<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>To Do List</h1>
        <p>List of tasks for each contact</p>
      </div>
      <router-link to="/todos/add" class="btn-add">+ Add Reminder</router-link>
    </div>

    <div v-if="selectedIds.length > 0" class="selection-bar">
      <button class="btn-export-sel" @click="exportSelected">Export {{ selectedIds.length }} selected</button>
      <span>{{ selectedIds.length }} record(s) selected</span>
    </div>

    <div class="toolbar">
      <div class="filter-date-range">
        <span class="date-range-label">Date Range</span>
        <div class="date-range-inputs">
          <div class="date-input-wrap">
            <span class="date-input-prefix">From</span>
            <input type="date" v-model="fromDate" @change="onDateRangeChange" class="date-range-input">
          </div>
          <span class="date-range-sep">→</span>
          <div class="date-input-wrap">
            <span class="date-input-prefix">To</span>
            <input type="date" v-model="toDate" @change="onDateRangeChange" class="date-range-input">
          </div>
        </div>
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
        <label>Status</label>
        <select v-model="statusFilter" @change="load">
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Per Page</label>
        <input type="number" v-model.number="perPage" @change="load" style="width:70px;">
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
      <button v-if="hasFilters" class="btn btn-clear" @click="clearFilters">Clear</button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        {{ periodLabel }} — <strong>{{ meta.total ?? todos.length }}</strong> record(s)
      </div>
      <LoadingSpinner v-if="loading" />
      <table v-else>
        <thead>
          <tr>
            <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
            <th>No</th>
            <th>To Do Date</th>
            <th>Date Created</th>
            <th>Contact Status</th>
            <th>Type</th>
            <th>Company</th>
            <th>User</th>
            <th>Task</th>
            <th>Remark</th>
            <th>Follow-Ups</th>
            <th>Last Touch</th>
            <th>Done</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="todos.length === 0">
            <td colspan="14" class="empty-state">No tasks found for this period.</td>
          </tr>
          <tr v-for="(t, idx) in todos" :key="t.id" :class="{ 'row-done': t.completion_status === 'completed' }">
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
              <router-link v-if="t.followups_count > 0"
                           :to="`/followups?todo_id=${t.id}`"
                           class="followup-count" title="View follow-ups for this to-do">
                {{ t.followups_count }}
              </router-link>
              <span v-else class="muted">—</span>
            </td>
            <td>
              <span v-if="t.last_followup_date">{{ t.last_followup_date }}</span>
              <span v-else class="muted">—</span>
            </td>
            <td>
              <button v-if="t.completion_status !== 'completed'"
                      class="icon-btn btn-done" title="Mark complete"
                      @click="markDone(t)">✓</button>
              <button v-else
                      class="icon-btn btn-undo" title="Mark pending"
                      @click="markPending(t)">↩</button>
            </td>
            <td class="actions-cell">
              <router-link :to="`/followups/add?todo_id=${t.id}`" class="icon-btn btn-followup" title="Log a follow-up for this to-do">📞</router-link>
              <router-link :to="`/todos/${t.id}/edit`" class="icon-btn btn-edit" title="Edit">✏️</router-link>
              <button class="icon-btn btn-delete" title="Delete task" @click="deleteTodo(t)">🗑️</button>
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
import LoadingSpinner from '../components/LoadingSpinner.vue';

const fromDate     = ref('');
const toDate       = ref('');
const search       = ref('');
const userId       = ref('');
const statusFilter = ref('pending');
const perPage      = ref(100);
const page         = ref(1);
const todos        = ref([]);
const meta         = ref({});
const loading      = ref(false);
const users        = ref([]);
const selectedIds  = ref([]);
const selectAllRef = ref(null);

const hasFilters = computed(() =>
  fromDate.value || toDate.value || search.value || userId.value || statusFilter.value
);

const periodLabel = computed(() => {
  if (fromDate.value && toDate.value) {
    const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    return `${fmt(fromDate.value)} → ${fmt(toDate.value)}`;
  }
  if (fromDate.value) return `From ${new Date(fromDate.value + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}`;
  if (toDate.value)   return `Up to ${new Date(toDate.value + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}`;
  return 'All Tasks';
});

function onDateRangeChange() {
  page.value = 1;
  load();
}

async function load() {
  loading.value = true;
  selectedIds.value = [];
  try {
    const params = { view: 'All', per_page: perPage.value, page: page.value };
    if (fromDate.value)     params.from_date = fromDate.value;
    if (toDate.value)       params.to_date   = toDate.value;
    if (search.value)       params.search    = search.value;
    if (userId.value)       params.user_id   = userId.value;
    if (statusFilter.value) params.completion_status = statusFilter.value;
    const res = await api.get('/v1/todos', { params });
    todos.value = res.data.data;
    meta.value  = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  fromDate.value = '';
  toDate.value   = '';
  search.value   = '';
  userId.value   = '';
  statusFilter.value = '';
  page.value     = 1;
  load();
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

async function markDone(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'completed' });
  todo.completion_status = 'completed';
}

async function markPending(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'pending' });
  todo.completion_status = 'pending';
}

async function deleteTodo(todo) {
  if (!confirm(`Delete task for "${todo.contact_name}"?\nThis also removes all linked follow-ups.`)) return;
  await api.delete(`/v1/todos/${todo.id}`);
  todos.value = todos.value.filter(t => t.id !== todo.id);
  if (meta.value.total) meta.value.total--;
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
.filter-group select:focus, .filter-group input:focus { border-color: #f97316; }
.btn { height: 36px; padding: 0 14px; border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #3b82f6; color: white; }
.btn-clear { background: var(--app-bg); color: var(--text-2); border: 1.5px solid var(--border); }
.btn-clear:hover { background: var(--border); }

/* Date range filter */
.filter-date-range {
  display: flex; flex-direction: column; gap: 4px;
}
.date-range-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.7px; color: var(--text-3);
}
.date-range-inputs { display: flex; align-items: center; gap: 6px; }
.date-input-wrap {
  display: flex; align-items: center; border: 1.5px solid var(--border);
  border-radius: 7px; overflow: hidden; background: var(--surface); height: 36px;
}
.date-input-wrap:focus-within { border-color: #f97316; }
.date-input-prefix {
  padding: 0 8px; font-size: 11px; font-weight: 600; color: var(--text-3);
  background: var(--app-bg); border-right: 1.5px solid var(--border);
  height: 100%; display: flex; align-items: center; white-space: nowrap;
}
.date-range-input {
  border: none !important; border-radius: 0 !important; height: 100%;
  padding: 0 8px; font-size: 13px; background: transparent; outline: none; width: 130px;
}
.date-range-sep { color: var(--text-3); font-weight: 700; font-size: 14px; padding: 0 2px; }
.table-wrap { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
.table-header-bar {
  background: var(--app-bg); padding: 12px 16px;
  font-size: 13px; font-weight: 700; color: var(--text-1);
  border-bottom: 2px solid var(--border);
}
.loading-msg { text-align: center; padding: 48px; color: var(--text-3); }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap;
}
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: #3b82f6; }
.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px; text-decoration: none; font-size: 13px;
}
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #ca8a04; }
.btn-delete { background: #fee2e2; color: #991b1b; border: none; }
.btn-delete:hover { background: #ef4444; color: #fff; }
.btn-followup { background: #fce7f3; color: #9d174d; }
.btn-followup:hover { background: #f9a8d4; color: white; }
.actions-cell { display: flex; gap: 4px; }
.followup-count {
  display: inline-block; min-width: 26px; padding: 2px 8px; border-radius: 12px;
  background: #fce7f3; color: #9d174d; font-size: 11px; font-weight: 700;
  text-align: center; text-decoration: none;
}
.followup-count:hover { background: #f9a8d4; color: white; }
.muted { color: var(--text-3); }
.btn-done { background: #f0fdf4; color: #166534; font-weight: 700; border: none; cursor: pointer; }
.btn-done:hover { background: #10b981; color: white; }
.btn-undo { background: var(--app-bg); color: var(--text-2); font-weight: 700; border: none; cursor: pointer; }
.btn-undo:hover { background: #94a3b8; color: white; }
.row-done td { opacity: 0.55; text-decoration: line-through; text-decoration-color: #94a3b8; }
.row-done .icon-btn { text-decoration: none; opacity: 1; }
.empty-state { text-align: center; padding: 40px; color: var(--text-3); font-size: 14px; }
.pagination {
  display: flex; align-items: center; justify-content: center; gap: 14px;
  padding: 14px; border-top: 1px solid var(--border); font-size: 13px;
}
.pagination button {
  padding: 6px 14px; border: 1.5px solid var(--border); border-radius: 7px;
  background: var(--surface); cursor: pointer; font-size: 13px;
}
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 700px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}
</style>
