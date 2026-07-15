<template>
  <div class="page">
    <div class="page-banner orange">
      <h1>Add Reminder</h1>
      <p>Add a new task or follow-up reminder</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>
        <div class="form-group">
          <label>Company <span class="req">*</span></label>
          <select v-model="form.contact_id" required>
            <option value="">Select company</option>
            <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
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
          <textarea v-model="form.todo_remark" rows="4" placeholder="Enter task remark or notes…"></textarea>
        </div>
        <div class="btn-row">
          <router-link to="/todos" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Add Reminder' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const contacts = ref([]);
const lookups = ref({ tasks: [], users: [], statuses: [], types: [] });

const form = ref({
  contact_id: '', task_id: '', user_id: '',
  todo_date: new Date().toISOString().slice(0, 10),
  date_created: new Date().toISOString().slice(0, 10),
  todo_remark: '', status_id: '', type_id: '',
});

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    await api.post('/v1/todos', {
      contact_id:   form.value.contact_id,
      task_id:      form.value.task_id      || null,
      user_id:      form.value.user_id      || null,
      todo_date:    form.value.todo_date,
      date_created: form.value.date_created || null,
      todo_remark:  form.value.todo_remark  || null,
    });
    if (form.value.contact_id && (form.value.status_id || form.value.type_id)) {
      const patch = {};
      if (form.value.status_id) patch.status_id = form.value.status_id;
      if (form.value.type_id)   patch.type_id   = form.value.type_id;
      await api.put(`/v1/contacts/${form.value.contact_id}`, patch);
    }
    router.push('/todos');
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
    const [contactsRes, lookupRes] = await Promise.all([
      api.get('/v1/contacts', { params: { per_page: 1000 } }),
      api.get('/v1/lookups'),
    ]);
    contacts.value = contactsRes.data.data;
    lookups.value = lookupRes.data;
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
.page-banner.orange { /* colour unified — kept for template compat */ }
.page-banner h1 { font-size: 26px; font-weight: 800; margin: 0 0 5px; letter-spacing: -0.4px; }
.page-banner p { font-size: 13px; color: rgba(237,233,254,0.82); margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }
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
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
}
</style>
