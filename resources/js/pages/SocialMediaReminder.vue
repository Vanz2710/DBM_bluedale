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
      <button class="btn-search" @click="load(1)">Search</button>
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
        <span>{{ meta.total ?? reminders.length }} job(s)</span>
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
            <td class="td-no">{{ idx + 1 }}</td>

            <td class="td-client">
              <input class="cell-input cell-client" v-model="item.company_name" @change="saveReminder(item)">
            </td>

            <td class="td-package">
              <div class="pkg-wrap">
                <span class="pkg-label">{{ item.package }}</span>
                <svg class="pkg-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <select class="pkg-select" v-model="item.package" @change="saveReminder(item)">
                  <option v-if="!packageOptions.some((pkg) => pkg.name === item.package)" :value="item.package">{{ item.package }}</option>
                  <option v-for="pkg in packageOptions" :key="pkg.id" :value="pkg.name">{{ pkg.name }}</option>
                </select>
              </div>
            </td>

            <td class="td-month">
              <input class="cell-input cell-month" v-model="item.month" @change="saveReminder(item)">
            </td>

            <!-- Content Calendar -->
            <td class="td-status">
              <div :class="['pill', pillClass(item.content_calendar_status)]">
                {{ pillLabel(item.content_calendar_status) }}
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <select v-model="item.content_calendar_status" @change="saveReminder(item)">
                  <option value="pending">Pending</option>
                  <option value="wfa">WFA</option>
                  <option value="approved">Approved</option>
                </select>
              </div>
            </td>

            <!-- Artwork Editing -->
            <td class="td-status">
              <div :class="['pill', pillClass(item.artwork_editing_status)]">
                {{ pillLabel(item.artwork_editing_status) }}
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <select v-model="item.artwork_editing_status" @change="saveReminder(item)">
                  <option value="pending">Pending</option>
                  <option value="wfa">WFA</option>
                  <option value="approved">Approved</option>
                </select>
              </div>
            </td>

            <!-- Posting -->
            <td class="td-status">
              <div class="posting-group">
                <div :class="['pill', pillClass(item.posting_status)]">
                  {{ pillLabel(item.posting_status) }}
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  <select v-model="item.posting_status" @change="handlePostingChange(item)">
                    <option value="pending">Pending</option>
                    <option value="wfa">WFA</option>
                    <option value="approved">Approved</option>
                    <option value="scheduling">Scheduling</option>
                    <option value="posted">Posted</option>
                  </select>
                </div>
                <input
                  v-if="item.posting_status === 'scheduling'"
                  class="initials-chip"
                  v-model="item.posting_staff_initials"
                  maxlength="4"
                  placeholder="AA"
                  @change="saveReminder(item)"
                >
              </div>
            </td>

            <!-- Report -->
            <td class="td-status">
              <div :class="['pill', pillClass(item.report_status)]">
                {{ pillLabel(item.report_status) }}
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <select v-model="item.report_status" @change="saveReminder(item)">
                  <option value="pending">Pending</option>
                  <option value="wfa">WFA</option>
                  <option value="done">Done</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
            </td>

            <td class="td-action">
              <button class="btn-del" @click="openDeleteModal(item)" title="Delete">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="pagination">
        <span class="pagination-info">Showing {{ reminders.length }} of {{ meta.total }} job(s)</span>
        <div class="pagination-btns">
          <button class="page-nav" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <template v-for="pg in pageNumbers" :key="'pg-' + pg">
            <button
              v-if="pg !== '...'"
              class="page-num"
              :class="{ 'page-num--on': pg === meta.current_page }"
              @click="load(pg)"
            >{{ pg }}</button>
            <span v-else class="page-ellipsis">…</span>
          </template>
          <button class="page-nav" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </div>
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
const meta      = ref({ current_page: 1, last_page: 1, total: 0 });
const page      = ref(1);
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

function pillClass(status) {
  const map = {
    pending:    'pill-pending',
    wfa:        'pill-wfa',
    approved:   'pill-approved',
    scheduling: 'pill-scheduling',
    posted:     'pill-posted',
    done:       'pill-done',
    completed:  'pill-done',
  };
  return map[status] ?? 'pill-pending';
}

function pillLabel(status) {
  const map = {
    pending:    'Pending',
    wfa:        'WFA',
    approved:   'Approved',
    scheduling: 'Scheduling',
    posted:     'Posted',
    done:       'Done',
    completed:  'Completed',
  };
  return map[status] ?? status;
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

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)          return [1, 2, 3, '...', total];
  if (cur >= total - 2)  return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

async function load(pg = page.value) {
  page.value = pg;
  loading.value = true;
  error.value = '';
  try {
    const params = { page: pg, per_page: 20 };
    if (search.value.trim()) params.search = search.value.trim();
    if (monthFilter.value.trim()) params.month = monthFilter.value.trim();
    const res = await api.get('/v1/social-media-reminders', { params });
    reminders.value = res.data.data ?? [];
    meta.value = {
      current_page: res.data.current_page ?? 1,
      last_page:    res.data.last_page    ?? 1,
      total:        res.data.total        ?? 0,
    };
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
  load(1);
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

/* ── Panel buttons ── */
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
  padding: 4px 13px; border-radius: 999px;
  font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
  white-space: nowrap;
}
.legend-item.status-pending    { background: #fee2e2; color: #991b1b; }
.legend-item.status-wfa        { background: #dbeafe; color: #1d4ed8; }
.legend-item.status-approved   { background: #dcfce7; color: #15803d; }
.legend-item.status-scheduling { background: #ede9fe; color: #6d28d9; }
.legend-item.status-posted     { background: #dcfce7; color: #15803d; }
.legend-item.status-done       { background: #d1fae5; color: #065f46; }

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
  padding: 11px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
}
.production-badge {
  display: inline-flex; align-items: center; padding: 3px 11px;
  border-radius: 999px; background: var(--primary-soft); color: var(--primary);
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}

/* ── Loading / empty ── */
.loading-wrap { display: flex; justify-content: center; align-items: center; padding: 60px 0; }
.loading-msg { color: var(--text-2); font-size: 14px; }
.empty-state { text-align: center; padding: 48px 24px; color: var(--text-3); font-size: 14px; }

/* ── Table ── */
table { width: 100%; min-width: 1060px; border-collapse: collapse; font-size: 13px; }

thead tr { background: var(--surface-2); }
thead th {
  padding: 11px 12px;
  font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
  text-align: center; white-space: nowrap;
  border-bottom: 1px solid var(--border);
}
thead th:first-child { text-align: center; }
thead th:nth-child(2) { text-align: left; }

tbody tr {
  border-bottom: 1px solid var(--border-soft);
  transition: background 0.1s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

tbody td {
  padding: 10px 12px;
  color: var(--text-1);
  vertical-align: middle;
}

/* ── Column cells ── */
.td-no { width: 48px; text-align: center; font-size: 12px; font-weight: 700; color: var(--text-3); }
.td-client { min-width: 200px; }
.td-package { min-width: 160px; text-align: center; }
.td-month { width: 130px; text-align: center; }
.td-status { width: 140px; text-align: center; }
.td-action { width: 52px; text-align: center; }

/* ── Client inline input ── */
.cell-input {
  width: 100%; border: none; background: transparent;
  font-size: 13px; color: var(--text-1); outline: none;
  border-radius: var(--radius-sm);
  transition: background 0.12s, box-shadow 0.12s;
}
.cell-input:hover { background: var(--surface-2); }
.cell-input:focus {
  background: var(--surface);
  box-shadow: 0 0 0 2px var(--primary-soft);
}
.cell-client { font-weight: 700; padding: 5px 8px; }
.cell-month  { text-align: center; font-weight: 700; font-size: 12px; text-transform: uppercase; padding: 5px 4px; }

/* ── Package dropdown ── */
.pkg-wrap {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 5px 10px; border-radius: var(--radius-sm);
  font-size: 12.5px; font-weight: 600; color: var(--text-2);
  position: relative; cursor: pointer;
  transition: background 0.12s;
}
.pkg-wrap:hover { background: var(--surface-2); }
.pkg-chevron { color: var(--text-3); flex-shrink: 0; }
.pkg-select {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}

/* ── Status pill (badge + overlay select) ── */
.pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 10px 5px 12px;
  border-radius: 999px;
  font-size: 11px; font-weight: 700;
  white-space: nowrap;
  position: relative; cursor: pointer;
  transition: opacity 0.12s, filter 0.12s;
  user-select: none;
}
.pill:hover { filter: brightness(0.95); }
.pill select {
  position: absolute; inset: 0; width: 100%; opacity: 0; cursor: pointer;
}

.pill-pending    { background: #fee2e2; color: #991b1b; }
.pill-wfa        { background: #dbeafe; color: #1d4ed8; }
.pill-approved   { background: #dcfce7; color: #15803d; }
.pill-scheduling { background: #ede9fe; color: #6d28d9; }
.pill-posted     { background: #dcfce7; color: #15803d; }
.pill-done       { background: #d1fae5; color: #065f46; }

/* ── Posting column — pill + initials chip ── */
.posting-group { display: flex; align-items: center; justify-content: center; gap: 6px; }
.initials-chip {
  width: 36px; height: 26px; text-align: center;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
  background: var(--surface); color: var(--text-1); outline: none;
  transition: border-color 0.12s;
}
.initials-chip:focus { border-color: var(--primary); }

/* ── Delete icon button ── */
.btn-del {
  width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-3);
  cursor: pointer; transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.btn-del:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .entry-panel, .toolbar { align-items: stretch; }
  .field, .month-field { width: 100%; min-width: 0; }
  .btn-add, .btn-search, .btn-clear { width: 100%; }
}
@media (max-width: 640px) { .page { padding: 16px 12px; } }

/* ── Pagination ── */
.pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px; border-top: 1px solid var(--border-soft);
  background: var(--surface-2);
}
.pagination-info { font-size: 12px; color: var(--text-3); flex-shrink: 0; }
.pagination-btns { display: flex; align-items: center; gap: 3px; }
.page-nav {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  color: var(--text-2); cursor: pointer; transition: background 0.12s;
}
.page-nav svg { width: 14px; height: 14px; }
.page-nav:hover:not(:disabled) { background: color-mix(in srgb, var(--primary) 12%, transparent); }
.page-nav:disabled { opacity: 0.3; cursor: default; }
.page-num {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  font-size: 12.5px; font-weight: 600; color: var(--text-2);
  cursor: pointer; transition: background 0.12s;
}
.page-num:hover { background: color-mix(in srgb, var(--primary) 12%, transparent); }
.page-num--on { background: var(--primary); color: #fff; font-weight: 700; }
.page-ellipsis { width: 32px; text-align: center; color: var(--text-3); font-size: 13px; line-height: 32px; }

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
