<template>
  <div class="page">
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Reminders</h1>
        <p class="page-subtitle">Overdue, today's and upcoming to-dos &amp; follow-ups</p>
      </div>
      <div class="page-head-actions">
        <button v-if="allUnread.length > 0" class="btn-mark-all" @click="markAllRead" :disabled="marking">
          <svg v-if="!marking" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><polyline points="20 6 9 17 4 12"/></svg>{{ marking ? 'Marking…' : 'Mark all read' }}
        </button>
        <button class="btn-refresh" @click="load" :disabled="loading" title="Refresh">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.63"/></svg>
        </button>
      </div>
    </div>

    <!-- Admin user filter -->
    <div v-if="isAdmin" class="toolbar">
      <div class="filter-group">
        <label>User</label>
        <select v-model="userId" @change="load">
          <option value="">My Reminders</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />
    <template v-else>
      <!-- Overdue -->
      <div class="section-card overdue-card" v-if="overdue.length > 0">
        <div class="section-hdr">
          <span class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
          <span class="sec-title">Overdue</span>
          <span class="sec-badge overdue-badge">{{ overdue.length }}</span>
        </div>
        <div class="items-list">
          <div
            v-for="item in overdue"
            :key="item.source_type + item.id"
            class="item-row"
            :class="{ 'row-read': item.is_read }"
          >
            <span class="type-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
              {{ item.source_type === 'todo' ? 'To Do' : 'Follow-Up' }}
            </span>
            <div class="item-main">
              <div class="item-title">{{ item.title }}</div>
              <router-link v-if="item.contact_id" :to="`/contacts/${item.contact_id}`" class="item-company">
                {{ item.contact_name }}
              </router-link>
              <span v-else class="item-company muted">{{ item.contact_name }}</span>
            </div>
            <div class="item-date overdue-date">{{ fmtDate(item.due_date) }}</div>
            <div class="item-actions">
              <router-link :to="item.link" class="btn-go" title="Open">Open</router-link>
              <button
                v-if="!item.is_read"
                class="btn-dismiss"
                @click="dismissOne(item)"
                title="Dismiss"
              >Dismiss</button>
              <span v-else class="read-label">Read</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Due Today -->
      <div class="section-card today-card" v-if="today.length > 0">
        <div class="section-hdr">
          <span class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          <span class="sec-title">Due Today</span>
          <span class="sec-badge today-badge">{{ today.length }}</span>
        </div>
        <div class="items-list">
          <div
            v-for="item in today"
            :key="item.source_type + item.id"
            class="item-row"
            :class="{ 'row-read': item.is_read }"
          >
            <span class="type-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
              {{ item.source_type === 'todo' ? 'To Do' : 'Follow-Up' }}
            </span>
            <div class="item-main">
              <div class="item-title">{{ item.title }}</div>
              <router-link v-if="item.contact_id" :to="`/contacts/${item.contact_id}`" class="item-company">
                {{ item.contact_name }}
              </router-link>
              <span v-else class="item-company muted">{{ item.contact_name }}</span>
            </div>
            <div class="item-date today-date">Today</div>
            <div class="item-actions">
              <router-link :to="item.link" class="btn-go" title="Open">Open</router-link>
              <button
                v-if="!item.is_read"
                class="btn-dismiss"
                @click="dismissOne(item)"
                title="Dismiss"
              >Dismiss</button>
              <span v-else class="read-label">Read</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Upcoming -->
      <div class="section-card upcoming-card" v-if="upcoming.length > 0">
        <div class="section-hdr">
          <span class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          <span class="sec-title">Upcoming (7 days)</span>
          <span class="sec-badge upcoming-badge">{{ upcoming.length }}</span>
        </div>
        <div class="items-list">
          <div
            v-for="item in upcoming"
            :key="item.source_type + item.id"
            class="item-row"
            :class="{ 'row-read': item.is_read }"
          >
            <span class="type-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
              {{ item.source_type === 'todo' ? 'To Do' : 'Follow-Up' }}
            </span>
            <div class="item-main">
              <div class="item-title">{{ item.title }}</div>
              <router-link v-if="item.contact_id" :to="`/contacts/${item.contact_id}`" class="item-company">
                {{ item.contact_name }}
              </router-link>
              <span v-else class="item-company muted">{{ item.contact_name }}</span>
            </div>
            <div class="item-date upcoming-date">{{ fmtDate(item.due_date) }}</div>
            <div class="item-actions">
              <router-link :to="item.link" class="btn-go" title="Open">Open</router-link>
              <button
                v-if="!item.is_read"
                class="btn-dismiss"
                @click="dismissOne(item)"
                title="Dismiss"
              >Dismiss</button>
              <span v-else class="read-label">Read</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!overdue.length && !today.length && !upcoming.length" class="all-clear">
        <div class="all-clear-icon"><svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="all-clear-title">All caught up!</div>
        <div class="all-clear-sub">No overdue or upcoming reminders in the next 7 days.</div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading = ref(false);
const marking = ref(false);
const overdue  = ref([]);
const today    = ref([]);
const upcoming = ref([]);
const userId   = ref('');
const users    = ref([]);

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

const allUnread = computed(() =>
  [...overdue.value, ...today.value, ...upcoming.value].filter(i => !i.is_read)
);

async function load() {
  loading.value = true;
  try {
    const params = {};
    if (userId.value) params.user_id = userId.value;
    const res      = await api.get('/v1/reminders', { params });
    overdue.value  = res.data.overdue;
    today.value    = res.data.today;
    upcoming.value = res.data.upcoming;
  } catch (_) { /* ignore */ }
  finally { loading.value = false; }
}

async function sendRead(items) {
  if (!items.length) return;
  await api.post('/v1/reminders/read', {
    items: items.map(i => ({ type: i.source_type, id: i.id })),
  });
}

function dismissOne(item) {
  sendRead([item]);
  item.is_read = true;
}

async function markAllRead() {
  marking.value = true;
  const unread = allUnread.value.slice();
  try {
    await sendRead(unread);
    [...overdue.value, ...today.value, ...upcoming.value].forEach(i => { i.is_read = true; });
  } finally {
    marking.value = false;
  }
}

function fmtDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short' });
}

onMounted(async () => {
  if (isAdmin.value) {
    try {
      const res = await api.get('/v1/rbac/users');
      users.value = res.data.data ?? [];
    } catch (_) { /* ignore */ }
  }
  load();
});
</script>

<style scoped>
.page { padding: 28px 28px 48px; max-width: 1100px; margin: 0 auto; }

/* Page head */
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.btn-mark-all {
  background: var(--primary); color: var(--primary-on);
  border: none; border-radius: 999px; padding: 9px 18px;
  font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
  box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55);
  transition: background 0.15s;
}
.btn-mark-all:hover:not(:disabled) { background: var(--primary-hover); }
.btn-mark-all:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-refresh {
  background: var(--surface); color: var(--text-2);
  border: 1px solid var(--border); border-radius: 999px;
  width: 38px; height: 38px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: border-color 0.15s, color 0.15s;
}
.btn-refresh:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius-lg); padding: 12px 16px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-soft);
  display: flex; gap: 10px; align-items: flex-end;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.filter-group select {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }

/* Section cards */
.section-card {
  background: var(--surface); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft);
  margin-bottom: 16px; overflow: hidden;
}
.overdue-card  { border-left: 4px solid #dc2626; }
.today-card    { border-left: 4px solid #f59e0b; }
.upcoming-card { border-left: 4px solid var(--primary); }

.section-hdr {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; border-bottom: 1px solid var(--border-soft);
}
.sec-icon  { display: inline-flex; align-items: center; line-height: 1; }
.sec-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.sec-badge {
  font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px;
}
.overdue-badge  { background: #fee2e2; color: #dc2626; }
.today-badge    { background: #fef3c7; color: #d97706; }
.upcoming-badge { background: var(--primary-soft); color: var(--primary-text); }

.item-row {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px; border-bottom: 1px solid var(--border-soft);
  transition: background 0.12s;
}
.item-row:last-child { border-bottom: none; }
.item-row:hover { background: var(--surface-2); }
.row-read { opacity: 0.45; }

.type-tag {
  font-size: 10.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px;
  flex-shrink: 0; white-space: nowrap;
}
.tag-todo { background: #fce7f3; color: #9d174d; }
.tag-fu   { background: #e0f2fe; color: #0369a1; }

.item-main { flex: 1; min-width: 0; }
.item-title { font-size: 13px; font-weight: 600; color: var(--text-1); margin-bottom: 2px; }
.item-company { font-size: 12px; color: var(--text-2); text-decoration: none; }
.item-company:not(.muted):hover { color: var(--primary); text-decoration: underline; }
.muted { color: var(--text-3); }

.item-date { font-size: 12.5px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
.overdue-date  { color: #dc2626; }
.today-date    { color: #d97706; }
.upcoming-date { color: var(--primary-text); }

.item-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.btn-go {
  background: var(--primary-soft); color: var(--primary-text);
  border: 1px solid rgba(29,78,216,0.15);
  border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600;
  text-decoration: none; white-space: nowrap;
  transition: background 0.12s;
}
.btn-go:hover { background: var(--primary); color: var(--primary-on); border-color: var(--primary); }
.btn-dismiss {
  background: #fee2e2; color: #dc2626; border: 1px solid #fecdd3;
  border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600;
  cursor: pointer; white-space: nowrap;
  transition: background 0.12s;
}
.btn-dismiss:hover { background: #fca5a5; }
.read-label { font-size: 11px; color: var(--text-3); font-weight: 500; }

.all-clear {
  background: var(--surface); border-radius: var(--radius-lg); padding: 64px 20px;
  text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft);
}
.all-clear-icon  { display: flex; justify-content: center; margin-bottom: 14px; }
.all-clear-title { font-size: 18px; font-weight: 800; color: var(--text-1); margin-bottom: 6px; letter-spacing: -0.3px; }
.all-clear-sub   { font-size: 13.5px; color: var(--text-3); }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
  .item-row { flex-wrap: wrap; gap: 8px; }
  .item-date { order: -1; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .item-actions { width: 100%; justify-content: flex-end; }
}
</style>
