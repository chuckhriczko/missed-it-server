# The Puzzle Garden

A Laravel 13 community puzzle library for **Pip and the Last Sunseed / Missed It By That Much**. The Godot game stays in `../game`; this app shares the JSON exported by its current Puzzle Studio (`game/game/scripts/WorkshopDocument.cs`).

## Included

- Public browsing, creator/title/objective search, difficulty and mechanic filters, newest/oldest/most-downloaded sorting, pagination.
- Registration, sign-in, remembered sessions, a personal collection, and owner-only puzzle removal.
- Versioned game API with Sanctum bearer tokens, native-client login, uploads, browsing, downloads, and per-device token revocation.
- Paste JSON or upload a file (100,000-byte limit). Stable UUID share pages, static SVG board previews, exact JSON downloads, clipboard sharing, download counts.
- Structural intake checks, foreign-key checks for required mechanisms, escaped user content, CSRF protection, login/registration/upload/download throttles.
- SQLite database with Laravel sessions and cache; no frontend build, background worker, or additional database server needed.

## Run locally

```bash
cd /var/www/sites/missed-it/server
composer install
# For a fresh checkout only:
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

The current workspace already has dependencies, an app key, and the migrated SQLite database. Do not replace an existing `.env` or regenerate its key on deployment. The database is `database/database.sqlite` by default and is ignored by Git. The site starts empty; register a creator account and share your first export.

For optional starting content, `php artisan garden:examples` adds three clearly attributed Studio example templates adapted from `WorkshopExamples.cs`. It is idempotent and creates an inaccessible example-author account with a random password. It does not create verified solutions or change the game.

## Game API

The game can upload directly to `POST /api/v1/puzzles` with a Sanctum bearer token. Players can generate/revoke tokens under **Game API** on the website, or obtain one through `POST /api/v1/tokens` using their account credentials. Successful uploads return the puzzle's public share URL. Public browse/detail/download endpoints and an authenticated personal collection are included.

See [docs/API.md](docs/API.md) for the full contract, authentication, request/response examples, errors, limits, and a C# upload example. Deploy the token-table migration with `php artisan migrate --force` and refresh routes with `php artisan optimize` after installing dependencies. The API reuses the same validator and publisher as the website.

## Sharing with the game

1. In the game, open Create a puzzle → Puzzles → **Share online**. The game copies the puzzle and opens this site's Share a puzzle page.
2. Sign in and paste it (or upload a JSON file).
3. Other players open Create a puzzle → **Puzzle Garden** in the game, which lists puzzles through the public game API (`GET /api/v1/puzzles`) and downloads from `GET /api/v1/puzzles/{id}/download`; see [docs/API.md](docs/API.md). Tapping one downloads it, replays its recorded clear, and saves a local copy.
4. Copying JSON by hand still works: Puzzles → Share JSON, then Import JSON → Import copy.

The download retains the submitted text byte for byte, including clear replay data and its board hash. The server checks supported version 1 document structure; it does **not** run the C# physics, establish solvability, check every geometric constraint, or certify replays. The game validates geometry and replays on import. Difficulty is self-reported. Old offline Room Workshop files and campaign bundles are different formats. Future Puzzle Studio schema versions need corresponding intake/preview updates. Moving pieces are shown at their home position, and boss/gift effects are not rendered in previews.

## Nginx and PHP-FPM

Configuration is prepared in `deploy/` for this machine's PHP 8.5 and Nginx. The default hostname is **missedit.chain-reaction-game.com**; substitute your actual domain if desired. The document root is strictly `server/public`, following [Laravel's deployment guidance](https://laravel.com/framework/docs/13.x/deployment).

This sandbox can write only to the project and `/tmp`. It cannot install files in `/etc` or reload system services, so the system vhost must be activated by an administrator:

```bash
cd /var/www/sites/missed-it
sudo bash server/deploy/install-vhost.sh missedit.chain-reaction-game.com
```

The script installs the site and a dedicated `missed-it` PHP-FPM pool running as the project owner (`chuck`), tests both service configurations, migrates/optimizes the app, and reloads services. It leaves other sites enabled. Nginx connects via `/run/php/missed-it.sock`. Inspect the two supplied configuration files before applying on another machine; their paths and users are specific to this server.

For local use, add `SERVER_IP missedit.chain-reaction-game.com` to the **client computer's** hosts file (127.0.0.1 if browsing on the server). For a public domain, point its DNS at this server, set `APP_URL=https://missedit.chain-reaction-game.com` and `SESSION_SECURE_COOKIE=true` in `.env`, add your TLS certificate using your existing certificate workflow, and rerun `php artisan config:cache` as `chuck`. The included vhost serves HTTP and does not provision certificates or DNS. Clipboard API requires HTTPS or localhost; an HTTP fallback exposes selectable text.

### Repair the FPM socket and enable HTTPS

The host uses an enforcing AppArmor PHP-FPM profile. Its existing rules allow the Chain Reaction sites but omit this app's custom socket and filesystem paths. The updated installer adds the scoped rules in `deploy/missed-it.apparmor` through an additive local include and reloads the profile. It verifies the socket and an actual Nginx request instead of relying only on a successful reload command. If the socket still fails, it prints FPM and kernel diagnostics.

For both the upstream repair and a Let's Encrypt certificate:

```bash
sudo bash /var/www/sites/missed-it/server/deploy/install-ssl.sh
```

This runs the repaired vhost installer, backs up the Nginx site, uses the existing Certbot Nginx plugin to issue/install a certificate for `missedit.chain-reaction-game.com` and redirect HTTP to HTTPS, updates `APP_URL` and secure cookies, rebuilds Laravel's configuration cache, checks HTTPS locally and publicly, and enables the existing `certbot.timer` renewal job. Certbot may prompt for account details if no existing account can be reused. The DNS record and inbound ports 80/443 must reach this server. See [Certbot's Nginx documentation](https://eff-certbot.readthedocs.io/en/stable/using.html#nginx).

Re-running `install-vhost.sh` preserves an existing TLS configuration. To test renewal after initial setup, run `sudo certbot renew --cert-name missedit.chain-reaction-game.com --dry-run` (check `sudo certbot certificates` if Certbot allocated a different certificate name).

Keep `APP_ENV=production` and `APP_DEBUG=false`. Preserve `.env` and back up `database/database.sqlite` using SQLite's backup mechanism (or stop writes before copying). Runtime writes belong to `storage/`, `bootstrap/cache/`, and the database directory. Static public files must remain readable by Nginx. There is no email verification/password recovery or moderation console in this initial version; the site never sends email, and email addresses are not displayed publicly. Operators can remove abusive uploads via Laravel Tinker or the database.

## Verify

```bash
cd server
composer test
php artisan view:cache
php artisan route:list --except-vendor
bash -n deploy/install-vhost.sh
```

`composer test` first clears cached configuration so PHPUnit can use its test database. Feature tests use an isolated in-memory SQLite database and cover sharing, exact download and replay preservation, malformed input, access control, escaping, filters, and authentication. No Godot build is required; importing and playing a file in Godot remains a manual integration check.
