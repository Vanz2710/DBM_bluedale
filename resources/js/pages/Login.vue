<template>
  <div class="login-root">

    <!-- ── Left panel ─────────────────────────────────────────── -->
    <div class="login-left">
      <div class="left-overlay"></div>

      <div class="left-content">
        <!-- Brand -->
        <div class="left-brand">
          <div class="left-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <rect x="2"  y="2"  width="9" height="9" rx="2" fill="white" opacity="0.95"/>
              <rect x="13" y="2"  width="9" height="9" rx="2" fill="white" opacity="0.5"/>
              <rect x="2"  y="13" width="9" height="9" rx="2" fill="white" opacity="0.5"/>
              <rect x="13" y="13" width="9" height="9" rx="2" fill="white" opacity="0.95"/>
            </svg>
          </div>
          <span class="left-brand-name">Bluedale<span class="left-brand-accent">CRM</span></span>
        </div>

        <!-- Photo showcase -->
        <div class="photo-showcase">
          <div class="photo-card photo-back">
            <img :src="img2" alt="KL Guide" draggable="false" />
          </div>
          <div class="photo-card photo-front">
            <img :src="img1" alt="KL Guide cover" draggable="false" />
          </div>
        </div>

        <!-- Tagline -->
        <div class="left-tagline">
          <p class="tagline-label">NOW AVAILABLE</p>
          <h2 class="tagline-title">The KL Guide</h2>
          <p class="tagline-sub">Malaysia's leading business & travel directory — March to May 2026</p>
        </div>

        <!-- Decorative dots -->
        <div class="deco-dots">
          <span v-for="i in 15" :key="i" class="dot"></span>
        </div>
      </div>
    </div>

    <!-- ── Right panel ────────────────────────────────────────── -->
    <div class="login-right">
      <div class="login-form-wrap">

        <!-- Brand (mobile only) -->
        <div class="mobile-brand">
          <div class="brand-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <rect x="2"  y="2"  width="9" height="9" rx="2" fill="currentColor" opacity="0.95"/>
              <rect x="13" y="2"  width="9" height="9" rx="2" fill="currentColor" opacity="0.5"/>
              <rect x="2"  y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.5"/>
              <rect x="13" y="13" width="9" height="9" rx="2" fill="currentColor" opacity="0.95"/>
            </svg>
          </div>
          <span class="mobile-brand-text">Bluedale<span class="mobile-brand-accent">CRM</span></span>
        </div>

        <h1 class="form-heading">Welcome back</h1>
        <p class="form-sub">Sign in to your workspace to continue</p>

        <!-- Error -->
        <div v-if="error" class="error-banner">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          {{ error }}
        </div>

        <form @submit.prevent="handleLogin" novalidate>

          <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input
                id="username"
                v-model="form.username"
                type="text"
                placeholder="Enter your username"
                required autofocus autocomplete="username"
                :disabled="loading"
              />
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </span>
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter your password"
                required autocomplete="current-password"
                :disabled="loading"
              />
              <button type="button" class="input-toggle" @click="showPassword = !showPassword" tabindex="-1">
                <svg v-if="!showPassword" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login" :disabled="loading">
            <span v-if="loading" class="btn-spinner"></span>
            <span>{{ loading ? 'Signing in…' : 'Sign In' }}</span>
          </button>

        </form>

        <p class="form-footer">Bluedale Group of Companies &copy; {{ year }}</p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const router       = useRouter();
const img1         = '/kl-guide-1.jpg';
const img2         = '/kl-guide-2.jpg';
const form         = ref({ username: '', password: '' });
const error        = ref('');
const loading      = ref(false);
const showPassword = ref(false);
const year         = new Date().getFullYear();

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    const res = await api.post('/auth/login', form.value);
    localStorage.setItem('crm_token', res.data.token);
    localStorage.setItem('crm_user', JSON.stringify(res.data.user));
    router.push('/');
  } catch (e) {
    error.value = e.response?.data?.message
      ?? e.response?.data?.errors?.username?.[0]
      ?? 'Login failed. Please check your credentials.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────── */
.login-root {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 55% 45%;
}

/* ── Left panel ───────────────────────────────────────────────── */
.login-left {
  position: relative;
  overflow: hidden;
  background: url('/building.png') center / cover no-repeat, #0f172a;
}

.left-overlay {
  position: absolute; inset: 0; z-index: 0;
  background: linear-gradient(
    155deg,
    rgba(10, 5, 30, 0.88) 0%,
    rgba(60, 20, 110, 0.70) 50%,
    rgba(10, 5, 30, 0.82) 100%
  );
}

.left-content {
  position: relative; z-index: 1;
  height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 48px 40px;
  gap: 0;
}

/* Brand top-left */
.left-brand {
  position: absolute; top: 32px; left: 36px;
  display: flex; align-items: center; gap: 10px;
}
.left-logo {
  width: 38px; height: 38px; border-radius: 10px;
  background: rgba(124,58,237,0.85);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 14px rgba(124,58,237,0.5);
}
.left-brand-name {
  font-size: 18px; font-weight: 800; color: #fff;
  letter-spacing: -0.3px;
}
.left-brand-accent { color: #a78bfa; }

/* Photo showcase */
.photo-showcase {
  position: relative;
  width: 300px; height: 260px;
  margin-bottom: 40px;
  flex-shrink: 0;
}

.photo-card {
  position: absolute;
  width: 260px;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.1);
  border: 3px solid rgba(255,255,255,0.12);
  transition: transform 0.3s ease;
}
.photo-card:hover { transform: scale(1.03) rotate(0deg) !important; }

.photo-back {
  top: 0; left: 0;
  transform: rotate(-6deg);
  z-index: 1;
}
.photo-front {
  top: 28px; left: 42px;
  transform: rotate(4deg);
  z-index: 2;
}

.photo-card img {
  width: 100%; height: 190px;
  object-fit: cover; display: block;
  pointer-events: none; user-select: none;
}

/* Tagline */
.left-tagline { text-align: center; color: #fff; }
.tagline-label {
  font-size: 10px; font-weight: 700; letter-spacing: 2px;
  text-transform: uppercase; color: #a78bfa; margin: 0 0 8px;
}
.tagline-title {
  font-size: 28px; font-weight: 800; margin: 0 0 10px;
  letter-spacing: -0.5px; color: #fff;
}
.tagline-sub {
  font-size: 13px; color: rgba(255,255,255,0.55);
  margin: 0; max-width: 280px; line-height: 1.6;
}

/* Decorative dot grid */
.deco-dots {
  position: absolute; bottom: 36px; right: 32px;
  display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;
}
.dot {
  width: 4px; height: 4px; border-radius: 50%;
  background: rgba(167,139,250,0.35);
}

/* ── Right panel ──────────────────────────────────────────────── */
.login-right {
  background: #fff;
  display: flex; align-items: center; justify-content: center;
  padding: 48px 40px;
}

.login-form-wrap {
  width: 100%; max-width: 360px;
  animation: form-rise 0.4s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes form-rise {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.mobile-brand { display: none; }

.form-heading {
  font-size: 26px; font-weight: 800; color: #1e293b;
  letter-spacing: -0.5px; margin: 0 0 6px;
}
.form-sub {
  font-size: 13.5px; color: #94a3b8; margin: 0 0 28px;
}

/* Error */
.error-banner {
  display: flex; align-items: flex-start; gap: 8px;
  background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;
  border-radius: 10px; padding: 11px 14px;
  font-size: 13px; margin-bottom: 20px; line-height: 1.5;
}
.error-banner svg { flex-shrink: 0; margin-top: 1px; }

/* Fields */
.field { margin-bottom: 18px; }
.field label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px;
  color: #64748b; margin-bottom: 7px;
}

.input-wrap { position: relative; display: flex; align-items: center; }
.input-icon {
  position: absolute; left: 13px; color: #94a3b8;
  display: flex; align-items: center; pointer-events: none;
}
.input-wrap input {
  width: 100%; height: 46px; padding: 0 42px 0 40px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 14px; color: #1e293b; background: #fff;
  outline: none; box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.input-wrap input:focus {
  border-color: #7c3aed;
  box-shadow: 0 0 0 3px rgba(124,58,237,0.14);
}
.input-wrap input:disabled { background: #f8fafc; cursor: not-allowed; }

.input-toggle {
  position: absolute; right: 12px; background: none; border: none;
  cursor: pointer; color: #94a3b8;
  display: flex; align-items: center; padding: 4px; border-radius: 6px;
  transition: color 0.15s;
}
.input-toggle:hover { color: #64748b; }

/* Button */
.btn-login {
  width: 100%; height: 48px; margin-top: 6px;
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  color: #fff; border: none; border-radius: 12px;
  font-size: 15px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 8px 24px -6px rgba(124,58,237,0.5);
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.15s;
}
.btn-login:hover:not(:disabled) {
  opacity: 0.93; transform: translateY(-1px);
  box-shadow: 0 12px 28px -6px rgba(124,58,237,0.6);
}
.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }

.btn-spinner {
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  animation: spin 0.7s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Footer */
.form-footer {
  text-align: center; font-size: 11px; color: #cbd5e1; margin: 28px 0 0;
}

/* ── Mobile ───────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .login-root { grid-template-columns: 1fr; }
  .login-left  { display: none; }
  .login-right { padding: 40px 28px; background: url('/building.png') center/cover no-repeat; }
  .login-right::before {
    content: ''; position: fixed; inset: 0; z-index: 0;
    background: linear-gradient(135deg, rgba(15,23,42,0.82), rgba(60,20,100,0.6));
  }
  .login-form-wrap {
    position: relative; z-index: 1;
    background: rgba(255,255,255,0.97);
    border-radius: 20px; padding: 36px 28px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.35);
  }
  .mobile-brand {
    display: flex; align-items: center; gap: 10px;
    justify-content: center; margin-bottom: 24px;
  }
  .brand-logo {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    display: flex; align-items: center; justify-content: center; color: #fff;
  }
  .mobile-brand-text { font-size: 20px; font-weight: 800; color: #1e293b; }
  .mobile-brand-accent { color: #7c3aed; }
  .form-footer { color: #94a3b8; }
}
</style>
