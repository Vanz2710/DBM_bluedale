<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Import Data</h1>
      <p class="page-subtitle">Upload an Excel or CSV file to add records into the system.</p>
    </div>

    <div class="stepper">
      <div class="step" :class="{ active: step >= 1, done: step > 1 }">
        <div class="step-dot">
          <svg v-if="step > 1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>1</span>
        </div>
        <span class="step-lbl">Upload File</span>
      </div>
      <div class="step-line" :class="{ filled: step >= 2 }"></div>
      <div class="step" :class="{ active: step >= 2, done: step > 2 }">
        <div class="step-dot">
          <svg v-if="step > 2" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>2</span>
        </div>
        <span class="step-lbl">Map Columns</span>
      </div>
      <div class="step-line" :class="{ filled: step >= 3 }"></div>
      <div class="step" :class="{ active: step >= 3 }">
        <div class="step-dot">3</div>
        <span class="step-lbl">Results</span>
      </div>
    </div>

    <!-- Step 1: Upload -->
    <div v-if="step === 1" class="card">
      <div class="card-title">Select file to import</div>
      <div class="card-sub">Supported: .xlsx, .xls, .csv &nbsp;·&nbsp; The system auto-detects column headers.</div>

      <div v-if="error" class="msg-box error-box"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:6px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>{{ error }}</div>

      <div class="drop-zone" :class="{ 'has-file': selectedFile, dragover: isDragging }"
           @dragover.prevent="isDragging = true"
           @dragleave="isDragging = false"
           @drop.prevent="onDrop">
        <input type="file" accept=".xls,.xlsx,.csv" @change="onFileChange" ref="fileInput" class="file-input">
        <div class="drop-icon">
          <svg v-if="!selectedFile" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
          <svg v-else width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="drop-main">{{ selectedFile ? selectedFile.name : 'Drag & drop your file here' }}</div>
        <div class="drop-sub" v-show="!selectedFile">or click to browse</div>
      </div>

      <button class="btn-primary btn-full" :disabled="!selectedFile || uploading" @click="uploadFile">
        {{ uploading ? 'Scanning file…' : 'Next: Map Columns' }}
      </button>
    </div>

    <!-- Step 2: Map columns -->
    <div v-if="step === 2" class="card">
      <div class="card-title">Map your columns to database fields</div>
      <div class="card-sub">We found {{ Object.keys(headers).length }} columns. Tell us what each one contains.</div>

      <div class="smart-notice"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg> Smart Scan detected headers. Data starts from row {{ dataStart }}.</div>

      <div class="map-list">
        <div v-for="(headerName, colLetter) in headers" :key="colLetter"
             class="map-row" :class="{ 'auto-row': autoMatchMap[colLetter] }">
          <div class="map-col-info">
            <span class="col-letter">Col {{ colLetter }}</span>
            <span class="col-name">{{ headerName }}</span>
            <span v-if="autoMatchMap[colLetter]" class="auto-badge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Auto-matched</span>
          </div>
          <select class="map-select" :class="{ 'auto-matched': autoMatchMap[colLetter] }" v-model="mapping[colLetter]">
            <option value="">Ignore / Do Not Import</option>
            <option value="name">Company Name (Required)</option>
            <option value="date_created">Date Created</option>
            <option value="address">Full Address</option>
            <option value="pic_name">Contact Person Name</option>
            <option value="phone_mobile">Contact Phone / Mobile</option>
            <option value="email">Contact Email</option>
            <option value="industry">Industry</option>
            <option value="status">Status</option>
            <option value="client_type">Client Type (A1, A2…)</option>
            <option value="category">Product Category</option>
            <option value="assigned_user">Assigned User</option>
            <option value="remark">Remark / Notes</option>
          </select>
        </div>
      </div>

      <div class="action-row">
        <button class="btn-ghost" @click="step = 1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:3px"><polyline points="15 18 9 12 15 6"/></svg> Back</button>
        <button class="btn-primary" :disabled="importing" @click="processImport">
          {{ importing ? 'Importing…' : 'Start Import' }}
        </button>
      </div>
    </div>

    <!-- Step 3: Results -->
    <div v-if="step === 3" class="card">
      <div class="result-banner" :class="importError ? 'result-error' : 'result-success'">
        <div>
          <h2 class="result-title">{{ importError ? 'Import Failed' : 'Import Complete' }}</h2>
          <p class="result-desc">{{ importError || `${results.imported.toLocaleString()} records imported, ${results.skipped.toLocaleString()} duplicates skipped${results.failed ? `, ${results.failed} rows failed` : ''}.` }}</p>
        </div>
        <div v-if="!importError" class="big-num">{{ results.imported.toLocaleString() }}</div>
      </div>

      <div v-if="!importError" class="summary-row" :class="{ 'three-col': results.failed > 0 }">
        <div class="summary-card imported">
          <div class="summary-label">New records added</div>
          <div class="summary-value">{{ results.imported.toLocaleString() }}</div>
        </div>
        <div class="summary-card skipped">
          <div class="summary-label">Duplicates skipped</div>
          <div class="summary-value">{{ results.skipped.toLocaleString() }}</div>
        </div>
        <div v-if="results.failed > 0" class="summary-card failed">
          <div class="summary-label">Rows failed</div>
          <div class="summary-value">{{ results.failed.toLocaleString() }}</div>
        </div>
      </div>

      <div v-if="results.errors && results.errors.length" class="error-detail">
        <div class="error-detail-title">Failed row details (first {{ results.errors.length }})</div>
        <div v-for="e in results.errors" :key="e.row" class="error-row">
          <span class="err-row-num">Row {{ e.row }}</span>
          <span class="err-name">{{ e.name }}</span>
          <span class="err-reason">{{ e.reason }}</span>
        </div>
      </div>

      <div class="action-row result-actions">
        <button class="btn-ghost" @click="reset">Import more data</button>
        <router-link to="/list" class="btn-primary btn-link">View Contacts</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import axios from '../api.js';

const step         = ref(1);
const selectedFile = ref(null);
const fileInput    = ref(null);
const isDragging   = ref(false);
const uploading    = ref(false);
const importing    = ref(false);
const error        = ref('');
const importError  = ref('');

const tempPath  = ref('');
const dataStart = ref(2);
const headers   = ref({});
const mapping   = reactive({});
const results   = ref({ imported: 0, skipped: 0, failed: 0, errors: [] });

const dbFieldMap = {
  'name': 'name', 'company': 'name', 'hotel': 'name', 'location': 'name', 'organis': 'name',
  'date created': 'date_created', 'date_created': 'date_created', 'created': 'date_created',
  'pic': 'pic_name', 'person in charge': 'pic_name', 'contact name': 'pic_name',
  'phone': 'phone_mobile', 'tel': 'phone_mobile', 'mobile': 'phone_mobile', 'hp': 'phone_mobile',
  'email': 'email', 'e-mail': 'email',
  'address': 'address', 'addr': 'address',
  'industry': 'industry', 'sector': 'industry',
  'status': 'status',
  'client type': 'client_type', 'type': 'client_type',
  'product': 'category', 'interest': 'category', 'category': 'category',
  'assign': 'assigned_user', 'agent': 'assigned_user', 'salesperson': 'assigned_user', 'user': 'assigned_user',
  'remark': 'remark', 'note': 'remark', 'notes': 'remark', 'comment': 'remark',
};

function autoMatch(headerName) {
  const h = headerName.toLowerCase();
  for (const [key, val] of Object.entries(dbFieldMap)) {
    if (h.includes(key)) return val;
  }
  return null;
}

// Pre-computed map of colLetter → matched field so the template doesn't
// recalculate autoMatch three times per column on every render cycle.
const autoMatchMap = computed(() => {
  const map = {};
  for (const [col, name] of Object.entries(headers.value)) {
    map[col] = autoMatch(name);
  }
  return map;
});

function onFileChange(e) {
  const f = e.target.files[0];
  if (f) { selectedFile.value = f; error.value = ''; }
}

function onDrop(e) {
  isDragging.value = false;
  const f = e.dataTransfer.files[0];
  if (f) { selectedFile.value = f; error.value = ''; }
}

async function uploadFile() {
  if (!selectedFile.value) return;
  uploading.value = true;
  error.value     = '';

  const fd = new FormData();
  fd.append('file', selectedFile.value);

  try {
    const { data } = await axios.post('/v1/import/preview', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    tempPath.value  = data.temp_path;
    dataStart.value = data.data_start;
    headers.value   = data.headers;

    Object.keys(mapping).forEach(k => delete mapping[k]);
    for (const [col, headerName] of Object.entries(data.headers)) {
      mapping[col] = autoMatch(headerName) ?? '';
    }

    step.value = 2;
  } catch (e) {
    const data = e.response?.data;
    error.value = data?.error ?? data?.message ?? 'Failed to scan file. Make sure PhpSpreadsheet is installed.';
  } finally {
    uploading.value = false;
  }
}

async function processImport() {
  importing.value = true;
  importError.value = '';

  try {
    const { data } = await axios.post('/v1/import/process', {
      temp_path:  tempPath.value,
      data_start: dataStart.value,
      mapping,
    });
    results.value = data;
    step.value    = 3;
  } catch (e) {
    importError.value = e.response?.data?.error ?? 'Import failed.';
    step.value        = 3;
  } finally {
    importing.value = false;
  }
}

function reset() {
  step.value         = 1;
  selectedFile.value = null;
  error.value        = '';
  importError.value  = '';
  results.value      = { imported: 0, skipped: 0, failed: 0, errors: [] };
  if (fileInput.value) fileInput.value.value = '';
}
</script>

<style scoped>
/* ── Page shell ─────────────────────────────────── */
.page          { padding: 28px 32px; max-width: 820px; }
.page-header   { margin-bottom: 24px; }
.page-title    { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

/* ── Step indicator ─────────────────────────────── */
.stepper   { display: flex; align-items: center; margin-bottom: 24px; }
.step      { display: flex; align-items: center; gap: 8px; }
.step-dot  {
  width: 28px; height: 28px; border-radius: 50%;
  border: 2px solid var(--border); background: var(--surface);
  color: var(--text-3); font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background 0.2s, border-color 0.2s;
}
.step.active .step-dot { border-color: var(--primary); background: var(--primary); color: #fff; }
.step.done   .step-dot { border-color: #22c55e; background: #22c55e; color: #fff; }
.step-lbl          { font-size: 13px; font-weight: 600; color: var(--text-3); white-space: nowrap; }
.step.active .step-lbl { color: var(--primary); }
.step.done   .step-lbl { color: #15803d; }
.step-line         { flex: 1; height: 2px; background: var(--border); min-width: 32px; margin: 0 8px; transition: background 0.2s; }
.step-line.filled  { background: var(--primary); }

/* ── Card ───────────────────────────────────────── */
.card       { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); padding: 28px 32px; }
.card-title { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0 0 4px; }
.card-sub   { font-size: 13px; color: var(--text-3); margin-bottom: 24px; }

/* ── Alert ──────────────────────────────────────── */
.msg-box   { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 20px; font-weight: 500; }
.error-box { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Drop zone ──────────────────────────────────── */
.drop-zone       { border: 2px dashed var(--border); border-radius: var(--radius); padding: 40px 20px; background: var(--surface-2); cursor: pointer; text-align: center; margin-bottom: 20px; position: relative; transition: all 0.2s; }
.drop-zone:hover,
.drop-zone.dragover { background: var(--primary-soft); border-color: var(--primary); }
.drop-zone.has-file { background: #f0fdf4; border-color: #22c55e; border-style: solid; }
.file-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.drop-icon  { display: flex; justify-content: center; margin-bottom: 10px; }
.drop-main  { font-size: 15px; font-weight: 600; color: var(--text-1); }
.drop-sub   { font-size: 13px; color: var(--text-3); margin-top: 5px; }

/* ── Buttons ────────────────────────────────────── */
.btn-primary {
  padding: 0 20px; height: 40px; background: var(--primary); color: #fff;
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600;
  cursor: pointer; box-shadow: 0 6px 18px -6px rgba(29,78,216,0.45);
  transition: background 0.15s, box-shadow 0.15s;
  display: inline-flex; align-items: center; justify-content: center;
}
.btn-primary:hover               { background: var(--primary-hover); }
.btn-primary:disabled            { background: var(--border); color: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-primary.btn-full            { width: 100%; height: 46px; font-size: 15px; }
.btn-primary.btn-link            { text-decoration: none; }

.btn-ghost {
  padding: 0 18px; height: 40px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; cursor: pointer;
  transition: background 0.15s;
  display: inline-flex; align-items: center;
}
.btn-ghost:hover { background: var(--border); color: var(--text-1); }

/* ── Action row ─────────────────────────────────── */
.action-row         { display: flex; gap: 8px; justify-content: flex-end; }
.result-actions     { margin-top: 20px; }

/* ── Smart notice ───────────────────────────────── */
.smart-notice { display: flex; align-items: center; gap: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #15803d; font-weight: 600; }

/* ── Column mapping ─────────────────────────────── */
.map-list     { display: flex; flex-direction: column; gap: 8px; max-height: 420px; overflow-y: auto; padding-right: 4px; margin-bottom: 24px; }
.map-row      { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: center; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 16px; }
.map-row.auto-row { border-color: #bbf7d0; background: #f0fdf4; }
.map-col-info { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.col-letter   { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-3); }
.col-name     { font-size: 14px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.auto-badge   { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 700; color: #15803d; background: #dcfce7; padding: 2px 7px; border-radius: 999px; width: fit-content; }
.map-select   { height: 38px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); background: var(--surface); outline: none; width: 100%; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
.map-select:focus          { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.map-select.auto-matched   { border-color: #86efac; }

/* ── Result banner ──────────────────────────────── */
.result-banner  { border-radius: var(--radius); padding: 24px 28px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 20px; }
.result-success { background: #f0fdf4; border: 1px solid #bbf7d0; }
.result-error   { background: #fef2f2; border: 1px solid #fecaca; }
.result-title   { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.result-success .result-title { color: #15803d; }
.result-error   .result-title { color: #991b1b; }
.result-desc    { font-size: 13.5px; margin: 0; }
.result-success .result-desc  { color: #15803d; }
.result-error   .result-desc  { color: #991b1b; }
.big-num        { font-size: 44px; font-weight: 800; color: #15803d; }

/* ── Summary cards ──────────────────────────────── */
.summary-row           { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.summary-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
.summary-card          { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 24px; box-shadow: var(--shadow-xs); border-top: 3px solid var(--border); }
.summary-card.imported { border-top-color: #22c55e; }
.summary-card.skipped  { border-top-color: var(--text-3); }
.summary-card.failed   { border-top-color: #f97316; }
.summary-label         { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-2); margin-bottom: 6px; }
.summary-value         { font-size: 36px; font-weight: 800; color: var(--text-1); }
.summary-card.failed .summary-value { color: #c2410c; }

/* ── Failed row details ─────────────────────────── */
.error-detail       { background: #fff7ed; border: 1px solid #fed7aa; border-radius: var(--radius); padding: 16px 20px; margin-bottom: 20px; }
.error-detail-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #9a3412; margin-bottom: 10px; }
.error-row          { display: grid; grid-template-columns: 60px 1fr 2fr; gap: 10px; padding: 6px 0; border-top: 1px solid #fed7aa; font-size: 12.5px; }
.err-row-num        { font-weight: 700; color: #9a3412; white-space: nowrap; }
.err-name           { color: var(--text-1); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.err-reason         { color: #c2410c; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 768px) {
  .page         { padding: 16px 12px; }
  .step-lbl     { display: none; }
  .card         { padding: 20px 16px; }
  .map-row      { grid-template-columns: 1fr; }
  .result-banner { flex-direction: column; align-items: flex-start; }
  .summary-row,
  .summary-row.three-col { grid-template-columns: 1fr; }
  .action-row   { flex-direction: column-reverse; }
  .btn-primary,
  .btn-ghost    { width: 100%; justify-content: center; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
}
</style>
