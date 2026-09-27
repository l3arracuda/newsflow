# PHASE_REPORT

## Phase
Phase 08 — Review & Approval

## Status
PASS

## Summary
Added an admin-only review workspace that presents the assembled pre-publication package (source snapshot, extracted facts, summary, Facebook draft, fact-check result, generated images, workflow history, and prior decisions). Reviewers can edit the draft, regenerate summary/rewrite/image, rerun fact-checking, approve, reject, or request changes. Approval requires a current passing fact-check or an audited reasoned override, and an image or an explicit reason for omitting one. Approval creates a content/version hash snapshot and does not publish. Subsequent edits create a new version and invalidate the old approval for the new version.

## Files changed
- `app/Enums/ArticleStatus.php`
- `app/Enums/GeneratedPostStatus.php`
- `app/Http/Controllers/Admin/ReviewController.php`
- `app/Reviews/ReviewService.php`
- `app/Workflows/Processors/CheckFactsProcessor.php`
- `resources/views/admin/review/show.blade.php`
- `resources/views/articles/show.blade.php`
- `resources/views/partials/status-badge.blade.php`
- `routes/web.php`
- `tests/Feature/ReviewApprovalTest.php`
- `PHASE_REPORT.md`

## Database migrations
- None in Phase 08. Existing workflow, generated-post, asset, audit, and review-decision tables are reused.

## Commands run
```text
vendor\bin\pint app database tests routes resources config
php artisan test --filter=ReviewApprovalTest
php artisan test
npm run build
git diff --check
```

## Tests
### Targeted
```text
ReviewApprovalTest: 11 passed (85 assertions)
```

### Full suite
```text
83 passed (386 assertions)
Production asset build: passed
git diff --check: passed
```

## Manual verification
1. Switch to `dev/phase-08-review-approval`, start the local Laravel app, and sign in with an admin account.
2. Open Articles, select an article with a completed Phase 07 workflow, and choose “เปิดชุดตรวจและอนุมัติ”. Confirm the source, facts, summary, draft, fact-check, image, and workflow history are visible together.
3. Try approving a draft with a failing/stale fact-check or without an image; confirm a reason is required for an override or explicit no-image approval.
4. Approve a complete package and confirm the version snapshot/status is recorded but no publication is created or sent to Facebook.
5. Edit an approved draft; confirm a new version is created and the new version must be checked and approved separately.

## Security / data notes
- Review page, preview assets, and review actions are protected by admin middleware.
- Approval overrides, no-image reasons, decisions, and version changes are recorded in audit/review history.
- Generated asset previews are served through an authenticated route with a restricted MIME allowlist and private caching.
- No live Facebook publishing or real external AI call is performed by approval; publication remains a separate later phase.
- No secrets are included in the phase changes.

## Known limitations
- Review content generation uses the project's configured AI/image providers; local tests use fakes and do not validate external provider credentials.
- Phase 08 ends at human approval. Facebook publishing is not included.

## Required user configuration
- No new configuration is required for Phase 08. Existing Phase 07 provider and database setup is used.

## Acceptance checklist
- [x] Complete pre-publication package is visible in one review workspace.
- [x] Draft, summary, rewrite, image, and fact-check actions create/audit the expected reviewable state.
- [x] Approval gating, reasoned exceptions, immutable version snapshot, and re-approval after edits are enforced.
- [x] Approval does not publish.
- [x] Tests pass.
- [x] No secrets committed.
- [x] No next phase work included.

## ACCEPTANCE GATE
PASS
