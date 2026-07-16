<template>
  <div class="detail-body">
    <!-- Header row: contact + status -->
    <div class="detail-top">
      <router-link
        v-if="todo.contact"
        :to="`/contacts/${todo.contact_id}`"
        class="company-chip"
        @click="$emit('navigate')"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        {{ todo.contact.name }}
      </router-link>
      <span class="badge" :class="statusBadgeClass">{{ statusLabel }}</span>
    </div>

    <!-- Field grid -->
    <div class="field-grid">
      <div class="field-item">
        <span class="field-key">Task</span>
        <span class="field-val">{{ todo.task?.name || '—' }}</span>
      </div>
      <div class="field-item">
        <span class="field-key">Assigned To</span>
        <span class="field-val">{{ todo.user?.name || 'Unassigned' }}</span>
      </div>
      <div class="field-item">
        <span class="field-key">Deadline</span>
        <span class="field-val" :class="{ 'val-overdue': isOverdue }">
          {{ fmtDate(todo.todo_date) }}
          <span v-if="isOverdue" class="overdue-tag">Overdue</span>
        </span>
      </div>
      <div class="field-item">
        <span class="field-key">To-Do Date</span>
        <span class="field-val">{{ fmtDate(todo.date_created) || '—' }}</span>
      </div>
      <div class="field-item">
        <span class="field-key">Company Status</span>
        <span class="field-val">{{ todo.contact?.status?.name || '—' }}</span>
      </div>
      <div class="field-item">
        <span class="field-key">Company Type</span>
        <span class="field-val">{{ todo.contact?.type?.name || '—' }}</span>
      </div>
    </div>

    <!-- Remark -->
    <div class="remark-block">
      <span class="field-key">Remark</span>
      <p class="remark-text">{{ todo.todo_remark || 'No remark added.' }}</p>
    </div>

    <!-- Actions -->
    <div class="btn-row">
      <button
        v-if="todo.completion_status === 'pending'"
        class="btn btn-success"
        :disabled="acting"
        @click="$emit('complete')"
      >
        {{ acting ? 'Saving…' : '✓ Mark Complete' }}
      </button>
      <router-link
        v-if="todo.contact"
        :to="`/contacts/${todo.contact_id}`"
        class="btn btn-ghost"
        @click="$emit('navigate')"
      >Open Contact</router-link>
      <router-link
        :to="`/todos/${todo.id}/edit`"
        class="btn btn-primary"
        @click="$emit('navigate')"
      >Edit</router-link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  todo:   { type: Object, required: true },
  acting: { type: Boolean, default: false },
});

defineEmits(['complete', 'navigate']);

const statusLabel = computed(() => {
  const s = props.todo?.completion_status ?? 'pending';
  return { pending: 'Pending', completed: 'Completed', cancelled: 'Cancelled' }[s] ?? s;
});

const statusBadgeClass = computed(() => ({
  pending:   'badge-amber',
  completed: 'badge-green',
  cancelled: 'badge-gray',
}[props.todo?.completion_status] ?? 'badge-gray'));

const isOverdue = computed(() => {
  if (props.todo?.completion_status !== 'pending') return false;
  const d = String(props.todo.todo_date).slice(0, 10);
  return d < new Date().toISOString().slice(0, 10);
});

function fmtDate(val) {
  if (!val) return '';
  const d = new Date(String(val).slice(0, 10) + 'T00:00:00');
  if (isNaN(d)) return '';
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<style scoped>
.detail-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 22px; }
.company-chip {
  background: var(--primary-soft); color: var(--primary-text); border-radius: var(--radius-sm);
  padding: 8px 14px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; transition: background 0.15s;
}
.company-chip:hover { background: #c7dbfd; }

.badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-green { background: #dcfce7; color: #15803d; }
.badge-amber { background: #fef3c7; color: #92400e; }
.badge-gray  { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }

.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; padding: 4px 0 22px; border-bottom: 1px solid var(--border-soft); }
.field-item { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.field-key { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-2); }
.field-val { font-size: 14px; color: var(--text-1); font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
.val-overdue { color: var(--danger); }
.overdue-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: var(--danger-soft); color: var(--danger); padding: 2px 7px; border-radius: 999px; }

.remark-block { padding: 20px 0 4px; display: flex; flex-direction: column; gap: 8px; }
.remark-text { font-size: 14px; color: var(--text-1); line-height: 1.6; margin: 0; white-space: pre-wrap; }

.btn-row { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
.btn { height: 40px; padding: 0 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: background 0.15s, color 0.15s; }
.btn-primary { background: var(--primary); color: var(--primary-on); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-ghost { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--border); color: var(--text-1); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #15803d; }
.btn-success:disabled { background: var(--text-3); cursor: not-allowed; }

@media (max-width: 768px) {
  .field-grid { grid-template-columns: 1fr; gap: 16px; }
}
</style>
