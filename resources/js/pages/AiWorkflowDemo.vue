<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>AI Workflow Demo</h1>
        <p>Example screens for ambient data capture and natural language CRM search.</p>
      </div>
    </div>

    <div class="demo-grid">
      <section class="panel">
        <div class="panel-head">
          <span class="step-pill">1</span>
          <div>
            <h2>Ambient Data Capture</h2>
            <p>Staff paste text from email, WhatsApp, LinkedIn, or a signature. AI detects useful CRM fields.</p>
          </div>
        </div>

        <textarea v-model="sourceText" class="source-box"></textarea>

        <div class="actions">
          <button class="btn-primary" @click="extractData">Extract Data</button>
          <button class="btn-secondary" @click="resetSource">Reset Example</button>
        </div>

        <div class="capture-layout">
          <div class="mini-card">
            <h3>Detected Fields</h3>
            <div v-for="field in extractedFields" :key="field.label" class="field-row">
              <span>{{ field.label }}</span>
              <strong>{{ field.value }}</strong>
            </div>
          </div>

          <div class="mini-card update-card">
            <h3>CRM Update Preview</h3>
            <div class="update-line good">Matched company: {{ extracted.company || 'Waiting...' }}</div>
            <div class="update-line">Add PIC: {{ extracted.name || '-' }}</div>
            <div class="update-line">Update job title: {{ extracted.title || '-' }}</div>
            <div class="update-line">Update phone: {{ extracted.phone || '-' }}</div>
            <button class="btn-disabled" disabled>Approve Update</button>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="step-pill">2</span>
          <div>
            <h2>Natural Language Query</h2>
            <p>Staff ask in normal language. AI converts it into filters and shows matching CRM records.</p>
          </div>
        </div>

        <div class="query-box">
          <input v-model="question" @keyup.enter="runQuery" placeholder="Ask CRM a question">
          <button class="btn-primary" @click="runQuery">Run Query</button>
        </div>

        <div class="examples">
          <button v-for="example in examples" :key="example" @click="useExample(example)">
            {{ example }}
          </button>
        </div>

        <div class="mini-card">
          <h3>AI Filter Plan</h3>
          <div class="filter-plan">{{ filterPlan }}</div>
        </div>

        <div class="results-card">
          <div class="results-head">
            <h3>Example Results</h3>
            <span>{{ results.length }} records</span>
          </div>
          <table>
            <thead>
              <tr>
                <th>Company</th>
                <th>Value</th>
                <th>Close Date</th>
                <th>Last Contact</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in results" :key="row.company">
                <td>{{ row.company }}</td>
                <td>RM {{ row.value.toLocaleString() }}</td>
                <td>{{ row.closeDate }}</td>
                <td>{{ row.lastContact }}</td>
                <td><span class="status-chip">{{ row.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <section class="flow-panel">
      <h2>How This Would Work In Your CRM</h2>
      <div class="flow-steps">
        <div class="flow-step">
          <span>Input</span>
          <strong>Email, WhatsApp, LinkedIn, notes, or typed question.</strong>
        </div>
        <div class="flow-step">
          <span>AI Reads</span>
          <strong>Detects names, phone numbers, job titles, company changes, and query intent.</strong>
        </div>
        <div class="flow-step">
          <span>Human Approval</span>
          <strong>Staff review changes before saving to CRM.</strong>
        </div>
        <div class="flow-step">
          <span>CRM Updates</span>
          <strong>Records, PICs, tasks, and follow-ups stay cleaner with less manual typing.</strong>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const defaultSource = `Hi team,

Please update the quotation for Kurnia Event Sdn Bhd.

Regards,
Nadia Salleh
Marketing Manager
Kurnia Event Sdn Bhd
Mobile: 012-448 9910`;

const sourceText = ref(defaultSource);
const extracted = ref({
  name: 'Nadia Salleh',
  title: 'Marketing Manager',
  company: 'Kurnia Event Sdn Bhd',
  phone: '012-448 9910',
});

const question = ref('Show me open opportunities over RM 10,000 closing this month that have not been contacted in a week.');
const filterPlan = ref('Status = open, value > RM 10,000, close date = this month, last contact older than 7 days.');

const examples = [
  'Show companies with no PIC phone number.',
  'Which open deals above RM 10,000 need follow-up?',
  'List clients added this month with no task yet.',
];

const sampleDeals = [
  { company: 'Kurnia Event Sdn Bhd', value: 18500, closeDate: '24/05/2026', lastContact: '08/05/2026', status: 'Open' },
  { company: 'Royal Gold NTPM', value: 12800, closeDate: '29/05/2026', lastContact: '09/05/2026', status: 'Open' },
  { company: 'Maxim Furniture', value: 9200, closeDate: '21/05/2026', lastContact: '14/05/2026', status: 'Open' },
  { company: 'Life Sauce', value: 23600, closeDate: '30/06/2026', lastContact: '02/05/2026', status: 'Open' },
];

const results = ref(sampleDeals.filter((row) => row.value > 10000 && row.closeDate.endsWith('/05/2026')));

const extractedFields = computed(() => [
  { label: 'PIC Name', value: extracted.value.name || '-' },
  { label: 'Job Title', value: extracted.value.title || '-' },
  { label: 'Company', value: extracted.value.company || '-' },
  { label: 'Phone', value: extracted.value.phone || '-' },
]);

function extractData() {
  const text = sourceText.value;
  const lines = text.split('\n').map((line) => line.trim()).filter(Boolean);
  const phone = text.match(/(\+?6?01[0-9][-\s]?[0-9]{3,4}[-\s]?[0-9]{4})/i)?.[1] ?? '';
  const title = lines.find((line) => /manager|director|executive|officer|sales|marketing/i.test(line)) ?? '';
  const company = lines.find((line) => /sdn bhd|berhad|enterprise|agency|trading/i.test(line)) ?? '';
  const name = lines.find((line) => /^[A-Z][a-z]+(\s[A-Z][a-z]+)+$/.test(line)) ?? '';

  extracted.value = { name, title, company, phone };
}

function resetSource() {
  sourceText.value = defaultSource;
  extractData();
}

function useExample(example) {
  question.value = example;
  runQuery();
}

function runQuery() {
  const q = question.value.toLowerCase();

  if (q.includes('no pic') || q.includes('phone number')) {
    filterPlan.value = 'Find companies where PIC phone is empty or missing.';
    results.value = [
      { company: 'All Kurma Sdn Bhd', value: 0, closeDate: '-', lastContact: '-', status: 'Missing PIC Phone' },
      { company: 'Amani Decor', value: 0, closeDate: '-', lastContact: '-', status: 'Missing PIC Phone' },
    ];
    return;
  }

  if (q.includes('task')) {
    filterPlan.value = 'Created date = this month, task count = 0.';
    results.value = [
      { company: 'Bina Vista Sdn Bhd', value: 0, closeDate: '-', lastContact: '12/05/2026', status: 'No Task' },
      { company: 'Daya Prime Trading', value: 0, closeDate: '-', lastContact: '15/05/2026', status: 'No Task' },
    ];
    return;
  }

  filterPlan.value = 'Status = open, value > RM 10,000, close date = this month, last contact older than 7 days.';
  results.value = sampleDeals.filter((row) => row.value > 10000 && row.closeDate.endsWith('/05/2026'));
}
</script>

<style scoped>
.page { padding: 24px 28px; color: #172033; }
.page-banner {
  background: linear-gradient(135deg, #12324a, #0f766e);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 18px; color: white;
}
.page-banner h1 { font-size: 22px; margin: 0 0 5px; font-weight: 800; }
.page-banner p { margin: 0; font-size: 13px; opacity: 0.88; }

.demo-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
.panel, .flow-panel {
  background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px;
}
.panel-head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
.step-pill {
  width: 34px; height: 34px; border-radius: 8px; background: #0f766e; color: white;
  display: inline-flex; align-items: center; justify-content: center; font-weight: 900; flex: 0 0 auto;
}
h2 { margin: 0 0 4px; font-size: 18px; color: #172033; }
h3 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.7px; color: #52627a; }
.panel-head p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.45; }

.source-box {
  width: 100%; min-height: 188px; border: 1.5px solid #dbe3ee; border-radius: 8px;
  padding: 12px; resize: vertical; font-size: 13px; line-height: 1.5; color: #1f2937; outline: none;
}
.source-box:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.actions, .query-box { display: flex; gap: 10px; margin: 12px 0; }
.btn-primary, .btn-secondary, .btn-disabled {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px; font-size: 13px; font-weight: 800;
}
.btn-primary { background: #0f766e; color: white; cursor: pointer; }
.btn-secondary { background: #e8eef5; color: #334155; cursor: pointer; }
.btn-disabled { background: #cbd5e1; color: #64748b; margin-top: 12px; }

.capture-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mini-card, .results-card {
  border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 14px;
}
.field-row, .update-line {
  display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #e2e8f0;
  font-size: 13px;
}
.field-row:last-child, .update-line:last-of-type { border-bottom: none; }
.field-row span { color: #64748b; }
.field-row strong { text-align: right; color: #172033; }
.update-line { display: block; color: #334155; font-weight: 700; }
.update-line.good { color: #047857; }

.query-box input {
  flex: 1; height: 40px; border: 1.5px solid #dbe3ee; border-radius: 8px; padding: 0 12px; font-size: 13px; outline: none;
}
.query-box input:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.examples { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.examples button {
  border: 1px solid #dbe3ee; background: white; color: #334155; border-radius: 7px; padding: 7px 10px;
  font-size: 12px; font-weight: 700; cursor: pointer;
}
.examples button:hover { border-color: #0f766e; color: #0f766e; }
.filter-plan { color: #334155; font-size: 13px; line-height: 1.45; font-weight: 700; }

.results-card { margin-top: 12px; padding: 0; overflow: hidden; }
.results-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
.results-head h3 { margin: 0; }
.results-head span { color: #64748b; font-size: 12px; font-weight: 800; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { background: white; color: #64748b; text-transform: uppercase; letter-spacing: 0.7px; font-size: 10px; padding: 9px; text-align: left; }
td { padding: 10px 9px; border-top: 1px solid #e2e8f0; color: #334155; }
.status-chip { display: inline-flex; align-items: center; min-height: 24px; border-radius: 999px; padding: 0 9px; background: #dcfce7; color: #166534; font-weight: 800; }

.flow-panel { margin-top: 18px; }
.flow-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
.flow-step { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; }
.flow-step span { display: block; color: #0f766e; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 8px; }
.flow-step strong { color: #334155; font-size: 13px; line-height: 1.45; }

@media (max-width: 1100px) {
  .demo-grid, .capture-layout, .flow-steps { grid-template-columns: 1fr; }
}

@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .actions, .query-box { flex-direction: column; }
  .query-box input, .btn-primary, .btn-secondary { width: 100%; }
}
</style>
