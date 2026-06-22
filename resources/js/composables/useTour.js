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
    target: '[data-tour="nav-crm-contacts"]',
    title: 'Contacts',
    body: 'The heart of the CRM. Add companies, assign owners, log calls and emails, schedule todos and follow-ups, and track deals — all from one contact record.',
    position: 'right',
  },
  {
    target: '[data-tour="nav-tasks"]',
    title: 'Performance & Tasks',
    body: 'Track your todos, follow-ups, and KPI targets. Monitor how you and your team are performing against goals over any time period.',
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
    title: 'Reminders & Alerts',
    body: 'Your notification bell shows overdue items, tasks due today, upcoming deadlines, and system alerts. The red badge tells you how many need attention.',
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
      body: 'Narrow down contacts by date added, company name, assigned user, status, type, category, or area. Use Export to download the filtered list as an Excel file.',
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
      body: 'Narrow down contacts by date added, company name, assigned user, status, type, category, or area. Use Export to download the filtered list as an Excel file.',
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
      body: 'The banner shows the company name, classification tags (status, type, industry, category), and a quick summary of how many tasks, follow-ups, and forecasts are on record.',
      position: 'bottom',
    },
    {
      target: '[data-tour="contact-head-actions"]',
      title: 'Quick Actions',
      body: 'Use these buttons to add a new task, create a forecast entry, edit the contact details, or delete the record entirely.',
      position: 'bottom-left',
    },
    {
      target: '[data-tour="contact-tasks-card"]',
      title: 'Tasks & Follow-Ups',
      body: 'Log and track tasks directly from this section. Each task row shows the due date, type, and status. Click the follow-up count on any row to jump to that task\'s follow-ups.',
      position: 'bottom',
    },
  ],

  todos: [
    {
      target: '.page-head',
      title: 'To Do List',
      body: 'All tasks across every contact in one place. Tasks can also be created here without being tied to a specific contact.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter Tasks',
      body: 'Filter by date range, company name, assigned user, or completion status. Hit Search to apply — Clear removes all active filters.',
      position: 'bottom',
    },
    {
      target: '[data-tour="add-todo-btn"]',
      title: 'Add a Task',
      body: 'Click here to create a new task. Choose a contact, task type, due date, and an optional remark. It will appear here and on the linked contact\'s record.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Task Records',
      body: 'Each row shows the due date, status, company, and task type. The Follow-Ups column shows how many follow-ups are logged — click any row to edit or complete it.',
      position: 'bottom',
    },
  ],

  followups: [
    {
      target: '.page-head',
      title: 'Follow-Ups',
      body: 'Follow-ups are actions logged against tasks. This page consolidates all follow-up records across every contact so you can track outcomes in one view.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Date Filters',
      body: 'Switch between Date Range (specific from/to dates) or Month Range (whole months at a time). Use the User filter to browse another team member\'s follow-ups.',
      position: 'bottom',
    },
    {
      target: '[data-tour="add-followup-btn"]',
      title: 'Add a Follow-Up',
      body: 'Click here to log a new follow-up. Select the contact, choose the task it belongs to, then set the follow-up date and completion status.',
      position: 'bottom-left',
    },
    {
      target: '.table-wrap',
      title: 'Follow-Up Records',
      body: 'Each row shows the follow-up date, linked task, company, and status. Use the Edit button to update details or mark it as completed.',
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

  forecasts: [
    {
      target: '.page-head',
      title: 'Forecasts',
      body: 'Track your sales pipeline. Each forecast entry links to a contact and captures the product, quantity, value, and expected close date.',
      position: 'bottom',
    },
    {
      target: '.toolbar',
      title: 'Filter Forecasts',
      body: 'Filter by date range, user, status, or product type. Use Export to download the filtered forecast data.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Forecast Records',
      body: 'Each row is a deal in your pipeline. Status can be Open, Won, or Lost. Click a row to edit the forecast or update its outcome.',
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
      title: 'Reminders',
      body: 'A consolidated view of all your overdue, today, and upcoming tasks and follow-ups. Use this as your daily action list.',
      position: 'bottom',
    },
    {
      target: '.table-wrap',
      title: 'Reminder Items',
      body: 'Each item links back to the contact it belongs to. Click the company name to jump straight to the contact record and take action.',
      position: 'bottom',
    },
  ],

  // Admin tour — all steps target always-visible elements (tab buttons, not tab content)
  'dept-tasks-admin': [
    {
      target: '.tab-bar',
      title: 'Task Manager — Five Views',
      body: 'You have five views: Board (kanban drag-and-drop), Dashboard (stats & charts), Table (searchable list), This Week (weekly report), and History (completed log). Click any tab to switch.',
      position: 'bottom',
    },
    {
      target: '.page-header-actions',
      title: 'Create a New Task',
      body: 'Click New Task to assign work. Set the title, department, assignee, priority (Low / Medium / High / Critical), due date, and whether the task requires approval before it can be marked complete.',
      position: 'bottom-left',
    },
    {
      target: '[data-tour="deptask-tab-board"]',
      title: 'Board — Kanban View',
      body: 'The Board tab shows tasks as draggable cards across five columns: Pending → In Progress → Waiting Approval → Completed → Cancelled. Drag a card to change its status. Overdue cards are flagged in red. Click any card to view details, add comments, or attach files.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-table"]',
      title: 'Table — Search & Filter',
      body: 'The Table tab shows a searchable, paginated list of every task. Filter by department, status, priority, or assignee simultaneously and click any column header to sort. Hit Print to export a formatted A4 report of the current filtered results.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-dashboard"]',
      title: 'Dashboard — Stats at a Glance',
      body: 'The Dashboard tab shows live counts: total, overdue, in progress, completed, waiting approval, and cancelled. Click any stat card to jump to a filtered board showing those tasks. Charts below break down by department, status, and weekly completion rate.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-weekly"]',
      title: 'This Week — Outstanding Report',
      body: 'This Week generates a printable report of all outstanding tasks grouped by department. Use the week arrows to move between weeks and filter by assignee for individual workload reviews. Click Print A4 to export for team meetings.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-reports"]',
      title: 'History — Completed Task Log',
      body: 'The History tab lists all completed and cancelled tasks with their resolution dates. Filter by department or date range to audit past work and track team output over time.',
      position: 'bottom',
    },
  ],

  // User tour — steps 2–4 target mywork content (default tab); step 5 targets the board tab button
  'dept-tasks-user': [
    {
      target: '.tab-bar',
      title: 'Your Task Manager',
      body: 'You have two views: List (your personal tasks sorted by urgency) and Board (the team kanban for reference). The List view is your day-to-day working surface.',
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
      body: 'Tasks are grouped into four buckets: Overdue (action immediately), Due Today, This Week, and Upcoming. A Done section at the bottom collects completed tasks. Click any task row to open the full detail view.',
      position: 'bottom',
    },
    {
      target: '.mw-action',
      title: 'Advance a Task',
      body: 'Each task row has a quick action button. Hit Start to begin a Pending task. When done, hit Complete — or Submit if the task requires approval first. It then moves to Awaiting Approval until an admin reviews it.',
      position: 'bottom',
    },
    {
      target: '[data-tour="deptask-tab-board"]',
      title: 'Board View',
      body: 'Switch to the Board tab to see all team tasks organised across status columns. You can view task details here, but task creation and status changes are managed by your admin.',
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
      body: 'When you use Save as Draft + Print PDF during product registration, the site is held here as a draft. Once the client approves, click Confirm to add it to the active availability list. Click Discard to remove it without saving.',
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
    _activeSteps.value = pageTour || TOUR_STEPS;
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
