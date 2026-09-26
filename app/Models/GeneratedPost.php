<?php

namespace App\Models;

use App\Enums\GeneratedPostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GeneratedPost extends Model
{
    use HasFactory;

    protected $fillable = ['article_id', 'version', 'status', 'draft_text', 'source_attribution', 'source_url', 'metadata'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'status' => GeneratedPostStatus::class, 'metadata' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(GeneratedAsset::class);
    }

    public function reviewDecisions(): HasMany
    {
        return $this->hasMany(ReviewDecision::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
