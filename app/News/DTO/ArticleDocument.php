<?php

namespace App\News\DTO;

use Carbon\CarbonImmutable;

final readonly class ArticleDocument
{
    public function __construct(public string $title, public string $url, public string $text, public ?CarbonImmutable $publishedAt = null, public array $metadata = []) {}
}
