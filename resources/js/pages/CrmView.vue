<template>
  <div class="page">
    <router-link to="/crm" class="back-link">← Back to CRM Dashboard</router-link>

    <div v-if="loading" class="loading-msg">Loading…</div>

    <template v-else-if="contact">
      <!-- Profile header -->
      <div class="profile-header">
        <h1>{{ contact.name }}</h1>
        <div class="profile-meta">
          <span v-if="contact.status" class="meta-badge">{{ contact.status.name }}</span>
          <span v-if="contact.industry" class="meta-badge">{{ contact.industry.name }}</span>
          <span v-if="contact.category" class="meta-badge">{{ contact.category.name }}</span>
        </div>
      </div>

      <!-- Details -->
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
            <span v-if="contact.status" class="status-badge" :class="statusClass(contact.status.name)">{{ contact.status.name }}</span>
            <span v-else class="muted">Not set</span>
          </div>
          <div class="info-field">
            <span class="info-label">Client Type</span>
            <span class="info-value" :class="{ muted: !contact.type }">{{ contact.type?.name ?? 'Not specified' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Assigned To</span>
            <span class="info-value" :class="{ muted: !contact.user }">{{ contact.user?.name ?? 'Unassigned' }}</span>
          </div>
          <div class="info-field">
            <span class="info-label">Added On</span>
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

      <!-- PICs -->
      <div class="card">
        <div class="card-title">Persons in Charge ({{ contact.incharges?.length ?? 0 }})</div>
        <p v-if="!contact.incharges?.length" class="no-data">No persons in charge recorded.</p>
        <table v-else>
          <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Office</th></tr></thead>
          <tbody>
            <tr v-for="pic in contact.incharges" :key="pic.id">
              <td><strong>{{ pic.name }}</strong></td>
              <td><a v-if="pic.email" :href="`mailto:${pic.email}`" class="contact-email">{{ pic.email }}</a><span v-else style="color:#cbd5e1">—</span></td>
              <td>{{ pic.phone_mobile || '—' }}</td>
              <td>{{ pic.phone_office || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Task history -->
      <div class="card">
        <div class="card-title">Task History ({{ contact.todos?.length ?? 0 }})</div>
        <p v-if="!contact.todos?.length" class="no-data">No tasks or history logged yet.</p>
        <table v-else>
          <thead><tr><th>Date</th><th>Task</th><th>Remark</th></tr></thead>
          <tbody>
            <tr v-for="td in contact.todos" :key="td.id">
              <td class="todo-date">{{ fmtDate(td.todo_date) }}</td>
              <td><span v-if="td.task" class="todo-task">{{ td.task.name }}</span><span v-else>—</span></td>
              <td style="white-space:pre-line">{{ td.todo_remark }}</td>
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
import axios from '../api.js';

const route   = useRoute();
const loading = ref(true);
const contact = ref(null);

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusClass(name) {
  const sl = (name ?? '').toLowerCase();
  if (sl.includes('existing')) return 'status-existing';
  if (sl.includes('potential')) return 'status-potential';
  if (sl.includes('raw')) return 'status-raw';
  return 'status-default';
}

onMounted(async () => {
  const { data } = await axios.get(`/v1/contacts/${route.params.id}`);
  contact.value = data.data;
  loading.value = false;
});
</script>

<style scoped>
.page { max-width: 980px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; }
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#64748b; text-decoration:none; margin-bottom:20px; }
.back-link:hover { color:#3b82f6; }

.profile-header { background:linear-gradient(135deg,#1a2f4a,#3498db); border-radius:10px; padding:28px 32px; margin-bottom:20px; color:white; }
.profile-header h1 { font-size:22px; font-weight:700; margin:0 0 6px; }
.profile-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
.meta-badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.9); }

.card { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); padding:24px 28px; margin-bottom:16px; }
.card-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#64748b; margin:0 0 20px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px 32px; }
.info-field { display:flex; flex-direction:column; gap:4px; }
.info-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; }
.info-value { font-size:15px; color:#1e293b; font-weight:500; }
.info-value.muted, .muted { color:#94a3b8; font-weight:400; font-style:italic; font-size:14px; }
.info-full { margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9; }

.status-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
.status-raw      { background:#f1f5f9; color:#64748b; }
.status-existing { background:#dcfce7; color:#15803d; }
.status-potential{ background:#fff7ed; color:#c2410c; }
.status-default  { background:#dbeafe; color:#1d4ed8; }

table { width:100%; border-collapse:collapse; }
thead th { background:#f8fafc; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; padding:11px 14px; border-bottom:2px solid #e2e8f0; text-align:left; }
tbody td { padding:12px 14px; border-bottom:1px solid #f1f5f9; font-size:14px; color:#374151; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover { background:#f8fafc; }
.contact-email { color:#3498db; text-decoration:none; }
.contact-email:hover { text-decoration:underline; }
.no-data { font-size:14px; color:#94a3b8; font-style:italic; padding:8px 0; margin:0; }
.todo-date { font-size:11px; font-weight:700; color:#64748b; white-space:nowrap; }
.todo-task { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; background:#eff6ff; color:#3b82f6; }
</style>
