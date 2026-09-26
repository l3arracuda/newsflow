<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Source;
use App\News\Adapters\NewsSourceAdapter;
use App\News\Adapters\SourceAdapterRegistry;
use App\News\Adapters\ThaiRath\RobotsPolicy;
use App\News\Adapters\ThaiRath\ThaiRathHtmlParser;
use App\News\DTO\ArticleCandidate;
use App\News\DTO\ArticleDocument;
use App\News\Exceptions\SourceFetchException;
use App\News\Http\SourceHttpClient;
use App\News\Services\ArticleDiscoveryService;
use App\News\Services\ArticleFetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourceFetchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_fixture_normalizes_urls_deduplicates_and_filters_other_hosts(): void
    {
        $items = (new ThaiRathHtmlParser)->listing(file_get_contents(base_path('tests/Fixtures/source-fetching/listing.html')), 'https://www.thairath.co.th');
        $this->assertCount(2, $items);
        $this->assertSame('https://www.thairath.co.th/news/society/12345', $items[0]->url);
        $this->assertSame('12345', $items[0]->externalId);
        $this->assertNotNull($items[0]->publishedAt);
        $this->assertSame('https://www.thairath.co.th/news/society/67890', $items[1]->url);
    }

    public function test_article_fixture_extracts_paragraphs_without_navigation_and_includes_metadata(): void
    {
        $document = (new ThaiRathHtmlParser)->article(file_get_contents(base_path('tests/Fixtures/source-fetching/article.html')), 'https://www.thairath.co.th/news/society/12345');
        $this->assertSame('หัวข้อข่าวทดสอบ', $document->title);
        $this->assertStringContainsString('เนื้อหาข่าวย่อหน้าที่หนึ่ง', $document->text);
        $this->assertStringNotContainsString('เมนูและโฆษณา', $document->text);
        $this->assertNotNull($document->publishedAt);
    }

    public function test_malformed_article_html_fails_in_a_controlled_way(): void
    {
        $this->expectException(SourceFetchException::class);
        (new ThaiRathHtmlParser)->article('<html><body><h1>ไม่มีเนื้อหา</h1></body></html>', 'https://www.thairath.co.th/news/society/1');
    }

    public function test_dry_run_does_not_write_and_repeated_discovery_is_idempotent(): void
    {
        $source = $this->source();
        $adapter = new class implements NewsSourceAdapter
        {
            public function discover(Source $source): iterable
            {
                return [new ArticleCandidate('ข่าวทดสอบระบบ', 'https://www.thairath.co.th/news/society/98765', null, '98765')];
            }

            public function fetchArticle(Source $source, ArticleCandidate $candidate): ArticleDocument
            {
                throw new \LogicException;
            }
        };
        $this->app->instance(SourceAdapterRegistry::class, new SourceAdapterRegistry(['thairath' => $adapter]));
        $service = app(ArticleDiscoveryService::class);
        $this->assertSame(0, $service->discover($source, true)['created']);
        $this->assertDatabaseCount('articles', 0);
        $this->assertNull($source->fresh()->last_scanned_at);
        $this->assertSame(1, $service->discover($source)['created']);
        $this->assertSame(1, $service->discover($source)['existing']);
        $this->assertDatabaseCount('articles', 1);
        $this->assertNotNull($source->fresh()->last_scanned_at);
    }

    public function test_detail_fetch_creates_snapshot_once_and_marks_article_fetched(): void
    {
        $source = $this->source();
        $article = $source->articles()->create(['source_external_id' => '98765', 'source_url' => 'https://www.thairath.co.th/news/society/98765', 'title' => 'หัวข้อเดิม', 'discovered_at' => now()]);
        $document = new ArticleDocument('หัวข้อที่ยืนยันแล้ว', $article->source_url, str_repeat('เนื้อหาข่าวที่ผ่านการทำความสะอาด ', 8));
        $adapter = new class($document) implements NewsSourceAdapter
        {
            public function __construct(private ArticleDocument $document) {}

            public function discover(Source $source): iterable
            {
                return [];
            }

            public function fetchArticle(Source $source, ArticleCandidate $candidate): ArticleDocument
            {
                return $this->document;
            }
        };
        $this->app->instance(SourceAdapterRegistry::class, new SourceAdapterRegistry(['thairath' => $adapter]));
        app(ArticleFetchService::class)->fetch($article);
        app(ArticleFetchService::class)->fetch($article);
        $this->assertDatabaseCount('article_snapshots', 1);
        $this->assertSame(ArticleStatus::FETCHED, $article->fresh()->status);
        $this->assertSame('หัวข้อที่ยืนยันแล้ว', $article->fresh()->title);
    }

    public function test_http_429_is_classified_without_retrying(): void
    {
        Http::fake(['*' => Http::response('', 429)]);
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'test-429');
            $this->fail('Expected classified error.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('rate_limited', $exception->category);
        }
        Http::assertSentCount(1);

    }

    public function test_server_errors_are_retried_a_bounded_number_of_times(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'test-503');
            $this->fail('Expected classified error.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('upstream_error', $exception->category);
        }
        Http::assertSentCount(3);
    }

    public function test_connection_failures_retry_a_bounded_number_of_times(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('timeout');
        });
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'test-timeout');
            $this->fail('Expected timeout error.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('timeout', $exception->category);
        }
        $this->assertSame(3, $attempts);
    }

    public function test_discovery_respects_robots_txt_before_requesting_listing(): void
    {
        Http::fake(['*' => Http::response("User-agent: *\nDisallow: /news/society\n", 200)]);
        $source = $this->source();

        try {
            app(RobotsPolicy::class)->assertAllowed($source->listing_url, $source->key);
            $this->fail('Expected robots policy to deny the configured path.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('robots_disallowed', $exception->category);
        }

        Http::assertSentCount(1);
    }

    public function test_robots_user_agent_group_is_respected_after_wildcard_group(): void
    {
        Http::fake(['*' => Http::response("User-agent: *\nDisallow:\n\nUser-agent: NewsFlow\nDisallow: /news/society\n", 200)]);
        $source = $this->source();

        try {
            app(RobotsPolicy::class)->assertAllowed($source->listing_url, $source->key);
            $this->fail('Expected NewsFlow-specific robots rule to deny the path.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('robots_disallowed', $exception->category);
        }
    }

    public function test_command_handles_a_missing_source_without_mutating(): void
    {
        $this->artisan('news:discover missing_source --dry-run')->assertFailed();
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_fetch_command_handles_missing_article(): void
    {
        $this->artisan('news:fetch 999999')->assertFailed();
    }

    private function source(): Source
    {
        return Source::create(['name' => 'ThaiRath Society', 'key' => 'thairath_society', 'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society', 'adapter' => 'thairath', 'is_active' => true]);
    }
}
