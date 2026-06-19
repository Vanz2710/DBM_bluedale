<template>
  <div class="em-root" :data-theme="theme">
    <!-- TOP BAR -->
    <header class="em-topbar">
      <div class="em-brand">
        <span class="em-logo">EM</span>
        <div>
          <strong>Email Marketing</strong>
          <small>Campaigns, audiences, automation &amp; analytics</small>
        </div>
      </div>
      <div class="em-topbar-actions">
        <div class="em-search">
          <input v-model="globalSearch" type="text" placeholder="Search contacts, campaigns..."
                 @keyup.enter="runGlobalSearch">
        </div>
        <button class="em-icon-btn" :title="theme === 'dark' ? 'Switch to light' : 'Switch to dark'" @click="toggleTheme">
          {{ theme === 'dark' ? '☀' : '☾' }}
        </button>
        <button class="em-btn-primary" @click="startNewCampaign">+ Create Campaign</button>
      </div>
    </header>

    <div class="em-shell">
      <!-- SUB-SIDEBAR -->
      <nav class="em-sidebar">
        <button
          v-for="item in nav"
          :key="item.id"
          type="button"
          class="em-nav-link"
          :class="{ active: section === item.id }"
          @click="goSection(item.id)"
        >
          <span class="em-nav-ico">{{ item.icon }}</span>
          <span class="em-nav-label">{{ item.label }}</span>
        </button>
      </nav>

      <!-- CONTENT -->
      <main class="em-content">
        <div v-if="flash" class="em-flash" :class="flash.type">{{ flash.text }}</div>

        <!-- ============ DASHBOARD ============ -->
        <section v-if="section === 'dashboard'" class="em-space">
          <div class="em-page-head">
            <h1>Dashboard</h1>
            <p>An overview of your contacts and campaign performance.</p>
          </div>

          <div class="em-stat-grid">
            <div v-for="stat in statCards" :key="stat.label" class="em-stat">
              <span class="em-stat-label">{{ stat.label }}</span>
              <strong class="em-stat-value">{{ stat.value }}</strong>
              <small>{{ stat.detail }}</small>
            </div>
          </div>

          <div class="em-grid-2">
            <div class="em-card">
              <div class="em-card-head"><h2>Email Performance (14 days)</h2></div>
              <div v-if="dash" class="em-chart">
                <div v-for="d in dash.performance" :key="d.date" class="em-chart-col" :title="`${d.date}: ${d.sent} sent, ${d.opened} opened, ${d.clicked} clicked`">
                  <div class="em-bars">
                    <span class="b sent" :style="{ height: barH(d.sent) }"></span>
                    <span class="b open" :style="{ height: barH(d.opened) }"></span>
                    <span class="b click" :style="{ height: barH(d.clicked) }"></span>
                  </div>
                  <em>{{ d.date.slice(8) }}</em>
                </div>
              </div>
              <div class="em-legend">
                <span><i class="dot sent"></i>Sent</span>
                <span><i class="dot open"></i>Opened</span>
                <span><i class="dot click"></i>Clicked</span>
              </div>
            </div>

            <div class="em-card">
              <div class="em-card-head"><h2>Activity Timeline</h2></div>
              <div class="em-timeline">
                <div v-for="t in dash?.timeline ?? []" :key="t.id" class="em-tl-row">
                  <span class="em-status-dot" :class="t.status"></span>
                  <div>
                    <strong>{{ t.name }}</strong>
                    <small>{{ ucfirst(t.status) }} · {{ t.count }} recipients · {{ timeAgo(t.at) }}</small>
                  </div>
                </div>
                <p v-if="!dash?.timeline?.length" class="em-empty">No activity yet.</p>
              </div>
            </div>
          </div>

          <div class="em-grid-2">
            <div class="em-card">
              <div class="em-card-head">
                <h2>Recent Campaigns</h2>
                <button class="em-link" @click="goSection('campaigns')">View all</button>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead><tr><th>Name</th><th>Status</th><th>Sent</th><th>Open</th><th>Click</th></tr></thead>
                  <tbody>
                    <tr v-for="c in dash?.recent_campaigns ?? []" :key="c.id">
                      <td><strong>{{ c.name }}</strong><small>{{ c.audience ?? '—' }}</small></td>
                      <td><span class="em-badge" :class="c.status">{{ ucfirst(c.status) }}</span></td>
                      <td>{{ c.sent_count }}</td>
                      <td>{{ pct(c.open_rate) }}</td>
                      <td>{{ pct(c.click_rate) }}</td>
                    </tr>
                    <tr v-if="!dash?.recent_campaigns?.length"><td colspan="5" class="em-empty">No campaigns yet.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="em-card">
              <div class="em-card-head"><h2>Top Performing</h2></div>
              <div class="em-bar-list">
                <div v-for="c in dash?.top_campaigns ?? []" :key="c.name">
                  <span class="em-bar-name">{{ c.name }}</span>
                  <div class="em-bar-track"><span :style="{ width: (c.open_rate || 0) + '%' }"></span></div>
                  <em>{{ pct(c.open_rate) }}</em>
                </div>
                <p v-if="!dash?.top_campaigns?.length" class="em-empty">No sent campaigns yet.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- ============ CONTACTS ============ -->
        <section v-else-if="section === 'contacts'" class="em-space">
          <div class="em-page-head">
            <h1>Contacts</h1>
            <p>{{ contactMeta.total ?? 0 }} contacts in your email book.</p>
          </div>

          <div class="em-card">
            <div class="em-toolbar">
              <input v-model="contactFilters.search" class="em-input" type="text" placeholder="Search name, email, company..." @keyup.enter="loadContacts(1)">
              <select v-model="contactFilters.status" class="em-input" @change="loadContacts(1)">
                <option value="">All status</option>
                <option value="subscribed">Subscribed</option>
                <option value="unsubscribed">Unsubscribed</option>
                <option value="bounced">Bounced</option>
                <option value="pending">Pending</option>
              </select>
              <select v-model="contactFilters.tag_id" class="em-input" @change="loadContacts(1)">
                <option value="">All tags</option>
                <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <div class="em-toolbar-spacer"></div>
              <button class="em-btn-ghost" @click="openTagManager">Tags</button>
              <button class="em-btn-ghost" @click="syncFromCrm" :disabled="busy === 'sync'">{{ busy === 'sync' ? 'Syncing...' : 'Sync CRM' }}</button>
              <button class="em-btn-ghost" @click="exportContacts">Export</button>
              <button class="em-btn-primary" @click="openContactModal()">+ Contact</button>
            </div>

            <div v-if="selectedContactIds.length" class="em-bulkbar">
              <span>{{ selectedContactIds.length }} selected</span>
              <button class="em-link" @click="bulkContacts('subscribe')">Subscribe</button>
              <button class="em-link" @click="bulkContacts('unsubscribe')">Unsubscribe</button>
              <select v-model="bulkTagId" class="em-input sm">
                <option value="">Add tag…</option>
                <option v-for="t in tags" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <button class="em-link" :disabled="!bulkTagId" @click="bulkContacts('add_tag')">Apply tag</button>
              <select v-model="bulkGroupId" class="em-input sm">
                <option value="">Add to group…</option>
                <option v-for="g in staticGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
              <button class="em-link" :disabled="!bulkGroupId" @click="bulkContacts('add_to_group')">Add</button>
              <button class="em-link danger" @click="bulkContacts('delete')">Delete</button>
            </div>

            <div class="em-table-wrap">
              <table class="em-table">
                <thead>
                  <tr>
                    <th class="em-check"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll"></th>
                    <th>Name</th><th>Email</th><th>Company</th><th>Tags</th><th>Status</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="contactsLoading"><td colspan="7" class="em-empty">Loading...</td></tr>
                  <tr v-else-if="!contacts.length"><td colspan="7" class="em-empty">No contacts found. Use “Sync CRM” or “Import CSV” to add some.</td></tr>
                  <tr v-for="c in contacts" :key="c.id">
                    <td class="em-check"><input type="checkbox" :value="c.id" v-model="selectedContactIds"></td>
                    <td><strong>{{ c.full_name ?? '—' }}</strong></td>
                    <td>{{ c.email }}</td>
                    <td>{{ c.company ?? '—' }}</td>
                    <td>
                      <span v-for="t in c.tags" :key="t.id" class="em-tag" :style="tagStyle(t)">{{ t.name }}</span>
                      <span v-if="!c.tags.length" class="em-muted">—</span>
                    </td>
                    <td><span class="em-badge" :class="c.status">{{ ucfirst(c.status) }}</span></td>
                    <td class="em-row-actions">
                      <button class="em-link" @click="openContactModal(c)">Edit</button>
                      <button class="em-link danger" @click="deleteContact(c)">Delete</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="contactMeta.last_page > 1" class="em-pagination">
              <button class="em-btn-ghost" :disabled="contactMeta.current_page <= 1" @click="loadContacts(contactMeta.current_page - 1)">Prev</button>
              <span>Page {{ contactMeta.current_page }} / {{ contactMeta.last_page }}</span>
              <button class="em-btn-ghost" :disabled="contactMeta.current_page >= contactMeta.last_page" @click="loadContacts(contactMeta.current_page + 1)">Next</button>
            </div>
          </div>
        </section>

        <!-- ============ LISTS ============ -->
        <section v-else-if="section === 'lists'" class="em-space">

          <!-- INDEX: all lists -->
          <template v-if="listsView === 'index'">
            <div class="em-page-head">
              <div>
                <h1>Lists</h1>
                <p>Organize your contacts into lists. Each list holds up to {{ listForm.max_contacts || 200 }} contacts by default.</p>
              </div>
              <button class="em-btn-primary" @click="openListModal()">+ Create a list</button>
            </div>
            <div class="em-card">
              <div class="em-toolbar">
                <input v-model="listSearch" class="em-input" type="text" placeholder="Search a list name or ID...">
                <div class="em-toolbar-spacer"></div>
                <span class="em-muted sm">{{ filteredLists.length }} list(s)</span>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead>
                    <tr>
                      <th>Lists</th><th>ID</th><th>Contacts</th><th>Creation date</th><th>Last updated</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="listsLoading"><td colspan="6" class="em-empty">Loading...</td></tr>
                    <tr v-else-if="!filteredLists.length">
                      <td colspan="6" class="em-empty">No lists yet. Click "+ Create a list" to get started.</td>
                    </tr>
                    <tr v-for="list in filteredLists" :key="list.id" class="em-list-row">
                      <td>
                        <button class="em-link em-list-name" @click="openListDetail(list)">{{ list.name }}</button>
                        <small class="em-muted" style="display:block" v-if="list.description">{{ list.description }}</small>
                      </td>
                      <td class="em-muted">#{{ list.id }}</td>
                      <td>
                        <div class="em-capacity">
                          <div class="em-capacity-track">
                            <div class="em-capacity-fill" :class="{ full: list.is_full }"
                                 :style="{ width: Math.min(100, Math.round((list.count / list.max_contacts) * 100)) + '%' }"></div>
                          </div>
                          <span :class="{ 'em-danger': list.is_full }">
                            {{ list.count }} / {{ list.max_contacts }}
                            <span v-if="list.is_full" class="em-badge failed" style="margin-left:4px">Full</span>
                          </span>
                        </div>
                      </td>
                      <td class="em-muted">{{ formatDate(list.created_at) }}</td>
                      <td class="em-muted">{{ formatDate(list.updated_at) }}</td>
                      <td class="em-row-actions">
                        <button class="em-link" @click="openListDetail(list)">Open</button>
                        <button class="em-link" @click="openListModal(list)">Edit</button>
                        <button class="em-link danger" :disabled="list.is_system" @click="deleteList(list)">Delete</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>

          <!-- DETAIL: single list -->
          <template v-else-if="listsView === 'detail' && activeList">
            <div class="em-page-head">
              <div>
                <button class="em-back-btn" @click="backToLists">← All Lists</button>
                <h1>{{ activeList.name }}</h1>
                <p class="em-muted" v-if="activeList.description">{{ activeList.description }}</p>
              </div>
              <button class="em-btn-primary" :disabled="activeList.is_full" @click="openListImport"
                      :title="activeList.is_full ? 'List is at maximum capacity' : ''">
                {{ activeList.is_full ? 'List Full' : '⬆ Import Contacts' }}
              </button>
            </div>

            <div v-if="activeList.is_full" class="em-flash error" style="margin-bottom:14px">
              This list has reached its maximum capacity of {{ activeList.max_contacts }} contacts. Delete some contacts or create a new list.
            </div>

            <div class="em-stat-grid sm">
              <div class="em-stat">
                <span class="em-stat-label">List ID</span>
                <strong class="em-stat-value">#{{ activeList.id }}</strong>
              </div>
              <div class="em-stat">
                <span class="em-stat-label">Total Contacts</span>
                <strong class="em-stat-value">{{ activeList.count }}</strong>
                <small>in this list</small>
              </div>
              <div class="em-stat">
                <span class="em-stat-label">Slots Available</span>
                <strong class="em-stat-value" :class="{ 'em-danger': activeList.is_full }">{{ activeList.slots_remaining }}</strong>
                <small>of {{ activeList.max_contacts }}</small>
              </div>
              <div class="em-stat">
                <span class="em-stat-label">Capacity Used</span>
                <strong class="em-stat-value">{{ Math.round((activeList.count / activeList.max_contacts) * 100) }}%</strong>
              </div>
            </div>

            <div class="em-lcb-wrap">
              <div class="em-lcb-track">
                <div class="em-lcb-fill" :class="{ full: activeList.is_full }"
                     :style="{ width: Math.min(100, Math.round((activeList.count / activeList.max_contacts) * 100)) + '%' }"></div>
              </div>
              <div class="em-lcb-labels">
                <span>{{ activeList.count }} contacts used</span>
                <span :class="{ 'em-danger': activeList.is_full }">{{ activeList.slots_remaining }} slots remaining</span>
              </div>
            </div>

            <div class="em-card">
              <div class="em-card-head">
                <h2>Contacts in this list</h2>
                <span class="em-muted sm">{{ activeList.count }} total</span>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th></tr></thead>
                  <tbody>
                    <tr v-if="listMembersLoading"><td colspan="4" class="em-empty">Loading...</td></tr>
                    <tr v-else-if="!listMembers.length">
                      <td colspan="4" class="em-empty">No contacts yet. Click "Import Contacts" to add some.</td>
                    </tr>
                    <tr v-for="c in listMembers" :key="c.id">
                      <td>{{ c.full_name || '—' }}</td>
                      <td>{{ c.email }}</td>
                      <td>{{ c.company || '—' }}</td>
                      <td><span class="em-badge" :class="c.status">{{ ucfirst(c.status) }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="listMembersMeta.last_page > 1" class="em-pagination">
                <button class="em-btn-ghost" :disabled="listMembersMeta.current_page <= 1" @click="loadListMembers(listMembersMeta.current_page - 1)">Prev</button>
                <span>Page {{ listMembersMeta.current_page }} / {{ listMembersMeta.last_page }}</span>
                <button class="em-btn-ghost" :disabled="listMembersMeta.current_page >= listMembersMeta.last_page" @click="loadListMembers(listMembersMeta.current_page + 1)">Next</button>
              </div>
            </div>
          </template>

          <!-- IMPORT: wizard inside the list -->
          <template v-else-if="listsView === 'import' && activeList">
            <div class="em-page-head">
              <div>
                <button class="em-back-btn" @click="backToDetail">← {{ activeList.name }}</button>
                <h1>Import Contacts</h1>
                <p>Importing into <strong>{{ activeList.name }}</strong> &mdash; <strong>{{ activeList.slots_remaining }}</strong> slot(s) remaining.</p>
              </div>
              <button v-if="!['method','result'].includes(importStep)" class="em-btn-ghost" @click="resetImport">Start Over</button>
            </div>

            <div v-if="importStep !== 'result'" class="em-import-stepbar">
              <template v-for="(s, idx) in importWizardSteps" :key="s.id">
                <div class="em-istep" :class="{ active: importStep === s.id, done: importStepIndex > idx }">
                  <div class="em-istep-dot">{{ importStepIndex > idx ? '✓' : idx + 1 }}</div>
                  <span class="em-istep-label">{{ s.label }}</span>
                </div>
                <div v-if="idx < importWizardSteps.length - 1" class="em-istep-line"></div>
              </template>
            </div>

            <!-- STEP: METHOD -->
            <div v-if="importStep === 'method'" class="em-method-grid">
              <button class="em-method-card" @click="pickImportMethod('paste')">
                <div class="em-method-ico">📋</div>
                <strong>Paste CSV Data</strong>
                <p>Copy contacts from a spreadsheet and paste them directly into a text area.</p>
              </button>
              <button class="em-method-card" @click="pickImportMethod('file')">
                <div class="em-method-ico">📂</div>
                <strong>Upload CSV File</strong>
                <p>Drag and drop or browse for a .csv file. Supports up to 5,000 contacts.</p>
              </button>
              <button class="em-method-card" @click="pickImportMethod('image')">
                <div class="em-method-ico">🔍</div>
                <strong>Image / OCR</strong>
                <p>Upload a business card, screenshot, or contact sheet — we'll extract the data.</p>
              </button>
            </div>

            <!-- STEP: INPUT -->
            <div v-else-if="importStep === 'input'">
              <div v-if="importMethod === 'paste'" class="em-card">
                <div class="em-card-head">
                  <h2>Paste email addresses</h2>
                  <span class="em-muted sm">One email address per line</span>
                </div>
                <div class="em-paste-hint">
                  <code>john@example.com</code><br>
                  <code>jane@company.com</code>
                </div>
                <textarea v-model="importPasteText" class="em-input em-textarea em-paste-area"
                          placeholder="Paste email addresses here, one per line..."></textarea>
                <p v-if="importPasteText.trim()" class="em-paste-meta">
                  {{ importPasteText.trim().split('\n').filter(l => l.trim()).length }} email(s) detected
                </p>
                <div class="em-import-nav">
                  <button class="em-btn-ghost" @click="importStep = 'method'">Back</button>
                  <button class="em-btn-primary" :disabled="!importPasteText.trim()" @click="parsePaste">Parse & Continue</button>
                </div>
              </div>

              <div v-else-if="importMethod === 'file'" class="em-card">
                <div class="em-card-head"><h2>Upload a CSV file</h2></div>
                <div class="em-dropzone" :class="{ 'dz-over': importDragOver, 'dz-filled': importFile }"
                     @dragover.prevent="importDragOver = true" @dragleave="importDragOver = false"
                     @drop.prevent="onFileDrop" @click="$refs.importFileRef.click()">
                  <input ref="importFileRef" type="file" accept=".csv,.txt" hidden @change="onFileSelect">
                  <div class="em-dz-inner">
                    <div class="em-dz-ico">{{ importFile ? '✅' : '📂' }}</div>
                    <strong>{{ importFile ? importFile.name : 'Drag & drop your CSV here' }}</strong>
                    <p v-if="!importFile">or <span class="em-dz-link">browse to upload</span></p>
                    <p v-else class="em-muted">{{ (importFile.size / 1024).toFixed(1) }} KB — click to change</p>
                    <small v-if="!importFile">.csv files, up to 10 MB</small>
                  </div>
                </div>
                <div class="em-import-nav">
                  <button class="em-btn-ghost" @click="importStep = 'method'">Back</button>
                  <button class="em-btn-primary" :disabled="!importFile" @click="parseFile">Parse & Continue</button>
                </div>
              </div>

              <div v-else-if="importMethod === 'image'" class="em-card">
                <div class="em-card-head"><h2>Upload image or business card</h2></div>
                <div class="em-dropzone" :class="{ 'dz-over': importImgDragOver, 'dz-filled': importImageFile }"
                     @dragover.prevent="importImgDragOver = true" @dragleave="importImgDragOver = false"
                     @drop.prevent="onImageDrop" @click="$refs.importImgRef.click()">
                  <input ref="importImgRef" type="file" accept="image/*,.pdf" hidden @change="onImageSelect">
                  <div class="em-dz-inner">
                    <div class="em-dz-ico">{{ importImageFile ? '🖼' : '🔍' }}</div>
                    <strong>{{ importImageFile ? importImageFile.name : 'Drag & drop image or PDF' }}</strong>
                    <p v-if="!importImageFile">Business cards, screenshots, contact sheets</p>
                    <p v-else class="em-muted">{{ (importImageFile.size / 1024).toFixed(1) }} KB — click to change</p>
                    <small v-if="!importImageFile">PNG, JPG, PDF accepted</small>
                  </div>
                </div>
                <div v-if="importOcrBusy" class="em-ocr-row"><div class="em-spin"></div><span>Extracting contact data with OCR...</span></div>
                <div v-if="importOcrError" class="em-flash error" style="margin-top:10px">{{ importOcrError }}</div>
                <div class="em-import-nav">
                  <button class="em-btn-ghost" @click="importStep = 'method'">Back</button>
                  <button class="em-btn-primary" :disabled="!importImageFile || importOcrBusy" @click="runOcr">Extract Data</button>
                </div>
              </div>
            </div>

            <!-- STEP: MAPPING -->
            <div v-else-if="importStep === 'mapping'" class="em-card">
              <div class="em-card-head">
                <h2>Map your columns</h2>
                <span class="em-muted sm">{{ importRawRows.length }} data row(s) · match each column to a CRM field</span>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead><tr><th>Source Column</th><th>Sample Values</th><th>Map to CRM Field</th></tr></thead>
                  <tbody>
                    <tr v-for="(hdr, idx) in importHeaders" :key="idx">
                      <td><strong>{{ hdr || ('Column ' + (idx + 1)) }}</strong></td>
                      <td><span v-for="s in colSamples(idx)" :key="s" class="em-sample-chip">{{ s }}</span></td>
                      <td>
                        <select v-model="importFieldMap[idx]" class="em-input" style="min-width:160px">
                          <option value="">— Skip —</option>
                          <option v-for="f in importCrmFields" :key="f.key" :value="f.key">{{ f.label }}</option>
                        </select>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="!mappingEmailMapped" class="em-flash error" style="margin-top:10px">
                Map at least one column to <strong>Email</strong> to continue.
              </div>
              <div class="em-import-nav">
                <button class="em-btn-ghost" @click="importStep = 'input'">Back</button>
                <button class="em-btn-primary" :disabled="!mappingEmailMapped" @click="buildPreviewRows">Preview Contacts</button>
              </div>
            </div>

            <!-- STEP: PREVIEW -->
            <div v-else-if="importStep === 'preview'" class="em-card">
              <div class="em-card-head">
                <h2>Preview &amp; Validate</h2>
                <div style="display:flex;gap:8px">
                  <span class="em-stat-chip em-chip-ok">{{ importValidCount }} valid</span>
                  <span class="em-stat-chip em-chip-bad">{{ importInvalidCount }} invalid</span>
                  <span class="em-stat-chip em-chip-all">{{ importPreviewRows.length }} total</span>
                </div>
              </div>
              <div v-if="importValidCount > activeList.slots_remaining" class="em-flash error" style="margin-bottom:10px">
                Warning: {{ importValidCount }} valid contacts exceed the {{ activeList.slots_remaining }} available slots. Only the first {{ activeList.slots_remaining }} will be imported.
              </div>
              <div class="em-toolbar">
                <input v-model="previewQ" class="em-input" placeholder="Search preview...">
                <select v-model="previewFilt" class="em-input">
                  <option value="">All rows</option>
                  <option value="valid">Valid only</option>
                  <option value="invalid">Invalid only</option>
                </select>
                <div class="em-toolbar-spacer"></div>
                <button class="em-link danger" :disabled="!previewSelected.length" @click="removePreviewSelected">
                  Remove selected ({{ previewSelected.length }})
                </button>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead>
                    <tr>
                      <th class="em-check"><input type="checkbox" :checked="previewAllChecked" @change="togglePreviewAll"></th>
                      <th>#</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Company</th><th>Status</th><th>Issues</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!previewFiltered.length"><td colspan="8" class="em-empty">No rows match the current filter.</td></tr>
                    <tr v-for="row in previewPaged" :key="row._i" :class="{ 'em-row-bad': row._errs.length }">
                      <td class="em-check"><input type="checkbox" :value="row._i" v-model="previewSelected"></td>
                      <td class="em-muted">{{ row._i }}</td>
                      <td>{{ row.full_name || '—' }}</td>
                      <td :class="{ 'em-err-text': !validEmail(row.email) }">{{ row.email || '—' }}</td>
                      <td>{{ row.phone || '—' }}</td>
                      <td>{{ row.company || '—' }}</td>
                      <td>
                        <select v-model="row.status" class="em-input sm">
                          <option value="subscribed">Subscribed</option>
                          <option value="unsubscribed">Unsubscribed</option>
                          <option value="pending">Pending</option>
                        </select>
                      </td>
                      <td>
                        <span v-if="row._errs.length" class="em-badge failed">{{ row._errs.join(', ') }}</span>
                        <span v-else class="em-badge sent">OK</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="previewPageCount > 1" class="em-pagination">
                <button class="em-btn-ghost" :disabled="previewPage <= 1" @click="previewPage--">Prev</button>
                <span>Page {{ previewPage }} / {{ previewPageCount }}</span>
                <button class="em-btn-ghost" :disabled="previewPage >= previewPageCount" @click="previewPage++">Next</button>
              </div>
              <div class="em-import-nav">
                <button class="em-btn-ghost" @click="importStep = 'mapping'">Back</button>
                <button class="em-btn-primary" :disabled="!importValidCount" @click="importStep = 'settings'">
                  Continue with {{ importValidCount }} valid contact(s)
                </button>
              </div>
            </div>

            <!-- STEP: SETTINGS -->
            <div v-else-if="importStep === 'settings'" class="em-card">
              <div class="em-card-head"><h2>Import Settings</h2></div>
              <div class="em-import-settings">
                <label class="em-switch-row">
                  <div>
                    <strong>Skip Duplicates</strong>
                    <p>Contacts with an existing email address will be ignored.</p>
                  </div>
                  <input type="checkbox" v-model="importCfg.skip_duplicates" class="em-toggle">
                </label>
                <label class="em-switch-row">
                  <div>
                    <strong>Update Existing Contacts</strong>
                    <p>If a contact already exists, update their name, phone and company.</p>
                  </div>
                  <input type="checkbox" v-model="importCfg.update_existing" class="em-toggle">
                </label>
                <div class="em-setting-row">
                  <strong>Default Status</strong>
                  <select v-model="importCfg.default_status" class="em-input" style="width:200px">
                    <option value="subscribed">Subscribed</option>
                    <option value="unsubscribed">Unsubscribed</option>
                    <option value="pending">Pending</option>
                  </select>
                </div>
                <div class="em-setting-row">
                  <strong>Assign Tags</strong>
                  <div class="em-tag-pick">
                    <label v-for="t in tags" :key="t.id" class="em-tag-opt">
                      <input type="checkbox" :value="t.id" v-model="importCfg.tag_ids">{{ t.name }}
                    </label>
                    <span v-if="!tags.length" class="em-muted">No tags yet.</span>
                  </div>
                </div>
                <div class="em-setting-info">
                  Contacts will be automatically added to <strong>{{ activeList.name }}</strong>.
                </div>
              </div>
              <div v-if="importError" class="em-flash error" style="margin-top:10px">{{ importError }}</div>
              <div class="em-import-nav">
                <button class="em-btn-ghost" @click="importStep = 'preview'">Back</button>
                <button class="em-btn-primary" :disabled="busy === 'import'" @click="runImport">
                  {{ busy === 'import' ? 'Importing...' : 'Import ' + importValidCount + ' Contact(s)' }}
                </button>
              </div>
            </div>

            <!-- STEP: IMPORTING -->
            <div v-else-if="importStep === 'importing'" class="em-card em-importing-card">
              <div class="em-dz-ico">⏳</div>
              <h2>Importing contacts...</h2>
              <p class="em-muted">Please wait while we process your contacts.</p>
              <div class="em-progress-wrap">
                <div class="em-progress-bar"><div class="em-progress-fill" :style="{ width: importPct + '%' }"></div></div>
                <span>{{ importPct }}%</span>
              </div>
            </div>

            <!-- STEP: RESULT -->
            <template v-else-if="importStep === 'result' && importResult">
              <div class="em-result-banner" :class="importResult.failed > 0 ? 'em-result-warn' : 'em-result-ok'">
                <div class="em-result-ico">{{ importResult.failed > 0 ? '⚠' : '🎉' }}</div>
                <h2>Import Complete</h2>
                <p>{{ importResult.failed > 0 ? 'Import finished with some issues.' : 'All contacts were imported successfully into ' + activeList.name + '!' }}</p>
              </div>
              <div class="em-stat-grid sm" style="margin-top:12px">
                <div class="em-stat"><span class="em-stat-label">Imported</span><strong class="em-stat-value">{{ importResult.imported }}</strong><small>New contacts added</small></div>
                <div class="em-stat"><span class="em-stat-label">Updated</span><strong class="em-stat-value">{{ importResult.updated }}</strong><small>Existing updated</small></div>
                <div class="em-stat"><span class="em-stat-label">Skipped</span><strong class="em-stat-value">{{ importResult.skipped }}</strong><small>Duplicates skipped</small></div>
                <div class="em-stat"><span class="em-stat-label">Failed</span><strong class="em-stat-value" :style="importResult.failed > 0 ? 'color:var(--danger)' : ''">{{ importResult.failed }}</strong><small>Records with errors</small></div>
              </div>
              <div v-if="importResult.errors?.length" class="em-card" style="margin-top:12px">
                <div class="em-card-head"><h2>Import Errors</h2></div>
                <div class="em-table-wrap">
                  <table class="em-table">
                    <thead><tr><th>Row</th><th>Email</th><th>Reason</th></tr></thead>
                    <tbody>
                      <tr v-for="err in importResult.errors" :key="err.row">
                        <td>{{ err.row }}</td><td>{{ err.email }}</td><td>{{ err.reason }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="em-import-nav" style="margin-top:12px;border-top:none">
                <button class="em-btn-ghost" @click="resetImport">Import More</button>
                <button class="em-btn-primary" @click="backToDetail">View List</button>
              </div>
            </template>
          </template>

        </section>

        <!-- ============ CAMPAIGNS ============ -->
        <section v-else-if="section === 'campaigns'" class="em-space">
          <template v-if="!builderOpen">
            <div class="em-page-head">
              <h1>Campaigns</h1>
              <p>Create, schedule and track your email campaigns.</p>
              <button class="em-btn-primary" @click="startNewCampaign">+ Create Campaign</button>
            </div>

            <div class="em-card">
              <div class="em-toolbar">
                <select v-model="campaignStatusFilter" class="em-input">
                  <option value="">All status</option>
                  <option value="draft">Draft</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="sent">Sent</option>
                  <option value="failed">Failed</option>
                </select>
              </div>
              <div class="em-table-wrap">
                <table class="em-table">
                  <thead><tr><th>Campaign</th><th>Audience</th><th>Status</th><th>Schedule / Sent</th><th>Open</th><th>Click</th><th></th></tr></thead>
                  <tbody>
                    <tr v-if="campaignsLoading"><td colspan="7" class="em-empty">Loading...</td></tr>
                    <tr v-else-if="!filteredCampaigns.length"><td colspan="7" class="em-empty">No campaigns found.</td></tr>
                    <tr v-for="c in filteredCampaigns" :key="c.id">
                      <td><strong>{{ c.name }}</strong><small>{{ c.subject || 'No subject' }}</small></td>
                      <td>{{ c.audience_group ?? '—' }}<small>{{ c.audience_count }} recipients</small></td>
                      <td><span class="em-badge" :class="c.status">{{ ucfirst(c.status) }}</span></td>
                      <td>{{ c.status === 'sent' ? formatDate(c.sent_at) : (c.scheduled_at ? formatDateTime(c.scheduled_at) : '—') }}</td>
                      <td>{{ pct(c.open_rate) }}</td>
                      <td>{{ pct(c.click_rate) }}</td>
                      <td class="em-row-actions">
                        <button class="em-link" @click="editCampaign(c)">Edit</button>
                        <button v-if="c.status === 'sent'" class="em-link" @click="openReport(c)">Report</button>
                        <button class="em-link" @click="duplicateCampaign(c)">Duplicate</button>
                        <button class="em-link danger" @click="deleteCampaign(c)">Delete</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>

          <!-- CAMPAIGN BUILDER -->
          <template v-else>
            <div class="em-page-head">
              <h1>{{ form.id ? 'Edit Campaign' : 'New Campaign' }}</h1>
              <p>{{ wizardSteps.find(s => s.id === wizardStep)?.desc }}</p>
              <button class="em-btn-ghost" @click="closeBuilder">Back to list</button>
            </div>

            <div class="em-builder">
              <aside class="em-steps">
                <button v-for="s in wizardSteps" :key="s.id" :class="{ active: wizardStep === s.id }" @click="wizardStep = s.id">
                  <span>{{ s.n }}</span>{{ s.label }}
                </button>
              </aside>

              <div class="em-card em-builder-body">
                <div v-if="builderError" class="em-flash error">{{ builderError }}</div>

                <!-- Step 1: Info -->
                <div v-if="wizardStep === 'info'" class="em-form-grid">
                  <label class="full"><span>Campaign Name *</span><input v-model="form.name" class="em-input" placeholder="e.g. June Newsletter"></label>
                  <label><span>Sender Name</span><input v-model="form.sender_name" class="em-input" :placeholder="settings.from_name || 'From settings'"></label>
                  <label><span>Sender Email</span><input v-model="form.sender_email" class="em-input" :placeholder="settings.from_email || 'From settings'"></label>
                </div>

                <!-- Step 2: Audience -->
                <div v-else-if="wizardStep === 'audience'" class="em-form-grid">
                  <label class="full">
                    <span>Audience Group *</span>
                    <select v-model="form.audience_group_id" class="em-input">
                      <option value="">Select a group</option>
                      <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }} ({{ g.count }})</option>
                    </select>
                  </label>
                  <div class="full em-estimate">
                    <strong>{{ estimatedRecipients }}</strong> estimated subscribed recipients
                    <small v-if="form.audience_group_id">Contacts that are unsubscribed or bounced are skipped automatically.</small>
                  </div>
                </div>

                <!-- Step 3: Content -->
                <div v-else-if="wizardStep === 'content'" class="em-content-grid">
                  <div>
                    <div class="em-form-grid">
                      <label class="full">
                        <span>Template</span>
                        <select v-model="selectedTemplateId" class="em-input" @change="applyTemplate">
                          <option value="">— Start from scratch —</option>
                          <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                      </label>
                      <label class="full"><span>Subject Line *</span><input v-model="form.subject" class="em-input"></label>
                      <label class="full"><span>Preview Text</span><input v-model="form.preview_text" class="em-input"></label>
                      <div class="full">
                        <span class="em-form-label">Body</span>
                        <div class="em-rich-toolbar">
                          <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('bold')"><b>B</b></button>
                          <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('italic')"><i>I</i></button>
                          <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('underline')"><u>U</u></button>
                          <span class="em-toolbar-sep"></span>
                          <button type="button" class="em-toolbar-btn sm" :disabled="busy === 'img-upload'" @click="$refs.campaignImageInput.click()">
                            &#128247; {{ busy === 'img-upload' ? 'Uploading...' : 'Insert Image' }}
                          </button>
                          <input ref="campaignImageInput" type="file" accept="image/*" style="display:none" @change="insertCampaignImage">
                        </div>
                        <div ref="campaignBodyRef" contenteditable="true" class="em-rich-editor" @input="form.body = $event.target.innerHTML" @paste="handleRichPaste($event, campaignBodyRef, form, 'body')"></div>
                      </div>
                    </div>
                    <div class="em-merge">
                      <button v-for="tag in mergeTags" :key="tag" class="em-chip" @click="insertTag(tag)">{{ tag }}</button>
                    </div>
                  </div>
                  <aside class="em-preview">
                    <div class="em-preview-top">
                      <span>{{ form.sender_email || settings.from_email || 'sender@company.com' }}</span>
                      <strong>{{ form.subject || 'Subject preview' }}</strong>
                    </div>
                    <div class="em-preview-body">
                      <div v-if="form.body" v-html="form.body"></div>
                      <p v-else class="em-muted">Your email body preview appears here.</p>
                    </div>
                  </aside>
                </div>

                <!-- Step 4: Review -->
                <div v-else class="em-review">
                  <div class="em-review-item"><span>Campaign</span><strong>{{ form.name || '—' }}</strong><p>{{ form.subject || 'No subject' }}</p></div>
                  <div class="em-review-item"><span>Audience</span><strong>{{ estimatedRecipients }} recipients</strong><p>{{ groups.find(g => g.id === Number(form.audience_group_id))?.name ?? 'No group' }}</p></div>
                  <div class="em-review-item"><span>Sender</span><strong>{{ form.sender_name || settings.from_name || '(settings)' }}</strong><p>{{ form.sender_email || settings.from_email || 'Not set' }}</p></div>
                  <div class="em-review-item"><span>Delivery</span><strong>{{ settings.configured ? 'SMTP ready' : 'SMTP not configured' }}</strong><p>{{ schedule || 'Send immediately' }}</p></div>
                  <label class="full"><span>Schedule (leave blank to send now)</span><input v-model="schedule" type="datetime-local" class="em-input"></label>
                </div>

                <div class="em-builder-actions">
                  <button class="em-btn-ghost" :disabled="busy" @click="saveDraft">{{ busy === 'draft' ? 'Saving...' : 'Save Draft' }}</button>
                  <button class="em-btn-ghost" :disabled="busy || !form.id" @click="openTestModal">Send Test</button>
                  <button v-if="schedule" class="em-btn-primary" :disabled="busy || !canSend" @click="scheduleCampaign">{{ busy === 'schedule' ? 'Scheduling...' : 'Schedule' }}</button>
                  <button v-else class="em-btn-primary" :disabled="busy || !canSend" @click="sendNow">{{ busy === 'send' ? 'Sending...' : 'Send Now' }}</button>
                </div>
              </div>
            </div>
          </template>
        </section>

        <!-- ============ TEMPLATES ============ -->
        <section v-else-if="section === 'templates'" class="em-space">
          <div class="em-page-head">
            <h1>Templates</h1>
            <p>Reusable email layouts, grouped by purpose.</p>
            <button class="em-btn-primary" @click="openTemplateModal()">+ New Template</button>
          </div>

          <div class="em-template-grid">
            <div v-for="t in templates" :key="t.id" class="em-card em-template-card">
              <span class="em-badge draft">{{ t.category || 'General' }}</span>
              <strong>{{ t.name }}</strong>
              <p class="em-muted">{{ t.subject }}</p>
              <div class="em-group-actions">
                <button class="em-link" @click="openTemplateModal(t)">Edit</button>
                <button class="em-link" @click="duplicateTemplate(t)">Duplicate</button>
                <button class="em-link danger" @click="deleteTemplate(t)">Delete</button>
              </div>
            </div>
            <p v-if="!templates.length" class="em-empty">No templates yet.</p>
          </div>
        </section>

        <!-- ============ AUTOMATION (placeholder) ============ -->
        <section v-else-if="section === 'automation'" class="em-space">
          <div class="em-page-head">
            <h1>Automation</h1>
            <p>Visual workflow builder — coming in the next phase.</p>
          </div>
          <div class="em-card em-automation">
            <div class="em-flow">
              <div class="em-flow-node">New Contact</div>
              <div class="em-flow-arrow">↓ wait 1 day</div>
              <div class="em-flow-node">Send Welcome Email</div>
              <div class="em-flow-arrow">↓ wait 3 days</div>
              <div class="em-flow-node">Send Product Intro</div>
              <div class="em-flow-arrow">↓ wait 7 days</div>
              <div class="em-flow-node">Send Promotional Offer</div>
            </div>
            <p class="em-muted" style="text-align:center;margin-top:20px">
              Triggers, actions and a drag-and-drop flowchart builder are planned for Phase 2.
            </p>
          </div>
        </section>

        <!-- ============ ANALYTICS ============ -->
        <section v-else-if="section === 'analytics'" class="em-space">
          <div class="em-page-head">
            <h1>Analytics</h1>
            <p>Campaign trends, comparisons and your most engaged contacts.</p>
          </div>

          <div class="em-grid-2">
            <div class="em-card">
              <div class="em-card-head"><h2>Open &amp; Click Rate Trend</h2></div>
              <div class="em-bar-list">
                <div v-for="c in analytics?.trend ?? []" :key="c.name + c.date">
                  <span class="em-bar-name">{{ c.name }}</span>
                  <div class="em-bar-track"><span :style="{ width: c.open_rate + '%' }"></span></div>
                  <em>{{ pct(c.open_rate) }} open</em>
                </div>
                <p v-if="!analytics?.trend?.length" class="em-empty">No sent campaigns yet.</p>
              </div>
            </div>

            <div class="em-card">
              <div class="em-card-head"><h2>Audience Growth</h2></div>
              <div class="em-chart">
                <div v-for="m in analytics?.audience_growth ?? []" :key="m.month" class="em-chart-col" :title="`${m.month}: ${m.count}`">
                  <div class="em-bars"><span class="b sent" :style="{ height: growthH(m.count) }"></span></div>
                  <em>{{ m.month.slice(0, 3) }}</em>
                </div>
              </div>
            </div>
          </div>

          <div class="em-card">
            <div class="em-card-head"><h2>Most Active Contacts</h2></div>
            <div class="em-table-wrap">
              <table class="em-table">
                <thead><tr><th>Contact</th><th>Email</th><th>Opens</th><th>Clicks</th></tr></thead>
                <tbody>
                  <tr v-for="c in analytics?.most_active_contacts ?? []" :key="c.email">
                    <td><strong>{{ c.name ?? '—' }}</strong></td>
                    <td>{{ c.email }}</td>
                    <td>{{ c.opens }}</td>
                    <td>{{ c.clicks }}</td>
                  </tr>
                  <tr v-if="!analytics?.most_active_contacts?.length"><td colspan="4" class="em-empty">No engagement recorded yet.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ============ SETTINGS ============ -->
        <section v-else-if="section === 'settings'" class="em-space">
          <div class="em-page-head">
            <h1>Settings</h1>
            <p>SMTP delivery, sender identity and branding.</p>
          </div>

          <div class="em-grid-2">
            <div class="em-card">
              <div class="em-card-head">
                <h2>SMTP Configuration</h2>
                <span class="em-badge" :class="settings.configured ? 'sent' : 'draft'">{{ settings.configured ? 'Connected' : 'Not configured' }}</span>
              </div>
              <div class="em-form-grid">
                <label class="full"><span>Provider Preset</span>
                  <select class="em-input" @change="applyPreset($event.target.value)">
                    <option value="">Custom</option>
                    <option value="gmail">Gmail</option>
                    <option value="outlook">Outlook</option>
                    <option value="sendgrid">SendGrid</option>
                    <option value="mailgun">Mailgun</option>
                    <option value="ses">Amazon SES</option>
                  </select>
                </label>
                <label><span>SMTP Host</span><input v-model="settingsForm.smtp_host" class="em-input" placeholder="smtp.gmail.com"></label>
                <label><span>SMTP Port</span><input v-model.number="settingsForm.smtp_port" type="number" class="em-input" placeholder="587"></label>
                <label><span>Username</span><input v-model="settingsForm.smtp_username" class="em-input"></label>
                <label><span>Password</span><input v-model="settingsForm.smtp_password" type="password" class="em-input" :placeholder="settings.smtp_password_set ? '•••••• (unchanged)' : ''"></label>
                <label><span>Encryption</span>
                  <select v-model="settingsForm.smtp_encryption" class="em-input">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                  </select>
                </label>
                <label class="em-switch full">
                  <input type="checkbox" v-model="settingsForm.tracking_enabled">
                  <span>Enable open &amp; click tracking</span>
                </label>
              </div>
            </div>

            <div class="em-card">
              <div class="em-card-head"><h2>Sender &amp; Branding</h2></div>
              <div class="em-form-grid">
                <label><span>From Name</span><input v-model="settingsForm.from_name" class="em-input"></label>
                <label><span>From Email</span><input v-model="settingsForm.from_email" type="email" class="em-input"></label>
                <label class="full"><span>Reply-To</span><input v-model="settingsForm.reply_to" type="email" class="em-input"></label>
                <label><span>Company Name</span><input v-model="settingsForm.company_name" class="em-input"></label>
                <label><span>Company Address</span><input v-model="settingsForm.company_address" class="em-input"></label>
                <label class="full"><span>Email Footer</span><textarea v-model="settingsForm.email_footer" class="em-input em-textarea sm"></textarea></label>
              </div>
              <p class="em-hint">Tracking base URL: <code>{{ settings.tracking_base_url }}</code>. Set <code>APP_URL</code> to a reachable address for remote opens/clicks to register.</p>
            </div>
          </div>

          <div class="em-settings-actions">
            <input v-model="testTo" class="em-input" type="email" placeholder="you@example.com" style="max-width:260px">
            <button class="em-btn-ghost" :disabled="busy === 'test'" @click="testSmtp">{{ busy === 'test' ? 'Sending...' : 'Send Test Email' }}</button>
            <button class="em-btn-primary" :disabled="busy === 'settings'" @click="saveSettings">{{ busy === 'settings' ? 'Saving...' : 'Save Settings' }}</button>
          </div>
        </section>
        <!-- bulk-import removed: import is now accessible from within a List -->



      </main>
    </div>

    <!-- ===== MODALS ===== -->
    <!-- List modal -->
    <div v-if="showListModal" class="em-backdrop" @click.self="showListModal = false">
      <div class="em-modal">
        <h3>{{ listForm.id ? 'Edit List' : 'Create a list' }}</h3>
        <div class="em-form-grid">
          <label class="full"><span>List Name *</span><input v-model="listForm.name" class="em-input" placeholder="e.g. Newsletter Subscribers"></label>
          <label class="full"><span>Description</span><input v-model="listForm.description" class="em-input" placeholder="Optional description"></label>
          <label><span>Max Contacts</span><input v-model.number="listForm.max_contacts" type="number" class="em-input" min="1" max="10000"></label>
        </div>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showListModal = false">Cancel</button>
          <button class="em-btn-primary" :disabled="busy === 'list' || !listForm.name.trim()" @click="saveList">
            {{ listForm.id ? 'Save Changes' : 'Create List' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Contact modal -->
    <div v-if="showContactModal" class="em-backdrop" @click.self="showContactModal = false">
      <div class="em-modal">
        <h3>{{ contactForm.id ? 'Edit Contact' : 'New Contact' }}</h3>
        <div class="em-form-grid">
          <label><span>Full Name</span><input v-model="contactForm.full_name" class="em-input"></label>
          <label><span>Email *</span><input v-model="contactForm.email" type="email" class="em-input"></label>
          <label><span>Phone</span><input v-model="contactForm.phone" class="em-input"></label>
          <label><span>Company</span><input v-model="contactForm.company" class="em-input"></label>
          <label><span>Status</span>
            <select v-model="contactForm.status" class="em-input">
              <option value="subscribed">Subscribed</option>
              <option value="unsubscribed">Unsubscribed</option>
              <option value="bounced">Bounced</option>
              <option value="pending">Pending</option>
            </select>
          </label>
          <label class="full"><span>Tags</span>
            <div class="em-tag-pick">
              <label v-for="t in tags" :key="t.id" class="em-tag-opt">
                <input type="checkbox" :value="t.id" v-model="contactForm.tag_ids">{{ t.name }}
              </label>
              <span v-if="!tags.length" class="em-muted">No tags yet — create them via “Tags”.</span>
            </div>
          </label>
        </div>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showContactModal = false">Cancel</button>
          <button class="em-btn-primary" :disabled="busy === 'contact'" @click="saveContact">Save</button>
        </div>
      </div>
    </div>

    <!-- Tag manager modal -->
    <div v-if="showTagModal" class="em-backdrop" @click.self="showTagModal = false">
      <div class="em-modal">
        <h3>Manage Tags</h3>
        <div class="em-tag-list">
          <div v-for="t in tags" :key="t.id" class="em-tag-row">
            <span class="em-tag" :style="tagStyle(t)">{{ t.name }}</span>
            <small class="em-muted">{{ t.contacts_count }} contacts</small>
            <button class="em-link danger" @click="deleteTag(t)">Delete</button>
          </div>
          <p v-if="!tags.length" class="em-muted">No tags yet.</p>
        </div>
        <div class="em-tag-add">
          <input v-model="newTag.name" class="em-input" placeholder="Tag name">
          <input v-model="newTag.color" type="color" class="em-color">
          <button class="em-btn-primary" :disabled="!newTag.name" @click="createTag">Add</button>
        </div>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showTagModal = false">Close</button>
        </div>
      </div>
    </div>

    <!-- Group modal -->
    <div v-if="showGroupModal" class="em-backdrop" @click.self="showGroupModal = false">
      <div class="em-modal wide">
        <h3>{{ groupForm.id ? 'Edit Group' : 'New Group' }}</h3>
        <div class="em-form-grid">
          <label><span>Name *</span><input v-model="groupForm.name" class="em-input"></label>
          <label><span>Type</span>
            <select v-model="groupForm.type" class="em-input" @change="previewGroup">
              <option value="dynamic">Dynamic (auto filter)</option>
              <option value="static">Static (manual list)</option>
            </select>
          </label>
          <label class="full"><span>Description</span><input v-model="groupForm.description" class="em-input"></label>
        </div>

        <div v-if="groupForm.type === 'dynamic'" class="em-filter-box">
          <strong class="em-muted">Filter conditions</strong>
          <div class="em-form-grid">
            <label><span>Company contains</span><input v-model="groupForm.filters.company" class="em-input" @input="previewGroup"></label>
            <label><span>Tag</span>
              <select v-model="groupForm.filters.tag" class="em-input" @change="previewGroup">
                <option value="">Any</option>
                <option v-for="t in tags" :key="t.id" :value="t.name">{{ t.name }}</option>
              </select>
            </label>
            <label><span>Status</span>
              <select v-model="groupForm.filters.status" class="em-input" @change="previewGroup">
                <option value="">Any</option>
                <option value="subscribed">Subscribed</option>
                <option value="unsubscribed">Unsubscribed</option>
                <option value="bounced">Bounced</option>
              </select>
            </label>
            <label><span>Activity</span>
              <select v-model="groupForm.filters.activity" class="em-input" @change="previewGroup">
                <option value="">Any</option>
                <option value="opened">Has opened</option>
                <option value="clicked">Has clicked</option>
                <option value="none">Never emailed</option>
              </select>
            </label>
          </div>
        </div>
        <div v-else class="em-filter-box">
          <strong class="em-muted">Pick contacts ({{ groupForm.contact_ids.length }} selected)</strong>
          <div class="em-static-pick">
            <label v-for="c in contacts" :key="c.id" class="em-tag-opt">
              <input type="checkbox" :value="c.id" v-model="groupForm.contact_ids">{{ c.full_name || c.email }}
            </label>
            <p v-if="!contacts.length" class="em-muted">Load contacts in the Contacts tab first.</p>
          </div>
        </div>

        <div class="em-estimate"><strong>{{ groupPreviewCount }}</strong> matching contacts</div>

        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showGroupModal = false">Cancel</button>
          <button class="em-btn-primary" :disabled="busy === 'group'" @click="saveGroup">Save</button>
        </div>
      </div>
    </div>

    <!-- Template modal -->
    <div v-if="showTemplateModal" class="em-backdrop" @click.self="showTemplateModal = false">
      <div class="em-modal wide">
        <h3>{{ templateForm.id ? 'Edit Template' : 'New Template' }}</h3>
        <div class="em-form-grid">
          <label><span>Name *</span><input v-model="templateForm.name" class="em-input"></label>
          <label><span>Category</span>
            <select v-model="templateForm.category" class="em-input">
              <option v-for="cat in templateCategories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
          </label>
          <label class="full"><span>Subject *</span><input v-model="templateForm.subject" class="em-input"></label>
          <div class="full">
            <div class="em-body-editor-tabs">
              <span class="em-form-label">Body *</span>
              <div class="em-tab-group">
                <button type="button" :class="['em-tab-btn', {active: templateTab === 'edit'}]" @click="templateTab = 'edit'">Edit</button>
                <button type="button" :class="['em-tab-btn', {active: templateTab === 'preview'}]" @click="templateTab = 'preview'">Preview</button>
              </div>
            </div>
            <div v-show="templateTab === 'edit'">
              <div class="em-rich-toolbar">
                <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('bold')"><b>B</b></button>
                <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('italic')"><i>I</i></button>
                <button type="button" class="em-toolbar-btn sm" @mousedown.prevent @click="execRich('underline')"><u>U</u></button>
                <span class="em-toolbar-sep"></span>
                <button type="button" class="em-toolbar-btn sm" :disabled="busy === 'img-upload'" @click="$refs.templateImageInput.click()">
                  &#128247; {{ busy === 'img-upload' ? 'Uploading...' : 'Insert Image' }}
                </button>
                <input ref="templateImageInput" type="file" accept="image/*" style="display:none" @change="insertTemplateImage">
              </div>
              <div ref="templateBodyRef" contenteditable="true" class="em-rich-editor" @input="templateForm.body = $event.target.innerHTML" @paste="handleRichPaste($event, templateBodyRef, templateForm, 'body')"></div>
            </div>
            <div v-if="templateTab === 'preview'" class="em-preview-frame" v-html="templateForm.body"></div>
          </div>
        </div>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showTemplateModal = false">Cancel</button>
          <button class="em-btn-primary" :disabled="busy === 'template'" @click="saveTemplate">Save</button>
        </div>
      </div>
    </div>

    <!-- Test modal -->
    <div v-if="showTestModal" class="em-backdrop" @click.self="showTestModal = false">
      <div class="em-modal">
        <h3>Send Test Email</h3>
        <input v-model="testTo" class="em-input" type="email" placeholder="you@example.com">
        <p v-if="testMsg" class="em-flash" :class="testMsg.ok ? 'success' : 'error'" style="margin-top:12px">{{ testMsg.text }}</p>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showTestModal = false">Close</button>
          <button class="em-btn-primary" :disabled="busy === 'test' || !testTo" @click="submitTest">Send</button>
        </div>
      </div>
    </div>

    <!-- Report modal -->
    <div v-if="showReportModal" class="em-backdrop" @click.self="showReportModal = false">
      <div class="em-modal wide">
        <h3>{{ reportCampaign?.name }} — Report</h3>
        <div class="em-stat-grid sm">
          <div class="em-stat"><span class="em-stat-label">Sent</span><strong class="em-stat-value">{{ reportCampaign?.sent_count }}</strong></div>
          <div class="em-stat"><span class="em-stat-label">Opened</span><strong class="em-stat-value">{{ reportCampaign?.opened_count }}</strong></div>
          <div class="em-stat"><span class="em-stat-label">Clicked</span><strong class="em-stat-value">{{ reportCampaign?.clicked_count }}</strong></div>
          <div class="em-stat"><span class="em-stat-label">Unsub</span><strong class="em-stat-value">{{ reportCampaign?.unsubscribed_count }}</strong></div>
        </div>
        <div class="em-table-wrap" style="max-height:320px;overflow:auto;margin-top:14px">
          <table class="em-table">
            <thead><tr><th>Email</th><th>Status</th><th>Opens</th><th>Clicks</th></tr></thead>
            <tbody>
              <tr v-for="r in reportRecipients" :key="r.email">
                <td>{{ r.email }}</td>
                <td><span class="em-badge" :class="r.status">{{ ucfirst(r.status) }}</span></td>
                <td>{{ r.open_count }}</td>
                <td>{{ r.click_count }}</td>
              </tr>
              <tr v-if="!reportRecipients.length"><td colspan="4" class="em-empty">No recipient data.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="em-modal-actions">
          <button class="em-btn-ghost" @click="showReportModal = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import api from '../api';

// ---- shell state ----
const theme = ref(localStorage.getItem('em_theme') || 'light');
const section = ref('dashboard');
const globalSearch = ref('');
const flash = ref(null);
const busy = ref('');

// ---- lists state ----
const listsView = ref('index'); // 'index' | 'detail' | 'import'
const activeList = ref(null);
const listsLoading = ref(false);
const listMembers = ref([]);
const listMembersMeta = reactive({ current_page: 1, last_page: 1, total: 0 });
const listMembersLoading = ref(false);
const listSearch = ref('');
const showListModal = ref(false);
const listForm = reactive({ id: null, name: '', description: '', max_contacts: 200 });

const nav = [
  { id: 'dashboard', label: 'Dashboard', icon: '▦' },
  { id: 'lists', label: 'Lists', icon: '☰' },
  { id: 'contacts', label: 'Contacts', icon: '◎' },
  { id: 'campaigns', label: 'Campaigns', icon: '✉' },
  { id: 'templates', label: 'Templates', icon: '▤' },
  { id: 'automation', label: 'Automation', icon: '⚙' },
  { id: 'analytics', label: 'Analytics', icon: '📈' },
  { id: 'settings', label: 'Settings', icon: '⚙' },
];

const mergeTags = ['{{first_name}}', '{{full_name}}', '{{company_name}}', '{{email}}', '{{phone}}'];
const templateCategories = ['Promotional', 'Newsletter', 'Product Launch', 'Event Invitation', 'Follow Up', 'Welcome Email', 'Holiday Greeting'];

// ---- data ----
const dash = ref(null);
const analytics = ref(null);
const contacts = ref([]);
const contactMeta = reactive({ current_page: 1, last_page: 1, total: 0 });
const contactsLoading = ref(false);
const contactFilters = reactive({ search: '', status: '', tag_id: '' });
const selectedContactIds = ref([]);
const bulkTagId = ref('');
const bulkGroupId = ref('');
const tags = ref([]);
const groups = ref([]);
const campaigns = ref([]);
const campaignsLoading = ref(false);
const campaignStatusFilter = ref('');
const templates = ref([]);
const settings = reactive({ configured: false, smtp_password_set: false, tracking_base_url: '' });
const settingsForm = reactive({
  smtp_host: '', smtp_port: 587, smtp_username: '', smtp_password: '', smtp_encryption: 'tls',
  from_name: '', from_email: '', reply_to: '', company_name: '', company_address: '', email_footer: '', tracking_enabled: true,
});

// ---- modal state ----
const showContactModal = ref(false);
const contactForm = reactive({ id: null, full_name: '', email: '', phone: '', company: '', status: 'subscribed', tag_ids: [] });
const showTagModal = ref(false);
const newTag = reactive({ name: '', color: '#0f766e' });
const showGroupModal = ref(false);
const groupForm = reactive({ id: null, name: '', description: '', type: 'dynamic', filters: {}, contact_ids: [] });
const groupPreviewCount = ref(0);
const campaignBodyRef = ref(null);
const showTemplateModal = ref(false);
const templateTab = ref('edit');
const templateBodyRef = ref(null);
const templateForm = reactive({ id: null, name: '', category: 'Newsletter', subject: '', body: '' });
const showTestModal = ref(false);
const testTo = ref('');
const testMsg = ref(null);
const showReportModal = ref(false);
const reportCampaign = ref(null);
const reportRecipients = ref([]);
const importInput = ref(null);

// ---- campaign builder ----
const builderOpen = ref(false);
const builderError = ref('');
const wizardStep = ref('info');
const selectedTemplateId = ref('');
const schedule = ref('');
const form = reactive({ id: null, name: '', subject: '', preview_text: '', body: '', sender_name: '', sender_email: '', audience_group_id: '' });
const wizardSteps = [
  { id: 'info', n: '1', label: 'Info', desc: 'Name your campaign and set the sender.' },
  { id: 'audience', n: '2', label: 'Audience', desc: 'Choose who receives this campaign.' },
  { id: 'content', n: '3', label: 'Content', desc: 'Write the email or start from a template.' },
  { id: 'review', n: '4', label: 'Review', desc: 'Confirm and send or schedule.' },
];

// ---- computed ----
const statCards = computed(() => {
  const s = dash.value?.stats ?? {};
  return [
    { label: 'Total Contacts', value: s.total_contacts ?? 0, detail: 'In your email book' },
    { label: 'Active Subscribers', value: s.active_subscribers ?? 0, detail: 'Subscribed' },
    { label: 'Campaigns', value: s.total_campaigns ?? 0, detail: 'All time' },
    { label: 'Emails Sent', value: s.emails_sent ?? 0, detail: 'Delivered total' },
    { label: 'Open Rate', value: (s.open_rate ?? 0) + '%', detail: 'Across campaigns' },
    { label: 'Click Rate', value: (s.click_rate ?? 0) + '%', detail: 'Across campaigns' },
    { label: 'Unsubscribed', value: s.unsubscribed ?? 0, detail: 'Opted out' },
  ];
});
const staticGroups = computed(() => groups.value.filter(g => g.type === 'static'));
const filteredLists = computed(() => {
  const q = listSearch.value.toLowerCase();
  return groups.value
    .filter(g => g.type === 'static')
    .filter(g => !q || g.name.toLowerCase().includes(q) || String(g.id).includes(q));
});
const filteredCampaigns = computed(() => campaignStatusFilter.value
  ? campaigns.value.filter(c => c.status === campaignStatusFilter.value)
  : campaigns.value);
const allSelected = computed(() => contacts.value.length > 0 && contacts.value.every(c => selectedContactIds.value.includes(c.id)));
const bodyParagraphs = computed(() => (form.body ?? '').split('\n').filter(l => l.trim()));
const estimatedRecipients = computed(() => groups.value.find(g => g.id === Number(form.audience_group_id))?.count ?? 0);
const canSend = computed(() => form.name.trim() && form.audience_group_id && settings.configured);
const chartMax = computed(() => Math.max(1, ...(dash.value?.performance ?? []).flatMap(d => [d.sent, d.opened, d.clicked])));
const growthMax = computed(() => Math.max(1, ...(analytics.value?.audience_growth ?? []).map(m => m.count)));

// ---- lifecycle ----
onMounted(() => {
  loadDashboard();
  loadTags();
  loadGroups();
  loadSettings();
});

watch(section, (s) => {
  if (s === 'dashboard') loadDashboard();
  if (s === 'lists') { listsView.value = 'index'; activeList.value = null; loadLists(); }
  if (s === 'contacts' && !contacts.value.length) loadContacts(1);
  if (s === 'campaigns') loadCampaigns();
  if (s === 'templates' && !templates.value.length) loadTemplates();
  if (s === 'analytics') loadAnalytics();
});

watch(() => form.audience_group_id, () => { /* estimate is computed */ });
watch(wizardStep, (step) => {
  if (step === 'content') nextTick(() => { if (campaignBodyRef.value) campaignBodyRef.value.innerHTML = form.body || ''; });
});

// ---- helpers ----
function toggleTheme() { theme.value = theme.value === 'dark' ? 'light' : 'dark'; localStorage.setItem('em_theme', theme.value); }
function goSection(id) { section.value = id; builderOpen.value = false; }
function showFlash(text, type = 'success') { flash.value = { text, type }; setTimeout(() => { flash.value = null; }, 3500); }
function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function pct(v) { return v != null ? v + '%' : '—'; }
function barH(v) { return Math.round((v / chartMax.value) * 100) + '%'; }
function growthH(v) { return Math.round((v / growthMax.value) * 100) + '%'; }
function tagStyle(t) { return t.color ? { background: t.color + '22', color: t.color, borderColor: t.color + '55' } : {}; }
function formatDate(iso) { return iso ? new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
function formatDateTime(iso) { return iso ? new Date(iso).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; }
function timeAgo(iso) {
  if (!iso) return '';
  const diff = (Date.now() - new Date(iso)) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}
function runGlobalSearch() { contactFilters.search = globalSearch.value; section.value = 'contacts'; loadContacts(1); }

// ---- lists ----
async function loadLists() {
  listsLoading.value = true;
  try { const r = await api.get('/v1/email-groups'); groups.value = r.data.data ?? []; }
  finally { listsLoading.value = false; }
}
async function openListDetail(list) {
  activeList.value = list;
  listsView.value = 'detail';
  await loadListMembers(1);
}
async function loadListMembers(page = 1) {
  if (!activeList.value) return;
  listMembersLoading.value = true;
  try {
    const r = await api.get(`/v1/email-groups/${activeList.value.id}/members`, { params: { page } });
    listMembers.value = r.data.data ?? [];
    listMembersMeta.current_page = r.data.current_page;
    listMembersMeta.last_page = r.data.last_page;
    listMembersMeta.total = r.data.total;
  } finally { listMembersLoading.value = false; }
}
function backToLists() {
  listsView.value = 'index';
  activeList.value = null;
  listMembers.value = [];
  loadLists();
}
async function backToDetail() {
  resetImport();
  listsView.value = 'detail';
  await refreshActiveList();
}
async function refreshActiveList() {
  try {
    const r = await api.get('/v1/email-groups');
    groups.value = r.data.data ?? [];
    const updated = groups.value.find(l => l.id === activeList.value?.id);
    if (updated) activeList.value = updated;
    await loadListMembers(1);
  } catch (_) { /* silent */ }
}
function openListImport() {
  if (activeList.value?.is_full) return;
  resetImport();
  listsView.value = 'import';
}
function openListModal(list = null) {
  if (list) Object.assign(listForm, { id: list.id, name: list.name, description: list.description ?? '', max_contacts: list.max_contacts ?? 200 });
  else Object.assign(listForm, { id: null, name: '', description: '', max_contacts: 200 });
  showListModal.value = true;
}
async function saveList() {
  if (!listForm.name.trim()) { showFlash('List name is required.', 'error'); return; }
  busy.value = 'list';
  try {
    if (listForm.id) await api.put(`/v1/email-groups/${listForm.id}`, { ...listForm, type: 'static' });
    else await api.post('/v1/email-groups', { ...listForm, type: 'static' });
    showListModal.value = false;
    await loadLists();
    showFlash('List saved.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function deleteList(list) {
  if (!confirm(`Delete list "${list.name}"? The contacts in the list will not be deleted.`)) return;
  try {
    await api.delete(`/v1/email-groups/${list.id}`);
    await loadLists();
    showFlash('List deleted.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
}

// ---- loaders ----
async function loadDashboard() { const r = await api.get('/v1/email-analytics/dashboard'); dash.value = r.data; }
async function loadAnalytics() { const r = await api.get('/v1/email-analytics'); analytics.value = r.data; }
async function loadTags() { const r = await api.get('/v1/email-tags'); tags.value = r.data.data ?? []; }
async function loadGroups() { const r = await api.get('/v1/email-groups'); groups.value = r.data.data ?? []; }
async function loadTemplates() { const r = await api.get('/v1/email-templates'); templates.value = r.data.data ?? []; }
async function loadSettings() {
  const r = await api.get('/v1/email-settings');
  Object.assign(settings, r.data.data);
  Object.assign(settingsForm, {
    smtp_host: r.data.data.smtp_host ?? '', smtp_port: r.data.data.smtp_port ?? 587,
    smtp_username: r.data.data.smtp_username ?? '', smtp_password: '',
    smtp_encryption: r.data.data.smtp_encryption ?? 'tls',
    from_name: r.data.data.from_name ?? '', from_email: r.data.data.from_email ?? '',
    reply_to: r.data.data.reply_to ?? '', company_name: r.data.data.company_name ?? '',
    company_address: r.data.data.company_address ?? '', email_footer: r.data.data.email_footer ?? '',
    tracking_enabled: r.data.data.tracking_enabled ?? true,
  });
}
async function loadContacts(page = 1) {
  contactsLoading.value = true;
  selectedContactIds.value = [];
  try {
    const r = await api.get('/v1/email-contacts', { params: { ...cleanFilters(), page, per_page: 25 } });
    contacts.value = r.data.data ?? [];
    contactMeta.current_page = r.data.current_page;
    contactMeta.last_page = r.data.last_page;
    contactMeta.total = r.data.total;
  } finally { contactsLoading.value = false; }
}
async function loadCampaigns() {
  campaignsLoading.value = true;
  try { const r = await api.get('/v1/email-campaigns'); campaigns.value = r.data.data ?? []; }
  finally { campaignsLoading.value = false; }
}
function cleanFilters() {
  const f = {};
  if (contactFilters.search) f.search = contactFilters.search;
  if (contactFilters.status) f.status = contactFilters.status;
  if (contactFilters.tag_id) f.tag_id = contactFilters.tag_id;
  return f;
}

// ---- contacts ----
function toggleSelectAll() {
  selectedContactIds.value = allSelected.value ? [] : contacts.value.map(c => c.id);
}
function openContactModal(c = null) {
  if (c) Object.assign(contactForm, { id: c.id, full_name: c.full_name ?? '', email: c.email, phone: c.phone ?? '', company: c.company ?? '', status: c.status, tag_ids: c.tags.map(t => t.id) });
  else Object.assign(contactForm, { id: null, full_name: '', email: '', phone: '', company: '', status: 'subscribed', tag_ids: [] });
  showContactModal.value = true;
}
async function saveContact() {
  if (!contactForm.email) { showFlash('Email is required.', 'error'); return; }
  busy.value = 'contact';
  try {
    if (contactForm.id) await api.put(`/v1/email-contacts/${contactForm.id}`, contactForm);
    else await api.post('/v1/email-contacts', contactForm);
    showContactModal.value = false;
    await loadContacts(contactMeta.current_page);
    showFlash('Contact saved.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function deleteContact(c) {
  if (!confirm(`Delete ${c.email}?`)) return;
  await api.delete(`/v1/email-contacts/${c.id}`);
  await loadContacts(contactMeta.current_page);
  showFlash('Contact deleted.');
}
async function bulkContacts(action) {
  if (action === 'delete' && !confirm(`Delete ${selectedContactIds.value.length} contact(s)?`)) return;
  try {
    await api.post('/v1/email-contacts/bulk', { action, ids: selectedContactIds.value, tag_id: bulkTagId.value || null, group_id: bulkGroupId.value || null });
    bulkTagId.value = ''; bulkGroupId.value = '';
    await loadContacts(contactMeta.current_page);
    await loadGroups();
    showFlash('Bulk action applied.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
}
async function syncFromCrm() {
  busy.value = 'sync';
  try { const r = await api.post('/v1/email-contacts/sync-crm'); showFlash(r.data.message); await loadContacts(1); }
  catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
function triggerImport() { importInput.value?.click(); }
async function importContacts(e) {
  const file = e.target.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  try { const r = await api.post('/v1/email-contacts/import', fd); showFlash(r.data.message); await loadContacts(1); }
  catch (err) { showFlash(errMsg(err), 'error'); }
  finally { e.target.value = ''; }
}
async function exportContacts() {
  const r = await api.get('/v1/email-contacts/export', { params: cleanFilters(), responseType: 'blob' });
  const url = URL.createObjectURL(r.data);
  const a = document.createElement('a');
  a.href = url; a.download = 'email-contacts.csv'; a.click();
  URL.revokeObjectURL(url);
}

// ---- tags ----
function openTagManager() { showTagModal.value = true; }
async function createTag() {
  try { await api.post('/v1/email-tags', { name: newTag.name, color: newTag.color }); newTag.name = ''; await loadTags(); }
  catch (e) { showFlash(errMsg(e), 'error'); }
}
async function deleteTag(t) {
  if (!confirm(`Delete tag "${t.name}"?`)) return;
  await api.delete(`/v1/email-tags/${t.id}`); await loadTags();
}

// ---- groups ----
function openGroupModal(g = null) {
  if (g) Object.assign(groupForm, { id: g.id, name: g.name, description: g.description ?? '', type: g.type, filters: { ...(g.filters ?? {}) }, contact_ids: [] });
  else Object.assign(groupForm, { id: null, name: '', description: '', type: 'dynamic', filters: {}, contact_ids: [] });
  groupPreviewCount.value = g?.count ?? 0;
  showGroupModal.value = true;
  if (g) previewGroup();
}
async function previewGroup() {
  try {
    const r = await api.post('/v1/email-groups/preview', { type: groupForm.type, filters: groupForm.filters, contact_ids: groupForm.contact_ids });
    groupPreviewCount.value = r.data.count;
  } catch { groupPreviewCount.value = 0; }
}
watch(() => groupForm.contact_ids.length, () => { if (groupForm.type === 'static') groupPreviewCount.value = groupForm.contact_ids.length; });
async function saveGroup() {
  if (!groupForm.name) { showFlash('Name is required.', 'error'); return; }
  busy.value = 'group';
  try {
    const payload = { name: groupForm.name, description: groupForm.description, type: groupForm.type, filters: groupForm.filters, contact_ids: groupForm.contact_ids };
    if (groupForm.id) await api.put(`/v1/email-groups/${groupForm.id}`, payload);
    else await api.post('/v1/email-groups', payload);
    showGroupModal.value = false;
    await loadGroups();
    showFlash('Group saved.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function deleteGroup(g) {
  if (g.is_system || !confirm(`Delete group "${g.name}"?`)) return;
  try { await api.delete(`/v1/email-groups/${g.id}`); await loadGroups(); showFlash('Group deleted.'); }
  catch (e) { showFlash(errMsg(e), 'error'); }
}

// ---- templates ----
function openTemplateModal(t = null) {
  if (t) Object.assign(templateForm, { id: t.id, name: t.name, category: t.category ?? 'Newsletter', subject: t.subject, body: t.body });
  else Object.assign(templateForm, { id: null, name: '', category: 'Newsletter', subject: '', body: '' });
  templateTab.value = 'edit';
  showTemplateModal.value = true;
  nextTick(() => { if (templateBodyRef.value) templateBodyRef.value.innerHTML = templateForm.body; });
}
async function uploadImageAndInsert(event, editorRef, bodyObj, bodyKey) {
  const file = event.target.files[0];
  event.target.value = '';
  if (!file) return;
  busy.value = 'img-upload';
  try {
    const fd = new FormData();
    fd.append('image', file);
    const r = await api.post('/v1/email-images', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    const imgHtml = `<img src="${r.data.url}" alt="" style="max-width:100%;height:auto;">`;
    const el = editorRef.value;
    if (el) {
      el.focus();
      document.execCommand('insertHTML', false, imgHtml);
      bodyObj[bodyKey] = el.innerHTML;
    } else {
      bodyObj[bodyKey] += imgHtml;
    }
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
function insertTemplateImage(event) { return uploadImageAndInsert(event, templateBodyRef, templateForm, 'body'); }
function insertCampaignImage(event) { return uploadImageAndInsert(event, campaignBodyRef, form, 'body'); }
async function handleRichPaste(event, editorRef, bodyObj, bodyKey) {
  const items = Array.from(event.clipboardData?.items || []);
  const imageItem = items.find(item => item.type.startsWith('image/'));
  if (!imageItem) return;
  event.preventDefault();
  const file = imageItem.getAsFile();
  if (!file) return;
  busy.value = 'img-upload';
  try {
    const fd = new FormData();
    fd.append('image', file);
    const r = await api.post('/v1/email-images', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    const imgHtml = `<img src="${r.data.url}" alt="" style="max-width:100%;height:auto;">`;
    const el = editorRef.value;
    if (el) {
      el.focus();
      document.execCommand('insertHTML', false, imgHtml);
      bodyObj[bodyKey] = el.innerHTML;
    } else {
      bodyObj[bodyKey] += imgHtml;
    }
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function saveTemplate() {
  if (!templateForm.name || !templateForm.subject || !templateForm.body) { showFlash('Name, subject and body are required.', 'error'); return; }
  busy.value = 'template';
  try {
    if (templateForm.id) await api.put(`/v1/email-templates/${templateForm.id}`, templateForm);
    else await api.post('/v1/email-templates', templateForm);
    showTemplateModal.value = false;
    await loadTemplates();
    showFlash('Template saved.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function duplicateTemplate(t) {
  await api.post('/v1/email-templates', { name: t.name + ' (copy)', category: t.category, subject: t.subject, body: t.body });
  await loadTemplates();
}
async function deleteTemplate(t) {
  if (!confirm(`Delete template "${t.name}"?`)) return;
  await api.delete(`/v1/email-templates/${t.id}`); await loadTemplates();
}
function applyTemplate() {
  const t = templates.value.find(x => x.id === Number(selectedTemplateId.value));
  if (t) {
    form.subject = t.subject;
    form.body = t.body;
    nextTick(() => { if (campaignBodyRef.value) campaignBodyRef.value.innerHTML = t.body; });
  }
}

// ---- campaigns / builder ----
function startNewCampaign() {
  Object.assign(form, { id: null, name: '', subject: '', preview_text: '', body: '', sender_name: '', sender_email: '', audience_group_id: '' });
  schedule.value = ''; selectedTemplateId.value = ''; wizardStep.value = 'info'; builderError.value = '';
  section.value = 'campaigns'; builderOpen.value = true;
  if (!groups.value.length) loadGroups();
  if (!templates.value.length) loadTemplates();
}
function editCampaign(c) {
  Object.assign(form, {
    id: c.id, name: c.name, subject: c.subject ?? '', preview_text: c.preview_text ?? '', body: c.body ?? '',
    sender_name: c.sender_name ?? '', sender_email: c.sender_email ?? '', audience_group_id: c.audience_group_id ?? '',
  });
  schedule.value = c.scheduled_at ? c.scheduled_at.slice(0, 16) : '';
  wizardStep.value = 'info'; builderError.value = ''; builderOpen.value = true;
}
function closeBuilder() { builderOpen.value = false; loadCampaigns(); }
function execRich(cmd) { document.execCommand(cmd, false, null); }
function insertTag(tag) {
  if (campaignBodyRef.value) {
    campaignBodyRef.value.focus();
    document.execCommand('insertText', false, ' ' + tag);
    form.body = campaignBodyRef.value.innerHTML;
  } else {
    form.body = (form.body ?? '') + ' ' + tag;
  }
}
function buildPayload() {
  return {
    name: form.name.trim(), subject: form.subject?.trim() || '', preview_text: form.preview_text?.trim() || null,
    body: form.body ?? '', sender_name: form.sender_name?.trim() || null, sender_email: form.sender_email?.trim() || null,
    audience_group_id: form.audience_group_id || null,
  };
}
async function saveDraft() {
  if (!form.name.trim()) { builderError.value = 'Campaign name is required.'; return; }
  busy.value = 'draft'; builderError.value = '';
  try {
    if (form.id) await api.put(`/v1/email-campaigns/${form.id}`, buildPayload());
    else { const r = await api.post('/v1/email-campaigns', buildPayload()); form.id = r.data.data.id; }
    showFlash('Draft saved.');
  } catch (e) { builderError.value = errMsg(e); }
  finally { busy.value = ''; }
}
async function ensureSaved() {
  if (form.id) { await api.put(`/v1/email-campaigns/${form.id}`, buildPayload()); }
  else { const r = await api.post('/v1/email-campaigns', buildPayload()); form.id = r.data.data.id; }
}
async function sendNow() {
  if (!canSend.value) { builderError.value = 'Name, audience and SMTP settings are required.'; return; }
  busy.value = 'send'; builderError.value = '';
  try {
    await ensureSaved();
    const r = await api.post(`/v1/email-campaigns/${form.id}/send`);
    showFlash(r.data.message ?? 'Campaign sent.');
    closeBuilder();
  } catch (e) { builderError.value = errMsg(e); }
  finally { busy.value = ''; }
}
async function scheduleCampaign() {
  if (!canSend.value) { builderError.value = 'Name, audience and SMTP settings are required.'; return; }
  busy.value = 'schedule'; builderError.value = '';
  try {
    await ensureSaved();
    await api.post(`/v1/email-campaigns/${form.id}/schedule`, { scheduled_at: schedule.value });
    showFlash('Campaign scheduled.');
    closeBuilder();
  } catch (e) { builderError.value = errMsg(e); }
  finally { busy.value = ''; }
}
async function duplicateCampaign(c) {
  await api.post(`/v1/email-campaigns/${c.id}/duplicate`); await loadCampaigns(); showFlash('Campaign duplicated.');
}
async function deleteCampaign(c) {
  if (!confirm(`Delete campaign "${c.name}"?`)) return;
  await api.delete(`/v1/email-campaigns/${c.id}`); await loadCampaigns(); showFlash('Campaign deleted.');
}
async function openReport(c) {
  reportCampaign.value = c; reportRecipients.value = []; showReportModal.value = true;
  const r = await api.get(`/v1/email-campaigns/${c.id}/recipients`);
  reportRecipients.value = r.data.data ?? [];
}

// ---- test / settings ----
function openTestModal() { testMsg.value = null; showTestModal.value = true; }
async function submitTest() {
  busy.value = 'test'; testMsg.value = null;
  try {
    await ensureSaved();
    const r = await api.post(`/v1/email-campaigns/${form.id}/send-test`, { email: testTo.value });
    testMsg.value = { ok: true, text: r.data.message };
  } catch (e) { testMsg.value = { ok: false, text: errMsg(e) }; }
  finally { busy.value = ''; }
}
async function testSmtp() {
  if (!testTo.value) { showFlash('Enter a test email address.', 'error'); return; }
  busy.value = 'test';
  try { const r = await api.post('/v1/email-settings/test', { email: testTo.value }); showFlash(r.data.message); }
  catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
async function saveSettings() {
  busy.value = 'settings';
  try {
    const payload = { ...settingsForm };
    if (!payload.smtp_password) delete payload.smtp_password;
    await api.put('/v1/email-settings', payload);
    await loadSettings();
    showFlash('Settings saved.');
  } catch (e) { showFlash(errMsg(e), 'error'); }
  finally { busy.value = ''; }
}
function applyPreset(p) {
  const presets = {
    gmail: { smtp_host: 'smtp.gmail.com', smtp_port: 587, smtp_encryption: 'tls' },
    outlook: { smtp_host: 'smtp.office365.com', smtp_port: 587, smtp_encryption: 'tls' },
    sendgrid: { smtp_host: 'smtp.sendgrid.net', smtp_port: 587, smtp_encryption: 'tls' },
    mailgun: { smtp_host: 'smtp.mailgun.org', smtp_port: 587, smtp_encryption: 'tls' },
    ses: { smtp_host: 'email-smtp.us-east-1.amazonaws.com', smtp_port: 587, smtp_encryption: 'tls' },
  };
  if (presets[p]) Object.assign(settingsForm, presets[p]);
}

// =========================================================
// BULK IMPORT
// =========================================================
const importStep       = ref('method'); // method | input | mapping | preview | settings | importing | result
const importMethod     = ref('');
const importPasteText  = ref('');
const importFile       = ref(null);
const importImageFile  = ref(null);
const importFileRef    = ref(null);
const importImgRef     = ref(null);
const importDragOver   = ref(false);
const importImgDragOver = ref(false);
const importOcrBusy    = ref(false);
const importOcrError   = ref('');
const importHeaders    = ref([]);
const importRawRows    = ref([]);
const importFieldMap   = ref({});
const importPreviewRows = ref([]);
const importCfg = reactive({
  skip_duplicates: true, update_existing: false, send_welcome: false,
  default_status: 'subscribed', assign_group_id: '', tag_ids: [],
});
const importPct    = ref(0);
const importResult = ref(null);
const importError  = ref('');
const previewSelected = ref([]);
const previewQ     = ref('');
const previewFilt  = ref('');
const previewPage  = ref(1);
const PREV_SIZE    = 25;

const importWizardSteps = [
  { id: 'method', label: 'Method' }, { id: 'input', label: 'Input' },
  { id: 'mapping', label: 'Map Columns' }, { id: 'preview', label: 'Preview' },
  { id: 'settings', label: 'Settings' }, { id: 'importing', label: 'Importing' },
];
const importCrmFields = [
  { key: 'full_name', label: 'Full Name' }, { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' }, { key: 'company', label: 'Company' },
  { key: 'status', label: 'Status' },
];
const FIELD_ALIASES = {
  full_name: ['full name','name','fullname','contact name','full_name','first name','firstname'],
  email:     ['email','email address','e-mail','email_address','mail'],
  phone:     ['phone','phone number','mobile','contact number','phone_number','handphone','tel','hp'],
  company:   ['company','company name','organisation','organization','company_name','employer','client'],
  status:    ['status','subscription status','sub status'],
};

const importStepIndex = computed(() => {
  const order = importWizardSteps.map(s => s.id);
  return order.indexOf(importStep.value);
});
const mappingEmailMapped = computed(() => Object.values(importFieldMap.value).includes('email'));
const importValidCount   = computed(() => importPreviewRows.value.filter(r => !r._errs.length).length);
const importInvalidCount = computed(() => importPreviewRows.value.filter(r => r._errs.length).length);
const previewFiltered = computed(() => {
  let rows = importPreviewRows.value;
  if (previewFilt.value === 'valid')   rows = rows.filter(r => !r._errs.length);
  if (previewFilt.value === 'invalid') rows = rows.filter(r =>  r._errs.length);
  if (previewQ.value) {
    const q = previewQ.value.toLowerCase();
    rows = rows.filter(r =>
      (r.full_name||'').toLowerCase().includes(q) ||
      (r.email||'').toLowerCase().includes(q) ||
      (r.company||'').toLowerCase().includes(q)
    );
  }
  return rows;
});
const previewPaged = computed(() => {
  const s = (previewPage.value - 1) * PREV_SIZE;
  return previewFiltered.value.slice(s, s + PREV_SIZE);
});
const previewPageCount = computed(() => Math.max(1, Math.ceil(previewFiltered.value.length / PREV_SIZE)));
const previewAllChecked = computed(() =>
  previewFiltered.value.length > 0 &&
  previewFiltered.value.every(r => previewSelected.value.includes(r._i))
);

function resetImport() {
  importStep.value = 'method'; importMethod.value = ''; importPasteText.value = '';
  importFile.value = null; importImageFile.value = null; importOcrError.value = '';
  importHeaders.value = []; importRawRows.value = []; importFieldMap.value = {};
  importPreviewRows.value = []; importResult.value = null; importError.value = '';
  importPct.value = 0; previewSelected.value = []; previewQ.value = ''; previewFilt.value = ''; previewPage.value = 1;
  Object.assign(importCfg, { skip_duplicates: true, update_existing: false, send_welcome: false, default_status: 'subscribed', assign_group_id: '', tag_ids: [] });
}

function pickImportMethod(m) { importMethod.value = m; importStep.value = 'input'; }

function parseCsvRow(line) {
  const cols = []; let cur = ''; let inQ = false;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (c === '"') { inQ = !inQ; }
    else if (c === ',' && !inQ) { cols.push(cur.trim()); cur = ''; }
    else { cur += c; }
  }
  cols.push(cur.trim());
  return cols;
}
function parseCsvText(text) {
  return text.trim().split(/\r?\n/).map(l => parseCsvRow(l));
}
function autoMap(headers) {
  const map = {};
  headers.forEach((h, i) => {
    const key = h.toLowerCase().trim();
    for (const [field, aliases] of Object.entries(FIELD_ALIASES)) {
      if (!Object.values(map).includes(field) && aliases.some(a => key.includes(a) || a.includes(key))) {
        map[i] = field; break;
      }
    }
  });
  return map;
}
function loadParsed(rows) {
  if (rows.length < 2) { showFlash('Need at least a header row and one data row.', 'error'); return false; }
  importHeaders.value  = rows[0];
  importRawRows.value  = rows.slice(1).filter(r => r.some(c => c));
  importFieldMap.value = autoMap(importHeaders.value);
  importStep.value     = 'mapping';
  return true;
}
function parsePaste() {
  const emails = importPasteText.value.trim().split(/\r?\n/).map(l => l.trim()).filter(Boolean);
  if (!emails.length) { showFlash('No email addresses found.', 'error'); return; }
  importHeaders.value  = ['email'];
  importRawRows.value  = emails.map(e => [e]);
  importFieldMap.value = { 0: 'email' };
  buildPreviewRows();
  importStep.value = 'preview';
}
async function parseFile() {
  if (!importFile.value) return;
  loadParsed(parseCsvText(await importFile.value.text()));
}
function onFileDrop(e) { importDragOver.value = false; const f = e.dataTransfer.files[0]; if (f) importFile.value = f; }
function onFileSelect(e) { const f = e.target.files[0]; if (f) importFile.value = f; }
function onImageDrop(e) { importImgDragOver.value = false; const f = e.dataTransfer.files[0]; if (f) importImageFile.value = f; }
function onImageSelect(e) { const f = e.target.files[0]; if (f) importImageFile.value = f; }
async function runOcr() {
  importOcrBusy.value = true; importOcrError.value = '';
  try {
    const fd = new FormData(); fd.append('image', importImageFile.value);
    const r  = await api.post('/v1/email-contacts/ocr-extract', fd);
    importHeaders.value  = r.data.headers ?? ['Full Name', 'Email', 'Phone', 'Company'];
    importRawRows.value  = r.data.rows ?? [];
    importFieldMap.value = autoMap(importHeaders.value);
    importStep.value     = 'mapping';
  } catch (e) { importOcrError.value = e.response?.data?.message ?? 'OCR processing failed.'; }
  finally { importOcrBusy.value = false; }
}
function colSamples(idx) { return importRawRows.value.slice(0, 3).map(r => r[idx] ?? '').filter(Boolean); }
function validEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e ?? ''); }
function buildPreviewRows() {
  const map = importFieldMap.value;
  importPreviewRows.value = importRawRows.value.map((row, i) => {
    const obj = { _i: i + 2, full_name: '', email: '', phone: '', company: '', status: 'subscribed', _errs: [] };
    for (const [ci, field] of Object.entries(map)) {
      if (field) obj[field] = row[Number(ci)]?.trim() ?? '';
    }
    if (!obj.email)           obj._errs.push('Missing email');
    else if (!validEmail(obj.email)) obj._errs.push('Invalid email');
    return obj;
  });
  previewSelected.value = []; previewQ.value = ''; previewFilt.value = ''; previewPage.value = 1;
  importStep.value = 'preview';
}
function togglePreviewAll(e) {
  previewSelected.value = e.target.checked ? previewFiltered.value.map(r => r._i) : [];
}
function removePreviewSelected() {
  importPreviewRows.value = importPreviewRows.value.filter(r => !previewSelected.value.includes(r._i));
  previewSelected.value = [];
}
async function runImport() {
  importError.value = ''; importStep.value = 'importing'; importPct.value = 0;
  busy.value = 'import';
  const contacts = importPreviewRows.value
    .filter(r => !r._errs.length)
    .map(r => ({ full_name: r.full_name || null, email: r.email, phone: r.phone || null, company: r.company || null, status: r.status }));
  let pct = 0;
  const tick = setInterval(() => { pct = Math.min(pct + 7, 85); importPct.value = pct; }, 200);
  try {
    const r = await api.post('/v1/email-contacts/bulk-import', {
      contacts,
      settings: {
        skip_duplicates: importCfg.skip_duplicates,
        update_existing: importCfg.update_existing,
        default_status:  importCfg.default_status,
        assign_group_id: activeList.value?.id || null,
        tag_ids:         importCfg.tag_ids,
      },
    });
    clearInterval(tick); importPct.value = 100;
    importResult.value = r.data;
    setTimeout(() => { importStep.value = 'result'; }, 600);
  } catch (e) {
    clearInterval(tick);
    importError.value = errMsg(e);
    importStep.value = 'settings';
  }
  finally { busy.value = ''; }
}

function errMsg(e) { return e.response?.data?.message ?? 'Something went wrong.'; }
</script>

<style scoped>
.em-root {
  --bg: #eef2f7; --surface: #ffffff; --surface-2: #f8fafc; --border: #e2e8f0;
  --text: #172033; --muted: #64748b; --primary: #0f766e; --primary-soft: #e8f4f2;
  --danger: #dc2626; --shadow: 0 12px 40px rgba(15,23,42,.12);
  background: var(--bg); color: var(--text); min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.em-root[data-theme="dark"] {
  --bg: #0b1220; --surface: #131c2e; --surface-2: #1a2436; --border: #28344b;
  --text: #e2e8f0; --muted: #94a3b8; --primary: #2dd4bf; --primary-soft: #16302e;
  --danger: #f87171; --shadow: 0 12px 40px rgba(0,0,0,.4);
}

/* topbar */
.em-topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 22px; background: var(--surface); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 20; }
.em-brand { display: flex; align-items: center; gap: 12px; }
.em-logo { width: 38px; height: 38px; border-radius: 9px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 13px; }
.em-brand strong { display: block; font-size: 15px; }
.em-brand small { color: var(--muted); font-size: 12px; }
.em-topbar-actions { display: flex; align-items: center; gap: 10px; }
.em-search input { height: 36px; width: 240px; max-width: 32vw; border: 1px solid var(--border); border-radius: 8px; padding: 0 12px; background: var(--surface-2); color: var(--text); font-size: 13px; outline: none; }
.em-icon-btn { width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); color: var(--text); cursor: pointer; font-size: 15px; }

/* shell */
.em-shell { display: grid; grid-template-columns: 220px 1fr; align-items: start; }
.em-sidebar { position: sticky; top: 63px; height: calc(100vh - 63px); padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; background: var(--surface); border-right: 1px solid var(--border); }
.em-nav-link { display: flex; align-items: center; gap: 11px; padding: 10px 12px; border: none; border-radius: 9px; background: transparent; color: var(--muted); font-size: 13.5px; font-weight: 650; cursor: pointer; text-align: left; }
.em-nav-link:hover { background: var(--surface-2); color: var(--text); }
.em-nav-link.active { background: var(--primary-soft); color: var(--primary); font-weight: 800; }
.em-nav-ico { width: 22px; text-align: center; font-size: 14px; }
.em-content { padding: 22px 26px; min-height: calc(100vh - 63px); }
.em-space { display: flex; flex-direction: column; gap: 16px; }

/* page head */
.em-page-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.em-page-head h1 { margin: 0; font-size: 22px; font-weight: 900; }
.em-page-head p { margin: 0; color: var(--muted); font-size: 13px; flex: 1; min-width: 200px; }

/* cards */
.em-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px; }
.em-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.em-card-head h2 { margin: 0; font-size: 14px; font-weight: 850; }
.em-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* stats */
.em-stat-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; }
.em-stat-grid.sm { grid-template-columns: repeat(4, 1fr); }
.em-stat { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; }
.em-stat-label { display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--muted); letter-spacing: .4px; }
.em-stat-value { display: block; font-size: 24px; font-weight: 900; color: var(--primary); margin: 4px 0 2px; }
.em-stat small { color: var(--muted); font-size: 11px; }

/* buttons */
.em-btn-primary, .em-btn-ghost { height: 36px; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 750; cursor: pointer; border: 1px solid transparent; }
.em-btn-primary { background: var(--primary); color: #fff; }
.em-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.em-btn-ghost { background: var(--surface-2); color: var(--text); border-color: var(--border); }
.em-btn-ghost:disabled { opacity: .5; cursor: not-allowed; }
.em-link { background: none; border: none; color: var(--primary); font-size: 12.5px; font-weight: 750; cursor: pointer; padding: 2px 4px; }
.em-link.danger { color: var(--danger); }
.em-link:disabled { color: var(--muted); cursor: not-allowed; }

/* tables */
.em-table-wrap { overflow-x: auto; }
.em-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.em-table th, .em-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); }
.em-table th { font-size: 10.5px; text-transform: uppercase; color: var(--muted); font-weight: 800; background: var(--surface-2); }
.em-table td strong { display: block; font-weight: 750; }
.em-table td small { color: var(--muted); font-size: 11px; }
.em-check { width: 36px; }
.em-row-actions { white-space: nowrap; display: flex; gap: 2px; }
.em-empty { text-align: center; color: var(--muted); padding: 22px; font-weight: 600; }

/* badges */
.em-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; text-transform: capitalize; }
.em-badge.draft, .em-badge.pending { background: #f1f5f9; color: #475569; }
.em-badge.scheduled, .em-badge.opened { background: #fef9c3; color: #854d0e; }
.em-badge.sent, .em-badge.subscribed, .em-badge.clicked, .em-badge.delivered { background: #dcfce7; color: #166534; }
.em-badge.failed, .em-badge.bounced, .em-badge.unsubscribed { background: #fee2e2; color: #991b1b; }

/* toolbar / filters */
.em-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
.em-toolbar-spacer { flex: 1; }
.em-input { height: 36px; border: 1px solid var(--border); border-radius: 8px; padding: 0 10px; font-size: 13px; background: var(--surface-2); color: var(--text); outline: none; }
.em-input:focus { border-color: var(--primary); }
.em-input.sm { height: 30px; font-size: 12px; }
.em-textarea { height: 200px; padding: 10px; resize: vertical; line-height: 1.5; font-family: inherit; }
.em-textarea.sm { height: 80px; }
.em-bulkbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 12px; background: var(--primary-soft); border-radius: 8px; margin-bottom: 12px; font-size: 13px; font-weight: 700; }
.em-pagination { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 12px; font-size: 13px; color: var(--muted); }

/* tags */
.em-tag { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; margin: 0 4px 2px 0; border: 1px solid var(--border); background: var(--surface-2); }
.em-muted { color: var(--muted); }
.em-muted.sm { font-size: 11px; }

/* chart */
.em-chart { display: flex; align-items: flex-end; gap: 6px; height: 160px; padding-top: 10px; }
.em-chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px; height: 100%; }
.em-bars { flex: 1; display: flex; align-items: flex-end; gap: 2px; width: 100%; justify-content: center; }
.em-bars .b { width: 7px; border-radius: 3px 3px 0 0; min-height: 2px; }
.em-bars .b.sent { background: var(--primary); }
.em-bars .b.open { background: #f59e0b; }
.em-bars .b.click { background: #6366f1; }
.em-chart-col em { font-size: 10px; color: var(--muted); font-style: normal; }
.em-legend { display: flex; gap: 16px; margin-top: 10px; font-size: 11.5px; color: var(--muted); }
.em-legend .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 5px; }
.em-legend .dot.sent { background: var(--primary); }
.em-legend .dot.open { background: #f59e0b; }
.em-legend .dot.click { background: #6366f1; }

/* bar list */
.em-bar-list > div { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.em-bar-name { width: 150px; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 650; }
.em-bar-track { flex: 1; height: 9px; background: var(--surface-2); border-radius: 999px; overflow: hidden; }
.em-bar-track span { display: block; height: 100%; background: var(--primary); border-radius: 999px; }
.em-bar-list em { font-style: normal; font-weight: 800; color: var(--primary); min-width: 70px; text-align: right; }

/* timeline */
.em-timeline { display: flex; flex-direction: column; gap: 2px; }
.em-tl-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); }
.em-tl-row strong { display: block; font-size: 13px; font-weight: 700; }
.em-tl-row small { color: var(--muted); font-size: 11.5px; }
.em-status-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--muted); flex-shrink: 0; }
.em-status-dot.sent { background: #22c55e; }
.em-status-dot.scheduled { background: #f59e0b; }
.em-status-dot.draft { background: #94a3b8; }
.em-status-dot.failed { background: var(--danger); }

/* groups */
.em-group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
.em-group-card { display: flex; flex-direction: column; gap: 6px; }
.em-group-top { display: flex; justify-content: space-between; align-items: center; }
.em-group-name { font-size: 15px; font-weight: 850; }
.em-group-count { font-size: 26px; font-weight: 900; color: var(--primary); margin-top: 4px; }
.em-group-count small { font-size: 12px; color: var(--muted); font-weight: 600; }
.em-group-actions { display: flex; gap: 8px; margin-top: auto; padding-top: 8px; }

/* templates */
.em-template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.em-template-card { display: flex; flex-direction: column; gap: 6px; }
.em-template-card strong { font-size: 14px; }

/* body editor: tabs + rich toolbar */
.em-body-editor-tabs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.em-form-label { font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }
.em-tab-group { display: flex; border: 1px solid var(--border); border-radius: 7px; overflow: hidden; }
.em-tab-btn { height: 28px; padding: 0 14px; font-size: 12px; font-weight: 700; border: none; background: var(--surface-2); color: var(--muted); cursor: pointer; }
.em-tab-btn.active { background: var(--primary); color: #fff; }
.em-rich-toolbar { display: flex; gap: 4px; align-items: center; padding: 6px 8px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px 8px 0 0; flex-wrap: wrap; }
.em-toolbar-sep { width: 1px; height: 18px; background: var(--border); margin: 0 4px; flex-shrink: 0; }
.em-toolbar-btn.sm { height: 28px; padding: 0 10px; font-size: 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); cursor: pointer; }
.em-toolbar-btn.sm:hover { border-color: var(--primary); color: var(--primary); }
.em-rich-editor { min-height: 220px; max-height: 420px; overflow-y: auto; border: 1px solid var(--border); border-top: none; border-radius: 0 0 8px 8px; padding: 12px 14px; font-size: 14px; line-height: 1.6; background: var(--surface); color: var(--text); outline: none; cursor: text; word-break: break-word; }
.em-rich-editor:focus { border-color: var(--primary); }
.em-rich-editor img { max-width: 100%; height: auto; display: block; margin: 8px 0; border-radius: 4px; cursor: pointer; }
.em-rich-editor img:hover { outline: 2px solid var(--primary); }
.em-preview-frame { min-height: 200px; max-height: 400px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 16px; font-size: 13px; line-height: 1.6; background: #fff; color: #1e293b; }

/* builder */
.em-builder { display: grid; grid-template-columns: 190px 1fr; gap: 16px; align-items: start; }
.em-steps { display: flex; flex-direction: column; gap: 6px; }
.em-steps button { display: flex; align-items: center; gap: 10px; padding: 11px 13px; border: 1px solid var(--border); border-radius: 9px; background: var(--surface); color: var(--muted); font-size: 13px; font-weight: 700; cursor: pointer; text-align: left; }
.em-steps button.active { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.em-steps button span { width: 22px; height: 22px; border-radius: 50%; background: var(--surface-2); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; }
.em-builder-body { min-height: 380px; display: flex; flex-direction: column; }
.em-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; }
.em-form-grid label { display: flex; flex-direction: column; gap: 5px; }
.em-form-grid label.full, .em-form-grid .full { grid-column: 1 / -1; }
.em-form-grid span { font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--muted); }
.em-content-grid { display: grid; grid-template-columns: 1fr 320px; gap: 18px; }
.em-merge { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.em-chip { height: 28px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface-2); color: var(--text); font-size: 11px; font-weight: 650; cursor: pointer; padding: 0 9px; }
.em-chip:hover { border-color: var(--primary); color: var(--primary); }
.em-preview { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.em-preview-top { background: var(--surface-2); padding: 10px 14px; border-bottom: 1px solid var(--border); }
.em-preview-top span { display: block; font-size: 11px; color: var(--muted); }
.em-preview-top strong { font-size: 13px; }
.em-preview-body { padding: 14px; font-size: 13px; line-height: 1.55; }
.em-preview-body p { margin: 0 0 9px; }
.em-estimate { background: var(--surface-2); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: var(--muted); }
.em-estimate strong { color: var(--primary); font-size: 18px; }
.em-estimate small { display: block; margin-top: 3px; }
.em-review { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.em-review-item { border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
.em-review-item span { font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--muted); }
.em-review-item strong { display: block; font-size: 15px; margin: 5px 0 2px; }
.em-review-item p { margin: 0; color: var(--muted); font-size: 12.5px; }
.em-builder-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: auto; padding-top: 18px; border-top: 1px solid var(--border); }

/* automation */
.em-flow { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 0; }
.em-flow-node { background: var(--primary-soft); color: var(--primary); border: 1.5px solid var(--primary); border-radius: 10px; padding: 12px 26px; font-weight: 800; font-size: 13px; }
.em-flow-arrow { color: var(--muted); font-size: 12px; padding: 4px 0; }

/* settings */
.em-switch { flex-direction: row !important; align-items: center; gap: 9px !important; }
.em-switch input { width: 18px; height: 18px; }
.em-hint { margin: 14px 0 0; font-size: 12px; color: var(--muted); line-height: 1.5; }
.em-hint code, .em-flash code { background: var(--surface-2); padding: 1px 6px; border-radius: 4px; font-family: monospace; font-size: 11.5px; }
.em-settings-actions { display: flex; justify-content: flex-end; gap: 10px; align-items: center; }
.em-color { width: 40px; height: 36px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-2); cursor: pointer; }

/* flash */
.em-flash { padding: 11px 14px; border-radius: 9px; font-size: 13px; font-weight: 700; }
.em-flash.success { background: #dcfce7; color: #166534; }
.em-flash.error { background: #fee2e2; color: #991b1b; }

/* modals */
.em-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 60; }
.em-modal { background: var(--surface); border-radius: 14px; padding: 22px; width: min(460px, 96vw); box-shadow: var(--shadow); max-height: 92vh; overflow: auto; }
.em-modal.wide { width: min(640px, 96vw); }
.em-modal h3 { margin: 0 0 16px; font-size: 16px; font-weight: 900; }
.em-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.em-tag-pick, .em-static-pick { display: flex; flex-wrap: wrap; gap: 8px; max-height: 160px; overflow: auto; }
.em-static-pick { flex-direction: column; flex-wrap: nowrap; }
.em-tag-opt { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; }
.em-filter-box { margin: 14px 0; padding: 14px; border: 1px dashed var(--border); border-radius: 10px; }
.em-tag-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
.em-tag-row { display: flex; align-items: center; gap: 10px; }
.em-tag-add { display: flex; gap: 8px; align-items: center; }

/* ===== LISTS ===== */
.em-list-row td { vertical-align: middle; }
.em-list-name { font-weight: 600; text-align: left; background: none; border: none; cursor: pointer; color: var(--primary); font-size: 14px; padding: 0; text-decoration: none; }
.em-list-name:hover { text-decoration: underline; }
.em-capacity { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
.em-capacity-track { height: 6px; border-radius: 4px; background: var(--border); overflow: hidden; }
.em-capacity-fill { height: 100%; border-radius: 4px; background: var(--primary); transition: width .3s; }
.em-capacity-fill.full { background: var(--danger); }
.em-danger { color: var(--danger) !important; }
.em-back-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: var(--muted); background: none; border: none; cursor: pointer; padding: 0; margin-bottom: 6px; }
.em-back-btn:hover { color: var(--primary); }
.em-lcb-wrap { margin: 14px 0; }
.em-lcb-track { height: 10px; border-radius: 6px; background: var(--border); overflow: hidden; margin-bottom: 6px; }
.em-lcb-fill { height: 100%; background: var(--primary); border-radius: 6px; transition: width .4s; }
.em-lcb-fill.full { background: var(--danger); }
.em-lcb-labels { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); }
.em-setting-info { background: var(--primary-soft); color: var(--primary); border-radius: 8px; padding: 12px 16px; font-size: 13px; margin-top: 4px; }
.em-import-settings { display: flex; flex-direction: column; gap: 16px; padding: 8px 0; }
.em-switch-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--border); cursor: pointer; }
.em-switch-row p { font-size: 13px; color: var(--muted); margin: 2px 0 0; }
.em-toggle { width: 40px; height: 22px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
.em-setting-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.em-progress-wrap { display: flex; align-items: center; gap: 12px; margin-top: 16px; width: 100%; max-width: 400px; }
.em-progress-bar { flex: 1; height: 10px; background: var(--border); border-radius: 6px; overflow: hidden; }
.em-progress-fill { height: 100%; background: var(--primary); border-radius: 6px; transition: width .25s; }

/* ===== BULK IMPORT ===== */
.em-method-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }
.em-method-card { background: var(--surface); border: 2px solid var(--border); border-radius: 14px; padding: 32px 22px; text-align: center; cursor: pointer; transition: border-color .15s, transform .15s; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.em-method-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 6px 24px rgba(15,118,110,.12); }
.em-method-ico { font-size: 44px; line-height: 1; }
.em-method-card strong { font-size: 15px; font-weight: 850; display: block; }
.em-method-card p { color: var(--muted); font-size: 13px; margin: 0; line-height: 1.5; }

.em-import-stepbar { display: flex; align-items: center; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 20px; gap: 0; }
.em-istep { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: var(--muted); white-space: nowrap; }
.em-istep.active { color: var(--primary); }
.em-istep.done   { color: var(--primary); }
.em-istep-dot { width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; background: var(--surface); flex-shrink: 0; }
.em-istep.active .em-istep-dot { border-color: var(--primary); color: var(--primary); }
.em-istep.done .em-istep-dot   { border-color: var(--primary); background: var(--primary); color: #fff; }
.em-istep-label { display: none; }
.em-istep.active .em-istep-label, .em-istep.done .em-istep-label { display: block; }
.em-istep-line { flex: 1; height: 2px; background: var(--border); margin: 0 8px; min-width: 12px; }
.em-istep.done + .em-istep-line { background: var(--primary); }

.em-paste-hint { background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; font-size: 12px; font-family: monospace; line-height: 1.7; }
.em-paste-hint code { color: var(--primary); }
.em-paste-area { width: 100%; box-sizing: border-box; height: 220px; resize: vertical; font-family: monospace; font-size: 12.5px; line-height: 1.5; padding: 10px; }
.em-paste-meta { font-size: 12px; color: var(--muted); margin-top: 6px; }

.em-dropzone { border: 2px dashed var(--border); border-radius: 12px; padding: 48px 24px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; margin-bottom: 4px; }
.em-dropzone:hover, .em-dropzone.dz-over { border-color: var(--primary); background: var(--primary-soft); }
.em-dropzone.dz-filled { border-style: solid; border-color: var(--primary); background: var(--primary-soft); }
.em-dz-inner { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.em-dz-ico { font-size: 44px; line-height: 1; }
.em-dz-inner strong { font-size: 15px; font-weight: 850; }
.em-dz-inner p { color: var(--muted); font-size: 13px; margin: 0; }
.em-dz-inner small { font-size: 11px; color: var(--muted); }
.em-dz-link { color: var(--primary); font-weight: 750; text-decoration: underline; }

.em-ocr-row { display: flex; align-items: center; gap: 10px; padding: 12px 0; color: var(--muted); font-size: 13px; }
.em-spin { width: 18px; height: 18px; border: 2px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: em-spin .7s linear infinite; flex-shrink: 0; }
@keyframes em-spin { to { transform: rotate(360deg); } }

.em-sample-chip { display: inline-block; background: var(--surface-2); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; margin: 1px 2px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }

.em-stat-chip { font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 999px; }
.em-chip-ok  { background: #dcfce7; color: #166534; }
.em-chip-bad { background: #fee2e2; color: #991b1b; }
.em-chip-all { background: #f1f5f9; color: #475569; }

.em-row-bad { background: #fff5f5; }
[data-theme="dark"] .em-row-bad { background: #2a1515; }
.em-row-bad td { border-color: #fecaca; }
.em-err-text { color: var(--danger); font-weight: 700; }

.em-isettings { display: flex; flex-direction: column; }
.em-iset-row { display: flex; align-items: center; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--border); }
.em-iset-select-row { gap: 24px; }
.em-iset-check { width: 17px; height: 17px; flex-shrink: 0; accent-color: var(--primary); cursor: pointer; }
.em-iset-body { flex: 1; cursor: pointer; }
.em-iset-body strong { display: block; font-size: 13.5px; font-weight: 800; margin-bottom: 2px; }
.em-iset-body span { color: var(--muted); font-size: 12.5px; line-height: 1.45; }
.em-iset-disabled { opacity: .45; cursor: not-allowed; }

.em-prog-bar { width: 100%; max-width: 480px; height: 10px; background: var(--border); border-radius: 999px; overflow: hidden; margin: 16px auto 6px; }
.em-prog-fill { height: 100%; background: var(--primary); border-radius: 999px; transition: width .3s ease; }
.em-importing-card { text-align: center; padding: 60px 24px; }
.em-importing-ico { font-size: 56px; margin-bottom: 14px; }
.em-importing-card h2 { font-size: 20px; font-weight: 900; margin: 0 0 6px; }

.em-result-banner { text-align: center; padding: 40px 24px; border-radius: 14px; }
.em-result-ok   { background: #dcfce7; border: 1px solid #86efac; }
.em-result-warn { background: #fef9c3; border: 1px solid #fde047; }
.em-result-ico  { font-size: 52px; margin-bottom: 10px; }
.em-result-banner h2 { font-size: 22px; font-weight: 900; margin: 0 0 4px; color: var(--text); }
.em-result-banner p  { color: var(--muted); margin: 0; font-size: 14px; }

.em-import-nav { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }

@media (max-width: 1000px) {
  .em-method-grid { grid-template-columns: 1fr; }
  .em-istep-label { display: none !important; }
}

@media (max-width: 1000px) {
  .em-shell { grid-template-columns: 1fr; }
  .em-sidebar { position: static; height: auto; flex-direction: row; overflow-x: auto; border-right: none; border-bottom: 1px solid var(--border); }
  .em-stat-grid { grid-template-columns: repeat(2, 1fr); }
  .em-grid-2, .em-content-grid, .em-builder, .em-review, .em-form-grid { grid-template-columns: 1fr; }
}
</style>
