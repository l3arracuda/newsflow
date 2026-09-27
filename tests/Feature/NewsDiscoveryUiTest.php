<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use App\News\Exceptions\SourceFetchException;
use App\News\Services\ArticleDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NewsDiscoveryUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_discover_latest_news_from_articles_page(): void
    {
        $source = $this->source();
        $this->instance(ArticleDiscoveryService::class, Mockery::mock(ArticleDiscoveryService::class, function ($mock) use ($source) {
            $mock->shouldReceive('discover')->once()->with(Mockery::on(fn (Source $given) => $given->is($source)))->andReturn([
                'candidates' => 4,
                'created' => 2,
                'existing' => 2,
                'items' => [],
            ]);
        }));

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('articles.index'))
            ->assertOk()
            ->assertSee('ดึงข่าวล่าสุด');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('articles.discover'))
            ->assertRedirect(route('articles.index'))
            ->assertSessionHas('discovery_result', [
                'candidates' => 4,
                'created' => 2,
                'existing' => 2,
            ]);
    }

    public function test_discovery_failure_returns_safe_feedback_to_articles_page(): void
    {
        $source = $this->source();
        $this->instance(ArticleDiscoveryService::class, Mockery::mock(ArticleDiscoveryService::class, function ($mock) use ($source) {
            $mock->shouldReceive('discover')->once()->with(Mockery::on(fn (Source $given) => $given->is($source)))
                ->andThrow(new SourceFetchException('rate_limited', 'Upstream details should not be shown.'));
        }));

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('articles.discover'))
            ->assertRedirect(route('articles.index'))
            ->assertSessionHas('discovery_error', 'แหล่งข่าวจำกัดการเข้าถึง กรุณารอสักครู่แล้วลองใหม่');
    }

    public function test_non_admin_cannot_trigger_news_discovery(): void
    {
        $this->source();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('articles.discover'))
            ->assertForbidden();
    }

    private function source(): Source
    {
        return Source::create([
            'name' => 'ThaiRath Society',
            'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th',
            'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath',
            'is_active' => true,
        ]);
    }
}
