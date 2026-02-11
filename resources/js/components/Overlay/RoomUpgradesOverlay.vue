<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="upgrades-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">🏗️</span>
                    <h2>Infrastructure Upgrades</h2>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <div v-if="!selectedRoom" class="no-room-selected">
                    <p>Please select a room to view available upgrades.</p>
                </div>

                <div v-else class="room-upgrades-container">
                    <div class="room-summary">
                        <h3>{{ selectedRoom.name }}</h3>
                        <p class="room-type">{{ selectedRoom.type.toUpperCase() }} ENVIRONMENT</p>
                    </div>

                    <div class="upgrades-grid">
                        <!-- Power Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('power') >= 5 }">
                            <div class="upgrade-icon">⚡</div>
                            <div class="upgrade-info">
                                <h4>Power Grid</h4>
                                <div class="upgrade-level">Level {{ getLevel('power') }}/5</div>
                                <div class="upgrade-stats">
                                    <span class="stat-current">{{ selectedRoom.specs.maxPowerKw.toFixed(1) }} kW</span>
                                    <span class="stat-arrow">→</span>
                                    <span class="stat-next">{{ (selectedRoom.specs.maxPowerKw * 1.25).toFixed(1) }} kW</span>
                                </div>
                            </div>
                            <button 
                                class="btn-upgrade" 
                                :disabled="getLevel('power') >= 5 || !canAfford(getCost('power')) || processing"
                                @click="handleUpgrade('power')"
                            >
                                {{ getLevel('power') >= 5 ? 'MAX' : '$' + formatMoney(getCost('power')) }}
                            </button>
                        </div>

                        <!-- Cooling Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('cooling') >= 5 }">
                            <div class="upgrade-icon">❄️</div>
                            <div class="upgrade-info">
                                <h4>Cooling Plant</h4>
                                <div class="upgrade-level">Level {{ getLevel('cooling') }}/5</div>
                                <div class="upgrade-stats">
                                    <span class="stat-current">{{ selectedRoom.specs.maxCoolingKw.toFixed(1) }} kW</span>
                                    <span class="stat-arrow">→</span>
                                    <span class="stat-next">{{ (selectedRoom.specs.maxCoolingKw * 1.25).toFixed(1) }} kW</span>
                                </div>
                            </div>
                            <button 
                                class="btn-upgrade" 
                                :disabled="getLevel('cooling') >= 5 || !canAfford(getCost('cooling')) || processing"
                                @click="handleUpgrade('cooling')"
                            >
                                {{ getLevel('cooling') >= 5 ? 'MAX' : '$' + formatMoney(getCost('cooling')) }}
                            </button>
                        </div>

                        <!-- Bandwidth Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('bandwidth') >= 5 }">
                            <div class="upgrade-icon">🌐</div>
                            <div class="upgrade-info">
                                <h4>Network Pipe</h4>
                                <div class="upgrade-level">Level {{ getLevel('bandwidth') }}/5</div>
                                <div class="upgrade-stats">
                                    <span class="stat-current">{{ selectedRoom.specs.bandwidthGbps }} Gbps</span>
                                    <span class="stat-arrow">→</span>
                                    <span class="stat-next">{{ selectedRoom.specs.bandwidthGbps * 2 }} Gbps</span>
                                </div>
                            </div>
                            <button 
                                class="btn-upgrade" 
                                :disabled="getLevel('bandwidth') >= 5 || !canAfford(getCost('bandwidth')) || processing"
                                @click="handleUpgrade('bandwidth')"
                            >
                                {{ getLevel('bandwidth') >= 5 ? 'MAX' : '$' + formatMoney(getCost('bandwidth')) }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="overlay-footer">
                <p class="footer-hint">Upgrading your infrastructure allows for more high-density racks and powerful servers.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';

const gameStore = useGameStore();
const { selectedRoom, player } = storeToRefs(gameStore);

const processing = ref(false);

const getLevel = (type) => {
    if (!selectedRoom.value || !selectedRoom.value.upgrades) return 0;
    return selectedRoom.value.upgrades[type] || 0;
};

const getCost = (type) => {
    if (!selectedRoom.value) return 0;
    
    // Logic matching the backend
    const baseRoomCost = selectedRoom.value.purchaseCost || 5000;
    const baseCost = Math.max(500, baseRoomCost * 0.2);
    const level = getLevel(type);
    
    return baseCost * Math.pow(1.8, level);
};

const canAfford = (cost) => {
    return player.value.economy.balance >= cost;
};

const formatMoney = (val) => {
    return Math.round(val).toLocaleString();
};

const handleUpgrade = async (type) => {
    if (processing.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, type);
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.upgrades-overlay {
    width: 600px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
}

.overlay-body {
    padding: var(--space-lg);
    min-height: 300px;
}

.room-summary {
    text-align: center;
    margin-bottom: var(--space-xl);
}

.room-summary h3 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--color-primary);
}

.room-type {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    letter-spacing: 2px;
    margin-top: 4px;
}

.upgrades-grid {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.upgrade-card {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-md);
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: all 0.2s;
}

.upgrade-card:hover:not(.at-max) {
    background: rgba(var(--color-primary-rgb), 0.05);
    border-color: var(--color-primary);
}

.upgrade-card.at-max {
    opacity: 0.7;
    background: rgba(0, 0, 0, 0.2);
}

.upgrade-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upgrade-info {
    flex: 1;
}

.upgrade-info h4 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
}

.upgrade-level {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-bottom: 8px;
}

.upgrade-stats {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-family-mono);
    font-size: 0.9rem;
}

.stat-current {
    color: var(--color-text-secondary);
}

.stat-arrow {
    color: var(--color-text-muted);
}

.stat-next {
    color: var(--color-success);
    font-weight: 700;
}

.btn-upgrade {
    padding: 10px 20px;
    background: var(--color-primary);
    color: #000;
    border: none;
    border-radius: 6px;
    font-weight: 800;
    cursor: pointer;
    min-width: 100px;
    transition: all 0.2s;
}

.btn-upgrade:hover:not(:disabled) {
    background: #fff;
    transform: scale(1.05);
}

.btn-upgrade:disabled {
    background: #444;
    color: #888;
    cursor: not-allowed;
}

.overlay-footer {
    padding: var(--space-md) var(--space-lg);
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid var(--color-border);
}

.footer-hint {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin: 0;
    text-align: center;
    font-style: italic;
}

.no-room-selected {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: var(--color-text-muted);
}

.animation-slide-up {
    animation: slide-up 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slide-up {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
