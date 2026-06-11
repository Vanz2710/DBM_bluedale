<template>
  <div class="page">
    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Page header -->
      <div class="page-header">
        <h2 class="page-title">Profile Settings</h2>
        <p class="page-subtitle">Manage your account details and preferences.</p>
      </div>

      <div class="layout-grid">

        <!-- ── Left col: Personal Information ────────────────── -->
        <div class="col-main">
          <div class="card card--accent">

            <h3 class="card-heading">
              <svg class="h-icon h-icon--primary" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 7H4a2 2 0 0 0-2 2v10c0 1.1.9 2 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM4 9h16v2H4V9zm0 10v-6h16v6H4z"/>
                <path d="M6 17h4v-2H6zm6 0h6v-2h-6z"/>
              </svg>
              Personal Information
            </h3>

            <!-- Avatar row -->
            <div class="avatar-row">
              <div class="avatar-ring">
                <div class="avatar-circle">{{ initials }}</div>
              </div>
              <div class="avatar-meta">
                <p class="avatar-name">{{ profile.name || '—' }}</p>
                <p class="avatar-hint">Your name initials are used as your avatar</p>
              </div>
            </div>

            <div v-if="profileMsg" :class="['alert', profileMsg.type === 'error' ? 'alert-error' : 'alert-success']">
              <svg viewBox="0 0 20 20" fill="currentColor"><path v-if="profileMsg.type === 'error'" fill-rule="evenodd" d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-.75-9.75a.75.75 0 0 1 1.5 0v3a.75.75 0 0 1-1.5 0v-3zm.75 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z" clip-rule="evenodd"/><path v-else fill-rule="evenodd" d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
              {{ profileMsg.text }}
            </div>

            <form @submit.prevent="saveProfile">
              <div class="fields-grid">

                <div class="field field--full">
                  <label class="field-label">Full Name <span class="req">*</span></label>
                  <input type="text" v-model="profile.name" required maxlength="255"
                    class="pill-input" placeholder="Your full name" />
                </div>

                <div class="field field--full">
                  <label class="field-label">Email Address <span class="req">*</span></label>
                  <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <input type="email" v-model="profile.email" required maxlength="255"
                      class="pill-input pill-input--icon" placeholder="you@example.com" />
                  </div>
                </div>

                <div class="field field--half">
                  <label class="field-label">Phone Number</label>
                  <input type="text" v-model="profile.phone" maxlength="50"
                    class="pill-input" placeholder="e.g. +60 12-345 6789" />
                </div>

                <div class="field field--half">
                  <label class="field-label">Designation</label>
                  <input type="text" v-model="profile.job_title" maxlength="100"
                    class="pill-input" placeholder="e.g. Sales Manager" />
                </div>

                <div class="field field--full">
                  <label class="field-label">Role</label>
                  <input type="text" :value="profile.roles?.join(', ') || '—'"
                    class="pill-input pill-input--disabled" disabled />
                </div>

              </div>

              <div class="card-footer">
                <button type="submit" class="btn-primary" :disabled="savingProfile">
                  <span v-if="savingProfile" class="btn-spinner"></span>
                  {{ savingProfile ? 'Saving…' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- ── Right col: Security + Details ─────────────────── -->
        <div class="col-side">

          <!-- Account Security -->
          <div class="card">
            <h3 class="card-heading">
              <svg class="h-icon h-icon--secondary" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
              </svg>
              Account Security
            </h3>

            <div class="pw-row">
              <div>
                <p class="pw-title">Password</p>
                <p class="pw-hint">Update your account password</p>
              </div>
              <button type="button" class="btn-outline btn-sm" @click="showPwForm = !showPwForm">
                <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                  <path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                </svg>
                {{ showPwForm ? 'Cancel' : 'Change' }}
              </button>
            </div>

            <transition name="expand">
              <div v-if="showPwForm" class="pw-form-wrap">
                <div class="pw-divider"></div>

                <div v-if="pwMsg" :class="['alert', pwMsg.type === 'error' ? 'alert-error' : 'alert-success']">
                  <svg viewBox="0 0 20 20" fill="currentColor"><path v-if="pwMsg.type === 'error'" fill-rule="evenodd" d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-.75-9.75a.75.75 0 0 1 1.5 0v3a.75.75 0 0 1-1.5 0v-3zm.75 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z" clip-rule="evenodd"/><path v-else fill-rule="evenodd" d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                  {{ pwMsg.text }}
                </div>

                <form @submit.prevent="changePassword" class="pw-form">
                  <div class="field">
                    <label class="field-label">Current Password <span class="req">*</span></label>
                    <input type="password" v-model="pw.current" required autocomplete="current-password"
                      class="pill-input" placeholder="Current password" />
                  </div>
                  <div class="field">
                    <label class="field-label">New Password <span class="req">*</span></label>
                    <input type="password" v-model="pw.password" required minlength="8"
                      autocomplete="new-password" class="pill-input" placeholder="Min. 8 characters" />
                  </div>
                  <div class="field">
                    <label class="field-label">Confirm New Password <span class="req">*</span></label>
                    <input type="password" v-model="pw.password_confirmation" required
                      autocomplete="new-password" class="pill-input" placeholder="Repeat new password" />
                  </div>
                  <div class="pw-footer">
                    <button type="submit" class="btn-primary btn-sm" :disabled="savingPw">
                      <span v-if="savingPw" class="btn-spinner"></span>
                      {{ savingPw ? 'Updating…' : 'Update Password' }}
                    </button>
                  </div>
                </form>
              </div>
            </transition>
          </div>

          <!-- Account Details -->
          <div class="card">
            <h3 class="card-heading">
              <svg class="h-icon h-icon--tertiary" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
              </svg>
              Account Details
            </h3>

            <div class="detail-list">
              <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value">{{ profile.email }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Role(s)</span>
                <span class="detail-value roles-val">
                  <span v-for="role in profile.roles" :key="role" class="role-badge">{{ role }}</span>
                  <span v-if="!profile.roles?.length" class="detail-dim">—</span>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Member Since</span>
                <span class="detail-value detail-dim">{{ profile.created_at ?? '—' }}</span>
              </div>
              <div class="detail-row no-border">
                <span class="detail-label">Last Updated</span>
                <span class="detail-value detail-dim">{{ profile.updated_at ?? '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Your Permissions -->
          <div class="card">
            <h3 class="card-heading">
              <svg class="h-icon h-icon--primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Your Permissions
            </h3>

            <!-- Super-admin: full access -->
            <div v-if="profile.roles?.includes('super-admin')" class="perm-full-access">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
              </svg>
              Full system access — super-admin bypasses all permission checks.
            </div>

            <!-- Regular users -->
            <template v-else>
              <div v-if="!activePermGroups.length" class="perm-empty">No permissions assigned. Contact your administrator.</div>
              <div v-else class="perm-list">
                <div v-for="group in activePermGroups" :key="group.label" class="perm-group">
                  <span class="perm-group-label">{{ group.label }}</span>
                  <div class="perm-group-chips">
                    <span v-for="p in group.activePerms" :key="p" class="perm-chip">{{ formatPerm(p) }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>

        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading       = ref(true);
const savingProfile = ref(false);
const savingPw      = ref(false);
const profileMsg    = ref(null);
const pwMsg         = ref(null);
const showPwForm    = ref(false);

const profile = ref({ name: '', email: '', phone: '', job_title: '', roles: [], permissions: [], created_at: null, updated_at: null });
const pw      = ref({ current: '', password: '', password_confirmation: '' });

const PERM_GROUPS = [
  { label: 'Contacts',    perms: ['view contacts','create contacts','edit contacts','delete contacts','import contacts'] },
  { label: 'To-Dos',     perms: ['view todos','create todos','edit todos','delete todos'] },
  { label: 'Deals',      perms: ['view deals','create deals','edit deals','delete deals'] },
  { label: 'Forecasts',  perms: ['view forecasts','create forecasts','edit forecasts','delete forecasts','view forecast summary'] },
  { label: 'Projects',   perms: ['view projects','create projects','edit projects','delete projects'] },
  { label: 'Follow-Ups', perms: ['view followups','create followups','edit followups','delete followups'] },
  { label: 'Analytics',  perms: ['view analytics','view summary','view data-health','view performance'] },
  { label: 'Marketing',  perms: ['manage social-media','manage posting-calendar','manage email-campaigns','manage product-availability'] },
  { label: 'Admin Tools', perms: ['manage lookups','manage webhooks','manage territories'] },
];

const activePermGroups = computed(() => {
  const userPerms = new Set(profile.value.permissions ?? []);
  return PERM_GROUPS
    .map(g => ({ ...g, activePerms: g.perms.filter(p => userPerms.has(p)) }))
    .filter(g => g.activePerms.length > 0);
});

function formatPerm(perm) {
  return perm.replace(/-/g, ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

const initials = computed(() => {
  const parts = (profile.value.name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return parts.length === 1
    ? parts[0][0].toUpperCase()
    : (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

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

    const stored = JSON.parse(localStorage.getItem('crm_user') || '{}');
    stored.name  = res.data.user.name;
    stored.email = res.data.user.email;
    localStorage.setItem('crm_user', JSON.stringify(stored));
    window.dispatchEvent(new CustomEvent('user-profile-updated'));
  } catch (err) {
    const errors = err.response?.data?.errors;
    profileMsg.value = errors
      ? { type: 'error', text: Object.values(errors).flat().join(' ') }
      : { type: 'error', text: err.response?.data?.message || 'Failed to save profile.' };
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
    showPwForm.value = false;
  } catch (err) {
    const errors = err.response?.data?.errors;
    pwMsg.value = errors
      ? { type: 'error', text: Object.values(errors).flat().join(' ') }
      : { type: 'error', text: err.response?.data?.message || 'Failed to change password.' };
  } finally {
    savingPw.value = false;
  }
}
</script>

<style scoped>
/* ── Local token aliases mapped onto the global theme ────── */
/* These custom names are kept so the rest of the file keeps working,
   but they now reference the global :root / [data-theme=dark] tokens
   so the page themes correctly and is dark-mode safe. */
.page {
  --primary-fixed:      var(--primary-soft);
  --secondary:          var(--info);
  --tertiary:           var(--success);
  --surface-bright:     var(--surface);
  --surface-low:        var(--surface-2);
  --surface-lowest:     var(--surface);
  --on-surface:         var(--text-1);
  --on-surface-variant: var(--text-2);
  --outline-variant:    var(--border);
  --error:              var(--danger);
  --error-container:    var(--danger-soft);

  padding: 28px 32px;
  max-width: 1200px;
  background: var(--app-bg);
  min-height: 100%;
  box-sizing: border-box;
}

/* ── Page header ─────────────────────────────────────────── */
.page-header { margin-bottom: 28px; }
.page-title {
  margin: 0 0 4px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--on-surface);
}
.page-subtitle {
  margin: 0;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--on-surface-variant);
}

/* ── Grid ────────────────────────────────────────────────── */
.layout-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  align-items: start;
}

/* ── Cards ───────────────────────────────────────────────── */
.card {
  background: var(--surface-lowest);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-soft);
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  position: relative;
  overflow: hidden;
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
.card--accent::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(to right, var(--primary), #39b8fd);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

/* ── Card heading ────────────────────────────────────────── */
.card-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.015em;
  color: var(--on-surface);
  margin: 0 0 24px;
}
.h-icon { width: 22px; height: 22px; flex-shrink: 0; }
.h-icon--primary  { color: var(--primary); }
.h-icon--secondary { color: var(--secondary); }
.h-icon--tertiary  { color: var(--tertiary); }

/* ── Avatar ──────────────────────────────────────────────── */
.avatar-row {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 28px;
}
.avatar-ring {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  border: 2px solid var(--outline-variant);
  overflow: hidden;
  flex-shrink: 0;
}
.avatar-circle {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, var(--primary), #39b8fd);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 800;
  color: white;
  letter-spacing: 1px;
}
.avatar-name {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 700;
  color: var(--on-surface);
}
.avatar-hint {
  margin: 0;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--on-surface-variant);
}

/* ── Alerts ──────────────────────────────────────────────── */
.alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 16px;
}
.alert svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-error   { background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger-soft); }
.alert-success { background: var(--success-soft); color: var(--success); border: 1px solid var(--success-soft); }

/* ── Fields ──────────────────────────────────────────────── */
.fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 20px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field--full { grid-column: 1 / -1; }
.field--half { grid-column: span 1; }

.field-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--on-surface-variant);
}
.req { color: var(--error); }

/* ── Pill inputs ─────────────────────────────────────────── */
.pill-input {
  width: 100%;
  border-radius: 9999px;
  border: 1.5px solid var(--border);
  background: var(--surface-bright);
  padding: 8px 16px;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--on-surface);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
}
.pill-input::placeholder { color: var(--text-3); }
.pill-input:hover:not(:focus):not(:disabled) {
  border-color: var(--primary);
}
.pill-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-soft);
}
.pill-input--disabled {
  background: var(--surface-low);
  border-color: rgba(204, 195, 216, 0.2);
  color: var(--on-surface-variant);
  cursor: not-allowed;
}

/* Input with icon */
.input-icon-wrap { position: relative; }
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: var(--on-surface-variant);
  pointer-events: none;
}
.pill-input--icon { padding-left: 40px; }

/* ── Card footer ─────────────────────────────────────────── */
.card-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

/* ── Buttons ─────────────────────────────────────────────── */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 24px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 9999px;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px 0 var(--focus-ring);
  transition: background 0.15s, box-shadow 0.15s, opacity 0.15s;
}
.btn-primary:hover:not(:disabled) {
  background: var(--primary-hover);
  box-shadow: 0 6px 20px -4px var(--focus-ring);
}
.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-outline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  background: transparent;
  color: var(--primary);
  border: 1.5px solid var(--primary-soft);
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.btn-outline:hover {
  background: var(--primary-fixed);
  border-color: var(--primary);
}

.btn-sm { padding: 7px 18px; font-size: 12.5px; }

.btn-spinner {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Password section ────────────────────────────────────── */
.pw-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.pw-title {
  margin: 0 0 2px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--on-surface);
}
.pw-hint {
  margin: 0;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--on-surface-variant);
}
.pw-divider {
  height: 1px;
  background: var(--border);
  margin: 16px 0;
}
.pw-form-wrap { overflow: hidden; }
.pw-form { display: flex; flex-direction: column; gap: 14px; }
.pw-footer { display: flex; justify-content: flex-end; margin-top: 4px; }

/* Expand transition */
.expand-enter-active, .expand-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.expand-enter-from, .expand-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ── Account detail list ─────────────────────────────────── */
.detail-list { display: flex; flex-direction: column; }
.detail-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 0;
  border-bottom: 1px solid var(--border-soft);
}
.detail-row.no-border { border-bottom: none; }
.detail-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--on-surface-variant);
  flex-shrink: 0;
  padding-top: 2px;
}
.detail-value {
  font-size: 13px;
  font-weight: 500;
  color: var(--on-surface);
  text-align: right;
}
.detail-dim { color: var(--on-surface-variant); }
.roles-val { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }

.role-badge {
  background: var(--primary-fixed);
  color: var(--primary);
  border-radius: 9999px;
  padding: 2px 10px;
  font-size: 11px;
  font-weight: 700;
}

/* ── Permissions card ────────────────────────────────────── */
.perm-full-access {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--primary-fixed);
  color: var(--primary);
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
}
.perm-empty {
  font-size: 12.5px;
  color: var(--on-surface-variant);
  font-weight: 500;
}
.perm-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 280px;
  overflow-y: auto;
  padding-right: 2px;
}
.perm-group { display: flex; flex-direction: column; gap: 5px; }
.perm-group-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--on-surface-variant);
}
.perm-group-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.perm-chip {
  background: var(--primary-fixed);
  color: var(--primary);
  border-radius: 999px;
  padding: 2px 9px;
  font-size: 11px;
  font-weight: 600;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 960px) {
  .layout-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .page { padding: 16px; }
  .fields-grid { grid-template-columns: 1fr; }
  .field--half { grid-column: 1 / -1; }
}
</style>
