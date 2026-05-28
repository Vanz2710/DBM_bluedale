<template>
  <div class="page ca">

    <!-- ══ Header + Date Picker ══════════════════════════════════════════════ -->
    <div class="ca-header">
      <div class="ca-header-left">
        <h1>Contact Analysis</h1>
        <p class="ca-subtitle">Insights into acquisition, engagement, and activity patterns across your contacts.</p>
      </div>
      <div class="ca-date-wrap" ref="pickerRef">
        <button class="ca-date-btn" @click.stop="pickerOpen = !pickerOpen">
          <svg class="ca-date-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="1" y="3" width="14" height="12" rx="2"/>
            <path d="M1 7h14M5 1v4M11 1v4"/>
          </svg>
          <span>{{ filters.label }}</span>
          <svg class="ca-caret" viewBox="0 0 10 6" fill="currentColor"><path d="M0 0l5 6 5-6z"/></svg>
        </button>
        <transition name="panel-drop">
          <div v-if="pickerOpen" class="ca-date-panel" @click.stop>
            <div class="ca-presets">
              <button
                v-for="p in PRESETS"
                :key="p.label"
                class="ca-preset"
                :class="{ 'ca-preset--on': filters.label === p.label }"
                @click="applyPreset(p)"
              >{{ p.label }}</button>
            </div>
            <div class="ca-custom">
              <div class="ca-custom-title">Custom range</div>
              <label class="ca-custom-row">
                <span>From</span>
                <input type="date" v-model="customFrom" :max="customTo" />
              </label>
              <label class="ca-custom-row">
                <span>To</span>
                <input type="date" v-model="customTo" :min="customFrom" :max="todayStr" />
              </label>
              <button class="btn btn-primary ca-apply-btn" @click="applyCustom">Apply</button>
            </div>
          </div>
        </transition>
      </div>
    </div>

    <!-- ══ Secondary Filters ═════════════════════════════════════════════════ -->
    <div class="ca-filters">
      <select v-if="isAdmin" v-model="filters.user_id" @change="loadAll" class="ca-fc">
        <option value="">All Agents</option>
        <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <select v-model="filters.status_id" @change="loadAll" class="ca-fc">
        <option value="">All Statuses</option>
        <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filters.industry_id" @change="loadAll" class="ca-fc">
        <option value="">All Industries</option>
        <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
      </select>
    </div>

    <!-- ══ KPI Cards ══════════════════════════════════════════════════════════ -->
    <div class="ca-kpi-row">
      <div v-for="card in kpiCards" :key="card.key" class="ca-kpi">
        <div class="ca-kpi-label">{{ card.label }}</div>
        <div class="ca-kpi-value">
          <span v-if="loading.overview" class="ca-kpi-loading">—</span>
          <span v-else>{{ card.value }}</span>
        </div>
        <div class="ca-kpi-delta" :class="deltaClass(card.delta)">
          <template v-if="!loading.overview && card.delta !== null">
            {{ card.delta > 0 ? '▲' : card.delta < 0 ? '▼' : '—' }}
            {{ Math.abs(card.delta) }}% vs prev. period
          </template>
          <template v-else-if="!loading.overview">No prior data</template>
        </div>
      </div>
    </div>

    <!-- ══ Activity Trend ═════════════════════════════════════════════════════ -->
    <div class="ca-card">
      <div class="ca-card-top">
        <div>
          <div class="ca-card-title">Activity Over Time</div>
          <div class="ca-card-sub">Daily counts within the selected period</div>
        </div>
        <div class="ca-pill-row">
          <button
            v-for="t in TREND_TABS"
            :key="t.key"
            class="ca-pill"
            :class="{ 'ca-pill--on': activeTrend === t.key }"
            @click="switchTrend(t.key)"
          >{{ t.label }}</button>
        </div>
      </div>
      <div class="ca-chart-box" style="height:220px">
        <div v-if="loading.overview" class="ca-chart-loading">Loading…</div>
        <canvas v-else ref="trendRef" class="ca-canvas"></canvas>
      </div>
    </div>

    <!-- ══ Lead Source + Follow-Up Actions ═══════════════════════════════════ -->
    <div class="ca-row-2">

      <!-- Lead Source -->
      <div class="ca-card">
        <div class="ca-card-top">
          <div>
            <div class="ca-card-title">Lead Source</div>
            <div class="ca-card-sub">Contacts added in period by acquisition channel</div>
          </div>
          <span class="ca-card-total">{{ sourceData.total }} contacts</span>
        </div>
        <div v-if="loading.source" class="ca-chart-loading" style="height:160px">Loading…</div>
        <template v-else>
          <div v-if="sourceData.sources?.length" class="ca-chart-box" style="height:180px">
            <canvas ref="sourceRef" class="ca-canvas"></canvas>
          </div>
          <div v-else class="ca-empty-chart">No contacts added in this period.</div>
          <table class="ca-tbl" v-if="sourceData.sources?.length">
            <thead><tr><th>Source</th><th>Contacts</th><th>Share</th></tr></thead>
            <tbody>
              <tr v-for="s in sourceData.sources" :key="s.source">
                <td>
                  <span class="ca-dot" :style="{ background: sourceColor(s.source) }"></span>
                  {{ s.label }}
                </td>
                <td>{{ s.count }}</td>
                <td>{{ s.pct }}%</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

      <!-- Follow-Up Action Types -->
      <div class="ca-card">
        <div class="ca-card-top">
          <div>
            <div class="ca-card-title">Follow-Up Activity Types</div>
            <div class="ca-card-sub">Follow-ups logged in period by action type</div>
          </div>
          <span class="ca-card-total">{{ actionData.total }} follow-ups</span>
        </div>
        <div v-if="loading.actions" class="ca-chart-loading" style="height:160px">Loading…</div>
        <template v-else>
          <div v-if="actionData.by_action?.length" class="ca-chart-box" style="height:180px">
            <canvas ref="actionRef" class="ca-canvas"></canvas>
          </div>
          <div v-else class="ca-empty-chart">No follow-ups logged in this period.</div>
          <table class="ca-tbl" v-if="actionData.by_action?.length">
            <thead><tr><th>Action</th><th>Total</th><th>Done</th><th>Completion Rate</th></tr></thead>
            <tbody>
              <tr v-for="a in actionData.by_action" :key="a.action_type">
                <td>{{ a.label }}</td>
                <td>{{ a.total }}</td>
                <td>{{ a.completed }}</td>
                <td>
                  <div class="ca-rate-wrap">
                    <div class="ca-rate-bar">
                      <div class="ca-rate-fill" :style="{ width: a.completion_rate + '%' }"></div>
                    </div>
                    <span class="ca-rate-txt">{{ a.completion_rate }}%</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>

    </div>

    <!-- ══ Engagement Health Table ════════════════════════════════════════════ -->
    <div class="ca-card">
      <div class="ca-card-top ca-card-top--wrap">
        <div>
          <div class="ca-card-title">Engagement Health</div>
          <div class="ca-card-sub">Current contact activity status · independent of the date range above</div>
        </div>
        <div class="ca-health-tabs">
          <button
            v-for="h in HEALTH_TABS"
            :key="h.key"
            class="ca-health-tab"
            :class="[`ca-health-tab--${h.key || 'all'}`, engFilters.health === h.key && 'ca-health-tab--on']"
            @click="setHealth(h.key)"
          >
            <span v-if="h.key" class="ca-health-dot"></span>
            {{ h.label }}
            <strong>{{ engSummary[h.countKey] ?? 0 }}</strong>
          </button>
        </div>
      </div>

      <!-- Engagement filter bar -->
      <div class="ca-eng-bar">
        <input
          v-model="engFilters.q"
          class="ca-fc"
          placeholder="Search contact name…"
          @keyup.enter="loadEngagement(1)"
        />
        <select v-if="isAdmin" v-model="engFilters.user_id" @change="loadEngagement(1)" class="ca-fc">
          <option value="">All Agents</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-model="engFilters.status_id" @change="loadEngagement(1)" class="ca-fc">
          <option value="">All Statuses</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <button class="btn btn-search" @click="loadEngagement(1)">Search</button>
      </div>

      <div v-if="loading.engagement" class="ca-chart-loading" style="height:140px">Loading…</div>
      <template v-else>
        <div class="ca-tbl-scroll">
          <table class="ca-tbl ca-tbl--full">
            <thead>
              <tr>
                <th class="ca-th-sort" @click="sortEng('name')">
                  Contact <span class="ca-sort-icon">{{ sortIcon('name') }}</span>
                </th>
                <th>Agent</th>
                <th>Status</th>
                <th class="ca-th-sort" @click="sortEng('last_todo_date')">
                  Last Task <span class="ca-sort-icon">{{ sortIcon('last_todo_date') }}</span>
                </th>
                <th class="ca-th-sort" @click="sortEng('days_inactive')">
                  Days Inactive <span class="ca-sort-icon">{{ sortIcon('days_inactive') }}</span>
                </th>
                <th>Health</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="engData.length === 0">
                <td colspan="6" class="ca-empty">No contacts found for this filter.</td>
              </tr>
              <tr v-for="c in engData" :key="c.id">
                <td>
                  <router-link :to="`/contacts/${c.id}`" class="ca-link">{{ c.name }}</router-link>
                </td>
                <td>{{ c.user_name }}</td>
                <td>{{ c.status_name }}</td>
                <td>{{ c.last_todo_date ?? '—' }}</td>
                <td>{{ c.days_inactive !== null ? c.days_inactive + 'd' : '—' }}</td>
                <td>
                  <span class="ca-badge" :class="`ca-badge--${c.health}`">
                    {{ healthLabel(c.health) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="ca-pagination">
          <button
            class="ca-page-btn"
            :disabled="engMeta.current_page === 1"
            @click="loadEngagement(engMeta.current_page - 1)"
          >‹ Prev</button>
          <span class="ca-page-info">
            Page {{ engMeta.current_page }} of {{ engMeta.last_page }}
            &nbsp;·&nbsp; {{ engMeta.total }} contacts
          </span>
          <button
            class="ca-page-btn"
            :disabled="engMeta.current_page === engMeta.last_page"
            @click="loadEngagement(engMeta.current_page + 1)"
          >Next ›</button>
        </div>
      </template>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import {
  Chart,
  BarController, BarElement,
  DoughnutController, ArcElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
} from 'chart.js';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

Chart.register(
  BarController, BarElement,
  DoughnutController, ArcElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
);

// ─── Auth ──────────────────────────────────────────────────────────────────
const user    = JSON.parse(localStorage.getItem('crm_user') || 'null');
const isAdmin = (user?.roles ?? []).some(r => ['admin', 'super-admin'].includes(r));

// ─── Date helpers ──────────────────────────────────────────────────────────
function fmtDate(d)    { return d.toISOString().slice(0, 10); }
function subDays(d, n) { const r = new Date(d); r.setDate(r.getDate() - n); return r; }

const today    = new Date();
const todayStr = fmtDate(today);

const PRESETS = [
  { label: 'Today',       from: todayStr, to: todayStr },
  { label: 'Yesterday',   from: fmtDate(subDays(today, 1)), to: fmtDate(subDays(today, 1)) },
  { label: 'Last 7 days', from: fmtDate(subDays(today, 6)), to: todayStr },
  { label: 'Last 30 days',from: fmtDate(subDays(today, 29)), to: todayStr },
  { label: 'Last 90 days',from: fmtDate(subDays(today, 89)), to: todayStr },
  {
    label: 'This month',
    from: fmtDate(new Date(today.getFullYear(), today.getMonth(), 1)),
    to: todayStr,
  },
  {
    label: 'Last month',
    from: fmtDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
    to: fmtDate(new Date(today.getFullYear(), today.getMonth(), 0)),
  },
  { label: 'This year', from: fmtDate(new Date(today.getFullYear(), 0, 1)), to: todayStr },
];

// ─── Picker state ──────────────────────────────────────────────────────────
const pickerOpen = ref(false);
const pickerRef  = ref(null);
const customFrom = ref(fmtDate(subDays(today, 29)));
const customTo   = ref(todayStr);

function applyPreset(p) {
  filters.from  = p.from;
  filters.to    = p.to;
  filters.label = p.label;
  customFrom.value = p.from;
  customTo.value   = p.to;
  pickerOpen.value = false;
  loadAll();
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return;
  filters.from  = customFrom.value;
  filters.to    = customTo.value;
  filters.label = `${customFrom.value} → ${customTo.value}`;
  pickerOpen.value = false;
  loadAll();
}

function handleOutsideClick(e) {
  if (pickerRef.value && !pickerRef.value.contains(e.target)) {
    pickerOpen.value = false;
  }
}

// ─── Filters ───────────────────────────────────────────────────────────────
const filters = reactive({
  from: fmtDate(subDays(today, 29)),
  to:   todayStr,
  label: 'Last 30 days',
  user_id:     '',
  status_id:   '',
  industry_id: '',
});

const lookups = reactive({ users: [], statuses: [], industries: [] });

function buildParams() {
  const p = { from: filters.from, to: filters.to };
  if (filters.user_id)     p.user_id     = filters.user_id;
  if (filters.status_id)   p.status_id   = filters.status_id;
  if (filters.industry_id) p.industry_id = filters.industry_id;
  return p;
}

// ─── Loading state ─────────────────────────────────────────────────────────
const loading = reactive({ overview: false, source: false, actions: false, engagement: false });

// ─── Overview / KPI cards ──────────────────────────────────────────────────
const overviewData = ref(null);

function calcDelta(curr, prev) {
  if (prev === 0) return curr > 0 ? 100 : null;
  return Math.round((curr - prev) / prev * 100);
}

function deltaClass(delta) {
  if (delta === null) return 'ca-delta--neutral';
  if (delta > 0)      return 'ca-delta--up';
  if (delta < 0)      return 'ca-delta--down';
  return 'ca-delta--neutral';
}

const kpiCards = computed(() => {
  const d = overviewData.value;
  if (!d) return [
    { key: 'contacts',  label: 'Contacts Added',       value: '—', delta: null },
    { key: 'tasks',     label: 'Tasks Scheduled',       value: '—', delta: null },
    { key: 'followups', label: 'Follow-Ups Completed',  value: '—', delta: null },
    { key: 'engaged',   label: 'Engaged Contacts',      value: '—', delta: null },
  ];
  return [
    { key: 'contacts',  label: 'Contacts Added',      value: d.contacts_added,      delta: calcDelta(d.contacts_added,      d.prev_contacts_added) },
    { key: 'tasks',     label: 'Tasks Scheduled',      value: d.tasks_created,       delta: calcDelta(d.tasks_created,       d.prev_tasks_created) },
    { key: 'followups', label: 'Follow-Ups Completed', value: d.followups_completed, delta: calcDelta(d.followups_completed, d.prev_followups_completed) },
    { key: 'engaged',   label: 'Engaged Contacts',     value: d.engaged_contacts,    delta: calcDelta(d.engaged_contacts,    d.prev_engaged_contacts) },
  ];
});

// ─── Activity Trend chart ──────────────────────────────────────────────────
const trendRef   = ref(null);
let   trendChart = null;
const activeTrend = ref('contacts');

const TREND_TABS = [
  { key: 'contacts',  label: 'Contacts',   color: '#7c3aed', rgb: '124,58,237' },
  { key: 'tasks',     label: 'Tasks',      color: '#0891b2', rgb: '8,145,178' },
  { key: 'followups', label: 'Follow-Ups', color: '#059669', rgb: '5,150,105' },
];

function buildTrendChart() {
  trendChart?.destroy(); trendChart = null;
  if (!trendRef.value || !overviewData.value?.daily_trend?.length) return;
  const trend = overviewData.value.daily_trend;
  const tab   = TREND_TABS.find(t => t.key === activeTrend.value);
  trendChart  = new Chart(trendRef.value.getContext('2d'), {
    type: 'bar',
    data: {
      labels: trend.map(d => d.date),
      datasets: [{
        label: tab.label,
        data: trend.map(d => d[tab.key]),
        backgroundColor: `rgba(${tab.rgb},0.6)`,
        borderColor: tab.color,
        borderWidth: 1,
        borderRadius: 3,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: tooltipDefaults() },
      scales: {
        x: {
          border: { display: false },
          grid:   { display: false },
          ticks:  { font: { size: 9 }, color: '#94a3b8', maxTicksLimit: 14, maxRotation: 45 },
        },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: 'rgba(148,163,184,0.12)', drawTicks: false },
          ticks: { font: { size: 10 }, color: '#94a3b8', padding: 8, stepSize: 1 },
        },
      },
    },
  });
}

async function switchTrend(key) {
  activeTrend.value = key;
  await nextTick();
  buildTrendChart();
}

// ─── Lead Source chart ─────────────────────────────────────────────────────
const sourceData  = ref({ sources: [], total: 0 });
const sourceRef   = ref(null);
let   sourceChart = null;

const SOURCE_COLORS = {
  manual: '#7c3aed', phone_call: '#0891b2', referral: '#059669',
  walk_in: '#d97706', social_media: '#db2777', email_campaign: '#dc2626',
  web_form: '#4f46e5', other: '#64748b', unknown: '#94a3b8',
};
function sourceColor(s) { return SOURCE_COLORS[s] ?? '#94a3b8'; }

function buildSourceChart() {
  sourceChart?.destroy(); sourceChart = null;
  if (!sourceRef.value || !sourceData.value.sources?.length) return;
  const rows = sourceData.value.sources;
  sourceChart = new Chart(sourceRef.value.getContext('2d'), {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        data: rows.map(r => r.count),
        backgroundColor: rows.map(r => sourceColor(r.source)),
        borderRadius: 5,
        borderSkipped: false,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: tooltipDefaults() },
      scales: {
        x: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(148,163,184,0.12)' }, ticks: { font: { size: 10 }, color: '#94a3b8', stepSize: 1 } },
        y: { border: { display: false }, grid: { display: false }, ticks: { font: { size: 10 }, color: '#64748b', maxRotation: 0 } },
      },
    },
  });
}

// ─── Follow-Up Action chart ────────────────────────────────────────────────
const actionData  = ref({ by_action: [], total: 0 });
const actionRef   = ref(null);
let   actionChart = null;

const ACTION_PALETTE = ['#7c3aed','#0891b2','#059669','#d97706','#dc2626','#db2777','#4f46e5','#0e7490','#84cc16','#f59e0b'];

function buildActionChart() {
  actionChart?.destroy(); actionChart = null;
  if (!actionRef.value || !actionData.value.by_action?.length) return;
  const rows = actionData.value.by_action;
  actionChart = new Chart(actionRef.value.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        data: rows.map(r => r.total),
        backgroundColor: ACTION_PALETTE.slice(0, rows.length),
        borderColor: '#fff',
        borderWidth: 2,
        hoverOffset: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: {
          display: true,
          position: 'right',
          labels: { font: { size: 10 }, color: '#64748b', padding: 8, boxWidth: 10, usePointStyle: true },
        },
        tooltip: tooltipDefaults(),
      },
    },
  });
}

// ─── Engagement table ──────────────────────────────────────────────────────
const engData    = ref([]);
const engMeta    = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 50 });
const engSummary = ref({ total: 0, active: 0, at_risk: 0, dormant: 0, no_activity: 0 });

const engFilters = reactive({
  health:    '',
  q:         '',
  user_id:   '',
  status_id: '',
  sort_by:   'days_inactive',
  sort_dir:  'desc',
});

const HEALTH_TABS = [
  { key: '',        label: 'All',     countKey: 'total' },
  { key: 'active',  label: 'Active',  countKey: 'active' },
  { key: 'at_risk', label: 'At Risk', countKey: 'at_risk' },
  { key: 'dormant', label: 'Dormant', countKey: 'dormant' },
];

function healthLabel(h) {
  return { active: 'Active', at_risk: 'At Risk', dormant: 'Dormant', no_activity: 'No Tasks' }[h] ?? h;
}

function setHealth(key) {
  engFilters.health = key;
  loadEngagement(1);
}

function sortEng(field) {
  if (engFilters.sort_by === field) {
    engFilters.sort_dir = engFilters.sort_dir === 'asc' ? 'desc' : 'asc';
  } else {
    engFilters.sort_by  = field;
    engFilters.sort_dir = 'desc';
  }
  loadEngagement(1);
}

function sortIcon(field) {
  if (engFilters.sort_by !== field) return '↕';
  return engFilters.sort_dir === 'asc' ? '↑' : '↓';
}

// ─── Shared tooltip style ──────────────────────────────────────────────────
function tooltipDefaults() {
  return {
    backgroundColor: '#1e1b4b',
    padding: 10,
    titleFont:   { size: 11, weight: '600' },
    bodyFont:    { size: 12 },
    displayColors: false,
    cornerRadius: 8,
  };
}

// ─── Load functions ────────────────────────────────────────────────────────
async function loadOverview() {
  loading.overview = true;
  trendChart?.destroy(); trendChart = null;
  try {
    const { data } = await api.get('/v1/contact-analysis/overview', { params: buildParams() });
    overviewData.value = data;
    await nextTick();
    buildTrendChart();
  } finally {
    loading.overview = false;
  }
}

async function loadSource() {
  loading.source = true;
  sourceChart?.destroy(); sourceChart = null;
  try {
    const { data } = await api.get('/v1/contact-analysis/lead-source', { params: buildParams() });
    sourceData.value = data;
    await nextTick();
    buildSourceChart();
  } finally {
    loading.source = false;
  }
}

async function loadActions() {
  loading.actions = true;
  actionChart?.destroy(); actionChart = null;
  try {
    const { data } = await api.get('/v1/contact-analysis/followup-actions', { params: buildParams() });
    actionData.value = data;
    await nextTick();
    buildActionChart();
  } finally {
    loading.actions = false;
  }
}

async function loadEngagement(page = 1) {
  loading.engagement = true;
  try {
    const params = {
      page,
      per_page: 50,
      health:   engFilters.health,
      q:        engFilters.q,
      sort_by:  engFilters.sort_by,
      sort_dir: engFilters.sort_dir,
    };
    if (engFilters.user_id)   params.user_id   = engFilters.user_id;
    if (engFilters.status_id) params.status_id = engFilters.status_id;
    const { data } = await api.get('/v1/contact-analysis/engagement', { params });
    engData.value = data.data;
    Object.assign(engMeta, data.meta);
    engSummary.value = data.summary;
  } finally {
    loading.engagement = false;
  }
}

async function loadAll() {
  await Promise.all([loadOverview(), loadSource(), loadActions()]);
}

// ─── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
  document.addEventListener('click', handleOutsideClick);
  const { data } = await api.get('/v1/lookups');
  lookups.users      = data.users      ?? [];
  lookups.statuses   = data.statuses   ?? [];
  lookups.industries = data.industries ?? [];
  await Promise.all([loadAll(), loadEngagement(1)]);
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
  trendChart?.destroy();
  sourceChart?.destroy();
  actionChart?.destroy();
});
</script>

<style scoped>
/* ── Layout ────────────────────────────────────────────────────────────── */
.ca { display: flex; flex-direction: column; gap: 20px; }

.ca-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.ca-header-left h1 { font-size: 22px; font-weight: 700; color: var(--text-1); margin: 0 0 4px; }
.ca-subtitle       { font-size: 13px; color: var(--text-3); margin: 0; }

/* ── Date Picker ───────────────────────────────────────────────────────── */
.ca-date-wrap { position: relative; flex-shrink: 0; }

.ca-date-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border: 1.5px solid var(--border, #e2e8f0);
  border-radius: 8px;
  background: var(--bg-2, #fff);
  color: var(--text-1);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
  white-space: nowrap;
}
.ca-date-btn:hover { border-color: var(--primary, #7c3aed); box-shadow: 0 0 0 3px rgba(124,58,237,0.08); }
.ca-date-icon { width: 16px; height: 16px; color: var(--text-3); flex-shrink: 0; }
.ca-caret     { width: 10px; height: 6px; color: var(--text-3); margin-left: 2px; }

.ca-date-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 200;
  display: flex;
  gap: 0;
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  overflow: hidden;
  min-width: 380px;
}

.ca-presets {
  display: flex;
  flex-direction: column;
  padding: 8px;
  border-right: 1px solid var(--border, #e2e8f0);
  gap: 2px;
  flex-shrink: 0;
}
.ca-preset {
  padding: 7px 14px;
  font-size: 13px;
  border: none;
  background: transparent;
  color: var(--text-2, #475569);
  border-radius: 7px;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
  transition: background 0.12s, color 0.12s;
}
.ca-preset:hover    { background: var(--bg-1, #f8fafc); color: var(--text-1); }
.ca-preset--on      { background: var(--primary, #7c3aed); color: #fff; font-weight: 600; }
.ca-preset--on:hover { background: var(--primary, #7c3aed); color: #fff; }

.ca-custom {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 200px;
}
.ca-custom-title { font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.06em; }
.ca-custom-row   { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-2); }
.ca-custom-row span { width: 32px; flex-shrink: 0; }
.ca-custom-row input[type="date"] { flex: 1; padding: 5px 8px; border: 1px solid var(--border, #e2e8f0); border-radius: 6px; font-size: 13px; color: var(--text-1); background: var(--bg-1, #f8fafc); }
.ca-apply-btn { margin-top: 4px; }

/* ── Secondary Filters ─────────────────────────────────────────────────── */
.ca-filters { display: flex; gap: 10px; flex-wrap: wrap; }
.ca-fc {
  padding: 7px 12px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 7px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--bg-2, #fff);
  min-width: 150px;
}

/* ── KPI Cards ─────────────────────────────────────────────────────────── */
.ca-kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.ca-kpi {
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ca-kpi-label   { font-size: 12px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.05em; }
.ca-kpi-value   { font-size: 30px; font-weight: 700; color: var(--text-1); line-height: 1; }
.ca-kpi-loading { color: var(--text-3); }
.ca-kpi-delta   { font-size: 12px; font-weight: 500; }
.ca-delta--up      { color: #16a34a; }
.ca-delta--down    { color: #dc2626; }
.ca-delta--neutral { color: var(--text-3); }

/* ── Cards ─────────────────────────────────────────────────────────────── */
.ca-card {
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.ca-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: nowrap;
}
.ca-card-top--wrap { flex-wrap: wrap; }
.ca-card-title  { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0; }
.ca-card-sub    { font-size: 12px; color: var(--text-3); margin: 3px 0 0; }
.ca-card-total  { font-size: 13px; color: var(--text-3); white-space: nowrap; flex-shrink: 0; align-self: center; }

/* ── Trend tab pills ───────────────────────────────────────────────────── */
.ca-pill-row {
  display: flex;
  gap: 4px;
  background: var(--bg-1, #f1f5f9);
  border-radius: 8px;
  padding: 3px;
  flex-shrink: 0;
}
.ca-pill {
  padding: 5px 14px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
  transition: background 0.13s, color 0.13s;
}
.ca-pill:hover  { color: var(--text-1); }
.ca-pill--on    { background: #fff; color: var(--primary, #7c3aed); box-shadow: 0 1px 3px rgba(0,0,0,0.07); }

/* ── Charts ────────────────────────────────────────────────────────────── */
.ca-chart-box {
  position: relative;
  width: 100%;
}
.ca-canvas {
  position: absolute;
  inset: 0;
  width: 100% !important;
  height: 100% !important;
}
.ca-chart-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.ca-empty-chart {
  text-align: center;
  padding: 40px 0;
  font-size: 13px;
  color: var(--text-3);
}

/* ── Two-column row ────────────────────────────────────────────────────── */
.ca-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

/* ── Mini table ────────────────────────────────────────────────────────── */
.ca-tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.ca-tbl th {
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 6px 8px 6px 0;
  border-bottom: 1px solid var(--border, #e2e8f0);
}
.ca-tbl td {
  padding: 7px 8px 7px 0;
  color: var(--text-2);
  border-bottom: 1px solid var(--border, #e2e8f0);
  vertical-align: middle;
}
.ca-tbl tr:last-child td { border-bottom: none; }
.ca-tbl--full { width: 100%; }

.ca-tbl-scroll { overflow-x: auto; }

.ca-dot {
  display: inline-block;
  width: 8px; height: 8px;
  border-radius: 50%;
  margin-right: 7px;
  flex-shrink: 0;
}

/* ── Completion rate bar ───────────────────────────────────────────────── */
.ca-rate-wrap  { display: flex; align-items: center; gap: 8px; }
.ca-rate-bar   { flex: 1; height: 6px; background: var(--bg-1, #f1f5f9); border-radius: 3px; overflow: hidden; }
.ca-rate-fill  { height: 100%; background: #059669; border-radius: 3px; transition: width 0.4s; }
.ca-rate-txt   { font-size: 12px; color: var(--text-3); min-width: 36px; text-align: right; }

/* ── Health tabs ───────────────────────────────────────────────────────── */
.ca-health-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  flex-shrink: 0;
}
.ca-health-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 600;
  border-radius: 20px;
  border: 1.5px solid var(--border, #e2e8f0);
  background: transparent;
  color: var(--text-2);
  cursor: pointer;
  transition: border-color 0.13s, background 0.13s;
}
.ca-health-tab strong { font-weight: 700; color: var(--text-1); }

.ca-health-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Health color dots */
.ca-health-tab--active .ca-health-dot   { background: #16a34a; }
.ca-health-tab--at_risk .ca-health-dot  { background: #d97706; }
.ca-health-tab--dormant .ca-health-dot  { background: #dc2626; }

/* Active state */
.ca-health-tab--on                          { border-color: var(--text-3); background: var(--bg-1, #f8fafc); }
.ca-health-tab--active.ca-health-tab--on    { border-color: #16a34a; background: #f0fdf4; color: #15803d; }
.ca-health-tab--at_risk.ca-health-tab--on   { border-color: #d97706; background: #fffbeb; color: #b45309; }
.ca-health-tab--dormant.ca-health-tab--on   { border-color: #dc2626; background: #fef2f2; color: #b91c1c; }

/* ── Engagement filters ────────────────────────────────────────────────── */
.ca-eng-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.ca-eng-bar .ca-fc { min-width: 130px; }

/* ── Sortable headers ──────────────────────────────────────────────────── */
.ca-th-sort { cursor: pointer; user-select: none; }
.ca-th-sort:hover { color: var(--primary, #7c3aed); }
.ca-sort-icon { font-size: 11px; color: var(--text-3); margin-left: 3px; }

/* ── Health badges ─────────────────────────────────────────────────────── */
.ca-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 11.5px;
  font-weight: 600;
}
.ca-badge--active      { background: #dcfce7; color: #15803d; }
.ca-badge--at_risk     { background: #fef3c7; color: #b45309; }
.ca-badge--dormant     { background: #fee2e2; color: #b91c1c; }
.ca-badge--no_activity { background: #f1f5f9; color: #64748b; }

/* ── Links ─────────────────────────────────────────────────────────────── */
.ca-link { color: var(--primary, #7c3aed); text-decoration: none; font-weight: 500; }
.ca-link:hover { text-decoration: underline; }

/* ── Pagination ────────────────────────────────────────────────────────── */
.ca-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding-top: 4px;
}
.ca-page-btn {
  padding: 5px 14px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 7px;
  background: var(--bg-2, #fff);
  color: var(--text-1);
  cursor: pointer;
  transition: border-color 0.12s;
}
.ca-page-btn:hover:not(:disabled) { border-color: var(--primary, #7c3aed); color: var(--primary); }
.ca-page-btn:disabled { opacity: 0.4; cursor: default; }
.ca-page-info { font-size: 13px; color: var(--text-3); }

/* ── Empty state ───────────────────────────────────────────────────────── */
.ca-empty { text-align: center; padding: 24px; color: var(--text-3); font-size: 13px; }

/* ── Panel transition ──────────────────────────────────────────────────── */
.panel-drop-enter-active, .panel-drop-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.panel-drop-enter-from, .panel-drop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
  .ca-kpi-row { grid-template-columns: repeat(2, 1fr); }
  .ca-row-2   { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
  .ca-kpi-row  { grid-template-columns: 1fr 1fr; }
  .ca-date-panel { min-width: 0; flex-direction: column; }
  .ca-presets  { border-right: none; border-bottom: 1px solid var(--border, #e2e8f0); flex-direction: row; flex-wrap: wrap; }
}
</style>
