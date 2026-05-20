<template>
  <div class="page">
    <!-- Banner -->
    <div class="page-banner">
      <div>
        <h1>CRM Dashboard</h1>
        <p>Browse and filter all company records across your sales pipeline.</p>
      </div>
      <div class="banner-count">
        {{ fmt(meta.total ?? 0) }}
        <span>records{{ filtersActive ? ' matched' : ' total' }}</span>
      </div>
    </div>

    <!-- Filter panel -->
    <div class="filter-panel">
      <div class="filter-row">
        <div class="filter-group wide">
          <span class="filter-label">Search</span>
          <input class="filter-input" v-model="filters.search" @keyup.enter="applyFilters" placeholder="Company name…">
        </div>
        <div class="filter-group">
          <span class="filter-label">Status</span>
          <select class="filter-select" v-model="filters.status_id">
            <option value="">All Statuses</option>
            <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Industry</span>
          <select class="filter-select" v-model="filters.industry_id">
            <option value="">All Industries</option>
            <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Category</span>
          <select class="filter-select" v-model="filters.category_id">
            <option value="">All Categories</option>
            <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Assigned To</span>
          <select class="filter-select" v-model="filters.user_id">
            <option value="">All Users</option>
            <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Type</span>
          <select class="filter-select" v-model="filters.type_id">
            <option value="">All Types</option>
            <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div class="btn-actions">
          <button class="btn btn-primary" @click="applyFilters">Search</button>
          <button class="btn btn-reset" @click="resetFilters">Reset</button>
        </div>
      </div>
    </div>

    <!-- Results bar -->
    <div class="results-bar">
      <span>
        Showing <strong>{{ contacts.length }}</strong> of <strong>{{ fmt(meta.total ?? 0) }}</strong> records
        <span v-if="filtersActive" class="active-tag">Filters active</span>
      </span>
      <span>Page {{ meta.current_page ?? 1 }} of {{ meta.last_page ?? 1 }}</span>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <LoadingSpinner v-if="loading" />
      <table v-else>
        <thead>
          <tr>
            <th>Company Name</th>
            <th>Industry</th>
            <th>Status</th>
            <th>Type</th>
            <th>Category</th>
            <th>Assigned To</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="contacts.length === 0">
            <td colspan="7" class="empty-state">No records found matching your filters.</td>
          </tr>
          <tr v-for="c in contacts" :key="c.id">
            <td><span class="company-name">{{ c.name }}</span></td>
            <td>{{ c.industry?.name ?? '—' }}</td>
            <td>
              <span v-if="c.status" class="badge" :class="statusBadge(c.status.name)">{{ c.status.name }}</span>
              <span v-else>—</span>
            </td>
            <td>
              <span v-if="c.type" class="badge badge-type">{{ c.type.name }}</span>
              <span v-else>—</span>
            </td>
            <td>{{ c.category?.name ?? '—' }}</td>
            <td>{{ c.user?.name ?? '—' }}</td>
            <td>
              <router-link :to="{ name: 'crm-view', params: { id: c.id } }" class="view-link">View →</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="page-btn" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">← Prev</button>
      <button v-for="p in pageRange" :key="p" class="page-btn" :class="{ active: p === meta.current_page }" @click="goPage(p)">{{ p }}</button>
      <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">Next →</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading  = ref(true);
const contacts = ref([]);
const meta     = ref({});
const lookups  = ref({ statuses: [], industries: [], categories: [], types: [], users: [] });

const filters = reactive({ search: '', status_id: '', industry_id: '', category_id: '', user_id: '', type_id: '' });
const currentPage = ref(1);

const filtersActive = computed(() => Object.values(filters).some(v => v !== ''));
const fmt = (n) => (n ?? 0).toLocaleString();
const pageRange = computed(() => {
  const p = meta.value.current_page ?? 1;
  const l = meta.value.last_page ?? 1;
  const start = Math.max(1, p - 2);
  const end   = Math.min(l, p + 2);
  return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

function statusBadge(name) {
  const sl = (name ?? '').toLowerCase();
  if (sl.includes('existing')) return 'badge-existing';
  if (sl.includes('potential')) return 'badge-potential';
  if (sl.includes('raw')) return 'badge-raw';
  return 'badge-status';
}

async function fetchContacts() {
  loading.value = true;
  const params = { ...filters, page: currentPage.value, per_page: 100 };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  const { data } = await axios.get('/v1/contacts', { params });
  contacts.value = data.data;
  meta.value     = data;
  loading.value  = false;
}

function applyFilters() { currentPage.value = 1; fetchContacts(); }
function resetFilters()  { Object.keys(filters).forEach(k => filters[k] = ''); currentPage.value = 1; fetchContacts(); }
function goPage(p)       { currentPage.value = p; fetchContacts(); }

onMounted(async () => {
  const [, res] = await Promise.all([
    axios.get('/v1/lookups').then(r => { lookups.value = r.data; }),
    fetchContacts(),
  ]);
});
</script>

<style scoped>
.page { max-width: 1400px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 40px; text-align: center; color: var(--text-3); }

.page-banner { background: linear-gradient(135deg,#1a2f4a,#3498db); border-radius:10px; padding:28px 36px; margin-bottom:24px; color:white; display:flex; justify-content:space-between; align-items:center; }
.page-banner h1 { font-size:22px; font-weight:700; margin:0 0 4px; }
.page-banner p  { font-size:14px; opacity:0.8; margin:0; }
.banner-count { font-size:42px; font-weight:800; text-align:right; line-height:1; }
.banner-count span { display:block; font-size:11px; font-weight:400; opacity:0.7; text-transform:uppercase; letter-spacing:1px; margin-top:4px; }

.filter-panel { background:var(--surface); border-radius:10px; padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:20px; }
.filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:130px; }
.filter-group.wide { flex:2.5; min-width:220px; }
.filter-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--text-3); }
.filter-input, .filter-select { height:40px; padding:0 12px; border:1.5px solid var(--border); border-radius:7px; font-size:14px; color:#2c3e50; background:var(--surface); outline:none; }
.filter-input:focus, .filter-select:focus { border-color:#3498db; }
.btn-actions { display:flex; gap:8px; align-items:flex-end; }
.btn { height:40px; padding:0 20px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
.btn-primary { background:#3498db; color:white; }
.btn-reset { background:var(--app-bg); color:var(--text-2); border:1.5px solid var(--border); }

.results-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; font-size:14px; color:var(--text-2); }
.results-bar strong { color:var(--text-1); }
.active-tag { background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700; padding:2px 9px; border-radius:20px; margin-left:8px; }

.table-wrap { background:var(--surface); border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); overflow:hidden; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
thead th { background:var(--app-bg); color:var(--text-2); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; padding:13px 16px; border-bottom:2px solid var(--border); text-align:left; white-space:nowrap; }
tbody td { padding:12px 16px; border-bottom:1px solid var(--border); font-size:14px; color:#374151; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover { background:var(--app-bg); }
.company-name { font-weight:600; color:var(--text-1); }
.empty-state { text-align:center; padding:40px; color:var(--text-3); }

.badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-raw      { background:#f1f5f9; color:#64748b; }
.badge-existing { background:#dcfce7; color:#15803d; }
.badge-potential{ background:#fff7ed; color:#c2410c; }
.badge-status   { background:#dbeafe; color:#1d4ed8; }
.badge-type     { background:#fef3c7; color:#92400e; }

.view-link { display:inline-flex; align-items:center; font-size:12px; font-weight:600; color:#3498db; text-decoration:none; padding:5px 13px; border-radius:6px; border:1.5px solid #bfdbfe; }
.view-link:hover { background:#3498db; color:white; }

.pagination { display:flex; gap:5px; justify-content:center; margin-top:24px; flex-wrap:wrap; }
.page-btn { padding:7px 14px; border-radius:7px; font-size:14px; border:1.5px solid var(--border); color:var(--text-2); background:var(--surface); cursor:pointer; font-weight:500; }
.page-btn:hover { border-color:#3498db; color:#3498db; }
.page-btn.active { background:#3498db; color:white; border-color:#3498db; font-weight:700; }
.page-btn:disabled { color:#cbd5e1; cursor:not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; gap: 12px; padding: 20px 20px; }
  .banner-count { font-size: 28px; text-align: left; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 680px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 100%; min-width: 100%; }
  .filter-group.wide { min-width: 100%; }
  .btn-actions { width: 100%; }
}
</style>
