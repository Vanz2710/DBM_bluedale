<template>
  <div class="fu-widget">
    <div class="fu-head">
      <div class="fu-title-wrap">
        <span class="fu-title">Upcoming Follow-Ups</span>
        <span class="fu-sub">{{ items.length ? items.length + ' pending this week' : 'Next 7 days' }}</span>
      </div>
      <router-link to="/followups" class="fu-link">View All</router-link>
    </div>

    <div v-if="loading" class="fu-state">Loading…</div>
    <div v-else-if="noAccess" class="fu-state fu-state--muted">No access to follow-up data</div>
    <div v-else-if="!items.length" class="fu-state">No pending follow-ups this week</div>
    <ul v-else class="fu-list">
      <li
        v-for="item in items"
        :key="item.id"
        class="fu-item"
        :class="{ 'fu-item--completing': completing.has(item.id) }"
      >
        <div class="fu-date" :class="dateClass(item.followup_date)">
          {{ fmtDate(item.followup_date) }}
        </div>
        <div class="fu-body">
          <router-link
            v-if="item.contact_id"
            :to="`/contacts/${item.contact_id}`"
            class="fu-name"
          >{{ item.contact_name ?? '—' }}</router-link>
          <span v-else class="fu-name fu-name--plain">{{ item.contact_name ?? '—' }}</span>
          <span v-if="item.action_type" class="fu-tag">{{ item.action_type }}</span>
        </div>
        <button
          class="fu-done"
          title="Mark complete"
          :disabled="completing.has(item.id)"
          @click="markDone(item)"
        >
          <Check :size="13" />
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Check } from 'lucide-vue-next';
import api from '../../api.js';

const loading    = ref(true);
const noAccess   = ref(false);
const items      = ref([]);
const completing = ref(new Set());

function today() {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}

function isoDate(d) {
  return d.toISOString().split('T')[0];
}

function parseDMY(str) {
  if (!str) return null;
  const [d, m, y] = str.split('-');
  return new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
}

function fmtDate(str) {
  const d = parseDMY(str);
  if (!d) return '—';
  const now = today();
  const diff = Math.round((d.getTime() - now.getTime()) / 86400000);
  if (diff < 0)  return 'Overdue';
  if (diff === 0) return 'Today';
  if (diff === 1) return 'Tomorrow';
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

function dateClass(str) {
  const d = parseDMY(str);
  if (!d) return '';
  const now = today();
  const diff = Math.round((d.getTime() - now.getTime()) / 86400000);
  if (diff < 0)   return 'fu-date--overdue';
  if (diff === 0) return 'fu-date--today';
  return '';
}

async function markDone(item) {
  completing.value = new Set([...completing.value, item.id]);
  try {
    await api.patch(`/v1/followups/${item.id}/status`, { status: 'completed' });
    items.value = items.value.filter(i => i.id !== item.id);
  } catch {
    // silently fail — item stays in list
  } finally {
    completing.value = new Set([...completing.value].filter(id => id !== item.id));
  }
}

onMounted(async () => {
  try {
    const from = isoDate(today());
    const to   = isoDate(new Date(today().getTime() + 7 * 86400000));
    const { data } = await api.get('/v1/followups', {
      params: { from_date: from, to_date: to, completion_status: 'pending', per_page: 15 },
    });
    items.value = data.data ?? data ?? [];
  } catch (e) {
    if (e?.response?.status === 403) noAccess.value = true;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.fu-widget {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px;
  overflow: hidden;
}
.fu-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-shrink: 0;
}
.fu-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.fu-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: -0.2px;
}
.fu-sub {
  font-size: 11.5px;
  color: var(--text-3);
}
.fu-link {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s;
  flex-shrink: 0;
}
.fu-link:hover { color: var(--primary-hover); }

.fu-state {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--text-3);
}
.fu-state--muted { opacity: 0.6; }

.fu-list {
  list-style: none;
  margin: 0;
  padding: 0;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
.fu-list::-webkit-scrollbar { width: 6px; }
.fu-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

.fu-item {
  display: grid;
  grid-template-columns: 76px 1fr 32px;
  align-items: center;
  gap: 10px;
  padding: 10px 4px;
  border-bottom: 1px solid var(--border-soft);
  transition: opacity 0.25s;
}
.fu-item:last-child { border-bottom: none; }
.fu-item--completing { opacity: 0.4; pointer-events: none; }

.fu-date {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--text-2);
  white-space: nowrap;
}
.fu-date--overdue { color: var(--danger); }
.fu-date--today   { color: var(--warning, #d97706); }

.fu-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.fu-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-1);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.15s;
}
.fu-name:not(.fu-name--plain):hover { color: var(--primary); }
.fu-name--plain { cursor: default; }

.fu-tag {
  align-self: flex-start;
  font-size: 10.5px;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary-text);
  padding: 1px 7px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.fu-done {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
  flex-shrink: 0;
}
.fu-done:hover:not(:disabled) {
  background: var(--success-soft);
  border-color: var(--success);
  color: var(--success);
}
.fu-done:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
