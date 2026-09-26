<?php

namespace App\Models;

use App\Enums\WorkflowStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepRun extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_run_id', 'step_key', 'name', 'status', 'started_at', 'finished_at', 'error_summary', 'attempt', 'metadata'];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStepStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempt' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
