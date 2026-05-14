<template>
  <div class="page">
    <!-- Banner -->
    <div class="welcome-banner">
      <h1>Dashboard Overview</h1>
      <p>Live summary of your CRM pipeline, exhibitions, travel directory, and contacts.</p>
    </div>

    <div v-if="loading" class="loading-msg">Loading analytics…</div>

    <template v-else>
      <!-- At a Glance -->
      <p class="section-title">At a Glance</p>
      <div class="stat-grid">
        <div class="stat-card blue">
          <div class="stat-icon">🏢</div>
          <div class="stat-label">Total Contacts</div>
          <div class="stat-value">{{ fmt(data.total_contacts) }}</div>
          <div class="stat-sub">{{ fmt(data.total_pics) }} persons in charge on record</div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon">📈</div>
          <div class="stat-label">New This Month</div>
          <div class="stat-value">{{ fmt(data.this_month) }}</div>
          <div class="stat-sub">
            <span v-if="momChange === null" class="badge-neutral">First data point</span>
            <span v-else-if="momChange > 0" class="badge-up">↑ {{ momChange }}% vs last month</span>
            <span v-else-if="momChange < 0" class="badge-down">↓ {{ Math.abs(momChange) }}% vs last month</span>
            <span v-else class="badge-neutral">Same as last month</span>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon">📋</div>
          <div class="stat-label">Tasks Due Today</div>
          <div class="stat-value">{{ fmt(data.tasks_due_today) }}</div>
          <div class="stat-sub">{{ todayLabel }}</div>
        </div>
        <div class="stat-card teal">
          <div class="stat-icon">⚡</div>
          <div class="stat-label">Active Contacts</div>
          <div class="stat-value">{{ fmt(data.active_count) }}</div>
          <div class="stat-sub">Status marked Active or On Going</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-icon">🤝</div>
          <div class="stat-label">Existing Clients</div>
          <div class="stat-value">{{ fmt(data.existing_count) }}</div>
          <div class="stat-sub">{{ existingPct }}% of total pipeline</div>
        </div>
        <div class="stat-card orange">
          <div class="stat-icon">⚠</div>
          <div class="stat-label">Unassigned</div>
          <div class="stat-value">{{ fmt(data.unassigned) }}</div>
          <div class="stat-sub">{{ unassignedPct }}% have no sales agent</div>
        </div>
      </div>

      <!-- Insights -->
      <p class="section-title">Smart Insights</p>
      <div class="insights-grid">
        <div class="insight-card" :class="momChange >= 0 ? 'green' : 'warn'">
          <div class="insight-icon">📊</div>
          <div class="insight-label">Pipeline Growth</div>
          <div class="insight-headline">{{ fmt(data.this_month) }} records added this month</div>
          <div class="insight-sub">
            <span v-if="momChange === null">First time records appear this month.</span>
            <span v-else-if="momChange > 0" class="badge-up">↑ {{ momChange }}%</span>
            <span v-else-if="momChange < 0" class="badge-down">↓ {{ Math.abs(momChange) }}%</span>
            <span v-else>Same as last month.</span>
            <template v-if="momChange !== null"> vs last month ({{ fmt(data.last_month) }} records)</template>
          </div>
        </div>

        <div class="insight-card" :class="rawPct > 50 ? 'red' : rawPct > 25 ? 'orange' : 'blue'">
          <div class="insight-icon">👁</div>
          <div class="insight-label">Uncontacted Leads</div>
          <div class="insight-headline">{{ fmt(data.raw_count) }} records are still marked Raw</div>
          <div class="insight-sub">That's {{ rawPct }}% of your entire CRM pipeline.</div>
          <router-link class="insight-cta" to="/crm">View CRM records →</router-link>
        </div>

        <div v-if="data.top_agent" class="insight-card blue">
          <div class="insight-icon">🏆</div>
          <div class="insight-label">Top Sales Agent</div>
          <div class="insight-headline">{{ data.top_agent.label }} leads with {{ fmt(data.top_agent.count) }} records</div>
          <div class="insight-sub">{{ topAgentPct }}% of the total CRM pipeline.</div>
        </div>

        <div v-if="data.top_industry" class="insight-card purple">
          <div class="insight-icon">🏭</div>
          <div class="insight-label">Dominant Industry</div>
          <div class="insight-headline">{{ data.top_industry.label }} is your largest segment</div>
          <div class="insight-sub">{{ fmt(data.top_industry.count) }} companies — {{ topIndPct }}% of all CRM records.</div>
        </div>

        <div v-if="data.top_product" class="insight-card orange">
          <div class="insight-icon">⭐</div>
          <div class="insight-label">Most Requested Product</div>
          <div class="insight-headline">{{ data.top_product.label }} is the top product interest</div>
          <div class="insight-sub">{{ fmt(data.top_product.count) }} companies have expressed interest.</div>
        </div>

        <div class="insight-card" :class="unassignedPct > 30 ? 'red' : 'warn'">
          <div class="insight-icon">⚠</div>
          <div class="insight-label">Data Gap</div>
          <div class="insight-headline">{{ fmt(data.unassigned) }} records have no assigned agent</div>
          <div class="insight-sub">{{ unassignedPct }}% of the pipeline is not being followed up on.</div>
        </div>
      </div>

      <!-- Charts row -->
      <p class="section-title">Records Added Over Time</p>
      <div class="chart-card full">
        <h3>Monthly CRM Entries</h3>
        <canvas ref="monthlyChart" height="80"></canvas>
      </div>

      <p class="section-title">Pipeline Breakdown</p>
      <div class="chart-grid-3">
        <div class="chart-card">
          <h3>By Status</h3>
          <canvas ref="statusChart" height="160"></canvas>
          <ul class="breakdown-list">
            <li v-for="r in data.by_status" :key="r.label">
              <span>{{ r.label }}</span>
              <span class="count-badge">{{ fmt(r.count) }}</span>
            </li>
          </ul>
        </div>
        <div class="chart-card">
          <h3>By Client Type</h3>
          <canvas ref="typeChart" height="160"></canvas>
          <ul class="breakdown-list">
            <li v-for="r in data.by_type" :key="r.label">
              <span>{{ r.label }}</span>
              <span class="count-badge">{{ fmt(r.count) }}</span>
            </li>
          </ul>
        </div>
        <div class="chart-card">
          <h3>Assigned vs Unassigned</h3>
          <canvas ref="assignChart" height="160"></canvas>
          <ul class="breakdown-list">
            <li><span>Assigned</span><span class="count-badge">{{ fmt(data.total_contacts - data.unassigned) }}</span></li>
            <li><span>Unassigned</span><span class="count-badge">{{ fmt(data.unassigned) }}</span></li>
          </ul>
        </div>
      </div>

      <p class="section-title">Market & Product Breakdown</p>
      <div class="chart-grid-2">
        <div class="chart-card">
          <h3>Top Industries</h3>
          <canvas ref="industryChart" :height="Math.max((data.by_industry?.length || 1) * 28 + 40, 160)"></canvas>
        </div>
        <div class="chart-card">
          <h3>Top Products</h3>
          <canvas ref="categoryChart" :height="Math.max((data.by_category?.length || 1) * 28 + 40, 160)"></canvas>
        </div>
      </div>

      <p class="section-title">By Sales Agent</p>
      <div class="chart-card full" style="margin-bottom:30px">
        <h3>Records per Assigned User</h3>
        <canvas ref="userChart" height="100"></canvas>
      </div>

      <!-- Quick actions -->
      <p class="section-title">Quick Actions</p>
      <div class="quick-links">
        <router-link to="/crm"         class="action-btn btn-blue">🏢 CRM Dashboard</router-link>
        <router-link to="/exhibitions" class="action-btn btn-purple">🎪 Exhibitions</router-link>
        <router-link to="/travel"      class="action-btn btn-teal">✈ Travel Hub</router-link>
        <router-link to="/import"      class="action-btn btn-orange">📥 Import Data</router-link>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from '../api.js';
import Chart from 'chart.js/auto';

const loading = ref(true);
const data = ref({});

const monthlyChart  = ref(null);
const statusChart   = ref(null);
const typeChart     = ref(null);
const assignChart   = ref(null);
const industryChart = ref(null);
const categoryChart = ref(null);
const userChart     = ref(null);

const fmt = (n) => (n ?? 0).toLocaleString();
const todayLabel = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });

const COLORS = ['#3498db','#9b59b6','#1abc9c','#e67e22','#e74c3c','#2ecc71','#f39c12','#34495e','#16a085','#8e44ad'];
const palette = (n) => Array.from({ length: n }, (_, i) => COLORS[i % COLORS.length]);

const momChange = computed(() => {
  const t = data.value.this_month ?? 0;
  const l = data.value.last_month ?? 0;
  if (l === 0 && t === 0) return 0;
  if (l === 0) return null;
  return Math.round(((t - l) / l) * 100 * 10) / 10;
});

const total = computed(() => data.value.total_contacts ?? 0);
const rawPct        = computed(() => total.value > 0 ? Math.round((data.value.raw_count ?? 0) / total.value * 100 * 10) / 10 : 0);
const unassignedPct = computed(() => total.value > 0 ? Math.round((data.value.unassigned ?? 0) / total.value * 100 * 10) / 10 : 0);
const existingPct   = computed(() => total.value > 0 ? Math.round((data.value.existing_count ?? 0) / total.value * 100 * 10) / 10 : 0);
const topAgentPct   = computed(() => total.value > 0 && data.value.top_agent ? Math.round(data.value.top_agent.count / total.value * 100 * 10) / 10 : 0);
const topIndPct     = computed(() => total.value > 0 && data.value.top_industry ? Math.round(data.value.top_industry.count / total.value * 100 * 10) / 10 : 0);

function makeChart(canvas, config) {
  if (!canvas) return;
  new Chart(canvas, config);
}

function buildCharts(d) {
  const doughnutOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '60%' };

  makeChart(monthlyChart.value, {
    type: 'line',
    data: {
      labels: d.by_month?.map(r => r.label) ?? [],
      datasets: [{ label: 'Records', data: d.by_month?.map(r => r.count) ?? [], borderColor: '#3498db', backgroundColor: 'rgba(52,152,219,0.1)', fill: true, tension: 0.4, pointRadius: 4 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxRotation: 45 } }, y: { beginAtZero: true } } }
  });

  makeChart(statusChart.value, { type: 'doughnut', data: { labels: d.by_status?.map(r => r.label) ?? [], datasets: [{ data: d.by_status?.map(r => r.count) ?? [], backgroundColor: palette(d.by_status?.length ?? 0), borderWidth: 2, borderColor: '#fff' }] }, options: doughnutOpts });
  makeChart(typeChart.value,   { type: 'doughnut', data: { labels: d.by_type?.map(r => r.label) ?? [],   datasets: [{ data: d.by_type?.map(r => r.count) ?? [],   backgroundColor: palette(d.by_type?.length ?? 0),   borderWidth: 2, borderColor: '#fff' }] }, options: doughnutOpts });
  makeChart(assignChart.value, { type: 'doughnut', data: { labels: ['Assigned','Unassigned'], datasets: [{ data: [d.total_contacts - d.unassigned, d.unassigned], backgroundColor: ['#3498db','#e2e8f0'], borderWidth: 2, borderColor: '#fff' }] }, options: doughnutOpts });

  const barOpts = (axis) => ({ indexAxis: axis, responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } });
  makeChart(industryChart.value, { type: 'bar', data: { labels: d.by_industry?.map(r => r.label) ?? [], datasets: [{ data: d.by_industry?.map(r => r.count) ?? [], backgroundColor: '#3498db' }] }, options: barOpts('y') });
  makeChart(categoryChart.value, { type: 'bar', data: { labels: d.by_category?.map(r => r.label) ?? [], datasets: [{ data: d.by_category?.map(r => r.count) ?? [], backgroundColor: '#9b59b6' }] }, options: barOpts('y') });
  makeChart(userChart.value,     { type: 'bar', data: { labels: d.by_user?.map(r => r.label) ?? [],     datasets: [{ data: d.by_user?.map(r => r.count) ?? [],     backgroundColor: palette(d.by_user?.length ?? 0) }] }, options: { ...barOpts('x'), scales: { y: { beginAtZero: true } } } });
}

onMounted(async () => {
  const { data: res } = await axios.get('/v1/analytics');
  data.value = res;
  loading.value = false;
  await nextTick();
  buildCharts(res);
});
</script>

<style scoped>
.page { max-width: 1300px; margin: 0 auto; padding: 30px 20px; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; font-size: 15px; }

.welcome-banner { background: linear-gradient(135deg,#2c3e50,#3498db); color:white; padding:35px 40px; border-radius:8px; margin-bottom:30px; }
.welcome-banner h1 { font-size:28px; margin:0 0 6px; }
.welcome-banner p { color:#ecf0f1; font-size:16px; margin:0; }

.section-title { font-size:18px; font-weight:bold; color:#2c3e50; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #ecf0f1; }

.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:30px; }
.stat-card { background:white; border-radius:10px; padding:22px 24px; box-shadow:0 1px 4px rgba(0,0,0,0.07); display:flex; flex-direction:column; gap:5px; position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
.stat-card.blue::before   { background:#3b82f6; }
.stat-card.green::before  { background:#10b981; }
.stat-card.teal::before   { background:#06b6d4; }
.stat-card.purple::before { background:#8b5cf6; }
.stat-card.orange::before { background:#f97316; }
.stat-card.red::before    { background:#ef4444; }
.stat-icon  { font-size:22px; }
.stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; }
.stat-value { font-size:40px; font-weight:800; color:#1e293b; line-height:1.1; }
.stat-sub   { font-size:12px; color:#64748b; }
.badge-up     { color:#27ae60; font-weight:bold; }
.badge-down   { color:#e74c3c; font-weight:bold; }
.badge-neutral { background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px; }

.insights-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:30px; }
.insight-card { background:white; border-radius:8px; padding:20px 22px; box-shadow:0 2px 6px rgba(0,0,0,0.05); border-left:4px solid #bdc3c7; display:flex; flex-direction:column; gap:6px; }
.insight-card.green  { border-left-color:#2ecc71; }
.insight-card.red    { border-left-color:#e74c3c; }
.insight-card.blue   { border-left-color:#3498db; }
.insight-card.purple { border-left-color:#9b59b6; }
.insight-card.orange { border-left-color:#e67e22; }
.insight-card.warn   { border-left-color:#f39c12; }
.insight-icon    { font-size:22px; }
.insight-label   { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#95a5a6; font-weight:bold; }
.insight-headline { font-size:15px; font-weight:bold; color:#2c3e50; line-height:1.35; }
.insight-sub     { font-size:12px; color:#7f8c8d; }
.insight-cta     { font-size:12px; color:#3498db; text-decoration:none; margin-top:4px; }
.insight-cta:hover { text-decoration:underline; }

.chart-card { background:white; border-radius:8px; padding:20px 24px; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
.chart-card.full { grid-column:1/-1; }
.chart-card h3 { font-size:14px; font-weight:bold; color:#7f8c8d; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 16px; }
.chart-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:24px; }
.chart-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }

.breakdown-list { list-style:none; margin:12px 0 0; padding:0; }
.breakdown-list li { display:flex; justify-content:space-between; align-items:center; padding:7px 0; border-bottom:1px solid #f0f0f0; font-size:14px; color:#34495e; }
.breakdown-list li:last-child { border-bottom:none; }
.count-badge { background:#ecf0f1; color:#2c3e50; font-size:12px; font-weight:bold; padding:3px 9px; border-radius:12px; }

.quick-links { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-top:10px; }
.action-btn { padding:18px; border-radius:8px; text-decoration:none; font-weight:bold; color:white; text-align:center; font-size:15px; transition:opacity 0.2s; }
.action-btn:hover { opacity:0.88; }
.btn-blue   { background:#3498db; }
.btn-purple { background:#9b59b6; }
.btn-teal   { background:#1abc9c; }
.btn-orange { background:#e67e22; }
</style>
