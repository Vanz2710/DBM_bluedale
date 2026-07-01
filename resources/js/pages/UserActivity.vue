<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">User Activity</h1>
        <p class="page-subtitle">Monitor login history, CRM activity, and security events across all users</p>
      </div>
    </div>

    <div class="tab-bar">
      <button :class="['tab-btn', { active: tab === 'users' }]" @click="tab = 'users'">Users Overview</button>
      <button :class="['tab-btn', { active: tab === 'security' }]" @click="tab = 'security'">Security Events</button>
    </div>

    <!-- ══════════ USERS TAB ══════════ -->
    <template v-if="tab === 'users'">
      <div class="toolbar">
        <div class="filter-chips">
          <button
            v-for="f in statusFilters" :key="f.value"
            :class="['chip', { active: statusFilter === f.value }]"
            @click="statusFilter = f.value"
          >
            {{ f.label }}
            <span class="chip-cnt">{{ filterCount(f.value) }}</span>
          </button>
        </div>
        <div class="toolbar-right">
          <span class="field-label">Activity period:</span>
          <select v-model="period" class="field-input period-select" @change="loadOverview">
            <option :value="7">Last 7 days</option>
            <option :value="30">Last 30 days</option>
            <option :value="90">Last 90 days</option>
            <option :value="365">Last 12 months</option>
          </select>
        </div>
      </div>

      <div class="status-legend">
        <span class="legend-label">What do these mean?</span>
        <span
          v-for="f in statusFilters.filter(f => f.value !== 'all')" :key="'legend-' + f.value"
          class="badge" :class="statusBadgeClass(f.value)"
          :title="statusTooltip(f.value)"
        >{{ f.label }}</span>
      </div>

      <div v-if="loadingUsers" class="loading-wrap"><LoadingSpinner /></div>
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th title="Total logins">Logins</th>
              <th title="Contacts owned">Contacts</th>
              <th title="Todos completed in period">Todos Done</th>
              <th title="Follow-ups completed in period">F/U Done</th>
              <th title="Deals created in period">Deals</th>
              <th title="Projects created in period">Projects</th>
              <th>Member Since</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredUsers.length === 0">
              <td colspan="11" class="empty-state">No users match this filter.</td>
            </tr>
            <tr v-for="u in filteredUsers" :key="u.id">
              <td>
                <div class="user-cell">
                  <div class="avatar">{{ initials(u.name) }}</div>
                  <div>
                    <div class="user-name">{{ u.name }}</div>
                    <div class="user-sub">@{{ u.username }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span v-for="r in u.roles" :key="r" class="badge badge-purple">{{ r }}</span>
                <span v-if="!u.roles.length" class="muted">—</span>
              </td>
              <td><span :class="['badge', statusBadgeClass(u.status)]" :title="statusTooltip(u.status)">{{ statusLabel(u.status) }}</span></td>
              <td>
                <span v-if="u.last_login_at" class="login-date">
                  {{ u.last_login_at }}<br>
                  <span class="muted">{{ u.days_since_login }}d ago</span>
                </span>
                <span v-else class="muted">Never</span>
              </td>
              <td class="num">{{ u.login_count }}</td>
              <td class="num">{{ u.contacts }}</td>
              <td class="num" :class="{ zero: u.todos_completed === 0 }">{{ u.todos_completed }}</td>
              <td class="num" :class="{ zero: u.followups_completed === 0 }">{{ u.followups_completed }}</td>
              <td class="num" :class="{ zero: u.deals_created === 0 }">{{ u.deals_created }}</td>
              <td class="num" :class="{ zero: u.projects_created === 0 }">{{ u.projects_created }}</td>
              <td class="muted small">{{ u.member_since }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ══════════ SECURITY EVENTS TAB ══════════ -->
    <template v-if="tab === 'security'">
      <div v-if="loadingSecurity" class="loading-wrap"><LoadingSpinner /></div>
      <div v-else-if="events.length === 0" class="empty-state" style="padding: 60px 0;">
        No security events recorded yet.
      </div>
      <div v-else class="timeline-card">
        <div v-for="(e, i) in events" :key="i" class="event-row">
          <div :class="['event-dot', 'dot-' + e.event_type]"></div>
          <div class="event-body">
            <div class="event-meta">
              <span :class="['badge', eventBadgeClass(e.event_type)]">{{ e.label }}</span>
              <span class="muted small">{{ e.created_at }}</span>
            </div>
            <div class="event-title">{{ e.title }}</div>
            <div class="muted small">{{ e.body }}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const tab           = ref('users');
const period        = ref(30);
const statusFilter  = ref('all');
const loadingUsers    = ref(true);
const loadingSecurity = ref(true);
const users  = ref([]);
const events = ref([]);

const statusFilters = [
  { value: 'all',             label: 'All' },
  { value: 'active',          label: 'Active' },
  { value: 'at_risk',         label: 'At Risk' },
  { value: 'dormant',         label: 'Dormant' },
  { value: 'locked',          label: 'Locked' },
  { value: 'never_logged_in', label: 'Never Logged In' },
];

const filteredUsers = computed(() =>
  statusFilter.value === 'all' ? users.value : users.value.filter(u => u.status === statusFilter.value)
);

function filterCount(value) {
  return value === 'all' ? users.value.length : users.value.filter(u => u.status === value).length;
}

function statusLabel(s) {
  return { active: 'Active', at_risk: 'At Risk', dormant: 'Dormant', locked: 'Locked', never_logged_in: 'Never Logged In' }[s] ?? s;
}

function statusBadgeClass(s) {
  return { active: 'badge-green', at_risk: 'badge-amber', dormant: 'badge-red', locked: 'badge-red', never_logged_in: 'badge-gray' }[s] ?? 'badge-gray';
}

function statusTooltip(s) {
  return {
    active: 'Logged in within the last 14 days.',
    at_risk: 'Last login was 14–29 days ago.',
    dormant: 'Last login was 30+ days ago.',
    locked: 'Account flagged for inactivity and locked out (login blocked until an admin restores access).',
    never_logged_in: 'This user has never logged in.',
  }[s] ?? '';
}

function eventBadgeClass(t) {
  return { password_change: 'badge-amber', restored_access: 'badge-green', approved: 'badge-blue', created: 'badge-purple', deleted: 'badge-red', restored: 'badge-blue' }[t] ?? 'badge-gray';
}

function initials(name) {
  return name.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
}

async function loadOverview() {
  loadingUsers.value = true;
  try {
    const { data } = await api.get('/v1/user-activity/overview', { params: { period: period.value } });
    users.value = data.data;
  } finally {
    loadingUsers.value = false;
  }
}

async function loadSecurity() {
  loadingSecurity.value = true;
  try {
    const { data } = await api.get('/v1/user-activity/security-events');
    events.value = data.events;
  } finally {
    loadingSecurity.value = false;
  }
}

watch(tab, t => { if (t === 'security' && events.value.length === 0) loadSecurity(); });
onMounted(loadOverview);
</script>

<style scoped>
.page        { padding: 28px 32px; }
.page-header { margin-bottom: 24px; }
.page-title  { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* Tabs */
.tab-bar {
  display: flex; gap: 4px;
  border-bottom: 2px solid var(--border); margin-bottom: 24px;
}
.tab-btn {
  padding: 9px 18px; border: none; background: none; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--text-2);
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  transition: color 0.15s, border-color 0.15s;
}
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-btn:hover:not(.active) { color: var(--text-1); background: var(--surface-2); }

/* Toolbar */
.toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }

.chip {
  padding: 5px 12px; border-radius: 999px; border: 1px solid var(--border);
  background: var(--surface); font-size: 12px; font-weight: 500; color: var(--text-2);
  cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.15s;
}
.chip.active { background: var(--primary); color: var(--primary-on); border-color: var(--primary); }
.chip:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
.chip-cnt {
  background: rgba(0,0,0,0.1); border-radius: 10px;
  padding: 0 5px; font-size: 11px;
}
.chip.active .chip-cnt { background: rgba(255,255,255,0.25); }

.toolbar-right { display: flex; align-items: center; gap: 8px; }
.period-select { width: auto; height: 34px; padding: 0 10px; cursor: pointer; }

/* Status legend */
.status-legend {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  margin-bottom: 16px;
}
.legend-label { font-size: 12px; font-weight: 600; color: var(--text-3); margin-right: 2px; }
.status-legend .badge { cursor: help; }

/* Table */
.table-wrap { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
.data-table  { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr { background: var(--surface-2); }
th {
  padding: 10px 14px; text-align: left;
  font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
td { padding: 12px 14px; color: var(--text-1); border-bottom: 1px solid var(--border-soft); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--surface-2); }

.empty-state { text-align: center; color: var(--text-3); padding: 40px; font-size: 14px; }

.user-cell { display: flex; align-items: center; gap: 10px; }
.avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary);
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.user-name { font-weight: 600; color: var(--text-1); white-space: nowrap; }
.user-sub  { font-size: 11px; color: var(--text-3); }

/* Badges */
.badge        { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-green  { background: #dcfce7; color: #15803d; }
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-purple { background: var(--primary-soft); color: var(--primary-text); }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-gray   { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }

.num       { text-align: center; font-weight: 600; color: var(--text-1); }
.zero      { color: var(--text-3); font-weight: 400; }
.muted     { color: var(--text-3); }
.small     { font-size: 12px; }
.login-date { font-size: 12px; color: var(--text-2); line-height: 1.5; }

/* Field */
.field-label { font-size: 12px; font-weight: 600; color: var(--text-2); white-space: nowrap; }
.field-input {
  height: 34px; padding: 0 10px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface);
  outline: none; transition: border-color 0.15s;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

.loading-wrap { display: flex; justify-content: center; padding: 60px 0; }

/* Security Timeline */
.timeline-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden; max-width: 760px;
}
.event-row {
  display: flex; gap: 14px; align-items: flex-start;
  padding: 14px 20px; border-bottom: 1px solid var(--border-soft);
}
.event-row:last-child { border-bottom: none; }
.event-row:hover { background: var(--surface-2); }

.event-dot {
  width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 4px;
}
.dot-password_change { background: #f59e0b; }
.dot-restored_access { background: #10b981; }
.dot-approved        { background: #3b82f6; }
.dot-created         { background: var(--primary); }
.dot-deleted         { background: #ef4444; }
.dot-restored        { background: #6366f1; }

.event-body  { flex: 1; min-width: 0; }
.event-meta  { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.event-title { font-size: 13px; font-weight: 600; color: var(--text-1); }
</style>
