<template>
  <div class="dtw-widget">
    <div class="dtw-head">
      <div class="dtw-title-wrap">
        <span class="dtw-title">Task Manager</span>
        <span class="dtw-sub">{{ loading ? '' : `${stats.total ?? 0} tasks tracked` }}</span>
      </div>
      <router-link to="/dept-tasks" class="dtw-view-all">View All</router-link>
    </div>

    <div v-if="loading" class="dtw-state">Loading…</div>
    <div v-else class="dtw-kpi-row">
      <div class="dtw-kpi dtw-kpi--danger">
        <div class="dtw-kpi-val">{{ stats.overdue ?? 0 }}</div>
        <div class="dtw-kpi-label">Overdue</div>
      </div>
      <div class="dtw-kpi dtw-kpi--info">
        <div class="dtw-kpi-val">{{ stats.inProgress ?? 0 }}</div>
        <div class="dtw-kpi-label">In Progress</div>
      </div>
      <div class="dtw-kpi dtw-kpi--muted">
        <div class="dtw-kpi-val">{{ stats.pending ?? 0 }}</div>
        <div class="dtw-kpi-label">Pending</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../api.js';

const stats   = ref({ total: 0, overdue: 0, inProgress: 0, pending: 0 });
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/dept/dashboard');
    stats.value = data.stats ?? stats.value;
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.dtw-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.dtw-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.dtw-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.dtw-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.dtw-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.dtw-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s;
}
.dtw-view-all:hover { color: var(--primary-hover); }

.dtw-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}

.dtw-kpi-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  flex: 1;
}
.dtw-kpi {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 4px;
  padding: 14px 8px;
  border-radius: var(--radius);
  background: var(--surface-2);
  text-align: center;
}
.dtw-kpi-val {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.4px;
}
.dtw-kpi-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.dtw-kpi--danger .dtw-kpi-val { color: var(--danger); }
.dtw-kpi--info   .dtw-kpi-val { color: var(--primary); }
.dtw-kpi--muted  .dtw-kpi-val { color: var(--text-2); }
</style>
