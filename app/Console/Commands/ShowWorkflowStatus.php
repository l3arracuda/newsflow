<?php

namespace App\Console\Commands;

use App\Workflows\WorkflowInspector;
use Illuminate\Console\Command;
use Throwable;

class ShowWorkflowStatus extends Command
{
    protected $signature = 'news:workflow:status {runId : Workflow run ID}';

    protected $description = 'Show workflow and step status, attempts, timing and sanitized errors.';

    public function handle(WorkflowInspector $inspector): int
    {
        try {
            $run = $inspector->find((int) $this->argument('runId'));
        } catch (Throwable) {
            $this->error('Workflow run was not found.');

            return self::FAILURE;
        }
        $this->line("Run {$run->id} | Article {$run->article_id} | {$run->status->value} | attempt {$run->attempt}");
        $this->table(['Step', 'Status', 'Attempt', 'Started', 'Finished', 'Error'], $run->steps->map(fn ($step) => [
            $step->step_key,
            $step->status->value,
            $step->attempt,
            $step->started_at?->toDateTimeString() ?? '—',
            $step->finished_at?->toDateTimeString() ?? '—',
            $step->error_summary ?? '—',
        ])->all());

        return self::SUCCESS;
    }
}
