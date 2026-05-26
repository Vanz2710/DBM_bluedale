<template>
  <div class="page">
    <div class="top-row">
      <router-link to="/crm" class="back-link">← Back to CRM Dashboard</router-link>
      <button v-if="contact" type="button" class="btn-forecast" @click="openForecastAdd">+ Add Forecast</button>
    </div>

    <LoadingSpinner v-if="loading" />

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
            <span class="info-label">User</span>
            <span class="info-value" :class="{ muted: !contact.user }">{{ contact.user?.name ?? '—' }}</span>
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

      <div class="card">
        <div class="card-title">Forecast History ({{ contact.forecasts?.length ?? 0 }})</div>
        <p v-if="!contact.forecasts?.length" class="no-data">No forecasts recorded yet.</p>
        <table v-else>
          <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Amount</th><th>Result</th><th>Owner</th><th></th></tr></thead>
          <tbody>
            <tr v-for="forecast in contact.forecasts" :key="forecast.id">
              <td class="todo-date">{{ fmtDate(forecast.forecast_date) }}</td>
              <td>{{ forecast.product?.name ?? '-' }}</td>
              <td><span class="todo-task">{{ forecast.forecast_type?.name ?? '-' }}</span></td>
              <td class="forecast-amount">{{ fmtCurrency(forecast.amount) }}</td>
              <td><span class="result-badge" :class="resultClass(forecast.result?.name)">{{ forecast.result?.name ?? 'No Result' }}</span></td>
              <td>{{ forecast.user?.name ?? '-' }}</td>
              <td><button type="button" class="inline-action" @click="openForecastEdit(forecast.id)">Edit</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-else class="loading-msg">Contact not found.</div>

    <ForecastFormModal
      :open="forecastModal.open"
      :mode="forecastModal.mode"
      :forecast-id="forecastModal.forecastId"
      :prefilled-contact="forecastModal.prefilledContact"
      @close="closeForecastModal"
      @saved="onForecastSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import ForecastFormModal from '../components/ForecastFormModal.vue';

const route   = useRoute();
const loading = ref(true);
const contact = ref(null);

const forecastModal = ref({ open: false, mode: 'add', forecastId: null, prefilledContact: null });
function openForecastAdd() {
  forecastModal.value = {
    open: true,
    mode: 'add',
    forecastId: null,
    prefilledContact: contact.value ? { id: contact.value.id, name: contact.value.name } : null,
  };
}
function openForecastEdit(forecastId) {
  forecastModal.value = { open: true, mode: 'edit', forecastId, prefilledContact: null };
}
function closeForecastModal() { forecastModal.value.open = false; }
async function onForecastSaved() {
  closeForecastModal();
  const { data } = await axios.get(`/v1/contacts/${route.params.id}`);
  contact.value = data.data;
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtCurrency(value) {
  const n = Number(value ?? 0);
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function resultClass(name) {
  const key = (name ?? 'No Result').toLowerCase().replace(/\s+/g, '-');
  return `result-${key}`;
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
.loading-msg { padding: 60px; text-align: center; color: var(--text-3); }
.top-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; }
.back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--text-2); text-decoration:none; margin-bottom:20px; }
.back-link:hover { color:#3b82f6; }
.top-row .back-link { margin-bottom:0; }
.btn-forecast { height:34px; padding:0 14px; border:none; border-radius:8px; background:#0284c7; color:white; font-size:12px; font-weight:800; text-decoration:none; cursor:pointer; display:inline-flex; align-items:center; }
.btn-forecast:hover { background:#0369a1; }
.inline-action { border:none; cursor:pointer; }

.profile-header { background:linear-gradient(135deg,#1a2f4a,#3498db); border-radius:10px; padding:28px 32px; margin-bottom:20px; color:white; }
.profile-header h1 { font-size:22px; font-weight:700; margin:0 0 6px; }
.profile-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
.meta-badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.9); }

.card { background:var(--surface); border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); padding:24px 28px; margin-bottom:16px; }
.card-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:var(--text-2); margin:0 0 20px; padding-bottom:12px; border-bottom:1px solid var(--border); }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px 32px; }
.info-field { display:flex; flex-direction:column; gap:4px; }
.info-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--text-3); }
.info-value { font-size:15px; color:var(--text-1); font-weight:500; }
.info-value.muted, .muted { color:var(--text-3); font-weight:400; font-style:italic; font-size:14px; }
.info-full { margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }

.status-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; }
.status-raw      { background:#f1f5f9; color:#64748b; }
.status-existing { background:#dcfce7; color:#15803d; }
.status-potential{ background:#fff7ed; color:#c2410c; }
.status-default  { background:#dbeafe; color:#1d4ed8; }

table { width:100%; border-collapse:collapse; }
thead th { background:var(--app-bg); color:var(--text-2); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; padding:11px 14px; border-bottom:2px solid var(--border); text-align:left; }
tbody td { padding:12px 14px; border-bottom:1px solid var(--border); font-size:14px; color:#374151; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover { background:var(--app-bg); }
.contact-email { color:#3498db; text-decoration:none; }
.contact-email:hover { text-decoration:underline; }
.no-data { font-size:14px; color:var(--text-3); font-style:italic; padding:8px 0; margin:0; }
.todo-date { font-size:11px; font-weight:700; color:var(--text-2); white-space:nowrap; }
.todo-task { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; background:#eff6ff; color:#3b82f6; }
.forecast-amount { font-weight:800; color:#0369a1; white-space:nowrap; }
.result-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:8px; font-size:10px; font-weight:800; white-space:nowrap; }
.result-confirmed { background:#dcfce7; color:#15803d; }
.result-pending { background:#fef3c7; color:#b45309; }
.result-rejected { background:#fee2e2; color:#b91c1c; }
.result-no-result { background:#f1f5f9; color:#64748b; }
.inline-action { height:26px; padding:0 8px; border-radius:6px; background:#fef9c3; color:#854d0e; font-size:11px; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .profile-header { padding: 20px; }
  .profile-header h1 { font-size: 18px; }
  .card { padding: 16px 14px; overflow-x: auto; }
  .info-grid { grid-template-columns: 1fr; gap: 14px; }
  table { min-width: 480px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
}
</style>
