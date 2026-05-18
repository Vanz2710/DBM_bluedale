<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Settings</h1>
        <p>Application preferences and display configuration</p>
      </div>
    </div>

    <!-- Profile shortcut -->
    <div class="profile-shortcut">
      <div class="shortcut-body">
        <span class="shortcut-title">Account &amp; Profile</span>
        <span class="shortcut-desc">Update your name, email, password, and account details on the profile page.</span>
      </div>
      <router-link to="/profile" class="btn-shortcut">My Profile →</router-link>
    </div>

    <!-- Tab navigation -->
    <div class="tab-nav">
      <button :class="['tab-btn', { active: tab === 'appearance' }]" @click="tab = 'appearance'">Appearance</button>
      <button :class="['tab-btn', { active: tab === 'notifications' }]" @click="tab = 'notifications'">Notifications</button>
      <button :class="['tab-btn', { active: tab === 'regional' }]" @click="tab = 'regional'">Regional</button>
      <button :class="['tab-btn', { active: tab === 'crm' }]" @click="tab = 'crm'">CRM Preferences</button>
      <button v-if="isAdmin" :class="['tab-btn', { active: tab === 'admin' }]" @click="tab = 'admin'">Admin</button>
    </div>

    <div v-if="saveMsg" :class="['msg-box', saveMsg.type === 'error' ? 'error-box' : 'success-box']">
      {{ saveMsg.text }}
    </div>

    <!-- ══════════════ APPEARANCE ══════════════ -->
    <template v-if="tab === 'appearance'">
      <div class="card">
        <div class="card-title">Theme</div>
        <p class="card-desc">Choose how the application looks. "System" follows your device preference.</p>
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
      <div class="card">
        <div class="card-title">Notification Preferences</div>
        <p class="card-desc">Choose which in-app alerts and reminders are active for your account.</p>
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
      <div class="card">
        <div class="card-title">Regional Settings</div>
        <p class="card-desc">Set your local timezone and how dates and times are displayed.</p>
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
              <option value="DD/MM/YYYY">DD/MM/YYYY &nbsp;(25/12/2024)</option>
              <option value="MM/DD/YYYY">MM/DD/YYYY &nbsp;(12/25/2024)</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD (2024-12-25)</option>
              <option value="DD-MM-YYYY">DD-MM-YYYY &nbsp;(25-12-2024)</option>
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
      <div class="card">
        <div class="card-title">CRM Display Preferences</div>
        <p class="card-desc">Control how records and lists are shown in the CRM interface.</p>
        <div class="settings-list">

          <div class="setting-row">
            <div class="setting-info">
              <span class="setting-label">Default Landing Page</span>
              <span class="setting-desc">Page to navigate to after login</span>
            </div>
            <select v-model="localSettings.crm.default_landing" class="setting-select">
              <option value="/">Dashboard</option>
              <option value="/list">Daily List</option>
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
      <div class="card">
        <div class="card-title">Admin Tools</div>
        <p class="card-desc">Quick access to system administration and management pages.</p>
        <div class="admin-links">
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
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useSettings, applyTheme } from '../composables/useSettings.js';

const _s = (p) => `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;

const tab    = ref('appearance');
const saving = ref(false);
const saveMsg = ref(null);

const { settings, loadFromServer, saveSettings } = useSettings();

// Local editable copy — isolates form state from the shared singleton until Save
const localSettings = reactive(JSON.parse(JSON.stringify(settings)));
// Ensure nested objects are properly reactive
localSettings.notifications = reactive({ ...settings.notifications });
localSettings.crm           = reactive({ ...settings.crm });

onMounted(async () => {
  await loadFromServer();
  // Sync local copy after server data arrives
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

function setTheme(value) {
  localSettings.theme = value;
  applyTheme(value); // apply immediately for live preview
}

async function save() {
  saving.value  = true;
  saveMsg.value = null;
  try {
    // Flush local copy into the shared singleton
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
  { key: 'crm_reminders',   label: 'CRM Reminders',              desc: 'Alerts from your scheduled CRM reminders' },
  { key: 'whatsapp_alerts', label: 'WhatsApp Lead Alerts',        desc: 'Notifications when new leads arrive via WhatsApp' },
  { key: 'deal_updates',    label: 'Deal Updates',                desc: 'Alerts when deal status or value changes' },
  { key: 'task_reminders',  label: 'Task & Follow-up Reminders',  desc: 'Due-date reminders for to-dos and follow-ups' },
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
    to:    '/admin/webhooks',
    icon:  _s('<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'),
    title: 'Webhooks',
    desc:  'Configure outbound webhook integrations for CRM events',
  },
  {
    to:    '/data-health',
    icon:  _s('<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'),
    title: 'Data Health',
    desc:  'Review CRM data quality, missing fields, and coverage reports',
  },
  {
    to:    '/admin/performance-targets',
    icon:  _s('<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'),
    title: 'Performance Targets',
    desc:  'Set team-wide KPI targets and goals',
  },
];
</script>

<style scoped>
.page { padding: 24px; max-width: 780px; }

.page-banner { margin-bottom: 20px; }
.page-banner h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; color: var(--text-1, #1e293b); }
.page-banner p  { margin: 0; font-size: 13px; color: var(--text-2, #64748b); }

/* Profile shortcut */
.profile-shortcut {
  display: flex; align-items: center; gap: 16px; justify-content: space-between;
  background: var(--surface, white); border: 1px solid var(--border, #e2e8f0);
  border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
}
.shortcut-body { display: flex; flex-direction: column; gap: 2px; }
.shortcut-title { font-size: 13px; font-weight: 600; color: var(--text-1, #1e293b); }
.shortcut-desc  { font-size: 12px; color: var(--text-2, #64748b); }
.btn-shortcut {
  white-space: nowrap; height: 34px; padding: 0 16px; border-radius: 8px;
  background: rgba(59,130,246,0.1); color: #3b82f6; font-size: 13px; font-weight: 600;
  text-decoration: none; display: flex; align-items: center; flex-shrink: 0;
  transition: background 0.15s;
}
.btn-shortcut:hover { background: rgba(59,130,246,0.18); }

/* Tabs */
.tab-nav {
  display: flex; gap: 2px; border-bottom: 2px solid var(--border, #e2e8f0);
  margin-bottom: 20px; overflow-x: auto; scrollbar-width: none;
}
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn {
  padding: 10px 18px; font-size: 13px; font-weight: 500; color: var(--text-2, #64748b);
  background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px;
  cursor: pointer; white-space: nowrap; transition: color 0.15s, border-color 0.15s;
}
.tab-btn:hover { color: var(--text-1, #1e293b); }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 600; }

/* Cards */
.card {
  background: var(--surface, white); border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px; padding: 24px; margin-bottom: 16px;
}
.card-title {
  font-size: 14px; font-weight: 700; color: var(--text-1, #1e293b);
  margin-bottom: 4px;
}
.card-desc { font-size: 12px; color: var(--text-2, #64748b); margin: 0 0 20px; }

/* Messages */
.msg-box { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.error-box   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.success-box { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

/* Theme selector */
.theme-options { display: flex; gap: 12px; flex-wrap: wrap; }
.theme-card {
  flex: 1; min-width: 110px; border: 2px solid var(--border, #e2e8f0);
  border-radius: 10px; padding: 18px 12px 14px; text-align: center; cursor: pointer;
  background: var(--surface, white); transition: border-color 0.15s, background 0.15s;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.theme-card:hover { border-color: #93c5fd; background: rgba(59,130,246,0.04); }
.theme-card.selected { border-color: #3b82f6; background: rgba(59,130,246,0.06); }
.theme-icon { color: var(--text-1, #1e293b); display: flex; align-items: center; justify-content: center; }
.theme-label { font-size: 13px; font-weight: 600; color: var(--text-1, #1e293b); }
.theme-hint  { font-size: 11px; color: var(--text-2, #64748b); }

/* Settings rows */
.settings-list { display: flex; flex-direction: column; }
.setting-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px; padding: 14px 0; border-bottom: 1px solid var(--border, #f1f5f9);
}
.setting-row:last-child { border-bottom: none; padding-bottom: 0; }
.setting-info { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.setting-label { font-size: 13px; font-weight: 600; color: var(--text-1, #1e293b); }
.setting-desc  { font-size: 12px; color: var(--text-2, #64748b); }
.setting-select {
  height: 36px; border: 1.5px solid var(--border, #e2e8f0); border-radius: 8px;
  padding: 0 10px; font-size: 13px; color: var(--text-1, #1e293b);
  background: var(--surface, white); outline: none; cursor: pointer;
  transition: border-color 0.15s; min-width: 180px; flex-shrink: 0;
}
.setting-select:focus { border-color: #3b82f6; }

/* Radio group */
.radio-group { display: flex; gap: 16px; flex-shrink: 0; }
.radio-opt {
  display: flex; align-items: center; gap: 6px; cursor: pointer;
  font-size: 13px; font-weight: 500; color: var(--text-1, #1e293b);
}
.radio-opt input[type="radio"] { accent-color: #3b82f6; width: 15px; height: 15px; cursor: pointer; }

/* Toggle list */
.toggle-list { display: flex; flex-direction: column; }
.toggle-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px; padding: 14px 0; border-bottom: 1px solid var(--border, #f1f5f9);
}
.toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
.toggle-info { display: flex; flex-direction: column; gap: 2px; }
.toggle-label { font-size: 13px; font-weight: 600; color: var(--text-1, #1e293b); }
.toggle-desc  { font-size: 12px; color: var(--text-2, #64748b); }

/* Toggle switch */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider {
  position: absolute; inset: 0; background: #cbd5e1; border-radius: 24px;
  cursor: pointer; transition: background 0.2s;
}
.toggle-slider::before {
  content: ''; position: absolute; width: 18px; height: 18px;
  left: 3px; bottom: 3px; background: white; border-radius: 50%;
  transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.toggle-switch input:checked + .toggle-slider { background: #3b82f6; }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }

/* Admin links */
.admin-links { display: flex; flex-direction: column; gap: 2px; }
.admin-link-row {
  display: flex; align-items: center; gap: 14px; padding: 14px 12px;
  border-radius: 8px; text-decoration: none; transition: background 0.15s;
  border: 1px solid transparent;
}
.admin-link-row:hover {
  background: rgba(59,130,246,0.05); border-color: var(--border, #e2e8f0);
}
.admin-link-icon {
  width: 36px; height: 36px; border-radius: 8px; background: rgba(99,102,241,0.1);
  color: #6366f1; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.admin-link-body { flex: 1; }
.admin-link-title { font-size: 13px; font-weight: 600; color: var(--text-1, #1e293b); margin-bottom: 2px; }
.admin-link-desc  { font-size: 12px; color: var(--text-2, #64748b); }
.admin-link-arrow { font-size: 18px; color: var(--text-3, #94a3b8); flex-shrink: 0; }

/* Save button row */
.action-row { display: flex; justify-content: flex-end; margin-bottom: 8px; }
.btn { height: 38px; padding: 0 22px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
.btn-save { background: #3b82f6; color: white; }
.btn-save:hover:not(:disabled) { background: #2563eb; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

/* Responsive */
@media (max-width: 640px) {
  .page { padding: 16px; }
  .profile-shortcut { flex-direction: column; align-items: flex-start; gap: 12px; }
  .btn-shortcut { align-self: flex-start; }
  .setting-row { flex-direction: column; align-items: flex-start; gap: 10px; }
  .setting-select { min-width: 100%; width: 100%; }
  .theme-options { gap: 8px; }
  .theme-card { min-width: 80px; padding: 14px 8px 10px; }
}
</style>
