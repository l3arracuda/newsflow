# Backup and restore procedure

This is an operator procedure, not an automated backup system. Store encrypted copies off-host with access controls and a retention policy. Never put dumps, `.env`, API credentials, or tokens in the repository.

## Backup

1. Record the release/commit, database host/name, backup time, and the chosen recovery point. Confirm sufficient encrypted storage and that the destination is not web-accessible.
2. Use a dedicated MySQL backup identity. Supply its password through a protected client option file or secret manager, not a command-line argument. Example (replace placeholders and protect the output directory):

   ```sh
   mysqldump --defaults-extra-file=/secure/path/newsflow-backup.cnf \
     --single-transaction --routines --triggers --hex-blob \
     --databases newsflow > /secure/backup/newsflow-YYYYMMDD-HHMM.sql
   ```

   For large databases, compress and encrypt the stream using the organization's approved tools; verify the resulting file and retain the encryption key separately.
3. Back up private uploaded/generated assets under `storage/app` (including manual-news images and generated assets) while preserving relative paths and permissions. Prefer a filesystem snapshot or archive with a consistent point-in-time relative to the database backup. Do not publish this directory.
4. Back up deployment configuration through the secret manager's protected mechanism. Do not include secrets in ordinary source backups. Record required secret identifiers/rotation owners, not secret values.
5. Verify dump integrity, file size, encryption, access restrictions, and off-host replication. A successful command alone is not a tested backup.

## Restore drill

Perform drills in an isolated environment, never over the live database or live asset directory.

1. Provision a clean isolated MySQL database and private storage location with compatible MySQL/PHP versions.
2. Restore the dump using a protected credentials file:

   ```sh
   mysql --defaults-extra-file=/secure/path/newsflow-restore.cnf \
     --database newsflow_restore < /secure/backup/newsflow-YYYYMMDD-HHMM.sql
   ```

   If the dump contains a `CREATE DATABASE` statement, restore it according to your MySQL policy instead of supplying `--database`.
3. Restore private assets to the isolated app's `storage/app` tree. Check ownership, permissions, file counts, and representative asset hashes.
4. Configure the isolated app with the restored database, a valid protected app key and provider secrets appropriate for a test environment. Keep `APP_DEBUG=false` unless the isolated environment is fully private, and keep `PUBLISHING_ENABLED=false`, `AUTO_PUBLISH=false`, and external providers fake/manual.
5. Run `php artisan migrate:status`, `php artisan newsflow:check-environment`, and `/health`; verify representative articles, snapshots, review records, publication history, audit rows, and private image previews.
6. Run the automated suite and a non-publishing smoke test. Record restore duration, data-loss window, exceptions, and corrective actions. Do not connect the restored environment to a real Facebook Page.

## Recovery cautions

- Use a database dump and asset backup from compatible points in time; otherwise an asset record may refer to a missing file or vice versa.
- Keep backups encrypted and access-audited because source snapshots, drafts, user data, and audit records can contain sensitive information.
- Validate retention/legal requirements before expiry or deletion. Snapshot pruning is separate from backup retention.
- Define and test RPO/RTO with the service owner. No recovery objective is claimed until a timed restore drill has passed.
