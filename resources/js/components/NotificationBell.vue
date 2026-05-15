<template>
  <div class="bell-wrap" ref="wrapRef">
    <button class="bell-btn" @click="togglePanel" :class="{ 'bell-active': open }">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>
      <span v-if="unreadCount > 0" class="bell-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>

    <div v-if="open" class="bell-panel">
      <div class="panel-hdr">
        <span class="panel-title">Reminders</span>
        <button v-if="allUnread.length > 0" class="btn-mark-all" @click="markAllRead">✓ All read</button>
      </div>

      <div v-if="loading" class="panel-placeholder">Loading…</div>
      <div v-else class="panel-body">
        <div v-if="overdue.length > 0">
          <div class="sec-head overdue-head">Overdue <span class="sec-cnt">{{ overdue.length }}</span></div>
          <div
            v-for="item in overdue"
            :key="item.source_type + item.id"
            class="r-item"
            :class="{ 'r-read': item.is_read }"
          >
            <router-link :to="item.link" class="r-body" @click="handleNav(item)">
              <span class="r-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
                {{ item.source_type === 'todo' ? 'TODO' : 'F/U' }}
              </span>
              <div class="r-text">
                <div class="r-title">{{ clip(item.title) }}</div>
                <div class="r-sub">{{ item.contact_name }} · <span class="date-red">{{ fmtDate(item.due_date) }}</span></div>
              </div>
            </router-link>
            <button v-if="!item.is_read" class="btn-dismiss" @click.stop="dismissOne(item)" title="Dismiss">×</button>
          </div>
        </div>

        <div v-if="today.length > 0">
          <div class="sec-head today-head">Due Today <span class="sec-cnt">{{ today.length }}</span></div>
          <div
            v-for="item in today"
            :key="item.source_type + item.id"
            class="r-item"
            :class="{ 'r-read': item.is_read }"
          >
            <router-link :to="item.link" class="r-body" @click="handleNav(item)">
              <span class="r-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
                {{ item.source_type === 'todo' ? 'TODO' : 'F/U' }}
              </span>
              <div class="r-text">
                <div class="r-title">{{ clip(item.title) }}</div>
                <div class="r-sub">{{ item.contact_name }}</div>
              </div>
            </router-link>
            <button v-if="!item.is_read" class="btn-dismiss" @click.stop="dismissOne(item)" title="Dismiss">×</button>
          </div>
        </div>

        <div v-if="upcoming.length > 0">
          <div class="sec-head upcoming-head">Upcoming <span class="sec-cnt">{{ upcoming.length }}</span></div>
          <div
            v-for="item in upcoming"
            :key="item.source_type + item.id"
            class="r-item"
            :class="{ 'r-read': item.is_read }"
          >
            <router-link :to="item.link" class="r-body" @click="handleNav(item)">
              <span class="r-tag" :class="item.source_type === 'todo' ? 'tag-todo' : 'tag-fu'">
                {{ item.source_type === 'todo' ? 'TODO' : 'F/U' }}
              </span>
              <div class="r-text">
                <div class="r-title">{{ clip(item.title) }}</div>
                <div class="r-sub">{{ item.contact_name }} · {{ fmtDate(item.due_date) }}</div>
              </div>
            </router-link>
            <button v-if="!item.is_read" class="btn-dismiss" @click.stop="dismissOne(item)" title="Dismiss">×</button>
          </div>
        </div>

        <div v-if="!overdue.length && !today.length && !upcoming.length" class="panel-empty">
          All caught up
        </div>
      </div>

      <div class="panel-footer">
        <router-link to="/reminders" @click="open = false">View all reminders →</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../api.js';

const wrapRef     = ref(null);
const open        = ref(false);
const loading     = ref(false);
const overdue     = ref([]);
const today       = ref([]);
const upcoming    = ref([]);
const unreadCount = ref(0);

let pollTimer = null;

const allUnread = computed(() =>
  [...overdue.value, ...today.value, ...upcoming.value].filter(i => !i.is_read)
);

async function load() {
  loading.value = true;
  try {
    const res         = await api.get('/v1/reminders');
    overdue.value     = res.data.overdue;
    today.value       = res.data.today;
    upcoming.value    = res.data.upcoming;
    unreadCount.value = res.data.unread_count;
  } catch (_) { /* ignore */ }
  finally { loading.value = false; }
}

function togglePanel() {
  open.value = !open.value;
  if (open.value) load();
}

function handleNav(item) {
  sendRead([item]);
  open.value = false;
}

function dismissOne(item) {
  sendRead([item]);
  item.is_read = true;
  unreadCount.value = Math.max(0, unreadCount.value - 1);
}

async function markAllRead() {
  const unread = allUnread.value.slice();
  await sendRead(unread);
  [...overdue.value, ...today.value, ...upcoming.value].forEach(i => { i.is_read = true; });
  unreadCount.value = 0;
}

async function sendRead(items) {
  if (!items.length) return;
  try {
    await api.post('/v1/reminders/read', {
      items: items.map(i => ({ type: i.source_type, id: i.id })),
    });
  } catch (_) { /* ignore */ }
}

function clip(str, len = 34) {
  if (!str) return '';
  return str.length > len ? str.slice(0, len) + '…' : str;
}

function fmtDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

function onOutsideClick(e) {
  if (open.value && wrapRef.value && !wrapRef.value.contains(e.target)) {
    open.value = false;
  }
}

onMounted(() => {
  load();
  pollTimer = setInterval(load, 60_000);
  document.addEventListener('click', onOutsideClick, true);
});

onUnmounted(() => {
  clearInterval(pollTimer);
  document.removeEventListener('click', onOutsideClick, true);
});
</script>

<style scoped>
.bell-wrap { position: relative; display: inline-flex; }

.bell-btn {
  position: relative; background: transparent; border: none; cursor: pointer;
  padding: 5px 7px; border-radius: 8px; line-height: 1;
  transition: background 0.15s; display: flex; align-items: center; color: #334155;
}
.bell-btn:hover, .bell-btn.bell-active { background: rgba(255,255,255,0.12); }

.bell-badge {
  position: absolute; top: 1px; right: 1px;
  background: #ef4444; color: white;
  font-size: 9px; font-weight: 800;
  min-width: 15px; height: 15px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  padding: 0 3px; line-height: 1; pointer-events: none;
}

.bell-panel {
  position: absolute; top: calc(100% + 8px); right: 0;
  width: 320px; max-height: 480px;
  background: white; border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.18);
  border: 1px solid #e2e8f0; z-index: 3000;
  display: flex; flex-direction: column; overflow: hidden;
}

.panel-hdr {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 14px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0;
}
.panel-title { font-size: 13px; font-weight: 700; color: #1e293b; }
.btn-mark-all {
  font-size: 11px; font-weight: 600; color: #3b82f6;
  background: none; border: none; cursor: pointer; padding: 2px 8px;
  border-radius: 4px; white-space: nowrap;
}
.btn-mark-all:hover { background: #eff6ff; }

.panel-body { flex: 1; overflow-y: auto; }
.panel-placeholder { padding: 24px; text-align: center; font-size: 13px; color: #94a3b8; }
.panel-empty       { padding: 32px 20px; text-align: center; font-size: 13px; color: #94a3b8; }

.sec-head {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 14px 5px;
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
}
.overdue-head  { color: #dc2626; background: #fff1f2; }
.today-head    { color: #d97706; background: #fffbeb; }
.upcoming-head { color: #0284c7; background: #f0f9ff; }
.sec-cnt {
  background: currentColor; color: white;
  border-radius: 10px; padding: 1px 5px; font-size: 10px; opacity: 0.85;
}

.r-item {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 12px 8px 14px; border-bottom: 1px solid #f8fafc;
  transition: background 0.1s;
}
.r-item:last-child { border-bottom: none; }
.r-item:hover { background: #f8fafc; }
.r-read { opacity: 0.4; }

.r-body {
  flex: 1; display: flex; align-items: center; gap: 8px;
  text-decoration: none; color: inherit; min-width: 0;
}
.r-tag {
  font-size: 9px; font-weight: 700; padding: 2px 5px;
  border-radius: 4px; flex-shrink: 0; white-space: nowrap;
}
.tag-todo { background: #fce7f3; color: #9d174d; }
.tag-fu   { background: #e0f2fe; color: #0369a1; }

.r-text { flex: 1; min-width: 0; }
.r-title { font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.r-sub   { font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.date-red { color: #dc2626; font-weight: 600; }

.btn-dismiss {
  flex-shrink: 0; width: 20px; height: 20px; background: #f1f5f9;
  border: none; border-radius: 4px; color: #94a3b8;
  font-size: 15px; cursor: pointer; line-height: 1;
  display: flex; align-items: center; justify-content: center; padding: 0;
}
.btn-dismiss:hover { background: #fee2e2; color: #dc2626; }

.panel-footer {
  padding: 10px 14px; border-top: 1px solid #f1f5f9; flex-shrink: 0;
}
.panel-footer a { font-size: 12px; color: #3b82f6; text-decoration: none; font-weight: 600; }
.panel-footer a:hover { text-decoration: underline; }

@media (max-width: 400px) {
  .bell-panel { width: calc(100vw - 20px); right: -10px; }
}
</style>
