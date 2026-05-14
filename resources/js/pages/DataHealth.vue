<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Data Health Report</h1>
        <p>Checks CRM records for missing fields, duplicates, and coverage issues.</p>
      </div>
      <div class="health-score-circle">
        <span class="score-num">{{ data.health_score ?? '—' }}%</span>
        <span class="score-label">health</span>
      </div>
    </div>

    <div v-if="loading" class="loading-msg">Loading…</div>

    <template v-else>
      <!-- Stats row -->
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

      <!-- Missing fields -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Contacts — missing required links</span>
          <span class="badge" :class="totalMissing > 0 ? 'issues' : 'clean'">
            {{ totalMissing > 0 ? `${totalMissing} field gaps` : '✓ All complete' }}
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

      <!-- PIC health + Referential integrity -->
      <div class="two-col">
        <div class="card" style="margin-bottom:0">
          <div class="card-header">
            <span class="card-title">PIC Health</span>
            <span class="badge" :class="picIssues > 0 ? 'issues' : 'clean'">
              {{ picIssues > 0 ? `${picIssues} issues` : '✓ Clean' }}
            </span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Contacts with no PIC</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(data.no_pic, data.total) + '%', background: '#f87171' }"></div></div>
            <span class="dist-count">{{ data.no_pic }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">PICs missing email</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(data.pic_no_email, data.total_pics) + '%', background: '#fbbf24' }"></div></div>
            <span class="dist-count">{{ data.pic_no_email }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">PICs missing mobile</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(data.pic_no_phone, data.total_pics) + '%', background: '#fbbf24' }"></div></div>
            <span class="dist-count">{{ data.pic_no_phone }}</span>
          </div>
        </div>

        <div class="card" style="margin-bottom:0">
          <div class="card-header">
            <span class="card-title">Activity Overdue</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Overdue to-dos</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(data.overdue_todos, data.total_todos) + '%', background: '#fbbf24' }"></div></div>
            <span class="dist-count">{{ data.overdue_todos }}</span>
          </div>
          <div class="dist-row">
            <span class="dist-label">Overdue follow-ups</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(data.overdue_followups, data.total_followups) + '%', background: '#fbbf24' }"></div></div>
            <span class="dist-count">{{ data.overdue_followups }}</span>
          </div>
        </div>
      </div>

      <!-- Duplicate names -->
      <div class="card" style="margin-top:16px">
        <div class="card-header">
          <span class="card-title">Duplicate Contact Names</span>
          <span class="badge" :class="data.duplicates?.length > 0 ? 'warn' : 'clean'">
            {{ data.duplicates?.length > 0 ? `${data.duplicates.length} group${data.duplicates.length !== 1 ? 's' : ''}` : '✓ No duplicates' }}
          </span>
        </div>
        <div v-if="!data.duplicates?.length" class="all-good">✓ No duplicate contact names found.</div>
        <table v-else>
          <thead><tr><th>Contact Name</th><th>Occurrences</th></tr></thead>
          <tbody>
            <tr v-for="d in data.duplicates" :key="d.name">
              <td><strong>{{ d.name ?? '(empty)' }}</strong></td>
              <td><span class="dup-pill">{{ d.cnt }}×</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Distributions -->
      <div class="two-col" style="margin-top:16px">
        <div class="card" style="margin-bottom:0">
          <div class="card-header"><span class="card-title">Contacts by Status</span></div>
          <div v-for="row in data.by_status" :key="row.name" class="dist-row">
            <span class="dist-label">{{ row.name ?? 'No Status' }}</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(row.cnt, maxStatus) + '%' }"></div></div>
            <span class="dist-count">{{ row.cnt }}</span>
          </div>
        </div>
        <div class="card" style="margin-bottom:0">
          <div class="card-header"><span class="card-title">Contacts by Assigned User</span></div>
          <div v-for="row in data.by_user" :key="row.name" class="dist-row">
            <span class="dist-label">{{ row.name ?? 'Unassigned' }}</span>
            <div class="dist-bar-wrap"><div class="dist-bar" :style="{ width: barPct(row.cnt, maxUser) + '%', background: '#0ea5e9' }"></div></div>
            <span class="dist-count">{{ row.cnt }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '../api.js';

const loading = ref(true);
const data    = ref({});

const fmt = (n) => (n ?? 0).toLocaleString();
const pct = (n) => data.value.total > 0 ? Math.round((n ?? 0) / data.value.total * 100 * 10) / 10 + '%' : '—';
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
.page { max-width: 1060px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; }

.page-banner { background:linear-gradient(135deg,#1e3a5f,#0ea5e9); border-radius:10px; padding:26px 32px; margin-bottom:20px; color:white; display:flex; justify-content:space-between; align-items:center; gap:20px; }
.page-banner h1 { font-size:21px; font-weight:700; margin:0 0 4px; }
.page-banner p  { font-size:13px; opacity:0.8; margin:0; }
.health-score-circle { width:72px; height:72px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0; }
.score-num   { font-size:22px; font-weight:800; line-height:1; }
.score-label { font-size:9px; opacity:0.75; text-transform:uppercase; letter-spacing:0.8px; }

.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.stat-card { background:white; border-radius:10px; padding:16px 18px; box-shadow:0 1px 4px rgba(0,0,0,0.07); text-align:center; }
.stat-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#94a3b8; margin-bottom:6px; }
.stat-value { font-size:28px; font-weight:800; color:#1e293b; }
.stat-sub   { font-size:11px; color:#94a3b8; margin-top:2px; }

.card { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); padding:20px 24px; margin-bottom:16px; }
.card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #f1f5f9; }
.card-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#64748b; }
.badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; }
.badge.issues { background:#fee2e2; color:#dc2626; }
.badge.clean  { background:#dcfce7; color:#16a34a; }
.badge.warn   { background:#fef9c3; color:#a16207; }

.missing-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.missing-card { background:white; border-radius:10px; padding:16px 18px; box-shadow:0 1px 4px rgba(0,0,0,0.07); border-top:3px solid #e2e8f0; }
.missing-card.warn { border-top-color:#fbbf24; }
.missing-card.ok   { border-top-color:#4ade80; }
.missing-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#94a3b8; margin-bottom:6px; }
.missing-value { font-size:28px; font-weight:800; color:#1e293b; }
.missing-pct   { font-size:11px; color:#94a3b8; margin-top:2px; }

.two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }

.dist-row { display:flex; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid #f8fafc; font-size:13px; color:#374151; }
.dist-row:last-child { border-bottom:none; }
.dist-label { width:200px; flex-shrink:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dist-bar-wrap { flex:1; background:#f1f5f9; border-radius:4px; height:8px; }
.dist-bar { height:8px; border-radius:4px; background:#6d28d9; }
.dist-count { width:48px; text-align:right; font-size:12px; font-weight:700; color:#64748b; flex-shrink:0; }

table { width:100%; border-collapse:collapse; font-size:13px; }
thead th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; padding:9px 14px; border-bottom:2px solid #e2e8f0; text-align:left; }
tbody td { padding:9px 14px; border-bottom:1px solid #f1f5f9; }
tbody tr:last-child td { border-bottom:none; }
.dup-pill { display:inline-block; background:#fee2e2; color:#dc2626; border-radius:20px; padding:1px 8px; font-size:11px; font-weight:700; }
.all-good { text-align:center; padding:20px; color:#94a3b8; font-size:14px; }
</style>
