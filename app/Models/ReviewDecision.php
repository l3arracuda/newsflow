<?php

namespace App\Models;

use App\Enums\ReviewDecisionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewDecision extends Model
{
    use HasFactory;

    protected $fillable = ['generated_post_id', 'user_id', 'decision', 'note', 'decided_at', 'metadata'];

    protected function casts(): array
    {
        return ['decision' => ReviewDecisionType::class, 'decided_at' => 'datetime', 'metadata' => 'array'];
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(GeneratedPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
