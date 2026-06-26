import '../css/app.css';
import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes, { setupGuard } from './router/index.js';
import { setRouter } from './api.js';

const router = createRouter({
    history: createWebHistory(import.meta.env.VITE_APP_BASE ?? '/'),
    routes,
});

setupGuard(router);
setRouter(router);

// When a lazy-loaded chunk fails (stale cache after rebuild), force a full reload
router.onError((err, to) => {
    const isChunkError = err.message && (
        err.message.includes('Failed to fetch dynamically imported module') ||
        err.message.includes('Importing a module script failed') ||
        err.message.includes('Unable to preload CSS')
    );
    if (isChunkError) {
        window.location.href = to.fullPath;
    }
});

const app = createApp(App).use(router);

app.mount('#app');
