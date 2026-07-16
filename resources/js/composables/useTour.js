import { ref, computed } from 'vue';

const STORAGE_KEY = 'crm_tour_seen';

// ─── Global app tour (shown on first login / when no page tour exists) ─────────
export const TOUR_STEPS = [
  {
    target: '[data-tour="brand"]',
    title: 'Welcome to Bluedale CRM',
    body: 'This is your CRM platform. Everything you need to manage clients, track leads, and monitor your team is accessible from the sidebar on the left.',
    position: 'right',
  },
  {
    target: '[data-tour="nav-crm-pipeline"]',
    title: 'Contacts',
    body: 'The heart of the CRM. Add companies, assign owners, schedule todos and follow-ups, and track deals and forecasts — all from one contact record.',
    position: 'right',
  },
  {
    target: '[data-tour="nav-activity"]',
    title: 'Activity & Tasks',
    body: 'Track your todos, follow-ups, notifications, and the team task manager. Stay on top of your daily workload from here.',
    position: 'right',
  },
  {
    target: '[data-tour="nav-marketing"]',
    title: 'Marketing & Media',
    body: 'Plan social media posts, manage email campaigns, track site availability, and maintain your posting calendar — all in one place.',
    position: 'right',
  },
  {
    target: '[data-tour="nav-marketing"]',
    title: 'Marketing AI',
    body: 'The new Marketing AI feature uses behaviour-based micro-segmentation, an AI email assistant, and next-best-action recommendations to help you prioritise and personalise outreach.',
    position: 'right',
  },
  {
    target: '[data-tour="notification-bell"]',
    title: 'Notifications',
    body: 'Your notification bell shows announcements, overdue items, tasks due today, and upcoming deadlines. Click it for a quick preview, or open the Notifications page for the full view.',
    position: 'bottom-left',
  },
  {
    target: '[data-tour="settings-btn"]',
    title: 'Settings',
    body: 'Customise your appearance, timezone, and notification preferences. Admins can also manage users, roles, lookups, and system configuration from here.',
    position: 'bottom-left',
  },
  {
    target: '[data-tour="user-profile"]',
    title: 'Your Account',
    body: 'Access your profile, update your details, change your password, or log out from here.',
    position: 'bottom-left',
  },
];

// ─── Page-specific tours keyed by Vue Router route name ──────────────────────
export const PAGE_TOURS = {
  home: [
    {
      target: '[data-tour="dash-hero"]',
      title: 'Your Dashboard',
      body: 'This is your personal workspace. It gives you a real-time snapshot of your pipeline, contacts, and revenue all in one place.',
      position: 'bottom',
    },
    {
      target: '[data-tour="dash-add-widget"]',
      title: 'Add Widgets',
      body: 'Click here to add widgets — Revenue charts, Recent Contacts, Forecast Pipeline, and more. Customise your dashboard to show exactly what matters most to you.',
      position: 'bottom-left',
    },
    {
      target: '[data-tour="dash-edit-layout"]',
      title: 'Edit Layout',
      body: 'Switch to edit mode to drag widgets to new positions and resize them freely. Your layout is saved automatically when you click Done.',
      position: 'bottom-left',
    },
  ],

  list: [
    {
      target: '.view-tabs',
      title: 'Four Views',
      body: 'Switch between Contacts (full list), Summary (activity analytics), To-Do (all contact tasks), and Forecast (deal pipeline). Each tab has its own filters.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter & Search',
      body: 'Narrow down contacts by date added, company name, assigned user, status, type, or category. Use Export to download the filtered list as an Excel file.',
      position: 'bottom',
    },
    {
      target: '[data-tour="add-contact-btn"]',
      title: 'Add a Contact',
      body: 'Click here to create a new company or client record. Fill in the company name, assign it to a user, set the status and type, then save.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Contact List',
      body: 'Each row is a contact record. Click any company name to open the full record where you can log tasks, follow-ups, calls, emails, and deals.',
      position: 'bottom',
    },
  ],

  'list-contacts': [
    {
      target: '.view-tabs',
      title: 'Four Views',
      body: 'Switch between Contacts (full list), Summary (activity analytics), To-Do (all contact tasks), and Forecast (deal pipeline). Each tab has its own filters.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter & Search',
      body: 'Narrow down contacts by date added, company name, assigned user, status, type, or category. Use Export to download the filtered list as an Excel file.',
      position: 'bottom',
    },
    {
      target: '[data-tour="add-contact-btn"]',
      title: 'Add a Contact',
      body: 'Click here to create a new company or client record. Fill in the company name, assign it to a user, set the status and type, then save.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Contact List',
      body: 'Each row is a contact record. Click any company name to open the full record where you can log tasks, follow-ups, calls, emails, and deals.',
      position: 'bottom',
    },
  ],

  'list-summary': [
    {
      target: '.view-tabs',
      title: 'Activity Summary Tab',
      body: 'You\'re on the Activity Summary tab — a year-by-year breakdown of which contacts were reached each month. Switch tabs anytime to return to the full contact list.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Summary Filters',
      body: 'Filter by year, company name, assigned user, status, type, category, or industry. Hit Search to apply — Reset clears all filters and restores defaults.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Monthly Activity Grid',
      body: 'Each row shows a contact and its 12-month activity grid for the selected year. Green cells mean a task was logged that month; grey means no activity. Click any row to open the contact\'s full record.',
      position: 'bottom',
    },
  ],

  'list-tasks': [
    {
      target: '.view-tabs',
      title: 'To-Do Tab',
      body: 'You\'re on the To-Do tab — a cross-contact view of all tasks. Each row is a task linked to a company. Manage your daily workload here without opening individual contact records.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter To-Dos',
      body: 'Filter by view period (All, Day, Month, Year), date, company name, assigned user, or completion status. Hit Search to apply.',
      position: 'bottom',
    },
    {
      target: '[data-tour="add-todo-btn"]',
      title: 'Add a To-Do',
      body: 'Click here to create a new to-do. Choose a company, task type, and due date, and add an optional remark — it appears here and on that contact\'s record.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'To-Do Records',
      body: 'Each row shows the due date, company, task type, and remark. Use the checkmark to mark a task complete, or the undo button to revert it to pending.',
      position: 'bottom',
    },
  ],

  'list-forecast': [
    {
      target: '.view-tabs',
      title: 'Forecast Tab',
      body: 'You\'re on the Forecast tab — your sales pipeline across all contacts. Each entry captures the product, amount, and expected result. Use the Add Forecast button to log a new deal.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter Forecasts',
      body: 'Narrow forecasts by company or product name, product type, forecast result, assigned user, or date range. Click Search to apply — Reset clears all filters.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Forecast Records',
      body: 'Each row is a deal showing the company, product, amount, date, and result. The totals bar at the top shows your confirmed and pending pipeline value. Click the edit button to update any entry.',
      position: 'bottom',
    },
  ],

  'contact-view': [
    {
      target: '[data-tour="contact-banner"]',
      title: 'Contact Profile',
      body: 'The banner shows the company name, classification tags (status, type, industry, category), and a quick summary of total to-dos, follow-ups, and forecasts logged against this contact.',
      position: 'bottom',
    },
    {
      target: '[data-tour="contact-head-actions"]',
      title: 'Quick Actions',
      body: 'Add a new to-do, log a forecast entry, edit the contact details, or delete the record — all without leaving the page.',
      position: 'bottom-left',
    },
    {
      target: '[data-tour="contact-tasks-card"]',
      title: 'To-Dos & Follow-Ups',
      body: 'Every to-do for this contact lives here. Each row shows the due date, task type, remark, and status. Click any to-do row to open it — inside you can mark it complete, edit details, and add follow-up notes. The follow-up count badge shows how many follow-ups have been logged against that to-do.',
      position: 'bottom',
    },
    {
      target: '[data-tour="contact-activity-card"]',
      title: 'Monthly Activity Chart',
      body: 'A month-by-month breakdown of how many to-dos were completed and follow-ups logged for this contact over the past year. Use it to spot gaps in engagement or periods of high activity.',
      position: 'bottom',
    },
    {
      target: '[data-tour="contact-forecast-card"]',
      title: 'Forecast History',
      body: 'All forecast entries logged against this contact — product name, amount, date, and result (Successful / Unsuccessful / Pending). The running total shows the confirmed and pending pipeline value. Use Add Forecast in the quick actions bar to log a new entry.',
      position: 'bottom',
    },
  ],

  // ── Contact-scoped forms ──────────────────────────────────────────────────
  'contact-add': [
    {
      target: '.page-header',
      title: 'Add a New Contact',
      body: 'A quick two-step form — company details first, then classification. Required fields are marked; everything else can be filled in later from the contact record.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Company Information',
      body: 'Start with the company name and basic details, then click Next for Step 2 — owner, status, type, and category.',
      position: 'bottom',
    },
  ],

  'contact-edit': [
    {
      target: '.page-header',
      title: 'Edit Company',
      body: 'Update any company detail — name, contact info, or classification. This doesn\'t affect the to-dos, follow-ups, or forecasts already logged against this contact.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Update and Save',
      body: 'Change any field, then click Save at the bottom to apply your changes.',
      position: 'bottom',
    },
  ],

  'task-add': [
    {
      target: '.page-header',
      title: 'Add To-Do',
      body: 'Schedule a to-do tied directly to this contact — task type, due date, and an optional note.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Fill In the Details',
      body: 'Once saved, it appears on both this contact\'s record and the main To-Do list.',
      position: 'bottom',
    },
  ],

  'todo-add': [
    {
      target: '.page-banner',
      title: 'Add To-Do',
      body: 'Create a to-do for any contact — task type, due date, and an optional remark.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Fill In the Details',
      body: 'Pick the company this to-do belongs to, then save — it\'ll show up on the To-Do list and the contact\'s record.',
      position: 'bottom',
    },
  ],

  'task-edit': [
    {
      target: '.page-header',
      title: 'Edit Reminder',
      body: 'Update the due date, task type, remark, or completion status of this to-do — you can also change the linked company\'s status from here.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Update and Save',
      body: 'Make your changes and click Save to apply them.',
      position: 'bottom',
    },
  ],

  'todo-view': [
    {
      target: '.page-header',
      title: 'To-Do Details',
      body: 'A read-only summary of this to-do, with quick actions to mark it complete or add a follow-up.',
      position: 'bottom',
    },
    {
      target: '.detail-card',
      title: 'Review and Act',
      body: 'Check the details, then use the action buttons to complete the to-do or log a follow-up against it.',
      position: 'bottom',
    },
  ],

  'followup-add': [
    {
      target: '.page-banner',
      title: 'Add Follow-Up',
      body: 'Log a follow-up action against an existing to-do — the action type, date, and a note on what happened.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Fill In the Details',
      body: 'Once saved, it appears in the Follow-Ups tab and on the linked contact\'s record.',
      position: 'bottom',
    },
  ],

  'followup-edit': [
    {
      target: '.page-header',
      title: 'Edit Follow-Up',
      body: 'Change the date, action type, or note on this follow-up, or update its completion status.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Update and Save',
      body: 'Make your changes and click Save to apply them.',
      position: 'bottom',
    },
  ],

  'list-followups': [
    {
      target: '.view-tabs',
      title: 'Follow-Ups Tab',
      body: 'You\'re on the Follow-Ups tab — a cross-contact view of every follow-up action logged against a to-do. Switch tabs anytime to return to the contact list, check tasks, or view the forecast pipeline.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter Follow-Ups',
      body: 'Switch between Date Range (specific from/to dates) and Month Range (whole months at a time). Narrow results by Action Type (Call, Email, Meeting…), completion Status, or search by company name. Hit Search to apply.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Follow-Up Records',
      body: 'Each row shows the date, action type, company, status, type, assigned user, task, and note. Use the column header dropdowns to filter within the current results. The checkmark marks a follow-up complete; undo reverts it to pending. Use the pencil to edit or Export to download the current view.',
      position: 'bottom',
    },
  ],

  performance: [
    {
      target: '.tab-nav',
      title: 'Performance Tabs',
      body: 'Switch between Overview (KPI summary + overdue items), Team (admin cross-user comparison), and Targets (set your monthly KPI goals).',
      position: 'bottom',
    },
    {
      target: '[data-tour="perf-period"]',
      title: 'Period Selector',
      body: 'Choose Week, Month, or Year to see performance data for that period. The KPI cards, pipeline forecast, and target progress all update instantly.',
      position: 'bottom',
    },
    {
      target: '[data-tour="perf-kpi-cards"]',
      title: 'KPI Summary',
      body: 'Each card shows your count for that metric in the selected period. Red cards flag overdue items that need attention — click through to the Needs Attention section below.',
      position: 'bottom',
    },
  ],

  'forecast-summary': [
    {
      target: '.page-head',
      title: 'Forecast Summary',
      body: 'Every forecast entry logged across all contacts, in one exportable report — useful for reviewing pipeline activity beyond a single contact record.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter the Report',
      body: 'Narrow the report by date range, company, assigned user, product, or result before exporting.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Forecast Rows',
      body: 'Each row shows the company, product, amount, date, and result badge (Successful / Unsuccessful / Pending). Use the pager at the bottom to move through results.',
      position: 'bottom',
    },
    {
      target: '.btn-export',
      title: 'Export',
      body: 'Pick which columns to include, then export the current filtered view to Excel.',
      position: 'bottom-left',
    },
  ],

  // ── Projects & Deals ────────────────────────────────────────────────────────
  projects: [
    {
      target: '.page-banner',
      title: 'Projects',
      body: 'Track standalone projects linked to a contact — separate from day-to-day to-dos and follow-ups.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter Projects',
      body: 'Search the list, or click a column header to sort.',
      position: 'bottom',
    },
    {
      target: '.btn-add',
      title: 'Add a Project',
      body: 'Click here to link a new project to a contact, with a start and end date and an optional remark.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Project List',
      body: 'Each row is a project. Click the remark icon to view the full note.',
      position: 'bottom',
    },
  ],

  'project-add': [
    {
      target: '.page-banner',
      title: 'Add Project',
      body: 'Link a new project to a contact, with a name, start and end date, and an optional remark.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Fill In the Details',
      body: 'Pick the contact this project belongs to, then save.',
      position: 'bottom',
    },
  ],

  'project-edit': [
    {
      target: '.page-banner',
      title: 'Edit Project',
      body: 'Update the project name, dates, or remark.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Update and Save',
      body: 'Make your changes and click Save to apply them.',
      position: 'bottom',
    },
  ],

  deals: [
    {
      target: '.header',
      title: 'Sales Pipeline',
      body: 'Track deals from lead to close — separate from the Forecast tab on the Contacts page. The stat strip shows open count, open value, won, and lost totals.',
      position: 'bottom',
    },
    {
      target: '.view-toggle',
      title: 'Pipeline / List View',
      body: 'Switch between the Pipeline (kanban) view and a flat List view of every deal.',
      position: 'bottom-left',
    },
    {
      target: '.filter-bar',
      title: 'Filter Deals',
      body: 'Narrow deals by stage, status, or assigned user, or search by title and company.',
      position: 'bottom',
    },
    {
      target: '.kanban-board',
      title: 'Pipeline Board',
      body: 'Drag a deal card between stage columns to update it. Each card shows the value and assigned owner.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Deal List',
      body: 'Every deal in a sortable table — title, stage, status, value, probability, and close date. Click a row to edit it.',
      position: 'bottom',
    },
    {
      target: '.btn-add',
      title: 'Add a Deal',
      body: 'Click here to log a new deal against a contact.',
      position: 'bottom-left',
    },
  ],

  'deal-add': [
    {
      target: '.page-banner',
      title: 'Add Deal',
      body: 'Log a new deal against a contact — title, value, stage, and expected close date.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Fill In the Details',
      body: 'Pick the contact this deal belongs to, then save.',
      position: 'bottom',
    },
  ],

  'deal-edit': [
    {
      target: '.page-banner',
      title: 'Edit Deal',
      body: 'Update the deal\'s stage, value, probability, or status — mark it Won or Lost once it closes.',
      position: 'bottom',
    },
    {
      target: '.form-group',
      title: 'Update and Save',
      body: 'Make your changes and click Save to apply them.',
      position: 'bottom',
    },
  ],

  reports: [
    {
      target: '.page-head',
      title: 'Reports',
      body: 'Generate activity and performance reports for any time period. Select the report type, set your filters, then export to Excel.',
      position: 'bottom',
    },
  ],

  reminders: [
    {
      target: '.page-head',
      title: 'Notifications',
      body: 'Your personal task inbox — overdue items, things due today, and upcoming deadlines in the next 7 days. For company-wide announcements, visit the Notice Board.',
      position: 'bottom',
    },
    {
      target: '.section-card',
      title: 'Reminder Sections',
      body: 'Overdue items are highlighted in red, today\'s tasks in amber, and upcoming ones in blue. Click Open to jump to the task record, or Dismiss to mark it read.',
      position: 'bottom',
    },
  ],

  'notice-board': [
    {
      target: '.page-header',
      title: 'Notice Board',
      body: 'Company-wide announcements from your administrators. Unlike task reminders, announcements are broadcast to everyone — think office notices, system updates, or team-wide news.',
      position: 'bottom',
    },
    {
      target: '.announce-list',
      title: 'Reading Announcements',
      body: 'Unread announcements have a coloured accent bar and a dot next to the title. Click Mark read to dismiss one, or use Mark all read at the top to clear everything at once.',
      position: 'bottom',
    },
  ],

  // Admin tour — all steps target always-visible elements (tab buttons, not tab content)
  'dept-tasks-admin': [
    {
      target: '.tab-bar',
      title: 'Task Manager — Seven Views',
      body: 'Seven tabs cover every angle: Dashboard (stats & charts), Calendar (month view of due dates), Table (searchable list with date-range filtering, doubling as the audit log), This Week (weekly report), Board (kanban drag-and-drop), People (tasks grouped by assignee), and Files (all attached documents). Click any tab to switch.',
      position: 'bottom',
    },
    {
      target: '.page-header-actions',
      title: 'Create a New Task',
      body: 'Click New Task to assign work to any team member. Set the title, department, assignee, priority (Low / Medium / High / Critical), and due date.',
      position: 'bottom-left',
    },
    {
      target: '[data-tour="deptask-tab-dashboard"]',
      title: 'Dashboard — Stats at a Glance',
      body: 'Live counts for total, overdue, in-progress, and completed tasks. Click any stat card to jump to the Table filtered to those tasks. Charts show breakdown by department, status, and weekly completion rate.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-calendar"]',
      title: 'Calendar — Month View',
      body: 'See every task laid out by due date across the month. Use the arrows to move between months, filter by department or assignee just like the Board, and click any task pill to open its detail panel. Admins can click empty space on a day to create a new task due that date.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-table"]',
      title: 'Table — Search & Filter',
      body: 'Searchable, paginated list of every task. Filter by department, status, priority, assignee, or a date range simultaneously — use the This Week / This Month buttons for common ranges, or pick custom From/To dates to audit past work. Click any column header to sort. Hit Print for a formatted A4 report, or Export to download the current filtered view as a CSV or Excel file with your choice of columns.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-weekly"]',
      title: 'This Week — Outstanding Report',
      body: 'Printable report of all outstanding tasks grouped by department for the selected week. Use the navigation arrows to move between weeks, filter by assignee for individual workload reviews, and click Print A4 to export for team meetings.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-board"]',
      title: 'Board — Kanban View',
      body: 'Tasks appear as draggable cards across columns: Pending → In Progress → Completed. Drag a card to move it. Overdue cards are flagged in red. Click any card to view details, add comments, attach files, or override the status directly.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-people"]',
      title: 'People — Tasks by Assignee',
      body: 'The People tab groups every task under its assigned person. Each person shows an overdue/active/done chip count and a progress bar. Switch between list view (collapsible rows with task details) and cards view (compact per-user cards) using the toggle on the right of the filter bar.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-files"]',
      title: 'Files — All Attachments',
      body: 'Every file attached to any task in one searchable table. Shows the file type, filename (clickable to download), the task it belongs to, who uploaded it, and when. Click the task name to open that task\'s detail panel. Use the edit icon to rename a file or the delete icon to remove it.',
      position: 'bottom',
    },
  ],

  'dept-tasks-user': [
    {
      target: '.tab-bar',
      title: 'Your Task Manager',
      body: 'Four views: List (your personal tasks sorted by urgency), Board (team kanban for reference), Calendar (your due dates by month), and Files (all documents attached to your tasks). List is your day-to-day working surface.',
      position: 'bottom',
    },
    {
      target: '.mw-summary',
      title: 'Urgency Summary',
      body: 'The strip at the top shows how many of your tasks are overdue, due today, and due this week. Overdue counts appear in red as a prompt to action them first.',
      position: 'bottom',
    },
    {
      target: '.mw-bucket',
      title: 'Tasks by Urgency',
      body: 'Tasks are bucketed into Overdue, Due Today, This Week, Later, and No Due Date. A collapsible Done section sits at the bottom. Click any task row to open the full detail panel where you can view comments, download attachments, and advance the task.',
      position: 'bottom',
    },
    {
      target: '.mw-action',
      title: 'Advance a Task',
      body: 'Each row has a quick action button. Start begins a Pending task. Complete finishes it right away.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-board"]',
      title: 'Board View',
      body: 'See all team tasks across status columns. Click any card to view details. Task creation and admin-level status changes are managed by your admin.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-calendar"]',
      title: 'Calendar View',
      body: 'See your tasks laid out by due date across the month. Use the arrows to move between months and click any task to open its detail panel.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-files"]',
      title: 'Files — Your Attachments',
      body: 'Every file you\'ve attached to your tasks in one place. Click any filename to download it, click the task name to open that task, rename files with the edit icon, or remove them with delete.',
      position: 'bottom',
    },
  ],

  admin: [
    {
      target: '.page-head',
      title: 'Lookup Settings',
      body: 'This is where you control the dropdown values used across the CRM — contact statuses, types, categories, industries, and task types.',
      position: 'bottom',
    },
    {
      target: '.tabs-bar',
      title: 'Lookup Categories',
      body: 'Each tab manages a different dropdown list. Switch between Contact classifications (Status, Type, Category, Industry) and Task types used when logging todos.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Add, Edit, Delete',
      body: 'Type a new value in the input at the top and click Add. Click Edit on any row to rename it inline. The "In Use" count shows how many records reference it — items in use cannot be deleted. Need to consolidate old values? Tick 2+ checkboxes and click Merge Selected to combine them into one (an existing value or a brand-new name) — every record using the old values is automatically reassigned.',
      position: 'bottom',
    },
  ],

  'system-settings': [
    {
      target: '.page-header',
      title: 'System Settings',
      body: 'Global configuration that applies across the entire CRM — currently the admin notification email address for security and approval alerts.',
      position: 'bottom',
    },
    {
      target: '.settings-card',
      title: 'Notification Settings',
      body: 'Set the Admin Notification Email to receive alerts when a user is locked out for inactivity or when a new user registers and needs approval. If left blank, all admin and super-admin users receive the alerts instead.',
      position: 'bottom',
    },
  ],

  'user-activity': [
    {
      target: '.page-header',
      title: 'User Activity',
      body: 'A monitoring dashboard for all user accounts — login frequency, CRM output per user, and security events like inactivity lockouts.',
      position: 'bottom',
    },
    {
      target: '.tab-bar',
      title: 'Two Views',
      body: 'Users Overview shows a table of every user with their login count, last seen date, contacts owned, and activity in the selected period. Security Events logs lockouts, approvals, and access-restore actions.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter & Period',
      body: 'Use the chips to filter by account status (All, Active, Inactive, Flagged, Pending). The Activity Period selector changes the range used to count todos, follow-ups, deals, and projects completed.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'User Table',
      body: 'Each row is a user account. High activity counts appear in green; zero activity in the period is greyed out. Inactivity-flagged accounts are highlighted so you can restore access from the Access Control panel.',
      position: 'bottom',
    },
  ],

  'audit-log': [
    {
      target: '.page-head',
      title: 'Audit Log',
      body: 'A tamper-evident record of every admin action — who did what, when, and from which IP address. Use it for accountability reviews or to investigate unexpected changes.',
      position: 'bottom',
    },
    {
      target: '.filter-bar',
      title: 'Filter Actions',
      body: 'Search free-text across actor names, entity names, and action descriptions. Use the dropdowns to narrow by action type (created, updated, deleted, approved…), entity type (User, Contact, Role…), or time window.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Log Entries',
      body: 'Each row shows the timestamp, who performed the action, what action was taken, and what record was affected. The Changes column expands to show the before/after field values for any update.',
      position: 'bottom',
    },
  ],

  // Admin tour — tab steps target the always-visible tab buttons (not tab content)
  rbac: [
    {
      target: '.page-head',
      title: 'Access Control',
      body: 'This panel is where you manage every aspect of user access — who can log in, what they can do, and how contacts are distributed across the team.',
      position: 'bottom',
    },
    {
      target: '.view-tabs',
      title: 'Six Tabs',
      body: 'Access Control is split into six tabs: Pending Approvals, Roles, Permissions, Users, Contact Grants, and Bulk Reassign. We\'ll walk through each one — click any tab anytime to jump straight to it.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-pending"]',
      title: 'Pending Approvals',
      body: 'New user registrations land here awaiting your sign-off. Review the requested account, then click Approve to grant access. The badge shows how many users are currently waiting.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-roles"]',
      title: 'Roles',
      body: 'Roles bundle a set of permissions under one name — like admin, user, or viewer. Create a new role, rename it, or adjust which permissions it carries. Assigning a user a role is the quickest way to grant a whole group of capabilities at once.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-permissions"]',
      title: 'Permissions',
      body: 'Permissions are the individual capabilities (e.g. "view contacts", "manage territories") that make up a role. Use this tab to review every permission in the system and see which are available to assign to roles.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-users"]',
      title: 'Users',
      body: 'The full list of accounts — name, email, role, and login history. From here you can edit a user, change their role, reset their password with Set Password (no request needed), or delete an account.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-grants"]',
      title: 'Contact Grants',
      body: 'Grant one user access to another user\'s contacts without changing ownership. Enable the mutual option to share access both ways — useful for partners or stand-ins who cover each other\'s portfolios.',
      position: 'bottom',
    },
    {
      target: '[data-tour="rbac-tab-reassign"]',
      title: 'Bulk Reassign',
      body: 'Move every contact from one user to another in a single action — ideal when someone leaves or portfolios are reorganised. Pick the source user, the target user, and confirm to transfer the whole book of contacts at once.',
      position: 'bottom',
    },
  ],

  'contact-duplicates': [
    {
      target: '.page-header',
      title: 'Duplicate Contact Scanner',
      body: 'This tool finds contacts that share the exact same name and lets you merge them into a single record. Zero groups means your data is clean. Run Refresh any time to re-scan.',
      position: 'bottom',
    },
    {
      target: '.btn-refresh',
      title: 'Merging Duplicates',
      body: 'When groups appear, each card lists all records with the same name — showing the owner, status, phone, and creation date. Tick the radio button on the record to keep, then click Merge Group. All todos, deals, projects, and forecasts transfer to the kept record before the rest are deleted.',
      position: 'bottom-left',
    },
  ],

  // ── Data tools ──────────────────────────────────────────────────────────────
  'data-health': [
    {
      target: '.page-header',
      title: 'Data Health Report',
      body: 'A system-wide check on data quality — missing fields, duplicate names, and overdue activity — so you know where the CRM data needs cleanup.',
      position: 'bottom',
    },
    {
      target: '.stats-row',
      title: 'Headline Numbers',
      body: 'Total contacts, PICs, to-dos, and follow-ups, with overdue counts called out underneath.',
      position: 'bottom',
    },
    {
      target: '.missing-grid',
      title: 'Missing Required Links',
      body: 'How many contacts are missing key fields like an assigned user, status, or type — worth fixing before reports rely on that data.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Duplicate Contacts',
      body: 'Contacts sharing the exact same name. Click Merge to combine records — all to-dos, deals, projects, and forecasts transfer to the one you keep.',
      position: 'bottom',
    },
  ],

  import: [
    {
      target: '.page-header',
      title: 'Import Data',
      body: 'Bulk-load contacts from a spreadsheet. Three steps: upload the file, map its columns to CRM fields, then review the results.',
      position: 'bottom',
    },
    {
      target: '.stepper',
      title: 'Step Indicator',
      body: 'Tracks where you are in the process. The app auto-matches columns where it can — you\'ll only need to fix the ones it couldn\'t guess.',
      position: 'bottom',
    },
    {
      target: '.drop-zone',
      title: 'Upload Your File',
      body: 'Drag a CSV or Excel file here, or click to browse.',
      position: 'bottom',
    },
  ],

  announcements: [
    {
      target: '.page-header',
      title: 'Team Announcements',
      body: 'Post messages that appear in every user\'s notification bell. Use announcements for office notices, system updates, or any message the whole team needs to see.',
      position: 'bottom',
    },
    {
      target: '.btn-new',
      title: 'Create an Announcement',
      body: 'Click here to write a new announcement. Set a Publish At time to schedule it for later, or leave it blank to go live immediately. Set Expires At to auto-remove it after a date.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Announcement List',
      body: 'Live announcements show a green badge and are visible to all users. Scheduled or unpublished ones show Draft. The Read by column tells you how many users have dismissed the message from their bell.',
      position: 'bottom',
    },
  ],

  'site-availability': [
    {
      target: '.action-bar',
      title: 'Search & Actions',
      body: 'Filter the grid by product type (Billboard, Temp Board, Lamp Post Bunting) or search by place name or company. Use Add Booking to reserve a site for a client, Register Product to add a new advertising site to the system, or Map View to see all sites plotted on an interactive map.',
      position: 'bottom',
    },
    {
      target: '.table-card',
      title: 'Site Availability Grid',
      body: 'The grid shows every confirmed advertising site as a row with 12 months across the top. A coloured bar in a month cell means that site is booked for that period. A grey dot means it\'s available. Click any cell to view or manage the booking for that month.',
      position: 'bottom',
    },
    {
      target: '.view-toggle',
      title: 'Month / Week View',
      body: 'Toggle between Month view (12 columns, one per month — good for a full-year overview) and Week view (52 columns, one per week — precise for checking exact availability windows). Week view auto-scrolls to the current week.',
      position: 'bottom-left',
    },
    {
      target: '.booking-bar',
      title: 'Booking Bars',
      body: 'Each bar shows the client company name and how many days the booking spans. Blue bars are active bookings; orange bars are for alternating rows to make scanning easier. Faded grey bars are completed (past end date). Hover for the exact date range. Click to edit or delete the booking.',
      position: 'bottom',
    },
    {
      target: '.btn-proposal',
      title: 'Generate Proposal',
      body: 'Tick the checkbox on one or more site rows, then click Generate Proposal. A 3-step wizard lets you fill in client details, pricing, and signatory info before generating a branded PDF proposal you can send directly to the client.',
      position: 'bottom-left',
    },
    {
      target: '.staged-section',
      title: 'Staged for Client Review',
      body: 'When you use Save as Draft + Print PDF during product registration, the site is held here as a draft. Tick one or more staged sites (or the header checkbox to select all) to generate a proposal covering them. Once the client approves, click Confirm to add it to the active availability list, or Discard to remove it.',
      position: 'bottom',
    },
  ],

  'contact-analysis': [
    {
      target: '.ca-header',
      title: 'Contact Analysis',
      body: 'A health check on your contact base — see who needs attention, how your pipeline is distributed, and where your new contacts are coming from.',
      position: 'bottom',
    },
    {
      target: '.ca-date-wrap',
      title: 'Date Range',
      body: 'Pick a preset (Today, Last 30 days, This year…) or set a custom range. Lead Source and acquisition figures update to reflect contacts added in this window.',
      position: 'bottom-left',
    },
    {
      target: '.ca-filter-bar',
      title: 'Filters',
      body: 'Narrow everything down by agent, status, or industry. Admins also get an Agent filter to drill into a specific team member. Use Clear All Filters to reset.',
      position: 'bottom',
    },
    {
      target: '.ca-attention-row',
      title: 'Needs Attention',
      body: 'Four signals worth acting on: overdue tasks, dormant contacts (silent 60+ days), at-risk contacts (30–60 days quiet), and ones never contacted. Click any card to jump to that group in the table below.',
      position: 'bottom',
    },
    {
      target: '.ca-row-asym',
      title: 'Pipeline & Lead Source',
      body: 'On the left, your contacts broken down by current status. On the right, where new contacts came from this period — referral, walk-in, social media, and more.',
      position: 'bottom',
    },
    {
      target: '.ca-card--eng',
      title: 'Engagement Health',
      body: 'Every contact ranked by inactivity. Filter by health band, search by name, and sort by last task or days inactive. Click a contact to open its full record and take action.',
      position: 'bottom',
    },
  ],

  'predictive-insights': [
    {
      target: '.pi-header',
      title: 'Predictive Insights',
      body: 'Forward-looking analysis of your pipeline — expected revenue, neglected contacts, agent workload, and which open deals are most likely to close.',
      position: 'bottom',
    },
    {
      target: '.pi-date-wrap',
      title: 'Date Range',
      body: 'Choose the period the insights are calculated over — Last 30/60/90 days, This Year, or a custom range. Every card recalculates instantly.',
      position: 'bottom-left',
    },
    {
      target: '.pi-kpi-row',
      title: 'Headline Numbers',
      body: 'Four at-a-glance metrics: neglected contacts, expected pipeline value (open deals weighted by probability), overloaded agents, and unworked opportunities.',
      position: 'bottom',
    },
    {
      target: '.pi-row-asym',
      title: 'Forecast & Neglected Contacts',
      body: 'The chart projects revenue from open deals weighted by win probability. Beside it, the contacts that have gone 60+ days without a touch — your highest-priority follow-ups.',
      position: 'bottom',
    },
    {
      target: '.pi-row-2col',
      title: 'Workload & Opportunities',
      body: 'Agent Coverage Load shows how contacts are distributed across the team and how much of each portfolio is actively worked. Unworked Opportunities highlights industries with active contacts left untouched for 30+ days.',
      position: 'bottom',
    },
    {
      target: '[data-tour="pi-deals"]',
      title: 'Deal Win Probability',
      body: 'Open deals auto-scored on activity and urgency. Each row shows the value, close date, and a win-probability bar tagged On Track, At Risk, or High Risk — so you know where to focus.',
      position: 'bottom',
    },
  ],

  // ── Marketing ──────────────────────────────────────────────────────────────
  'social-media': [
    {
      target: '.page-header',
      title: 'Social Media Jobs',
      body: 'Track pending social media work for your clients. Each entry is a package booked for a given month, so the team always knows what\'s still outstanding.',
      position: 'bottom',
    },
    {
      target: '.entry-panel',
      title: 'Log a New Job',
      body: 'Search for the company, pick the package, set the month, then click Add Job — it appears in the pending list instantly.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Search & Filter',
      body: 'Find jobs by company or package name, or narrow the list to a specific month using the Month Filter.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Pending Jobs',
      body: 'Each row is a pending job showing the company, package, and month. Update or remove a job here as the work progresses.',
      position: 'bottom',
    },
  ],

  'posting-calendar': [
    {
      target: '.page-header',
      title: 'Posting Calendar',
      body: 'Plan and schedule social media posts — FB, IG, TikTok, and content reminders — for the whole team in one calendar.',
      position: 'bottom',
    },
    {
      target: '.month-nav',
      title: 'Switch Months',
      body: 'Use the arrows to move between months. The calendar and reminder list below update to show that month\'s scheduled posts.',
      position: 'bottom-left',
    },
    {
      target: '.entry-panel',
      title: 'Schedule a Post',
      body: 'Fill in the title, platform, description, date, time, and status (Planned → Design → Approval → Scheduled → Posted), then click + Add.',
      position: 'bottom',
    },
    {
      target: '.calendar-wrap',
      title: 'Calendar View',
      body: 'Each day cell shows the posts scheduled for that date, colour-coded by platform. Click an event pill to load it into the form for editing.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'All Reminders',
      body: 'Below the calendar, all reminders appear as a paginated table sorted by date. Use the search and platform filter above to narrow the list.',
      position: 'bottom',
    },
  ],

  'marketing-email': [
    {
      target: '.page-header-row',
      title: 'Email Marketing',
      body: 'Build and send email campaigns to your contacts, then track how they perform — all from this one workspace.',
      position: 'bottom',
    },
    {
      target: '.tab-bar',
      title: 'Six Sections',
      body: 'Dashboard (performance at a glance), Campaigns (create & send), Contacts (your mailing audience), Lists (audience segments), Templates (reusable designs), and Analytics (open & click rates). Each section has its own create button in the top-right — New Campaign, Add Contact, New List, or New Template.',
      position: 'bottom',
    },
    {
      target: '.stat-grid',
      title: 'Dashboard Stats',
      body: 'The Dashboard opens first with headline numbers — total campaigns, contacts, and average open and click rates — plus your most recent campaigns below.',
      position: 'bottom',
    },
  ],

  profile: [
    {
      target: '.profile-hero',
      title: 'Your Profile',
      body: 'Your avatar, name, job title, and assigned roles at a glance.',
      position: 'bottom',
    },
    {
      target: '.card--accent',
      title: 'Personal Information',
      body: 'Update your name, phone number, and job title here, then click Save.',
      position: 'bottom',
    },
    {
      target: '.col-side',
      title: 'Security & Sessions',
      body: 'Change your password, review your account details, and see every device currently logged into your account — revoke any session you don\'t recognise.',
      position: 'bottom',
    },
  ],

  // ── Settings ───────────────────────────────────────────────────────────────
  settings: [
    {
      target: '.page-header',
      title: 'Settings',
      body: 'Customise how the application looks and behaves for you — and, if you\'re an admin, reach the system management tools.',
      position: 'bottom',
    },
    {
      target: '.settings-nav',
      title: 'Settings Sections',
      body: 'Switch between Appearance, timezone, and notification preferences here. Admins also see an Admin section linking to user management, lookups, and system configuration. The profile card at the top jumps straight to your account.',
      position: 'right',
    },
    {
      target: '.settings-content',
      title: 'Your Preferences',
      body: 'Each section\'s options appear in this panel. Pick your theme, set your timezone, toggle notifications, then click Save Changes to apply.',
      position: 'bottom',
    },
  ],
};

// ─── Module-level shared state ────────────────────────────────────────────────
const active        = ref(false);
const currentIndex  = ref(0);
const _activeSteps  = ref(TOUR_STEPS);

export function useTour() {
  const activeSteps = _activeSteps;
  const currentStep = computed(() => _activeSteps.value[currentIndex.value] ?? null);
  const isFirst     = computed(() => currentIndex.value === 0);
  const isLast      = computed(() => currentIndex.value === _activeSteps.value.length - 1);

  function hasSeen() {
    return localStorage.getItem(STORAGE_KEY) === 'true';
  }

  function markSeen() {
    localStorage.setItem(STORAGE_KEY, 'true');
  }

  // Pass a route name to run the page-specific tour; omit to run the global app tour.
  function start(routeName) {
    const pageTour = routeName && PAGE_TOURS[routeName];
    const steps = pageTour || TOUR_STEPS;
    // Drop steps whose target isn't currently rendered (e.g. booking bars when
    // there are no bookings, or the staged section when nothing is staged) —
    // otherwise the tooltip floats mispositioned over empty space.
    const visible = steps.filter((s) => !s.target || document.querySelector(s.target));
    _activeSteps.value = visible.length ? visible : steps;
    currentIndex.value = 0;
    active.value = true;
  }

  function next() {
    if (isLast.value) finish();
    else currentIndex.value++;
  }

  function prev() {
    if (!isFirst.value) currentIndex.value--;
  }

  function skip() {
    markSeen();
    active.value = false;
  }

  function finish() {
    markSeen();
    active.value = false;
  }

  return { active, currentIndex, currentStep, isFirst, isLast, activeSteps, start, next, prev, skip, finish, hasSeen };
}
