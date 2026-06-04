<template>
  <Teleport to="body">
    <div class="toast-stack" aria-live="polite">
      <TransitionGroup name="toast" tag="div" class="toast-inner">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="toast"
          :class="`toast--${t.type}`"
          role="alert"
        >
          <span class="toast-icon" v-html="icons[t.type]"></span>
          <span class="toast-body">{{ t.message }}</span>
          <button class="toast-close" @click="remove(t.id)" aria-label="Dismiss">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const toasts = ref([]);
let nextId = 0;

const _s = (p) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const icons = {
  error:   _s('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'),
  warning: _s('<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'),
  success: _s('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
  info:    _s('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'),
};

function remove(id) {
  toasts.value = toasts.value.filter(t => t.id !== id);
}

function onToastEvent(e) {
  const { message, type = 'info', duration = 4500 } = e.detail;
  const id = ++nextId;
  toasts.value.push({ id, message, type });
  if (duration > 0) setTimeout(() => remove(id), duration);
}

onMounted(() => window.addEventListener('crm-toast', onToastEvent));
onUnmounted(() => window.removeEventListener('crm-toast', onToastEvent));
</script>

<style scoped>
.toast-stack {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 99999;
  pointer-events: none;
  max-width: 360px;
  width: 100%;
}
.toast-inner {
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
}
.toast {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius);
  font-size: 13.5px;
  font-weight: 500;
  color: var(--text-1);
  background: var(--surface);
  border: 1px solid var(--border);
  border-left-width: 3px;
  box-shadow: var(--shadow-lg);
  pointer-events: all;
  min-width: 260px;
}
.toast--error   { border-left-color: var(--danger);  }
.toast--warning { border-left-color: var(--warning); }
.toast--success { border-left-color: var(--success); }
.toast--info    { border-left-color: var(--info);    }

.toast-icon { flex-shrink: 0; margin-top: 1px; display: flex; }
.toast--error   .toast-icon { color: var(--danger);  }
.toast--warning .toast-icon { color: var(--warning); }
.toast--success .toast-icon { color: var(--success); }
.toast--info    .toast-icon { color: var(--info);    }

.toast-body { flex: 1; line-height: 1.45; }

.toast-close {
  flex-shrink: 0;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-3);
  display: flex;
  align-items: center;
  padding: 0;
  border-radius: 4px;
  transition: color 0.15s;
  margin-top: 1px;
}
.toast-close:hover { color: var(--text-1); }

.toast-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(16px); }
.toast-leave-to     { opacity: 0; transform: translateX(16px); }
.toast-move         { transition: transform 0.2s ease; }
</style>
