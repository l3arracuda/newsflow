<?php

namespace App\Workflows;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Jobs\ProcessWorkflowJob;
use App\Models\WorkflowRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowRetrier
{
    public function retry(int $runId, ?string $stepKey = null): WorkflowRun
    {
        [$run, $failedStep] = DB::transaction(function () use ($runId, $stepKey) {
            $run = WorkflowRun::query()->lockForUpdate()->findOrFail($runId);
            if ($run->status !== WorkflowRunStatus::FAILED) {
                throw new InvalidArgumentException('Only a failed workflow run can be retried.');
            }
            $latestByStep = $run->steps()->orderBy('id')->get()->groupBy('step_key')->map(fn ($steps) => $steps->last());
            $latestByStep = $latestByStep->filter(fn ($step) => $step->status === WorkflowStepStatus::FAILED);
            $failedStep = $stepKey === null
                ? $latestByStep->sortByDesc('id')->first()
                : $latestByStep->get($stepKey);
            if (! $failedStep) {
                throw new InvalidArgumentException($stepKey === null ? 'No failed step is available to retry.' : "Step [{$stepKey}] is not failed in this run.");
            }

            $run->update([
                'status' => WorkflowRunStatus::PENDING,
                'attempt' => $run->attempt + 1,
                'started_at' => null,
                'finished_at' => null,
                'error_summary' => null,
            ]);
            $run->article?->update(['status' => ArticleStatus::PROCESSING]);
            $run->auditLogs()->create([
                'event' => 'workflow.retry_requested',
                'before_state' => ['run_status' => WorkflowRunStatus::FAILED->value],
                'after_state' => ['run_status' => WorkflowRunStatus::PENDING->value, 'failed_step' => $failedStep->step_key],
            ]);

            return [$run, $failedStep];
        });

        ProcessWorkflowJob::dispatch($run->id)->onConnection(config('queue.default'));

        return $run->refresh();
    }
}
