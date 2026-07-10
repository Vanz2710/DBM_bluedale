// A corrupted crm_user value must never throw here — many pages read it as
// one of their first synchronous statements, before Vue can render anything,
// so an uncaught error leaves the whole page blank. Self-heal instead of
// crashing (same pattern as App.vue's getStoredUser() and router/index.js's
// setupGuard()).
export function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('crm_user') || 'null');
  } catch {
    localStorage.removeItem('crm_user');
    return null;
  }
}
