<template>
  <div class="rte-container">
    <!-- HEADER -->
    <div class="rte-header">
      <div class="rte-title-row">
        <div>
          <h2 class="rte-title">Research Tree Architect</h2>
          <p class="rte-subtitle">Evolutionary Logic — Define the technological roadmap of the Rackora ecosystem.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchTree" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync
           </button>
           <button @click="saveTree" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy Research Matrix
           </button>
        </div>
      </div>

      <!-- SEARCH & FILTERS -->
      <div class="rte-toolbar">
        <div class="search-box">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" class="search-icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="searchQuery" placeholder="Search Technologies..." class="search-input" />
        </div>
        <div class="filter-tabs">
          <button v-for="cat in categories" :key="cat" :class="['filter-tab', { active: activeFilter === cat }]" @click="activeFilter = cat">
            {{ cat }}
          </button>
        </div>
        <button @click="openNewModal" class="add-tech-btn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          New Tech
        </button>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="rte-grid">
      <transition-group name="list">
        <div v-for="(tech, id) in filteredTree" :key="id" class="tech-card" :class="[{ active: selectedId === id }, tech.category]">
          <div class="tech-card-header" @click="selectedId = id">
            <div class="tech-icon-box">
              <span class="tech-cat-tag">{{ tech.category.charAt(0) }}</span>
            </div>
            <div class="tech-main-info">
              <h3 class="tech-name">{{ tech.name }}</h3>
              <span class="tech-id">{{ id }}</span>
            </div>
          </div>
          
          <div class="tech-stats-bar">
            <div class="stat">
              <span class="stat-label">Cost</span>
              <span class="stat-val">${{ tech.cost }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">Time</span>
              <span class="stat-val">{{ tech.duration }}s</span>
            </div>
          </div>

          <div class="tech-actions">
            <button @click="editTech(id)" class="action-icon-btn edit">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button @click="confirmDelete(id)" class="action-icon-btn delete">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
          
          <div class="tech-footer">
            <div class="prereq-count">
              <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="3"><path d="m7 17 9.2-9.2M17 17V7H7"/></svg>
              {{ tech.prerequisites?.length || 0 }} Prereqs
            </div>
            <div class="effect-summary">
               {{ tech.effect?.type }} ({{ tech.effect?.value }})
            </div>
          </div>
        </div>
      </transition-group>
    </div>

    <!-- MODAL: TECH EDITOR -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="tech-modal glass-effect animate-popup">
        <div class="modal-bar" :class="form.category"></div>
        <div class="modal-header">
          <div>
            <h3 class="modal-title font-black italic">{{ editingId ? 'Modify Strategy' : 'Initialize Innovation' }}</h3>
            <p class="modal-subtitle">Define technology parameters and dependencies.</p>
          </div>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Internal Key (ID)</label>
              <input v-model="formId" :disabled="editingId" placeholder="e.g. quantum_cooling_v2" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Display Name</label>
              <input v-model="form.name" placeholder="Technology Name" class="modal-input" />
            </div>
            <div class="form-group span-4">
              <label>Description</label>
              <textarea v-model="form.description" rows="3" class="modal-input" placeholder="What does this technology achieve?"></textarea>
            </div>
            
            <div class="form-group span-2">
              <label>Research Category</label>
              <select v-model="form.category" class="modal-input">
                <option v-for="c in categories.slice(1)" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Cost ($)</label>
              <input v-model.number="form.cost" type="number" class="modal-input" />
            </div>
            <div class="form-group">
              <label>Duration (sec)</label>
              <input v-model.number="form.duration" type="number" class="modal-input" />
            </div>

            <!-- EFFECT SECTION -->
            <div class="form-group span-4 effect-config">
              <label class="section-label">Operational Effect</label>
              <div class="effect-inputs">
                <select v-model="form.effect.type" class="modal-input">
                  <option v-for="e in effectTypes" :key="e" :value="e">{{ e }}</option>
                </select>
                <input v-model="form.effect.value" class="modal-input" placeholder="Value (e.g. 0.05 or 'rack_hv')" />
              </div>
            </div>

            <!-- PREREQUISITES -->
            <div class="form-group span-4">
               <label class="section-label">Prerequisites</label>
               <div class="prereq-selector custom-scrollbar">
                  <div v-for="(t, tid) in techTree" :key="tid" 
                       v-show="tid !== formId"
                       class="prereq-item" 
                       :class="{ active: form.prerequisites.includes(tid) }"
                       @click="togglePrereq(tid)">
                    <span class="prereq-check">{{ form.prerequisites.includes(tid) ? '✓' : '' }}</span>
                    <span class="prereq-name">{{ t.name }}</span>
                    <span class="prereq-id">{{ tid }}</span>
                  </div>
               </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Apply Configuration</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading, requestConfirm } = inject('adminContext');

const loading = ref(true);
const techTree = ref({});
const hasChanges = ref(false);
const searchQuery = ref('');
const activeFilter = ref('ALL');
const selectedId = ref(null);

const categories = ['ALL', 'infrastructure', 'software', 'networking', 'marketing', 'security', 'experimental'];
const effectTypes = ['power_efficiency', 'provisioning_speed', 'lifespan_bonus', 'rep_gain_multiplier', 'unlock_customer_tier', 'security_defense', 'auto_recovery_chance', 'power_cost_reduction', 'unlock', 'latency_reduction', 'ipv4_cost_reduction', 'ddos_resilience', 'bandwidth_capacity_bonus'];

// FETCH DATA
const fetchTree = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    // We look in 'simulation' group for research_tree or fallback
    const configs = res.configs || res.data?.configs;
    let raw = null;
    
    // Find in group map
    for (const group of Object.values(configs)) {
        const item = group.find(c => c.key === 'research_tree');
        if (item) { raw = item.value; break; }
    }
    
    if (raw) {
      techTree.value = raw;
      hasChanges.value = false;
    } else {
      addToast('No research tree config found in database.', 'warning');
    }
  } catch (e) {
    addToast('Failed to acquire research matrix.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveTree = async () => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'research_tree',
            value: techTree.value,
            comment: 'Technological roadmap recalibration.'
        });
        addToast('Lattice successfully synchronized.', 'success');
        hasChanges.value = false;
    } catch (e) {
        addToast(e.message, 'error');
    } finally {
        setGlobalLoading(false);
    }
};

// FILTERING
const filteredTree = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return Object.fromEntries(
    Object.entries(techTree.value).filter(([id, tech]) => {
      const matchSearch = tech.name.toLowerCase().includes(q) || id.toLowerCase().includes(q);
      const matchFilter = activeFilter.value === 'ALL' || tech.category === activeFilter.value;
      return matchSearch && matchFilter;
    })
  );
});

// MODAL LOGIC
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const form = ref({
  name: '',
  description: '',
  category: 'infrastructure',
  cost: 1000,
  duration: 300,
  effect: { type: 'power_efficiency', value: 0.05 },
  prerequisites: []
});

const openNewModal = () => {
  editingId.value = null;
  formId.value = '';
  form.value = {
    name: '',
    description: '',
    category: 'infrastructure',
    cost: 1000,
    duration: 300,
    effect: { type: 'power_efficiency', value: 0.1 },
    prerequisites: []
  };
  showModal.value = true;
};

const editTech = (id) => {
  const tech = techTree.value[id];
  editingId.value = id;
  formId.value = id;
  form.value = JSON.parse(JSON.stringify(tech));
  if (!form.value.prerequisites) form.value.prerequisites = [];
  if (!form.value.effect) form.value.effect = { type: 'unlock', value: '' };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const togglePrereq = (id) => {
  const index = form.value.prerequisites.indexOf(id);
  if (index > -1) form.value.prerequisites.splice(index, 1);
  else form.value.prerequisites.push(id);
};

const applyChanges = () => {
    if (!formId.value) return addToast('Internal ID is required.', 'error');
    if (!form.value.name) return addToast('Display Name is required.', 'error');
    
    // Validate circular dependency (simple)
    if (form.value.prerequisites.includes(formId.value)) {
        return addToast('Self-dependency detected. Protocol rejected.', 'error');
    }

    techTree.value[formId.value] = JSON.parse(JSON.stringify(form.value));
    hasChanges.value = true;
    showModal.value = false;
    addToast(`Configuration applied for ${formId.value}`, 'success');
};

const confirmDelete = (id) => {
    requestConfirm(`De-orbit technology node '${id}'? This may invalidate children dependencies.`, () => {
        delete techTree.value[id];
        // Cleanup prereqs in other techs
        Object.values(techTree.value).forEach(t => {
            if (t.prerequisites) {
                t.prerequisites = t.prerequisites.filter(p => p !== id);
            }
        });
        hasChanges.value = true;
        addToast(`${id} purged from matrix.`, 'info');
    });
};

onMounted(fetchTree);
</script>

<style scoped>
.rte-container { display: flex; flex-direction: column; gap: 32px; }

.rte-header { display: flex; flex-direction: column; gap: 24px; }
.rte-title-row { display: flex; justify-content: space-between; align-items: start; }
.rte-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.rte-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }

.rte-toolbar { display: flex; align-items: center; gap: 16px; background: #0a0a0c; border: 1px solid #18181b; padding: 12px; border-radius: 16px; }

.search-box { position: relative; flex: 1; display: flex; align-items: center; }
.search-icon { position: absolute; left: 16px; color: #3f3f46; }
.search-input { width: 100%; background: #050505; border: 1px solid #18181b; padding: 10px 16px 10px 42px; border-radius: 10px; color: white; font-size: 0.75rem; font-weight: 600; outline: none; }
.search-input:focus { border-color: #3b82f6; }

.filter-tabs { display: flex; gap: 4px; }
.filter-tab { padding: 8px 14px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: #52525b; cursor: pointer; border-radius: 8px; transition: all 0.2s; border: none; background: transparent; }
.filter-tab:hover { background: #111; color: #a1a1aa; }
.filter-tab.active { background: #1e3a5f; color: #60a5fa; }

.add-tech-btn { display: flex; align-items: center; gap: 8px; background: #3b82f6; color: white; border: none; padding: 10px 16px; border-radius: 10px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; }

/* GRID & CARDS */
.rte-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }

.tech-card {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 20px; padding: 20px;
  display: flex; flex-direction: column; gap: 16px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative; overflow: hidden;
}
.tech-card:hover { border-color: #27272a; transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
.tech-card.active { border-color: #3b82f6; background: #0c1222; }

/* Category Accents */
.tech-card::after { content: ''; position: absolute; top: 0; right: 0; width: 40px; height: 40px; background: radial-gradient(circle at top right, var(--cat-color), transparent 70%); opacity: 0.15; }
.infrastructure { --cat-color: #3b82f6; }
.software { --cat-color: #10b981; }
.marketing { --cat-color: #f59e0b; }
.security { --cat-color: #ef4444; }
.experimental { --cat-color: #8b5cf6; }
.networking { --cat-color: #06b6d4; }

.tech-card-header { display: flex; gap: 16px; align-items: center; cursor: pointer; }
.tech-icon-box { width: 42px; height: 42px; border-radius: 12px; background: #111; border: 1px solid #1c1c1e; display: flex; align-items: center; justify-content: center; }
.tech-cat-tag { font-size: 1rem; font-weight: 900; font-style: italic; color: var(--cat-color); }

.tech-name { font-size: 0.85rem; font-weight: 900; color: white; margin: 0; letter-spacing: -0.02em; }
.tech-id { font-size: 0.55rem; color: #3f3f46; font-family: var(--sys-font-mono); font-weight: 700; text-transform: uppercase; }

.tech-stats-bar { display: flex; gap: 12px; }
.stat { flex: 1; display: flex; flex-direction: column; gap: 2px; padding: 8px; background: #050505; border-radius: 8px; }
.stat-label { font-size: 0.5rem; color: #3f3f46; font-weight: 800; text-transform: uppercase; }
.stat-val { font-size: 0.7rem; color: #a1a1aa; font-weight: 700; font-family: var(--sys-font-mono); }

.tech-actions { display: flex; gap: 8px; position: absolute; top: 20px; right: 20px; }
.action-icon-btn { width: 28px; height: 28px; border-radius: 8px; background: #111; border: 1px solid #1c1c1e; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #3f3f46; transition: all 0.2s; }
.action-icon-btn.edit:hover { background: #0c1222; color: #3b82f6; border-color: #1e3a5f; }
.action-icon-btn.delete:hover { background: #450a0a; color: #ef4444; border-color: #991b1b; }

.tech-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #18181b; padding-top: 12px; margin-top: 4px; }
.prereq-count { display: flex; align-items: center; gap: 4px; font-size: 0.58rem; font-weight: 800; color: #3f3f46; text-transform: uppercase; }
.effect-summary { font-size: 0.55rem; font-weight: 700; color: #27272a; font-family: var(--sys-font-mono); }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.tech-modal { width: 800px; max-height: 90vh; background: #0a0a0c; border: 1px solid #18181b; border-radius: 32px; padding: 40px; display: flex; flex-direction: column; position: relative; }
.modal-bar { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 32px 32px 0 0; }
.modal-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 32px; }
.modal-title { font-size: 1.5rem; margin: 0; color: white; }
.modal-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }
.close-btn { background: #111; border: 1px solid #222; color: #3f3f46; width: 32px; height: 32px; border-radius: 10px; cursor: pointer; }

.modal-body { overflow-y: auto; padding-right: 12px; }
.form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }

.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }
.modal-input { background: #050505; border: 1px solid #18181b; padding: 12px 16px; border-radius: 12px; color: white; font-size: 0.8rem; font-weight: 600; outline: none; transition: border-color 0.2s; }
.modal-input:focus { border-color: #3b82f6; }
select.modal-input { appearance: none; }

.section-label { margin-bottom: 12px; border-bottom: 1px solid #18181b; padding-bottom: 8px; }
.effect-inputs { display: flex; gap: 12px; }
.effect-inputs select { flex: 1; }
.effect-inputs input { flex: 1; text-align: center; font-family: var(--sys-font-mono); }

/* PREREQ SELECTOR */
.prereq-selector { height: 180px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; overflow-y: auto; padding: 12px; background: #050505; border: 1px solid #18181b; border-radius: 16px; }
.prereq-item { 
  display: flex; align-items: center; gap: 10px; padding: 10px; background: #0a0a0c; border: 1px solid #18181b; border-radius: 10px; cursor: pointer; transition: all 0.2s;
}
.prereq-item:hover { border-color: #27272a; }
.prereq-item.active { border-color: #3b82f6; background: #0c1222; }
.prereq-check { width: 14px; height: 14px; border: 1px solid #18181b; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #3b82f6; font-weight: 900; }
.prereq-name { font-size: 0.72rem; font-weight: 700; color: #a1a1aa; flex: 1; }
.prereq-id { font-size: 0.5rem; color: #3f3f46; font-family: var(--sys-font-mono); }

.modal-footer { display: flex; gap: 16px; margin-top: 40px; }
.modal-footer button { flex: 1; }

/* LIST ANIMATION */
.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: scale(0.9); }
</style>
