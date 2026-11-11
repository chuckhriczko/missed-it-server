# Verification — 11 November 2026

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
