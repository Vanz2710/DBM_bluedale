<template>
  <div class="page">

    <!-- ── Header ── -->
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Forecast Summary</h1>
        <p class="page-subtitle">Monthly forecast totals with contact, status, type, product, result, and owner context.</p>
      </div>
      <div class="page-head-actions">
        <router-link to="/forecasts" class="btn btn-back"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>Back to Forecasts</router-link>
      </div>
    </div>

    <!-- ── Filters ── -->
    <div class="toolbar">
      <div class="filter-group">
        <label>Year</label>
        <select v-model="filters.year" @change="load">
          <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Product</label>
        <select v-model="filters.product_id" @change="load">
          <option value="">All Products</option>
          <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="filters.forecast_type_id" @change="load">
          <option value="">All Types</option>
          <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Result</label>
        <select v-model="filters.result_id" @change="load">
          <option value="">All Results</option>
          <option value="none">No Result</option>
          <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div v-if="isAdmin" class="filter-group">
        <label>User</label>
        <select v-model="filters.user_id" @change="load">
          <option value="">All Users</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="filters.q" @keyup.enter="load" placeholder="Company, product, user…">
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
      <button v-if="hasFilters" class="btn btn-clear" @click="clearFilters">Clear</button>
    </div>

    <!-- ── KPI Stats ── -->
    <div class="stat-row">
      <div class="stat-card">
        <span class="stat-label">Total Forecast</span>
        <strong class="stat-value">{{ fmtValue(totals.total_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--success">
        <span class="stat-label">Confirmed</span>
        <strong class="stat-value">{{ fmtValue(totals.confirmed_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--warning">
        <span class="stat-label">Pending</span>
        <strong class="stat-value">{{ fmtValue(totals.pending_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--danger">
        <span class="stat-label">Rejected</span>
        <strong class="stat-value">{{ fmtValue(totals.rejected_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--muted">
        <span class="stat-label">No Result</span>
        <strong class="stat-value">{{ fmtValue(totals.no_result_amount) }}</strong>
      </div>
    </div>

    <!-- ── Monthly Breakdown ── -->
    <div class="months-row">
      <div v-for="m in months" :key="m.month" class="month-chip">
        <span class="month-chip-name">{{ monthName(m.month) }}</span>
        <strong class="month-chip-value">{{ fmtValue(m.amount) }}</strong>
        <small class="month-chip-count">{{ m.count }} item{{ m.count === 1 ? '' : 's' }}</small>
      </div>
    </div>

    <!-- ── Table ── -->
    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="record-count">
          <span class="count-label">Forecast Rows</span>
          <span class="count-badge">{{ rows.length }}</span>
        </span>
      </div>
      <LoadingSpinner v-if="loading" />
      <div v-else class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Assigned</th>
              <th>Status</th>
              <th>Type</th>
              <th>Company</th>
              <th>Forecast Type</th>
              <th>Product</th>
              <th>Result</th>
              <th v-for="m in 12" :key="m">{{ monthShort(m) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="19" class="empty-state">
                <div class="empty-title">No forecast rows for this selection.</div>
                <div class="empty-sub">Try adjusting the filters above.</div>
              </td>
            </tr>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.user_name ?? '—' }}</td>
              <td>{{ row.contact_status_name ?? '—' }}</td>
              <td>{{ row.contact_type_name ?? '—' }}</td>
              <td>
                <router-link v-if="row.contact_id" :to="`/contacts/${row.contact_id}`" class="company-link">{{ row.contact_name }}</router-link>
                <span v-else class="muted-dash">—</span>
              </td>
              <td><span class="tag">{{ row.forecast_type_name ?? '—' }}</span></td>
              <td>{{ row.product_name ?? '—' }}</td>
              <td>
                <span class="result-badge" :class="resultClass(row.result_name)">{{ row.result_name ?? 'No Result' }}</span>
              </td>
              <td v-for="m in 12" :key="m" class="amount-cell">
                <span v-if="forecastMonth(row) === m">{{ fmtPlain(row.amount) }}</span>
                <span v-else class="muted-dash">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const resultOptions = computed(() =>
  (lookups.value.forecast_results ?? []).filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
);

const hasFilters = computed(() =>
  filters.year !== yearNow ||
  filters.product_id !== '' ||
  filters.forecast_type_id !== '' ||
  filters.result_id !== '' ||
  filters.user_id !== '' ||
  filters.q !== ''
);

function clearFilters() {
  filters.year = yearNow;
  filters.product_id = '';
  filters.forecast_type_id = '';
  filters.result_id = '';
  filters.user_id = '';
  filters.q = '';
  load();
}

const yearNow = new Date().getFullYear();
const years = Array.from({ length: 7 }, (_, i) => yearNow - 3 + i);
const lookups = ref({ forecast_products: [], forecast_types: [], forecast_results: [], users: [] });
const loading = ref(false);
const totals = ref({});
const months = ref([]);
const rows = ref([]);

const filters = reactive({
  year: yearNow,
  product_id: '',
  forecast_type_id: '',
  result_id: '',
  user_id: '',
  q: '',
});

function buildParams() {
  const params = { ...filters };
  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) delete params[key];
  });
  return params;
}

function monthName(month) {
  return new Date(2000, month - 1, 1).toLocaleString('en', { month: 'long' });
}

function monthShort(month) {
  return new Date(2000, month - 1, 1).toLocaleString('en', { month: 'short' });
}

function forecastMonth(row) {
  if (!row.forecast_date) return 0;
  return Number(String(row.forecast_date).slice(5, 7));
}

function fmtValue(value) {
  const n = Number(value ?? 0);
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtPlain(value) {
  const n = Number(value ?? 0);
  return n ? n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
}

function resultClass(name) {
  const key = (name ?? '').toLowerCase().trim();
  if (key === 'confirmed') return 'result-confirmed';
  if (key === 'pending')   return 'result-pending';
  if (key === 'rejected')  return 'result-rejected';
  return 'result-no-result';
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/forecasts/summary', { params: buildParams() });
    totals.value = res.data.data?.totals ?? {};
    months.value = res.data.data?.months ?? [];
    rows.value   = res.data.data?.rows   ?? [];
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await Promise.all([
    api.get('/v1/lookups').then((res) => { lookups.value = res.data; }),
    load(),
  ]);
});
</script>

<style scoped>
/* ── Page shell ─────────────────────────────────────────────────────────────── */
.page { padding: 28px 28px 48px; max-width: 1500px; margin: 0 auto; }

/* ── Page head ──────────────────────────────────────────────────────────────── */
.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

/* ── Buttons ────────────────────────────────────────────────────────────────── */
.btn {
  height: 38px;
  padding: 0 18px;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.06s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
}
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn-primary {
  background: var(--primary);
  color: var(--primary-on);
  box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55);
}
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
.btn-back {
  background: var(--surface);
  color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-back:hover { background: var(--surface-2); color: var(--text-1); }
.btn-clear {
  background: var(--surface);
  color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-clear:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-soft); }

/* ── Toolbar ────────────────────────────────────────────────────────────────── */
.toolbar {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  margin-bottom: 18px;
  box-shadow: var(--shadow-xs);
  border: 1px solid var(--border-soft);
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 220px; }
.filter-group label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: var(--text-3);
  padding-left: 2px;
}
.filter-group select,
.filter-group input {
  height: 38px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 13px;
  outline: none;
  background: var(--surface);
  color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 30px; }
.filter-group select:focus,
.filter-group input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}

/* ── Stat row ───────────────────────────────────────────────────────────────── */
.stat-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin-bottom: 18px;
}
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border-soft);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 16px 18px;
}
.stat-card--success { border-left-color: var(--success); }
.stat-card--warning { border-left-color: var(--warning); }
.stat-card--danger  { border-left-color: var(--danger); }
.stat-card--muted   { border-left-color: var(--text-3); }
.stat-label {
  display: block;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: var(--text-3);
  margin-bottom: 6px;
}
.stat-value {
  display: block;
  font-size: 17px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.3px;
}

/* ── Monthly breakdown ──────────────────────────────────────────────────────── */
.months-row {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  margin-bottom: 18px;
  overflow-x: auto;
}
.month-chip {
  background: var(--surface);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg);
  padding: 10px 12px;
  min-width: 90px;
  box-shadow: var(--shadow-xs);
}
.month-chip-name {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-3);
}
.month-chip-value {
  display: block;
  font-size: 12.5px;
  font-weight: 800;
  color: var(--text-1);
  margin-top: 4px;
}
.month-chip-count {
  display: block;
  font-size: 10px;
  color: var(--text-3);
  margin-top: 2px;
}

/* ── Table wrap ─────────────────────────────────────────────────────────────── */
.table-wrap {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.table-header-bar {
  background: var(--surface);
  padding: 16px 22px;
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label  { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge  {
  background: var(--primary-soft);
  color: var(--primary-text);
  font-size: 11.5px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
}
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1240px; }
thead th {
  background: transparent;
  color: var(--text-3);
  font-size: 11.5px;
  font-weight: 600;
  padding: 14px 14px;
  border-bottom: 1px solid var(--border-soft);
  text-align: left;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 1;
}
tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-soft);
  color: var(--text-1);
  vertical-align: middle;
  font-size: 13px;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

/* ── Inline elements ────────────────────────────────────────────────────────── */
.company-link { color: var(--text-1); font-weight: 700; text-decoration: none; transition: color 0.15s; }
.company-link:hover { color: var(--primary); }

.tag {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-2);
  color: var(--text-2);
}

.result-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  white-space: nowrap;
}
.result-confirmed { background: var(--success-soft); color: var(--success); }
.result-pending   { background: var(--warning-soft); color: var(--warning); }
.result-rejected  { background: var(--danger-soft);  color: var(--danger); }
.result-no-result { background: var(--surface-2);    color: var(--text-2); }

.muted-dash { color: var(--text-3); font-size: 13px; }

.amount-cell { text-align: right; white-space: nowrap; font-weight: 700; color: var(--info); }

/* ── Empty state ────────────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 56px 24px; }
.empty-title  { font-size: 15.5px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
.empty-sub    { font-size: 13px; color: var(--text-3); }

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 1200px) {
  .stat-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 900px) {
  .stat-row { grid-template-columns: repeat(2, 1fr); }
  .months-row { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 600px) {
  .page { padding: 16px 14px 32px; }
  .stat-row { grid-template-columns: 1fr 1fr; }
  .months-row { grid-template-columns: repeat(4, 1fr); }
  .page-title { font-size: 22px; }
}
</style>
