<template>
  <Teleport to="body">
    <div v-if="show" class="overlay" @click.self="$emit('stay')">
      <div class="modal session-modal" role="dialog" aria-modal="true" aria-labelledby="session-title">
        <div class="modal-head">
          <div class="session-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <div class="modal-title" id="session-title">Session expiring soon</div>
            <div class="modal-sub">You've been inactive for a while</div>
          </div>
        </div>
        <div class="modal-body">
          <p class="session-msg">
            You will be automatically logged out in
            <strong class="session-countdown">{{ formatted }}</strong>
            due to inactivity.
          </p>
        </div>
        <div class="modal-foot">
          <button class="btn btn-ghost" @click="$emit('logout')">Logout now</button>
          <button class="btn btn-primary" @click="$emit('stay')" autofocus>Stay logged in</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  show:        { type: Boolean, required: true },
  secondsLeft: { type: Number,  required: true },
});

defineEmits(['stay', 'logout']);

const formatted = computed(() => {
  const m = Math.floor(props.secondsLeft / 60);
  const s = props.secondsLeft % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
});
</script>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}

.modal-head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 22px 14px;
  border-bottom: 1px solid var(--border-soft);
}

.modal-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  line-height: 1.3;
}

.modal-sub {
  font-size: 12.5px;
  color: var(--text-3);
  margin-top: 2px;
}

.modal-body {
  padding: 18px 22px;
}

.session-msg {
  font-size: 13.5px;
  color: var(--text-2);
  margin: 0;
  line-height: 1.6;
}

.session-countdown {
  font-size: 15px;
  font-variant-numeric: tabular-nums;
  color: var(--text-1);
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px 18px;
  border-top: 1px solid var(--border-soft);
}

.session-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius);
  background: #fef3c7;
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s;
  line-height: 1;
}

.btn-primary {
  background: var(--primary);
  color: #fff;
}
.btn-primary:hover { opacity: 0.88; }

.btn-ghost {
  background: transparent;
  color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-ghost:hover { background: var(--surface-2); }
</style>
