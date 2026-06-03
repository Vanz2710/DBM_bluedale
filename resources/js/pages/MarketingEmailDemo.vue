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
        <button type="button" class="btn-primary" @click="startNewCampaign">Create Campaign</button>
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

    <!-- DASHBOARD -->
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
          <div v-if="campaignsLoading" class="empty-copy">Loading...</div>
          <div v-else class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Campaign Name</th><th>Status</th><th>Audience</th>
                  <th>Sent</th><th>Open Rate</th><th>Click Rate</th><th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="campaigns.length === 0">
                  <td colspan="7" class="empty-copy">No campaigns yet.</td>
                </tr>
                <tr v-for="row in campaigns.slice(0, 5)" :key="row.id">
                  <td><strong>{{ row.name }}</strong><small>{{ row.sender_email ?? '—' }}</small></td>
                  <td><span class="status-badge" :class="row.status">{{ ucfirst(row.status) }}</span></td>
                  <td>{{ row.audience_count }}</td>
                  <td>{{ row.sent_count }}</td>
                  <td>{{ row.open_rate != null ? row.open_rate + '%' : '—' }}</td>
                  <td>{{ row.click_rate != null ? row.click_rate + '%' : '—' }}</td>
                  <td>{{ formatDate(row.scheduled_at ?? row.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
        <aside class="panel">
          <div class="panel-head"><h2>Today</h2></div>
          <div class="activity-list">
            <div v-if="recentActivities.length === 0" class="empty-copy">No recent activity.</div>
            <div v-for="activity in recentActivities" :key="activity.id">
              <span>{{ formatTime(activity.updated_at) }}</span>
              <strong>{{ activity.name }}</strong>
              <p>{{ ucfirst(activity.status) }} · {{ activity.audience_count }} recipients</p>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <!-- CAMPAIGNS -->
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
              <option value="draft">Draft</option>
              <option value="scheduled">Scheduled</option>
              <option value="sent">Sent</option>
            </select>
            <button type="button" class="btn-primary" @click="startNewCampaign">New Campaign</button>
          </div>
        </div>
        <div v-if="campaignsLoading" class="empty-copy">Loading...</div>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Campaign Name</th><th>Status</th><th>Audience</th>
                <th>Sent</th><th>Open Rate</th><th>Click Rate</th><th>Date</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredCampaigns.length === 0">
                <td colspan="8" class="empty-copy">No campaigns found.</td>
              </tr>
              <tr v-for="row in filteredCampaigns" :key="row.id">
                <td><strong>{{ row.name }}</strong><small>{{ row.sender_email ?? '—' }}</small></td>
                <td><span class="status-badge" :class="row.status">{{ ucfirst(row.status) }}</span></td>
                <td>{{ row.audience_count }}</td>
                <td>{{ row.sent_count }}</td>
                <td>{{ row.open_rate != null ? row.open_rate + '%' : '—' }}</td>
                <td>{{ row.click_rate != null ? row.click_rate + '%' : '—' }}</td>
                <td>{{ formatDate(row.scheduled_at ?? row.created_at) }}</td>
                <td class="row-actions">
                  <button type="button" class="text-button" @click="editCampaign(row)">Edit</button>
                  <button v-if="row.brevo_campaign_id" type="button" class="text-button" @click="syncStats(row)">Sync</button>
                  <button type="button" class="text-button danger" @click="deleteCampaign(row)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>

    <!-- CREATE / EDIT CAMPAIGN -->
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

        <div v-if="error" class="error-banner">{{ error }}</div>

        <!-- Step 1: Info -->
        <div v-if="wizardStep === 'info'" class="form-grid">
          <label>
            <span>Campaign Name *</span>
            <input v-model="campaign.name" type="text" placeholder="e.g. May Follow-up">
          </label>
          <label>
            <span>Sender Name</span>
            <input v-model="campaign.sender_name" type="text" :placeholder="settings.sender_name ?? 'From .env'">
          </label>
          <label>
            <span>Sender Email</span>
            <input v-model="campaign.sender_email" type="email" :placeholder="settings.sender_email ?? 'From .env'">
          </label>
          <label>
            <span>Schedule (leave blank to send immediately)</span>
            <input v-model="campaign.schedule" type="datetime-local">
          </label>
        </div>

        <!-- Step 2: Audience -->
        <div v-else-if="wizardStep === 'audience'" class="audience-layout">
          <div class="form-grid">
            <label>
              <span>Company</span>
              <select v-model="campaign.contactId" :disabled="companyLoading">
                <option value="">{{ companyLoading ? 'Loading...' : 'Select company' }}</option>
                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
            </label>
            <label>
              <span>Filter by industry</span>
              <input v-model="industryFilter" type="text" placeholder="Type to filter companies">
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
              <span><strong>{{ pic.name || 'PIC' }}</strong> {{ pic.email }}</span>
            </label>
            <p v-if="!inchargeLoading && emailOptions.length === 0 && campaign.contactId" class="empty-copy">No PIC email found for this company.</p>
            <p v-if="inchargeLoading" class="empty-copy">Loading PIC emails...</p>
          </div>
        </div>

        <!-- Step 3: Content -->
        <div v-else-if="wizardStep === 'content'" class="content-grid">
          <div>
            <div class="form-grid">
              <label>
                <span>Template</span>
                <select v-model="selectedTemplateId" @change="applySelectedTemplate">
                  <option value="">— No template —</option>
                  <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </label>
              <label>
                <span>Subject Line *</span>
                <input v-model="campaign.subject" type="text">
              </label>
            </div>
            <label class="full-field">
              <span>Preview Text</span>
              <input v-model="campaign.previewText" type="text">
            </label>
            <label class="full-field">
              <span>Email Body</span>
              <textarea v-model="campaign.body"></textarea>
            </label>
            <div class="merge-tags">
              <button v-for="tag in mergeTags" :key="tag" type="button" @click="insertTag(tag)">{{ tag }}</button>
            </div>
          </div>
          <aside class="email-preview">
            <div class="email-topline">
              <span>{{ campaign.sender_email || settings.sender_email }}</span>
              <strong>{{ campaign.subject || 'Subject preview' }}</strong>
            </div>
            <article>
              <p v-for="paragraph in emailParagraphs" :key="paragraph">{{ paragraph }}</p>
              <button type="button">Contact selected PIC(s)</button>
            </article>
          </aside>
        </div>

        <!-- Step 4: Review -->
        <div v-else class="review-grid">
          <div class="review-block">
            <span>Campaign</span>
            <strong>{{ campaign.name || '—' }}</strong>
            <p>{{ campaign.subject || 'No subject' }}</p>
          </div>
          <div class="review-block">
            <span>Audience</span>
            <strong>{{ selectedEmails.length }} PIC email(s)</strong>
            <p>{{ selectedCompany?.name || 'No company selected' }}</p>
          </div>
          <div class="review-block">
            <span>Sender</span>
            <strong>{{ campaign.sender_name || settings.sender_name || '(from .env)' }}</strong>
            <p>{{ campaign.sender_email || settings.sender_email || 'Not configured' }}</p>
          </div>
          <div class="review-block">
            <span>Schedule</span>
            <strong>{{ formattedSchedule }}</strong>
            <p>{{ settings.configured ? 'Brevo configured' : 'Brevo API key not set' }}</p>
          </div>
        </div>

        <div class="builder-actions">
          <button type="button" class="btn-secondary" :disabled="saving" @click="saveDraft">
            {{ saving === 'draft' ? 'Saving...' : 'Save Draft' }}
          </button>
          <button type="button" class="btn-secondary" :disabled="saving || !campaign.id" @click="openSendTestModal">
            Send Test
          </button>
          <button type="button" class="btn-primary" :disabled="saving || !campaign.name.trim()" @click="scheduleCampaign">
            {{ saving === 'schedule' ? 'Scheduling...' : 'Schedule Campaign' }}
          </button>
        </div>
      </section>
    </section>

    <!-- TEMPLATES -->
    <section v-else-if="activeTab === 'templates'" class="tab-space templates-grid">
      <section class="panel template-list">
        <div class="panel-head">
          <h2>Email Template Library</h2>
          <div class="inline-actions">
            <button type="button" class="btn-secondary" @click="duplicateTemplate">Duplicate</button>
            <button type="button" class="btn-primary" @click="openNewTemplateModal">New Template</button>
          </div>
        </div>
        <button
          v-for="t in templates"
          :key="t.id"
          type="button"
          :class="{ active: selectedTemplateId === t.id }"
          @click="selectedTemplateId = t.id"
        >
          <strong>{{ t.name }}</strong>
          <span>{{ t.category ?? 'General' }}</span>
        </button>
        <p v-if="templates.length === 0" class="empty-copy">No templates saved yet.</p>
      </section>
      <section v-if="selectedTemplate" class="panel">
        <div class="panel-head">
          <h2>{{ selectedTemplate.name }}</h2>
          <div class="inline-actions">
            <button type="button" class="btn-secondary" @click="deleteTemplate(selectedTemplate)">Delete</button>
            <button type="button" class="btn-primary" @click="applySelectedTemplate">Use Template</button>
          </div>
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

    <!-- CONTACT LISTS -->
    <section v-else-if="activeTab === 'lists'" class="tab-space lists-grid">
      <section class="panel">
        <div class="panel-head">
          <h2>Contact Lists</h2>
          <span class="save-state">{{ companies.length }} CRM companies</span>
        </div>
        <div class="segment-list">
          <div>
            <strong>All CRM Companies</strong>
            <span>{{ companies.length }}</span>
            <p>Every company imported into CRM.</p>
          </div>
          <div>
            <strong>PICs with Email</strong>
            <span>{{ totalPicsWithEmail }}</span>
            <p>People in charge with a recorded email address.</p>
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Selected Audience</h2></div>
        <div class="selected-email-list">
          <div v-for="pic in selectedEmails" :key="pic.id">
            <strong>{{ pic.name || 'PIC' }}</strong>
            <span>{{ pic.email }}</span>
          </div>
          <p v-if="selectedEmails.length === 0" class="empty-copy">Select a company in Create Campaign to preview audience.</p>
        </div>
      </section>
    </section>

    <!-- ANALYTICS -->
    <section v-else-if="activeTab === 'analytics'" class="tab-space analytics-grid">
      <section class="panel">
        <div class="panel-head"><h2>Open Rate by Campaign</h2></div>
        <div class="bar-list">
          <div v-if="campaigns.length === 0" class="empty-copy">No campaign data yet.</div>
          <div v-for="row in campaigns.filter(c => c.open_rate != null)" :key="row.id">
            <span>{{ row.name }}</span>
            <div><strong :style="{ width: row.open_rate + '%' }"></strong></div>
            <em>{{ row.open_rate }}%</em>
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Campaign Summary</h2></div>
        <div class="channel-list">
          <div v-for="row in campaigns" :key="row.id">
            <strong>{{ row.name }}</strong>
            <span>{{ row.sent_count }} sent · {{ row.open_rate != null ? row.open_rate + '% open' : 'pending' }}</span>
            <small>{{ ucfirst(row.status) }}</small>
          </div>
          <div v-if="campaigns.length === 0" class="empty-copy">No campaigns yet.</div>
        </div>
      </section>
    </section>

    <!-- SETTINGS -->
    <section v-else-if="activeTab === 'settings'" class="tab-space settings-grid">
      <section class="panel smtp-panel">
        <div class="panel-head">
          <h2>Brevo API</h2>
          <span class="status-badge" :class="settings.configured ? 'sent' : 'draft'">
            {{ settings.configured ? 'Connected' : 'Not configured' }}
          </span>
        </div>
        <div class="settings-row"><span>API Key</span><strong>{{ settings.configured ? '••••••••••••••••' : 'Not set' }}</strong></div>
        <div class="settings-row"><span>Sender Name</span><strong>{{ settings.sender_name ?? '—' }}</strong></div>
        <div class="settings-row"><span>Sender Email</span><strong>{{ settings.sender_email ?? '—' }}</strong></div>
        <p class="settings-hint">Set <code>BREVO_API_KEY</code>, <code>BREVO_SENDER_NAME</code>, and <code>BREVO_SENDER_EMAIL</code> in your <code>.env</code> file.</p>
      </section>
    </section>

    <!-- UNSUBSCRIBED -->
    <section v-else class="tab-space">
      <section class="panel">
        <div class="panel-head"><h2>Unsubscribed</h2></div>
        <p class="empty-copy" style="padding:20px">Unsubscribe tracking is managed directly in Brevo. View it on your Brevo dashboard.</p>
      </section>
    </section>

    <!-- Send Test Modal -->
    <div v-if="showTestModal" class="modal-backdrop" @click.self="showTestModal = false">
      <section class="mini-modal">
        <h3>Send Test Email</h3>
        <input v-model="testEmail" type="email" placeholder="your@email.com" class="modal-input">
        <p v-if="testMessage" class="modal-msg" :class="testMessage.startsWith('Error') ? 'error' : 'success'">{{ testMessage }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="showTestModal = false">Cancel</button>
          <button type="button" class="btn-primary" :disabled="saving === 'test' || !testEmail" @click="submitSendTest">
            {{ saving === 'test' ? 'Sending...' : 'Send Test' }}
          </button>
        </div>
      </section>
    </div>

    <!-- New Template Modal -->
    <div v-if="showTemplateModal" class="modal-backdrop" @click.self="showTemplateModal = false">
      <section class="mini-modal wide-modal">
        <h3>{{ templateForm.id ? 'Edit Template' : 'New Template' }}</h3>
        <div class="form-grid">
          <label><span>Name</span><input v-model="templateForm.name" type="text"></label>
          <label><span>Category</span><input v-model="templateForm.category" type="text" placeholder="e.g. Sales"></label>
          <label class="full-field"><span>Subject</span><input v-model="templateForm.subject" type="text"></label>
          <label class="full-field"><span>Body</span><textarea v-model="templateForm.body"></textarea></label>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="showTemplateModal = false">Cancel</button>
          <button type="button" class="btn-primary" :disabled="saving === 'template'" @click="saveTemplate">
            {{ saving === 'template' ? 'Saving...' : 'Save Template' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../api';

const activeTab = ref('dashboard');
const wizardStep = ref('info');
const campaignStatusFilter = ref('');
const campaignStatus = ref('');
const companyLoading = ref(false);
const inchargeLoading = ref(false);
const campaignsLoading = ref(false);
const companies = ref([]);
const incharges = ref([]);
const industryFilter = ref('');
const selectedTemplateId = ref('');
const campaigns = ref([]);
const templates = ref([]);
const settings = ref({ configured: false, sender_name: null, sender_email: null });
const error = ref('');
const saving = ref('');
const totalPicsWithEmail = ref(0);
const showTestModal = ref(false);
const testEmail = ref('');
const testMessage = ref('');
const showTemplateModal = ref(false);
const templateForm = ref({ id: null, name: '', category: '', subject: '', body: '' });

const tabs = [
  { id: 'dashboard', label: 'Dashboard', short: 'DB' },
  { id: 'campaigns', label: 'Campaigns', short: 'CP' },
  { id: 'create', label: 'Create Campaign', short: 'CC' },
  { id: 'templates', label: 'Templates', short: 'TP' },
  { id: 'lists', label: 'Contact Lists', short: 'CL' },
  { id: 'analytics', label: 'Analytics', short: 'AN' },
  { id: 'settings', label: 'SMTP Settings', short: 'ST' },
  { id: 'unsubscribed', label: 'Unsubscribed', short: 'UN' },
];

const wizardSteps = [
  { id: 'info', number: '1', label: 'Campaign Info', description: 'Set the campaign name, sender, and schedule.' },
  { id: 'audience', number: '2', label: 'Audience', description: 'Choose companies and PIC emails from your CRM database.' },
  { id: 'content', number: '3', label: 'Content', description: 'Write the email body or start from a saved template.' },
  { id: 'review', number: '4', label: 'Review', description: 'Confirm audience, sender, and schedule before sending.' },
];

const mergeTags = ['{{first_name}}', '{{company_name}}', '{{phone}}'];

const campaign = ref({
  id: null,
  name: '',
  sender_name: '',
  sender_email: '',
  contactId: '',
  picIds: [],
  schedule: '',
  subject: '',
  previewText: '',
  body: '',
});

const selectedCompany = computed(() => companies.value.find((c) => c.id === Number(campaign.value.contactId)) ?? null);
const emailOptions = computed(() => incharges.value.filter((pic) => pic.email));
const selectedEmails = computed(() => emailOptions.value.filter((pic) => campaign.value.picIds.includes(pic.id)));
const allEmailsSelected = computed(() => emailOptions.value.length > 0 && selectedEmails.value.length === emailOptions.value.length);
const selectedTemplate = computed(() => templates.value.find((t) => t.id === selectedTemplateId.value || t.id === Number(selectedTemplateId.value)) ?? null);
const currentStep = computed(() => wizardSteps.find((s) => s.id === wizardStep.value) ?? wizardSteps[0]);
const emailParagraphs = computed(() => (campaign.value.body ?? '').split('\n').filter((l) => l.trim()));
const filteredCampaigns = computed(() => {
  if (!campaignStatusFilter.value) return campaigns.value;
  return campaigns.value.filter((c) => c.status === campaignStatusFilter.value);
});
const dashboardMetrics = computed(() => [
  { label: 'Active Campaigns', value: campaigns.value.filter((c) => c.status !== 'sent').length, detail: 'Draft and scheduled' },
  { label: 'Audience', value: selectedEmails.value.length, detail: selectedCompany.value?.name ?? '(Al-Amar) Azzurro Sdn Bhd' },
  { label: 'Average Open', value: avgOpenRate.value, detail: 'Based on recent campaigns' },
  { label: 'Templates', value: templates.value.length, detail: 'Ready to reuse' },
]);
const avgOpenRate = computed(() => {
  const rates = campaigns.value.filter((c) => c.open_rate != null).map((c) => c.open_rate);
  if (!rates.length) return '—';
  return Math.round(rates.reduce((a, b) => a + b, 0) / rates.length) + '%';
});
const recentActivities = computed(() => {
  return [...campaigns.value]
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    .slice(0, 3);
});
const formattedSchedule = computed(() => {
  if (!campaign.value.schedule) return 'Send immediately';
  return new Date(campaign.value.schedule).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' });
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
  const match = companies.value.find((c) => (c.industry?.name ?? '').toLowerCase().includes(term) || c.name.toLowerCase().includes(term));
  if (match) campaign.value.contactId = match.id;
});

onMounted(() => {
  loadCampaigns();
  loadTemplates();
  loadCompanies();
  loadSettings();
});

async function loadCampaigns() {
  campaignsLoading.value = true;
  try {
    const res = await api.get('/v1/email-campaigns');
    campaigns.value = res.data.data ?? [];
  } finally {
    campaignsLoading.value = false;
  }
}

async function loadTemplates() {
  const res = await api.get('/v1/email-templates');
  templates.value = res.data.data ?? [];
}

async function loadSettings() {
  const res = await api.get('/v1/email-settings');
  settings.value = res.data;
}

async function loadCompanies() {
  companyLoading.value = true;
  try {
    const res = await api.get('/v1/contacts', { params: { per_page: 1000 } });
    const list = res.data.data ?? [];
    companies.value = list;
    totalPicsWithEmail.value = list.reduce((acc, c) => acc + (c.incharges_with_email_count ?? 0), 0);
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

function startNewCampaign() {
  campaign.value = { id: null, name: '', sender_name: '', sender_email: '', contactId: '', picIds: [], schedule: '', subject: '', previewText: '', body: '' };
  wizardStep.value = 'info';
  campaignStatus.value = '';
  error.value = '';
  selectedTemplateId.value = '';
  activeTab.value = 'create';
}

function editCampaign(row) {
  campaign.value = {
    id: row.id,
    name: row.name,
    sender_name: row.sender_name ?? '',
    sender_email: row.sender_email ?? '',
    contactId: '',
    picIds: row.pic_ids ?? [],
    schedule: row.scheduled_at ? row.scheduled_at.slice(0, 16) : '',
    subject: row.subject ?? '',
    previewText: row.preview_text ?? '',
    body: row.body ?? '',
  };
  wizardStep.value = 'info';
  campaignStatus.value = 'Editing draft';
  error.value = '';
  activeTab.value = 'create';
}

function toggleAllEmails() {
  campaign.value.picIds = allEmailsSelected.value ? [] : emailOptions.value.map((pic) => pic.id);
}

function applySelectedTemplate() {
  if (!selectedTemplate.value) return;
  campaign.value.subject = selectedTemplate.value.subject;
  campaign.value.body = selectedTemplate.value.body;
  campaignStatus.value = 'Template applied';
  if (activeTab.value === 'templates') {
    activeTab.value = 'create';
    wizardStep.value = 'content';
  }
}

function insertTag(tag) {
  campaign.value.body = (campaign.value.body ?? '') + ' ' + tag;
}

async function saveDraft() {
  if (!campaign.value.name.trim()) { error.value = 'Campaign name is required.'; return; }
  saving.value = 'draft';
  error.value = '';
  try {
    const payload = buildPayload();
    let res;
    if (campaign.value.id) {
      res = await api.put(`/v1/email-campaigns/${campaign.value.id}`, payload);
    } else {
      res = await api.post('/v1/email-campaigns', payload);
      campaign.value.id = res.data.data.id;
    }
    upsertCampaign(res.data.data);
    campaignStatus.value = 'Draft saved';
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to save draft.';
  } finally {
    saving.value = '';
  }
}

async function scheduleCampaign() {
  if (!campaign.value.name.trim()) { error.value = 'Campaign name is required.'; return; }
  saving.value = 'schedule';
  error.value = '';
  try {
    if (!campaign.value.id) {
      const res = await api.post('/v1/email-campaigns', buildPayload());
      campaign.value.id = res.data.data.id;
      upsertCampaign(res.data.data);
    } else {
      const res = await api.put(`/v1/email-campaigns/${campaign.value.id}`, buildPayload());
      upsertCampaign(res.data.data);
    }
    const res = await api.post(`/v1/email-campaigns/${campaign.value.id}/schedule`, {
      scheduled_at: campaign.value.schedule || null,
    });
    upsertCampaign(res.data.data);
    campaignStatus.value = campaign.value.schedule ? 'Campaign scheduled' : 'Campaign sent';
    activeTab.value = 'campaigns';
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Failed to schedule campaign.';
  } finally {
    saving.value = '';
  }
}

async function syncStats(row) {
  try {
    const res = await api.post(`/v1/email-campaigns/${row.id}/sync-stats`);
    upsertCampaign(res.data.data);
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to sync stats.');
  }
}

async function deleteCampaign(row) {
  if (!confirm(`Delete campaign "${row.name}"?`)) return;
  await api.delete(`/v1/email-campaigns/${row.id}`);
  campaigns.value = campaigns.value.filter((c) => c.id !== row.id);
}

function openSendTestModal() {
  testEmail.value = '';
  testMessage.value = '';
  showTestModal.value = true;
}

async function submitSendTest() {
  saving.value = 'test';
  testMessage.value = '';
  try {
    const res = await api.post(`/v1/email-campaigns/${campaign.value.id}/send-test`, { email: testEmail.value });
    testMessage.value = res.data.message ?? 'Test sent!';
  } catch (e) {
    testMessage.value = 'Error: ' + (e.response?.data?.message ?? 'Failed to send test.');
  } finally {
    saving.value = '';
  }
}

function openNewTemplateModal() {
  templateForm.value = { id: null, name: '', category: '', subject: '', body: '' };
  showTemplateModal.value = true;
}

async function saveTemplate() {
  if (!templateForm.value.name || !templateForm.value.subject || !templateForm.value.body) return;
  saving.value = 'template';
  try {
    let res;
    if (templateForm.value.id) {
      res = await api.put(`/v1/email-templates/${templateForm.value.id}`, templateForm.value);
      const idx = templates.value.findIndex((t) => t.id === templateForm.value.id);
      if (idx !== -1) templates.value[idx] = res.data.data;
    } else {
      res = await api.post('/v1/email-templates', templateForm.value);
      templates.value.unshift(res.data.data);
    }
    showTemplateModal.value = false;
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to save template.');
  } finally {
    saving.value = '';
  }
}

async function duplicateTemplate() {
  if (!selectedTemplate.value) return;
  saving.value = 'template';
  try {
    const res = await api.post('/v1/email-templates', {
      name: selectedTemplate.value.name + ' Copy',
      category: selectedTemplate.value.category,
      subject: selectedTemplate.value.subject,
      body: selectedTemplate.value.body,
    });
    templates.value.unshift(res.data.data);
    selectedTemplateId.value = res.data.data.id;
  } finally {
    saving.value = '';
  }
}

async function deleteTemplate(t) {
  if (!confirm(`Delete template "${t.name}"?`)) return;
  await api.delete(`/v1/email-templates/${t.id}`);
  templates.value = templates.value.filter((tmpl) => tmpl.id !== t.id);
  if (selectedTemplateId.value === t.id) selectedTemplateId.value = '';
}

function buildPayload() {
  return {
    name: campaign.value.name.trim(),
    subject: campaign.value.subject.trim(),
    preview_text: campaign.value.previewText.trim() || null,
    body: campaign.value.body.trim(),
    sender_name: campaign.value.sender_name.trim() || null,
    sender_email: campaign.value.sender_email.trim() || null,
    scheduled_at: campaign.value.schedule || null,
    pic_ids: campaign.value.picIds,
  };
}

function upsertCampaign(row) {
  const idx = campaigns.value.findIndex((c) => c.id === row.id);
  if (idx === -1) campaigns.value.unshift(row);
  else campaigns.value[idx] = row;
}

function ucfirst(str) {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
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
.header-subtitle, .panel-head p, .review-block p, .activity-list p, .segment-list p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.45; }
.header-actions, .inline-actions, .builder-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-primary, .btn-secondary, .text-button {
  height: 36px; border: none; border-radius: 7px; padding: 0 14px; font-size: 13px; font-weight: 850; cursor: pointer;
}
.btn-primary { background: #0f766e; color: #ffffff; }
.btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }
.btn-secondary { background: #e8eef5; color: #334155; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.text-button { background: transparent; color: #0f766e; padding: 0 4px; }
.text-button.danger { color: #dc2626; }
.text-button:disabled { color: #94a3b8; cursor: not-allowed; }
.module-tabs { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 8px; margin-bottom: 14px; }
.module-tabs button {
  min-height: 52px; border: 1.5px solid #dbe3ee; border-radius: 8px; background: #ffffff;
  color: #334155; font-size: 12px; font-weight: 850; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px;
}
.module-tabs button.active { border-color: #0f766e; box-shadow: 0 0 0 3px rgba(15,118,110,0.1); color: #0f766e; }
.module-tabs span { width: 24px; height: 24px; border-radius: 6px; background: #e8f4f2; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; }
.tab-space { display: grid; gap: 14px; }
.panel { background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 20px; }
.panel-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
.panel-head h2 { margin: 0; font-size: 15px; font-weight: 900; }
.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.metric-tile { background: #ffffff; border: 1px solid #dbe3ee; border-radius: 8px; padding: 18px 20px; }
.metric-tile span { display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
.metric-tile strong { display: block; font-size: 28px; font-weight: 900; color: #0f766e; margin-bottom: 4px; }
.metric-tile small { color: #94a3b8; font-size: 12px; }
.dashboard-grid { display: grid; grid-template-columns: 1fr 320px; gap: 14px; }
.wide-panel { grid-column: 1; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { border-bottom: 1px solid #f1f5f9; padding: 10px 12px; text-align: left; }
th { font-size: 11px; font-weight: 900; text-transform: uppercase; color: #64748b; background: #f8fafc; }
td strong { display: block; font-weight: 850; }
td small { color: #94a3b8; font-size: 11px; }
.status-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 900; text-transform: capitalize; }
.status-badge.draft { background: #f1f5f9; color: #475569; }
.status-badge.scheduled { background: #fef9c3; color: #854d0e; }
.status-badge.sent { background: #dcfce7; color: #166534; }
.status-badge.failed { background: #fee2e2; color: #991b1b; }
.activity-list div { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.activity-list span { font-size: 11px; font-weight: 900; color: #0f766e; }
.activity-list strong { display: block; font-size: 13px; font-weight: 850; margin: 3px 0; }
.row-actions { display: flex; gap: 4px; white-space: nowrap; }
.campaign-builder { grid-template-columns: 200px 1fr; }
.builder-steps { display: flex; flex-direction: column; gap: 8px; }
.builder-steps button {
  display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1.5px solid #dbe3ee;
  border-radius: 8px; background: white; color: #475569; font-size: 13px; font-weight: 750; cursor: pointer; text-align: left;
}
.builder-steps button.active { border-color: #0f766e; color: #0f766e; background: #f0fdf4; }
.builder-steps span { width: 24px; height: 24px; border-radius: 50%; background: #e8f4f2; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; flex-shrink: 0; }
.builder-panel { min-height: 400px; display: flex; flex-direction: column; }
.save-state { font-size: 12px; color: #0f766e; font-weight: 750; white-space: nowrap; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.form-grid label, .full-field { display: flex; flex-direction: column; gap: 6px; }
.form-grid label span, .full-field span { font-size: 11px; font-weight: 900; text-transform: uppercase; color: #64748b; }
.form-grid input, .form-grid select, .full-field input, .full-field textarea, .modal-input {
  height: 36px; border: 1.5px solid #dbe3ee; border-radius: 7px; padding: 0 10px;
  font-size: 13px; color: #172033; outline: none; background: white;
}
.form-grid input:focus, .form-grid select:focus, .full-field input:focus, .full-field textarea:focus { border-color: #0f766e; }
.full-field { grid-column: 1 / -1; }
.full-field textarea { height: 200px; padding: 10px; resize: vertical; line-height: 1.5; }
.merge-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.merge-tags button { height: 28px; border: 1.5px solid #dbe3ee; border-radius: 6px; background: #f8fafc; color: #334155; font-size: 11px; font-weight: 750; cursor: pointer; padding: 0 10px; }
.merge-tags button:hover { background: #e8f4f2; border-color: #0f766e; color: #0f766e; }
.content-grid { display: grid; grid-template-columns: 1fr 340px; gap: 18px; flex: 1; }
.email-preview { border: 1.5px solid #dbe3ee; border-radius: 8px; overflow: hidden; font-size: 13px; }
.email-topline { background: #f8fafc; padding: 10px 14px; border-bottom: 1px solid #dbe3ee; }
.email-topline span { display: block; font-size: 11px; color: #64748b; margin-bottom: 2px; }
.email-topline strong { font-size: 13px; font-weight: 850; }
.email-preview article { padding: 16px; }
.email-preview article p { margin: 0 0 10px; color: #374151; line-height: 1.5; }
.email-preview article button { background: #0f766e; color: white; border: none; border-radius: 6px; padding: 8px 14px; font-size: 12px; font-weight: 850; cursor: pointer; }
.audience-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.audience-box { border: 1.5px solid #dbe3ee; border-radius: 8px; overflow: hidden; max-height: 320px; overflow-y: auto; }
.audience-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #dbe3ee; font-size: 12px; font-weight: 850; }
.pic-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: 13px; }
.pic-row strong { display: block; font-weight: 850; }
.review-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.review-block { border: 1.5px solid #dbe3ee; border-radius: 8px; padding: 16px; }
.review-block span { display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
.review-block strong { display: block; font-size: 15px; font-weight: 900; margin-bottom: 4px; }
.builder-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: auto; padding-top: 20px; border-top: 1px solid #f1f5f9; }
.templates-grid { grid-template-columns: 260px 1fr; }
.template-list { display: flex; flex-direction: column; gap: 0; }
.template-list .panel-head { margin-bottom: 12px; }
.template-list > button {
  display: flex; flex-direction: column; align-items: flex-start; padding: 10px 14px;
  border: none; border-bottom: 1px solid #f1f5f9; background: white; cursor: pointer; text-align: left; gap: 2px;
}
.template-list > button.active { background: #f0fdf4; }
.template-list > button strong { font-size: 13px; font-weight: 850; color: #172033; }
.template-list > button span { font-size: 11px; color: #64748b; }
.template-preview { padding: 4px 0 16px; border-bottom: 1px solid #f1f5f9; margin-bottom: 14px; }
.template-preview h3 { margin: 0 0 12px; font-size: 14px; font-weight: 900; }
.template-preview p { margin: 0 0 8px; font-size: 13px; color: #374151; line-height: 1.5; }
.merge-tag-panel { display: flex; gap: 8px; flex-wrap: wrap; }
.merge-tag-panel span { display: inline-block; padding: 3px 9px; border: 1.5px solid #dbe3ee; border-radius: 6px; font-size: 11px; font-weight: 750; color: #475569; }
.lists-grid { grid-template-columns: 1fr 1fr; }
.segment-list div { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.segment-list strong { font-weight: 850; font-size: 13px; flex: 1; }
.segment-list span { font-size: 18px; font-weight: 900; color: #0f766e; min-width: 40px; text-align: right; }
.selected-email-list div { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.selected-email-list strong { font-weight: 850; }
.selected-email-list span { color: #64748b; }
.analytics-grid { grid-template-columns: 1fr 1fr; }
.bar-list div { display: flex; align-items: center; gap: 12px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.bar-list div > span { width: 180px; font-weight: 750; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-list div > div { flex: 1; height: 10px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
.bar-list div > div strong { display: block; height: 100%; background: #0f766e; border-radius: 999px; min-width: 2px; }
.bar-list em { font-style: normal; font-weight: 850; font-size: 12px; color: #0f766e; min-width: 40px; text-align: right; }
.channel-list div { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.channel-list strong { display: block; font-size: 13px; font-weight: 850; margin-bottom: 2px; }
.channel-list span { font-size: 12px; color: #374151; }
.channel-list small { display: block; font-size: 11px; color: #94a3b8; }
.settings-grid { grid-template-columns: 1fr 1fr; }
.smtp-panel .settings-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.smtp-panel .settings-row span { color: #64748b; }
.smtp-panel .settings-row strong { font-weight: 850; }
.settings-hint { margin: 14px 0 0; font-size: 12px; color: #64748b; line-height: 1.5; }
.settings-hint code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-family: monospace; }
.empty-copy { color: #94a3b8; font-size: 13px; font-weight: 700; padding: 8px 0; }
.error-banner { background: #fee2e2; color: #991b1b; border-radius: 7px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; font-weight: 750; }
.modal-backdrop { position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,0.55); display: flex; align-items: center; justify-content: center; padding: 22px; }
.mini-modal { background: white; border-radius: 10px; padding: 24px; width: min(420px, 96vw); box-shadow: 0 24px 70px rgba(15,23,42,0.3); }
.mini-modal.wide-modal { width: min(620px, 96vw); }
.mini-modal h3 { margin: 0 0 16px; font-size: 16px; font-weight: 900; }
.modal-input { width: 100%; box-sizing: border-box; margin-bottom: 14px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.modal-msg { margin: 0 0 12px; font-size: 13px; font-weight: 750; padding: 8px 12px; border-radius: 6px; }
.modal-msg.success { background: #dcfce7; color: #166534; }
.modal-msg.error { background: #fee2e2; color: #991b1b; }
@media (max-width: 900px) {
  .metric-grid { grid-template-columns: repeat(2, 1fr); }
  .dashboard-grid, .content-grid, .audience-layout, .analytics-grid, .settings-grid, .lists-grid, .templates-grid { grid-template-columns: 1fr; }
  .campaign-builder { grid-template-columns: 1fr; }
  .builder-steps { flex-direction: row; overflow-x: auto; }
  .module-tabs { grid-template-columns: repeat(4, 1fr); }
}
</style>
