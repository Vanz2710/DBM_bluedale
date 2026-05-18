<template>
  <div class="flex flex-col h-full p-4 overflow-hidden">
    <div class="flex items-center gap-2 mb-3 shrink-0">
      <TrendingUp class="text-blue-500" :size="15" />
      <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Monthly Pipeline</span>
    </div>
    <div v-if="loading" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Loading…
    </div>
    <div v-else-if="error" class="flex-1 flex items-center justify-center text-sm text-red-400">
      Failed to load
    </div>
    <div v-else class="flex-1 relative min-h-0">
      <canvas ref="chartCanvas" class="absolute inset-0 w-full h-full"></canvas>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { TrendingUp } from 'lucide-vue-next';
import {
  Chart,
  LineController, LineElement, PointElement,
  CategoryScale, LinearScale,
  Tooltip, Filler,
} from 'chart.js';
import api from '../../api.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler);

const chartCanvas = ref(null);
const loading = ref(true);
const error = ref(false);
let chartInstance = null;

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/analytics');
    loading.value = false;
    await nextTick();
    if (chartCanvas.value && data.by_month?.length) {
      chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
          labels: data.by_month.map(r => r.label),
          datasets: [{
            data: data.by_month.map(r => r.count),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { beginAtZero: true, ticks: { font: { size: 10 } } },
          },
        },
      });
    }
  } catch {
    loading.value = false;
    error.value = true;
  }
});

onUnmounted(() => {
  chartInstance?.destroy();
});
</script>
