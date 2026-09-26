<?php

namespace App\AI\Pipelines;

use App\AI\Contracts\AiTextProvider;
use App\AI\Contracts\ArticleSummarizer;
use App\AI\Contracts\FactConsistencyChecker;
use App\AI\Contracts\FactExtractor;
use App\AI\Contracts\SocialPostRewriter;
use App\Models\PromptTemplate;
use Illuminate\Support\Carbon;
use RuntimeException;

class AiTextPipeline implements ArticleSummarizer, FactConsistencyChecker, FactExtractor, SocialPostRewriter
{
    public function __construct(private readonly AiTextProvider $provider) {}

    public function extract(string $title, string $sourceText): array
    {
        return $this->call('FACT_EXTRACT', ['title' => $title, 'source_text' => $sourceText], [
            'people_organizations' => [], 'places' => [], 'dates_times' => [], 'quantities_money' => [],
            'event_action' => '', 'warnings_advice' => [], 'source_claims' => [],
        ]);
    }

    public function summarize(array $facts): array
    {
        return $this->call('NEWS_SUMMARY', ['facts' => $facts], ['summary' => '']);
    }

    public function rewrite(string $title, array $facts, string $summary, string $sourceName, string $sourceUrl): array
    {
        return $this->call('FACEBOOK_REWRITE', ['title' => $title, 'facts' => $facts, 'summary' => $summary, 'source_name' => $sourceName, 'source_url' => $sourceUrl], [
            'title' => '', 'body' => '', 'hook' => '',
        ]);
    }

    public function check(array $facts, array $draft): array
    {
        $result = $this->call('FACT_CHECK', compact('facts', 'draft'), [
            'pass' => true, 'severity' => 'none', 'mismatches' => [], 'unsupported_claims' => [],
        ]);
        $result['checked_at'] = Carbon::now()->toIso8601String();

        return $result;
    }

    private function call(string $key, array $input, array $expected): array
    {
        $template = PromptTemplate::query()->where('key', $key)->where('is_active', true)->orderByDesc('version')->first();
        if (! $template || ! $template->system_prompt || ! $template->instruction) {
            throw new RuntimeException("Active AI prompt template [{$key}] is not configured. Run database seeding.");
        }

        $schema = $this->schema($expected);
        $response = $this->provider->generate($key, $template->system_prompt, $template->instruction, $input, $schema, $template->parameters ?? []);
        $data = $response['data'] ?? null;
        if (! is_array($data) || array_keys($data) !== array_keys($expected)) {
            throw new RuntimeException("AI provider returned malformed structured output for [{$key}].");
        }
        foreach ($expected as $field => $sample) {
            if (is_string($sample) && ! is_string($data[$field])) {
                throw new RuntimeException("AI output field [{$field}] must be text.");
            }
            if (is_array($sample) && ! is_array($data[$field])) {
                throw new RuntimeException("AI output field [{$field}] must be a list.");
            }
            if (is_bool($sample) && ! is_bool($data[$field])) {
                throw new RuntimeException("AI output field [{$field}] must be boolean.");
            }
        }
        if ($key === 'FACT_CHECK' && ! in_array($data['severity'], ['none', 'low', 'medium', 'high'], true)) {
            throw new RuntimeException('AI fact check returned an invalid severity.');
        }
        if ($key === 'FACT_CHECK' && $data['pass'] && ($data['mismatches'] || $data['unsupported_claims'])) {
            $data['pass'] = false;
        }

        return $data + ['_provenance' => [
            'prompt_template_id' => $template->id,
            'prompt_template_key' => $template->key,
            'prompt_template_version' => $template->version,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? [],
        ]];
    }

    private function schema(array $shape): array
    {
        $properties = [];
        foreach ($shape as $key => $value) {
            $properties[$key] = match (true) {
                is_string($value) => ['type' => 'string'],
                is_bool($value) => ['type' => 'boolean'],
                is_array($value) => ['type' => 'array', 'items' => ['type' => 'string']],
                default => ['type' => 'string'],
            };
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }
}
