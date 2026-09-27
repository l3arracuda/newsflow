<?php

namespace App\AI\Pipelines;

use App\AI\Contracts\AiTextProvider;
use App\AI\Contracts\ArticleSummarizer;
use App\AI\Contracts\FactConsistencyChecker;
use App\AI\Contracts\FactExtractor;
use App\AI\Contracts\SocialPostRewriter;
use App\AI\FactCheckReconciler;
use App\Models\PromptTemplate;
use Illuminate\Support\Carbon;
use RuntimeException;

class AiTextPipeline implements ArticleSummarizer, FactConsistencyChecker, FactExtractor, SocialPostRewriter
{
    public function __construct(private readonly AiTextProvider $provider, private readonly FactCheckReconciler $factChecks) {}

    public function extract(string $title, string $sourceText): array
    {
        return $this->call('FACT_EXTRACT', ['title' => $title, 'source_text' => $sourceText], [
            'people_organizations' => [], 'places' => [], 'dates_times' => [], 'quantities_money' => [],
            'event_action' => '', 'warnings_advice' => [], 'source_claims' => [],
            'evidence_claims' => [['statement' => '', 'source_quote' => '']],
            'quantitative_claims' => [['subject' => '', 'relation' => '', 'value' => '', 'unit' => '', 'source_quote' => '']],
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
            'pass' => true,
            'severity' => 'none',
            'mismatches' => [['draft_quote' => '', 'source_quote' => '', 'explanation' => '']],
            'unsupported_claims' => [['draft_quote' => '', 'explanation' => '']],
            'quantitative_claims' => [['draft_quote' => '', 'subject' => '', 'relation' => '', 'value' => '', 'unit' => '']],
        ]);
        $result = $this->factChecks->reconcile($facts, $draft, $result);
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
            $properties[$key] = $this->propertySchema($value);
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    private function propertySchema(mixed $sample): array
    {
        if (! is_array($sample)) {
            return match (true) {
                is_bool($sample) => ['type' => 'boolean'],
                is_int($sample) => ['type' => 'integer'],
                is_float($sample) => ['type' => 'number'],
                default => ['type' => 'string'],
            };
        }

        if (array_is_list($sample)) {
            return ['type' => 'array', 'items' => $this->propertySchema($sample[0] ?? '')];
        }

        $properties = [];
        foreach ($sample as $key => $value) {
            $properties[$key] = $this->propertySchema($value);
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }
}
