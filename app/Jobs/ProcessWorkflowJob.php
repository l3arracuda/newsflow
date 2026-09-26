<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use App\Workflows\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly int $workflowRunId) {}

    public function middleware(): array
    {
        $articleId = WorkflowRun::whereKey($this->workflowRunId)->value('article_id') ?? 'missing';

        return [(new WithoutOverlapping('workflow-article-'.$articleId))->releaseAfter(5)->expireAfter(600)];
    }

    public function handle(WorkflowEngine $engine): void
    {
        $engine->run($this->workflowRunId);
    }

    public function failed(Throwable $exception): void
    {
        app(WorkflowEngine::class)->failUnexpectedly($this->workflowRunId, $exception);
    }
}
