<template>
  <div class="fp-widget">
    <div class="fp-head">
      <div class="fp-title-wrap">
        <span class="fp-title">Revenue Forecast</span>
        <span class="fp-sub">Weighted pipeline by expected close month</span>
      </div>
      <router-link to="/predictive-insights" class="fp-view-all">Details</router-link>
    </div>

    <div v-if="loading" class="fp-state">Loading…</div>
    <div v-else-if="error" class="fp-state fp-state--err">Failed to load</div>
    <div v-else-if="!months.length" class="fp-state">No open deals with close dates</div>
    <div v-else class="fp-canvas-wrap">
      <canvas ref="chartCanvas" class="fp-canvas"></canvas>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import {
  Chart,
  BarController, BarElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
} from 'chart.js';
import api from '../../api.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const chartCanvas = ref(null);
const loading = ref(true);
const error = ref(false);
const months = ref([]);
let chartInstance = null;

function destroyChart() {
  chartInstance?.destroy();
  chartInstance = null;
}

function buildChart() {
  destroyChart();
  if (!chartCanvas.value || !months.value.length) return;

  const ctx = chartCanvas.value.getContext('2d');
  const labels = months.value.map(m => m.label);
  const total    = months.value.map(m => m.total_value);
  const expected = months.value.map(m => m.expected_value);

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Total Value',
          data: total,
          backgroundColor: 'rgba(29,78,216,0.15)',
          borderColor: 'rgba(29,78,216,0.5)',
          borderWidth: 1,
          borderRadius: 6,
          order: 2,
        },
        {
          label: 'Weighted',
          data: expected,
          backgroundColor: 'rgba(29,78,216,0.85)',
          borderColor: 'rgba(29,78,216,1)',
          borderWidth: 0,
          borderRadius: 6,
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { size: 11, family: 'inherit' },
            boxWidth: 10,
            boxHeight: 10,
            padding: 12,
            color: 'rgba(100,116,139,1)',
          },
        },
        tooltip: {
          backgroundColor: '#1e293b',
          titleFont: { size: 12, family: 'inherit' },
          bodyFont: { size: 11.5, family: 'inherit' },
          padding: 10,
          cornerRadius: 8,
          callbacks: {
            label(ctx) {
              const v = ctx.raw ?? 0;
              const formatted = v >= 1000 ? (v / 1000).toFixed(1) + 'k' : v.toFixed(0);
              return ` ${ctx.dataset.label}: ${formatted}`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11, family: 'inherit' }, color: 'rgba(100,116,139,1)' },
        },
        y: {
          grid: { color: 'rgba(226,232,240,0.6)' },
          ticks: {
            font: { size: 11, family: 'inherit' },
            color: 'rgba(100,116,139,1)',
            callback: (v) => v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v,
          },
        },
      },
    },
  });
}

onUnmounted(destroyChart);

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/predictive/forecast');
    months.value = Array.isArray(data) ? data : [];
    if (months.value.length) {
      await nextTick();
      buildChart();
    }
  } catch {
    error.value = true;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.fp-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}

.fp-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.fp-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.fp-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.fp-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.fp-view-all {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s;
}
.fp-view-all:hover { color: var(--primary-hover); }

.fp-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.fp-state--err { color: var(--danger); }

.fp-canvas-wrap {
  flex: 1;
  min-height: 0;
  position: relative;
}
.fp-canvas {
  width: 100% !important;
  height: 100% !important;
}
</style>
