<template>
  <div class="page">

    <!-- ── Page header ── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Posting Calendar</h1>
        <p class="page-subtitle">Schedule and track social media posts across all platforms.</p>
      </div>
      <div class="month-nav">
        <button type="button" class="nav-btn" @click="changeMonth(-1)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="month-label">{{ monthLabel }}</span>
        <button type="button" class="nav-btn" @click="changeMonth(1)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    <!-- ── Stats ── -->
    <div class="stats-row">
      <div v-for="card in summaryCards" :key="card.label" class="stat-card">
        <span class="stat-label">{{ card.label }}</span>
        <strong class="stat-val">{{ card.value }}</strong>
      </div>
    </div>

    <!-- ── Add / Edit form ── -->
    <div class="entry-panel" :class="{ 'is-editing': editingId }">
      <div v-if="editingId" class="editing-banner">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Editing reminder
      </div>
      <div class="form-row">
        <div class="field">
          <label class="field-label">Title</label>
          <input class="field-input" v-model="form.title" placeholder="e.g. FB + IG Post">
        </div>
        <div class="field field-sm">
          <label class="field-label">Platform</label>
          <select class="field-input" v-model="form.platform">
            <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label">Description</label>
          <input class="field-input" v-model="form.client" placeholder="e.g. Ramadan promo post">
        </div>
        <div class="field field-date">
          <label class="field-label">Date</label>
          <input class="field-input" v-model="form.date" type="date">
        </div>
        <div class="field field-time">
          <label class="field-label">Time</label>
          <input class="field-input" v-model="form.time" type="time">
        </div>
        <div class="field field-sm">
          <label class="field-label">Status</label>
          <select class="field-input" v-model="form.status">
            <option value="planned">Planned</option>
            <option value="design">Design</option>
            <option value="approval">Approval</option>
            <option value="scheduled">Scheduled</option>
            <option value="posted">Posted</option>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn-primary" :disabled="!canAdd" @click="saveReminder">
            {{ saving ? 'Saving…' : editingId ? 'Update' : '+ Add' }}
          </button>
          <button v-if="editingId" class="btn-ghost" @click="clearForm">Cancel</button>
        </div>
      </div>
    </div>

    <!-- ── Filters ── -->
    <div class="toolbar">
      <div class="field">
        <label class="field-label">Search</label>
        <input class="field-input" v-model="search" placeholder="Title, description, platform...">
      </div>
      <div class="field field-sm">
        <label class="field-label">Platform</label>
        <select class="field-input" v-model="platformFilter">
          <option value="">All platforms</option>
          <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>
      <button class="btn-ghost" @click="search = ''; platformFilter = ''">Clear</button>
    </div>

    <!-- ── Calendar ── -->
    <div class="calendar-wrap">
      <div class="calendar-head-row">
        <span v-for="day in weekDays" :key="day">{{ day }}</span>
      </div>
      <div class="calendar-grid">
        <button
          v-for="day in calendarDays"
          :key="day.key"
          type="button"
          class="day-cell"
          :class="{ 'day-muted': !day.inMonth, 'day-today': day.date === todayDate }"
          @click="form.date = day.date"
        >
          <span class="day-num">{{ day.day }}</span>
          <div class="event-stack">
            <div
              v-for="item in remindersForDate(day.date)"
              :key="item.id"
              class="event-pill"
              :class="[`plat-${item.platform.toLowerCase()}`, `ev-${item.status}`]"
              @click.stop="editReminder(item)"
            >
              <strong>{{ item.platform }}</strong>
              <span>{{ item.time || '--:--' }} {{ item.title }}</span>
            </div>
          </div>
        </button>
      </div>
    </div>

    <!-- ── List ── -->
    <div class="section-head">
      <h2 class="section-title">All Reminders</h2>
      <span class="section-count">{{ filteredReminders.length }} reminder(s)</span>

    </div>
    <div class="table-wrap">
      <div v-if="loading" class="loading-row">Loading...</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Platform</th>
            <th>Title</th>
            <th>Description</th>
            <th>Date &amp; Time</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredReminders.length === 0">
            <td colspan="6" class="empty-state">No posting reminders match the current filter.</td>
          </tr>
          <tr v-for="item in pagedReminders" :key="item.id">
            <td>
              <span class="plat-badge" :class="`plat-${item.platform.toLowerCase()}`">{{ item.platform }}</span>
            </td>
            <td class="td-title">{{ item.title }}</td>
            <td class="td-muted">{{ item.client || '—' }}</td>
            <td class="td-muted">{{ formatDate(item.date) }}<span v-if="item.time"> · {{ item.time }}</span></td>
            <td>
              <span class="badge" :class="statusBadgeClass(item.status)">{{ statusLabel(item.status) }}</span>
            </td>
            <td class="td-actions">
              <button class="btn-sm btn-edit-sm" @click="editReminder(item)">Edit</button>
              <button class="btn-sm btn-del-sm" @click="openDeleteModal(item)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="listMeta.lastPage > 1" class="pagination">
        <span class="pg-info">Showing {{ pagedReminders.length }} of {{ listMeta.total }} reminder(s)</span>
        <div class="pg-btns">
          <button class="pg-nav" :disabled="listPage <= 1" @click="listPage--">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <template v-for="pg in pageNumbers" :key="'pg-' + pg">
            <button v-if="pg !== '...'" class="pg-num" :class="{ 'pg-num--on': pg === listPage }" @click="listPage = pg">{{ pg }}</button>
            <span v-else class="pg-ellipsis">…</span>
          </template>
          <button class="pg-nav" :disabled="listPage >= listMeta.lastPage" @click="listPage++">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>

  </div>

  <Teleport to="body">
    <div v-if="deleteModal.open" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <div>
            <p class="modal-title">Delete Reminder</p>
            <p class="modal-sub">This cannot be undone.</p>
          </div>
          <button class="modal-close" @click="closeDeleteModal">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <svg class="warn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/>
          </svg>
          <p class="warn-text">Delete <strong>{{ deleteModal.item?.title }}</strong>?</p>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="closeDeleteModal">Cancel</button>
          <button class="btn-danger" :disabled="deleteModal.loading" @click="confirmDelete">
            {{ deleteModal.loading ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref, reactive, watch } from 'vue';
import api from '../api';

const platforms = ['FB', 'IG', 'TikTok', 'LinkedIn', 'Website'];
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function localIso(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const currentDate = ref(new Date());
const reminders   = ref([]);
const search      = ref('');
const platformFilter = ref('');
const editingId   = ref(null);
const loading     = ref(false);
const saving      = ref(false);
const todayDate   = localIso(new Date());

const form = ref({
  title:    '',
  platform: 'FB',
  client:   '',
  date:     todayDate,
  time:     '10:00',
  status:   'planned',
});

const canAdd = computed(() => !saving.value && form.value.title.trim() && form.value.date);
const monthLabel = computed(() => currentDate.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }));
const monthStart = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1));
const monthKey   = computed(() => `${currentDate.value.getFullYear()}-${String(currentDate.value.getMonth() + 1).padStart(2, '0')}`);

const filteredReminders = computed(() => {
  const term = search.value.trim().toLowerCase();
  return reminders.value
    .filter((item) => !platformFilter.value || item.platform === platformFilter.value)
    .filter((item) => {
      if (!term) return true;
      return [item.title, item.client, item.platform, item.status].some((v) => String(v || '').toLowerCase().includes(term));
    })
    .sort((a, b) => `${a.date} ${a.time}`.localeCompare(`${b.date} ${b.time}`));
});

const listPage    = ref(1);
const listPerPage = 15;

const listMeta = computed(() => ({
  total:    filteredReminders.value.length,
  lastPage: Math.max(1, Math.ceil(filteredReminders.value.length / listPerPage)),
}));

const pagedReminders = computed(() => {
  const start = (listPage.value - 1) * listPerPage;
  return filteredReminders.value.slice(start, start + listPerPage);
});

const pageNumbers = computed(() => {
  const total = listMeta.value.lastPage;
  const cur   = listPage.value;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)         return [1, 2, 3, '...', total];
  if (cur >= total - 2) return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

watch([search, platformFilter], () => { listPage.value = 1; });

const summaryCards = computed(() => [
  { label: 'This Month', value: reminders.value.filter((i) => i.date.startsWith(monthKey.value)).length },
  { label: 'Scheduled',  value: reminders.value.filter((i) => i.status === 'scheduled').length },
  { label: 'Needs Approval', value: reminders.value.filter((i) => i.status === 'approval').length },
  { label: 'Posted',     value: reminders.value.filter((i) => i.status === 'posted').length },
]);

const calendarDays = computed(() => {
  const start = new Date(monthStart.value);
  start.setDate(start.getDate() - start.getDay());
  const days = [];
  for (let i = 0; i < 42; i++) {
    const date = new Date(start);
    date.setDate(start.getDate() + i);
    const iso = localIso(date);
    days.push({ key: `${iso}-${i}`, date: iso, day: date.getDate(), inMonth: date.getMonth() === currentDate.value.getMonth() });
  }
  return days;
});

onMounted(loadReminders);

async function loadReminders() {
  loading.value = true;
  try {
    const { data } = await api.get('/v1/posting-calendar');
    reminders.value = data;
  } catch {
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
      const idx = reminders.value.findIndex((i) => i.id === editingId.value);
      if (idx !== -1) reminders.value[idx] = data;
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
  form.value = { title: item.title, platform: item.platform, client: item.client || '', date: item.date, time: item.time || '', status: item.status };
}

const deleteModal = reactive({ open: false, item: null, loading: false });
function openDeleteModal(item)  { deleteModal.item = item; deleteModal.open = true; }
function closeDeleteModal()     { deleteModal.open = false; deleteModal.item = null; deleteModal.loading = false; }

async function confirmDelete() {
  if (!deleteModal.item) return;
  deleteModal.loading = true;
  try {
    await api.delete(`/v1/posting-calendar/${deleteModal.item.id}`);
    if (editingId.value === deleteModal.item.id) clearForm();
    reminders.value = reminders.value.filter((r) => r.id !== deleteModal.item.id);
    closeDeleteModal();
  } catch {
    closeDeleteModal();
  } finally {
    deleteModal.loading = false;
  }
}

function clearForm() {
  editingId.value = null;
  form.value = { title: '', platform: 'FB', client: '', date: todayDate, time: '10:00', status: 'planned' };
}

function remindersForDate(date) {
  return filteredReminders.value.filter((i) => i.date === date).slice(0, 4);
}

function changeMonth(delta) {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + delta, 1);
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusLabel(status) {
  return { planned: 'Planned', design: 'Design', approval: 'Approval', scheduled: 'Scheduled', posted: 'Posted' }[status] ?? status;
}

function statusBadgeClass(status) {
  return { planned: 'badge-gray', design: 'badge-blue', approval: 'badge-amber', scheduled: 'badge-purple', posted: 'badge-green' }[status] ?? 'badge-gray';
}
</script>

<style scoped>
.page { padding: 28px 32px; color: var(--text-1); }

/* ── Page header ── */
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; margin-bottom: 24px;
}
.page-title    { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.month-nav     { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.month-label   { min-width: 160px; text-align: center; font-size: 15px; font-weight: 700; color: var(--text-1); }
.nav-btn {
  width: 34px; height: 34px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface); cursor: pointer; color: var(--text-1);
  display: inline-flex; align-items: center; justify-content: center;
}
.nav-btn:hover { background: var(--surface-2); }

/* ── Stats ── */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 16px 20px; box-shadow: var(--shadow-xs);
}
.stat-label { display: block; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.6px; }
.stat-val   { display: block; margin-top: 8px; font-size: 26px; font-weight: 800; color: var(--text-1); }

/* ── Entry panel ── */
.entry-panel {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 14px 16px; margin-bottom: 12px; transition: border-color 0.15s, box-shadow 0.15s;
}
.entry-panel.is-editing { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.editing-banner {
  display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;
  padding: 4px 12px; background: var(--primary-soft); color: var(--primary);
  border-radius: 999px; font-size: 12px; font-weight: 700;
}
.form-row    { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
.field       { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 160px; }
.field-sm    { flex: 0 0 130px; min-width: 120px; }
.field-date  { flex: 0 0 148px; }
.field-time  { flex: 0 0 110px; }
.field-label { font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.6px; }
.field-input {
  height: 36px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 10px; color: var(--text-1); font-size: 13px; background: var(--surface);
  outline: none; width: 100%; box-sizing: border-box;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.form-actions { display: flex; gap: 8px; padding-top: 20px; }

/* ── Toolbar ── */
.toolbar {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 12px 16px; margin-bottom: 20px;
  display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
}

/* ── Buttons ── */
.btn-primary {
  height: 36px; padding: 0 16px; background: var(--primary); color: var(--primary-on);
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer;
  box-shadow: 0 4px 12px -4px rgba(29,78,216,0.4); white-space: nowrap;
}
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
.btn-ghost {
  height: 36px; padding: 0 14px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; cursor: pointer;
}
.btn-ghost:hover { background: var(--border); color: var(--text-1); }
.btn-danger {
  height: 36px; padding: 0 18px; background: #dc2626; color: #fff;
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-sm { height: 28px; padding: 0 10px; border: none; border-radius: var(--radius-sm); font-size: 11px; font-weight: 600; cursor: pointer; }
.btn-edit-sm { background: var(--primary-soft); color: var(--primary); }
.btn-edit-sm:hover { background: #bfdbfe; }
.btn-del-sm  { background: #fee2e2; color: #991b1b; }
.btn-del-sm:hover { background: #fca5a5; }

/* ── Calendar ── */
.calendar-wrap {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  overflow: hidden; margin-bottom: 24px;
}
.calendar-head-row {
  display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: var(--text-1);
}
.calendar-head-row span {
  padding: 10px; text-align: center; font-size: 11px; font-weight: 700;
  text-transform: uppercase; color: #fff; letter-spacing: 0.5px;
}
.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.day-cell {
  min-height: 120px; border: none; border-right: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); padding: 8px; text-align: left; cursor: pointer; overflow: hidden;
}
.day-cell:hover { background: color-mix(in srgb, var(--primary) 4%, var(--surface)); }
.day-muted { background: var(--surface-2); color: var(--text-3); }
.day-today { box-shadow: inset 0 0 0 2px var(--primary); }
.day-num {
  display: inline-flex; width: 26px; height: 26px;
  align-items: center; justify-content: center; border-radius: 999px;
  font-size: 12px; font-weight: 700;
}
.day-today .day-num { background: var(--primary); color: #fff; }
.event-stack { display: grid; gap: 4px; margin-top: 6px; }
.event-pill {
  border-radius: var(--radius-sm); padding: 4px 6px; font-size: 10px; line-height: 1.3;
  border-left: 3px solid transparent; cursor: pointer;
}
.event-pill strong { margin-right: 3px; font-size: 10px; }

/* Platform colours — event pills + list badges */
.plat-fb       { background: #dbeafe; border-color: #2563eb; color: #1e3a8a; }
.plat-ig       { background: #fce7f3; border-color: #db2777; color: #831843; }
.plat-tiktok   { background: #ccfbf1; border-color: #0d9488; color: #134e4a; }
.plat-linkedin { background: #e0f2fe; border-color: #0a66c2; color: #0c4a6e; }
.plat-website  { background: #dcfce7; border-color: #16a34a; color: #14532d; }

/* Platform badge in list (pill) */
.plat-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }

/* Status overrides on event pills */
.event-pill.ev-approval  { background: #fef3c7; border-color: #f59e0b; color: #78350f; }
.event-pill.ev-scheduled { background: #ede9fe; border-color: #7c3aed; color: #4c1d95; }
.event-pill.ev-posted    { background: #dcfce7; border-color: #16a34a; color: #14532d; }

/* ── Section heading above table ── */
.section-head {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
}
.section-title { font-size: 17px; font-weight: 700; color: var(--text-1); margin: 0; }
.section-count { font-size: 12px; color: var(--text-2); font-weight: 600; }

/* ── Table ── */
.table-wrap   { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
.loading-row  { padding: 32px; text-align: center; color: var(--text-3); font-size: 13px; }
.data-table   { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead tr { background: var(--surface-2); }
.data-table th {
  padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700;
  color: var(--text-2); text-transform: uppercase; letter-spacing: 0.6px;
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.data-table td { padding: 12px 14px; color: var(--text-1); border-bottom: 1px solid var(--border-soft); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--surface-2); }
.td-title   { font-weight: 600; }
.td-muted   { color: var(--text-2); }
.td-actions { display: flex; gap: 6px; justify-content: flex-end; }
.empty-state { text-align: center; padding: 40px; color: var(--text-3); }

/* ── Status badges ── */
.badge        { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-gray   { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-purple { background: #ede9fe; color: #6d28d9; }
.badge-green  { background: #dcfce7; color: #15803d; }

/* ── Delete modal ── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.modal-box      { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid var(--border-soft); overflow: hidden; }
.modal-header   { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-soft); }
.modal-title    { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 2px; }
.modal-sub      { font-size: 12px; color: var(--text-3); margin: 0; }
.modal-close    { background: none; border: none; cursor: pointer; color: var(--text-3); display: flex; align-items: center; padding: 2px; border-radius: var(--radius-sm); }
.modal-close:hover { color: var(--text-1); background: var(--surface-2); }
.modal-body     { padding: 24px 20px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.warn-icon      { width: 44px; height: 44px; }
.warn-text      { font-size: 14px; color: var(--text-1); margin: 0; line-height: 1.5; }
.modal-footer   { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 22px; border-top: 1px solid var(--border-soft); }

/* ── Pagination ── */
.pagination  { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid var(--border-soft); flex-wrap: wrap; gap: 10px; }
.pg-info     { font-size: 12px; color: var(--text-2); }
.pg-btns     { display: flex; align-items: center; gap: 4px; }
.pg-nav {
  width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 50%;
  background: var(--surface); color: var(--text-2); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
}
.pg-nav:hover:not(:disabled) { background: color-mix(in srgb, var(--primary) 10%, var(--surface)); border-color: var(--primary); color: var(--primary); }
.pg-nav:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-num {
  min-width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 50%;
  background: var(--surface); color: var(--text-2); font-size: 13px; font-weight: 500; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; padding: 0 6px;
}
.pg-num:hover:not(.pg-num--on) { background: color-mix(in srgb, var(--primary) 10%, var(--surface)); border-color: var(--primary); color: var(--primary); }
.pg-num--on  { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 700; }
.pg-ellipsis { min-width: 32px; text-align: center; color: var(--text-3); font-size: 13px; }

/* ── Responsive ── */
@media (max-width: 980px) {
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .calendar-wrap { overflow-x: auto; }
  .calendar-head-row, .calendar-grid { min-width: 700px; }
}
@media (max-width: 768px) {
  .page { padding: 20px 16px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .field, .field-sm, .field-date, .field-time { flex: 1 1 140px; }
}
@media (max-width: 640px) {
  .page { padding: 16px 12px; }
  .stats-row { grid-template-columns: 1fr 1fr; }
}
</style>
