<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Images\Providers\OpenAiImageGenerationProvider;
use App\Images\Services\EditorialImagePromptBuilder;
use App\Images\Services\GeneratedImageAssetService;
use App\Models\Article;
use App\Models\Source;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowStarter;
use Database\Seeders\AiPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new AiPromptTemplateSeeder)->run();
        Storage::fake('local');
    }

    public function test_fake_provider_persists_traceable_new_illustration(): void
    {
        Queue::fake();
        $article = $this->articleWithSnapshot(['source_image_url' => 'https://source.test/original.jpg']);
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $asset = $article->generatedPosts()->firstOrFail()->assets()->firstOrFail();
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status);
        $this->assertSame('fake', $asset->provider);
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertSame([1, 1], [$asset->width, $asset->height]);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($asset->path)), $asset->content_hash);
        $this->assertSame(1, $asset->prompt_version);
        $this->assertTrue($asset->metadata['generated_illustration']);
        $this->assertFalse($asset->metadata['source_image_reference_used']);
        $this->assertStringNotContainsString('source.test/original.jpg', $asset->prompt_text);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'generated_asset.created']);
        $promptData = $run->steps()->where('step_key', 'image_prompt')->firstOrFail()->metadata['prompt_result'];
        $retryResult = app(GeneratedImageAssetService::class)->generate($asset->generatedPost, $promptData, $run);
        $this->assertSame($asset->id, $retryResult->id);
        $this->assertSame(1, $asset->generatedPost->assets()->count());
    }

    public function test_failed_provider_fails_workflow_without_creating_asset(): void
    {
        Queue::fake();
        config(['services.image_generation.fake_fail' => true]);
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->assertSame(WorkflowRunStatus::FAILED, $run->fresh()->status);
        $this->assertSame('failed', $run->steps()->where('step_key', 'image_generate')->firstOrFail()->status->value);
        $this->assertDatabaseCount('generated_assets', 0);
    }

    public function test_regeneration_creates_a_new_version_and_preserves_the_old_asset(): void
    {
        Queue::fake();
        $article = $this->articleWithSnapshot();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);
        $post = $article->generatedPosts()->firstOrFail();
        $first = $post->assets()->firstOrFail();
        $prompt = app(EditorialImagePromptBuilder::class)->build($article, $run);

        $second = app(GeneratedImageAssetService::class)->generate($post, $prompt);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(2, $post->assets()->count());
        Storage::disk('local')->assertExists($first->path);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_sensitive_story_prompt_avoids_graphic_and_identifiable_depictions(): void
    {
        Queue::fake();
        $article = $this->articleWithSnapshot(['sensitivity' => 'high']);
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);
        $asset = $article->generatedPosts()->firstOrFail()->assets()->firstOrFail();

        $this->assertSame('high', $asset->metadata['sensitivity']);
        $this->assertContains('graphic injury', $asset->metadata['avoid']);
        $this->assertStringContainsString('non-graphic', $asset->prompt_text);
    }

    public function test_openai_adapter_uses_mocked_base64_response_and_never_accepts_reference_images(): void
    {
        config(['services.image_generation.api_key' => 'test-key', 'services.image_generation.model' => 'gpt-image-1', 'services.image_generation.quality' => 'low']);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4XcAAAAASUVORK5CYII=';
        Http::fake(['*/images/generations' => Http::response(['data' => [['b64_json' => $png]]])]);

        $image = app(OpenAiImageGenerationProvider::class)->generate('new editorial illustration', ['avoid' => ['logos']]);

        $this->assertSame('image/png', $image->mimeType);
        $this->assertSame(base64_decode($png), $image->contents);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request['model'] === 'gpt-image-1'
            && $request['quality'] === 'low'
            && str_contains($request['prompt'], 'Avoid: logos')
            && ! array_key_exists('image', $request->data()));
    }

    public function test_prompt_builder_uses_facts_not_source_image_metadata(): void
    {
        $article = $this->articleWithSnapshot(['source_image_url' => 'https://source.test/original.jpg']);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => 'running', 'attempt' => 1]);
        $run->steps()->create(['step_key' => 'extract_facts', 'name' => 'facts', 'status' => 'succeeded', 'attempt' => 1, 'metadata' => ['facts' => ['event_action' => 'มีการประกาศมาตรการ', 'places' => []]]]);

        $prompt = app(EditorialImagePromptBuilder::class)->build($article, $run);

        $this->assertStringContainsString('มีการประกาศมาตรการ', $prompt['prompt']);
        $this->assertStringNotContainsString('source.test/original.jpg', $prompt['prompt']);
        $this->assertTrue($prompt['generated_illustration']);
    }

    private function articleWithSnapshot(array $metadata = []): Article
    {
        $source = Source::create([
            'name' => 'ThaiRath Society', 'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath', 'is_active' => true,
        ]);
        $article = $source->articles()->create([
            'source_url' => 'https://www.thairath.co.th/news/society/image-pipeline-test',
            'canonical_url' => 'https://www.thairath.co.th/news/society/image-pipeline-test',
            'title' => 'ข่าวทดสอบภาพประกอบ', 'discovered_at' => now(), 'status' => ArticleStatus::FETCHED,
            'metadata' => $metadata,
        ]);
        $article->snapshots()->create([
            'normalized_excerpt' => 'มีการประกาศมาตรการในกรุงเทพฯ เพื่อช่วยเหลือประชาชน',
            'fetched_at' => now(), 'checksum' => hash('sha256', 'image-pipeline-test-snapshot'),
        ]);

        return $article;
    }
}
