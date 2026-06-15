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
    body: 'Plan social media posts, manage email campaigns, track product availability, and maintain your posting calendar — all in one place.',
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
      body: 'Switch between Overview (your KPI summary), Activity (task report), Team (admin cross-user comparison), and Targets (set your KPI goals).',
      position: 'bottom',
    },
    {
      target: '[data-tour="perf-period"]',
      title: 'Period Selector',
      body: 'Choose Week, Month, or Year to see performance data for that period. The KPI cards and target progress update instantly.',
      position: 'bottom',
    },
    {
      target: '[data-tour="perf-kpi-cards"]',
      title: 'KPI Summary',
      body: 'Each card shows your count for that metric in the selected period alongside your target. Cards turn green when you hit the target.',
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
