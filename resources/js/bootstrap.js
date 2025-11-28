import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

// ОТКЛЮЧАЕМ стандартный echo.js, чтобы не было второй конфигурации
// import './echo';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const providedReverbConfig = window.__reverbConfig || {};
const env = (typeof import !== 'undefined' && import.meta && import.meta.env) ? import.meta.env : {};
const pickFirst = (...values) => values.find((v) => v !== undefined && v !== null && v !== '') ?? '';

const reverbKey = pickFirst(providedReverbConfig.key, env.VITE_REVERB_APP_KEY, '');
const reverbHost = pickFirst(providedReverbConfig.host, env.VITE_REVERB_HOST, window.location.hostname);
const reverbPort = Number(pickFirst(providedReverbConfig.port, env.VITE_REVERB_PORT, 8080));
const reverbScheme = pickFirst(providedReverbConfig.scheme, env.VITE_REVERB_SCHEME, 'http');
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

console.log('Echo options:', {
    key: reverbKey,
    host: reverbHost,
    port: reverbPort,
    scheme: reverbScheme,
    source: providedReverbConfig.key ? 'server' : 'env',
});
