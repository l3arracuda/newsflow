<?php

namespace App\Publishing\DTO;

readonly class PublicationRequest
{
    public function __construct(
        public string $message,
        public string $sourceUrl,
        public string $idempotencyKey,
        public string $imageContents,
        public string $imageMimeType,
        public string $imageFileName,
    ) {}
}
