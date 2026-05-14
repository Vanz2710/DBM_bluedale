<template>
  <div class="page">
    <div class="page-banner green">
      <h1>Add Task</h1>
      <p v-if="contact">{{ contact.name }}</p>
    </div>
    <div class="card">
      <div v-if="loading" class="loading-msg">Loading…</div>
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>
        <div class="company-chip">🏢 {{ contact?.name }}</div>
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
          <router-link :to="`/contacts/${contactId}`" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Add Task' }}
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
.page { padding: 24px 28px; max-width: 760px; }
.page-banner { border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white; }
.page-banner.green { background: linear-gradient(135deg, #1a2f4a, #22c55e); }
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: #94a3b8; }
.company-chip { background: #eff6ff; color: #1d4ed8; border-radius: 6px; padding: 8px 14px; font-size: 14px; font-weight: 700; display: inline-block; margin-bottom: 20px; }
.section-divider { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #64748b; padding: 10px 0 6px; border-top: 1px solid #e2e8f0; margin: 4px 0 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; color: #1e293b; outline: none;
  background: white; box-sizing: border-box;
}
.form-group textarea { height: 100px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.1);
}
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.btn-save { flex: 1; background: #22c55e; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }
</style>
