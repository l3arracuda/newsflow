# PHASE_REPORT

## Phase
Phase 02 — Database & Domain Model

## Status
PASS

## Summary
- Added the core NewsFlow schema for sources, articles, snapshots, workflow runs/steps, prompt templates, generated posts/assets, review decisions, publications, and audit logs.
- Added Eloquent models with typed relations, JSON/date casts, and PHP backed enums for finite statuses.
- Added DB constraints for source keys, source URL/source ID duplicate prevention, versioned records, workflow attempts, and publication idempotency.
- Added idempotent ThaiRath Society source seeding.
- Added tests for migrations, relationships, enum casts, unique constraints, and seeder idempotency.
- No fetching, AI, publishing, or large UI behavior was implemented.

## Files changed
- `app/Enums/` — article, workflow, generated post, review, and publication statuses.
- `app/Models/` — domain models and relations; added review/audit relations to User.
- `database/migrations/2026_09_26_000002_create_newsflow_domain_tables.php` — domain schema and indexes.
- `database/seeders/DatabaseSeeder.php` and `SourceSeeder.php` — safe initial source seed.
- `tests/Feature/DatabaseDomainTest.php` — phase acceptance coverage.
- This report.

## Database migrations
- `2026_09_26_000002_create_newsflow_domain_tables.php` creates all eleven required domain tables.
- Applied successfully to the local XAMPP MySQL-compatible database.
- `db:seed` ran twice; exactly one `thairath_society` source remained.

## Commands run
```text
git switch -c dev/phase-02-database-domain
vendor\bin\pint app/Enums app/Models database/migrations database/seeders tests/Feature/DatabaseDomainTest.php
php artisan test --filter=DatabaseDomainTest
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --force
vendor\bin\pint app/Enums app/Models database/migrations database/seeders tests
php artisan test
```

## Tests
### Targeted
```text
php artisan test --filter=DatabaseDomainTest
6 passed (36 assertions)
```

### Full suite
```text
php artisan test
37 passed (121 assertions)
```

## Manual verification
1. Run `php artisan migrate` and confirm the domain migration completes.
2. Run `php artisan db:seed` twice and confirm only one source with key `thairath_society` exists.
3. In Tinker, create a Source and Article, then verify `Article::source`, snapshots, workflow runs, and generated-post relations.
4. Verify duplicate `source_id + source_url` inserts are rejected by the database.
5. Confirm the dashboard and login still work; Phase 02 adds no user-facing workflow screens.

## Security / data notes
- Source configuration is JSON and the seeded config is empty; no credentials are stored in it.
- Snapshots retain a normalized excerpt and checksum for audit, not a full-site copy.
- Article source URLs are limited to 700 characters to keep the composite utf8mb4 unique index within MySQL index limits.
- Publication idempotency keys are unique; audit actors reference users when applicable and system events may have no user actor.

## Known limitations
- Status values are enforced by PHP enum casts; no database-specific CHECK constraint was added, preserving portability between MySQL and SQLite tests.
- Snapshot excerpt length is bounded by the database `TEXT` type; fetching and normalization limits belong to Phase 03.
- No HTTP fetching, AI processing, publishing logic, or review UI is included.

## Required user configuration
- Run `php artisan migrate` and `php artisan db:seed` in each environment.
- Configure the source adapter and database credentials in the appropriate environment; source config must not contain secrets.

## Acceptance checklist
- [x] All required domain tables and relationships exist.
- [x] MySQL migration succeeds.
- [x] Source/article duplicate URL constraint is enforced.
- [x] Models cast finite statuses to PHP enums.
- [x] ThaiRath Society source seed is idempotent.
- [x] Targeted and full test suites pass; Pint passes.
- [x] No secrets committed and no Phase 03 functionality included.

## ACCEPTANCE GATE
PASS
