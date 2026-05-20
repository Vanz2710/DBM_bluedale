<template>
  <div class="page">
    <div class="page-banner">
      <h1>Add Project</h1>
      <p>Create a new project linked to a contact</p>
    </div>
    <div class="card">
      <LoadingSpinner v-if="loading" />
      <form v-else @submit.prevent="submit">
        <div v-if="error" class="error-box">{{ error }}</div>

        <div class="form-row">
          <div class="form-group">
            <label>Start Date <span class="req">*</span></label>
            <input type="date" v-model="form.project_startdate" required>
          </div>
          <div class="form-group">
            <label>End Date <span class="req">*</span></label>
            <input type="date" v-model="form.project_enddate" required>
          </div>
        </div>

        <div v-if="durationDays !== null" class="duration-badge">
          Duration: {{ durationDays }} day(s)
        </div>

        <div class="form-group">
          <label>Project Name <span class="req">*</span></label>
          <input type="text" v-model="form.project_name" maxlength="800" required placeholder="Enter project name…">
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
            <div v-if="showDropdown && contactSearch.length >= 2 && filteredContacts.length === 0 && !contactsLoading" class="dropdown">
              <div class="dropdown-item muted">No results found</div>
            </div>
          </div>
          <span v-if="selectedContact" class="selected-contact">✔ {{ selectedContact.name }}</span>
        </div>

        <div class="form-group">
          <label>Remark</label>
          <textarea
            v-model="form.project_remark"
            rows="4"
            maxlength="800"
            placeholder="Enter project remark or notes…"
          ></textarea>
          <span v-if="form.project_remark.length >= 750" class="char-warn">
            {{ 800 - form.project_remark.length }} characters remaining
          </span>
        </div>

        <div class="btn-row">
          <router-link to="/projects" class="btn btn-cancel">Cancel</router-link>
          <button type="submit" class="btn btn-save" :disabled="saving || !form.contact_id">
            {{ saving ? 'Saving…' : 'Add Project' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const router = useRouter();
const loading = ref(false);
const saving  = ref(false);
const error   = ref('');

const form = ref({
  project_startdate: '',
  project_enddate:   '',
  project_name:      '',
  project_remark:    '',
  contact_id:        null,
});

const contactSearch    = ref('');
const filteredContacts = ref([]);
const contactsLoading  = ref(false);
const showDropdown     = ref(false);
const selectedContact  = ref(null);

let searchTimer = null;

const durationDays = computed(() => {
  if (!form.value.project_startdate || !form.value.project_enddate) return null;
  const diff = (new Date(form.value.project_enddate) - new Date(form.value.project_startdate)) / 86400000;
  return diff > 0 ? Math.ceil(diff) : null;
});

function searchContacts() {
  showDropdown.value = true;
  selectedContact.value = null;
  form.value.contact_id = null;
  clearTimeout(searchTimer);
  if (contactSearch.value.length < 2) {
    filteredContacts.value = [];
    return;
  }
  contactsLoading.value = true;
  searchTimer = setTimeout(async () => {
    try {
      const res = await api.get('/v1/contacts', { params: { q: contactSearch.value, per_page: 20 } });
      filteredContacts.value = res.data.data ?? [];
    } finally {
      contactsLoading.value = false;
    }
  }, 300);
}

function selectContact(c) {
  selectedContact.value = c;
  form.value.contact_id = c.id;
  contactSearch.value   = c.name;
  showDropdown.value    = false;
}

function hideDropdown() {
  setTimeout(() => { showDropdown.value = false; }, 150);
}

async function submit() {
  if (!form.value.contact_id) {
    error.value = 'Please select a company.';
    return;
  }
  saving.value = true;
  error.value  = '';
  try {
    await api.post('/v1/projects', {
      project_startdate: form.value.project_startdate,
      project_enddate:   form.value.project_enddate,
      project_name:      form.value.project_name,
      project_remark:    form.value.project_remark || null,
      contact_id:        form.value.contact_id,
    });
    router.push('/projects');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', hideDropdown);
});
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 760px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #0ea5e9);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }

.duration-badge {
  display: inline-block; background: #e0f2fe; color: #0369a1;
  border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 700;
  margin-bottom: 16px;
}

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group input, .form-group textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
}
.form-group textarea { height: 100px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group textarea:focus {
  border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
}

.search-wrap { position: relative; }
.dropdown {
  position: absolute; top: 100%; left: 0; right: 0; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: 8px; z-index: 100;
  max-height: 220px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.dropdown-item {
  padding: 9px 14px; font-size: 13px; cursor: pointer; color: #374151;
}
.dropdown-item:hover { background: #f0f9ff; color: #0ea5e9; }
.dropdown-item.muted { color: #94a3b8; cursor: default; }
.selected-contact { display: block; font-size: 12px; color: #10b981; font-weight: 600; margin-top: 4px; }

.char-warn { display: block; font-size: 11px; color: #f59e0b; margin-top: 4px; }
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: #ef4444; }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save   { flex: 1; background: #0ea5e9; color: white; justify-content: center; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
