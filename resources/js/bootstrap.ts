import axios from 'axios';
import Alpine from '@alpinejs/csp';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

Alpine.data('grWelcomeModal', () => ({
    open: false,
    init() {
        const storageKey = 'gr_welcome_seen';

        try {
            this.open = !window.localStorage.getItem(storageKey);
        } catch {
            this.open = true;
        }
    },
    close() {
        this.open = false;

        try {
            window.localStorage.setItem('gr_welcome_seen', '1');
        } catch {
            // Ignore storage failures in restricted contexts.
        }
    },
}));

Alpine.data('grDropdown', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

Alpine.data('grModal', () => ({
    show: false,
    focusOnOpen: false,
    init() {
        this.show = this.$el.dataset.modalInitialShow === '1';
        this.focusOnOpen = this.$el.dataset.modalFocusable === '1';

        this.$watch('show', (value: boolean) => {
            document.body.classList.toggle('overflow-y-hidden', value);

            if (value && this.focusOnOpen) {
                window.setTimeout(() => {
                    this.firstFocusable()?.focus();
                }, 100);
            }
        });
    },
    focusables() {
        const selector = 'a, button, input:not([type="hidden"]), textarea, select, details, [tabindex]:not([tabindex="-1"])';

        return Array.from(this.$el.querySelectorAll<HTMLElement>(selector))
            .filter((element) => !element.hasAttribute('disabled'));
    },
    firstFocusable() {
        return this.focusables()[0];
    },
    lastFocusable() {
        return this.focusables().slice(-1)[0];
    },
    nextFocusable() {
        return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable();
    },
    prevFocusable() {
        return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable();
    },
    nextFocusableIndex() {
        return (this.focusables().indexOf(document.activeElement as HTMLElement) + 1) % (this.focusables().length + 1);
    },
    prevFocusableIndex() {
        return Math.max(0, this.focusables().indexOf(document.activeElement as HTMLElement)) - 1;
    },
    open() {
        this.show = true;
    },
    close() {
        this.show = false;
    },
    focusNext() {
        this.nextFocusable()?.focus();
    },
    focusPrevious() {
        this.prevFocusable()?.focus();
    },
}));

Alpine.data('grEmptyState', () => ({}));

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
const reverbEnabled = Boolean(providedReverbConfig.enabled);
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
    if (!reverbEnabled) {
        return false;
    }

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
