#!/usr/bin/env bash
set -euo pipefail
if [[ $(id -u) -ne 0 ]]; then
    echo 'Run: sudo bash server/deploy/install-ssl.sh' >&2
    exit 1
fi
puzzle_host=missedit.chain-reaction-game.com
puzzle_app=/var/www/sites/missed-it/server
command -v certbot >/dev/null || { echo 'Certbot must be installed first.' >&2; exit 1; }
# Repair/check the upstream before asking the certificate authority for a cert.
bash "$puzzle_app/deploy/install-vhost.sh" "$puzzle_host"
cp -a /etc/nginx/sites-available/missed-it "/etc/nginx/sites-available/missed-it.pre-ssl-$(date -u +%Y%m%dT%H%M%SZ)"
# Nginx plugin handles HTTP validation, installs the certificate and redirects.
# Use the existing ACME account on this server; Certbot prompts if it needs one.
certbot --nginx --domain "$puzzle_host" --redirect --agree-tos
# Change application settings only after successful certificate installation.
runuser -u chuck -- python3 - "$puzzle_app/.env" "$puzzle_host" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
updates={'APP_URL':'https://'+sys.argv[2], 'SESSION_SECURE_COOKIE':'true'}
lines=p.read_text().splitlines()
for key,value in updates.items():
    matches=[i for i,line in enumerate(lines) if line.startswith(key+'=')]
    if matches:
        for i in matches:lines[i]=key+'='+value
    else:lines.append(key+'='+value)
p.write_text('\n'.join(lines)+'\n')
PY
runuser -u chuck -- bash -c 'cd /var/www/sites/missed-it/server && php artisan config:cache'
nginx -t
systemctl reload nginx
# Confirm this machine serves the certificate and Laravel before checking public DNS.
curl --fail --silent --show-error --max-time 15 --resolve "$puzzle_host:443:127.0.0.1" "https://$puzzle_host/up" > /dev/null
curl --fail --silent --show-error --max-time 15 "https://$puzzle_host/up" > /dev/null
systemctl enable --now certbot.timer
printf 'Verified HTTPS: https://%s\nRenewal timer enabled.\n' "$puzzle_host"
