<template>
  <div class="hca-container">
    <!-- HEADER -->
    <div class="hca-header">
      <div class="hca-title-row">
        <div>
          <h2 class="hca-title">Hardware Component Architect</h2>
          <p class="hca-subtitle">Modular Engineering — Define the discrete parts for custom server assembly.</p>
        </div>
        <div class="header-actions">
          <button @click="fetchComponents" class="sys-btn sys-btn-secondary" :disabled="loading">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="23 4 23 10 17 10" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg>
            Sync
          </button>
          <button @click="saveComponents" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
            Deploy Component Matrix
          </button>
        </div>
      </div>

      <!-- CATEGORY TABS -->
      <div class="hca-tabs">
        <button v-for="cat in categories" :key="cat" :class="['hca-tab', { active: activeCategory === cat }]"
          @click="activeCategory = cat">
          {{ cat.toUpperCase() }}
          <span class="tab-count" v-if="components[cat]">{{ Object.keys(components[cat]).length }}</span>
        </button>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="hca-grid">
      <div v-for="(comp, id) in components[activeCategory]" :key="id" class="comp-card" :class="activeCategory">
        <div class="comp-header">
          <div class="comp-meta">
            <h3 class="comp-name">{{ comp.name }}</h3>
            <span class="comp-id text-mono">{{ id }}</span>
          </div>
          <div class="comp-actions">
            <button @click="editComponent(id)" class="action-btn edit">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
            </button>
            <button @click="confirmDelete(id)" class="action-btn delete">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              </svg>
            </button>
          </div>
        </div>

        <div class="spec-list">
          <!-- DYNAMIC SPECS BASED ON CATEGORY -->
          <div v-if="activeCategory === 'cpu'" class="spec-row">
            <span class="s-label">Cores/Threads</span>
            <span class="s-val">{{ comp.cores }}/{{ comp.threads }} @ {{ comp.clock_ghz }}GHz</span>
          </div>
          <div v-if="activeCategory === 'ram'" class="spec-row">
            <span class="s-label">Dimensions</span>
            <span class="s-val">{{ comp.size_gb }} GB ECC</span>
          </div>
          <div v-if="activeCategory === 'storage'" class="spec-row">
            <span class="s-label">Density</span>
            <span class="s-val">{{ comp.size_tb }} TB {{ comp.type }}</span>
          </div>
          <div v-if="activeCategory === 'motherboard'" class="spec-row">
            <span class="s-label">Expansion</span>
            <span class="s-val">{{ comp.cpu_slots }}x CPU | {{ comp.ram_slots }}x RAM</span>
          </div>
          <div v-if="activeCategory === 'battery'" class="spec-row">
            <span class="s-label">Capacity</span>
            <span class="s-val">{{ comp.capacity_kwh }} kWh ({{ comp.size_u }}U)</span>
          </div>

          <div class="spec-row">
            <span class="s-label">Power Consumption</span>
            <span class="s-val energy">{{ comp.power_draw_w }}W</span>
          </div>
          <div class="spec-row">
            <span class="s-label">Induction Cost</span>
            <span class="s-val money">${{ comp.price.toLocaleString() }}</span>
          </div>
        </div>

        <div class="level-indicator">
          <div class="lvl-dot" :class="{ filled: comp.level_required <= 1 }"></div>
          <div class="lvl-dot" :class="{ filled: comp.level_required <= 5 }"></div>
          <div class="lvl-dot" :class="{ filled: comp.level_required <= 10 }"></div>
          <span class="lvl-text">Lvl {{ comp.level_required }}</span>
        </div>
      </div>

      <!-- ADD CARD -->
      <div class="comp-card add-card" @click="openNewModal">
        <div class="add-icon">+</div>
        <span class="add-text">Initialize discrete Part</span>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="hca-modal glass-effect animate-popup">
        <div class="modal-header">
          <div>
            <h3 class="modal-title font-black italic">{{ editingId ? 'Optimize Component Logic' : 'Part Hybridization'
              }}</h3>
            <p class="modal-subtitle">Define hardware specs and resource footprints.</p>
          </div>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Internal Key</label>
              <input v-model="formId" :disabled="editingId" class="modal-input" placeholder="e.g. epyc_7702" />
            </div>
            <div class="form-group span-2">
              <label>Manufacturer Label</label>
              <input v-model="form.name" class="modal-input" placeholder="e.g. AMD EPYC 7702" />
            </div>

            <div class="form-group">
              <label>Price ($)</label>
              <input v-model.number="form.price" type="number" class="modal-input" />
            </div>
            <div class="form-group">
              <label>Power (W)</label>
              <input v-model.number="form.power_draw_w" type="number" class="modal-input" />
            </div>
            <div class="form-group">
              <label>Level Req</label>
              <input v-model.number="form.level_required" type="number" class="modal-input" />
            </div>
            <div class="form-group">
              <label>Heat (W)</label>
              <input v-model.number="form.heat_output_w" type="number" class="modal-input" />
            </div>

            <!-- CPU SPECS -->
            <template v-if="activeCategory === 'cpu'">
              <div class="form-group">
                <label>Cores</label>
                <input v-model.number="form.cores" type="number" class="modal-input" />
              </div>
              <div class="form-group">
                <label>Threads</label>
                <input v-model.number="form.threads" type="number" class="modal-input" />
              </div>
              <div class="form-group span-2">
                <label>Clock (GHz)</label>
                <input v-model.number="form.clock_ghz" type="number" step="0.1" class="modal-input" />
              </div>
            </template>

            <!-- RAM SPECS -->
            <template v-if="activeCategory === 'ram'">
              <div class="form-group span-4">
                <label>Capacity (GB)</label>
                <input v-model.number="form.size_gb" type="number" class="modal-input" />
              </div>
            </template>

            <!-- STORAGE SPECS -->
            <template v-if="activeCategory === 'storage'">
              <div class="form-group span-2">
                <label>Capacity (TB)</label>
                <input v-model.number="form.size_tb" type="number" class="modal-input" />
              </div>
              <div class="form-group span-2">
                <label>Type</label>
                <select v-model="form.type" class="modal-input">
                  <option value="HDD">HDD</option>
                  <option value="SSD">SSD</option>
                  <option value="NVMe">NVMe</option>
                </select>
              </div>
            </template>

            <!-- MB SPECS -->
            <template v-if="activeCategory === 'motherboard'">
              <div class="form-group">
                <label>CPU Slots</label>
                <input v-model.number="form.cpu_slots" type="number" class="modal-input" />
              </div>
              <div class="form-group">
                <label>RAM Slots</label>
                <input v-model.number="form.ram_slots" type="number" class="modal-input" />
              </div>
              <div class="form-group">
                <label>Storage Slots</label>
                <input v-model.number="form.storage_slots" type="number" class="modal-input" />
              </div>
              <div class="form-group">
                <label>Size (U)</label>
                <input v-model.number="form.size_u" type="number" class="modal-input" />
              </div>
            </template>

            <!-- BATTERY SPECS -->
            <template v-if="activeCategory === 'battery'">
              <div class="form-group span-2">
                <label>Capacity (kWh)</label>
                <input v-model.number="form.capacity_kwh" type="number" step="0.1" class="modal-input" />
              </div>
              <div class="form-group span-2">
                <label>Size (U)</label>
                <input v-model.number="form.size_u" type="number" class="modal-input" />
              </div>
            </template>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Optimize Discrete Logic</button>
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
const components = ref({});
const activeCategory = ref('cpu');
const hasChanges = ref(false);
const categories = ['cpu', 'ram', 'storage', 'motherboard', 'battery'];

const fetchComponents = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.configs || res.data?.configs;
    let raw = null;

    for (const group of Object.values(configs)) {
      const item = group.find(c => c.key === 'server_components');
      if (item) { raw = item.value; break; }
    }

    if (raw) {
      components.value = raw;
      hasChanges.value = false;
    }
  } catch (e) {
    addToast('Hardware telemetry failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveComponents = async () => {
  setGlobalLoading(true);
  try {
    await api.post('/admin/configs/update', {
      key: 'server_components',
      value: components.value,
      comment: 'Hardware ecosystem refinement.'
    });
    addToast('Lattice successfully synchronized.', 'success');
    hasChanges.value = false;
  } catch (e) { addToast(e.message, 'error'); }
  finally { setGlobalLoading(false); }
};

// MODAL
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const form = ref({});

const openNewModal = () => {
  editingId.value = null;
  formId.value = '';
  // Skeleton based on category
  form.value = {
    name: '', price: 100, power_draw_w: 10, heat_output_w: 10, level_required: 1,
    ...(activeCategory.value === 'cpu' ? { cores: 4, threads: 8, clock_ghz: 2.5 } : {}),
    ...(activeCategory.value === 'ram' ? { size_gb: 16 } : {}),
    ...(activeCategory.value === 'storage' ? { size_tb: 1, type: 'SSD' } : {}),
    ...(activeCategory.value === 'motherboard' ? { cpu_slots: 1, ram_slots: 4, storage_slots: 4, size_u: 1 } : {}),
    ...(activeCategory.value === 'battery' ? { capacity_kwh: 5.0, size_u: 1 } : {}),
  };
  showModal.value = true;
};

const editComponent = (id) => {
  editingId.value = id;
  formId.value = id;
  form.value = JSON.parse(JSON.stringify(components.value[activeCategory.value][id]));
  showModal.value = true;
};

const closeModal = () => showModal.value = false;

const applyChanges = () => {
  if (!formId.value || !form.value.name) return addToast('Part ID and Name required.', 'error');
  if (!components.value[activeCategory.value]) components.value[activeCategory.value] = {};
  components.value[activeCategory.value][formId.value] = JSON.parse(JSON.stringify(form.value));
  hasChanges.value = true;
  showModal.value = false;
  addToast(`${formId.value} logic applied.`, 'success');
};

const confirmDelete = (id) => {
  requestConfirm(`Decommission hardware part '${id}'? Existing units will retain integrity until total lifecycle failure.`, () => {
    delete components.value[activeCategory.value][id];
    hasChanges.value = true;
    addToast(`${id} purged from matrix.`, 'info');
  });
};

onMounted(fetchComponents);
</script>

<style scoped>
.hca-container {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.hca-header {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.hca-title-row {
  display: flex;
  justify-content: space-between;
  align-items: start;
}

.hca-title {
  font-size: 1.5rem;
  font-weight: 900;
  font-style: italic;
  letter-spacing: -0.04em;
  color: white;
  margin: 0;
}

.hca-subtitle {
  font-size: 0.65rem;
  color: #52525b;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  margin-top: 4px;
}

.hca-tabs {
  display: flex;
  gap: 4px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  padding: 6px;
  border-radius: 16px;
  width: fit-content;
}

.hca-tab {
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 0.65rem;
  font-weight: 800;
  color: #3f3f46;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 8px;
}

.hca-tab:hover {
  color: #a1a1aa;
  background: #111;
}

.hca-tab.active {
  background: #1e3a5f;
  color: #3b82f6;
}

.tab-count {
  font-size: 0.55rem;
  background: #18181b;
  color: #52525b;
  padding: 2px 6px;
  border-radius: 4px;
}

.hca-tab.active .tab-count {
  background: #3b82f6;
  color: white;
}

.hca-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}

.comp-card {
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 20px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.comp-card:hover {
  border-color: #27272a;
  transform: translateY(-4px);
}

.comp-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
}

.comp-name {
  font-size: 0.95rem;
  font-weight: 900;
  color: white;
  margin: 0;
  font-style: italic;
  letter-spacing: -0.02em;
}

.comp-id {
  font-size: 0.55rem;
  color: #3f3f46;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 800;
}

.comp-actions {
  display: flex;
  gap: 6px;
}

.action-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid #18181b;
  background: #111;
  color: #3f3f46;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn.edit:hover {
  background: #0c1222;
  color: #3b82f6;
  border-color: #1e3a5f;
}

.action-btn.delete:hover {
  background: #450a0a;
  color: #ef4444;
  border-color: #991b1b;
}

.spec-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.spec-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  background: #050505;
  border-radius: 10px;
  border: 1px solid #0e0e10;
}

.s-label {
  font-size: 0.55rem;
  font-weight: 800;
  color: #3f3f46;
  text-transform: uppercase;
}

.s-val {
  font-size: 0.75rem;
  font-weight: 900;
  font-family: var(--sys-font-mono);
  color: #a1a1aa;
}

.s-val.energy {
  color: #fbbf24;
}

.s-val.money {
  color: #4ade80;
}

.level-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}

.lvl-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #18181b;
}

.lvl-dot.filled {
  background: #3b82f6;
  box-shadow: 0 0 6px #3b82f6;
}

.lvl-text {
  font-size: 0.55rem;
  color: #3f3f46;
  font-weight: 900;
  text-transform: uppercase;
  margin-left: 4px;
}

/* ADD CARD */
.add-card {
  border-style: dashed;
  border-color: #18181b;
  background: transparent;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  gap: 12px;
  min-height: 180px;
}

.add-card:hover {
  border-color: #3b82f6;
  background: #0c1222;
}

.add-card .add-icon {
  font-size: 2rem;
  color: #18181b;
  font-weight: 100;
  transition: all 0.2s;
}

.add-text {
  font-size: 0.7rem;
  font-weight: 900;
  color: #3f3f46;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  transition: all 0.2s;
}

.add-card:hover .add-icon,
.add-card:hover .add-text {
  color: #3b82f6;
}

/* MODAL */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(14px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.hca-modal {
  width: 680px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 36px;
  padding: 40px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 32px;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: #111;
  border: 1px solid #222;
  color: #3f3f46;
  cursor: pointer;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.span-2 {
  grid-column: span 2;
}

.span-4 {
  grid-column: span 4;
}

.modal-input {
  background: #050505;
  border: 1px solid #18181b;
  padding: 12px 16px;
  border-radius: 14px;
  color: white;
  font-size: 0.85rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s;
  width: 100%;
  box-sizing: border-box;
}

.modal-input:focus {
  border-color: #3b82f6;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 0.6rem;
  color: #52525b;
  font-weight: 800;
  text-transform: uppercase;
}

.modal-footer {
  display: flex;
  gap: 16px;
  margin-top: 40px;
}

.modal-footer button {
  flex: 1;
}
</style>
