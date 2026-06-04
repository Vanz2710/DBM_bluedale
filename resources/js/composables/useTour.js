import { ref, computed } from 'vue';

const STORAGE_KEY = 'crm_tour_seen';

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

// Module-level shared state — one tour instance across the app
const active       = ref(false);
const currentIndex = ref(0);

export function useTour() {
  const currentStep = computed(() => TOUR_STEPS[currentIndex.value] ?? null);
  const isFirst     = computed(() => currentIndex.value === 0);
  const isLast      = computed(() => currentIndex.value === TOUR_STEPS.length - 1);

  function hasSeen() {
    return localStorage.getItem(STORAGE_KEY) === 'true';
  }

  function markSeen() {
    localStorage.setItem(STORAGE_KEY, 'true');
  }

  function start() {
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

  return { active, currentIndex, currentStep, isFirst, isLast, start, next, prev, skip, finish, hasSeen };
}
