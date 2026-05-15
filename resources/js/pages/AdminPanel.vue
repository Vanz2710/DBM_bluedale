<template>
  <div class="page">
    <div class="page-banner">
      <h1>Admin Panel</h1>
      <p>Manage dropdown options and lookup values</p>
    </div>

    <div class="tabs">
      <button
        v-for="tab in tabs" :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="switchTab(tab.key)"
      >{{ tab.label }}</button>
    </div>

    <div class="card">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <div class="add-form">
          <input v-model="newName" :placeholder="`Add new ${currentTab.label.toLowerCase()}…`" @keyup.enter="addItem">
          <button class="btn btn-add" @click="addItem" :disabled="!newName.trim()">+ Add</button>
        </div>
        <div v-if="addError" class="hint error-hint">{{ addError }}</div>

        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Action</th></tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="3" class="empty-state">No items yet.</td>
            </tr>
            <tr v-for="(item, idx) in items" :key="item.id">
              <td class="num">{{ idx + 1 }}</td>
              <td>
                <input v-if="editId === item.id" v-model="editName" class="edit-input" @keyup.enter="saveEdit(item)" @keyup.escape="cancelEdit">
                <span v-else>{{ item.name }}</span>
              </td>
              <td class="actions">
                <template v-if="editId === item.id">
                  <button class="btn-sm btn-save" @click="saveEdit(item)">Save</button>
                  <button class="btn-sm btn-cancel" @click="cancelEdit">Cancel</button>
                </template>
                <template v-else>
                  <button class="btn-sm btn-edit" @click="startEdit(item)">Edit</button>
                  <button class="btn-sm btn-delete" @click="deleteItem(item)">Delete</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const tabs = [
  { key: 'statuses',   label: 'Statuses' },
  { key: 'types',      label: 'Types' },
  { key: 'industries', label: 'Industries' },
  { key: 'categories', label: 'Categories' },
  { key: 'areas',      label: 'Areas' },
  { key: 'tasks',      label: 'Tasks' },
];

const activeTab = ref('statuses');
const items     = ref([]);
const loading   = ref(false);
const newName   = ref('');
const addError  = ref('');
const editId    = ref(null);
const editName  = ref('');

const currentTab = computed(() => tabs.find(t => t.key === activeTab.value));

async function loadItems() {
  loading.value = true;
  addError.value = '';
  editId.value = null;
  try {
    const res = await api.get(`/v1/admin/${activeTab.value}`);
    items.value = res.data.data ?? res.data;
  } finally {
    loading.value = false;
  }
}

function switchTab(key) {
  activeTab.value = key;
  newName.value = '';
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
  editId.value = item.id;
  editName.value = item.name;
}

function cancelEdit() {
  editId.value = null;
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

async function deleteItem(item) {
  if (!confirm(`Delete "${item.name}"?`)) return;
  try {
    await api.delete(`/v1/admin/${activeTab.value}/${item.id}`);
    items.value = items.value.filter(i => i.id !== item.id);
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Failed to delete item.';
  }
}

onMounted(loadItems);
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 860px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #6366f1);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }

.tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.tab-btn {
  padding: 8px 16px; border-radius: 8px; border: 1.5px solid #e2e8f0;
  background: white; font-size: 13px; font-weight: 600; color: #64748b; cursor: pointer;
  transition: all 0.15s;
}
.tab-btn:hover { border-color: #6366f1; color: #6366f1; }
.tab-btn.active { background: #6366f1; border-color: #6366f1; color: white; }

.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 24px 28px; }
.loading-msg { text-align: center; padding: 40px; color: #94a3b8; }

.add-form { display: flex; gap: 10px; margin-bottom: 8px; }
.add-form input {
  flex: 1; height: 40px; padding: 0 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; outline: none;
}
.add-form input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.btn-add {
  height: 40px; padding: 0 18px; background: #6366f1; color: white;
  border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;
}
.btn-add:disabled { background: #94a3b8; cursor: not-allowed; }
.hint.error-hint { color: #ef4444; font-size: 12px; font-weight: 600; margin-bottom: 10px; }

table { width: 100%; border-collapse: collapse; margin-top: 16px; }
thead th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; text-align: left; }
tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
.num { color: #94a3b8; font-size: 12px; width: 40px; }
.actions { width: 140px; }
.edit-input {
  width: 100%; height: 32px; padding: 0 10px; border: 1.5px solid #6366f1;
  border-radius: 6px; font-size: 13px; outline: none;
}
.btn-sm { height: 28px; padding: 0 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; margin-left: 4px; }
.btn-sm:first-child { margin-left: 0; }
.btn-edit   { background: #fefce8; color: #92400e; }
.btn-edit:hover { background: #fde68a; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.btn-delete:hover { background: #fca5a5; }
.btn-save   { background: #dcfce7; color: #166534; }
.btn-save:hover { background: #86efac; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.empty-state { text-align: center; padding: 32px; color: #94a3b8; font-size: 13px; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 16px 14px; }
  table { overflow-x: auto; display: block; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .add-form { flex-wrap: wrap; }
  .add-form input { flex: 1 1 100%; }
}
</style>
