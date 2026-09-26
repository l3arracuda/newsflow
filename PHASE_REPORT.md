# PHASE_REPORT

## Phase
Phase 04 — Workflow Engine, Queue, Retry & Logs

## Status
PASS

## Summary
- Added a queued, nine-step article workflow: discover, fetch_detail, extract_facts, summarize, rewrite, fact_check, image_prompt, image_generate, awaiting_review.
- Added processor contract/registry. Detail fetch reuses an existing snapshot; AI/facts/image stages are explicit placeholders only.
- Added idempotent placeholder GeneratedPost creation for rewrite; retries do not create duplicate versions.
- Added database row lock plus cache lock to prevent duplicate workflow starts, and queue `WithoutOverlapping` middleware to prevent two jobs processing the same article concurrently.
- Each step stores status, attempt, timestamps, metadata, and sanitized failure summary. Successful steps are skipped on resume; a failed step is retried as a new attempt, then subsequent steps continue.
- Added start/retry/status Artisan commands and workflow audit events for start, running, failure, retry, completion, and awaiting-review transition.
- Queue uses Laravel's configured connection (`QUEUE_CONNECTION`), which can be `sync`, `database`, or `redis` where configured. No real AI, image provider, or publisher integration was added.

## Files changed
- `app/Workflows/WorkflowStepProcessor.php`, `WorkflowProcessorRegistry.php`, `WorkflowStepCatalog.php`, `WorkflowEngine.php`, `WorkflowStarter.php`, `WorkflowRetrier.php`, `WorkflowInspector.php`
- `app/Workflows/Processors/DiscoveredArticleProcessor.php`, `FetchDetailProcessor.php`, `PlaceholderProcessor.php`
- `app/Jobs/ProcessWorkflowJob.php`
- `app/Console/Commands/StartArticleWorkflow.php`, `RetryArticleWorkflow.php`, `ShowWorkflowStatus.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Feature/WorkflowEngineTest.php`
- This report.

## Database migrations
- No schema changes or new migrations; Phase 02 workflow, step, generated-post, article, and audit tables were reused.
- Existing unique key `generated_posts(article_id, version)` protects placeholder draft idempotency.

## Commands run
```text
git switch -c dev/phase-04-workflow-engine
vendor\bin\pint app\Workflows app\Jobs app\Console\Commands app\Providers\AppServiceProvider.php tests\Feature\WorkflowEngineTest.php
vendor\bin\pint --test app\Workflows app\Jobs app\Console\Commands app\Providers\AppServiceProvider.php tests\Feature\WorkflowEngineTest.php
php artisan test --filter=WorkflowEngineTest
php artisan test
```

## Tests
### Targeted
```text
4 passed (35 assertions)
```

### Full suite
```text
53 passed (186 assertions)
```

## Manual verification
Use the article ID from the record already fetched in Phase 03. Starting this workflow writes workflow/audit rows and creates a clearly marked placeholder draft; it does not publish anything.

1. From `D:\CodeX\NewsFlow`, if `QUEUE_CONNECTION` is `database` or `redis`, open a terminal and start the worker: `php artisan queue:work --tries=3 --timeout=60`. Leave it running. If the queue connection is `sync`, no worker is needed.
2. In another terminal, start the workflow: `php artisan news:workflow:start <ARTICLE_ID>`. Note the workflow run ID printed.
3. Inspect progress: `php artisan news:workflow:status <RUN_ID>`. Expected end state is run `succeeded`, all nine steps `succeeded`, and the article status `ready_for_review`.
4. Inspect `generated_posts` in phpMyAdmin. The generated record is a test placeholder, with `metadata.placeholder = true` and `status = draft`; it is not publishable content.
5. Failure/retry behavior is covered by automated failure-injection tests. For a failed run, retry its first failed step with `php artisan news:workflow:retry <RUN_ID>`, or select it explicitly with `php artisan news:workflow:retry <RUN_ID> --step=summarize`, then inspect again with the status command.

## Security / data notes
- Errors stored in run/step logs redact common bearer, token, password, secret, and API-key patterns; only sanitized summaries are shown in workflow status.
- Workflow audit records contain state changes and IDs, not provider credentials.
- Publishing is not part of this phase; the placeholder post remains in `draft` state.

## Known limitations
- `extract_facts`, `summarize`, `rewrite`, `fact_check`, `image_prompt`, and `image_generate` are placeholders, not AI or image processing.
- The workflow can reach the awaiting-review state, but review UI/approval actions are later-phase work.
- Redis is optional; it requires a running Redis service and corresponding Laravel configuration. Database queue is supported by the existing project configuration.

## Required user configuration
- For asynchronous runs, configure `QUEUE_CONNECTION=database` (default project option) or `redis`, and run a queue worker.
- No migration or new secrets are required.

## Acceptance checklist
- [x] Nine required workflow steps reach awaiting review on happy path.
- [x] Queue job, queue driver selection, article lock, and overlapping-job protection are present.
- [x] Step timestamps, statuses, attempts, sanitized errors, and audit transitions are recorded.
- [x] Failure injection and retry resume only the failed and subsequent steps without duplicate generated records.
- [x] Start/retry/status commands are available; full suite and Pint pass.
- [x] No real AI/image/publishing or Phase 05 work included; no secrets committed.

## ACCEPTANCE GATE
PASS
