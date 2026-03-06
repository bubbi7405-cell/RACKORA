<template>
    <div class="minimap-v2 glass-v2">
        <div class="minimap-header-v2">
            <span class="minimap-title-v2 l2-priority">GLOBAL_FACILITY_GRID</span>
            <div class="minimap-status-icon l1-priority">⧇</div>
        </div>
        <div class="minimap-grid-v2">
            <div v-for="room in sortedRooms" :key="room.id" class="minimap-node-v2" :class="{
                'is-active': selectedRoomId === room.id,
                'is-warning': room.warnings?.powerOverload || room.warnings?.bandwidthSaturated,
                'is-critical': room.warnings?.overheating || room.warnings?.powerOutage
            }" @click="gameStore.selectRoom(room.id)" 
               @mouseenter="tooltipStore.show($event, getRoomTooltip(room))"
               @mouseleave="tooltipStore.hide()">
                <div class="node-id-v2 l3-priority">{{ (room.name || 'NOD').substring(0, 3).toUpperCase() }}</div>
                <div class="node-bars-v2">
                    <div class="v-bar-bg">
                        <div class="v-bar-fill pwr" :style="{ height: Math.min(100, (room.power?.percent || 0)) + '%' }"></div>
                    </div>
                    <div class="v-bar-bg">
                        <div class="v-bar-fill heat" :style="{ height: Math.min(100, (room.cooling?.percent || 0)) + '%' }"></div>
                    </div>
                </div>
            </div>
            <div v-if="sortedRooms.length === 0" class="empty-minimap-v2 l3-priority">
                SIGNAL_LOSS: NO_FACILITY_UPLINK
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useTooltipStore } from '../../stores/tooltip';

const gameStore = useGameStore();
const tooltipStore = useTooltipStore();

const selectedRoomId = computed(() => gameStore.selectedRoomId);

const sortedRooms = computed(() => {
    if (!gameStore.rooms) return [];
    return Object.values(gameStore.rooms).sort((a, b) => a.name.localeCompare(b.name));
});

function getRoomTooltip(room) {
    if (!room) return null;
    let status = 'NOMINAL';
    if (room.warnings?.powerOutage) status = 'CRITICAL: TOTAL_BLACKOUT';
    else if (room.warnings?.overheating) status = 'CRITICAL: THERMAL_RUNAWAY';
    else if (room.warnings?.powerOverload) status = 'WARNING: POWER_CAP_EXCEEDED';
    else if (room.warnings?.bandwidthSaturated) status = 'WARNING: SIGNAL_SATURATION';

    return {
        title: (room.name || 'Unknown Node').toUpperCase(),
        content: `Energy: ${Math.round(room.power?.percent || 0)}% Load\nThermal: ${Math.round(room.cooling?.percent || 0)}% Sat\nStatus: ${status}`,
        hint: 'Click to re-orient command focus'
    };
}
</script>

<style scoped>
.minimap-v2 {
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 16px;
    border-radius: 2px;
}

.minimap-header-v2 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 8px;
}

.minimap-title-v2 {
    font-size: 0.5rem;
    font-weight: 950;
    color: var(--ds-text-ghost);
    letter-spacing: 0.25em;
}

.minimap-status-icon {
    font-size: 0.6rem;
    color: var(--ds-accent);
    opacity: 0.5;
}

.minimap-grid-v2 {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.minimap-node-v2 {
    width: 40px;
    height: 44px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 4px;
    transition: all 0.2s var(--ds-ease-spring);
}

.minimap-node-v2:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.3);
}

.minimap-node-v2.is-active {
    background: rgba(88, 166, 255, 0.05);
    border-color: var(--ds-accent);
    box-shadow: inset 0 0 10px rgba(88, 166, 255, 0.1);
}

.minimap-node-v2.is-warning { border-color: var(--ds-warning); }
.minimap-node-v2.is-critical {
    border-color: var(--ds-critical);
    background: rgba(248, 81, 73, 0.1);
    animation: ds-critical-pulse 1s infinite alternate;
}

@keyframes ds-critical-pulse {
    0% { background: rgba(248, 81, 73, 0.05); border-color: rgba(248, 81, 73, 0.3); }
    100% { background: rgba(248, 81, 73, 0.2); border-color: var(--ds-critical); }
}

.node-id-v2 {
    font-size: 0.45rem;
    font-weight: 950;
    font-family: var(--ds-font-mono);
    color: var(--ds-text-ghost);
}

.is-active .node-id-v2 { color: #fff; }

.node-bars-v2 {
    display: flex;
    gap: 2px;
    height: 16px;
    width: 100%;
}

.v-bar-bg {
    flex: 1;
    height: 100%;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: flex-end;
}

.v-bar-fill {
    width: 100%;
    transition: height 0.8s var(--ds-ease-spring);
}

.v-bar-fill.pwr { background: var(--ds-warning); opacity: 0.7; }
.v-bar-fill.heat { background: var(--ds-critical); opacity: 0.7; }

.is-critical .v-bar-fill { opacity: 1; }

.empty-minimap-v2 {
    font-size: 0.45rem;
    font-weight: 950;
    color: var(--ds-text-ghost);
    padding: 20px 0;
    width: 100%;
    text-align: center;
}
</style>

