<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Article;
use App\Models\GeneratedPost;
use App\Models\Publication;
use App\Models\Source;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function __invoke(): View
    {
        $counts = Article::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $sources = Source::query()->withCount('articles')->orderBy('name')->get();
        $scanRunsBySource = WorkflowRun::query()->where('run_type', 'source_scan')->latest('id')->limit(500)->get()
            ->groupBy(fn (WorkflowRun $run) => data_get($run->metadata, 'source_id'));
        $sourceHealth = $sources->map(function (Source $source) use ($scanRunsBySource) {
            $runs = $scanRunsBySource->get($source->id, collect());

            return [
                'source' => $source,
                'last_success' => $runs->first(fn (WorkflowRun $run) => $run->status === WorkflowRunStatus::SUCCEEDED),
                'last_failure' => $runs->first(fn (WorkflowRun $run) => $run->status === WorkflowRunStatus::FAILED),
                'latest' => $runs->first(),
            ];
        });
        $staleRunCount = WorkflowRun::query()->where('status', WorkflowRunStatus::RUNNING)
            ->where('started_at', '<', now()->subMinutes(max(1, (int) config('newsflow.stale_run_minutes', 60))))->count();
        $queuePending = $queueFailed = null;
        if (config('queue.default') === 'database') {
            $queuePending = DB::table(config('queue.connections.database.table', 'jobs'))->whereNull('reserved_at')->count();
            $queueFailed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        }
        $alerts = [];
        $sourceAlertMinutes = max(1, (int) config('newsflow.source_alert_minutes', 720));
        foreach ($sourceHealth as $health) {
            if (! $health['source']->is_active) {
                continue;
            }
            if (! $health['last_success'] || $health['last_success']->finished_at?->lt(now()->subMinutes($sourceAlertMinutes))
                || ($health['last_failure'] && (! $health['last_success'] || $health['last_failure']->id > $health['last_success']->id))) {
                $alerts[] = ['level' => 'warning', 'message' => 'แหล่งข่าว '.$health['source']->name.' ไม่มีรอบสแกนสำเร็จในช่วงที่กำหนด'];
            }
        }
        if (($queueFailed ?? 0) > 0) {
            $alerts[] = ['level' => 'error', 'message' => 'มีงานใน queue ล้มเหลว '.number_format($queueFailed).' รายการ'];
        }
        if ($staleRunCount > 0) {
            $alerts[] = ['level' => 'error', 'message' => 'มี workflow ค้างเกินกำหนด '.number_format($staleRunCount).' รายการ'];
        }

        return view('dashboard', [
            'counts' => [
                'new' => (int) ($counts[ArticleStatus::DISCOVERED->value] ?? 0),
                'processing' => (int) ($counts[ArticleStatus::PROCESSING->value] ?? 0),
                'review' => (int) ($counts[ArticleStatus::READY_FOR_REVIEW->value] ?? 0),
                'flagged' => (int) ($counts[ArticleStatus::FLAGGED->value] ?? 0),
                'published' => (int) ($counts[ArticleStatus::PUBLISHED->value] ?? 0),
                'failed' => (int) ($counts[ArticleStatus::FAILED->value] ?? 0),
            ],
            'recentRuns' => WorkflowRun::with('article:id,source_id,title', 'article.source:id,name')
                ->where('run_type', '!=', 'source_scan')->latest('id')->limit(8)->get(),
            'failedRuns' => WorkflowRun::with('article:id,source_id,title', 'article.source:id,name')
                ->where('run_type', '!=', 'source_scan')->where('status', WorkflowRunStatus::FAILED->value)->latest('id')->limit(5)->get(),
            'sources' => $sources,
            'publicationsToday' => Publication::query()
                ->where('status', 'published')
                ->where('published_at', '>=', today()->startOfDay())
                ->count(),
            'articlesDiscoveredToday' => Article::query()->where('discovered_at', '>=', today()->startOfDay())->count(),
            'draftsGeneratedToday' => GeneratedPost::query()->where('created_at', '>=', today()->startOfDay())->count(),
            'awaitingReviewCount' => Article::query()->where('status', ArticleStatus::READY_FOR_REVIEW)->count(),
            'failedSteps24h' => WorkflowStepRun::query()->where('status', 'failed')->where('finished_at', '>=', now()->subDay())->count(),
            'queuePending' => $queuePending,
            'queueFailed' => $queueFailed,
            'staleRunCount' => $staleRunCount,
            'sourceHealth' => $sourceHealth,
            'operationalAlerts' => $alerts,
        ]);
    }
}
