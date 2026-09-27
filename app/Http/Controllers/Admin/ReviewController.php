<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewDecisionType;
use App\Images\Services\ManualNewsImageImporter;
use App\Models\Article;
use App\Models\GeneratedAsset;
use App\Models\GeneratedPost;
use App\Reviews\ReviewService;
use App\Support\SafeErrorPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReviewController
{
    public function show(Request $request, Article $article): View
    {
        $postQuery = $article->generatedPosts()->with(['assets' => fn ($query) => $query->orderByDesc('version'), 'reviewDecisions.user:id,name']);
        $post = $request->integer('post')
            ? $postQuery->whereKey($request->integer('post'))->firstOrFail()
            : $postQuery->latest('version')->first();
        $article->load(['source', 'snapshots' => fn ($query) => $query->latest('fetched_at')->limit(1), 'workflowRuns' => fn ($query) => $query->with('steps')->latest('id')->limit(5)]);
        $metadata = $post?->metadata ?? [];

        return view('admin.review.show', [
            'article' => $article,
            'post' => $post,
            'facts' => $metadata['facts'] ?? [],
            'summary' => $metadata['summary']['summary'] ?? null,
            'factCheck' => $metadata['fact_check'] ?? null,
            'factCheckCurrent' => $post && ($metadata['checked_draft_hash'] ?? null) === hash('sha256', $post->draft_text),
            'workflow' => $article->workflowRuns->first(),
            'manualImageDirectory' => str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                Storage::disk('local')->path('manual-news-images'),
            ),
        ]);
    }

    public function preview(GeneratedAsset $asset)
    {
        abort_unless(in_array($asset->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true), 404);
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }

    public function edit(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['draft_text' => ['required', 'string', 'max:12000']]);

        return $this->run($post, fn () => $reviews->edit($post, $request->user(), $data['draft_text']), 'บันทึกร่างเป็น version ใหม่แล้ว และต้องตรวจข้อเท็จจริง/อนุมัติอีกครั้ง');
    }

    public function regenerateSummary(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        return $this->run($post, fn () => $reviews->regenerateSummary($post, $request->user()), 'สร้างสรุปเป็น version ใหม่แล้ว');
    }

    public function regenerateRewrite(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        return $this->run($post, fn () => $reviews->regenerateRewrite($post, $request->user()), 'สร้างร่างข้อความเป็น version ใหม่แล้ว ตรวจผลก่อนอนุมัติ');
    }

    public function rerunFactCheck(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        return $this->run($post, fn () => $reviews->rerunFactCheck($post, $request->user()), 'ตรวจข้อเท็จจริงของร่าง version นี้แล้ว');
    }

    public function regenerateImage(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        return $this->run($post, fn () => $reviews->regenerateImage($post, $request->user()), 'สร้างภาพ version ใหม่แล้ว โดยเก็บภาพเดิมไว้');
    }

    public function importManualImage(Request $request, Article $article, ManualNewsImageImporter $importer): RedirectResponse
    {
        $data = $request->validate(['post' => ['required', 'integer', 'min:1']]);
        $post = $article->generatedPosts()->whereKey($data['post'])->firstOrFail();

        return $this->run($post, fn () => ['post' => $post, 'asset' => $importer->import($article, $post, $request->user())], 'นำเข้าภาพสำหรับข่าว #'.$article->id.' แล้ว');
    }

    public function approve(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
            'no_image' => ['nullable', 'boolean'],
            'no_image_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run($post, fn () => $reviews->approve($post, $request->user(), $data['note'] ?? null, $data['override_reason'] ?? null, (bool) ($data['no_image'] ?? false), $data['no_image_reason'] ?? null), 'อนุมัติฉบับนี้แล้ว — ระบบยังไม่ได้เผยแพร่โพสต์');
    }

    public function reject(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);

        return $this->run($post, fn () => $reviews->decide($post, $request->user(), ReviewDecisionType::REJECT, $data['note']), 'บันทึกผลไม่อนุมัติแล้ว');
    }

    public function requestChanges(Request $request, GeneratedPost $post, ReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);

        return $this->run($post, fn () => $reviews->decide($post, $request->user(), ReviewDecisionType::REQUEST_CHANGES, $data['note']), 'ส่งคำขอแก้ไขพร้อมบันทึกเหตุผลแล้ว');
    }

    private function run(GeneratedPost $post, callable $action, string $message): RedirectResponse
    {
        try {
            $result = $action();
            $selectedPost = $result instanceof GeneratedPost ? $result : ($result['post'] ?? $post);

            return redirect()->route('articles.review', ['article' => $post->article_id, 'post' => $selectedPost->id])->with('status', $message);
        } catch (Throwable $exception) {
            $safe = app(SafeErrorPresenter::class)->sanitize($exception::class.': '.$exception->getMessage());

            return back()->withErrors(['review' => $safe ?: 'ดำเนินการไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองใหม่']);
        }
    }
}
