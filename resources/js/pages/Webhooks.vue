<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Webhooks</h1>
        <p class="page-sub">Notify external services when events occur in your CRM</p>
      </div>
      <button class="btn btn-add" @click="openAdd">+ Add Webhook</button>
    </div>

    <div v-if="loading" class="empty-state">Loading…</div>

    <template v-else>
      <div v-if="!webhooks.length" class="empty-state">
        No webhooks registered yet. Add one to start receiving event notifications.
      </div>

      <div v-for="wh in webhooks" :key="wh.id" class="card">
        <div class="card-row">
          <div class="card-left">
            <div class="wh-name">{{ wh.name }}</div>
            <div class="wh-url">{{ wh.url }}</div>
            <div class="wh-events">
              <span v-for="ev in wh.events" :key="ev" class="event-badge">{{ ev }}</span>
              <span v-if="wh.format === 'slack'" class="format-badge slack">Slack</span>
            </div>
          </div>
          <div class="card-right">
            <span class="status-badge" :class="wh.active ? 'active' : 'inactive'">
              {{ wh.active ? 'Active' : 'Paused' }}
            </span>
            <button class="btn btn-test" @click="testWebhook(wh)" :disabled="testing === wh.id">
              {{ testing === wh.id ? 'Sending…' : 'Test' }}
            </button>
            <button class="btn btn-edit-sm" @click="openEdit(wh)">Edit</button>
            <button class="btn btn-delete" @click="remove(wh.id)">Delete</button>
          </div>
        </div>
        <div v-if="testResult[wh.id]" class="test-result" :class="testResult[wh.id].ok ? 'ok' : 'fail'">
          {{ testResult[wh.id].msg }}
        </div>
      </div>
    </template>

    <!-- Add / Edit Modal -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal">
        <div class="modal-title">{{ editId ? 'Edit Webhook' : 'Add Webhook' }}</div>

        <div class="form-group">
          <label class="form-label">Name</label>
          <input v-model="form.name" class="form-input" placeholder="e.g. Slack notifications" />
        </div>
        <div class="form-group">
          <label class="form-label">URL</label>
          <input v-model="form.url" class="form-input" placeholder="https://hooks.example.com/…" />
        </div>
        <div class="form-group">
          <label class="form-label">Events</label>
          <div class="events-list">
            <label v-for="ev in availableEvents" :key="ev" class="event-check">
              <input type="checkbox" :value="ev" v-model="form.events" />
              <span>{{ ev }}</span>
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Format</label>
          <div class="format-options">
            <label class="event-check">
              <input type="radio" value="generic" v-model="form.format" />
              <span><strong>Generic</strong> — sends raw JSON payload</span>
            </label>
            <label class="event-check">
              <input type="radio" value="slack" v-model="form.format" />
              <span><strong>Slack</strong> — paste your Slack Incoming Webhook URL; sends a plain text message</span>
            </label>
          </div>
        </div>
        <div class="form-group" v-if="form.format === 'generic'">
          <label class="form-label">Secret (optional — for HMAC signature)</label>
          <input v-model="form.secret" class="form-input" placeholder="Leave blank to skip signing" />
        </div>
        <div class="form-group">
          <label class="event-check">
            <input type="checkbox" v-model="form.active" />
            <span>Active</span>
          </label>
        </div>

        <div v-if="formError" class="form-error">{{ formError }}</div>

        <div class="modal-actions">
          <button class="btn btn-cancel" @click="showForm = false">Cancel</button>
          <button class="btn btn-save" @click="save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save Webhook' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api.js';

const loading        = ref(true);
const webhooks       = ref([]);
const availableEvents = ref([]);
const showForm       = ref(false);
const saving         = ref(false);
const formError      = ref('');
const editId         = ref(null);
const testing        = ref(null);
const testResult     = ref({});

const form = ref(blankForm());

function blankForm() {
  return { name: '', url: '', events: [], secret: '', active: true, format: 'generic' };
}

function openAdd() {
  editId.value  = null;
  form.value    = blankForm();
  formError.value = '';
  showForm.value = true;
}

function openEdit(wh) {
  editId.value = wh.id;
  form.value   = { name: wh.name, url: wh.url, events: [...wh.events], secret: wh.secret ?? '', active: wh.active, format: wh.format ?? 'generic' };
  formError.value = '';
  showForm.value = true;
}

async function save() {
  if (!form.value.name.trim() || !form.value.url.trim() || !form.value.events.length) {
    formError.value = 'Name, URL and at least one event are required.';
    return;
  }
  saving.value    = true;
  formError.value = '';
  try {
    const payload = { ...form.value, secret: form.value.secret || null };
    if (editId.value) {
      const { data } = await api.put(`/v1/webhooks/${editId.value}`, payload);
      const idx = webhooks.value.findIndex(w => w.id === editId.value);
      if (idx !== -1) webhooks.value[idx] = data.data;
    } else {
      const { data } = await api.post('/v1/webhooks', payload);
      webhooks.value.push(data.data);
    }
    showForm.value = false;
  } catch (e) {
    formError.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ') || 'Failed to save.';
  } finally {
    saving.value = false;
  }
}

async function remove(id) {
  if (!confirm('Delete this webhook?')) return;
  await api.delete(`/v1/webhooks/${id}`);
  webhooks.value = webhooks.value.filter(w => w.id !== id);
}

async function testWebhook(wh) {
  testing.value = wh.id;
  testResult.value[wh.id] = null;
  try {
    await api.post(`/v1/webhooks/${wh.id}/test`);
    testResult.value = { ...testResult.value, [wh.id]: { ok: true, msg: 'Test payload sent successfully.' } };
  } catch {
    testResult.value = { ...testResult.value, [wh.id]: { ok: false, msg: 'Failed to reach URL — check it is publicly accessible.' } };
  } finally {
    testing.value = null;
  }
}

onMounted(async () => {
  const [whRes, evRes] = await Promise.all([
    api.get('/v1/webhooks'),
    api.get('/v1/webhooks/events'),
  ]);
  webhooks.value       = whRes.data.data;
  availableEvents.value = evRes.data.data;
  loading.value        = false;
});
</script>

<style scoped>
.page { max-width: 900px; margin: 0 auto; padding: 24px 28px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 4px; }
.page-sub { font-size: 13px; color: #64748b; margin: 0; }
.btn { height: 36px; padding: 0 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-add { background: #3b82f6; color: white; }
.btn-add:hover { background: #2563eb; }
.btn-test { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.btn-test:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-edit-sm { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.btn-save { background: #22c55e; color: white; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px 24px; margin-bottom: 12px; }
.card-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.card-left { flex: 1; min-width: 0; }
.card-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wh-name { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.wh-url { font-size: 12px; color: #64748b; word-break: break-all; margin-bottom: 8px; }
.wh-events { display: flex; gap: 6px; flex-wrap: wrap; }
.event-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #eff6ff; color: #3b82f6; }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.status-badge.active { background: #f0fdf4; color: #16a34a; }
.status-badge.inactive { background: #f8fafc; color: #94a3b8; }
.test-result { margin-top: 10px; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.test-result.ok { background: #f0fdf4; color: #16a34a; }
.test-result.fail { background: #fef2f2; color: #dc2626; }
.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; font-size: 14px; font-style: italic; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: white; border-radius: 12px; padding: 28px 32px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 6px; }
.form-input { width: 100%; padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b; box-sizing: border-box; }
.form-input:focus { outline: none; border-color: #3b82f6; }
.events-list { display: flex; flex-direction: column; gap: 8px; }
.event-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; cursor: pointer; }
.event-check input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; }
.event-check input[type="radio"] { width: 15px; height: 15px; cursor: pointer; }
.format-options { display: flex; flex-direction: column; gap: 10px; }
.format-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.format-badge.slack { background: #4a154b; color: white; }
.form-error { background: #fef2f2; color: #dc2626; border-radius: 6px; padding: 8px 12px; font-size: 12px; margin-bottom: 14px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .card-row { flex-direction: column; }
  .card-right { justify-content: flex-start; }
  .modal { padding: 20px 16px; }
}
</style>
