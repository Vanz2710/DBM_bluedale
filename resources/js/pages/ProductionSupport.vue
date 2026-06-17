<template>
  <div class="ps-wrap">
    <!-- Left Sub-Sidebar -->
    <aside class="ps-sidebar">
      <div class="ps-sidebar-header">
        <div class="ps-logo">
          <span class="ps-logo-icon">🏗️</span>
          <div class="ps-logo-text">
            <span class="ps-logo-title">Production</span>
            <span class="ps-logo-sub">Support Tracker</span>
          </div>
        </div>
        <button class="ps-new-btn" @click="openNewJobModal">
          <span>+</span> New Job
        </button>
      </div>

      <nav class="ps-nav">
        <button class="ps-nav-item" :class="{ active: currentView === 'dashboard' }" @click="currentView = 'dashboard'">
          <span class="ps-nav-icon">📊</span><span>Dashboard</span>
        </button>
        <button class="ps-nav-item" :class="{ active: currentView === 'table' }" @click="currentView = 'table'">
          <span class="ps-nav-icon">☰</span><span>Table View</span>
        </button>
        <button class="ps-nav-item" :class="{ active: currentView === 'board' }" @click="currentView = 'board'">
          <span class="ps-nav-icon">⬛</span><span>Board View</span>
        </button>
        <button class="ps-nav-item" :class="{ active: currentView === 'calendar' }" @click="currentView = 'calendar'">
          <span class="ps-nav-icon">📅</span><span>Calendar</span>
        </button>
      </nav>

      <div class="ps-sidebar-footer">
        <div class="ps-stage-legend">
          <div class="ps-legend-title">Stages</div>
          <div v-for="s in stages" :key="s.key" class="ps-legend-item">
            <span class="ps-legend-dot" :style="{ background: s.color }"></span>
            <span>{{ s.label }}</span>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="ps-main" :class="{ 'panel-open': !!selectedJob }">

      <!-- ── DASHBOARD ── -->
      <div v-show="currentView === 'dashboard'" class="ps-page">
        <div class="ps-page-header">
          <h1 class="ps-page-title">Production Overview</h1>
          <span class="ps-page-date">{{ todayLabel }}</span>
        </div>

        <div class="ps-stat-grid">
          <div class="ps-stat-card" style="--accent:#6366f1">
            <div class="ps-stat-icon">📋</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.total_active ?? '–' }}</div>
              <div class="ps-stat-label">Total Active Jobs</div>
            </div>
          </div>
          <div class="ps-stat-card" style="--accent:#3b82f6">
            <div class="ps-stat-icon">📝</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.pending_apps ?? '–' }}</div>
              <div class="ps-stat-label">Pending Applications</div>
            </div>
          </div>
          <div class="ps-stat-card" style="--accent:#f59e0b">
            <div class="ps-stat-icon">💳</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.pending_payments ?? '–' }}</div>
              <div class="ps-stat-label">Pending Payments</div>
            </div>
          </div>
          <div class="ps-stat-card" style="--accent:#06b6d4">
            <div class="ps-stat-icon">🔧</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.awaiting_install ?? '–' }}</div>
              <div class="ps-stat-label">Awaiting Installation</div>
            </div>
          </div>
          <div class="ps-stat-card" style="--accent:#ef4444">
            <div class="ps-stat-icon">⚠️</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.open_complaints ?? '–' }}</div>
              <div class="ps-stat-label">Open Complaints</div>
            </div>
          </div>
          <div class="ps-stat-card" style="--accent:#10b981">
            <div class="ps-stat-icon">✅</div>
            <div class="ps-stat-body">
              <div class="ps-stat-value">{{ stats.completed_month ?? '–' }}</div>
              <div class="ps-stat-label">Completed This Month</div>
            </div>
          </div>
        </div>

        <!-- Stage mini-board -->
        <div class="ps-dash-section">
          <h3 class="ps-section-title">Jobs by Stage</h3>
          <div class="ps-stage-bars">
            <div v-for="s in stages" :key="s.key" class="ps-stage-bar-item">
              <div class="ps-stage-bar-label">
                <span class="ps-stage-dot" :style="{ background: s.color }"></span>
                {{ s.label }}
              </div>
              <div class="ps-stage-bar-track">
                <div class="ps-stage-bar-fill" :style="{ width: stageBarWidth(s.key) + '%', background: s.color }"></div>
              </div>
              <div class="ps-stage-bar-count">{{ byStage[s.key] ?? 0 }}</div>
            </div>
          </div>
        </div>

        <!-- Recent Jobs -->
        <div class="ps-dash-section">
          <h3 class="ps-section-title">Recent Jobs</h3>
          <div class="ps-recent-table">
            <div v-if="isLoading" class="ps-loading">Loading…</div>
            <table v-else>
              <thead>
                <tr>
                  <th>Job #</th><th>Client</th><th>Type</th><th>Stage</th><th>Submission Date</th><th>Due Date</th><th>Payment Due Date</th><th>Installation Date</th><th>Dismantle Completion</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="job in recentJobs" :key="job.id" class="ps-table-row" @click="openJob(job.id)">
                  <td class="ps-job-num">{{ job.job_number }}</td>
                  <td class="ps-client">{{ job.client_name }}</td>
                  <td><span class="ps-type-badge">{{ job.product_type }}</span></td>
                  <td><span class="ps-stage-chip" :style="stageChipStyle(job.current_stage)">{{ stageLabel(job.current_stage) }}</span></td>
                  <td>{{ formatDate(job.submission_date) || '–' }}</td>
                  <td :class="{ 'ps-overdue': job.is_overdue }">{{ formatDate(job.due_date) }}</td>
                  <td>{{ formatDate(job.payment_due_date) || '–' }}</td>
                  <td>{{ formatDate(job.install_date) || '–' }}</td>
                  <td>{{ formatDate(job.dismantle_completion_date) || '–' }}</td>
                </tr>
                <tr v-if="!recentJobs.length">
                  <td colspan="9" class="ps-empty">No jobs yet. Create one to get started.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── TABLE VIEW ── -->
      <div v-show="currentView === 'table'" class="ps-page">
        <div class="ps-page-header">
          <h1 class="ps-page-title">All Jobs</h1>
          <button class="ps-btn ps-btn-primary" @click="openNewJobModal">+ New Job</button>
        </div>

        <!-- Filters -->
        <div class="ps-filters">
          <input v-model="searchQuery" class="ps-search" placeholder="Search client, job #, location…" @input="debouncedLoad" />
          <select v-model="filterStage" @change="loadJobs" class="ps-select">
            <option value="">All Stages</option>
            <option v-for="s in stages" :key="s.key" :value="s.key">{{ s.label }}</option>
          </select>
          <select v-model="filterType" @change="loadJobs" class="ps-select">
            <option value="">All Types</option>
            <option v-for="t in productTypes" :key="t" :value="t">{{ t }}</option>
          </select>
          <select v-model="filterStatus" @change="loadJobs" class="ps-select">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- Table -->
        <div class="ps-table-wrap">
          <div v-if="isLoading" class="ps-loading">Loading…</div>
          <table v-else class="ps-notion-table">
            <thead>
              <tr>
                <th @click="setSort('job_number')" class="ps-th-sort">Job # <span class="ps-sort-arrow">{{ sortArrow('job_number') }}</span></th>
                <th @click="setSort('client_name')" class="ps-th-sort">Client <span class="ps-sort-arrow">{{ sortArrow('client_name') }}</span></th>
                <th>Type</th>
                <th>Location</th>
                <th @click="setSort('current_stage')" class="ps-th-sort">Stage <span class="ps-sort-arrow">{{ sortArrow('current_stage') }}</span></th>
                <th>PIC</th>
                <th @click="setSort('due_date')" class="ps-th-sort">Due Date <span class="ps-sort-arrow">{{ sortArrow('due_date') }}</span></th>
                <th @click="setSort('request_date')" class="ps-th-sort">Req Date <span class="ps-sort-arrow">{{ sortArrow('request_date') }}</span></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="job in jobs" :key="job.id" class="ps-table-row" @click="openJob(job.id)">
                <td class="ps-job-num">{{ job.job_number }}</td>
                <td class="ps-client-bold">{{ job.client_name }}</td>
                <td><span class="ps-type-badge">{{ job.product_type }}</span></td>
                <td class="ps-location">{{ job.location || '–' }}</td>
                <td><span class="ps-stage-chip" :style="stageChipStyle(job.current_stage)">{{ stageLabel(job.current_stage) }}</span></td>
                <td>{{ job.pic || '–' }}</td>
                <td :class="{ 'ps-overdue': job.is_overdue }">{{ formatDate(job.due_date) }}</td>
                <td>{{ formatDate(job.request_date) }}</td>
                <td class="ps-actions" @click.stop>
                  <button class="ps-action-btn" @click="openJob(job.id)" title="View">👁</button>
                  <button class="ps-action-btn ps-danger" @click="deleteJob(job.id)" title="Delete">🗑</button>
                </td>
              </tr>
              <tr v-if="!jobs.length">
                <td colspan="9" class="ps-empty">No jobs found. Adjust your filters or create a new job.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── BOARD VIEW ── -->
      <div v-show="currentView === 'board'" class="ps-page ps-board-page">
        <div class="ps-page-header">
          <h1 class="ps-page-title">Kanban Board</h1>
          <div class="ps-board-filters">
            <select v-model="filterType" @change="loadJobs" class="ps-select ps-select-sm">
              <option value="">All Types</option>
              <option v-for="t in productTypes" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
        </div>

        <div class="ps-board" v-if="!isLoading">
          <div
            v-for="s in stages"
            :key="s.key"
            class="ps-board-col"
            @dragover.prevent
            @drop="onDrop($event, s.key)"
          >
            <div class="ps-board-col-header" :style="{ borderTopColor: s.color }">
              <span class="ps-board-col-title" :style="{ color: s.color }">{{ s.label }}</span>
              <span class="ps-board-col-count">{{ jobsByStage[s.key]?.length ?? 0 }}</span>
            </div>
            <div class="ps-board-cards">
              <div
                v-for="job in (jobsByStage[s.key] ?? [])"
                :key="job.id"
                class="ps-board-card"
                :class="{ 'ps-overdue-card': job.is_overdue }"
                draggable="true"
                @dragstart="onDragStart($event, job.id)"
                @click="openJob(job.id)"
              >
                <div class="ps-card-num">{{ job.job_number }}</div>
                <div class="ps-card-client">{{ job.client_name }}</div>
                <div class="ps-card-meta">
                  <span class="ps-type-badge ps-type-sm">{{ job.product_type }}</span>
                  <span v-if="job.is_overdue" class="ps-overdue-badge">Overdue</span>
                </div>
                <div class="ps-card-location" v-if="job.location">📍 {{ job.location }}</div>
                <div class="ps-card-due" v-if="job.due_date">
                  <span :class="{ 'ps-due-red': job.is_overdue }">📅 {{ formatDate(job.due_date) }}</span>
                </div>
                <div class="ps-card-pic" v-if="job.pic">👤 {{ job.pic }}</div>
              </div>
              <div v-if="!(jobsByStage[s.key]?.length)" class="ps-board-empty">No jobs</div>
            </div>
          </div>
        </div>
        <div v-if="isLoading" class="ps-loading">Loading…</div>
      </div>

      <!-- ── CALENDAR VIEW ── -->
      <div v-show="currentView === 'calendar'" class="ps-page">
        <div class="ps-page-header">
          <h1 class="ps-page-title">Calendar</h1>
          <div class="ps-cal-nav">
            <button class="ps-cal-nav-btn" @click="prevMonth">‹</button>
            <span class="ps-cal-month">{{ calMonthLabel }}</span>
            <button class="ps-cal-nav-btn" @click="nextMonth">›</button>
          </div>
        </div>

        <div class="ps-calendar">
          <div class="ps-cal-header">
            <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d" class="ps-cal-day-name">{{ d }}</div>
          </div>
          <div class="ps-cal-grid">
            <div
              v-for="day in calendarDays"
              :key="day.dateStr"
              class="ps-cal-cell"
              :class="{ 'ps-cal-other': !day.inMonth, 'ps-cal-today': day.isToday }"
            >
              <span class="ps-cal-date">{{ day.date }}</span>
              <div class="ps-cal-jobs">
                <div
                  v-for="job in day.jobs"
                  :key="job.id"
                  class="ps-cal-job-chip"
                  :style="{ background: stageColor(job.current_stage) }"
                  @click="openJob(job.id)"
                  :title="job.client_name + ' — ' + job.product_type"
                >
                  {{ job.client_name }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── DETAIL PANEL ── -->
    <transition name="ps-slide">
      <div v-if="selectedJob" class="ps-detail-panel">
        <div class="ps-detail-header">
          <div class="ps-detail-title-area">
            <div class="ps-detail-job-num">{{ selectedJob.job_number }}</div>
            <div class="ps-detail-client">{{ selectedJob.client_name }}</div>
            <span class="ps-stage-chip" :style="stageChipStyle(selectedJob.current_stage)">{{ stageLabel(selectedJob.current_stage) }}</span>
          </div>
          <div class="ps-detail-header-actions">
            <button class="ps-btn ps-btn-sm" @click="openEditJobModal">Edit</button>
            <button class="ps-btn ps-btn-sm ps-btn-danger" @click="deleteJob(selectedJob.id)">Delete</button>
            <button class="ps-close-btn" @click="selectedJob = null">✕</button>
          </div>
        </div>

        <!-- Stage stepper -->
        <div class="ps-stepper">
          <div
            v-for="(s, idx) in stages.slice(0, -1)"
            :key="s.key"
            class="ps-step"
            :class="{ 'ps-step-done': isStageReached(s.key), 'ps-step-active': selectedJob.current_stage === s.key }"
            @click="moveJobStage(selectedJob.id, s.key)"
            :title="'Move to ' + s.label"
          >
            <div class="ps-step-dot" :style="{ background: isStageReached(s.key) ? s.color : '#cbd5e1' }"></div>
            <div class="ps-step-label">{{ s.label }}</div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="ps-detail-tabs">
          <button v-for="t in detailTabs" :key="t.key" class="ps-tab" :class="{ active: detailTab === t.key }" @click="detailTab = t.key">
            {{ t.label }}
          </button>
        </div>

        <div class="ps-detail-body">

          <!-- Overview Tab -->
          <div v-if="detailTab === 'overview'" class="ps-detail-section">
            <div class="ps-info-grid">
              <div class="ps-info-item"><span class="ps-info-label">Product Type</span><span class="ps-info-val">{{ selectedJob.product_type }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Location</span><span class="ps-info-val">{{ selectedJob.location || '–' }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">PIC</span><span class="ps-info-val">{{ selectedJob.pic || '–' }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Request Date</span><span class="ps-info-val">{{ formatDate(selectedJob.request_date) }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Due Date</span><span class="ps-info-val" :class="{ 'ps-overdue': selectedJob.is_overdue }">{{ formatDate(selectedJob.due_date) || '–' }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Install Date</span><span class="ps-info-val">{{ formatDate(selectedJob.installation_date) || '–' }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Status</span><span class="ps-info-val">{{ selectedJob.overall_status }}</span></div>
              <div class="ps-info-item"><span class="ps-info-label">Created By</span><span class="ps-info-val">{{ selectedJob.created_by?.name || '–' }}</span></div>
            </div>
            <div class="ps-info-full" v-if="selectedJob.request_details">
              <span class="ps-info-label">Request Details</span>
              <p class="ps-info-text">{{ selectedJob.request_details }}</p>
            </div>
            <div class="ps-info-full" v-if="selectedJob.notes">
              <span class="ps-info-label">Notes</span>
              <p class="ps-info-text">{{ selectedJob.notes }}</p>
            </div>
          </div>

          <!-- Application Tab -->
          <div v-if="detailTab === 'application'" class="ps-detail-section">
            <form @submit.prevent="saveApplication" class="ps-form">
              <div class="ps-form-row">
                <label class="ps-label">Submission Date</label>
                <input type="date" v-model="appForm.submission_date" class="ps-input" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Council Authority</label>
                <select v-model="appForm.council" class="ps-input">
                  <option value="">Select Council</option>
                  <option v-for="c in councils" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div class="ps-form-row" v-if="appForm.council === 'Others'">
                <label class="ps-label">Council (Other)</label>
                <input v-model="appForm.council_other" class="ps-input" placeholder="Specify council" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Status</label>
                <select v-model="appForm.status" class="ps-input">
                  <option value="pending">Pending</option>
                  <option value="submitted">Submitted</option>
                  <option value="approved">Approved ✓</option>
                  <option value="rejected">Rejected ✗</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Reference Number</label>
                <input v-model="appForm.reference_number" class="ps-input" placeholder="e.g. DBKL/2026/001" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Remarks</label>
                <textarea v-model="appForm.remarks" class="ps-input ps-textarea" rows="3"></textarea>
              </div>
              <div class="ps-auto-note" v-if="appForm.status === 'approved'">
                ⚡ Approving will auto-advance job to <strong>Artwork Approval</strong>
              </div>
              <button type="submit" class="ps-btn ps-btn-primary ps-btn-full" :disabled="isSaving">
                {{ isSaving ? 'Saving…' : 'Save Application' }}
              </button>
            </form>
          </div>

          <!-- Artwork & Payment Tab -->
          <div v-if="detailTab === 'artwork'" class="ps-detail-section">
            <form @submit.prevent="saveArtworkPayment" class="ps-form">
              <div class="ps-form-section-title">Artwork</div>
              <div class="ps-form-row">
                <label class="ps-label">Artwork Version</label>
                <input v-model="artForm.artwork_version" class="ps-input" placeholder="e.g. V1, V2" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Artwork Status</label>
                <select v-model="artForm.artwork_status" class="ps-input">
                  <option value="pending">Pending</option>
                  <option value="in_review">In Review</option>
                  <option value="revision">Revision Required</option>
                  <option value="approved">Approved ✓</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Notes</label>
                <textarea v-model="artForm.artwork_notes" class="ps-input ps-textarea" rows="2"></textarea>
              </div>
              <div class="ps-form-section-title" style="margin-top:16px">Payment</div>
              <div class="ps-form-row">
                <label class="ps-label">Invoice Number</label>
                <input v-model="artForm.invoice_number" class="ps-input" placeholder="INV-2026-001" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Amount (RM)</label>
                <input type="number" step="0.01" v-model="artForm.payment_amount" class="ps-input" placeholder="0.00" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Payment Status</label>
                <select v-model="artForm.payment_status" class="ps-input">
                  <option value="pending">Pending</option>
                  <option value="partial">Partial Payment</option>
                  <option value="paid">Fully Paid ✓</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Payment Due Date</label>
                <input type="date" v-model="artForm.payment_due_date" class="ps-input" />
              </div>
              <div class="ps-auto-note" v-if="artForm.artwork_status === 'approved'">
                ⚡ Artwork approved → auto-advance to <strong>Payment Pending</strong>
              </div>
              <div class="ps-auto-note" v-if="artForm.payment_status === 'paid'">
                ⚡ Payment received → auto-advance to <strong>Printing</strong>
              </div>
              <button type="submit" class="ps-btn ps-btn-primary ps-btn-full" :disabled="isSaving">
                {{ isSaving ? 'Saving…' : 'Save Artwork & Payment' }}
              </button>
            </form>
          </div>

          <!-- Installation Tab -->
          <div v-if="detailTab === 'installation'" class="ps-detail-section">
            <form @submit.prevent="saveInstallation" class="ps-form">
              <div class="ps-form-section-title">Printing</div>
              <div class="ps-form-row">
                <label class="ps-label">Printing Status</label>
                <select v-model="instForm.printing_status" class="ps-input">
                  <option value="pending">Pending</option>
                  <option value="in_production">In Production</option>
                  <option value="ready">Ready ✓</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Quantity</label>
                <input type="number" min="1" v-model="instForm.quantity" class="ps-input" />
              </div>
              <div class="ps-form-section-title" style="margin-top:16px">Installation</div>
              <div class="ps-form-row">
                <label class="ps-label">Installation Date</label>
                <input type="date" v-model="instForm.installation_date" class="ps-input" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Installation Status</label>
                <select v-model="instForm.installation_status" class="ps-input">
                  <option value="scheduled">Scheduled</option>
                  <option value="ongoing">Ongoing</option>
                  <option value="completed">Completed ✓</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Installer PIC</label>
                <input v-model="instForm.installer_pic" class="ps-input" placeholder="Name of installer" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Notes</label>
                <textarea v-model="instForm.installation_notes" class="ps-input ps-textarea" rows="2"></textarea>
              </div>
              <div class="ps-auto-note" v-if="instForm.printing_status === 'ready'">
                ⚡ Printing ready → auto-advance to <strong>Installation</strong>
              </div>
              <div class="ps-auto-note" v-if="instForm.installation_status === 'completed'">
                ⚡ Installed → auto-advance to <strong>Completed</strong>
              </div>
              <button type="submit" class="ps-btn ps-btn-primary ps-btn-full" :disabled="isSaving">
                {{ isSaving ? 'Saving…' : 'Save Installation' }}
              </button>
            </form>
          </div>

          <!-- Complaints Tab -->
          <div v-if="detailTab === 'complaints'" class="ps-detail-section">
            <div class="ps-complaints-list">
              <div v-for="c in selectedJob.complaints ?? []" :key="c.id" class="ps-complaint-card">
                <div class="ps-complaint-header">
                  <span class="ps-complaint-type">{{ complaintTypeLabel(c.complaint_type) }}</span>
                  <span class="ps-resolution-badge" :class="'ps-res-' + c.resolution_status">{{ c.resolution_status.replace('_',' ') }}</span>
                </div>
                <div class="ps-complaint-date">{{ formatDate(c.complaint_date) }} — {{ c.site_location || '–' }}</div>
                <div class="ps-complaint-desc" v-if="c.description">{{ c.description }}</div>
                <div class="ps-complaint-assigned" v-if="c.assigned_user">👤 {{ c.assigned_user.name }}</div>
                <div class="ps-complaint-actions">
                  <select @change="resolveComplaint(c.id, $event.target.value)" class="ps-input ps-input-sm">
                    <option value="open" :selected="c.resolution_status === 'open'">Open</option>
                    <option value="in_progress" :selected="c.resolution_status === 'in_progress'">In Progress</option>
                    <option value="resolved" :selected="c.resolution_status === 'resolved'">Resolved</option>
                  </select>
                </div>
              </div>
              <div v-if="!(selectedJob.complaints?.length)" class="ps-empty-section">No complaints recorded.</div>
            </div>

            <div class="ps-add-complaint">
              <div class="ps-form-section-title">Add Complaint</div>
              <form @submit.prevent="addComplaint" class="ps-form">
                <div class="ps-form-row2">
                  <div class="ps-form-row">
                    <label class="ps-label">Date</label>
                    <input type="date" v-model="complaintForm.complaint_date" required class="ps-input" />
                  </div>
                  <div class="ps-form-row">
                    <label class="ps-label">Type</label>
                    <select v-model="complaintForm.complaint_type" required class="ps-input">
                      <option value="lighting">Lighting Issue</option>
                      <option value="structural">Structural Issue</option>
                      <option value="missing_panel">Missing Panel</option>
                      <option value="printing_defect">Printing Defect</option>
                      <option value="installation_defect">Installation Defect</option>
                      <option value="others">Others</option>
                    </select>
                  </div>
                </div>
                <div class="ps-form-row">
                  <label class="ps-label">Site Location</label>
                  <input v-model="complaintForm.site_location" class="ps-input" placeholder="Site address" />
                </div>
                <div class="ps-form-row">
                  <label class="ps-label">Description</label>
                  <textarea v-model="complaintForm.description" class="ps-input ps-textarea" rows="2"></textarea>
                </div>
                <button type="submit" class="ps-btn ps-btn-primary" :disabled="isSaving">Add Complaint</button>
              </form>
            </div>
          </div>

          <!-- Dismantle Tab -->
          <div v-if="detailTab === 'dismantle'" class="ps-detail-section">
            <form @submit.prevent="saveDismantle" class="ps-form">
              <div class="ps-form-row">
                <label class="ps-label">Status</label>
                <select v-model="dismantleForm.status" class="ps-input">
                  <option value="pending">Pending</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="completed">Completed ✓</option>
                </select>
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Scheduled Date</label>
                <input type="date" v-model="dismantleForm.scheduled_date" class="ps-input" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Completion Date</label>
                <input type="date" v-model="dismantleForm.completion_date" class="ps-input" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">PIC</label>
                <input v-model="dismantleForm.pic" class="ps-input" placeholder="Person in charge" />
              </div>
              <div class="ps-form-row">
                <label class="ps-label">Notes</label>
                <textarea v-model="dismantleForm.notes" class="ps-input ps-textarea" rows="3"></textarea>
              </div>
              <button type="submit" class="ps-btn ps-btn-primary ps-btn-full" :disabled="isSaving">
                {{ isSaving ? 'Saving…' : 'Save Dismantle' }}
              </button>
            </form>
          </div>

          <!-- Comments Tab -->
          <div v-if="detailTab === 'comments'" class="ps-detail-section">
            <div class="ps-comment-list">
              <div v-for="c in selectedJob.comments ?? []" :key="c.id" class="ps-comment">
                <div class="ps-comment-avatar">{{ initials(c.user?.name) }}</div>
                <div class="ps-comment-body">
                  <div class="ps-comment-meta">
                    <span class="ps-comment-author">{{ c.user?.name }}</span>
                    <span class="ps-comment-time">{{ formatDateTime(c.created_at) }}</span>
                  </div>
                  <div class="ps-comment-text">{{ c.comment }}</div>
                </div>
                <button class="ps-comment-del" @click="deleteComment(c.id)" title="Delete">✕</button>
              </div>
              <div v-if="!(selectedJob.comments?.length)" class="ps-empty-section">No comments yet.</div>
            </div>
            <div class="ps-comment-input">
              <textarea v-model="newComment" class="ps-input ps-textarea" rows="2" placeholder="Add a comment…"></textarea>
              <button class="ps-btn ps-btn-primary" @click="addComment" :disabled="!newComment.trim() || isSaving">
                {{ isSaving ? 'Posting…' : 'Post Comment' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── JOB MODAL ── -->
    <div v-if="showJobModal" class="ps-modal-overlay" @click.self="showJobModal = false">
      <div class="ps-modal">
        <div class="ps-modal-header">
          <h3>{{ editingJob.id ? 'Edit Job' : 'New Job' }}</h3>
          <button class="ps-close-btn" @click="showJobModal = false">✕</button>
        </div>
        <form @submit.prevent="saveJob" class="ps-form ps-modal-form">
          <div class="ps-form-row2">
            <div class="ps-form-row">
              <label class="ps-label">Client Name *</label>
              <input v-model="editingJob.client_name" required class="ps-input" placeholder="Client name" />
            </div>
            <div class="ps-form-row">
              <label class="ps-label">Product Type *</label>
              <select v-model="editingJob.product_type" required class="ps-input">
                <option v-for="t in productTypes" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </div>
          <div class="ps-form-row">
            <label class="ps-label">Title / Description</label>
            <input v-model="editingJob.title" class="ps-input" placeholder="Brief job title" />
          </div>
          <div class="ps-form-row">
            <label class="ps-label">Location</label>
            <input v-model="editingJob.location" class="ps-input" placeholder="Site location / address" />
          </div>
          <div class="ps-form-row2">
            <div class="ps-form-row">
              <label class="ps-label">Request Date *</label>
              <input type="date" v-model="editingJob.request_date" required class="ps-input" />
            </div>
            <div class="ps-form-row">
              <label class="ps-label">Due Date</label>
              <input type="date" v-model="editingJob.due_date" class="ps-input" />
            </div>
          </div>
          <div class="ps-form-row2">
            <div class="ps-form-row">
              <label class="ps-label">PIC</label>
              <input v-model="editingJob.pic" class="ps-input" placeholder="Person in charge" />
            </div>
            <div class="ps-form-row">
              <label class="ps-label">Stage</label>
              <select v-model="editingJob.current_stage" class="ps-input">
                <option v-for="s in stages" :key="s.key" :value="s.key">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div class="ps-form-row2">
            <div class="ps-form-row">
              <label class="ps-label">Status</label>
              <select v-model="editingJob.overall_status" class="ps-input">
                <option value="active">Active</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="ps-form-row">
              <label class="ps-label">Install Date</label>
              <input type="date" v-model="editingJob.installation_date" class="ps-input" />
            </div>
          </div>
          <div class="ps-form-row">
            <label class="ps-label">Request Details</label>
            <textarea v-model="editingJob.request_details" class="ps-input ps-textarea" rows="3" placeholder="Details of the request…"></textarea>
          </div>
          <div class="ps-form-row">
            <label class="ps-label">Notes</label>
            <textarea v-model="editingJob.notes" class="ps-input ps-textarea" rows="2"></textarea>
          </div>
          <div class="ps-modal-actions">
            <button type="button" class="ps-btn" @click="showJobModal = false">Cancel</button>
            <button type="submit" class="ps-btn ps-btn-primary" :disabled="isSaving">
              {{ isSaving ? 'Saving…' : (editingJob.id ? 'Update Job' : 'Create Job') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import api from '../api.js';

// ─── Constants ────────────────────────────────────────────────────────────────

const stages = [
  { key: 'new_request',     label: 'New Request',     color: '#6b7280' },
  { key: 'application',     label: 'Application',     color: '#3b82f6' },
  { key: 'artwork_approval',label: 'Artwork Approval', color: '#8b5cf6' },
  { key: 'payment_pending', label: 'Payment Pending',  color: '#f59e0b' },
  { key: 'printing',        label: 'Printing',         color: '#06b6d4' },
  { key: 'installation',    label: 'Installation',     color: '#10b981' },
  { key: 'completed',       label: 'Completed',        color: '#22c55e' },
  { key: 'cancelled',       label: 'Cancelled',        color: '#ef4444' },
];
const stageOrder = stages.map(s => s.key);

const productTypes = ['Billboard', 'Bunting', 'Banner', 'Signboard', 'Lamp Post', 'Others'];
const councils = ['DBKL', 'MBPJ', 'MBSJ', 'MBSA', 'JKR', 'Others'];

const detailTabs = [
  { key: 'overview',     label: 'Overview' },
  { key: 'application',  label: 'Application' },
  { key: 'artwork',      label: 'Artwork & Payment' },
  { key: 'installation', label: 'Installation' },
  { key: 'complaints',   label: 'Complaints' },
  { key: 'dismantle',    label: 'Dismantle' },
  { key: 'comments',     label: 'Comments' },
];

// ─── State ────────────────────────────────────────────────────────────────────

const currentView  = ref('dashboard');
const isLoading    = ref(false);
const isSaving     = ref(false);

const jobs         = ref([]);
const stats        = ref({});
const byStage      = ref({});
const recentJobs   = ref([]);
const users        = ref([]);

const selectedJob  = ref(null);
const detailTab    = ref('overview');

const showJobModal = ref(false);
const editingJob   = reactive({});

const searchQuery  = ref('');
const filterStage  = ref('');
const filterType   = ref('');
const filterStatus = ref('');
const sortField    = ref('created_at');
const sortDir      = ref('desc');

const newComment   = ref('');
const draggedId    = ref(null);

// Calendar state
const calYear  = ref(new Date().getFullYear());
const calMonth = ref(new Date().getMonth()); // 0-indexed

// Sub-forms
const appForm       = reactive({ submission_date:'', council:'', council_other:'', status:'pending', reference_number:'', remarks:'' });
const artForm       = reactive({ artwork_version:'', artwork_status:'pending', payment_amount:'', payment_status:'pending', invoice_number:'', payment_due_date:'', artwork_notes:'' });
const instForm      = reactive({ installation_date:'', quantity:1, printing_status:'pending', installation_status:'scheduled', installer_pic:'', installation_notes:'' });
const complaintForm = reactive({ complaint_date:'', site_location:'', complaint_type:'lighting', description:'' });
const dismantleForm = reactive({ scheduled_date:'', completion_date:'', pic:'', status:'pending', notes:'' });

// ─── Computed ─────────────────────────────────────────────────────────────────

const todayLabel = computed(() => new Date().toLocaleDateString('en-MY', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));

const jobsByStage = computed(() => {
  const map = {};
  stages.forEach(s => { map[s.key] = []; });
  jobs.value.forEach(j => {
    if (map[j.current_stage]) map[j.current_stage].push(j);
  });
  return map;
});

const maxStageCount = computed(() => Math.max(1, ...Object.values(byStage.value)));

const calMonthLabel = computed(() => new Date(calYear.value, calMonth.value, 1).toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }));

const calendarDays = computed(() => {
  const today = new Date(); today.setHours(0,0,0,0);
  const firstDay = new Date(calYear.value, calMonth.value, 1);
  const lastDay  = new Date(calYear.value, calMonth.value + 1, 0);
  const startPad = firstDay.getDay();
  const days = [];

  for (let i = startPad - 1; i >= 0; i--) {
    const d = new Date(firstDay); d.setDate(d.getDate() - i - 1);
    days.push({ date: d.getDate(), dateStr: d.toISOString().slice(0,10), inMonth: false, isToday: false, jobs: [] });
  }
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const dt = new Date(calYear.value, calMonth.value, d);
    const ds = dt.toISOString().slice(0,10);
    const dayJobs = jobs.value.filter(j => j.due_date === ds || j.installation_date === ds);
    days.push({ date: d, dateStr: ds, inMonth: true, isToday: dt.getTime() === today.getTime(), jobs: dayJobs });
  }
  const remaining = 42 - days.length;
  for (let i = 1; i <= remaining; i++) {
    const d = new Date(lastDay); d.setDate(d.getDate() + i);
    days.push({ date: d.getDate(), dateStr: d.toISOString().slice(0,10), inMonth: false, isToday: false, jobs: [] });
  }
  return days;
});

// ─── Data Loading ─────────────────────────────────────────────────────────────

async function loadDashboard() {
  try {
    const { data } = await api.get('/v1/prod/dashboard');
    stats.value     = data.stats;
    byStage.value   = data.byStage;
    recentJobs.value = data.recentJobs;
  } catch (e) { console.error(e); }
}

async function loadJobs() {
  isLoading.value = true;
  try {
    const params = {};
    if (searchQuery.value)  params.search = searchQuery.value;
    if (filterStage.value)  params.stage  = filterStage.value;
    if (filterType.value)   params.product_type = filterType.value;
    if (filterStatus.value) params.overall_status = filterStatus.value;
    params.sort = sortField.value;
    params.dir  = sortDir.value;
    const { data } = await api.get('/v1/prod/jobs', { params });
    jobs.value = data;
  } catch (e) { console.error(e); }
  finally { isLoading.value = false; }
}

async function loadUsers() {
  try { const { data } = await api.get('/v1/prod/users'); users.value = data; }
  catch (e) { console.error(e); }
}

async function openJob(id) {
  selectedJob.value = null;
  detailTab.value = 'overview';
  try {
    const { data } = await api.get(`/v1/prod/jobs/${id}`);
    selectedJob.value = data;
    populateForms(data);
  } catch (e) { console.error(e); }
}

function populateForms(job) {
  const ap = job.application ?? {};
  Object.assign(appForm, { submission_date: ap.submission_date??'', council: ap.council??'', council_other: ap.council_other??'', status: ap.status??'pending', reference_number: ap.reference_number??'', remarks: ap.remarks??'' });

  const aw = job.artwork_payment ?? {};
  Object.assign(artForm, { artwork_version: aw.artwork_version??'', artwork_status: aw.artwork_status??'pending', payment_amount: aw.payment_amount??'', payment_status: aw.payment_status??'pending', invoice_number: aw.invoice_number??'', payment_due_date: aw.payment_due_date??'', artwork_notes: aw.artwork_notes??'' });

  const ins = job.installation ?? {};
  Object.assign(instForm, { installation_date: ins.installation_date??'', quantity: ins.quantity??1, printing_status: ins.printing_status??'pending', installation_status: ins.installation_status??'scheduled', installer_pic: ins.installer_pic??'', installation_notes: ins.installation_notes??'' });

  const dis = job.dismantle ?? {};
  Object.assign(dismantleForm, { scheduled_date: dis.scheduled_date??'', completion_date: dis.completion_date??'', pic: dis.pic??'', status: dis.status??'pending', notes: dis.notes??'' });

  Object.assign(complaintForm, { complaint_date: new Date().toISOString().slice(0,10), site_location:'', complaint_type:'lighting', description:'' });
}

onMounted(async () => {
  await Promise.all([loadDashboard(), loadJobs(), loadUsers()]);
});

// Watch view changes to refresh data
watch(currentView, () => { loadJobs(); if (currentView.value === 'dashboard') loadDashboard(); });

// ─── Job CRUD ─────────────────────────────────────────────────────────────────

function openNewJobModal() {
  Object.assign(editingJob, { id:null, client_name:'', title:'', product_type:'Billboard', location:'', request_date: new Date().toISOString().slice(0,10), request_details:'', pic:'', current_stage:'new_request', overall_status:'active', due_date:'', installation_date:'', notes:'' });
  showJobModal.value = true;
}

function openEditJobModal() {
  if (!selectedJob.value) return;
  const j = selectedJob.value;
  Object.assign(editingJob, { id:j.id, client_name:j.client_name, title:j.title??'', product_type:j.product_type, location:j.location??'', request_date:j.request_date??'', request_details:j.request_details??'', pic:j.pic??'', current_stage:j.current_stage, overall_status:j.overall_status, due_date:j.due_date??'', installation_date:j.installation_date??'', notes:j.notes??'' });
  showJobModal.value = true;
}

async function saveJob() {
  isSaving.value = true;
  try {
    const payload = { ...editingJob };
    if (editingJob.id) {
      const { data } = await api.put(`/v1/prod/jobs/${editingJob.id}`, payload);
      if (selectedJob.value?.id === editingJob.id) await openJob(editingJob.id);
    } else {
      const { data } = await api.post('/v1/prod/jobs', payload);
    }
    showJobModal.value = false;
    await loadJobs();
    if (currentView.value === 'dashboard') await loadDashboard();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function deleteJob(id) {
  if (!confirm('Delete this job? This cannot be undone.')) return;
  try {
    await api.delete(`/v1/prod/jobs/${id}`);
    if (selectedJob.value?.id === id) selectedJob.value = null;
    await loadJobs();
    if (currentView.value === 'dashboard') await loadDashboard();
  } catch (e) { console.error(e); }
}

async function moveJobStage(id, newStage) {
  try {
    const { data } = await api.put(`/v1/prod/jobs/${id}/stage`, { stage: newStage });
    const idx = jobs.value.findIndex(j => j.id === id);
    if (idx !== -1) jobs.value[idx] = { ...jobs.value[idx], ...data };
    if (selectedJob.value?.id === id) selectedJob.value = { ...selectedJob.value, ...data };
  } catch (e) { console.error(e); }
}

// ─── Sub-form Saves ───────────────────────────────────────────────────────────

async function saveApplication() {
  isSaving.value = true;
  try {
    await api.put(`/v1/prod/jobs/${selectedJob.value.id}/application`, { ...appForm });
    await refreshSelected();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function saveArtworkPayment() {
  isSaving.value = true;
  try {
    await api.put(`/v1/prod/jobs/${selectedJob.value.id}/artwork-payment`, { ...artForm });
    await refreshSelected();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function saveInstallation() {
  isSaving.value = true;
  try {
    await api.put(`/v1/prod/jobs/${selectedJob.value.id}/installation`, { ...instForm });
    await refreshSelected();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function saveDismantle() {
  isSaving.value = true;
  try {
    await api.put(`/v1/prod/jobs/${selectedJob.value.id}/dismantle`, { ...dismantleForm });
    await refreshSelected();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function addComplaint() {
  isSaving.value = true;
  try {
    await api.post(`/v1/prod/jobs/${selectedJob.value.id}/complaints`, { ...complaintForm });
    await refreshSelected();
    Object.assign(complaintForm, { complaint_date: new Date().toISOString().slice(0,10), site_location:'', complaint_type:'lighting', description:'' });
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function resolveComplaint(complaintId, status) {
  try {
    await api.put(`/v1/prod/complaints/${complaintId}`, { resolution_status: status });
    await refreshSelected();
  } catch (e) { console.error(e); }
}

async function addComment() {
  if (!newComment.value.trim()) return;
  isSaving.value = true;
  try {
    await api.post(`/v1/prod/jobs/${selectedJob.value.id}/comments`, { comment: newComment.value });
    newComment.value = '';
    await refreshSelected();
  } catch (e) { console.error(e); }
  finally { isSaving.value = false; }
}

async function deleteComment(commentId) {
  try {
    await api.delete(`/v1/prod/comments/${commentId}`);
    await refreshSelected();
  } catch (e) { console.error(e); }
}

async function refreshSelected() {
  if (!selectedJob.value) return;
  const { data } = await api.get(`/v1/prod/jobs/${selectedJob.value.id}`);
  selectedJob.value = data;
  populateForms(data);
  await loadJobs();
}

// ─── Drag and Drop ────────────────────────────────────────────────────────────

function onDragStart(e, id) {
  draggedId.value = id;
  e.dataTransfer.effectAllowed = 'move';
}

function onDrop(e, stage) {
  e.preventDefault();
  if (draggedId.value) {
    moveJobStage(draggedId.value, stage);
    draggedId.value = null;
  }
}

// ─── Sorting ──────────────────────────────────────────────────────────────────

function setSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortDir.value = 'asc';
  }
  loadJobs();
}

function sortArrow(field) {
  if (sortField.value !== field) return '↕';
  return sortDir.value === 'asc' ? '↑' : '↓';
}

// ─── Calendar Navigation ──────────────────────────────────────────────────────

function prevMonth() {
  if (calMonth.value === 0) { calMonth.value = 11; calYear.value--; }
  else calMonth.value--;
}

function nextMonth() {
  if (calMonth.value === 11) { calMonth.value = 0; calYear.value++; }
  else calMonth.value++;
}

// ─── Debounced Search ─────────────────────────────────────────────────────────

let searchTimer = null;
function debouncedLoad() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadJobs, 350);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function stageLabel(key) {
  return stages.find(s => s.key === key)?.label ?? key;
}

function stageColor(key) {
  return stages.find(s => s.key === key)?.color ?? '#6b7280';
}

function stageChipStyle(key) {
  const color = stageColor(key);
  return { background: color + '22', color, border: `1px solid ${color}55`, borderRadius: '6px', padding: '2px 8px', fontSize: '11px', fontWeight: 600, display: 'inline-block' };
}

function stageBarWidth(key) {
  const count = byStage.value[key] ?? 0;
  return Math.round((count / maxStageCount.value) * 100);
}

function isStageReached(key) {
  if (!selectedJob.value) return false;
  const current = stageOrder.indexOf(selectedJob.value.current_stage);
  const target  = stageOrder.indexOf(key);
  return current >= target;
}

function complaintTypeLabel(type) {
  const map = { lighting: 'Lighting Issue', structural: 'Structural Issue', missing_panel: 'Missing Panel', printing_defect: 'Printing Defect', installation_defect: 'Installation Defect', others: 'Others' };
  return map[type] ?? type;
}

function formatDate(d) {
  if (!d) return '';
  try {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-MY', { day:'2-digit', month:'short', year:'numeric' });
  } catch { return d; }
}

function formatDateTime(d) {
  if (!d) return '';
  try { return new Date(d).toLocaleString('en-MY', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' }); }
  catch { return d; }
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').slice(0,2).map(n => n[0]).join('').toUpperCase();
}
</script>

<style scoped>
/* ── Layout ────────────────────────────────────────────────────────────────── */
.ps-wrap { display: flex; height: 100vh; overflow: hidden; background: #f8fafc; }

/* ── Sub-Sidebar ──────────────────────────────────────────────────────────── */
.ps-sidebar { width: 220px; min-width: 220px; background: #1e293b; display: flex; flex-direction: column; overflow-y: auto; border-right: 1px solid rgba(255,255,255,0.05); }
.ps-sidebar-header { padding: 20px 14px 12px; }
.ps-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.ps-logo-icon { font-size: 22px; }
.ps-logo-text { display: flex; flex-direction: column; }
.ps-logo-title { font-size: 13px; font-weight: 700; color: #f1f5f9; }
.ps-logo-sub { font-size: 10px; color: #64748b; }
.ps-new-btn { width: 100%; padding: 8px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
.ps-new-btn:hover { background: #2563eb; }

.ps-nav { padding: 4px 8px; flex: 1; }
.ps-nav-item { width: 100%; display: flex; align-items: center; gap: 10px; padding: 9px 10px; border: none; background: transparent; color: #64748b; font-size: 13px; font-weight: 500; border-radius: 7px; cursor: pointer; text-align: left; margin-bottom: 2px; }
.ps-nav-item:hover { background: rgba(255,255,255,0.06); color: #cbd5e1; }
.ps-nav-item.active { background: rgba(59,130,246,0.15); color: #93c5fd; font-weight: 600; }
.ps-nav-icon { font-size: 14px; width: 18px; text-align: center; }

.ps-sidebar-footer { padding: 12px 14px 16px; border-top: 1px solid rgba(255,255,255,0.05); }
.ps-stage-legend { }
.ps-legend-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #334155; margin-bottom: 8px; }
.ps-legend-item { display: flex; align-items: center; gap: 7px; font-size: 11px; color: #475569; margin-bottom: 4px; }
.ps-legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Main Area ────────────────────────────────────────────────────────────── */
.ps-main { flex: 1; overflow: auto; transition: margin-right 0.25s ease; }
.ps-main.panel-open { margin-right: 480px; }

/* ── Page ─────────────────────────────────────────────────────────────────── */
.ps-page { padding: 28px 32px; max-width: 1400px; }
.ps-board-page { padding: 20px; max-width: none; }

.ps-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.ps-page-title { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0; }
.ps-page-date { font-size: 13px; color: #64748b; }

/* ── Stat Cards ───────────────────────────────────────────────────────────── */
.ps-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; margin-bottom: 28px; }
.ps-stat-card { background: white; border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 12px; border: 1px solid #e2e8f0; border-left: 3px solid var(--accent); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.ps-stat-icon { font-size: 20px; }
.ps-stat-value { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
.ps-stat-label { font-size: 11px; color: #64748b; margin-top: 3px; }

/* ── Dashboard Section ────────────────────────────────────────────────────── */
.ps-dash-section { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 20px; }
.ps-section-title { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 16px; }

.ps-stage-bars { display: flex; flex-direction: column; gap: 8px; }
.ps-stage-bar-item { display: flex; align-items: center; gap: 10px; }
.ps-stage-bar-label { display: flex; align-items: center; gap: 7px; width: 140px; font-size: 12px; color: #475569; flex-shrink: 0; }
.ps-stage-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ps-stage-bar-track { flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
.ps-stage-bar-fill { height: 100%; border-radius: 4px; transition: width 0.4s ease; min-width: 4px; }
.ps-stage-bar-count { width: 30px; text-align: right; font-size: 12px; font-weight: 700; color: #0f172a; }

/* ── Shared Table ─────────────────────────────────────────────────────────── */
.ps-recent-table { overflow-x: auto; }
.ps-recent-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ps-recent-table th { padding: 10px 22px; color: #64748b; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; border-bottom: 1px solid #e2e8f0; }
.ps-recent-table td { padding: 11px 22px; white-space: nowrap; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.ps-recent-table th:first-child, .ps-recent-table td:first-child { padding-left: 6px; }
.ps-recent-table th:last-child, .ps-recent-table td:last-child { padding-right: 6px; }
.ps-notion-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ps-notion-table th { padding: 10px 12px; background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 12px; white-space: nowrap; }
.ps-notion-table td { padding: 11px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.ps-table-row { cursor: pointer; transition: background 0.12s; }
.ps-table-row:hover { background: #f8fafc; }
.ps-th-sort { cursor: pointer; user-select: none; }
.ps-th-sort:hover { color: #3b82f6; }
.ps-sort-arrow { font-size: 11px; color: #94a3b8; }
.ps-job-num { font-weight: 700; color: #3b82f6; font-size: 12px; }
.ps-client { font-weight: 600; color: #1e293b; }
.ps-client-bold { font-weight: 600; color: #1e293b; }
.ps-location { color: #64748b; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ps-overdue { color: #ef4444 !important; font-weight: 600; }
.ps-empty { text-align: center; padding: 32px; color: #94a3b8; font-size: 14px; }

.ps-type-badge { font-size: 11px; background: #eff6ff; color: #3b82f6; border-radius: 4px; padding: 2px 7px; font-weight: 600; white-space: nowrap; }
.ps-type-sm { font-size: 10px; }

.ps-actions { display: flex; gap: 4px; }
.ps-action-btn { background: none; border: 1px solid #e2e8f0; border-radius: 5px; padding: 3px 6px; cursor: pointer; font-size: 13px; color: #64748b; }
.ps-action-btn:hover { background: #f1f5f9; }
.ps-action-btn.ps-danger:hover { background: #fef2f2; border-color: #fca5a5; }

/* ── Filters ──────────────────────────────────────────────────────────────── */
.ps-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.ps-search { flex: 1; min-width: 220px; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; background: white; }
.ps-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.ps-select { padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; background: white; cursor: pointer; outline: none; }
.ps-select:focus { border-color: #3b82f6; }
.ps-select-sm { font-size: 12px; padding: 6px 8px; }

.ps-table-wrap { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }

/* ── Board ────────────────────────────────────────────────────────────────── */
.ps-board-page .ps-page-header { margin-bottom: 16px; }
.ps-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 16px; min-height: calc(100vh - 120px); }
.ps-board-col { min-width: 240px; width: 240px; background: #f8fafc; border-radius: 10px; display: flex; flex-direction: column; border: 1px solid #e2e8f0; }
.ps-board-col-header { padding: 12px 14px 10px; border-top: 3px solid #e2e8f0; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: space-between; }
.ps-board-col-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.ps-board-col-count { background: #e2e8f0; color: #64748b; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 700; }
.ps-board-cards { padding: 8px; flex: 1; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; max-height: calc(100vh - 160px); }
.ps-board-card { background: white; border-radius: 8px; padding: 12px; border: 1px solid #e2e8f0; cursor: pointer; transition: box-shadow 0.15s, transform 0.1s; }
.ps-board-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-1px); }
.ps-board-card.ps-overdue-card { border-color: #fca5a5; }
.ps-card-num { font-size: 10px; color: #3b82f6; font-weight: 700; margin-bottom: 4px; }
.ps-card-client { font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 6px; }
.ps-card-meta { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 4px; }
.ps-overdue-badge { font-size: 10px; background: #fef2f2; color: #ef4444; border-radius: 4px; padding: 2px 5px; font-weight: 700; }
.ps-card-location { font-size: 11px; color: #64748b; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-card-due { font-size: 11px; color: #64748b; margin-top: 3px; }
.ps-due-red { color: #ef4444; font-weight: 600; }
.ps-card-pic { font-size: 11px; color: #94a3b8; margin-top: 3px; }
.ps-board-empty { font-size: 12px; color: #94a3b8; text-align: center; padding: 16px 0; }
.ps-board-filters { display: flex; gap: 8px; }

/* ── Calendar ─────────────────────────────────────────────────────────────── */
.ps-calendar { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
.ps-cal-nav { display: flex; align-items: center; gap: 12px; }
.ps-cal-nav-btn { width: 32px; height: 32px; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }
.ps-cal-nav-btn:hover { background: #f1f5f9; }
.ps-cal-month { font-size: 15px; font-weight: 700; color: #1e293b; min-width: 160px; text-align: center; }
.ps-cal-header { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid #e2e8f0; }
.ps-cal-day-name { padding: 10px; text-align: center; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.ps-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.ps-cal-cell { min-height: 90px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; padding: 6px; position: relative; }
.ps-cal-cell:nth-child(7n) { border-right: none; }
.ps-cal-other { background: #fafafa; }
.ps-cal-today { background: #eff6ff; }
.ps-cal-date { font-size: 12px; font-weight: 600; color: #1e293b; display: block; margin-bottom: 4px; }
.ps-cal-other .ps-cal-date { color: #cbd5e1; }
.ps-cal-today .ps-cal-date { background: #3b82f6; color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; }
.ps-cal-jobs { display: flex; flex-direction: column; gap: 2px; }
.ps-cal-job-chip { font-size: 10px; color: white; border-radius: 3px; padding: 2px 5px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }

/* ── Detail Panel ─────────────────────────────────────────────────────────── */
.ps-detail-panel { position: fixed; top: 0; right: 0; width: 480px; height: 100vh; background: white; box-shadow: -4px 0 24px rgba(0,0,0,0.12); display: flex; flex-direction: column; z-index: 100; border-left: 1px solid #e2e8f0; overflow: hidden; }
.ps-slide-enter-active, .ps-slide-leave-active { transition: transform 0.25s ease; }
.ps-slide-enter-from, .ps-slide-leave-to { transform: translateX(100%); }

.ps-detail-header { padding: 16px 18px 12px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-shrink: 0; }
.ps-detail-title-area { flex: 1; min-width: 0; }
.ps-detail-job-num { font-size: 11px; color: #3b82f6; font-weight: 700; margin-bottom: 2px; }
.ps-detail-client { font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ps-detail-header-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

/* ── Stage Stepper ────────────────────────────────────────────────────────── */
.ps-stepper { display: flex; align-items: flex-start; padding: 10px 18px; gap: 0; overflow-x: auto; flex-shrink: 0; border-bottom: 1px solid #e2e8f0; }
.ps-step { display: flex; flex-direction: column; align-items: center; gap: 3px; cursor: pointer; min-width: 60px; flex: 1; position: relative; }
.ps-step::after { content: ''; position: absolute; top: 7px; left: 50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 0; }
.ps-step:last-child::after { display: none; }
.ps-step-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #e2e8f0; background: white; z-index: 1; transition: background 0.2s; }
.ps-step-done .ps-step-dot { border-color: transparent; }
.ps-step-label { font-size: 9px; color: #94a3b8; text-align: center; white-space: nowrap; }
.ps-step-done .ps-step-label { color: #475569; font-weight: 600; }
.ps-step-active .ps-step-label { color: #0f172a; font-weight: 700; }

/* ── Detail Tabs ──────────────────────────────────────────────────────────── */
.ps-detail-tabs { display: flex; overflow-x: auto; border-bottom: 1px solid #e2e8f0; flex-shrink: 0; padding: 0 10px; }
.ps-tab { padding: 10px 12px; border: none; background: none; font-size: 12px; font-weight: 500; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
.ps-tab:hover { color: #1e293b; }
.ps-tab.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 700; }

.ps-detail-body { flex: 1; overflow-y: auto; }

/* ── Detail Sections ──────────────────────────────────────────────────────── */
.ps-detail-section { padding: 16px 18px; }
.ps-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.ps-info-item { display: flex; flex-direction: column; gap: 2px; }
.ps-info-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.ps-info-val { font-size: 13px; color: #1e293b; font-weight: 500; }
.ps-info-full { margin-bottom: 12px; }
.ps-info-text { font-size: 13px; color: #475569; margin: 4px 0 0; line-height: 1.5; }

/* ── Forms ────────────────────────────────────────────────────────────────── */
.ps-form { display: flex; flex-direction: column; gap: 12px; }
.ps-form-row { display: flex; flex-direction: column; gap: 4px; }
.ps-form-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ps-label { font-size: 11px; font-weight: 700; color: #475569; }
.ps-input { padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 13px; outline: none; background: white; width: 100%; box-sizing: border-box; }
.ps-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.ps-input-sm { padding: 4px 7px; font-size: 11px; }
.ps-textarea { resize: vertical; min-height: 60px; font-family: inherit; }
.ps-form-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #334155; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
.ps-auto-note { font-size: 11px; color: #f59e0b; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 6px 10px; }

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.ps-btn { padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; background: white; color: #475569; }
.ps-btn:hover { background: #f1f5f9; }
.ps-btn-primary { background: #3b82f6; color: white; border-color: #3b82f6; }
.ps-btn-primary:hover { background: #2563eb; }
.ps-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.ps-btn-danger { background: #fef2f2; color: #ef4444; border-color: #fca5a5; }
.ps-btn-danger:hover { background: #fee2e2; }
.ps-btn-sm { padding: 5px 10px; font-size: 12px; }
.ps-btn-full { width: 100%; text-align: center; }
.ps-close-btn { width: 28px; height: 28px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; color: #94a3b8; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; }
.ps-close-btn:hover { background: #f1f5f9; color: #64748b; }

/* ── Complaints ───────────────────────────────────────────────────────────── */
.ps-complaints-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
.ps-complaint-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
.ps-complaint-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.ps-complaint-type { font-size: 12px; font-weight: 700; color: #1e293b; }
.ps-resolution-badge { font-size: 10px; font-weight: 700; border-radius: 4px; padding: 2px 7px; }
.ps-res-open { background: #fef2f2; color: #ef4444; }
.ps-res-in_progress { background: #fffbeb; color: #f59e0b; }
.ps-res-resolved { background: #f0fdf4; color: #22c55e; }
.ps-complaint-date { font-size: 11px; color: #64748b; margin-bottom: 4px; }
.ps-complaint-desc { font-size: 12px; color: #475569; margin-bottom: 4px; }
.ps-complaint-assigned { font-size: 11px; color: #64748b; margin-bottom: 8px; }
.ps-complaint-actions { display: flex; gap: 6px; }
.ps-add-complaint { border-top: 1px solid #f1f5f9; padding-top: 14px; }
.ps-empty-section { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; }

/* ── Comments ─────────────────────────────────────────────────────────────── */
.ps-comment-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; }
.ps-comment { display: flex; gap: 10px; position: relative; }
.ps-comment-avatar { width: 30px; height: 30px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.ps-comment-body { flex: 1; background: #f8fafc; border-radius: 8px; padding: 8px 10px; }
.ps-comment-meta { display: flex; gap: 8px; align-items: center; margin-bottom: 3px; }
.ps-comment-author { font-size: 12px; font-weight: 700; color: #1e293b; }
.ps-comment-time { font-size: 10px; color: #94a3b8; }
.ps-comment-text { font-size: 13px; color: #475569; }
.ps-comment-del { position: absolute; top: 4px; right: 0; background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: 11px; opacity: 0; }
.ps-comment:hover .ps-comment-del { opacity: 1; }
.ps-comment-input { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 12px; }

/* ── Modal ────────────────────────────────────────────────────────────────── */
.ps-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 200; padding: 20px; }
.ps-modal { background: white; border-radius: 14px; width: 100%; max-width: 640px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.ps-modal-header { padding: 18px 20px 14px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.ps-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
.ps-modal-form { padding: 18px 20px; overflow-y: auto; flex: 1; }
.ps-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; }

/* ── Loading ──────────────────────────────────────────────────────────────── */
.ps-loading { display: flex; align-items: center; justify-content: center; padding: 40px; color: #94a3b8; font-size: 14px; }

/* ── Stage Chips ──────────────────────────────────────────────────────────── */
.ps-stage-chip { display: inline-block; }
</style>
