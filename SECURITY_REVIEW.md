# NewsFlow Security Review

Reviewed for Phase 11. This is an application-level review, not a penetration test or a legal opinion.

## Threat summary and mitigations

| Area | Mitigation in the current application |
| --- | --- |
| Authentication and authorization | Public registration is disabled; review, publication, article, workflow, and dashboard routes require authentication and admin authorization. Admin routes have a per-user rate limit. |
| CSRF and sessions | Web routes use Laravel's CSRF middleware. Session cookies are HTTP-only and SameSite=Lax. Production environment checks require HTTPS, secure cookies, and encrypted sessions. |
| XSS | Blade's escaped `{{ }}` output is used for source titles, excerpts, drafts, and provider data; no raw Blade output was found in application views. Regression test covers an HTML payload in a source title. |
| SSRF and source redirects | The current source adapter and HTTP client allow only HTTPS ThaiRath hostnames on port 443, reject URL credentials, do not follow redirects, require an approved text content type, enforce request-rate limits, and read response streams only up to the configured byte cap. robots.txt is checked before source fetch. |
| Source retention and copyright | Fetched article text is capped at 8,000 characters by default and can be tuned up to 20,000. Generated rewrites retain source attribution and URL. Source images are not fetched for republishing. Old snapshots use a configurable retention period; pruning is dry-run unless `--execute` is explicitly provided, and each deletion is audited. |
| Upload and asset storage | Manual image import verifies file signature/MIME against extension, limits bytes, and copies accepted files into private local storage. Asset previews require admin authorization and use a MIME allowlist. |
| Prompt injection and AI output | Seeded system prompts say source content is untrusted; the provider wraps task instructions separately from source data; structured JSON output is validated. A malicious-source fixture tests that the payload remains data. These controls reduce risk but cannot guarantee model behavior. |
| Secrets and logs | Provider credentials are read from environment-backed config. Access tokens are not stored in publication metadata or URL. Errors exposed to the UI are sanitized; source-fetch logs retain category/class rather than response content. `.env` remains local and is not part of the phase changes. |
| Publishing safety | Publishing is a separate admin action on an approved content/version snapshot. `AUTO_PUBLISH=false` is the default; `PUBLISHING_ENABLED=false` is an emergency kill switch. Uncertain outcomes cannot be retried until a human checks the Page. Source attribution and URL are required. |
| Queue payloads | Workflow jobs carry a numeric run ID rather than serialized Eloquent models. Queue backend/database access must remain restricted to trusted operators and application workers. |

## Security-focused verification

- Private/non-allowlisted URL, HTTP URL, URL credentials, and nonstandard port rejected before network access.
- Redirect, oversized body, unsupported content type, and source request-rate tests pass.
- Prompt-injection boundary, escaped source title, production debug/session checks, kill switch, and manual snapshot retention behavior have regression tests.
- Composer audit: no security vulnerability advisories found.
- Full test suite: 121 passed (573 assertions); Pint and production asset build passed.

## Remaining risks

- No external penetration test has been performed. Production deployment should add outbound firewall rules denying private, loopback, link-local, and metadata-service ranges even for DNS resolutions of the allowlisted source host.
- Prompt instructions and structured-output validation are defense in depth; a model can still make qualitative mistakes. Human approval and evidence review remain required.
- Meta app permissions, access-token lifetime/rotation, current Graph API behavior, and production publishing were not live-verified. Keep publishing disabled until the Page/app has been tested by an operator.
- Database backups and access controls are deployment responsibilities and are documented in the production readiness phase.

## Production checklist

- [ ] Run `php artisan newsflow:check-environment` after production config is loaded.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, a private `APP_KEY`, HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, and `SESSION_ENCRYPT=true`.
- [ ] Keep `.env` out of Git and restrict filesystem/backup access to it.
- [ ] Configure outbound network policy, TLS certificates, database/cache/queue credentials, and least-privilege service accounts.
- [ ] Configure only the source hosts the business has approved; keep robots and site terms under review.
- [ ] Set and test source/snapshot limits. Preview snapshot pruning with `php artisan newsflow:retention:prune-snapshots`; require explicit `--execute` for deletion.
- [ ] Keep `AUTO_PUBLISH=false`; use `PUBLISHING_ENABLED=false` to stop all publishing immediately.
- [ ] Review Meta app permissions and run a manual Page test before setting `PUBLISH_DRIVER=meta`.
- [ ] Keep scheduler, queue, alert log monitoring, backups, and restore drills operational.
