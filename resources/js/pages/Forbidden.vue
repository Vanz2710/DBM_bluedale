<template>
  <div class="forbidden-page">
    <div class="forbidden-card">
      <div class="forbidden-icon-wrap">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>

      <h1 class="forbidden-title">Access Restricted</h1>
      <p class="forbidden-desc">You don't have permission to view this page.</p>

      <div v-if="permission" class="forbidden-perm-row">
        <span class="perm-label">Required permission</span>
        <span class="perm-chip">{{ permission }}</span>
      </div>

      <p class="forbidden-hint">
        Contact your administrator to request access, or visit your profile to review which permissions you currently hold.
      </p>

      <div class="forbidden-actions">
        <router-link to="/" class="btn-primary">Go to Dashboard</router-link>
        <router-link to="/profile" class="btn-outline">View My Permissions</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const permission = computed(() => route.query.permission ?? null);
</script>

<style scoped>
.forbidden-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: var(--app-bg);
}

.forbidden-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  padding: 48px 40px 40px;
  max-width: 460px;
  width: 100%;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}

.forbidden-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--danger-soft);
  color: var(--danger);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}

.forbidden-title {
  margin: 0 0 10px;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-1);
}

.forbidden-desc {
  margin: 0 0 20px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-2);
  line-height: 1.5;
}

.forbidden-perm-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  justify-content: center;
}
.perm-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.perm-chip {
  background: var(--danger-soft);
  color: var(--danger);
  border-radius: 999px;
  padding: 3px 12px;
  font-size: 12px;
  font-weight: 700;
  font-family: 'Courier New', monospace;
}

.forbidden-hint {
  margin: 0 0 32px;
  font-size: 13px;
  color: var(--text-3);
  line-height: 1.6;
  max-width: 340px;
}

.forbidden-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 22px;
  background: var(--primary);
  color: var(--primary-on);
  border: none;
  border-radius: 999px;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, box-shadow 0.15s;
  box-shadow: 0 4px 14px rgba(29, 78, 216, 0.3);
}
.btn-primary:hover {
  background: var(--primary-hover);
  color: var(--primary-on);
}

.btn-outline {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 22px;
  background: transparent;
  color: var(--primary);
  border: 1.5px solid var(--primary-soft);
  border-radius: 999px;
  font-size: 13.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.btn-outline:hover {
  background: var(--primary-soft);
  color: var(--primary-text);
  border-color: var(--primary-soft);
}
</style>
