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

                        <!-- Maintenance Section -->
                        <div class="maintenance-panel" v-if="selectedRoom.cooling">
                            <h4>Maintenance & Efficiency</h4>
                            <div class="maintenance-row">
                                <div class="health-indicator">
                                    <label>Cooling Health</label>
                                    <div class="progress-bar">
                                        <div class="progress-fill"
                                            :style="{ width: selectedRoom.cooling.health + '%', background: getHealthColor(selectedRoom.cooling.health) }">
                                        </div>
                                    </div>
                                    <span class="health-val">{{ selectedRoom.cooling.health.toFixed(1) }}%</span>
                                </div>
                                <button class="btn-repair" @click="handleRepair"
                                    :disabled="selectedRoom.cooling.health >= 100 || processing">
                                    Repair (${{ getRepairCost() }})
                                </button>
                            </div>
                        </div>

                        <!-- Airflow Optimization -->
                        <div class="airflow-panel">
                            <h4>Airflow Configuration</h4>
                            <div class="airflow-options">
                                <button class="airflow-card"
                                    :class="{ active: selectedRoom.cooling.airflow === 'standard' }"
                                    @click="handleAirflow('standard')" :disabled="true">
                                    <span class="af-icon">💨</span>
                                    <span class="af-label">Standard</span>
                                </button>
                                <button class="airflow-card"
                                    :class="{ active: selectedRoom.cooling.airflow === 'hot_aisle' }"
                                    @click="handleAirflow('hot_aisle')"
                                    :disabled="selectedRoom.cooling.airflow === 'hot_aisle' || selectedRoom.cooling.airflow === 'cold_aisle_containment'">
                                    <span class="af-icon">🔥</span>
                                    <div class="af-info">
                                        <span class="af-label">Hot Aisle</span>
                                        <span class="af-bonus">+25% Eff.</span>
                                        <span class="af-cost"
                                            v-if="selectedRoom.cooling.airflow !== 'hot_aisle'">$5,000</span>
                                    </div>
                                </button>
                                <button class="airflow-card"
                                    :class="{ active: selectedRoom.cooling.airflow === 'cold_aisle_containment' }"
                                    @click="handleAirflow('cold_aisle_containment')"
                                    :disabled="selectedRoom.cooling.airflow === 'cold_aisle_containment'">
                                    <span class="af-icon">❄️</span>
                                    <div class="af-info">
                                        <span class="af-label">Cold Aisle</span>
                                        <span class="af-bonus">+50% Eff.</span>
                                        <span class="af-cost"
                                            v-if="selectedRoom.cooling.airflow !== 'cold_aisle_containment'">$15,000</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Power Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('power') >= 5 }">
                            <div class="upgrade-icon">⚡</div>
                            <div class="upgrade-info">
                                <h4>Power Grid</h4>
                                <div class="upgrade-level">Level {{ getLevel('power') }}/5</div>
                                <div class="upgrade-stats" v-if="selectedRoom.power">
                                    <span class="stat-current">{{ (selectedRoom.power.max || 0).toFixed(1) }} kW</span>
                                    <span class="stat-arrow">→</span>
                                    <span class="stat-next">{{ ((selectedRoom.power.max || 0) * 1.25).toFixed(1) }}
                                        kW</span>
                                </div>
                            </div>
                            <button class="btn-upgrade"
                                :disabled="getLevel('power') >= 5 || !canAfford(getCost('power')) || processing"
                                @click="handleUpgrade('power')">
                                {{ getLevel('power') >= 5 ? 'MAX' : '$' + formatMoney(getCost('power')) }}
                            </button>
                        </div>

                        <!-- Cooling Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('cooling') >= 5 }">
                            <div class="upgrade-icon">❄️</div>
                            <div class="upgrade-info">
                                <h4>Cooling Plant</h4>
                                <div class="upgrade-level">Level {{ getLevel('cooling') }}/5</div>
                                <div class="upgrade-stats" v-if="selectedRoom.cooling">
                                    <div class="stat-row">
                                        <span class="label">Max:</span>
                                        <span class="stat-current">{{ (selectedRoom.cooling.max || 0).toFixed(1) }}
                                            kW</span>
                                        <span class="stat-arrow">→</span>
                                        <span class="stat-next">{{ ((selectedRoom.cooling.max || 0) * 1.25).toFixed(1)
                                        }} kW</span>
                                    </div>
                                    <div class="stat-row highlight">
                                        <span class="label">Effective:</span>
                                        <span class="stat-val">{{ (selectedRoom.cooling.effective || 0).toFixed(1) }}
                                            kW</span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-upgrade"
                                :disabled="getLevel('cooling') >= 5 || !canAfford(getCost('cooling')) || processing"
                                @click="handleUpgrade('cooling')">
                                {{ getLevel('cooling') >= 5 ? 'MAX' : '$' + formatMoney(getCost('cooling')) }}
                            </button>
                        </div>

                        <!-- Bandwidth Upgrade -->
                        <div class="upgrade-card" :class="{ 'at-max': getLevel('bandwidth') >= 5 }">
                            <div class="upgrade-icon">🌐</div>
                            <div class="upgrade-info">
                                <h4>Network Pipe</h4>
                                <div class="upgrade-level">Level {{ getLevel('bandwidth') }}/5</div>
                                <div class="upgrade-stats" v-if="selectedRoom.bandwidth">
                                    <span class="stat-current">{{ (selectedRoom.bandwidth.max || 0) }} Gbps</span>
                                    <span class="stat-arrow">→</span>
                                    <span class="stat-next">{{ (selectedRoom.bandwidth.max || 0) * 2 }} Gbps</span>
                                </div>
                            </div>
                            <button class="btn-upgrade"
                                :disabled="getLevel('bandwidth') >= 5 || !canAfford(getCost('bandwidth')) || processing"
                                @click="handleUpgrade('bandwidth')">
                                {{ getLevel('bandwidth') >= 5 ? 'MAX' : '$' + formatMoney(getCost('bandwidth')) }}
                            </button>
                        </div>

                        <!-- Network Tier Upgrade -->
                        <div class="upgrade-card upgrade-card--premium"
                            :class="{ 'at-max': getLevel('network_tier') >= 3 }">
                            <div class="upgrade-icon">🪐</div>
                            <div class="upgrade-info">
                                <h4>Network Backbone</h4>
                                <div class="upgrade-level" :class="{ 'text-glow': getLevel('network_tier') > 0 }">{{
                                    selectedRoom.bandwidth?.networkTierLabel || 'Standard' }}</div>
                                <div class="upgrade-description">Reduced regional latency & DDoS resistance.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--premium"
                                :disabled="getLevel('network_tier') >= 3 || !canAfford(getCost('network_tier')) || processing"
                                @click="handleUpgrade('network_tier')">
                                {{ getLevel('network_tier') >= 3 ? 'MAX' : '$' + formatMoney(getCost('network_tier')) }}
                            </button>
                        </div>

                        <!-- Redundancy Level Upgrade -->
                        <div class="upgrade-card upgrade-card--gold"
                            :class="{ 'at-max': (selectedRoom.cooling?.redundancy || 1) >= 4 }">
                            <div class="upgrade-icon">🛡️</div>
                            <div class="upgrade-info">
                                <h4>HA Redundancy</h4>
                                <div class="upgrade-level"
                                    :class="{ 'text-glow-gold': (selectedRoom.cooling?.redundancy || 1) > 1 }">
                                    {{ selectedRoom.cooling?.redundancyLabel || 'Tier 1' }}
                                </div>
                                <div class="upgrade-description">Reduced failure risk & partial operation during
                                    outages.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--gold"
                                :disabled="(selectedRoom.cooling?.redundancy || 1) >= 4 || !canAfford(getRedundancyCost()) || processing"
                                @click="handleRedundancyUpgrade">
                                {{ (selectedRoom.cooling?.redundancy || 1) >= 4 ? 'MAX' : '$' +
                                    formatMoney(getRedundancyCost()) }}
                            </button>
                        </div>

                        <!-- FEATURE 297: PR Tours & Data Center Tourism -->
                        <div class="upgrade-card upgrade-card--marketing" v-if="player.economy.level >= 15">
                            <div class="upgrade-icon">🎤</div>
                            <div class="upgrade-info">
                                <h4>Investor PR Tour</h4>
                                <div class="upgrade-level">Next Tour: {{ getPrTourStatus() }}</div>
                                <div class="upgrade-description">Host tours to gain Reputation and Investor Backing.
                                    Effectiveness depends on cleanliness and hardware quality.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--marketing"
                                :disabled="isPrTourOnCooldown() || !canAfford(5000) || processing"
                                @click="handlePrTour">
                                {{ isPrTourOnCooldown() ? 'COOLDOWN' : '$5,000' }}
                            </button>
                        </div>

                        <!-- FEATURE 268: Heat Recovery & Carbon Tax -->
                        <div class="carbon-tax-panel" v-if="selectedRoom.carbonTax">
                            <h4>🌿 Carbon Tax & Thermal Emissions</h4>
                            <div class="carbon-stats">
                                <div class="carbon-stat">
                                    <span class="cs-label">Waste Heat</span>
                                    <span class="cs-value">{{ selectedRoom.carbonTax.wasteHeatKw }} kW</span>
                                </div>
                                <div class="carbon-stat">
                                    <span class="cs-label">Regional Rate</span>
                                    <span class="cs-value">${{ selectedRoom.carbonTax.ratePerKw }}/kW</span>
                                </div>
                                <div class="carbon-stat highlight-cost">
                                    <span class="cs-label">Hourly Carbon Tax</span>
                                    <span class="cs-value">${{ selectedRoom.carbonTax.hourlyTax.toFixed(2) }}/h</span>
                                </div>
                            </div>
                            <p class="carbon-hint">Reduce via Green Reputation, eco_mode policy, or Heat Recovery.</p>
                        </div>

                        <div class="upgrade-card upgrade-card--eco" v-if="player.economy.level >= 8">
                            <div class="upgrade-icon">♻️</div>
                            <div class="upgrade-info">
                                <h4>Heat Recovery System</h4>
                                <div class="upgrade-level"
                                    :class="{ 'text-glow-green': selectedRoom.carbonTax?.hasHeatRecovery }">
                                    {{ selectedRoom.carbonTax?.hasHeatRecovery ? 'INSTALLED' : 'NOT INSTALLED' }}
                                </div>
                                <div class="upgrade-description">Sell waste heat to the district grid. Reduces Carbon
                                    Tax by 40% and boosts Green Reputation.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--eco"
                                :disabled="selectedRoom.carbonTax?.hasHeatRecovery || !canAfford(20000) || processing"
                                @click="handleHeatRecovery">
                                {{ selectedRoom.carbonTax?.hasHeatRecovery ? 'ACTIVE' : '$20,000' }}
                            </button>
                        </div>

                        <!-- FEATURE 63: Corporate Academy -->
                        <div class="upgrade-card upgrade-card--academy" v-if="player.economy.level >= 5">
                            <div class="upgrade-icon">🎓</div>
                            <div class="upgrade-info">
                                <h4>Corporate Academy</h4>
                                <div class="upgrade-level"
                                    :class="{ 'text-glow-blue': selectedRoom.hasAcademy }">
                                    {{ selectedRoom.hasAcademy ? 'INSTALLED' : 'NOT INSTALLED' }}
                                </div>
                                <div class="upgrade-description">Provides a continuous learning environment. All idle employees gain +5 XP per tick automatically.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--academy"
                                :disabled="selectedRoom.hasAcademy || !canAfford(35000) || processing"
                                @click="handleAcademyUpgrade">
                                {{ selectedRoom.hasAcademy ? 'ACTIVE' : '$35,000' }}
                            </button>
                        </div>

                        <!-- FEATURE 83: Reset Circuit Breaker -->
                        <div class="upgrade-card upgrade-card--danger" v-if="selectedRoom.power?.breakerTripped">
                            <div class="upgrade-icon">⚠️</div>
                            <div class="upgrade-info">
                                <h4>Circuit Breaker Tripped</h4>
                                <div class="upgrade-level text-glow-red">OFFLINE</div>
                                <div class="upgrade-description">Critical power overload detected. Reset the main
                                    breaker to restore power to your racks.</div>
                            </div>
                            <button class="btn-upgrade btn-upgrade--danger" :disabled="processing"
                                @click="handleResetBreaker">
                                RESET
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overlay-footer">
                <p class="footer-hint">Upgrading your infrastructure allows for more high-density racks and powerful
                    servers.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();

// Replace storeToRefs
const selectedRoom = computed(() => gameStore.selectedRoom);
const player = computed(() => gameStore.player);

const processing = ref(false);

const getLevel = (type) => {
    if (!selectedRoom.value || !selectedRoom.value.upgrades) return 0;
    return selectedRoom.value.upgrades[type] || 0;
};

const getCost = (type) => {
    if (!selectedRoom.value) return 0;

    // Logic matching the backend
    const baseRoomCost = selectedRoom.value.purchaseCost || 5000;
    const level = getLevel(type);

    if (type === 'network_tier') {
        return 10000 * Math.pow(3, level);
    }

    const baseCost = Math.max(500, baseRoomCost * 0.2);
    return baseCost * Math.pow(1.8, level);
};

const canAfford = (cost) => {
    return player.value.economy.balance >= cost;
};

const formatMoney = (val) => {
    return Math.round(val).toLocaleString();
};

const handleUpgrade = async (type) => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, type);
    } finally {
        processing.value = false;
    }
};

const handleRepair = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, 'repair_cooling');
    } finally {
        processing.value = false;
    }
};

const handleAirflow = async (type) => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, 'airflow', { airflow_type: type });
    } finally {
        processing.value = false;
    }
};

const getRepairCost = () => {
    if (!selectedRoom.value?.cooling) return 0;
    const damage = 100 - selectedRoom.value.cooling.health;
    return Math.max(50, Math.round(500 * (damage / 100)));
};

const getRedundancyCost = () => {
    if (!selectedRoom.value?.cooling) return 0;
    const level = selectedRoom.value.cooling.redundancy || 1;
    const redundancyCosts = {
        1: 25000,
        2: 100000,
        3: 500000,
    };
    return redundancyCosts[level] || 999999;
};

const handleRedundancyUpgrade = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, 'redundancy');
    } finally {
        processing.value = false;
    }
};

const handlePrTour = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        const result = await gameStore.hostPrTour(selectedRoom.value.id);
        if (result && result.success) {
            // Success toast is handled in store
        }
    } finally {
        processing.value = false;
    }
};

const isPrTourOnCooldown = () => {
    if (!selectedRoom.value?.lastPrTourAt) return false;
    const lastAt = new Date(selectedRoom.value.lastPrTourAt).getTime();
    const now = Date.now();
    return (now - lastAt) < (4 * 60 * 60 * 1000); // 4 hours
};

const getPrTourStatus = () => {
    if (!selectedRoom.value?.lastPrTourAt) return 'Ready Now';
    const lastAt = new Date(selectedRoom.value.lastPrTourAt).getTime();
    const nextAt = lastAt + (4 * 60 * 60 * 1000);
    const diff = nextAt - Date.now();

    if (diff <= 0) return 'Ready Now';

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return `In ${hours}h ${mins}m`;
};

const getHealthColor = (health) => {
    if (health > 80) return '#2ea043';
    if (health > 40) return '#e3b341';
    return '#f85149';
};

const handleHeatRecovery = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, 'heat_recovery');
    } finally {
        processing.value = false;
    }
};

const handleAcademyUpgrade = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.upgradeRoom(selectedRoom.value.id, 'academy');
    } finally {
        processing.value = false;
    }
};

const handleResetBreaker = async () => {
    if (processing.value || !selectedRoom.value) return;
    processing.value = true;
    try {
        await gameStore.resetCircuitBreaker(selectedRoom.value.id);
    } catch (e) {
        console.error('Failed to reset breaker', e);
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
    from {
        transform: translateY(50px);
        opacity: 0;
    }

    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.maintenance-panel,
.airflow-panel {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    margin-bottom: var(--space-md);
}

.maintenance-panel h4,
.airflow-panel h4 {
    margin: 0 0 10px 0;
    color: var(--color-text-secondary);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.maintenance-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.health-indicator {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.health-indicator label {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}

.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    transition: width 0.3s, background-color 0.3s;
}

.health-val {
    font-size: 0.8rem;
    font-family: var(--font-family-mono);
    text-align: right;
    color: var(--color-text-secondary);
}

.btn-repair {
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
}

.btn-repair:disabled {
    background: #333;
    color: #666;
    cursor: not-allowed;
}

.airflow-options {
    display: flex;
    gap: 10px;
}

.airflow-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.airflow-card.active {
    background: rgba(88, 166, 255, 0.15);
    border-color: var(--color-primary);
}

.airflow-card:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.1);
}

.af-icon {
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.af-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.af-label {
    font-weight: 700;
    font-size: 0.9rem;
    color: #fff;
}

.af-bonus {
    font-size: 0.8rem;
    color: var(--color-success);
}

.af-cost {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-top: 2px;
}

.upgrade-stats .stat-row {
    display: flex;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 2px;
}

.upgrade-stats {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.stat-row.highlight {
    color: var(--color-primary);
    font-weight: 700;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 4px;
    margin-top: 2px;
}

/* Premium Upgrade Style (Feature 30) */
.upgrade-card--premium {
    border-color: #7c3aed !important;
    background: linear-gradient(90deg, rgba(124, 58, 237, 0.05) 0%, transparent 100%) !important;
}

.upgrade-card--premium .upgrade-icon {
    background: rgba(124, 58, 237, 0.2);
    color: #a78bfa;
}

.btn-upgrade--premium {
    background: #7c3aed !important;
    color: white !important;
}

.btn-upgrade--premium:hover:not(:disabled) {
    background: #8b5cf6 !important;
    box-shadow: 0 0 15px rgba(124, 58, 237, 0.4);
}

.upgrade-description {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin-top: 4px;
}

.text-glow {
    text-shadow: 0 0 8px rgba(167, 139, 250, 0.5);
    color: #a78bfa !important;
    font-weight: 700;
}

.upgrade-card--gold {
    border-color: #f59e0b !important;
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%) !important;
}

.upgrade-card--gold .upgrade-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.btn-upgrade--gold {
    background: #f59e0b !important;
    color: black !important;
}

.btn-upgrade--gold:hover:not(:disabled) {
    background: #fbbf24 !important;
    box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);
}

.text-glow-gold {
    text-shadow: 0 0 8px rgba(251, 191, 36, 0.5);
    color: #fbbf24 !important;
    font-weight: 700;
}

/* Academy Upgrade Style */
.upgrade-card--academy {
    border-color: #3b82f6 !important;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, transparent 100%) !important;
}

.upgrade-card--academy .upgrade-icon {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.btn-upgrade--academy {
    background: #3b82f6 !important;
    color: white !important;
}

.btn-upgrade--academy:hover:not(:disabled) {
    background: #60a5fa !important;
    box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
}

.text-glow-blue {
    text-shadow: 0 0 8px rgba(96, 165, 250, 0.5);
    color: #60a5fa !important;
    font-weight: 700;
}
}

.upgrade-card--marketing {
    border-color: #06b6d4 !important;
    background: linear-gradient(90deg, rgba(6, 182, 212, 0.05) 0%, transparent 100%) !important;
}

.upgrade-card--marketing .upgrade-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #22d3ee;
}

.btn-upgrade--marketing {
    background: #0891b2 !important;
    color: white !important;
}

.btn-upgrade--marketing:hover:not(:disabled) {
    background: #0ea5e9 !important;
    box-shadow: 0 0 15px rgba(6, 182, 212, 0.4);
}

.upgrade-card--danger {
    border-color: #ef4444 !important;
    background: linear-gradient(90deg, rgba(239, 68, 68, 0.1) 0%, transparent 100%) !important;
    animation: pulse-border-red 2s infinite;
}

.upgrade-card--danger .upgrade-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.btn-upgrade--danger {
    background: #ef4444 !important;
    color: white !important;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
}

.btn-upgrade--danger:hover:not(:disabled) {
    background: #f87171 !important;
    box-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
}

.text-glow-red {
    text-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
    color: #f87171 !important;
    font-weight: 800;
}

@keyframes pulse-border-red {
    0% {
        border-color: rgba(239, 68, 68, 0.5);
    }

    50% {
        border-color: rgba(239, 68, 68, 1);
    }

    100% {
        border-color: rgba(239, 68, 68, 0.5);
    }
}

/* FEATURE 268: Carbon Tax Panel */
.carbon-tax-panel {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    margin-bottom: var(--space-md);
}

.carbon-tax-panel h4 {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    color: #34d399;
}

.carbon-stats {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.carbon-stat {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    padding: 4px 0;
}

.cs-label {
    color: var(--color-text-muted);
    font-family: var(--font-family-mono);
    font-size: 0.75rem;
}

.cs-value {
    color: var(--color-text-secondary);
    font-family: var(--font-family-mono);
    font-weight: 700;
}

.carbon-stat.highlight-cost {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 8px;
    margin-top: 4px;
}

.carbon-stat.highlight-cost .cs-value {
    color: #f59e0b;
}

.carbon-hint {
    margin: 8px 0 0 0;
    font-size: 0.7rem;
    color: var(--color-text-muted);
    font-style: italic;
}

.upgrade-card--eco {
    border-color: #10b981 !important;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%) !important;
}

.upgrade-card--eco .upgrade-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.btn-upgrade--eco {
    background: #10b981 !important;
    color: #000 !important;
}

.btn-upgrade--eco:hover:not(:disabled) {
    background: #34d399 !important;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
}

.text-glow-green {
    text-shadow: 0 0 8px rgba(52, 211, 153, 0.5);
    color: #34d399 !important;
    font-weight: 700;
}
</style>
