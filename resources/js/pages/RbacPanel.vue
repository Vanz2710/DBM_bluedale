<template>
  <div class="page">

    <div class="page-banner">
      <div class="banner-text">
        <h1>Access Control</h1>
        <p>Manage roles, permissions, and users</p>
      </div>
      <div class="tab-group">
        <button v-for="tab in tabs" :key="tab.key"
          class="tab-pill" :class="{ active: activeTab === tab.key }"
          @click="switchTab(tab.key)">
          {{ tab.label }}
          <span v-if="tabCount(tab.key) !== null" class="tab-count">{{ tabCount(tab.key) }}</span>
        </button>
      </div>
    </div>

    <!-- ROLES TAB -->
    <div v-if="activeTab === 'roles'">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">{{ editingRole ? 'Edit Role' : 'New Role' }}</span>
        </div>
        <div class="form-grid">
          <div class="form-field">
            <label>Role Name</label>
            <input v-model="roleForm.name" placeholder="e.g. manager" @keyup.enter="saveRole">
          </div>
          <div class="form-field">
            <label>Description <span class="optional">optional</span></label>
            <input v-model="roleForm.description" placeholder="What this role can do…">
          </div>
        </div>
        <div v-if="formError" class="form-error">{{ formError }}</div>
        <div class="form-actions">
          <button class="btn btn-primary" @click="saveRole" :disabled="!roleForm.name.trim()">
            {{ editingRole ? 'Update Role' : 'Add Role' }}
          </button>
          <button v-if="editingRole" class="btn btn-ghost" @click="cancelRoleEdit">Cancel</button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Roles</span>
          <span class="count-badge">{{ roles.length }}</span>
        </div>
        <div v-if="loading" class="loading-msg">Loading…</div>
        <table v-else>
          <thead>
            <tr><th>#</th><th>Role</th><th>Description</th><th>Permissions</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="roles.length === 0"><td colspan="5" class="empty-state">No roles yet.</td></tr>
            <tr v-for="(role, idx) in roles" :key="role.id">
              <td class="num">{{ idx + 1 }}</td>
              <td><span class="role-name">{{ role.name }}</span></td>
              <td class="muted">{{ role.description || '—' }}</td>
              <td>
                <div class="tag-wrap">
                  <span v-for="p in role.permissions" :key="p.id" class="tag tag-blue">{{ p.name }}</span>
                  <span v-if="!role.permissions?.length" class="muted">none</span>
                </div>
              </td>
              <td class="actions-cell">
                <button class="act-btn act-edit" @click="startRoleEdit(role)" title="Edit">Edit</button>
                <button class="act-btn act-purple" @click="openPermModal(role)" title="Assign permissions">Perms</button>
                <button class="act-btn act-red" @click="deleteRole(role)" title="Delete">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PERMISSIONS TAB -->
    <div v-if="activeTab === 'permissions'">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">{{ editingPerm ? 'Edit Permission' : 'New Permission' }}</span>
        </div>
        <div class="form-grid">
          <div class="form-field">
            <label>Permission Name</label>
            <input v-model="permForm.name" placeholder="e.g. view contacts" @keyup.enter="savePerm">
          </div>
          <div class="form-field">
            <label>Description <span class="optional">optional</span></label>
            <input v-model="permForm.description" placeholder="What this permission allows…">
          </div>
        </div>
        <div v-if="formError" class="form-error">{{ formError }}</div>
        <div class="form-actions">
          <button class="btn btn-primary" @click="savePerm" :disabled="!permForm.name.trim()">
            {{ editingPerm ? 'Update Permission' : 'Add Permission' }}
          </button>
          <button v-if="editingPerm" class="btn btn-ghost" @click="cancelPermEdit">Cancel</button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Permissions</span>
          <span class="count-badge">{{ permissions.length }}</span>
        </div>
        <div v-if="loading" class="loading-msg">Loading…</div>
        <table v-else>
          <thead>
            <tr><th>#</th><th>Permission</th><th>Description</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="permissions.length === 0"><td colspan="4" class="empty-state">No permissions yet.</td></tr>
            <tr v-for="(perm, idx) in permissions" :key="perm.id">
              <td class="num">{{ idx + 1 }}</td>
              <td><code class="perm-code">{{ perm.name }}</code></td>
              <td class="muted">{{ perm.description || '—' }}</td>
              <td class="actions-cell">
                <button class="act-btn act-edit" @click="startPermEdit(perm)">Edit</button>
                <button class="act-btn act-red" @click="deletePerm(perm)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- USERS TAB -->
    <div v-if="activeTab === 'users'">
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">New User</span>
        </div>
        <div class="form-grid form-grid-3">
          <div class="form-field">
            <label>Full Name</label>
            <input v-model="userForm.name" placeholder="e.g. Jane Smith">
          </div>
          <div class="form-field">
            <label>Email</label>
            <input v-model="userForm.email" type="email" placeholder="jane@example.com">
          </div>
          <div class="form-field">
            <label>Role <span class="optional">optional</span></label>
            <select v-model="userForm.role">
              <option value="">— Select role —</option>
              <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
          </div>
          <div class="form-field">
            <label>Password</label>
            <input v-model="userForm.password" type="password" placeholder="Min. 8 characters">
          </div>
          <div class="form-field">
            <label>Confirm Password</label>
            <input v-model="userForm.password_confirmation" type="password" placeholder="Repeat password">
          </div>
        </div>
        <div v-if="formError" class="form-error">{{ formError }}</div>
        <div class="form-actions">
          <button class="btn btn-primary" @click="createUser"
            :disabled="!userForm.name.trim() || !userForm.email.trim() || !userForm.password.trim()">
            Add User
          </button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Users</span>
          <span class="count-badge">{{ users.length }}</span>
        </div>
        <div v-if="loading" class="loading-msg">Loading…</div>
        <table v-else>
          <thead>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Roles</th><th>Joined</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0"><td colspan="6" class="empty-state">No users yet.</td></tr>
            <tr v-for="(user, idx) in users" :key="user.id">
              <td class="num">{{ idx + 1 }}</td>
              <td class="user-name-cell">{{ user.name }}</td>
              <td class="muted">{{ user.email }}</td>
              <td>
                <div class="tag-wrap">
                  <span v-for="r in user.roles" :key="r.id" class="tag tag-purple">{{ r.name }}</span>
                  <span v-if="!user.roles?.length" class="muted">no role</span>
                </div>
              </td>
              <td class="muted date-cell">{{ formatDate(user.created_at) }}</td>
              <td class="actions-cell">
                <button class="act-btn act-edit" @click="openEditUserModal(user)">Edit</button>
                <button class="act-btn act-purple" @click="openRoleModal(user)">Roles</button>
                <button class="act-btn act-red" @click="deleteUser(user)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PERMISSION SYNC MODAL -->
    <Teleport to="body">
      <div v-if="permModal.open" class="overlay" @click.self="permModal.open = false">
        <div class="modal">
          <div class="modal-head">
            <div>
              <div class="modal-title">Assign Permissions</div>
              <div class="modal-sub">Role: <strong>{{ permModal.role?.name }}</strong></div>
            </div>
            <button class="modal-close" @click="permModal.open = false">✕</button>
          </div>
          <div class="modal-body">
            <label v-for="p in permissions" :key="p.id" class="check-row">
              <input type="checkbox" :value="p.name" v-model="permModal.selected">
              <span class="check-label">{{ p.name }}</span>
            </label>
            <div v-if="!permissions.length" class="empty-state">No permissions defined yet.</div>
          </div>
          <div class="modal-foot">
            <span class="selected-count">{{ permModal.selected.length }} selected</span>
            <button class="btn btn-ghost" @click="permModal.open = false">Cancel</button>
            <button class="btn btn-primary" @click="savePermSync">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ROLE SYNC MODAL -->
    <Teleport to="body">
      <div v-if="roleModal.open" class="overlay" @click.self="roleModal.open = false">
        <div class="modal">
          <div class="modal-head">
            <div>
              <div class="modal-title">Assign Roles</div>
              <div class="modal-sub">User: <strong>{{ roleModal.user?.name }}</strong></div>
            </div>
            <button class="modal-close" @click="roleModal.open = false">✕</button>
          </div>
          <div class="modal-body">
            <label v-for="r in roles" :key="r.id" class="check-row">
              <input type="checkbox" :value="r.name" v-model="roleModal.selected">
              <span class="check-label">{{ r.name }}</span>
            </label>
            <div v-if="!roles.length" class="empty-state">No roles defined yet.</div>
          </div>
          <div class="modal-foot">
            <span class="selected-count">{{ roleModal.selected.length }} selected</span>
            <button class="btn btn-ghost" @click="roleModal.open = false">Cancel</button>
            <button class="btn btn-primary" @click="saveRoleSync">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- EDIT USER MODAL -->
    <Teleport to="body">
      <div v-if="editUserModal.open" class="overlay" @click.self="closeEditUserModal">
        <div class="modal modal-wide">
          <div class="modal-head">
            <div>
              <div class="modal-title">Edit User</div>
              <div class="modal-sub">{{ editUserModal.user?.email }}</div>
            </div>
            <button class="modal-close" @click="closeEditUserModal">✕</button>
          </div>
          <div class="modal-body">
            <div v-if="editUserModal.error" class="form-error" style="margin-bottom:12px">{{ editUserModal.error }}</div>
            <div class="edit-grid">
              <div class="form-field">
                <label>Full Name</label>
                <input v-model="editUserForm.name" class="edit-input" placeholder="Full name…">
              </div>
              <div class="form-field">
                <label>Email</label>
                <input v-model="editUserForm.email" type="email" class="edit-input" placeholder="Email…">
              </div>
              <div class="form-field">
                <label>New Password <span class="optional">leave blank to keep current</span></label>
                <input v-model="editUserForm.password" type="password" class="edit-input" placeholder="New password…">
              </div>
              <div class="form-field" v-if="editUserForm.password">
                <label>Confirm New Password</label>
                <input v-model="editUserForm.password_confirmation" type="password" class="edit-input" placeholder="Confirm new password…">
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-ghost" @click="closeEditUserModal">Cancel</button>
            <button class="btn btn-primary" @click="saveEditUser"
              :disabled="!editUserForm.name.trim() || !editUserForm.email.trim()">Save Changes</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../api.js';

const tabs = [
  { key: 'roles',       label: 'Roles' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'users',       label: 'Users' },
];

const activeTab  = ref('roles');
const loading    = ref(false);
const formError  = ref('');

const roles       = ref([]);
const permissions = ref([]);
const users       = ref([]);

const roleForm     = reactive({ name: '', description: '' });
const permForm     = reactive({ name: '', description: '' });
const userForm     = reactive({ name: '', email: '', password: '', password_confirmation: '', role: '' });
const editUserForm = reactive({ name: '', email: '', password: '', password_confirmation: '' });

const editingRole = ref(null);
const editingPerm = ref(null);

const permModal     = reactive({ open: false, role: null, selected: [] });
const roleModal     = reactive({ open: false, user: null, selected: [] });
const editUserModal = reactive({ open: false, user: null, error: '' });

function tabCount(key) {
  if (key === 'roles')       return roles.value.length || null;
  if (key === 'permissions') return permissions.value.length || null;
  if (key === 'users')       return users.value.length || null;
  return null;
}

function handleError(e) {
  const errors = e.response?.data?.errors;
  formError.value = errors
    ? Object.values(errors).flat().join(' ')
    : (e.response?.data?.message ?? 'An error occurred.');
}

async function loadRoles() {
  const res = await api.get('/v1/rbac/roles');
  roles.value = res.data.data;
}
async function loadPermissions() {
  const res = await api.get('/v1/rbac/permissions');
  permissions.value = res.data.data;
}
async function loadUsers() {
  const res = await api.get('/v1/rbac/users');
  users.value = res.data.data;
}

async function switchTab(key) {
  activeTab.value = key;
  formError.value = '';
  loading.value = true;
  try {
    if (key === 'roles')       await Promise.all([loadRoles(), loadPermissions()]);
    if (key === 'permissions') await loadPermissions();
    if (key === 'users')       await Promise.all([loadUsers(), loadRoles()]);
  } finally {
    loading.value = false;
  }
}

// --- Roles ---
async function saveRole() {
  formError.value = '';
  try {
    if (editingRole.value) {
      const res = await api.put(`/v1/rbac/roles/${editingRole.value.id}`, roleForm);
      const idx = roles.value.findIndex(r => r.id === editingRole.value.id);
      if (idx !== -1) roles.value[idx] = { ...roles.value[idx], ...res.data.data };
      cancelRoleEdit();
    } else {
      const res = await api.post('/v1/rbac/roles', roleForm);
      roles.value.push({ ...res.data.data, permissions: [] });
      roleForm.name = ''; roleForm.description = '';
    }
  } catch (e) { handleError(e); }
}

function startRoleEdit(role) {
  editingRole.value = role;
  roleForm.name = role.name;
  roleForm.description = role.description ?? '';
  formError.value = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelRoleEdit() {
  editingRole.value = null;
  roleForm.name = ''; roleForm.description = '';
}

async function deleteRole(role) {
  if (!confirm(`Delete role "${role.name}"?`)) return;
  try {
    await api.delete(`/v1/rbac/roles/${role.id}`);
    roles.value = roles.value.filter(r => r.id !== role.id);
  } catch (e) { handleError(e); }
}

function openPermModal(role) {
  permModal.role     = role;
  permModal.selected = (role.permissions ?? []).map(p => p.name);
  permModal.open     = true;
}

async function savePermSync() {
  try {
    const res = await api.put(`/v1/rbac/roles/${permModal.role.id}/permissions`, {
      permissions: permModal.selected,
    });
    const idx = roles.value.findIndex(r => r.id === permModal.role.id);
    if (idx !== -1) roles.value[idx] = res.data.data;
    permModal.open = false;
  } catch (e) { handleError(e); }
}

// --- Permissions ---
async function savePerm() {
  formError.value = '';
  try {
    if (editingPerm.value) {
      const res = await api.put(`/v1/rbac/permissions/${editingPerm.value.id}`, permForm);
      const idx = permissions.value.findIndex(p => p.id === editingPerm.value.id);
      if (idx !== -1) permissions.value[idx] = res.data.data;
      cancelPermEdit();
    } else {
      const res = await api.post('/v1/rbac/permissions', permForm);
      permissions.value.push(res.data.data);
      permForm.name = ''; permForm.description = '';
    }
  } catch (e) { handleError(e); }
}

function startPermEdit(perm) {
  editingPerm.value = perm;
  permForm.name = perm.name;
  permForm.description = perm.description ?? '';
  formError.value = '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelPermEdit() {
  editingPerm.value = null;
  permForm.name = ''; permForm.description = '';
}

async function deletePerm(perm) {
  if (!confirm(`Delete permission "${perm.name}"?`)) return;
  try {
    await api.delete(`/v1/rbac/permissions/${perm.id}`);
    permissions.value = permissions.value.filter(p => p.id !== perm.id);
  } catch (e) { handleError(e); }
}

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// --- Users ---
async function createUser() {
  formError.value = '';
  try {
    const res = await api.post('/v1/rbac/users', userForm);
    users.value.push(res.data.data);
    userForm.name = ''; userForm.email = ''; userForm.password = '';
    userForm.password_confirmation = ''; userForm.role = '';
  } catch (e) { handleError(e); }
}

async function deleteUser(user) {
  if (!confirm(`Delete user "${user.name}"?`)) return;
  try {
    await api.delete(`/v1/rbac/users/${user.id}`);
    users.value = users.value.filter(u => u.id !== user.id);
  } catch (e) { handleError(e); }
}

function openEditUserModal(user) {
  editUserModal.user  = user;
  editUserModal.error = '';
  editUserForm.name   = user.name;
  editUserForm.email  = user.email;
  editUserForm.password = '';
  editUserForm.password_confirmation = '';
  editUserModal.open  = true;
}

function closeEditUserModal() {
  editUserModal.open = false;
  editUserModal.user = null;
}

async function saveEditUser() {
  editUserModal.error = '';
  try {
    const payload = { name: editUserForm.name, email: editUserForm.email };
    if (editUserForm.password) {
      payload.password = editUserForm.password;
      payload.password_confirmation = editUserForm.password_confirmation;
    }
    const res = await api.put(`/v1/rbac/users/${editUserModal.user.id}`, payload);
    const idx = users.value.findIndex(u => u.id === editUserModal.user.id);
    if (idx !== -1) users.value[idx] = { ...users.value[idx], ...res.data.data };
    closeEditUserModal();
  } catch (e) {
    const errors = e.response?.data?.errors;
    editUserModal.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'An error occurred.');
  }
}

function openRoleModal(user) {
  roleModal.user     = user;
  roleModal.selected = (user.roles ?? []).map(r => r.name);
  roleModal.open     = true;
}

async function saveRoleSync() {
  try {
    const res = await api.put(`/v1/rbac/users/${roleModal.user.id}/roles`, {
      roles: roleModal.selected,
    });
    const idx = users.value.findIndex(u => u.id === roleModal.user.id);
    if (idx !== -1) users.value[idx] = res.data.data;
    roleModal.open = false;
  } catch (e) { handleError(e); }
}

onMounted(() => switchTab('roles'));
</script>

<style scoped>
/* ── Page ── */
.page { padding: 28px 32px; max-width: 1100px; }

/* ── Banner ── */
.page-banner {
  background: linear-gradient(135deg, #1a2f4a 0%, #4f46e5 100%);
  border-radius: 14px; padding: 22px 28px; margin-bottom: 24px; color: white;
  display: flex; align-items: center; justify-content: space-between; gap: 20px;
  flex-wrap: wrap;
}
.banner-text h1 { font-size: 20px; font-weight: 700; margin: 0 0 3px; }
.banner-text p  { font-size: 13px; opacity: 0.75; margin: 0; }

/* ── Tabs inside banner ── */
.tab-group { display: flex; gap: 6px; }
.tab-pill {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 16px; border-radius: 20px; border: 1.5px solid rgba(255,255,255,0.25);
  background: transparent; color: rgba(255,255,255,0.75);
  font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.tab-pill:hover  { background: rgba(255,255,255,0.12); color: white; }
.tab-pill.active { background: white; color: #4f46e5; border-color: white; }
.tab-count {
  background: rgba(0,0,0,0.12); color: inherit;
  font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 10px;
}
.tab-pill.active .tab-count { background: #eef2ff; color: #4f46e5; }

/* ── Panel (card) ── */
.panel {
  background: white; border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 8px rgba(0,0,0,0.04);
  margin-bottom: 16px; overflow: hidden;
}
.panel-header {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
}
.panel-title { font-size: 14px; font-weight: 700; color: #0f172a; }
.count-badge {
  background: #f1f5f9; color: #64748b;
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px;
}
.loading-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 13px; }

/* ── Forms ── */
.form-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px;
  padding: 20px 24px;
}
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }

.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 12px; font-weight: 600; color: #374151; }
.form-field input, .form-field select {
  height: 38px; padding: 0 12px;
  border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 13px; color: #0f172a; outline: none;
  transition: border-color 0.15s;
}
.form-field input:focus, .form-field select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
.optional { font-weight: 400; color: #94a3b8; font-size: 11px; margin-left: 4px; }

.form-error { margin: 0 24px; padding: 10px 14px; background: #fef2f2; color: #dc2626; font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid #fecaca; }

.form-actions {
  display: flex; gap: 8px; padding: 16px 24px 20px;
  border-top: 1px solid #f8fafc;
}

/* ── Buttons ── */
.btn {
  height: 38px; padding: 0 18px; border: none; border-radius: 8px;
  font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s;
}
.btn-primary { background: #6366f1; color: white; }
.btn-primary:hover { background: #4f46e5; }
.btn-primary:disabled { background: #c7d2fe; cursor: not-allowed; }
.btn-ghost { background: #f1f5f9; color: #64748b; }
.btn-ghost:hover { background: #e2e8f0; }

/* ── Table ── */
table { width: 100%; border-collapse: collapse; }
thead th {
  background: #f8fafc; color: #64748b;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
  padding: 11px 16px; border-bottom: 1px solid #e2e8f0; text-align: left;
}
tbody td {
  padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
  font-size: 13px; color: #374151; vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafbff; }
.num { color: #cbd5e1; font-size: 12px; width: 40px; }
.muted { color: #94a3b8; }
.empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 13px; }

.role-name { font-weight: 600; color: #1e293b; }
.user-name-cell { font-weight: 500; color: #1e293b; }
.date-cell { font-size: 12px; white-space: nowrap; }
.perm-code { background: #f1f5f9; color: #1e293b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; }

/* ── Tags ── */
.tag-wrap { display: flex; flex-wrap: wrap; gap: 4px; }
.tag { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 12px; }
.tag-blue   { background: #dbeafe; color: #1d4ed8; }
.tag-purple { background: #ede9fe; color: #6d28d9; }

/* ── Action buttons ── */
.actions-cell { white-space: nowrap; width: 1%; }
.act-btn {
  height: 28px; padding: 0 11px; border-radius: 6px;
  font-size: 12px; font-weight: 600; cursor: pointer; border: none;
  margin-right: 4px; transition: all 0.12s;
}
.act-btn:last-child { margin-right: 0; }
.act-edit   { background: #fef9c3; color: #854d0e; }
.act-edit:hover   { background: #fde68a; }
.act-purple { background: #ede9fe; color: #6d28d9; }
.act-purple:hover { background: #ddd6fe; }
.act-red    { background: #fee2e2; color: #991b1b; }
.act-red:hover    { background: #fecaca; }

/* ── Modals ── */
.overlay {
  position: fixed; inset: 0; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 100; padding: 20px;
}
.modal {
  background: white; border-radius: 14px;
  width: 460px; max-width: 100%; max-height: 85vh;
  display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-wide { width: 560px; }

.modal-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9;
}
.modal-title { font-size: 15px; font-weight: 700; color: #0f172a; }
.modal-sub   { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.modal-close {
  width: 28px; height: 28px; border-radius: 6px; border: none;
  background: #f1f5f9; color: #64748b; font-size: 13px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-close:hover { background: #e2e8f0; }

.modal-body {
  flex: 1; overflow-y: auto; padding: 16px 24px;
  display: flex; flex-direction: column; gap: 6px;
}

.check-row {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 8px; cursor: pointer;
  transition: background 0.1s;
}
.check-row:hover { background: #f8fafc; }
.check-row input { accent-color: #6366f1; width: 16px; height: 16px; flex-shrink: 0; }
.check-label { font-size: 13px; color: #374151; }

.modal-foot {
  display: flex; align-items: center; gap: 8px;
  padding: 16px 24px; border-top: 1px solid #f1f5f9;
}
.selected-count { font-size: 12px; color: #94a3b8; flex: 1; }
.modal-foot .btn { height: 36px; }

/* ── Edit user form inside modal ── */
.edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
.edit-input {
  height: 38px; padding: 0 12px;
  border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 13px; outline: none; width: 100%; box-sizing: border-box;
}
.edit-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
</style>
