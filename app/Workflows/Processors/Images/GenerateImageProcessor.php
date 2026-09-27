<?php

namespace App\Workflows\Processors\Images;

use App\Images\Services\GeneratedImageAssetService;
use App\Models\Article;
use App\Models\GeneratedPost;
use App\Models\WorkflowRun;
use App\Workflows\WorkflowStepProcessor;
use RuntimeException;

class GenerateImageProcessor implements WorkflowStepProcessor
{
    public function __construct(private readonly GeneratedImageAssetService $assets) {}

    public function process(Article $article, WorkflowRun $run): array
    {
        $promptData = $run->steps()->where('step_key', 'image_prompt')->where('status', 'succeeded')->latest('attempt')->first()?->metadata['prompt_result'] ?? null;
        $post = GeneratedPost::query()->where('workflow_run_id', $run->id)->first();
        if (! is_array($promptData) || ! $post) {
            throw new RuntimeException('Image prompt and generated post are required before image generation.');
        }
        $asset = $this->assets->generate($post, $promptData, $run);

        return ['generated_asset_id' => $asset->id, 'version' => $asset->version, 'provider' => $asset->provider, 'content_hash' => $asset->content_hash, 'generated_illustration' => true];
    }
}
