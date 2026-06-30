<template>
  <div class="page">

    <!-- Page header -->
    <div class="page-head">
      <router-link to="/list" class="back-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>Back to Contacts</router-link>
      <div class="head-actions" v-if="contact" data-tour="contact-head-actions">
        <button v-if="can('create todos')" class="btn btn-outline" @click="openAddTaskPanel">+ Add To-Do</button>
        <button v-if="can('create forecasts')" class="btn btn-info" @click="openForecastAdd">+ Forecast</button>
        <button
          v-if="can('edit contacts') && contact.can_edit"
          :class="contact.is_permanently_closed ? 'btn btn-success' : 'btn btn-closed'"
          @click="contact.is_permanently_closed ? toggleClosed() : openClosedModal()"
        >
          {{ contact.is_permanently_closed ? 'Mark as Active' : 'Mark as Closed' }}
        </button>
        <router-link v-if="can('edit contacts') && contact.can_edit" :to="`/contacts/${id}/edit`" class="btn btn-warn">Edit</router-link>
        <button v-if="can('delete contacts') && contact.can_edit" class="btn btn-danger" @click="openDeleteModal">Delete</button>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else-if="contact">

      <!-- Permanently closed alert -->
      <div v-if="contact.is_permanently_closed" class="closed-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        <span>This business is marked as <strong>Permanently Closed</strong>. Records are kept for reference.</span>
      </div>

      <!-- Profile banner -->
      <div class="profile-banner" data-tour="contact-banner">
        <div class="profile-avatar">{{ initials(contact.name) }}</div>
        <div class="profile-info">
          <h1 class="profile-name">{{ contact.name }}</h1>
          <div class="profile-pills">
            <span v-if="contact.status"   class="meta-pill pill-status">{{ contact.status.name }}</span>
            <span v-if="contact.type"     class="meta-pill pill-type">{{ contact.type.name }}</span>
            <span v-if="contact.industry" class="meta-pill pill-industry">{{ contact.industry.name }}</span>
            <span v-if="contact.category" class="meta-pill pill-category">{{ contact.category.name }}</span>
          </div>
        </div>
        <div class="profile-stats">
          <div class="pstat">
            <span class="pstat-num">{{ contact.todos?.length ?? 0 }}</span>
            <span class="pstat-lbl">To-Dos</span>
          </div>
          <div class="pstat">
            <span class="pstat-num">{{ totalFollowUps }}</span>
            <span class="pstat-lbl">Follow-Ups</span>
          </div>
          <div class="pstat">
            <span class="pstat-num">{{ contact.forecasts?.length ?? 0 }}</span>
            <span class="pstat-lbl">Forecasts</span>
          </div>
        </div>
      </div>

      <!-- Two-column: Details + Activity -->
      <div class="two-col">
        <div>
          <!-- Company Details -->
          <div class="card">
            <div class="card-title">Company Details</div>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">CS</span>
                <span class="detail-value">{{ contact.user?.name ?? '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Date Added</span>
                <span class="detail-value">{{ fmtDate(contact.created_at) }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Lead Source</span>
                <span v-if="contact.lead_source" :class="['source-badge', `source-${contact.lead_source}`]">{{ sourceLabel(contact.lead_source) }}</span>
                <span v-else class="detail-muted">—</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">To-Dos / Follow-Ups</span>
                <span class="detail-value">{{ contact.todos?.length ?? 0 }} / {{ totalFollowUps }}</span>
              </div>
            </div>
            <div v-if="contact.address" class="detail-full">
              <div class="detail-label-row">
                <span class="detail-label">Address</span>
                <a :href="mapsUrl(contact)" target="_blank" rel="noopener noreferrer" class="maps-link" @click.stop>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  Check on Google Maps
                </a>
              </div>
              <div class="detail-value">{{ contact.address }}</div>
            </div>
            <div v-if="contact.remark" class="detail-full">
              <div class="detail-label">Remark</div>
              <div class="detail-value pre-line">{{ contact.remark }}</div>
            </div>
          </div>

          <!-- Persons in Charge -->
          <div class="card" v-if="contact.incharges?.length">
            <div class="card-title">Persons in Charge ({{ contact.incharges.length }})</div>
            <table class="data-table">
              <thead><tr><th>Name</th><th>Mobile</th><th>Email</th></tr></thead>
              <tbody>
                <tr v-for="pic in contact.incharges" :key="pic.id">
                  <td><strong>{{ pic.name }}</strong></td>
                  <td>{{ pic.phone_mobile || '—' }}</td>
                  <td>
                    <a v-if="pic.email" :href="`mailto:${pic.email}`" class="email-link">{{ pic.email }}</a>
                    <span v-else class="muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Monthly Activity -->
        <div>
          <div class="card" data-tour="contact-activity-card">
            <div class="card-title-row">
              <span class="card-title">Monthly Activity</span>
              <select v-model="actYear" class="year-sel">
                <option v-for="y in actYears" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
            <div class="month-grid">
              <div
                v-for="(mname, i) in MONTH_NAMES" :key="i"
                class="month-cell"
                :class="{ 'month-active': monthMap[i+1] }"
                :title="monthMap[i+1] ? `${monthMap[i+1].date} — ${monthMap[i+1].task}` : ''"
              >
                <div class="month-label">{{ mname }}</div>
                <template v-if="monthMap[i+1]">
                  <div class="month-date">{{ monthMap[i+1].date }}</div>
                  <div class="month-task">{{ monthMap[i+1].task }}</div>
                </template>
                <div v-else class="month-empty">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tasks -->
      <div class="card" data-tour="contact-tasks-card">
        <div class="card-title-row">
          <span class="card-title">To-Dos ({{ contact.todos?.length ?? 0 }})</span>
          <button class="btn btn-sm btn-success" @click="openAddTaskPanel">+ Add To-Do</button>
        </div>

        <!-- Inline Add Task form -->
        <div v-if="addTaskOpen" class="inline-form">
          <div class="if-row">
            <div class="if-field">
              <label>Task Type</label>
              <select v-model="addTaskForm.task_id">
                <option value="">Select task type</option>
                <option v-for="t in lookups.tasks" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="if-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" v-model="addTaskForm.todo_date">
            </div>
          </div>
          <div class="if-field">
            <label>Remark</label>
            <textarea v-model="addTaskForm.todo_remark" rows="2" placeholder="Optional notes…"></textarea>
          </div>
          <div v-if="addTaskError" class="if-error">{{ addTaskError }}</div>
          <div class="if-actions">
            <button class="btn btn-sm btn-outline" @click="addTaskOpen = false; addTaskError = ''">Cancel</button>
            <button class="btn btn-sm btn-primary" :disabled="!addTaskForm.todo_date || addTaskSaving" @click="submitAddTask">
              {{ addTaskSaving ? 'Saving…' : 'Save To-Do' }}
            </button>
          </div>
        </div>

        <p v-if="!contact.todos?.length" class="empty-text">No to-dos logged yet.</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Date</th><th>Task</th><th>User</th><th>Remark</th>
              <th style="text-align:center">Follow-Ups</th><th>Status</th>
              <th style="text-align:center;width:80px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="td in contact.todos" :key="td.id" :class="{ 'row-done': td.completion_status === 'completed' }">
              <td class="date-cell">{{ fmtDate(td.todo_date) }}</td>
              <td><span v-if="td.task" class="task-badge">{{ td.task.name }}</span><span v-else class="muted">—</span></td>
              <td>{{ td.user?.name ?? '—' }}</td>
              <td class="remark-cell">{{ td.todo_remark || '—' }}</td>
              <td style="text-align:center">
                <button class="fu-pill" :class="{ 'fu-pill-has': td.follow_ups?.length }" @click="openTaskFuModal(td)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.76 10a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 3.6.01h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 6.34a16 16 0 0 0 6 6l.34-.34a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 14z"/></svg>
                  {{ td.follow_ups?.length ?? 0 }}
                </button>
              </td>
              <td>
                <span class="status-pill" :class="td.completion_status === 'completed' ? 'pill-done' : td.completion_status === 'cancelled' ? 'pill-cancel' : 'pill-pending'">
                  {{ td.completion_status ?? 'pending' }}
                </span>
              </td>
              <td style="text-align:center">
                <div class="task-action-cell">
                  <button v-if="can('edit todos') && td.completion_status !== 'completed'" class="done-btn" title="Mark complete" @click="toggleDone(td, 'completed')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                  <button v-else-if="can('edit todos')" class="undo-btn" title="Mark pending" @click="toggleDone(td, 'pending')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.63"/></svg></button>
                  <button v-if="can('delete todos')" class="task-del-btn" title="Delete to-do" @click="openDeleteTaskModal(td)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Forecasts -->
      <div class="card" data-tour="contact-forecast-card">
        <div class="card-title-row">
          <span class="card-title">Forecast History ({{ contact.forecasts?.length ?? 0 }})</span>
          <button v-if="can('create forecasts')" class="btn btn-sm btn-info" @click="openForecastAdd">+ Add Forecast</button>
        </div>
        <p v-if="!contact.forecasts?.length" class="empty-text">No forecasts recorded yet.</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Date</th><th>Product</th><th>Type</th>
              <th class="amount-col">Amount</th>
              <th>Result</th><th>Owner</th><th>Updated</th>
              <th style="width:90px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in contact.forecasts" :key="f.id">
              <td class="date-cell">{{ fmtDate(f.forecast_date) }}</td>
              <td>{{ f.product?.name ?? '—' }}</td>
              <td><span class="task-badge">{{ f.forecast_type?.name ?? '—' }}</span></td>
              <td class="amount-cell">{{ fmtCurrency(f.amount) }}</td>
              <td><span class="result-badge" :class="resultClass(f.result?.name)">{{ f.result?.name ?? 'No Result' }}</span></td>
              <td>{{ f.user?.name ?? '—' }}</td>
              <td class="date-cell">{{ fmtDate(f.forecast_updatedate) }}</td>
              <td>
                <div class="action-pair">
                  <button v-if="can('edit forecasts')" class="inline-btn edit-btn" @click="openForecastEdit(f.id)">Edit</button>
                  <button v-if="can('delete forecasts')" class="inline-btn del-btn" @click="openDeleteForecastModal(f.id)">Del</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Email Log -->
      <div class="card">
        <div class="card-title-row">
          <span class="card-title">Email Log ({{ emails.length }})</span>
          <button v-if="canLog" class="btn btn-sm btn-info" @click="toggleEmailForm">
            {{ emailFormOpen ? 'Cancel' : '+ Log Email' }}
          </button>
        </div>

        <div v-if="emailFormOpen" class="inline-form">
          <div class="if-row">
            <div class="if-field">
              <label>Direction</label>
              <div class="seg">
                <button type="button" :class="{ on: emailForm.direction === 'sent' }" @click="emailForm.direction = 'sent'">Sent</button>
                <button type="button" :class="{ on: emailForm.direction === 'received' }" @click="emailForm.direction = 'received'">Received</button>
              </div>
            </div>
            <div class="if-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" v-model="emailForm.emailed_at">
            </div>
          </div>
          <div class="if-field">
            <label>Subject <span class="req">*</span></label>
            <input v-model="emailForm.subject" maxlength="255" placeholder="e.g. Quotation for Q3 billboard campaign">
          </div>
          <div class="if-field">
            <label>Details</label>
            <textarea v-model="emailForm.body" rows="2" placeholder="Optional summary or outcome…"></textarea>
          </div>
          <div v-if="emailError" class="if-error">{{ emailError }}</div>
          <div class="if-actions">
            <button class="btn btn-sm btn-outline" @click="emailFormOpen = false">Cancel</button>
            <button class="btn btn-sm btn-primary" :disabled="!emailForm.subject.trim() || !emailForm.emailed_at || emailSaving" @click="submitEmail">
              {{ emailSaving ? 'Saving…' : 'Save Email' }}
            </button>
          </div>
        </div>

        <p v-if="!emails.length" class="empty-text">No emails logged yet.</p>
        <table v-else class="data-table">
          <thead>
            <tr><th>Date</th><th>Direction</th><th>Subject</th><th>Logged By</th><th class="log-act-col"></th></tr>
          </thead>
          <tbody>
            <tr v-for="em in emails" :key="em.id">
              <td class="date-cell">{{ fmtDate(em.emailed_at) }}</td>
              <td>
                <span class="dir-badge" :class="em.direction === 'sent' ? 'dir-out' : 'dir-in'">
                  <svg v-if="em.direction === 'sent'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                  <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/></svg>
                  {{ em.direction === 'sent' ? 'Sent' : 'Received' }}
                </span>
              </td>
              <td>
                <div class="log-subject">{{ em.subject }}</div>
                <div v-if="em.body" class="log-body">{{ em.body }}</div>
              </td>
              <td>{{ em.user?.name ?? '—' }}</td>
              <td class="log-act-col">
                <template v-if="em._confirmDel">
                  <button class="inline-btn confirm-btn" @click="deleteEmail(em)">Confirm</button>
                  <button class="inline-btn cancel-btn" @click="em._confirmDel = false">Cancel</button>
                </template>
                <button v-else-if="canLog" class="inline-btn del-btn" @click="em._confirmDel = true">Del</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Call Log -->
      <div class="card">
        <div class="card-title-row">
          <span class="card-title">Call Log ({{ calls.length }})</span>
          <button v-if="canLog" class="btn btn-sm btn-info" @click="toggleCallForm">
            {{ callFormOpen ? 'Cancel' : '+ Log Call' }}
          </button>
        </div>

        <div v-if="callFormOpen" class="inline-form">
          <div class="if-row">
            <div class="if-field">
              <label>Direction</label>
              <div class="seg">
                <button type="button" :class="{ on: callForm.direction === 'outbound' }" @click="callForm.direction = 'outbound'">Outbound</button>
                <button type="button" :class="{ on: callForm.direction === 'inbound' }" @click="callForm.direction = 'inbound'">Inbound</button>
              </div>
            </div>
            <div class="if-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" v-model="callForm.called_at">
            </div>
          </div>
          <div class="if-field">
            <label>Duration (minutes)</label>
            <input type="number" min="1" max="9999" v-model="callForm.duration" placeholder="e.g. 15">
          </div>
          <div class="if-field">
            <label>Notes</label>
            <textarea v-model="callForm.notes" rows="2" placeholder="What was discussed, next steps…"></textarea>
          </div>
          <div v-if="callError" class="if-error">{{ callError }}</div>
          <div class="if-actions">
            <button class="btn btn-sm btn-outline" @click="callFormOpen = false">Cancel</button>
            <button class="btn btn-sm btn-primary" :disabled="!callForm.called_at || callSaving" @click="submitCall">
              {{ callSaving ? 'Saving…' : 'Save Call' }}
            </button>
          </div>
        </div>

        <p v-if="!calls.length" class="empty-text">No calls logged yet.</p>
        <table v-else class="data-table">
          <thead>
            <tr><th>Date</th><th>Direction</th><th>Duration</th><th>Notes</th><th>Logged By</th><th class="log-act-col"></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in calls" :key="c.id">
              <td class="date-cell">{{ fmtDate(c.called_at) }}</td>
              <td>
                <span class="dir-badge" :class="c.direction === 'outbound' ? 'dir-out' : 'dir-in'">
                  <svg v-if="c.direction === 'outbound'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
                  <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="7" x2="7" y2="17"/><polyline points="17 17 7 17 7 7"/></svg>
                  {{ c.direction === 'outbound' ? 'Outbound' : 'Inbound' }}
                </span>
              </td>
              <td>{{ c.duration ? c.duration + ' min' : '—' }}</td>
              <td class="remark-cell">{{ c.notes || '—' }}</td>
              <td>{{ c.user?.name ?? '—' }}</td>
              <td class="log-act-col">
                <template v-if="c._confirmDel">
                  <button class="inline-btn confirm-btn" @click="deleteCall(c)">Confirm</button>
                  <button class="inline-btn cancel-btn" @click="c._confirmDel = false">Cancel</button>
                </template>
                <button v-else-if="canLog" class="inline-btn del-btn" @click="c._confirmDel = true">Del</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>
    <div v-else class="not-found">Contact not found.</div>

    <!-- Task Follow-Up Modal -->
    <div v-if="taskFuModal.open" class="modal-overlay">
      <div class="modal-box task-fu-modal">
        <div class="modal-head">
          <div class="modal-head-left">
            <strong class="modal-head-title">Follow-Ups</strong>
            <span class="task-chip" v-if="taskFuModal.todo">
              {{ taskFuModal.todo.task?.name ?? 'Task' }} — {{ fmtDate(taskFuModal.todo.todo_date) }}
            </span>
          </div>
          <button class="modal-close-btn" @click="closeTaskFuModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body">
          <div v-if="taskFuModal.todo?.todo_remark" class="fu-remark-box">
            <span class="detail-label">Note:</span> {{ taskFuModal.todo.todo_remark }}
          </div>
          <div class="fu-section-lbl">History ({{ taskFuModal.todo?.follow_ups?.length ?? 0 }})</div>
          <div v-if="!taskFuModal.todo?.follow_ups?.length" class="fu-empty">No follow-ups logged yet.</div>
          <table v-else class="data-table" style="margin-bottom:16px">
            <thead><tr><th>Date</th><th>Action</th><th>Note</th></tr></thead>
            <tbody>
              <tr v-for="fu in taskFuModal.todo.follow_ups" :key="fu.id">
                <td class="date-cell">{{ fmtDate(fu.followup_date) }}</td>
                <td><span v-if="fu.action_type" class="fu-action-badge">{{ fu.action_type }}</span><span v-else class="muted">—</span></td>
                <td style="font-size:12.5px;white-space:pre-line">{{ fu.note || '—' }}</td>
              </tr>
            </tbody>
          </table>
          <div class="fu-divider"></div>
          <div class="fu-section-lbl">Log New Follow-Up</div>
          <form @submit.prevent="submitTaskFu">
            <div v-if="taskFuModal.error" class="fu-error">{{ taskFuModal.error }}</div>
            <div class="fu-form-row">
              <div class="fu-field">
                <label>Date <span class="req">*</span></label>
                <input type="date" v-model="taskFuForm.followup_date" required>
              </div>
              <div class="fu-field">
                <label>Action Type</label>
                <select v-model="taskFuForm.action_type">
                  <option value="">— Select type —</option>
                  <option v-for="at in ACTION_TYPES" :key="at" :value="at">{{ at }}</option>
                </select>
              </div>
            </div>
            <div class="fu-field">
              <label>Note</label>
              <textarea v-model="taskFuForm.note" rows="3" placeholder="What happened? Outcome, next steps…"></textarea>
            </div>
            <div class="fu-form-actions">
              <button type="button" class="btn btn-outline" @click="closeTaskFuModal">Cancel</button>
              <button type="submit" class="btn btn-fu-save" :disabled="!taskFuForm.followup_date || taskFuModal.saving">
                {{ taskFuModal.saving ? 'Saving…' : 'Log Follow-Up' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="showDeleteModal" class="modal-overlay">
      <div class="modal-box delete-modal">
        <div class="modal-head modal-head-danger">
          <strong class="modal-head-title">Delete Company</strong>
          <button class="modal-close-btn" @click="closeDeleteModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body">
          <p class="delete-text">
            This will permanently delete <strong>{{ contact?.name }}</strong> and all associated tasks, forecasts, and history. This cannot be undone.
          </p>
          <div v-if="deleteError" class="delete-error">{{ deleteError }}</div>
          <p class="delete-hint">Type <code>confirm delete</code> to proceed:</p>
          <input v-model="deleteConfirmText" class="delete-input" placeholder="confirm delete"
            @keyup.enter="deleteConfirmText === 'confirm delete' && confirmDelete()">
          <div class="modal-actions">
            <button class="btn btn-outline" @click="closeDeleteModal">Cancel</button>
            <button class="btn btn-danger"
              :disabled="deleteConfirmText !== 'confirm delete' || deleting"
              @click="confirmDelete">
              {{ deleting ? 'Deleting…' : 'Delete Permanently' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <ForecastFormModal
      :open="forecastModal.open"
      :mode="forecastModal.mode"
      :forecast-id="forecastModal.forecastId"
      :prefilled-contact="forecastModal.prefilledContact"
      @close="closeForecastModal"
      @saved="onForecastSaved"
    />

  <Teleport to="body">
    <div v-if="closedModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Mark as Permanently Closed</p>
            <p class="conf-sub">This contact will be flagged as a closed business.</p>
          </div>
          <button class="conf-close" @click="closedModal.open = false">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Flag <strong>{{ contact?.name }}</strong> as permanently closed? You can undo this at any time.</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closedModal.open = false">Cancel</button>
          <button class="conf-delete" :disabled="closedModal.loading" @click="toggleClosed">
            {{ closedModal.loading ? 'Saving…' : 'Mark as Closed' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="deleteTaskModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete To-Do</p>
            <p class="conf-sub">All linked follow-ups will also be removed.</p>
          </div>
          <button class="conf-close" @click="closeDeleteTaskModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete <strong>{{ deleteTaskModal.todo?.task?.name ?? 'this task' }}</strong> on {{ fmtDate(deleteTaskModal.todo?.todo_date) }}?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteTaskModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteTaskModal.loading" @click="confirmDeleteTask">
            {{ deleteTaskModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="deleteForecastModal.open" class="conf-overlay">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete Forecast</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeDeleteForecastModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete this forecast entry?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteForecastModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteForecastModal.loading" @click="confirmDeleteForecast">
            {{ deleteForecastModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import ForecastFormModal from '../components/ForecastFormModal.vue';
import { usePermissions } from '../composables/usePermissions.js';

const { can } = usePermissions();

const route  = useRoute();
const router = useRouter();
const id     = route.params.id;

const loading = ref(true);
const contact = ref(null);

const isAdmin = computed(() => {
  const roles = JSON.parse(localStorage.getItem('crm_user') || 'null')?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

const totalFollowUps = computed(() =>
  (contact.value?.todos ?? []).reduce((sum, t) => sum + (t.follow_ups?.length ?? 0), 0)
);

// ── Activity calendar ──
const MONTH_NAMES = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
const actYear  = ref(new Date().getFullYear());
const actYears = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

const monthMap = computed(() => {
  const todos = contact.value?.todos;
  if (!todos) return {};
  const map = {};
  for (const t of todos) {
    const d = new Date(t.todo_date);
    if (d.getFullYear() !== actYear.value) continue;
    const m = d.getMonth() + 1;
    if (!map[m]) map[m] = { date: fmtDate(t.todo_date), task: t.task?.name ?? '—' };
  }
  return map;
});

// ── Lookups (for Add Task form) ──
const lookups = ref({ tasks: [] });

// ── Follow-up modal ──
const ACTION_TYPES = ['Call', 'Email', 'Meeting', 'Site Visit', 'Presentation', 'Proposal', 'Demo', 'Contract', 'Other'];
const taskFuModal = ref({ open: false, todo: null, saving: false, error: '' });
const taskFuForm  = ref({ followup_date: '', action_type: '', note: '' });

function openTaskFuModal(todo) {
  taskFuForm.value  = { followup_date: new Date().toISOString().slice(0, 10), action_type: '', note: '' };
  taskFuModal.value = { open: true, todo, saving: false, error: '' };
}
function closeTaskFuModal() { taskFuModal.value.open = false; }

async function submitTaskFu() {
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
    const res = await api.get(`/v1/contacts/${id}`);
    contact.value = res.data.data;
    const updated = contact.value.todos?.find(t => t.id === taskFuModal.value.todo.id);
    if (updated) taskFuModal.value.todo = updated;
    taskFuForm.value = { followup_date: new Date().toISOString().slice(0, 10), action_type: '', note: '' };
  } catch (e) {
    taskFuModal.value.error = e.response?.data?.message ?? 'Failed to save follow-up.';
  } finally {
    taskFuModal.value.saving = false;
  }
}

// ── Add Task ──
const addTaskOpen        = ref(false);
const addTaskSaving      = ref(false);
const addTaskError       = ref('');
const addTaskForm        = ref({ task_id: '', todo_date: '', todo_remark: '' });

function openAddTaskPanel() {
  addTaskForm.value = { task_id: '', todo_date: new Date().toISOString().slice(0, 10), todo_remark: '' };
  addTaskError.value = '';
  addTaskOpen.value  = true;
}

async function submitAddTask() {
  if (!addTaskForm.value.todo_date || addTaskSaving.value) return;
  addTaskSaving.value = true;
  addTaskError.value  = '';
  try {
    await api.post(`/v1/contacts/${id}/todos`, {
      task_id:     addTaskForm.value.task_id    || null,
      todo_date:   addTaskForm.value.todo_date,
      todo_remark: addTaskForm.value.todo_remark || null,
    });
    addTaskOpen.value = false;
    const res = await api.get(`/v1/contacts/${id}`);
    contact.value = res.data.data;
  } catch (e) {
    addTaskError.value = e.response?.data?.message ?? 'Failed to save task.';
  } finally {
    addTaskSaving.value = false;
  }
}

async function toggleDone(todo, status) {
  await api.patch(`/v1/todos/${todo.id}/status`, { status });
  todo.completion_status = status;
}

const deleteTaskModal = reactive({ open: false, todo: null, loading: false });
function openDeleteTaskModal(todo) { deleteTaskModal.todo = todo; deleteTaskModal.open = true; }
function closeDeleteTaskModal() { deleteTaskModal.open = false; deleteTaskModal.todo = null; deleteTaskModal.loading = false; }

async function confirmDeleteTask() {
  if (!deleteTaskModal.todo) return;
  deleteTaskModal.loading = true;
  try {
    await api.delete(`/v1/contacts/${id}/todos/${deleteTaskModal.todo.id}`);
    const res = await api.get(`/v1/contacts/${id}`);
    contact.value = res.data.data;
    closeDeleteTaskModal();
  } catch {
    closeDeleteTaskModal();
  } finally {
    deleteTaskModal.loading = false;
  }
}

// ── Forecast ──
const forecastModal = ref({ open: false, mode: 'add', forecastId: null, prefilledContact: null });

function openForecastAdd() {
  forecastModal.value = { open: true, mode: 'add', forecastId: null,
    prefilledContact: contact.value ? { id: contact.value.id, name: contact.value.name } : null };
}
function openForecastEdit(fid) {
  forecastModal.value = { open: true, mode: 'edit', forecastId: fid, prefilledContact: null };
}
function closeForecastModal() { forecastModal.value.open = false; }
async function onForecastSaved() {
  closeForecastModal();
  const res = await api.get(`/v1/contacts/${id}`);
  contact.value = res.data.data;
}
const deleteForecastModal = reactive({ open: false, forecastId: null, loading: false });
function openDeleteForecastModal(fid) { deleteForecastModal.forecastId = fid; deleteForecastModal.open = true; }
function closeDeleteForecastModal() { deleteForecastModal.open = false; deleteForecastModal.forecastId = null; deleteForecastModal.loading = false; }

async function confirmDeleteForecast() {
  if (!deleteForecastModal.forecastId) return;
  deleteForecastModal.loading = true;
  try {
    await api.delete(`/v1/forecasts/${deleteForecastModal.forecastId}`);
    contact.value.forecasts = contact.value.forecasts.filter(f => f.id !== deleteForecastModal.forecastId);
    closeDeleteForecastModal();
  } catch {
    closeDeleteForecastModal();
  } finally {
    deleteForecastModal.loading = false;
  }
}

// ── Permanently Closed ──
const closedModal = reactive({ open: false, loading: false });

function openClosedModal() { closedModal.open = true; }

async function toggleClosed() {
  closedModal.loading = true;
  try {
    const res = await api.patch(`/v1/contacts/${id}/closed`);
    contact.value.is_permanently_closed = res.data.is_permanently_closed;
    closedModal.open = false;
  } finally {
    closedModal.loading = false;
  }
}

function mapsUrl(c) {
  const q = encodeURIComponent([c.name, c.address].filter(Boolean).join(', '));
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
}

// ── Delete ──
const showDeleteModal  = ref(false);
const deleteConfirmText = ref('');
const deleting          = ref(false);
const deleteError       = ref('');

function openDeleteModal()  { deleteConfirmText.value = ''; deleteError.value = ''; showDeleteModal.value = true; }
function closeDeleteModal() { showDeleteModal.value = false; deleteConfirmText.value = ''; deleteError.value = ''; }

async function confirmDelete() {
  if (deleteConfirmText.value !== 'confirm delete') return;
  deleting.value = true;
  deleteError.value = '';
  try {
    await api.delete(`/v1/contacts/${id}`);
    router.push('/list');
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Failed to delete contact.';
    deleting.value = false;
  }
}

// ── Helpers ──
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
function resultClass(name) {
  return `result-${(name ?? 'No Result').toLowerCase().replace(/\s+/g, '-')}`;
}
const SOURCE_LABELS = {
  manual: 'Manual Entry', web_form: 'Web Form', phone_call: 'Phone Call',
  referral: 'Referral', walk_in: 'Walk-in', social_media: 'Social Media',
  email_campaign: 'Email Campaign', other: 'Other',
};
function sourceLabel(src) { return SOURCE_LABELS[src] ?? src; }

// ── Email & Call logs ──────────────────────────────────────────────────────
const emails = ref([]);
const calls  = ref([]);
const canLog = computed(() => can('edit contacts') && !!contact.value?.can_edit);
const todayStr = () => new Date().toISOString().slice(0, 10);

function logErr(e) {
  const errs = e.response?.data?.errors;
  return errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message ?? 'Could not save. Please try again.');
}

const emailFormOpen = ref(false);
const emailSaving   = ref(false);
const emailError    = ref('');
const emailForm     = ref({ direction: 'sent', subject: '', body: '', emailed_at: todayStr() });

function toggleEmailForm() {
  emailFormOpen.value = !emailFormOpen.value;
  if (emailFormOpen.value) {
    emailForm.value = { direction: 'sent', subject: '', body: '', emailed_at: todayStr() };
    emailError.value = '';
  }
}
async function submitEmail() {
  if (!emailForm.value.subject.trim()) return;
  emailSaving.value = true; emailError.value = '';
  try {
    const res = await api.post(`/v1/contacts/${id}/emails`, {
      direction:  emailForm.value.direction,
      subject:    emailForm.value.subject.trim(),
      body:       emailForm.value.body || null,
      emailed_at: emailForm.value.emailed_at,
    });
    emails.value.unshift({ ...res.data.data, _confirmDel: false });
    emailFormOpen.value = false;
  } catch (e) { emailError.value = logErr(e); }
  finally { emailSaving.value = false; }
}
async function deleteEmail(em) {
  try {
    await api.delete(`/v1/contacts/${id}/emails/${em.id}`);
    emails.value = emails.value.filter(x => x.id !== em.id);
  } catch (e) { emailError.value = logErr(e); em._confirmDel = false; }
}

const callFormOpen = ref(false);
const callSaving   = ref(false);
const callError    = ref('');
const callForm     = ref({ direction: 'outbound', duration: '', notes: '', called_at: todayStr() });

function toggleCallForm() {
  callFormOpen.value = !callFormOpen.value;
  if (callFormOpen.value) {
    callForm.value = { direction: 'outbound', duration: '', notes: '', called_at: todayStr() };
    callError.value = '';
  }
}
async function submitCall() {
  callSaving.value = true; callError.value = '';
  try {
    const res = await api.post(`/v1/contacts/${id}/calls`, {
      direction: callForm.value.direction,
      duration:  callForm.value.duration ? Number(callForm.value.duration) : null,
      notes:     callForm.value.notes || null,
      called_at: callForm.value.called_at,
    });
    calls.value.unshift({ ...res.data.data, _confirmDel: false });
    callFormOpen.value = false;
  } catch (e) { callError.value = logErr(e); }
  finally { callSaving.value = false; }
}
async function deleteCall(c) {
  try {
    await api.delete(`/v1/contacts/${id}/calls/${c.id}`);
    calls.value = calls.value.filter(x => x.id !== c.id);
  } catch (e) { callError.value = logErr(e); c._confirmDel = false; }
}

onMounted(async () => {
  try {
    const [contactRes, lookupRes] = await Promise.all([
      api.get(`/v1/contacts/${id}`),
      api.get('/v1/lookups'),
    ]);
    contact.value = contactRes.data.data;
    lookups.value = { tasks: lookupRes.data.tasks ?? [] };
  } finally {
    loading.value = false;
  }

  // Communication logs are non-critical — load after, don't block the page if they fail
  try {
    const [emailRes, callRes] = await Promise.all([
      api.get(`/v1/contacts/${id}/emails`),
      api.get(`/v1/contacts/${id}/calls`),
    ]);
    emails.value = (emailRes.data.data ?? []).map(e => ({ ...e, _confirmDel: false }));
    calls.value  = (callRes.data.data ?? []).map(c => ({ ...c, _confirmDel: false }));
  } catch (_) { /* logs unavailable — leave empty */ }
});
</script>

<style scoped>
.page { max-width: 1100px; margin: 0 auto; padding: 28px 28px 60px; }

/* Header */
.page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; flex-wrap: wrap; gap: 12px; }
.back-btn {
  font-size: 13px; font-weight: 600; color: var(--text-2); text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
  border-radius: 999px; background: var(--surface); border: 1px solid var(--border-soft);
  transition: color 0.15s, background 0.15s;
}
.back-btn:hover { color: var(--primary-text); background: var(--primary-soft); }
.head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* Buttons */
.btn {
  height: 38px; padding: 0 18px; border-radius: 999px; font-size: 13px; font-weight: 700;
  cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center;
  transition: background 0.15s, color 0.15s, transform 0.06s;
}
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn-sm { height: 32px; padding: 0 14px; font-size: 12px; }
.btn-primary  { background: var(--primary);      color: var(--primary-on); }
.btn-primary:hover  { background: var(--primary-hover); }
.btn-outline  { background: var(--surface);      color: var(--text-2); border: 1px solid var(--border); }
.btn-outline:hover  { background: var(--surface-2); }
.btn-warn     { background: var(--warning-soft); color: var(--warning); }
.btn-warn:hover     { background: var(--warning);      color: #fff; }
.btn-info     { background: var(--info-soft);    color: var(--info); }
.btn-info:hover     { background: var(--info);         color: #fff; }
.btn-success  { background: var(--success-soft); color: var(--success); }
.btn-success:hover  { background: var(--success);      color: #fff; }
.btn-danger   { background: var(--danger);       color: #fff; }
.btn-danger:hover   { background: #dc2626; }
.btn-danger:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-fu-save  { background: #e11d48; color: #fff; flex: 1; justify-content: center; }
.btn-fu-save:hover:not(:disabled)  { background: #be123c; }
.btn-fu-save:disabled { background: #94a3b8; cursor: not-allowed; }
.req { color: var(--danger); }

/* Profile banner */
.profile-banner {
  background: var(--surface);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg);
  padding: 24px 28px;
  margin-bottom: 22px;
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  box-shadow: var(--shadow-xs);
}
.profile-avatar {
  width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
  background: var(--primary-soft); color: var(--primary-text);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 800;
}
.profile-info { flex: 1; min-width: 200px; }
.profile-name { font-size: 26px; font-weight: 800; color: var(--text-1); margin: 0 0 10px; letter-spacing: -0.5px; }
.profile-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.meta-pill {
  display: inline-flex; align-items: center; padding: 4px 12px;
  border-radius: 999px; font-size: 11.5px; font-weight: 600;
  background: var(--surface-2); color: var(--text-2);
}
.pill-status   { background: var(--success-soft); color: var(--success); }
.pill-type     { background: var(--info-soft);    color: var(--info); }
.pill-industry { background: var(--warning-soft); color: var(--warning); }
.pill-category { background: var(--primary-soft); color: var(--primary-text); }
.profile-stats { display: flex; gap: 24px; flex-shrink: 0; }
.pstat { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.pstat-num { font-size: 24px; font-weight: 800; color: var(--text-1); line-height: 1; }
.pstat-lbl { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }

/* Two-column */
.two-col { display: grid; grid-template-columns: 1fr 340px; gap: 20px; margin-bottom: 20px; }

/* Cards */
.card {
  background: var(--surface); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft);
  padding: 22px 24px; margin-bottom: 20px;
}
.two-col .card { margin-bottom: 0; }
.two-col > div > .card + .card { margin-top: 20px; }
.card-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
  color: var(--text-3); margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--border-soft);
}
.card-title-row {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--border-soft);
}
.card-title-row .card-title { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

/* Detail grid */
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
.detail-item { display: flex; flex-direction: column; gap: 3px; }
.detail-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.detail-value { font-size: 14px; color: var(--text-1); font-weight: 500; }
.detail-muted { font-size: 14px; color: var(--text-3); font-style: italic; }
.detail-full  { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-soft); }
.pre-line { white-space: pre-line; }
.email-link { color: var(--primary-text); text-decoration: none; font-size: 13px; }
.email-link:hover { text-decoration: underline; }
.muted { color: var(--text-3); font-size: 13px; }
.not-found { padding: 80px; text-align: center; color: var(--text-3); font-size: 15px; }

/* Source badge */
.source-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
.source-manual         { background: var(--surface-2); color: var(--text-2); }
.source-web_form       { background: var(--info-soft);    color: var(--info); }
.source-phone_call     { background: #f5f3ff; color: #1d4ed8; }
.source-referral       { background: var(--warning-soft); color: var(--warning); }
.source-walk_in        { background: #f0fdfa; color: #0f766e; }
.source-social_media   { background: #fdf2f8; color: #be185d; }
.source-email_campaign { background: #fff7ed; color: #c2410c; }
.source-other          { background: var(--surface-2); color: var(--text-2); }

/* Activity calendar */
.year-sel {
  height: 30px; padding: 0 12px; border: 1px solid var(--border); border-radius: 999px;
  font-size: 12.5px; font-weight: 600; background: var(--surface); color: var(--text-1);
  outline: none; cursor: pointer;
}
.year-sel:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.month-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.month-cell {
  padding: 8px 6px; border-radius: 10px; background: var(--surface-2);
  border: 1px solid var(--border-soft); text-align: center;
  min-height: 56px; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 2px;
}
.month-cell.month-active { background: var(--success-soft); border-color: var(--success-soft); }
.month-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.month-cell.month-active .month-label { color: var(--success); }
.month-date  { font-size: 10px; font-weight: 700; color: var(--success); }
.month-task  { font-size: 9px; color: var(--success); opacity: 0.85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.month-empty { font-size: 13px; color: var(--text-3); opacity: 0.5; }

/* Data table */
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 11px 14px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap;
}
.data-table thead th:last-child { border-right: none; }
.data-table tbody td { padding: 13px 14px; border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; font-size: 13.5px; }
.data-table tbody td:last-child { border-right: none; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--surface-2); }
.date-cell { font-size: 12px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.remark-cell { font-size: 12px; white-space: pre-line; color: var(--text-1); max-width: 200px; }
.amount-cell { font-weight: 800; color: var(--info); white-space: nowrap; }
.amount-col  { text-align: right; }
.empty-text { font-size: 13px; color: var(--text-3); font-style: italic; margin: 4px 0 0; }

/* Task badge */
.task-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--primary-soft); color: var(--primary-text); }

/* Status pill */
.status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
.status-pending   { background: var(--warning-soft); color: var(--warning); }
.status-completed { background: var(--success-soft); color: var(--success); }
.status-cancelled { background: var(--surface-2);    color: var(--text-3); }

/* Done/undo buttons */
.row-done td { opacity: 0.5; text-decoration: line-through; text-decoration-color: var(--text-3); }
.row-done .done-btn, .row-done .undo-btn, .row-done .fu-pill, .row-done .task-del-btn { text-decoration: none; opacity: 1; }
.task-action-cell { display: inline-flex; align-items: center; gap: 4px; justify-content: center; }
.task-del-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 8px; border: none;
  background: var(--danger-soft); color: var(--danger); font-size: 11px;
  cursor: pointer; transition: background 0.15s, color 0.15s;
}
.task-del-btn:hover { background: var(--danger); color: #fff; }
.done-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 8px; background: var(--success-soft);
  color: var(--success); font-weight: 700; border: none; cursor: pointer; transition: background 0.15s, color 0.15s;
}
.done-btn:hover { background: var(--success); color: #fff; }
.undo-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 8px; background: var(--surface-2);
  color: var(--text-2); font-weight: 700; border: none; cursor: pointer; transition: background 0.15s, color 0.15s;
}
.undo-btn:hover { background: var(--text-2); color: var(--surface); }

/* Follow-up pill */
.fu-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 10px;
  border: 1.5px solid #fce7f3; background: #fce7f3; color: #9d174d;
  cursor: pointer; white-space: nowrap; transition: background 0.15s, color 0.15s;
}
.fu-pill.fu-pill-has { border-color: #fb7185; background: #ffe4e6; color: #be123c; }
.fu-pill:hover { background: #e11d48; border-color: #e11d48; color: #fff; }

/* Result badge */
.result-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.result-confirmed  { background: var(--success-soft); color: var(--success); }
.result-pending    { background: var(--warning-soft); color: var(--warning); }
.result-rejected   { background: var(--danger-soft);  color: var(--danger); }
.result-no-result  { background: var(--surface-2);    color: var(--text-2); }

/* Forecast action buttons */
.action-pair { display: flex; gap: 4px; }
.inline-btn { height: 26px; padding: 0 10px; border-radius: 6px; border: none; font-size: 11px; font-weight: 700; cursor: pointer; }
.edit-btn { background: var(--warning-soft); color: var(--warning); }
.edit-btn:hover { background: var(--warning); color: #fff; }
.del-btn  { background: var(--danger-soft);  color: var(--danger); }
.del-btn:hover  { background: var(--danger);  color: #fff; }

/* ── Communication logs (Email / Call) ── */
.seg { display: inline-flex; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg button { padding: 8px 16px; font-size: 12.5px; font-weight: 600; border: none; background: var(--surface); color: var(--text-2); cursor: pointer; transition: background 0.15s, color 0.15s; }
.seg button + button { border-left: 1.5px solid var(--border); }
.seg button.on { background: var(--primary); color: var(--primary-on); }

.dir-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.dir-out { background: var(--info-soft); color: var(--info); }
.dir-in  { background: var(--success-soft); color: var(--success); }

.log-subject { font-size: 13px; color: var(--text-1); font-weight: 500; }
.log-body { font-size: 11.5px; color: var(--text-3); margin-top: 2px; white-space: pre-line; }

.log-act-col { width: 130px; text-align: right; white-space: nowrap; }
.confirm-btn { background: var(--danger); color: #fff; margin-right: 4px; }
.confirm-btn:hover { background: #b91c1c; }
.cancel-btn { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.cancel-btn:hover { background: var(--border); color: var(--text-1); }

/* Inline Add Task form */
.inline-form {
  background: var(--surface-2); border: 1px solid var(--border-soft);
  border-radius: var(--radius); padding: 16px 18px; margin-bottom: 18px;
}
.if-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.if-field { display: flex; flex-direction: column; gap: 5px; }
.if-field label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); }
.if-field select, .if-field input, .if-field textarea {
  border: 1px solid var(--border); border-radius: 999px; font-size: 13px;
  padding: 0 14px; height: 36px; background: var(--surface); color: var(--text-1); outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.if-field textarea { height: 60px; padding: 8px 14px; resize: none; border-radius: var(--radius); }
.if-field select:focus, .if-field input:focus, .if-field textarea:focus {
  border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring);
}
.if-error { font-size: 12.5px; color: var(--danger); background: var(--danger-soft); border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; }
.if-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px; }

/* Modals */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
  z-index: 700; display: flex; align-items: center; justify-content: center; padding: 16px;
}
.modal-box {
  background: var(--surface); border-radius: var(--radius-xl); width: 580px; max-width: 95vw;
  max-height: 92vh; display: flex; flex-direction: column;
  box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden;
}
.task-fu-modal { width: 620px; }
.delete-modal  { width: 460px; }
.modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 18px 24px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0;
}
.modal-head-danger { background: var(--danger-soft); }
.modal-head-left { display: flex; align-items: center; gap: 12px; }
.modal-head-title { font-size: 16px; font-weight: 800; color: var(--text-1); }
.modal-head-danger .modal-head-title { color: var(--danger); }
.task-chip {
  background: var(--primary-soft); color: var(--primary-text);
  border-radius: 999px; padding: 4px 12px; font-size: 11.5px; font-weight: 700;
}
.modal-close-btn {
  background: var(--surface-2); border: none; cursor: pointer; font-size: 14px; color: var(--text-2);
  width: 30px; height: 30px; border-radius: 50%; display: inline-flex;
  align-items: center; justify-content: center; transition: background 0.15s, color 0.15s;
}
.modal-close-btn:hover { background: var(--danger-soft); color: var(--danger); }
.modal-body { padding: 20px 24px; overflow-y: auto; }

/* Follow-up modal internals */
.fu-remark-box {
  background: var(--surface-2); border-radius: var(--radius); padding: 10px 14px;
  font-size: 12.5px; color: var(--text-1); margin-bottom: 14px; white-space: pre-line;
}
.fu-section-lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-3); margin-bottom: 10px; }
.fu-empty { font-size: 13px; color: var(--text-3); font-style: italic; margin-bottom: 14px; }
.fu-divider { border-top: 1px solid var(--border-soft); margin: 14px 0; }
.fu-error { font-size: 13px; color: var(--danger); background: var(--danger-soft); border-radius: var(--radius); padding: 10px 14px; margin-bottom: 12px; }
.fu-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.fu-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.fu-field label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); }
.fu-field input, .fu-field select, .fu-field textarea {
  width: 100%; height: 40px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box; transition: border-color 0.15s, box-shadow 0.15s;
}
.fu-field textarea { height: 80px; padding: 10px 14px; resize: none; border-radius: var(--radius); }
.fu-field input:focus, .fu-field select:focus, .fu-field textarea:focus {
  border-color: #e11d48; box-shadow: 0 0 0 3px rgba(225,29,72,0.12);
}
.fu-action-badge { background: #fce7f3; color: #9d174d; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
.fu-form-actions { display: flex; gap: 10px; margin-top: 4px; justify-content: flex-end; }

/* Delete modal */
.delete-text { font-size: 13.5px; color: var(--text-1); line-height: 1.6; margin-bottom: 14px; }
.delete-hint { font-size: 12.5px; color: var(--text-2); margin-bottom: 8px; }
.delete-hint code { background: var(--danger-soft); color: var(--danger); padding: 2px 6px; border-radius: 4px; font-weight: 700; }
.delete-input {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none; background: var(--surface);
  box-sizing: border-box; margin-bottom: 18px; transition: border-color 0.15s, box-shadow 0.15s;
}
.delete-input:focus { border-color: var(--danger); box-shadow: 0 0 0 3px var(--danger-soft); }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

/* Responsive */
@media (max-width: 900px) {
  .two-col { grid-template-columns: 1fr; }
  .month-grid { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 768px) {
  .page { padding: 16px 14px 40px; }
  .profile-banner { flex-direction: column; align-items: flex-start; }
  .profile-stats { width: 100%; justify-content: space-around; }
  .if-row { grid-template-columns: 1fr; }
  .fu-form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 12px 10px 32px; }
  .month-grid { grid-template-columns: repeat(4, 1fr); }
  .detail-grid { grid-template-columns: 1fr; }
}

.delete-error { background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm); padding: 8px 12px; font-size: 13px; font-weight: 600; margin-bottom: 10px; }

/* Permanently closed */
.closed-banner {
  display: flex; align-items: center; gap: 10px;
  background: var(--danger-soft); color: var(--danger);
  border: 1px solid var(--danger); border-radius: var(--radius);
  padding: 12px 18px; font-size: 13.5px; font-weight: 500;
  margin-bottom: 18px;
}
.closed-banner svg { flex-shrink: 0; }
.btn-closed { background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger); }
.btn-closed:hover { background: var(--danger); color: #fff; }
.detail-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px; }
.maps-link {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700; color: var(--primary-text);
  text-decoration: none; padding: 3px 10px; border-radius: 999px;
  background: var(--primary-soft); transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}
.maps-link:hover { background: var(--primary); color: var(--primary-on); }

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
</style>
