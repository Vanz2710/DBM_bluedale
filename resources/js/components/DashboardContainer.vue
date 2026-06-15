<template>
  <div class="dashboard">
    <!-- Welcome hero -->
    <div class="dash-hero" data-tour="dash-hero">
      <div class="hero-glow" aria-hidden="true"></div>
      <div class="hero-inner">
        <div class="hero-text">
          <span class="hero-eyebrow">{{ todayLabel }}</span>
          <h1 class="hero-greeting">
            {{ greeting }}<template v-if="firstName">, {{ firstName }}</template>
          </h1>
          <p class="hero-sub">Here's your workspace at a glance — drag, resize and add widgets to make it your own.</p>
        </div>
        <div class="hero-actions">
          <span v-if="editMode" class="edit-hint">
            <Move :size="12" />
            Drag &amp; resize to customise
          </span>
          <span
            v-if="saveStatus !== 'idle'"
            class="save-status"
            :class="`save-status--${saveStatus}`"
          >{{ saveStatus === 'saved' ? 'Saved' : saveStatus === 'error' ? 'Save failed' : 'Saving…' }}</span>
          <button
            class="hbtn hbtn-ghost"
            :class="{ 'hbtn-ghost--active': editMode }"
            @click="editMode = !editMode"
            :title="editMode ? 'Exit edit mode' : 'Rearrange widgets'"
            data-tour="dash-edit-layout"
          >
            <LayoutGrid :size="14" />
            {{ editMode ? 'Done' : 'Edit Layout' }}
          </button>
          <button class="hbtn hbtn-solid" @click="showPicker = true" data-tour="dash-add-widget">
            <Plus :size="14" /> Add Widget
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingLayout" class="canvas-loading">
      <LoadingSpinner />
    </div>

    <!-- Empty canvas -->
    <div v-else-if="!layout.length" class="empty-canvas">
      <LayoutGrid :size="44" class="empty-icon" />
      <p>Your dashboard is empty.</p>
      <button class="btn btn-primary" @click="showPicker = true">
        <Plus :size="13" /> Add Your First Widget
      </button>
    </div>

    <!-- Grid -->
    <div v-else class="grid-canvas">
      <GridLayout
        v-model:layout="layout"
        :col-num="12"
        :row-height="ROW_HEIGHT"
        :is-draggable="editMode"
        :is-resizable="editMode"
        :margin="[12, 12]"
        :use-css-transforms="true"
        @layout-updated="onLayoutUpdated"
      >
        <GridItem
          v-for="item in layout"
          :key="item.i"
          :x="item.x"
          :y="item.y"
          :w="item.w"
          :h="item.h"
          :i="item.i"
          :min-w="2"
          :min-h="3"
        >
          <div
            class="widget-shell"
            :class="{ 'widget-shell--edit': editMode, 'widget-shell--new': item.i === newWidgetId }"
            :data-widget-id="item.i"
          >
            <button
              v-if="editMode"
              class="widget-remove"
              title="Remove widget"
              @click.stop="removeWidget(item.i)"
            >
              <X :size="11" />
            </button>
            <component
              :is="widgetComponents[item.type]"
              v-if="widgetComponents[item.type]"
            />
            <div v-else class="widget-unknown">
              Unknown: {{ item.type }}
            </div>
          </div>
        </GridItem>
      </GridLayout>
    </div>

    <!-- Widget picker modal -->
    <Teleport to="body">
      <div v-if="showPicker" class="modal-backdrop" @click.self="showPicker = false">
        <div class="modal-box">
          <div class="modal-head">
            <span class="modal-title">Add Widget</span>
            <button class="btn-icon" @click="showPicker = false"><X :size="15" /></button>
          </div>
          <div class="widget-catalog">
            <button
              v-for="w in WIDGET_CATALOG"
              :key="w.type"
              class="catalog-card"
              @click="addWidget(w)"
            >
              <component :is="w.icon" :size="22" class="catalog-icon" />
              <div>
                <div class="catalog-name">{{ w.label }}</div>
                <div class="catalog-desc">{{ w.description }}</div>
              </div>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent } from 'vue';
import { GridLayout, GridItem } from 'grid-layout-plus';
import { Plus, X, LayoutGrid, Move, TrendingUp, Users, BarChart2, ClipboardList, Target, CalendarCheck, Briefcase, Zap, AlertTriangle, BarChart3 } from 'lucide-vue-next';
import api from '../api.js';
import LoadingSpinner from './LoadingSpinner.vue';

// --- Registry (lazy-loaded — only fetched when a widget is actually on the canvas) ---
const widgetComponents = {
  RevenueChartWidget:       defineAsyncComponent(() => import('./widgets/RevenueChartWidget.vue')),
  RecentContactsWidget:     defineAsyncComponent(() => import('./widgets/RecentContactsWidget.vue')),
  KpiStatsWidget:           defineAsyncComponent(() => import('./widgets/KpiStatsWidget.vue')),
  TasksWidget:              defineAsyncComponent(() => import('./widgets/TasksWidget.vue')),
  KpiTargetWidget:          defineAsyncComponent(() => import('./widgets/KpiTargetWidget.vue')),
  UpcomingFollowUpsWidget:  defineAsyncComponent(() => import('./widgets/UpcomingFollowUpsWidget.vue')),
  DealPipelineWidget:       defineAsyncComponent(() => import('./widgets/DealPipelineWidget.vue')),
  PredictiveSummaryWidget:  defineAsyncComponent(() => import('./widgets/PredictiveSummaryWidget.vue')),
  AtRiskContactsWidget:     defineAsyncComponent(() => import('./widgets/AtRiskContactsWidget.vue')),
  ForecastPipelineWidget:   defineAsyncComponent(() => import('./widgets/ForecastPipelineWidget.vue')),
};

const WIDGET_CATALOG = [
  {
    type: 'RevenueChartWidget',
    label: 'Monthly Pipeline',
    description: 'Line chart of CRM entries by month',
    icon: TrendingUp,
    defaultSize: { w: 8, h: 5 },
  },
  {
    type: 'RecentContactsWidget',
    label: 'Recent Contacts',
    description: 'Latest added contacts with quick links',
    icon: Users,
    defaultSize: { w: 4, h: 6 },
  },
  {
    type: 'KpiStatsWidget',
    label: 'KPI Stats',
    description: 'Key contact counts at a glance',
    icon: BarChart2,
    defaultSize: { w: 4, h: 4 },
  },
  {
    type: 'TasksWidget',
    label: 'Pending Tasks',
    description: 'Upcoming to-dos needing attention',
    icon: ClipboardList,
    defaultSize: { w: 8, h: 5 },
  },
  {
    type: 'KpiTargetWidget',
    label: 'KPI Progress',
    description: 'This month\'s targets vs actuals with progress bars',
    icon: Target,
    defaultSize: { w: 4, h: 6 },
  },
  {
    type: 'UpcomingFollowUpsWidget',
    label: 'Upcoming Follow-Ups',
    description: 'Pending follow-ups due in the next 7 days',
    icon: CalendarCheck,
    defaultSize: { w: 4, h: 5 },
  },
  {
    type: 'DealPipelineWidget',
    label: 'Deal Pipeline',
    description: 'Open deals, pipeline value, and stage breakdown',
    icon: Briefcase,
    defaultSize: { w: 4, h: 6 },
  },
  {
    type: 'PredictiveSummaryWidget',
    label: 'Predictive Insights',
    description: 'At-risk contacts, weighted pipeline, and overdue risk',
    icon: Zap,
    defaultSize: { w: 4, h: 5 },
  },
  {
    type: 'AtRiskContactsWidget',
    label: 'At-Risk Contacts',
    description: 'Contacts with no activity in 30+ days',
    icon: AlertTriangle,
    defaultSize: { w: 4, h: 5 },
  },
  {
    type: 'ForecastPipelineWidget',
    label: 'Revenue Forecast',
    description: 'Weighted deal pipeline forecast by close month',
    icon: BarChart3,
    defaultSize: { w: 8, h: 5 },
  },
];

const DEFAULT_LAYOUT = [
  { i: 'w-1', x: 0, y: 0, w: 8, h: 5, type: 'RevenueChartWidget'  },
  { i: 'w-2', x: 8, y: 0, w: 4, h: 5, type: 'RecentContactsWidget' },
  { i: 'w-3', x: 0, y: 5, w: 4, h: 4, type: 'KpiStatsWidget'      },
  { i: 'w-4', x: 4, y: 5, w: 8, h: 4, type: 'TasksWidget'         },
];

const ROW_HEIGHT = 80;

// --- Greeting -----------------------------------------------------------
const _user      = JSON.parse(localStorage.getItem('crm_user') || 'null');
const firstName  = String(_user?.name || '').trim().split(/\s+/)[0] || '';
const _now       = new Date();
const _hour      = _now.getHours();
const greeting   = _hour < 12 ? 'Good morning' : _hour < 18 ? 'Good afternoon' : 'Good evening';
const todayLabel = _now.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// --- State --------------------------------------------------------------
const layout        = ref([]);
const editMode      = ref(false);
const showPicker    = ref(false);
const loadingLayout = ref(false);
const saveStatus    = ref('idle'); // 'idle' | 'pending' | 'saving' | 'saved' | 'error'
const newWidgetId   = ref(null);

let initialised = false;
let saveTimer   = null;
let fadeTimer   = null;
let glowTimer   = null;

// --- Auto-save ----------------------------------------------------------
function scheduleSave() {
  if (!initialised) return;
  clearTimeout(saveTimer);
  if (saveStatus.value !== 'saving') saveStatus.value = 'pending';
  saveTimer = setTimeout(doSave, 1200);
}

async function doSave() {
  saveStatus.value = 'saving';
  try {
    await api.put('/v1/user/dashboard-layout', { layout: layout.value });
    saveStatus.value = 'saved';
    clearTimeout(fadeTimer);
    fadeTimer = setTimeout(() => { saveStatus.value = 'idle'; }, 2000);
  } catch {
    saveStatus.value = 'error';
    clearTimeout(fadeTimer);
    fadeTimer = setTimeout(() => { saveStatus.value = 'idle'; }, 3000);
  }
}

watch(layout, () => { scheduleSave(); }, { deep: true });

// --- Actions ------------------------------------------------------------
function onLayoutUpdated() { scheduleSave(); }

function removeWidget(id) {
  layout.value = layout.value.filter(item => item.i !== id);
}

// Scan the grid top-to-bottom, left-to-right for the first open rectangle
// of size (w × h). Falls back to the bottom of the layout if nothing fits.
function findPosition(w, h) {
  const items = layout.value;
  if (!items.length) return { x: 0, y: 0 };

  const gridH = items.reduce((max, item) => Math.max(max, item.y + item.h), 0);

  function collides(cx, cy) {
    return items.some(item =>
      cx        < item.x + item.w &&
      cx + w    > item.x          &&
      cy        < item.y + item.h &&
      cy + h    > item.y
    );
  }

  for (let row = 0; row <= gridH; row++) {
    for (let col = 0; col <= 12 - w; col++) {
      if (!collides(col, row)) return { x: col, y: row };
    }
  }

  return { x: 0, y: gridH };
}

function addWidget(catalog) {
  const { w, h } = catalog.defaultSize;
  const { x, y } = findPosition(w, h);
  const id = `w-${Date.now()}`;
  layout.value.push({ i: id, x, y, w, h, type: catalog.type });
  showPicker.value = false;

  newWidgetId.value = id;
  clearTimeout(glowTimer);
  nextTick(() => {
    document.querySelector(`[data-widget-id="${id}"]`)
      ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  glowTimer = setTimeout(() => { newWidgetId.value = null; }, 3000);
}

onUnmounted(() => {
  clearTimeout(saveTimer);
  clearTimeout(fadeTimer);
  clearTimeout(glowTimer);
});

// --- Mount --------------------------------------------------------------
loadingLayout.value = true;
onMounted(async () => {
  try {
    const { data } = await api.get('/v1/user/dashboard-layout');
    layout.value = Array.isArray(data.layout) && data.layout.length
      ? data.layout
      : JSON.parse(JSON.stringify(DEFAULT_LAYOUT));
  } catch {
    layout.value = JSON.parse(JSON.stringify(DEFAULT_LAYOUT));
  } finally {
    loadingLayout.value = false;
    initialised = true;
  }
});
</script>

<style scoped>
.dashboard {
  max-width: 1500px;
  margin: 0 auto;
  padding: 28px 28px 48px;
}

/* Welcome hero */
.dash-hero {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background:
    radial-gradient(1100px 320px at 88% -45%, rgba(96,165,250,0.55), transparent 60%),
    linear-gradient(118deg, #0f2456 0%, #1d4ed8 52%, #1e40af 100%);
  box-shadow: 0 18px 40px -20px rgba(15,36,86,0.7);
  margin-bottom: 24px;
}
.hero-glow {
  position: absolute;
  inset: 0;
  background: radial-gradient(420px 220px at 10% 130%, rgba(29,78,216,0.5), transparent 70%);
  pointer-events: none;
}
.hero-inner {
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 18px;
  padding: 26px 30px;
}
.hero-text { min-width: 0; }
.hero-eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(237,233,254,0.82);
  margin-bottom: 8px;
}
.hero-greeting {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
  color: #fff;
  margin: 0;
  line-height: 1.15;
}
.hero-sub {
  font-size: 13.5px;
  color: rgba(237,233,254,0.78);
  margin: 8px 0 0;
  max-width: 460px;
  line-height: 1.5;
}
.hero-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.edit-hint {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 500;
  color: #fff;
  background: rgba(255,255,255,0.16);
  padding: 6px 11px;
  border-radius: 999px;
}

/* Hero action buttons */
.hbtn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 999px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.06s;
  white-space: nowrap;
  line-height: 1.2;
}
.hbtn:active { transform: translateY(1px); }
.hbtn-ghost {
  background: rgba(255,255,255,0.14);
  border-color: rgba(255,255,255,0.28);
  color: #fff;
}
.hbtn-ghost:hover { background: rgba(255,255,255,0.24); }
.hbtn-ghost--active {
  background: #fff;
  border-color: #fff;
  color: var(--primary-text);
}
.hbtn-solid {
  background: #fff;
  color: var(--primary-text);
  border-color: #fff;
  box-shadow: 0 8px 20px -8px rgba(0,0,0,0.4);
}
.hbtn-solid:hover { background: #f5f3ff; }

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 999px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.06s;
  white-space: nowrap;
  line-height: 1.2;
}
.btn:active:not(:disabled) { transform: translateY(1px); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-ghost {
  background: var(--surface);
  border-color: var(--border);
  color: var(--text-2);
}
.btn-ghost:hover:not(:disabled) {
  background: var(--primary-soft);
  border-color: var(--primary-soft);
  color: var(--primary-text);
}
.btn-ghost--active {
  background: var(--primary-soft) !important;
  border-color: var(--primary-soft) !important;
  color: var(--primary-text) !important;
}
.btn-primary {
  background: var(--primary);
  color: var(--primary-on);
  border-color: var(--primary);
  box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55);
}
.btn-primary:hover:not(:disabled) {
  background: var(--primary-hover);
  border-color: var(--primary-hover);
}
.save-status {
  font-size: 12px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 999px;
  white-space: nowrap;
}
.save-status--pending,
.save-status--saving {
  color: #fff;
  background: rgba(255, 255, 255, 0.16);
}
.save-status--saved {
  color: #fff;
  background: rgba(34, 197, 94, 0.92);
}
.save-status--error {
  color: #fff;
  background: rgba(239, 68, 68, 0.92);
}
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: var(--text-2);
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.12s, color 0.12s;
}
.btn-icon:hover { background: var(--primary-soft); color: var(--primary-text); }

/* Canvas states */
.canvas-loading,
.empty-canvas {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 480px;
  gap: 16px;
  color: var(--text-3);
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}
.empty-icon { color: var(--primary); opacity: 0.6; }
.empty-canvas p { font-size: 14.5px; margin: 0; color: var(--text-2); }

/* Grid canvas */
.grid-canvas { width: 100%; }

/* Widget shell — fills the vgl-item */
.widget-shell {
  position: relative;
  height: 100%;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-soft);
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.18s;
}
.widget-shell:hover {
  box-shadow: var(--shadow-md);
}
.widget-shell--edit {
  cursor: move;
  border-color: var(--primary);
  box-shadow:
    0 0 0 3px var(--primary-soft),
    var(--shadow-md);
}
.widget-shell--new {
  animation: widget-glow 3s ease-out forwards;
}
@keyframes widget-glow {
  0%   { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-soft), 0 0 24px 6px rgba(29,78,216,0.35), var(--shadow-md); }
  40%  { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft), 0 0 18px 4px rgba(29,78,216,0.25), var(--shadow-md); }
  100% { border-color: var(--border-soft); box-shadow: var(--shadow-sm); }
}
.widget-remove {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 10;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--danger);
  color: #fff;
  border: 2px solid var(--surface);
  border-radius: 50%;
  cursor: pointer;
  padding: 0;
  box-shadow: var(--shadow-sm);
  transition: transform 0.12s, background 0.12s;
}
.widget-remove:hover { background: #dc2626; transform: scale(1.08); }
.widget-unknown {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--text-3);
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-box {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 480px;
  max-height: calc(100vh - 48px);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  border: 1px solid var(--border-soft);
  display: flex;
  flex-direction: column;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 22px 16px;
  border-bottom: 1px solid var(--border-soft);
  flex-shrink: 0;
}
.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.widget-catalog {
  padding: 16px 22px 22px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
}
.widget-catalog::-webkit-scrollbar { width: 6px; }
.widget-catalog::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
.catalog-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, transform 0.08s;
  background: transparent;
  text-align: left;
  width: 100%;
  font-family: inherit;
}
.catalog-card:hover {
  background: var(--primary-soft);
  border-color: var(--primary);
  transform: translateX(2px);
}
.catalog-icon {
  color: var(--primary);
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  padding: 8px;
  background: var(--primary-soft);
  border-radius: 10px;
  box-sizing: content-box;
}
.catalog-card:hover .catalog-icon { background: #fff; }
.catalog-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-1);
}
.catalog-desc {
  font-size: 12px;
  color: var(--text-3);
  margin-top: 3px;
}

/* Responsive */
@media (max-width: 640px) {
  .dashboard { padding: 16px 14px 32px; }
  .hero-inner { flex-direction: column; align-items: flex-start; padding: 22px 20px; }
  .hero-actions { width: 100%; }
  .hero-greeting { font-size: 23px; }
  .hbtn, .btn { padding: 8px 14px; font-size: 12.5px; }
}
</style>

<!-- Override grid-layout-plus placeholder colour to match new accent -->
<style>
.vgl-item--placeholder {
  --vgl-placeholder-bg: #1d4ed8;
  --vgl-placeholder-opacity: 14%;
  border-radius: 14px;
}
</style>
