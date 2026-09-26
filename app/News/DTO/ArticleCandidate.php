<?php

namespace App\News\DTO;

use Carbon\CarbonImmutable;

final readonly class ArticleCandidate
{
    public function __construct(public string $title, public string $url, public ?CarbonImmutable $publishedAt = null, public ?string $externalId = null) {}
}
