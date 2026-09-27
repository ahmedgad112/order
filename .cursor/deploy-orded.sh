#!/usr/bin/env bash
set -euo pipefail

KEY="$HOME/.ssh/gad"
UNLOCK="$HOME/.ssh/gad-unlock"
APP_LOCAL="/c/laragon/www/Num_sustem"

cleanup() {
  rm -f "$UNLOCK" "$UNLOCK.pub"
}
trap cleanup EXIT

cp -f "$KEY" "$UNLOCK"
chmod 600 "$UNLOCK"
ssh-keygen -p -P 'Ahmedgad@2011' -N '' -f "$UNLOCK" >/dev/null

SSH=(ssh -i "$UNLOCK" -p 21098 -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=45 -o ServerAliveInterval=30 halwuecc@premium106-3.web-hosting.com)

echo "=== UPLOAD public/build ==="
tar czf - -C "$APP_LOCAL" public/build \
  | "${SSH[@]}" 'cd /home/halwuecc/orded.gaddevelopment.site && rm -rf public/build && tar xzf - && test -f public/build/manifest.json && echo upload:ok'

echo "=== ARTISAN + REVERB ==="
"${SSH[@]}" 'bash -s' <<'REMOTE'
set -euo pipefail
APP="/home/halwuecc/orded.gaddevelopment.site"
PHP="/usr/local/bin/php"
KEEPALIVE="/home/halwuecc/bin/orded-keepalive.sh"
cd "$APP"

echo "HEAD=$(git rev-parse --short HEAD) $(git log -1 --pretty=%s)"
rm -f public/hot
"$PHP" artisan migrate --force
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

if [ -x "$KEEPALIVE" ]; then
  set +e
  "$KEEPALIVE"
  echo "keepalive_exit=$?"
  set -e
else
  nohup "$PHP" artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction \
    >> storage/logs/reverb.log 2>&1 < /dev/null &
  sleep 2
fi

"$PHP" -r 'echo @fsockopen("127.0.0.1", 8080, $e, $s, 1) ? "reverb:8080 open\n" : "reverb:8080 closed\n";'
ps aux | grep -E "reverb:start|queue:work" | grep -v grep || echo "no reverb/queue procs"
ls -la public/build/manifest.json
echo DONE
REMOTE
