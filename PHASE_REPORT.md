# Phase 06 — AI Text Pipeline

## Acceptance gate: PASS

The workflow's fact extraction, summary, rewrite, and fact-check steps now use provider contracts and validated structured output. The default driver is a deterministic fake provider; OpenAI is available through environment configuration. This phase does not include the live provider call, image pipeline, review UI, or publishing.

## Delivered

- Provider and role contracts: `AiTextProvider`, `FactExtractor`, `ArticleSummarizer`, `SocialPostRewriter`, and `FactConsistencyChecker`.
- Fake provider for repeatable local/testing use and an OpenAI Chat Completions adapter using strict JSON Schema output, configurable model, timeout, retries, and API parameters.
- Prompt-template columns for system/instruction text, parameters, active version, and `updated_by`, while retaining legacy fields.
- Seeded version-1 prompt templates: `FACT_EXTRACT`, `NEWS_SUMMARY`, `FACEBOOK_REWRITE`, and `FACT_CHECK`.
- Facts, summary, rewrite, and structured consistency results with prompt id/version, model, usage, and check timestamp provenance.
- Draft posts link uniquely to a workflow run so retry does not duplicate draft versions.
- Failed consistency checks set the article status to `flagged` and do not allow `ready_for_review`.
- Source text is sent as untrusted evidence, separate from trusted prompt instructions; no tools are provided to the model.

## Verification

- `php artisan test --filter=AiTextPipelineTest` — PASS, 6 tests.
- `php artisan test` — PASS, 66 tests / 271 assertions.
- `vendor\\bin\\pint --test app database tests routes config` — PASS.
- `npm run build` — PASS (59 modules).
- Provider tests use a mocked HTTP response; no live AI API call or billing occurred.
- Production/local XAMPP database migration was not run by this task.

## Manual test on the local setup

1. On branch `dev/phase-06-ai-text-pipeline`, run `php artisan migrate` and `php artisan db:seed`.
2. Keep `AI_TEXT_DRIVER=fake` in `.env` for a no-cost local run. Start the app/queue the same way as the prior phase.
3. Start processing a fetched article with a snapshot using the existing workflow command or UI. Open its workflow details and verify `extract_facts`, `summarize`, `rewrite`, and `fact_check` all succeed; inspect the prompt/model provenance and structured output under each step.
4. Open the article details and verify one draft exists, its body includes source attribution and URL, and its metadata is not marked placeholder.
5. To test the blocked-review path without a real provider, automated coverage injects a fake fact-check response with unsupported claims; expected outcome is article status `flagged`, workflow complete, and no `article.ready_for_review` audit event.
6. To enable real provider calls deliberately, set `AI_TEXT_DRIVER=openai`, fill `AI_TEXT_API_KEY`, choose `AI_TEXT_MODEL`, and clear cached configuration/restart queue workers. This can incur API charges. Do not put the key in Git.

## Checkpoint

- Branch: `dev/phase-06-ai-text-pipeline`
- Commit: recorded after final verification.
- Merge/tag: not performed; awaiting review.
- Phase 07: not started.
