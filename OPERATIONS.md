# NewsFlow operations runbook

## Daily checks

- Open `/health`; expect HTTP 200 and JSON `status: ok`, including database `ok`. This checks application reachability and a MySQL query, not queue/provider health.
- Check the dashboard for source scan age/errors, stale workflows, pending/failed database jobs, awaiting-review items, and publication outcomes.
- Confirm the host scheduler ran `schedule:run` each minute and the queue worker is alive. `php artisan schedule:list` shows registered schedules; `php artisan queue:failed` lists terminal queue failures.
- Review application logs and the operational dashboard. The configured alert implementation writes to logs; no email/LINE/external alert channel is configured.
- Review publishing outcomes. Treat `uncertain` as a human investigation: check the Facebook Page and publication record before taking any action. Do not retry an uncertain publish automatically.

## Useful commands

```sh
php artisan newsflow:check-environment
php artisan schedule:list
php artisan news:scan
php artisan news:scan thairath_society
php artisan newsflow:operations:monitor
php artisan queue:failed
php artisan migrate:status
php artisan newsflow:retention:prune-snapshots
```

Scheduled scans run at the configured `NEWSFLOW_SCAN_TIMES` (default 08:00, 14:00, 20:00) in `NEWSFLOW_TIMEZONE` (default `Asia/Bangkok`). The operations monitor is scheduled every five minutes. Manual scans use the same source protections and audit trail.

## Incident handling

### Database or health check degraded

Check MySQL availability, network access, credentials from the host secret store, connection limits, and recent migration status. Do not expose detailed exception traces publicly. Restore service and verify `/health`, login, then a read-only dashboard request.

### Queue backlog or repeated workflow failure

Check worker process health, failed jobs, logs, provider reachability, timeouts, and database capacity. Correct the underlying issue first. Retry only the failed workflow step shown by the application, then verify the run's later steps and ensure no duplicate draft/image was created. Do not replay publishing jobs without checking publication state.

### Source fetching failure

Check the active source configuration, robots policy, source response status/type/size, and recent scan audit records. Do not disable host allowlisting, redirect protections, request limits, or size limits to force a fetch. Pause the source and investigate upstream changes if needed.

### Suspected credential exposure

Disable/rotate the affected key immediately in its provider, update the host secret store, restart workers, inspect access/audit logs, and verify the old key is revoked. Never copy credentials into tickets or logs.

### Publishing outcome is uncertain

Do not press publish again. Inspect the configured Page out-of-band, reconcile the post with the publication record, and preserve audit evidence. The application deliberately blocks automatic retry to reduce duplicate-post risk.

## Maintenance and change control

- Keep `AUTO_PUBLISH=false` and the publishing kill switch off unless the owner has explicitly enabled a verified production integration.
- Preview snapshot retention with `php artisan newsflow:retention:prune-snapshots`. The command deletes nothing without `--execute`; obtain an approved backup and policy confirmation before using it.
- Before a release: review changes, back up, run the full test suite in CI, deploy, migrate, rebuild config cache, restart workers, and verify health/dashboard/scheduler.
- For emergency rollback, switch application code to the previous release and restart processes. Assess database compatibility first; do not automatically run `migrate:rollback`.

## Recovery targets and limitations

Set explicit RPO/RTO with the hosting owner. The application does not itself provide off-site backup, external alert delivery, or a disaster-recovery service. Database queue/cache tables are operational state and may be reconstructed only with an understood impact; include them in backups if preserving in-flight work matters.
