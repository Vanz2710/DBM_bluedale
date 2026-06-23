<template>
  <div class="page">
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">{{ bannerTitle }}</h1>
        <p class="page-subtitle">{{ bannerSub }}</p>
      </div>
      <div class="page-head-actions">
        <button v-if="tab === 'contacts' && can('create contacts')" class="btn-primary-pill" data-tour="add-contact-btn" @click="openAddModal">
          <span class="plus-icon" aria-hidden="true">+</span> Add New Contact
        </button>
        <button v-else-if="tab === 'forecast' && can('create forecasts')" class="btn-primary-pill" @click="openForecastAdd()">
          <span class="plus-icon" aria-hidden="true">+</span> Add Forecast
        </button>
      </div>
    </div>

    <!-- Tab bar -->
    <div class="view-tabs">
      <button :class="['tab-btn', { 'tab-active': tab === 'contacts' }]" @click="switchTab('contacts')">
        <span class="tab-icon" v-html="CI.list"></span> Contacts
      </button>
      <button :class="['tab-btn', { 'tab-active': tab === 'summary' }]" @click="switchTab('summary')">
        <span class="tab-icon" v-html="CI.chart"></span> Summary
      </button>
      <button :class="['tab-btn', { 'tab-active': tab === 'tasks' }]" @click="switchTab('tasks')">
        <span class="tab-icon" v-html="CI.clipboard"></span> To-Do
      </button>
      <button :class="['tab-btn', { 'tab-active': tab === 'forecast' }]" @click="switchTab('forecast')">
        <span class="tab-icon" v-html="CI.trending"></span> Forecast
      </button>
    </div>

    <!-- Contacts toolbar -->
    <div v-if="tab === 'contacts'" class="toolbar">
      <div class="filter-date-range">
        <span class="date-range-label">Date Range</span>
        <div class="date-range-inputs">
          <div class="date-input-wrap">
            <span class="date-input-prefix">From</span>
            <input type="date" v-model="dateFrom" @change="load(1)" class="date-range-input">
          </div>
          <span class="date-range-sep"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
          <div class="date-input-wrap">
            <span class="date-input-prefix">To</span>
            <input type="date" v-model="dateTo" @change="load(1)" class="date-range-input">
          </div>
        </div>
      </div>
      <div class="filter-group wide search-group">
        <label>Search</label>
        <div class="search-wrap">
          <input
            v-model="search"
            @input="onSearchInput"
            @keyup.enter="load(1); showSuggestions = false"
            @keydown.esc="showSuggestions = false"
            @blur="onSearchBlur"
            @focus="search.trim() && suggestions.length && (showSuggestions = true)"
            placeholder="Search by company name…"
            autocomplete="off"
          >
          <div v-if="showSuggestions && suggestions.length" class="suggestions-dropdown">
            <div
              v-for="s in suggestions"
              :key="s.id"
              class="suggestion-item"
              @mousedown.prevent="pickSuggestion(s.name)"
            >{{ s.name }}</div>
          </div>
        </div>
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="userId" @change="load(1)">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="statusId" @change="load(1)">
          <option value="">All</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="typeId" @change="load(1)">
          <option value="">All</option>
          <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Category</label>
        <select v-model="categoryId" @change="load(1)">
          <option value="">All</option>
          <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Sort</label>
        <select v-model="sort" @change="load(1)">
          <option value="desc">Newest First</option>
          <option value="asc">Oldest First</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Rows</label>
        <select v-model.number="contactsPerPage" @change="load(1)">
          <option v-for="n in CONTACT_PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="load(1)">Search</button>
      <button class="btn btn-clear" @click="clearFilters" v-if="hasFilters">Clear</button>
      <button class="btn btn-export" @click="openExportModal">Export</button>
    </div>

    <!-- Summary toolbar -->
    <div v-else-if="tab === 'summary'" class="toolbar">
      <div class="filter-group">
        <label>Year</label>
        <select v-model="summaryYear" @change="applySummaryFilters">
          <option v-for="y in summaryYears" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="summaryFilters.search" @keyup.enter="applySummaryFilters" placeholder="Company name…">
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="summaryFilters.user_id" @change="applySummaryFilters">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="summaryFilters.status_id" @change="applySummaryFilters">
          <option value="">All</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="summaryFilters.type_id" @change="applySummaryFilters">
          <option value="">All</option>
          <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Category</label>
        <select v-model="summaryFilters.category_id" @change="applySummaryFilters">
          <option value="">All</option>
          <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Industry</label>
        <select v-model="summaryFilters.industry_id" @change="applySummaryFilters">
          <option value="">All</option>
          <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="applySummaryFilters">Search</button>
      <button class="btn btn-clear" @click="resetSummaryFilters">Reset</button>
      <button class="btn btn-export" @click="exportSummary">Export</button>
    </div>

    <!-- Tasks toolbar -->
    <div v-else-if="tab === 'tasks'" class="toolbar">
      <div class="date-nav">
        <button class="date-nav-arrow" @click="shiftTodoDate(-1)" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <CalendarPicker
          v-model="todoDate"
          :marked-dates="todoMarked"
          :loading-dates="todoMarkLoading"
          @update:modelValue="loadTodos"
          @month-change="({ year, month }) => loadTodoMarkedDates(year, month)"
        />
        <button class="date-nav-arrow" @click="shiftTodoDate(1)" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="todoSearch" @keyup.enter="loadTodos" placeholder="Company name…">
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="todoUserId" @change="loadTodos">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="todoStatus" @change="loadTodos">
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Per Page</label>
        <input type="number" v-model.number="todoPerPage" @change="loadTodos" style="width:70px;">
      </div>
      <button class="btn btn-primary" @click="loadTodos">Search</button>
    </div>

    <!-- Forecast toolbar -->
    <div v-else class="toolbar">
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="forecastFilters.q" @keyup.enter="applyForecastFilters" placeholder="Company, product, user…">
      </div>
      <div class="filter-group">
        <label>Product</label>
        <select v-model="forecastFilters.product_id" @change="applyForecastFilters">
          <option value="">All Products</option>
          <option v-for="p in forecastLookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="forecastFilters.forecast_type_id" @change="applyForecastFilters">
          <option value="">All Types</option>
          <option v-for="t in forecastLookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Result</label>
        <select v-model="forecastFilters.result_id" @change="applyForecastFilters">
          <option value="">All Results</option>
          <option value="none">No Result</option>
          <option v-for="r in forecastResultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>User</label>
        <select v-model="forecastFilters.user_id" @change="applyForecastFilters">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>From</label>
        <input type="date" v-model="forecastFilters.from_date" @change="applyForecastFilters">
      </div>
      <div class="filter-group">
        <label>To</label>
        <input type="date" v-model="forecastFilters.to_date" @change="applyForecastFilters">
      </div>
      <button class="btn btn-primary" @click="applyForecastFilters">Search</button>
      <button class="btn btn-clear" @click="resetForecastFilters">Reset</button>
    </div>

    <!-- Shared drawer -->
    <transition name="drawer">
      <div v-if="drawer.open" class="drawer-overlay">
        <div class="drawer-panel">
          <div class="drawer-header">
            <div class="drawer-title-row">
              <div>
                <div class="drawer-company">{{ drawer.contact?.name }}</div>
                <div class="drawer-meta" v-if="drawer.contact">
                  <span v-if="drawer.contact.status" class="meta-pill">{{ drawer.contact.status.name }}</span>
                  <span v-if="drawer.contact.type" class="meta-pill">{{ drawer.contact.type.name }}</span>
                  <span v-if="drawer.contact.industry" class="meta-pill">{{ drawer.contact.industry.name }}</span>
                </div>
              </div>
              <button class="drawer-close" @click="closeDrawer" v-html="CI.x"></button>
            </div>
            <div v-if="drawer.contact?.is_permanently_closed" class="drawer-closed-banner">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
              Permanently Closed
            </div>
            <div class="drawer-actions" v-if="drawer.contact">
              <router-link :to="`/contacts/${drawer.contact.id}`" class="daction-btn btn-view-full"><span v-html="CI.eye"></span> Full View</router-link>
              <router-link v-if="can('edit contacts') && drawer.contact.can_edit" :to="`/contacts/${drawer.contact.id}/edit`" class="daction-btn btn-edit-c"><span v-html="CI.edit"></span> Edit</router-link>
              <button v-if="can('create followups')" type="button" class="daction-btn btn-followup-c" @click="openFollowUpModal()"><span v-html="CI.bell"></span> Follow-Up</button>
              <button v-if="can('create forecasts')" type="button" class="daction-btn btn-forecast-c" @click="openForecastAddForDrawer"><span v-html="CI.trending"></span> Forecast</button>
              <button
                v-if="can('edit contacts') && drawer.contact.can_edit"
                type="button"
                :class="drawer.contact.is_permanently_closed ? 'daction-btn btn-reopen-c' : 'daction-btn btn-close-c'"
                @click="drawer.contact.is_permanently_closed ? toggleDrawerClosed() : openDrawerClosedModal()"
              >
                {{ drawer.contact.is_permanently_closed ? 'Mark Active' : 'Mark Closed' }}
              </button>
            </div>
          </div>

          <div class="drawer-body">
            <div v-if="drawer.loading" class="drawer-loading">Loading…</div>
            <template v-else-if="drawer.contact">

              <div class="drawer-section">
                <div class="dsec-title">Company Details</div>
                <div class="dinfo-grid">
                  <div class="dinfo-field">
                    <span class="dinfo-label">User</span>
                    <span class="dinfo-value">{{ drawer.contact.user?.name ?? '—' }}</span>
                  </div>
                  <div class="dinfo-field">
                    <span class="dinfo-label">Category</span>
                    <span class="dinfo-value">{{ drawer.contact.category?.name ?? '—' }}</span>
                  </div>
                  <div class="dinfo-field">
                    <span class="dinfo-label">Date Added</span>
                    <span class="dinfo-value">{{ fmtDate(drawer.contact.created_at) }}</span>
                  </div>
                  <div v-if="drawer.contact.whatsapp_phone" class="dinfo-field">
                    <span class="dinfo-label">WhatsApp</span>
                    <span class="dinfo-value">{{ drawer.contact.whatsapp_phone }}</span>
                  </div>
                </div>
                <div v-if="drawer.contact.address" class="dinfo-full">
                  <div class="dinfo-label-row">
                    <span class="dinfo-label">Address</span>
                    <a :href="mapsUrl(drawer.contact)" target="_blank" rel="noopener noreferrer" class="maps-link" @click.stop>
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                      Maps
                    </a>
                  </div>
                  <div class="dinfo-value">{{ drawer.contact.address }}</div>
                </div>
                <div v-if="drawer.contact.remark" class="dinfo-full">
                  <div class="dinfo-label">Remark</div>
                  <div class="dinfo-value" style="white-space:pre-line">{{ drawer.contact.remark }}</div>
                </div>
              </div>

              <div class="drawer-section" v-if="drawer.contact.incharges?.length">
                <div class="dsec-title">Persons in Charge ({{ drawer.contact.incharges.length }})</div>
                <table class="drawer-table">
                  <thead><tr><th>Name</th><th>Mobile</th><th>Email</th></tr></thead>
                  <tbody>
                    <tr v-for="pic in drawer.contact.incharges" :key="pic.id">
                      <td><strong>{{ pic.name }}</strong></td>
                      <td>{{ pic.phone_mobile || '—' }}</td>
                      <td>
                        <a v-if="pic.email" :href="`mailto:${pic.email}`" class="drawer-email-link">{{ pic.email }}</a>
                        <span v-else>—</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Tasks + their Follow-Ups nested -->
              <div class="drawer-section">
                <div class="dsec-title-row">
                  <span class="dsec-title">To-Dos ({{ drawer.contact.todos?.length ?? 0 }})</span>
                  <button v-if="can('create todos')" class="add-task-toggle-btn" @click="openAddTask">+ Add Task</button>
                </div>

                <!-- Add Task inline form -->
                <div v-if="addTaskOpen" class="add-task-form">
                  <div class="add-task-row">
                    <div class="add-task-field">
                      <label>Task</label>
                      <select v-model="addTaskForm.task_id">
                        <option value="">Select task type</option>
                        <option v-for="t in lookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                      </select>
                    </div>
                    <div class="add-task-field">
                      <label>Date <span class="req">*</span></label>
                      <input type="date" v-model="addTaskForm.todo_date">
                    </div>
                  </div>
                  <div class="add-task-field">
                    <label>Remark</label>
                    <textarea v-model="addTaskForm.todo_remark" placeholder="Optional notes…" rows="2"></textarea>
                  </div>
                  <div v-if="addTaskError" class="add-task-error">{{ addTaskError }}</div>
                  <div class="add-task-actions">
                    <button class="btn btn-clear btn-sm" @click="addTaskOpen = false; addTaskError = ''">Cancel</button>
                    <button class="btn btn-primary btn-sm" :disabled="!addTaskForm.todo_date || addTaskSaving" @click="submitQuickTask">
                      {{ addTaskSaving ? 'Saving…' : 'Save Task' }}
                    </button>
                  </div>
                </div>

                <p v-if="!drawer.contact.todos?.length" class="drawer-empty">No tasks logged yet.</p>
                <table v-else class="drawer-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Task</th>
                      <th>User</th>
                      <th>Remark</th>
                      <th style="text-align:right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="td in drawer.contact.todos" :key="td.id">
                      <tr :class="['todo-row', { 'todo-done-row': td.completion_status === 'completed' }]">
                        <td class="dtd-date">{{ fmtDate(td.todo_date) }}</td>
                        <td><span v-if="td.task" class="dtask-badge">{{ td.task.name }}</span><span v-else class="muted-dash">—</span></td>
                        <td>{{ td.user?.name ?? '—' }}</td>
                        <td style="white-space:pre-line;font-size:12px">{{ td.todo_remark || '—' }}</td>
                        <td class="todo-actions-cell">
                          <button class="fu-count-badge" :class="{ 'fu-has-entries': td.follow_ups?.length }" :title="(td.follow_ups?.length ?? 0) + ' follow-up(s) — click to view/add'" @click="openTaskFuModal(td)">
                            <span v-html="CI.phone" style="display:inline-flex;align-items:center;margin-right:3px"></span>{{ td.follow_ups?.length ?? 0 }}
                          </button>
                          <button v-if="td.completion_status !== 'completed'" class="todo-done-btn" title="Mark complete" @click="toggleDrawerTodoDone(td, 'completed')" v-html="CI.check"></button>
                          <button v-else class="todo-undo-btn" title="Mark pending" @click="toggleDrawerTodoDone(td, 'pending')" v-html="CI.rotateCcw"></button>
                          <button class="todo-del-btn" title="Delete task" @click="deleteDrawerTodo(td)" v-html="CI.x"></button>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              <div class="drawer-section">
                <div class="dsec-title-row">
                  <span class="dsec-title">Monthly Activity</span>
                  <select v-model="drawerYear" class="drawer-year-sel">
                    <option v-for="y in drawerYears" :key="y" :value="y">{{ y }}</option>
                  </select>
                </div>
                <div class="month-activity-grid">
                  <div
                    v-for="(mname, i) in MONTH_NAMES" :key="i"
                    class="month-cell"
                    :class="{ 'month-active': drawerMonthMap[i + 1] }"
                    :title="drawerMonthMap[i + 1] ? `${drawerMonthMap[i + 1].date} — ${drawerMonthMap[i + 1].task}` : ''"
                  >
                    <div class="month-cell-label">{{ mname }}</div>
                    <template v-if="drawerMonthMap[i + 1]">
                      <div class="month-cell-date">{{ drawerMonthMap[i + 1].date }}</div>
                      <div class="month-cell-task">{{ drawerMonthMap[i + 1].task }}</div>
                    </template>
                    <div v-else class="month-cell-empty">—</div>
                  </div>
                </div>
              </div>

              <div class="drawer-section" v-if="drawer.contact.forecasts?.length">
                <div class="dsec-title">Forecast History ({{ drawer.contact.forecasts.length }})</div>
                <table class="drawer-table">
                  <thead><tr><th>Date</th><th>Product</th><th>Amount</th><th>Result</th></tr></thead>
                  <tbody>
                    <tr v-for="f in drawer.contact.forecasts" :key="f.id">
                      <td class="dtd-date">{{ fmtDate(f.forecast_date) }}</td>
                      <td>{{ f.product?.name ?? '—' }}</td>
                      <td class="dfcast-amount">{{ fmtCurrency(f.amount) }}</td>
                      <td><span class="result-badge" :class="resultClass(f.result?.name)">{{ f.result?.name ?? 'No Result' }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- Delete confirmation modal -->
    <div v-if="deleteModal.show" class="remark-overlay">
      <div class="remark-modal delete-modal">
        <div class="remark-modal-header delete-modal-header">
          <strong>Delete Contact</strong>
          <button class="remark-close" @click="closeDeleteModal" v-html="CI.x"></button>
        </div>
        <div class="delete-modal-body">
          <div class="delete-warning-icon" v-html="CIL.warning"></div>
          <p class="delete-warning-text">
            You are about to permanently delete <strong>{{ deleteModal.contact?.name }}</strong>.
            This action cannot be undone and will remove all associated tasks, forecasts, and history.
          </p>
          <p class="delete-confirm-label">Type <strong>confirm delete</strong> to proceed:</p>
          <input
            v-model="deleteModal.input"
            class="delete-confirm-input"
            placeholder="confirm delete"
            @keyup.enter="confirmDelete"
            ref="deleteInput"
          />
          <div class="delete-modal-actions">
            <button class="btn btn-clear" @click="closeDeleteModal">Cancel</button>
            <button
              class="btn btn-danger"
              :disabled="deleteModal.input !== 'confirm delete' || deleteModal.loading"
              @click="confirmDelete"
            >{{ deleteModal.loading ? 'Deleting…' : 'Delete Contact' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Remark modal -->
    <div v-if="remarkModal.show" class="remark-overlay">
      <div class="remark-modal">
        <div class="remark-modal-header">
          <strong>{{ remarkModal.company }}</strong>
          <button class="remark-close" @click="remarkModal.show = false" v-html="CI.x"></button>
        </div>
        <div class="remark-modal-body">{{ remarkModal.text }}</div>
      </div>
    </div>

    <!-- Add Contact Modal -->
    <div v-if="addModal.open" class="remark-overlay">
      <div class="add-contact-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Add New Contact</strong>
            <span class="add-modal-step-pill">Step {{ addStep }} of 2</span>
          </div>
          <button class="remark-close" @click="closeAddModal" v-html="CI.x"></button>
        </div>

        <div v-if="addStep === 1" class="add-modal-body">
          <div class="add-section-label">Company Info</div>
          <form @submit.prevent="goAddStep2">
            <div class="add-form-group">
              <label>Company Name <span class="req">*</span></label>
              <input v-model="addForm.name" @input="checkAddDuplicate" placeholder="Enter company name" required>
              <div v-if="addDupError" class="add-hint error-hint">{{ addDupError }}</div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Status <span class="req">*</span></label>
                <select v-model="addForm.status_id" required>
                  <option value="">Select status</option>
                  <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Type <span class="req">*</span></label>
                <select v-model="addForm.type_id" required>
                  <option value="">Select type</option>
                  <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Industry <span class="req">*</span></label>
                <select v-model="addForm.industry_id" required>
                  <option value="">Select industry</option>
                  <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Category / Product <span class="req">*</span></label>
                <select v-model="addForm.category_id" required>
                  <option value="">Select category</option>
                  <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Area</label>
                <select v-model="addForm.area_id">
                  <option value="">Select area</option>
                  <option v-for="a in lookups.areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
              <div v-if="isAdmin" class="add-form-group">
                <label>Assign To</label>
                <select v-model="addForm.user_id">
                  <option value="">— Assign to me —</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Lead Source</label>
                <select v-model="addForm.lead_source">
                  <option value="manual">Manual Entry</option>
                  <option value="phone_call">Phone Call</option>
                  <option value="referral">Referral</option>
                  <option value="walk_in">Walk-in</option>
                  <option value="social_media">Social Media</option>
                  <option value="email_campaign">Email Campaign</option>
                  <option value="web_form">Web Form</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Date Created</label>
                <input type="date" v-model="addForm.created_at">
              </div>
            </div>
            <div class="add-form-group">
              <label>Address</label>
              <textarea v-model="addForm.address" placeholder="Enter address" rows="2"></textarea>
            </div>
            <div class="add-form-group">
              <label>Remarks</label>
              <textarea v-model="addForm.remark" placeholder="Internal notes about this company…" rows="2"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeAddModal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="!!addDupError">Next <span v-html="CI.chevronRight" style="display:inline-flex;align-items:center"></span></button>
            </div>
          </form>
        </div>

        <div v-else class="add-modal-body">
          <div class="add-section-label">
            Person in Charge
            <span class="company-chip-inline"><span v-html="CI.building"></span> {{ addForm.name }}</span>
          </div>
          <form @submit.prevent="submitAdd">
            <div v-if="addSubmitError" class="add-error-box">{{ addSubmitError }}</div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>PIC Name <span class="req">*</span></label>
                <input v-model="addPic.name" placeholder="Person in charge name" required>
              </div>
              <div class="add-form-group">
                <label>Phone Number <span class="req">*</span></label>
                <input v-model="addPic.phone" placeholder="Contact number" required>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" v-model="addPic.email" placeholder="Email address" required>
              </div>
              <div class="add-form-group">
                <label>Office Number</label>
                <input v-model="addPic.office" placeholder="Office number">
              </div>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="addStep = 1"><span v-html="CI.chevronLeft" style="display:inline-flex;align-items:center"></span> Back</button>
              <button type="submit" class="btn btn-primary" :disabled="addSaving">
                {{ addSaving ? 'Saving…' : 'Register Company' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Log Follow-Up Modal -->
    <div v-if="followUpModal.open" class="remark-overlay">
      <div class="add-contact-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Log Follow-Up</strong>
            <span class="company-chip-inline"><span v-html="CI.building"></span> {{ drawer.contact?.name }}</span>
          </div>
          <button class="remark-close" @click="closeFollowUpModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <form @submit.prevent="submitFollowUpModal">
            <div v-if="followUpModal.error" class="add-error-box">{{ followUpModal.error }}</div>
            <div class="add-form-row">
              <div class="add-form-group" style="flex:2">
                <label>Task <span class="req">*</span></label>
                <select v-model="followUpModalForm.todo_id" required>
                  <option value="">Select a task</option>
                  <option v-for="td in drawer.contact?.todos" :key="td.id" :value="td.id">
                    {{ td.task?.name ?? 'Task' }} — {{ fmtDate(td.todo_date) }}
                  </option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Date <span class="req">*</span></label>
                <input type="date" v-model="followUpModalForm.followup_date" required>
              </div>
            </div>
            <div class="add-form-group">
              <label>Action Type</label>
              <select v-model="followUpModalForm.action_type">
                <option value="">— Select type —</option>
                <option v-for="at in FOLLOWUP_ACTION_TYPES" :key="at" :value="at">{{ at }}</option>
              </select>
            </div>
            <div class="add-form-group">
              <label>Note</label>
              <textarea v-model="followUpModalForm.note" placeholder="What happened? Outcome, next steps…" rows="4"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeFollowUpModal">Cancel</button>
              <button type="submit" class="btn btn-followup-submit" :disabled="!followUpModalForm.todo_id || !followUpModalForm.followup_date || followUpModal.saving">
                {{ followUpModal.saving ? 'Saving…' : 'Save Follow-Up' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Task Follow-Up Modal -->
    <div v-if="taskFuModal.open" class="remark-overlay">
      <div class="add-contact-modal task-fu-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Follow-Ups</strong>
            <span class="company-chip-inline" v-if="taskFuModal.todo">
              <span v-html="CI.clipboard"></span>
              {{ taskFuModal.todo.task?.name ?? 'Task' }} — {{ fmtDate(taskFuModal.todo.todo_date) }}
            </span>
          </div>
          <button class="remark-close" @click="closeTaskFuModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body task-fu-body">
          <div v-if="taskFuModal.todo?.todo_remark" class="task-fu-remark">
            <span class="dinfo-label">Note:</span> {{ taskFuModal.todo.todo_remark }}
          </div>
          <div class="task-fu-section-label">History ({{ taskFuModal.todo?.follow_ups?.length ?? 0 }})</div>
          <div v-if="!taskFuModal.todo?.follow_ups?.length" class="task-fu-empty">No follow-ups logged yet.</div>
          <table v-else class="drawer-table" style="margin-bottom:18px">
            <thead>
              <tr><th>Date</th><th>Action</th><th>Note</th></tr>
            </thead>
            <tbody>
              <tr v-for="fu in taskFuModal.todo.follow_ups" :key="fu.id">
                <td class="dtd-date">{{ fmtDate(fu.followup_date) }}</td>
                <td><span v-if="fu.action_type" class="fu-badge">{{ fu.action_type }}</span><span v-else class="muted-dash">—</span></td>
                <td style="font-size:12.5px;white-space:pre-line;color:#374151">{{ fu.note || '—' }}</td>
              </tr>
            </tbody>
          </table>
          <div class="task-fu-divider"></div>
          <div class="task-fu-section-label">Log New Follow-Up</div>
          <form @submit.prevent="submitTaskFuForm">
            <div v-if="taskFuModal.error" class="add-error-box">{{ taskFuModal.error }}</div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Date <span class="req">*</span></label>
                <input type="date" v-model="taskFuForm.followup_date" required>
              </div>
              <div class="add-form-group">
                <label>Action Type</label>
                <select v-model="taskFuForm.action_type">
                  <option value="">— Select type —</option>
                  <option v-for="at in FOLLOWUP_ACTION_TYPES" :key="at" :value="at">{{ at }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-group">
              <label>Note</label>
              <textarea v-model="taskFuForm.note" placeholder="What happened? Outcome, next steps…" rows="3"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeTaskFuModal">Cancel</button>
              <button type="submit" class="btn-followup-submit" :disabled="!taskFuForm.followup_date || taskFuModal.saving">
                {{ taskFuModal.saving ? 'Saving…' : 'Log Follow-Up' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Quick Add Task Modal -->
    <div v-if="addTaskModal.open" class="remark-overlay">
      <div class="add-contact-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Add Task</strong>
            <span class="company-chip-inline"><span v-html="CI.building"></span> {{ addTaskModal.contact?.name }}</span>
          </div>
          <button class="remark-close" @click="closeAddTaskModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <form @submit.prevent="submitAddTaskModal">
            <div v-if="addTaskModal.error" class="add-error-box">{{ addTaskModal.error }}</div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Task Type</label>
                <select v-model="addTaskModalForm.task_id">
                  <option value="">Select task type</option>
                  <option v-for="t in lookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Date <span class="req">*</span></label>
                <input type="date" v-model="addTaskModalForm.todo_date" required>
              </div>
            </div>
            <div class="add-form-group">
              <label>Remark</label>
              <textarea v-model="addTaskModalForm.todo_remark" placeholder="Optional notes…" rows="3"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeAddTaskModal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="!addTaskModalForm.todo_date || addTaskModal.saving">
                {{ addTaskModal.saving ? 'Saving…' : 'Save Task' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Quick Edit Contact Modal -->
    <div v-if="editContactModal.open" class="remark-overlay">
      <div class="add-contact-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Edit Company</strong>
            <span class="company-chip-inline"><span v-html="CI.building"></span> {{ editContactModal.contactName }}</span>
          </div>
          <button class="remark-close" @click="closeEditContactModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <div v-if="editContactModal.loading" class="drawer-loading">Loading…</div>
          <form v-else @submit.prevent="submitEditContact">
            <div v-if="editContactModal.error" class="add-error-box">{{ editContactModal.error }}</div>
            <div class="add-form-group">
              <label>Company Name <span class="req">*</span></label>
              <input v-model="editContactForm.name" @input="checkEditDuplicate" placeholder="Company name" required>
              <div v-if="editContactModal.dupError" class="add-hint error-hint">{{ editContactModal.dupError }}</div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Status</label>
                <select v-model="editContactForm.status_id">
                  <option value="">— No change —</option>
                  <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Type</label>
                <select v-model="editContactForm.type_id">
                  <option value="">— No change —</option>
                  <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Industry</label>
                <select v-model="editContactForm.industry_id">
                  <option value="">— No change —</option>
                  <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>Category / Product</label>
                <select v-model="editContactForm.category_id">
                  <option value="">— No change —</option>
                  <option v-for="cat in lookups.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Area</label>
                <select v-model="editContactForm.area_id">
                  <option value="">— No change —</option>
                  <option v-for="a in lookups.areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
              <div v-if="isAdmin" class="add-form-group">
                <label>Assign To</label>
                <select v-model="editContactForm.user_id">
                  <option value="">— No change —</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Lead Source</label>
                <select v-model="editContactForm.lead_source">
                  <option value="">— Not specified —</option>
                  <option value="manual">Manual Entry</option>
                  <option value="phone_call">Phone Call</option>
                  <option value="referral">Referral</option>
                  <option value="walk_in">Walk-in</option>
                  <option value="social_media">Social Media</option>
                  <option value="email_campaign">Email Campaign</option>
                  <option value="web_form">Web Form</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="add-form-group" style="flex:1">
                <label>Address</label>
                <textarea v-model="editContactForm.address" rows="3" placeholder="Enter address"></textarea>
              </div>
            </div>
            <div class="add-form-group">
              <label>Remarks</label>
              <textarea v-model="editContactForm.remark" rows="3" placeholder="Internal notes about this company…"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeEditContactModal">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="editContactModal.saving || !!editContactModal.dupError">
                {{ editContactModal.saving ? 'Saving…' : 'Save Changes' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── CONTACTS TAB ── -->
    <template v-if="tab === 'contacts'">
      <LoadingSpinner v-if="loading" />
      <div v-else class="table-wrap">
        <div class="table-header-bar">
          <span class="record-count">
            <span class="count-label">{{ dateLabel }}</span>
            <span class="count-badge">{{ meta.total ?? 0 }} contact(s)</span>
          </span>
        </div>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th class="col-no">#</th>
                <th class="col-date">Date Added</th>
                <th class="col-user">User</th>
                <th class="col-status">Status</th>
                <th class="col-type">Type</th>
                <th class="col-industry">Industry</th>
                <th class="col-name">Company Name</th>
                <th class="col-category">Category</th>
                <th class="col-remark">Remarks</th>
                <th class="col-action">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="contacts.length === 0">
                <td colspan="10" class="empty-state">
                  <div class="empty-icon" v-html="CIL.search"></div>
                  <div class="empty-title">No contacts found</div>
                  <div class="empty-sub">Try adjusting the date or search filters</div>
                </td>
              </tr>
              <tr v-for="(c, idx) in contacts" :key="c.id" class="contact-row" @click="openDrawer(c)" style="cursor:pointer">
                <td class="col-no"><span class="row-num">{{ (meta.from ?? 1) + idx }}</span></td>
                <td class="col-date"><span class="date-text">{{ fmtDate(c.created_at) }}</span></td>
                <td class="col-user"><span class="user-name">{{ c.user?.name ?? '—' }}</span></td>
                <td class="col-status">
                  <span class="badge badge-status" :class="statusClass(c.status?.name)">{{ c.status?.name ?? '—' }}</span>
                </td>
                <td class="col-type"><span class="tag">{{ c.type?.name ?? '—' }}</span></td>
                <td class="col-industry">{{ c.industry?.name ?? '—' }}</td>
                <td class="col-name">
                  <span class="company-link">{{ c.name }}</span>
                  <span v-if="c.is_permanently_closed" class="closed-badge">Closed</span>
                </td>
                <td class="col-category"><span class="tag tag-category">{{ c.category?.name ?? '—' }}</span></td>
                <td class="col-remark" @click.stop>
                  <button v-if="c.remark" class="remark-inline-btn" @click="showRemark(c)" :title="'Click to expand'">
                    <span class="remark-text-preview">{{ c.remark }}</span>
                  </button>
                  <span v-else class="muted-dash">—</span>
                </td>
                <td class="col-action" @click.stop>
                  <div class="action-btns">
                    <button v-if="can('create todos')" class="action-chip chip-task" title="Add Task" @click="openAddTaskModal(c)">
                      <span class="chip-icon" v-html="CI.list"></span>
                      <span class="chip-label">Task</span>
                    </button>
                    <button v-if="can('edit contacts') && c.can_edit" class="action-chip chip-edit" title="Edit Contact" @click="openEditContactModal(c)">
                      <span class="chip-icon" v-html="CI.edit"></span>
                      <span class="chip-label">Edit</span>
                    </button>
                    <button v-if="can('delete contacts') && c.can_edit" class="action-chip chip-delete" title="Delete Contact" @click="openDeleteModal(c)">
                      <span class="chip-icon" v-html="CI.trash"></span>
                      <span class="chip-label">Del</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="(meta.total ?? 0) > 0" class="pagination">
          <span class="pagination-info">Showing {{ contacts.length }} of {{ meta.total }} contacts</span>
          <div class="pagination-btns">
            <button class="page-nav" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <template v-for="pg in pageNumbers" :key="'pg-' + pg">
              <button
                v-if="pg !== '...'"
                class="page-num"
                :class="{ 'page-num--on': pg === meta.current_page }"
                @click="load(pg)"
              >{{ pg }}</button>
              <span v-else class="page-ellipsis">…</span>
            </template>
            <button class="page-nav" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── SUMMARY TAB ── -->
    <template v-else-if="tab === 'summary'">
      <LoadingSpinner v-if="summaryLoading" />
      <div v-else class="table-wrap">
        <div class="table-header-bar">
          <span class="record-count">
            <span class="count-label">Activity {{ summaryYear }}</span>
            <span class="count-badge">{{ summaryMeta.total ?? summaryContacts.length }} contact(s)</span>
          </span>
          <div class="summary-legend">
            <span class="legend-dot dot-completed"></span><span class="legend-label">Contacted</span>
            <span class="legend-dot dot-cancelled"></span><span class="legend-label">Cancelled</span>
            <span class="legend-dot" style="background:#e2e8f0"></span><span class="legend-label">No activity</span>
          </div>
        </div>
        <div class="table-scroll">
          <table class="summary-table">
            <thead>
              <tr>
                <th class="col-check"><input type="checkbox" @change="toggleAllSummary" ref="summarySelectAllRef"></th>
                <th class="sum-no-col">#</th>
                <th class="sum-name-col">Company</th>
                <th class="sum-user-col">User</th>
                <th class="sum-status-col">Status</th>
                <th class="sum-activity-col">Activity {{ summaryYear }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="summaryContacts.length === 0">
                <td colspan="6" class="empty-state">
                  <div class="empty-icon" v-html="CIL.chart"></div>
                  <div class="empty-title">No records found</div>
                  <div class="empty-sub">Try adjusting filters or the year</div>
                </td>
              </tr>
              <tr v-for="(c, idx) in summaryContacts" :key="c.id" class="contact-row" @click="openDrawer(c)" style="cursor:pointer">
                <td class="col-check" @click.stop><input type="checkbox" :value="c.id" v-model="summarySelectedIds"></td>
                <td class="sum-no-col"><span class="row-num">{{ idx + 1 }}</span></td>
                <td class="sum-name-col"><span class="sum-company-link">{{ c.name }}</span></td>
                <td class="sum-user-col"><span class="user-name">{{ c.user ?? '—' }}</span></td>
                <td class="sum-status-col">
                  <span class="badge badge-status" :class="statusClass(c.status)">{{ c.status ?? '—' }}</span>
                </td>
                <td class="sum-activity-cell">
                  <div class="sum-act-meta">
                    <span v-if="summaryActiveMonths(c)" class="active-badge">{{ summaryActiveMonths(c) }}/12</span>
                    <span v-if="summaryLastContact(c)" class="sum-lc-inline">Last: {{ summaryLastContact(c).date }} · {{ summaryLastContact(c).task }}</span>
                    <span v-else class="sum-lc-inline muted-dash">No activity this year</span>
                  </div>
                  <div class="sum-act-grid">
                    <div
                      v-for="m in 12" :key="m"
                      class="sum-month-cell"
                      :class="c.months[m] ? (c.months[m].status === 'cancelled' ? 'smc-cancelled' : 'smc-active') : 'smc-empty'"
                      :title="c.months[m] ? `${MONTH_NAMES[m-1]}: ${c.months[m].date} — ${c.months[m].task} (${c.months[m].status})` : MONTH_NAMES[m-1]"
                    >
                      <span class="smc-name">{{ MONTH_NAMES[m-1] }}</span>
                      <template v-if="c.months[m]">
                        <span class="smc-date">{{ c.months[m].date.slice(0, 5) }}</span>
                        <span class="smc-task">{{ c.months[m].task }}</span>
                      </template>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="(summaryMeta.total ?? 0) > 0" class="pagination">
          <span class="pagination-info">Showing {{ summaryContacts.length }} of {{ summaryMeta.total }} contacts</span>
          <div class="pagination-btns">
            <button class="page-nav" :disabled="summaryMeta.current_page <= 1" @click="summaryChangePage(summaryMeta.current_page - 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <template v-for="pg in summaryPageNumbers" :key="'spg-' + pg">
              <button
                v-if="pg !== '...'"
                class="page-num"
                :class="{ 'page-num--on': pg === summaryMeta.current_page }"
                @click="summaryChangePage(pg)"
              >{{ pg }}</button>
              <span v-else class="page-ellipsis">…</span>
            </template>
            <button class="page-nav" :disabled="summaryMeta.current_page >= summaryMeta.last_page" @click="summaryChangePage(summaryMeta.current_page + 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── TASKS TAB ── -->
    <template v-else-if="tab === 'tasks'">
      <LoadingSpinner v-if="todoLoading" />
      <div v-else class="table-wrap">
        <div class="table-header-bar">
          <span class="record-count">
            <span class="count-label">{{ todoPeriodLabel }}</span>
            <span class="count-badge">{{ todoMeta.total ?? todos.length }} task(s)</span>
          </span>
        </div>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th class="col-no">#</th>
                <th style="width:100px">To Do Date</th>
                <th class="col-status">Status</th>
                <th class="col-name">Company</th>
                <th class="col-user">User</th>
                <th style="width:110px">Task</th>
                <th>Remark</th>
                <th style="width:60px;text-align:center">Done</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="todos.length === 0">
                <td colspan="8" class="empty-state">
                  <div class="empty-icon" v-html="CIL.clipboard"></div>
                  <div class="empty-title">No tasks found</div>
                  <div class="empty-sub">Try a different date or search filter</div>
                </td>
              </tr>
              <tr v-for="(t, idx) in todos" :key="t.id" :class="{ 'row-done': t.completion_status === 'completed' }">
                <td class="col-no"><span class="row-num">{{ todoMeta.from ? todoMeta.from + idx : idx + 1 }}</span></td>
                <td><span class="date-text">{{ t.todo_date }}</span></td>
                <td class="col-status">
                  <span class="badge badge-status" :class="statusClass(t.status)">{{ t.status ?? '—' }}</span>
                </td>
                <td class="col-name">
                  <button class="task-company-btn" @click="openDrawer({ id: t.contact_id })">{{ t.contact_name }}</button>
                </td>
                <td class="col-user"><span class="user-name">{{ t.user ?? '—' }}</span></td>
                <td><span v-if="t.task" class="dtask-badge">{{ t.task }}</span><span v-else class="muted-dash">—</span></td>
                <td style="font-size:12px;white-space:pre-line;color:#374151">{{ t.todo_remark || '—' }}</td>
                <td style="text-align:center">
                  <button v-if="t.completion_status !== 'completed'" class="todo-done-btn" title="Mark complete" @click="markTodoDone(t)" v-html="CI.check"></button>
                  <button v-else class="todo-undo-btn" title="Mark pending" @click="markTodoPending(t)" v-html="CI.rotateCcw"></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="todoMeta.last_page > 1" class="pagination">
          <button :disabled="todoMeta.current_page <= 1" @click="todoChangePage(todoMeta.current_page - 1)"><span v-html="CI.chevronLeft" style="display:inline-flex;align-items:center"></span> Prev</button>
          <span>Page {{ todoMeta.current_page }} of {{ todoMeta.last_page }}</span>
          <button :disabled="todoMeta.current_page >= todoMeta.last_page" @click="todoChangePage(todoMeta.current_page + 1)">Next <span v-html="CI.chevronRight" style="display:inline-flex;align-items:center"></span></button>
        </div>
      </div>
    </template>

    <!-- ── FORECAST TAB ── -->
    <template v-else>
      <LoadingSpinner v-if="forecastLoading" />
      <div v-else class="table-wrap">
        <div class="table-header-bar">
          <span class="record-count">
            <span class="count-label">Forecasts</span>
            <span class="count-badge">{{ forecastMeta.total ?? forecasts.length }} forecast(s)</span>
          </span>
          <div class="forecast-stats">
            <span class="fstat-chip"><strong>{{ fmtCurrency(forecastSummary.total_amount) }}</strong><small>Total</small></span>
            <span class="fstat-chip fstat-confirmed"><strong>{{ fmtCurrency(forecastSummary.confirmed_amount) }}</strong><small>Confirmed</small></span>
            <span class="fstat-chip fstat-pending"><strong>{{ fmtCurrency(forecastSummary.pending_amount) }}</strong><small>Pending</small></span>
          </div>
        </div>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th class="col-no">#</th>
                <th class="col-name">Company</th>
                <th>Product</th>
                <th>Type</th>
                <th class="fcol-amount">Amount</th>
                <th class="col-date">Forecast Date</th>
                <th>Result</th>
                <th class="col-user">Assigned</th>
                <th class="col-date">Updated</th>
                <th class="col-action">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="forecasts.length === 0">
                <td colspan="10" class="empty-state">
                  <div class="empty-icon" v-html="CIL.trending"></div>
                  <div class="empty-title">No forecasts found</div>
                  <div class="empty-sub">Try adjusting filters or add a new forecast</div>
                </td>
              </tr>
              <tr v-for="(f, idx) in forecasts" :key="f.id" class="contact-row" @click="openDrawerById(f.contact_id)" style="cursor:pointer">
                <td class="col-no"><span class="row-num">{{ (forecastMeta.from ?? 1) + idx }}</span></td>
                <td class="col-name"><span class="company-link">{{ f.contact_name ?? '—' }}</span></td>
                <td>{{ f.product_name ?? '—' }}</td>
                <td><span class="tag">{{ f.forecast_type_name ?? '—' }}</span></td>
                <td class="fcol-amount fcast-amount">{{ fmtCurrency(f.amount) }}</td>
                <td class="col-date"><span class="date-text">{{ fmtDate(f.forecast_date) }}</span></td>
                <td><span class="result-badge" :class="resultClass(f.result_name)">{{ f.result_name ?? 'No Result' }}</span></td>
                <td class="col-user"><span class="user-name">{{ f.user_name ?? '—' }}</span></td>
                <td class="col-date"><span class="date-text">{{ fmtDate(f.forecast_updatedate) }}</span></td>
                <td class="col-action" @click.stop>
                  <div class="action-btns">
                    <button class="icon-btn btn-edit" title="Edit Forecast" @click="openForecastEdit(f.id)" v-html="CI.edit"></button>
                    <button class="icon-btn btn-delete" title="Delete Forecast" @click="openForecastDeleteModal(f)" v-html="CI.trash"></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="forecastMeta.last_page > 1" class="pagination">
          <button :disabled="forecastMeta.current_page <= 1" @click="forecastChangePage(forecastMeta.current_page - 1)"><span v-html="CI.chevronLeft" style="display:inline-flex;align-items:center"></span> Prev</button>
          <span>Page {{ forecastMeta.current_page }} of {{ forecastMeta.last_page }}</span>
          <button :disabled="forecastMeta.current_page >= forecastMeta.last_page" @click="forecastChangePage(forecastMeta.current_page + 1)">Next <span v-html="CI.chevronRight" style="display:inline-flex;align-items:center"></span></button>
        </div>
      </div>
    </template>

    <!-- Export Modal -->
    <div v-if="exportModal.open" class="remark-overlay">
      <div class="export-modal">
        <div class="export-modal-header">
          <div>
            <strong class="export-modal-title">Export Contacts</strong>
            <p class="export-modal-sub">Pick what to include, then download.</p>
          </div>
          <button class="remark-close" @click="exportModal.open = false" v-html="CI.x"></button>
        </div>

        <div class="export-modal-body">

          <!-- Column picker -->
          <div class="export-section">
            <div class="export-cols-head">
              <span class="export-section-label" style="margin-bottom:0">Columns to include</span>
              <div class="export-cols-actions">
                <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = true)">All</button>
                <span class="export-dot-sep">·</span>
                <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = false)">None</button>
              </div>
            </div>
            <div class="export-cols-grid">
              <label v-for="col in exportCols" :key="col.key" class="export-col-check">
                <input type="checkbox" v-model="col.checked">
                <span>{{ col.label }}</span>
              </label>
            </div>
          </div>

        </div>

        <div class="export-modal-footer">
          <p class="export-footer-count">
            Will export <strong>{{ exportRowCount }}</strong> contact(s) × <strong>{{ exportCols.filter(c => c.checked).length }}</strong> column(s)
          </p>
          <div class="export-action-stack">
            <button
              class="export-dl-btn export-dl-xls"
              :disabled="exportModal.loading || exportCols.every(c => !c.checked)"
              @click="executeExport('xls')"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon">
                <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="export-dl-text">
                <span class="export-dl-label">{{ exportModal.loading ? 'Exporting…' : 'Download Excel' }}</span>
                <span class="export-dl-desc">Formatted with borders &amp; column widths</span>
              </span>
            </button>
            <button
              class="export-dl-btn export-dl-csv"
              :disabled="exportModal.loading || exportCols.every(c => !c.checked)"
              @click="executeExport('csv')"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon">
                <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="export-dl-text">
                <span class="export-dl-label">Download CSV</span>
                <span class="export-dl-desc">Plain text, opens in any spreadsheet app</span>
              </span>
            </button>
            <button class="export-cancel-btn" @click="exportModal.open = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Forecast delete confirmation -->
    <div v-if="forecastDeleteModal.show" class="remark-overlay">
      <div class="remark-modal delete-modal">
        <div class="remark-modal-header delete-modal-header">
          <strong>Delete Forecast</strong>
          <button class="remark-close" @click="closeForecastDeleteModal" v-html="CI.x"></button>
        </div>
        <div class="delete-modal-body">
          <div class="delete-warning-icon" v-html="CIL.warning"></div>
          <p class="delete-warning-text">
            Delete forecast for <strong>{{ forecastDeleteModal.forecast?.contact_name }}</strong>
            ({{ fmtCurrency(forecastDeleteModal.forecast?.amount) }})?
            This cannot be undone.
          </p>
          <div class="delete-modal-actions">
            <button class="btn btn-clear" @click="closeForecastDeleteModal">Cancel</button>
            <button class="btn btn-danger" :disabled="forecastDeleteModal.loading" @click="confirmForecastDelete">
              {{ forecastDeleteModal.loading ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Task (todo) delete confirmation -->
    <div v-if="followUpPrompt.open" class="remark-overlay">
      <div class="remark-modal delete-modal">
        <div class="remark-modal-header delete-modal-header">
          <strong>Pending Follow-Ups</strong>
          <button class="remark-close" @click="dismissFollowUpPrompt" v-html="CI.x"></button>
        </div>
        <div class="delete-modal-body">
          <div class="delete-warning-icon delete-info-icon" v-html="CIL.info"></div>
          <p class="delete-warning-text">
            This to-do has <strong>{{ followUpPrompt.count }}</strong> pending follow-up{{ followUpPrompt.count !== 1 ? 's' : '' }}.
            Mark {{ followUpPrompt.count !== 1 ? 'them' : 'it' }} as complete too?
          </p>
          <div class="delete-modal-actions">
            <button class="btn btn-clear" @click="dismissFollowUpPrompt">Skip</button>
            <button class="btn btn-primary" :disabled="followUpPrompt.loading" @click="completeFollowUps">
              {{ followUpPrompt.loading ? 'Completing…' : 'Yes, mark complete' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="todoDeleteModal.show" class="remark-overlay">
      <div class="remark-modal delete-modal">
        <div class="remark-modal-header delete-modal-header">
          <strong>Delete Task</strong>
          <button class="remark-close" @click="closeTodoDeleteModal" v-html="CI.x"></button>
        </div>
        <div class="delete-modal-body">
          <div class="delete-warning-icon" v-html="CIL.warning"></div>
          <p class="delete-warning-text">
            Delete <strong>{{ todoDeleteModal.todo?.task?.name ?? 'this task' }}</strong>
            on <strong>{{ fmtDate(todoDeleteModal.todo?.todo_date) }}</strong>?
            This also removes all linked follow-ups.
          </p>
          <div class="delete-modal-actions">
            <button class="btn btn-clear" @click="closeTodoDeleteModal">Cancel</button>
            <button class="btn btn-danger" :disabled="todoDeleteModal.loading" @click="confirmTodoDelete">
              {{ todoDeleteModal.loading ? 'Deleting…' : 'Delete Task' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Shared Forecast Form Modal -->
    <ForecastFormModal
      :open="forecastModal.open"
      :mode="forecastModal.mode"
      :forecast-id="forecastModal.forecastId"
      :prefilled-contact="forecastModal.prefilledContact"
      @close="closeForecastModal"
      @saved="onForecastSaved"
    />

  <!-- Mark as Closed confirm modal -->
  <Teleport to="body">
    <div v-if="closedDrawerModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Mark as Permanently Closed</p>
            <p class="conf-sub">This contact will be flagged as a closed business.</p>
          </div>
          <button class="conf-close" @click="closedDrawerModal.open = false">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Flag <strong>{{ drawer.contact?.name }}</strong> as permanently closed? You can undo this at any time.</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closedDrawerModal.open = false">Cancel</button>
          <button class="conf-delete" :disabled="closedDrawerModal.loading" @click="toggleDrawerClosed">
            {{ closedDrawerModal.loading ? 'Saving…' : 'Mark as Closed' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

    <!-- Toast notifications -->
    <div class="toast-container">
      <transition-group name="toast" tag="div" class="toast-list">
        <div v-for="t in toasts" :key="t.id" :class="['toast-item', `toast-${t.type}`]">
          <span class="toast-check" v-html="t.type === 'success' ? CI.check : CI.x"></span>
          <span class="toast-text">{{ t.message }}</span>
          <button class="toast-dismiss" @click="dismissToast(t.id)" v-html="CI.x"></button>
        </div>
      </transition-group>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import CalendarPicker from '../components/CalendarPicker.vue';
import ForecastFormModal from '../components/ForecastFormModal.vue';
import { usePermissions } from '../composables/usePermissions.js';

const { can, isAdmin } = usePermissions();

const router = useRouter();
const route  = useRoute();

// ── Toast notifications ──
const toasts = ref([]);
let _toastSeq = 0;
function showToast(message, type = 'success') {
  const id = ++_toastSeq;
  toasts.value.push({ id, message, type });
  setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 3000);
}
function dismissToast(id) { toasts.value = toasts.value.filter(t => t.id !== id); }

// ── Icons (same SVG style as the sidebar) ──
const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const ICO = {
  list:        (sz) => _si('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>', sz),
  chart:       (sz) => _si('<rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>', sz),
  clipboard:   (sz) => _si('<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/>', sz),
  trending:    (sz) => _si('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>', sz),
  eye:         (sz) => _si('<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>', sz),
  edit:        (sz) => _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>', sz),
  trash:       (sz) => _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>', sz),
  warning:     (sz) => _si('<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>', sz),
  building:    (sz) => _si('<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', sz),
  search:      (sz) => _si('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>', sz),
  message:     (sz) => _si('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', sz),
  bell:        (sz) => _si('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>', sz),
  chevronLeft: (sz) => _si('<polyline points="15 18 9 12 15 6"/>', sz),
  chevronRight:(sz) => _si('<polyline points="9 18 15 12 9 6"/>', sz),
  calendar:    (sz) => _si('<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', sz),
  user:        (sz) => _si('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', sz),
  arrowUp:     (sz) => _si('<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>', sz),
  arrowDown:   (sz) => _si('<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>', sz),
  check:       (sz) => _si('<polyline points="20 6 9 17 4 12"/>', sz),
  rotateCcw:   (sz) => _si('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.63"/>', sz),
  info:        (sz) => _si('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>', sz),
  x:           (sz) => _si('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>', sz),
  phone:       (sz) => _si('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.72 12 19.79 19.79 0 0 1 1.62 3.4A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8a16 16 0 0 0 6 6l.77-.77a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16z"/>', sz),
};
const CI  = Object.fromEntries(Object.entries(ICO).map(([k, fn]) => [k, fn(14)]));
const CIL = Object.fromEntries(Object.entries(ICO).map(([k, fn]) => [k, fn(40)]));

// ── Tab ──
const tab = ref('contacts');

function switchTab(newTab) {
  tab.value = newTab;
  router.replace({ query: newTab !== 'contacts' ? { tab: newTab } : {} });
  if (newTab === 'summary') { summaryPage.value = 1; loadSummary(); }
  else if (newTab === 'tasks') {
    loadTodos();
    const [y, m] = todoDate.value.split('-').map(Number);
    loadTodoMarkedDates(y, m);
  }
  else if (newTab === 'forecast') loadForecasts();
}

const bannerTitle = computed(() => {
  if (tab.value === 'summary') return 'Activity Summary';
  if (tab.value === 'tasks') return 'To-Do List';
  if (tab.value === 'forecast') return 'Forecasts';
  return 'List of Contacts';
});
const bannerSub = computed(() => {
  if (tab.value === 'summary') return 'Track contact engagement across months and years';
  if (tab.value === 'tasks') return 'View, manage and complete to-dos across all contacts';
  if (tab.value === 'forecast') return 'Track forecasted revenue by company, product, type and result';
  return 'Browse, manage and add new contacts';
});

// ── Contacts tab state ──
const dateFrom    = ref('');
const dateTo      = ref('');
const search      = ref('');
const userId      = ref('');
const statusId    = ref('');
const typeId      = ref('');
const categoryId  = ref('');
const sort        = ref('desc');
const contacts = ref([]);
const meta     = ref({});
const loading  = ref(false);
const contactsPage    = ref(1);
const contactsPerPage = ref(25);
const CONTACT_PER_PAGE_OPTIONS = [10, 25, 50, 100];

// ── Search autocomplete ──
const suggestions     = ref([]);
const showSuggestions = ref(false);
let _suggestTimer = null;

function onSearchInput() {
  clearTimeout(_suggestTimer);
  if (!search.value.trim()) {
    suggestions.value = [];
    showSuggestions.value = false;
    return;
  }
  _suggestTimer = setTimeout(async () => {
    try {
      const res = await api.get('/v1/contacts/daily', { params: { search: search.value, per_page: 8, sort: 'desc' } });
      suggestions.value = (res.data.data ?? []).map(c => ({ id: c.id, name: c.name }));
      showSuggestions.value = suggestions.value.length > 0;
    } catch { /* silent */ }
  }, 250);
}

function pickSuggestion(name) {
  search.value = name;
  showSuggestions.value = false;
  load();
}

function onSearchBlur() {
  setTimeout(() => { showSuggestions.value = false; }, 160);
}

// ── Summary tab state ──
const summaryYear         = ref(new Date().getFullYear());
const summaryYears        = Array.from({ length: 7 }, (_, i) => 2024 + i);
const summaryContacts     = ref([]);
const summaryLoading      = ref(false);
const summaryFilters      = ref({ search: '', user_id: '', status_id: '', type_id: '', category_id: '', industry_id: '' });
const summarySelectedIds  = ref([]);
const summarySelectAllRef = ref(null);
const summaryPage         = ref(1);
const summaryMeta         = ref({});

// ── Tasks tab state ──
const todoView    = ref('Day');
const todoDate    = ref((() => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; })());
const todoSearch  = ref('');
const todoUserId  = ref('');
const todoStatus  = ref('pending');
const todoPerPage = ref(50);
const todoPage    = ref(1);
const todos       = ref([]);
const todoMeta    = ref({});
const todoLoading    = ref(false);
const todoMarked     = ref([]);
const todoMarkLoading = ref(false);

// ── Forecast tab state ──
const forecasts        = ref([]);
const forecastMeta     = ref({});
const forecastSummary  = ref({});
const forecastLoading  = ref(false);
const forecastPage     = ref(1);
const forecastLookups  = ref({ forecast_products: [], forecast_types: [], forecast_results: [] });
const forecastFilters  = ref({ q: '', product_id: '', forecast_type_id: '', result_id: '', user_id: '', from_date: '', to_date: '' });
const forecastResultOptions = computed(() =>
  (forecastLookups.value.forecast_results ?? []).filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
);
const forecastModal = ref({ open: false, mode: 'add', forecastId: null, prefilledContact: null });
const forecastDeleteModal = ref({ show: false, forecast: null, loading: false });
const todoDeleteModal = ref({ show: false, todo: null, loading: false });

// ── Shared ──
const users   = ref([]);
const lookups = ref({ statuses: [], types: [], categories: [], industries: [], tasks: [], areas: [] });

// ── Modals & drawer ──
const remarkModal = ref({ show: false, company: '', text: '' });
const drawer      = ref({ open: false, loading: false, contact: null });
const deleteModal = ref({ show: false, contact: null, input: '', loading: false });
const deleteInput = ref(null);

// ── Add task inline ──
const addTaskOpen   = ref(false);
const addTaskSaving = ref(false);
const addTaskError  = ref('');
const addTaskForm   = ref({ task_id: '', todo_date: '', todo_remark: '' });

// ── Follow-up modals ──
const FOLLOWUP_ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];
// Header "Follow-Up" button — generic, user picks a task from dropdown
const followUpModal     = ref({ open: false, saving: false, error: '' });
const followUpModalForm = ref({ todo_id: '', followup_date: '', action_type: '', note: '' });
// Task-row "📞 N" badge — shows history + inline log form for that specific task
const taskFuModal = ref({ open: false, todo: null, saving: false, error: '' });
const taskFuForm  = ref({ followup_date: '', action_type: '', note: '' });

// ── Quick Add Task modal (from row action) ──
const addTaskModal     = ref({ open: false, contact: null, saving: false, error: '' });
const addTaskModalForm = ref({ task_id: '', todo_date: '', todo_remark: '' });

// ── Quick Edit Contact modal (from row action) ──
const editContactModal = ref({ open: false, contactId: null, contactName: '', loading: false, saving: false, error: '', dupError: '' });
const editContactForm  = ref({ name: '', status_id: '', type_id: '', industry_id: '', category_id: '', area_id: '', address: '', lead_source: '', remark: '', user_id: '' });
let editDupTimer = null;

// ── Add Contact modal ──
const addModal      = ref({ open: false });
const addStep       = ref(1);
const addSaving     = ref(false);
const addSubmitError = ref('');
const addDupError   = ref('');
let addDupTimer = null;
const addForm = ref({ name: '', status_id: '', industry_id: '', type_id: '', category_id: '', area_id: '', address: '', created_at: new Date().toISOString().slice(0, 10), lead_source: 'manual', user_id: '' });
const addPic  = ref({ name: '', phone: '', email: '', office: '' });

// ── Drawer month activity ──
const MONTH_NAMES = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
const drawerYear  = ref(new Date().getFullYear());
const drawerYears = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

const drawerMonthMap = computed(() => {
  const todos = drawer.value.contact?.todos;
  if (!todos) return {};
  const map = {};
  for (const t of todos) {
    const d = new Date(t.todo_date);
    if (d.getFullYear() !== drawerYear.value) continue;
    const m = d.getMonth() + 1;
    if (!map[m]) map[m] = { date: fmtDate(t.todo_date), task: t.task?.name ?? '—', remark: t.todo_remark ?? '' };
  }
  return map;
});

// ── Computed ──
const hasFilters = computed(() => dateFrom.value || dateTo.value || search.value || userId.value || statusId.value || typeId.value || categoryId.value);

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)          return [1, 2, 3, '...', total];
  if (cur >= total - 2)  return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

const summaryPageNumbers = computed(() => {
  const total = summaryMeta.value.last_page ?? 1;
  const cur   = summaryMeta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)          return [1, 2, 3, '...', total];
  if (cur >= total - 2)  return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

const dateLabel = computed(() => {
  if (!dateFrom.value && !dateTo.value) return 'All Contacts';
  const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  if (dateFrom.value && dateTo.value) return `${fmt(dateFrom.value)} – ${fmt(dateTo.value)}`;
  if (dateFrom.value) return `From ${fmt(dateFrom.value)}`;
  return `Until ${fmt(dateTo.value)}`;
});

const todoPeriodLabel = computed(() => {
  const d = new Date(todoDate.value + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

const todoNavLabel = computed(() => {
  const d = new Date(todoDate.value + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
});

// ── Helpers ──
function mapsUrl(c) {
  const q = encodeURIComponent([c.name, c.address].filter(Boolean).join(', '));
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function fmtCurrency(value) {
  const n = Number(value ?? 0);
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

function statusClass(status) {
  if (!status) return '';
  const s = status.toLowerCase();
  if (s.includes('active') || s.includes('prospect')) return 'status-active';
  if (s.includes('inactive') || s.includes('lost')) return 'status-inactive';
  if (s.includes('lead') || s.includes('new')) return 'status-lead';
  if (s.includes('client') || s.includes('customer')) return 'status-client';
  return 'status-default';
}

function resultClass(name) {
  const key = (name ?? 'No Result').toLowerCase().replace(/\s+/g, '-');
  return `result-${key}`;
}

// ── Contacts tab ──
async function load(page = 1) {
  contactsPage.value = page;
  loading.value = true;
  showSuggestions.value = false;
  try {
    const params = { sort: sort.value, per_page: contactsPerPage.value, page: contactsPage.value };
    if (dateFrom.value)   params.date_from    = dateFrom.value;
    if (dateTo.value)     params.date_to      = dateTo.value;
    if (search.value)     params.search       = search.value;
    if (userId.value)     params.user_id      = userId.value;
    if (statusId.value)   params.status_id    = statusId.value;
    if (typeId.value)     params.type_id      = typeId.value;
    if (categoryId.value) params.category_id  = categoryId.value;
    const res = await api.get('/v1/contacts/daily', { params });
    contacts.value = res.data.data;
    meta.value = res.data.meta ?? {
      current_page: res.data.current_page ?? 1,
      last_page:    res.data.last_page    ?? 1,
      total:        res.data.total        ?? res.data.data?.length ?? 0,
      from:         res.data.from         ?? 1,
    };
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  dateFrom.value = ''; dateTo.value = ''; search.value = '';
  userId.value = ''; statusId.value = ''; typeId.value = ''; categoryId.value = '';
  sort.value = 'desc';
  contactsPerPage.value = 25;
  suggestions.value = []; showSuggestions.value = false;
  load(1);
}

// ── Export ──
const EXPORT_COLUMNS = [
  { key: 'no',          label: 'No',           width: 45  },
  { key: 'date_added',  label: 'Date Added',   width: 110 },
  { key: 'user',        label: 'User',         width: 130 },
  { key: 'status',      label: 'Status',       width: 110 },
  { key: 'type',        label: 'Type',         width: 90  },
  { key: 'industry',    label: 'Industry',     width: 130 },
  { key: 'company',     label: 'Company Name', width: 220 },
  { key: 'category',    label: 'Category',     width: 130 },
  { key: 'address',     label: 'Address',      width: 220 },
  { key: 'remarks',     label: 'Remarks',      width: 220 },
  { key: 'pic_names',   label: 'PIC Name(s)',  width: 180 },
  { key: 'pic_emails',  label: 'Email(s)',     width: 200 },
  { key: 'pic_mobiles', label: 'Mobile(s)',    width: 150 },
  { key: 'pic_offices', label: 'Office(s)',    width: 150 },
];

const exportModal = ref({ open: false, loading: false });
const exportCols  = ref(EXPORT_COLUMNS.map(c => ({ ...c, checked: true })));

const exportRowCount = computed(() => 'all');

function openExportModal() {
  exportModal.value.open = true;
}

function getCellValue(c, key, idx) {
  const pics = c.incharges ?? [];
  switch (key) {
    case 'no':          return idx + 1;
    case 'date_added':  return fmtDate(c.created_at);
    case 'user':        return c.user?.name ?? '—';
    case 'status':      return c.status?.name ?? '—';
    case 'type':        return c.type?.name ?? '—';
    case 'industry':    return c.industry?.name ?? '—';
    case 'address':     return c.address ?? '—';
    case 'company':     return c.name ?? '—';
    case 'category':    return c.category?.name ?? '—';
    case 'remarks':     return c.remark ?? '—';
    case 'pic_names':   return pics.length ? pics.map(p => p.name || '—').join('; ') : '—';
    case 'pic_emails':  return pics.length ? pics.map(p => p.email || '—').join('; ') : '—';
    case 'pic_mobiles': return pics.length ? pics.map(p => p.phone_mobile || '—').join('; ') : '—';
    case 'pic_offices': return pics.length ? pics.map(p => p.phone_office || '—').join('; ') : '—';
    default:            return '';
  }
}

function escHtml(v) {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function executeExport(format = 'xls') {
  const selected = exportCols.value.filter(c => c.checked);
  if (!selected.length) return;
  exportModal.value.loading = true;
  const PIC_KEYS = ['pic_names', 'pic_emails', 'pic_mobiles', 'pic_offices'];
  const needsIncharges = selected.some(c => PIC_KEYS.includes(c.key));
  try {
    const qs = new URLSearchParams({ cols: selected.map(c => c.key).join(','), sort: sort.value, format });
    if (needsIncharges) qs.set('with_incharges', '1');

    const token = localStorage.getItem('crm_token');
    const resp  = await fetch(`/api/v1/contacts/export?${qs}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!resp.ok) throw new Error(`Server error ${resp.status}`);

    const date = new Date().toISOString().slice(0, 10);
    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `Contacts_${date}.${format}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 500);
    exportModal.value.open = false;
    showToast(`Downloaded as ${format.toUpperCase()}`);
  } catch (err) {
    console.error('[Export]', err);
    showToast(`Export failed: ${err.message}`, 'error');
  } finally {
    exportModal.value.loading = false;
  }
}

function showRemark(contact) {
  remarkModal.value = { show: true, company: contact.name, text: contact.remark };
}

// ── Summary tab ──
async function loadSummary() {
  summaryLoading.value = true;
  summarySelectedIds.value = [];
  if (summarySelectAllRef.value) summarySelectAllRef.value.checked = false;
  try {
    const params = {
      year: summaryYear.value,
      page: summaryPage.value,
      per_page: 50,
      ...Object.fromEntries(Object.entries(summaryFilters.value).filter(([, v]) => v)),
    };
    const res = await api.get('/v1/summary', { params });
    summaryContacts.value = res.data.data;
    summaryMeta.value = res.data.meta ?? {};
  } finally {
    summaryLoading.value = false;
  }
}

function applySummaryFilters() {
  summaryPage.value = 1;
  loadSummary();
}

function summaryChangePage(p) {
  summaryPage.value = p;
  loadSummary();
}

function summaryLastContact(c) {
  const keys = Object.keys(c.months).map(Number);
  if (!keys.length) return null;
  return c.months[Math.max(...keys)];
}

function summaryActiveMonths(c) {
  return Object.keys(c.months).length;
}

function resetSummaryFilters() {
  summaryFilters.value = { search: '', user_id: '', status_id: '', type_id: '', category_id: '', industry_id: '' };
  summaryPage.value = 1;
  loadSummary();
}

function toggleAllSummary(e) {
  summarySelectedIds.value = e.target.checked ? summaryContacts.value.map(c => c.id) : [];
}

function exportSummary() {
  const rows = summarySelectedIds.value.length
    ? summaryContacts.value.filter(c => summarySelectedIds.value.includes(c.id))
    : summaryContacts.value;

  const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const headers = ['No','User','Status','Type','Category','Industry','Company','Last Contact Date','Last Contact Task','Active Months',...MONTHS];
  const lines = [headers];

  rows.forEach((c, i) => {
    const lc = summaryLastContact(c);
    const monthCols = Array.from({ length: 12 }, (_, m) => {
      const d = c.months[m + 1];
      return d ? `${d.date} - ${d.task} (${d.status})` : '—';
    });
    lines.push([i + 1, c.user ?? '—', c.status ?? '—', c.type ?? '—', c.category ?? '—', c.industry ?? '—', c.name, lc?.date ?? '—', lc?.task ?? '—', `${summaryActiveMonths(c)}/12`, ...monthCols]);
  });

  const csv = '﻿' + lines.map(row => row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `Summary_${summaryYear.value}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Tasks tab ──
async function loadTodos() {
  todoLoading.value = true;
  try {
    const params = { view: todoView.value, date: todoDate.value, per_page: todoPerPage.value, page: todoPage.value };
    if (todoSearch.value)  params.search             = todoSearch.value;
    if (todoUserId.value)  params.user_id            = todoUserId.value;
    if (todoStatus.value)  params.completion_status  = todoStatus.value;
    const res = await api.get('/v1/todos', { params });
    todos.value    = res.data.data;
    todoMeta.value = res.data.meta ?? {};
  } finally {
    todoLoading.value = false;
  }
}

function todoChangePage(p) { todoPage.value = p; loadTodos(); }

function shiftTodoDate(n) {
  const [y, m, day] = todoDate.value.split('-').map(Number);
  const d = new Date(y, m - 1, day + n);
  const newDate = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const oldMonth = todoDate.value.slice(0, 7);
  todoDate.value = newDate;
  loadTodos();
  if (newDate.slice(0, 7) !== oldMonth) {
    loadTodoMarkedDates(d.getFullYear(), d.getMonth() + 1);
  }
}

async function loadTodoMarkedDates(year, month) {
  todoMarkLoading.value = true;
  try {
    const res = await api.get('/v1/todos/active-dates', { params: { year, month } });
    todoMarked.value = res.data.dates ?? [];
  } finally {
    todoMarkLoading.value = false;
  }
}

const followUpPrompt = ref({ open: false, todoId: null, count: 0, loading: false });

async function checkAndPromptFollowUps(todoId) {
  try {
    const res = await api.get('/v1/followups', {
      params: { todo_id: todoId, completion_status: 'pending', per_page: 1, view: 'DateRange', from_date: '2000-01-01', to_date: '2099-12-31' },
    });
    const count = res.data.meta?.total ?? 0;
    if (count > 0) {
      followUpPrompt.value = { open: true, todoId, count, loading: false };
    }
  } catch (_) { /* non-critical */ }
}

async function completeFollowUps() {
  followUpPrompt.value.loading = true;
  try {
    await api.patch(`/v1/todos/${followUpPrompt.value.todoId}/complete-followups`);
  } finally {
    followUpPrompt.value = { open: false, todoId: null, count: 0, loading: false };
  }
}

function dismissFollowUpPrompt() {
  followUpPrompt.value = { open: false, todoId: null, count: 0, loading: false };
}

async function markTodoDone(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'completed' });
  todo.completion_status = 'completed';
  showToast('Task marked complete');
  checkAndPromptFollowUps(todo.id);
}

async function markTodoPending(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'pending' });
  todo.completion_status = 'pending';
  showToast('Task marked pending');
}

// ── Forecast tab ──
function buildForecastParams(includePaging = true) {
  const params = { ...forecastFilters.value, sort_field: 'forecast_date', sort_direction: 'desc' };
  if (includePaging) {
    params.page = forecastPage.value;
    params.per_page = 50;
  }
  Object.keys(params).forEach((k) => {
    if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k];
  });
  return params;
}

async function ensureForecastLookups() {
  if (forecastLookups.value.forecast_products?.length) return;
  const res = await api.get('/v1/lookups');
  forecastLookups.value = {
    forecast_products: res.data.forecast_products ?? [],
    forecast_types: res.data.forecast_types ?? [],
    forecast_results: res.data.forecast_results ?? [],
  };
}

async function loadForecasts() {
  forecastLoading.value = true;
  try {
    await ensureForecastLookups();
    const [listRes, sumRes] = await Promise.all([
      api.get('/v1/forecasts', { params: buildForecastParams() }),
      api.get('/v1/forecasts/summary', { params: buildForecastParams(false) }),
    ]);
    forecasts.value = listRes.data.data ?? [];
    forecastMeta.value = listRes.data ?? {};
    forecastSummary.value = sumRes.data.data?.totals ?? {};
  } finally {
    forecastLoading.value = false;
  }
}

function applyForecastFilters() {
  forecastPage.value = 1;
  loadForecasts();
}

function resetForecastFilters() {
  forecastFilters.value = { q: '', product_id: '', forecast_type_id: '', result_id: '', user_id: '', from_date: '', to_date: '' };
  forecastPage.value = 1;
  loadForecasts();
}

function forecastChangePage(p) {
  forecastPage.value = p;
  loadForecasts();
}

function openForecastAdd(contact = null) {
  forecastModal.value = { open: true, mode: 'add', forecastId: null, prefilledContact: contact };
}

function openForecastAddForDrawer() {
  const c = drawer.value.contact;
  if (!c) return;
  forecastModal.value = { open: true, mode: 'add', forecastId: null, prefilledContact: { id: c.id, name: c.name } };
}

function openForecastEdit(id) {
  forecastModal.value = { open: true, mode: 'edit', forecastId: id, prefilledContact: null };
}

function closeForecastModal() {
  forecastModal.value.open = false;
}

async function onForecastSaved() {
  closeForecastModal();
  showToast('Forecast saved');
  if (tab.value === 'forecast') loadForecasts();
  if (drawer.value.open && drawer.value.contact) {
    const res = await api.get(`/v1/contacts/${drawer.value.contact.id}`);
    drawer.value.contact = res.data.data;
  }
}

function openForecastDeleteModal(forecast) {
  forecastDeleteModal.value = { show: true, forecast, loading: false };
}

function closeForecastDeleteModal() {
  forecastDeleteModal.value = { show: false, forecast: null, loading: false };
}

async function confirmForecastDelete() {
  if (forecastDeleteModal.value.loading) return;
  forecastDeleteModal.value.loading = true;
  try {
    await api.delete(`/v1/forecasts/${forecastDeleteModal.value.forecast.id}`);
    closeForecastDeleteModal();
    loadForecasts();
    showToast('Forecast deleted');
  } catch {
    forecastDeleteModal.value.loading = false;
  }
}

async function openDrawerById(contactId) {
  if (!contactId) return;
  await openDrawer({ id: contactId });
}

// ── Drawer closed toggle ──
const closedDrawerModal = ref({ open: false, loading: false });

function openDrawerClosedModal() { closedDrawerModal.value = { open: true, loading: false }; }

async function toggleDrawerClosed() {
  if (!drawer.value.contact) return;
  closedDrawerModal.value.loading = true;
  try {
    const res = await api.patch(`/v1/contacts/${drawer.value.contact.id}/closed`);
    drawer.value.contact.is_permanently_closed = res.data.is_permanently_closed;
    // sync the badge in the contacts table without a full reload
    const match = contacts.value.find(c => c.id === drawer.value.contact.id);
    if (match) match.is_permanently_closed = res.data.is_permanently_closed;
    closedDrawerModal.value.open = false;
  } finally {
    closedDrawerModal.value.loading = false;
  }
}

// ── Shared drawer ──
async function openDrawer(c) {
  addTaskOpen.value  = false;
  addTaskError.value = '';
  drawer.value = { open: true, loading: true, contact: null };
  try {
    const res = await api.get(`/v1/contacts/${c.id}`);
    drawer.value.contact = res.data.data;
  } finally {
    drawer.value.loading = false;
  }
}

function closeDrawer() {
  drawer.value.open     = false;
  addTaskOpen.value  = false;
  addTaskError.value = '';
}

function openAddTask() {
  addTaskOpen.value = true;
  addTaskError.value = '';
  addTaskForm.value = { task_id: '', todo_date: new Date().toISOString().slice(0, 10), todo_remark: '' };
}

function openFollowUpModal(todoId = '') {
  followUpModalForm.value = {
    todo_id:       todoId,
    followup_date: new Date().toISOString().slice(0, 10),
    action_type:   '',
    note:          '',
  };
  followUpModal.value = { open: true, saving: false, error: '' };
}

function closeFollowUpModal() { followUpModal.value.open = false; }

async function submitFollowUpModal() {
  if (!followUpModalForm.value.todo_id || !followUpModalForm.value.followup_date || followUpModal.value.saving) return;
  followUpModal.value.saving = true;
  followUpModal.value.error  = '';
  try {
    await api.post('/v1/followups', {
      todo_id:       followUpModalForm.value.todo_id,
      followup_date: followUpModalForm.value.followup_date,
      action_type:   followUpModalForm.value.action_type || null,
      note:          followUpModalForm.value.note        || null,
    });
    closeFollowUpModal();
    const res = await api.get(`/v1/contacts/${drawer.value.contact.id}`);
    drawer.value.contact = res.data.data;
    showToast('Follow-up saved');
  } catch (e) {
    followUpModal.value.error = e.response?.data?.message ?? 'Failed to save follow-up.';
  } finally {
    followUpModal.value.saving = false;
  }
}

// ── Task Follow-Up Modal ──
function openTaskFuModal(todo) {
  taskFuForm.value  = { followup_date: new Date().toISOString().slice(0, 10), action_type: '', note: '' };
  taskFuModal.value = { open: true, todo, saving: false, error: '' };
}

function closeTaskFuModal() { taskFuModal.value.open = false; }

async function submitTaskFuForm() {
  if (!taskFuForm.value.followup_date || taskFuModal.value.saving) return;
  taskFuModal.value.saving = true;
  taskFuModal.value.error  = '';
  try {
    await api.post('/v1/followups', {
      todo_id:       taskFuModal.value.todo.id,
      followup_date: taskFuForm.value.followup_date,
      action_type:   taskFuForm.value.action_type || null,
      note:          taskFuForm.value.note        || null,
    });
    const res = await api.get(`/v1/contacts/${drawer.value.contact.id}`);
    drawer.value.contact = res.data.data;
    const updatedTodo = drawer.value.contact.todos?.find(t => t.id === taskFuModal.value.todo.id);
    if (updatedTodo) taskFuModal.value.todo = updatedTodo;
    taskFuForm.value = { followup_date: new Date().toISOString().slice(0, 10), action_type: '', note: '' };
    showToast('Follow-up saved');
  } catch (e) {
    taskFuModal.value.error = e.response?.data?.message ?? 'Failed to save follow-up.';
  } finally {
    taskFuModal.value.saving = false;
  }
}

// ── Quick Add Task modal ──
function openAddTaskModal(c) {
  addTaskModal.value     = { open: true, contact: c, saving: false, error: '' };
  addTaskModalForm.value = { task_id: '', todo_date: new Date().toISOString().slice(0, 10), todo_remark: '' };
}

function closeAddTaskModal() { addTaskModal.value.open = false; }

async function submitAddTaskModal() {
  if (!addTaskModalForm.value.todo_date || addTaskModal.value.saving) return;
  addTaskModal.value.saving = true;
  addTaskModal.value.error  = '';
  try {
    await api.post(`/v1/contacts/${addTaskModal.value.contact.id}/todos`, {
      task_id:    addTaskModalForm.value.task_id    || null,
      todo_date:  addTaskModalForm.value.todo_date,
      todo_remark: addTaskModalForm.value.todo_remark || null,
    });
    closeAddTaskModal();
    if (tab.value === 'tasks') loadTodos();
    showToast('Task saved');
  } catch (e) {
    addTaskModal.value.error = e.response?.data?.message ?? 'Failed to save task.';
  } finally {
    addTaskModal.value.saving = false;
  }
}

// ── Quick Edit Contact modal ──
async function openEditContactModal(c) {
  editContactModal.value = {
    open: true, contactId: c.id, contactName: c.name,
    loading: true, saving: false, error: '', dupError: '',
  };
  try {
    const res = await api.get(`/v1/contacts/${c.id}`);
    const contact = res.data.data;
    editContactForm.value = {
      name:        contact.name        ?? '',
      status_id:   contact.status_id   ?? '',
      type_id:     contact.type_id     ?? '',
      industry_id: contact.industry_id ?? '',
      category_id: contact.category_id ?? '',
      area_id:     contact.area_id     ?? '',
      address:     contact.address     ?? '',
      lead_source: contact.lead_source ?? '',
      remark:      contact.remark      ?? '',
      user_id:     contact.user_id     ?? '',
    };
  } finally {
    editContactModal.value.loading = false;
  }
}

function closeEditContactModal() { editContactModal.value.open = false; }

function checkEditDuplicate() {
  clearTimeout(editDupTimer);
  editContactModal.value.dupError = '';
  if (!editContactForm.value.name.trim()) return;
  editDupTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts/check-duplicate', {
      params: { name: editContactForm.value.name, exclude_id: editContactModal.value.contactId },
    });
    editContactModal.value.dupError = res.data.exists ? 'This company name already exists!' : '';
  }, 400);
}

async function submitEditContact() {
  if (editContactModal.value.dupError || editContactModal.value.saving) return;
  editContactModal.value.saving = true;
  editContactModal.value.error  = '';
  try {
    await api.put(`/v1/contacts/${editContactModal.value.contactId}`, editContactForm.value);
    closeEditContactModal();
    load();
    showToast('Contact updated');
  } catch (e) {
    const errors = e.response?.data?.errors;
    editContactModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save.');
  } finally {
    editContactModal.value.saving = false;
  }
}

async function submitQuickTask() {
  if (!addTaskForm.value.todo_date || addTaskSaving.value) return;
  addTaskSaving.value = true;
  addTaskError.value  = '';
  try {
    await api.post(`/v1/contacts/${drawer.value.contact.id}/todos`, {
      task_id:    addTaskForm.value.task_id  || null,
      todo_date:  addTaskForm.value.todo_date,
      todo_remark: addTaskForm.value.todo_remark || null,
    });
    addTaskOpen.value = false;
    const res = await api.get(`/v1/contacts/${drawer.value.contact.id}`);
    drawer.value.contact = res.data.data;
    if (tab.value === 'tasks') loadTodos();
    showToast('Task saved');
  } catch (e) {
    addTaskError.value = e.response?.data?.message ?? 'Failed to save task.';
  } finally {
    addTaskSaving.value = false;
  }
}

async function toggleDrawerTodoDone(todo, status) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status });
  todo.completion_status = status;
  showToast(status === 'completed' ? 'Task marked complete' : 'Task marked pending');
  if (tab.value === 'tasks') {
    const t = todos.value.find(x => x.id === todo.id);
    if (t) t.completion_status = status;
  }
  if (status === 'completed') checkAndPromptFollowUps(todo.id);
}

function deleteDrawerTodo(todo) {
  todoDeleteModal.value = { show: true, todo, loading: false };
}

function closeTodoDeleteModal() {
  todoDeleteModal.value = { show: false, todo: null, loading: false };
}

async function confirmTodoDelete() {
  if (todoDeleteModal.value.loading) return;
  todoDeleteModal.value.loading = true;
  const todo = todoDeleteModal.value.todo;
  try {
    await api.delete(`/v1/contacts/${drawer.value.contact.id}/todos/${todo.id}`);
    closeTodoDeleteModal();
    const res = await api.get(`/v1/contacts/${drawer.value.contact.id}`);
    drawer.value.contact = res.data.data;
    if (tab.value === 'tasks') todos.value = todos.value.filter(t => t.id !== todo.id);
    showToast('Task deleted');
  } catch {
    todoDeleteModal.value.loading = false;
  }
}

// ── Delete ──
async function openDeleteModal(contact) {
  deleteModal.value = { show: true, contact, input: '', loading: false };
  await nextTick();
  deleteInput.value?.focus();
}

function closeDeleteModal() {
  deleteModal.value = { show: false, contact: null, input: '', loading: false };
}

async function confirmDelete() {
  if (deleteModal.value.input !== 'confirm delete' || deleteModal.value.loading) return;
  deleteModal.value.loading = true;
  try {
    await api.delete(`/v1/contacts/${deleteModal.value.contact.id}`);
    closeDeleteModal();
    load();
    showToast('Contact deleted');
  } catch {
    deleteModal.value.loading = false;
  }
}

// ── Add Contact ──
function openAddModal() {
  addStep.value = 1;
  addSaving.value = false;
  addSubmitError.value = '';
  addDupError.value = '';
  addForm.value = { name: '', status_id: '', industry_id: '', type_id: '', category_id: '', area_id: '', address: '', remark: '', created_at: new Date().toISOString().slice(0, 10), lead_source: 'manual', user_id: '' };
  addPic.value  = { name: '', phone: '', email: '', office: '' };
  addModal.value.open = true;
}

function closeAddModal() { addModal.value.open = false; }

function checkAddDuplicate() {
  clearTimeout(addDupTimer);
  addDupError.value = '';
  if (!addForm.value.name.trim()) return;
  addDupTimer = setTimeout(async () => {
    const res = await api.get('/v1/contacts/check-duplicate', { params: { name: addForm.value.name } });
    addDupError.value = res.data.exists ? 'A contact with this company name already exists!' : '';
  }, 400);
}

function goAddStep2() { if (addDupError.value) return; addStep.value = 2; }

async function submitAdd() {
  addSaving.value = true;
  addSubmitError.value = '';
  try {
    await api.post('/v1/contacts', {
      ...addForm.value,
      pic_name: addPic.value.name, pic_phone: addPic.value.phone,
      pic_email: addPic.value.email, pic_office: addPic.value.office,
    });
    closeAddModal();
    load();
    showToast('Contact registered successfully');
  } catch (e) {
    const errors = e.response?.data?.errors;
    addSubmitError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    addSaving.value = false;
  }
}

onMounted(async () => {
  const initialTab = route.query.tab;
  if (['summary', 'tasks', 'forecast'].includes(initialTab)) {
    tab.value = initialTab;
  }
  const initialLoad =
    tab.value === 'summary'  ? loadSummary() :
    tab.value === 'tasks'    ? loadTodos() :
    tab.value === 'forecast' ? loadForecasts() :
    load();
  const [y, m] = todoDate.value.split('-').map(Number);
  try {
    const [lu] = await Promise.all([api.get('/v1/lookups'), initialLoad, loadTodoMarkedDates(y, m)]);
    users.value = lu.data.users ?? [];
    lookups.value = {
      statuses:   lu.data.statuses   ?? [],
      types:      lu.data.types      ?? [],
      categories: lu.data.categories ?? [],
      industries: lu.data.industries ?? [],
      tasks:      lu.data.tasks      ?? [],
      areas:      lu.data.areas      ?? [],
    };
  } catch (_) {
    // individual load functions handle their own loading states; lookup failure leaves filters empty
  }
});
</script>

<style scoped>
.page { padding: 28px 28px 48px; max-width: 1500px; margin: 0 auto; }

/* Page head — clean, no gradient banner */
.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
  color: var(--text-1);
  margin: 0;
}
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

.btn-primary-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--primary);
  color: var(--primary-on);
  border: none;
  border-radius: 999px;
  padding: 11px 20px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 8px 22px -8px rgba(29,78,216,0.6);
  transition: background 0.15s, transform 0.06s, box-shadow 0.15s;
}
.btn-primary-pill:hover { background: var(--primary-hover); }
.btn-primary-pill:active { transform: translateY(1px); }
.plus-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(255,255,255,0.18);
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
}

/* Tab bar — pill style */
.view-tabs {
  display: inline-flex;
  gap: 4px;
  background: var(--surface);
  border-radius: 999px;
  padding: 5px;
  border: 1px solid var(--border-soft);
  margin-bottom: 18px;
  box-shadow: var(--shadow-xs);
  flex-wrap: wrap;
}
.tab-btn {
  padding: 8px 18px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-2);
  border-radius: 999px;
  transition: color 0.15s, background 0.15s;
  white-space: nowrap;
}
.tab-btn:hover { color: var(--text-1); background: var(--surface-2); }
.tab-icon { display: inline-flex; align-items: center; vertical-align: middle; }
.tab-active {
  color: var(--primary-on) !important;
  background: var(--primary) !important;
  box-shadow: 0 4px 12px -4px rgba(29,78,216,0.5);
}

/* Toolbar */
.toolbar {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  margin-bottom: 18px;
  box-shadow: var(--shadow-xs);
  border: 1px solid var(--border-soft);
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 220px; }
.filter-group label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: var(--text-3);
  padding-left: 2px;
}
.filter-group select, .filter-group input {
  height: 38px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 13px;
  outline: none;
  background: var(--surface);
  color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 30px; }
.filter-group select:focus,
.filter-group input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}

/* Date navigator */
.date-nav { display: flex; align-items: center; gap: 4px; align-self: flex-end; }
.date-nav-arrow {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 38px; border-radius: 999px;
  border: 1px solid var(--border); background: var(--surface);
  cursor: pointer; color: var(--text-2); transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.date-nav-arrow:hover { background: var(--primary-soft); color: var(--primary); border-color: var(--primary-soft); }
.date-nav-center {
  display: flex; align-items: center; gap: 7px; cursor: pointer; position: relative;
  padding: 0 16px; height: 38px; border-radius: 999px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; font-weight: 600; color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s; white-space: nowrap;
}
.date-nav-center:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.date-nav-cal { display: flex; color: var(--text-3); }
.date-nav-hidden-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; right: 0; bottom: 0; }

.btn {
  height: 38px;
  padding: 0 18px;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.06s;
}
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn-primary {
  background: var(--primary);
  color: var(--primary-on);
  box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55);
}
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
.btn-clear {
  background: var(--surface);
  color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-clear:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-soft); }
.btn-export { background: #10b981; color: white; border: none; }
.btn-export:hover { background: #059669; }
.btn-sm { height: 32px; padding: 0 14px; font-size: 12px; }

/* Table wrap */
.table-wrap {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.table-header-bar {
  background: var(--surface);
  padding: 16px 22px;
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge {
  background: var(--primary-soft);
  color: var(--primary-text);
  font-size: 11.5px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
}
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }

/* Column widths */
.col-no       { width: 44px; text-align: center; }
.col-date     { width: 100px; white-space: nowrap; }
.col-user     { width: 150px; }
.col-status   { width: 110px; }
.col-type     { width: 90px; }
.col-industry { width: 110px; }
.col-address  { width: 140px; }
.col-name     { min-width: 160px; }
.col-category { width: 110px; }
.col-remark   { width: 200px; }
.col-action   { width: 160px; white-space: nowrap; }

thead th {
  background: var(--surface-2);
  color: var(--text-2);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.55px;
  padding: 11px 14px;
  border-bottom: 2px solid var(--border);
  border-right: 1px solid var(--border-soft);
  text-align: left;
  white-space: nowrap;
}
thead th:last-child { border-right: none; }
thead th.col-no { text-align: center; }
tbody td {
  padding: 13px 14px;
  border-bottom: 1px solid var(--border-soft);
  border-right: 1px solid var(--border-soft);
  color: var(--text-1);
  vertical-align: middle;
  font-size: 13.5px;
}
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
.contact-row { transition: background 0.12s; }
.contact-row:hover { background: var(--surface-2); }
.contact-row:hover .company-link { color: var(--primary); }

/* Row number */
.row-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: var(--surface-2);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-3);
}
.date-text { font-size: 12.5px; color: var(--text-2); font-weight: 500; }

/* User cell */
.user-name { font-weight: 600; font-size: 13px; color: var(--text-1); }

/* Status badge */
.badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-status.status-active   { background: var(--success-soft); color: var(--success); }
.badge-status.status-inactive { background: var(--danger-soft);  color: var(--danger); }
.badge-status.status-lead     { background: var(--warning-soft); color: var(--warning); }
.badge-status.status-client   { background: var(--info-soft);    color: var(--info); }
.badge-status.status-default  { background: var(--surface-2);    color: var(--text-2); }

/* Tags */
.tag {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  background: var(--surface-2);
  color: var(--text-2);
}
.tag-category { background: var(--primary-soft); color: var(--primary-text); }
.company-link { color: var(--text-1); font-weight: 600; transition: color 0.15s; }
.closed-badge {
  display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px;
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
  background: var(--danger-soft); color: var(--danger); vertical-align: middle;
}
.col-name { white-space: nowrap; }
.dinfo-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px; }
.maps-link {
  display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 700;
  color: var(--primary-text); text-decoration: none; padding: 2px 8px; border-radius: 999px;
  background: var(--primary-soft); transition: background 0.15s, color 0.15s; white-space: nowrap;
}
.maps-link:hover { background: var(--primary); color: var(--primary-on); }

/* Action buttons */
.action-btns { display: flex; gap: 5px; align-items: center; }

.action-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px 4px 7px;
  border-radius: 7px;
  border: none;
  cursor: pointer;
  font-size: 11.5px;
  font-weight: 600;
  line-height: 1;
  transition: opacity 0.15s, transform 0.08s, box-shadow 0.15s;
}
.action-chip:hover { opacity: 0.82; transform: translateY(-1px); box-shadow: 0 3px 8px -2px rgba(0,0,0,0.18); }
.chip-icon { display: inline-flex; align-items: center; width: 14px; height: 14px; flex-shrink: 0; }
.chip-icon svg { width: 14px; height: 14px; }
.chip-label { letter-spacing: 0.2px; }

.chip-task   { background: var(--info-soft);    color: var(--info); }
.chip-edit   { background: var(--primary-soft); color: var(--primary-text); }
.chip-delete { background: var(--danger-soft);  color: var(--danger); }

/* Inline remark text */
.remark-inline-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  text-align: left;
  width: 100%;
}
.remark-text-preview {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 12.5px;
  color: var(--text-2);
  line-height: 1.45;
  max-width: 200px;
  transition: color 0.15s;
}
.remark-inline-btn:hover .remark-text-preview { color: var(--primary-text); }

.muted-dash { color: var(--text-3); font-size: 13px; }

/* Summary-specific */
.summary-legend { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-3); }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.legend-label { margin-right: 8px; }
.col-check { width: 32px; text-align: center; }

/* Summary table — scoped column widths so activity always gets room */
.summary-table { table-layout: fixed; width: 100%; }
.summary-table .col-check      { width: 36px; text-align: center; vertical-align: middle; }
.summary-table .sum-no-col     { width: 44px; text-align: center; vertical-align: middle; }
.summary-table .sum-user-col   { width: 110px; vertical-align: middle; }
.summary-table .sum-status-col { width: 110px; vertical-align: middle; }
.summary-table .sum-name-col   { width: 230px; vertical-align: middle; word-break: break-word; }
.sum-company-link {
  display: block; font-weight: 600; color: var(--text-1);
  white-space: normal; word-break: break-word; line-height: 1.4;
  transition: color 0.15s;
}
.contact-row:hover .sum-company-link { color: var(--primary); }
.sum-activity-col { border-left: 2px solid var(--border-soft); }
.sum-activity-cell {
  padding: 12px 16px;
  vertical-align: top;
  border-left: 2px solid var(--border-soft);
  background: var(--surface);
}
.contact-row:hover .sum-activity-cell { background: var(--surface-2); }

.sum-act-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}
.active-badge {
  display: inline-block;
  padding: 3px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  background: var(--success-soft);
  color: var(--success);
}
.sum-lc-inline {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
}

.sum-act-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 4px;
}
.sum-month-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 7px 4px;
  border-radius: 8px;
  text-align: center;
  gap: 3px;
  min-height: 58px;
  min-width: 0;
  cursor: default;
  transition: transform 0.12s, box-shadow 0.12s;
}
.sum-month-cell:hover { transform: scale(1.07); box-shadow: 0 3px 10px -2px rgba(0,0,0,0.15); z-index: 1; position: relative; }
.smc-empty    { background: var(--surface-2); border: 1px solid var(--border-soft); }
.smc-active   { background: var(--success-soft); border: 1.5px solid var(--success); }
.smc-cancelled { background: #fef3c7; border: 1.5px solid #fbbf24; }
.smc-name {
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  line-height: 1;
}
.smc-empty .smc-name { color: var(--text-3); }
.smc-active .smc-name { color: var(--success); }
.smc-cancelled .smc-name { color: #d97706; }
.smc-date {
  font-size: 10.5px;
  font-weight: 800;
  line-height: 1.2;
}
.smc-active .smc-date { color: var(--success); }
.smc-cancelled .smc-date { color: #d97706; }
.smc-task {
  font-size: 9px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.smc-active .smc-task { color: #065f46; }
.smc-cancelled .smc-task { color: #92400e; }

/* Tasks tab */
.task-company-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-1);
  padding: 0;
  transition: color 0.15s;
}
.task-company-btn:hover { color: var(--primary); text-decoration: underline; }
.row-done td { opacity: 0.55; text-decoration: line-through; text-decoration-color: var(--text-3); }
.row-done .todo-done-btn, .row-done .todo-undo-btn { text-decoration: none; opacity: 1; }
.todo-done-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: var(--success-soft);
  color: var(--success);
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.todo-done-btn:hover { background: var(--success); color: #fff; }
.todo-undo-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: var(--surface-2);
  color: var(--text-2);
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.todo-undo-btn:hover { background: var(--text-2); color: var(--surface); }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-top: 1px solid var(--border-soft);
  background: #f8f9ff;
}
.pagination-info { font-size: 12px; color: var(--text-3); flex-shrink: 0; }
.pagination-btns { display: flex; align-items: center; gap: 3px; }
.page-nav {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: none; background: transparent;
  border-radius: 50%;
  color: var(--text-2);
  cursor: pointer;
  transition: background 0.12s;
}
.page-nav svg { width: 14px; height: 14px; }
.page-nav:hover:not(:disabled) { background: #e5eeff; }
.page-nav:disabled { opacity: 0.3; cursor: default; }
.page-num {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: none; background: transparent;
  border-radius: 50%;
  font-size: 12.5px; font-weight: 600;
  color: var(--text-2);
  cursor: pointer;
  transition: background 0.12s;
}
.page-num:hover { background: #e5eeff; }
.page-num--on { background: var(--primary, #1d4ed8); color: #fff; font-weight: 700; }
.page-ellipsis { width: 32px; text-align: center; color: var(--text-3); font-size: 13px; line-height: 32px; }
/* Empty state */
.empty-state { text-align: center; padding: 64px 24px; }
.empty-icon  { display: flex; align-items: center; justify-content: center; margin-bottom: 12px; opacity: 0.7; }
.empty-title { font-size: 15.5px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
.empty-sub   { font-size: 13px; color: var(--text-3); }

/* Export modal */
.export-modal {
  background: var(--surface);
  border-radius: var(--radius-xl, 14px);
  box-shadow: 0 24px 60px rgba(0,0,0,0.18);
  width: min(520px, calc(100vw - 48px));
  max-height: calc(100vh - 64px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.export-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.export-modal-title { font-size: 17px; font-weight: 800; color: var(--text-1); }
.export-modal-sub   { font-size: 12.5px; color: var(--text-3); margin: 3px 0 0; }

.export-modal-body  { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.export-modal-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 24px 20px;
  border-top: 1px solid var(--border-soft);
  background: var(--surface-2, #f8f9ff);
  flex-shrink: 0;
}
.export-footer-count { font-size: 13px; color: var(--text-3); margin: 0; }
.export-footer-count strong { color: var(--primary); }

.export-action-stack { display: flex; flex-direction: column; gap: 10px; }

.export-dl-btn {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 13px 16px;
  border-radius: var(--radius, 10px);
  border: none;
  cursor: pointer;
  text-align: left;
  transition: opacity 0.15s, transform 0.08s;
}
.export-dl-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.export-dl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-dl-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.export-dl-text { display: flex; flex-direction: column; gap: 2px; }
.export-dl-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.export-dl-desc  { font-size: 12px; opacity: 0.82; line-height: 1.3; }

.export-dl-xls { background: #10b981; color: #fff; }
.export-dl-csv { background: var(--surface); border: 1.5px solid var(--border); color: var(--text-1); }

.export-cancel-btn {
  width: 100%;
  padding: 10px 16px;
  border: none;
  border-radius: var(--radius-sm, 6px);
  background: none;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--text-3);
  transition: background 0.12s, color 0.12s;
}
.export-cancel-btn:hover { background: var(--border-soft); color: var(--text-2); }

/* Sections */
.export-section { display: flex; flex-direction: column; gap: 10px; }
.export-section-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-3);
  margin-bottom: 2px;
}

/* Scope cards */
.export-scope-options { display: flex; flex-direction: column; gap: 8px; }
.export-scope-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border: 1.5px solid var(--border-soft);
  border-radius: var(--radius, 10px);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  background: var(--surface);
}
.export-scope-card:hover { border-color: var(--primary); background: var(--primary-soft, #dbeafe); }
.export-scope-card--on  { border-color: var(--primary); background: var(--primary-soft, #dbeafe); }
.export-scope-card-body { flex: 1; min-width: 0; }
.export-scope-card-title { font-size: 13px; font-weight: 700; color: var(--text-1); }
.export-scope-card-desc  { font-size: 12px; color: var(--text-3); margin-top: 2px; line-height: 1.4; }
.export-scope-badge {
  flex-shrink: 0;
  min-width: 36px;
  text-align: center;
  padding: 3px 8px;
  background: var(--primary);
  color: #fff;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}
.export-custom-input {
  display: inline-block;
  width: 64px;
  padding: 2px 6px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 12px;
  text-align: center;
  background: var(--surface);
  color: var(--text-1);
  outline: none;
  vertical-align: middle;
}

/* Filter chips */
.export-filters-row { display: flex; flex-wrap: wrap; gap: 6px; }
.export-filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: var(--primary-soft, #dbeafe);
  color: var(--primary);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.export-filter-chip--dim { background: var(--surface-2, #f1f5fb); color: var(--text-3); }
.export-filter-chip--sort { background: var(--surface-2, #f1f5fb); color: var(--text-2); }

/* Columns */
.export-cols-head     { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.export-cols-actions  { display: flex; align-items: center; gap: 4px; }
.export-link-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  color: var(--primary);
  padding: 2px 4px;
  border-radius: 4px;
}
.export-link-btn:hover { text-decoration: underline; }
.export-dot-sep { color: var(--text-3); font-size: 12px; }

.export-cols-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px 12px;
}
.export-col-check {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
  color: var(--text-2);
  font-weight: 500;
  padding: 6px 10px;
  border-radius: var(--radius-sm, 6px);
  border: 1px solid var(--border-soft);
  transition: background 0.12s, border-color 0.12s;
}
.export-col-check:hover { background: var(--primary-soft, #dbeafe); border-color: var(--primary); }
.export-col-check input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer; }

/* Modals */
.remark-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(4px);
  z-index: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.remark-modal {
  background: var(--surface);
  border-radius: var(--radius-xl);
  padding: 0;
  min-width: 320px;
  max-width: 480px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.remark-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 22px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
}
.remark-close {
  background: var(--surface-2);
  border: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-2);
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  transition: background 0.15s, color 0.15s;
}
.remark-close:hover { background: var(--danger-soft); color: var(--danger); }
.remark-modal-body { padding: 20px 22px; font-size: 13.5px; color: var(--text-1); white-space: pre-line; line-height: 1.6; }

.delete-modal { min-width: 380px; max-width: 480px; }
.delete-modal-header {
  background: var(--danger-soft);
  border-bottom-color: var(--danger-soft);
  color: var(--danger);
}
.delete-modal-body { padding: 22px 22px; }
.delete-warning-icon { display: flex; align-items: center; justify-content: center; margin-bottom: 10px; color: var(--warning); }
.delete-info-icon { color: var(--primary); }
.delete-warning-text { font-size: 13.5px; color: var(--text-1); line-height: 1.6; margin-bottom: 14px; text-align: center; }
.delete-confirm-label { font-size: 12.5px; color: var(--text-2); margin-bottom: 6px; }
.delete-confirm-input {
  width: 100%;
  height: 40px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 13px;
  outline: none;
  background: var(--surface);
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.delete-confirm-input:focus { border-color: var(--danger); box-shadow: 0 0 0 3px var(--danger-soft); }
.delete-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
.btn-danger {
  background: var(--danger);
  color: #fff;
  transition: background 0.15s;
  box-shadow: 0 6px 18px -6px rgba(239,68,68,0.55);
}
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-danger:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }

/* Drawer */
.drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(2px);
  z-index: 600;
  display: flex;
  justify-content: flex-end;
}
.drawer-panel {
  width: 580px;
  max-width: 95vw;
  height: 100%;
  background: var(--surface);
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  border-left: 1px solid var(--border-soft);
}
.drawer-header { padding: 22px 24px 16px; border-bottom: 1px solid var(--border-soft); background: var(--surface); flex-shrink: 0; }
.drawer-title-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; gap: 12px; }
.drawer-company { font-size: 20px; font-weight: 800; color: var(--text-1); margin-bottom: 8px; letter-spacing: -0.3px; }
.drawer-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.meta-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary-text);
}
.drawer-close {
  background: var(--surface-2);
  border: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-2);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  flex-shrink: 0;
  transition: background 0.15s, color 0.15s;
}
.drawer-close:hover { background: var(--danger-soft); color: var(--danger); }
.drawer-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.daction-btn {
  height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.btn-view-full { background: var(--primary-soft); color: var(--primary-text); }
.btn-view-full:hover { background: var(--primary); color: var(--primary-on); }
.btn-edit-c { background: var(--warning-soft); color: var(--warning); }
.btn-edit-c:hover { background: var(--warning); color: #fff; }
.btn-forecast-c { background: var(--info-soft); color: var(--info); }
.btn-forecast-c:hover { background: var(--info); color: #fff; }
.btn-followup-c { background: #fce7f3; color: #9d174d; }
.btn-followup-c:hover { background: #e11d48; color: #fff; }
.btn-close-c { background: var(--danger-soft); color: var(--danger); }
.btn-close-c:hover { background: var(--danger); color: #fff; }
.btn-reopen-c { background: var(--success-soft); color: var(--success); }
.btn-reopen-c:hover { background: var(--success); color: #fff; }
.drawer-closed-banner {
  display: flex; align-items: center; gap: 7px; margin: 10px 0 4px;
  background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm);
  padding: 7px 12px; font-size: 12px; font-weight: 700;
}
.btn-followup-save { background: #e11d48; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
.btn-followup-save:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-followup-submit { flex: 1; background: #e11d48; color: #fff; justify-content: center; height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; display: inline-flex; align-items: center; }
.btn-followup-submit:disabled { background: #94a3b8; cursor: not-allowed; }
.todo-actions-cell { text-align: right; white-space: nowrap; display: flex; gap: 4px; justify-content: flex-end; align-items: center; }
.todo-del-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 6px; border: none;
  background: var(--danger-soft); color: var(--danger); font-size: 11px;
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.todo-del-btn:hover { background: var(--danger); color: #fff; }
.fu-count-badge {
  font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 10px;
  border: 1.5px solid #fce7f3; background: #fce7f3; color: #9d174d;
  cursor: pointer; white-space: nowrap; transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.fu-count-badge.fu-has-entries { border-color: #fb7185; background: #ffe4e6; color: #be123c; }
.fu-count-badge:hover { background: #e11d48; border-color: #e11d48; color: #fff; }
.fu-badge {
  background: #fce7f3; color: #9d174d; font-size: 11px; font-weight: 600;
  padding: 2px 8px; border-radius: 10px; white-space: nowrap;
}
.task-fu-modal { width: 620px; }
.task-fu-body { padding: 18px 24px; }
.task-fu-remark {
  background: var(--surface-2); border-radius: var(--radius); padding: 10px 14px;
  font-size: 12.5px; color: var(--text-1); margin-bottom: 16px; white-space: pre-line;
}
.task-fu-section-label {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
  color: var(--text-3); margin-bottom: 10px;
}
.task-fu-empty { font-size: 13px; color: var(--text-3); font-style: italic; margin-bottom: 16px; }
.task-fu-divider { border-top: 1px solid var(--border-soft); margin: 16px 0; }
.drawer-body { flex: 1; overflow-y: auto; padding: 18px 24px; }
.drawer-loading { padding: 40px; text-align: center; color: var(--text-3); font-size: 14px; }
.drawer-section { margin-bottom: 22px; }
.dsec-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-3);
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-soft);
}
.dsec-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-soft);
}
.dsec-title-row .dsec-title { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.dinfo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
.dinfo-field { display: flex; flex-direction: column; gap: 3px; }
.dinfo-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.dinfo-value { font-size: 13.5px; color: var(--text-1); font-weight: 500; }
.dinfo-full { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-soft); }
.drawer-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.drawer-table thead th {
  background: var(--surface-2);
  color: var(--text-3);
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  padding: 9px 12px;
  border-bottom: 1px solid var(--border-soft);
  text-align: left;
}
.drawer-table thead th:first-child { border-top-left-radius: 10px; }
.drawer-table thead th:last-child { border-top-right-radius: 10px; }
.drawer-table tbody td { padding: 11px 12px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
.drawer-table tbody tr:last-child td { border-bottom: none; }
.drawer-table tbody tr:hover { background: var(--surface-2); }
.dtd-date { font-size: 11.5px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.dtask-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary-text);
}
.dfcast-amount { font-weight: 700; color: var(--info); white-space: nowrap; font-size: 12.5px; }
.result-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  white-space: nowrap;
}
.result-confirmed { background: var(--success-soft); color: var(--success); }
.result-pending   { background: var(--warning-soft); color: var(--warning); }
.result-rejected  { background: var(--danger-soft);  color: var(--danger); }
.result-no-result { background: var(--surface-2);    color: var(--text-2); }
.drawer-empty { font-size: 13px; color: var(--text-3); font-style: italic; margin: 0; }
.drawer-email-link { color: var(--primary); text-decoration: none; font-size: 12.5px; }
.drawer-email-link:hover { text-decoration: underline; color: var(--primary-hover); }

/* Drawer: add task toggle button */
.add-task-toggle-btn {
  height: 30px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: var(--success-soft);
  color: var(--success);
  font-size: 11.5px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.add-task-toggle-btn:hover { background: var(--success); color: #fff; }

/* Inline add task form */
.add-task-form {
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  padding: 16px 18px;
  margin-bottom: 16px;
}
.add-task-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.add-task-field { display: flex; flex-direction: column; gap: 5px; }
.add-task-field label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); }
.add-task-field select, .add-task-field input, .add-task-field textarea {
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 13px;
  padding: 0 14px;
  height: 36px;
  background: var(--surface);
  color: var(--text-1);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.add-task-field textarea { height: 60px; padding: 8px 14px; resize: none; border-radius: var(--radius); }
.add-task-field select:focus, .add-task-field input:focus, .add-task-field textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.add-task-error {
  font-size: 12.5px;
  color: var(--danger);
  background: var(--danger-soft);
  border-radius: 10px;
  padding: 8px 12px;
  margin-bottom: 10px;
}
.add-task-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }
.req { color: var(--danger); }

/* Drawer task completion */
.todo-done-row td { opacity: 0.55; text-decoration: line-through; text-decoration-color: var(--text-3); }
.todo-done-row .todo-status-cell { text-decoration: none; opacity: 1; }
.todo-status-cell { text-align: center; width: 40px; }

/* Monthly activity */
.drawer-year-sel {
  height: 30px;
  padding: 0 12px;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  background: var(--surface);
  color: var(--text-1);
  outline: none;
  cursor: pointer;
}
.drawer-year-sel:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.month-activity-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; }
.month-cell {
  padding: 8px 6px;
  border-radius: 10px;
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  text-align: center;
  min-height: 56px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}
.month-cell.month-active { background: var(--success-soft); border-color: var(--success-soft); }
.month-cell-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.month-cell.month-active .month-cell-label { color: var(--success); }
.month-cell-date { font-size: 10.5px; font-weight: 700; color: var(--success); }
.month-cell-task { font-size: 9px; color: var(--success); opacity: 0.85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.month-cell-empty { font-size: 13px; color: var(--text-3); line-height: 1; opacity: 0.5; }

/* Add Contact modal */
.add-contact-modal {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 580px;
  max-width: 95vw;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.add-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.add-modal-title-block { display: flex; align-items: center; gap: 12px; }
.add-modal-title { color: var(--text-1); font-size: 17px; font-weight: 800; letter-spacing: -0.2px; }
.add-modal-step-pill {
  background: var(--primary-soft);
  color: var(--primary-text);
  font-size: 11px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
}
.add-modal-body { padding: 22px 24px; overflow-y: auto; }
.add-section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-3);
  margin-bottom: 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  gap: 10px;
}
.company-chip-inline {
  background: var(--primary-soft);
  color: var(--primary-text);
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 700;
}
.add-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.add-form-group { margin-bottom: 14px; }
.add-form-group label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-2);
  margin-bottom: 6px;
}
.add-form-group input,
.add-form-group select,
.add-form-group textarea {
  width: 100%;
  height: 42px;
  padding: 0 14px;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 13.5px;
  color: var(--text-1);
  outline: none;
  background: var(--surface);
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.add-form-group textarea { height: 72px; padding: 10px 14px; resize: none; border-radius: var(--radius); }
.add-form-group input:focus,
.add-form-group select:focus,
.add-form-group textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.add-hint { font-size: 11.5px; margin-top: 4px; }
.error-hint { color: var(--danger); font-weight: 600; }
.add-error-box {
  background: var(--danger-soft);
  color: var(--danger);
  border-radius: var(--radius);
  padding: 12px 16px;
  font-size: 13px;
  margin-bottom: 14px;
}
.add-modal-actions { display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end; }
.add-modal-actions .btn { height: 42px; padding: 0 22px; }
.add-modal-actions .btn-primary { min-width: 160px; justify-content: center; }

/* Forecast tab */
.btn-add-forecast { background: #0ea5e9; }
.btn-add-forecast:hover { background: #0284c7; }
.forecast-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.fstat-chip {
  display: inline-flex; flex-direction: column; align-items: center; padding: 4px 12px;
  background: #f1f5f9; border-radius: 8px; min-width: 90px;
}
.fstat-chip strong { font-size: 12px; font-weight: 800; color: var(--text-1); line-height: 1.2; }
.fstat-chip small { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); margin-top: 2px; font-weight: 700; }
.fstat-confirmed { background: #dcfce7; }
.fstat-confirmed strong { color: #15803d; }
.fstat-pending { background: #fef3c7; }
.fstat-pending strong { color: #b45309; }
.fcol-amount { width: 110px; text-align: right; white-space: nowrap; }
.fcast-amount { font-weight: 800; color: #0369a1; font-size: 12px; }

/* Drawer slide-in transition */
.drawer-enter-active { transition: opacity 0.2s ease; }
.drawer-leave-active { transition: opacity 0.2s ease 0.05s; }
.drawer-enter-active .drawer-panel { transition: transform 0.25s ease; }
.drawer-leave-active .drawer-panel { transition: transform 0.22s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer-panel, .drawer-leave-to .drawer-panel { transform: translateX(100%); }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 18px 14px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 14px; }
  .page-title { font-size: 24px; }
  .table-scroll { overflow-x: auto; }
  table { min-width: 700px; }
  .add-form-row { grid-template-columns: 1fr; }
  .add-task-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 14px 10px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
  .view-tabs { overflow-x: auto; max-width: 100%; }
  .btn-primary-pill { width: 100%; justify-content: center; }
}

/* ── Date range filter ── */
.filter-date-range {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.date-range-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  color: var(--text-3);
  padding-left: 2px;
}
.date-range-inputs {
  display: flex;
  align-items: center;
  gap: 6px;
}
.date-input-wrap {
  display: flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 999px;
  overflow: hidden;
  height: 38px;
  background: var(--surface);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.date-input-wrap:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.date-input-prefix {
  padding: 0 10px 0 14px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-3);
  white-space: nowrap;
  pointer-events: none;
  border-right: 1px solid var(--border-soft);
}
.date-range-input {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  height: 36px;
  padding: 0 12px;
  font-size: 13px;
  background: transparent;
  color: var(--text-1);
  min-width: 130px;
}
.date-range-sep {
  font-size: 12px;
  color: var(--text-3);
  flex-shrink: 0;
}

/* ── Search autocomplete ── */
.search-group { position: relative; }
.search-wrap  { position: relative; }
.search-wrap input { width: 100%; }
.suggestions-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  right: 0;
  background: var(--surface);
  border: 1px solid var(--border-soft);
  border-radius: 14px;
  box-shadow: 0 8px 28px -6px rgba(0,0,0,0.14), 0 2px 8px -2px rgba(0,0,0,0.06);
  z-index: 200;
  overflow: hidden;
  max-height: 260px;
  overflow-y: auto;
}
.suggestion-item {
  padding: 10px 16px;
  font-size: 13px;
  color: var(--text-1);
  cursor: pointer;
  transition: background 0.1s;
  border-bottom: 1px solid var(--border-soft);
}
.suggestion-item:last-child { border-bottom: none; }
.suggestion-item:hover { background: var(--surface-2); color: var(--primary); }

/* ── Toast notifications ── */
.toast-container {
  position: fixed;
  bottom: 28px;
  right: 28px;
  z-index: 9999;
  pointer-events: none;
}
.toast-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.toast-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 260px;
  max-width: 360px;
  padding: 12px 16px;
  border-radius: 12px;
  background: #1e293b;
  color: #f8fafc;
  font-size: 13.5px;
  font-weight: 500;
  box-shadow: 0 8px 32px -6px rgba(0,0,0,0.38);
  pointer-events: all;
}
.toast-success .toast-check { color: #4ade80; font-weight: 800; font-size: 16px; flex-shrink: 0; }
.toast-error   .toast-check { color: #f87171; font-weight: 800; font-size: 16px; flex-shrink: 0; }
.toast-text { flex: 1; line-height: 1.4; }
.toast-dismiss {
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 11px;
  padding: 2px 5px;
  border-radius: 4px;
  line-height: 1;
  flex-shrink: 0;
  transition: color 0.15s, background 0.15s;
}
.toast-dismiss:hover { color: #f8fafc; background: rgba(255,255,255,0.12); }

.toast-enter-active { transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease; }
.toast-leave-active { transition: transform 0.2s ease, opacity 0.18s ease; position: absolute; width: 100%; }
.toast-enter-from   { transform: translateY(24px); opacity: 0; }
.toast-leave-to     { transform: translateY(12px); opacity: 0; }
.toast-move         { transition: transform 0.25s ease; }

/* Confirm modal (shared with Mark as Closed) */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; color: var(--text-3); padding: 0; line-height: 1; }
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
</style>
