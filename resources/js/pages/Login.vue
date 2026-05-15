<template>
  <div class="login-wrap">
    <div class="login-card">
      <div class="logo-area">
        <div class="brand-icon">B</div>
        <div class="brand-label">
          <span class="brand-name">Bluedale</span>
          <span class="brand-sub">CRM Platform</span>
        </div>
      </div>

      <form @submit.prevent="handleLogin">
        <div v-if="error" class="error-box">{{ error }}</div>

        <div class="field">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="Enter your email" required autofocus>
        </div>

        <div class="field">
          <label>Password</label>
          <input v-model="form.password" type="password" placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn-login" :disabled="loading">
          {{ loading ? 'Logging in…' : 'Login' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const router = useRouter();

const form = ref({ email: '', password: '' });
const error = ref('');
const loading = ref(false);

async function handleLogin() {
  error.value = '';
  loading.value = true;
  try {
    const res = await api.post('/auth/login', form.value);
    localStorage.setItem('crm_token', res.data.token);
    localStorage.setItem('crm_user', JSON.stringify(res.data.user));
    router.push('/');
  } catch (e) {
    const msg = e.response?.data?.message
      ?? e.response?.data?.errors?.email?.[0]
      ?? 'Login failed. Please check your credentials.';
    error.value = msg;
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-wrap {
  min-height: 100vh;
  background: url('/building.png') center/cover no-repeat, linear-gradient(135deg, #0f172a, #1e3a5f);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.login-card {
  background: rgba(255,255,255,0.96);
  border-radius: 20px;
  padding: 48px 44px;
  width: 100%; max-width: 420px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.logo-area {
  display: flex; align-items: center; gap: 14px;
  margin-bottom: 36px; justify-content: center;
}
.brand-icon {
  width: 48px; height: 48px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 800; color: white;
}
.brand-label { display: flex; flex-direction: column; line-height: 1; }
.brand-name { font-size: 20px; font-weight: 800; color: #1e293b; }
.brand-sub { font-size: 12px; color: #64748b; margin-top: 3px; }

.error-box {
  background: #fee2e2; color: #991b1b;
  border-radius: 8px; padding: 10px 14px;
  font-size: 13px; margin-bottom: 18px;
}
.field { margin-bottom: 18px; }
.field label {
  display: block; font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.6px;
  color: #475569; margin-bottom: 6px;
}
.field input {
  width: 100%; height: 44px; padding: 0 14px;
  border: 1.5px solid #e2e8f0; border-radius: 9px;
  font-size: 14px; color: #1e293b; outline: none;
  box-sizing: border-box; background: white;
}
.field input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }

.btn-login {
  width: 100%; height: 46px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: white; border: none; border-radius: 10px;
  font-size: 15px; font-weight: 700; cursor: pointer;
  margin-top: 8px; transition: opacity 0.2s;
}
.btn-login:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-login:hover:not(:disabled) { opacity: 0.92; }

/* Responsive */
@media (max-width: 480px) {
  .login-card { padding: 32px 24px; border-radius: 14px; }
}
</style>
