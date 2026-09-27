<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedAsset extends Model
{
    use HasFactory;

    protected $fillable = ['generated_post_id', 'workflow_run_id', 'version', 'provider', 'provider_asset_id', 'disk', 'path', 'mime_type', 'width', 'height', 'content_hash', 'prompt_version', 'prompt_text', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'width' => 'integer', 'height' => 'integer', 'prompt_version' => 'integer', 'metadata' => 'array'];
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(GeneratedPost::class);
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
