import http from 'node:http';
import net from 'node:net';

/**
 * Passenger (CloudLinux) entry for /app WebSocket proxy → Laravel Reverb.
 * Must listen on process.env.PORT. Plain HTTP GETs to /app may 4xx/5xx from
 * Reverb; that is expected — clients use the WebSocket upgrade path.
 */
const REVERB_HOST = process.env.REVERB_PROXY_HOST ?? '127.0.0.1';
const REVERB_PORT = Number(process.env.REVERB_PROXY_PORT ?? 8080);

function reverbPath(url = '/') {
    const pathOnly = String(url || '/').split('#')[0];

    if (
        pathOnly.startsWith('/app/')
        || pathOnly === '/app'
        || pathOnly.startsWith('/app?')
        || pathOnly.startsWith('/apps/')
        || pathOnly.startsWith('/apps?')
    ) {
        return pathOnly;
    }

    return pathOnly.startsWith('/') ? `/app${pathOnly}` : `/app/${pathOnly}`;
}

function proxyHeaders(req) {
    const headers = { ...req.headers, host: `${REVERB_HOST}:${REVERB_PORT}` };
    // Avoid compressed upstream responses confusing some clients.
    delete headers['accept-encoding'];

    return headers;
}

const server = http.createServer((req, res) => {
    const proxy = http.request(
        {
            host: REVERB_HOST,
            port: REVERB_PORT,
            path: reverbPath(req.url),
            method: req.method,
            headers: proxyHeaders(req),
        },
        (proxyRes) => {
            res.writeHead(proxyRes.statusCode ?? 502, proxyRes.headers);
            proxyRes.pipe(res);
        },
    );

    proxy.on('error', (error) => {
        console.error('[reverb-proxy] upstream error', error.message);
        if (!res.headersSent) {
            res.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
        }
        res.end('Bad gateway');
    });

    req.pipe(proxy);
});

server.on('upgrade', (req, socket, head) => {
    const upstream = net.connect(REVERB_PORT, REVERB_HOST, () => {
        const path = reverbPath(req.url);
        upstream.write(`${req.method} ${path} HTTP/${req.httpVersion}\r\n`);

        for (const [name, value] of Object.entries(proxyHeaders(req))) {
            if (value == null) {
                continue;
            }
            upstream.write(`${name}: ${Array.isArray(value) ? value.join(', ') : value}\r\n`);
        }

        upstream.write('\r\n');

        if (head?.length) {
            upstream.write(head);
        }

        socket.pipe(upstream).pipe(socket);
    });

    upstream.on('error', (error) => {
        console.error('[reverb-proxy] upgrade upstream error', error.message);
        socket.destroy();
    });
    socket.on('error', () => upstream.destroy());
});

const port = process.env.PORT || process.env.PASSENGER_PORT;

if (port) {
    server.listen(Number(port), () => {
        console.log(`[reverb-proxy] listening on ${port} → ${REVERB_HOST}:${REVERB_PORT}`);
    });
} else {
    // CloudLinux / older Passenger injects the socket via the 'passenger' path.
    server.listen('passenger', () => {
        console.log(`[reverb-proxy] listening via passenger socket → ${REVERB_HOST}:${REVERB_PORT}`);
    });
}