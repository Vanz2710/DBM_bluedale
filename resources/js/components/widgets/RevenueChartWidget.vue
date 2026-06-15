<template>
  <div class="cw-widget">
    <div class="cw-head">
      <div class="cw-title-wrap">
        <span class="cw-title">{{ currentTab.title }}</span>
        <span class="cw-sub">{{ currentTab.sub }}</span>
      </div>
      <div class="cw-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="cw-tab"
          :class="{ 'cw-tab--active': activeTab === tab.key }"
          @click="switchTab(tab.key)"
        >{{ tab.label }}</button>
      </div>
    </div>
    <div v-if="loading" class="cw-state">Loading…</div>
    <div v-else-if="error" class="cw-state cw-state--err">Failed to load</div>
    <div v-else class="cw-canvas-wrap">
      <canvas ref="chartCanvas" class="cw-canvas"></canvas>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import {
  Chart,
  LineController, LineElement, PointElement,
  BarController, BarElement,
  DoughnutController, ArcElement,
  CategoryScale, LinearScale,
  Tooltip, Legend, Filler,
} from 'chart.js';
import api from '../../api.js';

Chart.register(
  LineController, LineElement, PointElement,
  BarController, BarElement,
  DoughnutController, ArcElement,
  CategoryScale, LinearScale,
  Tooltip, Legend, Filler,
);

const tabs = [
  { key: 'trend',    label: 'Trend',    title: 'Monthly Pipeline',     sub: 'New contacts added per month (last 12 months)' },
  { key: 'tasks',    label: 'Tasks',    title: 'Monthly Task Activity', sub: 'Tasks scheduled per month (last 12 months)' },
  { key: 'status',   label: 'Status',   title: 'Contacts by Status',   sub: 'Current status distribution' },
  { key: 'industry', label: 'Industry', title: 'Contacts by Industry',  sub: 'Top industries represented' },
];

const activeTab = ref('trend');
const currentTab = computed(() => tabs.find(t => t.key === activeTab.value));

const chartCanvas = ref(null);
const loading = ref(true);
const error = ref(false);
let chartInstance = null;
let analyticsData = null;

const PALETTE = [
  '#1d4ed8','#1e40af','#4f46e5','#0891b2','#059669',
  '#d97706','#dc2626','#db2777','#1d4ed8','#0e7490',
];

function destroyChart() {
  chartInstance?.destroy();
  chartInstance = null;
}

async function buildChart() {
  destroyChart();
  if (!chartCanvas.value || !analyticsData) return;

  const ctx = chartCanvas.value.getContext('2d');

  if (activeTab.value === 'trend' || activeTab.value === 'tasks') {
    const months = activeTab.value === 'trend'
      ? (analyticsData.by_month ?? [])
      : (analyticsData.by_tasks ?? []);
    const color = activeTab.value === 'trend' ? '#1d4ed8' : '#0891b2';
    const gradientRgb = activeTab.value === 'trend' ? '124,58,237' : '8,145,178';
    const gradient = ctx.createLinearGradient(0, 0, 0, chartCanvas.value.clientHeight || 200);
    gradient.addColorStop(0, `rgba(${gradientRgb},0.28)`);
    gradient.addColorStop(1, `rgba(${gradientRgb},0.00)`);
    chartInstance = new Chart(chartCanvas.value, {
      type: 'bar',
      data: {
        labels: months.map(r => r.label),
        datasets: [{
          label: activeTab.value === 'trend' ? 'Contacts' : 'Tasks',
          data: months.map(r => r.count),
          backgroundColor: months.map((_, i) => i === months.length - 1 ? color : `rgba(${gradientRgb},0.55)`),
          borderRadius: 5,
          borderSkipped: false,
        }],
      },
      options: barMonthOpts(),
    });

  } else if (activeTab.value === 'status') {
    const rows = (analyticsData.by_status ?? []).slice(0, 8);
    chartInstance = new Chart(chartCanvas.value, {
      type: 'doughnut',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{
          data: rows.map(r => r.count),
          backgroundColor: PALETTE.slice(0, rows.length),
          borderColor: '#fff',
          borderWidth: 2,
          hoverOffset: 6,
        }],
      },
      options: doughnutOpts(),
    });

  } else if (activeTab.value === 'industry') {
    const rows = (analyticsData.by_industry ?? []).slice(0, 8);
    chartInstance = new Chart(chartCanvas.value, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{
          label: 'Contacts',
          data: rows.map(r => r.count),
          backgroundColor: PALETTE.slice(0, rows.length),
          borderRadius: 6,
          borderSkipped: false,
        }],
      },
      options: barOpts(),
    });
  }
}

function barMonthOpts() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: tooltipDefaults(),
    },
    scales: {
      x: {
        ...axisX(),
        ticks: { font: { size: 9 }, color: '#94a3b8', maxRotation: 45, minRotation: 30 },
      },
      y: { ...axisY(), ticks: { ...axisY().ticks, stepSize: 1 } },
    },
  };
}

function barOpts() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
      legend: { display: false },
      tooltip: tooltipDefaults(),
    },
    scales: {
      x: { ...axisY(), beginAtZero: true },
      y: { ...axisX(), ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0 } },
    },
  };
}

function doughnutOpts() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
      legend: {
        display: true,
        position: 'right',
        labels: { font: { size: 11 }, color: '#64748b', padding: 12, boxWidth: 12, usePointStyle: true },
      },
      tooltip: tooltipDefaults(),
    },
  };
}

function tooltipDefaults() {
  return {
    backgroundColor: '#0f2456',
    padding: 10,
    titleFont: { size: 11, weight: '600' },
    bodyFont: { size: 12 },
    displayColors: false,
    cornerRadius: 8,
  };
}

function axisX() {
  return {
    border: { display: false },
    grid: { display: false },
    ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0 },
  };
}

function axisY() {
  return {
    beginAtZero: true,
    border: { display: false },
    grid: { color: 'rgba(148,163,184,0.15)', drawTicks: false },
    ticks: { font: { size: 10 }, color: '#94a3b8', padding: 8 },
  };
}

async function switchTab(key) {
  activeTab.value = key;
  await nextTick();
  buildChart();
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/analytics');
    analyticsData = data;
    loading.value = false;
    await nextTick();
    buildChart();
  } catch {
    loading.value = false;
    error.value = true;
  }
});

onUnmounted(() => destroyChart());
</script>

<style scoped>
.cw-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.cw-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
  gap: 12px;
}
.cw-title-wrap { display: flex; flex-direction: column; gap: 2px; flex-shrink: 0; }
.cw-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.cw-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.cw-tabs {
  display: flex;
  gap: 4px;
  background: var(--bg-2, #f1f5f9);
  border-radius: 8px;
  padding: 3px;
  flex-shrink: 0;
}
.cw-tab {
  padding: 4px 12px;
  font-size: 11.5px;
  font-weight: 600;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  line-height: 1.6;
}
.cw-tab:hover { color: var(--text-1); }
.cw-tab--active {
  background: #fff;
  color: var(--primary);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.cw-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.cw-state--err { color: var(--danger); }
.cw-canvas-wrap {
  flex: 1;
  position: relative;
  min-height: 0;
}
.cw-canvas {
  position: absolute;
  inset: 0;
  width: 100% !important;
  height: 100% !important;
}
</style>
