<template>
  <div class="login-root">

    <!-- ── Left: brand showcase ──────────────────────────────── -->
    <aside class="login-left">
      <div class="left-glow" aria-hidden="true"></div>

      <header class="left-brand">
        <div class="left-logo">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <rect x="2"  y="2"  width="9" height="9" rx="2" fill="white" opacity="0.95"/>
            <rect x="13" y="2"  width="9" height="9" rx="2" fill="white" opacity="0.5"/>
            <rect x="2"  y="13" width="9" height="9" rx="2" fill="white" opacity="0.5"/>
            <rect x="13" y="13" width="9" height="9" rx="2" fill="white" opacity="0.95"/>
          </svg>
        </div>
        <span class="left-brand-name">Bluedale<span class="left-brand-accent">CRM</span></span>
      </header>

      <div class="left-hero">
        <h2 class="hero-title">Every client relationship,<br>in one place.</h2>
        <p class="hero-sub">The all-in-one CRM for Bluedale Group of Companies — contacts, deals, forecasts and follow-ups, beautifully organised.</p>

        <ul class="hero-features">
          <li>
            <span class="feat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            Track contacts, deals &amp; forecasts
          </li>
          <li>
            <span class="feat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            Smart reminders &amp; follow-ups
          </li>
          <li>
            <span class="feat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            Real-time team performance insights
          </li>
        </ul>
      </div>

      <div class="left-product">
        <img class="product-thumb" :src="img1" alt="The KL Guide cover" draggable="false" />
        <div class="product-info">
          <span class="product-pill">Now available</span>
          <span class="product-title">The KL Guide</span>
          <span class="product-sub">Malaysia's leading business &amp; travel directory</span>
        </div>
      </div>
    </aside>

    <!-- ── Right: sign-in form ───────────────────────────────── -->
    <main class="login-right">
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
        <p class="form-sub">Sign in to your workspace to continue.</p>

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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </span>
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter your password"
                required autocomplete="current-password"
                :disabled="loading"
              />
              <button type="button" class="input-toggle" @click="showPassword = !showPassword" tabindex="-1" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login" :disabled="loading">
            <span v-if="loading" class="btn-spinner"></span>
            <span>{{ loading ? 'Signing in…' : 'Sign In' }}</span>
            <svg v-if="!loading" class="btn-arrow" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>

        </form>

        <p class="form-footer">Bluedale Group of Companies &copy; {{ year }}</p>
      </div>
    </main>

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
  grid-template-columns: 53% 47%;
  background: var(--surface);
}

/* ── Left: premium brand panel (intentional dark splash) ──────── */
.login-left {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding: 44px 52px;
  color: #fff;
  background: #160e33;
}
.left-glow {
  position: absolute; inset: 0; z-index: 0; pointer-events: none;
  background:
    radial-gradient(55% 45% at 78% 12%, rgba(139, 92, 246, 0.45), transparent 70%),
    radial-gradient(50% 50% at 8% 92%, rgba(76, 29, 149, 0.55), transparent 72%),
    linear-gradient(158deg, #2a1065 0%, #1a1140 52%, #130c2c 100%);
}

.left-brand,
.left-hero,
.left-product { position: relative; z-index: 1; }

.left-brand { display: flex; align-items: center; gap: 11px; }
.left-logo {
  width: 40px; height: 40px; border-radius: 12px;
  background: linear-gradient(135deg, #7c3aed, #a78bfa);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 10px 26px rgba(124, 58, 237, 0.5);
}
.left-brand-name { font-size: 19px; font-weight: 800; letter-spacing: -0.3px; }
.left-brand-accent { color: #c4b5fd; }

/* Hero — vertically centred */
.left-hero { margin: auto 0; max-width: 440px; }
.hero-title {
  font-size: 34px; line-height: 1.16; font-weight: 800;
  letter-spacing: -0.8px; margin: 0 0 16px;
}
.hero-sub {
  font-size: 14.5px; line-height: 1.65; color: rgba(255, 255, 255, 0.62);
  margin: 0 0 30px; max-width: 390px;
}
.hero-features {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 15px;
}
.hero-features li {
  display: flex; align-items: center; gap: 13px;
  font-size: 14px; font-weight: 500; color: rgba(255, 255, 255, 0.9);
}
.feat-ico {
  width: 27px; height: 27px; border-radius: 50%; flex-shrink: 0;
  background: rgba(167, 139, 250, 0.18); color: #c4b5fd;
  border: 1px solid rgba(167, 139, 250, 0.3);
  display: flex; align-items: center; justify-content: center;
}
.feat-ico svg { width: 13px; height: 13px; }

/* Product card */
.left-product {
  display: flex; align-items: center; gap: 14px; max-width: 440px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px; padding: 13px 15px;
}
.product-thumb {
  width: 52px; height: 66px; border-radius: 9px; object-fit: cover; flex-shrink: 0;
  box-shadow: 0 10px 22px rgba(0, 0, 0, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.15);
  pointer-events: none; user-select: none;
}
.product-info { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.product-pill {
  align-self: flex-start; font-size: 9px; font-weight: 800;
  letter-spacing: 1px; text-transform: uppercase; color: #c4b5fd;
  background: rgba(167, 139, 250, 0.18); border-radius: 999px; padding: 2px 9px;
}
.product-title { font-size: 14.5px; font-weight: 800; }
.product-sub {
  font-size: 11.5px; color: rgba(255, 255, 255, 0.55);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ── Right: form ──────────────────────────────────────────────── */
.login-right {
  background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  padding: 48px 56px;
}
.login-form-wrap {
  width: 100%; max-width: 384px;
  animation: form-rise 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes form-rise {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.mobile-brand { display: none; }

.form-heading {
  font-size: 30px; font-weight: 800; color: var(--text-1);
  letter-spacing: -0.6px; margin: 0 0 8px;
}
.form-sub { font-size: 14px; color: var(--text-2); margin: 0 0 32px; }

/* Error */
.error-banner {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--danger-soft); color: var(--danger);
  border: 1px solid var(--danger-soft);
  border-radius: var(--radius); padding: 11px 14px;
  font-size: 13px; margin-bottom: 22px; line-height: 1.5;
}
.error-banner svg { flex-shrink: 0; margin-top: 1px; }

/* Fields */
.field { margin-bottom: 18px; }
.field label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px;
  color: var(--text-2); margin-bottom: 8px;
}
.input-wrap { position: relative; display: flex; align-items: center; }
.input-icon {
  position: absolute; left: 14px; color: var(--text-3);
  display: flex; align-items: center; pointer-events: none;
  transition: color 0.15s;
}
.input-wrap input {
  width: 100%; height: 50px; padding: 0 44px 0 44px;
  border: 1.5px solid var(--border); border-radius: var(--radius);
  font-size: 14px; color: var(--text-1); background: var(--surface-2);
  outline: none; box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.input-wrap input::placeholder { color: var(--text-3); }
.input-wrap input:focus {
  border-color: var(--primary); background: var(--surface);
  box-shadow: 0 0 0 4px var(--primary-soft);
}
.input-wrap input:focus + .input-toggle,
.input-wrap:focus-within .input-icon { color: var(--primary); }
.input-wrap input:disabled { opacity: 0.6; cursor: not-allowed; }

.input-toggle {
  position: absolute; right: 12px; background: none; border: none;
  cursor: pointer; color: var(--text-3);
  display: flex; align-items: center; padding: 5px; border-radius: var(--radius-sm);
  transition: color 0.15s;
}
.input-toggle:hover { color: var(--text-2); }

/* Button */
.btn-login {
  width: 100%; height: 50px; margin-top: 10px;
  background: linear-gradient(135deg, var(--primary), var(--primary-hover));
  color: var(--primary-on); border: none; border-radius: var(--radius-lg);
  font-size: 15px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 9px;
  box-shadow: 0 10px 26px -8px var(--focus-ring);
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}
.btn-login:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 14px 30px -8px var(--focus-ring);
}
.btn-login:hover:not(:disabled) .btn-arrow { transform: translateX(3px); }
.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }
.btn-arrow { transition: transform 0.18s ease; }

.btn-spinner {
  width: 17px; height: 17px; border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: #fff;
  animation: spin 0.7s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Footer */
.form-footer {
  text-align: center; font-size: 11.5px; color: var(--text-3); margin: 32px 0 0;
}

/* ── Mobile ───────────────────────────────────────────────────── */
@media (max-width: 860px) {
  .login-root { grid-template-columns: 1fr; }
  .login-left { display: none; }
  .login-right {
    padding: 40px 26px;
    background:
      radial-gradient(60% 40% at 80% 8%, rgba(124, 58, 237, 0.12), transparent 70%),
      var(--surface);
  }
  .login-form-wrap { max-width: 400px; }
  .mobile-brand {
    display: flex; align-items: center; gap: 11px;
    justify-content: center; margin-bottom: 28px;
  }
  .brand-logo {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    display: flex; align-items: center; justify-content: center; color: #fff;
    box-shadow: 0 8px 22px -6px var(--focus-ring);
  }
  .mobile-brand-text { font-size: 21px; font-weight: 800; color: var(--text-1); }
  .mobile-brand-accent { color: var(--primary); }
}

/* Respect reduced-motion preferences */
@media (prefers-reduced-motion: reduce) {
  .login-form-wrap { animation: none; }
  .btn-login, .btn-arrow { transition: none; }
}
</style>
