<template>
  <div class="page">
    <div class="page-head">
      <div class="page-head-left">
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
      <button class="btn-export-sel" @click="exportSelected">Export {{ selectedIds.length }} selected</button>
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
      <div class="filter-group wide">
        <label>Search Company</label>
        <input v-model="search" @keyup.enter="load" placeholder="Company name…">
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
      <button class="btn btn-export" @click="exportAll">Export</button>
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
          <thead>
            <tr>
              <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
              <th>No</th>
              <th>Follow-Up Date</th>
              <th>Action Type</th>
              <th>Company</th>
              <th>Status</th>
              <th>Type</th>
              <th>User</th>
              <th>Task</th>
              <th>Note</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="followUps.length === 0">
              <td colspan="11" class="empty-state">No follow-ups found for this period.</td>
            </tr>
            <tr v-for="(f, idx) in followUps" :key="f.id">
              <td><input type="checkbox" :value="f.id" v-model="selectedIds"></td>
              <td><span class="row-num">{{ meta.from ? meta.from + idx : idx + 1 }}</span></td>
              <td><span class="date-text">{{ f.followup_date }}</span></td>
              <td>
                <span v-if="f.action_type" class="action-chip">{{ f.action_type }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td>
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
                <router-link v-if="can('edit followups')" :to="`/followups/${f.id}/edit`" class="icon-btn btn-edit" title="Edit" v-html="CI.edit"></router-link>
                <button v-if="can('delete followups')" class="icon-btn btn-del" title="Delete" @click="confirmDelete(f)" v-html="CI.trash"></button>
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

    <!-- Add Follow-Up Modal -->
    <div v-if="addModal.open" class="remark-overlay" @click.self="closeAddModal">
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
              <select v-model="addModal.contactId" @change="onAddContactChange" required>
                <option value="">Select company</option>
                <option v-for="c in addContacts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
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

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
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

const { can } = usePermissions();

const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const CI = {
  edit:  _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash: _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
  x:     _si('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
};

const ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];

const route  = useRoute();
const router = useRouter();
const todoFilter = ref(route.query.todo_id ? Number(route.query.todo_id) : null);
const todoFilterInfo = ref(null); // { task, todo_date, contact_name }

const today = new Date().toISOString().slice(0, 10);
const thisMonth = today.slice(0, 7);

const view       = ref('DateRange');
const fromDate   = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const toDate     = ref(today);
const fromMonth  = ref(thisMonth);
const toMonth    = ref(thisMonth);
const PER_PAGE_OPTIONS = [20, 50, 100];

const actionType = ref('');
const search     = ref('');
const perPage    = ref(50);
const page       = ref(1);

const followUps    = ref([]);
const meta         = ref({});
const loading      = ref(false);
const selectedIds  = ref([]);
const selectAllRef = ref(null);
const deleteTarget = ref(null);
const deleting     = ref(false);

// Add Follow-Up modal
const addContacts = ref([]);
const addModal    = ref({ open: false, saving: false, error: '', todos: [], todosLoading: false, contactId: '' });
const addForm     = ref({ todo_id: '', followup_date: today, action_type: '', note: '' });

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
  if (actionType.value) p.action_type = actionType.value;
  if (search.value)     p.search      = search.value;
  if (todoFilter.value) p.todo_id     = todoFilter.value;
  return p;
}

function clearTodoFilter() {
  todoFilter.value     = null;
  todoFilterInfo.value = null;
  router.replace({ query: {} });
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

function exportAll() {
  const token = localStorage.getItem('crm_token');
  const p = buildParams();
  const qs = new URLSearchParams({ ...p, _token: token }).toString();
  window.location.href = `/api/v1/followups/export?${qs}`;
}

function exportSelected() {
  const token = localStorage.getItem('crm_token');
  const ids   = selectedIds.value.join(',');
  window.location.href = `/api/v1/followups/export?ids=${ids}&_token=${token}`;
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

async function openAddModal() {
  addModal.value = { open: true, saving: false, error: '', todos: [], todosLoading: false, contactId: '' };
  addForm.value  = { todo_id: '', followup_date: today, action_type: '', note: '' };
  if (!addContacts.value.length) {
    const res = await api.get('/v1/contacts', { params: { per_page: 1000 } });
    addContacts.value = res.data.data;
  }
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
  // If filtered by a specific ToDo, fetch its details for the banner.
  if (todoFilter.value) {
    // Widen date range so we don't accidentally exclude the to-do's existing follow-ups.
    fromDate.value = '2000-01-01';
    toDate.value   = '2099-12-31';
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
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 11px 14px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap;
}
thead th:last-child { border-right: none; }
tbody td { padding: 13px 14px; border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; font-size: 13.5px; }
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

.row-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; background: var(--surface-2);
  border-radius: 999px; font-size: 11px; font-weight: 700; color: var(--text-3);
}
.date-text { font-size: 12.5px; color: var(--text-2); font-weight: 500; }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; }
.company-link:hover { color: var(--primary); }
.action-chip { background: #fce7f3; color: #9d174d; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.status-chip { background: var(--surface-2); color: var(--text-2); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.task-chip { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.note-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: var(--text-2); }
.muted { color: var(--text-3); }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: var(--radius-sm); text-decoration: none; font-size: 14px;
  cursor: pointer; border: none; transition: background 0.12s, transform 0.06s;
}
.icon-btn:active { transform: scale(0.92); }
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #fde68a; }
.btn-del { background: #fee2e2; }
.btn-del:hover { background: #fca5a5; }
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
</style>
