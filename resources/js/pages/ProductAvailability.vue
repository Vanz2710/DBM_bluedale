<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Site Availability</h1>
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
        <button type="button" class="btn-register" @click="openRegisterModal">+ Register Product</button>
        <button type="button" class="btn-map-toggle" :class="{ active: showMapView }" @click="toggleMapView">
          {{ showMapView ? 'Table View' : 'Map View' }}
        </button>
        <button class="btn-proposal" :disabled="selectedProductIds.length === 0" @click="openProposalWizard">
          Generate Proposal ({{ selectedProductIds.length }})
        </button>
      </div>
    </div>

    <!-- Map View -->
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
        <span class="table-title-name">Site Availability</span>
        <span class="table-title-count">{{ rows.length }} Product(s)</span>
        <div class="table-title-controls">
          <div class="view-toggle">
            <button :class="{ active: tableViewMode === 'month' }" @click="tableViewMode = 'month'">Month</button>
            <button :class="{ active: tableViewMode === 'week' }" @click="tableViewMode = 'week'">Week</button>
          </div>
          <button class="btn-today" @click="goToCurrentYear">Today</button>
          <div class="table-nav">
            <button @click="changeYear(-1)" aria-label="Previous year">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button @click="changeYear(1)" aria-label="Next year">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
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
                  <div class="place-avatar" :style="avatarStyle(index)">{{ avatarLabel(row) }}</div>
                  <span class="place-cell-name" :title="row.site_name">{{ row.site_name }}</span>
                </div>
                <div class="place-cell-meta">
                  <span class="badge badge-product">{{ row.product_type }}</span>
                  <span class="badge" :class="`badge-status-${row.status.toLowerCase().replace(/ /g, '-')}`">{{ row.status }}</span>
                  <span class="badge badge-type">{{ row.type }}</span>
                </div>
              </div>
            </td>
            <td
              v-for="slot in monthSlots(row)"
              :key="slot.month"
              :colspan="slot.span"
              class="month-cell"
              :class="{ booked: slot.booked }"
              @click.stop="openCellMenu(row, slot.month)"
            >
              <template v-if="slot.booked">
                <div
                  class="booking-bar"
                  :class="index % 2 === 0 ? 'booking-bar-blue' : 'booking-bar-orange'"
                  :title="`${slot.booking.company_name} · ${formatDate(slot.booking.start_date) || '—'} → ${formatDate(slot.booking.end_date) || '—'}`"
                >
                  <span class="booking-bar-company">{{ slot.booking.company_name }}</span>
                  <span v-if="bookingDuration(slot.booking)" class="booking-bar-duration">{{ bookingDuration(slot.booking) }}</span>
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

          <div class="detail-map-links">
            <a class="map-link" :href="productMapUrl" target="_blank" rel="noopener">Open in Google Maps</a>
            <a v-if="productStreetViewUrl" class="street-view-link" :href="productStreetViewUrl" target="_blank" rel="noopener">Street View</a>
          </div>
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

    <!-- Register New Product Modal -->
    <div v-if="showRegisterModal" class="modal-backdrop" @click.self="closeRegisterModal">
      <section class="register-modal" role="dialog" aria-modal="true">
        <div class="register-modal-header">
          <h2>Register New Product</h2>
          <button type="button" class="detail-close" @click="closeRegisterModal">&times;</button>
        </div>
        <div class="register-modal-body">
          <div v-if="registerError" class="error-msg">{{ registerError }}</div>
          <div class="register-form-grid">

            <div class="field register-full">
              <label>Site Name *</label>
              <div class="register-site-row">
                <input v-model="registerForm.site_name" placeholder="e.g. KL - Bangsar: Jalan Maarof">
                <a :href="registerCoordMapUrl" target="_blank" rel="noopener" class="btn-open-gmaps">Open Google Maps</a>
              </div>
            </div>

            <div class="field register-full">
              <label>Find Location <span class="field-hint">Paste a Google Maps link or type a place name</span></label>
              <div class="location-input-wrap">
                <input
                  v-model="locationInput"
                  placeholder="Paste a Maps link or type a place name..."
                  autocomplete="off"
                  :class="{ 'input-error-border': locationError }"
                  @input="onLocationInput"
                >
                <span v-if="locationResolving || locationSearching" class="location-status">
                  <span class="lm-spinner"></span> {{ locationResolving ? 'Resolving...' : 'Searching...' }}
                </span>
                <div v-if="locationResults.length > 0" class="place-results">
                  <button
                    v-for="result in locationResults"
                    :key="result.place_id"
                    type="button"
                    class="place-result-item"
                    @click="selectLocationResult(result)"
                  >{{ result.display_name }}</button>
                </div>
              </div>
              <div v-if="locationError" class="maps-link-error">{{ locationError }}</div>
              <div v-else-if="registerForm.coordinate" class="coord-preview">Coordinate: {{ registerForm.coordinate }}</div>
            </div>

            <div class="field">
              <label>Ad Format *</label>
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

            <div class="field register-full">
              <div class="landmark-label-row">
                <label style="margin:0">
                  Nearest Landmarks
                  <span v-if="landmarkFetching" class="landmark-fetch-status"><span class="lm-spinner"></span> Searching...</span>
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
                  <tr><th>Category</th><th>Nearest Place</th></tr>
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
        </div>
        <div class="register-modal-footer">
          <button type="button" class="btn-clear" @click="closeRegisterModal">Cancel</button>
          <button type="button" class="btn-add" :disabled="registerSaving || !registerForm.site_name.trim()" @click="submitRegisterProduct">
            {{ registerSaving ? 'Registering...' : 'Register New Product' }}
          </button>
        </div>
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
const typeOptions = ref(['A1', 'A2', 'Ongoing', 'Reject']);
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

// Map view state
const showMapView = ref(false);
const mapEl = ref(null);
let leafletMap = null;
let mapMarkers = [];

// Register Product modal state
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
  nearest_landmarks: defaultRegisterLandmarks(),
});
const locationInput = ref('');
const locationError = ref('');
const locationResolving = ref(false);
const locationSearching = ref(false);
const locationResults = ref([]);
let locationTimer = null;
const landmarkFetching = ref(false);
const landmarkFetched = ref(false);

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
const productStreetViewUrl = computed(() => {
  const coord = selectedProduct.value?.coordinate;
  if (!coord) return null;
  const parsed = parseCoordinate(coord);
  if (!parsed) return null;
  return `https://maps.google.com/maps?q=&layer=c&cbll=${parsed.lat},${parsed.lng}`;
});
const registerCoordMapUrl = computed(() => {
  const coord = registerForm.value?.coordinate;
  if (!coord) return 'https://maps.google.com';
  const parsed = parseCoordinate(coord);
  if (!parsed) return 'https://maps.google.com';
  return `https://www.google.com/maps?q=${parsed.lat},${parsed.lng}`;
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

// --- Coordinate helper ---
function parseCoordinate(coord) {
  if (!coord) return null;
  const parts = coord.split(',').map((s) => parseFloat(s.trim()));
  if (parts.length !== 2 || parts.some(isNaN)) return null;
  return { lat: parts[0], lng: parts[1] };
}

// --- Register landmark defaults ---
function defaultRegisterLandmarks() {
  return [
    { category: 'Shopping Mall',         place: 'Not Found' },
    { category: 'School',                place: 'Not Found' },
    { category: 'Residential Area',      place: 'Not Found' },
    { category: 'Hospital / Clinic',     place: 'Not Found' },
    { category: 'MRT / LRT Station',     place: 'Not Found' },
    { category: 'Petrol Station',        place: 'Not Found' },
    { category: 'University / College',  place: 'Not Found' },
    { category: 'Police Station',        place: 'Not Found' },
    { category: 'Government Office',     place: 'Not Found' },
  ];
}

// --- Map View ---
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
  if (leafletMap) { leafletMap.remove(); leafletMap = null; }
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
      className: '', iconSize: [30, 30], iconAnchor: [15, 15], popupAnchor: [0, -18],
    });

    const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(row.coordinate)}`;
    const svUrl = `https://maps.google.com/maps?q=&layer=c&cbll=${coord.lat},${coord.lng}`;
    const popupHtml = `<div style="font-size:12px;min-width:170px">
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

// --- Register Product modal ---
function openRegisterModal() {
  registerForm.value = {
    site_name: '',
    product_type: 'Temp Board',
    status: 'Existing',
    type: 'A1',
    state_city: '',
    coordinate: '',
    nearest_landmarks: defaultRegisterLandmarks(),
  };
  locationInput.value = '';
  locationError.value = '';
  locationResolving.value = false;
  locationSearching.value = false;
  locationResults.value = [];
  registerError.value = '';
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
      site_name:          registerForm.value.site_name.trim(),
      product_type:       registerForm.value.product_type,
      status:             registerForm.value.status,
      type:               registerForm.value.type,
      state_city:         registerForm.value.state_city.trim() || null,
      coordinate:         registerForm.value.coordinate.trim() || null,
      nearest_landmarks:  registerForm.value.nearest_landmarks.filter((lm) => lm.category || lm.place),
    });
    upsertRow(res.data.data);
    closeRegisterModal();
    if (showMapView.value) refreshMapMarkers();
  } catch (e) {
    const errors = e.response?.data?.errors;
    registerError.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to register product.';
  } finally {
    registerSaving.value = false;
  }
}

// --- Google Maps link parsing ---
function parseMapsLink(url) {
  let m = url.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = url.match(/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = url.match(/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = url.match(/\/(-?\d{1,3}\.\d{4,}),(-?\d{1,3}\.\d{4,})/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  return null;
}

// --- Unified location input (Maps link OR place name search) ---
function onLocationInput() {
  clearTimeout(locationTimer);
  locationError.value = '';
  locationResults.value = [];
  registerForm.value.coordinate = '';

  const val = locationInput.value.trim();
  if (!val) return;

  if (val.startsWith('http://') || val.startsWith('https://')) {
    const direct = parseMapsLink(val);
    if (direct) {
      registerForm.value.coordinate = `${direct.lat.toFixed(6)}, ${direct.lng.toFixed(6)}`;
      fetchNearestLandmarks(direct.lat, direct.lng);
      return;
    }
    if (val.length > 15) {
      locationTimer = setTimeout(() => resolveMapsUrl(val), 400);
      return;
    }
    locationError.value = 'Invalid Google Maps URL.';
    return;
  }

  locationTimer = setTimeout(searchLocations, 400);
}

async function resolveMapsUrl(url) {
  locationResolving.value = true;
  locationError.value = '';
  try {
    const res = await api.post('/v1/product-availability/resolve-maps-url', { url });
    const lat = parseFloat(res.data.lat);
    const lng = parseFloat(res.data.lng);
    registerForm.value.coordinate = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    fetchNearestLandmarks(lat, lng);
  } catch (e) {
    locationError.value = e.response?.data?.error ?? 'Could not extract coordinates from this link.';
  } finally {
    locationResolving.value = false;
  }
}

async function searchLocations() {
  locationSearching.value = true;
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationInput.value)}&limit=6&countrycodes=my`,
      { headers: { 'Accept-Language': 'en' } },
    );
    locationResults.value = await res.json();
  } catch {
    locationResults.value = [];
  } finally {
    locationSearching.value = false;
  }
}

function selectLocationResult(result) {
  const lat = parseFloat(result.lat).toFixed(6);
  const lng = parseFloat(result.lon).toFixed(6);
  registerForm.value.coordinate = `${lat}, ${lng}`;
  locationInput.value = result.display_name;
  locationResults.value = [];
  fetchNearestLandmarks(parseFloat(lat), parseFloat(lng));
}

function refreshLandmarks() {
  const parsed = parseCoordinate(registerForm.value.coordinate);
  if (parsed) fetchNearestLandmarks(parsed.lat, parsed.lng);
}

// --- Overpass API landmark fetching ---
async function fetchNearestLandmarks(lat, lng) {
  landmarkFetching.value = true;
  landmarkFetched.value = false;

  const r = 3000;
  // Include relation type so OSM multi-polygon features (malls, campuses, stations) are found.
  // out center 100 ensures enough candidates are returned — Overpass does NOT sort by distance,
  // so a small limit like 10 would return arbitrary elements and miss the nearest one.
  function overpassQ(tagLines) {
    const body = tagLines.map((t) =>
      `node${t}(around:${r},${lat},${lng});way${t}(around:${r},${lat},${lng});relation${t}(around:${r},${lat},${lng});`
    ).join('');
    return `[out:json][timeout:25];(${body});out center 100;`;
  }

  const queries = [
    { category: 'Shopping Mall',         q: overpassQ([`["shop"="mall"]["name"]`, `["shop"="shopping_centre"]["name"]`, `["building"="mall"]["name"]`]) },
    { category: 'School',                q: overpassQ([`["amenity"="school"]["name"]`]) },
    // "landuse=residential" is almost never named in OSM; "place=village/neighbourhood/suburb" is.
    { category: 'Residential Area',      q: overpassQ([`["place"~"neighbourhood|suburb|village"]["name"]`]) },
    { category: 'Hospital / Clinic',     q: overpassQ([`["amenity"="hospital"]["name"]`, `["amenity"="clinic"]["name"]`]) },
    // Malaysian transit stops use both railway=station and public_transport=station tags.
    { category: 'MRT / LRT Station',     q: overpassQ([`["railway"="station"]["name"]`, `["railway"="halt"]["name"]`, `["public_transport"="station"]["name"]`]) },
    { category: 'Petrol Station',        q: overpassQ([`["amenity"="fuel"]["name"]`]) },
    { category: 'University / College',  q: overpassQ([`["amenity"="university"]["name"]`, `["amenity"="college"]["name"]`]) },
    { category: 'Police Station',        q: overpassQ([`["amenity"="police"]["name"]`]) },
    { category: 'Government Office',     q: overpassQ([`["amenity"="townhall"]["name"]`, `["office"="government"]["name"]`]) },
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
      .filter((e) => e.tags?.name)
      .map((e) => {
        const elat = e.lat ?? e.center?.lat;
        const elng = e.lon ?? e.center?.lon;
        if (!elat || !elng) return null;
        return { name: e.tags.name, km: haversineKm(lat, lng, elat, elng) };
      })
      .filter(Boolean)
      .sort((a, b) => a.km - b.km)[0];
  }

  const settled = await Promise.allSettled(
    queries.map(({ q }) => fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: q }).then((res) => res.json())),
  );

  registerForm.value.nearest_landmarks = queries.map(({ category }, i) => {
    const elements = settled[i].status === 'fulfilled' ? (settled[i].value?.elements ?? []) : [];
    const best = pickNearest(elements);
    return { category, place: best ? `${best.km.toFixed(1)}km — ${best.name}` : 'Not Found' };
  });
  landmarkFetched.value = true;
  landmarkFetching.value = false;
}

onUnmounted(() => {
  if (leafletMap) { leafletMap.remove(); leafletMap = null; }
});

// --- Table view ---
const tableViewMode = ref('month');

function goToCurrentYear() {
  year.value = new Date().getFullYear();
  load();
}

function monthSlots(row) {
  const slots = [];
  let i = 0;
  while (i < months.length) {
    const month = months[i].value;
    const booking = bookingFor(row, month);
    if (booking) {
      let span = 1;
      while (i + span < months.length) {
        const next = bookingFor(row, months[i + span].value);
        if (next && next.company_name === booking.company_name) span++;
        else break;
      }
      slots.push({ booked: true, booking, month, span });
      i += span;
    } else {
      slots.push({ booked: false, booking: null, month, span: 1 });
      i++;
    }
  }
  return slots;
}

function bookingDuration(booking) {
  if (!booking.start_date || !booking.end_date) return null;
  const days = Math.round((new Date(booking.end_date) - new Date(booking.start_date)) / 86400000) + 1;
  return days > 0 ? `${days} days` : null;
}

function avatarLabel(row) {
  if (row.product_type === 'Billboard') return 'BB';
  if (row.product_type === 'Lamp Post Bunting') return 'LB';
  return 'TB';
}

const AVATAR_PALETTE = [
  { bg: '#f3e8ff', color: '#7e22ce' },
  { bg: '#dbeafe', color: '#1d4ed8' },
  { bg: '#e0f2fe', color: '#0369a1' },
  { bg: '#f1f5f9', color: '#475569' },
  { bg: '#fce7f3', color: '#be185d' },
  { bg: '#d1fae5', color: '#065f46' },
];

function avatarStyle(index) {
  const c = AVATAR_PALETTE[index % AVATAR_PALETTE.length];
  return { background: c.bg, color: c.color };
}

onMounted(load);
</script>

<style scoped>
.page { padding: 28px 32px; color: var(--text-1); }
.page-header {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px;
  display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 14px;
}
.page-header h1 { margin: 0 0 4px; font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; }
.page-header p { margin: 0; color: var(--text-3); font-size: 13.5px; }
.year-control { display: flex; align-items: center; gap: 6px; }
.year-control button {
  width: 34px; height: 34px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface-2); color: var(--text-1); font-size: 18px; cursor: pointer;
}
.year-control input {
  width: 88px; height: 34px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  text-align: center; font-size: 14px; font-weight: 800; background: var(--surface); color: var(--text-1);
  outline: none;
}
/* Compact action bar replaces old entry-panel + toolbar + proposal-panel */
.action-bar {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px;
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
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2);
}
.field input, .field select {
  height: 38px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 12px;
  font-size: 13px; outline: none; background: var(--surface); color: var(--text-1);
}
.field input:focus, .field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
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
.btn-add, .btn-dark, .btn-clear, .btn-proposal {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(124,58,237,0.4); }
.btn-add:hover { background: var(--primary-hover); }
.btn-add:disabled, .btn-proposal:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-dark { background: var(--text-1); color: #ffffff; }
.btn-dark:hover { opacity: 0.88; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }
.btn-proposal { background: #0f766e; color: white; }
.error-msg {
  background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600;
}
.table-wrap {
  background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border);
  overflow: auto; position: relative;
}
.table-title {
  background: var(--surface); color: var(--text-1); border-bottom: 1px solid var(--border); padding: 10px 14px;
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
  border-radius: var(--radius) var(--radius) 0 0;
}
.table-title-name { color: var(--text-1); }
.table-title-count { color: var(--text-2); font-weight: 700; }
.table-title-controls { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.view-toggle {
  display: flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;
}
.view-toggle button {
  padding: 5px 11px; font-size: 11px; font-weight: 700; text-transform: none; letter-spacing: 0;
  border: none; border-right: 1px solid var(--border); background: var(--surface); color: var(--text-2); cursor: pointer;
}
.view-toggle button:last-child { border-right: none; }
.view-toggle button.active { background: var(--primary); color: #fff; }
.view-toggle button:not(.active):hover { background: var(--surface); }
.btn-today {
  height: 28px; padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-2); font-size: 11px; font-weight: 700; text-transform: none;
  letter-spacing: 0; cursor: pointer;
}
.btn-today:hover { background: var(--surface); color: var(--text-1); }
.table-nav { display: flex; gap: 2px; }
.table-nav button {
  width: 28px; height: 28px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-2); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.table-nav button:hover { background: var(--surface); color: var(--text-1); }
.loading-msg { text-align: center; padding: 44px; color: var(--text-2); }

/* Compact gantt table */
.gantt-table {
  width: 100%; table-layout: fixed; min-width: 820px;
  border-collapse: separate; border-spacing: 0; font-size: 12px;
}
.gantt-table th, .gantt-table td { border: none; border-right: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft); }
.gantt-table thead th {
  background: var(--surface); color: var(--text-2); padding: 8px 6px; font-size: 10px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.5px; text-align: center; white-space: nowrap;
  position: sticky; top: 0; z-index: 3; border-bottom: 2px solid var(--border);
}
.gantt-table tbody td {
  height: 56px; padding: 0; vertical-align: middle; background: var(--surface);
}
.product-row:hover td { background: var(--surface-2); }
.product-row:hover .month-cell.booked .booking-bar { filter: brightness(0.96); }

/* Sticky checkbox + place columns */
.select-col {
  width: 38px; min-width: 38px; text-align: center;
  position: sticky; left: 0; z-index: 2; background: var(--surface);
}
.gantt-table thead .select-col { background: var(--surface); }
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
  width: 220px; min-width: 220px; max-width: 220px;
  position: sticky; left: 38px; z-index: 2; background: var(--surface);
  text-align: left; cursor: pointer;
  border-right: 1.5px solid var(--border) !important;
  box-shadow: 6px 0 6px -6px rgba(15,23,42,0.18);
}
.gantt-table thead .place-col { background: var(--surface); text-align: left; padding-left: 14px; }
.place-cell { padding: 8px 12px; display: flex; flex-direction: column; gap: 5px; }
.place-cell-main { display: flex; align-items: flex-start; gap: 8px; }
.place-avatar {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 900; letter-spacing: -0.3px;
}
.place-cell-name {
  font-size: 12.5px; font-weight: 700; color: var(--text-1); line-height: 1.3;
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

/* Month header + cells — widths distributed by table-layout: fixed */
.gantt-table .month-th { /* width auto-distributed */ }
.month-cell {
  text-align: center; cursor: pointer; position: relative;
}
.month-cell.booked { background: transparent; }
.booking-bar {
  min-height: 44px; margin: 6px; padding: 6px 10px;
  display: flex; flex-direction: column; justify-content: center;
  border-radius: var(--radius-sm); border-left: 4px solid;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  overflow: hidden; transition: filter 0.12s;
}
.booking-bar-blue { background: #eff6ff; border-left-color: #0284c7; }
.booking-bar-orange { background: #fff7ed; border-left-color: #f97316; }
.booking-bar-company {
  font-size: 10.5px; font-weight: 800;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.booking-bar-blue .booking-bar-company { color: #0c4a6e; }
.booking-bar-orange .booking-bar-company { color: #9a3412; }
.booking-bar-duration { font-size: 9.5px; font-weight: 600; margin-top: 2px; }
.booking-bar-blue .booking-bar-duration { color: #0284c7; }
.booking-bar-orange .booking-bar-duration { color: #f97316; }
.month-cell:hover { background: var(--surface); }
.month-cell.booked:hover { background: transparent; }
.month-cell:hover .booking-bar { filter: brightness(0.97); }
.avail-tick {
  display: inline-block; width: 6px; height: 6px; border-radius: 999px;
  background: #cbd5e1;
}
.month-cell:hover .avail-tick { background: #60a5fa; }

.empty-state { text-align: center; padding: 36px; color: var(--text-2); font-size: 13px; font-weight: 700; background: var(--surface); }
.modal-backdrop {
  position: fixed; inset: 0; z-index: 2000; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center; padding: 24px;
}
.product-detail-modal {
  width: min(880px, 96vw); max-height: 85vh; background: var(--surface); color: var(--text-1);
  display: grid; grid-template-columns: 136px 1fr; overflow: auto; border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.detail-side { background: #0f56ad; min-height: 520px; }
.detail-content { position: relative; padding: 28px 40px 34px; }
.detail-close {
  position: absolute; top: 12px; right: 14px; width: 32px; height: 32px; border: none;
  border-radius: var(--radius-sm); background: var(--surface-2); color: var(--text-1);
  font-size: 22px; line-height: 1; cursor: pointer;
}
.detail-close:hover { background: var(--border); }
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
  border: 1.5px solid #111827; height: 40px; padding: 8px 10px; background: var(--surface); vertical-align: middle;
}
.detail-table th, .landmark-table th { width: 42%; text-transform: uppercase; font-size: 10px; font-weight: 900; text-align: center; }
.detail-table td, .landmark-table td { font-weight: 750; text-align: center; line-height: 1.3; }
.detail-table a { color: #2563eb; font-weight: 900; text-decoration: underline; }
.detail-table input, .landmark-table input {
  width: 100%; min-height: 30px; border: 1px solid var(--border); border-radius: 5px;
  padding: 0 8px; color: var(--text-1); font-size: 11px; font-weight: 800; text-align: center;
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
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border);
}

/* Cell menu modal (booking view/edit/delete) */
.cell-menu-modal {
  width: min(440px, 95vw); background: var(--surface);
  border-radius: var(--radius-lg); box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  display: flex; flex-direction: column; overflow: hidden;
}
.cell-menu-head {
  position: relative; display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--border);
}
.cell-menu-eyebrow {
  display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.6px; color: var(--primary); margin-bottom: 3px;
}
.cell-menu-head h2 {
  margin: 0; font-size: 14px; font-weight: 700; color: var(--text-1); line-height: 1.3;
}
.cell-menu-body { padding: 20px; }
.cell-menu-empty p { margin: 0; color: var(--text-2); font-size: 13px; }
.cell-menu-dl {
  display: grid; grid-template-columns: 90px 1fr; gap: 10px 14px; margin: 0;
}
.cell-menu-dl dt { color: var(--text-2); font-size: 11px; font-weight: 700; text-transform: uppercase; align-self: center; }
.cell-menu-dl dd { margin: 0; font-size: 13px; font-weight: 700; color: var(--text-1); }
.cell-menu-dl dd input[type="date"] {
  width: 100%; height: 38px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 12px; font-size: 13px; outline: none; background: var(--surface); color: var(--text-1);
}
.cell-menu-dl dd input[type="date"]:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.cell-menu-foot {
  display: flex; justify-content: space-between; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface-2);
}
.btn-danger {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  background: #fee2e2; color: #991b1b;
}
.btn-danger:hover { background: #fecaca; }

/* Photo panels inside product detail modal */
.photo-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px;
}
.photo-panel {
  border: 1.5px solid #111827; background: var(--surface); border-radius: 6px; overflow: hidden;
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
  background: var(--surface-2);
}
.photo-preview img { width: 100%; height: 100%; max-height: 220px; object-fit: cover; display: block; }
.photo-placeholder { color: var(--text-3); font-size: 11px; font-weight: 700; padding: 24px; text-align: center; }

/* Landmark distance column */
.landmark-col-cat { width: 32% !important; }
.landmark-col-dist { width: 24% !important; }

/* Wizard modal */
.wizard-modal {
  width: min(900px, 96vw); max-height: 85vh; background: var(--surface); color: var(--text-1);
  display: flex; flex-direction: column; border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden;
}
.wizard-header {
  position: relative; display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--border);
}
.wizard-header h2 { margin: 0 0 2px; font-size: 15px; font-weight: 700; color: var(--text-1); }
.wizard-header p { margin: 0; font-size: 12px; color: var(--text-2); font-weight: 500; }
.wizard-tabs {
  display: flex; gap: 8px; padding: 12px 20px; border-bottom: 1px solid var(--border);
  background: var(--surface-2);
}
.wizard-tabs button {
  flex: 1; display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  border: 1.5px solid var(--border); background: var(--surface); border-radius: var(--radius-sm);
  cursor: pointer; font-size: 12px; font-weight: 700; color: var(--text-2);
}
.wizard-tabs button.active { border-color: var(--primary); color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.wizard-tabs button.done { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
.wizard-tab-num {
  width: 20px; height: 20px; border-radius: 999px; background: var(--surface-2); color: var(--text-2);
  display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;
}
.wizard-tabs button.active .wizard-tab-num { background: var(--primary); color: var(--primary-on); }
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
  border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--surface-2);
}
.wizard-site header {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px;
}
.wizard-site header strong { display: block; color: var(--text-1); font-size: 13px; font-weight: 900; }
.wizard-site header p { margin: 2px 0 0; color: var(--text-2); font-size: 11px; font-weight: 700; }
.wizard-photo-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.wizard-photo-cell { display: flex; flex-direction: column; gap: 6px; }
.wizard-photo-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-2); }
.wizard-photo-frame {
  height: 120px; background: var(--surface); border: 1px dashed var(--border); border-radius: 6px;
  display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.wizard-photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
.btn-mini {
  height: 28px; border: 1px solid var(--border); background: var(--surface); color: var(--text-1);
  border-radius: 5px; padding: 0 10px; font-size: 11px; font-weight: 900; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
}
.btn-mini input { display: none; }
.btn-mini:hover { background: #f1f5f9; }
.wizard-review { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.review-block { background: var(--surface-2); border: 1px solid var(--border); border-radius: 7px; padding: 12px 14px; }
.review-block.full { grid-column: 1 / -1; }
.review-block h3 { margin: 0 0 8px; font-size: 12px; font-weight: 900; text-transform: uppercase; color: #0f766e; letter-spacing: 0.5px; }
.review-block dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 0; font-size: 12px; }
.review-block dt { color: var(--text-2); font-weight: 700; }
.review-block dd { margin: 0; color: var(--text-1); font-weight: 800; }
.review-sites { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.review-sites li {
  display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center;
  padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
  font-size: 12px;
}
.photo-status { font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
.photo-status.ok { background: #dcfce7; color: #166534; }
.photo-status.missing { background: #fee2e2; color: #991b1b; }
.wizard-footer {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface);
}
.wizard-footer-right { display: flex; gap: 8px; }
.btn-register {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  background: var(--text-1); color: var(--primary-on); white-space: nowrap;
}
.btn-register:hover { opacity: 0.88; }
.btn-map-toggle {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  background: var(--surface); color: var(--text-2); white-space: nowrap;
}
.btn-map-toggle:hover { background: var(--surface-2); color: var(--text-1); }
.btn-map-toggle.active { background: var(--text-1); color: var(--primary-on); border-color: var(--text-1); }

/* Map section — isolation:isolate scopes Leaflet's internal z-indexes (zoom controls at z:1000)
   so modal backdrops at z:50 in the root context correctly appear above the entire map */
.map-section {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  overflow: hidden; margin-bottom: 14px; isolation: isolate;
}
.leaflet-map { height: 480px; width: 100%; }
.map-legend {
  display: flex; gap: 18px; padding: 10px 16px; background: var(--surface-2);
  border-top: 1px solid var(--border); flex-wrap: wrap;
}
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-2); }
.legend-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

/* Street View link */
.detail-map-links { display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
.street-view-link {
  display: inline-flex; align-items: center; justify-content: center; min-height: 36px;
  padding: 0 14px; border-radius: var(--radius-sm); background: #2563eb; color: #fff;
  font-size: 12px; font-weight: 800; text-decoration: none;
}
.street-view-link:hover { opacity: 0.9; }

/* Register Product modal */
.register-modal {
  width: min(720px, 95vw); max-height: 85vh; background: var(--surface); color: var(--text-1);
  border-radius: var(--radius-lg); overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2); display: flex; flex-direction: column;
}
.register-modal-header {
  position: relative; background: var(--text-1); color: var(--primary-on); padding: 18px 20px 14px;
  display: flex; align-items: flex-start; justify-content: space-between; flex-shrink: 0;
}
.register-modal-header h2 { margin: 0; font-size: 15px; font-weight: 700; }
.register-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.register-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.register-full { grid-column: 1 / -1; }
.register-site-row { display: flex; gap: 8px; align-items: center; }
.register-site-row input { flex: 1; min-width: 0; }
.btn-open-gmaps {
  flex-shrink: 0; height: 36px; padding: 0 14px; border-radius: var(--radius-sm);
  background: var(--text-1); color: var(--primary-on); font-size: 13px; font-weight: 600;
  text-decoration: none; display: flex; align-items: center; white-space: nowrap;
}
.btn-open-gmaps:hover { opacity: 0.88; }
.field-hint { margin-left: 6px; font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: none; letter-spacing: 0; }
.coord-preview { margin-top: 5px; font-size: 11px; font-weight: 700; color: var(--primary); }
.register-modal-header .detail-close { background: rgba(255,255,255,0.12); color: var(--primary-on); }
.register-modal-header .detail-close:hover { background: rgba(255,255,255,0.22); }
.register-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--border); flex-shrink: 0; }
.location-input-wrap { position: relative; display: flex; align-items: center; gap: 8px; }
.location-input-wrap input { flex: 1; min-width: 0; width: 100%; box-sizing: border-box; }
.location-status { font-size: 11px; font-weight: 700; color: var(--primary); white-space: nowrap; display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.maps-link-error { margin-top: 5px; font-size: 11px; font-weight: 700; color: #dc2626; }
.input-error-border { border-color: #dc2626 !important; }
.place-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 200;
  background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-md); overflow: hidden; max-height: 220px; overflow-y: auto;
}
.place-result-item {
  width: 100%; min-height: 38px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 8px 12px;
  font-size: 12px; font-weight: 600; cursor: pointer; display: block; line-height: 1.4;
}
.place-result-item:last-child { border-bottom: none; }
.place-result-item:hover { background: var(--surface-2); }
.place-loading { color: var(--text-3); font-weight: 700; cursor: default; pointer-events: none; }

/* Landmark auto-fill */
.landmark-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.landmark-fetch-status { margin-left: 8px; font-size: 10px; font-weight: 700; text-transform: none; letter-spacing: 0; color: var(--text-3); }
.landmark-fetch-ok { color: #16a34a; }
.btn-refresh-landmarks {
  flex-shrink: 0; height: 28px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700; cursor: pointer;
}
.btn-refresh-landmarks:hover { background: var(--surface); border-color: var(--primary); color: var(--primary); }
.register-landmark-table { width: 100%; border-collapse: collapse; margin-top: 0; table-layout: fixed; }
.register-landmark-table th {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--primary-on); background: var(--text-1); padding: 6px 10px; text-align: left;
}
.register-landmark-table th:first-child { width: 36%; }
.register-landmark-table tbody tr { border-bottom: 1px solid var(--border-soft); }
.register-landmark-table tbody tr:last-child { border-bottom: none; }
.lm-cat-cell { padding: 7px 10px; font-size: 12px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.lm-place-cell { padding: 7px 10px; font-size: 12px; font-weight: 600; color: var(--text-1); }
.lm-not-found { color: var(--text-3); font-style: italic; }
@keyframes lm-skeleton-shine { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
.lm-skeleton {
  display: inline-block; width: 70%; height: 12px; border-radius: var(--radius-sm);
  background: var(--border); animation: lm-skeleton-shine 1.2s ease-in-out infinite;
}
@keyframes lm-spin { to { transform: rotate(360deg); } }
.lm-spinner {
  display: inline-block; width: 11px; height: 11px; border: 2px solid var(--border);
  border-top-color: var(--primary); border-radius: 50%; animation: lm-spin 0.7s linear infinite; vertical-align: middle;
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
  .photo-grid { grid-template-columns: 1fr; }
  .wizard-grid, .wizard-photo-row, .wizard-review { grid-template-columns: 1fr; }
  .register-form-grid { grid-template-columns: 1fr; }
  .leaflet-map { height: 320px; }
}
</style>
