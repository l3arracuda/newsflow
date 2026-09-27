<?php

namespace App\Images\Contracts;

use App\Images\DTO\GeneratedImage;

interface ImageGenerationProvider
{
    public function generate(string $prompt, array $options = []): GeneratedImage;
}
