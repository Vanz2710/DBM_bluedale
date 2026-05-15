<template>
  <div class="page">
    <div class="page-banner">
      <h1>Edit Follow-Up</h1>
      <p>Update follow-up date, action type, or note</p>
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
.page { padding: 24px 28px; max-width: 760px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #e11d48);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: #94a3b8; }

.context-box {
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 14px 16px; margin-bottom: 20px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;
}
.context-item { display: flex; flex-direction: column; gap: 2px; }
.context-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.context-val { font-size: 13px; font-weight: 600; color: #1e293b; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; color: #1e293b; outline: none;
  background: white; box-sizing: border-box;
}
.form-group textarea { height: 120px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: #e11d48; box-shadow: 0 0 0 3px rgba(225,29,72,0.1);
}
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.btn-save   { flex: 1; background: #e11d48; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
  .context-box { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
