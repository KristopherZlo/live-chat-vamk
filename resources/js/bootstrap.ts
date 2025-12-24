import axios from 'axios';
import Alpine from 'alpinejs';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Alpine = Alpine;
Alpine.start();

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow teams to quickly build robust real-time web applications.
 */

// ОТКЛЮЧАЕМ стандартный echo.js, чтобы не было второй конфигурации
// import './echo';

type Pickable = string | number | undefined | null;
const providedReverbConfig = window.__reverbConfig ?? {};
const env = typeof import.meta !== 'undefined' && import.meta?.env ? import.meta.env : {};
const pickFirst = (...values: Pickable[]): Pickable => {
    for (const value of values) {
        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return '';
};

const shouldInitEcho = () => {
    const routeName = document.body?.dataset?.routeName || '';
    if (routeName === 'rooms.public') {
        return true;
    }
    return Boolean(document.querySelector('[data-room-slug]') || document.querySelector('.messages-container'));
};

const initEcho = async () => {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ]);

    window.Pusher = Pusher;

    const reverbKey = String(pickFirst(providedReverbConfig.key, env?.VITE_REVERB_APP_KEY, '') ?? '');
    const reverbHost = String(
        pickFirst(providedReverbConfig.host, env?.VITE_REVERB_HOST, window.location.hostname) ?? window.location.hostname,
    );
    const reverbPort = Number(pickFirst(providedReverbConfig.port, env?.VITE_REVERB_PORT, 8080) ?? 8080);
    const reverbScheme = String(pickFirst(providedReverbConfig.scheme, env?.VITE_REVERB_SCHEME, 'http') ?? 'http');
    const forceTLS = reverbScheme === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    });
};

if (shouldInitEcho()) {
    window.__echoReady = initEcho().catch((error) => {
        console.warn('Echo init failed', error);
    });
}
