<template>
    <div class="locations-view animate-view">
        <header class="view-header">
            <div class="header-main">
                <nav class="breadcrumb">
                    <span class="root">SYSTEMS</span>
                    <span class="sep">/</span>
                    <span class="active">GLOBAL_ASSETS</span>
                </nav>
                <h2 class="view-title">INFRASTRUCTURE_NODES</h2>
            </div>
            <div class="header-stats">
                <div class="h-stat">
                    <span class="hs-label">ACTIVE_NODES</span>
                    <span class="hs-val">{{ Object.keys(rooms).length }}</span>
                </div>
                <div class="h-stat">
                    <span class="hs-label">TOTAL_CAPACITY</span>
                    <span class="hs-val">{{ totalCapacity }}U</span>
                </div>
            </div>
        </header>

        <div class="locations-content">
            <!-- Active Nodes Section -->
            <section class="nodes-section">
                <div class="section-header">
                    <span class="section-label">OPERATIONAL_DEPLOYS</span>
                    <div class="section-line"></div>
                </div>

                <div class="nodes-grid">
                    <div 
                        v-for="room in Object.values(rooms)" 
                        :key="room.id"
                        class="node-card"
                        :class="{ 'active-node': selectedRoomId === room.id }"
                        @click="switchToRoom(room.id)"
                    >
                        <div class="node-header">
                            <div class="node-meta">
                                <span class="node-type">{{ room.type.toUpperCase() }}</span>
                                <h3 class="node-name">{{ room.name }}</h3>
                            </div>
                            <div class="node-region" v-if="getRegion(room.region)">
                                <span class="region-flag">{{ getRegion(room.region).flag }}</span>
                                <span class="region-name">{{ getRegion(room.region).name.toUpperCase() }}</span>
                            </div>
                        </div>

                        <div class="node-metrics">
                            <div class="metric-mini">
                                <span class="mm-label">RACK_SPACE</span>
                                <div class="mm-bar-bg">
                                    <div class="mm-bar" :style="{ width: (room.usedRacks / room.maxRacks * 100) + '%' }"></div>
                                </div>
                                <span class="mm-val">{{ room.usedRacks }} / {{ room.maxRacks }}</span>
                            </div>
                            
                            <div class="metric-mini">
                                <span class="mm-label">POWER_UTIL</span>
                                <div class="mm-bar-bg">
                                    <div class="mm-bar" :style="{ width: Math.min(100, (room.stats?.powerUsage / room.stats?.powerCapacity * 100)) + '%' }"></div>
                                </div>
                                <span class="mm-val">{{ Math.round(room.stats?.powerUsage || 0) }}kW</span>
                            </div>
                        </div>

                        <div class="node-footer">
                            <div class="status-indicator">
                                <span class="status-pip online"></span>
                                <span class="status-text">NOMINAL</span>
                            </div>
                            <button class="node-action">ACCESS_NODE</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Expansion Section -->
            <section class="nodes-section expansion">
                <div class="section-header">
                    <span class="section-label">EXPANSION_OPPORTUNITIES</span>
                    <div class="section-line"></div>
                </div>

                <div class="nodes-grid">
                    <div 
                        v-for="(roomType, key) in lockedRooms" 
                        :key="key"
                        class="node-card locked"
                        :class="{ 'can-buy': canAfford(roomType.cost) && player.economy.level >= roomType.level }"
                    >
                        <div class="node-header">
                            <div class="node-meta">
                                <span class="node-type">DEPLOYMENT PLAN</span>
                                <h3 class="node-name">{{ roomType.name }}</h3>
                                <p style="font-size:0.6rem; color:var(--color-muted); margin-top:8px;">{{ roomType.desc }}</p>
                            </div>
                        </div>

                        <div class="node-requirements">
                            <div class="req-item" :class="{ 'met': player.economy.level >= roomType.level }">
                                <span class="req-label">REQUIRED_LEVEL</span>
                                <span class="req-val">LVL_{{ roomType.level }}</span>
                            </div>
                            <div class="req-item" :class="{ 'met': canAfford(roomType.cost) }">
                                <span class="req-label">ACQUISITION_COST</span>
                                <span class="req-val">${{ (roomType.cost / 1000).toFixed(0) }}K</span>
                            </div>
                        </div>

                        <button 
                            class="purchase-btn"
                            :disabled="player.economy.level < roomType.level || !canAfford(roomType.cost)"
                            @click="purchaseRoom(key)"
                        >
                            ACQUIRE_ASSET
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';

const gameStore = useGameStore();

const rooms = computed(() => gameStore.rooms || {});
const regions = computed(() => gameStore.regions || {});
const selectedRoomId = computed(() => gameStore.selectedRoomId);
const player = computed(() => gameStore.player || {});

const emit = defineEmits(['room-switched', 'open-region-select']);

const totalCapacity = computed(() => {
    return Object.values(rooms.value).reduce((acc, room) => acc + ((room.maxRacks || room.max_racks) * 42), 0); // Approx 42U per rack
});

const locationDefs = computed(() => gameStore.locationDefinitions || {});

const lockedRooms = computed(() => {
    // Phase 3: Multi-Region Expansion allows multiple nodes of the same type!
    // We keep all available node-types visible so players can deploy globally.
    const nodes = {};
    for (const [key, def] of Object.entries(locationDefs.value)) {
        if (key === 'basement') continue; // Basement is starting area
        
        nodes[key] = {
            name: def.label || def.name || 'Unknown Facility',
            level: def.required_level || 1,
            cost: def.unlock_cost || 0,
            desc: `Capacity for ${def.max_racks} racks. Requires level ${def.required_level}.`
        };
    }
    
    // Fallbacks if empty
    if (Object.keys(nodes).length === 0) {
        return {
            garage: { name: 'Garage Facility', level: 5, cost: 25000, desc: 'A basic workshop for initial expansion.' },
            small_hall: { name: 'Small Server Hall', level: 15, cost: 150000, desc: 'Professional commercial space for a regional footprint.' },
            data_center: { name: 'Major Data Center', level: 30, cost: 1000000, desc: 'Enterprise-grade facility for international hyperscaling.' },
        };
    }
    return nodes;
});

const getRegion = (key) => regions.value[key] || { name: 'Unknown', flag: '🌐' };

const canAfford = (cost) => player.value.economy.balance >= cost;

const switchToRoom = (id) => {
    gameStore.selectRoom(id);
    emit('room-switched');
};

const purchaseRoom = (type) => {
    emit('open-region-select', type);
};
</script>

<style scoped>
.locations-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--color-surface);
}

.view-header {
    padding: var(--space-2xl) var(--space-3xl);
    border-bottom: var(--border-ui);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.view-title { font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; }

.header-stats { display: flex; gap: 40px; }
.h-stat { display: flex; flex-direction: column; align-items: flex-end; }
.hs-label { font-size: 0.55rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.1em; }
.hs-val { font-size: 1.2rem; font-family: var(--font-mono); color: var(--color-accent); font-weight: 800; }

.locations-content {
    padding: var(--space-3xl);
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 60px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: var(--space-xl);
    margin-bottom: var(--space-2xl);
}

.section-label { font-size: 0.7rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.2em; white-space: nowrap; }
.section-line { flex: 1; height: 1px; background: var(--border-dim); opacity: 0.3; }

.nodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-xl);
}

.node-card {
    background: var(--color-elevated);
    border: var(--border-dim);
    padding: var(--space-2xl);
    display: flex;
    flex-direction: column;
    gap: 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.node-card:hover { border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.02); }
.active-node { border-color: var(--color-accent); background: rgba(58, 134, 255, 0.05); }

.node-header { display: flex; justify-content: space-between; align-items: flex-start; }
.node-type { font-size: 0.55rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.1em; }
.node-name { font-size: 1rem; font-weight: 800; color: #fff; margin-top: 4px; }

.node-region { display: flex; align-items: center; gap: 8px; font-size: 0.6rem; color: var(--color-muted); font-weight: 800; }

.node-metrics { display: flex; flex-direction: column; gap: 12px; }
.metric-mini { display: flex; flex-direction: column; gap: 6px; }
.mm-label { font-size: 0.5rem; font-weight: 800; color: var(--color-muted); opacity: 0.7; }
.mm-bar-bg { height: 2px; background: rgba(255,255,255,0.05); }
.mm-bar { height: 100%; background: var(--color-accent); }
.mm-val { font-size: 0.7rem; font-family: var(--font-mono); color: #fff; text-align: right; }

.node-footer {
    padding-top: 20px;
    border-top: var(--border-dim);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-indicator { display: flex; align-items: center; gap: 8px; font-size: 0.6rem; font-weight: 800; }
.status-pip { width: 6px; height: 6px; border-radius: 50%; }
.status-pip.online { background: var(--color-success); box-shadow: 0 0 8px var(--color-success); }
.status-text { color: var(--color-success); opacity: 0.8; }

.node-action {
    background: transparent;
    border: var(--border-dim);
    color: var(--color-muted);
    font-size: 0.6rem;
    font-weight: 800;
    padding: 6px 12px;
    border-radius: 2px;
}

.node-card:hover .node-action { color: #fff; border-color: #fff; }

/* Locked Node Styles */
.node-card.locked { opacity: 0.5; filter: grayscale(1); cursor: default; }
.node-card.locked:hover { filter: grayscale(0.5); opacity: 0.7; }
.node-card.can-buy { filter: grayscale(0.2); opacity: 0.9; cursor: pointer; }

.node-requirements { display: flex; flex-direction: column; gap: 12px; }
.req-item { display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--color-muted); }
.req-item.met { color: #fff; }
.req-val { font-family: var(--font-mono); font-weight: 800; }
.req-item.met .req-val { color: var(--color-success); }

.purchase-btn {
    width: 100%;
    padding: 12px;
    background: var(--color-bg-deep);
    border: var(--border-dim);
    color: var(--color-muted);
    font-size: 0.7rem;
    font-weight: 800;
    margin-top: auto;
}

.can-buy .purchase-btn { background: var(--color-success); color: #000; border: none; cursor: pointer; }
.can-buy .purchase-btn:hover { filter: brightness(1.2); }
</style>
