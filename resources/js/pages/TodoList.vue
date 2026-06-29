<template>
  <div class="page" :class="{ 'page-embedded': embedded }">
    <div class="page-head" :class="{ 'page-head-embedded': embedded }">
      <div v-if="!embedded" class="page-head-left">
        <h1 class="page-title">To Do List</h1>
        <p class="page-subtitle">List of to-dos for each contact</p>
      </div>
      <div class="page-head-actions">
        <button v-if="can('create todos')" class="btn-primary-pill" data-tour="add-todo-btn" @click="openAddModal">
          <span class="plus-icon" aria-hidden="true">+</span> Add To-Do
        </button>
      </div>
    </div>

    <div v-if="contactId" class="contact-filter-bar">
      <span class="cfb-text">Showing to-dos for <strong>{{ contactName || 'selected contact' }}</strong></span>
      <button class="cfb-clear" @click="clearContactFilter">Clear filter</button>
    </div>

    <div v-if="selectedIds.length > 0" class="selection-bar">
      <button class="btn-export-sel" @click="exportSelected">Export {{ selectedIds.length }} selected</button>
      <button v-if="can('edit todos')" class="btn-done-sel" @click="markSelectedDone">Set as Done</button>
      <button v-if="can('edit todos')" class="btn-pending-sel" @click="markSelectedPending">Revert to Pending</button>
      <span>{{ selectedIds.length }} record(s) selected</span>
    </div>

    <div class="toolbar">
      <div class="filter-group">
        <label>View</label>
        <select v-model="viewMode" @change="onViewModeChange" class="view-sel">
          <option value="DateRange">Date Range</option>
          <option value="MonthRange">Month Range</option>
        </select>
      </div>

      <template v-if="viewMode === 'DateRange'">
        <div class="filter-date-range">
          <span class="date-range-label">Date Range</span>
          <div class="date-range-inputs">
            <div class="date-input-wrap">
              <span class="date-input-prefix">From</span>
              <input type="date" v-model="rangeFrom" :max="rangeTo || undefined" @change="onRangeChange" class="date-range-input">
            </div>
            <span class="date-range-sep"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
            <div class="date-input-wrap">
              <span class="date-input-prefix">To</span>
              <input type="date" v-model="rangeTo" :min="rangeFrom || undefined" @change="onRangeChange" class="date-range-input">
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <div class="filter-group">
          <label>From Month</label>
          <input type="month" v-model="fromMonth" @change="onRangeChange" class="month-input">
        </div>
        <div class="filter-group">
          <label>To Month</label>
          <input type="month" v-model="toMonth" @change="onRangeChange" class="month-input">
        </div>
      </template>
      <div class="filter-group wide search-group">
        <label>Search</label>
        <div class="search-wrap">
          <input
            v-model="search"
            @input="onSearchInput"
            @keyup.enter="load(); showSuggestions = false"
            @keydown.esc="showSuggestions = false"
            @blur="onSearchBlur"
            @focus="search.trim() && suggestions.length && (showSuggestions = true)"
            placeholder="Company name…"
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
        <select v-model="userId" @change="load">
          <option value="">All Users</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="statusFilter" @change="load">
          <option value="">All</option>
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="load">Search</button>
      <button v-if="hasFilters" class="btn btn-clear" @click="clearFilters">Clear</button>
    </div>

    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="record-count">
          <span class="count-label">{{ periodLabel }}</span>
          <span class="count-badge">{{ meta.total ?? todos.length }} to-do(s)</span>
        </span>
      </div>
      <LoadingSpinner v-if="loading" />
      <div v-else class="table-scroll">
        <table>
          <colgroup>
            <col style="width:34px">   <!-- checkbox -->
            <col style="width:92px">   <!-- date -->
            <col style="width:110px">  <!-- status -->
            <col style="width:80px">   <!-- type -->
            <col>                      <!-- company - absorbs remaining space -->
            <col style="width:70px">   <!-- user -->
            <col style="width:100px">  <!-- task -->
            <col style="width:124px">  <!-- remark -->
            <col style="width:100px">  <!-- follow-ups -->
            <col style="width:88px">   <!-- last f/u -->
            <col style="width:130px">  <!-- actions -->
          </colgroup>
          <thead>
            <tr>
              <th><input type="checkbox" @change="toggleAll" ref="selectAllRef"></th>
              <th>To Do Date</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Status</span>
                  <select v-model="colStatusFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="s in addLookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
              </th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Type</span>
                  <select v-model="colTypeFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in addLookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </th>
              <th>Company</th>
              <th>User</th>
              <th class="th-filter">
                <div class="col-head">
                  <span>Task</span>
                  <select v-model="colTaskFilter" @change="page = 1; load()" class="col-filter-sel">
                    <option value="">All</option>
                    <option v-for="t in addLookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </th>
              <th>Remark</th>
              <th>Follow-Ups</th>
              <th>Last F/U</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="todos.length === 0">
              <td colspan="11" class="empty-state">No to-dos found for this period.</td>
            </tr>
            <tr v-for="(t, idx) in todos" :key="t.id" :class="{ 'row-done': t.completion_status === 'completed' }">
              <td><input type="checkbox" :value="t.id" v-model="selectedIds"></td>
              <td><span class="date-text">{{ t.todo_date }}</span></td>
              <td>
                <span v-if="t.status" class="status-chip">{{ t.status }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td>{{ t.type ?? '—' }}</td>
              <td class="td-company">
                <router-link :to="`/contacts/${t.contact_id}`" class="company-link">{{ t.contact_name }}</router-link>
              </td>
              <td>{{ t.user ?? '—' }}</td>
              <td>
                <div class="task-chip-wrap">
                  <span v-if="t.task" class="task-chip">{{ t.task }}</span>
                  <span v-else class="muted">—</span>
                  <button v-if="can('edit todos') && t.can_edit" class="task-edit-btn" title="Change task" @click="openTaskChangeModal(t)" v-html="CI.edit"></button>
                </div>
              </td>
              <td class="remark-cell">
                <div class="remark-inner">
                  <span class="remark-text" @click="openRemarkModal(t)" :title="t.todo_remark || 'No remark'">{{ t.todo_remark || 'No remark' }}</span>
                  <button v-if="can('edit todos') && t.can_edit" class="remark-edit-btn" title="Edit remark" @click="openRemarkModal(t)" v-html="CI.edit"></button>
                </div>
              </td>
              <td>
                <router-link v-if="t.followups_count > 0"
                             :to="`/followups?todo_id=${t.id}`"
                             class="followup-count" title="View follow-ups for this to-do">
                  {{ t.followups_count }}
                </router-link>
                <span v-else class="muted">—</span>
              </td>
              <td>
                <span v-if="t.last_followup_date" class="date-text">{{ t.last_followup_date }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td>
                <div class="actions-cell">
                  <button v-if="can('create followups') && t.can_edit" class="fu-add-btn" title="Add a follow-up for this to-do" @click="openFollowUpModal(t)">+ F/U</button>
                  <button v-if="can('edit todos') && t.can_edit" class="icon-btn btn-edit" title="Edit" @click="openEditModal(t)" v-html="CI.edit"></button>
                  <button v-if="can('delete todos') && t.can_edit" class="icon-btn btn-delete" title="Delete to-do" @click="openDeleteTodoModal(t)" v-html="CI.trash"></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <span class="pager-count">Showing {{ todos.length }} of {{ meta.total ?? todos.length }} to-do(s)</span>
        <div class="pager-btns">
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage((meta.current_page ?? 1) - 1)">‹</button>
          <template v-for="pg in pageNumbers" :key="pg">
            <button v-if="pg !== '...'" class="pager-num" :class="{ 'pager-num--on': pg === (meta.current_page ?? 1) }" @click="changePage(pg)">{{ pg }}</button>
            <span v-else class="pager-ellipsis">…</span>
          </template>
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage((meta.current_page ?? 1) + 1)">›</button>
        </div>
        <div class="pager-rows">
          <span class="pager-rows-label">Rows</span>
          <select v-model.number="perPage" @change="changePage(1)" class="pager-rows-sel">
            <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>
    </div>
    <!-- Add To-Do Modal -->
    <div v-if="addModal.open" class="remark-overlay">
      <div class="add-todo-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Add To-Do</strong>
          </div>
          <button class="remark-close" @click="closeAddModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <form @submit.prevent="submitAddTodo">
            <div v-if="addModal.error" class="add-error-box">{{ addModal.error }}</div>
            <div class="add-form-group">
              <label>Company <span class="req">*</span></label>
              <select v-model="addForm.contact_id" required>
                <option value="">Select company</option>
                <option v-for="c in addContacts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Task <span class="req">*</span></label>
                <select v-model="addForm.task_id" required>
                  <option value="">Select task</option>
                  <option v-for="t in addLookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>User</label>
                <select v-model="addForm.user_id">
                  <option value="">Select user</option>
                  <option v-for="u in addLookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>To Do Date <span class="req">*</span></label>
                <input type="date" v-model="addForm.todo_date" required>
              </div>
              <div class="add-form-group">
                <label>Date Created</label>
                <input type="date" v-model="addForm.date_created">
              </div>
            </div>
            <div class="add-section-divider">Update Company Status</div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>New Status</label>
                <select v-model="addForm.status_id">
                  <option value="">— No change —</option>
                  <option v-for="s in addLookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div class="add-form-group">
                <label>New Type</label>
                <select v-model="addForm.type_id">
                  <option value="">— No change —</option>
                  <option v-for="t in addLookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-group">
              <label>Remark</label>
              <textarea v-model="addForm.todo_remark" placeholder="Enter remark or notes…" rows="3"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeAddModal">Cancel</button>
              <button type="submit" class="btn-todo-submit" :disabled="!addForm.contact_id || !addForm.task_id || !addForm.todo_date || addModal.saving">
                {{ addModal.saving ? 'Saving…' : 'Add To-Do' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Add Follow-Up Modal -->
    <div v-if="fuModal.open" class="remark-overlay">
      <div class="add-todo-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Add Follow-Up</strong>
          </div>
          <button class="remark-close" @click="closeFuModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <form @submit.prevent="submitFollowUp">
            <div v-if="fuModal.error" class="add-error-box">{{ fuModal.error }}</div>
            <div class="fu-context-row">
              <div class="fu-context-item">
                <span class="fu-context-label">Company</span>
                <span class="fu-context-value">{{ fuModal.contactName }}</span>
              </div>
              <div class="fu-context-item">
                <span class="fu-context-label">Task</span>
                <span class="fu-context-value">{{ fuModal.task ?? '—' }}</span>
              </div>
            </div>
            <div class="add-form-row">
              <div class="add-form-group">
                <label>Follow-Up Date <span class="req">*</span></label>
                <input type="date" v-model="fuForm.followup_date" required>
              </div>
              <div class="add-form-group">
                <label>Action Type</label>
                <select v-model="fuForm.action_type">
                  <option value="">— Select type —</option>
                  <option v-for="t in FU_ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>
            <div class="add-form-group">
              <label>Note</label>
              <textarea v-model="fuForm.note" placeholder="Enter follow-up note or outcome…" rows="4"></textarea>
            </div>
            <div class="add-modal-actions">
              <button type="button" class="btn btn-clear" @click="closeFuModal">Cancel</button>
              <button type="submit" class="btn-todo-submit" :disabled="!fuForm.followup_date || fuModal.saving">
                {{ fuModal.saving ? 'Saving…' : 'Add Follow-Up' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit To-Do Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="remark-overlay" @mousedown.self="closeEditModal">
        <div class="add-todo-modal">
          <div class="add-modal-header">
            <div class="add-modal-title-block">
              <strong class="add-modal-title">Edit To-Do</strong>
              <span v-if="editModal.todo" class="add-modal-sub">{{ editModal.todo.contact_name }}</span>
            </div>
            <button class="remark-close" @click="closeEditModal" v-html="CI.x"></button>
          </div>
          <div class="add-modal-body">
            <form @submit.prevent="submitEditTodo">
              <div v-if="editModal.error" class="add-error-box">{{ editModal.error }}</div>
              <div class="edit-company-chip">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:5px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                {{ editModal.todo?.contact_name }}
              </div>
              <div class="add-form-row">
                <div class="add-form-group">
                  <label>Task <span class="req">*</span></label>
                  <select v-model="editForm.task_id" required>
                    <option value="">Select task</option>
                    <option v-for="t in addLookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
                <div class="add-form-group">
                  <label>User</label>
                  <select v-model="editForm.user_id">
                    <option value="">Select user</option>
                    <option v-for="u in addLookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                </div>
              </div>
              <div class="add-form-row">
                <div class="add-form-group">
                  <label>To Do Date <span class="req">*</span></label>
                  <input type="date" v-model="editForm.todo_date" required>
                </div>
                <div class="add-form-group">
                  <label>Date Created</label>
                  <input type="date" v-model="editForm.date_created">
                </div>
              </div>
              <div class="add-section-divider">Update Company Status</div>
              <div class="add-form-row">
                <div class="add-form-group">
                  <label>New Status</label>
                  <select v-model="editForm.status_id">
                    <option value="">— No change —</option>
                    <option v-for="s in addLookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div class="add-form-group">
                  <label>New Type</label>
                  <select v-model="editForm.type_id">
                    <option value="">— No change —</option>
                    <option v-for="t in addLookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
              </div>
              <div class="add-form-group">
                <label>Remark</label>
                <textarea v-model="editForm.todo_remark" rows="3" placeholder="Enter remark or notes…"></textarea>
              </div>
              <div class="add-modal-actions">
                <button type="button" class="btn btn-clear" @click="closeEditModal">Cancel</button>
                <button type="submit" class="btn-todo-submit" :disabled="!editForm.task_id || !editForm.todo_date || editModal.saving">
                  {{ editModal.saving ? 'Saving…' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Remark Modal -->
    <div v-if="remarkModal.open" class="remark-overlay">
      <div class="add-todo-modal remark-modal">
        <div class="add-modal-header">
          <div class="add-modal-title-block">
            <strong class="add-modal-title">Edit Remark</strong>
          </div>
          <button class="remark-close" @click="closeRemarkModal" v-html="CI.x"></button>
        </div>
        <div class="add-modal-body">
          <div v-if="remarkModal.error" class="add-error-box">{{ remarkModal.error }}</div>
          <div class="fu-context-row">
            <div class="fu-context-item">
              <span class="fu-context-label">Company</span>
              <span class="fu-context-value">{{ remarkModal.contactName }}</span>
            </div>
          </div>
          <div class="add-form-group">
            <label>Remark</label>
            <textarea v-model="remarkModal.text" rows="4" placeholder="Enter remark or notes…"></textarea>
          </div>
          <div class="add-modal-actions">
            <button type="button" class="btn btn-clear" @click="closeRemarkModal">Cancel</button>
            <button type="button" class="btn-todo-submit" :disabled="remarkModal.saving" @click="saveRemark">
              {{ remarkModal.saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="followUpPrompt.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Pending Follow-Ups</p>
            <p class="conf-sub">This to-do has {{ followUpPrompt.count }} pending follow-up{{ followUpPrompt.count !== 1 ? 's' : '' }}.</p>
          </div>
          <button class="conf-close" @click="dismissFollowUpPrompt"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="1" fill="#1d4ed8" stroke="none"/>
          </svg>
          <p class="conf-text">Mark all {{ followUpPrompt.count }} pending follow-up{{ followUpPrompt.count !== 1 ? 's' : '' }} as complete too?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="dismissFollowUpPrompt">Skip</button>
          <button class="conf-followup-ok" :disabled="followUpPrompt.loading" @click="completeFollowUps">
            {{ followUpPrompt.loading ? 'Completing…' : 'Yes, mark complete' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="deleteTodoModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete To-Do</p>
            <p class="conf-sub">All linked follow-ups will also be removed.</p>
          </div>
          <button class="conf-close" @click="closeDeleteTodoModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete to-do for <strong>{{ deleteTodoModal.todo?.contact_name }}</strong>?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteTodoModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteTodoModal.loading" @click="confirmDeleteTodo">
            {{ deleteTodoModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Task Change Confirmation Modal -->
    <div v-if="taskChangeModal.open" class="conf-overlay" @mousedown.self="closeTaskChangeModal">
      <div class="conf-modal task-change-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Update Task</p>
            <p class="conf-sub">{{ taskChangeModal.todo?.contact_name }}</p>
          </div>
          <button class="conf-close" @click="closeTaskChangeModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="task-change-body">
          <div class="task-change-current">
            <span class="task-change-label">Current task</span>
            <span v-if="taskChangeModal.todo?.task" class="task-chip">{{ taskChangeModal.todo.task }}</span>
            <span v-else class="muted">None</span>
          </div>
          <div class="task-change-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
          <div class="task-change-new">
            <span class="task-change-label">Change to</span>
            <select v-model="taskChangeModal.selectedTaskId" class="task-change-sel">
              <option value="">— No task —</option>
              <option v-for="tk in addLookups.tasks" :key="tk.id" :value="String(tk.id)">{{ tk.name }}</option>
            </select>
          </div>
        </div>
        <div v-if="taskChangeDirty" class="task-change-summary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Change task to <strong>{{ taskChangeTarget?.name ?? '(none)' }}</strong>?
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeTaskChangeModal">Cancel</button>
          <button class="conf-followup-ok" :disabled="!taskChangeDirty || taskChangeModal.saving" @click="confirmTaskChange">
            {{ taskChangeModal.saving ? 'Saving…' : 'Confirm Update' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { usePermissions } from '../composables/usePermissions.js';

// `embedded` = rendered inside the Contacts page's To-Do tab (vs the standalone route).
const props = defineProps({ embedded: { type: Boolean, default: false } });

const { can } = usePermissions();
const route  = useRoute();
const router = useRouter();

const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const CI = {
  check: _si('<polyline points="20 6 9 17 4 12"/>'),
  undo:  _si('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.04"/>'),
  phone: _si('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.76 10a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 3.6.01h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 6.34a16 16 0 0 0 6 6l.34-.34a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 14z"/>'),
  edit:  _si('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash: _si('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
  x:     _si('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
};

const PER_PAGE_OPTIONS = [20, 50, 100];
const FU_ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];

const today = new Date().toISOString().slice(0, 10);

const thisMonth = today.slice(0, 7);

const search          = ref('');
const userId          = ref('');
const statusFilter    = ref('pending');
const viewMode        = ref('DateRange');
const rangeFrom       = ref(today);
const rangeTo         = ref(today);
const fromMonth       = ref(thisMonth);
const toMonth         = ref(thisMonth);
const contactId       = ref('');
const contactName     = ref('');
const perPage         = ref(50);
const page            = ref(1);
const todos           = ref([]);
const meta            = ref({});
const loading         = ref(false);
const users           = ref([]);
const selectedIds     = ref([]);
const selectAllRef    = ref(null);

// Task change modal
const taskChangeModal = ref({ open: false, todo: null, selectedTaskId: '', saving: false });

const taskChangeTarget = computed(() => {
  if (!taskChangeModal.value.open) return null;
  const id = taskChangeModal.value.selectedTaskId;
  return addLookups.value.tasks?.find(t => String(t.id) === String(id)) ?? null;
});

const taskChangeDirty = computed(() => {
  const m = taskChangeModal.value;
  if (!m.todo) return false;
  return String(m.selectedTaskId) !== String(m.todo.task_id ?? '');
});

function openTaskChangeModal(todo) {
  taskChangeModal.value = { open: true, todo, selectedTaskId: String(todo.task_id ?? ''), saving: false };
}
function closeTaskChangeModal() { taskChangeModal.value.open = false; }

async function confirmTaskChange() {
  const m = taskChangeModal.value;
  if (!m.todo || !taskChangeDirty.value) { closeTaskChangeModal(); return; }
  const taskId = m.selectedTaskId ? parseInt(m.selectedTaskId) : null;
  m.saving = true;
  try {
    await api.put(`/v1/todos/${m.todo.id}`, { todo_date: dmyToYmd(m.todo.todo_date), task_id: taskId });
    m.todo.task    = addLookups.value.tasks?.find(t => t.id === taskId)?.name ?? null;
    m.todo.task_id = taskId;
    closeTaskChangeModal();
  } catch { /* silent — row keeps old value */ }
  finally { m.saving = false; }
}

// Column header filters
const colStatusFilter = ref('');
const colTypeFilter   = ref('');
const colTaskFilter   = ref('');

// Edit modal
const editModal = ref({ open: false, saving: false, error: '', todo: null });
const editForm  = ref({ task_id: '', user_id: '', todo_date: '', date_created: '', todo_remark: '', status_id: '', type_id: '' });

// Follow-Up modal
const fuModal = ref({ open: false, saving: false, error: '', todoId: null, contactName: '', task: '' });
const fuForm  = ref({ followup_date: '', action_type: '', note: '' });

function openFollowUpModal(todo) {
  const today = new Date().toISOString().slice(0, 10);
  fuModal.value = { open: true, saving: false, error: '', todoId: todo.id, contactName: todo.contact_name, task: todo.task ?? null };
  fuForm.value  = { followup_date: today, action_type: '', note: '' };
}

function closeFuModal() { fuModal.value.open = false; }

async function submitFollowUp() {
  fuModal.value.saving = true;
  fuModal.value.error  = '';
  try {
    await api.post('/v1/followups', {
      todo_id:       fuModal.value.todoId,
      followup_date: fuForm.value.followup_date,
      action_type:   fuForm.value.action_type || null,
      note:          fuForm.value.note        || null,
    });
    closeFuModal();
    load();
  } catch (e) {
    const errors = e.response?.data?.errors;
    fuModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    fuModal.value.saving = false;
  }
}

// Add To-Do modal
const addContacts = ref([]);
const addLookups  = ref({ tasks: [], users: [], statuses: [], types: [] });
const addModal    = ref({ open: false, saving: false, error: '' });
const addForm     = ref({ contact_id: '', task_id: '', user_id: '', todo_date: today, date_created: today, todo_remark: '', status_id: '', type_id: '' });

const hasFilters = computed(() =>
  search.value || userId.value || statusFilter.value !== 'pending'
  || (viewMode.value === 'DateRange' && (rangeFrom.value !== today || rangeTo.value !== today))
  || (viewMode.value === 'MonthRange' && (fromMonth.value !== thisMonth || toMonth.value !== thisMonth))
  || colStatusFilter.value || colTypeFilter.value || colTaskFilter.value
);

// List rows return dates as d-m-Y; flip back to Y-m-d for API writes.
function dmyToYmd(d) {
  if (!d) return today;
  const p = String(d).split('-');
  return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : d;
}

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)           return [1, 2, 3, '...', total];
  if (cur >= total - 2)   return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

const periodLabel = computed(() => {
  const from = rangeFrom.value, to = rangeTo.value;
  const long  = (s) => new Date(s + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
  const short = (s) => s ? new Date(s + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Any';
  if (!from && !to) return 'All time';
  if (from && to && from === to) return long(from);
  return `${short(from)} – ${short(to)}`;
});

async function load() {
  loading.value = true;
  selectedIds.value = [];
  try {
    let fromDate, toDate;
    if (viewMode.value === 'MonthRange') {
      fromDate = fromMonth.value ? fromMonth.value + '-01' : '2000-01-01';
      // Last day of toMonth
      if (toMonth.value) {
        const [y, m] = toMonth.value.split('-').map(Number);
        toDate = new Date(y, m, 0).toISOString().slice(0, 10);
      } else { toDate = '2099-12-31'; }
    } else {
      fromDate = rangeFrom.value || '2000-01-01';
      toDate   = rangeTo.value   || '2099-12-31';
    }
    const params = { view: 'All', per_page: perPage.value, page: page.value, from_date: fromDate, to_date: toDate };
    if (search.value)          params.search    = search.value;
    if (userId.value)          params.user_id   = userId.value;
    if (statusFilter.value)    params.completion_status = statusFilter.value;
    if (contactId.value)       params.contact_id = contactId.value;
    if (colStatusFilter.value) params.status_id = colStatusFilter.value;
    if (colTypeFilter.value)   params.type_id   = colTypeFilter.value;
    if (colTaskFilter.value)   params.task_id   = colTaskFilter.value;
    const res = await api.get('/v1/todos', { params });
    todos.value = res.data.data;
    meta.value  = res.data; // paginator fields (current_page, last_page, total) are at top level
  } finally {
    loading.value = false;
  }
}

function onViewModeChange() {
  page.value = 1;
  load();
}

function clearFilters() {
  search.value          = '';
  userId.value          = '';
  statusFilter.value    = 'pending';
  viewMode.value        = 'DateRange';
  rangeFrom.value       = today;
  rangeTo.value         = today;
  fromMonth.value       = thisMonth;
  toMonth.value         = thisMonth;
  colStatusFilter.value = '';
  colTypeFilter.value   = '';
  colTaskFilter.value   = '';
  page.value            = 1;
  load();
}

function onRangeChange() {
  page.value = 1;
  load();
}

function clearContactFilter() {
  contactId.value   = '';
  contactName.value = '';
  // When embedded in the Contacts page tab, keep the ?tab=tasks query intact.
  if (props.embedded) router.replace({ query: { tab: 'tasks' } });
  else                router.replace({ query: {} });
  page.value = 1;
  load();
}

function changePage(p) {
  page.value = p;
  load();
}

function toggleAll(e) {
  selectedIds.value = e.target.checked ? todos.value.map(t => t.id) : [];
}

function exportSelected() {
  const ids = selectedIds.value.join(',');
  const token = localStorage.getItem('crm_token');
  window.location.href = `/api/v1/todos/export?ids=${ids}&_token=${token}`;
}

async function markSelectedDone() {
  const pending = todos.value.filter(t => selectedIds.value.includes(t.id) && t.can_edit && t.completion_status !== 'completed');
  if (!pending.length) return;
  await Promise.all(pending.map(t => api.patch(`/v1/todos/${t.id}/status`, { status: 'completed' }).then(() => { t.completion_status = 'completed'; })));
  selectedIds.value = [];
}

async function markSelectedPending() {
  const completed = todos.value.filter(t => selectedIds.value.includes(t.id) && t.can_edit && t.completion_status === 'completed');
  if (!completed.length) return;
  await Promise.all(completed.map(t => api.patch(`/v1/todos/${t.id}/status`, { status: 'pending' }).then(() => { t.completion_status = 'pending'; })));
  selectedIds.value = [];
}

// Search autocomplete
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
  page.value = 1;
  load();
}

function onSearchBlur() {
  setTimeout(() => { showSuggestions.value = false; }, 160);
}

const followUpPrompt = reactive({ open: false, todoId: null, count: 0, loading: false });

async function markDone(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'completed' });
  todo.completion_status = 'completed';
  // Non-blocking check for pending follow-ups
  try {
    const res = await api.get('/v1/followups', {
      params: { todo_id: todo.id, completion_status: 'pending', per_page: 1, view: 'DateRange', from_date: '2000-01-01', to_date: '2099-12-31' },
    });
    const count = res.data.meta?.total ?? 0;
    if (count > 0) {
      followUpPrompt.todoId = todo.id;
      followUpPrompt.count  = count;
      followUpPrompt.open   = true;
    }
  } catch (_) { /* non-critical */ }
}

async function markPending(todo) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status: 'pending' });
  todo.completion_status = 'pending';
}

async function completeFollowUps() {
  followUpPrompt.loading = true;
  try {
    await api.patch(`/v1/todos/${followUpPrompt.todoId}/complete-followups`);
  } finally {
    followUpPrompt.open    = false;
    followUpPrompt.todoId  = null;
    followUpPrompt.loading = false;
  }
}

function dismissFollowUpPrompt() {
  followUpPrompt.open   = false;
  followUpPrompt.todoId = null;
}

// Edit To-Do modal
function openEditModal(todo) {
  const statusId = addLookups.value.statuses?.find(s => s.name === todo.status)?.id ?? '';
  const typeId   = addLookups.value.types?.find(t => t.name === todo.type)?.id ?? '';
  editModal.value = { open: true, saving: false, error: '', todo };
  editForm.value  = {
    task_id:      todo.task_id      ?? '',
    user_id:      todo.user_id      ?? '',
    todo_date:    dmyToYmd(todo.todo_date),
    date_created: dmyToYmd(todo.date_created),
    todo_remark:  todo.todo_remark  ?? '',
    status_id:    statusId,
    type_id:      typeId,
  };
}

function closeEditModal() { editModal.value.open = false; }

async function submitEditTodo() {
  const todo = editModal.value.todo;
  if (!todo) return;
  editModal.value.saving = true;
  editModal.value.error  = '';
  try {
    await api.put(`/v1/todos/${todo.id}`, {
      task_id:      editForm.value.task_id      || null,
      user_id:      editForm.value.user_id      || null,
      todo_date:    editForm.value.todo_date,
      date_created: editForm.value.date_created || null,
      todo_remark:  editForm.value.todo_remark  || null,
      status_id:    editForm.value.status_id    || null,
      type_id:      editForm.value.type_id      || null,
    });
    const row = todos.value.find(t => t.id === todo.id);
    if (row) {
      row.task        = addLookups.value.tasks?.find(t => t.id == editForm.value.task_id)?.name ?? row.task;
      row.task_id     = editForm.value.task_id;
      row.user        = addLookups.value.users?.find(u => u.id == editForm.value.user_id)?.name ?? row.user;
      row.user_id     = editForm.value.user_id;
      row.todo_remark = editForm.value.todo_remark || null;
      const ns = addLookups.value.statuses?.find(s => s.id == editForm.value.status_id);
      const nt = addLookups.value.types?.find(t => t.id == editForm.value.type_id);
      if (ns) row.status = ns.name;
      if (nt) row.type   = nt.name;
    }
    closeEditModal();
  } catch (e) {
    const errors = e.response?.data?.errors;
    editModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    editModal.value.saving = false;
  }
}

const deleteTodoModal = reactive({ open: false, todo: null, loading: false });
function openDeleteTodoModal(todo) { deleteTodoModal.todo = todo; deleteTodoModal.open = true; }
function closeDeleteTodoModal() { deleteTodoModal.open = false; deleteTodoModal.todo = null; deleteTodoModal.loading = false; }

async function confirmDeleteTodo() {
  if (!deleteTodoModal.todo) return;
  deleteTodoModal.loading = true;
  try {
    await api.delete(`/v1/todos/${deleteTodoModal.todo.id}`);
    todos.value = todos.value.filter(t => t.id !== deleteTodoModal.todo.id);
    if (meta.value.total) meta.value.total--;
    closeDeleteTodoModal();
  } catch {
    closeDeleteTodoModal();
  } finally {
    deleteTodoModal.loading = false;
  }
}

// Inline remark editor
const remarkModal = ref({ open: false, saving: false, error: '', todo: null, contactName: '', text: '' });

function openRemarkModal(todo) {
  remarkModal.value = { open: true, saving: false, error: '', todo, contactName: todo.contact_name, text: todo.todo_remark || '' };
}

function closeRemarkModal() { remarkModal.value.open = false; }

async function saveRemark() {
  const todo = remarkModal.value.todo;
  if (!todo) return;
  remarkModal.value.saving = true;
  remarkModal.value.error  = '';
  try {
    await api.put(`/v1/todos/${todo.id}`, {
      todo_date:   dmyToYmd(todo.todo_date),
      todo_remark: remarkModal.value.text,
    });
    todo.todo_remark = remarkModal.value.text;
    closeRemarkModal();
  } catch (e) {
    remarkModal.value.error = e.response?.data?.message ?? 'Failed to save. Please try again.';
  } finally {
    remarkModal.value.saving = false;
  }
}

async function openAddModal() {
  addModal.value = { open: true, saving: false, error: '' };
  addForm.value  = { contact_id: '', task_id: '', user_id: '', todo_date: today, date_created: today, todo_remark: '', status_id: '', type_id: '' };
  if (!addContacts.value.length || !addLookups.value.tasks.length) {
    const [cRes, lRes] = await Promise.all([
      api.get('/v1/contacts', { params: { per_page: 1000 } }),
      api.get('/v1/lookups'),
    ]);
    addContacts.value = cRes.data.data;
    addLookups.value  = lRes.data;
  }
}

function closeAddModal() { addModal.value.open = false; }

async function submitAddTodo() {
  addModal.value.saving = true;
  addModal.value.error  = '';
  try {
    await api.post('/v1/todos', {
      contact_id:   addForm.value.contact_id,
      task_id:      addForm.value.task_id      || null,
      user_id:      addForm.value.user_id      || null,
      todo_date:    addForm.value.todo_date,
      date_created: addForm.value.date_created || null,
      todo_remark:  addForm.value.todo_remark  || null,
    });
    if (addForm.value.contact_id && (addForm.value.status_id || addForm.value.type_id)) {
      const patch = {};
      if (addForm.value.status_id) patch.status_id = addForm.value.status_id;
      if (addForm.value.type_id)   patch.type_id   = addForm.value.type_id;
      await api.put(`/v1/contacts/${addForm.value.contact_id}`, patch);
    }
    closeAddModal();
    load();
  } catch (e) {
    const errors = e.response?.data?.errors;
    addModal.value.error = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to save. Please try again.');
  } finally {
    addModal.value.saving = false;
  }
}

onMounted(async () => {
  // Arriving from a contact's "Open in To-Do" — pre-filter to that contact, show its full history.
  if (route.query.contact_id) {
    contactId.value    = String(route.query.contact_id);
    contactName.value  = route.query.contact_name ? String(route.query.contact_name) : '';
    rangeFrom.value    = '';   // empty range = all dates for this contact
    rangeTo.value      = '';
    statusFilter.value = '';
  }
  const lu = await api.get('/v1/lookups');
  users.value = lu.data.users ?? [];
  addLookups.value = lu.data;
  load();
});
</script>

<style scoped>
.page { padding: 28px 16px 48px; max-width: 1500px; margin: 0 auto; }
/* When embedded in the Contacts page tab, the tab provides the outer chrome. */
.page-embedded { padding: 0; max-width: none; }
.page-head-embedded { margin-bottom: 14px; }
.page-head-embedded:empty,
.page-head-embedded .page-head-actions:only-child { justify-content: flex-end; }

/* Always-visible Date Range (mirrors the Contacts tab) */
.filter-date-range { display: flex; flex-direction: column; gap: 5px; }
.date-range-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.date-range-inputs { display: flex; align-items: center; gap: 6px; }
.date-input-wrap { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 999px; overflow: hidden; height: 38px; background: var(--surface); transition: border-color 0.15s, box-shadow 0.15s; }
.date-input-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.date-input-prefix { padding: 0 10px 0 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); white-space: nowrap; pointer-events: none; border-right: 1px solid var(--border-soft); }
.date-range-input { border: none !important; outline: none !important; box-shadow: none !important; height: 36px; padding: 0 12px; font-size: 13px; background: transparent; color: var(--text-1); min-width: 130px; }
.date-range-sep { font-size: 12px; color: var(--text-3); flex-shrink: 0; }

/* Page head */
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

.btn-primary-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary); color: var(--primary-on);
  border: none; border-radius: 999px; padding: 11px 20px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  white-space: nowrap; text-decoration: none;
  box-shadow: 0 8px 22px -8px rgba(29,78,216,0.6);
  transition: background 0.15s, transform 0.06s;
}
.btn-primary-pill:hover { background: var(--primary-hover); }
.btn-primary-pill:active { transform: translateY(1px); }
.plus-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(255,255,255,0.18); font-size: 14px; font-weight: 700; line-height: 1;
}

/* Contact filter bar (arrived from a contact's "Open in To-Do") */
.contact-filter-bar {
  display: flex; align-items: center; justify-content: space-between; gap: 14px;
  background: var(--primary-soft); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 10px 18px; margin-bottom: 14px;
}
.cfb-text { font-size: 13px; color: var(--primary-text); }
.cfb-clear {
  background: var(--surface); color: var(--text-2); border: 1px solid var(--border);
  border-radius: 999px; padding: 5px 14px; cursor: pointer; font-size: 12px; font-weight: 600;
  white-space: nowrap; transition: border-color 0.15s, color 0.15s;
}
.cfb-clear:hover { border-color: var(--primary); color: var(--primary); }

/* Selection bar */
.selection-bar {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 10px 18px; margin-bottom: 14px;
  display: flex; align-items: center; gap: 14px; font-size: 13px;
  color: var(--text-2); box-shadow: var(--shadow-xs);
}
.btn-export-sel {
  background: #10b981; color: white; border: none; border-radius: 999px;
  padding: 6px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
}
.btn-export-sel:hover { background: #059669; }
.btn-done-sel {
  background: var(--primary); color: white; border: none; border-radius: 999px;
  padding: 6px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
}
.btn-done-sel:hover { background: var(--primary-hover); }
.btn-pending-sel {
  background: var(--surface); color: var(--text-2); border: 1px solid var(--border);
  border-radius: 999px; padding: 6px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
}
.btn-pending-sel:hover { background: var(--surface-2); }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius-lg); padding: 14px 16px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-soft);
  display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 200px; }
.filter-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.filter-group select, .filter-group input {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 28px; }
.filter-group select:focus, .filter-group input:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.btn { height: 38px; padding: 0 18px; border: none; border-radius: 999px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.15s, transform 0.06s; }
.btn:active { transform: translateY(1px); }
.btn-primary { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-clear { background: var(--surface); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-soft); }

/* Date navigator */
.date-nav { display: flex; align-items: center; gap: 4px; align-self: flex-end; }
.date-nav-arrow {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 38px; border-radius: 999px;
  border: 1px solid var(--border); background: var(--surface);
  cursor: pointer; color: var(--text-2); transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.date-nav-arrow:hover { background: var(--primary-soft); color: var(--primary); border-color: var(--primary-soft); }
/* CalendarPicker trigger inherits its own styles from the component */

/* Table */
.table-wrap { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft); overflow: hidden; }
.table-header-bar {
  background: var(--surface); padding: 16px 22px;
  border-bottom: 1px solid var(--border-soft);
  display: flex; align-items: center; gap: 12px;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
.table-scroll { overflow-x: hidden; }
table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap; overflow: hidden;
}
thead th:last-child { border-right: none; }
tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; font-size: 13px; white-space: nowrap; overflow: hidden; }
tbody td.td-company { white-space: normal; word-break: break-word; overflow: visible; }
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

.row-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; background: var(--surface-2);
  border-radius: 999px; font-size: 10px; font-weight: 700; color: var(--text-3);
}
.date-text { font-size: 11.5px; color: var(--text-2); font-weight: 500; white-space: nowrap; }
.completed-date { font-size: 11px; font-weight: 600; color: var(--success); background: var(--success-soft); padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; white-space: normal; word-break: break-word; }
.company-link:hover { color: var(--primary); }
.status-chip { background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.remark-cell { max-width: 170px; }
.remark-inner { display: flex; align-items: center; gap: 6px; }
.remark-text {
  flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  font-size: 11.5px; color: var(--text-2); cursor: pointer;
  transition: color 0.12s;
}
.remark-text:hover { color: var(--primary); text-decoration: underline dotted; }
.remark-edit-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: var(--radius-sm); flex-shrink: 0;
  border: 1px solid var(--border); background: var(--surface-2); color: var(--text-2); cursor: pointer;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.remark-edit-btn:hover { background: #fefce8; color: #92400e; border-color: #fde68a; }
.remark-modal { width: 460px; }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: var(--radius-sm); text-decoration: none; font-size: 13px;
  cursor: pointer; border: none; transition: background 0.12s, transform 0.06s;
}
.icon-btn:active { transform: scale(0.92); }
.btn-edit { background: #fefce8; }
.btn-edit:hover { background: #fde68a; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.btn-delete:hover { background: #fca5a5; }
.btn-followup { background: #fce7f3; color: #9d174d; }
.btn-followup:hover { background: #f9a8d4; }
.fu-add-btn {
  display: inline-flex; align-items: center; justify-content: center;
  height: 24px; padding: 0 9px;
  border-radius: 999px;
  border: 1px solid #f9a8d4;
  background: #fce7f3; color: #9d174d;
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.2px;
  cursor: pointer; white-space: nowrap; flex-shrink: 0;
  transition: background 0.12s, border-color 0.12s, transform 0.06s;
}
.fu-add-btn:hover { background: #f9a8d4; border-color: #f472b6; }
.fu-add-btn:active { transform: scale(0.93); }
.actions-cell { display: flex; gap: 4px; align-items: center; }

/* Month input */
.month-input {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.month-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.view-sel {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s;
}
.view-sel:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }

/* Task chip in table cell */
.task-chip-wrap {
  display: flex; align-items: center; gap: 5px;
  overflow: hidden; max-width: 100%;
}
.task-chip {
  background: var(--primary-soft); color: var(--primary-text);
  font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  min-width: 0; flex-shrink: 1;
}
.task-edit-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; flex-shrink: 0;
  border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--surface-2); color: var(--text-2); cursor: pointer;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.task-edit-btn:hover { background: #fefce8; color: #92400e; border-color: #fde68a; }

/* Task change confirmation modal */
.task-change-modal { width: 440px; max-width: 95vw; }
.task-change-body {
  display: flex; align-items: center; gap: 10px;
  padding: 20px 22px; background: var(--surface-2);
  border-bottom: 1px solid var(--border-soft);
}
.task-change-current, .task-change-new {
  flex: 1; display: flex; flex-direction: column; gap: 6px; min-width: 0;
}
.task-change-arrow {
  flex-shrink: 0; color: var(--text-3); margin-top: 20px;
}
.task-change-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; color: var(--text-3);
}
.task-change-sel {
  width: 100%; height: 36px; padding: 0 10px;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; background: var(--surface); color: var(--text-1);
  outline: none; cursor: pointer;
}
.task-change-sel:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.task-change-summary {
  display: flex; align-items: center; gap: 6px;
  padding: 10px 22px; font-size: 12.5px; color: var(--primary-text);
  background: var(--primary-soft); border-bottom: 1px solid var(--border-soft);
}

/* Column header filters */
.th-filter { white-space: normal !important; overflow: visible !important; vertical-align: top !important; padding: 8px 10px !important; }
.col-head { display: flex; flex-direction: column; gap: 5px; }
.col-head span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.55px; color: var(--text-2); white-space: nowrap; }
.col-filter-sel {
  width: 100%; height: 22px; font-size: 11px; padding: 0 4px;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); color: var(--text-1); cursor: pointer;
}
.col-filter-sel:focus { outline: 1px solid var(--primary); }

/* Edit modal company chip */
.edit-company-chip {
  background: var(--primary-soft); color: var(--primary-text);
  border-radius: var(--radius-sm); padding: 7px 13px;
  font-size: 13.5px; font-weight: 700; margin-bottom: 18px;
  display: inline-block;
}
.followup-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 28px; padding: 3px 10px; border-radius: 999px;
  background: #fce7f3; color: #9d174d; font-size: 11.5px; font-weight: 700;
  text-decoration: none;
}
.followup-count:hover { background: #f9a8d4; }
.muted { color: var(--text-3); }
.row-done td { opacity: 0.5; text-decoration: line-through; text-decoration-color: var(--border); }
.row-done .icon-btn { text-decoration: none; opacity: 1; }
.row-done .completed-date { text-decoration: none; opacity: 1; }
.empty-state { text-align: center; padding: 48px; color: var(--text-3); font-size: 14px; }

.pager {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-top: 1px solid var(--border-soft);
  background: var(--surface); gap: 12px; flex-wrap: wrap;
}
.pager-count { font-size: 12px; color: var(--text-3); white-space: nowrap; }
.pager-btns  { display: flex; align-items: center; gap: 3px; }
.pager-nav {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  color: var(--text-2); cursor: pointer; font-size: 16px; line-height: 1;
  transition: background 0.12s;
}
.pager-nav:hover:not(:disabled) { background: var(--primary-soft); color: var(--primary); }
.pager-nav:disabled { opacity: 0.3; cursor: default; }
.pager-num {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  font-size: 12px; font-weight: 600; color: var(--text-2); cursor: pointer;
  transition: background 0.12s;
}
.pager-num:hover { background: var(--primary-soft); color: var(--primary); }
.pager-num--on { background: var(--primary); color: var(--primary-on); font-weight: 700; }
.pager-ellipsis { width: 30px; text-align: center; color: var(--text-3); font-size: 13px; }
.pager-rows { display: flex; align-items: center; gap: 6px; }
.pager-rows-label { font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; }
.pager-rows-sel {
  height: 30px; padding: 0 10px; border: 1px solid var(--border); border-radius: 999px;
  font-size: 12px; background: var(--surface); color: var(--text-1); outline: none; cursor: pointer;
  transition: border-color 0.15s;
}
.pager-rows-sel:focus { border-color: var(--primary); }

@media (max-width: 768px) {
  .page { padding: 16px 10px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 12px; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
  .filter-group { flex: 1 1 45%; }
  .filter-group.wide { flex: 1 1 100%; }
  .filter-group.wide input { width: 100%; }
}

/* Search autocomplete */
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

/* Add To-Do Modal */
.remark-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(4px);
  z-index: 700;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.add-todo-modal {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 580px; max-width: 95vw; max-height: 92vh;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}
.add-modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.add-modal-title-block { display: flex; align-items: center; gap: 12px; }
.add-modal-title { color: var(--text-1); font-size: 17px; font-weight: 800; letter-spacing: -0.2px; }
.add-modal-body { padding: 22px 24px; overflow-y: auto; }
.add-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.add-form-group { margin-bottom: 14px; }
.add-form-group label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: var(--text-2); margin-bottom: 6px;
}
.add-form-group input,
.add-form-group select,
.add-form-group textarea {
  width: 100%; height: 42px; padding: 0 14px;
  border: 1px solid var(--border); border-radius: 999px;
  font-size: 13.5px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.add-form-group textarea { height: 80px; padding: 10px 14px; resize: vertical; border-radius: var(--radius); }
.add-form-group input:focus,
.add-form-group select:focus,
.add-form-group textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.add-section-divider {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.7px; color: var(--text-3);
  padding: 8px 0 12px; border-top: 1px solid var(--border-soft);
  margin: 4px 0 14px;
}
.add-error-box {
  background: var(--danger-soft); color: var(--danger);
  border-radius: var(--radius); padding: 12px 16px;
  font-size: 13px; margin-bottom: 14px;
}
.add-modal-actions { display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end; }
.add-modal-actions .btn { height: 42px; padding: 0 22px; }
.remark-close {
  background: var(--surface-2); border: none; cursor: pointer;
  font-size: 14px; color: var(--text-2);
  width: 30px; height: 30px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  line-height: 1; transition: background 0.15s, color 0.15s;
}
.remark-close:hover { background: var(--danger-soft); color: var(--danger); }
.btn-todo-submit {
  flex: 1; background: var(--primary); color: var(--primary-on); justify-content: center;
  height: 42px; padding: 0 20px; border-radius: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer; border: none;
  display: inline-flex; align-items: center;
  box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55);
}
.btn-todo-submit:hover:not(:disabled) { background: var(--primary-hover); }
.btn-todo-submit:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; }
.req { color: #ef4444; }

/* Follow-Up modal context row */
.fu-context-row {
  display: flex; gap: 14px; background: var(--surface-2);
  border-radius: var(--radius); padding: 10px 14px; margin-bottom: 16px;
}
.fu-context-item { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.fu-context-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.fu-context-value { font-size: 13px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Confirm modal ── */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; }
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
.conf-followup-ok { height: 38px; padding: 0 18px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-followup-ok:hover:not(:disabled) { background: var(--primary-hover); }
.conf-followup-ok:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
