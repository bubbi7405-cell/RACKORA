<template>
    <div class="v2-game-world" ref="worldContainer">
        <canvas ref="gameCanvas" class="game-canvas"></canvas>

        <div v-if="selectedRoom" class="v2-world-context">
            <!-- Tactical Header -->
            <div class="v2-room-header">
                <div class="v2-breadcrumb l1-priority">
                    <span class="root">GLOBAL_ASSET_NETWORK</span>
                    <span class="sep">≫</span>
                    <span class="node">{{ (selectedRoom.name || 'NOD').toUpperCase() }}</span>
                </div>
                <div class="v2-room-meta">
                    <div class="meta-item">
                        <span class="label l3-priority">ASSET_GRID_MODE:</span>
                        <span class="value l2-priority">ASSET_VISUALIZATION</span>
                    </div>
                </div>
            </div>

            <!-- Operational Deck -->
            <div class="v2-operational-deck scroll-v2" ref="deckContainer">
                <div v-for="(aisle, aIndex) in aisles" :key="aIndex" class="v2-aisle"
                    :style="{ gridTemplateColumns: `repeat(${rowLength}, 1fr)` }">
                    <div v-for="(rack, index) in aisle" :key="rack?.id || `a${aIndex}-${index}`" class="v2-rack-slot">
                        <RackComponent v-if="rack" :rack="rack" @select="selectRack"
                            @selectServer="(id) => $emit('open-server-details', id)"
                            :is-selected="selectedRackId === rack.id" />
                        <div v-else class="v2-empty-slot"
                            @click="emit('openRackPurchase', { room: selectedRoom, index: aIndex * rowLength + index })">
                            <div class="slot-plus">+</div>
                            <div class="slot-label l3-priority">PROVISION_RACK</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="v2-empty-world-state">
            <div class="v2-state-content glass-v2">
                <div class="v2-spinner-industrial"></div>
                <h3 class="l1-priority">ACQUIRING_SITE_DATA</h3>
                <p class="l3-priority">Retrieving asset status...</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import RackComponent from '../Rack/RackComponent.vue';

const gameStore = useGameStore();
const emit = defineEmits(['openRackPurchase', 'openSandbox', 'open-server-details']);

const worldContainer = ref(null);
const deckContainer = ref(null);
const gameCanvas = ref(null);

const selectedRoom = computed(() => gameStore.selectedRoom);
const selectedRackId = computed(() => gameStore.selectedRackId);

// Racks positioning
const rowLength = 5;
const aisles = computed(() => {
    if (!selectedRoom.value) return [];

    const maxRacks = selectedRoom.value.maxRacks || 2;
    const racksMap = {};

    if (selectedRoom.value.racks) {
        Object.values(selectedRoom.value.racks).forEach(r => {
            const slot = r.position?.slot ?? r.position;
            racksMap[slot] = r;
        });
    }

    const allSlots = [];
    for (let i = 0; i < maxRacks; i++) {
        allSlots.push(racksMap[i] || null);
    }

    // Chunk into rows
    const chunks = [];
    for (let i = 0; i < allSlots.length; i += rowLength) {
        chunks.push(allSlots.slice(i, i + rowLength));
    }
    return chunks;
});

function selectRack(id) {
    gameStore.selectRack(id);
}

function onSelectServer(serverId) {
    emit('open-server-details', serverId);
}

// Canvas & Particle Logic
let ctx = null;
let animationId = null;
let particles = [];

const initCanvas = () => {
    if (!gameCanvas.value) return;
    ctx = gameCanvas.value.getContext('2d');
    resizeCanvas();
    createParticles();
    animate();
};

const resizeCanvas = () => {
    if (!worldContainer.value || !gameCanvas.value) return;
    const { width, height } = worldContainer.value.getBoundingClientRect();
    gameCanvas.value.width = width * window.devicePixelRatio;
    gameCanvas.value.height = height * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
};

const createParticles = () => {
    particles = [];
    const count = 40;
    for (let i = 0; i < count; i++) {
        particles.push({
            x: Math.random() * window.innerWidth,
            y: Math.random() * window.innerHeight,
            size: Math.random() * 2 + 1,
            speedX: Math.random() * 0.5 - 0.25,
            speedY: Math.random() * 0.5 - 0.25,
            opacity: Math.random() * 0.5
        });
    }
};

const animate = () => {
    if (!ctx || !gameCanvas.value) return;
    const width = gameCanvas.value.width / window.devicePixelRatio;
    const height = gameCanvas.value.height / window.devicePixelRatio;

    ctx.clearRect(0, 0, width, height);
    drawGrid(width, height);

    particles.forEach(p => {
        p.x += p.speedX;
        p.y += p.speedY;
        if (p.x < 0) p.x = width;
        if (p.x > width) p.x = 0;
        if (p.y < 0) p.y = height;
        if (p.y > height) p.y = 0;

        ctx.fillStyle = `rgba(88, 166, 255, ${p.opacity})`;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fill();
    });

    animationId = requestAnimationFrame(animate);
};

const drawGrid = (width, height) => {
    const step = 40;
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
    ctx.lineWidth = 1;

    for (let x = 0; x < width; x += step) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, height);
        ctx.stroke();
    }
    for (let y = 0; y < height; y += step) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(width, y);
        ctx.stroke();
    }
};

onMounted(() => {
    initCanvas();
    window.addEventListener('resize', resizeCanvas);
});

onUnmounted(() => {
    if (animationId) cancelAnimationFrame(animationId);
    window.removeEventListener('resize', resizeCanvas);
});
</script>

<style scoped>
.v2-game-world {
    flex: 1;
    position: relative;
    background: var(--ds-bg-void);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.game-canvas {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    opacity: 0.3;
}

.v2-world-context {
    position: relative;
    z-index: 10;
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 24px;
    gap: 20px;
}

.v2-room-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--ds-border-color);
}

.v2-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    font-weight: 600;
}

.v2-breadcrumb .root {
    color: var(--ds-text-ghost);
}

.v2-breadcrumb .sep {
    color: var(--ds-text-ghost);
    opacity: 0.5;
}

.v2-breadcrumb .node {
    color: var(--ds-text-primary);
    font-weight: 700;
}

.v2-room-meta {
    display: flex;
    gap: 20px;
}

.meta-item {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.meta-item .label {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--ds-text-ghost);
}

.meta-item .value {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-nominal);
}

.v2-operational-deck {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 32px;
    padding: 20px 0;
    overflow-y: auto;
}

.v2-aisle {
    display: grid;
    gap: 16px;
}

.v2-rack-slot {
    min-height: 380px;
    height: 100%;
    position: relative;
    transition: transform 0.2s ease;
}

.v2-rack-slot:hover {
    transform: translateY(-2px);
}

.v2-empty-slot {
    height: 100%;
    background: var(--ds-bg-elevated);
    border: 2px dashed var(--ds-border-color);
    border-radius: var(--ds-radius-lg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    gap: 8px;
}

.v2-empty-slot:hover {
    background: var(--ds-accent-soft);
    border-color: var(--ds-accent);
}

.slot-plus {
    font-size: 1.5rem;
    font-weight: 300;
    color: var(--ds-text-ghost);
}

.v2-empty-slot:hover .slot-plus {
    color: var(--ds-accent);
}

.slot-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-text-ghost);
}

.v2-empty-world-state {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.v2-state-content {
    padding: 48px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-xl);
    box-shadow: var(--ds-shadow-lg);
}

.v2-spinner-industrial {
    width: 40px;
    height: 40px;
    border: 3px solid var(--ds-bg-hover);
    border-top-color: var(--ds-accent);
    border-radius: 50%;
    animation: ds-spin 0.8s linear infinite;
}

@keyframes ds-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
