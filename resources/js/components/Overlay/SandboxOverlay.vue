<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="sandbox-modal glass-panel animation-slide-up">
            <div class="modal-header">
                <div class="header-main">
                    <h2>🧪 Hardware Lab: Sandbox</h2>
                    <p>Simulate configurations with zero risk to your balance or uptime.</p>
                </div>
                <button class="btn-close" @click="$emit('close')">&times;</button>
            </div>

            <div class="modal-body">
                <!-- Left: Component Selection -->
                <div class="selection-pane">
                    <div class="selection-grid">
                        <!-- Motherboard -->
                        <div class="selection-card">
                            <label>Motherboard Base</label>
                            <select v-model="selection.motherboard_key">
                                <option v-for="(mb, key) in catalog.motherboard" :key="key" :value="key">
                                    {{ mb.name }} ({{ mb.size_u }}U, {{ mb.cpu_slots }} slots)
                                </option>
                            </select>
                        </div>

                        <!-- CPU -->
                        <div class="selection-card">
                            <label>Processor (CPU)</label>
                            <div class="picker-row">
                                <select v-model="selection.cpu_key">
                                    <option v-for="(item, key) in catalog.cpu" :key="key" :value="key">
                                        {{ item.name }} ({{ item.cores }} cores)
                                    </option>
                                </select>
                                <div class="counter">
                                    <button @click="selection.cpu_count = Math.max(1, selection.cpu_count - 1)">-</button>
                                    <span>{{ selection.cpu_count }}</span>
                                    <button @click="selection.cpu_count = Math.min(activeMB?.cpu_slots || 1, selection.cpu_count + 1)">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- RAM -->
                        <div class="selection-card">
                            <label>Memory (RAM)</label>
                            <div class="picker-row">
                                <select v-model="selection.ram_key">
                                    <option v-for="(item, key) in catalog.ram" :key="key" :value="key">
                                        {{ item.name }} ({{ item.size_gb }}GB)
                                    </option>
                                </select>
                                <div class="counter">
                                    <button @click="selection.ram_count = Math.max(1, selection.ram_count - 1)">-</button>
                                    <span>{{ selection.ram_count }}</span>
                                    <button @click="selection.ram_count = Math.min(activeMB?.ram_slots || 4, selection.ram_count + 1)">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Storage -->
                        <div class="selection-card">
                            <label>Storage (Drives)</label>
                            <div class="picker-row">
                                <select v-model="selection.storage_key">
                                    <option v-for="(item, key) in catalog.storage" :key="key" :value="key">
                                        {{ item.name }} ({{ item.size_tb }}TB)
                                    </option>
                                </select>
                                <div class="counter">
                                    <button @click="selection.storage_count = Math.max(1, selection.storage_count - 1)">-</button>
                                    <span>{{ selection.storage_count }}</span>
                                    <button @click="selection.storage_count = Math.min(activeMB?.storage_slots || 2, selection.storage_count + 1)">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="blueprint-viewer">
                        <div class="blueprint-bg">
                            <div class="chassis-outline" :class="'u-' + activeMB?.size_u">
                                <div class="component cpu-slots">CPU x{{ selection.cpu_count }}</div>
                                <div class="component ram-slots">RAM x{{ selection.ram_count }}</div>
                                <div class="component storage-slots">DISK x{{ selection.storage_count }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Simulation Results -->
                <div class="results-pane">
                    <div v-if="loading" class="result-loader">
                        <div class="spinner"></div>
                        <p>Processing thermal & power loads...</p>
                    </div>
                    <div v-else-if="result" class="result-content">
                        <div class="result-header">
                            <h3>BUILD REPORT</h3>
                            <div class="status-badge" :class="canAfford ? 'good' : 'bad'">
                                {{ canAfford ? 'VALID CONFIG' : 'INSUFFICIENT FUNDS' }}
                            </div>
                        </div>

                        <div class="main-metrics">
                            <div class="metric-block">
                                <div class="m-val">{{ result.cpu_cores }}</div>
                                <div class="m-label">CORES</div>
                            </div>
                            <div class="metric-block">
                                <div class="m-val">{{ result.ram_gb }}GB</div>
                                <div class="m-label">RAM</div>
                            </div>
                            <div class="metric-block">
                                <div class="m-val">{{ result.storage_tb }}TB</div>
                                <div class="m-label">STORAGE</div>
                            </div>
                        </div>

                        <div class="data-grid">
                            <div class="data-row">
                                <span>Chassis Size</span>
                                <strong>{{ result.size_u }} Rack Units</strong>
                            </div>
                            <div class="data-row">
                                <span>Power Draw</span>
                                <strong :class="result.power_draw_kw > 0.5 ? 'warn' : ''">{{ (result.power_draw_kw * 1000).toFixed(0) }} W</strong>
                            </div>
                            <div class="data-row">
                                <span>Thermal Output</span>
                                <strong>{{ (result.heat_output_kw * 1000).toFixed(0) }} BTU/h</strong>
                            </div>
                            <div class="data-row">
                                <span>VServer Cap</span>
                                <strong>~{{ result.vserver_capacity }} Slots</strong>
                            </div>
                        </div>

                        <div class="cost-summary">
                            <div class="price-pill">
                                <span class="lbl">TOTAL PRICE</span>
                                <span class="val">${{ result.total_price.toLocaleString() }}</span>
                            </div>
                        </div>

                        <div class="sandbox-actions">
                            <button class="btn-purchase-build" @click="startPurchase" :disabled="!canAfford">
                                ⚡ ORDER COMPONENTS
                            </button>
                            <p class="hint">Buying will add parts to your inventory and debit your balance.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();
const catalog = computed(() => gameStore.hardware.catalog || {});

const selection = reactive({
    motherboard_key: 'mb_standard_1u',
    cpu_key: 'cpu_intel_xeon_gold',
    cpu_count: 1,
    ram_key: 'ram_ddr4_16gb',
    ram_count: 1,
    storage_key: 'ssd_standard_1tb',
    storage_count: 1
});

const activeMB = computed(() => catalog.value.motherboard?.[selection.motherboard_key]);
const loading = ref(false);
const result = ref(null);

const canAfford = computed(() => {
    if (!result.value) return false;
    return (gameStore.player?.economy?.balance || 0) >= result.value.total_price;
});

const runSimulation = async () => {
    loading.value = true;
    try {
        const data = await gameStore.simulateBuild(selection);
        if (data) {
            result.value = data;
        }
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    runSimulation();
});

watch(selection, () => {
    // Clamp counts if MB changed
    if (activeMB.value) {
        if (selection.cpu_count > activeMB.value.cpu_slots) selection.cpu_count = activeMB.value.cpu_slots;
        if (selection.ram_count > activeMB.value.ram_slots) selection.ram_count = activeMB.value.ram_slots;
        if (selection.storage_count > activeMB.value.storage_slots) selection.storage_count = activeMB.value.storage_slots;
    }
    runSimulation();
}, { deep: true });

async function startPurchase() {
    if (!confirm('Proceed with purchasing these components? Total: $' + result.value.total_price.toLocaleString())) return;
    
    loading.value = true;
    try {
        // Purchase components sequentially or via atomic bulk if available (MVP: simple loop)
        await gameStore.purchaseComponent('motherboard', selection.motherboard_key);
        for(let i=0; i<selection.cpu_count; i++) await gameStore.purchaseComponent('cpu', selection.cpu_key);
        for(let i=0; i<selection.ram_count; i++) await gameStore.purchaseComponent('ram', selection.ram_key);
        for(let i=0; i<selection.storage_count; i++) await gameStore.purchaseComponent('storage', selection.storage_key);
        
        SoundManager.playSuccess();
        emit('close');
    } catch(e) {
        SoundManager.playError();
    } finally {
        loading.value = false;
    }
}

const emit = defineEmits(['close']);
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.9); backdrop-filter: blur(12px);
    z-index: 4000;
    display: flex; align-items: center; justify-content: center;
}

.sandbox-modal {
    width: 1100px; max-width: 95vw; height: 80vh;
    border: 1px solid #1f6feb66;
    background: #0d1117 url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
    display: flex; flex-direction: column;
    box-shadow: 0 0 50px rgba(31, 111, 235, 0.2);
}

.modal-header {
    padding: 30px; border-bottom: 1px solid #30363d;
    display: flex; justify-content: space-between; align-items: flex-start;
}

.header-main p { color: #8b949e; margin: 4px 0 0; font-size: 0.9rem; }
.btn-close { background: none; border: none; color: #484f58; font-size: 2rem; cursor: pointer; }

.modal-body { flex: 1; display: grid; grid-template-columns: 1fr 380px; overflow: hidden; }

.selection-pane { padding: 30px; display: flex; flex-direction: column; gap: 30px; overflow-y: auto; background: rgba(0,0,0,0.2); }

.selection-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

.selection-card { display: flex; flex-direction: column; gap: 8px; }
.selection-card label { font-size: 0.7rem; font-weight: 800; color: #1f6feb; text-transform: uppercase; letter-spacing: 1px; }

select {
    background: #010409; border: 1px solid #30363d; color: #c9d1d9;
    padding: 10px; border-radius: 6px; font-size: 0.9rem; outline: none;
}
select:focus { border-color: #1f6feb; box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.3); }

.picker-row { display: flex; gap: 10px; }
.picker-row select { flex: 1; }

.counter {
    display: flex; align-items: center; background: #010409; border: 1px solid #30363d; border-radius: 6px; overflow: hidden;
}
.counter button {
    width: 30px; height: 100%; border: none; background: #161b22; color: #fff; cursor: pointer;
}
.counter button:hover { background: #21262d; }
.counter span { width: 30px; text-align: center; font-weight: bold; font-family: monospace; }

.blueprint-viewer {
    flex: 1; background: #010409; border: 1px dashed #30363d; border-radius: 12px;
    position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: center;
}

.blueprint-bg {
    width: 100%; height: 100%;
    background-image: 
        linear-gradient(rgba(31,111,235,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,111,235,0.05) 1px, transparent 1px);
    background-size: 20px 20px;
    display: flex; align-items: center; justify-content: center;
}

.chassis-outline {
    width: 500px; border: 2px solid #58a6ff; background: rgba(88, 166, 255, 0.05);
    border-radius: 4px; padding: 20px; display: grid; gap: 10px;
}
.u-1 { height: 60px; }
.u-2 { height: 120px; }
.u-4 { height: 240px; }

.component { 
    border: 1px solid #58a6ff44; background: rgba(88, 166, 255, 0.1);
    font-size: 0.6rem; color: #58a6ff; display: flex; align-items: center; justify-content: center;
    font-weight: bold; text-transform: uppercase;
}

.results-pane { padding: 30px; border-left: 1px solid #30363d; }

.result-loader { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #8b949e; gap: 20px; }
.spinner { border: 3px solid rgba(255,255,255,0.05); border-top: 3px solid #1f6feb; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.result-content { display: flex; flex-direction: column; height: 100%; }

.result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.result-header h3 { margin: 0; font-size: 0.8rem; letter-spacing: 2px; color: #8b949e; }
.status-badge { font-size: 0.65rem; font-weight: 800; padding: 4px 8px; border-radius: 4px; }
.status-badge.good { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
.status-badge.bad { background: rgba(248, 81, 73, 0.15); color: #f85149; }

.main-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
.metric-block { 
    background: #161b22; border: 1px solid #30363d; padding: 15px; border-radius: 8px; text-align: center;
}
.m-val { font-size: 1.25rem; font-weight: bold; color: #fff; font-family: monospace; }
.m-label { font-size: 0.6rem; color: #8b949e; margin-top: 4px; }

.data-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 40px; }
.data-row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.data-row span { color: #8b949e; }
.data-row strong { color: #c9d1d9; }
.data-row .warn { color: #f85149; }

.cost-summary { 
    margin-top: auto; padding: 20px; background: #161b22; border-radius: 12px;
    border: 1px solid #30363d;
}
.price-pill { display: flex; justify-content: space-between; align-items: center; }
.price-pill .lbl { color: #8b949e; font-weight: bold; }
.price-pill .val { font-size: 1.5rem; font-weight: 800; color: #3fb950; }

.sandbox-actions { margin-top: 20px; }
.btn-purchase-build {
    width: 100%; padding: 18px; background: #1f6feb; border: none; border-radius: 8px;
    color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.2s;
}
.btn-purchase-build:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(31, 111, 235, 0.4); }
.btn-purchase-build:disabled { opacity: 0.3; cursor: not-allowed; }

.hint { font-size: 0.7rem; color: #484f58; text-align: center; margin-top: 10px; }
</style>
