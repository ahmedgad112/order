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
PHP="/usr/local/bin/php"
KEEPALIVE="/home/halwuecc/bin/orded-keepalive.sh"
LOG="$APP/storage/logs/reverb.log"

cat > "$KEEPALIVE" <<'EOF'
#!/bin/bash
set -eu
APP="/home/halwuecc/orded.gaddevelopment.site"
PHP="/usr/local/bin/php"
LOG="$APP/storage/logs/reverb.log"
LOCK="$APP/storage/logs/reverb.lock"

listening() {
  "$PHP" -r 'exit(@fsockopen("127.0.0.1", 8080, $e, $s, 1) ? 0 : 1);'
}

reverb_pids() {
  for pid in $(pgrep -f 'artisan reverb:start' || true); do
    cwd="$(readlink -f "/proc/$pid/cwd" 2>/dev/null || true)"
    if [ "$cwd" = "$APP" ]; then
      printf '%s\n' "$pid"
    fi
  done
}

healthy() {
  listening || return 1
  pids="$(reverb_pids)"
  [ -n "$pids" ] || return 1
  for pid in $pids; do
    cmd="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
    case "$cmd" in
      *"/usr/local/bin/php"*) return 0 ;;
    esac
  done
  return 1
}

if healthy; then
  exit 0
fi

exec 9>"$LOCK"
if ! flock -n 9; then
  exit 0
fi

if healthy; then
  exit 0
fi

for pid in $(reverb_pids); do
  kill "$pid" 2>/dev/null || true
done
sleep 1
for pid in $(reverb_pids); do
  kill -9 "$pid" 2>/dev/null || true
done
sleep 1

mkdir -p "$APP/storage/logs"
cd "$APP"
printf '%s starting reverb with php %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" >> "$LOG"
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

echo "=== KILL STALE THEN START ==="
set +e
"$KEEPALIVE"
echo "keepalive_exit=$?"
set -e

echo
echo "=== VERIFY ==="
echo "--- crontab ---"
crontab -l
echo "--- php ---"
"$PHP" -v | head -1
echo "--- fsockopen 8080 ---"
if "$PHP" -r 'exit(@fsockopen("127.0.0.1", 8080, $e, $s, 1) ? 0 : 1);'; then
  echo "8080 open"
else
  echo "8080 closed"
fi
echo "--- processes ---"
ps aux | grep -E "reverb:start|queue:work" | grep -v grep || echo none
echo "--- cwd/cmdline ---"
for pid in $(pgrep -f 'artisan reverb:start' || true); do
  echo "pid=$pid cwd=$(readlink -f /proc/$pid/cwd) cmd=$(tr '\0' ' ' < /proc/$pid/cmdline)"
done
echo "--- queue log ---"
tail -n 20 "$APP/storage/logs/queue-worker.log" 2>/dev/null || echo no_queue_log
echo "--- reverb log tail ---"
tail -n 15 "$LOG"
REMOTE
