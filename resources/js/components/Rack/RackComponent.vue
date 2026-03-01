<template>
    <div class="rack-container" :class="{
        'rack-container--selected': isSelected,
        'rack-container--thermal': showHeatmap,
        'rack--high-load': ((rack.power?.current || 0) / (rack.power?.max || 1)) > 0.7,
        'rack--thermal-risk': rack.temperature > 30,
        'rack--critical': rack.temperature > 40 || ((rack.power?.current || 0) / (rack.power?.max || 1)) > 0.9
    }" @click="$emit('select')"
        :style="rack.ledColor ? { '--rack-led': rack.ledColor, 'box-shadow': `0 0 15px ${rack.ledColor}22` } : {}">
        <!-- LED Status Strips -->
        <div class="rack-led-strip left" :style="{ backgroundColor: rack.ledColor || '#00ff00' }"
            :class="`mode-${rack.ledMode || 'static'}`"></div>
        <div class="rack-led-strip right" :style="{ backgroundColor: rack.ledColor || '#00ff00' }"
            :class="`mode-${rack.ledMode || 'static'}`"></div>
        <div class="rack-summary">
            <span class="rack-label">UNIT_ID: {{ rack.id?.toString().slice(-4) || '----' }}</span>
            <div class="summary-actions">
                <button class="colo-toggle-btn" :class="{ 'is-active': rack.isColocationMode }"
                    @click.stop="gameStore.toggleColocation(rack.id)" title="Toggle Colocation Mode">
                    🏢
                </button>
                <span class="rack-specs">{{ rack.units?.used || 0 }}/{{ rack.units?.total || 0 }} U</span>
            </div>
        </div>

        <div class="rack-structure">
            <div class="power-zone-overlay"></div>
            <div class="slots-area">
                <div v-for="slot in slotDisplay" :key="slot.number" class="slot-u" :class="{
                    'slot--empty': slot.empty,
                    'slot--occupy': !slot.empty,
                    'slot--start': slot.isServerStart,
                    'slot--target': isDropTarget(slot.number),
                    'slot--invalid': isDropInvalid(slot.number),
                }" :style="[
                        slot.isServerStart ? { height: `calc(${slot.serverSize} * 22px)` } : {},
                        getThermalStyle(slot.number)
                    ]" @dragover="onDragOver($event, slot)" @dragleave="onDragLeave" @drop="onDrop($event, slot)">
                    <span class="u-index">{{ slot.number }}</span>

                    <template v-if="slot.isServerStart && slot.server">
                        <div class="server-blade" :class="[
                            `type--${slot.server.type}`,
                            `status--${slot.server.status}`,
                            { 'is-thermal-glass': showHeatmap, 'is-colo': slot.isColo }
                        ]" draggable="true" :id="'server-' + slot.server.id"
                            @dragstart="onServerDragStart($event, slot.server)"
                            @click.stop="$emit('selectServer', slot.server.id)">
                            <div v-if="showHeatmap" class="thermal-shimmer" :style="getShimmerStyle(slot.number)"></div>
                            <div class="blade-edge"></div>
                            <div class="blade-body">
                                <div class="blade-meta">
                                    <span class="blade-name">{{ slot.server.modelName || 'Tenant Area' }}</span>
                                    <span v-if="slot.isColo" class="rental-badge is-colo">TENANT</span>
                                    <span v-if="slot.server.tenantId" class="rental-badge">RENTED_OUT</span>
                                    <span v-if="slot.server.isLeased" class="rental-badge is-inbound">LEASED</span>
                                    <div class="blade-status-dot"></div>
                                </div>
                                <div class="activity-pip" v-for="n in 3" :key="n"
                                    :class="{ 'active': slot.server.status === 'online' || slot.server.status === 'degraded' }">
                                </div>

                                <!-- Battery Level Overlay (Only for type battery) -->
                                <div v-if="slot.server.type === 'battery'" class="battery-status">
                                    <div class="battery-level-bar"
                                        :style="{ width: `${slot.server.battery?.percent || 0}%` }"></div>
                                    <div class="battery-label">{{ Math.round(slot.server.battery?.percent || 0) }}%
                                    </div>
                                </div>
                            </div>
                            <div v-if="slot.server.status === 'eol'" class="eol-marker">
                                💀
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rack-telemetry">
                <div class="telemetry-item" :class="temperatureClass">
                    <span class="tel-label">TMP</span>
                    <span class="tel-val">{{ Math.round(rack.temperature) }}°C</span>
                </div>
                <div class="telemetry-item" :class="powerClass">
                    <span class="tel-label">PWR</span>
                    <span class="tel-val">{{ rack.power?.current?.toFixed(1) }}k</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const props = defineProps({
    rack: { type: Object, required: true },
    isSelected: { type: Boolean, default: false },
    showHeatmap: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'selectServer']);
const gameStore = useGameStore();

const dropTargetSlot = ref(null);
const dropValid = ref(true);
const draggingSize = ref(0);

const slotDisplay = computed(() => {
    const slots = [];
    const totalUnits = props.rack.units?.total || 42;
    const backendSlots = props.rack.slots || {};

    for (let u = totalUnits; u >= 1; u--) {
        const slotData = backendSlots[u] || { empty: true };

        if (slotData.isColo) {
            slots.push({
                number: u,
                empty: false,
                isServerStart: true,
                isColo: true,
                server: {
                    id: slotData.serverId,
                    status: slotData.serverStatus,
                    modelName: slotData.modelName,
                    sizeU: 1,
                    type: 'colo'
                },
                serverSize: 1
            });
        } else if (slotData.serverId && slotData.isStart) {
            const server = props.rack.servers?.find(s => s.id === slotData.serverId);
            slots.push({
                number: u,
                empty: false,
                isServerStart: true,
                server: server,
                serverSize: server?.sizeU || 1
            });
        } else if (slotData.empty) {
            slots.push({ number: u, empty: true, isServerStart: false, server: null, serverSize: 0 });
        }
    }
    return slots;
});

const temperatureClass = computed(() => {
    if (props.rack.temperature > 40) return 'critical';
    if (props.rack.temperature > 30) return 'warning';
    return 'normal';
});

const powerClass = computed(() => {
    const ratio = (props.rack.power?.current || 0) / (props.rack.power?.max || 1);
    if (ratio > 0.9) return 'critical';
    if (ratio > 0.7) return 'warning';
    return 'normal';
});

function isDropTarget(n) { return dropTargetSlot.value === n && dropValid.value; }
function isDropInvalid(n) { return dropTargetSlot.value === n && !dropValid.value; }

function onDragOver(event, slot) {
    event.preventDefault();
    if (!slot.empty) { dropValid.value = false; dropTargetSlot.value = slot.number; return; }
    if (gameStore.draggedServer) draggingSize.value = gameStore.draggedServer.sizeU;
    dropTargetSlot.value = slot.number;
    dropValid.value = canPlaceAt(slot.number, draggingSize.value);
}

function onDragLeave() { dropTargetSlot.value = null; dropValid.value = true; }

function onDrop(event, slot) {
    event.preventDefault(); dropTargetSlot.value = null;
    const data = event.dataTransfer.getData('application/json');
    if (!data) return;
    try {
        const parsed = JSON.parse(data);
        if (parsed.type === 'new_server') gameStore.placeServer(props.rack.id, parsed.category, parsed.modelKey, slot.number, parsed.generation, parsed.isLeased);
        else if (parsed.type === 'existing_server') gameStore.moveServer(parsed.serverId, props.rack.id, slot.number);
    } catch (e) { }
}

function onServerDragStart(event, server) {
    event.dataTransfer.setData('application/json', JSON.stringify({ type: 'existing_server', serverId: server.id, sizeU: server.sizeU }));
    gameStore.startDrag(server);
}

function canPlaceAt(startSlot, sizeU) {
    for (let s = startSlot; s < startSlot + sizeU; s++) {
        if (s > (props.rack.units?.total || 42)) return false;
        const slotInfo = slotDisplay.value.find(slot => slot.number === s);
        if (slotInfo && !slotInfo.empty) return false;
    }
    return true;
}

function getThermalStyle(slotNumber) {
    if (!props.showHeatmap) return {};

    const temp = props.rack.thermalMap ? (props.rack.thermalMap[slotNumber] || props.rack.temperature) : props.rack.temperature;

    let color = '';
    let opacity = 0;

    if (temp < 25) {
        color = '0, 150, 255'; // Blue (Ambient)
        opacity = 0.2;
    } else if (temp < 30) {
        color = '0, 255, 200'; // Cyan (Cool)
        opacity = 0.3;
    } else if (temp < 35) {
        color = '100, 255, 0'; // Lime (Normal)
        opacity = 0.4;
    } else if (temp < 40) {
        color = '255, 200, 0'; // Yellow (Warm)
        opacity = 0.5;
    } else if (temp < 45) {
        color = '255, 100, 0'; // Orange (Hot)
        opacity = 0.6;
    } else if (temp < 50) {
        color = '255, 0, 0'; // Red (Critical)
        opacity = 0.75;
    } else {
        color = '255, 0, 255'; // Magenta (Meltdown)
        opacity = 0.85;
    }

    return {
        backgroundColor: `rgba(${color}, ${opacity})`,
        boxShadow: `inset 0 0 10px rgba(${color}, ${opacity * 0.8})`,
        borderTop: `1px solid rgba(${color}, ${opacity})`,
        zIndex: temp > 40 ? 5 : 1,
        '--slot-temp': `'${Math.round(temp)}°C'` // CSS variable for tooltip
    };
}

function getShimmerStyle(slotNumber) {
    const temp = props.rack.thermalMap ? (props.rack.thermalMap[slotNumber] || props.rack.temperature) : props.rack.temperature;
    if (temp < 35) return { display: 'none' };

    const intensity = Math.min(1, (temp - 30) / 20);
    return {
        opacity: intensity,
        animationDuration: `${1.5 - (intensity * 1.2)}s`
    };
}
</script>

<style scoped>
.rack-container {
    background: var(--v3-bg-surface);
    border: var(--v3-border-heavy);
    padding: var(--v2-space-md);
    display: flex;
    flex-direction: column;
    gap: var(--v2-space-md);
    transition: all var(--v3-transition-base);
    min-width: 220px;
    border-radius: var(--v3-radius);
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), inset 0 0 20px rgba(255, 255, 255, 0.01);
}

/* RACK HERO STATES */
.rack-container--thermal {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6), inset 0 0 40px rgba(255, 100, 0, 0.05);
}

.rack--high-load {
    box-shadow: 0 10px 30px rgba(47, 107, 255, 0.1), inset 0 0 30px rgba(47, 107, 255, 0.05);
}

.rack--thermal-risk {
    border-color: var(--v3-warning);
}

.rack--critical {
    border-color: var(--v3-danger);
    animation: v3-crit-glow 1.2s infinite ease-in-out;
}

.rack--thermal-risk::after {
    content: '';
    position: absolute;
    inset: -1px;
    background: linear-gradient(0deg, rgba(239, 68, 68, 0.1), transparent);
    pointer-events: none;
    animation: v3-heat-shimmer 2s infinite alternate ease-in-out;
    border-radius: var(--v3-radius);
}

@keyframes v3-heat-shimmer {
    from {
        opacity: 0.3;
        transform: scale(1);
    }

    to {
        opacity: 0.7;
        transform: scale(1.01);
    }
}

.rack-container--selected {
    border-color: var(--v3-accent);
    box-shadow: 0 0 30px var(--v3-accent-glow);
    transform: translateY(-4px);
}

.rack-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--font-family-mono);
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--v3-text-secondary);
    border-bottom: var(--v3-border-soft);
    padding-bottom: 6px;
    letter-spacing: 0.1em;
}

.summary-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.colo-toggle-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 0.6rem;
    padding: 2px 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    opacity: 0.5;
}

.colo-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    opacity: 1;
}

.colo-toggle-btn.is-active {
    background: #8b5cf6;
    border-color: #a78bfa;
    opacity: 1;
    box-shadow: 0 0 10px rgba(139, 92, 246, 0.4);
}

.rack-structure {
    display: flex;
    gap: var(--v2-space-md);
    position: relative;
}

/* Power Zone Layer (Subtle) */
.power-zone-overlay {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 48px;
    background: linear-gradient(90deg, transparent, rgba(47, 107, 255, 0.02));
    pointer-events: none;
    z-index: 1;
}

.slots-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(0, 0, 0, 0.5);
    border: var(--v3-border-soft);
    min-height: 320px;
    position: relative;
}

.slot-u {
    height: 22px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.02);
    position: relative;
    display: flex;
    align-items: center;
    transition: background var(--v3-transition-fast);
}

.u-index {
    position: absolute;
    left: -28px;
    font-size: 0.55rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
}

.slot--target {
    background: var(--v3-accent-soft);
    box-shadow: inset 0 0 0 1px var(--v3-accent);
}

.slot--invalid {
    background: rgba(255, 77, 79, 0.1);
    box-shadow: inset 0 0 0 1px var(--v3-danger);
}

/* SERVER BLADE REFINEMENT */
.server-blade {
    width: 100%;
    height: 100%;
    background: var(--v3-bg-overlay);
    border: var(--v3-border-soft);
    display: flex;
    cursor: grab;
    transition: all var(--v3-transition-fast);
    position: relative;
    overflow: hidden;
}

.server-blade::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, transparent 100%);
    pointer-events: none;
}

.server-blade:hover {
    border-color: var(--v3-accent);
    background: var(--v3-bg-accent);
    z-index: 2;
}

.is-thermal-glass {
    background: rgba(0, 0, 0, 0.2) !important;
    backdrop-filter: blur(2px);
    border-color: rgba(255, 255, 255, 0.05) !important;
}

.thermal-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    background-size: 200% 100%;
    animation: v3-shimmer linear infinite;
    pointer-events: none;
}

@keyframes v3-shimmer {
    from {
        background-position: -100% 0;
    }

    to {
        background-position: 100% 0;
    }
}

.server-blade:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.status--online::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 2px;
    height: 100%;
    background: var(--v3-success);
    box-shadow: 0 0 10px var(--v3-success);
    animation: v3-power-pulse 2s infinite ease-in-out;
}

@keyframes v3-power-pulse {

    0%,
    100% {
        opacity: 0.5;
    }

    50% {
        opacity: 1;
        box-shadow: 0 0 15px var(--v3-success);
    }
}

.status--eol {
    opacity: 0.4;
    cursor: default !important;
    filter: grayscale(1);
}

.status--eol::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0, 0, 0, 0.2) 10px, rgba(0, 0, 0, 0.2) 20px);
}

.blade-edge {
    width: 3px;
    background: var(--v3-text-ghost);
    transition: background var(--v3-transition-base);
}

.status--online .blade-edge {
    background: var(--v3-success);
    box-shadow: 0 0 8px var(--v3-success);
}

.status--damaged .blade-edge {
    background: var(--v3-danger);
    box-shadow: 0 0 8px var(--v3-danger);
}

.blade-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
}

.blade-name {
    font-size: 0.55rem;
    font-weight: 700;
    font-family: var(--font-family-mono);
    color: var(--v3-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: 0.05em;
    max-width: 90px;
}

.status--eol .blade-name {
    color: #444;
}

.eol-marker {
    position: absolute;
    right: 8px;
    font-size: 0.7rem;
    opacity: 0.6;
}

.blade-status-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--v3-text-ghost);
    margin-left: 8px;
}

.status--online .blade-status-dot {
    background: var(--v3-success);
    box-shadow: 0 0 5px var(--v3-success);
}

.blade-activity {
    display: flex;
    gap: 3px;
}

.activity-pip {
    width: 2px;
    height: 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 1px;
}

.activity-pip.active {
    background: var(--v3-success);
    opacity: 0.6;
    animation: v3-pulse-state 1s infinite;
}

/* RACK TELEMETRY */
.rack-telemetry {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 48px;
    z-index: 2;
}

.telemetry-item {
    display: flex;
    flex-direction: column;
    background: rgba(0, 0, 0, 0.3);
    border: var(--v3-border-soft);
    padding: 6px;
    border-radius: var(--v3-radius);
    transition: all var(--v3-transition-base);
}

.tel-label {
    font-size: 0.45rem;
    color: var(--v3-text-ghost);
    font-weight: 900;
    letter-spacing: 0.1em;
    margin-bottom: 2px;
}

.tel-val {
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-primary);
    font-weight: 700;
}

.telemetry-item.critical {
    border-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.05);
}

.telemetry-item.critical .tel-val {
    color: var(--v3-danger);
}

.telemetry-item.warning {
    border-color: var(--v3-warning);
    background: rgba(244, 180, 0, 0.05);
}

.telemetry-item.warning .tel-val {
    color: var(--v3-warning);
}

.rental-badge {
    font-size: 0.5rem;
    background: #6e7681;
    color: #fff;
    padding: 1px 4px;
    border-radius: 3px;
    margin-left: 6px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.rental-badge.is-inbound {
    background: var(--v3-accent);
    box-shadow: 0 0 10px var(--v3-accent-glow);
}

.rental-badge.is-colo {
    background: #8b5cf6;
    /* Purple */
    box-shadow: 0 0 10px rgba(139, 92, 246, 0.4);
}

.type--colo {
    background: linear-gradient(135deg, #1e1b4b, #2e1065) !important;
}

.type--colo .blade-edge {
    background: #a78bfa !important;
    box-shadow: 0 0 8px #a78bfa !important;
}

/* BATTERY STYLING */
.type--battery {
    background: linear-gradient(90deg, #09090b 0%, #1c1917 100%) !important;
}

.type--battery .blade-edge {
    background: #fbbf24 !important;
    box-shadow: 0 0 10px rgba(251, 191, 36, 0.4) !important;
}

.type--battery .blade-name {
    color: #fbbf24;
    font-weight: 900;
}

.battery-status {
    position: absolute;
    right: 32px;
    height: 10px;
    width: 60px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(251, 191, 36, 0.2);
    border-radius: 2px;
    display: flex;
    align-items: center;
    overflow: hidden;
}

.battery-level-bar {
    height: 100%;
    background: linear-gradient(90deg, #d97706, #fbbf24);
    box-shadow: 0 0 8px rgba(251, 191, 36, 0.3);
    transition: width 0.5s ease;
}

.battery-label {
    position: absolute;
    inset: 0;
    font-size: 0.45rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
}

/* THERMAL TOOLTIP */
.rack-container--thermal .slot-u:hover::after {
    content: var(--slot-temp);
    position: absolute;
    right: -40px;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    z-index: 100;
    pointer-events: none;
    font-family: var(--font-family-mono);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.rack-led-strip {
    position: absolute;
    top: 15%;
    bottom: 15%;
    width: 2px;
    opacity: 0.6;
    border-radius: 1px;
    z-index: 5;
    pointer-events: none;
    transition: all 0.3s ease;
}

.rack-led-strip.left {
    left: 4px;
}

.rack-led-strip.right {
    right: 4px;
}

.mode-pulse {
    animation: led-pulse 2s infinite ease-in-out;
}

.mode-rainbow {
    animation: led-rainbow 5s infinite linear;
}

@keyframes led-pulse {

    0%,
    100% {
        opacity: 0.3;
        filter: brightness(0.8);
    }

    50% {
        opacity: 1;
        filter: brightness(1.5);
        box-shadow: 0 0 10px currentColor;
    }
}

@keyframes led-rainbow {
    0% {
        filter: hue-rotate(0deg);
    }

    100% {
        filter: hue-rotate(360deg);
    }
}
</style>
