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
- Approval alone never publishes. Phase 09 adds a separate explicit Facebook publish action.
- Set `IMAGE_GENERATION_DRIVER=manual` to avoid automatic image API calls and fake one-pixel fixtures; manual image import is required before approval unless the reviewer explicitly records a no-image reason.
- No secrets are included in the phase changes.
- For legacy successful runs containing Phase 04 placeholders, `news:workflow:reprocess-placeholder {articleId}` starts a fresh run only if no post has been approved or published; prior workflow and draft versions are preserved.

## Known limitations
- Review content generation uses the project's configured AI/image providers; local tests use fakes and do not validate external provider credentials.
- AI may still miss or misclassify qualitative claims; flagged text must now quote the current draft, and mismatch evidence must link back to a source quote validated against the saved source snapshot. Numeric facts with verified typed evidence are reconciled deterministically.
- Phase 08 ended at human approval; Phase 09 adds a separate opt-in publisher adapter (live credentials still unverified).

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

---

## Phase 09 checkpoint — Facebook Publisher

### Status
PASS — fake provider fully tested; Meta live publishing is implemented but not live-verified.

### Delivered
- Added `SocialPublisher` contract with fake and Meta Graph API implementations; `PUBLISH_DRIVER=fake` and `AUTO_PUBLISH=false` are defaults.
- Added an explicit publish action on the admin review page. It is available only for the approved immutable post version and approved image asset; source attribution and URL are required.
- Persisted publication state, external post id/link, timestamps, and sanitized outcome metadata in the existing publications table. Added publisher/user audit events.
- Idempotency key is unique per approved post/version/content/image. A definitive provider rejection can be retried using the same publication record. A timeout or unconfirmed provider response becomes `uncertain` and is deliberately blocked from retry to avoid duplicate posts.
- Meta settings are environment-driven (`META_GRAPH_VERSION`, `META_PAGE_ID`, `META_PAGE_ACCESS_TOKEN`, `META_PUBLISH_TIMEOUT`); no credentials are stored in database or source.
- Meta adapter sends a Page photo post to the configured Graph API version and does not place the token in the URL. Automated tests use HTTP fakes and never make a live request.

### Tests and verification
- `ReviewApprovalTest`: 19 passed (147 assertions).
- Full suite: 104 passed (501 assertions).
- Pint check: passed; `npm run build`: passed; `git diff --check`: passed.
- Added regression coverage for publish success, no duplicate on repeat, unapproved/edited snapshot rejection, definitive retry, uncertain outcome lockout, and configured Meta endpoint/token handling.

### Optional live setup / verification
1. In the local `.env` only, set `PUBLISH_DRIVER=meta`, `AUTO_PUBLISH=false`, `META_GRAPH_VERSION` to a currently supported version, `META_PAGE_ID`, and `META_PAGE_ACCESS_TOKEN` with the required Page publishing permission.
2. Clear Laravel config cache and restart the app/queue workers. Never paste the token into chat or commit `.env`.
3. Approve a test post with the correct source attribution and image, open its review page, and click “เผยแพร่ไป Facebook” once.
4. Confirm the Page post, external link, timestamp, status, and audit record. Do not retry if the screen says the outcome is uncertain; first inspect the Page manually.

### Limits / outstanding live checks
- Meta credentials, app review/permission grants, selected Page, and current supported Graph API version were not available here; live publishing is **not live verified**.
- Meta's official documentation endpoint returned HTTP 429 during this task. The API version is therefore supplied by configuration, not guessed or hard-coded. Confirm current endpoint and permission requirements in Meta's official [Page Photos reference](https://developers.facebook.com/docs/graph-api/reference/page/photos/) and [permissions reference](https://developers.facebook.com/docs/permissions/).
- Network timeout is inherently ambiguous; the system blocks retry and requires a human to inspect the Page before any further action.

## Current checkpoint
- Branch: `dev/phase-09-facebook-publisher`
- Acceptance gate for Phase 09: PASS for automated/fake behavior; live integration remains not live verified.
