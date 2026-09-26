<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['source_id', 'source_external_id', 'source_url', 'canonical_url', 'title', 'source_published_at', 'discovered_at', 'content_hash', 'status', 'metadata'];

    protected function casts(): array
    {
        return [
            'source_published_at' => 'datetime',
            'discovered_at' => 'datetime',
            'status' => ArticleStatus::class,
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ArticleSnapshot::class);
    }

    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function generatedPosts(): HasMany
    {
        return $this->hasMany(GeneratedPost::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
