<template>
  <!-- Login page renders without sidebar -->
  <template v-if="isLogin">
    <router-view />
  </template>

  <div v-else class="layout" :class="{ collapsed, 'mobile-open': mobileOpen }">
    <!-- Mobile overlay backdrop -->
    <div class="mobile-overlay" @click="mobileOpen = false"></div>
    <!-- Sidebar -->
    <aside class="sidebar">
      <button class="sidebar-toggle" @click="collapsed = !collapsed" :title="collapsed ? 'Expand' : 'Collapse'" v-html="collapsed ? SVGI.chevronRight : SVGI.chevronLeft"></button>

      <router-link to="/" class="sidebar-brand">
        <svg class="brand-logo" viewBox="0 0 160 48" xmlns="http://www.w3.org/2000/svg" aria-label="Bluedale Group of Companies">
          <text x="80" y="28" text-anchor="middle" font-family="'Segoe UI', Arial, sans-serif" font-size="22" font-weight="800" letter-spacing="2" fill="#f1f5f9">BLUEDALE</text>
          <line x1="10" y1="32" x2="150" y2="32" stroke="#3b82f6" stroke-width="1.5"/>
          <text x="80" y="43" text-anchor="middle" font-family="'Segoe UI', Arial, sans-serif" font-size="8" font-weight="600" letter-spacing="2.5" fill="#94a3b8">GROUP OF COMPANIES</text>
        </svg>
      </router-link>

      <!-- Main section -->
      <nav class="nav-section">
        <div class="nav-label">Main</div>
        <div v-for="group in mainGroups" :key="group.key" class="nav-group">
          <button class="nav-group-header" :class="groupHeaderClass(group)" @click="toggleGroup(group.key)">
            <span class="nav-icon" v-html="group.icon"></span>
            <span class="nav-text">{{ group.label }}</span>
            <span class="nav-arrow nav-text" :class="{ open: openGroups[group.key] }">›</span>
          </button>
          <Transition name="nav-menu">
            <div v-show="openGroups[group.key] && !collapsed" class="nav-group-slide">
              <div class="nav-group-items">
                <router-link
                  v-for="item in group.items"
                  :key="item.key"
                  :to="item.to"
                  class="nav-link nav-sub"
                  :class="itemClass(item, group)"
                >
                  <span class="nav-icon" v-html="item.icon"></span>
                  <span class="nav-text">{{ item.label }}</span>
                </router-link>
              </div>
            </div>
          </Transition>
        </div>
      </nav>

      <div class="sidebar-divider"></div>

      <!-- Tools section -->
      <nav class="nav-section">
        <div class="nav-label">Tools</div>
        <div v-for="group in toolGroups" :key="group.key" class="nav-group">
          <button class="nav-group-header" :class="groupHeaderClass(group)" @click="toggleGroup(group.key)">
            <span class="nav-icon" v-html="group.icon"></span>
            <span class="nav-text">{{ group.label }}</span>
            <span class="nav-arrow nav-text" :class="{ open: openGroups[group.key] }">›</span>
          </button>
          <Transition name="nav-menu">
            <div v-show="openGroups[group.key] && !collapsed" class="nav-group-slide">
              <div class="nav-group-items">
                <router-link
                  v-for="item in group.items"
                  :key="item.key"
                  :to="item.to"
                  class="nav-link nav-sub"
                  :class="itemClass(item, group)"
                >
                  <span class="nav-icon" v-html="item.icon"></span>
                  <span class="nav-text">{{ item.label }}</span>
                </router-link>
              </div>
            </div>
          </Transition>
        </div>
      </nav>

      <div class="sidebar-divider"></div>

      <div class="sidebar-user" v-if="currentUser">
        <div class="user-info">
          <span class="user-name nav-text">{{ currentUser.name }}</span>
          <span class="user-email nav-text">{{ currentUser.email }}</span>
        </div>
        <router-link to="/profile" class="btn-profile nav-text"><span v-html="SVGI.user"></span> My Profile</router-link>
        <button class="btn-logout nav-text" @click="logout" title="Logout"><span v-html="SVGI.logout"></span> Logout</button>
      </div>

      <div class="sidebar-footer">Bluedale CRM Platform</div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <div class="app-topbar">
        <button class="hamburger-btn" @click="mobileOpen = !mobileOpen" v-html="SVGI.menu"></button>
        <span class="mobile-title">Bluedale</span>
        <div class="topbar-right">
          <NotificationBell v-if="!isLogin" />
        </div>
      </div>
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from './api.js';
import NotificationBell from './components/NotificationBell.vue';
import { applyTheme, useSettings } from './composables/useSettings.js';

const route = useRoute();
const router = useRouter();
const collapsed = ref(localStorage.getItem('sidebarCollapsed') === '1');
const mobileOpen = ref(false);
const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));

const isLogin = computed(() => ['login', 'verify-email'].includes(route.name));
const isAdminOrSuperAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

watch(collapsed, (v) => localStorage.setItem('sidebarCollapsed', v ? '1' : '0'));

const { loadFromServer } = useSettings();

onMounted(() => {
  window.addEventListener('user-profile-updated', () => {
    currentUser.value = JSON.parse(localStorage.getItem('crm_user') || 'null');
  });
  // Sync user settings from server once on app mount (no-op if not logged in)
  if (localStorage.getItem('crm_token')) loadFromServer();
});

// ─── Navigation icons ─────────────────────────────────────────────────────────
const _s = (p) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const SVGI = {
  home:         _s('<path d="M3 12L12 3l9 9M5 10v9a1 1 0 0 0 1 1h4v-5h4v5h4a1 1 0 0 0 1-1v-9"/>'),
  chart:        _s('<rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>'),
  folder:       _s('<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 2h9a2 2 0 0 1 2 2z"/>'),
  list:         _s('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
  bell:         _s('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'),
  layers:       _s('<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>'),
  briefcase:    _s('<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>'),
  clipboard:    _s('<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/>'),
  trending:     _s('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'),
  gear:         _s('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
  shield:       _s('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>'),
  target:       _s('<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'),
  download:     _s('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'),
  activity:     _s('<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'),
  chevronRight: _s('<polyline points="9 18 15 12 9 6"/>'),
  chevronLeft:  _s('<polyline points="15 18 9 12 15 6"/>'),
  menu:         _s('<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>'),
  logout:       _s('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>'),
  user:         _s('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
  sliders:      _s('<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>'),
};

// ─── Navigation config ────────────────────────────────────────────────────────
const ALL_GROUPS = [
  {
    key: 'overview', label: 'Overview', icon: SVGI.home, color: 'blue', section: 'main', adminOnly: false,
    items: [
      { key: 'home',    to: '/',         icon: SVGI.home,  label: 'Dashboard', activeRoutes: ['home'] },
      { key: 'summary', to: '/summary',  icon: SVGI.chart, label: 'Summary',   activeRoutes: ['summary'] },
      { key: 'reports', to: '/reports',  icon: SVGI.chart, label: 'Reports',   activeRoutes: ['reports'] },
    ],
  },
  {
    key: 'crm-contacts', label: 'CRM & Contacts', icon: SVGI.folder, color: 'green', section: 'main', adminOnly: false,
    items: [
      { key: 'list',      to: '/list',      icon: SVGI.list,      label: 'Daily List',    activeRoutes: ['list', 'contact-view', 'contact-add', 'contact-edit', 'task-add'] },
      { key: 'crm',       to: '/crm',       icon: SVGI.folder,    label: 'CRM Dashboard', activeRoutes: ['crm', 'crm-view'] },
      { key: 'followups', to: '/followups', icon: SVGI.bell,      label: 'Follow-Ups',    activeRoutes: ['followups', 'followup-add', 'followup-edit'] },
      { key: 'projects',  to: '/projects',  icon: SVGI.layers,    label: 'Projects',      activeRoutes: ['projects', 'project-add', 'project-edit'] },
      { key: 'deals',     to: '/deals',     icon: SVGI.briefcase, label: 'Deals',         activeRoutes: ['deals', 'deal-add', 'deal-edit'] },
    ],
  },
  {
    key: 'tasks', label: 'Tasks & Performance', icon: SVGI.clipboard, color: 'orange', section: 'main', adminOnly: false,
    items: [
      { key: 'todos',       to: '/todos',       icon: SVGI.clipboard, label: 'To Do List',  activeRoutes: ['todos', 'todo-add', 'task-edit'] },
      { key: 'reminders',   to: '/reminders',   icon: SVGI.bell,      label: 'Reminders',   activeRoutes: ['reminders'] },
      { key: 'performance', to: '/performance', icon: SVGI.trending,  label: 'Performance', activeRoutes: ['performance'] },
    ],
  },
  {
    key: 'admin', label: 'Administration', icon: SVGI.gear, color: 'purple', section: 'tools', adminOnly: true,
    items: [
      { key: 'admin-panel',  to: '/admin',                     icon: SVGI.gear,   label: 'Lookup Settings', activeRoutes: ['admin'] },
      { key: 'rbac',         to: '/admin/rbac',                icon: SVGI.shield, label: 'Access Control', activeRoutes: ['rbac'] },
      { key: 'perf-targets', to: '/admin/performance-targets', icon: SVGI.target,    label: 'Perf. Targets',  activeRoutes: ['perf-targets'] },
      { key: 'webhooks',    to: '/admin/webhooks',             icon: SVGI.activity, label: 'Webhooks',       activeRoutes: ['webhooks'] },
    ],
  },
  {
    key: 'data', label: 'Data Management', icon: SVGI.download, color: 'orange', section: 'tools', adminOnly: false,
    items: [
      { key: 'import',      to: '/import',      icon: SVGI.download, label: 'Import Data', activeRoutes: ['import'] },
      { key: 'data-health', to: '/data-health', icon: SVGI.activity, label: 'Data Health', activeRoutes: ['data-health'] },
    ],
  },
  {
    key: 'preferences', label: 'Preferences', icon: SVGI.sliders, color: 'teal', section: 'tools', adminOnly: false,
    items: [
      { key: 'settings', to: '/settings', icon: SVGI.sliders, label: 'Settings', activeRoutes: ['settings'] },
    ],
  },
];

const mainGroups = computed(() =>
  ALL_GROUPS.filter(g => g.section === 'main' && (!g.adminOnly || isAdminOrSuperAdmin.value))
);
const toolGroups = computed(() =>
  ALL_GROUPS.filter(g => g.section === 'tools' && (!g.adminOnly || isAdminOrSuperAdmin.value))
);

// ─── Group open/close state ───────────────────────────────────────────────────
const openGroups = reactive(Object.fromEntries(ALL_GROUPS.map(g => [g.key, false])));
const activeRouteName = computed(() => route.name ?? '');
const activeGroupKeys = computed(() => {
  const keys = new Set();

  for (const group of ALL_GROUPS) {
    if (group.items.some(item => item.activeRoutes.includes(activeRouteName.value))) {
      keys.add(group.key);
    }
  }

  return keys;
});
const activeItemKeys = computed(() => {
  const keys = new Set();

  for (const group of ALL_GROUPS) {
    for (const item of group.items) {
      if (item.activeRoutes.includes(activeRouteName.value)) {
        keys.add(item.key);
      }
    }
  }

  return keys;
});

function groupHeaderClass(group) {
  return activeGroupKeys.value.has(group.key) ? `group-active-${group.color}` : '';
}

function itemClass(item, group) {
  return activeItemKeys.value.has(item.key) ? `active-${group.color}` : '';
}

function toggleGroup(key) {
  if (collapsed.value) {
    collapsed.value = false;
    openGroups[key] = true;
    return;
  }
  openGroups[key] = !openGroups[key];
}

// Auto-open the group that owns the current route
watch(route, (newRoute) => {
  mobileOpen.value = false;
  currentUser.value = JSON.parse(localStorage.getItem('crm_user') || 'null');
  const routeName = newRoute.name ?? '';
  for (const group of ALL_GROUPS) {
    if (group.items.some(item => item.activeRoutes.includes(routeName))) {
      openGroups[group.key] = true;
    }
  }
}, { immediate: true });

// ─── Auth ─────────────────────────────────────────────────────────────────────
async function logout() {
  try { await api.post('/auth/logout'); } catch (_) { /* ignore */ }
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

:root {
  --topbar-h: 47px;
  --app-bg:        #f0f2f5;
  --surface:       #ffffff;
  --border:        #e2e8f0;
  --text-1:        #1e293b;
  --text-2:        #64748b;
  --text-3:        #94a3b8;
  --topbar-bg:     #ffffff;
  --topbar-border: #e2e8f0;
}
[data-theme="dark"] {
  --app-bg:        #0f172a;
  --surface:       #1e293b;
  --border:        #334155;
  --text-1:        #f1f5f9;
  --text-2:        #94a3b8;
  --text-3:        #475569;
  --topbar-bg:     #1e293b;
  --topbar-border: #334155;
}

body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--app-bg); transition: background 0.2s; }

.layout { display: flex; min-height: 100vh; }
.layout.collapsed .sidebar { width: 76px; }
.layout.collapsed .nav-label,
.layout.collapsed .nav-text,
.layout.collapsed .sidebar-footer,
.layout.collapsed .sidebar-user { display: none; }
.layout.collapsed .sidebar-toggle { right: 23px; }
.layout.collapsed .sidebar-brand { justify-content: center; padding: 16px 0 14px; }
.layout.collapsed .brand-logo { width: 40px; height: 40px; }
.layout.collapsed .nav-group-header { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-link { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-icon { width: auto; }
.layout.collapsed .main-content { margin-left: 76px; }

.sidebar { position: fixed; left: 0; top: 0; width: 240px; height: 100vh; background: #0f172a;
  display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; z-index: 1000;
  border-right: 1px solid rgba(255,255,255,0.04); transition: width 0.2s ease, transform 0.25s ease;
  scrollbar-width: none; -ms-overflow-style: none; }
.sidebar::-webkit-scrollbar { display: none; }

.sidebar-toggle { position: absolute; top: 14px; right: 12px; width: 30px; height: 30px;
  border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; background: rgba(255,255,255,0.05);
  color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 14px; z-index: 2; transition: background 0.15s; }
.sidebar-toggle:hover { background: rgba(255,255,255,0.1); color: #e2e8f0; }

.sidebar-brand { display: flex; align-items: center; justify-content: center; padding: 18px 14px 16px;
  text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.06); flex-shrink: 0; }
.brand-logo { width: 148px; height: 46px; flex-shrink: 0; transition: width 0.2s ease, height 0.2s ease; }

.nav-section { padding: 18px 10px 4px; }
.nav-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px;
  color: #334155; padding: 0 10px; margin-bottom: 6px; }

.nav-group { margin-bottom: 1px; }
.nav-group-header { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px;
  color: #64748b; background: none; border: none; cursor: pointer; font-size: 13.5px; font-weight: 500;
  width: 100%; text-align: left; transition: background 0.15s, color 0.15s; position: relative; }
.nav-group-header:hover { background: rgba(255,255,255,0.05); color: #cbd5e1; }

.nav-arrow { margin-left: auto; font-size: 16px; line-height: 1; transition: transform 0.2s ease; display: inline-block; }
.nav-arrow.open { transform: rotate(90deg); }

.nav-group-slide { overflow: hidden; transform-origin: top; will-change: opacity, transform; }
.nav-menu-enter-active,
.nav-menu-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.nav-menu-enter-from,
.nav-menu-leave-to { opacity: 0; transform: translate3d(0,-4px,0) scaleY(0.98); }
.nav-menu-enter-to,
.nav-menu-leave-from { opacity: 1; transform: translate3d(0,0,0) scaleY(1); }
.nav-menu-leave-active { pointer-events: none; }
.nav-group-items { overflow: hidden; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px;
  color: #64748b; text-decoration: none; font-size: 13.5px; font-weight: 500; margin-bottom: 1px;
  transition: background 0.15s, color 0.15s; position: relative; }
.nav-link:hover { background: rgba(255,255,255,0.05); color: #cbd5e1; }
.nav-sub { padding-left: 22px; font-size: 13px; }
.nav-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* Active item states */
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
.active-rose   { background: rgba(225,29,72,0.14) !important; color: #fda4af !important; font-weight: 600 !important; }
.active-rose::before   { content:''; position:absolute; left:0; top:25%; height:50%; width:3px; border-radius:0 3px 3px 0; background:#e11d48; }

/* Active group header colors */
.group-active-blue   { color: #93c5fd !important; font-weight: 600 !important; }
.group-active-green  { color: #86efac !important; font-weight: 600 !important; }
.group-active-orange { color: #fdba74 !important; font-weight: 600 !important; }
.group-active-purple { color: #c4b5fd !important; font-weight: 600 !important; }
.group-active-teal   { color: #6ee7b7 !important; font-weight: 600 !important; }
.group-active-rose   { color: #fda4af !important; font-weight: 600 !important; }

.sidebar-divider { height:1px; background:rgba(255,255,255,0.05); margin:10px 16px; }

.sidebar-user { padding: 12px 18px; }
.user-info { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.user-name { font-size: 13px; font-weight: 600; color: #cbd5e1; }
.user-email { font-size: 11px; color: #475569; }
.btn-profile { width: 100%; height: 32px; background: rgba(59,130,246,0.12); color: #93c5fd; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0 10px; text-decoration: none; margin-bottom: 6px; }
.btn-profile:hover { background: rgba(59,130,246,0.22); }
.btn-logout { width: 100%; height: 32px; background: rgba(239,68,68,0.12); color: #f87171; border: none; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0 10px; }
.btn-logout:hover { background: rgba(239,68,68,0.22); }

.sidebar-footer { margin-top:auto; padding:16px 18px; border-top:1px solid rgba(255,255,255,0.05);
  font-size:11px; color:#1e293b; }

.main-content { margin-left: 240px; flex: 1; transition: margin-left 0.2s ease; min-height: 100vh; }

.mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; }

.app-topbar {
  display: flex; align-items: center; gap: 10px; padding: 8px 16px;
  background: var(--topbar-bg); border-bottom: 1px solid var(--topbar-border);
  position: sticky; top: 0; z-index: 100; transition: background 0.2s, border-color 0.2s;
}
.topbar-right { margin-left: auto; display: flex; align-items: center; }
.hamburger-btn {
  background: none; border: 1.5px solid var(--border); border-radius: 8px; width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; color: var(--text-1); flex-shrink: 0;
}
.hamburger-btn:hover { background: var(--app-bg); }
.mobile-title { font-size: 15px; font-weight: 700; color: var(--text-1); }
/* Desktop: hide hamburger and brand title */
@media (min-width: 641px) {
  .hamburger-btn { display: none; }
  .mobile-title  { display: none; }
}

/* Tablet (641px–1023px) */
@media (min-width: 641px) and (max-width: 1023px) {
  .sidebar { width: 76px; }
  .nav-label, .nav-text, .sidebar-footer, .sidebar-user { display: none !important; }
  .brand-logo { width: 40px; height: 40px; }
  .sidebar-toggle { right: 23px; }
  .sidebar-brand { justify-content: center; padding: 16px 0 14px; }
  .nav-group-header { justify-content: center; padding: 10px 0; }
  .nav-link { justify-content: center; padding: 10px 0; }
  .nav-icon { width: auto !important; }
  .main-content { margin-left: 76px; }
  .layout.collapsed .main-content { margin-left: 76px; }
}

/* Mobile (≤640px) */
@media (max-width: 640px) {
  .sidebar,
  .layout.collapsed .sidebar { width: 240px !important; transform: translateX(-240px) !important; }
  .layout.mobile-open .sidebar,
  .layout.collapsed.mobile-open .sidebar { transform: translateX(0) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.3); }
  .layout.mobile-open .mobile-overlay,
  .layout.collapsed.mobile-open .mobile-overlay { display: block; }
  .layout.mobile-open .nav-text,
  .layout.collapsed.mobile-open .nav-text,
  .layout.mobile-open .nav-label,
  .layout.collapsed.mobile-open .nav-label,
  .layout.mobile-open .sidebar-footer,
  .layout.collapsed.mobile-open .sidebar-footer { display: block !important; }
  .layout.mobile-open .sidebar-user,
  .layout.collapsed.mobile-open .sidebar-user { display: flex !important; }
  .layout.mobile-open .sidebar-brand,
  .layout.collapsed.mobile-open .sidebar-brand { justify-content: center !important; padding: 18px 14px 16px !important; }
  .layout.mobile-open .brand-logo,
  .layout.collapsed.mobile-open .brand-logo { width: 148px !important; height: 46px !important; }
  .layout.mobile-open .nav-group-header,
  .layout.collapsed.mobile-open .nav-group-header { justify-content: flex-start !important; padding: 9px 10px !important; }
  .layout.mobile-open .nav-link,
  .layout.collapsed.mobile-open .nav-link { justify-content: flex-start !important; padding: 9px 10px !important; }
  .layout.mobile-open .nav-sub,
  .layout.collapsed.mobile-open .nav-sub { padding-left: 22px !important; }
  .layout.mobile-open .nav-icon,
  .layout.collapsed.mobile-open .nav-icon { width: 22px !important; font-size: 15px !important; }
  .main-content,
  .layout.collapsed .main-content { margin-left: 0 !important; }
  .sidebar-toggle { display: none !important; }
}
</style>
