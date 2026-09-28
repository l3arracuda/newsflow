<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Source;
use App\Models\WorkflowRun;
use App\News\Exceptions\SourceFetchException;
use App\News\Services\ArticleDiscoveryService;
use App\Operations\Contracts\OperationalAlert;
use App\Operations\SourceScanService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_command_runs_only_active_sources_and_persists_scan_run(): void
    {
        $source = $this->source();
        $inactive = $this->source(['key' => 'inactive-source', 'is_active' => false]);
        $this->mock(ArticleDiscoveryService::class)->shouldReceive('discover')->once()->withArgs(fn ($actual) => $actual->is($source))
            ->andReturn(['candidates' => 2, 'created' => 1, 'existing' => 1, 'items' => []]);

        $exitCode = Artisan::call('news:scan');
        $run = WorkflowRun::query()->where('run_type', 'source_scan')->firstOrFail();
        $this->assertSame(0, $exitCode, Artisan::output().' '.$run->error_summary);
        $this->assertSame(WorkflowRunStatus::SUCCEEDED, $run->status);
        $this->assertSame($source->id, $run->metadata['source_id']);
        $this->assertSame(1, $run->metadata['result']['created']);
        $this->assertDatabaseCount('workflow_runs', 1);
        $this->assertDatabaseHas('sources', ['id' => $inactive->id, 'is_active' => 0]);
    }

    public function test_inactive_source_is_skipped_and_lock_prevents_overlapping_manual_scan(): void
    {
        $inactive = $this->source(['is_active' => false]);
        $this->artisan('news:scan', ['sourceKey' => $inactive->key])->assertSuccessful();
        $this->assertDatabaseCount('workflow_runs', 0);

        $active = $this->source(['key' => 'locked-source']);
        $lock = Cache::lock('newsflow:source-scan:'.$active->id, 900);
        $this->assertTrue($lock->get());
        try {
            $result = app(SourceScanService::class)->scan($active);
            $this->assertSame('skipped', $result['status']);
            $this->assertSame('already_running', $result['reason']);
            $this->assertDatabaseCount('workflow_runs', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_source_scan_failure_is_recorded_and_alerted_without_raw_exception_text(): void
    {
        $source = $this->source();
        $this->mock(ArticleDiscoveryService::class)->shouldReceive('discover')->once()
            ->andThrow(new SourceFetchException('upstream_error', 'token=do-not-store'));
        $alert = $this->mock(OperationalAlert::class);
        $alert->shouldReceive('send')->once()->with('error', 'News source scan failed.', \Mockery::on(fn ($context) => $context['source_key'] === $source->key && $context['error_category'] === 'upstream_error'));

        $result = app(SourceScanService::class)->scan($source);
        $this->assertSame('failed', $result['status']);
        $run = WorkflowRun::query()->where('run_type', 'source_scan')->firstOrFail();
        $this->assertSame(WorkflowRunStatus::FAILED, $run->status);
        $this->assertStringNotContainsString('do-not-store', $run->error_summary);
        $this->assertDatabaseHas('audit_logs', ['event' => 'source_scan.failed']);
    }

    public function test_stale_running_workflows_are_failed_and_alerted(): void
    {
        config(['newsflow.stale_run_minutes' => 60]);
        $article = $this->source()->articles()->create(['source_url' => 'https://example.test/stale', 'title' => 'Stale item', 'discovered_at' => now(), 'status' => ArticleStatus::PROCESSING]);
        $run = WorkflowRun::create(['article_id' => $article->id, 'run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::RUNNING, 'started_at' => now()->subHours(2), 'attempt' => 1]);
        $recent = WorkflowRun::create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::RUNNING, 'started_at' => now()->subMinutes(5), 'attempt' => 1]);
        $alert = $this->mock(OperationalAlert::class);
        $alert->shouldReceive('send')->once()->with('error', 'A workflow run exceeded the stale-running threshold.', ['workflow_run_id' => $run->id, 'threshold_minutes' => 60]);

        $this->artisan('newsflow:operations:monitor')->assertSuccessful();
        $this->assertSame(WorkflowRunStatus::FAILED, $run->fresh()->status);
        $this->assertSame(ArticleStatus::FAILED, $article->fresh()->status);
        $this->assertSame(WorkflowRunStatus::RUNNING, $recent->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.stale_failed', 'entity_id' => $run->id]);
    }

    public function test_scheduler_has_three_local_thailand_scans_and_frequent_monitoring(): void
    {
        config(['newsflow.scan_times' => ['08:00', '14:00', '20:00'], 'newsflow.timezone' => 'Asia/Bangkok']);
        $events = app(Schedule::class)->events();
        $scanExpressions = collect($events)->filter(fn ($event) => str_contains($event->command ?? '', 'news:scan'))
            ->map(fn ($event) => $event->expression)->values()->all();

        $this->assertEqualsCanonicalizing(['0 8 * * *', '0 14 * * *', '0 20 * * *'], $scanExpressions);
        $this->assertTrue(collect($events)->contains(fn ($event) => str_contains($event->command ?? '', 'newsflow:operations:monitor') && $event->expression === '*/5 * * * *'));
    }

    private function source(array $attributes = []): Source
    {
        static $sequence = 0;
        $sequence++;

        return Source::create(array_merge([
            'name' => 'Source '.$sequence, 'key' => 'source-'.$sequence,
            'base_url' => 'https://example.test', 'listing_url' => 'https://example.test/news',
            'adapter' => 'thairath', 'is_active' => true,
        ], $attributes));
    }
}
