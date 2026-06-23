<template>
  <div class="dtp" ref="rootEl">
    <button class="dtp-trigger" :class="{ 'is-set': !!modelValue }" type="button" @click="toggle">
      <svg class="dtp-cal-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      <span :class="['dtp-lbl', { 'dtp-ph': !modelValue }]">{{ displayLabel }}</span>
      <button v-if="modelValue" class="dtp-x" type="button" @click.stop="clearVal">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </button>

    <Teleport to="body">
      <div v-if="open" class="dtp-popup" :style="popupStyle" ref="popupEl" @click.stop>

        <!-- Month nav -->
        <div class="dtp-head">
          <button class="dtp-nav" type="button" @click="shiftMonth(-1)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <span class="dtp-month-lbl">{{ monthYearLabel }}</span>
          <button class="dtp-nav" type="button" @click="shiftMonth(1)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>

        <!-- Day grid -->
        <div class="dtp-grid">
          <span v-for="d in DOW" :key="d" class="dtp-dow">{{ d }}</span>
          <span v-for="i in leadBlanks" :key="'b'+i" />
          <button
            v-for="d in daysCount" :key="d" type="button"
            :class="['dtp-day', { 'is-sel': isSelected(d), 'is-today': isToday(d) }]"
            @click="pickDate(d)"
          >{{ d }}</button>
        </div>

        <!-- Time row -->
        <div v-if="withTime" class="dtp-time-row">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <select v-model="selHour" class="dtp-sel" @change="commitTime">
            <option v-for="h in HOURS" :key="h" :value="h">{{ h }}</option>
          </select>
          <span class="dtp-colon">:</span>
          <select v-model="selMinute" class="dtp-sel" @change="commitTime">
            <option v-for="m in MINUTES" :key="m" :value="m">{{ m }}</option>
          </select>
        </div>

        <!-- Footer -->
        <div class="dtp-foot">
          <button class="dtp-btn-clear" type="button" @click="clearAndClose">Clear</button>
          <button class="dtp-btn-today" type="button" @click="pickToday">Today</button>
          <button v-if="withTime && modelValue" class="dtp-btn-done" type="button" @click="open = false">Done</button>
        </div>

      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  modelValue:  { type: String,  default: '' },
  withTime:    { type: Boolean, default: false },
  placeholder: { type: String,  default: 'Select date' },
});
const emit = defineEmits(['update:modelValue']);

const DOW     = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const HOURS   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const MINUTES = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];

const open       = ref(false);
const rootEl     = ref(null);
const popupEl    = ref(null);
const popupStyle = ref({});

const viewYear  = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth() + 1);
const selHour   = ref('09');
const selMinute = ref('00');

function pad(n) { return String(n).padStart(2, '0'); }

function parseValue(val) {
  if (!val) return null;
  const d = new Date(val.replace(' ', 'T'));
  return isNaN(d.getTime()) ? null : d;
}

function syncFromValue() {
  const d = parseValue(props.modelValue);
  if (d) {
    viewYear.value  = d.getFullYear();
    viewMonth.value = d.getMonth() + 1;
    if (props.withTime) {
      selHour.value   = pad(d.getHours());
      selMinute.value = pad(d.getMinutes());
    }
  }
}

const displayLabel = computed(() => {
  if (!props.modelValue) return props.placeholder;
  const d = parseValue(props.modelValue);
  if (!d) return props.placeholder;
  const datePart = d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  return props.withTime ? `${datePart}, ${pad(d.getHours())}:${pad(d.getMinutes())}` : datePart;
});

const monthYearLabel = computed(() =>
  new Date(viewYear.value, viewMonth.value - 1, 1)
    .toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
);

const daysCount  = computed(() => new Date(viewYear.value, viewMonth.value, 0).getDate());
const leadBlanks = computed(() => {
  const dow = new Date(viewYear.value, viewMonth.value - 1, 1).getDay();
  return (dow + 6) % 7;
});

function dateStrFor(d) {
  return `${viewYear.value}-${pad(viewMonth.value)}-${pad(d)}`;
}

function isSelected(d) {
  return !!props.modelValue && dateStrFor(d) === props.modelValue.slice(0, 10);
}

function isToday(d) {
  const t = new Date();
  return viewYear.value === t.getFullYear() && viewMonth.value === t.getMonth() + 1 && d === t.getDate();
}

function shiftMonth(n) {
  let m = viewMonth.value + n, y = viewYear.value;
  if (m > 12) { m = 1; y++; }
  if (m < 1)  { m = 12; y--; }
  viewMonth.value = m; viewYear.value = y;
}

function pickDate(d) {
  const datePart = dateStrFor(d);
  if (props.withTime) {
    emit('update:modelValue', `${datePart}T${selHour.value}:${selMinute.value}`);
    // Stay open to allow time adjustment
  } else {
    emit('update:modelValue', datePart);
    open.value = false;
  }
}

function commitTime() {
  if (!props.modelValue) return;
  emit('update:modelValue', `${props.modelValue.slice(0, 10)}T${selHour.value}:${selMinute.value}`);
}

function pickToday() {
  const t = new Date();
  viewYear.value  = t.getFullYear();
  viewMonth.value = t.getMonth() + 1;
  if (props.withTime) {
    selHour.value   = pad(t.getHours());
    selMinute.value = MINUTES.reduce((closest, m) =>
      Math.abs(parseInt(m) - t.getMinutes()) < Math.abs(parseInt(closest) - t.getMinutes()) ? m : closest
    );
    emit('update:modelValue', `${dateStrFor(t.getDate())}T${selHour.value}:${selMinute.value}`);
  } else {
    emit('update:modelValue', dateStrFor(t.getDate()));
    open.value = false;
  }
}

function clearVal()       { emit('update:modelValue', ''); }
function clearAndClose()  { emit('update:modelValue', ''); open.value = false; }

async function toggle() {
  if (open.value) { open.value = false; return; }
  syncFromValue();
  open.value = true;
  await nextTick();
  positionPopup();
}

function positionPopup() {
  if (!rootEl.value) return;
  const rect  = rootEl.value.getBoundingClientRect();
  const vh    = window.innerHeight;
  const vw    = window.innerWidth;
  const popW  = 272;
  const popH  = popupEl.value?.offsetHeight ?? 320;
  const GAP   = 6;

  let left = rect.left;
  if (left + popW > vw - 8) left = vw - popW - 8;

  // Flip above trigger when insufficient space below
  const spaceBelow = vh - rect.bottom - GAP;
  const top = spaceBelow >= popH
    ? rect.bottom + GAP
    : Math.max(8, rect.top - popH - GAP);

  popupStyle.value = {
    position: 'fixed',
    top:  `${top}px`,
    left: `${Math.max(8, left)}px`,
    zIndex: 9999,
  };
}

watch(() => props.modelValue, syncFromValue);

function onOutsideClick(e) {
  if (open.value && rootEl.value && !rootEl.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('click', onOutsideClick, true));
onUnmounted(() => document.removeEventListener('click', onOutsideClick, true));
</script>

<style scoped>
.dtp { position: relative; display: block; }

/* ── Trigger ── */
.dtp-trigger {
  display: flex; align-items: center; gap: 8px;
  width: 100%; padding: 8px 11px;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface-2); color: var(--text-1);
  font-size: 13px; cursor: pointer; text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.dtp-trigger:hover,
.dtp-trigger:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); outline: none; }
.dtp-cal-ico { flex-shrink: 0; color: var(--text-3); }
.dtp-lbl { flex: 1; font-weight: 500; }
.dtp-ph  { color: var(--text-3); font-weight: 400; }
.dtp-x {
  display: flex; align-items: center; justify-content: center;
  width: 18px; height: 18px; border-radius: 50%;
  border: none; background: var(--border); color: var(--text-2);
  cursor: pointer; flex-shrink: 0; padding: 0;
  transition: background 0.12s;
}
.dtp-x:hover { background: #fecaca; color: #dc2626; }

/* ── Popup ── */
.dtp-popup {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius-lg); box-shadow: 0 8px 32px rgba(0,0,0,0.14);
  padding: 14px; width: 272px; user-select: none;
}

/* ── Month nav ── */
.dtp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.dtp-nav {
  width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
  border: none; border-radius: var(--radius-sm); background: none;
  cursor: pointer; color: var(--text-2); transition: background 0.12s, color 0.12s;
}
.dtp-nav:hover { background: var(--surface-2); color: var(--text-1); }
.dtp-month-lbl { font-size: 13px; font-weight: 700; color: var(--text-1); }

/* ── Day grid ── */
.dtp-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.dtp-dow {
  text-align: center; font-size: 9px; font-weight: 700;
  color: var(--text-3); text-transform: uppercase; letter-spacing: 0.4px;
  padding: 4px 0 6px;
}
.dtp-day {
  display: flex; align-items: center; justify-content: center;
  height: 32px; font-size: 12.5px; font-weight: 500; color: var(--text-1);
  background: none; border: none; border-radius: var(--radius-sm);
  cursor: pointer; transition: background 0.1s;
}
.dtp-day:hover:not(.is-sel) { background: var(--surface-2); }
.dtp-day.is-today { color: var(--primary); font-weight: 700; }
.dtp-day.is-sel { background: var(--primary) !important; color: #fff !important; font-weight: 700; }

/* ── Time row ── */
.dtp-time-row {
  display: flex; align-items: center; gap: 6px;
  margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);
  color: var(--text-3);
}
.dtp-sel {
  flex: 1; padding: 5px 6px; border: 1px solid var(--border);
  border-radius: var(--radius-sm); background: var(--surface-2);
  color: var(--text-1); font-size: 13px; font-weight: 600;
  text-align: center; cursor: pointer;
}
.dtp-sel:focus { border-color: var(--primary); outline: none; }
.dtp-colon { font-size: 14px; font-weight: 700; color: var(--text-2); }

/* ── Footer ── */
.dtp-foot {
  display: flex; gap: 6px; margin-top: 12px; padding-top: 10px;
  border-top: 1px solid var(--border);
}
.dtp-btn-clear, .dtp-btn-today, .dtp-btn-done {
  flex: 1; padding: 6px 0; border-radius: var(--radius-sm);
  font-size: 12px; font-weight: 600; cursor: pointer;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); transition: background 0.12s, color 0.12s;
}
.dtp-btn-clear:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
.dtp-btn-today:hover { background: var(--surface-2); color: var(--text-1); }
.dtp-btn-done {
  background: var(--primary); border-color: var(--primary); color: #fff;
}
.dtp-btn-done:hover { opacity: .88; }
</style>
