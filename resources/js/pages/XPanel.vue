<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API   = '/api/_dp';
const H_KEY = 'X-Dev-K';
const S_KEY = '_dmk';

// — Auth —
const keyInput   = ref('');
const sessionKey = ref(sessionStorage.getItem(S_KEY) || '');
const authed     = ref(false);
const authErr    = ref('');
const authBusy   = ref(false);

// — Navigation —
const TAB_ITEMS = [
  { key: 'overview',   label: 'Overview'   },
  { key: 'users',      label: 'Users'      },
  { key: 'activity',   label: 'Activity'   },
  { key: 'inject',     label: 'Inject'     },
  { key: 'db',         label: 'Database'   },
  { key: 'commands',   label: 'Commands'   },
  { key: 'settings',   label: 'Settings'   },
  { key: 'broadcast',  label: 'Broadcast'  },
  { key: 'shutdown',   label: 'Shutdown'   },
];
const tab = ref('overview');

// — Data —
const info      = ref(null);
const users     = ref([]);
const roles     = ref([]);
const tables    = ref([]);
const settings  = ref([]);
const cmdOutput = ref('');
const cmdBusy   = ref(false);
const seedClass = ref('');
const dbLoading = ref(false);

// — User modal —
const editUser    = ref(null);
const addUser     = ref(null);
const deleteTarget = ref(null);
const userBusy    = ref(false);
const userErr     = ref('');

// — Login as —
const APP_BASE        = import.meta.env.VITE_APP_BASE || '/';
const loginAsBusy     = ref(null);
const loginAsErr      = ref('');
const superAdminBusy  = ref(false);
const superAdminErr   = ref('');

// — Settings edit state —
const settingEdits = ref({});
const newKey   = ref('');
const newVal   = ref('');
const savingKey = ref('');

// — Shutdown —
const shutdown     = ref({ active: false, message: '' });
const shutdownBusy = ref(false);
const shutdownMsg  = ref('');

// — Inject —
const INJECT_PRESETS = [
  { key: 'contacts',    label: 'Fake Contacts',   desc: 'Realistic fake company records'      },
  { key: 'todos',       label: 'Fake Todos',       desc: 'Tasks on existing contacts'          },
  { key: 'deals',       label: 'Fake Deals',       desc: 'Pipeline deals on existing contacts' },
  { key: 'followups',   label: 'Fake Follow-ups',  desc: 'Follow-ups on existing todos'        },
  { key: 'edge_cases',  label: 'Edge Cases',       desc: 'XSS/SQLi/Unicode/overflow records'   },
  { key: 'full_dataset',label: 'Full Dataset',     desc: 'Contacts + todos + deals bundle'     },
];
const injectPreset       = ref('contacts');
const injectCount        = ref(25);
const injectLabel        = ref('');
const injectBusy         = ref(false);
const injectErr          = ref('');
const injectOk           = ref('');
const injections         = ref([]);
const injectionsLoading  = ref(false);
const rollbackBusy       = ref(null);

// — Activity —
const activityUsers   = ref([]);
const activityLoading = ref(false);
const blockBusy       = ref(null);

// — Quarantine —
const quarantineTarget = ref(null);
const quarantineReason = ref('');
const quarantineBusy   = ref(false);
const quarantineErr    = ref('');
const quarantineResult = ref(null);
const pwCopied          = ref(false);

// — Broadcast —
const adminUsers = ref([]);
const bcForm = ref({
  title:          '',
  body:           '',
  urgency:        'normal',
  target_user_id: '',
  published_at:   '',
  expires_at:     '',
  as_user_id:     '',
});
const bcBusy    = ref(false);
const bcSuccess = ref(false);
const bcErr     = ref('');

function http() {
  return axios.create({
    baseURL: API,
    headers: { [H_KEY]: sessionKey.value, Accept: 'application/json' },
  });
}

// ─── Auth ─────────────────────────────────────────────────────────
async function tryUnlock() {
  authBusy.value = true;
  authErr.value  = '';
  try {
    const r = await axios.get(`${API}/info`, {
      headers: { [H_KEY]: keyInput.value, Accept: 'application/json' },
    });
    sessionKey.value = keyInput.value;
    sessionStorage.setItem(S_KEY, keyInput.value);
    info.value  = r.data;
    authed.value = true;
  } catch (e) {
    authErr.value = (e.response?.status === 404 || e.response?.status === 401)
      ? 'Invalid key.'
      : 'Connection error — check console.';
  } finally {
    authBusy.value = false;
  }
}

function lock() {
  sessionStorage.removeItem(S_KEY);
  sessionKey.value = '';
  authed.value     = false;
  keyInput.value   = '';
}

// ─── Tabs ─────────────────────────────────────────────────────────
async function switchTab(t) {
  tab.value = t;
  if (t === 'overview'  && !info.value)             await fetchInfo();
  if (t === 'users'     && !users.value.length)     await fetchUsers();
  if (t === 'activity')                              await fetchActivity();
  if (t === 'inject')                                await fetchInjections();
  if (t === 'db')                                    await fetchDb();
  if (t === 'settings'  && !settings.value.length)  await fetchSettings();
  if (t === 'broadcast' && !adminUsers.value.length) await fetchAdminUsers();
  if (t === 'shutdown')                              await fetchShutdown();
}

async function fetchInfo() {
  const r = await http().get('/info');
  info.value = r.data;
}

async function fetchUsers() {
  const r = await http().get('/users');
  users.value = r.data.users;
  roles.value = r.data.roles;
}

async function fetchDb() {
  dbLoading.value = true;
  try {
    const r = await http().get('/db');
    tables.value = r.data.tables;
  } finally {
    dbLoading.value = false;
  }
}

async function fetchSettings() {
  const r = await http().get('/settings');
  settings.value = r.data.settings;
  settingEdits.value = Object.fromEntries(r.data.settings.map(s => [s.key, s.value ?? '']));
}

// ─── Inject & rollback ────────────────────────────────────────────
async function fetchInjections() {
  injectionsLoading.value = true;
  try {
    const r = await http().get('/inject');
    injections.value = r.data.injections;
  } finally {
    injectionsLoading.value = false;
  }
}

async function runInject() {
  injectBusy.value = true;
  injectErr.value  = '';
  injectOk.value   = '';
  try {
    const payload = { preset: injectPreset.value, count: injectCount.value };
    if (injectLabel.value.trim()) payload.label = injectLabel.value.trim();
    const r = await http().post('/inject', payload);
    injectOk.value    = `Injected ${r.data.count} records successfully.`;
    injectLabel.value = '';
    await fetchInjections();
  } catch (e) {
    const errs = e.response?.data?.errors;
    injectErr.value = errs
      ? Object.values(errs)[0]?.[0]
      : (e.response?.data?.message || 'Injection failed.');
  } finally {
    injectBusy.value = false;
  }
}

async function rollbackInjection(id) {
  rollbackBusy.value = id;
  try {
    await http().delete(`/inject/${id}`);
    await fetchInjections();
  } finally {
    rollbackBusy.value = null;
  }
}

// ─── Activity & blocking ──────────────────────────────────────────
async function fetchActivity() {
  activityLoading.value = true;
  try {
    const r = await http().get('/activity');
    activityUsers.value = r.data.users;
  } finally {
    activityLoading.value = false;
  }
}

async function toggleBlock(u) {
  blockBusy.value = u.id;
  try {
    if (u.is_blocked) {
      await http().delete(`/users/${u.id}/block`);
    } else {
      await http().post(`/users/${u.id}/block`);
    }
    await fetchActivity();
  } finally {
    blockBusy.value = null;
  }
}

// ─── Quarantine ────────────────────────────────────────────────────
function startQuarantine(u) {
  quarantineTarget.value = u;
  quarantineReason.value = '';
  quarantineErr.value    = '';
  quarantineResult.value = null;
  pwCopied.value          = false;
}

async function confirmQuarantine() {
  quarantineBusy.value = true;
  quarantineErr.value  = '';
  try {
    const payload = {};
    if (quarantineReason.value.trim()) payload.reason = quarantineReason.value.trim();
    const r = await http().post(`/users/${quarantineTarget.value.id}/quarantine`, payload);
    quarantineResult.value = { password: r.data.password };
    await fetchActivity();
  } catch (e) {
    quarantineErr.value = e.response?.data?.message || 'Quarantine failed.';
  } finally {
    quarantineBusy.value = false;
  }
}

function closeQuarantine() {
  quarantineTarget.value = null;
  quarantineResult.value = null;
}

async function copyQuarantinePassword() {
  try {
    await navigator.clipboard.writeText(quarantineResult.value.password);
    pwCopied.value = true;
    setTimeout(() => { pwCopied.value = false; }, 2000);
  } catch {
    // clipboard API unavailable — password is still visible to select/copy manually
  }
}

function activityStatus(lastApiCall) {
  if (!lastApiCall) return 'never';
  const mins = (Date.now() - new Date(lastApiCall)) / 60000;
  if (mins < 30)   return 'online';
  if (mins < 1440) return 'recent';
  return 'offline';
}

// ─── Users CRUD ───────────────────────────────────────────────────
function startEdit(u) {
  editUser.value = {
    id:                    u.id,
    name:                  u.name,
    email:                 u.email,
    role:                  u.roles[0] || '',
    is_approved:           u.is_approved,
    inactivity_flagged_at: u.inactivity_flagged_at,
    email_verified_at:     u.email_verified_at,
    password:              '',
  };
  userErr.value = '';
}

function startAdd() {
  addUser.value = { name: '', email: '', password: '', role: roles.value[0] || '' };
  userErr.value = '';
}

async function saveEdit() {
  userBusy.value = true;
  userErr.value  = '';
  try {
    const payload = { ...editUser.value };
    if (!payload.password) delete payload.password;
    await http().put(`/users/${payload.id}`, payload);
    editUser.value = null;
    await fetchUsers();
  } catch (e) {
    userErr.value = e.response?.data?.message || 'Save failed.';
  } finally {
    userBusy.value = false;
  }
}

async function saveAdd() {
  userBusy.value = true;
  userErr.value  = '';
  try {
    await http().post('/users', addUser.value);
    addUser.value = null;
    await fetchUsers();
  } catch (e) {
    const errs = e.response?.data?.errors;
    userErr.value = errs
      ? Object.values(errs)[0]?.[0]
      : (e.response?.data?.message || 'Create failed.');
  } finally {
    userBusy.value = false;
  }
}

function startDelete(u) {
  deleteTarget.value = u;
}

async function confirmDeleteUser() {
  userBusy.value = true;
  try {
    await http().delete(`/users/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    await fetchUsers();
  } finally {
    userBusy.value = false;
  }
}

// ─── Login as ─────────────────────────────────────────────────────
function enterApp(token, user) {
  localStorage.setItem('crm_token', token);
  localStorage.setItem('crm_user', JSON.stringify(user));
  // New tab: same-origin localStorage is shared, so the new tab boots
  // already authenticated. Keeps this DevPanel session untouched.
  window.open(APP_BASE, '_blank');
}

async function loginAsUser(u) {
  loginAsBusy.value = u.id;
  loginAsErr.value  = '';
  try {
    const r = await http().post(`/users/${u.id}/login-as`);
    enterApp(r.data.token, r.data.user);
  } catch (e) {
    loginAsErr.value = e.response?.data?.error || e.response?.data?.message || 'Failed to log in as user.';
  } finally {
    loginAsBusy.value = null;
  }
}

async function enterAsSuperAdmin() {
  superAdminBusy.value = true;
  superAdminErr.value  = '';
  try {
    const r = await http().post('/login-as/super-admin');
    enterApp(r.data.token, r.data.user);
  } catch (e) {
    superAdminErr.value = e.response?.data?.error || e.response?.data?.message || 'Failed to enter as super-admin.';
  } finally {
    superAdminBusy.value = false;
  }
}

// ─── Artisan commands ─────────────────────────────────────────────
const COMMANDS = [
  { key: 'migrate',                label: 'Run Migrations',        desc: 'php artisan migrate --force',          cat: 'Database'  },
  { key: 'migrate:rollback',       label: 'Rollback Last Batch',   desc: 'Rolls back the last migration batch',  cat: 'Database'  },
  { key: 'migrate:status',         label: 'Migration Status',      desc: 'Show which migrations have run',       cat: 'Database'  },
  { key: 'db:seed',                label: 'Run Seeder',            desc: 'php artisan db:seed (class below)',    cat: 'Database', hasExtra: true },
  { key: 'cache:clear',            label: 'Clear Cache',           desc: 'php artisan cache:clear',              cat: 'Cache'     },
  { key: 'config:clear',           label: 'Clear Config Cache',    desc: 'Remove compiled config',               cat: 'Cache'     },
  { key: 'config:cache',           label: 'Cache Config',          desc: 'Re-compile configuration cache',       cat: 'Cache'     },
  { key: 'route:clear',            label: 'Clear Route Cache',     desc: 'Remove compiled route list',           cat: 'Cache'     },
  { key: 'route:cache',            label: 'Cache Routes',          desc: 'Compile all routes for performance',   cat: 'Cache'     },
  { key: 'view:clear',             label: 'Clear View Cache',      desc: 'Delete compiled Blade templates',      cat: 'Cache'     },
  { key: 'view:cache',             label: 'Cache Views',           desc: 'Pre-compile all Blade templates',      cat: 'Cache'     },
  { key: 'permission:cache-reset', label: 'Reset Permissions',     desc: 'Flush Spatie permission cache',        cat: 'System'    },
  { key: 'queue:restart',          label: 'Restart Queue Workers', desc: 'Signal all workers to restart',        cat: 'System'    },
  { key: 'storage:link',           label: 'Create Storage Link',   desc: 'Link public/storage → storage/app/public', cat: 'System' },
];

const cmdsByCategory = computed(() => {
  return COMMANDS.reduce((acc, c) => {
    (acc[c.cat] ??= []).push(c);
    return acc;
  }, {});
});

async function runCmd(cmd) {
  cmdBusy.value   = true;
  cmdOutput.value = `▶ ${cmd.key}…`;
  try {
    const extra = cmd.hasExtra ? { class: seedClass.value } : {};
    const r = await http().post('/artisan', { command: cmd.key, extra });
    cmdOutput.value = r.data.output || r.data.error || 'Done.';
  } catch (e) {
    cmdOutput.value = `✗ ${e.response?.data?.error || e.message}`;
  } finally {
    cmdBusy.value = false;
  }
}

// ─── Shutdown ─────────────────────────────────────────────────────
async function fetchShutdown() {
  const r = await http().get('/maintenance');
  shutdown.value = { active: r.data.active, message: r.data.message };
  shutdownMsg.value = '';
}

async function toggleShutdown() {
  shutdownBusy.value = true;
  shutdownMsg.value  = '';
  try {
    const next = !shutdown.value.active;
    await http().put('/maintenance', { active: next, message: shutdown.value.message });
    shutdown.value.active = next;
    shutdownMsg.value = next ? 'Maintenance mode enabled.' : 'System is back online.';
  } catch (e) {
    shutdownMsg.value = e.response?.data?.message || 'Failed to update.';
  } finally {
    shutdownBusy.value = false;
  }
}

// ─── Broadcast ────────────────────────────────────────────────────
async function fetchAdminUsers() {
  const r = await http().get('/admin-users');
  adminUsers.value = r.data.users;
  if (adminUsers.value.length && !bcForm.value.as_user_id) {
    bcForm.value.as_user_id = adminUsers.value[0].id;
  }
}

async function sendBroadcast() {
  bcBusy.value    = true;
  bcErr.value     = '';
  bcSuccess.value = false;
  try {
    const payload = { ...bcForm.value };
    if (!payload.target_user_id) delete payload.target_user_id;
    if (!payload.published_at)   delete payload.published_at;
    if (!payload.expires_at)     delete payload.expires_at;
    if (!payload.as_user_id)     delete payload.as_user_id;
    await http().post('/announcement', payload);
    bcSuccess.value = true;
    bcForm.value.title          = '';
    bcForm.value.body           = '';
    bcForm.value.expires_at     = '';
    bcForm.value.target_user_id = '';
    bcForm.value.published_at   = '';
  } catch (e) {
    const errs = e.response?.data?.errors;
    bcErr.value = errs
      ? Object.values(errs)[0]?.[0]
      : (e.response?.data?.message || 'Failed to send.');
  } finally {
    bcBusy.value = false;
  }
}

// ─── Settings ─────────────────────────────────────────────────────
async function saveSetting(key) {
  savingKey.value = key;
  try {
    await http().put('/settings', { key, value: settingEdits.value[key] });
  } finally {
    savingKey.value = '';
  }
}

async function addNewSetting() {
  if (!newKey.value.trim()) return;
  await http().post('/settings', { key: newKey.value.trim(), value: newVal.value });
  newKey.value = '';
  newVal.value = '';
  await fetchSettings();
}

// ─── Utils ────────────────────────────────────────────────────────
function fmtBytes(b) {
  if (!b) return '0 B';
  const s = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(b) / Math.log(1024));
  return `${(b / 1024 ** i).toFixed(1)} ${s[i]}`;
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString();
}

function diskPct(free, total) {
  if (!total) return 0;
  return Math.round((1 - free / total) * 100);
}

// ─── Mount ────────────────────────────────────────────────────────
onMounted(async () => {
  if (!sessionKey.value) return;
  try {
    const r = await axios.get(`${API}/info`, {
      headers: { [H_KEY]: sessionKey.value, Accept: 'application/json' },
    });
    info.value   = r.data;
    authed.value = true;
  } catch {
    sessionStorage.removeItem(S_KEY);
    sessionKey.value = '';
  }
});
</script>

<template>
  <!-- ── Lock screen ── -->
  <div v-if="!authed" class="xp-lock">
    <div class="xp-lock-card">
      <svg class="xp-lock-icon" width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 1a5 5 0 0 0-5 5v3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-2V6a5 5 0 0 0-5-5zm-3 5a3 3 0 1 1 6 0v3H9V6zm3 8a2 2 0 0 1 1 3.732V20h-2v-2.268A2 2 0 0 1 12 14z"/>
      </svg>
      <h1 class="xp-lock-title">System Access</h1>
      <p class="xp-lock-sub">Developer key required to continue.</p>
      <form @submit.prevent="tryUnlock" class="xp-lock-form">
        <input
          v-model="keyInput"
          type="password"
          placeholder="Enter access key…"
          class="xp-input"
          :disabled="authBusy"
          autocomplete="off"
          autofocus
        />
        <p v-if="authErr" class="xp-err-msg">{{ authErr }}</p>
        <button type="submit" class="xp-btn-primary" :disabled="authBusy || !keyInput.trim()">
          {{ authBusy ? 'Verifying…' : 'Unlock' }}
        </button>
      </form>
    </div>
  </div>

  <!-- ── Main console ── -->
  <div v-else class="xp-root">

    <!-- Header -->
    <header class="xp-header">
      <div class="xp-header-left">
        <span class="xp-status-dot"></span>
        <span class="xp-brand">System Console</span>
        <span class="xp-env-badge" :class="info?.env === 'production' ? 'env-prod' : 'env-local'">{{ info?.env }}</span>
      </div>

      <nav class="xp-nav">
        <button
          v-for="t in TAB_ITEMS" :key="t.key"
          class="xp-nav-btn" :class="{ active: tab === t.key }"
          @click="switchTab(t.key)"
        >{{ t.label }}</button>
      </nav>

      <button
        class="xp-btn-sm xp-super-btn"
        :disabled="superAdminBusy"
        @click="enterAsSuperAdmin"
        title="Open the CRM in a new tab, already signed in as super-admin — no password needed, works even if the password was changed"
      >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2L4 5v6c0 5.25 3.5 9.74 8 11 4.5-1.26 8-5.75 8-11V5l-8-3zm-1.2 14.2L7.5 12.9l1.4-1.4 1.9 1.9 4.9-4.9 1.4 1.4-6.3 6.3z"/>
        </svg>
        {{ superAdminBusy ? 'Opening…' : 'Enter as Super Admin' }}
      </button>

      <button class="xp-icon-btn" @click="lock" title="Lock session">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 1a5 5 0 0 0-5 5v3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-2V6a5 5 0 0 0-5-5zm-3 5a3 3 0 1 1 6 0v3H9V6z"/>
        </svg>
      </button>
    </header>

    <p v-if="superAdminErr" class="xp-err-msg" style="padding:8px 20px 0;margin:0">{{ superAdminErr }}</p>

    <!-- Body -->
    <main class="xp-main">

      <!-- ══ OVERVIEW ══ -->
      <div v-if="tab === 'overview'" class="xp-pane">
        <div v-if="!info" class="xp-empty">Loading…</div>
        <div v-else>
          <div class="xp-grid">

            <div class="xp-card">
              <div class="xp-card-title">Application</div>
              <div class="xp-rows">
                <div class="xp-row">
                  <span class="xp-rl">Environment</span>
                  <span class="xp-badge" :class="info.env === 'production' ? 'bdg-red' : 'bdg-gray'">{{ info.env }}</span>
                </div>
                <div class="xp-row">
                  <span class="xp-rl">Debug</span>
                  <span class="xp-badge" :class="info.debug ? 'bdg-yellow' : 'bdg-green'">{{ info.debug ? 'ON' : 'OFF' }}</span>
                </div>
                <div class="xp-row">
                  <span class="xp-rl">URL</span>
                  <span class="xp-rv mono sm">{{ info.url }}</span>
                </div>
                <div class="xp-row">
                  <span class="xp-rl">Timezone</span>
                  <span class="xp-rv mono sm">{{ info.timezone }}</span>
                </div>
              </div>
            </div>

            <div class="xp-card">
              <div class="xp-card-title">Runtime</div>
              <div class="xp-rows">
                <div class="xp-row"><span class="xp-rl">PHP</span><span class="xp-rv mono">{{ info.php }}</span></div>
                <div class="xp-row"><span class="xp-rl">Laravel</span><span class="xp-rv mono">{{ info.laravel }}</span></div>
                <div class="xp-row"><span class="xp-rl">Cache driver</span><span class="xp-rv mono">{{ info.cache }}</span></div>
                <div class="xp-row"><span class="xp-rl">Queue driver</span><span class="xp-rv mono">{{ info.queue }}</span></div>
              </div>
            </div>

            <div class="xp-card">
              <div class="xp-card-title">Database</div>
              <div class="xp-rows">
                <div class="xp-row">
                  <span class="xp-rl">Connection</span>
                  <span class="xp-badge" :class="info.db_ok ? 'bdg-green' : 'bdg-red'">{{ info.db_ok ? 'OK' : 'FAILED' }}</span>
                </div>
                <div class="xp-row"><span class="xp-rl">Driver</span><span class="xp-rv mono">{{ info.db_driver }}</span></div>
                <div class="xp-row"><span class="xp-rl">Name</span><span class="xp-rv mono">{{ info.db_name }}</span></div>
                <div class="xp-row"><span class="xp-rl">Host:Port</span><span class="xp-rv mono">{{ info.db_host }}:{{ info.db_port }}</span></div>
              </div>
            </div>

            <div class="xp-card">
              <div class="xp-card-title">Storage</div>
              <div class="xp-rows">
                <div class="xp-row">
                  <span class="xp-rl">Storage dir</span>
                  <span class="xp-badge" :class="info.storage_ok ? 'bdg-green' : 'bdg-red'">{{ info.storage_ok ? 'Writable' : 'READ-ONLY' }}</span>
                </div>
                <div class="xp-row"><span class="xp-rl">Free</span><span class="xp-rv mono">{{ fmtBytes(info.disk_free) }}</span></div>
                <div class="xp-row"><span class="xp-rl">Total</span><span class="xp-rv mono">{{ fmtBytes(info.disk_total) }}</span></div>
                <div class="xp-row">
                  <span class="xp-rl">Used {{ diskPct(info.disk_free, info.disk_total) }}%</span>
                  <div class="xp-bar">
                    <div class="xp-bar-fill" :style="{ width: diskPct(info.disk_free, info.disk_total) + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>

          </div>
          <button class="xp-btn-sm" style="margin-top:16px" @click="fetchInfo">↻ Refresh</button>
        </div>
      </div>

      <!-- ══ USERS ══ -->
      <div v-if="tab === 'users'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>Users <span class="xp-count">{{ users.length }}</span></h2>
          <div style="display:flex;gap:8px">
            <button class="xp-btn-sm" @click="fetchUsers">↻ Refresh</button>
            <button class="xp-btn-primary" @click="startAdd">+ Add User</button>
          </div>
        </div>
        <p v-if="loginAsErr" class="xp-err-msg" style="margin-bottom:12px">{{ loginAsErr }}</p>
        <div class="xp-table-wrap">
          <table class="xp-table">
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
                <th>Approved</th><th>Flagged</th><th>Last Login</th><th>Logins</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id">
                <td class="mono muted sm">{{ u.id }}</td>
                <td>{{ u.name }}</td>
                <td class="mono sm">{{ u.email }}</td>
                <td>
                  <span v-for="r in u.roles" :key="r" class="xp-role">{{ r }}</span>
                  <span v-if="!u.roles.length" class="muted">—</span>
                </td>
                <td>
                  <span class="xp-badge" :class="u.is_approved ? 'bdg-green' : 'bdg-yellow'">{{ u.is_approved ? 'Yes' : 'No' }}</span>
                </td>
                <td>
                  <span v-if="u.inactivity_flagged_at" class="xp-badge bdg-red">Flagged</span>
                  <span v-else class="muted">—</span>
                </td>
                <td class="mono muted sm">{{ fmtDate(u.last_login_at) }}</td>
                <td class="mono muted">{{ u.login_count }}</td>
                <td>
                  <div class="xp-actions">
                    <button
                      class="xp-btn-sm"
                      :disabled="loginAsBusy === u.id"
                      @click="loginAsUser(u)"
                    >{{ loginAsBusy === u.id ? '…' : 'Login as' }}</button>
                    <button class="xp-btn-sm" @click="startEdit(u)">Edit</button>
                    <button class="xp-btn-sm danger" @click="startDelete(u)">Delete</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!users.length">
                <td colspan="9" class="muted" style="text-align:center;padding:24px">Loading users…</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ ACTIVITY ══ -->
      <div v-if="tab === 'activity'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>User Activity <span class="xp-count">{{ activityUsers.length }}</span></h2>
          <button class="xp-btn-sm" @click="fetchActivity">↻ Refresh</button>
        </div>
        <div v-if="activityLoading" class="xp-empty">Loading activity…</div>
        <div v-else class="xp-table-wrap">
          <table class="xp-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>Last Login</th>
                <th>Last API Call</th>
                <th>Sessions</th>
                <th>Logins</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in activityUsers" :key="u.id">
                <td>
                  <div class="xp-user-cell">
                    <span class="xp-act-dot" :class="'dot-' + activityStatus(u.last_api_call)"></span>
                    <div>
                      <div style="font-weight:500;font-size:13px">{{ u.name }}</div>
                      <div class="mono muted sm">{{ u.email }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span v-for="r in u.roles" :key="r" class="xp-role">{{ r }}</span>
                  <span v-if="!u.roles.length" class="muted">—</span>
                </td>
                <td class="mono muted sm">{{ fmtDate(u.last_login_at) }}</td>
                <td class="mono sm" :class="activityStatus(u.last_api_call) === 'online' ? 'clr-ok' : 'muted'">
                  {{ u.last_api_call ? fmtDate(u.last_api_call) : '—' }}
                </td>
                <td class="mono" :class="u.active_sessions > 0 ? 'clr-ok' : 'muted'">{{ u.active_sessions }}</td>
                <td class="mono muted">{{ u.login_count }}</td>
                <td>
                  <span v-if="u.is_blocked" class="xp-badge bdg-red">Blocked</span>
                  <span v-else-if="!u.is_approved" class="xp-badge bdg-yellow">Pending</span>
                  <span v-else class="xp-badge bdg-gray">Active</span>
                </td>
                <td>
                  <div class="xp-actions">
                    <button
                      class="xp-btn-sm"
                      :class="u.is_blocked ? '' : 'danger'"
                      :disabled="blockBusy === u.id"
                      @click="toggleBlock(u)"
                    >{{ blockBusy === u.id ? '…' : (u.is_blocked ? 'Unblock' : 'Block') }}</button>
                    <button class="xp-btn-sm danger" @click="startQuarantine(u)">Quarantine</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!activityUsers.length && !activityLoading">
                <td colspan="8" class="muted" style="text-align:center;padding:24px">No users found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ INJECT ══ -->
      <div v-if="tab === 'inject'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>Data Injection</h2>
        </div>

        <!-- Preset grid -->
        <div class="xp-inject-grid">
          <div
            v-for="p in INJECT_PRESETS" :key="p.key"
            class="xp-preset-card"
            :class="{ selected: injectPreset === p.key }"
            @click="injectPreset = p.key; injectOk = ''; injectErr = ''"
          >
            <span class="xp-preset-label">{{ p.label }}</span>
            <span class="xp-preset-desc">{{ p.desc }}</span>
          </div>
        </div>

        <!-- Controls -->
        <div class="xp-inject-controls">
          <div v-if="injectPreset !== 'edge_cases'" class="xp-inject-row">
            <span class="xp-rl" style="min-width:52px">Count</span>
            <div class="xp-count-btns">
              <button
                v-for="n in [10, 25, 50, 100]" :key="n"
                class="xp-btn-sm" :class="{ 'xp-btn-sm-active': injectCount === n }"
                @click="injectCount = n"
              >{{ n }}</button>
            </div>
          </div>
          <div v-else class="xp-inject-row">
            <span class="xp-rl" style="min-width:52px">Count</span>
            <span class="muted sm">Fixed — injects 10 predefined boundary records</span>
          </div>

          <div class="xp-inject-row">
            <span class="xp-rl" style="min-width:52px">Label</span>
            <input v-model="injectLabel" class="xp-input sm" placeholder="Optional batch label…" style="flex:1" />
          </div>
        </div>

        <p v-if="injectErr" class="xp-err-msg" style="margin-top:12px">{{ injectErr }}</p>
        <p v-if="injectOk" style="color:var(--xok);font-size:13px;margin-top:12px">{{ injectOk }}</p>

        <button
          class="xp-btn-primary"
          style="margin-top:16px"
          :disabled="injectBusy"
          @click="runInject"
        >{{ injectBusy ? 'Injecting…' : 'Inject Data' }}</button>

        <!-- Past injections -->
        <div style="margin-top:36px">
          <div class="xp-pane-head" style="margin-bottom:12px">
            <h2>Past Injections <span class="xp-count">{{ injections.length }}</span></h2>
            <button class="xp-btn-sm" @click="fetchInjections">↻ Refresh</button>
          </div>

          <div v-if="injectionsLoading" class="xp-empty">Loading…</div>
          <p v-else-if="!injections.length" class="muted sm">No injections recorded yet.</p>
          <div v-else class="xp-table-wrap">
            <table class="xp-table">
              <thead>
                <tr><th>Label</th><th>Preset</th><th>Records</th><th>Injected At</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="inj in injections" :key="inj.id">
                  <td>{{ inj.label }}</td>
                  <td><span class="xp-role">{{ inj.preset }}</span></td>
                  <td class="mono">{{ inj.record_count }}</td>
                  <td class="muted mono sm">{{ fmtDate(inj.created_at) }}</td>
                  <td>
                    <button
                      class="xp-btn-sm danger"
                      :disabled="rollbackBusy === inj.id"
                      @click="rollbackInjection(inj.id)"
                    >{{ rollbackBusy === inj.id ? 'Rolling back…' : 'Rollback' }}</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ══ DATABASE ══ -->
      <div v-if="tab === 'db'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>Tables <span class="xp-count">{{ tables.length }}</span></h2>
          <button class="xp-btn-sm" @click="fetchDb">↻ Refresh</button>
        </div>
        <div v-if="dbLoading" class="xp-empty">Querying tables…</div>
        <div v-else class="xp-table-wrap">
          <table class="xp-table">
            <thead>
              <tr><th>#</th><th>Table</th><th>Rows</th></tr>
            </thead>
            <tbody>
              <tr v-for="(t, i) in tables" :key="t.table">
                <td class="muted mono sm">{{ i + 1 }}</td>
                <td class="mono">{{ t.table }}</td>
                <td class="mono" :class="t.rows === 0 ? 'muted' : t.rows > 50 ? 'clr-ok' : ''">
                  {{ t.rows ?? '?' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ COMMANDS ══ -->
      <div v-if="tab === 'commands'" class="xp-pane">
        <div v-for="(cmds, cat) in cmdsByCategory" :key="cat" class="xp-cmd-group">
          <div class="xp-card-title" style="margin-bottom:8px">{{ cat }}</div>
          <div class="xp-cmd-list">
            <div v-for="cmd in cmds" :key="cmd.key" class="xp-cmd-row">
              <div class="xp-cmd-info">
                <span class="xp-cmd-label">{{ cmd.label }}</span>
                <span class="xp-cmd-desc">{{ cmd.desc }}</span>
              </div>
              <input
                v-if="cmd.hasExtra"
                v-model="seedClass"
                class="xp-input sm"
                placeholder="SeederClass"
                style="width:190px;flex-shrink:0"
              />
              <button class="xp-btn-sm" :disabled="cmdBusy" @click="runCmd(cmd)">Run</button>
            </div>
          </div>
        </div>

        <div class="xp-output-block">
          <div class="xp-output-label">Output</div>
          <pre class="xp-output">{{ cmdOutput || 'No output yet.' }}</pre>
        </div>
      </div>

      <!-- ══ SETTINGS ══ -->
      <div v-if="tab === 'settings'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>System Settings</h2>
          <button class="xp-btn-sm" @click="fetchSettings">↻ Refresh</button>
        </div>

        <div class="xp-settings-list">
          <div v-for="s in settings" :key="s.key" class="xp-setting-row">
            <span class="xp-setting-key mono">{{ s.key }}</span>
            <input v-model="settingEdits[s.key]" class="xp-input sm" style="flex:1" />
            <button
              class="xp-btn-sm"
              :disabled="savingKey === s.key"
              @click="saveSetting(s.key)"
            >{{ savingKey === s.key ? '…' : 'Save' }}</button>
          </div>
          <p v-if="!settings.length" class="muted" style="padding:12px 0">No settings found.</p>
        </div>

        <div class="xp-add-setting">
          <div class="xp-card-title" style="margin-bottom:10px">Add Setting</div>
          <div class="xp-add-row">
            <input v-model="newKey" class="xp-input sm" placeholder="key" style="width:200px" />
            <input v-model="newVal" class="xp-input sm" placeholder="value" style="flex:1" />
            <button class="xp-btn-primary" :disabled="!newKey.trim()" @click="addNewSetting">Add</button>
          </div>
        </div>
      </div>

      <!-- ══ BROADCAST ══ -->
      <div v-if="tab === 'broadcast'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>Send Announcement</h2>
        </div>

        <div class="xp-bc-wrap">
          <div class="xp-form" style="max-width:600px">

            <label>Title</label>
            <input v-model="bcForm.title" class="xp-input" placeholder="Announcement title" />

            <label>Body</label>
            <textarea v-model="bcForm.body" class="xp-input xp-textarea" placeholder="Write your announcement…" rows="5"></textarea>

            <label>Urgency</label>
            <select v-model="bcForm.urgency" class="xp-select">
              <option value="normal">Normal</option>
              <option value="urgent">Urgent</option>
            </select>

            <label>Send as <small style="font-weight:400;color:var(--xm)">(appears as author in the notice board)</small></label>
            <select v-model="bcForm.as_user_id" class="xp-select">
              <option value="">— default (super-admin) —</option>
              <option v-for="u in adminUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
            </select>

            <label>Target User <small style="font-weight:400;color:var(--xm)">(leave blank to send to everyone)</small></label>
            <select v-model="bcForm.target_user_id" class="xp-select">
              <option value="">Everyone</option>
              <option v-for="u in adminUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label>Publish At <small style="font-weight:400;color:var(--xm)">(blank = now)</small></label>
                <input v-model="bcForm.published_at" class="xp-input" type="datetime-local" />
              </div>
              <div>
                <label>Expires At <small style="font-weight:400;color:var(--xm)">(optional)</small></label>
                <input v-model="bcForm.expires_at" class="xp-input" type="date" />
              </div>
            </div>
          </div>

          <p v-if="bcErr" class="xp-err-msg" style="margin-top:12px">{{ bcErr }}</p>
          <p v-if="bcSuccess" style="color:var(--xok);font-size:13px;margin-top:12px">Announcement sent successfully.</p>

          <button
            class="xp-btn-primary"
            style="margin-top:16px"
            :disabled="bcBusy || !bcForm.title.trim() || !bcForm.body.trim()"
            @click="sendBroadcast"
          >{{ bcBusy ? 'Sending…' : 'Send Announcement' }}</button>
        </div>
      </div>

      <!-- ══ SHUTDOWN ══ -->
      <div v-if="tab === 'shutdown'" class="xp-pane">
        <div class="xp-pane-head">
          <h2>System Shutdown</h2>
          <button class="xp-btn-sm" @click="fetchShutdown">↻ Refresh</button>
        </div>

        <div class="xp-card" style="max-width:540px">
          <div class="xp-card-title">Current Status</div>
          <div class="xp-row" style="margin-bottom:20px">
            <span class="xp-rl">System is</span>
            <span class="xp-badge" :class="shutdown.active ? 'bdg-red' : 'bdg-green'">
              {{ shutdown.active ? 'OFFLINE — Maintenance' : 'ONLINE' }}
            </span>
          </div>

          <div class="xp-card-title">Message shown to users</div>
          <textarea
            v-model="shutdown.message"
            class="xp-input xp-textarea"
            rows="3"
            placeholder="System is currently under maintenance. Please try again later."
            style="margin-top:6px;margin-bottom:16px"
          ></textarea>

          <div v-if="!shutdown.active" class="xp-warn-box">
            Enabling maintenance mode will immediately block all CRM users from the API. This panel will stay accessible.
          </div>

          <button
            :class="shutdown.active ? 'xp-btn-sm' : 'xp-btn-danger'"
            :disabled="shutdownBusy"
            @click="toggleShutdown"
            style="margin-top:14px;width:100%;padding:10px 0;font-size:13px;font-weight:600"
          >
            {{ shutdownBusy ? 'Applying…' : (shutdown.active ? 'Bring System Online' : 'Enable Maintenance Mode') }}
          </button>

          <p v-if="shutdownMsg" :style="{ color: shutdown.active ? 'var(--xer)' : 'var(--xok)', fontSize: '12px', marginTop: '10px' }">
            {{ shutdownMsg }}
          </p>
        </div>
      </div>

    </main>

    <!-- ══ EDIT USER MODAL ══ -->
    <div v-if="editUser" class="xp-overlay" @click.self="editUser = null">
      <div class="xp-modal">
        <h3>Edit User</h3>
        <div class="xp-form">
          <label>Name</label>
          <input v-model="editUser.name" class="xp-input" />

          <label>Email</label>
          <input v-model="editUser.email" class="xp-input" type="email" />

          <label>New Password <small style="font-weight:400;color:var(--xm)">(blank = keep current)</small></label>
          <input v-model="editUser.password" class="xp-input" type="password" placeholder="Leave blank to keep" />

          <label>Role</label>
          <select v-model="editUser.role" class="xp-select">
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>

          <label class="xp-check-lbl">
            <input type="checkbox" v-model="editUser.is_approved" />
            Approved
          </label>

          <label class="xp-check-lbl">
            <input
              type="checkbox"
              :checked="!!editUser.inactivity_flagged_at"
              @change="e => { editUser.inactivity_flagged_at = e.target.checked ? (editUser.inactivity_flagged_at || new Date().toISOString()) : null; }"
            />
            Inactivity Flagged
            <span v-if="editUser.inactivity_flagged_at" class="muted sm" style="margin-left:6px">since {{ fmtDate(editUser.inactivity_flagged_at) }}</span>
          </label>

          <label class="xp-check-lbl">
            <input
              type="checkbox"
              :checked="!!editUser.email_verified_at"
              @change="e => { editUser.email_verified_at = e.target.checked ? (editUser.email_verified_at || new Date().toISOString()) : null; }"
            />
            Email Verified
          </label>
        </div>
        <p v-if="userErr" class="xp-err-msg">{{ userErr }}</p>
        <div class="xp-modal-actions">
          <button class="xp-btn-primary" :disabled="userBusy" @click="saveEdit">{{ userBusy ? 'Saving…' : 'Save Changes' }}</button>
          <button class="xp-btn-sm" @click="editUser = null">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ══ ADD USER MODAL ══ -->
    <div v-if="addUser" class="xp-overlay" @click.self="addUser = null">
      <div class="xp-modal">
        <h3>Create User</h3>
        <div class="xp-form">
          <label>Name</label>
          <input v-model="addUser.name" class="xp-input" />

          <label>Email</label>
          <input v-model="addUser.email" class="xp-input" type="email" />

          <label>Password</label>
          <input v-model="addUser.password" class="xp-input" type="password" />

          <label>Role</label>
          <select v-model="addUser.role" class="xp-select">
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <p v-if="userErr" class="xp-err-msg">{{ userErr }}</p>
        <div class="xp-modal-actions">
          <button class="xp-btn-primary" :disabled="userBusy" @click="saveAdd">{{ userBusy ? 'Creating…' : 'Create User' }}</button>
          <button class="xp-btn-sm" @click="addUser = null">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ══ QUARANTINE USER MODAL ══ -->
    <div v-if="quarantineTarget" class="xp-overlay" @click.self="!quarantineBusy && closeQuarantine()">
      <div class="xp-modal" style="width:420px">

        <!-- Confirm step -->
        <template v-if="!quarantineResult">
          <h3>Quarantine User</h3>
          <p style="color:var(--xm);font-size:13px;margin:0 0 16px">
            This immediately resets <strong style="color:var(--xt)">{{ quarantineTarget.name }}</strong>'s password to a random value, blocks login, and revokes every active session. The new password is shown once — you'll need to relay it manually if the account should regain access.
          </p>
          <div class="xp-form">
            <label>Reason <small style="font-weight:400;color:var(--xm)">(optional, recorded in the audit log)</small></label>
            <textarea v-model="quarantineReason" class="xp-input xp-textarea" rows="2" placeholder="e.g. suspected compromised credentials"></textarea>
          </div>
          <p v-if="quarantineErr" class="xp-err-msg">{{ quarantineErr }}</p>
          <div class="xp-modal-actions">
            <button class="xp-btn-sm danger" :disabled="quarantineBusy" @click="confirmQuarantine">
              {{ quarantineBusy ? 'Quarantining…' : 'Quarantine' }}
            </button>
            <button class="xp-btn-sm" :disabled="quarantineBusy" @click="closeQuarantine">Cancel</button>
          </div>
        </template>

        <!-- Result step -->
        <template v-else>
          <h3>User Quarantined</h3>
          <p style="color:var(--xm);font-size:13px;margin:0 0 12px">
            {{ quarantineTarget.name }} is blocked and all sessions are revoked. New password (shown once):
          </p>
          <div class="xp-output" style="margin-bottom:12px;user-select:all">{{ quarantineResult.password }}</div>
          <div class="xp-modal-actions" style="justify-content:space-between">
            <button class="xp-btn-sm" @click="copyQuarantinePassword">{{ pwCopied ? 'Copied ✓' : 'Copy' }}</button>
            <button class="xp-btn-primary" @click="closeQuarantine">Done</button>
          </div>
        </template>

      </div>
    </div>

    <!-- ══ CONFIRM DELETE MODAL ══ -->
    <div v-if="deleteTarget" class="xp-overlay" @click.self="deleteTarget = null">
      <div class="xp-modal" style="width:360px">
        <h3>Delete User</h3>
        <p style="color:var(--xm);font-size:13px;margin:0 0 20px">
          Delete <strong style="color:var(--xt)">{{ deleteTarget.name }}</strong>? This cannot be undone.
        </p>
        <div class="xp-modal-actions">
          <button class="xp-btn-sm danger" :disabled="userBusy" @click="confirmDeleteUser">
            {{ userBusy ? 'Deleting…' : 'Delete' }}
          </button>
          <button class="xp-btn-sm" @click="deleteTarget = null">Cancel</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* ── Design tokens ── */
*, *::before, *::after { box-sizing: border-box; }

.xp-root, .xp-lock {
  --xbg:  #0d1117;
  --xsf:  #161b22;
  --xbd:  #30363d;
  --xt:   #c9d1d9;
  --xm:   #8b949e;
  --xac:  #1d4ed8;
  --xok:  #3fb950;
  --xwn:  #d29922;
  --xer:  #f85149;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  font-size: 14px;
  color: var(--xt);
}

/* ── Lock screen ── */
.xp-lock {
  min-height: 100vh;
  background: var(--xbg);
  display: flex;
  align-items: center;
  justify-content: center;
}

.xp-lock-card {
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 14px;
  padding: 44px 40px;
  width: 360px;
  text-align: center;
}

.xp-lock-icon {
  color: var(--xm);
  margin-bottom: 18px;
}

.xp-lock-title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 6px;
}

.xp-lock-sub {
  font-size: 13px;
  color: var(--xm);
  margin: 0 0 24px;
}

.xp-lock-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Root shell ── */
.xp-root {
  min-height: 100vh;
  background: var(--xbg);
  display: flex;
  flex-direction: column;
}

/* ── Header ── */
.xp-header {
  display: flex;
  align-items: center;
  gap: 16px;
  height: 52px;
  padding: 0 20px;
  background: var(--xsf);
  border-bottom: 1px solid var(--xbd);
  position: sticky;
  top: 0;
  z-index: 20;
  flex-shrink: 0;
}

.xp-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 180px;
}

.xp-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--xok);
  flex-shrink: 0;
}

.xp-brand {
  font-size: 14px;
  font-weight: 600;
}

.xp-env-badge {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 2px 7px;
  border-radius: 999px;
}
.env-local { background: rgba(139,148,158,.15); color: var(--xm); }
.env-prod  { background: rgba(248,81,73,.15);  color: var(--xer); }

.xp-nav {
  display: flex;
  gap: 2px;
  flex: 1;
}

.xp-nav-btn {
  background: none;
  border: none;
  color: var(--xm);
  font-size: 13px;
  padding: 6px 14px;
  cursor: pointer;
  border-radius: 6px;
  transition: color .12s, background .12s;
}
.xp-nav-btn:hover  { color: var(--xt); background: rgba(255,255,255,.06); }
.xp-nav-btn.active { color: var(--xt); background: rgba(255,255,255,.1); font-weight: 600; }

.xp-icon-btn {
  background: none;
  border: 1px solid var(--xbd);
  border-radius: 6px;
  color: var(--xm);
  padding: 6px 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: color .12s;
  flex-shrink: 0;
}
.xp-icon-btn:hover { color: var(--xt); }

.xp-super-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  color: var(--xok);
  border-color: rgba(63,185,80,.35);
}
.xp-super-btn:hover:not(:disabled) { background: rgba(63,185,80,.12); }

/* ── Main ── */
.xp-main {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.xp-pane { max-width: 1100px; }

.xp-pane-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.xp-pane-head h2 {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
}

.xp-count {
  font-size: 13px;
  font-weight: 400;
  color: var(--xm);
  margin-left: 6px;
}

.xp-empty {
  color: var(--xm);
  font-size: 13px;
  padding: 24px 0;
}

/* ── Info grid ── */
.xp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
}

.xp-card {
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 10px;
  padding: 16px 18px;
}

.xp-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--xm);
  margin-bottom: 12px;
}

.xp-rows { display: flex; flex-direction: column; gap: 9px; }

.xp-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
}

.xp-rl { color: var(--xm); font-size: 12px; flex-shrink: 0; }
.xp-rv { color: var(--xt); }

/* ── Disk bar ── */
.xp-bar {
  flex: 1;
  height: 5px;
  background: var(--xbd);
  border-radius: 3px;
  overflow: hidden;
}
.xp-bar-fill {
  height: 100%;
  background: var(--xac);
  border-radius: 3px;
  transition: width .3s;
}

/* ── Badges ── */
.xp-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  flex-shrink: 0;
}
.bdg-green  { background: rgba(63,185,80,.15);  color: var(--xok); }
.bdg-yellow { background: rgba(210,153,34,.15); color: var(--xwn); }
.bdg-red    { background: rgba(248,81,73,.15);  color: var(--xer); }
.bdg-gray   { background: rgba(139,148,158,.12); color: var(--xm); }

.xp-role {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  background: rgba(29,78,216,.2);
  color: #60a5fa;
  margin-right: 3px;
}

/* ── Table ── */
.xp-table-wrap {
  border: 1px solid var(--xbd);
  border-radius: 10px;
  overflow: hidden;
}

.xp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.xp-table th {
  text-align: left;
  padding: 9px 14px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--xm);
  background: var(--xsf);
  border-bottom: 1px solid var(--xbd);
}

.xp-table td {
  padding: 9px 14px;
  border-bottom: 1px solid rgba(48,54,61,.5);
  vertical-align: middle;
}

.xp-table tbody tr:last-child td { border-bottom: none; }
.xp-table tbody tr:hover { background: rgba(255,255,255,.025); }

.xp-actions { display: flex; gap: 6px; }

/* ── Commands ── */
.xp-cmd-group { margin-bottom: 24px; }

.xp-cmd-list {
  border: 1px solid var(--xbd);
  border-radius: 10px;
  overflow: hidden;
}

.xp-cmd-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(48,54,61,.5);
}
.xp-cmd-row:last-child { border-bottom: none; }
.xp-cmd-row:hover { background: rgba(255,255,255,.02); }

.xp-cmd-info { flex: 1; min-width: 0; }
.xp-cmd-label { display: block; font-size: 13px; font-weight: 500; }
.xp-cmd-desc  { display: block; font-size: 11px; color: var(--xm); font-family: 'Menlo', 'Consolas', monospace; margin-top: 2px; }

.xp-output-block { margin-top: 20px; }
.xp-output-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--xm);
  margin-bottom: 8px;
}
.xp-output {
  background: #010409;
  border: 1px solid var(--xbd);
  border-radius: 8px;
  padding: 14px 16px;
  color: var(--xok);
  font-family: 'Menlo', 'Consolas', 'Monaco', monospace;
  font-size: 12px;
  min-height: 80px;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
}

/* ── Settings ── */
.xp-settings-list { display: flex; flex-direction: column; gap: 8px; }

.xp-setting-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 8px;
}

.xp-setting-key {
  min-width: 220px;
  font-size: 13px;
  color: var(--xm);
}

.xp-add-setting {
  margin-top: 20px;
  padding: 16px;
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 10px;
}

.xp-add-row {
  display: flex;
  gap: 10px;
  align-items: center;
}

/* ── Inputs ── */
.xp-input {
  background: #010409;
  border: 1px solid var(--xbd);
  border-radius: 6px;
  color: var(--xt);
  font-size: 13px;
  padding: 8px 12px;
  width: 100%;
  outline: none;
  transition: border-color .12s;
  font-family: inherit;
}
.xp-input:focus { border-color: var(--xac); }
.xp-input.sm    { padding: 5px 10px; font-size: 12px; }
.xp-input:disabled { opacity: .5; cursor: not-allowed; }
.xp-textarea { resize: vertical; min-height: 100px; font-family: inherit; line-height: 1.5; }
.xp-bc-wrap { max-width: 640px; }

.xp-select {
  background: #010409;
  border: 1px solid var(--xbd);
  border-radius: 6px;
  color: var(--xt);
  font-size: 13px;
  padding: 8px 12px;
  width: 100%;
  outline: none;
  font-family: inherit;
}

/* ── Buttons ── */
.xp-btn-primary {
  background: var(--xac);
  border: none;
  border-radius: 6px;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 18px;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity .12s;
  font-family: inherit;
}
.xp-btn-primary:hover:not(:disabled) { opacity: .85; }
.xp-btn-primary:disabled { opacity: .45; cursor: not-allowed; }

.xp-btn-sm {
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 6px;
  color: var(--xt);
  font-size: 12px;
  padding: 5px 12px;
  cursor: pointer;
  white-space: nowrap;
  transition: background .12s;
  font-family: inherit;
}
.xp-btn-sm:hover:not(:disabled) { background: rgba(255,255,255,.09); }
.xp-btn-sm:disabled { opacity: .45; cursor: not-allowed; }
.xp-btn-sm.danger   { color: var(--xer); border-color: rgba(248,81,73,.3); }
.xp-btn-sm.danger:hover { background: rgba(248,81,73,.1); }

/* ── Shutdown ── */
.xp-warn-box {
  background: rgba(210,153,34,.1);
  border: 1px solid rgba(210,153,34,.3);
  border-radius: 8px;
  color: var(--xwn);
  font-size: 12px;
  padding: 10px 14px;
  line-height: 1.5;
}

.xp-btn-danger {
  background: rgba(248,81,73,.15);
  border: 1px solid rgba(248,81,73,.4);
  border-radius: 6px;
  color: var(--xer);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background .12s;
  font-family: inherit;
}
.xp-btn-danger:hover:not(:disabled) { background: rgba(248,81,73,.25); }
.xp-btn-danger:disabled { opacity: .45; cursor: not-allowed; }

/* ── Inject tab ── */
.xp-inject-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.xp-preset-card {
  background: var(--xsf);
  border: 2px solid var(--xbd);
  border-radius: 8px;
  padding: 14px 16px;
  cursor: pointer;
  transition: border-color .12s, background .12s;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.xp-preset-card:hover { border-color: rgba(29,78,216,.45); }
.xp-preset-card.selected { border-color: var(--xac); background: rgba(29,78,216,.1); }

.xp-preset-label { font-size: 13px; font-weight: 600; color: var(--xt); }
.xp-preset-desc  { font-size: 11px; color: var(--xm); line-height: 1.4; }

.xp-inject-controls {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 16px;
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 10px;
}

.xp-inject-row {
  display: flex;
  align-items: center;
  gap: 14px;
}

.xp-count-btns { display: flex; gap: 6px; }

.xp-btn-sm-active {
  background: var(--xac) !important;
  border-color: var(--xac) !important;
  color: #fff !important;
}

/* ── Activity ── */
.xp-user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.xp-act-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot-online  { background: var(--xok); box-shadow: 0 0 0 2px rgba(63,185,80,.25); }
.dot-recent  { background: var(--xwn); }
.dot-offline { background: var(--xbd); }
.dot-never   { background: var(--xbd); }

/* ── Helpers ── */
.mono  { font-family: 'Menlo', 'Consolas', monospace; }
.sm    { font-size: 12px; }
.muted { color: var(--xm); }
.clr-ok { color: var(--xok); }

.xp-err-msg { color: var(--xer); font-size: 12px; margin: 0; }

/* ── Modal overlay ── */
.xp-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.72);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.xp-modal {
  background: var(--xsf);
  border: 1px solid var(--xbd);
  border-radius: 12px;
  padding: 28px;
  width: 440px;
  max-width: 90vw;
  max-height: 85vh;
  overflow-y: auto;
}

.xp-modal h3 {
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 20px;
}

.xp-form {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 16px;
}

.xp-form label:not(.xp-check-lbl) {
  font-size: 12px;
  font-weight: 500;
  color: var(--xm);
  margin-top: 10px;
  margin-bottom: 4px;
  display: block;
}

.xp-check-lbl {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
  margin-top: 10px;
}

.xp-modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}
</style>
