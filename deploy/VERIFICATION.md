# Verification — 11 November 2026

## Operations update — 12 November 2026

- Added daily backup and expired-token schedules, host cron/logrotate templates
  and installer, database/schema checks on `/up`, private daily logs, SQLite WAL
  with a 5000 ms lock timeout, and GitHub Actions checks.
- Bundled the unchanged Logalyzer SDK from the other site's `web/logalyzer-sdk`
  as a mirrored Composer path package. Only this dependency was added to the lock
  file; existing package versions were preserved.
- Production `.env` now selects daily logs plus Logalyzer, reusing the host's
  existing Logalyzer connection/project with the distinct **Puzzle Garden —
  Server** group. The app key was preserved. Local daily log permissions are 0600.
- Isolated full suite: **36 tests, 275 assertions passed**, including health
  failure privacy, token cleanup, backup/WAL preservation and retention, remote
  logging failure containment, and concurrent SQLite readers/writers. The health
  tests were rerun successfully after refining configured connection handling.
- A real production snapshot was created and restored into a temporary database
  for integrity verification. Backup refusal while deployment holds the lock was
  checked separately in the isolated workspace.
- Production Laravel kernel `/up` check: **200**, JSON `status=up`. Production
  journal mode is `wal`. Config/event/route/view cache compilation, shell/PHP
  syntax, Composer validation, and logrotate debug validation passed.
- Direct Logalyzer smoke delivery could not be verified: cURL errno **6** (DNS
  resolution failure) in this execution environment. Mocked delivery verifies
  grouping, severity filtering, and local log preservation during transport
  failure. No live delivery success is claimed.
- Host cron/logrotate activation still requires the administrator installer;
  no `/etc` files or service state were changed by this session. Off-server
  backups and external uptime alerts remain intentionally unconfigured.
- Dependency advisories could not be refreshed with network access disabled.
  Online auditing is included in CI; the offline install is not an audit.

## Original application verification

- Laravel 13.25.0 installed using the existing Composer cache; `composer.lock` pins installed versions. Direct Packagist access was unavailable.
- `composer validate --no-check-publish`: passed.
- `composer test`: 9 tests, 79 assertions passed, using in-memory SQLite.
- `php artisan migrate --force`: applied the schema to the workspace SQLite database.
- `php artisan view:cache` and `php artisan route:list --except-vendor`: passed.
- Optional `garden:examples` ran twice against a separate `/tmp` SQLite database and produced exactly three example records; the workspace database remains empty.
- `node --check public/garden.js` and `bash -n deploy/install-vhost.sh`: passed.
- Nginx `-t`, using a standalone configuration with the supplied vhost and temporary log paths, reported **syntax is ok**, then failed its socket check because socket creation is forbidden in this sandbox. This is not a completed Nginx runtime test.
- PHP-FPM `-t` could not read its temporary test configuration under this environment's restrictions. The installation script runs the real service configuration tests before reload.
- Firefox could not launch because its Snap runtime directories are outside writable roots. No visual browser QA was completed.
- No system vhost, PHP-FPM pool, DNS, hosts entry, TLS certificate, or system service was changed. The session permits project and `/tmp` writes only.
- No C# game code was modified. Actual Godot import/playtesting remains a manual integration check; the website does not verify physical solutions.

## Follow-up: 502 and HTTPS preparation

Nginx's site error log reports `connect() to unix:/run/php/missed-it.sock failed (2: No such file or directory)`. The socket is absent. The existing AppArmor PHP-FPM profile and local overrides omit both this socket and the app root, while explicitly allowing the other hosted sites. Added scoped rules and installer checks for the upstream. Prepared `install-ssl.sh` for Certbot, HTTPS redirects, secure cookies, application URL configuration, HTTPS checks and the renewal timer.

- Both deployment scripts pass `bash -n`.
- The existing AppArmor profile was preprocessed, combined with the new rules, and successfully parsed with `--skip-kernel-load --skip-cache`; no live security policy was changed.
- System installation, FPM reload, certificate issuance, HTTPS validation and renewal checks still require running the installer as an administrator outside the sandbox. No certificate has been issued by this session.

The first administrator run passed AppArmor loading, FPM/Nginx config validation, migrations, and Laravel optimization, but reported that `php8.5-fpm.service` was inactive. The installer now starts inactive FPM (clearing prior startup failure limits), reloads active FPM, verifies service state as well as socket presence, and prints service/log diagnostics if either action fails. Certificate issuance was not reached in that run.

## Game API

Added Laravel Sanctum 4.3.3 and an API token migration; installed dependencies from the local cache. Added versioned upload, browse, detail, download, personal collection, delete, token-login and token-revocation endpoints plus a browser token management page. Shared the publisher, upload request and filter query with the website.

- Full suite: 21 tests and 204 assertions passed. Covers token authentication, expiry, hashing, abilities, revocation, cookie rejection, rate limits, validation, file uploads, exact download preservation, idempotent retries, ownership, pagination/filtering and the original website flows.
- `composer validate --no-check-publish`, Blade compilation, route caching and deployment shell syntax checks passed.
- Applied the additive `personal_access_tokens` migration to the workspace database.
- Production Laravel kernel checks: `GET /api/v1/puzzles` returns 200, unauthenticated `POST /api/v1/puzzles` returns 401, and `/account/tokens` redirects guests to login. These checks bypass Nginx; live browser/HTTP verification remains constrained by the session's network restrictions.
- Integration contract and C# upload example: `docs/API.md`. The Godot game source is unchanged.
