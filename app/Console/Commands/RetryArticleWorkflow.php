<?php

namespace App\Console\Commands;

use App\Workflows\WorkflowRetrier;
use Illuminate\Console\Command;
use Throwable;

class RetryArticleWorkflow extends Command
{
    protected $signature = 'news:workflow:retry {runId : Workflow run ID} {--step= : Retry this failed step instead of the first failed step}';

    protected $description = 'Retry a failed workflow from its failed step.';

    public function handle(WorkflowRetrier $retrier): int
    {
        try {
            $run = $retrier->retry((int) $this->argument('runId'), $this->option('step') ?: null);
        } catch (Throwable $exception) {
            $this->error('Workflow retry was not accepted: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Queued retry for workflow run '.$run->id.'.');

        return self::SUCCESS;
    }
}
