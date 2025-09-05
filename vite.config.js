/**
 * TODO: Vite Configuration
 * 
 * Requirements from specification:
 * - Single Build: Vite bundles the SPA served from /public
 * - Vue 3 SPA support
 * - Asset compilation and bundling
 */

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
