<template>
  <div class="page">
    <div class="page-banner">
      <h1>Edit Deal</h1>
      <p>Update deal details</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>

        <!-- Read-only context -->
        <div class="context-box">
          <div class="context-item">
            <span class="context-label">Company</span>
            <span class="context-val">{{ data.contact_name ?? '—' }}</span>
          </div>
          <div class="context-item">
            <span class="context-label">Assigned To</span>
            <span class="context-val">{{ data.user_name ?? '—' }}</span>
          </div>
        </div>

        <div class="form-group">
          <label>Deal Title <span class="req">*</span></label>
          <input type="text" v-model="form.title" maxlength="500" required>
        </div>

        <div class="form-group">
          <label>Company <span class="req">*</span></label>
          <div class="search-wrap">
            <input
              type="text"
              v-model="contactSearch"
              @input="searchContacts"
              @focus="showDropdown = true"
              placeholder="Type to search company…"
              autocomplete="off"
            >
            <div v-if="showDropdown && filteredContacts.length > 0" class="dropdown">
              <div
                v-for="c in filteredContacts"
                :key="c.id"
                class="dropdown-item"
                @mousedown.prevent="selectContact(c)"
              >{{ c.name }}</div>
            </div>
          </div>
          <span v-if="selectedContactName" class="selected-contact">✔ {{ selectedContactName }}</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Stage <span class="req">*</span></label>
            <select v-model="form.stage" required>
              <option v-for="s in STAGES" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status <span class="req">*</span></label>
            <select v-model="form.status" required>
              <option value="open">Open</option>
              <option value="won">Won</option>
              <option value="lost">Lost</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Deal Value</label>
            <input type="number" v-model="form.value" min="0" max="999999999" step="0.01">
          </div>
          <div class="form-group">
            <label>Probability (%)</label>
            <input type="number" v-model="form.probability" min="0" max="100">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Expected Close Date</label>
            <input type="date" v-model="form.expected_close_date">
          </div>
          <div v-if="isAdmin" class="form-group">
            <label>Reassign To</label>
            <select v-model="form.assigned_user_id">
              <option value="">Keep current</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>

        <div v-if="form.status === 'lost'" class="form-group">
          <label>Lost Reason</label>
          <input type="text" v-model="form.lost_reason" maxlength="500" placeholder="Why was this deal lost?">
        </div>

        <div class="form-group">
          <label>Notes</label>
          <textarea v-model="form.notes" rows="5" maxlength="2000" placeholder="Additional notes…"></textarea>
          <span v-if="form.notes && form.notes.length >= 1900" class="char-warn">{{ 2000 - form.notes.length }} characters remaining</span>
        </div>

        <div class="btn-row">
          <router-link to="/deals" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const STAGES = ['New Lead', 'Contacted', 'Quotation Sent', 'Negotiation', 'Won', 'Lost'];

const route  = useRoute();
const router = useRouter();
const id     = route.params.id;

const loading = ref(true);
const saving  = ref(false);
const error   = ref('');
const data    = ref({});
const users   = ref([]);

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

const form = ref({
  title:               '',
  stage:               'New Lead',
  status:              'open',
  value:               '',
  probability:         '',
  expected_close_date: '',
  lost_reason:         '',
  notes:               '',
  contact_id:          null,
  assigned_user_id:    '',
});

const contactSearch       = ref('');
const filteredContacts    = ref([]);
const showDropdown        = ref(false);
const selectedContactName = ref('');
let searchTimer = null;

function searchContacts() {
  showDropdown.value = true;
  clearTimeout(searchTimer);
  if (contactSearch.value.length < 2) {
    filteredContacts.value = [];
    return;
  }
  searchTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts', { params: { q: contactSearch.value, per_page: 20 } });
    filteredContacts.value = res.data.data ?? [];
  }, 300);
}

function selectContact(c) {
  form.value.contact_id    = c.id;
  contactSearch.value      = c.name;
  selectedContactName.value = c.name;
  showDropdown.value       = false;
  filteredContacts.value   = [];
}

async function loadUsers() {
  if (!isAdmin.value) return;
  try {
    const res = await api.get('/v1/rbac/users');
    users.value = res.data.data ?? [];
  } catch (_) { /* ignore */ }
}

async function submit() {
  saving.value = true;
  error.value  = '';
  try {
    await api.put(`/v1/deals/${id}`, {
      title:               form.value.title,
      stage:               form.value.stage,
      status:              form.value.status,
      value:               form.value.value !== '' ? form.value.value : null,
      probability:         form.value.probability !== '' ? form.value.probability : null,
      expected_close_date: form.value.expected_close_date || null,
      lost_reason:         form.value.lost_reason || null,
      notes:               form.value.notes || null,
      contact_id:          form.value.contact_id,
      assigned_user_id:    form.value.assigned_user_id || null,
    });
    router.push('/deals');
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
  await loadUsers();
  try {
    const res = await api.get(`/v1/deals/${id}`);
    data.value = res.data.data;
    form.value.title               = data.value.title               ?? '';
    form.value.stage               = data.value.stage               ?? 'New Lead';
    form.value.status              = data.value.status              ?? 'open';
    form.value.value               = data.value.value               ?? '';
    form.value.probability         = data.value.probability         ?? '';
    form.value.expected_close_date = data.value.expected_close_date ?? '';
    form.value.lost_reason         = data.value.lost_reason         ?? '';
    form.value.notes               = data.value.notes               ?? '';
    form.value.contact_id          = data.value.contact_id          ?? null;
    contactSearch.value            = data.value.contact_name        ?? '';
    selectedContactName.value      = data.value.contact_name        ?? '';
  } catch {
    error.value = 'Deal not found.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 760px; }
.page-banner {
  background: linear-gradient(135deg, #134e4a, #0d9488);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }

.context-box {
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 14px 16px; margin-bottom: 20px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;
}
.context-item { display: flex; flex-direction: column; gap: 2px; }
.context-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.context-val { font-size: 13px; font-weight: 600; color: #1e293b; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; color: #1e293b; outline: none;
  background: white; box-sizing: border-box;
}
.form-group textarea { height: 120px; padding: 10px 14px; resize: vertical; }
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }

.search-wrap { position: relative; }
.dropdown {
  position: absolute; top: 100%; left: 0; right: 0; background: white;
  border: 1.5px solid #e2e8f0; border-radius: 8px; z-index: 100;
  max-height: 220px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.dropdown-item { padding: 9px 14px; font-size: 13px; cursor: pointer; color: #374151; }
.dropdown-item:hover { background: #f0fdf4; color: #0d9488; }
.selected-contact { display: block; font-size: 12px; color: #10b981; font-weight: 600; margin-top: 4px; }

.char-warn { display: block; font-size: 11px; color: #f59e0b; margin-top: 4px; }
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: #f1f5f9; color: #64748b; }
.btn-save   { flex: 1; background: #0d9488; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
  .context-box { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
