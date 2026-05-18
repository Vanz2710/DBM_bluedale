import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes, { setupGuard } from './router/index.js';
import { setRouter } from './api.js';

const router = createRouter({
    history: createWebHistory(),
    routes,
});

setupGuard(router);
setRouter(router);

createApp(App).use(router).mount('#app');
