<?php

namespace App\AI\Contracts;

interface AiTextProvider
{
    /** @return array{data: array, model: ?string, usage: array} */
    public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array;
}
