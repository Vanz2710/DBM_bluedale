<template>
  <div class="flex flex-col h-full p-4 overflow-hidden">
    <div class="flex items-center justify-between mb-3 shrink-0">
      <div class="flex items-center gap-2">
        <ClipboardList class="text-green-500" :size="15" />
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pending Tasks</span>
      </div>
      <router-link to="/todos" class="text-xs text-blue-500 hover:underline">View all</router-link>
    </div>
    <div v-if="loading" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Loading…
    </div>
    <div v-else-if="!tasks.length" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      No pending tasks
    </div>
    <ul v-else class="flex-1 overflow-auto divide-y divide-gray-50 -mx-1 px-1">
      <li v-for="t in tasks" :key="t.id" class="py-2">
        <div class="text-sm font-medium text-gray-800 truncate">
          {{ t.contact?.name ?? 'Unknown' }}
        </div>
        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
          <span class="text-xs text-gray-400">{{ formatDate(t.todo_date) }}</span>
          <span
            v-if="t.task_label ?? t.task"
            class="text-xs bg-yellow-50 text-yellow-700 border border-yellow-200 px-1.5 py-px rounded"
          >
            {{ t.task_label ?? t.task }}
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ClipboardList } from 'lucide-vue-next';
import api from '../../api.js';

const tasks = ref([]);
const loading = ref(true);

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/todos', {
      params: { per_page: 8, completion_status: 'pending' },
    });
    tasks.value = data.data ?? data ?? [];
  } catch {
    // silently fail
  } finally {
    loading.value = false;
  }
});
</script>
