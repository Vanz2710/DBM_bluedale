<template>
  <div class="page">

    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Access Control</h1>
        <p class="page-subtitle">Manage roles, permissions, and user accounts</p>
      </div>
    </div>

    <div class="view-tabs">
      <button v-for="tab in tabs" :key="tab.key"
        :class="['tab-btn', { 'tab-active': activeTab === tab.key }]"
        @click="switchTab(tab.key)">
        {{ tab.label }}
        <span v-if="tabCount(tab.key) !== null" class="tab-count-chip"
          :class="{ 'tab-count-alert': tab.key === 'pending' && tabCount('pending') > 0 }">
          {{ tabCount(tab.key) }}
        </span>
      </button>
    </div>

    <!-- PENDING APPROVALS TAB -->
    <div v-if="activeTab === 'pending'">
      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">Users Awaiting Approval</span>
          <span class="count-badge">{{ pending.length }}</span>
        </div>
        <LoadingSpinner v-if="loading" />
        <div v-else-if="pending.length === 0" class="empty-banner">
          <div class="empty-icon">✓</div>
          <div class="empty-title">No pending approvals</div>
          <div class="empty-sub">All new users have been reviewed.</div>
        </div>
        <table v-else>
          <thead>
            <tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Requested</th><th>Created</th><th>Action</th></tr>
          </thead>
          <tbody>
            <tr v-for="(user, idx) in pending" :key="user.id" class="pending-row">
              <td class="num">{{ idx + 1 }}</td>
              <td class="user-name-cell">{{ user.name }}</td>
              <td><code class="username-code">{{ user.username }}</code></td>
              <td class="muted">{{ user.email || '—' }}</td>
              <td class="muted date-cell">{{ formatDate(user.access_requested_at) }}</td>
              <td class="muted date-cell">{{ formatDate(user.created_at) }}</td>
              <td class="actions-cell">
                <button class="act-btn act-green" @click="approveUser(user)">Approve</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ROLES TAB -->
    <div v-if="activeTab === 'roles'">
      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">{{ editingRole ? 'Edit Role' : 'New Role' }}</span>
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

      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">Roles</span>
          <span class="count-badge">{{ roles.length }}</span>
        </div>
        <LoadingSpinner v-if="loading" />
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
                <div v-if="!role.permissions?.length" class="muted">none</div>
                <div v-else class="perm-summary" @click="openPermModal(role)" title="Click to edit permissions">
                  <span v-for="p in role.permissions.slice(0, 3)" :key="p.id" class="tag tag-blue">{{ p.name }}</span>
                  <span v-if="role.permissions.length > 3" class="tag tag-more">+{{ role.permissions.length - 3 }} more</span>
                </div>
              </td>
              <td class="actions-cell">
                <button class="act-btn act-edit" @click="startRoleEdit(role)">Edit</button>
                <button class="act-btn act-purple" @click="openPermModal(role)">Perms</button>
                <button class="act-btn act-red" @click="deleteRole(role)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PERMISSIONS TAB -->
    <div v-if="activeTab === 'permissions'">
      <div class="perm-info-banner">
        <div class="perm-info-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
        </div>
        <div>
          <div class="perm-info-title">Permissions are defined in code</div>
          <div class="perm-info-sub">These are set by a developer in the seeder and tied directly to route middleware. To add or change a permission, update the <code>RolesAndPermissionsSeeder</code> and re-run it. Assign permissions to roles in the <strong>Roles</strong> tab.</div>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">All Permissions</span>
          <span class="count-badge">{{ permissions.length }}</span>
        </div>
        <LoadingSpinner v-if="loading" />
        <table v-else>
          <thead>
            <tr><th>#</th><th>Permission</th><th>Description</th></tr>
          </thead>
          <tbody>
            <tr v-if="permissions.length === 0"><td colspan="3" class="empty-state">No permissions seeded yet.</td></tr>
            <tr v-for="(perm, idx) in permissions" :key="perm.id">
              <td class="num">{{ idx + 1 }}</td>
              <td><code class="perm-code">{{ perm.name }}</code></td>
              <td class="muted">{{ perm.description || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- USERS TAB -->
    <div v-if="activeTab === 'users'">
      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">New User</span>
        </div>
        <div class="form-grid form-grid-3">
          <div class="form-field">
            <label>Full Name</label>
            <input v-model="userForm.name" placeholder="e.g. Jane Smith">
          </div>
          <div class="form-field">
            <label>Username</label>
            <input v-model="userForm.username" placeholder="e.g. jane_smith (letters, numbers, _)">
          </div>
          <div class="form-field">
            <label>Email <span class="optional">optional — required if admin role</span></label>
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
            <div class="pw-wrap">
              <input v-model="userForm.password" :type="showUserPw ? 'text' : 'password'" placeholder="Min. 8 characters">
              <button type="button" class="pw-toggle" @click="showUserPw = !showUserPw" tabindex="-1">
                <svg v-if="showUserPw" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              </button>
            </div>
            <div v-if="userForm.password" class="pw-hints">
              <span :class="['hint', pwCheck(userForm.password).upper  ? 'ok' : 'fail']">A–Z</span>
              <span :class="['hint', pwCheck(userForm.password).num    ? 'ok' : 'fail']">0–9</span>
              <span :class="['hint', pwCheck(userForm.password).sym    ? 'ok' : 'fail']">!@#</span>
              <span :class="['hint', pwCheck(userForm.password).length ? 'ok' : 'fail']">8+ chars</span>
            </div>
          </div>
          <div class="form-field">
            <label>Confirm Password</label>
            <div class="pw-wrap">
              <input v-model="userForm.password_confirmation" :type="showUserConfPw ? 'text' : 'password'" placeholder="Repeat password">
              <button type="button" class="pw-toggle" @click="showUserConfPw = !showUserConfPw" tabindex="-1">
                <svg v-if="showUserConfPw" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              </button>
            </div>
          </div>
        </div>
        <div v-if="formError" class="form-error">{{ formError }}</div>
        <div v-if="userCreatedMsg" class="form-success">{{ userCreatedMsg }}</div>
        <div class="form-actions">
          <button class="btn btn-primary" @click="createUser"
            :disabled="!userForm.name.trim() || !userForm.username.trim() || !pwStrong(userForm.password)">
            Add User
          </button>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-header-bar">
          <span class="table-header-title">Users</span>
          <span class="count-badge">{{ activeUsers.length }}</span>
          <label class="toggle-deleted">
            <input type="checkbox" v-model="showDeleted" @change="loadUsers">
            <span>Show deleted</span>
          </label>
        </div>
        <LoadingSpinner v-if="loading" />
        <table v-else>
          <thead>
            <tr><th>#</th><th>Name</th><th>Username</th><th>Roles</th><th>Status</th><th>Logins</th><th>Last Login</th><th>Joined</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0"><td colspan="9" class="empty-state">No users yet.</td></tr>
            <tr v-for="(user, idx) in users" :key="user.id" :class="{ 'row-deleted': user.deleted_at }">
              <td class="num">{{ idx + 1 }}</td>
              <td class="user-name-cell">{{ user.name }}</td>
              <td><code class="username-code">{{ user.username }}</code></td>
              <td>
                <div class="tag-wrap">
                  <span v-for="r in user.roles" :key="r.id" class="tag tag-purple">{{ r.name }}</span>
                  <span v-if="!user.roles?.length" class="muted">no role</span>
                </div>
              </td>
              <td>
                <span v-if="user.deleted_at" class="tag tag-gray">Deleted</span>
                <span v-else-if="user.inactivity_flagged_at" class="tag tag-red">Locked — Inactive</span>
                <span v-else-if="user.is_approved" class="tag tag-green">Approved</span>
                <span v-else-if="user.access_requested_at" class="tag tag-orange">Pending</span>
                <span v-else class="tag tag-slate">Not logged in</span>
              </td>
              <td class="num">{{ user.login_count ?? 0 }}</td>
              <td class="muted date-cell">{{ formatDate(user.last_login_at) }}</td>
              <td class="muted date-cell">{{ formatDate(user.created_at) }}</td>
              <td class="actions-cell">
                <template v-if="user.deleted_at">
                  <button class="act-btn act-green" @click="restoreUser(user)">Restore</button>
                </template>
                <template v-else-if="user.inactivity_flagged_at">
                  <button class="act-btn act-green" @click="restoreAccess(user)">Restore Access</button>
                  <button class="act-btn act-red" @click="deleteUser(user)">Delete</button>
                </template>
                <template v-else>
                  <button v-if="!user.is_approved && user.access_requested_at" class="act-btn act-green" @click="approveUser(user)">Approve</button>
                  <button class="act-btn act-edit" @click="openEditUserModal(user)">Edit</button>
                  <button class="act-btn act-purple" @click="openRoleModal(user)">Roles</button>
                  <button class="act-btn act-red" @click="deleteUser(user)">Delete</button>
                </template>
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
          <div class="modal-body perm-modal-body">
            <div class="perm-search-wrap">
              <input v-model="filterText" class="perm-search" placeholder="Filter permissions…" autocomplete="off" />
            </div>
            <div v-if="!permissions.length" class="empty-state">No permissions defined yet.</div>
            <div v-else-if="groupedPermissions.length === 0" class="empty-state">No match for "{{ filterText }}"</div>
            <div v-else v-for="group in groupedPermissions" :key="group.resource" class="perm-group">
              <div class="perm-group-header">
                <label class="perm-group-label">
                  <input type="checkbox" class="perm-group-check"
                    :checked="isGroupAllSelected(group)"
                    v-bind:indeterminate="isGroupIndeterminate(group)"
                    @change="toggleGroupAll(group)"
                  />
                  <span class="perm-group-name">{{ group.resource }}</span>
                </label>
                <span class="perm-group-count">{{ selectedInGroup(group) }}/{{ group.items.length }}</span>
              </div>
              <div class="perm-actions-row">
                <label v-for="p in group.items" :key="p.id" class="perm-action-chip"
                  :class="{ 'perm-action-chip-on': permModal.selected.includes(p.name) }">
                  <input type="checkbox" :value="p.name" v-model="permModal.selected" />
                  <span>{{ p.action }}</span>
                </label>
              </div>
            </div>
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
              <div class="modal-sub">@{{ editUserModal.user?.username }}</div>
            </div>
            <button class="modal-close" @click="closeEditUserModal">✕</button>
          </div>
          <div class="modal-body modal-form">
            <div v-if="editUserModal.error" class="form-error" style="margin-bottom:12px">{{ editUserModal.error }}</div>
            <div class="edit-grid">
              <div class="form-field">
                <label>Full Name</label>
                <input v-model="editUserForm.name" class="edit-input" placeholder="Full name…">
              </div>
              <div class="form-field">
                <label>Username</label>
                <input v-model="editUserForm.username" class="edit-input" placeholder="Username…">
              </div>
              <div class="form-field">
                <label>Email <span class="optional">optional</span></label>
                <input v-model="editUserForm.email" type="email" class="edit-input" placeholder="Email address…">
              </div>
              <div class="form-field">
                <label>New Password <span class="optional">leave blank to keep current</span></label>
                <div class="pw-wrap">
                  <input v-model="editUserForm.password" :type="showEditPw ? 'text' : 'password'" class="edit-input" placeholder="New password…">
                  <button type="button" class="pw-toggle" @click="showEditPw = !showEditPw" tabindex="-1">
                    <svg v-if="showEditPw" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  </button>
                </div>
                <div v-if="editUserForm.password" class="pw-hints">
                  <span :class="['hint', pwCheck(editUserForm.password).upper  ? 'ok' : 'fail']">A–Z</span>
                  <span :class="['hint', pwCheck(editUserForm.password).num    ? 'ok' : 'fail']">0–9</span>
                  <span :class="['hint', pwCheck(editUserForm.password).sym    ? 'ok' : 'fail']">!@#</span>
                  <span :class="['hint', pwCheck(editUserForm.password).length ? 'ok' : 'fail']">8+ chars</span>
                </div>
              </div>
              <div class="form-field" v-if="editUserForm.password">
                <label>Confirm New Password</label>
                <div class="pw-wrap">
                  <input v-model="editUserForm.password_confirmation" :type="showEditConfPw ? 'text' : 'password'" class="edit-input" placeholder="Confirm new password…">
                  <button type="button" class="pw-toggle" @click="showEditConfPw = !showEditConfPw" tabindex="-1">
                    <svg v-if="showEditConfPw" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-ghost" @click="closeEditUserModal">Cancel</button>
            <button class="btn btn-primary" @click="saveEditUser"
              :disabled="!editUserForm.name.trim() || !editUserForm.username.trim() || (editUserForm.password && !pwStrong(editUserForm.password))">
              Save Changes
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const tabs = [
  { key: 'pending',     label: 'Pending Approvals' },
  { key: 'roles',       label: 'Roles' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'users',       label: 'Users' },
];

const activeTab  = ref('pending');
const loading    = ref(false);
const formError  = ref('');

const roles       = ref([]);
const permissions = ref([]);
const users       = ref([]);
const pending     = ref([]);
const showDeleted = ref(false);

const activeUsers = computed(() => users.value.filter(u => !u.deleted_at));

const roleForm     = reactive({ name: '', description: '' });
const userForm     = reactive({ name: '', username: '', email: '', password: '', password_confirmation: '', role: '' });
const editUserForm = reactive({ name: '', username: '', email: '', password: '', password_confirmation: '' });

const editingRole = ref(null);

const permModal     = reactive({ open: false, role: null, selected: [] });
const roleModal     = reactive({ open: false, user: null, selected: [] });
const editUserModal = reactive({ open: false, user: null, error: '' });

const filterText = ref('');

const groupedPermissions = computed(() => {
  const filter = filterText.value.toLowerCase().trim();
  const groups = {};
  permissions.value.forEach(p => {
    const spaceIdx = p.name.indexOf(' ');
    const action   = spaceIdx > -1 ? p.name.slice(0, spaceIdx) : p.name;
    const resource = spaceIdx > -1 ? p.name.slice(spaceIdx + 1) : '(general)';
    if (!groups[resource]) groups[resource] = { resource, items: [] };
    groups[resource].items.push({ ...p, action });
  });
  const actionOrder = ['view', 'create', 'edit', 'delete', 'import', 'manage'];
  const sorted = Object.values(groups).sort((a, b) => a.resource.localeCompare(b.resource));
  sorted.forEach(g => {
    g.items.sort((a, b) => {
      const ai = actionOrder.indexOf(a.action);
      const bi = actionOrder.indexOf(b.action);
      return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });
  });
  if (!filter) return sorted;
  return sorted
    .map(g => ({ ...g, items: g.items.filter(p => p.name.toLowerCase().includes(filter)) }))
    .filter(g => g.items.length > 0);
});

function selectedInGroup(group) {
  return group.items.filter(p => permModal.selected.includes(p.name)).length;
}
function isGroupAllSelected(group) {
  return group.items.length > 0 && group.items.every(p => permModal.selected.includes(p.name));
}
function isGroupIndeterminate(group) {
  const c = selectedInGroup(group);
  return c > 0 && c < group.items.length;
}
function toggleGroupAll(group) {
  if (isGroupAllSelected(group)) {
    permModal.selected = permModal.selected.filter(name => !group.items.some(p => p.name === name));
  } else {
    const toAdd = group.items.map(p => p.name).filter(n => !permModal.selected.includes(n));
    permModal.selected = [...permModal.selected, ...toAdd];
  }
}

const showUserPw     = ref(false);
const showUserConfPw = ref(false);
const userCreatedMsg = ref('');
const showEditPw     = ref(false);
const showEditConfPw = ref(false);

function pwCheck(pw) {
  return {
    upper:  /[A-Z]/.test(pw),
    num:    /[0-9]/.test(pw),
    sym:    /[^A-Za-z0-9]/.test(pw),
    length: pw.length >= 8,
  };
}
function pwStrong(pw) {
  const c = pwCheck(pw);
  return c.upper && c.num && c.sym && c.length;
}

function tabCount(key) {
  if (key === 'pending')     return pending.value.length || null;
  if (key === 'roles')       return roles.value.length || null;
  if (key === 'permissions') return permissions.value.length || null;
  if (key === 'users')       return activeUsers.value.length || null;
  return null;
}

function handleError(e) {
  const errors = e.response?.data?.errors;
  formError.value = errors
    ? Object.values(errors).flat().join(' ')
    : (e.response?.data?.message ?? 'An error occurred.');
}

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
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
  const params = showDeleted.value ? { include_deleted: 1 } : {};
  const res = await api.get('/v1/rbac/users', { params });
  users.value = res.data.data;
}
async function loadPending() {
  const res = await api.get('/v1/rbac/users/pending');
  pending.value = res.data.data;
}

async function switchTab(key) {
  activeTab.value = key;
  formError.value = '';
  loading.value   = true;
  try {
    if (key === 'pending')     await loadPending();
    if (key === 'roles')       await Promise.all([loadRoles(), loadPermissions()]);
    if (key === 'permissions') await loadPermissions();
    if (key === 'users')       await Promise.all([loadUsers(), loadRoles()]);
  } finally {
    loading.value = false;
  }
}

// --- Approve ---
async function approveUser(user) {
  try {
    const res = await api.put(`/v1/rbac/users/${user.id}/approve`);
    // Remove from pending list
    pending.value = pending.value.filter(u => u.id !== user.id);
    // Update in users list if loaded
    const idx = users.value.findIndex(u => u.id === user.id);
    if (idx !== -1) users.value[idx] = { ...users.value[idx], ...res.data.data };
  } catch (e) { handleError(e); }
}

// --- Restore deleted user ---
async function restoreUser(user) {
  try {
    const res = await api.post(`/v1/rbac/users/${user.id}/restore`);
    const idx = users.value.findIndex(u => u.id === user.id);
    if (idx !== -1) users.value[idx] = { ...users.value[idx], ...res.data.data, deleted_at: null };
  } catch (e) { handleError(e); }
}

// --- Restore access for inactivity-flagged user ---
async function restoreAccess(user) {
  if (!confirm(`Restore access for "${user.name}"? They will be able to log in again.`)) return;
  try {
    const res = await api.put(`/v1/rbac/users/${user.id}/restore-access`);
    const idx = users.value.findIndex(u => u.id === user.id);
    if (idx !== -1) users.value[idx] = { ...users.value[idx], ...res.data.data, inactivity_flagged_at: null };
  } catch (e) { handleError(e); }
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
  filterText.value   = '';
}
async function savePermSync() {
  try {
    const res = await api.put(`/v1/rbac/roles/${permModal.role.id}/permissions`, { permissions: permModal.selected });
    const idx = roles.value.findIndex(r => r.id === permModal.role.id);
    if (idx !== -1) roles.value[idx] = res.data.data;
    permModal.open = false;
  } catch (e) { handleError(e); }
}

// --- Users ---
async function createUser() {
  formError.value  = '';
  userCreatedMsg.value = '';
  try {
    const res = await api.post('/v1/rbac/users', userForm);
    users.value.push(res.data.data);
    userForm.name = ''; userForm.username = ''; userForm.email = '';
    userForm.password = ''; userForm.password_confirmation = ''; userForm.role = '';
    showUserPw.value = false; showUserConfPw.value = false;
    userCreatedMsg.value = res.data.message ?? 'User created. They must log in once for admin approval.';
  } catch (e) { handleError(e); }
}
async function deleteUser(user) {
  if (!confirm(`Delete user "${user.name}"? This is reversible — they can be restored later.`)) return;
  try {
    await api.delete(`/v1/rbac/users/${user.id}`);
    if (showDeleted.value) {
      const idx = users.value.findIndex(u => u.id === user.id);
      if (idx !== -1) users.value[idx] = { ...users.value[idx], deleted_at: new Date().toISOString() };
    } else {
      users.value = users.value.filter(u => u.id !== user.id);
    }
  } catch (e) { handleError(e); }
}
function openEditUserModal(user) {
  editUserModal.user  = user;
  editUserModal.error = '';
  editUserForm.name   = user.name;
  editUserForm.username = user.username;
  editUserForm.email  = user.email ?? '';
  editUserForm.password = '';
  editUserForm.password_confirmation = '';
  showEditPw.value = false; showEditConfPw.value = false;
  editUserModal.open  = true;
}
function closeEditUserModal() {
  editUserModal.open = false;
  editUserModal.user = null;
}
async function saveEditUser() {
  editUserModal.error = '';
  try {
    const payload = {
      name: editUserForm.name,
      username: editUserForm.username,
      email: editUserForm.email || null,
    };
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
    const res = await api.put(`/v1/rbac/users/${roleModal.user.id}/roles`, { roles: roleModal.selected });
    const idx = users.value.findIndex(u => u.id === roleModal.user.id);
    if (idx !== -1) users.value[idx] = res.data.data;
    roleModal.open = false;
  } catch (e) { handleError(e); }
}

onMounted(() => switchTab('pending'));
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1200px; }

/* ── Page header ── */
.page-head { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Tab bar ── */
.view-tabs {
  display: inline-flex; gap: 4px; background: var(--surface); border-radius: 999px;
  padding: 5px; border: 1px solid var(--border-soft); margin-bottom: 20px;
  box-shadow: var(--shadow-xs); flex-wrap: wrap;
}
.tab-btn { padding: 8px 18px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text-2); border-radius: 999px; transition: color 0.15s, background 0.15s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
.tab-btn:hover { color: var(--text-1); background: var(--surface-2); }
.tab-active { color: var(--primary-on) !important; background: var(--primary) !important; box-shadow: 0 4px 12px -4px rgba(124,58,237,0.45); }
.tab-count-chip { font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: rgba(0,0,0,0.08); color: inherit; }
.tab-active .tab-count-chip { background: rgba(255,255,255,0.22); }
.tab-count-alert { background: #ef4444 !important; color: white !important; }

/* ── Table wrap (replaces .panel) ── */
.table-wrap {
  background: var(--surface); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft);
  margin-bottom: 16px; overflow: hidden;
}
.table-header-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 22px; border-bottom: 1px solid var(--border-soft);
}
.table-header-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.count-badge  { background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 999px; }
.toggle-deleted { margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-3); cursor: pointer; }
.toggle-deleted input { accent-color: var(--primary); }

/* ── Forms ── */
.form-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; padding: 20px 24px; }
.form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-field  { display: flex; flex-direction: column; gap: 5px; }
.form-field label { font-size: 12px; font-weight: 600; color: var(--text-2); }
.form-field input, .form-field select {
  height: 38px; padding: 0 12px;
  border: 1.5px solid var(--border); border-radius: 8px;
  font-size: 13px; color: var(--text-1); outline: none; transition: border-color 0.15s;
}
.form-field input:focus, .form-field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.optional { font-weight: 400; color: var(--text-3); font-size: 11px; margin-left: 4px; }

.form-error   { margin: 0 24px; padding: 10px 14px; background: #fef2f2; color: #dc2626; font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid #fecaca; }
.form-success { margin: 0 24px; padding: 10px 14px; background: #f0fdf4; color: #15803d; font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid #bbf7d0; }

.pw-wrap { display: flex; align-items: center; border: 1.5px solid var(--border); border-radius: 8px; overflow: hidden; transition: border-color 0.15s; }
.pw-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.pw-wrap input { flex: 1; min-width: 0; height: 38px; padding: 0 12px; border: none !important; box-shadow: none !important; outline: none; background: transparent; font-size: 13px; color: var(--text-1); }
.pw-wrap .edit-input { border: none !important; box-shadow: none !important; height: 38px; border-radius: 0; }
.pw-toggle { flex-shrink: 0; width: 36px; height: 38px; padding: 0; border: none; border-left: 1px solid var(--border); background: transparent; color: var(--text-3); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: color 0.15s, background 0.15s; }
.pw-toggle:hover { color: #475569; background: var(--app-bg); }
.pw-hints { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px; }
.hint { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; }
.hint.ok   { background: #dcfce7; color: #166534; }
.hint.fail { background: #fee2e2; color: #991b1b; }

.form-actions { display: flex; gap: 8px; padding: 16px 24px 20px; border-top: 1px solid var(--border); }

/* ── Buttons ── */
.btn { height: 38px; padding: 0 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.btn-primary { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(124,58,237,0.45); }
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
.btn-primary:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-ghost { background: var(--surface-2); color: var(--text-2); }
.btn-ghost:hover { background: var(--border); }

/* ── Table ── */
table { width: 100%; border-collapse: collapse; }
thead th { background: var(--app-bg); color: var(--text-2); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 11px 16px; border-bottom: 1px solid var(--border); text-align: left; }
tbody td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-1); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--app-bg); }
.num { color: var(--text-3); font-size: 12px; width: 40px; }
.muted { color: var(--text-3); }
.empty-state { text-align: center; padding: 40px; color: var(--text-3); font-size: 13px; }
.role-name { font-weight: 600; color: var(--text-1); }
.user-name-cell { font-weight: 500; color: var(--text-1); }
.date-cell { font-size: 12px; white-space: nowrap; }
.perm-code { background: var(--app-bg); color: var(--text-1); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; }
.username-code { background: #f0f4ff; color: #4338ca; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; }
.row-deleted td { opacity: 0.5; }

/* ── Tags ── */
.tag-wrap { display: flex; flex-wrap: wrap; gap: 4px; }
.tag { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 12px; }
.tag-blue   { background: #dbeafe; color: #1d4ed8; }
.tag-purple { background: #ede9fe; color: #6d28d9; }
.tag-green  { background: #dcfce7; color: #15803d; }
.tag-orange { background: #fff7ed; color: #c2410c; }
.tag-gray   { background: #f1f5f9; color: #64748b; }
.tag-slate  { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

/* ── Action buttons ── */
.actions-cell { white-space: nowrap; width: 1%; }
.act-btn { height: 28px; padding: 0 11px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; margin-right: 4px; transition: all 0.12s; }
.act-btn:last-child { margin-right: 0; }
.act-edit   { background: #fef9c3; color: #854d0e; }
.act-edit:hover   { background: #fde68a; }
.act-purple { background: #ede9fe; color: #6d28d9; }
.act-purple:hover { background: #ddd6fe; }
.act-red    { background: #fee2e2; color: #991b1b; }
.act-red:hover    { background: #fecaca; }
.act-green  { background: #dcfce7; color: #15803d; }
.act-green:hover  { background: #bbf7d0; }

/* ── Pending empty state ── */
.empty-banner { text-align: center; padding: 48px 24px; }
.empty-icon  { font-size: 36px; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
.empty-sub   { font-size: 13px; color: var(--text-3); }

/* ── Pending row highlight ── */
.pending-row { border-left: 3px solid #f59e0b; }

/* ── Modals ── */
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.45); display: flex; align-items: center; justify-content: center; z-index: 100; padding: 20px; }
.modal { background: var(--surface); border-radius: 14px; width: 460px; max-width: 100%; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-wide { width: 600px; }
.modal-head { display: flex; align-items: flex-start; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border); }
.modal-title { font-size: 15px; font-weight: 700; color: var(--text-1); }
.modal-sub   { font-size: 12px; color: var(--text-3); margin-top: 2px; }
.modal-close { width: 28px; height: 28px; border-radius: 6px; border: none; background: var(--app-bg); color: var(--text-2); font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.modal-close:hover { background: var(--border); }
.modal-body { flex: 1; overflow-y: auto; padding: 16px 24px; display: flex; flex-direction: column; gap: 6px; }
.modal-form { padding: 20px 24px; gap: 0; }
.check-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: background 0.1s; }
.check-row:hover { background: var(--app-bg); }
.check-row input { accent-color: var(--primary); width: 16px; height: 16px; flex-shrink: 0; }
.check-label { font-size: 13px; color: var(--text-2); }
.modal-foot { display: flex; align-items: center; gap: 8px; padding: 16px 24px; border-top: 1px solid var(--border); }
.selected-count { font-size: 12px; color: var(--text-3); flex: 1; }
.modal-foot .btn { height: 36px; }

.edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
.edit-input { height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; width: 100%; box-sizing: border-box; }
.edit-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }

@media (max-width: 1024px) { .page { padding: 20px 16px; } .form-grid-3 { grid-template-columns: 1fr 1fr; } }
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .view-tabs { flex-wrap: wrap; }
  .table-wrap { overflow-x: auto; }
  table { min-width: 700px; }
  .form-grid, .form-grid-3, .edit-grid { grid-template-columns: 1fr; }
  .modal { width: 95vw; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } .form-actions { flex-wrap: wrap; } }

/* ── Permissions tab: info banner ── */
.perm-info-banner {
  display: flex; align-items: flex-start; gap: 12px;
  background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
  padding: 14px 18px; margin-bottom: 16px;
}
.perm-info-icon { color: #3b82f6; flex-shrink: 0; margin-top: 1px; }
.perm-info-title { font-size: 13px; font-weight: 700; color: #1e40af; margin-bottom: 3px; }
.perm-info-sub { font-size: 12px; color: #3b82f6; line-height: 1.5; }
.perm-info-sub code { background: #dbeafe; padding: 1px 5px; border-radius: 4px; font-size: 11px; }
.perm-info-sub strong { color: #1d4ed8; }

/* ── Roles table: permission summary ── */
.perm-summary { display: flex; flex-wrap: wrap; gap: 4px; cursor: pointer; }
.perm-summary:hover .tag { opacity: 0.85; }
.tag-more { background: #e0e7ff; color: #4338ca; cursor: pointer; }
.tag-more:hover { background: #c7d2fe; }

/* ── Permission modal: grouped layout ── */
.perm-modal-body { display: block !important; padding: 12px 16px !important; }
.perm-search-wrap { margin-bottom: 10px; }
.perm-search {
  width: 100%; height: 34px; padding: 0 10px 0 32px;
  border: 1.5px solid var(--border); border-radius: 8px;
  font-size: 13px; outline: none; color: var(--text-1);
  background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath stroke-linecap='round' d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
  box-sizing: border-box;
}
.perm-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }

.perm-group { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 6px; }
.perm-group-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 12px; background: var(--app-bg);
}
.perm-group-label { display: flex; align-items: center; gap: 8px; cursor: pointer; flex: 1; min-width: 0; }
.perm-group-check { width: 14px; height: 14px; accent-color: #6366f1; flex-shrink: 0; cursor: pointer; }
.perm-group-name { font-size: 12px; font-weight: 700; color: var(--text-1); text-transform: capitalize; }
.perm-group-count { font-size: 10px; color: var(--text-3); font-weight: 700; background: var(--border); padding: 1px 7px; border-radius: 8px; flex-shrink: 0; }

.perm-actions-row { display: flex; flex-wrap: wrap; gap: 5px; padding: 8px 12px; background: var(--surface); }
.perm-action-chip {
  display: flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 6px; border: 1.5px solid var(--border);
  font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-2);
  background: var(--surface); transition: all 0.1s; user-select: none;
}
.perm-action-chip input[type="checkbox"] { width: 13px; height: 13px; accent-color: #6366f1; flex-shrink: 0; }
.perm-action-chip:hover { border-color: #a5b4fc; color: #4338ca; background: #eef2ff; }
.perm-action-chip-on { border-color: #6366f1; background: #eef2ff; color: #4338ca; }
</style>
