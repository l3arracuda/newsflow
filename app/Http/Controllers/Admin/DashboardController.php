<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Article;
use App\Models\Publication;
use App\Models\Source;
use App\Models\WorkflowRun;
use Illuminate\Contracts\View\View;

class DashboardController
{
    public function __invoke(): View
    {
        $counts = Article::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');

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
                ->latest('id')->limit(8)->get(),
            'failedRuns' => WorkflowRun::with('article:id,source_id,title', 'article.source:id,name')
                ->where('status', WorkflowRunStatus::FAILED->value)->latest('id')->limit(5)->get(),
            'sources' => Source::query()->withCount('articles')->orderBy('name')->get(),
            'publicationsToday' => Publication::query()
                ->where('status', 'published')
                ->where('published_at', '>=', today()->startOfDay())
                ->count(),
        ]);
    }
}
