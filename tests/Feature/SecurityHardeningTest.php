<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use App\News\Adapters\ThaiRath\ThaiRathHtmlParser;
use App\News\Exceptions\SourceFetchException;
use App\News\Http\SourceHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_hosts_and_non_https_source_urls_are_blocked_before_network_access(): void
    {
        Http::fake();
        foreach (['http://www.thairath.co.th/news', 'https://127.0.0.1/admin', 'https://user@www.thairath.co.th/news', 'https://www.thairath.co.th:8443/news'] as $url) {
            try {
                app(SourceHttpClient::class)->get($url, 'security-test');
                $this->fail('Unsafe source URL was accepted: '.$url);
            } catch (SourceFetchException $exception) {
                $this->assertSame('invalid_url', $exception->category);
            }
        }
        Http::assertNothingSent();
    }

    public function test_unsafe_redirect_is_rejected_without_following_location(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'redirect-test');
            $this->fail('Expected redirect rejection.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('unsafe_redirect', $exception->category);
        }
        Http::assertSentCount(1);
    }

    public function test_oversized_and_non_html_source_responses_are_rejected(): void
    {
        config(['newsflow.max_source_bytes' => 1024]);
        Http::fake(['*' => Http::response(str_repeat('x', 1025), 200, ['Content-Type' => 'text/html'])]);
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'large-response');
            $this->fail('Expected response size rejection.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('response_too_large', $exception->category);
        }

    }

    public function test_non_html_source_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response('{"data":true}', 200, ['Content-Type' => 'application/json'])]);
        try {
            app(SourceHttpClient::class)->get('https://www.thairath.co.th/news/society', 'wrong-content');
            $this->fail('Expected content type rejection.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('invalid_content_type', $exception->category);
        }
    }

    public function test_source_request_rate_limit_is_enforced(): void
    {
        config(['newsflow.source_requests_per_minute' => 1]);
        RateLimiter::clear('source-fetch:rate-limit-test');
        Http::fake(['*' => Http::response('ok', 200, ['Content-Type' => 'text/plain'])]);
        $client = app(SourceHttpClient::class);
        $this->assertSame('ok', $client->get('https://www.thairath.co.th/news/society', 'rate-limit-test'));

        try {
            $client->get('https://www.thairath.co.th/news/society', 'rate-limit-test');
            $this->fail('Expected source rate limit rejection.');
        } catch (SourceFetchException $exception) {
            $this->assertSame('rate_limited', $exception->category);
        }
        Http::assertSentCount(1);
    }

    public function test_fetched_source_snapshot_text_is_bounded(): void
    {
        config(['newsflow.max_source_text_chars' => 1000]);
        $paragraphs = str_repeat('<p>'.str_repeat('เนื้อหาข่าวที่ยืนยันได้ ', 20).'</p>', 8);
        $document = app(ThaiRathHtmlParser::class)->article('<html><body><article><h1>หัวข้อทดสอบ</h1>'.$paragraphs.'</article></body></html>', 'https://www.thairath.co.th/news/society/test');

        $this->assertLessThanOrEqual(1000, mb_strlen($document->text));
    }

    public function test_source_title_is_html_escaped_in_admin_list(): void
    {
        $source = Source::create(['name' => 'ThaiRath Society', 'key' => 'thairath_society', 'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society', 'adapter' => 'thairath', 'is_active' => true]);
        Article::create(['source_id' => $source->id, 'source_url' => 'https://www.thairath.co.th/news/society/xss', 'title' => '<img src=x onerror=alert(1)>', 'discovered_at' => now()]);

        $response = $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]))->get('/articles')->assertOk();
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_production_environment_check_fails_when_debug_or_secure_cookie_is_unsafe(): void
    {
        config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://newsflow.test', 'session.secure' => false, 'session.encrypt' => false]);

        $this->artisan('newsflow:check-environment')->assertFailed()
            ->expectsOutputToContain('APP_DEBUG must be false in production.')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE must be enabled in production HTTPS.')
            ->expectsOutputToContain('SESSION_ENCRYPT must be enabled in production.')
            ->expectsOutputToContain('APP_URL must use HTTPS in production.');
    }

    public function test_source_snapshot_retention_defaults_to_dry_run_and_audits_explicit_pruning(): void
    {
        config(['newsflow.snapshot_retention_days' => 30]);
        $source = Source::create(['name' => 'ThaiRath Society', 'key' => 'thairath_society', 'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society', 'adapter' => 'thairath', 'is_active' => true]);
        $article = Article::create(['source_id' => $source->id, 'source_url' => 'https://www.thairath.co.th/news/society/retention', 'title' => 'Retention test', 'discovered_at' => now()]);
        $old = $article->snapshots()->create(['normalized_excerpt' => 'old source text', 'fetched_at' => now()->subDays(40), 'checksum' => hash('sha256', 'old')]);
        $recent = $article->snapshots()->create(['normalized_excerpt' => 'recent source text', 'fetched_at' => now()->subDays(5), 'checksum' => hash('sha256', 'recent')]);

        $this->artisan('newsflow:retention:prune-snapshots')->assertSuccessful();
        $this->assertDatabaseHas('article_snapshots', ['id' => $old->id]);
        $this->artisan('newsflow:retention:prune-snapshots', ['--execute' => true])->assertSuccessful();
        $this->assertDatabaseMissing('article_snapshots', ['id' => $old->id]);
        $this->assertDatabaseHas('article_snapshots', ['id' => $recent->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => Article::class, 'entity_id' => $article->id, 'event' => 'source_snapshots.retention_pruned']);
    }
}
