<template>
  <div class="page">

    <!-- ══ Header ══════════════════════════════════════════════════════════════ -->
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">{{ pageSubtitle }}</p>
      </div>
    </div>

    <!-- ══ Tab bar ═════════════════════════════════════════════════════════════ -->
    <div class="view-tabs">
      <button :class="['tab-btn', { 'tab-active': tab === 'overview' }]" @click="switchTab('overview')">
        <span class="tab-icon" v-html="CI.grid"></span> Overview
      </button>
      <button :class="['tab-btn', { 'tab-active': tab === 'breakdown' }]" @click="switchTab('breakdown')">
        <span class="tab-icon" v-html="CI.pieChart"></span> Breakdown
      </button>
      <button :class="['tab-btn', { 'tab-active': tab === 'trends' }]" @click="switchTab('trends')">
        <span class="tab-icon" v-html="CI.trending"></span> Trends
      </button>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>

      <!-- ══════════════════════════════════════════════════════════════════════
           OVERVIEW TAB
      ═══════════════════════════════════════════════════════════════════════════ -->
      <template v-if="tab === 'overview'">

        <!-- KPI cards -->
        <div class="kpi-row">
          <div class="kpi-card">
            <div class="kpi-icon kpi-icon--purple">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/>
                <line x1="22" y1="11" x2="16" y2="11"/>
              </svg>
            </div>
            <div class="kpi-body">
              <div class="kpi-value">{{ analytics.total_contacts.toLocaleString() }}</div>
              <div class="kpi-label">Total Contacts</div>
              <div class="kpi-sub">{{ isAdmin ? `${analytics.unassigned} unassigned` : 'your contacts' }}</div>
            </div>
          </div>

          <div class="kpi-card">
            <div class="kpi-icon kpi-icon--blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18"/>
              </svg>
            </div>
            <div class="kpi-body">
              <div class="kpi-value">{{ analytics.this_month.toLocaleString() }}</div>
              <div class="kpi-label">Added This Month</div>
              <div class="kpi-sub" :class="monthDeltaClass">
                <template v-if="monthDelta !== null">
                  {{ monthDelta >= 0 ? '+' : '' }}{{ monthDelta }}% vs last month
                </template>
                <template v-else>{{ analytics.last_month }} last month</template>
              </div>
            </div>
          </div>

          <div class="kpi-card">
            <div class="kpi-icon kpi-icon--green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>
              </svg>
            </div>
            <div class="kpi-body">
              <div class="kpi-value">{{ analytics.tasks_due_today.toLocaleString() }}</div>
              <div class="kpi-label">Tasks Due Today</div>
              <div class="kpi-sub">Across all agents</div>
            </div>
          </div>

          <div class="kpi-card">
            <div class="kpi-icon kpi-icon--orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2 4.09 12.96A1 1 0 0 0 5 14.5h5.5l-1 7.5 9.41-11.46A1 1 0 0 0 18 9H12.5L13 2z"/>
              </svg>
            </div>
            <div class="kpi-body">
              <div class="kpi-value">{{ analytics.active_count.toLocaleString() }}</div>
              <div class="kpi-label">Active Contacts</div>
              <div class="kpi-sub">{{ pct(analytics.active_count, analytics.total_contacts) }} of total</div>
            </div>
          </div>
        </div>

        <!-- Top performers -->
        <div class="top-row">
          <div class="top-card" v-if="analytics.top_agent">
            <div class="top-card-icon top-card-icon--purple">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
            <div class="top-card-body">
              <div class="top-card-label">Top Agent</div>
              <div class="top-card-name">{{ analytics.top_agent.label }}</div>
              <div class="top-card-count">{{ Number(analytics.top_agent.count).toLocaleString() }} contacts</div>
            </div>
          </div>
          <div class="top-card" v-if="analytics.top_industry">
            <div class="top-card-icon top-card-icon--blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
              </svg>
            </div>
            <div class="top-card-body">
              <div class="top-card-label">Top Industry</div>
              <div class="top-card-name">{{ analytics.top_industry.label }}</div>
              <div class="top-card-count">{{ Number(analytics.top_industry.count).toLocaleString() }} contacts</div>
            </div>
          </div>
          <div class="top-card" v-if="analytics.top_product">
            <div class="top-card-icon top-card-icon--green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
            </div>
            <div class="top-card-body">
              <div class="top-card-label">Top Category</div>
              <div class="top-card-name">{{ analytics.top_product.label }}</div>
              <div class="top-card-count">{{ Number(analytics.top_product.count).toLocaleString() }} contacts</div>
            </div>
          </div>
        </div>

        <!-- Status composition -->
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Contact Status Composition</div>
              <div class="card-subtitle">All-time distribution across status categories</div>
            </div>
            <span class="count-badge">{{ analytics.total_contacts.toLocaleString() }} total</span>
          </div>
          <div class="dist-list">
            <div v-for="row in topStatuses" :key="row.label" class="dist-row">
              <span class="dist-label">{{ row.label }}</span>
              <div class="dist-bar-wrap">
                <div class="dist-bar-fill dist-bar-fill--status"
                     :style="{ width: pctNum(row.count, analytics.total_contacts) + '%' }"></div>
              </div>
              <span class="dist-pct">{{ pct(row.count, analytics.total_contacts) }}</span>
              <span class="dist-count">{{ row.count.toLocaleString() }}</span>
            </div>
          </div>
        </div>

        <!-- Agent leaderboard -->
        <div v-if="isAdmin" class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Agent Contact Ranking</div>
              <div class="card-subtitle">Total contacts owned per sales agent</div>
            </div>
            <span class="count-badge">{{ analytics.by_user.length }} agents</span>
          </div>
          <div class="table-scroll">
            <table class="rep-table">
              <thead>
                <tr>
                  <th class="th-rank">Rank</th>
                  <th>Agent</th>
                  <th>Contacts</th>
                  <th>Share</th>
                  <th class="th-bar">Distribution</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in agentRows" :key="row.label">
                  <td class="td-rank">
                    <span :class="['rank-badge', i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : '']">
                      {{ i + 1 }}
                    </span>
                  </td>
                  <td class="td-agent">
                    <span class="agent-avatar">{{ initials(row.label) }}</span>
                    <span class="agent-name">{{ row.label }}</span>
                  </td>
                  <td class="td-num">{{ row.count.toLocaleString() }}</td>
                  <td class="td-num">{{ pct(row.count, agentTotal) }}</td>
                  <td class="td-bar">
                    <div class="inline-bar-wrap">
                      <div class="inline-bar-fill" :style="{ width: pctNum(row.count, agentMax) + '%' }"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </template>

      <!-- ══════════════════════════════════════════════════════════════════════
           BREAKDOWN TAB
      ═══════════════════════════════════════════════════════════════════════════ -->
      <template v-if="tab === 'breakdown'">

        <!-- Dimension sub-tabs -->
        <div class="sub-tabs">
          <button
            v-for="d in visibleDims" :key="d.key"
            :class="['sub-tab', { 'sub-tab-active': activeDim === d.key }]"
            @click="activeDim = d.key"
          >{{ d.label }}</button>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">{{ currentDim.label }}</div>
              <div class="card-subtitle">Contact distribution by {{ currentDim.labelLower }}</div>
            </div>
            <span class="count-badge">{{ currentRows.length }} {{ currentRows.length === 1 ? 'segment' : 'segments' }}</span>
          </div>

          <!-- Bar distribution -->
          <div class="dist-list">
            <div v-for="row in currentRows" :key="row.label" class="dist-row">
              <span class="dist-label">{{ row.label ?? '—' }}</span>
              <div class="dist-bar-wrap">
                <div class="dist-bar-fill" :style="{ width: pctNum(row.count, dimTotal) + '%' }"></div>
              </div>
              <span class="dist-pct">{{ pct(row.count, dimTotal) }}</span>
              <span class="dist-count">{{ row.count.toLocaleString() }}</span>
            </div>
          </div>

          <!-- Table -->
          <div class="table-scroll">
            <table class="rep-table rep-table--bordered">
              <thead>
                <tr>
                  <th style="width:52px">#</th>
                  <th>Name</th>
                  <th style="width:100px">Count</th>
                  <th style="width:90px">Share</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in currentRows" :key="row.label">
                  <td class="td-num">{{ i + 1 }}</td>
                  <td>{{ row.label ?? '—' }}</td>
                  <td class="td-num">{{ row.count.toLocaleString() }}</td>
                  <td class="td-num">{{ pct(row.count, dimTotal) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" class="tfoot-label">Total</td>
                  <td class="td-num tfoot-val">{{ dimTotal.toLocaleString() }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </template>

      <!-- ══════════════════════════════════════════════════════════════════════
           TRENDS TAB
      ═══════════════════════════════════════════════════════════════════════════ -->
      <template v-if="tab === 'trends'">

        <!-- Summary row -->
        <div class="trend-summary-row">
          <div class="trend-stat">
            <div class="trend-stat-val">{{ totalContactsAdded.toLocaleString() }}</div>
            <div class="trend-stat-label">Contacts added (last 12 months)</div>
          </div>
          <div class="trend-stat-div"></div>
          <div class="trend-stat">
            <div class="trend-stat-val">{{ totalTasksScheduled.toLocaleString() }}</div>
            <div class="trend-stat-label">Tasks scheduled (last 12 months)</div>
          </div>
          <div class="trend-stat-div"></div>
          <div class="trend-stat">
            <div class="trend-stat-val">{{ avgContactsPerMonth }}</div>
            <div class="trend-stat-label">Avg contacts / month</div>
          </div>
          <div class="trend-stat-div"></div>
          <div class="trend-stat">
            <div class="trend-stat-val">{{ avgTasksPerMonth }}</div>
            <div class="trend-stat-label">Avg tasks / month</div>
          </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Monthly Contacts Added</div>
                <div class="card-subtitle">New contacts registered each month</div>
              </div>
              <span class="count-badge count-badge--purple">Last 12 months</span>
            </div>
            <div class="chart-box">
              <canvas ref="contactsChartRef" class="chart-canvas"></canvas>
            </div>
          </div>

          <div class="card">
            <div class="card-head">
              <div>
                <div class="card-title">Monthly Tasks Scheduled</div>
                <div class="card-subtitle">Tasks created or due each month</div>
              </div>
              <span class="count-badge count-badge--teal">Last 12 months</span>
            </div>
            <div class="chart-box">
              <canvas ref="tasksChartRef" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>

        <!-- Monthly data table -->
        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Month-by-Month Breakdown</div>
              <div class="card-subtitle">Contacts and tasks side by side</div>
            </div>
          </div>
          <div class="table-scroll">
            <table class="rep-table rep-table--bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Month</th>
                  <th>Contacts Added</th>
                  <th>Tasks Scheduled</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in analytics.by_month" :key="row.label">
                  <td class="td-num">{{ i + 1 }}</td>
                  <td>{{ row.label }}</td>
                  <td class="td-num">{{ row.count.toLocaleString() }}</td>
                  <td class="td-num">{{ (analytics.by_tasks[i]?.count ?? 0).toLocaleString() }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" class="tfoot-label">Total</td>
                  <td class="td-num tfoot-val">{{ totalContactsAdded.toLocaleString() }}</td>
                  <td class="td-num tfoot-val">{{ totalTasksScheduled.toLocaleString() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </template>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import {
  Chart,
  BarController, BarElement,
  CategoryScale, LinearScale,
  Tooltip,
} from 'chart.js';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

// ── Icons (same SVG style as the sidebar) ──
const _si = (p, sz = 14) => `<svg width="${sz}" height="${sz}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const ICO = {
  grid:     (sz) => _si('<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>', sz),
  pieChart: (sz) => _si('<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>', sz),
  trending: (sz) => _si('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>', sz),
};
const CI = Object.fromEntries(Object.entries(ICO).map(([k, fn]) => [k, fn(14)]));

// ── Constants ─────────────────────────────────────────────────────────────────
const DIMS = [
  { key: 'by_status',   label: 'By Status',   labelLower: 'status' },
  { key: 'by_industry', label: 'By Industry',  labelLower: 'industry' },
  { key: 'by_category', label: 'By Category',  labelLower: 'category' },
  { key: 'by_type',     label: 'By Type',      labelLower: 'type' },
  { key: 'by_user',     label: 'By Agent',     labelLower: 'sales agent' },
];

// ── State ─────────────────────────────────────────────────────────────────────
const loading   = ref(true);
const analytics = ref(null);
const tab       = ref('overview');
const activeDim = ref('by_status');

// ── Role ──────────────────────────────────────────────────────────────────────
const isAdmin = computed(() => analytics.value?.is_admin ?? false);

const pageSubtitle = computed(() =>
  isAdmin.value
    ? 'CRM composition, agent rankings, and 12-month activity trends'
    : 'Your portfolio composition and 12-month activity trends'
);

const visibleDims = computed(() =>
  isAdmin.value ? DIMS : DIMS.filter(d => d.key !== 'by_user')
);

watch(isAdmin, (admin) => {
  if (!admin && activeDim.value === 'by_user') activeDim.value = 'by_status';
});

// ── Computed — Overview ───────────────────────────────────────────────────────
const monthDelta = computed(() => {
  const a = analytics.value;
  if (!a || a.last_month === 0) return null;
  return Math.round((a.this_month - a.last_month) / a.last_month * 100);
});
const monthDeltaClass = computed(() => {
  if (monthDelta.value === null) return 'kpi-sub';
  return monthDelta.value >= 0 ? 'kpi-sub kpi-sub--up' : 'kpi-sub kpi-sub--down';
});

const topStatuses = computed(() =>
  (analytics.value?.by_status ?? []).slice(0, 7).map(r => ({
    label: r.label,
    count: Number(r.count),
  }))
);

const agentRows = computed(() =>
  (analytics.value?.by_user ?? []).map(r => ({
    label: r.label,
    count: Number(r.count),
  }))
);
const agentTotal = computed(() => agentRows.value.reduce((s, r) => s + r.count, 0));
const agentMax   = computed(() => Math.max(...agentRows.value.map(r => r.count), 1));

// ── Computed — Breakdown ──────────────────────────────────────────────────────
const currentDim = computed(() => DIMS.find(d => d.key === activeDim.value) ?? DIMS[0]);
const currentRows = computed(() => {
  const raw = analytics.value?.[activeDim.value] ?? [];
  return raw.map(r => ({ label: r.label ?? r.name, count: Number(r.count ?? 0) }));
});
const dimTotal = computed(() => currentRows.value.reduce((s, r) => s + r.count, 0));

// ── Computed — Trends ─────────────────────────────────────────────────────────
const totalContactsAdded = computed(() =>
  (analytics.value?.by_month ?? []).reduce((s, r) => s + Number(r.count), 0)
);
const totalTasksScheduled = computed(() =>
  (analytics.value?.by_tasks ?? []).reduce((s, r) => s + Number(r.count), 0)
);
const avgContactsPerMonth = computed(() => {
  const months = analytics.value?.by_month?.length || 1;
  return Math.round(totalContactsAdded.value / months);
});
const avgTasksPerMonth = computed(() => {
  const months = analytics.value?.by_tasks?.length || 1;
  return Math.round(totalTasksScheduled.value / months);
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function pctNum(count, total) {
  return total > 0 ? Math.round(count / total * 100) : 0;
}
function pct(count, total) {
  return total > 0 ? (count / total * 100).toFixed(1) + '%' : '—';
}
function initials(name) {
  return (name || '').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// ── Charts ────────────────────────────────────────────────────────────────────
const contactsChartRef = ref(null);
const tasksChartRef    = ref(null);
let   contactsChart    = null;
let   tasksChart       = null;

const TOOLTIP_DEFAULTS = {
  backgroundColor: '#0f2456',
  padding: 10,
  titleFont: { size: 11, weight: '600' },
  bodyFont:  { size: 12 },
  displayColors: false,
  cornerRadius: 8,
};

const AXIS_DEFAULTS = {
  x: {
    border: { display: false },
    grid:   { display: false },
    ticks:  { font: { size: 9 }, color: '#94a3b8', maxRotation: 45 },
  },
  y: {
    beginAtZero: true,
    border: { display: false },
    grid: { color: 'rgba(148,163,184,0.12)', drawTicks: false },
    ticks: { font: { size: 10 }, color: '#94a3b8', padding: 8, stepSize: 1 },
  },
};

function buildCharts() {
  const data = analytics.value;
  if (!data) return;

  contactsChart?.destroy();
  if (contactsChartRef.value) {
    contactsChart = new Chart(contactsChartRef.value.getContext('2d'), {
      type: 'bar',
      data: {
        labels: data.by_month.map(r => r.label),
        datasets: [{
          data: data.by_month.map(r => Number(r.count)),
          backgroundColor: 'rgba(29,78,216,0.55)',
          borderColor: '#1d4ed8',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: TOOLTIP_DEFAULTS },
        scales: AXIS_DEFAULTS,
      },
    });
  }

  tasksChart?.destroy();
  if (tasksChartRef.value) {
    tasksChart = new Chart(tasksChartRef.value.getContext('2d'), {
      type: 'bar',
      data: {
        labels: data.by_tasks.map(r => r.label),
        datasets: [{
          data: data.by_tasks.map(r => Number(r.count)),
          backgroundColor: 'rgba(8,145,178,0.55)',
          borderColor: '#0891b2',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: TOOLTIP_DEFAULTS },
        scales: AXIS_DEFAULTS,
      },
    });
  }
}

// ── Tab switching ─────────────────────────────────────────────────────────────
async function switchTab(newTab) {
  tab.value = newTab;
  if (newTab === 'trends') {
    await nextTick();
    buildCharts();
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
function showToast(message, type = 'error') {
  window.dispatchEvent(new CustomEvent('crm-toast', { detail: { message, type } }));
}

onMounted(async () => {
  try {
    const res = await api.get('/v1/analytics');
    analytics.value = res.data;
  } catch (e) {
    showToast(e.response?.data?.message ?? 'Failed to load report data.', 'error');
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  contactsChart?.destroy();
  tasksChart?.destroy();
});
</script>

<style scoped>
/* ── Root ────────────────────────────────────────────────────────────────── */
.page { padding: 28px 28px 48px; max-width: 1500px; margin: 0 auto; }

/* ── Page head ───────────────────────────────────────────────────────────── */
.page-head {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; margin-bottom: 18px; flex-wrap: wrap;
}
.page-head-left { display: flex; flex-direction: column; gap: 4px; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Tab bar — underline style (matches List of Contacts / Task Manager) ──── */
.view-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 20px;
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--app-bg);
  padding-top: 4px;
  flex-wrap: wrap;
}
.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-2);
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  white-space: nowrap;
}
.tab-btn:hover:not(.tab-active) { color: var(--text-1); background: var(--surface-2); }
.tab-icon { display: inline-flex; align-items: center; vertical-align: middle; opacity: 0.8; }
.tab-active {
  color: var(--primary) !important;
  border-bottom-color: var(--primary);
}

/* ── KPI cards ───────────────────────────────────────────────────────────── */
.kpi-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px;
}
.kpi-card {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 20px;
  display: flex; align-items: flex-start; gap: 14px; box-shadow: var(--shadow-sm);
}
.kpi-icon {
  width: 44px; height: 44px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.kpi-icon svg { width: 20px; height: 20px; }
.kpi-icon--purple { background: #dbeafe; color: #1d4ed8; }
.kpi-icon--blue   { background: #dbeafe; color: #2563eb; }
.kpi-icon--green  { background: #dcfce7; color: #16a34a; }
.kpi-icon--orange { background: #ffedd5; color: #ea580c; }
.kpi-body { display: flex; flex-direction: column; gap: 3px; }
.kpi-value { font-size: 28px; font-weight: 800; color: var(--text-1); line-height: 1; letter-spacing: -0.03em; }
.kpi-label { font-size: 10.5px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
.kpi-sub { font-size: 11.5px; color: var(--text-3); }
.kpi-sub--up   { color: #16a34a; font-weight: 600; }
.kpi-sub--down { color: #dc2626; font-weight: 600; }

/* ── Top performers ──────────────────────────────────────────────────────── */
.top-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
.top-card {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 20px 22px;
  display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm);
}
.top-card-icon {
  width: 44px; height: 44px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.top-card-icon svg { width: 20px; height: 20px; }
.top-card-icon--purple { background: #dbeafe; color: #1d4ed8; }
.top-card-icon--blue   { background: #dbeafe; color: #2563eb; }
.top-card-icon--green  { background: #dcfce7; color: #16a34a; }
.top-card-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-3); margin-bottom: 3px; }
.top-card-name  { font-size: 16px; font-weight: 800; color: var(--text-1); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-card-count { font-size: 12px; color: var(--text-3); font-weight: 600; }

/* ── Card shell ──────────────────────────────────────────────────────────── */
.card {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;
  margin-bottom: 20px;
}
.card-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 22px; border-bottom: 1px solid var(--border-soft); gap: 12px; flex-wrap: wrap;
}
.card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2); }
.card-subtitle { font-size: 12px; color: var(--text-3); margin-top: 2px; }
.count-badge {
  background: var(--primary-soft); color: var(--primary-text);
  font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; white-space: nowrap;
}
.count-badge--purple { background: #dbeafe; color: #1d4ed8; }
.count-badge--teal   { background: #cffafe; color: #0891b2; }

/* ── Distribution bars ───────────────────────────────────────────────────── */
.dist-list { padding: 6px 22px 2px; }
.dist-row {
  display: grid; grid-template-columns: 190px 1fr 60px 72px;
  align-items: center; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--border-soft);
}
.dist-row:last-child { border-bottom: none; }
.dist-label { font-size: 13px; color: var(--text-1); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dist-bar-wrap { height: 10px; background: var(--border-soft); border-radius: 4px; overflow: hidden; }
.dist-bar-fill { height: 100%; background: #1d4ed8; border-radius: 4px; min-width: 2px; transition: width 0.4s; }
.dist-bar-fill--status { background: linear-gradient(90deg, #1d4ed8, #60a5fa); }
.dist-pct   { font-size: 11.5px; color: var(--text-3); text-align: right; }
.dist-count { font-size: 13px; font-weight: 700; color: var(--text-1); text-align: right; }

/* ── Table shared ────────────────────────────────────────────────────────── */
.table-scroll { overflow-x: auto; }
.rep-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rep-table thead th {
  background: var(--surface-2); color: var(--text-3); font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 16px;
  border-bottom: 1px solid var(--border); text-align: left; white-space: nowrap;
}
.rep-table tbody td { padding: 11px 16px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
.rep-table tbody tr:last-child td { border-bottom: none; }
.rep-table tbody tr:hover { background: var(--surface-2); }
.rep-table tfoot td { padding: 10px 16px; font-weight: 700; background: var(--surface-2); border-top: 1px solid var(--border); }
.rep-table--bordered { }
.td-num { text-align: center; }
.tfoot-label { text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.tfoot-val { color: #1d4ed8; font-size: 14px; }

/* ── Agent leaderboard ───────────────────────────────────────────────────── */
.th-rank { width: 64px; text-align: center; }
.th-bar  { width: 200px; }
.td-rank { text-align: center; }
.td-agent { display: flex; align-items: center; gap: 10px; }
.td-num   { text-align: center; }
.td-bar   { min-width: 150px; }

.rank-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 50%;
  font-size: 11.5px; font-weight: 700; background: var(--border-soft); color: var(--text-2);
}
.rank-1 { background: #fef9c3; color: #854d0e; }
.rank-2 { background: #f1f5f9; color: #475569; }
.rank-3 { background: #ffedd5; color: #9a3412; }

.agent-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary-text);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 10.5px; font-weight: 700; flex-shrink: 0;
}
.agent-name { font-weight: 600; color: var(--text-1); }

.inline-bar-wrap { height: 8px; background: var(--border-soft); border-radius: 4px; overflow: hidden; }
.inline-bar-fill { height: 100%; background: #1d4ed8; border-radius: 4px; min-width: 2px; transition: width 0.4s; }

/* ── Sub-tabs (Breakdown) ────────────────────────────────────────────────── */
.sub-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
.sub-tab {
  height: 34px; padding: 0 16px; border: 1.5px solid var(--border); border-radius: 8px;
  background: var(--surface); color: var(--text-2); font-size: 12.5px; font-weight: 600;
  cursor: pointer; transition: all 0.15s;
}
.sub-tab:hover:not(.sub-tab-active) { border-color: #1d4ed8; color: #1d4ed8; }
.sub-tab-active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }

/* ── Trends summary row ──────────────────────────────────────────────────── */
.trend-summary-row {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 20px 28px;
  display: flex; align-items: center; gap: 0;
  box-shadow: var(--shadow-sm); margin-bottom: 20px; flex-wrap: wrap;
}
.trend-stat { flex: 1; min-width: 140px; padding: 8px 16px; }
.trend-stat-val { font-size: 26px; font-weight: 800; color: var(--text-1); letter-spacing: -0.03em; }
.trend-stat-label { font-size: 11.5px; color: var(--text-3); margin-top: 3px; font-weight: 500; }
.trend-stat-div { width: 1px; height: 48px; background: var(--border-soft); align-self: center; flex-shrink: 0; }

/* ── Charts ──────────────────────────────────────────────────────────────── */
.charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.chart-box { position: relative; height: 240px; padding: 16px 22px 22px; }
.chart-canvas { position: absolute; inset: 16px 22px 22px; width: calc(100% - 44px) !important; height: calc(100% - 38px) !important; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .kpi-row { grid-template-columns: repeat(2, 1fr); }
  .charts-grid { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
  .top-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .page { padding: 16px 12px 40px; }
  .kpi-row { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .top-row { grid-template-columns: 1fr; }
  .dist-row { grid-template-columns: 120px 1fr 48px 58px; gap: 8px; }
  .trend-summary-row { gap: 0; }
  .trend-stat-div { display: none; }
  .trend-stat { min-width: calc(50% - 32px); }
}
@media (max-width: 480px) {
  .kpi-row { grid-template-columns: 1fr; }
  .trend-stat { min-width: 100%; }
}
</style>
