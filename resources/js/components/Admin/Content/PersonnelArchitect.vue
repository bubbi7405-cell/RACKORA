<template>
  <div class="pa-container">
    <!-- HEADER -->
    <div class="pa-header">
      <div class="pa-title-row">
        <div>
          <h2 class="pa-title">Personnel Architect</h2>
          <p class="pa-subtitle">Human Resources & Logic — Configure the specialized workforce tiers of Rackora.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchTypes" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync Matrix
           </button>
           <button @click="saveTypes" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy HR Manifest
           </button>
        </div>
      </div>

      <!-- TOOLBAR -->
      <div class="pa-toolbar">
        <div class="tool-info">
          <span class="info-label">Current Tiers:</span>
          <span class="info-val">{{ Object.keys(types).length }} Units</span>
        </div>
        <div class="toolbar-spacer"></div>
        <button @click="openNewModal" class="add-type-btn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Initialize New Tier
        </button>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="pa-grid">
      <div v-for="(type, id) in types" :key="id" class="tier-card" :class="{ active: selectedId === id }">
        <div class="tier-accent" :style="{ background: getTierColor(id) }"></div>
        <div class="tier-content">
          <div class="tier-header">
            <div class="tier-meta">
              <h3 class="tier-name">{{ type.name }}</h3>
              <span class="tier-id">{{ id }}</span>
            </div>
            <div class="tier-actions">
              <button @click="editType(id)" class="action-btn edit" title="Modify Tier">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button @click="confirmDelete(id)" class="action-btn delete" title="Decommission Tier">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </div>
          </div>

          <p class="tier-desc">{{ type.description }}</p>

          <div class="tier-stats">
            <div class="t-stat">
              <span class="t-label">Hiring Cost</span>
              <span class="t-val money">${{ type.hiring_cost.toLocaleString() }}</span>
            </div>
            <div class="t-stat">
              <span class="t-label">Base Salary</span>
              <span class="t-val salary">/hr ${{ type.base_salary }}</span>
            </div>
          </div>

          <div class="skill-cloud">
            <span v-for="skill in type.skills" :key="skill" class="skill-tag">{{ skill }}</span>
          </div>

          <div class="effect-box">
             <span class="effect-label">Specialization:</span>
             <span class="effect-val">{{ type.special_effect || 'Standard' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: TIER EDITOR -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="tier-modal glass-effect animate-popup">
        <div class="modal-header">
          <div>
            <h3 class="modal-title font-black italic tracking-tighter">{{ editingId ? 'Modify Personnel Tier' : 'Personnel Induction' }}</h3>
            <p class="modal-subtitle">Define operational parameters for the workforce.</p>
          </div>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Service Identifier (ID)</label>
              <input v-model="formId" :disabled="editingId" placeholder="e.g. quantum_engineer" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Tier Name</label>
              <input v-model="form.name" placeholder="Systems Expert" class="modal-input" />
            </div>
            <div class="form-group span-4">
              <label>Mission Description</label>
              <textarea v-model="form.description" rows="2" class="modal-input" placeholder="Define the core responsibility..."></textarea>
            </div>
            
            <div class="form-group span-2">
              <label>Hiring Bonus ($)</label>
              <input v-model.number="form.hiring_cost" type="number" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Hourly Retention ($)</label>
              <input v-model.number="form.base_salary" type="number" class="modal-input" />
            </div>

            <div class="form-group span-4">
              <label>Skill Matrix (Comma separated)</label>
              <input v-model="skillsInput" placeholder="infrastructure, maintenance, security..." class="modal-input" />
            </div>

            <div class="form-group span-4">
              <label>System Logic Tag</label>
              <select v-model="form.special_effect" class="modal-input">
                <option value="none">Standard Protocol</option>
                <option v-for="e in knownEffects" :key="e" :value="e">{{ e }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Commit Tier Configuration</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject, watch } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const loading = ref(true);
const types = ref({});
const hasChanges = ref(false);
const selectedId = ref(null);

const knownEffects = ['repair_boost', 'churn_reduction', 'security_hardening', 'compliance_speed', 'network_optimization', 'customer_satisfaction_bonus', 'energy_efficiency_monitor'];

const fetchTypes = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.configs || res.data?.configs;
    let raw = null;
    
    for (const group of Object.values(configs)) {
        const item = group.find(c => c.key === 'employee_types');
        if (item) { raw = item.value; break; }
    }
    
    if (raw) {
      types.value = raw;
      hasChanges.value = false;
    }
  } catch (e) {
    addToast('Personnel data link failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveTypes = async () => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'employee_types',
            value: types.value,
            comment: 'Workforce tier recalibration.'
        });
        addToast('HR Manifest successfully synchronized.', 'success');
        hasChanges.value = false;
    } catch (e) {
        addToast(e.message, 'error');
    } finally {
        setGlobalLoading(false);
    }
};

const getTierColor = (id) => {
    if (id.includes('admin')) return '#3b82f6';
    if (id.includes('support')) return '#f59e0b';
    if (id.includes('security')) return '#ef4444';
    if (id.includes('compliance')) return '#10b981';
    if (id.includes('engineer') || id.includes('tech')) return '#8b5cf6';
    return '#3f3f46';
};

// MODAL
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const skillsInput = ref('');
const form = ref({
  name: '',
  description: '',
  hiring_cost: 5000,
  base_salary: 100,
  skills: [],
  special_effect: 'none'
});

watch(skillsInput, (val) => {
    form.value.skills = val.split(',').map(s => s.trim()).filter(s => s !== '');
});

const openNewModal = () => {
  editingId.value = null;
  formId.value = '';
  skillsInput.value = '';
  form.value = {
    name: '',
    description: '',
    hiring_cost: 5000,
    base_salary: 100,
    skills: [],
    special_effect: 'none'
  };
  showModal.value = true;
};

const editType = (id) => {
  const t = types.value[id];
  editingId.value = id;
  formId.value = id;
  form.value = JSON.parse(JSON.stringify(t));
  skillsInput.value = (t.skills || []).join(', ');
  showModal.value = true;
};

const closeModal = () => showModal.value = false;

const applyChanges = () => {
    if (!formId.value || !form.value.name) return addToast('ID and Name are mandatory.', 'error');
    types.value[formId.value] = JSON.parse(JSON.stringify(form.value));
    hasChanges.value = true;
    showModal.value = false;
    addToast(`Protocol updated for tier: ${formId.value}`, 'success');
};

const confirmDelete = (id) => {
    requestConfirm(`Decommission tier '${id}'? Existing units will retain previous logic until liquidated.`, () => {
        delete types.value[id];
        hasChanges.value = true;
        addToast(`${id} removed from manifest.`, 'info');
    });
};

onMounted(fetchTypes);
</script>

<style scoped>
.pa-container { display: flex; flex-direction: column; gap: 32px; }

.pa-header { display: flex; flex-direction: column; gap: 24px; }
.pa-title-row { display: flex; justify-content: space-between; align-items: start; }
.pa-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.pa-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }

.pa-toolbar { display: flex; align-items: center; background: #0a0a0c; border: 1px solid #18181b; padding: 16px 24px; border-radius: 20px; }
.info-label { font-size: 0.6rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; margin-right: 8px; }
.info-val { font-size: 0.8rem; color: #a1a1aa; font-weight: 900; font-style: italic; }
.toolbar-spacer { flex: 1; }
.add-type-btn { display: flex; align-items: center; gap: 8px; background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 12px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; cursor: pointer; }

.pa-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }

.tier-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 24px; padding: 24px;
  position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.tier-card:hover { border-color: #27272a; transform: translateY(-4px); }
.tier-accent { position: absolute; top: 0; left: 0; width: 4px; height: 100%; opacity: 0.8; }

.tier-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px; }
.tier-name { font-size: 1.1rem; font-weight: 900; color: white; margin: 0; font-style: italic; letter-spacing: -0.02em; }
.tier-id { font-size: 0.6rem; color: #3f3f46; font-family: var(--sys-font-mono); font-weight: 800; text-transform: uppercase; }

.tier-actions { display: flex; gap: 8px; }
.action-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #18181b; background: #111; color: #3f3f46; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.action-btn.edit:hover { color: #3b82f6; border-color: #1e3a5f; background: #0c1222; }
.action-btn.delete:hover { color: #ef4444; border-color: #450a0a; background: #2a0a0a; }

.tier-desc { font-size: 0.75rem; color: #71717a; line-height: 1.6; margin-bottom: 20px; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; }

.tier-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.t-stat { padding: 12px; background: #050505; border-radius: 12px; display: flex; flex-direction: column; gap: 4px; }
.t-label { font-size: 0.55rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.t-val { font-size: 0.85rem; font-weight: 900; font-family: var(--sys-font-mono); }
.t-val.money { color: #4ade80; }
.t-val.salary { color: #a1a1aa; }

.skill-cloud { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.skill-tag { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; background: #111; border: 1px solid #18181b; color: #52525b; padding: 4px 10px; border-radius: 99px; }

.effect-box { padding-top: 16px; border-top: 1px solid #18181b; display: flex; align-items: center; gap: 10px; }
.effect-label { font-size: 0.6rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.effect-val { font-size: 0.65rem; color: #3b82f6; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--sys-font-mono); }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(12px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.tier-modal { width: 680px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 32px; padding: 40px; position: relative; }
.close-btn { background: #111; border: 1px solid #222; color: #3f3f46; width: 32px; height: 32px; border-radius: 12px; cursor: pointer; position: absolute; top: 32px; right: 32px; }

.modal-title { font-size: 1.5rem; color: white; margin: 0; }
.modal-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 32px; }

.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }

.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; }
.modal-input { background: #050505; border: 1px solid #18181b; padding: 12px 16px; border-radius: 14px; color: white; font-size: 0.85rem; font-weight: 600; outline: none; transition: border-color 0.2s; }
.modal-input:focus { border-color: #3b82f6; }

.modal-footer { display: flex; gap: 16px; margin-top: 40px; }
.modal-footer button { flex: 1; }
</style>
