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

echo "=== HEAD / CONFIG ==="
git rev-parse --short HEAD
"$PHP" artisan tinker --execute 'echo "app.url=".config("app.url").PHP_EOL; echo "broadcast=".config("broadcasting.default").PHP_EOL;'

echo "=== LATEST ANNOUNCEMENT ==="
"$PHP" artisan tinker --execute '$l=App\Models\AnnouncementLog::query()->latest("id")->first(); if(!$l){echo "none\n"; return;} $s=app(App\Services\SpeechService::class); echo json_encode(["id"=>$l->id,"text"=>mb_substr((string)$l->text,0,100),"audio_filename"=>$l->audio_filename,"audioUrl"=>$s->audioUrl((string)$l->audio_filename),"path"=>$s->audioPath((string)$l->audio_filename),"size"=>(($l->audio_filename && Storage::exists("announcements/".$l->audio_filename))?Storage::size("announcements/".$l->audio_filename):null),"created_at"=>(string)$l->created_at], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;'

echo "=== PUBLIC STATUS ANNOUNCEMENT ==="
curl -s "https://orded.gaddevelopment.site/api/public/queue-status" | "$PHP" -r '$j=json_decode(stream_get_contents(STDIN), true); echo json_encode($j["announcement"] ?? null, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;'

echo "=== AUDIO HTTP ==="
FN=$("$PHP" artisan tinker --execute 'echo (string) optional(App\Models\AnnouncementLog::query()->latest("id")->first())->audio_filename;')
echo "filename=$FN"
if [ -n "$FN" ]; then
  curl -sI "https://orded.gaddevelopment.site/api/public/audio/$FN" | head -n 20
  echo "body_bytes=$(curl -s "https://orded.gaddevelopment.site/api/public/audio/$FN" | wc -c)"
fi

echo "=== REVERB ENV (names only) ==="
grep -E '^(REVERB_|VITE_REVERB_|BROADCAST_|APP_URL)' .env | sed 's/=.*/=***/' || true

echo "=== BUILT JS REVERB HINTS ==="
# Look for localhost vs domain baked into assets
rg -l "REVERB|localhost|orded|8080|reverb" public/build/assets/*.js 2>/dev/null | head -n 5 || true
rg -o "wsHost:\"[^\"]+\"|wss://[^\"']+|orded\.gaddevelopment\.site|localhost|127\.0\.0\.1" public/build/assets/echo*.js public/build/assets/app*.js 2>/dev/null | head -n 40 || true

echo "=== LOG WARNINGS ==="
grep -E "Announcement TTS|Speech broadcast|Queue broadcast|No audio" storage/logs/laravel.log 2>/dev/null | tail -n 40 || echo none

echo "=== REVERB PROC ==="
"$PHP" -r 'echo @fsockopen("127.0.0.1", 8080, $e, $s, 1) ? "8080 open\n" : "8080 closed\n";'
ps aux | grep -E "reverb:start" | grep -v grep || echo none
tail -n 15 storage/logs/reverb.log 2>/dev/null || true

echo "=== audioUrl source ==="
grep -n -A4 "function audioUrl" app/Services/SpeechService.php
REMOTE
