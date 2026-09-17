import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;
let echoPromise = null;

export function loadEcho() {
    if (echoInstance) {
        return Promise.resolve(echoInstance);
    }

    if (echoPromise) {
        return echoPromise;
    }

    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (!reverbKey) {
        return Promise.resolve(null);
    }

    echoPromise = Promise.resolve().then(() => {
        window.Pusher = Pusher;

        const configuredHost = import.meta.env.VITE_REVERB_HOST;
        const wsHost = !configuredHost || configuredHost === 'localhost'
            ? window.location.hostname
            : configuredHost;
        const pageIsHttps = window.location.protocol === 'https:';
        const pagePort = Number(window.location.port || (pageIsHttps ? 443 : 80));
        const configuredPort = Number(import.meta.env.VITE_REVERB_PORT ?? 0);
        const forceTLS = pageIsHttps || (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https';
        const usesDevPort = !configuredPort || configuredPort === 8080;
        const socketPort = forceTLS
            ? (usesDevPort ? pagePort : configuredPort)
            : (configuredPort || 8080);

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost,
            wsPort: socketPort,
            wssPort: socketPort,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo = echoInstance;

        return echoInstance;
    });

    return echoPromise;
}
