<template>
  <div class="page">
    <Transition name="toast">
      <div v-if="toast" class="toast-msg" role="status">{{ toast }}</div>
    </Transition>

    <div class="page-header">
      <div>
        <h1 class="page-title">Announcements</h1>
        <p class="page-subtitle">Broadcast messages to all users or send a direct notice to a specific person.</p>
      </div>
      <button class="btn-new" @click="openForm()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Announcement
      </button>
    </div>

    <!-- Create / Edit Form -->
    <div v-if="showForm" class="form-card">
      <div class="form-card-head">
        <span>{{ editingId ? 'Edit Announcement' : 'New Announcement' }}</span>
        <button class="close-btn" @click="closeForm">&times;</button>
      </div>
      <div class="form-body">
        <div v-if="formError" class="form-error">{{ formError }}</div>
        <div class="field full">
          <label>Title <span class="req">*</span></label>
          <input v-model="form.title" placeholder="Announcement title…" maxlength="200">
        </div>
        <div class="field full">
          <label>Message <span class="req">*</span></label>
          <textarea v-model="form.body" placeholder="Write the full announcement here…" rows="4"></textarea>
        </div>
        <div class="field">
          <label>Priority</label>
          <div class="urgency-toggle">
            <button
              type="button"
              :class="['urg-opt', form.urgency === 'normal' ? 'urg-selected-normal' : '']"
              @click="form.urgency = 'normal'"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/></svg>
              Normal
            </button>
            <button
              type="button"
              :class="['urg-opt', form.urgency === 'urgent' ? 'urg-selected-urgent' : '']"
              @click="form.urgency = 'urgent'"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"/></svg>
              Urgent
            </button>
          </div>
        </div>
        <div class="field">
          <label>Send To</label>
          <select v-model="form.target_user_id">
            <option value="">Everyone</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <span class="hint-text">Leave blank to broadcast to all users</span>
        </div>
        <div class="field">
          <label>Publish At <span class="hint">leave blank to publish immediately</span></label>
          <DateTimePicker v-model="form.published_at" :with-time="true" placeholder="Publish immediately" />
        </div>
        <div class="field">
          <label>Expires At <span class="hint">leave blank — never expires</span></label>
          <DateTimePicker v-model="form.expires_at" placeholder="Never expires" />
        </div>
      </div>
      <div class="form-foot">
        <button class="btn-cancel" @click="closeForm">Cancel</button>
        <button class="btn-save" :disabled="saving || !form.title.trim() || !form.body.trim()" @click="save">
          {{ saving ? 'Saving…' : editingId ? 'Save Changes' : 'Publish' }}
        </button>
      </div>
    </div>

    <!-- List -->
    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="table-title">All Announcements</span>
        <span class="count-chip">{{ rows.length }}</span>
      </div>
      <div v-if="loading" class="loading-msg">Loading…</div>
      <div v-else-if="rows.length === 0" class="empty-row">No announcements yet.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Title</th>
            <th>Priority</th>
            <th>Audience</th>
            <th>Status</th>
            <th>Published</th>
            <th>Expires</th>
            <th>Read by</th>
            <th>By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="title-col">
              <div class="row-title">{{ row.title }}</div>
              <div class="row-body-preview">{{ row.body.length > 80 ? row.body.slice(0, 80) + '…' : row.body }}</div>
            </td>
            <td>
              <span :class="['priority-badge', row.urgency === 'urgent' ? 'badge-urgent' : 'badge-normal']">
                {{ row.urgency === 'urgent' ? 'Urgent' : 'Normal' }}
              </span>
            </td>
            <td class="muted">
              <span v-if="row.target_user" class="audience-user">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                {{ row.target_user }}
              </span>
              <span v-else class="audience-all">Everyone</span>
            </td>
            <td>
              <span :class="['status-badge', row.is_draft ? 'badge-draft' : 'badge-live']">
                {{ row.is_draft ? 'Draft / Scheduled' : 'Live' }}
              </span>
            </td>
            <td class="muted">{{ row.published_at ?? '—' }}</td>
            <td class="muted">{{ row.expires_at ?? 'Never' }}</td>
            <td class="muted">{{ row.reads_count }}</td>
            <td class="muted">{{ row.author }}</td>
            <td class="actions-cell">
              <button class="act-btn act-edit" @click="openForm(row)">Edit</button>
              <button class="act-btn act-red" @click="openDeleteModal(row)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Delete confirm -->
    <Teleport to="body">
      <div v-if="deleteModal.open" class="modal-backdrop">
        <div class="confirm-modal" role="dialog" aria-modal="true">
          <div class="confirm-head">
            <h2>Delete Announcement</h2>
            <button class="close-btn" @click="deleteModal.open = false">&times;</button>
          </div>
          <div class="confirm-body">
            <svg class="warn-icon" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/></svg>
            <p>Delete <strong>{{ deleteModal.row?.title }}</strong>? This cannot be undone.</p>
          </div>
          <div class="confirm-foot">
            <button class="btn-cancel" @click="deleteModal.open = false">Cancel</button>
            <button class="btn-delete" :disabled="deleteModal.loading" @click="doDelete">
              {{ deleteModal.loading ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../api.js';
import DateTimePicker from '../components/DateTimePicker.vue';

const rows    = ref([]);
const users   = ref([]);
const loading = ref(false);
const saving  = ref(false);
const toast   = ref('');
const formError = ref('');

const showForm  = ref(false);
const editingId = ref(null);
const form = reactive({ title: '', body: '', urgency: 'normal', target_user_id: '', published_at: '', expires_at: '' });

const deleteModal = reactive({ open: false, row: null, loading: false });

function showToast(msg) {
  toast.value = msg;
  setTimeout(() => { toast.value = ''; }, 3000);
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/announcements/admin/all');
    rows.value = res.data.data;
  } finally {
    loading.value = false;
  }
}

async function loadUsers() {
  try {
    const res = await api.get('/v1/lookups');
    users.value = res.data.users ?? [];
  } catch { /* silently fail */ }
}

function openForm(row = null) {
  formError.value = '';
  if (row) {
    editingId.value        = row.id;
    form.title             = row.title;
    form.body              = row.body;
    form.urgency           = row.urgency ?? 'normal';
    form.target_user_id    = row.target_user_id ?? '';
    form.published_at      = row.published_at ?? '';
    form.expires_at        = row.expires_at ?? '';
  } else {
    editingId.value        = null;
    form.title             = '';
    form.body              = '';
    form.urgency           = 'normal';
    form.target_user_id    = '';
    form.published_at      = '';
    form.expires_at        = '';
  }
  showForm.value = true;
  setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 50);
}

function closeForm() { showForm.value = false; editingId.value = null; }

async function save() {
  formError.value = '';
  saving.value = true;
  try {
    const payload = {
      title:          form.title,
      body:           form.body,
      urgency:        form.urgency,
      target_user_id: form.target_user_id || null,
      published_at:   form.published_at || null,
      expires_at:     form.expires_at   || null,
    };
    if (editingId.value) {
      await api.put(`/v1/announcements/${editingId.value}`, payload);
      await load();
      showToast('Announcement updated.');
    } else {
      await api.post('/v1/announcements', payload);
      await load();
      showToast('Announcement published.');
    }
    closeForm();
  } catch (e) {
    const errs = e.response?.data?.errors;
    formError.value = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message ?? 'An error occurred.');
  } finally {
    saving.value = false;
  }
}

function openDeleteModal(row) { deleteModal.row = row; deleteModal.loading = false; deleteModal.open = true; }

async function doDelete() {
  if (!deleteModal.row) return;
  deleteModal.loading = true;
  try {
    await api.delete(`/v1/announcements/${deleteModal.row.id}`);
    rows.value = rows.value.filter(r => r.id !== deleteModal.row.id);
    deleteModal.open = false;
    showToast('Announcement deleted.');
  } catch {
    showToast('Delete failed.');
    deleteModal.loading = false;
  }
}

onMounted(() => { load(); loadUsers(); });
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1200px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; }
.page-title   { font-size: 28px; font-weight: 800; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

.btn-new {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 16px; border-radius: var(--radius-sm); border: none;
  background: var(--primary); color: #fff; font-size: 13px; font-weight: 600;
  cursor: pointer; white-space: nowrap;
}
.btn-new:hover { opacity: .88; }

/* Form */
.form-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); margin-bottom: 24px; overflow: hidden;
}
.form-card-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  font-size: 14px; font-weight: 700; color: var(--text-1);
}
.close-btn { background: none; border: none; font-size: 20px; color: var(--text-3); cursor: pointer; line-height: 1; }
.form-body { padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field.full { grid-column: 1 / -1; }
.field label { font-size: 12px; font-weight: 600; color: var(--text-2); }
.field input, .field textarea, .field select {
  padding: 8px 11px; border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--surface-2); color: var(--text-1); font-size: 13px;
}
.field textarea { resize: vertical; font-family: inherit; }
.req { color: #dc2626; }
.hint { font-size: 11px; font-weight: 400; color: var(--text-3); margin-left: 4px; }
.hint-text { font-size: 11px; color: var(--text-3); }
.form-error { grid-column: 1/-1; color: #dc2626; font-size: 13px; }
.form-foot {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface-2);
}

/* Urgency toggle */
.urgency-toggle { display: flex; gap: 6px; }
.urg-opt {
  display: flex; align-items: center; gap: 5px;
  padding: 7px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--surface-2); color: var(--text-2); font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all 0.15s;
}
.urg-opt:hover { border-color: var(--primary); color: var(--primary); }
.urg-selected-normal { border-color: var(--primary); background: #eff6ff; color: var(--primary); }
.urg-selected-urgent { border-color: #dc2626; background: #fef2f2; color: #dc2626; }

.btn-cancel {
  padding: 8px 16px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface); color: var(--text-2);
  font-size: 13px; cursor: pointer;
}
.btn-save {
  padding: 8px 18px; border-radius: var(--radius-sm); border: none;
  background: var(--primary); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-save:hover:not(:disabled) { opacity: .88; }
.btn-save:disabled { opacity: .45; cursor: not-allowed; }

/* Table */
.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.table-header-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 18px; border-bottom: 1px solid var(--border); background: var(--surface-2);
}
.table-title { font-size: 13px; font-weight: 700; color: var(--text-1); }
.count-chip {
  font-size: 11px; font-weight: 600; padding: 2px 8px;
  border-radius: 999px; background: var(--border); color: var(--text-2);
}
.loading-msg, .empty-row { padding: 32px; text-align: center; color: var(--text-3); font-size: 13px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
  padding: 9px 14px; text-align: left; font-size: 11px; font-weight: 600;
  color: var(--text-3); background: var(--surface-2); border-bottom: 1px solid var(--border);
  text-transform: uppercase; letter-spacing: .4px;
}
tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border); color: var(--text-2); vertical-align: top; }
tbody tr:last-child td { border-bottom: none; }
.title-col { max-width: 240px; }
.row-title { font-weight: 600; color: var(--text-1); margin-bottom: 3px; }
.row-body-preview { font-size: 12px; color: var(--text-3); }
.muted { color: var(--text-3); }

.priority-badge {
  display: inline-block; font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 999px; letter-spacing: .3px;
}
.badge-urgent { background: #fee2e2; color: #dc2626; }
.badge-normal { background: #f1f5f9; color: var(--text-3); }

.audience-user {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; color: var(--primary); font-weight: 600;
}
.audience-all { font-size: 12px; color: var(--text-3); }

.status-badge {
  display: inline-block; font-size: 11px; font-weight: 600;
  padding: 2px 8px; border-radius: 999px;
}
.badge-live  { background: #dcfce7; color: #16a34a; }
.badge-draft { background: #fef3c7; color: #b45309; }

.actions-cell { white-space: nowrap; }
.act-btn { padding: 5px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); font-size: 12px; cursor: pointer; margin-right: 4px; color: var(--text-2); }
.act-btn:hover { background: var(--surface-2); }
.act-edit:hover { border-color: var(--primary); color: var(--primary); }
.act-red { color: #dc2626; }
.act-red:hover { border-color: #dc2626; background: #fef2f2; }

/* Modal */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 200;
  display: flex; align-items: center; justify-content: center;
}
.confirm-modal {
  background: var(--surface); border-radius: var(--radius-lg);
  border: 1px solid var(--border); width: 420px; max-width: 95vw; overflow: hidden;
}
.confirm-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.confirm-head h2 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0; }
.confirm-body { padding: 20px; display: flex; gap: 14px; align-items: flex-start; }
.warn-icon { width: 32px; height: 32px; flex-shrink: 0; margin-top: 2px; }
.confirm-body p { font-size: 13.5px; color: var(--text-2); margin: 0; }
.confirm-foot {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border);
}
.btn-delete {
  padding: 8px 18px; border-radius: var(--radius-sm); border: none;
  background: #dc2626; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-delete:hover:not(:disabled) { opacity: .88; }
.btn-delete:disabled { opacity: .45; cursor: not-allowed; }

/* Toast */
.toast-msg {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: var(--text-1); color: var(--surface); padding: 10px 20px;
  border-radius: var(--radius); font-size: 13px; z-index: 300; white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: opacity .25s, transform .25s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
