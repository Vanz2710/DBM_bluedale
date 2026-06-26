<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">To-Do Details</h1>
        <p class="page-subtitle">Review this reminder and choose an action</p>
      </div>
      <router-link to="/todos" class="btn btn-ghost">← Back to To-Do List</router-link>
    </div>

    <div v-if="loading" class="loading-wrap"><LoadingSpinner /></div>
    <div v-else-if="error" class="error-box">{{ error }}</div>

    <div v-else class="card detail-card">
      <TodoDetailBody :todo="todo" :acting="acting" @complete="markComplete" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import TodoDetailBody from '../components/TodoDetailBody.vue';

const route = useRoute();
const id = route.params.id;
const loading = ref(true);
const acting = ref(false);
const error = ref('');
const todo = ref(null);

async function markComplete() {
  acting.value = true;
  try {
    await api.patch(`/v1/todos/${id}/status`, { status: 'completed' });
    todo.value.completion_status = 'completed';
    todo.value.completed_at = new Date().toISOString();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update. Please try again.';
  } finally {
    acting.value = false;
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/v1/todos/${id}`);
    todo.value = data.data;
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'This to-do no longer exists.'
      : (e.response?.data?.message ?? 'Failed to load this to-do.');
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

.loading-wrap { display: flex; justify-content: center; align-items: center; padding: 60px 0; }
.error-box { background: var(--danger-soft); color: var(--danger); border: 1px solid #fecaca; border-radius: var(--radius-sm); padding: 12px 16px; font-size: 14px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
.detail-card { padding: 24px 28px 22px; }
.btn { height: 40px; padding: 0 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: background 0.15s, color 0.15s; }
.btn-ghost { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--border); color: var(--text-1); }

@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .detail-card { padding: 20px 18px; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .page-header { flex-direction: column; }
}
</style>
