<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Exhibitions Directory</h1>
        <p>Browse events, venues, and organizers from the clean exhibitions list.</p>
      </div>
      <div class="banner-count">
        {{ fmt(total) }}
        <span>event{{ total !== 1 ? 's' : '' }}{{ filtersActive ? ' matched' : ' total' }}</span>
      </div>
    </div>

    <div class="filter-panel">
      <div class="filter-row">
        <div class="filter-group wide">
          <span class="filter-label">Search</span>
          <input class="filter-input" v-model="search" @keyup.enter="applyFilters" placeholder="Search events, venues, or organizers…">
        </div>
        <div class="filter-group">
          <span class="filter-label">Month</span>
          <select class="filter-select" v-model="monthFilter">
            <option value="">All Months</option>
            <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
          </select>
        </div>
        <div class="btn-actions">
          <button class="btn btn-primary" @click="applyFilters">Filter</button>
          <button class="btn btn-reset" @click="resetFilters">Reset</button>
        </div>
      </div>
    </div>

    <div class="results-bar">
      <span>
        <strong>{{ fmt(total) }}</strong> event{{ total !== 1 ? 's' : '' }} found
        <span v-if="filtersActive" class="active-tag">Filters active</span>
      </span>
    </div>

    <div class="table-wrap">
      <div v-if="loading" class="loading-msg">Loading…</div>
      <table v-else>
        <thead>
          <tr><th>Event Name</th><th>Organizer</th><th>Venue</th><th>Month</th><th>Date</th></tr>
        </thead>
        <tbody>
          <tr v-if="exhibitions.length === 0">
            <td colspan="5" class="empty-state">No events found matching your criteria.</td>
          </tr>
          <tr v-for="e in exhibitions" :key="e.id">
            <td><span class="event-name">{{ e.event_name }}</span></td>
            <td>{{ e.company_name }}</td>
            <td>{{ e.venue }}</td>
            <td><span v-if="e.event_month !== 'N/A'" class="month-badge">{{ e.event_month }}</span><span v-else>—</span></td>
            <td>{{ e.event_date }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '../api.js';

const loading     = ref(true);
const exhibitions = ref([]);
const months      = ref([]);
const total       = ref(0);
const search      = ref('');
const monthFilter = ref('');

const filtersActive = computed(() => search.value || monthFilter.value);
const fmt = (n) => (n ?? 0).toLocaleString();

async function fetchData() {
  loading.value = true;
  const params = {};
  if (search.value)      params.search = search.value;
  if (monthFilter.value) params.month  = monthFilter.value;
  const { data } = await axios.get('/v1/exhibitions', { params });
  exhibitions.value = data.data;
  months.value      = data.months;
  total.value       = data.total;
  loading.value     = false;
}

function applyFilters() { fetchData(); }
function resetFilters()  { search.value = ''; monthFilter.value = ''; fetchData(); }

onMounted(fetchData);
</script>

<style scoped>
.page { max-width: 1300px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 40px; text-align: center; color: #94a3b8; }

.page-banner { background:linear-gradient(135deg,#3b1f5e,#9b59b6); border-radius:10px; padding:28px 36px; margin-bottom:24px; color:white; display:flex; justify-content:space-between; align-items:center; }
.page-banner h1 { font-size:22px; font-weight:700; margin:0 0 4px; }
.page-banner p  { font-size:14px; opacity:0.8; margin:0; }
.banner-count { font-size:42px; font-weight:800; text-align:right; line-height:1; }
.banner-count span { display:block; font-size:11px; font-weight:400; opacity:0.7; text-transform:uppercase; letter-spacing:1px; margin-top:4px; }

.filter-panel { background:white; border-radius:10px; padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:20px; }
.filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:160px; }
.filter-group.wide { flex:3; min-width:260px; }
.filter-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; }
.filter-input, .filter-select { height:40px; padding:0 12px; border:1.5px solid #e2e8f0; border-radius:7px; font-size:14px; color:#2c3e50; background:white; outline:none; }
.filter-input:focus, .filter-select:focus { border-color:#9b59b6; }
.btn-actions { display:flex; gap:8px; align-items:flex-end; }
.btn { height:40px; padding:0 20px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
.btn-primary { background:#9b59b6; color:white; }
.btn-reset { background:#f1f5f9; color:#64748b; border:1.5px solid #e2e8f0; }

.results-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; font-size:14px; color:#64748b; }
.results-bar strong { color:#1e293b; }
.active-tag { background:#f3e8ff; color:#7c3aed; font-size:11px; font-weight:700; padding:2px 9px; border-radius:20px; margin-left:8px; }

.table-wrap { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); overflow:hidden; }
table { width:100%; border-collapse:collapse; }
thead th { background:#f8fafc; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; padding:13px 16px; border-bottom:2px solid #e2e8f0; text-align:left; }
tbody td { padding:13px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; color:#374151; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover { background:#fdf9ff; }
.event-name { font-weight:600; color:#6d28d9; }
.month-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#f3e8ff; color:#7c3aed; }
.empty-state { text-align:center; padding:40px; color:#94a3b8; }
</style>
