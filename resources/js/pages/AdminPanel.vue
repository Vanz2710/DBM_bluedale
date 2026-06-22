<template>
  <div class="page">

    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Lookup Settings</h1>
        <p class="page-subtitle">Manage dropdown values used in contacts, tasks, filters, reports, and performance tracking.</p>
      </div>
    </div>

    <div class="tabs-bar">
      <div v-for="group in TAB_GROUPS" :key="group.label" class="tab-group-block">
        <span class="tab-group-label">{{ group.label }}</span>
        <div class="tab-group-pills">
          <button
            v-for="tab in group.tabs" :key="tab.key"
            :class="['tab-btn', { 'tab-active': activeTab === tab.key }]"
            @click="switchTab(tab.key)"
          >{{ tab.label }}</button>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <LoadingSpinner v-if="loading" />
      <div v-else-if="loadError" class="error-banner">{{ loadError }}</div>
      <template v-else>
        <div class="table-header-bar">
          <div class="add-form">
            <input v-model="newName" :placeholder="`Add new ${currentTab.label.toLowerCase()}...`" @keyup.enter="addItem">
            <button class="btn-add" @click="addItem" :disabled="!newName.trim()">+ Add</button>
          </div>
          <div v-if="addError" class="inline-error">{{ addError }}</div>
        </div>
        <div class="table-scroll">
          <table>
            <thead>
              <tr><th>#</th><th>Name</th><th>In Use</th><th>Action</th></tr>
            </thead>
            <tbody>
              <tr v-if="items.length === 0">
                <td colspan="4" class="empty-state">No items yet.</td>
              </tr>
              <tr v-for="(item, idx) in items" :key="item.id">
                <td class="num">{{ idx + 1 }}</td>
                <td>
                  <input v-if="editId === item.id" v-model="editName" class="edit-input" @keyup.enter="saveEdit(item)" @keyup.escape="cancelEdit">
                  <span v-else class="item-name">{{ item.name }}</span>
                </td>
                <td class="usage-cell">
                  <span :class="item.usage_count > 0 ? 'badge-used' : 'badge-unused'">{{ item.usage_count }}</span>
                </td>
                <td class="actions-cell">
                  <template v-if="editId === item.id">
                    <button class="act-btn act-save" @click="saveEdit(item)">Save</button>
                    <button class="act-btn act-cancel" @click="cancelEdit">Cancel</button>
                  </template>
                  <template v-else>
                    <button class="act-btn act-edit" @click="startEdit(item)">Edit</button>
                    <button
                      class="act-btn act-delete"
                      @click="openDeleteModal(item)"
                      :disabled="item.usage_count > 0"
                      :title="item.usage_count > 0 ? 'In use by ' + item.usage_count + ' record(s) — remove references first' : 'Delete ' + item.name"
                    >Delete</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

  </div>

  <Teleport to="body">
    <div v-if="deleteModal.open" class="conf-overlay" @click.self="closeDeleteModal">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete Item</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeDeleteModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete <strong>{{ deleteModal.item?.name }}</strong>?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteModal.loading" @click="confirmDeleteItem">
            {{ deleteModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const TAB_GROUPS = [
  {
    label: 'Contact Fields',
    tabs: [
      { key: 'statuses',   label: 'Statuses' },
      { key: 'types',      label: 'Types' },
      { key: 'industries', label: 'Industries' },
      { key: 'categories', label: 'Categories' },
      { key: 'areas',      label: 'Areas' },
    ],
  },
  {
    label: 'Task / Performance',
    tabs: [
      { key: 'tasks', label: 'Tasks' },
    ],
  },
  {
    label: 'Forecasts',
    tabs: [
      { key: 'forecast-products', label: 'Products' },
      { key: 'forecast-types',    label: 'Types' },
      { key: 'forecast-results',  label: 'Results' },
    ],
  },
];

const tabs = TAB_GROUPS.flatMap(g => g.tabs);

const activeTab = ref('statuses');
const items     = ref([]);
const loading   = ref(false);
const loadError = ref('');
const newName   = ref('');
const addError  = ref('');
const editId    = ref(null);
const editName  = ref('');

const deleteModal = reactive({ open: false, item: null, loading: false });
function openDeleteModal(item) { deleteModal.item = item; deleteModal.open = true; }
function closeDeleteModal() { deleteModal.open = false; deleteModal.item = null; deleteModal.loading = false; }

const currentTab = computed(() => tabs.find(t => t.key === activeTab.value));

async function loadItems() {
  loading.value   = true;
  loadError.value = '';
  addError.value  = '';
  editId.value    = null;
  try {
    const res = await api.get(`/v1/admin/${activeTab.value}`);
    items.value = res.data.data ?? res.data;
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Failed to load items.';
    items.value = [];
  } finally {
    loading.value = false;
  }
}

function switchTab(key) {
  activeTab.value = key;
  newName.value   = '';
  loadItems();
}

async function addItem() {
  const name = newName.value.trim();
  if (!name) return;
  addError.value = '';
  try {
    const res = await api.post(`/v1/admin/${activeTab.value}`, { name });
    items.value.push(res.data.data ?? res.data);
    newName.value = '';
  } catch (e) {
    const errors = e.response?.data?.errors;
    addError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to add item.');
  }
}

function startEdit(item) {
  editId.value   = item.id;
  editName.value = item.name;
}

function cancelEdit() {
  editId.value   = null;
  editName.value = '';
}

async function saveEdit(item) {
  const name = editName.value.trim();
  if (!name) return;
  try {
    const res = await api.put(`/v1/admin/${activeTab.value}/${item.id}`, { name });
    const updated = res.data.data ?? res.data;
    const idx = items.value.findIndex(i => i.id === item.id);
    if (idx !== -1) items.value[idx] = updated;
    cancelEdit();
  } catch (e) {
    const errors = e.response?.data?.errors;
    addError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to update item.');
  }
}

async function confirmDeleteItem() {
  if (!deleteModal.item) return;
  deleteModal.loading = true;
  addError.value = '';
  try {
    await api.delete(`/v1/admin/${activeTab.value}/${deleteModal.item.id}`);
    items.value = items.value.filter(i => i.id !== deleteModal.item.id);
    closeDeleteModal();
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Failed to delete item.';
    closeDeleteModal();
  } finally {
    deleteModal.loading = false;
  }
}

onMounted(loadItems);
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1100px; }

/* ── Page header ── */
.page-head { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Tab bar ── */
.tabs-bar { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
.tab-group-block { display: flex; flex-direction: column; gap: 6px; }
.tab-group-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-3); padding-left: 4px; }
.tab-group-pills { display: inline-flex; gap: 4px; background: var(--surface); border-radius: 999px; padding: 5px; border: 1px solid var(--border-soft); box-shadow: var(--shadow-xs); }
.tab-btn { padding: 7px 16px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text-2); border-radius: 999px; transition: color 0.15s, background 0.15s; white-space: nowrap; }
.tab-btn:hover { color: var(--text-1); background: var(--surface-2); }
.tab-active { color: var(--primary-on) !important; background: var(--primary) !important; box-shadow: 0 4px 12px -4px rgba(29,78,216,0.45); }

/* ── Table wrap ── */
.table-wrap { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft); overflow: hidden; }
.table-header-bar { padding: 16px 22px; border-bottom: 1px solid var(--border-soft); display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.table-scroll { overflow-x: auto; }

/* ── Add form ── */
.add-form { display: flex; gap: 10px; flex: 1; }
.add-form input {
  flex: 1; height: 38px; padding: 0 16px;
  border: 1px solid var(--border); border-radius: 999px;
  font-size: 13px; outline: none; background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.add-form input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.btn-add {
  height: 38px; padding: 0 20px; background: var(--primary); color: var(--primary-on);
  border: none; border-radius: 999px; font-size: 13px; font-weight: 700; cursor: pointer;
  white-space: nowrap; box-shadow: 0 6px 18px -6px rgba(29,78,216,0.5);
  transition: background 0.15s;
}
.btn-add:hover:not(:disabled) { background: var(--primary-hover); }
.btn-add:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }

.error-banner { padding: 14px 22px; color: var(--danger); font-size: 13px; font-weight: 600; background: var(--danger-soft); }
.inline-error { font-size: 12px; font-weight: 600; color: var(--danger); }

/* ── Table ── */
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { background: transparent; color: var(--text-3); font-size: 11.5px; font-weight: 600; padding: 14px 16px; border-bottom: 1px solid var(--border-soft); text-align: left; white-space: nowrap; }
tbody td { padding: 14px 16px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }
.num { color: var(--text-3); font-size: 12px; width: 44px; }
.item-name { font-weight: 500; }
.usage-cell { width: 80px; }
.actions-cell { width: 160px; white-space: nowrap; }

.badge-used   { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: var(--danger-soft); color: var(--danger); }
.badge-unused { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: var(--surface-2); color: var(--text-3); }

.edit-input { width: 100%; height: 34px; padding: 0 12px; border: 1.5px solid var(--primary); border-radius: 8px; font-size: 13px; outline: none; background: var(--surface); }
.act-btn { height: 28px; padding: 0 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; margin-left: 4px; transition: background 0.12s; }
.act-btn:first-child { margin-left: 0; }
.act-edit:not(:disabled)   { background: #fefce8; color: #92400e; }
.act-edit:hover:not(:disabled)   { background: #fde68a; }
.act-delete { background: var(--danger-soft); color: var(--danger); }
.act-delete:hover:not(:disabled) { background: #fca5a5; }
.act-delete:disabled { opacity: 0.35; cursor: not-allowed; }
.act-save   { background: #dcfce7; color: #166534; }
.act-save:hover { background: #bbf7d0; }
.act-cancel { background: var(--surface-2); color: var(--text-2); }
.act-cancel:hover { background: var(--border); }
.empty-state { text-align: center; padding: 48px; color: var(--text-3); font-size: 13px; }

/* ── Audit log ── */
.muted { color: var(--text-3); }
.date-cell { font-size: 12px; white-space: nowrap; }
.actor-cell { font-weight: 500; font-size: 13px; }
.entity-name { display: block; font-size: 13px; font-weight: 500; color: var(--text-1); }
.entity-type { display: block; font-size: 10px; color: var(--text-3); font-family: monospace; }
.detail-cell { font-size: 11px; color: var(--text-3); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.detail-snippet { font-family: monospace; }
.audit-badge { display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 3px 9px; border-radius: 999px; }
.audit-created  { background: #dcfce7; color: #15803d; }
.audit-updated  { background: #dbeafe; color: #1d4ed8; }
.audit-deleted  { background: var(--danger-soft); color: var(--danger); }
.audit-restored { background: #fef9c3; color: #854d0e; }
.audit-approved { background: #d1fae5; color: #065f46; }

@media (max-width: 768px) { .page { padding: 20px 16px; } .tabs-bar { gap: 12px; } }
@media (max-width: 640px) { .page { padding: 16px 12px; } .add-form { flex-wrap: wrap; } }

/* ── Confirm modal ── */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-3); line-height: 1; padding: 0; }
.conf-close:hover { color: var(--text-1); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete { height: 38px; padding: 0 18px; background: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-delete:hover:not(:disabled) { background: #b91c1c; }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
