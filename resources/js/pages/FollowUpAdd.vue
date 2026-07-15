<template>
  <div class="page">
    <div class="page-banner">
      <h1>Add Follow-Up</h1>
      <p>Log a follow-up action linked to a to-do</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>

        <div class="form-group">
          <label>Company <span class="req">*</span></label>
          <select v-model="contactId" @change="onContactChange" required>
            <option value="">Select company</option>
            <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div class="form-group">
          <label>To-Do <span class="req">*</span></label>
          <select v-model="form.todo_id" required :disabled="!contactId || todosLoading">
            <option value="">{{ contactId ? (todosLoading ? 'Loading…' : 'Select to-do') : 'Select company first' }}</option>
            <option v-for="t in todos" :key="t.id" :value="t.id">
              {{ t.task?.name ?? 'Task' }} — {{ t.todo_date }} {{ t.todo_remark ? '— ' + t.todo_remark.slice(0, 40) : '' }}
            </option>
          </select>
          <span v-if="contactId && !todosLoading && todos.length === 0" class="hint">No to-dos found for this company.</span>
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
          <textarea v-model="form.note" rows="4" placeholder="Enter follow-up note or outcome…"></textarea>
        </div>

        <div class="btn-row">
          <router-link to="/followups" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Add Follow-Up' }}
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

const route       = useRoute();
const router      = useRouter();
const loading     = ref(true);
const saving      = ref(false);
const todosLoading = ref(false);
const error       = ref('');
const contacts    = ref([]);
const todos       = ref([]);
const contactId   = ref('');

const form = ref({
  todo_id:       '',
  followup_date: new Date().toISOString().slice(0, 10),
  action_type:   '',
  note:          '',
});

async function onContactChange() {
  form.value.todo_id = '';
  todos.value = [];
  if (!contactId.value) return;
  todosLoading.value = true;
  try {
    const res = await api.get(`/v1/contacts/${contactId.value}/todos`);
    todos.value = res.data.data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load to-dos for this company.';
  } finally {
    todosLoading.value = false;
  }
}

async function submit() {
  saving.value = true;
  error.value  = '';
  try {
    await api.post('/v1/followups', {
      todo_id:       form.value.todo_id,
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
    const res = await api.get('/v1/contacts', { params: { per_page: 1000 } });
    contacts.value = res.data.data;

    // If launched from "Log Follow-Up" on a ToDo row, pre-select the parent.
    const prefillTodoId = route.query.todo_id;
    if (prefillTodoId) {
      try {
        const todoRes = await api.get(`/v1/todos/${prefillTodoId}`);
        const todo    = todoRes.data.data;
        if (todo?.contact_id) {
          contactId.value = todo.contact_id;
          await onContactChange();
          form.value.todo_id = Number(prefillTodoId);
        }
      } catch (_) { /* fall back to manual selection */ }
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load form data. Please try again.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-banner {
  border-radius: var(--radius-lg); padding: 22px 28px; margin-bottom: 20px; color: white;
  background:
    radial-gradient(900px 200px at 90% -20%, rgba(96,165,250,0.5), transparent 55%),
    linear-gradient(118deg, #0f2456 0%, #1d4ed8 52%, #1e40af 100%);
  box-shadow: 0 12px 32px -14px rgba(15,36,86,0.65);
}
.page-banner h1 { font-size: 26px; font-weight: 800; margin: 0 0 5px; letter-spacing: -0.4px; }
.page-banner p  { font-size: 13px; color: rgba(237,233,254,0.82); margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
}
.form-group textarea { height: 100px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.12);
}
.form-group select:disabled { background: var(--app-bg); color: var(--text-3); cursor: not-allowed; }
.hint { font-size: 11px; color: var(--warning); margin-top: 4px; display: block; }
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save   { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
