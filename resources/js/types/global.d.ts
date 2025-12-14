import type Alpine from 'alpinejs';
import type axios from 'axios';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';

type ReverbConfig = {
    key?: string;
    host?: string;
    port?: number | string;
    scheme?: string;
};

declare global {
    interface Window {
        __reverbConfig?: ReverbConfig;
        axios: typeof axios;
        Alpine: typeof Alpine;
        Pusher: typeof Pusher;
        Echo: Echo;
        createQrModules?: (
            link: string,
            canvasSize: number,
            options?: { quietModules?: number; errorCorrectionLevel?: string },
        ) => null | { modules: boolean[][]; moduleSize: number; moduleCount: number; offset: number };
    }
}

export {};
