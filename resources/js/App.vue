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

      <router-link to="/" class="sidebar-brand" aria-label="Bluedale CRM">
        <span class="brand-mark" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" opacity="0.9"/>
            <rect x="13" y="2" width="9" height="9" rx="2" fill="currentColor" opacity="0.55"/>
            <rect x="2" y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.55"/>
            <rect x="13" y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.9"/>
          </svg>
        </span>
        <span class="brand-text nav-text">
          <span class="brand-text-main">Bluedale</span><span class="brand-text-accent">CRM</span>
        </span>
      </router-link>

      <!-- Main section -->
      <nav class="nav-section">
        <div class="nav-label">General</div>
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
  megaphone:    _s('<path d="M3 11l19-9-9 19-2-8-8-2z"/>'),
  calendar:     _s('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'),
  mail:         _s('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'),
  grid:         _s('<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'),
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
      { key: 'reports', to: '/reports',  icon: SVGI.chart, label: 'Reports',   activeRoutes: ['reports'] },
    ],
  },
  {
    key: 'crm-contacts', label: 'CRM & Contacts', icon: SVGI.folder, color: 'green', section: 'main', adminOnly: false,
    items: [
      { key: 'list',      to: '/list',      icon: SVGI.list,      label: 'Contacts',      activeRoutes: ['list', 'contact-view', 'contact-add', 'contact-edit', 'task-add'] },
      { key: 'projects',  to: '/projects',  icon: SVGI.layers,    label: 'Projects',      activeRoutes: ['projects', 'project-add', 'project-edit'] },
      { key: 'deals',     to: '/deals',     icon: SVGI.briefcase, label: 'Deals (demo)', activeRoutes: ['deals', 'deal-add', 'deal-edit'] },
      { key: 'forecasts', to: '/forecasts', icon: SVGI.trending,  label: 'Forecasts',     activeRoutes: ['forecasts', 'forecast-summary'] },
    ],
  },
  {
    key: 'tasks', label: 'Tasks & Performance', icon: SVGI.clipboard, color: 'orange', section: 'main', adminOnly: false,
    items: [
      { key: 'todos',       to: '/todos',       icon: SVGI.clipboard, label: 'To Do List',  activeRoutes: ['todos', 'todo-add', 'task-edit'] },
      { key: 'followups',   to: '/followups',   icon: SVGI.bell,      label: 'Follow-Ups',  activeRoutes: ['followups', 'followup-add', 'followup-edit'] },
      { key: 'reminders',   to: '/reminders',   icon: SVGI.bell,      label: 'Reminders',   activeRoutes: ['reminders'] },
      { key: 'performance', to: '/performance', icon: SVGI.trending,  label: 'Performance', activeRoutes: ['performance'] },
    ],
  },
  {
    key: 'marketing', label: 'Marketing & Media', icon: SVGI.megaphone, color: 'teal', section: 'main', adminOnly: false,
    items: [
      { key: 'social-media',         to: '/social-media',         icon: SVGI.megaphone, label: 'Social Media',         activeRoutes: ['social-media'] },
      { key: 'posting-calendar',     to: '/posting-calendar',     icon: SVGI.calendar,  label: 'Posting Calendar',     activeRoutes: ['posting-calendar'] },
      { key: 'marketing-email',      to: '/marketing-email',      icon: SVGI.mail,      label: 'Email Marketing',      activeRoutes: ['marketing-email'] },
      { key: 'product-availability', to: '/product-availability', icon: SVGI.grid,      label: 'Product Availability', activeRoutes: ['product-availability'] },
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
  --app-bg:        #f6f7fb;
  --surface:       #ffffff;
  --surface-2:     #f9fafb;
  --border:        #e5e7eb;
  --border-soft:   #eef0f4;
  --text-1:        #1e293b;
  --text-2:        #64748b;
  --text-3:        #94a3b8;
  --topbar-bg:     #ffffff;
  --topbar-border: #eceef3;

  /* Accent (purple, matches swiftCRM image) */
  --primary:        #7c3aed;
  --primary-hover:  #6d28d9;
  --primary-soft:   #ede9fe;
  --primary-on:     #ffffff;
  --primary-text:   #4c1d95;
  --focus-ring:     rgba(124,58,237,0.35);

  /* Semantic */
  --success:        #16a34a;
  --success-soft:   #dcfce7;
  --danger:         #ef4444;
  --danger-soft:    #fee2e2;
  --warning:        #f59e0b;
  --warning-soft:   #fef3c7;
  --info:           #0ea5e9;
  --info-soft:      #e0f2fe;

  /* Shape & elevation */
  --radius-sm:  6px;
  --radius:     10px;
  --radius-lg:  14px;
  --radius-xl:  20px;
  --shadow-xs:  0 1px 2px rgba(15,23,42,0.04);
  --shadow-sm:  0 1px 2px rgba(15,23,42,0.04), 0 1px 3px rgba(15,23,42,0.06);
  --shadow-md:  0 4px 6px -1px rgba(15,23,42,0.08), 0 2px 4px -2px rgba(15,23,42,0.05);
  --shadow-lg:  0 10px 15px -3px rgba(15,23,42,0.10), 0 4px 6px -4px rgba(15,23,42,0.06);

  /* Sidebar (swiftCRM-inspired light theme) */
  --sb-bg:             #ffffff;
  --sb-border:         #eceef3;
  --sb-brand-1:        #1e1b4b;
  --sb-brand-2:        #7c3aed;
  --sb-label:          #9ca3af;
  --sb-item:           #4b5563;
  --sb-item-icon:      #6b7280;
  --sb-item-hover-bg:  #f3f4f6;
  --sb-item-hover:     #1f2937;
  --sb-active-bg:      #ede9fe;
  --sb-active-text:    #4c1d95;
  --sb-active-icon:    #7c3aed;
  --sb-divider:        #eef0f4;
  --sb-toggle-bg:      #f3f4f6;
  --sb-toggle-border:  #e5e7eb;
  --sb-toggle-icon:    #6b7280;
}
[data-theme="dark"] {
  --app-bg:        #0f172a;
  --surface:       #1e293b;
  --surface-2:     #172033;
  --border:        #334155;
  --border-soft:   #243049;
  --text-1:        #f1f5f9;
  --text-2:        #94a3b8;
  --text-3:        #475569;
  --topbar-bg:     #1e293b;
  --topbar-border: #334155;

  --primary:        #a78bfa;
  --primary-hover:  #8b5cf6;
  --primary-soft:   rgba(139,92,246,0.18);
  --primary-on:     #0f172a;
  --primary-text:   #c4b5fd;
  --focus-ring:     rgba(167,139,250,0.45);

  --success-soft:   rgba(34,197,94,0.16);
  --danger-soft:    rgba(239,68,68,0.16);
  --warning-soft:   rgba(245,158,11,0.16);
  --info-soft:      rgba(14,165,233,0.16);

  --shadow-xs:  0 1px 2px rgba(0,0,0,0.30);
  --shadow-sm:  0 1px 2px rgba(0,0,0,0.30), 0 1px 3px rgba(0,0,0,0.35);
  --shadow-md:  0 4px 6px -1px rgba(0,0,0,0.40), 0 2px 4px -2px rgba(0,0,0,0.30);
  --shadow-lg:  0 10px 15px -3px rgba(0,0,0,0.45), 0 4px 6px -4px rgba(0,0,0,0.35);

  --sb-bg:             #0f172a;
  --sb-border:         rgba(255,255,255,0.06);
  --sb-brand-1:        #f1f5f9;
  --sb-brand-2:        #a78bfa;
  --sb-label:          #475569;
  --sb-item:           #94a3b8;
  --sb-item-icon:      #94a3b8;
  --sb-item-hover-bg:  rgba(255,255,255,0.05);
  --sb-item-hover:     #e2e8f0;
  --sb-active-bg:      rgba(139,92,246,0.18);
  --sb-active-text:    #c4b5fd;
  --sb-active-icon:    #a78bfa;
  --sb-divider:        rgba(255,255,255,0.06);
  --sb-toggle-bg:      rgba(255,255,255,0.05);
  --sb-toggle-border:  rgba(255,255,255,0.08);
  --sb-toggle-icon:    #94a3b8;
}

body { margin: 0; font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: var(--app-bg); color: var(--text-1);
  transition: background 0.2s, color 0.2s;
  -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }

/* Text selection */
::selection { background: var(--primary-soft); color: var(--primary-text); }

/* Links default to primary accent (pages can still override per-component) */
a { color: var(--primary); }
a:hover { color: var(--primary-hover); }

/* Unified focus ring for interactive controls (uses each element's own radius) */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[role="button"]:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--focus-ring);
}

/* Custom scrollbar (webkit) — subtle, theme-aware */
* { scrollbar-width: thin; scrollbar-color: var(--border) transparent; }
*::-webkit-scrollbar { width: 10px; height: 10px; }
*::-webkit-scrollbar-track { background: transparent; }
*::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; border: 2px solid var(--app-bg); }
*::-webkit-scrollbar-thumb:hover { background: var(--text-3); }

.layout { display: flex; min-height: 100vh; }
.layout.collapsed .sidebar { width: 76px; }
.layout.collapsed .nav-label,
.layout.collapsed .nav-text,
.layout.collapsed .sidebar-footer,
.layout.collapsed .sidebar-user { display: none; }
.layout.collapsed .sidebar-toggle { right: 23px; top: 12px; }
.layout.collapsed .sidebar-brand { justify-content: center; padding: 16px 0 14px; margin-right: 0; }
.layout.collapsed .brand-mark { width: 36px; height: 36px; }
.layout.collapsed .brand-mark svg { width: 22px; height: 22px; }
.layout.collapsed .nav-group-header { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-link { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-icon { width: auto; }
.layout.collapsed .main-content { margin-left: 76px; }

.sidebar { position: fixed; left: 0; top: 0; width: 248px; height: 100vh; background: var(--sb-bg);
  display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; z-index: 1000;
  border-right: 1px solid var(--sb-border);
  transition: width 0.2s ease, transform 0.25s ease, background 0.2s, border-color 0.2s;
  scrollbar-width: none; -ms-overflow-style: none; padding: 8px 0; }
.sidebar::-webkit-scrollbar { display: none; }

.sidebar-toggle { position: absolute; top: 16px; right: 12px; width: 28px; height: 28px;
  border: 1px solid var(--sb-toggle-border); border-radius: 8px; background: var(--sb-toggle-bg);
  color: var(--sb-toggle-icon); cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 14px; z-index: 2; transition: background 0.15s, color 0.15s, border-color 0.15s; }
.sidebar-toggle:hover { background: var(--sb-active-bg); color: var(--sb-active-icon); border-color: var(--sb-active-bg); }

.sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 14px 18px 16px;
  text-decoration: none; flex-shrink: 0; margin-right: 36px; }
.brand-mark { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center;
  border-radius: 8px; background: var(--sb-active-bg); color: var(--sb-brand-2); flex-shrink: 0; }
.brand-text { display: inline-flex; align-items: baseline; font-size: 18px; font-weight: 800;
  letter-spacing: -0.2px; line-height: 1; white-space: nowrap; }
.brand-text-main { color: var(--sb-brand-1); }
.brand-text-accent { color: var(--sb-brand-2); }

.nav-section { padding: 14px 12px 4px; }
.nav-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.4px;
  color: var(--sb-label); padding: 0 12px; margin-bottom: 8px; }

.nav-group { margin-bottom: 2px; }
.nav-group-header { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px;
  color: var(--sb-item); background: none; border: none; cursor: pointer; font-size: 14px; font-weight: 500;
  width: 100%; text-align: left; transition: background 0.15s, color 0.15s; position: relative; }
.nav-group-header .nav-icon { color: var(--sb-item-icon); transition: color 0.15s; }
.nav-group-header:hover { background: var(--sb-item-hover-bg); color: var(--sb-item-hover); }
.nav-group-header:hover .nav-icon { color: var(--sb-item-hover); }

.nav-arrow { margin-left: auto; font-size: 16px; line-height: 1; transition: transform 0.2s ease; display: inline-block; opacity: 0.6; }
.nav-arrow.open { transform: rotate(90deg); }

.nav-group-slide { overflow: hidden; transform-origin: top; will-change: opacity, transform; }
.nav-menu-enter-active,
.nav-menu-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.nav-menu-enter-from,
.nav-menu-leave-to { opacity: 0; transform: translate3d(0,-4px,0) scaleY(0.98); }
.nav-menu-enter-to,
.nav-menu-leave-from { opacity: 1; transform: translate3d(0,0,0) scaleY(1); }
.nav-menu-leave-active { pointer-events: none; }
.nav-group-items { overflow: hidden; padding: 2px 0 4px; }
.nav-link { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 10px;
  color: var(--sb-item); text-decoration: none; font-size: 13.5px; font-weight: 500; margin-bottom: 1px;
  transition: background 0.15s, color 0.15s; position: relative; }
.nav-link .nav-icon { color: var(--sb-item-icon); transition: color 0.15s; }
.nav-link:hover { background: var(--sb-item-hover-bg); color: var(--sb-item-hover); }
.nav-link:hover .nav-icon { color: var(--sb-item-hover); }
.nav-sub { padding-left: 28px; font-size: 13px; }
.nav-icon { width: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* Unified active state — purple accent (matches swiftCRM image) */
.active-blue, .active-green, .active-orange, .active-purple, .active-teal, .active-rose {
  background: var(--sb-active-bg) !important;
  color: var(--sb-active-text) !important;
  font-weight: 600 !important;
}
.active-blue .nav-icon,
.active-green .nav-icon,
.active-orange .nav-icon,
.active-purple .nav-icon,
.active-teal .nav-icon,
.active-rose .nav-icon { color: var(--sb-active-icon) !important; }

/* Active group header — subtle dark text emphasis */
.group-active-blue,
.group-active-green,
.group-active-orange,
.group-active-purple,
.group-active-teal,
.group-active-rose {
  color: var(--sb-active-text) !important;
  font-weight: 600 !important;
}
.group-active-blue .nav-icon,
.group-active-green .nav-icon,
.group-active-orange .nav-icon,
.group-active-purple .nav-icon,
.group-active-teal .nav-icon,
.group-active-rose .nav-icon { color: var(--sb-active-icon) !important; }

.sidebar-divider { height:1px; background: var(--sb-divider); margin: 10px 18px; }

.sidebar-user { padding: 8px 14px 12px; }
.user-info { display: flex; flex-direction: column; gap: 2px; margin-bottom: 10px; padding: 0 4px; }
.user-name { font-size: 13px; font-weight: 600; color: var(--sb-active-text); }
.user-email { font-size: 11px; color: var(--sb-label); }
.btn-profile { width: 100%; height: 34px; background: var(--sb-item-hover-bg); color: var(--sb-item); border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 0 12px; text-decoration: none; margin-bottom: 6px; transition: background 0.15s, color 0.15s; }
.btn-profile:hover { background: var(--sb-active-bg); color: var(--sb-active-text); }
.btn-logout { width: 100%; height: 34px; background: transparent; color: #ef4444; border: none; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 0 12px; transition: background 0.15s; }
.btn-logout:hover { background: rgba(239,68,68,0.10); }

.sidebar-footer { margin-top:auto; padding: 14px 22px; border-top:1px solid var(--sb-divider);
  font-size:11px; color: var(--sb-label); letter-spacing: 0.3px; }

.main-content { margin-left: 248px; flex: 1; transition: margin-left 0.2s ease; min-height: 100vh; }

.mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; }

.app-topbar {
  display: flex; align-items: center; gap: 10px; padding: 10px 18px;
  background: var(--topbar-bg); border-bottom: 1px solid var(--topbar-border);
  position: sticky; top: 0; z-index: 100;
  box-shadow: var(--shadow-xs);
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}
.topbar-right { margin-left: auto; display: flex; align-items: center; }
.hamburger-btn {
  background: none; border: 1px solid var(--border); border-radius: var(--radius); width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; color: var(--text-1); flex-shrink: 0;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.hamburger-btn:hover { background: var(--primary-soft); color: var(--primary-text); border-color: var(--primary-soft); }
.mobile-title { font-size: 15px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
/* Desktop: hide hamburger and brand title */
@media (min-width: 641px) {
  .hamburger-btn { display: none; }
  .mobile-title  { display: none; }
}

/* Tablet (641px–1023px) */
@media (min-width: 641px) and (max-width: 1023px) {
  .sidebar { width: 76px; }
  .nav-label, .nav-text, .sidebar-footer, .sidebar-user { display: none !important; }
  .brand-mark { width: 36px; height: 36px; }
  .sidebar-toggle { right: 23px; top: 12px; }
  .sidebar-brand { justify-content: center; padding: 16px 0 14px; margin-right: 0; }
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
  .layout.collapsed.mobile-open .sidebar-brand { justify-content: flex-start !important; padding: 14px 18px 16px !important; margin-right: 36px !important; }
  .layout.mobile-open .brand-mark,
  .layout.collapsed.mobile-open .brand-mark { width: 28px !important; height: 28px !important; }
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
