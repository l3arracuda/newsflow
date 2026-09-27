<?php

namespace App\Images\Providers;

use App\Images\Contracts\ImageGenerationProvider;
use App\Images\DTO\GeneratedImage;
use RuntimeException;

class FakeImageGenerationProvider implements ImageGenerationProvider
{
    public function generate(string $prompt, array $options = []): GeneratedImage
    {
        if (config('services.image_generation.fake_fail')) {
            throw new RuntimeException('Fake image provider failure requested.');
        }

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4XcAAAAASUVORK5CYII=', true);
        if (! is_string($png)) {
            throw new RuntimeException('Fake image fixture could not be decoded.');
        }

        return new GeneratedImage($png, 'image/png', 'fake', null, ['fake' => true, 'generated_illustration' => true]);
    }
}
