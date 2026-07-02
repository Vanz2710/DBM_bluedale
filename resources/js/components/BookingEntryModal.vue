<template>
  <Teleport to="body">
    <div v-if="open" class="modal-backdrop">
      <section class="entry-modal" role="dialog" aria-modal="true">
        <header class="entry-modal-head">
          <div>
            <h2>{{ form.site_name && form.id ? 'Edit Booking' : 'Add Booking' }}</h2>
            <p>Reserve a month for a company at one of your advertising sites.</p>
          </div>
          <button type="button" class="detail-close" @click="emit('close')">&times;</button>
        </header>

        <div class="entry-modal-body">
          <div class="field full">
            <label>Company <span class="req-star">*</span></label>
            <div class="company-search">
              <input
                v-model="form.company_name"
                placeholder="Search or type company name"
                autocomplete="off"
                @input="searchCompanies"
                @focus="searchCompanies"
                @keyup.enter="selectFirstCompany"
              >
              <span v-if="form.contact_id" class="company-linked-badge">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Linked to CRM
              </span>
              <div v-if="showCompanyResults" class="company-results">
                <button v-for="company in companyResults" :key="company.id" type="button" @click="selectCompany(company)">
                  {{ company.name }}
                </button>
                <div v-if="!companyLoading && companyResults.length === 0" class="company-empty">No company found</div>
                <div v-if="companyLoading" class="company-empty">Searching...</div>
              </div>
            </div>
          </div>

          <div class="field full">
            <label>Place <span class="req-star">*</span></label>
            <div class="place-search">
              <input
                v-model="placeSearch"
                placeholder="Search place…"
                autocomplete="off"
                @focus="showPlaceDrop = true"
                @blur="onPlaceBlur"
                @keyup.esc="showPlaceDrop = false"
              >
              <div v-if="showPlaceDrop && filteredPlaces.length > 0" class="place-results">
                <button
                  v-for="place in filteredPlaces"
                  :key="place.id"
                  type="button"
                  @mousedown.prevent="selectPlace(place)"
                >{{ place.site_name }}</button>
              </div>
            </div>
          </div>

          <div class="entry-modal-grid">
            <div class="field" style="grid-column: 1 / -1">
              <label>Product</label>
              <select v-model="form.product_type">
                <option v-for="product in productOptions" :key="product" :value="product">{{ product }}</option>
              </select>
            </div>
            <div class="field">
              <label>Start Date</label>
              <input v-model="form.start_date" type="date">
            </div>
            <div class="field">
              <label>End Date</label>
              <input v-model="form.end_date" type="date">
            </div>
          </div>
        </div>

        <footer class="entry-modal-foot">
          <p v-if="!canAdd" class="entry-validation-hint">
            <template v-if="!form.company_name.trim()">Enter a company name to continue</template>
            <template v-else-if="!form.site_name.trim()">Select a place to continue</template>
            <template v-else-if="!hasValidDateRange">End date must be after start date</template>
          </p>
          <div class="entry-modal-foot-actions">
            <button type="button" class="btn-clear" @click="emit('close')">Cancel</button>
            <button type="button" class="btn-add" :disabled="saving || !canAdd" @click="emit('save')">
              {{ saving ? 'Saving…' : 'Save Booking' }}
            </button>
          </div>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue';
import api from '../api.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  productOptions: { type: Array, default: () => [] },
  placeOptions: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'save']);
const form = defineModel('form', { required: true });

const companyResults = ref([]);
const companyLoading = ref(false);
let companySearchTimer = null;

const placeSearch = ref('');
const showPlaceDrop = ref(false);

const hasValidDateRange = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return true;
  return form.value.end_date >= form.value.start_date;
});
const canAdd = computed(() => form.value.company_name.trim() && form.value.site_name.trim() && hasValidDateRange.value);
const showCompanyResults = computed(() => form.value.company_name.trim().length > 0 && !form.value.contact_id);
const filteredPlaces = computed(() => {
  const q = placeSearch.value.toLowerCase().trim();
  if (!q) return props.placeOptions;
  return props.placeOptions.filter(p => p.site_name.toLowerCase().includes(q));
});

function searchCompanies() {
  form.value.contact_id = null;
  clearTimeout(companySearchTimer);

  if (!form.value.company_name.trim()) {
    companyResults.value = [];
    return;
  }

  companySearchTimer = setTimeout(loadCompanyResults, 250);
}

async function loadCompanyResults() {
  companyLoading.value = true;
  try {
    const res = await api.get('/v1/contacts', {
      params: { search: form.value.company_name.trim(), per_page: 8 },
    });
    companyResults.value = res.data.data ?? [];
  } catch (_) {
    companyResults.value = [];
  } finally {
    companyLoading.value = false;
  }
}

function selectCompany(company) {
  form.value.contact_id = company.id;
  form.value.company_name = company.name;
  companyResults.value = [];
}

function selectFirstCompany() {
  if (companyResults.value.length > 0) selectCompany(companyResults.value[0]);
}

function applyPlaceDefaults() {
  const selected = props.placeOptions.find((place) => place.site_name === form.value.site_name);
  if (!selected) return;

  form.value.status = selected.status;
  form.value.type = selected.type;
  form.value.product_type = selected.product_type;
}

function selectPlace(place) {
  form.value.site_name = place.site_name;
  placeSearch.value = place.site_name;
  showPlaceDrop.value = false;
  applyPlaceDefaults();
}

function onPlaceBlur() {
  setTimeout(() => { showPlaceDrop.value = false; }, 150);
}
</script>

<style scoped>
@keyframes overlay-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes modal-spring-in {
  from { opacity: 0; transform: scale(0.92) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-backdrop {
  position: fixed; inset: 0; z-index: 2000; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center; padding: 24px;
  animation: overlay-fade-in 0.18s ease;
}
.modal-backdrop > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }

.entry-modal {
  width: min(620px, 95vw); max-height: 85vh; background: var(--surface);
  border-radius: var(--radius-lg); box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  display: flex; flex-direction: column; overflow: hidden;
}
.entry-modal-head {
  position: relative; display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--border);
}
.entry-modal-head h2 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: var(--text-1); }
.entry-modal-head p { margin: 0; font-size: 12px; color: var(--text-2); font-weight: 500; }
.entry-modal-body { padding: 20px; overflow: auto; display: flex; flex-direction: column; gap: 12px; }
.entry-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.entry-modal-foot {
  display: flex; flex-direction: column; gap: 8px;
  padding: 12px 20px 14px; border-top: 1px solid var(--border);
}
.entry-modal-foot-actions { display: flex; justify-content: flex-end; gap: 8px; }
.entry-validation-hint {
  margin: 0; font-size: 11.5px; font-weight: 600; color: var(--warning);
  text-align: right; padding: 0 2px;
}

.detail-close {
  flex-shrink: 0; width: 30px; height: 30px; border: none; margin-top: 2px;
  border-radius: var(--radius-sm); background: var(--surface-2); color: var(--text-1);
  font-size: 20px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.detail-close:hover { background: var(--border); }

.field { display: flex; flex-direction: column; gap: 5px; min-width: 150px; }
.field.full { width: 100%; }
.field label {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2);
}
.field input, .field select {
  height: 38px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 12px;
  font-size: 13px; outline: none; background: var(--surface); color: var(--text-1);
}
.field input:focus, .field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.req-star { color: var(--danger); font-weight: 700; margin-left: 1px; }

.company-search { position: relative; }
.company-search input { width: 100%; }
.company-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 20; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-md); overflow: hidden;
}
.company-results button {
  width: 100%; min-height: 36px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 8px 12px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.company-results button:hover { background: var(--primary-soft); color: var(--primary-text); }
.company-empty { padding: 10px; color: var(--text-3); font-size: 12px; font-weight: 600; }
.company-linked-badge {
  display: inline-flex; align-items: center; gap: 4px;
  margin-top: 5px; font-size: 10.5px; font-weight: 700; color: var(--success);
  background: var(--success-soft); border-radius: 999px; padding: 2px 8px;
}

.place-search { position: relative; }
.place-search input { width: 100%; }
.place-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 200;
  background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-md); overflow: hidden; max-height: 220px;
}
.place-results button {
  width: 100%; min-height: 34px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 7px 12px;
  font-size: 12.5px; font-weight: 500; cursor: pointer; display: block;
}
.place-results button:last-child { border-bottom: none; }
.place-results button:hover { background: var(--primary-soft); color: var(--primary-text); }

.btn-add, .btn-clear {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.4); }
.btn-add:hover { background: var(--primary-hover); }
.btn-add:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }
</style>
