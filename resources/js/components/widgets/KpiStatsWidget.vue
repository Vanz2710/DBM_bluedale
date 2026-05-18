<template>
  <div class="flex flex-col h-full p-4 overflow-hidden">
    <div class="flex items-center gap-2 mb-3 shrink-0">
      <BarChart2 class="text-purple-500" :size="15" />
      <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">At a Glance</span>
    </div>
    <div v-if="loading" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Loading…
    </div>
    <div v-else class="grid grid-cols-2 gap-3 flex-1 content-start">
      <div class="bg-blue-50 rounded-lg p-3">
        <div class="text-2xl font-bold text-blue-700">{{ fmt(stats.total_contacts) }}</div>
        <div class="text-xs text-blue-400 mt-0.5">Total Contacts</div>
      </div>
      <div class="bg-green-50 rounded-lg p-3">
        <div class="text-2xl font-bold text-green-700">{{ fmt(stats.this_month) }}</div>
        <div class="text-xs text-green-400 mt-0.5">New This Month</div>
      </div>
      <div class="bg-cyan-50 rounded-lg p-3">
        <div class="text-2xl font-bold text-cyan-700">{{ fmt(stats.active_count) }}</div>
        <div class="text-xs text-cyan-400 mt-0.5">Active</div>
      </div>
      <div class="bg-orange-50 rounded-lg p-3">
        <div class="text-2xl font-bold text-orange-600">{{ fmt(stats.unassigned) }}</div>
        <div class="text-xs text-orange-400 mt-0.5">Unassigned</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { BarChart2 } from 'lucide-vue-next';
import api from '../../api.js';

const stats = ref({});
const loading = ref(true);

const fmt = (n) => (n ?? 0).toLocaleString();

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/analytics');
    stats.value = data;
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>
