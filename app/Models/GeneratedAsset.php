<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedAsset extends Model
{
    use HasFactory;

    protected $fillable = ['generated_post_id', 'version', 'provider', 'disk', 'path', 'mime_type', 'content_hash', 'metadata'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'metadata' => 'array'];
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(GeneratedPost::class);
    }
}
