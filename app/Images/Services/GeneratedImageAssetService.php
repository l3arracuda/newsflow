<?php

namespace App\Images\Services;

use App\Images\Contracts\GeneratedAssetStorage;
use App\Images\Contracts\ImageGenerationProvider;
use App\Models\GeneratedAsset;
use App\Models\GeneratedPost;
use App\Models\WorkflowRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GeneratedImageAssetService
{
    public function __construct(
        private readonly ImageGenerationProvider $provider,
        private readonly GeneratedAssetStorage $storage,
    ) {}

    public function generate(GeneratedPost $post, array $promptData, ?WorkflowRun $run = null): GeneratedAsset
    {
        return Cache::lock('image-generation-post-'.$post->id, 180)->block(15, function () use ($post, $promptData, $run) {
            if ($run) {
                $existing = GeneratedAsset::query()->where('workflow_run_id', $run->id)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $prompt = trim((string) ($promptData['prompt'] ?? ''));
            if ($prompt === '' || ($promptData['generated_illustration'] ?? false) !== true) {
                throw new RuntimeException('A safe generated-illustration prompt is required.');
            }
            $image = $this->provider->generate($prompt, [
                'aspect_ratio' => $promptData['aspect_ratio'] ?? '1:1',
                'avoid' => $promptData['avoid'] ?? [],
            ]);
            if (strlen($image->contents) > (int) config('services.image_generation.max_bytes', 20971520)) {
                throw new RuntimeException('Generated image exceeds the configured size limit.');
            }
            $dimensions = @getimagesizefromstring($image->contents);
            $mime = $dimensions['mime'] ?? null;
            if (! is_array($dimensions) || ! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $mime !== $image->mimeType) {
                throw new RuntimeException('Image provider returned an unsupported or invalid image file.');
            }

            $hash = hash('sha256', $image->contents);
            $version = ((int) $post->assets()->max('version')) + 1;
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $path = "generated/newsflow/{$post->id}/{$version}-{$hash}.{$extension}";
            $this->storage->put($path, $image->contents);

            try {
                return DB::transaction(function () use ($post, $run, $version, $image, $path, $mime, $dimensions, $hash, $promptData, $prompt) {
                    $asset = $post->assets()->create([
                        'workflow_run_id' => $run?->id,
                        'version' => $version,
                        'provider' => $image->provider,
                        'provider_asset_id' => $image->providerAssetId,
                        'disk' => config('services.image_generation.disk', 'local'),
                        'path' => $path,
                        'mime_type' => $mime,
                        'width' => $dimensions[0],
                        'height' => $dimensions[1],
                        'content_hash' => $hash,
                        'prompt_version' => (int) ($promptData['prompt_version'] ?? 1),
                        'prompt_text' => $prompt,
                        'status' => 'generated',
                        'metadata' => array_merge($image->metadata, [
                            'generated_illustration' => true,
                            'style' => $promptData['style'] ?? 'editorial_illustration',
                            'aspect_ratio' => $promptData['aspect_ratio'] ?? '1:1',
                            'sensitivity' => $promptData['sensitivity'] ?? 'standard',
                            'avoid' => $promptData['avoid'] ?? [],
                            'safety_notes' => $promptData['safety_notes'] ?? [],
                            'source_image_reference_used' => false,
                        ]),
                    ]);
                    $audit = ['event' => 'generated_asset.created', 'after_state' => ['asset_id' => $asset->id, 'version' => $version, 'provider' => $image->provider, 'content_hash' => $hash]];
                    if ($run) {
                        $run->auditLogs()->create($audit);
                    } else {
                        $post->auditLogs()->create($audit);
                    }

                    return $asset;
                });
            } catch (Throwable $exception) {
                $this->storage->delete($path);
                if ($run && $existing = GeneratedAsset::query()->where('workflow_run_id', $run->id)->first()) {
                    return $existing;
                }

                throw $exception;
            }
        });
    }
}
