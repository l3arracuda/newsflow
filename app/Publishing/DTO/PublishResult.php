<?php

namespace App\Publishing\DTO;

readonly class PublishResult
{
    public function __construct(public string $externalPostId, public ?string $externalUrl = null) {}
}
