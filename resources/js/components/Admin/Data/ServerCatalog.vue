<template>
  <div class="catalog-container">
    <!-- HEADER -->
    <div class="catalog-header">
      <div class="catalog-title-row">
        <div>
          <h2 class="catalog-title">Hardware Shop Catalog</h2>
          <p class="catalog-subtitle">Server products available for purchase — VNodes, Dedicated, GPU, Storage.</p>
        </div>
        <div class="header-actions">
          <button @click="loadCatalog" class="refresh-btn" :class="{ 'spin': loading }">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Reload
          </button>
          <button @click="saveCatalog" class="save-btn" :disabled="!hasChanges || saving">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            {{ saving ? 'Committing...' : 'Commit Changes' }}
          </button>
        </div>
      </div>

      <!-- CATEGORY TABS -->
      <div class="category-tabs">
        <button v-for="(info, key) in categoryMeta" :key="key"
                :class="['cat-tab', { active: activeCategory === key }]"
                @click="activeCategory = key">
          <span class="cat-icon" v-html="info.icon"></span>
          {{ info.label }}
          <span class="model-count" v-if="catalog[key]">{{ Object.keys(catalog[key]).length }}</span>
        </button>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-pulse"></div>
      <span>Loading hardware manifest...</span>
    </div>

    <!-- CATALOG GRID -->
    <div v-else-if="catalog && catalog[activeCategory]" class="catalog-grid">
      <div v-for="(model, modelKey) in catalog[activeCategory]" :key="modelKey" class="model-card" :class="{ editing: editingKey === modelKey }">
        <!-- VIEW MODE -->
        <template v-if="editingKey !== modelKey">
          <div class="card-header">
            <div class="card-title-section">
              <h3 class="model-name">{{ model.modelName }}</h3>
              <span class="model-key">{{ modelKey }}</span>
            </div>
            <div class="card-actions">
              <button @click="startEdit(modelKey, model)" class="card-btn edit-btn" title="Edit">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button @click="confirmDeleteModel(modelKey, model.modelName)" class="card-btn del-btn" title="Remove">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </div>
          </div>

          <div class="specs-display">
            <div class="spec-item">
              <span class="spec-label">Price</span>
              <span class="spec-value money">${{ formatNumber(model.purchaseCost) }}</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">Size</span>
              <span class="spec-value">{{ model.sizeU }}U</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">CPU</span>
              <span class="spec-value">{{ model.cpuCores }} Cores</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">RAM</span>
              <span class="spec-value">{{ model.ramGb }} GB</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">Storage</span>
              <span class="spec-value">{{ model.storageTb }} TB</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">Network</span>
              <span class="spec-value">{{ formatBandwidth(model.bandwidthMbps) }}</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">Power</span>
              <span class="spec-value power">{{ model.powerDrawKw }} kW</span>
            </div>
            <div class="spec-item">
              <span class="spec-label">Heat</span>
              <span class="spec-value heat">{{ model.heatOutputKw }} kW</span>
            </div>
            <div v-if="model.vserverCapacity" class="spec-item">
              <span class="spec-label">VServer Slots</span>
              <span class="spec-value slots">{{ model.vserverCapacity }}</span>
            </div>
          </div>
        </template>

        <!-- EDIT MODE -->
        <template v-else>
          <div class="card-header editing-header">
            <input v-model="editForm.modelName" class="edit-name-input" placeholder="Model Name" />
            <div class="card-actions">
              <button @click="saveModelEdit(modelKey)" class="card-btn save-btn" title="Save">✓</button>
              <button @click="cancelEdit" class="card-btn cancel-btn" title="Cancel">✕</button>
            </div>
          </div>

          <div class="edit-grid">
            <div class="edit-field">
              <label>Purchase Cost ($)</label>
              <input type="number" v-model.number="editForm.purchaseCost" step="any" />
            </div>
            <div class="edit-field">
              <label>Size (U)</label>
              <input type="number" v-model.number="editForm.sizeU" />
            </div>
            <div class="edit-field">
              <label>CPU Cores</label>
              <input type="number" v-model.number="editForm.cpuCores" />
            </div>
            <div class="edit-field">
              <label>RAM (GB)</label>
              <input type="number" v-model.number="editForm.ramGb" />
            </div>
            <div class="edit-field">
              <label>Storage (TB)</label>
              <input type="number" v-model.number="editForm.storageTb" step="any" />
            </div>
            <div class="edit-field">
              <label>Bandwidth (Mbps)</label>
              <input type="number" v-model.number="editForm.bandwidthMbps" />
            </div>
            <div class="edit-field">
              <label>Power Draw (kW)</label>
              <input type="number" v-model.number="editForm.powerDrawKw" step="0.01" />
            </div>
            <div class="edit-field">
              <label>Heat Output (kW)</label>
              <input type="number" v-model.number="editForm.heatOutputKw" step="0.01" />
            </div>
            <div class="edit-field" v-if="activeCategory === 'vserver_node'">
              <label>VServer Capacity</label>
              <input type="number" v-model.number="editForm.vserverCapacity" />
            </div>
          </div>
        </template>
      </div>

      <!-- ADD NEW MODEL CARD -->
      <div class="model-card add-card" @click="showAddModal = true">
        <div class="add-icon">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        </div>
        <span class="add-label">Add New Model</span>
        <span class="add-sub">to {{ categoryMeta[activeCategory]?.label }}</span>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-else class="empty-state">
      <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>
      <p>No catalog data found. Run the seeder or check the server_catalog config.</p>
    </div>

    <!-- ADD NEW MODEL MODAL -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
      <div class="modal-card">
        <h3 class="modal-title">New Hardware Model</h3>
        <p class="modal-sub">Category: {{ categoryMeta[activeCategory]?.label }}</p>

        <div class="modal-field">
          <label>Model Key <span class="required">(unique, snake_case)</span></label>
          <input v-model="newModel.key" placeholder="e.g. vs_ultimate" class="modal-input" />
        </div>
        <div class="modal-field">
          <label>Display Name</label>
          <input v-model="newModel.modelName" placeholder="e.g. VNode Ultimate" class="modal-input" />
        </div>

        <div class="modal-grid">
          <div class="modal-field">
            <label>Price ($)</label>
            <input type="number" v-model.number="newModel.purchaseCost" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>Size (U)</label>
            <input type="number" v-model.number="newModel.sizeU" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>CPU Cores</label>
            <input type="number" v-model.number="newModel.cpuCores" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>RAM (GB)</label>
            <input type="number" v-model.number="newModel.ramGb" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>Storage (TB)</label>
            <input type="number" v-model.number="newModel.storageTb" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>Bandwidth (Mbps)</label>
            <input type="number" v-model.number="newModel.bandwidthMbps" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>Power (kW)</label>
            <input type="number" v-model.number="newModel.powerDrawKw" step="0.01" class="modal-input" />
          </div>
          <div class="modal-field">
            <label>Heat (kW)</label>
            <input type="number" v-model.number="newModel.heatOutputKw" step="0.01" class="modal-input" />
          </div>
        </div>

        <div class="modal-field" v-if="activeCategory === 'vserver_node'">
          <label>VServer Capacity</label>
          <input type="number" v-model.number="newModel.vserverCapacity" class="modal-input" />
        </div>

        <div class="modal-actions">
          <button @click="showAddModal = false" class="sys-btn sys-btn-secondary">Cancel</button>
          <button @click="addModel" class="sys-btn sys-btn-primary" :disabled="!newModel.key || !newModel.modelName">
            Create Model
          </button>
        </div>
      </div>
    </div>

    <!-- DELETE CONFIRMATION -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-card modal-danger">
        <div class="delete-icon">⚠</div>
        <h3 class="modal-title danger">Remove Hardware Model</h3>
        <p>Delete <strong>{{ deleteTarget.name }}</strong> ({{ deleteTarget.key }})? Existing servers using this model won't be affected, but it will no longer appear in the shop.</p>
        <div class="modal-actions">
          <button @click="deleteTarget = null" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="executeDelete" class="sys-btn sys-btn-danger">Remove Model</button>
        </div>
      </div>
    </div>

    <!-- UNSAVED BADGE -->
    <div v-if="hasChanges" class="unsaved-banner">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Unsaved changes — click "Commit Changes" to persist to database.
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast } = inject('adminContext');

const loading = ref(false);
const saving = ref(false);
const catalog = ref(null);
const activeCategory = ref('vserver_node');
const editingKey = ref(null);
const editForm = ref({});
const hasChanges = ref(false);
const showAddModal = ref(false);
const deleteTarget = ref(null);

const newModel = ref({
  key: '',
  modelName: '',
  purchaseCost: 1000,
  sizeU: 1,
  cpuCores: 4,
  ramGb: 16,
  storageTb: 1,
  bandwidthMbps: 1000,
  powerDrawKw: 0.5,
  heatOutputKw: 0.4,
  vserverCapacity: 0,
});

const categoryMeta = {
  vserver_node: {
    label: 'VServer Nodes',
    icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>',
  },
  dedicated: {
    label: 'Dedicated',
    icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
  },
  gpu_server: {
    label: 'GPU Servers',
    icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>',
  },
  storage_server: {
    label: 'Storage',
    icon: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
  },
};

// ─── LOAD CATALOG ───

async function loadCatalog() {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.data?.configs || res.configs;
    if (configs) {
      // Find server_catalog in the config groups
      for (const group of Object.values(configs)) {
        const arr = Array.isArray(group) ? group : [group];
        for (const cfg of arr) {
          if (cfg.key === 'server_catalog') {
            catalog.value = JSON.parse(JSON.stringify(cfg.value));
            hasChanges.value = false;
            break;
          }
        }
      }
    }
    if (!catalog.value) {
      addToast('server_catalog config not found. Run migration first.', 'error');
    }
  } catch (e) {
    console.error(e);
    addToast('Failed to load catalog: ' + (e.response?.data?.message || e.message), 'error');
  }
  loading.value = false;
}

// ─── SAVE CATALOG ───

async function saveCatalog() {
  saving.value = true;
  try {
    await api.post('/admin/configs/update', {
      key: 'server_catalog',
      value: catalog.value,
    });
    addToast('Hardware catalog committed to database.', 'success');
    hasChanges.value = false;
  } catch (e) {
    addToast('Save failed: ' + (e.response?.data?.message || e.message), 'error');
  }
  saving.value = false;
}

// ─── INLINE EDITING ───

function startEdit(key, model) {
  editingKey.value = key;
  editForm.value = JSON.parse(JSON.stringify(model));
}

function cancelEdit() {
  editingKey.value = null;
  editForm.value = {};
}

function saveModelEdit(key) {
  catalog.value[activeCategory.value][key] = JSON.parse(JSON.stringify(editForm.value));
  editingKey.value = null;
  editForm.value = {};
  hasChanges.value = true;
  addToast(`${catalog.value[activeCategory.value][key].modelName} updated (unsaved).`, 'info');
}

// ─── ADD MODEL ───

function addModel() {
  if (!newModel.value.key || !newModel.value.modelName) return;
  const key = newModel.value.key.trim().toLowerCase().replace(/\s+/g, '_');

  if (catalog.value[activeCategory.value]?.[key]) {
    addToast('Model key already exists in this category!', 'error');
    return;
  }

  if (!catalog.value[activeCategory.value]) {
    catalog.value[activeCategory.value] = {};
  }

  const { key: _, ...modelData } = newModel.value;
  catalog.value[activeCategory.value][key] = JSON.parse(JSON.stringify(modelData));
  hasChanges.value = true;
  showAddModal.value = false;
  addToast(`${modelData.modelName} added to catalog (unsaved).`, 'success');

  // Reset form
  newModel.value = {
    key: '',
    modelName: '',
    purchaseCost: 1000,
    sizeU: 1,
    cpuCores: 4,
    ramGb: 16,
    storageTb: 1,
    bandwidthMbps: 1000,
    powerDrawKw: 0.5,
    heatOutputKw: 0.4,
    vserverCapacity: 0,
  };
}

// ─── DELETE MODEL ───

function confirmDeleteModel(key, name) {
  deleteTarget.value = { key, name };
}

function executeDelete() {
  if (!deleteTarget.value) return;
  delete catalog.value[activeCategory.value][deleteTarget.value.key];
  hasChanges.value = true;
  addToast(`${deleteTarget.value.name} removed (unsaved).`, 'success');
  deleteTarget.value = null;
}

// ─── UTILITIES ───

function formatNumber(n) {
  if (n == null) return '0';
  return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function formatBandwidth(mbps) {
  if (mbps >= 1000) return (mbps / 1000).toFixed(0) + ' Gbps';
  return mbps + ' Mbps';
}

// ─── INIT ───

onMounted(() => {
  loadCatalog();
});
</script>

<style scoped>
.catalog-container {
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
}

/* HEADER */
.catalog-header {
  margin-bottom: 24px;
}

.catalog-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}

.catalog-title {
  font-size: 1.5rem;
  font-weight: 900;
  font-style: italic;
  letter-spacing: -0.03em;
  color: #fafafa;
  margin: 0;
}

.catalog-subtitle {
  font-size: 0.7rem;
  color: #52525b;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-top: 4px;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.refresh-btn, .save-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid #222;
}

.refresh-btn { background: #111; color: #a1a1aa; }
.refresh-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.refresh-btn.spin svg { animation: spin360 0.8s ease; }

.save-btn { background: #052e16; color: #4ade80; border-color: #15803d; }
.save-btn:hover:not(:disabled) { background: #14532d; color: #86efac; }
.save-btn:disabled { opacity: 0.35; cursor: not-allowed; }

@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* CATEGORY TABS */
.category-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 14px;
}

.cat-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #52525b;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
  border: none;
  background: transparent;
}
.cat-tab:hover { color: #a1a1aa; background: #111; }
.cat-tab.active { color: #3b82f6; background: #0c1222; }

.cat-icon { opacity: 0.5; flex-shrink: 0; }
.cat-tab.active .cat-icon { opacity: 1; }

.model-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: #18181b;
  color: #71717a;
  font-size: 0.6rem;
  font-weight: 800;
}
.cat-tab.active .model-count { background: #1e3a5f; color: #60a5fa; }

/* CATALOG GRID */
.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

/* MODEL CARD */
.model-card {
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 16px;
  padding: 20px;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.model-card:hover { border-color: #27272a; }
.model-card.editing { border-color: #3b82f6; background: #0c1222; box-shadow: 0 0 30px rgba(59, 130, 246, 0.08); }

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}

.model-name {
  font-size: 1rem;
  font-weight: 900;
  font-style: italic;
  color: #fafafa;
  margin: 0;
  letter-spacing: -0.02em;
}

.model-key {
  font-size: 0.6rem;
  color: #3f3f46;
  font-family: 'JetBrains Mono', monospace;
  margin-top: 2px;
  display: block;
}

.card-actions { display: flex; gap: 6px; }

.card-btn {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
  border: 1px solid #222;
  background: #111;
  color: #71717a;
}
.card-btn:hover { transform: translateY(-1px); }
.edit-btn:hover { background: #1e3a5f; color: #60a5fa; border-color: #2563eb; }
.del-btn:hover { background: #450a0a; color: #f87171; border-color: #dc2626; }
.save-btn { background: #052e16; color: #4ade80; border-color: #15803d; font-weight: 900; font-size: 16px; }
.cancel-btn { background: #1c1917; color: #a8a29e; border-color: #44403c; font-weight: 900; font-size: 12px; }

/* SPECS DISPLAY */
.specs-display {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.spec-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  background: #111;
  border-radius: 8px;
}

.spec-label {
  font-size: 0.55rem;
  color: #52525b;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.spec-value {
  font-size: 0.8rem;
  color: #d4d4d8;
  font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
}

.spec-value.money { color: #4ade80; }
.spec-value.power { color: #fbbf24; }
.spec-value.heat { color: #fb923c; }
.spec-value.slots { color: #60a5fa; }

/* EDIT GRID */
.editing-header {
  margin-bottom: 12px;
}

.edit-name-input {
  flex: 1;
  height: 38px;
  padding: 0 14px;
  background: #111;
  border: 1px solid #3b82f6;
  border-radius: 8px;
  color: #fafafa;
  font-size: 0.95rem;
  font-weight: 800;
  font-style: italic;
  outline: none;
  margin-right: 10px;
}

.edit-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.edit-field label {
  display: block;
  font-size: 0.55rem;
  color: #52525b;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  margin-bottom: 4px;
}

.edit-field input {
  width: 100%;
  height: 32px;
  padding: 0 10px;
  background: #111;
  border: 1px solid #27272a;
  border-radius: 8px;
  color: #fafafa;
  font-size: 0.75rem;
  font-weight: 600;
  font-family: 'JetBrains Mono', monospace;
  outline: none;
  box-sizing: border-box;
}
.edit-field input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15); }

/* ADD CARD */
.add-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  cursor: pointer;
  border: 1px dashed #27272a;
  min-height: 200px;
}
.add-card:hover { border-color: #3b82f6; background: #0c1222; }
.add-card .add-icon { color: #3f3f46; }
.add-card:hover .add-icon { color: #3b82f6; }
.add-label { font-size: 0.75rem; font-weight: 800; color: #52525b; text-transform: uppercase; letter-spacing: 0.1em; }
.add-sub { font-size: 0.6rem; color: #3f3f46; }

/* LOADING */
.loader-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 0;
  color: #3f3f46;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.15em;
}

.loader-pulse {
  width: 32px;
  height: 32px;
  border: 2px solid #18181b;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: pulseSpin 0.8s ease infinite;
}
@keyframes pulseSpin { to { transform: rotate(360deg); } }

/* EMPTY STATE */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 0;
  color: #27272a;
}
.empty-state p { color: #3f3f46; font-size: 0.75rem; font-weight: 600; }

/* MODAL */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-card {
  width: 520px;
  padding: 36px;
  background: #0a0a0c;
  border: 1px solid #18181b;
  border-radius: 24px;
  animation: modalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-danger { border-color: #450a0a; text-align: center; }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

.modal-title { font-size: 1.1rem; font-weight: 900; font-style: italic; color: #fafafa; margin: 0 0 4px 0; }
.modal-title.danger { color: #f87171; }
.modal-sub { font-size: 0.65rem; color: #52525b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 20px; }

.modal-field { margin-bottom: 14px; }
.modal-field label { display: block; font-size: 0.6rem; color: #71717a; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
.modal-field .required { color: #3f3f46; font-size: 0.55rem; text-transform: none; }

.modal-input {
  width: 100%;
  height: 38px;
  padding: 0 14px;
  background: #111;
  border: 1px solid #222;
  border-radius: 10px;
  color: #fafafa;
  font-size: 0.78rem;
  font-weight: 600;
  outline: none;
  box-sizing: border-box;
}
.modal-input:focus { border-color: #3b82f6; }

.modal-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.modal-actions { display: flex; gap: 12px; margin-top: 24px; }

.delete-icon { font-size: 2.5rem; margin-bottom: 16px; }

.modal-card p {
  font-size: 0.8rem;
  color: #71717a;
  line-height: 1.6;
  margin-bottom: 8px;
}

/* BUTTONS */
.sys-btn {
  flex: 1;
  height: 44px;
  border-radius: 12px;
  font-weight: 800;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  cursor: pointer;
  transition: all 0.2s;
}

.sys-btn-primary { background: #1e3a5f; color: #60a5fa; border: 1px solid #2563eb; }
.sys-btn-primary:hover:not(:disabled) { background: #2563eb; color: white; }
.sys-btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }

.sys-btn-secondary { background: #111; color: #a1a1aa; border: 1px solid #222; }
.sys-btn-secondary:hover { background: #1a1a1a; color: white; }

.sys-btn-danger { background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; }
.sys-btn-danger:hover { background: #991b1b; color: white; }

/* UNSAVED BANNER */
.unsaved-banner {
  position: sticky;
  bottom: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  background: #422006;
  border: 1px solid #92400e;
  border-radius: 14px;
  color: #fbbf24;
  font-size: 0.7rem;
  font-weight: 700;
  margin-top: 16px;
  animation: fadeInUp 0.3s ease;
}
@keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
