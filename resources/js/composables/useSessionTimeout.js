import { ref, watch, onUnmounted } from 'vue';

const IDLE_WARN_MS   = 25 * 60 * 1000; // warn at 25 min
const IDLE_LOGOUT_MS = 30 * 60 * 1000; // logout at 30 min
const CHECK_INTERVAL = 10_000;          // poll every 10 s
const STORAGE_KEY    = 'crm_last_activity';

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];

function readLastActivity() {
  const stored = sessionStorage.getItem(STORAGE_KEY);
  // If no stored value or stored value is in the future (clock skew), use now
  const val = stored ? parseInt(stored, 10) : Date.now();
  return Number.isFinite(val) && val <= Date.now() ? val : Date.now();
}

function writeLastActivity(ts) {
  sessionStorage.setItem(STORAGE_KEY, String(ts));
}

export function useSessionTimeout({ isLoggedIn, onTimeout }) {
  const showWarning = ref(false);
  const secondsLeft = ref(300);

  let checkTimer     = null;
  let countdownTimer = null;

  function onActivity() {
    const idle = Date.now() - readLastActivity();

    if (idle >= IDLE_LOGOUT_MS) {
      teardown();
      showWarning.value = false;
      onTimeout();
      return;
    }

    writeLastActivity(Date.now());

    if (showWarning.value) {
      showWarning.value = false;
      clearInterval(countdownTimer);
      countdownTimer = null;
      secondsLeft.value = 300;
    }
  }

  function startCountdown() {
    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
      const remaining = IDLE_LOGOUT_MS - (Date.now() - readLastActivity());
      secondsLeft.value = Math.max(0, Math.ceil(remaining / 1000));
    }, 500);
  }

  function check() {
    const idle = Date.now() - readLastActivity();

    if (idle >= IDLE_LOGOUT_MS) {
      teardown();
      showWarning.value = false;
      onTimeout();
    } else if (idle >= IDLE_WARN_MS && !showWarning.value) {
      showWarning.value = true;
      secondsLeft.value = Math.max(0, Math.ceil((IDLE_LOGOUT_MS - idle) / 1000));
      startCountdown();
    }
  }

  function setup() {
    // Only write lastActivity if there's no stored value (first login in this browser session)
    if (!sessionStorage.getItem(STORAGE_KEY)) {
      writeLastActivity(Date.now());
    } else {
      // Already have a stored value — check immediately in case they were idle during a refresh
      check();
    }
    ACTIVITY_EVENTS.forEach(e => window.addEventListener(e, onActivity, { passive: true }));
    checkTimer = setInterval(check, CHECK_INTERVAL);
  }

  function teardown() {
    ACTIVITY_EVENTS.forEach(e => window.removeEventListener(e, onActivity));
    clearInterval(checkTimer);
    clearInterval(countdownTimer);
    checkTimer     = null;
    countdownTimer = null;
  }

  watch(isLoggedIn, (logged) => {
    if (logged) setup();
    else {
      teardown();
      showWarning.value = false;
      sessionStorage.removeItem(STORAGE_KEY);
    }
  }, { immediate: true });

  onUnmounted(teardown);

  return {
    showWarning,
    secondsLeft,
    stayLoggedIn: onActivity,
  };
}
