<template>
  <div class="tw-widget">
    <div class="tw-head">
      <div class="tw-title-wrap">
        <span class="tw-title">Tasks To Do</span>
        <span class="tw-sub">Today · {{ tasks.length }} pending</span>
      </div>
      <router-link to="/todos" class="tw-view-all">View All</router-link>
    </div>
    <div v-if="loading" class="tw-empty">Loading…</div>
    <div v-else-if="!tasks.length" class="tw-empty">No pending tasks</div>
    <ul v-else class="tw-list">
      <li v-for="t in tasks" :key="t.id" class="tw-item">
        <div class="tw-date" :class="{ 'tw-date--overdue': isOverdue(t.todo_date) }">
          {{ formatDate(t.todo_date) }}
          <span v-if="isOverdue(t.todo_date)" class="tw-overdue-dot" title="Overdue"></span>
        </div>
        <div class="tw-body">
          <router-link :to="taskLink(t)" class="tw-name">
            {{ t.contact_name ?? '—' }}
          </router-link>
          <div v-if="t.task" class="tw-tag">{{ t.task }}</div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../api.js';

const tasks = ref([]);
const loading = ref(true);

function parseDate(dateStr) {
  if (!dateStr) return null;
  // backend returns dd-mm-yyyy
  const parts = dateStr.split('-');
  if (parts.length === 3 && parts[0].length === 2) {
    return new Date(+parts[2], +parts[1] - 1, +parts[0]);
  }
  return new Date(dateStr);
}

function formatDate(dateStr) {
  const d = parseDate(dateStr);
  if (!d || isNaN(d)) return '—';
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function isOverdue(dateStr) {
  const d = parseDate(dateStr);
  if (!d || isNaN(d)) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return d < today;
}

function taskLink(t) {
  return t.contact_id ? `/contacts/${t.contact_id}` : '/todos';
}

onMounted(async () => {
  const now = new Date();
  const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
  try {
    const { data } = await api.get('/v1/todos', {
      params: { per_page: 8, view: 'Day', date: todayStr, completion_status: 'pending' },
    });
    tasks.value = data.data ?? data ?? [];
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.tw-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.tw-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.tw-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.tw-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.tw-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.tw-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
}
.tw-view-all:hover { color: var(--primary-hover); }

.tw-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}

.tw-list {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.tw-list::-webkit-scrollbar { width: 6px; }
.tw-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.tw-item {
  display: grid;
  grid-template-columns: 100px 1fr;
  align-items: center;
  gap: 14px;
  padding: 10px 4px;
  border-bottom: 1px solid var(--border-soft);
}
.tw-item:last-child { border-bottom: none; }

.tw-date {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-2);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.tw-date--overdue { color: var(--danger); }
.tw-overdue-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--danger);
  box-shadow: 0 0 0 3px var(--danger-soft);
}

.tw-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.tw-name {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-1);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.15s;
}
.tw-name:hover { color: var(--primary); }
.tw-tag {
  align-self: flex-start;
  font-size: 11px;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary-text);
  padding: 2px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
</style>
