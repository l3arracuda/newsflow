<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['article_id', 'normalized_excerpt', 'fetched_at', 'checksum', 'metadata'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime', 'metadata' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
