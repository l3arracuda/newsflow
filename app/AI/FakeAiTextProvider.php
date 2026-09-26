<?php

namespace App\AI;

use App\AI\Contracts\AiTextProvider;
use RuntimeException;

class FakeAiTextProvider implements AiTextProvider
{
    public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array
    {
        $data = match ($operation) {
            'FACT_EXTRACT' => ['people_organizations' => [], 'places' => [], 'dates_times' => [], 'quantities_money' => [], 'event_action' => $input['title'], 'warnings_advice' => [], 'source_claims' => [$input['source_text']]],
            'NEWS_SUMMARY' => ['summary' => mb_substr($input['facts']['event_action'] ?? '', 0, 300)],
            'FACEBOOK_REWRITE' => ['title' => $input['title'], 'body' => ($input['summary'] ?? '')."\n\nที่มา: {$input['source_name']} {$input['source_url']}", 'hook' => $input['title']],
            'FACT_CHECK' => ['pass' => true, 'severity' => 'none', 'mismatches' => [], 'unsupported_claims' => []],
            default => throw new RuntimeException('Unsupported fake AI operation.'),
        };

        return ['data' => $data, 'model' => 'fake', 'usage' => []];
    }
}
