<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Import Data</h1>
        <p>Upload an Excel or CSV file to add records into the system.</p>
      </div>
      <div class="step-indicator">
        <div class="step-pill" :class="{ active: step >= 1 }">1 &nbsp;Upload File</div>
        <div class="step-arrow">›</div>
        <div class="step-pill" :class="{ active: step >= 2 }">2 &nbsp;Map Columns</div>
        <div class="step-arrow">›</div>
        <div class="step-pill" :class="{ active: step >= 3 }">3 &nbsp;Results</div>
      </div>
    </div>

    <!-- Step 1: Upload -->
    <div v-if="step === 1" class="card">
      <div class="card-title">Select file to import</div>
      <div class="card-sub">Supported: .xlsx, .xls, .csv &nbsp;·&nbsp; The system auto-detects column headers.</div>

      <div v-if="error" class="alert error">⚠ {{ error }}</div>

      <div class="drop-zone" :class="{ 'has-file': selectedFile, dragover: isDragging }"
           @dragover.prevent="isDragging = true"
           @dragleave="isDragging = false"
           @drop.prevent="onDrop">
        <input type="file" accept=".xls,.xlsx,.csv" @change="onFileChange" ref="fileInput" class="file-input">
        <div class="drop-icon">{{ selectedFile ? '✓' : '📁' }}</div>
        <div class="drop-main">{{ selectedFile ? selectedFile.name : 'Drag & drop your file here' }}</div>
        <div class="drop-sub">{{ selectedFile ? '' : 'or click to browse' }}</div>
      </div>

      <button class="submit-btn" :disabled="!selectedFile || uploading" @click="uploadFile">
        {{ uploading ? 'Scanning file…' : 'Next: Map Columns →' }}
      </button>
    </div>

    <!-- Step 2: Map columns -->
    <div v-if="step === 2" class="card">
      <div class="card-title">Map your columns to database fields</div>
      <div class="card-sub">We found {{ Object.keys(headers).length }} columns. Tell us what each one contains.</div>

      <div class="smart-notice">✓ Smart Scan detected headers. Data starts from row {{ dataStart }}.</div>

      <div class="map-list">
        <div v-for="(headerName, colLetter) in headers" :key="colLetter"
             class="map-row" :class="{ 'auto-row': autoMatch(headerName) }">
          <div class="map-col-info">
            <span class="col-letter">Col {{ colLetter }}</span>
            <span class="col-name">{{ headerName }}</span>
            <span v-if="autoMatch(headerName)" class="auto-badge">✓ Auto-matched</span>
          </div>
          <select class="map-select" :class="{ 'auto-matched': autoMatch(headerName) }" v-model="mapping[colLetter]">
            <option value="">Ignore / Do Not Import</option>
            <option value="name">Company Name (Required)</option>
            <option value="address">Full Address</option>
            <option value="pic_name">Contact Person Name</option>
            <option value="phone_mobile">Contact Phone / Mobile</option>
            <option value="email">Contact Email</option>
            <option value="industry">Industry</option>
            <option value="status">Status</option>
            <option value="client_type">Client Type (A1, A2…)</option>
            <option value="category">Product Category</option>
            <option value="assigned_user">Assigned Sales Person</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:12px">
        <button class="import-btn" :disabled="importing" @click="processImport">
          {{ importing ? 'Importing…' : 'Start Import →' }}
        </button>
        <button class="back-btn" @click="step = 1">← Back</button>
      </div>
    </div>

    <!-- Step 3: Results -->
    <div v-if="step === 3" class="card">
      <div class="result-banner" :class="importError ? 'error' : 'success'">
        <div>
          <h2>{{ importError ? 'Import Failed' : 'Import Complete' }}</h2>
          <p>{{ importError || `${results.imported.toLocaleString()} records imported, ${results.skipped.toLocaleString()} duplicates skipped.` }}</p>
        </div>
        <div v-if="!importError" class="big-num">{{ results.imported.toLocaleString() }}</div>
      </div>

      <div v-if="!importError" class="summary-row">
        <div class="summary-card imported">
          <div class="summary-label">New records added</div>
          <div class="summary-value">{{ results.imported.toLocaleString() }}</div>
        </div>
        <div class="summary-card skipped">
          <div class="summary-label">Duplicates skipped</div>
          <div class="summary-value">{{ results.skipped.toLocaleString() }}</div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:20px">
        <router-link to="/crm" class="import-btn" style="text-decoration:none;text-align:center">View in CRM Dashboard →</router-link>
        <button class="back-btn" @click="reset">Import more data</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
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
const results   = ref({ imported: 0, skipped: 0 });

const dbFieldMap = {
  'name': 'name', 'company': 'name', 'hotel': 'name', 'location': 'name', 'organis': 'name',
  'pic': 'pic_name', 'person in charge': 'pic_name', 'contact name': 'pic_name',
  'phone': 'phone_mobile', 'tel': 'phone_mobile', 'mobile': 'phone_mobile', 'hp': 'phone_mobile',
  'email': 'email', 'e-mail': 'email',
  'address': 'address', 'addr': 'address',
  'industry': 'industry', 'sector': 'industry',
  'status': 'status',
  'client type': 'client_type', 'type': 'client_type',
  'product': 'category', 'interest': 'category', 'category': 'category',
  'assign': 'assigned_user', 'agent': 'assigned_user', 'salesperson': 'assigned_user',
};

function autoMatch(headerName) {
  const h = headerName.toLowerCase();
  for (const [key, val] of Object.entries(dbFieldMap)) {
    if (h.includes(key)) return val;
  }
  return null;
}

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

    // Apply auto-mapping
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
  results.value      = { imported: 0, skipped: 0 };
  if (fileInput.value) fileInput.value.value = '';
}
</script>

<style scoped>
.page { max-width: 820px; margin: 0 auto; padding: 28px 24px; }

.page-banner { background:linear-gradient(135deg,#7c2d12,#f97316); border-radius:10px; padding:26px 32px; margin-bottom:24px; color:white; display:flex; justify-content:space-between; align-items:center; gap:20px; }
.page-banner h1 { font-size:21px; font-weight:700; margin:0 0 4px; }
.page-banner p  { font-size:13px; opacity:0.8; margin:0; }
.step-indicator { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.step-pill { display:flex; align-items:center; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.6); white-space:nowrap; }
.step-pill.active { background:rgba(255,255,255,0.95); color:#c2410c; }
.step-arrow { color:rgba(255,255,255,0.4); font-size:14px; }

.card { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.07); padding:28px 32px; }
.card-title { font-size:15px; font-weight:700; color:#1e293b; margin:0 0 4px; }
.card-sub   { font-size:13px; color:#64748b; margin-bottom:24px; }

.alert { display:flex; align-items:flex-start; gap:10px; padding:14px 16px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:500; }
.alert.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

.drop-zone { border:2px dashed #e2e8f0; border-radius:10px; padding:40px 20px; background:#fafafa; cursor:pointer; text-align:center; margin-bottom:20px; position:relative; transition:all 0.2s; }
.drop-zone:hover, .drop-zone.dragover { background:#fff7ed; border-color:#f97316; }
.drop-zone.has-file { background:#f0fdf4; border-color:#22c55e; border-style:solid; }
.file-input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.drop-icon { font-size:36px; margin-bottom:10px; }
.drop-main { font-size:15px; font-weight:600; color:#374151; }
.drop-sub  { font-size:13px; color:#94a3b8; margin-top:5px; }

.submit-btn { width:100%; height:46px; background:#f97316; color:white; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; }
.submit-btn:disabled { background:#cbd5e1; color:#94a3b8; cursor:not-allowed; }

.smart-notice { display:flex; align-items:center; gap:10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px; color:#15803d; font-weight:600; }
.map-list { display:flex; flex-direction:column; gap:8px; max-height:420px; overflow-y:auto; padding-right:4px; margin-bottom:24px; }
.map-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; }
.map-row.auto-row { border-color:#bbf7d0; background:#f0fdf4; }
.map-col-info { display:flex; flex-direction:column; gap:3px; min-width:0; }
.col-letter { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; }
.col-name   { font-size:14px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.auto-badge { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; color:#15803d; background:#dcfce7; padding:2px 7px; border-radius:10px; width:fit-content; }
.map-select { height:38px; padding:0 10px; border:1.5px solid #e2e8f0; border-radius:7px; font-size:13px; color:#374151; background:white; outline:none; width:100%; }
.map-select.auto-matched { border-color:#86efac; }

.import-btn { flex:1; height:46px; background:#3b82f6; color:white; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.import-btn:disabled { background:#cbd5e1; cursor:not-allowed; }
.back-btn { height:46px; padding:0 22px; background:#f1f5f9; color:#475569; border:1.5px solid #e2e8f0; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }

.result-banner { border-radius:10px; padding:26px 32px; margin-bottom:20px; color:white; display:flex; justify-content:space-between; align-items:center; gap:20px; }
.result-banner.success { background:linear-gradient(135deg,#14532d,#16a34a); }
.result-banner.error   { background:linear-gradient(135deg,#7f1d1d,#dc2626); }
.result-banner h2 { font-size:20px; font-weight:700; margin:0 0 4px; }
.result-banner p  { font-size:14px; opacity:0.85; margin:0; }
.big-num { font-size:44px; font-weight:800; }

.summary-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.summary-card { background:white; border-radius:10px; padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,0.07); border-top:3px solid #e2e8f0; }
.summary-card.imported { border-top-color:#22c55e; }
.summary-card.skipped  { border-top-color:#94a3b8; }
.summary-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#94a3b8; margin-bottom:6px; }
.summary-value { font-size:36px; font-weight:800; color:#1e293b; }

/* Responsive */
@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .page-banner { flex-direction: column; align-items: flex-start; gap: 12px; padding: 20px; }
  .step-indicator { flex-wrap: wrap; }
  .card { padding: 20px 16px; }
  .map-row { grid-template-columns: 1fr; }
  .result-banner { flex-direction: column; align-items: flex-start; }
  .summary-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .page { padding: 12px 8px; }
}
</style>
