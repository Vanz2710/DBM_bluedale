<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Follow-Ups</h1>
        <p>Track follow-up actions by date range or month range</p>
      </div>
      <router-link to="/followups/add" class="btn-add">+ Add Follow-Up</router-link>
    </div>

    <div v-if="selectedIds.length > 0" class="selection-bar">
      <button class="btn-export-sel" @click="exportSelected">Export {{ selectedIds.length }} selected</button>
      <span>{{ selectedIds.length }} record(s) selected</span>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>View</label>
        <select v-model="view" @change="onViewChange">
          <option>DateRange</option>
          <option>MonthRange</option>
        </select>
      </div>

      <template v-if="view === 'DateRange'">
        <div class="filter-group">
          <label>From Date</label>
          <input type="date" v-model="fromDate">
        </div>
        <div class="filter-group">
          <label>To Date</label>
          <input type="date" v-model="toDate">
        </div>
      </template>

      <template v-else>
        <div class="filter-group">
          <label>From Month</label>
          <input type="month" v-model="fromMonth">
        </div>
        <div class="filter-group">
          <label>To Month</label>
          <input type="month" v-model="toMonth">
        </div>
      </template>

      <div class="filter-group">
        <label>Action Type</label>
        <select v-model="actionType">
          <option value="">All Types</option>
          <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search Company</label>
        <input v-model="search" @keyup.enter="load" placeholder="Company name…">
      </div>
      <div class="filter-group">
        <label>Per Page</label>
        <input type="number" v-model.number="perPage" style="width:70px;">
      </div>
      <div class="filter-group btn-group">
        <label>&nbsp;</label>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary" @click="load">Search</button>
          <button class="btn btn-export" @click="exportAll">Export</button>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        {{ periodLabel }} — {{ meta.total ?? followUps.length }} record(s)
      </div>
      <LoadingSpinner v-if="loading" />
      <table v-else>
        <thead>
          <tr>
            <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
            <th>No</th>
            <th>Follow-Up Date</th>
            <th>Action Type</th>
            <th>Company</th>
            <th>Status</th>
            <th>Type</th>
            <th>User</th>
            <th>Task</th>
            <th>Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="followUps.length === 0">
            <td colspan="11" class="empty-state">No follow-ups found for this period.</td>
          </tr>
          <tr v-for="(f, idx) in followUps" :key="f.id">
            <td><input type="checkbox" :value="f.id" v-model="selectedIds"></td>
            <td>{{ meta.from ? meta.from + idx : idx + 1 }}</td>
            <td>{{ f.followup_date }}</td>
            <td>
              <span v-if="f.action_type" class="badge">{{ f.action_type }}</span>
              <span v-else class="muted">—</span>
            </td>
            <td>
              <router-link v-if="f.contact_id" :to="`/contacts/${f.contact_id}`" class="company-link">
                {{ f.contact_name }}
              </router-link>
              <span v-else>{{ f.contact_name ?? '—' }}</span>
            </td>
            <td>{{ f.status ?? '—' }}</td>
            <td>{{ f.type ?? '—' }}</td>
            <td>{{ f.user ?? '—' }}</td>
            <td>{{ f.task ?? '—' }}</td>
            <td class="note-cell">{{ f.note ?? '—' }}</td>
            <td>
              <router-link :to="`/followups/${f.id}/edit`" class="icon-btn btn-edit" title="Edit">✏️</router-link>
              <button class="icon-btn btn-del" title="Delete" @click="confirmDelete(f)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="pagination">
        <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">← Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next →</button>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
      <div class="modal">
        <h3>Delete Follow-Up?</h3>
        <p>Are you sure you want to delete this follow-up for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
        <div class="modal-btns">
          <button class="btn btn-cancel" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-danger" @click="doDelete" :disabled="deleting">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];

const today = new Date().toISOString().slice(0, 10);
const thisMonth = today.slice(0, 7);

const view       = ref('DateRange');
const fromDate   = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const toDate     = ref(today);
const fromMonth  = ref(thisMonth);
const toMonth    = ref(thisMonth);
const actionType = ref('');
const search     = ref('');
const perPage    = ref(100);
const page       = ref(1);

const followUps    = ref([]);
const meta         = ref({});
const loading      = ref(false);
const selectedIds  = ref([]);
const selectAllRef = ref(null);
const deleteTarget = ref(null);
const deleting     = ref(false);

const periodLabel = computed(() => {
  if (view.value === 'DateRange') {
    return `${fromDate.value} to ${toDate.value}`;
  }
  return `${fromMonth.value} to ${toMonth.value}`;
});

function onViewChange() {
  page.value = 1;
  load();
}

function buildParams() {
  const p = { view: view.value, per_page: perPage.value, page: page.value };
  if (view.value === 'MonthRange') {
    p.from_month = fromMonth.value;
    p.to_month   = toMonth.value;
  } else {
    p.from_date = fromDate.value;
    p.to_date   = toDate.value;
  }
  if (actionType.value) p.action_type = actionType.value;
  if (search.value)     p.search      = search.value;
  return p;
}

async function load() {
  loading.value = true;
  selectedIds.value = [];
  try {
    const res = await api.get('/v1/followups', { params: buildParams() });
    followUps.value = res.data.data;
    meta.value      = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

function changePage(p) {
  page.value = p;
  load();
}

function toggleAll(e) {
  selectedIds.value = e.target.checked ? followUps.value.map(f => f.id) : [];
}

function exportAll() {
  const token = localStorage.getItem('crm_token');
  const p = buildParams();
  const qs = new URLSearchParams({ ...p, _token: token }).toString();
  window.location.href = `/api/v1/followups/export?${qs}`;
}

function exportSelected() {
  const token = localStorage.getItem('crm_token');
  const ids   = selectedIds.value.join(',');
  window.location.href = `/api/v1/followups/export?ids=${ids}&_token=${token}`;
}

function confirmDelete(f) {
  deleteTarget.value = f;
}

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/followups/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    load();
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.page { padding: 24px 28px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #e11d48);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.btn-add {
  background: #e11d48; color: white; border-radius: 8px;
  padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 700;
  border: 2px solid rgba(255,255,255,0.3); white-space: nowrap;
}

.selection-bar {
  background: #1e293b; color: white; border-radius: 8px;
  padding: 10px 18px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 14px; font-size: 13px;
}
.btn-export-sel {
  background: #10b981; color: white; border: none; border-radius: 6px;
  padding: 6px 14px; cursor: pointer; font-size: 13px; font-weight: 600;
}

.toolbar {
  background: var(--surface); border-radius: 10px; padding: 14px 18px;
  margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group select, .filter-group input[type="date"],
.filter-group input[type="month"], .filter-group input[type="number"] {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; outline: none; background: var(--surface);
}
.filter-group select:focus, .filter-group input:focus { border-color: #e11d48; }

.btn { height: 36px; padding: 0 14px; border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #3b82f6; color: white; }
.btn-export  { background: #10b981; color: white; }
.btn-cancel  { background: var(--app-bg); color: var(--text-2); }
.btn-danger  { background: #ef4444; color: white; }
.btn-danger:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-group label { visibility: hidden; }

.table-wrap { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-header-bar {
  background: var(--app-bg); padding: 12px 16px;
  font-size: 13px; font-weight: 700; color: var(--text-1);
  border-bottom: 2px solid var(--border);
}
.loading-msg { text-align: center; padding: 48px; color: var(--text-3); }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap;
}
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }

.badge {
  background: #fce7f3; color: #9d174d; font-size: 11px; font-weight: 600;
  padding: 2px 8px; border-radius: 12px; white-space: nowrap;
}
.muted { color: var(--text-3); }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: #e11d48; }
.note-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px; text-decoration: none; font-size: 13px;
  cursor: pointer; border: none; background: transparent;
}
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #fde68a; }
.btn-del  { background: #fee2e2; }
.btn-del:hover { background: #fca5a5; }

.empty-state { text-align: center; padding: 40px; color: var(--text-3); font-size: 14px; }
.pagination {
  display: flex; align-items: center; justify-content: center; gap: 14px;
  padding: 14px; border-top: 1px solid var(--border); font-size: 13px;
}
.pagination button {
  padding: 6px 14px; border: 1.5px solid var(--border); border-radius: 7px;
  background: var(--surface); cursor: pointer; font-size: 13px;
}
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Modal */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: var(--surface); border-radius: 12px; padding: 28px 32px;
  max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 10px; }
.modal p  { font-size: 13px; color: var(--text-2); margin: 0 0 20px; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 800px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
</style>
