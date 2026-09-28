<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\PublicationStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Jobs\ProcessWorkflowJob;
use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use App\Workflows\WorkflowStepCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_articles_and_workflows_require_admin_access(): void
    {
        $source = $this->source();
        $article = $this->article($source);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::FAILED, 'attempt' => 1]);

        foreach ([
            ['get', '/dashboard'], ['get', '/articles'], ['get', '/articles/'.$article->id], ['get', '/workflows/'.$run->id], ['post', '/workflows/'.$run->id.'/retry'],
        ] as [$method, $uri]) {
            $this->{$method}($uri)->assertRedirect('/login');
        }

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/articles')->assertForbidden();
        $this->get('/workflows/'.$run->id)->assertForbidden();
    }

    public function test_dashboard_shows_article_workflow_source_scan_and_publication_metrics(): void
    {
        $source = $this->source(['last_scanned_at' => now()->subMinute()]);
        $new = $this->article($source, ['status' => ArticleStatus::DISCOVERED]);
        $processing = $this->article($source, ['source_url' => 'https://example.test/news/processing', 'status' => ArticleStatus::PROCESSING]);
        $review = $this->article($source, ['source_url' => 'https://example.test/news/review', 'status' => ArticleStatus::READY_FOR_REVIEW]);
        $published = $this->article($source, ['source_url' => 'https://example.test/news/published', 'status' => ArticleStatus::PUBLISHED]);
        $failed = $this->article($source, ['source_url' => 'https://example.test/news/failed', 'status' => ArticleStatus::FAILED]);
        $run = $failed->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::FAILED, 'attempt' => 1]);
        $post = $published->generatedPosts()->create(['version' => 1, 'status' => GeneratedPostStatus::PUBLISHED, 'draft_text' => 'Published test content', 'source_attribution' => 'Test', 'source_url' => $published->source_url]);
        $post->publications()->create(['channel' => 'facebook', 'provider' => 'test', 'status' => PublicationStatus::PUBLISHED, 'published_at' => now(), 'idempotency_key' => 'dashboard-publication-'.$post->id]);

        $response = $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]))->get('/dashboard')->assertOk();
        foreach (['แดชบอร์ด', $source->name, 'สแกนสำเร็จล่าสุด', 'รอบสำเร็จล่าสุด', 'รอบล้มเหลวล่าสุด', 'Workflow ล่าสุด', 'Workflow ที่ผิดพลาด', 'เผยแพร่วันนี้', 'ค้นพบวันนี้', 'Draft วันนี้', 'ขั้นตอนล้มเหลว 24 ชม.', 'Queue รอดำเนินการ'] as $text) {
            $response->assertSee($text);
        }
        $response->assertSee('href="'.route('workflows.show', $run).'"', false);
        $response->assertSee('1');
    }

    public function test_article_filters_by_source_status_date_and_keyword_and_keeps_query_on_pagination(): void
    {
        $first = $this->source(['name' => 'แหล่งข่าวหนึ่ง', 'key' => 'source-one']);
        $second = $this->source(['name' => 'แหล่งข่าวสอง', 'key' => 'source-two', 'base_url' => 'https://other.test', 'listing_url' => 'https://other.test/news']);
        $match = $this->article($first, ['title' => 'เหตุการณ์ค้นหาได้', 'status' => ArticleStatus::FAILED, 'discovered_at' => now()->subDays(2)]);
        $this->article($first, ['title' => 'หัวข้อไม่ตรง', 'source_url' => 'https://example.test/news/no-match', 'status' => ArticleStatus::FAILED, 'discovered_at' => now()->subDays(5)]);
        $this->article($second, ['title' => 'เหตุการณ์ค้นหาได้จากอีกแหล่ง', 'source_url' => 'https://other.test/news/match', 'status' => ArticleStatus::FAILED, 'discovered_at' => now()->subDays(2)]);

        $response = $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/articles?'.http_build_query(['source_id' => $first->id, 'status' => 'failed', 'from' => now()->subDays(3)->toDateString(), 'to' => now()->toDateString(), 'q' => 'ค้นหาได้']))
            ->assertOk();
        $response->assertSee($match->title)->assertDontSee('หัวข้อไม่ตรง')->assertDontSee('อีกแหล่ง');

        foreach (range(1, 17) as $index) {
            $this->article($first, ['title' => 'Pagination item '.$index, 'source_url' => 'https://example.test/news/pagination-'.$index]);
        }
        $page = $this->get('/articles?q=Pagination')->assertOk();
        $page->assertSee('Pagination item 1')->assertSee('q=Pagination', false);
    }

    public function test_article_detail_shows_source_snapshot_workflow_draft_asset_and_audit_history(): void
    {
        $source = $this->source(['last_scanned_at' => now()]);
        $article = $this->article($source, ['status' => ArticleStatus::READY_FOR_REVIEW, 'content_hash' => hash('sha256', 'detail-content')]);
        $article->snapshots()->create(['normalized_excerpt' => 'เนื้อหาต้นทางสำหรับอ่านบนหน้าแอดมิน', 'fetched_at' => now(), 'checksum' => $article->content_hash]);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::SUCCEEDED, 'attempt' => 1]);
        $run->steps()->create(['step_key' => 'awaiting_review', 'name' => 'รอตรวจทาน', 'status' => WorkflowStepStatus::SUCCEEDED, 'attempt' => 1, 'started_at' => now(), 'finished_at' => now()]);
        $post = $article->generatedPosts()->create(['version' => 1, 'status' => GeneratedPostStatus::DRAFT, 'draft_text' => '[placeholder] draft sample', 'source_attribution' => $source->name, 'source_url' => $article->source_url, 'metadata' => ['placeholder' => true]]);
        $post->assets()->create(['version' => 1, 'provider' => 'placeholder', 'path' => 'placeholder/no-image.png', 'metadata' => ['placeholder' => true]]);
        $article->auditLogs()->create(['event' => 'article.ready_for_review', 'after_state' => ['article_status' => 'ready_for_review', 'token' => 'should-not-render']]);

        $response = $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]))->get('/articles/'.$article->id)->assertOk();
        foreach ([$source->name, 'เปิดข่าวต้นทาง', 'เนื้อหาต้นทางสำหรับอ่าน', 'Workflow timeline', 'placeholder', 'ประวัติการตรวจสอบ'] as $text) {
            $response->assertSee($text);
        }
        $response->assertDontSee('should-not-render');
        $response->assertSee(route('workflows.show', $run));
    }

    public function test_failed_step_is_visible_redacted_and_retry_is_admin_only(): void
    {
        Queue::fake();
        $source = $this->source();
        $article = $this->article($source, ['status' => ArticleStatus::FAILED]);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::FAILED, 'attempt' => 1, 'finished_at' => now()]);
        $stepKey = WorkflowStepCatalog::STEPS[3]['key'];
        $run->steps()->create(['step_key' => $stepKey, 'name' => 'สรุปข่าว', 'status' => WorkflowStepStatus::FAILED, 'attempt' => 1, 'error_summary' => 'RuntimeException: api_key=old-secret token=hidden-value']);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/workflows/'.$run->id.'/retry', ['step_key' => $stepKey])->assertForbidden();
        Queue::assertNothingPushed();

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/workflows/'.$run->id)->assertOk();
        $response->assertSee('สรุปข่าว')->assertSee('[REDACTED]')->assertDontSee('old-secret')->assertDontSee('hidden-value');

        $this->post('/workflows/'.$run->id.'/retry', ['step_key' => $stepKey])->assertRedirect(route('workflows.show', $run));
        Queue::assertPushed(ProcessWorkflowJob::class, 1);
        $this->assertSame(WorkflowRunStatus::PENDING, $run->fresh()->status);
    }

    public function test_only_the_latest_failed_attempt_is_offered_for_retry(): void
    {
        Queue::fake();
        $article = $this->article($this->source(), ['status' => ArticleStatus::FAILED]);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::FAILED, 'attempt' => 2]);
        $run->steps()->create(['step_key' => 'summarize', 'name' => 'สรุปข่าว', 'status' => WorkflowStepStatus::FAILED, 'attempt' => 1]);
        $run->steps()->create(['step_key' => 'summarize', 'name' => 'สรุปข่าว', 'status' => WorkflowStepStatus::SUCCEEDED, 'attempt' => 2]);
        $run->steps()->create(['step_key' => 'fact_check', 'name' => 'ตรวจสอบข้อเท็จจริง', 'status' => WorkflowStepStatus::FAILED, 'attempt' => 1]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withoutVite()->actingAs($admin)->get('/workflows/'.$run->id)
            ->assertOk()->assertDontSee('value="summarize"', false)->assertSee('value="fact_check"', false);
        $this->post('/workflows/'.$run->id.'/retry', ['step_key' => 'summarize'])
            ->assertRedirect()->assertSessionHasErrors('retry');
        $this->assertSame(WorkflowRunStatus::FAILED, $run->fresh()->status);
        $this->post('/workflows/'.$run->id.'/retry')->assertRedirect(route('workflows.show', $run));
        $this->assertSame('fact_check', $run->fresh()->auditLogs()->latest('id')->first()->after_state['failed_step']);
    }

    public function test_article_list_uses_bounded_query_count_for_page_of_records(): void
    {
        $source = $this->source();
        foreach (range(1, 15) as $index) {
            $article = $this->article($source, ['title' => 'News item '.$index, 'source_url' => 'https://example.test/news/query-'.$index]);
            $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::SUCCEEDED, 'attempt' => 1]);
            $post = $article->generatedPosts()->create(['version' => 1, 'status' => GeneratedPostStatus::DRAFT, 'draft_text' => 'Draft', 'source_attribution' => 'Test', 'source_url' => $article->source_url]);
            $post->publications()->create(['channel' => 'facebook', 'provider' => 'fake', 'status' => PublicationStatus::PENDING, 'idempotency_key' => 'query-publication-'.$post->id]);
        }

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        DB::enableQueryLog();
        $this->get('/articles')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queryCount, "Article list executed {$queryCount} queries for one page.");
    }

    private function source(array $attributes = []): Source
    {
        return Source::create(array_merge([
            'name' => 'ThaiRath Society', 'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th', 'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath', 'is_active' => true,
        ], $attributes));
    }

    private function article(Source $source, array $attributes = []): Article
    {
        static $sequence = 0;
        $sequence++;

        return $source->articles()->create(array_merge([
            'source_url' => 'https://example.test/news/item-'.$sequence,
            'title' => 'Test news '.$sequence,
            'discovered_at' => now(),
            'status' => ArticleStatus::DISCOVERED,
        ], $attributes));
    }
}
