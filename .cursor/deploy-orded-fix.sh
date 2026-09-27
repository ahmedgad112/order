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

echo "=== UPLOAD code + build ==="
tar czf - -C "$APP_LOCAL" \
  public/build \
  reverb-proxy.mjs \
  app/Services/SpeechService.php \
  resources/js/views/MicView.vue \
  resources/js/views/PublicDisplayView.vue \
  resources/js/stores/queueStore.js \
  | "${SSH[@]}" 'cd /home/halwuecc/orded.gaddevelopment.site && tar xzf - && echo upload:ok'

echo "=== ARTISAN + PASSENGER TOUCH ==="
"${SSH[@]}" 'bash -s' <<'REMOTE'
set -euo pipefail
APP=/home/halwuecc/orded.gaddevelopment.site
PHP=/usr/local/bin/php
KEEPALIVE=/home/halwuecc/bin/orded-keepalive.sh
cd "$APP"

rm -f public/hot
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

# Restart Passenger Node app for /app
mkdir -p tmp
touch tmp/restart.txt
# also common CloudLinux restart signal
touch public/app/tmp/restart.txt 2>/dev/null || mkdir -p public/app/tmp && touch public/app/tmp/restart.txt

if [ -x "$KEEPALIVE" ]; then
  set +e
  "$KEEPALIVE"
  echo "keepalive_exit=$?"
  set -e
fi

"$PHP" -r 'echo @fsockopen("127.0.0.1", 8080, $e, $s, 1) ? "reverb:8080 open\n" : "reverb:8080 closed\n";'
echo "--- /app probe ---"
curl -s -o /tmp/app_body.txt -w "http=%{http_code}\n" "https://orded.gaddevelopment.site/app/local-key" || true
head -c 200 /tmp/app_body.txt; echo
test -f public/build/manifest.json && echo "manifest:ok"
grep -n "previewAnnouncementAudio\|isRecentAnnouncement\|created_at" public/build/assets/MicView-*.js public/build/assets/PublicDisplayView-*.js app/Services/SpeechService.php | head -n 20 || true
echo DONE
REMOTE
