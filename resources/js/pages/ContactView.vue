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

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(async () => {
  const { data } = await api.get(`/v1/contacts/${id}`);
  contact.value = data.data;
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
