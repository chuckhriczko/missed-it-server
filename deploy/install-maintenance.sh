#!/usr/bin/env bash
set -euo pipefail
[[ $(id -u) == 0 ]] || { echo 'Run with sudo bash deploy/install-maintenance.sh' >&2; exit 1; }
app=/var/www/sites/missed-it/server
command -v logrotate >/dev/null || { echo 'Install logrotate first.' >&2; exit 1; }
[[ -x /usr/bin/php && -f "$app/artisan" ]] || { echo 'Application or PHP is missing.' >&2; exit 1; }
logrotate --debug "$app/deploy/missed-it.logrotate"
install -o root -g root -m 0644 "$app/deploy/missed-it.cron" /etc/cron.d/missed-it
install -o root -g root -m 0644 "$app/deploy/missed-it.logrotate" /etc/logrotate.d/missed-it
systemctl enable --now cron
echo 'Scheduler installed: daily verified backups at 02:15 UTC and token cleanup at 02:45 UTC.'
