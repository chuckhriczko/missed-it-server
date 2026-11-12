# Operations

## Activate scheduled maintenance

As an administrator, after deploying the application:

```bash
sudo bash /var/www/sites/missed-it/server/deploy/install-maintenance.sh
```

The installer validates log rotation configuration and installs an app-specific
cron file and logrotate file. It enables the existing `cron` service. It does not
replace other sites' crontabs or configuration. This workspace cannot write to
`/etc` or manage system services, so preparing the files alone does not activate
the schedule. Install this once; future application deployments update Laravel's
schedule through the repository.

Laravel's scheduler runs once a minute as `chuck`. It creates a backup at 02:15 UTC
and prunes tokens expired for over 24 hours at 02:45 UTC. Both tasks prevent
overlap; backup also takes the same file lock as deployment. Check task times
with `php artisan schedule:list` and inspect `storage/logs/scheduler.log` for
failures. A host scheduler is required; this app has no background worker.

## Backups and restore checks

Run manually as `chuck`:

```bash
php artisan garden:backup
php artisan garden:backup-verify /absolute/path/to/backup/database.sqlite
```

Both deployment and scheduled backups use SQLite's online backup API, preserving
committed WAL data. Every snapshot is restored to an independent private
temporary database and checked for schema presence, SQLite integrity, and foreign
key violations. Temporary restore files are deleted; live data is never replaced
by a verification. Successful backups are named `database.sqlite`; `.partial`
files indicate incomplete backup attempts.

Backups live in `storage/app/private/deploy-backups/<UTC timestamp>-<unique ID>/`
with private directory/file permissions. After each successful scheduled backup,
completed backups older than `BACKUP_KEEP_DAYS` (default 30) are removed. This
includes deployment snapshots. Incomplete backups and directories with unrelated
contents are preserved for operator review. No pruning runs after a failed backup.
Deployment itself never prunes or restores snapshots.

Off-server copying is intentionally unconfigured. These snapshots protect
against application mistakes, but share the server's failure domain. Keep `.env`
and its original `APP_KEY` securely alongside your independent recovery materials.
Do not put credentials or backups in Git or the public directory.

For an actual recovery, stop all writers (web requests, scheduled tasks, and any
manual scripts), verify the selected backup, preserve the current database and
its WAL/SHM files, restore the selected snapshot without leaving stale WAL/SHM
files beside it, and restore compatible application code. Reopen the site only
after checking schema, permissions, and `/up`. Restoring a database loses writes
made after the snapshot. Rollback is an explicit operator operation.

## Health and logs

`GET /up` verifies that Laravel boots and can query the puzzle, account, and token
tables, plus database-backed sessions/cache when configured. It returns 500 with
a generic error when the database or required schema is unavailable. It does not
write a probe record, certify disk capacity, or contact Logalyzer. JSON clients
can send `Accept: application/json` to receive `{"status":"up"}` or
`{"status":"down"}`. External uptime monitoring and alert destinations are
intentionally unconfigured.

The normal log stack is `daily,logalyzer`. Daily local files retain 14 files by
default (`LOG_DAILY_DAYS`), use private file permissions, and record info and above
(`LOG_LEVEL`). Host logrotate handles scheduler output and any legacy single
`laravel.log` file. Daily rotation bounds file count rather than total disk use;
check free disk space as part of host operations.

The bundled SDK, available through `App\Logging\LogalyzerDriver`, originates from the other site's `web/logalyzer-sdk`; see its
`packages/logalyzer-sdk/PROVENANCE.md`. It is installed through a local Composer
path repository, mirrored rather than symlinked. Changes to the other site's
copy do not silently change this app.

Configure `LOGALYZER_URL`, `LOGALYZER_TOKEN`, and optionally
`LOGALYZER_PROJECT_ID` in `.env`. The active channel uses `App\Logging\CreateLogalyzerLogger`, with the same
connection settings as game error reports. The log group defaults to
**Missed It · Puzzle Garden server** (`LOGALYZER_LOG_NAME`);
this is a distinct log group, not a newly provisioned project. Info and above (`LOGALYZER_LEVEL`)
are buffered and flushed at Laravel termination or process shutdown. Transport
failures are contained and leave the local daily record intact; delivery is
best-effort, with a one-second timeout and no retries by default. No credentials
means the extra channel uses a null handler. Keep remote credentials private and
avoid logging request payloads, passwords, bearer tokens, or puzzle documents.

After changing environment settings, run `php artisan config:cache` as `chuck`.
The app's Logalyzer connection may reuse the existing host connection and project,
while using its own group; use a dedicated token/project if separation is desired.
Off-server backup and uptime-monitor configuration are independent of Logalyzer.

## SQLite and CI

SQLite connections default to WAL with a 5000 ms busy timeout. These settings can
be changed with `DB_JOURNAL_MODE` and `DB_BUSY_TIMEOUT`. WAL still allows only one
writer at a time. Use SQLite's backup API instead of copying an active database
file. Database and parent-directory write access is required for WAL/SHM files.

GitHub Actions installs the locked dependencies with SQLite enabled, runs tests,
checks Laravel cache compilation and shell syntax, and audits dependencies. Tests
must run in a separate checkout: `composer test` clears configuration/routes, and
production dependencies intentionally omit PHPUnit. Dependency advisory auditing
requires network access; an offline install is not an advisory audit.
