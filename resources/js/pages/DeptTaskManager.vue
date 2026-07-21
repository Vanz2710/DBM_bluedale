<template>
  <div class="page">

    <!-- Page Header -->
    <div class="page-header-row">
      <div>
        <h1 class="page-title">Task Manager</h1>
        <p class="page-subtitle">{{ isAdmin ? 'Department tasks, board view, and reports' : 'My assigned tasks' }}</p>
      </div>
      <div class="page-header-actions">
        <router-link v-if="isAdmin" class="btn-ghost" :to="{ path: '/admin', query: { tab: 'departments' } }">
          <span v-html="ICO.settings"></span>
          Manage Departments
        </router-link>
        <button v-if="can('manage dept-tasks')" class="btn-primary" @click="openTaskModal()">
          <span v-html="ICO.plus"></span>
          New Task
        </button>
      </div>
    </div>

    <!-- Tab Bar -->
    <div class="tab-bar">
      <button v-for="v in views" :key="v.id" @click="currentView = v.id"
        :class="['tab-btn', currentView === v.id && 'active']"
        :data-tour="'deptask-tab-' + v.id">
        <span class="tab-icon" v-html="v.icon"></span>
        {{ v.label }}
      </button>
    </div>

    <!-- ── Task Notification Strip ──────────────────────────────────────────── -->
    <div v-if="unreadNotifs.length" class="task-notif-strip">
      <div v-for="n in unreadNotifs.slice(0, 2)" :key="n.id" class="tns-row">
        <span class="ntag" :class="'ntag-'+n.type">{{ notifTypeLabel(n.type) }}</span>
        <span class="tns-msg">{{ n.message }}</span>
        <button class="btn-ghost sm" @click.stop="openNotifTask(n)">View task</button>
        <button class="notif-dismiss" @click.stop="dismissNotif(n)" title="Dismiss">×</button>
      </div>
      <div v-if="unreadNotifs.length > 2" class="tns-more">
        <span>+{{ unreadNotifs.length - 2 }} more unread</span>
        <button class="notif-mark-read" @click="markAllRead">Dismiss all</button>
      </div>
    </div>

    <!-- ── My Work (user list) ───────────────────────────────────────────────── -->
    <div v-if="currentView === 'mywork'" class="dtm-section">
      <div v-if="boardLoading" class="view-loading"><div class="spinner"></div></div>
      <template v-else>
        <div class="mw-summary">
          <span class="mw-stat" :class="buckets.overdue.length && 'mw-stat--danger'">
            <b>{{ buckets.overdue.length }}</b> overdue
          </span>
          <span class="mw-dot-sep"></span>
          <span class="mw-stat"><b>{{ buckets.today.length }}</b> due today</span>
          <span class="mw-dot-sep"></span>
          <span class="mw-stat"><b>{{ buckets.thisWeek.length }}</b> this week</span>
        </div>

        <div v-if="!activeCount && !buckets.done.length" class="empty-state">
          <span class="empty-icon" v-html="ICO.check"></span>
          <p>No tasks assigned to you yet.</p>
        </div>
        <div v-else-if="!activeCount" class="empty-state">
          <span class="empty-icon" v-html="ICO.check"></span>
          <p>You're all caught up — nothing open right now.</p>
        </div>

        <div v-for="grp in bucketGroups" :key="grp.key" v-show="grp.tasks.length" class="mw-bucket">
          <div class="mw-bucket-head">
            <span class="mw-bucket-title" :class="grp.tone">{{ grp.label }}</span>
            <span class="mw-bucket-count">{{ grp.tasks.length }}</span>
          </div>
          <div class="mw-list">
            <div v-for="task in grp.tasks" :key="task.id" class="mw-row" @click="openTaskDetail(task)">
              <button class="mw-check" @click.stop="completeTask(task)" title="Mark complete" aria-label="Mark complete">
                <span class="mw-check-ring"></span>
              </button>
              <span class="mw-prio" :style="{ background: priorityColor(task.priority) }" :title="task.priority"></span>
              <div class="mw-main">
                <div class="mw-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</div>
                <div class="mw-sub">
                  <span v-if="task.department" class="mw-dept" :style="{ color: task.department.color }">{{ task.department.name }}</span>
                  <span v-if="task.due_date_fmt" class="mw-due" :class="grp.key === 'overdue' && 'text-danger'">{{ task.due_date_fmt }}</span>
                  <span v-if="task.status === 'in_progress'" class="mw-state">In Progress</span>
                </div>
              </div>
              <div class="mw-action" @click.stop>
                <button v-if="task.status === 'pending'" class="btn-ghost sm" @click="advanceStatus(task)">Start</button>
                <button v-else-if="task.status === 'in_progress'" class="btn-primary sm" @click="completeTask(task)">Complete</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Done (collapsed) -->
        <div v-if="buckets.done.length" class="mw-bucket">
          <button class="mw-bucket-head mw-bucket-toggle" @click="doneOpen = !doneOpen">
            <span class="mw-toggle-ico" :class="doneOpen && 'open'" v-html="ICO.chevronR"></span>
            <span class="mw-bucket-title">Done</span>
            <span class="mw-bucket-count">{{ buckets.done.length }}</span>
          </button>
          <div v-show="doneOpen" class="mw-list">
            <div v-for="task in buckets.done" :key="task.id" class="mw-row mw-row--done" @click="openTaskDetail(task)">
              <span class="mw-check mw-check--done" v-html="ICO.check"></span>
              <div class="mw-main"><div class="mw-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</div></div>
              <span v-if="task.due_date_fmt" class="mw-due">{{ task.due_date_fmt }}</span>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Dashboard ─────────────────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'dashboard'" class="dtm-section">

      <div v-if="dashLoading" class="view-loading"><div class="spinner"></div></div>
      <template v-else>
      <div class="stat-grid">
        <div v-for="s in statCards" :key="s.label"
          :class="['stat-card', 'accent-'+s.colorClass, s.filterKey && 'stat-card--link']"
          @click="statCardClick(s)">
          <div class="stat-icon-wrap" :class="'icon-'+s.colorClass" v-html="s.icon"></div>
          <div class="stat-body">
            <div class="stat-val">{{ s.value }}</div>
            <div class="stat-lbl">{{ s.label }}</div>
          </div>
        </div>
      </div>

      <div class="chart-row">
        <div v-if="isAdmin" class="card chart-card">
          <div class="chart-title">Tasks by Department</div>
          <div v-if="!dashData.stats?.total" class="chart-empty">No data yet</div>
          <canvas v-else ref="deptChart" height="220"></canvas>
        </div>
        <div class="card chart-card">
          <div class="chart-title">Tasks by Status</div>
          <div v-if="!dashData.stats?.total" class="chart-empty">No data yet</div>
          <canvas v-else ref="statusChart" height="220"></canvas>
        </div>
        <div v-if="isAdmin" class="card chart-card">
          <div class="chart-title">Weekly Completion Rate</div>
          <div v-if="!dashData.stats?.total" class="chart-empty">No data yet</div>
          <canvas v-else ref="weeklyChart" height="220"></canvas>
        </div>
      </div>

      <div class="card recent-card">
        <div class="card-section-title">Active Tasks</div>
        <div class="task-rows">
          <div v-for="t in dashData.recentTasks" :key="t.id"
            class="task-row-item" @click="openTaskDetail(t)">
            <span class="priority-dot" :style="{background: priorityColor(t.priority)}"></span>
            <span v-if="t.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>
            <span class="task-row-title">{{ t.title }}</span>
            <span class="dept-pill" :style="{background: t.department?.color+'22', color: t.department?.color}">
              {{ t.department?.name }}
            </span>
            <span :class="['status-badge', 'st-'+t.status, t.is_overdue && 'st-overdue']">
              {{ t.is_overdue ? 'Overdue' : statusLabel(t.status) }}
            </span>
            <span class="task-row-date">{{ t.due_date_fmt || '—' }}</span>
          </div>
          <div v-if="!(dashData.recentTasks || []).length" class="empty-row">No tasks yet.</div>
        </div>
      </div>
      </template>
    </div>

    <!-- ── Board (Kanban) ─────────────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'board'" class="dtm-section">
      <div class="section-toolbar">
        <div v-if="isAdmin" class="scope-toggle">
          <button :class="['scope-btn', boardFilters.assigned_to ? 'active' : '']"
            @click="setBoardScope(true)">My Tasks</button>
          <button :class="['scope-btn', !boardFilters.assigned_to ? 'active' : '']"
            @click="setBoardScope(false)">All Tasks</button>
        </div>
        <select v-model="boardFilters.department_id" @change="loadBoardTasks" class="field-input sm">
          <option value="">All Departments</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <button class="btn-ghost sm board-toggle-closed" :class="showClosed && 'active'" @click="toggleShowClosed">
          <span v-html="showClosed ? ICO.eyeOff : ICO.eye"></span>
          {{ showClosed ? 'Hide closed' : 'Show closed' }}
        </button>
      </div>

      <div v-if="boardLoading" class="view-loading">
        <div class="spinner"></div>
      </div>
      <div v-else class="kanban-board" :style="{ gridTemplateColumns: 'repeat(' + kanbanCols.length + ', minmax(0, 1fr))' }">
        <div v-for="col in kanbanCols" :key="col.status" class="kanban-col"
          @dragover.prevent
          @dragenter.prevent="dragOverCol = col.status"
          @drop="onDrop($event, col.status)"
          :class="dragOverCol === col.status && 'drag-over'">
          <div class="kanban-col-header" :style="{borderTopColor: col.color}">
            <span class="col-dot" :style="{background: col.color}"></span>
            <span class="kanban-col-title">{{ col.label }}</span>
            <span class="kanban-col-count">{{ boardByStatus[col.status].length }}</span>
          </div>
          <div class="kanban-cards">
            <div v-for="task in boardByStatus[col.status]" :key="task.id"
              class="kanban-card" draggable="true"
              @dragstart="onDragStart($event, task)"
              @dragend="dragOverCol = ''"
              @click="openTaskDetail(task)">
              <div class="kc-top">
                <span class="kc-priority" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                  {{ task.priority }}
                </span>
                <span v-if="task.is_overdue" class="overdue-badge">Overdue</span>
              </div>
              <div class="kc-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</div>
              <div class="kc-dept">
                <span class="dept-color-dot" :style="{background: task.department?.color}"></span>
                <span :style="{color: task.department?.color}">{{ task.department?.name }}</span>
              </div>
              <div class="kc-footer">
                <span class="kc-date" :class="task.is_overdue && 'text-danger'">
                  {{ task.due_date_fmt || 'No date' }}
                </span>
                <span class="kc-assignee" v-if="task.assignee">
                  {{ initials(task.assignee.name) }}
                </span>
              </div>
            </div>
            <div v-if="boardByStatus[col.status].length === 0" class="kanban-empty">
              No tasks yet
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Calendar ──────────────────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'calendar'" class="dtm-section">
      <div class="section-toolbar">
        <div v-if="calendarViewMode === 'list'" class="cal-nav">
          <button class="btn-icon" @click="calendarListYearNav(-1)" aria-label="Previous year" v-html="ICO.chevronL"></button>
          <span class="cal-nav-label">{{ calendarListYear }}</span>
          <button class="btn-icon" @click="calendarListYearNav(1)" aria-label="Next year" v-html="ICO.chevronR"></button>
        </div>
        <div v-else-if="calendarViewMode === 'grid'" class="cal-nav">
          <button class="btn-icon" @click="calendarNav(-1)" aria-label="Previous month" v-html="ICO.chevronL"></button>
          <span class="cal-nav-label">{{ calendarMonthLabel }}</span>
          <button class="btn-icon" @click="calendarNav(1)" aria-label="Next month" v-html="ICO.chevronR"></button>
        </div>
        <div v-if="isAdmin" class="scope-toggle">
          <button :class="['scope-btn', boardFilters.assigned_to ? 'active' : '']"
            @click="setBoardScope(true)">My Tasks</button>
          <button :class="['scope-btn', !boardFilters.assigned_to ? 'active' : '']"
            @click="setBoardScope(false)">All Tasks</button>
        </div>
        <select v-model="boardFilters.department_id" @change="loadBoardTasks" class="field-input sm">
          <option value="">All Departments</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <button class="btn-ghost sm board-toggle-closed" :class="showClosed && 'active'" @click="toggleShowClosed">
          <span v-html="showClosed ? ICO.eyeOff : ICO.eye"></span>
          {{ showClosed ? 'Hide closed' : 'Show closed' }}
        </button>
        <button v-if="calendarViewMode !== 'important'" class="btn-ghost sm" @click="openCalendarExportModal()">
          <span v-html="ICO.download"></span> Export
        </button>
        <div class="pv-toggle">
          <button class="pv-btn" :class="calendarViewMode === 'grid' && 'active'" @click="calendarViewMode = 'grid'" title="Grid view" v-html="ICO.calendarDays"></button>
          <button class="pv-btn" :class="calendarViewMode === 'list' && 'active'" @click="calendarViewMode = 'list'" title="List view" v-html="ICO.list"></button>
          <button class="pv-btn" :class="calendarViewMode === 'important' && 'active'" @click="calendarViewMode = 'important'" title="Important tasks" v-html="ICO.star"></button>
        </div>
      </div>

      <div v-if="calendarViewMode !== 'important'" class="cal-legend">
        <span class="cal-legend-hint">Colour = department:</span>
        <span v-for="d in departments" :key="d.id" class="cal-legend-item">
          <span class="cal-legend-dot" :style="{ background: d.color }"></span>{{ d.name }}
        </span>
      </div>

      <div v-if="boardLoading" class="view-loading">
        <div class="spinner"></div>
      </div>
      <div v-else-if="calendarViewMode === 'important'" class="pg-block">
        <div class="pg-task-area">
          <div class="pg-col-labels">
            <span>Task</span>
            <span>Department</span>
            <span>Due Date</span>
            <span>Status</span>
          </div>
          <div v-for="task in calendarImportantTasks" :key="task.id"
            class="pg-task-row"
            :class="task.is_overdue && 'pg-task-row--ov'"
            :style="{ borderLeftColor: priorityColor(task.priority) }"
            @click="openTaskDetail(task)">
            <div class="pg-task-info">
              <span class="pg-task-name">{{ task.title }}</span>
              <span v-if="task.description" class="pg-task-hint">{{ task.description.slice(0, 80) }}{{ task.description.length > 80 ? '…' : '' }}</span>
            </div>
            <div class="pg-task-dept-cell">
              <span v-if="task.department" class="dept-pill" :style="{ background: task.department.color + '22', color: task.department.color }">
                {{ task.department.name }}
              </span>
              <span v-else class="pg-task-empty">—</span>
            </div>
            <span class="pg-task-due" :class="task.is_overdue && 'text-danger'">
              {{ task.due_date_fmt || '—' }}
            </span>
            <span :class="['status-badge', 'st-' + task.status, task.is_overdue && 'st-overdue']">
              {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
            </span>
          </div>
          <div v-if="!calendarImportantTasks.length" class="pg-no-tasks">No important tasks flagged yet.</div>
        </div>
      </div>
      <div v-else-if="calendarViewMode === 'list'" class="cal-year">
        <div v-for="mo in calendarYearMonths" :key="mo.key" class="cal-year-month">
          <div class="cal-year-month-head">{{ mo.name }}</div>
          <div class="cal-year-month-rows">
            <div v-for="task in mo.tasks" :key="task.id"
              class="cal-year-row" :class="task.is_overdue && 'cal-year-row--overdue'"
              :style="{ borderLeftColor: priorityColor(task.priority), background: task.department ? task.department.color + '14' : undefined }"
              :title="task.title + (task.department ? ' · ' + task.department.name : '') + (task.assignee ? ' · ' + task.assignee.name : '')"
              @click="openTaskDetail(task)">
              <span class="cal-year-row-date">{{ dayOfMonth(task.due_date) }}</span>
              <span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>
              <span class="cal-year-row-title">{{ task.title }}</span>
            </div>
            <div v-if="!mo.tasks.length" class="cal-year-empty">No tasks</div>
          </div>
        </div>
      </div>
      <div v-else class="cal-wrap">
        <div class="cal-head-row">
          <span v-for="d in CAL_WEEKDAYS" :key="d">{{ d }}</span>
        </div>
        <div class="cal-grid">
          <div v-for="day in calendarDays" :key="day.key"
            class="cal-day"
            :class="{ 'cal-day--muted': !day.inMonth, 'cal-day--today': day.isToday, 'cal-day--addable': can('manage dept-tasks') }"
            :title="can('manage dept-tasks') ? 'Click to add a task due ' + day.date : ''"
            @click="can('manage dept-tasks') && openTaskModal(null, boardFilters.department_id || null, day.date)">
            <span class="cal-day-num">{{ day.day }}</span>
            <div class="cal-pill-stack">
              <div v-for="task in (calendarTasksByDate[day.date] || []).slice(0, 3)" :key="task.id"
                class="cal-pill" :class="task.is_overdue && 'cal-pill--overdue'"
                :style="{ borderLeftColor: priorityColor(task.priority), background: task.department ? task.department.color + '22' : 'var(--surface-2)' }"
                :title="task.title + (task.department ? ' · ' + task.department.name : '') + (task.assignee ? ' · ' + task.assignee.name : '')"
                @click.stop="openTaskDetail(task)">
                <span class="cal-pill-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</span>
                <span v-if="task.assignee" class="cal-pill-who">{{ initials(task.assignee.name) }}</span>
              </div>
              <button v-if="(calendarTasksByDate[day.date] || []).length > 3" type="button"
                class="cal-more" @click.stop="openDayTasks(day)">
                +{{ (calendarTasksByDate[day.date] || []).length - 3 }} more
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── People (tasks by user, Notion-style) ────────────────────────────── -->
    <div v-else-if="currentView === 'people'" class="dtm-section">

      <!-- Filter bar -->
      <div class="people-filter-bar">
        <input v-model="peopleSearch" placeholder="Filter tasks by name…" class="field-input pf-search" />
        <select v-model="peopleFilters.user_id" class="field-input sm">
          <option value="">All People</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-model="peopleFilters.department_id" class="field-input sm">
          <option value="">All Departments</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="peopleFilters.status" class="field-input sm">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="overdue">Overdue</option>
        </select>
        <span class="pf-count">{{ peopleGroups.length }} {{ peopleGroups.length === 1 ? 'person' : 'people' }}</span>
        <div class="pv-toggle">
          <button class="pv-btn" :class="peopleViewMode === 'list' && 'active'" @click="peopleViewMode = 'list'" title="List view" v-html="ICO.list"></button>
          <button class="pv-btn" :class="peopleViewMode === 'cards' && 'active'" @click="peopleViewMode = 'cards'" title="Cards view" v-html="ICO.grid"></button>
        </div>
      </div>

      <div v-if="peopleLoading" class="view-loading"><div class="spinner"></div></div>

      <div v-else-if="!peopleGroups.length" class="empty-state">
        <span class="empty-icon" v-html="ICO.users"></span>
        <p>No tasks match your filters.</p>
      </div>

      <div v-else-if="peopleViewMode === 'list'" class="people-list">
        <div v-for="grp in peopleGroups" :key="grp.key" class="pg-block">

          <!-- Group header -->
          <button class="pg-head" @click="togglePeopleUser(grp.key)">
            <div class="pg-head-left">
              <span class="pg-chevron" :class="!peopleCollapsed[grp.key] && 'open'" v-html="ICO.chevronR"></span>
              <div class="pg-avatar" :style="grp.user ? { background: userAvatarColor(grp.user.id) } : {}">
                {{ grp.user ? initials(grp.user.name) : '?' }}
              </div>
              <span class="pg-name">{{ grp.user?.name ?? 'Unassigned' }}</span>
              <span v-if="grp.overdue" class="pg-chip pg-chip--danger">{{ grp.overdue }} overdue</span>
              <span class="pg-chip pg-chip--neutral">{{ grp.active }} active</span>
              <span v-if="grp.done" class="pg-chip pg-chip--done">{{ grp.done }} done</span>
            </div>
            <div class="pg-head-right">
              <div class="pg-progress-wrap">
                <div class="pg-progress-bar">
                  <div class="pg-progress-fill"
                    :style="{ width: grp.total ? (grp.done / grp.total * 100).toFixed(1) + '%' : '0%' }">
                  </div>
                </div>
                <span class="pg-progress-txt">{{ grp.done }}/{{ grp.total }}</span>
              </div>
            </div>
          </button>

          <!-- Expanded task list -->
          <div v-show="!peopleCollapsed[grp.key]" class="pg-task-area">
            <!-- Column labels -->
            <div class="pg-col-labels">
              <span>Task</span>
              <span>Department</span>
              <span>Due Date</span>
              <span>Status</span>
            </div>
            <!-- Task rows -->
            <div v-for="task in grp.tasks" :key="task.id"
              class="pg-task-row"
              :class="task.is_overdue && 'pg-task-row--ov'"
              :style="{ borderLeftColor: priorityColor(task.priority) }"
              @click="openTaskDetail(task)">
              <div class="pg-task-info">
                <span class="pg-task-name"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</span>
                <span v-if="task.description" class="pg-task-hint">{{ task.description.slice(0, 80) }}{{ task.description.length > 80 ? '…' : '' }}</span>
              </div>
              <div class="pg-task-dept-cell">
                <span v-if="task.department" class="dept-pill"
                  :style="{ background: task.department.color + '22', color: task.department.color }">
                  {{ task.department.name }}
                </span>
                <span v-else class="pg-task-empty">—</span>
              </div>
              <span class="pg-task-due" :class="task.is_overdue && 'text-danger'">
                {{ task.due_date_fmt || '—' }}
              </span>
              <span :class="['status-badge', 'st-' + task.status, task.is_overdue && 'st-overdue']">
                {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
              </span>
            </div>
            <div v-if="!grp.tasks.length" class="pg-no-tasks">No tasks match your filters.</div>
          </div>

        </div>
      </div>

      <!-- Cards view -->
      <div v-else class="people-cards">
        <div v-for="grp in peopleGroups" :key="grp.key" class="pc-card">
          <div class="pc-card-head">
            <div class="pg-avatar" :style="grp.user ? { background: userAvatarColor(grp.user.id) } : {}">
              {{ grp.user ? initials(grp.user.name) : '?' }}
            </div>
            <div class="pc-head-info">
              <div class="pc-name">{{ grp.user?.name ?? 'Unassigned' }}</div>
              <div class="pc-chips">
                <span v-if="grp.overdue" class="pg-chip pg-chip--danger">{{ grp.overdue }} overdue</span>
                <span class="pg-chip pg-chip--neutral">{{ grp.active }} active</span>
                <span v-if="grp.done" class="pg-chip pg-chip--done">{{ grp.done }} done</span>
              </div>
            </div>
            <span class="pc-progress-txt-top">{{ grp.done }}/{{ grp.total }}</span>
          </div>
          <div class="pc-progress">
            <div class="pc-progress-fill" :style="{ width: grp.total ? (grp.done / grp.total * 100).toFixed(1) + '%' : '0%' }"></div>
          </div>
          <div class="pc-tasks">
            <div v-for="task in grp.tasks" :key="task.id"
              class="pc-task"
              :class="task.is_overdue && 'pc-task--ov'"
              :style="{ borderLeftColor: priorityColor(task.priority) }"
              @click="openTaskDetail(task)">
              <span class="pc-task-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</span>
              <span :class="['status-badge', 'st-' + task.status, task.is_overdue && 'st-overdue']">
                {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
              </span>
            </div>
            <div v-if="!grp.tasks.length" class="pc-empty">No tasks</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Table ──────────────────────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'table'" class="dtm-section">
      <div class="filter-bar">
        <div v-if="isAdmin" class="scope-toggle">
          <button :class="['scope-btn', tableFilters.assigned_to ? 'active' : '']"
            @click="setTableScope(true)">My Tasks</button>
          <button :class="['scope-btn', !tableFilters.assigned_to ? 'active' : '']"
            @click="setTableScope(false)">All Tasks</button>
        </div>
        <div class="filter-search">
          <input v-model="tableFilters.search" @input="debouncedLoadTable"
            placeholder="Search tasks…" class="field-input" />
        </div>
        <select v-model="tableFilters.department_id" @change="tableFilterChange" class="field-input sm">
          <option value="">All Departments</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="tableFilters.status" @change="tableFilterChange" class="field-input sm">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="overdue">Overdue</option>
        </select>
        <select v-model="tableFilters.priority" @change="tableFilterChange" class="field-input sm">
          <option value="">All Priorities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        <select v-if="isAdmin" v-model="tableFilters.assigned_to" @change="tableFilterChange" class="field-input sm">
          <option value="">All Assignees</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <button @click="quickDateFilter('week')" class="btn-ghost sm">This Week</button>
        <button @click="quickDateFilter('month')" class="btn-ghost sm">This Month</button>
        <label class="filter-label">From
          <input type="date" v-model="tableFilters.date_from" @change="tableFilterChange" class="field-input sm" />
        </label>
        <label class="filter-label">To
          <input type="date" v-model="tableFilters.date_to" @change="tableFilterChange" class="field-input sm" />
        </label>
        <button class="btn-ghost sm" @click="toggleTableExpanded">
          <span v-html="tableExpanded ? ICO.list : ICO.grid"></span>
          {{ tableExpanded ? 'Fewer columns' : 'Show all columns' }}
        </button>
        <div class="filter-actions">
          <button @click="clearTableFilters" class="btn-ghost sm">Clear</button>
          <button @click="exportTable()" class="btn-ghost sm">
            <span v-html="ICO.print"></span> Print
          </button>
          <button @click="openExportModal()" class="btn-ghost sm">
            <span v-html="ICO.download"></span> Export
          </button>
          <button v-if="isAdmin" @click="openTaskModal()" class="btn-primary sm">
            <span v-html="ICO.plus"></span> New Task
          </button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th @click="toggleSort('created_at')" class="sortable">
                Date In
                <span class="sort-indicator" v-html="tableSort.field === 'created_at' ? (tableSort.dir === 'asc' ? ICO.sortAsc : ICO.sortDesc) : ICO.sortNone"></span>
              </th>
              <th @click="toggleSort('due_date')" class="sortable">
                Deadline
                <span class="sort-indicator" v-html="tableSort.field === 'due_date' ? (tableSort.dir === 'asc' ? ICO.sortAsc : ICO.sortDesc) : ICO.sortNone"></span>
              </th>
              <th @click="toggleSort('department_id')" class="sortable">Dept</th>
              <th @click="toggleSort('title')" class="sortable col-details">
                Details / Task
                <span class="sort-indicator" v-html="tableSort.field === 'title' ? (tableSort.dir === 'asc' ? ICO.sortAsc : ICO.sortDesc) : ICO.sortNone"></span>
              </th>
              <template v-if="tableExpanded">
                <th>Assigned To</th>
                <th @click="toggleSort('priority')" class="sortable">Priority</th>
                <th @click="toggleSort('status')" class="sortable">Status</th>
              </template>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tableLoading">
              <td :colspan="tableExpanded ? 8 : 5" class="empty-cell">Loading…</td>
            </tr>
            <tr v-else-if="tableTasks.length === 0">
              <td :colspan="tableExpanded ? 8 : 5" class="empty-cell">No tasks found.</td>
            </tr>
            <tr v-for="task in tableTasks" :key="task.id"
              class="table-row" @click="openTaskDetail(task)">
              <td class="text-2">{{ dateOnly(task.created_at) }}</td>
              <td :class="task.is_overdue && 'text-danger'">{{ task.due_date_fmt || '—' }}</td>
              <td>
                <span class="dept-pill" :style="{background: task.department?.color+'22', color: task.department?.color}">
                  {{ task.department?.name }}
                </span>
              </td>
              <td class="col-details" :title="task.description || task.title">
                <div class="task-cell-wrap">
                  <span class="task-cell-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</span>
                  <span v-if="task.description" class="task-cell-desc">{{ task.description }}</span>
                </div>
              </td>
              <template v-if="tableExpanded">
                <td class="text-2">{{ task.assignee?.name || '—' }}</td>
                <td>
                  <span class="kc-priority" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                    {{ task.priority }}
                  </span>
                </td>
                <td>
                  <span :class="['status-badge', 'st-'+task.status, task.is_overdue && 'st-overdue']">
                    {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
                  </span>
                </td>
              </template>
              <td @click.stop class="actions-cell">
                <button class="btn-icon" @click="openTaskModal(task)" title="Edit" aria-label="Edit task" v-html="ICO.edit"></button>
                <button class="btn-icon danger" @click="deleteTask(task.id)" title="Delete" aria-label="Delete task" v-html="ICO.trash"></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="tableLastPage > 1" class="pagination-bar">
        <span class="page-info">Showing {{ tablePageFrom }}–{{ tablePageTo }} of {{ tableTotal }}</span>
        <div class="page-btns">
          <button class="btn-ghost sm" @click="tablePage--; loadTableTasks()" :disabled="tablePage <= 1">Prev</button>
          <span class="page-num">{{ tablePage }} / {{ tableLastPage }}</span>
          <button class="btn-ghost sm" @click="tablePage++; loadTableTasks()" :disabled="tablePage >= tableLastPage">Next</button>
        </div>
      </div>
    </div>

    <!-- ── This Week ─────────────────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'weekly'" class="dtm-section">

      <div class="section-toolbar no-print">
        <div class="week-nav">
          <button class="btn-icon" @click="weekNav(-1)" aria-label="Previous week" v-html="ICO.chevronL"></button>
          <span class="week-nav-label">{{ weeklyDisplayLabel }}</span>
          <button class="btn-icon" @click="weekNav(1)" aria-label="Next week" v-html="ICO.chevronR"></button>
        </div>
        <div class="scope-toggle" role="group" aria-label="Report grouping">
          <button :class="['scope-btn', weeklyGroupMode === 'weekly' && 'active']" @click="setWeeklyGroupMode('weekly')">By Department</button>
          <button :class="['scope-btn', weeklyGroupMode === 'department' && 'active']" @click="setWeeklyGroupMode('department')">Department Focus</button>
          <button :class="['scope-btn', weeklyGroupMode === 'all' && 'active']" @click="setWeeklyGroupMode('all')">All Together</button>
        </div>
        <select v-if="weeklyGroupMode === 'department'" v-model="weeklyDeptFocus" class="field-input sm">
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-if="isAdmin" v-model="weeklyAssigneeFilter" class="field-input sm">
          <option value="">All Assignees</option>
          <option v-for="u in users" :key="u.id" :value="u.name">{{ u.name }}</option>
        </select>
        <button @click="printWeekly" class="btn-ghost sm">
          <span v-html="ICO.print"></span> Print A4
        </button>
      </div>

      <div class="weekly-report card" id="weekly-print-area">
        <div class="wr-header">
          <div>
            <div class="wr-title">{{ weeklyReportTitle }}</div>
            <div class="wr-meta">
              <span><strong>Month:</strong> {{ weeklyMonthLabel }}</span>
              <span><strong>Week:</strong> {{ weeklyData.week_start }} to {{ weeklyData.week_end }}</span>
            </div>
          </div>
          <div v-if="weeklySummary.total" class="wr-summary-chips">
            <span class="wr-chip"><span class="hm-dot" style="background:var(--primary)"></span>{{ weeklySummary.total }} outstanding</span>
            <span v-if="weeklySummary.overdue" class="wr-chip wr-chip--danger"><span class="hm-dot" style="background:#ef4444"></span>{{ weeklySummary.overdue }} overdue</span>
            <span v-if="weeklyGroupMode === 'department' && focusedDept" class="wr-chip"
              :style="{color: focusedDept.color, borderColor: focusedDept.color+'44', background: focusedDept.color+'10'}">
              <span class="hm-dot" :style="{background: focusedDept.color}"></span>{{ focusedDept.name }}
            </span>
            <span v-else class="wr-chip"><span class="hm-dot" style="background:#94a3b8"></span>{{ weeklySummary.depts }} {{ weeklySummary.depts === 1 ? 'dept' : 'depts' }}</span>
          </div>
        </div>

        <div v-if="weeklyIsEmpty" class="empty-state">
          <span class="empty-icon" v-html="ICO.check"></span>
          <p>{{ weeklyEmptyMessage }}</p>
        </div>

        <div v-else-if="weeklyGroupMode === 'all'" class="table-wrap">
          <table class="data-table compact">
            <thead>
              <tr><th>Due</th><th>Department</th><th>Task</th><th>Assignee</th><th>Priority</th><th>Status</th></tr>
            </thead>
            <tbody>
              <tr v-for="task in weeklyFlatTasks" :key="task.id"
                class="table-row" @click="openTaskDetail(task)">
                <td class="wr-date" :class="task.is_overdue && 'text-danger'">{{ task.due_date || '—' }}</td>
                <td>
                  <span class="dept-pill" :style="{background: task.department.color+'22', color: task.department.color}">
                    {{ task.department.name }}
                  </span>
                </td>
                <td><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</td>
                <td class="text-2">{{ task.assignee || '—' }}</td>
                <td>
                  <span class="kc-priority" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                    {{ task.priority }}
                  </span>
                </td>
                <td>
                  <span :class="['status-badge', 'st-'+task.status, task.is_overdue && 'st-overdue']">
                    {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="wr-grid">
          <div v-for="deptRow in filteredWeeklyDepts" :key="deptRow.department.id" class="wr-dept-block">
            <div class="wr-dept-header" :style="{background: deptRow.department.color+'18', borderLeft: '3px solid '+deptRow.department.color}">
              <span :style="{color: deptRow.department.color, fontWeight: 700}">{{ deptRow.department.name }}</span>
              <span class="wr-dept-count">{{ deptRow.tasks.length }} task{{ deptRow.tasks.length !== 1 ? 's' : '' }}</span>
            </div>
            <table class="data-table compact">
              <thead>
                <tr><th>Due</th><th>Task</th><th>Assignee</th><th>Priority</th><th>Status</th></tr>
              </thead>
              <tbody>
                <tr v-for="task in deptRow.tasks" :key="task.id"
                  class="table-row" @click="openTaskDetail(task)">
                  <td class="wr-date" :class="task.is_overdue && 'text-danger'">{{ task.due_date || '—' }}</td>
                  <td><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</td>
                  <td class="text-2">{{ task.assignee || '—' }}</td>
                  <td>
                    <span class="kc-priority" :style="{background: priorityColor(task.priority)+'22', color: priorityColor(task.priority)}">
                      {{ task.priority }}
                    </span>
                  </td>
                  <td>
                    <span :class="['status-badge', 'st-'+task.status, task.is_overdue && 'st-overdue']">
                      {{ task.is_overdue ? 'Overdue' : statusLabel(task.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Files / Attachments ───────────────────────────────────────────────── -->
    <div v-else-if="currentView === 'files'" class="dtm-section">

      <div class="fm-toolbar">
        <div class="filter-search" style="flex:1;max-width:320px">
          <input v-model="attachmentsSearch" @input="debouncedAttachmentSearch"
            placeholder="Search by filename…" class="field-input" />
        </div>
        <select v-model="filesSort" class="field-input fm-sort-select">
          <option value="date">Newest first</option>
          <option value="name">Name A–Z</option>
          <option value="size">Largest first</option>
        </select>
        <div class="fm-view-toggle">
          <button :class="['fm-vt-btn', filesViewMode === 'grid' && 'active']"
            @click="setFilesView('grid')" title="Grid view" v-html="ICO.grid"></button>
          <button :class="['fm-vt-btn', filesViewMode === 'list' && 'active']"
            @click="setFilesView('list')" title="List view" v-html="ICO.list"></button>
        </div>
        <span class="af-count">{{ attachmentsTotal }} file{{ attachmentsTotal !== 1 ? 's' : '' }}</span>
      </div>

      <div v-if="attachmentsLoading" class="view-loading"><div class="spinner"></div></div>

      <template v-else>
        <div v-if="!sortedAttachments.length" class="empty-state">
          <span class="empty-icon" v-html="ICO.folder"></span>
          <p>No attachments found.</p>
        </div>

        <!-- Grid view -->
        <div v-else-if="filesViewMode === 'grid'" class="fm-grid">
          <div v-for="a in sortedAttachments" :key="a.id" class="fm-card">
            <div class="fm-icon-wrap">
              <span class="fm-ext-icon"
                :style="{ background: fileExtStyle(a.filename)[0], color: fileExtStyle(a.filename)[1] }">
                {{ fileExtLabel(a.filename) }}
              </span>
              <div class="fm-card-overlay">
                <a :href="a.url" target="_blank" class="fm-overlay-btn" title="Download" v-html="ICO.download"></a>
                <button v-if="isAdmin || a.user?.id === currentUserId" class="fm-overlay-btn" @click.stop="openRename(a)" title="Rename" v-html="ICO.edit"></button>
                <button v-if="isAdmin || a.user?.id === currentUserId" class="fm-overlay-btn danger" @click.stop="deleteAttachmentFromTab(a)" title="Delete" v-html="ICO.trash"></button>
              </div>
            </div>
            <div class="fm-card-foot">
              <div class="fm-card-name" :title="a.filename">{{ a.filename }}</div>
              <div class="fm-card-meta">{{ formatFileSize(a.size) }} · {{ a.created_at }}</div>
              <button v-if="a.task" class="fm-task-link" @click="openTaskDetail(a.task)">
                {{ a.task.title }}
              </button>
              <div v-if="a.task?.department" class="fm-dept-row">
                <span class="dept-pill fm-dept-pill"
                  :style="{ background: a.task.department.color + '22', color: a.task.department.color }">
                  {{ a.task.department.name }}
                </span>
                <span v-if="isAdmin && a.user" class="fm-by">{{ a.user.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- List view -->
        <div v-else class="fm-list">
          <div v-for="a in sortedAttachments" :key="a.id" class="fm-list-row">
            <span class="fm-list-badge"
              :style="{ background: fileExtStyle(a.filename)[0], color: fileExtStyle(a.filename)[1] }">
              {{ fileExtLabel(a.filename) }}
            </span>
            <div class="fm-list-main">
              <a :href="a.url" target="_blank" class="fm-list-name" :title="a.filename">{{ a.filename }}</a>
              <div class="fm-list-sub">
                <span v-if="a.task?.department" class="dept-pill fm-dept-pill"
                  :style="{ background: a.task.department.color + '22', color: a.task.department.color }">
                  {{ a.task.department.name }}
                </span>
                <button v-if="a.task" class="fm-task-link-inline" @click="openTaskDetail(a.task)">
                  {{ a.task.title }}
                </button>
                <span v-if="isAdmin && a.user" class="fm-list-meta">{{ a.user.name }}</span>
              </div>
            </div>
            <span class="fm-list-meta">{{ formatFileSize(a.size) }}</span>
            <span class="fm-list-meta">{{ a.created_at }}</span>
            <div class="fm-list-actions" @click.stop>
              <button v-if="isAdmin || a.user?.id === currentUserId" class="btn-icon" @click="openRename(a)" title="Rename" v-html="ICO.edit"></button>
              <button v-if="isAdmin || a.user?.id === currentUserId" class="btn-icon danger" @click="deleteAttachmentFromTab(a)" title="Delete" v-html="ICO.trash"></button>
            </div>
          </div>
        </div>

        <div v-if="attachmentsLastPage > 1" class="pagination-bar">
          <span class="page-info">Page {{ attachmentsPage }} of {{ attachmentsLastPage }} · {{ attachmentsTotal }} files</span>
          <div class="page-btns">
            <button class="btn-ghost sm" @click="attachmentsPage--; loadAllAttachments()" :disabled="attachmentsPage <= 1">Prev</button>
            <span class="page-num">{{ attachmentsPage }} / {{ attachmentsLastPage }}</span>
            <button class="btn-ghost sm" @click="attachmentsPage++; loadAllAttachments()" :disabled="attachmentsPage >= attachmentsLastPage">Next</button>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Task Detail Panel (right slide-in) ──────────────────────────────── -->
    <transition name="slide-panel">
      <div v-if="selectedTask" class="panel-overlay" @click.self="selectedTask = null">
        <div class="detail-panel">
          <div class="dp-header">
            <div>
              <span class="dept-pill dept-pill--dot" :style="{background: selectedTask.department?.color+'22', color: selectedTask.department?.color}">
                <span class="dept-color-dot" :style="{background: selectedTask.department?.color}"></span>
                {{ selectedTask.department?.name }}
              </span>
              <h2 class="dp-title"><span v-if="selectedTask.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ selectedTask.title }}</h2>
            </div>
            <button class="btn-icon" @click="selectedTask = null" v-html="ICO.x" title="Close" aria-label="Close panel"></button>
          </div>

          <div class="dp-meta-grid">
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Priority</span>
              <span class="kc-priority" :style="{background: priorityColor(selectedTask.priority)+'22', color: priorityColor(selectedTask.priority)}">
                {{ selectedTask.priority }}
              </span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Status</span>
              <span :class="['status-badge', 'st-'+selectedTask.status, selectedTask.is_overdue && 'st-overdue']">
                {{ selectedTask.is_overdue ? 'Overdue' : statusLabel(selectedTask.status) }}
              </span>
            </div>
            <div v-if="selectedTask.is_recurring" class="dp-meta-item">
              <span class="dp-meta-lbl">Repeats</span>
              <span>
                {{ recurrenceLabel(selectedTask.recurrence_type) }}
                <template v-if="selectedTask.next_recurrence_date"> · next {{ selectedTask.next_recurrence_date }}</template>
              </span>
            </div>
            <div class="dp-meta-item">
              <span class="dp-meta-lbl">Due Date</span>
              <span :class="selectedTask.is_overdue && 'text-danger'">{{ selectedTask.due_date_fmt || 'Not set' }}</span>
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
              <span>{{ formatDate(selectedTask.created_at) }}</span>
            </div>
          </div>

          <div v-if="selectedTask.description" class="dp-description">
            {{ selectedTask.description }}
          </div>

          <div class="dp-actions">
            <!-- Row 1: Workflow actions -->
            <div class="dp-action-row">
              <!-- Assignee driving their own task forward -->
              <template v-if="selectedTask.assigned_to === currentUserId">
                <button v-if="selectedTask.status === 'pending'" class="btn-primary sm" @click="detailAdvance('in_progress')">Start task</button>
                <button v-else-if="selectedTask.status === 'in_progress'" class="btn-primary sm" @click="detailAdvance('completed')">Mark complete</button>
                <span v-else-if="selectedTask.status === 'completed'" class="dp-status-note dp-status-note--done"><span v-html="ICO.check"></span> Task completed</span>
                <span v-else-if="selectedTask.status === 'cancelled'" class="dp-status-note">Task cancelled</span>
              </template>

              <!-- Closed states -->
              <span v-else-if="selectedTask.status === 'completed'" class="dp-status-note dp-status-note--done"><span v-html="ICO.check"></span> Task completed</span>
              <span v-else-if="selectedTask.status === 'cancelled'" class="dp-status-note">Task cancelled</span>

              <span v-else-if="isAdmin || selectedTask.created_by === currentUserId" class="dp-status-note">Use the controls below to update this task</span>
              <span v-else class="dp-no-action">Only {{ selectedTask.assignee?.name || 'the assignee' }} can update this task</span>
            </div>

            <!-- Row 2: Admin / creator utilities -->
            <div v-if="isAdmin || selectedTask.created_by === currentUserId" class="dp-util-row">
              <span class="dp-util-badge">{{ isAdmin ? 'Admin' : 'Creator' }}</span>
              <template v-if="isAdmin">
                <span class="dp-util-lbl">Status</span>
                <select v-model="quickStatus" @change="quickUpdateStatus" class="field-input sm" aria-label="Override task status">
                  <option value="pending">Pending</option>
                  <option value="in_progress">In Progress</option>
                          <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </template>
              <button class="btn-ghost sm" @click="openTaskModal(selectedTask)">
                <span v-html="ICO.edit"></span> Edit
              </button>
              <button v-if="isAdmin" class="btn-ghost sm danger dp-delete-btn" @click="deleteTask(selectedTask.id)">
                <span v-html="ICO.trash"></span> Delete
              </button>
            </div>
          </div>

          <div class="dp-attachments">
            <div class="dp-section-title">
              Attachments ({{ (selectedTask.attachments || []).length }})
              <label class="attach-upload-btn" :class="attachmentUploading && 'uploading'">
                <input type="file" @change="uploadAttachment" style="display:none"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip" />
                <span v-html="ICO.paperclip"></span>
                {{ attachmentUploading ? 'Uploading…' : 'Attach File' }}
              </label>
            </div>
            <div class="attach-size-hint">PDF, Word, Excel, PowerPoint, images, ZIP · Max 20 MB</div>
            <div class="attachment-list">
              <div v-for="a in (selectedTask.attachments || [])" :key="a.id" class="attachment-item">
                <a :href="a.url" target="_blank" class="attach-name" :title="a.filename">{{ a.filename }}</a>
                <span class="attach-size">{{ formatFileSize(a.size) }}</span>
                <button v-if="isAdmin || a.user?.id === currentUserId" class="btn-icon xs danger" @click="deleteAttachmentFile(a.id)" v-html="ICO.x" title="Remove" aria-label="Remove attachment"></button>
              </div>
              <div v-if="!(selectedTask.attachments || []).length" class="empty-comments">No attachments yet.</div>
            </div>
          </div>

          <div class="dp-comments">
            <div class="dp-section-title">Comments ({{ (selectedTask.comments || []).length }})</div>
            <div class="comment-list">
              <div v-for="c in (selectedTask.comments || [])" :key="c.id" class="comment-item">
                <div class="comment-avatar">{{ initials(c.user?.name || '?') }}</div>
                <div class="comment-body">
                  <div class="comment-meta">
                    <strong>{{ c.user?.name }}</strong>
                    <span class="comment-time">{{ formatDate(c.created_at) }}</span>
                    <button v-if="isAdmin || c.user?.id === currentUserId" class="btn-icon xs danger" @click="deleteComment(c.id)" v-html="ICO.x" title="Delete" aria-label="Delete comment"></button>
                  </div>
                  <div class="comment-text">{{ c.comment }}</div>
                </div>
              </div>
              <div v-if="!(selectedTask.comments || []).length" class="empty-comments">No comments yet.</div>
            </div>
            <div class="comment-input-row">
              <input v-model="newComment" @keydown.enter="addComment"
                placeholder="Add a comment… (Enter to send)" class="field-input" />
              <button @click="addComment" class="btn-primary sm">Send</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Task Create/Edit Modal ────────────────────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-box">
          <div class="modal-header">
            <h3 class="modal-title">{{ editTask ? 'Edit Task' : 'New Task' }}</h3>
            <button class="btn-icon" @click="showModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="field-row">
              <label class="field-label">Task Title *</label>
              <input v-model="form.title" class="field-input" placeholder="Enter task title…" />
            </div>
            <div class="field-row two-col">
              <div>
                <label class="field-label">Department *</label>
                <select v-model="form.department_id" class="field-input">
                  <option value="">Select department…</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div v-if="isAdmin">
                <label class="field-label">Assigned To</label>
                <select v-model="form.assigned_to" class="field-input">
                  <option value="">Unassigned</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div v-else>
                <label class="field-label">Assigned To</label>
                <div class="field-input field-readonly">{{ formAssigneeName }}</div>
              </div>
            </div>
            <div class="field-row three-col">
              <div>
                <label class="field-label">Priority *</label>
                <select v-model="form.priority" class="field-input">
                  <option value="critical">Critical</option>
                  <option value="high">High</option>
                  <option value="medium">Medium</option>
                  <option value="low">Low</option>
                </select>
              </div>
              <div>
                <label class="field-label">Status</label>
                <select v-model="form.status" class="field-input">
                  <option value="pending">Pending</option>
                  <option value="in_progress">In Progress</option>
                          <option v-if="editTask && (isAdmin || form.assigned_to === currentUserId)" value="completed">Completed</option>
                  <option v-if="editTask && isAdmin" value="cancelled">Cancelled</option>
                </select>
              </div>
              <div>
                <label class="field-label">Due Date</label>
                <input type="date" v-model="form.due_date" class="field-input" />
              </div>
            </div>
            <div class="field-row">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_recurring" />
                Repeats
              </label>
              <select v-if="form.is_recurring" v-model="form.recurrence_type" class="field-input sm recur-select">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
              </select>
              <span class="field-hint">
                {{ form.is_recurring
                    ? 'When this task is completed, the next occurrence is created automatically.'
                    : 'A one-off task with no repeat.' }}
              </span>
            </div>
            <div class="field-row">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_important" />
                Important Task
              </label>
              <span class="field-hint">
                {{ form.is_important
                    ? 'Flagged as important — shown with a star everywhere this task appears.'
                    : 'Not marked as important.' }}
              </span>
            </div>
            <div class="field-row">
              <label class="field-label">Description</label>
              <textarea v-model="form.description" class="field-textarea" rows="4" placeholder="Task description…"></textarea>
            </div>
            <div v-if="formError" class="form-error">{{ formError }}</div>
          </div>
          <div class="modal-footer">
            <button @click="showModal = false" class="btn-ghost">Cancel</button>
            <button @click="saveTask" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving…' : (editTask ? 'Update Task' : 'Create Task') }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Delete Confirm Modal ──────────────────────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="showDeleteConfirm" class="modal-backdrop">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">Delete Task</h3>
            <button class="btn-icon" @click="showDeleteConfirm = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <p style="color: var(--text-1); font-size: 14px; margin: 0;">{{ deleteConfirmMsg }}</p>
          </div>
          <div class="modal-footer">
            <button @click="showDeleteConfirm = false" class="btn-ghost">Cancel</button>
            <button @click="confirmDelete" class="btn-danger">Delete</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Rename Attachment Modal ─────────────────────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="renameModal.show" class="modal-backdrop">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">Rename File</h3>
            <button class="btn-icon" @click="renameModal.show = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="field-row">
              <label class="field-label">Filename</label>
              <input v-model="renameModal.filename" class="field-input"
                placeholder="Enter new filename…"
                @keydown.enter="saveRename" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="renameModal.show = false" class="btn-ghost">Cancel</button>
            <button @click="saveRename" class="btn-primary" :disabled="renameModal.saving">
              {{ renameModal.saving ? 'Saving…' : 'Rename' }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Export Tasks Modal ─────────────────────────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="exportModal.open" class="modal-backdrop">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <div>
              <h3 class="modal-title">Export Tasks</h3>
              <p class="modal-subtitle">Pick what to include, then download.</p>
            </div>
            <button class="btn-icon" @click="exportModal.open = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="export-section">
              <div class="export-cols-head">
                <span class="export-section-label">Columns to include</span>
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
              Exports the Table tab's current filters · <strong>{{ exportCols.filter(c => c.checked).length }}</strong> column(s)
            </p>
            <div class="export-action-stack">
              <button class="export-dl-btn export-dl-xls" :disabled="exportModal.loading || exportCols.every(c => !c.checked)" @click="executeExport('xls')">
                <span class="export-dl-icon" v-html="ICO.download"></span>
                <span class="export-dl-text">
                  <span class="export-dl-label">{{ exportModal.loading ? 'Exporting…' : 'Download Excel' }}</span>
                  <span class="export-dl-desc">Formatted with borders &amp; column widths</span>
                </span>
              </button>
              <button class="export-dl-btn export-dl-csv" :disabled="exportModal.loading || exportCols.every(c => !c.checked)" @click="executeExport('csv')">
                <span class="export-dl-icon" v-html="ICO.download"></span>
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
    </transition>

    <!-- ── Export Calendar Modal ─────────────────────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="calendarExportModal.open" class="modal-backdrop">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <div>
              <h3 class="modal-title">Export Calendar</h3>
              <p class="modal-subtitle">Pick what to include, then download.</p>
            </div>
            <button class="btn-icon" @click="calendarExportModal.open = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="export-section">
              <span class="export-section-label">Include</span>
              <div class="export-cols-grid">
                <label class="export-col-check">
                  <input type="checkbox" v-model="calendarExportModal.includeClosed">
                  <span>Closed tasks (completed &amp; cancelled)</span>
                </label>
              </div>
            </div>
          </div>
          <div class="export-modal-footer">
            <p class="export-footer-count">
              Exports the Calendar tab's current filters · <strong>{{ calendarMonthLabel }}</strong>
            </p>
            <div class="export-action-stack">
              <button class="export-dl-btn export-dl-xls" :disabled="calendarExportModal.loading" @click="exportCalendar()">
                <span class="export-dl-icon" v-html="ICO.download"></span>
                <span class="export-dl-text">
                  <span class="export-dl-label">{{ calendarExportModal.loading ? 'Exporting…' : 'Download Excel' }}</span>
                  <span class="export-dl-desc">Month grid, formatted with borders</span>
                </span>
              </button>
              <button class="export-cancel-btn" @click="calendarExportModal.open = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Day Tasks Modal (calendar "+N more") ─────────────────────────────── -->
    <transition name="modal-fade">
      <div v-if="dayTasksModal.open" class="modal-backdrop">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <h3 class="modal-title">Tasks due {{ dayTasksModal.label }}</h3>
            <button class="btn-icon" @click="dayTasksModal.open = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="day-tasks-list">
              <button v-for="task in dayTasksModal.tasks" :key="task.id" type="button"
                class="cal-pill" :class="task.is_overdue && 'cal-pill--overdue'"
                :style="{ borderLeftColor: priorityColor(task.priority), background: task.department ? task.department.color + '22' : 'var(--surface-2)' }"
                :title="task.title + (task.department ? ' · ' + task.department.name : '') + (task.assignee ? ' · ' + task.assignee.name : '')"
                @click="openDayTask(task)">
                <span class="cal-pill-title"><span v-if="task.is_important" class="imp-star" title="Important" v-html="ICO.star"></span>{{ task.title }}</span>
                <span v-if="task.assignee" class="cal-pill-who">{{ initials(task.assignee.name) }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Toast notifications ───────────────────────────────────────────────── -->
    <div class="toast-container" aria-live="polite">
      <transition-group name="toast-slide">
        <div v-for="t in toasts" :key="t.id" :class="['toast', 'toast--'+t.type]">
          <span class="toast-ico" v-html="t.type === 'error' ? ICO.trash : ICO.check"></span>
          {{ t.msg }}
        </div>
      </transition-group>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Chart, registerables } from 'chart.js';
import api from '../api.js';
import { usePermissions } from '../composables/usePermissions.js';

Chart.register(...registerables);

// ─── SVG icon helper ──────────────────────────────────────────────────────────
function _i(d) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
}
const ICO = {
  chart:    _i('<rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>'),
  kanban:   _i('<rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="13" rx="1"/><rect x="17" y="3" width="4" height="9" rx="1"/>'),
  list:     _i('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
  calendar: _i('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'),
  bell:     _i('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'),
  plus:     _i('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'),
  edit:     _i('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash:    _i('<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
  x:        _i('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
  print:    _i('<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'),
  check:    _i('<polyline points="20 6 9 17 4 12"/>'),
  clock:    _i('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
  refresh:  _i('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.29"/>'),
  alert:    _i('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'),
  download:    _i('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'),
  paperclip:   _i('<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>'),
  sortAsc:  _i('<polyline points="18 15 12 9 6 15"/>'),
  sortDesc: _i('<polyline points="6 9 12 15 18 9"/>'),
  sortNone:  _i('<line x1="12" y1="5" x2="12" y2="19"/><polyline points="9 8 12 5 15 8"/><polyline points="9 16 12 19 15 16"/>'),
  chevronL:  _i('<polyline points="15 18 9 12 15 6"/>'),
  chevronR:  _i('<polyline points="9 18 15 12 9 6"/>'),
  lock:      _i('<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'),
  eye:       _i('<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'),
  eyeOff:    _i('<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'),
  users:     _i('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  grid:      _i('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'),
  folder:    _i('<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'),
  calendarDays: _i('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/><line x1="16" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="8" y2="18"/><line x1="12" y1="18" x2="12" y2="18"/>'),
  star: _i('<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'),
  settings: _i('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
};

// ─── View state ───────────────────────────────────────────────────────────────
// A corrupted crm_user value must never throw here — this runs at component
// setup time, before the page can render at all (same guarded pattern as
// App.vue's getStoredUser() and router/index.js's setupGuard()).
function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('crm_user') || '{}');
  } catch {
    localStorage.removeItem('crm_user');
    return {};
  }
}

// Admins get the full management console; users get a focused personal surface.
const _authUserInit = getStoredUser();
const _isAdminInit  = (_authUserInit.roles || []).some(r => ['admin', 'super-admin'].includes(r));

const ADMIN_VIEWS = [
  { id: 'dashboard',   label: 'Dashboard',  icon: ICO.chart },
  { id: 'calendar',    label: 'Calendar',   icon: ICO.calendarDays },
  { id: 'table',       label: 'Table',      icon: ICO.list },
  { id: 'weekly',      label: 'Department', icon: ICO.calendar },
  { id: 'board',       label: 'Board',      icon: ICO.kanban },
  { id: 'people',      label: 'People',     icon: ICO.users },
  { id: 'files',       label: 'Files',      icon: ICO.folder },
];
const USER_VIEWS = [
  { id: 'mywork',   label: 'List',     icon: ICO.list },
  { id: 'board',    label: 'Board',    icon: ICO.kanban },
  { id: 'calendar', label: 'Calendar', icon: ICO.calendarDays },
  { id: 'files',    label: 'Files',    icon: ICO.folder },
];
const views = computed(() => (isAdmin.value ? ADMIN_VIEWS : USER_VIEWS));
const currentView = ref(_isAdminInit ? 'dashboard' : 'mywork');

// ─── Data ─────────────────────────────────────────────────────────────────────
const departments   = ref([]);
const users         = ref([]);
const dashData      = ref({ stats: {}, recentTasks: [], byDepartment: [], byStatus: [], weeklyRate: [] });
const boardTasks    = ref([]);
const tableTasks      = ref([]);
const tableLoading    = ref(false);
const tablePage       = ref(1);
const tableLastPage   = ref(1);
const tableTotal      = ref(0);
const tablePageFrom   = ref(0);
const tablePageTo     = ref(0);
const weeklyData    = ref({ week_start: '', week_end: '', departments: [] });
const notifications = ref([]);
const toasts        = ref([]);
const boardLoading  = ref(false);
const dashLoading   = ref(false);
const peopleTasks    = ref([]);
const peopleLoading  = ref(false);
const peopleSearch   = ref('');
const peopleFilters  = reactive({ department_id: '', status: '', user_id: '' });
const peopleCollapsed = reactive({});
const peopleViewMode  = ref('list');

// ─── Attachments tab ──────────────────────────────────────────────────────────
const allAttachments       = ref([]);
const attachmentsLoading   = ref(false);
const attachmentsSearch    = ref('');
const attachmentsPage      = ref(1);
const attachmentsLastPage  = ref(1);
const attachmentsTotal     = ref(0);
const renameModal   = reactive({ show: false, id: null, filename: '', saving: false });
const filesViewMode = ref(localStorage.getItem('dtm_files_view') || 'grid');
const filesSort     = ref('date');

const sortedAttachments = computed(() => {
  const list = [...allAttachments.value];
  list.sort((a, b) => {
    if (filesSort.value === 'name') {
      const av = (a.filename || '').toLowerCase(), bv = (b.filename || '').toLowerCase();
      return av < bv ? -1 : av > bv ? 1 : 0;
    }
    if (filesSort.value === 'size') return (b.size || 0) - (a.size || 0);
    return (b.created_at || '') < (a.created_at || '') ? -1 : 1;
  });
  return list;
});

function setFilesView(mode) {
  filesViewMode.value = mode;
  localStorage.setItem('dtm_files_view', mode);
}

function setWeeklyGroupMode(mode) {
  weeklyGroupMode.value = mode;
  localStorage.setItem('dtm_weekly_group', mode);
}

// ─── Auth state ───────────────────────────────────────────────────────────────
const authUser        = computed(() => getStoredUser());
const isAdmin         = computed(() => (authUser.value.roles || []).some(r => ['admin', 'super-admin'].includes(r)));
const currentUserId   = computed(() => authUser.value.id ?? null);
const currentUserName = computed(() => authUser.value.name ?? '');
const { can }          = usePermissions();

// ─── Filters ──────────────────────────────────────────────────────────────────
const boardFilters = reactive({ department_id: '', assigned_to: '' });
const tableFilters = reactive({ search: '', department_id: '', status: '', priority: '', assigned_to: '', date_from: '', date_to: '' });
const tableSort    = reactive({ field: 'created_at', dir: 'desc' });
const weeklyWeekStart      = ref('');
const weeklyAssigneeFilter = ref('');
const weeklyGroupMode      = ref(localStorage.getItem('dtm_weekly_group') || 'weekly'); // 'weekly' | 'department' | 'all'
const weeklyDeptFocusRaw   = ref('');

// ─── Modal / panel state ──────────────────────────────────────────────────────
const showModal    = ref(false);
const editTask     = ref(null);
const saving              = ref(false);
const formError           = ref('');
const showDeleteConfirm   = ref(false);
const deleteConfirmMsg    = ref('');
const deletePendingFn     = ref(null);
const attachmentUploading = ref(false);
const selectedTask      = ref(null);
const dayTasksModal = reactive({ open: false, label: '', tasks: [] });
const newComment   = ref('');
const quickStatus  = ref('');
const form         = reactive({
  title: '', description: '', department_id: '', assigned_to: '', priority: 'medium',
  status: 'pending', due_date: '', is_important: false,
  is_recurring: false, recurrence_type: 'weekly',
});
// Non-admins see a read-only "Assigned To" field (no picker) — this resolves what it should
// say. Matters once a non-admin can *edit* their own task (not just create it): the task may
// have been created unassigned, or reassigned by an admin afterwards, so it can't just always
// show "me" the way the create flow's default does.
const formAssigneeName = computed(() => {
  if (!form.assigned_to) return 'Unassigned';
  if (form.assigned_to === currentUserId.value) return currentUserName.value;
  return users.value.find(u => u.id === form.assigned_to)?.name ?? 'Unknown user';
});

// ─── Drag & drop state ────────────────────────────────────────────────────────
const dragTask    = ref(null);
const dragOverCol = ref('');

// ─── Board "show closed" toggle (persisted) ───────────────────────────────────
let _showClosedInit = false;
try { _showClosedInit = JSON.parse(localStorage.getItem('dtm_show_closed') ?? 'false'); }
catch { localStorage.removeItem('dtm_show_closed'); }
const showClosed = ref(_showClosedInit);
function toggleShowClosed() {
  showClosed.value = !showClosed.value;
  localStorage.setItem('dtm_show_closed', String(showClosed.value));
}

// ─── Table "expand columns" toggle (persisted) ─────────────────────────────
// Basic view = a handful of quick-glance columns (Date In / Deadline / Dept / Task).
// Expanded view adds Assigned To, Priority and Status. Purely a client-side render
// switch over the already-loaded tableTasks — no re-fetch needed.
let _tableExpandedInit = false;
try { _tableExpandedInit = JSON.parse(localStorage.getItem('dtm_table_expanded') ?? 'false'); }
catch { localStorage.removeItem('dtm_table_expanded'); }
const tableExpanded = ref(_tableExpandedInit);
function toggleTableExpanded() {
  tableExpanded.value = !tableExpanded.value;
  localStorage.setItem('dtm_table_expanded', String(tableExpanded.value));
}

function dateOnly(str) {
  return str ? str.split(',')[0] : '—';
}

// ─── Chart refs ───────────────────────────────────────────────────────────────
const deptChart   = ref(null);
const statusChart = ref(null);
const weeklyChart = ref(null);
let chartInstances = {};

// ─── Kanban columns ───────────────────────────────────────────────────────────
// Completed and Cancelled are hidden by default; toggle with the "Show closed" button.
const kanbanCols = computed(() => {
  const cols = [
    { status: 'pending',          label: 'Pending',          color: '#94a3b8' },
    { status: 'in_progress',      label: 'In Progress',      color: '#3b82f6' },
  ];
  if (showClosed.value) {
    cols.push({ status: 'completed', label: 'Completed', color: '#10b981' });
    if (isAdmin.value) cols.push({ status: 'cancelled', label: 'Cancelled', color: '#6b7280' });
  }
  return cols;
});

// ─── Calendar view ────────────────────────────────────────────────────────────
const CAL_WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const calendarMonth = ref(new Date());
const calendarViewMode = ref('grid'); // 'grid' | 'list' | 'important'

const calendarMonthLabel = computed(() =>
  calendarMonth.value.toLocaleDateString('default', { month: 'long', year: 'numeric' })
);

function calendarNav(delta) {
  calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1);
}

// Same closed-task visibility rule the Kanban board applies via kanbanCols/showClosed.
const calendarFilteredTasks = computed(() => {
  if (showClosed.value) return boardTasks.value;
  return boardTasks.value.filter(t => t.status !== 'completed' && t.status !== 'cancelled');
});

// Important tab: every flagged task regardless of month/year — this one is browsed by nothing
// but its own filters, so it isn't scoped to calendarMonth/calendarListYear like the other two modes.
const calendarImportantTasks = computed(() =>
  calendarFilteredTasks.value
    .filter(t => t.is_important)
    .slice()
    .sort((a, b) => {
      if (!a.due_date && !b.due_date) return a.title.localeCompare(b.title);
      if (!a.due_date) return 1;
      if (!b.due_date) return -1;
      return a.due_date.localeCompare(b.due_date) || a.title.localeCompare(b.title);
    })
);

// Grouped once per recompute so each day cell is an O(1) lookup instead of a re-filter.
const calendarTasksByDate = computed(() => {
  const map = {};
  for (const t of calendarFilteredTasks.value) {
    if (!t.due_date) continue;
    (map[t.due_date] ??= []).push(t);
  }
  return map;
});

// List toggle is a full-year board (Jan–Dec columns), navigated by year only — independent of the grid's month nav.
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const calendarListYear = ref(new Date().getFullYear());
function calendarListYearNav(delta) {
  calendarListYear.value += delta;
}
function dayOfMonth(due_date) {
  return due_date ? Number(due_date.split('-')[2]) : '';
}

// Same tasks + closed-visibility rule as the grid, bucketed by month for the given year.
const calendarYearMonths = computed(() => {
  const year = calendarListYear.value;
  const byMonth = Array.from({ length: 12 }, () => []);
  for (const t of calendarFilteredTasks.value) {
    if (!t.due_date) continue;
    const [ty, tm] = t.due_date.split('-').map(Number);
    if (ty === year) byMonth[tm - 1].push(t);
  }
  return MONTH_NAMES.map((name, i) => ({
    key: i,
    name,
    tasks: byMonth[i].slice().sort((a, b) => a.due_date.localeCompare(b.due_date) || a.title.localeCompare(b.title)),
  }));
});

const calendarDays = computed(() => {
  const monthStart = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth(), 1);
  const start = new Date(monthStart);
  const mondayOffset = (start.getDay() + 6) % 7; // Monday-first: 0=Mon...6=Sun
  start.setDate(start.getDate() - mondayOffset);
  const todayStr = toLocalDate(new Date());

  const days = [];
  for (let i = 0; i < 42; i++) {
    const date = new Date(start);
    date.setDate(start.getDate() + i);
    const iso = toLocalDate(date);
    days.push({
      key: `${iso}-${i}`,
      date: iso,
      day: date.getDate(),
      inMonth: date.getMonth() === calendarMonth.value.getMonth(),
      isToday: iso === todayStr,
    });
  }
  return days;
});

// Local Date(y, m, d) construction — never new Date(isoString), which parses
// as UTC and can shift the displayed day depending on the browser's timezone.
function openDayTasks(day) {
  const [y, m, d] = day.date.split('-').map(Number);
  dayTasksModal.label = new Date(y, m - 1, d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
  dayTasksModal.tasks = calendarTasksByDate.value[day.date] || [];
  dayTasksModal.open  = true;
}
function openDayTask(task) {
  dayTasksModal.open = false;
  openTaskDetail(task);
}

// ─── Computed ─────────────────────────────────────────────────────────────────
const statCards = computed(() => {
  const s = dashData.value.stats || {};
  return [
    { label: 'Total Tasks',      value: s.total      || 0, colorClass: 'blue',  icon: ICO.list,    filterKey: 'all' },
    { label: 'Pending',          value: s.pending    || 0, colorClass: 'gray',  icon: ICO.clock,   filterKey: 'pending' },
    { label: 'In Progress',      value: s.inProgress || 0, colorClass: 'blue',  icon: ICO.refresh, filterKey: 'in_progress' },
    { label: 'Completed',        value: s.completed  || 0, colorClass: 'green', icon: ICO.check,   filterKey: 'completed' },
    { label: 'Overdue',          value: s.overdue    || 0, colorClass: 'red',   icon: ICO.alert,   filterKey: 'overdue' },
  ];
});

const weeklyMonthLabel = computed(() => {
  if (!weeklyData.value.week_start) return '';
  const parts = weeklyData.value.week_start.split('/');
  if (parts.length < 3) return '';
  const d = new Date(parts[2], parts[1] - 1, parts[0]);
  return d.toLocaleString('default', { month: 'long', year: 'numeric' }).toUpperCase();
});

const weeklyDeptFocus = computed({
  get: () => weeklyDeptFocusRaw.value || (departments.value[0]?.id ?? ''),
  set: (v) => { weeklyDeptFocusRaw.value = v; },
});

const focusedDept = computed(() => departments.value.find(d => String(d.id) === String(weeklyDeptFocus.value)) || null);

const filteredWeeklyDepts = computed(() => {
  let depts = weeklyData.value.departments || [];
  if (weeklyGroupMode.value === 'department' && weeklyDeptFocus.value) {
    depts = depts.filter(d => String(d.department.id) === String(weeklyDeptFocus.value));
  }
  if (weeklyAssigneeFilter.value) {
    depts = depts.map(dept => ({
      ...dept,
      tasks: dept.tasks.filter(t => t.assignee === weeklyAssigneeFilter.value),
    }));
  }
  return depts.filter(d => d.tasks.length > 0);
});

// Flat, chronologically-sorted view of the same filtered data for "All Together" mode.
const weeklyFlatTasks = computed(() => {
  const all = filteredWeeklyDepts.value.flatMap(dept =>
    dept.tasks.map(t => ({ ...t, department: dept.department }))
  );
  return all.slice().sort((a, b) => (a.due_date_sort || '9999-99-99').localeCompare(b.due_date_sort || '9999-99-99'));
});

const weeklyIsEmpty = computed(() => (
  weeklyGroupMode.value === 'all' ? weeklyFlatTasks.value.length === 0 : filteredWeeklyDepts.value.length === 0
));

const weeklyEmptyMessage = computed(() => {
  const who = weeklyAssigneeFilter.value;
  if (weeklyGroupMode.value === 'department') {
    const name = focusedDept.value?.name || 'this department';
    return who ? `No tasks for ${who} in ${name} this week.` : `All clear — no outstanding tasks for ${name} this week.`;
  }
  if (who) return `No tasks for ${who} this week.`;
  return 'All clear — no outstanding tasks this week.';
});

const weeklyReportTitle = computed(() => {
  if (weeklyGroupMode.value === 'department') return `Outstanding Task Report — ${focusedDept.value?.name || 'Department'}`;
  if (weeklyGroupMode.value === 'all') return 'Outstanding Task Report — All Departments';
  return 'Outstanding Task Report — By Department';
});

const weeklySummary = computed(() => {
  const all = filteredWeeklyDepts.value.flatMap(d => d.tasks);
  return {
    total:   all.length,
    overdue: all.filter(t => t.is_overdue).length,
    depts:   filteredWeeklyDepts.value.length,
  };
});

const weeklyDisplayLabel = computed(() => {
  if (!weeklyData.value.week_start) return '';
  return `${weeklyData.value.week_start} – ${weeklyData.value.week_end}`;
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function priorityColor(p) {
  return { low: '#10b981', medium: '#3b82f6', high: '#f59e0b', critical: '#ef4444' }[p] || '#94a3b8';
}

function statusLabel(s) {
  return { pending: 'Pending', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' }[s] || s;
}

function recurrenceLabel(type) {
  return { daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly', quarterly: 'Quarterly' }[type] || 'Weekly';
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
}

function toLocalDate(d) {
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function weekNav(delta) {
  const d = new Date(weeklyWeekStart.value + 'T00:00:00');
  d.setDate(d.getDate() + delta * 7);
  weeklyWeekStart.value = toLocalDate(d);
  loadWeeklyData();
}

function formatDate(dt) {
  if (!dt) return '';
  const d = new Date(dt);
  if (isNaN(d)) return dt;
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Computed map so the template doesn't re-filter on every drag event
const boardByStatus = computed(() => {
  const map = Object.fromEntries(kanbanCols.value.map(c => [c.status, []]));
  for (const t of boardTasks.value) {
    if (map[t.status] !== undefined) map[t.status].push(t);
  }
  for (const arr of Object.values(map)) {
    arr.sort((a, b) => (a.board_position ?? 9999) - (b.board_position ?? 9999));
  }
  return map;
});

// ─── My Work buckets (user list view) ─────────────────────────────────────────
const doneOpen = ref(false);
const PRIORITY_RANK = { critical: 0, high: 1, medium: 2, low: 3 };

const buckets = computed(() => {
  const today    = new Date();
  const todayStr = toLocalDate(today);
  const dow      = today.getDay();                          // 0 = Sunday
  const weekEnd  = new Date(today.getFullYear(), today.getMonth(), today.getDate() + (dow === 0 ? 0 : 7 - dow));
  const weekEndStr = toLocalDate(weekEnd);

  const out = { overdue: [], today: [], thisWeek: [], later: [], noDate: [], done: [] };
  for (const t of boardTasks.value) {
    if (t.status === 'completed') { out.done.push(t); continue; }
    if (t.status === 'cancelled') continue;                 // hidden for users
    const d = t.due_date;                                   // 'YYYY-MM-DD' or null
    if (!d)                   out.noDate.push(t);
    else if (d < todayStr)    out.overdue.push(t);
    else if (d === todayStr)  out.today.push(t);
    else if (d <= weekEndStr) out.thisWeek.push(t);
    else                      out.later.push(t);
  }

  const byDue = (a, b) => {
    const ad = a.due_date || '9999-12-31', bd = b.due_date || '9999-12-31';
    if (ad !== bd) return ad < bd ? -1 : 1;
    return (PRIORITY_RANK[a.priority] ?? 9) - (PRIORITY_RANK[b.priority] ?? 9);
  };
  const byPriority = (a, b) => (PRIORITY_RANK[a.priority] ?? 9) - (PRIORITY_RANK[b.priority] ?? 9);
  out.overdue.sort(byDue); out.today.sort(byPriority); out.thisWeek.sort(byDue);
  out.later.sort(byDue);   out.noDate.sort(byPriority);
  return out;
});

const bucketGroups = computed(() => [
  { key: 'overdue',  label: 'Overdue',     tone: 'tone-danger', tasks: buckets.value.overdue },
  { key: 'today',    label: 'Due Today',   tone: 'tone-warn',   tasks: buckets.value.today },
  { key: 'thisWeek', label: 'This Week',   tone: '',            tasks: buckets.value.thisWeek },
  { key: 'later',    label: 'Later',       tone: '',            tasks: buckets.value.later },
  { key: 'noDate',   label: 'No Due Date', tone: '',            tasks: buckets.value.noDate },
]);

const activeCount = computed(() => bucketGroups.value.reduce((n, g) => n + g.tasks.length, 0));

// ─── People view: tasks grouped by assignee ────────────────────────────────────
const PRIO_RANK_P = { critical: 0, high: 1, medium: 2, low: 3 };
const peopleGroups = computed(() => {
  let tasks = peopleTasks.value;

  if (peopleFilters.user_id)
    tasks = tasks.filter(t => t.assigned_to == peopleFilters.user_id);
  if (peopleFilters.department_id)
    tasks = tasks.filter(t => t.department_id == peopleFilters.department_id);
  if (peopleFilters.status === 'overdue')
    tasks = tasks.filter(t => t.is_overdue);
  else if (peopleFilters.status)
    tasks = tasks.filter(t => t.status === peopleFilters.status);
  if (peopleSearch.value.trim()) {
    const q = peopleSearch.value.trim().toLowerCase();
    tasks = tasks.filter(t => t.title.toLowerCase().includes(q));
  }

  const map = new Map();
  for (const task of tasks) {
    const uid = task.assigned_to ?? '__none__';
    if (!map.has(uid)) map.set(uid, { user: task.assignee ?? null, tasks: [] });
    map.get(uid).tasks.push(task);
  }

  const sortTasks = arr => [...arr].sort((a, b) => {
    if (a.is_overdue !== b.is_overdue) return a.is_overdue ? -1 : 1;
    const pd = (PRIO_RANK_P[a.priority] ?? 9) - (PRIO_RANK_P[b.priority] ?? 9);
    if (pd !== 0) return pd;
    return (a.due_date || '') < (b.due_date || '') ? -1 : 1;
  });

  const result = [];
  for (const [uid, grp] of map) {
    const active  = grp.tasks.filter(t => !['completed','cancelled'].includes(t.status)).length;
    const done    = grp.tasks.filter(t => t.status === 'completed').length;
    const overdue = grp.tasks.filter(t => t.is_overdue).length;
    result.push({ key: uid, user: grp.user, tasks: sortTasks(grp.tasks), active, done, overdue, total: grp.tasks.length });
  }

  result.sort((a, b) => {
    if (!a.user) return -1;
    if (!b.user) return 1;
    return (a.user.name || '').localeCompare(b.user.name || '');
  });
  return result;
});

// ─── Workflow rules (mirror of backend assertCanTransition) ────────────────────
// Client-side guard so the board doesn't fire transitions the server will reject.
function canTransitionClient(task, to) {
  const isAdminUser = isAdmin.value;
  const isAssignee  = task.assigned_to === currentUserId.value;

  // Must be involved
  if (!isAdminUser && !isAssignee && task.created_by !== currentUserId.value) return false;
  // Cancelling is admin-only
  if (to === 'cancelled' && !isAdminUser) return false;
  // Reopening is admin-only
  if (['completed', 'cancelled'].includes(task.status) && !isAdminUser) return false;
  return true;
}

async function advanceStatus(task) {
  const next = { pending: 'in_progress' }[task.status];
  if (!next) return;
  try {
    await api.put(`/v1/dept/tasks/${task.id}/status`, { status: next });
    task.status = next;
    showToast('Task started');
    refreshCurrentView(true);
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not update task', 'error');
  }
}

async function completeTask(task) {
  if (task.status === 'completed') return;
  try {
    await api.put(`/v1/dept/tasks/${task.id}/status`, { status: 'completed' });
    task.status = 'completed';
    showToast('Task completed');
    refreshCurrentView(true);
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not update task', 'error');
  }
}

// ─── Stat card drill-through ──────────────────────────────────────────────────
function statCardClick(card) {
  if (!card.filterKey) return;
  tableFilters.status      = card.filterKey === 'all' ? '' : card.filterKey;
  tableFilters.assigned_to = '';  // show all users so the count matches the card
  currentView.value        = 'table';
  loadTableTasks();
}

// ─── Notification helpers ─────────────────────────────────────────────────────
const unreadNotifs = computed(() => notifications.value.filter(n => !n.read_at));

function notifTypeLabel(type) {
  return { assigned: 'ASSIGNED', completed: 'COMPLETED', overdue: 'OVERDUE' }[type] ?? 'TASK';
}

async function dismissNotif(n) {
  try {
    await api.post('/v1/dept/notifications/read', { id: n.id });
  } catch (_) { /* ignore */ }
  n.read_at = new Date().toISOString();
}

async function openNotifTask(n) {
  if (!n.read_at) dismissNotif(n);
  if (n.task_id) openTaskDetail({ id: n.task_id });
}

// ─── Toast notifications ──────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const id = Date.now();
  toasts.value.push({ id, msg, type });
  setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 3000);
}

// ─── API calls ────────────────────────────────────────────────────────────────
async function loadMeta() {
  try {
    const [deptRes, userRes] = await Promise.all([
      api.get('/v1/dept/departments'),
      api.get('/v1/dept/users'),
    ]);
    departments.value = deptRes.data;
    users.value       = userRes.data;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load departments or users', 'error');
  }
}

async function loadDashboard() {
  dashLoading.value = true;
  try {
    const params = {};
    if (!isAdmin.value && currentUserId.value) params.user_id = currentUserId.value;
    const res = await api.get('/v1/dept/dashboard', { params });
    dashData.value = res.data;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load dashboard', 'error');
    return;
  } finally {
    dashLoading.value = false;
  }
  // nextTick must run after dashLoading = false so the v-else canvas elements
  // are in the DOM before Chart.js tries to bind to them.
  await nextTick();
  buildCharts();
}

async function loadBoardTasks(silent = false) {
  if (!silent) boardLoading.value = true;
  try {
    const params = { all: true };
    if (boardFilters.department_id) params.department_id = boardFilters.department_id;
    if (boardFilters.assigned_to)   params.assigned_to   = boardFilters.assigned_to;
    const res = await api.get('/v1/dept/tasks', { params });
    boardTasks.value = res.data;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load tasks', 'error');
  } finally {
    if (!silent) boardLoading.value = false;
  }
}

async function loadTableTasks() {
  tableLoading.value = true;
  try {
    const params = { sort_by: tableSort.field, sort_dir: tableSort.dir, page: tablePage.value };
    if (tableFilters.search)        params.search        = tableFilters.search;
    if (tableFilters.department_id) params.department_id = tableFilters.department_id;
    if (tableFilters.status)        params.status        = tableFilters.status;
    if (tableFilters.priority)      params.priority      = tableFilters.priority;
    if (tableFilters.assigned_to)   params.assigned_to   = tableFilters.assigned_to;
    if (tableFilters.date_from)     params.date_from     = tableFilters.date_from;
    if (tableFilters.date_to)       params.date_to       = tableFilters.date_to;
    const res = await api.get('/v1/dept/tasks', { params });
    tableTasks.value  = res.data.data;
    tableLastPage.value = res.data.last_page;
    tableTotal.value    = res.data.total;
    tablePageFrom.value = res.data.from ?? 0;
    tablePageTo.value   = res.data.to   ?? 0;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load tasks', 'error');
  } finally {
    tableLoading.value = false;
  }
}

async function loadWeeklyData() {
  try {
    const params = weeklyWeekStart.value ? { week_start: weeklyWeekStart.value } : {};
    const res = await api.get('/v1/dept/weekly', { params });
    weeklyData.value = res.data;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load weekly report', 'error');
  }
}

async function loadNotifications() {
  try {
    const res = await api.get('/v1/dept/notifications');
    notifications.value = res.data.notifications;
  } catch (_) {
    // Non-critical background fetch — fail quietly, same as dismissNotif().
  }
}

async function markAllRead() {
  try {
    await api.post('/v1/dept/notifications/read');
    notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
    showToast('All notifications marked as read');
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not mark notifications as read', 'error');
  }
}

// ─── Task CRUD ────────────────────────────────────────────────────────────────
function openTaskModal(task = null, deptId = null, dueDate = null) {
  editTask.value = task;
  if (task) {
    Object.assign(form, {
      title: task.title, description: task.description || '', department_id: task.department_id,
      assigned_to: task.assigned_to || '', priority: task.priority, status: task.status,
      due_date: task.due_date || '', is_important: !!task.is_important,
      is_recurring: !!task.is_recurring, recurrence_type: task.recurrence_type || 'weekly',
    });
  } else {
    Object.assign(form, {
      title: '', description: '', department_id: deptId || '',
      assigned_to: isAdmin.value ? '' : currentUserId.value,
      priority: 'medium', status: 'pending', due_date: dueDate || '', is_important: false,
      is_recurring: false, recurrence_type: 'weekly',
    });
  }
  formError.value = '';
  showModal.value = true;
}

async function saveTask() {
  if (!form.title.trim() || !form.department_id) {
    formError.value = 'Title and Department are required.';
    return;
  }
  saving.value = true;
  try {
    const payload = { ...form };
    if (!payload.assigned_to) delete payload.assigned_to;
    if (!payload.due_date)    delete payload.due_date;
    if (!payload.is_recurring) payload.recurrence_type = null;

    if (editTask.value) {
      await api.put(`/v1/dept/tasks/${editTask.value.id}`, payload);
    } else {
      await api.post('/v1/dept/tasks', payload);
    }
    showModal.value = false;
    showToast(editTask.value ? 'Task updated' : 'Task created');
    refreshCurrentView();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to save task.';
  } finally {
    saving.value = false;
  }
}

function triggerDeleteConfirm(message, fn) {
  deleteConfirmMsg.value  = message;
  deletePendingFn.value   = fn;
  showDeleteConfirm.value = true;
}
async function confirmDelete() {
  showDeleteConfirm.value = false;
  try {
    await deletePendingFn.value?.();
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not delete. Please try again.', 'error');
  } finally {
    deletePendingFn.value = null;
  }
}
function deleteTask(id) {
  triggerDeleteConfirm('Are you sure you want to delete this task? This cannot be undone.', async () => {
    await api.delete(`/v1/dept/tasks/${id}`);
    if (selectedTask.value?.id === id) selectedTask.value = null;
    showToast('Task deleted');
    refreshCurrentView();
  });
}

async function openTaskDetail(task) {
  try {
    const res = await api.get(`/v1/dept/tasks/${task.id}`);
    selectedTask.value = res.data;
    quickStatus.value  = res.data.status;
  } catch (e) {
    if (e.response?.status === 404) showToast('This task no longer exists.', 'error');
    else showToast('Could not load task details.', 'error');
  }
}

async function quickUpdateStatus() {
  if (!quickStatus.value || !selectedTask.value) return;
  if (quickStatus.value === selectedTask.value.status) return;
  const prev = selectedTask.value.status;
  try {
    await api.put(`/v1/dept/tasks/${selectedTask.value.id}/status`, { status: quickStatus.value });
    selectedTask.value.status = quickStatus.value;
    showToast('Status updated');
    refreshCurrentView(true);
  } catch (e) {
    quickStatus.value = prev; // revert the select
    showToast(e.response?.data?.message || 'Could not update status', 'error');
  }
}

// Assignee steps their own task forward (Start → Complete)
async function detailAdvance(status) {
  if (!selectedTask.value) return;
  try {
    await api.put(`/v1/dept/tasks/${selectedTask.value.id}/status`, { status });
    selectedTask.value.status = status;
    quickStatus.value = status;
    showToast({
      in_progress: 'Task started',
      completed:   'Task completed',
    }[status] || 'Status updated');
    refreshCurrentView(true);
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not update status', 'error');
  }
}

function setBoardScope(myTasksOnly) {
  boardFilters.assigned_to = myTasksOnly ? currentUserId.value : '';
  loadBoardTasks();
}

function setTableScope(myTasksOnly) {
  tableFilters.assigned_to = myTasksOnly ? currentUserId.value : '';
  tablePage.value = 1;
  loadTableTasks();
}

// ─── Comments ─────────────────────────────────────────────────────────────────
async function addComment() {
  const text = newComment.value.trim();
  if (!text || !selectedTask.value) return;
  try {
    const res = await api.post(`/v1/dept/tasks/${selectedTask.value.id}/comments`, { comment: text });
    selectedTask.value.comments = [res.data, ...(selectedTask.value.comments || [])];
    newComment.value = '';
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not post comment', 'error');
  }
}

function deleteComment(commentId) {
  triggerDeleteConfirm('Delete this comment?', async () => {
    await api.delete(`/v1/dept/tasks/${selectedTask.value.id}/comments/${commentId}`);
    selectedTask.value.comments = selectedTask.value.comments.filter(c => c.id !== commentId);
  });
}

// ─── Attachments ──────────────────────────────────────────────────────────────
const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024; // 20 MB — must match Laravel max:20480 and .user.ini

async function uploadAttachment(event) {
  const file = event.target.files?.[0];
  if (!file || !selectedTask.value) return;
  if (file.size > MAX_ATTACHMENT_BYTES) {
    showToast(`File too large (${formatFileSize(file.size)}). Maximum is 20 MB.`, 'error');
    event.target.value = '';
    return;
  }
  const fd = new FormData();
  fd.append('file', file);
  attachmentUploading.value = true;
  try {
    const res = await api.post(`/v1/dept/tasks/${selectedTask.value.id}/attachments`, fd);
    selectedTask.value.attachments = [res.data, ...(selectedTask.value.attachments || [])];
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not upload file', 'error');
  } finally {
    attachmentUploading.value = false;
    event.target.value = '';
  }
}

function deleteAttachmentFile(attachmentId) {
  triggerDeleteConfirm('Delete this attachment?', async () => {
    await api.delete(`/v1/dept/tasks/${selectedTask.value.id}/attachments/${attachmentId}`);
    selectedTask.value.attachments = selectedTask.value.attachments.filter(a => a.id !== attachmentId);
  });
}

function formatFileSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

// ─── File extension helpers ───────────────────────────────────────────────────
const _EXT_STYLES = {
  pdf:  ['#fee2e2','#991b1b'],
  doc:  ['#dbeafe','#1d4ed8'], docx: ['#dbeafe','#1d4ed8'],
  xls:  ['#dcfce7','#15803d'], xlsx: ['#dcfce7','#15803d'], csv: ['#dcfce7','#15803d'],
  ppt:  ['#fff7ed','#c2410c'], pptx: ['#fff7ed','#c2410c'],
  jpg:  ['#ede9fe','#6d28d9'], jpeg: ['#ede9fe','#6d28d9'],
  png:  ['#ede9fe','#6d28d9'], gif:  ['#ede9fe','#6d28d9'], webp: ['#ede9fe','#6d28d9'],
  zip:  ['#fef3c7','#92400e'],
  txt:  ['var(--surface-2)','var(--text-2)'],
};
function fileExtStyle(filename) {
  const ext = (filename || '').split('.').pop().toLowerCase();
  return _EXT_STYLES[ext] ?? ['var(--surface-2)', 'var(--text-2)'];
}
function fileExtLabel(filename) {
  const ext = (filename || '').split('.').pop().toUpperCase();
  return ext || 'FILE';
}

const _AVATAR_PALETTE = ['#1d4ed8','#7c3aed','#db2777','#d97706','#059669','#0891b2','#78350f','#dc2626'];
function userAvatarColor(uid) {
  return _AVATAR_PALETTE[(uid ?? 0) % _AVATAR_PALETTE.length];
}

// ─── Attachments tab ─────────────────────────────────────────────────────────
async function loadAllAttachments() {
  attachmentsLoading.value = true;
  try {
    const params = { page: attachmentsPage.value };
    if (attachmentsSearch.value) params.search = attachmentsSearch.value;
    const res = await api.get('/v1/dept/attachments', { params });
    allAttachments.value      = res.data.data;
    attachmentsLastPage.value = res.data.last_page;
    attachmentsTotal.value    = res.data.total;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load files', 'error');
  } finally {
    attachmentsLoading.value = false;
  }
}

let attachmentSearchTimer = null;
function debouncedAttachmentSearch() {
  clearTimeout(attachmentSearchTimer);
  attachmentSearchTimer = setTimeout(() => { attachmentsPage.value = 1; loadAllAttachments(); }, 350);
}

function openRename(a) {
  renameModal.show     = true;
  renameModal.id       = a.id;
  renameModal.filename = a.filename;
  renameModal.saving   = false;
}

async function saveRename() {
  if (!renameModal.filename.trim() || !renameModal.id) return;
  renameModal.saving = true;
  try {
    await api.put(`/v1/dept/attachments/${renameModal.id}`, { filename: renameModal.filename.trim() });
    const a = allAttachments.value.find(x => x.id === renameModal.id);
    if (a) a.filename = renameModal.filename.trim();
    renameModal.show = false;
    showToast('File renamed');
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not rename', 'error');
  } finally {
    renameModal.saving = false;
  }
}

function deleteAttachmentFromTab(a) {
  triggerDeleteConfirm(`Delete "${a.filename}"? This cannot be undone.`, async () => {
    await api.delete(`/v1/dept/attachments/${a.id}`);
    allAttachments.value   = allAttachments.value.filter(x => x.id !== a.id);
    attachmentsTotal.value = Math.max(0, attachmentsTotal.value - 1);
    showToast('Attachment deleted');
  });
}

async function loadPeopleTasks(silent = false) {
  if (!silent) peopleLoading.value = true;
  try {
    const res = await api.get('/v1/dept/tasks', { params: { all: true } });
    peopleTasks.value = res.data;
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load tasks', 'error');
  } finally {
    if (!silent) peopleLoading.value = false;
  }
}

function togglePeopleUser(key) {
  peopleCollapsed[key] = !peopleCollapsed[key];
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
  dragTask.value = null;
  if (!task || task.status === targetStatus) return;

  // Block moves the server would reject (e.g. non-admin reopening a finished task)
  if (!canTransitionClient(task, targetStatus)) {
    showToast('You can’t move this task there.', 'error');
    return;
  }

  const t = boardTasks.value.find(b => b.id === task.id);
  if (!t) return;
  const prevStatus = t.status;
  const prevPos    = t.board_position;
  const position   = boardTasks.value.filter(b => b.id !== task.id && b.status === targetStatus).length;

  // Optimistic move, revert if the server says no
  t.status = targetStatus;
  t.board_position = position;
  try {
    await api.put(`/v1/dept/tasks/${task.id}/status`, { status: targetStatus, board_position: position });
  } catch (e) {
    t.status = prevStatus;
    t.board_position = prevPos;
    showToast(e.response?.data?.message || 'Could not move task', 'error');
  }
}

// ─── Sorting ──────────────────────────────────────────────────────────────────
function toggleSort(field) {
  if (tableSort.field === field) {
    tableSort.dir = tableSort.dir === 'asc' ? 'desc' : 'asc';
  } else {
    tableSort.field = field;
    tableSort.dir   = 'asc';
  }
  tablePage.value = 1;
  loadTableTasks();
}

function clearTableFilters() {
  const defaultAssignee = isAdmin.value ? '' : currentUserId.value;
  Object.assign(tableFilters, { search: '', department_id: '', status: '', priority: '', assigned_to: defaultAssignee, date_from: '', date_to: '' });
  tablePage.value = 1;
  loadTableTasks();
}

// Quick date-range presets — Table's stand-in for the old dedicated "This Week"/History date pickers.
function quickDateFilter(range) {
  const now = new Date();
  if (range === 'week') {
    const day    = now.getDay();
    const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() + (day === 0 ? -6 : 1 - day));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    tableFilters.date_from = toLocalDate(monday);
    tableFilters.date_to   = toLocalDate(sunday);
  } else if (range === 'month') {
    tableFilters.date_from = toLocalDate(new Date(now.getFullYear(), now.getMonth(), 1));
    tableFilters.date_to   = toLocalDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
  }
  tableFilterChange();
}

// ─── Export / Print ───────────────────────────────────────────────────────────

const PRINT_CSS = `
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,'Helvetica Neue',sans-serif; font-size:10px; color:#000; background:#fff; }
@page { size:A4 landscape; margin:12mm 10mm; }
@media print { body { padding:0; } }

/* ── Document header ── */
.doc-co { font-size:8px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:#555; margin-bottom:2px; }
.doc-title-line { display:flex; align-items:flex-end; justify-content:space-between; border-bottom:2px solid #000; padding-bottom:6px; margin-bottom:8px; }
.doc-title { font-size:19px; font-weight:700; line-height:1; }
.doc-meta { display:flex; gap:22px; font-size:10px; margin-top:4px; }
.doc-meta b { font-weight:700; }
.doc-generated { font-size:8.5px; color:#555; padding-bottom:2px; }

/* ── Summary bar ── */
.doc-summary { margin:8px 0 14px; padding:5px 10px; background:#e8e8e8; border-left:3px solid #000; font-size:10px; font-weight:600; display:flex; gap:20px; }
.doc-summary .s-danger { font-weight:800; }

/* ── Weekly grid (date × dept columns) ── */
.wkly { width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed; }
.wkly thead th { background:#000; color:#fff; border:1px solid #000; padding:7px 8px; font-weight:700; text-align:left; font-size:10.5px; text-transform:uppercase; letter-spacing:0.05em; word-break:break-word; vertical-align:middle; }
.wkly thead th.th-date { width:46px; text-align:center; }
.wkly tbody td { border:1px solid #ccc; padding:5px 6px; vertical-align:top; }
.wkly tbody td.td-date { background:#ebebeb; font-weight:700; font-size:9px; text-align:center; white-space:nowrap; vertical-align:middle; }
.wkly tbody tr:nth-child(even) td:not(.td-date) { background:#fafafa; }
.ti { font-size:9.5px; line-height:1.3; padding:2px 0; }
.ti + .ti { border-top:1px dotted #ccc; margin-top:3px; padding-top:3px; }
.ti-name { display:block; }
.ti-name.ov { font-weight:700; }
.ti-sub { display:block; font-size:8px; color:#555; margin-top:1px; }
.ov-tag { display:inline-block; font-size:7px; font-weight:800; background:#000; color:#fff; padding:1px 3px; margin-left:3px; vertical-align:middle; letter-spacing:0.04em; }
.imp-mark { color:#f59e0b; margin-right:3px; }

/* ── Flat table (history / table export) ── */
.flat { width:100%; border-collapse:collapse; font-size:10px; }
.flat thead th { background:#000; color:#fff; border:1px solid #000; padding:7px 10px; font-weight:700; text-align:left; font-size:10.5px; text-transform:uppercase; letter-spacing:0.05em; }
.flat tbody td { border:1px solid #ccc; padding:5px 8px; vertical-align:middle; }
.flat tbody tr:nth-child(even) td { background:#f5f5f5; }
.flat .td-ov { font-weight:700; }
.flat .td-date-ov { font-weight:700; }
.flat .td-muted { color:#555; font-size:9.5px; }
.flat .td-dept { font-size:8.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
.st { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
.st-ov { font-weight:800; }

/* ── Footer ── */
.doc-footer { margin-top:14px; padding-top:6px; border-top:1px solid #aaa; font-size:8px; color:#666; display:flex; justify-content:space-between; }
.empty-msg { text-align:center; padding:40px; color:#888; font-size:12px; }
`;

const _SL = s => ({ pending:'Pending', in_progress:'In Progress', completed:'Completed', cancelled:'Cancelled' }[s] ?? s);
const _SU = s => _SL(s).toUpperCase();
const _PU = p => (p ?? '—').toUpperCase();

// `w` defaults to opening synchronously here for callers (like printWeekly) that
// already have their data in hand. Callers that must `await` an API call first
// (like exportTable) should open the window *before* that await — inside the
// original click's call stack — and pass the handle in, or most browsers'
// popup blockers will silently swallow a window.open() that fires after a delay.
function openPrintWindow(html, w = window.open('', '_blank', 'width=900,height=700')) {
  if (!w) {
    showToast('Pop-up blocked — please allow pop-ups for this site to print.', 'error');
    return;
  }
  w.document.write(html);
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 500);
}

function wrapDoc(title, meta, body) {
  const footer = `<div class="doc-footer"><span>Bluedale Group of Companies — Task Manager</span><span>${new Date().toLocaleString('en-GB')}</span></div>`;
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title><style>${PRINT_CSS}</style></head><body>${meta}${body}${footer}</body></html>`;
}

function flatTable(tasks, emptyMsg) {
  if (!tasks.length) return `<p class="empty-msg">${emptyMsg}</p>`;
  const rows = tasks.map(t => {
    const ov   = t.is_overdue;
    const dept = t.department?.name || t.department || '—';
    const who  = t.assignee?.name  || t.assignee  || '—';
    const date = t.due_date_fmt || t.due_date || '—';
    return `<tr>
      <td ${ov ? 'class="td-ov"' : ''}>${t.is_important ? '<span class="imp-mark">★</span>' : ''}${t.title}</td>
      <td class="td-dept">${dept}</td>
      <td class="td-muted">${who}</td>
      <td><span class="st">${_PU(t.priority)}</span></td>
      <td ${ov ? 'class="td-date-ov"' : ''}>${date}</td>
      <td><span class="st ${ov ? 'st-ov' : ''}">${ov ? 'OVERDUE' : _SU(t.status)}</span></td>
    </tr>`;
  }).join('');
  return `<table class="flat"><thead><tr><th>Task</th><th>Department</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function printWeekly() {
  const depts   = filteredWeeklyDepts.value;
  const summary = weeklySummary.value;
  const ws      = weeklyData.value.week_start || '';
  const we      = weeklyData.value.week_end   || '';
  const fmtNow  = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });

  const title = weeklyReportTitle.value;
  const deptChip = (weeklyGroupMode.value === 'department' && focusedDept.value)
    ? `<span style="color:${focusedDept.value.color}">${focusedDept.value.name}</span>`
    : `<span>${summary.depts} dept${summary.depts !== 1 ? 's' : ''}</span>`;

  const meta = `<div style="margin-bottom:12px">
  <div class="doc-co">Bluedale Group of Companies</div>
  <div class="doc-title-line">
    <div>
      <div class="doc-title">${title}</div>
      <div class="doc-meta"><span><b>Month:</b> ${weeklyMonthLabel.value}</span><span><b>Week:</b> ${ws} to ${we}</span></div>
    </div>
    <div class="doc-generated">Generated: ${fmtNow}</div>
  </div>
</div>
<div class="doc-summary">
  <span>${summary.total} outstanding</span>
  ${summary.overdue ? `<span class="s-danger">${summary.overdue} overdue</span>` : ''}
  ${deptChip}
</div>`;

  if (weeklyIsEmpty.value) {
    openPrintWindow(wrapDoc(title, meta, `<p class="empty-msg">${weeklyEmptyMessage.value}</p>`));
    return;
  }

  // "All Together" prints as one flat, chronologically-sorted table — same shape as the on-screen view.
  if (weeklyGroupMode.value === 'all') {
    openPrintWindow(wrapDoc(title, meta, flatTable(weeklyFlatTasks.value, 'No outstanding tasks this week.')));
    return;
  }

  // Collect and sort all unique due dates (format "d/m" e.g. "15/6")
  const dateSet = new Set();
  depts.forEach(d => d.tasks.forEach(t => dateSet.add(t.due_date || '')));
  const allDates = [...dateSet].sort((a, b) => {
    if (!a) return 1;
    if (!b) return -1;
    const [da, ma] = a.split('/').map(Number);
    const [db, mb] = b.split('/').map(Number);
    return ma !== mb ? ma - mb : da - db;
  });

  // Department column headers
  const deptTh = depts.map(d =>
    `<th>${d.department.name}<br><span style="font-weight:400;font-size:7.5px;opacity:0.75">${d.tasks.length} task${d.tasks.length !== 1 ? 's' : ''}</span></th>`
  ).join('');

  // One row per unique due date; each cell = tasks for that dept on that date
  const bodyRows = allDates.map(date => {
    const cells = depts.map(d => {
      const slot = d.tasks.filter(t => (t.due_date || '') === date);
      if (!slot.length) return '<td></td>';
      const items = slot.map(t => {
        const ov = t.is_overdue;
        return `<div class="ti">
          <span class="ti-name ${ov ? 'ov' : ''}">${t.is_important ? '<span class="imp-mark">★</span>' : ''}${t.title}${ov ? '<span class="ov-tag">OVERDUE</span>' : ''}</span>
          <span class="ti-sub">${t.assignee ? t.assignee + ' · ' : ''}${_PU(t.priority)} · ${ov ? 'OVERDUE' : _SU(t.status)}</span>
        </div>`;
      }).join('');
      return `<td>${items}</td>`;
    }).join('');
    return `<tr><td class="td-date">${date || '—'}</td>${cells}</tr>`;
  }).join('');

  const grid = `<table class="wkly"><thead><tr><th class="th-date">Due</th>${deptTh}</tr></thead><tbody>${bodyRows}</tbody></table>`;
  openPrintWindow(wrapDoc(title, meta, grid));
}

async function exportTable() {
  // Open the window synchronously, inside the click's own call stack, so popup
  // blockers see it as a direct response to the user gesture — see openPrintWindow().
  const printWin = window.open('', '_blank', 'width=900,height=700');
  if (!printWin) {
    showToast('Pop-up blocked — please allow pop-ups for this site to print.', 'error');
    return;
  }

  const params = { all: true, sort_by: tableSort.field, sort_dir: tableSort.dir };
  if (tableFilters.search)        params.search        = tableFilters.search;
  if (tableFilters.department_id) params.department_id = tableFilters.department_id;
  if (tableFilters.status)        params.status        = tableFilters.status;
  if (tableFilters.priority)      params.priority      = tableFilters.priority;
  if (tableFilters.assigned_to)   params.assigned_to   = tableFilters.assigned_to;
  if (tableFilters.date_from)     params.date_from     = tableFilters.date_from;
  if (tableFilters.date_to)       params.date_to       = tableFilters.date_to;

  let tasks;
  try {
    const res = await api.get('/v1/dept/tasks', { params });
    tasks = res.data;
  } catch (e) {
    printWin.close();
    showToast(e.response?.data?.message || 'Could not export tasks', 'error');
    return;
  }

  const fmtNow = new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
  const period = (tableFilters.date_from || tableFilters.date_to)
    ? `<div class="doc-meta"><span><b>Period:</b> ${tableFilters.date_from || '…'} – ${tableFilters.date_to || '…'}</span></div>`
    : '';

  const meta = `<div style="margin-bottom:12px">
  <div class="doc-co">Bluedale Group of Companies</div>
  <div class="doc-title-line">
    <div><div class="doc-title">Task List</div>${period}</div>
    <div class="doc-generated">${tasks.length} task${tasks.length !== 1 ? 's' : ''} &nbsp;·&nbsp; Generated: ${fmtNow}</div>
  </div>
</div>`;

  openPrintWindow(wrapDoc('Task List', meta, flatTable(tasks, 'No tasks match the current filters.')), printWin);
}

// ─── Export (CSV / Excel download) ─────────────────────────────────────────────
const EXPORT_COLUMNS = [
  { key: 'no',          label: 'No' },
  { key: 'title',       label: 'Task' },
  { key: 'department',  label: 'Department' },
  { key: 'assigned_to', label: 'Assigned To' },
  { key: 'priority',    label: 'Priority' },
  { key: 'status',      label: 'Status' },
  { key: 'due_date',    label: 'Due Date' },
  { key: 'created_by',  label: 'Created By' },
  { key: 'created_at',  label: 'Created On' },
  { key: 'description', label: 'Description' },
];
const exportModal = ref({ open: false, loading: false });
const exportCols  = ref(EXPORT_COLUMNS.map(c => ({ ...c, checked: true })));

function openExportModal() {
  exportModal.value = { open: true, loading: false };
}

async function executeExport(format) {
  const cols = exportCols.value.filter(c => c.checked);
  if (!cols.length) return;
  exportModal.value.loading = true;
  try {
    const params = new URLSearchParams({ cols: cols.map(c => c.key).join(','), format });
    params.set('sort_by', tableSort.field);
    params.set('sort_dir', tableSort.dir);
    if (tableFilters.search)        params.set('search', tableFilters.search);
    if (tableFilters.department_id) params.set('department_id', tableFilters.department_id);
    if (tableFilters.status)        params.set('status', tableFilters.status);
    if (tableFilters.priority)      params.set('priority', tableFilters.priority);
    if (tableFilters.assigned_to)   params.set('assigned_to', tableFilters.assigned_to);
    if (tableFilters.date_from)     params.set('date_from', tableFilters.date_from);
    if (tableFilters.date_to)       params.set('date_to', tableFilters.date_to);

    const token = localStorage.getItem('crm_token');
    const resp  = await fetch(`/api/v1/dept/tasks/export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!resp.ok) throw new Error(`Server error ${resp.status}`);
    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `Task_Export_${new Date().toISOString().slice(0, 10)}.${format}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 500);
    exportModal.value.open = false;
    showToast('Export downloaded');
  } catch (e) {
    showToast('Could not export tasks', 'error');
  } finally {
    exportModal.value.loading = false;
  }
}

// ─── Calendar export (Excel, month-grid layout — CSV can't represent a grid) ──
const calendarExportModal = ref({ open: false, loading: false, includeClosed: false });

function openCalendarExportModal() {
  calendarExportModal.value = { open: true, loading: false, includeClosed: showClosed.value };
}

async function exportCalendar() {
  calendarExportModal.value.loading = true;
  try {
    const month  = toLocalDate(calendarMonth.value).slice(0, 7); // 'YYYY-MM'
    const params = new URLSearchParams({ month });
    if (boardFilters.department_id)        params.set('department_id', boardFilters.department_id);
    if (boardFilters.assigned_to)          params.set('assigned_to', boardFilters.assigned_to);
    if (calendarExportModal.value.includeClosed) params.set('show_closed', '1');

    const token = localStorage.getItem('crm_token');
    const resp  = await fetch(`/api/v1/dept/tasks/calendar-export?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!resp.ok) throw new Error(`Server error ${resp.status}`);
    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `Task_Calendar_${month}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 500);
    calendarExportModal.value.open = false;
    showToast('Calendar exported');
  } catch (e) {
    showToast('Could not export calendar', 'error');
  } finally {
    calendarExportModal.value.loading = false;
  }
}

// ─── Debounce ─────────────────────────────────────────────────────────────────
let searchTimer = null;
function debouncedLoadTable() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { tablePage.value = 1; loadTableTasks(); }, 350);
}

function tableFilterChange() {
  tablePage.value = 1;
  loadTableTasks();
}

// ─── Refresh current view ─────────────────────────────────────────────────────
function refreshCurrentView(silent = false) {
  if (currentView.value === 'mywork')    loadBoardTasks(silent);
  if (currentView.value === 'dashboard') loadDashboard();
  if (currentView.value === 'board')     loadBoardTasks(silent);
  if (currentView.value === 'calendar')  loadBoardTasks(silent);
  if (currentView.value === 'people')    loadPeopleTasks(silent);
  if (currentView.value === 'table')     loadTableTasks();
  if (currentView.value === 'weekly')    loadWeeklyData();
  if (currentView.value === 'files')     loadAllAttachments();
}

// ─── Charts (Chart.js) ────────────────────────────────────────────────────────
function destroyCharts() {
  Object.values(chartInstances).forEach(c => c?.destroy());
  chartInstances = {};
}

function buildCharts() {
  destroyCharts();

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
  if (v === 'mywork')    loadBoardTasks();
  if (v === 'dashboard') loadDashboard();
  if (v === 'board')     loadBoardTasks();
  if (v === 'calendar')  loadBoardTasks();
  if (v === 'people')    loadPeopleTasks();
  if (v === 'table')     loadTableTasks();
  if (v === 'weekly')    loadWeeklyData();
  if (v === 'files')     loadAllAttachments();
});

// ─── Escape-to-close ──────────────────────────────────────────────────────────
// One listener for every dismissable surface, innermost/most-recently-opened first —
// the only real stacking case today is the delete-confirm modal opened from within
// the detail panel, so it must close before the panel underneath it does.
function handleEscapeKey(e) {
  if (e.key !== 'Escape') return;
  if (showDeleteConfirm.value) { showDeleteConfirm.value = false; return; }
  if (renameModal.show)        { renameModal.show = false; return; }
  if (exportModal.value.open)  { exportModal.value.open = false; return; }
  if (calendarExportModal.value.open) { calendarExportModal.value.open = false; return; }
  if (dayTasksModal.open)      { dayTasksModal.open = false; return; }
  if (showModal.value)         { showModal.value = false; return; }
  if (selectedTask.value)      { selectedTask.value = null; return; }
}

// ─── Deep link: reminders can open a specific task via ?task=ID ───────────────
const route  = useRoute();
const router = useRouter();

watch(() => route.query.task, (taskId) => {
  if (!taskId) return;
  openTaskDetail({ id: taskId });
  const { task, ...rest } = route.query;
  router.replace({ query: rest });
}, { immediate: true });

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onUnmounted(() => {
  destroyCharts();
  clearTimeout(searchTimer);
  clearTimeout(attachmentSearchTimer);
  window.removeEventListener('keydown', handleEscapeKey);
});

onMounted(async () => {
  const now = new Date();

  // Use toLocalDate() — toISOString() converts to UTC and shifts the date in UTC+8
  const day    = now.getDay();
  const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate() + (day === 0 ? -6 : 1 - day));
  weeklyWeekStart.value = toLocalDate(monday);

  window.addEventListener('keydown', handleEscapeKey);

  await loadMeta();

  // Non-admins default to their own tasks across all views
  if (!isAdmin.value) {
    boardFilters.assigned_to    = currentUserId.value;
    tableFilters.assigned_to    = currentUserId.value;
    weeklyAssigneeFilter.value  = currentUserName.value;
  }

  if (currentView.value === 'dashboard') {
    await loadDashboard();
  } else {
    await loadBoardTasks();
  }
  loadNotifications();
});
</script>

<style scoped>
/* ── Page shell ───────────────────────────────────────────────────────────── */
.page { padding: 28px 32px; background: var(--app-bg); min-height: 100vh; }

/* ── Page header ──────────────────────────────────────────────────────────── */
.page-header-row { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.page-title      { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle   { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-header-actions { display: flex; align-items: center; gap: 8px; }

/* ── Tab bar ──────────────────────────────────────────────────────────────── */
.tab-bar {
  display: flex; gap: 4px;
  border-bottom: 2px solid var(--border); margin-bottom: 28px;
  position: sticky; top: 0; z-index: 10;
  background: var(--app-bg); padding-top: 4px;
}
.tab-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 18px; border: none; background: none; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--text-2);
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-btn:hover:not(.active) { color: var(--text-1); background: var(--surface-2); }
.tab-icon { display: flex; align-items: center; opacity: 0.8; }

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.btn-primary {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; background: var(--primary); color: var(--primary-on);
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600;
  cursor: pointer; transition: background 0.15s;
}
.btn-primary:hover { background: var(--primary-hover); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-primary.sm { padding: 6px 12px; font-size: 12.5px; }

.btn-ghost {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 14px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.15s;
  text-decoration: none;
}
.btn-ghost:hover { background: var(--border); color: var(--text-1); }
.btn-ghost.sm { padding: 6px 10px; font-size: 12.5px; }
.btn-ghost.danger { color: #991b1b; border-color: #fca5a5; }
.btn-ghost.danger:hover { background: #fee2e2; }

.btn-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; background: none; border: none; cursor: pointer;
  border-radius: var(--radius-sm); color: var(--text-2); transition: background 0.15s, color 0.15s;
}
.btn-icon:hover { background: var(--surface-2); color: var(--text-1); }
.btn-icon.danger:hover { background: #fee2e2; color: #991b1b; }
.btn-icon.xs { width: 22px; height: 22px; }

/* ── Inputs / selects ─────────────────────────────────────────────────────── */
.field-input {
  height: 36px; padding: 0 12px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface);
  transition: border-color 0.15s, box-shadow 0.15s; outline: none; cursor: auto;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
select.field-input { cursor: pointer; }
.field-input.sm  { height: 32px; padding: 0 10px; font-size: 12.5px; width: auto; }
.field-textarea {
  width: 100%; padding: 8px 12px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface);
  outline: none; resize: vertical; font-family: inherit; transition: border-color 0.15s;
}
.field-textarea:focus { border-color: var(--primary); }

/* ── Stat cards ───────────────────────────────────────────────────────────── */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; margin-bottom: 28px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px 20px;
  display: flex; align-items: center; gap: 14px;
  box-shadow: var(--shadow-sm); border-top: 3px solid transparent;
  transition: box-shadow 0.2s;
}
.stat-card:hover { box-shadow: var(--shadow-md); }
.stat-card--link { cursor: pointer; }
.stat-card--link:hover { transform: translateY(-2px); }
.stat-card.accent-blue   { border-top-color: #3b82f6; }
.stat-card.accent-gray   { border-top-color: var(--text-3); }
.stat-card.accent-green  { border-top-color: #10b981; }
.stat-card.accent-red    { border-top-color: #ef4444; }
.stat-card.accent-amber  { border-top-color: #f59e0b; }
.stat-icon-wrap {
  width: 40px; height: 40px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.icon-blue  { background: #dbeafe; color: #1d4ed8; }
.icon-gray  { background: var(--surface-2); color: var(--text-2); }
.icon-green { background: #dcfce7; color: #15803d; }
.icon-red   { background: #fee2e2; color: #991b1b; }
.icon-amber { background: #fef3c7; color: #92400e; }
.stat-val   { font-size: 26px; font-weight: 800; color: var(--text-1); line-height: 1; }
.stat-lbl   { font-size: 12px; color: var(--text-2); margin-top: 3px; font-weight: 500; }

/* ── Charts ───────────────────────────────────────────────────────────────── */
.chart-row  { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 28px; }
.chart-card { padding: 20px; }
.chart-title { font-size: 13.5px; font-weight: 700; color: var(--text-1); margin-bottom: 16px; }

/* ── Recent tasks (dashboard) ─────────────────────────────────────────────── */
.recent-card     { padding: 20px; }
.card            { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
.card-section-title { font-size: 13.5px; font-weight: 700; color: var(--text-1); margin-bottom: 14px; }
.task-rows       { display: flex; flex-direction: column; gap: 2px; }
.task-row-item   { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: var(--radius-sm); cursor: pointer; transition: background 0.15s; }
.task-row-item:hover { background: var(--surface-2); }
.task-row-title  { flex: 1; font-size: 13px; color: var(--text-1); font-weight: 500; }
.task-row-date   { font-size: 12px; color: var(--text-3); }
.empty-row       { text-align: center; color: var(--text-3); padding: 24px; font-size: 13px; }

/* ── Section toolbar ──────────────────────────────────────────────────────── */
.section-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }

/* ── Board "show closed" toggle ──────────────────────────────────────────── */
.board-toggle-closed { display: inline-flex; align-items: center; gap: 5px; }
.board-toggle-closed.active { color: var(--primary); border-color: var(--primary); }

/* ── Scope toggle (My Tasks / All Tasks) ─────────────────────────────────── */
.scope-toggle { display: flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0; }
.scope-btn {
  padding: 6px 14px; border: none; background: none; cursor: pointer;
  font-size: 12.5px; font-weight: 600; color: var(--text-2);
  transition: background 0.12s, color 0.12s; white-space: nowrap;
}
.scope-btn:not(:last-child) { border-right: 1px solid var(--border); }
.scope-btn.active { background: var(--primary); color: var(--primary-on); }
.scope-btn:not(.active):hover { background: var(--surface-2); color: var(--text-1); }

/* ── My Work (user list view) ────────────────────────────────────────────── */
.mw-summary {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 22px; font-size: 13.5px; color: var(--text-2);
}
.mw-stat b { color: var(--text-1); font-weight: 800; font-size: 15px; }
.mw-stat--danger b { color: #dc2626; }
.mw-dot-sep { width: 4px; height: 4px; border-radius: 50%; background: var(--border); flex-shrink: 0; }

.mw-bucket { margin-bottom: 22px; }
.mw-bucket-head {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 8px; padding: 0 2px;
}
.mw-bucket-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
  color: var(--text-2);
}
.mw-bucket-title.tone-danger { color: #dc2626; }
.mw-bucket-title.tone-warn   { color: #b45309; }
.mw-bucket-count {
  font-size: 11px; font-weight: 700; color: var(--text-3);
  background: var(--surface-2); border: 1px solid var(--border);
  padding: 1px 8px; border-radius: 999px;
}
.mw-bucket-toggle {
  width: 100%; background: none; border: none; cursor: pointer;
  text-align: left; padding: 6px 2px;
}
.mw-toggle-ico { display: inline-flex; color: var(--text-3); transition: transform 0.18s; }
.mw-toggle-ico.open { transform: rotate(90deg); }

.mw-list {
  display: flex; flex-direction: column;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-xs);
}
.mw-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; cursor: pointer;
  border-bottom: 1px solid var(--border-soft); transition: background 0.12s;
}
.mw-row:last-child { border-bottom: none; }
.mw-row:hover { background: var(--surface-2); }

.mw-check {
  width: 22px; height: 22px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer; padding: 0;
  color: #16a34a;
}
.mw-check-ring {
  width: 18px; height: 18px; border-radius: 50%;
  border: 2px solid var(--border); transition: border-color 0.12s, background 0.12s;
}
.mw-check:hover .mw-check-ring { border-color: #16a34a; background: #16a34a18; }
.mw-check--done { color: #16a34a; cursor: default; }

.mw-prio { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.mw-main { flex: 1; min-width: 0; }
.mw-title {
  font-size: 13.5px; font-weight: 600; color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mw-sub { display: flex; align-items: center; gap: 10px; margin-top: 3px; font-size: 11.5px; }
.mw-dept { font-weight: 600; }
.mw-due  { color: var(--text-3); }
.mw-state {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;
  color: #1d4ed8; background: #dbeafe; padding: 1px 7px; border-radius: 999px;
}
.mw-action { flex-shrink: 0; }
.mw-row--done .mw-title { color: var(--text-3); text-decoration: line-through; }

/* ── Read-only field (non-admin assignee display) ────────────────────────── */
.field-readonly {
  display: flex; align-items: center;
  color: var(--text-2); background: var(--surface-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  padding: 8px 12px; font-size: 13px; min-height: 38px;
}

/* ── Kanban board ─────────────────────────────────────────────────────────── */
.kanban-board {
  display: grid;
  /* grid-template-columns set inline from kanbanCols.length (4 for users, 5 for admins) */
  gap: 14px;
  height: calc(100vh - 230px);
  min-height: 480px;
}
@media (max-width: 1100px) { .kanban-board { height: auto; } }
@media (max-width: 900px) {
  /* Equal-width grid columns get unusably thin once there are 4-5 of them on a
     narrow screen — switch to a horizontally-scrolling row of fixed-width columns. */
  .kanban-board { display: flex; overflow-x: auto; padding-bottom: 8px; -webkit-overflow-scrolling: touch; }
  .kanban-col   { min-width: 260px; flex: 0 0 260px; }
}
.kanban-col {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 14px;
  display: flex; flex-direction: column;
  overflow: hidden; transition: background 0.15s;
}
.kanban-col.drag-over { background: #eff6ff; border-color: #93c5fd; }
.kanban-col-header {
  display: flex; align-items: center; gap: 8px; flex-shrink: 0;
  padding-bottom: 12px; margin-bottom: 12px;
  border-top: 3px solid; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  padding-top: 8px;
}
.col-dot         { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.kanban-col-title { font-size: 12.5px; font-weight: 700; color: var(--text-1); flex: 1; }
.kanban-col-count { background: var(--border); color: var(--text-2); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
.kanban-cards    { display: flex; flex-direction: column; gap: 10px; flex: 1; overflow-y: auto; padding-right: 2px; }
.kanban-cards::-webkit-scrollbar       { width: 4px; }
.kanban-cards::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.kanban-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px; cursor: grab;
  box-shadow: var(--shadow-xs); transition: box-shadow 0.15s, transform 0.15s;
}
.kanban-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.kanban-card:active { cursor: grabbing; }
.kc-top      { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
.kc-priority { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: capitalize; }
.kc-title    { font-size: 13px; font-weight: 600; color: var(--text-1); margin-bottom: 6px; line-height: 1.4; }
.kc-dept     { font-size: 11.5px; color: var(--text-2); margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
.dept-color-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.dept-pill--dot { display: inline-flex; align-items: center; gap: 5px; }
.kc-footer   { display: flex; align-items: center; justify-content: space-between; }
.kc-date     { font-size: 11.5px; color: var(--text-3); }
.kc-assignee { width: 26px; height: 26px; border-radius: 50%; background: var(--primary); color: var(--primary-on); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; }
.kanban-empty { text-align: center; color: var(--text-3); font-size: 13px; padding: 24px 0; border: 2px dashed var(--border); border-radius: var(--radius); }
.overdue-badge { font-size: 10px; background: #fee2e2; color: #991b1b; padding: 2px 7px; border-radius: 999px; font-weight: 600; }

/* ── Calendar ─────────────────────────────────────────────────────────────── */
.cal-nav       { display: flex; align-items: center; gap: 6px; }
.cal-nav-label { font-size: 21px; font-weight: 800; color: var(--text-1); min-width: 150px; text-align: center; }

.cal-wrap     { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; background: var(--surface); }
.cal-head-row { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: var(--primary-soft); border-bottom: 2px solid var(--primary); }
.cal-head-row span {
  padding: 10px; text-align: center; font-size: 12.5px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary-text);
}
.cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.cal-day {
  min-height: 124px; padding: 8px;
  border-right: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft);
  background: var(--surface); overflow: hidden;
}
.cal-day:nth-child(7n)       { border-right: none; }
.cal-day--muted              { background: var(--surface-2); }
.cal-day--muted .cal-day-num { color: var(--text-3); }
.cal-day--today               { background: var(--primary-soft); }
.cal-day--today .cal-day-num { background: var(--primary); color: var(--primary-on); }
.cal-day--addable            { cursor: pointer; }
.cal-day--addable:hover      { background: color-mix(in srgb, var(--primary) 4%, var(--surface)); }
.cal-day-num {
  display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center;
  border-radius: 999px; font-size: 14px; font-weight: 800; color: var(--text-1);
}
.cal-pill-stack { display: flex; flex-direction: column; gap: 3px; margin-top: 6px; }
.cal-pill {
  display: flex; align-items: center; justify-content: space-between; gap: 4px;
  padding: 4px 7px; border-radius: var(--radius-sm); border-left: 4px solid transparent;
  background: var(--surface-2); font-size: 12px; line-height: 1.3; cursor: pointer;
}
.cal-pill:hover                       { filter: brightness(0.96); }
.cal-pill--overdue                    { outline: 1.5px dashed var(--danger); outline-offset: -2px; }
.cal-pill-title                       { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 700; color: var(--text-1); }
.cal-pill--overdue .cal-pill-title    { color: var(--danger); }
.cal-pill-who   { flex-shrink: 0; font-size: 10.5px; font-weight: 700; color: var(--text-2); }
.cal-more {
  display: block; width: 100%; text-align: left; font-family: inherit;
  background: none; border: none; cursor: pointer;
  font-size: 11.5px; font-weight: 600; color: var(--text-2); padding: 2px 4px;
}
.cal-more:hover { color: var(--primary); text-decoration: underline; }

.cal-legend      { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; padding: 2px 2px 14px; }
.cal-legend-hint { font-size: 11.5px; font-weight: 700; color: var(--text-3); }
.cal-legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; color: var(--text-2); }
.cal-legend-dot  { width: 9px; height: 9px; border-radius: 999px; flex-shrink: 0; }

.day-tasks-list { display: flex; flex-direction: column; gap: 6px; max-height: 50vh; overflow-y: auto; }
.day-tasks-list .cal-pill {
  width: 100%; text-align: left; font-family: inherit;
  border: none; border-left: 4px solid transparent;
  font-size: 13.5px; padding: 9px 11px;
}
.day-tasks-list .cal-pill-title { white-space: normal; overflow: visible; text-overflow: clip; }

@media (max-width: 640px) {
  .cal-day         { min-height: 88px; padding: 5px; }
  .cal-pill-who    { display: none; }
  .cal-nav-label   { min-width: 110px; font-size: 17px; }
}

/* ── Calendar: List (year board) view ─────────────────────────────────────── */
.cal-year {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px; align-items: start;
}
.cal-year-month {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);
}
.cal-year-month-head {
  padding: 9px 14px; background: var(--primary-soft); border-bottom: 2px solid var(--primary);
  font-size: 12.5px; font-weight: 800; color: var(--primary-text);
}
.cal-year-month-rows {
  display: flex; flex-direction: column;
  max-height: 340px; overflow-y: auto;
}
.cal-year-row {
  display: flex; align-items: baseline; gap: 8px; padding: 6px 10px;
  border-left: 3px solid var(--border); border-bottom: 1px solid var(--border-soft);
  cursor: pointer; transition: filter 0.12s;
}
.cal-year-row:last-child     { border-bottom: none; }
.cal-year-row:hover          { filter: brightness(0.97); }
.cal-year-row--overdue       { outline: 1.5px dashed var(--danger); outline-offset: -2px; }
.cal-year-row-date {
  flex-shrink: 0; width: 18px; font-size: 11px; font-weight: 700; color: var(--text-3);
}
.cal-year-row-title {
  font-size: 12.5px; font-weight: 600; color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cal-year-row--overdue .cal-year-row-title { color: var(--danger); }
.cal-year-empty { padding: 10px 14px; font-size: 11.5px; color: var(--text-3); text-align: center; }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.filter-bar {
  display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 14px;
  padding: 14px 16px; background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-xs);
}
.filter-search .field-input { width: 220px; }
.filter-actions { display: flex; gap: 6px; margin-left: auto; }

/* ── Table ────────────────────────────────────────────────────────────────── */
.table-wrap  {
  border: 1px solid var(--border); border-radius: var(--radius); overflow-x: auto;
  scrollbar-width: thin; scrollbar-color: var(--border) transparent;
}
.table-wrap::-webkit-scrollbar { height: 10px; }
.table-wrap::-webkit-scrollbar-track { background: transparent; }
.table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
.table-wrap::-webkit-scrollbar-thumb:hover { background: var(--text-3); }
.data-table  { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.data-table.compact { font-size: 12.5px; }
.data-table thead tr { background: var(--surface-2); }
.data-table th {
  padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 700;
  color: var(--text-2); text-transform: uppercase; letter-spacing: 0.6px;
  border-bottom: 1px solid var(--border); border-right: 1px solid var(--border-soft);
  white-space: nowrap; user-select: none;
}
.data-table th:last-child { border-right: none; }
.data-table th.sortable { cursor: pointer; }
.data-table th.sortable:hover { color: var(--text-1); }
.sort-indicator { display: inline-flex; align-items: center; margin-left: 4px; opacity: 0.5; }
.data-table td {
  padding: 7px 12px; color: var(--text-1);
  border-bottom: 1px solid var(--border-soft); border-right: 1px solid var(--border-soft);
}
.data-table td:last-child { border-right: none; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--surface-2); }
.table-row   { cursor: pointer; }
.actions-cell {
  white-space: nowrap; width: 1%;
  position: sticky; right: 0; background: var(--surface); box-shadow: -6px 0 8px -6px rgba(15,23,42,0.12);
}
.col-actions  {
  width: 1%; position: sticky; right: 0; background: var(--surface-2);
  box-shadow: -6px 0 8px -6px rgba(15,23,42,0.12);
}
.empty-cell  { text-align: center; color: var(--text-3); padding: 32px; }
.col-details      { min-width: 260px; max-width: 420px; white-space: normal; }
.task-cell-wrap   { display: flex; flex-direction: column; gap: 2px; padding: 3px 0; }
.task-cell-title  { font-weight: 700; font-size: 15px; line-height: 1.35; color: var(--text-1); }
.task-cell-desc   {
  font-weight: 400; font-size: 14px; line-height: 1.4; color: var(--text-1);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* ── Pills / tags ─────────────────────────────────────────────────────────── */
.priority-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0; margin-right: 4px; }
.dept-pill    { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.status-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
/* Important-task marker — reuses ICO.star (outline) but forces it solid gold via CSS, which wins over the baked-in fill="none". */
.imp-star { display: inline-flex; flex-shrink: 0; color: #f59e0b; }
.imp-star svg { fill: currentColor; }
.st-pending          { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.st-in_progress      { background: #dbeafe; color: #1d4ed8; }
.st-completed        { background: #dcfce7; color: #15803d; }
.st-cancelled        { background: var(--surface-2); color: var(--text-3); text-decoration: line-through; }
.st-overdue          { background: #fee2e2 !important; color: #991b1b !important; }
.text-danger { color: #dc2626 !important; }
.text-2      { color: var(--text-2); }

/* ── This Week nav + chips ────────────────────────────────────────────────── */
.week-nav       { display: flex; align-items: center; gap: 6px; }
.week-nav-label { font-size: 13.5px; font-weight: 600; color: var(--text-1); min-width: 200px; text-align: center; }
.wr-header      { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
.wr-summary-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wr-chip        { display: flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--border); font-size: 12.5px; color: var(--text-2); font-weight: 500; }
.wr-chip--danger { color: #dc2626; background: #dc262608; border-color: #dc262622; }
/* ── Weekly report ────────────────────────────────────────────────────────── */
.weekly-report { padding: 28px; margin-bottom: 28px; }
.wr-title      { font-size: 20px; font-weight: 800; color: var(--text-1); }
.wr-meta       { display: flex; gap: 32px; margin-top: 6px; font-size: 13.5px; color: var(--text-2); }
.wr-grid       { display: flex; flex-direction: column; gap: 16px; }
.wr-dept-block { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.wr-dept-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 9px 14px; font-size: 13px; font-weight: 700;
}
.wr-dept-count { font-size: 12px; color: var(--text-3); font-weight: 500; }
.wr-date       { white-space: nowrap; font-weight: 600; color: var(--text-1); width: 52px; }
.wr-overdue td { background: #fef2f2; }
.wr-dept-block .status-badge { white-space: nowrap; }
.wr-dept-block .table-wrap   { overflow-x: auto; }

/* ── Reports / History ────────────────────────────────────────────────────── */
.filter-label { font-size: 12.5px; color: var(--text-2); display: flex; align-items: center; gap: 6px; }
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-3); }
.empty-icon  { display: flex; justify-content: center; margin-bottom: 12px; opacity: 0.4; }
.empty-icon svg { width: 40px; height: 40px; }

/* ── Detail Panel ─────────────────────────────────────────────────────────── */
.panel-overlay  { position: fixed; inset: 0; background: rgba(15,23,42,0.35); z-index: 100; display: flex; justify-content: flex-end; }
.detail-panel   { width: 460px; max-width: 100vw; background: var(--surface); height: 100vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: -4px 0 24px rgba(15,23,42,0.12); }
.dp-header      { display: flex; justify-content: space-between; align-items: flex-start; padding: 22px 22px 16px; border-bottom: 1px solid var(--border); gap: 12px; }
.dp-title       { font-size: 18px; font-weight: 700; color: var(--text-1); margin: 6px 0 0; line-height: 1.35; }
.dp-meta-grid   { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 22px; border-bottom: 1px solid var(--border); }
.dp-meta-item   { display: flex; flex-direction: column; gap: 4px; }
.dp-meta-item:last-child:nth-child(odd) { grid-column: 1 / -1; }
.dp-meta-lbl    { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.dp-description { padding: 16px 22px; font-size: 13.5px; color: var(--text-2); line-height: 1.6; border-bottom: 1px solid var(--border); white-space: pre-wrap; }
.dp-actions     { display: flex; flex-direction: column; border-bottom: 1px solid var(--border); }
.dp-action-row  { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 14px 22px; min-height: 52px; }
.dp-util-row    { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 10px 22px 12px; border-top: 1px solid var(--border); background: var(--surface-2); }
.dp-no-action           { font-size: 13px; color: var(--text-3); font-style: italic; }

/* Task closed / done state notes */
.dp-status-note {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 13px; color: var(--text-3); font-style: italic;
}
.dp-status-note--done { color: #15803d; background: #dcfce7; padding: 4px 10px; border-radius: 999px; font-style: normal; font-weight: 500; }
.dp-status-note--done svg { width: 13px; height: 13px; }

/* Admin utility row labels */
.dp-util-badge {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
  background: var(--primary-soft); color: var(--primary);
  padding: 2px 8px; border-radius: 999px; flex-shrink: 0;
}
.dp-util-lbl {
  font-size: 12px; font-weight: 600; color: var(--text-2); flex-shrink: 0; white-space: nowrap;
}
/* Push delete to the far right so it's separated from Edit */
.dp-delete-btn { margin-left: auto; }
.dp-comments    { padding: 16px 22px; display: flex; flex-direction: column; }
.dp-section-title { font-size: 13px; font-weight: 700; color: var(--text-1); margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; }
.comment-list   { flex: 1; display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; max-height: 320px; overflow-y: auto; }
.comment-item   { display: flex; gap: 10px; }
.comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: var(--primary-on); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.comment-body   { flex: 1; background: var(--surface-2); border-radius: var(--radius); padding: 10px 12px; }
.comment-meta   { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 12px; }
.comment-meta strong { color: var(--text-1); }
.comment-time   { color: var(--text-3); flex: 1; }
.comment-text   { font-size: 13px; color: var(--text-2); line-height: 1.5; }
.empty-comments { color: var(--text-3); font-size: 13px; text-align: center; padding: 20px; }
.comment-input-row { display: flex; gap: 8px; }
.comment-input-row .field-input { flex: 1; }

/* ── Notifications (strip only — bell moved to topbar) ───────────────────── */
.notif-mark-read   { background: none; border: none; color: var(--primary); font-size: 12px; cursor: pointer; font-weight: 600; }
.notif-mark-read:hover { text-decoration: underline; }
.notif-dismiss     { flex-shrink: 0; width: 20px; height: 20px; background: rgba(255,255,255,0.6); border: none; border-radius: 4px; color: var(--text-3); font-size: 16px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.notif-dismiss:hover { background: #fee2e2; color: #dc2626; }

/* notification type tags */
.ntag              { font-size: 9px; font-weight: 700; padding: 2px 5px; border-radius: 4px; flex-shrink: 0; white-space: nowrap; align-self: flex-start; margin-top: 2px; }
.ntag-assigned         { background: #dbeafe; color: #1d4ed8; }
.ntag-completed        { background: #dcfce7; color: #15803d; }
.ntag-overdue          { background: #fff7ed; color: #c2410c; }

/* ── Task notification strip (below tabs) ────────────────────────────────── */
.task-notif-strip  { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.tns-row           { display: flex; align-items: center; gap: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); padding: 10px 14px; }
.tns-msg           { flex: 1; font-size: 13px; color: var(--text-1); min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tns-more          { display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--text-2); padding: 2px 2px; }

/* ── Modal ────────────────────────────────────────────────────────────────── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
.modal-box      { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header   { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px 14px; border-bottom: 1px solid var(--border); }
.modal-title    { margin: 0; font-size: 17px; font-weight: 700; color: var(--text-1); }
.modal-subtitle { margin: 3px 0 0; font-size: 12.5px; color: var(--text-3); }
.modal-body     { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; }
.modal-footer   { padding: 16px 22px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.field-row      { display: flex; flex-direction: column; gap: 6px; }
.field-row.two-col   { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field-row.three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
.field-row.two-col > div,
.field-row.three-col > div { display: flex; flex-direction: column; gap: 6px; }
@media (max-width: 560px) {
  .field-row.two-col, .field-row.three-col { grid-template-columns: 1fr; }
}
.field-label    { font-size: 12px; font-weight: 600; color: var(--text-2); display: block; }
.checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-1); cursor: pointer; }
.checkbox-label input { width: 16px; height: 16px; }
.field-hint { font-size: 11.5px; color: var(--text-3); margin-top: 6px; line-height: 1.5; }
.recur-select { display: block; margin-top: 8px; }

/* ── Export modal ─────────────────────────────────────────────────────────── */
.export-section       { display: flex; flex-direction: column; gap: 10px; }
.export-section-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-3); }
.export-cols-head      { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.export-cols-actions   { display: flex; align-items: center; gap: 4px; }
.export-link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--primary); padding: 2px 4px; border-radius: 4px; }
.export-link-btn:hover { text-decoration: underline; }
.export-dot-sep   { color: var(--text-3); font-size: 12px; }
.export-cols-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 12px; }
.export-col-check {
  display: flex; align-items: center; gap: 8px; cursor: pointer;
  font-size: 13px; color: var(--text-2); font-weight: 500;
  padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-soft);
  transition: background 0.12s, border-color 0.12s;
}
.export-col-check:hover { background: var(--primary-soft); border-color: var(--primary); }
.export-col-check input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer; }

.export-modal-footer {
  display: flex; flex-direction: column; gap: 12px;
  padding: 16px 22px 20px; border-top: 1px solid var(--border); background: var(--surface-2);
}
.export-footer-count        { font-size: 12.5px; color: var(--text-3); margin: 0; }
.export-footer-count strong { color: var(--primary); }
.export-action-stack { display: flex; flex-direction: column; gap: 10px; }
.export-dl-btn {
  width: 100%; display: flex; align-items: flex-start; gap: 14px;
  padding: 14px 18px; border-radius: var(--radius); border: none; cursor: pointer;
  text-align: left; transition: opacity 0.15s, transform 0.08s;
}
.export-dl-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.export-dl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-dl-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.export-dl-text  { display: flex; flex-direction: column; gap: 2px; }
.export-dl-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.export-dl-desc  { font-size: 12px; opacity: 0.82; line-height: 1.3; }
.export-dl-xls { background: #10b981; color: #fff; }
.export-dl-csv { background: var(--surface); border: 1.5px solid var(--border) !important; color: var(--text-1); }
.export-cancel-btn {
  width: 100%; padding: 10px 16px; background: none;
  border: 1px solid var(--border-soft); border-radius: var(--radius);
  font-size: 13px; font-weight: 600; color: var(--text-3); cursor: pointer;
  transition: background 0.12s, color 0.12s;
}
.export-cancel-btn:hover { background: var(--border-soft); color: var(--text-2); }

/* ── Section animation ────────────────────────────────────────────────────── */
.dtm-section { animation: dtm-fade 0.2s ease; }
@keyframes dtm-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* ── Transitions ──────────────────────────────────────────────────────────── */
.slide-panel-enter-active, .slide-panel-leave-active { transition: opacity 0.2s ease; }
.slide-panel-enter-from, .slide-panel-leave-to { opacity: 0; }
.slide-panel-enter-active .detail-panel, .slide-panel-leave-active .detail-panel { transition: transform 0.25s ease; }
.slide-panel-enter-from .detail-panel, .slide-panel-leave-to .detail-panel { transform: translateX(100%); }
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* ── Print ────────────────────────────────────────────────────────────────── */
@media print {
  .no-print, .page-header-actions, .tab-bar, .filter-bar, .section-toolbar { display: none !important; }
  .page { padding: 0; background: #fff; }
  .weekly-report { box-shadow: none; border-radius: 0; }
  .wr-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── Attachments ─────────────────────────────────────────────────────────── */
.dp-attachments    { padding: 16px 22px; border-bottom: 1px solid var(--border); }
.attachment-list   { display: flex; flex-direction: column; gap: 6px; }
.attachment-item   { display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: var(--surface-2); border-radius: var(--radius-sm); }
.attach-name       { flex: 1; font-size: 13px; color: var(--primary); text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.attach-name:hover { text-decoration: underline; }
.attach-size       { font-size: 11px; color: var(--text-3); white-space: nowrap; }
.attach-upload-btn { display: inline-flex; align-items: center; gap: 5px; margin-left: auto; padding: 4px 10px; border-radius: var(--radius-sm); border: 1px dashed var(--border); color: var(--text-2); font-size: 12px; cursor: pointer; transition: border-color 0.15s, color 0.15s; }
.attach-upload-btn:hover { border-color: var(--primary); color: var(--primary); }
.attach-upload-btn.uploading { opacity: 0.6; pointer-events: none; }
.attach-size-hint { font-size: 11px; color: var(--text-3); margin-top: 4px; }

/* ── Form error / Confirm modal ───────────────────────────────────────────── */
.form-error { font-size: 13px; color: var(--danger, #dc2626); background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.25); border-radius: var(--radius-sm); padding: 8px 12px; }
.modal-sm   { max-width: 380px !important; }
.btn-danger { padding: 8px 18px; border-radius: var(--radius-sm); border: none; background: var(--danger, #dc2626); color: #fff; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: opacity 0.15s; }
.btn-danger:hover { opacity: 0.88; }

@media (max-width: 768px) { .page { padding: 20px 14px; } }
@media (max-width: 640px) { .page { padding: 14px 10px; } }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.pagination-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 2px; margin-top: 4px; font-size: 13px;
}
.page-info { color: var(--text-3); }
.page-btns { display: flex; align-items: center; gap: 8px; }
.page-num  { font-weight: 600; color: var(--text-1); min-width: 48px; text-align: center; }
.page-btns .btn-ghost:disabled { opacity: 0.35; cursor: not-allowed; }

.hm-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Loading spinner ──────────────────────────────────────────────────────── */
.view-loading { display: flex; align-items: center; justify-content: center; padding: 80px 20px; }
.spinner {
  width: 36px; height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Chart empty state ────────────────────────────────────────────────────── */
.chart-empty { display: flex; align-items: center; justify-content: center; height: 180px; color: var(--text-3); font-size: 13px; }

/* ── People view (Notion-style, tasks by user) ────────────────────────────── */
.people-filter-bar {
  display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 20px;
  padding: 12px 16px; background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-xs);
}
.pf-search  { width: 200px; }
.pf-count   { font-size: 12.5px; color: var(--text-3); }

.people-list { display: flex; flex-direction: column; gap: 12px; }

/* ── View toggle ─────────────────────────────────────────────────────────── */
.pv-toggle { display: flex; margin-left: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.pv-btn { padding: 5px 10px; background: transparent; border: none; color: var(--text-3); cursor: pointer; display: flex; align-items: center; transition: background 0.12s, color 0.12s; }
.pv-btn.active { background: var(--primary-soft); color: var(--primary); }
.pv-btn:hover:not(.active) { background: var(--surface-2); color: var(--text-1); }

/* ── People: Cards view ──────────────────────────────────────────────────── */
.people-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 16px; align-items: start; }
.pc-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow: hidden; }
.pc-card-head { padding: 14px 16px 12px; display: flex; align-items: center; gap: 10px; }
.pc-head-info { flex: 1; min-width: 0; }
.pc-name { font-size: 13.5px; font-weight: 700; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-chips { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
.pc-progress-txt-top { font-size: 11px; font-weight: 600; color: var(--text-3); white-space: nowrap; }
.pc-progress { height: 3px; background: var(--border); margin: 0 16px 0; border-radius: 999px; }
.pc-progress-fill { height: 100%; background: #22c55e; border-radius: 999px; transition: width 0.4s; }
.pc-tasks { max-height: 300px; overflow-y: auto; border-top: 1px solid var(--border-soft); margin-top: 10px; }
.pc-task { display: flex; align-items: center; gap: 8px; padding: 9px 14px; border-bottom: 1px solid var(--border-soft); border-left: 3px solid transparent; cursor: pointer; transition: background 0.12s; }
.pc-task:last-child { border-bottom: none; }
.pc-task:hover { background: var(--surface-2); }
.pc-task--ov { background: #fff0f0; }
.pc-task--ov:hover { background: #fee2e2; }
.pc-task-title { flex: 1; font-size: 12.5px; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-empty { padding: 18px 16px; text-align: center; font-size: 12px; color: var(--text-3); }

.pg-block {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);
}

.pg-head {
  width: 100%; display: flex; align-items: center; justify-content: space-between;
  padding: 13px 18px; background: none; border: none; cursor: pointer; gap: 16px;
  transition: background 0.12s; text-align: left;
}
.pg-head:hover { background: var(--surface-2); }

.pg-head-left  { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; overflow: hidden; }
.pg-head-right { flex-shrink: 0; }

.pg-chevron { display: inline-flex; color: var(--text-3); transition: transform 0.2s; flex-shrink: 0; }
.pg-chevron.open { transform: rotate(90deg); }

.pg-avatar {
  width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
  background: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; letter-spacing: 0.3px; text-transform: uppercase;
}

.pg-name { font-size: 14.5px; font-weight: 700; color: var(--text-1); white-space: nowrap; }

.pg-chip {
  display: inline-block; padding: 2px 9px; border-radius: 999px;
  font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.pg-chip--danger  { background: #fee2e2; color: #991b1b; }
.pg-chip--neutral { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.pg-chip--done    { background: #dcfce7; color: #15803d; }

.pg-progress-wrap { display: flex; align-items: center; gap: 8px; }
.pg-progress-bar  { width: 90px; height: 5px; background: var(--border); border-radius: 999px; overflow: hidden; flex-shrink: 0; }
.pg-progress-fill { height: 100%; background: #10b981; border-radius: 999px; transition: width 0.4s ease; }
.pg-progress-txt  { font-size: 11.5px; color: var(--text-3); white-space: nowrap; min-width: 28px; text-align: right; }

.pg-task-area { border-top: 1px solid var(--border-soft); }

.pg-col-labels {
  display: grid; grid-template-columns: 1fr 155px 105px 148px;
  gap: 12px; padding: 7px 20px 7px 22px;
  background: var(--surface-2); border-bottom: 1px solid var(--border-soft);
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3);
}

.pg-task-row {
  display: grid; grid-template-columns: 1fr 155px 105px 148px;
  align-items: center; gap: 12px; padding: 11px 20px 11px 22px;
  border-left: 3px solid var(--border); border-bottom: 1px solid var(--border-soft);
  cursor: pointer; transition: background 0.12s;
}
.pg-task-row:last-child { border-bottom: none; }
.pg-task-row:hover { background: #f6f9ff; }
.pg-task-row--ov { background: #fef9f9; }
.pg-task-row--ov:hover { background: #fef2f2; }

.pg-task-info { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.pg-task-name { font-size: 13.5px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pg-task-hint { font-size: 11.5px; color: var(--text-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.pg-task-dept-cell { display: flex; align-items: center; }
.pg-task-empty { color: var(--text-3); font-size: 13px; }

.pg-task-due { font-size: 12.5px; color: var(--text-2); white-space: nowrap; }

.pg-no-tasks { padding: 20px 22px; color: var(--text-3); font-size: 13px; text-align: center; }

@media (max-width: 900px) {
  .pg-col-labels { grid-template-columns: 1fr 120px 130px; }
  .pg-task-row   { grid-template-columns: 1fr 120px 130px; }
  .pg-col-labels span:nth-child(3),
  .pg-task-row .pg-task-due { display: none; }
}
@media (max-width: 640px) {
  .pg-col-labels { grid-template-columns: 1fr 130px; }
  .pg-task-row   { grid-template-columns: 1fr 130px; }
  .pg-col-labels span:nth-child(2), .pg-col-labels span:nth-child(3),
  .pg-task-row .pg-task-dept-cell, .pg-task-row .pg-task-due { display: none; }
  .pg-progress-bar { width: 60px; }
  .pg-chip { display: none; }
  .pg-chip--danger { display: inline-block; }
}

/* ── File Manager ────────────────────────────────────────────────────────── */
.af-count { font-size: 12.5px; color: var(--text-3); margin-left: auto; white-space: nowrap; }
.fm-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.fm-sort-select { width: auto; min-width: 140px; }

.fm-view-toggle { display: flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; flex-shrink: 0; }
.fm-vt-btn {
  background: none; border: none; cursor: pointer; padding: 6px 10px;
  color: var(--text-3); display: flex; align-items: center; justify-content: center;
  transition: background 0.15s, color 0.15s; line-height: 1;
}
.fm-vt-btn:hover { background: var(--surface-2); color: var(--text-1); }
.fm-vt-btn.active { background: var(--primary); color: #fff; }

/* ── Grid ── */
.fm-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(152px, 1fr));
  gap: 14px;
}
.fm-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
  transition: box-shadow 0.18s, transform 0.18s;
  cursor: default;
}
.fm-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.10); transform: translateY(-2px); }

.fm-icon-wrap {
  position: relative; height: 100px;
  display: flex; align-items: center; justify-content: center;
  background: var(--surface-2); overflow: hidden;
}
.fm-ext-icon {
  font-size: 22px; font-weight: 800; letter-spacing: 0.5px;
  padding: 14px 20px; border-radius: var(--radius);
  user-select: none;
}
.fm-card-overlay {
  position: absolute; inset: 0;
  background: rgba(15,20,30,0.62); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center; gap: 8px;
  opacity: 0; transition: opacity 0.16s;
}
.fm-card:hover .fm-card-overlay { opacity: 1; }
.fm-overlay-btn {
  background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22);
  border-radius: var(--radius-sm); padding: 7px; cursor: pointer; color: #fff;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.12s; text-decoration: none;
}
.fm-overlay-btn:hover { background: rgba(255,255,255,0.28); }
.fm-overlay-btn.danger:hover { background: rgba(220,38,38,0.75); border-color: transparent; }

.fm-card-foot { padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 3px; }
.fm-card-name {
  font-size: 12.5px; font-weight: 600; color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fm-card-meta { font-size: 11px; color: var(--text-3); }
.fm-task-link {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 11.5px; color: var(--primary); text-align: left;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  display: block; width: 100%; margin-top: 1px;
}
.fm-task-link:hover { text-decoration: underline; }
.fm-dept-row { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 3px; }
.fm-dept-pill { font-size: 10px !important; padding: 1px 7px !important; }
.fm-by { font-size: 10.5px; color: var(--text-3); }

/* ── List ── */
.fm-list {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
}
.fm-list-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 16px; border-bottom: 1px solid var(--border);
  transition: background 0.1s;
}
.fm-list-row:last-child { border-bottom: none; }
.fm-list-row:hover { background: var(--surface-2); }
.fm-list-badge {
  font-size: 10px; font-weight: 700; padding: 3px 8px;
  border-radius: 6px; white-space: nowrap; flex-shrink: 0; letter-spacing: 0.3px;
}
.fm-list-main { flex: 1; min-width: 0; }
.fm-list-name {
  display: block; color: var(--text-1); text-decoration: none;
  font-size: 13px; font-weight: 500;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fm-list-name:hover { color: var(--primary); }
.fm-list-sub { display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
.fm-task-link-inline {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 11.5px; color: var(--text-2); text-align: left;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fm-task-link-inline:hover { color: var(--primary); text-decoration: underline; }
.fm-list-meta { font-size: 12px; color: var(--text-3); white-space: nowrap; flex-shrink: 0; }
.fm-list-actions { display: flex; gap: 2px; flex-shrink: 0; }

.file-ext-badge {
  font-size: 10px; font-weight: 700; padding: 2px 7px;
  border-radius: 999px; white-space: nowrap; display: inline-block; letter-spacing: 0.3px;
}

/* ── Notification overlay (click-outside) ────────────────────────────────── */
.notif-overlay { position: fixed; inset: 0; z-index: 199; }

/* ── Toast notifications ──────────────────────────────────────────────────── */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: var(--radius);
  font-size: 13px; font-weight: 500; color: #fff;
  box-shadow: 0 4px 16px rgba(0,0,0,0.18); pointer-events: auto;
  min-width: 200px;
}
.toast--success { background: #16a34a; }
.toast--error   { background: #dc2626; }
.toast-ico      { display: flex; flex-shrink: 0; }
.toast-slide-enter-active { transition: all 0.25s ease; }
.toast-slide-leave-active { transition: all 0.2s ease; }
.toast-slide-enter-from   { opacity: 0; transform: translateY(12px); }
.toast-slide-leave-to     { opacity: 0; transform: translateX(20px); }
</style>
