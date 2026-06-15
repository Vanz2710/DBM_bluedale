<template>
  <div class="cpkr" ref="rootEl">
    <button class="cpkr-trigger" type="button" @click="toggle">
      <span class="cpkr-label">{{ triggerLabel }}</span>
      <span class="cpkr-icon">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </span>
    </button>

    <Teleport to="body">
      <div v-if="open" class="cpkr-popup" :style="popupStyle" @click.stop>

        <div class="cpkr-head">
          <button class="cpkr-nav" type="button" @click="shiftMonth(-1)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
          </button>
          <span class="cpkr-month-label">{{ monthYearLabel }}</span>
          <button class="cpkr-nav" type="button" @click="shiftMonth(1)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </button>
        </div>

        <div class="cpkr-grid">
          <span v-for="d in DOW" :key="d" class="cpkr-dow">{{ d }}</span>
          <span v-for="i in leadBlanks" :key="'b' + i" class="cpkr-blank" />
          <button
            v-for="d in daysCount"
            :key="d"
            type="button"
            class="cpkr-day"
            :class="{
              'is-sel':    isSelected(d),
              'is-today':  isToday(d),
              'is-marked': isMarked(d),
            }"
            @click="pick(d)"
          >
            {{ d }}
            <span v-if="isMarked(d)" class="cpkr-dot" />
          </button>
        </div>

        <div v-if="loadingDates" class="cpkr-loading-bar" />

        <div class="cpkr-legend">
          <span class="cpkr-legend-dot" />
          <span class="cpkr-legend-text">has to-dos</span>
        </div>

      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  modelValue:   { type: String,  required: true },
  markedDates:  { type: Array,   default: () => [] },
  loadingDates: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'month-change']);

const DOW = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

const open    = ref(false);
const rootEl  = ref(null);
const popupStyle = ref({});

const [initY, initM] = props.modelValue.split('-').map(Number);
const viewYear  = ref(initY);
const viewMonth = ref(initM);

const triggerLabel = computed(() => {
  const d = new Date(props.modelValue + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
});

const monthYearLabel = computed(() =>
  new Date(viewYear.value, viewMonth.value - 1, 1)
    .toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
);

const daysCount = computed(() =>
  new Date(viewYear.value, viewMonth.value, 0).getDate()
);

const leadBlanks = computed(() => {
  const dow = new Date(viewYear.value, viewMonth.value - 1, 1).getDay();
  return (dow + 6) % 7; // Mon-start: Mon=0 … Sun=6
});

const markedSet = computed(() => new Set(props.markedDates));

function pad(n) { return String(n).padStart(2, '0'); }
function dateStr(d) { return `${viewYear.value}-${pad(viewMonth.value)}-${pad(d)}`; }

function isSelected(d) { return dateStr(d) === props.modelValue; }
function isToday(d) {
  const t = new Date();
  return viewYear.value === t.getFullYear() &&
         viewMonth.value === t.getMonth() + 1 &&
         d === t.getDate();
}
function isMarked(d) { return markedSet.value.has(dateStr(d)); }

function pick(d) {
  emit('update:modelValue', dateStr(d));
  open.value = false;
}

function shiftMonth(n) {
  let y = viewYear.value;
  let m = viewMonth.value + n;
  if (m > 12) { m = 1;  y++; }
  if (m < 1)  { m = 12; y--; }
  viewYear.value  = y;
  viewMonth.value = m;
  emit('month-change', { year: y, month: m });
}

async function toggle() {
  if (open.value) { open.value = false; return; }
  // Sync view to the currently selected month
  const [y, m] = props.modelValue.split('-').map(Number);
  if (viewYear.value !== y || viewMonth.value !== m) {
    viewYear.value  = y;
    viewMonth.value = m;
    emit('month-change', { year: y, month: m });
  }
  open.value = true;
  await nextTick();
  positionPopup();
}

function positionPopup() {
  if (!rootEl.value) return;
  const rect = rootEl.value.getBoundingClientRect();
  const popW = 272;
  let left = rect.left;
  if (left + popW > window.innerWidth - 8) left = window.innerWidth - popW - 8;
  popupStyle.value = {
    position: 'fixed',
    top:  `${rect.bottom + 6}px`,
    left: `${Math.max(8, left)}px`,
    zIndex: 9999,
  };
}

// Keep view in sync when parent changes selected date externally
watch(() => props.modelValue, (val) => {
  const [y, m] = val.split('-').map(Number);
  viewYear.value  = y;
  viewMonth.value = m;
});

function onOutsideClick(e) {
  if (open.value && rootEl.value && !rootEl.value.contains(e.target)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('click', onOutsideClick, true));
onUnmounted(() => document.removeEventListener('click', onOutsideClick, true));
</script>

<style scoped>
.cpkr { position: relative; display: inline-flex; }

/* ── Trigger ─────────────────────────────────────────────────────────────── */
.cpkr-trigger {
  display: inline-flex; align-items: center; gap: 7px;
  height: 36px; padding: 0 14px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
  white-space: nowrap;
}
.cpkr-trigger:hover {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.cpkr-icon { display: flex; color: var(--text-3); }

/* ── Popup ───────────────────────────────────────────────────────────────── */
.cpkr-popup {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.16);
  padding: 14px;
  width: 272px;
  user-select: none;
}

/* ── Month nav ───────────────────────────────────────────────────────────── */
.cpkr-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px;
}
.cpkr-nav {
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border: none; border-radius: var(--radius-sm);
  background: none; cursor: pointer; color: var(--text-2);
  transition: background 0.12s, color 0.12s;
}
.cpkr-nav:hover { background: var(--surface-2); color: var(--text-1); }
.cpkr-month-label { font-size: 13px; font-weight: 700; color: var(--text-1); }

/* ── Day grid ────────────────────────────────────────────────────────────── */
.cpkr-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}
.cpkr-dow {
  text-align: center;
  font-size: 9px; font-weight: 700;
  color: var(--text-3); text-transform: uppercase; letter-spacing: 0.4px;
  padding: 4px 0 6px;
}
.cpkr-blank { /* spacer */ }

.cpkr-day {
  position: relative;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  height: 34px; gap: 2px;
  font-size: 12.5px; font-weight: 500; color: var(--text-1);
  background: none; border: none; border-radius: var(--radius-sm);
  cursor: pointer; transition: background 0.12s;
  line-height: 1;
}
.cpkr-day:hover:not(.is-sel) { background: var(--surface-2); }

.cpkr-day.is-today {
  color: var(--primary);
  font-weight: 700;
}
.cpkr-day.is-sel {
  background: var(--primary) !important;
  color: var(--primary-on) !important;
  font-weight: 700;
}
.cpkr-day.is-marked { font-weight: 700; }

/* ── Dot indicator ───────────────────────────────────────────────────────── */
.cpkr-dot {
  display: block;
  width: 4px; height: 4px; border-radius: 50%;
  background: var(--primary);
  flex-shrink: 0;
}
.cpkr-day.is-sel .cpkr-dot { background: rgba(255, 255, 255, 0.65); }
.cpkr-day.is-today:not(.is-sel) .cpkr-dot { background: var(--primary); }

/* ── Loading bar ─────────────────────────────────────────────────────────── */
.cpkr-loading-bar {
  height: 2px; margin-top: 10px; border-radius: 2px;
  background: linear-gradient(90deg, transparent 0%, var(--primary) 50%, transparent 100%);
  background-size: 200% 100%;
  animation: cpkr-shimmer 1s linear infinite;
}
@keyframes cpkr-shimmer {
  from { background-position: 200% 0; }
  to   { background-position: -200% 0; }
}

/* ── Legend ──────────────────────────────────────────────────────────────── */
.cpkr-legend {
  display: flex; align-items: center; gap: 5px;
  margin-top: 10px; padding-top: 10px;
  border-top: 1px solid var(--border-soft);
}
.cpkr-legend-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--primary); flex-shrink: 0;
}
.cpkr-legend-text { font-size: 10.5px; color: var(--text-3); font-weight: 500; }
</style>
