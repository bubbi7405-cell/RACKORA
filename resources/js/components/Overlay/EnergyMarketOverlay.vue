<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="energy-market-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">⚡</span>
                    <h2>Global Energy Grid <small>Live Market Access</small></h2>
                </div>
                <div class="header-actions">
                    <div class="status-badge" :class="isCrisis ? 'crisis' : 'stable'">
                        {{ isCrisis ? 'MARKET VOLATILITY' : 'GRID STABLE' }}
                    </div>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </div>
            </div>

            <div class="overlay-tabs">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="{ active: activeTab === tab.id }"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="overlay-body">
                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'" class="tab-content">
                    <div class="market-summary">
                        <div class="summary-card">
                            <label>Global Spot Price</label>
                            <div class="value large">
                                ${{ (marketData.spot_price || 0).toFixed(3) }} <small>/ kWh</small>
                            </div>
                        </div>
                        <div class="summary-card">
                            <label>Your Avg. Rate</label>
                            <div class="value">
                                ${{ (userAvgRate || 0).toFixed(3) }} <small>/ kWh</small>
                            </div>
                        </div>
                         <div class="summary-card">
                            <label>Current Contract</label>
                            <div class="value highlight">
                                {{ currentContractLabel }}
                            </div>
                        </div>
                    </div>

                    <div class="region-grid">
                        <div class="grid-header">
                            <span>Region Node</span>
                            <span>Trend (1h)</span>
                            <span class="text-right">Spot Price</span>
                            <span class="text-right">Status</span>
                        </div>
                        <div v-for="(price, region) in regionalPrices" :key="region" class="grid-row" :class="{ 'active-region': isUserInRegion(region) }">
                            <div class="region-name">
                                <span class="indicator"></span>
                                {{ formatRegionName(region) }}
                                <span v-if="isUserInRegion(region)" class="user-badge">ACTIVE</span>
                            </div>
                            <div class="sparkline">
                                <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                                    <path :d="generateSparkline(region)" fill="none" stroke="var(--color-accent)" stroke-width="2" />
                                </svg>
                            </div>
                            <div class="price-cell text-right">
                                <span class="price-val">${{ Number(price).toFixed(3) }}</span>
                            </div>
                            <div class="status-cell text-right">
                                <span class="status-dot" :class="getPriceStatus(price)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contracts Tab -->
                <div v-if="activeTab === 'contracts'" class="tab-content">
                    <div class="info-block">
                        <p>Lock in energy rates to protect your margins from market volatility.</p>
                    </div>

                    <div class="contracts-list">
                        <div v-for="offer in marketData.offers" :key="offer.type" class="contract-card">
                            <div class="contract-header">
                                <h3>{{ offer.name }}</h3>
                                <span class="duration">{{ offer.duration_days }} Days</span>
                            </div>
                            <div class="contract-price">
                                ${{ offer.price_per_kwh.toFixed(3) }} <small>/ kWh</small>
                            </div>
                            <div class="contract-desc">{{ offer.description }}</div>
                            <button 
                                class="btn-sign" 
                                :disabled="hasActiveContract || processing"
                                @click="signContract(offer.type)"
                            >
                                {{ hasActiveContract ? 'Contract Active' : 'Sign Contract' }}
                            </button>
                        </div>
                    </div>
                </div>

                  <!-- Policies Tab -->
                <div v-if="activeTab === 'policies'" class="tab-content">
                     <div class="policies-list">
                        <div v-for="policy in marketData.policies" :key="policy.key" class="policy-card">
                            <div class="policy-header">
                                <h3>{{ policy.name }}</h3>
                                <label class="switch">
                                    <input type="checkbox" :checked="isPolicyActive(policy.key)" @change="togglePolicy(policy.key)">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <p>{{ policy.description }}</p>
                            <div class="policy-effects">
                                <span v-for="(eff, i) in policy.effects" :key="i" class="effect-tag">{{ eff }}</span>
                            </div>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../utils/api';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();
const activeTab = ref('overview');
const processing = ref(false);
const marketData = ref({
    spot_price: 0,
    regional_prices: {},
    regional_history: {},
    current_contract: {},
    offers: [],
    policies: [],
    active_policies: {}
});

const tabs = [
    { id: 'overview', label: 'Grid Status' },
    { id: 'contracts', label: 'Contracts' },
    { id: 'policies', label: 'Policies' }
];

const loadData = async () => {
    try {
        const res = await api.get('/energy');
        if (res.success) {
            marketData.value = res;
        }
    } catch (e) {
        console.error("Failed to load energy market", e);
    }
};

const signContract = async (type) => {
    if (processing.value) return;
    if (!confirm('Sign this energy contract? Early cancellation penalties may apply.')) return;
    
    processing.value = true;
    try {
        await api.post('/energy/sign', { type });
        await loadData();
        gameStore.loadGameState();
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to sign contract');
    } finally {
        processing.value = false;
    }
};

const togglePolicy = async (policyKey) => {
    try {
        await api.post('/energy/policy', { policy: policyKey });
        await loadData();
    } catch (e) {
        console.error(e);
    }
};

const isPolicyActive = (key) => {
    return marketData.value.active_policies && marketData.value.active_policies[key];
};

const generateSparkline = (region) => {
    const history = marketData.value.regional_history?.[region] || [];
    if (history.length < 2) return "M 0 15 L 100 15";

    // Normalize
    const prices = history.map(h => Number(h.price));
    const min = Math.min(...prices) * 0.95;
    const max = Math.max(...prices) * 1.05;
    const range = max - min || 0.01;

    const points = prices.map((p, i) => {
        const x = (i / (prices.length - 1)) * 100;
        const y = 30 - ((p - min) / range) * 30;
        return `${x} ${y}`;
    });

    return `M ${points.join(' L ')}`;
};

const regionalPrices = computed(() => {
    // Filter out global_avg and sort
    const p = { ...marketData.value.regional_prices };
    delete p['global_avg'];
    return p;
});

const isUserInRegion = (region) => {
    if (!gameStore.rooms) return false;
    return Object.values(gameStore.rooms).some(r => r.region === region);
};

const userAvgRate = computed(() => {
    // Simplified: just show global spot or contract
    if (marketData.value.current_contract?.type !== 'variable') {
         return marketData.value.current_contract?.price;
    }
    return marketData.value.spot_price;
});

const currentContractLabel = computed(() => {
    const c = marketData.value.current_contract;
    if (c?.type === 'fixed') return 'FIXED RATE';
    return 'VARIABLE SPOT';
});

const hasActiveContract = computed(() => {
    const c = marketData.value.current_contract;
    return c?.type === 'fixed' && new Date(c.expires_at) > new Date();
});

const formatRegionName = (key) => {
    return key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
};

const getPriceStatus = (price) => {
    // Determine if price is high or low relative to base/avg
    // This logic can be improved
    if (price > 0.15) return 'high';
    if (price < 0.08) return 'low';
    return 'med';
};

const isCrisis = computed(() => {
    return marketData.value.spot_price > 0.20;
});

onMounted(loadData);
</script>

<style scoped>
.energy-market-overlay {
    width: 900px;
    max-width: 95vw;
    background: #05070a;
    border-radius: 4px;
    border: 1px solid #333;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 50px 100px rgba(0,0,0,0.8);
    color: #ccc;
    font-family: 'Inter', sans-serif;
}

.overlay-header {
    background: #0f1219;
    padding: 24px;
    border-bottom: 1px solid #222;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.header-title small {
    display: block;
    font-size: 0.7rem;
    color: #666;
    margin-top: 4px;
}

.status-badge {
    padding: 6px 12px;
    font-size: 0.7rem;
    font-weight: 800;
    border-radius: 2px;
    background: #111;
    border: 1px solid #333;
}
.status-badge.stable { color: #2ecc71; border-color: #2ecc71; }
.status-badge.crisis { color: #e74c3c; border-color: #e74c3c; animation: pulse 2s infinite; }

.overlay-tabs {
    display: flex;
    border-bottom: 1px solid #222;
    background: #0a0c10;
}

.overlay-tabs button {
    padding: 16px 24px;
    background: transparent;
    border: none;
    color: #666;
    font-weight: 700;
    cursor: pointer;
    text-transform: uppercase;
    font-size: 0.75rem;
}

.overlay-tabs button.active {
    color: #fff;
    border-bottom: 2px solid #3498db;
}

.overlay-body {
    padding: 32px;
    min-height: 400px;
}

/* Overview Tab */
.market-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.summary-card {
    background: #0f1219;
    padding: 20px;
    border: 1px solid #222;
    text-align: center;
}

.summary-card label {
    display: block;
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #666;
    margin-bottom: 8px;
    letter-spacing: 1px;
}

.summary-card .value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #fff;
}
.summary-card .value small { font-size: 0.8rem; color: #888; }
.summary-card .value.highlight { color: #3498db; font-size: 1.2rem; margin-top: 8px; }

.region-grid {
    border: 1px solid #222;
    background: #0f1219;
}

.grid-header {
    display: grid;
    grid-template-columns: 3fr 3fr 2fr 1fr;
    padding: 12px 20px;
    background: #151921;
    border-bottom: 1px solid #222;
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #666;
}

.grid-row {
    display: grid;
    grid-template-columns: 3fr 3fr 2fr 1fr;
    padding: 16px 20px;
    border-bottom: 1px solid #222;
    align-items: center;
    transition: background 0.2s;
}

.grid-row:hover { background: #1a1e26; }
.grid-row.active-region { background: #1a2230; border-left: 3px solid #3498db; }

.region-name { font-weight: 700; color: #eee; display: flex; align-items: center; gap: 8px; }
.user-badge { font-size: 0.5rem; background: #3498db; color: #fff; padding: 2px 4px; border-radius: 2px; }

.sparkline svg { width: 100%; height: 30px; }

.price-val { font-family: monospace; font-size: 0.9rem; color: #fff; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-dot.low { background: #2ecc71; box-shadow: 0 0 5px #2ecc71; }
.status-dot.med { background: #f1c40f; }
.status-dot.high { background: #e74c3c; box-shadow: 0 0 5px #e74c3c; }

/* Contracts */
.contracts-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.contract-card {
    background: #0f1219;
    border: 1px solid #222;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.contract-header h3 { margin: 0; font-size: 1rem; color: #fff; }
.contract-header .duration { font-size: 0.7rem; color: #666; text-transform: uppercase; }

.contract-price {
    font-size: 1.5rem;
    font-weight: 800;
    color: #3498db;
}

.btn-sign {
    margin-top: auto;
    background: #3498db;
    color: #fff;
    border: none;
    padding: 12px;
    text-transform: uppercase;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-sign:hover:not(:disabled) { background: #2980b9; }
.btn-sign:disabled { background: #333; color: #666; cursor: not-allowed; }

/* Policies */
.policies-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.policy-card {
    background: #0f1219;
    border: 1px solid #222;
    padding: 20px;
}

.policy-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.policy-header h3 { margin: 0; color: #fff; font-size: 1rem; }

.effect-tag {
    display: inline-block;
    background: #111;
    border: 1px solid #333;
    color: #aaa;
    font-size: 0.7rem;
    padding: 4px 8px;
    margin-top: 8px;
    margin-right: 8px;
}

/* Toggle Switch */
.switch { position: relative; display: inline-block; width: 40px; height: 20px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 20px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #2ecc71; }
input:checked + .slider:before { transform: translateX(20px); }

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>
