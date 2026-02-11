<template>
    <div 
        class="rack-container" 
        :class="{ 'rack-container--selected': isSelected }"
        @click="$emit('select')"
    >
        <div class="rack-header">
            <div class="rack-header__info">
                <span class="rack-header__name">{{ rack.name }}</span>
                <span class="rack-header__type">{{ rack.type.replace('rack_', '') }}</span>
            </div>
            <div class="rack-header__stats">
                <span class="rack-header__capacity">{{ rack.units.used }}/{{ rack.units.total }}U</span>
            </div>
        </div>

        <!-- Visual Rack Representation -->
        <div class="rack-visual">
            <!-- Status bar -->
            <div class="rack-status-bar">
                <div class="status-indicator" :class="temperatureClass" :title="`${rack.temperature?.toFixed(1) || '0.0'}°C`">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>
                    </svg>
                </div>
                <div class="status-indicator" :class="powerClass" :title="`${rack.power?.current?.toFixed(1) || '0.0'}/${rack.power?.max?.toFixed(1) || '0.0'} kW`">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
            </div>

            <!-- U Slots -->
            <div class="rack-slots" ref="slotsContainer">
                <div 
                    v-for="slot in slotDisplay" 
                    :key="slot.number"
                    class="rack-slot"
                    :class="{
                        'rack-slot--empty': slot.empty,
                        'rack-slot--server-start': slot.isServerStart,
                        'rack-slot--server-middle': slot.isServerMiddle,
                        'rack-slot--server-end': slot.isServerEnd,
                        'rack-slot--drop-target': isDropTarget(slot.number),
                        'rack-slot--drop-invalid': isDropInvalid(slot.number),
                    }"
                    :style="slot.isServerStart ? { height: `${slot.serverSize * 20}px` } : {}"
                    @dragover="onDragOver($event, slot)"
                    @dragleave="onDragLeave"
                    @drop="onDrop($event, slot)"
                >
                    <span class="rack-slot__number">{{ slot.number }}</span>
                    
                    <template v-if="slot.isServerStart && slot.server">
                        <div 
                            class="server-unit"
                            :class="[
                                `server-unit--${slot.server.type}`,
                                `server-unit--status-${slot.server.status}`
                            ]"
                            draggable="true"
                            @dragstart="onServerDragStart($event, slot.server)"
                            @click.stop="$emit('selectServer', slot.server.id)"
                        >
                            <!-- Rack Ears (Left) -->
                            <div class="server-ear">
                                <div class="screw"></div>
                            </div>

                            <!-- Front Panel Content -->
                            <div class="server-face">
                                <!-- Left Control Panel -->
                                <div class="server-controls">
                                    <div class="power-btn" :class="{ 'on': slot.server.status === 'online' }">
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16.56,5.44L15.11,6.89C16.84,7.94 18,9.83 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12C6,9.83 7.16,7.94 8.88,6.88L7.44,5.44C5.36,6.88 4,9.28 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12C20,9.28 18.64,6.88 16.56,5.44M13,3H11V13H13"/></svg>
                                    </div>
                                    <div class="status-leds">
                                        <div class="led" :class="getLedClass(slot.server, 'sys')" :style="{ animationDelay: Math.random() * 2 + 's' }" title="System Status"></div>
                                        <div class="led" :class="getLedClass(slot.server, 'net')" :style="{ animationDelay: Math.random() * 0.5 + 's' }" title="Network Activity"></div>
                                        <div class="led" :class="getLedClass(slot.server, 'disk')" :style="{ animationDelay: Math.random() * 1 + 's' }" title="Disk Activity"></div>
                                    </div>
                                </div>

                                <!-- Center: Vent/Drive Bays depending on type -->
                                <div class="server-body">
                                    <div v-if="slot.server.type === 'storage_server'" class="drive-bays">
                                        <div v-for="n in Math.min(12, slot.server.sizeU * 4)" :key="n" class="drive-bay">
                                            <div class="drive-led blinking-random" :style="{ animationDelay: Math.random() * 3 + 's' }"></div>
                                        </div>
                                    </div>
                                    <div v-else class="vent-grill">
                                        <span class="server-brand">{{ slot.server.modelName }}</span>
                                    </div>
                                </div>

                                <!-- Right Info Panel -->
                                <div class="server-info">
                                    <span v-if="slot.server.type === 'storage_server'" class="server-model-text">{{ slot.server.modelName }}</span>
                                    <div class="ports-mockup">
                                        <div class="port-usb"></div>
                                        <div class="port-eth"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rack Ears (Right) -->
                            <div class="server-ear">
                                <div class="screw"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Rack Footer Stats -->
        <div class="rack-footer">
            <div class="rack-stat">
                <span class="rack-stat__label">Power</span>
                <div class="progress-bar" :class="powerClass">
                    <div class="progress-bar__fill" :style="{ width: powerPercent + '%' }"></div>
                </div>
                <span class="rack-stat__value">{{ rack.power?.current?.toFixed(1) || '0.0' }} kW</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const props = defineProps({
    rack: {
        type: Object,
        required: true,
    },
    isSelected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select', 'selectServer']);

const gameStore = useGameStore();
const dropTargetSlot = ref(null);
const dropValid = ref(true);
const draggingSize = ref(0);

// Helper to determine LED colors
function getLedClass(server, type) {
    if (server.status === 'offline') return '';
    if (server.status === 'provisioning') return 'led--yellow led--blink';
    if (server.status === 'damaged') return 'led--red led--blink';
    
    // Online behaviors
    if (type === 'sys') return 'led--green';
    if (type === 'net') return 'led--blue led--flicker'; // Network flickers
    if (type === 'disk') return 'led--green led--blink-slow'; // Disk blinks slowly
    return '';
}

// Calculate slot display with server information
const slotDisplay = computed(() => {
    const slots = [];
    const serverPositions = {};

    // Map servers to their positions
    if (props.rack.servers) {
        // console.log('Rack servers:', props.rack.servers);
        for (const server of props.rack.servers) {
            if (server.startSlot) {
                serverPositions[server.startSlot] = {
                    server,
                    size: server.sizeU || 1,
                };
            }
        }
    }

    // Build slot display from top to bottom (highest U first for visual)
    for (let u = props.rack.units.total; u >= 1; u--) {
        const serverData = serverPositions[u];
        
        if (serverData) {
            slots.push({
                number: u,
                empty: false,
                isServerStart: true,
                isServerMiddle: false,
                isServerEnd: false,
                server: serverData.server,
                serverSize: serverData.size,
            });
            // Skip the slots covered by this server (they won't be displayed separately)
            // Actually we need to mark the covered slots
        } else {
            // Check if this slot is covered by a server above
            let covered = false;
            for (const [startSlot, data] of Object.entries(serverPositions)) {
                const start = parseInt(startSlot);
                if (u > start && u < start + data.size) {
                    covered = true;
                    break;
                }
            }

            if (!covered) {
                slots.push({
                    number: u,
                    empty: true,
                    isServerStart: false,
                    isServerMiddle: false,
                    isServerEnd: false,
                    server: null,
                    serverSize: 0,
                });
            }
        }
    }

    return slots;
});

const temperatureClass = computed(() => {
    if (props.rack.temperature > 40) return 'status-indicator--danger';
    if (props.rack.temperature > 30) return 'status-indicator--warning';
    return 'status-indicator--normal';
});

const powerPercent = computed(() => {
    if (!props.rack.power || !props.rack.power.max) return 0;
    return (props.rack.power.current / props.rack.power.max) * 100;
});

const powerClass = computed(() => {
    if (powerPercent.value > 90) return 'progress-bar--danger';
    if (powerPercent.value > 70) return 'progress-bar--warning';
    return '';
});

function isDropTarget(slotNumber) {
    return dropTargetSlot.value === slotNumber && dropValid.value;
}

function isDropInvalid(slotNumber) {
    return dropTargetSlot.value === slotNumber && !dropValid.value;
}

function onDragOver(event, slot) {
    event.preventDefault();
    
    if (!slot.empty) {
        dropValid.value = false;
        dropTargetSlot.value = slot.number;
        return;
    }

    // Try to parse dragged data
    const data = event.dataTransfer.getData('application/json');
    if (data) {
        try {
            const parsed = JSON.parse(data);
            draggingSize.value = parsed.sizeU || 1;
        } catch (e) {}
    }

    dropTargetSlot.value = slot.number;
    dropValid.value = canPlaceAt(slot.number, draggingSize.value);
}

function onDragLeave() {
    dropTargetSlot.value = null;
    dropValid.value = true;
}

function onDrop(event, slot) {
    event.preventDefault();
    dropTargetSlot.value = null;

    const data = event.dataTransfer.getData('application/json');
    if (!data) return;

    try {
        const parsed = JSON.parse(data);
        
        if (parsed.type === 'new_server') {
            // New server from catalog
            gameStore.placeServer(
                props.rack.id,
                parsed.category,
                parsed.modelKey,
                slot.number
            );
        } else if (parsed.type === 'existing_server') {
            // Moving existing server
            gameStore.moveServer(
                parsed.serverId,
                props.rack.id,
                slot.number
            );
        }
    } catch (e) {
        console.error('Failed to parse drop data:', e);
    }
}

function onServerDragStart(event, server) {
    event.dataTransfer.setData('application/json', JSON.stringify({
        type: 'existing_server',
        serverId: server.id,
        sizeU: server.sizeU,
    }));
    event.dataTransfer.effectAllowed = 'move';
    gameStore.startDrag(server);
}

function canPlaceAt(startSlot, sizeU) {
    // Check if slots are available
    for (let s = startSlot; s < startSlot + sizeU; s++) {
        if (s > props.rack.units.total) return false;
        
        const slotInfo = slotDisplay.value.find(slot => slot.number === s);
        if (slotInfo && !slotInfo.empty) return false;
    }
    return true;
}
</script>

<style scoped>
.rack-container {
    background: #0f1115;
    border: 2px solid #2d333b;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s ease;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.rack-container:hover {
    border-color: #57606a;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
}

.rack-container--selected {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(0, 255, 157, 0.2), 0 0 20px rgba(0, 255, 157, 0.1);
}

.rack-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #161b22;
    border-bottom: 1px solid #2d333b;
}

.rack-header__name {
    font-weight: 600;
    font-size: 0.85rem;
    color: #e6edf3;
}

.rack-header__type {
    font-family: monospace;
    font-size: 0.7rem;
    color: var(--color-primary);
    background: rgba(0, 255, 157, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 8px;
    text-transform: uppercase;
}

.rack-header__capacity {
    font-family: monospace;
    font-size: 0.75rem;
    color: #8b949e;
}

.rack-visual {
    display: flex;
    padding: 8px;
    background: #0a0c10;
    min-height: 200px;
}

.rack-status-bar {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-right: 8px;
    border-right: 1px solid #2d333b;
    margin-right: 8px;
}

.status-indicator {
    width: 14px;
    height: 14px;
    color: #484f58;
}

.status-indicator--normal { color: #2ea043; }
.status-indicator--warning { color: #dbab09; }
.status-indicator--danger { color: #f85149; animation: pulse 1s infinite; }

.rack-slots {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
    background: #161b22;
    border: 1px solid #30363d;
    padding: 1px;
}

.rack-slot {
    min-height: 22px; /* Standard 1U height visual */
    background: #0d1117;
    border-bottom: 1px solid #21262d;
    display: flex;
    align-items: center;
    position: relative;
}

.rack-slot:last-child {
    border-bottom: none;
}

.rack-slot__number {
    font-family: monospace;
    font-size: 10px;
    color: #484f58;
    position: absolute;
    left: -24px; /* Move numbers outside */
    width: 20px;
    text-align: right;
    opacity: 0.5;
}

.rack-slot--empty {
    /* Subtle u-marker lines */
    background-image: linear-gradient(90deg, #21262d 1px, transparent 1px);
    background-size: 10px 100%;
}

.rack-slot--drop-target {
    background: rgba(0, 255, 157, 0.15);
    box-shadow: inset 0 0 0 2px var(--color-primary);
    z-index: 10;
}

.rack-slot--drop-invalid {
    background: rgba(248, 81, 73, 0.15);
    box-shadow: inset 0 0 0 2px #f85149;
}

.rack-slot--server-start {
    z-index: 5;
    background: transparent;
    border: none;
    padding: 1px; /* Gap betweeen servers */
    overflow: visible;
}

/* --- PREMIUM SERVER DESIGN (BLING EDITION) --- */
.server-unit {
    display: flex;
    height: 100%;
    width: 100%;
    background: linear-gradient(180deg, #2d333b 0%, #1c2128 100%);
    border: 1px solid #30363d;
    border-radius: 2px;
    box-shadow: 
        0 1px 3px rgba(0,0,0,0.5), 
        inset 0 1px 0 rgba(255,255,255,0.05),
        inset 0 0 20px rgba(0,0,0,0.2); /* Inner depth */
    cursor: grab;
    position: relative;
    color: #c9d1d9;
    overflow: hidden; /* For shine effect */
}

/* Glass Reflection Overlay */
.server-unit::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 50%;
    background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, transparent 100%);
    pointer-events: none;
    z-index: 1;
}

.server-unit:hover {
    box-shadow: 
        0 4px 12px rgba(0,0,0,0.5), 
        inset 0 1px 0 rgba(255,255,255,0.1);
    transform: translateY(-1px);
    z-index: 10;
    border-color: #58a6ff;
}

.server-unit:active { cursor: grabbing; transform: translateY(0); }

/* Status Glows */
.server-unit--status-online {
    box-shadow: inset 0 0 10px rgba(46, 160, 67, 0.05);
}
.server-unit--status-provisioning {
    box-shadow: inset 0 0 10px rgba(210, 153, 34, 0.1);
}

/* Specific Type Accents & Bling */

/* VServer Node: Blue Tech Look */
.server-unit--vserver_node { 
    border-left: 3px solid #58a6ff; 
}
.server-unit--vserver_node .vent-grill {
    background: repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(88, 166, 255, 0.1) 2px, rgba(88, 166, 255, 0.1) 3px);
}

/* Dedicated: Green Industrial Look */
.server-unit--dedicated { 
    border-left: 3px solid #2ea043; 
}

/* Storage: Orange Heavy Look */
.server-unit--storage_server { 
    border-left: 3px solid #d29922; 
}

/* GPU Server: RGB GAMER MODE */
.server-unit--gpu_server {
    border-left: 3px solid transparent; /* Handled by RGB */
    position: relative;
    animation: rgb-border 4s linear infinite;
    background: linear-gradient(180deg, #1f1f1f 0%, #0f0f0f 100%);
}

.server-unit--gpu_server::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0; width: 3px;
    background: linear-gradient(180deg, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000);
    background-size: 100% 200%;
    animation: rgb-flow 3s linear infinite;
    z-index: 5;
}

.server-unit--gpu_server .vent-grill {
    /* Hexagon pattern */
    background-image: 
        linear-gradient(30deg, #222 12%, transparent 12.5%, transparent 87%, #222 87.5%, #222),
        linear-gradient(150deg, #222 12%, transparent 12.5%, transparent 87%, #222 87.5%, #222),
        linear-gradient(30deg, #222 12%, transparent 12.5%, transparent 87%, #222 87.5%, #222),
        linear-gradient(150deg, #222 12%, transparent 12.5%, transparent 87%, #222 87.5%, #222),
        linear-gradient(60deg, #333 25%, transparent 25.5%, transparent 75%, #333 75%, #333),
        linear-gradient(60deg, #333 25%, transparent 25.5%, transparent 75%, #333 75%, #333);
    background-size: 8px 14px;
    background-position: 0 0, 0 0, 4px 7px, 4px 7px, 0 0, 4px 7px;
    opacity: 0.5;
}

/* Animations */
@keyframes rgb-flow {
    0% { background-position: 0% 0%; }
    100% { background-position: 0% 200%; }
}

@keyframes rgb-border {
    0% { border-color: #ff0000; }
    33% { border-color: #00ff00; }
    66% { border-color: #0000ff; }
    100% { border-color: #ff0000; }
}

/* Rack Ears */
.server-ear {
    width: 14px;
    background: linear-gradient(90deg, #21262d, #30363d);
    border-right: 1px solid #161b22;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.screw {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, #b1bac4, #6e7681);
    box-shadow: 0 1px 2px rgba(0,0,0,0.8);
}

.server-face {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 0 8px;
    overflow: hidden;
    gap: 8px;
    z-index: 2; /* Above glass effect */
}

/* LEDs with enhanced glow */
.led {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #1c2128;
    box-shadow: inset 0 1px 1px rgba(0,0,0,0.8);
    transition: all 0.1s;
}

.led--green { background: #3fb950; box-shadow: 0 0 6px rgba(63, 185, 80, 0.8), inset 0 0 2px #fff; }
.led--blue { background: #58a6ff; box-shadow: 0 0 6px rgba(88, 166, 255, 0.8), inset 0 0 2px #fff; }
.led--yellow { background: #d29922; box-shadow: 0 0 6px rgba(210, 153, 34, 0.8), inset 0 0 2px #fff; }
.led--red { background: #f85149; box-shadow: 0 0 6px rgba(248, 81, 73, 0.8), inset 0 0 2px #fff; }

.led--flicker { animation: flicker 0.1s infinite alternate; }

.power-btn.on { 
    color: #3fb950; 
    filter: drop-shadow(0 0 3px rgba(63, 185, 80, 0.6)); 
}

/* Drive Bays with Activity */
.drive-bay {
    width: 12px;
    height: 85%;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 1px;
    position: relative;
    box-shadow: inset 0 0 4px rgba(0,0,0,0.8);
}

.drive-led {
    width: 3px;
    height: 3px;
    background: #238636;
    position: absolute;
    bottom: 3px;
    right: 3px;
    opacity: 0.3;
}
.drive-led.blinking-random { 
    animation: drive-access 0.2s infinite; 
    background: #3fb950;
    box-shadow: 0 0 4px #3fb950;
}

@keyframes drive-access {
    0%, 100% { opacity: 0.2; }
    50% { opacity: 1; }
}

/* Brand Badge */
.server-brand {
    background: linear-gradient(180deg, #30363d 0%, #21262d 100%);
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 8px;
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
    border: 1px solid rgba(255,255,255,0.1);
    color: #e6edf3;
    text-transform: uppercase;
    box-shadow: 0 1px 2px rgba(0,0,0,0.5);
}

/* Ports */
.port-usb { width: 8px; height: 4px; background: #000; border: 1px solid #555; border-radius: 1px; }
.port-eth { 
    width: 8px; height: 6px; background: #000; border: 1px solid #555; 
    position: relative;
}
.port-eth::after {
    /* Link lights */
    content: ''; position: absolute; top: 0; right: 0; width: 2px; height: 2px;
    background: #3fb950;
    box-shadow: 0 0 2px #3fb950;
}

/* Footer */
.rack-footer {
    padding: 8px 12px;
    background: #161b22;
    border-top: 1px solid #2d333b;
}

.rack-stat {
    display: flex;
    align-items: center;
    gap: 8px;
}

.rack-stat__label {
    font-size: 10px;
    text-transform: uppercase;
    color: #8b949e;
}

.progress-bar {
    flex: 1;
    height: 4px;
    background: #0d1117;
    border-radius: 2px;
    overflow: hidden;
}

.progress-bar__fill {
    height: 100%;
    background: #2ea043;
    transition: width 0.3s ease;
}

.progress-bar--warning .progress-bar__fill { background: #d29922; }
.progress-bar--danger .progress-bar__fill { background: #f85149; }

.rack-stat__value {
    font-family: monospace;
    font-size: 11px;
    color: #c9d1d9;
}
</style>
