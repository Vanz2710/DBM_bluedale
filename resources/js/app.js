import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes, { setupGuard } from './router/index.js';

const basePath = window.__APP_BASE_PATH__ || '/';

const router = createRouter({
    history: createWebHistory(basePath === '/' ? '/' : `${basePath}/`),
    routes,
});

setupGuard(router);

createApp(App).use(router).mount('#app');
