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

                <!-- Server Models -->
                <div class="server-models">
                    <div 
                        v-for="(model, key) in filteredModels" 
                        :key="key"
                        class="server-model"
                        :class="{ 'server-model--selected': selectedModel === key }"
                        @click="selectModel(key)"
                        draggable="true"
                        @dragstart="onDragStart($event, key, model)"
                    >
                        <div class="server-model__header">
                            <span class="server-model__name">{{ model.modelName }}</span>
                            <span class="server-model__size">{{ model.sizeU }}U</span>
                        </div>
                        
                        <div class="server-model__specs">
                            <div class="spec">
                                <span class="spec__label">CPU</span>
                                <span class="spec__value">{{ model.cpuCores }} cores</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">RAM</span>
                                <span class="spec__value">{{ model.ramGb }} GB</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">Storage</span>
                                <span class="spec__value">{{ model.storageTb }} TB</span>
                            </div>
                            <div class="spec">
                                <span class="spec__label">Power</span>
                                <span class="spec__value">{{ model.powerDrawKw }} kW</span>
                            </div>
                        </div>

                        <div class="server-model__footer">
                            <span class="server-model__price">${{ model.purchaseCost.toLocaleString() }}</span>
                            <button 
                                class="btn-purchase" 
                                @click.stop="purchaseServer(key)"
                                :disabled="!canAfford(model.purchaseCost) || !selectedRack"
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
                            <div class="server-detail__name">{{ selectedServerData.modelName }}</div>
                            <div class="server-detail__status-badge" :class="`status--${selectedServerData.status}`">
                                {{ selectedServerData.status }}
                            </div>
                        </div>

                        <div class="server-detail__stats">
                            <div class="compact-spec"><span>CPU</span> <strong>{{ selectedServerData.specs.cpuCores }} Cores</strong></div>
                            <div class="compact-spec"><span>RAM</span> <strong>{{ selectedServerData.specs.ramGb }} GB</strong></div>
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
const { player, selectedRack, selectedRackId, selectedServerId, rooms } = storeToRefs(gameStore);

const selectedCategory = ref('vserver_node');
const selectedModel = ref(null);
const serverCatalog = ref({});
const isRepairing = ref(false);

const serverCategories = [
    { type: 'vserver_node', name: 'VServer', icon: '🖥️', level: 1 },
    { type: 'dedicated', name: 'Dedicated', icon: '🔧', level: 2 },
    { type: 'storage_server', name: 'Storage', icon: '💾', level: 5 },
    { type: 'gpu_server', name: 'GPU', icon: '🎮', level: 15 },
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

const repairCost = computed(() => {
    if (!selectedServerData.value) return 0;
    return Math.round((selectedServerData.value.purchaseCost || 500) * 0.2);
});

function needsRepair(server) {
    return ['damaged', 'degraded'].includes(server.status);
}

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
        freeSlots[0]
    );

    if (result.success) {
        selectedModel.value = null;
    }
}

function findFreeSlots(rack, sizeNeeded) {
    // Find contiguous free slots
    const slots = rack.slots;
    const freeSlots = [];

    for (let i = 1; i <= rack.units.total - sizeNeeded + 1; i++) {
        let canFit = true;
        for (let j = 0; j < sizeNeeded; j++) {
            if (!slots[i + j]?.empty) {
                canFit = false;
                break;
            }
        }
        if (canFit) {
            freeSlots.push(i);
        }
    }

    return freeSlots;
}

function onDragStart(event, modelKey, model) {
    event.dataTransfer.setData('application/json', JSON.stringify({
        type: 'new_server',
        category: selectedCategory.value,
        modelKey,
        sizeU: model.sizeU,
    }));
    event.dataTransfer.effectAllowed = 'copy';
}

onMounted(() => {
    loadCatalog();
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
}

.server-model:hover {
    border-color: var(--color-primary);
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
    margin-bottom: var(--space-sm);
}

.power-controls {
    display: flex;
    gap: var(--space-xs);
}

.server-workloads {
    margin-top: var(--space-md);
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
</style>
