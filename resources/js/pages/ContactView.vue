<template>
  <div class="page">
    <div class="page-header">
      <router-link to="/list" class="back-link">← Back to Daily List</router-link>
      <div class="header-actions" v-if="contact">
        <router-link :to="`/contacts/${id}/task/add`" class="btn btn-task">+ Add Task</router-link>
        <router-link :to="`/contacts/${id}/edit`" class="btn btn-edit">✏️ Edit</router-link>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else-if="contact">
      <div class="profile-header">
        <h1>{{ contact.name }}</h1>
        <div class="profile-meta">
          <span v-if="contact.status" class="meta-badge">{{ contact.status.name }}</span>
          <span v-if="contact.type" class="meta-badge">{{ contact.type.name }}</span>
          <span v-if="contact.industry" class="meta-badge">{{ contact.industry.name }}</span>
          <span v-if="contact.area" class="meta-badge">{{ contact.area.name }}</span>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Company Details</div>
        <div class="info-grid">
          <div class="info-field">
            <span class="info-label">Industry</span>
            <span class="info-value" :class="{ muted: !contact.industry }">{{ contact.industry?.name ?? 'Not specified' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Category</span>
            <span class="info-value" :class="{ muted: !contact.category }">{{ contact.category?.name ?? 'Not specified' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Status</span>
            <span class="info-value" :class="{ muted: !contact.status }">{{ contact.status?.name ?? 'Not set' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Type</span>
            <span class="info-value" :class="{ muted: !contact.type }">{{ contact.type?.name ?? 'Not specified' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Area</span>
            <span class="info-value" :class="{ muted: !contact.area }">{{ contact.area?.name ?? 'Not specified' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Assigned To</span>
            <span class="info-value" :class="{ muted: !contact.user }">{{ contact.user?.name ?? 'Unassigned' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Date Added</span>
            <span class="info-value">{{ fmtDate(contact.created_at) }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Lead Source</span>
            <span v-if="contact.lead_source" :class="['source-badge', `source-${contact.lead_source}`]">{{ sourceLabel(contact.lead_source) }}</span>
            <span v-else class="info-value muted">—</span>
          </div>
        </div>
        <div v-if="contact.address" class="info-full">
          <div class="info-label">Address</div>
          <div class="info-value">{{ contact.address }}</div>
        </div>
        <div v-if="contact.remark" class="info-full">
          <div class="info-label">Remark</div>
          <div class="info-value" style="white-space:pre-line">{{ contact.remark }}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Persons in Charge ({{ contact.incharges?.length ?? 0 }})</div>
        <p v-if="!contact.incharges?.length" class="no-data">No persons in charge recorded.</p>
        <table v-else>
          <thead>
            <tr><th>Name</th><th>Email</th><th>Mobile</th><th>Office</th></tr>
          </thead>
          <tbody>
            <tr v-for="pic in contact.incharges" :key="pic.id">
              <td><strong>{{ pic.name }}</strong></td>
              <td>
                <a v-if="pic.email" :href="`mailto:${pic.email}`" class="contact-email">{{ pic.email }}</a>
                <span v-else class="muted">—</span>
              </td>
              <td>{{ pic.phone_mobile || '—' }}</td>
              <td>{{ pic.phone_office || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card">
        <div class="card-title">Task History ({{ contact.todos?.length ?? 0 }})</div>
        <p v-if="!contact.todos?.length" class="no-data">No tasks logged yet.</p>
        <table v-else>
          <thead>
            <tr><th>To Do Date</th><th>Date Created</th><th>Task</th><th>User</th><th>Remark</th></tr>
          </thead>
          <tbody>
            <tr v-for="td in contact.todos" :key="td.id">
              <td class="todo-date">{{ fmtDate(td.todo_date) }}</td>
              <td class="todo-date">{{ fmtDate(td.date_created) }}</td>
              <td>
                <span v-if="td.task" class="task-badge">{{ td.task.name }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td>{{ td.user?.name ?? '—' }}</td>
              <td style="white-space:pre-line">{{ td.todo_remark || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card">
        <div class="card-title-row">
          <div class="card-title">Email History ({{ emails.length }})</div>
          <button class="btn btn-email" @click="toggleEmailForm">
            {{ showEmailForm ? 'Cancel' : '+ Log Email' }}
          </button>
        </div>

        <div v-if="showEmailForm" class="email-form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Direction</label>
              <select v-model="newEmail.direction" class="form-select">
                <option value="sent">Sent</option>
                <option value="received">Received</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date &amp; Time</label>
              <input type="datetime-local" v-model="newEmail.emailed_at" class="form-input" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Subject</label>
            <input type="text" v-model="newEmail.subject" class="form-input" placeholder="Email subject..." />
          </div>
          <div class="form-group">
            <label class="form-label">Body (optional)</label>
            <textarea v-model="newEmail.body" class="form-textarea" rows="4" placeholder="Email body..."></textarea>
          </div>
          <div class="form-actions">
            <button class="btn btn-save" @click="logEmail" :disabled="savingEmail">
              {{ savingEmail ? 'Saving...' : 'Save Email' }}
            </button>
          </div>
        </div>

        <p v-if="!emails.length && !showEmailForm" class="no-data">No emails logged yet.</p>
        <div v-for="email in emails" :key="email.id" class="email-item">
          <div class="email-header">
            <span class="dir-badge" :class="email.direction">
              {{ email.direction === 'sent' ? '↑ Sent' : '↓ Received' }}
            </span>
            <span class="email-subject">{{ email.subject }}</span>
            <span class="email-meta">{{ fmtDate(email.emailed_at) }} · {{ email.user?.name ?? 'Unknown' }}</span>
            <button class="btn-delete-email" @click="deleteEmail(email.id)" title="Delete">✕</button>
          </div>
          <div v-if="email.body" class="email-body">{{ email.body }}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-title-row">
          <div class="card-title">Call Log ({{ calls.length }})</div>
          <button class="btn btn-call" @click="toggleCallForm">
            {{ showCallForm ? 'Cancel' : '+ Log Call' }}
          </button>
        </div>

        <div v-if="showCallForm" class="email-form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Direction</label>
              <select v-model="newCall.direction" class="form-select">
                <option value="outbound">Outbound</option>
                <option value="inbound">Inbound</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date &amp; Time</label>
              <input type="datetime-local" v-model="newCall.called_at" class="form-input" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Duration (minutes, optional)</label>
            <input type="number" v-model.number="newCall.duration" class="form-input" placeholder="e.g. 10" min="1" max="9999" />
          </div>
          <div class="form-group">
            <label class="form-label">Notes (optional)</label>
            <textarea v-model="newCall.notes" class="form-textarea" rows="4" placeholder="What was discussed..."></textarea>
          </div>
          <div class="form-actions">
            <button class="btn btn-save" @click="logCall" :disabled="savingCall">
              {{ savingCall ? 'Saving...' : 'Save Call' }}
            </button>
          </div>
        </div>

        <p v-if="!calls.length && !showCallForm" class="no-data">No calls logged yet.</p>
        <div v-for="call in calls" :key="call.id" class="email-item">
          <div class="email-header">
            <span class="dir-badge" :class="call.direction">
              {{ call.direction === 'outbound' ? '↗ Outbound' : '↙ Inbound' }}
            </span>
            <span class="email-subject">{{ fmtDate(call.called_at) }}</span>
            <span class="email-meta">
              {{ call.duration ? call.duration + ' min' : 'Duration not recorded' }} · {{ call.user?.name ?? 'Unknown' }}
            </span>
            <button class="btn-delete-email" @click="deleteCall(call.id)" title="Delete">✕</button>
          </div>
          <div v-if="call.notes" class="email-body">{{ call.notes }}</div>
        </div>
      </div>
    </template>

    <div v-else class="loading-msg">Contact not found.</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const route = useRoute();
const id = route.params.id;
const loading = ref(true);
const contact = ref(null);

const emails = ref([]);
const showEmailForm = ref(false);
const savingEmail = ref(false);
const newEmail = ref(defaultEmailForm());

const calls = ref([]);
const showCallForm = ref(false);
const savingCall = ref(false);
const newCall = ref(defaultCallForm());

function defaultEmailForm() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  return { direction: 'sent', subject: '', body: '', emailed_at: now.toISOString().slice(0, 16) };
}

function defaultCallForm() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  return { direction: 'outbound', duration: '', notes: '', called_at: now.toISOString().slice(0, 16) };
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

const SOURCE_LABELS = {
  manual: 'Manual Entry', web_form: 'Web Form', whatsapp: 'WhatsApp',
  phone_call: 'Phone Call', referral: 'Referral', social_media: 'Social Media',
  email_campaign: 'Email Campaign', walk_in: 'Walk-in', other: 'Other',
};
function sourceLabel(src) { return SOURCE_LABELS[src] ?? src; }

function toggleEmailForm() {
  showEmailForm.value = !showEmailForm.value;
  if (showEmailForm.value) newEmail.value = defaultEmailForm();
}

async function logEmail() {
  if (!newEmail.value.subject.trim()) return;
  savingEmail.value = true;
  try {
    const { data } = await api.post(`/v1/contacts/${id}/emails`, newEmail.value);
    emails.value.unshift(data.data);
    showEmailForm.value = false;
    newEmail.value = defaultEmailForm();
  } finally {
    savingEmail.value = false;
  }
}

async function deleteEmail(emailId) {
  if (!confirm('Delete this email log?')) return;
  await api.delete(`/v1/contacts/${id}/emails/${emailId}`);
  emails.value = emails.value.filter(e => e.id !== emailId);
}

function toggleCallForm() {
  showCallForm.value = !showCallForm.value;
  if (showCallForm.value) newCall.value = defaultCallForm();
}

async function logCall() {
  savingCall.value = true;
  try {
    const payload = { ...newCall.value };
    if (!payload.duration) delete payload.duration;
    const { data } = await api.post(`/v1/contacts/${id}/calls`, payload);
    calls.value.unshift(data.data);
    showCallForm.value = false;
    newCall.value = defaultCallForm();
  } finally {
    savingCall.value = false;
  }
}

async function deleteCall(callId) {
  if (!confirm('Delete this call log?')) return;
  await api.delete(`/v1/contacts/${id}/calls/${callId}`);
  calls.value = calls.value.filter(c => c.id !== callId);
}

onMounted(async () => {
  const [contactRes, emailsRes, callsRes] = await Promise.all([
    api.get(`/v1/contacts/${id}`),
    api.get(`/v1/contacts/${id}/emails`),
    api.get(`/v1/contacts/${id}/calls`).catch(() => ({ data: { data: [] } })),
  ]);
  contact.value = contactRes.data.data;
  emails.value = emailsRes.data.data;
  calls.value = callsRes.data.data;
  loading.value = false;
});
</script>

<style scoped>
.page { max-width: 980px; margin: 0 auto; padding: 24px 28px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.back-link { font-size: 13px; font-weight: 600; color: #64748b; text-decoration: none; }
.back-link:hover { color: #3b82f6; }
.header-actions { display: flex; gap: 10px; }
.btn { height: 38px; padding: 0 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-task { background: #22c55e; color: white; }
.btn-edit { background: #f59e0b; color: white; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; }
.profile-header { background: linear-gradient(135deg, #1a2f4a, #22c55e); border-radius: 10px; padding: 28px 32px; margin-bottom: 20px; color: white; }
.profile-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 10px; }
.profile-meta { display: flex; gap: 10px; flex-wrap: wrap; }
.meta-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9); }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 24px 28px; margin-bottom: 16px; }
.card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #64748b; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 32px; }
.info-field { display: flex; flex-direction: column; gap: 4px; }
.info-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; }
.info-value { font-size: 15px; color: #1e293b; font-weight: 500; }
.info-value.muted, .muted { color: #94a3b8; font-weight: 400; font-style: italic; font-size: 14px; }
.info-full { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.source-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.source-manual        { background: #f1f5f9; color: #475569; }
.source-web_form      { background: #eff6ff; color: #1d4ed8; }
.source-whatsapp      { background: #f0fdf4; color: #16a34a; }
.source-phone_call    { background: #faf5ff; color: #7c3aed; }
.source-referral      { background: #fffbeb; color: #b45309; }
.source-social_media  { background: #fdf2f8; color: #be185d; }
.source-email_campaign{ background: #fff7ed; color: #c2410c; }
.source-walk_in       { background: #f0fdfa; color: #0f766e; }
.source-other         { background: #f8fafc; color: #64748b; }
table { width: 100%; border-collapse: collapse; }
thead th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; padding: 11px 14px; border-bottom: 2px solid #e2e8f0; text-align: left; }
tbody td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
.contact-email { color: #3b82f6; text-decoration: none; }
.contact-email:hover { text-decoration: underline; }
.no-data { font-size: 14px; color: #94a3b8; font-style: italic; padding: 8px 0; margin: 0; }
.todo-date { font-size: 12px; font-weight: 700; color: #64748b; white-space: nowrap; }
.task-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #eff6ff; color: #3b82f6; }

.card-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.card-title-row .card-title { margin: 0; padding: 0; border: none; }
.btn-email { height: 34px; padding: 0 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #3b82f6; color: white; border: none; cursor: pointer; }
.btn-email:hover { background: #2563eb; }
.btn-call { height: 34px; padding: 0 14px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #8b5cf6; color: white; border: none; cursor: pointer; }
.btn-call:hover { background: #7c3aed; }
.dir-badge.outbound { background: #f5f3ff; color: #7c3aed; }
.dir-badge.inbound { background: #fef3c7; color: #d97706; }
.email-form { background: #f8fafc; border-radius: 8px; padding: 18px; margin-bottom: 16px; border: 1px solid #e2e8f0; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.form-group:last-child { margin-bottom: 0; }
.form-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; color: #1e293b; background: white; box-sizing: border-box; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; }
.form-textarea { resize: vertical; font-family: inherit; }
.form-actions { display: flex; justify-content: flex-end; margin-top: 14px; }
.btn-save { height: 36px; padding: 0 18px; border-radius: 8px; font-size: 13px; font-weight: 700; background: #22c55e; color: white; border: none; cursor: pointer; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-save:hover:not(:disabled) { background: #16a34a; }
.email-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
.email-item:last-child { margin-bottom: 0; }
.email-header { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.dir-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.dir-badge.sent { background: #eff6ff; color: #3b82f6; }
.dir-badge.received { background: #f0fdf4; color: #16a34a; }
.email-subject { font-size: 14px; font-weight: 600; color: #1e293b; flex: 1; }
.email-meta { font-size: 11px; color: #94a3b8; white-space: nowrap; }
.btn-delete-email { margin-left: auto; background: none; border: none; color: #94a3b8; font-size: 13px; cursor: pointer; padding: 2px 6px; border-radius: 4px; }
.btn-delete-email:hover { color: #ef4444; background: #fef2f2; }
.email-body { margin-top: 10px; font-size: 13px; color: #475569; white-space: pre-line; padding-top: 10px; border-top: 1px solid #f1f5f9; line-height: 1.5; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
  .card { padding: 16px 14px; overflow-x: auto; }
  .info-grid { grid-template-columns: 1fr; gap: 14px; }
  .profile-header { padding: 20px; }
  .profile-header h1 { font-size: 18px; }
  table { min-width: 500px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .header-actions { flex-wrap: wrap; }
}
</style>
