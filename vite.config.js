import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * The magazine design's typefaces (ui_revamp_plan.md section 10),
             * downloaded at build time and
             * served from this origin. The Astro site linked Google Fonts,
             * which costs a DNS lookup and a connection to a third party on
             * every first visit, and sends the visitor's IP there too. Bunny
             * serves the same files; the build fetches them once.
             *
             * Preload is limited to the two variants above the fold on almost
             * every page: the display face for headings and the body face.
             * The mono face is only for code blocks in articles.
             */
            fonts: [
                bunny('Instrument Serif', {
                    weights: [400],
                    styles: ['normal', 'italic'],
                    preload: [{ weight: 400, style: 'normal' }],
                }),
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    preload: [{ weight: 400, style: 'normal' }],
                }),
                bunny('JetBrains Mono', {
                    weights: [400],
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
