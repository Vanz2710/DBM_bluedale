<template>
  <div class="page">

    <!-- Page Header -->
    <div class="page-header-row">
      <div>
        <h1 class="page-title">Email Marketing</h1>
        <p class="page-subtitle">Campaigns, contacts, lists, templates and analytics</p>
      </div>
      <div class="page-header-actions">
        <button v-if="tab === 'campaigns'" class="btn-primary" @click="openCampaignModal()">
          <span v-html="ICO.plus"></span> New Campaign
        </button>
        <button v-else-if="tab === 'contacts'" class="btn-primary" @click="openContactModal()">
          <span v-html="ICO.plus"></span> Add Contact
        </button>
        <button v-else-if="tab === 'lists'" class="btn-primary" @click="openListModal()">
          <span v-html="ICO.plus"></span> New List
        </button>
        <button v-else-if="tab === 'templates'" class="btn-primary" @click="openTemplateModal()">
          <span v-html="ICO.plus"></span> New Template
        </button>
      </div>
    </div>

    <!-- Tab Bar -->
    <div class="tab-bar">
      <button v-for="t in TABS" :key="t.id" :class="['tab-btn', tab === t.id && 'active']" @click="switchTab(t.id)">
        <span class="tab-icon" v-html="t.icon"></span>
        {{ t.label }}
      </button>
    </div>

    <!-- ── Dashboard ───────────────────────────────────────────────────────── -->
    <div v-show="tab === 'dashboard'" class="tab-section">
      <div v-if="dashLoading" class="view-loading"><div class="spinner"></div></div>
      <template v-else>
        <div class="stat-grid">
          <div v-for="s in dashStats" :key="s.label" class="stat-card" :class="'accent-'+s.color">
            <div class="stat-icon-wrap" :class="'icon-'+s.color" v-html="s.icon"></div>
            <div class="stat-body">
              <div class="stat-val">{{ s.value }}</div>
              <div class="stat-lbl">{{ s.label }}</div>
            </div>
          </div>
        </div>

        <div class="dash-grid">
          <div class="card">
            <div class="card-head">
              <span class="card-title">Recent Campaigns</span>
              <button class="btn-link" @click="tab = 'campaigns'">View all</button>
            </div>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Name</th><th>Status</th><th>Sent</th><th>Open Rate</th><th>Click Rate</th></tr></thead>
                <tbody>
                  <tr v-for="c in dash.recent_campaigns" :key="c.id">
                    <td><strong>{{ c.name }}</strong></td>
                    <td><span class="badge" :class="'badge-'+statusColor(c.status)">{{ ucfirst(c.status) }}</span></td>
                    <td>{{ c.sent_count ?? 0 }}</td>
                    <td>{{ pct(c.open_rate) }}</td>
                    <td>{{ pct(c.click_rate) }}</td>
                  </tr>
                  <tr v-if="!dash.recent_campaigns?.length"><td colspan="5" class="empty-cell">No campaigns yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-head"><span class="card-title">Activity Timeline</span></div>
            <div class="timeline-list">
              <div v-for="t in dash.timeline" :key="t.id" class="tl-row">
                <span class="tl-dot" :class="'tl-'+statusColor(t.status)"></span>
                <div>
                  <div class="tl-name">{{ t.name }}</div>
                  <div class="tl-meta">{{ ucfirst(t.status) }} · {{ t.count }} recipients · {{ timeAgo(t.at) }}</div>
                </div>
              </div>
              <div v-if="!dash.timeline?.length" class="empty-cell">No activity yet.</div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Contacts ────────────────────────────────────────────────────────── -->
    <div v-if="visited.contacts" v-show="tab === 'contacts'" class="tab-section">
      <div class="filter-bar">
        <div class="filter-search">
          <input type="text" v-model="cFilter.search" @keyup.enter="loadContacts(1)" placeholder="Search name, email…" class="field-input" />
        </div>
        <select v-model="cFilter.status" @change="loadContacts(1)" class="field-input sm">
          <option value="">All Statuses</option>
          <option value="subscribed">Subscribed</option>
          <option value="unsubscribed">Unsubscribed</option>
          <option value="bounced">Bounced</option>
          <option value="pending">Pending</option>
        </select>
        <select v-model="cFilter.tag_id" @change="loadContacts(1)" class="field-input sm">
          <option value="">All Tags</option>
          <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <div class="filter-actions">
          <button class="btn-ghost sm" @click="syncCrm" :disabled="busy === 'sync'">
            <span v-html="ICO.refresh"></span>
            {{ busy === 'sync' ? 'Syncing…' : 'Sync CRM' }}
          </button>
          <button class="btn-ghost sm" @click="exportContacts">
            <span v-html="ICO.download"></span> Export
          </button>
          <button class="btn-ghost sm" @click="openTagManager">
            <span v-html="ICO.tag"></span> Tags
          </button>
        </div>
      </div>

      <div v-if="selectedContactIds.length" class="bulk-bar">
        <span class="bulk-count">{{ selectedContactIds.length }} selected</span>
        <button class="btn-link" @click="bulkContacts('subscribe')">Subscribe</button>
        <button class="btn-link" @click="bulkContacts('unsubscribe')">Unsubscribe</button>
        <select v-model="bulkTagId" class="field-input sm">
          <option value="">Add tag…</option>
          <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <button class="btn-link" :disabled="!bulkTagId" @click="bulkContacts('add_tag')">Apply</button>
        <button class="btn-link danger" @click="bulkContacts('delete')">Delete</button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-check"><input type="checkbox" :checked="allContactsSelected" @change="toggleSelectAll" /></th>
              <th>Name</th><th>Email</th><th>Company</th><th>Tags</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cLoading"><td colspan="7" class="empty-cell">Loading…</td></tr>
            <tr v-else-if="!contacts.length"><td colspan="7" class="empty-cell">No contacts found. Use "Sync CRM" to import from your contacts.</td></tr>
            <tr v-for="c in contacts" :key="c.id">
              <td class="col-check"><input type="checkbox" :value="c.id" v-model="selectedContactIds" /></td>
              <td><strong>{{ c.full_name ?? '—' }}</strong></td>
              <td>{{ c.email }}</td>
              <td>{{ c.company ?? '—' }}</td>
              <td>
                <span v-for="t in c.tags" :key="t.id" class="tag-pill" :style="{ background: t.color+'22', color: t.color }">{{ t.name }}</span>
                <span v-if="!c.tags?.length" class="text-muted">—</span>
              </td>
              <td><span class="badge" :class="'badge-'+statusColor(c.status)">{{ ucfirst(c.status) }}</span></td>
              <td class="actions-cell" @click.stop>
                <button class="btn-icon" @click="openContactModal(c)" v-html="ICO.edit" title="Edit"></button>
                <button class="btn-icon danger" @click="deleteContact(c)" v-html="ICO.trash" title="Delete"></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="cMeta.last_page > 1" class="pagination-bar">
        <span class="page-info">Page {{ cMeta.current_page }} / {{ cMeta.last_page }} ({{ cMeta.total }} total)</span>
        <div class="page-btns">
          <button class="btn-ghost sm" :disabled="cMeta.current_page <= 1" @click="loadContacts(cMeta.current_page - 1)">Prev</button>
          <button class="btn-ghost sm" :disabled="cMeta.current_page >= cMeta.last_page" @click="loadContacts(cMeta.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- ── Lists (Audience Groups) ─────────────────────────────────────────── -->
    <div v-if="visited.lists" v-show="tab === 'lists'" class="tab-section">
      <!-- List index -->
      <template v-if="listsView === 'index'">
        <div class="filter-bar">
          <div class="filter-search">
            <input type="text" v-model="listSearch" placeholder="Search lists…" class="field-input" />
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>List Name</th><th>Type</th><th>Contacts</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <tr v-if="listsLoading"><td colspan="5" class="empty-cell">Loading…</td></tr>
              <tr v-else-if="!filteredLists.length"><td colspan="5" class="empty-cell">No lists yet.</td></tr>
              <tr v-for="list in filteredLists" :key="list.id">
                <td>
                  <button class="btn-link" @click="openListDetail(list)">{{ list.name }}</button>
                  <div v-if="list.description" class="text-muted sm">{{ list.description }}</div>
                </td>
                <td><span class="badge badge-blue">{{ ucfirst(list.type) }}</span></td>
                <td>
                  <div class="capacity-row">
                    <div class="capacity-track">
                      <div class="capacity-fill" :class="list.is_full && 'full'" :style="{ width: Math.min(100, Math.round((list.count / (list.max_contacts||200)) * 100)) + '%' }"></div>
                    </div>
                    <span :class="list.is_full && 'text-danger'">{{ list.count }} / {{ list.max_contacts ?? 200 }}</span>
                  </div>
                </td>
                <td class="text-muted">{{ formatDate(list.created_at) }}</td>
                <td class="actions-cell" @click.stop>
                  <button class="btn-icon" @click="openListDetail(list)" v-html="ICO.eye" title="View"></button>
                  <button class="btn-icon" @click="openListModal(list)" v-html="ICO.edit" title="Edit"></button>
                  <button class="btn-icon danger" :disabled="list.is_system" @click="deleteList(list)" v-html="ICO.trash" title="Delete"></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- List detail -->
      <template v-else-if="listsView === 'detail' && activeList">
        <div class="back-nav">
          <button class="btn-back" @click="listsView = 'index'"><span v-html="ICO.chevronL"></span> All Lists</button>
        </div>
        <div class="page-header-row">
          <div>
            <h2 class="section-title">{{ activeList.name }}</h2>
            <p v-if="activeList.description" class="text-muted">{{ activeList.description }}</p>
          </div>
          <button class="btn-primary" :disabled="activeList.is_full" @click="listsView = 'import'">
            <span v-html="ICO.download"></span> Import Contacts
          </button>
        </div>
        <div v-if="activeList.is_full" class="alert alert-danger">
          This list is at maximum capacity ({{ activeList.max_contacts }} contacts). Delete contacts or create a new list.
        </div>
        <div class="stat-grid sm">
          <div class="stat-card accent-blue">
            <div class="stat-body"><div class="stat-val">{{ activeList.count }}</div><div class="stat-lbl">Total Contacts</div></div>
          </div>
          <div class="stat-card" :class="activeList.is_full ? 'accent-red' : 'accent-green'">
            <div class="stat-body"><div class="stat-val">{{ activeList.slots_remaining }}</div><div class="stat-lbl">Slots Available</div></div>
          </div>
          <div class="stat-card accent-gray">
            <div class="stat-body"><div class="stat-val">{{ activeList.max_contacts ?? 200 }}</div><div class="stat-lbl">Max Capacity</div></div>
          </div>
        </div>
        <div class="capacity-bar-wrap">
          <div class="capacity-bar-track">
            <div class="capacity-bar-fill" :class="activeList.is_full && 'full'" :style="{ width: Math.min(100, Math.round((activeList.count / (activeList.max_contacts||200)) * 100)) + '%' }"></div>
          </div>
          <div class="capacity-bar-labels">
            <span>{{ activeList.count }} used</span>
            <span :class="activeList.is_full && 'text-danger'">{{ activeList.slots_remaining }} remaining</span>
          </div>
        </div>
        <div class="card" style="margin-top:20px">
          <div class="card-head"><span class="card-title">Contacts in this list</span></div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-if="listMembersLoading"><td colspan="4" class="empty-cell">Loading…</td></tr>
                <tr v-else-if="!listMembers.length"><td colspan="4" class="empty-cell">No contacts in this list yet.</td></tr>
                <tr v-for="c in listMembers" :key="c.id">
                  <td>{{ c.full_name || '—' }}</td>
                  <td>{{ c.email }}</td>
                  <td>{{ c.company || '—' }}</td>
                  <td><span class="badge" :class="'badge-'+statusColor(c.status)">{{ ucfirst(c.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="listMembersMeta.last_page > 1" class="pagination-bar">
            <div class="page-btns">
              <button class="btn-ghost sm" :disabled="listMembersMeta.current_page <= 1" @click="loadListMembers(listMembersMeta.current_page - 1)">Prev</button>
              <span class="page-num">{{ listMembersMeta.current_page }} / {{ listMembersMeta.last_page }}</span>
              <button class="btn-ghost sm" :disabled="listMembersMeta.current_page >= listMembersMeta.last_page" @click="loadListMembers(listMembersMeta.current_page + 1)">Next</button>
            </div>
          </div>
        </div>
      </template>

      <!-- Import wizard -->
      <template v-else-if="listsView === 'import' && activeList">
        <div class="back-nav">
          <button class="btn-back" @click="listsView = 'detail'"><span v-html="ICO.chevronL"></span> {{ activeList.name }}</button>
        </div>
        <h2 class="section-title">Import Contacts into "{{ activeList.name }}"</h2>

        <div v-if="importStep !== 'result'" class="step-bar">
          <div v-for="(s, idx) in importSteps" :key="s.id" class="step-item" :class="{ active: importStep === s.id, done: importStepIdx > idx }">
            <div class="step-dot">{{ importStepIdx > idx ? '✓' : idx + 1 }}</div>
            <span>{{ s.label }}</span>
          </div>
        </div>

        <!-- Step: Method -->
        <div v-if="importStep === 'method'" class="method-grid">
          <button class="method-card" @click="importMethod = 'paste'; importStep = 'input'">
            <span v-html="ICO.edit" class="method-ico"></span>
            <strong>Paste CSV Data</strong>
            <p>Copy contacts from a spreadsheet and paste directly.</p>
          </button>
          <button class="method-card" @click="importMethod = 'file'; importStep = 'input'">
            <span v-html="ICO.paperclip" class="method-ico"></span>
            <strong>Upload CSV File</strong>
            <p>Drag and drop or browse for a .csv file.</p>
          </button>
        </div>

        <!-- Step: Input -->
        <div v-else-if="importStep === 'input'" class="card">
          <div v-if="importMethod === 'paste'">
            <div class="card-head"><span class="card-title">Paste Email Addresses</span><span class="text-muted sm">One per line</span></div>
            <textarea v-model="importPasteText" class="field-input textarea-lg" placeholder="john@example.com&#10;jane@company.com"></textarea>
            <p v-if="importPasteText.trim()" class="text-muted sm">{{ importPasteText.trim().split('\n').filter(l => l.trim()).length }} address(es) detected</p>
          </div>
          <div v-else>
            <div class="card-head"><span class="card-title">Upload CSV File</span></div>
            <div class="dropzone" :class="importDragOver && 'dz-over'" @dragover.prevent="importDragOver = true" @dragleave="importDragOver = false" @drop.prevent="onImportFileDrop" @click="$refs.importFileRef.click()">
              <input ref="importFileRef" type="file" accept=".csv,.txt" hidden @change="onImportFileSelect" />
              <span v-html="importFile ? ICO.check : ICO.paperclip" class="dz-ico"></span>
              <strong>{{ importFile ? importFile.name : 'Drag & drop CSV here' }}</strong>
              <p v-if="!importFile" class="text-muted">or click to browse — .csv files only</p>
            </div>
          </div>
          <div class="import-nav">
            <button class="btn-ghost" @click="importStep = 'method'"><span v-html="ICO.chevronL"></span> Back</button>
            <button class="btn-primary" :disabled="importMethod === 'paste' ? !importPasteText.trim() : !importFile" @click="parseImport">Parse & Continue</button>
          </div>
        </div>

        <!-- Step: Preview -->
        <div v-else-if="importStep === 'preview'" class="card">
          <div class="card-head">
            <span class="card-title">Preview</span>
            <div style="display:flex;gap:8px">
              <span class="badge badge-green">{{ importValidCount }} valid</span>
              <span class="badge badge-red">{{ importInvalidCount }} invalid</span>
            </div>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Email</th><th>Name</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in importPreviewRows.slice(0, 10)" :key="i" :class="!r.valid && 'row-invalid'">
                  <td>{{ r.email }}</td>
                  <td>{{ r.name || '—' }}</td>
                  <td><span v-if="!r.valid" class="badge badge-red">{{ r.error }}</span><span v-else class="badge badge-green">OK</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="importPreviewRows.length > 10" class="text-muted sm" style="margin-top:8px">Showing first 10 of {{ importPreviewRows.length }}</p>
          <div class="import-nav">
            <button class="btn-ghost" @click="importStep = 'input'"><span v-html="ICO.chevronL"></span> Back</button>
            <button class="btn-primary" :disabled="importValidCount === 0 || busy === 'import'" @click="doImport">
              {{ busy === 'import' ? 'Importing…' : `Import ${importValidCount} contact(s)` }}
            </button>
          </div>
        </div>

        <!-- Step: Result -->
        <div v-else-if="importStep === 'result'" class="card import-result">
          <span v-html="ICO.check" class="result-ico"></span>
          <h3>Import Complete</h3>
          <p>{{ importResult.imported }} contact(s) added. {{ importResult.skipped }} skipped (duplicates/invalid).</p>
          <div class="import-nav">
            <button class="btn-ghost" @click="resetImport">Import More</button>
            <button class="btn-primary" @click="listsView = 'detail'">Back to List</button>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Campaigns ───────────────────────────────────────────────────────── -->
    <div v-if="visited.campaigns" v-show="tab === 'campaigns'" class="tab-section">
      <div class="filter-bar">
        <div class="filter-search">
          <input type="text" v-model="campSearch" @keyup.enter="loadCampaigns" placeholder="Search campaigns…" class="field-input" />
        </div>
        <select v-model="campStatusFilter" @change="loadCampaigns" class="field-input sm">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="scheduled">Scheduled</option>
          <option value="sending">Sending</option>
          <option value="sent">Sent</option>
        </select>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Campaign</th><th>Audience</th><th>Status</th><th>Sent</th><th>Open Rate</th><th>Click Rate</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="campLoading"><td colspan="8" class="empty-cell">Loading…</td></tr>
            <tr v-else-if="!filteredCampaigns.length"><td colspan="8" class="empty-cell">No campaigns yet. Click "New Campaign" to create one.</td></tr>
            <tr v-for="c in filteredCampaigns" :key="c.id" class="table-row" @click="openCampaignModal(c)">
              <td>
                <strong>{{ c.name }}</strong>
                <div v-if="c.subject" class="text-muted sm">{{ c.subject }}</div>
              </td>
              <td>{{ c.audience_group ?? '—' }}</td>
              <td><span class="badge" :class="'badge-'+statusColor(c.status)">{{ ucfirst(c.status) }}</span></td>
              <td>{{ c.sent_count ?? 0 }}</td>
              <td>{{ pct(c.open_rate) }}</td>
              <td>{{ pct(c.click_rate) }}</td>
              <td class="text-muted">{{ formatDate(c.updated_at) }}</td>
              <td class="actions-cell" @click.stop>
                <button class="btn-icon" @click="duplicateCampaign(c)" v-html="ICO.copy" title="Duplicate"></button>
                <button v-if="c.status === 'draft'" class="btn-icon" @click="openSendModal(c)" v-html="ICO.send" title="Send"></button>
                <button class="btn-icon" @click="openCampaignModal(c)" v-html="ICO.edit" title="Edit"></button>
                <button class="btn-icon danger" @click="deleteCampaign(c)" v-html="ICO.trash" title="Delete"></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Templates ──────────────────────────────────────────────────────── -->
    <div v-if="visited.templates" v-show="tab === 'templates'" class="tab-section">
      <div v-if="templatesLoading" class="view-loading"><div class="spinner"></div></div>
      <div v-else-if="!templates.length" class="empty-state">
        <span v-html="ICO.edit" class="empty-icon"></span>
        <p>No templates yet. Create reusable email layouts to speed up campaign creation.</p>
      </div>
      <div v-else class="template-grid">
        <div v-for="t in templates" :key="t.id" class="template-card">
          <span class="badge badge-blue">{{ t.category || 'General' }}</span>
          <strong class="template-name">{{ t.name }}</strong>
          <p class="text-muted sm">{{ t.subject }}</p>
          <div class="template-actions">
            <button class="btn-ghost sm" @click="openTemplateModal(t)">Edit</button>
            <button class="btn-ghost sm" @click="duplicateTemplate(t)">Duplicate</button>
            <button class="btn-ghost sm danger" @click="deleteTemplate(t)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Analytics ──────────────────────────────────────────────────────── -->
    <div v-if="visited.analytics" v-show="tab === 'analytics'" class="tab-section">
      <div v-if="analyticsLoading" class="view-loading"><div class="spinner"></div></div>
      <template v-else>
        <div class="dash-grid">
          <div class="card">
            <div class="card-head"><span class="card-title">Open &amp; Click Rate Trend</span></div>
            <div v-if="analytics.trend?.length" class="bar-list">
              <div v-for="c in analytics.trend" :key="c.name + c.date" class="bar-row">
                <span class="bar-name text-muted sm">{{ c.name }}</span>
                <div class="bar-track"><div class="bar-fill" :style="{ width: (c.open_rate || 0) + '%' }"></div></div>
                <span class="bar-val">{{ pct(c.open_rate) }} open</span>
              </div>
            </div>
            <div v-else class="empty-cell">No sent campaigns yet.</div>
          </div>

          <div class="card">
            <div class="card-head"><span class="card-title">Audience Growth</span></div>
            <div class="chart-cols">
              <div v-for="m in analytics.audience_growth" :key="m.month" class="chart-col" :title="`${m.month}: ${m.count}`">
                <div class="chart-bar-wrap"><div class="chart-bar" :style="{ height: growthH(m.count, analytics.audience_growth) }"></div></div>
                <span class="chart-lbl">{{ m.month?.slice(0, 3) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:20px">
          <div class="card-head"><span class="card-title">Most Active Contacts</span></div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Contact</th><th>Email</th><th>Opens</th><th>Clicks</th></tr></thead>
              <tbody>
                <tr v-for="c in analytics.most_active_contacts" :key="c.email">
                  <td><strong>{{ c.name ?? '—' }}</strong></td>
                  <td>{{ c.email }}</td>
                  <td>{{ c.opens }}</td>
                  <td>{{ c.clicks }}</td>
                </tr>
                <tr v-if="!analytics.most_active_contacts?.length"><td colspan="4" class="empty-cell">No engagement recorded yet.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Settings ───────────────────────────────────────────────────────── -->
    <div v-if="visited.settings" v-show="tab === 'settings'" class="tab-section">
      <div v-if="settingsLoading" class="view-loading"><div class="spinner"></div></div>
      <div v-else class="settings-grid">
        <div class="card">
          <div class="card-head">
            <span class="card-title">SMTP Configuration</span>
            <span class="badge" :class="settingsForm.smtp_host ? 'badge-green' : 'badge-gray'">{{ settingsForm.smtp_host ? 'Connected' : 'Not configured' }}</span>
          </div>
          <div class="form-grid">
            <div class="form-group full">
              <label>Provider Preset</label>
              <select class="field-input" @change="applyPreset($event.target.value)">
                <option value="">Custom</option>
                <option value="gmail">Gmail</option>
                <option value="outlook">Outlook</option>
                <option value="sendgrid">SendGrid</option>
                <option value="mailgun">Mailgun</option>
              </select>
            </div>
            <div class="form-group">
              <label>SMTP Host</label>
              <input v-model="settingsForm.smtp_host" type="text" class="field-input" placeholder="smtp.gmail.com" />
            </div>
            <div class="form-group">
              <label>SMTP Port</label>
              <input v-model.number="settingsForm.smtp_port" type="number" class="field-input" placeholder="587" />
            </div>
            <div class="form-group">
              <label>Username</label>
              <input v-model="settingsForm.smtp_username" type="text" class="field-input" />
            </div>
            <div class="form-group">
              <label>Password</label>
              <input v-model="settingsForm.smtp_password" type="password" class="field-input" placeholder="Leave blank to keep current" />
            </div>
            <div class="form-group">
              <label>Encryption</label>
              <select v-model="settingsForm.smtp_encryption" class="field-input">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="none">None</option>
              </select>
            </div>
            <div class="form-group full">
              <label class="toggle-label">
                <input type="checkbox" v-model="settingsForm.tracking_enabled" />
                <span>Enable open &amp; click tracking</span>
              </label>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><span class="card-title">Sender Identity</span></div>
          <div class="form-grid">
            <div class="form-group">
              <label>From Name</label>
              <input v-model="settingsForm.from_name" type="text" class="field-input" />
            </div>
            <div class="form-group">
              <label>From Email</label>
              <input v-model="settingsForm.from_email" type="email" class="field-input" />
            </div>
            <div class="form-group full">
              <label>Reply-To</label>
              <input v-model="settingsForm.reply_to" type="email" class="field-input" />
            </div>
            <div class="form-group">
              <label>Company Name</label>
              <input v-model="settingsForm.company_name" type="text" class="field-input" />
            </div>
            <div class="form-group">
              <label>Company Address</label>
              <input v-model="settingsForm.company_address" type="text" class="field-input" />
            </div>
            <div class="form-group full">
              <label>Email Footer</label>
              <textarea v-model="settingsForm.email_footer" class="field-input" rows="3"></textarea>
            </div>
          </div>
        </div>

        <div class="settings-actions">
          <input v-model="testEmailTo" type="email" class="field-input" placeholder="test@example.com" style="max-width:260px" />
          <button class="btn-ghost" :disabled="busy === 'test'" @click="testSmtp">{{ busy === 'test' ? 'Sending…' : 'Send Test Email' }}</button>
          <button class="btn-primary" :disabled="busy === 'settings'" @click="saveSettings">{{ busy === 'settings' ? 'Saving…' : 'Save Settings' }}</button>
        </div>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════════════ -->
    <!-- Modals                                                               -->
    <!-- ═════════════════════════════════════════════════════════════════════ -->

    <!-- Campaign Modal -->
    <Teleport to="body">
      <div v-if="showCampaignModal" class="modal-backdrop">
        <div class="modal modal-lg">
          <div class="modal-header">
            <h3>{{ campaignForm.id ? 'Edit Campaign' : 'New Campaign' }}</h3>
            <button class="btn-icon" @click="showCampaignModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div v-if="modalError" class="alert alert-danger">{{ modalError }}</div>
            <div class="form-grid">
              <div class="form-group full">
                <label>Campaign Name <span class="req">*</span></label>
                <input v-model="campaignForm.name" type="text" class="field-input" placeholder="e.g. June Newsletter" />
              </div>
              <div class="form-group full">
                <label>Subject Line</label>
                <input v-model="campaignForm.subject" type="text" class="field-input" placeholder="Email subject" />
              </div>
              <div class="form-group full">
                <label>Preview Text</label>
                <input v-model="campaignForm.preview_text" type="text" class="field-input" placeholder="Short summary shown in inbox preview" />
              </div>
              <div class="form-group">
                <label>Sender Name</label>
                <input v-model="campaignForm.sender_name" type="text" class="field-input" />
              </div>
              <div class="form-group">
                <label>Sender Email</label>
                <input v-model="campaignForm.sender_email" type="email" class="field-input" />
              </div>
              <div class="form-group full">
                <label>Audience List</label>
                <select v-model="campaignForm.audience_group_id" class="field-input">
                  <option :value="null">— No list selected —</option>
                  <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }} ({{ g.count ?? 0 }} contacts)</option>
                </select>
              </div>
              <div class="form-group full">
                <label>Email Body</label>
                <textarea v-model="campaignForm.body" class="field-input" rows="8" placeholder="Write your email content here (HTML supported)…"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="showCampaignModal = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'campaign' || !campaignForm.name?.trim()" @click="saveCampaign">
              {{ busy === 'campaign' ? 'Saving…' : (campaignForm.id ? 'Save Changes' : 'Create Campaign') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Send / Schedule Modal -->
    <Teleport to="body">
      <div v-if="showSendModal" class="modal-backdrop">
        <div class="modal">
          <div class="modal-header">
            <h3>Send Campaign</h3>
            <button class="btn-icon" @click="showSendModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div v-if="modalError" class="alert alert-danger">{{ modalError }}</div>
            <p class="text-muted">Campaign: <strong>{{ sendTarget?.name }}</strong></p>
            <p class="text-muted">Audience: {{ sendTarget?.audience_group ?? 'No list selected' }} · {{ sendTarget?.audience_count ?? 0 }} recipients</p>

            <div class="send-options">
              <button :class="['send-opt', sendMode === 'now' && 'active']" @click="sendMode = 'now'">
                <span v-html="ICO.send" class="send-opt-ico"></span>
                <strong>Send Now</strong>
                <p>Deliver to all recipients immediately</p>
              </button>
              <button :class="['send-opt', sendMode === 'schedule' && 'active']" @click="sendMode = 'schedule'">
                <span v-html="ICO.clock" class="send-opt-ico"></span>
                <strong>Schedule</strong>
                <p>Pick a future date and time</p>
              </button>
            </div>

            <div v-if="sendMode === 'schedule'" class="form-group" style="margin-top:16px">
              <label>Send At</label>
              <input v-model="scheduledAt" type="datetime-local" class="field-input" />
            </div>

            <div class="form-group" style="margin-top:16px">
              <label>Send Test Email</label>
              <div style="display:flex;gap:8px">
                <input v-model="testEmailTo" type="email" class="field-input" placeholder="you@example.com" />
                <button class="btn-ghost" :disabled="busy === 'test'" @click="sendTestEmail">{{ busy === 'test' ? '…' : 'Test' }}</button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="showSendModal = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'send'" @click="doSend">
              {{ busy === 'send' ? 'Sending…' : (sendMode === 'now' ? 'Send Now' : 'Schedule') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Contact Modal -->
    <Teleport to="body">
      <div v-if="showContactModal" class="modal-backdrop">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ contactForm.id ? 'Edit Contact' : 'New Contact' }}</h3>
            <button class="btn-icon" @click="showContactModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label>Full Name</label>
                <input v-model="contactForm.full_name" type="text" class="field-input" />
              </div>
              <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input v-model="contactForm.email" type="email" class="field-input" />
              </div>
              <div class="form-group">
                <label>Phone</label>
                <input v-model="contactForm.phone" type="text" class="field-input" />
              </div>
              <div class="form-group">
                <label>Company</label>
                <input v-model="contactForm.company" type="text" class="field-input" />
              </div>
              <div class="form-group full">
                <label>Status</label>
                <select v-model="contactForm.status" class="field-input">
                  <option value="subscribed">Subscribed</option>
                  <option value="unsubscribed">Unsubscribed</option>
                  <option value="pending">Pending</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="showContactModal = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'contact' || !contactForm.email?.trim()" @click="saveContact">
              {{ busy === 'contact' ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- List Modal -->
    <Teleport to="body">
      <div v-if="showListModal" class="modal-backdrop">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ listForm.id ? 'Edit List' : 'Create List' }}</h3>
            <button class="btn-icon" @click="showListModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="form-grid">
              <div class="form-group full">
                <label>List Name <span class="req">*</span></label>
                <input v-model="listForm.name" type="text" class="field-input" placeholder="e.g. Newsletter Subscribers" />
              </div>
              <div class="form-group full">
                <label>Description</label>
                <input v-model="listForm.description" type="text" class="field-input" placeholder="Optional description" />
              </div>
              <div class="form-group full">
                <label>Max Contacts</label>
                <input v-model.number="listForm.max_contacts" type="number" class="field-input" min="1" max="10000" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="showListModal = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'list' || !listForm.name?.trim()" @click="saveList">
              {{ busy === 'list' ? 'Saving…' : (listForm.id ? 'Save Changes' : 'Create List') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Template Modal -->
    <Teleport to="body">
      <div v-if="showTemplateModal" class="modal-backdrop">
        <div class="modal modal-lg">
          <div class="modal-header">
            <h3>{{ templateForm.id ? 'Edit Template' : 'New Template' }}</h3>
            <button class="btn-icon" @click="showTemplateModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label>Template Name <span class="req">*</span></label>
                <input v-model="templateForm.name" type="text" class="field-input" />
              </div>
              <div class="form-group">
                <label>Category</label>
                <input v-model="templateForm.category" type="text" class="field-input" placeholder="e.g. Newsletter" />
              </div>
              <div class="form-group full">
                <label>Subject</label>
                <input v-model="templateForm.subject" type="text" class="field-input" />
              </div>
              <div class="form-group full">
                <label>Body</label>
                <textarea v-model="templateForm.body" class="field-input" rows="10"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="showTemplateModal = false">Cancel</button>
            <button class="btn-primary" :disabled="busy === 'template' || !templateForm.name?.trim()" @click="saveTemplate">
              {{ busy === 'template' ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Tag Manager Modal -->
    <Teleport to="body">
      <div v-if="showTagModal" class="modal-backdrop">
        <div class="modal">
          <div class="modal-header">
            <h3>Manage Tags</h3>
            <button class="btn-icon" @click="showTagModal = false" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <div class="tag-form">
              <input v-model="tagForm.name" type="text" class="field-input" placeholder="Tag name" />
              <input v-model="tagForm.color" type="color" class="color-picker" />
              <button class="btn-primary sm" :disabled="!tagForm.name?.trim() || busy === 'tag'" @click="saveTag">
                {{ tagForm.id ? 'Update' : 'Add' }}
              </button>
            </div>
            <div class="tag-list">
              <div v-for="t in tags" :key="t.id" class="tag-row">
                <span class="tag-pill" :style="{ background: t.color+'22', color: t.color }">{{ t.name }}</span>
                <div class="tag-row-actions">
                  <button class="btn-icon" @click="editTag(t)" v-html="ICO.edit"></button>
                  <button class="btn-icon danger" @click="deleteTag(t)" v-html="ICO.trash"></button>
                </div>
              </div>
              <p v-if="!tags.length" class="text-muted sm">No tags yet.</p>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-primary" @click="showTagModal = false">Done</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast -->
    <div class="toast-area">
      <div v-for="t in toasts" :key="t.id" class="toast" :class="'toast-'+t.type">{{ t.text }}</div>
    </div>

    <!-- Generic Confirm Dialog -->
    <Teleport to="body">
      <div v-if="confirmDlg.show" class="modal-backdrop" style="z-index:1100">
        <div class="modal" style="max-width:400px">
          <div class="modal-header">
            <h3>{{ confirmDlg.title }}</h3>
            <button class="btn-icon" @click="resolveConfirm(false)" v-html="ICO.x"></button>
          </div>
          <div class="modal-body">
            <p style="margin:0;font-size:14px;color:var(--text-2)">{{ confirmDlg.message }}</p>
          </div>
          <div class="modal-footer">
            <button class="btn-ghost" @click="resolveConfirm(false)">Cancel</button>
            <button class="btn-primary" style="background:#dc2626" @click="resolveConfirm(true)">{{ confirmDlg.ok }}</button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, watch } from 'vue';
import api from '../api.js';

// ─── Icons ────────────────────────────────────────────────────────────────────
function _i(p) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
}
const ICO = {
  chart:     _i('<rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="14" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/>'),
  users:     _i('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  list:      _i('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
  mail:      _i('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'),
  template:  _i('<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>'),
  trending:  _i('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'),
  settings:  _i('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
  plus:      _i('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'),
  edit:      _i('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  trash:     _i('<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
  x:         _i('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
  check:     _i('<polyline points="20 6 9 17 4 12"/>'),
  send:      _i('<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>'),
  copy:      _i('<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'),
  clock:     _i('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
  chevronL:  _i('<polyline points="15 18 9 12 15 6"/>'),
  chevronR:  _i('<polyline points="9 18 15 12 9 6"/>'),
  download:  _i('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'),
  paperclip: _i('<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>'),
  refresh:   _i('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.29"/>'),
  eye:       _i('<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'),
  tag:       _i('<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>'),
};

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const TABS = [
  { id: 'dashboard',  label: 'Dashboard',  icon: ICO.chart },
  { id: 'contacts',   label: 'Contacts',   icon: ICO.users },
  { id: 'lists',      label: 'Lists',      icon: ICO.list },
  { id: 'campaigns',  label: 'Campaigns',  icon: ICO.mail },
  { id: 'templates',  label: 'Templates',  icon: ICO.template },
  { id: 'analytics',  label: 'Analytics',  icon: ICO.trending },
  { id: 'settings',   label: 'Settings',   icon: ICO.settings },
];
const tab = ref('dashboard');

// Which tabs have been rendered at least once (keep DOM alive, avoids remount lag)
const visited = reactive({
  dashboard: true, contacts: false, lists: false,
  campaigns: false, templates: false, analytics: false, settings: false,
});
// Which tabs have already fetched their data (prevents redundant API calls)
const loaded = {
  dashboard: false, contacts: false, lists: false,
  campaigns: false, templates: false, analytics: false, settings: false,
};

function switchTab(id) {
  visited[id] = true;
  tab.value   = id;
}

watch(tab, id => {
  if (loaded[id]) return;
  loaded[id] = true;
  if (id === 'contacts')  loadContacts(1);
  if (id === 'campaigns') loadCampaigns();
  if (id === 'templates') loadTemplates();
  if (id === 'analytics') loadAnalytics();
  if (id === 'settings')  loadSettings();
});

// ─── Toast ────────────────────────────────────────────────────────────────────
const toasts = ref([]);
let toastId = 0;
function toast(text, type = 'success') {
  const id = ++toastId;
  toasts.value.push({ id, text, type });
  setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 3500);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function pct(v) { return v ? Number(v).toFixed(1) + '%' : '0%'; }
function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}
function timeAgo(d) {
  if (!d) return '';
  const diff = Date.now() - new Date(d).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1)  return 'Just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}
function statusColor(s) {
  const map = { draft: 'gray', scheduled: 'blue', sending: 'orange', sent: 'green', subscribed: 'green', unsubscribed: 'gray', bounced: 'red', pending: 'orange', failed: 'red' };
  return map[s] ?? 'gray';
}
function growthH(v, arr) {
  const max = Math.max(...(arr ?? []).map(m => m.count), 1);
  return Math.round((v / max) * 100) + '%';
}

// ─── Busy / Error ─────────────────────────────────────────────────────────────
const busy       = ref('');
const modalError = ref('');

// ─── Generic Confirm Dialog ───────────────────────────────────────────────────
const confirmDlg = reactive({ show: false, title: 'Confirm', message: '', ok: 'Delete' });
let confirmResolve = null;
function showConfirm(message, title = 'Confirm', ok = 'Delete') {
  return new Promise(res => {
    Object.assign(confirmDlg, { show: true, message, title, ok });
    confirmResolve = res;
  });
}
function resolveConfirm(val) {
  confirmDlg.show = false;
  if (confirmResolve) { confirmResolve(val); confirmResolve = null; }
}

// ═══════════════════════════════════════════════════════════════════════════════
// DASHBOARD
// ═══════════════════════════════════════════════════════════════════════════════
const dash        = ref({ recent_campaigns: [], timeline: [], stats: {} });
const dashLoading = ref(false);

const dashStats = computed(() => {
  const s = dash.value.stats ?? {};
  return [
    { label: 'Total Contacts',  value: s.total_contacts  ?? 0, color: 'blue',   icon: ICO.users },
    { label: 'Active Campaigns', value: s.active_campaigns ?? 0, color: 'green', icon: ICO.mail },
    { label: 'Emails Sent',     value: s.emails_sent     ?? 0, color: 'purple', icon: ICO.send },
    { label: 'Avg Open Rate',   value: pct(s.avg_open_rate ?? 0), color: 'orange', icon: ICO.trending },
  ];
});

async function loadDashboard() {
  dashLoading.value = true;
  try {
    const res = await api.get('/v1/email-analytics/dashboard');
    dash.value = res.data;
  } catch { /* silent */ } finally {
    dashLoading.value = false;
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// CONTACTS
// ═══════════════════════════════════════════════════════════════════════════════
const contacts            = ref([]);
const cLoading            = ref(false);
const cMeta               = ref({ current_page: 1, last_page: 1, total: 0 });
const cFilter             = reactive({ search: '', status: '', tag_id: '' });
const selectedContactIds  = ref([]);
const bulkTagId           = ref('');

const allContactsSelected = computed(() =>
  contacts.value.length > 0 && contacts.value.every(c => selectedContactIds.value.includes(c.id))
);
function toggleSelectAll(e) {
  selectedContactIds.value = e.target.checked ? contacts.value.map(c => c.id) : [];
}

async function loadContacts(page = 1) {
  cLoading.value = true;
  selectedContactIds.value = [];
  try {
    const res = await api.get('/v1/email-contacts', { params: { page, ...cFilter } });
    contacts.value = res.data.data;
    cMeta.value    = res.data.meta ?? { current_page: page, last_page: 1, total: res.data.data.length };
  } catch { /* silent */ } finally {
    cLoading.value = false;
  }
}

async function syncCrm() {
  busy.value = 'sync';
  try {
    const res = await api.post('/v1/email-contacts/sync-crm');
    toast(`Synced ${res.data.synced ?? 0} contact(s) from CRM`);
    loadContacts(1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Sync failed', 'error');
  } finally {
    busy.value = '';
  }
}

async function exportContacts() {
  const token = localStorage.getItem('crm_token');
  const params = new URLSearchParams({ ...cFilter, _token: token });
  window.open(`/api/v1/email-contacts/export?${params}`, '_blank');
}

async function bulkContacts(action) {
  if (!selectedContactIds.value.length) return;
  if (action === 'delete') {
    if (!await showConfirm(`Permanently delete ${selectedContactIds.value.length} contact(s)?`, 'Delete Contacts')) return;
  }
  try {
    await api.post('/v1/email-contacts/bulk', { ids: selectedContactIds.value, action, tag_id: bulkTagId.value || undefined });
    toast('Action applied');
    selectedContactIds.value = [];
    bulkTagId.value = '';
    loadContacts(cMeta.value.current_page);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Action failed', 'error');
  }
}

async function deleteContact(c) {
  if (!await showConfirm(`Remove "${c.email}" from email contacts?`, 'Delete Contact')) return;
  try {
    await api.delete(`/v1/email-contacts/${c.id}`);
    toast('Contact deleted');
    loadContacts(cMeta.value.current_page);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Delete failed', 'error');
  }
}

// Contact Modal
const showContactModal = ref(false);
const contactForm      = reactive({ id: null, full_name: '', email: '', phone: '', company: '', status: 'subscribed' });

function openContactModal(c = null) {
  Object.assign(contactForm, { id: null, full_name: '', email: '', phone: '', company: '', status: 'subscribed' });
  if (c) Object.assign(contactForm, { id: c.id, full_name: c.full_name ?? '', email: c.email, phone: c.phone ?? '', company: c.company ?? '', status: c.status });
  showContactModal.value = true;
}

async function saveContact() {
  busy.value = 'contact';
  try {
    if (contactForm.id) {
      await api.put(`/v1/email-contacts/${contactForm.id}`, contactForm);
    } else {
      await api.post('/v1/email-contacts', contactForm);
    }
    showContactModal.value = false;
    toast('Contact saved');
    loadContacts(cMeta.value.current_page);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Save failed', 'error');
  } finally {
    busy.value = '';
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TAGS
// ═══════════════════════════════════════════════════════════════════════════════
const tags        = ref([]);
const showTagModal = ref(false);
const tagForm     = reactive({ id: null, name: '', color: '#1d4ed8' });

async function loadTags() {
  try { const r = await api.get('/v1/email-tags'); tags.value = r.data.data ?? r.data; } catch { /* silent */ }
}

function openTagManager() { showTagModal.value = true; }
function editTag(t) { Object.assign(tagForm, { id: t.id, name: t.name, color: t.color ?? '#1d4ed8' }); }

async function saveTag() {
  busy.value = 'tag';
  try {
    if (tagForm.id) { await api.put(`/v1/email-tags/${tagForm.id}`, tagForm); }
    else            { await api.post('/v1/email-tags', tagForm); }
    Object.assign(tagForm, { id: null, name: '', color: '#1d4ed8' });
    await loadTags();
    toast('Tag saved');
  } catch (e) {
    toast(e.response?.data?.message ?? 'Save failed', 'error');
  } finally {
    busy.value = '';
  }
}

async function deleteTag(t) {
  if (!await showConfirm(`Delete tag "${t.name}"? This will remove it from all contacts.`, 'Delete Tag')) return;
  try { await api.delete(`/v1/email-tags/${t.id}`); await loadTags(); toast('Tag deleted'); }
  catch (e) { toast(e.response?.data?.message ?? 'Delete failed', 'error'); }
}

// ═══════════════════════════════════════════════════════════════════════════════
// LISTS (Audience Groups)
// ═══════════════════════════════════════════════════════════════════════════════
const groups       = ref([]);
const listsLoading = ref(false);
const listsView    = ref('index');
const activeList   = ref(null);
const listSearch   = ref('');

const filteredLists = computed(() =>
  listSearch.value ? groups.value.filter(g => g.name.toLowerCase().includes(listSearch.value.toLowerCase())) : groups.value
);

async function loadGroups() {
  listsLoading.value = true;
  try { const r = await api.get('/v1/email-groups'); groups.value = r.data.data ?? r.data; }
  catch { /* silent */ } finally { listsLoading.value = false; }
}

async function openListDetail(list) {
  activeList.value = list;
  listsView.value  = 'detail';
  loadListMembers(1);
}

const listMembers       = ref([]);
const listMembersLoading = ref(false);
const listMembersMeta   = ref({ current_page: 1, last_page: 1 });

async function loadListMembers(page = 1) {
  if (!activeList.value) return;
  listMembersLoading.value = true;
  try {
    const r = await api.get(`/v1/email-groups/${activeList.value.id}/members`, { params: { page } });
    listMembers.value     = r.data.data;
    listMembersMeta.value = r.data.meta ?? { current_page: page, last_page: 1 };
  } catch { /* silent */ } finally { listMembersLoading.value = false; }
}

async function deleteList(list) {
  if (list.is_system) return;
  if (!await showConfirm(`Delete list "${list.name}"? The contacts themselves will not be deleted.`, 'Delete List')) return;
  try {
    await api.delete(`/v1/email-groups/${list.id}`);
    toast('List deleted');
    loadGroups();
  } catch (e) { toast(e.response?.data?.message ?? 'Delete failed', 'error'); }
}

// List Modal
const showListModal = ref(false);
const listForm      = reactive({ id: null, name: '', description: '', max_contacts: 200 });

function openListModal(list = null) {
  Object.assign(listForm, { id: null, name: '', description: '', max_contacts: 200 });
  if (list) Object.assign(listForm, { id: list.id, name: list.name, description: list.description ?? '', max_contacts: list.max_contacts ?? 200 });
  showListModal.value = true;
}

async function saveList() {
  busy.value = 'list';
  try {
    if (listForm.id) { await api.put(`/v1/email-groups/${listForm.id}`, listForm); }
    else             { await api.post('/v1/email-groups', listForm); }
    showListModal.value = false;
    toast('List saved');
    loadGroups();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Save failed', 'error');
  } finally { busy.value = ''; }
}

// ─── Import Wizard ────────────────────────────────────────────────────────────
const importStep     = ref('method');
const importMethod   = ref('paste');
const importPasteText = ref('');
const importFile     = ref(null);
const importDragOver = ref(false);
const importPreviewRows = ref([]);
const importResult   = ref({ imported: 0, skipped: 0 });

const importSteps = [
  { id: 'method',  label: 'Method'  },
  { id: 'input',   label: 'Input'   },
  { id: 'preview', label: 'Preview' },
  { id: 'result',  label: 'Done'    },
];
const importStepIdx   = computed(() => importSteps.findIndex(s => s.id === importStep.value));
const importValidCount   = computed(() => importPreviewRows.value.filter(r => r.valid).length);
const importInvalidCount = computed(() => importPreviewRows.value.filter(r => !r.valid).length);

function onImportFileDrop(e) {
  importDragOver.value = false;
  importFile.value = e.dataTransfer.files[0] ?? null;
}
function onImportFileSelect(e) { importFile.value = e.target.files[0] ?? null; }

function parseImport() {
  importPreviewRows.value = [];
  if (importMethod.value === 'paste') {
    const lines = importPasteText.value.split('\n').map(l => l.trim()).filter(Boolean);
    importPreviewRows.value = lines.map(email => {
      const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      return { email, name: '', valid, error: valid ? '' : 'Invalid email' };
    });
  } else if (importFile.value) {
    const reader = new FileReader();
    reader.onload = e => {
      const lines = e.target.result.split('\n').slice(1); // skip header
      importPreviewRows.value = lines.filter(l => l.trim()).map(line => {
        const cols = line.split(',').map(c => c.replace(/"/g, '').trim());
        const email = cols[0] ?? '';
        const name  = cols[1] ?? '';
        const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        return { email, name, valid, error: valid ? '' : 'Invalid email' };
      });
      importStep.value = 'preview';
    };
    reader.readAsText(importFile.value);
    return;
  }
  importStep.value = 'preview';
}

async function doImport() {
  if (!activeList.value) return;
  busy.value = 'import';
  try {
    const rows = importPreviewRows.value.filter(r => r.valid).map(r => ({ email: r.email, full_name: r.name }));
    const res  = await api.post('/v1/email-contacts/import', { rows, group_id: activeList.value.id });
    importResult.value = { imported: res.data.imported ?? rows.length, skipped: res.data.skipped ?? 0 };
    importStep.value = 'result';
    toast(`Imported ${importResult.value.imported} contact(s)`);
    loadListMembers(1);
    loadGroups();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Import failed', 'error');
  } finally { busy.value = ''; }
}

function resetImport() {
  importStep.value = 'method';
  importMethod.value = 'paste';
  importPasteText.value = '';
  importFile.value = null;
  importPreviewRows.value = [];
}

// ═══════════════════════════════════════════════════════════════════════════════
// CAMPAIGNS
// ═══════════════════════════════════════════════════════════════════════════════
const campaigns        = ref([]);
const campLoading      = ref(false);
const campSearch       = ref('');
const campStatusFilter = ref('');

const filteredCampaigns = computed(() => {
  let list = campaigns.value;
  if (campSearch.value)       list = list.filter(c => c.name.toLowerCase().includes(campSearch.value.toLowerCase()));
  if (campStatusFilter.value) list = list.filter(c => c.status === campStatusFilter.value);
  return list;
});

async function loadCampaigns() {
  campLoading.value = true;
  try { const r = await api.get('/v1/email-campaigns'); campaigns.value = r.data.data ?? r.data; }
  catch { /* silent */ } finally { campLoading.value = false; }
}

async function duplicateCampaign(c) {
  try {
    const r = await api.post(`/v1/email-campaigns/${c.id}/duplicate`);
    campaigns.value.unshift(r.data.data);
    toast('Campaign duplicated');
  } catch (e) { toast(e.response?.data?.message ?? 'Duplicate failed', 'error'); }
}

async function deleteCampaign(c) {
  if (!await showConfirm(`Delete campaign "${c.name}"? This cannot be undone.`, 'Delete Campaign')) return;
  try {
    await api.delete(`/v1/email-campaigns/${c.id}`);
    campaigns.value = campaigns.value.filter(x => x.id !== c.id);
    toast('Campaign deleted');
  } catch (e) { toast(e.response?.data?.message ?? 'Delete failed', 'error'); }
}

// Campaign Modal
const showCampaignModal = ref(false);
const campaignForm      = reactive({ id: null, name: '', subject: '', preview_text: '', sender_name: '', sender_email: '', audience_group_id: null, body: '' });

function openCampaignModal(c = null) {
  modalError.value = '';
  Object.assign(campaignForm, { id: null, name: '', subject: '', preview_text: '', sender_name: '', sender_email: '', audience_group_id: null, body: '' });
  if (c) Object.assign(campaignForm, { id: c.id, name: c.name, subject: c.subject ?? '', preview_text: c.preview_text ?? '', sender_name: c.sender_name ?? '', sender_email: c.sender_email ?? '', audience_group_id: c.audience_group_id ?? null, body: c.body ?? '' });
  showCampaignModal.value = true;
}

async function saveCampaign() {
  busy.value = 'campaign';
  modalError.value = '';
  try {
    if (campaignForm.id) {
      const r = await api.put(`/v1/email-campaigns/${campaignForm.id}`, campaignForm);
      const idx = campaigns.value.findIndex(c => c.id === campaignForm.id);
      if (idx >= 0) campaigns.value[idx] = r.data.data;
    } else {
      const r = await api.post('/v1/email-campaigns', campaignForm);
      campaigns.value.unshift(r.data.data);
    }
    showCampaignModal.value = false;
    toast('Campaign saved');
  } catch (e) {
    modalError.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(' ') ?? 'Save failed';
  } finally { busy.value = ''; }
}

// Send / Schedule Modal
const showSendModal = ref(false);
const sendTarget    = ref(null);
const sendMode      = ref('now');
const scheduledAt   = ref('');
const testEmailTo   = ref('');

function openSendModal(c) {
  sendTarget.value  = c;
  sendMode.value    = 'now';
  scheduledAt.value = '';
  modalError.value  = '';
  showSendModal.value = true;
}

async function sendTestEmail() {
  if (!testEmailTo.value || !sendTarget.value) return;
  busy.value = 'test';
  try {
    await api.post(`/v1/email-campaigns/${sendTarget.value.id}/send-test`, { email: testEmailTo.value });
    toast(`Test sent to ${testEmailTo.value}`);
  } catch (e) { toast(e.response?.data?.message ?? 'Test failed', 'error'); }
  finally { busy.value = ''; }
}

async function doSend() {
  if (!sendTarget.value) return;
  busy.value = 'send';
  modalError.value = '';
  try {
    if (sendMode.value === 'now') {
      const r = await api.post(`/v1/email-campaigns/${sendTarget.value.id}/send`);
      toast(r.data.message ?? 'Campaign sent');
    } else {
      if (!scheduledAt.value) { modalError.value = 'Please pick a date and time.'; busy.value = ''; return; }
      await api.post(`/v1/email-campaigns/${sendTarget.value.id}/schedule`, { scheduled_at: scheduledAt.value });
      toast('Campaign scheduled');
    }
    showSendModal.value = false;
    loadCampaigns();
  } catch (e) {
    modalError.value = e.response?.data?.message ?? 'Send failed';
  } finally { busy.value = ''; }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEMPLATES
// ═══════════════════════════════════════════════════════════════════════════════
const templates        = ref([]);
const templatesLoading = ref(false);

async function loadTemplates() {
  templatesLoading.value = true;
  try { const r = await api.get('/v1/email-templates'); templates.value = r.data.data ?? r.data; }
  catch { /* silent */ } finally { templatesLoading.value = false; }
}

async function duplicateTemplate(t) {
  const r = await api.post('/v1/email-templates', { name: t.name + ' (copy)', category: t.category, subject: t.subject, body: t.body });
  templates.value.push(r.data.data);
  toast('Template duplicated');
}

async function deleteTemplate(t) {
  if (!await showConfirm(`Delete template "${t.name}"?`, 'Delete Template')) return;
  try {
    await api.delete(`/v1/email-templates/${t.id}`);
    templates.value = templates.value.filter(x => x.id !== t.id);
    toast('Template deleted');
  } catch (e) { toast(e.response?.data?.message ?? 'Delete failed', 'error'); }
}

// Template Modal
const showTemplateModal = ref(false);
const templateForm      = reactive({ id: null, name: '', category: '', subject: '', body: '' });

function openTemplateModal(t = null) {
  Object.assign(templateForm, { id: null, name: '', category: '', subject: '', body: '' });
  if (t) Object.assign(templateForm, { id: t.id, name: t.name, category: t.category ?? '', subject: t.subject ?? '', body: t.body ?? '' });
  showTemplateModal.value = true;
}

async function saveTemplate() {
  busy.value = 'template';
  try {
    if (templateForm.id) {
      const r = await api.put(`/v1/email-templates/${templateForm.id}`, templateForm);
      const idx = templates.value.findIndex(t => t.id === templateForm.id);
      if (idx >= 0) templates.value[idx] = r.data.data;
    } else {
      const r = await api.post('/v1/email-templates', templateForm);
      templates.value.push(r.data.data);
    }
    showTemplateModal.value = false;
    toast('Template saved');
  } catch (e) { toast(e.response?.data?.message ?? 'Save failed', 'error'); }
  finally { busy.value = ''; }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ANALYTICS
// ═══════════════════════════════════════════════════════════════════════════════
const analytics        = ref({ trend: [], audience_growth: [], most_active_contacts: [] });
const analyticsLoading = ref(false);

async function loadAnalytics() {
  analyticsLoading.value = true;
  try { const r = await api.get('/v1/email-analytics'); analytics.value = r.data; }
  catch { /* silent */ } finally { analyticsLoading.value = false; }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════════════════════════════════════════════
const settingsLoading = ref(false);
const settingsForm    = reactive({
  smtp_host: '', smtp_port: 587, smtp_username: '', smtp_password: '', smtp_encryption: 'tls',
  from_name: '', from_email: '', reply_to: '', company_name: '', company_address: '', email_footer: '',
  tracking_enabled: true,
});

const PRESETS = {
  gmail:     { smtp_host: 'smtp.gmail.com',       smtp_port: 587, smtp_encryption: 'tls' },
  outlook:   { smtp_host: 'smtp.office365.com',   smtp_port: 587, smtp_encryption: 'tls' },
  sendgrid:  { smtp_host: 'smtp.sendgrid.net',    smtp_port: 587, smtp_encryption: 'tls' },
  mailgun:   { smtp_host: 'smtp.mailgun.org',      smtp_port: 587, smtp_encryption: 'tls' },
};

function applyPreset(key) {
  if (PRESETS[key]) Object.assign(settingsForm, PRESETS[key]);
}

async function loadSettings() {
  settingsLoading.value = true;
  try {
    const r = await api.get('/v1/email-settings');
    Object.assign(settingsForm, r.data);
  } catch { /* silent */ } finally { settingsLoading.value = false; }
}

async function saveSettings() {
  busy.value = 'settings';
  try {
    await api.put('/v1/email-settings', settingsForm);
    toast('Settings saved');
  } catch (e) { toast(e.response?.data?.message ?? 'Save failed', 'error'); }
  finally { busy.value = ''; }
}

async function testSmtp() {
  if (!testEmailTo.value) { toast('Enter an email address to send the test to', 'error'); return; }
  busy.value = 'test';
  try {
    await api.post('/v1/email-settings/test', { email: testEmailTo.value });
    toast(`Test email sent to ${testEmailTo.value}`);
  } catch (e) { toast(e.response?.data?.message ?? 'Test failed', 'error'); }
  finally { busy.value = ''; }
}

// ─── Mount ───────────────────────────────────────────────────────────────────
onMounted(() => {
  loaded.dashboard = true;
  loaded.lists     = true; // groups pre-fetched so they're available in the campaign modal audience dropdown
  loadDashboard();
  loadTags();
  loadGroups();
});
</script>

<style scoped>
.page { padding: 28px 32px; }

/* ── Header ── */
.page-header-row { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 28px; font-weight: 800; color: var(--text-1); margin: 0 0 4px; letter-spacing: -0.5px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
.page-header-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

/* ── Tabs ── */
.tab-bar { display: flex; gap: 4px; border-bottom: 2px solid var(--border); margin-bottom: 24px; overflow-x: auto; }
.tab-btn {
  display: flex; align-items: center; gap: 6px; padding: 9px 14px; border: none; background: none;
  font-size: 13px; font-weight: 600; color: var(--text-2); cursor: pointer; white-space: nowrap;
  border-bottom: 2px solid transparent; margin-bottom: -2px; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  transition: color 0.15s, border-color 0.15s;
}
.tab-btn:hover { color: var(--text-1); }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-icon { display: flex; opacity: 0.75; }
.tab-btn.active .tab-icon { opacity: 1; }

/* ── Section wrapper ── */
.tab-section { min-height: 300px; }
.tab-section[style*="display: none"] { min-height: 0; }
.view-loading { display: flex; justify-content: center; padding: 60px 0; }
.spinner { width: 32px; height: 32px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Stat grid ── */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-grid.sm { grid-template-columns: repeat(3, 1fr); margin-bottom: 16px; }
.stat-card {
  background: var(--surface); border-radius: var(--radius); box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  padding: 16px 18px; display: flex; align-items: center; gap: 14px;
  border-left: 3px solid var(--border);
}
.stat-card.accent-blue   { border-left-color: var(--primary); }
.stat-card.accent-green  { border-left-color: #10b981; }
.stat-card.accent-purple { border-left-color: #8b5cf6; }
.stat-card.accent-orange { border-left-color: #f59e0b; }
.stat-card.accent-red    { border-left-color: #ef4444; }
.stat-card.accent-gray   { border-left-color: var(--text-3); }
.stat-icon-wrap { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.icon-blue   { background: rgba(29,78,216,0.1); color: var(--primary); }
.icon-green  { background: rgba(16,185,129,0.1); color: #10b981; }
.icon-purple { background: rgba(139,92,246,0.1); color: #8b5cf6; }
.icon-orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-val { font-size: 22px; font-weight: 800; color: var(--text-1); line-height: 1.1; }
.stat-lbl { font-size: 11px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.4px; margin-top: 2px; }

/* ── Dashboard grid ── */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.card { background: var(--surface); border-radius: var(--radius); box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 18px 20px; }
.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.card-title { font-size: 13px; font-weight: 700; color: var(--text-1); text-transform: uppercase; letter-spacing: 0.4px; }
.btn-link { background: none; border: none; font-size: 12px; color: var(--primary); cursor: pointer; font-weight: 600; }
.btn-link:hover { text-decoration: underline; }

/* ── Timeline ── */
.timeline-list { display: flex; flex-direction: column; gap: 10px; }
.tl-row { display: flex; align-items: flex-start; gap: 10px; }
.tl-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; background: var(--text-3); }
.tl-green  { background: #10b981; }
.tl-blue   { background: var(--primary); }
.tl-orange { background: #f59e0b; }
.tl-gray   { background: var(--text-3); }
.tl-name { font-size: 13px; font-weight: 600; color: var(--text-1); }
.tl-meta { font-size: 11px; color: var(--text-3); }

/* ── Filter bar ── */
.filter-bar { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; flex-wrap: wrap; }
.filter-search { flex: 1; min-width: 180px; }
.filter-actions { display: flex; gap: 6px; margin-left: auto; }

/* ── Table ── */
.table-wrap { border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead th { background: var(--app-bg); color: var(--text-2); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); }
.data-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: var(--app-bg); }
.data-table tbody tr.table-row { cursor: pointer; }
.data-table td { padding: 10px 14px; color: var(--text-1); vertical-align: middle; }
.data-table td strong { font-weight: 600; }
.empty-cell { text-align: center; color: var(--text-3); padding: 32px 14px !important; font-size: 13px; }
.col-check { width: 36px; padding: 0 8px 0 14px !important; }
.actions-cell { width: 1%; white-space: nowrap; }
.row-invalid { background: rgba(239,68,68,0.04); }

/* ── Badges ── */
.badge { display: inline-flex; align-items: center; height: 20px; padding: 0 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.badge-green  { background: rgba(16,185,129,0.12); color: #059669; }
.badge-blue   { background: rgba(29,78,216,0.1);  color: var(--primary); }
.badge-gray   { background: var(--app-bg); color: var(--text-2); }
.badge-orange { background: rgba(245,158,11,0.12); color: #b45309; }
.badge-red    { background: rgba(239,68,68,0.1);  color: #dc2626; }
.badge-purple { background: rgba(139,92,246,0.1); color: #7c3aed; }

/* ── Tags ── */
.tag-pill { display: inline-flex; align-items: center; height: 20px; padding: 0 8px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-right: 4px; }

/* ── Bulk bar ── */
.bulk-bar { display: flex; align-items: center; gap: 10px; background: var(--primary-soft); border-radius: var(--radius-sm); padding: 8px 14px; margin-bottom: 10px; flex-wrap: wrap; font-size: 13px; }
.bulk-count { font-weight: 700; color: var(--primary-text); }

/* ── Buttons ── */
.btn-primary { display: flex; align-items: center; gap: 6px; height: 38px; padding: 0 16px; background: var(--primary); color: white; border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary.sm { height: 30px; padding: 0 12px; font-size: 12px; }
.btn-ghost { display: flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px; background: var(--surface); color: var(--text-1); border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-ghost:hover:not(:disabled) { background: var(--app-bg); }
.btn-ghost:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-ghost.sm { height: 30px; padding: 0 10px; font-size: 12px; }
.btn-icon { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: none; border: none; color: var(--text-2); border-radius: var(--radius-sm); cursor: pointer; }
.btn-icon:hover { background: var(--app-bg); color: var(--text-1); }
.btn-icon.danger:hover { color: #dc2626; background: rgba(239,68,68,0.08); }
.btn-back { display: flex; align-items: center; gap: 4px; background: none; border: none; color: var(--text-2); font-size: 13px; font-weight: 600; cursor: pointer; padding: 0; }
.btn-back:hover { color: var(--primary); }

/* ── Field inputs ── */
.field-input { height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); background: var(--surface); outline: none; box-sizing: border-box; width: 100%; }
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.12); }
.field-input.sm { height: 30px; font-size: 12px; width: auto; min-width: 120px; }
textarea.field-input { height: auto; padding: 10px 12px; resize: vertical; }
.textarea-lg { height: 140px; }

/* ── Pagination ── */
.pagination-bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; font-size: 13px; color: var(--text-2); }
.page-btns { display: flex; gap: 6px; align-items: center; }
.page-num { font-size: 12px; color: var(--text-2); }
.page-info { font-size: 12px; }

/* ── Capacity ── */
.capacity-row { display: flex; align-items: center; gap: 8px; }
.capacity-track { width: 60px; height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; }
.capacity-fill { height: 100%; background: var(--primary); border-radius: 3px; transition: width 0.3s; }
.capacity-fill.full { background: #ef4444; }
.capacity-bar-wrap { margin-bottom: 16px; }
.capacity-bar-track { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
.capacity-bar-fill { height: 100%; background: var(--primary); border-radius: 4px; transition: width 0.3s; }
.capacity-bar-fill.full { background: #ef4444; }
.capacity-bar-labels { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-2); }
.back-nav { margin-bottom: 12px; }
.section-title { font-size: 20px; font-weight: 700; color: var(--text-1); margin: 0 0 4px; }

/* ── Template grid ── */
.template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.template-card { background: var(--surface); border-radius: var(--radius); box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 18px; display: flex; flex-direction: column; gap: 6px; }
.template-name { font-size: 14px; font-weight: 700; color: var(--text-1); }
.template-actions { display: flex; gap: 6px; margin-top: 8px; }

/* ── Analytics ── */
.bar-list { display: flex; flex-direction: column; gap: 10px; }
.bar-row { display: flex; align-items: center; gap: 8px; }
.bar-name { min-width: 120px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-track { flex: 1; height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
.bar-fill { height: 100%; background: var(--primary); border-radius: 4px; }
.bar-val { font-size: 12px; color: var(--text-2); min-width: 60px; text-align: right; }
.chart-cols { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding: 0 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.chart-bar-wrap { flex: 1; display: flex; align-items: flex-end; width: 100%; }
.chart-bar { width: 100%; background: var(--primary); border-radius: 3px 3px 0 0; transition: height 0.3s; min-height: 2px; }
.chart-lbl { font-size: 10px; color: var(--text-3); }

/* ── Settings ── */
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.settings-actions { grid-column: 1 / -1; display: flex; gap: 10px; align-items: center; justify-content: flex-end; padding-top: 4px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.4px; }
.toggle-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--text-1); cursor: pointer; }
.color-picker { width: 38px; height: 38px; padding: 2px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; }

/* ── Import wizard ── */
.step-bar { display: flex; align-items: center; gap: 0; margin-bottom: 24px; }
.step-item { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--text-3); }
.step-item.active { color: var(--primary); }
.step-item.done { color: #10b981; }
.step-dot { width: 22px; height: 22px; border-radius: 50%; background: var(--border); color: var(--text-2); font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.step-item.active .step-dot { background: var(--primary); color: white; }
.step-item.done .step-dot { background: #10b981; color: white; }
.method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.method-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 24px 20px; text-align: center; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.method-card:hover { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(29,78,216,0.1); }
.method-ico { color: var(--primary); opacity: 0.8; }
.method-card strong { font-size: 14px; font-weight: 700; color: var(--text-1); }
.method-card p { font-size: 13px; color: var(--text-2); margin: 0; }
.dropzone { border: 2px dashed var(--border); border-radius: var(--radius); padding: 32px; text-align: center; cursor: pointer; transition: border-color 0.15s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.dropzone:hover, .dropzone.dz-over { border-color: var(--primary); background: rgba(29,78,216,0.04); }
.dz-ico { color: var(--text-3); }
.import-nav { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
.import-result { text-align: center; padding: 48px 24px; }
.result-ico { display: flex; justify-content: center; color: #10b981; margin-bottom: 12px; }
.import-result h3 { font-size: 20px; font-weight: 700; color: var(--text-1); margin: 0 0 8px; }

/* ── Send options ── */
.send-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
.send-opt { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 16px; text-align: left; cursor: pointer; transition: border-color 0.15s; display: flex; flex-direction: column; gap: 4px; }
.send-opt:hover, .send-opt.active { border-color: var(--primary); background: rgba(29,78,216,0.04); }
.send-opt-ico { color: var(--primary); margin-bottom: 4px; }
.send-opt strong { font-size: 14px; font-weight: 700; color: var(--text-1); }
.send-opt p { font-size: 12px; color: var(--text-2); margin: 0; }

/* ── Tag manager ── */
.tag-form { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
.tag-list { display: flex; flex-direction: column; gap: 6px; }
.tag-row { display: flex; align-items: center; justify-content: space-between; }
.tag-row-actions { display: flex; gap: 4px; }

/* ── Alert ── */
.alert { padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 14px; }
.alert-danger { background: rgba(239,68,68,0.1); color: #dc2626; }

/* ── Modal ── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--surface); border-radius: var(--radius-lg); box-shadow: 0 24px 64px rgba(0,0,0,0.18); width: 100%; max-width: 520px; max-height: 90vh; display: flex; flex-direction: column; }
.modal.modal-lg { max-width: 700px; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px 14px; border-bottom: 1px solid var(--border); }
.modal-header h3 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0; }
.modal-body { padding: 18px 20px; overflow-y: auto; flex: 1; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; padding: 14px 20px; border-top: 1px solid var(--border); }

/* ── Misc ── */
.text-muted  { color: var(--text-3); }
.text-danger { color: #dc2626; }
.text-muted.sm, .sm { font-size: 11px; }
.req { color: #ef4444; }
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-3); }
.empty-icon { display: flex; justify-content: center; margin-bottom: 12px; }
.empty-state p { font-size: 14px; color: var(--text-2); }

/* ── Toast ── */
.toast-area { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast { background: var(--text-1); color: var(--surface); padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,0.2); max-width: 320px; }
.toast-error   { background: #dc2626; }
.toast-success { background: #059669; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-grid { grid-template-columns: 1fr; }
  .settings-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .page { padding: 16px 14px; }
  .stat-grid { grid-template-columns: 1fr 1fr; }
  .stat-grid.sm { grid-template-columns: 1fr 1fr; }
  .method-grid { grid-template-columns: 1fr; }
  .send-options { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
}
</style>
