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

echo "=== LOCAL BUILD ==="
cd "$APP_LOCAL"
npm run build

echo "=== SERVER: git pull ==="
"${SSH[@]}" 'bash -s' <<'REMOTE'
set -euo pipefail
APP=/home/halwuecc/orded.gaddevelopment.site
COMPOSER=/home/halwuecc/bin/composer
cd "$APP"
echo "BEFORE=$(git rev-parse --short HEAD)"
git fetch origin main
git reset --hard origin/main
echo "AFTER=$(git rev-parse --short HEAD) $(git log -1 --pretty=%s)"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
REMOTE

echo "=== UPLOAD public/build ==="
tar czf - -C "$APP_LOCAL" public/build \
  | "${SSH[@]}" 'cd /home/halwuecc/orded.gaddevelopment.site && rm -rf public/build && tar xzf - && test -f public/build/manifest.json && echo upload:ok'

echo "=== ARTISAN + REVERB ==="
"${SSH[@]}" 'bash -s' <<'REMOTE'
set -euo pipefail
APP=/home/halwuecc/orded.gaddevelopment.site
PHP=/usr/local/bin/php
KEEPALIVE=/home/halwuecc/bin/orded-keepalive.sh
cd "$APP"
rm -f public/hot
"$PHP" artisan migrate --force
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
mkdir -p tmp public/app/tmp
touch tmp/restart.txt public/app/tmp/restart.txt
if [ -x "$KEEPALIVE" ]; then
  set +e
  "$KEEPALIVE"
  echo "keepalive_exit=$?"
  set -e
fi
"$PHP" -r 'echo @fsockopen("127.0.0.1", 8080, $e, $s, 1) ? "reverb:8080 open\n" : "reverb:8080 closed\n";'
echo "DONE $(git rev-parse --short HEAD)"
REMOTE
