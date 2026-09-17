import http from 'node:http';
import net from 'node:net';

const REVERB_HOST = process.env.REVERB_PROXY_HOST ?? '127.0.0.1';
const REVERB_PORT = Number(process.env.REVERB_PROXY_PORT ?? 8080);

function reverbPath(url = '/') {
    if (url.startsWith('/app/') || url === '/app' || url.startsWith('/app?') || url.startsWith('/apps/') || url.startsWith('/apps?')) {
        return url;
    }

    return url.startsWith('/') ? `/app${url}` : `/app/${url}`;
}

const server = http.createServer((req, res) => {
    const proxy = http.request(
        {
            host: REVERB_HOST,
            port: REVERB_PORT,
            path: reverbPath(req.url),
            method: req.method,
            headers: {
                ...req.headers,
                host: `${REVERB_HOST}:${REVERB_PORT}`,
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
    const upstream = net.connect(REVERB_PORT, REVERB_HOST, () => {
        const path = reverbPath(req.url);
        upstream.write(`${req.method} ${path} HTTP/${req.httpVersion}\r\n`);

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

const port = process.env.PORT;

if (port) {
    server.listen(Number(port));
} else {
    server.listen();
}
