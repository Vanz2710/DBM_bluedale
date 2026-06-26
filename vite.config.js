import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // InfinityFree serves .js with the wrong MIME type, so ES modules fail there.
    // Set VITE_IIFE=1 to build a single inlined IIFE bundle for InfinityFree uploads.
    // Leave it unset for the normal code-split build used locally / on proper hosts.
    const iife = ['1', 'true'].includes((env.VITE_IIFE || '').toLowerCase());

    return {
        base: env.VITE_BASE_URL || '/build/',
        plugins: [
            laravel({
                input: ['resources/js/app.js'],
                refresh: true,
            }),
            vue(),
        ],
        build: {
            rollupOptions: {
                output: iife
                    ? {
                        format: 'iife',
                        inlineDynamicImports: true,
                        entryFileNames: 'assets/[name]-[hash].js',
                        assetFileNames: 'assets/[name]-[hash][extname]',
                    }
                    : {
                        manualChunks(id) {
                            if (id.includes('/node_modules/vue') || id.includes('/node_modules/vue-router')) {
                                return 'vue-vendor';
                            }
                            if (id.includes('/node_modules/axios')) return 'axios';
                            if (id.includes('/node_modules/chart.js')) return 'chart';
                            if (id.includes('/node_modules/lottie-web')) return 'lottie';
                            if (id.includes('/node_modules/leaflet')) return 'leaflet';
                        },
                    },
            },
        },
    };
});
