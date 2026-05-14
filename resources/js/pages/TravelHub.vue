<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Travel & Lifestyle Directory</h1>
        <p>Explore accommodations, dining, attractions, and shopping destinations.</p>
      </div>
      <div class="banner-count">
        {{ fmt(total) }}
        <span>location{{ total !== 1 ? 's' : '' }}{{ filtersActive ? ' matched' : ' total' }}</span>
      </div>
    </div>

    <div class="filter-panel">
      <div class="filter-row">
        <div class="filter-group wide">
          <span class="filter-label">Search</span>
          <input class="filter-input" v-model="search" @keyup.enter="applyFilters" placeholder="Search by name, address, or keyword…">
        </div>
        <div class="filter-group">
          <span class="filter-label">Region / State</span>
          <select class="filter-select" v-model="stateFilter">
            <option value="">All Regions</option>
            <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div class="filter-group">
          <span class="filter-label">Category</span>
          <select class="filter-select" v-model="categoryFilter">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div class="btn-actions">
          <button class="btn btn-primary" @click="applyFilters">Search</button>
          <button class="btn btn-reset" @click="resetFilters">Reset</button>
        </div>
      </div>
    </div>

    <div class="results-bar">
      <strong>{{ fmt(total) }}</strong> location{{ total !== 1 ? 's' : '' }} found
      <span v-if="filtersActive" class="active-tag">Filters active</span>
    </div>

    <div v-if="loading" class="loading-msg">Loading…</div>

    <div class="card-grid" v-else>
      <div v-if="locations.length === 0" class="empty-state">No locations found matching your criteria.</div>

      <div v-for="row in locations" :key="row.id" class="travel-card">
        <div class="card-strip" :style="{ background: catStyle(row.category).strip }"></div>
        <div class="card-header">
          <div class="card-header-top">
            <router-link :to="{ name: 'travel-view', params: { id: row.id } }" class="card-name">{{ row.company_name }}</router-link>
            <span class="cat-badge" :style="{ background: catStyle(row.category).bg, color: catStyle(row.category).text }">{{ row.category ?? 'Uncategorized' }}</span>
          </div>
          <div v-if="row.state" class="card-location">📍 {{ row.state }}</div>
        </div>
        <div class="card-body">
          <div v-if="row.address" class="info-row"><span>🏢</span> {{ row.address }}</div>
          <div v-if="row.phone" class="info-row"><span>📞</span> {{ row.phone }}</div>
          <div v-if="row.email" class="info-row"><span>✉</span> {{ row.email }}</div>
          <div v-if="row.extra && Object.keys(row.extra).length" class="extra-data">
            <div v-for="(val, key) in row.extra" :key="key" class="extra-item">
              <span class="extra-label">{{ key }}:</span>
              <span>{{ val }}</span>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <router-link :to="{ name: 'travel-view', params: { id: row.id } }" class="view-link">View details →</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from '../api.js';

const loading        = ref(true);
const locations      = ref([]);
const states         = ref([]);
const categories     = ref([]);
const total          = ref(0);
const search         = ref('');
const stateFilter    = ref('');
const categoryFilter = ref('');

const filtersActive = computed(() => search.value || stateFilter.value || categoryFilter.value);
const fmt = (n) => (n ?? 0).toLocaleString();

const catConfig = {
  accommodation: { strip: '#0d9488', bg: '#ccfbf1', text: '#0f766e' },
  hotel:         { strip: '#0d9488', bg: '#ccfbf1', text: '#0f766e' },
  food:          { strip: '#d97706', bg: '#fef3c7', text: '#92400e' },
  'f&b':         { strip: '#d97706', bg: '#fef3c7', text: '#92400e' },
  attraction:    { strip: '#2563eb', bg: '#dbeafe', text: '#1d4ed8' },
  activity:      { strip: '#2563eb', bg: '#dbeafe', text: '#1d4ed8' },
  shopping:      { strip: '#db2777', bg: '#fce7f3', text: '#9d174d' },
};

function catStyle(cat) {
  const lower = (cat ?? '').toLowerCase();
  for (const [key, style] of Object.entries(catConfig)) {
    if (lower.includes(key)) return style;
  }
  return { strip: '#64748b', bg: '#f1f5f9', text: '#475569' };
}

async function fetchData() {
  loading.value = true;
  const params = {};
  if (search.value)         params.search   = search.value;
  if (stateFilter.value)    params.state    = stateFilter.value;
  if (categoryFilter.value) params.category = categoryFilter.value;
  const { data } = await axios.get('/v1/travel', { params });
  locations.value  = data.data;
  states.value     = data.states;
  categories.value = data.categories;
  total.value      = data.total;
  loading.value    = false;
}

function applyFilters() { fetchData(); }
function resetFilters()  { search.value = ''; stateFilter.value = ''; categoryFilter.value = ''; fetchData(); }

onMounted(fetchData);
</script>

<style scoped>
.page { max-width: 1400px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; }

.page-banner { background:linear-gradient(135deg,#064e3b,#1abc9c); border-radius:10px; padding:28px 36px; margin-bottom:24px; color:white; display:flex; justify-content:space-between; align-items:center; }
.page-banner h1 { font-size:22px; font-weight:700; margin:0 0 4px; }
.page-banner p  { font-size:14px; opacity:0.8; margin:0; }
.banner-count { font-size:42px; font-weight:800; text-align:right; line-height:1; }
.banner-count span { display:block; font-size:11px; font-weight:400; opacity:0.7; text-transform:uppercase; letter-spacing:1px; margin-top:4px; }

.filter-panel { background:white; border-radius:10px; padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,0.07); margin-bottom:24px; }
.filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:160px; }
.filter-group.wide { flex:3; min-width:260px; }
.filter-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; }
.filter-input, .filter-select { height:40px; padding:0 12px; border:1.5px solid #e2e8f0; border-radius:7px; font-size:14px; color:#2c3e50; background:white; outline:none; }
.filter-input:focus, .filter-select:focus { border-color:#1abc9c; }
.btn-actions { display:flex; gap:8px; align-items:flex-end; }
.btn { height:40px; padding:0 20px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
.btn-primary { background:#1abc9c; color:white; }
.btn-reset { background:#f1f5f9; color:#64748b; border:1.5px solid #e2e8f0; }

.results-bar { display:flex; align-items:center; gap:8px; margin-bottom:20px; font-size:14px; color:#64748b; }
.results-bar strong { color:#1e293b; }
.active-tag { background:#ccfbf1; color:#0f766e; font-size:11px; font-weight:700; padding:2px 9px; border-radius:20px; }

.card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; }
.empty-state { text-align:center; padding:60px; color:#94a3b8; font-size:15px; background:white; border-radius:10px; }

.travel-card { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); overflow:hidden; display:flex; flex-direction:column; transition:box-shadow 0.2s,transform 0.2s; }
.travel-card:hover { box-shadow:0 8px 24px rgba(0,0,0,0.1); transform:translateY(-2px); }
.card-strip { height:5px; }
.card-header { padding:18px 20px 14px; border-bottom:1px solid #f1f5f9; }
.card-header-top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:6px; }
.card-name { font-size:16px; font-weight:700; color:#1e293b; text-decoration:none; line-height:1.3; flex:1; }
.card-name:hover { color:#1abc9c; }
.cat-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap; flex-shrink:0; }
.card-location { font-size:12px; font-weight:600; color:#94a3b8; }
.card-body { padding:16px 20px; flex:1; display:flex; flex-direction:column; gap:10px; }
.info-row { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#475569; }
.extra-data { margin-top:4px; padding:10px 12px; background:#f8fafc; border-radius:7px; font-size:12px; display:flex; flex-direction:column; gap:4px; }
.extra-item { display:flex; gap:6px; }
.extra-label { font-weight:600; color:#94a3b8; white-space:nowrap; }
.card-footer { padding:12px 20px; border-top:1px solid #f1f5f9; }
.view-link { display:inline-flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#1abc9c; text-decoration:none; }
</style>
