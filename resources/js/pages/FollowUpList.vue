<template>
  <div class="page" :class="{ 'page-embedded': embedded }">
    <div class="page-head" :class="{ 'page-head-embedded': embedded }">
      <div v-if="!embedded" class="page-head-left">
        <h1 class="page-title">Follow-Ups</h1>
        <p class="page-subtitle">Track follow-up actions by date range or month range</p>
      </div>
      <div class="page-head-actions">
        <button v-if="can('create followups')" class="btn-primary-pill" data-tour="add-followup-btn" @click="openAddModal">
          <span class="plus-icon" aria-hidden="true">+</span> Add Follow-Up
        </button>
      </div>
    </div>

    <div v-if="todoFilter" class="filter-banner">
      <span class="filter-badge">FILTERED</span>
      <span v-if="todoFilterInfo">
        Showing follow-ups for <strong>{{ todoFilterInfo.task }}</strong>
        <span v-if="todoFilterInfo.todo_date"> (due {{ todoFilterInfo.todo_date }})</span>
        — <strong>{{ todoFilterInfo.contact_name }}</strong>
      </span>
      <span v-else>Showing follow-ups for to-do #{{ todoFilter }}</span>
      <button class="btn-clear-filter" @click="clearTodoFilter">Clear filter <span v-html="CI.x" style="display:inline-flex;vertical-align:middle;margin-left:2px"></span></button>
    </div>

    <div v-if="selectedIds.length > 0" class="selection-bar">
      <button class="btn-export-sel" @click="openExportModal(true)">Export {{ selectedIds.length }} selected</button>
      <span>{{ selectedIds.length }} record(s) selected</span>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>View</label>
        <select v-model="view" @change="onViewChange">
          <option>DateRange</option>
          <option>MonthRange</option>
        </select>
      </div>

      <template v-if="view === 'DateRange'">
        <div class="filter-group">
          <label>From Date</label>
          <input type="date" v-model="fromDate">
        </div>
        <div class="filter-group">
          <label>To Date</label>
          <input type="date" v-model="toDate">
        </div>
      </template>

      <template v-else>
        <div class="filter-group">
          <label>From Month</label>
          <input type="month" v-model="fromMonth">
        </div>
        <div class="filter-group">
          <label>To Month</label>
          <input type="month" v-model="toMonth">
        </div>
      </template>

      <div class="filter-group">
        <label>Action Type</label>
        <select v-model="actionType">
          <option value="">All Types</option>
          <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="completionStatus" @change="load">
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="">All</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search Company</label>
        <input type="text" v-model="search" @keyup.enter="load" placeholder="Company name…">
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
      <button class="btn btn-export" @click="openExportModal()">Export</button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="record-count">
          <span class="count-label">{{ periodLabel }}</span>
          <span class="count-badge">{{ meta.total ?? followUps.length }} record(s)</span>
        </span>
      </div>
      <LoadingSpinner v-if="loading" />
      <div v-else class="table-scroll">
        <table>
          <colgroup>
            <col style="width:34px">    <!-- checkbox -->
            <col style="width:126px">   <!-- date -->
            <col style="width:112px">   <!-- action type -->
            <col>                       <!-- company -->
            <col style="width:120px">   <!-- status -->
            <col style="width:82px">    <!-- type -->
            <col style="width:78px">    <!-- user -->
            <col style="width:112px">   <!-- task -->
            <col style="width:150px">   <!-- note -->
            <col style="width:106px">   <!-- actions -->
          </colgroup>
          <thead>
            <tr>
              <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
              <th>Follow-Up Date</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Action Type</span>
                  <select v-model="colActionFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>
              </th>
              <th>Company</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Status</span>
                  <select v-model="colStatusFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
              </th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Type</span>
                  <select v-model="colTypeFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </th>
              <th>User</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Task</span>
                  <select v-model="colTaskFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in lookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </th>
              <th>Note</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="followUps.length === 0">
              <td colspan="10" class="empty-state">No follow-ups found for this period.</td>
            </tr>
            <tr v-for="(f, idx) in followUps" :key="f.id" :class="{ 'row-done': f.completion_status === 'completed', 'row-cancelled': f.completion_status === 'cancelled' }">
              <td><input type="checkbox" :value="f.id" v-model="selectedIds"></td>
              <td><span class="date-text">{{ f.followup_date }}</span></td>
              <td>
                <span v-if="f.action_type" class="action-chip">{{ f.action_type }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td class="td-company">
                <router-link v-if="f.contact_id" :to="`/contacts/${f.contact_id}`" class="company-link">
                  {{ f.contact_name }}
                </router-link>
                <span v-else>{{ f.contact_name ?? '—' }}</span>
              </td>
              <td>
                <span v-if="f.status" class="status-chip">{{ f.status }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td>{{ f.type ?? '—' }}</td>
              <td>{{ f.user ?? '—' }}</td>
              <td>
                <span v-if="f.task" class="task-chip">{{ f.task }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td class="note-cell">{{ f.note ?? '—' }}</td>
              <td class="actions-cell">
                <button v-if="can('edit followups') && f.can_edit && f.completion_status === 'pending'"
                  class="icon-btn btn-complete" title="Mark complete"
                  :disabled="completing === f.id"
                  @click="toggleStatus(f)" v-html="CI.check">
                </button>
                <button v-if="can('edit followups') && f.can_edit && f.completion_status !== 'pending'"
                  class="icon-btn btn-undo" title="Mark pending"
                  :disabled="completing === f.id"
                  @click="toggleStatus(f)" v-html="CI.undo">
                </button>
                <button v-if="can('edit followups') && f.can_edit" class="icon-btn btn-edit" title="Edit" @click="openEditModal(f)" v-html="CI.edit"></button>
                <button v-if="can('delete followups') && f.can_edit" class="icon-btn btn-del" title="Delete" @click="confirmDelete(f)" v-html="CI.trash"></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <span class="pager-count">Showing {{ followUps.length }} of {{ meta.total ?? followUps.length }} record(s)</span>
        <div class="pager-btns">
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage((meta.current_page ?? 1) - 1)">‹</button>
          <template v-for="pg in pageNumbers" :key="pg">
            <button v-if="pg !== '...'" class="pager-num" :class="{ 'pager-num--on': pg === (meta.current_page ?? 1) }" @click="changePage(pg)">{{ pg }}</button>
            <span v-else class="pager-ellipsis">…</span>
          </template>
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage((meta.current_page ?? 1) + 1)">›</button>
        </div>
        <div class="pager-rows">
          <span class="pager-rows-label">Rows</span>
          <select v-model.number="perPage" @change="changePage(1)" class="pager-rows-sel">
            <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Export Modal -->
    <Teleport to="body">
      <div v-if="exportModal.open" class="remark-overlay" @mousedown.self="exportModal.open = false">
        <div class="export-modal">
          <div class="export-modal-header">
            <div>
              <strong class="export-modal-title">Export Follow-Ups</strong>
              <p class="export-modal-sub">Pick what to include, then download.</p>
            </div>
            <button class="remark-close" @click="exportModal.open = false" v-html="CI.x"></button>
          </div>
          <div class="export-modal-body">
            <div class="export-section">
              <div class="export-cols-head">
                <span class="export-section-label">Columns to include</span>
                <div class="export-cols-actions">
                  <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = true)">All</button>
                  <span class="export-dot-sep">·</span>
                  <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = false)">None</button>
                </div>
              </div>
              <div class="export-cols-grid">
                <label v-for="col in exportCols" :key="col.key" class="export-col-check">
                  <input type="checkbox" v-model="col.checked">
                  <span>{{ col.label }}</span>
                </label>
              </div>
            </div>
          </div>
          <div class="export-modal-footer">
            <p class="export-footer-count">
              Will export <strong>{{ exportRowCount }}</strong> × <strong>{{ exportCols.filter(c => c.checked).length }}</strong> column(s)
            </p>
            <div class="export-action-stack">
              <button class="export-dl-btn export-dl-xls" :disabled="exportModal.loading || exportCols.every(c => !c.checked)" @click="executeExport('xls')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="export-dl-text">
                  <span class="export-dl-label">{{ exportModal.loading ? 'Exporting…' : 'Download Excel' }}</span>
                  <span class="export-dl-desc">Formatted with borders &amp; column widths</span>
                </span>
              </button>
              <button class="export-dl-btn export-dl-csv" :disabled="exportModal.loading || exportCols.every(c => !c.checked)" @click="executeExport('csv')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="export-dl-text">
                  <span class="export-dl-label">Download CSV</span>
                  <span class="export-dl-desc">Plain text, opens in any spreadsheet app</span>
                </span>
              </button>
              <button class="export-cancel-btn" @click="exportModal.open = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Follow-Up Modal -->
    <div v-if="addModal.open" class="remark-overlay">
      <div class="add-followup-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Add Follow-Up</strong>
          </div>
          <button class="remark-close" @click="closeAddModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <form @submit.prevent="submitAddFollowUp">
            <div v-if="addModal.error" class="add-error-box">{{ addModal.error }}</div>
            <div class="add-form-group">
              <label>Company <span class="req">*</span></label>
              <div class="company-search-wrap">
                <input
                  type="text"
                  class="company-input"
                  v-model="companySearch.query"
                  @input="onCompanySearch"
                  @focus="companySearch.open = true"
                  @blur="closeCompanyDropdownSoon"
                  placeholder="Type to search company…"
                  autocomplete="off"
                >
                <button v-if="addModal.contactId" type="button" class="company-clear" @click="clearCompany" v-html="CI.x"></button>
                <div v-if="companySearch.open && companySearch.query.trim().length" class="company-dropdown">
                  <div v-if="companySearch.loading" class="company-option muted">Searching…</div>
                  <template v-else>
                    <div v-if="!companySearch.results.length" class="company-option muted">No companies found</div>
                    <div
                      v-for="c in companySearch.results"
                      :key="c.id"
                      class="company-option"
                      @mousedown.prevent="selectCompany(c)"
                    >{{ c.name }}</div>
                  </template>
                </div>
              </div>
            </div>
            <div class="add-form-group">
              <label>To-Do <span class="req">*</span></label>
              <select v-model="addForm.todo_id" required :disabled="!addModal.contactId || addModal.todosLoading">
                <option value="">{{ addModal.contactId ? (addModal.todosLoading ? 'Loading…' : 'Select to-do') : 'Select company first' }}</option>
                <option v-for="t in addModal.todos" :key="t.id" :value="t.id">
                  {{ t.task?.name ?? 'Task' }} — {{ t.todo_date }}{{ t.todo_remark ? ' — ' + t.todo_remark.slice(0, 40) : '' }}
                </option>
              </select>
              <span v-if="addModal.contactId && !addModal.todosLoading && addModal.todos.length === 0" class="add-hint">No to-dos found for this company.</span>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Follow-Up Date <span class="req">*</span></label>
                <input type="date" v-model="addForm.followup_date" required>
              </div>
              <div class="add-form-group">
                <label>Action Type</label>
                <select v-model="addForm.action_type">
                  <option value="">— Select type —</option>
                  <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-group">
              <label>Note</label>
              <textarea v-model="addForm.note" placeholder="Enter follow-up note or outcome…" rows="4"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-cancel" @click="closeAddModal">Cancel</button>
              <button type="submit" class="btn-followup-submit" :disabled="!addForm.todo_id || !addForm.followup_date || addModal.saving">
                {{ addModal.saving ? 'Saving…' : 'Add Follow-Up' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Follow-Up Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="remark-overlay" @mousedown.self="closeEditModal">
        <div class="add-followup-modal">
          <div class="add-modal-header">
            <div class="add-modal-title-block">
              <strong class="add-modal-title">Edit Follow-Up</strong>
            </div>
            <button class="remark-close" @click="closeEditModal" v-html="CI.x"></button>
          </div>
          <div class="add-modal-body">
            <div v-if="editModal.error" class="add-error-box">{{ editModal.error }}</div>
            <div class="fu-context-row">
              <div class="fu-context-item">
                <span class="fu-context-label">Company</span>
                <span class="fu-context-value">{{ editModal.followUp?.contact_name ?? '—' }}</span>
              </div>
              <div class="fu-context-item">
                <span class="fu-context-label">Task</span>
                <span class="fu-context-value">{{ editModal.followUp?.task ?? '—' }}</span>
              </div>
              <div class="fu-context-item">
                <span class="fu-context-label">To-Do Date</span>
                <span class="fu-context-value">{{ editModal.followUp?.todo_date ?? '—' }}</span>
              </div>
              <div class="fu-context-item">
                <span class="fu-context-label">Assigned To</span>
                <span class="fu-context-value">{{ editModal.followUp?.user ?? '—' }}</span>
              </div>
            </div>
            <form @submit.prevent="submitEditFollowUp">
              <div class="add-form-row">
                <div class="add-form-group">
                  <label>Follow-Up Date <span class="req">*</span></label>
                  <input type="date" v-model="editForm.followup_date" required>
                </div>
                <div class="add-form-group">
                  <label>Action Type</label>
                  <select v-model="editForm.action_type">
                    <option value="">— Select type —</option>
                    <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>
              </div>
              <div class="add-form-group">
                <label>Note</label>
                <textarea v-model="editForm.note" rows="5" placeholder="Enter follow-up note or outcome…"></textarea>
              </div>
              <div class="add-modal-actions">
                <button type="button" class="btn btn-cancel" @click="closeEditModal">Cancel</button>
                <button type="submit" class="btn-followup-submit" :disabled="!editForm.followup_date || editModal.saving">
                  {{ editModal.saving ? 'Saving…' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-backdrop">
      <div class="modal">
        <h3>Delete Follow-Up?</h3>
        <p>Are you sure you want to delete this follow-up for <strong>{{ deleteTarget.contact_name }}</strong>?</p>
        <div class="modal-btns">
          <button class="btn btn-cancel" @click="deleteTarget = null">Cancel</button>
          <button class="btn btn-danger" @click="doDelete" :disabled="deleting">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { usePermissions } from '../composables/usePermissions.js';

// `embedded` = rendered inside the Contacts page's Follow-Up tab (vs the standalone route).
const props = defineProps({ embedded: { type: Boolean, default: false } });

const { can } = usePermissions();

const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const CI = {
  edit:  _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash: _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
  x:     _si('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
  check: _si('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
  undo:  _si('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.29"/>'),
};

const ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];

const route  = useRoute();
const router = useRouter();
const todoFilter = ref(route.query.todo_id ? Number(route.query.todo_id) : null);
const todoFilterInfo = ref(null); // { task, todo_date, contact_name }

const today = new Date().toISOString().slice(0, 10);
const thisMonth = today.slice(0, 7);

const view       = ref('DateRange');
const fromDate   = ref(today);
const toDate     = ref(today);
const fromMonth  = ref(thisMonth);
const toMonth    = ref(thisMonth);
const PER_PAGE_OPTIONS = [20, 50, 100];

const actionType       = ref('');
const completionStatus = ref('pending');
const search           = ref('');
const perPage          = ref(50);

// Column header filters
const colActionFilter = ref('');
const colStatusFilter = ref('');
const colTypeFilter   = ref('');
const colTaskFilter   = ref('');

// Lookups for column filter dropdowns
const lookups = ref({ statuses: [], types: [], tasks: [] });
const page             = ref(1);
const completing       = ref(null);

const followUps    = ref([]);
const meta         = ref({});
const loading      = ref(false);
const selectedIds  = ref([]);
const selectAllRef = ref(null);
const deleteTarget = ref(null);
const deleting     = ref(false);

// List rows use d-m-Y; date inputs need Y-m-d
function dmyToYmd(d) {
  if (!d) return '';
  const p = String(d).split('-');
  return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : d;
}

// Edit Follow-Up modal
const editModal = ref({ open: false, saving: false, error: '', followUp: null });
const editForm  = ref({ followup_date: '', action_type: '', note: '' });

function openEditModal(f) {
  editModal.value = { open: true, saving: false, error: '', followUp: f };
  editForm.value  = {
    followup_date: dmyToYmd(f.followup_date),
    action_type:   f.action_type ?? '',
    note:          f.note        ?? '',
  };
}

function closeEditModal() { editModal.value.open = false; }

async function submitEditFollowUp() {
  const f = editModal.value.followUp;
  if (!f) return;
  editModal.value.saving = true;
  editModal.value.error  = '';
  try {
    await api.put(`/v1/followups/${f.id}`, {
      followup_date: editForm.value.followup_date,
      action_type:   editForm.value.action_type || null,
      note:          editForm.value.note        || null,
    });
    // Update row in-place
    f.followup_date = editForm.value.followup_date
      ? (() => { const [y,m,d] = editForm.value.followup_date.split('-'); return `${d}-${m}-${y}`; })()
      : f.followup_date;
    f.action_type = editForm.value.action_type || null;
    f.note        = editForm.value.note        || null;
    closeEditModal();
  } catch (e) {
    const errors = e.response?.data?.errors;
    editModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    editModal.value.saving = false;
  }
}

// Add Follow-Up modal
const addModal      = ref({ open: false, saving: false, error: '', todos: [], todosLoading: false, contactId: '', contactName: '' });
const addForm       = ref({ todo_id: '', followup_date: today, action_type: '', note: '' });
const companySearch = ref({ query: '', results: [], loading: false, open: false });
let companyTimer = null;

function onCompanySearch() {
  clearTimeout(companyTimer);
  const q = companySearch.value.query.trim();
  companySearch.value.open = true;
  // Editing the text invalidates any previously picked company
  if (addModal.value.contactId && q !== addModal.value.contactName) {
    addModal.value.contactId   = '';
    addModal.value.contactName = '';
    addForm.value.todo_id      = '';
    addModal.value.todos       = [];
  }
  if (!q) { companySearch.value.results = []; return; }
  companyTimer = setTimeout(async () => {
    companySearch.value.loading = true;
    try {
      const res = await api.get('/v1/contacts', { params: { search: q, per_page: 20 } });
      companySearch.value.results = res.data.data;
    } catch (_) {
      companySearch.value.results = [];
    } finally {
      companySearch.value.loading = false;
    }
  }, 300);
}

function selectCompany(c) {
  addModal.value.contactId    = c.id;
  addModal.value.contactName  = c.name;
  companySearch.value.query   = c.name;
  companySearch.value.open    = false;
  companySearch.value.results = [];
  onAddContactChange();
}

function clearCompany() {
  addModal.value.contactId    = '';
  addModal.value.contactName  = '';
  companySearch.value.query   = '';
  companySearch.value.results = [];
  addForm.value.todo_id       = '';
  addModal.value.todos        = [];
}

function closeCompanyDropdownSoon() {
  setTimeout(() => { companySearch.value.open = false; }, 150);
}

const periodLabel = computed(() => {
  if (view.value === 'DateRange') {
    return `${fromDate.value} to ${toDate.value}`;
  }
  return `${fromMonth.value} to ${toMonth.value}`;
});

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)           return [1, 2, 3, '...', total];
  if (cur >= total - 2)   return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

function onViewChange() {
  page.value = 1;
  load();
}

function buildParams() {
  const p = { view: view.value, per_page: perPage.value, page: page.value };
  if (view.value === 'MonthRange') {
    p.from_month = fromMonth.value;
    p.to_month   = toMonth.value;
  } else {
    p.from_date = fromDate.value;
    p.to_date   = toDate.value;
  }
  const effectiveActionType = colActionFilter.value || actionType.value;
  if (effectiveActionType)    p.action_type       = effectiveActionType;
  if (completionStatus.value) p.completion_status = completionStatus.value;
  if (search.value)           p.search            = search.value;
  if (todoFilter.value)       p.todo_id           = todoFilter.value;
  if (colStatusFilter.value)  p.status_id         = colStatusFilter.value;
  if (colTypeFilter.value)    p.type_id           = colTypeFilter.value;
  if (colTaskFilter.value)    p.task_id           = colTaskFilter.value;
  return p;
}

function clearTodoFilter() {
  todoFilter.value     = null;
  todoFilterInfo.value = null;
  // When embedded in the Contacts page tab, keep the ?tab=followups query intact.
  router.replace({ query: props.embedded ? { tab: 'followups' } : {} });
  load();
}

async function load() {
  loading.value = true;
  selectedIds.value = [];
  try {
    const res = await api.get('/v1/followups', { params: buildParams() });
    followUps.value = res.data.data;
    meta.value      = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

function changePage(p) {
  page.value = p;
  load();
}

function toggleAll(e) {
  selectedIds.value = e.target.checked ? followUps.value.map(f => f.id) : [];
}

// ── Export ──
const EXPORT_COLUMNS = [
  { key: 'no',            label: 'No' },
  { key: 'followup_date', label: 'Follow-Up Date' },
  { key: 'action_type',   label: 'Action Type' },
  { key: 'company',       label: 'Company' },
  { key: 'status',        label: 'Status' },
  { key: 'type',          label: 'Type' },
  { key: 'user',          label: 'User' },
  { key: 'task',          label: 'Task' },
  { key: 'note',          label: 'Note' },
];
const exportModal = ref({ open: false, loading: false, selectedIds: null });
const exportCols  = ref(EXPORT_COLUMNS.map(c => ({ ...c, checked: true })));
const exportRowCount = computed(() =>
  exportModal.value.selectedIds ? `${exportModal.value.selectedIds.length} selected` : 'all filtered'
);

function openExportModal(selectedOnly = false) {
  exportModal.value = {
    open: true,
    loading: false,
    selectedIds: selectedOnly && selectedIds.value.length ? [...selectedIds.value] : null,
  };
}

async function executeExport(format = 'xls') {
  const cols = exportCols.value.filter(c => c.checked);
  if (!cols.length) return;
  exportModal.value.loading = true;
  try {
    const params = new URLSearchParams({ cols: cols.map(c => c.key).join(','), format });
    if (exportModal.value.selectedIds) {
      params.set('ids', exportModal.value.selectedIds.join(','));
    } else {
      Object.entries(buildParams()).forEach(([k, v]) => { if (v !== '' && v != null) params.set(k, v); });
    }
    const token = localStorage.getItem('crm_token');
    const resp  = await fetch(`/api/v1/followups/export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!resp.ok) throw new Error(`Server error ${resp.status}`);
    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `FollowUp_Export_${new Date().toISOString().slice(0, 10)}.${format}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 500);
    exportModal.value.open = false;
  } catch (err) {
    console.error('[Export]', err);
  } finally {
    exportModal.value.loading = false;
  }
}

function confirmDelete(f) {
  deleteTarget.value = f;
}

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/followups/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    load();
  } finally {
    deleting.value = false;
  }
}

async function toggleStatus(f) {
  completing.value = f.id;
  const next = f.completion_status === 'pending' ? 'completed' : 'pending';
  try {
    await api.patch(`/v1/followups/${f.id}/status`, { status: next });
    f.completion_status = next;
    // If a specific status is filtered, remove the row — it no longer belongs in this view
    if (completionStatus.value && completionStatus.value !== next) {
      followUps.value = followUps.value.filter(x => x.id !== f.id);
      if (meta.value.total) meta.value.total--;
    }
  } finally {
    completing.value = null;
  }
}

function openAddModal() {
  addModal.value      = { open: true, saving: false, error: '', todos: [], todosLoading: false, contactId: '', contactName: '' };
  addForm.value       = { todo_id: '', followup_date: today, action_type: '', note: '' };
  companySearch.value = { query: '', results: [], loading: false, open: false };
}

function closeAddModal() { addModal.value.open = false; }

async function onAddContactChange() {
  addForm.value.todo_id       = '';
  addModal.value.todos        = [];
  if (!addModal.value.contactId) return;
  addModal.value.todosLoading = true;
  try {
    const res = await api.get(`/v1/contacts/${addModal.value.contactId}/todos`);
    addModal.value.todos = res.data.data;
  } finally {
    addModal.value.todosLoading = false;
  }
}

async function submitAddFollowUp() {
  addModal.value.saving = true;
  addModal.value.error  = '';
  try {
    await api.post('/v1/followups', {
      todo_id:       addForm.value.todo_id,
      followup_date: addForm.value.followup_date,
      action_type:   addForm.value.action_type || null,
      note:          addForm.value.note        || null,
    });
    closeAddModal();
    load();
  } catch (e) {
    const errors = e.response?.data?.errors;
    addModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    addModal.value.saving = false;
  }
}

onMounted(async () => {
  // Load lookups for column filter dropdowns
  try {
    const lu = await api.get('/v1/lookups');
    lookups.value = { statuses: lu.data.statuses ?? [], types: lu.data.types ?? [], tasks: lu.data.tasks ?? [] };
  } catch (_) { /* non-critical */ }

  // If filtered by a specific ToDo, fetch its details for the banner.
  if (todoFilter.value) {
    // Widen date range and clear status filter so all follow-ups for this todo are visible.
    fromDate.value       = '2000-01-01';
    toDate.value         = '2099-12-31';
    completionStatus.value = '';
    try {
      const todoRes = await api.get(`/v1/todos/${todoFilter.value}`);
      const t = todoRes.data.data;
      todoFilterInfo.value = {
        task:         t.task?.name ?? 'Task',
        todo_date:    t.todo_date ? String(t.todo_date).slice(0, 10) : null,
        contact_name: t.contact?.name ?? '—',
      };
    } catch (_) { /* banner is best-effort */ }
  }
  load();
});
</script>

<style scoped>
.page { padding: 28px 28px 48px; max-width: 1500px; margin: 0 auto; }
.page-embedded { padding: 0; max-width: none; }
.page-head-embedded { margin-bottom: 14px; }

/* Page head */
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

.btn-primary-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary); color: var(--primary-on);
  border: none; border-radius: 999px; padding: 11px 20px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  white-space: nowrap; text-decoration: none;
  box-shadow: 0 8px 22px -8px rgba(29,78,216,0.6);
  transition: background 0.15s, transform 0.06s;
}
.btn-primary-pill:hover { background: var(--primary-hover); }
.btn-primary-pill:active { transform: translateY(1px); }
.plus-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(255,255,255,0.18); font-size: 14px; font-weight: 700; line-height: 1;
}

/* Todo filter banner */
.filter-banner {
  background: var(--primary-soft); color: var(--primary-text);
  border: 1px solid rgba(29,78,216,0.2);
  border-radius: var(--radius-lg); padding: 10px 16px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 12px; font-size: 13px; flex-wrap: wrap;
}
.filter-badge {
  background: var(--primary); color: var(--primary-on); font-size: 10px; font-weight: 700;
  padding: 2px 8px; border-radius: 999px; letter-spacing: 0.5px;
}
.btn-clear-filter {
  margin-left: auto; background: transparent; color: var(--primary-text);
  border: 1px solid rgba(29,78,216,0.3); border-radius: 999px;
  padding: 4px 14px; cursor: pointer; font-size: 12px; font-weight: 600;
}
.btn-clear-filter:hover { background: var(--primary); color: var(--primary-on); border-color: var(--primary); }

/* Selection bar */
.selection-bar {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 10px 18px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 14px; font-size: 13px;
  color: var(--text-2); box-shadow: var(--shadow-xs);
}
.btn-export-sel {
  background: #10b981; color: white; border: none; border-radius: 999px;
  padding: 6px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
}
.btn-export-sel:hover { background: #059669; }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius-lg); padding: 14px 16px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-soft);
  display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.filter-group select, .filter-group input[type="date"],
.filter-group input[type="month"], .filter-group input[type="number"],
.filter-group input[type="text"] {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 28px; }
.filter-group select:focus, .filter-group input:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.btn { height: 38px; padding: 0 18px; border: none; border-radius: 999px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.15s, transform 0.06s; }
.btn:active { transform: translateY(1px); }
.btn-primary { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-export { background: #10b981; color: white; }
.btn-export:hover { background: #059669; }
.btn-cancel { background: var(--surface); color: var(--text-2); border: 1px solid var(--border); }
.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-danger:disabled { background: var(--border); color: var(--text-3); cursor: not-allowed; }

/* Table */
.table-wrap { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft); overflow: hidden; }
.table-header-bar {
  background: var(--surface); padding: 16px 22px;
  border-bottom: 1px solid var(--border-soft);
  display: flex; align-items: center; gap: 12px;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
.table-scroll { overflow-x: hidden; }
table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap; overflow: hidden;
}
thead th:last-child { border-right: none; }
tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; font-size: 13px; white-space: nowrap; overflow: hidden; }
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

/* Column header filters */
.th-filter { white-space: normal !important; overflow: visible !important; vertical-align: top !important; padding: 8px 10px !important; }
.col-head { display: flex; flex-direction: column; gap: 5px; }
.col-head span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.55px; color: var(--text-2); white-space: nowrap; }
.col-filter-sel {
  width: 100%; height: 22px; font-size: 11px; padding: 0 4px;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-1); cursor: pointer;
}
.col-filter-sel:focus { outline: 1px solid var(--primary); }

.row-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; background: var(--surface-2);
  border-radius: 999px; font-size: 11px; font-weight: 700; color: var(--text-3);
}
.date-text { font-size: 12.5px; color: var(--text-2); font-weight: 500; }
.td-company { white-space: normal !important; word-break: break-word; overflow: visible !important; }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; white-space: normal; word-break: break-word; }
.company-link:hover { color: var(--primary); }
.action-chip { background: #fce7f3; color: #9d174d; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.status-chip { background: var(--surface-2); color: var(--text-2); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.task-chip { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.note-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: var(--text-2); }
.muted { color: var(--text-3); }

/* Completed / cancelled row dimming */
.row-done td   { opacity: 0.5; }
.row-done td .company-link { text-decoration: line-through; }
.row-cancelled td { opacity: 0.35; }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: var(--radius-sm); text-decoration: none; font-size: 14px;
  cursor: pointer; border: none; transition: background 0.12s, transform 0.06s;
}
.icon-btn:active { transform: scale(0.92); }
.icon-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-edit     { background: #fefce8; }
.btn-edit:hover { background: #fde68a; }
.btn-del      { background: #fee2e2; }
.btn-del:hover { background: #fca5a5; }
.btn-complete { background: #dcfce7; color: #15803d; }
.btn-complete:hover:not(:disabled) { background: #bbf7d0; }
.btn-undo     { background: var(--surface-2); color: var(--text-3); }
.btn-undo:hover:not(:disabled) { background: var(--border); color: var(--text-2); }
.actions-cell { display: flex; gap: 4px; }
.empty-state { text-align: center; padding: 48px; color: var(--text-3); font-size: 14px; }

.pager {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-top: 1px solid var(--border-soft);
  background: var(--surface); gap: 12px; flex-wrap: wrap;
}
.pager-count { font-size: 12px; color: var(--text-3); white-space: nowrap; }
.pager-btns  { display: flex; align-items: center; gap: 3px; }
.pager-nav {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  color: var(--text-2); cursor: pointer; font-size: 16px; line-height: 1;
  transition: background 0.12s;
}
.pager-nav:hover:not(:disabled) { background: var(--primary-soft); color: var(--primary); }
.pager-nav:disabled { opacity: 0.3; cursor: default; }
.pager-num {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  font-size: 12px; font-weight: 600; color: var(--text-2); cursor: pointer;
  transition: background 0.12s;
}
.pager-num:hover { background: var(--primary-soft); color: var(--primary); }
.pager-num--on { background: var(--primary); color: var(--primary-on); font-weight: 700; }
.pager-ellipsis { width: 30px; text-align: center; color: var(--text-3); font-size: 13px; }
.pager-rows { display: flex; align-items: center; gap: 6px; }
.pager-rows-label { font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; }
.pager-rows-sel {
  height: 30px; padding: 0 10px; border: 1px solid var(--border); border-radius: 999px;
  font-size: 12px; background: var(--surface); color: var(--text-1); outline: none; cursor: pointer;
  transition: border-color 0.15s;
}
.pager-rows-sel:focus { border-color: var(--primary); }

/* Delete modal */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: var(--surface); border-radius: var(--radius-lg); padding: 28px 32px;
  max-width: 400px; width: 90%; box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
}
.modal h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 10px; }
.modal p { font-size: 13px; color: var(--text-2); margin: 0 0 20px; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
  table { min-width: 820px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}

/* Follow-up context info row (used in Add and Edit modals) */
.fu-context-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px;
  background: var(--surface-2); border-radius: var(--radius-sm);
  padding: 12px 14px; margin-bottom: 16px;
}
.fu-context-item { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.fu-context-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.fu-context-value { font-size: 13px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Add Follow-Up Modal */
.remark-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(4px);
  z-index: 700;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.add-followup-modal {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 580px; max-width: 95vw; max-height: 92vh;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.add-modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.add-modal-title-block { display: flex; align-items: center; gap: 12px; }
.add-modal-title { color: var(--text-1); font-size: 17px; font-weight: 800; letter-spacing: -0.2px; }
.add-modal-body { padding: 22px 24px; overflow-y: auto; }
.add-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.add-form-group { margin-bottom: 14px; }
.add-form-group label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--text-2); margin-bottom: 6px;
}
.add-form-group input,
.add-form-group select,
.add-form-group textarea {
  width: 100%; height: 42px; padding: 0 14px;
  border: 1px solid var(--border); border-radius: 999px;
  font-size: 13.5px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.add-form-group textarea { height: 100px; padding: 10px 14px; resize: vertical; border-radius: var(--radius); }
.add-form-group input:focus,
.add-form-group select:focus,
.add-form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.add-form-group select:disabled { background: var(--app-bg); color: var(--text-3); cursor: not-allowed; }

/* Searchable company picker */
.company-search-wrap { position: relative; }
.company-search-wrap .company-input { padding-right: 38px; }
.company-clear {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; padding: 4px; cursor: pointer;
  color: var(--text-3); display: flex; align-items: center;
}
.company-clear:hover { color: var(--text-1); }
.company-clear :deep(svg) { width: 14px; height: 14px; }
.company-dropdown {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-md);
  max-height: 220px; overflow-y: auto;
}
.company-option {
  padding: 10px 14px; font-size: 13px; color: var(--text-1);
  cursor: pointer; border-bottom: 1px solid var(--border-soft);
}
.company-option:last-child { border-bottom: none; }
.company-option:hover { background: var(--surface-2); }
.company-option.muted { color: var(--text-3); cursor: default; }
.company-option.muted:hover { background: none; }
.add-hint { font-size: 11.5px; color: #f59e0b; margin-top: 4px; display: block; }
.add-error-box {
  background: var(--danger-soft); color: var(--danger);
  border-radius: var(--radius); padding: 12px 16px;
  font-size: 13px; margin-bottom: 14px;
}
.add-modal-actions { display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end; }
.add-modal-actions .btn { height: 42px; padding: 0 22px; }
.remark-close {
  background: var(--surface-2); border: none; cursor: pointer;
  font-size: 14px; color: var(--text-2);
  width: 30px; height: 30px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  line-height: 1; transition: background 0.15s, color 0.15s;
}
.remark-close:hover { background: var(--danger-soft); color: var(--danger); }
.btn-followup-submit {
  flex: 1; background: #e11d48; color: #fff; justify-content: center;
  height: 42px; padding: 0 20px; border-radius: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer; border: none;
  display: inline-flex; align-items: center;
}
.btn-followup-submit:disabled { background: #94a3b8; cursor: not-allowed; }
.req { color: #ef4444; }

/* ── Export modal ── */
.export-modal {
  background: var(--surface); border-radius: var(--radius-xl, 14px);
  width: 480px; max-width: 95vw; max-height: 90vh;
  display: flex; flex-direction: column; box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft); overflow: hidden;
}
.export-modal-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0;
}
.export-modal-title { font-size: 17px; font-weight: 800; color: var(--text-1); }
.export-modal-sub   { font-size: 12.5px; color: var(--text-3); margin: 3px 0 0; }
.export-modal-body  { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.export-modal-footer { display: flex; flex-direction: column; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--border-soft); flex-shrink: 0; }
.export-footer-count { font-size: 13px; color: var(--text-3); margin: 0; }
.export-footer-count strong { color: var(--primary); }
.export-action-stack { display: flex; flex-direction: column; gap: 10px; }
.export-dl-btn { width: 100%; display: flex; align-items: flex-start; gap: 14px; padding: 14px 18px; border-radius: var(--radius); border: none; cursor: pointer; text-align: left; transition: opacity 0.15s, transform 0.08s; }
.export-dl-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.export-dl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-dl-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.export-dl-text { display: flex; flex-direction: column; gap: 2px; }
.export-dl-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.export-dl-desc  { font-size: 12px; opacity: 0.82; line-height: 1.3; }
.export-dl-xls { background: #10b981; color: #fff; }
.export-dl-csv { background: var(--surface); border: 1.5px solid var(--border) !important; color: var(--text-1); }
.export-cancel-btn { width: 100%; padding: 10px 16px; background: none; border: 1px solid var(--border-soft); border-radius: var(--radius); font-size: 13px; font-weight: 600; color: var(--text-3); cursor: pointer; transition: background 0.12s, color 0.12s; }
.export-cancel-btn:hover { background: var(--border-soft); color: var(--text-2); }
.export-section { display: flex; flex-direction: column; gap: 10px; }
.export-section-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-3); }
.export-cols-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.export-cols-actions { display: flex; align-items: center; gap: 4px; }
.export-link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--primary); padding: 2px 4px; border-radius: 4px; }
.export-link-btn:hover { text-decoration: underline; }
.export-dot-sep { color: var(--text-3); font-size: 12px; }
.export-cols-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 12px; }
.export-col-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--text-2); font-weight: 500; padding: 6px 10px; border-radius: var(--radius-sm, 6px); border: 1px solid var(--border-soft); transition: background 0.12s, border-color 0.12s; }
.export-col-check:hover { background: var(--primary-soft, #dbeafe); border-color: var(--primary); }
.export-col-check input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer; }

/* ── Modal open animation ── */
@keyframes overlay-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes modal-spring-in {
  from { opacity: 0; transform: scale(0.92) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.remark-overlay { animation: overlay-fade-in 0.18s ease; }
.remark-overlay > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
</style>
