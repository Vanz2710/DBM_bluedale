<template>
  <div class="ca">

    <!-- ══ Header ═══════════════════════════════════════════════════════════════ -->
    <div class="ca-header">
      <div class="ca-header-left">
        <h1>Contact Analysis</h1>
        <p class="ca-subtitle">Insights into acquisition, engagement, and activity patterns across your contacts.</p>
      </div>
      <div class="ca-header-actions">
        <div class="ca-date-wrap" ref="pickerRef">
          <button class="ca-date-btn" @click.stop="pickerOpen = !pickerOpen">
            <svg class="ca-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
            <span>{{ filters.label }}</span>
            <svg class="ca-caret" viewBox="0 0 10 6" fill="currentColor"><path d="M0 0l5 6 5-6z"/></svg>
          </button>
          <transition name="panel-drop">
            <div v-if="pickerOpen" class="ca-date-panel" @click.stop>
              <div class="ca-presets">
                <button
                  v-for="p in PRESETS" :key="p.label"
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
        <button class="ca-export-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 15V3m0 12-4-4m4 4 4-4M2 17l.621 2.485A2 2 0 0 0 4.561 21h14.878a2 2 0 0 0 1.94-1.515L22 17"/>
          </svg>
          Export Data
        </button>
      </div>
    </div>

    <!-- ══ Filter Bar ════════════════════════════════════════════════════════════ -->
    <div class="ca-filter-bar">
      <div v-if="isAdmin" class="ca-filter-group">
        <span class="ca-filter-label">AGENT</span>
        <select v-model="filters.user_id" @change="loadAll" class="ca-filter-select">
          <option value="">All Users</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="ca-filter-group">
        <span class="ca-filter-label">STATUS</span>
        <select v-model="filters.status_id" @change="loadAll" class="ca-filter-select">
          <option value="">All Statuses</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
      <div class="ca-filter-group">
        <span class="ca-filter-label">INDUSTRY</span>
        <select v-model="filters.industry_id" @change="loadAll" class="ca-filter-select">
          <option value="">All Industries</option>
          <option v-for="i in lookups.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
      </div>
      <button class="ca-clear-btn" @click="clearFilters">Clear All Filters</button>
    </div>

    <!-- ══ KPI Cards ══════════════════════════════════════════════════════════════ -->
    <div class="ca-kpi-row">
      <div v-for="card in kpiCards" :key="card.key" class="ca-kpi">
        <div class="ca-kpi-top-row">
          <div class="ca-kpi-icon-wrap" :class="`ca-kpi-icon--${card.key}`">
            <svg v-if="card.key === 'contacts'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
            </svg>
            <svg v-else-if="card.key === 'tasks'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>
            </svg>
            <svg v-else-if="card.key === 'followups'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z"/><path d="m9 12 2 2 4-4"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="currentColor">
              <path d="M13 2 4.09 12.96A1 1 0 0 0 5 14.5h5.5l-1 7.5 9.41-11.46A1 1 0 0 0 18 9H12.5L13 2z"/>
            </svg>
          </div>
          <span
            v-if="!loading.overview && card.delta !== null"
            class="ca-kpi-badge"
            :class="card.delta >= 0 ? 'ca-kpi-badge--up' : 'ca-kpi-badge--down'"
          >
            <svg viewBox="0 0 12 12" fill="currentColor" width="9" height="9">
              <path v-if="card.delta >= 0" d="M6 2l4 8H2z"/>
              <path v-else d="M6 10L2 2h8z"/>
            </svg>
            {{ Math.abs(card.delta) }}%
          </span>
        </div>
        <div class="ca-kpi-label">{{ card.label }}</div>
        <div class="ca-kpi-value">
          <span v-if="loading.overview">—</span>
          <span v-else>{{ card.value }}</span>
        </div>
        <div v-if="!loading.overview && card.prevValue !== null" class="ca-kpi-prev">
          vs. {{ card.prevValue }} last period
        </div>
      </div>
    </div>

    <!-- ══ Activity Over Time ════════════════════════════════════════════════════ -->
    <div class="ca-card">
      <div class="ca-card-top">
        <div>
          <div class="ca-card-title">Activity Over Time</div>
          <div class="ca-card-sub">Daily volume of primary contact interactions</div>
        </div>
        <div class="ca-pill-row">
          <button
            v-for="t in TREND_TABS" :key="t.key"
            class="ca-pill"
            :class="{ 'ca-pill--on': activeTrend === t.key }"
            @click="switchTrend(t.key)"
          >{{ t.label }}</button>
        </div>
      </div>
      <div class="ca-chart-box" style="height:260px">
        <div v-if="loading.overview" class="ca-chart-loading">Loading…</div>
        <canvas v-else ref="trendRef" class="ca-canvas"></canvas>
      </div>
    </div>

    <!-- ══ Lead Source + Activity Types (asymmetric) ═══════════════════════════ -->
    <div class="ca-row-asym">

      <!-- Lead Source -->
      <div class="ca-card">
        <div class="ca-card-top">
          <div>
            <div class="ca-card-title">Lead Source</div>
            <div class="ca-card-sub">Contacts added in period by acquisition channel</div>
          </div>
          <span class="ca-card-total">{{ sourceData.total }} contacts</span>
        </div>
        <div v-if="loading.source" class="ca-chart-loading" style="height:120px">Loading…</div>
        <template v-else>
          <div v-if="sourceData.sources?.length">
            <div class="ca-source-bars">
              <div v-for="s in sourceData.sources" :key="s.source" class="ca-source-bar-item">
                <div class="ca-source-bar-hdr">
                  <span>{{ s.label }}</span>
                  <span class="ca-source-pct">{{ s.pct }}%</span>
                </div>
                <div class="ca-source-track">
                  <div class="ca-source-fill" :style="{ width: s.pct + '%', background: sourceColor(s.source) }"></div>
                </div>
              </div>
            </div>
            <table class="ca-tbl ca-tbl--mt">
              <thead><tr><th>Channel</th><th>Count</th><th>Share</th></tr></thead>
              <tbody>
                <tr v-for="s in sourceData.sources" :key="s.source">
                  <td class="ca-tbl-bold">{{ s.label }}</td>
                  <td>{{ s.count }}</td>
                  <td class="ca-tbl-accent">{{ s.pct }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="ca-empty-chart">No contacts added in this period.</div>
        </template>
      </div>

      <!-- Activity Types -->
      <div class="ca-card">
        <div>
          <div class="ca-card-title">Activity Types</div>
          <div class="ca-card-sub">Action distribution</div>
        </div>
        <div v-if="loading.actions" class="ca-chart-loading" style="height:180px">Loading…</div>
        <template v-else>
          <template v-if="actionData.by_action?.length">
            <div class="ca-chart-box" style="height:200px">
              <canvas ref="actionRef" class="ca-canvas"></canvas>
            </div>
            <div class="ca-legend-grid">
              <div v-for="(a, i) in actionData.by_action" :key="a.action_type" class="ca-legend-item">
                <span class="ca-legend-dot" :style="{ background: ACTION_PALETTE[i % ACTION_PALETTE.length] }"></span>
                <span>{{ a.label }} ({{ a.completion_rate }}%)</span>
              </div>
            </div>
          </template>
          <div v-else class="ca-empty-chart">No follow-ups logged in this period.</div>
        </template>
      </div>

    </div>

    <!-- ══ Engagement Health ══════════════════════════════════════════════════════ -->
    <div class="ca-card ca-card--eng">

      <div class="ca-eng-head">
        <div>
          <div class="ca-card-title">Engagement Health</div>
          <div class="ca-card-sub">Identify contacts needing attention based on inactivity days.</div>
        </div>
        <div class="ca-health-pill-group">
          <button
            v-for="h in HEALTH_TABS" :key="h.key"
            class="ca-health-pill"
            :class="engFilters.health === h.key && 'ca-health-pill--on'"
            @click="setHealth(h.key)"
          >
            {{ h.label }} <strong>({{ engSummary[h.countKey] ?? 0 }})</strong>
          </button>
        </div>
      </div>

      <div class="ca-eng-bar">
        <div class="ca-search-wrap">
          <svg class="ca-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            v-model="engFilters.q"
            class="ca-search-input"
            placeholder="Search by name…"
            @keyup.enter="loadEngagement(1)"
          />
        </div>
        <select v-if="isAdmin" v-model="engFilters.user_id" @change="loadEngagement(1)" class="ca-eng-select">
          <option value="">All Agents</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-model="engFilters.status_id" @change="loadEngagement(1)" class="ca-eng-select">
          <option value="">All Statuses</option>
          <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div v-if="loading.engagement" class="ca-chart-loading" style="height:140px">Loading…</div>
      <template v-else>
        <div class="ca-tbl-scroll">
          <table class="ca-tbl ca-tbl--full ca-tbl--eng">
            <thead>
              <tr>
                <th class="ca-th-sort" @click="sortEng('name')">
                  Contact Name <span class="ca-sort-icon">{{ sortIcon('name') }}</span>
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
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="engData.length === 0">
                <td colspan="7" class="ca-empty">No contacts found for this filter.</td>
              </tr>
              <tr v-for="c in engData" :key="c.id" class="ca-eng-row">
                <td>
                  <div class="ca-contact-cell">
                    <div class="ca-avatar" :class="`ca-avatar--${c.health}`">{{ initials(c.name) }}</div>
                    <router-link :to="`/contacts/${c.id}`" class="ca-link">{{ c.name }}</router-link>
                  </div>
                </td>
                <td>{{ c.user_name }}</td>
                <td><span class="ca-status-pill">{{ c.status_name }}</span></td>
                <td>{{ c.last_todo_date ?? '—' }}</td>
                <td>{{ c.days_inactive !== null ? c.days_inactive + 'd' : '—' }}</td>
                <td>
                  <span class="ca-health-inline" :class="`ca-health-inline--${c.health}`">
                    <span class="ca-hdot"></span>
                    {{ healthLabel(c.health) }}
                  </span>
                </td>
                <td class="ca-chevron-cell">
                  <router-link :to="`/contacts/${c.id}`" class="ca-chevron">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="ca-pagination">
          <span class="ca-page-count">Showing {{ engData.length }} of {{ engMeta.total }} contacts</span>
          <div class="ca-page-btns">
            <button class="ca-page-nav" :disabled="engMeta.current_page === 1" @click="loadEngagement(engMeta.current_page - 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <template v-for="pg in pageNumbers" :key="String(pg) + Math.random()">
              <button
                v-if="pg !== '...'"
                class="ca-page-num"
                :class="{ 'ca-page-num--on': pg === engMeta.current_page }"
                @click="loadEngagement(pg)"
              >{{ pg }}</button>
              <span v-else class="ca-page-ellipsis">…</span>
            </template>
            <button class="ca-page-nav" :disabled="engMeta.current_page === engMeta.last_page" @click="loadEngagement(engMeta.current_page + 1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
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
  { label: 'Today',        from: todayStr, to: todayStr },
  { label: 'Yesterday',    from: fmtDate(subDays(today, 1)), to: fmtDate(subDays(today, 1)) },
  { label: 'Last 7 days',  from: fmtDate(subDays(today, 6)), to: todayStr },
  { label: 'Last 30 days', from: fmtDate(subDays(today, 29)), to: todayStr },
  { label: 'Last 90 days', from: fmtDate(subDays(today, 89)), to: todayStr },
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

function clearFilters() {
  filters.user_id     = '';
  filters.status_id   = '';
  filters.industry_id = '';
  loadAll();
}

// ─── Loading state ─────────────────────────────────────────────────────────
const loading = reactive({ overview: false, source: false, actions: false, engagement: false });

// ─── Overview / KPI cards ──────────────────────────────────────────────────
const overviewData = ref(null);

function calcDelta(curr, prev) {
  if (prev === 0) return curr > 0 ? 100 : null;
  return Math.round((curr - prev) / prev * 100);
}

const kpiCards = computed(() => {
  const d = overviewData.value;
  if (!d) return [
    { key: 'contacts',  label: 'Contacts Added',       value: '—', delta: null, prevValue: null },
    { key: 'tasks',     label: 'Tasks Scheduled',       value: '—', delta: null, prevValue: null },
    { key: 'followups', label: 'Follow-Ups Completed',  value: '—', delta: null, prevValue: null },
    { key: 'engaged',   label: 'Engaged Contacts',      value: '—', delta: null, prevValue: null },
  ];
  return [
    { key: 'contacts',  label: 'Contacts Added',       value: d.contacts_added,      delta: calcDelta(d.contacts_added,      d.prev_contacts_added),      prevValue: d.prev_contacts_added },
    { key: 'tasks',     label: 'Tasks Scheduled',       value: d.tasks_created,       delta: calcDelta(d.tasks_created,       d.prev_tasks_created),       prevValue: d.prev_tasks_created },
    { key: 'followups', label: 'Follow-Ups Completed',  value: d.followups_completed, delta: calcDelta(d.followups_completed, d.prev_followups_completed),  prevValue: d.prev_followups_completed },
    { key: 'engaged',   label: 'Engaged Contacts',      value: d.engaged_contacts,    delta: calcDelta(d.engaged_contacts,    d.prev_engaged_contacts),    prevValue: d.prev_engaged_contacts },
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
        backgroundColor: `rgba(${tab.rgb},0.5)`,
        borderColor: tab.color,
        borderWidth: 1,
        borderRadius: 4,
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

// ─── Lead Source data (progress bars, no chart) ────────────────────────────
const sourceData = ref({ sources: [], total: 0 });

const SOURCE_COLORS = {
  manual: '#7c3aed', phone_call: '#0891b2', referral: '#059669',
  walk_in: '#d97706', social_media: '#db2777', email_campaign: '#dc2626',
  web_form: '#4f46e5', other: '#64748b', unknown: '#94a3b8',
};
function sourceColor(s) { return SOURCE_COLORS[s] ?? '#94a3b8'; }

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
      cutout: '62%',
      plugins: {
        legend: { display: false },
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

function initials(name) {
  return (name || '').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

const pageNumbers = computed(() => {
  const total = engMeta.last_page;
  const cur   = engMeta.current_page;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)        return [1, 2, 3, '...', total];
  if (cur >= total - 2) return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

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
  try {
    const { data } = await api.get('/v1/contact-analysis/lead-source', { params: buildParams() });
    sourceData.value = data;
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
  actionChart?.destroy();
});
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────────────────── */
.ca { display: flex; flex-direction: column; gap: 20px; }

/* ── Header ─────────────────────────────────────────────────────────────── */
.ca-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.ca-header-left h1 {
  font-size: 26px;
  font-weight: 800;
  color: var(--text-1);
  margin: 0 0 4px;
  letter-spacing: -0.02em;
}
.ca-subtitle { font-size: 13px; color: var(--text-3); margin: 0; }

.ca-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  flex-wrap: wrap;
}

/* ── Date Picker ─────────────────────────────────────────────────────────── */
.ca-date-wrap { position: relative; flex-shrink: 0; }

.ca-date-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1.5px solid #d3e4fe;
  border-radius: 999px;
  background: #d3e4fe;
  color: var(--text-1);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  white-space: nowrap;
}
.ca-date-btn:hover { border-color: var(--primary, #7c3aed); background: #eaddff; }
.ca-date-icon { width: 15px; height: 15px; color: var(--primary, #7c3aed); flex-shrink: 0; }
.ca-caret     { width: 10px; height: 6px; color: var(--text-3); margin-left: 2px; }

.ca-date-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 200;
  display: flex;
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
  border-radius: 999px;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
  transition: background 0.12s, color 0.12s;
}
.ca-preset:hover     { background: #eff4ff; color: var(--text-1); }
.ca-preset--on       { background: var(--primary, #7c3aed); color: #fff; font-weight: 600; }
.ca-preset--on:hover { background: var(--primary, #7c3aed); }

.ca-custom       { padding: 16px; display: flex; flex-direction: column; gap: 10px; min-width: 200px; }
.ca-custom-title { font-size: 10.5px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.08em; }
.ca-custom-row   { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-2); }
.ca-custom-row span { width: 32px; flex-shrink: 0; }
.ca-custom-row input[type="date"] {
  flex: 1;
  padding: 5px 8px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 6px;
  font-size: 13px;
  color: var(--text-1);
  background: #f8f9ff;
}
.ca-apply-btn { margin-top: 4px; }

/* ── Export button ───────────────────────────────────────────────────────── */
.ca-export-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  background: var(--primary, #7c3aed);
  color: #fff;
  border: none;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: transform 0.15s, box-shadow 0.15s;
}
.ca-export-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
.ca-export-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(124,58,237,0.35); }

/* ── Filter Bar ──────────────────────────────────────────────────────────── */
.ca-filter-bar {
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 14px 18px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
}
.ca-filter-group { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 160px; }
.ca-filter-label {
  font-size: 10.5px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  flex-shrink: 0;
}
.ca-filter-select {
  flex: 1;
  padding: 6px 12px;
  border: none;
  border-radius: 999px;
  font-size: 12.5px;
  color: var(--text-1);
  background: #e5eeff;
  cursor: pointer;
  outline: none;
  transition: box-shadow 0.12s;
}
.ca-filter-select:focus { box-shadow: 0 0 0 2px rgba(124,58,237,0.2); }
.ca-clear-btn {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary, #7c3aed);
  background: none;
  border: none;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 3px;
  padding: 4px 8px;
  white-space: nowrap;
  margin-left: auto;
}

/* ── KPI Cards ───────────────────────────────────────────────────────────── */
.ca-kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}
.ca-kpi {
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: transform 0.2s, box-shadow 0.2s;
}
.ca-kpi:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }

.ca-kpi-top-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 4px;
}
.ca-kpi-icon-wrap {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ca-kpi-icon-wrap svg { width: 20px; height: 20px; }

.ca-kpi-icon--contacts  { background: #eaddff; color: #7c3aed; }
.ca-kpi-icon--tasks     { background: #c9e6ff; color: #006591; }
.ca-kpi-icon--followups { background: #7ffc97; color: #005c25; }
.ca-kpi-icon--engaged   { background: #eaddff; color: #630ed4; }

.ca-kpi-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
}
.ca-kpi-badge--up   { background: #7ffc97; color: #002109; }
.ca-kpi-badge--down { background: #ffdad6; color: #93000a; }

.ca-kpi-label { font-size: 10.5px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.08em; }
.ca-kpi-value { font-size: 26px; font-weight: 800; color: var(--text-1); line-height: 1; letter-spacing: -0.02em; }
.ca-kpi-prev  { font-size: 11px; color: var(--text-3); }

/* ── Cards ───────────────────────────────────────────────────────────────── */
.ca-card {
  background: var(--bg-2, #fff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.ca-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.ca-card-title { font-size: 16px; font-weight: 800; color: var(--text-1); margin: 0; letter-spacing: -0.01em; }
.ca-card-sub   { font-size: 12px; color: var(--text-3); margin: 3px 0 0; }
.ca-card-total { font-size: 13px; color: var(--text-3); white-space: nowrap; flex-shrink: 0; align-self: center; }

/* ── Trend tab pills ─────────────────────────────────────────────────────── */
.ca-pill-row {
  display: flex;
  background: #e5eeff;
  border-radius: 999px;
  padding: 3px;
  flex-shrink: 0;
}
.ca-pill {
  padding: 6px 16px;
  font-size: 12px;
  font-weight: 700;
  border-radius: 999px;
  border: none;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
  transition: background 0.13s, color 0.13s;
}
.ca-pill:hover  { color: var(--text-1); }
.ca-pill--on    { background: #fff; color: var(--primary, #7c3aed); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

/* ── Charts ──────────────────────────────────────────────────────────────── */
.ca-chart-box    { position: relative; width: 100%; }
.ca-canvas       { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }
.ca-chart-loading { display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--text-3); }
.ca-empty-chart  { text-align: center; padding: 40px 0; font-size: 13px; color: var(--text-3); }

/* ── Asymmetric two-column ───────────────────────────────────────────────── */
.ca-row-asym { display: grid; grid-template-columns: 7fr 5fr; gap: 18px; }

/* ── Source progress bars ────────────────────────────────────────────────── */
.ca-source-bars { display: flex; flex-direction: column; gap: 12px; }
.ca-source-bar-item { display: flex; flex-direction: column; gap: 4px; }
.ca-source-bar-hdr {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: var(--text-2);
}
.ca-source-pct { color: var(--text-3); font-weight: 600; }
.ca-source-track {
  width: 100%;
  height: 8px;
  background: #e5eeff;
  border-radius: 999px;
  overflow: hidden;
}
.ca-source-fill { height: 100%; border-radius: 999px; transition: width 0.5s; }

/* ── Legend grid ─────────────────────────────────────────────────────────── */
.ca-legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; margin-top: 4px; }
.ca-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-2); }
.ca-legend-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Tables ──────────────────────────────────────────────────────────────── */
.ca-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
.ca-tbl--mt { margin-top: 10px; }
.ca-tbl th {
  text-align: left;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 8px 8px 8px 0;
  border-bottom: 1px solid #e5eeff;
}
.ca-tbl td {
  padding: 8px 8px 8px 0;
  color: var(--text-2);
  border-bottom: 1px solid #e5eeff;
  vertical-align: middle;
}
.ca-tbl tr:last-child td { border-bottom: none; }
.ca-tbl--full { width: 100%; }
.ca-tbl-scroll { overflow-x: auto; }
.ca-tbl-bold   { font-weight: 700; color: var(--text-1); }
.ca-tbl-accent { color: #006591; font-weight: 700; }

/* ── Engagement card ─────────────────────────────────────────────────────── */
.ca-card--eng { padding: 0; gap: 0; overflow: hidden; }

.ca-eng-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 22px 22px 18px;
  border-bottom: 1px solid #e5eeff;
}

/* Health pill group */
.ca-health-pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: 3px;
  background: #eff4ff;
  padding: 4px;
  border-radius: 999px;
}
.ca-health-pill {
  display: flex;
  align-items: center;
  gap: 3px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-3);
  border: none;
  background: transparent;
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.13s, color 0.13s;
  white-space: nowrap;
}
.ca-health-pill strong { font-weight: 700; color: var(--text-2); }
.ca-health-pill--on { background: #fff; color: var(--primary, #7c3aed); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.ca-health-pill--on strong { color: var(--primary, #7c3aed); }

/* Engagement search + filter bar */
.ca-eng-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  border-bottom: 1px solid #e5eeff;
}
.ca-search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 320px; }
.ca-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 15px; height: 15px;
  color: var(--text-3);
  pointer-events: none;
}
.ca-search-input {
  width: 100%;
  padding: 8px 14px 8px 36px;
  border: 1px solid #e5eeff;
  border-radius: 999px;
  font-size: 13px;
  color: var(--text-1);
  background: #e5eeff;
  outline: none;
  transition: border-color 0.12s, background 0.12s;
  box-sizing: border-box;
}
.ca-search-input:focus { background: #fff; border-color: var(--primary, #7c3aed); }
.ca-search-input::placeholder { color: var(--text-3); }

.ca-eng-select {
  padding: 7px 14px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-1);
  background: var(--bg-2, #fff);
  cursor: pointer;
  outline: none;
  transition: border-color 0.12s;
}
.ca-eng-select:focus { border-color: var(--primary, #7c3aed); }

/* Engagement table */
.ca-tbl--eng { min-width: 680px; }
.ca-tbl--eng thead { background: #f8f9ff; }
.ca-tbl--eng th { padding: 12px 14px; border-bottom: 1px solid #e5eeff; }
.ca-tbl--eng td { padding: 13px 14px; border-bottom: 1px solid #e5eeff; }
.ca-tbl--eng tbody tr { transition: background 0.12s; }
.ca-tbl--eng tbody tr:hover { background: #f8f9ff; }
.ca-tbl--eng tbody tr:last-child td { border-bottom: none; }

.ca-contact-cell { display: flex; align-items: center; gap: 12px; }
.ca-avatar {
  width: 34px; height: 34px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700;
  flex-shrink: 0;
}
.ca-avatar--active      { background: #dcfce7; color: #15803d; }
.ca-avatar--at_risk     { background: #fef3c7; color: #b45309; }
.ca-avatar--dormant     { background: #fee2e2; color: #b91c1c; }
.ca-avatar--no_activity { background: #e5eeff; color: #4a4455; }

.ca-status-pill {
  display: inline-block;
  padding: 3px 10px;
  background: #dce9ff;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  color: #4a4455;
}

.ca-health-inline { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; }
.ca-hdot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

.ca-health-inline--active      { color: #16a34a; }
.ca-health-inline--active .ca-hdot     { background: #16a34a; }
.ca-health-inline--at_risk     { color: #d97706; }
.ca-health-inline--at_risk .ca-hdot    { background: #d97706; }
.ca-health-inline--dormant     { color: #dc2626; }
.ca-health-inline--dormant .ca-hdot    { background: #dc2626; }
.ca-health-inline--no_activity { color: #64748b; }
.ca-health-inline--no_activity .ca-hdot { background: #64748b; }

.ca-chevron-cell { width: 40px; text-align: right; }
.ca-chevron {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px;
  border-radius: 50%;
  color: var(--primary, #7c3aed);
  opacity: 0;
  transition: opacity 0.12s;
}
.ca-chevron svg { width: 16px; height: 16px; }
.ca-eng-row:hover .ca-chevron { opacity: 1; }

/* Sortable headers */
.ca-th-sort { cursor: pointer; user-select: none; }
.ca-th-sort:hover { color: var(--primary, #7c3aed); }
.ca-sort-icon { font-size: 10px; color: var(--text-3); margin-left: 2px; }

/* Links */
.ca-link { color: var(--primary, #7c3aed); text-decoration: none; font-weight: 600; }
.ca-link:hover { text-decoration: underline; }

/* Empty */
.ca-empty { text-align: center; padding: 32px; color: var(--text-3); font-size: 13px; }

/* ── Pagination ───────────────────────────────────────────────────────────── */
.ca-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 22px;
  background: #f8f9ff;
  border-top: 1px solid #e5eeff;
}
.ca-page-count { font-size: 12px; color: var(--text-3); }
.ca-page-btns  { display: flex; align-items: center; gap: 3px; }
.ca-page-nav {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: none; background: transparent;
  border-radius: 50%;
  color: var(--text-2);
  cursor: pointer;
  transition: background 0.12s;
}
.ca-page-nav svg { width: 14px; height: 14px; }
.ca-page-nav:hover:not(:disabled) { background: #e5eeff; }
.ca-page-nav:disabled { opacity: 0.3; cursor: default; }
.ca-page-num {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: none; background: transparent;
  border-radius: 50%;
  font-size: 12.5px; font-weight: 600;
  color: var(--text-2);
  cursor: pointer;
  transition: background 0.12s;
}
.ca-page-num:hover { background: #e5eeff; }
.ca-page-num--on  { background: var(--primary, #7c3aed); color: #fff; font-weight: 700; }
.ca-page-ellipsis { width: 32px; text-align: center; color: var(--text-3); font-size: 13px; line-height: 32px; }

/* ── Panel transition ─────────────────────────────────────────────────────── */
.panel-drop-enter-active, .panel-drop-leave-active { transition: opacity 0.15s, transform 0.15s; }
.panel-drop-enter-from, .panel-drop-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .ca-row-asym { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 900px) {
  .ca-kpi-row  { grid-template-columns: repeat(2, 1fr); }
  .ca-row-asym { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .ca-kpi-row       { grid-template-columns: 1fr 1fr; }
  .ca-date-panel    { min-width: 0; flex-direction: column; }
  .ca-presets       { border-right: none; border-bottom: 1px solid var(--border, #e2e8f0); flex-direction: row; flex-wrap: wrap; }
  .ca-header-actions { width: 100%; }
  .ca-health-pill-group { border-radius: 12px; }
}
</style>
