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
      <div class="action-bar-main">
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
          <button class="btn-ghost-action" @click="load">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search
          </button>
          <button class="btn-ghost-action" @click="clearFilters">Clear</button>
        </div>
        <div class="action-bar-actions">
          <button type="button" class="btn-map-toggle" :class="{ active: showMapView }" @click="toggleMapView">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            {{ showMapView ? 'Table View' : 'Map View' }}
          </button>
          <button type="button" class="btn-ghost-action" @click="openRegisterModal">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Register Product
          </button>
          <button class="btn-add" @click="openEntryModal()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Booking
          </button>
        </div>
      </div>

      <div class="action-bar-landmark">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 14 8 14s8-8.75 8-14a8 8 0 0 0-8-8z"/></svg>
        <span class="landmark-label">Near a Place</span>
        <input v-model="landmarkQuery" class="landmark-query-input" placeholder="e.g. KLCC, Sunway Pyramid" @keyup.enter="searchLandmark">
        <select v-model.number="landmarkRadiusKm" class="landmark-radius-select" aria-label="Search radius">
          <option :value="1">1 km</option>
          <option :value="3">3 km</option>
          <option :value="5">5 km</option>
          <option :value="10">10 km</option>
          <option :value="15">15 km</option>
        </select>
        <button class="btn-ghost-action" :disabled="landmarkSearching || !landmarkQuery.trim()" @click="searchLandmark">
          {{ landmarkSearching ? 'Finding…' : 'Find Nearby' }}
        </button>
      </div>
    </div>

    <!-- Landmark search results banner -->
    <div v-if="nearbyMode" class="landmark-banner">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 14 8 14s8-8.75 8-14a8 8 0 0 0-8-8z"/></svg>
      <span>{{ displayRows.length }} site(s) within {{ landmarkRadiusKm }}km of "{{ landmarkResult.label }}"</span>
      <button type="button" class="landmark-banner-clear" @click="clearLandmarkSearch">Clear</button>
    </div>

    <!-- Contextual selection bar — only appears once sites are ticked -->
    <Transition name="selection-bar">
      <div v-if="selectedProductIds.length > 0" class="selection-bar">
        <div class="selection-bar-info">
          <span class="selection-bar-count">{{ selectedProductIds.length }}</span>
          <span>site{{ selectedProductIds.length === 1 ? '' : 's' }} selected</span>
          <button type="button" class="selection-bar-clear" @click="selectedProductIds = []">Clear selection</button>
        </div>
        <div class="selection-bar-actions">
          <button type="button" class="btn-compile" @click="openCompileModal">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Compile Sites
          </button>
          <button type="button" class="btn-proposal" @click="openProposalWizard">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Generate Proposal
          </button>
        </div>
      </div>
    </Transition>

    <!-- Staged for Review section — only shown when there are pending products -->
    <div v-if="pendingRows.length > 0" class="staged-section">
      <div class="staged-header" @click="stagedCollapsed = !stagedCollapsed">
        <div class="staged-header-left">
          <input
            type="checkbox"
            class="staged-select-all"
            :checked="allStagedSelected"
            @click.stop
            @change="toggleAllStaged"
            aria-label="Select all staged sites"
          >
          <svg class="staged-chevron" :class="{ 'staged-chevron-open': !stagedCollapsed }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          <span class="staged-title">Staged for Client Review</span>
          <span class="staged-count-chip">{{ pendingRows.length }}</span>
        </div>
        <p class="staged-sub">These sites are awaiting client approval. Confirm to add them to the availability list, or discard to remove.</p>
      </div>
      <div v-if="!stagedCollapsed" class="staged-grid">
        <article v-for="(product, i) in pendingRows" :key="product.id" class="staged-card" :class="{ 'staged-card-selected': selectedProductIds.includes(product.id) }">
          <input
            type="checkbox"
            class="staged-card-check"
            :checked="selectedProductIds.includes(product.id)"
            @change="toggleProductSelection(product.id)"
            :aria-label="`Select ${product.site_name}`"
          >
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
        <span class="legend-item"><span class="legend-dot" style="background:#7c3aed"></span>JKR Signage</span>
      </div>
    </div>

    <div class="table-card">
      <div class="table-title">
        <span class="table-title-name">Site Availability</span>
        <span class="table-title-count">{{ displayRows.length }} Product(s)</span>
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
              <input type="checkbox" :checked="allRowsSelected" :disabled="displayRows.length === 0" @change="toggleAllRows">
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
          <tr v-if="displayRows.length === 0">
            <td :colspan="columns.length + 2" class="empty-state">
              {{ nearbyMode ? `No sites found within ${landmarkRadiusKm}km of "${landmarkResult.label}".` : 'No confirmed products yet. Register a product to get started.' }}
            </td>
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
                  <button
                    type="button"
                    class="btn-row-delete"
                    :aria-label="`Delete ${row.site_name}`"
                    title="Delete site"
                    @click.stop="openDiscardProductModalFor(row)"
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                  </button>
                </div>
                <div class="place-cell-meta">
                  <span class="badge badge-product">{{ row.product_type }}</span>
                  <span class="badge" :class="`badge-status-${row.status.toLowerCase().replace(/ /g, '-')}`">{{ row.status }}</span>
                  <span class="badge badge-type">{{ row.type }}</span>
                  <span v-if="nearbyMode && landmarkDistances.has(row.id)" class="badge badge-distance">{{ formatDistance(landmarkDistances.get(row.id)) }}</span>
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
              <span
                v-else
                class="avail-tick"
                :class="{ 'avail-tick--past': slot.isPast }"
                :title="slot.isPast ? 'This month has passed — no booking on record' : 'Available'"
                aria-hidden="true"
              ></span>
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

    <div v-if="selectedProduct" class="modal-backdrop product-detail-backdrop">
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
          <div class="detail-head-actions">
            <button v-if="!selectedProduct.is_pending" type="button" class="btn-delete-site" @click="openDiscardProductModalFor(selectedProduct)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Delete Site
            </button>
            <button type="button" class="detail-close" aria-label="Close" @click="closeProductDetail">&times;</button>
          </div>
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
                      <span v-else>
                        {{ item.value }}
                        <span v-if="item.hint" class="detail-cell-hint">{{ item.hint }}</span>
                      </span>
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
                    <th class="landmark-col-remove"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(landmark, index) in activeLandmarkRows" :key="index">
                    <th class="landmark-col-cat">
                      <input v-if="editingLandmarks" v-model="landmarkForm[index].category" placeholder="e.g. Shopping Mall" aria-label="Landmark category">
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
                    <td v-if="editingLandmarks" class="landmark-col-remove">
                      <button type="button" class="btn-remove-landmark" :disabled="savingLandmarks" @click="removeLandmarkRow(index)" aria-label="Remove landmark row">&times;</button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <button v-if="editingLandmarks" type="button" class="btn-add-landmark" :disabled="savingLandmarks" @click="addLandmarkRow">+ Add Landmark</button>
            </div>
          </div>

          <!-- Contact (shown on this site's sheet) -->
          <div class="detail-panel contact-panel">
            <div class="detail-actions">
              <span>Contact</span>
              <button v-if="!editingContact" type="button" @click="startContactEdit">Edit</button>
              <div v-else class="detail-edit-actions">
                <button type="button" class="btn-save-detail" :disabled="savingContact" @click="saveContact">
                  {{ savingContact ? 'Saving...' : 'Save' }}
                </button>
                <button type="button" class="btn-cancel-detail" :disabled="savingContact" @click="cancelContactEdit">Cancel</button>
              </div>
            </div>
            <table class="detail-table">
              <tbody>
                <tr>
                  <th>Name</th>
                  <td>
                    <input v-if="editingContact" v-model="contactForm.contact_name" aria-label="Contact name" placeholder="e.g. NURUL ASYIQIN JAAFAR">
                    <span v-else>{{ selectedProduct.contact_name || 'Default (NURUL ASYIQIN JAAFAR)' }}</span>
                  </td>
                </tr>
                <tr>
                  <th>Mobile</th>
                  <td>
                    <input v-if="editingContact" v-model="contactForm.contact_mobile" aria-label="Contact mobile" placeholder="e.g. +6014-907 253">
                    <span v-else>{{ selectedProduct.contact_mobile || 'Default (+6014- 907 253)' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p class="contact-hint">Leave blank to use the company default contact on this site's sheet.</p>
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
              <div class="photo-preview paste-drop-zone"
                   :class="{ 'paste-active': pasteTargetId === 'modal_site_photo' }"
                   style="position:relative; cursor:pointer;"
                   @click="setPasteTarget('modal_site_photo')"
                   :title="pasteTargetId === 'modal_site_photo' ? 'Active — Ctrl+V to paste' : 'Click to set as paste target'">
                <img v-if="billboardComposites[selectedProduct?.id]" :src="billboardComposites[selectedProduct.id]" alt="Billboard composite">
                <img v-else-if="selectedProduct.site_photo_url" :src="selectedProduct.site_photo_url" alt="Site photo">
                <span v-else class="photo-placeholder">No site photo uploaded — click then Ctrl+V</span>
                <span v-if="billboardComposites[selectedProduct?.id]" class="composite-tag">Billboard Preview</span>
                <span v-if="pasteTargetId === 'modal_site_photo'" class="paste-ready-tag">Ready to paste</span>
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
              <div class="photo-preview paste-drop-zone"
                   :class="{ 'paste-active': pasteTargetId === 'modal_site_map_photo' }"
                   style="position:relative; cursor:pointer;"
                   @click="setPasteTarget('modal_site_map_photo')"
                   :title="pasteTargetId === 'modal_site_map_photo' ? 'Active — Ctrl+V to paste' : 'Click to set as paste target'">
                <img v-if="selectedProduct.site_map_photo_url" :src="selectedProduct.site_map_photo_url" alt="Map photo">
                <span v-else class="photo-placeholder">No map photo uploaded — click then Ctrl+V</span>
                <span v-if="pasteTargetId === 'modal_site_map_photo'" class="paste-ready-tag">Ready to paste</span>
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

    <!-- Compile Sites Modal — gallery for grabbing photos + Excel export for proposal copy/paste -->
    <Teleport to="body">
    <div v-if="compileModalOpen" class="modal-backdrop">
      <section class="compile-modal" role="dialog" aria-modal="true">
        <header class="compile-header">
          <div>
            <h2>Compile Sites</h2>
            <p>{{ selectedProducts.length }} site(s) selected — click a photo to preview it, use the <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> icon to save it, or export below</p>
          </div>
          <button type="button" class="detail-close" @click="closeCompileModal">&times;</button>
        </header>

        <div class="compile-body">
          <div v-if="selectedProducts.length === 0" class="compile-empty">No sites selected. Close this and tick sites from the table or map first.</div>
          <div v-else class="compile-grid">
            <article v-for="product in selectedProducts" :key="product.id" class="compile-card">
              <button type="button" class="compile-card-remove" :aria-label="`Remove ${product.site_name} from compile list`" title="Remove from list" @click="removeFromCompile(product.id)">&times;</button>
              <div class="compile-card-photos">
                <div v-if="product.site_photo_url" class="compile-photo-link">
                  <a class="compile-photo-view" :href="product.site_photo_url" target="_blank" rel="noopener" title="Preview full-size in a new tab">
                    <img :src="product.site_photo_url" alt="Site photo">
                  </a>
                  <span class="compile-photo-tag">Site Photo</span>
                  <a
                    class="compile-photo-download"
                    :href="product.site_photo_url"
                    :download="`${sanitizeFilename(product.site_name)}-site-photo.jpg`"
                    title="Download this photo"
                    aria-label="Download site photo"
                    @click.stop
                  ><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
                </div>
                <div v-else class="compile-photo-placeholder">No site photo</div>
                <div v-if="product.site_map_photo_url" class="compile-photo-link">
                  <a class="compile-photo-view" :href="product.site_map_photo_url" target="_blank" rel="noopener" title="Preview full-size in a new tab">
                    <img :src="product.site_map_photo_url" alt="Map photo">
                  </a>
                  <span class="compile-photo-tag">Map</span>
                  <a
                    class="compile-photo-download"
                    :href="product.site_map_photo_url"
                    :download="`${sanitizeFilename(product.site_name)}-map-photo.jpg`"
                    title="Download this photo"
                    aria-label="Download map photo"
                    @click.stop
                  ><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
                </div>
                <div v-else class="compile-photo-placeholder">No map photo</div>
              </div>
              <div class="compile-card-info">
                <div class="compile-card-name" :title="product.site_name">{{ product.site_name }}</div>
                <div class="compile-card-badges">
                  <span class="badge badge-product">{{ product.product_type }}</span>
                  <span class="badge" :class="`badge-status-${product.status.toLowerCase().replace(/ /g, '-')}`">{{ product.status }}</span>
                  <span class="badge" :class="compileAvailability(product).busy ? 'badge-busy' : 'badge-available'">{{ compileAvailability(product).label }}</span>
                </div>
                <div class="compile-card-meta">
                  <div v-if="product.size" class="compile-meta-row"><span class="compile-meta-label">Size</span><span class="compile-meta-value">{{ product.size }}</span></div>
                  <div v-if="product.state_city" class="compile-meta-row"><span class="compile-meta-label">Area</span><span class="compile-meta-value">{{ product.state_city }}</span></div>
                  <div v-if="product.coordinate" class="compile-meta-row"><span class="compile-meta-label">Coord</span><span class="compile-meta-value">{{ product.coordinate }}</span></div>
                </div>
              </div>
            </article>
          </div>
        </div>

        <footer class="compile-footer">
          <button type="button" class="btn-clear" @click="closeCompileModal">Close</button>
          <button
            v-if="compilePhotoCount > 0"
            type="button"
            class="btn-dark"
            :disabled="downloadingAllPhotos"
            title="Downloads every site/map photo across all selected sites, one file at a time"
            @click="downloadAllPhotos"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {{ downloadingAllPhotos ? 'Downloading…' : `Download All Photos (${compilePhotoCount})` }}
          </button>
          <button type="button" class="btn-add" :disabled="compileExporting || selectedProducts.length === 0" @click="exportCompiledSites">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {{ compileExporting ? 'Exporting…' : 'Export to Excel' }}
          </button>
        </footer>
      </section>
    </div>
    </Teleport>

    <!-- Add / Edit Booking Modal -->
    <BookingEntryModal
      v-model:form="form"
      :open="entryModalOpen"
      :product-options="productOptions"
      :place-options="placeOptions"
      :saving="saving"
      @close="closeEntryModal"
      @save="saveFromModal"
    />

    <!-- Cell Menu Modal (view / edit / delete booking, quick-add if empty) -->
    <Teleport to="body">
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
          <p class="cell-menu-hint">Change Start or End Date to extend or shorten this booking to any date.</p>
        </div>
        <div v-else-if="cellMenu.isPast" class="cell-menu-body cell-menu-empty cell-menu-empty--past">
          <span class="past-flag">Past month</span>
          <p>This month has already passed with no booking on record. You can still log one here for record-keeping — it won't show as active inventory.</p>
        </div>
        <div v-else class="cell-menu-body cell-menu-empty">
          <p>This month is currently <strong>available</strong> at this site.</p>
        </div>

        <footer class="cell-menu-foot">
          <button v-if="cellMenu.booking" type="button" class="btn-danger" @click="deleteFromCellMenu">Delete Booking</button>
          <button v-else type="button" class="btn-add" @click="addFromCellMenu">
            {{ cellMenu.isPast ? '+ Add Past Booking' : '+ Book This Month' }}
          </button>
        </footer>
      </section>
    </div>
    </Teleport>

    <!-- Register New Product Modal -->
    <RegisterProductModal
      v-model:form="registerForm"
      :open="showRegisterModal"
      :product-options="productOptions"
      :type-options="typeOptions"
      :status-options="statusOptions"
      :saving="registerSaving"
      :error="registerError"
      @close="closeRegisterModal"
      @register="submitRegisterProduct"
      @stage="submitStagePendingProduct"
    />

    <!-- Proposal Wizard Modal -->
    <Teleport to="body">
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
              <div class="field full">
                <SignaturePad
                  v-model:signature="proposalForm.signatory_signature"
                  v-model:loaded="sigLoaded"
                  v-model:saved="sigSaved"
                  @signature-saved="showToast('Signature saved')"
                />
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

          <!-- ── WHAT TO GENERATE ──────────────────────────────────────── -->
          <div class="output-settings-section">
            <div class="output-settings-label">What to generate</div>
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
          </div>

        </div>

        <!-- Step 2: Per-product photo check -->
        <div v-else-if="wizardStep === 'sheets'" class="wizard-body">

          <!-- ── SHEET SETTINGS ─────────────────────────────────────────── -->
          <div class="sheets-settings-bar">
            <div class="sheets-orient-row">
              <span class="sheets-settings-label">Sheet orientation</span>
              <div class="orientation-group">
                <button type="button"
                  :class="['orient-btn', { active: proposalForm.sheet_orientation === 'landscape' }]"
                  @click="proposalForm.sheet_orientation = 'landscape'"
                  title="Landscape — photos stacked, info on the right">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/></svg>
                  Landscape
                </button>
                <button type="button"
                  :class="['orient-btn', { active: proposalForm.sheet_orientation === 'portrait' }]"
                  @click="proposalForm.sheet_orientation = 'portrait'"
                  title="Portrait — full standalone page per site with client header">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/></svg>
                  Portrait
                </button>
              </div>
            </div>
            <div v-if="proposalForm.sheet_orientation === 'portrait'" class="addl-fee-field">
              <label class="addl-fee-label">Skin replacement fee</label>
              <input
                v-model="proposalForm.additional_fee"
                type="text"
                class="addl-fee-input"
                placeholder="RM500"
              >
            </div>
          </div>

          <p class="wizard-note">Confirm each site has a photo and map. Paste (Ctrl+V) an image anywhere on this step to upload it.</p>

          <!-- Photo completeness indicator -->
          <div class="wizard-completeness">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Photo coverage:
            <strong>{{ photoReadySites }} / {{ selectedProducts.length }}</strong> sites have both photos
            <span class="wizard-comp-badge" :class="photoReadySites === selectedProducts.length ? 'comp-ok' : 'comp-partial'">
              {{ photoReadySites === selectedProducts.length ? 'All ready' : `${selectedProducts.length - photoReadySites} missing` }}
            </span>
          </div>

          <div class="wizard-sites">
            <article v-for="product in selectedProducts" :key="product.id" class="wizard-site">
              <header>
                <div>
                  <strong>{{ product.site_name }}</strong>
                  <p>{{ product.product_type }} · {{ product.site_code || '—' }} · {{ product.coordinate || 'No coordinate' }}</p>
                </div>
                <button type="button" class="btn-mini" @click="openProductDetail(product)">Edit Details</button>
              </header>

              <!-- Sheet title inline edit — shows what will appear in red in the PDF header -->
              <div class="wizard-sheet-title-row">
                <label class="wizard-sheet-title-label">Sheet Title</label>
                <input
                  v-model="wizardSheetTitles[product.id]"
                  :placeholder="productTypeLabelFor(product.product_type)"
                  @blur="saveWizardSheetTitle(product)"
                  :disabled="savingSheetTitle[product.id]"
                  class="wizard-sheet-title-input"
                  :aria-label="`Sheet title for ${product.site_name}`"
                >
                <span class="wizard-sheet-title-live" :title="'Will print as: ' + effectiveSheetTitle(product)">
                  {{ effectiveSheetTitle(product) }}
                </span>
              </div>
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

          <!-- Summary bar — read-only confirmation of choices made in steps 1 & 2 -->
          <div class="review-topbar">
            <div class="review-summary">
              <span class="review-summary-chip">
                {{ proposalForm.print_mode === 'both' ? 'Proposal + Site Sheets' : proposalForm.print_mode === 'proposal_only' ? 'Proposal Only' : 'Site Sheets Only' }}
              </span>
              <span v-if="proposalForm.print_mode !== 'proposal_only'" class="review-summary-chip">
                {{ proposalForm.sheet_orientation === 'portrait' ? 'Portrait' : 'Landscape' }}
              </span>
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
              <span class="review-sheet-title" :title="'PDF header: ' + effectiveSheetTitle(p)">{{ effectiveSheetTitle(p) }}</span>
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
    </Teleport>
  </div>

  <ConfirmDialog
    :open="removePhotoModal.open"
    title="Remove Photo"
    subtitle="This cannot be undone."
    :loading="removePhotoModal.loading"
    confirm-label="Remove Photo"
    loading-label="Removing…"
    @close="closeRemovePhotoModal"
    @confirm="confirmRemovePhoto"
  >
    Remove this photo?
  </ConfirmDialog>

  <ConfirmDialog
    :open="discardProductModal.open"
    :title="discardProductModal.product?.is_pending ? 'Discard Pending Product' : 'Delete Site'"
    :subtitle="discardProductModal.product?.is_pending ? 'This will permanently delete the staged product.' : 'This will permanently remove this site, its photos and all its bookings.'"
    :loading="discardProductModal.loading"
    :confirm-label="discardProductModal.product?.is_pending ? 'Discard Product' : 'Delete Site'"
    :loading-label="discardProductModal.product?.is_pending ? 'Discarding…' : 'Deleting…'"
    @close="closeDiscardProductModal"
    @confirm="confirmDiscardProduct"
  >
    {{ discardProductModal.product?.is_pending ? 'Discard' : 'Delete' }} <strong>{{ discardProductModal.product?.site_name }}</strong>?
    It will be removed from the list{{ discardProductModal.product?.is_pending ? '.' : ', along with all its booking history.' }}
  </ConfirmDialog>

  <!-- Billboard Overlay Editor -->
  <BillboardOverlayEditor
    :open="overlayEditorOpen"
    :product="overlayEditorProduct"
    @close="closeOverlayEditor"
    @apply="applyBillboardComposite"
  />

  <ConfirmDialog
    :open="removeBookingModal.open"
    title="Remove Booking"
    subtitle="This cannot be undone."
    :loading="removeBookingModal.loading"
    confirm-label="Remove Booking"
    loading-label="Removing…"
    @close="closeRemoveBookingModal"
    @confirm="confirmRemoveBooking"
  >
    Remove booking for <strong>{{ removeBookingModal.booking?.company_name }}</strong>?
  </ConfirmDialog>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch, reactive } from 'vue';
import api from '../api.js';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import BillboardOverlayEditor from '../components/BillboardOverlayEditor.vue';
import BookingEntryModal from '../components/BookingEntryModal.vue';
import RegisterProductModal from '../components/RegisterProductModal.vue';
import SignaturePad from '../components/SignaturePad.vue';
import { getStoredUser } from '../utils/storage.js';

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
const productOptions = ref(['Billboard', 'Temp Board', 'Lamp Post Bunting', 'JKR Signage']);
const statusOptions = ref(['Existing', 'Raw New']);
const typeOptions = ref(['A1', 'A2', 'Ongoing', 'Reject']);
const selectedProduct = ref(null);
const selectedProductIds = ref([]);
const generatingProposal = ref(false);
const previewBlob = ref(null);
const previewUrl = ref(null);
const previewLoading = ref(false);
const wizardSheetTitles = ref({});
const savingSheetTitle  = ref({});
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
const _authUser        = getStoredUser();
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

// ── Signature pad (SignaturePad.vue) session-lifetime flags — reset on wizard open/close ──
const sigSaved  = ref(false);
const sigLoaded = ref(false);

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
  sheet_orientation: 'portrait',
  additional_fee: 'RM500',
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
const entryModalOpen = ref(false);
const cellMenu = ref({ open: false, row: null, month: null, booking: null, isPast: false });
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
const editingContact = ref(false);
const savingContact = ref(false);
const contactForm = ref({ contact_name: '', contact_mobile: '' });

// Map view state
const showMapView = ref(false);
const mapEl = ref(null);
let leafletMap = null;
let mapMarkers = [];
let landmarkMarker = null;
let landmarkCircle = null;

// Landmark ("near a place") search state
const landmarkQuery = ref('');
const landmarkRadiusKm = ref(5);
const landmarkSearching = ref(false);
const landmarkResult = ref(null); // { lat, lng, label }

// Compile Sites modal state
const compileModalOpen = ref(false);
const compileExporting = ref(false);

// Register Product modal state
const showRegisterModal = ref(false);
const registerSaving = ref(false);
const registerError = ref('');
const registerForm = ref({
  site_name: '',
  product_type: '',
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
const placeOptions = computed(() => [...confirmedRows.value].sort((a, b) => a.site_name.localeCompare(b.site_name)));
const confirmedRows = computed(() => rows.value.filter((r) => !r.is_pending));
const pendingRows = computed(() => rows.value.filter((r) => r.is_pending));

// ── Landmark ("near a place") distance filter — layers on top of confirmedRows ──
const nearbyMode = computed(() => !!landmarkResult.value);
const landmarkDistances = computed(() => {
  const map = new Map();
  if (!landmarkResult.value) return map;
  confirmedRows.value.forEach((row) => {
    const coord = parseCoordinate(row.coordinate);
    if (!coord) return;
    const distanceKm = haversineKm(landmarkResult.value.lat, landmarkResult.value.lng, coord.lat, coord.lng);
    if (distanceKm <= landmarkRadiusKm.value) map.set(row.id, distanceKm);
  });
  return map;
});
const displayRows = computed(() => {
  if (!nearbyMode.value) return confirmedRows.value;
  return confirmedRows.value
    .filter((r) => landmarkDistances.value.has(r.id))
    .sort((a, b) => landmarkDistances.value.get(a.id) - landmarkDistances.value.get(b.id));
});

// ── Pagination (client-side, table display only) ──
const currentPage = ref(1);
const rowsPerPage = ref(15);
const totalPages = computed(() => Math.max(1, Math.ceil(displayRows.value.length / rowsPerPage.value)));
const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value;
  return displayRows.value.slice(start, start + rowsPerPage.value);
});
const pageStartIndex = computed(() => (currentPage.value - 1) * rowsPerPage.value);
const pageRangeEnd = computed(() => Math.min(pageStartIndex.value + rowsPerPage.value, displayRows.value.length));
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
// Clamp current page if the row count shrinks (filter/delete/discard/landmark search)
watch([() => displayRows.value.length, rowsPerPage], () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});
// Live-resize the map circle/marker when the radius is adjusted mid-search
watch(landmarkRadiusKm, () => {
  if (landmarkResult.value && showMapView.value) nextTick(refreshMapMarkers);
});
const stagedCollapsed = ref(false);
const allRowsSelected = computed(() => pagedRows.value.length > 0 && pagedRows.value.every((row) => selectedProductIds.value.includes(row.id)));
const allStagedSelected = computed(() => pendingRows.value.length > 0 && pendingRows.value.every((p) => selectedProductIds.value.includes(p.id)));
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
  rows.push({
    label: 'Sheet Title',
    value: selectedProduct.value.sheet_type_label || '—',
    field: 'sheet_type_label',
    hint: !selectedProduct.value.sheet_type_label ? `Default: ${productTypeLabelFor(selectedProduct.value.product_type)}` : null,
  })
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
const photoReadySites = computed(() => selectedProducts.value.filter(p => p.site_photo && p.site_map_photo).length)

// Mirrors the PHP productTypeLabel() fallback so the UI shows the same label the PDF will use.
function productTypeLabelFor(type) {
  if (type === 'Temp Board') return 'Mini Billboard (Without Light)';
  if (type === 'Lamp Post Bunting') return 'Lamp Post Bunting';
  return type || 'Billboard';
}

// Returns the label that will actually appear in red in the PDF header.
// Checks the in-wizard editable title first (wizardSheetTitles), then the DB value, then the computed default.
function effectiveSheetTitle(product) {
  const inWizard = (wizardSheetTitles.value[product.id] ?? '').trim();
  if (inWizard) return inWizard;
  const saved = (product.sheet_type_label ?? '').trim();
  if (saved) return saved;
  return productTypeLabelFor(product.product_type);
}

async function saveWizardSheetTitle(product) {
  const newVal = (wizardSheetTitles.value[product.id] ?? '').trim();
  const oldVal = (product.sheet_type_label ?? '').trim();
  if (newVal === oldVal) return;
  savingSheetTitle.value = { ...savingSheetTitle.value, [product.id]: true };
  try {
    const res = await api.put(`/v1/site-availability/products/${product.id}`, {
      sheet_type_label: newVal || null,
    });
    const prepared = normalizeRow(res.data.data);
    const idx = rows.value.findIndex(r => r.id === prepared.id);
    if (idx !== -1) rows.value[idx] = prepared;
    wizardSheetTitles.value = { ...wizardSheetTitles.value, [product.id]: newVal };
  } catch (_) {
    showToast('Failed to save sheet title');
    wizardSheetTitles.value = { ...wizardSheetTitles.value, [product.id]: oldVal };
  } finally {
    savingSheetTitle.value = { ...savingSheetTitle.value, [product.id]: false };
  }
}

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

watch(() => proposalForm.value.sheet_orientation, () => {
  if (wizardStep.value === 'review') generatePreview();
});

watch(() => proposalForm.value.additional_fee, () => {
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

// --- Compile Sites modal ---
function openCompileModal() { compileModalOpen.value = true; }
function closeCompileModal() { compileModalOpen.value = false; }
function removeFromCompile(productId) { toggleProductSelection(productId); }

// Windows/macOS/Linux all reject some subset of \ / : * ? " < > | in a filename.
// Malaysian road names routinely contain "/" as part of the actual road number
// (e.g. "Jalan PJU 1a/41", "Along Jalan SS 4B/10") — 17 of 180 sites in this DB
// do today — so leaving it to the browser's own `download` sanitization produces
// unpredictable, differently-mangled results per browser instead of a filename
// that still clearly reads as the same site. Sanitize ourselves for a
// consistent, trackable result, and cap length so it stays scannable in a
// folder listing (longest current site_name is 95 chars).
function sanitizeFilename(name) {
  return (name || 'site')
    .replace(/[\\/:*?"<>|]/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/\s{2,}/g, ' ')
    .trim()
    .slice(0, 90);
}

const downloadingAllPhotos = ref(false);
const compilePhotoCount = computed(() => selectedProducts.value.reduce(
  (n, p) => n + (p.site_photo_url ? 1 : 0) + (p.site_map_photo_url ? 1 : 0), 0,
));

// Same document.createElement('a') + click() technique as exportCompiledSites()
// below, just looped — same-origin storage URLs so `download` reliably saves
// instead of navigating. Staggered because firing many programmatic downloads
// in the same tick makes some browsers silently drop all but the first one.
async function downloadAllPhotos() {
  const items = [];
  selectedProducts.value.forEach((p) => {
    const safeName = sanitizeFilename(p.site_name);
    if (p.site_photo_url) items.push({ url: p.site_photo_url, name: `${safeName}-site-photo.jpg` });
    if (p.site_map_photo_url) items.push({ url: p.site_map_photo_url, name: `${safeName}-map-photo.jpg` });
  });
  if (items.length === 0) return;
  downloadingAllPhotos.value = true;
  showToast(`Downloading ${items.length} photo${items.length === 1 ? '' : 's'}…`);
  for (const item of items) {
    const a = document.createElement('a');
    a.href = item.url;
    a.download = item.name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    await new Promise((resolve) => setTimeout(resolve, 350));
  }
  downloadingAllPhotos.value = false;
}

function compileAvailability(product) {
  // product.bookings is already scoped server-side to the currently browsed year
  // (see load()) — "now" only has a meaningful month within that scope when the
  // browsed year is the real current year. Comparing against real-world "now"
  // unconditionally made this always report "Available" while browsing any other
  // year, even for a site that's actually booked in the month being looked at.
  const now = new Date();
  const isCurrentYear = Number(year.value) === now.getFullYear();
  const targetMonth = isCurrentYear ? now.getMonth() + 1 : null;
  const booking = targetMonth
    ? (product.bookings || []).find((b) => b.year === Number(year.value) && b.month === targetMonth)
    : null;
  if (!booking) return { label: 'Available', busy: false };
  return { label: `Booked — ${booking.company_name}`, busy: true };
}

async function exportCompiledSites() {
  if (selectedProductIds.value.length === 0) return;
  compileExporting.value = true;
  try {
    const params = new URLSearchParams({ product_ids: selectedProductIds.value.join(',') });
    const token = localStorage.getItem('crm_token');
    const resp = await fetch(`/api/v1/site-availability/export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!resp.ok) throw new Error(`Server error ${resp.status}`);
    const blob = await resp.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Site_Compile_${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 500);
    showToast('Exported to Excel');
  } catch (err) {
    console.error('[Compile Export]', err);
    showToast('Failed to export. Please try again.');
  } finally {
    compileExporting.value = false;
  }
}

function toggleAllRows() {
  if (allRowsSelected.value) {
    selectedProductIds.value = selectedProductIds.value.filter((id) => !pagedRows.value.some((row) => row.id === id));
    return;
  }
  selectedProductIds.value = [...new Set([...selectedProductIds.value, ...pagedRows.value.map((row) => row.id)])];
}

function toggleAllStaged() {
  if (allStagedSelected.value) {
    selectedProductIds.value = selectedProductIds.value.filter((id) => !pendingRows.value.some((p) => p.id === id));
    return;
  }
  selectedProductIds.value = [...new Set([...selectedProductIds.value, ...pendingRows.value.map((p) => p.id)])];
}

function bookingFor(row, month) {
  return row.bookings.find((booking) => Number(booking.month) === Number(month));
}

// Many existing bookings were created with no explicit date range (just a year+month) —
// editing just one of start/end on those must not send the other as null, otherwise the
// backend never sees a complete range and silently skips the multi-month resync, leaving
// the newly "extended" months still showing as available.
function effectiveDates(booking) {
  const monthStr = `${booking.year}-${String(booking.month).padStart(2, '0')}`;
  const lastDay = new Date(booking.year, booking.month, 0).getDate();
  return {
    start: booking.start_date?.split('T')[0] || `${monthStr}-01`,
    end: booking.end_date?.split('T')[0] || `${monthStr}-${String(lastDay).padStart(2, '0')}`,
  };
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
  editingContact.value = false;
  contactForm.value = { contact_name: '', contact_mobile: '' };
  pasteTargetId.value = null;
}

function closeProductDetail() {
  selectedProduct.value = null;
  editingDetails.value = false;
  detailForm.value = emptyDetailForm();
  editingLandmarks.value = false;
  landmarkForm.value = [];
  editingContact.value = false;
  contactForm.value = { contact_name: '', contact_mobile: '' };
  pasteTargetId.value = null;
}

function startContactEdit() {
  if (!selectedProduct.value) return;
  contactForm.value = {
    contact_name:   selectedProduct.value.contact_name || '',
    contact_mobile: selectedProduct.value.contact_mobile || '',
  };
  editingContact.value = true;
}

function cancelContactEdit() {
  editingContact.value = false;
  contactForm.value = { contact_name: '', contact_mobile: '' };
}

async function saveContact() {
  if (!selectedProduct.value) return;
  savingContact.value = true;
  error.value = '';
  try {
    const res = await api.put(`/v1/site-availability/products/${selectedProduct.value.id}`, {
      contact_name:   contactForm.value.contact_name.trim() || null,
      contact_mobile: contactForm.value.contact_mobile.trim() || null,
    });
    const prepared = normalizeRow(res.data.data);
    const index = rows.value.findIndex((row) => row.id === prepared.id);
    if (index !== -1) rows.value[index] = prepared;
    selectedProduct.value = prepared;
    editingContact.value = false;
    contactForm.value = { contact_name: '', contact_mobile: '' };
    showToast('Contact details saved');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save contact details.';
    showToast(error.value);
  } finally {
    savingContact.value = false;
  }
}

function emptyDetailForm() {
  return {
    site_code: '',
    size: '',
    illumination: '',
    facing: '',
    sheet_type_label: '',
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
    sheet_type_label: selectedProduct.value.sheet_type_label || '',
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
      illumination:      detailForm.value.illumination || null,
      facing:            detailForm.value.facing || null,
      sheet_type_label:  detailForm.value.sheet_type_label.trim() || null,
      state_city:        detailForm.value.state_city.trim() || null,
      coordinate:  detailForm.value.coordinate.trim() || null,
    });
    const prepared = normalizeRow(res.data.data);
    const index = rows.value.findIndex((row) => row.id === prepared.id);
    if (index !== -1) rows.value[index] = prepared;
    selectedProduct.value = prepared;
    editingDetails.value = false;
    detailForm.value = emptyDetailForm();
    showToast('Product details saved');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save product details.';
    showToast(error.value);
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
    category: landmark.category,
    distance: landmark.distance || '',
    place: landmark.place === 'Not set' ? '' : landmark.place,
  }));
  editingLandmarks.value = true;
}

function cancelLandmarkEdit() {
  editingLandmarks.value = false;
  landmarkForm.value = [];
}

function addLandmarkRow() {
  landmarkForm.value.push({ category: '', distance: '', place: '' });
}

function removeLandmarkRow(index) {
  landmarkForm.value.splice(index, 1);
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
    showToast('Nearest landmarks saved');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save nearest landmarks.';
    showToast(error.value);
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
    sheet_orientation:     f.print_mode !== 'proposal_only' ? f.sheet_orientation : 'landscape',
    additional_fee:        (f.additional_fee || '').trim() || 'RM500',
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

// Bumped on every call so an in-flight request that resolves after a newer one
// started can recognise it's stale and discard itself — otherwise rapid toggling
// of orientation/print-mode/fee on the Review step can let an older, slower
// response overwrite a newer preview with one that no longer matches the form.
let previewRequestId = 0;

async function generatePreview() {
  if (selectedProductIds.value.length === 0) return;
  const requestId = ++previewRequestId;
  previewLoading.value = true;
  if (previewUrl.value) { window.URL.revokeObjectURL(previewUrl.value); previewUrl.value = null; }
  previewBlob.value = null;
  try {
    const res = await api.post('/v1/site-availability/proposal', buildProposalPayload(), { responseType: 'blob' });
    if (requestId !== previewRequestId) return; // superseded by a newer request
    previewBlob.value = new Blob([res.data], { type: 'application/pdf' });
    previewUrl.value = window.URL.createObjectURL(previewBlob.value);
  } catch (_) {
    // preview failure is non-fatal — user can still download directly
  } finally {
    if (requestId === previewRequestId) previewLoading.value = false;
  }
}

function openPreview() {
  if (previewUrl.value) window.open(previewUrl.value, '_blank');
}

async function generateProposal() {
  if (selectedProductIds.value.length === 0) return;
  if (hasStep1Errors.value) {
    error.value = 'Please fix the highlighted issues in Step 1 before generating.'
    showToast(error.value)
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
  if (!pasteTargetId.value) return
  const items = e.clipboardData?.items
  if (!items) return
  for (const item of items) {
    if (!item.type.startsWith('image/')) continue
    const file = item.getAsFile()
    if (!file) continue

    // Paste target set from the site detail modal (single product, no wizard involved)
    if (pasteTargetId.value.startsWith('modal_')) {
      if (!selectedProduct.value) return
      const field = pasteTargetId.value === 'modal_site_map_photo' ? 'site_map_photo' : 'site_photo'
      uploadPhoto(selectedProduct.value, field, file)
      pasteTargetId.value = null
      break
    }

    if (wizardStep.value !== 'sheets') return
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
  // Initialise inline sheet-title overrides for wizard Step 2
  const titles = {}
  selectedProducts.value.forEach(p => { titles[p.id] = p.sheet_type_label || '' })
  wizardSheetTitles.value = titles
  sigLoaded.value = false; // let SignaturePad re-fetch the saved signature for this session
  loadPreparedByProfile();
  proposalWizardOpen.value = true;
  sigSaved.value = false;
}

function closeProposalWizard() {
  proposalWizardOpen.value = false;
  // Clear the working selection so the Generate Proposal button only reflects
  // sites the user explicitly checks in the confirmed list — staged sites that
  // were auto-selected on registration/Print PDF must not leave the button "on".
  selectedProductIds.value = [];
  wizardStep.value = 'info';
  pasteTargetId.value = null;
  siteQuantities.value = {};
  wizardSheetTitles.value = {};
  savingSheetTitle.value = {};
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
    print_mode: 'both', sheet_orientation: 'portrait', additional_fee: 'RM500',
    signatory_name: '', signatory_title: '',
    signatory_mobile_code: '+60', signatory_mobile_local: '',
    signatory_label: '', signatory_signature: '',
  };
}

// ── Billboard overlay editor ──────────────────────────────────────────────────

const BILLBOARD_TYPES = ['Billboard', 'Temp Board', 'Lamp Post Bunting', 'JKR Signage']
function isBillboardType(type) { return BILLBOARD_TYPES.includes(type) }

function openOverlayEditor(product) {
  overlayEditorProduct.value = product
  overlayEditorOpen.value = true
}

function closeOverlayEditor() {
  overlayEditorOpen.value = false
  overlayEditorProduct.value = null
}

function applyBillboardComposite(dataUrl) {
  if (!overlayEditorProduct.value) return
  billboardComposites.value = {
    ...billboardComposites.value,
    [overlayEditorProduct.value.id]: dataUrl,
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

  const MAX_BYTES = 20 * 1024 * 1024; // 20 MB — matches server .htaccess / .user.ini
  if (file.size > MAX_BYTES) {
    error.value = `Photo is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is 20 MB.`;
    showToast(error.value);
    return;
  }

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
    showToast('Photo uploaded');
  } catch (e) {
    const status = e.response?.status;
    const errors = e.response?.data?.errors;
    if (errors) {
      error.value = Object.values(errors).flat().join(' ');
    } else if (status === 413 || !e.response) {
      error.value = 'Photo upload failed — the file is too large for the server. Try a smaller image (under 20 MB).';
    } else {
      error.value = 'The photo failed to upload. Please try again.';
    }
    showToast(error.value);
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
    showToast('Photo removed');
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove photo.';
    showToast(error.value);
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

async function addBooking() {
  if (!canAdd.value) return;
  saving.value = true;
  error.value = '';
  try {
    const res = await api.post('/v1/site-availability', {
      ...form.value,
      company_name: form.value.company_name.trim(),
      site_name: form.value.site_name.trim(),
      contact_id: form.value.contact_id,
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
    showToast('Booking saved successfully');
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Failed to save booking.';
    showToast(error.value);
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
  cellMenu.value = { open: true, row, month, booking: booking ?? null, isPast: isPastMonth(year.value, month) };
}

function closeCellMenu() {
  cellMenu.value = { open: false, row: null, month: null, booking: null, isPast: false };
}

// A month with no booking can still be in the past (viewing a prior year, or
// earlier months of the current year) — it was never "available" in any live
// sense, so it must not be presented the same way as a genuinely open slot.
function isPastMonth(y, m) {
  const now = new Date();
  const curYear = now.getFullYear();
  const curMonth = now.getMonth() + 1;
  return Number(y) < curYear || (Number(y) === curYear && Number(m) < curMonth);
}

function addFromCellMenu() {
  const { row, month } = cellMenu.value;

  const presets = {
    site_name: row.site_name,
    status: row.status,
    type: row.type,
    product_type: row.product_type,
    month,
    // Prefill the range to the month that was actually clicked so the form
    // doesn't silently default to today's month if left untouched.
    start_date: `${year.value}-${String(month).padStart(2, '0')}-01`,
    end_date: `${year.value}-${String(month).padStart(2, '0')}-${String(new Date(year.value, month, 0).getDate()).padStart(2, '0')}`,
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
  // Refresh local cellMenu.booking to latest — clone into a fresh object so Vue
  // always detects the change and resets the native date input's displayed value,
  // even when the update was rejected and the underlying booking is unchanged
  // (same object reference would otherwise be a same-reference no-op).
  const latest = bookingFor(row, month);
  cellMenu.value.booking = latest ? { ...latest } : null;
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

async function updateBookingDate(row, month, field, value) {
  const booking = bookingFor(row, month);
  if (!booking) return;
  if (!value) {
    error.value = 'Date cannot be cleared — pick a new date or delete the booking instead.';
    showToast(error.value);
    return;
  }

  const next = { ...effectiveDates(booking), [field === 'start_date' ? 'start' : 'end']: value };

  if (next.end < next.start) {
    error.value = 'End rent date must be same as or after start rent date.';
    showToast(error.value);
    return;
  }

  try {
    const res = await api.put(`/v1/site-availability/bookings/${booking.id}`, {
      company_name: booking.company_name,
      contact_id: booking.contact_id,
      start_date: next.start,
      end_date: next.end,
    });
    // The date range may now span different months than before (rows added/removed
    // on the server), so replace the whole set rather than patching one entry. It may
    // also land in a different year than the one currently displayed (e.g. extending a
    // December booking into January) — follow it either way.
    const nextYear = yearFromDate(value);
    error.value = '';
    if (nextYear && Number(year.value) !== nextYear) {
      year.value = nextYear;
      await load();
    } else {
      row.bookings = (res.data.data ?? []).filter((b) => Number(b.year) === Number(year.value));
    }
    showToast(`${booking.company_name}'s booking now runs ${formatDate(next.start)} → ${formatDate(next.end)}`);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to update rental date.';
    showToast(error.value);
    load();
  }
}

async function deleteBooking(row, booking) {
  try {
    await api.delete(`/v1/site-availability/bookings/${booking.id}`);
    // The server removes every row in the same booking_group (the whole multi-month
    // booking), so drop all of them here too — not just the clicked month.
    const groupId = booking.booking_group;
    row.bookings = row.bookings.filter((item) =>
      groupId ? item.booking_group !== groupId : item.id !== booking.id
    );
    return true;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to remove booking.';
    showToast(error.value);
    load();
    return false;
  }
}

const removeBookingModal = reactive({ open: false, row: null, booking: null, loading: false });
function openRemoveBookingModal(row, booking) { removeBookingModal.row = row; removeBookingModal.booking = booking; removeBookingModal.open = true; }
function closeRemoveBookingModal() { removeBookingModal.open = false; removeBookingModal.row = null; removeBookingModal.booking = null; removeBookingModal.loading = false; }

async function confirmRemoveBooking() {
  if (!removeBookingModal.row || !removeBookingModal.booking) return;
  removeBookingModal.loading = true;
  try {
    const ok = await deleteBooking(removeBookingModal.row, removeBookingModal.booking);
    if (ok) {
      closeCellMenu();
      closeRemoveBookingModal();
    }
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

// --- Landmark ("near a place") search ---
function haversineKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDistance(km) {
  if (km == null) return '';
  return km < 1 ? `${Math.round(km * 1000)}m away` : `${km.toFixed(1)}km away`;
}

async function searchLandmark() {
  const q = landmarkQuery.value.trim();
  if (!q) return;
  landmarkSearching.value = true;
  try {
    const res = await api.get('/v1/site-availability/geocode', { params: { q } });
    landmarkResult.value = {
      lat: parseFloat(res.data.lat),
      lng: parseFloat(res.data.lng),
      label: res.data.display_name || q,
    };
    currentPage.value = 1;
    if (showMapView.value) nextTick(refreshMapMarkers);
  } catch (err) {
    showToast(err.response?.data?.error || `Could not find a location for "${q}".`);
  } finally {
    landmarkSearching.value = false;
  }
}

function clearLandmarkSearch() {
  landmarkQuery.value = '';
  landmarkResult.value = null;
  currentPage.value = 1;
  if (showMapView.value) nextTick(refreshMapMarkers);
}

// --- Google Maps link parsing (also used by the Register Product modal) ---
function parseMapsLink(url) {
  // Normalise URL-encoded spaces after commas (Google search redirects use "lat,+lng")
  const u = url.replace(/,\+/g, ',').replace(/,\s/g, ',');

  // Street View share links encode the camera position as @lat,lng,<N>a,<heading>h,<tilt>t
  // — note the "a" (altitude/pitch) suffix, versus a normal map link's "z" (zoom level):
  // @lat,lng,17z. If the user reached that spot via a place search first, the URL can
  // still carry a leftover !3d!4d for that ORIGINAL place, which may sit meters away from
  // where the camera is actually pointed — so for a Street View link, @ is the one that
  // reflects what's actually being looked at, and must be checked before !3d!4d.
  let m = u.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+),\d+(?:\.\d+)?a,/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

  // Otherwise (a regular map/place link), the precise pinned-marker coordinate
  // (Google's !3d<lat>!4d<lng> data params) is more reliable than the @lat,lng viewport
  // center, which can drift from the actual pin if the map was panned/zoomed afterwards.
  m = u.match(/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
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
  if (productType === 'JKR Signage') return '#7c3aed';
  return '#dc2626';
}

function markerLabel(productType) {
  if (productType === 'Billboard') return 'BB';
  if (productType === 'Lamp Post Bunting') return 'LB';
  if (productType === 'JKR Signage') return 'JK';
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
  if (landmarkMarker) { landmarkMarker.remove(); landmarkMarker = null; }
  if (landmarkCircle) { landmarkCircle.remove(); landmarkCircle = null; }
  const bounds = [];

  rows.value.forEach((row) => {
    if (nearbyMode.value && !landmarkDistances.value.has(row.id)) return;

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

  if (landmarkResult.value) {
    const pinIcon = L.divIcon({
      html: `<div style="background:#f59e0b;width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.4)"><div style="width:8px;height:8px;border-radius:50%;background:white;transform:rotate(45deg)"></div></div>`,
      className: '', iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -30],
    });
    landmarkMarker = L.marker([landmarkResult.value.lat, landmarkResult.value.lng], { icon: pinIcon, zIndexOffset: 1000 })
      .addTo(leafletMap)
      .bindPopup(`<b>${landmarkResult.value.label}</b><br>Search center · ${landmarkRadiusKm.value}km radius`);
    landmarkCircle = L.circle([landmarkResult.value.lat, landmarkResult.value.lng], {
      radius: landmarkRadiusKm.value * 1000, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.08, weight: 1.5,
    }).addTo(leafletMap);
    bounds.push([landmarkResult.value.lat, landmarkResult.value.lng]);
  }

  if (bounds.length > 0) {
    leafletMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
  }
}

// --- Register Product modal ---
function openRegisterModal() {
  registerForm.value = {
    site_name: '',
    product_type: '',
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
  registerError.value = '';
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
    // Auto-add to the working selection (append, not replace — registering several
    // products in a row before compiling them should keep all of them selected)
    // so the site is immediately visible in Compile Sites / Generate Proposal
    // without the user having to remember to tick its checkbox afterward.
    selectedProductIds.value = [...selectedProductIds.value, res.data.data.id];
    closeRegisterModal();
    if (showMapView.value) refreshMapMarkers();
    showToast('Product registered — added to your selection');
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
    showToast('Product confirmed');
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to confirm product.';
    showToast(error.value);
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
    showToast('Product confirmed');
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to confirm product.';
    showToast(error.value);
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
    showToast('Product discarded');
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to discard product.';
    showToast(error.value);
  } finally {
    discardProductModal.loading = false;
  }
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

// A bare "YYYY-MM-DD" is parsed as UTC by `new Date()`, while `week.start`/`week.end`
// are built with the local-time constructor — an 8-hour (MY/UTC+8) mismatch that made
// a booking starting exactly on a week's last day fail the overlap check below. Parse
// the components directly so both sides are constructed the same (local) way.
function parseLocalDate(dateStr) {
  const [y, m, d] = dateStr.split('T')[0].split('-').map(Number);
  return new Date(y, m - 1, d);
}

function bookingForWeek(row, week) {
  return row.bookings.find(b => {
    if (b.start_date && b.end_date) {
      return parseLocalDate(b.start_date) <= week.end && parseLocalDate(b.end_date) >= week.start;
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
        // Prefer the exact booking_group so two separate, coincidentally-adjacent
        // bookings by the same company don't visually merge into one bar (matches
        // how weekSlots merges by exact booking id). Fall back to company_name only
        // for legacy rows saved before booking_group existed.
        const sameBooking = next && (booking.booking_group && next.booking_group
          ? next.booking_group === booking.booking_group
          : next.company_name === booking.company_name);
        if (sameBooking) {
          lastBooking = next;
          span++;
        } else break;
      }
      // Merge end_date across the span so duration reflects the full period
      const mergedBooking = span > 1 ? { ...booking, end_date: lastBooking.end_date } : booking;
      slots.push({ booked: true, booking: mergedBooking, month, span, key: String(month) });
      i += span;
    } else {
      slots.push({ booked: false, booking: null, month, span: 1, key: String(month), isPast: isPastMonth(year.value, month) });
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
  if (row.product_type === 'JKR Signage') return 'JK';
  return 'TB';
}

// A fixed, theme-invariant categorical palette (not semantic tokens) — rotates by row
// index purely to help visually distinguish adjacent rows, independent of light/dark mode.
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
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  margin-bottom: 12px; overflow: hidden;
}
.action-bar-main {
  padding: 12px 14px; display: flex; justify-content: space-between; align-items: flex-end;
  gap: 14px; flex-wrap: wrap;
}
.action-bar-filters {
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; flex: 1; min-width: 260px;
}
.action-bar-actions {
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
}
/* Secondary "near a place" search — visually set apart from the main filter row
   as an opt-in tool, not another field competing with Product/Search for attention. */
.action-bar-landmark {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 10px 14px; background: var(--surface-2); border-top: 1px solid var(--border); color: var(--text-2);
}
.action-bar-landmark > svg { flex-shrink: 0; color: var(--text-3); }
.landmark-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2); flex-shrink: 0;
}
.landmark-query-input {
  height: 34px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 12px;
  font-size: 12.5px; outline: none; background: var(--surface); color: var(--text-1); flex: 1; min-width: 180px;
}
.landmark-query-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.landmark-radius-select {
  height: 34px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 8px;
  font-size: 12px; font-weight: 600; outline: none; background: var(--surface); color: var(--text-1); flex-shrink: 0;
}
/* Shared ghost/secondary button — filter-apply and occasional-use actions
   (Search, Clear, Register Product, Find Nearby) all read at the same, de-emphasised
   weight so the one true primary action (Add Booking, blue) stands out. */
.btn-ghost-action {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  background: var(--surface-2); color: var(--text-2); white-space: nowrap;
}
.btn-ghost-action:hover:not(:disabled) { background: var(--border); color: var(--text-1); }
.btn-ghost-action:disabled { opacity: 0.5; cursor: not-allowed; }
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
.req-star { color: var(--danger); font-weight: 700; margin-left: 1px; }
.btn-add, .btn-dark, .btn-clear, .btn-proposal, .btn-compile {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.4); }
.btn-add:hover { background: var(--primary-hover); }
.btn-add:disabled, .btn-proposal:disabled, .btn-compile:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-dark { background: var(--text-1); color: var(--primary-on); }
.btn-dark:hover { opacity: 0.88; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }
/* Deliberately its own accent (not a theme token) — the one CTA that must read
   as distinct from the primary blue "Add" actions elsewhere on this page. */
.btn-proposal { background: #0f766e; color: var(--primary-on); }
.btn-compile { background: var(--primary-soft); color: var(--primary-text); }
.btn-compile:hover:not(:disabled) { background: var(--border); }
.error-msg {
  background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger-soft); border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600;
}
.landmark-banner {
  display: flex; align-items: center; gap: 8px;
  background: var(--info-soft); color: var(--info); border: 1px solid var(--info-soft); border-radius: var(--radius-sm);
  padding: 9px 14px; margin-bottom: 14px; font-size: 12.5px; font-weight: 700;
}
.landmark-banner svg { flex-shrink: 0; }
.landmark-banner span { flex: 1; }
.landmark-banner-clear {
  height: 26px; padding: 0 12px; border: none; border-radius: var(--radius-sm);
  background: var(--surface); color: var(--info); font-size: 11.5px; font-weight: 700; cursor: pointer; flex-shrink: 0;
}
.landmark-banner-clear:hover { filter: brightness(0.95); }

/* Contextual selection bar — replaces static, always-visible Compile/Proposal buttons.
   Only takes up space once the user has actually ticked sites to act on. */
.selection-bar {
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
  background: var(--primary-text); color: var(--primary-on); border-radius: var(--radius);
  padding: 10px 16px; margin-bottom: 12px;
}
.selection-bar-info { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
.selection-bar-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 22px; height: 22px; padding: 0 6px;
  background: var(--primary); border-radius: 999px; font-size: 12px; font-weight: 800;
}
.selection-bar-clear {
  border: none; background: transparent; color: rgba(255,255,255,0.7);
  font-size: 12px; font-weight: 600; text-decoration: underline; cursor: pointer; padding: 0;
}
.selection-bar-clear:hover { color: #fff; }
.selection-bar-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.selection-bar-enter-active, .selection-bar-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.selection-bar-enter-from, .selection-bar-leave-to { opacity: 0; transform: translateY(-6px); }
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
.page-num--on { background: var(--primary); color: var(--primary-on); font-weight: 700; }
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
.view-toggle button.active { background: var(--primary); color: var(--primary-on); }
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
  content: ""; width: 8px; height: 8px; transform: scale(0); background: var(--primary); border-radius: 1px;
}
.select-col input:checked::before { transform: scale(1); }
.select-col input:focus { outline: 2px solid rgba(37,99,235,0.32); outline-offset: 2px; }
.select-col input:disabled { border-color: var(--text-3); background: var(--border); cursor: not-allowed; }

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
  flex: 1; font-size: 12.5px; font-weight: 700; color: var(--text-1); line-height: 1.3;
  overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
  -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.btn-row-delete {
  flex-shrink: 0; width: 22px; height: 22px; margin-left: auto;
  display: flex; align-items: center; justify-content: center;
  border: none; background: var(--danger-soft); border-radius: var(--radius-sm);
  color: var(--danger); cursor: pointer; transition: background .12s, color .12s;
}
.btn-row-delete:hover { filter: brightness(0.93); }
.btn-row-delete:focus-visible { outline: 2px solid rgba(37,99,235,0.32); outline-offset: 1px; }
.place-cell-meta { display: flex; flex-wrap: wrap; gap: 4px; }
.badge {
  display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 999px;
  font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px;
  background: var(--surface-2); color: var(--text-2); border: 1px solid transparent;
}
.badge-product { background: var(--primary-soft); color: var(--primary-text); }
.badge-type { background: var(--warning-soft); color: var(--warning); }
.badge-status-existing { background: var(--success-soft); color: var(--success); }
.badge-status-raw-new { background: var(--primary-soft); color: var(--primary-text); }
.badge-distance { background: var(--info-soft); color: var(--info); }
.badge-available { background: var(--success-soft); color: var(--success); }
.badge-busy { background: var(--danger-soft); color: var(--danger); }

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
.booking-bar-blue { background: var(--primary-soft); border-left-color: var(--info); }
.booking-bar-orange { background: var(--warning-soft); border-left-color: var(--warning); }
.booking-bar-done { background: var(--surface-2) !important; border-left-color: var(--border) !important; opacity: 0.6; }
.booking-bar-done .booking-bar-company { color: var(--text-2) !important; text-decoration: line-through; }
.booking-bar-done .booking-bar-duration { color: var(--text-3) !important; text-decoration: none; }
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
  color: var(--primary-on) !important;
  border-radius: 4px 4px 0 0;
}
.week-th-today .week-th-dates { color: var(--primary-on); }
.week-th-today .week-th-month { color: rgba(255,255,255,0.78); }
.week-cell-today { background: rgba(29,78,216,0.04) !important; }
.booking-bar-company {
  font-size: 10.5px; font-weight: 800;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.booking-bar-blue .booking-bar-company { color: var(--info); }
.booking-bar-orange .booking-bar-company { color: var(--warning); }
.booking-bar-duration { font-size: 9.5px; font-weight: 600; margin-top: 2px; }
.booking-bar-blue .booking-bar-duration { color: var(--info); }
.booking-bar-orange .booking-bar-duration { color: var(--warning); }
.month-cell:hover { background: var(--surface); }
.month-cell.booked:hover { background: transparent; }
.month-cell:hover .booking-bar { filter: brightness(0.97); }
.avail-tick {
  display: inline-block; width: 6px; height: 6px; border-radius: 999px;
  background: var(--border);
}
.month-cell:hover .avail-tick { background: var(--primary); }
/* Past, never-booked months are historical gaps, not open inventory —
   keep them visually inert instead of hover-highlighting like real availability. */
.avail-tick--past { background: var(--border); opacity: 0.6; }
.month-cell:hover .avail-tick--past { background: var(--text-3); }

.empty-state { text-align: center; padding: 36px; color: var(--text-2); font-size: 13px; font-weight: 700; background: var(--surface); }
.modal-backdrop {
  position: fixed; inset: 0; z-index: 2000; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center; padding: 24px;
}
.product-detail-backdrop { z-index: 2100; }

/* ── Modal open animation (shared with ContactList.vue) ── */
@keyframes overlay-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes modal-spring-in {
  from { opacity: 0; transform: scale(0.92) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-backdrop { animation: overlay-fade-in 0.18s ease; }
.modal-backdrop > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
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
.detail-head-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.btn-delete-site {
  display: flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; margin-top: 2px;
  border: none; border-radius: var(--radius-sm); background: var(--danger-soft); color: var(--danger);
  font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.btn-delete-site:hover { filter: brightness(0.93); }
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
.detail-panel, .landmark-panel, .contact-panel {
  min-width: 0; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow: hidden;
}
.contact-panel { margin-top: 18px; }
.contact-hint { margin: 0; padding: 10px 16px 14px; font-size: 12px; color: var(--text-3); }
.detail-actions, .landmark-actions {
  min-height: 44px; background: var(--surface-2); color: var(--text-1);
  border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;
  gap: 10px; padding: 8px 12px 8px 16px; font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
}
.detail-actions button, .landmark-actions button {
  height: 30px; border-radius: var(--radius-sm); padding: 0 14px; font-size: 12px;
  font-weight: 600; cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.detail-actions > button, .landmark-actions > button,
.btn-cancel-detail, .btn-cancel-landmarks {
  background: var(--surface); color: var(--text-2); border: 1px solid var(--border);
}
.detail-actions > button:hover, .landmark-actions > button:hover,
.btn-cancel-detail:hover, .btn-cancel-landmarks:hover { background: var(--border); color: var(--text-1); }
.btn-save-detail, .btn-save-landmarks { background: var(--primary); color: var(--primary-on); border: none; }
.btn-save-detail:hover, .btn-save-landmarks:hover { background: var(--primary-hover); }
.detail-actions button:disabled, .landmark-actions button:disabled { opacity: 0.6; cursor: not-allowed; }
.detail-edit-actions, .landmark-edit-actions { display: flex; align-items: center; gap: 6px; }
.detail-table, .landmark-table { width: 100%; min-width: 0; border-collapse: collapse; font-size: 13px; }
.detail-table th, .detail-table td, .landmark-table th, .landmark-table td {
  padding: 10px 16px; border-bottom: 1px solid var(--border-soft); vertical-align: middle; text-align: left;
}
.detail-table tbody tr:last-child th, .detail-table tbody tr:last-child td,
.landmark-table tbody tr:last-child th, .landmark-table tbody tr:last-child td { border-bottom: none; }
.detail-table th, .landmark-table th {
  width: 40%; text-transform: uppercase; font-size: 11px; font-weight: 700;
  letter-spacing: 0.5px; color: var(--text-2); background: var(--surface-2); white-space: nowrap;
}
.detail-table td, .landmark-table td { font-weight: 600; color: var(--text-1); line-height: 1.35; }
.detail-table a { color: var(--primary); font-weight: 600; text-decoration: underline; }
.detail-table input, .landmark-table input {
  width: 100%; height: 34px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 10px; color: var(--text-1); font-size: 13px; font-weight: 500; text-align: left;
  outline: none; background: var(--surface);
}
.detail-table input:focus, .landmark-table input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.btn-remove-landmark {
  width: 26px; height: 26px; border: none; border-radius: var(--radius-sm);
  background: var(--danger-soft); color: var(--danger); font-size: 16px; line-height: 1;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.btn-remove-landmark:hover:not(:disabled) { filter: brightness(0.93); }
.btn-remove-landmark:disabled, .btn-add-landmark:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-add-landmark {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: calc(100% - 32px); margin: 10px 16px 14px; height: 34px;
  border: 1.5px dashed var(--border); border-radius: var(--radius-sm);
  background: transparent; color: var(--text-2); font-size: 12px; font-weight: 700; cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.btn-add-landmark:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.map-link {
  display: inline-flex; align-items: center; justify-content: center; min-height: 36px;
  padding: 0 14px; border-radius: var(--radius-sm); background: var(--primary); color: var(--primary-on); font-size: 12px;
  font-weight: 600; text-decoration: none;
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
.cell-menu-empty--past .past-flag {
  display: inline-block; font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.4px; padding: 2px 9px; border-radius: 999px;
  background: var(--surface-2); color: var(--text-3); margin-bottom: 8px;
}
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
.cell-menu-hint { margin: 10px 0 0; font-size: 11.5px; color: var(--text-3); }
.cell-menu-foot {
  display: flex; justify-content: space-between; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface-2);
}
.btn-danger {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  background: var(--danger-soft); color: var(--danger);
}
.btn-danger:hover { filter: brightness(0.93); }

/* Photo panels inside product detail modal */
.photo-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px;
}
.photo-panel {
  border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); overflow: hidden;
  display: flex; flex-direction: column; box-shadow: var(--shadow-sm);
}
.photo-actions {
  background: var(--surface-2); color: var(--text-1); padding: 8px 12px;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border);
}
.btn-upload, .btn-remove-photo {
  height: 30px; border: none; border-radius: var(--radius-sm); padding: 0 14px; font-size: 12px;
  font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
  transition: background 0.15s;
}
.btn-upload { background: var(--primary); color: var(--primary-on); }
.btn-upload:hover { background: var(--primary-hover); }
.btn-upload input { display: none; }
.btn-upload:has(input:disabled) { opacity: 0.6; cursor: not-allowed; }
.btn-remove-photo { background: var(--danger-soft); color: var(--danger); }
.btn-remove-photo:hover { filter: brightness(0.93); }
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
  height: 24px; border: 1.5px solid var(--primary); border-radius: 5px; padding: 0 10px;
  font-size: 9px; font-weight: 900; cursor: pointer; background: var(--primary-soft); color: var(--primary);
  display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
}
.btn-overlay-pill:hover { filter: brightness(0.95); }
/* Composite badge overlaid on photo preview */
.composite-tag {
  position: absolute; bottom: 6px; left: 6px;
  background: rgba(29,78,216,0.85); color: var(--primary-on); font-size: 9px; font-weight: 700;
  padding: 2px 6px; border-radius: 999px; pointer-events: none;
}
/* Wizard overlay button */
.btn-mini-overlay {
  background: var(--primary-soft); color: var(--primary); border: 1.5px solid var(--primary);
}
.btn-mini-overlay:hover { filter: brightness(0.95); }

/* Landmark distance column */
.landmark-col-cat { width: 32% !important; }
.landmark-col-dist { width: 24% !important; }
.landmark-col-remove { width: 36px !important; padding: 10px 12px 10px 0 !important; }

/* Wizard modal */
/* Compile Sites modal */
.compile-modal {
  width: min(980px, 96vw); max-height: 88vh; background: var(--surface); color: var(--text-1);
  display: flex; flex-direction: column; overflow: hidden; border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,0.22);
}
.compile-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.compile-header h2 { margin: 0 0 2px; font-size: 15px; font-weight: 700; color: var(--text-1); }
.compile-header p { margin: 0; font-size: 12px; color: var(--text-2); font-weight: 500; max-width: 560px; }
.compile-body { flex: 1; overflow: auto; padding: 20px; }
.compile-empty { text-align: center; padding: 40px; color: var(--text-3); font-size: 13px; font-weight: 600; }
.compile-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px;
}
.compile-card {
  position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  overflow: hidden; box-shadow: var(--shadow-sm); display: flex; flex-direction: column;
}
.compile-card-remove {
  position: absolute; top: 6px; right: 6px; z-index: 1; width: 22px; height: 22px;
  border: none; border-radius: 50%; background: rgba(15,23,42,0.55); color: #fff;
  font-size: 15px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.compile-card-remove:hover { background: var(--danger); }
.compile-card-photos { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border); }
.compile-photo-link { position: relative; display: block; aspect-ratio: 4/3; overflow: hidden; background: var(--surface-2); }
.compile-photo-view { display: block; width: 100%; height: 100%; cursor: zoom-in; }
.compile-photo-view img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.15s; }
.compile-photo-link:hover .compile-photo-view img { transform: scale(1.05); }
.compile-photo-tag {
  position: absolute; left: 4px; bottom: 4px; padding: 1px 6px; border-radius: var(--radius-sm);
  background: rgba(15,23,42,0.65); color: #fff; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
  pointer-events: none;
}
.compile-photo-download {
  position: absolute; top: 4px; right: 4px; z-index: 1; width: 22px; height: 22px;
  border-radius: 50%; background: rgba(15,23,42,0.65); color: #fff;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}
.compile-photo-download:hover { background: var(--primary); transform: scale(1.08); }
.compile-photo-placeholder {
  aspect-ratio: 4/3; display: flex; align-items: center; justify-content: center;
  background: var(--surface-2); color: var(--text-3); font-size: 10.5px; font-weight: 600; font-style: italic; text-align: center; padding: 8px;
}
.compile-card-info { padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 6px; }
.compile-card-name { font-size: 12.5px; font-weight: 700; color: var(--text-1); line-height: 1.35; }
.compile-card-badges { display: flex; flex-wrap: wrap; gap: 4px; }
.compile-card-meta { display: flex; flex-direction: column; gap: 3px; margin-top: 2px; }
.compile-meta-row { display: flex; gap: 5px; font-size: 10.5px; }
.compile-meta-label { flex-shrink: 0; font-weight: 700; color: var(--text-3); }
.compile-meta-value { font-weight: 600; color: var(--text-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.compile-footer {
  display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--border);
  background: var(--surface-2); flex-shrink: 0;
}
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
.wizard-tabs button.done { color: var(--success); border-color: var(--success); background: var(--success-soft); }
.wizard-tab-num {
  width: 20px; height: 20px; border-radius: 999px; background: var(--surface-2); color: var(--text-2);
  display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;
}
.wizard-tabs button.active .wizard-tab-num { background: var(--primary); color: var(--primary-on); }
.wizard-tabs button.done .wizard-tab-num { background: var(--success); color: var(--primary-on); }
.wizard-body { flex: 1; overflow: auto; padding: 20px 24px; }
.wizard-section { margin-bottom: 22px; }
.wizard-section:last-child { margin-bottom: 0; }
.wizard-signatory-presets { display: flex; gap: 6px; flex-wrap: wrap; }
.signatory-preset-btn {
  padding: 4px 12px; font-size: 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2);
  color: var(--text-2); cursor: pointer; transition: background 0.15s, border-color 0.15s;
}
.signatory-preset-btn:hover { background: var(--surface-3); }
.signatory-preset-btn.active {
  background: var(--primary); color: var(--primary-on); border-color: var(--primary);
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
.badge-active-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--success-soft); color: var(--success); }
.btn-set-active {
  padding: 4px 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-sm); cursor: pointer;
  background: var(--primary-soft); color: var(--primary); border: 1px solid var(--primary);
  transition: background 0.15s, color 0.15s;
}
.btn-set-active:hover { background: var(--primary); color: var(--primary-on); }
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
  margin: 0 0 12px; padding: 10px 12px; background: var(--warning-soft); border: 1px solid var(--warning);
  border-radius: 6px; color: var(--warning); font-size: 12px; font-weight: 700;
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
  position: absolute; top: 4px; right: 4px; background: var(--primary); color: var(--primary-on);
  font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 999px;
}
.btn-mini {
  height: 28px; border: 1px solid var(--border); background: var(--surface); color: var(--text-1);
  border-radius: 5px; padding: 0 10px; font-size: 11px; font-weight: 900; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 4px;
}
.btn-mini input { display: none; }
.btn-mini:hover { background: var(--surface-2); }
.review-body { padding: 16px 20px; }
/* ── Step 1 output settings section ────────────────────────────── */
.output-settings-section {
  margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);
}
.output-settings-label {
  font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--text-3); margin-bottom: 10px;
}

/* ── Step 2 sheet settings bar ──────────────────────────────────── */
.sheets-settings-bar {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  padding: 10px 14px; background: var(--surface-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 14px;
}
.sheets-orient-row {
  display: flex; align-items: center; gap: 10px;
}
.sheets-settings-label {
  font-size: 11px; font-weight: 700; color: var(--text-2); white-space: nowrap;
}

/* Orientation toggle (used in Step 2) */
.orientation-group {
  display: flex; gap: 0; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;
}
.orient-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 11px; border: none; border-right: 1px solid var(--border);
  background: var(--surface); color: var(--text-2); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.orient-btn:last-child { border-right: none; }
.orient-btn:hover { background: var(--surface-2); color: var(--text-1); }
.orient-btn.active { background: var(--primary); color: var(--primary-on); }

/* Replacement fee field (inside sheets-settings-bar) */
.addl-fee-field { display: flex; align-items: center; gap: 6px; }
.addl-fee-label { font-size: 11px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.addl-fee-input {
  width: 72px; height: 30px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 8px; font-size: 12px; color: var(--text-1); background: var(--surface);
  outline: none; text-align: center;
}
.addl-fee-input:focus { border-color: var(--primary); }

/* ── Step 3 review topbar ───────────────────────────────────────── */
.review-topbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 14px;
}
.review-summary {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.review-summary-chip {
  display: inline-flex; align-items: center; font-size: 11px; font-weight: 700;
  color: var(--text-2); background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 999px; padding: 3px 10px; white-space: nowrap;
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
  border-radius: var(--radius-sm); background: var(--primary); color: var(--primary-on);
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

/* ── Wizard Step 2 improvements ─────────────────────────────────── */
/* Photo completeness bar */
.wizard-completeness {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 8px 14px;
  font-size: 12px; font-weight: 600; color: var(--text-2);
  margin-bottom: 14px;
}
.wizard-completeness strong { color: var(--text-1); }
.wizard-comp-badge {
  margin-left: auto; font-size: 11px; font-weight: 700;
  padding: 2px 9px; border-radius: 999px; white-space: nowrap;
}
.comp-ok { background: var(--success-soft); color: var(--success); }
.comp-partial { background: var(--warning-soft); color: var(--warning); }

/* Per-site sheet title row in wizard card */
.wizard-sheet-title-row {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  padding: 7px 12px 8px; background: var(--danger-soft);
  border-top: 1px solid var(--danger-soft); border-bottom: 1px solid var(--danger-soft);
  margin-bottom: 10px;
}
.wizard-sheet-title-label {
  font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--danger); white-space: nowrap; min-width: 68px;
}
.wizard-sheet-title-input {
  flex: 1; min-width: 120px; height: 28px; border: 1px solid var(--danger);
  border-radius: var(--radius-sm); padding: 0 8px;
  font-size: 12px; color: var(--text-1); background: var(--surface); outline: none;
}
.wizard-sheet-title-input:focus { border-color: var(--danger); box-shadow: 0 0 0 2px color-mix(in srgb, var(--danger) 12%, transparent); }
.wizard-sheet-title-input::placeholder { color: var(--text-3); font-style: italic; }
.wizard-sheet-title-input:disabled { opacity: 0.55; cursor: not-allowed; }
.wizard-sheet-title-live {
  font-size: 11px; font-weight: 800; color: var(--danger); white-space: nowrap;
  max-width: 180px; overflow: hidden; text-overflow: ellipsis;
  background: rgba(204,0,0,0.08); border-radius: 4px; padding: 2px 7px;
}

/* Review step: sheet title chip per site */
.review-sheet-title {
  display: inline-flex; align-items: center; overflow: hidden;
  font-size: 10px; font-weight: 700; color: var(--danger);
  background: var(--danger-soft); border: 1px solid var(--danger-soft);
  border-radius: 999px; padding: 1px 8px;
  max-width: 160px; text-overflow: ellipsis; white-space: nowrap;
}

/* Product detail table: hint text under empty value */
.detail-cell-hint {
  display: block; font-size: 11px; font-weight: 500; color: var(--text-3);
  margin-top: 3px; font-style: italic;
}

.review-sites { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 5px; }
.review-sites li {
  display: grid; grid-template-columns: 1fr auto auto auto; gap: 8px; align-items: center;
  padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
  font-size: 12px;
}
.photo-status { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
.photo-status.ok { background: var(--success-soft); color: var(--success); }
.photo-status.missing { background: var(--danger-soft); color: var(--danger); }
.wizard-footer {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface);
}
.wizard-footer-right { display: flex; gap: 8px; }
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
  padding: 0 14px; border-radius: var(--radius-sm); background: var(--primary); color: var(--primary-on);
  font-size: 12px; font-weight: 800; text-decoration: none;
}
.street-view-link:hover { opacity: 0.9; }

.field-hint { margin-left: 6px; font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: none; letter-spacing: 0; }
.input-error-border { border-color: var(--danger) !important; }
.field-error { margin: 3px 0 0; font-size: 11.5px; color: var(--danger); font-weight: 500; }
.promo-date-input { cursor: pointer; }
.phone-input {
  display: flex; align-items: stretch; height: 38px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); overflow: hidden;
}
.phone-input:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.phone-input.input-error-border { border-color: var(--danger) !important; box-shadow: none !important; }
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
.place-loading { color: var(--text-3); font-weight: 700; cursor: default; pointer-events: none; }

/* Staged for Review section */
.staged-section {
  background: var(--surface); border: 1.5px solid var(--warning); border-radius: var(--radius);
  margin-bottom: 12px; overflow: hidden;
}
.staged-header {
  display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
  padding: 11px 16px; background: var(--warning-soft); cursor: pointer; user-select: none;
  border-bottom: 1px solid var(--warning-soft);
}
.staged-header:hover { filter: brightness(0.97); }
.staged-header-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.staged-chevron { color: var(--warning); transition: transform 0.2s; flex-shrink: 0; }
.staged-chevron-open { transform: rotate(90deg); }
.staged-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: var(--warning); }
.staged-count-chip {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px;
  background: var(--warning); color: var(--primary-on); border-radius: 999px; font-size: 11px; font-weight: 800;
}
.staged-sub { margin: 0; font-size: 11.5px; color: var(--warning); font-weight: 600; }
.staged-grid {
  display: flex; flex-direction: column;
  border-top: 1px solid var(--warning-soft);
}
.staged-card {
  background: var(--surface); padding: 12px 16px;
  display: flex; flex-direction: row; align-items: center; gap: 14px;
  border-right: 1px solid var(--warning-soft); border-bottom: 1px solid var(--warning-soft);
  transition: background 0.15s;
}
.staged-card-selected { background: var(--primary-soft); }
.staged-card-check, .staged-select-all {
  width: 16px; height: 16px; flex-shrink: 0; cursor: pointer; accent-color: var(--primary); margin: 0;
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
  font-size: 10.5px; color: var(--warning); font-weight: 600;
  padding: 0 16px; border-left: 1px solid var(--warning-soft); border-right: 1px solid var(--warning-soft);
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
  background: var(--primary-soft); color: var(--primary); font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-staged-pdf:hover { filter: brightness(0.95); }
.btn-staged-confirm {
  height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm);
  background: var(--success-soft); color: var(--success); font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px; margin-left: auto;
}
.btn-staged-confirm:hover { filter: brightness(0.95); }
.btn-staged-discard {
  height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm);
  background: var(--danger-soft); color: var(--danger); font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px;
}
.btn-staged-discard:hover { filter: brightness(0.93); }

/* Pending product states */
.badge-pending { background: var(--warning-soft); color: var(--warning); border-color: var(--warning); }

/* Pending banner inside product detail modal */
.pending-banner {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 10px; background: var(--warning-soft); border: 1.5px solid var(--warning); border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px;
}
.pending-banner-text {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 700; color: var(--warning);
}
.pending-banner-actions { display: flex; gap: 8px; }
.btn-confirm-product {
  height: 30px; padding: 0 12px; border: none; border-radius: var(--radius-sm);
  background: var(--success); color: var(--primary-on); font-size: 12px; font-weight: 700; cursor: pointer;
}
.btn-confirm-product:hover:not(:disabled) { filter: brightness(0.9); }
.btn-confirm-product:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-discard-product {
  height: 30px; padding: 0 12px; border: none; border-radius: var(--radius-sm);
  background: var(--danger-soft); color: var(--danger); font-size: 12px; font-weight: 700; cursor: pointer;
}
.btn-discard-product:hover { filter: brightness(0.93); }

@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { align-items: stretch; flex-direction: column; }
  .field, .field.company-field, .field.place-field, .field.small { width: 100%; min-width: 0; }
  .btn-add, .btn-dark, .btn-clear, .btn-proposal, .btn-compile, .btn-ghost-action, .btn-map-toggle { width: 100%; justify-content: center; }
  .action-bar-filters, .action-bar-actions { flex-direction: column; width: 100%; align-items: stretch; }
  .action-bar-landmark { flex-direction: column; align-items: stretch; }
  .landmark-query-input { min-width: 0; }
  .selection-bar { flex-direction: column; align-items: stretch; }
  .selection-bar-actions { flex-direction: column; }
  .staged-card { flex-wrap: wrap; }
  .staged-card-meta { border: none; padding: 0; }
  .detail-body { padding: 14px 12px; }
  .detail-grid { grid-template-columns: 1fr; }
  .photo-grid { grid-template-columns: 1fr; }
  .wizard-grid, .wizard-photo-row, .wizard-review { grid-template-columns: 1fr; }
  .register-form-grid { grid-template-columns: 1fr; }
  .leaflet-map { height: 320px; }
}

/* Toast notification */
.toast-msg {
  position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
  background: var(--success); color: var(--primary-on); padding: 10px 22px;
  border-radius: var(--radius); box-shadow: 0 6px 24px rgba(0,0,0,0.22);
  font-size: 13px; font-weight: 600; z-index: 9999; pointer-events: none;
  white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(6px); }
</style>
