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
# Ubuntu confines FPM with AppArmor. Permit this socket and app without
# disabling confinement or changing the permissions of the other sites.
if [[ -f /etc/apparmor.d/php-fpm ]]; then
    install -m 644 "$puzzle_deploy/missed-it.apparmor" /etc/apparmor.d/local/php-fpm-missed-it
    puzzle_aa_include='include if exists <local/php-fpm-missed-it>'
    touch /etc/apparmor.d/local/php-fpm
    if ! grep -Fxq "$puzzle_aa_include" /etc/apparmor.d/local/php-fpm; then
        printf '\n%s\n' "$puzzle_aa_include" >> /etc/apparmor.d/local/php-fpm
    fi
    apparmor_parser -r /etc/apparmor.d/php-fpm
fi
install -m 644 "$puzzle_deploy/missed-it.fpm.conf" /etc/php/8.5/fpm/pool.d/missed-it.conf
# Keep any certificate configuration that Certbot has already installed.
if [[ -f /etc/nginx/sites-available/missed-it ]] && grep -q 'ssl_certificate ' /etc/nginx/sites-available/missed-it; then
    grep -Fq "server_name $puzzle_host;" /etc/nginx/sites-available/missed-it || { echo 'Existing TLS vhost uses another hostname; inspect it before proceeding.' >&2; exit 1; }
else
    sed "s/server_name missedit.chain-reaction-game.com;/server_name $puzzle_host;/" "$puzzle_deploy/missed-it.nginx.conf" > /etc/nginx/sites-available/missed-it
fi
if [[ -e /etc/nginx/sites-enabled/missed-it || -L /etc/nginx/sites-enabled/missed-it ]]; then
    [[ $(readlink -f /etc/nginx/sites-enabled/missed-it) == /etc/nginx/sites-available/missed-it ]] || { echo 'Existing missed-it vhost points elsewhere; inspect it before proceeding.' >&2; exit 1; }
else
    ln -s /etc/nginx/sites-available/missed-it /etc/nginx/sites-enabled/missed-it
fi
php-fpm8.5 -t
nginx -t
runuser -u chuck -- bash -c 'cd /var/www/sites/missed-it/server && php artisan migrate --force && php artisan optimize'
puzzle_fpm_diagnostics() {
    systemctl status php8.5-fpm --no-pager --full >&2 || true
    journalctl -u php8.5-fpm -n 30 --no-pager >&2 || true
    tail -n 30 /var/log/php8.5-fpm.log >&2 || true
    journalctl -k --since '-3 minutes' --no-pager | grep -E 'apparmor|DENIED' >&2 || true
}
if systemctl is-active --quiet php8.5-fpm; then
    puzzle_fpm_action=reload
else
    # Config and policy are now validated. Clear previous startup failure limits.
    systemctl reset-failed php8.5-fpm || true
    puzzle_fpm_action=start
fi
if ! systemctl "$puzzle_fpm_action" php8.5-fpm; then
    echo "PHP-FPM failed to $puzzle_fpm_action. Recent diagnostics:" >&2
    puzzle_fpm_diagnostics
    exit 1
fi
for puzzle_wait in {1..15}; do
    [[ -S /run/php/missed-it.sock ]] && systemctl is-active --quiet php8.5-fpm && break
    systemctl is-active --quiet php8.5-fpm || break
    sleep 1
done
if [[ ! -S /run/php/missed-it.sock ]] || ! systemctl is-active --quiet php8.5-fpm; then
    echo 'FPM did not create the missed-it socket. Recent diagnostics:' >&2
    puzzle_fpm_diagnostics
    exit 1
fi
systemctl reload nginx
# An actual request catches missing sockets, socket ownership and app failures.
puzzle_http_code=$(curl --silent --show-error --max-time 15 --output /dev/null --write-out '%{http_code}' -H "Host: $puzzle_host" http://127.0.0.1/)
if [[ "$puzzle_http_code" != 200 && "$puzzle_http_code" != 301 && "$puzzle_http_code" != 302 && "$puzzle_http_code" != 308 ]]; then
    echo "Nginx returned HTTP $puzzle_http_code; refusing to report success." >&2
    tail -n 30 /var/log/nginx/missed-it-error.log >&2 || true
    exit 1
fi
# A TLS redirect alone does not prove PHP works. Check /up over HTTPS too.
if grep -q 'ssl_certificate ' /etc/nginx/sites-available/missed-it; then
    curl --fail --silent --show-error --max-time 15 --resolve "$puzzle_host:443:127.0.0.1" "https://$puzzle_host/up" > /dev/null
fi
printf 'Vhost activated for http://%s. Set APP_URL to match and configure client DNS/hosts.\n' "$puzzle_host"
