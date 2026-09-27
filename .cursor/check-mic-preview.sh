#!/usr/bin/env bash
set -euo pipefail
KEY="$HOME/.ssh/gad"
UNLOCK="$HOME/.ssh/gad-unlock"
cleanup() { rm -f "$UNLOCK" "$UNLOCK.pub"; }
trap cleanup EXIT
cp -f "$KEY" "$UNLOCK"
chmod 600 "$UNLOCK"
ssh-keygen -p -P 'Ahmedgad@2011' -N '' -f "$UNLOCK" >/dev/null
ssh -i "$UNLOCK" -p 21098 -o IdentitiesOnly=yes -o BatchMode=yes \
  halwuecc@premium106-3.web-hosting.com 'bash -s' <<'REMOTE'
set -euo pipefail
APP=/home/halwuecc/orded.gaddevelopment.site
cd "$APP"
echo "=== MicView has preview? ==="
grep -o "previewAnnouncementAudio\|new Audio" public/build/assets/MicView-*.js | head
echo "=== passenger error from stderr if any ==="
# Try running proxy briefly under node with PORT to see if it works
PORT=19090 /home/halwuecc/nodevenv/orded.gaddevelopment.site/24/bin/node reverb-proxy.mjs >/tmp/rp.log 2>&1 &
RPID=$!
sleep 1
curl -s -o /tmp/rpb.txt -w "direct_proxy=%{http_code}\n" "http://127.0.0.1:19090/local-key" || true
head -c 120 /tmp/rpb.txt; echo
kill $RPID 2>/dev/null || true
cat /tmp/rp.log
# Check if passenger can find startup
ls -la reverb-proxy.mjs public/app/.htaccess
# Restart passenger more forcefully
mkdir -p tmp public/app/tmp
touch tmp/restart.txt public/app/tmp/restart.txt
sleep 2
curl -s -o /tmp/app2.txt -w "app_after_restart=%{http_code}\n" "https://orded.gaddevelopment.site/app/local-key" || true
head -c 120 /tmp/app2.txt; echo
REMOTE
