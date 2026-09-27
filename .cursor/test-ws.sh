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
NODE=/home/halwuecc/nodevenv/orded.gaddevelopment.site/24/bin/node
$NODE - <<'JS'
import net from 'node:net';
import tls from 'node:tls';

function tryWs(host, port, path, useTls) {
  return new Promise((resolve) => {
    const sock = useTls
      ? tls.connect({ host, port, servername: host })
      : net.connect({ host, port });
    const key = Buffer.from('dGhlIHNhbXBsZSBub25jZQ==', 'base64').toString('base64');
    let data = '';
    const timer = setTimeout(() => {
      sock.destroy();
      resolve({ host, path, useTls, result: 'timeout', data: data.slice(0, 200) });
    }, 5000);
    sock.on('connect', () => {
      sock.write(
        `GET ${path} HTTP/1.1\r\n` +
        `Host: ${host}\r\n` +
        `Connection: Upgrade\r\n` +
        `Upgrade: websocket\r\n` +
        `Sec-WebSocket-Version: 13\r\n` +
        `Sec-WebSocket-Key: ${key}\r\n` +
        `\r\n`
      );
    });
    sock.on('data', (chunk) => {
      data += chunk.toString('utf8');
      if (data.includes('\r\n\r\n')) {
        clearTimeout(timer);
        const status = data.split('\r\n')[0];
        sock.destroy();
        resolve({ host, path, useTls, result: status, head: data.slice(0, 180) });
      }
    });
    sock.on('error', (e) => {
      clearTimeout(timer);
      resolve({ host, path, useTls, result: 'error:' + e.message });
    });
  });
}

const tests = [
  await tryWs('127.0.0.1', 8080, '/app/local-key?protocol=7&client=js&version=8.4.0&flash=false', false),
  await tryWs('orded.gaddevelopment.site', 443, '/app/local-key?protocol=7&client=js&version=8.4.0&flash=false', true),
];
console.log(JSON.stringify(tests, null, 2));
JS
REMOTE
