import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes, { setupGuard } from './router/index.js';

const router = createRouter({
    history: createWebHistory(),
    routes,
});

setupGuard(router);

createApp(App).use(router).mount('#app');
