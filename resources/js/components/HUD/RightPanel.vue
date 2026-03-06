<template>
    <aside class="right-panel" :class="{ 'slide-out-panel': slideOut }">
        <header class="panel-header">
            <div class="header-meta">
                <span class="meta-label">SESSION_TYPE</span>
                <span class="meta-id">{{ selectedServerId ? 'ASSET_INSPECTION' : 'ASSET_ACQUISITION' }}</span>
            </div>
            <button v-if="slideOut" class="close-btn" @click="$emit('close')">&times;</button>
        </header>

        <div class="panel-content">
            <!-- SHOP VIEW -->
            <template v-if="!selectedServerId">
                <nav class="cat-nav">
                    <button v-for="cat in serverCategories" :key="cat.type" class="cat-btn"
                        :class="{ 'active': selectedCategory === cat.type, 'locked': player.economy.level < cat.level }"
                        @click="selectedCategory = cat.type">
                        <span class="cat-label">{{ cat.name.toUpperCase() }}</span>
                    </button>
                </nav>

                <div class="lease-toggle-bar">
                    <div class="lease-toggle" :class="{ 'is-active': isLeasingMode }"
                        @click="isLeasingMode = !isLeasingMode">
                        <div class="toggle-switch"></div>
                        <div class="toggle-text">
                            <span class="main">HARDWARE_LEASING_MODE</span>
                            <span class="sub">{{ isLeasingMode ? 'LOW CAPEX / HIGH OPEX' : 'FULL OWNERSHIP' }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="selectedCategory !== 'hardware_parts' && selectedCategory !== 'experimental'"
                    class="gen-strip">
                    <button v-for="g in generations" :key="g.generation" class="gen-pill"
                        :class="{ 'active': selectedGen === g.generation, 'legacy': g.era === 'legacy', 'nextgen': g.era === 'nextgen' }"
                        @click="selectedGen = g.generation"
                        :title="`${g.name}: ${g.efficiency}x Perf, ${g.power}x Power`">
                        GEN_{{ g.generation }} <small v-if="g.era">{{ g.era.toUpperCase() }}</small>
                    </button>
                </div>

                <div class="hardware-list">
                    <div v-for="(model, key) in filteredModels" :key="key" class="model-row" :id="'shop-item-' + key"
                        draggable="true" @dragstart="onDragStart($event, key, model)" @dragend="onDragEnd">
                        <div class="model-visual">
                            <HardwareIcon
                                :type="selectedCategory === 'hardware_parts' ? model._type : (['dedicated', 'storage_server', 'gpu_server'].includes(selectedCategory) ? 'rack' : 'server')"
                                size="md" />
                        </div>
                        <div class="model-info">
                            <div class="info-top">
                                <span class="model-name">{{ model.name?.toUpperCase() || model.modelName?.toUpperCase()
                                    }}</span>
                                <span class="model-u" v-if="selectedCategory !== 'hardware_parts'">{{ model.sizeU
                                    }}U</span>
                            </div>
                            <div class="spec-line" v-if="selectedCategory !== 'hardware_parts'">
                                <span>{{ getGenSpec(model, 'cpuCores') }}C</span>
                                <span class="sep">/</span>
                                <span>{{ getGenSpec(model, 'ramGb') }}G</span>
                                <span class="sep">/</span>
                                <span>{{ model.storageTb }}T</span>
                            </div>
                            <div class="spec-line" v-else>
                                <span v-if="model._type === 'cpu'">{{ model.cores }} Cores, {{ model.frequency_ghz
                                    }}GHz</span>
                                <span v-else-if="model._type === 'ram'">{{ model.size_gb }}GB {{ model.type }}</span>
                                <span v-else-if="model._type === 'storage'">{{ model.size_tb }}TB {{ model.type
                                    }}</span>
                                <span v-else-if="model._type === 'motherboard'">{{ model.socket }}, {{ model.size_u
                                    }}U</span>
                                <span v-else>GEN_{{ model.generation || 1 }}</span>
                            </div>
                        </div>
                        <div class="model-action">
                            <button class="buy-btn" :class="{ 'is-lease': isLeasingMode }" :id="'buy-btn-' + key"
                                @click.stop="handlePurchase(key)" :disabled="!canAfford(getDisplayPrice(model))">
                                <template v-if="isLeasingMode">
                                    <span class="upfront">${{ getLeaseUpfront(model).toLocaleString() }}</span>
                                    <span class="monthly">${{ getLeaseHourly(model).toFixed(2) }}/h</span>
                                </template>
                                <template v-else>
                                    ${{ getGenPrice(model).toLocaleString() }}
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ASSET DETAILS VIEW -->
            <template v-else-if="selectedServerData">
                <div class="asset-details">
                    <div class="asset-header">
                        <div class="asset-title">
                            <span class="asset-label">ASSET_ID</span>
                            <h3 class="asset-name">{{ selectedServerData.modelName.toUpperCase() }}</h3>
                        </div>
                        <div class="asset-status" :class="`status--${selectedServerData.status}`">
                            {{ selectedServerData.status.toUpperCase() }}
                        </div>
                        <div v-if="selectedServerData.isLeased" class="asset-status status--leased">
                            LEASED
                        </div>
                    </div>

                    <div class="asset-metrics" :class="{ 'v3-state-critical': selectedServerData.health < 20 }">
                        <div class="metric-item">
                            <span class="m-label l2-priority">CONDITION</span>
                            <div class="m-bar-container">
                                <div class="m-bar" :style="{ width: selectedServerData.health + '%' }"
                                    :class="healthClass"></div>
                            </div>
                            <span class="m-val l1-priority">{{ Math.round(selectedServerData.health) }}%</span>
                        </div>
                        <div class="metric-grid">
                            <div class="m-mini l3-priority">
                                <span class="mm-label">WEAR</span>
                                <span class="mm-val">{{ Math.round(selectedServerData.aging?.wearPercentage || 0)
                                    }}%</span>
                            </div>
                            <div class="m-mini l3-priority">
                                <span class="mm-label">EFFICIENCY</span>
                                <span class="mm-val">{{ Math.max(0, 100 -
                                    Math.round((selectedServerData.aging?.efficiencyPenalty || 0) * 100)) }}%</span>
                            </div>
                            <div class="m-mini resale-highlight secondary l2-priority">
                                <span class="mm-label">RESALE_VALUE</span>
                                <span class="mm-val text-success">${{
                                    Math.round(selectedServerData.resaleValue).toLocaleString() }}</span>
                            </div>
                            <div class="m-mini l3-priority">
                                <span class="mm-label">GENERATION</span>
                                <span class="mm-val">GEN_{{ selectedServerData.hardwareGeneration }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="asset-specs l3-priority">
                        <div class="s-row"><span>CPU_CORES</span> <strong>{{ selectedServerData.specs.cpuCores
                                }}</strong></div>
                        <div class="s-row"><span>RAM_CAPACITY</span> <strong>{{ selectedServerData.specs.ramGb
                                }}G</strong></div>
                        <div v-if="selectedServerData.activeOrdersCount > 0" class="s-row workload l1-priority">
                            <span>ACTIVE_WORKLOADS</span>
                            <strong>{{ selectedServerData.activeOrdersCount }} UNIT(S)</strong>
                        </div>
                        <div v-if="selectedServerData.isLeased" class="s-row workload l1-priority">
                            <span>HOURLY_LEASE_RATE</span>
                            <strong>${{ selectedServerData.leaseCostPerHour.toFixed(2) }}/h</strong>
                        </div>
                    </div>

                    <div class="asset-actions">
                        <button v-if="needsRepair(selectedServerData)" class="action-btn primary"
                            :disabled="isRepairing" @click="repairSelectedServer">
                            {{ isRepairing ? 'REPAIRING_IN_PROGRESS...' : `EXECUTE_REPAIR_($${repairCost})` }}
                        </button>

                        <button class="action-btn" @click="$emit('openDetails', selectedServerData.id)">
                            DEEP_INSPECTION_MODE
                        </button>

                        <div class="action-grid">
                            <button v-if="selectedServerData.status === 'online'" class="action-btn danger"
                                @click="gameStore.powerOffServer(selectedServerData.id)">POWER_OFF</button>
                            <button v-else class="action-btn success"
                                @click="gameStore.powerOnServer(selectedServerData.id)">POWER_ON</button>

                            <button
                                v-if="selectedServerData.activeOrdersCount === 0 && selectedServerData.specs?.is_custom"
                                class="action-btn warning" @click="disassembleSelectedServer">DISASSEMBLE</button>
                            <button v-else-if="selectedServerData.activeOrdersCount === 0" class="action-btn warning"
                                @click="sellSelectedServer">LIQUIDATE</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import { storeToRefs } from 'pinia';
import api from '../../utils/api';
import HardwareIcon from '../UI/HardwareIcon.vue';

const props = defineProps({
    slideOut: { type: Boolean, default: false },
    initialCategory: { type: String, default: 'vserver_node' }
});

const gameStore = useGameStore();
const toastStore = useToastStore();

const rooms = computed(() => gameStore.rooms || {});
const hardware = computed(() => gameStore.hardware || {});
const player = computed(() => gameStore.player || {});
const selectedRack = computed(() => gameStore.selectedRack);
const selectedRackId = computed(() => gameStore.selectedRackId);
const selectedServerId = computed(() => gameStore.selectedServerId);

const emit = defineEmits(['openDetails', 'openAssembly', 'close']);

const selectedCategory = ref(props.initialCategory);
watch(() => props.initialCategory, (newVal) => {
    if (newVal) selectedCategory.value = newVal;
});
const serverCatalog = ref({});
const isRepairing = ref(false);
const selectedGen = ref(2);
const generations = ref([]);
const isLeasingMode = ref(false);

const currentGen = computed(() => generations.value?.find(g => g.generation === selectedGen.value));

const getGenSpec = (model, key) => {
    if (selectedCategory.value === 'hardware_parts') return model[key];
    const g = currentGen.value;
    if (!g || model.isExperimental) return model[key];
    return Math.ceil(model[key] * g.efficiency);
};

const getGenPrice = (model) => {
    if (selectedCategory.value === 'hardware_parts') return model.price || 0;
    const g = currentGen.value;
    if (!g || model.isExperimental) return model.purchaseCost;
    return Math.round(model.purchaseCost * g.price);
};

const getLeaseUpfront = (model) => {
    const price = getGenPrice(model);
    return Math.round(price * 0.05); // 5% setup fee
};

const getLeaseHourly = (model) => {
    const price = getGenPrice(model);
    const monthlyRate = selectedCategory.value === 'hardware_parts' ? 0.10 : 0.12;
    return (price * monthlyRate) / 720;
};

const getDisplayPrice = (model) => {
    return isLeasingMode.value ? getLeaseUpfront(model) : getGenPrice(model);
};

const serverCategories = [
    { type: 'inventory', name: 'INVENTORY / LAGER', icon: '📦', level: 1 },
    { type: 'vserver_node', name: 'ENTRY_ASSETS', icon: '🖥️', level: 1 },
    { type: 'dedicated', name: 'ENTERPRISE', icon: '🔧', level: 2 },
    { type: 'storage_server', name: 'STORAGE', icon: '💾', level: 5 },
    { type: 'gpu_server', name: 'HPC_COMPUTE', icon: '🎮', level: 15 },
    { type: 'experimental', name: 'RESTRICTED', icon: '⚛️', level: 20 },
    { type: 'hardware_parts', name: 'COMPONENTS', icon: '📦', level: 1 },
];

const filteredModels = computed(() => {
    if (selectedCategory.value === 'inventory') {
        const items = {};
        (gameStore.hardware?.inventory || []).forEach(item => {
            items[item.id] = {
                ...item,
                name: item.modelName || item.name,
                isFromInventory: true
            };
        });
        return items;
    }
    if (selectedCategory.value === 'hardware_parts') {
        const flat = {};
        for (const [type, items] of Object.entries(hardwareCatalog.value)) {
            for (const [key, item] of Object.entries(items)) {
                flat[`${type}:${key}`] = { ...item, _type: type, _key: key, modelName: item.name };
            }
        }
        return flat;
    }
    return serverCatalog.value[selectedCategory.value] || {};
});

const selectedServerData = computed(() => {
    if (!selectedServerId.value) return null;
    for (const room of Object.values(rooms.value)) {
        for (const rack of (room.racks || [])) {
            for (const server of (rack.servers || [])) {
                if (server.id === selectedServerId.value) return server;
            }
        }
    }
    return null;
});

const needsRepair = (server) => server.health < 80;
const repairCost = computed(() => {
    if (!selectedServerData.value) return 0;
    return Math.round((selectedServerData.value.purchaseCost || 500) * 0.15);
});

async function repairSelectedServer() {
    if (!selectedServerId.value || isRepairing.value) return;
    isRepairing.value = true;
    try {
        await gameStore.repairServer(selectedServerId.value);
    } finally {
        isRepairing.value = false;
    }
}

function canAfford(cost) {
    return player.value.economy.balance >= cost;
}

async function sellSelectedServer() {
    if (!selectedServerId.value) return;
    if (!confirm('Are you sure you want to sell this server?')) return;
    try {
        const res = await api.post('/hardware/sell', { server_id: selectedServerId.value });
        if (res.success) {
            toastStore.success(res.message);
            gameStore.selectServer(null);
            gameStore.loadGameState();
        }
    } catch (e) {
        toastStore.error('Failed to sell server.');
    }
}

async function disassembleSelectedServer() {
    if (!selectedServerId.value) return;
    if (!confirm('Are you sure you want to disassemble this server into parts?')) return;
    try {
        const success = await gameStore.disassembleServer(selectedServerId.value);
        if (success) {
            gameStore.selectServer(null);
            gameStore.loadGameState();
        }
    } catch (e) {
        toastStore.error('Failed to disassemble server.');
    }
}

const hardwareCatalog = ref({});

async function loadCatalog() {
    try {
        const [serverRes, hardwareRes] = await Promise.all([
            api.get('/catalog/servers'),
            api.get('/hardware/catalog')
        ]);

        if (serverRes.success) serverCatalog.value = serverRes.data;
        if (hardwareRes.success) hardwareCatalog.value = hardwareRes.data;
    } catch (error) {
        console.error('Failed to load catalogs', error);
    }
}

async function loadGenerations() {
    try {
        const res = await api.get('/hardware/generations');
        if (res.success) generations.value = res.data;
    } catch (e) { }
}

async function purchaseServer(modelKey) {
    if (!selectedRackId.value) {
        toastStore.warning('SELECT_TARGET_RACK: Click a rack unit in the layout below to select it.');
        return;
    }
    const rack = selectedRack.value;
    const model = filteredModels.value[modelKey];
    const freeSlots = findFreeSlots(rack, model.sizeU);
    if (freeSlots.length === 0) {
        toastStore.error('No space available in rack');
        return;
    }

    if (model.isFromInventory) {
        await gameStore.placeServer(
            rack.id,
            'inventory',
            model.id,
            freeSlots[0],
            selectedGen.value,
            false
        );
    } else {
        await gameStore.placeServer(
            rack.id,
            selectedCategory.value,
            modelKey,
            freeSlots[0],
            selectedGen.value,
            isLeasingMode.value
        );
    }
}

function findFreeSlots(rack, sizeNeeded) {
    if (!rack?.units) return [];
    const totalSlots = rack.units.total;
    const occupied = new Set();
    (rack.servers || []).forEach(s => {
        if (!s) return;
        for (let i = 0; i < s.sizeU; i++) occupied.add(s.startSlot + i);
    });
    const freeSlots = [];
    for (let i = 1; i <= totalSlots - sizeNeeded + 1; i++) {
        let isSlotFree = true;
        for (let j = 0; j < sizeNeeded; j++) {
            if (occupied.has(i + j)) { isSlotFree = false; break; }
        }
        if (isSlotFree) freeSlots.push(i);
    }
    return freeSlots;
}

// Hardware buying logic
async function handlePurchase(modelKey) {
    if (selectedCategory.value === 'hardware_parts') {
        const item = filteredModels.value[modelKey];
        if (!item) return;
        await gameStore.purchaseComponent(item._type, item._key, 'standard', isLeasingMode.value);
    } else {
        await purchaseServer(modelKey);
    }
}


function onDragStart(event, modelKey, model) {
    const dragData = {
        category: selectedCategory.value,
        modelKey,
        sizeU: model.sizeU,
        generation: selectedGen.value,
        isLeased: isLeasingMode.value
    };

    if (model.isFromInventory) {
        dragData.type = 'inventory_server';
        dragData.inventoryId = model.id;
    } else {
        dragData.type = 'new_server';
    }

    event.dataTransfer.setData('application/json', JSON.stringify(dragData));
    gameStore.startDrag({ sizeU: model.sizeU });
}

function onDragEnd() {
    gameStore.endDrag();
}

onMounted(() => {
    loadCatalog();
    loadGenerations();
});

const healthClass = computed(() => {
    if (!selectedServerData.value) return '';
    const h = selectedServerData.value.health;
    if (h > 70) return 'health-good';
    if (h > 30) return 'health-warn';
    return 'health-danger';
});
</script>

<style scoped>
.right-panel {
    width: 380px;
    height: 100%;
    background: var(--ds-bg-elevated);
    border-left: 1px solid var(--ds-border-color);
    display: flex;
    flex-direction: column;
    z-index: 100;
    box-shadow: -4px 0 16px rgba(0, 0, 0, 0.06);
}

.slide-out-panel {
    position: absolute;
    right: 0;
    top: 0;
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.1);
}

.panel-header {
    height: var(--v3-topbar-height);
    padding: 0 20px;
    border-bottom: 1px solid var(--ds-border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--ds-bg-subtle);
}

.header-meta {
    display: flex;
    flex-direction: column;
}

.meta-label {
    font-size: 0.625rem;
    font-weight: 600;
    color: var(--ds-text-ghost);
    letter-spacing: 0.04em;
    margin-bottom: 2px;
}

.meta-id {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--ds-text-primary);
}

.close-btn {
    background: none;
    border: none;
    color: var(--v3-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    padding: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-btn:hover {
    color: #fff;
    transform: rotate(90deg);
}

.panel-content {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.lease-toggle-bar {
    padding: 12px 20px;
    border-bottom: 1px solid var(--ds-border-color);
    background: var(--ds-bg-subtle);
}

.lease-toggle {
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    user-select: none;
}

.toggle-switch {
    width: 36px;
    height: 18px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--v3-border-soft);
    border-radius: 9px;
    position: relative;
    transition: all 0.3s;
}

.toggle-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 12px;
    height: 12px;
    background: var(--v3-text-ghost);
    border-radius: 50%;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.lease-toggle.is-active .toggle-switch {
    background: var(--v3-accent-soft);
    border-color: var(--v3-accent);
}

.lease-toggle.is-active .toggle-switch::after {
    left: 20px;
    background: var(--ds-accent);
}

.toggle-text {
    display: flex;
    flex-direction: column;
}

.toggle-text .main {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-text-primary);
}

.toggle-text .sub {
    font-size: 0.625rem;
    font-weight: 500;
    color: var(--ds-text-ghost);
}

/* SHOP STYLES - V3 ENTERPRISE */
.cat-nav {
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: var(--v3-border-soft);
    border-bottom: var(--v3-border-soft);
}

.cat-btn {
    padding: 16px;
    background: transparent;
    border: none;
    border-right: var(--v3-border-soft);
    border-bottom: var(--v3-border-soft);
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--v3-text-secondary);
    transition: all var(--v3-transition-fast);
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    opacity: 0.7;
}

.cat-btn:hover:not(.locked) {
    color: #fff;
    background: var(--v3-bg-accent);
    opacity: 1;
}

.cat-btn.active {
    background: var(--v3-bg-overlay);
    color: var(--v3-accent);
    opacity: 1;
    box-shadow: inset 0 -2px 0 var(--v3-accent);
}

.cat-btn.locked {
    opacity: 0.2;
    cursor: not-allowed;
    filter: grayscale(1);
}

.gen-strip {
    padding: 12px 20px;
    display: flex;
    gap: 8px;
    border-bottom: 1px solid var(--ds-border-color);
    background: var(--ds-bg-elevated);
}

.gen-pill {
    font-size: 0.55rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
    padding: 4px 10px;
    border: var(--v3-border-soft);
    border-radius: 2px;
    background: transparent;
    cursor: pointer;
    transition: all var(--v3-transition-fast);
}

.gen-pill.active {
    border-color: var(--v3-accent);
    color: var(--v3-accent);
    background: var(--v3-accent-soft);
}

.gen-pill small {
    display: block;
    font-size: 0.35rem;
    opacity: 0.5;
    margin-top: 2px;
}

.gen-pill.legacy {
    border-style: dotted;
}

.gen-pill.nextgen {
    border-color: var(--v3-warning);
    color: var(--v3-warning);
}

.gen-pill.nextgen.active {
    background: rgba(244, 180, 0, 0.1);
}

.hardware-list {
    display: flex;
    flex-direction: column;
}

.model-row {
    padding: 14px 20px;
    border-bottom: 1px solid var(--ds-border-color);
    display: flex;
    gap: 14px;
    align-items: center;
    cursor: grab;
    transition: all 0.15s;
}

.model-row:hover {
    background: var(--ds-bg-subtle);
    box-shadow: inset 3px 0 0 var(--ds-accent);
}

.model-visual {
    width: 48px;
    height: 48px;
    background: var(--ds-bg-subtle);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-md);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.model-thumb {
    width: 60%;
    height: 60%;
    object-fit: contain;
}

.model-row:hover .model-visual {
    background: var(--ds-accent-soft);
}

.model-info {
    flex: 1;
}

.info-top {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 4px;
}

.model-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--ds-text-primary);
}

.model-u {
    font-size: 0.55rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
    font-weight: 700;
}

.spec-line {
    font-size: 0.6rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-secondary);
    font-weight: 600;
}

.spec-line .sep {
    opacity: 0.3;
    margin: 0 6px;
}

.buy-btn {
    padding: 8px 14px;
    background: transparent;
    border: 1px solid var(--v3-border-heavy);
    color: var(--v3-success);
    font-size: 0.7rem;
    font-family: var(--font-family-mono);
    font-weight: 800;
    border-radius: var(--v3-radius);
    transition: all var(--v3-transition-fast);
    cursor: pointer;
}

.buy-btn:hover:not(:disabled) {
    background: var(--v3-success);
    border-color: var(--v3-success);
    color: var(--v3-bg-base);
    transform: translateY(-1px);
}

.buy-btn.is-lease {
    color: var(--v3-accent);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 6px 12px;
    line-height: 1.2;
}

.buy-btn.is-lease .upfront {
    font-size: 0.75rem;
}

.buy-btn.is-lease .monthly {
    font-size: 0.5rem;
    opacity: 0.8;
}

.buy-btn:disabled {
    opacity: 0.2;
    filter: grayscale(1);
    cursor: not-allowed;
}

/* ASSET DETAILS V3 */
.asset-details {
    padding: 32px;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.asset-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.asset-label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.25em;
    margin-bottom: 4px;
}

.asset-name {
    font-size: 1rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.1em;
}

.asset-status {
    font-size: 0.55rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
    padding: 4px 10px;
    border-radius: 2px;
    letter-spacing: 0.1em;
}

.status--online {
    background: rgba(46, 204, 113, 0.1);
    color: var(--v3-success);
    border: 1px solid rgba(46, 204, 113, 0.2);
}

.status--offline {
    background: rgba(0, 0, 0, 0.3);
    color: var(--v3-text-ghost);
    border: 1px solid var(--v3-border-soft);
}

.status--leased {
    background: rgba(88, 166, 255, 0.1);
    color: var(--v3-accent);
    border: 1px solid rgba(88, 166, 255, 0.2);
    margin-left: 8px;
}

.asset-metrics {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.metric-item {
    display: flex;
    align-items: center;
    gap: 16px;
}

.m-label {
    width: 90px;
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.15em;
}

.m-bar-container {
    flex: 1;
    height: 3px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 1px;
    overflow: hidden;
}

.m-bar {
    height: 100%;
    background: var(--v3-accent);
    transition: width var(--v3-transition-slow);
}

.m-bar.health-good {
    background: var(--v3-success);
}

.m-bar.health-warn {
    background: var(--v3-warning);
}

.m-bar.health-danger {
    background: var(--v3-danger);
}

.m-val {
    width: 42px;
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-primary);
    text-align: right;
    font-weight: 700;
}

.metric-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.m-mini {
    background: rgba(0, 0, 0, 0.2);
    padding: 16px;
    border: var(--v3-border-soft);
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-radius: var(--v3-radius);
}

.mm-label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.mm-val {
    font-size: 0.9rem;
    font-family: var(--font-family-mono);
    color: #fff;
    font-weight: 800;
}

.m-mini.resale-highlight {
    background: rgba(46, 204, 113, 0.05);
    border-color: rgba(46, 204, 113, 0.2);
}

.text-success {
    color: var(--v3-success) !important;
}

.asset-specs {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-top: var(--v3-border-soft);
    padding-top: 32px;
}

.s-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--v3-text-secondary);
    font-weight: 600;
}

.s-row strong {
    color: #fff;
    font-family: var(--font-family-mono);
    font-weight: 700;
}

.s-row.workload {
    color: var(--v3-accent);
    margin-top: 12px;
}

.asset-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
}

.action-btn {
    width: 100%;
    padding: 14px;
    background: var(--v3-bg-overlay);
    border: var(--v3-border-soft);
    color: var(--v3-text-primary);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    transition: all var(--v3-transition-fast);
    text-transform: uppercase;
    cursor: pointer;
    border-radius: var(--v3-radius);
}

.action-btn:hover:not(:disabled) {
    background: var(--v3-bg-accent);
    border-color: var(--v3-text-ghost);
    color: #fff;
    transform: translateY(-1px);
}

.action-btn.primary {
    background: var(--v3-accent);
    color: #fff;
    border: none;
    box-shadow: 0 4px 15px var(--v3-accent-soft);
}

.action-btn.primary:hover {
    background: #477fff;
    box-shadow: 0 6px 20px var(--v3-accent-glow);
}

.action-btn.danger {
    color: var(--v3-danger);
    border-color: rgba(255, 77, 79, 0.2);
}

.action-btn.danger:hover {
    background: rgba(255, 77, 79, 0.1);
    border-color: var(--v3-danger);
}

.action-btn.success {
    color: var(--v3-success);
    border-color: rgba(46, 204, 113, 0.2);
}

.action-btn.success:hover {
    background: rgba(46, 204, 113, 0.1);
    border-color: var(--v3-success);
}

.action-btn.warning {
    color: var(--v3-warning);
    border-color: rgba(244, 180, 0, 0.2);
}

.action-btn.warning:hover {
    background: rgba(244, 180, 0, 0.1);
    border-color: var(--v3-warning);
}

.action-btn:disabled {
    opacity: 0.3;
    filter: grayscale(1);
    cursor: not-allowed;
}

.action-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
</style>
