import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/chart.js'))   return 'vendor-chartjs';
                    if (id.includes('node_modules/lottie-web')) return 'vendor-lottie';
                    if (id.includes('node_modules/vue-router')) return 'vendor-vue';
                    if (id.includes('node_modules/vue'))        return 'vendor-vue';
                    if (id.includes('node_modules/axios'))      return 'vendor-axios';
                },
            },
        },
    },
});
