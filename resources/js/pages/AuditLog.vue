<template>
  <div class="page">
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
        <option v-for="a in availableActions" :key="a" :value="a">{{ a }}</option>
      </select>
      <select v-model="filterEntity" class="filter-select">
        <option value="">All types</option>
        <option v-for="e in availableEntities" :key="e" :value="e">{{ e }}</option>
      </select>
      <select v-model="filterDays" class="filter-select">
        <option value="7">Last 7 days</option>
        <option value="30">Last 30 days</option>
        <option value="90">Last 90 days</option>
        <option value="">All time</option>
      </select>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="table-header-title">Admin Actions</span>
        <span class="count-badge">{{ filtered.length }}</span>
        <span v-if="loading" class="loading-hint">Loading…</span>
      </div>

      <div v-if="loading" class="loading-block">
        <svg class="spin-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      </div>

      <div v-else-if="error" class="error-banner">{{ error }}</div>

      <div v-else-if="filtered.length === 0" class="empty-banner">
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
            <th style="width:64px">Changes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in filtered" :key="log.id" :class="['log-row', `action-${log.action}`]">
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
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Diff modal -->
    <Teleport to="body">
      <div v-if="diffModal.open" class="overlay">
        <div class="modal modal-wide">
          <div class="modal-head">
            <div>
              <div class="modal-title">Change Details</div>
              <div class="modal-sub">
                <span :class="['action-badge', `action-badge-${diffModal.log?.action}`]">{{ diffModal.log?.action }}</span>
                <span style="margin-left:6px">{{ diffModal.log?.entity_type }}: <strong>{{ diffModal.log?.entity_name }}</strong></span>
              </div>
            </div>
            <button class="modal-close" @click="diffModal.open = false">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="modal-body diff-body">
            <div v-if="diffModal.log?.old_values" class="diff-col">
              <div class="diff-label">Before</div>
              <div class="diff-json diff-old">{{ formatJson(diffModal.log.old_values) }}</div>
            </div>
            <div v-if="diffModal.log?.new_values" class="diff-col">
              <div class="diff-label">After</div>
              <div class="diff-json diff-new">{{ formatJson(diffModal.log.new_values) }}</div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';

const logs    = ref([]);
const loading = ref(false);
const error   = ref('');

const search       = ref('');
const filterAction = ref('');
const filterEntity = ref('');
const filterDays   = ref('30');

const diffModal = ref({ open: false, log: null });

const availableActions = computed(() => {
  const s = new Set(logs.value.map(l => l.action));
  return [...s].sort();
});

const availableEntities = computed(() => {
  const s = new Set(logs.value.map(l => l.entity_type));
  return [...s].sort();
});

const filtered = computed(() => {
  const q        = search.value.trim().toLowerCase();
  const cutoff   = filterDays.value ? Date.now() - parseInt(filterDays.value) * 86400000 : 0;

  return logs.value.filter(l => {
    if (filterAction.value && l.action !== filterAction.value) return false;
    if (filterEntity.value && l.entity_type !== filterEntity.value) return false;
    if (cutoff && new Date(l.created_at).getTime() < cutoff) return false;
    if (q) {
      const actorName   = l.actor?.name?.toLowerCase() ?? '';
      const entityName  = (l.entity_name ?? '').toLowerCase();
      const entityType  = l.entity_type.toLowerCase();
      const action      = l.action.toLowerCase();
      const ip          = (l.ip_address ?? '').toLowerCase();
      if (!actorName.includes(q) && !entityName.includes(q) && !entityType.includes(q) && !action.includes(q) && !ip.includes(q)) return false;
    }
    return true;
  });
});

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function formatJson(obj) {
  if (!obj) return '—';
  return JSON.stringify(obj, null, 2);
}

function diffTitle(log) {
  const lines = [];
  if (log.old_values) lines.push('Before: ' + JSON.stringify(log.old_values));
  if (log.new_values) lines.push('After: ' + JSON.stringify(log.new_values));
  return lines.join('\n');
}

function openDiff(log) {
  diffModal.value = { open: true, log };
}

async function loadLogs() {
  loading.value = true;
  error.value   = '';
  try {
    const res = await api.get('/v1/admin/audit-log');
    logs.value = res.data.data ?? [];
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load audit log.';
  } finally {
    loading.value = false;
  }
}

onMounted(loadLogs);
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
.diff-cell   { text-align: center; }

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

/* fallback for any other action */
.action-badge:not([class*="action-badge-"]) { background: var(--surface-2); color: var(--text-2); }

/* ── Diff button ── */
.diff-btn {
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

/* ── Modal ── */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 24px;
}
.modal {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 24px 80px rgba(0,0,0,0.2);
  width: 100%;
  max-width: 560px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal-wide { max-width: 820px; }
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border-soft);
}
.modal-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin-bottom: 2px; }
.modal-sub   { font-size: 12px; color: var(--text-2); display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.modal-close {
  width: 28px; height: 28px;
  border: none; background: none;
  color: var(--text-3); cursor: pointer;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.modal-close:hover { background: var(--surface-2); color: var(--text-1); }

.modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
.diff-body  { display: flex; gap: 16px; }
.diff-col   { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.diff-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.diff-json  {
  font-family: monospace;
  font-size: 12px;
  line-height: 1.6;
  padding: 12px 14px;
  border-radius: 8px;
  white-space: pre-wrap;
  word-break: break-all;
  overflow-y: auto;
  max-height: 400px;
}
.diff-old { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.diff-new { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

@media (max-width: 768px) { .page { padding: 20px 16px; } }
@media (max-width: 640px) { .page { padding: 16px 12px; } }
</style>
