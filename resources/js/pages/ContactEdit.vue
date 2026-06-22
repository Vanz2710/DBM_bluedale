<template>
  <div class="page">
    <div class="page-banner amber">
      <h1>Edit Company</h1>
      <p>Update company information</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
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
        <div class="form-group">
          <label>Lead Source</label>
          <select v-model="form.lead_source">
            <option value="">— Not specified —</option>
            <option value="manual">Manual Entry</option>
            <option value="phone_call">Phone Call</option>
            <option value="referral">Referral</option>
            <option value="walk_in">Walk-in</option>
            <option value="social_media">Social Media</option>
            <option value="email_campaign">Email Campaign</option>
            <option value="web_form">Web Form</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea v-model="form.address" rows="3" placeholder="Enter address"></textarea>
        </div>
        <div class="form-group">
          <label>Remarks</label>
          <textarea v-model="form.remark" rows="3" placeholder="Internal notes about this company…"></textarea>
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
import LoadingSpinner from '../components/LoadingSpinner.vue';

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const dupError = ref('');
let dupTimer = null;

const lookups = ref({ statuses: [], types: [], industries: [], categories: [] });
const form = ref({
  name: '', status_id: '', type_id: '', industry_id: '',
  category_id: '', address: '', lead_source: '', remark: '',
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
  try {
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
      address:     c.address     ?? '',
      lead_source: c.lead_source ?? '',
      remark:      c.remark      ?? '',
    };
    lookups.value = lookupRes.data;
  } catch (e) {
    error.value = e.response?.status === 404
      ? 'Contact not found.'
      : 'Failed to load contact. Please go back and try again.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-banner {
  border-radius: var(--radius-lg); padding: 22px 28px; margin-bottom: 20px; color: white;
  background:
    radial-gradient(900px 200px at 90% -20%, rgba(96,165,250,0.5), transparent 55%),
    linear-gradient(118deg, #0f2456 0%, #1d4ed8 52%, #1e40af 100%);
  box-shadow: 0 12px 32px -14px rgba(15,36,86,0.65);
}
.page-banner.amber { /* colour unified — kept for template compat */ }
.page-banner h1 { font-size: 26px; font-weight: 800; margin: 0 0 5px; letter-spacing: -0.4px; }
.page-banner p { font-size: 13px; color: rgba(237,233,254,0.82); margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
}
.form-group textarea { height: 80px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.12);
}
.hint { font-size: 11px; color: var(--warning); margin-top: 4px; }
.error-hint { color: var(--danger); font-weight: 600; }
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .btn-row { flex-wrap: wrap; }
}
</style>
