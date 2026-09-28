<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\PublicationStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowStarter;
use Database\Seeders\AiPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsFlowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new AiPromptTemplateSeeder)->run();
        Storage::fake('local');
        Queue::fake();
        config(['services.facebook.driver' => 'fake', 'services.facebook.enabled' => true]);
        Cache::forget('robots:thairath:NewsFlow');
    }

    public function test_single_article_runs_from_source_scan_through_approval_and_fake_facebook_publish(): void
    {
        $source = $this->source();
        $listing = '<html><body><article><a href="/news/society/45678">ข่าวจังหวัดตัวอย่าง ระบบเตรียมรับมือฝน</a><time datetime="2026-09-27T08:15:00+07:00"></time></article></body></html>';
        $articleHtml = file_get_contents(base_path('tests/Fixtures/source-fetching/article.html'));
        Http::fake(function ($request) use ($listing, $articleHtml) {
            $url = $request->url();
            if (str_ends_with($url, '/robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200, ['Content-Type' => 'text/plain']);
            }
            if (str_ends_with($url, '/news/society')) {
                return Http::response($listing, 200, ['Content-Type' => 'text/html; charset=utf-8']);
            }
            if (str_ends_with($url, '/news/society/45678')) {
                return Http::response($articleHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            return Http::response('', 404, ['Content-Type' => 'text/plain']);
        });

        $this->artisan('news:scan', ['sourceKey' => $source->key])->assertSuccessful();
        $article = Article::query()->where('source_id', $source->id)->firstOrFail();
        $this->assertSame(ArticleStatus::DISCOVERED, $article->status);
        $this->assertDatabaseCount('articles', 1);

        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status, json_encode($run->fresh()->steps()->get(['step_key', 'status', 'error_summary'])->toArray(), JSON_UNESCAPED_UNICODE));
        $post = $article->generatedPosts()->firstOrFail();
        $asset = $post->assets()->firstOrFail();
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status);
        $this->assertSame(ArticleStatus::READY_FOR_REVIEW, $article->fresh()->status);
        $this->assertSame('image/png', $asset->mime_type);
        Storage::disk('local')->assertExists($asset->path);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();
        $this->post(route('review.publish', $post))->assertRedirect();

        $publication = $post->publications()->firstOrFail();
        $this->assertSame(PublicationStatus::PUBLISHED, $publication->status);
        $this->assertSame(GeneratedPostStatus::PUBLISHED, $post->fresh()->status);
        $this->assertSame(ArticleStatus::PUBLISHED, $article->fresh()->status);
        $this->assertNotEmpty($publication->external_url);
        foreach (['workflow.started', 'workflow.succeeded', 'review.approved', 'publication.started', 'publication.published'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event]);
        }

        // Scenario B: source scan and publish retries remain idempotent.
        $this->artisan('news:scan', ['sourceKey' => $source->key])->assertSuccessful();
        $this->post(route('review.publish', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertDatabaseCount('articles', 1);
        $this->assertDatabaseCount('publications', 1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    private function source(): Source
    {
        return Source::create([
            'name' => 'ThaiRath Society', 'key' => 'thairath_e2e',
            'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath', 'is_active' => true,
        ]);
    }
}
