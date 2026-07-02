<template>
  <Teleport to="body">
    <div v-if="open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">{{ title }}</p>
            <p v-if="subtitle" class="conf-sub">{{ subtitle }}</p>
          </div>
          <button class="conf-close" @click="$emit('close')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="var(--warning)" stroke="none"/>
          </svg>
          <p class="conf-text"><slot /></p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="$emit('close')">Cancel</button>
          <button class="conf-delete" :disabled="loading" @click="$emit('confirm')">
            {{ loading ? loadingLabel : confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  confirmLabel: { type: String, default: 'Delete' },
  loadingLabel: { type: String, default: 'Deleting…' },
});
defineEmits(['close', 'confirm']);
</script>

<style scoped>
@keyframes overlay-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes modal-spring-in {
  from { opacity: 0; transform: scale(0.92) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 16px; animation: overlay-fade-in 0.18s ease; }
.conf-overlay > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-3); line-height: 1; padding: 0; }
.conf-close:hover { color: var(--text-1); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete { height: 38px; padding: 0 18px; background: var(--danger); color: var(--primary-on); border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-delete:hover:not(:disabled) { filter: brightness(0.88); }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
