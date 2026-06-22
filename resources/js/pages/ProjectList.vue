<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Projects</h1>
        <p>Manage projects linked to contacts</p>
      </div>
      <router-link v-if="can('create projects')" to="/projects/add" class="btn-add">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Project
      </router-link>
    </div>

    <div v-if="isAdmin" class="wip-notice">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>This page is still a work in progress. Some features may be incomplete or subject to change.</span>
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
              Start Date <span class="sort-icon" v-html="sortIcon('project_startdate')"></span>
            </th>
            <th class="sortable" @click="changeSort('project_enddate')">
              End Date <span class="sort-icon" v-html="sortIcon('project_enddate')"></span>
            </th>
            <th>Duration</th>
            <th>Company</th>
            <th class="sortable" @click="changeSort('project_name')">
              Project <span class="sort-icon" v-html="sortIcon('project_name')"></span>
            </th>
            <th>Remark</th>
            <th class="sortable" @click="changeSort('updated_at')">
              Entry Date <span class="sort-icon" v-html="sortIcon('updated_at')"></span>
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
              <button v-if="p.project_remark" class="remark-btn" @click="openRemark(p)" title="View full remark" :aria-label="`View remark for ${p.project_name}`">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </button>
              <span v-else class="muted">—</span>
            </td>
            <td>{{ p.entry_date ?? '—' }}</td>
            <td>{{ p.user_name ?? '—' }}</td>
            <td class="actions-cell">
              <router-link v-if="can('edit projects')" :to="`/projects/${p.id}/edit`" class="icon-btn btn-edit" :aria-label="`Edit ${p.project_name}`">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </router-link>
              <button v-if="can('delete projects')" class="icon-btn btn-del" :aria-label="`Delete ${p.project_name}`" @click="confirmDelete(p)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="pagination">
        <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)" aria-label="Previous page">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          Prev
        </button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)" aria-label="Next page">
          Next
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    <!-- Remark modal -->
    <div v-if="remarkTarget" class="modal-backdrop" @click.self="remarkTarget = null">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="remark-modal-title">
        <h3 id="remark-modal-title">Project Remark</h3>
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
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <h3 id="delete-modal-title">Delete Project?</h3>
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
import { ref, shallowRef, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { usePermissions } from '../composables/usePermissions.js';

const { can, isAdmin } = usePermissions();

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

const fromDate  = ref(firstOfMonth);
const toDate    = ref(today);
const search    = ref('');
const perPage   = ref(100);
const page      = ref(1);
const sortField = ref('project_startdate');
const sortDir   = ref('desc');

const projects     = shallowRef([]);
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
  if (sortField.value !== field) return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4"><line x1="12" y1="20" x2="12" y2="4"/><polyline points="5 11 12 4 19 11"/><polyline points="19 13 12 20 5 13"/></svg>';
  return sortDir.value === 'asc'
    ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>'
    : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
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
.page { padding: 28px 32px; }

/* Banner */
.page-banner {
  background: linear-gradient(135deg, #0c1e3a 0%, var(--primary) 100%);
  border-radius: var(--radius); padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 28px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.3px; }
.page-banner p  { font-size: 13.5px; opacity: 0.75; margin: 0; }

/* WIP notice (admin-only) */
.wip-notice {
  display: flex; align-items: center; gap: 8px;
  background: var(--warning-soft); color: #92400e;
  border: 1px solid #fcd34d; border-radius: var(--radius-sm);
  padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
}
.wip-notice svg { flex-shrink: 0; color: var(--warning); }

.btn-add {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.18); color: white; border-radius: var(--radius-sm);
  padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 600;
  border: 1.5px solid rgba(255,255,255,0.35); white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.btn-add:hover { background: rgba(255,255,255,0.28); border-color: rgba(255,255,255,0.55); color: white; }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius); padding: 14px 18px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 220px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group input[type="date"],
.filter-group input[type="number"],
.filter-group input:not([type]) {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s ease;
}
.filter-group input:focus { border-color: var(--primary); }

/* Buttons */
.btn {
  height: 36px; padding: 0 14px; border: none; border-radius: var(--radius-sm);
  cursor: pointer; font-size: 13px; font-weight: 600;
  transition: background 0.15s ease, opacity 0.15s ease;
}
.btn-primary { background: var(--primary); color: var(--primary-on); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-export  { background: var(--success); color: white; }
.btn-export:hover { opacity: 0.88; }
.btn-cancel  { background: var(--app-bg); color: var(--text-2); }
.btn-cancel:hover { background: var(--border); }
.btn-danger  { background: var(--danger); color: white; }
.btn-danger:hover { opacity: 0.88; }
.btn-danger:disabled { background: var(--text-3); cursor: not-allowed; opacity: 1; }
.btn-group label { visibility: hidden; }

/* Table */
.table-wrap { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-xs); overflow: hidden; }
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
thead th.sortable { cursor: pointer; user-select: none; transition: color 0.15s ease, background 0.15s ease; }
thead th.sortable:hover { color: var(--primary); background: var(--surface-2); }
.sort-icon { font-size: 11px; }
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text-1); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr { transition: background 0.12s ease; }
tbody tr:hover { background: var(--app-bg); }

.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; transition: color 0.15s ease; }
.company-link:hover { color: var(--primary); }
.project-name-cell { max-width: 220px; }
.remark-cell { max-width: 180px; white-space: nowrap; overflow: hidden; display: flex; align-items: center; gap: 4px; }
.remark-preview { overflow: hidden; text-overflow: ellipsis; flex: 1; }
.remark-btn {
  display: inline-flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer; padding: 3px 4px;
  border-radius: var(--radius-sm); color: var(--text-3);
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.remark-btn:hover { background: var(--primary-soft); color: var(--primary); }
.muted { color: var(--text-3); }

/* Icon action buttons */
.actions-cell { display: flex; align-items: center; gap: 4px; }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: var(--radius-sm); text-decoration: none;
  cursor: pointer; border: none; transition: filter 0.15s ease;
}
.btn-edit { background: var(--warning-soft); color: var(--warning); }
.btn-edit:hover { filter: brightness(0.9); }
.btn-del  { background: var(--danger-soft); color: var(--danger); }
.btn-del:hover  { filter: brightness(0.9); }

/* Focus states */
.icon-btn:focus-visible,
.remark-btn:focus-visible,
.btn:focus-visible,
.btn-add:focus-visible,
.pagination button:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}

.empty-state { text-align: center; padding: 40px; color: var(--text-3); font-size: 14px; }

/* Pagination */
.pagination {
  display: flex; align-items: center; justify-content: center; gap: 14px;
  padding: 14px; border-top: 1px solid var(--border); font-size: 13px; color: var(--text-1);
}
.pagination button {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 6px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); cursor: pointer; font-size: 13px; color: var(--text-1);
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.pagination button:hover:not(:disabled) { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Modals */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: var(--surface); border-radius: var(--radius-lg); padding: 28px 32px;
  max-width: 480px; width: 90%; box-shadow: var(--shadow-lg);
}
.modal h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 6px; }
.modal p  { font-size: 13px; color: var(--text-2); margin: 0 0 16px; }
.modal-company { font-size: 12px; color: var(--primary); font-weight: 600; margin-bottom: 12px !important; }
.remark-full {
  background: var(--app-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
  padding: 14px 16px; font-size: 13px; color: var(--text-1); white-space: pre-wrap;
  max-height: 300px; overflow-y: auto; margin-bottom: 16px;
}
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 900px; }
  .icon-btn { width: 36px; height: 36px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; animation: none !important; }
}
</style>
