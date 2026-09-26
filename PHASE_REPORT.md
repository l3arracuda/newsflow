# Phase 05 — Admin Dashboard & Article Operations

## Acceptance gate: PASS

Phase 05 adds an authenticated operational dashboard, article search and detail pages, workflow-run inspection, and admin-only retry controls. It does not start Phase 06 or publish content to an external platform.

## Delivered

- Dashboard metrics, recent/failed workflows, source scan status, and publication counts.
- Article list filters for source, status, date range, and keyword, with pagination.
- Article detail view for source, snapshots, workflow history, generated drafts/assets, and selected audit details.
- Workflow timeline with sanitized error messages and retry of eligible latest failed attempts.
- `last_scanned_at` tracking for real source scans; dry runs do not update it.
- Thai responsive navigation and operational views.

Publishing/provider integration remains out of scope. Generated content is presented as a draft/placeholder and must not be treated as published. Existing sources show no scan time until a non-dry-run discovery executes.

## Verification

- `php artisan test --filter=AdminDashboardTest` — PASS, 7 tests / 60 assertions.
- `php artisan test --filter=SourceFetchingTest` — PASS, 12 tests / 32 assertions.
- `php artisan test` — PASS, 60 tests / 248 assertions.
- `vendor\\bin\\pint --test app database tests routes` — PASS.
- `npm run build` — PASS.
- Live XAMPP database migration was not run as part of this checkpoint.

## Manual verification on the local XAMPP setup

1. On branch `dev/phase-05-admin-dashboard`, run `php artisan migrate` to add `sources.last_scanned_at`.
2. Optionally run `php artisan news:discover thairath_society` for a real source scan. A dry run will not record the scan time.
3. Sign in with an admin account and open `/dashboard`. Check the metrics, workflow lists, and source scan status.
4. Open `/articles`; try the source, status, date, and keyword filters and confirm pagination retains them.
5. Open an article and inspect snapshots, workflow history, draft/assets, and audit details. Confirm placeholder output is clearly not presented as published.
6. Open a workflow from the article or dashboard and inspect its step timeline. For an eligible failed workflow, verify retry is available to an admin only and asks for confirmation.

## Checkpoint

- Branch: `dev/phase-05-admin-dashboard`
- Commit: recorded after final verification.
- Merge/tag: intentionally not performed; awaiting user review.
- Next phase: not started.
