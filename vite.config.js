import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // The wordmark is lettered in Plus Jakarta Sans Bold, so the whole app speaks the same type. Downloaded
                // at build time and served from the app (no font requests to anyone else at runtime), with
                // font-display: swap. Only the weights the first screen needs are preloaded; the rest load when used.
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [{ weight: 400 }, { weight: 600 }, { weight: 700 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
