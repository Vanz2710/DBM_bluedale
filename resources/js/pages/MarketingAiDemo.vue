<template>
  <div class="page">
    <div class="page-banner">
      <div>
        <h1>Marketing AI Demo</h1>
        <p>Example workflow for personalized segments, follow-up content, and next-best actions.</p>
      </div>
    </div>

    <div class="layout-grid">
      <section class="panel">
        <div class="section-head">
          <span class="step">1</span>
          <div>
            <h2>Dynamic Micro-Segmentation</h2>
            <p>AI groups contacts by behavior instead of fixed lists.</p>
          </div>
        </div>

        <div class="segment-builder">
          <label>Segment Rule</label>
          <select v-model="selectedSegment">
            <option value="pricing">Viewed pricing twice but no demo booked</option>
            <option value="inactive">Existing clients inactive for 30 days</option>
            <option value="hot">High-value leads with recent engagement</option>
          </select>
        </div>

        <div class="segment-summary">
          <div>
            <span>Live Segment</span>
            <strong>{{ segmentLabel }}</strong>
          </div>
          <div>
            <span>Contacts Found</span>
            <strong>{{ filteredContacts.length }}</strong>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>Company</th>
              <th>Behavior</th>
              <th>Value</th>
              <th>Owner</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="contact in filteredContacts" :key="contact.company">
              <td>{{ contact.company }}</td>
              <td>{{ contact.behavior }}</td>
              <td>RM {{ contact.value.toLocaleString() }}</td>
              <td>{{ contact.owner }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="panel">
        <div class="section-head">
          <span class="step">2</span>
          <div>
            <h2>Generative Email Assistant</h2>
            <p>AI drafts a follow-up based on the selected company history.</p>
          </div>
        </div>

        <div class="segment-builder">
          <label>Target Company</label>
          <select v-model="selectedCompany">
            <option v-for="contact in filteredContacts" :key="contact.company" :value="contact.company">
              {{ contact.company }}
            </option>
          </select>
        </div>

        <div class="tone-row">
          <button :class="{ active: tone === 'friendly' }" @click="tone = 'friendly'">Friendly</button>
          <button :class="{ active: tone === 'professional' }" @click="tone = 'professional'">Professional</button>
          <button :class="{ active: tone === 'urgent' }" @click="tone = 'urgent'">Urgent</button>
        </div>

        <div class="email-card">
          <div class="email-meta">
            <span>Subject</span>
            <strong>{{ emailSubject }}</strong>
          </div>
          <textarea v-model="draftEmail"></textarea>
          <div class="actions">
            <button class="btn-primary" @click="generateEmail">Generate Draft</button>
            <button class="btn-secondary" @click="copyStyle">Match Brand Tone</button>
          </div>
        </div>
      </section>
    </div>

    <section class="panel action-panel">
      <div class="section-head">
        <span class="step">3</span>
        <div>
          <h2>Next-Best-Action Recommendations</h2>
          <p>The CRM suggests the next move for each contact based on behavior and timing.</p>
        </div>
      </div>

      <div class="action-grid">
        <div v-for="contact in filteredContacts" :key="contact.company" class="action-card">
          <div class="card-top">
            <h3>{{ contact.company }}</h3>
            <span>{{ contact.owner }}</span>
          </div>
          <p>{{ nextAction(contact) }}</p>
          <div class="action-buttons">
            <button>Create Task</button>
            <button>Draft Email</button>
            <button>Schedule Call</button>
          </div>
        </div>
      </div>
    </section>

    <section class="explain-panel">
      <h2>What This Feature Means</h2>
      <div class="explain-grid">
        <div>
          <span>Before</span>
          <p>Staff manually filter contacts, guess who to follow up, and write every message from scratch.</p>
        </div>
        <div>
          <span>With AI</span>
          <p>The CRM watches useful behavior, builds a live target list, drafts the right message, then recommends the next step.</p>
        </div>
        <div>
          <span>Important</span>
          <p>Staff should still review before sending. AI prepares the work; humans approve it.</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const selectedSegment = ref('pricing');
const selectedCompany = ref('Royal Gold NTPM');
const tone = ref('friendly');
const draftEmail = ref('');

const contacts = [
  { company: 'Royal Gold NTPM', behavior: 'Viewed pricing 2 times in 48 hours', value: 18000, owner: 'AA', tags: ['pricing', 'hot'] },
  { company: 'Life Sauce', behavior: 'Opened proposal but no reply', value: 12500, owner: 'NS', tags: ['hot'] },
  { company: 'Maxim Furniture', behavior: 'No activity for 34 days', value: 7200, owner: 'AA', tags: ['inactive'] },
  { company: 'All Kurma Sdn Bhd', behavior: 'Viewed pricing 3 times, no demo booked', value: 22000, owner: 'NS', tags: ['pricing', 'hot'] },
  { company: 'Amani Decor', behavior: 'No reply after campaign', value: 5200, owner: 'MK', tags: ['inactive'] },
];

const segmentLabel = computed(() => {
  if (selectedSegment.value === 'pricing') return 'Pricing interest, no demo booked';
  if (selectedSegment.value === 'inactive') return 'Inactive existing clients';
  return 'High-value engaged leads';
});

const filteredContacts = computed(() => contacts.filter((contact) => contact.tags.includes(selectedSegment.value)));

const selectedContact = computed(() => (
  filteredContacts.value.find((contact) => contact.company === selectedCompany.value) ?? filteredContacts.value[0]
));

const emailSubject = computed(() => {
  if (!selectedContact.value) return 'Follow-up';
  if (selectedSegment.value === 'pricing') return `Quick follow-up on ${selectedContact.value.company} package options`;
  if (selectedSegment.value === 'inactive') return `Checking in with ${selectedContact.value.company}`;
  return `Next step for ${selectedContact.value.company}`;
});

watch(filteredContacts, (list) => {
  if (!list.some((contact) => contact.company === selectedCompany.value)) {
    selectedCompany.value = list[0]?.company ?? '';
  }
  generateEmail();
}, { immediate: true });

watch([selectedCompany, tone], generateEmail);

function generateEmail() {
  const contact = selectedContact.value;
  if (!contact) {
    draftEmail.value = '';
    return;
  }

  const greeting = tone.value === 'professional' ? 'Good day' : 'Hi';
  const close = tone.value === 'urgent'
    ? 'Can we arrange a quick call today or tomorrow?'
    : 'Would you like me to share the next best package option?';

  draftEmail.value = `${greeting},

I noticed ${contact.company} recently showed interest: ${contact.behavior.toLowerCase()}.

Based on that, I think we can recommend a focused package around your current marketing needs. The estimated opportunity value is RM ${contact.value.toLocaleString()}, so I wanted to follow up while the timing is still fresh.

${close}

Thank you.`;
}

function copyStyle() {
  tone.value = 'professional';
  generateEmail();
}

function nextAction(contact) {
  if (selectedSegment.value === 'pricing') {
    return `Send a package comparison and offer a 15-minute demo. ${contact.company} is showing buying intent.`;
  }
  if (selectedSegment.value === 'inactive') {
    return `Create a reactivation task and send a check-in message. This client has been quiet too long.`;
  }
  return `Call within 24 hours and send a tailored proposal. This lead is high value and recently active.`;
}
</script>

<style scoped>
.page { padding: 24px 28px; color: #172033; }
.page-banner {
  background: linear-gradient(135deg, #164e63, #7c3aed);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 18px; color: white;
}
.page-banner h1 { margin: 0 0 5px; font-size: 22px; font-weight: 800; }
.page-banner p { margin: 0; font-size: 13px; opacity: 0.88; }
.layout-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
.panel, .explain-panel {
  background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 20px;
}
.section-head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
.step {
  width: 34px; height: 34px; border-radius: 8px; background: #0f766e; color: white;
  display: inline-flex; align-items: center; justify-content: center; font-weight: 900; flex: 0 0 auto;
}
h2 { margin: 0 0 4px; font-size: 18px; }
.section-head p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.45; }
.segment-builder { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.segment-builder label { color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; }
select, textarea {
  border: 1.5px solid #dbe3ee; border-radius: 8px; background: white; color: #1f2937; outline: none;
}
select { height: 40px; padding: 0 12px; font-size: 13px; }
select:focus, textarea:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.segment-summary { display: grid; grid-template-columns: 1fr 150px; gap: 10px; margin-bottom: 14px; }
.segment-summary div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
.segment-summary span { display: block; color: #64748b; font-size: 11px; font-weight: 800; margin-bottom: 5px; }
.segment-summary strong { color: #172033; font-size: 16px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { background: #f8fafc; color: #64748b; text-transform: uppercase; letter-spacing: 0.7px; font-size: 10px; padding: 9px; text-align: left; }
td { padding: 10px 9px; border-top: 1px solid #e2e8f0; color: #334155; }
.tone-row { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.tone-row button, .action-buttons button {
  border: 1px solid #dbe3ee; background: white; color: #334155; border-radius: 7px; padding: 7px 10px;
  font-size: 12px; font-weight: 800; cursor: pointer;
}
.tone-row button.active { background: #0f766e; color: white; border-color: #0f766e; }
.email-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
.email-meta { margin-bottom: 10px; }
.email-meta span { display: block; color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-bottom: 5px; }
.email-meta strong { font-size: 13px; color: #172033; }
textarea { width: 100%; min-height: 230px; resize: vertical; padding: 12px; font-size: 13px; line-height: 1.5; }
.actions { display: flex; gap: 10px; margin-top: 12px; }
.btn-primary, .btn-secondary {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px; font-size: 13px; font-weight: 800; cursor: pointer;
}
.btn-primary { background: #0f766e; color: white; }
.btn-secondary { background: #e8eef5; color: #334155; }
.action-panel { margin-top: 18px; }
.action-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.action-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
.card-top { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.card-top h3 { margin: 0; font-size: 15px; color: #172033; }
.card-top span { color: #0f766e; font-size: 12px; font-weight: 900; }
.action-card p { color: #334155; font-size: 13px; line-height: 1.45; min-height: 56px; }
.action-buttons { display: flex; gap: 7px; flex-wrap: wrap; }
.action-buttons button:hover { border-color: #0f766e; color: #0f766e; }
.explain-panel { margin-top: 18px; }
.explain-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
.explain-grid div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
.explain-grid span { color: #7c3aed; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; }
.explain-grid p { margin: 8px 0 0; color: #334155; font-size: 13px; line-height: 1.45; }

@media (max-width: 1100px) {
  .layout-grid, .action-grid, .explain-grid { grid-template-columns: 1fr; }
}

@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .segment-summary { grid-template-columns: 1fr; }
  .actions { flex-direction: column; }
}
</style>
