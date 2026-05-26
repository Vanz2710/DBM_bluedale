<template>
  <div class="page">
    <div class="header">
      <div>
        <h1>Forecast Summary</h1>
        <p>Monthly forecast totals with contact, status, type, product, result, and owner context.</p>
      </div>
      <router-link to="/forecasts" class="btn btn-light">Back to Forecasts</router-link>
    </div>

    <div class="filter-bar">
      <select v-model="filters.year" @change="load" class="fc">
        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
      </select>
      <select v-model="filters.product_id" @change="load" class="fc">
        <option value="">All Products</option>
        <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.forecast_type_id" @change="load" class="fc">
        <option value="">All Types</option>
        <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <select v-model="filters.result_id" @change="load" class="fc">
        <option value="">All Results</option>
        <option value="none">No Result</option>
        <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
      <select v-if="isAdmin" v-model="filters.user_id" @change="load" class="fc">
        <option value="">All Users</option>
        <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <input v-model="filters.q" @keyup.enter="load" class="fc fc-search" placeholder="Search company, product, user...">
      <button class="btn btn-search" @click="load">Search</button>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <span>Total Forecast</span>
        <strong>{{ fmtValue(totals.total_amount) }}</strong>
      </div>
      <div class="stat-card confirmed">
        <span>Confirmed</span>
        <strong>{{ fmtValue(totals.confirmed_amount) }}</strong>
      </div>
      <div class="stat-card pending">
        <span>Pending</span>
        <strong>{{ fmtValue(totals.pending_amount) }}</strong>
      </div>
      <div class="stat-card rejected">
        <span>Rejected</span>
        <strong>{{ fmtValue(totals.rejected_amount) }}</strong>
      </div>
      <div class="stat-card no-result">
        <span>No Result</span>
        <strong>{{ fmtValue(totals.no_result_amount) }}</strong>
      </div>
    </div>

    <div class="months-grid">
      <div v-for="m in months" :key="m.month" class="month-card">
        <span>{{ monthName(m.month) }}</span>
        <strong>{{ fmtValue(m.amount) }}</strong>
        <small>{{ m.count }} item{{ m.count === 1 ? '' : 's' }}</small>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-bar">{{ rows.length }} forecast row(s)</div>
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
              <td colspan="19" class="empty-state">No forecast rows for this selection.</td>
            </tr>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.user_name ?? '-' }}</td>
              <td>{{ row.contact_status_name ?? '-' }}</td>
              <td>{{ row.contact_type_name ?? '-' }}</td>
              <td>
                <router-link v-if="row.contact_id" :to="`/contacts/${row.contact_id}`" class="co-link">{{ row.contact_name }}</router-link>
                <span v-else>-</span>
              </td>
              <td><span class="type-badge">{{ row.forecast_type_name ?? '-' }}</span></td>
              <td>{{ row.product_name ?? '-' }}</td>
              <td><span class="result-badge" :class="resultClass(row.result_name)">{{ row.result_name ?? 'No Result' }}</span></td>
              <td v-for="m in 12" :key="m" class="month-cell">
                <span v-if="forecastMonth(row) === m">{{ fmtPlain(row.amount) }}</span>
                <span v-else>-</span>
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
  const key = (name ?? 'No Result').toLowerCase().replace(/\s+/g, '-');
  return `result-${key}`;
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/forecasts/summary', { params: buildParams() });
    totals.value = res.data.data?.totals ?? {};
    months.value = res.data.data?.months ?? [];
    rows.value = res.data.data?.rows ?? [];
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
.page { display: flex; flex-direction: column; height: calc(100vh - var(--topbar-h, 47px)); overflow: hidden; padding: 14px 20px 12px; gap: 10px; }
.header { display: flex; align-items: center; justify-content: space-between; gap: 14px; background: linear-gradient(135deg, #1f2937, #0ea5e9); border-radius: 10px; padding: 14px 20px; color: white; flex-shrink: 0; }
.header h1 { font-size: 17px; font-weight: 800; margin: 0; }
.header p { font-size: 12px; opacity: 0.8; margin: 3px 0 0; }
.btn { height: 32px; padding: 0 13px; border: none; border-radius: 7px; font-size: 12px; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
.btn-light { background: rgba(255,255,255,0.16); color: white; border: 1px solid rgba(255,255,255,0.28); }
.btn-search { background: #0284c7; color: white; }
.filter-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--surface); border-radius: 9px; padding: 9px 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); flex-shrink: 0; }
.fc { height: 32px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: 6px; font-size: 12px; outline: none; background: var(--surface); color: var(--text-1); }
.fc-search { flex: 1; min-width: 180px; }
.stat-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; flex-shrink: 0; }
.stat-card { background: var(--surface); border-left: 4px solid #0284c7; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 12px 14px; }
.stat-card span { display: block; color: var(--text-3); font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; }
.stat-card strong { display: block; color: var(--text-1); font-size: 18px; margin-top: 5px; }
.stat-card.confirmed { border-left-color: #22c55e; }
.stat-card.pending { border-left-color: #f59e0b; }
.stat-card.rejected { border-left-color: #ef4444; }
.stat-card.no-result { border-left-color: #64748b; }
.months-grid { display: grid; grid-template-columns: repeat(12, minmax(92px, 1fr)); gap: 8px; overflow-x: auto; flex-shrink: 0; }
.month-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 9px 10px; min-width: 92px; }
.month-card span { display: block; color: var(--text-3); font-size: 10px; font-weight: 800; text-transform: uppercase; }
.month-card strong { display: block; color: var(--text-1); font-size: 13px; margin-top: 4px; }
.month-card small { display: block; color: var(--text-3); font-size: 10px; margin-top: 2px; }
.table-wrap { flex: 1; min-height: 0; display: flex; flex-direction: column; background: var(--surface); border-radius: 9px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-bar { background: var(--app-bg); padding: 9px 14px; font-size: 12px; font-weight: 800; color: var(--text-1); border-bottom: 2px solid var(--border); flex-shrink: 0; }
.table-scroll { flex: 1; overflow: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1240px; }
thead th { position: sticky; top: 0; z-index: 1; background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; padding: 9px 10px; border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap; }
tbody td { padding: 8px 10px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:hover { background: var(--app-bg); }
.co-link { color: var(--text-1); font-weight: 800; text-decoration: none; }
.co-link:hover { color: #0284c7; }
.type-badge, .result-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 800; white-space: nowrap; }
.type-badge { background: #e0f2fe; color: #0369a1; }
.result-confirmed { background: #dcfce7; color: #15803d; }
.result-pending { background: #fef3c7; color: #b45309; }
.result-rejected { background: #fee2e2; color: #b91c1c; }
.result-no-result { background: #f1f5f9; color: #64748b; }
.month-cell { text-align: right; white-space: nowrap; font-weight: 800; color: #0369a1; }
.empty-state { text-align: center; padding: 42px; color: var(--text-3); }
@media (max-width: 1200px) {
  .stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 800px) {
  .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
  .page { padding: 10px 12px 8px; }
  .header { flex-direction: column; align-items: flex-start; }
  .stat-grid { grid-template-columns: 1fr; }
}
</style>
