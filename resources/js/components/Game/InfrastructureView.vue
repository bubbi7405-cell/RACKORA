<template>
    <div class="v2-main-viewport infrastructure-v2" :class="`theme-${currentRoom?.theme || 'classic'}`">
        <header class="v2-content-header">
            <div class="v2-breadcrumb l3-priority">
                <span class="v2-path">NETWORK_TOPOLOGY</span>
                <span class="v2-sep">≫</span>
                <span class="v2-asset-site">{{ currentRoom?.name?.toUpperCase() }}</span>
            </div>

            <div class="v2-room-tabs">
                <button v-for="room in Object.values(rooms || {})" :key="room.id" class="v2-room-tab"
                    :class="{ 'is-active': selectedRoomId === room.id, 'l1-priority': selectedRoomId === room.id, 'l3-priority': selectedRoomId !== room.id }"
                    @click="gameStore.selectRoom(room.id)">
                    {{ room.name }}
                </button>
            </div>

            <div class="v2-command-actions">
                <button class="v2-cmd-btn secondary l2-priority" @click="showHeatmap = !showHeatmap"
                    :class="{ 'active': showHeatmap }">
                    <span class="v2-icon">⧗</span>
                    {{ showHeatmap ? 'THERMAL_OVERLAY: ON' : 'THERMAL_OVERLAY: OFF' }}
                </button>
                <button class="v2-cmd-btn secondary l2-priority" @click="$emit('openMarket')">
                    <span class="v2-icon">⚡</span>
                    POWER_GRID
                </button>
                <button class="v2-cmd-btn secondary l2-priority" @click="showSpecialization = true">
                    <span class="v2-icon">✵</span>
                    SPECIALIZE_NODE
                </button>
                <button class="v2-cmd-btn l1-priority" @click="$emit('openShop')">
                    <span class="v2-icon">⊞</span>
                    DEPLOY_SERVER
                </button>
                <button class="v2-cmd-btn l1-priority" @click="$emit('openRackPurchase')">
                    <span class="v2-icon">+</span>
                    PROVISION_RACK
                </button>
                <button class="v2-close-btn" @click="gameStore.selectView('overview')">&times;</button>
            </div>
        </header>

        <div class="v2-content-scroll">
            <div class="v2-telemetry-cluster">
                <!-- Power Card -->
                <div class="v2-tel-card" :class="{ 'is-critical': powerUsageRatio >= 0.9 }">
                    <div class="tel-scanline"></div>
                    <div class="tel-header">
                        <span class="tel-label l3-priority"
                              @mouseenter="tooltipStore.show($event, 'server')"
                              @mouseleave="tooltipStore.hide()">NODE_LOAD</span>
                        <div class="tel-badge l1-priority">{{ powerUsageRatio > 0.9 ? 'CRITICAL' : 'STABLE' }}</div>
                        <div v-if="currentRoom?.specialization" class="spec-active-pill">{{ currentRoom.specialization.toUpperCase() }}</div>
                    </div>
                    <div class="tel-main">
                        <div class="tel-value l1-priority">{{ currentRoom?.stats?.powerUsage || 0 }}<small>kW</small>
                        </div>
                        <div class="tel-gauge-cluster">
                            <div class="tel-gauge-bg">
                                <div class="tel-gauge-fill pwr"
                                    :style="{ width: Math.min(100, powerUsageRatio * 100) + '%' }"></div>
                            </div>
                            <div class="tel-meta l3-priority">LIMIT: {{ currentRoom?.stats?.powerCapacity || 100 }} KW
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thermal Card -->
                <div class="v2-tel-card" :class="{ 'is-critical': currentRoom?.temperature > 45 }">
                    <div class="tel-scanline"></div>
                    <div class="tel-header">
                        <span class="tel-label l3-priority"
                              @mouseenter="tooltipStore.show($event, 'cooling')"
                              @mouseleave="tooltipStore.hide()">THERMAL_STATE</span>
                        <div class="tel-badge l1-priority">{{ currentRoom?.temperature > 40 ? 'HEAT_WARN' : 'NOMINAL' }}
                        </div>
                    </div>
                    <div class="tel-main">
                        <div class="tel-value l1-priority">{{ Math.round(currentRoom?.temperature || 20)
                            }}<small>°C</small></div>
                        <div class="tel-gauge-cluster">
                            <div class="tel-gauge-bg">
                                <div class="tel-gauge-fill tmp"
                                    :style="{ width: (currentRoom?.temperature / 60 * 100) + '%' }"></div>
                            </div>
                            <div class="tel-meta l3-priority">PEAK: 55°C</div>
                        </div>
                    </div>
                </div>

                <!-- Network Card -->
                <div class="v2-tel-card">
                    <div class="tel-scanline"></div>
                    <div class="tel-header">
                        <span class="tel-label l3-priority"
                              @mouseenter="tooltipStore.show($event, 'network')"
                              @mouseleave="tooltipStore.hide()">NETWORK_THROUGHPUT</span>
                        <div class="tel-badge l1-priority">STABLE</div>
                    </div>
                    <div class="tel-main">
                        <div class="tel-value l1-priority">{{ currentRoom?.stats?.bandwidthUsage || 0
                            }}<small>Gbps</small></div>
                        <div class="tel-gauge-cluster">
                            <div class="tel-gauge-bg">
                                <div class="tel-gauge-fill sig" :style="{ width: (bandwidthUsageRatio * 100) + '%' }">
                                </div>
                            </div>
                            <div class="tel-meta l3-priority">CAP: {{ currentRoom?.stats?.bandwidthCapacity || 1 }} GBPS
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="v2-section grid-section">
                <div class="v2-section-header l2-priority">
                    SITE_INVENTORY // [PHYSICAL_ASSETS]
                </div>

                <div class="v2-visual-grid">
                    <RackComponent v-for="rack in currentRoomRacks" :key="rack.id" :rack="rack"
                        :isSelected="selectedRackId === rack.id" :show-heatmap="showHeatmap"
                        @select="gameStore.selectRack(rack.id)" @selectServer="(id) => $emit('open-server-details', id)"
                        @openInventory="(id) => $emit('openInventory', id)" />

                    <div v-if="currentRoomRacks.length === 0" class="v2-empty-state">
                        <p>NO_UNITS_PROVISIONED_ON_THIS_COORD</p>
                        <button class="v2-cmd-btn l1-priority" style="margin-top: 20px" @click="$emit('openRackPurchase')">
                            <span class="v2-icon">+</span> PROVISION_RACK
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="selectedRack" class="v2-section animate-fade-in-right">
                <div class="v2-title">
                    RACK_MANAGEMENT: {{ selectedRack.name }}
                    <span class="v2-info-trigger"
                        @mouseenter="tooltipStore.show($event, { title: 'RACK_MANAGEMENT', content: 'Operational control for the selected hardware rack. Maintain system integrity and clear thermal barriers.', hint: 'Visual indicators reflect current asset status.' })"
                        @mouseleave="tooltipStore.hide()">ⓘ</span>
                </div>
                <div class="v2-rack-ops">
                    <!-- Lighting Control -->
                    <div class="v2-op-group">
                        <label>
                            RACK_LIGHTING
                            <span class="v2-info-trigger"
                                @mouseenter="tooltipStore.show($event, { title: 'LIGHTING_SETTINGS', content: 'Adjust the visual status indicators of the hardware rack.', hint: 'Atmospheric variance for operational clarity.' })"
                                @mouseleave="tooltipStore.hide()">ⓘ</span>
                        </label>
                        <div class="v2-op-row">
                            <input type="color" v-model="ledColor" @change="updateLighting" class="v2-color-input"
                                @mouseenter="tooltipStore.show($event, { title: 'COLOR_SELECTION', content: 'Define the color signature of the rack indicators.', hint: 'Sync with active asset status.' })"
                                @mouseleave="tooltipStore.hide()">
                            <select v-model="ledMode" @change="updateLighting" class="v2-select-sm"
                                @mouseenter="tooltipStore.show($event, { title: 'LIGHTING_MODE', content: 'Choose a lighting pattern for the rack indicators.', hint: 'Visual feedback for system integrity.' })"
                                @mouseleave="tooltipStore.hide()">
                                <option value="static">STATIC</option>
                                <option value="pulse">PULSE</option>
                                <option value="rainbow">RAINBOW</option>
                            </select>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="v2-op-group">
                        <label>
                            SYSTEM_MAINTENANCE
                            <span class="v2-info-trigger"
                                @mouseenter="tooltipStore.show($event, { title: 'SYSTEM_MAINTENANCE', content: 'Direct protocols to ensure long-term hardware reliability.', hint: 'Particulate buildup increases thermal resistance.' })"
                                @mouseleave="tooltipStore.hide()">ⓘ</span>
                        </label>
                        <button class="v2-btn-sm" :disabled="selectedRack.dustLevel < 0.1 || processing"
                            @click="handleCleanRack"
                            @mouseenter="tooltipStore.show($event, { title: 'CLEAN_HARDWARE', content: 'Authorize a pressurized cleaning cycle on the hardware assets.', hint: 'Maintenance directly optimizes rack efficiency.' })"
                            @mouseleave="tooltipStore.hide()">
                            CLEAN_HARDWARE ({{ Math.round(selectedRack.dustLevel * 100) }}%)
                        </button>
                    </div>
                </div>
            </div>

            <div class="v2-section log-section">
                <div class="v2-section-header l2-priority">
                    ASSET_LOG // [STATUS: NOMINAL]
                </div>

                <div class="v2-table-wrapper">
                    <div class="v2-table-header l3-priority">
                        <span class="v2-th">ASSET_ID</span>
                        <span class="v2-th">TEMPERATURE</span>
                        <span class="v2-th">UTILIZATION</span>
                    </div>
                    <div v-for="rack in currentRoomRacks" :key="rack.id" class="v2-table-row">
                        <span class="v2-td text-mono l1-priority">ASSET-{{ rack.id.toString().slice(-4).toUpperCase()
                            }}</span>
                        <span class="v2-td l2-priority">
                            <span class="v2-status-dot" :class="{ 'is-online': !rack.isOverheating }"></span>
                            {{ rack.temperature }}°C
                        </span>
                        <span class="v2-td l3-priority">{{ rack.units.used }} / {{ rack.units.total }}U</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thermal Scale Overlay -->
        <div v-if="showHeatmap" class="thermal-legend animate-fade-in-up">
            <div class="legend-title">THERMAL_SCALE_KEY</div>
            <div class="legend-items">
                <div class="legend-item"><span class="scale-dot ambient"></span>
                    <25°C AMBIENT</div>
                        <div class="legend-item"><span class="scale-dot warm"></span> 35°C WARM</div>
                        <div class="legend-item"><span class="scale-dot hot"></span> 45°C HOT</div>
                        <div class="legend-item"><span class="scale-dot critical"></span> >50°C CRITICAL</div>
                </div>
            </div>
        </div>

        <SpecializationOverlay 
            v-if="showSpecialization" 
            :room="currentRoom"
            @close="showSpecialization = false"
        />
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useInfrastructureStore } from '../../stores/infrastructure';
import { useTooltipStore } from '../../stores/tooltip';
import RackComponent from '../Rack/RackComponent.vue';
import SpecializationOverlay from '../Overlay/SpecializationOverlay.vue';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();
const tooltipStore = useTooltipStore();

defineEmits(['openShop', 'openRackPurchase', 'open-server-details', 'openMarket', 'openLab', 'openInventory']);
const showHeatmap = ref(false);
const processing = ref(false);
const showSpecialization = ref(false);

const ledColor = ref('#00ff00');
const ledMode = ref('static');

const rooms = computed(() => gameStore.rooms);
const selectedRoomId = computed(() => gameStore.selectedRoomId);
const selectedRackId = computed(() => gameStore.selectedRackId);

const selectedRack = computed(() => {
    if (!selectedRackId.value) return null;
    return currentRoomRacks.value?.find(r => r.id === selectedRackId.value);
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
.infrastructure-v2 {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--ds-bg-void);
    color: #fff;
    overflow: hidden;
}

/* ── HEADER ────────────────────────────────── */
.v2-content-header {
    height: 80px;
    padding: 0 32px;
    background: rgba(13, 17, 23, 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.v2-breadcrumb {
    font-size: 0.6rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    display: flex;
    gap: 12px;
}

.v2-sep {
    opacity: 0.3;
    color: var(--ds-accent);
}

.v2-asset-site {
    color: #fff;
}

.v2-room-tabs {
    display: flex;
    gap: 24px;
}

.v2-room-tab {
    font-size: 0.75rem;
    font-weight: 950;
    letter-spacing: 0.1em;
    padding: 12px 0;
    position: relative;
    transition: all 0.2s;
}

.v2-room-tab.is-active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--ds-accent);
    box-shadow: 0 0 10px var(--ds-accent-glow);
}

.v2-command-actions {
    display: flex;
    gap: 16px;
}

.v2-cmd-btn {
    padding: 10px 20px;
    background: var(--ds-accent);
    color: #000;
    font-size: 0.65rem;
    font-weight: 950;
    letter-spacing: 0.1em;
    border-radius: 2px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
}

.v2-cmd-btn.secondary {
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.v2-cmd-btn.secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}

.v2-cmd-btn.secondary.active {
    border-color: var(--ds-nominal);
    color: var(--ds-nominal);
    background: rgba(35, 134, 54, 0.1);
}

/* ── TELEMETRY ─────────────────────────────── */
.v2-telemetry-cluster {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    padding: 32px;
}

.v2-tel-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, transparent 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.v2-tel-card:hover {
    border-color: rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.03);
}

.v2-tel-card.is-critical {
    border-color: var(--ds-critical);
    background: rgba(248, 81, 73, 0.05);
}

.tel-scanline {
    position: absolute;
    inset: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(255, 255, 255, 0.02) 50%);
    background-size: 100% 2px;
    pointer-events: none;
}

.tel-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
}

.tel-label {
    font-size: 0.55rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    color: var(--ds-text-ghost);
}

.tel-badge {
    font-size: 0.55rem;
    font-weight: 950;
    color: var(--ds-nominal);
}

.is-critical .tel-badge {
    color: var(--ds-critical);
    text-shadow: 0 0 10px var(--ds-critical);
}

.tel-main {
    display: flex;
    align-items: flex-end;
    gap: 32px;
}

.tel-value {
    font-size: 2.2rem;
    font-weight: 950;
    font-family: var(--ds-font-mono);
}

.tel-value small {
    font-size: 0.8rem;
    margin-left: 8px;
    opacity: 0.5;
}

.tel-gauge-cluster {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-bottom: 8px;
}

.tel-gauge-bg {
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2px;
    overflow: hidden;
}

.tel-gauge-fill {
    height: 100%;
    transition: width 1.5s var(--ds-ease-spring);
}

.tel-gauge-fill.pwr {
    background: var(--ds-nominal);
}

.tel-gauge-fill.tmp {
    background: var(--ds-warning);
}

.tel-gauge-fill.sig {
    background: var(--ds-accent);
}

.is-critical .tel-gauge-fill {
    background: var(--ds-critical) !important;
    box-shadow: 0 0 10px var(--ds-critical);
}

.tel-meta {
    font-size: 0.5rem;
    font-weight: 950;
    letter-spacing: 0.1em;
}

/* ── GRID SECTION ──────────────────────────── */
.v2-section {
    padding: 0 32px 48px;
}

.v2-section-header {
    font-size: 0.65rem;
    font-weight: 950;
    letter-spacing: 0.2em;
    margin-bottom: 24px;
    border-left: 3px solid var(--ds-accent);
    padding-left: 16px;
    opacity: 0.8;
}

.v2-visual-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 32px;
}

.v2-content-scroll {
    flex: 1;
    overflow-y: auto;
}

.v2-table-header {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr;
    padding: 16px 24px;
    background: rgba(255, 255, 255, 0.02);
    font-size: 0.55rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.v2-table-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr;
    padding: 16px 24px;
    font-size: 0.7rem;
    font-weight: 900;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    transition: all 0.2s;
}

.v2-table-row:hover {
    background: rgba(255, 255, 255, 0.01);
}

.v2-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-critical);
    display: inline-block;
    margin-right: 12px;
}

.v2-status-dot.is-online {
    background: var(--ds-nominal);
    box-shadow: 0 0 8px var(--ds-nominal);
}

.thermal-legend {
    position: fixed;
    bottom: 100px;
    left: 280px;
    background: rgba(5, 7, 10, 0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 16px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 1000;
}

.legend-title {
    font-size: 0.5rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    color: var(--ds-text-ghost);
}

.legend-items {
    display: flex;
    gap: 24px;
}

.legend-item {
    font-size: 0.6rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 10px;
}

.scale-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

.scale-dot.ambient {
    background: rgba(0, 150, 255, 0.6);
}

.scale-dot.warm {
    background: rgba(255, 200, 0, 0.6);
}

.scale-dot.hot {
    background: rgba(255, 90, 50, 0.6);
}

.scale-dot.critical {
    background: rgba(255, 0, 0, 0.8);
    animation: ds-blink 1s infinite;
}

@keyframes ds-blink {
    50% {
        opacity: 0.4;
    }
}

.v2-close-btn {
    background: none;
    border: none;
    color: var(--ds-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    padding: 8px;
    transition: all 0.2s;
    margin-left: 16px;
}

.v2-close-btn:hover {
    color: #fff;
    transform: rotate(90deg);
}
.spec-active-pill {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--color-accent);
    background: rgba(58, 134, 255, 0.1);
    border: 1px solid var(--color-accent);
    padding: 1px 6px;
    border-radius: 4px;
    letter-spacing: 0.05em;
    margin-left: 8px;
}
</style>
