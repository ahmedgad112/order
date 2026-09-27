#!/usr/bin/env bash
set -euo pipefail
KEY="$HOME/.ssh/gad"
UNLOCK="$HOME/.ssh/gad-unlock"
cleanup() { rm -f "$UNLOCK" "$UNLOCK.pub"; }
trap cleanup EXIT
cp -f "$KEY" "$UNLOCK"
chmod 600 "$UNLOCK"
ssh-keygen -p -P 'Ahmedgad@2011' -N '' -f "$UNLOCK" >/dev/null
ssh -i "$UNLOCK" -p 21098 -o IdentitiesOnly=yes -o BatchMode=yes -o ServerAliveInterval=30 \
  halwuecc@premium106-3.web-hosting.com 'bash -s' <<'REMOTE'
set -euo pipefail
APP=/home/halwuecc/orded.gaddevelopment.site
cd "$APP"
echo "=== public/app ==="
ls -la public/app 2>/dev/null || echo missing
cat public/app/.htaccess 2>/dev/null || true
echo "=== find reverb/ws/passenger ==="
find . -maxdepth 3 \( -iname '*passenger*' -o -iname '*reverb*' -o -iname '*websocket*' \) 2>/dev/null | head -50
echo "=== bin ==="
ls -la /home/halwuecc/bin 2>/dev/null | head -40
echo "=== curl apps proxy ==="
curl -sI "https://orded.gaddevelopment.site/apps/local/events" | head -n 10 || true
echo "=== error_log tail ==="
tail -n 30 storage/logs/laravel.log | grep -i -E "speech|announce|reverb|broadcast" || tail -n 5 storage/logs/laravel.log
REMOTE
