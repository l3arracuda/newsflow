<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\PublicationStatus;
use App\Enums\ReviewDecisionType;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_two_tables_are_created_by_migrations(): void
    {
        foreach ([
            'sources',
            'articles',
            'article_snapshots',
            'workflow_runs',
            'workflow_step_runs',
            'prompt_templates',
            'generated_posts',
            'generated_assets',
            'review_decisions',
            'publications',
            'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }
    }

    public function test_source_article_snapshot_and_workflow_relations_work(): void
    {
        $source = $this->createSource();
        $article = $this->createArticle($source);
        $snapshot = $article->snapshots()->create([
            'normalized_excerpt' => 'A short source excerpt retained for audit.',
            'fetched_at' => now(),
            'checksum' => hash('sha256', 'A short source excerpt retained for audit.'),
        ]);
        $run = $article->workflowRuns()->create([
            'run_type' => 'article_processing',
            'status' => WorkflowRunStatus::RUNNING,
            'attempt' => 1,
            'started_at' => now(),
        ]);
        $step = $run->steps()->create([
            'step_key' => 'fetch_detail',
            'name' => 'Fetch article detail',
            'status' => WorkflowStepStatus::SUCCEEDED,
            'attempt' => 1,
        ]);

        $this->assertTrue($source->articles->contains($article));
        $this->assertTrue($article->source->is($source));
        $this->assertTrue($article->snapshots->contains($snapshot));
        $this->assertTrue($article->workflowRuns->contains($run));
        $this->assertTrue($run->steps->contains($step));
        $this->assertTrue($step->workflowRun->is($run));
    }

    public function test_generated_post_asset_review_publication_and_audit_relations_work(): void
    {
        $article = $this->createArticle($this->createSource());
        $post = $article->generatedPosts()->create([
            'version' => 1,
            'status' => GeneratedPostStatus::DRAFT,
            'draft_text' => 'Original summary draft.',
            'source_attribution' => 'Source: ThaiRath Society',
            'source_url' => $article->source_url,
        ]);
        $asset = $post->assets()->create([
            'version' => 1,
            'provider' => 'test-provider',
            'disk' => 'public',
            'path' => 'generated/test-image.png',
            'content_hash' => hash('sha256', 'test-image'),
            'metadata' => ['illustrative' => true],
        ]);
        $user = User::factory()->create(['is_admin' => true]);
        $decision = $post->reviewDecisions()->create([
            'user_id' => $user->id,
            'decision' => ReviewDecisionType::APPROVE,
            'note' => 'Reviewed.',
            'decided_at' => now(),
        ]);
        $publication = $post->publications()->create([
            'channel' => 'facebook',
            'provider' => 'fake',
            'status' => PublicationStatus::PENDING,
            'idempotency_key' => 'publication-'.$post->id,
        ]);
        $audit = $article->auditLogs()->create([
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'event' => 'article.created',
            'after_state' => ['status' => ArticleStatus::DISCOVERED->value],
        ]);

        $this->assertTrue($article->generatedPosts->contains($post));
        $this->assertTrue($post->assets->contains($asset));
        $this->assertTrue($asset->generatedPost->is($post));
        $this->assertTrue($post->reviewDecisions->contains($decision));
        $this->assertTrue($decision->user->is($user));
        $this->assertTrue($user->reviewDecisions->contains($decision));
        $this->assertTrue($post->publications->contains($publication));
        $this->assertTrue($publication->generatedPost->is($post));
        $this->assertTrue($audit->entity->is($article));
        $this->assertTrue($audit->actor->is($user));
        $this->assertTrue($article->auditLogs->contains($audit));
    }

    public function test_status_fields_are_cast_to_php_enums(): void
    {
        $article = $this->createArticle($this->createSource());
        $run = $article->workflowRuns()->create([
            'run_type' => 'article_processing',
            'status' => WorkflowRunStatus::RUNNING,
        ]);
        $post = $article->generatedPosts()->create([
            'version' => 1,
            'status' => GeneratedPostStatus::READY_FOR_REVIEW,
            'draft_text' => 'Draft text.',
            'source_attribution' => 'Source: ThaiRath Society',
            'source_url' => $article->source_url,
        ]);
        $decision = $post->reviewDecisions()->create([
            'decision' => ReviewDecisionType::REQUEST_CHANGES,
            'decided_at' => now(),
        ]);
        $publication = $post->publications()->create([
            'channel' => 'facebook',
            'provider' => 'fake',
            'status' => PublicationStatus::PENDING,
            'idempotency_key' => 'enum-check-'.$post->id,
        ]);

        $this->assertSame(ArticleStatus::DISCOVERED, $article->fresh()->status);
        $this->assertSame(WorkflowRunStatus::RUNNING, $run->fresh()->status);
        $this->assertSame(GeneratedPostStatus::READY_FOR_REVIEW, $post->fresh()->status);
        $this->assertSame(ReviewDecisionType::REQUEST_CHANGES, $decision->fresh()->decision);
        $this->assertSame(PublicationStatus::PENDING, $publication->fresh()->status);
    }

    public function test_duplicate_source_url_is_rejected_by_database_constraint(): void
    {
        $source = $this->createSource();
        $this->createArticle($source);

        $this->expectException(QueryException::class);
        $this->createArticle($source);
    }

    public function test_default_source_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseHas('sources', [
            'key' => 'thairath_society',
            'name' => 'ThaiRath Society',
            'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath',
            'is_active' => true,
        ]);
    }

    private function createSource(): Source
    {
        return Source::create([
            'name' => 'Example Source',
            'key' => 'example-source',
            'base_url' => 'https://example.test',
            'listing_url' => 'https://example.test/news',
            'adapter' => 'example',
            'is_active' => true,
            'config' => ['section' => 'news'],
        ]);
    }

    private function createArticle(Source $source, string $url = 'https://example.test/news/one'): Article
    {
        return $source->articles()->create([
            'source_url' => $url,
            'title' => 'Example article',
            'discovered_at' => now(),
            'metadata' => ['language' => 'th'],
        ]);
    }
}
