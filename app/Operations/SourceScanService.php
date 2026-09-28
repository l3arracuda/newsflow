<?php

namespace App\Operations;

use App\Enums\WorkflowRunStatus;
use App\Models\Source;
use App\Models\WorkflowRun;
use App\News\Services\ArticleDiscoveryService;
use App\Operations\Contracts\OperationalAlert;
use App\Support\SafeErrorPresenter;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SourceScanService
{
    public function __construct(private readonly ArticleDiscoveryService $discovery, private readonly OperationalAlert $alerts) {}

    public function scan(Source $source): array
    {
        if (! $source->is_active) {
            return ['status' => 'skipped', 'reason' => 'inactive'];
        }

        $lock = Cache::lock('newsflow:source-scan:'.$source->id, 900);
        if (! $lock->get()) {
            return ['status' => 'skipped', 'reason' => 'already_running'];
        }

        $run = WorkflowRun::create([
            'run_type' => 'source_scan', 'status' => WorkflowRunStatus::RUNNING,
            'started_at' => now(), 'attempt' => 1,
            'metadata' => ['source_id' => $source->id, 'source_key' => $source->key, 'trigger' => 'manual_or_schedule'],
        ]);
        $run->auditLogs()->create([
            'event' => 'source_scan.started', 'after_state' => ['status' => WorkflowRunStatus::RUNNING->value],
            'metadata' => ['source_id' => $source->id, 'source_key' => $source->key],
        ]);

        try {
            $result = $this->discovery->discover($source);
            $run->update([
                'status' => WorkflowRunStatus::SUCCEEDED, 'finished_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], ['result' => ['candidates' => $result['candidates'], 'created' => $result['created'], 'existing' => $result['existing']]]),
            ]);
            $run->auditLogs()->create([
                'event' => 'source_scan.succeeded', 'after_state' => ['status' => WorkflowRunStatus::SUCCEEDED->value],
                'metadata' => ['source_id' => $source->id, 'candidates' => $result['candidates'], 'created' => $result['created'], 'existing' => $result['existing']],
            ]);

            return ['status' => 'succeeded', 'run_id' => $run->id, ...$result];
        } catch (Throwable $exception) {
            $category = property_exists($exception, 'category') ? (string) $exception->category : 'unexpected';
            $safeMessage = app(SafeErrorPresenter::class)->sanitize($exception::class.': '.$exception->getMessage());
            $run->update(['status' => WorkflowRunStatus::FAILED, 'finished_at' => now(), 'error_summary' => 'Source scan failed ['.$category.']: '.mb_substr((string) $safeMessage, 0, 400)]);
            $run->auditLogs()->create([
                'event' => 'source_scan.failed', 'after_state' => ['status' => WorkflowRunStatus::FAILED->value],
                'metadata' => ['source_id' => $source->id, 'error_category' => $category, 'error_class' => $exception::class],
            ]);
            $this->alerts->send('error', 'News source scan failed.', ['source_key' => $source->key, 'run_id' => $run->id, 'error_category' => $category]);

            return ['status' => 'failed', 'run_id' => $run->id, 'reason' => $category];
        } finally {
            $lock->release();
        }
    }
}
