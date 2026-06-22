import { ref, watch, onUnmounted } from 'vue';

const IDLE_WARN_MS   = 25 * 60 * 1000; // warn at 25 min
const IDLE_LOGOUT_MS = 30 * 60 * 1000; // logout at 30 min
const CHECK_INTERVAL = 10_000;          // poll every 10 s

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'];

export function useSessionTimeout({ isLoggedIn, onTimeout }) {
  const showWarning = ref(false);
  const secondsLeft = ref(300);

  let lastActivity   = Date.now();
  let checkTimer     = null;
  let countdownTimer = null;

  function onActivity() {
    const idle = Date.now() - lastActivity;

    if (idle >= IDLE_LOGOUT_MS) {
      teardown();
      showWarning.value = false;
      onTimeout();
      return;
    }

    lastActivity = Date.now();

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
      const remaining = IDLE_LOGOUT_MS - (Date.now() - lastActivity);
      secondsLeft.value = Math.max(0, Math.ceil(remaining / 1000));
    }, 500);
  }

  function check() {
    const idle = Date.now() - lastActivity;

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
    lastActivity = Date.now();
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
    else { teardown(); showWarning.value = false; }
  }, { immediate: true });

  onUnmounted(teardown);

  return {
    showWarning,
    secondsLeft,
    stayLoggedIn: onActivity,
  };
}
