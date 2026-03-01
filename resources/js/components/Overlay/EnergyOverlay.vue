<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="energy-overlay glass-panel animation-fade-in">
            <div class="overlay-header">
                <div class="header-main">
                    <div class="header-icon">⚡</div>
                    <div class="header-text">
                        <h2>Energy Market Hub</h2>
                        <p class="subtitle">Grid Stability & Spot Market Operations</p>
                    </div>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-grid">
                <!-- LEFT: MARKET DATA -->
                <div class="market-view">
                    <div class="current-price-card" :class="priceTrendClass">
                        <div class="price-label">Current Spot Price</div>
                        <div class="price-value">
                            <span class="currency">$</span>
                            <span class="amount">{{ energyMarket.spotPrice.toFixed(4) }}</span>
                            <span class="unit">/kWh</span>
                        </div>
                        <div class="price-trend">
                            <span class="trend-icon">{{ priceTrendIcon }}</span>
                            <span class="trend-text">{{ priceTrendText }}</span>
                        </div>
                    </div>

                    <div class="history-chart">
                        <h3>Price History (Last 60m)</h3>
                        <div class="sparkline-container">
                            <svg viewBox="0 0 400 100" class="sparkline">
                                <polyline fill="none" stroke="#58a6ff" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" :points="sparklinePoints" />
                                <!-- Zero line -->
                                <line x1="0" y1="90" x2="400" y2="90" stroke="rgba(255,255,255,0.05)"
                                    stroke-width="1" />
                            </svg>
                        </div>
                        <div class="chart-labels">
                            <span>-60 min</span>
                            <span>Now</span>
                        </div>
                    </div>

                    <div class="market-info">
                        <div class="info-item">
                            <span class="i-label">Grid Load</span>
                            <span class="i-val">Nominal</span>
                        </div>
                        <div class="info-item">
                            <span class="i-label">Volatility</span>
                            <span class="i-val text-warning">Medium</span>
                        </div>
                    </div>

                    <!-- NEW: Battery Storage Overview -->
                    <div class="storage-card" v-if="energyMarket.storage?.battery_count > 0">
                        <div class="card-header">
                            <h3>Battery Storage</h3>
                            <span class="unit-count">{{ energyMarket.storage.battery_count }} Units</span>
                        </div>
                        <div class="storage-visual">
                            <div class="progress-container">
                                <div class="progress-fill" :style="{ width: storagePercent + '%' }"></div>
                            </div>
                            <div class="storage-stats">
                                <div class="stat">
                                    <span class="l">Current</span>
                                    <span class="v">{{ (energyMarket.storage?.current_level || 0).toFixed(1) }}
                                        kWh</span>
                                </div>
                                <div class="stat">
                                    <span class="l">Avg Health</span>
                                    <span class="v" :class="healthClass">{{
                                        (energyMarket.storage?.average_health || 100).toFixed(1) }}%</span>
                                </div>
                                <div class="stat text-right">
                                    <span class="l">Max</span>
                                    <span class="v">{{ (energyMarket.storage?.total_capacity || 0).toFixed(1) }}
                                        kWh</span>
                                </div>
                            </div>
                        </div>
                        <div class="storage-footer">
                            <span class="status-msg vpp-active" v-if="energyMarket.storage?.is_vpp_active">
                                <span class="pulse-dot"></span> SELLING TO GRID
                            </span>
                            <span class="status-msg" v-else>
                                {{ storagePercent > 90 ? 'Full capacity reached' : (storagePercent < 10 ? 'Reserves low'
                                    : 'Stabilizing grid') }} </span>
                                    <span class="percent-val">{{ storagePercent.toFixed(1) }}%</span>
                        </div>
                    </div>

                    <!-- NEW: Green Power Score -->
                    <div class="green-score-card">
                        <div class="card-header">
                            <h3>Green Power Score</h3>
                            <span class="eco-badge">ECO-CERTIFIED</span>
                        </div>
                        <div class="score-display">
                            <div class="score-radial">
                                <svg viewBox="0 0 36 36" class="circular-chart green">
                                    <path class="circle-bg"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" :stroke-dasharray="greenScorePercent + ', 100'"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <text x="18" y="20.35" class="percentage">{{ greenScorePercent }}%</text>
                                </svg>
                            </div>
                            <div class="score-labels">
                                <div class="score-tier">{{ greenScoreTier }}</div>
                                <p class="score-desc">Based on renewable coverage and battery storage capacity.</p>
                            </div>
                        </div>
                        <div class="score-footer">
                            <span class="bonus-msg">+{{ (energyMarket.greenScore * 15).toFixed(1) }}% Negotiation
                                Bonus</span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: CONTRACTS -->
                <div class="contracts-view">
                    <div class="section-title">
                        <h3>Active Contract</h3>
                    </div>

                    <div v-if="energyMarket.currentContract?.type === 'fixed'" class="active-contract-card">
                        <div class="contract-badge">LOCKED</div>
                        <div class="contract-details">
                            <div class="c-price">${{ energyMarket.currentContract.price.toFixed(4) }}/kWh</div>
                            <div class="c-expiry">Expires in: {{ formatExpiry }}</div>
                        </div>
                        <div class="contract-status">
                            <div class="status-indicator online"></div>
                            <span>Protected against market spikes</span>
                        </div>
                    </div>

                    <div v-else class="active-contract-card variable">
                        <div class="contract-badge gray">FLEXIBLE</div>
                        <div class="contract-details">
                            <div class="c-price">SPOT MARKET RATES</div>
                            <div class="c-expiry">Direct grid connection</div>
                        </div>
                        <div class="contract-status">
                            <div class="status-indicator warning"></div>
                            <span>Exposed to price volatility</span>
                        </div>
                    </div>

                    <div class="available-offers">
                        <h3>Available Fixed Contracts</h3>
                        <div class="offers-list">
                            <div v-for="offer in energyMarket.offers" :key="offer.type" class="offer-card">
                                <div class="offer-header">
                                    <h4>{{ offer.name }}</h4>
                                    <div class="offer-price">${{ offer.price.toFixed(4) }}</div>
                                </div>
                                <p class="offer-desc">{{ offer.description }}</p>
                                <div class="offer-meta">
                                    <span>Duration: {{ (offer.duration_ticks / 60).toFixed(1) }}h</span>
                                </div>
                                <button class="sign-button" @click="signContract(offer.type)"
                                    :disabled="loading || economy.balance < 100">
                                    Sign Agreement
                                </button>
                            </div>
                        </div>
                        <p class="disclaimer">* Switching to a fixed contract requires a $100 administration fee.</p>
                    </div>

                    <!-- NEW: Policies Section -->
                    <div class="policies-section">
                        <div class="section-title">
                            <h3>Operational Policies</h3>
                            <span class="badge-count">{{ energyMarket.activePolicies?.length || 0 }} / 1 Active</span>
                        </div>
                        <div class="policies-grid">
                            <div v-for="(policy, key) in energyMarket.policies" :key="key" class="policy-card"
                                :class="{ 'active': energyMarket.activePolicies?.includes(key) }"
                                @click="handleTogglePolicy(key)">
                                <div class="policy-icon">{{ policy.icon }}</div>
                                <div class="policy-info">
                                    <h4>{{ policy.name }}</h4>
                                    <p>{{ policy.description }}</p>
                                </div>
                                <div class="policy-status">
                                    <div class="toggle-switch">
                                        <div class="switch-handle"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';

const emit = defineEmits(['close']);
const gameStore = useGameStore();

// Replace storeToRefs
const energyMarket = computed(() => gameStore.energyMarket);
const player = computed(() => gameStore.player);

const loading = ref(false);
const economy = computed(() => player.value.economy);

const priceTrendClass = computed(() => {
    if (energyMarket.value.history.length < 2) return '';
    const last = energyMarket.value.history[energyMarket.value.history.length - 1].price;
    const prev = energyMarket.value.history[energyMarket.value.history.length - 2].price;
    return last > prev ? 'price-up' : 'price-down';
});

const priceTrendIcon = computed(() => {
    if (energyMarket.value.history.length < 2) return '─';
    const last = energyMarket.value.history[energyMarket.value.history.length - 1].price;
    const prev = energyMarket.value.history[energyMarket.value.history.length - 2].price;
    return last > prev ? '▲' : '▼';
});

const priceTrendText = computed(() => {
    if (energyMarket.value.history.length < 2) return 'Stable';
    const last = energyMarket.value.history[energyMarket.value.history.length - 1].price;
    const prev = energyMarket.value.history[energyMarket.value.history.length - 2].price;
    const diff = ((last - prev) / prev * 100).toFixed(2);
    return diff > 0 ? `+${diff}%` : `${diff}%`;
});

const sparklinePoints = computed(() => {
    const history = energyMarket.value.history;
    if (history.length < 2) return '0,50 400,50';

    const min = Math.min(...history.map(h => h.price)) * 0.9;
    const max = Math.max(...history.map(h => h.price)) * 1.1;
    const range = max - min;

    return history.map((h, i) => {
        const x = (i / (history.length - 1)) * 400;
        const y = 100 - ((h.price - min) / range) * 100;
        return `${x},${y}`;
    }).join(' ');
});

const formatExpiry = computed(() => {
    const expiry = energyMarket.value.currentContract?.expires_at;
    if (!expiry) return 'N/A';
    const diff = new Date(expiry) - new Date();
    if (diff < 0) return 'Expired';
    const mins = Math.floor(diff / 60000);
    return `${mins}m`;
});

const storagePercent = computed(() => {
    if (!energyMarket.value.storage?.total_capacity) return 0;
    return (energyMarket.value.storage.current_level / energyMarket.value.storage.total_capacity) * 100;
});
const greenScorePercent = computed(() => {
    return Math.round((energyMarket.value.greenScore || 0) * 100);
});

const greenScoreTier = computed(() => {
    const s = energyMarket.value.greenScore * 100;
    if (s >= 90) return 'Carbon Neutral';
    if (s >= 70) return 'Green Innovator';
    if (s >= 40) return 'Eco Conscious';
    if (s > 0) return 'Grid Dependent';
    return 'Coal Powered';
});

const healthClass = computed(() => {
    const h = energyMarket.value.storage?.average_health || 100;
    if (h > 80) return 'text-success';
    if (h > 50) return 'text-warning';
    return 'text-danger';
});

onMounted(() => {
    gameStore.loadEnergyData();
});

async function signContract(type) {
    if (!confirm("Contract update will incur an administrative fee of $100. Apply signature?")) return;
    loading.value = true;
    await gameStore.signEnergyContract(type);
    loading.value = false;
}

async function handleTogglePolicy(key) {
    loading.value = true;
    await gameStore.toggleEnergyPolicy(key);
    loading.value = false;
}
</script>

<style scoped>
.badge-count {
    font-size: 0.7rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 2px 8px;
    border-radius: 10px;
    color: #8b949e;
}

.policies-section {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.policies-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.policy-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.policy-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.15);
}

.policy-card.active {
    background: rgba(88, 166, 255, 0.08);
    border-color: rgba(88, 166, 255, 0.3);
}

.policy-icon {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.policy-info {
    flex: 1;
}

.policy-info h4 {
    margin: 0 0 4px 0;
    font-size: 0.95rem;
    color: #fff;
}

.policy-info p {
    margin: 0;
    font-size: 0.8rem;
    color: #8b949e;
    line-height: 1.3;
}

.toggle-switch {
    width: 36px;
    height: 20px;
    background: #30363d;
    border-radius: 10px;
    position: relative;
    transition: all 0.3s;
}

.active .toggle-switch {
    background: #238636;
}

.switch-handle {
    width: 14px;
    height: 14px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    left: 3px;
    transition: all 0.3s;
}

.active .switch-handle {
    left: 19px;
}

.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    z-index: 2500;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.glass-panel {
    background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(10, 15, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
    border-radius: 24px;
}

.energy-overlay {
    width: 1000px;
    max-width: 95vw;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    padding: 30px 40px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-main {
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-icon {
    font-size: 2.2rem;
    filter: drop-shadow(0 0 10px rgba(227, 179, 65, 0.5));
}

.header-text h2 {
    margin: 0;
    font-size: 1.6rem;
    color: #fff;
}

.subtitle {
    margin: 4px 0 0 0;
    color: #8b949e;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 2rem;
    cursor: pointer;
    transition: color 0.2s;
}

.close-btn:hover {
    color: #fff;
}

.overlay-grid {
    display: grid;
    grid-template-columns: 420px 1fr;
    padding: 40px;
    gap: 40px;
}

/* LEFT SIDE */
.market-view {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.current-price-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.current-price-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #58a6ff;
}

.price-up::before {
    background: #f85149;
}

.price-down::before {
    background: #3fb950;
}

.price-label {
    font-size: 0.8rem;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.price-value {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
}

.currency {
    font-size: 1.5rem;
    color: #8b949e;
}

.amount {
    font-size: 3rem;
    font-weight: 800;
    color: #fff;
    font-family: 'JetBrains Mono', monospace;
}

.unit {
    font-size: 1.2rem;
    color: #8b949e;
}

.price-trend {
    margin-top: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 700;
    font-size: 0.9rem;
}

.price-up .price-trend {
    color: #f85149;
}

.price-down .price-trend {
    color: #3fb950;
}

.history-chart {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.history-chart h3 {
    font-size: 0.9rem;
    color: #8b949e;
    margin-bottom: 20px;
}

.sparkline-container {
    height: 100px;
    width: 100%;
    margin-bottom: 10px;
}

.sparkline {
    width: 100%;
    height: 100%;
}

.chart-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #484f58;
    font-weight: 700;
}

.market-info {
    display: flex;
    gap: 20px;
}

.info-item {
    flex: 1;
    background: rgba(255, 255, 255, 0.02);
    padding: 15px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.i-label {
    font-size: 0.7rem;
    color: #8b949e;
    text-transform: uppercase;
    font-weight: 700;
}

.i-val {
    font-size: 1rem;
    color: #fff;
    font-weight: 600;
}

/* RIGHT SIDE */
.contracts-view {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.section-title h3 {
    margin: 0;
    font-size: 1.2rem;
    color: #fff;
}

.active-contract-card {
    background: rgba(88, 166, 255, 0.05);
    border: 1px solid rgba(88, 166, 255, 0.2);
    border-radius: 20px;
    padding: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
}

.active-contract-card.variable {
    background: rgba(227, 179, 65, 0.05);
    border-color: rgba(227, 179, 65, 0.2);
}

.contract-badge {
    position: absolute;
    top: 15px;
    right: 20px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 800;
    background: #58a6ff;
    color: #0d1117;
}

.contract-badge.gray {
    background: #30363d;
    color: #8b949e;
}

.c-price {
    font-size: 1.8rem;
    font-weight: 800;
    color: #fff;
    font-family: 'JetBrains Mono', monospace;
}

.c-expiry {
    color: #8b949e;
    font-size: 0.9rem;
    margin-top: 5px;
}

.contract-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
    color: #fff;
    font-weight: 600;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.status-indicator.online {
    background: #3fb950;
    box-shadow: 0 0 10px #3fb950;
}

.status-indicator.warning {
    background: #e3b341;
    box-shadow: 0 0 10px #e3b341;
}

.available-offers h3 {
    margin-bottom: 20px;
    font-size: 1rem;
    color: #fff;
}

.offers-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.offer-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    transition: all 0.2s;
}

.offer-card:hover {
    border-color: rgba(88, 166, 255, 0.4);
    background: rgba(255, 255, 255, 0.05);
}

.offer-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.offer-header h4 {
    margin: 0;
    font-size: 0.95rem;
    color: #fff;
}

.offer-price {
    font-family: 'JetBrains Mono', monospace;
    color: #58a6ff;
    font-weight: 700;
    font-size: 1.1rem;
}

.offer-desc {
    font-size: 0.85rem;
    color: #8b949e;
    line-height: 1.4;
}

.offer-meta {
    font-size: 0.75rem;
    color: #484f58;
    font-weight: 700;
    text-transform: uppercase;
}

.sign-button {
    background: #238636;
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.sign-button:hover:not(:disabled) {
    background: #2ea043;
    transform: translateY(-1px);
}

.sign-button:disabled {
    opacity: 0.5;
    background: #30363d;
    cursor: not-allowed;
}

.disclaimer {
    font-size: 0.7rem;
    color: #484f58;
    margin-top: 20px;
    font-style: italic;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animation-fade-in {
    animation: fadeIn 0.3s ease-out;
}

.green-score-card {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.1);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.green-score-card h3 {
    margin: 0;
    font-size: 0.9rem;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.eco-badge {
    font-size: 0.6rem;
    font-weight: 800;
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}

.score-display {
    display: flex;
    align-items: center;
    gap: 20px;
}

.score-radial {
    width: 80px;
    height: 80px;
}

.circular-chart {
    display: block;
    margin: 0;
    max-width: 100%;
    max-height: 250px;
}

.circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.05);
    stroke-width: 3.8;
}

.circle {
    fill: none;
    stroke-width: 2.8;
    stroke-linecap: round;
    transition: stroke-dasharray 0.3s ease;
}

.green .circle {
    stroke: #10b981;
}

.percentage {
    fill: #fff;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.5rem;
    text-anchor: middle;
    font-weight: 800;
}

.score-labels {
    flex: 1;
}

.score-tier {
    font-size: 1.1rem;
    font-weight: 800;
    color: #10b981;
    margin-bottom: 4px;
}

.score-desc {
    font-size: 0.75rem;
    color: #8b949e;
    margin: 0;
    line-height: 1.3;
}

.score-footer {
    padding-top: 10px;
    border-top: 1px solid rgba(16, 185, 129, 0.1);
}

.bonus-msg {
    font-size: 0.75rem;
    font-weight: 700;
    color: #10b981;
}

.vpp-active {
    color: #58a6ff !important;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pulse-dot {
    width: 6px;
    height: 6px;
    background: #58a6ff;
    border-radius: 50%;
    box-shadow: 0 0 10px #58a6ff;
    animation: anchor-pulse 1.5s infinite;
}

@keyframes anchor-pulse {
    0% {
        transform: scale(0.95);
        opacity: 0.5;
    }

    50% {
        transform: scale(1.2);
        opacity: 1;
    }

    100% {
        transform: scale(0.95);
        opacity: 0.5;
    }
}

.storage-card {
    background: rgba(251, 191, 36, 0.05);
    border: 1px solid rgba(251, 191, 36, 0.1);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 20px;
}

.storage-card h3 {
    margin: 0;
    font-size: 0.9rem;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.unit-count {
    font-size: 0.7rem;
    font-weight: 800;
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.1);
    padding: 2px 8px;
    border-radius: 6px;
}

.progress-container {
    height: 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #fbbf24, #d97706);
    box-shadow: 0 0 10px rgba(251, 191, 36, 0.3);
    transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.storage-stats {
    display: flex;
    justify-content: space-between;
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat .l {
    font-size: 0.6rem;
    color: #8b949e;
    text-transform: uppercase;
    font-weight: 700;
}

.stat .v {
    font-size: 0.9rem;
    color: #fff;
    font-family: var(--font-family-mono);
    font-weight: 700;
}

.text-right {
    text-align: right;
}

.storage-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 12px;
}

.status-msg {
    font-size: 0.7rem;
    color: #8b949e;
    font-style: italic;
}

.percent-val {
    font-size: 1.1rem;
    font-weight: 900;
    color: #fbbf24;
    font-family: var(--font-family-mono);
}
</style>
