#!/usr/bin/env bash
set -euo pipefail

KEY="$HOME/.ssh/gad"
UNLOCK="$HOME/.ssh/gad-unlock"

cleanup() {
  rm -f "$UNLOCK" "$UNLOCK.pub"
}
trap cleanup EXIT

cp -f "$KEY" "$UNLOCK"
chmod 600 "$UNLOCK"
ssh-keygen -p -P 'Ahmedgad@2011' -N '' -f "$UNLOCK" >/dev/null

ssh -i "$UNLOCK" -p 21098 -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=45 -o ServerAliveInterval=30 \
  halwuecc@premium106-3.web-hosting.com 'bash -s' <<'REMOTE'
set -euo pipefail
APP="/home/halwuecc/orded.gaddevelopment.site"
PHP="/usr/local/bin/php"
cd "$APP"

echo "=== REVERB KEY MATCH (hashed compare) ==="
"$PHP" artisan tinker --execute 'echo "reverb_key_len=".strlen((string)config("reverb.apps.apps.0.key")).PHP_EOL; echo "reverb_key_prefix=".substr((string)config("reverb.apps.apps.0.key"),0,4).PHP_EOL; echo "vite_key_in_env_prefix=".substr((string)env("VITE_REVERB_APP_KEY"),0,4).PHP_EOL; echo "broadcast_host=".config("broadcasting.connections.reverb.options.host").PHP_EOL; echo "broadcast_port=".config("broadcasting.connections.reverb.options.port").PHP_EOL; echo "broadcast_scheme=".config("broadcasting.connections.reverb.options.scheme").PHP_EOL;'

echo "=== HTACCESS / PROXY ==="
ls -la public/.htaccess .htaccess 2>/dev/null || true
echo "--- public/.htaccess ---"
sed -n '1,120p' public/.htaccess 2>/dev/null || true
echo "--- root .htaccess ---"
sed -n '1,160p' .htaccess 2>/dev/null || true

echo "=== WS ENDPOINT VIA HTTPS ==="
# Should be upgraded by proxy; expect 4xx/101-ish, not HTML 404
curl -sI -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" "https://orded.gaddevelopment.site/app/local-key?protocol=7&client=js&version=8.4.0&flash=false" | head -n 20 || true
curl -sI "https://orded.gaddevelopment.site/app/local-key" | head -n 15 || true

echo "=== TEST BROADCAST ==="
"$PHP" artisan tinker --execute 'try { event(new App\Events\AnnouncementMadeEvent("/api/public/audio/tts-a0ec7d5ddf364fb525d122f02e1ca62c6319b4d1.mp3", "اختبار بث", 455, 1)); echo "broadcast_ok\n"; } catch (Throwable $e) { echo "broadcast_fail: ".$e->getMessage().PHP_EOL; }'

echo "=== BUILT ECHO FILE ON SERVER ==="
ls -la public/build/assets/echo*.js
# extract key baked in
python3 - <<'PY'
import glob,re
for p in glob.glob("public/build/assets/echo*.js"):
    t=open(p,encoding="utf-8",errors="ignore").read()
    print(p)
    print("key=", re.search(r"key:`([^`]+)`|key:\"([^\"]+)\"", t).groups() if re.search(r"key:`([^`]+)`|key:\"([^\"]+)\"", t) else None)
    print("has_localhost", "localhost" in t)
    print("snippet=", t[:300])
PY

echo "=== MANIFEST PublicDisplay ==="
"$PHP" -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true); foreach($m as $k=>$v){ if(str_contains($k,"PublicDisplay")||str_contains($k,"echo")) echo $k," => ",($v["file"]??""),PHP_EOL; }'
REMOTE
