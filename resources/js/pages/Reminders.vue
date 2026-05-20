<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Reminders</h1>
        <p>Overdue, today's and upcoming to-dos &amp; follow-ups</p>
      </div>
      <div class="banner-actions">
        <button v-if="allUnread.length > 0" class="btn-mark-all" @click="markAllRead" :disabled="marking">
          {{ marking ? 'Marking…' : '✓ Mark all read' }}
        </button>
        <button class="btn-refresh" @click="load" :disabled="loading" title="Refresh">↺</button>
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
          <span class="sec-icon">⚠</span>
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
              <router-link :to="item.link" class="btn-go" title="Open">→ Open</router-link>
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
          <span class="sec-icon">📅</span>
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
              <router-link :to="item.link" class="btn-go" title="Open">→ Open</router-link>
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
          <span class="sec-icon">🗓</span>
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
              <router-link :to="item.link" class="btn-go" title="Open">→ Open</router-link>
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
        <div class="all-clear-icon">🎉</div>
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
.page { padding: 24px 28px; }
.page-banner {
  background: linear-gradient(135deg, #1e3a5f, #3b82f6);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.banner-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
.btn-mark-all {
  background: rgba(255,255,255,0.2); color: white; border: 1.5px solid rgba(255,255,255,0.3);
  border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 600;
  cursor: pointer; white-space: nowrap;
}
.btn-mark-all:hover:not(:disabled) { background: rgba(255,255,255,0.3); }
.btn-mark-all:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-refresh {
  background: rgba(255,255,255,0.15); color: white; border: 1.5px solid rgba(255,255,255,0.25);
  border-radius: 8px; width: 36px; height: 36px; font-size: 18px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.btn-refresh:hover:not(:disabled) { background: rgba(255,255,255,0.25); }

.toolbar {
  background: var(--surface); border-radius: 10px; padding: 12px 18px;
  margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group select {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; outline: none; background: var(--surface);
}
.filter-group select:focus { border-color: #3b82f6; }

.section-card {
  background: var(--surface); border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  margin-bottom: 16px; overflow: hidden;
}
.overdue-card  { border-left: 4px solid #dc2626; }
.today-card    { border-left: 4px solid #f59e0b; }
.upcoming-card { border-left: 4px solid #3b82f6; }

.section-hdr {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 20px; border-bottom: 1px solid var(--border);
}
.sec-icon  { font-size: 16px; }
.sec-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.sec-badge {
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px;
}
.overdue-badge  { background: #fee2e2; color: #dc2626; }
.today-badge    { background: #fef3c7; color: #d97706; }
.upcoming-badge { background: #dbeafe; color: #1d4ed8; }

.items-list { }
.item-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 20px; border-bottom: 1px solid var(--border);
  transition: background 0.1s;
}
.item-row:last-child { border-bottom: none; }
.item-row:hover { background: var(--app-bg); }
.row-read { opacity: 0.45; }

.type-tag {
  font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 5px;
  flex-shrink: 0; white-space: nowrap;
}
.tag-todo { background: #fce7f3; color: #9d174d; }
.tag-fu   { background: #e0f2fe; color: #0369a1; }

.item-main { flex: 1; min-width: 0; }
.item-title   { font-size: 13px; font-weight: 600; color: var(--text-1); margin-bottom: 2px; }
.item-company { font-size: 12px; color: var(--text-2); text-decoration: none; }
.item-company:not(.muted):hover { color: #3b82f6; text-decoration: underline; }
.muted { color: var(--text-3); }

.item-date { font-size: 12px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
.overdue-date  { color: #dc2626; }
.today-date    { color: #d97706; }
.upcoming-date { color: #0284c7; }

.item-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.btn-go {
  background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd;
  border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600;
  text-decoration: none; white-space: nowrap;
}
.btn-go:hover { background: #e0f2fe; }
.btn-dismiss {
  background: #fff1f2; color: #dc2626; border: 1px solid #fecdd3;
  border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600;
  cursor: pointer; white-space: nowrap;
}
.btn-dismiss:hover { background: #fee2e2; }
.read-label { font-size: 11px; color: var(--text-3); font-weight: 500; }

.all-clear {
  background: var(--surface); border-radius: 10px; padding: 60px 20px;
  text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}
.all-clear-icon  { font-size: 40px; margin-bottom: 12px; }
.all-clear-title { font-size: 18px; font-weight: 700; color: var(--text-1); margin-bottom: 6px; }
.all-clear-sub   { font-size: 13px; color: var(--text-2); }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .item-row { flex-wrap: wrap; gap: 8px; }
  .item-date { order: -1; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .item-actions { width: 100%; justify-content: flex-end; }
}
</style>
