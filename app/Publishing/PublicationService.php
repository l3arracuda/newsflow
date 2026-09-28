<?php

namespace App\Publishing;

use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\PublicationStatus;
use App\Models\GeneratedAsset;
use App\Models\GeneratedPost;
use App\Models\Publication;
use App\Models\User;
use App\Publishing\Contracts\SocialPublisher;
use App\Publishing\DTO\PublicationRequest;
use App\Publishing\Exceptions\DefinitivePublishFailure;
use App\Publishing\Exceptions\UncertainPublishOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublicationService
{
    public function __construct(private readonly SocialPublisher $publisher) {}

    public function publish(GeneratedPost $post, User $publisher): Publication
    {
        if (! config('services.facebook.enabled', true)) {
            throw new RuntimeException('การเผยแพร่ถูกปิดด้วย emergency kill switch');
        }

        [$publication, $asset, $alreadyPublished] = DB::transaction(function () use ($post, $publisher) {
            $locked = GeneratedPost::query()->lockForUpdate()->with('article')->findOrFail($post->id);
            if ($locked->status !== GeneratedPostStatus::APPROVED) {
                throw new RuntimeException('เผยแพร่ได้เฉพาะฉบับที่อนุมัติแล้วเท่านั้น');
            }

            $snapshot = $locked->metadata['approval_snapshot'] ?? [];
            if (($snapshot['post_id'] ?? null) !== $locked->id
                || ($snapshot['post_version'] ?? null) !== $locked->version
                || ! hash_equals((string) ($snapshot['content_hash'] ?? ''), hash('sha256', $locked->draft_text))) {
                throw new RuntimeException('ฉบับนี้ไม่ตรงกับ snapshot ที่อนุมัติ กรุณาตรวจและอนุมัติใหม่');
            }
            if (trim($locked->source_url) === '' || ! str_contains($locked->draft_text, $locked->source_url)
                || trim($locked->source_attribution) === '') {
                throw new RuntimeException('เผยแพร่ไม่ได้: ต้องมีข้อความอ้างอิงและ URL ต้นทางในโพสต์');
            }
            if (($snapshot['no_image'] ?? true) || empty($snapshot['asset_versions'])) {
                throw new RuntimeException('เผยแพร่ไม่ได้: ฉบับที่อนุมัติต้องมีภาพประกอบ');
            }

            $approvedAsset = $snapshot['asset_versions'][0] ?? [];
            $asset = $locked->assets()->whereKey($approvedAsset['id'] ?? 0)->first();
            if (! $asset instanceof GeneratedAsset || $asset->content_hash !== ($approvedAsset['content_hash'] ?? null)) {
                throw new RuntimeException('ไม่พบภาพ version ที่อนุมัติไว้');
            }

            $key = hash('sha256', implode(':', ['facebook', $locked->id, $locked->version, $snapshot['content_hash'], $asset->id, $asset->content_hash]));
            $publication = Publication::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($publication?->status === PublicationStatus::PUBLISHED) {
                return [$publication, $asset, true];
            }
            if (in_array($publication?->status, [PublicationStatus::PUBLISHING, PublicationStatus::PENDING, PublicationStatus::UNCERTAIN], true)) {
                throw new RuntimeException('รายการนี้กำลังเผยแพร่หรือผลลัพธ์ยังไม่แน่ชัด ห้ามกดซ้ำเพื่อป้องกันโพสต์ซ้ำ');
            }

            $publication ??= new Publication;
            $publication->fill([
                'generated_post_id' => $locked->id,
                'channel' => 'facebook',
                'provider' => config('services.facebook.driver'),
                'status' => PublicationStatus::PUBLISHING,
                'idempotency_key' => $key,
                'metadata' => ['post_version' => $locked->version, 'content_hash' => $snapshot['content_hash'], 'asset_id' => $asset->id, 'asset_hash' => $asset->content_hash],
            ])->save();
            $publication->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $publisher->id, 'event' => 'publication.started',
                'after_state' => ['status' => PublicationStatus::PUBLISHING->value, 'channel' => 'facebook'],
                'metadata' => ['post_id' => $locked->id, 'post_version' => $locked->version, 'idempotency_key' => $key],
            ]);

            return [$publication, $asset, false];
        });

        if ($alreadyPublished) {
            return $publication;
        }

        try {
            $disk = Storage::disk($asset->disk);
            $contents = $disk->get($asset->path);
            if (! hash_equals($asset->content_hash, hash('sha256', $contents))) {
                throw new DefinitivePublishFailure('ไฟล์ภาพไม่ตรงกับ version ที่อนุมัติ');
            }
            $result = $this->publisher->publish(new PublicationRequest(
                message: $post->draft_text,
                sourceUrl: $post->source_url,
                idempotencyKey: $publication->idempotency_key,
                imageContents: $contents,
                imageMimeType: $asset->mime_type,
                imageFileName: 'news-'.$post->article_id.'.'.match ($asset->mime_type) {
                    'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
                },
            ));
        } catch (DefinitivePublishFailure $exception) {
            return $this->recordFailure($publication, $publisher, $exception->getMessage(), PublicationStatus::FAILED);
        } catch (UncertainPublishOutcome $exception) {
            return $this->recordFailure($publication, $publisher, $exception->getMessage(), PublicationStatus::UNCERTAIN);
        } catch (Throwable) {
            return $this->recordFailure($publication, $publisher, 'ผลตอบกลับไม่ครบถ้วน ระบบพักการ retry ไว้เพื่อป้องกันโพสต์ซ้ำ', PublicationStatus::UNCERTAIN);
        }

        return DB::transaction(function () use ($publication, $publisher, $result) {
            $lockedPublication = Publication::query()->lockForUpdate()->findOrFail($publication->id);
            $lockedPublication->update([
                'external_post_id' => $result->externalPostId,
                'external_url' => $result->externalUrl,
                'status' => PublicationStatus::PUBLISHED,
                'published_at' => now(),
                'metadata' => array_merge($lockedPublication->metadata ?? [], ['provider_result' => 'success']),
            ]);
            $lockedPublication->generatedPost()->update(['status' => GeneratedPostStatus::PUBLISHED]);
            $lockedPublication->generatedPost->article()->update(['status' => ArticleStatus::PUBLISHED]);
            $lockedPublication->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $publisher->id, 'event' => 'publication.published',
                'after_state' => ['status' => PublicationStatus::PUBLISHED->value, 'external_post_id' => $result->externalPostId, 'published_at' => now()->toIso8601String()],
                'metadata' => ['channel' => 'facebook', 'post_version' => $lockedPublication->metadata['post_version'] ?? null],
            ]);

            return $lockedPublication;
        });
    }

    private function recordFailure(Publication $publication, User $publisher, string $reason, PublicationStatus $status): Publication
    {
        return DB::transaction(function () use ($publication, $publisher, $reason, $status) {
            $locked = Publication::query()->lockForUpdate()->findOrFail($publication->id);
            $locked->update(['status' => $status, 'metadata' => array_merge($locked->metadata ?? [], ['failure_reason' => $reason])]);
            $locked->auditLogs()->create([
                'actor_type' => 'user', 'actor_id' => $publisher->id, 'event' => 'publication.'.($status === PublicationStatus::UNCERTAIN ? 'uncertain' : 'failed'),
                'after_state' => ['status' => $status->value], 'metadata' => ['reason' => $reason],
            ]);

            return $locked;
        });
    }
}
