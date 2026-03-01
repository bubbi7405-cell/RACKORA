<template>
  <div class="ma-container">
    <!-- HEADER -->
    <div class="ma-header">
      <div class="ma-title-row">
        <div>
          <h2 class="ma-title">Marketing Architect</h2>
          <p class="ma-subtitle">Growth Engine — Configure campaign mechanics and reputation thresholds.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchCampaigns" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync
           </button>
           <button @click="saveCampaigns" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy Growth Logic
           </button>
        </div>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="ma-grid">
      <div v-for="(camp, id) in campaigns" :key="id" class="campaign-card">
        <div class="card-glow" :style="{ background: getCampaignColor(id) }"></div>
        <div class="campaign-header">
          <div class="campaign-meta">
            <h3 class="campaign-name">{{ camp.name }}</h3>
            <span class="campaign-id">{{ id }}</span>
          </div>
          <div class="card-actions">
            <button @click="editCampaign(id)" class="action-btn edit">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button @click="confirmDelete(id)" class="action-btn delete">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </div>

        <div class="stats-grid">
          <div class="m-stat cost">
            <span class="m-label">Activation Cost</span>
            <span class="m-val">${{ camp.cost.toLocaleString() }}</span>
          </div>
          <div class="m-stat duration">
            <span class="m-label">Runtime</span>
            <span class="m-val">{{ formatDuration(camp.duration) }}</span>
          </div>
          <div class="m-stat effectiveness">
            <span class="m-label">Effectiveness</span>
            <span class="m-val">{{ camp.effectiveness }}x</span>
          </div>
          <div class="m-stat rep">
            <span class="m-label">Reputation Gain</span>
            <span class="m-val">+{{ camp.reputation_gain }}</span>
          </div>
        </div>

        <div class="requirement-bar">
          <div class="req-track">
            <div class="req-fill" :style="{ width: Math.min(100, camp.min_reputation) + '%' }"></div>
          </div>
          <span class="req-label">Required Reputation: {{ camp.min_reputation }}</span>
        </div>
      </div>

      <!-- ADD CARD -->
      <div class="campaign-card add-card" @click="openNewModal">
        <div class="add-icon">+</div>
        <span class="add-text">Initialize Campaign Template</span>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="ma-modal glass-effect animate-popup">
        <div class="modal-header">
           <div>
              <h3 class="modal-title font-black italic">{{ editingId ? 'Tune Strategy' : 'New Campaign Vector' }}</h3>
              <p class="modal-subtitle">Define market impact and resource requirements.</p>
           </div>
           <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Template Identifier (Key)</label>
              <input v-model="formId" :disabled="editingId" placeholder="e.g. viral_tiktok" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Campaign Name</label>
              <input v-model="form.name" placeholder="e.g. Viral Outreach" class="modal-input" />
            </div>
            
            <div class="form-group span-2">
              <label>Cost (USD)</label>
              <input v-model.number="form.cost" type="number" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Duration (Minutes)</label>
              <input v-model.number="form.duration" type="number" class="modal-input" />
            </div>

            <div class="form-group">
              <label>Effect Mod</label>
              <input v-model.number="form.effectiveness" type="number" step="0.1" class="modal-input" />
            </div>
            <div class="form-group">
              <label>Rep Gain</label>
              <input v-model.number="form.reputation_gain" type="number" step="0.1" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Minimum Rep Required</label>
              <input v-model.number="form.min_reputation" type="number" class="modal-input" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Commit Template</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const loading = ref(true);
const campaigns = ref({});
const hasChanges = ref(false);

const fetchCampaigns = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.configs || res.data?.configs;
    let raw = null;
    
    for (const group of Object.values(configs)) {
        const item = group.find(c => c.key === 'marketing_campaigns');
        if (item) { raw = item.value; break; }
    }
    
    if (raw) {
      campaigns.value = raw;
      hasChanges.value = false;
    }
  } catch (e) {
    addToast('Market analysis failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveCampaigns = async () => {
  setGlobalLoading(true);
  try {
    await api.post('/admin/configs/update', {
      key: 'marketing_campaigns',
      value: campaigns.value,
      comment: 'Strategic market shift.'
    });
    addToast('Growth vectors synchronized.', 'success');
    hasChanges.value = false;
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

const formatDuration = (min) => {
    if (min < 60) return `${min}m`;
    const hours = Math.floor(min / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    return `${days}d ${hours % 24}h`;
};

const getCampaignColor = (id) => {
    if (id.includes('blast')) return '#10b981';
    if (id.includes('social')) return '#3b82f6';
    if (id.includes('influencer')) return '#8b5cf6';
    if (id.includes('billboard')) return '#f59e0b';
    if (id.includes('tv') || id.includes('super')) return '#ef4444';
    return '#3f3f46';
};

// MODAL
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const form = ref({
    name: '',
    cost: 1000,
    duration: 60,
    effectiveness: 1.0,
    reputation_gain: 1.0,
    min_reputation: 0
});

const openNewModal = () => {
    editingId.value = null;
    formId.value = '';
    form.value = { name: '', cost: 1000, duration: 60, effectiveness: 1.0, reputation_gain: 1.0, min_reputation: 0 };
    showModal.value = true;
};

const editCampaign = (id) => {
    editingId.value = id;
    formId.value = id;
    form.value = JSON.parse(JSON.stringify(campaigns.value[id]));
    showModal.value = true;
};

const closeModal = () => showModal.value = false;

const applyChanges = () => {
    if (!formId.value || !form.value.name) return addToast('Protocol missing ID or Name.', 'error');
    campaigns.value[formId.value] = JSON.parse(JSON.stringify(form.value));
    hasChanges.value = true;
    showModal.value = false;
    addToast(`Template ${formId.value} optimized.`, 'success');
};

const confirmDelete = (id) => {
  requestConfirm(`Redact campaign template '${id}'? Existing active campaigns will persist until sunset.`, () => {
    delete campaigns.value[id];
    hasChanges.value = true;
    addToast(`${id} purged from strategy.`, 'info');
  });
};

onMounted(fetchCampaigns);
</script>

<style scoped>
.ma-container { display: flex; flex-direction: column; gap: 32px; }

.ma-header { display: flex; flex-direction: column; gap: 24px; }
.ma-title-row { display: flex; justify-content: space-between; align-items: start; }
.ma-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.ma-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }

.ma-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

.campaign-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 24px; padding: 24px;
  position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex; flex-direction: column; gap: 24px;
}
.campaign-card:hover { border-color: #27272a; transform: translateY(-4px); }

.card-glow { position: absolute; top: 0; right: 0; width: 60px; height: 60px; filter: blur(40px); opacity: 0.15; }

.campaign-header { display: flex; justify-content: space-between; align-items: start; }
.campaign-name { font-size: 1.1rem; font-weight: 900; color: white; margin: 0; font-style: italic; letter-spacing: -0.02em; }
.campaign-id { font-size: 0.6rem; color: #3f3f46; font-family: var(--sys-font-mono); font-weight: 800; text-transform: uppercase; }

.card-actions { display: flex; gap: 8px; }
.action-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #18181b; background: #111; color: #3f3f46; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.action-btn.edit:hover { color: #3b82f6; border-color: #1e3a5f; background: #0c1222; }
.action-btn.delete:hover { color: #ef4444; border-color: #450a0a; background: #2a0a0a; }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-stat { padding: 12px; background: #050505; border-radius: 12px; display: flex; flex-direction: column; gap: 4px; }
.m-label { font-size: 0.55rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.m-val { font-size: 0.85rem; font-weight: 900; font-family: var(--sys-font-mono); color: #a1a1aa; }
.cost .m-val { color: #4ade80; }
.effectiveness .m-val { color: #3b82f6; }

.requirement-bar { display: flex; flex-direction: column; gap: 8px; }
.req-track { height: 4px; background: #18181b; border-radius: 2px; overflow: hidden; }
.req-fill { height: 100%; background: #3b82f6; box-shadow: 0 0 10px #3b82f6; }
.req-label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }

/* ADD CARD */
.add-card { border-style: dashed; border-color: #18181b; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; gap: 12px; }
.add-card:hover { border-color: #3b82f6; background: #0c1222; }
.add-icon { font-size: 2rem; color: #18181b; font-weight: 100; line-height: 1; }
.add-text { font-size: 0.7rem; font-weight: 900; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.1em; }
.add-card:hover .add-icon, .add-card:hover .add-text { color: #3b82f6; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(12px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.ma-modal { width: 620px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 32px; padding: 40px; }
.close-btn { background: #111; border: 1px solid #222; color: #3f3f46; width: 32px; height: 32px; border-radius: 12px; cursor: pointer; }
.modal-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 32px; }
.modal-title { font-size: 1.5rem; color: white; margin: 0; }
.modal-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; margin-top: 4px; }

.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }
.modal-input { background: #050505; border: 1px solid #18181b; padding: 12px 16px; border-radius: 14px; color: white; font-size: 0.85rem; font-weight: 600; outline: none; transition: border-color 0.2s; }
.modal-input:focus { border-color: #3b82f6; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; }

.modal-footer { display: flex; gap: 16px; margin-top: 40px; }
.modal-footer button { flex: 1; }
</style>
