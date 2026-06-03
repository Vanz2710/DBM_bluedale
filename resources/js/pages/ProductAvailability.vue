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
      <button type="button" class="btn-register-product" @click="openRegisterModal">+ Register Product</button>
      <button type="button" class="btn-map-view" :class="{ 'btn-map-active': showMapView }" @click="toggleMapView">
        {{ showMapView ? 'Table View' : 'Map View' }}
      </button>
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

    <div v-if="showMapView" class="map-section">
      <div ref="mapEl" class="leaflet-map"></div>
      <div class="map-legend">
        <span class="legend-item"><span class="legend-dot" style="background:#2563eb"></span>Billboard</span>
        <span class="legend-item"><span class="legend-dot" style="background:#dc2626"></span>Temp Board</span>
        <span class="legend-item"><span class="legend-dot" style="background:#16a34a"></span>Lamp Post Bunting</span>
      </div>
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

          <div class="detail-map-links">
            <a class="map-link" :href="productMapUrl" target="_blank" rel="noopener">Open in Google Maps</a>
            <a v-if="productStreetViewUrl" class="street-view-link" :href="productStreetViewUrl" target="_blank" rel="noopener">Street View</a>
          </div>
        </div>
      </section>
    </div>

    <div v-if="showRegisterModal" class="modal-backdrop" @click.self="closeRegisterModal">
      <section class="register-modal" role="dialog" aria-modal="true">
        <div class="register-modal-header">
          <h2>Register New Product</h2>
          <button type="button" class="detail-close" @click="closeRegisterModal">&times;</button>
        </div>
        <div class="register-modal-body">
          <div v-if="registerError" class="error-msg">{{ registerError }}</div>
          <div class="register-form-grid">

            <!-- Row 1: Site Name + Open Google Maps -->
            <div class="field register-full">
              <label>Site Name *</label>
              <div class="register-site-row">
                <input v-model="registerForm.site_name" placeholder="e.g. KL - Bangsar: Jalan Maarof">
                <a :href="registerCoordMapUrl" target="_blank" rel="noopener" class="btn-open-gmaps">Open Google Maps</a>
              </div>
            </div>

            <!-- Row 2: Link Place (paste Google Maps link) -->
            <div class="field register-full">
              <label>Link Place <span class="field-hint">Paste a Google Maps or shortened link to auto-fill</span></label>
              <div class="maps-link-row">
                <input
                  v-model="mapsLink"
                  placeholder="Paste Google Maps link here..."
                  autocomplete="off"
                  :class="{ 'input-error-border': mapsLinkError }"
                  @input="onMapsLinkInput"
                >
                <span v-if="mapsLinkResolving" class="maps-link-resolving">
                  <span class="lm-spinner"></span> Resolving...
                </span>
              </div>
              <div v-if="mapsLinkError" class="maps-link-error">{{ mapsLinkError }}</div>
              <div v-else-if="registerForm.coordinate" class="coord-preview">
                Coordinate: {{ registerForm.coordinate }}
              </div>
            </div>

            <!-- Row 3: Product Type + Type -->
            <div class="field">
              <label>Product Type *</label>
              <select v-model="registerForm.product_type">
                <option v-for="p in productOptions" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="field">
              <label>Type *</label>
              <select v-model="registerForm.type">
                <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>

            <!-- Row 4: Status + State & City -->
            <div class="field">
              <label>Status *</label>
              <select v-model="registerForm.status">
                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div class="field">
              <label>State & City</label>
              <input v-model="registerForm.state_city" placeholder="Optional">
            </div>

            <!-- Row 5: Search Place -->
            <div class="field register-full">
              <label>Search Place</label>
              <div class="place-search-wrap">
                <input
                  v-model="placeSearch"
                  placeholder="Type a place name to auto-fill coordinate and landmarks..."
                  autocomplete="off"
                  @input="onPlaceSearchInput"
                >
                <div v-if="placeResults.length > 0 || placeSearching" class="place-results">
                  <div v-if="placeSearching" class="place-result-item place-loading">Searching...</div>
                  <button
                    v-for="result in placeResults"
                    :key="result.place_id"
                    type="button"
                    class="place-result-item"
                    @click="selectPlaceResult(result)"
                  >
                    {{ result.display_name }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Row 6: Nearest Landmarks -->
            <div class="field register-full">
              <div class="landmark-label-row">
                <label style="margin:0">
                  Nearest Landmarks
                  <span v-if="landmarkFetching" class="landmark-fetch-status">
                    <span class="lm-spinner"></span> Searching...
                  </span>
                  <span v-else-if="landmarkFetched" class="landmark-fetch-status landmark-fetch-ok">Auto-filled</span>
                </label>
                <button
                  v-if="registerForm.coordinate && !landmarkFetching"
                  type="button"
                  class="btn-refresh-landmarks"
                  @click="refreshLandmarks"
                >&#8635; Refresh</button>
              </div>
              <table class="register-landmark-table">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Nearest Place</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(lm, i) in registerForm.nearest_landmarks" :key="i">
                    <td class="lm-cat-cell">{{ lm.category }}</td>
                    <td class="lm-place-cell" :class="{ 'lm-not-found': lm.place === 'Not Found' }">
                      <span v-if="landmarkFetching" class="lm-skeleton"></span>
                      <template v-else>{{ lm.place }}</template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
          <div class="register-modal-footer">
            <button type="button" class="btn-cancel-register" @click="closeRegisterModal">Cancel</button>
            <button type="button" class="btn-save-register" :disabled="registerSaving || !registerForm.site_name.trim()" @click="submitRegisterProduct">
              {{ registerSaving ? 'Registering...' : 'Register Product' }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../api.js';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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
const showRegisterModal = ref(false);
const registerSaving = ref(false);
const registerError = ref('');
const registerForm = ref({
  site_name: '',
  product_type: 'Temp Board',
  status: 'Existing',
  type: 'A1',
  state_city: '',
  coordinate: '',
  nearest_landmarks: defaultLandmarks(),
});
const mapsLink = ref('');
const mapsLinkError = ref('');
const mapsLinkResolving = ref(false);
let mapsLinkTimer = null;
const landmarkFetching = ref(false);
const landmarkFetched = ref(false);
const showMapView = ref(false);
const mapEl = ref(null);
let leafletMap = null;
let mapMarkers = [];
const placeSearch = ref('');
const placeResults = ref([]);
const placeSearching = ref(false);
let placeSearchTimer = null;

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
const registerCoordMapUrl = computed(() => {
  const coord = registerForm.value?.coordinate;
  if (!coord) return 'https://maps.google.com';
  const parsed = parseCoordinate(coord);
  if (!parsed) return 'https://maps.google.com';
  return `https://www.google.com/maps?q=${parsed.lat},${parsed.lng}`;
});

const productStreetViewUrl = computed(() => {
  const coord = selectedProduct.value?.coordinate;
  if (!coord) return null;
  const parsed = parseCoordinate(coord);
  if (!parsed) return null;
  return `https://maps.google.com/maps?q=&layer=c&cbll=${parsed.lat},${parsed.lng}`;
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

function onPlaceSearchInput() {
  clearTimeout(placeSearchTimer);
  placeResults.value = [];
  if (!placeSearch.value.trim()) return;
  placeSearchTimer = setTimeout(searchPlaces, 400);
}

async function searchPlaces() {
  placeSearching.value = true;
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(placeSearch.value)}&limit=6&countrycodes=my`,
      { headers: { 'Accept-Language': 'en' } },
    );
    placeResults.value = await res.json();
  } catch {
    placeResults.value = [];
  } finally {
    placeSearching.value = false;
  }
}

function selectPlaceResult(result) {
  const lat = parseFloat(result.lat).toFixed(6);
  const lng = parseFloat(result.lon).toFixed(6);
  registerForm.value.coordinate = `${lat}, ${lng}`;
  placeSearch.value = result.display_name;
  placeResults.value = [];
  fetchNearestLandmarks(parseFloat(lat), parseFloat(lng));
}

function onCoordinateChange() {
  const parsed = parseCoordinate(registerForm.value.coordinate);
  if (parsed) fetchNearestLandmarks(parsed.lat, parsed.lng);
}

function parseMapsLink(url) {
  // @lat,lng,zoom (standard Google Maps URL)
  let m = url.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  // q=lat,lng
  m = url.match(/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  // ll=lat,lng
  m = url.match(/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  // /place/.../lat,lng (some share links)
  m = url.match(/\/(-?\d{1,3}\.\d{4,}),(-?\d{1,3}\.\d{4,})/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  return null;
}

function onMapsLinkInput() {
  clearTimeout(mapsLinkTimer);
  const url = mapsLink.value.trim();
  mapsLinkError.value = '';
  registerForm.value.coordinate = '';

  if (!url) return;

  // Parse full Google Maps URLs directly (no round-trip needed)
  const direct = parseMapsLink(url);
  if (direct) {
    registerForm.value.coordinate = `${direct.lat.toFixed(6)}, ${direct.lng.toFixed(6)}`;
    fetchNearestLandmarks(direct.lat, direct.lng);
    return;
  }

  // Looks like a URL — resolve shortened links via backend after a short debounce
  if (url.startsWith('http') && url.length > 15) {
    mapsLinkTimer = setTimeout(() => resolveShortenedUrl(url), 400);
    return;
  }

  mapsLinkError.value = 'Invalid Google Maps URL. Please paste a full or shortened Google Maps link.';
}

async function resolveShortenedUrl(url) {
  mapsLinkResolving.value = true;
  mapsLinkError.value = '';
  try {
    const res = await api.post('/v1/product-availability/resolve-maps-url', { url });
    const lat = parseFloat(res.data.lat);
    const lng = parseFloat(res.data.lng);
    registerForm.value.coordinate = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    fetchNearestLandmarks(lat, lng);
  } catch (e) {
    mapsLinkError.value = e.response?.data?.error ?? 'Could not extract coordinates from this link.';
  } finally {
    mapsLinkResolving.value = false;
  }
}

function refreshLandmarks() {
  const parsed = parseCoordinate(registerForm.value.coordinate);
  if (parsed) fetchNearestLandmarks(parsed.lat, parsed.lng);
}

async function fetchNearestLandmarks(lat, lng) {
  landmarkFetching.value = true;
  landmarkFetched.value = false;

  const r = 3000; // 3 km radius

  function overpassQ(tagLines) {
    const body = tagLines
      .map(t => `node${t}(around:${r},${lat},${lng});way${t}(around:${r},${lat},${lng});`)
      .join('');
    return `[out:json][timeout:15];(${body});out center 10;`;
  }

  const queries = [
    {
      category: 'Shopping Mall',
      q: overpassQ([
        `["shop"="mall"]["name"]`,
        `["shop"="shopping_centre"]["name"]`,
        `["building"="mall"]["name"]`,
        `["landuse"="retail"]["name"]`,
      ]),
    },
    {
      category: 'School',
      q: overpassQ([
        `["amenity"="school"]["name"]`,
      ]),
    },
    {
      category: 'Residential Area',
      q: overpassQ([
        `["place"~"neighbourhood|suburb|quarter"]["name"]`,
        `["place"="village"]["name"]`,
        `["landuse"="residential"]["name"]`,
      ]),
    },
    {
      category: 'Hospital / Clinic',
      q: overpassQ([
        `["amenity"="hospital"]["name"]`,
        `["amenity"="clinic"]["name"]`,
        `["healthcare"]["name"]`,
      ]),
    },
    {
      category: 'MRT / LRT Station',
      q: overpassQ([
        `["railway"="station"]["name"]`,
        `["railway"="halt"]["name"]`,
        `["station"~"subway|light_rail|monorail"]["name"]`,
      ]),
    },
    {
      category: 'Petrol Station',
      q: overpassQ([
        `["amenity"="fuel"]["name"]`,
      ]),
    },
    {
      category: 'University / College',
      q: overpassQ([
        `["amenity"="university"]["name"]`,
        `["amenity"="college"]["name"]`,
      ]),
    },
    {
      category: 'Police Station',
      q: overpassQ([
        `["amenity"="police"]["name"]`,
      ]),
    },
    {
      category: 'Government Office',
      q: overpassQ([
        `["amenity"="townhall"]["name"]`,
        `["office"="government"]["name"]`,
        `["office"="government_office"]["name"]`,
      ]),
    },
  ];

  function haversineKm(a, b, c, d) {
    const R = 6371;
    const dLat = (c - a) * Math.PI / 180;
    const dLng = (d - b) * Math.PI / 180;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(a * Math.PI / 180) * Math.cos(c * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }

  function pickNearest(elements) {
    return elements
      .filter(e => e.tags?.name)
      .map(e => {
        const elat = e.lat ?? e.center?.lat;
        const elng = e.lon ?? e.center?.lon;
        if (!elat || !elng) return null;
        return { name: e.tags.name, km: haversineKm(lat, lng, elat, elng) };
      })
      .filter(Boolean)
      .sort((a, b) => a.km - b.km)[0];
  }

  const settled = await Promise.allSettled(
    queries.map(({ q }) =>
      fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: q })
        .then(res => res.json())
    )
  );

  registerForm.value.nearest_landmarks = queries.map(({ category }, i) => {
    const elements = settled[i].status === 'fulfilled' ? (settled[i].value?.elements ?? []) : [];
    const best = pickNearest(elements);
    return {
      category,
      place: best ? `${best.km.toFixed(1)}km — ${best.name}` : 'Not Found',
    };
  });
  landmarkFetched.value = true;
  landmarkFetching.value = false;
}

function parseCoordinate(coord) {
  if (!coord) return null;
  const parts = coord.split(',').map((s) => parseFloat(s.trim()));
  if (parts.length !== 2 || parts.some(isNaN)) return null;
  return { lat: parts[0], lng: parts[1] };
}

function markerColor(productType) {
  if (productType === 'Billboard') return '#2563eb';
  if (productType === 'Lamp Post Bunting') return '#16a34a';
  return '#dc2626';
}

function markerLabel(productType) {
  if (productType === 'Billboard') return 'BB';
  if (productType === 'Lamp Post Bunting') return 'LB';
  return 'TB';
}

function toggleMapView() {
  showMapView.value = !showMapView.value;
  if (showMapView.value) {
    nextTick(initMap);
  } else if (leafletMap) {
    leafletMap.remove();
    leafletMap = null;
  }
}

function initMap() {
  if (leafletMap) {
    leafletMap.remove();
    leafletMap = null;
  }
  leafletMap = L.map(mapEl.value).setView([3.139, 101.6869], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(leafletMap);
  refreshMapMarkers();
}

function refreshMapMarkers() {
  if (!leafletMap) return;
  mapMarkers.forEach((m) => m.remove());
  mapMarkers = [];
  const bounds = [];

  rows.value.forEach((row) => {
    const coord = parseCoordinate(row.coordinate);
    if (!coord) return;

    const color = markerColor(row.product_type);
    const label = markerLabel(row.product_type);
    const icon = L.divIcon({
      html: `<div style="background:${color};width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:9px;font-weight:900;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.35)">${label}</div>`,
      className: '',
      iconSize: [30, 30],
      iconAnchor: [15, 15],
      popupAnchor: [0, -18],
    });

    const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(row.coordinate)}`;
    const svUrl = `https://maps.google.com/maps?q=&layer=c&cbll=${coord.lat},${coord.lng}`;

    const popupHtml = `
      <div style="font-size:12px;min-width:170px">
        <div style="font-weight:900;margin-bottom:3px">${row.product_type}</div>
        <div style="color:#374151;margin-bottom:5px;line-height:1.35">${row.site_name}</div>
        <div style="color:#6b7280;font-size:11px;margin-bottom:8px">${row.status} · ${row.type}</div>
        <a href="${mapsUrl}" target="_blank" style="display:block;background:#172033;color:white;text-align:center;padding:5px 8px;border-radius:5px;font-weight:800;font-size:11px;text-decoration:none;margin-bottom:4px">Open in Google Maps</a>
        <a href="${svUrl}" target="_blank" style="display:block;background:#2563eb;color:white;text-align:center;padding:5px 8px;border-radius:5px;font-weight:800;font-size:11px;text-decoration:none">Street View</a>
      </div>`;

    const marker = L.marker([coord.lat, coord.lng], { icon }).addTo(leafletMap);
    marker.bindPopup(popupHtml);
    mapMarkers.push(marker);
    bounds.push([coord.lat, coord.lng]);
  });

  if (bounds.length > 0) {
    leafletMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
  }
}

function defaultLandmarks() {
  return [
    { category: 'Shopping Mall',      place: 'Not Found' },
    { category: 'School',             place: 'Not Found' },
    { category: 'Residential Area',   place: 'Not Found' },
    { category: 'Hospital / Clinic',  place: 'Not Found' },
    { category: 'MRT / LRT Station',  place: 'Not Found' },
    { category: 'Petrol Station',     place: 'Not Found' },
    { category: 'University / College', place: 'Not Found' },
    { category: 'Police Station',     place: 'Not Found' },
    { category: 'Government Office',  place: 'Not Found' },
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

function openRegisterModal() {
  registerForm.value = {
    site_name: '',
    product_type: 'Temp Board',
    status: 'Existing',
    type: 'A1',
    state_city: '',
    coordinate: '',
    nearest_landmarks: defaultLandmarks(),
  };
  mapsLink.value = '';
  mapsLinkError.value = '';
  mapsLinkResolving.value = false;
  registerError.value = '';
  placeSearch.value = '';
  placeResults.value = [];
  landmarkFetching.value = false;
  landmarkFetched.value = false;
  showRegisterModal.value = true;
}

function closeRegisterModal() {
  showRegisterModal.value = false;
  registerError.value = '';
}

async function submitRegisterProduct() {
  if (!registerForm.value.site_name.trim()) return;
  registerSaving.value = true;
  registerError.value = '';
  try {
    const res = await api.post('/v1/product-availability/products', {
      site_name: registerForm.value.site_name.trim(),
      product_type: registerForm.value.product_type,
      status: registerForm.value.status,
      type: registerForm.value.type,
      state_city: registerForm.value.state_city.trim() || null,
      coordinate: registerForm.value.coordinate.trim() || null,
      nearest_landmarks: registerForm.value.nearest_landmarks.filter(lm => lm.category || lm.place),
    });
    upsertRow(res.data.data);
    closeRegisterModal();
  } catch (e) {
    const errors = e.response?.data?.errors;
    registerError.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to register product.';
  } finally {
    registerSaving.value = false;
  }
}

onUnmounted(() => {
  if (leafletMap) {
    leafletMap.remove();
    leafletMap = null;
  }
});

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
.btn-register-product {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px;
  font-size: 13px; font-weight: 850; cursor: pointer; background: #172033; color: white;
  white-space: nowrap;
}
.btn-register-product:hover { background: #0f172a; }
.register-modal {
  width: min(720px, 96vw); background: #ffffff; color: #111827;
  border-radius: 10px; overflow: hidden;
  box-shadow: 0 24px 70px rgba(15,23,42,0.36);
}
.register-modal-header {
  background: #172033; color: #ffffff; padding: 16px 20px;
  display: flex; align-items: center; justify-content: space-between;
}
.register-modal-header h2 { margin: 0; font-size: 15px; font-weight: 900; }
.register-modal-body { padding: 20px; }
.register-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.register-full { grid-column: 1 / -1; }
.register-site-row { display: flex; gap: 8px; align-items: center; }
.register-site-row input { flex: 1; min-width: 0; }
.btn-open-gmaps {
  flex-shrink: 0; height: 36px; padding: 0 12px; border-radius: 7px;
  background: #172033; color: #fff; font-size: 11px; font-weight: 800;
  text-decoration: none; display: flex; align-items: center; white-space: nowrap;
}
.btn-open-gmaps:hover { background: #2563eb; }
.field-hint { margin-left: 6px; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: none; letter-spacing: 0; }
.coord-preview { margin-top: 5px; font-size: 11px; font-weight: 700; color: #2563eb; }
.register-modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
.btn-save-register {
  height: 36px; border: none; border-radius: 7px; padding: 0 18px;
  font-size: 13px; font-weight: 850; cursor: pointer; background: #2563eb; color: white;
}
.btn-save-register:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-cancel-register {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px;
  font-size: 13px; font-weight: 850; cursor: pointer; background: #eef2f7; color: #475569;
}
.btn-map-view {
  height: 36px; border: 1.5px solid #172033; border-radius: 7px; padding: 0 14px;
  font-size: 13px; font-weight: 850; cursor: pointer; background: white; color: #172033;
  white-space: nowrap;
}
.btn-map-view:hover { background: #f8fafc; }
.btn-map-active { background: #172033; color: white; }
.btn-map-active:hover { background: #0f172a; }
.map-section {
  background: white; border: 1px solid #dbe3ee; border-radius: 8px;
  overflow: hidden; margin-bottom: 14px;
}
.leaflet-map { height: 480px; width: 100%; }
.map-legend {
  display: flex; gap: 18px; padding: 10px 16px; background: #f8fafc;
  border-top: 1px solid #dbe3ee; flex-wrap: wrap;
}
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 750; color: #374151; }
.legend-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.detail-map-links { display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
.map-link, .street-view-link {
  display: inline-flex; align-items: center; justify-content: center; min-height: 36px;
  padding: 0 14px; border-radius: 7px; font-size: 12px; font-weight: 850; text-decoration: none;
}
.map-link { background: #172033; color: #ffffff; }
.street-view-link { background: #2563eb; color: #ffffff; }
.coord-helper {
  margin-left: 6px; color: #2563eb; font-size: 10px; font-weight: 750;
  text-decoration: underline; text-transform: none; letter-spacing: 0;
}
.place-search-wrap { position: relative; }
.place-search-wrap input { width: 100%; box-sizing: border-box; }
.place-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 200;
  background: white; border: 1.5px solid #dbe3ee; border-radius: 8px;
  box-shadow: 0 12px 28px rgba(15,23,42,0.18); overflow: hidden; max-height: 220px; overflow-y: auto;
}
.place-result-item {
  width: 100%; min-height: 38px; border: none; border-bottom: 1px solid #eef2f7;
  background: white; color: #172033; text-align: left; padding: 8px 12px;
  font-size: 12px; font-weight: 600; cursor: pointer; display: block; line-height: 1.4;
}
.place-result-item:last-child { border-bottom: none; }
.place-result-item:hover { background: #eff6ff; color: #1d4ed8; }
.place-loading { color: #64748b; font-weight: 700; cursor: default; pointer-events: none; }
.landmark-fetch-status { margin-left: 8px; font-size: 10px; font-weight: 700; text-transform: none; letter-spacing: 0; color: #94a3b8; }
.landmark-fetch-ok { color: #16a34a; }
.landmark-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.btn-refresh-landmarks {
  flex-shrink: 0; height: 28px; padding: 0 10px; border: 1.5px solid #dbe3ee; border-radius: 6px;
  background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; cursor: pointer;
}
.btn-refresh-landmarks:hover { background: #eff6ff; border-color: #2563eb; color: #2563eb; }
.maps-link-row { position: relative; display: flex; align-items: center; gap: 8px; }
.maps-link-row input { flex: 1; min-width: 0; }
.maps-link-resolving { font-size: 11px; font-weight: 700; color: #2563eb; white-space: nowrap; display: flex; align-items: center; gap: 5px; }
.maps-link-error { margin-top: 5px; font-size: 11px; font-weight: 700; color: #dc2626; }
.input-error-border { border-color: #dc2626 !important; }
@keyframes lm-spin { to { transform: rotate(360deg); } }
.lm-spinner {
  display: inline-block; width: 11px; height: 11px; border: 2px solid #dbe3ee;
  border-top-color: #2563eb; border-radius: 50%; animation: lm-spin 0.7s linear infinite;
  vertical-align: middle;
}
.register-landmark-table { width: 100%; border-collapse: collapse; margin-top: 0; table-layout: fixed; }
.register-landmark-table th { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; color: #fff; background: #172033; padding: 6px 10px; text-align: left; }
.register-landmark-table th:first-child { width: 36%; }
.register-landmark-table th:last-child { width: 64%; }
.register-landmark-table tbody tr { border-bottom: 1px solid #eef2f7; }
.register-landmark-table tbody tr:last-child { border-bottom: none; }
.lm-cat-cell { padding: 7px 10px; font-size: 12px; font-weight: 700; color: #374151; white-space: nowrap; }
.lm-place-cell { padding: 7px 10px; font-size: 12px; font-weight: 600; color: #111827; }
.lm-not-found { color: #94a3b8; font-style: italic; }
@keyframes lm-skeleton-shine { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
.lm-skeleton {
  display: inline-block; width: 70%; height: 12px; border-radius: 4px;
  background: #dbe3ee; animation: lm-skeleton-shine 1.2s ease-in-out infinite;
}
@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { align-items: stretch; flex-direction: column; }
  .field, .field.company-field, .field.place-field, .field.small { width: 100%; min-width: 0; }
  .btn-add, .btn-dark, .btn-clear, .btn-proposal, .btn-register-product { width: 100%; }
  .product-detail-modal { grid-template-columns: 1fr; }
  .detail-side { display: none; }
  .detail-content { padding: 22px 16px 24px; }
  .detail-header { width: calc(100% - 42px); border-radius: 14px; }
  .detail-grid { grid-template-columns: 1fr; }
  .register-form-grid { grid-template-columns: 1fr; }
  .leaflet-map { height: 320px; }
}
</style>
