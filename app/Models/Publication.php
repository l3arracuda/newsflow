<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Publication extends Model
{
    use HasFactory;

    protected $fillable = ['generated_post_id', 'channel', 'provider', 'external_post_id', 'external_url', 'status', 'published_at', 'idempotency_key', 'metadata'];

    protected function casts(): array
    {
        return ['status' => PublicationStatus::class, 'published_at' => 'datetime', 'metadata' => 'array'];
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(GeneratedPost::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
