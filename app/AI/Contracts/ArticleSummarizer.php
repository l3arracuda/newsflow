<?php

namespace App\AI\Contracts;

interface ArticleSummarizer
{
    public function summarize(array $facts): array;
}
