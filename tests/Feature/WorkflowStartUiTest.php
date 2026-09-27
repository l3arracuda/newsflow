<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Jobs\ProcessWorkflowJob;
use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowStartUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_workflow_for_one_article_from_articles_list(): void
    {
        Queue::fake();
        $article = $this->article();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('articles.index'))
            ->assertOk()
            ->assertSee('เริ่ม Workflow');

        $this->from(route('articles.index'))
            ->post(route('articles.workflow.start', $article))
            ->assertRedirect(route('articles.index'))
            ->assertSessionHas('workflow_start_result.dispatched', true);

        $this->assertDatabaseCount('workflow_runs', 1);
        $this->assertSame(ArticleStatus::PROCESSING, $article->fresh()->status);
        $this->assertSame(WorkflowRunStatus::PENDING, $article->latestWorkflowRun->status);
        Queue::assertPushed(ProcessWorkflowJob::class, 1);
    }

    public function test_starting_again_while_workflow_is_pending_does_not_duplicate_the_run(): void
    {
        Queue::fake();
        $article = $this->article();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('articles.index'))->post(route('articles.workflow.start', $article));
        $this->from(route('articles.index'))->post(route('articles.workflow.start', $article))
            ->assertSessionHas('workflow_start_result.dispatched', false);

        $this->assertDatabaseCount('workflow_runs', 1);
        Queue::assertPushed(ProcessWorkflowJob::class, 1);
    }

    public function test_non_admin_cannot_start_a_workflow(): void
    {
        $article = $this->article();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('articles.workflow.start', $article))
            ->assertForbidden();
    }

    private function article(): Article
    {
        $source = Source::create([
            'name' => 'ThaiRath Society',
            'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th',
            'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath',
            'is_active' => true,
        ]);

        return $source->articles()->create([
            'source_external_id' => 'test-article-1',
            'source_url' => 'https://www.thairath.co.th/news/society/test-article-1',
            'canonical_url' => 'https://www.thairath.co.th/news/society/test-article-1',
            'title' => 'ข่าวทดสอบสำหรับเริ่ม Workflow',
            'discovered_at' => now(),
            'status' => ArticleStatus::DISCOVERED,
        ]);
    }
}
