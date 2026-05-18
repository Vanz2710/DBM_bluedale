<template>
  <div class="page">

    <!-- ── Header ── -->
    <div class="header">
      <div class="header-left">
        <h1>Sales Pipeline</h1>
        <p>Track deals from lead to close</p>
      </div>
      <div class="header-right">
        <div class="stat-strip">
          <div class="stat-chip chip-open">
            <span class="chip-num">{{ summaryLoading ? '…' : summary.open_count }}</span>
            <span class="chip-lbl">Open</span>
          </div>
          <div class="stat-chip chip-openval">
            <span class="chip-num">{{ summaryLoading ? '…' : fmtValue(summary.open_value) }}</span>
            <span class="chip-lbl">Open Value</span>
          </div>
          <div class="stat-chip chip-won">
            <span class="chip-num">{{ summaryLoading ? '…' : fmtValue(summary.won_value) }}</span>
            <span class="chip-lbl">Won</span>
          </div>
          <div class="stat-chip chip-lost">
            <span class="chip-num">{{ summaryLoading ? '…' : fmtValue(summary.lost_value) }}</span>
            <span class="chip-lbl">Lost</span>
          </div>
        </div>
        <div class="view-toggle">
          <button :class="{ active: viewMode === 'pipeline' }" @click="setView('pipeline')" title="Pipeline view">&#9646; Pipeline</button>
          <button :class="{ active: viewMode === 'list' }" @click="setView('list')" title="List view">&#9776; List</button>
        </div>
        <router-link to="/deals/add" class="btn-add">+ Add Deal</router-link>
      </div>
    </div>

    <!-- ── Filter bar ── -->
    <div class="filter-bar">
      <select v-model="filterStage" @change="applyFilters" class="fc" title="Stage">
        <option value="">All Stages</option>
        <option v-for="s in STAGES" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="filterStatus" @change="applyFilters" class="fc" title="Status">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="won">Won</option>
        <option value="lost">Lost</option>
      </select>
      <select v-if="isAdmin" v-model="filterUser" @change="applyFilters" class="fc" title="Assigned To">
        <option value="">All Users</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <div class="date-range">
        <input type="date" v-model="fromDate" class="fc fc-date" title="Close date from">
        <span class="date-sep">&#8594;</span>
        <input type="date" v-model="toDate" class="fc fc-date" title="Close date to">
      </div>
      <input v-model="search" @keyup.enter="applyFilters" class="fc fc-search" placeholder="Search title, company…" type="text">
      <button class="fb fb-search" @click="applyFilters">Search</button>
      <button v-if="viewMode === 'list'" class="fb fb-export" @click="exportAll">Export</button>
    </div>

    <!-- ── Pipeline (Kanban) ── -->
    <div v-if="viewMode === 'pipeline'" class="content">
      <div v-if="loading" class="loading-msg">Loading pipeline…</div>
      <div v-else class="kanban-board" :class="{ 'dragging-active': draggingId !== null }">
        <div
          v-for="(stage, si) in STAGES" :key="stage"
          class="kanban-col"
          :class="{ 'drop-over': dragOverStage === stage && dragOverStage !== currentDragStage }"
          @dragover.prevent
          @dragenter.prevent="onDragEnter(stage)"
          @dragleave="onDragLeave($event, stage)"
          @drop.prevent="onDrop($event, stage)"
        >
          <div class="col-head" :class="`ch-${stageClass(stage)}`">
            <div class="col-head-left">
              <span class="ch-step">{{ si + 1 }}</span>
              <span class="ch-name">{{ stage }}</span>
            </div>
            <div class="col-head-right">
              <span class="ch-count">{{ (pipelineByStage[stage] || []).length }}</span>
              <span v-if="colValue(stage) > 0" class="ch-val">{{ fmtValue(colValue(stage)) }}</span>
            </div>
          </div>
          <div class="col-body">
            <div
              v-for="deal in (pipelineByStage[stage] || [])"
              :key="deal.id"
              class="deal-card"
              :class="[`dc-${stageClass(stage)}`, {
                'is-dragging': draggingId === deal.id,
                'is-saving':   savingId === deal.id,
              }]"
              draggable="true"
              @dragstart="onDragStart($event, deal)"
              @dragend="onDragEnd"
            >
              <div class="dc-grip" title="Drag to move stage">&#8942;&#8942;</div>
              <div class="dc-body">
                <div class="dc-title">{{ deal.title }}</div>
                <div class="dc-company">{{ deal.contact_name ?? '—' }}</div>
                <div class="dc-chips">
                  <span v-if="deal.value" class="dc-val">{{ fmtValue(deal.value) }}</span>
                  <span v-if="deal.probability != null" class="dc-prob">{{ deal.probability }}%</span>
                  <span v-if="deal.expected_close_date" class="dc-date">{{ fmt(deal.expected_close_date) }}</span>
                </div>
                <div class="dc-actions">
                  <router-link :to="`/deals/${deal.id}/edit`" class="dc-btn dc-edit">Edit</router-link>
                  <button class="dc-btn dc-del" @click="confirmDelete(deal)">Del</button>
                </div>
              </div>
            </div>

            <!-- Drop placeholder shown in empty columns while dragging -->
            <div v-if="draggingId && (pipelineByStage[stage] || []).length === 0" class="drop-placeholder">
              Drop here
            </div>
            <div v-else-if="(pipelineByStage[stage] || []).length === 0" class="col-empty">No deals</div>
          </div>
        </div>
      </div>

      <!-- Pipeline flow indicator -->
      <div class="pipeline-legend">
        <span v-for="(stage, si) in STAGES" :key="stage" class="pl-item">
          <span class="pl-dot" :class="`pld-${stageClass(stage)}`"></span>
          <span class="pl-name">{{ stage }}</span>
          <span v-if="si < STAGES.length - 1" class="pl-arrow">›</span>
        </span>
        <span class="pl-hint">Drag cards to advance stage</span>
      </div>
    </div>

    <!-- ── List view ── -->
    <div v-if="viewMode === 'list'" class="content">
      <div class="table-wrap">
        <div class="table-bar">
          {{ meta.total ?? deals.length }} deal(s)
          <span class="sort-hint">— click headers to sort</span>
        </div>
        <div v-if="loading" class="loading-msg">Loading…</div>
        <div v-else class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>No</th>
                <th class="sortable" @click="changeSort('title')">Title {{ sortIcon('title') }}</th>
                <th>Stage</th>
                <th>Status</th>
                <th>Company</th>
                <th class="sortable" @click="changeSort('value')">Value {{ sortIcon('value') }}</th>
                <th class="sortable" @click="changeSort('probability')">Prob. {{ sortIcon('probability') }}</th>
                <th class="sortable" @click="changeSort('expected_close_date')">Close {{ sortIcon('expected_close_date') }}</th>
                <th>Assigned</th>
                <th class="sortable" @click="changeSort('created_at')">Added {{ sortIcon('created_at') }}</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="deals.length === 0">
                <td colspan="11" class="empty-state">No deals found.</td>
              </tr>
              <tr v-for="(d, idx) in deals" :key="d.id">
                <td>{{ meta.from ? meta.from + idx : idx + 1 }}</td>
                <td class="td-title">{{ d.title }}</td>
                <td><span class="sbadge" :class="`sb-${stageClass(d.stage)}`">{{ d.stage }}</span></td>
                <td><span class="stbadge" :class="`st-${d.status}`">{{ d.status }}</span></td>
                <td>
                  <router-link v-if="d.contact_id" :to="`/contacts/${d.contact_id}`" class="co-link">{{ d.contact_name }}</router-link>
                  <span v-else>{{ d.contact_name ?? '—' }}</span>
                </td>
                <td>{{ d.value ? fmtValue(d.value) : '—' }}</td>
                <td>{{ d.probability != null ? d.probability + '%' : '—' }}</td>
                <td>{{ fmt(d.expected_close_date) }}</td>
                <td>{{ d.user_name ?? '—' }}</td>
                <td>{{ d.entry_date ?? '—' }}</td>
                <td class="td-actions">
                  <router-link :to="`/deals/${d.id}/edit`" class="icon-btn ibtn-edit" title="Edit">&#9998;</router-link>
                  <button class="icon-btn ibtn-del" title="Delete" @click="confirmDelete(d)">&#128465;</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="meta.last_page > 1" class="pagination">
          <button :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">&#8592; Prev</button>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next &#8594;</button>
        </div>
      </div>
    </div>

    <!-- ── Move toast ── -->
    <div v-if="moveToast" class="move-toast" :class="`toast-${moveToast.type}`">
      {{ moveToast.message }}
    </div>

    <!-- ── Delete modal ── -->
    <div v-if="deleteTarget" class="modal-backdrop" @click.self="deleteTarget = null">
      <div class="modal">
        <h3>Delete Deal?</h3>
        <p>Delete <strong>{{ deleteTarget.title }}</strong>?</p>
        <div class="modal-btns">
          <button class="mb-cancel" @click="deleteTarget = null">Cancel</button>
          <button class="mb-danger" @click="doDelete" :disabled="deleting">{{ deleting ? 'Deleting…' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';

const STAGES = ['New Lead', 'Contacted', 'Quotation Sent', 'Negotiation', 'Won', 'Lost'];
const STAGE_CLASSES = {
  'New Lead': 'new-lead', 'Contacted': 'contacted',
  'Quotation Sent': 'quotation', 'Negotiation': 'negotiation',
  'Won': 'won', 'Lost': 'lost',
};

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

const viewMode     = ref('pipeline');
const filterStage  = ref('');
const filterStatus = ref('');
const filterUser   = ref('');
const fromDate     = ref('');
const toDate       = ref('');
const search       = ref('');
const perPage      = ref(100);
const page         = ref(1);
const sortField    = ref('created_at');
const sortDir      = ref('desc');

const deals          = ref([]);
const meta           = ref({});
const loading        = ref(false);
const users          = ref([]);
const summary        = ref({ open_count: 0, open_value: 0, won_value: 0, lost_value: 0 });
const summaryLoading = ref(false);
const deleteTarget   = ref(null);
const deleting       = ref(false);

// Drag-and-drop state
const draggingId       = ref(null);
const currentDragStage = ref(null);
const dragOverStage    = ref(null);
const savingId         = ref(null);
const moveToast        = ref(null);
let   toastTimer       = null;

function stageClass(s) { return STAGE_CLASSES[s] ?? 'other'; }

function fmt(d) {
  if (!d) return '—';
  const [y, m, day] = d.split('-');
  return `${day}-${m}-${y}`;
}

function fmtValue(v) {
  if (v == null || Number(v) === 0) return '—';
  return Number(v).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function sortIcon(f) {
  if (sortField.value !== f) return '⇅';
  return sortDir.value === 'asc' ? '↑' : '↓';
}

function changeSort(f) {
  sortDir.value   = sortField.value === f ? (sortDir.value === 'asc' ? 'desc' : 'asc') : 'desc';
  sortField.value = f;
  load();
}

function setView(v) {
  viewMode.value = v;
  page.value = 1;
  load();
}

function buildParams() {
  const p = {
    per_page:       viewMode.value === 'pipeline' ? 500 : perPage.value,
    page:           viewMode.value === 'pipeline' ? 1   : page.value,
    sort_field:     sortField.value,
    sort_direction: sortDir.value,
    q:              search.value,
    stage:          filterStage.value,
    status:         filterStatus.value,
    from_date:      fromDate.value,
    to_date:        toDate.value,
  };
  if (isAdmin.value && filterUser.value) p.user_id = filterUser.value;
  return p;
}

function buildSummaryParams() {
  const p = { q: search.value, stage: filterStage.value, from_date: fromDate.value, to_date: toDate.value };
  if (isAdmin.value && filterUser.value) p.user_id = filterUser.value;
  return p;
}

const pipelineByStage = computed(() => {
  const g = {};
  for (const s of STAGES) g[s] = [];
  for (const d of deals.value) { if (g[d.stage]) g[d.stage].push(d); }
  return g;
});

function colValue(stage) {
  return (pipelineByStage.value[stage] || []).reduce((sum, d) => sum + (Number(d.value) || 0), 0);
}

// ── Drag-and-drop handlers ──

function onDragStart(event, deal) {
  draggingId.value       = deal.id;
  currentDragStage.value = deal.stage;
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(deal.id));
}

function onDragEnd() {
  draggingId.value       = null;
  currentDragStage.value = null;
  dragOverStage.value    = null;
}

function onDragEnter(stage) {
  dragOverStage.value = stage;
}

function onDragLeave(event, stage) {
  if (!event.currentTarget.contains(event.relatedTarget)) {
    if (dragOverStage.value === stage) dragOverStage.value = null;
  }
}

async function onDrop(event, targetStage) {
  dragOverStage.value = null;

  const dealId = draggingId.value;
  draggingId.value       = null;
  currentDragStage.value = null;

  if (!dealId) return;

  const deal = deals.value.find(d => d.id === dealId);
  if (!deal || deal.stage === targetStage) return;

  const oldStage  = deal.stage;
  const oldStatus = deal.status;

  // Auto-sync status when moving to terminal stages
  let newStatus = deal.status;
  if (targetStage === 'Won')  newStatus = 'won';
  else if (targetStage === 'Lost') newStatus = 'lost';
  else if (deal.status !== 'open') newStatus = 'open';

  // Optimistic update
  deal.stage  = targetStage;
  deal.status = newStatus;
  savingId.value = dealId;

  try {
    await api.put(`/v1/deals/${dealId}`, {
      title:               deal.title,
      stage:               targetStage,
      contact_id:          deal.contact_id,
      value:               deal.value,
      probability:         deal.probability,
      expected_close_date: deal.expected_close_date,
      status:              newStatus,
      lost_reason:         deal.lost_reason ?? null,
      notes:               deal.notes ?? null,
    });
    loadSummary();
    showToast(`Moved to "${targetStage}"`, 'success');
  } catch {
    deal.stage  = oldStage;
    deal.status = oldStatus;
    showToast('Move failed — please try again', 'error');
  } finally {
    savingId.value = null;
  }
}

function showToast(message, type = 'success') {
  moveToast.value = { message, type };
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { moveToast.value = null; }, 2800);
}

// ── Data loading ──

async function load() {
  loading.value = true;
  try {
    const res   = await api.get('/v1/deals', { params: buildParams() });
    deals.value = res.data.data ?? [];
    meta.value  = res.data.meta ?? {};
  } finally {
    loading.value = false;
  }
}

async function loadSummary() {
  summaryLoading.value = true;
  try {
    const res     = await api.get('/v1/deals/summary', { params: buildSummaryParams() });
    summary.value = res.data.data ?? summary.value;
  } finally {
    summaryLoading.value = false;
  }
}

async function loadUsers() {
  if (!isAdmin.value) return;
  try {
    const res   = await api.get('/v1/rbac/users');
    users.value = res.data.data ?? [];
  } catch (_) {}
}

function applyFilters() { page.value = 1; load(); loadSummary(); }
function changePage(p)   { page.value = p; load(); }

function exportAll() {
  const token = localStorage.getItem('crm_token');
  const qs = new URLSearchParams({ ...buildParams(), _token: token }).toString();
  window.location.href = `/api/v1/deals/export?${qs}`;
}

function confirmDelete(d) { deleteTarget.value = d; }

async function doDelete() {
  deleting.value = true;
  try {
    await api.delete(`/v1/deals/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    load(); loadSummary();
  } finally {
    deleting.value = false;
  }
}

onMounted(() => { load(); loadSummary(); loadUsers(); });
</script>

<style scoped>
/* ── Page shell ── */
.page {
  display: flex; flex-direction: column;
  height: calc(100vh - var(--topbar-h, 47px)); overflow: hidden;
  padding: 14px 20px 12px; box-sizing: border-box;
  gap: 10px;
}

/* ── Header ── */
.header {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  background: linear-gradient(135deg, #134e4a, #0d9488);
  border-radius: 10px; padding: 12px 20px; color: white; flex-shrink: 0;
}
.header-left h1  { font-size: 16px; font-weight: 800; margin: 0; line-height: 1; }
.header-left p   { font-size: 11px; opacity: 0.75; margin: 3px 0 0; }
.header-right    { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Stat chips */
.stat-strip { display: flex; gap: 6px; }
.stat-chip {
  display: flex; flex-direction: column; align-items: center;
  background: rgba(255,255,255,0.12); border-radius: 8px;
  padding: 5px 10px; min-width: 64px;
}
.chip-open    { border-bottom: 2px solid #93c5fd; }
.chip-openval { border-bottom: 2px solid #5eead4; }
.chip-won     { border-bottom: 2px solid #86efac; }
.chip-lost    { border-bottom: 2px solid #fca5a5; }
.chip-num  { font-size: 13px; font-weight: 800; line-height: 1; }
.chip-lbl  { font-size: 9px; opacity: 0.75; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }

/* View toggle */
.view-toggle { display: flex; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 7px; overflow: hidden; }
.view-toggle button {
  height: 30px; padding: 0 12px; border: none; background: transparent;
  font-size: 12px; font-weight: 600; cursor: pointer; color: rgba(255,255,255,0.7);
  transition: background 0.15s, color 0.15s;
}
.view-toggle button.active { background: rgba(255,255,255,0.2); color: white; }
.view-toggle button:first-child { border-right: 1px solid rgba(255,255,255,0.2); }

.btn-add {
  background: rgba(255,255,255,0.15); color: white; border-radius: 7px;
  padding: 7px 14px; text-decoration: none; font-size: 12px; font-weight: 700;
  border: 1.5px solid rgba(255,255,255,0.3); white-space: nowrap;
}
.btn-add:hover { background: rgba(255,255,255,0.25); }

/* ── Filter bar ── */
.filter-bar {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  background: white; border-radius: 9px; padding: 8px 14px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07); flex-shrink: 0;
}
.fc {
  height: 32px; padding: 0 10px; border: 1.5px solid #e2e8f0;
  border-radius: 6px; font-size: 12px; outline: none; background: white; color: #374151;
}
.fc:focus { border-color: #0d9488; }
.fc-date  { width: 130px; }
.fc-search { flex: 1; min-width: 140px; }
.date-range { display: flex; align-items: center; gap: 4px; }
.date-sep { font-size: 11px; color: #94a3b8; }
.fb {
  height: 32px; padding: 0 14px; border: none; border-radius: 6px;
  font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.fb-search { background: #3b82f6; color: white; }
.fb-export { background: #10b981; color: white; }

/* ── Content area ── */
.content { flex: 1; min-height: 0; display: flex; flex-direction: column; }

/* ── Kanban board ── */
.kanban-board {
  flex: 1; min-height: 0; overflow-x: auto; overflow-y: hidden;
  display: flex; gap: 10px; padding-bottom: 4px; align-items: flex-start;
}

/* When any card is being dragged, change cursor on board */
.kanban-board.dragging-active { cursor: grabbing; }

/* ── Kanban column ── */
.kanban-col {
  flex: 1 1 0; min-width: 160px; max-width: 230px;
  display: flex; flex-direction: column;
  background: #f1f5f9; border-radius: 9px; overflow: hidden;
  height: 100%;
  border: 2px solid transparent;
  transition: border-color 0.15s, box-shadow 0.15s;
}

/* Drop-over highlight */
.kanban-col.drop-over {
  border-color: #0d9488;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.18), inset 0 0 0 2000px rgba(13, 148, 136, 0.04);
}

/* ── Column header ── */
.col-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 9px 10px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.4px; flex-shrink: 0;
}
.col-head-left  { display: flex; align-items: center; gap: 6px; }
.col-head-right { display: flex; align-items: center; gap: 5px; flex-direction: column; align-items: flex-end; }

.ch-step {
  width: 16px; height: 16px; border-radius: 50%;
  background: rgba(0,0,0,0.12); font-size: 9px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ch-new-lead    { background: #dbeafe; color: #1d4ed8; }
.ch-contacted   { background: #e0e7ff; color: #4338ca; }
.ch-quotation   { background: #fef3c7; color: #b45309; }
.ch-negotiation { background: #ffedd5; color: #c2410c; }
.ch-won         { background: #dcfce7; color: #15803d; }
.ch-lost        { background: #fee2e2; color: #b91c1c; }

.ch-count {
  background: rgba(0,0,0,0.1); border-radius: 20px;
  padding: 1px 7px; font-size: 10px;
}
.ch-val {
  font-size: 9px; font-weight: 700; opacity: 0.75;
  background: rgba(0,0,0,0.08); border-radius: 8px;
  padding: 1px 5px; white-space: nowrap;
}

/* ── Column body ── */
.col-body {
  flex: 1; min-height: 0; overflow-y: auto;
  padding: 8px; display: flex; flex-direction: column; gap: 6px;
}
.col-empty {
  text-align: center; font-size: 11px; color: #94a3b8; padding: 12px 0;
}

/* Drop placeholder (empty column while dragging) */
.drop-placeholder {
  border: 2px dashed #0d9488; border-radius: 7px;
  padding: 16px 8px; text-align: center;
  font-size: 11px; color: #0d9488; font-weight: 600;
  background: rgba(13, 148, 136, 0.05);
  animation: pulse-border 1.2s ease-in-out infinite;
}
@keyframes pulse-border {
  0%, 100% { border-color: #0d9488; opacity: 1; }
  50% { border-color: #5eead4; opacity: 0.7; }
}

/* ── Deal card ── */
.deal-card {
  background: white; border-radius: 7px;
  border-left: 3px solid transparent;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  cursor: grab;
  display: flex; align-items: flex-start; gap: 4px;
  transition: box-shadow 0.12s, opacity 0.12s, transform 0.1s;
  user-select: none;
}
.deal-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.13);
  transform: translateY(-1px);
}
.deal-card.is-dragging {
  opacity: 0.35;
  transform: scale(0.97);
  cursor: grabbing;
}
.deal-card.is-saving {
  opacity: 0.65;
  pointer-events: none;
}

.dc-new-lead    { border-left-color: #3b82f6; }
.dc-contacted   { border-left-color: #6366f1; }
.dc-quotation   { border-left-color: #f59e0b; }
.dc-negotiation { border-left-color: #f97316; }
.dc-won         { border-left-color: #22c55e; }
.dc-lost        { border-left-color: #ef4444; }

/* Drag grip */
.dc-grip {
  padding: 9px 2px 9px 6px;
  font-size: 11px; color: #cbd5e1; letter-spacing: -1px;
  cursor: grab; flex-shrink: 0; line-height: 1;
  transition: color 0.12s;
}
.deal-card:hover .dc-grip { color: #94a3b8; }

/* Card body */
.dc-body { padding: 9px 10px 9px 0; flex: 1; min-width: 0; }

.dc-title   { font-size: 12px; font-weight: 700; color: #1e293b; margin-bottom: 2px; word-break: break-word; line-height: 1.3; }
.dc-company { font-size: 10px; color: #64748b; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dc-chips   { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 6px; }
.dc-val  { font-size: 10px; font-weight: 700; color: #0d9488; background: #f0fdf4; padding: 1px 6px; border-radius: 8px; }
.dc-prob { font-size: 10px; color: #64748b; background: #f1f5f9; padding: 1px 6px; border-radius: 8px; }
.dc-date { font-size: 10px; color: #94a3b8; background: #f8fafc; padding: 1px 6px; border-radius: 8px; }
.dc-actions { display: flex; gap: 5px; }
.dc-btn {
  flex: 1; height: 22px; border-radius: 5px; font-size: 10px; font-weight: 700;
  cursor: pointer; border: none; text-decoration: none;
  display: inline-flex; align-items: center; justify-content: center;
}
.dc-edit { background: #fef9c3; color: #854d0e; }
.dc-edit:hover { background: #fde68a; }
.dc-del  { background: #fee2e2; color: #991b1b; }
.dc-del:hover { background: #fca5a5; }

/* ── Pipeline legend ── */
.pipeline-legend {
  display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
  padding: 5px 4px 0; flex-shrink: 0;
}
.pl-item  { display: flex; align-items: center; gap: 4px; }
.pl-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.pld-new-lead    { background: #3b82f6; }
.pld-contacted   { background: #6366f1; }
.pld-quotation   { background: #f59e0b; }
.pld-negotiation { background: #f97316; }
.pld-won         { background: #22c55e; }
.pld-lost        { background: #ef4444; }
.pl-name  { font-size: 10px; color: #64748b; white-space: nowrap; }
.pl-arrow { font-size: 11px; color: #cbd5e1; }
.pl-hint  { font-size: 10px; color: #94a3b8; margin-left: 8px; font-style: italic; }

/* ── List table ── */
.table-wrap {
  flex: 1; display: flex; flex-direction: column;
  background: white; border-radius: 9px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden;
}
.table-bar {
  background: #f8fafc; padding: 9px 14px;
  font-size: 12px; font-weight: 700; color: #1e293b;
  border-bottom: 2px solid #e2e8f0; flex-shrink: 0;
}
.sort-hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
.loading-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 13px; }
.table-scroll { flex: 1; overflow: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead th {
  position: sticky; top: 0; z-index: 1;
  background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.6px; padding: 9px 10px;
  border-bottom: 2px solid #e2e8f0; text-align: left; white-space: nowrap;
}
thead th.sortable { cursor: pointer; user-select: none; }
thead th.sortable:hover { color: #0d9488; background: #f0fdfa; }
tbody td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8fafc; }
.td-title { max-width: 180px; font-weight: 600; color: #1e293b; }
.td-actions { white-space: nowrap; }
.co-link { color: #1e293b; font-weight: 600; text-decoration: none; }
.co-link:hover { color: #0d9488; }

/* Stage badge */
.sbadge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 8px; white-space: nowrap; }
.sb-new-lead    { background: #dbeafe; color: #1d4ed8; }
.sb-contacted   { background: #e0e7ff; color: #4338ca; }
.sb-quotation   { background: #fef3c7; color: #b45309; }
.sb-negotiation { background: #ffedd5; color: #c2410c; }
.sb-won         { background: #dcfce7; color: #15803d; }
.sb-lost        { background: #fee2e2; color: #b91c1c; }

/* Status badge */
.stbadge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 8px; text-transform: capitalize; }
.st-open { background: #dbeafe; color: #1d4ed8; }
.st-won  { background: #dcfce7; color: #15803d; }
.st-lost { background: #fee2e2; color: #b91c1c; }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 6px; text-decoration: none;
  font-size: 12px; cursor: pointer; border: none; background: transparent;
}
.ibtn-edit { background: #fefce8; }
.ibtn-edit:hover { background: #fde68a; }
.ibtn-del  { background: #fee2e2; }
.ibtn-del:hover { background: #fca5a5; }
.empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 13px; }

.pagination {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  padding: 10px; border-top: 1px solid #f1f5f9; font-size: 12px; flex-shrink: 0;
}
.pagination button {
  padding: 5px 12px; border: 1.5px solid #e2e8f0; border-radius: 6px;
  background: white; cursor: pointer; font-size: 12px;
}
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Move toast ── */
.move-toast {
  position: fixed; bottom: 24px; right: 24px; z-index: 3000;
  padding: 10px 18px; border-radius: 9px;
  font-size: 13px; font-weight: 600;
  box-shadow: 0 6px 24px rgba(0,0,0,0.18);
  animation: toast-in 0.22s ease;
  pointer-events: none;
}
.toast-success { background: #0d9488; color: white; }
.toast-error   { background: #ef4444; color: white; }
@keyframes toast-in {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Modal ── */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: white; border-radius: 12px; padding: 24px 28px;
  max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal h3 { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 6px; }
.modal p  { font-size: 13px; color: #64748b; margin: 0 0 18px; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; }
.mb-cancel { height: 34px; padding: 0 16px; border: none; border-radius: 7px; background: #f1f5f9; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; }
.mb-danger { height: 34px; padding: 0 16px; border: none; border-radius: 7px; background: #ef4444; color: white; font-size: 13px; font-weight: 600; cursor: pointer; }
.mb-danger:disabled { background: #94a3b8; cursor: not-allowed; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .stat-strip { display: none; }
  .pipeline-legend { display: none; }
}
@media (max-width: 768px) {
  .page { padding: 10px 12px 8px; }
  .header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .header-right { width: 100%; justify-content: space-between; }
}
@media (max-width: 640px) {
  .page { padding: 8px 8px 6px; }
  .chip-num { font-size: 11px; }
  .fc-date { width: 110px; }
}
</style>
