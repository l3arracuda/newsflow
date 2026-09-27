<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use App\News\Exceptions\SourceFetchException;
use App\News\Services\ArticleDiscoveryService;
use App\Support\SafeErrorPresenter;
use App\Workflows\WorkflowStarter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class ArticleController
{
    public function discoverLatest(ArticleDiscoveryService $discovery): RedirectResponse
    {
        $source = Source::query()->where('key', 'thairath_society')->first();
        if (! $source || ! $source->is_active) {
            return redirect()->route('articles.index')->with('discovery_error', 'ไม่พบแหล่งข่าว ThaiRath Society ที่เปิดใช้งานอยู่');
        }

        try {
            $result = $discovery->discover($source);
        } catch (Throwable $exception) {
            $category = $exception instanceof SourceFetchException ? $exception->category : 'unexpected';
            Log::warning('News discovery from articles page failed.', [
                'source_key' => $source->key,
                'error_category' => $category,
                'error_class' => $exception::class,
            ]);

            $message = match ($category) {
                'robots_disallowed' => 'แหล่งข่าวไม่อนุญาตให้ดึงข้อมูลในขณะนี้',
                'rate_limited' => 'แหล่งข่าวจำกัดการเข้าถึง กรุณารอสักครู่แล้วลองใหม่',
                'timeout' => 'เชื่อมต่อแหล่งข่าวไม่สำเร็จ กรุณาลองใหม่ภายหลัง',
                default => 'ดึงข่าวไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่',
            };

            return redirect()->route('articles.index')->with('discovery_error', $message);
        }

        return redirect()->route('articles.index')->with('discovery_result', [
            'candidates' => $result['candidates'],
            'created' => $result['created'],
            'existing' => $result['existing'],
        ]);
    }

    public function startWorkflow(Article $article, WorkflowStarter $starter): RedirectResponse
    {
        try {
            $result = $starter->start($article);
        } catch (Throwable $exception) {
            Log::warning('Manual article workflow start failed.', [
                'article_id' => $article->id,
                'error_class' => $exception::class,
            ]);

            return back()->with('workflow_start_error', 'เริ่ม Workflow ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }

        return back()->with('workflow_start_result', [
            'run_id' => $result['run']->id,
            'status' => $result['run']->status->value,
            'dispatched' => $result['dispatched'],
        ]);
    }

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
