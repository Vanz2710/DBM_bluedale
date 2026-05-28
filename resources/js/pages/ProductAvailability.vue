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

    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="action-bar">
      <div class="action-bar-filters">
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
      <div class="action-bar-actions">
        <button class="btn-add" @click="openEntryModal()">+ Add Booking</button>
        <button class="btn-proposal" :disabled="selectedProductIds.length === 0" @click="openProposalWizard">
          Generate Proposal ({{ selectedProductIds.length }})
        </button>
      </div>
    </div>

    <div class="table-wrap">
      <div class="table-title">
        <span>CRM TB Availability</span>
        <span>{{ rows.length }} product(s)</span>
      </div>

      <div v-if="loading" class="loading-msg">Loading...</div>
      <table v-else class="gantt-table">
        <thead>
          <tr>
            <th class="select-col" @click.stop>
              <input type="checkbox" :checked="allRowsSelected" :disabled="rows.length === 0" @change="toggleAllRows">
            </th>
            <th class="place-col">Place</th>
            <th v-for="month in months" :key="month.value" class="month-th">{{ month.short }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td :colspan="months.length + 2" class="empty-state">No product bookings yet.</td>
          </tr>
          <tr v-for="(row, index) in rows" :key="row.id" class="product-row">
            <td class="select-col" @click.stop>
              <input
                type="checkbox"
                :checked="selectedProductIds.includes(row.id)"
                @change="toggleProductSelection(row.id)"
              >
            </td>
            <td class="place-col" @click.stop="openProductDetail(row)">
              <div class="place-cell">
                <div class="place-cell-main">
                  <span class="place-cell-no">{{ index + 1 }}</span>
                  <span class="place-cell-name" :title="row.site_name">{{ row.site_name }}</span>
                </div>
                <div class="place-cell-meta">
                  <span class="badge badge-product">{{ row.product_type }}</span>
                  <span class="badge" :class="`badge-status-${row.status.toLowerCase().replace(' ', '-')}`">{{ row.status }}</span>
                  <span class="badge badge-type">{{ row.type }}</span>
                </div>
              </div>
            </td>
            <td
              v-for="month in months"
              :key="month.value"
              class="month-cell"
              :class="{ booked: bookingFor(row, month.value) }"
              @click.stop="openCellMenu(row, month.value)"
            >
              <template v-if="bookingFor(row, month.value)">
                <div class="booking-bar" :title="`${bookingFor(row, month.value).company_name} · ${formatDate(bookingFor(row, month.value).start_date) || '—'} → ${formatDate(bookingFor(row, month.value).end_date) || '—'}`">
                  {{ bookingFor(row, month.value).company_name }}
                </div>
              </template>
              <span v-else class="avail-tick" aria-hidden="true"></span>
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
                <thead v-if="editingLandmarks">
                  <tr>
                    <th class="landmark-col-cat">Category</th>
                    <th class="landmark-col-dist">Distance</th>
                    <th>Place</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(landmark, index) in activeLandmarkRows" :key="`${landmark.category}-${index}`">
                    <th class="landmark-col-cat">
                      <input v-if="editingLandmarks" v-model="landmarkForm[index].category" aria-label="Landmark category">
                      <span v-else>{{ landmark.category }}</span>
                    </th>
                    <td v-if="editingLandmarks" class="landmark-col-dist">
                      <input v-model="landmarkForm[index].distance" placeholder="e.g. 5.5km" aria-label="Distance">
                    </td>
                    <td>
                      <input v-if="editingLandmarks" v-model="landmarkForm[index].place" aria-label="Nearby place">
                      <span v-else>
                        <template v-if="landmark.distance">{{ landmark.distance }} to </template>{{ landmark.place }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Photo upload section -->
          <div class="photo-grid">
            <div class="photo-panel">
              <div class="photo-actions">
                <span>Site Photo</span>
                <label class="btn-upload">
                  {{ uploadingPhoto.site_photo ? 'Uploading…' : 'Upload' }}
                  <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingPhoto.site_photo" @change="onPhotoSelected($event, 'site_photo')">
                </label>
                <button v-if="selectedProduct.site_photo" type="button" class="btn-remove-photo" @click="removePhoto('site_photo')">Remove</button>
              </div>
              <div class="photo-preview">
                <img v-if="selectedProduct.site_photo_url" :src="selectedProduct.site_photo_url" alt="Site photo">
                <span v-else class="photo-placeholder">No site photo uploaded</span>
              </div>
            </div>
            <div class="photo-panel">
              <div class="photo-actions">
                <span>Map Photo</span>
                <label class="btn-upload">
                  {{ uploadingPhoto.site_map_photo ? 'Uploading…' : 'Upload' }}
                  <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingPhoto.site_map_photo" @change="onPhotoSelected($event, 'site_map_photo')">
                </label>
                <button v-if="selectedProduct.site_map_photo" type="button" class="btn-remove-photo" @click="removePhoto('site_map_photo')">Remove</button>
              </div>
              <div class="photo-preview">
                <img v-if="selectedProduct.site_map_photo_url" :src="selectedProduct.site_map_photo_url" alt="Map photo">
                <span v-else class="photo-placeholder">No map photo uploaded</span>
              </div>
            </div>
          </div>

          <a class="map-link" :href="productMapUrl" target="_blank" rel="noopener">Open Location in Google Maps</a>
        </div>
      </section>
    </div>

    <!-- Add / Edit Booking Modal -->
    <div v-if="entryModalOpen" class="modal-backdrop" @click.self="closeEntryModal">
      <section class="entry-modal" role="dialog" aria-modal="true">
        <header class="entry-modal-head">
          <div>
            <h2>{{ form.site_name && form.id ? 'Edit Booking' : 'Add Booking' }}</h2>
            <p>Reserve a month for a company at one of your advertising sites.</p>
          </div>
          <button type="button" class="detail-close" @click="closeEntryModal">&times;</button>
        </header>

        <div class="entry-modal-body">
          <div class="field full">
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

          <div class="field full">
            <label>Place</label>
            <select v-model="form.site_name" @change="applyPlaceDefaults">
              <option value="">Select place</option>
              <option v-for="place in placeOptions" :key="place.id" :value="place.site_name">
                {{ place.site_name }}
              </option>
            </select>
          </div>

          <div class="entry-modal-grid">
            <div class="field">
              <label>Product</label>
              <select v-model="form.product_type">
                <option v-for="product in productOptions" :key="product" :value="product">{{ product }}</option>
              </select>
            </div>
            <div class="field">
              <label>Month</label>
              <select v-model.number="form.month">
                <option v-for="month in months" :key="month.value" :value="month.value">{{ month.short }}</option>
              </select>
            </div>
            <div class="field">
              <label>Start Rent</label>
              <input v-model="form.start_date" type="date">
            </div>
            <div class="field">
              <label>End Rent</label>
              <input v-model="form.end_date" type="date">
            </div>
          </div>
        </div>

        <footer class="entry-modal-foot">
          <button type="button" class="btn-clear" @click="closeEntryModal">Cancel</button>
          <button type="button" class="btn-add" :disabled="saving || !canAdd" @click="saveFromModal">
            {{ saving ? 'Saving…' : 'Save Booking' }}
          </button>
        </footer>
      </section>
    </div>

    <!-- Cell Menu Modal (view / edit / delete booking, quick-add if empty) -->
    <div v-if="cellMenu.open" class="modal-backdrop" @click.self="closeCellMenu">
      <section class="cell-menu-modal" role="dialog" aria-modal="true">
        <header class="cell-menu-head">
          <div>
            <span class="cell-menu-eyebrow">{{ monthLabel(cellMenu.month) }} {{ year }}</span>
            <h2>{{ cellMenu.row?.site_name }}</h2>
          </div>
          <button type="button" class="detail-close" @click="closeCellMenu">&times;</button>
        </header>

        <div v-if="cellMenu.booking" class="cell-menu-body">
          <dl class="cell-menu-dl">
            <dt>Company</dt><dd>{{ cellMenu.booking.company_name }}</dd>
            <dt>Start Date</dt>
            <dd>
              <input type="date" :value="cellMenu.booking.start_date?.split('T')[0]" @change="onCellDateChange($event, 'start_date')">
            </dd>
            <dt>End Date</dt>
            <dd>
              <input type="date" :value="cellMenu.booking.end_date?.split('T')[0]" @change="onCellDateChange($event, 'end_date')">
            </dd>
          </dl>
        </div>
        <div v-else class="cell-menu-body cell-menu-empty">
          <p>This month is currently <strong>available</strong> at this site.</p>
        </div>

        <footer class="cell-menu-foot">
          <button v-if="cellMenu.booking" type="button" class="btn-danger" @click="deleteFromCellMenu">Delete Booking</button>
          <button type="button" class="btn-add" @click="addFromCellMenu">
            {{ cellMenu.booking ? 'Add Another Month' : '+ Book This Month' }}
          </button>
        </footer>
      </section>
    </div>

    <!-- Proposal Wizard Modal -->
    <div v-if="proposalWizardOpen" class="modal-backdrop" @click.self="closeProposalWizard">
      <section class="wizard-modal" role="dialog" aria-modal="true">
        <header class="wizard-header">
          <div>
            <h2>Generate Proposal</h2>
            <p>{{ selectedProductIds.length }} site(s) selected · Step {{ wizardStepIndex + 1 }} of {{ wizardSteps.length }}</p>
          </div>
          <button type="button" class="detail-close" @click="closeProposalWizard">&times;</button>
        </header>

        <nav class="wizard-tabs">
          <button
            v-for="(step, i) in wizardSteps"
            :key="step.id"
            type="button"
            :class="{ active: wizardStep === step.id, done: i < wizardStepIndex }"
            @click="wizardStep = step.id"
          >
            <span class="wizard-tab-num">{{ i + 1 }}</span>
            <span class="wizard-tab-label">{{ step.label }}</span>
          </button>
        </nav>

        <!-- Step 1: Client Info -->
        <div v-if="wizardStep === 'info'" class="wizard-body">
          <div class="wizard-grid">
            <div class="field">
              <label>Client Name</label>
              <input v-model="proposalForm.client_name" placeholder="e.g. ACC Evesuite Medical Centre">
            </div>
            <div class="field">
              <label>Attention</label>
              <input v-model="proposalForm.attention" placeholder="e.g. Amira">
            </div>
            <div class="field">
              <label>Attention Phone</label>
              <input v-model="proposalForm.attention_phone" placeholder="e.g. +60 17-842 7710">
            </div>
            <div class="field">
              <label>Reference</label>
              <input v-model="proposalForm.reference" :placeholder="defaultReference">
            </div>
            <div class="field small">
              <label>Duration (months)</label>
              <input v-model.number="proposalForm.duration" type="number" min="1" max="36">
            </div>
            <div class="field">
              <label>Duration Label Override</label>
              <input v-model="proposalForm.duration_label" :placeholder="`${proposalForm.duration} MONTH${proposalForm.duration > 1 ? 'S' : ''}`">
            </div>
            <div class="field">
              <label>Normal Price (banner, RM)</label>
              <input v-model.number="proposalForm.normal_price" type="number" min="0" placeholder="e.g. 180">
            </div>
            <div class="field">
              <label>Price per Unit (RM)</label>
              <input v-model.number="proposalForm.price_per_unit" type="number" min="0" placeholder="e.g. 120">
            </div>
            <div class="field">
              <label>Quantity Size</label>
              <input v-model="proposalForm.quantity_size" placeholder="e.g. 7 x 3">
            </div>
            <div class="field">
              <label>SST Rate</label>
              <input v-model.number="proposalForm.sst_rate" type="number" min="0" max="1" step="0.01" placeholder="0.08">
            </div>
            <div class="field">
              <label>Promo Until</label>
              <input v-model="proposalForm.promo_until" placeholder="e.g. 15/2/2026">
            </div>
            <div class="field full">
              <label>RE: Line Override (optional)</label>
              <input v-model="proposalForm.re_line" placeholder="Leave blank for auto-generated">
            </div>
            <div class="field full inline-checkbox">
              <label>
                <input v-model="proposalForm.include_site_sheets" type="checkbox">
                Include site data sheet (one extra page per selected product)
              </label>
            </div>
          </div>
        </div>

        <!-- Step 2: Per-product photo check -->
        <div v-else-if="wizardStep === 'sheets'" class="wizard-body">
          <p class="wizard-note">Confirm each site has a photo and map. Sheets without photos will show a placeholder.</p>
          <div class="wizard-sites">
            <article v-for="product in selectedProducts" :key="product.id" class="wizard-site">
              <header>
                <div>
                  <strong>{{ product.site_name }}</strong>
                  <p>{{ product.product_type }} · {{ product.site_code || '—' }} · {{ product.coordinate || 'No coordinate' }}</p>
                </div>
                <button type="button" class="btn-mini" @click="openProductDetail(product)">Edit Details</button>
              </header>
              <div class="wizard-photo-row">
                <div class="wizard-photo-cell">
                  <div class="wizard-photo-label">Site Photo</div>
                  <div class="wizard-photo-frame">
                    <img v-if="product.site_photo_url" :src="product.site_photo_url" alt="Site photo">
                    <span v-else class="photo-placeholder">No photo</span>
                  </div>
                  <label class="btn-mini">
                    {{ uploadingPhotoFor[product.id]?.site_photo ? 'Uploading…' : (product.site_photo ? 'Replace' : 'Upload') }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingPhotoFor[product.id]?.site_photo" @change="onPhotoSelectedFor($event, product, 'site_photo')">
                  </label>
                </div>
                <div class="wizard-photo-cell">
                  <div class="wizard-photo-label">Map Photo</div>
                  <div class="wizard-photo-frame">
                    <img v-if="product.site_map_photo_url" :src="product.site_map_photo_url" alt="Map photo">
                    <span v-else class="photo-placeholder">No map</span>
                  </div>
                  <label class="btn-mini">
                    {{ uploadingPhotoFor[product.id]?.site_map_photo ? 'Uploading…' : (product.site_map_photo ? 'Replace' : 'Upload') }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingPhotoFor[product.id]?.site_map_photo" @change="onPhotoSelectedFor($event, product, 'site_map_photo')">
                  </label>
                </div>
              </div>
            </article>
          </div>
        </div>

        <!-- Step 3: Review -->
        <div v-else-if="wizardStep === 'review'" class="wizard-body">
          <div class="wizard-review">
            <div class="review-block">
              <h3>Client</h3>
              <dl>
                <dt>Name</dt><dd>{{ proposalForm.client_name || '—' }}</dd>
                <dt>Attention</dt><dd>{{ proposalForm.attention || '—' }}{{ proposalForm.attention_phone ? ` (${proposalForm.attention_phone})` : '' }}</dd>
                <dt>Reference</dt><dd>{{ proposalForm.reference || defaultReference }}</dd>
              </dl>
            </div>
            <div class="review-block">
              <h3>Package</h3>
              <dl>
                <dt>Duration</dt><dd>{{ proposalForm.duration_label || `${proposalForm.duration} MONTH${proposalForm.duration > 1 ? 'S' : ''}` }}</dd>
                <dt>Normal Price</dt><dd>{{ proposalForm.normal_price ? `RM ${proposalForm.normal_price}` : '—' }}</dd>
                <dt>Price / Unit</dt><dd>{{ proposalForm.price_per_unit ? `RM ${proposalForm.price_per_unit}` : '—' }}</dd>
                <dt>Site Sheets</dt><dd>{{ proposalForm.include_site_sheets ? 'Included' : 'Excluded' }}</dd>
              </dl>
            </div>
            <div class="review-block full">
              <h3>Sites ({{ selectedProducts.length }})</h3>
              <ul class="review-sites">
                <li v-for="p in selectedProducts" :key="p.id">
                  <strong>{{ p.site_name }}</strong>
                  <span :class="['photo-status', p.site_photo ? 'ok' : 'missing']">{{ p.site_photo ? '✓ photo' : '✗ no photo' }}</span>
                  <span :class="['photo-status', p.site_map_photo ? 'ok' : 'missing']">{{ p.site_map_photo ? '✓ map' : '✗ no map' }}</span>
                </li>
              </ul>
            </div>
          </div>
          <div v-if="error" class="error-msg">{{ error }}</div>
        </div>

        <footer class="wizard-footer">
          <button type="button" class="btn-clear" @click="closeProposalWizard">Cancel</button>
          <div class="wizard-footer-right">
            <button v-if="wizardStepIndex > 0" type="button" class="btn-dark" @click="wizardStep = wizardSteps[wizardStepIndex - 1].id">Back</button>
            <button v-if="wizardStepIndex < wizardSteps.length - 1" type="button" class="btn-add" @click="wizardStep = wizardSteps[wizardStepIndex + 1].id">Next</button>
            <button v-else type="button" class="btn-proposal" :disabled="generatingProposal" @click="generateProposal">
              {{ generatingProposal ? 'Generating…' : 'Generate PDF' }}
            </button>
          </div>
        </footer>
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
  attention_phone: '',
  reference: '',
  duration: 1,
  duration_label: '',
  normal_price: null,
  price_per_unit: null,
  quantity_size: '',
  sst_rate: 0.08,
  promo_until: '',
  re_line: '',
  include_site_sheets: true,
});
const proposalWizardOpen = ref(false);
const wizardStep = ref('info');
const wizardSteps = [
  { id: 'info',   label: 'Client Info' },
  { id: 'sheets', label: 'Site Sheets' },
  { id: 'review', label: 'Review' },
];
const uploadingPhoto = ref({ site_photo: false, site_map_photo: false });
const uploadingPhotoFor = ref({});
const entryModalOpen = ref(false);
const cellMenu = ref({ open: false, row: null, month: null, booking: null });
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
const wizardStepIndex = computed(() => wizardSteps.findIndex((s) => s.id === wizardStep.value));
const selectedProducts = computed(() => rows.value.filter((row) => selectedProductIds.value.includes(row.id)));
const defaultReference = computed(() => {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yy = String(d.getFullYear()).slice(-2);
  const time = `${String(d.getHours()).padStart(2, '0')}${String(d.getMinutes()).padStart(2, '0')}${String(d.getSeconds()).padStart(2, '0')}`;
  return `AEMC/PROPOSAL/${mm}-${yy}/${time}`;
});

function defaultLandmarks() {
  return [
    { category: 'Exhibition Center',     distance: '', place: 'Not set' },
    { category: 'Shopping Mall',          distance: '', place: 'Not set' },
    { category: 'International School',   distance: '', place: 'Not set' },
    { category: 'Hosp/ Medical Centre',   distance: '', place: 'Not set' },
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
    distance: landmark.distance || '',
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
      category: (landmark.category || '').trim(),
      distance: (landmark.distance || '').trim(),
      place: (landmark.place || '').trim(),
    }))
    .filter((landmark) => landmark.category || landmark.place || landmark.distance)
    .map((landmark) => ({
      category: landmark.category || 'Landmark',
      distance: landmark.distance,
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
    const f = proposalForm.value;
    const res = await api.post('/v1/product-availability/proposal', {
      product_ids:         selectedProductIds.value,
      client_name:         (f.client_name || '').trim() || null,
      attention:           (f.attention || '').trim() || null,
      attention_phone:     (f.attention_phone || '').trim() || null,
      reference:           (f.reference || '').trim() || null,
      duration:            f.duration || 1,
      duration_label:      (f.duration_label || '').trim() || null,
      normal_price:        f.normal_price || null,
      price_per_unit:      f.price_per_unit || null,
      quantity_size:       (f.quantity_size || '').trim() || null,
      sst_rate:            f.sst_rate ?? null,
      promo_until:         (f.promo_until || '').trim() || null,
      re_line:             (f.re_line || '').trim() || null,
      include_site_sheets: !!f.include_site_sheets,
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
    closeProposalWizard();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to generate proposal PDF.';
  } finally {
    generatingProposal.value = false;
  }
}

function openProposalWizard() {
  if (selectedProductIds.value.length === 0) return;
  if (!proposalForm.value.reference) proposalForm.value.reference = defaultReference.value;
  wizardStep.value = 'info';
  proposalWizardOpen.value = true;
}

function closeProposalWizard() {
  proposalWizardOpen.value = false;
}

async function onPhotoSelected(event, kind) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !selectedProduct.value) return;
  await uploadPhoto(selectedProduct.value, kind, file);
}

async function onPhotoSelectedFor(event, product, kind) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  await uploadPhoto(product, kind, file, true);
}

async function uploadPhoto(product, kind, file, fromWizard = false) {
  error.value = '';
  if (fromWizard) {
    if (!uploadingPhotoFor.value[product.id]) uploadingPhotoFor.value[product.id] = {};
    uploadingPhotoFor.value[product.id][kind] = true;
  } else {
    uploadingPhoto.value[kind] = true;
  }

  try {
    const fd = new FormData();
    fd.append('kind', kind);
    fd.append('photo', file);
    const res = await api.post(`/v1/product-availability/products/${product.id}/photo`, fd);
    const { path, url } = res.data.data;
    applyPhotoUpdate(product.id, kind, path, url);
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to upload photo.';
  } finally {
    if (fromWizard) {
      uploadingPhotoFor.value[product.id][kind] = false;
    } else {
      uploadingPhoto.value[kind] = false;
    }
  }
}

async function removePhoto(kind) {
  if (!selectedProduct.value) return;
  if (!window.confirm('Remove this photo?')) return;
  error.value = '';
  try {
    await api.delete(`/v1/product-availability/products/${selectedProduct.value.id}/photo`, {
      data: { kind },
    });
    applyPhotoUpdate(selectedProduct.value.id, kind, null, null);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove photo.';
  }
}

function applyPhotoUpdate(productId, kind, path, url) {
  const urlKey = `${kind}_url`;
  const row = rows.value.find((r) => r.id === productId);
  if (row) {
    row[kind] = path;
    row[urlKey] = url;
  }
  if (selectedProduct.value && selectedProduct.value.id === productId) {
    selectedProduct.value[kind] = path;
    selectedProduct.value[urlKey] = url;
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

function openEntryModal(presets = {}) {
  // Reset to a fresh form, then apply presets (used by quick-add from cell)
  form.value = {
    company_name: '',
    contact_id: null,
    site_name: '',
    status: 'Existing',
    type: 'A1',
    product_type: 'Temp Board',
    month: new Date().getMonth() + 1,
    start_date: '',
    end_date: '',
    ...presets,
  };
  selectedContactId.value = presets.contact_id ?? null;
  companyResults.value = [];
  error.value = '';
  entryModalOpen.value = true;
}

function closeEntryModal() {
  entryModalOpen.value = false;
}

async function saveFromModal() {
  await addBooking();
  if (!error.value) closeEntryModal();
}

function openCellMenu(row, month) {
  const booking = bookingFor(row, month);
  cellMenu.value = { open: true, row, month, booking: booking ?? null };
}

function closeCellMenu() {
  cellMenu.value = { open: false, row: null, month: null, booking: null };
}

function addFromCellMenu() {
  const { row, month } = cellMenu.value;
  const presets = {
    site_name: row.site_name,
    status: row.status,
    type: row.type,
    product_type: row.product_type,
    month,
  };
  closeCellMenu();
  openEntryModal(presets);
}

async function deleteFromCellMenu() {
  const { row, booking } = cellMenu.value;
  if (!booking) return;
  if (!window.confirm(`Remove booking for ${booking.company_name}?`)) return;
  await deleteBooking(row, booking);
  closeCellMenu();
}

async function onCellDateChange(event, field) {
  const value = event.target.value;
  const { row, month } = cellMenu.value;
  await updateBookingDate(row, month, field, value);
  // Refresh local cellMenu.booking to latest
  cellMenu.value.booking = bookingFor(row, month) ?? null;
}

function monthLabel(value) {
  const m = months.find((mo) => mo.value === value);
  return m ? m.short : '';
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
/* Compact action bar replaces old entry-panel + toolbar + proposal-panel */
.action-bar {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 12px 14px;
  margin-bottom: 14px; display: flex; justify-content: space-between; align-items: flex-end;
  gap: 16px; flex-wrap: wrap;
}
.action-bar-filters {
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; flex: 1; min-width: 280px;
}
.action-bar-actions {
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
}
.field { display: flex; flex-direction: column; gap: 5px; min-width: 150px; }
.field.company-field { flex: 0 1 430px; min-width: 320px; }
.field.place-field { flex: 0 1 480px; min-width: 340px; }
.field.small { min-width: 112px; }
.field.date-field { min-width: 138px; }
.field.full { width: 100%; }
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
.table-wrap {
  background: white; border-radius: 8px; border: 1px solid #e5e7eb; overflow: auto;
  position: relative;
}
.table-title {
  background: #f8fafc; color: #0f172a; border-bottom: 1px solid #e5e7eb; padding: 10px 14px;
  display: flex; justify-content: space-between; align-items: center;
  font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
}
.loading-msg { text-align: center; padding: 44px; color: #64748b; }

/* Compact gantt table */
.gantt-table {
  width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px;
}
.gantt-table th, .gantt-table td { border: none; border-right: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7; }
.gantt-table thead th {
  background: #1e293b; color: #f1f5f9; padding: 8px 6px; font-size: 11px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.4px; text-align: center; white-space: nowrap;
  position: sticky; top: 0; z-index: 3;
}
.gantt-table tbody td {
  height: 56px; padding: 0; vertical-align: middle; background: #ffffff;
}
.product-row:hover td { background: #f8fafc; }
.product-row:hover .month-cell.booked .booking-bar { filter: brightness(0.96); }

/* Sticky checkbox + place columns */
.select-col {
  width: 38px; min-width: 38px; text-align: center;
  position: sticky; left: 0; z-index: 2; background: #ffffff;
}
.gantt-table thead .select-col { background: #1e293b; }
.select-col input {
  appearance: none; -webkit-appearance: none;
  width: 16px; height: 16px; padding: 0; cursor: pointer;
  border: 1.5px solid #1f2937; border-radius: 3px; background: #ffffff;
  display: inline-grid; place-content: center; vertical-align: middle;
}
.select-col input::before {
  content: ""; width: 8px; height: 8px; transform: scale(0); background: #1d4ed8; border-radius: 1px;
}
.select-col input:checked::before { transform: scale(1); }
.select-col input:focus { outline: 2px solid rgba(37,99,235,0.32); outline-offset: 2px; }
.select-col input:disabled { border-color: #94a3b8; background: #e2e8f0; cursor: not-allowed; }

.place-col {
  width: 280px; min-width: 280px; max-width: 280px;
  position: sticky; left: 38px; z-index: 2; background: #ffffff;
  text-align: left; cursor: pointer;
  border-right: 1.5px solid #cbd5e1 !important;
  box-shadow: 6px 0 6px -6px rgba(15,23,42,0.18);
}
.gantt-table thead .place-col { background: #1e293b; text-align: left; padding-left: 14px; }
.place-cell { padding: 8px 12px; display: flex; flex-direction: column; gap: 5px; }
.place-cell-main { display: flex; align-items: flex-start; gap: 8px; }
.place-cell-no {
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  min-width: 22px; height: 18px; padding: 0 5px; border-radius: 999px;
  background: #eef2f7; color: #475569; font-size: 10px; font-weight: 800;
}
.place-cell-name {
  font-size: 12.5px; font-weight: 700; color: #0f172a; line-height: 1.3;
  overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
  -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.place-cell-meta { display: flex; flex-wrap: wrap; gap: 4px; }
.badge {
  display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 999px;
  font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px;
  background: #eef2f7; color: #475569; border: 1px solid transparent;
}
.badge-product { background: #ede9fe; color: #5b21b6; }
.badge-type { background: #fef3c7; color: #92400e; }
.badge-status-existing { background: #dcfce7; color: #166534; }
.badge-status-raw-new { background: #dbeafe; color: #1e40af; }

/* Month header + cells */
.gantt-table .month-th { min-width: 78px; width: 78px; }
.month-cell {
  min-width: 78px; width: 78px; text-align: center; cursor: pointer;
  position: relative;
}
.month-cell.booked { background: #f0f9ff; }
.booking-bar {
  height: 36px; margin: 8px 6px; padding: 0 6px;
  background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%);
  color: #ffffff; border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  font-size: 10.5px; font-weight: 700; line-height: 1.15;
  overflow: hidden; text-overflow: ellipsis;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  word-break: break-word;
  box-shadow: 0 1px 2px rgba(2,132,199,0.25);
  transition: filter 0.12s;
}
.month-cell:hover { background: #f1f5f9; }
.month-cell.booked:hover { background: #e0f2fe; }
.month-cell:hover .booking-bar { filter: brightness(1.05); }
.avail-tick {
  display: inline-block; width: 6px; height: 6px; border-radius: 999px;
  background: #cbd5e1;
}
.month-cell:hover .avail-tick { background: #60a5fa; }

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

/* Add/Edit Booking modal */
.entry-modal {
  width: min(620px, 96vw); max-height: 92vh; background: #ffffff;
  border-radius: 10px; box-shadow: 0 24px 70px rgba(15,23,42,0.36);
  display: flex; flex-direction: column; overflow: hidden;
}
.entry-modal-head {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
  padding: 18px 22px 14px; border-bottom: 1px solid #e5e7eb;
}
.entry-modal-head h2 { margin: 0 0 4px; font-size: 18px; font-weight: 900; }
.entry-modal-head p { margin: 0; font-size: 12px; color: #64748b; font-weight: 600; }
.entry-modal-body { padding: 18px 22px; overflow: auto; display: flex; flex-direction: column; gap: 12px; }
.entry-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.entry-modal-foot {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 22px; border-top: 1px solid #e5e7eb;
}

/* Cell menu modal (booking view/edit/delete) */
.cell-menu-modal {
  width: min(440px, 92vw); background: #ffffff;
  border-radius: 10px; box-shadow: 0 24px 70px rgba(15,23,42,0.36);
  display: flex; flex-direction: column; overflow: hidden;
}
.cell-menu-head {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
  padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
}
.cell-menu-eyebrow {
  display: block; font-size: 10px; font-weight: 900; text-transform: uppercase;
  letter-spacing: 0.6px; color: #0284c7; margin-bottom: 3px;
}
.cell-menu-head h2 {
  margin: 0; font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.3;
}
.cell-menu-body { padding: 16px 20px; }
.cell-menu-empty p { margin: 0; color: #475569; font-size: 13px; }
.cell-menu-dl {
  display: grid; grid-template-columns: 90px 1fr; gap: 10px 14px; margin: 0;
}
.cell-menu-dl dt { color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; align-self: center; }
.cell-menu-dl dd { margin: 0; font-size: 13px; font-weight: 700; color: #0f172a; }
.cell-menu-dl dd input[type="date"] {
  width: 100%; height: 34px; border: 1.5px solid #dbe3ee; border-radius: 6px;
  padding: 0 10px; font-size: 13px; outline: none; background: white;
}
.cell-menu-dl dd input[type="date"]:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
.cell-menu-foot {
  display: flex; justify-content: space-between; gap: 8px;
  padding: 14px 20px; border-top: 1px solid #e5e7eb; background: #f8fafc;
}
.btn-danger {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px;
  font-size: 12.5px; font-weight: 800; cursor: pointer;
  background: #fee2e2; color: #991b1b;
}
.btn-danger:hover { background: #fecaca; }

/* Photo panels inside product detail modal */
.photo-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px;
}
.photo-panel {
  border: 1.5px solid #111827; background: #fff; border-radius: 6px; overflow: hidden;
  display: flex; flex-direction: column;
}
.photo-actions {
  background: #171717; color: #fff; padding: 7px 10px;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  font-size: 10px; font-weight: 900; text-transform: uppercase;
}
.btn-upload, .btn-remove-photo {
  height: 26px; border: none; border-radius: 5px; padding: 0 10px; font-size: 10px;
  font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
}
.btn-upload { background: #fff; color: #172033; }
.btn-upload input { display: none; }
.btn-upload:has(input:disabled) { opacity: 0.6; cursor: not-allowed; }
.btn-remove-photo { background: #fee2e2; color: #991b1b; }
.btn-remove-photo:hover { background: #fecaca; }
.photo-preview {
  flex: 1; min-height: 160px; display: flex; align-items: center; justify-content: center;
  background: #f8fafc;
}
.photo-preview img { width: 100%; height: 100%; max-height: 220px; object-fit: cover; display: block; }
.photo-placeholder { color: #94a3b8; font-size: 11px; font-weight: 700; padding: 24px; text-align: center; }

/* Landmark distance column */
.landmark-col-cat { width: 32% !important; }
.landmark-col-dist { width: 24% !important; }

/* Wizard modal */
.wizard-modal {
  width: min(900px, 96vw); max-height: 92vh; background: #ffffff; color: #111827;
  display: flex; flex-direction: column; border-radius: 10px;
  box-shadow: 0 24px 70px rgba(15,23,42,0.36); overflow: hidden;
}
.wizard-header {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
  padding: 18px 22px 12px; border-bottom: 1px solid #e5e7eb;
}
.wizard-header h2 { margin: 0 0 2px; font-size: 18px; font-weight: 900; }
.wizard-header p { margin: 0; font-size: 12px; color: #64748b; font-weight: 700; }
.wizard-tabs {
  display: flex; gap: 8px; padding: 10px 22px; border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}
.wizard-tabs button {
  flex: 1; display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  border: 1.5px solid #dbe3ee; background: #fff; border-radius: 7px; cursor: pointer;
  font-size: 12px; font-weight: 800; color: #475569;
}
.wizard-tabs button.active { border-color: #0f766e; color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.12); }
.wizard-tabs button.done { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
.wizard-tab-num {
  width: 20px; height: 20px; border-radius: 999px; background: #e8eef5; color: #334155;
  display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900;
}
.wizard-tabs button.active .wizard-tab-num { background: #0f766e; color: #fff; }
.wizard-tabs button.done .wizard-tab-num { background: #16a34a; color: #fff; }
.wizard-body { flex: 1; overflow: auto; padding: 18px 22px; }
.wizard-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.wizard-grid .field.full { grid-column: 1 / -1; }
.wizard-grid .field.full.inline-checkbox label {
  display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #1f2937;
  text-transform: none; letter-spacing: 0;
}
.wizard-grid .field.full.inline-checkbox input { width: auto; height: auto; }
.wizard-note {
  margin: 0 0 12px; padding: 10px 12px; background: #fff7ed; border: 1px solid #fed7aa;
  border-radius: 6px; color: #9a3412; font-size: 12px; font-weight: 700;
}
.wizard-sites { display: flex; flex-direction: column; gap: 12px; }
.wizard-site {
  border: 1px solid #dbe3ee; border-radius: 8px; padding: 12px; background: #f8fafc;
}
.wizard-site header {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px;
}
.wizard-site header strong { display: block; color: #0f172a; font-size: 13px; font-weight: 900; }
.wizard-site header p { margin: 2px 0 0; color: #64748b; font-size: 11px; font-weight: 700; }
.wizard-photo-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.wizard-photo-cell { display: flex; flex-direction: column; gap: 6px; }
.wizard-photo-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; }
.wizard-photo-frame {
  height: 120px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px;
  display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.wizard-photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
.btn-mini {
  height: 28px; border: 1px solid #cbd5e1; background: #fff; color: #172033;
  border-radius: 5px; padding: 0 10px; font-size: 11px; font-weight: 900; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
}
.btn-mini input { display: none; }
.btn-mini:hover { background: #f1f5f9; }
.wizard-review { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.review-block { background: #f8fafc; border: 1px solid #dbe3ee; border-radius: 7px; padding: 12px 14px; }
.review-block.full { grid-column: 1 / -1; }
.review-block h3 { margin: 0 0 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; color: #0f766e; letter-spacing: 0.5px; }
.review-block dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 0; font-size: 12px; }
.review-block dt { color: #64748b; font-weight: 700; }
.review-block dd { margin: 0; color: #0f172a; font-weight: 800; }
.review-sites { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.review-sites li {
  display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center;
  padding: 8px 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
  font-size: 12px;
}
.photo-status { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
.photo-status.ok { background: #dcfce7; color: #166534; }
.photo-status.missing { background: #fee2e2; color: #991b1b; }
.wizard-footer {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
  padding: 14px 22px; border-top: 1px solid #e5e7eb; background: #fff;
}
.wizard-footer-right { display: flex; gap: 8px; }
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
  .photo-grid { grid-template-columns: 1fr; }
  .wizard-grid, .wizard-photo-row, .wizard-review { grid-template-columns: 1fr; }
}
</style>
