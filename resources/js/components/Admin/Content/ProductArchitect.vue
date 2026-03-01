<template>
  <div class="pra-container">
    <!-- HEADER -->
    <div class="pra-header">
      <div class="pra-title-row">
        <div>
          <h2 class="pra-title">Product Architect</h2>
          <p class="pra-subtitle">SLA & Contract Logic — Define product tiers, infrastructure requirements, and base pricing.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchProducts" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync
           </button>
           <button @click="saveProducts" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy Product Ledger
           </button>
        </div>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="pra-grid">
      <div v-for="(prod, id) in products" :key="id" class="product-card">
        <div class="product-header">
          <div class="product-meta">
            <h3 class="product-name">{{ prod.name }}</h3>
            <span class="product-id text-mono">{{ id }}</span>
          </div>
          <div class="tier-badge" :class="'tier-' + getTier(prod.min_level)">
            Level {{ prod.min_level }}+
          </div>
        </div>

        <div class="requirement-preview">
          <div v-for="(range, key) in prod.requirements" :key="key" class="req-pill">
            <span class="pill-label">{{ key.toUpperCase() }}</span>
            <span class="pill-range" v-if="Array.isArray(range)">{{ range[0] }} - {{ range[1] }}</span>
            <span class="pill-range" v-else>{{ range }}</span>
          </div>
        </div>

        <div class="price-strip">
          <span class="price-label">Base Hourly Yield</span>
          <span class="price-val">${{ prod.base_price.toLocaleString() }}</span>
        </div>

        <div class="card-footer">
          <button @click="editProduct(id)" class="edit-link">
            Configure Logic
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
          <button @click="confirmDelete(id)" class="delete-btn">✕</button>
        </div>
      </div>

      <!-- ADD CARD -->
      <div class="product-card add-card" @click="openNewModal">
        <div class="add-icon">+</div>
        <span class="add-text">Initialize Product Vector</span>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="pra-modal glass-effect animate-popup">
        <div class="modal-header">
          <div>
            <h3 class="modal-title font-black italic tracking-tighter">{{ editingId ? 'Optimize SLA Model' : 'Product Induction' }}</h3>
            <p class="modal-subtitle">Define hardware constraints and economic impact per unit.</p>
          </div>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div class="modal-body custom-scrollbar">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Service ID (Internal)</label>
              <input v-model="formId" :disabled="editingId" placeholder="e.g. cloud_vps_high" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Public Label</label>
              <input v-model="form.name" placeholder="e.g. Enterprise VPS" class="modal-input" />
            </div>
            
            <div class="form-group span-2">
              <label>Min Player Level</label>
              <input v-model.number="form.min_level" type="number" class="modal-input" />
            </div>
            <div class="form-group span-2">
              <label>Base Hourly Price ($)</label>
              <input v-model.number="form.base_price" type="number" class="modal-input" />
            </div>

            <div class="span-4 requirements-editor">
              <label class="section-label">Requirement Matrix (Min - Max Ranges)</label>
              <div class="ranges-grid">
                <div v-for="key in ['cpu', 'ram', 'storage', 'bandwidth', 'gpu']" :key="key" class="range-row">
                  <div class="range-key text-mono">{{ key.toUpperCase() }}</div>
                  <div class="range-inputs">
                    <input v-model.number="form.requirements[key][0]" type="number" class="modal-input tiny" placeholder="Min" />
                    <span class="range-sep">-</span>
                    <input v-model.number="form.requirements[key][1]" type="number" class="modal-input tiny" placeholder="Max" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="sys-btn sys-btn-secondary">Abort</button>
          <button @click="applyChanges" class="sys-btn sys-btn-primary">Commit SLA Definition</button>
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
const products = ref({});
const hasChanges = ref(false);

const fetchProducts = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/configs');
    const configs = res.configs || res.data?.configs;
    let raw = null;
    
    for (const group of Object.values(configs)) {
        const item = group.find(c => c.key === 'product_definitions');
        if (item) { raw = item.value; break; }
    }
    
    if (raw) {
      products.value = raw;
      hasChanges.value = false;
    }
  } catch (e) {
    addToast('Product ledger sync failed.', 'error');
  } finally {
    loading.value = false;
  }
};

const saveProducts = async () => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'product_definitions',
            value: products.value,
            comment: 'Product portfolio recalibration.'
        });
        addToast('Lattice successfully synchronized.', 'success');
        hasChanges.value = false;
    } catch (e) { addToast(e.message, 'error'); }
    finally { setGlobalLoading(false); }
};

const getTier = (lvl) => {
    if (lvl >= 15) return 'elite';
    if (lvl >= 10) return 'pro';
    if (lvl >= 5) return 'mid';
    return 'entry';
};

// MODAL
const showModal = ref(false);
const editingId = ref(null);
const formId = ref('');
const form = ref({
    name: '',
    min_level: 1,
    base_price: 10,
    requirements: {
        cpu: [1, 2], ram: [2, 4], storage: [10, 50], bandwidth: [100, 500], gpu: [0, 0]
    }
});

const openNewModal = () => {
    editingId.value = null;
    formId.value = '';
    form.value = {
        name: '',
        min_level: 1,
        base_price: 10,
        requirements: {
            cpu: [1, 2], ram: [2, 4], storage: [10, 50], bandwidth: [100, 500], gpu: [0, 0]
        }
    };
    showModal.value = true;
};

const editProduct = (id) => {
    editingId.value = id;
    formId.value = id;
    const p = products.value[id];
    // Ensure all reqs exist to avoid errors in inputs
    const reqs = { cpu: [0,0], ram: [0,0], storage: [0,0], bandwidth: [0,0], gpu: [0,0], ...(p.requirements || {}) };
    // Convert single values to ranges if needed
    Object.keys(reqs).forEach(k => {
        if (!Array.isArray(reqs[k])) reqs[k] = [reqs[k], reqs[k]];
    });
    form.value = { ...p, requirements: reqs };
    showModal.value = true;
};

const closeModal = () => showModal.value = false;

const applyChanges = () => {
    if (!formId.value || !form.value.name) return addToast('ID and Name required.', 'error');
    products.value[formId.value] = JSON.parse(JSON.stringify(form.value));
    hasChanges.value = true;
    showModal.value = false;
    addToast(`${formId.value} protocol successfully committed.`, 'success');
};

const confirmDelete = (id) => {
    requestConfirm(`Liquidate product tier '${id}'? Incoming orders for this vector will cease immediately.`, () => {
        delete products.value[id];
        hasChanges.value = true;
        addToast(`${id} purged from ledger.`, 'info');
    });
};

onMounted(fetchProducts);
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
  display: flex; flex-direction: column; gap: 20px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}
.product-card:hover { border-color: #27272a; transform: translateY(-4px); }

.product-header { display: flex; justify-content: space-between; align-items: start; }
.product-name { font-size: 1.1rem; font-weight: 900; color: white; margin: 0; font-style: italic; letter-spacing: -0.02em; }
.product-id { font-size: 0.55rem; color: #3f3f46; text-transform: uppercase; font-weight: 800; }

.tier-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.55rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; }
.tier-entry { background: #111; color: #52525b; }
.tier-mid { background: #0c1222; color: #3b82f6; }
.tier-pro { background: #0c1c1c; color: #10b981; }
.tier-elite { background: #1c110c; color: #f59e0b; }

.requirement-preview { display: flex; flex-wrap: wrap; gap: 6px; }
.req-pill { padding: 6px 12px; background: #050505; border: 1px solid #18181b; border-radius: 10px; display: flex; align-items: center; gap: 8px; }
.pill-label { font-size: 0.5rem; font-weight: 800; color: #3f3f46; border-right: 1px solid #18181b; padding-right: 8px; }
.pill-range { font-size: 0.65rem; font-weight: 800; color: #a1a1aa; font-family: var(--sys-font-mono); }

.price-strip { padding: 14px; background: #111; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #18181b; }
.price-label { font-size: 0.6rem; font-weight: 800; color: #52525b; text-transform: uppercase; }
.price-val { font-size: 1.1rem; font-weight: 900; color: #4ade80; font-family: var(--sys-font-mono); font-style: italic; }

.card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
.edit-link { display: flex; align-items: center; gap: 8px; background: transparent; border: none; font-size: 0.65rem; font-weight: 900; color: #3b82f6; cursor: pointer; text-transform: uppercase; letter-spacing: 0.1em; }
.edit-link:hover { color: #60a5fa; }
.delete-btn { width: 28px; height: 28px; border-radius: 6px; background: transparent; border: 1px solid #1c1c1e; color: #3f3f46; cursor: pointer; transition: all 0.2s; }
.delete-btn:hover { background: #450a0a; color: #ef4444; border-color: #991b1b; }

/* ADD CARD */
.add-card { border-style: dashed; border-color: #18181b; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; gap: 12px; min-height: 200px; }
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
.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }
.modal-input { background: #050505; border: 1px solid #18181b; padding: 12px 16px; border-radius: 14px; color: white; font-size: 0.85rem; font-weight: 600; outline: none; transition: border-color 0.2s; width: 100%; box-sizing: border-box; }
.modal-input:focus { border-color: #3b82f6; }
.modal-input.tiny { text-align: center; }

.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.6rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }

.ranges-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; background: #050505; border: 1px solid #18181b; padding: 20px; border-radius: 20px; }
.range-row { display: flex; align-items: center; gap: 16px; }
.range-key { font-size: 0.6rem; font-weight: 900; color: #3f3f46; width: 80px; }
.range-inputs { display: flex; align-items: center; gap: 10px; flex: 1; }
.range-sep { color: #18181b; font-weight: 900; }

.modal-footer { display: flex; gap: 16px; margin-top: 40px; }
.modal-footer button { flex: 1; }

.section-label { color: #3b82f6; border-bottom: 1px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 16px; }
</style>
