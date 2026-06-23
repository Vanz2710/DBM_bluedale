<template>
  <div class="page">
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Forecasts</h1>
        <p class="page-subtitle">Track forecasted revenue by company, product, type, result, and owner</p>
      </div>
      <div class="page-head-actions">
        <router-link to="/forecasts/summary" class="btn-light-pill">Summary</router-link>
        <button v-if="can('create forecasts')" type="button" class="btn-primary-pill" @click="openAdd">
          <span class="plus-icon" aria-hidden="true">+</span> Add Forecast
        </button>
      </div>
    </div>

    <div class="toolbar">
      <div class="filter-group wide">
        <label>Search</label>
        <input type="text" v-model="filters.q" @keyup.enter="applyFilters" placeholder="Company, product, user…">
      </div>
      <div class="filter-group">
        <label>Product</label>
        <select v-model="filters.product_id" @change="applyFilters">
          <option value="">All Products</option>
          <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="filters.forecast_type_id" @change="applyFilters">
          <option value="">All Types</option>
          <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Result</label>
        <select v-model="filters.result_id" @change="applyFilters">
          <option value="">All Results</option>
          <option value="none">No Result</option>
          <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div v-if="isAdmin" class="filter-group">
        <label>User</label>
        <select v-model="filters.user_id" @change="applyFilters">
          <option value="">All Users</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>From</label>
        <input type="date" v-model="filters.from_date">
      </div>
      <div class="filter-group">
        <label>To</label>
        <input type="date" v-model="filters.to_date">
      </div>
      <button class="btn btn-primary" @click="applyFilters">Search</button>
      <button class="btn btn-clear" @click="resetFilters">Reset</button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="record-count">
          <span class="count-label">Forecasts</span>
          <span class="count-badge">{{ meta.total ?? forecasts.length }} record(s)</span>
        </span>
        <div class="summary-stats">
          <span class="fstat-chip"><strong>{{ fmtValue(summary.total_amount) }}</strong><small>Total</small></span>
          <span class="fstat-chip fstat-confirmed"><strong>{{ fmtValue(summary.confirmed_amount) }}</strong><small>Confirmed</small></span>
          <span class="fstat-chip fstat-pending"><strong>{{ fmtValue(summary.pending_amount) }}</strong><small>Pending</small></span>
        </div>
      </div>

      <LoadingSpinner v-if="loading" />
      <div v-else class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>No</th>
              <th>Company</th>
              <th>Product</th>
              <th>Type</th>
              <th>Snapshot</th>
              <th class="sortable" @click="changeSort('amount')">Amount <span class="sort-icon" v-html="sortIcon('amount')"></span></th>
              <th class="sortable" @click="changeSort('forecast_date')">Forecast Date <span class="sort-icon" v-html="sortIcon('forecast_date')"></span></th>
              <th>Result</th>
              <th>Assigned</th>
              <th class="sortable" @click="changeSort('forecast_updatedate')">Updated <span class="sort-icon" v-html="sortIcon('forecast_updatedate')"></span></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="forecasts.length === 0">
              <td colspan="11" class="empty-state">No forecasts found.</td>
            </tr>
            <tr v-for="(f, idx) in forecasts" :key="f.id">
              <td><span class="row-num">{{ (meta.from ?? 1) + idx }}</span></td>
              <td>
                <router-link v-if="f.contact_id" :to="`/contacts/${f.contact_id}`" class="company-link">{{ f.contact_name }}</router-link>
                <span v-else class="muted">—</span>
              </td>
              <td>{{ f.product_name ?? '—' }}</td>
              <td><span class="type-badge">{{ f.forecast_type_name ?? '—' }}</span></td>
              <td>
                <div class="snapshot">
                  <span>{{ f.contact_status_name ?? 'No status' }}</span>
                  <small>{{ f.contact_type_name ?? 'No type' }}</small>
                </div>
              </td>
              <td class="amount-cell">{{ fmtValue(f.amount) }}</td>
              <td><span class="date-text">{{ fmtDate(f.forecast_date) }}</span></td>
              <td><span class="result-badge" :class="resultClass(f.result_name)">{{ f.result_name ?? 'No Result' }}</span></td>
              <td>{{ f.user_name ?? '—' }}</td>
              <td><span class="date-text">{{ fmtDate(f.forecast_updatedate) }}</span></td>
              <td class="actions-cell">
                <button v-if="can('edit forecasts')" type="button" class="icon-btn btn-edit" title="Edit" @click="openEdit(f.id)" v-html="CI.edit"></button>
                <button v-if="can('delete forecasts')" class="icon-btn btn-del" title="Delete" @click="confirmDelete(f)" v-html="CI.trash"></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <span class="pager-count">Showing {{ forecasts.length }} of {{ meta.total ?? 0 }} record(s)</span>
        <div class="pager-btns">
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage((meta.current_page ?? 1) - 1)">‹</button>
          <template v-for="pg in pageNumbers" :key="pg">
            <button v-if="pg !== '...'" class="pager-num" :class="{ 'pager-num--on': pg === (meta.current_page ?? 1) }" @click="changePage(pg)">{{ pg }}</button>
            <span v-else class="pager-ellipsis">…</span>
          </template>
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage((meta.current_page ?? 1) + 1)">›</button>
        </div>
        <div class="pager-rows">
          <span class="pager-rows-label">Rows</span>
          <select v-model.number="perPage" @change="changePage(1)" class="pager-rows-sel">
            <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-backdrop">
      <div class="modal">
        <h3>Delete Forecast?</h3>
        <p>Delete the forecast for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
        <div class="modal-btns">
          <button class="btn btn-cancel-sm" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-danger" @click="doDelete" :disabled="deleting">{{ deleting ? 'Deleting…' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <ForecastFormModal
      :open="formModal.open"
      :mode="formModal.mode"
      :forecast-id="formModal.forecastId"
      @close="closeFormModal"
      @saved="onFormSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import ForecastFormModal from '../components/ForecastFormModal.vue';
import { usePermissions } from '../composables/usePermissions.js';

const { can } = usePermissions();

const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const CI = {
  edit:  _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash: _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
};

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const resultOptions = computed(() =>
  (lookups.value.forecast_results ?? []).filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
);

const PER_PAGE_OPTIONS = [20, 50, 100];
const perPage     = ref(50);
const lookups     = ref({ forecast_products: [], forecast_types: [], forecast_results: [], users: [] });
const forecasts   = ref([]);
const summary     = ref({});
const meta        = ref({});
const loading     = ref(false);
const deleteTarget = ref(null);
const deleting    = ref(false);
const page        = ref(1);
const sortField   = ref('forecast_date');
const sortDir     = ref('desc');

const filters = reactive({
  q: '', product_id: '', forecast_type_id: '', result_id: '', user_id: '', from_date: '', to_date: '',
});

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)           return [1, 2, 3, '...', total];
  if (cur >= total - 2)   return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

function buildParams(includePaging = true) {
  const params = { ...filters, sort_field: sortField.value, sort_direction: sortDir.value };
  if (includePaging) { params.page = page.value; params.per_page = perPage.value; }
  Object.keys(params).forEach((k) => {
    if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k];
  });
  return params;
}

function fmtValue(value) {
  const n = Number(value ?? 0);
  if (!n) return 'RM 0.00';
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtDate(value) {
  if (!value) return '—';
  const [y, m, d] = String(value).slice(0, 10).split('-');
  return `${d}-${m}-${y}`;
}

function sortIcon(field) {
  if (sortField.value !== field) return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4"><line x1="12" y1="20" x2="12" y2="4"/><polyline points="5 11 12 4 19 11"/><polyline points="19 13 12 20 5 13"/></svg>';
  return sortDir.value === 'asc'
    ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>'
    : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
}

function changeSort(field) {
  sortDir.value = sortField.value === field && sortDir.value === 'desc' ? 'asc' : 'desc';
  sortField.value = field;
  load();
}

function resultClass(name) {
  const key = (name ?? 'No Result').toLowerCase().replace(/\s+/g, '-');
  return `result-${key}`;
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/forecasts', { params: buildParams() });
    forecasts.value = res.data.data ?? [];
    meta.value = res.data ?? {};
  } finally {
    loading.value = false;
  }
}

async function loadSummary() {
  const res = await api.get('/v1/forecasts/summary', { params: { ...buildParams(false), totals_only: 1 } });
  summary.value = res.data.data?.totals ?? {};
}

function applyFilters() { page.value = 1; load(); loadSummary(); }
function resetFilters()  { Object.keys(filters).forEach((k) => { filters[k] = ''; }); page.value = 1; load(); loadSummary(); }
function changePage(p)   { page.value = p; load(); }
function confirmDelete(f){ deleteTarget.value = f; }

const formModal = ref({ open: false, mode: 'add', forecastId: null });
function openAdd()       { formModal.value = { open: true, mode: 'add', forecastId: null }; }
function openEdit(id)    { formModal.value = { open: true, mode: 'edit', forecastId: id }; }
function closeFormModal(){ formModal.value.open = false; }
function onFormSaved()   { closeFormModal(); load(); loadSummary(); }

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/forecasts/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    load(); loadSummary();
  } finally {
    deleting.value = false;
  }
}

onMounted(async () => {
  await Promise.all([
    api.get('/v1/lookups').then((res) => { lookups.value = res.data; }),
    load(),
    loadSummary(),
  ]);
});
</script>

<style scoped>
.page { padding: 28px 28px 48px; max-width: 1500px; margin: 0 auto; }

/* Page head */
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

.btn-primary-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary); color: var(--primary-on);
  border: none; border-radius: 999px; padding: 11px 20px;
  font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;
  box-shadow: 0 8px 22px -8px rgba(29,78,216,0.6);
  transition: background 0.15s, transform 0.06s;
}
.btn-primary-pill:hover { background: var(--primary-hover); }
.btn-primary-pill:active { transform: translateY(1px); }
.plus-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(255,255,255,0.18); font-size: 14px; font-weight: 700; line-height: 1;
}
.btn-light-pill {
  display: inline-flex; align-items: center;
  background: var(--surface); color: var(--text-2);
  border: 1px solid var(--border); border-radius: 999px; padding: 10px 18px;
  font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
  transition: border-color 0.15s, color 0.15s;
}
.btn-light-pill:hover { border-color: var(--primary); color: var(--primary); }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius-lg); padding: 14px 16px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-soft);
  display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.filter-group select, .filter-group input {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 28px; }
.filter-group select:focus, .filter-group input:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.btn { height: 38px; padding: 0 18px; border: none; border-radius: 999px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.15s, transform 0.06s; }
.btn:active { transform: translateY(1px); }
.btn-primary { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-clear { background: var(--surface); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-soft); }

/* Table */
.table-wrap { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft); overflow: hidden; }
.table-header-bar {
  background: var(--surface); padding: 14px 20px;
  border-bottom: 1px solid var(--border-soft);
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
.summary-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.fstat-chip {
  display: inline-flex; flex-direction: column; align-items: center; padding: 4px 12px;
  background: var(--surface-2); border-radius: 8px; min-width: 90px; gap: 2px;
}
.fstat-chip strong { font-size: 12px; font-weight: 800; color: var(--text-1); line-height: 1.2; }
.fstat-chip small { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); font-weight: 700; }
.fstat-confirmed { background: #dcfce7; }
.fstat-confirmed strong { color: #15803d; }
.fstat-pending { background: #fef3c7; }
.fstat-pending strong { color: #b45309; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
  background: transparent; color: var(--text-3); font-size: 11.5px; font-weight: 600;
  padding: 12px 12px; border-bottom: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap;
}
thead th.sortable { cursor: pointer; user-select: none; }
thead th.sortable:hover { color: var(--text-1); }
.sort-icon { font-size: 10px; color: var(--primary); margin-left: 2px; }
tbody td { padding: 12px 12px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

.row-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; background: var(--surface-2);
  border-radius: 999px; font-size: 11px; font-weight: 700; color: var(--text-3);
}
.date-text { font-size: 12.5px; color: var(--text-2); font-weight: 500; }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: var(--primary); }
.muted { color: var(--text-3); }
.type-badge { background: #e0f2fe; color: #0369a1; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.result-badge { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.result-confirmed { background: #dcfce7; color: #15803d; }
.result-pending   { background: #fef3c7; color: #b45309; }
.result-rejected  { background: #fee2e2; color: #b91c1c; }
.result-no-result { background: var(--surface-2); color: var(--text-3); }
.amount-cell { font-weight: 800; color: #0369a1; white-space: nowrap; }
.snapshot { display: flex; flex-direction: column; gap: 2px; }
.snapshot span { font-size: 12.5px; font-weight: 600; color: var(--text-1); }
.snapshot small { font-size: 11px; color: var(--text-3); }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: var(--radius-sm); font-size: 14px;
  cursor: pointer; border: none; transition: background 0.12s, transform 0.06s;
}
.icon-btn:active { transform: scale(0.92); }
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #fde68a; }
.btn-del  { background: #fee2e2; }
.btn-del:hover  { background: #fca5a5; }
.actions-cell { display: flex; gap: 4px; }
.empty-state { text-align: center; padding: 48px; color: var(--text-3); font-size: 14px; }

/* Pager */
.pager {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-top: 1px solid var(--border-soft);
  background: var(--surface); gap: 12px; flex-wrap: wrap;
}
.pager-count { font-size: 12px; color: var(--text-3); white-space: nowrap; }
.pager-btns  { display: flex; align-items: center; gap: 3px; }
.pager-nav {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  color: var(--text-2); cursor: pointer; font-size: 16px; line-height: 1;
  transition: background 0.12s;
}
.pager-nav:hover:not(:disabled) { background: var(--primary-soft); color: var(--primary); }
.pager-nav:disabled { opacity: 0.3; cursor: default; }
.pager-num {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  font-size: 12px; font-weight: 600; color: var(--text-2); cursor: pointer;
  transition: background 0.12s;
}
.pager-num:hover { background: var(--primary-soft); color: var(--primary); }
.pager-num--on { background: var(--primary); color: var(--primary-on); font-weight: 700; }
.pager-ellipsis { width: 30px; text-align: center; color: var(--text-3); font-size: 13px; }
.pager-rows { display: flex; align-items: center; gap: 6px; }
.pager-rows-label { font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; }
.pager-rows-sel {
  height: 30px; padding: 0 10px; border: 1px solid var(--border); border-radius: 999px;
  font-size: 12px; background: var(--surface); color: var(--text-1); outline: none; cursor: pointer;
  transition: border-color 0.15s;
}
.pager-rows-sel:focus { border-color: var(--primary); }

/* Delete modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000; display: flex; align-items: center; justify-content: center; }
.modal { background: var(--surface); border-radius: var(--radius-lg); padding: 28px 32px; max-width: 400px; width: 90%; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); }
.modal h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 10px; }
.modal p { font-size: 13px; color: var(--text-2); margin: 0 0 20px; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }
.btn-cancel-sm { height: 38px; padding: 0 18px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface); color: var(--text-2); font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-danger { height: 38px; padding: 0 18px; border: none; border-radius: 999px; background: var(--danger); color: white; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-danger:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
</style>
