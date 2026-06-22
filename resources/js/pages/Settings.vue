<template>
  <div class="page">
    <!-- Page header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Application preferences and display configuration</p>
      </div>
    </div>

    <div v-if="saveMsg" :class="['msg-box', saveMsg.type === 'error' ? 'error-box' : 'success-box']">
      <span class="msg-icon" v-html="saveMsg.type === 'error' ? iconError : iconCheck"></span>
      {{ saveMsg.text }}
    </div>

    <!-- Two-column layout -->
    <div class="settings-layout">

      <!-- Left sidebar navigation -->
      <aside class="settings-nav">
        <!-- Profile shortcut -->
        <router-link to="/profile" class="profile-card">
          <div class="profile-avatar">{{ userInitial }}</div>
          <div class="profile-info">
            <span class="profile-name">{{ currentUser?.name ?? 'My Account' }}</span>
            <span class="profile-link-hint">View &amp; edit profile<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-left:3px"><polyline points="9 18 15 12 9 6"/></svg></span>
          </div>
        </router-link>

        <nav class="nav-list">
          <button
            v-for="item in navItems"
            :key="item.tab"
            v-show="item.tab !== 'admin' || isAdmin"
            :class="['nav-item', { active: tab === item.tab }]"
            @click="tab = item.tab"
          >
            <span class="nav-item-icon" v-html="item.icon"></span>
            <span class="nav-item-label">{{ item.label }}</span>
          </button>
        </nav>
      </aside>

      <!-- Right content panel -->
      <div class="settings-content">

        <!-- ══════════════ APPEARANCE ══════════════ -->
        <template v-if="tab === 'appearance'">
          <div class="section-header">
            <h2 class="section-title">Appearance</h2>
            <p class="section-desc">Choose how the application looks. "System" follows your device preference.</p>
          </div>
          <div class="content-card">
            <div class="field-label">Theme</div>
            <div class="theme-options">
              <button
                v-for="opt in themeOptions"
                :key="opt.value"
                :class="['theme-card', { selected: localSettings.theme === opt.value }]"
                @click="setTheme(opt.value)"
              >
                <div class="theme-icon" v-html="opt.icon"></div>
                <div class="theme-label">{{ opt.label }}</div>
                <div class="theme-hint">{{ opt.hint }}</div>
              </button>
            </div>
          </div>
          <div class="action-row">
            <button class="btn btn-save" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </template>

        <!-- ══════════════ NOTIFICATIONS ══════════════ -->
        <template v-if="tab === 'notifications'">
          <div class="section-header">
            <h2 class="section-title">Notifications</h2>
            <p class="section-desc">Choose which in-app alerts and reminders are active for your account.</p>
          </div>
          <div class="content-card">
            <div class="toggle-list">
              <div v-for="notif in notifOptions" :key="notif.key" class="toggle-row">
                <div class="toggle-info">
                  <span class="toggle-label">{{ notif.label }}</span>
                  <span class="toggle-desc">{{ notif.desc }}</span>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" v-model="localSettings.notifications[notif.key]" />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>
          </div>
          <div class="action-row">
            <button class="btn btn-save" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </template>

        <!-- ══════════════ REGIONAL ══════════════ -->
        <template v-if="tab === 'regional'">
          <div class="section-header">
            <h2 class="section-title">Regional</h2>
            <p class="section-desc">Set your local timezone and how dates and times are displayed.</p>
          </div>
          <div class="content-card">
            <div class="settings-list">

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Timezone</span>
                  <span class="setting-desc">Used when displaying scheduled events and reminders</span>
                </div>
                <select v-model="localSettings.timezone" class="setting-select">
                  <option value="">— Browser default —</option>
                  <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                </select>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Date Format</span>
                  <span class="setting-desc">How dates appear across the application</span>
                </div>
                <select v-model="localSettings.date_format" class="setting-select">
                  <option value="DD/MM/YYYY">DD/MM/YYYY (25/12/2024)</option>
                  <option value="MM/DD/YYYY">MM/DD/YYYY (12/25/2024)</option>
                  <option value="YYYY-MM-DD">YYYY-MM-DD (2024-12-25)</option>
                  <option value="DD-MM-YYYY">DD-MM-YYYY (25-12-2024)</option>
                </select>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Time Format</span>
                  <span class="setting-desc">12-hour (AM/PM) or 24-hour clock</span>
                </div>
                <div class="radio-group">
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.time_format" value="12h" />
                    <span>12-hour</span>
                  </label>
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.time_format" value="24h" />
                    <span>24-hour</span>
                  </label>
                </div>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">First Day of Week</span>
                  <span class="setting-desc">Start of week in calendar and weekly views</span>
                </div>
                <select v-model="localSettings.first_day_of_week" class="setting-select">
                  <option value="monday">Monday</option>
                  <option value="sunday">Sunday</option>
                  <option value="saturday">Saturday</option>
                </select>
              </div>

            </div>
          </div>
          <div class="action-row">
            <button class="btn btn-save" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </template>

        <!-- ══════════════ CRM PREFERENCES ══════════════ -->
        <template v-if="tab === 'crm'">
          <div class="section-header">
            <h2 class="section-title">CRM Preferences</h2>
            <p class="section-desc">Control how records and lists are shown in the CRM interface.</p>
          </div>
          <div class="content-card">
            <div class="settings-list">

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Default Landing Page</span>
                  <span class="setting-desc">Page to navigate to after login</span>
                </div>
                <select v-model="localSettings.crm.default_landing" class="setting-select">
                  <option value="/">Dashboard</option>
                  <option value="/list">Contacts</option>
                  <option value="/crm">CRM Dashboard</option>
                  <option value="/todos">To Do List</option>
                  <option value="/followups">Follow-Ups</option>
                  <option value="/performance">Performance</option>
                </select>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Contact List Density</span>
                  <span class="setting-desc">Spacing for contact rows in list views</span>
                </div>
                <div class="radio-group">
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.crm.contact_list_density" value="comfortable" />
                    <span>Comfortable</span>
                  </label>
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.crm.contact_list_density" value="compact" />
                    <span>Compact</span>
                  </label>
                </div>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Records Per Page</span>
                  <span class="setting-desc">Default number of items shown in paginated lists</span>
                </div>
                <select v-model.number="localSettings.crm.records_per_page" class="setting-select">
                  <option :value="10">10 records</option>
                  <option :value="20">20 records</option>
                  <option :value="50">50 records</option>
                  <option :value="100">100 records</option>
                </select>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Show Completed Tasks</span>
                  <span class="setting-desc">Include completed to-dos and follow-ups in list views by default</span>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" v-model="localSettings.crm.show_completed_tasks" />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="setting-row">
                <div class="setting-info">
                  <span class="setting-label">Default Pipeline View</span>
                  <span class="setting-desc">How the deals pipeline is displayed by default</span>
                </div>
                <div class="radio-group">
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.crm.pipeline_view" value="list" />
                    <span>List</span>
                  </label>
                  <label class="radio-opt">
                    <input type="radio" v-model="localSettings.crm.pipeline_view" value="kanban" />
                    <span>Kanban</span>
                  </label>
                </div>
              </div>

            </div>
          </div>
          <div class="action-row">
            <button class="btn btn-save" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </template>

        <!-- ══════════════ ADMIN ══════════════ -->
        <template v-if="tab === 'admin' && isAdmin">
          <div class="section-header">
            <h2 class="section-title">Admin Tools</h2>
            <p class="section-desc">Quick access to system administration and management pages.</p>
          </div>
          <div class="content-card no-pad">
            <router-link
              v-for="link in adminLinks"
              :key="link.to"
              :to="link.to"
              class="admin-link-row"
            >
              <div class="admin-link-icon" v-html="link.icon"></div>
              <div class="admin-link-body">
                <div class="admin-link-title">{{ link.title }}</div>
                <div class="admin-link-desc">{{ link.desc }}</div>
              </div>
              <span class="admin-link-arrow">›</span>
            </router-link>
          </div>
        </template>

      </div><!-- /.settings-content -->
    </div><!-- /.settings-layout -->
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useSettings, applyTheme } from '../composables/useSettings.js';

const _s = (p) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;

const tab    = ref('appearance');
const saving = ref(false);
const saveMsg = ref(null);

const { settings, loadFromServer, saveSettings } = useSettings();

const localSettings = reactive(JSON.parse(JSON.stringify(settings)));
localSettings.notifications = reactive({ ...settings.notifications });
localSettings.crm           = reactive({ ...settings.crm });

onMounted(async () => {
  await loadFromServer();
  Object.assign(localSettings, {
    theme:             settings.theme,
    timezone:          settings.timezone,
    date_format:       settings.date_format,
    time_format:       settings.time_format,
    first_day_of_week: settings.first_day_of_week,
  });
  Object.assign(localSettings.notifications, settings.notifications);
  Object.assign(localSettings.crm, settings.crm);
});

const currentUser = computed(() => JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin     = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const userInitial = computed(() => (currentUser.value?.name ?? 'U')[0].toUpperCase());

function setTheme(value) {
  localSettings.theme = value;
  applyTheme(value);
}

async function save() {
  saving.value  = true;
  saveMsg.value = null;
  try {
    settings.theme             = localSettings.theme;
    settings.timezone          = localSettings.timezone;
    settings.date_format       = localSettings.date_format;
    settings.time_format       = localSettings.time_format;
    settings.first_day_of_week = localSettings.first_day_of_week;
    Object.assign(settings.notifications, localSettings.notifications);
    Object.assign(settings.crm, localSettings.crm);

    await saveSettings();
    saveMsg.value = { type: 'success', text: 'Settings saved successfully.' };
    setTimeout(() => { saveMsg.value = null; }, 3000);
  } catch (_) {
    saveMsg.value = { type: 'error', text: 'Could not sync to server. Settings saved locally.' };
  } finally {
    saving.value = false;
  }
}

// ─── Static data ─────────────────────────────────────────────────────────────
const iconCheck = _s('<polyline points="20 6 9 17 4 12"/>');
const iconError = _s('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>');

const navItems = [
  { tab: 'appearance',   label: 'Appearance',      icon: _s('<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>') },
  { tab: 'notifications', label: 'Notifications',   icon: _s('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>') },
  { tab: 'regional',     label: 'Regional',         icon: _s('<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>') },
  { tab: 'crm',          label: 'CRM Preferences',  icon: _s('<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>') },
  { tab: 'admin',        label: 'Admin',            icon: _s('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>') },
];

const themeOptions = [
  {
    value: 'light',
    icon:  _s('<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'),
    label: 'Light',
    hint:  'Always light',
  },
  {
    value: 'dark',
    icon:  _s('<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'),
    label: 'Dark',
    hint:  'Always dark',
  },
  {
    value: 'system',
    icon:  _s('<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'),
    label: 'System',
    hint:  'Follow device',
  },
];

const notifOptions = [
  { key: 'crm_reminders',   label: 'CRM Reminders',             desc: 'Alerts from your scheduled CRM reminders' },
  { key: 'deal_updates',    label: 'Deal Updates',               desc: 'Alerts when deal status or value changes' },
  { key: 'task_reminders',  label: 'Task & Follow-up Reminders', desc: 'Due-date reminders for to-dos and follow-ups' },
];

const timezones = [
  { value: 'UTC',                 label: 'UTC — Coordinated Universal Time' },
  { value: 'Asia/Kuala_Lumpur',   label: 'UTC+8  — Kuala Lumpur (MYT)' },
  { value: 'Asia/Singapore',      label: 'UTC+8  — Singapore (SGT)' },
  { value: 'Asia/Bangkok',        label: 'UTC+7  — Bangkok (ICT)' },
  { value: 'Asia/Jakarta',        label: 'UTC+7  — Jakarta (WIB)' },
  { value: 'Asia/Hong_Kong',      label: 'UTC+8  — Hong Kong (HKT)' },
  { value: 'Asia/Shanghai',       label: 'UTC+8  — Shanghai (CST)' },
  { value: 'Asia/Tokyo',          label: 'UTC+9  — Tokyo (JST)' },
  { value: 'Asia/Seoul',          label: 'UTC+9  — Seoul (KST)' },
  { value: 'Asia/Kolkata',        label: 'UTC+5:30 — India (IST)' },
  { value: 'Asia/Dubai',          label: 'UTC+4  — Dubai (GST)' },
  { value: 'Europe/London',       label: 'UTC+0  — London (GMT/BST)' },
  { value: 'Europe/Paris',        label: 'UTC+1  — Paris (CET/CEST)' },
  { value: 'America/New_York',    label: 'UTC-5  — New York (EST/EDT)' },
  { value: 'America/Chicago',     label: 'UTC-6  — Chicago (CST/CDT)' },
  { value: 'America/Los_Angeles', label: 'UTC-8  — Los Angeles (PST/PDT)' },
  { value: 'Australia/Sydney',    label: 'UTC+10 — Sydney (AEST/AEDT)' },
  { value: 'Pacific/Auckland',    label: 'UTC+12 — Auckland (NZST/NZDT)' },
];

const adminLinks = [
  {
    to:    '/admin/rbac',
    icon:  _s('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>'),
    title: 'Access Control & Users',
    desc:  'Manage roles, permissions, and user accounts',
  },
  {
    to:    '/admin',
    icon:  _s('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
    title: 'Lookup Settings',
    desc:  'Manage dropdown options — statuses, types, categories, industries',
  },
  {
    to:    '/admin/system-settings',
    icon:  _s('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.6a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 3h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 10.59a16 16 0 0 0 6 6l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 18z"/>'),
    title: 'System Settings',
    desc:  'Configure notification email and other global system settings',
  },
  {
    to:    '/admin/user-activity',
    icon:  _s('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
    title: 'User Activity',
    desc:  'Monitor login history, CRM activity counts, and security events per user',
  },
  {
    to:    '/admin/audit-log',
    icon:  _s('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
    title: 'Audit Log',
    desc:  'View every admin action — user changes, role edits, approvals, and unlocks',
  },
  {
    to:    '/data-health',
    icon:  _s('<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'),
    title: 'Data Health',
    desc:  'Review CRM data quality, missing fields, and coverage reports',
  },
];
</script>

<style scoped>
/* ─── Page shell ──────────────────────────────────────────────────────────── */
.page {
  padding: 28px 32px;
  max-width: 1080px;
}

.page-header {
  margin-bottom: 28px;
}
.page-title {
  margin: 0 0 4px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-1);
}
.page-subtitle {
  margin: 0;
  font-size: 13.5px;
  color: var(--text-3);
}

/* ─── Status message ──────────────────────────────────────────────────────── */
.msg-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 20px;
}
.msg-icon { display: flex; align-items: center; flex-shrink: 0; }
.error-box   { background: rgba(220, 38, 38, 0.08); color: #ef4444; border: 1px solid rgba(220,38,38,0.2); }
.success-box { background: rgba(22, 163, 74, 0.08); color: #22c55e; border: 1px solid rgba(22,163,74,0.2); }

/* ─── Two-column layout ───────────────────────────────────────────────────── */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 28px;
  align-items: start;
}

/* ─── Left nav sidebar ────────────────────────────────────────────────────── */
.settings-nav {
  position: sticky;
  top: calc(var(--topbar-h, 47px) + 20px);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.profile-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  text-decoration: none;
  transition: border-color 0.15s, background 0.15s;
  margin-bottom: 4px;
}
.profile-card:hover {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.profile-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary-soft);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}
.profile-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.profile-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-1);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.profile-link-hint {
  font-size: 11px;
  color: var(--primary);
}

.nav-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 6px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 7px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-2);
  text-align: left;
  width: 100%;
  transition: background 0.15s, color 0.15s;
}
.nav-item:hover {
  background: var(--app-bg);
  color: var(--text-1);
}
.nav-item.active {
  background: var(--primary-soft);
  color: var(--primary);
  font-weight: 600;
}
.nav-item-icon {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.nav-item-label { flex: 1; }

/* ─── Right content area ──────────────────────────────────────────────────── */
.settings-content {
  min-width: 0;
}

.section-header {
  margin-bottom: 16px;
}
.section-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
  color: var(--text-1);
}
.section-desc {
  margin: 0;
  font-size: 13px;
  color: var(--text-2);
}

.content-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 16px;
}
.content-card.no-pad {
  padding: 0;
  overflow: hidden;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--text-2);
  margin-bottom: 16px;
}

/* ─── Theme selector ──────────────────────────────────────────────────────── */
.theme-options {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.theme-card {
  flex: 1;
  min-width: 110px;
  border: 2px solid var(--border);
  border-radius: 10px;
  padding: 20px 12px 16px;
  text-align: center;
  cursor: pointer;
  background: var(--surface);
  transition: border-color 0.15s, background 0.15s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
}
.theme-card:hover {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.theme-card.selected {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.theme-icon {
  color: var(--text-1);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--app-bg);
}
.theme-card.selected .theme-icon {
  color: var(--primary);
  background: var(--primary-soft);
}
.theme-label { font-size: 13px; font-weight: 600; color: var(--text-1); }
.theme-hint  { font-size: 11px; color: var(--text-2); }

/* ─── Settings rows ───────────────────────────────────────────────────────── */
.settings-list {
  display: flex;
  flex-direction: column;
}
.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border);
}
.setting-row:first-child { padding-top: 0; }
.setting-row:last-child  { border-bottom: none; padding-bottom: 0; }

.setting-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}
.setting-label { font-size: 13px; font-weight: 600; color: var(--text-1); }
.setting-desc  { font-size: 12px; color: var(--text-2); }

.setting-select {
  height: 36px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  padding: 0 10px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--surface);
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s;
  min-width: 180px;
  flex-shrink: 0;
}
.setting-select:focus { border-color: var(--primary); }
.setting-select option {
  background: var(--surface);
  color: var(--text-1);
}

/* ─── Radio group ─────────────────────────────────────────────────────────── */
.radio-group {
  display: flex;
  gap: 16px;
  flex-shrink: 0;
}
.radio-opt {
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-1);
}
.radio-opt input[type="radio"] {
  accent-color: var(--primary);
  width: 15px;
  height: 15px;
  cursor: pointer;
}

/* ─── Toggle list ─────────────────────────────────────────────────────────── */
.toggle-list  { display: flex; flex-direction: column; }
.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border);
}
.toggle-row:first-child { padding-top: 0; }
.toggle-row:last-child  { border-bottom: none; padding-bottom: 0; }
.toggle-info  { display: flex; flex-direction: column; gap: 3px; }
.toggle-label { font-size: 13px; font-weight: 600; color: var(--text-1); }
.toggle-desc  { font-size: 12px; color: var(--text-2); }

/* ─── Toggle switch ───────────────────────────────────────────────────────── */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider {
  position: absolute;
  inset: 0;
  background: var(--border);
  border-radius: 24px;
  cursor: pointer;
  transition: background 0.2s;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  bottom: 3px;
  background: var(--surface);
  border-radius: 50%;
  transition: transform 0.2s;
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.toggle-switch input:checked + .toggle-slider { background: var(--primary); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }

/* ─── Admin links ─────────────────────────────────────────────────────────── */
.admin-link-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 20px;
  text-decoration: none;
  transition: background 0.15s;
  border-bottom: 1px solid var(--border);
}
.admin-link-row:last-child { border-bottom: none; }
.admin-link-row:first-child { border-radius: 12px 12px 0 0; }
.admin-link-row:last-child  { border-radius: 0 0 12px 12px; }
.admin-link-row:hover { background: var(--primary-soft); }

.admin-link-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary-text);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.admin-link-body { flex: 1; min-width: 0; }
.admin-link-title { font-size: 13px; font-weight: 600; color: var(--text-1); margin-bottom: 2px; }
.admin-link-desc  { font-size: 12px; color: var(--text-2); }
.admin-link-arrow { font-size: 20px; color: var(--text-3); flex-shrink: 0; }

/* ─── Save row ────────────────────────────────────────────────────────────── */
.action-row { display: flex; justify-content: flex-end; margin-bottom: 8px; }
.btn {
  height: 38px;
  padding: 0 22px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}
.btn-save { background: var(--primary); color: white; }
.btn-save:hover:not(:disabled) { background: var(--primary-hover); }
.btn-save:disabled { opacity: 0.55; cursor: not-allowed; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .page { padding: 16px; }
  .settings-layout {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .settings-nav {
    position: static;
    flex-direction: column;
  }
  .nav-list {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 2px;
  }
  .nav-item { flex: 0 0 auto; }
  .nav-item-icon { display: none; }
  .setting-row { flex-direction: column; align-items: flex-start; gap: 10px; }
  .setting-select { min-width: 100%; width: 100%; }
  .theme-options { gap: 8px; }
  .theme-card { min-width: 80px; padding: 14px 8px 10px; }
}
</style>
