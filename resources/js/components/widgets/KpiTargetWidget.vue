<template>
  <div class="kt-widget">
    <div class="kt-head">
      <div class="kt-title-wrap">
        <span class="kt-title">KPI Progress</span>
        <span class="kt-sub">This month vs targets</span>
      </div>
      <router-link to="/performance" class="kt-link">Manage</router-link>
    </div>

    <div v-if="loading" class="kt-state">Loading…</div>
    <div v-else-if="noAccess" class="kt-state kt-state--muted">No access to performance data</div>
    <div v-else-if="!hasTargets" class="kt-empty">
      <Target :size="30" class="kt-empty-icon" />
      <p class="kt-empty-msg">No targets configured yet</p>
      <router-link to="/performance" class="kt-empty-link">Set targets →</router-link>
    </div>
    <div v-else class="kt-list">
      <div v-for="(row, metric) in targets" :key="metric" class="kt-row">
        <div class="kt-row-head">
          <span class="kt-label">{{ LABELS[metric] ?? metric }}</span>
          <span class="kt-vals">
            <span class="kt-achieved" :class="pctClass(row.pct)">{{ fmtVal(metric, row.achieved) }}</span>
            <span class="kt-slash">/</span>
            <span class="kt-target">{{ fmtVal(metric, row.target) }}</span>
            <span class="kt-pct-badge" :class="pctClass(row.pct)">{{ row.pct }}%</span>
          </span>
        </div>
        <div class="kt-track">
          <div class="kt-fill" :class="pctClass(row.pct)" :style="{ width: row.pct + '%' }"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Target } from 'lucide-vue-next';
import api from '../../api.js';

const loading  = ref(true);
const noAccess = ref(false);
const targets  = ref({});

const hasTargets = computed(() => Object.keys(targets.value).length > 0);

const LABELS = {
  contacts_added:      'New Contacts',
  todos_completed:     'Tasks Completed',
  followups_completed: 'Follow-ups Done',
  deals_created:       'Deals Created',
  deals_won:           'Deals Won',
  won_deal_value:      'Won Deal Value',
  projects_created:    'Projects Created',
};

function fmtVal(metric, val) {
  if (metric === 'won_deal_value') {
    return '$' + Number(val ?? 0).toLocaleString();
  }
  return String(val ?? 0);
}

function pctClass(pct) {
  if (pct >= 100) return 'kt--success';
  if (pct >= 75)  return 'kt--info';
  if (pct >= 50)  return 'kt--warning';
  return 'kt--danger';
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/performance/overview', { params: { view: 'month' } });
    targets.value = data.data?.targets ?? {};
  } catch (e) {
    if (e?.response?.status === 403) noAccess.value = true;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.kt-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.kt-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.kt-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.kt-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.kt-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.kt-link {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
}
.kt-link:hover { color: var(--primary-hover); }

.kt-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.kt-state--muted { opacity: 0.6; }

.kt-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
}
.kt-empty-icon { color: var(--primary); opacity: 0.45; }
.kt-empty-msg {
  font-size: 13px;
  color: var(--text-3);
  margin: 0;
}
.kt-empty-link {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
}
.kt-empty-link:hover { text-decoration: underline; }

.kt-list {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.kt-list::-webkit-scrollbar { width: 5px; }
.kt-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.kt-row {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.kt-row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.kt-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kt-vals {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  font-size: 12px;
}
.kt-achieved { font-weight: 700; }
.kt-slash { color: var(--text-3); margin: 0 1px; }
.kt-target { color: var(--text-3); }
.kt-pct-badge {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 999px;
  margin-left: 4px;
}

.kt-track {
  height: 6px;
  background: var(--border-soft, #e2e8f0);
  border-radius: 999px;
  overflow: hidden;
}
.kt-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(.4,0,.2,1);
  min-width: 4px;
}

/* Color classes */
.kt--success { color: var(--success); }
.kt--info    { color: var(--info, #0891b2); }
.kt--warning { color: var(--warning); }
.kt--danger  { color: var(--danger); }

.kt-fill.kt--success { background: var(--success); }
.kt-fill.kt--info    { background: var(--info, #0891b2); }
.kt-fill.kt--warning { background: var(--warning); }
.kt-fill.kt--danger  { background: var(--danger); }

.kt-pct-badge.kt--success { background: var(--success-soft); }
.kt-pct-badge.kt--info    { background: var(--info-soft, #cffafe); }
.kt-pct-badge.kt--warning { background: var(--warning-soft); }
.kt-pct-badge.kt--danger  { background: var(--danger-soft); }
</style>
