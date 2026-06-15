<template>
  <div class="pi">

    <!-- ══ Header ══════════════════════════════════════════════════════════════════ -->
    <div class="pi-header">
      <div class="pi-header-left">
        <h1>Predictive Insights</h1>
        <p class="pi-subtitle">Forward-looking analysis of pipeline health, contact risks, and agent performance.</p>
      </div>
      <div class="pi-header-actions">
        <div class="pi-date-wrap" ref="pickerRef">
          <button class="pi-date-btn" @click.stop="pickerOpen = !pickerOpen">
            <svg class="pi-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
            <span>{{ filters.label }}</span>
            <svg class="pi-caret" viewBox="0 0 10 6" fill="currentColor"><path d="M0 0l5 6 5-6z"/></svg>
          </button>
          <transition name="panel-drop">
            <div v-if="pickerOpen" class="pi-date-panel" @click.stop>
              <div class="pi-presets-section">
                <div class="pi-custom-title">Presets</div>
                <div class="pi-presets">
                  <button
                    v-for="p in PRESETS" :key="p.label"
                    class="pi-preset"
                    :class="{ 'pi-preset--on': filters.label === p.label }"
                    @click="applyPreset(p)"
                  >{{ p.label }}</button>
                </div>
              </div>
              <div class="pi-custom">
                <div class="pi-custom-title">Custom range</div>
                <div class="pi-custom-inline">
                  <input type="date" v-model="customFrom" :max="customTo" />
                  <span class="pi-custom-sep">to</span>
                  <input type="date" v-model="customTo" :min="customFrom" :max="todayStr" />
                  <button class="pi-apply-btn" @click="applyCustom">Apply</button>
                </div>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- ══ Filter Bar ══════════════════════════════════════════════════════════════ -->
    <div v-if="isAdmin" class="pi-filter-bar">
      <div class="pi-filter-group">
        <span class="pi-filter-label">AGENT</span>
        <select v-model="filters.user_id" @change="loadAll" class="pi-filter-select">
          <option value="">All Agents</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <!-- ══ KPI Summary Row ══════════════════════════════════════════════════════════ -->
    <div class="pi-kpi-row">

      <!-- Neglected Contacts -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--danger">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">{{ summary.neglected ?? '—' }}</div>
          <div class="pi-kpi-label">Neglected Contacts</div>
          <div class="pi-kpi-sub">Potential/Existing, 60+ days untouched</div>
        </div>
      </div>

      <!-- Expected Pipeline -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--success">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23"/>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">{{ summary.pipeline_value != null ? formatCurrency(summary.pipeline_value) : '—' }}</div>
          <div class="pi-kpi-label">Expected Pipeline</div>
          <div class="pi-kpi-sub">Open deals × probability</div>
        </div>
      </div>

      <!-- Coverage Imbalance -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--warning">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <circle cx="12" cy="12" r="6"/>
            <circle cx="12" cy="12" r="2"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">{{ summary.overloaded_agents ?? '—' }}</div>
          <div class="pi-kpi-label">Overloaded Agents</div>
          <div class="pi-kpi-sub">Carrying 1.5× average portfolio</div>
        </div>
      </div>

      <!-- Unworked Opportunities -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">{{ summary.unworked_opps ?? '—' }}</div>
          <div class="pi-kpi-label">Unworked Opportunities</div>
          <div class="pi-kpi-sub">Active contacts, 30+ days untouched</div>
        </div>
      </div>

    </div>

    <!-- ══ Revenue Forecast + Neglected Contacts ══════════════════════════════════ -->
    <div class="pi-row-asym">

      <!-- Revenue Pipeline Forecast -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
            <span class="pi-card-title">Revenue Pipeline Forecast</span>
          </div>
          <span class="pi-card-meta">Open deals weighted by probability</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.forecast" class="pi-skeleton-block"></div>
          <div v-else-if="!forecast.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
            <p>No open deals to forecast</p>
          </div>
          <div v-else class="pi-chart-wrap">
            <canvas ref="forecastCanvasRef"></canvas>
          </div>
        </div>
      </div>

      <!-- Neglected Contacts -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span class="pi-card-title">Neglected Contacts</span>
          </div>
          <span class="pi-card-meta">Potential/Existing, 60+ days untouched</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.neglected" class="pi-skeleton-list">
            <div v-for="i in 5" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="!neglected.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>All active contacts have been recently touched</p>
          </div>
          <ul v-else class="pi-risk-list">
            <li v-for="c in neglected" :key="c.id" class="pi-risk-item">
              <div class="pi-risk-left">
                <div class="pi-risk-name">{{ c.name }}</div>
                <div class="pi-risk-owner">{{ c.owner_name }} · <span class="pi-status-chip">{{ c.status_name }}</span></div>
              </div>
              <span class="pi-risk-badge" :class="neglectedBadgeClass(c.days_since_update)">{{ c.days_since_update }}d</span>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <!-- ══ Agent Coverage Load + Unworked Segments ══════════════════════════════════ -->
    <div class="pi-row-2col">

      <!-- Agent Coverage Load -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="12" r="6"/>
              <circle cx="12" cy="12" r="2"/>
            </svg>
            <span class="pi-card-title">Agent Coverage Load</span>
          </div>
          <span class="pi-card-meta">Total contacts per agent — bar shows actionable share</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.agentLoad" class="pi-skeleton-list">
            <div v-for="i in 4" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="!agentLoad.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="12" r="6"/>
              <circle cx="12" cy="12" r="2"/>
            </svg>
            <p>No agent data available</p>
          </div>
          <ul v-else class="pi-load-list">
            <li v-for="agent in agentLoad" :key="agent.user_id" class="pi-load-item">
              <div class="pi-load-header">
                <span class="pi-load-name">{{ agent.name }}</span>
                <span class="pi-load-stat">
                  {{ agent.total.toLocaleString() }}
                  <span class="pi-load-engaged-text">· {{ agent.engaged_count }} active</span>
                </span>
              </div>
              <div class="pi-load-track">
                <div
                  class="pi-load-bar"
                  :class="loadBarClass(agent.engaged_pct)"
                  :style="{ width: agent.load_pct + '%' }"
                ></div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Unworked Segment Opportunities -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            <span class="pi-card-title">Unworked Opportunities</span>
          </div>
          <span class="pi-card-meta">Active contacts per industry, untouched 30+ days</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.unworked" class="pi-skeleton-list">
            <div v-for="i in 4" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="!unworked.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>All active contacts have been recently engaged</p>
          </div>
          <ul v-else class="pi-unworked-list">
            <li v-for="s in unworked" :key="s.industry_id" class="pi-unworked-item">
              <div class="pi-unworked-header">
                <span class="pi-unworked-name">{{ s.industry_name }}</span>
                <span class="pi-unworked-badge">{{ s.unworked }} unworked</span>
              </div>
              <div class="pi-unworked-bar-row">
                <div class="pi-mini-bar pi-mini-bar--wide">
                  <div class="pi-mini-fill pi-prob--warning" :style="{ width: s.unworked_pct + '%' }"></div>
                </div>
                <span class="pi-unworked-pct">{{ s.unworked_pct }}%</span>
                <span class="pi-unworked-of">of {{ s.total }}</span>
              </div>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <!-- ══ Deal Win Probability ═════════════════════════════════════════════════════ -->
    <div class="pi-card">
      <div class="pi-card-head">
        <div class="pi-card-title-wrap">
          <svg class="pi-card-icon pi-card-icon--success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="7" width="20" height="14" rx="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <span class="pi-card-title">Deal Win Probability</span>
        </div>
        <span class="pi-card-meta">Auto-scored open deals based on activity and urgency</span>
      </div>
      <div class="pi-card-body">
        <div v-if="loading.deals" class="pi-skeleton-block"></div>
        <div v-else-if="!deals.length" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="7" width="20" height="14" rx="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <p>No open deals found</p>
        </div>
        <div v-else class="pi-table-wrap">
          <table class="pi-table">
            <thead>
              <tr>
                <th>Deal</th>
                <th>Contact</th>
                <th>Value</th>
                <th>Close Date</th>
                <th>Win Probability</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in deals" :key="d.id">
                <td class="pi-td-bold">{{ d.title }}</td>
                <td>{{ d.contact_name }}</td>
                <td>{{ formatCurrency(d.value) }}</td>
                <td>{{ d.expected_close_date }}</td>
                <td>
                  <div class="pi-bar-cell">
                    <div class="pi-mini-bar">
                      <div class="pi-mini-fill" :class="probFillClass(d.probability)" :style="{ width: d.probability + '%' }"></div>
                    </div>
                    <span>{{ d.probability }}%</span>
                  </div>
                </td>
                <td><span class="pi-deal-pill" :class="dealPillClass(d.probability)">{{ dealPillLabel(d.probability) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>


  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import {
  Chart,
  BarController, BarElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
} from 'chart.js';
import api from '../api.js';

Chart.register(
  BarController, BarElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
);

// ── Auth ──────────────────────────────────────────────────────────────────────
const currentUser = JSON.parse(localStorage.getItem('crm_user') || 'null');
const isAdmin = computed(() => {
  const roles = currentUser?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

// ── Date picker ───────────────────────────────────────────────────────────────
const todayStr = new Date().toISOString().slice(0, 10);

const PRESETS = [
  { label: 'Last 30 Days', days: 30  },
  { label: 'Last 60 Days', days: 60  },
  { label: 'Last 90 Days', days: 90  },
  { label: 'This Year',    days: 365 },
];

function dateStr(daysAgo) {
  const d = new Date();
  d.setDate(d.getDate() - daysAgo);
  return d.toISOString().slice(0, 10);
}

const pickerOpen = ref(false);
const pickerRef  = ref(null);
const customFrom = ref(dateStr(30));
const customTo   = ref(todayStr);

const filters = ref({
  label:   'Last 30 Days',
  from:    dateStr(30),
  to:      todayStr,
  user_id: '',
});

function applyPreset(p) {
  filters.value.from  = dateStr(p.days);
  filters.value.to    = todayStr;
  filters.value.label = p.label;
  pickerOpen.value    = false;
  loadAll();
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return;
  filters.value.from  = customFrom.value;
  filters.value.to    = customTo.value;
  filters.value.label = `${customFrom.value} → ${customTo.value}`;
  pickerOpen.value    = false;
  loadAll();
}

function closePicker(e) {
  if (pickerRef.value && !pickerRef.value.contains(e.target)) pickerOpen.value = false;
}

// ── Users (admin agent filter) ────────────────────────────────────────────────
const users = ref([]);

async function loadLookups() {
  try {
    const { data } = await api.get('/v1/lookups');
    users.value = data.users ?? [];
  } catch (_) { /* noop */ }
}

// ── Data refs ─────────────────────────────────────────────────────────────────
const summary   = ref({ neglected: null, pipeline_value: null, overloaded_agents: null, unworked_opps: null });
const forecast  = ref([]);
const neglected = ref([]);
const agentLoad = ref([]);
const unworked  = ref([]);
const deals     = ref([]);

const loading = ref({
  forecast:  false,
  neglected: false,
  agentLoad: false,
  unworked:  false,
  deals:     false,
});

// ── Chart canvas refs + instances ─────────────────────────────────────────────
const forecastCanvasRef = ref(null);
let   forecastChartInst = null;

// ── API calls ─────────────────────────────────────────────────────────────────
function buildParams() {
  const p = { from: filters.value.from, to: filters.value.to };
  if (filters.value.user_id) p.user_id = filters.value.user_id;
  return p;
}

async function loadSummary() {
  try {
    const { data } = await api.get('/v1/predictive/summary', { params: buildParams() });
    summary.value = data;
  } catch (_) { /* noop */ }
}

async function loadForecast() {
  loading.value.forecast = true;
  forecastChartInst?.destroy(); forecastChartInst = null;
  try {
    const { data } = await api.get('/v1/predictive/forecast', { params: buildParams() });
    forecast.value = data;
  } catch (_) { forecast.value = []; }
  finally { loading.value.forecast = false; }
  await nextTick();
  buildForecastChart();
}

async function loadNeglected() {
  loading.value.neglected = true;
  try {
    const { data } = await api.get('/v1/predictive/at-risk', { params: buildParams() });
    neglected.value = data;
  } catch (_) { neglected.value = []; }
  finally { loading.value.neglected = false; }
}

async function loadAgentLoad() {
  loading.value.agentLoad = true;
  try {
    const { data } = await api.get('/v1/predictive/pace', { params: buildParams() });
    agentLoad.value = data;
  } catch (_) { agentLoad.value = []; }
  finally { loading.value.agentLoad = false; }
}

async function loadUnworked() {
  loading.value.unworked = true;
  try {
    const { data } = await api.get('/v1/predictive/overdue-risk', { params: buildParams() });
    unworked.value = data;
  } catch (_) { unworked.value = []; }
  finally { loading.value.unworked = false; }
}

async function loadDeals() {
  loading.value.deals = true;
  try {
    const { data } = await api.get('/v1/predictive/deals', { params: buildParams() });
    deals.value = data;
  } catch (_) { deals.value = []; }
  finally { loading.value.deals = false; }
}

function loadAll() {
  loadSummary();
  loadForecast();
  loadNeglected();
  loadAgentLoad();
  loadUnworked();
  loadDeals();
}

// ── Display helpers ───────────────────────────────────────────────────────────
function formatCurrency(val) {
  if (val == null) return '—';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(val);
}

function neglectedBadgeClass(days) {
  if (days >= 180) return 'pi-risk-badge--danger';
  if (days >= 90)  return 'pi-risk-badge--warning';
  return 'pi-risk-badge--info';
}

function loadBarClass(engagedPct) {
  if (engagedPct >= 20) return 'pi-load-bar--primary';
  if (engagedPct >= 10) return 'pi-load-bar--warning';
  return 'pi-load-bar--muted';
}

function probFillClass(pct) {
  if (pct >= 70) return 'pi-prob--success';
  if (pct >= 40) return 'pi-prob--warning';
  return 'pi-prob--danger';
}

function dealPillClass(pct) {
  if (pct >= 70) return 'pi-deal-pill--on-track';
  if (pct >= 40) return 'pi-deal-pill--at-risk';
  return 'pi-deal-pill--high-risk';
}

function dealPillLabel(pct) {
  if (pct >= 70) return 'On Track';
  if (pct >= 40) return 'At Risk';
  return 'High Risk';
}

// ── Chart helpers ─────────────────────────────────────────────────────────────
function tooltipDefaults() {
  return {
    backgroundColor: '#0f2456',
    padding: 10,
    titleFont:   { size: 11, weight: '600' },
    bodyFont:    { size: 12 },
    displayColors: true,
    cornerRadius: 8,
  };
}

function buildForecastChart() {
  forecastChartInst?.destroy(); forecastChartInst = null;
  if (!forecastCanvasRef.value || !forecast.value.length) return;
  const rows = forecast.value;
  forecastChartInst = new Chart(forecastCanvasRef.value.getContext('2d'), {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [
        {
          label: 'Full Pipeline',
          data: rows.map(r => r.total_value),
          backgroundColor: 'rgba(148,163,184,0.2)',
          borderColor: 'rgba(148,163,184,0.5)',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
          order: 2,
        },
        {
          label: 'Expected Value',
          data: rows.map(r => r.expected_value),
          backgroundColor: 'rgba(29,78,216,0.55)',
          borderColor: '#1d4ed8',
          borderWidth: 1,
          borderRadius: 4,
          borderSkipped: false,
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 11 }, color: '#64748b', boxWidth: 12, padding: 12 },
        },
        tooltip: {
          ...tooltipDefaults(),
          callbacks: {
            afterBody: (items) => {
              const idx = items[0]?.dataIndex;
              const row = rows[idx];
              return row ? [`Deals: ${row.deal_count}`] : [];
            },
          },
        },
      },
      scales: {
        x: {
          border: { display: false },
          grid:   { display: false },
          ticks:  { font: { size: 11 }, color: '#94a3b8' },
        },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: 'rgba(148,163,184,0.12)', drawTicks: false },
          ticks: {
            font: { size: 10 }, color: '#94a3b8', padding: 8,
            callback: v => v >= 1000 ? `$${(v / 1000).toFixed(0)}k` : `$${v}`,
          },
        },
      },
    },
  });
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
  document.addEventListener('click', closePicker);
  loadLookups();
  loadAll();
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closePicker);
  forecastChartInst?.destroy();
});
</script>

<style scoped>
/* ── Root layout ──────────────────────────────────────────────────────────── */
.pi {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px 32px 48px;
  max-width: 1500px;
  margin: 0 auto;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.pi-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.pi-header h1 {
  font-size: 28px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.5px;
  margin: 0 0 4px;
}
.pi-subtitle {
  font-size: 13.5px;
  color: var(--text-3);
  margin: 0;
}
.pi-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* ── Date picker ──────────────────────────────────────────────────────────── */
.pi-date-wrap { position: relative; }
.pi-date-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 14px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); font-size: 13.5px; font-weight: 500; color: var(--text-1);
  cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s;
}
.pi-date-btn:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.pi-date-icon { width: 15px; height: 15px; color: var(--text-2); flex-shrink: 0; }
.pi-caret { width: 8px; height: 5px; color: var(--text-3); flex-shrink: 0; }
.pi-date-panel {
  position: absolute; top: calc(100% + 6px); right: 0; z-index: 200;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg); padding: 16px; min-width: 320px;
}
.pi-presets-section { margin-bottom: 14px; }
.pi-custom-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--text-3); margin-bottom: 8px;
}
.pi-presets { display: flex; flex-wrap: wrap; gap: 6px; }
.pi-preset {
  padding: 5px 12px; background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 20px; font-size: 12.5px; color: var(--text-2); cursor: pointer; transition: all 0.15s;
}
.pi-preset:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary-text); }
.pi-preset--on  { background: var(--primary-soft); border-color: var(--primary); color: var(--primary-text); font-weight: 600; }
.pi-custom { }
.pi-custom-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pi-custom-inline input {
  padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); flex: 1; min-width: 120px;
}
.pi-custom-sep { font-size: 12px; color: var(--text-3); }
.pi-apply-btn {
  white-space: nowrap; padding: 6px 14px; font-size: 13px; border-radius: var(--radius-sm);
  border: none; background: var(--primary); color: var(--primary-on);
  font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.pi-apply-btn:hover { background: var(--primary-hover); }
.panel-drop-enter-active, .panel-drop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.panel-drop-enter-from, .panel-drop-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.pi-filter-bar {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 10px 16px;
  box-shadow: var(--shadow-xs);
}
.pi-filter-group { display: flex; align-items: center; gap: 8px; }
.pi-filter-label {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--text-3); white-space: nowrap;
}
.pi-filter-select {
  height: 34px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s; outline: none;
}
.pi-filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

/* ── KPI row ──────────────────────────────────────────────────────────────── */
.pi-kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.pi-kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  box-shadow: var(--shadow-sm);
}
.pi-kpi-icon {
  width: 44px; height: 44px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pi-kpi-icon svg           { width: 20px; height: 20px; }
.pi-kpi-icon--danger  { background: var(--danger-soft);  color: var(--danger); }
.pi-kpi-icon--success { background: var(--success-soft); color: var(--success); }
.pi-kpi-icon--warning { background: var(--warning-soft); color: var(--warning); }
.pi-kpi-icon--info    { background: var(--info-soft);    color: var(--info); }
.pi-kpi-value { font-size: 26px; font-weight: 800; color: var(--text-1); line-height: 1.1; }
.pi-kpi-label { font-size: 13px; font-weight: 600; color: var(--text-1); margin-top: 2px; }
.pi-kpi-sub   { font-size: 11.5px; color: var(--text-3); margin-top: 2px; }

/* ── Layout grids ─────────────────────────────────────────────────────────── */
.pi-row-asym { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
.pi-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }

/* ── Card shell ───────────────────────────────────────────────────────────── */
.pi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
}
.pi-card-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; padding: 18px 20px 14px;
  border-bottom: 1px solid var(--border-soft); flex-wrap: wrap;
}
.pi-card-title-wrap { display: flex; align-items: center; gap: 10px; }
.pi-card-icon { width: 18px; height: 18px; color: var(--primary); flex-shrink: 0; }
.pi-card-icon--danger  { color: var(--danger); }
.pi-card-icon--warning { color: var(--warning); }
.pi-card-icon--info    { color: var(--info); }
.pi-card-icon--success { color: var(--success); }
.pi-card-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.pi-card-meta  { font-size: 12px; color: var(--text-3); }
.pi-card-body  { padding: 16px 20px; flex: 1; }

/* ── Empty state ──────────────────────────────────────────────────────────── */
.pi-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; padding: 32px 16px; color: var(--text-3); text-align: center;
}
.pi-empty svg { width: 36px; height: 36px; opacity: 0.4; }
.pi-empty p   { font-size: 13px; margin: 0; }

/* ── Skeleton loading ─────────────────────────────────────────────────────── */
.pi-skeleton-block {
  height: 200px; background: var(--border-soft); border-radius: var(--radius);
  animation: pi-pulse 1.4s ease-in-out infinite;
}
.pi-skeleton-block--tall { height: 260px; }
.pi-skeleton-list { display: flex; flex-direction: column; gap: 10px; }
.pi-skeleton-row {
  height: 36px; background: var(--border-soft); border-radius: var(--radius-sm);
  animation: pi-pulse 1.4s ease-in-out infinite;
}
@keyframes pi-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* ── Neglected contacts list ──────────────────────────────────────────────── */
.pi-risk-list {
  list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column;
  max-height: 320px; overflow-y: auto;
}
.pi-risk-item {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 9px 0; border-bottom: 1px solid var(--border-soft);
}
.pi-risk-item:last-child { border-bottom: none; }
.pi-risk-left   { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.pi-risk-name   { font-size: 13.5px; font-weight: 500; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi-risk-owner  { font-size: 11.5px; color: var(--text-3); display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.pi-status-chip {
  font-size: 10.5px; font-weight: 600; padding: 1px 7px;
  border-radius: 999px; background: var(--primary-soft); color: var(--primary-text);
}
.pi-risk-badge  { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
.pi-risk-badge--danger  { background: var(--danger-soft);  color: var(--danger); }
.pi-risk-badge--warning { background: var(--warning-soft); color: var(--warning); }
.pi-risk-badge--info    { background: var(--info-soft);    color: var(--info); }

/* ── Agent coverage load ──────────────────────────────────────────────────── */
.pi-load-list  { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
.pi-load-item  { display: flex; flex-direction: column; gap: 6px; }
.pi-load-header { display: flex; align-items: center; justify-content: space-between; }
.pi-load-name  { font-size: 13px; font-weight: 600; color: var(--text-1); }
.pi-load-stat  { font-size: 12px; color: var(--text-2); }
.pi-load-engaged-text { color: var(--success); font-weight: 600; }
.pi-load-track { height: 8px; background: var(--border-soft); border-radius: 99px; overflow: hidden; }
.pi-load-bar   { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.pi-load-bar--primary { background: var(--primary); }
.pi-load-bar--warning { background: var(--warning); }
.pi-load-bar--muted   { background: var(--text-3); }

/* ── Unworked segments ────────────────────────────────────────────────────── */
.pi-unworked-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.pi-unworked-item { display: flex; flex-direction: column; gap: 6px; }
.pi-unworked-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pi-unworked-name  { font-size: 13px; font-weight: 600; color: var(--text-1); }
.pi-unworked-badge { font-size: 11.5px; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: var(--warning-soft); color: var(--warning); white-space: nowrap; }
.pi-unworked-bar-row { display: flex; align-items: center; gap: 8px; }
.pi-unworked-pct { font-size: 12px; font-weight: 700; color: var(--text-2); min-width: 36px; }
.pi-unworked-of  { font-size: 11.5px; color: var(--text-3); }

/* ── Tables ───────────────────────────────────────────────────────────────── */
.pi-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
.pi-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pi-table thead tr { background: var(--surface-2); }
.pi-table th {
  text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-2);
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.pi-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
.pi-table tr:last-child td { border-bottom: none; }
.pi-table tbody tr:hover td { background: var(--surface-2); }
.pi-td-bold { font-weight: 600; }
/* Mini bar (used in table cells and unworked list) */
.pi-bar-cell  { display: flex; align-items: center; gap: 8px; }
.pi-mini-bar  { width: 80px; height: 6px; background: var(--border-soft); border-radius: 99px; overflow: hidden; }
.pi-mini-bar--wide { width: 100%; flex: 1; }
.pi-mini-fill { height: 100%; border-radius: 99px; background: var(--primary); }
.pi-prob--success { background: var(--success); }
.pi-prob--warning { background: var(--warning); }
.pi-prob--danger  { background: var(--danger); }

/* Deal pills */
.pi-deal-pill { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
.pi-deal-pill--on-track  { background: var(--success-soft); color: var(--success); }
.pi-deal-pill--at-risk   { background: var(--warning-soft); color: var(--warning); }
.pi-deal-pill--high-risk { background: var(--danger-soft);  color: var(--danger); }

/* ── Chart canvases ───────────────────────────────────────────────────────── */
.pi-chart-wrap { height: 260px; position: relative; }
.pi-chart-wrap canvas { width: 100% !important; height: 100% !important; }

/* Forecast card body should always have enough room for the chart */
.pi-row-asym .pi-card:first-child .pi-card-body { min-height: 300px; }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .pi-kpi-row  { grid-template-columns: repeat(2, 1fr); }
  .pi-row-asym { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .pi               { padding: 18px 14px; }
  .pi-kpi-row       { grid-template-columns: 1fr 1fr; }
  .pi-row-2col      { grid-template-columns: 1fr; }
  .pi-date-panel    { min-width: 0; right: -10px; }
  .pi-header-actions { width: 100%; }
}
@media (max-width: 480px) {
  .pi         { padding: 14px 10px; }
  .pi-kpi-row { grid-template-columns: 1fr; }
}
</style>
