#!/usr/bin/env bash
set -euo pipefail
if [[ $(id -u) -ne 0 ]]; then
    echo 'Run this installer as root: sudo bash server/deploy/install-vhost.sh [hostname]' >&2
    exit 1
fi
puzzle_host=${1:-missedit.chain-reaction-game.com}
if [[ ! $puzzle_host =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$ ]]; then
    echo 'Supply a bare hostname, without a scheme, port, or path.' >&2
    exit 1
fi
puzzle_app=/var/www/sites/missed-it/server
puzzle_deploy="$puzzle_app/deploy"
getent passwd chuck >/dev/null
getent passwd nginx >/dev/null
# Keep FPM writes in the application's existing runtime directories.
chown -R chuck:chuck "$puzzle_app/storage" "$puzzle_app/bootstrap/cache" "$puzzle_app/database"
chmod -R u+rwX,go-rwx "$puzzle_app/storage" "$puzzle_app/database"
chmod 600 "$puzzle_app/.env"
install -m 644 "$puzzle_deploy/missed-it.fpm.conf" /etc/php/8.5/fpm/pool.d/missed-it.conf
sed "s/server_name missedit.chain-reaction-game.com;/server_name $puzzle_host;/" "$puzzle_deploy/missed-it.nginx.conf" > /etc/nginx/sites-available/missed-it
if [[ -e /etc/nginx/sites-enabled/missed-it || -L /etc/nginx/sites-enabled/missed-it ]]; then
    [[ $(readlink -f /etc/nginx/sites-enabled/missed-it) == /etc/nginx/sites-available/missed-it ]] || { echo 'Existing missed-it vhost points elsewhere; inspect it before proceeding.' >&2; exit 1; }
else
    ln -s /etc/nginx/sites-available/missed-it /etc/nginx/sites-enabled/missed-it
fi
php-fpm8.5 -t
nginx -t
runuser -u chuck -- bash -c 'cd /var/www/sites/missed-it/server && php artisan migrate --force && php artisan optimize'
systemctl reload php8.5-fpm
systemctl reload nginx
printf 'Vhost activated for http://%s. Set APP_URL to match and configure client DNS/hosts.\n' "$puzzle_host"
