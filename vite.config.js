import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import os from 'os';

const outboundIPv4 = Object.values(os.networkInterfaces())
    .flatMap((details) => details ?? [])
    .find((detail) => detail && !detail.internal && (detail.family === 'IPv4' || detail.family === 4))
    ?.address;

const devHost = process.env.VITE_DEV_HOST ?? '0.0.0.0';
const devPort = Number(process.env.VITE_DEV_PORT) || 5173;
const devProtocol = process.env.VITE_DEV_PROTOCOL ?? 'http';
const hmrHost = process.env.VITE_DEV_HMR_HOST ?? outboundIPv4 ?? 'localhost';
const devOrigin = process.env.VITE_DEV_ORIGIN ?? `${devProtocol}://${hmrHost}:${devPort}`;

export default defineConfig({
    server: {
        host: devHost,
        port: devPort,
        strictPort: true,
        origin: devOrigin,
        cors: true,
        hmr: {
            host: hmrHost,
            port: devPort,
            protocol: devProtocol === 'https' ? 'wss' : 'ws',
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/design.css',
                'resources/css/login.css',
                'resources/css/onboarding.css',
                'resources/js/app.js',
                'resources/js/design.js',
                'resources/js/login.js',
                'resources/js/onboarding.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
