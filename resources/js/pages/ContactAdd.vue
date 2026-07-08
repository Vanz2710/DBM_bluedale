<template>
  <div class="page">
    <!-- Step 1 -->
    <template v-if="step === 1">
      <div class="page-header">
        <h1 class="page-title">Add New Contact</h1>
        <p class="page-subtitle">Step 1 of 2 — Company Information</p>
      </div>
      <div class="card">
        <div class="section-label">Company Info</div>
        <form @submit.prevent="goStep2">
          <div class="form-group">
            <label>Company Name <span class="req">*</span></label>
            <input v-model="form.name" @input="checkDuplicate" placeholder="Enter company name" required>
            <div v-if="dupError" class="hint error-hint">{{ dupError }}</div>
            <div v-else class="hint">This field is required</div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status <span class="req">*</span></label>
              <select v-model="form.status_id" required>
                <option value="">Select status</option>
                <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Type <span class="req">*</span></label>
              <select v-model="form.type_id" required>
                <option value="">Select type</option>
                <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Industry <span class="req">*</span></label>
              <select v-model="form.industry_id" required>
                <option value="">Select industry</option>
                <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Category / Product <span class="req">*</span></label>
              <select v-model="form.category_id" required>
                <option value="">Select category</option>
                <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>
          <div v-if="isAdmin" class="form-row">
            <div class="form-group">
              <label>Assign To</label>
              <select v-model="form.user_id">
                <option value="">— Assign to me —</option>
                <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Lead Source</label>
              <select v-model="form.lead_source">
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
              <label>Date Created</label>
              <input type="date" v-model="form.created_at">
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <textarea v-model="form.address" placeholder="Enter address" rows="3"></textarea>
          </div>
          <div class="btn-row">
            <router-link to="/contacts" class="btn btn-cancel">Cancel</router-link>
            <button type="submit" class="btn btn-next" :disabled="!!dupError">Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-left:2px"><polyline points="9 18 15 12 9 6"/></svg></button>
          </div>
        </form>
      </div>
    </template>

    <!-- Step 2 -->
    <template v-else>
      <div class="page-header">
        <h1 class="page-title">Add New Contact</h1>
        <p class="page-subtitle">Step 2 of 2 — Contact Information</p>
      </div>
      <div class="card">
        <div class="section-label">Contact Info</div>
        <div class="company-chip"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:5px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>{{ form.name }}</div>
        <form @submit.prevent="submit">
          <div v-if="submitError" class="error-box">{{ submitError }}</div>
          <div class="form-row">
            <div class="form-group">
              <label>PIC Name <span class="req">*</span></label>
              <input v-model="pic.name" placeholder="Person in charge name" required>
            </div>
            <div class="form-group">
              <label>Phone Number <span class="req">*</span></label>
              <input v-model="pic.phone" placeholder="Contact number" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email <span class="req">*</span></label>
              <input type="email" v-model="pic.email" placeholder="Email address" required>
            </div>
            <div class="form-group">
              <label>Office Number</label>
              <input v-model="pic.office" placeholder="Office number">
            </div>
          </div>
          <div class="btn-row">
            <button type="button" class="btn btn-cancel" @click="step = 1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:2px"><polyline points="15 18 9 12 15 6"/></svg> Back</button>
            <button type="submit" class="btn btn-save" :disabled="saving">
              {{ saving ? 'Saving…' : 'Register Company' }}
            </button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';
import { usePermissions } from '../composables/usePermissions.js';

const router = useRouter();
const { isAdmin } = usePermissions();
const step = ref(1);
const saving = ref(false);
const submitError = ref('');
const dupError = ref('');
let dupTimer = null;

const lookups = ref({ statuses: [], industries: [], types: [], categories: [], users: [] });

const form = ref({
  name: '', status_id: '', industry_id: '', type_id: '', category_id: '',
  user_id: '',
  address: '', created_at: new Date().toISOString().slice(0, 10),
  lead_source: 'manual',
});

const pic = ref({ name: '', phone: '', email: '', office: '' });

function checkDuplicate() {
  clearTimeout(dupTimer);
  dupError.value = '';
  if (!form.value.name.trim()) return;
  dupTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts/check-duplicate', { params: { name: form.value.name } });
    dupError.value = res.data.exists ? 'This company already exists!' : '';
  }, 400);
}

function goStep2() {
  if (dupError.value) return;
  step.value = 2;
}

async function submit() {
  saving.value = true;
  submitError.value = '';
  try {
    await api.post('/v1/contacts', {
      ...form.value,
      pic_name:   pic.value.name,
      pic_phone:  pic.value.phone,
      pic_email:  pic.value.email,
      pic_office: pic.value.office,
    });
    router.push('/list');
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      submitError.value = Object.values(errors).flat().join(' ');
    } else {
      submitError.value = e.response?.data?.message ?? 'Failed to save. Please try again.';
    }
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/v1/lookups');
    lookups.value = res.data;
  } catch (_) {}
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 760px; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 28px 32px; }
.section-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px;
  color: var(--text-2); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border);
}
.company-chip {
  background: var(--primary-soft); color: var(--primary-text); border-radius: 6px; padding: 8px 14px;
  font-size: 14px; font-weight: 700; display: inline-block; margin-bottom: 20px;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 0; }
.form-group { margin-bottom: 16px; }
.form-group label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px;
}
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
.error-box {
  background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm);
  padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
}
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-next { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-next:disabled { background: var(--text-3); cursor: not-allowed; }
.btn-save { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .btn-row { flex-wrap: wrap; }
}
</style>
