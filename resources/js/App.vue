<template>
  <!-- Login page renders without sidebar -->
  <template v-if="isLogin">
    <router-view />
  </template>

  <div v-else class="layout" :class="{ collapsed }">
    <!-- Sidebar -->
    <aside class="sidebar">
      <button class="sidebar-toggle" @click="collapsed = !collapsed" :title="collapsed ? 'Expand' : 'Collapse'">
        {{ collapsed ? '>' : '<' }}
      </button>

      <router-link to="/" class="sidebar-brand">
        <div class="brand-icon">B</div>
        <span class="brand-text">
          <span class="brand-name">Bluedale</span>
          <span class="brand-sub">Data System</span>
        </span>
      </router-link>

      <nav class="nav-section">
        <div class="nav-label">Main</div>
        <router-link to="/" class="nav-link" :class="navClass('home')">
          <span class="nav-icon">🏠</span><span class="nav-text">Dashboard</span>
        </router-link>
        <router-link to="/summary" class="nav-link" :class="navClass('summary')">
          <span class="nav-icon">📊</span><span class="nav-text">Summary</span>
        </router-link>
        <router-link to="/list" class="nav-link" :class="navClass('list')">
          <span class="nav-icon">🏢</span><span class="nav-text">Daily List</span>
        </router-link>
        <router-link to="/todos" class="nav-link" :class="navClass('todos')">
          <span class="nav-icon">📋</span><span class="nav-text">To Do List</span>
        </router-link>
        <router-link to="/crm" class="nav-link" :class="navClass('crm')">
          <span class="nav-icon">📁</span><span class="nav-text">CRM Dashboard</span>
        </router-link>
        <router-link to="/exhibitions" class="nav-link" :class="navClass('exhibitions')">
          <span class="nav-icon">🎪</span><span class="nav-text">Exhibitions</span>
        </router-link>
        <router-link to="/travel" class="nav-link" :class="navClass('travel')">
          <span class="nav-icon">✈</span><span class="nav-text">Travel Hub</span>
        </router-link>
        <router-link to="/social-media-reminders" class="nav-link" :class="navClass('social-media-reminders')">
          <span class="nav-icon">SM</span><span class="nav-text">Social Media</span>
        </router-link>
        <router-link to="/posting-calendar" class="nav-link" :class="navClass('posting-calendar')">
          <span class="nav-icon">PC</span><span class="nav-text">Posting Calendar</span>
        </router-link>
        <router-link to="/marketing-ai-demo" class="nav-link" :class="navClass('marketing-ai-demo')">
          <span class="nav-icon">MK</span><span class="nav-text">Marketing AI</span>
        </router-link>
        <router-link to="/marketing-email" class="nav-link" :class="navClass('marketing-email')">
          <span class="nav-icon">EM</span><span class="nav-text">Marketing Email</span>
        </router-link>
        <router-link to="/product-availability" class="nav-link" :class="navClass('product-availability')">
          <span class="nav-icon">AD</span><span class="nav-text">Product Availability</span>
        </router-link>
      </nav>

      <div class="sidebar-divider"></div>

      <nav class="nav-section">
        <div class="nav-label">Tools</div>
        <router-link to="/admin" class="nav-link" :class="navClass('admin')">
          <span class="nav-icon">⚙️</span><span class="nav-text">Admin Panel</span>
        </router-link>
        <router-link to="/import" class="nav-link" :class="navClass('import')">
          <span class="nav-icon">📥</span><span class="nav-text">Import Data</span>
        </router-link>
        <router-link to="/data-health" class="nav-link" :class="navClass('data-health')">
          <span class="nav-icon">🩺</span><span class="nav-text">Data Health</span>
        </router-link>
        <router-link to="/ai-workflow-demo" class="nav-link" :class="navClass('ai-workflow-demo')">
          <span class="nav-icon">AI</span><span class="nav-text">AI Demo</span>
        </router-link>
      </nav>

      <div class="sidebar-divider"></div>

      <div class="sidebar-user" v-if="currentUser">
        <div class="user-info">
          <span class="user-name nav-text">{{ currentUser.name }}</span>
          <span class="user-email nav-text">{{ currentUser.email }}</span>
        </div>
        <button class="btn-logout nav-text" @click="logout" title="Logout">↩ Logout</button>
      </div>

      <div class="sidebar-footer">Bluedale CRM Platform</div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from './api.js';

const route = useRoute();
const router = useRouter();
const collapsed = ref(localStorage.getItem('sidebarCollapsed') === '1');
const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));

const isLogin = computed(() => route.name === 'login');

watch(collapsed, (v) => localStorage.setItem('sidebarCollapsed', v ? '1' : '0'));

function navClass(name) {
  const matched = route.name ?? '';
  const greenRoutes  = ['summary', 'list', 'contact-view', 'contact-add', 'contact-edit', 'task-add'];
  const orangeRoutes = ['todos', 'todo-add', 'task-edit'];
  const purpleRoutes = ['admin'];
  const blueRoutes   = ['home', 'crm', 'crm-view'];
  const tealRoutes   = ['travel', 'travel-view'];

  if (name === 'home')        return blueRoutes.includes(matched)   ? 'active-blue'   : '';
  if (name === 'summary')     return matched === 'summary'           ? 'active-green'  : '';
  if (name === 'list')        return ['list', 'contact-view', 'contact-add', 'contact-edit', 'task-add'].includes(matched) ? 'active-green' : '';
  if (name === 'todos')       return ['todos', 'todo-add', 'task-edit'].includes(matched) ? 'active-orange' : '';
  if (name === 'crm')         return ['crm', 'crm-view'].includes(matched) ? 'active-blue' : '';
  if (name === 'exhibitions') return matched === 'exhibitions'       ? 'active-purple' : '';
  if (name === 'travel')      return tealRoutes.includes(matched)   ? 'active-teal'   : '';
  if (name === 'social-media-reminders') return matched === 'social-media-reminders' ? 'active-teal' : '';
  if (name === 'posting-calendar') return matched === 'posting-calendar' ? 'active-teal' : '';
  if (name === 'admin')       return matched === 'admin'            ? 'active-purple' : '';
  if (name === 'import')      return matched === 'import'           ? 'active-orange' : '';
  if (name === 'data-health') return matched === 'data-health'      ? 'active-orange' : '';
  if (name === 'ai-workflow-demo') return matched === 'ai-workflow-demo' ? 'active-teal' : '';
  if (name === 'marketing-ai-demo') return matched === 'marketing-ai-demo' ? 'active-purple' : '';
  if (name === 'marketing-email') return matched === 'marketing-email' ? 'active-teal' : '';
  if (name === 'product-availability') return matched === 'product-availability' ? 'active-orange' : '';
  return '';
}

async function logout() {
  try {
    await api.post('/auth/logout');
  } catch (_) { /* ignore */ }
  localStorage.removeItem('crm_token');
  localStorage.removeItem('crm_user');
  router.push('/login');
}
</script>

<script>
export default { name: 'App' };
</script>

<style>
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }

.layout { display: flex; min-height: 100vh; }
.layout.collapsed .sidebar { width: 76px; }
.layout.collapsed .brand-text,
.layout.collapsed .nav-label,
.layout.collapsed .nav-text,
.layout.collapsed .sidebar-footer,
.layout.collapsed .sidebar-user { display: none; }
.layout.collapsed .sidebar-toggle { right: 23px; }
.layout.collapsed .sidebar-brand { justify-content: center; padding: 22px 0 20px; }
.layout.collapsed .nav-link { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-icon { width: auto; font-size: 16px; }
.layout.collapsed .main-content { margin-left: 76px; }

.sidebar { position: fixed; left: 0; top: 0; width: 240px; height: 100vh; background: #0f172a;
  display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; z-index: 1000;
  border-right: 1px solid rgba(255,255,255,0.04); transition: width 0.2s ease; }

.sidebar-toggle { position: absolute; top: 14px; right: 12px; width: 30px; height: 30px;
  border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; background: rgba(255,255,255,0.05);
  color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 14px; z-index: 2; transition: background 0.15s; }
.sidebar-toggle:hover { background: rgba(255,255,255,0.1); color: #e2e8f0; }

.sidebar-brand { display: flex; align-items: center; gap: 11px; padding: 22px 18px 20px;
  text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.06); flex-shrink: 0; }
.brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg,#3b82f6,#1d4ed8);
  border-radius: 9px; display: flex; align-items: center; justify-content: center;
  font-size: 17px; font-weight: 800; color: white; flex-shrink: 0; }
.brand-text { display: flex; flex-direction: column; line-height: 1; }
.brand-name { font-size: 14px; font-weight: 700; color: #f1f5f9; }
.brand-sub  { font-size: 11px; color: #475569; margin-top: 3px; }

.nav-section { padding: 18px 10px 4px; }
.nav-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px;
  color: #334155; padding: 0 10px; margin-bottom: 6px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px;
  color: #64748b; text-decoration: none; font-size: 13.5px; font-weight: 500; margin-bottom: 1px;
  transition: background 0.15s, color 0.15s; position: relative; }
.nav-link:hover { background: rgba(255,255,255,0.05); color: #cbd5e1; }
.nav-icon { font-size: 15px; width: 22px; text-align: center; flex-shrink: 0; }

.active-blue   { background: rgba(59,130,246,0.14) !important; color: #93c5fd !important; font-weight: 600 !important; }
.active-blue::before   { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#3b82f6; }
.active-green  { background: rgba(34,197,94,0.14) !important; color: #86efac !important; font-weight: 600 !important; }
.active-green::before  { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#22c55e; }
.active-orange { background: rgba(249,115,22,0.14) !important; color: #fdba74 !important; font-weight: 600 !important; }
.active-orange::before { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#f97316; }
.active-purple { background: rgba(168,85,247,0.14) !important; color: #c4b5fd !important; font-weight: 600 !important; }
.active-purple::before { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#a855f7; }
.active-teal   { background: rgba(16,185,129,0.14) !important; color: #6ee7b7 !important; font-weight: 600 !important; }
.active-teal::before   { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#10b981; }

.sidebar-divider { height:1px; background:rgba(255,255,255,0.05); margin:10px 16px; }

.sidebar-user { padding: 12px 18px; }
.user-info { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.user-name { font-size: 13px; font-weight: 600; color: #cbd5e1; }
.user-email { font-size: 11px; color: #475569; }
.btn-logout { width: 100%; height: 32px; background: rgba(239,68,68,0.12); color: #f87171; border: none; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; text-align: left; padding: 0 10px; }
.btn-logout:hover { background: rgba(239,68,68,0.22); }

.sidebar-footer { margin-top:auto; padding:16px 18px; border-top:1px solid rgba(255,255,255,0.05);
  font-size:11px; color:#1e293b; }

.main-content { margin-left: 240px; flex: 1; transition: margin-left 0.2s ease; min-height: 100vh; }
</style>
