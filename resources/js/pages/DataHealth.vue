<template>
  <div class="page">

    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">Data Health Report</h1>
        <p class="page-subtitle">Checks CRM records for missing fields, duplicates, and coverage issues.</p>
      </div>
      <div v-if="!loading" class="health-score">
        <span class="score-num">{{ data.health_score ?? '—' }}%</span>
        <span class="score-lbl">Health Score</span>
      </div>
    </div>

    <div v-if="loading" class="loading-wrap">
      <LoadingSpinner />
    </div>

    <template v-else>

      <!-- Stats Row -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Total Contacts</div>
          <div class="stat-value">{{ fmt(data.total) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total PICs</div>
          <div class="stat-value">{{ fmt(data.total_pics) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">To-Dos</div>
          <div class="stat-value">{{ fmt(data.total_todos) }}</div>
          <div class="stat-sub">{{ data.overdue_todos }} overdue</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Follow-ups</div>
          <div class="stat-value">{{ fmt(data.total_followups) }}</div>
          <div class="stat-sub">{{ data.overdue_followups }} overdue</div>
        </div>
      </div>

      <!-- Missing Fields -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Contacts — missing required links</span>
          <span class="badge" :class="totalMissing > 0 ? 'badge-red' : 'badge-green'">
            {{ totalMissing > 0 ? `${totalMissing} field gaps` : 'All complete' }}
          </span>
        </div>
        <div class="missing-grid">
          <div v-for="(val, key) in data.missing" :key="key" class="missing-card" :class="val > 0 ? 'warn' : 'ok'">
            <div class="missing-label">{{ missingLabel(key) }}</div>
            <div class="missing-value">{{ fmt(val) }}</div>
            <div class="missing-pct">{{ pct(val) }} of contacts</div>
          </div>
        </div>
      </div>

      <!-- PIC Health + Activity Overdue -->
      <div class="two-col">
        <div class="card">
          <div class="card-header">
            <span class="card-title">PIC Health</span>
            <span class="badge" :class="picIssues > 0 ? 'badge-red' : 'badge-green'">
              {{ picIssues > 0 ? `${picIssues} issues` : 'Clean' }}
            </span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Contacts with no PIC</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-red" :style="{ width: barPct(data.no_pic, data.total) + '%' }"></div></div>
            <span class="dist-count">{{ data.no_pic }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">PICs missing email</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-amber" :style="{ width: barPct(data.pic_no_email, data.total_pics) + '%' }"></div></div>
            <span class="dist-count">{{ data.pic_no_email }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">PICs missing mobile</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-amber" :style="{ width: barPct(data.pic_no_phone, data.total_pics) + '%' }"></div></div>
            <span class="dist-count">{{ data.pic_no_phone }}</span>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Activity Overdue</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Overdue to-dos</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-amber" :style="{ width: barPct(data.overdue_todos, data.total_todos) + '%' }"></div></div>
            <span class="dist-count">{{ data.overdue_todos }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Overdue follow-ups</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-amber" :style="{ width: barPct(data.overdue_followups, data.total_followups) + '%' }"></div></div>
            <span class="dist-count">{{ data.overdue_followups }}</span>
          </div>
        </div>
      </div>

      <!-- Duplicate Names -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Duplicate Contact Names</span>
          <span class="badge" :class="data.duplicates?.length > 0 ? 'badge-amber' : 'badge-green'">
            {{ data.duplicates?.length > 0 ? `${data.duplicates.length} group${data.duplicates.length !== 1 ? 's' : ''}` : 'No duplicates' }}
          </span>
        </div>
        <div v-if="!data.duplicates?.length" class="empty-state">No duplicate contact names found.</div>
        <div v-else class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Contact Name</th>
                <th>Occurrences</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in data.duplicates" :key="d.name">
                <td><strong>{{ d.name ?? '(empty)' }}</strong></td>
                <td><span class="badge badge-red">{{ d.cnt }}×</span></td>
                <td>
                  <button class="btn-ghost btn-sm" @click="openMerge(d.name)">Review &amp; Merge</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Merge Modal -->
      <div v-if="mergeModal.open" class="modal-backdrop" @click.self="closeMerge">
        <div class="modal-box">
          <div class="modal-header">
            <h2 class="modal-title">Merge Duplicates — {{ mergeModal.name }}</h2>
            <button class="modal-close" @click="closeMerge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div class="modal-body">
            <p class="modal-hint">Select the contact record to <strong>keep</strong>. All todos, deals, projects, and PICs from the others will be moved to the kept record, and duplicates will be deleted.</p>
            <div v-if="mergeModal.loading" class="loading-wrap">Loading contacts…</div>
            <div v-else class="merge-list">
              <label v-for="c in mergeModal.contacts" :key="c.id" class="merge-row" :class="{ selected: mergeModal.keepId === c.id }">
                <input type="radio" :value="c.id" v-model="mergeModal.keepId" class="merge-radio">
                <div class="merge-info">
                  <div class="merge-name">{{ c.name }}</div>
                  <div class="merge-meta">
                    ID #{{ c.id }}
                    <span v-if="c.status?.name"> · {{ c.status.name }}</span>
                    <span v-if="c.user?.name"> · {{ c.user.name }}</span>
                    <span v-if="c.incharges_count > 0"> · {{ c.incharges_count }} PIC{{ c.incharges_count !== 1 ? 's' : '' }}</span>
                    <span> · added {{ c.created_at }}</span>
                  </div>
                </div>
                <span v-if="mergeModal.keepId === c.id" class="badge badge-purple keep-badge">KEEP</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="closeMerge">Cancel</button>
            <button class="btn-primary" :disabled="!mergeModal.keepId || mergeModal.merging || mergeModal.contacts.length < 2" @click="doMerge">
              {{ mergeModal.merging ? 'Merging…' : `Merge ${mergeModal.contacts.length - 1} duplicate${mergeModal.contacts.length - 1 !== 1 ? 's' : ''}` }}
            </button>
          </div>
        </div>
      </div>

      <!-- Distributions -->
      <div class="two-col">
        <div class="card">
          <div class="card-header"><span class="card-title">Contacts by Status</span></div>
          <div v-for="row in data.by_status" :key="row.name" class="dist-row">
            <span class="dist-label">{{ row.name ?? 'No Status' }}</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-purple" :style="{ width: barPct(row.cnt, maxStatus) + '%' }"></div></div>
            <span class="dist-count">{{ row.cnt }}</span>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Contacts by Assigned User</span></div>
          <div v-for="row in data.by_user" :key="row.name" class="dist-row">
            <span class="dist-label">{{ row.name ?? 'Unassigned' }}</span>
            <div class="dist-bar-wrap"><div class="dist-bar bar-blue" :style="{ width: barPct(row.cnt, maxUser) + '%' }"></div></div>
            <span class="dist-count">{{ row.cnt }}</span>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import axios from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const loading = ref(true);
const data    = ref({});

const mergeModal = reactive({
  open: false, name: '', loading: false, merging: false,
  contacts: [], keepId: null,
});

async function openMerge(name) {
  mergeModal.open     = true;
  mergeModal.name     = name;
  mergeModal.loading  = true;
  mergeModal.contacts = [];
  mergeModal.keepId   = null;
  const res = await axios.get('/v1/contacts', { params: { search: name, per_page: 50 } });
  mergeModal.contacts = (res.data.data ?? []).filter(c => c.name === name);
  if (mergeModal.contacts.length > 0) mergeModal.keepId = mergeModal.contacts[0].id;
  mergeModal.loading = false;
}

function closeMerge() {
  mergeModal.open = false;
}

async function doMerge() {
  if (!mergeModal.keepId) return;
  mergeModal.merging = true;
  const mergeIds = mergeModal.contacts.map(c => c.id).filter(id => id !== mergeModal.keepId);
  await axios.post('/v1/contacts/merge', { keep_id: mergeModal.keepId, merge_ids: mergeIds });
  mergeModal.merging = false;
  mergeModal.open    = false;
  const { data: res } = await axios.get('/v1/data-health');
  data.value = res;
}

const fmt    = (n) => (n ?? 0).toLocaleString();
const pct    = (n) => data.value.total > 0 ? Math.round((n ?? 0) / data.value.total * 100 * 10) / 10 + '%' : '—';
const barPct = (n, max) => max > 0 ? Math.round((n ?? 0) / max * 100) : 0;

const totalMissing = computed(() => Object.values(data.value.missing ?? {}).reduce((s, v) => s + (v ?? 0), 0));
const picIssues    = computed(() => (data.value.no_pic ?? 0) + (data.value.pic_no_email ?? 0) + (data.value.pic_no_phone ?? 0));
const maxStatus    = computed(() => Math.max(...(data.value.by_status ?? []).map(r => r.cnt), 1));
const maxUser      = computed(() => Math.max(...(data.value.by_user ?? []).map(r => r.cnt), 1));

function missingLabel(key) {
  const map = { no_name: 'No Name', no_user: 'No User', no_status: 'No Status', no_type: 'No Type', no_industry: 'No Industry', no_category: 'No Category' };
  return map[key] ?? key;
}

onMounted(async () => {
  const { data: res } = await axios.get('/v1/data-health');
  data.value    = res;
  loading.value = false;
});
</script>

<style scoped>
/* ── Page shell ── */
.page        { padding: 28px 32px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; gap: 16px; }
.header-left { flex: 1; }
.page-title  { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* Health score widget */
.health-score {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  background: var(--primary-soft); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 12px 20px; min-width: 96px; text-align: center; flex-shrink: 0;
}
.score-num { font-size: 28px; font-weight: 800; color: var(--primary); line-height: 1; }
.score-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); margin-top: 2px; }

/* ── Stat row ── */
.stats-row  { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-card  { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; box-shadow: var(--shadow-sm); text-align: center; }
.stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 800; color: var(--text-1); }
.stat-sub   { font-size: 11px; color: var(--text-3); margin-top: 2px; }

/* ── Cards ── */
.card        { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 20px 24px; margin-bottom: 16px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.card-title  { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2); }

/* ── Badges ── */
.badge        { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-green  { background: #dcfce7; color: #15803d; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-purple { background: var(--primary-soft); color: var(--primary-text); }

/* ── Missing fields grid ── */
.missing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.missing-card { background: var(--surface-2); border-radius: var(--radius); padding: 16px 18px; border-top: 3px solid var(--border); }
.missing-card.warn { border-top-color: #f59e0b; }
.missing-card.ok   { border-top-color: #22c55e; }
.missing-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); margin-bottom: 6px; }
.missing-value { font-size: 28px; font-weight: 800; color: var(--text-1); }
.missing-pct   { font-size: 11px; color: var(--text-3); margin-top: 2px; }

/* ── Two-column grid ── */
.two-col      { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.two-col .card { margin-bottom: 0; }

/* ── Distribution bars ── */
.dist-row      { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px; }
.dist-row:last-child { border-bottom: none; }
.dist-label    { width: 200px; flex-shrink: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-2); font-size: 13px; }
.dist-bar-wrap { flex: 1; background: var(--app-bg); border-radius: var(--radius-sm); height: 8px; }
.dist-bar      { height: 8px; border-radius: var(--radius-sm); background: var(--primary); transition: width 0.3s ease; }
.dist-bar.bar-red    { background: #ef4444; }
.dist-bar.bar-amber  { background: #f59e0b; }
.dist-bar.bar-blue   { background: #3b82f6; }
.dist-bar.bar-purple { background: var(--primary); }
.dist-count    { width: 48px; text-align: right; font-size: 12px; font-weight: 700; color: var(--text-2); flex-shrink: 0; }

/* ── Table ── */
.table-wrap    { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
.data-table    { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr       { background: var(--surface-2); }
th             { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.6px; border-bottom: 1px solid var(--border); white-space: nowrap; }
td             { padding: 12px 14px; color: var(--text-1); border-bottom: 1px solid var(--border-soft); }
tr:last-child td { border-bottom: none; }
tr:hover td    { background: var(--surface-2); }

/* ── Buttons ── */
.btn-primary   { padding: 8px 18px; background: var(--primary); color: var(--primary-on); border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; box-shadow: 0 6px 18px -6px rgba(29,78,216,0.45); transition: background 0.15s; }
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-ghost     { padding: 8px 14px; background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.15s; }
.btn-ghost:hover { background: var(--border); color: var(--text-1); }
.btn-sm        { padding: 4px 12px; font-size: 11px; font-weight: 700; }

/* ── Loading / empty ── */
.loading-wrap { display: flex; justify-content: center; align-items: center; padding: 60px 0; }
.empty-state  { text-align: center; padding: 48px 24px; color: var(--text-3); font-size: 14px; }

/* ── Modal ── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
.modal-box      { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 580px; box-shadow: var(--shadow-lg); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header   { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--border); }
.modal-title    { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0; }
.modal-close    { background: none; border: none; font-size: 18px; color: var(--text-3); cursor: pointer; padding: 2px 6px; line-height: 1; }
.modal-close:hover { color: #ef4444; }
.modal-body     { padding: 20px; overflow-y: auto; flex: 1; }
.modal-hint     { font-size: 12px; color: var(--text-2); margin: 0 0 14px; line-height: 1.6; }
.modal-footer   { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--border); }

/* ── Merge list ── */
.merge-list { display: flex; flex-direction: column; gap: 8px; }
.merge-row  { display: flex; align-items: center; gap: 12px; border: 2px solid var(--border); border-radius: var(--radius); padding: 12px 14px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.merge-row.selected { border-color: var(--primary); background: var(--primary-soft); }
.merge-radio { width: 16px; height: 16px; accent-color: var(--primary); flex-shrink: 0; }
.merge-info  { flex: 1; min-width: 0; }
.merge-name  { font-size: 14px; font-weight: 700; color: var(--text-1); }
.merge-meta  { font-size: 11px; color: var(--text-3); margin-top: 2px; }
.keep-badge  { white-space: nowrap; flex-shrink: 0; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .stats-row    { grid-template-columns: repeat(2, 1fr); }
  .missing-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .page         { padding: 16px 16px; }
  .page-header  { flex-direction: column; }
  .two-col      { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page         { padding: 12px 8px; }
  .stats-row    { grid-template-columns: 1fr 1fr; }
  .missing-grid { grid-template-columns: 1fr; }
}
</style>
