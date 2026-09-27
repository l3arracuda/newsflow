<?php

namespace App\AI;

class FactEvidenceValidator
{
    public const SCHEMA_VERSION = 2;

    public function validate(array $facts, string $sourceText): array
    {
        foreach (['evidence_claims', 'quantitative_claims'] as $key) {
            $facts[$key] = array_values(array_map(function (array $claim) use ($sourceText) {
                $quote = trim((string) ($claim['source_quote'] ?? ''));
                $claim['evidence_verified'] = $quote !== '' && $this->contains($sourceText, $quote);

                return $claim;
            }, array_filter($facts[$key] ?? [], 'is_array')));
        }

        $facts['evidence_schema_version'] = self::SCHEMA_VERSION;

        return $facts;
    }

    public function contains(string $text, string $quote): bool
    {
        $normalize = static fn (string $value): string => preg_replace('/[\p{Z}\s]+/u', ' ', trim($value)) ?? trim($value);

        return $quote !== '' && str_contains($normalize($text), $normalize($quote));
    }
}
