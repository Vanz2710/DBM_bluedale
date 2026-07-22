<template>
  <div class="ar-widget">
    <div class="ar-head">
      <div class="ar-title-wrap">
        <span class="ar-title">At-Risk Contacts</span>
        <span class="ar-sub">{{ loading ? '' : contacts.length ? `${contacts.length} need attention` : 'All clear' }}</span>
      </div>
      <router-link to="/predictive-insights" class="ar-view-all">View All</router-link>
    </div>

    <div v-if="loading" class="ar-state">Loading…</div>
    <div v-else-if="!contacts.length" class="ar-state ar-state--good">
      <CheckCircle :size="20" />
      <span>No contacts are at risk</span>
    </div>
    <ul v-else class="ar-list">
      <li v-for="c in contacts" :key="c.id" class="ar-item">
        <router-link :to="`/contacts/${c.id}`" class="ar-name">{{ c.name }}</router-link>
        <span class="ar-badge" :class="badgeClass(c.days_since_activity)">
          {{ c.days_since_activity }}d
        </span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { CheckCircle } from 'lucide-vue-next';
import api from '../../api.js';

const contacts = ref([]);
const loading = ref(true);

function badgeClass(days) {
  if (days >= 120) return 'ar-badge--critical';
  if (days >= 90) return 'ar-badge--high';
  return 'ar-badge--medium';
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/predictive/at-risk');
    contacts.value = (Array.isArray(data) ? data : []).slice(0, 15);
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.ar-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}

.ar-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.ar-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.ar-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.ar-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.ar-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s;
}
.ar-view-all:hover { color: var(--primary-hover); }

.ar-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.ar-state--good {
  flex-direction: column;
  gap: 8px;
  color: var(--success);
  font-weight: 500;
}

.ar-list {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.ar-list::-webkit-scrollbar { width: 5px; }
.ar-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.ar-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 4px;
  border-bottom: 1px solid var(--border-soft);
}
.ar-item:last-child { border-bottom: none; }

.ar-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-1);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
  transition: color 0.15s;
}
.ar-name:hover { color: var(--primary); }

.ar-badge {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
}
.ar-badge--medium  { background: var(--warning-soft); color: var(--warning); }
.ar-badge--high    { background: #fee2e2; color: #b91c1c; }
.ar-badge--critical {
  background: var(--danger);
  color: #fff;
  box-shadow: 0 2px 8px -2px rgba(220,38,38,0.45);
}
</style>
