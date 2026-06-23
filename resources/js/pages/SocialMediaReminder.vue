<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Social Media Pending Jobs</h1>
      <p class="page-subtitle">Prepared by {{ preparedBy }} &mdash; as of {{ todayLabel }}</p>
    </div>

    <div class="entry-panel">
      <div class="field">
        <label>Company Name</label>
        <div class="company-search">
          <input
            v-model="form.company_name"
            placeholder="Search company"
            autocomplete="off"
            @input="searchCompanies"
            @focus="searchCompanies"
            @keyup.enter="selectFirstCompany"
          >
          <div v-if="showCompanyResults" class="company-results">
            <button
              v-for="company in companyResults"
              :key="company.id"
              type="button"
              @click="selectCompany(company)"
            >
              {{ company.name }}
            </button>
            <div v-if="!companyLoading && companyResults.length === 0" class="company-empty">No company found</div>
            <div v-if="companyLoading" class="company-empty">Searching...</div>
          </div>
        </div>
      </div>
      <div class="field">
        <label>Package</label>
        <select v-model="form.package">
          <option value="">Select package</option>
          <option v-for="pkg in packageOptions" :key="pkg.id" :value="pkg.name">{{ pkg.name }}</option>
        </select>
      </div>
      <div class="field month-field">
        <label>Month</label>
        <input v-model="form.month" placeholder="May 2026" @keyup.enter="addReminder">
      </div>
      <button class="btn-add" :disabled="saving || !canAdd" @click="addReminder">
        {{ saving ? 'Adding...' : '+ Add Job' }}
      </button>
    </div>
    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="toolbar">
      <div class="field">
        <label>Search</label>
        <input v-model="search" placeholder="Company or package" @keyup.enter="load">
      </div>
      <div class="field month-field">
        <label>Month Filter</label>
        <input v-model="monthFilter" placeholder="All months" @keyup.enter="load">
      </div>
      <button class="btn-search" @click="load">Search</button>
      <button class="btn-clear" @click="clearFilters">Clear</button>
    </div>

    <div class="legend">
      <span class="legend-item status-pending">Pending</span>
      <span class="legend-item status-wfa">WFA</span>
      <span class="legend-item status-approved">Approved</span>
      <span class="legend-item status-scheduling">Scheduling</span>
      <span class="legend-item status-posted">Posted</span>
      <span class="legend-item status-done">Done</span>
    </div>

    <div class="table-wrap">
      <div class="table-title">
        <span>{{ reminders.length }} job(s)</span>
        <span class="production-badge">Production Status</span>
      </div>

      <div v-if="loading" class="loading-wrap"><span class="loading-msg">Loading...</span></div>
      <table v-else>
        <thead>
          <tr>
            <th class="no-col">No</th>
            <th>Client</th>
            <th>Package</th>
            <th>Month</th>
            <th>Content Calendar</th>
            <th>Artwork Editing</th>
            <th>Posting</th>
            <th>Report</th>
            <th class="action-col">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="reminders.length === 0">
            <td colspan="9" class="empty-state">No social media jobs yet.</td>
          </tr>
          <tr v-for="(item, idx) in reminders" :key="item.id">
            <td class="no-col">{{ idx + 1 }}</td>
            <td><input class="plain-input client-input" v-model="item.company_name" @change="saveReminder(item)"></td>
            <td>
              <select class="plain-select package-input" v-model="item.package" @change="saveReminder(item)">
                <option v-if="!packageOptions.some((pkg) => pkg.name === item.package)" :value="item.package">{{ item.package }}</option>
                <option v-for="pkg in packageOptions" :key="pkg.id" :value="pkg.name">{{ pkg.name }}</option>
              </select>
            </td>
            <td><input class="plain-input month-input" v-model="item.month" @change="saveReminder(item)"></td>
            <td :class="statusClass(item.content_calendar_status)">
              <select v-model="item.content_calendar_status" @change="saveReminder(item)">
                <option value="pending">PENDING</option>
                <option value="wfa">WFA</option>
                <option value="approved">APPROVED</option>
              </select>
            </td>
            <td :class="statusClass(item.artwork_editing_status)">
              <select v-model="item.artwork_editing_status" @change="saveReminder(item)">
                <option value="pending">PENDING</option>
                <option value="wfa">WFA</option>
                <option value="approved">APPROVED</option>
              </select>
            </td>
            <td :class="statusClass(item.posting_status)">
              <div class="posting-cell">
                <select v-model="item.posting_status" @change="handlePostingChange(item)">
                  <option value="pending">PENDING</option>
                  <option value="wfa">WFA</option>
                  <option value="approved">APPROVED</option>
                  <option value="scheduling">SCHEDULING</option>
                  <option value="posted">POSTED</option>
                </select>
                <input
                  v-if="item.posting_status === 'scheduling'"
                  class="initials-input"
                  v-model="item.posting_staff_initials"
                  maxlength="10"
                  placeholder="AA"
                  @change="saveReminder(item)"
                >
              </div>
            </td>
            <td :class="statusClass(item.report_status)">
              <select v-model="item.report_status" @change="saveReminder(item)">
                <option value="pending">PENDING</option>
                <option value="wfa">WFA</option>
                <option value="done">DONE</option>
                <option value="completed">COMPLETED</option>
              </select>
            </td>
            <td class="action-col">
              <button class="btn-delete" @click="openDeleteModal(item)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="deleteModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete Job</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeDeleteModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete social media job for <strong>{{ deleteModal.item?.company_name }}</strong>?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteModal.loading" @click="confirmDelete">
            {{ deleteModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref, reactive } from 'vue';
import api from '../api.js';

const reminders = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const search = ref('');
const monthFilter = ref('');
const preparedBy = ref(JSON.parse(localStorage.getItem('crm_user') || 'null')?.name ?? 'Staff');
const companyResults = ref([]);
const companyLoading = ref(false);
const selectedContactId = ref(null);
const packageOptions = ref([]);
let companySearchTimer = null;

const form = ref({
  contact_id: null,
  company_name: '',
  package: '',
  month: new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
});

const canAdd = computed(() => selectedContactId.value && form.value.package.trim() && form.value.month.trim());
const todayLabel = computed(() => new Date().toLocaleDateString('en-GB'));
const showCompanyResults = computed(() => form.value.company_name.trim().length > 0 && !selectedContactId.value);

function statusClass(status) {
  return {
    'status-pending': status === 'pending',
    'status-wfa': status === 'wfa',
    'status-approved': status === 'approved',
    'status-scheduling': status === 'scheduling',
    'status-posted': status === 'posted',
    'status-done': status === 'done' || status === 'completed',
  };
}

function payload(item) {
  return {
    company_name: item.company_name,
    contact_id: item.contact_id,
    package: item.package,
    month: item.month,
    content_calendar_status: item.content_calendar_status,
    artwork_editing_status: item.artwork_editing_status,
    posting_status: item.posting_status,
    posting_staff_initials: item.posting_staff_initials,
    report_status: item.report_status,
  };
}

function searchCompanies() {
  selectedContactId.value = null;
  form.value.contact_id = null;
  clearTimeout(companySearchTimer);

  const term = form.value.company_name.trim();
  if (!term) {
    companyResults.value = [];
    return;
  }

  companySearchTimer = setTimeout(loadCompanyResults, 250);
}

async function loadCompanyResults() {
  const term = form.value.company_name.trim();
  if (!term) return;

  companyLoading.value = true;
  try {
    const res = await api.get('/v1/contacts', {
      params: { search: term, per_page: 8 },
    });
    companyResults.value = res.data.data ?? [];
  } catch (_) {
    companyResults.value = [];
  } finally {
    companyLoading.value = false;
  }
}

function selectCompany(company) {
  selectedContactId.value = company.id;
  form.value.contact_id = company.id;
  form.value.company_name = company.name;
  companyResults.value = [];
}

function selectFirstCompany() {
  if (companyResults.value.length > 0) {
    selectCompany(companyResults.value[0]);
  }
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (search.value.trim()) params.search = search.value.trim();
    if (monthFilter.value.trim()) params.month = monthFilter.value.trim();
    const res = await api.get('/v1/social-media-reminders', { params });
    reminders.value = res.data.data ?? [];
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load social media reminders.';
  } finally {
    loading.value = false;
  }
}

async function addReminder() {
  if (!canAdd.value) return;
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/v1/social-media-reminders', {
      contact_id: selectedContactId.value,
      company_name: form.value.company_name.trim(),
      package: form.value.package.trim(),
      month: form.value.month.trim(),
    });
    reminders.value.unshift(res.data.data);
    selectedContactId.value = null;
    form.value.contact_id = null;
    form.value.company_name = '';
    form.value.package = '';
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to add social media job.';
  } finally {
    saving.value = false;
  }
}

async function saveReminder(item) {
  error.value = '';
  try {
    const res = await api.put(`/v1/social-media-reminders/${item.id}`, payload(item));
    const index = reminders.value.findIndex((row) => row.id === item.id);
    if (index !== -1) reminders.value[index] = res.data.data;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update social media job.';
    load();
  }
}

function handlePostingChange(item) {
  if (item.posting_status !== 'scheduling') {
    item.posting_staff_initials = '';
  }
  saveReminder(item);
}

const deleteModal = reactive({ open: false, item: null, loading: false });
function openDeleteModal(item) { deleteModal.item = item; deleteModal.open = true; }
function closeDeleteModal() { deleteModal.open = false; deleteModal.item = null; deleteModal.loading = false; }

async function confirmDelete() {
  if (!deleteModal.item) return;
  deleteModal.loading = true;
  error.value = '';
  try {
    await api.delete(`/v1/social-media-reminders/${deleteModal.item.id}`);
    reminders.value = reminders.value.filter((row) => row.id !== deleteModal.item.id);
    closeDeleteModal();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to delete social media job.';
    closeDeleteModal();
  } finally {
    deleteModal.loading = false;
  }
}

function clearFilters() {
  search.value = '';
  monthFilter.value = '';
  load();
}

onMounted(load);
onMounted(loadPackages);

async function loadPackages() {
  try {
    const res = await api.get('/v1/admin/packages');
    packageOptions.value = res.data.data ?? [];
  } catch (_) {
    packageOptions.value = [];
  }
}
</script>

<style scoped>
/* ── Page shell ── */
.page { padding: 28px 32px; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Entry / toolbar panels ── */
.entry-panel, .toolbar {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-bottom: 14px;
  box-shadow: var(--shadow-sm);
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.field { display: flex; flex-direction: column; gap: 5px; min-width: 180px; }
.field label {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2);
}
.field input, .field select {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 10px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field input:focus, .field select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-soft);
}
.month-field { min-width: 130px; }

/* ── Company autocomplete ── */
.company-search { position: relative; }
.company-search input { width: 100%; }
.company-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 20;
  background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-md); overflow: hidden;
}
.company-results button {
  width: 100%; min-height: 36px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 8px 10px;
  font-size: 13px; font-weight: 700; cursor: pointer; transition: background 0.15s, color 0.15s;
}
.company-results button:last-child { border-bottom: none; }
.company-results button:hover { background: var(--surface-2); color: var(--primary); }
.company-empty { padding: 10px; color: var(--text-2); font-size: 12px; font-weight: 700; }

/* ── Buttons ── */
.btn-add, .btn-search, .btn-clear {
  height: 36px; border: none; border-radius: var(--radius-sm);
  padding: 0 15px; font-size: 13px; font-weight: 700; cursor: pointer;
  transition: background 0.15s;
}
.btn-add { background: var(--primary); color: var(--primary-on); }
.btn-add:hover:not(:disabled) { background: var(--primary-hover); }
.btn-add:disabled { background: var(--text-3); cursor: not-allowed; }
.btn-search { background: var(--primary); color: var(--primary-on); }
.btn-search:hover { background: var(--primary-hover); }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }

/* ── Error banner ── */
.error-msg {
  background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;
  border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 14px; font-size: 13px;
}

/* ── Status legend ── */
.legend { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
.legend-item {
  padding: 3px 12px; border-radius: 999px;
  font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
  white-space: nowrap;
}

/* ── Table wrapper ── */
.table-wrap {
  background: var(--surface);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  overflow: auto;
}

.table-title {
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
  padding: 10px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-2);
  text-transform: uppercase;
  letter-spacing: 0.6px;
}
.production-badge {
  display: inline-flex; align-items: center; padding: 2px 10px;
  border-radius: 999px; background: var(--primary-soft); color: var(--primary);
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}

/* ── Loading / empty ── */
.loading-wrap { display: flex; justify-content: center; align-items: center; padding: 60px 0; }
.loading-msg { color: var(--text-2); font-size: 14px; }
.empty-state { text-align: center; padding: 48px 24px; color: var(--text-3); font-size: 14px; background: var(--surface); }

/* ── Table ── */
table { width: 100%; min-width: 1120px; border-collapse: collapse; font-size: 12px; }

thead tr { background: var(--surface-2); }
thead th {
  padding: 10px 8px;
  font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
  text-align: center; white-space: nowrap;
  border-bottom: 1px solid var(--border);
  border-right: 1px solid var(--border-soft);
}
thead th:last-child { border-right: none; }

tbody td {
  padding: 0; height: 42px; color: var(--text-1);
  background: var(--surface); vertical-align: middle;
  border-bottom: 1px solid var(--border-soft);
  border-right: 1px solid var(--border-soft);
}
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td:not([class]) { background: var(--surface-2); }

.no-col { width: 52px; text-align: center; font-weight: 800; }
.action-col { width: 86px; text-align: center; }

/* ── Inline edit inputs ── */
.plain-input {
  width: 100%; min-height: 40px; border: none; padding: 0 8px;
  background: transparent; font-size: 12px; color: var(--text-1); outline: none;
}
.plain-select {
  width: 100%; min-height: 40px; border: none; padding: 0 8px;
  background: transparent; font-size: 12px; color: var(--text-1); outline: none;
}
.client-input { min-width: 210px; font-weight: 700; color: var(--primary); text-decoration: underline; }
.package-input { min-width: 230px; text-align: center; }
.month-input { min-width: 110px; text-align: center; font-weight: 800; text-transform: uppercase; }

td select {
  width: 100%; height: 40px; border: none; background: transparent; color: inherit;
  font-size: 12px; font-weight: 700; text-align: center; text-align-last: center;
  outline: none; cursor: pointer;
}

.posting-cell { display: flex; align-items: center; gap: 4px; padding: 0 4px; }
.posting-cell select { flex: 1; min-width: 110px; }
.initials-input {
  width: 46px; height: 28px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  text-align: center; font-size: 12px; font-weight: 700; text-transform: uppercase;
  color: inherit; background: var(--surface); outline: none;
}

/* ── Status cell colours (soft / design-system-aligned) ── */
.status-pending   { background: #fee2e2 !important; color: #991b1b !important; }
.status-wfa       { background: #dbeafe !important; color: #1d4ed8 !important; }
.status-approved  { background: #dcfce7 !important; color: #15803d !important; }
.status-scheduling{ background: #ede9fe !important; color: #6d28d9 !important; }
.status-posted    { background: #dcfce7 !important; color: #15803d !important; }
.status-done      { background: #dcfce7 !important; color: #15803d !important; }

/* legend uses same colours */
.legend-item.status-pending    { background: #fee2e2; color: #991b1b; }
.legend-item.status-wfa        { background: #dbeafe; color: #1d4ed8; }
.legend-item.status-approved   { background: #dcfce7; color: #15803d; }
.legend-item.status-scheduling { background: #ede9fe; color: #6d28d9; }
.legend-item.status-posted     { background: #dcfce7; color: #15803d; }
.legend-item.status-done       { background: #dcfce7; color: #15803d; }

/* ── Delete button ── */
.btn-delete {
  height: 28px; border: none; border-radius: var(--radius-sm); padding: 0 9px;
  background: #fee2e2; color: #991b1b;
  font-size: 11px; font-weight: 700; cursor: pointer; transition: background 0.15s;
}
.btn-delete:hover { background: #fca5a5; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .entry-panel, .toolbar { align-items: stretch; }
  .field, .month-field { width: 100%; min-width: 0; }
  .btn-add, .btn-search, .btn-clear { width: 100%; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
}

/* ── Confirm delete modal ── */
.conf-overlay {
  position: fixed; inset: 0; background: rgba(15,23,42,0.45);
  z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px;
}
.conf-modal {
  background: var(--surface); border-radius: var(--radius-lg);
  width: 100%; max-width: 420px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  border: 1px solid var(--border-soft); overflow: hidden;
}
.conf-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft);
}
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close {
  background: none; border: none; cursor: pointer; color: var(--text-3);
  line-height: 1; padding: 0; transition: color 0.15s;
}
.conf-close:hover { color: var(--text-1); }
.conf-body {
  padding: 20px 24px; display: flex; flex-direction: column;
  align-items: center; gap: 12px; text-align: center;
}
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 14px 22px; border-top: 1px solid var(--border-soft);
}
.conf-cancel {
  height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border);
  border-radius: var(--radius-sm); font-size: 13px; font-weight: 600;
  color: var(--text-2); cursor: pointer; transition: background 0.15s;
}
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete {
  height: 38px; padding: 0 18px; background: #dc2626; color: #fff;
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700;
  cursor: pointer; transition: background 0.15s;
}
.conf-delete:hover:not(:disabled) { background: #b91c1c; }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
