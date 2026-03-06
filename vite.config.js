import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'],
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
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'vue': 'vue/dist/vue.esm-bundler.js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('vue') || id.includes('pinia')) {
                            return 'vendor-core';
                        }
                        return 'vendor-lib';
                    }
                    if (id.includes('resources/js/components/Overlay') ||
                        id.includes('resources/js/components/Market') ||
                        id.includes('resources/js/components/Game')) {
                        return 'game-ui';
                    }
                }
            }
        },
        chunkSizeWarningLimit: 1000,
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'https://dev.codepony.de:5173',
        hmr: {
            host: 'dev.codepony.de',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
