<template>
  <div class="aw-widget">
    <div class="aw-head">
      <div class="aw-title-wrap">
        <span class="aw-title">Announcements</span>
        <span class="aw-sub" v-if="unreadCount > 0">{{ unreadCount }} unread</span>
        <span class="aw-sub" v-else>Notice board</span>
      </div>
      <router-link to="/notice-board" class="aw-view-all">View All</router-link>
    </div>

    <div v-if="loading" class="aw-empty">Loading…</div>
    <div v-else-if="!items.length" class="aw-empty">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
      <span>No active announcements</span>
    </div>

    <ul v-else class="aw-list">
      <li v-for="item in items" :key="item.id" class="aw-item" :class="{ 'aw-unread': !item.is_read, 'aw-urgent': item.urgency === 'urgent' }">
        <div class="aw-unread-dot" v-if="!item.is_read" :class="{ 'aw-dot-urgent': item.urgency === 'urgent' }" />
        <div class="aw-body">
          <div class="aw-item-title-row">
            <span v-if="item.urgency === 'urgent'" class="aw-urgent-tag">URGENT</span>
            <div class="aw-item-title">{{ item.title }}</div>
          </div>
          <div class="aw-item-text">{{ truncate(item.body) }}</div>
          <div class="aw-item-meta">
            <span class="aw-author">{{ item.author }}</span>
            <span class="aw-meta-dot">·</span>
            <span class="aw-date">{{ item.published_at }}</span>
          </div>
        </div>
        <button v-if="!item.is_read" class="aw-dismiss" @click.stop="dismiss(item)" title="Dismiss">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api.js';

const items   = ref([]);
const loading = ref(true);

const unreadCount = computed(() => items.value.filter(a => !a.is_read).length);

function truncate(text, max = 80) {
  return text && text.length > max ? text.slice(0, max) + '…' : text;
}

async function load() {
  try {
    const res = await api.get('/v1/announcements');
    items.value = res.data.data ?? [];
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
}

function dismiss(item) {
  api.post(`/v1/announcements/${item.id}/read`).catch(() => {});
  item.is_read = true;
}

onMounted(load);
</script>

<style scoped>
.aw-widget {
  display: flex; flex-direction: column;
  height: 100%; padding: 18px; overflow: hidden;
}

.aw-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 14px; flex-shrink: 0;
}
.aw-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.aw-title { font-size: 15px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.aw-sub   { font-size: 11.5px; color: var(--text-3); }
.aw-view-all {
  font-size: 12.5px; font-weight: 600; color: var(--primary);
  text-decoration: none; transition: color 0.15s; white-space: nowrap;
}
.aw-view-all:hover { color: var(--primary-hover); }

.aw-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 10px;
  font-size: 13px; color: var(--text-3);
}

.aw-list {
  list-style: none; margin: 0; padding: 0;
  flex: 1; overflow-y: auto; min-height: 0;
}
.aw-list::-webkit-scrollbar { width: 4px; }
.aw-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.aw-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 4px; border-bottom: 1px solid var(--border-soft);
  transition: background 0.12s;
}
.aw-item:last-child { border-bottom: none; }

.aw-unread-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--primary); flex-shrink: 0; margin-top: 5px;
}
.aw-dot-urgent { background: #dc2626; }

.aw-item-title-row { display: flex; align-items: center; gap: 5px; margin-bottom: 3px; }
.aw-urgent-tag {
  font-size: 9px; font-weight: 800; letter-spacing: .4px;
  padding: 1px 5px; border-radius: 999px;
  background: #fee2e2; color: #dc2626; flex-shrink: 0;
}

.aw-body { flex: 1; min-width: 0; }
.aw-item-title {
  font-size: 13px; font-weight: 700; color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.aw-item:not(.aw-unread) .aw-item-title { font-weight: 500; color: var(--text-2); }
.aw-item-text {
  font-size: 12px; color: var(--text-3); line-height: 1.45; margin-bottom: 5px;
}
.aw-item-meta {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px; color: var(--text-3);
}
.aw-meta-dot { color: var(--border); }

.aw-dismiss {
  display: flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; margin-top: 2px;
  border: 1px solid var(--border); background: var(--surface); color: var(--text-3);
  cursor: pointer; padding: 0; transition: background 0.12s, color 0.12s;
}
.aw-dismiss:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
</style>
