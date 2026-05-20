<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Daily Company List</h1>
        <p>Companies registered on a specific date</p>
      </div>
      <router-link to="/contacts/add" class="btn-add">+ Add Company</router-link>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>Date</label>
        <input type="date" v-model="date" @change="load">
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="search" @keyup.enter="load" placeholder="Company name…">
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="userId" @change="load">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Sort</label>
        <select v-model="sort" @change="load">
          <option value="desc">Newest First</option>
          <option value="asc">Oldest First</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
    </div>

    <LoadingSpinner v-if="loading" />
    <div v-else class="table-wrap">
      <div class="table-header-bar">
        <span>{{ dateLabel }} — {{ meta.total ?? 0 }} record(s)</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Date Added</th>
            <th>CS (User)</th>
            <th>Status</th>
            <th>Type</th>
            <th>Industry</th>
            <th>Area</th>
            <th>Company Name</th>
            <th>Category</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="contacts.length === 0">
            <td colspan="10" class="empty-state">No companies registered on this date.</td>
          </tr>
          <tr v-for="(c, idx) in contacts" :key="c.id">
            <td>{{ idx + 1 }}</td>
            <td>{{ fmtDate(c.created_at) }}</td>
            <td><strong>{{ c.user?.name ?? '—' }}</strong></td>
            <td>{{ c.status?.name ?? '—' }}</td>
            <td>{{ c.type?.name ?? '—' }}</td>
            <td>{{ c.industry?.name ?? '—' }}</td>
            <td>{{ c.area?.name ?? '—' }}</td>
            <td>
              <router-link :to="`/contacts/${c.id}`" class="company-link">{{ c.name }}</router-link>
            </td>
            <td>{{ c.category?.name ?? '—' }}</td>
            <td class="action-btns">
              <router-link :to="`/contacts/${c.id}/task/add`" class="icon-btn btn-todo" title="Add Task">📋</router-link>
              <router-link :to="`/contacts/${c.id}/edit`" class="icon-btn btn-edit" title="Edit">✏️</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const date    = ref(new Date().toISOString().slice(0, 10));
const search  = ref('');
const userId  = ref('');
const sort    = ref('desc');
const contacts = ref([]);
const meta    = ref({});
const loading = ref(false);
const users   = ref([]);

const dateLabel = computed(() => {
  const d = new Date(date.value + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function load() {
  loading.value = true;
  try {
    const params = { date: date.value, sort: sort.value };
    if (search.value) params.search = search.value;
    if (userId.value) params.user_id = userId.value;
    const res = await api.get('/v1/contacts/daily', { params });
    contacts.value = res.data.data;
    meta.value = res.data.meta ?? { total: res.data.data?.length ?? 0 };
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  const lu = await api.get('/v1/lookups');
  users.value = lu.data.users;
  load();
});
</script>

<style scoped>
.page { padding: 24px 28px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #3b82f6);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }
.btn-add {
  background: #22c55e; color: white; border-radius: 8px;
  padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 700;
}
.toolbar {
  background: var(--surface); border-radius: 10px; padding: 14px 18px;
  margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group select, .filter-group input {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; outline: none; background: var(--surface);
}
.btn { height: 36px; padding: 0 14px; border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #3b82f6; color: white; }
.loading-msg { text-align: center; padding: 48px; color: var(--text-3); }
.table-wrap { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-header-bar {
  background: var(--app-bg); padding: 12px 16px;
  font-size: 13px; font-weight: 700; color: var(--text-1);
  border-bottom: 2px solid var(--border);
}
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap;
}
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: #3b82f6; }
.action-btns { display: flex; gap: 6px; }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border-radius: 7px; text-decoration: none; font-size: 15px;
}
.btn-todo { background: #eff6ff; }
.btn-todo:hover { background: #3b82f6; }
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #ca8a04; }
.empty-state { text-align: center; padding: 48px; color: var(--text-3); font-size: 14px; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 750px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
</style>
