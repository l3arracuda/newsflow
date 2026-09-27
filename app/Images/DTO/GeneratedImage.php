<?php

namespace App\Images\DTO;

class GeneratedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly string $provider,
        public readonly ?string $providerAssetId = null,
        public readonly array $metadata = [],
    ) {}
}
