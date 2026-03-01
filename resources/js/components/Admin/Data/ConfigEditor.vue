<template>
  <div class="cfg-container">
    <!-- HEADER -->
    <div class="cfg-header">
      <div class="cfg-title-row">
        <div>
          <h2 class="cfg-title">{{ editorMeta.title }}</h2>
          <p class="cfg-subtitle">{{ editorMeta.subtitle }}</p>
        </div>
        <div class="header-actions">
          <button @click="loadConfig" class="action-btn-header reload-btn" :class="{ spin: loading }">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Reload
          </button>
          <button @click="saveConfig" class="action-btn-header commit-btn" :disabled="!hasChanges || saving">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
            {{ saving ? 'Saving...' : 'Commit Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="loader-state">
      <div class="loader-ring"></div>
      <span>Loading configuration...</span>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- ENGINE CONSTANTS (flat key-value) -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'engine_constants' && configData">
      <div class="flat-grid">
        <div v-for="(val, key) in configData" :key="key" class="flat-row">
          <div class="flat-info">
            <span class="flat-name">{{ formatKeyName(key) }}</span>
            <span class="flat-key">{{ key }}</span>
          </div>
          <input type="number" step="0.001" v-model.number="configData[key]" class="flat-input" @input="hasChanges = true" />
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- LOCATIONS -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'location_definitions' && configData">
      <div class="card-grid">
        <div v-for="(loc, key) in configData" :key="key" class="config-card">
          <div class="card-header">
            <div>
              <h3 class="card-name">{{ loc.name }}</h3>
              <span class="card-key">{{ key }}</span>
            </div>
          </div>
          <div class="field-grid cols-3">
            <div class="cfg-field">
              <label>Max Racks</label>
              <input type="number" v-model.number="loc.max_racks" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Power (kW)</label>
              <input type="number" step="0.1" v-model.number="loc.max_power_kw" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Cooling (kW)</label>
              <input type="number" step="0.1" v-model.number="loc.max_cooling_kw" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Bandwidth (Gbps)</label>
              <input type="number" step="0.1" v-model.number="loc.bandwidth_gbps" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Unlock Cost ($)</label>
              <input type="number" v-model.number="loc.unlock_cost" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Rent ($/hr)</label>
              <input type="number" step="0.01" v-model.number="loc.rent_per_hour" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Required Level</label>
              <input type="number" v-model.number="loc.required_level" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Dust Rate</label>
              <input type="number" step="0.001" v-model.number="loc.dust_rate" @input="hasChanges = true" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- EMPLOYEES -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'employee_types' && configData">
      <div class="card-grid">
        <div v-for="(role, key) in configData" :key="key" class="config-card">
          <div class="card-header">
            <div>
              <h3 class="card-name">{{ role.name }}</h3>
              <span class="card-key">{{ key }}</span>
            </div>
            <button @click="deleteItem(key)" class="card-del" title="Remove">✕</button>
          </div>
          <div class="cfg-field full">
            <label>Description</label>
            <textarea v-model="role.description" rows="2" @input="hasChanges = true"></textarea>
          </div>
          <div class="field-grid cols-2">
            <div class="cfg-field">
              <label>Base Salary ($/hr)</label>
              <input type="number" step="0.01" v-model.number="role.base_salary" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Hiring Cost ($)</label>
              <input type="number" v-model.number="role.hiring_cost" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Efficiency</label>
              <input type="number" step="0.1" v-model.number="role.efficiency" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Stress Resist.</label>
              <input type="number" step="0.1" v-model.number="role.stress_resistance" @input="hasChanges = true" />
            </div>
          </div>
        </div>
        <div class="config-card add-card" @click="addEmployee">
          <div class="add-icon">+</div>
          <span>Add Employee Role</span>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- MARKETING CAMPAIGNS -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'marketing_campaigns' && configData">
      <div class="card-grid">
        <div v-for="(camp, key) in configData" :key="key" class="config-card">
          <div class="card-header">
            <div>
              <h3 class="card-name">{{ camp.name }}</h3>
              <span class="card-key">{{ key }}</span>
            </div>
            <button @click="deleteItem(key)" class="card-del" title="Remove">✕</button>
          </div>
          <div class="field-grid cols-3">
            <div class="cfg-field">
              <label>Cost ($)</label>
              <input type="number" v-model.number="camp.cost" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Duration (min)</label>
              <input type="number" v-model.number="camp.duration" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Effectiveness</label>
              <input type="number" step="0.1" v-model.number="camp.effectiveness" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Rep Gain</label>
              <input type="number" step="0.1" v-model.number="camp.reputation_gain" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Min Reputation</label>
              <input type="number" v-model.number="camp.min_reputation" @input="hasChanges = true" />
            </div>
          </div>
        </div>
        <div class="config-card add-card" @click="addMarketing">
          <div class="add-icon">+</div>
          <span>Add Campaign</span>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- PRODUCT DEFINITIONS -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'product_definitions' && configData">
      <div class="card-grid">
        <div v-for="(prod, key) in configData" :key="key" class="config-card">
          <div class="card-header">
            <div>
              <h3 class="card-name">{{ prod.name }}</h3>
              <span class="card-key">{{ key }}</span>
            </div>
            <button @click="deleteItem(key)" class="card-del" title="Remove">✕</button>
          </div>
          <div class="field-grid cols-2">
            <div class="cfg-field">
              <label>Name</label>
              <input v-model="prod.name" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Min Level</label>
              <input type="number" v-model.number="prod.min_level" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Base Price ($)</label>
              <input type="number" v-model.number="prod.base_price" @input="hasChanges = true" />
            </div>
          </div>
          <div v-if="prod.requirements" class="requirements-section">
            <span class="req-title">Resource Requirements (min — max)</span>
            <div class="field-grid cols-2">
              <div v-for="(range, rKey) in prod.requirements" :key="rKey" class="cfg-field range-field">
                <label>{{ rKey.toUpperCase() }}</label>
                <div class="range-inputs" v-if="Array.isArray(range)">
                  <input type="number" v-model.number="range[0]" @input="hasChanges = true" />
                  <span class="range-sep">—</span>
                  <input type="number" v-model.number="range[1]" @input="hasChanges = true" />
                </div>
                <input v-else type="number" v-model.number="prod.requirements[rKey]" @input="hasChanges = true" />
              </div>
            </div>
          </div>
        </div>
        <div class="config-card add-card" @click="addProduct">
          <div class="add-icon">+</div>
          <span>Add Product</span>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- RESEARCH TREE -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'research_tree' && configData">
      <div class="card-grid cols-1">
        <div v-for="(tech, id) in configData" :key="id" class="config-card tech-card">
          <div class="card-header">
            <div>
              <input v-model="tech.name" class="inline-name-input" @input="hasChanges = true" />
              <span class="card-key">{{ id }}</span>
            </div>
            <div class="card-header-right">
              <span class="tech-category" :class="tech.category">{{ tech.category }}</span>
              <button @click="deleteItem(id)" class="card-del" title="Remove">✕</button>
            </div>
          </div>
          <div class="cfg-field full">
            <textarea v-model="tech.description" rows="2" class="tech-desc" @input="hasChanges = true"></textarea>
          </div>
          <div class="field-grid cols-4">
            <div class="cfg-field">
              <label>Cost ($)</label>
              <input type="number" v-model.number="tech.cost" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Duration (s)</label>
              <input type="number" v-model.number="tech.duration" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Effect Type</label>
              <select v-model="tech.effect.type" @change="hasChanges = true">
                <option value="power_efficiency">Power Efficiency</option>
                <option value="provisioning_speed">Provisioning Speed</option>
                <option value="lifespan_bonus">Lifespan Bonus</option>
                <option value="rep_gain_multiplier">Rep Gain Multiplier</option>
                <option value="unlock">Unlock Feature</option>
                <option value="unlock_customer_tier">Unlock Tier</option>
              </select>
            </div>
            <div class="cfg-field">
              <label>Effect Value</label>
              <input v-model="tech.effect.value" @input="hasChanges = true" />
            </div>
          </div>
          <div class="cfg-field full" v-if="tech.prerequisites">
            <label>Prerequisites (comma-separated IDs)</label>
            <input :value="(tech.prerequisites || []).join(', ')" @input="updatePrereqs(tech, $event)" />
          </div>
        </div>
        <div class="config-card add-card" @click="addResearch">
          <div class="add-icon">+</div>
          <span>Add Technology</span>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- SERVER COMPONENTS (categorised) -->
    <!-- ═══════════════════════════════════════════════ -->
    <template v-else-if="configKey === 'server_components' && configData">
      <!-- Component Category Tabs -->
      <div class="sub-tabs">
        <button v-for="cat in componentCategories" :key="cat"
                :class="['sub-tab', { active: activeSubTab === cat }]"
                @click="activeSubTab = cat">
          {{ cat.toUpperCase() }}
          <span class="sub-count">{{ Object.keys(configData[cat] || {}).length }}</span>
        </button>
      </div>

      <div class="card-grid" v-if="configData[activeSubTab]">
        <div v-for="(comp, key) in configData[activeSubTab]" :key="key" class="config-card">
          <div class="card-header">
            <div>
              <h3 class="card-name">{{ comp.name }}</h3>
              <span class="card-key">{{ key }}</span>
            </div>
            <button @click="deleteComponent(key)" class="card-del" title="Remove">✕</button>
          </div>
          <div class="field-grid cols-3">
            <div class="cfg-field">
              <label>Name</label>
              <input v-model="comp.name" @input="hasChanges = true" />
            </div>
            <div class="cfg-field" v-if="comp.manufacturer !== undefined">
              <label>Manufacturer</label>
              <input v-model="comp.manufacturer" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Price ($)</label>
              <input type="number" v-model.number="comp.price" @input="hasChanges = true" />
            </div>
            <div class="cfg-field">
              <label>Level Required</label>
              <input type="number" v-model.number="comp.level_required" @input="hasChanges = true" />
            </div>
            <!-- CPU specific -->
            <template v-if="activeSubTab === 'cpu'">
              <div class="cfg-field"><label>Cores</label><input type="number" v-model.number="comp.cores" @input="hasChanges = true" /></div>
              <div class="cfg-field"><label>Threads</label><input type="number" v-model.number="comp.threads" @input="hasChanges = true" /></div>
              <div class="cfg-field"><label>Clock (GHz)</label><input type="number" step="0.1" v-model.number="comp.clock_ghz" @input="hasChanges = true" /></div>
            </template>
            <!-- RAM specific -->
            <template v-if="activeSubTab === 'ram'">
              <div class="cfg-field"><label>Size (GB)</label><input type="number" v-model.number="comp.size_gb" @input="hasChanges = true" /></div>
            </template>
            <!-- Storage specific -->
            <template v-if="activeSubTab === 'storage'">
              <div class="cfg-field"><label>Size (TB)</label><input type="number" step="0.1" v-model.number="comp.size_tb" @input="hasChanges = true" /></div>
              <div class="cfg-field">
                <label>Type</label>
                <select v-model="comp.type" @change="hasChanges = true">
                  <option value="HDD">HDD</option><option value="SSD">SSD</option><option value="NVMe">NVMe</option>
                </select>
              </div>
            </template>
            <!-- Motherboard specific -->
            <template v-if="activeSubTab === 'motherboard'">
              <div class="cfg-field"><label>Size (U)</label><input type="number" v-model.number="comp.size_u" @input="hasChanges = true" /></div>
              <div class="cfg-field"><label>CPU Slots</label><input type="number" v-model.number="comp.cpu_slots" @input="hasChanges = true" /></div>
              <div class="cfg-field"><label>RAM Slots</label><input type="number" v-model.number="comp.ram_slots" @input="hasChanges = true" /></div>
              <div class="cfg-field"><label>Storage Slots</label><input type="number" v-model.number="comp.storage_slots" @input="hasChanges = true" /></div>
            </template>
            <!-- Power/heat for CPU, RAM, Storage -->
            <div class="cfg-field" v-if="comp.power_draw_w !== undefined"><label>Power (W)</label><input type="number" v-model.number="comp.power_draw_w" @input="hasChanges = true" /></div>
            <div class="cfg-field" v-if="comp.heat_output_w !== undefined"><label>Heat (W)</label><input type="number" v-model.number="comp.heat_output_w" @input="hasChanges = true" /></div>
          </div>
        </div>
        <div class="config-card add-card" @click="addComponent">
          <div class="add-icon">+</div>
          <span>Add {{ activeSubTab }} Component</span>
        </div>
      </div>
    </template>

    <!-- EMPTY STATE -->
    <div v-else class="empty-state">
      <p>No configuration data found for <strong>{{ configKey }}</strong>.</p>
    </div>

    <!-- UNSAVED BANNER -->
    <div v-if="hasChanges" class="unsaved-banner">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Unsaved changes — click "Commit Changes" to persist.
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const props = defineProps({
  configKey: { type: String, required: true },
});

const { addToast } = inject('adminContext');

const loading = ref(false);
const saving = ref(false);
const configData = ref(null);
const hasChanges = ref(false);
const activeSubTab = ref('cpu');

const editorMetaMap = {
  location_definitions: { title: 'Location Properties', subtitle: 'Room types — racks, power, cooling, bandwidth, rent, and unlock requirements.' },
  research_tree: { title: 'Research Tree', subtitle: 'Technologies players can research — costs, durations, effects, and prerequisites.' },
  marketing_campaigns: { title: 'Marketing Campaigns', subtitle: 'Campaign definitions — cost, duration, effectiveness, reputation rewards.' },
  product_definitions: { title: 'Product Definitions', subtitle: 'Order types and their resource requirements — min/max ranges for matching.' },
  employee_types: { title: 'Employee Roles', subtitle: 'Personnel types — salaries, hiring costs, efficiency, and stress resistance.' },
  engine_constants: { title: 'Engine Constants', subtitle: 'Core game multipliers — XP rate, tax, energy cost, churn, reputation decay.' },
  server_components: { title: 'Server Components', subtitle: 'Modular parts — CPUs, RAM, Storage, and Motherboards for custom builds.' },
};

const editorMeta = computed(() => editorMetaMap[props.configKey] || { title: props.configKey, subtitle: '' });

const componentCategories = computed(() => {
  if (props.configKey === 'server_components' && configData.value) {
    return Object.keys(configData.value);
  }
  return [];
});

// ─── LOAD ───

async function loadConfig() {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.data?.configs || res.configs;
    if (configs) {
      for (const group of Object.values(configs)) {
        const arr = Array.isArray(group) ? group : [group];
        for (const cfg of arr) {
          if (cfg.key === props.configKey) {
            configData.value = JSON.parse(JSON.stringify(cfg.value));
            hasChanges.value = false;
            if (props.configKey === 'server_components' && configData.value) {
              activeSubTab.value = Object.keys(configData.value)[0] || 'cpu';
            }
            break;
          }
        }
      }
    }
    if (!configData.value) {
      addToast(`Config "${props.configKey}" not found in database.`, 'error');
    }
  } catch (e) {
    addToast('Failed to load: ' + (e.response?.data?.message || e.message), 'error');
  }
  loading.value = false;
}

// ─── SAVE ───

async function saveConfig() {
  saving.value = true;
  try {
    await api.post('/admin/configs/update', {
      key: props.configKey,
      value: configData.value,
    });
    addToast('Configuration committed.', 'success');
    hasChanges.value = false;
  } catch (e) {
    addToast('Save failed: ' + (e.response?.data?.message || e.message), 'error');
  }
  saving.value = false;
}

// ─── CRUD HELPERS ───

function deleteItem(key) {
  if (!confirm(`Delete "${key}"?`)) return;
  delete configData.value[key];
  hasChanges.value = true;
}

function deleteComponent(key) {
  if (!confirm(`Delete component "${key}"?`)) return;
  delete configData.value[activeSubTab.value][key];
  hasChanges.value = true;
}

function addEmployee() {
  const id = prompt('Unique role key (e.g. network_engineer):');
  if (!id) return;
  if (configData.value[id]) { addToast('Key exists!', 'error'); return; }
  configData.value[id] = { name: 'New Role', description: '', base_salary: 20, hiring_cost: 1000, efficiency: 1.0, stress_resistance: 1.0 };
  hasChanges.value = true;
}

function addMarketing() {
  const id = prompt('Campaign key (e.g. podcast_sponsorship):');
  if (!id) return;
  if (configData.value[id]) { addToast('Key exists!', 'error'); return; }
  configData.value[id] = { name: 'New Campaign', cost: 1000, duration: 60, effectiveness: 1.0, reputation_gain: 1.0, min_reputation: 0 };
  hasChanges.value = true;
}

function addProduct() {
  const id = prompt('Product key (e.g. cdn_hosting):');
  if (!id) return;
  if (configData.value[id]) { addToast('Key exists!', 'error'); return; }
  configData.value[id] = { name: 'New Product', min_level: 1, base_price: 50, requirements: { cpu: [1, 4], ram: [2, 8], storage: [10, 100], bandwidth: [10, 100] } };
  hasChanges.value = true;
}

function addResearch() {
  const id = prompt('Tech ID (e.g. security_v1):');
  if (!id) return;
  if (configData.value[id]) { addToast('ID exists!', 'error'); return; }
  configData.value[id] = { name: 'New Tech', description: '', cost: 1000, duration: 120, category: 'infrastructure', effect: { type: 'power_efficiency', value: 0.05 }, prerequisites: [] };
  hasChanges.value = true;
}

function addComponent() {
  const id = prompt(`Component key for ${activeSubTab.value}:`);
  if (!id) return;
  if (configData.value[activeSubTab.value][id]) { addToast('Key exists!', 'error'); return; }
  const base = { name: 'New Component', price: 100, level_required: 1, power_draw_w: 10 };
  if (activeSubTab.value === 'cpu') Object.assign(base, { manufacturer: '', cores: 4, threads: 4, clock_ghz: 2.0, heat_output_w: 65 });
  if (activeSubTab.value === 'ram') Object.assign(base, { size_gb: 16 });
  if (activeSubTab.value === 'storage') Object.assign(base, { size_tb: 1, type: 'SSD' });
  if (activeSubTab.value === 'motherboard') Object.assign(base, { size_u: 1, cpu_slots: 1, ram_slots: 4, storage_slots: 2 });
  configData.value[activeSubTab.value][id] = base;
  hasChanges.value = true;
}

function updatePrereqs(tech, event) {
  tech.prerequisites = event.target.value.split(',').map(s => s.trim()).filter(Boolean);
  hasChanges.value = true;
}

function formatKeyName(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// ─── INIT ───
onMounted(() => loadConfig());
</script>

<style scoped>
.cfg-container { display: flex; flex-direction: column; gap: 0; position: relative; }

/* HEADER */
.cfg-header { margin-bottom: 24px; }
.cfg-title-row { display: flex; align-items: flex-start; justify-content: space-between; }
.cfg-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.03em; color: #fafafa; margin: 0; }
.cfg-subtitle { font-size: 0.7rem; color: #52525b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }

.header-actions { display: flex; gap: 10px; }

.action-btn-header {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 10px;
  font-size: 0.7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  cursor: pointer; transition: all 0.2s; border: 1px solid #222;
}
.reload-btn { background: #111; color: #a1a1aa; }
.reload-btn:hover { background: #1a1a1a; color: white; border-color: #333; }
.reload-btn.spin svg { animation: spin360 0.8s ease; }
.commit-btn { background: #052e16; color: #4ade80; border-color: #15803d; }
.commit-btn:hover:not(:disabled) { background: #14532d; }
.commit-btn:disabled { opacity: 0.35; cursor: not-allowed; }
@keyframes spin360 { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* LOADER */
.loader-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 16px; padding: 80px 0; color: #3f3f46;
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em;
}
.loader-ring {
  width: 32px; height: 32px; border: 2px solid #18181b;
  border-top-color: #3b82f6; border-radius: 50%;
  animation: spin360 0.8s linear infinite;
}

/* FLAT GRID (engine constants) */
.flat-grid { display: flex; flex-direction: column; gap: 8px; }
.flat-row {
  display: flex; align-items: center; justify-content: space-between; gap: 20px;
  padding: 14px 18px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 12px;
}
.flat-info { display: flex; flex-direction: column; gap: 2px; }
.flat-name { font-size: 0.8rem; font-weight: 700; color: #d4d4d8; }
.flat-key { font-size: 0.6rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; }
.flat-input {
  width: 140px; height: 36px; padding: 0 12px;
  background: #111; border: 1px solid #222; border-radius: 8px;
  color: #fafafa; font-size: 0.8rem; font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  outline: none; text-align: right;
}
.flat-input:focus { border-color: #3b82f6; }

/* CARD GRID */
.card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 16px; }
.card-grid.cols-1 { grid-template-columns: 1fr; }

.config-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 16px;
  padding: 20px; transition: all 0.2s;
}
.config-card:hover { border-color: #27272a; }

.card-header {
  display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px;
}
.card-name { font-size: 1rem; font-weight: 900; font-style: italic; color: #fafafa; margin: 0; }
.card-key { font-size: 0.6rem; color: #3f3f46; font-family: 'JetBrains Mono', monospace; display: block; margin-top: 2px; }
.card-header-right { display: flex; align-items: center; gap: 10px; }
.card-del {
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: #111; border: 1px solid #222; color: #52525b;
  cursor: pointer; font-size: 12px; font-weight: 900; transition: all 0.15s;
}
.card-del:hover { background: #450a0a; color: #f87171; border-color: #dc2626; }

/* FIELD GRID */
.field-grid { display: grid; gap: 10px; }
.field-grid.cols-2 { grid-template-columns: repeat(2, 1fr); }
.field-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
.field-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }

.cfg-field label {
  display: block; font-size: 0.55rem; color: #52525b;
  font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 4px;
}
.cfg-field input, .cfg-field select, .cfg-field textarea {
  width: 100%; height: 32px; padding: 0 10px;
  background: #111; border: 1px solid #222; border-radius: 8px;
  color: #fafafa; font-size: 0.72rem; font-weight: 600;
  outline: none; box-sizing: border-box;
}
.cfg-field textarea { height: 56px; padding: 8px 10px; resize: vertical; font-family: inherit; }
.cfg-field select { cursor: pointer; }
.cfg-field input:focus, .cfg-field select:focus, .cfg-field textarea:focus { border-color: #3b82f6; }
.cfg-field.full { margin-bottom: 10px; }

/* INLINE NAME INPUT */
.inline-name-input {
  background: transparent; border: none; border-bottom: 1px solid #27272a;
  color: #fafafa; font-size: 1rem; font-weight: 900; font-style: italic;
  outline: none; padding: 2px 0; width: 100%;
}
.inline-name-input:focus { border-color: #3b82f6; }

/* TECH CATEGORY BADGE */
.tech-category {
  font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  padding: 3px 10px; border-radius: 99px; display: inline-block;
}
.tech-category.infrastructure { background: #052e16; color: #4ade80; }
.tech-category.software { background: #0c1222; color: #60a5fa; }
.tech-category.marketing { background: #422006; color: #fbbf24; }

.tech-desc {
  font-size: 0.72rem; color: #a1a1aa; line-height: 1.5;
}

/* REQUIREMENTS */
.requirements-section { margin-top: 12px; padding-top: 12px; border-top: 1px solid #18181b; }
.req-title { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 10px; }
.range-inputs { display: flex; align-items: center; gap: 6px; }
.range-inputs input { flex: 1; }
.range-sep { color: #3f3f46; font-weight: 800; }

/* SUB TABS */
.sub-tabs {
  display: flex; gap: 4px; padding: 4px;
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 14px; margin-bottom: 16px;
}
.sub-tab {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: 10px;
  font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
  color: #52525b; cursor: pointer; white-space: nowrap;
  transition: all 0.2s; border: none; background: transparent;
}
.sub-tab:hover { color: #a1a1aa; background: #111; }
.sub-tab.active { color: #3b82f6; background: #0c1222; }
.sub-count {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 6px;
  background: #18181b; color: #71717a; font-size: 0.6rem; font-weight: 800;
}
.sub-tab.active .sub-count { background: #1e3a5f; color: #60a5fa; }

/* ADD CARD */
.add-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; cursor: pointer; border: 1px dashed #27272a; min-height: 120px;
  font-size: 0.7rem; font-weight: 700; color: #3f3f46; text-transform: uppercase; letter-spacing: 0.1em;
}
.add-card:hover { border-color: #3b82f6; background: #0c1222; color: #60a5fa; }
.add-icon { font-size: 1.5rem; color: #3f3f46; }
.add-card:hover .add-icon { color: #3b82f6; }

/* EMPTY */
.empty-state { text-align: center; padding: 80px 0; color: #3f3f46; font-size: 0.75rem; }

/* UNSAVED */
.unsaved-banner {
  position: sticky; bottom: 0;
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; background: #422006; border: 1px solid #92400e;
  border-radius: 14px; color: #fbbf24;
  font-size: 0.7rem; font-weight: 700; margin-top: 16px;
  animation: fadeUp 0.3s ease;
}
@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
