<?php

namespace App\Workflows;

use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\WorkflowRunStatus;
use App\Jobs\ProcessWorkflowJob;
use App\Models\Article;
use App\Models\WorkflowRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WorkflowStarter
{
    /** @return array{run: WorkflowRun, dispatched: bool} */
    public function start(Article $article): array
    {
        [$run, $dispatch] = Cache::lock('workflow-start-article-'.$article->id, 10)->block(5, fn () => DB::transaction(function () use ($article) {
            $lockedArticle = Article::query()->lockForUpdate()->findOrFail($article->id);
            $active = $lockedArticle->workflowRuns()
                ->whereIn('status', [WorkflowRunStatus::PENDING->value, WorkflowRunStatus::RUNNING->value])
                ->latest('id')->first();
            if ($active) {
                return [$active, false];
            }
            $successful = $lockedArticle->workflowRuns()->where('status', WorkflowRunStatus::SUCCEEDED->value)->latest('id')->first();
            if ($successful) {
                return [$successful, false];
            }

            $run = $lockedArticle->workflowRuns()->create([
                'run_type' => 'article_pipeline',
                'status' => WorkflowRunStatus::PENDING,
                'attempt' => 1,
                'metadata' => ['trigger' => 'manual'],
            ]);
            $before = $lockedArticle->status?->value;
            $lockedArticle->update(['status' => ArticleStatus::PROCESSING]);
            $run->auditLogs()->create([
                'event' => 'workflow.started',
                'before_state' => ['article_status' => $before],
                'after_state' => ['run_status' => WorkflowRunStatus::PENDING->value, 'article_status' => ArticleStatus::PROCESSING->value],
            ]);

            return [$run, true];
        }));

        if ($dispatch) {
            ProcessWorkflowJob::dispatch($run->id)->onConnection(config('queue.default'));
        }

        return ['run' => $run->refresh(), 'dispatched' => $dispatch];
    }

    public function reprocessPlaceholder(Article $article): WorkflowRun
    {
        [$run, $dispatch] = Cache::lock('workflow-start-article-'.$article->id, 10)->block(5, fn () => DB::transaction(function () use ($article) {
            $lockedArticle = Article::query()->lockForUpdate()->findOrFail($article->id);
            $active = $lockedArticle->workflowRuns()
                ->whereIn('status', [WorkflowRunStatus::PENDING->value, WorkflowRunStatus::RUNNING->value])
                ->latest('id')->first();
            if ($active) {
                throw new \InvalidArgumentException('Article already has an active workflow.');
            }

            if ($lockedArticle->generatedPosts()->whereIn('status', [
                GeneratedPostStatus::APPROVED->value,
                GeneratedPostStatus::PUBLISHING->value,
                GeneratedPostStatus::PUBLISHED->value,
            ])->exists() || $lockedArticle->generatedPosts()->whereHas('publications')->exists()) {
                throw new \InvalidArgumentException('Cannot reprocess an article that has an approved or published post.');
            }

            $previousRun = $lockedArticle->workflowRuns()->where('status', WorkflowRunStatus::SUCCEEDED->value)->latest('id')->first();
            if (! $previousRun) {
                throw new \InvalidArgumentException('No successful workflow is available to reprocess.');
            }

            $hasPlaceholder = $previousRun->steps()->where('status', 'succeeded')->get()->contains(
                fn ($step) => (bool) ($step->metadata['placeholder'] ?? false)
            ) || $lockedArticle->generatedPosts()->get()->contains(
                fn ($post) => (bool) ($post->metadata['placeholder'] ?? false)
            );
            if (! $hasPlaceholder) {
                throw new \InvalidArgumentException('The latest workflow has no placeholder output to replace.');
            }

            $run = $lockedArticle->workflowRuns()->create([
                'run_type' => 'article_pipeline',
                'status' => WorkflowRunStatus::PENDING,
                'attempt' => 1,
                'metadata' => ['trigger' => 'manual_placeholder_reprocess', 'previous_run_id' => $previousRun->id],
            ]);
            $before = $lockedArticle->status?->value;
            $lockedArticle->update(['status' => ArticleStatus::PROCESSING]);
            $run->auditLogs()->create([
                'event' => 'workflow.placeholder_reprocess_started',
                'before_state' => ['article_status' => $before, 'previous_run_id' => $previousRun->id],
                'after_state' => ['run_status' => WorkflowRunStatus::PENDING->value, 'article_status' => ArticleStatus::PROCESSING->value],
            ]);
            $lockedArticle->auditLogs()->create([
                'event' => 'workflow.placeholder_reprocess_started',
                'before_state' => ['article_status' => $before],
                'after_state' => ['article_status' => ArticleStatus::PROCESSING->value, 'workflow_run_id' => $run->id, 'previous_run_id' => $previousRun->id],
            ]);

            return [$run, true];
        }));

        if ($dispatch) {
            ProcessWorkflowJob::dispatch($run->id)->onConnection(config('queue.default'));
        }

        return $run->refresh();
    }
}
