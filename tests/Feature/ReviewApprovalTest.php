<?php

namespace Tests\Feature;

use App\AI\Contracts\SocialPostRewriter;
use App\Enums\ArticleStatus;
use App\Enums\GeneratedPostStatus;
use App\Enums\PublicationStatus;
use App\Enums\ReviewDecisionType;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Source;
use App\Models\User;
use App\Publishing\Contracts\SocialPublisher;
use App\Publishing\DTO\PublicationRequest;
use App\Publishing\DTO\PublishResult;
use App\Publishing\Exceptions\DefinitivePublishFailure;
use App\Publishing\Exceptions\UncertainPublishOutcome;
use App\Publishing\Providers\MetaFacebookPublisher;
use Database\Seeders\AiPromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new AiPromptTemplateSeeder)->run();
        Storage::fake('local');
    }

    public function test_review_screen_shows_pre_post_package_and_does_not_publish(): void
    {
        [$article, $post, $admin] = $this->reviewCase();

        $response = $this->withoutVite()->actingAs($admin)->get(route('articles.review', $article))->assertOk();
        $response->assertSee('ชุดตรวจข่าว')->assertSee($article->title)->assertSee('เนื้อหาต้นทางสำหรับ review');
        $response->assertSee('เหตุการณ์ที่ยืนยัน')->assertSee('สรุปที่ตรวจสอบได้')->assertSee($post->draft_text);
        $response->assertSee('ผลตรวจความสอดคล้อง')->assertSee('ภาพประกอบที่สร้างใหม่')->assertSee('ไม่มีการส่งไป Facebook');
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_cannot_approve_incomplete_text(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $draft = 'ร่างที่ไม่มีลิงก์ต้นทางครบถ้วน';
        $metadata = $post->metadata;
        $metadata['checked_draft_hash'] = hash('sha256', $draft);
        $post->update(['draft_text' => $draft, 'metadata' => $metadata]);

        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertSame(GeneratedPostStatus::DRAFT, $post->fresh()->status);
        $this->assertSame(ArticleStatus::READY_FOR_REVIEW, $article->fresh()->status);
        $this->assertDatabaseCount('review_decisions', 0);
    }

    public function test_approval_happy_path_locks_a_snapshot_and_does_not_publish(): void
    {
        [$article, $post, $admin] = $this->reviewCase();

        $this->actingAs($admin)->post(route('review.approve', $post), ['note' => 'ตรวจแล้ว ผ่าน'])->assertRedirect();

        $approved = $post->fresh();
        $this->assertSame(GeneratedPostStatus::APPROVED, $approved->status);
        $this->assertSame(ArticleStatus::APPROVED, $article->fresh()->status);
        $this->assertSame(hash('sha256', $post->draft_text), $approved->metadata['approval_snapshot']['content_hash']);
        $this->assertSame($post->version, $approved->metadata['approval_snapshot']['post_version']);
        $this->assertSame($admin->id, $approved->metadata['approval_snapshot']['approved_by']);
        $this->assertSame(ReviewDecisionType::APPROVE, $approved->reviewDecisions()->firstOrFail()->decision);
        $this->assertDatabaseHas('audit_logs', ['event' => 'review.approved', 'actor_id' => $admin->id]);
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_approved_post_publishes_once_and_records_external_result_and_audit(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        config(['services.facebook.driver' => 'fake']);
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();

        $this->post(route('review.publish', $post))->assertRedirect();
        $publication = $post->publications()->firstOrFail();
        $this->assertSame(PublicationStatus::PUBLISHED, $publication->status);
        $this->assertNotEmpty($publication->external_post_id);
        $this->assertSame(GeneratedPostStatus::PUBLISHED, $post->fresh()->status);
        $this->assertSame(ArticleStatus::PUBLISHED, $article->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'publication.published', 'actor_id' => $admin->id]);

        $this->actingAs($admin)->post(route('review.publish', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_unapproved_or_modified_approval_snapshot_cannot_be_published(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        config(['services.facebook.driver' => 'fake']);
        $this->actingAs($admin)->post(route('review.publish', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();
        $post->update(['draft_text' => $post->draft_text."\nเปลี่ยนหลังอนุมัติ"]);

        $this->post(route('review.publish', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertDatabaseCount('publications', 0);
    }

    public function test_definitive_provider_failure_can_be_retried_without_new_publication(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();
        $publisher = $this->mock(SocialPublisher::class);
        $publisher->shouldReceive('publish')->once()->withArgs(fn (PublicationRequest $request) => str_contains($request->message, $post->source_url))
            ->andThrow(new DefinitivePublishFailure('provider rejected'));
        $this->post(route('review.publish', $post))->assertRedirect();
        $this->assertSame(PublicationStatus::FAILED, $post->publications()->firstOrFail()->status);

        $this->app->instance(SocialPublisher::class, new class implements SocialPublisher
        {
            public function publish(PublicationRequest $request): PublishResult
            {
                return new PublishResult('retry-success-1', 'https://facebook.test/posts/retry-success-1');
            }
        });
        $this->post(route('review.publish', $post))->assertRedirect();
        $this->assertDatabaseCount('publications', 1);
        $this->assertSame(PublicationStatus::PUBLISHED, $post->publications()->firstOrFail()->status);
    }

    public function test_uncertain_provider_result_blocks_retry_to_avoid_duplicate_post(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();
        $publisher = $this->mock(SocialPublisher::class);
        $publisher->shouldReceive('publish')->once()->andThrow(new UncertainPublishOutcome('timeout after sending'));

        $this->post(route('review.publish', $post))->assertRedirect();
        $this->assertSame(PublicationStatus::UNCERTAIN, $post->publications()->firstOrFail()->status);
        $this->post(route('review.publish', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_meta_adapter_uses_configured_page_endpoint_and_keeps_token_out_of_url(): void
    {
        config(['services.facebook.graph_version' => 'v99.0', 'services.facebook.page_id' => 'page-123', 'services.facebook.page_access_token' => 'private-token']);
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'page-123_post-456'], 200)]);
        $result = app(MetaFacebookPublisher::class)->publish(new PublicationRequest(
            message: 'ข้อความข่าว', sourceUrl: 'https://example.com/news', idempotencyKey: 'stable-key',
            imageContents: 'png-bytes', imageMimeType: 'image/png', imageFileName: 'news-1.png',
        ));

        $this->assertSame('page-123_post-456', $result->externalPostId);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v99.0/page-123/photos')
            && ! str_contains($request->url(), 'private-token')
            && $request->hasHeader('Authorization', 'Bearer private-token'));
    }

    public function test_fact_check_override_requires_and_records_reason(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $metadata = $post->metadata;
        $metadata['fact_check'] = ['pass' => false, 'severity' => 'high', 'mismatches' => [], 'unsupported_claims' => ['ข้ออ้างไม่รองรับ']];
        $post->update(['metadata' => $metadata]);

        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->actingAs($admin)->post(route('review.approve', $post), ['override_reason' => 'ตรวจสอบกับเอกสารเพิ่มเติมแล้ว'])->assertRedirect();

        $snapshot = $post->fresh()->metadata['approval_snapshot'];
        $this->assertTrue($snapshot['fact_check_overridden']);
        $this->assertSame('ตรวจสอบกับเอกสารเพิ่มเติมแล้ว', $snapshot['override_reason']);
        $this->assertDatabaseHas('review_decisions', ['generated_post_id' => $post->id, 'decision' => ReviewDecisionType::APPROVE->value]);
    }

    public function test_no_image_approval_requires_explicit_choice_and_reason(): void
    {
        [$article, $post, $admin] = $this->reviewCase(withImage: false);

        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect()->assertSessionHasErrors('review');
        $this->actingAs($admin)->post(route('review.approve', $post), ['no_image' => 1])->assertRedirect()->assertSessionHasErrors('review');
        $this->actingAs($admin)->post(route('review.approve', $post), ['no_image' => 1, 'no_image_reason' => 'ไม่จำเป็นต่อเนื้อหาข่าว'])->assertRedirect();

        $snapshot = $post->fresh()->metadata['approval_snapshot'];
        $this->assertTrue($snapshot['no_image']);
        $this->assertSame([], $snapshot['asset_versions']);
    }

    public function test_edit_after_approval_creates_new_version_and_keeps_approval_history(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $this->actingAs($admin)->post(route('review.approve', $post))->assertRedirect();
        $edited = $post->draft_text."\n\nเพิ่มเติมที่ตรวจสอบแล้ว";

        $this->patch(route('review.edit', $post), ['draft_text' => $edited])->assertRedirect();

        $revision = $article->generatedPosts()->latest('version')->firstOrFail();
        $this->assertNotSame($post->id, $revision->id);
        $this->assertSame(2, $revision->version);
        $this->assertSame(GeneratedPostStatus::APPROVED, $post->fresh()->status);
        $this->assertSame(GeneratedPostStatus::DRAFT, $revision->status);
        $this->assertSame($edited, $revision->draft_text);
        $this->assertArrayNotHasKey('fact_check', $revision->metadata);
        $this->assertArrayNotHasKey('approval_snapshot', $revision->metadata);
        $this->assertSame(ArticleStatus::FLAGGED, $article->fresh()->status);
        $this->assertSame(1, $revision->assets()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'review.approval_superseded']);
        $this->assertSame(1, $post->reviewDecisions()->count());
    }

    public function test_regenerate_summary_rewrite_and_fact_check_create_reviewable_versions(): void
    {
        [$article, $post, $admin] = $this->reviewCase(withImage: false);

        $this->actingAs($admin)->post(route('review.summary.regenerate', $post))->assertRedirect();
        $summaryRevision = $article->generatedPosts()->latest('version')->firstOrFail();
        $this->assertSame(2, $summaryRevision->version);
        $this->assertNotEmpty($summaryRevision->metadata['summary']['summary']);

        $this->post(route('review.rewrite.regenerate', $summaryRevision))->assertRedirect();
        $rewriteRevision = $article->generatedPosts()->latest('version')->firstOrFail();
        $this->assertSame(3, $rewriteRevision->version);
        $this->assertStringContainsString($article->source_url, $rewriteRevision->draft_text);
        $this->assertArrayNotHasKey('fact_check', $rewriteRevision->metadata);

        $this->post(route('review.fact-check', $rewriteRevision))->assertRedirect();
        $checked = $rewriteRevision->fresh();
        $this->assertTrue($checked->metadata['fact_check']['pass']);
        $this->assertSame(2, $checked->metadata['facts']['evidence_schema_version']);
        $this->assertSame(hash('sha256', $checked->draft_text), $checked->metadata['checked_draft_hash']);
    }

    public function test_rewrite_adds_source_attribution_if_provider_omits_it(): void
    {
        [$article, $post, $admin] = $this->reviewCase(withImage: false);
        $this->app->instance(SocialPostRewriter::class, new class implements SocialPostRewriter
        {
            public function rewrite(string $title, array $facts, string $summary, string $sourceName, string $sourceUrl): array
            {
                return ['title' => 'หัวข้อทดสอบ', 'body' => 'เนื้อหาโพสต์ทดสอบที่ไม่มีลิงก์', 'hook' => ''];
            }
        });

        $this->actingAs($admin)->post(route('review.rewrite.regenerate', $post))->assertRedirect();

        $revision = $article->generatedPosts()->latest('version')->firstOrFail();
        $this->assertStringContainsString("ที่มา: {$article->source->name} {$article->source_url}", $revision->draft_text);
    }

    public function test_regenerate_image_appends_asset_version_and_preserves_original(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $old = $post->assets()->firstOrFail();

        $this->actingAs($admin)->post(route('review.image.regenerate', $post))->assertRedirect();

        $this->assertSame(2, $post->assets()->count());
        $this->assertSame(1, $old->fresh()->version);
        $this->assertSame(2, $post->assets()->orderByDesc('version')->firstOrFail()->version);
        Storage::disk('local')->assertExists($old->path);
    }

    public function test_manual_image_is_imported_by_article_id_and_locked_as_the_approved_asset(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        $filename = "manual-news-images/{$article->id}.png";
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4XcAAAAASUVORK5CYII=', true);
        Storage::disk('local')->put($filename, $image);

        $this->actingAs($admin)->post(route('review.image.import-manual', $article), ['post' => $post->id])->assertRedirect()->assertSessionHasNoErrors();

        $manual = $post->assets()->orderByDesc('version')->firstOrFail();
        $this->assertSame('manual', $manual->provider);
        $this->assertSame(2, $manual->version);
        $this->assertSame($article->id, $manual->metadata['article_id']);
        Storage::disk('local')->assertExists($filename);
        Storage::disk('local')->assertExists($manual->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'review.manual_image_imported']);

        $this->actingAs($admin)->post(route('review.image.import-manual', $article), ['post' => $post->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $post->assets()->count());

        $this->post(route('review.approve', $post))->assertRedirect();
        $approvedAssets = $post->fresh()->metadata['approval_snapshot']['asset_versions'];
        $this->assertCount(1, $approvedAssets);
        $this->assertSame($manual->id, $approvedAssets[0]['id']);

        $this->actingAs($admin)->post(route('review.image.import-manual', $article), ['post' => $post->id])->assertRedirect()->assertSessionHasErrors('review');
        $this->assertSame(2, $post->assets()->count());
    }

    public function test_manual_image_import_rejects_invalid_image_bytes(): void
    {
        [$article, $post, $admin] = $this->reviewCase();
        Storage::disk('local')->put("manual-news-images/{$article->id}.png", 'not an image');

        $this->actingAs($admin)->post(route('review.image.import-manual', $article), ['post' => $post->id])
            ->assertRedirect()->assertSessionHasErrors('review');

        $this->assertSame(1, $post->assets()->count());
        $this->assertDatabaseMissing('generated_assets', ['provider' => 'manual']);
    }

    public function test_reject_records_decision_and_changes_article_status(): void
    {
        [$article, $post, $admin] = $this->reviewCase(withImage: false);

        $this->actingAs($admin)->post(route('review.reject', $post), ['note' => 'ไม่ตรงตามแนวทางบรรณาธิการ'])->assertRedirect();

        $this->assertSame(GeneratedPostStatus::REJECTED, $post->fresh()->status);
        $this->assertSame(ArticleStatus::REJECTED, $article->fresh()->status);
        $this->assertDatabaseHas('review_decisions', ['decision' => ReviewDecisionType::REJECT->value, 'user_id' => $admin->id]);
    }

    public function test_request_changes_and_reject_require_notes_and_record_reviewer(): void
    {
        [$article, $post, $admin] = $this->reviewCase(withImage: false);

        $this->actingAs($admin)->post(route('review.request-changes', $post), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('review.request-changes', $post), ['note' => 'โปรดแก้หัวข้อให้ชัดขึ้น'])->assertRedirect();
        $this->assertSame(GeneratedPostStatus::CHANGES_REQUESTED, $post->fresh()->status);
        $this->assertSame(ArticleStatus::CHANGES_REQUESTED, $article->fresh()->status);
        $this->assertDatabaseHas('review_decisions', ['decision' => ReviewDecisionType::REQUEST_CHANGES->value, 'user_id' => $admin->id]);
    }

    public function test_only_admin_can_open_review_or_change_a_decision(): void
    {
        [$article, $post] = $this->reviewCase();
        $this->get(route('articles.review', $article))->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('articles.review', $article))->assertForbidden();
        $this->post(route('review.approve', $post))->assertForbidden();
        $this->assertSame(GeneratedPostStatus::DRAFT, $post->fresh()->status);
    }

    private function reviewCase(bool $withImage = true): array
    {
        $source = Source::create([
            'name' => 'ThaiRath Society', 'key' => 'thairath_society', 'base_url' => 'https://www.thairath.co.th',
            'listing_url' => 'https://www.thairath.co.th/news/society', 'adapter' => 'thairath', 'is_active' => true,
        ]);
        $article = $source->articles()->create([
            'source_url' => 'https://www.thairath.co.th/news/society/review-test', 'title' => 'หัวข้อสำหรับตรวจทาน',
            'discovered_at' => now(), 'status' => ArticleStatus::READY_FOR_REVIEW,
        ]);
        $article->snapshots()->create(['normalized_excerpt' => 'เนื้อหาต้นทางสำหรับ review ที่ผ่านการจัดรูปแบบแล้ว', 'fetched_at' => now(), 'checksum' => hash('sha256', 'review-snapshot')]);
        $run = $article->workflowRuns()->create(['run_type' => 'article_pipeline', 'status' => WorkflowRunStatus::SUCCEEDED, 'attempt' => 1]);
        $run->steps()->create(['step_key' => 'extract_facts', 'name' => 'แยกข้อเท็จจริง', 'status' => WorkflowStepStatus::SUCCEEDED, 'attempt' => 1, 'metadata' => ['facts' => ['event_action' => 'เหตุการณ์ที่ยืนยัน', 'places' => ['กรุงเทพฯ']]]]);
        $draft = "ข่าวที่ตรวจสอบแล้ว\n\nรายละเอียดข่าวที่ยืนยันได้จากต้นทาง\nที่มา: ThaiRath Society https://www.thairath.co.th/news/society/review-test";
        $metadata = [
            'facts' => ['event_action' => 'เหตุการณ์ที่ยืนยัน', 'places' => ['กรุงเทพฯ']],
            'summary' => ['summary' => 'สรุปที่ตรวจสอบได้'],
            'rewrite' => ['title' => 'ข่าวที่ตรวจสอบแล้ว', 'body' => $draft],
            'fact_check' => ['pass' => true, 'severity' => 'none', 'mismatches' => [], 'unsupported_claims' => [], 'checked_at' => now()->toIso8601String()],
            'checked_draft_hash' => hash('sha256', $draft),
        ];
        $post = $article->generatedPosts()->create(['version' => 1, 'status' => GeneratedPostStatus::DRAFT, 'draft_text' => $draft, 'source_attribution' => $source->name, 'source_url' => $article->source_url, 'metadata' => $metadata]);
        if ($withImage) {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4XcAAAAASUVORK5CYII=');
            Storage::disk('local')->put('review/test.png', $png);
            $post->assets()->create(['version' => 1, 'provider' => 'fake', 'disk' => 'local', 'path' => 'review/test.png', 'mime_type' => 'image/png', 'width' => 1, 'height' => 1, 'content_hash' => hash('sha256', $png), 'prompt_version' => 1, 'prompt_text' => 'editorial illustration', 'status' => 'generated', 'metadata' => ['generated_illustration' => true, 'fake' => true]]);
        }
        $admin = User::factory()->create(['is_admin' => true]);

        return [$article, $post, $admin, $run];
    }
}
