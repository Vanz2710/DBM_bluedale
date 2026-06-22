<template>
  <div class="login-root">

    <!-- ── Split card ────────────────────────────────────────────── -->
    <div class="login-card" role="main">

      <!-- ── Left: visual panel ──────────────────────────────────── -->
      <div class="card-left">
        <div class="left-art">
          <div ref="lottieBox" class="lottie-box" :class="{ 'art-hidden': !artVisible }" aria-hidden="true"></div>
        </div>

        <div class="left-bottom">
          <h2 class="left-heading">Bluedale CRM System</h2>
          <p class="left-sub">Your complete CRM workspace — built for the Bluedale team.</p>
        </div>

        <!-- Wave decoration SVG -->
        <svg class="wave-deco" viewBox="0 0 400 180" preserveAspectRatio="none" aria-hidden="true">
          <path d="M0,90 C80,140 160,40 240,90 C320,140 380,60 400,80 L400,180 L0,180 Z" fill="rgba(255,255,255,0.07)"/>
          <path d="M0,120 C60,80 140,160 220,110 C300,60 360,140 400,100 L400,180 L0,180 Z" fill="rgba(255,255,255,0.05)"/>
        </svg>
      </div>

      <!-- ── Right: form panel ────────────────────────────────────── -->
      <div class="card-right">
        <h1 class="form-heading">Welcome Back</h1>
        <p class="form-sub">Enter your credentials to access your workspace.</p>

        <!-- Error banner -->
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

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const router       = useRouter();
const form         = ref({ username: '', password: '' });
const error        = ref('');
const loading      = ref(false);
const showPassword = ref(false);
const year         = new Date().getFullYear();

const lottieBox = ref(null);
const artVisible = ref(true);
let lottie = null;
let anim = null;
let artData = [];
let artIndex = 0;
let cycleTimer = null;
let fadeTimer = null;

const ART_HOLD = 6000;   // ms each animation is shown
const ART_FADE = 500;    // ms crossfade duration

function loadArt(i) {
  if (!lottieBox.value || !lottie) return;
  anim?.destroy();
  anim = lottie.loadAnimation({
    container: lottieBox.value,
    renderer: 'svg',
    loop: true,
    autoplay: true,
    animationData: artData[i],
  });
}

onMounted(async () => {
  const [{ default: lottieLib }, team, rocket, goals] = await Promise.all([
    import('lottie-web/build/player/lottie_light'),
    import('../assets/business-team.json'),
    import('../assets/businessman-rocket.json'),
    import('../assets/sales-goals.json'),
  ]);
  lottie = lottieLib;
  artData = [team.default, rocket.default, goals.default];
  if (!lottieBox.value) return;

  loadArt(artIndex);

  cycleTimer = setInterval(() => {
    artVisible.value = false;                 // fade out
    fadeTimer = setTimeout(() => {
      artIndex = (artIndex + 1) % artData.length;
      loadArt(artIndex);
      artVisible.value = true;                // fade in
    }, ART_FADE);
  }, ART_HOLD);
});

onBeforeUnmount(() => {
  anim?.destroy();
  clearInterval(cycleTimer);
  clearTimeout(fadeTimer);
});

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
/* ── Page ───────────────────────────────────────────────────── */
.login-root {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 28px 20px;
  box-sizing: border-box;
  background:
    radial-gradient(ellipse 80% 60% at 15% 50%, rgba(29, 78, 216, 0.18), transparent),
    radial-gradient(ellipse 60% 50% at 85% 50%, rgba(14, 44, 120, 0.22), transparent),
    linear-gradient(135deg, #07101f 0%, #0b1a38 50%, #070e1c 100%);
}

/* ── Card ───────────────────────────────────────────────────── */
.login-card {
  display: flex;
  width: 100%;
  max-width: 900px;
  min-height: 560px;
  border-radius: 22px;
  overflow: hidden;
  box-shadow:
    0 40px 80px rgba(0, 0, 0, 0.55),
    0 0 0 1px rgba(255, 255, 255, 0.06);
  animation: card-rise 0.52s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes card-rise {
  from { opacity: 0; transform: translateY(22px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Left: blue wave panel ──────────────────────────────────── */
.card-left {
  width: 46%;
  flex-shrink: 0;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 36px 40px 48px;
  box-sizing: border-box;
  overflow: hidden;
  background:
    radial-gradient(ellipse 180% 45% at -5% 105%, rgba(13, 110, 253, 0.85) 0%, rgba(30, 170, 255, 0.35) 40%, transparent 65%),
    radial-gradient(ellipse 120% 40% at 110% 65%, rgba(56, 189, 248, 0.55) 0%, transparent 55%),
    radial-gradient(ellipse 90% 35% at 45% 35%, rgba(147, 210, 255, 0.25) 0%, transparent 60%),
    linear-gradient(168deg,
      #cce8ff 0%,
      #7ec8f8 12%,
      #3aaff5 25%,
      #1a8ae0 40%,
      #0f67c8 55%,
      #0b4aaa 70%,
      #072f82 85%,
      #041d60 100%
    );
  color: #ffffff;
}

/* Wave SVG decorations at the bottom */
.wave-deco {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 180px;
  pointer-events: none;
}

/* Top: lottie animation (where the logo used to be) */
.left-art {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: center;
  flex: 1;
  align-items: center;
  min-height: 0;
}
.lottie-box {
  width: 100%;
  max-width: 320px;
  aspect-ratio: 1 / 1;
  filter: drop-shadow(0 16px 32px rgba(0, 0, 0, 0.22));
  opacity: 1;
  transform: scale(1);
  transition: opacity 0.5s ease, transform 0.5s ease;
}
.lottie-box.art-hidden {
  opacity: 0;
  transform: scale(0.92);
}

/* Bottom: big heading + sub */
.left-bottom {
  position: relative;
  z-index: 1;
}
.left-heading {
  font-size: 32px;
  font-weight: 800;
  line-height: 1.14;
  letter-spacing: -0.8px;
  margin: 0 0 14px;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
}
.left-sub {
  font-size: 13px;
  line-height: 1.65;
  color: rgba(255, 255, 255, 0.65);
  margin: 0;
  max-width: 280px;
}

/* ── Right: form panel ──────────────────────────────────────── */
.card-right {
  flex: 1;
  background: #ffffff;
  padding: 52px 52px 52px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-sizing: border-box;
}

.form-heading {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.5px;
  margin: 0 0 8px;
}
.form-sub {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 30px;
  line-height: 1.5;
}

/* Error */
.error-banner {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 13px;
  margin-bottom: 22px;
  line-height: 1.5;
}
.error-banner svg { flex-shrink: 0; margin-top: 1px; }

/* Fields */
.field { margin-bottom: 18px; }
.field label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: #64748b;
  margin-bottom: 8px;
}
.input-wrap { position: relative; display: flex; align-items: center; }
.input-icon {
  position: absolute; left: 14px;
  color: #94a3b8;
  display: flex; align-items: center;
  pointer-events: none;
  transition: color 0.15s;
}
.input-wrap input {
  width: 100%; height: 50px;
  padding: 0 44px 0 44px;
  background: #f8faff;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px; color: #0f172a;
  outline: none; box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.input-wrap input::placeholder { color: #b0bac6; }
.input-wrap input:focus {
  border-color: #3b82f6;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}
.input-wrap:focus-within .input-icon { color: #3b82f6; }
.input-wrap input:disabled { opacity: 0.55; cursor: not-allowed; }

.input-toggle {
  position: absolute; right: 12px;
  background: none; border: none; cursor: pointer;
  color: #94a3b8;
  display: flex; align-items: center;
  padding: 5px; border-radius: 6px;
  transition: color 0.15s;
}
.input-toggle:hover { color: #64748b; }

/* Button */
.btn-login {
  width: 100%; height: 50px; margin-top: 10px;
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
  color: #ffffff; border: none; border-radius: 14px;
  font-size: 15px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 9px;
  box-shadow: 0 10px 28px -8px rgba(37, 99, 235, 0.55);
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
}
.btn-login:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 14px 32px -8px rgba(37, 99, 235, 0.65);
}
.btn-login:hover:not(:disabled) .btn-arrow { transform: translateX(3px); }
.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: 0.50; cursor: not-allowed; box-shadow: none; }
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
  text-align: center;
  font-size: 11.5px;
  color: #94a3b8;
  margin: 28px 0 0;
}

/* ── Mobile ─────────────────────────────────────────────────── */
@media (max-width: 700px) {
  .login-root { padding: 16px; align-items: flex-start; }
  .login-card { flex-direction: column; min-height: unset; }
  .card-left { width: 100%; min-height: unset; padding: 28px 28px 32px; }
  .left-art { display: none; }
  .left-heading { font-size: 24px; }
  .left-sub { display: none; }
  .card-right { padding: 36px 28px 40px; }
}

/* ── Reduced motion ─────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  .login-card { animation: none; }
  .btn-login, .btn-arrow { transition: none; }
}
</style>
