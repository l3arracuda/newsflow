<?php

namespace App\Images\Providers;

use App\Images\Contracts\ImageGenerationProvider;
use App\Images\DTO\GeneratedImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiImageGenerationProvider implements ImageGenerationProvider
{
    public function generate(string $prompt, array $options = []): GeneratedImage
    {
        $key = config('services.image_generation.api_key');
        if (! $key) {
            throw new RuntimeException('Image provider is selected but its API key is not configured.');
        }
        if (! str_starts_with((string) config('services.image_generation.model'), 'gpt-image-')) {
            throw new RuntimeException('OpenAI image adapter expects a GPT image model that returns base64 image data.');
        }
        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout((int) config('services.image_generation.timeout', 90))
            ->retry((int) config('services.image_generation.retries', 1), 500, throw: false)
            ->post(rtrim(config('services.image_generation.base_url'), '/').'/images/generations', [
                'model' => config('services.image_generation.model'),
                'prompt' => $prompt."\nAvoid: ".implode('; ', array_filter($options['avoid'] ?? [], 'is_string')),
                'size' => config('services.image_generation.size', '1024x1024'),
                'n' => 1,
                'output_format' => 'png',
            ]);
        $response->throw();
        $encoded = $response->json('data.0.b64_json');
        $contents = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Image provider did not return a valid base64 image.');
        }

        return new GeneratedImage($contents, 'image/png', 'openai', $response->json('data.0.id'), ['model' => $response->json('model'), 'created' => $response->json('created')]);
    }
}
