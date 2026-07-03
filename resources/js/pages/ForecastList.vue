<template>
  <div class="page" :class="{ 'page-embedded': embedded }">
    <div class="page-head" :class="{ 'page-head-embedded': embedded }">
      <div v-if="!embedded" class="page-head-left">
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
        <label>Snapshot</label>
        <select v-model="filters.contact_status_id" @change="applyFilters">
          <option value="">All Statuses</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
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
        <label>Year</label>
        <select v-model="filters.year" @change="applyFilters">
          <option value="">All Years</option>
          <option v-for="y in YEAR_OPTIONS" :key="y" :value="y">{{ y }}</option>
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
      <button class="btn btn-export" @click="openExportModal">{{ selectedIds.size > 0 ? `Export (${selectedIds.size})` : 'Export' }}</button>
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
          <colgroup>
            <col style="width:36px">    <!-- checkbox -->
            <col>                        <!-- company -->
            <col style="width:140px">   <!-- product -->
            <col style="width:92px">    <!-- type -->
            <col style="width:132px">   <!-- snapshot -->
            <col style="width:120px">   <!-- amount -->
            <col style="width:112px">   <!-- forecast date -->
            <col style="width:140px">   <!-- result -->
            <col style="width:82px">    <!-- assigned -->
            <col style="width:100px">   <!-- updated -->
            <col style="width:76px">    <!-- actions -->
          </colgroup>
          <thead>
            <tr>
              <th class="th-check"><input type="checkbox" class="row-cb" :checked="allSelected" :indeterminate.prop="someSelected" @change="toggleAll"></th>
              <th>Company</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Product</span>
                  <select v-model="filters.product_id" @change="page = 1; load(); loadSummary()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
              </th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Type</span>
                  <select v-model="filters.forecast_type_id" @change="page = 1; load(); loadSummary()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </th>
              <th>Snapshot</th>
              <th class="sortable" @click="changeSort('amount')">Amount <span class="sort-icon" v-html="sortIcon('amount')"></span></th>
              <th class="sortable" @click="changeSort('forecast_date')">Forecast Date <span class="sort-icon" v-html="sortIcon('forecast_date')"></span></th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Result</span>
                  <select v-model="filters.result_id" @change="page = 1; load(); loadSummary()" class="col-filter-sel">
                    <option value="">All</option>
                    <option value="none">No Result</option>
                    <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                  </select>
                </div>
              </th>
              <th>Assigned</th>
              <th class="sortable" @click="changeSort('forecast_updatedate')">Updated <span class="sort-icon" v-html="sortIcon('forecast_updatedate')"></span></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="forecasts.length === 0">
              <td colspan="11" class="empty-state">No forecasts found.</td>
            </tr>
            <tr v-for="(f, idx) in forecasts" :key="f.id" :class="{ 'row-clickable': f.contact_id }" @click="openContactRow(f)">
              <td class="td-check" @click.stop><input type="checkbox" class="row-cb" :checked="selectedIds.has(f.id)" @change="toggleSelect(f.id)"></td>
              <td class="td-company" @click.stop>
                <router-link v-if="f.contact_id" :to="`/contacts/${f.contact_id}`" class="company-link">{{ f.contact_name }}</router-link>
                <span v-else class="muted">—</span>
              </td>
              <td class="td-product">{{ f.product_name ?? '—' }}</td>
              <td><span class="type-badge">{{ f.forecast_type_name ?? '—' }}</span></td>
              <td>
                <div class="snapshot">
                  <span>{{ f.contact_status_name ?? 'No status' }}</span>
                  <small>{{ f.contact_type_name ?? 'No type' }}</small>
                </div>
              </td>
              <td class="amount-cell">{{ fmtValue(f.amount) }}</td>
              <td><span class="date-text">{{ fmtDate(f.forecast_date) }}</span></td>
              <td class="result-cell" @click.stop>
                <div class="result-inner">
                  <span class="result-badge" :class="resultClass(f.result_name)">{{ f.result_name ?? 'No Result' }}</span>
                  <button v-if="can('edit forecasts') && f.can_edit" class="result-edit-btn" title="Update result" aria-label="Update result" @click="openResultModal(f)" v-html="CI.edit"></button>
                </div>
              </td>
              <td>{{ f.user_name ?? '—' }}</td>
              <td><span class="date-text">{{ fmtDate(f.forecast_updatedate) }}</span></td>
              <td class="actions-cell" @click.stop>
                <button v-if="can('edit forecasts') && f.can_edit" type="button" class="icon-btn btn-edit" title="Edit" aria-label="Edit forecast" @click="openEdit(f.id)" v-html="CI.edit"></button>
                <button v-if="can('delete forecasts') && f.can_edit" class="icon-btn btn-del" title="Delete" aria-label="Delete forecast" @click="confirmDelete(f)" v-html="CI.trash"></button>
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
    <Teleport to="body">
      <div v-if="deleteTarget" class="conf-overlay" @mousedown.self="deleteTarget = null">
        <div class="conf-modal">
          <div class="conf-head">
            <div>
              <p class="conf-title">Delete Forecast</p>
              <p class="conf-sub">This action cannot be undone.</p>
            </div>
            <button class="conf-close" @click="deleteTarget = null"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div class="conf-body">
            <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
            </svg>
            <p class="conf-text">Delete the forecast for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
          </div>
          <div class="conf-foot">
            <button class="conf-cancel" @click="deleteTarget = null">Cancel</button>
            <button class="conf-delete" @click="doDelete" :disabled="deleting">{{ deleting ? 'Deleting…' : 'Delete' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Export Modal -->
    <Teleport to="body">
      <div v-if="exportModal.open" class="remark-overlay" @mousedown.self="exportModal.open = false">
        <div class="export-modal">
          <div class="export-modal-header">
            <div>
              <strong class="export-modal-title">Export Forecasts</strong>
              <p class="export-modal-sub">Pick what to include, then download.</p>
            </div>
            <button class="remark-close" @click="exportModal.open = false"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div class="export-modal-body">
            <div class="export-section">
              <div class="export-cols-head">
                <span class="export-section-label">Columns to include</span>
                <div class="export-cols-actions">
                  <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = true)">All</button>
                  <span class="export-dot-sep">·</span>
                  <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = false)">None</button>
                </div>
              </div>
              <div class="export-cols-grid">
                <label v-for="col in exportCols" :key="col.key" class="export-col-check">
                  <input type="checkbox" v-model="col.checked">
                  <span>{{ col.label }}</span>
                </label>
              </div>
            </div>
          </div>
          <div class="export-modal-footer">
            <p class="export-footer-count">
              Will export <strong>{{ exportRowCount }}</strong> forecast(s) × <strong>{{ exportCols.filter(c => c.checked).length }}</strong> column(s)
            </p>
            <div class="export-action-stack">
              <button class="export-dl-btn export-dl-xls" :disabled="exportCols.every(c => !c.checked)" @click="executeExport('xls')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="export-dl-text">
                  <span class="export-dl-label">Download Excel</span>
                  <span class="export-dl-desc">Formatted with borders &amp; column widths</span>
                </span>
              </button>
              <button class="export-dl-btn export-dl-csv" :disabled="exportCols.every(c => !c.checked)" @click="executeExport('csv')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="export-dl-text">
                  <span class="export-dl-label">Download CSV</span>
                  <span class="export-dl-desc">Plain text, opens in any spreadsheet app</span>
                </span>
              </button>
              <button class="export-cancel-btn" @click="exportModal.open = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Update Result Modal -->
    <Teleport to="body">
      <div v-if="resultModal.open" class="remark-overlay" @mousedown.self="closeResultModal">
        <div class="conf-modal">
          <div class="conf-head">
            <div>
              <p class="conf-title">Update Result</p>
              <p class="conf-sub">{{ resultModal.forecast?.contact_name }}</p>
            </div>
            <button class="conf-close" @click="closeResultModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div class="result-body">
            <div v-if="resultModal.error" class="result-error">{{ resultModal.error }}</div>
            <div class="result-field">
              <label>Result</label>
              <select v-model="resultModal.selectedId">
                <option value="">No Result</option>
                <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
          </div>
          <div class="conf-foot">
            <button class="conf-cancel" @click="closeResultModal">Cancel</button>
            <button class="conf-save" :disabled="resultModal.saving" @click="saveResult">
              {{ resultModal.saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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
import { useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import ForecastFormModal from '../components/ForecastFormModal.vue';
import { usePermissions } from '../composables/usePermissions.js';
import { useLookups } from '../composables/useLookups.js';

// `embedded` = rendered inside the Contacts page's Forecast tab (vs the standalone route).
defineProps({ embedded: { type: Boolean, default: false } });

const { can } = usePermissions();
const { lookups, load: loadLookups } = useLookups();
const router = useRouter();

function openContactRow(f) {
  if (f.contact_id) router.push(`/contacts/${f.contact_id}`);
}

const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const CI = {
  edit:  _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash: _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
};

function toast(message, type = 'success') {
  window.dispatchEvent(new CustomEvent('crm-toast', { detail: { message, type } }));
}

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const resultOptions = computed(() => {
  const list = lookups.value?.forecast_results;
  return Array.isArray(list)
    ? list.filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
    : [];
});

const PER_PAGE_OPTIONS = [20, 50, 100];
const YEAR_OPTIONS = Array.from({ length: new Date().getFullYear() - 2019 }, (_, i) => 2020 + i);

const perPage     = ref(50);
const forecasts   = ref([]);
const summary     = ref({});
const meta        = ref({});
const loading     = ref(false);
const deleteTarget = ref(null);
const deleting    = ref(false);
const page        = ref(1);
const sortField   = ref('forecast_date');
const sortDir     = ref('desc');

const selectedIds = ref(new Set());
const allSelected = computed(() => forecasts.value.length > 0 && selectedIds.value.size === forecasts.value.length);
const someSelected = computed(() => selectedIds.value.size > 0 && selectedIds.value.size < forecasts.value.length);

function toggleSelect(id) {
  const s = new Set(selectedIds.value);
  s.has(id) ? s.delete(id) : s.add(id);
  selectedIds.value = s;
}
function toggleAll() {
  selectedIds.value = allSelected.value ? new Set() : new Set(forecasts.value.map(f => f.id));
}

const filters = reactive({
  q: '', product_id: '', forecast_type_id: '', result_id: '', contact_status_id: '', user_id: '', year: '', from_date: '', to_date: '',
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
  selectedIds.value = new Set();
  try {
    const res = await api.get('/v1/forecasts', { params: buildParams() });
    forecasts.value = Array.isArray(res.data?.data) ? res.data.data : [];
    meta.value = res.data && typeof res.data === 'object' ? res.data : {};
  } catch (e) {
    forecasts.value = [];
    meta.value = {};
    console.error('Forecast list load failed:', e);
  } finally {
    loading.value = false;
  }
}

async function loadSummary() {
  try {
    const res = await api.get('/v1/forecasts/summary', { params: { ...buildParams(false), totals_only: 1 } });
    summary.value = res.data?.data?.totals ?? {};
  } catch (e) {
    summary.value = {};
    console.error('Forecast summary load failed:', e);
  }
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

const FORECAST_EXPORT_COLUMNS = [
  { key: 'no',                  label: 'No' },
  { key: 'contact_name',        label: 'Company' },
  { key: 'product_name',        label: 'Product' },
  { key: 'forecast_type_name',  label: 'Type' },
  { key: 'contact_status_name', label: 'Contact Status' },
  { key: 'contact_type_name',   label: 'Contact Type' },
  { key: 'amount',              label: 'Amount' },
  { key: 'forecast_date',       label: 'Forecast Date' },
  { key: 'result_name',         label: 'Result' },
  { key: 'user_name',           label: 'Assigned' },
  { key: 'forecast_updatedate', label: 'Updated' },
];

const exportModal = ref({ open: false });
const exportCols  = ref(FORECAST_EXPORT_COLUMNS.map(c => ({ ...c, checked: true })));
const exportRowCount = computed(() =>
  selectedIds.value.size > 0 ? selectedIds.value.size : forecasts.value.length
);

function openExportModal() { exportModal.value.open = true; }

function getCellVal(key, f, i) {
  switch (key) {
    case 'no':                  return i + 1;
    case 'contact_name':        return f.contact_name ?? '—';
    case 'product_name':        return f.product_name ?? '—';
    case 'forecast_type_name':  return f.forecast_type_name ?? '—';
    case 'contact_status_name': return f.contact_status_name ?? '—';
    case 'contact_type_name':   return f.contact_type_name ?? '—';
    case 'amount':              return f.amount ?? 0;
    case 'forecast_date':       return fmtDate(f.forecast_date);
    case 'result_name':         return f.result_name ?? 'No Result';
    case 'user_name':           return f.user_name ?? '—';
    case 'forecast_updatedate': return fmtDate(f.forecast_updatedate);
    default:                    return '';
  }
}

function executeExport(format = 'csv') {
  const rows = selectedIds.value.size > 0
    ? forecasts.value.filter(f => selectedIds.value.has(f.id))
    : forecasts.value;
  if (!rows.length) return;
  const cols = exportCols.value.filter(c => c.checked);
  if (!cols.length) return;

  const date = new Date().toISOString().slice(0, 10);

  const triggerDownload = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 100);
  };

  if (format === 'xls') {
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const NUMERIC_KEYS = new Set(['no', 'amount']);
    const COL_WIDTHS = {
      no: 30, contact_name: 190, product_name: 110, forecast_type_name: 90,
      contact_status_name: 100, contact_type_name: 80, amount: 75,
      forecast_date: 90, result_name: 90, user_name: 90, forecast_updatedate: 90,
    };
    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n';
    const B = '<Border ss:LineStyle="Continuous" ss:Weight="1"/>';
    const BORDERS = `<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>`;
    xml += '<Styles>';
    xml += `<Style ss:ID="H"><Font ss:Bold="1" ss:FontName="Arial" ss:Size="10"/><Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="0"/>${BORDERS}</Style>`;
    xml += `<Style ss:ID="D"><Font ss:FontName="Arial" ss:Size="10"/><Alignment ss:Vertical="Top" ss:WrapText="1"/>${BORDERS}</Style>`;
    xml += `<Style ss:ID="N"><Font ss:FontName="Arial" ss:Size="10"/><Alignment ss:Horizontal="Right" ss:Vertical="Top"/>${BORDERS}</Style>`;
    xml += '</Styles>';
    xml += '<Worksheet ss:Name="Forecasts"><Table>\n';
    cols.forEach(c => { xml += `<Column ss:Width="${COL_WIDTHS[c.key] ?? 100}"/>`; });
    xml += '\n<Row ss:Height="18">' + cols.map(c => `<Cell ss:StyleID="H"><Data ss:Type="String">${esc(c.label)}</Data></Cell>`).join('') + '</Row>\n';
    rows.forEach((f, i) => {
      xml += '<Row>' + cols.map(c => {
        const v = getCellVal(c.key, f, i);
        const isNum = NUMERIC_KEYS.has(c.key) && v !== '' && v !== '—' && !isNaN(Number(v));
        return `<Cell ss:StyleID="${isNum ? 'N' : 'D'}"><Data ss:Type="${isNum ? 'Number' : 'String'}">${isNum ? v : esc(v)}</Data></Cell>`;
      }).join('') + '</Row>\n';
    });
    xml += '</Table></Worksheet></Workbook>';
    triggerDownload(new Blob([xml], { type: 'application/vnd.ms-excel' }), `Forecast_Export_${date}.xls`);
  } else {
    const lines = [cols.map(c => c.label)];
    rows.forEach((f, i) => lines.push(cols.map(c => getCellVal(c.key, f, i))));
    const csv = '﻿' + lines.map(row => row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    triggerDownload(new Blob([csv], { type: 'text/csv;charset=utf-8;' }), `Forecast_Export_${date}.csv`);
  }
  exportModal.value.open = false;
}

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

// Inline result editor
const resultModal = ref({ open: false, saving: false, error: '', forecast: null, selectedId: '' });

function openResultModal(f) {
  resultModal.value = { open: true, saving: false, error: '', forecast: f, selectedId: f.result_id ?? '' };
}
function closeResultModal() { resultModal.value.open = false; }

async function saveResult() {
  const f = resultModal.value.forecast;
  if (!f) return;
  resultModal.value.saving = true;
  resultModal.value.error  = '';
  try {
    await api.put(`/v1/forecasts/${f.id}`, {
      contact_id:       f.contact_id,
      product_id:       f.product_id,
      forecast_type_id: f.forecast_type_id,
      amount:           f.amount,
      forecast_date:    f.forecast_date,
      result_id:        resultModal.value.selectedId || null,
    });
    const hit = resultModal.value.selectedId
      ? lookups.value?.forecast_results?.find(r => String(r.id) === String(resultModal.value.selectedId))
      : null;
    f.result_id   = resultModal.value.selectedId || null;
    f.result_name = hit?.name ?? null;
    closeResultModal();
    toast('Result updated.');
  } catch (e) {
    resultModal.value.error = e.response?.data?.message ?? 'Failed to update. Please try again.';
  } finally {
    resultModal.value.saving = false;
  }
}

onMounted(async () => {
  await Promise.all([loadLookups(), load(), loadSummary()]);
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1500px; margin: 0 auto; }
.page-embedded { padding: 0; max-width: none; }
.page-head-embedded { margin-bottom: 14px; }

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
  background: var(--primary-soft); color: var(--primary);
  border: 1.5px solid var(--primary); border-radius: 999px; padding: 10px 18px;
  font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
  transition: background 0.15s, color 0.15s, transform 0.06s;
}
.btn-light-pill:hover { background: var(--primary); color: var(--primary-on); }
.btn-light-pill:active { transform: translateY(1px); }

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
.btn-export { background: #10b981; color: #fff; border: none; }
.btn-export:hover { background: #059669; }

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
table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap; overflow: hidden;
}
thead th:last-child { border-right: none; }
thead th.sortable { cursor: pointer; user-select: none; }
thead th.sortable:hover { color: var(--text-1); background: var(--border-soft); }
.sort-icon { display: inline-flex; vertical-align: middle; margin-left: 3px; color: var(--primary); }
.th-filter { white-space: normal !important; overflow: visible !important; vertical-align: top !important; padding: 8px 10px !important; }
.col-head { display: flex; flex-direction: column; gap: 5px; }
.col-head span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.55px; color: var(--text-2); white-space: nowrap; }
.col-filter-sel { width: 100%; height: 22px; font-size: 11px; padding: 0 4px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-1); cursor: pointer; }
.col-filter-sel:focus { outline: 1px solid var(--primary); }
tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; font-size: 13px; white-space: nowrap; overflow: hidden; }
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }
.row-clickable { cursor: pointer; }
.td-company  { white-space: normal !important; word-break: break-word; overflow: visible !important; }
.td-product  { white-space: normal !important; word-break: break-word; overflow: visible !important; }

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
.result-badge     { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.result-confirmed { background: #dcfce7; color: #15803d; }
.result-pending   { background: #fef3c7; color: #b45309; }
.result-rejected  { background: #fee2e2; color: #b91c1c; }
.result-no-result { background: var(--surface-2); color: var(--text-3); }
.amount-cell { font-weight: 800; color: #0369a1; white-space: nowrap; }
.snapshot { display: flex; flex-direction: column; gap: 2px; }
.snapshot span { font-size: 12.5px; font-weight: 600; color: var(--text-1); }
.snapshot small { font-size: 11px; color: var(--text-3); }
.th-check, .td-check { text-align: center; padding: 0 !important; }
.row-cb { width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary); }
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

/* Confirm / delete modal */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; animation: overlay-fade-in 0.18s ease; }
.conf-overlay > .conf-modal { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-3); line-height: 1; padding: 0; }
.conf-close:hover { color: var(--text-1); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete { height: 38px; padding: 0 18px; background: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-delete:hover:not(:disabled) { background: #b91c1c; }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }
.conf-save { height: 38px; padding: 0 18px; background: var(--primary); color: var(--primary-on); border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-save:hover:not(:disabled) { background: var(--primary-hover); }
.conf-save:disabled { opacity: 0.5; cursor: not-allowed; }

/* Inline result editor */
.result-cell { white-space: normal !important; overflow: hidden !important; }
.result-inner { display: flex; align-items: center; gap: 5px; }
.result-inner .result-badge { cursor: default; }
.result-inner:has(.result-edit-btn) .result-badge { cursor: pointer; }
.result-edit-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-3); cursor: pointer;
  transition: background 0.12s, color 0.12s, transform 0.06s;
  flex-shrink: 0;
}
.result-edit-btn:hover { background: var(--surface-2); color: var(--text-1); border-color: var(--border); }
.result-edit-btn:active { transform: scale(0.92); }
.result-body { padding: 18px 22px; display: flex; flex-direction: column; gap: 12px; }
.result-error { background: #fee2e2; color: #b91c1c; border-radius: var(--radius-sm); padding: 8px 12px; font-size: 13px; }
.result-field { display: flex; flex-direction: column; gap: 5px; }
.result-field label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.result-field select { height: 38px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px; font-size: 13px; outline: none; background: var(--surface); color: var(--text-1); transition: border-color 0.15s, box-shadow 0.15s; }
.result-field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }

/* Export modal */
.export-modal { background: var(--surface); border-radius: var(--radius-xl, 14px); box-shadow: 0 24px 60px rgba(0,0,0,0.18); width: min(520px, calc(100vw - 48px)); max-height: calc(100vh - 64px); display: flex; flex-direction: column; overflow: hidden; }
.export-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 22px 24px 18px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0; }
.export-modal-title { font-size: 17px; font-weight: 800; color: var(--text-1); }
.export-modal-sub   { font-size: 12.5px; color: var(--text-3); margin: 3px 0 0; }
.export-modal-body  { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.export-modal-footer { display: flex; flex-direction: column; gap: 12px; padding: 16px 24px 20px; border-top: 1px solid var(--border-soft); background: var(--surface-2); flex-shrink: 0; }
.export-footer-count { font-size: 13px; color: var(--text-3); margin: 0; }
.export-footer-count strong { color: var(--primary); }
.export-action-stack { display: flex; flex-direction: column; gap: 10px; }
.export-dl-btn { width: 100%; display: flex; align-items: flex-start; gap: 12px; padding: 13px 16px; border-radius: var(--radius, 10px); border: none; cursor: pointer; text-align: left; transition: opacity 0.15s, transform 0.08s; }
.export-dl-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.export-dl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-dl-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.export-dl-text { display: flex; flex-direction: column; gap: 2px; }
.export-dl-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.export-dl-desc  { font-size: 12px; opacity: 0.82; line-height: 1.3; }
.export-dl-xls { background: #10b981; color: #fff; }
.export-dl-csv { background: var(--surface); border: 1.5px solid var(--border) !important; color: var(--text-1); }
.export-cancel-btn { width: 100%; padding: 10px 16px; border: none; border-radius: var(--radius-sm, 6px); background: none; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-3); transition: background 0.12s, color 0.12s; }
.export-cancel-btn:hover { background: var(--border-soft); color: var(--text-2); }
.export-section { display: flex; flex-direction: column; gap: 10px; }
.export-section-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-3); margin-bottom: 2px; }
.export-cols-head    { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.export-cols-actions { display: flex; align-items: center; gap: 4px; }
.export-link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 700; color: var(--primary); padding: 2px 4px; border-radius: 4px; }
.export-link-btn:hover { text-decoration: underline; }
.export-dot-sep { color: var(--text-3); font-size: 12px; }
.export-cols-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 12px; }
.export-col-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--text-2); font-weight: 500; padding: 6px 10px; border-radius: var(--radius-sm, 6px); border: 1px solid var(--border-soft); transition: background 0.12s, border-color 0.12s; }
.export-col-check:hover { background: var(--primary-soft, #dbeafe); border-color: var(--primary); }
.export-col-check input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer; }

@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}

/* ── Overlay + modal animation ── */
@keyframes overlay-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes modal-spring-in { from { opacity: 0; transform: scale(0.92) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.remark-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; animation: overlay-fade-in 0.18s ease; }
.remark-overlay > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
.remark-close { background: none; border: none; cursor: pointer; color: var(--text-3); padding: 4px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: color 0.12s, background 0.12s; }
.remark-close:hover { color: var(--text-1); background: var(--surface-2); }
</style>
