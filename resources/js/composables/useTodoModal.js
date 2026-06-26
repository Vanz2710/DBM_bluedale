import { ref } from 'vue';

// Singleton state so any component (notification bell, reminders page, …) can
// pop the To-Do detail modal over the current page without navigating away.
const openId = ref(null);

export function useTodoModal() {
  function open(id) { openId.value = id; }
  function close()  { openId.value = null; }
  return { openId, open, close };
}
