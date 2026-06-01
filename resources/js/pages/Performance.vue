<template>
  <div class="page">
    <!-- Banner -->
    <div class="page-banner">
      <div class="banner-text">
        <h1>Performance</h1>
        <p>KPI dashboard — track activity, targets, and team progress</p>
      </div>
      <button class="btn-export-top" @click="exportActivityCSV">↗ Export Activity CSV</button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="filter-group">
        <label>View</label>
        <select v-model="viewType" @change="onViewChange">
          <option value="week">Week</option>
          <option value="month">Month</option>
          <option value="year">Year</option>
        </select>
      </div>
      <div v-if="viewType === 'month'" class="filter-group">
        <label>Month</label>
        <input type="month" v-model="selectedMonth" @change="onViewChange">
      </div>
      <div v-if="viewType === 'year'" class="filter-group">
        <label>Year</label>
        <input type="number" v-model.number="selectedYear" min="2020" max="2035" style="width:90px" @change="onViewChange">
      </div>
      <div v-if="isAdmin" class="filter-group">
        <label>User</label>
        <select v-model="selectedUserId" @change="onUserChange">
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
      <button :class="['tab-btn', { active: activeTab === 'overview' }]" @click="activeTab = 'overview'">Overview</button>
      <button :class="['tab-btn', { active: activeTab === 'activity' }]" @click="activeTab = 'activity'">Activity</button>
      <button v-if="isAdmin" :class="['tab-btn', { active: activeTab === 'team' }]" @click="switchTeam">Team</button>
      <button :class="['tab-btn', { active: activeTab === 'targets' }]" @click="switchTargets">Targets</button>
    </div>

    <!-- ═══════════════════════════════ OVERVIEW TAB ═══════════════════════════ -->
    <div v-if="activeTab === 'overview'">
      <LoadingSpinner v-if="loadingOverview" />
      <template v-else-if="overview">

        <!-- Period label -->
        <div class="section-label">{{ periodLabel }}</div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
          <div v-for="card in kpiCards" :key="card.key"
               :class="['kpi-card', `kpi-${card.color}`]">
            <div class="kpi-icon">{{ card.icon }}</div>
            <div class="kpi-body">
              <div class="kpi-value">{{ card.value }}</div>
              <div class="kpi-label">{{ card.label }}</div>
            </div>
          </div>
        </div>

        <!-- Pipeline Forecast -->
        <div v-if="dealSummary" class="section-card">
          <div class="section-title">Pipeline Forecast</div>
          <div class="forecast-grid">
            <div class="forecast-stat">
              <div class="forecast-label">Open Pipeline</div>
              <div class="forecast-value">{{ formatCurrency(dealSummary.open_value) }}</div>
              <div class="forecast-sub">{{ dealSummary.open_count }} open deal{{ dealSummary.open_count !== 1 ? 's' : '' }}</div>
            </div>
            <div class="forecast-stat highlight">
              <div class="forecast-label">Weighted Forecast</div>
              <div class="forecast-value">{{ formatCurrency(dealSummary.weighted_forecast) }}</div>
              <div class="forecast-sub">value × probability</div>
            </div>
            <div class="forecast-stat green">
              <div class="forecast-label">Won Value</div>
              <div class="forecast-value">{{ formatCurrency(dealSummary.won_value) }}</div>
              <div class="forecast-sub">{{ dealSummary.won_count }} deal{{ dealSummary.won_count !== 1 ? 's' : '' }} closed</div>
            </div>
          </div>
          <div v-if="dealSummary.by_stage?.length" class="stage-bars">
            <div class="stage-bars-title">Open Deals by Stage</div>
            <div v-for="s in dealSummary.by_stage" :key="s.stage" class="stage-row">
              <span class="stage-name">{{ s.stage }}</span>
              <div class="stage-bar-wrap">
                <div class="stage-bar-fill" :style="{ width: stageBarPct(s.total_value) + '%' }"></div>
              </div>
              <span class="stage-count">{{ s.count }}</span>
              <span class="stage-val">{{ formatCurrency(s.total_value) }}</span>
            </div>
          </div>
        </div>

        <!-- Target Progress Bars -->
        <div v-if="targetProgress.length > 0" class="section-card">
          <div class="section-title">Target Progress</div>
          <div class="progress-list">
            <div v-for="t in targetProgress" :key="t.metric" class="progress-row">
              <div class="progress-meta">
                <span class="progress-label">{{ t.label }}</span>
                <span class="progress-nums">{{ t.achieved }} / {{ t.target }}</span>
              </div>
              <div class="progress-bar-wrap">
                <div class="progress-bar-fill"
                     :class="{ 'bar-done': t.pct >= 100, 'bar-low': t.pct < 40 }"
                     :style="{ width: t.pct + '%' }"></div>
              </div>
              <span class="progress-pct" :class="{ 'pct-done': t.pct >= 100 }">{{ t.pct }}%</span>
            </div>
          </div>
        </div>

        <!-- Needs Attention -->
        <div v-if="hasAttentionItems" class="section-card attention-card">
          <div class="section-title attention-title">Needs Attention</div>

          <div v-if="overview.overdue_todos.length > 0" class="attention-group">
            <div class="attention-group-label">Overdue To-Dos</div>
            <div class="attention-list">
              <div v-for="t in overview.overdue_todos" :key="t.id" class="attention-item">
                <span class="badge-overdue">{{ t.days_overdue }}d</span>
                <router-link :to="`/contacts/${t.contact_id}`" class="att-company">{{ t.contact_name }}</router-link>
                <span class="att-meta">{{ t.task }} — due {{ fmtDate(t.todo_date) }}</span>
                <router-link :to="`/todos/${t.id}/edit`" class="att-action">Edit</router-link>
              </div>
            </div>
          </div>

          <div v-if="overview.overdue_followups.length > 0" class="attention-group">
            <div class="attention-group-label">Overdue Follow-Ups</div>
            <div class="attention-list">
              <div v-for="f in overview.overdue_followups" :key="f.id" class="attention-item">
                <span class="badge-overdue">{{ f.days_overdue }}d</span>
                <router-link :to="`/contacts/${f.contact_id}`" class="att-company">{{ f.contact_name }}</router-link>
                <span class="att-meta">{{ f.action_type || 'Follow-up' }} — due {{ fmtDate(f.followup_date) }}</span>
                <router-link :to="`/followups/${f.id}/edit`" class="att-action">Edit</router-link>
              </div>
            </div>
          </div>

          <div v-if="overview.overdue_deals.length > 0" class="attention-group">
            <div class="attention-group-label">Overdue Deals</div>
            <div class="attention-list">
              <div v-for="d in overview.overdue_deals" :key="d.id" class="attention-item">
                <span class="badge-overdue">{{ d.days_overdue }}d</span>
                <router-link :to="`/contacts/${d.contact_id}`" class="att-company">{{ d.contact_name }}</router-link>
                <span class="att-meta">{{ d.title }} ({{ d.stage }}) — close {{ fmtDate(d.expected_close_date) }}</span>
                <router-link :to="`/deals/${d.id}/edit`" class="att-action">Edit</router-link>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="no-attention">
          <span class="no-att-icon">✓</span> Nothing overdue — great work!
        </div>

      </template>
      <div v-else class="empty-state">No data for this period.</div>
    </div>

    <!-- ═══════════════════════════════ ACTIVITY TAB ══════════════════════════ -->
    <div v-if="activeTab === 'activity'">
      <!-- Date Navigator -->
      <div class="date-nav">
        <button class="nav-btn" @click="prevPeriod">‹ Prev</button>
        <span class="period-label">{{ periodLabel }}</span>
        <button class="nav-btn" @click="nextPeriod">Next ›</button>
      </div>

      <div class="table-wrap">
        <LoadingSpinner v-if="loadingActivity" />
        <div v-else-if="tasks.length === 0" class="loading-msg">
          No tasks found. Add tasks in the Admin Panel first.
        </div>
        <div v-else class="table-scroll">
          <table ref="tableRef">
            <thead>
              <tr class="header-tasks">
                <th class="col-period" :colspan="viewType === 'year' ? 2 : 1">
                  {{ viewType === 'week' ? 'Date / Day' : viewType === 'month' ? 'Week Range' : 'Month / Week' }}
                </th>
                <th v-for="task in tasks" :key="task.id" class="col-task">{{ task.name }}</th>
              </tr>
              <tr class="header-targets">
                <td :colspan="viewType === 'year' ? 2 : 1" class="target-label">
                  {{ viewType === 'week' ? 'Weekly Target' : 'Monthly Target (×5)' }}
                </td>
                <td v-for="task in tasks" :key="task.id" class="target-val">
                  {{ getTaskTarget(task.name, viewType === 'week' ? 1 : 5) ?? '—' }}
                </td>
              </tr>
            </thead>
            <tbody v-if="viewType === 'week'">
              <tr v-for="(date, idx) in datesInWeek" :key="date" :class="{ 'weekend-row': idx >= 5 }">
                <td class="col-period">
                  {{ fmtDate(date) }}
                  <span class="weekday-tag">{{ WEEKDAYS[idx] }}</span>
                </td>
                <td v-for="task in tasks" :key="task.id" class="col-count"
                    :class="{ 'has-data': getActual(date, task.name) > 0 }">
                  {{ getActual(date, task.name) || '—' }}
                </td>
              </tr>
              <tr class="total-row">
                <td>Total</td>
                <td v-for="task in tasks" :key="task.id" class="col-count total"
                    :class="{ 'over-target': isOverTarget(task.name), 'under-target': isUnderTarget(task.name) }">
                  {{ getWeekTotal(task.name) }}
                </td>
              </tr>
            </tbody>
            <tbody v-else-if="viewType === 'month'">
              <tr v-for="week in weeksInMonth" :key="week.isoWeek">
                <td class="col-period">{{ fmtDate(week.start) }} — {{ fmtDate(week.end) }}</td>
                <td v-for="task in tasks" :key="task.id" class="col-count"
                    :class="{ 'has-data': getActual(week.isoWeek, task.name) > 0 }">
                  {{ getActual(week.isoWeek, task.name) || '—' }}
                </td>
              </tr>
              <tr class="total-row">
                <td>Total</td>
                <td v-for="task in tasks" :key="task.id" class="col-count total">
                  {{ getPeriodTotal(task.name, weeksInMonth.map(w => w.isoWeek)) }}
                </td>
              </tr>
            </tbody>
            <tbody v-else>
              <tr v-for="week in weeksInYear" :key="week.isoWeek">
                <td class="col-period month-label">{{ week.monthLabel }}</td>
                <td class="col-period small">{{ fmtDate(week.start) }} — {{ fmtDate(week.end) }}</td>
                <td v-for="task in tasks" :key="task.id" class="col-count"
                    :class="{ 'has-data': getActual(week.isoWeek, task.name) > 0 }">
                  {{ getActual(week.isoWeek, task.name) || '—' }}
                </td>
              </tr>
              <tr class="total-row">
                <td colspan="2">Total</td>
                <td v-for="task in tasks" :key="task.id" class="col-count total">
                  {{ getPeriodTotal(task.name, weeksInYear.map(w => w.isoWeek)) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════ TEAM TAB ══════════════════════════════ -->
    <div v-if="activeTab === 'team' && isAdmin">
      <LoadingSpinner v-if="loadingTeam" />
      <template v-else>
        <!-- Quota Attainment Summary Cards -->
        <div v-if="teamData.length > 0" class="quota-summary-grid">
          <div v-for="u in teamData.filter(u => u.revenue_quota)" :key="u.user_id"
               class="quota-card"
               :class="{ 'quota-met': u.quota_attainment_pct >= 100, 'quota-low': u.quota_attainment_pct < 50 }">
            <div class="quota-user">{{ u.user_name }}</div>
            <div class="quota-bar-wrap">
              <div class="quota-bar-fill" :style="{ width: u.quota_attainment_pct + '%' }"></div>
            </div>
            <div class="quota-numbers">
              {{ formatCurrency(u.won_deal_value) }} / {{ formatCurrency(u.revenue_quota) }}
              <span class="quota-pct" :class="{ 'pct-done': u.quota_attainment_pct >= 100 }">
                {{ u.quota_attainment_pct }}%
              </span>
            </div>
          </div>
          <div v-if="!teamData.some(u => u.revenue_quota)" class="quota-hint">
            No revenue quotas set. Go to the Targets tab to configure per-user quotas.
          </div>
        </div>

        <!-- Team Activity Table -->
        <div class="table-wrap">
          <div class="table-header-bar">Team Activity — {{ periodLabel }}</div>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>User</th>
                  <th>Contacts Added</th>
                  <th>To-Dos Created</th>
                  <th>To-Dos Completed</th>
                  <th>To-Dos Overdue</th>
                  <th>Follow-Ups Created</th>
                  <th>Follow-Ups Done</th>
                  <th>Deals Won</th>
                  <th>Won Value</th>
                  <th>Quota</th>
                  <th>Attainment</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="teamData.length === 0">
                  <td colspan="11" class="empty-state">No data.</td>
                </tr>
                <tr v-for="u in teamData" :key="u.user_id">
                  <td class="user-cell">{{ u.user_name }}</td>
                  <td class="num-cell">{{ u.contacts_added }}</td>
                  <td class="num-cell">{{ u.todos_created }}</td>
                  <td class="num-cell green-cell">{{ u.todos_completed }}</td>
                  <td class="num-cell" :class="{ 'red-cell': u.todos_overdue > 0 }">{{ u.todos_overdue }}</td>
                  <td class="num-cell">{{ u.followups_created }}</td>
                  <td class="num-cell green-cell">{{ u.followups_completed }}</td>
                  <td class="num-cell green-cell">{{ u.deals_won }}</td>
                  <td class="num-cell green-cell">{{ formatCurrency(u.won_deal_value) }}</td>
                  <td class="num-cell">{{ u.revenue_quota ? formatCurrency(u.revenue_quota) : '—' }}</td>
                  <td class="num-cell" :class="{ 'green-cell': u.quota_attainment_pct >= 100, 'red-cell': u.quota_attainment_pct !== null && u.quota_attainment_pct < 50 }">
                    {{ u.quota_attainment_pct !== null ? u.quota_attainment_pct + '%' : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>


      </template>
    </div>

    <!-- ═══════════════════════════════ TARGETS TAB ═══════════════════════════ -->
    <div v-if="activeTab === 'targets'">
      <LoadingSpinner v-if="loadingTargets" />
      <div v-else class="section-card">
        <div class="section-title-row">
          <div class="section-title">KPI Targets</div>
          <div class="targets-subtitle">Monthly targets for {{ targetUserName }}</div>
        </div>

        <div v-if="isAdmin" class="filter-group" style="margin-bottom:16px">
          <label>Set targets for</label>
          <select v-model="targetUserId" @change="loadKpiTargets" style="height:36px;padding:0 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13px">
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <div class="kpi-targets-grid">
          <div v-for="m in KPI_METRICS" :key="m.key" class="kpi-target-row">
            <div class="kpi-target-label">
              <span class="kpi-target-icon">{{ m.icon }}</span>
              {{ m.label }}
            </div>
            <div class="kpi-target-input-wrap">
              <input type="number" min="0" step="1"
                     v-model.number="editableTargets[m.key]"
                     class="kpi-target-input"
                     placeholder="0">
              <span class="kpi-target-unit">/ month</span>
            </div>
          </div>
        </div>

        <div class="targets-footer">
          <button class="btn-save-targets" @click="saveKpiTargets" :disabled="savingTargets">
            {{ savingTargets ? 'Saving…' : 'Save Targets' }}
          </button>
          <span v-if="targetsSaved" class="save-success">Saved!</span>
        </div>
      </div>

      <!-- Task Activity Targets (existing, kept for reference) -->
      <div class="section-card" style="margin-top:16px">
        <div class="section-title">Task Activity Targets (Weekly)</div>
        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px">
          These control the weekly targets shown in the Activity tab.
          Edit them via Admin → Performance Targets.
        </p>
        <div class="table-scroll" v-if="taskTargetRows.length > 0">
          <table>
            <thead>
              <tr>
                <th>Task</th>
                <th>Weekly Target</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in taskTargetRows" :key="t.id">
                <td>{{ t.task_name }}</td>
                <td class="num-cell">{{ t.weekly_target }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="empty-state" style="padding:20px">
          No task targets set. Go to Admin → Performance Targets.
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

// ─── Constants ────────────────────────────────────────────────────────────────
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTHS   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

const KPI_METRICS = [
  { key: 'contacts_added',      label: 'New Contacts',        icon: '👥' },
  { key: 'todos_completed',     label: 'To-Dos Completed',    icon: '✅' },
  { key: 'followups_completed', label: 'Follow-Ups Done',     icon: '📞' },
  { key: 'projects_created',    label: 'Projects Created',    icon: '📁' },
  { key: 'deals_created',       label: 'Deals Created',       icon: '📝' },
  { key: 'deals_won',           label: 'Deals Won',           icon: '🏆' },
  { key: 'won_deal_value',      label: 'Won Deal Value (RM)', icon: '💰' },
];

const METRIC_LABELS = Object.fromEntries(KPI_METRICS.map(m => [m.key, m.label]));

// ─── Date helpers (no moment.js) ─────────────────────────────────────────────
function toYMD(d) {
  const dt = new Date(d);
  return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`;
}
function fmtDate(dateStr) {
  if (!dateStr) return '—';
  const [y, m, d] = dateStr.split('-');
  return `${d}-${m}-${String(y).slice(2)}`;
}
function getISOWeek(dateStr) {
  const d = new Date(dateStr);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
  const jan4 = new Date(d.getFullYear(), 0, 4);
  return String(1 + Math.round(((d - jan4) / 86400000 - 3 + ((jan4.getDay() + 6) % 7)) / 7)).padStart(2, '0');
}
function getMondayOfWeek(dateStr) {
  const d = new Date(dateStr);
  const day = d.getDay() || 7;
  d.setDate(d.getDate() - (day - 1));
  return toYMD(d);
}
function buildWeekDates(mondayStr) {
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(mondayStr); d.setDate(d.getDate() + i); return toYMD(d);
  });
}
function buildWeeksInMonth(yearMonth) {
  const [year, month] = yearMonth.split('-').map(Number);
  const firstDay = new Date(year, month - 1, 1);
  const lastDay  = new Date(year, month, 0);
  const weeks    = [];
  const day0     = firstDay.getDay() || 7;
  const monday   = new Date(firstDay);
  monday.setDate(firstDay.getDate() - (day0 - 1));
  let current = new Date(monday);
  while (current <= lastDay) {
    const weekEnd    = new Date(current); weekEnd.setDate(weekEnd.getDate() + 6);
    const clampedEnd = weekEnd > lastDay ? lastDay : weekEnd;
    weeks.push({ isoWeek: getISOWeek(toYMD(current)), start: toYMD(current), end: toYMD(clampedEnd) });
    current.setDate(current.getDate() + 7);
  }
  return weeks;
}
function buildWeeksInYear(year) {
  const jan4      = new Date(year, 0, 4);
  const dayOfJan4 = jan4.getDay() || 7;
  const week1Mon  = new Date(jan4); week1Mon.setDate(jan4.getDate() - (dayOfJan4 - 1));
  const yearEnd   = new Date(year, 11, 31);
  const weeks     = [];
  let current     = new Date(week1Mon);
  while (current <= yearEnd) {
    const weekStart = new Date(current);
    const weekEnd   = new Date(current); weekEnd.setDate(weekEnd.getDate() + 6);
    const thu       = new Date(weekStart); thu.setDate(thu.getDate() + 3);
    if (thu.getFullYear() === year) {
      weeks.push({ isoWeek: getISOWeek(toYMD(weekStart)), start: toYMD(weekStart), end: toYMD(weekEnd), monthLabel: MONTHS[thu.getMonth()] });
    }
    current.setDate(current.getDate() + 7);
    if (weeks.length > 55) break;
  }
  return weeks;
}
function formatCurrency(val) {
  if (!val) return '—';
  return 'RM ' + Number(val).toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
const currentUser = JSON.parse(localStorage.getItem('crm_user') || 'null');
const isAdmin     = (currentUser?.roles ?? []).some(r => ['admin', 'super-admin'].includes(r));

// ─── State ───────────────────────────────────────────────────────────────────
const today        = toYMD(new Date());
const thisMonth    = today.slice(0, 7);
const thisYear     = parseInt(today.slice(0, 4));

const activeTab      = ref('overview');
const viewType       = ref('month');
const selectedMonth  = ref(thisMonth);
const selectedYear   = ref(thisYear);
const currentMonday  = ref(getMondayOfWeek(today));
const selectedUserId = ref(null);
const targetUserId   = ref(null);

// Overview
const overview        = ref(null);
const loadingOverview = ref(false);

// Activity (task breakdown)
const tasks           = ref([]);
const taskTargets     = ref({});   // { task_name: weekly_target }
const taskTargetRows  = ref([]);
const report          = ref({});
const loadingActivity = ref(false);

// Team
const teamData    = ref([]);
const loadingTeam = ref(false);

// Deal forecast
const dealSummary = ref(null);

// KPI Targets
const editableTargets = ref({});
const loadingTargets  = ref(false);
const savingTargets   = ref(false);
const targetsSaved    = ref(false);

// Lookups
const users = ref([]);
const tableRef = ref(null);

// ─── Computed ────────────────────────────────────────────────────────────────
const periodLabel = computed(() => {
  if (viewType.value === 'week') {
    const dates = datesInWeek.value;
    return `${fmtDate(dates[0])} — ${fmtDate(dates[6])}`;
  }
  if (viewType.value === 'month') {
    const [y, m] = selectedMonth.value.split('-');
    return `${MONTHS[parseInt(m)-1]} ${y}`;
  }
  return String(selectedYear.value);
});

const datesInWeek  = computed(() => buildWeekDates(currentMonday.value));
const weeksInMonth = computed(() => buildWeeksInMonth(selectedMonth.value));
const weeksInYear  = computed(() => buildWeeksInYear(selectedYear.value));

const targetUserName = computed(() => {
  if (!targetUserId.value) return '';
  const u = users.value.find(u => u.id === targetUserId.value);
  return u?.name ?? '';
});

const kpiCards = computed(() => {
  const d = overview.value?.kpis;
  if (!d) return [];
  return [
    { key: 'contacts_added',      label: 'Contacts Added',      icon: '👥', value: d.contacts_added,      color: 'blue'   },
    { key: 'todos_created',       label: 'To-Dos Created',      icon: '📋', value: d.todos_created,       color: 'indigo' },
    { key: 'todos_completed',     label: 'To-Dos Completed',    icon: '✅', value: d.todos_completed,     color: 'green'  },
    { key: 'todos_due_today',     label: 'Due Today',           icon: '📅', value: d.todos_due_today,     color: d.todos_due_today > 0 ? 'orange' : 'gray' },
    { key: 'todos_overdue',       label: 'To-Dos Overdue',      icon: '⚠', value: d.todos_overdue,       color: d.todos_overdue > 0 ? 'red' : 'gray'     },
    { key: 'followups_created',   label: 'Follow-Ups Created',  icon: '📞', value: d.followups_created,   color: 'indigo' },
    { key: 'followups_completed', label: 'Follow-Ups Done',     icon: '✓',  value: d.followups_completed, color: 'green'  },
    { key: 'followups_overdue',   label: 'Follow-Ups Overdue',  icon: '⚠', value: d.followups_overdue,   color: d.followups_overdue > 0 ? 'red' : 'gray' },
    { key: 'projects_open',       label: 'Projects Open',       icon: '📁', value: d.projects_open,       color: 'blue'   },
    { key: 'deals_won',           label: 'Deals Won',           icon: '🏆', value: d.deals_won,           color: 'green'  },
    { key: 'won_deal_value',      label: 'Won Value',           icon: '💰', value: formatCurrency(d.won_deal_value), color: 'green' },
  ];
});

const targetProgress = computed(() => {
  const t = overview.value?.targets;
  if (!t) return [];
  return Object.entries(t).map(([metric, data]) => ({
    metric,
    label: METRIC_LABELS[metric] ?? metric,
    target: data.target,
    achieved: data.achieved,
    pct: data.pct,
  }));
});

const stageBarPct = computed(() => {
  const max = Math.max(...(dealSummary.value?.by_stage ?? []).map(s => Number(s.total_value) || 0), 1);
  return (val) => Math.round((Number(val) || 0) / max * 100);
});

const hasAttentionItems = computed(() => {
  const o = overview.value;
  if (!o) return false;
  return o.overdue_todos.length > 0 || o.overdue_followups.length > 0 || o.overdue_deals.length > 0;
});

// ─── Period params ────────────────────────────────────────────────────────────
function periodParams() {
  const p = { view: viewType.value };
  if (viewType.value === 'week') {
    p.start_date = datesInWeek.value[0];
    p.end_date   = datesInWeek.value[6];
  } else if (viewType.value === 'month') {
    p.month = selectedMonth.value;
  } else {
    p.year = selectedYear.value;
  }
  return p;
}

// ─── Navigation (activity tab) ───────────────────────────────────────────────
function prevPeriod() {
  if (viewType.value === 'week') {
    const d = new Date(currentMonday.value); d.setDate(d.getDate() - 7); currentMonday.value = toYMD(d);
  } else if (viewType.value === 'month') {
    const [y, m] = selectedMonth.value.split('-').map(Number);
    const prev = new Date(y, m - 2, 1);
    selectedMonth.value = `${prev.getFullYear()}-${String(prev.getMonth()+1).padStart(2,'0')}`;
  } else {
    selectedYear.value--;
  }
  loadActivity();
}
function nextPeriod() {
  if (viewType.value === 'week') {
    const d = new Date(currentMonday.value); d.setDate(d.getDate() + 7); currentMonday.value = toYMD(d);
  } else if (viewType.value === 'month') {
    const [y, m] = selectedMonth.value.split('-').map(Number);
    const next = new Date(y, m, 1);
    selectedMonth.value = `${next.getFullYear()}-${String(next.getMonth()+1).padStart(2,'0')}`;
  } else {
    selectedYear.value++;
  }
  loadActivity();
}

// ─── Activity tab helpers ─────────────────────────────────────────────────────
function getTaskTarget(taskName, multiplier = 1) {
  const t = taskTargets.value[taskName];
  return t != null ? t * multiplier : null;
}
function getActual(key, taskName) { return report.value?.[key]?.[taskName] ?? 0; }
function getWeekTotal(taskName) { return datesInWeek.value.reduce((s, d) => s + getActual(d, taskName), 0); }
function getPeriodTotal(taskName, keys) { return keys.reduce((s, k) => s + getActual(k, taskName), 0); }
function isOverTarget(taskName) { const t = getTaskTarget(taskName, 1); return t != null && getWeekTotal(taskName) >= t; }
function isUnderTarget(taskName) { const t = getTaskTarget(taskName, 1); return t != null && t > 0 && getWeekTotal(taskName) < t; }

// ─── Event handlers ──────────────────────────────────────────────────────────
function onViewChange() {
  if (activeTab.value === 'overview') { loadOverview(); loadDealSummary(); }
  else if (activeTab.value === 'activity') loadActivity();
  else if (activeTab.value === 'team') loadTeam();
}
function onUserChange() {
  targetUserId.value = selectedUserId.value;
  loadOverview();
  loadDealSummary();
  loadTaskTargets();
  loadActivity();
}
function switchTeam() {
  activeTab.value = 'team';
  if (teamData.value.length === 0) loadTeam();
}
function switchTargets() {
  activeTab.value = 'targets';
  if (Object.keys(editableTargets.value).length === 0) loadKpiTargets();
}

// ─── API calls ───────────────────────────────────────────────────────────────
async function loadLookups() {
  const res = await api.get('/v1/lookups');
  tasks.value = res.data.tasks ?? [];
  users.value = res.data.users ?? [];
  if (!selectedUserId.value) {
    const me = users.value.find(u => u.id === currentUser?.id);
    selectedUserId.value = me ? me.id : (users.value[0]?.id ?? null);
  }
  targetUserId.value = selectedUserId.value;
}

async function loadOverview() {
  if (!selectedUserId.value) return;
  loadingOverview.value = true;
  try {
    const res = await api.get('/v1/performance/overview', {
      params: { ...periodParams(), user_id: selectedUserId.value },
    });
    overview.value = res.data.data;
  } finally {
    loadingOverview.value = false;
  }
}

async function loadDealSummary() {
  if (!selectedUserId.value) return;
  const params = { ...periodParams(), user_id: selectedUserId.value };
  if (params.view === 'week') {
    params.from_date = params.start_date;
    params.to_date   = params.end_date;
    delete params.start_date; delete params.end_date;
  } else if (params.view === 'month') {
    const [y, m] = params.month.split('-');
    params.from_date = `${y}-${m}-01`;
    const last = new Date(y, m, 0);
    params.to_date = `${y}-${m}-${String(last.getDate()).padStart(2,'0')}`;
    delete params.month;
  } else {
    params.from_date = `${params.year}-01-01`;
    params.to_date   = `${params.year}-12-31`;
    delete params.year;
  }
  delete params.view;
  const res = await api.get('/v1/deals/summary', { params });
  dealSummary.value = res.data.data;
}

async function loadTaskTargets() {
  if (!selectedUserId.value) return;
  const res = await api.get(`/v1/performance/targets/${selectedUserId.value}`);
  const map = {};
  taskTargetRows.value = res.data.data ?? [];
  for (const t of taskTargetRows.value) map[t.task_name] = t.weekly_target;
  taskTargets.value = map;
}

async function loadActivity() {
  if (!selectedUserId.value) return;
  loadingActivity.value = true;
  try {
    const res = await api.get('/v1/performance/report', {
      params: { ...periodParams(), user_id: selectedUserId.value },
    });
    report.value = res.data.data ?? {};
  } finally {
    loadingActivity.value = false;
  }
}

async function loadTeam() {
  loadingTeam.value = true;
  try {
    const res = await api.get('/v1/performance/team', { params: periodParams() });
    teamData.value = res.data.data ?? [];
  } finally {
    loadingTeam.value = false;
  }
}

async function loadKpiTargets() {
  if (!targetUserId.value) return;
  loadingTargets.value = true;
  try {
    const res = await api.get(`/v1/performance/kpi-targets/${targetUserId.value}`);
    const map = {};
    for (const m of KPI_METRICS) {
      map[m.key] = Number(res.data.data[m.key]?.target_value ?? 0);
    }
    editableTargets.value = map;
  } finally {
    loadingTargets.value = false;
  }
}

async function saveKpiTargets() {
  savingTargets.value = true;
  targetsSaved.value  = false;
  try {
    const targets = KPI_METRICS.map(m => ({
      metric:       m.key,
      target_value: editableTargets.value[m.key] ?? 0,
    }));
    await api.put(`/v1/performance/kpi-targets/${targetUserId.value}`, { targets });
    targetsSaved.value = true;
    setTimeout(() => { targetsSaved.value = false; }, 3000);
    // Refresh overview progress bars
    await loadOverview();
  } finally {
    savingTargets.value = false;
  }
}

function exportActivityCSV() {
  if (!tableRef.value) return;
  const rows = [...tableRef.value.querySelectorAll('tr')].map(tr =>
    [...tr.querySelectorAll('th,td')].map(c => `"${c.innerText.replace(/"/g, '\\"')}"`).join(',')
  );
  const csv = '﻿' + rows.join('\n');
  const a   = document.createElement('a');
  a.href     = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = `Activity_${periodLabel.value.replace(/[^a-zA-Z0-9]/g, '-')}.csv`;
  a.click();
}

// ─── Mount ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadLookups();
  await Promise.all([loadOverview(), loadDealSummary(), loadTaskTargets(), loadActivity()]);
});
</script>

<style scoped>
.page { padding: 24px 28px; }

/* Banner */
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #7c3aed);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.btn-export-top {
  background: #7c3aed; color: white; border-radius: 8px;
  padding: 9px 18px; font-size: 13px; font-weight: 700;
  border: 2px solid rgba(255,255,255,0.3); cursor: pointer; white-space: nowrap;
}

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: 10px; padding: 14px 18px;
  margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); }
.filter-group select, .filter-group input {
  height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; outline: none; background: var(--surface);
}
.filter-group select:focus, .filter-group input:focus { border-color: #7c3aed; }

/* Tabs */
.tab-nav {
  display: flex; gap: 4px; margin-bottom: 16px;
  background: var(--surface); border-radius: 10px; padding: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}
.tab-btn {
  flex: 1; height: 38px; border: none; border-radius: 7px; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--text-2); background: transparent;
  transition: background 0.15s, color 0.15s;
}
.tab-btn.active { background: #7c3aed; color: white; }
.tab-btn:hover:not(.active) { background: #f5f3ff; color: #7c3aed; }

/* Section label */
.section-label {
  font-size: 13px; font-weight: 700; color: var(--text-2);
  margin-bottom: 12px; padding: 0 2px;
}

/* KPI Cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px; margin-bottom: 16px;
}
.kpi-card {
  background: var(--surface); border-radius: 10px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; align-items: center; gap: 12px;
  border-left: 4px solid var(--border);
}
.kpi-blue   { border-left-color: #3b82f6; }
.kpi-indigo { border-left-color: #6366f1; }
.kpi-green  { border-left-color: #10b981; }
.kpi-orange { border-left-color: #f97316; }
.kpi-red    { border-left-color: #ef4444; background: #fff5f5; }
.kpi-gray   { border-left-color: #d1d5db; }

.kpi-icon  { font-size: 22px; line-height: 1; }
.kpi-value { font-size: 22px; font-weight: 800; color: var(--text-1); line-height: 1; }
.kpi-label { font-size: 11px; color: var(--text-2); font-weight: 600; margin-top: 3px; }

/* Section Card */
.section-card {
  background: var(--surface); border-radius: 10px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07); margin-bottom: 16px;
}
.section-title { font-size: 14px; font-weight: 700; color: var(--text-1); margin-bottom: 14px; }
.section-title-row { display: flex; align-items: baseline; gap: 12px; margin-bottom: 14px; }
.targets-subtitle { font-size: 12px; color: var(--text-3); }

/* Progress bars */
.progress-list { display: flex; flex-direction: column; gap: 12px; }
.progress-row { display: grid; grid-template-columns: 1fr 120px auto; align-items: center; gap: 12px; }
.progress-meta { display: flex; justify-content: space-between; align-items: center; }
.progress-label { font-size: 12px; font-weight: 600; color: #374151; }
.progress-nums  { font-size: 11px; color: var(--text-3); margin-left: 8px; }
.progress-bar-wrap { height: 8px; background: var(--app-bg); border-radius: 99px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: #7c3aed; border-radius: 99px; min-width: 2px; transition: width 0.4s; }
.bar-done { background: #10b981; }
.bar-low  { background: #f97316; }
.progress-pct { font-size: 11px; font-weight: 700; color: var(--text-2); width: 36px; text-align: right; }
.pct-done { color: #10b981; }

/* Needs Attention */
.attention-card  { border-left: 4px solid #ef4444; }
.attention-title { color: #b91c1c; }
.attention-group { margin-bottom: 16px; }
.attention-group:last-child { margin-bottom: 0; }
.attention-group-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.6px; color: #94a3b8; margin-bottom: 8px;
}
.attention-list { display: flex; flex-direction: column; gap: 6px; }
.attention-item {
  display: flex; align-items: center; gap: 10px;
  background: #fff5f5; border-radius: 7px; padding: 8px 12px;
  font-size: 12px;
}
.badge-overdue {
  background: #ef4444; color: white; border-radius: 4px;
  padding: 2px 6px; font-size: 10px; font-weight: 700; white-space: nowrap;
}
.att-company { font-weight: 700; color: #1e293b; text-decoration: none; }
.att-company:hover { color: #7c3aed; }
.att-meta { color: #64748b; flex: 1; }
.att-action {
  background: var(--app-bg); color: #374151; border-radius: 5px;
  padding: 3px 10px; font-size: 11px; font-weight: 600; text-decoration: none; white-space: nowrap;
}
.att-action:hover { background: #7c3aed; color: white; }

.no-attention {
  background: #f0fdf4; border-radius: 10px; padding: 20px 24px;
  font-size: 13px; color: #166534; font-weight: 600;
  display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
}
.no-att-icon { font-size: 18px; }

/* Date nav (activity tab) */
.date-nav {
  background: var(--surface); border-radius: 10px; padding: 12px 18px;
  margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  display: flex; align-items: center; justify-content: space-between;
}
.nav-btn {
  height: 36px; padding: 0 18px; background: #7c3aed; color: white;
  border: none; border-radius: 7px; cursor: pointer; font-size: 13px; font-weight: 700;
}
.nav-btn:hover { background: #6d28d9; }
.period-label { font-size: 15px; font-weight: 700; color: var(--text-1); }

/* Tables (shared) */
.table-wrap { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 16px; }
.table-header-bar { background: var(--app-bg); padding: 12px 16px; font-size: 13px; font-weight: 700; color: var(--text-1); border-bottom: 2px solid var(--border); }
.loading-msg { text-align: center; padding: 48px; color: var(--text-3); font-size: 14px; }
.table-scroll { overflow-x: auto; overflow-y: auto; max-height: 560px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }

/* Activity table header */
.header-tasks th {
  background: #1e293b; color: #f8fafc; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 14px;
  text-align: left; white-space: nowrap;
}
.header-tasks .col-period { min-width: 160px; }
.header-tasks .col-task   { min-width: 90px; text-align: center; }
.header-targets { background: #334155; }
.header-targets td { padding: 6px 14px; font-size: 11px; color: #e2e8f0; }
.target-label { font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.target-val   { text-align: center; font-weight: 700; color: #a78bfa; }

tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover:not(.total-row) { background: #faf5ff; }
.weekend-row { background: #f8fafc; }
.col-period { padding: 9px 14px; color: #374151; white-space: nowrap; font-weight: 600; min-width: 130px; }
.col-period.month-label { color: #7c3aed; font-weight: 700; font-size: 11px; min-width: 50px; }
.col-period.small { font-size: 11px; color: #64748b; font-weight: 400; }
.weekday-tag { display: inline-block; margin-left: 6px; background: #f1f5f9; color: #64748b; font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 600; }
.col-count { padding: 9px 14px; text-align: center; color: #64748b; }
.col-count.has-data { color: #1e293b; font-weight: 700; background: #f5f3ff; }
.total-row { background: #f0fdf4; }
.total-row td { padding: 10px 14px; color: #166534; font-weight: 700; }
.total-row .over-target  { color: #15803d; background: #dcfce7; }
.total-row .under-target { color: #b91c1c; background: #fee2e2; }

/* Team table */
thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 14px;
  border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap;
}
tbody td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: #374151; vertical-align: middle; }
.user-cell { font-weight: 600; color: var(--text-1); }
.num-cell  { text-align: center; }
.green-cell { color: #166534; font-weight: 600; }
.red-cell   { color: #b91c1c; font-weight: 600; background: #fff5f5; }
.empty-state { text-align: center; padding: 40px; color: var(--text-3); font-size: 14px; }

/* KPI Targets editor */
.kpi-targets-grid { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.kpi-target-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; background: var(--app-bg); border-radius: 8px;
}
.kpi-target-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #374151; }
.kpi-target-icon  { font-size: 16px; }
.kpi-target-input-wrap { display: flex; align-items: center; gap: 6px; }
.kpi-target-input {
  width: 80px; height: 34px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; text-align: right; outline: none;
}
.kpi-target-input:focus { border-color: #7c3aed; }
.kpi-target-unit { font-size: 11px; color: var(--text-3); }
.targets-footer { display: flex; align-items: center; gap: 12px; }
.btn-save-targets {
  height: 38px; padding: 0 24px; background: #7c3aed; color: white;
  border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;
}
.btn-save-targets:hover:not(:disabled) { background: #6d28d9; }
.btn-save-targets:disabled { opacity: 0.6; cursor: not-allowed; }
.save-success { font-size: 13px; color: #10b981; font-weight: 600; }

/* Pipeline Forecast */
.forecast-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;
}
.forecast-stat {
  background: var(--app-bg); border-radius: 8px; padding: 14px 16px;
  border-left: 4px solid var(--border); text-align: center;
}
.forecast-stat.highlight { border-left-color: #7c3aed; background: #faf5ff; }
.forecast-stat.green     { border-left-color: #10b981; background: #f0fdf4; }
.forecast-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); margin-bottom: 6px; }
.forecast-value { font-size: 20px; font-weight: 800; color: var(--text-1); line-height: 1; }
.forecast-sub   { font-size: 11px; color: var(--text-3); margin-top: 4px; }

.stage-bars { margin-top: 4px; }
.stage-bars-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #94a3b8; margin-bottom: 8px; }
.stage-row { display: grid; grid-template-columns: 130px 1fr 32px 110px; align-items: center; gap: 10px; padding: 5px 0; }
.stage-name { font-size: 12px; font-weight: 600; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stage-bar-wrap { height: 8px; background: var(--app-bg); border-radius: 99px; overflow: hidden; }
.stage-bar-fill { height: 100%; background: #7c3aed; border-radius: 99px; min-width: 2px; transition: width 0.4s; }
.stage-count { font-size: 11px; font-weight: 700; color: var(--text-2); text-align: center; }
.stage-val   { font-size: 11px; font-weight: 700; color: #374151; text-align: right; }

/* Quota Attainment Cards */
.quota-summary-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px; margin-bottom: 16px;
}
.quota-card {
  background: var(--surface); border-radius: 10px; padding: 14px 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07); border-left: 4px solid #7c3aed;
}
.quota-card.quota-met  { border-left-color: #10b981; }
.quota-card.quota-low  { border-left-color: #ef4444; }
.quota-user { font-size: 13px; font-weight: 700; color: var(--text-1); margin-bottom: 8px; }
.quota-bar-wrap { height: 8px; background: var(--app-bg); border-radius: 99px; overflow: hidden; margin-bottom: 6px; }
.quota-bar-fill { height: 100%; background: #7c3aed; border-radius: 99px; min-width: 2px; transition: width 0.4s; max-width: 100%; }
.quota-met  .quota-bar-fill { background: #10b981; }
.quota-low  .quota-bar-fill { background: #ef4444; }
.quota-numbers { font-size: 11px; color: var(--text-2); display: flex; justify-content: space-between; align-items: center; }
.quota-pct { font-size: 12px; font-weight: 700; color: #7c3aed; }
.pct-done  { color: #10b981; }
.quota-hint { font-size: 12px; color: var(--text-3); padding: 16px 0; grid-column: 1 / -1; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .progress-row { grid-template-columns: 1fr 80px auto; }
  .forecast-grid { grid-template-columns: 1fr; }
  .stage-row { grid-template-columns: 100px 1fr 28px 90px; }
  table { min-width: 600px; }
  .attention-item { flex-wrap: wrap; }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .tab-btn { font-size: 11px; }
  .kpi-target-row { flex-direction: column; align-items: flex-start; gap: 8px; }
}
</style>
