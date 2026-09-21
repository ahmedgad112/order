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
PHP="/opt/alt/php84/usr/bin/php"
KEEPALIVE="/home/halwuecc/bin/orded-keepalive.sh"
LOG="$APP/storage/logs/reverb.log"
LOCK="$APP/storage/logs/reverb.lock"

"$PHP" -v | head -1

cat > "$KEEPALIVE" <<'EOF'
#!/bin/bash
set -eu
APP="/home/halwuecc/orded.gaddevelopment.site"
PHP="/opt/alt/php84/usr/bin/php"
LOG="$APP/storage/logs/reverb.log"
LOCK="$APP/storage/logs/reverb.lock"

listening() {
  "$PHP" -r 'exit(@fsockopen("127.0.0.1", 8080, $e, $s, 1) ? 0 : 1);'
}

if listening; then
  exit 0
fi

exec 9>"$LOCK"
if ! flock -n 9; then
  exit 0
fi

if listening; then
  exit 0
fi

ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -F '8080' | awk '{print $1}' | while read -r pid; do
  kill "$pid" 2>/dev/null || true
done
sleep 1
ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -F '8080' | awk '{print $1}' | while read -r pid; do
  kill -9 "$pid" 2>/dev/null || true
done
sleep 1

mkdir -p "$APP/storage/logs"
cd "$APP"
printf '%s starting reverb\n' "$(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"
nohup "$PHP" artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction >> "$LOG" 2>&1 < /dev/null &
sleep 2
if listening; then
  printf '%s reverb listening on 8080\n' "$(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"
  exit 0
fi
printf '%s reverb failed to bind 8080\n' "$(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"
exit 1
EOF
chmod 700 "$KEEPALIVE"

echo "=== STOP OLD REVERB ==="
ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -F '8080' || echo none
ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -F '8080' | awk '{print $1}' | while read -r pid; do
  kill "$pid" 2>/dev/null || true
done
sleep 1
ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -F '8080' | awk '{print $1}' | while read -r pid; do
  kill -9 "$pid" 2>/dev/null || true
done
rm -f "$LOCK"
sleep 2

echo "=== START PHP84 REVERB ==="
cd "$APP"
nohup "$PHP" artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction >> "$LOG" 2>&1 < /dev/null &
sleep 3

echo "=== VERIFY ==="
echo "--- processes ---"
ps -eo pid=,args= | grep '[a]rtisan reverb:start' | grep -v grep || echo none
echo "--- 8080 ---"
if "$PHP" -r 'exit(@fsockopen("127.0.0.1", 8080, $e, $s, 1) ? 0 : 1);'; then
  echo "8080 open"
else
  echo "8080 closed"
fi
echo "--- crontab ---"
crontab -l
echo "--- log ---"
tail -n 12 "$LOG"
echo "--- queue log mtime ---"
ls -l "$APP/storage/logs/queue-worker.log"
REMOTE
