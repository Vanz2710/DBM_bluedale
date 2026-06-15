<template>
  <div class="rc-widget">
    <div class="rc-head">
      <div class="rc-title-wrap">
        <span class="rc-title">Recent Contacts</span>
        <span class="rc-sub">Latest entries</span>
      </div>
      <router-link to="/list" class="rc-view-all">View All</router-link>
    </div>
    <div v-if="loading" class="rc-empty">Loading…</div>
    <div v-else-if="!contacts.length" class="rc-empty">No contacts yet</div>
    <ul v-else class="rc-list">
      <li v-for="c in contacts" :key="c.id" class="rc-item">
        <div class="rc-avatar" :style="avatarStyle(c.name)">
          {{ initials(c.name) }}
        </div>
        <div class="rc-body">
          <router-link :to="`/contacts/${c.id}`" class="rc-name">{{ c.name }}</router-link>
          <div class="rc-meta">{{ c.status_label ?? c.status?.name ?? '—' }}<span v-if="c.type?.name" class="rc-meta-sep">·</span>{{ c.type?.name }}</div>
        </div>
        <router-link :to="`/contacts/${c.id}/edit`" class="rc-edit" title="Edit">
          <Pencil :size="13" />
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Pencil } from 'lucide-vue-next';
import api from '../../api.js';

const contacts = ref([]);
const loading = ref(true);

function initials(name) {
  return (name ?? '?')
    .split(' ')
    .slice(0, 2)
    .map(w => w[0]?.toUpperCase() ?? '')
    .join('');
}

// Deterministic pastel based on the name — keeps the same color across reloads
function avatarStyle(name) {
  const palette = [
    { bg: '#dbeafe', fg: '#1e40af' },
    { bg: '#dcfce7', fg: '#15803d' },
    { bg: '#dbeafe', fg: '#1d4ed8' },
    { bg: '#fef3c7', fg: '#a16207' },
    { bg: '#fee2e2', fg: '#b91c1c' },
    { bg: '#cffafe', fg: '#0e7490' },
  ];
  let hash = 0;
  for (let i = 0; i < (name ?? '').length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0;
  const c = palette[Math.abs(hash) % palette.length];
  return { background: c.bg, color: c.fg };
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/contacts', {
      params: { per_page: 7, sort_by: 'created_at', sort_dir: 'desc' },
    });
    contacts.value = data.data ?? data ?? [];
  } catch {
    // silently fail — widget shows empty state
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.rc-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.rc-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.rc-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.rc-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.rc-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.rc-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
}
.rc-view-all:hover { color: var(--primary-hover); }

.rc-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}

.rc-list {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.rc-list::-webkit-scrollbar { width: 6px; }
.rc-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; border: 0; }

.rc-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 4px;
  border-bottom: 1px solid var(--border-soft);
}
.rc-item:last-child { border-bottom: none; }

.rc-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.rc-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.rc-name {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-1);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.15s;
}
.rc-name:hover { color: var(--primary); }
.rc-meta {
  font-size: 11.5px;
  color: var(--text-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rc-meta-sep { margin: 0 4px; }

.rc-edit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--text-3);
  background: transparent;
  text-decoration: none;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.rc-edit:hover { background: var(--primary-soft); color: var(--primary); }
</style>
