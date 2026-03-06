<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="assembly-modal glass-panel animation-slide-up">
            <div class="modal-header">
                <h2>🛠️ Server Assembly</h2>
                <button class="btn-close" @click="$emit('close')">&times;</button>
            </div>

            <div class="modal-body">
                <!-- Left Column: Selection -->
                <div class="selection-column">
                    <section class="selection-section">
                        <h3>1. Motherboard</h3>
                        <div class="component-grid">
                            <div 
                                v-for="item in inventoryByType('motherboard')" 
                                :key="item.id"
                                class="component-card"
                                :class="{ 'selected': selection.motherboard?.id === item.id }"
                                @click="selection.motherboard = item"
                            >
                                <div class="card-header">
                                    <HardwareIcon type="motherboard" size="sm" />
                                    <div class="card-name">{{ item.name }}</div>
                                    <div class="vendor-tag" :class="item.vendor?.toLowerCase()">{{ item.vendor || 'Generic' }}</div>
                                    <button class="btn-sell-part" @click.stop="sellComponentItem(item)" title="Liquidate Component">💰</button>
                                </div>
                                <div class="card-meta">{{ item.config.size_u }}U | {{ item.config.cpu_slots }} CPU | {{ item.config.ram_slots }} RAM</div>
                            </div>
                            <div v-if="inventoryByType('motherboard').length === 0" class="empty-hint">
                                No motherboards in inventory.
                            </div>
                        </div>
                    </section>

                    <section class="selection-section" :class="{ 'locked': !selection.motherboard }">
                        <h3>2. CPU (Max {{ selection.motherboard?.config.cpu_slots || 0 }})</h3>
                        <div class="component-grid">
                            <div 
                                v-for="item in inventoryByType('cpu')" 
                                :key="item.id"
                                class="component-card"
                                :class="{ 'selected': isSelected(item, 'cpus') }"
                                @click="toggleSelection(item, 'cpus', selection.motherboard?.config.cpu_slots)"
                            >
                                <div class="card-header">
                                    <HardwareIcon type="cpu" size="sm" />
                                    <div class="card-name">{{ item.name }}</div>
                                    <div class="vendor-tag" :class="item.vendor?.toLowerCase()">{{ item.vendor || 'Generic' }}</div>
                                    <button class="btn-sell-part" @click.stop="sellComponentItem(item)" title="Liquidate Component">💰</button>
                                </div>
                                <div class="card-meta">{{ item.config.cores }} Cores | {{ item.config.power_draw_w }}W</div>
                            </div>
                        </div>
                    </section>

                    <section class="selection-section" :class="{ 'locked': !selection.motherboard }">
                        <h3>3. RAM (Max {{ selection.motherboard?.config.ram_slots || 0 }})</h3>
                        <div class="component-grid">
                            <div 
                                v-for="item in inventoryByType('ram')" 
                                :key="item.id"
                                class="component-card"
                                :class="{ 'selected': isSelected(item, 'rams') }"
                                @click="toggleSelection(item, 'rams', selection.motherboard?.config.ram_slots)"
                            >
                                <div class="card-header">
                                    <HardwareIcon type="ram" size="sm" />
                                    <div class="card-name">{{ item.name }}</div>
                                    <div class="vendor-tag" :class="item.vendor?.toLowerCase()">{{ item.vendor || 'Generic' }}</div>
                                    <button class="btn-sell-part" @click.stop="sellComponentItem(item)" title="Liquidate Component">💰</button>
                                </div>
                                <div class="card-meta">{{ item.config.size_gb }}GB | {{ item.config.power_draw_w }}W</div>
                            </div>
                        </div>
                    </section>

                    <section class="selection-section" :class="{ 'locked': !selection.motherboard }">
                        <h3>4. Storage (Max {{ selection.motherboard?.config.storage_slots || 0 }})</h3>
                        <div class="component-grid">
                            <div 
                                v-for="item in inventoryByType('storage')" 
                                :key="item.id"
                                class="component-card"
                                :class="{ 'selected': isSelected(item, 'storages') }"
                                @click="toggleSelection(item, 'storages', selection.motherboard?.config.storage_slots)"
                            >
                                <div class="card-header">
                                    <HardwareIcon type="storage" size="sm" />
                                    <div class="card-name">{{ item.name }}</div>
                                    <div class="vendor-tag" :class="item.vendor?.toLowerCase()">{{ item.vendor || 'Generic' }}</div>
                                    <button class="btn-sell-part" @click.stop="sellComponentItem(item)" title="Liquidate Component">💰</button>
                                </div>
                                <div class="card-meta">{{ item.config.size_tb }}TB | {{ item.config.power_draw_w }}W</div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Right Column: Summary & Deployment -->
                <div class="summary-column">
                    <button class="btn-reset" @click="resetSelection">🔄 Clear All</button>
                    
                    <div class="status-card highlight">
                        <h3>Configuration Summary</h3>
                        <div class="stats-list">
                            <div class="stat-row">
                                <span class="label">Total Cores</span>
                                <span class="value">{{ estimatedStats.cores }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Total RAM</span>
                                <span class="value">{{ estimatedStats.ram }} GB</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Total Storage</span>
                                <span class="value">{{ estimatedStats.storage }} TB</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Est. Power Draw</span>
                                <span class="value">{{ estimatedStats.power }} W</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">vServer Capacity</span>
                                <span class="value text-success">{{ estimatedStats.vservers }}</span>
                            </div>
                            <div v-if="simulationPending" class="simulation-loading">
                                <span class="loading-dots">Logic Calculation Phase...</span>
                            </div>
                        </div>
                    </div>

                    <div class="deployment-card">
                        <h3>Deployment Details</h3>
                        <div class="input-group">
                            <label>Designation (Optional)</label>
                            <input v-model="serverName" type="text" placeholder="e.g. Cluster Node 01">
                        </div>

                        <div class="input-group">
                            <label>Target Rack</label>
                            <select v-model="selectedRackId">
                                <option :value="null" disabled>Select Rack...</option>
                                <option v-for="rack in allRacks" :key="rack.id" :value="rack.id">
                                    {{ rack.name }} ({{ rack.units.available }}U free)
                                </option>
                            </select>
                        </div>

                        <div v-if="selectedRackId" class="input-group">
                            <label>Target Slot</label>
                            <select v-model="targetSlot">
                                <option :value="null" disabled>Select Slot...</option>
                                <option v-for="slot in availableSlots" :key="slot" :value="slot">
                                    Slot {{ slot }}
                                </option>
                            </select>
                        </div>

                        <div class="assembly-actions-v3">
                            <button 
                                class="btn-assemble-final animate-glow" 
                                :disabled="!isReadyToAssemble"
                                @click="handleAssemble"
                            >
                                {{ isReadyToAssemble ? '🚀 Initialize Assembly' : 'Waiting for configuration...' }}
                            </button>

                            <button 
                                class="btn-auto-config l2-priority" 
                                @click="handleAutoConfig"
                                :disabled="simulationPending"
                                title="Automatically pick best components from inventory"
                            >
                                🤖 AUTO_CONFIG
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';
import HardwareIcon from '../UI/HardwareIcon.vue';

const gameStore = useGameStore();

// Replace storeToRefs
const hardware = computed(() => gameStore.hardware);
const rooms = computed(() => gameStore.rooms);

const emit = defineEmits(['close']);

const serverName = ref('');
const selectedRackId = ref(null);
const targetSlot = ref(null);

const selection = reactive({
    motherboard: null,
    cpus: [],
    rams: [],
    storages: []
});

const inventoryByType = (type) => {
    return hardware.value.inventory.filter(c => c.type === type && c.status === 'inventory');
};

const isSelected = (item, type) => {
    return selection[type].some(i => i.id === item.id);
};

const toggleSelection = (item, type, max) => {
    if (!selection.motherboard) return;
    
    const index = selection[type].findIndex(i => i.id === item.id);
    if (index > -1) {
        selection[type].splice(index, 1);
    } else if (selection[type].length < max) {
        selection[type].push(item);
    }
};

const estimatedStats = ref({ cores: 0, ram: 0, storage: 0, power: 0, vservers: 0 });
const simulationPending = ref(false);

watch(selection, async (newVal) => {
    if (!newVal.motherboard || newVal.cpus.length === 0 || newVal.rams.length === 0 || newVal.storages.length === 0) {
        estimatedStats.value = { cores: 0, ram: 0, storage: 0, power: newVal.motherboard?.config.base_power_draw_w || 0, vservers: 0 };
        return;
    }

    try {
        simulationPending.value = true;
        const payload = {
            motherboard_key: newVal.motherboard.component_key || newVal.motherboard.config.key,
            cpu_key: newVal.cpus[0].component_key || newVal.cpus[0].config.key,
            cpu_count: newVal.cpus.length,
            ram_key: newVal.rams[0].component_key || newVal.rams[0].config.key,
            ram_count: newVal.rams.length,
            storage_key: newVal.storages[0].component_key || newVal.storages[0].config.key,
            storage_count: newVal.storages.length
        };
        const res = await api.post('/hardware/simulate', payload);
        if (res.success && res.data) {
            estimatedStats.value = {
                cores: res.data.cpu_cores,
                ram: res.data.ram_gb,
                storage: res.data.storage_tb,
                power: Math.round(res.data.power_draw_kw * 1000), // convert back to W for display if needed
                vservers: res.data.vserver_capacity
            };
        }
    } catch (e) {
        console.error("Simulation failed", e);
    } finally {
        simulationPending.value = false;
    }
}, { deep: true });

function resetSelection() {
    selection.motherboard = null;
    selection.cpus = [];
    selection.rams = [];
    selection.storages = [];
    serverName.value = '';
}

const allRacks = computed(() => {
    const racks = [];
    Object.values(rooms.value).forEach(room => {
        if (room.racks) racks.push(...room.racks);
    });
    return racks;
});

const availableSlots = computed(() => {
    if (!selectedRackId.value || !selection.motherboard) return [];
    
    const rack = allRacks.value?.find(r => r.id === selectedRackId.value);
    if (!rack || !rack.slots) return [];

    const slots = [];
    const sizeNeeded = selection.motherboard.config.size_u;

    for (let i = 1; i <= rack.units.total - sizeNeeded + 1; i++) {
        let canFit = true;
        for (let j = 0; j < sizeNeeded; j++) {
            if (!rack.slots[i + j]?.empty) {
                canFit = false;
                break;
            }
        }
        if (canFit) slots.push(i);
    }
    return slots;
});

const isReadyToAssemble = computed(() => {
    return selection.motherboard && 
           selection.cpus.length > 0 &&
           selection.rams.length > 0 &&
           selection.storages.length > 0 &&
           selectedRackId.value &&
           targetSlot.value;
});

async function handleAssemble() {
    const payload = {
        rack_id: selectedRackId.value,
        slot: targetSlot.value,
        motherboard_id: selection.motherboard.id,
        cpu_ids: selection.cpus.map(c => c.id),
        ram_ids: selection.rams.map(r => r.id),
        storage_ids: selection.storages.map(s => s.id),
        name: serverName.value
    };

    const success = await gameStore.assembleServer(payload);
    if (success) {
        await gameStore.loadGameState();
        emit('close');
    }
}

function handleAutoConfig() {
    const mb = inventoryByType('motherboard')[0];
    if (!mb) {
        useToastStore().error('NO_MOTHERBOARD_IN_INVENTORY');
        return;
    }
    selection.motherboard = mb;
    
    const sortedCpus = [...inventoryByType('cpu')].sort((a,b) => (b.config.tier || 0) - (a.config.tier || 0));
    selection.cpus = sortedCpus.slice(0, mb.config.cpu_slots);
    
    const sortedRams = [...inventoryByType('ram')].sort((a,b) => (b.config.tier || 0) - (a.config.tier || 0));
    selection.rams = sortedRams.slice(0, mb.config.ram_slots);
    
    const sortedStorages = [...inventoryByType('storage')].sort((a,b) => (b.config.tier || 0) - (a.config.tier || 0));
    selection.storages = sortedStorages.slice(0, mb.config.storage_slots);

    useToastStore().success('AUTO_CONFIG_GENERATED: High-density profile selected.');
}

async function sellComponentItem(item) {
    if (!confirm(`Liquidate ${item.name} for 50% of its base value?`)) return;
    try {
        const response = await api.post(`/hardware/components/${item.id}/sell`);
        if (response.success) {
            useToastStore().success(response.message);
            if (selection.motherboard?.id === item.id) selection.motherboard = null;
            selection.cpus = selection.cpus.filter(c => c.id !== item.id);
            selection.rams = selection.rams.filter(r => r.id !== item.id);
            selection.storages = selection.storages.filter(s => s.id !== item.id);
            await gameStore.loadGameState();
        }
    } catch (e) {
        useToastStore().error(e.response?.data?.error || 'Failed to sell component');
    }
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.assembly-modal {
    width: 90vw;
    max-width: 1100px;
    height: 80vh;
    display: flex;
    flex-direction: column;
    background: var(--v3-bg-base);
    border: var(--v3-border-heavy);
    border-radius: var(--v3-radius);
    box-shadow: 0 50px 100px rgba(0,0,0,0.6);
    overflow: hidden;
}

.modal-header {
    padding: 24px 32px;
    background: rgba(0,0,0,0.2);
    border-bottom: var(--v3-border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 0.85rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header h2::before {
    content: '';
    width: 3px;
    height: 12px;
    background: var(--v3-accent);
}

.modal-body {
    flex: 1;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr 340px;
}

.selection-column {
    padding: 32px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.selection-section h3 {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-text-secondary);
    letter-spacing: 0.2em;
    text-transform: uppercase;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.selection-section h3::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--v3-border-soft);
}

.selection-section.locked {
    opacity: 0.3;
    filter: grayscale(1);
    pointer-events: none;
}

.component-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.component-card {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    padding: 16px;
    border-radius: var(--v3-radius);
    cursor: pointer;
    transition: all var(--v3-transition-fast);
}

.component-card:hover {
    border-color: var(--v3-text-ghost);
    background: var(--v3-bg-overlay);
}

.component-card.selected {
    background: var(--v3-accent-soft);
    border-color: var(--v3-accent);
    box-shadow: inset 0 0 0 1px var(--v3-accent);
}

.vendor-tag {
    font-size: 0.5rem;
    font-weight: 900;
    text-transform: uppercase;
    padding: 1px 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.vendor-tag.rack-mate { color: #f1c40f; }
.vendor-tag.gigaparts { color: #3498db; }
.vendor-tag.serverpro { color: #2ecc71; }
.vendor-tag.datavault { color: #e74c3c; }

.card-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.card-name { font-weight: 800; font-size: 0.75rem; color: #fff; letter-spacing: 0.05em; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card-meta { font-size: 0.6rem; font-family: var(--font-family-mono); color: var(--v3-text-ghost); font-weight: 700; }

.btn-sell-part {
    background: transparent;
    border: none;
    cursor: pointer;
    opacity: 0.3;
    transition: opacity 0.2s;
    font-size: 14px;
}
.btn-sell-part:hover {
    opacity: 1;
}

.summary-column {
    background: rgba(0,0,0,0.25);
    padding: 32px;
    border-left: var(--v3-border-soft);
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.status-card {
    padding: 24px;
    background: rgba(255,255,255,0.02);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
}

.status-card h3 {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.2em;
    margin-bottom: 20px;
}

.stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
}

.stat-row .label { color: var(--v3-text-secondary); font-weight: 700; }
.stat-row .value { font-weight: 800; font-family: var(--font-family-mono); color: #fff; }

.deployment-card h3 {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.2em;
    margin-bottom: 20px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.input-group label {
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.15em;
}

.input-group input, .input-group select {
    background: #000;
    border: var(--v3-border-soft);
    color: #fff;
    padding: 10px 12px;
    border-radius: var(--v3-radius);
    font-size: 0.75rem;
    font-family: var(--font-family-mono);
}

.btn-assemble-final {
    width: 100%;
    padding: 16px;
    background: var(--v3-bg-accent);
    color: var(--v3-text-primary);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    cursor: pointer;
    margin-top: auto;
    transition: all var(--v3-transition-fast);
}

.btn-assemble-final:hover:not(:disabled) {
    background: var(--v3-accent);
    color: #fff;
    border-color: var(--v3-accent);
    box-shadow: 0 10px 30px var(--v3-accent-glow);
    transform: translateY(-2px);
}

.btn-assemble-final:disabled {
    opacity: 0.2;
    filter: grayscale(1);
    cursor: not-allowed;
}

.assembly-actions-v3 {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
}

.btn-auto-config {
    background: rgba(0,0,0,0.3);
    border: 1px dashed var(--v3-border-soft);
    color: var(--v3-text-ghost);
    padding: 12px;
    border-radius: var(--v3-radius);
    font-size: 0.65rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-auto-config:hover:not(:disabled) {
    border-color: var(--v3-accent);
    color: var(--v3-accent);
    background: var(--v3-accent-soft);
}

.empty-hint {
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    font-style: italic;
    grid-column: 1 / -1;
    text-align: center;
    padding: 20px;
}

.btn-reset {
    background: transparent;
    border: 1px solid var(--v3-border-soft);
    color: var(--v3-text-ghost);
    padding: 8px 12px;
    border-radius: var(--v3-radius);
    font-size: 0.55rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: all var(--v3-transition-fast);
    align-self: flex-start;
}

.btn-reset:hover {
    color: var(--v3-danger);
    border-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.05);
}

.simulation-loading {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--v3-border-soft);
    text-align: center;
}

.loading-dots {
    font-size: 0.6rem;
    color: var(--v3-accent);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.4; }
    50% { opacity: 1; }
    100% { opacity: 0.4; }
}

.text-primary {
    color: var(--v3-accent) !important;
}

.btn-close {
    font-size: 1.5rem;
    color: var(--v3-text-ghost);
    background: transparent;
    border: none;
    cursor: pointer;
}
.btn-close:hover { color: #fff; }
</style>
