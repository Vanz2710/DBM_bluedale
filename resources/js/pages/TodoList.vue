<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>To Do List</h1>
        <p>Track tasks and follow-ups by date</p>
      </div>
      <router-link to="/todos/add" class="btn-add">+ Add Reminder</router-link>
    </div>

    <div v-if="selectedIds.length > 0" class="selection-bar">
      <button class="btn-export-sel" @click="exportSelected">Export {{ selectedIds.length }} selected</button>
      <span>{{ selectedIds.length }} record(s) selected</span>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>View</label>
        <select v-model="view" @change="load">
          <option>Day</option>
          <option>Month</option>
          <option>Year</option>
        </select>
      </div>
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
        <label>Per Page</label>
        <input type="number" v-model.number="perPage" @change="load" style="width:70px;">
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        {{ periodLabel }} — {{ meta.total ?? todos.length }} record(s)
      </div>
      <div v-if="loading" class="loading-msg">Loading…</div>
      <table v-else>
        <thead>
          <tr>
            <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
            <th>No</th>
            <th>To Do Date</th>
            <th>Date Created</th>
            <th>Status</th>
            <th>Type</th>
            <th>Company</th>
            <th>User</th>
            <th>Task</th>
            <th>Remark</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="todos.length === 0">
            <td colspan="11" class="empty-state">No tasks found for this period.</td>
          </tr>
          <tr v-for="(t, idx) in todos" :key="t.id">
            <td><input type="checkbox" :value="t.id" v-model="selectedIds"></td>
            <td>{{ meta.from ? meta.from + idx : idx + 1 }}</td>
            <td>{{ t.todo_date }}</td>
            <td>{{ t.date_created ?? '—' }}</td>
            <td>{{ t.status ?? '—' }}</td>
            <td>{{ t.type ?? '—' }}</td>
            <td>
              <router-link :to="`/contacts/${t.contact_id}`" class="company-link">{{ t.contact_name }}</router-link>
            </td>
            <td>{{ t.user ?? '—' }}</td>
            <td>{{ t.task ?? '—' }}</td>
            <td>{{ t.todo_remark }}</td>
            <td>
              <router-link :to="`/todos/${t.id}/edit`" class="icon-btn btn-edit" title="Edit">✏️</router-link>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pagination">
        <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">← Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';

const view    = ref('Day');
const date    = ref(new Date().toISOString().slice(0, 10));
const search  = ref('');
const userId  = ref('');
const perPage = ref(100);
const page    = ref(1);
const todos   = ref([]);
const meta    = ref({});
const loading = ref(false);
const users   = ref([]);
const selectedIds = ref([]);
const selectAllRef = ref(null);

const periodLabel = computed(() => {
  const d = new Date(date.value + 'T00:00:00');
  if (view.value === 'Day') return d.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  if (view.value === 'Month') return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
  return d.getFullYear().toString();
});

async function load() {
  loading.value = true;
  selectedIds.value = [];
  try {
    const params = { view: view.value, date: date.value, per_page: perPage.value, page: page.value };
    if (search.value) params.search = search.value;
    if (userId.value) params.user_id = userId.value;
    const res = await api.get('/v1/todos', { params });
    todos.value = res.data.data;
    meta.value  = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

function changePage(p) {
  page.value = p;
  load();
}

function toggleAll(e) {
  selectedIds.value = e.target.checked ? todos.value.map(t => t.id) : [];
}

function exportSelected() {
  const ids = selectedIds.value.join(',');
  const token = localStorage.getItem('crm_token');
  window.location.href = `/api/v1/todos/export?ids=${ids}&_token=${token}`;
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
  background: linear-gradient(135deg, #1a2f4a, #f97316);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }
.btn-add {
  background: #f97316; color: white; border-radius: 8px;
  padding: 9px 18px; text-decoration: none; font-size: 13px; font-weight: 700;
  border: 2px solid rgba(255,255,255,0.3);
}
.selection-bar {
  background: #1e293b; color: white; border-radius: 8px;
  padding: 10px 18px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 14px; font-size: 13px;
}
.btn-export-sel {
  background: #10b981; color: white; border: none; border-radius: 6px;
  padding: 6px 14px; cursor: pointer; font-size: 13px; font-weight: 600;
}
.toolbar {
  background: white; border-radius: 10px; padding: 14px 18px;
  margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #94a3b8; }
.filter-group select, .filter-group input {
  height: 36px; padding: 0 10px; border: 1.5px solid #e2e8f0;
  border-radius: 7px; font-size: 13px; outline: none; background: white;
}
.filter-group select:focus, .filter-group input:focus { border-color: #f97316; }
.btn { height: 36px; padding: 0 14px; border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #3b82f6; color: white; }
.table-wrap { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-header-bar {
  background: #f8fafc; padding: 12px 16px;
  font-size: 13px; font-weight: 700; color: #1e293b;
  border-bottom: 2px solid #e2e8f0;
}
.loading-msg { text-align: center; padding: 48px; color: #94a3b8; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th {
  background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px;
  border-bottom: 2px solid #e2e8f0; text-align: left; white-space: nowrap;
}
tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
.company-link { color: #1e293b; font-weight: 600; text-decoration: none; }
.company-link:hover { color: #3b82f6; }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px; text-decoration: none; font-size: 13px;
}
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #ca8a04; }
.empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }
.pagination {
  display: flex; align-items: center; justify-content: center; gap: 14px;
  padding: 14px; border-top: 1px solid #f1f5f9; font-size: 13px;
}
.pagination button {
  padding: 6px 14px; border: 1.5px solid #e2e8f0; border-radius: 7px;
  background: white; cursor: pointer; font-size: 13px;
}
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
