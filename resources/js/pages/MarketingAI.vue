<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Marketing AI</h1>
        <p class="page-subtitle">AI-powered segmentation, email drafting, and next-best-action recommendations for your CRM contacts.</p>
      </div>
      <span class="ai-badge">Beta</span>
    </div>

    <div class="ai-banner">
      <div class="banner-inner">
        <div class="banner-icon">&#x2728;</div>
        <div>
          <h2>Intelligent Marketing Automation</h2>
          <p>Three AI workflows — segment your contacts, draft personalised emails, and surface the best next action per client.</p>
        </div>
      </div>
    </div>

    <!-- Section 1: Dynamic Micro-Segmentation -->
    <div class="ai-section card">
      <div class="section-header">
        <div class="section-number">01</div>
        <div>
          <h3>Dynamic Micro-Segmentation</h3>
          <p>AI groups contacts by recent behaviour so you target the right clients at the right moment.</p>
        </div>
      </div>

      <div class="segment-tabs">
        <button
          v-for="seg in segments"
          :key="seg.key"
          type="button"
          class="seg-btn"
          :class="{ active: activeSegment === seg.key }"
          @click="activeSegment = seg.key"
        >
          <span class="seg-count">{{ seg.contacts.length }}</span>
          {{ seg.label }}
        </button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Behaviour Signal</th>
              <th>Est. Value</th>
              <th>Owner</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="activeContacts.length === 0">
              <td colspan="5" class="empty-state">No contacts in this segment.</td>
            </tr>
            <tr v-for="contact in activeContacts" :key="contact.id">
              <td><strong>{{ contact.company }}</strong></td>
              <td><span class="behaviour-tag">{{ contact.behaviour }}</span></td>
              <td>{{ contact.value }}</td>
              <td>{{ contact.owner }}</td>
              <td>
                <button type="button" class="btn-ghost btn-sm" @click="draftEmailFor(contact)">Draft Email</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Section 2: Generative Email Assistant -->
    <div class="ai-section card">
      <div class="section-header">
        <div class="section-number">02</div>
        <div>
          <h3>Generative Email Assistant</h3>
          <p>Select a tone and the AI drafts a personalised follow-up based on the contact's history.</p>
        </div>
      </div>

      <div class="email-composer">
        <div class="composer-left">
          <div class="field-group">
            <label class="field-label">Recipient</label>
            <input v-model="emailDraft.recipient" class="field-input" placeholder="Company or contact name" readonly>
          </div>

          <div class="tone-row">
            <label class="field-label">Tone</label>
            <div class="tone-options">
              <button
                v-for="tone in tones"
                :key="tone"
                type="button"
                class="tone-btn"
                :class="{ active: emailDraft.tone === tone }"
                @click="emailDraft.tone = tone; generateEmailDraft()"
              >{{ tone }}</button>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">Subject</label>
            <input v-model="emailDraft.subject" class="field-input" placeholder="AI-generated subject">
          </div>

          <div class="field-group">
            <label class="field-label">Email Body</label>
            <textarea v-model="emailDraft.body" class="field-textarea" rows="8" placeholder="AI-generated body will appear here..."></textarea>
          </div>

          <div class="composer-actions">
            <button type="button" class="btn-ghost" @click="generateEmailDraft">&#x21BB; Regenerate</button>
            <button type="button" class="btn-primary" @click="copyToClipboard">Copy to Clipboard</button>
          </div>
          <div v-if="copySuccess" class="copy-success">Copied!</div>
        </div>

        <div class="composer-right">
          <div class="email-preview-label">Live Preview</div>
          <div class="email-preview">
            <div class="preview-header">
              <strong>To:</strong> {{ emailDraft.recipient || 'Recipient' }}<br>
              <strong>Subject:</strong> {{ emailDraft.subject || '—' }}
            </div>
            <div class="preview-body">
              <p v-for="(line, i) in previewLines" :key="i">{{ line }}</p>
            </div>
          </div>
          <div class="brand-tone-note">
            <span>&#x26A0;&#xFE0F;</span>
            <span>Always review AI-generated emails before sending. Adjust to match your brand voice.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 3: Next-Best-Action Recommendations -->
    <div class="ai-section card">
      <div class="section-header">
        <div class="section-number">03</div>
        <div>
          <h3>Next-Best-Action Recommendations</h3>
          <p>AI surfaces the most impactful action per client based on their engagement history.</p>
        </div>
      </div>

      <div class="nba-grid">
        <div v-for="rec in recommendations" :key="rec.company" class="nba-card">
          <div class="nba-card-header">
            <div class="nba-avatar">{{ rec.company.slice(0, 2).toUpperCase() }}</div>
            <div>
              <strong>{{ rec.company }}</strong>
              <span class="nba-owner">{{ rec.owner }}</span>
            </div>
          </div>
          <p class="nba-signal">{{ rec.signal }}</p>
          <div class="nba-action-row">
            <span class="nba-action-badge" :class="`badge-${rec.actionType}`">{{ rec.action }}</span>
            <span class="nba-priority" :class="`priority-${rec.priority}`">{{ rec.priority }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Explanation panel -->
    <div class="explainer-card card">
      <h3>How Marketing AI Works</h3>
      <div class="explainer-grid">
        <div class="explainer-item">
          <div class="explainer-icon">&#x1F4CA;</div>
          <strong>Before AI</strong>
          <p>Manual list-building, generic email blasts, no visibility into which contacts need attention.</p>
        </div>
        <div class="explainer-item">
          <div class="explainer-icon">&#x1F916;</div>
          <strong>With AI</strong>
          <p>Behaviour-based segments, personalised emails drafted in seconds, priority-ranked next actions.</p>
        </div>
        <div class="explainer-item">
          <div class="explainer-icon">&#x26A0;&#xFE0F;</div>
          <strong>Important</strong>
          <p>AI suggestions are a starting point — review before sending. Your judgment and relationship context always take precedence.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const activeSegment = ref('viewed-pricing');
const copySuccess = ref(false);
const tones = ['Friendly', 'Professional', 'Urgent'];

const emailDraft = ref({
  recipient: '',
  tone: 'Professional',
  subject: '',
  body: '',
});

const segments = [
  {
    key: 'viewed-pricing',
    label: 'Viewed Pricing Twice',
    contacts: [
      { id: 1, company: 'TechNova Sdn Bhd', behaviour: 'Viewed pricing twice in 7 days', value: 'RM 24,000', owner: 'Ammar' },
      { id: 2, company: 'BuildRight Corp',   behaviour: 'Visited proposal page 3×',          value: 'RM 18,500', owner: 'Sarah' },
      { id: 3, company: 'MediaFirst Bhd',    behaviour: 'Viewed outdoor media packages',     value: 'RM 12,000', owner: 'Ammar' },
    ],
  },
  {
    key: 'inactive',
    label: 'Inactive 30+ Days',
    contacts: [
      { id: 4, company: 'SkyBrand Ventures',  behaviour: 'No activity in 38 days',   value: 'RM 9,800',  owner: 'Raj' },
      { id: 5, company: 'GreenPath Holdings', behaviour: 'Last todo 45 days ago',    value: 'RM 15,200', owner: 'Sarah' },
    ],
  },
  {
    key: 'high-value',
    label: 'High-Value Active',
    contacts: [
      { id: 6, company: 'PrimeMedia Group',   behaviour: 'Booked 3 sites this quarter', value: 'RM 72,000', owner: 'Ammar' },
      { id: 7, company: 'CityMax Retail',     behaviour: '2 deals won this year',       value: 'RM 48,000', owner: 'Raj' },
    ],
  },
];

const activeContacts = computed(() => segments.find((s) => s.key === activeSegment.value)?.contacts ?? []);

const recommendations = [
  { company: 'TechNova Sdn Bhd',  owner: 'Ammar', signal: 'Viewed pricing page twice — high intent signal.',   action: 'Schedule Demo',  actionType: 'demo',   priority: 'High' },
  { company: 'SkyBrand Ventures', owner: 'Raj',   signal: 'No activity in 38 days — risk of churn.',          action: 'Draft Email',    actionType: 'email',  priority: 'Medium' },
  { company: 'PrimeMedia Group',  owner: 'Ammar', signal: 'Active client with repeat bookings — upsell ready.', action: 'Create Task',   actionType: 'task',   priority: 'High' },
  { company: 'GreenPath Holdings',owner: 'Sarah', signal: '45 days since last contact — re-engage now.',       action: 'Schedule Call',  actionType: 'call',   priority: 'Medium' },
];

function draftEmailFor(contact) {
  emailDraft.value.recipient = contact.company;
  generateEmailDraft();
  document.querySelector('.ai-section:nth-child(3)')?.scrollIntoView({ behavior: 'smooth' });
}

function generateEmailDraft() {
  const tone = emailDraft.value.tone;
  const recipient = emailDraft.value.recipient || 'valued client';

  const subjects = {
    Friendly:     `Quick catch-up — ${recipient}`,
    Professional: `Follow-up: Advertising Opportunities for ${recipient}`,
    Urgent:       `Time-sensitive: Confirm your campaign slot — ${recipient}`,
  };

  const bodies = {
    Friendly: `Hi there,\n\nHope you're doing well! I wanted to check in and share a few ideas that might be a great fit for ${recipient}'s upcoming campaigns.\n\nWe have some prime billboard and temp board slots available in your target areas. Would love to walk you through the options over a quick call.\n\nLet me know a good time!\n\nWarm regards,\nBluedale Team`,
    Professional: `Dear ${recipient} Team,\n\nThank you for your continued engagement with Bluedale. Following our recent interactions, I would like to present targeted outdoor advertising opportunities that align with your current marketing objectives.\n\nI am available for a brief consultation at your convenience to discuss available sites and pricing.\n\nKind regards,\nBluedale Integrated`,
    Urgent: `Hi,\n\nThis is a time-sensitive note — the outdoor advertising slots we discussed for ${recipient} are now filling up for the next quarter.\n\nTo secure your preferred locations, I'd recommend confirming by end of this week.\n\nPlease reply or call me directly to lock in your campaign.\n\nBest,\nBluedale Team`,
  };

  emailDraft.value.subject = subjects[tone] ?? subjects.Professional;
  emailDraft.value.body = bodies[tone] ?? bodies.Professional;
}

const previewLines = computed(() => emailDraft.value.body.split('\n').filter((l) => l.trim() !== ''));

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(`Subject: ${emailDraft.value.subject}\n\n${emailDraft.value.body}`);
    copySuccess.value = true;
    setTimeout(() => { copySuccess.value = false; }, 2000);
  } catch (_) {
    copySuccess.value = false;
  }
}
</script>

<style scoped>
.page { padding: 28px 32px; }
.page-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 24px; gap: 16px;
}
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.ai-badge {
  display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px;
  background: var(--primary-soft); color: var(--primary-text); font-size: 11px; font-weight: 700; flex-shrink: 0;
}

.ai-banner {
  background: linear-gradient(135deg, var(--primary) 0%, #0e7490 100%);
  border-radius: var(--radius); padding: 20px 24px; margin-bottom: 24px; color: #fff;
}
.banner-inner { display: flex; align-items: center; gap: 16px; }
.banner-icon { font-size: 28px; flex-shrink: 0; }
.ai-banner h2 { margin: 0 0 4px; font-size: 18px; font-weight: 800; }
.ai-banner p { margin: 0; font-size: 13px; opacity: 0.88; }

.card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-sm); padding: 20px 24px; margin-bottom: 20px;
}

.ai-section { }

.section-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px; }
.section-number {
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 32px; height: 32px; border-radius: var(--radius-sm); background: var(--primary-soft);
  color: var(--primary-text); font-size: 12px; font-weight: 800; font-variant-numeric: tabular-nums;
}
.section-header h3 { margin: 0 0 4px; font-size: 16px; font-weight: 700; color: var(--text-1); }
.section-header p { margin: 0; font-size: 13px; color: var(--text-2); }

/* Segment tabs */
.segment-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.seg-btn {
  display: flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px;
  border: 1.5px solid var(--border); border-radius: 999px; background: var(--surface-2);
  color: var(--text-2); font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.seg-btn.active { border-color: var(--primary); background: var(--primary-soft); color: var(--primary-text); }
.seg-btn:hover:not(.active) { border-color: var(--text-3); color: var(--text-1); }
.seg-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px;
  background: var(--border); color: var(--text-2); font-size: 10px; font-weight: 800;
}
.seg-btn.active .seg-count { background: var(--primary); color: #fff; }

/* Table */
.table-wrap { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead tr { background: var(--surface-2); }
.data-table th {
  padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px; border-bottom: 1px solid var(--border); white-space: nowrap;
}
.data-table td { padding: 12px 14px; color: var(--text-1); border-bottom: 1px solid var(--border-soft); }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--surface-2); }
.behaviour-tag {
  display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
  background: var(--primary-soft); color: var(--primary-text);
}
.empty-state { text-align: center; padding: 36px; color: var(--text-3); font-size: 14px; }

/* Email composer */
.email-composer { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
.composer-left { display: flex; flex-direction: column; gap: 14px; }
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 12px; font-weight: 600; color: var(--text-2); }
.field-input {
  height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); outline: none; transition: border-color 0.15s;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.field-textarea {
  padding: 10px 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); outline: none;
  resize: vertical; line-height: 1.6; font-family: inherit; transition: border-color 0.15s;
}
.field-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.tone-row { display: flex; flex-direction: column; gap: 6px; }
.tone-options { display: flex; gap: 8px; flex-wrap: wrap; }
.tone-btn {
  height: 32px; padding: 0 14px; border: 1.5px solid var(--border); border-radius: 999px;
  background: var(--surface-2); color: var(--text-2); font-size: 12px; font-weight: 600; cursor: pointer;
}
.tone-btn.active { border-color: var(--primary); background: var(--primary-soft); color: var(--primary-text); }
.composer-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-ghost {
  height: 36px; padding: 0 14px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; cursor: pointer;
}
.btn-ghost:hover { background: var(--border); color: var(--text-1); }
.btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }
.btn-primary {
  height: 36px; padding: 0 18px; background: var(--primary); color: var(--primary-on);
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer;
  box-shadow: 0 6px 18px -6px rgba(124,58,237,0.45);
}
.btn-primary:hover { background: var(--primary-hover); }
.copy-success { font-size: 12px; font-weight: 700; color: #16a34a; padding: 2px 0; }

/* Email preview */
.email-preview-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-3); margin-bottom: 8px; }
.email-preview {
  border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; background: var(--surface);
}
.preview-header {
  padding: 12px 16px; background: var(--surface-2); border-bottom: 1px solid var(--border);
  font-size: 12px; color: var(--text-2); line-height: 1.8;
}
.preview-body { padding: 16px; }
.preview-body p { margin: 0 0 12px; font-size: 13px; color: var(--text-1); line-height: 1.6; }
.preview-body p:last-child { margin-bottom: 0; }
.brand-tone-note {
  display: flex; align-items: flex-start; gap: 8px; padding: 10px 14px; margin-top: 12px;
  background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-sm);
  font-size: 12px; color: #92400e; line-height: 1.4;
}

/* Next-Best-Action */
.nba-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.nba-card {
  border: 1px solid var(--border); border-radius: var(--radius); padding: 16px;
  background: var(--surface-2); display: flex; flex-direction: column; gap: 10px;
}
.nba-card-header { display: flex; align-items: center; gap: 10px; }
.nba-avatar {
  width: 36px; height: 36px; border-radius: 50%; background: var(--primary-soft); color: var(--primary-text);
  font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.nba-card-header strong { display: block; font-size: 13px; font-weight: 700; color: var(--text-1); }
.nba-owner { font-size: 11px; color: var(--text-3); display: block; margin-top: 2px; }
.nba-signal { margin: 0; font-size: 12px; color: var(--text-2); line-height: 1.45; }
.nba-action-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.nba-action-badge {
  display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
}
.badge-demo     { background: var(--primary-soft); color: var(--primary-text); }
.badge-email    { background: #dbeafe; color: #1d4ed8; }
.badge-task     { background: #dcfce7; color: #166534; }
.badge-call     { background: #fef3c7; color: #92400e; }
.nba-priority { font-size: 11px; font-weight: 700; }
.priority-High   { color: #dc2626; }
.priority-Medium { color: #d97706; }

/* Explainer */
.explainer-card { }
.explainer-card h3 { margin: 0 0 16px; font-size: 15px; font-weight: 700; color: var(--text-1); }
.explainer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.explainer-item {
  background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 16px; display: flex; flex-direction: column; gap: 8px;
}
.explainer-icon { font-size: 22px; }
.explainer-item strong { font-size: 13px; font-weight: 700; color: var(--text-1); }
.explainer-item p { margin: 0; font-size: 12px; color: var(--text-2); line-height: 1.5; }

@media (max-width: 1100px) {
  .email-composer, .nba-grid { grid-template-columns: 1fr; }
  .explainer-grid { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
  .page { padding: 18px 14px; }
  .page-header { flex-direction: column; }
  .segment-tabs { gap: 6px; }
}
</style>
