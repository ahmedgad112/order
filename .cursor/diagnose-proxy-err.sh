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
echo "=== reverb-proxy.mjs head ==="
sed -n '1,120p' reverb-proxy.mjs
echo "=== passenger / stderr logs ==="
ls -lt ~/logs 2>/dev/null | head -20 || true
ls -lt "$APP/tmp" 2>/dev/null | head -20 || true
# common passenger log locations on cPanel
for f in \
  ~/logs/orded.gaddevelopment.site.error.log \
  ~/logs/orded_gaddevelopment_site.php.error.log \
  "$APP/tmp/passenger.log" \
  "$APP/tmp/stderr.log" \
  /tmp/passenger-*.log
 do
  if [ -f "$f" ]; then
    echo "--- $f ---"
    tail -n 40 "$f"
  fi
 done
# try node proxy directly
echo "=== node syntax check ==="
/home/halwuecc/nodevenv/orded.gaddevelopment.site/24/bin/node --check reverb-proxy.mjs && echo syntax_ok
echo "=== curl /app body ==="
curl -s "https://orded.gaddevelopment.site/app/local-key" | head -c 800; echo
REMOTE
