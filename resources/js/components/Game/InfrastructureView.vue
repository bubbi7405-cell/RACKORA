<template>
    <div class="v2-main-viewport infrastructure-v2" :class="`theme-${currentRoom?.theme || 'classic'}`">
        <header class="v2-content-header">
            <div class="v2-breadcrumb">
                <span class="v2-path">INFRASTRUCTURE</span>
                <span class="v2-sep">//</span>
                <span class="v2-node">{{ currentRoom?.name?.toUpperCase() }}</span>
            </div>
            
            <div class="v2-room-tabs">
                <button 
                    v-for="room in Object.values(rooms || {})" 
                    :key="room.id"
                    class="v2-room-tab"
                    :class="{ 'is-active': selectedRoomId === room.id }"
                    @click="gameStore.selectRoom(room.id)"
                >
                    {{ room.name }}
                </button>
            </div>

            <button class="v2-action-btn secondary" @click="showHeatmap = !showHeatmap" :class="{ 'active': showHeatmap }" style="margin-right: 12px;">
                <span class="v2-icon">🌡️</span>
                {{ showHeatmap ? 'HEATMAP_ON' : 'HEATMAP_OFF' }}
            </button>
            <button class="v2-action-btn secondary" @click="$emit('openMarket')" style="margin-right: 12px;">
                <span class="v2-icon">⚡</span>
                ENERGY_GRID
            </button>
            <button class="v2-action-btn" @click="$emit('openShop')">
                <span class="v2-icon">⊞</span>
                PROVISION_HARDWARE
            </button>
        </header>

        <div class="v2-content-scroll">
            <div class="v2-telemetry-grid">
                <!-- Power Card -->
                <div class="v2-card telemetry-v2" :class="{ 'is-danger': powerUsageRatio >= 1 }">
                    <div class="v2-title">
                        <span class="v2-label">POWER_ARRAY</span>
                        <div class="v2-badge" :class="{ 'is-danger': powerUsageRatio > 0.95 }">
                            {{ powerUsageRatio > 0.95 ? 'CRITICAL' : 'STABLE' }}
                        </div>
                    </div>
                    <div class="v2-stat-row">
                        <div class="v2-stat-value large">{{ currentRoom?.stats?.powerUsage || 0 }}<small>kW</small></div>
                        <div class="v2-gauge-wrapper">
                            <div class="v2-gauge">
                                <div class="v2-gauge-fill" :style="{ width: Math.min(100, powerUsageRatio * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                    <div class="v2-card-footer">
                        <span>CAPACITY: {{ currentRoom?.stats?.powerCapacity || 100 }} kW</span>
                    </div>
                </div>

                <!-- Cooling Card -->
                <div class="v2-card telemetry-v2" :class="{ 'is-danger': currentRoom?.temperature > 50 }">
                    <div class="v2-title">
                        <span class="v2-label">THERMAL_CONTEXT</span>
                        <div class="v2-badge" :class="{ 'is-danger': currentRoom?.temperature > 40 }">
                            {{ currentRoom?.temperature > 40 ? 'HIGH_TEMP' : 'OPTIMAL' }}
                        </div>
                    </div>
                    <div class="v2-stat-row">
                        <div class="v2-stat-value large">{{ Math.round(currentRoom?.temperature || 20) }}<small>°C</small></div>
                        <div class="v2-gauge-wrapper">
                            <div class="v2-gauge">
                                <div class="v2-gauge-fill is-thermal" :style="{ width: (currentRoom?.temperature / 60 * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                    <div class="v2-card-footer">
                        <span>AMBIENT: 22°C</span>
                    </div>
                </div>

                <!-- Network Card -->
                <div class="v2-card telemetry-v2">
                    <div class="v2-title">
                        <span class="v2-label">NETWORK_PIPE</span>
                        <div class="v2-badge">STABLE</div>
                    </div>
                    <div class="v2-stat-row">
                        <div class="v2-stat-value large">{{ currentRoom?.stats?.bandwidthUsage || 0 }}<small>Gbps</small></div>
                        <div class="v2-gauge-wrapper">
                            <div class="v2-gauge">
                                <div class="v2-gauge-fill is-network" :style="{ width: (bandwidthUsageRatio * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                    <div class="v2-card-footer">
                        <span>CAPACITY: {{ currentRoom?.stats?.bandwidthCapacity || 1 }} Gbps</span>
                    </div>
                </div>
            </div>

            <div class="v2-section">
                <div class="v2-title">PHYSICAL_ASSET_LAYOUT</div>
                
                <div class="v2-visual-grid">
                    <RackComponent 
                        v-for="rack in currentRoomRacks" 
                        :key="rack.id"
                        :rack="rack"
                        :isSelected="selectedRackId === rack.id"
                        :show-heatmap="showHeatmap"
                        @select="gameStore.selectRack(rack.id)"
                        @selectServer="(id) => $emit('open-server-details', id)"
                    />
                    
                    <div v-if="currentRoomRacks.length === 0" class="v2-empty-state">
                        NO_UNITS_PROVISIONED_ON_THIS_NODE
                    </div>
                </div>
            </div>

            <div v-if="selectedRack" class="v2-section animate-fade-in-right">
                <div class="v2-title">RACK_OPERATIONS: {{ selectedRack.name }}</div>
                <div class="v2-rack-ops">
                    <!-- Lighting Control -->
                    <div class="v2-op-group">
                        <label>RGB_ENVIRONMENT</label>
                        <div class="v2-op-row">
                            <input type="color" v-model="ledColor" @change="updateLighting" class="v2-color-input">
                            <select v-model="ledMode" @change="updateLighting" class="v2-select-sm">
                                <option value="static">STATIC</option>
                                <option value="pulse">PULSE</option>
                                <option value="rainbow">RAINBOW</option>
                            </select>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="v2-op-group">
                        <label>MAINTENANCE</label>
                        <button 
                            class="v2-btn-sm" 
                            :disabled="selectedRack.dustLevel < 0.1 || processing"
                            @click="handleCleanRack"
                        >
                            CLEAN_DUST ({{ Math.round(selectedRack.dustLevel * 100) }}%)
                        </button>
                    </div>
                </div>
            </div>

            <div class="v2-section">
                <div class="v2-title">ACTIVE_ASSET_INVENTORY</div>
                
                <div class="v2-table">
                    <div class="v2-table-header">
                        <span class="v2-th">UNIT_ID</span>
                        <span class="v2-th">THERMAL_STATE</span>
                        <span class="v2-th">U-UTILIZATION</span>
                    </div>
                    <div v-for="rack in currentRoomRacks" :key="rack.id" class="v2-table-row">
                        <span class="v2-td text-mono">RACK-{{ rack.id.toString().slice(-4).toUpperCase() }}</span>
                        <span class="v2-td">
                            <span class="v2-status-dot" :class="{ 'is-online': !rack.isOverheating }"></span>
                            {{ rack.temperature }}°C
                        </span>
                        <span class="v2-td">{{ rack.units.used }}/{{ rack.units.total }}U</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thermal Legend Overlay -->
        <div v-if="showHeatmap" class="thermal-legend animate-fade-in-up">
            <div class="legend-title">THERMAL_SCALE_KEY</div>
            <div class="legend-items">
                <div class="legend-item"><span class="scale-dot ambient"></span> <25°C AMBIENT</div>
                <div class="legend-item"><span class="scale-dot warm"></span> 35°C WARM</div>
                <div class="legend-item"><span class="scale-dot hot"></span> 45°C HOT</div>
                <div class="legend-item"><span class="scale-dot critical"></span> >50°C CRITICAL</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useInfrastructureStore } from '../../stores/infrastructure';
import RackComponent from '../Rack/RackComponent.vue';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();

defineEmits(['openShop', 'open-server-details', 'openMarket']);
const showHeatmap = ref(false);
const processing = ref(false);

const ledColor = ref('#00ff00');
const ledMode = ref('static');

const rooms = computed(() => gameStore.rooms);
const selectedRoomId = computed(() => gameStore.selectedRoomId);
const selectedRackId = computed(() => gameStore.selectedRackId);

const selectedRack = computed(() => {
    if (!selectedRackId.value) return null;
    return currentRoomRacks.value.find(r => r.id === selectedRackId.value);
});

// Sync LED values when rack changes
watch(selectedRackId, (newId) => {
    if (selectedRack.value) {
        ledColor.value = selectedRack.value.ledColor || '#00ff00';
        ledMode.value = selectedRack.value.ledMode || 'static';
    }
});

const updateLighting = async () => {
    if (!selectedRack.value) return;
    await infraStore.updateRackLighting(selectedRack.value.id, ledColor.value, ledMode.value);
};

const handleCleanRack = async () => {
    if (!selectedRack.value || processing.value) return;
    processing.value = true;
    try {
        await infraStore.cleanRack(selectedRack.value.id);
    } finally {
        processing.value = false;
    }
};

const currentRoom = computed(() => rooms.value[selectedRoomId.value]);
const currentRoomRacks = computed(() => {
    return currentRoom.value?.racks || [];
});

const powerUsageRatio = computed(() => {
    if (!currentRoom.value?.stats) return 0;
    return (currentRoom.value.stats.powerUsage || 0) / (currentRoom.value.stats.powerCapacity || 1);
});

const bandwidthUsageRatio = computed(() => {
    if (!currentRoom.value?.stats) return 0;
    return (currentRoom.value.stats.bandwidthUsage || 0) / (currentRoom.value.stats.bandwidthCapacity || 1);
});
</script>

<style scoped>
.infrastructure-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--color-surface);
}

.view-header {
    padding: var(--space-xl) var(--space-2xl);
    border-bottom: var(--border-ui);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.breadcrumb {
    display: flex;
    gap: 12px;
    font-size: 0.65rem;
    font-weight: 800;
    font-family: var(--font-mono);
    margin-bottom: 12px;
}

.breadcrumb .root { color: var(--color-muted); }
.breadcrumb .sep { color: var(--color-muted); opacity: 0.3; }
.breadcrumb .active { color: var(--color-accent); }

.room-selector {
    display: flex;
    gap: 20px;
}

.room-tab {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--color-muted);
    padding: var(--space-xs) 0;
    position: relative;
    transition: color 0.2s;
}

.room-tab:hover { color: #fff; }
.room-tab.active { color: #fff; }
.room-tab.active::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--color-accent);
}

.industrial-btn {
    background: transparent;
    border: var(--border-highlight);
    color: #fff;
    padding: 8px 16px;
    font-size: 0.7rem;
    font-weight: 800;
    font-family: var(--font-mono);
    letter-spacing: 0.1em;
    transition: all 0.2s;
    border-radius: 2px;
}

.industrial-btn:hover {
    background: #fff;
    color: #000;
    box-shadow: 0 0 20px rgba(255,255,255,0.1);
}

.view-content {
    padding: var(--space-2xl);
    flex: 1;
}

.telemetry-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-xl);
    margin-bottom: var(--space-2xl);
}

.tel-card {
    background: var(--color-elevated);
    border: var(--border-dim);
    padding: var(--space-lg);
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.tel-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    font-family: var(--font-mono);
}

.tel-label { color: var(--color-muted); }
.tel-status { color: var(--color-success); }

.tel-body { display: flex; align-items: baseline; gap: var(--space-lg); }
.tel-value { font-size: 2rem; font-weight: 800; color: #fff; }
.tel-value small { font-size: 0.8rem; color: var(--color-muted); margin-left: 4px; }

.tel-gauge { flex: 1; height: 6px; background: rgba(255,255,255,0.05); position: relative; border-radius: 3px; overflow: hidden; }
.gauge-bar { height: 100%; background: var(--color-accent); width: 0%; transition: width 0.5s ease; }

.tel-footer {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    font-family: var(--font-mono);
    color: var(--color-muted);
}

.warning .tel-status { color: var(--color-warning); }
.danger .tel-status { color: var(--color-danger); }
.danger .gauge-bar { background: var(--color-danger); }

.asset-section { margin-top: var(--space-2xl); }

.section-header {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
}

.section-title { font-size: 0.7rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.15em; white-space: nowrap; }
.section-line { flex: 1; height: 1px; background: var(--border-dim); }

.asset-table { display: flex; flex-direction: column; }

.table-header {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr 1.5fr;
    padding: var(--space-md) var(--space-lg);
    background: rgba(255,255,255,0.02);
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--color-muted);
    font-family: var(--font-mono);
    border-bottom: var(--border-ui);
}

.table-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr 1.5fr;
    padding: var(--space-lg);
    border-bottom: var(--border-dim);
    font-size: 0.8rem;
    font-family: var(--font-mono);
    transition: background 0.15s;
}

.table-row:hover { background: rgba(255,255,255,0.01); }

.visual-racks {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    padding: 12px 0;
}

.empty-assets {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 60px 0;
    border: 1px dashed var(--border-dim);
    color: var(--color-muted);
}

.empty-icon { font-size: 2rem; margin-bottom: 12px; opacity: 0.3; }
.empty-text { font-size: 0.6rem; font-weight: 800; letter-spacing: 0.2em; }

.status-pip { width: 6px; height: 6px; border-radius: 50%; display: inline-block; background: var(--color-danger); margin-right: 8px; }
.status-pip.online { background: var(--color-success); box-shadow: 0 0 6px var(--color-success); }

/* THERMAL LEGEND */
.thermal-legend {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid var(--v3-border-soft);
    padding: 16px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 1000;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    pointer-events: none;
}

.legend-title {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
    font-family: var(--font-family-mono);
}

.legend-items {
    display: flex;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--v3-text-secondary);
}

.scale-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

.scale-dot.ambient { background: rgba(0, 150, 255, 0.6); box-shadow: 0 0 8px rgba(0, 150, 255, 0.4); }
.scale-dot.warm { background: rgba(255, 200, 0, 0.6); box-shadow: 0 0 8px rgba(255, 200, 0, 0.4); }
.scale-dot.hot { background: rgba(255, 90, 50, 0.6); box-shadow: 0 0 8px rgba(255, 90, 50, 0.4); }
.scale-dot.critical { background: rgba(255, 0, 0, 0.8); box-shadow: 0 0 10px rgba(255, 0, 0, 0.6); animation: v3-crit-blink 1s infinite; }

@keyframes v3-crit-blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.animate-fade-in-up {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.v2-rack-ops {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
    padding: var(--space-md);
    background: rgba(255, 255, 255, 0.02);
    border: var(--border-ui);
    border-radius: var(--radius-md);
}

.v2-op-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.v2-op-group label {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--color-text-muted);
}

.v2-op-row {
    display: flex;
    align-items: center;
    gap: var(--space-md);
}

.v2-color-input {
    width: 40px;
    height: 30px;
    padding: 0;
    border: var(--border-ui);
    background: transparent;
    cursor: pointer;
}

.v2-select-sm {
    flex: 1;
    background: rgba(0, 0, 0, 0.3);
    color: #fff;
    border: var(--border-ui);
    padding: 4px 8px;
    font-size: 0.75rem;
}

.v2-btn-sm {
    background: var(--color-primary-dim);
    color: var(--color-primary);
    border: 1px solid var(--color-primary);
    padding: 8px 16px;
    font-size: 0.75rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
}

.v2-btn-sm:hover:not(:disabled) {
    background: var(--color-primary);
    color: #000;
}

.v2-btn-sm:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@keyframes fade-in-right {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.animate-fade-in-right {
    animation: fade-in-right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>

