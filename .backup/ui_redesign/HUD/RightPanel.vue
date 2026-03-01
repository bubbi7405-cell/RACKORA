<template>
    <aside class="right-panel">
        <div class="panel-header">
            <button v-if="selectedServerData" class="btn-back" @click="gameStore.selectServer(null)">&larr;</button>
            <h3 class="panel-header__title">{{ selectedServerData ? 'Server Details' : 'Server Shop' }}</h3>
        </div>

        <div class="panel-content">
            <!-- SHOP VIEW -->
            <template v-if="!selectedServerData">
                <!-- Server Categories -->
                <div class="server-categories">
                    <button 
                        v-for="cat in serverCategories" 
                        :key="cat.type"
                        class="category-tab"
                        :class="{ 'category-tab--active': selectedCategory === cat.type }"
                        @click="selectedCategory = cat.type"
                        :disabled="player.economy.level < cat.level"
                    >
                        <span class="category-tab__icon">{{ cat.icon }}</span>
                        <span class="category-tab__name">{{ cat.name }}</span>
                        <span v-if="player.economy.level < cat.level" class="category-tab__lock">Lvl {{ cat.level }}</span>
                    </button>
                </div>

                <!-- Component Models (Modular) -->
                <div v-if="selectedCategory === 'hardware_parts'" class="component-shop">
                    <div class="component-actions">
                         <button class="btn-assemble animate-pulse" @click="$emit('openAssembly')">🛠️ Open Assembly Desk</button>
                    </div>

                    <!-- Incoming Shipments -->
                    <div v-if="incomingCount > 0" class="incoming-shipments">
                        <h4 class="component-group__title">🚢 Incoming Shipments ({{ incomingCount }})</h4>
                        <div class="shipment-list">
                            <template v-for="item in incomingItems" :key="item?.id || Math.random()">
                                <div v-if="item" class="shipment-item">
                                    <div class="shipment-info">
                                        <span class="shipment-name">{{ item.name }}</span>
                                        <span class="shipment-timer" :title="item.arrivalAt">{{ getArrivalCountDown(item.arrivalAt) }}</span>
                                    </div>
                                    <div class="shipment-progress">
                                        <div class="shipment-progress-bar" :style="{ width: getArrivalProgress(item) + '%' }"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-for="(group, type) in hardware.catalog" :key="type" class="component-group">
                        <h4 class="component-group__title">{{ type.toUpperCase() }}</h4>
                        <div class="server-models component-grid-layout">
                            <div 
                                v-for="(item, key) in group" 
                                :key="key"
                                class="server-model component-item"
                                :class="{ 'locked': player.economy.level < (item.level_required || 1) }"
                            >
                                <div class="server-model__header">
                                    <span class="server-model__name">
                                        {{ item.name }}
                                        <span v-if="player.economy.level < (item.level_required || 1)" class="lock-tag">🔒 Lvl {{ item.level_required }}</span>
                                    </span>
                                    <div class="stock-badge" v-if="getInventoryCount(type, key) > 0">
                                        {{ getInventoryCount(type, key) }} in stock
                                    </div>
                                </div>
                                <div class="component-specs">
                                     <span class="spec-tag" v-if="item.cores">{{ item.cores }} Cores</span>
                                     <span class="spec-tag" v-if="item.size_gb">{{ item.size_gb }} GB</span>
                                     <span class="spec-tag" v-if="item.size_tb">{{ item.size_tb }} TB</span>
                                     <span class="spec-tag" v-if="item.size_u">{{ item.size_u }}U</span>
                                     <span class="spec-tag" v-if="item.cpu_slots">{{ item.cpu_slots }} CPU Slots</span>
                                </div>
                                <div class="server-model__footer">
                                    <span class="server-model__price">${{ item.price }}</span>
                                    <div class="buy-actions">
                                        <button class="btn-buy-std" :disabled="!canAfford(item.price) || player.economy.level < (item.level_required || 1)" @click="purchaseComponent(type, key, 'standard')">STD</button>
                                        <button class="btn-buy-exp" :disabled="!canAfford(item.price + 50) || player.economy.level < (item.level_required || 1)" @click="purchaseComponent(type, key, 'express')">EXP</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hardware Generation Selector -->
                <div v-if="selectedCategory !== 'hardware_parts' && selectedCategory !== 'experimental'" class="gen-selector">
                    <div class="gen-selector__label">Hardware Generation</div>
                    <div class="gen-selector__tabs">
                        <button
                            v-for="g in generations"
                            :key="g.generation"
                            class="gen-tab"
                            :class="{ 'gen-tab--active': selectedGen === g.generation, 'gen-tab--legacy': g.isLegacy, 'gen-tab--nextgen': g.era === 'nextgen' }"
                            @click="selectedGen = g.generation"
                        >
                            <span class="gen-tab__num">Gen {{ g.generation }}</span>
                            <span class="gen-tab__hint" v-if="g.isLegacy">Budget</span>
                            <span class="gen-tab__hint" v-else-if="g.era === 'current'">Standard</span>
                            <span class="gen-tab__hint" v-else>Premium</span>
                        </button>
                    </div>
                    <div class="gen-details" v-if="currentGen">
                        <span class="gen-detail">⚡ {{ Math.round((currentGen.efficiency - 1) * 100) }}% perf</span>
                        <span class="gen-detail">🔌 {{ currentGen.power < 1 ? '-' + Math.round((1 - currentGen.power) * 100) + '%' : '+' + Math.round((currentGen.power - 1) * 100) + '%' }} power</span>
                        <span class="gen-detail">💰 {{ currentGen.price }}x price</span>
                    </div>
                </div>

                <!-- Server Models (Standard) -->
                <div v-else-if="selectedCategory !== 'hardware_parts'" class="server-models"></div>
                <div v-if="selectedCategory !== 'hardware_parts'" class="server-models">
                    <div 
                        v-for="(model, key) in filteredModels" 
                        :key="key"
                        class="server-model"
                        :class="{ 
                            'server-model--selected': selectedModel === key,
                            'server-model--experimental': model.isExperimental 
                        }"
                        @click="selectModel(key)"
                        draggable="true"
                        @dragstart="onDragStart($event, key, model)"
                        @dragend="onDragEnd"
                    >
                        <div class="server-model__header">
                            <span class="server-model__name">
                                {{ model.modelName }}
                                <span v-if="model.isExperimental" class="badge-experimental">EXPERIMENTAL</span>
                                <span v-else-if="currentGen" class="badge-gen" :class="'badge-gen--' + currentGen.era">GEN {{ selectedGen }}</span>
                            </span>
                            <span class="server-model__size">{{ model.sizeU }}U</span>
                        </div>
                        
                        <div class="server-model__specs">
                            <div class="spec">
                                <span class="spec__label">CPU</span>
                                <span class="spec__value">{{ getGenSpec(model, 'cpuCores') }} cores</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">RAM</span>
                                <span class="spec__value">{{ getGenSpec(model, 'ramGb') }} GB</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">Storage</span>
                                <span class="spec__value">{{ model.storageTb }} TB</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">Power</span>
                                <span class="spec__value">{{ getGenPower(model) }} kW</span>
                            </div>
                        </div>

                        <div class="server-model__footer">
                            <span class="server-model__price">${{ getGenPrice(model).toLocaleString() }}</span>
                            <button 
                                class="btn-purchase" 
                                @click.stop="purchaseServer(key)"
                                :disabled="!canAfford(getGenPrice(model)) || !selectedRack"
                            >
                                Buy
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Selected Rack Info (Optional footer in shop) -->
                <div v-if="selectedRack" class="selected-rack-info">
                    <div class="rack-info-header">
                        <span class="rack-info-name">🗄️ {{ selectedRack.name }}</span>
                        <span class="rack-info-temp" :class="{ 'text-danger': selectedRack.temperature > 50 }">
                            {{ selectedRack.temperature }}°C
                        </span>
                    </div>

                    <div class="rack-metrics">
                        <div class="rack-metric">
                            <div class="metric-label">
                                <span>Dust Level</span>
                                <span>{{ Math.round(selectedRack.dustLevel) }}%</span>
                            </div>
                            <div class="metric-progress-bg">
                                <div class="metric-progress-fill" 
                                     :style="{ width: selectedRack.dustLevel + '%' }"
                                     :class="getDustClass(selectedRack.dustLevel)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rack-actions">
                        <div class="rack-capacity">
                            <span>{{ selectedRack.units.available }}U Available</span>
                        </div>
                        <button 
                            v-if="selectedRack.dustLevel > 10"
                            class="btn-rack-action btn-clean" 
                            @click="gameStore.cleanRack(selectedRack.id)"
                        >
                            🧹 Clean ($50)
                        </button>
                    </div>
                </div>
            </template>

            <!-- DETAILS VIEW -->
            <template v-else>
                <div class="server-detail-section">
                    <div class="server-detail-card main-card">
                        <div class="server-detail__header">
                            <div class="server-detail__name">
                                {{ selectedServerData.modelName }}
                                <span v-if="selectedServerData.hardwareGeneration" class="badge-gen-detail">Gen {{ selectedServerData.hardwareGeneration }}</span>
                            </div>
                            <div class="server-detail__status-badge" :class="`status--${selectedServerData.status}`">
                                {{ selectedServerData.status }}
                            </div>
                        </div>

                        <div class="server-detail__stats">
                            <div class="compact-spec"><span>CPU</span> <strong>{{ selectedServerData.specs.cpuCores }} Cores</strong></div>
                            <div class="compact-spec"><span>RAM</span> <strong>{{ selectedServerData.specs.ramGb }} GB</strong></div>
                            
                            <!-- Tech Debt / Aging Stats -->
                            <div class="compact-spec" title="Lifespan used. High wear = efficiency loss.">
                                <span>Wear</span> 
                                <strong :class="{'text-warning': (selectedServerData.aging?.wearPercentage || 0) > 80}">
                                    {{ Math.round(selectedServerData.aging?.wearPercentage || 0) }}%
                                </strong>
                            </div>
                            <div class="compact-spec" title="Power efficiency. Lower means higher electricity costs.">
                                <span>Eff.</span> 
                                <strong :class="{'text-danger': (selectedServerData.aging?.efficiencyPenalty || 0) > 0.1}">
                                    {{ Math.max(0, 100 - Math.round((selectedServerData.aging?.efficiencyPenalty || 0) * 100)) }}%
                                </strong>
                            </div>
                        </div>
                        
                        <!-- Health Bar -->
                        <div class="server-detail__health">
                            <div class="health-meta">
                                <span class="health-label">Condition</span>
                                <span class="health-value text-glow">{{ Math.round(selectedServerData.health) }}%</span>
                            </div>
                            <div class="health-bar">
                                <div 
                                    class="health-bar__fill" 
                                    :class="{
                                        'health--good': selectedServerData.health > 70,
                                        'health--warn': selectedServerData.health > 30 && selectedServerData.health <= 70,
                                        'health--danger': selectedServerData.health <= 30,
                                    }"
                                    :style="{ width: selectedServerData.health + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div class="server-detail__footer-actions">
                            <button 
                                v-if="needsRepair(selectedServerData)"
                                class="btn-repair btn-large"
                                :disabled="isRepairing"
                                @click="repairSelectedServer"
                            >
                                🔧 {{ isRepairing ? 'Repairing...' : `Repair Server ($${repairCost})` }}
                            </button>

                            <button 
                                class="btn-action btn-action--info"
                                @click="$emit('openDetails', selectedServerData.id)"
                            >🔬 Deep Inspection</button>

                            <button 
                                v-if="(selectedServerData.aging?.efficiencyPenalty > 0.05 || selectedServerData.health < 95) && selectedServerData.status === 'offline'"
                                class="btn-action btn-action--warning"
                                @click="gameStore.modernizeServer(selectedServerData.id)"
                                title="Restores server to peak efficiency and health"
                            > 🛠️ Modernize (${{ getModernizationCost(selectedServerData) }})</button>
                            
                            <div class="power-controls">
                                <button 
                                    v-if="selectedServerData.status === 'online'" 
                                    class="btn-action btn-action--danger"
                                    @click="gameStore.powerOffServer(selectedServerData.id)"
                                >Power Off</button>
                                <button 
                                    v-else-if="['offline', 'degraded', 'damaged'].includes(selectedServerData.status)"
                                    class="btn-action btn-action--success"
                                    @click="gameStore.powerOnServer(selectedServerData.id)"
                                >Power On</button>
                            </div>
                            
                            <!-- Sell Server -->
                            <button 
                                v-if="selectedServerData.status === 'offline' && selectedServerData.activeOrdersCount === 0"
                                class="btn-action btn-action--warning btn-sell"
                                @click="sellSelectedServer"
                            >
                                💰 Sell (${{ Math.round(selectedServerData.resaleValue || 0).toLocaleString() }})
                            </button>

                            <!-- Disassemble Action -->
                            <button 
                                v-if="selectedServerData.specs?.is_custom"
                                class="btn-action btn-action--danger btn-disassemble"
                                @click="disassembleSelectedServer"
                                :disabled="selectedServerData.status !== 'offline'"
                                title="Server must be offline to disassemble"
                            >
                                ♻️ Disassemble
                            </button>
                        </div>
                    </div>

                    <!-- Active Workloads placeholder -->
                    <div class="server-workloads">
                        <h4 class="section-title">Active Workloads</h4>
                        <div class="workload-list">
                            <div v-if="selectedServerData.activeOrdersCount > 0" class="workload-item">
                                <span class="dot"></span> Running {{ selectedServerData.activeOrdersCount }} instances...
                            </div>
                            <div v-else class="empty-text">No active orders on this server.</div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </aside>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import { storeToRefs } from 'pinia';
import api from '../../utils/api';

const gameStore = useGameStore();
const toastStore = useToastStore();
const { player, selectedRack, selectedRackId, selectedServerId, rooms, hardware } = storeToRefs(gameStore);

const emit = defineEmits(['openDetails', 'openAssembly']);

const selectedCategory = ref('vserver_node');
const selectedModel = ref(null);
const serverCatalog = ref({});
const isRepairing = ref(false);
const selectedGen = ref(2);
const generations = ref([]);

const currentGen = computed(() => generations.value.find(g => g.generation === selectedGen.value));

const getGenSpec = (model, key) => {
    const g = currentGen.value;
    if (!g || model.isExperimental) return model[key];
    return Math.ceil(model[key] * g.efficiency);
};

const getGenPower = (model) => {
    const g = currentGen.value;
    if (!g || model.isExperimental) return model.powerDrawKw;
    return +(model.powerDrawKw * g.power).toFixed(2);
};

const getGenPrice = (model) => {
    const g = currentGen.value;
    if (!g || model.isExperimental) return model.purchaseCost;
    return Math.round(model.purchaseCost * g.price);
};

const serverCategories = [
    { type: 'vserver_node', name: 'VServer', icon: '🖥️', level: 1 },
    { type: 'dedicated', name: 'Dedicated', icon: '🔧', level: 2 },
    { type: 'storage_server', name: 'Storage', icon: '💾', level: 5 },
    { type: 'gpu_server', name: 'GPU', icon: '🎮', level: 15 },
    { type: 'experimental', name: 'Experimental', icon: '⚛️', level: 20 },
    { type: 'hardware_parts', name: 'Hardware', icon: '📦', level: 1 },
];

const filteredModels = computed(() => {
    return serverCatalog.value[selectedCategory.value] || {};
});

// Find selected server data from rooms state
const selectedServerData = computed(() => {
    if (!selectedServerId.value) return null;
    
    for (const room of Object.values(rooms.value)) {
        for (const rack of (room.racks || [])) {
            for (const server of (rack.servers || [])) {
                if (server.id === selectedServerId.value) {
                    return server;
                }
            }
        }
    }
    return null;
});

const needsRepair = (server) => {
    return server.health < 80;
};

const getModernizationCost = (server) => {
    return Math.round((server.purchaseCost || 0) * 0.4 + 200);
};

const upgradeCost = computed(() => {
    if (!selectedServerData.value) return 0;
    return Math.round((selectedServerData.value.purchaseCost || 500) * 0.2);
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

function selectModel(key) {
    selectedModel.value = selectedModel.value === key ? null : key;
}

async function purchaseComponent(type, key, deliveryType = 'standard') {
    await gameStore.purchaseComponent(type, key, deliveryType);
}

const incomingItems = computed(() => {
    return (gameStore.hardware.inventory || []).filter(i => i.status === 'delivering');
});

const incomingCount = computed(() => incomingItems.value.length);

const getArrivalCountDown = (arrivalAt) => {
    if (!arrivalAt) return '--:--';
    const diff = new Date(arrivalAt) - new Date();
    if (diff <= 0) return 'Arriving...';
    
    const mins = Math.floor(diff / 60000);
    const secs = Math.floor((diff % 60000) / 1000);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const getArrivalProgress = (item) => {
    if (!item.arrivalAt) return 100;
    const arrival = new Date(item.arrivalAt).getTime();
    const purchased = new Date(item.purchasedAt || new Date().getTime() - 120000).getTime();
    const now = new Date().getTime();
    
    if (now >= arrival) return 100;
    
    const total = arrival - purchased;
    const elapsed = now - purchased;
    return Math.min(100, Math.max(0, (elapsed / total) * 100));
};

function getInventoryCount(type, key) {
    return (gameStore.hardware.inventory || []).filter(item => 
        item.type === type && item.key === key && item.status === 'inventory'
    ).length;
}

async function disassembleSelectedServer() {
     if (!selectedServerId.value) return;
     if (confirm('Are you sure you want to disassemble this server? All parts will be returned to your inventory.')) {
         const success = await gameStore.disassembleServer(selectedServerId.value);
         if (success) {
             gameStore.selectServer(null);
         }
     }
}

async function loadCatalog() {
    try {
        const response = await api.get('/catalog/servers');
        if (response.success) {
            serverCatalog.value = response.data;
        }
    } catch (error) {
        console.error('Failed to load server catalog:', error);
    }
}

async function purchaseServer(modelKey) {
    if (!selectedRackId.value) {
        toastStore.warning('Select a rack first');
        return;
    }

    const rack = selectedRack.value;
    if (!rack) return;

    // Find first available slot
    const model = filteredModels.value[modelKey];
    const freeSlots = findFreeSlots(rack, model.sizeU);
    
    if (freeSlots.length === 0) {
        toastStore.error('No space available in rack');
        return;
    }

    const result = await gameStore.placeServer(
        rack.id,
        selectedCategory.value,
        modelKey,
        freeSlots[0],
        selectedGen.value
    );

    if (result.success) {
        selectedModel.value = null;
    }
}

function findFreeSlots(rack, sizeNeeded) {
    if (!rack || !rack.units || !rack.servers) return [];
    
    const totalSlots = rack.units.total;
    const occupied = new Set();
    
    // Mark occupied
    rack.servers.forEach(s => {
        if (!s) return;
        for (let i = 0; i < s.sizeU; i++) {
            occupied.add(s.startSlot + i);
        }
    });

    const freeSlots = [];
    // Iterate all possible start positions
    for (let i = 1; i <= totalSlots - sizeNeeded + 1; i++) {
        let isSlotFree = true;
        // Check if all needed units for this position are free
        for (let j = 0; j < sizeNeeded; j++) {
            if (occupied.has(i + j)) {
                isSlotFree = false;
                break;
            }
        }
        
        if (isSlotFree) {
            freeSlots.push(i);
        }
    }
    
    return freeSlots;
}

function onDragStart(event, modelKey, model) {
    const dragData = JSON.stringify({
        type: 'new_server',
        category: selectedCategory.value,
        modelKey,
        sizeU: model.sizeU,
    });
    
    event.dataTransfer.setData('application/json', dragData);
    event.dataTransfer.setData('text/plain', dragData); // Fallback for some browsers/OS
    
    gameStore.startDrag({ sizeU: model.sizeU }); // Notify store of drag size
    event.dataTransfer.effectAllowed = 'copy';
    console.log('Drag started:', { type: 'new_server', category: selectedCategory.value, modelKey, sizeU: model.sizeU });
}

function onDragEnd() {
    gameStore.endDrag();
}

async function sellSelectedServer() {
    if (!selectedServerId.value) return;
    if (!confirm('Are you sure you want to sell this server? You will receive its depreciated resale value.')) return;
    try {
        const res = await api.post('/hardware/sell', { server_id: selectedServerId.value });
        if (res.success) {
            toastStore.success(res.message);
            gameStore.selectServer(null);
            gameStore.loadGameState();
        } else {
            toastStore.error(res.message);
        }
    } catch (e) {
        toastStore.error('Failed to sell server.');
    }
}

async function loadGenerations() {
    try {
        const res = await api.get('/hardware/generations');
        if (res.success) generations.value = res.data;
    } catch (e) {
        console.error('Failed to load generations:', e);
    }
}

onMounted(() => {
    loadCatalog();
    loadGenerations();
});
const getDustClass = (dust) => {
    if (dust > 70) return 'progress-red';
    if (dust > 40) return 'progress-yellow';
    return 'progress-green';
};
</script>

<style scoped>
.right-panel {
    grid-area: right-panel;
    background: linear-gradient(270deg, rgba(15, 20, 25, 0.98) 0%, rgba(22, 27, 34, 0.95) 100%);
    border-left: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.panel-content {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-md);
}

.server-categories {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-xs);
    margin-bottom: var(--space-md);
}

.category-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--space-sm);
    background: var(--color-bg-elevated);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
}

.category-tab:hover:not(:disabled) {
    background: var(--color-bg-light);
}

.category-tab--active {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.category-tab:disabled {
    opacity: 0.4;
}

.category-tab__icon {
    font-size: 1.25rem;
}

.category-tab__name {
    font-size: var(--font-size-xs);
    margin-top: 2px;
}

.category-tab__lock {
    font-size: var(--font-size-xs);
    color: var(--color-warning);
}

.server-models {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    margin-bottom: var(--space-lg);
}

.server-model {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    cursor: grab;
    transition: all var(--transition-fast);
    user-select: none;
}

.server-model:hover {
    border-color: var(--color-primary);
}

.server-model--experimental {
    border: 1px solid #7c3aed; /* Indigo border */
    position: relative;
    overflow: hidden;
}

.server-model--experimental::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.05) 0%, transparent 100%);
    pointer-events: none;
}

.badge-experimental {
    display: inline-block;
    padding: 1px 4px;
    background: #7c3aed;
    color: white;
    font-size: 8px;
    font-weight: bold;
    border-radius: 4px;
    margin-left: 6px;
    vertical-align: middle;
    text-transform: uppercase;
    box-shadow: 0 0 5px rgba(124, 58, 237, 0.5);
}

.server-model--selected {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.server-model:active {
    cursor: grabbing;
}

.server-model__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
}

.server-model__name {
    font-weight: 600;
    font-size: var(--font-size-sm);
}

.server-model__size {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-xs);
    color: var(--color-primary);
    background: var(--color-primary-dim);
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius-sm);
}

.server-model__specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-xs);
    margin-bottom: var(--space-sm);
}

.spec {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-xs);
}

.spec__label {
    color: var(--color-text-muted);
}

.spec__value {
    font-family: var(--font-family-mono);
}

.server-model__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: var(--space-sm);
    border-top: 1px solid var(--color-border);
}

.server-model__price {
    font-family: var(--font-family-mono);
    font-weight: 600;
    color: var(--color-success);
}

.btn-purchase {
    padding: var(--space-xs) var(--space-md);
    background: var(--color-primary);
    color: var(--color-bg-deep);
    font-weight: 600;
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.btn-purchase:hover:not(:disabled) {
    background: var(--color-primary);
    filter: brightness(1.2);
}

.btn-purchase:disabled {
    background: var(--color-bg-light);
    color: var(--color-text-muted);
}

/* Logistic Styles */
.incoming-shipments {
    margin-bottom: var(--space-md);
    background: rgba(31, 111, 235, 0.05);
    border: 1px dashed rgba(31, 111, 235, 0.3);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
}

.shipment-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.shipment-item {
    background: var(--color-bg-deep);
    border-radius: var(--radius-sm);
    padding: var(--space-xs) var(--space-sm);
}

.shipment-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    margin-bottom: 4px;
}

.shipment-name { color: #8b949e; }
.shipment-timer { color: #58a6ff; font-family: var(--font-family-mono); font-weight: bold; }

.shipment-progress {
    height: 3px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    overflow: hidden;
}

.shipment-progress-bar {
    height: 100%;
    background: #1f6feb;
    box-shadow: 0 0 10px rgba(31, 111, 235, 0.5);
    transition: width 1s linear;
}

.buy-actions {
    display: flex;
    gap: 4px;
}

.buy-actions button {
    padding: 2px 8px;
    font-size: 0.65rem;
    font-weight: 800;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid rgba(255,255,255,0.1);
    transition: 0.2s;
}

.btn-buy-std { background: #21262d; color: #c9d1d9; }
.btn-buy-std:hover:not(:disabled) { background: #30363d; border-color: #8b949e; }

.btn-buy-exp { background: #1f6feb; color: #fff; }
.btn-buy-exp:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 0 10px rgba(31, 111, 235, 0.3); }

.buy-actions button:disabled { opacity: 0.3; cursor: not-allowed; }

.locked {
    filter: grayscale(0.8) opacity(0.8);
    pointer-events: none;
    position: relative;
    border-color: rgba(248, 81, 73, 0.1) !important;
}

.lock-tag {
    font-size: 0.6rem;
    color: #f85149;
    background: rgba(248, 81, 73, 0.1);
    padding: 2px 4px;
    border-radius: 4px;
    margin-left: 4px;
}

.selected-rack-info {
    margin-top: var(--space-lg);
}

.section-title {
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-text-secondary);
    margin-bottom: var(--space-sm);
}

.rack-info-card {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-primary);
    border-radius: var(--radius-md);
    padding: var(--space-md);
}

.rack-info-card__name {
    font-weight: 600;
    margin-bottom: var(--space-sm);
}

.rack-info-card__stats {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.rack-stat {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-sm);
}

.rack-stat__label {
    color: var(--color-text-muted);
}

.rack-stat__value {
    font-family: var(--font-family-mono);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--space-xl);
    color: var(--color-text-muted);
    text-align: center;
}

.empty-state__icon {
    font-size: 2rem;
    margin-bottom: var(--space-sm);
}

.empty-state__text {
    font-size: var(--font-size-sm);
}

/* Server Detail Panel */
.server-detail-card {
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
}

.server-detail__name {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    margin-bottom: var(--space-xs);
}

.server-detail__status {
    display: inline-block;
    font-size: var(--font-size-xs);
    font-weight: 600;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: var(--space-sm);
}

.status--online { background: rgba(46, 160, 67, 0.2); color: #3fb950; }
.status--offline { background: rgba(139, 148, 158, 0.2); color: #8b949e; }
.status--degraded { background: rgba(210, 153, 34, 0.2); color: #d29922; }
.status--damaged { background: rgba(248, 81, 73, 0.2); color: #f85149; }
.status--provisioning { background: rgba(56, 132, 255, 0.2); color: #388bfd; }

.server-detail__health {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin-bottom: var(--space-sm);
}

.health-label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    width: 40px;
    flex-shrink: 0;
}

.health-bar {
    flex: 1;
    height: 6px;
    background: var(--color-bg-dark, #161b22);
    border-radius: 3px;
    overflow: hidden;
}

.health-bar__fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease, background-color 0.3s ease;
}

.health--good { background: #3fb950; }
.health--warn { background: #d29922; }
.health--danger { background: #f85149; }

.health-value {
    font-size: var(--font-size-xs);
    font-family: var(--font-family-mono);
    color: var(--color-text-muted);
    width: 35px;
    text-align: right;
    flex-shrink: 0;
}

.btn-repair {
    width: 100%;
    padding: var(--space-sm) var(--space-md);
    background: linear-gradient(135deg, #da6b2b, #d29922);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
    margin-bottom: var(--space-sm);
}

.btn-repair:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(210, 153, 34, 0.3);
}

.btn-repair:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.server-detail__actions {
    display: flex;
    gap: var(--space-xs);
}

.btn-back {
    background: transparent;
    border: none;
    color: var(--color-text-muted);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0 10px 0 0;
    transition: color 0.2s;
}
.btn-back:hover { color: var(--color-primary); }

.selected-rack-info.minimal {
    margin-top: auto;
    padding-top: var(--space-md);
    border-top: 1px solid var(--color-border);
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

/* Updated Details View Styles */
.server-detail-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-lg);
}

.server-detail-card.main-card {
    background: rgba(var(--color-primary-rgb), 0.03);
    border: 1px solid rgba(var(--color-primary-rgb), 0.2);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.server-detail__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-md);
}

.server-detail__status-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}

.server-detail__stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: var(--space-lg);
}

.compact-spec {
    font-size: 0.75rem;
    display: flex;
    justify-content: space-between;
    background: rgba(0,0,0,0.2);
    padding: 4px 8px;
    border-radius: 4px;
}

.health-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.text-glow {
    text-shadow: 0 0 10px rgba(var(--color-primary-rgb), 0.5);
}

.btn-large {
    padding: 12px !important;
    font-size: 0.9rem !important;
}

.btn-action--info {
    border-color: rgba(var(--color-primary-rgb), 0.3);
    color: var(--color-primary);
    margin-top: var(--space-md);
    width: 100%;
}

.workload-list {
    background: rgba(0,0,0,0.2);
    border-radius: 6px;
    padding: 10px;
    min-height: 60px;
}

.workload-item {
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dot {
    width: 6px;
    height: 6px;
    background: var(--color-success);
    border-radius: 50%;
    box-shadow: 0 0 5px var(--color-success);
}

.empty-text {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    font-style: italic;
    text-align: center;
    padding-top: 15px;
}

.btn-action {
    flex: 1;
    padding: var(--space-xs) var(--space-sm);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
    background: var(--color-bg-elevated);
    color: var(--color-text-primary);
}

.btn-action--danger {
    border-color: rgba(248, 81, 73, 0.3);
    color: #f85149;
}

.btn-action--warning {
    border-color: rgba(255, 180, 0, 0.3);
    color: #ffb400;
}

.btn-action--warning:hover {
    background: rgba(255, 180, 0, 0.1);
}

.power-controls {
    display: flex;
    gap: var(--space-xs);
    width: 100%;
    margin-top: var(--space-sm);
}

.btn-action--danger:hover {
    background: rgba(248, 81, 73, 0.15);
}

.btn-action--success {
    border-color: rgba(46, 160, 67, 0.3);
    color: #3fb950;
}

.btn-action--success:hover {
    background: rgba(46, 160, 67, 0.15);
}
/* Selected Rack Info Section */
.selected-rack-info {
    margin-top: auto;
    background: rgba(var(--color-primary-rgb), 0.05);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.rack-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}

.rack-info-temp {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-sm);
}

.rack-metrics {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.metric-label {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    margin-bottom: 2px;
}

.metric-progress-bg {
    height: 6px;
    background: rgba(0,0,0,0.3);
    border-radius: 3px;
    overflow: hidden;
}

.metric-progress-fill {
    height: 100%;
    transition: width 0.3s ease, background-color 0.3s ease;
}

.progress-green { background: var(--color-success); box-shadow: 0 0 8px var(--color-success); }
.progress-yellow { background: var(--color-warning); box-shadow: 0 0 8px var(--color-warning); }
.progress-red { background: var(--color-danger); box-shadow: 0 0 8px var(--color-danger); }

.rack-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.rack-capacity {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
}

.btn-rack-action {
    padding: 4px 12px;
    background: var(--color-primary);
    color: #000;
    border: none;
    border-radius: 4px;
    font-size: var(--font-size-xs);
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.1s;
}

.btn-rack-action:hover {
    transform: scale(1.05);
    background: #fff;
}

.text-danger { color: var(--color-danger) !important; }

/* Component Shop Styles */
.component-shop {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.component-actions {
    margin-bottom: var(--space-sm);
}

.btn-assemble {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, var(--color-primary), #388bfd);
    color: #000;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(var(--color-primary-rgb), 0.3);
    transition: all 0.2s;
    position: relative;
    z-index: 5;
}

.btn-assemble:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

.component-group__title {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    letter-spacing: 2px;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    padding-bottom: 4px;
}

.component-item {
    cursor: pointer;
    background: rgba(0,0,0,0.2);
}

.component-item:hover {
    background: rgba(var(--color-primary-rgb), 0.1);
}

.component-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.7rem;
    color: var(--color-text-secondary);
    margin-top: 4px;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(var(--color-primary-rgb), 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(var(--color-primary-rgb), 0); }
    100% { box-shadow: 0 0 0 0 rgba(var(--color-primary-rgb), 0); }
}

.animate-pulse {
    animation: pulse 2s infinite;
}

.component-grid-layout {
    gap: var(--space-md) !important;
}

.component-item {
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px dashed rgba(255, 255, 255, 0.1) !important;
    cursor: pointer !important;
}

.stock-badge {
    font-size: 0.65rem;
    background: var(--color-success);
    color: #000;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 800;
}

.component-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: var(--space-sm);
}

.spec-tag {
    font-size: 0.6rem;
    background: rgba(255,255,255,0.05);
    padding: 1px 6px;
    border-radius: 4px;
    color: var(--color-text-muted);
    border: 1px solid rgba(255,255,255,0.05);
}

.btn-buy-part {
    padding: 4px 10px;
    background: rgba(var(--color-primary-rgb), 0.2);
    border: 1px solid var(--color-primary);
    color: var(--color-primary);
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-buy-part:hover:not(:disabled) {
    background: var(--color-primary);
    color: #000;
}

.btn-assemble {
    width: 100%;
    margin-bottom: var(--space-md);
    padding: var(--space-sm);
    background: var(--color-primary);
    color: #000;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-xs);
    font-size: var(--font-size-sm);
}

.btn-disassemble {
    margin-top: var(--space-md);
    width: 100%;
    border: 1px dashed var(--color-danger) !important;
    background: rgba(var(--color-danger-rgb), 0.1) !important;
    color: var(--color-danger) !important;
}

.btn-disassemble:hover:not(:disabled) {
    background: var(--color-danger) !important;
    color: #fff !important;
}

/* Generation Selector */
.gen-selector {
    margin-bottom: var(--space-md);
    background: var(--color-bg-light);
    border-radius: var(--radius-md);
    padding: var(--space-sm);
    border: 1px solid var(--color-border);
}

.gen-selector__label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--color-text-muted);
    font-weight: 700;
    margin-bottom: 6px;
}

.gen-selector__tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 8px;
}

.gen-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 6px 4px;
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    cursor: pointer;
    transition: 0.2s;
}

.gen-tab:hover {
    background: var(--color-bg-light);
    border-color: var(--color-text-muted);
}

.gen-tab--active {
    background: var(--color-primary-dim);
    border-color: var(--color-primary);
    box-shadow: 0 0 10px rgba(var(--color-primary-rgb), 0.1);
}

.gen-tab__num {
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--color-text-highlight);
}

.gen-tab__hint {
    font-size: 0.6rem;
    color: var(--color-text-muted);
}

.gen-tab--legacy .gen-tab__hint { color: var(--color-warning); }
.gen-tab--nextgen .gen-tab__hint { color: #a855f7; }

.gen-details {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    padding-top: 6px;
    border-top: 1px dashed var(--color-border);
    color: var(--color-text-secondary);
}

/* Badges */
.badge-gen {
    font-size: 0.6rem;
    padding: 1px 4px;
    border-radius: 3px;
    margin-left: 6px;
    background: var(--color-bg-light);
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    vertical-align: middle;
}

.badge-gen--current { background: rgba(31, 111, 235, 0.1); color: #58a6ff; border-color: rgba(31, 111, 235, 0.3); }
.badge-gen--nextgen { background: rgba(168, 85, 247, 0.1); color: #a855f7; border-color: rgba(168, 85, 247, 0.3); }
.badge-gen--legacy { background: rgba(234, 179, 8, 0.1); color: #eab308; border-color: rgba(234, 179, 8, 0.3); }

.badge-gen-detail {
    font-size: 0.7rem;
    background: var(--color-bg-light);
    border: 1px solid var(--color-border);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 8px;
    color: var(--color-text-secondary);
}

.btn-sell {
    width: 100%;
    justify-content: center;
    margin-top: var(--space-md);
}
</style>
