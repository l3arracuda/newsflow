<?php

namespace App\AI\Contracts;

interface FactExtractor
{
    public function extract(string $title, string $sourceText): array;
}
