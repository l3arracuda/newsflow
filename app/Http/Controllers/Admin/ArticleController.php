<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use App\Support\SafeErrorPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'status' => ['nullable', Rule::in(array_map(fn ($status) => $status->value, ArticleStatus::cases()))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Article::query()->with([
            'source:id,name',
            'latestWorkflowRun:id,workflow_runs.article_id,status',
            'latestGeneratedPost.latestPublication',
        ]);
        $query->when($filters['source_id'] ?? null, fn (Builder $query, $sourceId) => $query->where('source_id', $sourceId));
        $query->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
        $query->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('discovered_at', '>=', $date));
        $query->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('discovered_at', '<=', $date));
        $query->when($filters['q'] ?? null, fn (Builder $query, $keyword) => $query->where('title', 'like', '%'.$keyword.'%'));

        return view('articles.index', [
            'articles' => $query->latest('discovered_at')->paginate(15)->withQueryString(),
            'sources' => Source::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ArticleStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Article $article, SafeErrorPresenter $errors): View
    {
        $article->load([
            'source:id,name,key,base_url,listing_url,adapter,is_active,last_scanned_at',
            'snapshots' => fn ($query) => $query->latest('fetched_at')->limit(10),
            'workflowRuns' => fn ($query) => $query->with('steps')->latest('id')->limit(10),
            'generatedPosts' => fn ($query) => $query->with(['assets', 'reviewDecisions.user:id,name', 'latestPublication'])->latest('id')->limit(10),
        ]);
        foreach ($article->workflowRuns as $run) {
            $run->setAttribute('safe_error_summary', $errors->sanitize($run->error_summary));
            foreach ($run->steps as $step) {
                $step->setAttribute('safe_error_summary', $errors->sanitize($step->error_summary));
            }
        }
        foreach ($article->generatedPosts as $post) {
            foreach ($post->assets as $asset) {
                $asset->setAttribute('is_placeholder', (bool) ($asset->metadata['placeholder'] ?? false));
            }
        }

        return view('articles.show', [
            'article' => $article,
            'auditLogs' => $article->auditLogs()->with('actor:id,name')->latest('created_at')->paginate(20, ['*'], 'audit_page'),
        ]);
    }
}
