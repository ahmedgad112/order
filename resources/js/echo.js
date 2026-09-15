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
        const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 0);

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost,
            wsPort: reverbPort || 8080,
            wssPort: reverbPort || (pageIsHttps ? pagePort : 8080),
            forceTLS: pageIsHttps || (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo = echoInstance;

        return echoInstance;
    });

    return echoPromise;
}
