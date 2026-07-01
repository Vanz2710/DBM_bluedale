<template>
  <div class="page">

    <!-- ── Header ── -->
    <div class="page-head">
      <div class="page-head-left">
        <h1 class="page-title">Forecast Summary</h1>
        <p class="page-subtitle">Monthly forecast totals with contact, status, type, product, result, and owner context.</p>
      </div>
      <div class="page-head-actions">
        <button class="btn-export" @click="openExportModal">Export</button>
        <router-link to="/forecasts" class="btn-light-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>Back to Forecasts</router-link>
      </div>
    </div>

    <!-- ── Filters ── -->
    <div class="toolbar">
      <div class="filter-group">
        <label>Year</label>
        <select v-model="filters.year" @change="page = 1; load()">
          <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Product</label>
        <select v-model="filters.product_id" @change="page = 1; load()">
          <option value="">All Products</option>
          <option v-for="p in lookups.forecast_products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Type</label>
        <select v-model="filters.forecast_type_id" @change="page = 1; load()">
          <option value="">All Types</option>
          <option v-for="t in lookups.forecast_types" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Result</label>
        <select v-model="filters.result_id" @change="page = 1; load()">
          <option value="">All Results</option>
          <option value="none">No Result</option>
          <option v-for="r in resultOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div v-if="isAdmin" class="filter-group">
        <label>User</label>
        <select v-model="filters.user_id" @change="page = 1; load()">
          <option value="">All Users</option>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div class="filter-group wide">
        <label>Search</label>
        <input v-model="filters.q" @keyup.enter="load" placeholder="Company, product, user…">
      </div>
      <button class="btn btn-primary" @click="page = 1; load()">Search</button>
      <button v-if="hasFilters" class="btn btn-clear" @click="clearFilters">Clear</button>
    </div>

    <!-- ── KPI Stats ── -->
    <div class="stat-row">
      <div class="stat-card">
        <span class="stat-label">Total Forecast</span>
        <strong class="stat-value">{{ fmtValue(totals.total_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--success">
        <span class="stat-label">Confirmed</span>
        <strong class="stat-value">{{ fmtValue(totals.confirmed_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--warning">
        <span class="stat-label">Pending</span>
        <strong class="stat-value">{{ fmtValue(totals.pending_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--danger">
        <span class="stat-label">Rejected</span>
        <strong class="stat-value">{{ fmtValue(totals.rejected_amount) }}</strong>
      </div>
      <div class="stat-card stat-card--muted">
        <span class="stat-label">No Result</span>
        <strong class="stat-value">{{ fmtValue(totals.no_result_amount) }}</strong>
      </div>
    </div>

    <!-- ── Monthly Breakdown ── -->
    <div class="months-row">
      <div v-for="m in months" :key="m.month" class="month-chip">
        <span class="month-chip-name">{{ monthName(m.month) }}</span>
        <strong class="month-chip-value">{{ fmtValue(m.amount) }}</strong>
        <small class="month-chip-count">{{ m.count }} item{{ m.count === 1 ? '' : 's' }}</small>
      </div>
    </div>

    <!-- ── Table ── -->
    <div class="table-wrap">
      <div class="table-header-bar">
        <span class="record-count">
          <span class="count-label">Forecast Rows</span>
          <span class="count-badge">{{ rows.length }}</span>
        </span>
        <button class="btn-ghost sm view-toggle-btn" :class="viewMode === 'compact' && 'active'" @click="viewMode = viewMode === 'full' ? 'compact' : 'full'">
          <span v-html="viewMode === 'compact' ? ICO.eye : ICO.eyeOff"></span>
          {{ viewMode === 'compact' ? 'Show Details' : 'Hide Details' }}
        </button>
      </div>
      <LoadingSpinner v-if="loading" />
      <div v-else class="table-scroll">

        <table>
          <thead>
            <tr>
              <th>Company</th>
              <th v-if="viewMode === 'full'">Type</th>
              <th v-if="viewMode === 'full'">Product</th>
              <th v-if="viewMode === 'full'">Result</th>
              <th v-for="m in 12" :key="m">{{ monthShort(m) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td :colspan="viewMode === 'full' ? 16 : 13" class="empty-state">
                <div class="empty-title">No forecast rows for this selection.</div>
                <div class="empty-sub">Try adjusting the filters above.</div>
              </td>
            </tr>
            <tr v-for="row in rows" :key="row.id">
              <td class="td-company">
                <router-link v-if="row.contact_id" :to="`/contacts/${row.contact_id}`" class="company-link">{{ row.contact_name }}</router-link>
                <span v-else class="muted-dash">—</span>
              </td>
              <td v-if="viewMode === 'full'"><span class="type-badge">{{ row.forecast_type_name ?? '—' }}</span></td>
              <td v-if="viewMode === 'full'" class="td-product">{{ row.product_name ?? '—' }}</td>
              <td v-if="viewMode === 'full'">
                <span class="result-badge" :class="resultClass(row.result_name)">{{ row.result_name ?? 'No Result' }}</span>
              </td>
              <td v-for="m in 12" :key="m" class="amount-cell">
                <span v-if="forecastMonth(row) === m">{{ fmtPlain(row.amount) }}</span>
                <span v-else class="muted-dash">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <span class="pager-count">Showing {{ rows.length }} of {{ meta.total ?? rows.length }} row(s)</span>
        <div class="pager-btns">
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) <= 1" @click="changePage((meta.current_page ?? 1) - 1)">‹</button>
          <template v-for="pg in pageNumbers" :key="pg">
            <button v-if="pg !== '...'" class="pager-num" :class="{ 'pager-num--on': pg === (meta.current_page ?? 1) }" @click="changePage(pg)">{{ pg }}</button>
            <span v-else class="pager-ellipsis">…</span>
          </template>
          <button class="pager-nav" :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)" @click="changePage((meta.current_page ?? 1) + 1)">›</button>
        </div>
        <div class="pager-rows">
          <span class="pager-rows-label">Rows</span>
          <select v-model.number="perPage" @change="changePage(1)" class="pager-rows-sel">
            <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>
    </div>

  <!-- Export Modal -->
  <Teleport to="body">
    <div v-if="exportModal.open" class="remark-overlay" @mousedown.self="exportModal.open = false">
      <div class="export-modal">
        <div class="export-modal-header">
          <div>
            <strong class="export-modal-title">Export Forecast Summary</strong>
            <p class="export-modal-sub">Pick what to include, then download.</p>
          </div>
          <button class="remark-close" @click="exportModal.open = false"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="export-modal-body">
          <div class="export-section">
            <div class="export-cols-head">
              <span class="export-section-label">Columns to include</span>
              <div class="export-cols-actions">
                <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = true)">All</button>
                <span class="export-dot-sep">·</span>
                <button class="export-link-btn" @click="exportCols.forEach(c => c.checked = false)">None</button>
              </div>
            </div>
            <div class="export-cols-grid">
              <label v-for="col in exportCols" :key="col.key" class="export-col-check">
                <input type="checkbox" v-model="col.checked">
                <span>{{ col.label }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="export-modal-footer">
          <p class="export-footer-count">
            Will export <strong>{{ rows.length }}</strong> row(s) × <strong>{{ exportCols.filter(c => c.checked).length }}</strong> column(s)
          </p>
          <div class="export-action-stack">
            <button class="export-dl-btn export-dl-xls" :disabled="exportCols.every(c => !c.checked)" @click="executeExport('xls')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              <span class="export-dl-text">
                <span class="export-dl-label">Download Excel</span>
                <span class="export-dl-desc">Formatted with borders &amp; column widths</span>
              </span>
            </button>
            <button class="export-dl-btn export-dl-csv" :disabled="exportCols.every(c => !c.checked)" @click="executeExport('csv')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="export-dl-icon" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              <span class="export-dl-text">
                <span class="export-dl-label">Download CSV</span>
                <span class="export-dl-desc">Plain text, opens in any spreadsheet app</span>
              </span>
            </button>
            <button class="export-cancel-btn" @click="exportModal.open = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';
import { useLookups } from '../composables/useLookups.js';

function _i(d) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
}
const ICO = {
  eye:    _i('<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'),
  eyeOff: _i('<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'),
};

const { lookups, load: loadLookups } = useLookups();

const currentUser = ref(JSON.parse(localStorage.getItem('crm_user') || 'null'));
const isAdmin = computed(() => {
  const roles = currentUser.value?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});
const resultOptions = computed(() => {
  const list = lookups.value?.forecast_results;
  return Array.isArray(list)
    ? list.filter((r) => (r.name ?? '').toLowerCase() !== 'no result')
    : [];
});

const hasFilters = computed(() =>
  filters.year !== yearNow ||
  filters.product_id !== '' ||
  filters.forecast_type_id !== '' ||
  filters.result_id !== '' ||
  filters.user_id !== '' ||
  filters.q !== ''
);

function clearFilters() {
  filters.year = yearNow;
  filters.product_id = '';
  filters.forecast_type_id = '';
  filters.result_id = '';
  filters.user_id = '';
  filters.q = '';
  page.value = 1;
  load();
}

const PER_PAGE_OPTIONS = [20, 50, 100];
const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const SUMMARY_EXPORT_COLUMNS = [
  { key: 'no',                 label: 'No' },
  { key: 'contact_name',       label: 'Company' },
  { key: 'forecast_type_name', label: 'Forecast Type' },
  { key: 'product_name',       label: 'Product' },
  { key: 'result_name',        label: 'Result' },
  ...MONTH_NAMES.map((m, i) => ({ key: `month_${i + 1}`, label: m })),
];
const yearNow = new Date().getFullYear();
const years = Array.from({ length: 7 }, (_, i) => yearNow - 3 + i);
const loading    = ref(false);
const viewMode   = ref('full');
const totals     = ref({});
const months     = ref([]);
const rows       = ref([]);
const meta       = ref({});
const page       = ref(1);
const perPage    = ref(25);
const exportModal = ref({ open: false });
const exportCols  = ref(SUMMARY_EXPORT_COLUMNS.map(c => ({ ...c, checked: true })));

const filters = reactive({
  year: yearNow,
  product_id: '',
  forecast_type_id: '',
  result_id: '',
  user_id: '',
  q: '',
});

const pageNumbers = computed(() => {
  const total = meta.value.last_page ?? 1;
  const cur   = meta.value.current_page ?? 1;
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3)           return [1, 2, 3, '...', total];
  if (cur >= total - 2)   return [1, '...', total - 2, total - 1, total];
  return [1, '...', cur, '...', total];
});

function buildParams() {
  const params = { ...filters, page: page.value, per_page: perPage.value };
  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) delete params[key];
  });
  return params;
}

function changePage(p) { page.value = p; load(); }

function monthName(month) {
  return new Date(2000, month - 1, 1).toLocaleString('en', { month: 'long' });
}

function monthShort(month) {
  return new Date(2000, month - 1, 1).toLocaleString('en', { month: 'short' });
}

function forecastMonth(row) {
  if (!row.forecast_date) return 0;
  return Number(String(row.forecast_date).slice(5, 7));
}

function fmtValue(value) {
  const n = Number(value ?? 0);
  return `RM ${n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtPlain(value) {
  const n = Number(value ?? 0);
  return n ? n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
}

function resultClass(name) {
  const key = (name ?? '').toLowerCase().trim();
  if (key === 'confirmed') return 'result-confirmed';
  if (key === 'pending')   return 'result-pending';
  if (key === 'rejected')  return 'result-rejected';
  return 'result-no-result';
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/forecasts/summary', { params: buildParams() });
    totals.value = res.data.data?.totals ?? {};
    months.value = res.data.data?.months ?? [];
    rows.value   = res.data.data?.rows   ?? [];
    meta.value   = { current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total };
  } finally {
    loading.value = false;
  }
}

function openExportModal() { exportModal.value.open = true; }

function getExportVal(key, row, i) {
  if (key === 'no')                 return i + 1;
  if (key === 'contact_name')       return row.contact_name ?? '—';
  if (key === 'forecast_type_name') return row.forecast_type_name ?? '—';
  if (key === 'product_name')       return row.product_name ?? '—';
  if (key === 'result_name')        return row.result_name ?? 'No Result';
  const m = key.match(/^month_(\d+)$/);
  if (m) return forecastMonth(row) === parseInt(m[1]) ? Number(row.amount ?? 0) : '';
  return '';
}

function executeExport(format = 'csv') {
  const exportRows = rows.value;
  if (!exportRows.length) return;
  const cols = exportCols.value.filter(c => c.checked);
  if (!cols.length) return;
  const date = new Date().toISOString().slice(0, 10);

  const triggerDownload = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 100);
  };

  if (format === 'xls') {
    const esc = v => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const NUMERIC = new Set(['no', ...MONTH_NAMES.map((_, i) => `month_${i + 1}`)]);
    const COL_WIDTHS = { no: 28, contact_name: 190, forecast_type_name: 80, product_name: 110, result_name: 90 };
    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    xml += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n';
    const BORDERS = `<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>`;
    xml += '<Styles>';
    xml += `<Style ss:ID="H"><Font ss:Bold="1" ss:FontName="Arial" ss:Size="10"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/>${BORDERS}</Style>`;
    xml += `<Style ss:ID="D"><Font ss:FontName="Arial" ss:Size="10"/><Alignment ss:Vertical="Top" ss:WrapText="1"/>${BORDERS}</Style>`;
    xml += `<Style ss:ID="N"><Font ss:FontName="Arial" ss:Size="10"/><Alignment ss:Horizontal="Right" ss:Vertical="Top"/>${BORDERS}</Style>`;
    xml += '</Styles>';
    xml += '<Worksheet ss:Name="Forecast Summary"><Table>\n';
    cols.forEach(c => { xml += `<Column ss:Width="${COL_WIDTHS[c.key] ?? 75}"/>`; });
    xml += '\n<Row ss:Height="18">' + cols.map(c => `<Cell ss:StyleID="H"><Data ss:Type="String">${esc(c.label)}</Data></Cell>`).join('') + '</Row>\n';
    exportRows.forEach((row, i) => {
      xml += '<Row>' + cols.map(c => {
        const v = getExportVal(c.key, row, i);
        const isNum = NUMERIC.has(c.key) && v !== '' && !isNaN(Number(v));
        return `<Cell ss:StyleID="${isNum ? 'N' : 'D'}"><Data ss:Type="${isNum ? 'Number' : 'String'}">${isNum ? v : esc(v)}</Data></Cell>`;
      }).join('') + '</Row>\n';
    });
    xml += '</Table></Worksheet></Workbook>';
    triggerDownload(new Blob([xml], { type: 'application/vnd.ms-excel' }), `ForecastSummary_Export_${date}.xls`);
  } else {
    const lines = [cols.map(c => c.label)];
    exportRows.forEach((row, i) => lines.push(cols.map(c => getExportVal(c.key, row, i))));
    const csv = '﻿' + lines.map(r => r.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',')).join('\n');
    triggerDownload(new Blob([csv], { type: 'text/csv;charset=utf-8;' }), `ForecastSummary_Export_${date}.csv`);
  }
  exportModal.value.open = false;
}

onMounted(async () => {
  await Promise.all([loadLookups(), load()]);
});
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1500px; margin: 0 auto; }

/* Page head */
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
.page-head-left { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.page-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: var(--text-1); margin: 0; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-head-actions { display: flex; gap: 10px; align-items: center; }

/* Buttons */
.btn-light-pill {
  display: inline-flex; align-items: center;
  background: var(--surface); color: var(--text-2);
  border: 1px solid var(--border); border-radius: 999px; padding: 10px 18px;
  font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
  transition: border-color 0.15s, color 0.15s;
}
.btn-light-pill:hover { border-color: var(--primary); color: var(--primary); }
.btn-export {
  display: inline-flex; align-items: center;
  background: #10b981; color: #fff; border: none; border-radius: 999px; padding: 11px 20px;
  font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap;
  transition: background 0.15s, transform 0.06s;
}
.btn-export:hover { background: #059669; }
.btn-export:active { transform: translateY(1px); }
.btn { height: 38px; padding: 0 18px; border: none; border-radius: 999px; cursor: pointer; font-size: 13px; font-weight: 600; transition: background 0.15s, transform 0.06s; }
.btn:active { transform: translateY(1px); }
.btn-primary { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.55); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-clear { background: var(--surface); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-soft); }

/* Toolbar */
.toolbar {
  background: var(--surface); border-radius: var(--radius-lg); padding: 14px 16px;
  margin-bottom: 18px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-soft);
  display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group.wide input { width: 220px; }
.filter-group label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); padding-left: 2px; }
.filter-group select, .filter-group input {
  height: 38px; padding: 0 14px; border: 1px solid var(--border);
  border-radius: 999px; font-size: 13px; outline: none;
  background: var(--surface); color: var(--text-1);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-group select { padding-right: 30px; }
.filter-group select:focus, .filter-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }

/* Stat row */
.stat-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 18px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-left: 4px solid var(--primary); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm); padding: 16px 18px;
}
.stat-card--success { border-left-color: #16a34a; }
.stat-card--warning { border-left-color: #b45309; }
.stat-card--danger  { border-left-color: #b91c1c; }
.stat-card--muted   { border-left-color: var(--text-3); }
.stat-label { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-3); margin-bottom: 6px; }
.stat-value { display: block; font-size: 17px; font-weight: 800; color: var(--text-1); letter-spacing: -0.3px; }

/* Monthly breakdown */
.months-row { display: grid; grid-template-columns: repeat(12, 1fr); gap: 8px; margin-bottom: 18px; overflow-x: auto; }
.month-chip {
  background: var(--surface); border: 1px solid var(--border-soft);
  border-radius: var(--radius-lg); padding: 10px 12px; min-width: 90px; box-shadow: var(--shadow-xs);
}
.month-chip-name { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-3); }
.month-chip-value { display: block; font-size: 12.5px; font-weight: 800; color: var(--text-1); margin-top: 4px; }
.month-chip-count { display: block; font-size: 10px; color: var(--text-3); margin-top: 2px; }

/* Table */
.table-wrap { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-soft); overflow: hidden; }
.table-header-bar {
  background: var(--surface); padding: 14px 20px; border-bottom: 1px solid var(--border-soft);
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.record-count { display: flex; align-items: center; gap: 10px; }
.count-label { font-size: 14px; font-weight: 700; color: var(--text-1); letter-spacing: -0.2px; }
.count-badge { background: var(--primary-soft); color: var(--primary-text); font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
.table-scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1240px; }
thead th {
  background: var(--surface-2); color: var(--text-2); font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.55px; padding: 10px 12px;
  border-bottom: 2px solid var(--border); border-right: 1px solid var(--border-soft);
  text-align: left; white-space: nowrap; position: sticky; top: 0; z-index: 1;
}
thead th:last-child { border-right: none; }
tbody td {
  padding: 8px 12px; border-bottom: 1px solid var(--border-soft);
  border-right: 1px solid var(--border-soft); color: var(--text-1);
  vertical-align: middle; font-size: 13px;
}
tbody td:last-child { border-right: none; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--surface-2); }

/* Inline elements */
.td-company { white-space: normal !important; word-break: break-word; min-width: 160px; }
.td-product  { white-space: normal !important; word-break: break-word; min-width: 100px; }
.company-link { color: var(--text-1); font-weight: 600; text-decoration: none; transition: color 0.15s; }
.company-link:hover { color: var(--primary); }
.type-badge { background: #e0f2fe; color: #0369a1; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.result-badge { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
.result-confirmed { background: #dcfce7; color: #15803d; }
.result-pending   { background: #fef3c7; color: #b45309; }
.result-rejected  { background: #fee2e2; color: #b91c1c; }
.result-no-result { background: var(--surface-2); color: var(--text-3); }
.muted-dash { color: var(--text-3); }
.amount-cell { text-align: right; white-space: nowrap; font-weight: 800; color: #0369a1; }

/* View toggle */
.btn-ghost {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
  background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 12.5px; font-weight: 500; cursor: pointer; transition: background 0.15s;
}
.btn-ghost:hover { background: var(--border-soft); color: var(--text-1); }
.view-toggle-btn.active { color: var(--primary); border-color: var(--primary); }

/* Empty state */
.empty-state { text-align: center; padding: 56px 24px; }
.empty-title  { font-size: 15.5px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
.empty-sub    { font-size: 13px; color: var(--text-3); }

/* Export modal */
.remark-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.export-modal { background: var(--surface); border-radius: var(--radius-lg); box-shadow: 0 24px 60px rgba(0,0,0,0.18); width: min(520px, calc(100vw - 48px)); max-height: calc(100vh - 64px); display: flex; flex-direction: column; overflow: hidden; }
.export-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 22px 24px 18px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0; }
.export-modal-title { font-size: 17px; font-weight: 800; color: var(--text-1); }
.export-modal-sub   { font-size: 12.5px; color: var(--text-3); margin: 3px 0 0; }
.export-modal-body  { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.export-modal-footer { display: flex; flex-direction: column; gap: 12px; padding: 16px 24px 20px; border-top: 1px solid var(--border-soft); background: var(--surface-2); flex-shrink: 0; }
.export-footer-count { font-size: 13px; color: var(--text-3); margin: 0; }
.export-footer-count strong { color: var(--primary); }
.export-action-stack { display: flex; flex-direction: column; gap: 10px; }
.export-dl-btn { width: 100%; display: flex; align-items: flex-start; gap: 12px; padding: 13px 16px; border-radius: var(--radius); border: none; cursor: pointer; text-align: left; transition: opacity 0.15s, transform 0.08s; }
.export-dl-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.export-dl-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-dl-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.export-dl-text { display: flex; flex-direction: column; gap: 2px; }
.export-dl-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.export-dl-desc  { font-size: 12px; opacity: 0.82; line-height: 1.3; }
.export-dl-xls { background: #10b981; color: #fff; }
.export-dl-csv { background: var(--surface); border: 1.5px solid var(--border) !important; color: var(--text-1); }
.export-cancel-btn { width: 100%; padding: 10px 16px; border: none; border-radius: var(--radius-sm); background: none; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-3); transition: background 0.12s, color 0.12s; }
.export-cancel-btn:hover { background: var(--border-soft); color: var(--text-2); }
.export-section { display: flex; flex-direction: column; gap: 10px; }
.export-section-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-3); }
.export-cols-head    { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.export-cols-actions { display: flex; align-items: center; gap: 4px; }
.export-link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 700; color: var(--primary); padding: 2px 4px; border-radius: 4px; }
.export-link-btn:hover { text-decoration: underline; }
.export-dot-sep { color: var(--text-3); font-size: 12px; }
.export-cols-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 12px; }
.export-col-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--text-2); font-weight: 500; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-soft); transition: background 0.12s, border-color 0.12s; }
.export-col-check:hover { background: var(--primary-soft); border-color: var(--primary); }
.export-col-check input[type="checkbox"] { accent-color: var(--primary); width: 14px; height: 14px; flex-shrink: 0; cursor: pointer; }
.remark-close { background: none; border: none; cursor: pointer; color: var(--text-3); padding: 4px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: color 0.12s, background 0.12s; }
.remark-close:hover { color: var(--text-1); background: var(--surface-2); }

/* Pager */
.pager {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-top: 1px solid var(--border-soft);
  background: var(--surface); gap: 12px; flex-wrap: wrap;
}
.pager-count { font-size: 12px; color: var(--text-3); white-space: nowrap; }
.pager-btns  { display: flex; align-items: center; gap: 3px; }
.pager-nav {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  color: var(--text-2); cursor: pointer; font-size: 16px; line-height: 1;
  transition: background 0.12s;
}
.pager-nav:hover:not(:disabled) { background: var(--primary-soft); color: var(--primary); }
.pager-nav:disabled { opacity: 0.3; cursor: default; }
.pager-num {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 50%;
  font-size: 12px; font-weight: 600; color: var(--text-2); cursor: pointer;
  transition: background 0.12s;
}
.pager-num:hover { background: var(--primary-soft); color: var(--primary); }
.pager-num--on { background: var(--primary); color: var(--primary-on); font-weight: 700; }
.pager-ellipsis { width: 30px; text-align: center; color: var(--text-3); font-size: 13px; }
.pager-rows { display: flex; align-items: center; gap: 6px; }
.pager-rows-label { font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; }
.pager-rows-sel {
  height: 30px; padding: 0 10px; border: 1px solid var(--border); border-radius: 999px;
  font-size: 12px; background: var(--surface); color: var(--text-1); outline: none; cursor: pointer;
  transition: border-color 0.15s;
}
.pager-rows-sel:focus { border-color: var(--primary); }

@media (max-width: 1200px) { .stat-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px) {
  .stat-row { grid-template-columns: repeat(2, 1fr); }
  .months-row { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 600px) {
  .page { padding: 16px 14px 32px; }
  .stat-row { grid-template-columns: 1fr 1fr; }
  .months-row { grid-template-columns: repeat(4, 1fr); }
  .page-title { font-size: 22px; }
}
</style>
