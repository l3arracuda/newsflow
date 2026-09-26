# PHASE_REPORT

## Phase
Phase 03 — Source Adapter & Article Discovery

## Status
PASS

## Summary
- Added a pluggable `NewsSourceAdapter` contract and registry keyed by `sources.adapter`.
- Implemented the ThaiRath adapter using the listing URL stored in the source record.
- Added fixture-driven listing/detail HTML parsing, canonical URL normalization, duplicate link removal, source host validation, title/date/external-ID extraction, and bounded article text extraction.
- Added `news:discover {sourceKey} {--dry-run}` and `news:fetch {articleId}` commands.
- Added idempotent article discovery protected by existing database unique constraints, and detail snapshots keyed by SHA-256 checksum.
- Added HTTP timeouts, an identified user agent, one-request-per-second source throttling, bounded retries for timeouts/5xx, and classified failures for 429/4xx/5xx.
- Added cached robots.txt policy enforcement; disallowed paths stop before the page request. No access controls are bypassed.
- No AI, image generation, publishing, UI, or migration changes were included.

## Files changed
- `app/News/Adapters/NewsSourceAdapter.php`, `SourceAdapterRegistry.php`
- `app/News/Adapters/ThaiRath/ThaiRathAdapter.php`, `ThaiRathHtmlParser.php`, `RobotsPolicy.php`
- `app/News/DTO/ArticleCandidate.php`, `ArticleDocument.php`
- `app/News/Exceptions/SourceFetchException.php`
- `app/News/Http/SourceHttpClient.php`
- `app/News/Services/ArticleDiscoveryService.php`, `ArticleFetchService.php`
- `app/Console/Commands/DiscoverNews.php`, `FetchNewsArticle.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Feature/SourceFetchingTest.php`
- `tests/Fixtures/source-fetching/listing.html`, `article.html`
- This report.

## Database migrations
- No schema changes or new migrations.
- Discovery writes to the Phase 02 `articles` table and uses its unique source URL/external ID constraints.
- Detail fetch writes a bounded normalized excerpt to `article_snapshots`; checksum uniqueness makes repeat fetches idempotent.

## Commands run
```text
git switch -c dev/phase-03-source-fetching
vendor\bin\pint app\News app\Console\Commands app\Providers\AppServiceProvider.php tests\Feature\SourceFetchingTest.php
vendor\bin\pint --test app\News app\Console\Commands app\Providers\AppServiceProvider.php tests\Feature\SourceFetchingTest.php
php artisan test --filter=SourceFetchingTest
php artisan test
```

## Tests
### Targeted
```text
12 passed (30 assertions)
```

### Full suite
```text
49 passed (151 assertions)
```

### Formatting
```text
Pint --test passed
```

## Manual verification
Live verification is optional and was not run during this implementation. It makes requests to ThaiRath and can fail transparently if the site is unavailable or policy blocks access.

1. Ensure MySQL is running and the Phase 02 migrations/source seed are present.
2. Preview candidates without writing article records: `php artisan news:discover thairath_society --dry-run`.
3. If the preview is acceptable, discover idempotently: `php artisan news:discover thairath_society`.
4. Obtain an internal article ID with `SELECT id, title, source_url, status FROM articles ORDER BY id DESC LIMIT 5;` in phpMyAdmin.
5. Fetch one article detail and create its snapshot: `php artisan news:fetch <ARTICLE_ID>`.
6. Verify the article status is `fetched`, its `content_hash` is set, and `article_snapshots` contains the matching checksum. Repeating the fetch should not duplicate a snapshot with the same checksum.

## Security / data notes
- Only HTTPS requests to the exact `thairath.co.th` hostnames are permitted by the adapter.
- Robots policy is checked before listing/detail requests, cached for one hour, and disallowed paths are rejected.
- CAPTCHA, paywall, authentication, anti-bot, and access controls are not bypassed; HTTP/policy/parse failures are surfaced.
- The parser stores only normalized article paragraphs (up to 20,000 characters), not page markup, navigation, or full-site copies.
- No credentials or secrets were added.

## Known limitations
- ThaiRath HTML may change; parser uses standard anchors, metadata, article/main headings, and paragraph fallbacks, so unsupported markup fails with a controlled parse error.
- The optional live test can return an upstream error or be disallowed; automated tests use saved fixtures and HTTP fakes only.
- `news:fetch` fetches one article per invocation; orchestration and scheduled scanning belong to later phases.

## Required user configuration
- No new keys or migrations are required. Existing ThaiRath Society source configuration must be seeded and active.
- The configured cache store should be available for shared throttling and robots policy caching in multi-process deployments.

## Acceptance checklist
- [x] Adapter contract and registry select by stored adapter key.
- [x] Fixture listing parsing, URL normalization, filtering, duplicate handling, and metadata extraction.
- [x] Detail parsing, bounded normalized text, checksum snapshot, and idempotent fetch.
- [x] Discovery dry-run does not mutate database; repeated discovery does not duplicate records.
- [x] Timeout and HTTP behavior has bounded retries/classified errors; throttling and robots policy are enforced.
- [x] Targeted/full tests and Pint pass; no secrets committed; no Phase 04 functionality included.

## ACCEPTANCE GATE
PASS
