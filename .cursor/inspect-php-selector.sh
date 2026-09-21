#!/usr/bin/env bash
set -euo pipefail
KEY="$HOME/.ssh/gad"
UNLOCK="$HOME/.ssh/gad-unlock"
cleanup() { rm -f "$UNLOCK" "$UNLOCK.pub"; }
trap cleanup EXIT
cp -f "$KEY" "$UNLOCK"
chmod 600 "$UNLOCK"
ssh-keygen -p -P 'Ahmedgad@2011' -N '' -f "$UNLOCK" >/dev/null
ssh -i "$UNLOCK" -p 21098 -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=45 -o ServerAliveInterval=30 \
  halwuecc@premium106-3.web-hosting.com 'bash -s' <<'REMOTE'
set -e
APP="/home/halwuecc/orded.gaddevelopment.site"
echo "=== PHP FROM HOME ==="
/usr/local/bin/php -v | head -1
echo "=== PHP FROM APP ==="
cd "$APP"
/usr/local/bin/php -v | head -1
echo "=== WRAPPER ==="
head -c 400 /usr/local/bin/php; echo
echo "=== ALT PHP ==="
ls -d /opt/alt/php* 2>/dev/null || true
ls /opt/cpanel | grep -i php || true
ls /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null || true
echo "=== SELECTOR ==="
ls -la "$HOME/.cl.selector"
find "$HOME/.cl.selector" -type f | head
echo "=== APP SELECTOR FILES ==="
ls -la "$APP/.php-version" "$APP/.htaccess" "$APP/public/.htaccess" 2>/dev/null || true
grep -n -i "php\|handler\|lsphp" "$APP/public/.htaccess" | head -20
echo "=== LOCK ==="
ls -l "$APP/storage/logs/reverb.lock" 2>/dev/null || echo no_lock
fuser -v "$APP/storage/logs/reverb.lock" 2>&1 || true
echo "=== QUEUE LOG TIME ==="
ls -l "$APP/storage/logs/queue-worker.log" "$APP/storage/logs/scheduler.log" 2>/dev/null || true
REMOTE
