<template>
  <div class="page">
    <Transition name="toast">
      <div v-if="toast" class="toast-msg" role="status">{{ toast }}</div>
    </Transition>

    <div class="page-head">
      <div>
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">Record of all admin actions — user management, role changes, and system events</p>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input v-model="search" class="search-input" placeholder="Search actor, entity, action…" autocomplete="off" />
      </div>
      <select v-model="filterAction" class="filter-select">
        <option value="">All actions</option>
        <option v-for="a in KNOWN_ACTIONS" :key="a" :value="a">{{ a }}</option>
      </select>
      <select v-model="filterEntity" class="filter-select">
        <option value="">All types</option>
        <option v-for="e in KNOWN_ENTITIES" :key="e" :value="e">{{ e }}</option>
      </select>
      <select v-model="filterDays" class="filter-select">
        <option value="7">Last 7 days</option>
        <option value="30">Last 30 days</option>
        <option value="90">Last 90 days</option>
        <option value="">All time</option>
      </select>
      <button class="btn-export" @click="exportLogs">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="table-header-title">Admin Actions</span>
        <span class="count-badge">{{ total }} total</span>
        <span v-if="loading" class="loading-hint">Loading…</span>
      </div>

      <div v-if="loading" class="loading-block">
        <svg class="spin-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      </div>

      <div v-else-if="error" class="error-banner">{{ error }}</div>

      <div v-else-if="logs.length === 0" class="empty-banner">
        <div class="empty-title">No entries found</div>
        <div class="empty-sub">Try adjusting your filters.</div>
      </div>

      <table v-else>
        <thead>
          <tr>
            <th style="width:140px">Time</th>
            <th style="width:130px">Actor</th>
            <th style="width:100px">Action</th>
            <th style="width:100px">Type</th>
            <th>Entity</th>
            <th style="width:60px">IP</th>
            <th style="width:160px">Changes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" :class="['log-row', `action-${log.action}`]">
            <td class="time-cell">
              <span class="date-part">{{ formatDate(log.created_at) }}</span>
              <span class="time-part">{{ formatTime(log.created_at) }}</span>
            </td>
            <td>
              <span v-if="log.actor" class="actor-name">{{ log.actor.name }}</span>
              <span v-else class="muted">System</span>
            </td>
            <td><span :class="['action-badge', `action-badge-${log.action}`]">{{ log.action }}</span></td>
            <td class="muted entity-type">{{ log.entity_type }}</td>
            <td class="entity-name">{{ log.entity_name ?? log.entity_id ?? '—' }}</td>
            <td class="muted ip-cell">{{ log.ip_address ?? '—' }}</td>
            <td class="diff-cell">
              <button
                v-if="log.old_values || log.new_values"
                class="diff-btn"
                :title="diffTitle(log)"
                @click="openDiff(log)"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                View
              </button>
              <button
                v-if="canRevert(log)"
                class="revert-btn"
                title="Undo this merge — recreates the merged-away items and moves their records back"
                @click="openRevertModal(log)"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                Revert
              </button>
              <span v-else-if="log.action === 'merged' && log.reverted_at" class="reverted-pill" title="Already reverted">Reverted</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="lastPage > 1" class="pagination-bar">
        <button class="page-btn" :disabled="page <= 1 || loading" @click="loadLogs(page - 1)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          Prev
        </button>
        <span class="page-info">Page {{ page }} of {{ lastPage }}</span>
        <button class="page-btn" :disabled="page >= lastPage || loading" @click="loadLogs(page + 1)">
          Next
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    <!-- Detail modal -->
    <Teleport to="body">
      <div v-if="diffModal.open" class="overlay" @click.self="diffModal.open = false">
        <div class="modal">
          <div class="modal-head">
            <div>
              <div class="modal-title">Change Details</div>
              <div class="modal-sub">
                <span :class="['action-badge', `action-badge-${diffModal.log?.action}`]">{{ diffModal.log?.action }}</span>
                <span class="modal-entity">{{ prettifyKey(diffModal.log?.entity_type ?? '') }}: <strong>{{ diffModal.log?.entity_name ?? diffModal.log?.entity_id ?? '—' }}</strong></span>
              </div>
            </div>
            <button class="modal-close" @click="diffModal.open = false">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="modal-body">
            <!-- Context bar -->
            <div class="ctx-bar">
              <div class="ctx-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>{{ diffModal.log?.actor?.name ?? 'System' }}</span>
              </div>
              <span class="ctx-dot">·</span>
              <div class="ctx-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>{{ formatDate(diffModal.log?.created_at) }}, {{ formatTime(diffModal.log?.created_at) }}</span>
              </div>
              <template v-if="diffModal.log?.ip_address">
                <span class="ctx-dot">·</span>
                <div class="ctx-item ctx-ip">{{ diffModal.log.ip_address }}</div>
              </template>
            </div>

            <!-- Summary sentence -->
            <div class="summary-sentence">
              {{ summaryText }}
            </div>

            <!-- Field-level changes -->
            <template v-if="diffRows.length > 0">
              <div class="section-label">
                {{ diffModal.log?.old_values && diffModal.log?.new_values ? 'Fields Changed' : diffModal.log?.new_values ? 'Initial Values' : 'Values at Deletion' }}
              </div>
              <div class="change-list">
                <div
                  v-for="row in diffRows"
                  :key="row.key"
                  class="change-row"
                  :class="`change-${row.type}`"
                >
                  <div class="change-field">{{ row.label }}</div>
                  <div class="change-values">
                    <template v-if="row.type === 'changed'">
                      <span class="val-old">{{ row.before }}</span>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                      <span class="val-new">{{ row.after }}</span>
                    </template>
                    <template v-else-if="row.type === 'added'">
                      <span class="val-set">{{ row.after }}</span>
                    </template>
                    <template v-else>
                      <span class="val-removed">{{ row.before }}</span>
                      <span class="val-removed-label">removed</span>
                    </template>
                  </div>
                </div>
              </div>
            </template>

            <div v-else class="no-changes">
              No field-level changes were recorded for this action.
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Revert confirm modal -->
    <Teleport to="body">
      <div v-if="revertModal.open" class="conf-overlay">
        <div class="conf-modal">
          <div class="conf-head">
            <div>
              <p class="conf-title">Revert Merge</p>
              <p class="conf-sub">Recreates the merged-away item(s) and moves their records back.</p>
            </div>
            <button class="conf-close" @click="closeRevertModal">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="conf-body">
            <svg class="conf-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <p class="conf-text">
              Restore <strong>{{ revertMergedNames }}</strong> back out of
              <strong>{{ revertModal.log?.entity_name ?? '—' }}</strong>?
              Any record still assigned to it will move back to its original value.
            </p>
            <p v-if="revertModal.error" class="conf-error">{{ revertModal.error }}</p>
          </div>
          <div class="conf-foot">
            <button class="conf-cancel" @click="closeRevertModal">Cancel</button>
            <button class="conf-revert" :disabled="revertModal.loading" @click="confirmRevert">
              {{ revertModal.loading ? 'Reverting…' : 'Revert' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../api.js';

const logs    = ref([]);
const loading = ref(false);
const error   = ref('');

const page     = ref(1);
const total    = ref(0);
const lastPage = ref(1);

const search       = ref('');
const filterAction = ref('');
const filterEntity = ref('');
const filterDays   = ref('30');

const diffModal = ref({ open: false, log: null });

const toast = ref('');
function showToast(msg) {
  toast.value = msg;
  setTimeout(() => { toast.value = ''; }, 3000);
}

const revertModal = ref({ open: false, log: null, loading: false, error: '' });

function canRevert(log) {
  return log.action === 'merged' && !log.reverted_at && log.has_revert_data;
}

const revertMergedNames = computed(() => {
  const items = revertModal.value.log?.old_values?.merged_items ?? [];
  return items.map(i => i.name).join(', ') || 'these items';
});

function openRevertModal(log) {
  revertModal.value = { open: true, log, loading: false, error: '' };
}
function closeRevertModal() {
  revertModal.value.open = false;
}

async function confirmRevert() {
  const log = revertModal.value.log;
  if (!log) return;
  revertModal.value.error   = '';
  revertModal.value.loading = true;
  try {
    const res = await api.post(`/v1/admin/audit-log/${log.id}/revert`);
    log.reverted_at = new Date().toISOString();
    revertModal.value.open = false;
    showToast(`Reverted — ${res.data.recreated} item(s) recreated, ${res.data.records_moved} record(s) moved back.`);
  } catch (e) {
    const errors = e.response?.data?.errors;
    revertModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to revert this merge.');
  } finally {
    revertModal.value.loading = false;
  }
}

const KNOWN_ACTIONS = [
  'approved', 'created', 'deleted', 'devpanel_login_as', 'merged', 'quarantined', 'restored', 'restored_access',
  'reverted', 'unlocked', 'updated', 'updated_password',
];

const KNOWN_ENTITIES = [
  'user', 'user_roles', 'role', 'role_permissions', 'contact',
  'lookup:statuses', 'lookup:types', 'lookup:industries', 'lookup:categories',
  'lookup:areas', 'lookup:tasks', 'lookup:forecast-products',
  'lookup:forecast-types', 'lookup:forecast-results', 'lookup:packages',
];

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

const SENSITIVE_KEYS = ['password', 'remember_token', 'token', 'secret', 'api_key'];

function prettifyKey(key) {
  if (!key) return '';
  return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function prettifyValue(key, val) {
  if (val === null || val === undefined) return null;
  if (SENSITIVE_KEYS.some(k => key.toLowerCase().includes(k))) return '[hidden]';
  if (typeof val === 'boolean') return val ? 'Yes' : 'No';
  if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
    try { return new Date(val).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); } catch { /* skip */ }
  }
  if (Array.isArray(val)) return val.join(', ') || '(empty)';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
}

const diffRows = computed(() => {
  const log = diffModal.value.log;
  if (!log) return [];
  const oldV = log.old_values ?? {};
  const newV = log.new_values ?? {};
  const keys = new Set([...Object.keys(oldV), ...Object.keys(newV)]);
  const rows = [];
  for (const key of keys) {
    const rawOld = key in oldV ? oldV[key] : null;
    const rawNew = key in newV ? newV[key] : null;
    if (JSON.stringify(rawOld) === JSON.stringify(rawNew)) continue;
    const before = rawOld !== null ? prettifyValue(key, rawOld) : null;
    const after  = rawNew !== null ? prettifyValue(key, rawNew) : null;
    const type   = rawOld === null ? 'added' : rawNew === null ? 'removed' : 'changed';
    rows.push({ key, label: prettifyKey(key), before, after, type });
  }
  return rows;
});

const summaryText = computed(() => {
  const log = diffModal.value.log;
  if (!log) return '';
  const actor  = log.actor?.name ?? 'System';
  const entity = prettifyKey(log.entity_type ?? '');
  const name   = log.entity_name ?? log.entity_id ?? 'this record';
  const changed = diffRows.value.filter(r => r.type === 'changed').length;
  const added   = diffRows.value.filter(r => r.type === 'added').length;
  const removed = diffRows.value.filter(r => r.type === 'removed').length;
  const action = (log.action ?? '').toLowerCase();
  if (action === 'created')  return `${actor} created a new ${entity.toLowerCase()} named "${name}".`;
  if (action === 'deleted')  return `${actor} permanently deleted ${entity.toLowerCase()} "${name}".`;
  if (action === 'restored_access') return `${actor} restored login access for "${name}".`;
  if (action === 'approved') return `${actor} approved "${name}".`;
  if (action === 'unlocked') return `${actor} unlocked "${name}".`;
  if (action === 'merged')   return `${actor} merged one or more ${entity.toLowerCase()}s into "${name}".`;
  if (action === 'reverted') return `${actor} reverted a merge on ${entity.toLowerCase()} "${name}", recreating the merged-away item(s).`;
  if (action === 'updated_password') return `${actor} changed the password for "${name}".`;
  if (action === 'quarantined') return `${actor} quarantined "${name}" — password reset, blocked, and all sessions revoked.`;
  if (action === 'devpanel_login_as') return `${actor} opened a live session as "${name}" via the DevPanel — no password used.`;
  if (action === 'updated') {
    const parts = [];
    if (changed > 0) parts.push(`${changed} field${changed > 1 ? 's' : ''} updated`);
    if (added   > 0) parts.push(`${added} field${added   > 1 ? 's' : ''} added`);
    if (removed > 0) parts.push(`${removed} field${removed > 1 ? 's' : ''} removed`);
    const summary = parts.length ? parts.join(', ') : 'minor change';
    return `${actor} updated ${entity.toLowerCase()} "${name}" — ${summary}.`;
  }
  return `${actor} performed "${log.action}" on ${entity.toLowerCase()} "${name}".`;
});

function diffTitle(log) {
  return log.entity_name ?? log.entity_type ?? '';
}

function openDiff(log) {
  diffModal.value = { open: true, log };
}

async function loadLogs(goToPage = 1) {
  page.value    = goToPage;
  loading.value = true;
  error.value   = '';
  try {
    const params = { page: page.value, per_page: 50 };
    if (filterDays.value)    params.days        = filterDays.value;
    if (filterAction.value)  params.action      = filterAction.value;
    if (filterEntity.value)  params.entity_type = filterEntity.value;
    if (search.value.trim()) params.q           = search.value.trim();

    const res      = await api.get('/v1/admin/audit-log', { params });
    logs.value     = res.data.data ?? [];
    total.value    = res.data.meta?.total ?? 0;
    lastPage.value = res.data.meta?.last_page ?? 1;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load audit log.';
  } finally {
    loading.value = false;
  }
}

function exportLogs() {
  const token = localStorage.getItem('crm_token');
  const params = { _token: token };
  if (filterDays.value)    params.days        = filterDays.value;
  if (filterAction.value)  params.action      = filterAction.value;
  if (filterEntity.value)  params.entity_type = filterEntity.value;
  if (search.value.trim()) params.q           = search.value.trim();
  const qs = new URLSearchParams(params).toString();
  window.location.href = `/api/v1/admin/audit-log/export?${qs}`;
}

let searchTimer;
watch([filterAction, filterEntity, filterDays], () => loadLogs(1));
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadLogs(1), 350);
});

onMounted(() => loadLogs(1));
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1300px; }
.page-head { margin-bottom: 20px; }
.page-title    { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Filter bar ── */
.filter-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}
.search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  pointer-events: none;
}
.search-input {
  width: 100%;
  height: 36px;
  padding: 0 12px 0 30px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--surface);
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}
.search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.filter-select {
  height: 36px;
  padding: 0 10px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--surface);
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s;
}
.filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: white;
  background: var(--success);
  cursor: pointer;
  transition: opacity 0.15s;
  flex-shrink: 0;
}
.btn-export:hover { opacity: 0.88; }

/* ── Table wrap ── */
.table-wrap {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.table-header-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 22px;
  border-bottom: 1px solid var(--border-soft);
}
.table-header-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.count-badge {
  background: var(--surface-2);
  color: var(--text-2);
  font-size: 11px;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 999px;
}
.loading-hint { font-size: 12px; color: var(--text-3); margin-left: auto; }

/* ── States ── */
.loading-block {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 60px;
  color: var(--text-3);
}
.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-banner {
  margin: 20px 22px;
  padding: 12px 16px;
  background: #fef2f2;
  color: #dc2626;
  border-radius: 8px;
  border: 1px solid #fecaca;
  font-size: 13px;
}
.empty-banner {
  padding: 60px 24px;
  text-align: center;
}
.empty-title { font-size: 14px; font-weight: 600; color: var(--text-2); margin-bottom: 4px; }
.empty-sub   { font-size: 13px; color: var(--text-3); }

/* ── Table ── */
table { width: 100%; border-collapse: collapse; }
thead th {
  background: var(--surface-2);
  color: var(--text-2);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.55px;
  padding: 10px 14px;
  border-bottom: 2px solid var(--border);
  text-align: left;
  white-space: nowrap;
}
tbody td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--border-soft);
  font-size: 13px;
  color: var(--text-1);
  vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }
.muted       { color: var(--text-3); }
.actor-name  { font-weight: 500; }
.entity-name { font-weight: 500; color: var(--text-1); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.entity-type { font-size: 12px; }
.ip-cell     { font-size: 11px; font-family: monospace; }
.time-cell   { display: flex; flex-direction: column; gap: 1px; }
.date-part   { font-size: 12px; font-weight: 500; color: var(--text-1); white-space: nowrap; }
.time-part   { font-size: 11px; color: var(--text-3); }
.diff-cell   { text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap; }

/* ── Action badge ── */
.action-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.action-badge-created       { background: #dcfce7; color: #15803d; }
.action-badge-updated       { background: #dbeafe; color: #1d4ed8; }
.action-badge-deleted       { background: #fee2e2; color: #991b1b; }
.action-badge-restored      { background: #f0fdf4; color: #166534; }
.action-badge-approved      { background: #d1fae5; color: #065f46; }
.action-badge-unlocked      { background: #ecfdf5; color: #047857; }
.action-badge-restored_access { background: #fef3c7; color: #92400e; }
.action-badge-updated_password { background: #e0e7ff; color: #3730a3; }
.action-badge-merged        { background: #ede9fe; color: #5b21b6; }
.action-badge-reverted      { background: #cffafe; color: #155e75; }
.action-badge-quarantined   { background: #fee2e2; color: #991b1b; }
.action-badge-devpanel_login_as { background: #fef3c7; color: #92400e; }

/* fallback for any other action */
.action-badge:not([class*="action-badge-"]) { background: var(--surface-2); color: var(--text-2); }

/* ── Diff / revert buttons ── */
.diff-btn, .revert-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 24px;
  padding: 0 8px;
  border: 1.5px solid var(--border);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-2);
  background: transparent;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.diff-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(29,78,216,0.04);
}
.revert-btn:hover {
  border-color: #0891b2;
  color: #0891b2;
  background: rgba(8,145,178,0.06);
}
.reverted-pill {
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface-2);
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* ── Modal ── */
.overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000; padding: 24px;
}
.modal {
  background: var(--surface); border-radius: var(--radius-lg);
  box-shadow: 0 24px 80px rgba(0,0,0,0.2);
  width: 100%; max-width: 560px; max-height: 82vh;
  display: flex; flex-direction: column; overflow: hidden;
}
.modal-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 20px 24px 14px; border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.modal-title  { font-size: 15px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
.modal-sub    { font-size: 12px; color: var(--text-2); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.modal-entity { color: var(--text-2); }
.modal-close  {
  width: 28px; height: 28px; border: none; background: none;
  color: var(--text-3); cursor: pointer; border-radius: 6px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.modal-close:hover { background: var(--surface-2); color: var(--text-1); }

.modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px; }

/* Context bar */
.ctx-bar {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: var(--radius-sm);
  background: var(--surface-2); font-size: 12px; color: var(--text-2);
  flex-wrap: wrap;
}
.ctx-item { display: flex; align-items: center; gap: 5px; }
.ctx-dot  { color: var(--border); }
.ctx-ip   { font-family: monospace; font-size: 11.5px; }

/* Summary sentence */
.summary-sentence {
  font-size: 13.5px; color: var(--text-1); line-height: 1.55;
  padding: 12px 16px; border-radius: var(--radius-sm);
  background: var(--surface-2); border-left: 3px solid var(--primary);
}

/* Section label */
.section-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--text-3);
}

/* Change list */
.change-list { display: flex; flex-direction: column; gap: 1px; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border); }

.change-row {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 10px 14px; background: var(--surface);
  font-size: 13px; border-bottom: 1px solid var(--border-soft);
}
.change-row:last-child { border-bottom: none; }
.change-row.change-changed { background: #fafbff; }
.change-row.change-added   { background: #f0fdf4; }
.change-row.change-removed { background: #fff8f8; }

.change-field {
  font-weight: 600; color: var(--text-1); min-width: 130px;
  flex-shrink: 0; padding-top: 1px;
}
.change-values {
  display: flex; align-items: center; gap: 8px;
  flex-wrap: wrap; flex: 1; min-width: 0;
}
.val-old     { color: #991b1b; text-decoration: line-through; font-size: 12.5px; word-break: break-all; }
.val-new     { color: #15803d; font-weight: 600; font-size: 12.5px; word-break: break-all; }
.val-set     { color: #15803d; font-weight: 600; font-size: 12.5px; word-break: break-all; }
.val-removed { color: #991b1b; font-size: 12.5px; word-break: break-all; }
.val-removed-label {
  font-size: 10.5px; font-weight: 700; padding: 1px 6px; border-radius: 999px;
  background: #fee2e2; color: #dc2626; text-transform: uppercase; letter-spacing: .3px; flex-shrink: 0;
}

/* No changes */
.no-changes {
  padding: 28px; text-align: center; font-size: 13px; color: var(--text-3);
  border: 1px dashed var(--border); border-radius: var(--radius-sm);
}

/* ── Pagination ── */
.pagination-bar {
  display: flex; align-items: center; justify-content: center; gap: 16px;
  padding: 14px 22px; border-top: 1px solid var(--border-soft);
}
.page-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 32px; padding: 0 14px;
  border: 1.5px solid var(--border); border-radius: 8px;
  font-size: 12px; font-weight: 600; color: var(--text-2);
  background: var(--surface); cursor: pointer; transition: all 0.15s;
}
.page-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: rgba(29,78,216,0.04); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 12px; color: var(--text-3); }

@media (max-width: 768px) { .page { padding: 20px 16px; } }
@media (max-width: 640px) { .page { padding: 16px 12px; } }

/* ── Revert confirm modal ── */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; color: var(--text-3); line-height: 1; padding: 4px; border-radius: 6px; display: flex; }
.conf-close:hover { color: var(--text-1); background: var(--surface-2); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-info { width: 40px; height: 40px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-error { font-size: 12.5px; color: #dc2626; margin: 0; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-revert { height: 38px; padding: 0 18px; background: var(--primary); color: var(--primary-on); border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-revert:hover:not(:disabled) { background: var(--primary-hover); }
.conf-revert:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Toast ── */
.toast-msg {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: var(--text-1); color: var(--surface); padding: 10px 20px;
  border-radius: var(--radius); font-size: 13px; z-index: 950; white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
