<template>
  <div class="ca">

    <!-- ══ Header ═══════════════════════════════════════════════════════════════ -->
    <div class="ca-header">
      <div class="ca-header-left">
        <h1>Contact Analysis</h1>
        <p class="ca-subtitle">Engagement health, pipeline snapshot, and acquisition breakdown for your contacts.</p>
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
              <div class="ca-presets-section">
                <div class="ca-custom-title">Presets</div>
                <div class="ca-presets">
                  <button
                    v-for="p in PRESETS" :key="p.label"
                    class="ca-preset"
                    :class="{ 'ca-preset--on': filters.label === p.label }"
                    @click="applyPreset(p)"
                  >{{ p.label }}</button>
                </div>
              </div>
              <div class="ca-custom">
                <div class="ca-custom-title">Custom range</div>
                <div class="ca-custom-inline">
                  <input type="date" v-model="customFrom" :max="customTo" />
                  <span class="ca-custom-sep">to</span>
                  <input type="date" v-model="customTo" :min="customFrom" :max="todayStr" />
                  <button class="btn btn-primary ca-apply-btn" @click="applyCustom">Apply</button>
                </div>
              </div>
            </div>
          </transition>
        </div>
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

    <!-- ══ Attention Required ════════════════════════════════════════════════════ -->
    <div class="ca-section-label">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="ca-section-icon">
        <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
      </svg>
      Needs Attention
    </div>
    <div class="ca-attention-row">

      <div class="ca-attn-card ca-attn-card--overdue">
        <div class="ca-attn-top">
          <div class="ca-attn-icon-wrap ca-attn-icon--overdue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
            </svg>
          </div>
          <div class="ca-attn-count">
            <span v-if="loading.overview">—</span>
            <span v-else>{{ overviewData?.overdue_tasks ?? 0 }}</span>
          </div>
        </div>
        <div class="ca-attn-label">Overdue Tasks</div>
        <div class="ca-attn-desc">Pending tasks past their scheduled date</div>
      </div>

      <div class="ca-attn-card ca-attn-card--dormant" @click="focusEngagement('dormant')" role="button" tabindex="0">
        <div class="ca-attn-top">
          <div class="ca-attn-icon-wrap ca-attn-icon--dormant">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <line x1="22" y1="9" x2="16" y2="9"/>
            </svg>
          </div>
          <div class="ca-attn-count">
            <span v-if="loading.engagement">—</span>
            <span v-else>{{ engSummary.dormant ?? 0 }}</span>
          </div>
        </div>
        <div class="ca-attn-label">Dormant Contacts</div>
        <div class="ca-attn-desc">Had activity, gone silent for 60+ days</div>
        <div class="ca-attn-action">
          View contacts
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </div>

      <div class="ca-attn-card ca-attn-card--at-risk" @click="focusEngagement('at_risk')" role="button" tabindex="0">
        <div class="ca-attn-top">
          <div class="ca-attn-icon-wrap ca-attn-icon--at-risk">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4m0 4h.01"/>
            </svg>
          </div>
          <div class="ca-attn-count">
            <span v-if="loading.engagement">—</span>
            <span v-else>{{ engSummary.at_risk ?? 0 }}</span>
          </div>
        </div>
        <div class="ca-attn-label">At Risk</div>
        <div class="ca-attn-desc">No activity logged in the last 30–60 days</div>
        <div class="ca-attn-action">
          View contacts
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </div>

      <div class="ca-attn-card ca-attn-card--never" @click="focusEngagement('no_activity')" role="button" tabindex="0">
        <div class="ca-attn-top">
          <div class="ca-attn-icon-wrap ca-attn-icon--never">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M19 8v6M22 11h-6"/>
            </svg>
          </div>
          <div class="ca-attn-count">
            <span v-if="loading.engagement">—</span>
            <span v-else>{{ engSummary.no_activity ?? 0 }}</span>
          </div>
        </div>
        <div class="ca-attn-label">Never Contacted</div>
        <div class="ca-attn-desc">Contacts with no tasks logged at all</div>
        <div class="ca-attn-action">
          View contacts
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
      </div>

    </div>

    <!-- ══ Pipeline Snapshot + Lead Source ══════════════════════════════════════ -->
    <div class="ca-row-asym">

      <!-- Contact Status Distribution -->
      <div class="ca-card">
        <div class="ca-card-top">
          <div>
            <div class="ca-card-title">Pipeline Snapshot</div>
            <div class="ca-card-sub">All contacts distributed by current status</div>
          </div>
          <span class="ca-card-total">{{ statusDistData.total }} total</span>
        </div>
        <div v-if="loading.statusDist" class="ca-chart-loading" style="height:120px">Loading…</div>
        <template v-else>
          <div v-if="statusDistData.statuses?.length">
            <div class="ca-source-bars">
              <div v-for="(s, i) in statusDistData.statuses" :key="s.name" class="ca-source-bar-item">
                <div class="ca-source-bar-hdr">
                  <span>{{ s.name }}</span>
                  <span class="ca-source-count-pct">
                    <strong>{{ s.count }}</strong>
                    <span class="ca-source-pct">{{ s.pct }}%</span>
                  </span>
                </div>
                <div class="ca-source-track">
                  <div class="ca-source-fill" :style="{ width: s.pct + '%', background: statusColor(i) }"></div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="ca-empty-chart">No contacts found.</div>
        </template>
      </div>

      <!-- Lead Source -->
      <div class="ca-card">
        <div class="ca-card-top">
          <div>
            <div class="ca-card-title">Lead Source</div>
            <div class="ca-card-sub">New contacts in period by acquisition channel</div>
          </div>
          <span class="ca-card-total">{{ sourceData.total }} added</span>
        </div>
        <div v-if="loading.source" class="ca-chart-loading" style="height:120px">Loading…</div>
        <template v-else>
          <div v-if="sourceData.sources?.length">
            <div class="ca-source-bars">
              <div v-for="s in sourceData.sources" :key="s.source" class="ca-source-bar-item">
                <div class="ca-source-bar-hdr">
                  <span>{{ s.label }}</span>
                  <span class="ca-source-count-pct">
                    <strong>{{ s.count }}</strong>
                    <span class="ca-source-pct">{{ s.pct }}%</span>
                  </span>
                </div>
                <div class="ca-source-track">
                  <div class="ca-source-fill" :style="{ width: s.pct + '%', background: sourceColor(s.source) }"></div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="ca-empty-chart">No contacts added in this period.</div>
        </template>
      </div>

    </div>

    <!-- ══ Engagement Health ══════════════════════════════════════════════════════ -->
    <div class="ca-card ca-card--eng" ref="engSectionRef">

      <div class="ca-eng-head">
        <div>
          <div class="ca-card-title">Engagement Health</div>
          <div class="ca-card-sub">Contacts ranked by inactivity — click a card above to jump to a specific group.</div>
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
        <div class="ca-per-page-wrap">
          <span class="ca-per-page-label">Rows</span>
          <select v-model.number="engFilters.per_page" @change="loadEngagement(1)" class="ca-eng-select">
            <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading.engagement" class="ca-chart-loading" style="height:140px">Loading…</div>
      <template v-else>
        <div class="ca-tbl-scroll">
          <table class="ca-tbl ca-tbl--full ca-tbl--eng">
            <thead>
              <tr>
                <th class="ca-th-sort" @click="sortEng('name')">
                  Contact Name <span class="ca-sort-icon" v-html="sortIcon('name')"></span>
                </th>
                <th>Agent</th>
                <th>Status</th>
                <th class="ca-th-sort" @click="sortEng('last_todo_date')">
                  Last Task <span class="ca-sort-icon" v-html="sortIcon('last_todo_date')"></span>
                </th>
                <th class="ca-th-sort" @click="sortEng('days_inactive')">
                  Days Inactive <span class="ca-sort-icon" v-html="sortIcon('days_inactive')"></span>
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
                  <span class="ca-health-pill-badge" :class="`ca-health-pill-badge--${c.health}`">
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
            <template v-for="pg in pageNumbers" :key="String(pg) + '_' + engMeta.current_page">
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
import api from '../api.js';

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
const loading = reactive({ overview: false, source: false, statusDist: false, engagement: false });

// ─── Overview (overdue tasks count) ───────────────────────────────────────
const overviewData = ref(null);

async function loadOverview() {
  loading.overview = true;
  try {
    const { data } = await api.get('/v1/contact-analysis/overview', { params: buildParams() });
    overviewData.value = data;
  } finally {
    loading.overview = false;
  }
}

// ─── Lead Source ───────────────────────────────────────────────────────────
const sourceData = ref({ sources: [], total: 0 });

const SOURCE_COLORS = {
  manual: '#1d4ed8', phone_call: '#0891b2', referral: '#059669',
  walk_in: '#d97706', social_media: '#db2777', email_campaign: '#dc2626',
  web_form: '#4f46e5', other: '#64748b', unknown: '#94a3b8',
  exhibition: '#f59e0b', linkedin: '#0e7490', tender: '#84cc16',
  cold_call: '#e11d48', whatsapp: '#16a34a', direct: '#6366f1',
};
function sourceColor(s) { return SOURCE_COLORS[s] ?? '#94a3b8'; }

async function loadSource() {
  loading.source = true;
  try {
    const { data } = await api.get('/v1/contact-analysis/lead-source', { params: buildParams() });
    sourceData.value = data;
  } finally {
    loading.source = false;
  }
}

// ─── Status Distribution ───────────────────────────────────────────────────
const statusDistData = ref({ statuses: [], total: 0 });

const STATUS_PALETTE = ['#1d4ed8','#0891b2','#059669','#d97706','#dc2626','#db2777','#4f46e5','#0e7490','#84cc16','#f59e0b','#64748b'];
function statusColor(i) { return STATUS_PALETTE[i % STATUS_PALETTE.length]; }

async function loadStatusDist() {
  loading.statusDist = true;
  try {
    const params = {};
    if (filters.user_id)     params.user_id     = filters.user_id;
    if (filters.industry_id) params.industry_id = filters.industry_id;
    const { data } = await api.get('/v1/contact-analysis/status-distribution', { params });
    statusDistData.value = data;
  } finally {
    loading.statusDist = false;
  }
}

// ─── Engagement table ──────────────────────────────────────────────────────
const PER_PAGE_OPTIONS = [10, 20, 50];

const engData    = ref([]);
const engMeta    = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const engSummary = ref({ total: 0, active: 0, at_risk: 0, dormant: 0, no_activity: 0 });
const engSectionRef = ref(null);

const engFilters = reactive({
  health:    '',
  q:         '',
  user_id:   '',
  status_id: '',
  sort_by:   'days_inactive',
  sort_dir:  'desc',
  per_page:  10,
});

const HEALTH_TABS = [
  { key: '',            label: 'All',             countKey: 'total' },
  { key: 'active',      label: 'Active',          countKey: 'active' },
  { key: 'at_risk',     label: 'At Risk',         countKey: 'at_risk' },
  { key: 'dormant',     label: 'Dormant',         countKey: 'dormant' },
  { key: 'no_activity', label: 'Never Contacted', countKey: 'no_activity' },
];

function healthLabel(h) {
  return { active: 'Active', at_risk: 'At Risk', dormant: 'Dormant', no_activity: 'No Tasks' }[h] ?? h;
}

function setHealth(key) {
  engFilters.health = key;
  loadEngagement(1);
}

function focusEngagement(health) {
  engFilters.health = health;
  loadEngagement(1);
  nextTick(() => {
    engSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
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
  if (engFilters.sort_by !== field) return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4"><line x1="12" y1="20" x2="12" y2="4"/><polyline points="5 11 12 4 19 11"/><polyline points="19 13 12 20 5 13"/></svg>';
  return engFilters.sort_dir === 'asc'
    ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>'
    : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
}

function initials(name) {
  return (name || '').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

const pageNumbers = computed(() => {
  const total = engMeta.last_page;
  const cur   = engMeta.current_page;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)          return [1, 2, 3, '...', total];
  if (cur >= total - 2)  return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

// ─── Load functions ────────────────────────────────────────────────────────
async function loadEngagement(page = 1) {
  loading.engagement = true;
  try {
    const params = {
      page,
      per_page: engFilters.per_page,
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
  await Promise.all([loadOverview(), loadSource(), loadStatusDist()]);
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
});
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────────────────── */
.ca {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px 32px 48px;
  max-width: 1500px;
  margin: 0 auto;
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.ca-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.ca-header-left h1 {
  font-size: 28px;
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
.ca-date-btn:hover { border-color: var(--primary, #1d4ed8); background: #eaddff; }
.ca-date-icon { width: 15px; height: 15px; color: var(--primary, #1d4ed8); flex-shrink: 0; }
.ca-caret     { width: 10px; height: 6px; color: var(--text-3); margin-left: 2px; }

.ca-date-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 200;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  padding: 16px;
  min-width: 340px;
}
.ca-presets-section { display: flex; flex-direction: column; gap: 8px; }
.ca-presets { display: flex; flex-wrap: wrap; gap: 6px; }
.ca-preset {
  padding: 7px 14px;
  font-size: 13px;
  border: none;
  background: transparent;
  color: var(--text-2, #475569);
  border-radius: 999px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.12s, color 0.12s;
}
.ca-preset:hover     { background: #eff4ff; color: var(--text-1); }
.ca-preset--on       { background: var(--primary, #1d4ed8); color: #fff; font-weight: 600; }
.ca-preset--on:hover { background: var(--primary, #1d4ed8); }

.ca-custom       { display: flex; flex-direction: column; gap: 10px; }
.ca-custom-title { font-size: 10.5px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.08em; }
.ca-custom-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ca-custom-sep { font-size: 13px; color: var(--text-3); flex-shrink: 0; }
.ca-custom-inline input[type="date"] {
  flex: 1;
  min-width: 120px;
  padding: 5px 8px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 6px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--surface-2);
}
.ca-apply-btn { flex-shrink: 0; }

/* ── Filter Bar ──────────────────────────────────────────────────────────── */
.ca-filter-bar {
  background: var(--surface);
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
  background: var(--border-soft);
  cursor: pointer;
  outline: none;
  transition: box-shadow 0.12s;
}
.ca-filter-select:focus { box-shadow: 0 0 0 2px rgba(29,78,216,0.2); }
.ca-clear-btn {
  font-size: 12px;
  font-weight: 700;
  color: var(--primary, #1d4ed8);
  background: none;
  border: none;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 3px;
  padding: 4px 8px;
  white-space: nowrap;
  margin-left: auto;
}

/* ── Section label ───────────────────────────────────────────────────────── */
.ca-section-label {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  font-weight: 800;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: -6px;
}
.ca-section-icon { width: 14px; height: 14px; flex-shrink: 0; }

/* ── Attention Cards ─────────────────────────────────────────────────────── */
.ca-attention-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.ca-attn-card {
  background: var(--surface);
  border: 1px solid var(--border, #e2e8f0);
  border-left: 4px solid transparent;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: transform 0.15s, box-shadow 0.15s;
}
.ca-attn-card[role="button"] { cursor: pointer; }
.ca-attn-card[role="button"]:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.09);
}
.ca-attn-card[role="button"]:focus-visible {
  outline: 2px solid var(--primary, #1d4ed8);
  outline-offset: 2px;
}

.ca-attn-card--overdue { border-left-color: #dc2626; }
.ca-attn-card--dormant { border-left-color: #b91c1c; }
.ca-attn-card--at-risk { border-left-color: #d97706; }
.ca-attn-card--never   { border-left-color: #64748b; }

.ca-attn-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 4px;
}

.ca-attn-icon-wrap {
  width: 38px; height: 38px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ca-attn-icon-wrap svg { width: 18px; height: 18px; }

.ca-attn-icon--overdue { background: #fee2e2; color: #dc2626; }
.ca-attn-icon--dormant { background: #fecaca; color: #b91c1c; }
.ca-attn-icon--at-risk { background: #fef3c7; color: #d97706; }
.ca-attn-icon--never   { background: #e2e8f0; color: #475569; }

.ca-attn-count {
  font-size: 34px;
  font-weight: 800;
  color: var(--text-1);
  line-height: 1;
  letter-spacing: -0.03em;
}

.ca-attn-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.ca-attn-desc {
  font-size: 12px;
  color: var(--text-3);
  line-height: 1.4;
  flex: 1;
}
.ca-attn-action {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  color: var(--primary, #1d4ed8);
  margin-top: 4px;
}
.ca-attn-action svg { width: 13px; height: 13px; }

/* ── Cards ───────────────────────────────────────────────────────────────── */
.ca-card {
  background: var(--surface);
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

/* ── Charts / loading ────────────────────────────────────────────────────── */
.ca-chart-loading { display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--text-3); }
.ca-empty-chart  { text-align: center; padding: 40px 0; font-size: 13px; color: var(--text-3); }

/* ── Asymmetric two-column ───────────────────────────────────────────────── */
.ca-row-asym { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

/* ── Source / Status progress bars ──────────────────────────────────────── */
.ca-source-bars { display: flex; flex-direction: column; gap: 12px; }
.ca-source-bar-item { display: flex; flex-direction: column; gap: 4px; }
.ca-source-bar-hdr {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: var(--text-2);
}
.ca-source-count-pct {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ca-source-count-pct strong { color: var(--text-1); }
.ca-source-pct { color: var(--text-3); font-weight: 600; }
.ca-source-track {
  width: 100%;
  height: 8px;
  background: var(--border-soft);
  border-radius: 999px;
  overflow: hidden;
}
.ca-source-fill { height: 100%; border-radius: 999px; transition: width 0.5s; }

/* ── Tables ──────────────────────────────────────────────────────────────── */
.ca-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
.ca-tbl th {
  text-align: left;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 8px 8px 8px 0;
  border-bottom: 1px solid var(--border-soft);
}
.ca-tbl td {
  padding: 8px 8px 8px 0;
  color: var(--text-2);
  border-bottom: 1px solid var(--border-soft);
  vertical-align: middle;
}
.ca-tbl tr:last-child td { border-bottom: none; }
.ca-tbl--full { width: 100%; }
.ca-tbl-scroll { overflow-x: auto; }

/* ── Engagement card ─────────────────────────────────────────────────────── */
.ca-card--eng { padding: 0; gap: 0; overflow: hidden; }

.ca-eng-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 22px 22px 18px;
  border-bottom: 1px solid var(--border-soft);
}

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
.ca-health-pill--on { background: #fff; color: var(--primary, #1d4ed8); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.ca-health-pill--on strong { color: var(--primary, #1d4ed8); }

.ca-eng-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  border-bottom: 1px solid var(--border-soft);
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
  border: 1px solid var(--border-soft);
  border-radius: 999px;
  font-size: 13px;
  color: var(--text-1);
  background: var(--border-soft);
  outline: none;
  transition: border-color 0.12s, background 0.12s;
  box-sizing: border-box;
}
.ca-search-input:focus { background: #fff; border-color: var(--primary, #1d4ed8); }
.ca-search-input::placeholder { color: var(--text-3); }

.ca-eng-select {
  padding: 7px 14px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-1);
  background: var(--surface);
  cursor: pointer;
  outline: none;
  transition: border-color 0.12s;
}
.ca-eng-select:focus { border-color: var(--primary, #1d4ed8); }

.ca-per-page-wrap { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.ca-per-page-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.ca-tbl--eng { min-width: 680px; }
.ca-tbl--eng thead tr { background: var(--surface-2); }
.ca-tbl--eng thead th {
  padding: 11px 14px;
  border-bottom: 2px solid var(--border);
  border-right: 1px solid var(--border-soft);
  color: var(--text-2);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}
.ca-tbl--eng thead th:last-child { border-right: none; }
.ca-tbl--eng tbody td {
  padding: 13px 14px;
  border-bottom: 1px solid var(--border-soft);
  border-right: 1px solid var(--border-soft);
  color: var(--text-1);
}
.ca-tbl--eng tbody td:last-child { border-right: none; }
.ca-tbl--eng tbody tr { transition: background 0.12s; }
.ca-tbl--eng tbody tr:hover { background: var(--surface-2); }
.ca-tbl--eng tbody tr:last-child td { border-bottom: none; }

.ca-contact-cell { display: flex; align-items: center; gap: 12px; }
.ca-avatar {
  width: 26px; height: 26px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 9px; font-weight: 700;
  flex-shrink: 0;
}
.ca-avatar--active      { background: #dcfce7; color: #166534; }
.ca-avatar--at_risk     { background: #fef3c7; color: #92400e; }
.ca-avatar--dormant     { background: #fee2e2; color: #991b1b; }
.ca-avatar--no_activity { background: var(--border-soft); color: #4a4455; }

.ca-status-pill {
  display: inline-block;
  padding: 3px 10px;
  background: #dce9ff;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  color: #4a4455;
}

.ca-health-pill-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
}
.ca-health-pill-badge--active      { background: #dcfce7; color: #166534; }
.ca-health-pill-badge--at_risk     { background: #fef3c7; color: #92400e; }
.ca-health-pill-badge--dormant     { background: #fee2e2; color: #991b1b; }
.ca-health-pill-badge--no_activity { background: var(--border-soft); color: #4a4455; }

.ca-chevron-cell { width: 40px; text-align: right; }
.ca-chevron {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px;
  border-radius: 50%;
  color: var(--primary, #1d4ed8);
  opacity: 0;
  transition: opacity 0.12s;
}
.ca-chevron svg { width: 16px; height: 16px; }
.ca-eng-row:hover .ca-chevron { opacity: 1; }

.ca-th-sort { cursor: pointer; user-select: none; }
.ca-th-sort:hover { color: var(--primary, #1d4ed8); }
.ca-sort-icon { font-size: 10px; color: var(--text-3); margin-left: 2px; }

.ca-link { color: var(--primary, #1d4ed8); text-decoration: none; font-weight: 600; }
.ca-link:hover { text-decoration: underline; }

.ca-empty { text-align: center; padding: 32px; color: var(--text-3); font-size: 13px; }

/* ── Pagination ───────────────────────────────────────────────────────────── */
.ca-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 22px;
  background: var(--surface-2);
  border-top: 1px solid var(--border-soft);
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
.ca-page-nav:hover:not(:disabled) { background: var(--border-soft); }
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
.ca-page-num:hover { background: var(--border-soft); }
.ca-page-num--on  { background: var(--primary, #1d4ed8); color: #fff; font-weight: 700; }
.ca-page-ellipsis { width: 32px; text-align: center; color: var(--text-3); font-size: 13px; line-height: 32px; }

/* ── Panel transition ─────────────────────────────────────────────────────── */
.panel-drop-enter-active, .panel-drop-leave-active { transition: opacity 0.15s, transform 0.15s; }
.panel-drop-enter-from, .panel-drop-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .ca-attention-row { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 900px) {
  .ca              { padding: 18px 14px; }
  .ca-attention-row { grid-template-columns: repeat(2, 1fr); }
  .ca-row-asym      { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .ca              { padding: 14px 10px; }
  .ca-attention-row { grid-template-columns: 1fr 1fr; }
  .ca-date-panel    { min-width: 0; right: -10px; }
  .ca-header-actions { width: 100%; }
  .ca-health-pill-group { border-radius: 12px; }
}
</style>
