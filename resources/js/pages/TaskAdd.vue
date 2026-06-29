<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Add To-Do</h1>
      <p class="page-subtitle">{{ contact ? contact.name : 'Schedule a to-do for this contact' }}</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>
        <div class="company-chip"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:5px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>{{ contact?.name }}</div>
        <div class="form-row">
          <div class="form-group">
            <label>Task <span class="req">*</span></label>
            <select v-model="form.task_id" required>
              <option value="">Select task</option>
              <option v-for="t in lookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>User</label>
            <select v-model="form.user_id">
              <option value="">Select user</option>
              <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>To Do Date <span class="req">*</span></label>
            <input type="date" v-model="form.todo_date" required>
          </div>
          <div class="form-group">
            <label>Date Created</label>
            <input type="date" v-model="form.date_created">
          </div>
        </div>
        <div class="section-divider">Update Company Status</div>
        <div class="form-row">
          <div class="form-group">
            <label>New Status</label>
            <select v-model="form.status_id">
              <option value="">— No change —</option>
              <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>New Type</label>
            <select v-model="form.type_id">
              <option value="">— No change —</option>
              <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Remark</label>
          <textarea v-model="form.todo_remark" rows="4" placeholder="Enter remark or notes…"></textarea>
        </div>
        <div class="btn-row">
          <router-link :to="`/contacts/${contactId}`" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Add To-Do' }}
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

const route = useRoute();
const router = useRouter();
const contactId = route.params.id;

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const contact = ref(null);
const lookups = ref({ tasks: [], users: [], statuses: [], types: [] });

const form = ref({
  task_id: '', user_id: '',
  todo_date: new Date().toISOString().slice(0, 10),
  date_created: new Date().toISOString().slice(0, 10),
  todo_remark: '', status_id: '', type_id: '',
});

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    await api.post(`/v1/contacts/${contactId}/todos`, {
      task_id:      form.value.task_id      || null,
      user_id:      form.value.user_id      || null,
      todo_date:    form.value.todo_date,
      date_created: form.value.date_created || null,
      todo_remark:  form.value.todo_remark  || null,
    });
    if (form.value.status_id || form.value.type_id) {
      const patch = {};
      if (form.value.status_id) patch.status_id = form.value.status_id;
      if (form.value.type_id)   patch.type_id   = form.value.type_id;
      await api.put(`/v1/contacts/${contactId}`, patch);
    }
    router.push(`/contacts/${contactId}`);
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
  const [contactRes, lookupRes] = await Promise.all([
    api.get(`/v1/contacts/${contactId}`),
    api.get('/v1/lookups'),
  ]);
  contact.value = contactRes.data.data;
  lookups.value = lookupRes.data;
  loading.value = false;
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }
.company-chip { background: var(--primary-soft); color: var(--primary-text); border-radius: 6px; padding: 8px 14px; font-size: 14px; font-weight: 700; display: inline-block; margin-bottom: 20px; }
.section-divider { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2); padding: 10px 0 6px; border-top: 1px solid var(--border); margin: 4px 0 12px; }
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
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
}
</style>
