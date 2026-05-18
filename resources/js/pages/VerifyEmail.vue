<template>
  <div class="verify-page">
    <div class="verify-card">
      <div class="verify-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>

      <h1>Verify Your Email</h1>
      <p class="sub">We sent a verification link to</p>
      <p class="email-display">{{ email }}</p>
      <p class="hint">Click the link in that email to activate your account. Check your spam folder if you don't see it.</p>

      <div v-if="checking" class="status-msg checking">Checking verification status...</div>
      <div v-else-if="message" :class="['status-msg', messageType]">{{ message }}</div>

      <div class="actions">
        <button class="btn btn-primary" @click="resend" :disabled="resending || cooldown > 0 || checking">
          {{ cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend Verification Email' }}
        </button>
        <button class="btn btn-secondary" @click="checkVerified" :disabled="checking || resending">
          I've Verified — Continue
        </button>
        <button class="btn btn-ghost" @click="logout" :disabled="checking || resending">
          Logout
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const router  = useRouter();
const email   = ref('');
const message = ref('');
const messageType = ref('');
const resending   = ref(false);
const checking    = ref(false);
const cooldown    = ref(0);
let cooldownTimer = null;

onMounted(async () => {
  const stored = JSON.parse(localStorage.getItem('crm_user') || 'null');
  email.value  = stored?.email ?? '';
  await refreshStatus();
});

onUnmounted(() => clearInterval(cooldownTimer));

async function refreshStatus(showMessage = false) {
  checking.value = true;
  try {
    const res  = await api.get('/auth/me');
    const user = res.data.user;
    localStorage.setItem('crm_user', JSON.stringify(user));
    window.dispatchEvent(new Event('user-profile-updated'));
    email.value = user.email;
    if (user.email_verified) {
      router.replace({ name: 'home' });
      return;
    }
    if (showMessage) {
      message.value     = 'Email not verified yet. Please click the link in your inbox.';
      messageType.value = 'error';
    }
  } catch {
    // Token invalid — the 401 interceptor in api.js will redirect to login
  } finally {
    checking.value = false;
  }
}

async function checkVerified() {
  message.value = '';
  await refreshStatus(true);
}

async function resend() {
  if (cooldown.value > 0) return;
  resending.value = true;
  message.value   = '';
  try {
    await api.post('/auth/email/resend');
    message.value     = 'Verification email sent. Please check your inbox.';
    messageType.value = 'success';
    startCooldown(60);
  } catch (e) {
    message.value     = e.response?.data?.message ?? 'Failed to send. Please try again.';
    messageType.value = 'error';
  } finally {
    resending.value = false;
  }
}

async function logout() {
  try { await api.post('/auth/logout'); } catch {}
  localStorage.removeItem('crm_token');
  localStorage.removeItem('crm_user');
  router.push({ name: 'login' });
}

function startCooldown(seconds) {
  clearInterval(cooldownTimer);
  cooldown.value = seconds;
  cooldownTimer  = setInterval(() => {
    if (--cooldown.value <= 0) clearInterval(cooldownTimer);
  }, 1000);
}
</script>

<style scoped>
.verify-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg, #f5f6fa);
  padding: 24px;
}

.verify-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0,0,0,.08);
  padding: 48px 40px;
  max-width: 440px;
  width: 100%;
  text-align: center;
}

.verify-icon {
  color: #5c6bc0;
  margin-bottom: 20px;
}

h1 {
  font-size: 22px;
  font-weight: 700;
  color: #1a1d2e;
  margin: 0 0 8px;
}

.sub {
  color: #6b7280;
  margin: 0 0 4px;
  font-size: 14px;
}

.email-display {
  font-weight: 600;
  color: #1a1d2e;
  margin: 0 0 12px;
  word-break: break-all;
}

.hint {
  color: #6b7280;
  font-size: 13px;
  margin: 0 0 20px;
  line-height: 1.5;
}

.status-msg {
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 16px;
}
.status-msg.success  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.status-msg.error    { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.status-msg.checking { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

.actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn {
  padding: 10px 20px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: opacity .15s;
}
.btn:disabled { opacity: .5; cursor: not-allowed; }

.btn-primary   { background: #5c6bc0; color: #fff; }
.btn-primary:not(:disabled):hover { background: #4a57a8; }

.btn-secondary { background: #e8eaf6; color: #3949ab; }
.btn-secondary:not(:disabled):hover { background: #d9dcf5; }

.btn-ghost { background: transparent; color: #6b7280; border: 1px solid #e5e7eb; }
.btn-ghost:not(:disabled):hover { background: #f9fafb; }
</style>
