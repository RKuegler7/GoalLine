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
                // Body text: Plus Jakarta Sans, the wordmark's typeface. Headings, scores and big numbers: Space
                // Grotesk. Both are downloaded at build time and served from the app (no font requests to anyone
                // else at runtime), with font-display: swap. Licenses: resources/fonts/OFL.txt, OFL-SpaceGrotesk.txt.
                // Only the weights the first screen needs are preloaded; the rest load when a page uses them.
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [{ weight: 400 }, { weight: 600 }],
                }),
                bunny('Space Grotesk', {
                    weights: [500, 600, 700],
                    preload: [{ weight: 700 }],
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
