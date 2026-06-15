<template>
  <div class="ps-widget">
    <div class="ps-head">
      <span class="ps-eyebrow">Predictive Insights</span>
      <Zap :size="16" class="ps-eyebrow-icon" />
    </div>
    <div v-if="loading" class="ps-loading">Loading…</div>
    <div v-else class="ps-grid">
      <div class="ps-card ps-card--danger">
        <div class="ps-card-body">
          <div class="ps-label">Neglected Contacts</div>
          <div class="ps-num">{{ fmt(summary.neglected) }}</div>
          <div class="ps-hint">No activity 60+ days</div>
        </div>
        <span class="ps-icon ps-icon--danger"><AlertTriangle :size="18" /></span>
      </div>
      <div class="ps-card ps-card--primary">
        <div class="ps-card-body">
          <div class="ps-label">Weighted Pipeline</div>
          <div class="ps-num">{{ fmtVal(summary.pipeline_value) }}</div>
          <div class="ps-hint">Open deals (weighted)</div>
        </div>
        <span class="ps-icon ps-icon--primary"><DollarSign :size="18" /></span>
      </div>
      <div class="ps-card ps-card--warning">
        <div class="ps-card-body">
          <div class="ps-label">Unworked Contacts</div>
          <div class="ps-num">{{ fmt(summary.unworked_opps) }}</div>
          <div class="ps-hint">No activity 30+ days</div>
        </div>
        <span class="ps-icon ps-icon--warning"><Clock :size="18" /></span>
      </div>
      <div class="ps-card" :class="summary.overloaded_agents != null ? 'ps-card--info' : 'ps-card--muted'">
        <div class="ps-card-body">
          <div class="ps-label">Overloaded Agents</div>
          <div class="ps-num">{{ summary.overloaded_agents != null ? fmt(summary.overloaded_agents) : '—' }}</div>
          <div class="ps-hint">1.5× avg contact load</div>
        </div>
        <span class="ps-icon" :class="summary.overloaded_agents != null ? 'ps-icon--info' : 'ps-icon--muted'">
          <Users :size="18" />
        </span>
      </div>
    </div>
    <router-link to="/predictive-insights" class="ps-cta">
      View full report <ArrowRight :size="12" />
    </router-link>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Zap, AlertTriangle, DollarSign, Clock, Users, ArrowRight } from 'lucide-vue-next';
import api from '../../api.js';

const summary = ref({ neglected: 0, pipeline_value: 0, unworked_opps: 0, overloaded_agents: null });
const loading = ref(true);

const fmt = (n) => (n ?? 0).toLocaleString();

function fmtVal(n) {
  const v = parseFloat(n ?? 0);
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1_000) return (v / 1_000).toFixed(1) + 'k';
  return v.toFixed(0);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/predictive/summary');
    summary.value = data;
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.ps-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.ps-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.ps-eyebrow {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.3px;
  color: var(--text-3);
}
.ps-eyebrow-icon { color: var(--primary); opacity: 0.7; }

.ps-loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}

.ps-grid {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  align-content: start;
  min-height: 0;
}

.ps-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 12px;
  border-radius: var(--radius);
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  gap: 8px;
  transition: transform 0.15s, box-shadow 0.15s;
  min-width: 0;
}
.ps-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}
.ps-card-body { min-width: 0; flex: 1; }

.ps-label {
  font-size: 11px;
  color: var(--text-2);
  font-weight: 500;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ps-num {
  font-size: 22px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.5px;
  line-height: 1;
  margin-bottom: 4px;
}
.ps-hint {
  font-size: 10px;
  color: var(--text-3);
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ps-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  flex-shrink: 0;
}
.ps-icon--danger  { background: var(--danger-soft);  color: var(--danger); }
.ps-icon--primary { background: var(--primary-soft); color: var(--primary); }
.ps-icon--warning { background: var(--warning-soft); color: var(--warning); }
.ps-icon--info    { background: var(--info-soft);    color: var(--info); }
.ps-icon--muted   { background: var(--surface-2);    color: var(--text-3); }

.ps-card--danger  .ps-num { color: var(--danger); }
.ps-card--primary .ps-num { color: var(--primary); }
.ps-card--warning .ps-num { color: var(--warning); }
.ps-card--info    .ps-num { color: var(--info); }

.ps-cta {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
  align-self: flex-start;
}
.ps-cta:hover { color: var(--primary-hover); }
</style>
