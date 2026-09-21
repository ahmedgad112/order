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
  halwuecc@premium106-3.web-hosting.com 'echo CRONTAB; crontab -l; echo; echo LOG; tail -n 50 /home/halwuecc/orded.gaddevelopment.site/storage/logs/reverb.log 2>/dev/null || echo no_log; echo; echo PS; ps aux | grep reverb | grep -v grep || echo none; echo; echo PORT; (ss -lptn 2>/dev/null || netstat -lptn 2>/dev/null || true) | grep 8080 || echo no_8080; echo BINS; command -v setsid || echo no_setsid; command -v nohup || echo no_nohup; command -v flock || echo no_flock; echo KEEPALIVE; ls -l /home/halwuecc/bin/orded-keepalive.sh; echo SCRIPT; cat /home/halwuecc/bin/orded-keepalive.sh'
