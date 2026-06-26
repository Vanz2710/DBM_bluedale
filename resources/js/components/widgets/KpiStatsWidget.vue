<template>
  <div class="kpi-widget">
    <div class="kpi-head">
      <span class="kpi-eyebrow">At a Glance</span>
      <BarChart2 :size="16" class="kpi-eyebrow-icon" />
    </div>
    <div v-if="loading" class="kpi-grid">
      <div v-for="i in 4" :key="i" class="kpi-card kpi-skel">
        <div class="kpi-card-body">
          <div class="skel skel-label"></div>
          <div class="skel skel-num"></div>
        </div>
        <div class="skel skel-icon"></div>
      </div>
    </div>
    <div v-else class="kpi-grid">
      <div class="kpi-card kpi-card--primary">
        <div class="kpi-card-body">
          <div class="kpi-label">Total Contacts</div>
          <div class="kpi-num">{{ fmt(displayed.total_contacts) }}</div>
        </div>
        <span class="kpi-icon kpi-icon--primary"><Users :size="18" /></span>
      </div>
      <div class="kpi-card kpi-card--success">
        <div class="kpi-card-body">
          <div class="kpi-label">New This Month</div>
          <div class="kpi-num">{{ fmt(displayed.this_month) }}</div>
        </div>
        <span class="kpi-icon kpi-icon--success"><TrendingUp :size="18" /></span>
      </div>
      <div class="kpi-card kpi-card--info">
        <div class="kpi-card-body">
          <div class="kpi-label">Active</div>
          <div class="kpi-num">{{ fmt(displayed.active_count) }}</div>
        </div>
        <span class="kpi-icon kpi-icon--info"><Activity :size="18" /></span>
      </div>
      <div class="kpi-card kpi-card--warning">
        <div class="kpi-card-body">
          <div class="kpi-label">Unassigned</div>
          <div class="kpi-num">{{ fmt(displayed.unassigned) }}</div>
        </div>
        <span class="kpi-icon kpi-icon--warning"><AlertCircle :size="18" /></span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { BarChart2, Users, TrendingUp, Activity, AlertCircle } from 'lucide-vue-next';
import api from '../../api.js';

const stats    = ref({});
const loading  = ref(true);
const displayed = reactive({ total_contacts: 0, this_month: 0, active_count: 0, unassigned: 0 });

const fmt = (n) => (n ?? 0).toLocaleString();

function countUp(key, target, duration = 750) {
  const to = target ?? 0;
  const start = performance.now();
  function frame(now) {
    const p = Math.min((now - start) / duration, 1);
    const eased = 1 - (1 - p) ** 3;
    displayed[key] = Math.round(to * eased);
    if (p < 1) requestAnimationFrame(frame);
    else displayed[key] = to;
  }
  requestAnimationFrame(frame);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/analytics');
    stats.value = data;
    countUp('total_contacts', data.total_contacts);
    countUp('this_month',     data.this_month);
    countUp('active_count',   data.active_count);
    countUp('unassigned',     data.unassigned);
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.kpi-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.kpi-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.kpi-eyebrow {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.3px;
  color: var(--text-3);
}
.kpi-eyebrow-icon { color: var(--primary); opacity: 0.7; }
.kpi-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.kpi-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  align-content: start;
  min-height: 0;
}
.kpi-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 14px;
  border-radius: var(--radius);
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  gap: 10px;
  transition: transform 0.15s, box-shadow 0.15s;
  min-width: 0;
}
.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}
.kpi-card-body { min-width: 0; }
.kpi-label {
  font-size: 11px;
  color: var(--text-2);
  font-weight: 500;
  margin-bottom: 6px;
  line-height: 1.3;
}
.kpi-num {
  font-size: 24px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.5px;
  line-height: 1;
}
.kpi-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  flex-shrink: 0;
}
.kpi-icon--primary { background: var(--primary-soft); color: var(--primary); }
.kpi-icon--success { background: var(--success-soft); color: var(--success); }
.kpi-icon--info    { background: var(--info-soft);    color: var(--info); }
.kpi-icon--warning { background: var(--warning-soft); color: var(--warning); }

/* Skeleton shimmer */
@keyframes shimmer {
  from { background-position: -200% 0; }
  to   { background-position:  200% 0; }
}
.skel {
  background: linear-gradient(90deg, var(--border-soft) 25%, var(--border) 50%, var(--border-soft) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite linear;
  border-radius: 4px;
  flex-shrink: 0;
}
.kpi-skel { pointer-events: none; }
.skel-label { height: 11px; width: 70%; margin-bottom: 10px; }
.skel-num   { height: 24px; width: 50%; border-radius: 6px; }
.skel-icon  { width: 36px; height: 36px; border-radius: 12px; }
</style>
