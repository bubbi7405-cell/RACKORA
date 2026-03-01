<template>
    <div class="minimap-panel glass-panel">
        <div class="minimap-header">
            <span class="minimap-title">GLOBAL_FACILITY_GRID</span>
        </div>
        <div class="minimap-grid">
            <div v-for="room in sortedRooms" :key="room.id" class="minimap-node" :class="{
                'active': selectedRoomId === room.id,
                'warning': room.warnings?.powerOverload || room.warnings?.bandwidthSaturated,
                'danger': room.warnings?.overheating || room.warnings?.powerOutage
            }" @click="gameStore.selectRoom(room.id)" v-tooltip="getRoomTooltip(room)">
                <div class="node-id">{{ (room.name || 'NOD').substring(0, 3).toUpperCase() }}</div>
                <div class="node-bars">
                    <div class="n-bar">
                        <div class="fill power" :style="{ height: Math.min(100, (room.power?.percent || 0)) + '%' }">
                        </div>
                    </div>
                    <div class="n-bar">
                        <div class="fill heat" :style="{ height: Math.min(100, (room.cooling?.percent || 0)) + '%' }">
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="sortedRooms.length === 0" class="empty-minimap">
                NO LINK
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();

const selectedRoomId = computed(() => gameStore.selectedRoomId);

const sortedRooms = computed(() => {
    if (!gameStore.rooms) return [];
    return Object.values(gameStore.rooms).sort((a, b) => {
        // Sort active room first, or just alphabetically
        return a.name.localeCompare(b.name);
    });
});

function getRoomTooltip(room) {
    if (!room) return null;
    let status = 'NOMINAL';
    if (room.warnings?.powerOutage) status = 'CRITICAL: BLACKOUT';
    else if (room.warnings?.overheating) status = 'CRITICAL: OVERHEATING';
    else if (room.warnings?.powerOverload) status = 'WARNING: POWER CAPACITY EXCEEDED';
    else if (room.warnings?.bandwidthSaturated) status = 'WARNING: BANDWIDTH SATURATED';

    return {
        title: (room.name || 'Unknown Node').toUpperCase(),
        content: `Power: ${Math.round(room.power?.percent || 0)}%\nHeat: ${Math.round(room.cooling?.percent || 0)}%\nStatus: ${status}`,
        hint: 'Click to switch focus'
    };
}
</script>

<style scoped>
.minimap-panel {
    background: rgba(0, 0, 0, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: var(--v3-radius);
    padding: 12px;
}

.minimap-header {
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 6px;
    margin-bottom: 10px;
}

.minimap-title {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.2em;
}

.minimap-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.empty-minimap {
    font-size: 0.5rem;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.minimap-node {
    width: 36px;
    height: 36px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 3px 2px;
    transition: all var(--v3-transition-fast);
}

.minimap-node:hover {
    border-color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.1);
}

.minimap-node.active {
    border-color: var(--v3-accent);
    box-shadow: 0 0 10px rgba(58, 134, 255, 0.2);
    background: rgba(58, 134, 255, 0.05);
}

.minimap-node.warning {
    border-color: var(--v3-warning);
}

.minimap-node.danger {
    border-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.1);
    animation: minimap-pulse 1.2s infinite ease-in-out;
}

@keyframes minimap-pulse {

    0%,
    100% {
        border-color: rgba(255, 77, 79, 0.4);
    }

    50% {
        border-color: rgba(255, 77, 79, 1);
        box-shadow: 0 0 8px rgba(255, 77, 79, 0.5);
    }
}

.node-id {
    font-size: 0.5rem;
    font-family: var(--font-family-mono);
    font-weight: 900;
    color: var(--v3-text-secondary);
    margin-top: 2px;
}

.active .node-id {
    color: var(--v3-accent);
}

.danger .node-id {
    color: var(--v3-danger);
}

.node-bars {
    display: flex;
    gap: 2px;
    height: 12px;
    width: 100%;
    justify-content: center;
    align-items: flex-end;
}

.n-bar {
    width: 8px;
    height: 100%;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: flex-end;
}

.n-bar .fill {
    width: 100%;
    transition: height 0.5s;
}

.n-bar .fill.power {
    background: var(--v3-warning);
}

.n-bar .fill.heat {
    background: var(--v3-danger);
}
</style>
