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
.page { padding: 28px 32px; max-width: 720px; }
.page-header { margin-bottom: 28px; }
.page-title  { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 4px; }
.page-subtitle { font-size: 14px; color: #6b7280; margin: 0; }

.msg-box    { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
.success-box { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.error-box   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.loading-wrap { display: flex; justify-content: center; padding: 60px 0; }

.settings-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}

.card-section { padding: 28px 28px 20px; }

.section-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 6px; }
.section-desc  { font-size: 13px; color: #6b7280; margin: 0 0 24px; }

.field-group  { display: flex; flex-direction: column; gap: 6px; }
.field-label  { font-size: 13px; font-weight: 600; color: #374151; }
.field-desc   { font-size: 12px; color: #9ca3af; margin: 0; }
.field-input  {
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  color: #111827;
  outline: none;
  transition: border-color 0.15s;
  max-width: 420px;
}
.field-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

.card-footer {
  padding: 16px 28px;
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
}

.btn-save {
  padding: 9px 22px;
  background: #4f46e5;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-save:hover:not(:disabled) { background: #4338ca; }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
