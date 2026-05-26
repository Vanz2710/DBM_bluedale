<template>
  <transition name="fm">
    <div v-if="open" class="fm-overlay" @click.self="$emit('close')">
      <div class="fm-modal">
        <div class="fm-header">
          <div class="fm-title-block">
            <strong class="fm-title">{{ mode === 'edit' ? 'Edit Forecast' : 'Add Forecast' }}</strong>
            <span v-if="lockedContact" class="fm-company-chip">🏢 {{ lockedContact.name }}</span>
          </div>
          <button class="fm-close" @click="$emit('close')">✕</button>
        </div>

        <div class="fm-body">
          <LoadingSpinner v-if="loading" />
          <form v-else @submit.prevent="submit">
            <div v-if="error" class="fm-error">{{ error }}</div>

            <div v-if="!lockedContact" class="fm-group">
              <label>Company <span class="req">*</span></label>
              <div class="fm-search-wrap">
                <input
                  type="text"
                  v-model="contactSearch"
                  @input="searchContacts"
                  @focus="showDropdown = !form.contact_id"
                  @blur="hideDropdown"
                  placeholder="Type to search company..."
                  autocomplete="off"
                />
                <div v-if="showDropdown && filteredContacts.length > 0" class="fm-dropdown">
                  <div
                    v-for="c in filteredContacts"
                    :key="c.id"
                    class="fm-dropdown-item"
                    @mousedown.prevent="selectContact(c)"
                  >
                    {{ c.name }}
                  </div>
                </div>
              </div>
              <span v-if="form.contact_id && contactSearch" class="fm-selected">Selected: {{ contactSearch }}</span>
            </div>

            <div class="fm-row">
              <div class="fm-group">
                <label>Product <span class="req">*</span></label>
                <select v-model="form.product_id" required>
                  <option value="">Select product</option>
                  <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div class="fm-group">
                <label>Forecast Type <span class="req">*</span></label>
                <select v-model="form.forecast_type_id" required>
                  <option value="">Select type</option>
                  <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </div>

            <div class="fm-row">
              <div class="fm-group">
                <label>Amount <span class="req">*</span></label>
                <div class="fm-amount-wrap">
                  <span class="fm-currency">RM</span>
                  <input type="number" v-model="form.amount" min="0" max="999999999999" step="0.01" required placeholder="0.00" class="fm-amount-input" />
                </div>
              </div>
              <div class="fm-group">
                <label>Forecast Date <span class="req">*</span></label>
                <input type="date" v-model="form.forecast_date" required />
              </div>
            </div>

            <div class="fm-row">
              <div class="fm-group">
                <label>Result</label>
                <select v-model="form.result_id">
                  <option value="">No Result</option>
                  <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>
              <div v-if="isAdmin" class="fm-group">
                <label>{{ mode === 'edit' ? 'Reassign To' : 'Assigned To' }}</label>
                <select v-model="form.assigned_user_id">
                  <option value="">{{ mode === 'edit' ? 'Keep current' : 'Use contact owner' }}</option>
                  <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <div class="fm-actions">
              <button type="button" class="fm-btn fm-cancel" @click="$emit('close')">Cancel</button>
              <button type="submit" class="fm-btn fm-save" :disabled="saving || !form.contact_id">
                {{ saving ? 'Saving…' : (mode === 'edit' ? 'Save Changes' : 'Add Forecast') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../api.js';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, default: 'add' },
  forecastId: { type: [Number, String], default: null },
  prefilledContact: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);

const currentUser = JSON.parse(localStorage.getItem('crm_user') || 'null');
const isAdmin = computed(() => {
  const roles = currentUser?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const lookups = ref({ forecast_products: [], forecast_types: [], forecast_results: [], users: [] });
const lookupsLoaded = ref(false);
const filteredContacts = ref([]);
const contactSearch = ref('');
const showDropdown = ref(false);
const lockedContact = ref(null);
let searchTimer = null;

const form = ref({
  contact_id: null,
  product_id: '',
  forecast_type_id: '',
  result_id: '',
  amount: '',
  forecast_date: new Date().toISOString().slice(0, 10),
  assigned_user_id: '',
});

const resultOptions = computed(() =>
  (lookups.value.forecast_results ?? []).filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
);

function selectContact(contact) {
  form.value.contact_id = contact.id;
  contactSearch.value = contact.name;
  filteredContacts.value = [];
  showDropdown.value = false;
}

function searchContacts() {
  if (lockedContact.value) return;
  showDropdown.value = true;
  form.value.contact_id = null;
  clearTimeout(searchTimer);
  if (contactSearch.value.length < 2) {
    filteredContacts.value = [];
    return;
  }
  searchTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts', { params: { search: contactSearch.value, per_page: 20 } });
    filteredContacts.value = res.data.data ?? [];
  }, 250);
}

function hideDropdown() {
  setTimeout(() => { showDropdown.value = false; }, 150);
}

function resetForm() {
  form.value = {
    contact_id: null,
    product_id: '',
    forecast_type_id: '',
    result_id: '',
    amount: '',
    forecast_date: new Date().toISOString().slice(0, 10),
    assigned_user_id: '',
  };
  contactSearch.value = '';
  filteredContacts.value = [];
  showDropdown.value = false;
  lockedContact.value = null;
  error.value = '';
}

async function ensureLookups() {
  if (lookupsLoaded.value) return;
  const res = await api.get('/v1/lookups');
  lookups.value = res.data;
  lookupsLoaded.value = true;
}

async function initialize() {
  loading.value = true;
  error.value = '';
  resetForm();
  try {
    await ensureLookups();
    if (props.mode === 'edit' && props.forecastId) {
      const res = await api.get(`/v1/forecasts/${props.forecastId}`);
      const data = res.data.data;
      form.value.contact_id = data.contact_id;
      form.value.product_id = data.product_id ?? '';
      form.value.forecast_type_id = data.forecast_type_id ?? '';
      form.value.result_id = data.result_id ?? '';
      form.value.amount = data.amount ?? '';
      form.value.forecast_date = data.forecast_date ?? '';
      form.value.assigned_user_id = data.user_id ?? '';
      contactSearch.value = data.contact_name ?? '';
    } else if (props.prefilledContact) {
      lockedContact.value = props.prefilledContact;
      form.value.contact_id = props.prefilledContact.id;
      contactSearch.value = props.prefilledContact.name;
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load.';
  } finally {
    loading.value = false;
  }
}

watch(() => props.open, (val) => {
  if (val) initialize();
});

async function submit() {
  if (!form.value.contact_id) {
    error.value = 'Please select a company.';
    return;
  }
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      contact_id: form.value.contact_id,
      product_id: form.value.product_id,
      forecast_type_id: form.value.forecast_type_id,
      result_id: form.value.result_id || null,
      amount: form.value.amount,
      forecast_date: form.value.forecast_date,
      assigned_user_id: form.value.assigned_user_id || null,
    };
    let res;
    if (props.mode === 'edit' && props.forecastId) {
      res = await api.put(`/v1/forecasts/${props.forecastId}`, payload);
    } else {
      res = await api.post('/v1/forecasts', payload);
    }
    emit('saved', res.data?.data ?? res.data);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save forecast.');
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.fm-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(4px);
  z-index: 700;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.fm-modal {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 580px; max-width: 95vw; max-height: 92vh;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.fm-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.fm-title-block { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.fm-title { color: var(--text-1); font-size: 17px; font-weight: 800; letter-spacing: -0.2px; }
.fm-company-chip {
  background: var(--primary-soft); color: var(--primary-text);
  font-size: 11px; font-weight: 700;
  padding: 4px 12px; border-radius: 999px; white-space: nowrap;
}
.fm-close {
  background: var(--surface-2); border: none; cursor: pointer; font-size: 14px;
  color: var(--text-2); width: 30px; height: 30px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  line-height: 1; flex-shrink: 0; transition: background 0.15s, color 0.15s;
}
.fm-close:hover { background: var(--danger-soft); color: var(--danger); }

.fm-body { padding: 22px 24px; overflow-y: auto; }
.fm-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.fm-group { margin-bottom: 14px; }
.fm-group label {
  display: block; font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px;
}
.fm-group input, .fm-group select {
  width: 100%; height: 42px; padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 999px; font-size: 13.5px;
  color: var(--text-1); background: var(--surface);
  outline: none; box-sizing: border-box; transition: border-color 0.15s, box-shadow 0.15s;
}
.fm-group input:focus, .fm-group select:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}

.fm-search-wrap { position: relative; }
.fm-dropdown {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 50;
  max-height: 220px; overflow-y: auto; background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
}
.fm-dropdown-item { padding: 9px 14px; font-size: 13px; color: var(--text-1); cursor: pointer; }
.fm-dropdown-item:hover { background: var(--primary-soft); color: var(--primary-text); }
.fm-selected { display: block; font-size: 11px; color: var(--primary); font-weight: 700; margin-top: 4px; }

.fm-error {
  background: var(--danger-soft); color: var(--danger);
  border-radius: var(--radius); padding: 12px 16px; font-size: 13px; margin-bottom: 14px;
}
.req { color: var(--danger); }

.fm-actions { display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end; }
.fm-btn {
  height: 42px; padding: 0 22px; border-radius: 999px; font-size: 14px; font-weight: 700;
  cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center;
}
.fm-cancel { background: var(--surface-2); color: var(--text-2); transition: background 0.15s; }
.fm-cancel:hover { background: var(--border); }
.fm-save { background: var(--primary); color: var(--primary-on); min-width: 160px; transition: background 0.15s; }
.fm-save:hover:not(:disabled) { background: var(--primary-hover); }
.fm-save:disabled { background: var(--border); color: var(--text-3); cursor: not-allowed; }

.fm-enter-active, .fm-leave-active { transition: opacity 0.18s ease; }
.fm-enter-active .fm-modal, .fm-leave-active .fm-modal { transition: transform 0.22s ease; }
.fm-enter-from, .fm-leave-to { opacity: 0; }
.fm-enter-from .fm-modal, .fm-leave-to .fm-modal { transform: translateY(20px) scale(0.98); }

.fm-amount-wrap {
  display: flex; align-items: stretch;
  border: 1px solid var(--border);
  border-radius: 999px; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
}
.fm-amount-wrap:focus-within {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.fm-currency {
  display: flex; align-items: center; padding: 0 12px;
  background: var(--surface-2); color: var(--text-2);
  font-size: 13px; font-weight: 700;
  border-right: 1px solid var(--border); flex-shrink: 0;
}
.fm-amount-input {
  flex: 1; height: 42px; padding: 0 14px; border: none; border-radius: 0;
  font-size: 13.5px; color: var(--text-1); background: var(--surface);
  outline: none; min-width: 0; box-sizing: border-box; width: 0;
}
.fm-amount-input:focus { border-color: transparent; box-shadow: none; }

@media (max-width: 640px) {
  .fm-modal { width: 100%; max-height: 100vh; border-radius: 0; }
  .fm-row { grid-template-columns: 1fr; }
}
</style>