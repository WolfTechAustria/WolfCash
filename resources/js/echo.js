import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/*
 * Wird die Kasse über das lokale Netz geladen (Mobile-App mit
 * Autodiscovery, http://<private IP>), soll auch der WebSocket
 * lokal über nginx laufen statt über den öffentlichen Host.
 */
const isLocalNetworkHost =
    /^(10\.\d{1,3}|192\.168|172\.(1[6-9]|2\d|3[01]))\.\d{1,3}\.\d{1,3}$/
        .test(window.location.hostname);

const localPort =
    Number(window.location.port)
    || (window.location.protocol === 'https:' ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: isLocalNetworkHost
        ? window.location.hostname
        : import.meta.env.VITE_REVERB_HOST,
    wsPort: isLocalNetworkHost
        ? localPort
        : import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: isLocalNetworkHost
        ? localPort
        : import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: isLocalNetworkHost
        ? window.location.protocol === 'https:'
        : (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
