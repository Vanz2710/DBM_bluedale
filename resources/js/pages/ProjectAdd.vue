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
          <span v-if="selectedContact" class="selected-contact"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg>{{ selectedContact.name }}</span>
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
.page { padding: 28px 32px; max-width: 760px; }
.page-banner {
  border-radius: var(--radius-lg); padding: 22px 28px; margin-bottom: 20px; color: white;
  background:
    radial-gradient(900px 200px at 90% -20%, rgba(96,165,250,0.5), transparent 55%),
    linear-gradient(118deg, #0f2456 0%, #1d4ed8 52%, #1e40af 100%);
  box-shadow: 0 12px 32px -14px rgba(15,36,86,0.65);
}
.page-banner h1 { font-size: 26px; font-weight: 800; margin: 0 0 5px; letter-spacing: -0.4px; }
.page-banner p  { font-size: 13px; color: rgba(237,233,254,0.82); margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }
.loading-msg { text-align: center; padding: 40px; color: var(--text-3); }

.duration-badge {
  display: inline-block; background: var(--primary-soft); color: var(--primary-text);
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
  border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.12);
}

.search-wrap { position: relative; }
.dropdown {
  position: absolute; top: 100%; left: 0; right: 0; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: 8px; z-index: 100;
  max-height: 220px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.dropdown-item {
  padding: 9px 14px; font-size: 13px; cursor: pointer; color: var(--text-1);
}
.dropdown-item:hover { background: var(--primary-soft); color: var(--primary-text); }
.dropdown-item.muted { color: var(--text-3); cursor: default; }
.selected-contact { display: block; font-size: 12px; color: var(--primary); font-weight: 600; margin-top: 4px; }

.char-warn { display: block; font-size: 11px; color: var(--warning); margin-top: 4px; }
.error-box { background: var(--danger-soft); color: var(--danger); border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.req { color: var(--danger); }
.btn-row { display: flex; gap: 10px; margin-top: 24px; }
.btn { height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; }
.btn-cancel { background: var(--app-bg); color: var(--text-2); }
.btn-save   { flex: 1; background: var(--primary); color: white; justify-content: center; }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) { .page { padding: 12px 8px; } }
</style>
