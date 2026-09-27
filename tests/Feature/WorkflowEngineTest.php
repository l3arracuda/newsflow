<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Jobs\ProcessWorkflowJob;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Source;
use App\Models\WorkflowRun;
use App\Workflows\Processors\PlaceholderProcessor;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowInspector;
use App\Workflows\WorkflowProcessorRegistry;
use App\Workflows\WorkflowRetrier;
use App\Workflows\WorkflowStarter;
use App\Workflows\WorkflowStepCatalog;
use App\Workflows\WorkflowStepProcessor;
use Database\Seeders\AiPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new AiPromptTemplateSeeder)->run();
        Storage::fake('local');
    }

    public function test_happy_path_records_all_steps_and_reaches_awaiting_review(): void
    {
        Queue::fake();
        $article = $this->fetchedArticle();
        $starter = app(WorkflowStarter::class);

        $first = $starter->start($article);
        $duplicate = $starter->start($article);
        Queue::assertPushed(ProcessWorkflowJob::class, 1);
        $this->assertTrue($first['dispatched']);
        $this->assertFalse($duplicate['dispatched']);
        $this->assertSame($first['run']->id, $duplicate['run']->id);
        $this->assertInstanceOf(WithoutOverlapping::class, (new ProcessWorkflowJob($first['run']->id))->middleware()[0]);

        $engine = app(WorkflowEngine::class);
        $engine->run($first['run']->id);
        $engine->run($first['run']->id);

        $run = $first['run']->fresh(['steps']);
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->status);
        $this->assertCount(9, $run->steps);
        $this->assertTrue($run->steps->every(fn ($step) => $step->status === WorkflowStepStatus::SUCCEEDED && $step->started_at && $step->finished_at));
        $this->assertSame(ArticleStatus::READY_FOR_REVIEW, $article->fresh()->status);
        $this->assertDatabaseCount('generated_posts', 1);
        $this->assertFalse((bool) $article->generatedPosts()->first()->metadata['placeholder']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.started']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'article.ready_for_review']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.succeeded']);
        $this->assertSame(1, AuditLog::where('event', 'workflow.succeeded')->count());
    }

    public function test_failure_is_sanitized_and_retry_resumes_at_failed_step_without_duplicates(): void
    {
        Queue::fake();
        $article = $this->fetchedArticle();
        $counters = [];
        $failOnce = new class implements WorkflowStepProcessor
        {
            public int $calls = 0;

            public function process(Article $article, WorkflowRun $run): array
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new RuntimeException('remote failed api_key=top-secret-value token=hidden-value');
                }

                return ['placeholder' => true, 'retried' => true];
            }
        };
        $processors = [];
        foreach (WorkflowStepCatalog::STEPS as $step) {
            $key = $step['key'];
            if ($key === 'summarize') {
                $processors[$key] = $failOnce;

                continue;
            }
            $processors[$key] = new class($key, $counters) implements WorkflowStepProcessor
            {
                public function __construct(private string $key, private array &$counters) {}

                public function process(Article $article, WorkflowRun $run): array
                {
                    $this->counters[$this->key] = ($this->counters[$this->key] ?? 0) + 1;
                    if ($this->key === 'rewrite') {
                        return (new PlaceholderProcessor('rewrite'))->process($article, $run);
                    }
                    if ($this->key === 'fact_check') {
                        return ['pass' => true, 'severity' => 'none', 'mismatches' => [], 'unsupported_claims' => []];
                    }

                    return ['placeholder' => true];
                }
            };
        }
        $this->app->instance(WorkflowProcessorRegistry::class, new WorkflowProcessorRegistry($processors));

        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $failed = $run->fresh();
        $this->assertSame(WorkflowRunStatus::FAILED, $failed->status);
        $this->assertSame(ArticleStatus::FAILED, $article->fresh()->status);
        $failedStep = $failed->steps()->where('step_key', 'summarize')->latest('id')->firstOrFail();
        $this->assertSame(WorkflowStepStatus::FAILED, $failedStep->status);
        $this->assertStringNotContainsString('top-secret-value', $failedStep->error_summary);
        $this->assertStringNotContainsString('hidden-value', $failedStep->error_summary);
        $this->assertSame(1, $counters['discover']);
        $this->assertSame(1, $counters['fetch_detail']);
        $this->assertSame(1, $counters['extract_facts']);

        app(WorkflowRetrier::class)->retry($run->id, 'summarize');
        app(WorkflowEngine::class)->run($run->id);

        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->fresh()->status);
        $this->assertSame(2, $failOnce->calls);
        $this->assertSame(1, $counters['discover']);
        $this->assertSame(1, $counters['fetch_detail']);
        $this->assertSame(1, $counters['extract_facts']);
        $this->assertDatabaseCount('generated_posts', 1);
        $this->assertSame(2, $run->steps()->where('step_key', 'summarize')->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.retry_requested']);
    }

    public function test_explicit_retry_rejects_steps_that_did_not_fail(): void
    {
        Queue::fake();
        $article = $this->fetchedArticle();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->expectException(\InvalidArgumentException::class);
        app(WorkflowRetrier::class)->retry($run->id, 'summarize');
    }

    public function test_placeholder_success_can_be_reprocessed_as_a_new_run_without_overwriting_old_post(): void
    {
        Queue::fake();
        $article = $this->fetchedArticle();
        $previousRun = $article->workflowRuns()->create([
            'run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::SUCCEEDED,
            'attempt' => 1, 'metadata' => ['trigger' => 'legacy'], 'finished_at' => now(),
        ]);
        $previousRun->steps()->create([
            'step_key' => 'extract_facts', 'name' => 'แยกข้อเท็จจริง', 'status' => WorkflowStepStatus::SUCCEEDED,
            'attempt' => 1, 'metadata' => ['placeholder' => true, 'processor' => 'not_configured'],
        ]);
        $oldPost = $article->generatedPosts()->create([
            'workflow_run_id' => $previousRun->id, 'version' => 1, 'status' => 'draft',
            'draft_text' => '[placeholder]', 'source_attribution' => 'ThaiRath',
            'source_url' => $article->source_url, 'metadata' => ['placeholder' => true],
        ]);

        $newRun = app(WorkflowStarter::class)->reprocessPlaceholder($article);

        Queue::assertPushed(ProcessWorkflowJob::class, 1);
        $this->assertNotSame($previousRun->id, $newRun->id);
        $this->assertSame(WorkflowRunStatus::PENDING, $newRun->status);
        $this->assertSame($previousRun->id, $newRun->metadata['previous_run_id']);
        $this->assertSame('[placeholder]', $oldPost->fresh()->draft_text);
        $this->assertSame(ArticleStatus::PROCESSING, $article->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.placeholder_reprocess_started']);
    }

    public function test_placeholder_reprocess_is_refused_for_real_successful_workflow(): void
    {
        $article = $this->fetchedArticle();
        $run = $article->workflowRuns()->create([
            'run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::SUCCEEDED,
            'attempt' => 1, 'metadata' => [], 'finished_at' => now(),
        ]);
        $run->steps()->create([
            'step_key' => 'extract_facts', 'name' => 'แยกข้อเท็จจริง', 'status' => WorkflowStepStatus::SUCCEEDED,
            'attempt' => 1, 'metadata' => ['facts' => ['event_action' => 'ข้อเท็จจริง']],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(WorkflowStarter::class)->reprocessPlaceholder($article);
    }

    public function test_status_command_displays_run_and_step_history(): void
    {
        Queue::fake();
        $article = $this->fetchedArticle();
        $run = app(WorkflowStarter::class)->start($article)['run'];
        app(WorkflowEngine::class)->run($run->id);

        $this->artisan('news:workflow:status '.$run->id)
            ->expectsOutputToContain('succeeded')
            ->assertSuccessful();
        $this->assertSame('awaiting_review', app(WorkflowInspector::class)->find($run->id)->steps->last()->step_key);
    }

    private function fetchedArticle(): Article
    {
        $source = Source::create([
            'name' => 'ThaiRath Society',
            'key' => 'thairath_society',
            'base_url' => 'https://www.thairath.co.th',
            'listing_url' => 'https://www.thairath.co.th/news/society',
            'adapter' => 'thairath',
            'is_active' => true,
        ]);
        $article = $source->articles()->create([
            'source_url' => 'https://www.thairath.co.th/news/society/phase4-test-article',
            'canonical_url' => 'https://www.thairath.co.th/news/society/phase4-test-article',
            'title' => 'ข่าวทดสอบ workflow',
            'discovered_at' => now(),
            'status' => ArticleStatus::FETCHED,
        ]);
        $article->snapshots()->create([
            'normalized_excerpt' => 'เนื้อหาต้นทางที่ยืนยันแล้วสำหรับทดสอบ workflow และ pipeline ของระบบข่าว',
            'fetched_at' => now(),
            'checksum' => hash('sha256', 'workflow-test-snapshot'),
        ]);

        return $article;
    }
}
