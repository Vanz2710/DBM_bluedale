<template>
  <div class="email-page">
    <header class="module-header">
      <div>
        <p class="eyebrow">CRM Email Marketing</p>
        <h1>Email Marketing</h1>
        <p class="header-subtitle">Campaigns, templates, audiences, automations, analytics, and SMTP controls in one workspace.</p>
      </div>
      <div class="header-actions">
        <button type="button" class="btn-secondary" @click="activeTab = 'lists'">Import Contacts</button>
        <button type="button" class="btn-primary" @click="activeTab = 'create'">Create Campaign</button>
      </div>
    </header>

    <nav class="module-tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="{ active: activeTab === tab.id }"
        @click="activeTab = tab.id"
      >
        <span>{{ tab.short }}</span>
        {{ tab.label }}
      </button>
    </nav>

    <section v-if="activeTab === 'dashboard'" class="tab-space">
      <div class="metric-grid">
        <div v-for="metric in dashboardMetrics" :key="metric.label" class="metric-tile">
          <span>{{ metric.label }}</span>
          <strong>{{ metric.value }}</strong>
          <small>{{ metric.detail }}</small>
        </div>
      </div>

      <div class="dashboard-grid">
        <section class="panel wide-panel">
          <div class="panel-head">
            <h2>Recent Campaigns</h2>
            <button type="button" class="text-button" @click="activeTab = 'campaigns'">View all</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Campaign Name</th>
                  <th>Status</th>
                  <th>Audience</th>
                  <th>Sent</th>
                  <th>Open Rate</th>
                  <th>Click Rate</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="campaignRow in campaigns" :key="campaignRow.id">
                  <td>
                    <strong>{{ campaignRow.name }}</strong>
                    <small>{{ campaignRow.channel }}</small>
                  </td>
                  <td><span class="status-badge" :class="campaignRow.status.toLowerCase()">{{ campaignRow.status }}</span></td>
                  <td>{{ campaignRow.audience }}</td>
                  <td>{{ campaignRow.sent }}</td>
                  <td>{{ campaignRow.openRate }}</td>
                  <td>{{ campaignRow.clickRate }}</td>
                  <td>{{ campaignRow.date }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <aside class="panel">
          <div class="panel-head">
            <h2>Today</h2>
          </div>
          <div class="activity-list">
            <div v-for="activity in activities" :key="activity.title">
              <span>{{ activity.time }}</span>
              <strong>{{ activity.title }}</strong>
              <p>{{ activity.detail }}</p>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <section v-else-if="activeTab === 'campaigns'" class="tab-space">
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Campaign Management</h2>
            <p>Track campaign status, audience volume, delivery, opens, and clicks.</p>
          </div>
          <div class="inline-actions">
            <select v-model="campaignStatusFilter">
              <option value="">All status</option>
              <option value="Draft">Draft</option>
              <option value="Scheduled">Scheduled</option>
              <option value="Sent">Sent</option>
            </select>
            <button type="button" class="btn-primary" @click="activeTab = 'create'">New Campaign</button>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Campaign Name</th>
                <th>Status</th>
                <th>Audience</th>
                <th>Sent</th>
                <th>Open Rate</th>
                <th>Click Rate</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="campaignRow in filteredCampaigns" :key="campaignRow.id">
                <td>
                  <strong>{{ campaignRow.name }}</strong>
                  <small>{{ campaignRow.channel }}</small>
                </td>
                <td><span class="status-badge" :class="campaignRow.status.toLowerCase()">{{ campaignRow.status }}</span></td>
                <td>{{ campaignRow.audience }}</td>
                <td>{{ campaignRow.sent }}</td>
                <td>{{ campaignRow.openRate }}</td>
                <td>{{ campaignRow.clickRate }}</td>
                <td>{{ campaignRow.date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'create'" class="tab-space campaign-builder">
      <aside class="builder-steps">
        <button v-for="step in wizardSteps" :key="step.id" type="button" :class="{ active: wizardStep === step.id }" @click="wizardStep = step.id">
          <span>{{ step.number }}</span>
          {{ step.label }}
        </button>
      </aside>

      <section class="panel builder-panel">
        <div class="panel-head">
          <div>
            <h2>{{ currentStep.label }}</h2>
            <p>{{ currentStep.description }}</p>
          </div>
          <span class="save-state">{{ campaignStatus }}</span>
        </div>

        <div v-if="wizardStep === 'info'" class="form-grid">
          <label>
            <span>Campaign Name</span>
            <input v-model="campaign.name" type="text">
          </label>
          <label>
            <span>Provider</span>
            <select v-model="campaign.provider">
              <option value="gmail">Gmail SMTP</option>
              <option value="outlook">Outlook SMTP</option>
            </select>
          </label>
          <label>
            <span>Sender Account</span>
            <select v-model="campaign.sender">
              <option v-for="sender in selectedProvider.senders" :key="sender" :value="sender">{{ sender }}</option>
            </select>
          </label>
          <label>
            <span>Schedule</span>
            <input v-model="campaign.schedule" type="datetime-local">
          </label>
        </div>

        <div v-else-if="wizardStep === 'audience'" class="audience-layout">
          <div class="form-grid">
            <label>
              <span>Company</span>
              <select v-model="campaign.contactId" :disabled="companyLoading">
                <option value="">{{ companyLoading ? 'Loading companies...' : 'Select company' }}</option>
                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
            </label>
            <label>
              <span>Industry Filter</span>
              <input v-model="industryFilter" type="text" placeholder="Filter visible companies">
            </label>
          </div>

          <div class="audience-box">
            <div class="audience-toolbar">
              <strong>PIC Emails</strong>
              <button type="button" class="text-button" :disabled="emailOptions.length === 0" @click="toggleAllEmails">
                {{ allEmailsSelected ? 'Clear all' : 'Select all' }}
              </button>
            </div>
            <label v-for="pic in emailOptions" :key="pic.id" class="pic-row">
              <input v-model="campaign.picIds" type="checkbox" :value="pic.id">
              <span>
                <strong>{{ pic.name || 'PIC' }}</strong>
                {{ pic.email }}
              </span>
            </label>
            <p v-if="!inchargeLoading && emailOptions.length === 0" class="empty-copy">No PIC email found for this company.</p>
            <p v-if="inchargeLoading" class="empty-copy">Loading PIC emails...</p>
          </div>
        </div>

        <div v-else-if="wizardStep === 'content'" class="content-grid">
          <div>
            <div class="form-grid">
              <label>
                <span>Template</span>
                <select v-model="selectedTemplateId" @change="applySelectedTemplate">
                  <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                </select>
              </label>
              <label>
                <span>Subject Line</span>
                <input v-model="campaign.subject" type="text">
              </label>
            </div>
            <label class="full-field">
              <span>Preview Text</span>
              <input v-model="campaign.previewText" type="text">
            </label>
            <label class="full-field">
              <span>HTML / Email Body</span>
              <textarea v-model="campaign.body"></textarea>
            </label>
            <div class="merge-tags">
              <button v-for="tag in mergeTags" :key="tag" type="button" @click="insertTag(tag)">{{ tag }}</button>
            </div>
          </div>

          <aside class="email-preview">
            <div class="email-topline">
              <span>{{ campaign.sender }}</span>
              <strong>{{ campaign.subject }}</strong>
            </div>
            <article>
              <p v-for="paragraph in emailParagraphs" :key="paragraph">{{ paragraph }}</p>
              <button type="button">{{ selectedEmails.length > 1 ? 'Contact selected PICs' : 'Contact selected PIC' }}</button>
            </article>
          </aside>
        </div>

        <div v-else class="review-grid">
          <div class="review-block">
            <span>Campaign</span>
            <strong>{{ campaign.name }}</strong>
            <p>{{ campaign.subject }}</p>
          </div>
          <div class="review-block">
            <span>Audience</span>
            <strong>{{ selectedEmails.length }} PIC email(s)</strong>
            <p>{{ selectedCompany?.name || 'No company selected' }}</p>
          </div>
          <div class="review-block">
            <span>Provider</span>
            <strong>{{ selectedProvider.name }}</strong>
            <p>{{ campaign.sender }}</p>
          </div>
          <div class="review-block">
            <span>Schedule</span>
            <strong>{{ formattedSchedule }}</strong>
            <p>{{ selectedProvider.limit }} daily limit</p>
          </div>
        </div>

        <div class="builder-actions">
          <button type="button" class="btn-secondary" @click="saveDraft">Save Draft</button>
          <button type="button" class="btn-secondary" @click="sendTest">Send Test</button>
          <button type="button" class="btn-primary" @click="scheduleCampaign">Schedule Campaign</button>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'templates'" class="tab-space templates-grid">
      <section class="panel template-list">
        <div class="panel-head">
          <h2>Email Template Library</h2>
          <button type="button" class="btn-secondary" @click="duplicateTemplate">Duplicate</button>
        </div>
        <button v-for="template in templates" :key="template.id" type="button" :class="{ active: selectedTemplateId === template.id }" @click="selectedTemplateId = template.id">
          <strong>{{ template.name }}</strong>
          <span>{{ template.category }}</span>
        </button>
      </section>
      <section class="panel">
        <div class="panel-head">
          <h2>{{ selectedTemplate.name }}</h2>
          <button type="button" class="btn-primary" @click="applySelectedTemplate">Use Template</button>
        </div>
        <div class="template-preview">
          <h3>{{ selectedTemplate.subject }}</h3>
          <p v-for="paragraph in selectedTemplate.body.split('\n').filter(Boolean)" :key="paragraph">{{ paragraph }}</p>
        </div>
        <div class="merge-tag-panel">
          <span v-for="tag in mergeTags" :key="tag">{{ tag }}</span>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'lists'" class="tab-space lists-grid">
      <section class="panel">
        <div class="panel-head">
          <h2>Contact Lists</h2>
          <span class="save-state">{{ companies.length }} CRM companies</span>
        </div>
        <div class="segment-list">
          <div v-for="segment in segments" :key="segment.name">
            <strong>{{ segment.name }}</strong>
            <span>{{ segment.count }}</span>
            <p>{{ segment.rule }}</p>
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-head">
          <h2>Selected Audience</h2>
        </div>
        <div class="selected-email-list">
          <div v-for="pic in selectedEmails" :key="pic.id">
            <strong>{{ pic.name || 'PIC' }}</strong>
            <span>{{ pic.email }}</span>
          </div>
          <p v-if="selectedEmails.length === 0" class="empty-copy">No PIC emails selected.</p>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'automations'" class="tab-space">
      <section class="panel">
        <div class="panel-head">
          <h2>Automation Workflow</h2>
          <button type="button" class="btn-primary">Create Automation</button>
        </div>
        <div class="automation-grid">
          <div v-for="flow in automations" :key="flow.name" class="automation-item">
            <span>{{ flow.status }}</span>
            <strong>{{ flow.name }}</strong>
            <p>{{ flow.trigger }}</p>
            <div class="flow-line">
              <em v-for="step in flow.steps" :key="step">{{ step }}</em>
            </div>
          </div>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'analytics'" class="tab-space analytics-grid">
      <section class="panel">
        <div class="panel-head">
          <h2>Analytics</h2>
        </div>
        <div class="bar-list">
          <div v-for="campaignRow in campaigns" :key="campaignRow.id">
            <span>{{ campaignRow.name }}</span>
            <div><strong :style="{ width: campaignRow.openRate }"></strong></div>
            <em>{{ campaignRow.openRate }}</em>
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-head">
          <h2>Channel Comparison</h2>
        </div>
        <div class="channel-list">
          <div v-for="provider in providers" :key="provider.id">
            <strong>{{ provider.name }}</strong>
            <span>{{ provider.openRate }} open rate</span>
            <small>{{ provider.bestFor }}</small>
          </div>
        </div>
      </section>
    </section>

    <section v-else-if="activeTab === 'settings'" class="tab-space settings-grid">
      <section v-for="provider in providers" :key="provider.id" class="panel smtp-panel">
        <div class="panel-head">
          <h2>{{ provider.name }} SMTP</h2>
          <span class="status-badge scheduled">Connected</span>
        </div>
        <div class="settings-row">
          <span>Daily Limit</span>
          <strong>{{ provider.limit }}</strong>
        </div>
        <div class="settings-row">
          <span>Reply Handling</span>
          <strong>{{ provider.replies }}</strong>
        </div>
        <div class="settings-row">
          <span>Sender</span>
          <strong>{{ provider.senders[0] }}</strong>
        </div>
      </section>
    </section>

    <section v-else class="tab-space">
      <section class="panel">
        <div class="panel-head">
          <h2>Unsubscribed</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Email</th>
                <th>Company</th>
                <th>Reason</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in unsubscribed" :key="row.email">
                <td>{{ row.email }}</td>
                <td>{{ row.company }}</td>
                <td>{{ row.reason }}</td>
                <td>{{ row.date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../api';

const activeTab = ref('dashboard');
const wizardStep = ref('info');
const campaignStatusFilter = ref('');
const campaignStatus = ref('Draft saved');
const companyLoading = ref(false);
const inchargeLoading = ref(false);
const companies = ref([]);
const incharges = ref([]);
const industryFilter = ref('');
const selectedTemplateId = ref('follow-up');

const tabs = [
  { id: 'dashboard', label: 'Dashboard', short: 'DB' },
  { id: 'campaigns', label: 'Campaigns', short: 'CP' },
  { id: 'create', label: 'Create Campaign', short: 'CC' },
  { id: 'templates', label: 'Templates', short: 'TP' },
  { id: 'lists', label: 'Contact Lists', short: 'CL' },
  { id: 'automations', label: 'Automations', short: 'AU' },
  { id: 'analytics', label: 'Analytics', short: 'AN' },
  { id: 'settings', label: 'SMTP Settings', short: 'ST' },
  { id: 'unsubscribed', label: 'Unsubscribed', short: 'UN' },
];

const wizardSteps = [
  { id: 'info', number: '1', label: 'Campaign Info', description: 'Set the campaign name, sender, provider, and schedule.' },
  { id: 'audience', number: '2', label: 'Audience', description: 'Choose companies and PIC emails from your CRM database.' },
  { id: 'content', number: '3', label: 'Content', description: 'Write the email body or start from a saved template.' },
  { id: 'review', number: '4', label: 'Review', description: 'Confirm audience, sender, and schedule before sending.' },
];

const providers = [
  {
    id: 'gmail',
    name: 'Gmail',
    senders: ['ammar@bluedale.com.my', 'sales@bluedale.com.my'],
    openRate: '42%',
    limit: '500/day',
    bestFor: 'Warm leads',
    replies: 'Gmail inbox',
  },
  {
    id: 'outlook',
    name: 'Outlook',
    senders: ['marketing@bluedale.com.my', 'support@bluedale.com.my'],
    openRate: '38%',
    limit: '1,000/day',
    bestFor: 'Existing clients',
    replies: 'Outlook inbox',
  },
];

const templates = ref([
  {
    id: 'follow-up',
    name: 'Follow-up Package',
    category: 'Sales',
    subject: 'Quick follow-up from Bluedale',
    body: `Hi {{first_name}},

Thank you for your recent interest in Bluedale. I wanted to follow up with a focused recommendation for {{company_name}}.

Would you like us to arrange a quick call this week?`,
  },
  {
    id: 'proposal',
    name: 'Proposal Reminder',
    category: 'Proposal',
    subject: 'Proposal package for {{company_name}}',
    body: `Hello {{first_name}},

We can prepare a tailored proposal package for {{company_name}} based on your campaign direction.

Please let me know the preferred locations and campaign period.`,
  },
  {
    id: 'reactivation',
    name: 'Reactivation',
    category: 'Review',
    subject: 'Checking your next campaign plan',
    body: `Hi {{first_name}},

Just checking whether {{company_name}} has any upcoming marketing campaign plans.

I can share available options if you are reviewing outdoor media again.`,
  },
]);

const campaign = ref({
  name: 'May Outdoor Media Follow-up',
  provider: 'gmail',
  contactId: '',
  picIds: [],
  sender: 'ammar@bluedale.com.my',
  schedule: '2026-05-26T10:00',
  subject: 'Quick follow-up from Bluedale',
  previewText: 'A short note about the package options we discussed.',
  body: templates.value[0].body,
});

const campaigns = ref([]);

const activities = [
  { time: '09:15', title: 'Draft updated', detail: 'May Outdoor Media Follow-up content changed.' },
  { time: '10:00', title: 'Test queued', detail: 'Gmail test email prepared for review.' },
  { time: '14:30', title: 'Audience synced', detail: 'CRM PIC emails refreshed from contacts.' },
];

const automations = [
  { name: 'New enquiry nurture', status: 'Active', trigger: 'When a new company is added', steps: ['Wait 1 day', 'Send intro', 'Create task'] },
  { name: 'Proposal follow-up', status: 'Paused', trigger: 'When proposal is generated', steps: ['Wait 3 days', 'Send reminder', 'Notify owner'] },
  { name: 'Inactive client review', status: 'Active', trigger: 'No activity for 60 days', steps: ['Send check-in', 'Tag review', 'Create call task'] },
];

const unsubscribed = [
  { email: 'example@company.com', company: 'Sample Company', reason: 'Manual opt-out', date: '12 May 2026' },
];

const mergeTags = ['{{first_name}}', '{{company_name}}', '{{phone}}'];

const selectedProvider = computed(() => providers.find((provider) => provider.id === campaign.value.provider) ?? providers[0]);
const selectedCompany = computed(() => companies.value.find((company) => company.id === Number(campaign.value.contactId)) ?? null);
const emailOptions = computed(() => incharges.value.filter((pic) => pic.email));
const selectedEmails = computed(() => emailOptions.value.filter((pic) => campaign.value.picIds.includes(pic.id)));
const allEmailsSelected = computed(() => emailOptions.value.length > 0 && selectedEmails.value.length === emailOptions.value.length);
const selectedTemplate = computed(() => templates.value.find((template) => template.id === selectedTemplateId.value) ?? templates.value[0]);
const currentStep = computed(() => wizardSteps.find((step) => step.id === wizardStep.value) ?? wizardSteps[0]);
const emailParagraphs = computed(() => campaign.value.body.split('\n').filter((line) => line.trim()));

const filteredCampaigns = computed(() => {
  if (!campaignStatusFilter.value) return campaigns.value;
  return campaigns.value.filter((row) => row.status === campaignStatusFilter.value);
});

const dashboardMetrics = computed(() => [
  { label: 'Active Campaigns', value: campaigns.value.filter((row) => row.status !== 'Sent').length, detail: 'Draft and scheduled' },
  { label: 'Audience', value: selectedEmails.value.length, detail: selectedCompany.value?.name ?? 'No company selected' },
  { label: 'Average Open', value: '42%', detail: 'Based on recent campaigns' },
  { label: 'Templates', value: templates.value.length, detail: 'Ready to reuse' },
]);

const segments = computed(() => [
  { name: 'All CRM Companies', count: companies.value.length, rule: 'Every company imported into CRM.' },
  { name: 'Selected Company PICs', count: selectedEmails.value.length, rule: selectedCompany.value?.name ?? 'Choose a company in Create Campaign.' },
  { name: 'Warm Leads', count: campaigns.value[0].audience, rule: 'Companies with recent campaign interest.' },
]);

const formattedSchedule = computed(() => {
  if (!campaign.value.schedule) return 'Not scheduled';
  return new Date(campaign.value.schedule).toLocaleString('en-MY', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
});

watch(() => campaign.value.provider, () => {
  campaign.value.sender = selectedProvider.value.senders[0];
});

watch(() => campaign.value.contactId, async (contactId) => {
  campaign.value.picIds = [];
  incharges.value = [];
  if (!contactId) return;
  await loadIncharges(contactId);
  campaign.value.picIds = emailOptions.value.map((pic) => pic.id);
});

watch(industryFilter, () => {
  const term = industryFilter.value.trim().toLowerCase();
  if (!term) return;
  const match = companies.value.find((company) => (company.industry?.name ?? '').toLowerCase().includes(term));
  if (match) campaign.value.contactId = match.id;
});

onMounted(async () => {
  await loadCompanies();
  await loadCampaigns();
});

async function loadCampaigns() {
  try {
    const res = await api.get('/v1/email-campaigns');
    const raw = res.data?.data ?? res.data ?? [];
    campaigns.value = raw.map((c) => ({
      id: c.id,
      name: c.name,
      channel: c.sender_email ? c.sender_email.split('@')[1] ?? 'Email' : 'Email',
      status: c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : 'Draft',
      audience: c.audience_count ?? 0,
      sent: c.sent_count ?? 0,
      openRate: c.open_rate != null ? `${c.open_rate}%` : '—',
      clickRate: c.click_rate != null ? `${c.click_rate}%` : '—',
      date: c.scheduled_at ? new Date(c.scheduled_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—',
      _raw: c,
    }));
  } catch (_) {
    campaigns.value = [];
  }
}

async function loadCompanies() {
  companyLoading.value = true;
  try {
    const res = await api.get('/v1/contacts', { params: { per_page: 1000 } });
    companies.value = res.data.data ?? [];
    if (!campaign.value.contactId && companies.value.length > 0) {
      campaign.value.contactId = companies.value[0].id;
    }
  } finally {
    companyLoading.value = false;
  }
}

async function loadIncharges(contactId) {
  inchargeLoading.value = true;
  try {
    const res = await api.get(`/v1/contacts/${contactId}/incharges`);
    incharges.value = res.data.data ?? [];
  } finally {
    inchargeLoading.value = false;
  }
}

function toggleAllEmails() {
  campaign.value.picIds = allEmailsSelected.value ? [] : emailOptions.value.map((pic) => pic.id);
}

function applySelectedTemplate() {
  campaign.value.subject = selectedTemplate.value.subject;
  campaign.value.body = selectedTemplate.value.body;
  campaignStatus.value = 'Template applied';
  activeTab.value = activeTab.value === 'templates' ? 'create' : activeTab.value;
  wizardStep.value = 'content';
}

function duplicateTemplate() {
  templates.value.push({
    ...selectedTemplate.value,
    id: `${selectedTemplate.value.id}-${Date.now()}`,
    name: `${selectedTemplate.value.name} Copy`,
  });
}

function insertTag(tag) {
  campaign.value.body = `${campaign.value.body} ${tag}`;
}

async function saveDraft() {
  campaignStatus.value = 'Saving…';
  try {
    await api.post('/v1/email-campaigns', {
      name:         campaign.value.name || 'Untitled Draft',
      sender_email: campaign.value.sender,
      subject:      campaign.value.subject,
      body:         campaign.value.body,
      scheduled_at: campaign.value.schedule || null,
      pic_ids:      selectedEmails.value.map((p) => p.id),
    });
    await loadCampaigns();
    campaignStatus.value = 'Draft saved';
  } catch (_) {
    campaignStatus.value = 'Save failed';
  }
}

async function sendTest() {
  campaignStatus.value = 'Sending test…';
  try {
    const saved = await api.post('/v1/email-campaigns', {
      name:         campaign.value.name || 'Test Campaign',
      sender_email: campaign.value.sender,
      subject:      campaign.value.subject,
      body:         campaign.value.body,
      pic_ids:      selectedEmails.value.map((p) => p.id),
    });
    await api.post(`/v1/email-campaigns/${saved.data.data?.id ?? saved.data.id}/send-test`, {
      email: campaign.value.sender,
    });
    campaignStatus.value = 'Test sent';
  } catch (_) {
    campaignStatus.value = 'Test failed';
  }
}

async function scheduleCampaign() {
  campaignStatus.value = 'Scheduling…';
  try {
    const saved = await api.post('/v1/email-campaigns', {
      name:         campaign.value.name || 'Untitled Campaign',
      sender_email: campaign.value.sender,
      subject:      campaign.value.subject,
      body:         campaign.value.body,
      scheduled_at: campaign.value.schedule || null,
      pic_ids:      selectedEmails.value.map((p) => p.id),
    });
    const id = saved.data.data?.id ?? saved.data.id;
    await api.post(`/v1/email-campaigns/${id}/schedule`, {
      scheduled_at: campaign.value.schedule || null,
    });
    await loadCampaigns();
    campaignStatus.value = 'Campaign scheduled';
  } catch (_) {
    campaignStatus.value = 'Schedule failed';
  }
}
</script>

<style scoped>
.email-page { padding: 24px 28px; color: #172033; }
.module-header {
  display: flex; justify-content: space-between; gap: 18px; align-items: center;
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 18px 20px; margin-bottom: 14px;
}
.eyebrow { margin: 0 0 5px; color: #0f766e; font-size: 11px; font-weight: 900; letter-spacing: 0.8px; text-transform: uppercase; }
.module-header h1 { margin: 0 0 4px; font-size: 24px; font-weight: 900; }
.header-subtitle, .panel-head p, .review-block p, .activity-list p, .automation-item p, .segment-list p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.45; }
.header-actions, .inline-actions, .builder-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-primary, .btn-secondary, .text-button {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px; font-size: 13px; font-weight: 850; cursor: pointer;
}
.btn-primary { background: #0f766e; color: #ffffff; }
.btn-secondary { background: #e8eef5; color: #334155; }
.text-button { background: transparent; color: #0f766e; padding: 0 4px; }
.text-button:disabled { color: #94a3b8; cursor: not-allowed; }
.module-tabs {
  display: grid; grid-template-columns: repeat(9, minmax(0, 1fr)); gap: 8px; margin-bottom: 14px;
}
.module-tabs button {
  min-height: 52px; border: 1.5px solid #dbe3ee; border-radius: 8px; background: #ffffff;
  color: #334155; font-size: 12px; font-weight: 850; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px;
}
.module-tabs button.active { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); color: #0f766e; }
.module-tabs span {
  width: 24px; height: 24px; border-radius: 6px; background: #e8f4f2; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;
}
.tab-space { display: grid; gap: 14px; }
.panel {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(15,23,42,0.05);
}
.panel-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-bottom: 14px; }
.panel-head h2 { margin: 0; font-size: 18px; color: #172033; }
.metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.metric-tile {
  background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 14px;
}
.metric-tile span, .review-block span, .settings-row span {
  display: block; color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px;
}
.metric-tile strong { display: block; margin: 6px 0 4px; font-size: 26px; color: #172033; }
.metric-tile small { color: #64748b; font-size: 12px; font-weight: 700; }
.dashboard-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 14px; }
.table-wrap { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
table { width: 100%; min-width: 780px; border-collapse: collapse; font-size: 12px; }
th, td { border-bottom: 1px solid #eef2f7; padding: 10px; text-align: left; vertical-align: middle; }
th { background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px; }
td strong, td small { display: block; }
td small { color: #64748b; margin-top: 3px; }
.status-badge {
  display: inline-flex; min-height: 24px; align-items: center; border-radius: 999px; padding: 0 9px; font-size: 11px; font-weight: 900;
}
.status-badge.scheduled { background: #dbeafe; color: #1d4ed8; }
.status-badge.draft { background: #fef3c7; color: #92400e; }
.status-badge.sent { background: #dcfce7; color: #166534; }
.activity-list, .segment-list, .selected-email-list, .channel-list { display: grid; gap: 10px; }
.activity-list div, .segment-list div, .selected-email-list div, .channel-list div {
  border: 1px solid #e2e8f0; border-radius: 8px; padding: 11px; background: #f8fafc;
}
.activity-list span { color: #0f766e; font-size: 11px; font-weight: 900; }
.activity-list strong, .segment-list strong, .selected-email-list strong, .channel-list strong { display: block; margin: 3px 0; font-size: 13px; color: #172033; }
.campaign-builder { grid-template-columns: 220px minmax(0, 1fr); align-items: start; }
.builder-steps { display: grid; gap: 8px; }
.builder-steps button {
  min-height: 48px; border: 1.5px solid #dbe3ee; border-radius: 8px; background: #ffffff;
  color: #334155; text-align: left; padding: 8px 10px; font-size: 13px; font-weight: 850; cursor: pointer;
}
.builder-steps button.active { border-color: #0f766e; color: #0f766e; }
.builder-steps span {
  width: 22px; height: 22px; border-radius: 999px; background: #e8f4f2; display: inline-flex; align-items: center; justify-content: center; margin-right: 7px;
}
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
label, .full-field { display: flex; flex-direction: column; gap: 6px; }
label span, .full-field span {
  color: #64748b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.7px;
}
input, select, textarea {
  width: 100%; border: 1.5px solid #dbe3ee; border-radius: 7px; background: #ffffff; color: #172033; font-family: inherit; font-size: 13px; outline: none;
}
input, select { height: 38px; padding: 0 10px; }
textarea { min-height: 220px; padding: 11px; resize: vertical; line-height: 1.5; }
input:focus, select:focus, textarea:focus { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.save-state { background: #ecfdf5; color: #047857; border-radius: 999px; padding: 7px 10px; font-size: 12px; font-weight: 900; }
.audience-layout, .content-grid, .review-grid { display: grid; gap: 14px; }
.audience-box { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.audience-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.pic-row { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 9px; align-items: center; padding: 10px 12px; border-bottom: 1px solid #eef2f7; }
.pic-row input { width: 16px; height: 16px; padding: 0; }
.pic-row span { color: #64748b; font-size: 12px; text-transform: none; letter-spacing: 0; }
.empty-copy { margin: 0; color: #64748b; font-size: 13px; font-weight: 700; }
.content-grid { grid-template-columns: minmax(0, 1fr) 360px; align-items: start; }
.full-field { margin-top: 12px; }
.merge-tags, .merge-tag-panel { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.merge-tags button, .merge-tag-panel span {
  min-height: 28px; border: 1px solid #dbe3ee; border-radius: 6px; background: #f8fafc; color: #334155; padding: 0 9px; font-size: 12px; font-weight: 800;
}
.email-preview { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #ffffff; }
.email-topline { display: grid; gap: 4px; padding: 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.email-topline span { color: #0f766e; font-size: 12px; font-weight: 900; }
.email-topline strong { color: #172033; }
.email-preview article { padding: 16px; }
.email-preview p { margin: 0 0 12px; color: #334155; font-size: 13px; line-height: 1.55; }
.email-preview button { height: 34px; border: none; border-radius: 7px; background: #0f766e; color: #ffffff; padding: 0 12px; font-size: 12px; font-weight: 900; }
.review-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.review-block { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc; }
.review-block strong { display: block; margin: 7px 0 4px; color: #172033; font-size: 14px; }
.builder-actions { justify-content: flex-end; margin-top: 14px; }
.templates-grid, .lists-grid, .analytics-grid, .settings-grid { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 14px; align-items: start; }
.template-list { display: grid; gap: 9px; }
.template-list .panel-head { margin-bottom: 4px; }
.template-list button {
  border: 1.5px solid #dbe3ee; border-radius: 8px; background: #ffffff; padding: 12px; text-align: left; cursor: pointer;
}
.template-list button.active { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); }
.template-list span { display: block; margin-top: 4px; color: #64748b; font-size: 12px; font-weight: 800; }
.template-preview { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc; }
.template-preview h3 { margin: 0 0 12px; font-size: 20px; }
.template-preview p { color: #334155; font-size: 14px; line-height: 1.55; }
.segment-list span { float: right; color: #0f766e; font-weight: 900; }
.automation-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.automation-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 13px; background: #f8fafc; }
.automation-item > span { color: #0f766e; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.automation-item strong { display: block; margin: 7px 0 5px; font-size: 15px; }
.flow-line { display: grid; gap: 6px; margin-top: 10px; }
.flow-line em { background: #ffffff; border: 1px solid #dbe3ee; border-radius: 6px; padding: 7px 9px; color: #334155; font-size: 12px; font-style: normal; font-weight: 800; }
.bar-list { display: grid; gap: 12px; }
.bar-list div { display: grid; grid-template-columns: 190px minmax(0, 1fr) 48px; gap: 10px; align-items: center; }
.bar-list span, .bar-list em { color: #334155; font-size: 12px; font-weight: 800; font-style: normal; }
.bar-list div div { height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; display: block; }
.bar-list strong { display: block; height: 100%; background: #0f766e; border-radius: inherit; }
.channel-list span, .channel-list small { display: block; color: #64748b; font-size: 12px; font-weight: 800; }
.settings-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.settings-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #eef2f7; }
.settings-row strong { color: #172033; font-size: 13px; text-align: right; }

@media (max-width: 1180px) {
  .module-tabs { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .dashboard-grid, .campaign-builder, .content-grid, .templates-grid, .lists-grid, .analytics-grid, .settings-grid { grid-template-columns: 1fr; }
  .automation-grid, .review-grid, .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 760px) {
  .email-page { padding: 18px 14px; }
  .module-header { flex-direction: column; align-items: stretch; }
  .header-actions, .builder-actions { justify-content: stretch; }
  .header-actions button, .builder-actions button { width: 100%; }
  .module-tabs, .metric-grid, .form-grid, .automation-grid, .review-grid { grid-template-columns: 1fr; }
}
</style>
