# Phase 12 — E2E and production readiness report

## Automated scenarios

The tests use Laravel HTTP fakes and fake AI/image/Facebook providers. They do not publish to Facebook or call paid external AI/image services.

| Scenario | Verification |
| --- | --- |
| A — Happy path | `NewsFlowEndToEndTest::test_single_article_runs_from_source_scan_through_approval_and_fake_facebook_publish`: real ThaiRath adapter against fake robots/listing/article responses; full workflow, image asset, admin approval, fake publication, publication state, and audit events. |
| B — Duplicate protection | Same E2E test repeats discovery and publication; asserts one article, one publication, second publish rejected, and no request to `graph.facebook.com`. |
| C — AI failure/retry | `AiTextPipelineTest::test_retrying_rewrite_does_not_create_another_post_version` and `WorkflowEngineTest::test_failure_is_sanitized_and_retry_resumes_at_failed_step_without_duplicates`. |
| D — Image failure/readiness | `ImagePipelineTest::test_failed_provider_fails_workflow_without_creating_asset` and `ReviewApprovalTest::test_no_image_approval_requires_explicit_choice_and_reason`. Failure and approval gates are tested; there is no separate provider-failure-then-retry test. |
| E — Unsupported claim | `AiTextPipelineTest::test_unsupported_claim_flags_article_and_prevents_ready_for_review`; `ReviewApprovalTest::test_fact_check_override_requires_and_records_reason` verifies normal approval is blocked and an explicit reason is recorded for override. |
| F — Publisher uncertainty | `ReviewApprovalTest::test_uncertain_provider_result_blocks_retry_to_avoid_duplicate_post`; the uncertain state is blocked from retry. Automated tests cannot prove delivery behavior of a live Meta account. |

## Verification results

- `php artisan test`: 122 passed, 599 assertions.
- `vendor\\bin\\pint --test`: passed.
- `npm run build`: passed.
- `composer audit --locked --no-interaction`: no security vulnerability advisories found.
- `git diff --check`: passed.
- `php artisan config:cache` and `php artisan config:clear`: passed.
- `php artisan schedule:list`: displayed three daily scans and the five-minute monitor.
- `php artisan newsflow:retention:prune-snapshots`: dry run passed; 0 eligible records, nothing deleted.
- `php artisan migrate:status`: all 8 migrations report Ran.

## Additional checks
- Migrations define indexes for article status/discovery time, source/publication time, snapshots by article/fetch time, workflow/article/status, asset status/time, review versions, publications, audit logs, and queue tables.
- `/health` checks application reachability and MySQL; it does not assert that queue workers or third-party providers are healthy.
- Retention command is dry-run by default. Destructive pruning requires the explicit `--execute` option and produces audit records.
- Scheduler/queue, private storage, migration, backup/restore, HTTPS, debug, and operator procedures are described in `DEPLOYMENT.md`, `OPERATIONS.md`, and `BACKUP_RESTORE.md`.

## Manual UI smoke test

1. In a private/staging environment, log in as an administrator and confirm the dashboard and `/health` are available.
2. Trigger a source scan, open a newly discovered article, and start its workflow. Confirm workflow steps finish and the review page shows facts, summary, draft, fact-check result, and image/manual-image status.
3. Review the actual source evidence and draft. Confirm edits force the required re-check/re-approval behavior.
4. Approve only a test item in the fake-provider environment. If publishing is enabled, confirm it is still configured with the fake provider. Verify one publication and audit trail; do not use a real Page during this smoke test.
5. Run a second source scan and confirm no duplicate article was added. Confirm the second publish attempt is rejected.
6. Inspect logs, queue status, and scheduler configuration. Do not enable auto-publish as part of this checklist.

## Integration status and known limitations

- Facebook/Meta live credentials, Page permissions, app review, current Graph API version, and actual live delivery: **not live verified**.
- External AI/image APIs, spend limits, provider quotas, and real-output editorial quality: **not live verified** by fake-provider tests.
- Penetration testing, production hosting/network egress policy, off-site backup, restore drill, external alerts, and production performance: deployment-owner tasks, not claimed as completed here.
- This phase adds production documentation and verification; it does not add a live integration, auto-publish, or social channels beyond Facebook.
