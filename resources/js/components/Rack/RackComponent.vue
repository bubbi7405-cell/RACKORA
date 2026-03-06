<template>
    <div class="v2-rack-unit" :class="{
        'is-selected': isSelected,
        'thermal-view': showHeatmap,
        'high-load': ((rack.power?.current || 0) / (rack.power?.max || 1)) > 0.75,
        'thermal-critical': rack.temperature > 50
    }" @click="$emit('select')">



        <div class="rack-header">
            <div class="rack-title">RACK SM-{{ rack.id?.toString().slice(-4).toUpperCase() }}</div>
            <div class="rack-sub-info">
                <span>Cap: {{ rack.units?.used || 0 }} / {{ rack.units?.total || 0 }}U</span>
                <span>Stat: <span class="text-nominal">Stable</span></span>
            </div>
        </div>

        <div class="rack-main">
            <div class="u-slots">
                <div v-for="slot in slotDisplay" :key="slot.number" class="u-row" :class="{
                    'is-empty': slot.empty,
                    'is-target': isDropTarget(slot.number),
                    'is-invalid': isDropInvalid(slot.number)
                }" :style="[
                    { height: slot.empty ? '24px' : `calc(${slot.serverSize || 1} * 54px + ${(slot.serverSize || 1) - 1} * 2px)` },
                    getThermalStyle(slot.number)
                ]" @dragover="onDragOver($event, slot)" @dragleave="onDragLeave" @drop="onDrop($event, slot)">

                    <span v-if="slot.empty || slot.isServerStart" class="u-index l4-priority">U{{
                        String(slot.number).padStart(2, '0') }}</span>

                    <!-- Empty Slot -->
                    <div v-if="slot.empty" class="empty-slot-indicator" @click.stop="emit('openInventory', rack.id)">
                        <span class="empty-text">+ Deploy Server</span>
                    </div>

                    <!-- Server Slot -->
                    <template v-if="slot.isServerStart && slot.server">
                        <div class="asset-blade" :class="[
                            `type-${slot.server.type}`,
                            `status-${slot.server.status}`,
                            { 'tenant-unit': slot.isColo }
                        ]" draggable="true" @dragstart="onServerDragStart($event, slot.server)"
                            @click.stop="$emit('selectServer', slot.server.id)">

                            <!-- Hardware Grip Handles -->
                            <div class="blade-grip">
                                <div class="grip-line"></div>
                                <div class="grip-line"></div>
                                <div class="grip-line"></div>
                            </div>

                            <!-- Main Server Faceplate -->
                            <div class="blade-body">
                                <div class="blade-header">
                                    <span class="icon" :class="`text-${getServerColor(slot.server.type)}`">{{
                                        getServerIcon(slot.server.type) }}</span>
                                    <span class="model-name">[{{ getServerTypeLabel(slot.server.type) }} {{
                                        slot.server.modelName }}]</span>
                                    <span v-if="slot.isColo" class="tenant-tag">EXT_TENANT</span>
                                </div>
                                <div class="blade-metrics">
                                    <div class="metric-row">
                                        <span class="lbl">CPU</span>
                                        <div class="hw-bar">
                                            <div class="hw-fill cpu"
                                                :style="{ width: `${slot.server.load || Math.floor(Math.random() * 60 + 20)}%` }">
                                            </div>
                                        </div>
                                        <span class="val">{{ slot.server.load || Math.floor(Math.random() * 60 + 20)
                                            }}%</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="lbl">NET</span>
                                        <div class="hw-bar">
                                            <div class="hw-fill net"
                                                :style="{ width: `${slot.server.netUsage || Math.floor(Math.random() * 50 + 10)}%` }">
                                            </div>
                                        </div>
                                        <span class="val">{{ slot.server.netUsage || Math.floor(Math.random() * 50 + 10)
                                            }}%</span>
                                    </div>
                                    <div class="metric-row inline">
                                        <span class="inline-item">Temp {{ Math.round(slot.server.temperature ||
                                            rack.temperature || 22) }}°C</span>
                                        <span class="inline-item status-indicator">
                                            <span class="led" :class="getStatusLEDClass(slot.server.status)"></span>
                                            {{ getStatusText(slot.server.status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Hardware Air Vent Details -->
                            <div class="blade-vents">
                                <div class="fan-container">
                                    <div class="fan-hub"></div>
                                    <div class="fan-blades"></div>
                                </div>
                                <div class="fan-container">
                                    <div class="fan-hub"></div>
                                    <div class="fan-blades"></div>
                                </div>
                                <div class="fan-container">
                                    <div class="fan-hub"></div>
                                    <div class="fan-blades"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rack-telemetry">
                <div class="tel-item" :class="temperatureClass">
                    <span class="lbl">TEMP</span>
                    <div class="hw-bar tel-bar">
                        <div class="hw-fill cpu" :style="{ width: `${Math.min(100, rack.temperature * 2)}%` }"></div>
                    </div>
                    <span class="val align-r">{{ Math.round(rack.temperature) }}°C</span>
                </div>
                <div class="tel-item" :class="powerClass">
                    <span class="lbl">POWER</span>
                    <div class="hw-bar tel-bar">
                        <div class="hw-fill warning"
                            :style="{ width: `${Math.min(100, ((rack.power?.current || 0) / (rack.power?.max || 1)) * 100)}%` }">
                        </div>
                    </div>
                    <span class="val align-r">{{ (rack.power?.current || 0).toFixed(1) }}kW</span>
                </div>
                <div class="tel-item">
                    <span class="lbl">NETWORK</span>
                    <div class="hw-bar tel-bar">
                        <div class="hw-fill net" :style="{ width: `${Math.random() * 40 + 20}%` }"></div>
                    </div>
                    <span class="val align-r">{{ (Math.random() * 3 + 1).toFixed(1) }}Gbps</span>
                </div>
                <div class="tel-item revenue">
                    <span class="lbl">REVENUE</span>
                    <span class="val text-success flex-r">+$24/hr</span>
                </div>
                <div class="game-stats">
                    <div class="g-stat"><span class="lbl">Rack Efficiency</span><span class="val">82%</span></div>
                    <div class="g-stat"><span class="lbl">Cooling</span><span class="val text-success">Optimal</span>
                    </div>
                    <div class="g-stat"><span class="lbl">Energy Cost</span><span
                            class="val text-warning">-$12/hr</span></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useTooltipStore } from '../../stores/tooltip';

const props = defineProps({
    rack: { type: Object, required: true },
    isSelected: { type: Boolean, default: false },
    showHeatmap: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'selectServer', 'openInventory']);
const gameStore = useGameStore();
const tooltipStore = useTooltipStore();

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
        else if (parsed.type === 'inventory_server') gameStore.placeServer(props.rack.id, 'inventory', parsed.inventoryId, slot.number, parsed.generation, false);
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
        const slotInfo = slotDisplay.value?.find(slot => slot.number === s);
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

function getServerColor(type) {
    if (type === 'web' || type === 'app' || type === 'balancer') return 'info'; // Blue
    if (type === 'storage' || type === 'database') return 'cyan'; // Cyan
    if (type === 'gpu' || type === 'ai') return 'purple'; // Purple
    if (type === 'compute') return 'nominal'; // Green
    if (type === 'network') return 'warning'; // Orange
    return 'primary';
}

function getServerIcon(type) {
    if (type === 'web' || type === 'app' || type === 'balancer') return '🌐';
    if (type === 'storage' || type === 'database') return '💾';
    if (type === 'gpu' || type === 'ai') return '🎮';
    if (type === 'compute') return '⚡';
    if (type === 'network') return '🔌';
    return '🖥️';
}

function getServerTypeLabel(type) {
    let lower = type?.toLowerCase() || '';
    if (lower === 'web') return 'Web Node';
    if (lower === 'app') return 'App Server';
    if (lower === 'storage') return 'Storage';
    if (lower === 'database') return 'Database';
    if (lower === 'gpu') return 'GPU Node';
    if (lower === 'ai') return 'AI Node';
    if (lower === 'compute') return 'Compute';
    if (lower === 'network' || lower === 'balancer') return 'Network Switch';
    return 'Server';
}

function getStatusLEDClass(status) {
    if (status === 'online') return 'bg-nominal shadow-nominal';
    if (status === 'degraded' || status === 'warning') return 'bg-warning shadow-warning';
    if (status === 'offline' || status === 'error') return 'bg-critical shadow-critical';
    return 'bg-subtle';
}

function getStatusText(status) {
    if (status === 'online') return 'SYSTEM_READY';
    if (status === 'provisioning') return 'INITIALIZING...';
    if (status === 'degraded' || status === 'warning') return 'ATTN: PERFORMANCE';
    if (status === 'offline' || status === 'error') return 'SYSTEM_OFFLINE';
    return 'UNKNOWN_STATE';
}

function getServerTooltip(server) {
    if (!server) return null;
    return {
        title: server.modelName || 'Unknown Server',
        content: `Type: ${getServerTypeLabel(server.type)}\nCPU Load: ${server.load || Math.floor(Math.random() * 60 + 20)}%\nTemp: ${server.temperature || server.temp || 28}°C\nPower: ${server.powerUsage || 0}W`,
        hint: `Revenue: $${(server.revenueGeneration || server.revenue || 0).toFixed(2)}/hr`
    };
}
</script>

<style scoped>
.v2-rack-unit {
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-lg);
    position: relative;
    padding: 12px;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    box-shadow: var(--ds-shadow-card);
    overflow: hidden;
}

.v2-rack-unit:hover {
    box-shadow: var(--ds-shadow-md);
    border-color: #CBD5E1;
}

.v2-rack-unit.is-selected {
    border-color: var(--ds-accent);
    transform: translateY(-2px);
    box-shadow: 0 0 0 3px var(--ds-accent-soft), var(--ds-shadow-lg);
}

.rack-header {
    display: flex;
    flex-direction: column;
    margin-bottom: 8px;
    flex-shrink: 0;
}

.rack-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ds-text-primary);
    font-family: var(--ds-font-mono);
    letter-spacing: 0.05em;
    margin-bottom: 2px;
    text-align: center;
}

.rack-sub-info {
    font-size: 0.5625rem;
    color: var(--ds-text-muted);
    font-family: var(--ds-font-mono);
    display: flex;
    justify-content: center;
    gap: 12px;
}

.rack-main {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-height: 0;
}

.u-slots {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    background: #090D14;
    border-left: 6px solid #1E293B;
    border-right: 6px solid #1E293B;
    border-top: 3px solid #1E293B;
    border-bottom: 3px solid #1E293B;
    border-radius: 3px;
    padding: 3px 4px 3px 28px;
    position: relative;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.5);
}

/* Hide scrollbar but allow scrolling */
.u-slots::-webkit-scrollbar {
    width: 3px;
}

.u-slots::-webkit-scrollbar-track {
    background: transparent;
}

.u-slots::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 2px;
}

.u-row {
    position: relative;
    display: flex;
    align-items: stretch;
    margin-bottom: 1px;
    width: 100%;
}

.u-index {
    position: absolute;
    left: -24px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.5rem;
    font-weight: 600;
    font-family: var(--ds-font-mono);
    color: #4B5563;
}

/* ── RACK TELEMETRY (FOOTER) ──────────────── */
.rack-telemetry {
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: var(--ds-bg-subtle);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-md);
    padding: 5px;
    flex-shrink: 0;
}

.tel-item {
    font-size: 0.5rem;
    display: flex;
    align-items: center;
    padding: 0;
    gap: 4px;
}

.tel-item .lbl {
    color: var(--ds-text-ghost);
    font-weight: 600;
    width: 36px;
    font-family: var(--ds-font-mono);
    text-transform: uppercase;
    font-size: 0.5rem;
}

.tel-bar {
    height: 5px;
    flex: 1;
    background: #090D14;
    border: 1px solid #374151;
    border-radius: 1px;
}

.hw-fill.warning {
    background: repeating-linear-gradient(90deg, var(--ds-warning), var(--ds-warning) 3px, #1A2233 3px, #1A2233 4px);
}

.tel-item .val {
    font-family: var(--ds-font-mono);
    font-weight: 700;
    color: var(--ds-text-primary);
    font-size: 0.5rem;
}

.val.align-r {
    width: 40px;
    text-align: right;
}

.flex-r {
    flex: 1;
    text-align: right;
}

.tel-item.revenue .val {
    color: var(--ds-nominal);
}

.tel-item.critical .val {
    color: var(--ds-critical);
}

.tel-item.warning .val {
    color: var(--ds-warning);
}

.game-stats {
    margin-top: 2px;
    padding-top: 2px;
    border-top: 1px dashed var(--ds-border-color);
    display: grid;
    grid-template-columns: 1fr;
    gap: 1px;
}

.g-stat {
    display: flex;
    justify-content: space-between;
    font-size: 0.5rem;
    font-family: var(--ds-font-mono);
    color: var(--ds-text-ghost);
}

.g-stat .val {
    color: var(--ds-text-primary);
    font-weight: 600;
}

.g-stat .text-success {
    color: var(--ds-nominal);
}

.g-stat .text-warning {
    color: var(--ds-warning);
}

.u-row.is-target {
    background: var(--ds-accent-soft);
}

.u-row.is-invalid {
    background: var(--ds-critical-soft);
}

/* ── EMPTY SLOTS ──────────────────────────── */
.empty-slot-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    border: 1px dashed rgba(255, 255, 255, 0.15);
    border-radius: 2px;
    transition: all 0.2s;
}

.u-row:hover .empty-slot-indicator {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.3);
    cursor: pointer;
}

.empty-text {
    font-size: 0.5rem;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    opacity: 0;
    transition: opacity 0.2s;
}

.u-row:hover .empty-text {
    opacity: 1;
}

/* ── ASSET BLADES ──────────────────────────── */
.asset-blade {
    width: 100%;
    height: 100%;
    background: #1A2233;
    border: 1px solid #374151;
    display: flex;
    align-items: stretch;
    cursor: grab;
    transition: all 0.15s;
    border-radius: 2px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.asset-blade:hover {
    border-color: #6B7280;
    background: #1E293B;
    z-index: 10;
}

.blade-indicator {
    width: 4px;
    flex-shrink: 0;
}

/* Color Helpers */
.bg-info {
    background: var(--ds-info);
}

.bg-cyan {
    background: var(--ds-cyan);
}

.bg-purple {
    background: var(--ds-purple);
}

.bg-nominal {
    background: var(--ds-nominal);
}

.bg-warning {
    background: var(--ds-warning);
}

.bg-primary {
    background: var(--ds-accent);
}

.bg-critical {
    background: var(--ds-critical);
}

.shadow-nominal {
    box-shadow: 0 0 8px var(--ds-nominal);
}

.shadow-warning {
    box-shadow: 0 0 8px var(--ds-warning);
}

.shadow-critical {
    box-shadow: 0 0 8px var(--ds-critical);
}

.text-info {
    color: var(--ds-info);
}

.text-cyan {
    color: var(--ds-cyan);
}

.text-purple {
    color: var(--ds-purple);
}

.text-nominal {
    color: var(--ds-nominal);
}

.text-warning {
    color: var(--ds-warning);
}

.text-success {
    color: var(--ds-nominal);
}

/* ───────────────────────────────────────────────────────── */
/* BLADE VISUAL STATES                                       */
/* ───────────────────────────────────────────────────────── */

.asset-blade.status-online {
    border-color: #3B82F6;
    background: #1e293b;
    box-shadow: inset 4px 0 0 #3B82F6, 0 0 15px rgba(59, 130, 246, 0.2);
    position: relative;
    overflow: hidden;
}

.asset-blade.status-online .led {
    box-shadow: 0 0 10px #22c55e, 0 0 20px rgba(34, 197, 94, 0.4);
    background-color: #4ade80 !important;
}

.asset-blade.status-online::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(to bottom,
            transparent 0%,
            rgba(59, 130, 246, 0.05) 48%,
            rgba(59, 130, 246, 0.2) 50%,
            rgba(59, 130, 246, 0.05) 52%,
            transparent 100%);
    background-size: 100% 200%;
    animation: blade-scan 4s linear infinite;
    pointer-events: none;
    z-index: 5;
}

@keyframes blade-scan {
    from {
        background-position: 0 100%;
    }

    to {
        background-position: 0 -100%;
    }
}

.asset-blade.status-provisioning {
    border-color: #F59E0B;
    box-shadow: inset 4px 0 0 #F59E0B;
}

.asset-blade.status-degraded,
.asset-blade.status-warning {
    border-color: #EAB308;
    box-shadow: inset 4px 0 0 #EAB308;
}

.asset-blade.status-offline {
    background: #0f172a;
    border-color: #1e293b;
    opacity: 0.6;
    box-shadow: none;
    filter: grayscale(80%);
}

.asset-blade.status-offline:hover {
    opacity: 1;
    filter: grayscale(50%);
}

.asset-blade.status-offline .hw-fill {
    display: none !important;
}

.asset-blade.status-offline .blade-header .icon {
    opacity: 0.3;
}

.asset-blade.status-offline .lbl,
.asset-blade.status-offline .val {
    color: #4B5563 !important;
}

/* ── DETAILED HARDWARE BLADE ──────────────── */
.blade-grip {
    width: 12px;
    background: #111827;
    border-right: 1px solid #374151;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    flex-shrink: 0;
}

.grip-line {
    width: 6px;
    height: 2px;
    background: #4B5563;
    border-radius: 1px;
}

.blade-vents {
    width: 24px;
    background: #090D14;
    border-left: 1px solid #1E293B;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    align-items: center;
    padding: 0;
    flex-shrink: 0;
    overflow: hidden;
    position: relative;
    box-shadow: inset 2px 0 5px rgba(0, 0, 0, 0.5);
}

.fan-container {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #111827;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 0 4px #000;
}

.fan-blades {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    position: relative;
    background: conic-gradient(#374151 0deg 45deg,
            transparent 45deg 90deg,
            #374151 90deg 135deg,
            transparent 135deg 180deg,
            #374151 180deg 225deg,
            transparent 225deg 270deg,
            #374151 270deg 315deg,
            transparent 315deg 360deg);
}

.fan-hub {
    position: absolute;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #9CA3AF;
    z-index: 2;
}

@keyframes fan-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.status-online .fan-blades {
    animation: fan-spin 0.2s linear infinite;
}

.status-provisioning .fan-blades {
    animation: fan-spin 0.5s linear infinite;
}

.status-degraded .fan-blades {
    animation: fan-spin 0.4s linear infinite;
    background: conic-gradient(#B45309 0deg 45deg,
            transparent 45deg 90deg,
            #B45309 90deg 135deg,
            transparent 135deg 180deg,
            #374151 180deg 225deg,
            transparent 225deg 270deg,
            #374151 270deg 315deg,
            transparent 315deg 360deg);
}

.blade-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 4px 6px;
    gap: 2px;
    min-width: 0;
    overflow: hidden;
}

.blade-header {
    display: flex;
    align-items: center;
    gap: 4px;
}

.blade-header .icon {
    font-size: 0.65rem;
}

.blade-header .model-name {
    font-size: 0.5625rem;
    font-weight: 700;
    color: var(--ds-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: var(--ds-font-mono);
    flex: 1;
    min-width: 0;
}

.blade-metrics {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 2px;
}

.metric-row {
    display: flex;
    align-items: center;
    font-size: 0.5rem;
    font-family: var(--ds-font-mono);
    color: var(--ds-text-ghost);
    gap: 4px;
}

.metric-row .lbl {
    width: 24px;
    text-align: right;
    font-weight: 600;
    color: #6B7280;
}

.hw-bar {
    flex: 1;
    height: 5px;
    background: #090D14;
    border: 1px solid #374151;
    border-radius: 1px;
    overflow: hidden;
    display: flex;
}

.hw-fill {
    height: 100%;
}

.hw-fill.cpu {
    background: repeating-linear-gradient(90deg,
            var(--ds-info),
            var(--ds-info) 3px,
            #1A2233 3px,
            #1A2233 6px);
    background-size: 6px 100%;
}

.hw-fill.net {
    background: repeating-linear-gradient(90deg,
            var(--ds-purple),
            var(--ds-purple) 3px,
            #1A2233 3px,
            #1A2233 6px);
    background-size: 6px 100%;
}

@keyframes hardware-activity-scroll {
    to {
        background-position: -12px 0;
    }
}

.status-online .hw-fill {
    animation: hardware-activity-scroll 1s linear infinite;
}

.status-provisioning .hw-fill {
    animation: hardware-activity-scroll 0.5s linear infinite;
}

.metric-row .val {
    width: 24px;
    text-align: right;
    font-weight: 700;
}

.metric-row.inline {
    margin-top: 2px;
    justify-content: space-between;
    color: var(--ds-text-muted);
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
}

.led {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

@keyframes led-pulse {

    0%,
    100% {
        opacity: 1;
        transform: scale(1.1);
    }

    50% {
        opacity: 0.4;
        transform: scale(0.9);
    }
}

@keyframes led-blink-fast {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.1;
    }
}

.status-online .led {
    animation: led-pulse 2s infinite ease-in-out;
}

.status-provisioning .led {
    animation: led-pulse 0.8s infinite ease-in-out alternate;
}

.status-degraded .led {
    animation: led-blink-fast 0.6s infinite step-end;
}

.status-damaged .led {
    animation: led-blink-fast 0.2s infinite step-end;
}

.status-maintenance .led {
    animation: led-pulse 1.5s infinite ease-in-out;
}

/* Offline is handled by CSS above */

/* ── SIDE PANEL ────────────────────────────── */
.side-panel {
    width: 48px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.telemetry-node {
    background: var(--ds-bg-subtle);
    border: 1px solid var(--ds-border-color);
    padding: 6px;
    border-radius: var(--ds-radius-md);
    display: flex;
    flex-direction: column;
    text-align: center;
}

.telemetry-node .label {
    font-size: 0.5625rem;
    font-weight: 600;
    color: var(--ds-text-ghost);
    margin-bottom: 2px;
    text-transform: uppercase;
}

.telemetry-node .val {
    font-size: 0.75rem;
    font-weight: 700;
    font-family: var(--ds-font-mono);
    color: var(--ds-text-primary);
}

.telemetry-node.critical {
    border-color: var(--ds-critical);
    color: var(--ds-critical);
    background: var(--ds-critical-soft);
}

.telemetry-node.warning {
    border-color: var(--ds-warning);
    color: var(--ds-warning);
    background: var(--ds-warning-soft);
}

.colo-btn {
    margin-top: auto;
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-color);
    color: var(--ds-text-secondary);
    padding: 8px 0;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
    border-radius: var(--ds-radius-md);
}

.colo-btn:hover {
    border-color: var(--ds-accent);
    color: var(--ds-accent);
}

.colo-btn.active {
    color: #7C3AED;
    border-color: #7C3AED;
    background: rgba(124, 58, 237, 0.06);
}
</style>
