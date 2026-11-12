#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ ${1:-} == --help ]]; then
    echo 'Usage: bash deploy/deploy.sh'
    echo 'Deploy the code already checked out here as the project owner (no sudo).'
    exit 0
fi
[[ $# == 0 ]] || { echo 'Unexpected arguments. Use --help.' >&2; exit 1; }
[[ $(id -un) == chuck ]] || { echo 'Run as chuck, without sudo.' >&2; exit 1; }

app_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd "$app_dir"
for dependency in php composer curl flock; do
    command -v "$dependency" >/dev/null || { echo "Missing command: $dependency" >&2; exit 1; }
done
for required in .env composer.lock vendor/autoload.php; do
    [[ -f $required ]] || { echo "Missing $required; complete initial setup first." >&2; exit 1; }
done
[[ -w storage/framework && -w bootstrap/cache ]] || { echo 'Runtime directories must be writable.' >&2; exit 1; }
exec 9>storage/framework/deploy.lock
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
[[ ! -e storage/framework/down && ! -e storage/framework/maintenance.php ]] || {
    echo 'App is already in maintenance mode. Resolve that deployment first.' >&2
    exit 1
}

stage=preflight
maintenance_started=0
backup_dir=
failed() {
    result=$?
    trap - EXIT
    if (( result != 0 )); then
        echo "Deployment failed during: $stage." >&2
        [[ -z $backup_dir ]] || echo "Backup directory (check for a completed database.sqlite): $backup_dir" >&2
        if (( maintenance_started )); then
            echo 'Maintenance mode has been requested; inspect the app before reopening it.' >&2
            echo 'Fix the failure, rerun the failed steps, then run: php artisan up' >&2
            echo 'Database and code rollback are manual; no backup is restored automatically.' >&2
        fi
    fi
    exit "$result"
}
trap failed EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

health_url=$(php deploy/sqlite-backup.php --check)
composer validate --no-check-publish --no-interaction

stage='entering maintenance mode'
# Match Artisan to the fresh .env configuration validated by the helper.
php artisan config:clear --no-interaction
maintenance_started=1
php artisan down --retry=60 --no-interaction

stage='SQLite backup'
backup_dir="$app_dir/storage/app/private/deploy-backups/$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -p "$backup_dir"
php deploy/sqlite-backup.php "$backup_dir/database.sqlite"
echo "Database backup: $backup_dir/database.sqlite"

stage='installing locked dependencies'
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev

stage=migrations
php artisan migrate --force --no-interaction
stage='rebuilding caches'
# Run individually: optimize can report a failed subtask without a nonzero exit.
php artisan config:cache --no-interaction
php artisan event:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

stage='leaving maintenance mode'
php artisan up --no-interaction
maintenance_started=0
stage='HTTP health check'
# A redirect is not success: require a direct 200 from the configured /up URL.
if ! health_status=$(curl --silent --show-error --connect-timeout 5 --max-time 20 \
    --output /dev/null --write-out '%{http_code}' "$health_url") || [[ $health_status != 200 ]]; then
    echo "Health check returned HTTP $health_status at $health_url" >&2
    maintenance_started=1
    php artisan down --retry=60 --no-interaction
    exit 1
fi
echo "Deployment complete. Health check passed: $health_url"
