<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\WorkflowRun;
use App\Operations\Contracts\OperationalAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorOperations extends Command
{
    protected $signature = 'newsflow:operations:monitor';

    protected $description = 'Mark stale running workflows as failed and raise an operational alert.';

    public function handle(OperationalAlert $alerts): int
    {
        $threshold = max(1, (int) config('newsflow.stale_run_minutes', 60));
        $candidateIds = WorkflowRun::query()->where('status', WorkflowRunStatus::RUNNING)
            ->where('started_at', '<', now()->subMinutes($threshold))->pluck('id');
        $marked = 0;

        foreach ($candidateIds as $runId) {
            $changed = DB::transaction(function () use ($runId, $threshold) {
                $run = WorkflowRun::query()->lockForUpdate()->find($runId);
                if (! $run || $run->status !== WorkflowRunStatus::RUNNING || $run->started_at?->gte(now()->subMinutes($threshold))) {
                    return false;
                }
                $run->update(['status' => WorkflowRunStatus::FAILED, 'finished_at' => now(), 'error_summary' => 'Workflow exceeded the stale-running threshold.']);
                if ($run->article_id !== null) {
                    $run->article()->where('status', ArticleStatus::PROCESSING)->update(['status' => ArticleStatus::FAILED]);
                }
                $run->auditLogs()->create([
                    'event' => 'workflow.stale_failed',
                    'before_state' => ['status' => WorkflowRunStatus::RUNNING->value],
                    'after_state' => ['status' => WorkflowRunStatus::FAILED->value],
                    'metadata' => ['threshold_minutes' => $threshold],
                ]);

                return true;
            });
            if ($changed) {
                $marked++;
                $alerts->send('error', 'A workflow run exceeded the stale-running threshold.', ['workflow_run_id' => $runId, 'threshold_minutes' => $threshold]);
            }
        }

        $this->info('Stale workflow runs marked failed: '.$marked);

        return self::SUCCESS;
    }
}
