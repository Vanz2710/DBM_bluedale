<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>My Profile</h1>
        <p>View and manage your account information</p>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Profile Info -->
      <div class="card">
        <div class="card-title">Profile Information</div>
        <div v-if="profileMsg" :class="['msg-box', profileMsg.type === 'error' ? 'error-box' : 'success-box']">{{ profileMsg.text }}</div>
        <form @submit.prevent="saveProfile">
          <div class="form-row">
            <div class="form-group">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" v-model="profile.name" required maxlength="255" />
            </div>
            <div class="form-group">
              <label>Email Address <span class="req">*</span></label>
              <input type="email" v-model="profile.email" required maxlength="255" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" v-model="profile.phone" maxlength="50" placeholder="e.g. +60 12-345 6789" />
            </div>
            <div class="form-group">
              <label>Job Title / Designation</label>
              <input type="text" v-model="profile.job_title" maxlength="100" placeholder="e.g. Sales Manager" />
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-save" :disabled="savingProfile">
              {{ savingProfile ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-title">Change Password</div>
        <div v-if="pwMsg" :class="['msg-box', pwMsg.type === 'error' ? 'error-box' : 'success-box']">{{ pwMsg.text }}</div>
        <form @submit.prevent="changePassword">
          <div class="form-group">
            <label>Current Password <span class="req">*</span></label>
            <input type="password" v-model="pw.current" required autocomplete="current-password" />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>New Password <span class="req">*</span></label>
              <input type="password" v-model="pw.password" required minlength="8" autocomplete="new-password" />
            </div>
            <div class="form-group">
              <label>Confirm New Password <span class="req">*</span></label>
              <input type="password" v-model="pw.password_confirmation" required autocomplete="new-password" />
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-save" :disabled="savingPw">
              {{ savingPw ? 'Updating…' : 'Update Password' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Account Info (read-only) -->
      <div class="card">
        <div class="card-title">Account Details</div>
        <div class="info-grid">
          <div class="info-row">
            <span class="info-label">Account Email</span>
            <span class="info-value">{{ profile.email }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Role(s)</span>
            <span class="info-value">
              <span v-for="role in profile.roles" :key="role" class="role-badge">{{ role }}</span>
              <span v-if="!profile.roles?.length" class="info-dim">—</span>
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">Account Created</span>
            <span class="info-value info-dim">{{ profile.created_at ?? '—' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Last Updated</span>
            <span class="info-value info-dim">{{ profile.updated_at ?? '—' }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading      = ref(true);
const savingProfile = ref(false);
const savingPw     = ref(false);
const profileMsg   = ref(null);
const pwMsg        = ref(null);

const profile = ref({ name: '', email: '', phone: '', job_title: '', roles: [], created_at: null, updated_at: null });
const pw = ref({ current: '', password: '', password_confirmation: '' });

onMounted(async () => {
  try {
    const res = await api.get('/v1/profile');
    profile.value = res.data.user;
  } finally {
    loading.value = false;
  }
});

async function saveProfile() {
  savingProfile.value = true;
  profileMsg.value = null;
  try {
    const res = await api.put('/v1/profile', {
      name:      profile.value.name,
      email:     profile.value.email,
      phone:     profile.value.phone || null,
      job_title: profile.value.job_title || null,
    });
    profile.value = res.data.user;
    profileMsg.value = { type: 'success', text: 'Profile updated successfully.' };

    // Keep localStorage in sync so sidebar name/email stays current
    const stored = JSON.parse(localStorage.getItem('crm_user') || '{}');
    stored.name  = res.data.user.name;
    stored.email = res.data.user.email;
    localStorage.setItem('crm_user', JSON.stringify(stored));
    window.dispatchEvent(new CustomEvent('user-profile-updated'));
  } catch (err) {
    const errors = err.response?.data?.errors;
    if (errors) {
      profileMsg.value = { type: 'error', text: Object.values(errors).flat().join(' ') };
    } else {
      profileMsg.value = { type: 'error', text: err.response?.data?.message || 'Failed to save profile.' };
    }
  } finally {
    savingProfile.value = false;
  }
}

async function changePassword() {
  pwMsg.value = null;
  if (pw.value.password !== pw.value.password_confirmation) {
    pwMsg.value = { type: 'error', text: 'New passwords do not match.' };
    return;
  }
  savingPw.value = true;
  try {
    await api.put('/v1/profile/password', {
      current_password:      pw.value.current,
      password:              pw.value.password,
      password_confirmation: pw.value.password_confirmation,
    });
    pwMsg.value = { type: 'success', text: 'Password changed successfully.' };
    pw.value = { current: '', password: '', password_confirmation: '' };
  } catch (err) {
    const errors = err.response?.data?.errors;
    if (errors) {
      pwMsg.value = { type: 'error', text: Object.values(errors).flat().join(' ') };
    } else {
      pwMsg.value = { type: 'error', text: err.response?.data?.message || 'Failed to change password.' };
    }
  } finally {
    savingPw.value = false;
  }
}
</script>

<style scoped>
.page { padding: 24px; max-width: 860px; }

.page-banner { margin-bottom: 24px; }
.page-banner h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; color: #1e293b; }
.page-banner p  { margin: 0; font-size: 13px; color: #64748b; }

.card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px; }
.card-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.form-group label { font-size: 12px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
.form-group input { height: 38px; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 0 12px; font-size: 14px; color: #1e293b; outline: none; transition: border-color 0.15s; }
.form-group input:focus { border-color: #3b82f6; }
.req { color: #ef4444; }

.form-row { display: flex; gap: 16px; margin-bottom: 16px; }
.form-row .form-group { margin-bottom: 0; }
.form-group { margin-bottom: 16px; }

.btn-row { display: flex; justify-content: flex-end; margin-top: 4px; }
.btn { height: 38px; padding: 0 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
.btn-save { background: #3b82f6; color: white; }
.btn-save:hover:not(:disabled) { background: #2563eb; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.msg-box { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.error-box   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.success-box { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

.info-grid { display: flex; flex-direction: column; gap: 0; }
.info-row { display: flex; align-items: center; padding: 11px 0; border-bottom: 1px solid #f8fafc; gap: 16px; }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; width: 160px; flex-shrink: 0; }
.info-value { font-size: 13px; color: #334155; display: flex; flex-wrap: wrap; gap: 6px; }
.info-dim { color: #94a3b8; }

.role-badge { background: #ede9fe; color: #7c3aed; border-radius: 6px; padding: 2px 10px; font-size: 12px; font-weight: 600; }

@media (max-width: 640px) {
  .page { padding: 16px; }
  .form-row { flex-direction: column; gap: 0; }
  .info-label { width: 120px; }
}
</style>
