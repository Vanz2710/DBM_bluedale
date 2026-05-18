<template>
  <div class="page">
    <div class="page-banner">
      <div class="banner-text">
        <h1>Reports</h1>
        <p>Pre-built contact and pipeline breakdowns</p>
      </div>
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Report picker -->
      <div class="report-nav">
        <button
          v-for="r in REPORTS" :key="r.key"
          :class="['report-tab', { active: activeReport === r.key }]"
          @click="activeReport = r.key"
        >{{ r.label }}</button>
      </div>

      <!-- Report content -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">{{ currentReport.label }}</span>
          <span class="badge total">{{ currentRows.length }} {{ currentRows.length === 1 ? 'entry' : 'entries' }}</span>
        </div>

        <div v-if="activeReport === 'by_month'" class="chart-wrap">
          <div class="month-bars">
            <div v-for="row in currentRows" :key="row.label" class="month-bar-col">
              <div class="month-bar-outer">
                <div class="month-bar-fill" :style="{ height: monthBarPct(row.count) + '%' }">
                  <span class="month-bar-val">{{ row.count }}</span>
                </div>
              </div>
              <div class="month-label">{{ row.label }}</div>
            </div>
          </div>
        </div>

        <div v-else class="dist-list">
          <div v-for="row in currentRows" :key="row.label" class="dist-row">
            <span class="dist-label">{{ row.label ?? 'Unknown' }}</span>
            <div class="dist-bar-wrap">
              <div class="dist-bar-fill" :style="{ width: barPct(row.count) + '%' }"></div>
            </div>
            <span class="dist-pct">{{ pct(row.count) }}</span>
            <span class="dist-count">{{ row.count.toLocaleString() }}</span>
          </div>
        </div>

        <!-- Table -->
        <table class="report-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ activeReport === 'by_month' ? 'Month' : 'Name' }}</th>
              <th>Count</th>
              <th>Share</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in currentRows" :key="row.label">
              <td class="num-cell">{{ i + 1 }}</td>
              <td>{{ row.label ?? '—' }}</td>
              <td class="num-cell">{{ row.count.toLocaleString() }}</td>
              <td class="num-cell">{{ pct(row.count) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" class="total-label">Total</td>
              <td class="num-cell total-val">{{ totalCount.toLocaleString() }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const REPORTS = [
  { key: 'by_status',   label: 'By Status' },
  { key: 'by_industry', label: 'By Industry' },
  { key: 'by_category', label: 'By Category' },
  { key: 'by_type',     label: 'By Type' },
  { key: 'by_user',     label: 'By Sales Agent' },
  { key: 'by_month',    label: 'Monthly Growth' },
];

const loading      = ref(true);
const analytics    = ref(null);
const activeReport = ref('by_status');

const currentReport = computed(() => REPORTS.find(r => r.key === activeReport.value) ?? REPORTS[0]);

const currentRows = computed(() => {
  const raw = analytics.value?.[activeReport.value] ?? [];
  return raw.map(r => ({ label: r.label ?? r.name, count: Number(r.count ?? r.cnt ?? 0) }));
});

const totalCount = computed(() => currentRows.value.reduce((s, r) => s + r.count, 0));

const maxCount = computed(() => Math.max(...currentRows.value.map(r => r.count), 1));

const barPct      = (count) => Math.round(count / maxCount.value * 100);
const monthBarPct = (count) => Math.round(count / maxCount.value * 100);
const pct         = (count) => totalCount.value > 0 ? (count / totalCount.value * 100).toFixed(1) + '%' : '—';

onMounted(async () => {
  const res = await api.get('/v1/analytics');
  analytics.value = res.data;
  loading.value   = false;
});
</script>

<style scoped>
.page { padding: 24px 28px; max-width: 900px; }

.page-banner {
  background: linear-gradient(135deg, #1e3a5f, #0ea5e9);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }

.report-nav {
  display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px;
}
.report-tab {
  height: 36px; padding: 0 16px; border: 1.5px solid #e2e8f0; border-radius: 8px;
  background: white; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer;
  transition: all 0.15s;
}
.report-tab.active { background: #7c3aed; color: white; border-color: #7c3aed; }
.report-tab:hover:not(.active) { border-color: #7c3aed; color: #7c3aed; }

.card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px 24px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
.card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #64748b; }
.badge.total { background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

/* Horizontal bar chart */
.dist-list { margin-bottom: 20px; }
.dist-row { display: grid; grid-template-columns: 180px 1fr 52px 60px; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f8fafc; }
.dist-row:last-child { border-bottom: none; }
.dist-label { font-size: 13px; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dist-bar-wrap { height: 10px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
.dist-bar-fill { height: 100%; background: #7c3aed; border-radius: 4px; min-width: 2px; transition: width 0.4s; }
.dist-pct  { font-size: 11px; color: #94a3b8; text-align: right; }
.dist-count { font-size: 13px; font-weight: 700; color: #1e293b; text-align: right; }

/* Monthly vertical bar chart */
.chart-wrap { margin-bottom: 20px; }
.month-bars { display: flex; gap: 6px; align-items: flex-end; height: 160px; padding: 0 4px; }
.month-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; }
.month-bar-outer { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.month-bar-fill {
  width: 100%; background: #7c3aed; border-radius: 4px 4px 0 0; min-height: 4px;
  position: relative; display: flex; align-items: flex-start; justify-content: center;
  transition: height 0.4s;
}
.month-bar-val { font-size: 9px; font-weight: 700; color: white; padding-top: 3px; }
.month-label { font-size: 9px; color: #94a3b8; font-weight: 600; text-align: center; white-space: nowrap; }

/* Table */
.report-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
.report-table thead th {
  background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 9px 12px;
  border-bottom: 2px solid #e2e8f0; text-align: left;
}
.report-table tbody td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #374151; }
.report-table tbody tr:last-child td { border-bottom: none; }
.report-table tfoot td { padding: 9px 12px; font-weight: 700; color: #1e293b; background: #f8fafc; border-top: 2px solid #e2e8f0; }
.num-cell { text-align: center; }
.total-label { text-align: right; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
.total-val { color: #7c3aed; font-size: 15px; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .dist-row { grid-template-columns: 120px 1fr 40px 50px; }
  .month-bars { gap: 3px; }
  .month-label { font-size: 8px; }
}
</style>
