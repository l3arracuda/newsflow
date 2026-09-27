# PHASE_REPORT

## Phase
Phase 08 — Review & Approval

## Status
PASS

## Summary
Added an admin-only review workspace that presents the assembled pre-publication package (source snapshot, extracted facts, summary, Facebook draft, fact-check result, generated images, workflow history, and prior decisions). Reviewers can edit the draft, regenerate summary/rewrite/image, rerun fact-checking, approve, reject, or request changes. Approval requires a current passing fact-check or an audited reasoned override, and an image or an explicit reason for omitting one. Approval creates a content/version hash snapshot and does not publish. Subsequent edits create a new version and invalidate the old approval for the new version. The admin article list has a “ดึงข่าวล่าสุด” action for ThaiRath Society and a per-article “เริ่ม Workflow” action; duplicate active runs are prevented, and failed/cancelled articles can be started again. Fact extraction now stores typed numeric claims with verbatim source quotes, verifies those quotes against the fetched snapshot, and the checker reconciles numerical values deterministically while grounding text findings in exact draft/source excerpts.

## Files changed
- `app/Enums/ArticleStatus.php`
- `app/Enums/GeneratedPostStatus.php`
- `app/AI/FactCheckReconciler.php`
- `app/AI/FactEvidenceValidator.php`
- `app/AI/FakeAiTextProvider.php`
- `app/AI/Pipelines/AiTextPipeline.php`
- `app/Http/Controllers/Admin/ReviewController.php`
- `app/Http/Controllers/Admin/ArticleController.php`
- `app/Reviews/ReviewService.php`
- `app/Workflows/Processors/CheckFactsProcessor.php`
- `app/Workflows/Processors/ExtractFactsProcessor.php`
- `database/seeders/AiPromptTemplateSeeder.php`
- `resources/views/admin/review/show.blade.php`
- `resources/views/articles/index.blade.php`
- `resources/views/articles/show.blade.php`
- `resources/views/partials/status-badge.blade.php`
- `routes/web.php`
- `tests/Feature/ReviewApprovalTest.php`
- `tests/Feature/AiTextPipelineTest.php`
- `tests/Feature/NewsDiscoveryUiTest.php`
- `tests/Feature/WorkflowStartUiTest.php`
- `tests/Unit/FactCheckReconcilerTest.php`
- `PHASE_REPORT.md`

## Database migrations
- None in Phase 08. Existing workflow, generated-post, asset, audit, and review-decision tables are reused.

## Commands run
```text
vendor\bin\pint app database tests routes resources config
php artisan test --filter=ReviewApprovalTest
php artisan test --filter=NewsDiscoveryUiTest
php artisan test --filter=WorkflowStartUiTest
php artisan test --filter=FactCheckReconcilerTest
php artisan test
npm run build
git diff --check
```

## Tests
### Targeted
```text
ReviewApprovalTest: 14 passed (111 assertions)
AiTextPipelineTest: 7 passed
NewsDiscoveryUiTest: 3 passed (11 assertions)
WorkflowStartUiTest: 3 passed (13 assertions)
FactCheckReconcilerTest: 3 passed (12 assertions)
```

### Full suite
```text
99 passed (465 assertions)
Production asset build: passed
git diff --check: passed
```

Test suite forces text and image drivers to `fake`, even when local `.env` enables paid providers.
The rewrite processor appends source attribution if an AI provider omits the URL; regression tests cover this behavior.
OpenAI image generation sends the configured `IMAGE_GENERATION_QUALITY` (default `low`) to the provider so image cost/quality is explicit.
Manual image mode imports a validated image named with the article ID from `storage/app/private/manual-news-images/`; the approved snapshot records only the latest selected asset version.
The fact-check regression fixture verifies the Thai gold-price example, rejects AI-quoted draft text that does not exist, and emits source evidence for mismatched amounts. Existing posts with legacy facts are upgraded from their saved source snapshot the next time “ตรวจใหม่” is used.

## Manual verification
1. Switch to `dev/phase-08-review-approval`, start the local Laravel app, and sign in with an admin account.
2. Open Articles, select an article with a completed Phase 07 workflow, and choose “เปิดชุดตรวจและอนุมัติ”. Confirm the source, facts, summary, draft, fact-check, image, and workflow history are visible together.
3. Try approving a draft with a failing/stale fact-check or without an image; confirm a reason is required for an override or explicit no-image approval.
4. Approve a complete package and confirm the version snapshot/status is recorded but no publication is created or sent to Facebook.
5. Edit an approved draft; confirm a new version is created and the new version must be checked and approved separately.
6. For manual imagery, place `{article_id}.png` (or `.jpg`, `.jpeg`, `.webp`) in `storage/app/private/manual-news-images/`, then select “นำเข้าภาพจากโฟลเดอร์” on the review page. The source file stays in place and each changed image is copied into a new immutable asset version.
7. From Articles, click “ดึงข่าวล่าสุด”; confirm the ThaiRath Society discovery result reports candidate, new, and existing article counts. This does not start content-generation workflows.
8. For an article with no run, click “เริ่ม Workflow” in its row. With the database queue driver, keep `php artisan queue:work` running and verify the run advances on the workflow page. Confirm duplicate clicks do not create duplicate runs.
9. Run the prompt seeder after deployment, then rerun fact-check on an existing post; confirm typed amounts include source quotes and the checker only flags text actually present in the draft.

## Security / data notes
- Review page, preview assets, and review actions are protected by admin middleware.
- Approval overrides, no-image reasons, decisions, and version changes are recorded in audit/review history.
- Generated asset previews are served through an authenticated route with a restricted MIME allowlist and private caching.
- No live Facebook publishing or real external AI call is performed by approval; publication remains a separate later phase.
- Set `IMAGE_GENERATION_DRIVER=manual` to avoid automatic image API calls and fake one-pixel fixtures; manual image import is required before approval unless the reviewer explicitly records a no-image reason.
- No secrets are included in the phase changes.
- For legacy successful runs containing Phase 04 placeholders, `news:workflow:reprocess-placeholder {articleId}` starts a fresh run only if no post has been approved or published; prior workflow and draft versions are preserved.

## Known limitations
- Review content generation uses the project's configured AI/image providers; local tests use fakes and do not validate external provider credentials.
- AI may still miss or misclassify qualitative claims; flagged text must now quote the current draft, and mismatch evidence must link back to a source quote validated against the saved source snapshot. Numeric facts with verified typed evidence are reconciled deterministically.
- Phase 08 ends at human approval. Facebook publishing is not included.

## Required user configuration
- Set `IMAGE_GENERATION_DRIVER=manual` in local `.env`, then clear Laravel's config cache. Keep `AI_TEXT_DRIVER` unchanged if AI text generation is still desired.
- Drop one image named `{article_id}.png` (or `.jpg`, `.jpeg`, `.webp`) in `storage/app/private/manual-news-images/` before importing it from review.
- Run `php artisan db:seed --class=AiPromptTemplateSeeder` once after updating the code to activate evidence-backed prompt version 2. Rechecking an existing legacy post will make one additional text-extraction API call to upgrade its stored facts; subsequent rechecks use the saved evidence facts.

## Acceptance checklist
- [x] Complete pre-publication package is visible in one review workspace.
- [x] Draft, summary, rewrite, image, and fact-check actions create/audit the expected reviewable state.
- [x] Approval gating, reasoned exceptions, immutable version snapshot, and re-approval after edits are enforced.
- [x] Approval does not publish.
- [x] Numeric facts carry source evidence and are deterministically reconciled; non-numeric flags are grounded in exact draft/source quotes.
- [x] Tests pass.
- [x] No secrets committed.
- [x] No next phase work included.

## ACCEPTANCE GATE
PASS
