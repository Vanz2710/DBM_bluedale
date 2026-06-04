<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Projects</h1>
        <p>Manage projects linked to contacts</p>
      </div>
      <router-link v-if="can('create projects')" to="/projects/add" class="btn-add">+ Add Project</router-link>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>Start Date From</label>
        <input type="date" v-model="fromDate">
      </div>
      <div class="filter-group">
        <label>Start Date To</label>
        <input type="date" v-model="toDate">
      </div>
      <div class="filter-group wide">
        <label>Search (Name / Company / Remark)</label>
        <input v-model="search" @keyup.enter="load" placeholder="Type and press Enter…">
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
        {{ meta.total ?? projects.length }} project(s)
        <span class="sort-hint">— click column headers to sort</span>
      </div>
      <div v-if="loading" class="loading-msg">Loading…</div>
      <table v-else>
        <thead>
          <tr>
            <th>No</th>
            <th class="sortable" @click="changeSort('project_startdate')">
              Start Date <span class="sort-icon">{{ sortIcon('project_startdate') }}</span>
            </th>
            <th class="sortable" @click="changeSort('project_enddate')">
              End Date <span class="sort-icon">{{ sortIcon('project_enddate') }}</span>
            </th>
            <th>Duration</th>
            <th>Company</th>
            <th class="sortable" @click="changeSort('project_name')">
              Project <span class="sort-icon">{{ sortIcon('project_name') }}</span>
            </th>
            <th>Remark</th>
            <th class="sortable" @click="changeSort('updated_at')">
              Entry Date <span class="sort-icon">{{ sortIcon('updated_at') }}</span>
            </th>
            <th>By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="projects.length === 0">
            <td colspan="10" class="empty-state">No projects found.</td>
          </tr>
          <tr v-for="(p, idx) in projects" :key="p.id">
            <td>{{ meta.from ? meta.from + idx : idx + 1 }}</td>
            <td>{{ fmt(p.project_startdate) }}</td>
            <td>{{ fmt(p.project_enddate) }}</td>
            <td>{{ p.duration_days != null ? p.duration_days + ' day(s)' : '—' }}</td>
            <td>
              <router-link v-if="p.contact_id" :to="`/contacts/${p.contact_id}`" class="company-link">
                {{ p.contact_name }}
              </router-link>
              <span v-else>{{ p.contact_name ?? '—' }}</span>
            </td>
            <td class="project-name-cell">{{ p.project_name }}</td>
            <td class="remark-cell">
              <span v-if="p.project_remark" class="remark-preview">{{ p.project_remark.slice(0, 30) }}{{ p.project_remark.length > 30 ? '…' : '' }}</span>
              <button v-if="p.project_remark" class="remark-btn" @click="openRemark(p)" title="View full remark">💬</button>
              <span v-else class="muted">—</span>
            </td>
            <td>{{ p.entry_date ?? '—' }}</td>
            <td>{{ p.user_name ?? '—' }}</td>
            <td>
              <router-link v-if="can('edit projects')" :to="`/projects/${p.id}/edit`" class="icon-btn btn-edit" title="Edit">✏️</router-link>
              <button v-if="can('delete projects')" class="icon-btn btn-del" title="Delete" @click="confirmDelete(p)">🗑️</button>
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

    <!-- Remark modal -->
    <div v-if="remarkTarget" class="modal-backdrop" @click.self="remarkTarget = null">
      <div class="modal">
        <h3>Project Remark</h3>
        <p class="modal-company">{{ remarkTarget.contact_name }} — {{ remarkTarget.project_name }}</p>
        <LoadingSpinner v-if="remarkLoading" />
        <div v-else class="remark-full">{{ remarkText || '—' }}</div>
        <div class="modal-btns">
          <button class="btn btn-cancel" @click="remarkTarget = null">Close</button>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
      <div class="modal">
        <h3>Delete Project?</h3>
        <p>Are you sure you want to delete <strong>{{ deleteTarget.project_name }}</strong> for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
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
import { usePermissions } from '../composables/usePermissions.js';

const { can } = usePermissions();

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

const fromDate  = ref(firstOfMonth);
const toDate    = ref(today);
const search    = ref('');
const perPage   = ref(100);
const page      = ref(1);
const sortField = ref('project_startdate');
const sortDir   = ref('desc');

const projects     = ref([]);
const meta         = ref({});
const loading      = ref(false);
const deleteTarget = ref(null);
const deleting     = ref(false);
const remarkTarget = ref(null);
const remarkLoading = ref(false);
const remarkText   = ref('');

function fmt(dateStr) {
  if (!dateStr) return '—';
  const [y, m, d] = dateStr.split('-');
  return `${d}-${m}-${y}`;
}

function sortIcon(field) {
  if (sortField.value !== field) return '⇅';
  return sortDir.value === 'asc' ? '↑' : '↓';
}

function changeSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortDir.value   = 'desc';
  }
  load();
}

function buildParams() {
  return {
    per_page:       perPage.value,
    page:           page.value,
    q:              search.value,
    from_date:      fromDate.value,
    to_date:        toDate.value,
    sort_field:     sortField.value,
    sort_direction: sortDir.value,
  };
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/projects', { params: buildParams() });
    projects.value = res.data.data;
    meta.value     = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

function changePage(p) {
  page.value = p;
  load();
}

function exportAll() {
  const token = localStorage.getItem('crm_token');
  const p = buildParams();
  const qs = new URLSearchParams({ ...p, _token: token }).toString();
  window.location.href = `/api/v1/projects/export?${qs}`;
}

async function openRemark(p) {
  remarkTarget.value  = p;
  remarkText.value    = '';
  remarkLoading.value = true;
  try {
    const res = await api.get(`/v1/projects/${p.id}/remark`);
    remarkText.value = res.data.data?.project_remark ?? p.project_remark ?? '';
  } finally {
    remarkLoading.value = false;
  }
}

function confirmDelete(p) {
  deleteTarget.value = p;
}

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/projects/${deleteTarget.value.id}`);
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
  background: linear-gradient(135deg, #1a2f4a, #0ea5e9);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.btn-add {
  background: #0ea5e9; color: white; border-radius: 8px;
  padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 700;
  border: 2px solid rgba(255,255,255,0.3); white-space: nowrap;
}

.toolbar {
  background: var(--surface); border-radius: 10px; padding: 14px 18px;
  margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 220px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group input[type="date"], .filter-group input[type="number"], .filter-group input:not([type]) {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; outline: none; background: var(--surface);
}
.filter-group input:focus { border-color: #0ea5e9; }

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
.sort-hint { font-weight: 400; color: var(--text-3); font-size: 12px; }
.loading-msg { text-align: center; padding: 48px; color: var(--text-3); }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap;
}
thead th.sortable { cursor: pointer; user-select: none; }
thead th.sortable:hover { color: #0ea5e9; background: #f0f9ff; }
.sort-icon { font-size: 11px; }
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }

.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: #0ea5e9; }
.project-name-cell { max-width: 220px; }
.remark-cell { max-width: 180px; white-space: nowrap; overflow: hidden; display: flex; align-items: center; gap: 4px; }
.remark-preview { overflow: hidden; text-overflow: ellipsis; flex: 1; }
.remark-btn { background: none; border: none; cursor: pointer; font-size: 14px; padding: 0 2px; }
.muted { color: var(--text-3); }

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

.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: var(--surface); border-radius: 12px; padding: 28px 32px;
  max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 6px; }
.modal p  { font-size: 13px; color: var(--text-2); margin: 0 0 16px; }
.modal-company { font-size: 12px; color: #0ea5e9; font-weight: 600; margin-bottom: 12px !important; }
.remark-full {
  background: var(--app-bg); border: 1px solid var(--border); border-radius: 8px;
  padding: 14px 16px; font-size: 13px; color: #374151; white-space: pre-wrap;
  max-height: 300px; overflow-y: auto; margin-bottom: 16px;
}
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 900px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
</style>
