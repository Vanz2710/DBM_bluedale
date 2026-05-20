<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Summary</h1>
        <p>Full company tracking across all months</p>
      </div>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>Year</label>
        <select v-model="year" @change="load" class="year-select">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="filters.search" @keyup.enter="load" placeholder="Company name…">
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="filters.user_id" @change="load">
          <option value="">All Users</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="filters.status_id" @change="load">
          <option value="">All</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="filters.type_id" @change="load">
          <option value="">All</option>
          <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Category</label>
        <select v-model="filters.category_id" @change="load">
          <option value="">All</option>
          <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Industry</label>
        <select v-model="filters.industry_id" @change="load">
          <option value="">All</option>
          <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Area</label>
        <select v-model="filters.area_id" @change="load">
          <option value="">All</option>
          <option v-for="a in lookups.areas" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
      </div>
      <div class="filter-actions">
        <button class="btn btn-primary" @click="load">Search</button>
        <button class="btn btn-reset" @click="resetFilters">Reset</button>
        <button class="btn btn-export" @click="exportSelected">Export</button>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />
    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
            <th>No</th>
            <th>User</th>
            <th>Status</th>
            <th>Type</th>
            <th>Category</th>
            <th>Industry</th>
            <th>Area</th>
            <th>Company</th>
            <th v-for="m in months" :key="m" class="month-col">{{ m }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="contacts.length === 0">
            <td :colspan="9 + months.length" class="empty-state">No records found.</td>
          </tr>
          <tr v-for="(c, idx) in contacts" :key="c.id">
            <td><input type="checkbox" :value="c.id" v-model="selectedIds"></td>
            <td>{{ idx + 1 }}</td>
            <td>{{ c.user ?? '—' }}</td>
            <td>{{ c.status ?? '—' }}</td>
            <td>{{ c.type ?? '—' }}</td>
            <td>{{ c.category ?? '—' }}</td>
            <td>{{ c.industry ?? '—' }}</td>
            <td>{{ c.area ?? '—' }}</td>
            <td class="company-cell">
              <router-link :to="`/contacts/${c.id}`" class="company-link">{{ c.name }}</router-link>
            </td>
            <td v-for="m in 12" :key="m" class="task-cell">
              <div v-if="c.months[m]" class="task-badge badge-green">
                <span class="badge-date">{{ c.months[m].date }}</span>
                <span class="badge-info">{{ c.months[m].task }}</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
const years = Array.from({ length: 7 }, (_, i) => 2024 + i);
const year = ref(new Date().getFullYear());
const contacts = ref([]);
const loading = ref(false);
const selectedIds = ref([]);
const selectAllRef = ref(null);
const lookups = ref({ users: [], statuses: [], types: [], categories: [], industries: [], areas: [] });
const filters = ref({ search: '', user_id: '', status_id: '', type_id: '', category_id: '', industry_id: '', area_id: '' });

async function load() {
  loading.value = true;
  try {
    const params = { year: year.value, ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v)) };
    const res = await api.get('/v1/summary', { params });
    contacts.value = res.data.data;
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', user_id: '', status_id: '', type_id: '', category_id: '', industry_id: '', area_id: '' };
  load();
}

function toggleAll(e) {
  selectedIds.value = e.target.checked ? contacts.value.map(c => c.id) : [];
}

function exportSelected() {
  const ids = selectedIds.value.join(',');
  const url = `/api/v1/todos/export?ids=${ids}&date=${year.value}-01-01`;
  window.location.href = url;
}

onMounted(async () => {
  const res = await api.get('/v1/lookups');
  lookups.value = res.data;
  load();
});
</script>

<style scoped>
.page { padding: 24px 28px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #3b82f6);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }

.toolbar {
  background: var(--surface); border-radius: 10px; padding: 14px 18px; margin-bottom: 18px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07); display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group select, .filter-group input {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; color: #374151; outline: none; background: var(--surface);
}
.filter-group select:focus, .filter-group input:focus { border-color: #3b82f6; }
.year-select { font-size: 16px; font-weight: 700; border: none; background: none; cursor: pointer; height: 36px; }
.filter-actions { display: flex; gap: 8px; align-items: flex-end; }
.btn { height: 36px; padding: 0 14px; border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #3b82f6; color: white; }
.btn-reset { background: var(--app-bg); color: var(--text-2); }
.btn-export { background: #10b981; color: white; }

.loading-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
.table-wrap { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: auto; }
table { width: 100%; table-layout: auto; border-collapse: collapse; font-size: 11px; }
thead th {
  background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 8px;
  border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.month-col { width: 72px; text-align: center; }
tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
.company-cell { text-align: left; }
.company-link { color: #3b82f6; text-decoration: none; font-weight: 600; font-size: 12px; }
.company-link:hover { text-decoration: underline; }
.task-cell { text-align: center; }
.task-badge { display: inline-block; padding: 3px 4px; border-radius: 5px; font-size: 9px; line-height: 1.3; text-align: center; width: 90%; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-date { font-weight: 700; font-size: 10px; display: block; }
.badge-info { display: block; font-size: 8px; opacity: 0.8; }
.empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 900px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
  .filter-actions { width: 100%; }
}
</style>
