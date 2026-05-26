<template>
  <div class="page">
    <header class="page-header">
      <div>
        <p class="eyebrow">Social Media Planner</p>
        <h1>Posting Calendar</h1>
        <p>Schedule FB, IG, TikTok, and content reminders for the social media team.</p>
      </div>
      <div class="month-control">
        <button type="button" @click="changeMonth(-1)">&lt;</button>
        <strong>{{ monthLabel }}</strong>
        <button type="button" @click="changeMonth(1)">&gt;</button>
      </div>
    </header>

    <section class="entry-panel">
      <div class="field">
        <label>Title</label>
        <input v-model="form.title" placeholder="Posting FB and IG">
      </div>
      <div class="field">
        <label>Platform</label>
        <select v-model="form.platform">
          <option v-for="platform in platforms" :key="platform" :value="platform">{{ platform }}</option>
        </select>
      </div>
      <div class="field">
        <label>Client / Company</label>
        <input v-model="form.client" placeholder="Optional">
      </div>
      <div class="field date-field">
        <label>Date</label>
        <input v-model="form.date" type="date">
      </div>
      <div class="field time-field">
        <label>Time</label>
        <input v-model="form.time" type="time">
      </div>
      <div class="field">
        <label>Status</label>
        <select v-model="form.status">
          <option value="planned">Planned</option>
          <option value="design">Design</option>
          <option value="approval">Approval</option>
          <option value="scheduled">Scheduled</option>
          <option value="posted">Posted</option>
        </select>
      </div>
      <button class="btn-add" :disabled="!canAdd" @click="saveReminder">
        {{ editingId ? 'Update Reminder' : '+ Add Reminder' }}
      </button>
    </section>

    <div class="toolbar">
      <div class="field">
        <label>Search</label>
        <input v-model="search" placeholder="Title, client, platform">
      </div>
      <div class="field">
        <label>Platform Filter</label>
        <select v-model="platformFilter">
          <option value="">All platforms</option>
          <option v-for="platform in platforms" :key="platform" :value="platform">{{ platform }}</option>
        </select>
      </div>
      <button class="btn-clear" @click="clearForm">Clear Form</button>
    </div>

    <section class="summary-grid">
      <div v-for="item in summaryCards" :key="item.label" class="summary-card">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </div>
    </section>

    <section class="calendar-card">
      <div class="calendar-head">
        <span v-for="day in weekDays" :key="day">{{ day }}</span>
      </div>
      <div class="calendar-grid">
        <button
          v-for="day in calendarDays"
          :key="day.key"
          type="button"
          class="day-cell"
          :class="{ muted: !day.inMonth, today: day.date === todayDate }"
          @click="form.date = day.date"
        >
          <span class="day-number">{{ day.day }}</span>
          <div class="event-stack">
            <div
              v-for="item in remindersForDate(day.date)"
              :key="item.id"
              class="event-pill"
              :class="[`platform-${item.platform.toLowerCase()}`, `status-${item.status}`]"
              @click.stop="editReminder(item)"
            >
              <strong>{{ item.platform }}</strong>
              <span>{{ item.time || '--:--' }} {{ item.title }}</span>
            </div>
          </div>
        </button>
      </div>
    </section>

    <section class="list-card">
      <div class="list-head">
        <h2>Upcoming Reminders</h2>
        <span>{{ filteredReminders.length }} task(s)</span>
      </div>
      <div class="reminder-list">
        <div v-if="filteredReminders.length === 0" class="empty-state">No posting reminders for this filter.</div>
        <article v-for="item in filteredReminders" :key="item.id" class="reminder-row">
          <div class="platform-mark" :class="`platform-${item.platform.toLowerCase()}`">{{ item.platformShort }}</div>
          <div>
            <strong>{{ item.title }}</strong>
            <p>{{ item.client || 'No client' }} - {{ formatDate(item.date) }} {{ item.time || '' }}</p>
          </div>
          <span class="status-badge" :class="`status-${item.status}`">{{ statusLabel(item.status) }}</span>
          <button type="button" class="btn-edit" @click="editReminder(item)">Edit</button>
          <button type="button" class="btn-delete" @click="deleteReminder(item)">Delete</button>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const storageKey = 'posting_calendar_reminders';
const platforms = ['FB', 'IG', 'TikTok', 'LinkedIn', 'Website'];
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const currentDate = ref(new Date());
const reminders = ref([]);
const search = ref('');
const platformFilter = ref('');
const editingId = ref(null);
const todayDate = new Date().toISOString().slice(0, 10);

const form = ref({
  title: '',
  platform: 'FB',
  client: '',
  date: todayDate,
  time: '10:00',
  status: 'planned',
});

const canAdd = computed(() => form.value.title.trim() && form.value.date);
const monthLabel = computed(() => currentDate.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }));
const monthStart = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1));

const filteredReminders = computed(() => {
  const term = search.value.trim().toLowerCase();
  return reminders.value
    .filter((item) => !platformFilter.value || item.platform === platformFilter.value)
    .filter((item) => {
      if (!term) return true;
      return [item.title, item.client, item.platform, item.status].some((value) => String(value || '').toLowerCase().includes(term));
    })
    .sort((a, b) => `${a.date} ${a.time}`.localeCompare(`${b.date} ${b.time}`));
});

const summaryCards = computed(() => [
  { label: 'This Month', value: reminders.value.filter((item) => item.date.startsWith(monthKey.value)).length },
  { label: 'Scheduled', value: reminders.value.filter((item) => item.status === 'scheduled').length },
  { label: 'Needs Approval', value: reminders.value.filter((item) => item.status === 'approval').length },
  { label: 'Posted', value: reminders.value.filter((item) => item.status === 'posted').length },
]);

const monthKey = computed(() => `${currentDate.value.getFullYear()}-${String(currentDate.value.getMonth() + 1).padStart(2, '0')}`);

const calendarDays = computed(() => {
  const start = new Date(monthStart.value);
  start.setDate(start.getDate() - start.getDay());
  const days = [];

  for (let index = 0; index < 42; index += 1) {
    const date = new Date(start);
    date.setDate(start.getDate() + index);
    const iso = date.toISOString().slice(0, 10);
    days.push({
      key: `${iso}-${index}`,
      date: iso,
      day: date.getDate(),
      inMonth: date.getMonth() === currentDate.value.getMonth(),
    });
  }

  return days;
});

watch(reminders, persistReminders, { deep: true });

onMounted(() => {
  try {
    reminders.value = JSON.parse(localStorage.getItem(storageKey) || '[]');
  } catch (_) {
    reminders.value = [];
  }
});

function saveReminder() {
  if (!canAdd.value) return;

  const payload = {
    id: editingId.value ?? Date.now(),
    title: form.value.title.trim(),
    platform: form.value.platform,
    platformShort: form.value.platform.slice(0, 2).toUpperCase(),
    client: form.value.client.trim(),
    date: form.value.date,
    time: form.value.time,
    status: form.value.status,
  };

  if (editingId.value) {
    const index = reminders.value.findIndex((item) => item.id === editingId.value);
    if (index !== -1) reminders.value[index] = payload;
  } else {
    reminders.value.push(payload);
  }

  clearForm();
}

function editReminder(item) {
  editingId.value = item.id;
  form.value = {
    title: item.title,
    platform: item.platform,
    client: item.client,
    date: item.date,
    time: item.time,
    status: item.status,
  };
}

function deleteReminder(item) {
  if (!confirm(`Delete reminder "${item.title}"?`)) return;
  reminders.value = reminders.value.filter((row) => row.id !== item.id);
  if (editingId.value === item.id) clearForm();
}

function clearForm() {
  editingId.value = null;
  form.value = {
    title: '',
    platform: 'FB',
    client: '',
    date: todayDate,
    time: '10:00',
    status: 'planned',
  };
}

function remindersForDate(date) {
  return filteredReminders.value.filter((item) => item.date === date).slice(0, 4);
}

function changeMonth(delta) {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + delta, 1);
}

function persistReminders() {
  localStorage.setItem(storageKey, JSON.stringify(reminders.value));
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusLabel(status) {
  return {
    planned: 'Planned',
    design: 'Design',
    approval: 'Approval',
    scheduled: 'Scheduled',
    posted: 'Posted',
  }[status] ?? status;
}
</script>

<style scoped>
.page { padding: 24px 28px; color: #172033; }
.page-header {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 18px 20px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px;
}
.eyebrow { margin: 0 0 5px; color: #0f766e; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.8px; }
.page-header h1 { margin: 0 0 4px; font-size: 24px; font-weight: 900; }
.page-header p { margin: 0; color: #64748b; font-size: 13px; }
.month-control { display: flex; align-items: center; gap: 10px; }
.month-control strong { min-width: 160px; text-align: center; font-size: 15px; }
.month-control button {
  width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 7px; background: #f8fafc; cursor: pointer; font-size: 18px;
}
.entry-panel, .toolbar {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 14px; margin-bottom: 14px;
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 5px; min-width: 170px; }
.date-field { min-width: 145px; }
.time-field { min-width: 110px; }
.field label {
  color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px;
}
.field input, .field select {
  height: 36px; border: 1.5px solid #dbe3ee; border-radius: 7px; padding: 0 10px; color: #172033; font-size: 13px; outline: none; background: #ffffff;
}
.field input:focus, .field select:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.btn-add, .btn-clear {
  height: 36px; border: none; border-radius: 7px; padding: 0 15px; font-size: 13px; font-weight: 850; cursor: pointer;
}
.btn-add { background: #0f766e; color: #ffffff; }
.btn-add:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-clear { background: #eef2f7; color: #475569; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summary-card {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 14px;
}
.summary-card span { color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; }
.summary-card strong { display: block; margin-top: 6px; font-size: 24px; }
.calendar-card, .list-card {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; overflow: hidden; margin-bottom: 14px;
}
.calendar-head { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: #0f172a; color: #ffffff; }
.calendar-head span { padding: 10px; text-align: center; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.day-cell {
  min-height: 128px; border: none; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
  background: #ffffff; color: #172033; padding: 8px; text-align: left; cursor: pointer; overflow: hidden;
}
.day-cell:hover { background: #f8fafc; }
.day-cell.muted { background: #f8fafc; color: #94a3b8; }
.day-cell.today { box-shadow: inset 0 0 0 2px #0f766e; }
.day-number { display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 999px; font-size: 12px; font-weight: 900; }
.today .day-number { background: #0f766e; color: #ffffff; }
.event-stack { display: grid; gap: 5px; margin-top: 6px; }
.event-pill {
  border-radius: 6px; padding: 5px 6px; font-size: 11px; line-height: 1.25; color: #0f172a; border-left: 4px solid #64748b;
}
.event-pill strong { margin-right: 4px; }
.event-pill span { overflow-wrap: anywhere; }
.platform-fb { background: #dbeafe; border-color: #2563eb; }
.platform-ig { background: #fce7f3; border-color: #db2777; }
.platform-tiktok { background: #e0f2fe; border-color: #0891b2; }
.platform-linkedin { background: #dbeafe; border-color: #0a66c2; }
.platform-website { background: #dcfce7; border-color: #16a34a; }
.status-planned { opacity: 0.9; }
.status-design { outline: 1px dashed rgba(15,23,42,0.24); }
.status-approval { background: #fef3c7; border-color: #f59e0b; }
.status-scheduled { background: #e0f2fe; border-color: #0284c7; }
.status-posted { background: #dcfce7; border-color: #16a34a; }
.list-head {
  display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #e2e8f0;
}
.list-head h2 { margin: 0; font-size: 17px; }
.list-head span { color: #64748b; font-size: 12px; font-weight: 800; }
.reminder-list { display: grid; gap: 8px; padding: 12px; }
.reminder-row {
  display: grid; grid-template-columns: 42px minmax(0, 1fr) 110px 64px 70px; align-items: center; gap: 10px;
  border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #f8fafc;
}
.platform-mark { width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; }
.reminder-row strong { display: block; color: #172033; font-size: 13px; }
.reminder-row p { margin: 4px 0 0; color: #64748b; font-size: 12px; font-weight: 700; }
.status-badge {
  display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; padding: 0 10px;
  background: #e2e8f0; color: #334155; font-size: 11px; font-weight: 900; text-transform: uppercase;
}
.btn-edit, .btn-delete {
  height: 30px; border: none; border-radius: 6px; padding: 0 9px; font-size: 11px; font-weight: 850; cursor: pointer;
}
.btn-edit { background: #dbeafe; color: #1d4ed8; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.empty-state { padding: 28px; text-align: center; color: #64748b; font-size: 13px; font-weight: 700; }
@media (max-width: 980px) {
  .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .calendar-card { overflow-x: auto; }
  .calendar-head, .calendar-grid { min-width: 900px; }
  .reminder-row { grid-template-columns: 42px minmax(0, 1fr); }
  .status-badge, .btn-edit, .btn-delete { justify-self: start; }
}
@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { flex-direction: column; align-items: stretch; }
  .field, .date-field, .time-field { width: 100%; min-width: 0; }
  .btn-add, .btn-clear { width: 100%; }
  .summary-grid { grid-template-columns: 1fr; }
}
</style>
