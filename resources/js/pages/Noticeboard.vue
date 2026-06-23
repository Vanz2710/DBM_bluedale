<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Notice Board</h1>
        <p class="page-subtitle">Company-wide announcements from your administrators</p>
      </div>
      <button class="btn-refresh" @click="load" :disabled="loading" title="Refresh">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.63"/></svg>
      </button>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <div v-if="unread.length > 0" class="unread-banner">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        You have {{ unread.length }} unread announcement{{ unread.length > 1 ? 's' : '' }}.
        <button class="btn-mark-all" @click="markAllRead">Mark all read</button>
      </div>

      <div v-if="items.length === 0" class="empty-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
        <div class="empty-title">No announcements</div>
        <div class="empty-sub">There are no active announcements right now. Check back later.</div>
      </div>

      <div v-else class="announce-list">
        <div
          v-for="item in items"
          :key="item.id"
          class="announce-card"
          :class="{ 'is-unread': !item.is_read, 'is-read': item.is_read, 'is-urgent': item.urgency === 'urgent' }"
        >
          <div class="card-accent" />
          <div class="card-content">
            <div class="card-top">
              <div class="card-title-row">
                <span v-if="!item.is_read" class="unread-dot" :class="{ 'dot-urgent': item.urgency === 'urgent' }" />
                <span v-if="item.urgency === 'urgent'" class="badge-urgent">URGENT</span>
                <h2 class="card-title">{{ item.title }}</h2>
              </div>
              <button
                v-if="!item.is_read"
                class="btn-dismiss"
                @click="dismiss(item)"
              >Mark read</button>
              <span v-else class="read-tag">Read</span>
            </div>
            <p class="card-body">{{ item.body }}</p>
            <div class="card-meta">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>{{ item.author }}</span>
              <span class="meta-dot">·</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <span>{{ item.published_at }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const items   = ref([]);
const loading = ref(false);

const unread = computed(() => items.value.filter(a => !a.is_read));

async function load() {
  loading.value = true;
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

function markAllRead() {
  unread.value.forEach(item => dismiss(item));
}

onMounted(load);
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 860px; }

.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 16px; margin-bottom: 24px;
}
.page-title    { font-size: 28px; font-weight: 800; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

.btn-refresh {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 999px;
  border: 1px solid var(--border); background: var(--surface); color: var(--text-2);
  cursor: pointer; flex-shrink: 0; transition: border-color 0.15s, color 0.15s;
}
.btn-refresh:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }

/* Unread banner */
.unread-banner {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary);
  font-size: 13px; font-weight: 600; margin-bottom: 20px;
}
.btn-mark-all {
  margin-left: auto; padding: 5px 12px; border-radius: 999px;
  border: 1px solid var(--primary); background: var(--surface); color: var(--primary);
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background 0.12s;
}
.btn-mark-all:hover { background: color-mix(in srgb, var(--primary) 12%, transparent); }

/* Empty */
.empty-state {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 72px 20px; text-align: center;
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg);
}
.empty-title { font-size: 17px; font-weight: 700; color: var(--text-1); }
.empty-sub   { font-size: 13.5px; color: var(--text-3); }

/* Cards */
.announce-list { display: flex; flex-direction: column; gap: 12px; }

.announce-card {
  display: flex; border-radius: var(--radius-lg);
  border: 1px solid var(--border-soft); background: var(--surface);
  overflow: hidden; transition: box-shadow 0.15s;
}
.announce-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }

.card-accent {
  width: 5px; flex-shrink: 0;
  background: var(--primary);
}
.is-urgent .card-accent { background: #dc2626; }
.is-read .card-accent { background: var(--border); }

.badge-urgent {
  font-size: 10px; font-weight: 800; letter-spacing: .5px;
  padding: 2px 7px; border-radius: 999px;
  background: #fee2e2; color: #dc2626; flex-shrink: 0;
}

.card-content { flex: 1; padding: 20px 22px; min-width: 0; }

.card-top {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 10px;
}
.card-title-row { display: flex; align-items: center; gap: 8px; }
.unread-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--primary); flex-shrink: 0;
}
.unread-dot.dot-urgent { background: #dc2626; }
.card-title {
  font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0;
}
.is-read .card-title { font-weight: 600; color: var(--text-2); }

.btn-dismiss {
  padding: 5px 14px; border-radius: 999px; white-space: nowrap; flex-shrink: 0;
  border: 1px solid var(--primary); background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary);
  font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.12s;
}
.btn-dismiss:hover { background: color-mix(in srgb, var(--primary) 18%, transparent); }

.read-tag {
  font-size: 11.5px; color: var(--text-3); font-weight: 500;
  white-space: nowrap; flex-shrink: 0; padding-top: 2px;
}

.card-body {
  font-size: 14px; color: var(--text-2); line-height: 1.65;
  margin: 0 0 14px; white-space: pre-wrap; word-break: break-word;
}
.is-read .card-body { color: var(--text-3); }

.card-meta {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--text-3);
}
.meta-dot { color: var(--border); }
</style>
