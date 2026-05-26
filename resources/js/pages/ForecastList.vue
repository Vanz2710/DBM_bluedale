<template>
  <div class="page">
    <div class="header">
      <div>
        <h1>Forecasts</h1>
        <p>Track forecasted revenue by company, product, type, result, and owner.</p>
      </div>
      <div class="header-actions">
        <div class="stat-chip">
          <strong>{{ summary.forecast_count ?? 0 }}</strong>
          <span>Forecasts</span>
        </div>
        <div class="stat-chip">
          <strong>{{ fmtValue(summary.total_amount) }}</strong>
          <span>Total</span>
        </div>
        <router-link to="/forecasts/summary" class="btn btn-light">Summary</router-link>
        <button type="button" class="btn btn-add" @click="openAdd">+ Add Forecast</button>
      </div>
    </div>

    <div class="filter-bar">
      <input v-model="filters.q" @keyup.enter="applyFilters" class="fc fc-search" placeholder="Search company, product, user...">
      <select v-model="filters.product_id" @change="applyFilters" class="fc">
        <option value="">All Products</option>
        <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.forecast_type_id" @change="applyFilters" class="fc">
        <option value="">All Types</option>
        <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <select v-model="filters.result_id" @change="applyFilters" class="fc">
        <option value="">All Results</option>
        <option value="none">No Result</option>
        <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
      <select v-if="isAdmin" v-model="filters.user_id" @change="applyFilters" class="fc">
        <option value="">All Users</option>
        <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <input type="date" v-model="filters.from_date" class="fc fc-date" title="Forecast from">
      <input type="date" v-model="filters.to_date" class="fc fc-date" title="Forecast to">
      <button class="btn btn-search" @click="applyFilters">Search</button>
      <button class="btn btn-reset" @click="resetFilters">Reset</button>
    </div>

    <div class="table-wrap">
      <div class="table-bar">
        <span>{{ meta.total ?? forecasts.length }} forecast(s)</span>
        <span>Page {{ meta.current_page ?? 1 }} of {{ meta.last_page ?? 1 }}</span>
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
              <th>Contact Snapshot</th>
              <th class="sortable" @click="changeSort('amount')">Amount {{ sortIcon('amount') }}</th>
              <th class="sortable" @click="changeSort('forecast_date')">Forecast Date {{ sortIcon('forecast_date') }}</th>
              <th>Result</th>
              <th>Assigned</th>
              <th class="sortable" @click="changeSort('forecast_updatedate')">Updated {{ sortIcon('forecast_updatedate') }}</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="forecasts.length === 0">
              <td colspan="11" class="empty-state">No forecasts found.</td>
            </tr>
            <tr v-for="(f, idx) in forecasts" :key="f.id">
              <td>{{ (meta.from ?? 1) + idx }}</td>
              <td>
                <router-link v-if="f.contact_id" :to="`/contacts/${f.contact_id}`" class="co-link">{{ f.contact_name }}</router-link>
                <span v-else>-</span>
              </td>
              <td>{{ f.product_name ?? '-' }}</td>
              <td><span class="type-badge">{{ f.forecast_type_name ?? '-' }}</span></td>
              <td>
                <div class="snapshot">
                  <span>{{ f.contact_status_name ?? 'No status' }}</span>
                  <small>{{ f.contact_type_name ?? 'No type' }}</small>
                </div>
              </td>
              <td class="amount-cell">{{ fmtValue(f.amount) }}</td>
              <td>{{ fmtDate(f.forecast_date) }}</td>
              <td><span class="result-badge" :class="resultClass(f.result_name)">{{ f.result_name ?? 'No Result' }}</span></td>
              <td>{{ f.user_name ?? '-' }}</td>
              <td>{{ fmtDate(f.forecast_updatedate) }}</td>
              <td class="td-actions">
                <button type="button" class="icon-btn edit" title="Edit" @click="openEdit(f.id)">Edit</button>
                <button class="icon-btn del" title="Delete" @click="confirmDelete(f)">Del</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="pagination">
        <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next</button>
      </div>
    </div>

    <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
      <div class="modal">
        <h3>Delete Forecast?</h3>
        <p>Delete the forecast for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
        <div class="modal-btns">
          <button class="mb-cancel" @click="deleteTarget = null">Cancel</button>
          <button class="mb-danger" @click="doDelete" :disabled="deleting">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
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

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const resultOptions = computed(() =>
  (lookups.value.forecast_results ?? []).filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
);

const lookups = ref({ forecast_products: [], forecast_types: [], forecast_results: [], users: [] });
const forecasts = ref([]);
const summary = ref({});
const meta = ref({});
const loading = ref(false);
const deleteTarget = ref(null);
const deleting = ref(false);
const page = ref(1);
const sortField = ref('forecast_date');
const sortDir = ref('desc');

const filters = reactive({
  q: '',
  product_id: '',
  forecast_type_id: '',
  result_id: '',
  user_id: '',
  from_date: '',
  to_date: '',
});

function buildParams(includePaging = true) {
  const params = {
    ...filters,
    sort_field: sortField.value,
    sort_direction: sortDir.value,
  };
  if (includePaging) {
    params.page = page.value;
    params.per_page = 100;
  }
  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) delete params[key];
  });
  return params;
}

function fmtValue(value) {
  const n = Number(value ?? 0);
  if (!n) return 'RM 0.00';
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtDate(value) {
  if (!value) return '-';
  const [y, m, d] = String(value).slice(0, 10).split('-');
  return `${d}-${m}-${y}`;
}

function sortIcon(field) {
  if (sortField.value !== field) return '';
  return sortDir.value === 'asc' ? 'up' : 'down';
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
  const res = await api.get('/v1/forecasts/summary', { params: buildParams(false) });
  summary.value = res.data.data?.totals ?? {};
}

function applyFilters() {
  page.value = 1;
  load();
  loadSummary();
}

function resetFilters() {
  Object.keys(filters).forEach((key) => { filters[key] = ''; });
  page.value = 1;
  load();
  loadSummary();
}

function changePage(nextPage) {
  page.value = nextPage;
  load();
}

function confirmDelete(forecast) {
  deleteTarget.value = forecast;
}

const formModal = ref({ open: false, mode: 'add', forecastId: null });
function openAdd() { formModal.value = { open: true, mode: 'add', forecastId: null }; }
function openEdit(id) { formModal.value = { open: true, mode: 'edit', forecastId: id }; }
function closeFormModal() { formModal.value.open = false; }
function onFormSaved() {
  closeFormModal();
  load();
  loadSummary();
}

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/forecasts/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    load();
    loadSummary();
  } finally {
    deleting.value = false;
  }
}

onMounted(async () => {
  const [lookupRes] = await Promise.all([
    api.get('/v1/lookups').then((res) => { lookups.value = res.data; }),
    load(),
    loadSummary(),
  ]);
});
</script>

<style scoped>
.page { display: flex; flex-direction: column; height: calc(100vh - var(--topbar-h, 47px)); overflow: hidden; padding: 14px 20px 12px; gap: 10px; }
.header { display: flex; align-items: center; justify-content: space-between; gap: 14px; background: linear-gradient(135deg, #1f2937, #0ea5e9); border-radius: 10px; padding: 14px 20px; color: white; flex-shrink: 0; }
.header h1 { font-size: 17px; font-weight: 800; margin: 0; }
.header p { font-size: 12px; opacity: 0.8; margin: 3px 0 0; }
.header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.stat-chip { min-width: 86px; background: rgba(255,255,255,0.14); border-radius: 8px; padding: 6px 10px; text-align: center; }
.stat-chip strong { display: block; font-size: 13px; line-height: 1; }
.stat-chip span { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.75; margin-top: 3px; }
.btn { height: 32px; padding: 0 13px; border: none; border-radius: 7px; font-size: 12px; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
.btn-add, .btn-light { background: rgba(255,255,255,0.16); color: white; border: 1px solid rgba(255,255,255,0.28); }
.filter-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--surface); border-radius: 9px; padding: 9px 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); flex-shrink: 0; }
.fc { height: 32px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: 6px; font-size: 12px; outline: none; background: var(--surface); color: var(--text-1); }
.fc:focus { border-color: #0ea5e9; }
.fc-search { flex: 1; min-width: 180px; }
.fc-date { width: 132px; }
.btn-search { background: #0284c7; color: white; }
.btn-reset { background: var(--app-bg); color: var(--text-2); border: 1.5px solid var(--border); }
.table-wrap { flex: 1; min-height: 0; display: flex; flex-direction: column; background: var(--surface); border-radius: 9px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-bar { display: flex; justify-content: space-between; background: var(--app-bg); padding: 9px 14px; font-size: 12px; font-weight: 800; color: var(--text-1); border-bottom: 2px solid var(--border); flex-shrink: 0; }
.table-scroll { flex: 1; overflow: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th { position: sticky; top: 0; z-index: 1; background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; padding: 9px 10px; border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap; }
thead th.sortable { cursor: pointer; }
tbody td { padding: 9px 10px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:hover { background: var(--app-bg); }
.co-link { color: var(--text-1); font-weight: 800; text-decoration: none; }
.co-link:hover { color: #0284c7; }
.amount-cell { font-weight: 800; color: #0369a1; white-space: nowrap; }
.type-badge, .result-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 800; white-space: nowrap; }
.type-badge { background: #e0f2fe; color: #0369a1; }
.result-confirmed { background: #dcfce7; color: #15803d; }
.result-pending { background: #fef3c7; color: #b45309; }
.result-rejected { background: #fee2e2; color: #b91c1c; }
.result-no-result { background: #f1f5f9; color: #64748b; }
.snapshot { display: flex; flex-direction: column; gap: 2px; }
.snapshot span { font-weight: 700; color: var(--text-1); }
.snapshot small { color: var(--text-3); }
.td-actions { white-space: nowrap; }
.icon-btn { height: 26px; padding: 0 8px; border-radius: 6px; font-size: 11px; font-weight: 800; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; margin-right: 4px; }
.icon-btn.edit { background: #fef9c3; color: #854d0e; }
.icon-btn.del { background: #fee2e2; color: #991b1b; }
.empty-state { text-align: center; padding: 42px; color: var(--text-3); }
.pagination { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 10px; border-top: 1px solid var(--border); font-size: 12px; }
.pagination button { padding: 5px 12px; border: 1.5px solid var(--border); border-radius: 6px; background: var(--surface); cursor: pointer; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000; display: flex; align-items: center; justify-content: center; }
.modal { background: var(--surface); border-radius: 12px; padding: 24px 28px; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal h3 { font-size: 15px; font-weight: 800; color: var(--text-1); margin: 0 0 6px; }
.modal p { font-size: 13px; color: var(--text-2); margin: 0 0 18px; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; }
.mb-cancel, .mb-danger { height: 34px; padding: 0 16px; border: none; border-radius: 7px; font-size: 13px; font-weight: 800; cursor: pointer; }
.mb-cancel { background: var(--app-bg); color: var(--text-2); }
.mb-danger { background: #ef4444; color: white; }
@media (max-width: 768px) {
  .page { padding: 10px 12px 8px; }
  .header { flex-direction: column; align-items: flex-start; }
  .header-actions { width: 100%; justify-content: flex-start; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 980px; }
}
</style>
