<template>
    <div class="game-world" ref="worldContainer">
        <canvas ref="gameCanvas" class="game-canvas"></canvas>
        <div class="atmosphere-layer" :style="atmosphereStyle"></div>

        <div v-if="selectedRoom" class="room-context">
            <header class="room-topbar glass-panel">
                <div class="topbar-scan"></div>
                <div class="room-info">
                    <div class="sys-id">NODE_CONTEXT_0{{ selectedRoom.id }}</div>
                    <h2 class="room-name">{{ (selectedRoom.name || 'Unknown Room').toUpperCase() }}</h2>
                </div>
                
                <div class="room-telemetry">
                    <div class="tele-group">
                        <div class="tele-item" :class="{ 'danger': selectedRoom.warnings?.powerOverload }">
                            <span class="t-label">POWER_FEED</span>
                            <span class="t-val">{{ formatPower(selectedRoom.power?.current) }}</span>
                        </div>
                        <div class="t-track"><div class="t-fill data-fill" :style="{ width: (selectedRoom.power?.percent || 0) + '%' }"></div></div>
                    </div>

                    <div class="tele-group">
                        <div class="tele-item" :class="{ 'danger': selectedRoom.warnings?.overheating }">
                            <span class="t-label">THERMAL_LOAD</span>
                            <span class="t-val">{{ Math.round(selectedRoom.cooling?.percent || 0) }}%</span>
                        </div>
                        <div class="t-track"><div class="t-fill data-fill" :style="{ width: (selectedRoom.cooling?.percent || 0) + '%', background: 'var(--color-danger)' }"></div></div>
                    </div>
                </div>

                <div class="room-actions">
                    <button class="btn-enterprise" @click="showCustomization = true">CONFIGURE_ZONE</button>
                </div>
            </header>

            <div class="rack-operations">
                <div class="aisle aisle-1" :style="{ gridTemplateColumns: `repeat(${rowLength}, 1fr)` }">
                    <div v-for="slotIdx in row1Slots" :key="'slot-' + slotIdx" class="slot-wrapper">
                        <RackComponent 
                            v-if="getRackAtSlot(slotIdx)"
                            :rack="getRackAtSlot(slotIdx)"
                            @select="gameStore.selectRack(getRackAtSlot(slotIdx).id)"
                            @selectServer="(id) => $emit('open-server-details', id)"
                            :isSelected="selectedRackId === getRackAtSlot(slotIdx).id"
                            :id="'rack-unit-' + getRackAtSlot(slotIdx).id"
                            v-tooltip="getRackTooltip(getRackAtSlot(slotIdx))"
                        />
                        <button v-else class="empty-slot" :id="slotIdx === 0 ? 'tutorial-first-slot' : null" @click="showRackPurchase = true">
                            <span class="plus">+</span>
                            <span class="label">ADD_UNIT</span>
                        </button>
                    </div>
                </div>

                <div class="aisle-meta">
                    <div class="aisle-line"></div>
                    <span class="aisle-id">{{ aisleLabel }}</span>
                    <div class="aisle-line"></div>
                </div>

                <div class="aisle aisle-2" :style="{ gridTemplateColumns: `repeat(${rowLength}, 1fr)` }">
                    <div v-for="slotIdx in row2Slots" :key="'slot-' + slotIdx" class="slot-wrapper">
                        <RackComponent 
                            v-if="getRackAtSlot(slotIdx)"
                            :rack="getRackAtSlot(slotIdx)"
                            @select="gameStore.selectRack(getRackAtSlot(slotIdx).id)"
                            @selectServer="(id) => $emit('open-server-details', id)"
                            :isSelected="selectedRackId === getRackAtSlot(slotIdx).id"
                            v-tooltip="getRackTooltip(getRackAtSlot(slotIdx))"
                        />
                        <button v-else class="empty-slot" @click="showRackPurchase = true">
                            <span class="plus">+</span>
                            <span class="label">ADD_UNIT</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>



        <div v-else class="empty-world-state">
            <div class="state-content">
                <div class="spinner"></div>
                <h3>ESTABLISHING_UPLINK</h3>
                <p>Waiting for node telemetry...</p>
                <div v-if="gameStore.rooms && Object.keys(gameStore.rooms).length === 0" class="no-rooms-hint">
                    <p>No infrastructure detected.</p>
                </div>
            </div>
        </div>

        <!-- MODAL_SYSTEM -->
        <Transition name="fade">
            <div v-if="showRackPurchase" class="industrial-overlay" @click.self="showRackPurchase = false">
                <div class="industrial-modal" id="rack-purchase-modal">
                    <header class="modal-h">
                        <span class="h-title">PROVISION_RACK_UNIT</span>
                        <button @click="showRackPurchase = false" class="close">×</button>
                    </header>
                    <div class="rack-options">
                        <div 
                            v-for="(specs, type) in rackTypes" 
                            :key="type"
                            class="rack-card"
                            :id="'buy-rack-' + type"
                            :class="{ 'disabled': !canAfford(specs.cost) || (player?.economy?.level || 1) < specs.level }"
                            @click="purchaseRack(type)"
                        >
                            <div class="card-top">
                                <span class="card-name">{{ specs.name.toUpperCase() }}</span>
                                <span class="card-u">{{ specs.units }}U</span>
                            </div>
                            <div class="card-bottom">
                                <span class="card-price">${{ specs.cost }}</span>
                                <span v-if="(player?.economy?.level || 1) < specs.level" class="card-lock">LEVEL_{{ specs.level }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <EventOverlay />
        <RoomCustomizationOverlay v-if="showCustomization" :room="selectedRoom" @close="showCustomization = false" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';
import RackComponent from '../Rack/RackComponent.vue';
import OrderOverlay from '../Overlay/OrderOverlay.vue';
import ContractNegotiationOverlay from '../Overlay/ContractNegotiationOverlay.vue';
import EventOverlay from '../Overlay/EventOverlay.vue';
import ResearchOverlay from '../Overlay/ResearchOverlay.vue';
import RoomCustomizationOverlay from '../Overlay/RoomCustomizationOverlay.vue';
import { WALLPAPERS } from '../../constants/wallpapers';
import { useToastStore } from '../../stores/toast';

const emit = defineEmits(['open-server-details']);
const gameStore = useGameStore();

// Replace storeToRefs with direct computed properties to avoid potential reactivity issues
const selectedRoom = computed(() => gameStore.selectedRoom);
const selectedRackId = computed(() => gameStore.selectedRackId);
const player = computed(() => gameStore.player);
const selectedOrder = computed(() => gameStore.selectedOrder);

// Debug log to verify state
console.log('[GameWorld] Initializing...', { 
    hasRoom: !!selectedRoom.value, 
    hasPlayer: !!player.value,
});

const worldContainer = ref(null);
const gameCanvas = ref(null);
const showRackPurchase = ref(false);
const showResearchOverlay = ref(false);
const showNegotiation = ref(false);
const showCustomization = ref(false);
const negotiatingOrder = ref(null);


// Rack type configurations
const rackTypes = {
    rack_12u: { name: '12U Rack', units: 12, cost: 500, level: 1 },
    rack_24u: { name: '24U Rack', units: 24, cost: 1200, level: 3 },
    rack_42u: { name: '42U Rack', units: 42, cost: 2500, level: 8 },
};

// Calculate empty rack slots available
const emptyRackSlots = computed(() => {
    if (!selectedRoom.value) return [];
    const usedSlots = selectedRoom.value.racks?.length || 0;
    const maxSlots = selectedRoom.value.maxRacks;
    const empty = [];
    for (let i = usedSlots; i < maxSlots; i++) {
        empty.push(i);
    }
    return empty;
});

const hasRoomWarnings = computed(() => {
    if (!selectedRoom.value?.warnings) return false;
    const w = selectedRoom.value.warnings;
    return w.powerOverload || w.overheating || w.bandwidthSaturated;
});

const rowLength = computed(() => {
    const max = selectedRoom.value?.maxRacks || 10;
    return Math.ceil(max / 2);
});

const row1Slots = computed(() => {
    const len = rowLength.value;
    return Array.from({ length: len }, (_, i) => i);
});

const row2Slots = computed(() => {
    const len = rowLength.value;
    const max = selectedRoom.value?.maxRacks || (len * 2);
    return Array.from({ length: Math.max(0, max - len) }, (_, i) => i + len);
});

function getRackAtSlot(slotIdx) {
    if (!selectedRoom.value?.racks) return null;
    return selectedRoom.value.racks.find(r => r.position?.slot === slotIdx);
}

const aisleLabel = computed(() => {
    const airflow = selectedRoom.value?.cooling?.airflow;
    if (airflow === 'hot_aisle') return 'HOT AISLE';
    if (airflow === 'cold_aisle_containment') return 'CRYO CONTAINMENT';
    return 'SERVICE AISLE';
});

const aisleClass = computed(() => {
    const airflow = selectedRoom.value?.cooling?.airflow;
    return {
        'aisle-divider--hot': airflow === 'hot_aisle',
        'aisle-divider--cold': airflow === 'cold_aisle_containment',
    };
});

const aisleIcon = computed(() => {
    const airflow = selectedRoom.value?.cooling?.airflow;
    if (airflow === 'hot_aisle') return '🔥';
    if (airflow === 'cold_aisle_containment') return '❄️';
    return '🛠';
});

function canAfford(cost) {
    return (player.value?.economy?.balance || 0) >= cost;
}

async function purchaseRack(type) {
    if (!selectedRoom.value) return;
    
    const result = await gameStore.purchaseRack(selectedRoom.value.id, type);
    if (result.success) {
        showRackPurchase.value = false;
    }
}

// Tooltip Helper
const getRackTooltip = (rack) => {
    if (!rack) return null;
    const powerVal = rack.power?.current || 0;
    const powerMax = rack.power?.max || 10;
    const powerPct = Math.round((powerVal / powerMax) * 100);
    const heat = Math.round(rack.temperature || 20);
    const uUsed = rack.servers?.reduce((acc, s) => acc + (s.size_u || 1), 0) || 0;
    
    return {
        title: `RACK ${rack.name || rack.id.substring(0,6)}`,
        content: `Space: ${uUsed}/${rack.capacity}U Used\nPower: ${powerVal.toFixed(1)}kW / ${powerMax.toFixed(1)}kW (${powerPct}%)\nTemp: ${heat}°C`,
        hint: heat > 45 ? '⚠️ High Temperature Warning' : 'Click to inspect rack'
    };
};

const formatPower = (val) => {
    return (val || 0).toFixed(1) + ' kW';
};

// Canvas setup and rendering
const particles = ref([]);
const particleCount = 40;

function initParticles() {
    particles.value = [];
    if (!gameCanvas.value) return;
    for (let i = 0; i < particleCount; i++) {
        particles.value.push({
            x: Math.random() * gameCanvas.value.width,
            y: Math.random() * gameCanvas.value.height,
            size: Math.random() * 2 + 1,
            speedX: (Math.random() - 0.5) * 0.5,
            speedY: (Math.random() - 0.5) * 0.2,
            opacity: Math.random() * 0.5 + 0.2
        });
    }
}

let ctx = null;
let animationFrame = null;

onMounted(() => {
    if (gameCanvas.value) {
        ctx = gameCanvas.value.getContext('2d');
        resizeCanvas();
        initParticles();
        window.addEventListener('resize', () => {
            resizeCanvas();
            initParticles();
        });
        render();
    }
    
    // Start polling game state
    gameStore.startPolling(5000);
});

onUnmounted(() => {
    gameStore.stopPolling();
    window.removeEventListener('resize', resizeCanvas);
    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
    }
});

function resizeCanvas() {
    if (!gameCanvas.value || !worldContainer.value) return;
    
    gameCanvas.value.width = worldContainer.value.clientWidth;
    gameCanvas.value.height = worldContainer.value.clientHeight;
}

function render() {
    if (!ctx || !gameCanvas.value) return;
    
    const width = gameCanvas.value.width;
    const height = gameCanvas.value.height;

    // Clear canvas
    const wallpaperId = selectedRoom.value?.wallpaper || 'default';
    const wp = WALLPAPERS[wallpaperId] || WALLPAPERS.default;
    
    ctx.fillStyle = wp.bgColor;
    ctx.fillRect(0, 0, width, height);

    // Draw isometric grid background
    drawIsometricGrid();

    // Draw Particles
    drawParticles();

    // Continue animation loop
    animationFrame = requestAnimationFrame(render);
}

function drawIsometricGrid() {
    if (!ctx) return;
    
    const width = gameCanvas.value.width;
    const height = gameCanvas.value.height;
    
    const wallpaperId = selectedRoom.value?.wallpaper || 'default';
    const wp = WALLPAPERS[wallpaperId] || WALLPAPERS.default;
    
    ctx.strokeStyle = wp.gridColor;
    ctx.lineWidth = 1;

    const gridSize = 40;
    
    // Draw diamond grid pattern
    for (let x = 0; x <= width + gridSize * 2; x += gridSize) {
        for (let y = 0; y <= height + gridSize * 2; y += gridSize) {
            ctx.beginPath();
            const offsetX = (y / gridSize % 2) * (gridSize / 2);
            ctx.moveTo(x + offsetX, y);
            ctx.lineTo(x + offsetX + gridSize / 2, y + gridSize / 2);
            ctx.lineTo(x + offsetX, y + gridSize);
            ctx.lineTo(x + offsetX - gridSize / 2, y + gridSize / 2);
            ctx.closePath();
            ctx.stroke();
        }
    }
}

function handleCanvasClick(event) {
    // Handle canvas click for room-level interactions
}

function handleMouseMove(event) {
    // Handle mouse move for hover effects
}

function drawParticles() {
    if (!ctx) return;
    
    const wallpaperId = selectedRoom.value?.wallpaper || 'default';
    const wp = WALLPAPERS[wallpaperId] || WALLPAPERS.default;
    
    ctx.fillStyle = wp.accentColor;

    particles.value.forEach(p => {
        ctx.globalAlpha = p.opacity;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fill();

        // Move
        p.x += p.speedX;
        p.y += p.speedY;

        // Wrap
        if (p.x < 0) p.x = gameCanvas.value.width;
        if (p.x > gameCanvas.value.width) p.x = 0;
        if (p.y < 0) p.y = gameCanvas.value.height;
        if (p.y > gameCanvas.value.height) p.y = 0;
    });
    
    ctx.globalAlpha = 1.0;
}

const atmosphereStyle = computed(() => {
    const wallpaperId = selectedRoom.value?.wallpaper || 'default';
    const wp = WALLPAPERS[wallpaperId] || WALLPAPERS.default;
    
    const warnings = hasRoomWarnings.value;
    const baseColor = warnings ? '#ef4444' : (wp.accentColor || '#2F6BFF');
    
    return {
        boxShadow: `inset 0 0 100px ${baseColor}22`,
        pointerEvents: 'none',
        position: 'absolute',
        inset: 0,
        zIndex: 5,
        opacity: warnings ? 0.6 : 1,
        transition: 'all 0.5s ease'
    };
});

const dayNightStyle = computed(() => {
    // Basic day night overlay (Simplified to avoid crashes if gameTime is missing)
    const hour = player.value?.economy?.gameTime?.hour || 12;
    let color = 'transparent';
    let opacity = 0;

    if (hour >= 22 || hour < 4) {
        color = '#00051a';
        opacity = 0.4;
    } else if (hour >= 18 || hour < 22) {
        color = '#ff6b35';
        opacity = 0.15;
    }

    return {
        backgroundColor: color,
        opacity: opacity,
        position: 'absolute',
        inset: 0,
        pointerEvents: 'none',
        zIndex: 4,
        transition: 'all 2s ease'
    };
});
</script>

<style scoped>
.game-world {
    flex: 1;
    width: 100%;
    height: 100%;
    min-height: 0;
    position: relative;
    overflow: hidden;
    background: var(--color-bg-deep);
}

.game-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
}

.room-context {
    position: absolute;
    top: 60px; /* Increased from 20px */
    left: 24px;
    right: 24px;
    bottom: 24px;
    pointer-events: none;
    z-index: 10;
    display: flex;
    flex-direction: column;
}

.room-topbar {
    pointer-events: auto;
    padding: 24px 32px; /* Increased padding */
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    border-radius: var(--v3-radius);
    margin-bottom: 40px; /* Force racks further down */
    background: rgba(0,0,0,0.4);
    border: var(--v3-border-heavy);
}

.topbar-scan { position: absolute; left: 0; top: 0; width: 4px; height: 100%; background: var(--v3-accent); box-shadow: 0 0 15px var(--v3-accent); animation: scan-h 6s infinite ease-in-out; }
@keyframes scan-h { 0%, 100% { left: 0%; } 50% { left: 100%; } }

.sys-id { font-size: 0.5rem; font-weight: 900; color: var(--v3-text-ghost); letter-spacing: 0.1em; margin-bottom: 2px; }
.room-name { font-size: 1.4rem; font-weight: 900; color: #fff; letter-spacing: 0.05em; }

.room-telemetry { display: flex; gap: 60px; flex: 1; justify-content: center; }
.tele-group { display: flex; flex-direction: column; gap: 8px; width: 180px; }
.tele-item { display: flex; justify-content: space-between; align-items: baseline; }
.t-label { font-size: 0.55rem; font-weight: 900; color: var(--v3-text-ghost); letter-spacing: 0.15em; }
.t-val { font-size: 0.9rem; font-family: var(--font-family-mono); font-weight: 800; color: #fff; }

.t-track { height: 3px; background: rgba(255,255,255,0.05); position: relative; overflow: hidden; }
.t-fill { height: 100%; background: var(--v3-accent); transition: width 0.5s var(--v3-easing); }

.rack-operations {
    pointer-events: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start; /* Changed from center to allow top-down flow */
    padding: 20px 0;
}
.tele-item.danger .t-val { color: var(--color-danger); text-shadow: 0 0 10px rgba(255,0,0,0.3); }

.ghost-btn {
    padding: 6px 12px;
    background: transparent;
    border: var(--border-dim);
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--color-muted);
    border-radius: 2px;
}
.ghost-btn:hover { border-color: #fff; color: #fff; }

.rack-operations {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 48px; /* High gap between aisles */
    padding: 24px;
    overflow-y: auto;
}

.aisle {
    display: grid;
    gap: 32px;
    padding: 32px;
    background: rgba(255,255,255,0.01);
    border: var(--v3-border-soft);
    border-radius: 4px;
}

.aisle-meta {
    display: flex;
    align-items: center;
    gap: 24px;
    opacity: 0.3;
}

.aisle-line { flex: 1; height: 1px; background: var(--v3-border-soft); }
.aisle-id { font-size: 0.55rem; font-weight: 800; font-family: var(--font-family-mono); letter-spacing: 0.4em; color: var(--v3-text-ghost); }

.slot-wrapper { min-width: 220px; }

.empty-slot {
    width: 100%;
    height: 300px;
    border: 1px dashed rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.01);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.2s;
    border-radius: 2px;
}

.empty-slot:hover { border-color: var(--color-accent); background: rgba(58, 134, 255, 0.05); }
.empty-slot .plus { font-size: 1.5rem; color: var(--color-muted); opacity: 0.3; }
.empty-slot .label { font-size: 0.55rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.1em; }

/* MODAL STYLES */
.industrial-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(10,13,20,0.8);
    backdrop-filter: blur(10px);
    z-index: 5005;
    display: flex;
    align-items: center;
    justify-content: center;
}

.industrial-modal {
    width: 500px;
    background: var(--color-elevated);
    border: var(--border-ui);
    box-shadow: 0 30px 60px rgba(0,0,0,0.5);
}

.modal-h {
    padding: var(--space-lg) var(--space-xl);
    border-bottom: var(--border-dim);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.h-title { font-size: 0.65rem; font-weight: 800; color: var(--color-muted); letter-spacing: 0.1em; }
.modal-h .close { font-size: 1.5rem; color: var(--color-muted); }

.rack-options {
    padding: var(--space-xl);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rack-card {
    padding: var(--space-lg);
    border: var(--border-dim);
    background: rgba(255,255,255,0.01);
    cursor: pointer;
    transition: all 0.2s;
}

.rack-card:hover:not(.disabled) { border-color: var(--color-accent); background: rgba(58, 134, 255, 0.05); }

.card-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
.card-name { font-size: 0.8rem; font-weight: 800; color: #fff; }
.card-u { font-size: 0.6rem; font-family: var(--font-mono); color: var(--color-accent); }

.card-bottom { display: flex; justify-content: space-between; align-items: center; }
.card-price { font-size: 0.9rem; font-family: var(--font-mono); color: var(--color-success); font-weight: 700; }
.card-lock { font-size: 0.55rem; color: var(--color-warning); font-weight: 800; }

.disabled { opacity: 0.4; cursor: not-allowed; }


.empty-world-state {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 5;
}

.state-content {
    background: rgba(0,0,0,0.6);
    padding: 40px;
    border: 1px solid var(--v3-accent);
    text-align: center;
    backdrop-filter: blur(5px);
    border-radius: 4px;
}

.state-content h3 {
    margin: 16px 0 8px;
    font-size: 1rem;
    letter-spacing: 0.2em;
    color: var(--v3-accent);
    font-weight: 900;
}

.state-content p {
    font-size: 0.7rem;
    color: var(--v3-text-secondary);
}

.spinner {
    width: 40px; height: 40px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: var(--v3-accent);
    border-radius: 50%;
    margin: 0 auto;
    animation: spin 1s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

</style>

