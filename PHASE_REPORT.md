# Phase 07 — Image Prompt & Generated Asset Pipeline

## Acceptance gate: PASS

Phase 07 replaces the workflow's image prompt and image generation placeholders with an editorial prompt builder, provider abstraction, private storage adapter, traceable asset records, and a regenerate command. The default provider is a small fake PNG fixture; no live image API was called.

## Delivered

- `ImagePromptBuilder`, `ImageGenerationProvider`, and `GeneratedAssetStorage` contracts.
- Fact-based editorial prompt generation with category/sensitivity metadata and conservative non-graphic guidance for sensitive stories.
- Fake provider for tests and configurable OpenAI GPT image adapter returning base64 image data; model, timeout, retry, output size, and storage disk are configuration-driven.
- Generated asset schema includes workflow association, provider ID, file dimensions, content hash, prompt text/version, and status.
- File type/dimensions/size validation before private storage; metadata identifies generated illustrations and records that no source image reference was used.
- Workflow retry is idempotent by workflow run; regeneration creates a higher asset version without deleting old files.
- `news:image:regenerate {generatedPostId}` command and admin article view details for assets.
- Audit event on generated asset creation.

The OpenAI adapter is limited to GPT image models, which return base64 data by default; no remote source image URL is downloaded or passed as an image reference.

## Verification

- `php artisan test --filter=ImagePipelineTest` — PASS, 6 tests / 30 assertions.
- `php artisan test` — PASS, 72 tests / 301 assertions.
- `vendor\\bin\\pint --test app database tests routes config` — PASS.
- `npm run build` — PASS (59 modules).
- OpenAI adapter test uses a mocked HTTP response only; no external API call or billing occurred.
- Live XAMPP database migration was not run by this task.

## Manual test on XAMPP

1. Switch to `dev/phase-07-image-pipeline`, then run `php artisan migrate` and `php artisan db:seed`.
2. Leave `IMAGE_GENERATION_DRIVER=fake` in `.env` for a no-cost test. The fake provider produces a tiny fixture image, not a usable editorial illustration.
3. Create a fresh workflow using an article with a snapshot and extracted facts. Previously completed Phase 04 workflows will not rerun automatically; discover/fetch a new article or start an eligible new workflow.
4. Open the workflow detail and verify `image_prompt` and `image_generate` succeeded. Open the article detail and check the generated-illustration marker, provider, dimensions, prompt version, checksum, and storage path.
5. Run `php artisan news:image:regenerate <generatedPostId>` twice. Each run should create the next asset version while earlier versions remain present. The generated post ID is visible in the article detail page.
6. Only when intentionally ready to use a paid provider, set `IMAGE_GENERATION_DRIVER=openai`, set `IMAGE_GENERATION_API_KEY`, select an accessible `IMAGE_GENERATION_MODEL` beginning with `gpt-image-`, clear cached config, and restart queue workers. This can incur API charges.

## Checkpoint

- Branch: `dev/phase-07-image-pipeline`
- Commit: recorded after final verification.
- Merge/tag: not performed; awaiting user review.
- Phase 08: not started.
