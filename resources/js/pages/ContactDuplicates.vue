<template>
  <div class="page">
    <Transition name="toast">
      <div v-if="toast" class="toast-msg" role="status">{{ toast }}</div>
    </Transition>

    <div class="page-header">
      <div>
        <h1 class="page-title">Duplicate Contacts</h1>
        <p class="page-subtitle">{{ groupCount }} group(s) of contacts share the same name. Keep one and merge the rest.</p>
      </div>
      <button class="btn-refresh" @click="load" :disabled="loading">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Refresh
      </button>
    </div>

    <div v-if="loading" class="loading-msg">Scanning for duplicates…</div>

    <div v-else-if="groups.length === 0" class="empty-card">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <div class="empty-title">No duplicates found</div>
      <div class="empty-sub">All contact names are unique.</div>
    </div>

    <div v-else>
      <div v-for="group in groups" :key="group[0].name" class="group-card">
        <div class="group-head">
          <span class="group-name">{{ group[0].name }}</span>
          <span class="group-count-chip">{{ group.length }} contacts</span>
        </div>

        <table class="dup-table">
          <thead>
            <tr>
              <th>Keep</th>
              <th>ID</th>
              <th>Owner</th>
              <th>Status</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in group"
              :key="c.id"
              :class="{ 'row-keep': keepIds[group[0].name] === c.id, 'row-merge': keepIds[group[0].name] && keepIds[group[0].name] !== c.id }"
            >
              <td class="keep-col">
                <input
                  type="radio"
                  :name="'keep-' + group[0].name"
                  :value="c.id"
                  v-model="keepIds[group[0].name]"
                >
              </td>
              <td class="id-col">#{{ c.id }}</td>
              <td>{{ c.user?.name ?? '—' }}</td>
              <td>{{ c.status?.name ?? '—' }}</td>
              <td class="muted">{{ c.phone || '—' }}</td>
              <td class="muted">{{ c.email || '—' }}</td>
              <td class="muted">{{ fmtDate(c.created_at) }}</td>
            </tr>
          </tbody>
        </table>

        <div class="group-foot">
          <p class="merge-hint" v-if="keepIds[group[0].name]">
            Will keep <strong>#{{ keepIds[group[0].name] }}</strong> and merge
            {{ group.length - 1 }} other(s) into it — all todos, deals, projects and forecasts will be transferred.
          </p>
          <p class="merge-hint muted" v-else>Select the contact to keep, then click Merge.</p>
          <button
            class="btn-merge"
            :disabled="!keepIds[group[0].name] || merging[group[0].name]"
            @click="merge(group)"
          >
            {{ merging[group[0].name] ? 'Merging…' : 'Merge Group' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm merge modal -->
    <Teleport to="body">
      <div v-if="confirmModal.open" class="modal-backdrop">
        <div class="confirm-modal" role="dialog" aria-modal="true">
          <div class="confirm-head">
            <h2>Confirm Merge</h2>
            <button class="close-btn" @click="confirmModal.open = false">&times;</button>
          </div>
          <div class="confirm-body">
            <svg class="warn-icon" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="1" fill="#f59e0b" stroke="none"/></svg>
            <p>
              Keep <strong>{{ confirmModal.keepName }} (#{{ confirmModal.keepId }})</strong> and permanently delete
              {{ confirmModal.mergeCount }} duplicate(s)?<br>
              <span class="confirm-sub">All their todos, deals, projects and forecasts will be moved to the kept contact. This cannot be undone.</span>
            </p>
          </div>
          <div class="confirm-foot">
            <button class="btn-cancel" @click="confirmModal.open = false">Cancel</button>
            <button class="btn-confirm-merge" :disabled="confirmModal.loading" @click="doMerge">
              {{ confirmModal.loading ? 'Merging…' : 'Yes, Merge' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../api.js';

const groups    = ref([]);
const keepIds   = reactive({});
const merging   = reactive({});
const loading   = ref(false);
const groupCount = ref(0);
const toast     = ref('');

const confirmModal = reactive({ open: false, group: null, keepId: null, keepName: '', mergeCount: 0, loading: false });

function showToast(msg) {
  toast.value = msg;
  setTimeout(() => { toast.value = ''; }, 3000);
}

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function load() {
  loading.value = true;
  try {
    const res = await api.get('/v1/contacts/find-duplicates');
    groups.value     = res.data.data;
    groupCount.value = res.data.group_count;
    // Reset selections for new data
    Object.keys(keepIds).forEach(k => delete keepIds[k]);
    groups.value.forEach(g => {
      // Auto-select oldest (first) contact as default keep
      keepIds[g[0].name] = g[0].id;
    });
  } finally {
    loading.value = false;
  }
}

function merge(group) {
  const keepId = keepIds[group[0].name];
  if (!keepId) return;
  const keep = group.find(c => c.id === keepId);
  confirmModal.group      = group;
  confirmModal.keepId     = keepId;
  confirmModal.keepName   = keep?.name ?? '';
  confirmModal.mergeCount = group.length - 1;
  confirmModal.loading    = false;
  confirmModal.open       = true;
}

async function doMerge() {
  const { group, keepId } = confirmModal;
  if (!group || !keepId) return;
  confirmModal.loading = true;
  const name = group[0].name;
  merging[name] = true;
  try {
    const mergeIds = group.filter(c => c.id !== keepId).map(c => c.id);
    await api.post('/v1/contacts/merge', { keep_id: keepId, merge_ids: mergeIds });
    groups.value = groups.value.filter(g => g[0].name !== name);
    groupCount.value = groups.value.length;
    delete keepIds[name];
    delete merging[name];
    confirmModal.open = false;
    showToast(`Merged ${mergeIds.length} duplicate(s) into #${keepId}.`);
  } catch (e) {
    showToast(e.response?.data?.message ?? 'Merge failed.');
    confirmModal.loading = false;
  } finally {
    merging[name] = false;
  }
}

onMounted(load);
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 1100px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; }
.page-title  { font-size: 28px; font-weight: 800; color: var(--text-1); margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }

.btn-refresh {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-2); font-size: 13px; cursor: pointer; white-space: nowrap;
}
.btn-refresh:hover { background: var(--surface-2); }
.btn-refresh:disabled { opacity: .5; cursor: not-allowed; }

.loading-msg { color: var(--text-3); padding: 48px; text-align: center; }

.empty-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 12px; padding: 64px; background: var(--surface); border-radius: var(--radius);
  border: 1px solid var(--border);
}
.empty-title { font-size: 16px; font-weight: 700; color: var(--text-1); }
.empty-sub   { font-size: 13px; color: var(--text-3); }

.group-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); margin-bottom: 20px; overflow: hidden;
}
.group-head {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 18px; border-bottom: 1px solid var(--border);
  background: var(--surface-2);
}
.group-name { font-size: 15px; font-weight: 700; color: var(--text-1); }
.group-count-chip {
  font-size: 11px; font-weight: 600; padding: 2px 8px;
  border-radius: 999px; background: var(--primary); color: #fff;
}

.dup-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dup-table th {
  padding: 9px 14px; text-align: left; font-size: 11px; font-weight: 600;
  color: var(--text-3); background: var(--surface-2); border-bottom: 1px solid var(--border);
  text-transform: uppercase; letter-spacing: .4px;
}
.dup-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text-2); vertical-align: middle; }
.dup-table tr:last-child td { border-bottom: none; }
.dup-table .keep-col { width: 48px; }
.dup-table .id-col   { width: 60px; font-size: 12px; color: var(--text-3); }
.dup-table .muted    { color: var(--text-3); }

.row-keep { background: color-mix(in srgb, var(--primary) 6%, transparent); }
.row-merge { opacity: .65; }

.group-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 12px 18px; background: var(--surface-2); border-top: 1px solid var(--border);
}
.merge-hint { font-size: 12.5px; color: var(--text-2); margin: 0; }
.merge-hint.muted { color: var(--text-3); }

.btn-merge {
  padding: 7px 18px; border-radius: var(--radius-sm); border: none;
  background: var(--primary); color: #fff; font-size: 13px; font-weight: 600;
  cursor: pointer; white-space: nowrap;
}
.btn-merge:hover:not(:disabled) { opacity: .88; }
.btn-merge:disabled { opacity: .45; cursor: not-allowed; }

/* Modal */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 200;
  display: flex; align-items: center; justify-content: center;
}
.confirm-modal {
  background: var(--surface); border-radius: var(--radius-lg);
  border: 1px solid var(--border); width: 440px; max-width: 95vw; overflow: hidden;
}
.confirm-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.confirm-head h2 { font-size: 16px; font-weight: 700; color: var(--text-1); margin: 0; }
.close-btn { background: none; border: none; font-size: 20px; color: var(--text-3); cursor: pointer; line-height: 1; }
.confirm-body { padding: 20px; display: flex; gap: 14px; align-items: flex-start; }
.warn-icon { width: 32px; height: 32px; flex-shrink: 0; margin-top: 2px; }
.confirm-body p { font-size: 13.5px; color: var(--text-2); margin: 0; line-height: 1.6; }
.confirm-sub { font-size: 12px; color: var(--text-3); display: block; margin-top: 6px; }
.confirm-foot {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 14px 20px; border-top: 1px solid var(--border);
}
.btn-cancel {
  padding: 8px 16px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2);
  color: var(--text-2); font-size: 13px; cursor: pointer;
}
.btn-confirm-merge {
  padding: 8px 18px; border-radius: var(--radius-sm); border: none;
  background: #dc2626; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-confirm-merge:hover:not(:disabled) { opacity: .88; }
.btn-confirm-merge:disabled { opacity: .45; cursor: not-allowed; }

/* Toast */
.toast-msg {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: var(--text-1); color: var(--surface); padding: 10px 20px;
  border-radius: var(--radius); font-size: 13px; z-index: 300; white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: opacity .25s, transform .25s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
