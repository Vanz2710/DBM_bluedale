<template>
  <div class="flex flex-col h-full p-4 overflow-hidden">
    <div class="flex items-center justify-between mb-3 shrink-0">
      <div class="flex items-center gap-2">
        <Users class="text-teal-500" :size="15" />
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Recent Contacts</span>
      </div>
      <router-link to="/contacts/add" class="text-xs text-blue-500 hover:underline">+ Add</router-link>
    </div>
    <div v-if="loading" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      Loading…
    </div>
    <div v-else-if="!contacts.length" class="flex-1 flex items-center justify-center text-sm text-gray-400">
      No contacts yet
    </div>
    <ul v-else class="flex-1 overflow-auto divide-y divide-gray-50 -mx-1 px-1">
      <li
        v-for="c in contacts"
        :key="c.id"
        class="flex items-center gap-2.5 py-2"
      >
        <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600 shrink-0">
          {{ initials(c.name) }}
        </div>
        <div class="flex-1 min-w-0">
          <router-link
            :to="`/contacts/${c.id}`"
            class="block text-sm font-medium text-gray-800 truncate hover:text-blue-600"
          >
            {{ c.name }}
          </router-link>
          <div class="text-xs text-gray-400 truncate">{{ c.status_label ?? c.status ?? '—' }}</div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Users } from 'lucide-vue-next';
import api from '../../api.js';

const contacts = ref([]);
const loading = ref(true);

function initials(name) {
  return (name ?? '?')
    .split(' ')
    .slice(0, 2)
    .map(w => w[0]?.toUpperCase() ?? '')
    .join('');
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/contacts', {
      params: { per_page: 7, sort_by: 'created_at', sort_dir: 'desc' },
    });
    contacts.value = data.data ?? data ?? [];
  } catch {
    // silently fail — widget shows empty state
  } finally {
    loading.value = false;
  }
});
</script>
