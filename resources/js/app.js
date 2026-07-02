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

// Wait for the first navigation (and its guard) to complete before mounting,
// so the app never flashes a protected page before the login redirect fires.
// Falls back to mounting anyway on rejection — a stuck/failed first navigation
// must never leave the page permanently blank with no recovery path.
router.isReady().then(() => app.mount('#app'), () => app.mount('#app'));
