<?php

namespace Tests\Feature;

use App\AI\Contracts\AiTextProvider;
use App\AI\FakeAiTextProvider;
use App\AI\OpenAiTextProvider;
use App\AI\Pipelines\AiTextPipeline;
use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Article;
use App\Models\Source;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowRetrier;
use App\Workflows\WorkflowStarter;
use Database\Seeders\AiPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AiTextPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new AiPromptTemplateSeeder)->run();
    }

    public function test_fake_provider_completes_pipeline_and_records_prompt_provenance(): void
    {
        Queue::fake();
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status);
        $this->assertSame(ArticleStatus::READY_FOR_REVIEW, $article->fresh()->status);
        $this->assertDatabaseCount('generated_posts', 1);
        $post = $article->generatedPosts()->firstOrFail();
        $this->assertStringContainsString($article->source_url, $post->draft_text);
        $this->assertSame($run->id, $post->workflow_run_id);
        $facts = $run->steps()->where('step_key', 'extract_facts')->firstOrFail()->metadata['facts'];
        $this->assertSame(1, $facts['_provenance']['prompt_template_version']);
        $this->assertTrue($run->steps()->where('step_key', 'fact_check')->firstOrFail()->metadata['pass']);
    }

    public function test_malformed_structured_output_is_rejected(): void
    {
        $this->app->instance(AiTextProvider::class, new class implements AiTextProvider
        {
            public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array
            {
                return ['data' => ['summary' => ['wrong-type']], 'model' => 'test', 'usage' => []];
            }
        });

        $this->expectException(RuntimeException::class);
        app(AiTextPipeline::class)->summarize(['event_action' => 'ข่าว']);
    }

    public function test_openai_adapter_uses_configured_model_and_structured_output_without_live_network(): void
    {
        config(['services.ai_text.api_key' => 'test-key', 'services.ai_text.model' => 'test-model']);
        Http::fake(['*/chat/completions' => Http::response([
            'model' => 'test-model',
            'choices' => [['message' => ['content' => '{"summary":"สรุปทดสอบ"}']]],
            'usage' => ['total_tokens' => 12],
        ])]);

        $result = app(OpenAiTextProvider::class)->generate('NEWS_SUMMARY', 'system', 'task', ['facts' => []], [
            'type' => 'object', 'properties' => ['summary' => ['type' => 'string']], 'required' => ['summary'], 'additionalProperties' => false,
        ], ['temperature' => 0]);

        $this->assertSame(['summary' => 'สรุปทดสอบ'], $result['data']);
        $this->assertSame(12, $result['usage']['total_tokens']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request['model'] === 'test-model'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['temperature'] === 0);
    }

    public function test_unsupported_claim_flags_article_and_prevents_ready_for_review(): void
    {
        Queue::fake();
        $provider = new class implements AiTextProvider
        {
            public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array
            {
                if ($operation === 'FACT_CHECK') {
                    return ['data' => ['pass' => false, 'severity' => 'high', 'mismatches' => [], 'unsupported_claims' => ['ยอดเงินไม่อยู่ในข้อมูลต้นทาง']], 'model' => 'test', 'usage' => []];
                }

                return app(FakeAiTextProvider::class)->generate($operation, $systemPrompt, $instruction, $input, $schema, $parameters);
            }
        };
        $this->app->instance(AiTextProvider::class, $provider);
        $this->app->forgetInstance(AiTextPipeline::class);
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status);
        $this->assertSame(ArticleStatus::FLAGGED, $article->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'article.flagged_for_fact_check']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'article.ready_for_review']);
    }

    public function test_provider_timeout_is_recorded_as_workflow_failure(): void
    {
        Queue::fake();
        $provider = new class implements AiTextProvider
        {
            public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array
            {
                if ($operation === 'FACT_EXTRACT') {
                    throw new RuntimeException('provider timed out');
                }

                return app(FakeAiTextProvider::class)->generate($operation, $systemPrompt, $instruction, $input, $schema, $parameters);
            }
        };
        $this->app->instance(AiTextProvider::class, $provider);
        $this->app->forgetInstance(AiTextPipeline::class);
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->assertSame(WorkflowRunStatus::FAILED, $run->fresh()->status);
        $this->assertSame('failed', $run->steps()->where('step_key', 'extract_facts')->firstOrFail()->status->value);
        $this->assertDatabaseCount('generated_posts', 0);
    }

    public function test_retrying_rewrite_does_not_create_another_post_version(): void
    {
        Queue::fake();
        $provider = new class implements AiTextProvider
        {
            private bool $failed = false;

            public function generate(string $operation, string $systemPrompt, string $instruction, array $input, array $schema, array $parameters): array
            {
                if ($operation === 'FACEBOOK_REWRITE' && ! $this->failed) {
                    $this->failed = true;
                    throw new RuntimeException('temporary provider failure');
                }

                return app(FakeAiTextProvider::class)->generate($operation, $systemPrompt, $instruction, $input, $schema, $parameters);
            }
        };
        $this->app->instance(AiTextProvider::class, $provider);
        $this->app->forgetInstance(AiTextPipeline::class);
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);
        $this->assertDatabaseCount('generated_posts', 0);
        app(WorkflowRetrier::class)->retry($run->id, 'rewrite');
        app(WorkflowEngine::class)->run($run->id);
        $this->assertDatabaseCount('generated_posts', 1);

        $post = $article->generatedPosts()->firstOrFail();
        $this->assertSame(1, $post->version);
        $this->assertDatabaseHas('generated_posts', ['workflow_run_id' => $run->id, 'version' => 1]);
        $this->assertSame(2, $run->steps()->where('step_key', 'rewrite')->count());
    }

    private function articleWithSnapshot(): Article
    {
        $source = Source::create([
            'name' => 'ThaiRath Society', 'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath', 'is_active' => true,
        ]);
        $article = $source->articles()->create([
            'source_url' => 'https://www.thairath.co.th/news/society/ai-pipeline-test',
            'canonical_url' => 'https://www.thairath.co.th/news/society/ai-pipeline-test',
            'title' => 'ข่าวทดสอบ AI pipeline', 'discovered_at' => now(), 'status' => ArticleStatus::FETCHED,
        ]);
        $article->snapshots()->create([
            'normalized_excerpt' => 'เจ้าหน้าที่ประกาศมาตรการในกรุงเทพฯ เมื่อวันที่ 27 กันยายน 2569 โดยไม่ระบุจำนวนเงิน',
            'fetched_at' => now(), 'checksum' => hash('sha256', 'ai-pipeline-test-snapshot'),
        ]);

        return $article;
    }
}
