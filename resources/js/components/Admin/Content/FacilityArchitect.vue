<template>
  <div class="pra-container">
    <!-- HEADER -->
    <div class="pra-header">
      <div class="pra-title-row">
        <div>
          <h2 class="pra-title">Facility Architect (Infrastructure Nodes)</h2>
          <p class="pra-subtitle">Define physical datacenter rooms, capacity constraints, and physical environment logic.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchRooms" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync
           </button>
           <button @click="saveRooms" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy Node Config
           </button>
        </div>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="pra-grid">
      <div v-for="(room, id) in rooms" :key="id" class="product-card">
        <div class="product-header">
          <div class="product-meta">
            <h3 class="product-name">{{ room.label }}</h3>
            <span class="product-id text-mono">{{ id }}</span>
          </div>
          <div class="tier-badge" :class="'tier-' + getTier(room.required_level)">
            Level {{ room.required_level }}+
          </div>
        </div>

        <div class="requirement-preview mt-2">
          <div class="req-pill">
            <span class="pill-label">RACKS MAX</span>
            <span class="pill-range text-white">{{ room.max_racks }}</span>
          </div>
          <div class="req-pill">
            <span class="pill-label">POWER</span>
            <span class="pill-range text-yellow-500">{{ room.max_power_kw }}kW</span>
          </div>
          <div class="req-pill">
            <span class="pill-label">COOLING</span>
            <span class="pill-range text-blue-400">{{ room.max_cooling_kw }}kW</span>
          </div>
          <div class="req-pill">
            <span class="pill-label">UPLINK</span>
            <span class="pill-range text-green-400">{{ room.bandwidth_gbps }}Gbps</span>
          </div>
        </div>

        <div class="price-strip mt-2">
          <span class="price-label">Unlock / Rent</span>
          <span class="price-val text-sm text-gray-300">${{ (room.unlock_cost || 0).toLocaleString() }} <span class="text-[0.45rem] text-gray-500 uppercase block text-right mt-1">/ ${{ room.rent_per_hour }}/hr</span></span>
        </div>

        <div class="card-footer mt-4">
          <button @click="editRoom(id)" class="edit-link">
            Configure Logic
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
          <button @click="confirmDelete(id)" class="delete-btn">✕</button>
        </div>
      </div>

      <!-- ADD CARD -->
      <div class="product-card add-card" @click="openNewModal">
        <div class="add-icon">+</div>
        <span class="add-text">Initialize Facility Node</span>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="pra-modal glass-effect animate-popup">
        <div class="modal-header">
          <div>
            <h3 class="modal-title font-black italic tracking-tighter">{{ editingId ? 'Optimize Facility' : 'Node Induction' }}</h3>
            <p class="modal-subtitle">Configure spatial capacity and power grids.</p>
          </div>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Node ID (Internal JSON Key)</label>
              <input v-model="formId" :disabled="editingId" placeholder="e.g. bunker_1" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Public Label (Display Name)</label>
              <input v-model="form.label" placeholder="e.g. Underground Bunker" class="modal-input" />
            </div>
            
            <div class="form-group span-2">
              <label>Min Player Level to Unlock</label>
              <input v-model.number="form.required_level" type="number" class="modal-input" />
            </div>
            <div class="form-group span-1">
              <label>Unlock Cost ($)</label>
              <input v-model.number="form.unlock_cost" type="number" class="modal-input" />
            </div>
            <div class="form-group span-1">
              <label>Hourly Rent ($)</label>
              <input v-model.number="form.rent_per_hour" type="number" class="modal-input" />
            </div>

            <!-- CORE SPECS -->
            <div class="form-group span-4 mt-6">
              <label class="section-label">Infrastructure Limits & Physics</label>
            </div>
            
            <div class="form-group span-1">
              <label>Max Racks</label>
              <input v-model.number="form.max_racks" type="number" class="modal-input text-white" />
            </div>
            <div class="form-group span-1">
              <label>Max Power (kW)</label>
              <input v-model.number="form.max_power_kw" type="number" class="modal-input text-yellow-500" />
            </div>
            <div class="form-group span-1">
              <label>Max Cooling (kW)</label>
              <input v-model.number="form.max_cooling_kw" type="number" class="modal-input text-blue-400" />
            </div>
            <div class="form-group span-1">
              <label>Bandwidth (Gbps)</label>
              <input v-model.number="form.bandwidth_gbps" type="number" class="modal-input text-green-400" />
            </div>

            <div class="form-group span-4">
              <label>Dust Accumulation Rate (Base Modifier)</label>
              <input v-model.number="form.dust_rate" type="number" step="0.01" class="modal-input" placeholder="e.g. 0.45" />
              <p class="text-[9px] text-gray-500 mt-1 uppercase">Higher value = faster server hardware degradation.</p>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Commit Node Param</button>
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
const rooms = ref({});
const hasChanges = ref(false);

const fetchRooms = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.configs || res.data?.configs;
    let raw = null;
    
    for (const group of Object.values(configs)) {
        const item = group.find(c => c.key === 'location_definitions');
        if (item) { raw = item.value; break; }
    }
    
    if (raw) {
      rooms.value = raw;
      hasChanges.value = false;
    }
  } catch (e) {
    addToast('Facility Sync failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveRooms = async () => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'location_definitions',
            value: rooms.value,
            comment: 'Facility & Node reconfiguration.'
        });
        addToast('Nodes successfully synchronized.', 'success');
        hasChanges.value = false;
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
};

const getTier = (lvl) => {
    if (lvl >= 30) return 'elite';
    if (lvl >= 15) return 'pro';
    if (lvl >= 5) return 'mid';
    return 'entry';
};

// MODAL
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const form = ref({
    label: '',
    required_level: 1,
    unlock_cost: 0,
    rent_per_hour: 0,
    max_racks: 2,
    max_power_kw: 10,
    max_cooling_kw: 8,
    bandwidth_gbps: 1,
    dust_rate: 0.45
});

const openNewModal = () => {
    editingId.value = null;
    formId.value = '';
    form.value = {
        label: '',
        required_level: 1,
        unlock_cost: 0,
        rent_per_hour: 0,
        max_racks: 2,
        max_power_kw: 10,
        max_cooling_kw: 8,
        bandwidth_gbps: 1,
        dust_rate: 0.45
    };
    showModal.value = true;
};

const editRoom = (id) => {
    editingId.value = id;
    formId.value = id;
    form.value = JSON.parse(JSON.stringify(rooms.value[id]));
    showModal.value = true;
};

const closeModal = () => showModal.value = false;

const applyChanges = () => {
    if (!formId.value || !form.value.label) return addToast('ID and Label required.', 'error');
    rooms.value[formId.value] = JSON.parse(JSON.stringify(form.value));
    hasChanges.value = true;
    showModal.value = false;
    addToast(`${formId.value} parameters set in memory.`, 'success');
};

const confirmDelete = (id) => {
    requestConfirm(`Demolish Facility '${id}'? This might cause unrecoverable UI errors for players if they own it!`, () => {
        delete rooms.value[id];
        hasChanges.value = true;
        addToast(`${id} purged from infrastructure array.`, 'info');
    });
};

onMounted(fetchRooms);
</script>

<style scoped>
.pra-container { display: flex; flex-direction: column; gap: 32px; }

.pra-header { display: flex; flex-direction: column; gap: 24px; }
.pra-title-row { display: flex; justify-content: space-between; align-items: start; }
.pra-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.pra-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 4px; }

.pra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }

.product-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 24px; padding: 24px;
  display: flex; flex-direction: column; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}
.product-card:hover { border-color: #27272a; transform: translateY(-4px); }

.product-header { display: flex; justify-content: space-between; align-items: start; }
.product-name { font-size: 1.1rem; font-weight: 900; color: white; margin: 0; font-style: italic; letter-spacing: -0.02em; }
.product-id { font-size: 0.55rem; color: #3f3f46; text-transform: uppercase; font-weight: 800; margin-bottom: 10px; display: block; }

.tier-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.55rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; }
.tier-entry { background: #111; color: #52525b; }
.tier-mid { background: #0c1222; color: #3b82f6; }
.tier-pro { background: #0c1c1c; color: #10b981; }
.tier-elite { background: #1c110c; color: #f59e0b; }

.requirement-preview { display: flex; flex-wrap: wrap; gap: 6px; }
.req-pill { padding: 6px 12px; background: #050505; border: 1px solid #18181b; border-radius: 10px; display: flex; flex-direction: column; align-items: start; min-width: 60px; }
.pill-label { font-size: 0.45rem; font-weight: 800; color: #52525b; letter-spacing: 0.05em; margin-bottom: 2px; }
.pill-range { font-size: 0.8rem; font-weight: 900; font-family: var(--sys-font-mono); }

.price-strip { padding: 14px; background: #111; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #18181b; }
.price-label { font-size: 0.6rem; font-weight: 800; color: #52525b; text-transform: uppercase; }
.price-val { font-size: 1.1rem; font-weight: 900; font-family: var(--sys-font-mono); font-style: italic; }

.card-footer { display: flex; justify-content: space-between; align-items: center; }
.edit-link { display: flex; align-items: center; gap: 8px; background: transparent; border: none; font-size: 0.65rem; font-weight: 900; color: #a1a1aa; cursor: pointer; text-transform: uppercase; letter-spacing: 0.1em; }
.edit-link:hover { color: white; }
.delete-btn { width: 28px; height: 28px; border-radius: 6px; background: transparent; border: 1px solid #1c1c1e; color: #3f3f46; cursor: pointer; transition: all 0.2s; }
.delete-btn:hover { background: #450a0a; color: #ef4444; border-color: #991b1b; }

/* ADD CARD */
.add-card { border-style: dashed; border-color: #18181b; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; gap: 12px; min-height: 250px; }
.add-card:hover { border-color: #3b82f6; background: #0c1222; }
.add-card .add-icon { font-size: 2rem; color: #18181b; font-weight: 100; transition: all 0.2s; }
.add-text { font-size: 0.7rem; font-weight: 900; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.1em; transition: all 0.2s; }
.add-card:hover .add-icon, .add-card:hover .add-text { color: #3b82f6; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(14px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.pra-modal { width: 700px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 36px; padding: 40px; }
.close-btn { width: 32px; height: 32px; border-radius: 10px; background: #111; border: 1px solid #222; color: #3f3f46; cursor: pointer; }
.modal-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 32px; }
.modal-title { font-size: 1.5rem; color: white; margin: 0; }
.modal-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; margin-top: 4px; }

.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
.span-1 { grid-column: span 1; }
.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }
.modal-input { background: #050505; border: 1px solid #18181b; padding: 12px 16px; border-radius: 14px; color: white; font-size: 0.85rem; font-weight: 600; outline: none; transition: border-color 0.2s; width: 100%; box-sizing: border-box; }
.modal-input:focus { border-color: #3b82f6; }

.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }

.modal-footer { display: flex; gap: 16px; margin-top: 40px; }
.modal-footer button { flex: 1; }

.section-label { color: #3b82f6; border-bottom: 1px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 5px; }
</style>
