<template>
  <div class="page">
    <div class="page-head">
      <div>
        <p class="eyebrow">Social Media Planner</p>
        <h1>Posting Calendar</h1>
        <p>Schedule FB, IG, TikTok, and content reminders for the social media team.</p>
      </div>
      <div class="month-control">
        <button type="button" @click="changeMonth(-1)" class="month-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
        <strong>{{ monthLabel }}</strong>
        <button type="button" @click="changeMonth(1)" class="month-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>
      </div>
    </div>

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
          <button type="button" class="btn-delete" @click="openDeleteModal(item)">Delete</button>
        </article>
      </div>
    </section>
  </div>

  <Teleport to="body">
    <div v-if="deleteModal.open" class="conf-overlay" @click.self="closeDeleteModal">
      <div class="conf-modal">
        <div class="conf-head">
          <div>
            <p class="conf-title">Delete Reminder</p>
            <p class="conf-sub">This cannot be undone.</p>
          </div>
          <button class="conf-close" @click="closeDeleteModal"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="conf-body">
          <svg class="conf-warn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="conf-text">Delete <strong>{{ deleteModal.item?.title }}</strong>?</p>
        </div>
        <div class="conf-foot">
          <button class="conf-cancel" @click="closeDeleteModal">Cancel</button>
          <button class="conf-delete" :disabled="deleteModal.loading" @click="confirmDelete">
            {{ deleteModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref, watch, reactive } from 'vue';
import api from '../api';

const platforms = ['FB', 'IG', 'TikTok', 'LinkedIn', 'Website'];
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const currentDate = ref(new Date());
const reminders = ref([]);
const search = ref('');
const platformFilter = ref('');
const editingId = ref(null);
const loading = ref(false);
const saving = ref(false);
const todayDate = new Date().toISOString().slice(0, 10);

const form = ref({
  title: '',
  platform: 'FB',
  client: '',
  date: todayDate,
  time: '10:00',
  status: 'planned',
});

const canAdd = computed(() => !saving.value && form.value.title.trim() && form.value.date);
const monthLabel = computed(() => currentDate.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }));
const monthStart = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1));

const enriched = computed(() =>
  reminders.value.map((item) => ({
    ...item,
    platformShort: String(item.platform || '').slice(0, 2).toUpperCase(),
  }))
);

const filteredReminders = computed(() => {
  const term = search.value.trim().toLowerCase();
  return enriched.value
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

onMounted(loadReminders);

async function loadReminders() {
  loading.value = true;
  try {
    const { data } = await api.get('/v1/posting-calendar');
    reminders.value = data;
  } catch (_) {
    reminders.value = [];
  } finally {
    loading.value = false;
  }
}

async function saveReminder() {
  if (!canAdd.value) return;

  const payload = {
    title:    form.value.title.trim(),
    platform: form.value.platform,
    client:   form.value.client.trim() || null,
    date:     form.value.date,
    time:     form.value.time || null,
    status:   form.value.status,
  };

  saving.value = true;
  try {
    if (editingId.value) {
      const { data } = await api.put(`/v1/posting-calendar/${editingId.value}`, payload);
      const index = reminders.value.findIndex((item) => item.id === editingId.value);
      if (index !== -1) reminders.value[index] = data;
    } else {
      const { data } = await api.post('/v1/posting-calendar', payload);
      reminders.value.push(data);
    }
    clearForm();
  } finally {
    saving.value = false;
  }
}

function editReminder(item) {
  editingId.value = item.id;
  form.value = {
    title:    item.title,
    platform: item.platform,
    client:   item.client || '',
    date:     item.date,
    time:     item.time || '10:00',
    status:   item.status,
  };
}

const deleteModal = reactive({ open: false, item: null, loading: false });
function openDeleteModal(item) { deleteModal.item = item; deleteModal.open = true; }
function closeDeleteModal() { deleteModal.open = false; deleteModal.item = null; deleteModal.loading = false; }

async function confirmDelete() {
  if (!deleteModal.item) return;
  deleteModal.loading = true;
  try {
    await api.delete(`/v1/posting-calendar/${deleteModal.item.id}`);
    if (editingId.value === deleteModal.item.id) clearForm();
    reminders.value = reminders.value.filter((row) => row.id !== deleteModal.item.id);
    closeDeleteModal();
  } catch {
    closeDeleteModal();
  } finally {
    deleteModal.loading = false;
  }
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
.page { padding: 28px 32px; color: var(--text-1); }
.page-head {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;
}
.eyebrow { margin: 0 0 5px; color: var(--primary); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
.page-head h1 { margin: 0 0 4px; font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; }
.page-head p { margin: 0; color: var(--text-3); font-size: 13.5px; }
.month-control { display: flex; align-items: center; gap: 10px; }
.month-control strong { min-width: 160px; text-align: center; font-size: 15px; color: var(--text-1); }
.month-btn {
  width: 34px; height: 34px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface-2); cursor: pointer; color: var(--text-1);
  display: flex; align-items: center; justify-content: center;
}
.entry-panel, .toolbar {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 14px;
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 5px; min-width: 170px; }
.date-field { min-width: 145px; }
.time-field { min-width: 110px; }
.field label {
  color: var(--text-2); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px;
}
.field input, .field select {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 10px; color: var(--text-1); font-size: 13px; outline: none; background: var(--surface);
}
.field input:focus, .field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.btn-add, .btn-clear {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 15px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.45); }
.btn-add:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summary-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px;
  box-shadow: var(--shadow-xs);
}
.summary-card span { color: var(--text-2); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; }
.summary-card strong { display: block; margin-top: 6px; font-size: 24px; color: var(--text-1); }
.calendar-card, .list-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 14px;
}
.calendar-head { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: var(--text-1); color: #fff; }
.calendar-head span { padding: 10px; text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.day-cell {
  min-height: 128px; border: none; border-right: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); padding: 8px; text-align: left; cursor: pointer; overflow: hidden;
}
.day-cell:hover { background: var(--surface-2); }
.day-cell.muted { background: var(--surface-2); color: var(--text-3); }
.day-cell.today { box-shadow: inset 0 0 0 2px var(--primary); }
.day-number { display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 999px; font-size: 12px; font-weight: 700; }
.today .day-number { background: var(--primary); color: #fff; }
.event-stack { display: grid; gap: 5px; margin-top: 6px; }
.event-pill {
  border-radius: var(--radius-sm); padding: 5px 6px; font-size: 11px; line-height: 1.25; color: var(--text-1); border-left: 4px solid var(--text-3);
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
  display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid var(--border);
}
.list-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--text-1); }
.list-head span { color: var(--text-2); font-size: 12px; font-weight: 700; }
.reminder-list { display: grid; gap: 8px; padding: 12px; }
.reminder-row {
  display: grid; grid-template-columns: 42px minmax(0, 1fr) 110px 64px 70px; align-items: center; gap: 10px;
  border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; background: var(--surface-2);
}
.platform-mark { width: 38px; height: 38px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
.reminder-row strong { display: block; color: var(--text-1); font-size: 13px; }
.reminder-row p { margin: 4px 0 0; color: var(--text-2); font-size: 12px; font-weight: 600; }
.status-badge {
  display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; padding: 0 10px;
  background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); font-size: 11px; font-weight: 700; text-transform: uppercase;
}
.btn-edit, .btn-delete {
  height: 30px; border: none; border-radius: var(--radius-sm); padding: 0 9px; font-size: 11px; font-weight: 600; cursor: pointer;
}
.btn-edit { background: #dbeafe; color: #1d4ed8; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.empty-state { padding: 28px; text-align: center; color: var(--text-3); font-size: 14px; }
@media (max-width: 980px) {
  .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .calendar-card { overflow-x: auto; }
  .calendar-head, .calendar-grid { min-width: 900px; }
  .reminder-row { grid-template-columns: 42px minmax(0, 1fr); }
  .status-badge, .btn-edit, .btn-delete { justify-self: start; }
}
@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-head { flex-direction: column; align-items: stretch; }
  .field, .date-field, .time-field { width: 100%; min-width: 0; }
  .btn-add, .btn-clear { width: 100%; }
  .summary-grid { grid-template-columns: 1fr; }
}

/* ── Confirm modal ── */
.conf-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 900; display: flex; align-items: center; justify-content: center; padding: 16px; }
.conf-modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-soft); overflow: hidden; }
.conf-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.conf-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.conf-sub { font-size: 12px; color: var(--text-3); margin: 0; }
.conf-close { background: none; border: none; cursor: pointer; font-size: 16px; color: var(--text-3); line-height: 1; padding: 0; }
.conf-close:hover { color: var(--text-1); }
.conf-body { padding: 20px 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.conf-warn { width: 44px; height: 44px; flex-shrink: 0; }
.conf-text { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.conf-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }
.conf-cancel { height: 38px; padding: 0 18px; background: none; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; }
.conf-cancel:hover { background: var(--surface-2); }
.conf-delete { height: 38px; padding: 0 18px; background: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.conf-delete:hover:not(:disabled) { background: #b91c1c; }
.conf-delete:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
