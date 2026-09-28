# NewsFlow deployment guide

This guide describes a conservative Linux deployment for the Laravel 12 application. Adapt service names and paths to the selected hosting provider. Keep the first deployment private or behind access control until the operator has completed the live integration checks.

## Before deployment

- Provision a supported PHP 8.2+ runtime with the PHP extensions required by Laravel, Composer, MySQL, HTTPS termination, persistent private storage, and a queue worker manager (Supervisor/systemd or the platform equivalent).
- Create a dedicated MySQL database/user with only the privileges needed by this application. Use a shared Redis cache/lock backend if running more than one app or scheduler instance; database cache is suitable for a single-node start.
- Configure secrets in the host's secret manager or protected environment file, never in Git. Back up the database and private `storage/app` assets before every deployment.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a valid `APP_KEY`, and an HTTPS `APP_URL`. Also set `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.
- Keep `PUBLISH_DRIVER=fake`, `AUTO_PUBLISH=false`, and `PUBLISHING_ENABLED=false` until Meta credentials, permissions, Page selection, and a controlled live test have been verified. Live Facebook publishing is **not live verified** by the automated suite.
- Choose `AI_TEXT_DRIVER` and `IMAGE_GENERATION_DRIVER` deliberately. This project supports manual images; keep AI providers disabled/fake until credentials, spend limits, and editorial quality are verified.

## Release procedure

1. Deploy the reviewed release/branch to a new release directory; do not deploy a developer working tree.
2. Install PHP dependencies and build frontend assets:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci
   npm run build
   ```

3. Set the production environment securely, then validate the app configuration and MySQL reachability:

   ```sh
   php artisan newsflow:check-environment
   ```

4. Run migrations in maintenance mode during the first deployment or any migration that requires downtime:

   ```sh
   php artisan down
   php artisan migrate --force
   php artisan config:cache
   php artisan up
   ```

   Review each migration's `down()` method and the release notes before upgrading. Back up first. Prefer forward-compatible, expand/contract migrations; do not blindly roll back production migrations.

5. Set ownership/permissions so the web and worker users can write to `storage` and `bootstrap/cache`, but cannot modify application source. Keep `storage/app/private` private; do not expose it through a public symlink. Serve only `public/` through the web server. Enforce HTTPS and trusted-proxy configuration at the edge.
6. Restart long-running PHP and queue processes after each release/config change. Keep the prior release available for code rollback, while treating schema rollback as a separate reviewed operation.
7. Verify `php artisan migrate:status`, `php artisan schedule:list`, `php artisan newsflow:retention:prune-snapshots` (dry run), `https://<host>/health`, login, dashboard, source scan, workflow review, and logs. Configure external monitoring for non-200 health responses and worker/scheduler liveness.

## Queue and scheduler services

The default queue is the database queue. Run at least one supervised long-running worker:

```sh
php artisan queue:work database --sleep=3 --tries=3 --timeout=60
```

The database queue `retry_after` defaults to 90 seconds, longer than the example 60-second worker timeout. Keep that relationship if changing either value. Monitor failed jobs and use `php artisan queue:failed` / `php artisan queue:retry <id>` only after understanding the failed action and duplicate-side-effect risk.

Run Laravel's scheduler every minute from the host scheduler:

```cron
* * * * * cd /srv/newsflow/current && php artisan schedule:run >> /dev/null 2>&1
```

For multiple app nodes, use a shared cache backend that supports atomic locks, and ensure scheduler hosts share that backend. `onOneServer` and overlap protection depend on those shared locks.

## Safe initial operation

- Create the first administrator interactively with `php artisan newsflow:create-admin`; the password prompt is hidden. Do not pass passwords as command-line arguments.
- Keep publishing disabled until an operator explicitly approves and verifies the Meta setup. Never test against an unintended production Page.
- Snapshot cleanup is a dry run unless `--execute` is supplied. Inspect the count and retention policy before any destructive execution.
- For a local Windows/XAMPP development setup, keep its machine-specific service instructions separate from this Linux production guide.
