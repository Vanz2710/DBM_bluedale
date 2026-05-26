<template>
  <div class="dashboard">
    <!-- Header -->
    <div class="dash-header">
      <div class="dash-header-left">
        <h1 class="dash-title">Dashboard</h1>
        <span v-if="editMode" class="edit-hint">
          <Move :size="12" />
          Drag &amp; resize to customise
        </span>
      </div>
      <div class="dash-header-actions">
        <button
          class="btn btn-ghost"
          :class="{ 'btn-ghost--active': editMode }"
          @click="editMode = !editMode"
          :title="editMode ? 'Exit edit mode' : 'Rearrange widgets'"
        >
          <LayoutGrid :size="14" />
          {{ editMode ? 'Done' : 'Edit Layout' }}
        </button>
        <span
          v-if="saveStatus !== 'idle'"
          class="save-status"
          :class="`save-status--${saveStatus}`"
        >{{ saveStatus === 'saved' ? 'Saved' : saveStatus === 'error' ? 'Save failed' : 'Saving…' }}</span>
        <button class="btn btn-primary" @click="showPicker = true">
          <Plus :size="14" /> Add Widget
        </button>
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
          <div class="widget-shell" :class="{ 'widget-shell--edit': editMode }">
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
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { GridLayout, GridItem } from 'grid-layout-plus';
import { Plus, X, LayoutGrid, Move, TrendingUp, Users, BarChart2, ClipboardList, Target, CalendarCheck } from 'lucide-vue-next';
import api from '../api.js';
import LoadingSpinner from './LoadingSpinner.vue';

import RevenueChartWidget       from './widgets/RevenueChartWidget.vue';
import RecentContactsWidget     from './widgets/RecentContactsWidget.vue';
import KpiStatsWidget           from './widgets/KpiStatsWidget.vue';
import TasksWidget              from './widgets/TasksWidget.vue';
import KpiTargetWidget          from './widgets/KpiTargetWidget.vue';
import UpcomingFollowUpsWidget  from './widgets/UpcomingFollowUpsWidget.vue';

// --- Registry -----------------------------------------------------------
const widgetComponents = {
  RevenueChartWidget,
  RecentContactsWidget,
  KpiStatsWidget,
  TasksWidget,
  KpiTargetWidget,
  UpcomingFollowUpsWidget,
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
];

const DEFAULT_LAYOUT = [
  { i: 'w-1', x: 0, y: 0, w: 8, h: 5, type: 'RevenueChartWidget'  },
  { i: 'w-2', x: 8, y: 0, w: 4, h: 5, type: 'RecentContactsWidget' },
  { i: 'w-3', x: 0, y: 5, w: 4, h: 4, type: 'KpiStatsWidget'      },
  { i: 'w-4', x: 4, y: 5, w: 8, h: 4, type: 'TasksWidget'         },
];

const ROW_HEIGHT = 80;

// --- State --------------------------------------------------------------
const layout        = ref([]);
const editMode      = ref(false);
const showPicker    = ref(false);
const loadingLayout = ref(false);
const saveStatus    = ref('idle'); // 'idle' | 'pending' | 'saving' | 'saved' | 'error'

let initialised = false;
let saveTimer   = null;
let fadeTimer   = null;

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

function addWidget(catalog) {
  const maxY = layout.value.reduce((acc, item) => Math.max(acc, item.y + item.h), 0);
  layout.value.push({
    i:    `w-${Date.now()}`,
    x:    0,
    y:    maxY,
    w:    catalog.defaultSize.w,
    h:    catalog.defaultSize.h,
    type: catalog.type,
  });
  showPicker.value = false;
}

onUnmounted(() => {
  clearTimeout(saveTimer);
  clearTimeout(fadeTimer);
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

/* Header */
.dash-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}
.dash-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.dash-title {
  font-size: 28px;
  font-weight: 800;
  color: var(--text-1);
  margin: 0;
  letter-spacing: -0.5px;
}
.edit-hint {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 500;
  color: var(--primary-text);
  background: var(--primary-soft);
  padding: 5px 11px;
  border-radius: 999px;
}
.dash-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

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
  box-shadow: 0 6px 18px -6px rgba(124,58,237,0.55);
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
  color: var(--text-3);
  background: var(--surface-2, #f1f5f9);
}
.save-status--saved {
  color: var(--success);
  background: var(--success-soft);
}
.save-status--error {
  color: var(--danger);
  background: var(--danger-soft);
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
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  border: 1px solid var(--border-soft);
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 22px 16px;
  border-bottom: 1px solid var(--border-soft);
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
}
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
  .dash-header { flex-direction: column; align-items: flex-start; }
  .dash-header-actions { flex-wrap: wrap; }
  .dash-title { font-size: 22px; }
  .btn { padding: 8px 14px; font-size: 12.5px; }
}
</style>

<!-- Override grid-layout-plus placeholder colour to match new accent -->
<style>
.vgl-item--placeholder {
  --vgl-placeholder-bg: #7c3aed;
  --vgl-placeholder-opacity: 14%;
  border-radius: 14px;
}
</style>
