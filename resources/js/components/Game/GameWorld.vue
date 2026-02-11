<template>
    <div class="game-world" ref="worldContainer">
        <!-- Day/Night Cycle Overlay -->
        <div class="day-night-overlay" :style="dayNightStyle"></div>
        <!-- Canvas for isometric rendering -->
        <canvas 
            ref="gameCanvas" 
            class="game-canvas"
            @click="handleCanvasClick"
            @mousemove="handleMouseMove"
        ></canvas>

        <!-- Room view overlay with rack visualization -->
        <div v-if="selectedRoom" class="room-view">
            <div class="room-header">
                <h2 class="room-header__title">{{ selectedRoom.name }}</h2>
                <div class="room-header__stats">
                    <span class="room-stat" :class="{ 'room-stat--danger': selectedRoom.warnings?.powerOverload }">
                        <span class="room-stat__icon">⚡</span>
                        <span class="room-stat__value">{{ formatPower(selectedRoom.power?.current) }}</span>
                        <span class="room-stat__max">/ {{ formatPower(selectedRoom.power?.max) }}</span>
                    </span>
                    <span class="room-stat" :class="{ 'room-stat--danger': selectedRoom.warnings?.overheating }">
                        <span class="room-stat__icon">🌡</span>
                        <span class="room-stat__value">{{ Math.round(selectedRoom.cooling?.percent || 0) }}%</span>
                    </span>
                    <span class="room-stat" :class="{ 'room-stat--danger': selectedRoom.warnings?.bandwidthSaturated }">
                        <span class="room-stat__icon">🌐</span>
                        <span class="room-stat__value">{{ (selectedRoom.bandwidth?.current || 0).toFixed(1) }}</span>
                        <span class="room-stat__max">/ {{ (selectedRoom.bandwidth?.max || 0).toFixed(1) }} Gbps</span>
                    </span>
                </div>
                <!-- Warning Badges -->
                <div v-if="hasRoomWarnings" class="room-header__warnings">
                    <span v-if="selectedRoom.warnings?.powerOverload" class="warning-badge warning-badge--red">⚡ POWER OVERLOAD</span>
                    <span v-if="selectedRoom.warnings?.overheating" class="warning-badge warning-badge--orange">🌡 OVERHEATING</span>
                    <span v-if="selectedRoom.warnings?.bandwidthSaturated" class="warning-badge warning-badge--blue">🌐 BANDWIDTH SATURATED</span>
                </div>
            </div>

            <!-- Rack Grid -->
            <div class="rack-grid">
                <RackComponent 
                    v-for="rack in selectedRoom.racks" 
                    :key="rack.id"
                    :rack="rack"
                    @select="gameStore.selectRack(rack.id)"
                    :isSelected="selectedRackId === rack.id"
                />

                <!-- Empty rack slots -->
                <div 
                    v-for="slot in emptyRackSlots" 
                    :key="'empty-' + slot"
                    class="rack-slot-empty"
                    @click="showRackPurchase = true"
                >
                    <div class="rack-slot-empty__icon">+</div>
                    <span class="rack-slot-empty__text">Add Rack</span>
                </div>
            </div>
        </div>

        <!-- Rack Purchase Modal -->
        <div v-if="showRackPurchase" class="modal-overlay" @click.self="showRackPurchase = false">
            <div class="modal-card">
                <h3 class="modal-card__title">Purchase Rack</h3>
                <div class="rack-options">
                    <button 
                        v-for="(specs, type) in rackTypes" 
                        :key="type"
                        class="rack-option"
                        @click="purchaseRack(type)"
                        :disabled="!canAfford(specs.cost) || player.economy.level < specs.level"
                    >
                        <div class="rack-option__name">{{ specs.name }}</div>
                        <div class="rack-option__specs">{{ specs.units }}U capacity</div>
                        <div class="rack-option__cost">
                            ${{ specs.cost.toLocaleString() }}
                        </div>
                        <div v-if="player.economy.level < specs.level" class="rack-option__locked">
                            Level {{ specs.level }} required
                        </div>
                    </button>
                </div>
                <button class="btn btn--secondary" @click="showRackPurchase = false">Cancel</button>
            </div>
        </div>
        <!-- Order Overlay -->
        <OrderOverlay 
            v-if="selectedOrder" 
            :order="selectedOrder" 
            @close="gameStore.selectOrder(null)"
        />
        
        <EventOverlay />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';
import RackComponent from '../Rack/RackComponent.vue';
import OrderOverlay from '../Overlay/OrderOverlay.vue';
import EventOverlay from '../Overlay/EventOverlay.vue';
import ResearchOverlay from '../Overlay/ResearchOverlay.vue';
import { useToastStore } from '../../stores/toast';

const gameStore = useGameStore();
const { selectedRoom, selectedRackId, player, selectedOrder } = storeToRefs(gameStore);

const worldContainer = ref(null);
const gameCanvas = ref(null);
const showRackPurchase = ref(false);
const showResearchOverlay = ref(false);


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

function canAfford(cost) {
    return player.value.economy.balance >= cost;
}

async function purchaseRack(type) {
    if (!selectedRoom.value) return;
    
    const result = await gameStore.purchaseRack(selectedRoom.value.id, type);
    if (result.success) {
        showRackPurchase.value = false;
    }
}

function formatPower(kw) {
    if (kw === undefined || kw === null) return '0.0 kW';
    if (kw >= 1000) {
        return (kw / 1000).toFixed(1) + ' MW';
    }
    return kw.toFixed(1) + ' kW';
}

// Canvas setup and rendering
let ctx = null;
let animationFrame = null;

onMounted(() => {
    if (gameCanvas.value) {
        ctx = gameCanvas.value.getContext('2d');
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
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
    ctx.fillStyle = '#0a0d14';
    ctx.fillRect(0, 0, width, height);

    // Draw isometric grid background
    drawIsometricGrid();

    // Continue animation loop
    animationFrame = requestAnimationFrame(render);
}

function drawIsometricGrid() {
    if (!ctx) return;
    
    const width = gameCanvas.value.width;
    const height = gameCanvas.value.height;
    
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
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
const dayNightStyle = computed(() => {
    const hour = player.value?.economy?.gameTime?.hour || 12;
    let color = 'transparent';
    let opacity = 0;

    if (hour >= 22 || hour < 4) {
        // Night
        color = '#00051a';
        opacity = 0.4;
    } else if (hour >= 4 && hour < 6) {
        // Dawn
        color = '#ff8c00';
        opacity = 0.15;
    } else if (hour >= 6 && hour < 18) {
        // Day
        opacity = 0;
    } else if (hour >= 18 && hour < 20) {
        // Sunset
        color = '#ff4500';
        opacity = 0.15;
    } else if (hour >= 20 && hour < 22) {
        // Evening
        color = '#00051a';
        opacity = 0.25;
    }

    return {
        backgroundColor: color,
        opacity: opacity,
        transition: 'all 2s ease-in-out'
    };
});
</script>

<style scoped>
.game-world {
    grid-area: game-world;
    position: relative;
    overflow: hidden;
    background: var(--color-bg-deep);
}

.day-night-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 5;
    pointer-events: none;
    transition: all 2s ease-in-out;
}

.game-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.room-view {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    padding: var(--space-lg);
    display: flex;
    flex-direction: column;
    pointer-events: none;
}

.room-view > * {
    pointer-events: auto;
}

.room-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    background: rgba(15, 20, 25, 0.9);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-lg);
    backdrop-filter: blur(8px);
}

.room-header__title {
    font-size: var(--font-size-xl);
    font-weight: 600;
    margin: 0;
}

.room-header__stats {
    display: flex;
    gap: var(--space-lg);
}

.room-stat {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    font-family: var(--font-family-mono);
    font-size: var(--font-size-sm);
    transition: color 0.3s;
}

.room-stat--danger {
    color: var(--color-danger, #ff4444);
    text-shadow: 0 0 8px rgba(255, 68, 68, 0.5);
}

.room-stat__max {
    color: var(--color-text-muted);
}

.room-header__warnings {
    width: 100%;
    display: flex;
    gap: var(--space-sm);
    margin-top: var(--space-xs);
}

.warning-badge {
    font-size: var(--font-size-xs);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 10px;
    border-radius: var(--radius-sm);
    animation: warningPulse 1.5s ease-in-out infinite;
}

.warning-badge--red {
    background: rgba(255, 50, 50, 0.2);
    border: 1px solid rgba(255, 50, 50, 0.5);
    color: #ff5555;
}

.warning-badge--orange {
    background: rgba(255, 160, 50, 0.2);
    border: 1px solid rgba(255, 160, 50, 0.5);
    color: #ffa032;
}

.warning-badge--blue {
    background: rgba(50, 140, 255, 0.2);
    border: 1px solid rgba(50, 140, 255, 0.5);
    color: #328cff;
}

@keyframes warningPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.rack-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--space-lg);
    align-content: start;
    overflow-y: auto;
    padding: var(--space-sm);
}

.rack-slot-empty {
    min-height: 300px;
    border: 2px dashed var(--color-border);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.rack-slot-empty:hover {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.rack-slot-empty__icon {
    font-size: 2rem;
    color: var(--color-text-muted);
    margin-bottom: var(--space-sm);
}

.rack-slot-empty__text {
    color: var(--color-text-muted);
    font-size: var(--font-size-sm);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(10, 13, 20, 0.8);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 500;
}

.modal-card {
    background: var(--color-bg-dark);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: var(--space-xl);
    max-width: 500px;
    width: 90%;
}

.modal-card__title {
    font-size: var(--font-size-xl);
    margin-bottom: var(--space-lg);
    text-align: center;
}

.rack-options {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
}

.rack-option {
    display: flex;
    flex-direction: column;
    padding: var(--space-md);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    text-align: left;
    transition: all var(--transition-fast);
}

.rack-option:hover:not(:disabled) {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.rack-option:disabled {
    opacity: 0.5;
}

.rack-option__name {
    font-weight: 600;
    margin-bottom: var(--space-xs);
}

.rack-option__specs {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
}

.rack-option__cost {
    font-family: var(--font-family-mono);
    color: var(--color-success);
    margin-top: var(--space-sm);
}

.rack-option__locked {
    font-size: var(--font-size-xs);
    color: var(--color-warning);
    margin-top: var(--space-xs);
}
</style>
