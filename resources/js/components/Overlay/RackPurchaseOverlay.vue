<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="rack-purchase-modal glass-panel" id="rack-purchase-modal">
            <div class="modal-header">
                <h2>PROVISION RACK ENCLOSURE</h2>
                <button class="btn-close" @click="$emit('close')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="rack-options">
                    <button 
                        v-for="(specs, type) in rackTypes" 
                        :key="type"
                        class="rack-option-card"
                        :class="{ 'invalid': !canAfford(specs.cost) || player.economy.level < specs.level }"
                        @click="handlePurchase(type)"
                        :disabled="!canAfford(specs.cost) || player.economy.level < specs.level || processing"
                    >
                        <div class="ro-main">
                            <h4>{{ specs.name }}</h4>
                            <span class="ro-spec">{{ specs.units }}U CAPACITY</span>
                        </div>
                        <div class="ro-meta">
                            <div class="ro-cost">${{ specs.cost.toLocaleString() }}</div>
                            <div v-if="player.economy.level < specs.level" class="ro-req">REQ: LVL {{ specs.level }}</div>
                            <div v-else-if="!canAfford(specs.cost)" class="ro-req">INSUFFICIENT FUNDS</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useInfrastructureStore } from '../../stores/infrastructure';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();

const emit = defineEmits(['close']);

const processing = ref(false);

const player = computed(() => gameStore.player);

const rackTypes = {
    rack_12u: { name: '12U MICRO-RACK', units: 12, cost: 500, level: 1 },
    rack_24u: { name: '24U HALF-RACK', units: 24, cost: 1200, level: 3 },
    rack_42u: { name: '42U ENTERPRISE RACK', units: 42, cost: 2500, level: 8 },
};

function canAfford(cost) {
    return (player.value?.economy?.balance || 0) >= cost;
}

async function handlePurchase(type) {
    if (processing.value || !gameStore.selectedRoom) return;
    processing.value = true;
    try {
        const result = await infraStore.purchaseRack(gameStore.selectedRoom.id, type);
        if (result?.success || result) {
            emit('close');
        }
    } finally {
        processing.value = false;
    }
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(10, 15, 20, 0.85);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
}

.rack-purchase-modal {
    width: 500px;
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-lg);
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--ds-border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 0.85rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: 0.1em;
    color: var(--ds-text-primary);
}

.btn-close {
    background: transparent;
    border: none;
    color: var(--ds-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}

.btn-close:hover {
    color: #fff;
}

.modal-body {
    padding: 24px;
}

.rack-options {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.rack-option-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--ds-bg-subtle);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-md);
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.rack-option-card:hover:not(:disabled) {
    background: var(--ds-accent-soft);
    border-color: var(--ds-accent);
}

.rack-option-card.invalid {
    opacity: 0.5;
    cursor: not-allowed;
    filter: grayscale(1);
}

.ro-main h4 {
    margin: 0 0 4px 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--ds-text-primary);
}

.ro-spec {
    font-size: 0.75rem;
    color: var(--ds-text-secondary);
    font-family: var(--font-family-mono);
}

.ro-meta {
    text-align: right;
}

.ro-cost {
    font-size: 1.1rem;
    font-weight: 900;
    color: var(--ds-success);
    font-family: var(--font-family-mono);
}

.ro-req {
    font-size: 0.65rem;
    color: var(--ds-warning);
    font-weight: 700;
    margin-top: 4px;
}
</style>
