<template>
  <div class="dtm-wrap">
    <!-- Left sub-sidebar -->
    <aside class="dtm-nav">
      <div class="dtm-nav-header">
        <span class="dtm-nav-icon">📋</span>
        <span class="dtm-nav-title">Task Manager</span>
      </div>
      <nav class="dtm-nav-links">
        <button v-for="v in views" :key="v.id" @click="currentView = v.id"
          :class="['dtm-nav-btn', currentView === v.id && 'active']">
          <span class="dtm-nav-link-icon">{{ v.icon }}</span>
          <span>{{ v.label }}</span>
        </button>
      </nav>
      <div class="dtm-nav-footer">
        <button class="dtm-add-btn" @click="openTaskModal()">+ New Task</button>
      </div>
    </aside>

    <!-- Main content -->
    <div class="dtm-content">
      <!-- Dashboard -->
      <div v-if="currentView === 'dashboard'" class="dtm-section">
        <div class="dtm-page-header">
          <h1>Dashboard</h1>
          <span class="dtm-sub">Overview of all department tasks</span>
        </div>

        <!-- Stat cards -->
        <div class="stat-grid">
          <div class="stat-card" v-for="s in statCards" :key="s.label" :style="{borderTopColor: s.color}">
            <div class="stat-icon" :style="{background: s.color+'22', color: s.color}">{{ s.icon }}</div>
            <div class="stat-body">
              <div class="stat-val">{{ s.value }}</div>
              <div class="stat-lbl">{{ s.label }}</div>
            </div>
          </div>
        </div>

        <!-- Charts row -->
        <div class="chart-row">
          <div class="chart-card">
            <div class="chart-title">Tasks by Department</div>
            <canvas ref="deptChart" height="220"></canvas>
          </div>
          <div class="chart-card">
            <div class="chart-title">Tasks by Status</div>
            <canvas ref="statusChart" height="220"></canvas>
          </div>
          <div class="chart-card">
            <div class="chart-title">Weekly Completion Rate</div>
            <canvas ref="weeklyChart" height="220"></canvas>
          </div>
        </div>

        <!-- Recent tasks -->
        <div class="dash-recent">
          <div class="section-title">Recent Tasks</div>
          <div class="task-rows">
            <div v-for="t in dashData.recentTasks" :key="t.id"
              class="task-row-item" @click="openTaskDetail(t)">
              <span class="priority-dot" :style="{background: priorityColor(t.priority)}"></span>
              <span class="task-row-title">{{ t.title }}</span>
              <span class="dept-tag" :style="{background: t.department?.color+'22', color: t.department?.color}">
                {{ t.department?.name }}
              </span>
              <span :class="['status-chip', 'st-'+t.status, t.is_overdue && 'st-overdue']">
                {{ t.is_overdue ? 'Overdue' : statusLabel(t.status) }}
              </span>
              <span class="task-row-date">{{ t.due_date_fmt || '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Board (Kanban) -->
      <div v-else-if="currentView === 'board'" class="dtm-section">
        <div class="dtm-page-header">
          <h1>Board View</h1>
          <div class="header-actions">
            <select v-model="boardFilters.department_id" @change="loadBoardTasks" class="dtm-select sm">
              <option value="">All Departments</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <button class="dtm-add-btn sm" @click="openTaskModal()">+ New Task</button>
          </div>
        </div>

        <div class="kanban-board">
          <div v-for="col in kanbanCols" :key="col.status" class="kanban-col"
            @dragover.prevent @drop="onDrop($event, col.status)"
            :class="dragOverCol === col.status && 'drag-over'">
            <div class="kanban-col-header" :style="{borderTopColor: col.color}">
              <span class="kanban-col-icon">{{ col.icon }}</span>
              <span class="kanban-col-title">{{ col.label }}</span>
              <span class="kanban-col-count">{{ boardTasksByStatus(col.status).length }}</span>
            </div>
            <div class="kanban-cards">
              <div v-for="task in boardTasksByStatus(col.status)" :key="task.id"
                class="kanban-card" draggable="true"
                @dragstart="onDragStart($event, task)"
                @click="openTaskDetail(task)">
                <div class="kc-top">
                  <span class="priority-badge" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                    {{ task.priority }}
                  </span>
                  <span v-if="task.is_overdue" class="overdue-badge">Overdue</span>
                </div>
                <div class="kc-title">{{ task.title }}</div>
                <div class="kc-dept" :style="{color: task.department?.color}">
                  {{ task.department?.icon }} {{ task.department?.name }}
                </div>
                <div class="kc-footer">
                  <span class="kc-date" :class="task.is_overdue && 'text-red'">
                    📅 {{ task.due_date_fmt || 'No date' }}
                  </span>
                  <span class="kc-assignee" v-if="task.assignee">
                    {{ initials(task.assignee.name) }}
                  </span>
                </div>
              </div>
              <div v-if="boardTasksByStatus(col.status).length === 0" class="kanban-empty">
                Drop tasks here
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div v-else-if="currentView === 'table'" class="dtm-section">
        <div class="dtm-page-header">
          <h1>Table View</h1>
          <div class="header-actions">
            <input v-model="tableFilters.search" @input="debouncedLoadTable" placeholder="Search tasks…" class="dtm-input sm" />
            <button class="dtm-add-btn sm" @click="openTaskModal()">+ New Task</button>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
          <select v-model="tableFilters.department_id" @change="loadTableTasks" class="dtm-select">
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <select v-model="tableFilters.status" @change="loadTableTasks" class="dtm-select">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting_approval">Waiting Approval</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="overdue">Overdue</option>
          </select>
          <select v-model="tableFilters.priority" @change="loadTableTasks" class="dtm-select">
            <option value="">All Priorities</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
          <select v-model="tableFilters.assigned_to" @change="loadTableTasks" class="dtm-select">
            <option value="">All Assignees</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <button @click="clearTableFilters" class="dtm-btn-ghost">Clear</button>
          <button @click="exportTable('excel')" class="dtm-btn-ghost">📊 Excel</button>
          <button @click="exportTable('pdf')" class="dtm-btn-ghost">📄 Print</button>
        </div>

        <!-- Table -->
        <div class="table-wrap">
          <table class="dtm-table">
            <thead>
              <tr>
                <th @click="toggleSort('title')">Task <sort-icon :field="'title'" :current="tableSort" /></th>
                <th @click="toggleSort('department_id')">Department</th>
                <th>Assigned To</th>
                <th @click="toggleSort('priority')">Priority</th>
                <th @click="toggleSort('due_date')">Due Date</th>
                <th @click="toggleSort('status')">Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="tableLoading">
                <td colspan="7" class="loading-cell">Loading…</td>
              </tr>
              <tr v-else-if="tableTasks.length === 0">
                <td colspan="7" class="empty-cell">No tasks found.</td>
              </tr>
              <tr v-for="task in tableTasks" :key="task.id"
                class="table-row" @click="openTaskDetail(task)">
                <td>
                  <span class="priority-dot" :style="{background: priorityColor(task.priority)}"></span>
                  {{ task.title }}
                </td>
                <td>
                  <span class="dept-tag sm" :style="{background: task.department?.color+'22', color: task.department?.color}">
                    {{ task.department?.name }}
                  </span>
                </td>
                <td>{{ task.assignee?.name || '—' }}</td>
                <td>
                  <span class="priority-badge" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                    {{ task.priority }}
                  </span>
                </td>
                <td :class="task.is_overdue && 'text-red'">{{ task.due_date_fmt || '—' }}</td>
                <td>
                  <span :class="['status-chip', 'st-'+task.status, task.is_overdue && 'st-overdue']">
                    {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
                  </span>
                </td>
                <td @click.stop>
                  <button class="dtm-btn-icon" @click="openTaskModal(task)" title="Edit">✏️</button>
                  <button class="dtm-btn-icon danger" @click="deleteTask(task.id)" title="Delete">🗑️</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Weekly Outstanding Report -->
      <div v-else-if="currentView === 'weekly'" class="dtm-section">
        <div class="dtm-page-header no-print">
          <h1>Weekly Outstanding Tasks</h1>
          <div class="header-actions">
            <input type="date" v-model="weeklyWeekStart" @change="loadWeeklyData" class="dtm-input sm" />
            <button @click="printWeekly" class="dtm-btn-ghost">🖨️ Print A4</button>
          </div>
        </div>

        <!-- Printable weekly report -->
        <div class="weekly-report" id="weekly-print-area">
          <div class="wr-header">
            <div class="wr-title">Finance HOD — Outstanding Task</div>
            <div class="wr-meta">
              <span><strong>Month:</strong> {{ weeklyMonthLabel }}</span>
              <span><strong>Week:</strong> {{ weeklyData.week_start }} to {{ weeklyData.week_end }}</span>
            </div>
          </div>

          <div class="wr-grid">
            <div v-for="deptRow in weeklyData.departments" :key="deptRow.department.id" class="wr-dept-block">
              <div class="wr-dept-header" :style="{background: deptRow.department.color, color: '#fff'}">
                {{ deptRow.department.icon }} {{ deptRow.department.name.toUpperCase() }}
              </div>
              <table class="wr-table">
                <thead>
                  <tr><th>Date</th><th>Task</th><th>Assignee</th><th>Status</th></tr>
                </thead>
                <tbody>
                  <tr v-for="task in deptRow.tasks" :key="task.id"
                    :class="task.is_overdue && 'wr-overdue'">
                    <td class="wr-date">{{ task.due_date || '—' }}</td>
                    <td>{{ task.title }}</td>
                    <td>{{ task.assignee || '—' }}</td>
                    <td>
                      <span :class="['wr-status', 'st-'+task.status]">{{ statusLabel(task.status) }}</span>
                    </td>
                  </tr>
                  <tr v-if="deptRow.tasks.length === 0">
                    <td colspan="4" class="wr-empty">No outstanding tasks</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Reports -->
      <div v-else-if="currentView === 'reports'" class="dtm-section">
        <div class="dtm-page-header">
          <h1>Reports</h1>
        </div>
        <div class="report-filters">
          <label>Date From: <input type="date" v-model="reportFilters.date_from" class="dtm-input sm" /></label>
          <label>Date To: <input type="date" v-model="reportFilters.date_to" class="dtm-input sm" /></label>
          <button @click="loadReport" class="dtm-add-btn">Generate Report</button>
          <button @click="printReport" class="dtm-btn-ghost">🖨️ Print</button>
        </div>

        <div v-if="reportData" class="report-content" id="report-print-area">
          <div class="report-summary-cards">
            <div class="rsc" v-for="s in reportSummaryCards" :key="s.label" :style="{borderColor: s.color}">
              <div class="rsc-val" :style="{color: s.color}">{{ s.value }}</div>
              <div class="rsc-lbl">{{ s.label }}</div>
            </div>
          </div>

          <div v-for="dept in reportData.byDepartment" :key="dept.department" class="report-dept-section">
            <div class="rds-header" :style="{borderColor: dept.color}">
              <strong :style="{color: dept.color}">{{ dept.department }}</strong>
              <span>Total: {{ dept.total }} | Completed: {{ dept.completed }} | Pending: {{ dept.pending }} | Overdue: {{ dept.overdue }}</span>
            </div>
            <table class="dtm-table compact">
              <thead><tr><th>Task</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-for="t in dept.tasks" :key="t.id">
                  <td>{{ t.title }}</td>
                  <td>{{ t.assignee?.name || '—' }}</td>
                  <td><span class="priority-badge sm" :style="{color: priorityColor(t.priority)}">{{ t.priority }}</span></td>
                  <td :class="t.is_overdue && 'text-red'">{{ t.due_date_fmt || '—' }}</td>
                  <td><span :class="['status-chip', 'st-'+t.status, t.is_overdue && 'st-overdue']">{{ t.is_overdue ? 'Overdue' : statusLabel(t.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="dtm-empty-state">
          <div class="es-icon">📊</div>
          <div>Select a date range and click Generate Report</div>
        </div>
      </div>
    </div>

    <!-- Task Detail Panel (right slide-in) -->
    <transition name="slide-panel">
      <div v-if="selectedTask" class="detail-panel-overlay" @click.self="selectedTask = null">
        <div class="detail-panel">
          <div class="dp-header">
            <div>
              <span class="dp-dept-tag" :style="{background: selectedTask.department?.color+'22', color: selectedTask.department?.color}">
                {{ selectedTask.department?.icon }} {{ selectedTask.department?.name }}
              </span>
              <h2 class="dp-title">{{ selectedTask.title }}</h2>
            </div>
            <button class="dp-close" @click="selectedTask = null">✕</button>
          </div>

          <div class="dp-meta-row">
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Priority</span>
              <span class="priority-badge" :style="{background: priorityColor(selectedTask.priority)+'22', color: priorityColor(selectedTask.priority)}">
                {{ selectedTask.priority }}
              </span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Status</span>
              <span :class="['status-chip', 'st-'+selectedTask.status, selectedTask.is_overdue && 'st-overdue']">
                {{ selectedTask.is_overdue ? 'Overdue' : statusLabel(selectedTask.status) }}
              </span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Due Date</span>
              <span :class="selectedTask.is_overdue && 'text-red'">{{ selectedTask.due_date_fmt || 'Not set' }}</span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Assigned To</span>
              <span>{{ selectedTask.assignee?.name || 'Unassigned' }}</span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Created By</span>
              <span>{{ selectedTask.creator?.name }}</span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Created</span>
              <span>{{ selectedTask.created_at }}</span>
            </div>
          </div>

          <div v-if="selectedTask.description" class="dp-description">
            {{ selectedTask.description }}
          </div>

          <!-- Quick status change -->
          <div class="dp-actions">
            <select v-model="quickStatus" @change="quickUpdateStatus" class="dtm-select sm">
              <option value="">Change Status…</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting_approval">Waiting Approval</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <button class="dtm-btn-ghost sm" @click="openTaskModal(selectedTask)">✏️ Edit Task</button>
            <button class="dtm-btn-ghost sm danger" @click="deleteTask(selectedTask.id)">🗑️ Delete</button>
          </div>

          <!-- Comments -->
          <div class="dp-comments">
            <div class="dp-section-title">Comments ({{ (selectedTask.comments || []).length }})</div>
            <div class="comment-list">
              <div v-for="c in (selectedTask.comments || [])" :key="c.id" class="comment-item">
                <div class="comment-avatar">{{ initials(c.user?.name || '?') }}</div>
                <div class="comment-body">
                  <div class="comment-meta">
                    <strong>{{ c.user?.name }}</strong>
                    <span class="comment-time">{{ formatDate(c.created_at) }}</span>
                    <button class="comment-del" @click="deleteComment(c.id)" title="Delete">✕</button>
                  </div>
                  <div class="comment-text">{{ c.comment }}</div>
                </div>
              </div>
              <div v-if="!(selectedTask.comments || []).length" class="no-comments">No comments yet.</div>
            </div>
            <div class="comment-input-row">
              <input v-model="newComment" @keydown.enter="addComment"
                placeholder="Add a comment… (Enter to send)" class="dtm-input" />
              <button @click="addComment" class="dtm-add-btn sm">Send</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Task Create/Edit Modal -->
    <transition name="modal-fade">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3>{{ editTask ? 'Edit Task' : 'New Task' }}</h3>
            <button class="dp-close" @click="showModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <label class="form-lbl">Task Title *</label>
              <input v-model="form.title" class="dtm-input" placeholder="Enter task title…" />
            </div>
            <div class="form-row two-col">
              <div>
                <label class="form-lbl">Department *</label>
                <select v-model="form.department_id" class="dtm-select full">
                  <option value="">Select department…</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.icon }} {{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="form-lbl">Assigned To</label>
                <select v-model="form.assigned_to" class="dtm-select full">
                  <option value="">Unassigned</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
            <div class="form-row two-col">
              <div>
                <label class="form-lbl">Priority *</label>
                <select v-model="form.priority" class="dtm-select full">
                  <option value="low">🟢 Low</option>
                  <option value="medium">🔵 Medium</option>
                  <option value="high">🟠 High</option>
                  <option value="critical">🔴 Critical</option>
                </select>
              </div>
              <div>
                <label class="form-lbl">Status</label>
                <select v-model="form.status" class="dtm-select full">
                  <option value="pending">Pending</option>
                  <option value="in_progress">In Progress</option>
                  <option value="waiting_approval">Waiting Approval</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>
            <div class="form-row two-col">
              <div>
                <label class="form-lbl">Due Date</label>
                <input type="date" v-model="form.due_date" class="dtm-input full" />
              </div>
              <div>
                <label class="form-lbl">Recurring</label>
                <select v-model="form.recurrence_type" class="dtm-select full">
                  <option value="">Not recurring</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <label class="form-lbl">Description</label>
              <textarea v-model="form.description" class="dtm-textarea" rows="4" placeholder="Task description…"></textarea>
            </div>
            <div class="form-row checkbox-row">
              <label class="checkbox-lbl">
                <input type="checkbox" v-model="form.requires_approval" />
                Requires approval before completing
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="showModal = false" class="dtm-btn-ghost">Cancel</button>
            <button @click="saveTask" class="dtm-add-btn" :disabled="saving">
              {{ saving ? 'Saving…' : (editTask ? 'Update Task' : 'Create Task') }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Notification Bell (top right) -->
    <div class="notif-bell-wrap">
      <button class="notif-bell" @click="notifOpen = !notifOpen">
        🔔
        <span v-if="notifCount > 0" class="notif-badge">{{ notifCount }}</span>
      </button>
      <div v-if="notifOpen" class="notif-dropdown">
        <div class="notif-header">
          Notifications
          <button @click="markAllRead" class="notif-mark-read">Mark all read</button>
        </div>
        <div v-for="n in notifications" :key="n.id" class="notif-item" :class="!n.read_at && 'unread'">
          <div class="notif-msg">{{ n.message }}</div>
          <div class="notif-time">{{ formatDate(n.created_at) }}</div>
        </div>
        <div v-if="!notifications.length" class="notif-empty">No notifications</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';
import api from '../api.js';

Chart.register(...registerables);

// ─── View state ───────────────────────────────────────────────────────────────
const views = [
  { id: 'dashboard', label: 'Dashboard',        icon: '📊' },
  { id: 'board',     label: 'Board',             icon: '🗂️' },
  { id: 'table',     label: 'Table',             icon: '📋' },
  { id: 'weekly',    label: 'Weekly Report',     icon: '📰' },
  { id: 'reports',   label: 'Reports',           icon: '📈' },
];
const currentView = ref('dashboard');

// ─── Data ─────────────────────────────────────────────────────────────────────
const departments   = ref([]);
const users         = ref([]);
const dashData      = ref({ stats: {}, recentTasks: [], byDepartment: [], byStatus: [], weeklyRate: [] });
const boardTasks    = ref([]);
const tableTasks    = ref([]);
const tableLoading  = ref(false);
const weeklyData    = ref({ week_start: '', week_end: '', departments: [] });
const reportData    = ref(null);
const notifications = ref([]);
const notifCount    = ref(0);
const notifOpen     = ref(false);

// ─── Filters ──────────────────────────────────────────────────────────────────
const boardFilters = reactive({ department_id: '' });
const tableFilters = reactive({ search: '', department_id: '', status: '', priority: '', assigned_to: '' });
const tableSort    = reactive({ field: 'created_at', dir: 'desc' });
const weeklyWeekStart = ref('');
const reportFilters   = reactive({ date_from: '', date_to: '' });

// ─── Modal / panel state ──────────────────────────────────────────────────────
const showModal    = ref(false);
const editTask     = ref(null);
const saving       = ref(false);
const selectedTask = ref(null);
const newComment   = ref('');
const quickStatus  = ref('');
const form         = reactive({
  title: '', description: '', department_id: '', assigned_to: '', priority: 'medium',
  status: 'pending', due_date: '', recurrence_type: '', requires_approval: false,
});

// ─── Drag & drop state ────────────────────────────────────────────────────────
const dragTask    = ref(null);
const dragOverCol = ref('');

// ─── Chart refs ───────────────────────────────────────────────────────────────
const deptChart   = ref(null);
const statusChart = ref(null);
const weeklyChart = ref(null);
let chartInstances = {};

// ─── Kanban columns ───────────────────────────────────────────────────────────
const kanbanCols = [
  { status: 'pending',          label: 'Pending',         color: '#94a3b8', icon: '⏳' },
  { status: 'in_progress',      label: 'In Progress',     color: '#3b82f6', icon: '🔄' },
  { status: 'waiting_approval', label: 'Waiting Approval',color: '#f59e0b', icon: '👀' },
  { status: 'completed',        label: 'Completed',       color: '#10b981', icon: '✅' },
];

// ─── Computed ─────────────────────────────────────────────────────────────────
const statCards = computed(() => {
  const s = dashData.value.stats || {};
  return [
    { label: 'Total Tasks',  value: s.total     || 0, color: '#3b82f6', icon: '📋' },
    { label: 'Pending',      value: s.pending   || 0, color: '#94a3b8', icon: '⏳' },
    { label: 'In Progress',  value: s.inProgress|| 0, color: '#3b82f6', icon: '🔄' },
    { label: 'Completed',    value: s.completed || 0, color: '#10b981', icon: '✅' },
    { label: 'Overdue',      value: s.overdue   || 0, color: '#ef4444', icon: '🚨' },
  ];
});

const weeklyMonthLabel = computed(() => {
  if (!weeklyData.value.week_start) return '';
  const parts = weeklyData.value.week_start.split('/');
  if (parts.length < 3) return '';
  const d = new Date(parts[2], parts[1] - 1, parts[0]);
  return d.toLocaleString('default', { month: 'long', year: 'numeric' }).toUpperCase();
});

const reportSummaryCards = computed(() => {
  if (!reportData.value) return [];
  const r = reportData.value;
  return [
    { label: 'Total',     value: r.total,     color: '#3b82f6' },
    { label: 'Completed', value: r.completed, color: '#10b981' },
    { label: 'Pending',   value: r.pending,   color: '#94a3b8' },
    { label: 'Overdue',   value: r.overdue,   color: '#ef4444' },
  ];
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function priorityColor(p) {
  return { low: '#10b981', medium: '#3b82f6', high: '#f59e0b', critical: '#ef4444' }[p] || '#94a3b8';
}

function statusLabel(s) {
  return { pending: 'Pending', in_progress: 'In Progress', waiting_approval: 'Waiting Approval', completed: 'Completed', cancelled: 'Cancelled' }[s] || s;
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
}

function formatDate(dt) {
  if (!dt) return '';
  const d = new Date(dt);
  if (isNaN(d)) return dt;
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function boardTasksByStatus(status) {
  return boardTasks.value.filter(t => t.status === status);
}

// ─── API calls ────────────────────────────────────────────────────────────────
async function loadMeta() {
  const [deptRes, userRes] = await Promise.all([
    api.get('/v1/dept/departments'),
    api.get('/v1/dept/users'),
  ]);
  departments.value = deptRes.data;
  users.value       = userRes.data;
}

async function loadDashboard() {
  const res = await api.get('/v1/dept/dashboard');
  dashData.value = res.data;
  await nextTick();
  buildCharts();
}

async function loadBoardTasks() {
  const params = {};
  if (boardFilters.department_id) params.department_id = boardFilters.department_id;
  const res = await api.get('/v1/dept/tasks', { params });
  boardTasks.value = res.data;
}

async function loadTableTasks() {
  tableLoading.value = true;
  try {
    const params = { sort_by: tableSort.field, sort_dir: tableSort.dir };
    if (tableFilters.search)        params.search        = tableFilters.search;
    if (tableFilters.department_id) params.department_id = tableFilters.department_id;
    if (tableFilters.status)        params.status        = tableFilters.status;
    if (tableFilters.priority)      params.priority      = tableFilters.priority;
    if (tableFilters.assigned_to)   params.assigned_to   = tableFilters.assigned_to;
    const res = await api.get('/v1/dept/tasks', { params });
    tableTasks.value = res.data;
  } finally {
    tableLoading.value = false;
  }
}

async function loadWeeklyData() {
  const params = weeklyWeekStart.value ? { week_start: weeklyWeekStart.value } : {};
  const res = await api.get('/v1/dept/weekly', { params });
  weeklyData.value = res.data;
}

async function loadReport() {
  const params = {};
  if (reportFilters.date_from) params.date_from = reportFilters.date_from;
  if (reportFilters.date_to)   params.date_to   = reportFilters.date_to;
  const res = await api.get('/v1/dept/report', { params });
  reportData.value = res.data;
}

async function loadNotifications() {
  const res = await api.get('/v1/dept/notifications');
  notifications.value = res.data.notifications;
  notifCount.value    = res.data.unread;
}

async function markAllRead() {
  await api.post('/v1/dept/notifications/read');
  notifCount.value = 0;
  notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
}

// ─── Task CRUD ────────────────────────────────────────────────────────────────
function openTaskModal(task = null) {
  editTask.value = task;
  if (task) {
    Object.assign(form, {
      title: task.title, description: task.description || '', department_id: task.department_id,
      assigned_to: task.assigned_to || '', priority: task.priority, status: task.status,
      due_date: task.due_date || '', recurrence_type: task.recurrence_type || '',
      requires_approval: task.requires_approval,
    });
  } else {
    Object.assign(form, {
      title: '', description: '', department_id: '', assigned_to: '', priority: 'medium',
      status: 'pending', due_date: '', recurrence_type: '', requires_approval: false,
    });
  }
  showModal.value = true;
}

async function saveTask() {
  if (!form.title.trim() || !form.department_id) {
    alert('Title and Department are required.');
    return;
  }
  saving.value = true;
  try {
    const payload = { ...form };
    if (!payload.assigned_to)    delete payload.assigned_to;
    if (!payload.due_date)       delete payload.due_date;
    if (!payload.recurrence_type) { payload.is_recurring = false; delete payload.recurrence_type; }
    else payload.is_recurring = true;

    if (editTask.value) {
      await api.put(`/v1/dept/tasks/${editTask.value.id}`, payload);
    } else {
      await api.post('/v1/dept/tasks', payload);
    }
    showModal.value = false;
    refreshCurrentView();
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to save task.');
  } finally {
    saving.value = false;
  }
}

async function deleteTask(id) {
  if (!confirm('Delete this task?')) return;
  await api.delete(`/v1/dept/tasks/${id}`);
  if (selectedTask.value?.id === id) selectedTask.value = null;
  refreshCurrentView();
}

async function openTaskDetail(task) {
  const res = await api.get(`/v1/dept/tasks/${task.id}`);
  selectedTask.value = res.data;
  quickStatus.value  = '';
}

async function quickUpdateStatus() {
  if (!quickStatus.value || !selectedTask.value) return;
  await api.put(`/v1/dept/tasks/${selectedTask.value.id}/status`, { status: quickStatus.value });
  selectedTask.value.status = quickStatus.value;
  quickStatus.value = '';
  refreshCurrentView();
}

// ─── Comments ─────────────────────────────────────────────────────────────────
async function addComment() {
  const text = newComment.value.trim();
  if (!text || !selectedTask.value) return;
  const res = await api.post(`/v1/dept/tasks/${selectedTask.value.id}/comments`, { comment: text });
  selectedTask.value.comments = [res.data, ...(selectedTask.value.comments || [])];
  newComment.value = '';
}

async function deleteComment(commentId) {
  if (!confirm('Delete this comment?')) return;
  await api.delete(`/v1/dept/tasks/${selectedTask.value.id}/comments/${commentId}`);
  selectedTask.value.comments = selectedTask.value.comments.filter(c => c.id !== commentId);
}

// ─── Drag & drop ──────────────────────────────────────────────────────────────
function onDragStart(event, task) {
  dragTask.value = task;
  event.dataTransfer.setData('taskId', task.id);
  event.dataTransfer.effectAllowed = 'move';
}

async function onDrop(event, targetStatus) {
  dragOverCol.value = '';
  const task = dragTask.value;
  if (!task || task.status === targetStatus) return;
  await api.put(`/v1/dept/tasks/${task.id}/status`, { status: targetStatus });
  const t = boardTasks.value.find(t => t.id === task.id);
  if (t) t.status = targetStatus;
  dragTask.value = null;
}

// ─── Sorting ──────────────────────────────────────────────────────────────────
function toggleSort(field) {
  if (tableSort.field === field) {
    tableSort.dir = tableSort.dir === 'asc' ? 'desc' : 'asc';
  } else {
    tableSort.field = field;
    tableSort.dir   = 'asc';
  }
  loadTableTasks();
}

function clearTableFilters() {
  Object.assign(tableFilters, { search: '', department_id: '', status: '', priority: '', assigned_to: '' });
  loadTableTasks();
}

// ─── Export / Print ───────────────────────────────────────────────────────────
function exportTable(type) { window.print(); }
function printWeekly() { window.print(); }
function printReport() { window.print(); }

// ─── Debounce ─────────────────────────────────────────────────────────────────
let searchTimer = null;
function debouncedLoadTable() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadTableTasks, 350);
}

// ─── Refresh current view ─────────────────────────────────────────────────────
function refreshCurrentView() {
  if (currentView.value === 'dashboard') loadDashboard();
  if (currentView.value === 'board')     loadBoardTasks();
  if (currentView.value === 'table')     loadTableTasks();
  if (currentView.value === 'weekly')    loadWeeklyData();
}

// ─── Charts (Chart.js) ───────────────────────────────────────────────────────
function destroyCharts() {
  Object.values(chartInstances).forEach(c => c?.destroy());
  chartInstances = {};
}

function buildCharts() {
  destroyCharts();

  // By Department
  if (deptChart.value) {
    const labels = dashData.value.byDepartment.map(d => d.name);
    const totals  = dashData.value.byDepartment.map(d => d.total);
    const colors  = dashData.value.byDepartment.map(d => d.color);
    chartInstances.dept = new Chart(deptChart.value, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Tasks', data: totals, backgroundColor: colors.map(c => c+'99'), borderColor: colors, borderWidth: 2 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } },
    });
  }

  // By Status
  if (statusChart.value) {
    const byStatus = dashData.value.byStatus || [];
    chartInstances.status = new Chart(statusChart.value, {
      type: 'doughnut',
      data: {
        labels: byStatus.map(s => s.label),
        datasets: [{ data: byStatus.map(s => s.value), backgroundColor: byStatus.map(s => s.color), borderWidth: 2 }],
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
    });
  }

  // Weekly rate
  if (weeklyChart.value) {
    const wr = dashData.value.weeklyRate || [];
    chartInstances.weekly = new Chart(weeklyChart.value, {
      type: 'line',
      data: {
        labels: wr.map(w => w.label),
        datasets: [
          { label: 'Completed', data: wr.map(w => w.completed), borderColor: '#10b981', backgroundColor: '#10b98122', fill: true, tension: 0.4 },
          { label: 'Created',   data: wr.map(w => w.created),   borderColor: '#3b82f6', backgroundColor: '#3b82f622', fill: false, tension: 0.4 },
        ],
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } },
    });
  }
}

// ─── Watch view changes ───────────────────────────────────────────────────────
watch(currentView, (v) => {
  if (v === 'dashboard') loadDashboard();
  if (v === 'board')     loadBoardTasks();
  if (v === 'table')     loadTableTasks();
  if (v === 'weekly')    loadWeeklyData();
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadMeta();
  await loadDashboard();
  loadNotifications();
});
</script>

<style scoped>
/* ── Layout ───────────────────────────────────────────────────────────────── */
.dtm-wrap { display: flex; min-height: 100vh; background: #f8fafc; position: relative; }

/* ── Left sub-sidebar ─────────────────────────────────────────────────────── */
.dtm-nav { width: 210px; background: #1e293b; display: flex; flex-direction: column; min-height: 100vh; position: sticky; top: 0; height: 100vh; flex-shrink: 0; }
.dtm-nav-header { display: flex; align-items: center; gap: 10px; padding: 22px 18px 16px; border-bottom: 1px solid rgba(255,255,255,0.06); color: #f1f5f9; font-weight: 700; font-size: 15px; }
.dtm-nav-icon { font-size: 18px; }
.dtm-nav-links { flex: 1; padding: 14px 10px; display: flex; flex-direction: column; gap: 2px; }
.dtm-nav-btn { width: 100%; background: none; border: none; color: #64748b; display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; font-size: 13.5px; font-weight: 500; cursor: pointer; text-align: left; transition: background 0.15s, color 0.15s; }
.dtm-nav-btn:hover { background: rgba(255,255,255,0.06); color: #cbd5e1; }
.dtm-nav-btn.active { background: rgba(59,130,246,0.18); color: #93c5fd; font-weight: 600; }
.dtm-nav-link-icon { font-size: 15px; width: 20px; text-align: center; }
.dtm-nav-footer { padding: 14px 12px; border-top: 1px solid rgba(255,255,255,0.06); }

/* ── Main content ─────────────────────────────────────────────────────────── */
.dtm-content { flex: 1; padding: 28px 32px; max-width: calc(100vw - 450px); overflow: auto; }
.dtm-section { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* ── Page header ──────────────────────────────────────────────────────────── */
.dtm-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.dtm-page-header h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0; }
.dtm-sub { color: #64748b; font-size: 13px; display: block; margin-top: 3px; }
.header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* ── Buttons & inputs ────────────────────────────────────────────────────── */
.dtm-add-btn { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.dtm-add-btn:hover { background: #2563eb; }
.dtm-add-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.dtm-add-btn.sm { padding: 7px 14px; font-size: 13px; }
.dtm-btn-ghost { background: none; border: 1px solid #e2e8f0; color: #475569; border-radius: 8px; padding: 7px 14px; font-size: 13px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.dtm-btn-ghost:hover { background: #f1f5f9; border-color: #cbd5e1; }
.dtm-btn-ghost.sm { padding: 6px 10px; font-size: 12px; }
.dtm-btn-ghost.danger { color: #ef4444; border-color: #fca5a5; }
.dtm-btn-ghost.danger:hover { background: #fef2f2; }
.dtm-btn-icon { background: none; border: none; cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 14px; transition: background 0.15s; }
.dtm-btn-icon:hover { background: #f1f5f9; }
.dtm-btn-icon.danger:hover { background: #fef2f2; }
.dtm-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 13.5px; color: #0f172a; background: #fff; outline: none; transition: border-color 0.15s; }
.dtm-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.dtm-input.sm { width: auto; }
.dtm-input.full { width: 100%; }
.dtm-textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 13.5px; color: #0f172a; background: #fff; outline: none; resize: vertical; transition: border-color 0.15s; font-family: inherit; }
.dtm-textarea:focus { border-color: #3b82f6; }
.dtm-select { border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 12px; font-size: 13.5px; color: #374151; background: #fff; outline: none; cursor: pointer; }
.dtm-select:focus { border-color: #3b82f6; }
.dtm-select.sm { font-size: 13px; padding: 6px 10px; }
.dtm-select.full { width: 100%; }

/* ── Stat cards ───────────────────────────────────────────────────────────── */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 14px; padding: 18px 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-top: 3px solid transparent; transition: box-shadow 0.2s; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
.stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-val { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
.stat-lbl { font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500; }

/* ── Charts ───────────────────────────────────────────────────────────────── */
.chart-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px; }
.chart-card { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.chart-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 16px; }

/* ── Recent tasks (dashboard) ─────────────────────────────────────────────── */
.dash-recent { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.section-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 14px; }
.task-rows { display: flex; flex-direction: column; gap: 2px; }
.task-row-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: background 0.15s; }
.task-row-item:hover { background: #f8fafc; }
.task-row-title { flex: 1; font-size: 13.5px; color: #1e293b; font-weight: 500; }
.task-row-date { font-size: 12px; color: #94a3b8; }

/* ── Kanban board ─────────────────────────────────────────────────────────── */
.kanban-board { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; min-height: 500px; }
@media (max-width: 1200px) { .kanban-board { grid-template-columns: repeat(2, 1fr); } }
.kanban-col { background: #f1f5f9; border-radius: 14px; padding: 14px; min-height: 400px; transition: background 0.15s; }
.kanban-col.drag-over { background: #e0f2fe; }
.kanban-col-header { display: flex; align-items: center; gap: 8px; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 2px solid; }
.kanban-col-icon { font-size: 15px; }
.kanban-col-title { font-size: 13px; font-weight: 700; color: #374151; flex: 1; }
.kanban-col-count { background: #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.kanban-cards { display: flex; flex-direction: column; gap: 10px; }
.kanban-card { background: #fff; border-radius: 10px; padding: 14px; cursor: grab; box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: box-shadow 0.15s, transform 0.15s; }
.kanban-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.12); transform: translateY(-2px); }
.kanban-card:active { cursor: grabbing; }
.kc-top { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
.kc-title { font-size: 13.5px; font-weight: 600; color: #1e293b; margin-bottom: 6px; line-height: 1.4; }
.kc-dept { font-size: 11.5px; color: #64748b; margin-bottom: 10px; }
.kc-footer { display: flex; align-items: center; justify-content: space-between; }
.kc-date { font-size: 11.5px; color: #94a3b8; }
.kc-assignee { width: 26px; height: 26px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; }
.kanban-empty { text-align: center; color: #cbd5e1; font-size: 13px; padding: 24px 0; border: 2px dashed #e2e8f0; border-radius: 10px; }
.overdue-badge { font-size: 10px; background: #fef2f2; color: #ef4444; padding: 2px 7px; border-radius: 20px; font-weight: 600; }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; padding: 14px 16px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }

/* ── Table ────────────────────────────────────────────────────────────────── */
.table-wrap { background: #fff; border-radius: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); overflow: auto; }
.dtm-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.dtm-table.compact { font-size: 12.5px; }
.dtm-table thead tr { border-bottom: 2px solid #f1f5f9; }
.dtm-table th { padding: 13px 16px; text-align: left; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; cursor: pointer; white-space: nowrap; user-select: none; }
.dtm-table th:hover { color: #374151; }
.dtm-table td { padding: 12px 16px; color: #374151; border-bottom: 1px solid #f8fafc; }
.table-row { cursor: pointer; transition: background 0.12s; }
.table-row:hover { background: #f8fafc; }
.loading-cell, .empty-cell { text-align: center; color: #94a3b8; padding: 32px; }

/* ── Chips / tags ─────────────────────────────────────────────────────────── */
.priority-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; margin-right: 4px; }
.priority-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; text-transform: capitalize; }
.priority-badge.sm { font-size: 10.5px; }
.dept-tag { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
.dept-tag.sm { font-size: 11px; padding: 2px 8px; }
.status-chip { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: capitalize; white-space: nowrap; }
.st-pending          { background: #f1f5f9; color: #64748b; }
.st-in_progress      { background: #dbeafe; color: #1d4ed8; }
.st-waiting_approval { background: #fef3c7; color: #b45309; }
.st-completed        { background: #d1fae5; color: #065f46; }
.st-cancelled        { background: #f3f4f6; color: #9ca3af; text-decoration: line-through; }
.st-overdue          { background: #fee2e2 !important; color: #b91c1c !important; }
.text-red { color: #ef4444 !important; }

/* ── Weekly report ────────────────────────────────────────────────────────── */
.weekly-report { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.wr-header { margin-bottom: 24px; text-align: center; }
.wr-title { font-size: 20px; font-weight: 800; color: #0f172a; }
.wr-meta { display: flex; justify-content: center; gap: 32px; margin-top: 8px; font-size: 13.5px; color: #475569; }
.wr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; }
.wr-dept-block { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.wr-dept-header { padding: 10px 16px; font-size: 13px; font-weight: 800; letter-spacing: 0.5px; }
.wr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.wr-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.wr-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #374151; }
.wr-date { white-space: nowrap; font-weight: 600; color: #1e293b; }
.wr-overdue td { background: #fef2f2; }
.wr-status { font-size: 10.5px; font-weight: 700; }
.wr-empty { text-align: center; color: #94a3b8; padding: 12px; font-style: italic; }

/* ── Reports ──────────────────────────────────────────────────────────────── */
.report-filters { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 24px; padding: 16px 20px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.report-filters label { font-size: 13px; color: #374151; display: flex; align-items: center; gap: 8px; }
.report-content { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.report-summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.rsc { border: 2px solid; border-radius: 12px; padding: 16px 20px; text-align: center; }
.rsc-val { font-size: 28px; font-weight: 800; }
.rsc-lbl { font-size: 12px; font-weight: 600; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.report-dept-section { margin-bottom: 24px; }
.rds-header { border-left: 4px solid; padding: 10px 14px; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #475569; margin-bottom: 10px; border-radius: 0 8px 8px 0; }
.dtm-empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.es-icon { font-size: 48px; margin-bottom: 16px; }

/* ── Detail Panel ─────────────────────────────────────────────────────────── */
.detail-panel-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 100; display: flex; justify-content: flex-end; }
.detail-panel { width: 460px; max-width: 100vw; background: #fff; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: -4px 0 20px rgba(0,0,0,0.12); }
.dp-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 22px 22px 16px; border-bottom: 1px solid #f1f5f9; gap: 12px; }
.dp-dept-tag { display: inline-block; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-bottom: 8px; }
.dp-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.35; }
.dp-close { background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px; flex-shrink: 0; }
.dp-close:hover { background: #f1f5f9; color: #374151; }
.dp-meta-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 22px; border-bottom: 1px solid #f1f5f9; }
.dp-meta-item { display: flex; flex-direction: column; gap: 4px; }
.dp-meta-lbl { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.dp-description { padding: 16px 22px; font-size: 13.5px; color: #475569; line-height: 1.6; border-bottom: 1px solid #f1f5f9; white-space: pre-wrap; }
.dp-actions { display: flex; gap: 8px; padding: 14px 22px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; align-items: center; }
.dp-comments { flex: 1; padding: 16px 22px; display: flex; flex-direction: column; }
.dp-section-title { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 14px; }
.comment-list { flex: 1; display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; max-height: 320px; overflow-y: auto; }
.comment-item { display: flex; gap: 10px; }
.comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.comment-body { flex: 1; background: #f8fafc; border-radius: 10px; padding: 10px 12px; }
.comment-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 12px; }
.comment-meta strong { color: #374151; }
.comment-time { color: #94a3b8; flex: 1; }
.comment-del { background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: 12px; padding: 0; }
.comment-del:hover { color: #ef4444; }
.comment-text { font-size: 13px; color: #475569; line-height: 1.5; }
.no-comments { color: #94a3b8; font-size: 13px; text-align: center; padding: 20px; }
.comment-input-row { display: flex; gap: 8px; }

/* ── Modal ────────────────────────────────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-box { background: #fff; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9; }
.modal-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }
.modal-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-lbl { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
.checkbox-row { flex-direction: row; align-items: center; }
.checkbox-lbl { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #374151; cursor: pointer; }
.checkbox-lbl input { width: 16px; height: 16px; }

/* ── Notifications ────────────────────────────────────────────────────────── */
.notif-bell-wrap { position: fixed; top: 16px; right: 20px; z-index: 300; }
.notif-bell { background: #fff; border: 1px solid #e2e8f0; border-radius: 50%; width: 42px; height: 42px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.notif-bell:hover { background: #f8fafc; }
.notif-badge { position: absolute; top: -4px; right: -4px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; }
.notif-dropdown { position: absolute; right: 0; top: 50px; width: 320px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); overflow: hidden; }
.notif-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; font-size: 13px; font-weight: 700; color: #374151; border-bottom: 1px solid #f1f5f9; }
.notif-mark-read { background: none; border: none; color: #3b82f6; font-size: 12px; cursor: pointer; }
.notif-item { padding: 12px 16px; border-bottom: 1px solid #f8fafc; transition: background 0.12s; }
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #eff6ff; }
.notif-msg { font-size: 13px; color: #374151; margin-bottom: 4px; }
.notif-time { font-size: 11px; color: #94a3b8; }
.notif-empty { text-align: center; color: #94a3b8; font-size: 13px; padding: 24px; }

/* ── Transitions ──────────────────────────────────────────────────────────── */
.slide-panel-enter-active, .slide-panel-leave-active { transition: opacity 0.2s ease; }
.slide-panel-enter-from, .slide-panel-leave-to { opacity: 0; }
.slide-panel-enter-active .detail-panel, .slide-panel-leave-active .detail-panel { transition: transform 0.25s ease; }
.slide-panel-enter-from .detail-panel, .slide-panel-leave-to .detail-panel { transform: translateX(100%); }
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* ── Print styles ─────────────────────────────────────────────────────────── */
@media print {
  .no-print, .notif-bell-wrap, .dtm-nav, .header-actions, .filter-bar { display: none !important; }
  .dtm-wrap { display: block; }
  .dtm-content { padding: 0; max-width: 100%; }
  .weekly-report, .report-content { box-shadow: none; border-radius: 0; }
  .wr-grid { grid-template-columns: repeat(2, 1fr); }
  body { background: #fff; }
}
</style>
