import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
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
