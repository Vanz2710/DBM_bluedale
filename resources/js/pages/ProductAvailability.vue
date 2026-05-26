<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Product Availability</h1>
        <p>Billboard, Temp Board and Lamp Post Bunting rental schedule for {{ year }}.</p>
      </div>
      <div class="year-control">
        <button type="button" @click="changeYear(-1)">&lt;</button>
        <input v-model.number="year" type="number" min="2020" max="2100" @change="load">
        <button type="button" @click="changeYear(1)">&gt;</button>
      </div>
    </div>

    <div class="entry-panel">
      <div class="field company-field">
        <label>Company</label>
        <div class="company-search">
          <input
            v-model="form.company_name"
            placeholder="Search or type company name"
            autocomplete="off"
            @input="searchCompanies"
            @focus="searchCompanies"
            @keyup.enter="selectFirstCompany"
          >
          <div v-if="showCompanyResults" class="company-results">
            <button v-for="company in companyResults" :key="company.id" type="button" @click="selectCompany(company)">
              {{ company.name }}
            </button>
            <div v-if="!companyLoading && companyResults.length === 0" class="company-empty">No company found</div>
            <div v-if="companyLoading" class="company-empty">Searching...</div>
          </div>
        </div>
      </div>

      <div class="field place-field">
        <label>Place</label>
        <select v-model="form.site_name" @change="applyPlaceDefaults">
          <option value="">Select place</option>
          <option v-for="place in placeOptions" :key="place.id" :value="place.site_name">
            {{ place.site_name }}
          </option>
        </select>
      </div>

      <div class="field">
        <label>Product</label>
        <select v-model="form.product_type">
          <option v-for="product in productOptions" :key="product" :value="product">{{ product }}</option>
        </select>
      </div>

      <div class="field small">
        <label>Month</label>
        <select v-model.number="form.month">
          <option v-for="month in months" :key="month.value" :value="month.value">{{ month.short }}</option>
        </select>
      </div>

      <div class="field date-field">
        <label>Start Rent</label>
        <input v-model="form.start_date" type="date">
      </div>

      <div class="field date-field">
        <label>End Rent</label>
        <input v-model="form.end_date" type="date">
      </div>

      <button class="btn-add" :disabled="saving || !canAdd" @click="addBooking">
        {{ saving ? 'Saving...' : '+ Save Booking' }}
      </button>
    </div>

    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="toolbar">
      <div class="field">
        <label>Product Filter</label>
        <select v-model="productFilter" @change="load">
          <option value="">All products</option>
          <option v-for="product in productOptions" :key="product" :value="product">{{ product }}</option>
        </select>
      </div>
      <div class="field">
        <label>Search</label>
        <input v-model="search" placeholder="Place or company" @keyup.enter="load">
      </div>
      <button class="btn-dark" @click="load">Search</button>
      <button class="btn-clear" @click="clearFilters">Clear</button>
    </div>

    <div class="proposal-panel">
      <div class="field">
        <label>Client Name</label>
        <input v-model="proposalForm.client_name" placeholder="Optional">
      </div>
      <div class="field">
        <label>Attention</label>
        <input v-model="proposalForm.attention" placeholder="Optional">
      </div>
      <div class="field small">
        <label>Duration</label>
        <input v-model.number="proposalForm.duration" type="number" min="1" max="36">
      </div>
      <button class="btn-proposal" :disabled="generatingProposal || selectedProductIds.length === 0" @click="generateProposal">
        {{ generatingProposal ? 'Generating...' : `Generate Proposal PDF (${selectedProductIds.length})` }}
      </button>
    </div>

    <div class="table-wrap">
      <div class="table-title">
        <span>CRM TB Availability</span>
        <span>{{ rows.length }} product(s)</span>
      </div>

      <div v-if="loading" class="loading-msg">Loading...</div>
      <table v-else>
        <thead>
          <tr>
            <th class="select-col" @click.stop>
              <input type="checkbox" :checked="allRowsSelected" :disabled="rows.length === 0" @change="toggleAllRows">
            </th>
            <th class="no-col">No</th>
            <th class="site-col">Place</th>
            <th>Status</th>
            <th>Type</th>
            <th>Product</th>
            <th v-for="month in months" :key="month.value">{{ month.short }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="18" class="empty-state">No product bookings yet.</td>
          </tr>
          <tr v-for="(row, index) in rows" :key="row.id" class="product-row" @click="openProductDetail(row)">
            <td class="select-col" @click.stop>
              <input
                type="checkbox"
                :checked="selectedProductIds.includes(row.id)"
                @change="toggleProductSelection(row.id)"
              >
            </td>
            <td class="no-col">{{ index + 1 }}</td>
            <td class="site-col">
              <span class="cell-label place-label">{{ row.site_name }}</span>
            </td>
            <td><span class="cell-label center-label">{{ row.status }}</span></td>
            <td><span class="cell-label center-label">{{ row.type }}</span></td>
            <td><span class="cell-label center-label">{{ row.product_type }}</span></td>
            <td v-for="month in months" :key="month.value" class="booking-cell" :class="{ booked: bookingFor(row, month.value) }">
              <template v-if="bookingFor(row, month.value)">
                <span class="cell-label booking-name">{{ bookingFor(row, month.value).company_name }}</span>
                <div class="rent-dates">
                  <span>{{ formatDate(bookingFor(row, month.value).start_date) }}</span>
                  <span>{{ formatDate(bookingFor(row, month.value).end_date) }}</span>
                </div>
                <button
                  type="button"
                  class="booking-remove"
                  title="Remove booking"
                  @click.stop="removeBooking(row, bookingFor(row, month.value))"
                >
                  Delete
                </button>
              </template>
              <span v-else class="available-label">Available</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedProduct" class="modal-backdrop" @click.self="closeProductDetail">
      <section class="product-detail-modal" role="dialog" aria-modal="true">
        <div class="detail-side"></div>
        <div class="detail-content">
          <button type="button" class="detail-close" aria-label="Close product detail" @click="closeProductDetail">&times;</button>

          <header class="detail-header">
            <h2>{{ selectedProduct.product_type }}</h2>
            <p>{{ selectedProduct.site_name }}</p>
          </header>

          <div class="detail-grid">
            <div class="detail-panel">
              <div class="detail-actions">
                <span>Product Detail</span>
                <button v-if="!editingDetails" type="button" @click="startDetailEdit">Edit</button>
                <div v-else class="detail-edit-actions">
                  <button type="button" class="btn-save-detail" :disabled="savingDetails" @click="saveDetails">
                    {{ savingDetails ? 'Saving...' : 'Save' }}
                  </button>
                  <button type="button" class="btn-cancel-detail" :disabled="savingDetails" @click="cancelDetailEdit">Cancel</button>
                </div>
              </div>

              <table class="detail-table">
                <tbody>
                  <tr v-for="item in detailRows" :key="item.label">
                    <th>{{ item.label }}</th>
                    <td>
                      <input
                        v-if="editingDetails && item.field"
                        v-model="detailForm[item.field]"
                        :aria-label="item.label"
                      >
                      <a v-else-if="item.href" :href="item.href" target="_blank" rel="noopener">{{ item.value }}</a>
                      <span v-else>{{ item.value }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="landmark-panel">
              <div class="landmark-actions">
                <span>Nearest Landmark</span>
                <button v-if="!editingLandmarks" type="button" @click="startLandmarkEdit">Edit</button>
                <div v-else class="landmark-edit-actions">
                  <button type="button" class="btn-save-landmarks" :disabled="savingLandmarks" @click="saveLandmarks">
                    {{ savingLandmarks ? 'Saving...' : 'Save' }}
                  </button>
                  <button type="button" class="btn-cancel-landmarks" :disabled="savingLandmarks" @click="cancelLandmarkEdit">Cancel</button>
                </div>
              </div>

              <table class="landmark-table">
                <tbody>
                  <tr v-for="(landmark, index) in activeLandmarkRows" :key="`${landmark.category}-${index}`">
                    <th>
                      <input v-if="editingLandmarks" v-model="landmarkForm[index].category" aria-label="Landmark category">
                      <span v-else>{{ landmark.category }}</span>
                    </th>
                    <td>
                      <input v-if="editingLandmarks" v-model="landmarkForm[index].place" aria-label="Nearby place">
                      <span v-else>{{ landmark.place }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <a class="map-link" :href="productMapUrl" target="_blank" rel="noopener">Open Location in Google Maps</a>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../api.js';

const months = [
  { value: 1, short: 'Jan' },
  { value: 2, short: 'Feb' },
  { value: 3, short: 'Mar' },
  { value: 4, short: 'Apr' },
  { value: 5, short: 'May' },
  { value: 6, short: 'Jun' },
  { value: 7, short: 'Jul' },
  { value: 8, short: 'Aug' },
  { value: 9, short: 'Sep' },
  { value: 10, short: 'Oct' },
  { value: 11, short: 'Nov' },
  { value: 12, short: 'Dec' },
];

const rows = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const search = ref('');
const year = ref(new Date().getFullYear());
const productFilter = ref('');
const productOptions = ref(['Billboard', 'Temp Board', 'Lamp Post Bunting']);
const statusOptions = ref(['Existing', 'Raw New']);
const typeOptions = ref(['A1', 'A2', 'ongoing', 'reject']);
const companyResults = ref([]);
const companyLoading = ref(false);
const selectedContactId = ref(null);
const selectedProduct = ref(null);
const selectedProductIds = ref([]);
const generatingProposal = ref(false);
const proposalForm = ref({
  client_name: '',
  attention: '',
  duration: 1,
});
const editingDetails = ref(false);
const savingDetails = ref(false);
const detailForm = ref({
  site_code: '',
  size: '',
  location: '',
  state_city: '',
  coordinate: '',
});
const editingLandmarks = ref(false);
const savingLandmarks = ref(false);
const landmarkForm = ref([]);
let companySearchTimer = null;

const form = ref({
  company_name: '',
  contact_id: null,
  site_name: '',
  status: 'Existing',
  type: 'A1',
  product_type: 'Temp Board',
  month: new Date().getMonth() + 1,
  start_date: '',
  end_date: '',
});

const hasValidDateRange = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return true;
  return form.value.end_date >= form.value.start_date;
});
const canAdd = computed(() => form.value.company_name.trim() && form.value.site_name.trim() && hasValidDateRange.value);
const showCompanyResults = computed(() => form.value.company_name.trim().length > 0 && !selectedContactId.value);
const placeOptions = computed(() => [...rows.value].sort((a, b) => a.site_name.localeCompare(b.site_name)));
const allRowsSelected = computed(() => rows.value.length > 0 && rows.value.every((row) => selectedProductIds.value.includes(row.id)));
const productMapUrl = computed(() => {
  if (!selectedProduct.value) return '#';

  const query = selectedProduct.value.coordinate || selectedProduct.value.site_name;
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
});
const detailRows = computed(() => {
  if (!selectedProduct.value) return [];

  return [
    { label: 'Site', value: productSiteCode(selectedProduct.value), field: 'site_code' },
    { label: 'Size', value: selectedProduct.value.size || defaultProductSize(selectedProduct.value), field: 'size' },
    { label: 'Location', value: productLocation(selectedProduct.value), field: 'location' },
    { label: 'State & City', value: selectedProduct.value.state_city || inferredStateCity(selectedProduct.value.site_name), field: 'state_city' },
    {
      label: 'Coordinate',
      value: selectedProduct.value.coordinate || 'Not set',
      href: selectedProduct.value.coordinate ? productMapUrl.value : null,
      field: 'coordinate',
    },
  ];
});
const landmarkRows = computed(() => {
  const landmarks = selectedProduct.value?.nearest_landmarks;
  if (Array.isArray(landmarks) && landmarks.length > 0) return landmarks;

  return defaultLandmarks();
});
const activeLandmarkRows = computed(() => (editingLandmarks.value ? landmarkForm.value : landmarkRows.value));

function defaultLandmarks() {
  return [
    { category: 'Exhibition Center', place: 'Not set' },
    { category: 'Shopping Mall', place: 'Not set' },
    { category: 'International School', place: 'Not set' },
    { category: 'Hosp/ Medical Centre', place: 'Not set' },
  ];
}

function normalizeRow(row) {
  return {
    ...row,
    bookings: row.bookings || [],
  };
}

function toggleProductSelection(productId) {
  if (selectedProductIds.value.includes(productId)) {
    selectedProductIds.value = selectedProductIds.value.filter((id) => id !== productId);
    return;
  }

  selectedProductIds.value = [...selectedProductIds.value, productId];
}

function toggleAllRows() {
  if (allRowsSelected.value) {
    selectedProductIds.value = selectedProductIds.value.filter((id) => !rows.value.some((row) => row.id === id));
    return;
  }

  selectedProductIds.value = [...new Set([...selectedProductIds.value, ...rows.value.map((row) => row.id)])];
}

function bookingFor(row, month) {
  return row.bookings.find((booking) => Number(booking.month) === Number(month));
}

function monthFromDate(value) {
  if (!value) return null;
  const parts = value.split('-');
  const month = Number(parts[1]);
  return month >= 1 && month <= 12 ? month : null;
}

function yearFromDate(value) {
  if (!value) return null;
  const yearValue = Number(value.split('-')[0]);
  return yearValue >= 2020 && yearValue <= 2100 ? yearValue : null;
}

function formatDate(value) {
  if (!value) return '';
  const [date] = value.split('T');
  const parts = date.split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function openProductDetail(row) {
  selectedProduct.value = row;
  editingDetails.value = false;
  detailForm.value = emptyDetailForm();
  editingLandmarks.value = false;
  landmarkForm.value = [];
}

function closeProductDetail() {
  selectedProduct.value = null;
  editingDetails.value = false;
  detailForm.value = emptyDetailForm();
  editingLandmarks.value = false;
  landmarkForm.value = [];
}

function emptyDetailForm() {
  return {
    site_code: '',
    size: '',
    location: '',
    state_city: '',
    coordinate: '',
  };
}

function startDetailEdit() {
  if (!selectedProduct.value) return;

  detailForm.value = {
    site_code: productSiteCode(selectedProduct.value),
    size: selectedProduct.value.size || defaultProductSize(selectedProduct.value),
    location: productLocation(selectedProduct.value),
    state_city: selectedProduct.value.state_city || inferredStateCity(selectedProduct.value.site_name),
    coordinate: selectedProduct.value.coordinate || '',
  };
  editingDetails.value = true;
}

function cancelDetailEdit() {
  editingDetails.value = false;
  detailForm.value = emptyDetailForm();
}

async function saveDetails() {
  if (!selectedProduct.value) return;

  savingDetails.value = true;
  error.value = '';

  try {
    const res = await api.put(`/v1/product-availability/products/${selectedProduct.value.id}`, {
      site_name: buildSiteNameFromDetails(),
      site_code: detailForm.value.site_code.trim() || null,
      size: detailForm.value.size.trim() || null,
      state_city: detailForm.value.state_city.trim() || null,
      coordinate: detailForm.value.coordinate.trim() || null,
    });
    const prepared = normalizeRow(res.data.data);
    const index = rows.value.findIndex((row) => row.id === prepared.id);
    if (index !== -1) rows.value[index] = prepared;
    selectedProduct.value = prepared;
    editingDetails.value = false;
    detailForm.value = emptyDetailForm();
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save product details.';
  } finally {
    savingDetails.value = false;
  }
}

function buildSiteNameFromDetails() {
  const stateCity = detailForm.value.state_city.trim();
  const location = detailForm.value.location.trim();

  if (stateCity && location) return `${stateCity}: ${location}`;
  if (location) return location;
  return selectedProduct.value.site_name;
}

function startLandmarkEdit() {
  landmarkForm.value = landmarkRows.value.map((landmark) => ({
    category: landmark.category === 'Not set' ? '' : landmark.category,
    place: landmark.place === 'Not set' ? '' : landmark.place,
  }));
  editingLandmarks.value = true;
}

function cancelLandmarkEdit() {
  editingLandmarks.value = false;
  landmarkForm.value = [];
}

async function saveLandmarks() {
  if (!selectedProduct.value) return;

  savingLandmarks.value = true;
  error.value = '';

  const nearestLandmarks = landmarkForm.value
    .map((landmark) => ({
      category: landmark.category.trim(),
      place: landmark.place.trim(),
    }))
    .filter((landmark) => landmark.category || landmark.place)
    .map((landmark) => ({
      category: landmark.category || 'Landmark',
      place: landmark.place || 'Not set',
    }));

  try {
    const res = await api.put(`/v1/product-availability/products/${selectedProduct.value.id}`, {
      nearest_landmarks: nearestLandmarks,
    });
    const prepared = normalizeRow(res.data.data);
    const index = rows.value.findIndex((row) => row.id === prepared.id);
    if (index !== -1) rows.value[index] = prepared;
    selectedProduct.value = prepared;
    editingLandmarks.value = false;
    landmarkForm.value = [];
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save nearest landmarks.';
  } finally {
    savingLandmarks.value = false;
  }
}

function productSiteCode(row) {
  if (row.site_code) return row.site_code;
  return `${row.product_type.substring(0, 2).toUpperCase()}-${String(row.id).padStart(4, '0')}`;
}

function defaultProductSize(row) {
  if (row.product_type === 'Temp Board') return '15 feet x 10 feet';
  return 'Not set';
}

function productLocation(row) {
  const [, location] = row.site_name.split(/:\s(.+)/);
  return location || row.site_name;
}

function inferredStateCity(siteName) {
  return siteName.split(':')[0] || 'Not set';
}

watch(() => form.value.start_date, (startDate) => {
  const startMonth = monthFromDate(startDate);
  const startYear = yearFromDate(startDate);

  if (startMonth) form.value.month = startMonth;
  if (startYear && Number(year.value) !== startYear) {
    year.value = startYear;
    load();
  }
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = { year: year.value };
    if (search.value.trim()) params.search = search.value.trim();
    if (productFilter.value) params.product_type = productFilter.value;
    const res = await api.get('/v1/product-availability', { params });
    rows.value = (res.data.data ?? []).map(normalizeRow);
    selectedProductIds.value = selectedProductIds.value.filter((id) => rows.value.some((row) => row.id === id));
    productOptions.value = res.data.products ?? productOptions.value;
    statusOptions.value = res.data.statuses ?? statusOptions.value;
    typeOptions.value = res.data.types ?? typeOptions.value;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load product availability.';
  } finally {
    loading.value = false;
  }
}

async function generateProposal() {
  if (selectedProductIds.value.length === 0) return;

  generatingProposal.value = true;
  error.value = '';

  try {
    const res = await api.post('/v1/product-availability/proposal', {
      product_ids: selectedProductIds.value,
      client_name: proposalForm.value.client_name.trim() || null,
      attention: proposalForm.value.attention.trim() || null,
      duration: proposalForm.value.duration || 1,
    }, {
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `proposal-${new Date().toISOString().slice(0, 10)}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to generate proposal PDF.';
  } finally {
    generatingProposal.value = false;
  }
}

function changeYear(delta) {
  year.value = Number(year.value) + delta;
  load();
}

function searchCompanies() {
  selectedContactId.value = null;
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
  selectedContactId.value = company.id;
  form.value.contact_id = company.id;
  form.value.company_name = company.name;
  companyResults.value = [];
}

function selectFirstCompany() {
  if (companyResults.value.length > 0) selectCompany(companyResults.value[0]);
}

function applyPlaceDefaults() {
  const selected = rows.value.find((row) => row.site_name === form.value.site_name);
  if (!selected) return;

  form.value.status = selected.status;
  form.value.type = selected.type;
  form.value.product_type = selected.product_type;
}

async function addBooking() {
  if (!canAdd.value) return;
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/v1/product-availability', {
      ...form.value,
      company_name: form.value.company_name.trim(),
      site_name: form.value.site_name.trim(),
      contact_id: selectedContactId.value,
      start_date: form.value.start_date || null,
      end_date: form.value.end_date || null,
      year: yearFromDate(form.value.start_date) ?? year.value,
      month: monthFromDate(form.value.start_date) ?? form.value.month,
    });
    upsertRow(res.data.data);
    form.value.company_name = '';
    form.value.contact_id = null;
    form.value.start_date = '';
    form.value.end_date = '';
    selectedContactId.value = null;
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save booking.';
  } finally {
    saving.value = false;
  }
}

function upsertRow(row) {
  const prepared = normalizeRow(row);
  const index = rows.value.findIndex((item) => item.id === prepared.id);
  if (index === -1) {
    rows.value.unshift(prepared);
  } else {
    rows.value[index] = prepared;
  }
}

async function saveProduct(row) {
  error.value = '';
  try {
    await api.put(`/v1/product-availability/products/${row.id}`, {
      site_name: row.site_name,
      status: row.status,
      type: row.type,
      product_type: row.product_type,
    });
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update product row.';
    load();
  }
}

async function updateMonth(row, month, value) {
  const companyName = value.trim();
  const booking = bookingFor(row, month);

  if (!companyName && booking) {
    await deleteBooking(row, booking);
    return;
  }

  if (!companyName) return;

  try {
    if (booking) {
      const res = await api.put(`/v1/product-availability/bookings/${booking.id}`, {
        company_name: companyName,
        contact_id: booking.contact_id,
        start_date: booking.start_date || null,
        end_date: booking.end_date || null,
      });
      Object.assign(booking, res.data.data);
      return;
    }

    const res = await api.post('/v1/product-availability', {
      site_name: row.site_name,
      status: row.status,
      type: row.type,
      product_type: row.product_type,
      company_name: companyName,
      start_date: null,
      end_date: null,
      year: year.value,
      month,
    });
    upsertRow(res.data.data);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update month booking.';
    load();
  }
}

async function updateBookingDate(row, month, field, value) {
  const booking = bookingFor(row, month);
  if (!booking) return;

  const next = {
    ...booking,
    [field]: value || null,
  };
  const nextMonth = field === 'start_date' ? monthFromDate(value) : null;
  const nextYear = field === 'start_date' ? yearFromDate(value) : null;

  if (next.start_date && next.end_date && next.end_date < next.start_date) {
    error.value = 'End rent date must be same as or after start rent date.';
    load();
    return;
  }

  try {
    const res = await api.put(`/v1/product-availability/bookings/${booking.id}`, {
      company_name: booking.company_name,
      contact_id: booking.contact_id,
      start_date: next.start_date,
      end_date: next.end_date,
      year: nextYear ?? booking.year,
      month: nextMonth ?? booking.month,
    });
    Object.assign(booking, res.data.data);
    error.value = '';
    if (nextYear && Number(year.value) !== nextYear) {
      year.value = nextYear;
      await load();
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update rental date.';
    load();
  }
}

async function deleteBooking(row, booking) {
  try {
    await api.delete(`/v1/product-availability/bookings/${booking.id}`);
    row.bookings = row.bookings.filter((item) => item.id !== booking.id);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove booking.';
    load();
  }
}

async function removeBooking(row, booking) {
  if (!booking) return;
  const confirmed = window.confirm(`Remove booking for ${booking.company_name}?`);
  if (!confirmed) return;

  await deleteBooking(row, booking);
}

function clearFilters() {
  productFilter.value = '';
  search.value = '';
  load();
}

onMounted(load);
</script>

<style scoped>
.page { padding: 24px 28px; color: #172033; }
.page-header {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 18px 20px;
  display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 14px;
}
.page-header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 850; }
.page-header p { margin: 0; color: #64748b; font-size: 13px; }
.year-control { display: flex; align-items: center; gap: 6px; }
.year-control button {
  width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 7px; background: #f8fafc;
  color: #0f172a; font-size: 18px; cursor: pointer;
}
.year-control input {
  width: 88px; height: 34px; border: 1px solid #cbd5e1; border-radius: 7px; text-align: center;
  font-size: 14px; font-weight: 800;
}
.entry-panel, .toolbar, .proposal-panel {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 14px;
  margin-bottom: 14px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 5px; min-width: 170px; }
.field.company-field { flex: 0 1 430px; min-width: 320px; }
.field.place-field { flex: 0 1 480px; min-width: 340px; }
.field.small { min-width: 112px; }
.field.date-field { min-width: 138px; }
.field label {
  font-size: 10px; font-weight: 850; text-transform: uppercase; letter-spacing: 0.7px; color: #64748b;
}
.field input, .field select {
  height: 36px; border: 1.5px solid #dbe3ee; border-radius: 7px; padding: 0 10px;
  font-size: 13px; outline: none; background: white; color: #172033;
}
.field input:focus, .field select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.company-search { position: relative; }
.company-search input { width: 100%; }
.company-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 20; background: white;
  border: 1.5px solid #dbe3ee; border-radius: 8px; box-shadow: 0 12px 28px rgba(15,23,42,0.16); overflow: hidden;
}
.company-results button {
  width: 100%; min-height: 36px; border: none; border-bottom: 1px solid #eef2f7; background: white;
  color: #172033; text-align: left; padding: 8px 10px; font-size: 13px; font-weight: 700; cursor: pointer;
}
.company-results button:hover { background: #eff6ff; color: #1d4ed8; }
.company-empty { padding: 10px; color: #64748b; font-size: 12px; font-weight: 700; }
.btn-add, .btn-dark, .btn-clear, .btn-proposal {
  height: 36px; border: none; border-radius: 7px; padding: 0 15px; font-size: 13px; font-weight: 850; cursor: pointer;
}
.btn-add { background: #2563eb; color: white; }
.btn-add:disabled, .btn-proposal:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-dark { background: #172033; color: white; }
.btn-clear { background: #eef2f7; color: #475569; }
.btn-proposal { background: #0f766e; color: white; }
.error-msg {
  background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;
  font-size: 13px; font-weight: 750;
}
.table-wrap { background: white; border-radius: 8px; border: 1px solid #111827; overflow: auto; }
.table-title {
  background: #d9d21a; color: #111827; border-bottom: 2px solid #111827; padding: 9px 12px;
  display: flex; justify-content: space-between; font-size: 12px; font-weight: 900; text-transform: uppercase;
}
.loading-msg { text-align: center; padding: 44px; color: #64748b; }
table { width: 100%; min-width: 1320px; border-collapse: collapse; font-size: 12px; }
th, td { border: 1.5px solid #111827; }
thead th {
  background: #d9d21a; color: #111827; padding: 8px 7px; font-size: 11px; font-weight: 900;
  text-transform: uppercase; text-align: center; white-space: nowrap;
}
tbody td { height: 44px; padding: 0; color: #111827; background: #ffffff; vertical-align: middle; }
.product-row { cursor: pointer; }
.product-row:hover td { background: #f8fafc; }
.product-row:hover .booking-cell.booked { background: #d7eefc; }
.no-col { width: 48px; text-align: center; font-weight: 850; }
.select-col { width: 42px; min-width: 42px; text-align: center; }
.select-col input {
  appearance: none; -webkit-appearance: none;
  width: 18px; height: 18px; min-height: 0; padding: 0; cursor: pointer;
  border: 1.5px solid #1f2937; border-radius: 2px; background: #ffffff;
  display: inline-grid; place-content: center; vertical-align: middle;
}
.select-col input::before {
  content: ""; width: 10px; height: 10px; transform: scale(0);
  background: #1d4ed8;
}
.select-col input:checked::before { transform: scale(1); }
.select-col input:focus { outline: 2px solid rgba(37,99,235,0.32); outline-offset: 2px; }
.select-col input:disabled {
  border-color: #94a3b8; background: #e2e8f0; cursor: not-allowed;
}
.site-col { width: 330px; min-width: 330px; }
td select, td input {
  width: 100%; min-height: 42px; border: none; background: transparent; color: #111827;
  font-size: 12px; font-weight: 700; outline: none;
}
td select { text-align: center; text-align-last: center; padding: 0 5px; cursor: pointer; }
.cell-label {
  display: flex; align-items: center; min-height: 42px; width: 100%;
  color: #111827; font-size: 12px; font-weight: 800; line-height: 1.25;
}
.place-label { padding: 7px 8px; justify-content: flex-start; }
.center-label { justify-content: center; text-align: center; padding: 0 6px; }
.booking-cell { min-width: 142px; }
.booking-cell.booked { background: #e0f2fe; }
.booking-name {
  min-height: 30px; justify-content: center; text-align: center; padding: 4px 6px;
  border-bottom: 1px solid rgba(15,23,42,0.16); color: #0f172a; font-weight: 900;
}
.available-label {
  display: flex; min-height: 42px; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 12px; font-weight: 750;
}
.rent-dates { display: grid; grid-template-columns: 1fr; gap: 0; }
.rent-dates span {
  display: flex; align-items: center; justify-content: center; min-height: 26px;
  border-top: 1px solid rgba(15,23,42,0.1); font-size: 10px; font-weight: 800;
  padding: 0 3px; background: rgba(255,255,255,0.45);
}
.booking-remove {
  width: 100%; min-height: 26px; border: none; border-top: 1px solid rgba(153,27,27,0.18);
  background: rgba(254,226,226,0.78); color: #991b1b; font-size: 10px; font-weight: 900;
  text-transform: uppercase; cursor: pointer;
}
.booking-remove:hover { background: #fecaca; }
.empty-state { text-align: center; padding: 36px; color: #64748b; font-size: 13px; font-weight: 700; background: white; }
.modal-backdrop {
  position: fixed; inset: 0; z-index: 50; background: rgba(15,23,42,0.58);
  display: flex; align-items: center; justify-content: center; padding: 22px;
}
.product-detail-modal {
  width: min(880px, 96vw); max-height: 92vh; background: #ffffff; color: #111827;
  display: grid; grid-template-columns: 136px 1fr; overflow: auto; border-radius: 6px;
  box-shadow: 0 24px 70px rgba(15,23,42,0.36);
}
.detail-side { background: #0f56ad; min-height: 520px; }
.detail-content { position: relative; padding: 28px 40px 34px; }
.detail-close {
  position: absolute; top: 12px; right: 14px; width: 32px; height: 32px; border: none;
  border-radius: 6px; background: #eef2f7; color: #172033; font-size: 22px; line-height: 1;
  cursor: pointer;
}
.detail-close:hover { background: #dbe3ee; }
.detail-header {
  width: min(440px, 100%); min-height: 62px; background: #0f56ad; color: #ffffff;
  border-radius: 22px; display: flex; flex-direction: column; justify-content: center;
  align-items: center; text-align: center; padding: 10px 18px; margin-bottom: 18px;
}
.detail-header h2 { margin: 0; font-size: 13px; font-weight: 900; text-transform: uppercase; }
.detail-header p { margin: 4px 0 0; font-size: 11px; font-weight: 750; line-height: 1.25; opacity: 0.9; }
.detail-grid { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(280px, 1.15fr); gap: 18px; align-items: start; }
.detail-panel, .landmark-panel { min-width: 0; }
.detail-actions, .landmark-actions {
  min-height: 40px; background: #171717; color: #ffffff; border: 1.5px solid #111827;
  border-bottom: none; display: flex; align-items: center; justify-content: space-between;
  gap: 10px; padding: 7px 10px 7px 16px; font-size: 10px; font-weight: 900; text-transform: uppercase;
}
.detail-actions button, .landmark-actions button {
  height: 26px; border: none; border-radius: 5px; padding: 0 10px; font-size: 10px;
  font-weight: 900; cursor: pointer;
}
.detail-actions > button, .landmark-actions > button, .btn-save-detail, .btn-save-landmarks { background: #ffffff; color: #172033; }
.btn-cancel-detail, .btn-cancel-landmarks { background: #334155; color: #ffffff; }
.detail-actions button:disabled, .landmark-actions button:disabled { opacity: 0.6; cursor: not-allowed; }
.detail-edit-actions, .landmark-edit-actions { display: flex; align-items: center; gap: 6px; }
.detail-table, .landmark-table { width: 100%; min-width: 0; border-collapse: collapse; font-size: 11px; }
.detail-table th, .detail-table td, .landmark-table th, .landmark-table td {
  border: 1.5px solid #111827; height: 40px; padding: 8px 10px; background: #ffffff; vertical-align: middle;
}
.detail-table th, .landmark-table th { width: 42%; text-transform: uppercase; font-size: 10px; font-weight: 900; text-align: center; }
.detail-table td, .landmark-table td { font-weight: 750; text-align: center; line-height: 1.3; }
.detail-table a { color: #2563eb; font-weight: 900; text-decoration: underline; }
.detail-table input, .landmark-table input {
  width: 100%; min-height: 30px; border: 1px solid #cbd5e1; border-radius: 5px;
  padding: 0 8px; color: #172033; font-size: 11px; font-weight: 800; text-align: center;
  outline: none;
}
.detail-table input:focus, .landmark-table input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.14); }
.map-link {
  display: inline-flex; align-items: center; justify-content: center; min-height: 36px; margin-top: 18px;
  padding: 0 14px; border-radius: 7px; background: #172033; color: #ffffff; font-size: 12px;
  font-weight: 850; text-decoration: none;
}
@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { align-items: stretch; flex-direction: column; }
  .field, .field.company-field, .field.place-field, .field.small { width: 100%; min-width: 0; }
  .btn-add, .btn-dark, .btn-clear, .btn-proposal { width: 100%; }
  .product-detail-modal { grid-template-columns: 1fr; }
  .detail-side { display: none; }
  .detail-content { padding: 22px 16px 24px; }
  .detail-header { width: calc(100% - 42px); border-radius: 14px; }
  .detail-grid { grid-template-columns: 1fr; }
}
</style>
