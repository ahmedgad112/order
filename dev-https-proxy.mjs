// Local HTTPS dev proxy: https://localhost:8443
//   -> http://127.0.0.1:8000  (Laravel)
//   -> http://127.0.0.1:8080  (Reverb websockets, /app + /apps paths)
// Run: node dev-https-proxy.mjs

import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import net from 'node:net';

const cert = fs.readFileSync(new URL('./dev-localhost.pem', import.meta.url));
const key = fs.readFileSync(new URL('./dev-localhost-key.pem', import.meta.url));

const APP_PORT = Number(process.env.APP_PORT ?? 8000);
const REVERB_PORT = Number(process.env.REVERB_PORT ?? 8080);
const LISTEN_PORT = Number(process.env.LISTEN_PORT ?? 8443);

const targetPort = (url) => (url?.startsWith('/app/') || url?.startsWith('/apps/') ? REVERB_PORT : APP_PORT);

const server = https.createServer({ cert, key }, (req, res) => {
    const port = targetPort(req.url);
    const proxy = http.request(
        {
            host: '127.0.0.1',
            port,
            path: req.url,
            method: req.method,
            headers: {
                ...req.headers,
                host: req.headers.host,
                'x-forwarded-proto': 'https',
                'x-forwarded-host': req.headers.host,
                'x-forwarded-port': String(LISTEN_PORT),
            },
        },
        (proxyRes) => {
            res.writeHead(proxyRes.statusCode ?? 502, proxyRes.headers);
            proxyRes.pipe(res);
        },
    );

    proxy.on('error', () => {
        if (!res.headersSent) {
            res.writeHead(502);
        }
        res.end('Bad gateway');
    });

    req.pipe(proxy);
});

server.on('upgrade', (req, socket, head) => {
    const upstream = net.connect(targetPort(req.url), '127.0.0.1', () => {
        upstream.write(`${req.method} ${req.url} HTTP/${req.httpVersion}\r\n`);

        for (const [name, value] of Object.entries(req.headers)) {
            upstream.write(`${name}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
        }

        upstream.write('\r\n');

        if (head?.length) {
            upstream.write(head);
        }

        socket.pipe(upstream).pipe(socket);
    });

    upstream.on('error', () => socket.destroy());
    socket.on('error', () => upstream.destroy());
});

server.listen(LISTEN_PORT, '0.0.0.0', () => {
    console.log(`https://localhost:${LISTEN_PORT} -> 127.0.0.1:${APP_PORT} (reverb /app,/apps -> ${REVERB_PORT})`);
});
