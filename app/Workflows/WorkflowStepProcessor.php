<?php

namespace App\Workflows;

use App\Models\Article;
use App\Models\WorkflowRun;

interface WorkflowStepProcessor
{
    /** @return array<string, mixed> Sanitized, non-secret step metadata. */
    public function process(Article $article, WorkflowRun $run): array;
}
