<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">Global configuration for notifications and system behaviour</p>
      </div>
    </div>

    <div v-if="msg" :class="['msg-box', msg.type === 'error' ? 'error-box' : 'success-box']">
      {{ msg.text }}
    </div>

    <div v-if="loading" class="loading-wrap">
      <LoadingSpinner />
    </div>

    <div v-else class="settings-card">
      <div class="card-section">
        <h2 class="section-title">Notification Settings</h2>
        <p class="section-desc">Configure where system alert emails are delivered.</p>

        <div class="field-group">
          <label class="field-label" for="notif-email">Admin Notification Email</label>
          <p class="field-desc">Receives alerts for inactivity lockouts and new user approval requests.</p>
          <input
            id="notif-email"
            v-model="form.admin_notification_email"
            type="email"
            class="field-input"
            placeholder="admin@example.com"
          />
        </div>
      </div>

      <div class="card-footer">
        <button class="btn-save" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading = ref(true);
const saving  = ref(false);
const msg     = ref(null);

const form = reactive({ admin_notification_email: '' });

function showMsg(type, text) {
  msg.value = { type, text };
  setTimeout(() => { msg.value = null; }, 4000);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/v1/system-settings');
    for (const setting of data.settings) {
      if (setting.key in form) form[setting.key] = setting.value ?? '';
    }
  } catch {
    showMsg('error', 'Failed to load settings.');
  } finally {
    loading.value = false;
  }
});

async function save() {
  saving.value = true;
  try {
    await api.put('/v1/system-settings', { ...form });
    showMsg('success', 'Settings saved successfully.');
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      showMsg('error', Object.values(errors).flat().join(' '));
    } else {
      showMsg('error', 'Failed to save settings.');
    }
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.page { padding: 28px 32px; }
.page-header { margin-bottom: 28px; }
.page-title  { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

.msg-box    { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 20px; }
.success-box { background: var(--success-soft); color: var(--success); border: 1px solid var(--success-soft); }
.error-box   { background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger-soft); }

.loading-wrap { display: flex; justify-content: center; padding: 60px 0; }

.settings-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.card-section { padding: 28px 28px 20px; }

.section-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 6px; }
.section-desc  { font-size: 13px; color: var(--text-2); margin: 0 0 24px; }

.field-group  { display: flex; flex-direction: column; gap: 6px; }
.field-label  { font-size: 13px; font-weight: 600; color: var(--text-2); }
.field-desc   { font-size: 12px; color: var(--text-3); margin: 0; }
.field-input  {
  padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--text-1);
  background: var(--surface);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  max-width: 420px;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

.card-footer {
  padding: 16px 28px;
  background: var(--surface-2);
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 9px 22px;
  background: var(--primary);
  color: var(--primary-on);
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 6px 18px -6px var(--focus-ring);
  transition: background 0.15s;
}
.btn-save:hover:not(:disabled) { background: var(--primary-hover); }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 768px) { .page { padding: 20px 16px; } }
@media (max-width: 640px) { .page { padding: 16px 12px; } }
</style>
