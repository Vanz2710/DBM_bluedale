<template>
  <div class="page">
    <div class="page-banner">
      <h1>Performance Targets</h1>
      <p>Set weekly task targets per user</p>
    </div>

    <div class="card">
      <div class="form-group">
        <label>Select User</label>
        <select v-model="selectedUserId" @change="loadTargets">
          <option value="">— Choose a user —</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>

      <LoadingSpinner v-if="loadingTargets" />

      <div v-else-if="selectedUserId && tasks.length > 0" class="targets-section">
        <div v-if="saved" class="success-box">✔ Targets saved successfully.</div>
        <div v-if="saveError" class="error-box">{{ saveError }}</div>

        <table>
          <thead>
            <tr>
              <th>Task</th>
              <th class="col-target">Weekly Target</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="task in tasks" :key="task.id">
              <td>{{ task.name }}</td>
              <td>
                <input
                  type="number"
                  min="0"
                  max="9999"
                  class="target-input"
                  v-model.number="targetMap[task.id]"
                  placeholder="0"
                >
              </td>
            </tr>
          </tbody>
        </table>

        <div class="btn-row">
          <button class="btn btn-save" @click="saveTargets" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save Targets' }}
          </button>
        </div>
      </div>

      <div v-else-if="selectedUserId && tasks.length === 0" class="loading-msg">
        No tasks found. Add tasks in the Admin Panel first.
      </div>

      <div v-else-if="!selectedUserId" class="empty-hint">
        Select a user to view and edit their performance targets.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api.js';
import LoadingSpinner from '../components/LoadingSpinner.vue';

const users         = ref([]);
const tasks         = ref([]);
const selectedUserId = ref('');
const targetMap     = ref({});   // { task_id: weekly_target }
const loadingTargets = ref(false);
const saving        = ref(false);
const saved         = ref(false);
const saveError     = ref('');

async function loadLookups() {
  const res = await api.get('/v1/lookups');
  users.value = res.data.users ?? [];
  tasks.value = res.data.tasks ?? [];
}

async function loadTargets() {
  if (!selectedUserId.value) { targetMap.value = {}; return; }
  loadingTargets.value = true;
  saved.value = false;
  saveError.value = '';
  try {
    // Initialize all tasks to 0
    const map = {};
    for (const t of tasks.value) map[t.id] = 0;

    const res = await api.get(`/v1/performance/targets/${selectedUserId.value}`);
    for (const t of (res.data.data ?? [])) {
      map[t.task_id] = t.weekly_target;
    }
    targetMap.value = map;
  } finally {
    loadingTargets.value = false;
  }
}

async function saveTargets() {
  saving.value  = true;
  saved.value   = false;
  saveError.value = '';
  try {
    await api.put(`/v1/performance/targets/${selectedUserId.value}`, {
      tasks:         tasks.value.map(t => ({ id: t.id, name: t.name })),
      weekly_target: tasks.value.map(t => targetMap.value[t.id] ?? 0),
    });
    saved.value = true;
    setTimeout(() => { saved.value = false; }, 3000);
  } catch (e) {
    saveError.value = e.response?.data?.message ?? 'Failed to save targets.';
  } finally {
    saving.value = false;
  }
}

onMounted(loadLookups);
</script>

<style scoped>
.page { padding: 28px 32px; max-width: 680px; }
.page-banner {
  background: linear-gradient(135deg, #1a2f4a, #7c3aed);
  border-radius: 10px; padding: 20px 28px; margin-bottom: 20px; color: white;
}
.page-banner h1 { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
.page-banner p  { font-size: 13px; opacity: 0.8; margin: 0; }
.card { background: var(--surface); border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); padding: 28px 32px; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px; }
.form-group select {
  width: 100%; height: 40px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: 13px; color: var(--text-1); outline: none; background: var(--surface);
}
.form-group select:focus { border-color: var(--primary); }

.loading-msg { text-align: center; padding: 32px; color: var(--text-3); }
.empty-hint { text-align: center; padding: 32px; color: var(--text-3); font-size: 14px; }

.targets-section table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.targets-section thead th {
  background: var(--app-bg); color: var(--text-2); font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.7px; padding: 10px 14px;
  border-bottom: 2px solid var(--border); text-align: left;
}
.col-target { width: 140px; text-align: center; }
.targets-section tbody td { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-1); }
.targets-section tbody tr:last-child td { border-bottom: none; }
.targets-section tbody tr:hover { background: var(--primary-soft); }

.target-input {
  width: 90px; height: 36px; padding: 0 10px; border: 1.5px solid var(--border);
  border-radius: 7px; font-size: 13px; text-align: center; outline: none;
}
.target-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

.btn-row { display: flex; justify-content: flex-end; }
.btn { height: 42px; padding: 0 24px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; }
.btn-save { background: var(--primary); color: var(--primary-on); }
.btn-save:disabled { background: var(--text-3); cursor: not-allowed; }

.success-box { background: #dcfce7; color: #166534; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; font-weight: 600; }
.error-box { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }

@media (max-width: 768px) {
  .page { padding: 16px 12px; }
  .card { padding: 20px 16px; }
}
</style>
