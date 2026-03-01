<template>
  <div class="ea-container">
    <!-- HEADER -->
    <div class="ea-header">
      <div class="ea-title-row">
        <div>
          <h2 class="ea-title">Engine Architecture Nexus</h2>
          <p class="ea-subtitle">Tuning the fundamental laws of the Rackora universe — temporal flow and progression vectors.</p>
        </div>
        <div class="header-actions">
           <button @click="fetchConfigs" class="sys-btn sys-btn-secondary" :disabled="loading">
             <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
             Sync Telemetry
           </button>
           <button @click="saveConfigs" class="sys-btn sys-btn-primary" :disabled="loading || !hasChanges">
             Deploy Kernel Constants
           </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="ea-loading-state">
       <div class="ea-ring"></div>
       <span>Establishing high-bandwidth kernel link...</span>
    </div>

    <div v-else class="ea-grid">
       <!-- TEMPORAL MECHANICS -->
       <div class="ea-panel">
          <div class="panel-header">
             <div class="panel-accent accent-cyan"></div>
             <h3 class="panel-title">Temporal Mechanics</h3>
          </div>
          <div class="ea-field">
             <label>Universe Heartbeat (Tick Rate)</label>
             <div class="ea-slider-row">
                <input type="range" v-model.number="constants.tick_rate_seconds" min="1" max="60" step="1" @input="hasChanges = true" />
                <span class="ea-val text-mono">{{ constants.tick_rate_seconds }}s</span>
             </div>
             <p class="ea-desc">Resolution of the global game loop. Lower values increase simulation fidelity but add server overhead.</p>
          </div>
       </div>

       <!-- PROGRESSION VECTORS -->
       <div class="ea-panel">
          <div class="panel-header">
             <div class="panel-accent accent-purple"></div>
             <h3 class="panel-title">Progression Vectors</h3>
          </div>
          <div class="ea-field-group">
            <div class="ea-field">
               <label>XP Yield Multiplier</label>
               <input type="number" v-model.number="constants.xp_multiplier" step="0.1" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Global scalar for experience acquisition across all operational tasks.</p>
            </div>
            <div class="ea-field">
               <label>Hardware Repair Scalar</label>
               <input type="number" v-model.number="constants.base_repair_cost_multiplier" step="0.05" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Base multiplier for structural restoration of depreciated assets.</p>
            </div>
          </div>
       </div>

       <!-- ECONOMIC CALIBRATION -->
       <div class="ea-panel">
          <div class="panel-header">
             <div class="panel-accent accent-emerald"></div>
             <h3 class="panel-title">Economic Calibration</h3>
          </div>
          <div class="ea-field-group">
            <div class="ea-field">
               <label>Revenue Yield Flux</label>
               <input type="number" v-model.number="constants.revenue_multiplier" step="0.05" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Global scalar for all customer contract cashflows.</p>
            </div>
            <div class="ea-field">
               <label>OpEx Overhead Scalar</label>
               <input type="number" v-model.number="constants.expense_multiplier" step="0.05" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Global multiplier for energy, rent, and salary liabilities.</p>
            </div>
          </div>
       </div>

       <!-- CHAOS VECTORS -->
       <div class="ea-panel">
          <div class="panel-header">
             <div class="panel-accent accent-red"></div>
             <h3 class="panel-title">Simulation Volatility</h3>
          </div>
          <div class="ea-field-group">
            <div class="ea-field">
               <label>Anomalous Event Frequency</label>
               <input type="number" v-model.number="constants.event_frequency_modifier" step="0.1" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Linear modifier for the incidence rate of random hardware and network anomalies.</p>
            </div>
            <div class="ea-field">
               <label>Market Sentiment Oscillator</label>
               <input type="number" v-model.number="constants.market_volatility" step="0.1" min="0" class="ea-num-input" @input="hasChanges = true" />
               <p class="ea-desc">Amplitude of stochastic swings in region energy prices and order demand.</p>
            </div>
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import api from '../../../utils/api';

const { addToast, setGlobalLoading } = inject('adminContext');

const loading = ref(true);
const hasChanges = ref(false);
const constants = ref({
    tick_rate_seconds: 15,
    xp_multiplier: 1.0,
    base_repair_cost_multiplier: 0.2,
    revenue_multiplier: 1.0,
    expense_multiplier: 1.0,
    event_frequency_modifier: 1.0,
    market_volatility: 1.0
});

const fetchConfigs = async () => {
    loading.value = true;
    try {
        const res = await api.get('/admin/configs');
        const groups = res.configs || res.data?.configs;
        let raw = null;
        
        for (const items of Object.values(groups)) {
            const match = items.find(i => i.key === 'engine_constants');
            if (match) { raw = match.value; break; }
        }
        
        if (raw) {
            // Merge with defaults to ensure all architect fields are present
            constants.value = { ...constants.value, ...raw };
            hasChanges.value = false;
        }
    } catch (e) {
        addToast('Kernel telemetry failed.', 'error');
    } finally {
        loading.value = false;
    }
};

const saveConfigs = async () => {
    setGlobalLoading(true);
    try {
        await api.post('/admin/configs/update', {
            key: 'engine_constants',
            value: constants.value,
            comment: 'Engine Architecture refinement via specialized Nexus.'
        });
        addToast('Lattice successfully re-polarized.', 'success');
        hasChanges.value = false;
    } catch (e) {
        addToast(e.message, 'error');
    } finally {
        setGlobalLoading(false);
    }
};

onMounted(fetchConfigs);
</script>

<style scoped>
.ea-container { display: flex; flex-direction: column; gap: 32px; }

.ea-header { display: flex; flex-direction: column; gap: 24px; }
.ea-title-row { display: flex; justify-content: space-between; align-items: start; }
.ea-title { font-size: 1.5rem; font-weight: 900; font-style: italic; letter-spacing: -0.04em; color: white; margin: 0; }
.ea-subtitle { font-size: 0.65rem; color: #52525b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 4px; }

.ea-loading-state { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 100px 0; color: #3f3f46; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.2em; }
.ea-ring { width: 32px; height: 32px; border: 2px solid #18181b; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.ea-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

.ea-panel {
  background: #0a0a0c; border: 1px solid #18181b; border-radius: 24px; padding: 32px;
  display: flex; flex-direction: column; gap: 24px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.ea-panel:hover { border-color: #27272a; transform: translateY(-4px); }

.panel-header { display: flex; align-items: center; gap: 12px; }
.panel-accent { width: 4px; height: 20px; border-radius: 99px; }
.accent-cyan { background: #06b6d4; box-shadow: 0 0 12px rgba(6, 182, 212, 0.4); }
.accent-purple { background: #a855f7; box-shadow: 0 0 12px rgba(168, 85, 247, 0.4); }
.accent-emerald { background: #10b981; box-shadow: 0 0 12px rgba(16, 185, 129, 0.4); }
.accent-red { background: #ef4444; box-shadow: 0 0 12px rgba(239, 68, 68, 0.4); }
.panel-title { font-size: 0.8rem; font-weight: 900; color: white; text-transform: uppercase; letter-spacing: 0.12em; font-style: italic; margin: 0; }

.ea-field { display: flex; flex-direction: column; gap: 10px; }
.ea-field label { font-size: 0.65rem; color: #a1a1aa; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }

.ea-field-group { display: flex; flex-direction: column; gap: 24px; }

.ea-slider-row { display: flex; align-items: center; gap: 20px; background: #050505; padding: 16px; border-radius: 16px; border: 1px solid #111; }
.ea-slider-row input { flex: 1; -webkit-appearance: none; appearance: none; height: 4px; background: #18181b; border-radius: 99px; outline: none; }
.ea-slider-row input::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; border-radius: 6px; background: #06b6d4; cursor: pointer; box-shadow: 0 0 10px rgba(6, 182, 212, 0.5); }
.ea-val { font-size: 1.1rem; color: #06b6d4; font-weight: 900; min-width: 48px; text-align: right; }

.ea-num-input {
  background: #050505; border: 1px solid #18181b; border-radius: 12px; padding: 12px 16px; color: white;
  font-size: 1rem; font-family: 'JetBrains Mono', monospace; font-weight: 800; outline: none; transition: border-color 0.2s;
}
.ea-num-input:focus { border-color: #3b82f6; }

.ea-desc { font-size: 0.6rem; color: #3f3f46; font-weight: 600; font-style: italic; margin: 0; line-height: 1.5; }
</style>
