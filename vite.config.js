import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

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
                output: {
                    manualChunks(id) {
                        if (id.includes('/node_modules/vue') || id.includes('/node_modules/vue-router')) {
                            return 'vue-vendor';
                        }
                        if (id.includes('/node_modules/axios')) return 'axios';
                        if (id.includes('/node_modules/chart.js')) return 'chart';
                        if (id.includes('/node_modules/lottie-web')) return 'lottie';
                        if (id.includes('/node_modules/@sentry')) return 'sentry';
                        if (id.includes('/node_modules/leaflet')) return 'leaflet';
                    },
                },
            },
        },
    };
});
