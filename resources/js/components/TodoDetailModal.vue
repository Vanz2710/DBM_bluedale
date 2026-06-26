<template>
  <Teleport to="body">
    <Transition name="todo-modal">
      <div v-if="openId" class="modal-overlay" @click.self="close">
        <div class="modal-box" role="dialog" aria-modal="true">
          <div class="modal-head">
            <div>
              <h2 class="modal-title">To-Do Details</h2>
              <p class="modal-sub">Review this reminder and choose an action</p>
            </div>
            <button class="modal-close" @click="close" aria-label="Close">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="modal-body">
            <div v-if="loading" class="modal-loading"><LoadingSpinner /></div>
            <div v-else-if="error" class="error-box">{{ error }}</div>
            <TodoDetailBody
              v-else-if="todo"
              :todo="todo"
              :acting="acting"
              @complete="markComplete"
              @navigate="close"
            />
          </div>

          <div class="modal-foot">
            <router-link to="/reminders" class="btn-link" @click="close">View all reminders →</router-link>
            <button class="btn-close-text" @click="close">Close</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import api from '../api.js';
import { useTodoModal } from '../composables/useTodoModal.js';
import LoadingSpinner from './LoadingSpinner.vue';
import TodoDetailBody from './TodoDetailBody.vue';

const { openId, close } = useTodoModal();

const loading = ref(false);
const acting  = ref(false);
const error   = ref('');
const todo    = ref(null);

watch(openId, async (id) => {
  if (!id) { todo.value = null; error.value = ''; return; }
  loading.value = true;
  error.value = '';
  todo.value = null;
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

function onKeydown(e) {
  if (e.key === 'Escape' && openId.value) close();
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

async function markComplete() {
  if (!todo.value) return;
  acting.value = true;
  try {
    await api.patch(`/v1/todos/${todo.value.id}/status`, { status: 'completed' });
    todo.value.completion_status = 'completed';
    todo.value.completed_at = new Date().toISOString();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update. Please try again.';
  } finally {
    acting.value = false;
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 4000;
  background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-box {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); width: 100%; max-width: 640px;
  max-height: calc(100vh - 48px); box-shadow: var(--shadow-lg);
  display: flex; flex-direction: column; overflow: hidden;
}
.modal-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
  padding: 20px 24px 16px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0;
}
.modal-title { font-size: 18px; font-weight: 800; color: var(--text-1); letter-spacing: -0.3px; margin: 0 0 3px; }
.modal-sub { font-size: 12.5px; color: var(--text-3); margin: 0; }
.modal-close {
  flex-shrink: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: none; background: var(--surface-2); color: var(--text-2); border-radius: var(--radius-sm);
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.modal-close:hover { background: var(--danger-soft); color: var(--danger); }

.modal-body { padding: 22px 24px 8px; overflow-y: auto; }
.modal-loading { display: flex; justify-content: center; align-items: center; padding: 48px 0; }
.error-box { background: var(--danger-soft); color: var(--danger); border: 1px solid #fecaca; border-radius: var(--radius-sm); padding: 12px 16px; font-size: 14px; }

.modal-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 24px; border-top: 1px solid var(--border-soft); flex-shrink: 0;
}
.btn-link { font-size: 12.5px; color: var(--primary); text-decoration: none; font-weight: 600; }
.btn-link:hover { text-decoration: underline; }
.btn-close-text {
  background: var(--surface-2); border: 1px solid var(--border); color: var(--text-2);
  border-radius: var(--radius-sm); padding: 7px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.btn-close-text:hover { background: var(--border); color: var(--text-1); }

/* Transition */
.todo-modal-enter-active { transition: opacity 0.2s ease; }
.todo-modal-leave-active { transition: opacity 0.16s ease; }
.todo-modal-enter-from, .todo-modal-leave-to { opacity: 0; }
.todo-modal-enter-active .modal-box { transition: transform 0.26s cubic-bezier(0.34,1.4,0.64,1), opacity 0.2s ease; }
.todo-modal-enter-from .modal-box { transform: scale(0.94) translateY(12px); opacity: 0; }

@media (max-width: 640px) {
  .modal-overlay { padding: 0; align-items: flex-end; }
  .modal-box { max-width: 100%; max-height: 92vh; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
}
</style>
