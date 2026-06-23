<template>
  <div class="page">
    <Transition name="toast">
      <div v-if="toastMsg" class="toast-msg" role="status">{{ toastMsg }}</div>
    </Transition>

    <div class="page-header">
      <div>
        <h1 class="page-title">Site Availability</h1>
        <p class="page-subtitle">Billboard, Temp Board and Lamp Post Bunting rental schedule for {{ year }}.</p>
      </div>
      <div class="year-control">
        <button type="button" @click="changeYear(-1)" aria-label="Previous year"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
        <input v-model.number="year" type="number" min="2020" max="2100" @change="load">
        <button type="button" @click="changeYear(1)" aria-label="Next year"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
      </div>
    </div>

    <div v-if="error" class="error-msg">{{ error }}</div>

    <div class="action-bar">
      <div class="action-bar-filters">
        <div class="field">
          <label>Product</label>
          <select v-model="productFilter" @change="load">
            <option value="">All types</option>
            <option v-for="product in productOptions" :key="product" :value="product">{{ product }}</option>
          </select>
        </div>
        <div class="field">
          <label>Search</label>
          <input v-model="search" placeholder="Place or company…" @keyup.enter="load">
        </div>
        <button class="btn-search" @click="load">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <button class="btn-clear" @click="clearFilters">Clear</button>
      </div>
      <div class="action-bar-actions">
        <div class="action-group-secondary">
          <button class="btn-add" @click="openEntryModal()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Booking
          </button>
          <button type="button" class="btn-register" @click="openRegisterModal">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Register Product
          </button>
        </div>
        <div class="action-group-primary">
          <button type="button" class="btn-map-toggle" :class="{ active: showMapView }" @click="toggleMapView">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            {{ showMapView ? 'Table View' : 'Map View' }}
          </button>
          <button class="btn-proposal" :disabled="selectedProductIds.length === 0" @click="openProposalWizard">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Generate Proposal
            <span v-if="selectedProductIds.length > 0" class="proposal-count">{{ selectedProductIds.length }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Staged for Review section — only shown when there are pending products -->
    <div v-if="pendingRows.length > 0" class="staged-section">
      <div class="staged-header" @click="stagedCollapsed = !stagedCollapsed">
        <div class="staged-header-left">
          <svg class="staged-chevron" :class="{ 'staged-chevron-open': !stagedCollapsed }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          <span class="staged-title">Staged for Client Review</span>
          <span class="staged-count-chip">{{ pendingRows.length }}</span>
        </div>
        <p class="staged-sub">These sites are awaiting client approval. Confirm to add them to the availability list, or discard to remove.</p>
      </div>
      <div v-if="!stagedCollapsed" class="staged-grid">
        <article v-for="(product, i) in pendingRows" :key="product.id" class="staged-card">
          <div class="staged-card-avatar" :style="avatarStyle(i)">{{ avatarLabel(product) }}</div>
          <div class="staged-card-info">
            <div class="staged-card-name" :title="product.site_name">{{ product.site_name }}</div>
            <div class="staged-card-badges">
              <span class="badge badge-product">{{ product.product_type }}</span>
              <span class="badge" :class="`badge-status-${product.status.toLowerCase().replace(/ /g, '-')}`">{{ product.status }}</span>
              <span class="badge badge-type">{{ product.type }}</span>
            </div>
            <div v-if="product.coordinate" class="staged-card-coord">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 14 8 14s8-8.75 8-14a8 8 0 0 0-8-8z"/></svg>
              {{ product.coordinate }}
            </div>
            <div v-else class="staged-card-no-coord">No coordinate set</div>
          </div>
          <div class="staged-card-meta">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Staged {{ formatStagedAt(product.created_at) }}
          </div>
          <div class="staged-card-actions">
            <button type="button" class="btn-staged-detail" @click="openProductDetail(product)">Details</button>
            <button type="button" class="btn-staged-pdf" @click="printPdfForStagedProduct(product)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Print PDF
            </button>
            <button type="button" class="btn-staged-confirm" @click="confirmProductDirect(product)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Confirm
            </button>
            <button type="button" class="btn-staged-discard" @click="openDiscardProductModalFor(product)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Discard
            </button>
          </div>
        </article>
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

    <div class="table-card">
      <div class="table-title">
        <span class="table-title-name">Site Availability</span>
        <span class="table-title-count">{{ confirmedRows.length }} Product(s)</span>
        <div class="table-title-controls">
          <div class="view-toggle">
            <button :class="{ active: tableViewMode === 'month' }" @click="tableViewMode = 'month'">Month</button>
            <button :class="{ active: tableViewMode === 'week' }" @click="tableViewMode = 'week'">Week</button>
          </div>
          <div v-if="tableViewMode === 'month'" class="table-nav">
            <button @click="tableNavShift(-1)" aria-label="Previous year">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button @click="tableNavShift(1)" aria-label="Next year">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div class="table-wrap" ref="ganttWrapRef">
      <div v-if="loading" class="loading-msg">Loading...</div>
      <table v-else class="gantt-table" :style="tableViewMode === 'week' ? { width: ganttTableWidth + 'px' } : {}">
        <thead>
          <!-- Month group row — week view only -->
          <tr v-if="tableViewMode === 'week'" class="month-group-row">
            <th class="select-col month-group-spacer"></th>
            <th class="place-col month-group-spacer"></th>
            <th
              v-for="group in weekMonthGroups"
              :key="group.key"
              :colspan="group.span"
              class="month-group-th"
            >{{ group.label }}</th>
          </tr>
          <tr>
            <th class="select-col" @click.stop>
              <input type="checkbox" :checked="allRowsSelected" :disabled="confirmedRows.length === 0" @change="toggleAllRows">
            </th>
            <th class="place-col">Place</th>
            <th
              v-for="col in columns"
              :key="col.value"
              class="month-th"
              :class="{
                'week-th': tableViewMode === 'week',
                'week-th-today': tableViewMode === 'week' && col.isToday,
              }"
              :style="tableViewMode === 'week' ? { width: weekColWidth + 'px', minWidth: weekColWidth + 'px' } : {}"
              :data-is-today="tableViewMode === 'week' && col.isToday ? '' : undefined"
            >
              <template v-if="tableViewMode === 'week'">
                <span class="week-th-dates">{{ col.short }}</span>
                <span class="week-th-month">{{ col.monthShort }}</span>
              </template>
              <template v-else>{{ col.short }}</template>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="confirmedRows.length === 0">
            <td :colspan="columns.length + 2" class="empty-state">No confirmed products yet. Register a product to get started.</td>
          </tr>
          <tr v-for="(row, i) in pagedRows" :key="row.id" class="product-row">
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
                  <div class="place-avatar" :style="avatarStyle(pageStartIndex + i)">{{ avatarLabel(row) }}</div>
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
              v-for="slot in slotsFor(row)"
              :key="slot.key"
              :colspan="slot.span"
              class="month-cell"
              :class="{
                booked: slot.booked,
                'week-cell-today': tableViewMode === 'week' && slot.isToday && !slot.booked,
              }"
              @click.stop="openCellMenu(row, slot.month)"
            >
              <template v-if="slot.booked">
                <div
                  class="booking-bar"
                  :class="[
                    (pageStartIndex + i) % 2 === 0 ? 'booking-bar-blue' : 'booking-bar-orange',
                    { 'booking-bar-done': isBookingExpired(slot.booking) }
                  ]"
                  :title="`${slot.booking.company_name} · ${formatDate(slot.booking.start_date) || '—'} → ${formatDate(slot.booking.end_date) || '—'}${isBookingExpired(slot.booking) ? ' (Completed)' : ''}`"
                >
                  <span class="booking-bar-company">{{ slot.booking.company_name }}</span>
                  <span v-if="bookingDuration(slot.booking)" class="booking-bar-duration">{{ isBookingExpired(slot.booking) ? 'Completed' : bookingDuration(slot.booking) }}</span>
                </div>
              </template>
              <span v-else class="avail-tick" aria-hidden="true"></span>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <div v-if="!loading && confirmedRows.length > rowsPerPage" class="pagination">
        <span class="pagination-info">
          Showing {{ pageStartIndex + 1 }}–{{ pageRangeEnd }} of {{ confirmedRows.length }} products
        </span>
        <div class="pagination-btns">
          <button class="page-nav" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)" aria-label="Previous page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <template v-for="pg in pageNumbers" :key="'pg-' + pg">
            <button
              v-if="pg !== '...'"
              class="page-num"
              :class="{ 'page-num--on': pg === currentPage }"
              @click="goToPage(pg)"
            >{{ pg }}</button>
            <span v-else class="page-ellipsis">…</span>
          </template>
          <button class="page-nav" :disabled="currentPage >= totalPages" @click="goToPage(currentPage + 1)" aria-label="Next page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <div v-if="selectedProduct" class="modal-backdrop">
      <section class="product-detail-modal" role="dialog" aria-modal="true">

        <!-- Header -->
        <div class="detail-head">
          <div class="detail-head-info">
            <h2>{{ selectedProduct.site_name }}</h2>
            <div class="detail-head-badges">
              <span class="badge badge-product">{{ selectedProduct.product_type }}</span>
              <span class="badge" :class="`badge-status-${selectedProduct.status.toLowerCase().replace(/ /g, '-')}`">{{ selectedProduct.status }}</span>
              <span class="badge badge-type">{{ selectedProduct.type }}</span>
            </div>
          </div>
          <button type="button" class="detail-close" aria-label="Close" @click="closeProductDetail">&times;</button>
        </div>

        <!-- Pending banner -->
        <div v-if="selectedProduct.is_pending" class="pending-banner">
          <div class="pending-banner-text">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Pending client review — confirm to add to your site list
          </div>
          <div class="pending-banner-actions">
            <button type="button" class="btn-confirm-product" :disabled="confirmingProduct" @click="confirmPendingProduct">
              {{ confirmingProduct ? 'Confirming…' : 'Confirm & Add to List' }}
            </button>
            <button type="button" class="btn-discard-product" @click="openDiscardProductModal">Discard</button>
          </div>
        </div>

        <!-- Scrollable body -->
        <div class="detail-body">
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
                      <select v-if="editingDetails && item.field && item.options" v-model="detailForm[item.field]" :aria-label="item.label">
                        <option value="">—</option>
                        <option v-for="opt in item.options" :key="opt" :value="opt">{{ opt }}</option>
                      </select>
                      <input
                        v-else-if="editingDetails && item.field"
                        v-model="detailForm[item.field]"
                        :aria-label="item.label"
                        @change="item.field === 'coordinate' ? onDetailCoordChange($event.target.value) : undefined"
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
              <div class="photo-preview" style="position:relative;">
                <img v-if="billboardComposites[selectedProduct?.id]" :src="billboardComposites[selectedProduct.id]" alt="Billboard composite">
                <img v-else-if="selectedProduct.site_photo_url" :src="selectedProduct.site_photo_url" alt="Site photo">
                <span v-else class="photo-placeholder">No site photo uploaded</span>
                <span v-if="billboardComposites[selectedProduct?.id]" class="composite-tag">Billboard Preview</span>
              </div>
              <div v-if="isBillboardType(selectedProduct?.product_type) && selectedProduct?.site_photo_url" class="billboard-overlay-bar">
                <span class="billboard-overlay-hint">Billboard overlay</span>
                <button type="button" class="btn-overlay-pill" @click="openOverlayEditor(selectedProduct)">
                  {{ billboardComposites[selectedProduct.id] ? 'Edit Overlay' : 'Add Billboard' }}
                </button>
                <button v-if="billboardComposites[selectedProduct?.id]" type="button" class="btn-remove-photo" style="height:24px; font-size:9px; padding:0 8px;" @click="removeBillboardComposite(selectedProduct.id)">Remove</button>
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
        </div><!-- /detail-body -->
      </section>
    </div>

    <!-- Add / Edit Booking Modal -->
    <div v-if="entryModalOpen" class="modal-backdrop">
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
              <span v-if="selectedContactId" class="company-linked-badge">
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
            <button type="button" class="btn-clear" @click="closeEntryModal">Cancel</button>
            <button type="button" class="btn-add" :disabled="saving || !canAdd" @click="saveFromModal">
              {{ saving ? 'Saving…' : 'Save Booking' }}
            </button>
          </div>
        </footer>
      </section>
    </div>

    <!-- Cell Menu Modal (view / edit / delete booking, quick-add if empty) -->
    <div v-if="cellMenu.open" class="modal-backdrop">
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
    <div v-if="showRegisterModal" class="modal-backdrop">
      <section class="register-modal" role="dialog" aria-modal="true">
        <div class="register-modal-header">
          <h2>Register New Product</h2>
          <button type="button" class="detail-close" @click="closeRegisterModal">&times;</button>
        </div>
        <div class="register-modal-body">
          <div v-if="registerError" class="error-msg">{{ registerError }}</div>
          <div class="register-form-grid">

            <div class="field register-full">
              <label>Site Name <span class="req-star">*</span></label>
              <div class="register-site-row">
                <input v-model="registerForm.site_name" placeholder="e.g. KL - Bangsar: Jalan Maarof">
              </div>
            </div>

            <div class="field register-full">
              <div class="field-loc-head">
                <label style="margin:0;">Find Location <span class="field-hint">Paste a Google Maps link or type a place name</span></label>
                <a :href="registerCoordMapUrl" target="_blank" rel="noopener" class="btn-open-gmaps">Open Google Maps</a>
              </div>
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
              <label>Ad Format <span class="req-star">*</span></label>
              <select v-model="registerForm.product_type">
                <option v-for="p in productOptions" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="field">
              <label>Type <span class="req-star">*</span></label>
              <select v-model="registerForm.type">
                <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="field">
              <label>Status <span class="req-star">*</span></label>
              <select v-model="registerForm.status">
                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div class="field">
              <label>Site Code <span class="field-hint">optional</span></label>
              <input v-model="registerForm.site_code" placeholder="e.g. BB-KL-001">
            </div>
            <div class="field">
              <label>Board / Unit Size <span class="field-hint">optional</span></label>
              <input v-model="registerForm.size" placeholder="e.g. 20ft × 10ft">
            </div>
            <template v-if="registerForm.product_type !== 'Lamp Post Bunting'">
              <div class="field">
                <label>Illumination</label>
                <select v-model="registerForm.illumination">
                  <option value="">—</option>
                  <option v-for="opt in ILLUMINATION_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
              <div class="field">
                <label>Facing</label>
                <select v-model="registerForm.facing">
                  <option value="">—</option>
                  <option v-for="opt in FACING_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
            </template>
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
                    <td class="lm-place-cell">
                      <span v-if="landmarkFetching" class="lm-skeleton"></span>
                      <input
                        v-else
                        :value="lm.place === 'Not Found' ? '' : lm.place"
                        class="lm-place-input"
                        placeholder="Not found"
                        :class="{ 'lm-not-found': lm.place === 'Not Found' }"
                        @input="registerForm.nearest_landmarks[i].place = $event.target.value || 'Not Found'"
                      >
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
        <div class="register-modal-footer">
          <p class="register-footer-note">
            <strong>Register Product</strong> adds it to the active list.
            <strong>Save as Draft</strong> saves a temporary entry and opens the proposal wizard so you can print a PDF first.
          </p>
          <div class="register-footer-actions">
            <button type="button" class="btn-clear" @click="closeRegisterModal">Cancel</button>
            <button type="button" class="btn-stage-pdf" :disabled="registerSaving || !registerForm.site_name.trim()" @click="submitStagePendingProduct">
              {{ registerSaving ? 'Saving...' : 'Save as Draft + Print PDF' }}
            </button>
            <button type="button" class="btn-add" :disabled="registerSaving || !registerForm.site_name.trim()" @click="submitRegisterProduct">
              {{ registerSaving ? 'Registering...' : 'Register Product' }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- Proposal Wizard Modal -->
    <div v-if="proposalWizardOpen" class="modal-backdrop">
      <section class="wizard-modal" role="dialog" aria-modal="true">
        <header class="wizard-header">
          <div>
            <h2>Generate Proposal</h2>
            <p>{{ selectedProductIds.length }} site(s) selected · Step {{ wizardStepIndex + 1 }} of {{ visibleWizardSteps.length }}</p>
          </div>
          <button type="button" class="detail-close" @click="closeProposalWizard">&times;</button>
        </header>

        <nav class="wizard-tabs">
          <button
            v-for="(step, i) in visibleWizardSteps"
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

          <!-- Section: Client -->
          <div class="wizard-section">
            <div class="wizard-section-head">Client</div>
            <div class="wizard-grid">
              <div class="field full">
                <label>Company / Client Name</label>
                <input v-model="proposalForm.client_name" placeholder="e.g. ACC Evesuite Medical Centre">
              </div>
              <div class="field">
                <label>Attention <span class="field-hint">person name</span></label>
                <input v-model="proposalForm.attention" placeholder="e.g. Amira">
              </div>
              <div class="field">
                <label>Designation <span class="field-hint">printed on acceptance block</span></label>
                <input v-model="proposalForm.client_designation" placeholder="e.g. Director">
              </div>
              <div class="field">
                <label>Attention Phone</label>
                <div class="phone-input" :class="{ 'input-error-border': proposalErrors.attention_phone }">
                  <select v-model="proposalForm.attention_phone_code" class="phone-code-select">
                    <option v-for="c in phoneCountries" :key="c.code" :value="c.code">{{ c.code }} {{ c.short }} · {{ c.name }}</option>
                  </select>
                  <input v-model="proposalForm.attention_phone_local" class="phone-local-input" placeholder="17-842 7710">
                </div>
                <p v-if="proposalErrors.attention_phone" class="field-error">{{ proposalErrors.attention_phone }}</p>
              </div>
            </div>
          </div>

          <!-- Section: Package -->
          <div class="wizard-section">
            <div class="wizard-section-head">Package Details</div>
            <div class="wizard-grid">
              <!-- Row 1: timing -->
              <div class="field small">
                <label>Duration <span class="field-hint">months</span></label>
                <input v-model.number="proposalForm.duration" type="number" min="1" max="36" placeholder="e.g. 3">
              </div>
              <div class="field">
                <label>Duration Display Text <span class="field-hint">leave blank to auto-generate</span></label>
                <input v-model="proposalForm.duration_label" :placeholder="`${proposalForm.duration} MONTH${proposalForm.duration > 1 ? 'S' : ''}`">
              </div>
              <!-- Row 2: pricing -->
              <div class="field">
                <label>Normal Price <span class="field-hint">RM, per {{ isBuntingOnly ? 'pcs' : 'site' }}</span></label>
                <input v-model.number="proposalForm.normal_price" type="number" min="0" placeholder="e.g. 180" :class="{ 'input-error-border': proposalErrors.normal_price }">
                <p v-if="proposalErrors.normal_price" class="field-error">{{ proposalErrors.normal_price }}</p>
              </div>
              <div class="field">
                <label>Offered Price / Unit <span class="field-hint">RM</span></label>
                <input v-model.number="proposalForm.price_per_unit" type="number" min="0" placeholder="e.g. 120" :class="{ 'input-error-border': proposalErrors.price_per_unit }">
                <p v-if="proposalErrors.price_per_unit" class="field-error">{{ proposalErrors.price_per_unit }}</p>
              </div>
              <!-- Row 3: offer conditions -->
              <div class="field">
                <label>Promo Valid Until <span class="field-hint">optional</span></label>
                <input v-model="proposalForm.promo_until" type="date" class="promo-date-input">
              </div>
              <div class="field small">
                <label>SST Rate <span class="field-hint">0.08 = 8%</span></label>
                <input v-model.number="proposalForm.sst_rate" type="number" min="0" max="1" step="0.01">
              </div>
              <!-- Bunting size (only relevant for bunting — shown as column header in the table) -->
              <div v-if="isBuntingOnly" class="field full">
                <label>Bunting Size <span class="field-hint">shown in the QUANTITY column header (e.g. 7 x 3)</span></label>
                <input v-model="proposalForm.quantity_size" placeholder="e.g. 7 x 3">
              </div>
            </div>
          </div>

          <!-- Section: Document Settings -->
          <div class="wizard-section">
            <div class="wizard-section-head">Document Settings</div>
            <div class="wizard-grid">
              <div class="field">
                <label>Reference No.</label>
                <input v-model="proposalForm.reference" placeholder="Auto-generated">
              </div>
              <div class="field">
                <label>RE: Subject Line <span class="field-hint">leave blank to auto-generate</span></label>
                <input v-model="proposalForm.re_line" placeholder="Auto-generated from selected sites">
              </div>
            </div>
          </div>

          <!-- Section: Signatory -->
          <div class="wizard-section">
            <div class="wizard-section-head">Prepared By</div>
            <div v-if="myPreparedBy || activePreparedBy" class="wizard-signatory-presets">
              <button v-if="myPreparedBy" type="button"
                class="signatory-preset-btn"
                :class="{ active: proposalForm.signatory_name === myPreparedBy.name }"
                @click="applyPreparedByProfile(myPreparedBy)">
                My Profile
              </button>
              <button v-if="activePreparedBy && activePreparedBy.user_id !== authUserId" type="button"
                class="signatory-preset-btn"
                :class="{ active: proposalForm.signatory_name === activePreparedBy.name }"
                @click="applyPreparedByProfile(activePreparedBy)">
                {{ activePreparedBy.user_name }}'s Profile
              </button>
            </div>
            <div class="wizard-grid" style="margin-top:8px;">
              <div class="field">
                <label>Full Name</label>
                <input v-model="proposalForm.signatory_name" placeholder="e.g. John Doe">
              </div>
              <div class="field">
                <label>Title / Position</label>
                <input v-model="proposalForm.signatory_title" placeholder="e.g. Assistant Business Manager">
              </div>
              <div class="field">
                <label>Mobile</label>
                <div class="phone-input" :class="{ 'input-error-border': proposalErrors.signatory_mobile }">
                  <select v-model="proposalForm.signatory_mobile_code" class="phone-code-select">
                    <option v-for="c in phoneCountries" :key="c.code" :value="c.code">{{ c.code }} {{ c.short }} · {{ c.name }}</option>
                  </select>
                  <input v-model="proposalForm.signatory_mobile_local" class="phone-local-input" placeholder="14-907 253">
                </div>
                <p v-if="proposalErrors.signatory_mobile" class="field-error">{{ proposalErrors.signatory_mobile }}</p>
              </div>

              <!-- Save prepared-by details -->
              <div class="field full">
                <button type="button" class="btn-save-prep"
                  :disabled="savingPreparedBy || !proposalForm.signatory_name.trim()"
                  @click="savePreparedByProfile">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                  {{ savingPreparedBy ? 'Saving…' : preparedBySaved ? 'Saved' : 'Save My Details' }}
                </button>
              </div>

              <!-- Signature pad — full width -->
              <div class="field full sig-pad-wrap">
                <div class="sig-pad-head">
                  <label style="margin:0;">Signature
                    <span class="field-hint">draw or upload — saved per user</span>
                  </label>
                  <div class="sig-pad-actions">
                    <button type="button" class="sig-btn" @click="clearSigPad">Clear</button>
                    <label class="sig-btn">
                      Upload
                      <input type="file" accept="image/png,image/jpeg,image/webp" @change="onSigFileUpload">
                    </label>
                    <button type="button" class="sig-btn sig-btn-save" :disabled="sigSaving" @click="saveSig">
                      {{ sigSaving ? 'Saving…' : sigSaved ? 'Saved' : 'Save Signature' }}
                    </button>
                  </div>
                </div>
                <div class="sig-pad-canvas-wrap">
                  <canvas
                    ref="sigPadRef"
                    width="800" height="160"
                    class="sig-pad-canvas"
                    @mousedown="sigStartDraw"
                    @mousemove="sigDraw"
                    @mouseup="sigStopDraw"
                    @mouseleave="sigStopDraw"
                    @touchstart.prevent="sigStartDraw"
                    @touchmove.prevent="sigDraw"
                    @touchend="sigStopDraw"
                  ></canvas>
                  <span class="sig-pad-hint">Draw your signature here</span>
                </div>
              </div>
            </div>

            <!-- Super-admin: manage signatory profiles -->
            <div v-if="isSuperAdmin" class="profiles-admin-panel">
              <button type="button" class="profiles-admin-toggle" @click="toggleProfilesPanel">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Manage Signatory Profiles
                <svg class="toggle-chevron" :class="{ open: showProfilesPanel }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div v-if="showProfilesPanel" class="profiles-admin-body">
                <div v-if="loadingProfiles" class="profiles-loading">Loading…</div>
                <p v-else-if="preparedByProfiles.length === 0" class="profiles-empty">No profiles saved yet.</p>
                <div v-else v-for="profile in preparedByProfiles" :key="profile.user_id" class="profile-admin-row">
                  <div class="profile-admin-info">
                    <div class="profile-admin-name">{{ profile.name }}</div>
                    <div class="profile-admin-meta">{{ profile.title || '—' }} · {{ profile.user_email }}</div>
                  </div>
                  <div class="profile-admin-actions">
                    <span v-if="profile.is_active" class="badge-active-pill">Active</span>
                    <button v-else type="button" class="btn-set-active" @click="setActivePreparedBy(profile.user_id)">Set Active</button>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Step 2: Per-product photo check -->
        <div v-else-if="wizardStep === 'sheets'" class="wizard-body">
          <p class="wizard-note">Confirm each site has a photo and map. Paste (Ctrl+V) an image anywhere on this step to upload it.</p>
          <div class="wizard-sites">
            <article v-for="product in selectedProducts" :key="product.id" class="wizard-site">
              <header>
                <div>
                  <strong>{{ product.site_name }}</strong>
                  <p>{{ product.product_type }} · {{ product.site_code || '—' }} · {{ product.coordinate || 'No coordinate' }}</p>
                </div>
                <button type="button" class="btn-mini" @click="openProductDetail(product)">Edit Details</button>
              </header>
              <!-- Per-site piece count — bunting only -->
              <div v-if="product.product_type === 'Lamp Post Bunting' && siteQuantities[product.id]" class="wizard-pcs-row">
                <div class="wizard-pcs-field">
                  <label class="wizard-pcs-label">Pieces</label>
                  <input type="number" min="1" class="wizard-pcs-input" v-model.number="siteQuantities[product.id].pcs" @input="updatePoles(product.id)">
                  <span class="wizard-pcs-unit">pcs</span>
                </div>
                <div class="wizard-pcs-field">
                  <label class="wizard-pcs-label">Poles</label>
                  <input type="number" min="1" class="wizard-pcs-input" v-model.number="siteQuantities[product.id].poles">
                </div>
                <span class="wizard-pcs-preview">→ {{ siteQuantities[product.id].pcs }} pcs (2 sided - {{ siteQuantities[product.id].poles }} poles)</span>
              </div>
              <div class="wizard-photo-row">
                <div class="wizard-photo-cell">
                  <div class="wizard-photo-label">Site Photo</div>
                  <div class="wizard-photo-frame paste-drop-zone"
                       :class="{ 'paste-active': pasteTargetId === product.id + '_site_photo' }"
                       @click="setPasteTarget(product.id + '_site_photo')"
                       :title="pasteTargetId === product.id + '_site_photo' ? 'Active — Ctrl+V to paste' : 'Click to set as paste target'"
                       style="position:relative; cursor:pointer;">
                    <img v-if="billboardComposites[product.id]" :src="billboardComposites[product.id]" alt="Billboard composite">
                    <img v-else-if="product.site_photo_url" :src="product.site_photo_url" alt="Site photo">
                    <span v-else class="photo-placeholder">No photo — click then Ctrl+V</span>
                    <span v-if="billboardComposites[product.id]" class="composite-tag">Overlay</span>
                    <span v-if="pasteTargetId === product.id + '_site_photo'" class="paste-ready-tag">Ready to paste</span>
                  </div>
                  <div style="display:flex; gap:4px; flex-wrap:wrap; margin-top:4px;">
                    <label class="btn-mini">
                      {{ uploadingPhotoFor[product.id]?.site_photo ? 'Uploading…' : (product.site_photo ? 'Replace' : 'Upload') }}
                      <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingPhotoFor[product.id]?.site_photo" @change="onPhotoSelectedFor($event, product, 'site_photo')">
                    </label>
                    <button v-if="isBillboardType(product.product_type) && product.site_photo_url"
                            type="button" class="btn-mini btn-mini-overlay"
                            @click="openOverlayEditor(product)">
                      {{ billboardComposites[product.id] ? 'Edit Overlay' : 'Add Billboard' }}
                    </button>
                  </div>
                </div>
                <div class="wizard-photo-cell">
                  <div class="wizard-photo-label">Map Photo</div>
                  <div class="wizard-photo-frame paste-drop-zone"
                       :class="{ 'paste-active': pasteTargetId === product.id + '_site_map_photo' }"
                       @click="setPasteTarget(product.id + '_site_map_photo')"
                       :title="pasteTargetId === product.id + '_site_map_photo' ? 'Active — Ctrl+V to paste' : 'Click to set as paste target'"
                       style="cursor:pointer;">
                    <img v-if="product.site_map_photo_url" :src="product.site_map_photo_url" alt="Map photo">
                    <span v-else class="photo-placeholder">No map — click then Ctrl+V</span>
                    <span v-if="pasteTargetId === product.id + '_site_map_photo'" class="paste-ready-tag">Ready to paste</span>
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
        <div v-else-if="wizardStep === 'review'" class="wizard-body review-body">

          <!-- Top bar: print mode + site count -->
          <div class="review-topbar">
            <div class="print-mode-group">
              <button type="button" :class="['print-mode-btn', { active: proposalForm.print_mode === 'both' }]" @click="proposalForm.print_mode = 'both'">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Proposal + Site Sheets
              </button>
              <button type="button" :class="['print-mode-btn', { active: proposalForm.print_mode === 'proposal_only' }]" @click="proposalForm.print_mode = 'proposal_only'">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Proposal Only
              </button>
              <button type="button" :class="['print-mode-btn', { active: proposalForm.print_mode === 'sheets_only' }]" @click="proposalForm.print_mode = 'sheets_only'">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                Site Sheets Only
              </button>
            </div>
            <div class="review-site-count">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
              {{ selectedProducts.length }} site{{ selectedProducts.length !== 1 ? 's' : '' }}
            </div>
          </div>

          <!-- PDF Preview button -->
          <div class="pdf-preview-row">
            <div class="pdf-preview-status">
              <svg v-if="previewLoading" class="spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg v-else-if="previewUrl" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <span class="pdf-preview-status-text">
                {{ previewLoading ? 'Preparing preview…' : previewUrl ? 'Preview ready' : 'Preview unavailable' }}
              </span>
            </div>
            <div class="pdf-preview-actions">
              <button type="button" class="btn-open-preview" :disabled="previewLoading || !previewUrl" @click="openPreview">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Open Preview
              </button>
              <button type="button" class="btn-refresh-preview" :disabled="previewLoading" @click="generatePreview">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Refresh
              </button>
            </div>
          </div>

          <!-- Photo status strip -->
          <ul class="review-sites">
            <li v-for="p in selectedProducts" :key="p.id">
              <strong>{{ p.site_name }}</strong>
              <span :class="['photo-status', p.site_photo ? 'ok' : 'missing']">
                <svg v-if="p.site_photo" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                {{ p.site_photo ? 'photo' : 'no photo' }}
              </span>
              <span :class="['photo-status', p.site_map_photo ? 'ok' : 'missing']">
                <svg v-if="p.site_map_photo" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                {{ p.site_map_photo ? 'map' : 'no map' }}
              </span>
            </li>
          </ul>

          <div v-if="error" class="error-msg">{{ error }}</div>
        </div>

        <footer class="wizard-footer">
          <button type="button" class="btn-clear" @click="closeProposalWizard">Cancel</button>
          <div class="wizard-footer-right">
            <button v-if="wizardStepIndex > 0" type="button" class="btn-dark" @click="wizardStep = visibleWizardSteps[wizardStepIndex - 1].id">Back</button>
            <button v-if="wizardStepIndex < visibleWizardSteps.length - 1" type="button" class="btn-add" @click="wizardStep = visibleWizardSteps[wizardStepIndex + 1].id">Next</button>
            <button v-else type="button" class="btn-proposal" :disabled="generatingProposal" @click="generateProposal">
              {{ generatingProposal ? 'Generating…' : 'Generate PDF' }}
            </button>
          </div>
        </footer>
      </section>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="removePhotoModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Remove Photo</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeRemovePhotoModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Remove this photo?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeRemovePhotoModal">Cancel</button>
          <button class="conf-delete" :disabled="removePhotoModal.loading" @click="confirmRemovePhoto">
            {{ removePhotoModal.loading ? 'Removing…' : 'Remove Photo' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="discardProductModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Discard Pending Product</p>
            <p class="conf-sub">This will permanently delete the staged product.</p>
          </div>
          <button class="conf-close" @click="closeDiscardProductModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Discard <strong>{{ discardProductModal.product?.site_name }}</strong>? It will be removed from the list.</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDiscardProductModal">Cancel</button>
          <button class="conf-delete" :disabled="discardProductModal.loading" @click="confirmDiscardProduct">
            {{ discardProductModal.loading ? 'Discarding…' : 'Discard Product' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Billboard Overlay Editor -->
  <Teleport to="body">
    <div v-if="overlayEditorOpen" class="overlay-editor-backdrop">
      <div class="overlay-editor-modal">
        <div class="overlay-editor-head">
          <div>
            <p class="overlay-editor-title">Billboard Overlay Editor</p>
            <p class="overlay-editor-sub">Drag to position the billboard. Drag corners to resize. Upload your artwork to replace the placeholder.</p>
          </div>
          <button type="button" class="conf-close" @click="closeOverlayEditor">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="overlay-canvas-wrap">
          <canvas
            ref="overlayCanvas"
            width="620" height="414"
            class="overlay-canvas"
            @mousedown="onCanvasMouseDown"
            @mousemove="onCanvasMouseMove"
            @mouseup="onCanvasMouseUp"
            @mouseleave="onCanvasMouseLeave"
          ></canvas>
        </div>
        <div class="overlay-editor-foot">
          <label class="btn-upload" style="cursor:pointer;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload Artwork
            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="onArtworkFileSelected">
          </label>
          <button v-if="overlayArtDataUrl" type="button" class="btn-clear" @click="overlayArtDataUrl = null; drawOverlayCanvas()">Reset to Yellow</button>
          <div style="flex:1;"></div>
          <button type="button" class="btn-dark" @click="closeOverlayEditor">Cancel</button>
          <button type="button" class="btn-add" @click="applyBillboardComposite">Apply to Photo</button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="removeBookingModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Remove Booking</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeRemoveBookingModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Remove booking for <strong>{{ removeBookingModal.booking?.company_name }}</strong>?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeRemoveBookingModal">Cancel</button>
          <button class="conf-delete" :disabled="removeBookingModal.loading" @click="confirmRemoveBooking">
            {{ removeBookingModal.loading ? 'Removing…' : 'Remove Booking' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch, reactive } from 'vue';
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
const previewBlob = ref(null);
const previewUrl = ref(null);
const previewLoading = ref(false);
const phoneCountries = [
  { code: '+60',  short: 'MY', name: 'Malaysia' },
  { code: '+65',  short: 'SG', name: 'Singapore' },
  { code: '+62',  short: 'ID', name: 'Indonesia' },
  { code: '+66',  short: 'TH', name: 'Thailand' },
  { code: '+63',  short: 'PH', name: 'Philippines' },
  { code: '+673', short: 'BN', name: 'Brunei' },
  { code: '+84',  short: 'VN', name: 'Vietnam' },
  { code: '+95',  short: 'MM', name: 'Myanmar' },
  { code: '+855', short: 'KH', name: 'Cambodia' },
  { code: '+856', short: 'LA', name: 'Laos' },
  { code: '+91',  short: 'IN', name: 'India' },
  { code: '+86',  short: 'CN', name: 'China' },
  { code: '+81',  short: 'JP', name: 'Japan' },
  { code: '+82',  short: 'KR', name: 'South Korea' },
  { code: '+852', short: 'HK', name: 'Hong Kong' },
  { code: '+886', short: 'TW', name: 'Taiwan' },
  { code: '+61',  short: 'AU', name: 'Australia' },
  { code: '+64',  short: 'NZ', name: 'New Zealand' },
  { code: '+44',  short: 'GB', name: 'United Kingdom' },
  { code: '+1',   short: 'US', name: 'USA / Canada' },
  { code: '+971', short: 'AE', name: 'UAE' },
  { code: '+966', short: 'SA', name: 'Saudi Arabia' },
  { code: '+49',  short: 'DE', name: 'Germany' },
  { code: '+33',  short: 'FR', name: 'France' },
]

// ── Prepared-by profiles ───────────────────────────────────────────────────
const _authUser        = JSON.parse(localStorage.getItem('crm_user') || 'null');
const authUserId       = _authUser?.id ?? null;
const isSuperAdmin     = _authUser?.roles?.includes('super-admin') ?? false;

const myPreparedBy       = ref(null);
const activePreparedBy   = ref(null);
const preparedByProfiles = ref([]);
const savingPreparedBy   = ref(false);
const preparedBySaved    = ref(false);
const loadingProfiles    = ref(false);
const showProfilesPanel  = ref(false);

function applyPreparedByProfile(p) {
  proposalForm.value.signatory_name         = p.name || '';
  proposalForm.value.signatory_title        = p.title || '';
  proposalForm.value.signatory_mobile_code  = p.mobile_code || '+60';
  proposalForm.value.signatory_mobile_local = p.mobile_local || '';
  proposalForm.value.signatory_label        = p.signature_label || '';
}

async function loadPreparedByProfile() {
  try {
    const [ownRes, activeRes] = await Promise.all([
      api.get('/v1/prepared-by/own'),
      api.get('/v1/prepared-by/active'),
    ]);
    myPreparedBy.value     = ownRes.data;
    activePreparedBy.value = activeRes.data;
    const profile = ownRes.data || activeRes.data;
    if (profile) applyPreparedByProfile(profile);
  } catch (_) {}
}

async function savePreparedByProfile() {
  if (!proposalForm.value.signatory_name.trim()) return;
  savingPreparedBy.value = true;
  try {
    const label = proposalForm.value.signatory_label.trim()
      || proposalForm.value.signatory_name.trim().split(' ')[0];
    await api.put('/v1/prepared-by/own', {
      name:            proposalForm.value.signatory_name.trim(),
      title:           proposalForm.value.signatory_title.trim(),
      mobile_code:     proposalForm.value.signatory_mobile_code,
      mobile_local:    proposalForm.value.signatory_mobile_local.trim(),
      signature_label: label,
    });
    myPreparedBy.value = {
      name:            proposalForm.value.signatory_name.trim(),
      title:           proposalForm.value.signatory_title.trim(),
      mobile_code:     proposalForm.value.signatory_mobile_code,
      mobile_local:    proposalForm.value.signatory_mobile_local.trim(),
      signature_label: label,
    };
    proposalForm.value.signatory_label = label;
    preparedBySaved.value = true;
    showToast('Prepared-by details saved');
    setTimeout(() => { preparedBySaved.value = false; }, 2500);
  } catch (_) {
    showToast('Failed to save details');
  } finally {
    savingPreparedBy.value = false;
  }
}

async function loadProfilesPanel() {
  if (preparedByProfiles.value.length) return;
  loadingProfiles.value = true;
  try {
    const res = await api.get('/v1/prepared-by/profiles');
    preparedByProfiles.value = res.data;
  } catch (_) {} finally {
    loadingProfiles.value = false;
  }
}

function toggleProfilesPanel() {
  showProfilesPanel.value = !showProfilesPanel.value;
  if (showProfilesPanel.value) loadProfilesPanel();
}

async function setActivePreparedBy(userId) {
  try {
    await api.put(`/v1/prepared-by/profiles/${userId}/activate`);
    preparedByProfiles.value = preparedByProfiles.value.map(p => ({
      ...p, is_active: p.user_id === userId,
    }));
    const active = preparedByProfiles.value.find(p => p.user_id === userId);
    if (active) activePreparedBy.value = active;
    showToast('Active signatory updated');
  } catch (_) {
    showToast('Failed to update');
  }
}

// ── Signature pad ──────────────────────────────────────────────────────────
const sigPadRef  = ref(null);
const sigDrawing = ref(false);
const sigSaving  = ref(false);
const sigSaved   = ref(false);
const sigLoaded  = ref(false); // tracks whether sig was drawn for the current wizard session

function _sigCtx() {
  const c = sigPadRef.value;
  if (!c) return null;
  const ctx = c.getContext('2d');
  ctx.strokeStyle = '#111827';
  ctx.lineWidth   = 2.5;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
  return ctx;
}

function _sigCoords(e) {
  const c = sigPadRef.value;
  const r = c.getBoundingClientRect();
  const sx = c.width  / r.width;
  const sy = c.height / r.height;
  const src = e.touches ? e.touches[0] : e;
  return { x: (src.clientX - r.left) * sx, y: (src.clientY - r.top) * sy };
}

function sigStartDraw(e) {
  sigDrawing.value = true;
  const ctx = _sigCtx();
  if (!ctx) return;
  const { x, y } = _sigCoords(e);
  ctx.beginPath();
  ctx.moveTo(x, y);
}

function sigDraw(e) {
  if (!sigDrawing.value) return;
  const ctx = _sigCtx();
  if (!ctx) return;
  const { x, y } = _sigCoords(e);
  ctx.lineTo(x, y);
  ctx.stroke();
}

function sigStopDraw() {
  if (!sigDrawing.value) return;
  sigDrawing.value = false;
  const c = sigPadRef.value;
  if (c) proposalForm.value.signatory_signature = c.toDataURL('image/png');
}

function clearSigPad() {
  const c = sigPadRef.value;
  if (!c) return;
  c.getContext('2d').clearRect(0, 0, c.width, c.height);
  proposalForm.value.signatory_signature = '';
  sigSaved.value = false;
}

function onSigFileUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = new Image();
    img.onload = () => {
      const c = sigPadRef.value;
      if (!c) return;
      const ctx = c.getContext('2d');
      ctx.clearRect(0, 0, c.width, c.height);
      const ratio = Math.min(c.width / img.width, c.height / img.height);
      const w = img.width * ratio;
      const h = img.height * ratio;
      ctx.drawImage(img, (c.width - w) / 2, (c.height - h) / 2, w, h);
      proposalForm.value.signatory_signature = c.toDataURL('image/png');
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
  e.target.value = '';
}

async function saveSig() {
  const c = sigPadRef.value;
  if (!c) return;
  const data = c.toDataURL('image/png');
  sigSaving.value = true;
  try {
    await api.put('/v1/my-signature', { signature_data: data });
    proposalForm.value.signatory_signature = data;
    sigSaved.value = true;
    showToast('Signature saved');
  } catch (_) {
    // non-fatal
  } finally {
    sigSaving.value = false;
  }
}

async function loadSavedSig() {
  try {
    const res = await api.get('/v1/my-signature');
    const data = res.data?.signature_data;
    if (!data) return;
    proposalForm.value.signatory_signature = data;
    // Re-check ref after async gap — user may have navigated away from step 'info'
    const c = sigPadRef.value;
    if (!c) return;
    const img = new Image();
    img.onload = () => {
      // Re-fetch ref again in case canvas was replaced between load and paint
      const canvas = sigPadRef.value;
      if (!canvas) return;
      canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
      canvas.getContext('2d').drawImage(img, 0, 0);
    };
    img.src = data;
  } catch (_) { /* no saved sig */ }
}

// Fire loadSavedSig as soon as the canvas element is in the DOM (sigPadRef becomes non-null).
// This is more reliable than nextTick because we're reacting to the ref itself being set,
// meaning the canvas is guaranteed to exist when we try to draw on it.
watch(sigPadRef, (canvas) => {
  if (!canvas || sigLoaded.value) return;
  sigLoaded.value = true;
  loadSavedSig();
});

const proposalForm = ref({
  client_name: '',
  attention: '',
  client_designation: '',
  attention_phone_code:  '+60',
  attention_phone_local: '',
  reference: '',
  duration: 1,
  duration_label: '',
  normal_price: null,
  price_per_unit: null,
  quantity_size: '',
  sst_rate: 0.08,
  promo_until: '',
  re_line: '',
  print_mode: 'both',
  signatory_name:         '',
  signatory_title:        '',
  signatory_mobile_code:  '+60',
  signatory_mobile_local: '',
  signatory_label:        '',
  signatory_signature:    '',
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
const billboardComposites = ref({})
const siteQuantities = ref({})  // { [productId]: { pcs: number, poles: number } } — bunting only
const overlayEditorOpen = ref(false)
const overlayEditorProduct = ref(null)
const overlayCanvas = ref(null)
const overlayOvl = ref({ x: 0.28, y: 0.08, w: 0.16, h: 0.42 })
const overlayArtDataUrl = ref(null)
const overlayDragState = ref(null)
const overlayBgImage = ref(null)
const overlayArtImage = ref(null)
const overlayHovered = ref(false)
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
  illumination: '',
  facing: '',
  state_city: '',
  coordinate: '',
  site_code: '',
  size: '',
  nearest_landmarks: defaultRegisterLandmarks(),
});

const toastMsg = ref('');
let toastTimer = null;
function showToast(msg) {
  toastMsg.value = msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastMsg.value = ''; }, 3000);
}
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
const placeOptions = computed(() => [...confirmedRows.value].sort((a, b) => a.site_name.localeCompare(b.site_name)));
const placeSearch = ref('');
const showPlaceDrop = ref(false);
const filteredPlaces = computed(() => {
  const q = placeSearch.value.toLowerCase().trim();
  if (!q) return placeOptions.value;
  return placeOptions.value.filter(p => p.site_name.toLowerCase().includes(q));
});
function selectPlace(place) {
  form.value.site_name = place.site_name;
  placeSearch.value = place.site_name;
  showPlaceDrop.value = false;
  applyPlaceDefaults();
}
function onPlaceBlur() {
  setTimeout(() => { showPlaceDrop.value = false; }, 150);
}
const confirmedRows = computed(() => rows.value.filter((r) => !r.is_pending));
const pendingRows = computed(() => rows.value.filter((r) => r.is_pending));

// ── Pagination (client-side, table display only) ──
const currentPage = ref(1);
const rowsPerPage = ref(15);
const totalPages = computed(() => Math.max(1, Math.ceil(confirmedRows.value.length / rowsPerPage.value)));
const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value;
  return confirmedRows.value.slice(start, start + rowsPerPage.value);
});
const pageStartIndex = computed(() => (currentPage.value - 1) * rowsPerPage.value);
const pageRangeEnd = computed(() => Math.min(pageStartIndex.value + rowsPerPage.value, confirmedRows.value.length));
const pageNumbers = computed(() => {
  const total = totalPages.value;
  const cur = currentPage.value;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3) return [1, 2, 3, '...', total];
  if (cur >= total - 2) return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});
function goToPage(pg) {
  if (pg === '...' || pg < 1 || pg > totalPages.value) return;
  currentPage.value = pg;
}
// Clamp current page if the row count shrinks (filter/delete/discard)
watch([() => confirmedRows.value.length, rowsPerPage], () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});
const stagedCollapsed = ref(false);
const allRowsSelected = computed(() => pagedRows.value.length > 0 && pagedRows.value.every((row) => selectedProductIds.value.includes(row.id)));
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
const ILLUMINATION_OPTIONS = ['Lit', 'Unlit', 'LED', 'Backlit']
const FACING_OPTIONS = ['Right-Hand Read', 'Left-Hand Read', 'Cross Read', 'Single-Face', 'Back-To-Back', 'V-Shape', 'Tri-Face']

const detailRows = computed(() => {
  if (!selectedProduct.value) return [];

  const isBillboard = selectedProduct.value.product_type !== 'Lamp Post Bunting'
  const rows = [
    { label: 'Site', value: productSiteCode(selectedProduct.value), field: 'site_code' },
    { label: 'Size', value: selectedProduct.value.size || defaultProductSize(selectedProduct.value), field: 'size' },
  ]
  if (isBillboard) {
    rows.push({ label: 'Illumination', value: selectedProduct.value.illumination || '—', field: 'illumination', options: ILLUMINATION_OPTIONS })
    rows.push({ label: 'Facing', value: selectedProduct.value.facing || '—', field: 'facing', options: FACING_OPTIONS })
  }
  rows.push({ label: 'Location', value: productLocation(selectedProduct.value), field: 'location' })
  rows.push({ label: 'State & City', value: selectedProduct.value.state_city || inferredStateCity(selectedProduct.value.site_name), field: 'state_city' })
  rows.push({
    label: 'Coordinate',
    value: selectedProduct.value.coordinate || 'Not set',
    href: selectedProduct.value.coordinate ? productMapUrl.value : null,
    field: 'coordinate',
  })
  return rows
});
const landmarkRows = computed(() => {
  const landmarks = selectedProduct.value?.nearest_landmarks;
  if (Array.isArray(landmarks) && landmarks.length > 0) return landmarks;

  return defaultLandmarks();
});
const activeLandmarkRows = computed(() => (editingLandmarks.value ? landmarkForm.value : landmarkRows.value));
const isBuntingOnly = computed(() => selectedProducts.value.length > 0 && selectedProducts.value.every(p => p.product_type === 'Lamp Post Bunting'))
const hasBunting    = computed(() => selectedProducts.value.some(p => p.product_type === 'Lamp Post Bunting'))

const visibleWizardSteps = computed(() =>
  wizardSteps.filter(s => !(s.id === 'sheets' && proposalForm.value.print_mode === 'proposal_only'))
);
const wizardStepIndex = computed(() => visibleWizardSteps.value.findIndex((s) => s.id === wizardStep.value));
const selectedProducts = computed(() => rows.value.filter((row) => selectedProductIds.value.includes(row.id)));

const proposalErrors = computed(() => {
  const f = proposalForm.value
  const checkPhone = (local) => {
    if (!local?.trim()) return ''
    const digits = local.replace(/[\s\-\(\)]/g, '')
    return /^\d{6,12}$/.test(digits) ? '' : 'Enter digits only, no country code (e.g. 17-842 7710)'
  }
  return {
    attention_phone:  checkPhone(f.attention_phone_local),
    signatory_mobile: checkPhone(f.signatory_mobile_local),
    normal_price:     (f.normal_price !== null && f.normal_price !== '' && Number(f.normal_price) <= 0) ? 'Must be greater than 0' : '',
    price_per_unit:   (f.price_per_unit !== null && f.price_per_unit !== '' && Number(f.price_per_unit) <= 0) ? 'Must be greater than 0' : '',
    promo_until:      '',
  }
})
const hasStep1Errors = computed(() => Object.values(proposalErrors.value).some(Boolean))

watch(() => proposalForm.value.print_mode, (newMode) => {
  if (newMode === 'proposal_only' && wizardStep.value === 'sheets') {
    wizardStep.value = 'review';
  }
  if (wizardStep.value === 'review') generatePreview();
});

watch(wizardStep, (newStep, oldStep) => {
  if (newStep === 'review') {
    generatePreview();
  } else if (oldStep === 'review') {
    if (previewUrl.value) { window.URL.revokeObjectURL(previewUrl.value); previewUrl.value = null; }
    previewBlob.value = null;
  }
});
function makeDefaultReference() {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yy = String(d.getFullYear()).slice(-2);
  const time = `${String(d.getHours()).padStart(2, '0')}${String(d.getMinutes()).padStart(2, '0')}${String(d.getSeconds()).padStart(2, '0')}`;
  return `AEMC/PROPOSAL/${mm}-${yy}/${time}`;
}

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
    selectedProductIds.value = selectedProductIds.value.filter((id) => !pagedRows.value.some((row) => row.id === id));
    return;
  }
  selectedProductIds.value = [...new Set([...selectedProductIds.value, ...pagedRows.value.map((row) => row.id)])];
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

function formatStagedAt(value) {
  if (!value) return '';
  const d = new Date(value);
  const diffMs = Date.now() - d.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const time = d.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', hour12: true });
  return `${formatDate(value)} at ${time}`;
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
    illumination: '',
    facing: '',
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
    illumination: selectedProduct.value.illumination || '',
    facing: selectedProduct.value.facing || '',
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

async function onDetailCoordChange(val) {
  val = val.trim();
  if (!val.startsWith('http://') && !val.startsWith('https://')) return;
  const direct = parseMapsLink(val);
  if (direct) {
    detailForm.value.coordinate = `${direct.lat.toFixed(6)}, ${direct.lng.toFixed(6)}`;
    return;
  }
  try {
    const res = await api.post('/v1/site-availability/resolve-maps-url', { url: val });
    detailForm.value.coordinate = `${parseFloat(res.data.lat).toFixed(6)}, ${parseFloat(res.data.lng).toFixed(6)}`;
  } catch {
    // leave as-is; user will see an error on save if it can't be stored as coords
  }
}

async function saveDetails() {
  if (!selectedProduct.value) return;

  savingDetails.value = true;
  error.value = '';

  try {
    const res = await api.put(`/v1/site-availability/products/${selectedProduct.value.id}`, {
      site_name:   buildSiteNameFromDetails(),
      site_code:   detailForm.value.site_code.trim() || null,
      size:        detailForm.value.size.trim() || null,
      illumination:detailForm.value.illumination || null,
      facing:      detailForm.value.facing || null,
      state_city:  detailForm.value.state_city.trim() || null,
      coordinate:  detailForm.value.coordinate.trim() || null,
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
    const res = await api.put(`/v1/site-availability/products/${selectedProduct.value.id}`, {
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

function shortLocationFrom(siteName) {
  const idx = siteName.indexOf(': ')
  return idx !== -1 ? siteName.slice(idx + 2) : siteName
}

function updatePoles(productId) {
  const sq = siteQuantities.value[productId]
  if (sq) sq.poles = Math.max(1, Math.ceil(sq.pcs / 2))
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
    const res = await api.get('/v1/site-availability', { params });
    rows.value = (res.data.data ?? []).map(normalizeRow);
    currentPage.value = 1;
    selectedProductIds.value = selectedProductIds.value.filter((id) => rows.value.some((row) => row.id === id));
    productOptions.value = res.data.products ?? productOptions.value;
    statusOptions.value = res.data.statuses ?? statusOptions.value;
    typeOptions.value = res.data.types ?? typeOptions.value;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to load site availability.';
  } finally {
    loading.value = false;
  }
}

function buildProposalPayload() {
  const f = proposalForm.value;
  const pricePerUnit = Number(f.price_per_unit) || 0
  const sstRate = Number.isFinite(f.sst_rate) ? f.sst_rate : 0

  // For bunting proposals: build per-site rows using the pcs values keyed in Step 2.
  // Billboard/Temp Board rows are left to the controller's defaultRows() (no quantity column).
  let rows = undefined
  if (hasBunting.value) {
    rows = selectedProducts.value.map(p => {
      if (p.product_type === 'Lamp Post Bunting') {
        const sq = siteQuantities.value[p.id] || { pcs: 10, poles: 5 }
        const pcs   = Math.max(1, sq.pcs  || 10)
        const poles = Math.max(1, sq.poles || Math.ceil(pcs / 2))
        const rowPrice = pcs * pricePerUnit
        return {
          product_id:      p.id,
          location:        shortLocationFrom(p.site_name),
          quantity:        `${pcs} pcs`,
          quantity_detail: `2 sided- ${poles} poles`,
          price:           rowPrice,
          sst:             Math.round(rowPrice * sstRate * 100) / 100,
          confirmed:       '/',
        }
      } else {
        const rowPrice = pricePerUnit
        return {
          product_id:      p.id,
          location:        shortLocationFrom(p.site_name),
          quantity:        '',
          quantity_detail: '',
          price:           rowPrice,
          sst:             Math.round(rowPrice * sstRate * 100) / 100,
          confirmed:       '/',
        }
      }
    })
  }

  return {
    product_ids:           selectedProductIds.value,
    client_name:           (f.client_name || '').trim() || null,
    attention:             (f.attention || '').trim() || null,
    attention_phone:       f.attention_phone_local?.trim() ? (f.attention_phone_code + f.attention_phone_local.trim()) : null,
    reference:             (f.reference || '').trim() || null,
    duration:              f.duration || 1,
    duration_label:        (f.duration_label || '').trim() || null,
    normal_price:          f.normal_price || null,
    price_per_unit:        f.price_per_unit || null,
    quantity_size:         isBuntingOnly.value ? ((f.quantity_size || '').trim() || null) : null,
    sst_rate:              Number.isFinite(f.sst_rate) ? f.sst_rate : null,
    promo_until:           f.promo_until ? (() => { const [y,m,d] = f.promo_until.split('-'); return `${parseInt(d)}/${parseInt(m)}/${y}`; })() : null,
    re_line:               (f.re_line || '').trim() || null,
    include_site_sheets:   f.print_mode !== 'proposal_only',
    include_proposal_page: f.print_mode !== 'sheets_only',
    billboard_composites:  Object.keys(billboardComposites.value).length > 0 ? billboardComposites.value : undefined,
    rows,
    client_designation:    (f.client_designation || '').trim() || null,
    signatory_name:        (f.signatory_name || '').trim() || null,
    signatory_title:       (f.signatory_title || '').trim() || null,
    signatory_mobile:      f.signatory_mobile_local?.trim() ? (f.signatory_mobile_code + f.signatory_mobile_local.trim()) : null,
    signatory_label:       (f.signatory_label || '').trim() || null,
    signatory_signature:   f.signatory_signature || null,
  };
}

async function generatePreview() {
  if (selectedProductIds.value.length === 0) return;
  previewLoading.value = true;
  if (previewUrl.value) { window.URL.revokeObjectURL(previewUrl.value); previewUrl.value = null; }
  previewBlob.value = null;
  try {
    const res = await api.post('/v1/site-availability/proposal', buildProposalPayload(), { responseType: 'blob' });
    previewBlob.value = new Blob([res.data], { type: 'application/pdf' });
    previewUrl.value = window.URL.createObjectURL(previewBlob.value);
  } catch (_) {
    // preview failure is non-fatal — user can still download directly
  } finally {
    previewLoading.value = false;
  }
}

function openPreview() {
  if (previewUrl.value) window.open(previewUrl.value, '_blank');
}

async function generateProposal() {
  if (selectedProductIds.value.length === 0) return;
  if (hasStep1Errors.value) {
    error.value = 'Please fix the highlighted issues in Step 1 before generating.'
    wizardStep.value = 'info'
    return
  }

  generatingProposal.value = true;
  error.value = '';

  try {
    let blob = previewBlob.value;
    if (!blob) {
      const res = await api.post('/v1/site-availability/proposal', buildProposalPayload(), { responseType: 'blob' });
      blob = new Blob([res.data], { type: 'application/pdf' });
    }

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `proposal-${new Date().toISOString().slice(0, 10)}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    closeProposalWizard();
  } catch (e) {
    const status = e.response?.status;
    if (status === 422) {
      error.value = 'Some required information is missing. Please check all fields and try again.';
    } else if (status >= 500 || !status) {
      error.value = 'The PDF could not be generated due to a server error. Please try again or contact support.';
    } else {
      error.value = 'Failed to generate proposal PDF. Please try again.';
    }
  } finally {
    generatingProposal.value = false;
  }
}

const pasteTargetId = ref(null)

function setPasteTarget(id) {
  pasteTargetId.value = (pasteTargetId.value === id) ? null : id
}

function handleGlobalPaste(e) {
  if (!pasteTargetId.value || wizardStep.value !== 'sheets') return
  const items = e.clipboardData?.items
  if (!items) return
  for (const item of items) {
    if (!item.type.startsWith('image/')) continue
    const file = item.getAsFile()
    if (!file) continue
    // pasteTargetId format: "{productId}_site_photo" or "{productId}_site_map_photo"
    const isMap = pasteTargetId.value.endsWith('_site_map_photo')
    const field = isMap ? 'site_map_photo' : 'site_photo'
    const suffix = '_' + field
    const productId = parseInt(pasteTargetId.value.slice(0, -suffix.length), 10)
    const product = selectedProducts.value.find(p => p.id === productId)
    if (!product) return
    onPhotoSelectedFor({ target: { files: [file] } }, product, field)
    pasteTargetId.value = null
    break
  }
}

function openProposalWizard() {
  if (selectedProductIds.value.length === 0) return;
  proposalForm.value.reference = makeDefaultReference();
  error.value = '';
  wizardStep.value = 'info';
  // Initialise per-site pcs/poles for any bunting products (default: 10 pcs, 5 poles)
  const qty = {}
  selectedProducts.value.forEach(p => {
    if (p.product_type === 'Lamp Post Bunting') qty[p.id] = { pcs: 10, poles: 5 }
  })
  siteQuantities.value = qty
  sigLoaded.value = false; // allow watch to fire loadSavedSig for this session
  loadPreparedByProfile();
  proposalWizardOpen.value = true;
  sigSaved.value = false;
}

function closeProposalWizard() {
  proposalWizardOpen.value = false;
  wizardStep.value = 'info';
  pasteTargetId.value = null;
  siteQuantities.value = {};
  sigSaved.value = false;
  sigLoaded.value = false;
  if (previewUrl.value) { window.URL.revokeObjectURL(previewUrl.value); previewUrl.value = null; }
  previewBlob.value = null;
  previewLoading.value = false;
  proposalForm.value = {
    client_name: '', attention: '', client_designation: '',
    attention_phone_code: '+60', attention_phone_local: '', reference: '',
    duration: 1, duration_label: '', normal_price: null, price_per_unit: null,
    quantity_size: '', sst_rate: 0.08, promo_until: '', re_line: '',
    print_mode: 'both',
    signatory_name: '', signatory_title: '',
    signatory_mobile_code: '+60', signatory_mobile_local: '',
    signatory_label: '', signatory_signature: '',
  };
}

// ── Billboard overlay editor ──────────────────────────────────────────────────

const BILLBOARD_TYPES = ['Billboard', 'Temp Board', 'Lamp Post Bunting']
function isBillboardType(type) { return BILLBOARD_TYPES.includes(type) }

function openOverlayEditor(product) {
  overlayEditorProduct.value = product
  overlayOvl.value = { x: 0.28, y: 0.08, w: 0.16, h: 0.42 }
  overlayArtDataUrl.value = null
  overlayArtImage.value = null
  overlayHovered.value = false
  overlayDragState.value = null
  overlayBgImage.value = null
  overlayEditorOpen.value = true

  // Pre-load background image once — reused on every redraw (no flicker)
  const src = product?.site_photo_url
  if (src) {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload  = () => { overlayBgImage.value = img;  nextTick(_redraw) }
    img.onerror = () => { overlayBgImage.value = null; nextTick(_redraw) }
    img.src = src
  } else {
    nextTick(_redraw)
  }
}

function closeOverlayEditor() {
  overlayEditorOpen.value = false
  overlayEditorProduct.value = null
  overlayArtDataUrl.value = null
  overlayArtImage.value = null
  overlayBgImage.value = null
  overlayDragState.value = null
  overlayHovered.value = false
}

// Synchronous full redraw — uses only cached images, never starts new loads
function _redraw() {
  const canvas = overlayCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  const W = canvas.width, H = canvas.height
  ctx.clearRect(0, 0, W, H)
  if (overlayBgImage.value) {
    ctx.drawImage(overlayBgImage.value, 0, 0, W, H)
  } else {
    ctx.fillStyle = '#ddd'
    ctx.fillRect(0, 0, W, H)
    ctx.fillStyle = '#999'; ctx.font = '14px sans-serif'
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'
    ctx.fillText('No site photo', W / 2, H / 2)
  }
  _drawBillboard(ctx, W, H)
}
// Public alias so template can call it after nextTick
function drawOverlayCanvas() { _redraw() }

function _poleH(oh) { return Math.min(oh * 0.52, 90) }

function _drawBillboard(ctx, W, H) {
  const o = overlayOvl.value
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H
  const pw = Math.max(4, ow * 0.10)
  const ph = _poleH(oh)
  const pxL = ox + (ow - pw) / 2

  // ── Pole (black, slight left-highlight) ──
  ctx.fillStyle = 'rgba(0,0,0,0.25)'
  ctx.fillRect(pxL + 2, oy + oh + 2, pw, ph) // shadow
  const pGrad = ctx.createLinearGradient(pxL, 0, pxL + pw, 0)
  pGrad.addColorStop(0,   '#1a1a1a')
  pGrad.addColorStop(0.3, '#444')
  pGrad.addColorStop(0.7, '#222')
  pGrad.addColorStop(1,   '#111')
  ctx.fillStyle = pGrad
  ctx.fillRect(pxL, oy + oh, pw, ph)
  // subtle highlight
  ctx.fillStyle = 'rgba(255,255,255,0.10)'
  ctx.fillRect(pxL + pw * 0.15, oy + oh, pw * 0.2, ph)

  // ── Board shadow ──
  ctx.fillStyle = 'rgba(0,0,0,0.22)'
  ctx.fillRect(ox + 4, oy + 4, ow, oh)

  // ── Board fill (fully opaque yellow) ──
  if (overlayArtImage.value) {
    ctx.drawImage(overlayArtImage.value, ox, oy, ow, oh)
  } else {
    ctx.fillStyle = '#FFD64A'
    ctx.fillRect(ox, oy, ow, oh)
    // thin vertical texture lines
    ctx.strokeStyle = 'rgba(180,120,0,0.18)'
    ctx.lineWidth = 1
    for (let i = 1; i < 5; i++) {
      const lx = ox + (ow / 5) * i
      ctx.beginPath(); ctx.moveTo(lx, oy); ctx.lineTo(lx, oy + oh); ctx.stroke()
    }
  }

  // ── Board border ──
  ctx.strokeStyle = '#b07a00'
  ctx.lineWidth = 2
  ctx.strokeRect(ox, oy, ow, oh)

  // ── Selection / handles — only when hovered or actively dragging ──
  const active = overlayHovered.value || !!overlayDragState.value
  if (active) {
    // dashed selection outline
    ctx.strokeStyle = '#1d4ed8'
    ctx.lineWidth = 1.5
    ctx.setLineDash([4, 3])
    ctx.strokeRect(ox - 1, oy - 1, ow + 2, oh + 2)
    ctx.setLineDash([])
    // corner + mid-edge handles
    const s = 8
    const pts = [
      [ox, oy], [ox + ow / 2, oy], [ox + ow, oy],
      [ox, oy + oh / 2],            [ox + ow, oy + oh / 2],
      [ox, oy + oh], [ox + ow / 2, oy + oh], [ox + ow, oy + oh],
    ]
    pts.forEach(([hx, hy]) => {
      ctx.fillStyle = '#fff'
      ctx.fillRect(hx - s / 2, hy - s / 2, s, s)
      ctx.strokeStyle = '#1d4ed8'
      ctx.lineWidth = 1.5
      ctx.strokeRect(hx - s / 2, hy - s / 2, s, s)
    })
  }
}

function _getHandle(px, py, W, H) {
  const o = overlayOvl.value
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H
  const ht = 10
  const handles = [
    { key: 'nw', x: ox,            y: oy       }, { key: 'n',  x: ox + ow / 2, y: oy       },
    { key: 'ne', x: ox + ow,       y: oy       }, { key: 'w',  x: ox,           y: oy + oh / 2 },
    { key: 'e',  x: ox + ow,       y: oy + oh / 2 }, { key: 'sw', x: ox,        y: oy + oh  },
    { key: 's',  x: ox + ow / 2,   y: oy + oh  }, { key: 'se', x: ox + ow,     y: oy + oh  },
  ]
  return handles.find(h => Math.abs(px - h.x) < ht && Math.abs(py - h.y) < ht) || null
}

function _canvasPos(e, canvas) {
  const r = canvas.getBoundingClientRect()
  return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) }
}

function _isOverBoard(x, y, W, H) {
  const o = overlayOvl.value
  const ox = o.x * W - 6, oy = o.y * H - 6, ow = o.w * W + 12, oh = o.h * H + 12
  return x >= ox && x <= ox + ow && y >= oy && y <= oy + oh
}

function onCanvasMouseDown(e) {
  const canvas = overlayCanvas.value
  if (!canvas) return
  const { x, y } = _canvasPos(e, canvas)
  const W = canvas.width, H = canvas.height
  const o = overlayOvl.value
  const handle = _getHandle(x, y, W, H)
  if (handle) {
    overlayDragState.value = { type: 'resize', handle: handle.key, sx: x, sy: y, so: { ...o } }
    return
  }
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H
  if (x >= ox && x <= ox + ow && y >= oy && y <= oy + oh) {
    overlayDragState.value = { type: 'move', sx: x, sy: y, so: { ...o } }
  }
}

function onCanvasMouseMove(e) {
  const canvas = overlayCanvas.value
  if (!canvas) return
  const { x, y } = _canvasPos(e, canvas)
  const W = canvas.width, H = canvas.height
  const d = overlayDragState.value

  if (d) {
    const dx = (x - d.sx) / W, dy = (y - d.sy) / H
    const s = d.so
    if (d.type === 'move') {
      overlayOvl.value = { ...s, x: Math.max(0, Math.min(1 - s.w, s.x + dx)), y: Math.max(0, Math.min(1 - s.h, s.y + dy)) }
    } else {
      let nx = s.x, ny = s.y, nw = s.w, nh = s.h
      const h = d.handle
      if (h.includes('e')) nw = Math.max(0.06, s.w + dx)
      if (h.includes('s')) nh = Math.max(0.08, s.h + dy)
      if (h.includes('w')) { nx = s.x + dx; nw = Math.max(0.06, s.w - dx) }
      if (h.includes('n')) { ny = s.y + dy; nh = Math.max(0.08, s.h - dy) }
      overlayOvl.value = { x: nx, y: ny, w: nw, h: nh }
    }
    _redraw()
    return
  }

  // Hover detection (no drag active)
  const nowOver = _isOverBoard(x, y, W, H)
  if (nowOver !== overlayHovered.value) {
    overlayHovered.value = nowOver
    canvas.style.cursor = nowOver ? 'grab' : 'default'
    _redraw()
  }
}

function onCanvasMouseUp() {
  overlayDragState.value = null
  // Keep handles visible if still hovering
  _redraw()
}

function onCanvasMouseLeave() {
  overlayDragState.value = null
  if (overlayHovered.value) {
    overlayHovered.value = false
    const canvas = overlayCanvas.value
    if (canvas) canvas.style.cursor = 'default'
    _redraw()
  }
}

function onArtworkFileSelected(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    overlayArtDataUrl.value = e.target.result
    const img = new Image()
    img.onload  = () => { overlayArtImage.value = img; _redraw() }
    img.onerror = () => { overlayArtImage.value = null; _redraw() }
    img.src = e.target.result
  }
  reader.readAsDataURL(file)
}

function applyBillboardComposite() {
  const canvas = overlayCanvas.value
  if (!canvas || !overlayEditorProduct.value) return
  // Temporarily hide handles for the final composite
  overlayHovered.value = false
  overlayDragState.value = null
  _redraw()
  billboardComposites.value = {
    ...billboardComposites.value,
    [overlayEditorProduct.value.id]: canvas.toDataURL('image/jpeg', 0.92),
  }
  closeOverlayEditor()
}

function removeBillboardComposite(productId) {
  const copy = { ...billboardComposites.value }
  delete copy[productId]
  billboardComposites.value = copy
}

// ── end billboard overlay ──────────────────────────────────────────────────────

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
    const res = await api.post(`/v1/site-availability/products/${product.id}/photo`, fd);
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

const removePhotoModal = reactive({ open: false, kind: null, loading: false });
function openRemovePhotoModal(kind) { removePhotoModal.kind = kind; removePhotoModal.open = true; }
function closeRemovePhotoModal() { removePhotoModal.open = false; removePhotoModal.kind = null; removePhotoModal.loading = false; }

async function confirmRemovePhoto() {
  if (!selectedProduct.value || !removePhotoModal.kind) return;
  removePhotoModal.loading = true;
  error.value = '';
  try {
    await api.delete(`/v1/site-availability/products/${selectedProduct.value.id}/photo`, {
      data: { kind: removePhotoModal.kind },
    });
    applyPhotoUpdate(selectedProduct.value.id, removePhotoModal.kind, null, null);
    closeRemovePhotoModal();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove photo.';
    closeRemovePhotoModal();
  } finally {
    removePhotoModal.loading = false;
  }
}

async function removePhoto(kind) {
  if (!selectedProduct.value) return;
  openRemovePhotoModal(kind);
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
    const res = await api.post('/v1/site-availability', {
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
    showToast('Booking saved successfully');
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
  placeSearch.value = presets.site_name ?? '';
  showPlaceDrop.value = false;
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
  openRemoveBookingModal(row, booking);
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
    await api.put(`/v1/site-availability/products/${row.id}`, {
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
      const res = await api.put(`/v1/site-availability/bookings/${booking.id}`, {
        company_name: companyName,
        contact_id: booking.contact_id,
        start_date: booking.start_date || null,
        end_date: booking.end_date || null,
      });
      Object.assign(booking, res.data.data);
      return;
    }

    const res = await api.post('/v1/site-availability', {
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
    const res = await api.put(`/v1/site-availability/bookings/${booking.id}`, {
      company_name: booking.company_name,
      contact_id: booking.contact_id,
      start_date: next.start_date,
      end_date: next.end_date,
      year: nextYear ?? booking.year,
      month: nextMonth ?? booking.month,
    });
    const idx = row.bookings.findIndex(b => b.id === booking.id);
    if (idx !== -1) row.bookings.splice(idx, 1, { ...booking, ...res.data.data });
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
    await api.delete(`/v1/site-availability/bookings/${booking.id}`);
    row.bookings = row.bookings.filter((item) => item.id !== booking.id);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove booking.';
    load();
  }
}

const removeBookingModal = reactive({ open: false, row: null, booking: null, loading: false });
function openRemoveBookingModal(row, booking) { removeBookingModal.row = row; removeBookingModal.booking = booking; removeBookingModal.open = true; }
function closeRemoveBookingModal() { removeBookingModal.open = false; removeBookingModal.row = null; removeBookingModal.booking = null; removeBookingModal.loading = false; }

async function confirmRemoveBooking() {
  if (!removeBookingModal.row || !removeBookingModal.booking) return;
  removeBookingModal.loading = true;
  try {
    await deleteBooking(removeBookingModal.row, removeBookingModal.booking);
    closeCellMenu();
    closeRemoveBookingModal();
  } catch {
    closeRemoveBookingModal();
  } finally {
    removeBookingModal.loading = false;
  }
}

async function removeBooking(row, booking) {
  if (!booking) return;
  openRemoveBookingModal(row, booking);
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
    illumination: '',
    facing: '',
    state_city: '',
    coordinate: '',
    site_code: '',
    size: '',
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
    const isBb = registerForm.value.product_type !== 'Lamp Post Bunting'
    const res = await api.post('/v1/site-availability/products', {
      site_name:          registerForm.value.site_name.trim(),
      product_type:       registerForm.value.product_type,
      status:             registerForm.value.status,
      type:               registerForm.value.type,
      site_code:          registerForm.value.site_code.trim() || null,
      size:               registerForm.value.size.trim() || null,
      illumination:       isBb ? (registerForm.value.illumination || null) : null,
      facing:             isBb ? (registerForm.value.facing || null) : null,
      state_city:         registerForm.value.state_city.trim() || null,
      coordinate:         registerForm.value.coordinate.trim() || null,
      nearest_landmarks:  registerForm.value.nearest_landmarks.filter((lm) => lm.category || lm.place),
    });
    upsertRow(res.data.data);
    closeRegisterModal();
    if (showMapView.value) refreshMapMarkers();
    showToast('Product registered successfully');
  } catch (e) {
    const errors = e.response?.data?.errors;
    registerError.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to register product.';
  } finally {
    registerSaving.value = false;
  }
}

async function submitStagePendingProduct() {
  if (!registerForm.value.site_name.trim()) return;
  registerSaving.value = true;
  registerError.value = '';
  try {
    const isBb = registerForm.value.product_type !== 'Lamp Post Bunting'
    const res = await api.post('/v1/site-availability/products', {
      site_name:          registerForm.value.site_name.trim(),
      product_type:       registerForm.value.product_type,
      status:             registerForm.value.status,
      type:               registerForm.value.type,
      site_code:          registerForm.value.site_code.trim() || null,
      size:               registerForm.value.size.trim() || null,
      illumination:       isBb ? (registerForm.value.illumination || null) : null,
      facing:             isBb ? (registerForm.value.facing || null) : null,
      state_city:         registerForm.value.state_city.trim() || null,
      coordinate:         registerForm.value.coordinate.trim() || null,
      nearest_landmarks:  registerForm.value.nearest_landmarks.filter((lm) => lm.category || lm.place),
      is_pending:         true,
    });
    const newProduct = normalizeRow(res.data.data);
    rows.value.unshift(newProduct);
    closeRegisterModal();
    if (showMapView.value) refreshMapMarkers();
    showToast('Draft saved — opening proposal wizard');
    selectedProductIds.value = [newProduct.id];
    proposalForm.value.reference = makeDefaultReference();
    proposalForm.value.print_mode = 'both';
    error.value = '';
    sigLoaded.value = false;
    loadPreparedByProfile();
    wizardStep.value = 'info';
    proposalWizardOpen.value = true;
  } catch (e) {
    const errors = e.response?.data?.errors;
    registerError.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to stage product.';
  } finally {
    registerSaving.value = false;
  }
}

function printPdfForStagedProduct(product) {
  selectedProductIds.value = [product.id];
  proposalForm.value.reference = makeDefaultReference();
  proposalForm.value.include_site_sheets = true;
  error.value = '';
  sigLoaded.value = false;
  loadPreparedByProfile();
  wizardStep.value = 'info';
  proposalWizardOpen.value = true;
}

async function confirmProductDirect(product) {
  error.value = '';
  try {
    const res = await api.post(`/v1/site-availability/products/${product.id}/confirm`);
    const updated = normalizeRow(res.data.data);
    const idx = rows.value.findIndex((r) => r.id === updated.id);
    if (idx !== -1) rows.value[idx] = updated;
    if (selectedProduct.value?.id === updated.id) selectedProduct.value = updated;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to confirm product.';
  }
}

function openDiscardProductModalFor(product) {
  discardProductModal.product = product;
  discardProductModal.open = true;
}

const confirmingProduct = ref(false);

async function confirmPendingProduct() {
  if (!selectedProduct.value) return;
  confirmingProduct.value = true;
  error.value = '';
  try {
    const res = await api.post(`/v1/site-availability/products/${selectedProduct.value.id}/confirm`);
    const updated = normalizeRow(res.data.data);
    const idx = rows.value.findIndex((r) => r.id === updated.id);
    if (idx !== -1) rows.value[idx] = updated;
    selectedProduct.value = updated;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to confirm product.';
  } finally {
    confirmingProduct.value = false;
  }
}

const discardProductModal = reactive({ open: false, product: null, loading: false });

function openDiscardProductModal() {
  if (!selectedProduct.value?.is_pending) return;
  discardProductModal.product = selectedProduct.value;
  discardProductModal.open = true;
}

function closeDiscardProductModal() {
  discardProductModal.open = false;
  discardProductModal.product = null;
  discardProductModal.loading = false;
}

async function confirmDiscardProduct() {
  if (!discardProductModal.product) return;
  discardProductModal.loading = true;
  error.value = '';
  try {
    await api.delete(`/v1/site-availability/products/${discardProductModal.product.id}`);
    rows.value = rows.value.filter((r) => r.id !== discardProductModal.product.id);
    selectedProductIds.value = selectedProductIds.value.filter((id) => id !== discardProductModal.product.id);
    closeDiscardProductModal();
    closeProductDetail();
    if (showMapView.value) refreshMapMarkers();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to discard product.';
    closeDiscardProductModal();
  } finally {
    discardProductModal.loading = false;
  }
}

// --- Google Maps link parsing ---
function parseMapsLink(url) {
  // Normalise URL-encoded spaces after commas (Google search redirects use "lat,+lng")
  const u = url.replace(/,\+/g, ',').replace(/,\s/g, ',');
  let m = u.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/\/search\/(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/\/(-?\d{1,3}\.\d{4,}),(-?\d{1,3}\.\d{4,})/);
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
    const res = await api.post('/v1/site-availability/resolve-maps-url', { url });
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
function haversineKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// One merged Overpass query → categorise client-side.
// Avoids 9 parallel requests which quickly hit rate limits on the public endpoint.
async function fetchNearestLandmarks(lat, lng) {
  landmarkFetching.value = true;
  landmarkFetched.value = false;

  const r = 3000;
  const q = `[out:json][timeout:30];
(
  node["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  way["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  relation["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  node["amenity"="school"]["name"](around:${r},${lat},${lng});
  way["amenity"="school"]["name"](around:${r},${lat},${lng});
  node["place"~"^(neighbourhood|suburb|village|town)$"]["name"](around:${r},${lat},${lng});
  node["amenity"~"^(hospital|clinic)$"]["name"](around:${r},${lat},${lng});
  way["amenity"~"^(hospital|clinic)$"]["name"](around:${r},${lat},${lng});
  node["railway"~"^(station|halt)$"]["name"](around:${r},${lat},${lng});
  node["public_transport"="station"]["name"](around:${r},${lat},${lng});
  node["amenity"="fuel"]["name"](around:${r},${lat},${lng});
  node["amenity"~"^(university|college)$"]["name"](around:${r},${lat},${lng});
  way["amenity"~"^(university|college)$"]["name"](around:${r},${lat},${lng});
  node["amenity"="police"]["name"](around:${r},${lat},${lng});
  node["amenity"="townhall"]["name"](around:${r},${lat},${lng});
  node["office"="government"]["name"](around:${r},${lat},${lng});
);
out center 300;`;

  const CATEGORIES = [
    { category: 'Shopping Mall',        test: (t) => t.shop === 'mall' || t.shop === 'shopping_centre' },
    { category: 'School',               test: (t) => t.amenity === 'school' },
    { category: 'Residential Area',     test: (t) => ['neighbourhood','suburb','village','town'].includes(t.place) },
    { category: 'Hospital / Clinic',    test: (t) => t.amenity === 'hospital' || t.amenity === 'clinic' },
    { category: 'MRT / LRT Station',    test: (t) => t.railway === 'station' || t.railway === 'halt' || t.public_transport === 'station' },
    { category: 'Petrol Station',       test: (t) => t.amenity === 'fuel' },
    { category: 'University / College', test: (t) => t.amenity === 'university' || t.amenity === 'college' },
    { category: 'Police Station',       test: (t) => t.amenity === 'police' },
    { category: 'Government Office',    test: (t) => t.amenity === 'townhall' || t.office === 'government' },
  ];

  const ENDPOINTS = [
    'https://overpass-api.de/api/interpreter',
    'https://lz4.overpass-api.de/api/interpreter',
  ];

  let elements = [];
  for (const endpoint of ENDPOINTS) {
    try {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 28000);
      const res = await fetch(endpoint, { method: 'POST', body: q, signal: ctrl.signal });
      clearTimeout(timer);
      const data = await res.json();
      elements = data?.elements ?? [];
      break;
    } catch { /* try next endpoint */ }
  }

  registerForm.value.nearest_landmarks = CATEGORIES.map(({ category, test }) => {
    const best = elements
      .filter((e) => e.tags?.name && test(e.tags))
      .map((e) => {
        const elat = e.lat ?? e.center?.lat;
        const elng = e.lon ?? e.center?.lon;
        if (elat == null || elng == null) return null;
        return { name: e.tags.name, km: haversineKm(lat, lng, elat, elng) };
      })
      .filter(Boolean)
      .sort((a, b) => a.km - b.km)[0];
    return { category, place: best ? `${best.km.toFixed(1)}km — ${best.name}` : 'Not Found' };
  });

  landmarkFetched.value = true;
  landmarkFetching.value = false;
}

onUnmounted(() => {
  if (leafletMap) { leafletMap.remove(); leafletMap = null; }
  document.removeEventListener('paste', handleGlobalPaste)
  window.removeEventListener('resize', updateWrapWidth)
});

// --- Table view ---
const tableViewMode = ref('month');
const weekPage = ref(0); // kept for backwards-compat; unused in scroll mode
const ganttWrapRef = ref(null);
const wrapWidth = ref(0);

const weekColWidth = computed(() => {
  const w = wrapWidth.value;
  if (!w) return 110;
  // Match exactly the width of a month column: (available width − sticky left) ÷ 12
  return Math.max(80, Math.floor((w - 258) / 12));
});

// Total table width in week mode = sticky cols (38 + 220) + every week column.
// Giving the <table> an explicit width makes table-layout:fixed honour per-column
// widths and overflow the container (so it scrolls) instead of squishing to 100%.
const ganttTableWidth = computed(() =>
  258 + weekColumns.value.length * weekColWidth.value
);

function updateWrapWidth() {
  if (ganttWrapRef.value) wrapWidth.value = ganttWrapRef.value.clientWidth;
}

// All weeks for the selected year (Mon–Sun), starting from the Monday of the week containing Jan 1.
const weekColumns = computed(() => {
  const jan1 = new Date(year.value, 0, 1);
  const dow = jan1.getDay();
  const toMonday = dow === 0 ? -6 : 1 - dow;
  const firstMonday = new Date(jan1);
  firstMonday.setDate(jan1.getDate() + toMonday);
  const dec31 = new Date(year.value, 11, 31);

  const cols = [];
  for (let w = 0; w < 54; w++) {
    const start = new Date(firstMonday);
    start.setDate(firstMonday.getDate() + w * 7);
    if (start > dec31) break;
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    cols.push({ start, end });
  }
  return cols;
});

// Unified column list: month columns or week columns
const columns = computed(() => {
  if (tableViewMode.value === 'week') {
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return weekColumns.value.map(w => ({
      value: w.start.getTime(),
      short: `${w.start.getDate()}–${w.end.getDate()}`,
      monthShort: w.start.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase(),
      isToday: now >= w.start && now <= w.end,
    }));
  }
  return months;
});

// Month groups for the week-view sub-header row
const weekMonthGroups = computed(() => {
  if (tableViewMode.value !== 'week') return [];
  const cols = weekColumns.value;
  const groups = [];
  let current = null;
  cols.forEach(w => {
    const key = `${w.start.getFullYear()}-${w.start.getMonth()}`;
    const label = w.start.toLocaleDateString('en-GB', { month: 'short' }).toUpperCase()
      + ' ' + w.start.getFullYear();
    if (current && current.key === key) {
      current.span++;
    } else {
      current = { key, label, span: 1 };
      groups.push(current);
    }
  });
  return groups;
});

function bookingForWeek(row, week) {
  return row.bookings.find(b => {
    if (b.start_date && b.end_date) {
      return new Date(b.start_date) <= week.end && new Date(b.end_date) >= week.start;
    }
    // fallback: month overlap
    const wm1 = week.start.getMonth() + 1;
    const wm2 = week.end.getMonth() + 1;
    return Number(b.month) === wm1 || Number(b.month) === wm2;
  }) || null;
}

function weekSlots(row) {
  const cols = weekColumns.value;
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  const slots = [];
  let i = 0;
  while (i < cols.length) {
    const week = cols[i];
    const booking = bookingForWeek(row, week);
    const isToday = now >= week.start && now <= week.end;
    if (booking) {
      let span = 1;
      while (i + span < cols.length) {
        const nb = bookingForWeek(row, cols[i + span]);
        if (nb && nb.id === booking.id) span++;
        else break;
      }
      slots.push({ booked: true, booking, key: String(week.start.getTime()), span, month: week.start.getMonth() + 1, isToday });
      i += span;
    } else {
      slots.push({ booked: false, booking: null, key: String(week.start.getTime()), span: 1, month: week.start.getMonth() + 1, isToday });
      i++;
    }
  }
  return slots;
}

function slotsFor(row) {
  return tableViewMode.value === 'week' ? weekSlots(row) : monthSlots(row);
}

function isBookingExpired(booking) {
  if (!booking?.end_date) return false;
  return new Date(booking.end_date + 'T23:59:59') < new Date();
}

// Auto-scroll to today's column when switching to week view
watch(tableViewMode, (mode) => {
  if (mode === 'week') nextTick(scrollToToday);
});

function scrollToToday() {
  const el = document.querySelector('[data-is-today]');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

function tableNavShift(dir) {
  changeYear(dir);
}

function monthSlots(row) {
  const slots = [];
  let i = 0;
  while (i < months.length) {
    const month = months[i].value;
    const booking = bookingFor(row, month);
    if (booking) {
      let span = 1;
      let lastBooking = booking;
      while (i + span < months.length) {
        const next = bookingFor(row, months[i + span].value);
        if (next && next.company_name === booking.company_name) {
          lastBooking = next;
          span++;
        } else break;
      }
      // Merge end_date across the span so duration reflects the full period
      const mergedBooking = span > 1 ? { ...booking, end_date: lastBooking.end_date } : booking;
      slots.push({ booked: true, booking: mergedBooking, month, span, key: String(month) });
      i += span;
    } else {
      slots.push({ booked: false, booking: null, month, span: 1, key: String(month) });
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

onMounted(() => {
  load()
  document.addEventListener('paste', handleGlobalPaste)
  nextTick(updateWrapWidth)
  window.addEventListener('resize', updateWrapWidth)
});
</script>

<style scoped>
.page { padding: 28px 32px; color: var(--text-1); }
.page-header { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 20px; }
.page-title { margin: 0 0 4px; font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; }
.page-subtitle { margin: 0; color: var(--text-3); font-size: 13.5px; }
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
/* Action bar */
.action-bar {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px;
  margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-end;
  gap: 14px; flex-wrap: wrap;
}
.action-bar-filters {
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; flex: 1; min-width: 260px;
}
.action-bar-actions {
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
}
.action-group-secondary { display: flex; gap: 8px; }
.action-group-primary { display: flex; gap: 8px; padding-left: 8px; border-left: 1px solid var(--border); }
.btn-search {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  background: var(--text-1); color: #fff;
}
.btn-search:hover { opacity: 0.88; }
.proposal-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 5px;
  background: rgba(255,255,255,0.25); border-radius: 999px; font-size: 11px; font-weight: 800;
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
.company-linked-badge {
  display: inline-flex; align-items: center; gap: 4px;
  margin-top: 5px; font-size: 10.5px; font-weight: 700; color: #16a34a;
  background: #dcfce7; border-radius: 999px; padding: 2px 8px;
}
.req-star { color: #dc2626; font-weight: 700; margin-left: 1px; }
.place-search { position: relative; }
.place-search input { width: 100%; }
.place-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 50;
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-md);
  max-height: 200px; overflow-y: auto;
}
.place-results button {
  width: 100%; min-height: 34px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 7px 12px;
  font-size: 12.5px; font-weight: 500; cursor: pointer; display: block;
}
.place-results button:last-child { border-bottom: none; }
.place-results button:hover { background: var(--primary-soft); color: var(--primary-text); }
.btn-add, .btn-dark, .btn-clear, .btn-proposal {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.4); }
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
.table-card {
  background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border);
  overflow: hidden; position: relative;
}

/* Pagination */
.pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-top: 1px solid var(--border-soft); background: var(--surface-2);
}
.pagination-info { font-size: 12px; color: var(--text-3); flex-shrink: 0; }
.pagination-btns { display: flex; align-items: center; gap: 3px; }
.page-nav {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%; color: var(--text-2);
  cursor: pointer; transition: background 0.12s;
}
.page-nav svg { width: 14px; height: 14px; }
.page-nav:hover:not(:disabled) { background: var(--primary-soft); }
.page-nav:disabled { opacity: 0.3; cursor: default; }
.page-num {
  min-width: 32px; height: 32px; padding: 0 6px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%; font-size: 12.5px; font-weight: 600;
  color: var(--text-2); cursor: pointer; transition: background 0.12s;
}
.page-num:hover { background: var(--primary-soft); }
.page-num--on { background: var(--primary); color: #fff; font-weight: 700; }
.page-ellipsis { width: 28px; text-align: center; color: var(--text-3); font-size: 13px; line-height: 32px; }
.table-wrap {
  background: var(--surface);
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
  position: sticky; top: 0; z-index: 5; border-bottom: 2px solid var(--border);
}
.gantt-table tbody td {
  height: 56px; padding: 0; vertical-align: middle; background: var(--surface);
}
.product-row:hover td { background: var(--surface-2); }
.product-row:hover .month-cell.booked .booking-bar { filter: brightness(0.96); }

/* Sticky checkbox + place columns */
.select-col {
  width: 38px; min-width: 38px; text-align: center;
  position: sticky; left: 0; z-index: 3; background: var(--surface);
}
.gantt-table thead .select-col,
.gantt-table thead .place-col { z-index: 7; background: var(--surface); }
.select-col input {
  appearance: none; -webkit-appearance: none;
  width: 16px; height: 16px; padding: 0; cursor: pointer;
  border: 1.5px solid var(--border); border-radius: 3px; background: var(--surface);
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
  position: sticky; left: 38px; z-index: 3; background: var(--surface);
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
.badge-product { background: #dbeafe; color: #5b21b6; }
.badge-type { background: #fef3c7; color: #92400e; }
.badge-status-existing { background: #dcfce7; color: #166534; }
.badge-status-raw-new { background: #dbeafe; color: #1e40af; }

/* Month header + cells — widths distributed by table-layout: fixed */
.gantt-table .month-th { /* width auto-distributed */ }
.month-cell {
  text-align: center; cursor: pointer; position: relative; z-index: 1;
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
.booking-bar-done { background: #f1f5f9 !important; border-left-color: #94a3b8 !important; opacity: 0.6; }
.booking-bar-done .booking-bar-company { color: #64748b !important; text-decoration: line-through; }
.booking-bar-done .booking-bar-duration { color: #94a3b8 !important; text-decoration: none; }
/* ── Week view header ──────────────────────────────────────────────── */
.month-group-row th { padding: 0; }
.month-group-spacer { background: var(--surface) !important; border-bottom: none !important; }
.month-group-th {
  padding: 5px 4px 4px;
  font-size: 9px; font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase;
  color: var(--text-2); background: var(--surface-2);
  border-bottom: 1px solid var(--border);
  text-align: center;
}
.week-th {
  padding: 5px 3px 4px !important;
  line-height: 1; white-space: nowrap; text-align: center;
}
.week-th-dates { display: block; font-size: 11px; font-weight: 800; color: var(--text-1); }
.week-th-month { display: block; font-size: 8.5px; font-weight: 600; color: var(--text-3); letter-spacing: 0.4px; margin-top: 2px; }
.week-th-today {
  background: var(--primary) !important;
  color: #fff !important;
  border-radius: 4px 4px 0 0;
}
.week-th-today .week-th-dates { color: #fff; }
.week-th-today .week-th-month { color: rgba(255,255,255,0.78); }
.week-cell-today { background: rgba(29,78,216,0.04) !important; }
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
  width: min(880px, 96vw); max-height: 88vh; background: var(--surface); color: var(--text-1);
  display: flex; flex-direction: column; overflow: hidden; border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,0.22);
}
/* Header — standardised entry-modal-head pattern */
.detail-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.detail-head-info h2 { margin: 0 0 6px; font-size: 15px; font-weight: 700; color: var(--text-1); line-height: 1.25; }
.detail-head-badges { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
/* Scrollable body */
.detail-body { padding: 20px; overflow: auto; display: flex; flex-direction: column; gap: 18px; flex: 1; }
/* Close button */
.detail-close {
  flex-shrink: 0; width: 30px; height: 30px; border: none; margin-top: 2px;
  border-radius: var(--radius-sm); background: var(--surface-2); color: var(--text-1);
  font-size: 20px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.detail-close:hover { background: var(--border); }
.detail-grid { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(280px, 1.15fr); gap: 18px; align-items: start; }
.detail-panel, .landmark-panel { min-width: 0; }
.detail-actions, .landmark-actions {
  min-height: 40px; background: var(--text-1); color: #ffffff; border: 1px solid var(--border);
  border-bottom: none; display: flex; align-items: center; justify-content: space-between;
  gap: 10px; padding: 7px 10px 7px 16px; font-size: 10px; font-weight: 900; text-transform: uppercase;
}
.detail-actions button, .landmark-actions button {
  height: 26px; border: none; border-radius: 5px; padding: 0 10px; font-size: 10px;
  font-weight: 900; cursor: pointer;
}
.detail-actions > button, .landmark-actions > button, .btn-save-detail, .btn-save-landmarks { background: #ffffff; color: var(--text-1); }
.btn-cancel-detail, .btn-cancel-landmarks { background: var(--text-2); color: #ffffff; }
.detail-actions button:disabled, .landmark-actions button:disabled { opacity: 0.6; cursor: not-allowed; }
.detail-edit-actions, .landmark-edit-actions { display: flex; align-items: center; gap: 6px; }
.detail-table, .landmark-table { width: 100%; min-width: 0; border-collapse: collapse; font-size: 11px; }
.detail-table th, .detail-table td, .landmark-table th, .landmark-table td {
  border: 1px solid var(--border); height: 40px; padding: 8px 10px; background: var(--surface); vertical-align: middle;
}
.detail-table th, .landmark-table th { width: 42%; text-transform: uppercase; font-size: 10px; font-weight: 900; text-align: center; }
.detail-table td, .landmark-table td { font-weight: 750; text-align: center; line-height: 1.3; }
.detail-table a { color: var(--primary); font-weight: 700; text-decoration: underline; }
.detail-table input, .landmark-table input {
  width: 100%; min-height: 30px; border: 1px solid var(--border); border-radius: 5px;
  padding: 0 8px; color: var(--text-1); font-size: 11px; font-weight: 800; text-align: center;
  outline: none;
}
.detail-table input:focus, .landmark-table input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.map-link {
  display: inline-flex; align-items: center; justify-content: center; min-height: 36px;
  padding: 0 14px; border-radius: var(--radius-sm); background: var(--text-1); color: #ffffff; font-size: 12px;
  font-weight: 700; text-decoration: none;
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
  display: flex; flex-direction: column; gap: 8px;
  padding: 12px 20px 14px; border-top: 1px solid var(--border);
}
.entry-modal-foot-actions { display: flex; justify-content: flex-end; gap: 8px; }
.entry-validation-hint {
  margin: 0; font-size: 11.5px; font-weight: 600; color: #d97706;
  text-align: right; padding: 0 2px;
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
  border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius-sm); overflow: hidden;
  display: flex; flex-direction: column;
}
.photo-actions {
  background: var(--text-1); color: #fff; padding: 7px 10px;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  font-size: 10px; font-weight: 900; text-transform: uppercase;
}
.btn-upload, .btn-remove-photo {
  height: 26px; border: none; border-radius: 5px; padding: 0 10px; font-size: 10px;
  font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
}
.btn-upload { background: #fff; color: var(--text-1); }
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

/* Billboard overlay bar below photo preview */
.billboard-overlay-bar {
  display: flex; align-items: center; gap: 6px; padding: 6px 10px;
  background: var(--surface-2); border-top: 1px solid var(--border);
}
.billboard-overlay-hint {
  flex: 1; font-size: 9px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.4px; color: var(--text-3);
}
/* Billboard overlay pill button */
.btn-overlay-pill {
  height: 24px; border: 1.5px solid #1d4ed8; border-radius: 5px; padding: 0 10px;
  font-size: 9px; font-weight: 900; cursor: pointer; background: #eff6ff; color: #1d4ed8;
  display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
}
.btn-overlay-pill:hover { background: #dbeafe; }
/* Composite badge overlaid on photo preview */
.composite-tag {
  position: absolute; bottom: 6px; left: 6px;
  background: rgba(29,78,216,0.85); color: #fff; font-size: 9px; font-weight: 700;
  padding: 2px 6px; border-radius: 999px; pointer-events: none;
}
/* Wizard overlay button */
.btn-mini-overlay {
  background: #eff6ff; color: #1d4ed8; border: 1.5px solid #1d4ed8;
}
.btn-mini-overlay:hover { background: #dbeafe; }

/* Overlay editor modal */
.overlay-editor-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
}
.overlay-editor-modal {
  background: var(--surface); border-radius: var(--radius-lg); overflow: hidden;
  box-shadow: 0 24px 72px rgba(0,0,0,0.3); width: min(680px, 96vw);
  display: flex; flex-direction: column;
}
.overlay-editor-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 14px 16px 12px; border-bottom: 1px solid var(--border); gap: 12px;
}
.overlay-editor-title { margin: 0 0 2px; font-size: 14px; font-weight: 700; color: var(--text-1); }
.overlay-editor-sub { margin: 0; font-size: 11px; color: var(--text-3); }
.overlay-canvas-wrap {
  background: #111; display: flex; align-items: center; justify-content: center; padding: 8px;
}
.overlay-canvas {
  max-width: 100%; display: block; cursor: default; border-radius: 4px;
}
.overlay-editor-foot {
  display: flex; align-items: center; gap: 8px; padding: 12px 14px;
  border-top: 1px solid var(--border); flex-wrap: wrap;
}

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
.wizard-body { flex: 1; overflow: auto; padding: 20px 24px; }
.wizard-section { margin-bottom: 22px; }
.wizard-section:last-child { margin-bottom: 0; }
/* Signature pad */
.sig-pad-wrap { border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; background: var(--surface); }
.sig-pad-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.sig-pad-actions { display: flex; gap: 6px; align-items: center; }
.sig-btn {
  height: 28px; padding: 0 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2, var(--surface)); color: var(--text-2);
  cursor: pointer; display: inline-flex; align-items: center; white-space: nowrap;
}
.sig-btn input[type="file"] { display: none; }
.sig-btn:hover:not(:disabled) { background: var(--surface-3, var(--border)); }
.sig-btn-save { background: var(--primary); color: #fff; border-color: var(--primary); }
.sig-btn-save:hover:not(:disabled) { opacity: 0.88; }
.sig-btn-save:disabled { opacity: 0.55; cursor: default; }
.sig-pad-canvas-wrap { position: relative; }
.sig-pad-canvas {
  width: 100%; height: 110px; display: block; cursor: crosshair; touch-action: none;
  border: 1.5px dashed var(--border); border-radius: var(--radius-sm); background: #fff;
}
.sig-pad-hint {
  position: absolute; bottom: 7px; left: 50%; transform: translateX(-50%);
  font-size: 10px; color: var(--text-3); pointer-events: none; white-space: nowrap;
}

.wizard-signatory-presets { display: flex; gap: 6px; flex-wrap: wrap; }
.signatory-preset-btn {
  padding: 4px 12px; font-size: 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2);
  color: var(--text-2); cursor: pointer; transition: background 0.15s, border-color 0.15s;
}
.signatory-preset-btn:hover { background: var(--surface-3); }
.signatory-preset-btn.active {
  background: var(--primary); color: #fff; border-color: var(--primary);
}
.btn-save-prep {
  display: inline-flex; align-items: center; gap: 6px; width: fit-content;
  padding: 6px 14px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-save-prep:hover:not(:disabled) { background: var(--primary-soft); color: var(--primary); border-color: var(--primary); }
.btn-save-prep:disabled { opacity: 0.5; cursor: not-allowed; }
.profiles-admin-panel { margin-top: 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.profiles-admin-toggle {
  display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 14px;
  background: var(--surface-2); border: none; font-size: 12px; font-weight: 600;
  color: var(--text-2); cursor: pointer; text-align: left;
}
.profiles-admin-toggle:hover { color: var(--text-1); background: var(--border-soft); }
.toggle-chevron { transition: transform 0.2s; margin-left: auto; flex-shrink: 0; }
.toggle-chevron.open { transform: rotate(180deg); }
.profiles-admin-body { border-top: 1px solid var(--border); }
.profiles-loading, .profiles-empty { padding: 12px 14px; font-size: 13px; color: var(--text-3); font-style: italic; margin: 0; }
.profile-admin-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; border-bottom: 1px solid var(--border-soft);
}
.profile-admin-row:last-child { border-bottom: none; }
.profile-admin-name { font-size: 13px; font-weight: 600; color: var(--text-1); }
.profile-admin-meta { font-size: 11px; color: var(--text-3); margin-top: 2px; }
.profile-admin-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-left: 12px; }
.badge-active-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #15803d; }
.btn-set-active {
  padding: 4px 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-sm); cursor: pointer;
  background: var(--primary-soft); color: var(--primary); border: 1px solid var(--primary);
  transition: background 0.15s, color 0.15s;
}
.btn-set-active:hover { background: var(--primary); color: #fff; }
.wizard-section-head {
  font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.7px;
  color: var(--text-1); margin-bottom: 12px; padding: 0 0 9px 10px;
  border-bottom: 1.5px solid var(--border); border-left: 3px solid var(--primary);
}
.field-hint {
  font-size: 10.5px; font-weight: 500; color: var(--text-3);
  text-transform: none; letter-spacing: 0; margin-left: 4px;
}
.wizard-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.wizard-grid .field.full { grid-column: 1 / -1; }
.wizard-grid .field.full.inline-checkbox label {
  display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: var(--text-1);
  text-transform: none; letter-spacing: 0;
}
.wizard-grid .field.full.inline-checkbox input { width: auto; height: auto; }
.print-mode-group {
  display: flex; gap: 0; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;
}
.print-mode-btn {
  flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px 12px; border: none; border-right: 1px solid var(--border);
  background: var(--surface); color: var(--text-2); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.print-mode-btn:last-child { border-right: none; }
.print-mode-btn:hover { background: var(--surface-2); color: var(--text-1); }
.print-mode-btn.active { background: var(--primary); color: var(--primary-on); }
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
.wizard-pcs-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; flex-wrap: wrap; }
.wizard-pcs-field { display: flex; align-items: center; gap: 5px; }
.wizard-pcs-label { font-size: 11px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.wizard-pcs-input { width: 60px; padding: 3px 6px; font-size: 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--surface-2); color: var(--text-1); }
.wizard-pcs-unit { font-size: 11px; color: var(--text-3); }
.wizard-pcs-preview { font-size: 11px; color: var(--primary); font-weight: 700; margin-left: auto; white-space: nowrap; }
.wizard-photo-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.wizard-photo-cell { display: flex; flex-direction: column; gap: 6px; }
.wizard-photo-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: var(--text-2); }
.wizard-photo-frame {
  height: 120px; background: var(--surface); border: 1px dashed var(--border); border-radius: 6px;
  display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;
}
.wizard-photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
.paste-drop-zone { transition: border-color 0.15s, background 0.15s; }
.paste-drop-zone:hover { border-color: var(--primary); background: var(--surface-2); }
.paste-drop-zone.paste-active { border: 2px solid var(--primary); background: color-mix(in srgb, var(--primary) 6%, var(--surface)); }
.paste-ready-tag {
  position: absolute; top: 4px; right: 4px; background: var(--primary); color: #fff;
  font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 999px;
}
.btn-mini {
  height: 28px; border: 1px solid var(--border); background: var(--surface); color: var(--text-1);
  border-radius: 5px; padding: 0 10px; font-size: 11px; font-weight: 900; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
}
.btn-mini input { display: none; }
.btn-mini:hover { background: #f1f5f9; }
.review-body { padding: 16px 20px; }
.review-topbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 14px;
}
.review-site-count {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 700; color: var(--text-2);
  white-space: nowrap;
}

/* PDF preview row */
.pdf-preview-row {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--radius);
  background: var(--surface-2); margin-bottom: 12px;
}
.pdf-preview-status {
  display: flex; align-items: center; gap: 8px;
  font-size: 12.5px; color: var(--text-2);
}
.pdf-preview-status-text { font-weight: 600; }
.pdf-preview-actions { display: flex; align-items: center; gap: 8px; }
.btn-open-preview {
  display: inline-flex; align-items: center; gap: 6px;
  height: 34px; padding: 0 14px; border: none;
  border-radius: var(--radius-sm); background: var(--primary); color: #fff;
  font-size: 13px; font-weight: 700; cursor: pointer;
}
.btn-open-preview:hover { opacity: 0.88; }
.btn-open-preview:disabled { opacity: 0.45; cursor: default; }
.btn-refresh-preview {
  display: inline-flex; align-items: center; gap: 5px;
  height: 34px; padding: 0 12px; border: 1px solid var(--border);
  border-radius: var(--radius-sm); background: var(--surface);
  color: var(--text-2); font-size: 12px; font-weight: 700; cursor: pointer;
}
.btn-refresh-preview:hover { background: var(--surface-2); }
.btn-refresh-preview:disabled { opacity: 0.5; cursor: default; }
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 0.9s linear infinite; }

.review-sites { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 5px; }
.review-sites li {
  display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center;
  padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
  font-size: 12px;
}
.photo-status { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
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
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-register:hover { opacity: 0.88; }
.btn-map-toggle {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  background: var(--surface); color: var(--text-2); white-space: nowrap;
  display: inline-flex; align-items: center; gap: 6px;
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
.detail-map-links { display: flex; gap: 8px; flex-wrap: wrap; }
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
.field-loc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
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
.register-modal-footer { display: flex; flex-direction: column; gap: 10px; padding: 12px 20px 16px; border-top: 1px solid var(--border); flex-shrink: 0; }
.register-footer-note { margin: 0; font-size: 11.5px; color: var(--text-3); line-height: 1.5; }
.register-footer-note strong { color: var(--text-2); }
.register-footer-actions { display: flex; justify-content: flex-end; gap: 8px; }
.location-input-wrap { position: relative; display: flex; align-items: center; gap: 8px; }
.location-input-wrap input { flex: 1; min-width: 0; width: 100%; box-sizing: border-box; }
.location-status { font-size: 11px; font-weight: 700; color: var(--primary); white-space: nowrap; display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.maps-link-error { margin-top: 5px; font-size: 11px; font-weight: 700; color: #dc2626; }
.input-error-border { border-color: #dc2626 !important; }
.field-error { margin: 3px 0 0; font-size: 11.5px; color: #dc2626; font-weight: 500; }
.promo-date-input { cursor: pointer; }
.phone-input {
  display: flex; align-items: stretch; height: 38px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); overflow: hidden;
}
.phone-input:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.phone-input.input-error-border { border-color: #dc2626 !important; box-shadow: none !important; }
.phone-input select, .phone-input input {
  border: none !important; border-radius: 0 !important; box-shadow: none !important;
  height: 100% !important; outline: none;
}
.phone-code-select {
  border-right: 1.5px solid var(--border) !important;
  background: var(--surface-2) !important;
  padding: 0 6px 0 9px; font-size: 12px; font-weight: 700; color: var(--text-1);
  cursor: pointer; flex-shrink: 0; width: 80px;
}
.phone-local-input {
  background: transparent !important; flex: 1;
  padding: 0 12px; font-size: 13px; color: var(--text-1); min-width: 0;
}
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
.lm-place-input {
  width: 100%; min-height: 30px; border: 1.5px solid transparent; border-radius: 4px;
  padding: 0 8px; font-size: 12px; font-weight: 600; color: var(--text-1);
  background: transparent; outline: none; transition: border-color 0.15s;
}
.lm-place-input:hover { border-color: var(--border); background: var(--surface); }
.lm-place-input:focus { border-color: var(--primary); background: var(--surface); box-shadow: 0 0 0 3px var(--primary-soft); }
.lm-place-input::placeholder { color: var(--text-3); font-style: italic; font-weight: 500; }
.lm-place-input.lm-not-found { color: var(--text-3); font-style: italic; }
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

/* Staged for Review section */
.staged-section {
  background: var(--surface); border: 1.5px solid #fcd34d; border-radius: var(--radius);
  margin-bottom: 12px; overflow: hidden;
}
.staged-header {
  display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
  padding: 11px 16px; background: #fffbeb; cursor: pointer; user-select: none;
  border-bottom: 1px solid #fde68a;
}
.staged-header:hover { background: #fef3c7; }
.staged-header-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.staged-chevron { color: #92400e; transition: transform 0.2s; flex-shrink: 0; }
.staged-chevron-open { transform: rotate(90deg); }
.staged-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #78350f; }
.staged-count-chip {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px;
  background: #f59e0b; color: #fff; border-radius: 999px; font-size: 11px; font-weight: 800;
}
.staged-sub { margin: 0; font-size: 11.5px; color: #92400e; font-weight: 600; }
.staged-grid {
  display: flex; flex-direction: column;
  border-top: 1px solid #fde68a;
}
.staged-card {
  background: var(--surface); padding: 12px 16px;
  display: flex; flex-direction: row; align-items: center; gap: 14px;
  border-right: 1px solid #fde68a; border-bottom: 1px solid #fde68a;
}
.staged-card-avatar {
  width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 900; letter-spacing: -0.3px;
}
.staged-card-info { min-width: 0; flex: 1; }
.staged-card-name {
  font-size: 12.5px; font-weight: 700; color: var(--text-1); line-height: 1.35;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px;
}
.staged-card-badges { display: flex; flex-wrap: wrap; gap: 3px; margin-bottom: 4px; }
.staged-card-coord {
  display: flex; align-items: center; gap: 4px;
  font-size: 10.5px; font-weight: 600; color: var(--text-3);
}
.staged-card-no-coord { font-size: 10.5px; font-weight: 600; color: var(--text-3); font-style: italic; }
.staged-card-meta {
  display: flex; align-items: center; gap: 4px; flex-shrink: 0; white-space: nowrap;
  font-size: 10.5px; color: #92400e; font-weight: 600;
  padding: 0 16px; border-left: 1px solid #fde68a; border-right: 1px solid #fde68a;
}
.staged-card-actions {
  display: flex; gap: 6px; flex-shrink: 0;
}
.btn-staged-detail {
  height: 28px; padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-2); font-size: 11px; font-weight: 700; cursor: pointer;
}
.btn-staged-detail:hover { background: var(--surface-2); color: var(--text-1); }
.btn-staged-pdf {
  height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm);
  background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-staged-pdf:hover { background: #dbeafe; }
.btn-staged-confirm {
  height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm);
  background: #dcfce7; color: #166534; font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px; margin-left: auto;
}
.btn-staged-confirm:hover { background: #bbf7d0; }
.btn-staged-discard {
  height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm);
  background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-staged-discard:hover { background: #fecaca; }

/* Pending product states */
.badge-pending { background: #fef3c7; color: #92400e; border-color: #fcd34d; }

/* Pending banner inside product detail modal */
.pending-banner {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 10px; background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px;
}
.pending-banner-text {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 700; color: #92400e;
}
.pending-banner-actions { display: flex; gap: 8px; }
.btn-confirm-product {
  height: 30px; padding: 0 12px; border: none; border-radius: var(--radius-sm);
  background: #16a34a; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer;
}
.btn-confirm-product:hover:not(:disabled) { background: #15803d; }
.btn-confirm-product:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-discard-product {
  height: 30px; padding: 0 12px; border: none; border-radius: var(--radius-sm);
  background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 700; cursor: pointer;
}
.btn-discard-product:hover { background: #fecaca; }

/* Stage & Print PDF button */
.btn-stage-pdf {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  background: #f59e0b; color: #fff;
}
.btn-stage-pdf:hover:not(:disabled) { background: #d97706; }
.btn-stage-pdf:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { align-items: stretch; flex-direction: column; }
  .field, .field.company-field, .field.place-field, .field.small { width: 100%; min-width: 0; }
  .btn-add, .btn-dark, .btn-clear, .btn-proposal, .btn-search { width: 100%; justify-content: center; }
  .action-group-secondary, .action-group-primary { flex-direction: column; width: 100%; }
  .action-group-primary { border-left: none; border-top: 1px solid var(--border); padding-left: 0; padding-top: 8px; }
  .staged-card { flex-wrap: wrap; }
  .staged-card-meta { border: none; padding: 0; }
  .detail-body { padding: 14px 12px; }
  .detail-grid { grid-template-columns: 1fr; }
  .photo-grid { grid-template-columns: 1fr; }
  .wizard-grid, .wizard-photo-row, .wizard-review { grid-template-columns: 1fr; }
  .register-form-grid { grid-template-columns: 1fr; }
  .leaflet-map { height: 320px; }
}

/* ── Confirm modal ── */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 3000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-3); line-height: 1; padding: 0; }
.conf-close:hover { color: var(--text-1); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete { height: 38px; padding: 0 18px; background: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-delete:hover:not(:disabled) { background: #b91c1c; }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }

/* Toast notification */
.toast-msg {
  position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
  background: #15803d; color: #fff; padding: 10px 22px;
  border-radius: var(--radius); box-shadow: 0 6px 24px rgba(0,0,0,0.22);
  font-size: 13px; font-weight: 600; z-index: 9999; pointer-events: none;
  white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(6px); }
</style>
