<template>
  <div class="dp-widget">
    <div class="dp-head">
      <div class="dp-title-wrap">
        <span class="dp-title">Deal Pipeline</span>
        <span class="dp-sub">{{ loading ? '' : `${stats.open_count ?? 0} open deals` }}</span>
      </div>
      <router-link to="/deals" class="dp-view-all">View All</router-link>
    </div>

    <div v-if="loading" class="dp-state">Loading…</div>
    <div v-else class="dp-body">
      <!-- KPI row -->
      <div class="dp-kpi-row">
        <div class="dp-kpi dp-kpi--primary">
          <div class="dp-kpi-val">{{ fmtVal(stats.open_value) }}</div>
          <div class="dp-kpi-label">Pipeline Value</div>
        </div>
        <div class="dp-kpi dp-kpi--success">
          <div class="dp-kpi-val">{{ fmtVal(stats.won_value) }}</div>
          <div class="dp-kpi-label">Won Value</div>
        </div>
        <div class="dp-kpi dp-kpi--info">
          <div class="dp-kpi-val">{{ fmtVal(stats.weighted_forecast) }}</div>
          <div class="dp-kpi-label">Weighted Forecast</div>
        </div>
      </div>

      <!-- By stage -->
      <div class="dp-stages">
        <div v-if="!(stats.by_stage ?? []).length" class="dp-no-stages">No open deals by stage</div>
        <div v-for="s in (stats.by_stage ?? [])" :key="s.stage" class="dp-stage-row">
          <span class="dp-stage-name">{{ s.stage }}</span>
          <div class="dp-stage-bar-wrap">
            <div class="dp-stage-bar" :style="{ width: stageWidth(s.count) + '%' }"></div>
          </div>
          <span class="dp-stage-count">{{ s.count }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../api.js';

const stats = ref({ open_count: 0, open_value: 0, won_count: 0, won_value: 0, weighted_forecast: 0, by_stage: [] });
const loading = ref(true);

const maxCount = computed(() => {
  const stages = stats.value.by_stage ?? [];
  return stages.length ? Math.max(...stages.map(s => s.count), 1) : 1;
});

function stageWidth(count) {
  return Math.max(4, Math.round((count / maxCount.value) * 100));
}

function fmtVal(n) {
  const v = parseFloat(n ?? 0);
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1_000) return (v / 1_000).toFixed(1) + 'k';
  return v.toFixed(0);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/deals/summary');
    stats.value = data.data ?? data;
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.dp-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}

.dp-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.dp-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.dp-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.dp-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.dp-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s;
}
.dp-view-all:hover { color: var(--primary-hover); }

.dp-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}

.dp-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 0;
  overflow: hidden;
}

/* KPI row */
.dp-kpi-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  flex-shrink: 0;
}
.dp-kpi {
  padding: 10px 12px;
  border-radius: var(--radius);
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
}
.dp-kpi-val {
  font-size: 18px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.5px;
  line-height: 1;
  margin-bottom: 4px;
}
.dp-kpi-label {
  font-size: 10.5px;
  color: var(--text-3);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dp-kpi--primary .dp-kpi-val { color: var(--primary); }
.dp-kpi--success .dp-kpi-val { color: var(--success); }
.dp-kpi--info    .dp-kpi-val { color: var(--info); }

/* Stages */
.dp-stages {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 7px;
  overflow-y: auto;
  min-height: 0;
}
.dp-stages::-webkit-scrollbar { width: 5px; }
.dp-stages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.dp-no-stages {
  font-size: 12.5px;
  color: var(--text-3);
  text-align: center;
  padding: 12px 0;
}

.dp-stage-row {
  display: grid;
  grid-template-columns: 130px 1fr 28px;
  align-items: center;
  gap: 8px;
}
.dp-stage-name {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dp-stage-bar-wrap {
  height: 7px;
  background: var(--border-soft);
  border-radius: 999px;
  overflow: hidden;
}
.dp-stage-bar {
  height: 100%;
  background: var(--primary);
  border-radius: 999px;
  transition: width 0.4s ease;
}
.dp-stage-count {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-1);
  text-align: right;
}
</style>
