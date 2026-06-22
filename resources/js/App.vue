<template>
  <!-- Login page renders without sidebar -->
  <template v-if="isLogin">
    <router-view />
  </template>

  <div v-else class="layout" :class="{ collapsed, peeking, 'mobile-open': mobileOpen }">
    <!-- Mobile overlay backdrop -->
    <div class="mobile-overlay" @click="mobileOpen = false"></div>
    <!-- Sidebar -->
    <aside class="sidebar" @mouseenter="onSidebarEnter" @mouseleave="onSidebarLeave">
      <button class="sidebar-toggle" @click="collapsed = !collapsed" :title="collapsed ? 'Expand' : 'Collapse'" v-html="collapsed ? SVGI.chevronRight : SVGI.chevronLeft"></button>

      <router-link to="/" class="sidebar-brand" aria-label="Bluedale CRM" data-tour="brand">
        <span class="brand-mark" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" opacity="0.9"/>
            <rect x="13" y="2" width="9" height="9" rx="2" fill="currentColor" opacity="0.55"/>
            <rect x="2" y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.55"/>
            <rect x="13" y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.9"/>
          </svg>
        </span>
        <img :src="'/images/bluedale-logo.png'" class="brand-logo nav-text" alt="Bluedale Group of Companies" />
      </router-link>

      <!-- Main section -->
      <nav class="nav-section">
        <div class="nav-label">General</div>
        <div v-for="group in mainGroups" :key="group.key" class="nav-group">
          <button class="nav-group-header" :class="groupHeaderClass(group)" @click="toggleGroup(group.key)" :data-tour="'nav-' + group.key" :title="group.label">
            <span class="nav-icon" v-html="group.icon"></span>
            <span class="nav-text nav-group-label">{{ group.label }}</span>
            <span class="nav-arrow nav-text" :class="{ open: openGroups[group.key] }">›</span>
          </button>
          <Transition name="nav-menu">
            <div v-show="openGroups[group.key] && (!collapsed || peeking)" class="nav-group-slide">
              <div class="nav-group-items">
                <template v-for="item in group.items" :key="item.key">
                  <router-link
                    v-if="!item.locked"
                    :to="item.to"
                    class="nav-link nav-sub"
                    :class="itemClass(item, group)"
                  >
                    <span class="nav-text">{{ item.label }}</span>
                  </router-link>
                  <div
                    v-else
                    class="nav-link nav-sub nav-locked"
                    :title="`${item.label} — contact your admin to get access`"
                  >
                    <span class="nav-text">{{ item.label }}</span>
                    <span class="nav-lock-icon nav-text" v-html="SVGI.lockIcon"></span>
                  </div>
                </template>
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
          <button class="nav-group-header" :class="groupHeaderClass(group)" @click="toggleGroup(group.key)" :data-tour="'nav-' + group.key" :title="group.label">
            <span class="nav-icon" v-html="group.icon"></span>
            <span class="nav-text nav-group-label">{{ group.label }}</span>
            <span class="nav-arrow nav-text" :class="{ open: openGroups[group.key] }">›</span>
          </button>
          <Transition name="nav-menu">
            <div v-show="openGroups[group.key] && (!collapsed || peeking)" class="nav-group-slide">
              <div class="nav-group-items">
                <template v-for="item in group.items" :key="item.key">
                  <router-link
                    v-if="!item.locked"
                    :to="item.to"
                    class="nav-link nav-sub"
                    :class="itemClass(item, group)"
                  >
                    <span class="nav-text">{{ item.label }}</span>
                  </router-link>
                  <div
                    v-else
                    class="nav-link nav-sub nav-locked"
                    :title="`${item.label} — contact your admin to get access`"
                  >
                    <span class="nav-text">{{ item.label }}</span>
                    <span class="nav-lock-icon nav-text" v-html="SVGI.lockIcon"></span>
                  </div>
                </template>
              </div>
            </div>
          </Transition>
        </div>
      </nav>

      <div class="sidebar-footer">Bluedale CRM Platform</div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <div class="app-topbar">
        <button class="hamburger-btn" @click="mobileOpen = !mobileOpen" v-html="SVGI.menu"></button>
        <span class="mobile-title">Bluedale</span>

        <!-- Global nav search -->
        <div class="topbar-search" v-if="!isLogin" ref="searchWrap">
          <div class="topbar-search-inner">
            <span class="search-icon" v-html="SVGI.searchIcon"></span>
            <input
              ref="searchInput"
              v-model="searchQuery"
              type="text"
              class="topbar-search-input"
              placeholder="Search pages…"
              autocomplete="off"
              @focus="searchOpen = true"
              @keydown.escape="closeSearch"
              @keydown.enter="goToFirstResult"
              @keydown.down.prevent="searchFocusNext(1)"
              @keydown.up.prevent="searchFocusNext(-1)"
            />
            <kbd class="search-kbd" v-if="!searchQuery">⌘K</kbd>
          </div>
          <div class="search-dropdown" v-if="searchOpen && searchQuery.trim()">
            <template v-if="searchResults.length">
              <router-link
                v-for="(item, idx) in searchResults"
                :key="item.key"
                :to="item.to"
                class="search-result-item"
                :class="{ focused: idx === searchFocusIdx }"
                @click="closeSearch"
              >
                <span class="search-result-label">{{ item.label }}</span>
                <span class="search-result-group">{{ item.groupLabel }}</span>
              </router-link>
            </template>
            <div v-else class="search-empty">No pages found</div>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Tour trigger -->
          <button
            v-if="!isLogin"
            class="topbar-icon-btn tour-trigger-btn"
            title="Page guide"
            @click="tour.start(tourKeyFor(route.name))"
          >
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </button>

          <span data-tour="notification-bell">
            <NotificationBell v-if="!isLogin" />
          </span>

          <!-- Settings shortcut -->
          <router-link
            v-if="!isLogin"
            to="/settings"
            class="topbar-icon-btn"
            title="Settings"
            data-tour="settings-btn"
            v-html="SVGI.gear"
          ></router-link>

          <!-- User profile -->
          <div class="topbar-user" v-if="!isLogin && currentUser" ref="profileWrap" data-tour="user-profile">
            <button class="topbar-avatar-btn" @click="profileOpen = !profileOpen" :title="currentUser.name">
              {{ userInitials }}
            </button>
            <Transition name="dd-fade">
              <div class="profile-dropdown" v-if="profileOpen">
                <div class="profile-dd-header">
                  <span class="profile-dd-avatar">{{ userInitials }}</span>
                  <div>
                    <span class="profile-dd-name">{{ currentUser.name }}</span>
                    <span class="profile-dd-email">{{ currentUser.email }}</span>
                  </div>
                </div>
                <div class="profile-dd-divider"></div>
                <router-link to="/profile" class="profile-dd-item" @click="profileOpen = false">
                  <span v-html="SVGI.user"></span> My Profile
                </router-link>
                <router-link to="/settings" class="profile-dd-item" @click="profileOpen = false">
                  <span v-html="SVGI.gear"></span> Settings
                </router-link>
                <div class="profile-dd-divider"></div>
                <button class="profile-dd-item danger" @click="profileOpen = false; logout()">
                  <span v-html="SVGI.logout"></span> Logout
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </div>
      <router-view />
    </main>
  </div>

  <TourOverlay />
  <ToastContainer />
  <SessionTimeoutModal
    :show="sessionWarning"
    :secondsLeft="sessionSecondsLeft"
    @stay="stayLoggedIn"
    @logout="logout"
  />
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from './api.js';
import NotificationBell from './components/NotificationBell.vue';
import TourOverlay from './components/TourOverlay.vue';
import ToastContainer from './components/ToastContainer.vue';
import SessionTimeoutModal from './components/SessionTimeoutModal.vue';
import { useSessionTimeout } from './composables/useSessionTimeout.js';
import { applyTheme, useSettings } from './composables/useSettings.js';
import { useTour } from './composables/useTour.js';

const route = useRoute();
const router = useRouter();
const collapsed = ref(localStorage.getItem('sidebarCollapsed') === '1');
const peeking = ref(false);
const mobileOpen = ref(false);

// ─── Tour ──────────────────────────────────────────────────────────────────────
const tour = useTour();
// Auto-expand sidebar during tour so all targets are visible
watch(tour.active, val => { if (val) collapsed.value = false; });

// Pages that have separate admin/user tours — append role suffix before lookup
const ROLE_SPLIT_TOURS = ['dept-tasks'];
function tourKeyFor(routeName) {
  if (routeName === 'list') return 'list-' + (route.query.tab || 'contacts');
  if (ROLE_SPLIT_TOURS.includes(routeName)) {
    return routeName + (isAdminOrSuperAdmin.value ? '-admin' : '-user');
  }
  return routeName;
}

// ─── Topbar search ────────────────────────────────────────────────────────────
const searchQuery = ref('');
const searchOpen = ref(false);
const searchFocusIdx = ref(-1);
const searchInput = ref(null);
const searchWrap = ref(null);

const allNavItems = computed(() => {
  const items = [];
  for (const group of ALL_GROUPS) {
    if (group.adminOnly && !isAdminOrSuperAdmin.value) continue;
    for (const item of group.items) {
      if (!hasItemPermission(item)) continue;
      items.push({ ...item, groupLabel: group.label });
    }
  }
  return items;
});

const searchResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return [];
  return allNavItems.value.filter(
    item => item.label.toLowerCase().includes(q) || item.groupLabel.toLowerCase().includes(q)
  ).slice(0, 8);
});

function closeSearch() {
  searchOpen.value = false;
  searchQuery.value = '';
  searchFocusIdx.value = -1;
}

function goToFirstResult() {
  const idx = searchFocusIdx.value >= 0 ? searchFocusIdx.value : 0;
  if (searchResults.value[idx]) {
    router.push(searchResults.value[idx].to);
    closeSearch();
  }
}

function searchFocusNext(dir) {
  const max = searchResults.value.length - 1;
  searchFocusIdx.value = Math.max(0, Math.min(max, searchFocusIdx.value + dir));
}

// ─── Profile dropdown ─────────────────────────────────────────────────────────
const profileOpen = ref(false);
const profileWrap = ref(null);

const userInitials = computed(() => {
  const name = currentUser.value?.name ?? '';
  return name.split(' ').filter(Boolean).map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';
});

// Global click-outside handler (search + profile)
function handleDocClick(e) {
  if (searchWrap.value && !searchWrap.value.contains(e.target)) {
    searchOpen.value = false;
  }
  if (profileWrap.value && !profileWrap.value.contains(e.target)) {
    profileOpen.value = false;
  }
}

// Cmd/Ctrl+K shortcut
function handleKeydown(e) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    searchInput.value?.focus();
    searchOpen.value = true;
  }
}

function onSidebarEnter() { if (collapsed.value) peeking.value = true; }
function onSidebarLeave() { peeking.value = false; }
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
  document.addEventListener('click', handleDocClick, true);
  document.addEventListener('keydown', handleKeydown);
  // Sync user settings from server once on app mount (no-op if not logged in)
  if (localStorage.getItem('crm_token')) {
    loadFromServer();
    // Refresh roles/permissions so sidebar reflects any changes made by an admin
    // since the last login (crm_user is otherwise only written at login time).
    api.get('/auth/me').then(res => {
      const fresh  = res.data.user;
      const stored = JSON.parse(localStorage.getItem('crm_user') || '{}');
      const merged = { ...stored, ...fresh };
      localStorage.setItem('crm_user', JSON.stringify(merged));
      currentUser.value = merged;
    }).catch(() => { /* 401 is handled by the axios interceptor */ });
    // Auto-start tour for users who haven't seen it yet
    if (!tour.hasSeen()) {
      setTimeout(() => tour.start(), 1200);
    }
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleDocClick, true);
  document.removeEventListener('keydown', handleKeydown);
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
  sparkle:      _s('<path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/>'),
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
  zap:          _s('<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'),
  flask:        _s('<path d="M9 3h6m-5 0v6l-4 9a1 1 0 0 0 .93 1.37h10.14A1 1 0 0 0 18 18l-4-9V3"/>'),
  searchIcon:   _s('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'),
  map:          _s('<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>'),
  lockIcon:     _s('<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'),
  kanban:       _s('<rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="13" rx="1"/><rect x="17" y="3" width="4" height="9" rx="1"/>'),
};

// ─── Navigation config ────────────────────────────────────────────────────────
const ALL_GROUPS = [
  {
    key: 'overview', label: 'Overview', icon: SVGI.home, color: 'blue', section: 'main', adminOnly: false,
    items: [
      { key: 'home',    to: '/',        icon: SVGI.home,  label: 'Dashboard', activeRoutes: ['home'] },
      { key: 'reports', to: '/reports', icon: SVGI.chart, label: 'Reports',   activeRoutes: ['reports'] },
    ],
  },
  {
    key: 'crm-pipeline', label: 'CRM Pipeline', icon: SVGI.folder, color: 'green', section: 'main', adminOnly: false,
    items: [
      { key: 'list',      to: '/list',      icon: SVGI.list,      label: 'Contacts',  activeRoutes: ['list', 'contact-view', 'contact-add', 'contact-edit', 'task-add'] },
      { key: 'forecasts', to: '/forecasts', icon: SVGI.trending,  label: 'Forecasts', activeRoutes: ['forecasts', 'forecast-summary'] },
      { key: 'projects',  to: '/projects',  icon: SVGI.layers,    label: 'Projects',  activeRoutes: ['projects', 'project-add', 'project-edit'], adminOnly: true },
      { key: 'deals',     to: '/deals',     icon: SVGI.briefcase, label: 'Deals',     activeRoutes: ['deals', 'deal-add', 'deal-edit'],           adminOnly: true },
    ],
  },
  {
    key: 'activity', label: 'Activity', icon: SVGI.clipboard, color: 'teal', section: 'main', adminOnly: false,
    items: [
      { key: 'todos',      to: '/todos',      icon: SVGI.clipboard, label: 'To Do List',   activeRoutes: ['todos', 'todo-add', 'task-edit'] },
      { key: 'followups',  to: '/followups',  icon: SVGI.bell,      label: 'Follow-Ups',   activeRoutes: ['followups', 'followup-add', 'followup-edit'] },
      { key: 'reminders',  to: '/reminders',  icon: SVGI.bell,      label: 'Reminders',    activeRoutes: ['reminders'] },
      { key: 'dept-tasks', to: '/dept-tasks', icon: SVGI.kanban,    label: 'Task Manager', activeRoutes: ['dept-tasks'], permission: 'manage dept-tasks' },
    ],
  },
  {
    key: 'analytics', label: 'Analytics', icon: SVGI.trending, color: 'orange', section: 'main', adminOnly: false,
    items: [
      { key: 'contact-analysis',    to: '/contact-analysis',    icon: SVGI.chart,    label: 'Contact Analysis',    activeRoutes: ['contact-analysis'] },
      { key: 'predictive-insights', to: '/predictive-insights', icon: SVGI.zap,      label: 'Predictive Insights', activeRoutes: ['predictive-insights'] },
      { key: 'performance',         to: '/performance',         icon: SVGI.trending, label: 'Performance',         activeRoutes: ['performance'] },
    ],
  },
  {
    key: 'marketing', label: 'Marketing', icon: SVGI.megaphone, color: 'rose', section: 'main', adminOnly: false,
    items: [
      { key: 'social-media',      to: '/social-media',      icon: SVGI.megaphone, label: 'Social Media',      activeRoutes: ['social-media'],      permission: 'manage social-media' },
      { key: 'posting-calendar',  to: '/posting-calendar',  icon: SVGI.calendar,  label: 'Posting Calendar',  activeRoutes: ['posting-calendar'],  permission: 'manage posting-calendar' },
      { key: 'marketing-email',   to: '/marketing-email',   icon: SVGI.mail,      label: 'Email Marketing',   activeRoutes: ['marketing-email'],   permission: 'manage email-campaigns' },
    ],
  },
  {
    key: 'site-availability', label: 'Site Availability', icon: SVGI.grid, color: 'blue', section: 'main', adminOnly: false,
    items: [
      { key: 'site-availability', to: '/site-availability', icon: SVGI.grid, label: 'Site Availability', activeRoutes: ['site-availability'], permission: 'manage site-availability' },
    ],
  },
  {
    key: 'admin', label: 'Administration', icon: SVGI.gear, color: 'purple', section: 'tools', adminOnly: true,
    items: [
      { key: 'admin-panel',     to: '/admin',                  icon: SVGI.gear,     label: 'Lookup Settings', activeRoutes: ['admin'] },
      { key: 'rbac',            to: '/admin/rbac',             icon: SVGI.shield,   label: 'Access Control',  activeRoutes: ['rbac'] },
      { key: 'system-settings', to: '/admin/system-settings',  icon: SVGI.mail,     label: 'System Settings', activeRoutes: ['system-settings'] },
      { key: 'user-activity',   to: '/admin/user-activity',   icon: SVGI.activity, label: 'User Activity',   activeRoutes: ['user-activity'] },
      { key: 'audit-log',       to: '/admin/audit-log',        icon: SVGI.list,     label: 'Audit Log',       activeRoutes: ['audit-log'] },
    ],
  },
  {
    key: 'data', label: 'Data Management', icon: SVGI.download, color: 'orange', section: 'tools', adminOnly: false, hideLocked: true,
    items: [
      { key: 'import',      to: '/import',      icon: SVGI.download, label: 'Import Data', activeRoutes: ['import'],      permission: 'import contacts' },
      { key: 'data-health', to: '/data-health', icon: SVGI.activity, label: 'Data Health', activeRoutes: ['data-health'], permission: 'view data-health' },
    ],
  },
  // section: 'account' — not rendered in sidebar (only 'main'/'tools' are); included in search only
  {
    key: 'account', label: 'Account', icon: SVGI.user, color: 'gray', section: 'account', adminOnly: false,
    items: [
      { key: 'profile',  to: '/profile',  icon: SVGI.user, label: 'My Profile', activeRoutes: ['profile'] },
      { key: 'settings', to: '/settings', icon: SVGI.gear, label: 'Settings',   activeRoutes: ['settings'] },
    ],
  },
];

function hasItemPermission(item) {
  if (item.adminOnly && !isAdminOrSuperAdmin.value) return false;
  if (!item.permission) return true;
  const u = currentUser.value;
  if (!u) return false;
  if (u.permissions === null) return true; // super-admin: full access, no DB permissions stored
  if ((u.roles ?? []).includes('super-admin')) return true; // fallback for stale localStorage
  return (u.permissions ?? []).includes(item.permission);
}

function resolveGroupItems(g) {
  // hideLocked: fully remove items the user can't access (group disappears when all items are hidden)
  // default: keep locked items visible but dimmed so users know the feature exists
  return g.hideLocked
    ? g.items.filter(item => hasItemPermission(item))
    : g.items.map(item => ({ ...item, locked: !hasItemPermission(item) }));
}

const mainGroups = computed(() =>
  ALL_GROUPS
    .filter(g => g.section === 'main' && (!g.adminOnly || isAdminOrSuperAdmin.value))
    .map(g => ({ ...g, items: resolveGroupItems(g) }))
    .filter(g => g.items.length > 0)
);
const toolGroups = computed(() =>
  ALL_GROUPS
    .filter(g => g.section === 'tools' && (!g.adminOnly || isAdminOrSuperAdmin.value))
    .map(g => ({ ...g, items: resolveGroupItems(g) }))
    .filter(g => g.items.length > 0)
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
  currentUser.value = null;
  router.push('/login');
}

const isLoggedIn = computed(() => !isLogin.value && currentUser.value !== null);
const { showWarning: sessionWarning, secondsLeft: sessionSecondsLeft, stayLoggedIn } = useSessionTimeout({
  isLoggedIn,
  onTimeout: logout,
});
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

  /* Accent — Bluedale navy blue */
  --primary:        #1d4ed8;
  --primary-hover:  #1e40af;
  --primary-soft:   #dbeafe;
  --primary-on:     #ffffff;
  --primary-text:   #0f2456;
  --focus-ring:     rgba(29,78,216,0.35);

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
  --sb-brand-1:        #0f2456;
  --sb-brand-2:        #1d4ed8;
  --sb-label:          #9ca3af;
  --sb-item:           #4b5563;
  --sb-item-icon:      #6b7280;
  --sb-item-hover-bg:  #f3f4f6;
  --sb-item-hover:     #1f2937;
  --sb-active-bg:      #dbeafe;
  --sb-active-text:    #0f2456;
  --sb-active-icon:    #1d4ed8;
  --sb-divider:        #eef0f4;
  --sb-toggle-bg:      #f3f4f6;
  --sb-toggle-border:  #e5e7eb;
  --sb-toggle-icon:    #6b7280;

  /* Page-level outer padding — scales down at smaller effective viewports
     (high browser zoom shrinks the effective CSS pixel width, triggering the
     responsive overrides below just like a narrow physical screen would). */
  --page-px: 32px;
  --page-py: 28px;
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

  --primary:        #60a5fa;
  --primary-hover:  #3b82f6;
  --primary-soft:   rgba(59,130,246,0.18);
  --primary-on:     #0f172a;
  --primary-text:   #93c5fd;
  --focus-ring:     rgba(96,165,250,0.45);

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
  --sb-brand-2:        #60a5fa;
  --sb-label:          #475569;
  --sb-item:           #94a3b8;
  --sb-item-icon:      #94a3b8;
  --sb-item-hover-bg:  rgba(255,255,255,0.05);
  --sb-item-hover:     #e2e8f0;
  --sb-active-bg:      rgba(59,130,246,0.18);
  --sb-active-text:    #93c5fd;
  --sb-active-icon:    #60a5fa;
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
.layout.collapsed .nav-text:not(.nav-group-label),
.layout.collapsed .sidebar-footer { display: none; }
.layout.collapsed .sidebar-toggle { right: 23px; top: 12px; }
.layout.collapsed .sidebar-brand { justify-content: center; padding: 18px 0 16px; border-bottom: 1px solid var(--sb-divider); }
.layout.collapsed .brand-mark { width: 36px; height: 36px; }
.layout.collapsed .brand-mark svg { width: 22px; height: 22px; }
.layout.collapsed .nav-group-header { flex-direction: column; justify-content: center; align-items: center; padding: 8px 4px; gap: 3px; }
.layout.collapsed:not(.peeking) .nav-group-label { font-size: 9.5px; font-weight: 600; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 68px; line-height: 1.2; opacity: 0.7; display: block; }
.layout.collapsed .nav-link { justify-content: center; padding: 10px 0; }
.layout.collapsed .nav-icon { width: auto; }
.layout.collapsed .main-content { margin-left: 76px; }

/* Peek-expand: sidebar expands over content on hover when collapsed */
.layout.collapsed.peeking .sidebar { width: 248px; box-shadow: 4px 0 24px rgba(0,0,0,0.13); }
.layout.collapsed.peeking .nav-label,
.layout.collapsed.peeking .sidebar-footer { display: block; }
.layout.collapsed.peeking .nav-text { display: inline; }
.layout.collapsed.peeking .sidebar-toggle { right: 12px; top: 16px; }
.layout.collapsed.peeking .sidebar-brand { justify-content: center; padding: 14px 16px 12px; border-bottom: 1px solid var(--sb-divider); }
.layout.collapsed.peeking .nav-group-header { flex-direction: row; justify-content: flex-start; padding: 10px 12px; gap: 12px; }
.layout.collapsed.peeking .nav-group-label { font-size: inherit; font-weight: inherit; opacity: 1; text-align: left; white-space: normal; overflow: visible; text-overflow: clip; max-width: none; }
.layout.collapsed.peeking .nav-link { justify-content: flex-start; padding: 9px 12px; }
.layout.collapsed.peeking .nav-sub { padding-left: 28px; }
.layout.collapsed.peeking .nav-icon { width: 22px; }

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

.sidebar-brand { display: flex; align-items: center; justify-content: center; padding: 14px 16px 12px;
  text-decoration: none; flex-shrink: 0;
  border-bottom: 1px solid var(--sb-divider); }
.brand-mark { display: none; width: 28px; height: 28px; align-items: center; justify-content: center;
  border-radius: 8px; color: var(--sb-brand-2); flex-shrink: 0; }
/* Collapsed-only: show icon mark, hide logo (logo already hidden via .nav-text) */
.layout.collapsed:not(.peeking) .brand-mark { display: inline-flex; }
.brand-logo {
  /* object-fit: cover crops the image's internal whitespace, zooming into just the text */
  width: 100%; height: 68px; max-width: calc(100% - 44px);
  object-fit: cover; object-position: center 50%;
}
[data-theme="dark"] .brand-logo {
  /* Dark mode: invert flips white-bg→black, dark-text→light; screen blend removes the black bg */
  filter: invert(1);
  mix-blend-mode: screen;
}

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
.nav-locked { opacity: 0.4; cursor: not-allowed; }
.nav-lock-icon { margin-left: auto; display: flex; align-items: center; flex-shrink: 0; }

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

/* Collapsed sidebar — show background on active group so the icon is clearly highlighted */
.layout.collapsed .group-active-blue,
.layout.collapsed .group-active-green,
.layout.collapsed .group-active-orange,
.layout.collapsed .group-active-purple,
.layout.collapsed .group-active-teal,
.layout.collapsed .group-active-rose {
  background: var(--sb-active-bg) !important;
}

.sidebar-divider { height:1px; background: var(--sb-divider); margin: 10px 18px; }


.sidebar-footer { margin-top:auto; padding: 14px 22px; border-top:1px solid var(--sb-divider);
  font-size:11px; color: var(--sb-label); letter-spacing: 0.3px; }

.main-content { margin-left: 248px; flex: 1; min-width: 0; transition: margin-left 0.2s ease; min-height: 100vh; }

.mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; }

.app-topbar {
  display: flex; align-items: center; gap: 10px; padding: 10px 18px;
  min-height: var(--topbar-h);
  background: var(--topbar-bg); border-bottom: 1px solid var(--topbar-border);
  position: sticky; top: 0; z-index: 100;
  box-shadow: var(--shadow-xs);
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 4px; }
.hamburger-btn {
  background: none; border: 1px solid var(--border); border-radius: var(--radius); width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; color: var(--text-1); flex-shrink: 0;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.hamburger-btn:hover { background: var(--primary-soft); color: var(--primary-text); border-color: var(--primary-soft); }
.mobile-title { font-size: 15px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }

/* ── Topbar search ─────────────────────────────────────────────── */
.topbar-search {
  position: relative; flex: 1; max-width: 320px; margin: 0 12px;
}
.topbar-search-inner {
  display: flex; align-items: center; gap: 8px;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 0 10px; height: 36px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.topbar-search-inner:focus-within {
  border-color: var(--primary);
}
.search-icon { color: var(--text-3); display: flex; align-items: center; flex-shrink: 0; }
.topbar-search-input {
  flex: 1; border: none; background: transparent; font-size: 13px;
  color: var(--text-1); outline: none; box-shadow: none; min-width: 0;
}
.topbar-search-input:focus,
.topbar-search-input:focus-visible { outline: none; box-shadow: none; }
.topbar-search-input::placeholder { color: var(--text-3); }
.search-kbd {
  font-size: 10px; color: var(--text-3); background: var(--border);
  border-radius: 4px; padding: 1px 5px; font-family: inherit; flex-shrink: 0;
}
.search-dropdown {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-lg);
  z-index: 9999; overflow: hidden;
}
.search-result-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 9px 14px; text-decoration: none; color: var(--text-1);
  font-size: 13px; gap: 10px; transition: background 0.12s;
}
.search-result-item:hover,
.search-result-item.focused { background: var(--primary-soft); color: var(--primary-text); }
.search-result-label { font-weight: 500; }
.search-result-group { font-size: 11px; color: var(--text-3); white-space: nowrap; }
.search-result-item.focused .search-result-group { color: var(--primary-text); opacity: 0.7; }
.search-empty { padding: 12px 14px; font-size: 13px; color: var(--text-3); text-align: center; }

/* ── Topbar icon button (settings) ────────────────────────────── */
.topbar-icon-btn {
  width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
  border-radius: var(--radius); color: var(--text-2); text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.topbar-icon-btn:hover { background: var(--primary-soft); color: var(--primary-text); }
.tour-trigger-btn { border: none; cursor: pointer; background: transparent; }

/* ── Topbar user avatar + profile dropdown ─────────────────────── */
.topbar-user { position: relative; }
.topbar-avatar-btn {
  width: 34px; height: 34px; border-radius: 50%; border: 2px solid var(--primary-soft);
  background: var(--primary-soft); color: var(--primary-text);
  font-size: 12px; font-weight: 700; cursor: pointer; display: flex;
  align-items: center; justify-content: center;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.topbar-avatar-btn:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.profile-dropdown {
  position: absolute; top: calc(100% + 8px); right: 0; width: 220px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 9999;
  overflow: hidden;
}
.profile-dd-header {
  display: flex; align-items: center; gap: 10px; padding: 12px 14px;
}
.profile-dd-avatar {
  width: 34px; height: 34px; border-radius: 50%; background: var(--primary-soft);
  color: var(--primary-text); font-size: 12px; font-weight: 700; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.profile-dd-name { display: block; font-size: 13px; font-weight: 600; color: var(--text-1); }
.profile-dd-email { display: block; font-size: 11px; color: var(--text-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
.profile-dd-divider { height: 1px; background: var(--border-soft); margin: 2px 0; }
.profile-dd-item {
  display: flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 13px;
  font-weight: 500; color: var(--text-1); text-decoration: none; width: 100%;
  background: none; border: none; cursor: pointer; text-align: left;
  transition: background 0.12s, color 0.12s;
}
.profile-dd-item:hover { background: var(--surface-2); color: var(--primary); }
.profile-dd-item.danger { color: var(--danger); }
.profile-dd-item.danger:hover { background: var(--danger-soft); color: var(--danger); }

/* ── Dropdown transition ────────────────────────────────────────── */
.dd-fade-enter-active, .dd-fade-leave-active { transition: opacity 0.12s ease, transform 0.12s ease; }
.dd-fade-enter-from, .dd-fade-leave-to { opacity: 0; transform: translateY(-6px); }

/* Desktop: hide hamburger and brand title */
@media (min-width: 641px) {
  .hamburger-btn { display: none; }
  .mobile-title  { display: none; }
}

/* Tablet (641px–1023px) */
@media (min-width: 641px) and (max-width: 1023px) {
  .sidebar { width: 76px; }
  .nav-label, .nav-text:not(.nav-group-label), .sidebar-footer, .sidebar-user { display: none !important; }
  .nav-group-label { display: block !important; font-size: 9.5px; font-weight: 600; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 68px; line-height: 1.2; opacity: 0.7; }
  .brand-mark { width: 36px; height: 36px; }
  .sidebar-toggle { right: 23px; top: 12px; }
  .sidebar-brand { justify-content: center; padding: 18px 0 16px; border-bottom: 1px solid var(--sb-divider); }
  .nav-group-header { flex-direction: column; justify-content: center; align-items: center; padding: 8px 4px; gap: 3px; }
  .nav-link { justify-content: center; padding: 10px 0; }
  .nav-icon { width: auto !important; }
  .main-content { margin-left: 76px; }
  .layout.collapsed .main-content { margin-left: 76px; }
}

/* Mobile (≤640px) */
@media (max-width: 640px) {
  .topbar-search { display: none; }
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
  .layout.mobile-open .sidebar-brand,
  .layout.collapsed.mobile-open .sidebar-brand { justify-content: center !important; padding: 14px 16px 12px !important; }
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
