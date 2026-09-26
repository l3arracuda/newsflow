<?php

namespace App\Workflows;

use App\Enums\ArticleStatus;
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
}
