<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Edit Company</h1>
      <p class="page-subtitle">Update company information</p>
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
        <div v-if="isAdmin" class="form-row">
          <div class="form-group">
            <label>Assign To</label>
            <select v-model="form.user_id">
              <option value="">— No change —</option>
              <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
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

    <!-- Persons in Charge manager -->
    <div v-if="!loading" class="card pic-card">
      <div class="pic-head">
        <div>
          <h2 class="pic-title">Persons in Charge</h2>
          <p class="pic-sub">Add, edit, or remove the people handling this company. Changes save immediately.</p>
        </div>
        <button type="button" class="btn-add-pic" @click="addDraft">+ Add Person</button>
      </div>

      <div v-if="picError" class="error-box">{{ picError }}</div>

      <div v-if="!incharges.length && !drafts.length" class="pic-empty">
        No persons in charge yet. Click “Add Person” to create one.
      </div>

      <div class="pic-list">
        <!-- existing -->
        <div v-for="pic in incharges" :key="pic.id" class="pic-row">
          <input v-model="pic.name" placeholder="Name *" class="pic-input" />
          <input v-model="pic.phone_mobile" placeholder="Mobile" class="pic-input" />
          <input v-model="pic.email" placeholder="Email" class="pic-input" />
          <div class="pic-actions">
            <button type="button" class="pic-btn pic-btn-save" :disabled="!pic.name?.trim() || pic._saving" @click="saveExisting(pic)">
              {{ pic._saving ? 'Saving…' : 'Save' }}
            </button>
            <template v-if="pic._confirmDel">
              <button type="button" class="pic-btn pic-btn-confirm" :disabled="pic._saving" @click="removeExisting(pic)">Confirm</button>
              <button type="button" class="pic-btn pic-btn-ghost" @click="pic._confirmDel = false">Cancel</button>
            </template>
            <button v-else type="button" class="pic-btn pic-btn-del" @click="pic._confirmDel = true">Remove</button>
          </div>
        </div>

        <!-- new drafts -->
        <div v-for="(d, idx) in drafts" :key="'draft-' + idx" class="pic-row pic-row-draft">
          <input v-model="d.name" placeholder="Name *" class="pic-input" />
          <input v-model="d.phone_mobile" placeholder="Mobile" class="pic-input" />
          <input v-model="d.email" placeholder="Email" class="pic-input" />
          <div class="pic-actions">
            <button type="button" class="pic-btn pic-btn-save" :disabled="!d.name.trim() || d._saving" @click="saveDraft(idx)">
              {{ d._saving ? 'Adding…' : 'Add' }}
            </button>
            <button type="button" class="pic-btn pic-btn-ghost" @click="drafts.splice(idx, 1)">Discard</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { usePermissions } from '../composables/usePermissions.js';

const route = useRoute();
const router = useRouter();
const { isAdmin } = usePermissions();
const id = route.params.id;
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const dupError = ref('');
let dupTimer = null;

const lookups = ref({ statuses: [], types: [], industries: [], categories: [], users: [] });
const form = ref({
  name: '', status_id: '', type_id: '', industry_id: '',
  category_id: '', address: '', lead_source: '', remark: '', user_id: '',
});

// ── Persons in Charge (managed live via their own endpoints) ──
const incharges = ref([]);
const drafts    = ref([]);
const picError  = ref('');

function picErr(e) {
  const errors = e.response?.data?.errors;
  return errors
    ? Object.values(errors).flat().join(' ')
    : (e.response?.data?.message ?? 'Could not save. Please try again.');
}

function addDraft() {
  drafts.value.push({ name: '', phone_mobile: '', email: '', _saving: false });
}

async function saveDraft(idx) {
  const d = drafts.value[idx];
  if (!d.name.trim()) return;
  d._saving = true;
  picError.value = '';
  try {
    const res = await api.post(`/v1/contacts/${id}/incharges`, {
      name: d.name.trim(), phone_mobile: d.phone_mobile || null, email: d.email || null,
    });
    incharges.value.push({ ...res.data.data, _saving: false, _confirmDel: false });
    drafts.value.splice(idx, 1);
  } catch (e) {
    picError.value = picErr(e);
    d._saving = false;
  }
}

async function saveExisting(pic) {
  if (!pic.name?.trim()) return;
  pic._saving = true;
  picError.value = '';
  try {
    await api.put(`/v1/contacts/${id}/incharges/${pic.id}`, {
      name: pic.name.trim(), phone_mobile: pic.phone_mobile || null, email: pic.email || null,
    });
  } catch (e) {
    picError.value = picErr(e);
  } finally {
    pic._saving = false;
  }
}

async function removeExisting(pic) {
  pic._saving = true;
  picError.value = '';
  try {
    await api.delete(`/v1/contacts/${id}/incharges/${pic.id}`);
    incharges.value = incharges.value.filter(p => p.id !== pic.id);
  } catch (e) {
    picError.value = picErr(e);
    pic._saving = false;
  }
}

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
    const [contactRes, lookupRes, picRes] = await Promise.all([
      api.get(`/v1/contacts/${id}`),
      api.get('/v1/lookups'),
      api.get(`/v1/contacts/${id}/incharges`),
    ]);
    const c = contactRes.data.data;
    incharges.value = (picRes.data.data ?? []).map(p => ({ ...p, _saving: false, _confirmDel: false }));
    form.value = {
      name:        c.name         ?? '',
      status_id:   c.status_id   ?? '',
      type_id:     c.type_id     ?? '',
      industry_id: c.industry_id ?? '',
      category_id: c.category_id ?? '',
      address:     c.address     ?? '',
      lead_source: c.lead_source ?? '',
      remark:      c.remark      ?? '',
      user_id:     c.user_id     ?? '',
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
.page-header { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 28px 32px;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
}
.form-group textarea { height: 80px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft);
}
.hint { font-size: 11px; color: var(--warning); margin-top: 4px; }
.error-hint { color: var(--danger); font-weight: 600; }
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-save { flex: 1; background: var(--primary); color: var(--primary-on); justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

/* Persons in Charge */
.pic-card { margin-top: 20px; }
.pic-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.pic-title { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0 0 3px; }
.pic-sub { font-size: 12.5px; color: var(--text-3); margin: 0; }
.btn-add-pic { flex-shrink: 0; height: 36px; padding: 0 14px; border: 1px solid var(--primary); background: var(--primary-soft); color: var(--primary-text); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.btn-add-pic:hover { background: #c7dbfd; }
.pic-empty { font-size: 13px; color: var(--text-3); padding: 8px 0 4px; }
.pic-list { display: flex; flex-direction: column; gap: 10px; }
.pic-row { display: grid; grid-template-columns: 1.4fr 1fr 1.4fr auto; gap: 10px; align-items: center; }
.pic-row-draft { background: var(--surface-2); border-radius: var(--radius-sm); padding: 8px; }
.pic-input { height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); background: var(--surface); outline: none; box-sizing: border-box; width: 100%; }
.pic-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.pic-actions { display: flex; gap: 6px; align-items: center; }
.pic-btn { height: 34px; padding: 0 12px; border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 600; cursor: pointer; border: 1px solid transparent; white-space: nowrap; transition: background 0.15s, color 0.15s; }
.pic-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.pic-btn-save { background: var(--primary); color: var(--primary-on); }
.pic-btn-save:hover:not(:disabled) { background: var(--primary-hover); }
.pic-btn-del { background: var(--surface-2); color: var(--danger); border-color: var(--border); }
.pic-btn-del:hover { background: var(--danger-soft); }
.pic-btn-confirm { background: var(--danger); color: #fff; }
.pic-btn-confirm:hover:not(:disabled) { background: #b91c1c; }
.pic-btn-ghost { background: var(--surface-2); color: var(--text-2); border-color: var(--border); }
.pic-btn-ghost:hover { background: var(--border); color: var(--text-1); }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .btn-row { flex-wrap: wrap; }
  .pic-row { grid-template-columns: 1fr; }
  .pic-actions { justify-content: flex-end; }
}
</style>
