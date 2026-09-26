<?php

namespace App\Workflows;

use App\Models\WorkflowRun;

class WorkflowInspector
{
    public function find(int $runId): WorkflowRun
    {
        return WorkflowRun::with(['article', 'steps' => fn ($query) => $query->orderBy('id')])->findOrFail($runId);
    }
}
