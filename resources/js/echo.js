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

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo = echoInstance;

        return echoInstance;
    });

    return echoPromise;
}
