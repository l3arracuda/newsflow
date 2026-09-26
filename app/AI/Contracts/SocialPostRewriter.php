<?php

namespace App\AI\Contracts;

interface SocialPostRewriter
{
    public function rewrite(string $title, array $facts, string $summary, string $sourceName, string $sourceUrl): array;
}
