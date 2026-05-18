<template>
  <div class="dashboard">
    <!-- Header -->
    <div class="dash-header">
      <div class="dash-header-left">
        <h1 class="dash-title">My Dashboard</h1>
        <span v-if="editMode" class="edit-hint">
          <Move :size="11" />
          Drag &amp; resize to customise
        </span>
      </div>
      <div class="dash-header-actions">
        <button class="btn btn-ghost" @click="showPicker = true">
          <Plus :size="13" /> Add Widget
        </button>
        <button
          class="btn"
          :class="editMode ? 'btn-primary' : 'btn-ghost'"
          @click="editMode = !editMode"
        >
          <LayoutGrid :size="13" />
          {{ editMode ? 'Done' : 'Edit Layout' }}
        </button>
        <button
          class="btn btn-save"
          :class="{ dirty }"
          :disabled="saving"
          @click="saveLayout"
        >
          <Save :size="13" />
          {{ saving ? 'Saving…' : 'Save Layout' }}
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
import { ref, watch, onMounted } from 'vue';
import { GridLayout, GridItem } from 'grid-layout-plus';
import { Plus, Save, X, LayoutGrid, Move, TrendingUp, Users, BarChart2, ClipboardList } from 'lucide-vue-next';
import api from '../api.js';
import LoadingSpinner from './LoadingSpinner.vue';

import RevenueChartWidget  from './widgets/RevenueChartWidget.vue';
import RecentContactsWidget from './widgets/RecentContactsWidget.vue';
import KpiStatsWidget      from './widgets/KpiStatsWidget.vue';
import TasksWidget         from './widgets/TasksWidget.vue';

// --- Registry -----------------------------------------------------------
const widgetComponents = {
  RevenueChartWidget,
  RecentContactsWidget,
  KpiStatsWidget,
  TasksWidget,
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
];

const DEFAULT_LAYOUT = [
  { i: 'w-1', x: 0, y: 0, w: 8, h: 5, type: 'RevenueChartWidget'  },
  { i: 'w-2', x: 8, y: 0, w: 4, h: 5, type: 'RecentContactsWidget' },
  { i: 'w-3', x: 0, y: 5, w: 4, h: 4, type: 'KpiStatsWidget'      },
  { i: 'w-4', x: 4, y: 5, w: 8, h: 4, type: 'TasksWidget'         },
];

const ROW_HEIGHT = 80;

// --- State --------------------------------------------------------------
const layout      = ref([]);
const editMode    = ref(false);
const showPicker  = ref(false);
const saving      = ref(false);
const loadingLayout = ref(true);
const dirty       = ref(false);

// Mark dirty when layout changes (but not on the initial load)
let initialised = false;
watch(layout, () => {
  if (initialised) dirty.value = true;
}, { deep: true });

// --- Actions ------------------------------------------------------------
function onLayoutUpdated() {
  if (initialised) dirty.value = true;
}

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

async function saveLayout() {
  saving.value = true;
  try {
    await api.put('/v1/user/dashboard-layout', { layout: layout.value });
    dirty.value = false;
  } catch (e) {
    console.error('Failed to save layout:', e);
  } finally {
    saving.value = false;
  }
}

// --- Mount --------------------------------------------------------------
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
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px 20px 40px;
}

/* Header */
.dash-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px;
}
.dash-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.dash-title {
  font-size: 21px;
  font-weight: 700;
  color: var(--text-1, #1e293b);
  margin: 0;
}
.edit-hint {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--text-3, #94a3b8);
  background: var(--surface, #f8fafc);
  border: 1px solid var(--border, #e2e8f0);
  padding: 3px 9px;
  border-radius: 20px;
}
.dash-header-actions {
  display: flex;
  align-items: center;
  gap: 7px;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 13px;
  font-size: 12.5px;
  font-weight: 500;
  border-radius: 6px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  white-space: nowrap;
  line-height: 1.4;
}
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-ghost {
  background: transparent;
  border-color: var(--border, #e2e8f0);
  color: var(--text-2, #64748b);
}
.btn-ghost:hover:not(:disabled) { background: var(--surface, #f8fafc); }
.btn-primary {
  background: #3b82f6;
  color: #fff;
  border-color: #3b82f6;
}
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-save {
  background: transparent;
  border-color: var(--border, #e2e8f0);
  color: var(--text-2, #64748b);
}
.btn-save.dirty {
  background: #10b981;
  border-color: #10b981;
  color: #fff;
}
.btn-save.dirty:hover:not(:disabled) { background: #059669; }
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: var(--text-2, #64748b);
  cursor: pointer;
  border-radius: 5px;
}
.btn-icon:hover { background: var(--surface, #f8fafc); }

/* Canvas states */
.canvas-loading,
.empty-canvas {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 420px;
  gap: 14px;
  color: var(--text-3, #94a3b8);
}
.empty-icon { color: #cbd5e1; }
.empty-canvas p { font-size: 14px; margin: 0; }

/* Grid canvas — let grid-layout-plus control sizing */
.grid-canvas { width: 100%; }

/* Widget shell — fills the vgl-item */
.widget-shell {
  position: relative;
  height: 100%;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07), 0 0 0 1px rgba(0, 0, 0, 0.04);
  transition: box-shadow 0.15s;
}
.widget-shell--edit {
  cursor: move;
  box-shadow:
    0 0 0 2px #3b82f6,
    0 4px 12px rgba(59, 130, 246, 0.18);
}
.widget-remove {
  position: absolute;
  top: 7px;
  right: 7px;
  z-index: 10;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  padding: 0;
  transition: background 0.12s;
}
.widget-remove:hover { background: #dc2626; }
.widget-unknown {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #94a3b8;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-box {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 14px;
  border-bottom: 1px solid #f1f5f9;
}
.modal-title {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
}
.widget-catalog {
  padding: 14px 20px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.catalog-card {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 13px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  background: transparent;
  text-align: left;
  width: 100%;
}
.catalog-card:hover {
  background: #f8fafc;
  border-color: #3b82f6;
}
.catalog-icon { color: #3b82f6; flex-shrink: 0; }
.catalog-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #1e293b;
}
.catalog-desc {
  font-size: 11.5px;
  color: #94a3b8;
  margin-top: 2px;
}

/* Responsive */
@media (max-width: 640px) {
  .dashboard { padding: 14px 10px 32px; }
  .dash-header { flex-direction: column; align-items: flex-start; }
  .dash-header-actions { flex-wrap: wrap; }
}
</style>

<!-- Override grid-layout-plus placeholder colour to a subtle blue -->
<style>
.vgl-item--placeholder {
  --vgl-placeholder-bg: #3b82f6;
  --vgl-placeholder-opacity: 12%;
  border-radius: 10px;
}
</style>
