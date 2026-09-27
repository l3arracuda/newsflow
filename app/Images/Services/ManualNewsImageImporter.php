<?php

namespace App\Images\Services;

use App\Enums\GeneratedPostStatus;
use App\Models\Article;
use App\Models\GeneratedAsset;
use App\Models\GeneratedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ManualNewsImageImporter
{
    private const INPUT_DIRECTORY = 'manual-news-images';

    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public function import(Article $article, GeneratedPost $post, User $user): GeneratedAsset
    {
        if ($post->article_id !== $article->id) {
            throw new RuntimeException('Draft นี้ไม่ได้อยู่ในข่าวที่เลือก');
        }

        if ($article->generatedPosts()->latest('version')->value('id') !== $post->id) {
            throw new RuntimeException('นำเข้าภาพได้เฉพาะ draft version ล่าสุด');
        }

        if (! in_array($post->status, [GeneratedPostStatus::DRAFT, GeneratedPostStatus::CHANGES_REQUESTED], true)) {
            throw new RuntimeException('นำเข้าภาพได้เฉพาะ draft ที่ยังไม่อนุมัติหรือเผยแพร่');
        }

        $disk = Storage::disk('local');
        $matches = collect(self::EXTENSIONS)
            ->map(fn (string $extension) => self::INPUT_DIRECTORY.'/'.$article->id.'.'.$extension)
            ->filter(fn (string $path) => $disk->exists($path))
            ->values();

        if ($matches->isEmpty()) {
            throw new RuntimeException('ไม่พบไฟล์ภาพ กรุณาวางไฟล์ชื่อ '.$article->id.'.png, .jpg, .jpeg หรือ .webp ในโฟลเดอร์ '.$disk->path(self::INPUT_DIRECTORY));
        }
        if ($matches->count() > 1) {
            throw new RuntimeException('พบไฟล์ภาพมากกว่าหนึ่งนามสกุลสำหรับข่าวนี้ ให้เหลือไฟล์ชื่อ ID ข่าวเพียงไฟล์เดียว');
        }

        $inputPath = $matches->first();
        $contents = $disk->get($inputPath);
        $maxBytes = (int) config('services.image_generation.max_bytes', 20 * 1024 * 1024);
        if (! is_string($contents) || $contents === '' || strlen($contents) > $maxBytes) {
            throw new RuntimeException('ไฟล์ภาพว่างหรือมีขนาดเกิน '.number_format($maxBytes / 1024 / 1024, 0).' MB');
        }

        $imageInfo = @getimagesizefromstring($contents);
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new RuntimeException('ไฟล์ไม่ใช่ภาพ PNG, JPG หรือ WebP ที่ระบบรองรับ');
        }

        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        $expectedMime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
        if ($mime !== $expectedMime) {
            throw new RuntimeException('นามสกุลไฟล์ไม่ตรงกับชนิดภาพ กรุณาบันทึกภาพและนามสกุลให้ตรงกัน');
        }

        $hash = hash('sha256', $contents);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($article, $post, $user, $disk, $inputPath, $contents, $imageInfo, $mime, $extension, $hash, &$storedPath) {
                $lockedPost = GeneratedPost::query()->lockForUpdate()->findOrFail($post->id);
                $existing = $lockedPost->assets()->where('provider', 'manual')->where('content_hash', $hash)->first();
                if ($existing) {
                    return $existing;
                }

                $version = ((int) $lockedPost->assets()->max('version')) + 1;
                if ($version > 65535) {
                    throw new RuntimeException('จำนวน version ภาพเกินขีดจำกัดของระบบ');
                }
                $storedPath = "generated/manual-newsflow/{$article->id}/{$lockedPost->id}/{$version}-{$hash}.{$extension}";
                if (! $disk->put($storedPath, $contents)) {
                    throw new RuntimeException('บันทึกสำเนาภาพในระบบไม่สำเร็จ');
                }

                $asset = $lockedPost->assets()->create([
                    'version' => $version,
                    'provider' => 'manual',
                    'disk' => 'local',
                    'path' => $storedPath,
                    'mime_type' => $mime,
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'content_hash' => $hash,
                    'prompt_version' => 1,
                    'prompt_text' => null,
                    'status' => 'generated',
                    'metadata' => [
                        'origin' => 'manual_news_image_folder',
                        'source_filename' => basename($inputPath),
                        'article_id' => $article->id,
                        'imported_by' => $user->id,
                    ],
                ]);
                $audit = [
                    'actor_type' => 'user', 'actor_id' => $user->id,
                    'event' => 'review.manual_image_imported',
                    'after_state' => ['asset_id' => $asset->id, 'version' => $asset->version, 'content_hash' => $hash],
                    'metadata' => ['source_filename' => basename($inputPath), 'article_id' => $article->id],
                ];
                $lockedPost->auditLogs()->create($audit);
                $article->auditLogs()->create($audit);

                return $asset;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }

            throw $exception;
        }
    }
}
