<template>
  <div class="page">
    <!-- Step 1 -->
    <template v-if="step === 1">
      <div class="page-banner green">
        <h1>Add New Contact</h1>
        <p>Step 1 of 2 — Contact Information</p>
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
            <button type="submit" class="btn btn-next" :disabled="!!dupError">Next →</button>
          </div>
        </form>
      </div>
    </template>

    <!-- Step 2 -->
    <template v-else>
      <div class="page-banner green">
        <h1>Add New Company</h1>
        <p>Step 2 of 2 — Contact information</p>
      </div>
      <div class="card">
        <div class="section-label">Contact Info</div>
        <div class="company-chip">🏢 {{ form.name }}</div>
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
            <button type="button" class="btn btn-cancel" @click="step = 1">← Back</button>
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

const router = useRouter();
const step = ref(1);
const saving = ref(false);
const submitError = ref('');
const dupError = ref('');
let dupTimer = null;

const lookups = ref({ statuses: [], industries: [], types: [], categories: [] });

const form = ref({
  name: '', status_id: '', industry_id: '', type_id: '', category_id: '',
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
  const res = await api.get('/v1/lookups');
  lookups.value = res.data;
});
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 760px; }
.page-banner {
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner.green { background: linear-gradient(135deg, #1a2f4a, #22c55e); }
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.section-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px;
  color: var(--text-2); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border);
}
.company-chip {
  background: #eff6ff; color: #1d4ed8; border-radius: 6px; padding: 8px 14px;
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
  border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.1);
}
.hint { font-size: 11px; color: #f59e0b; margin-top: 4px; }
.error-hint { color: #ef4444; font-weight: 600; }
.error-box {
  background: #fee2e2; color: #991b1b; border-radius: 8px;
  padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
}
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-next { flex: 1; background: #22c55e; color: white; justify-content: center; }
.btn-next:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-save { flex: 1; background: #22c55e; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }

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
