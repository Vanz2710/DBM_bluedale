<template>
  <div class="page">
    <div class="page-banner amber">
      <h1>Edit Company</h1>
      <p>Update company information</p>
    </div>
    <div class="card">
      <div v-if="loading" class="loading-msg">Loading…</div>
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>
        <div class="form-group">
          <label>Company Name <span class="req">*</span></label>
          <input v-model="form.name" required @input="checkDuplicate" placeholder="Company name">
          <div v-if="dupError" class="hint error-hint">{{ dupError }}</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status_id">
              <option value="">— No change —</option>
              <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Type</label>
            <select v-model="form.type_id">
              <option value="">— No change —</option>
              <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Industry</label>
            <select v-model="form.industry_id">
              <option value="">— No change —</option>
              <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Category / Product</label>
            <select v-model="form.category_id">
              <option value="">— No change —</option>
              <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Area</label>
            <select v-model="form.area_id">
              <option value="">— No change —</option>
              <option v-for="a in lookups.areas" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Assigned User</label>
            <select v-model="form.user_id">
              <option value="">— Unassigned —</option>
              <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea v-model="form.address" rows="3" placeholder="Enter address"></textarea>
        </div>
        <div class="btn-row">
          <router-link :to="`/contacts/${id}`" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving || !!dupError">
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const dupError = ref('');
let dupTimer = null;

const lookups = ref({ statuses: [], types: [], industries: [], categories: [], areas: [], users: [] });
const form = ref({
  name: '', status_id: '', type_id: '', industry_id: '',
  category_id: '', area_id: '', user_id: '', address: '',
});

function checkDuplicate() {
  clearTimeout(dupTimer);
  dupError.value = '';
  if (!form.value.name.trim()) return;
  dupTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts/check-duplicate', {
      params: { name: form.value.name, exclude_id: id },
    });
    dupError.value = res.data.exists ? 'This company name already exists!' : '';
  }, 400);
}

async function submit() {
  if (dupError.value) return;
  saving.value = true;
  error.value = '';
  try {
    await api.put(`/v1/contacts/${id}`, form.value);
    router.push(`/contacts/${id}`);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  const [contactRes, lookupRes] = await Promise.all([
    api.get(`/v1/contacts/${id}`),
    api.get('/v1/lookups'),
  ]);
  const c = contactRes.data.data;
  form.value = {
    name:        c.name         ?? '',
    status_id:   c.status_id   ?? '',
    type_id:     c.type_id     ?? '',
    industry_id: c.industry_id ?? '',
    category_id: c.category_id ?? '',
    area_id:     c.area_id     ?? '',
    user_id:     c.user_id     ?? '',
    address:     c.address     ?? '',
  };
  lookups.value = lookupRes.data;
  loading.value = false;
});
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 760px; }
.page-banner { border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white; }
.page-banner.amber { background: linear-gradient(135deg, #1a2f4a, #f59e0b); }
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: #94a3b8; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; color: #1e293b; outline: none;
  background: white; box-sizing: border-box;
}
.form-group textarea { height: 80px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
}
.hint { font-size: 11px; color: #f59e0b; margin-top: 4px; }
.error-hint { color: #ef4444; font-weight: 600; }
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.btn-save { flex: 1; background: #f59e0b; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }
</style>
