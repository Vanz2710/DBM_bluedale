<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Edit Follow-Up</h1>
      <p class="page-subtitle">Update follow-up date, action type, or note</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>

        <!-- Read-only context info -->
        <div class="context-box">
          <div class="context-item">
            <span class="context-label">Company</span>
            <span class="context-val">{{ data.contact_name ?? '—' }}</span>
          </div>
          <div class="context-item">
            <span class="context-label">To-Do Date</span>
            <span class="context-val">{{ data.todo_date ?? '—' }}</span>
          </div>
          <div class="context-item">
            <span class="context-label">Task</span>
            <span class="context-val">{{ data.task ?? '—' }}</span>
          </div>
          <div class="context-item">
            <span class="context-label">Assigned To</span>
            <span class="context-val">{{ data.user ?? '—' }}</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Follow-Up Date <span class="req">*</span></label>
            <input type="date" v-model="form.followup_date" required>
          </div>
          <div class="form-group">
            <label>Action Type</label>
            <select v-model="form.action_type">
              <option value="">— Select type —</option>
              <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Note</label>
          <textarea v-model="form.note" rows="5" placeholder="Enter follow-up note or outcome…"></textarea>
        </div>

        <div class="btn-row">
          <router-link to="/followups" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];

const route  = useRoute();
const router = useRouter();
const id     = route.params.id;

const loading = ref(true);
const saving  = ref(false);
const error   = ref('');
const data    = ref({});

const form = ref({
  followup_date: '',
  action_type:   '',
  note:          '',
});

async function submit() {
  saving.value = true;
  error.value  = '';
  try {
    await api.put(`/v1/followups/${id}`, {
      followup_date: form.value.followup_date,
      action_type:   form.value.action_type   || null,
      note:          form.value.note          || null,
    });
    router.push('/followups');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get(`/v1/followups/${id}`);
    data.value = res.data.data;
    form.value.followup_date = data.value.followup_date ?? '';
    form.value.action_type   = data.value.action_type   ?? '';
    form.value.note          = data.value.note          ?? '';
  } catch {
    error.value = 'Follow-up not found.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-header   { margin-bottom: 24px; }
.page-title    { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }

.context-box {
  background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: var(--radius-sm);
  padding: 14px 16px; margin-bottom: 20px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;
}
.context-item { display: flex; flex-direction: column; gap: 2px; }
.context-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.context-val { font-size: 13px; font-weight: 600; color: var(--text-1); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
}
.form-group textarea { height: 120px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.12);
}
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save   { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
  .context-box { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
