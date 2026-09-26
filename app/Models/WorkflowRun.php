<?php

namespace App\Models;

use App\Enums\WorkflowRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkflowRun extends Model
{
    use HasFactory;

    protected $fillable = ['article_id', 'run_type', 'status', 'started_at', 'finished_at', 'error_summary', 'attempt', 'metadata'];

    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempt' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStepRun::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
