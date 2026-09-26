<?php

namespace App\Workflows;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Article;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowEngine
{
    public function __construct(private readonly WorkflowProcessorRegistry $processors) {}

    public function run(int $runId): void
    {
        $started = DB::transaction(function () use ($runId) {
            $run = WorkflowRun::query()->lockForUpdate()->findOrFail($runId);
            if (! in_array($run->status, [WorkflowRunStatus::PENDING, WorkflowRunStatus::RUNNING], true)) {
                return false;
            }
            $wasPending = $run->status === WorkflowRunStatus::PENDING;
            $run->update(['status' => WorkflowRunStatus::RUNNING, 'started_at' => now(), 'finished_at' => null]);
            if ($wasPending) {
                $run->auditLogs()->create([
                    'event' => 'workflow.running',
                    'before_state' => ['run_status' => WorkflowRunStatus::PENDING->value],
                    'after_state' => ['run_status' => WorkflowRunStatus::RUNNING->value, 'attempt' => $run->attempt],
                ]);
            }

            return true;
        });

        if (! $started) {
            return;
        }

        $run = WorkflowRun::with('article')->findOrFail($runId);
        foreach (WorkflowStepCatalog::STEPS as $definition) {
            $latest = $run->steps()->where('step_key', $definition['key'])->orderByDesc('attempt')->orderByDesc('id')->first();
            if ($latest?->status === WorkflowStepStatus::SUCCEEDED) {
                continue;
            }
            $stepAttempt = $latest?->status === WorkflowStepStatus::FAILED ? $latest->attempt + 1 : ($latest?->attempt ?? 1);
            $step = WorkflowStepRun::query()->firstOrNew([
                'workflow_run_id' => $run->id,
                'step_key' => $definition['key'],
                'attempt' => $stepAttempt,
            ]);
            $step->fill([
                'name' => $definition['name'],
                'status' => WorkflowStepStatus::RUNNING,
                'started_at' => now(),
                'finished_at' => null,
                'error_summary' => null,
            ])->save();

            try {
                $metadata = $this->processors->for($definition['key'])->process($run->article, $run);
                DB::transaction(function () use ($step, $run, $definition, $metadata) {
                    $step->update(['status' => WorkflowStepStatus::SUCCEEDED, 'finished_at' => now(), 'metadata' => $metadata]);
                    if ($definition['key'] === 'awaiting_review') {
                        $article = Article::query()->lockForUpdate()->findOrFail($run->article_id);
                        $before = $article->status?->value;
                        $article->update(['status' => ArticleStatus::READY_FOR_REVIEW]);
                        $run->auditLogs()->create([
                            'event' => 'article.ready_for_review',
                            'before_state' => ['article_status' => $before],
                            'after_state' => ['article_status' => ArticleStatus::READY_FOR_REVIEW->value],
                        ]);
                    }
                });
            } catch (Throwable $exception) {
                $this->fail($run, $step, $exception);

                return;
            }
        }

        DB::transaction(function () use ($runId) {
            $run = WorkflowRun::query()->lockForUpdate()->findOrFail($runId);
            $run->update(['status' => WorkflowRunStatus::SUCCEEDED, 'finished_at' => now(), 'error_summary' => null]);
            $run->auditLogs()->create([
                'event' => 'workflow.succeeded',
                'after_state' => ['run_status' => WorkflowRunStatus::SUCCEEDED->value],
            ]);
        });
    }

    public function failUnexpectedly(int $runId, Throwable $exception): void
    {
        DB::transaction(function () use ($runId, $exception) {
            $run = WorkflowRun::query()->lockForUpdate()->find($runId);
            if (! $run || $run->status === WorkflowRunStatus::SUCCEEDED) {
                return;
            }
            $step = $run->steps()->where('status', WorkflowStepStatus::RUNNING->value)->latest('id')->first();
            if ($step) {
                $step->update(['status' => WorkflowStepStatus::FAILED, 'finished_at' => now(), 'error_summary' => $this->safeError($exception)]);
            }
            $run->update(['status' => WorkflowRunStatus::FAILED, 'finished_at' => now(), 'error_summary' => $this->safeError($exception)]);
            $run->article?->update(['status' => ArticleStatus::FAILED]);
            $run->auditLogs()->create([
                'event' => 'workflow.failed',
                'after_state' => ['run_status' => WorkflowRunStatus::FAILED->value, 'error_class' => $exception::class],
            ]);
        });
    }

    private function fail(WorkflowRun $run, WorkflowStepRun $step, Throwable $exception): void
    {
        $message = $this->safeError($exception);
        DB::transaction(function () use ($run, $step, $exception, $message) {
            $step->update([
                'status' => WorkflowStepStatus::FAILED,
                'finished_at' => now(),
                'error_summary' => $message,
                'metadata' => ['error_class' => $exception::class],
            ]);
            $run->update([
                'status' => WorkflowRunStatus::FAILED,
                'finished_at' => now(),
                'error_summary' => $message,
            ]);
            $article = Article::query()->lockForUpdate()->findOrFail($run->article_id);
            $before = $article->status?->value;
            $article->update(['status' => ArticleStatus::FAILED]);
            $run->auditLogs()->create([
                'event' => 'workflow.failed',
                'before_state' => ['article_status' => $before],
                'after_state' => ['run_status' => WorkflowRunStatus::FAILED->value, 'step_key' => $step->step_key, 'error_class' => $exception::class],
            ]);
        });
        Log::warning('Workflow step failed.', [
            'workflow_run_id' => $run->id,
            'step_key' => $step->step_key,
            'error_class' => $exception::class,
            'error_summary' => $message,
        ]);
    }

    private function safeError(Throwable $exception): string
    {
        $message = preg_replace('/(authorization\s*[:=]\s*bearer\s+)[^\s,;]+/i', '$1[REDACTED]', $exception->getMessage()) ?? '';
        $message = preg_replace('/\b(token|password|secret|api[_-]?key)\s*[:=]\s*[^&\s,;]+/i', '$1=[REDACTED]', $message) ?? '';
        $message = preg_replace('/([?&](?:token|password|secret|api[_-]?key)=)[^&\s]+/i', '$1[REDACTED]', $message) ?? '';

        return mb_substr($exception::class.': '.trim($message), 0, 1000);
    }
}
