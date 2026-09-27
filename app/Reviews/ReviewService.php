<?php

namespace App\Reviews;

use App\AI\Contracts\ArticleSummarizer;
use App\AI\Contracts\FactConsistencyChecker;
use App\AI\Contracts\FactExtractor;
use App\AI\Contracts\SocialPostRewriter;
use App\AI\FactEvidenceValidator;
use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\ReviewDecisionType;
use App\Images\Services\EditorialImagePromptBuilder;
use App\Images\Services\GeneratedImageAssetService;
use App\Models\Article;
use App\Models\GeneratedPost;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Models\WorkflowRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewService
{
    public function __construct(
        private readonly ArticleSummarizer $summarizer,
        private readonly SocialPostRewriter $rewriter,
        private readonly FactConsistencyChecker $factChecker,
        private readonly FactExtractor $factExtractor,
        private readonly FactEvidenceValidator $factEvidence,
        private readonly EditorialImagePromptBuilder $imagePrompts,
        private readonly GeneratedImageAssetService $images,
    ) {}

    public function edit(GeneratedPost $post, User $reviewer, string $draftText): GeneratedPost
    {
        $draftText = trim($draftText);
        if (mb_strlen($draftText) < 20 || ! str_contains($draftText, $post->source_url)) {
            throw new RuntimeException('ร่างต้องมีเนื้อหาที่เพียงพอและคงลิงก์ต้นทางไว้');
        }
        $metadata = $post->metadata ?? [];
        $metadata['manual_edit'] = ['by' => $reviewer->id, 'at' => now()->toIso8601String(), 'parent_version' => $post->version];
        unset($metadata['fact_check'], $metadata['checked_draft_hash']);

        return $this->createRevision($post, $reviewer, $draftText, $metadata, 'review.draft_edited');
    }

    public function regenerateSummary(GeneratedPost $post, User $reviewer): GeneratedPost
    {
        $metadata = $post->metadata ?? [];
        $facts = $metadata['facts'] ?? null;
        if (! is_array($facts)) {
            throw new RuntimeException('ยังไม่มี facts สำหรับสร้างสรุปใหม่');
        }
        $metadata['summary'] = $this->summarizer->summarize($facts);
        $metadata['summary_regenerated_by'] = $reviewer->id;

        return $this->createRevision($post, $reviewer, $post->draft_text, $metadata, 'review.summary_regenerated');
    }

    public function regenerateRewrite(GeneratedPost $post, User $reviewer): GeneratedPost
    {
        $metadata = $post->metadata ?? [];
        $facts = $metadata['facts'] ?? null;
        $summary = $metadata['summary']['summary'] ?? null;
        if (! is_array($facts) || ! is_string($summary) || $summary === '') {
            throw new RuntimeException('ต้องมี facts และ summary ก่อนเรียบเรียงใหม่');
        }
        $rewrite = $this->rewriter->rewrite($post->article->title, $facts, $summary, $post->source_attribution, $post->source_url);
        if (! str_contains($rewrite['body'], $post->source_url)) {
            $rewrite['body'] = rtrim($rewrite['body'])."\n\nที่มา: {$post->source_attribution} {$post->source_url}";
        }
        $metadata['rewrite'] = $rewrite;
        $metadata['rewrite_regenerated_by'] = $reviewer->id;
        unset($metadata['fact_check'], $metadata['checked_draft_hash']);

        return $this->createRevision($post, $reviewer, trim($rewrite['title'])."\n\n".trim($rewrite['body']), $metadata, 'review.rewrite_regenerated');
    }

    public function rerunFactCheck(GeneratedPost $post, User $reviewer): array
    {
        if ($post->status === GeneratedPostStatus::APPROVED) {
            $metadata = $post->metadata ?? [];
            unset($metadata['fact_check'], $metadata['checked_draft_hash']);
            $post = $this->createRevision($post, $reviewer, $post->draft_text, $metadata, 'review.fact_check_revision_created');
        }
        $metadata = $post->metadata ?? [];
        $facts = $metadata['facts'] ?? null;
        if (! is_array($facts)) {
            throw new RuntimeException('ยังไม่มี facts สำหรับตรวจสอบข้อเท็จจริง');
        }
        if (($facts['evidence_schema_version'] ?? 0) < FactEvidenceValidator::SCHEMA_VERSION) {
            $snapshot = $post->article->snapshots()->latest('fetched_at')->first();
            if (! $snapshot) {
                throw new RuntimeException('ไม่พบเนื้อหาต้นทางสำหรับสร้าง facts พร้อมหลักฐาน');
            }
            $facts = $this->factEvidence->validate(
                $this->factExtractor->extract($post->article->title, $snapshot->normalized_excerpt),
                $snapshot->normalized_excerpt,
            );
            $metadata['facts'] = $facts;
            $post->update(['metadata' => $metadata]);
        }
        $draft = ['title' => mb_substr($post->draft_text, 0, 180), 'body' => $post->draft_text];
        $result = $this->factChecker->check($facts, $draft);
        $result['flagged'] = ! $result['pass'];
        $metadata = $post->metadata ?? [];
        $metadata['fact_check'] = $result;
        $metadata['checked_draft_hash'] = hash('sha256', $post->draft_text);
        $metadata['checked_by'] = $reviewer->id;
        $post->update(['metadata' => $metadata]);
        $articleBeforeStatus = $post->article->status?->value;
        $articleStatus = $result['pass'] ? ArticleStatus::READY_FOR_REVIEW : ArticleStatus::FLAGGED;
        $post->article->update(['status' => $articleStatus]);
        $post->auditLogs()->create([
            'actor_type' => 'user', 'actor_id' => $reviewer->id,
            'event' => 'review.fact_check_rerun',
            'after_state' => ['post_id' => $post->id, 'version' => $post->version, 'pass' => $result['pass'], 'severity' => $result['severity']],
        ]);
        $post->article->auditLogs()->create([
            'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => 'review.fact_check_rerun',
            'before_state' => ['article_status' => $articleBeforeStatus],
            'after_state' => ['article_status' => $articleStatus->value, 'post_id' => $post->id, 'pass' => $result['pass']],
        ]);

        return ['post' => $post->refresh(), 'result' => $result];
    }

    public function regenerateImage(GeneratedPost $post, User $reviewer): array
    {
        if (config('services.image_generation.driver') === 'manual') {
            throw new RuntimeException('ระบบตั้งค่าให้ใช้ภาพที่แนบเองแล้ว กรุณาวางภาพตาม ID ข่าวและนำเข้าจากหน้า review');
        }

        if ($post->status === GeneratedPostStatus::APPROVED) {
            $post = $this->createRevision($post, $reviewer, $post->draft_text, $post->metadata ?? [], 'review.image_revision_created');
        }
        $run = WorkflowRun::query()->where('article_id', $post->article_id)->where('status', 'succeeded')->latest('id')->first();
        if (! $run) {
            throw new RuntimeException('ยังไม่มี workflow สำเร็จเพื่อใช้ประกอบ prompt ภาพ');
        }
        $prompt = $this->imagePrompts->build($post->article, $run);
        $asset = $this->images->generate($post, $prompt);

        return ['post' => $post, 'asset' => $asset];
    }

    public function approve(GeneratedPost $post, User $reviewer, ?string $note, ?string $overrideReason, bool $noImage, ?string $noImageReason): ReviewDecision
    {
        return DB::transaction(function () use ($post, $reviewer, $note, $overrideReason, $noImage, $noImageReason) {
            $post = GeneratedPost::query()->lockForUpdate()->with('article')->findOrFail($post->id);
            if ($post->status === GeneratedPostStatus::APPROVED || $post->status === GeneratedPostStatus::PUBLISHED || $post->status === GeneratedPostStatus::PUBLISHING) {
                throw new RuntimeException('ฉบับนี้ถูกอนุมัติหรือนำไปใช้แล้ว ให้สร้างฉบับแก้ไขใหม่ก่อน');
            }
            $text = trim($post->draft_text);
            if (mb_strlen($text) < 20 || ! str_contains($text, $post->source_url)) {
                throw new RuntimeException('อนุมัติไม่ได้: ร่างไม่ครบหรือไม่มีลิงก์ต้นทาง');
            }
            $metadata = $post->metadata ?? [];
            $checkCurrent = ($metadata['checked_draft_hash'] ?? null) === hash('sha256', $post->draft_text);
            $factPass = $checkCurrent && (bool) ($metadata['fact_check']['pass'] ?? false);
            if (! $factPass && trim((string) $overrideReason) === '') {
                throw new RuntimeException('ผลตรวจไม่ผ่านหรือไม่ใช่ร่างฉบับล่าสุด ต้องใส่เหตุผล override ก่อนอนุมัติ');
            }
            if ($post->assets()->exists() === false && (! $noImage || trim((string) $noImageReason) === '')) {
                throw new RuntimeException('ยังไม่มีภาพ ต้องเลือกระบุไม่ใช้ภาพพร้อมเหตุผลก่อนอนุมัติ');
            }
            if ($noImage && trim((string) $noImageReason) === '') {
                throw new RuntimeException('การเลือกไม่ใช้ภาพต้องระบุเหตุผล');
            }

            $assets = $noImage ? [] : $post->assets()->orderByDesc('version')->limit(1)->get(['id', 'version', 'content_hash'])->map(fn ($asset) => ['id' => $asset->id, 'version' => $asset->version, 'content_hash' => $asset->content_hash])->values()->all();
            $snapshot = [
                'post_id' => $post->id,
                'post_version' => $post->version,
                'content_hash' => hash('sha256', $post->draft_text),
                'facts_hash' => hash('sha256', json_encode($metadata['facts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'summary_hash' => hash('sha256', json_encode($metadata['summary'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'fact_check_hash' => $checkCurrent ? hash('sha256', json_encode($metadata['fact_check'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) : null,
                'fact_check_overridden' => ! $factPass,
                'override_reason' => $factPass ? null : trim((string) $overrideReason),
                'no_image' => $noImage,
                'no_image_reason' => $noImage ? trim((string) $noImageReason) : null,
                'asset_versions' => $assets,
                'approved_by' => $reviewer->id,
                'approved_at' => now()->toIso8601String(),
            ];
            $metadata['approval_snapshot'] = $snapshot;
            $post->update(['status' => GeneratedPostStatus::APPROVED, 'metadata' => $metadata]);
            $articleBeforeStatus = $post->article->status?->value;
            $post->article->update(['status' => ArticleStatus::APPROVED]);
            $decision = $post->reviewDecisions()->create([
                'user_id' => $reviewer->id,
                'decision' => ReviewDecisionType::APPROVE,
                'note' => $note,
                'decided_at' => now(),
                'metadata' => $snapshot,
            ]);
            $post->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id,
                'event' => 'review.approved',
                'after_state' => ['post_id' => $post->id, 'version' => $post->version, 'article_status' => ArticleStatus::APPROVED->value],
                'metadata' => $snapshot,
            ]);
            $post->article->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => 'review.approved',
                'before_state' => ['article_status' => $articleBeforeStatus],
                'after_state' => ['article_status' => ArticleStatus::APPROVED->value, 'post_id' => $post->id, 'version' => $post->version],
                'metadata' => $snapshot,
            ]);

            return $decision;
        });
    }

    public function decide(GeneratedPost $post, User $reviewer, ReviewDecisionType $decisionType, string $note): ReviewDecision
    {
        return DB::transaction(function () use ($post, $reviewer, $decisionType, $note) {
            $post = GeneratedPost::query()->lockForUpdate()->with('article')->findOrFail($post->id);
            if ($post->status === GeneratedPostStatus::APPROVED || $post->status === GeneratedPostStatus::PUBLISHED || $post->status === GeneratedPostStatus::PUBLISHING) {
                throw new RuntimeException('ฉบับที่อนุมัติแล้วแก้ผลตรวจไม่ได้ ให้สร้างฉบับแก้ไขใหม่');
            }
            $status = $decisionType === ReviewDecisionType::REJECT ? GeneratedPostStatus::REJECTED : GeneratedPostStatus::CHANGES_REQUESTED;
            $articleStatus = $decisionType === ReviewDecisionType::REJECT ? ArticleStatus::REJECTED : ArticleStatus::CHANGES_REQUESTED;
            $post->update(['status' => $status]);
            $articleBeforeStatus = $post->article->status?->value;
            $post->article->update(['status' => $articleStatus]);
            $decision = $post->reviewDecisions()->create([
                'user_id' => $reviewer->id, 'decision' => $decisionType, 'note' => $note, 'decided_at' => now(),
                'metadata' => ['post_id' => $post->id, 'version' => $post->version, 'content_hash' => hash('sha256', $post->draft_text)],
            ]);
            $post->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id,
                'event' => 'review.'.$decisionType->value,
                'after_state' => ['post_id' => $post->id, 'version' => $post->version, 'article_status' => $articleStatus->value],
                'metadata' => ['note' => $note],
            ]);
            $post->article->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => 'review.'.$decisionType->value,
                'before_state' => ['article_status' => $articleBeforeStatus],
                'after_state' => ['article_status' => $articleStatus->value, 'post_id' => $post->id, 'version' => $post->version],
                'metadata' => ['note' => $note],
            ]);

            return $decision;
        });
    }

    private function createRevision(GeneratedPost $parent, User $reviewer, string $draftText, array $metadata, string $event): GeneratedPost
    {
        return DB::transaction(function () use ($parent, $reviewer, $draftText, $metadata, $event) {
            $article = Article::query()->lockForUpdate()->findOrFail($parent->article_id);
            $version = ((int) $article->generatedPosts()->max('version')) + 1;
            unset($metadata['approval_snapshot']);
            $metadata['revision_of'] = ['post_id' => $parent->id, 'version' => $parent->version];
            $revision = $article->generatedPosts()->create([
                'version' => $version, 'status' => GeneratedPostStatus::DRAFT, 'draft_text' => $draftText,
                'source_attribution' => $parent->source_attribution, 'source_url' => $parent->source_url, 'metadata' => $metadata,
            ]);
            $latestAsset = $parent->assets()->orderByDesc('version')->first();
            if ($latestAsset) {
                $revision->assets()->create([
                    'version' => 1, 'provider' => $latestAsset->provider, 'provider_asset_id' => $latestAsset->provider_asset_id,
                    'disk' => $latestAsset->disk, 'path' => $latestAsset->path, 'mime_type' => $latestAsset->mime_type,
                    'width' => $latestAsset->width, 'height' => $latestAsset->height, 'content_hash' => $latestAsset->content_hash,
                    'prompt_version' => $latestAsset->prompt_version, 'prompt_text' => $latestAsset->prompt_text,
                    'status' => $latestAsset->status, 'metadata' => $latestAsset->metadata,
                ]);
            }
            $checkCurrent = ($metadata['checked_draft_hash'] ?? null) === hash('sha256', $draftText)
                && (bool) ($metadata['fact_check']['pass'] ?? false);
            $articleStatus = $checkCurrent ? ArticleStatus::READY_FOR_REVIEW : ArticleStatus::FLAGGED;
            $article->update(['status' => $articleStatus]);
            $revision->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => $event,
                'before_state' => ['parent_post_id' => $parent->id, 'parent_version' => $parent->version],
                'after_state' => ['post_id' => $revision->id, 'version' => $revision->version, 'article_status' => $articleStatus->value],
            ]);
            $article->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => $event,
                'before_state' => ['post_id' => $parent->id, 'version' => $parent->version],
                'after_state' => ['post_id' => $revision->id, 'version' => $revision->version, 'article_status' => $articleStatus->value],
            ]);
            if ($parent->status === GeneratedPostStatus::APPROVED) {
                $parent->auditLogs()->create([
                    'actor_type' => 'user', 'actor_id' => $reviewer->id, 'event' => 'review.approval_superseded',
                    'after_state' => ['approved_post_id' => $parent->id, 'new_post_id' => $revision->id, 'new_version' => $version],
                ]);
            }

            return $revision;
        });
    }
}
