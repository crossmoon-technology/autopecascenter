import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
                'resources/js/informativo-preview.js',
                'resources/js/onboarding-tour.js',
                'resources/css/onboarding-tour.css',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/super-admin/theme.css',
                'resources/css/filament/client/theme.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'https://autopecascenter.local.com.br',
        hmr: {
            host: 'autopecascenter.local.com.br',
            protocol: 'wss',
            clientPort: 443,
            path: '@vite/hmr',
        },
    },
})
