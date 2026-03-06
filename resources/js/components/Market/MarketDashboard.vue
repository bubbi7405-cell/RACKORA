<template>
    <div class="market-dashboard">
        <!-- Header -->
        <header class="market-header">
            <div class="header-left">
                <h1 class="market-title">Global Exchange</h1>
                <div class="market-status">
                    <span class="status-dot" :class="economyStateClass"></span>
                    {{ marketStore.economy.label }}
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="market-ticker-container">
                <div class="ticker-scroll">
                    <span class="ticker-item">GDP: {{ formatPercent(marketStore.economy.gdp_growth) }}</span>
                    <span class="ticker-item">Inflation: {{ formatPercent(marketStore.economy.inflation) }}</span>
                    <span class="ticker-item">Energy Index: {{ marketStore.economy.energy_cost.toFixed(2) }}x</span>
                    <span class="ticker-item">Global Demand: {{ marketStore.economy.global_demand_index.toFixed(1)
                        }}</span>
                    <span class="ticker-item">Credit: {{ marketStore.economy.credit_cost.toFixed(2) }}x</span>
                </div>
            </div>
        </header>

        <!-- Navigation -->
        <nav class="market-tabs">
            <button v-for="tab in tabs" :key="tab.id" class="tab-btn" :class="{ active: activeTab === tab.id }"
                @click="activeTab = tab.id">
                {{ tab.label }}
            </button>
        </nav>

        <!-- Content Area -->
        <div class="market-content scroll-container">

            <!-- OVERVIEW TAB -->
            <div v-if="activeTab === 'overview'" class="overview-layout">
                <!-- Left: Market Share -->
                <div class="dashboard-card share-card">
                    <div class="card-header">
                        <h3>Global Market Share</h3>
                        <span class="card-badge">LIVE</span>
                    </div>
                    <div class="chart-container">
                        <MarketShareChart :segments="marketShareData" :size="280" :thickness="40" show-total
                            :total-label="totalMarketCapLabel" />
                        <div class="legend">
                            <div v-for="item in marketShareData" :key="item.label" class="legend-item"
                                :style="{ '--comp-color': item.color }">
                                <div class="legend-header">
                                    <span class="legend-label">{{ item.label }}</span>
                                    <span class="legend-value">{{ item.value.toFixed(1) }}%</span>
                                </div>
                                <div class="legend-bar-track">
                                    <div class="legend-bar-fill"
                                        :style="{ width: item.value + '%', background: item.color }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: KPIs & Sectors -->
                <div class="dashboard-right-col">
                    <!-- KPIs -->
                    <div class="kpi-grid">
                        <KpiGauge label="Your Share" :value="marketStore.player.globalShare" unit="%"
                            :trend="shareTrend" />
                        <KpiGauge label="ARPU" :value="marketStore.player.arpu" unit="$" :decimals="2" />
                        <KpiGauge label="Innovation" :value="marketStore.player.innovationIndex" :max="100" show-bar
                            :bar-percent="marketStore.player.innovationIndex" />
                        <KpiGauge label="CAC" :value="marketStore.player.customerAcquisitionCost" unit="$" inverted
                            :decimals="2" />
                    </div>

                    <!-- Sectors -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Sector Performance</h3>
                        </div>
                        <div class="sector-list">
                            <div v-for="sector in marketStore.sectors" :key="sector.key" class="sector-row">
                                <div class="sector-info">
                                    <span class="sector-name">{{ sector.label }}</span>
                                    <span class="sector-meta">Growth: {{ formatPercent(sector.growthRate - 1) }}</span>
                                </div>
                                <div class="sector-bars">
                                    <div class="bar-group">
                                        <label>Demand</label>
                                        <div class="mini-bar-track">
                                            <div class="mini-bar-fill"
                                                :style="{ width: Math.min(100, sector.baseDemand / 2) + '%' }"></div>
                                        </div>
                                    </div>
                                    <div class="bar-group">
                                        <label>Innovation</label>
                                        <div class="mini-bar-track">
                                            <div class="mini-bar-fill innovation"
                                                :style="{ width: Math.min(100, sector.innovation * 20) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMPETITORS TAB -->
            <div v-else-if="activeTab === 'competitors'" class="competitors-layout">
                <div class="competitor-grid">
                    <div v-for="comp in marketStore.competitors" :key="comp.id" class="competitor-card">
                        <div class="comp-header">
                            <div class="comp-ident">
                                <div class="comp-logo" :style="{ background: comp.color_primary }">{{ comp.name[0] }}
                                </div>
                                <div>
                                    <div class="comp-name">{{ comp.name }}</div>
                                    <div class="comp-tagline">{{ comp.tagline }}</div>
                                </div>
                            </div>
                            <div class="comp-share">{{ comp.marketShare.toFixed(1) }}%</div>
                        </div>
                        <div class="comp-stats">
                            <div class="stat">
                                <label>Behavior</label>
                                <span class="behavior-val">{{ formatArchetype(comp.archetype) }}</span>
                            </div>
                            <div class="stat">
                                <label>Price</label>
                                <span>{{ comp.pricing }}</span>
                            </div>
                            <div class="stat">
                                <label>Reputation</label>
                                <span>{{ (comp.reputation || 0).toFixed(0) }}</span>
                            </div>
                        </div>

                        <div class="comp-tech-grid">
                            <div class="mini-metric">
                                <span class="m-label">UPTIME</span>
                                <span class="m-val">{{ (comp.uptimeScore || 99).toFixed(2) }}%</span>
                            </div>
                            <div class="mini-metric">
                                <span class="m-label">LATENCY</span>
                                <span class="m-val">{{ (comp.latencyScore || 20).toFixed(0) }}ms</span>
                            </div>
                            <div class="mini-metric">
                                <span class="m-label">INNOVATION</span>
                                <span class="m-val">{{ (comp.innovationIndex || 0).toFixed(0) }}%</span>
                            </div>
                        </div>

                        <div class="comp-actions" v-if="comp.id !== 'player'">
                            <button class="peering-btn" :disabled="!isEligible(comp.id)"
                                @click="openPeeringNegotiation(comp)">
                                {{ getPeeringLabel(comp.id) }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- USED MARKET TAB -->
            <div v-else-if="activeTab === 'used'" class="used-market-wrapper">
                <UsedMarket />
            </div>

            <div v-else-if="activeTab === 'auctions'" class="auctions-wrapper">
                <HardwareAuctions />
            </div>

        </div>
        <PeeringNegotiationOverlay v-if="selectedPartner" :partner="selectedPartner" @close="selectedPartner = null"
            @success="handlePeeringSuccess" />
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../utils/api';
import { useMarketStore } from '../../stores/useMarketStore';
import MarketShareChart from './MarketShareChart.vue';
import UsedMarket from './UsedMarket.vue';
import HardwareAuctions from './HardwareAuctions.vue';
import KpiGauge from '../Feedback/KpiGauge.vue';
import PeeringNegotiationOverlay from '../Overlay/PeeringNegotiationOverlay.vue';

const props = defineProps({
    initialTab: { type: String, default: 'overview' }
});

const emit = defineEmits(['close']);

const marketStore = useMarketStore();
const activeTab = ref(props.initialTab);
const peeringPartners = ref([]);
const selectedPartner = ref(null);

const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'competitors', label: 'Competitors' },
    { id: 'used', label: 'Used Hardware' },
    { id: 'auctions', label: 'Auctions' },
];

onMounted(() => {
    marketStore.fetchMarketState();
    fetchPeeringPartners();
});

async function fetchPeeringPartners() {
    try {
        const res = await api.get('/network/peering/partners');
        if (res.success) {
            peeringPartners.value = res.data;
        }
    } catch (e) {
        console.error('Failed to load peering partners', e);
    }
}

const isEligible = (id) => {
    const p = peeringPartners.value?.find(p => p.id === id);
    return p ? p.isEligible : false;
};

const getPeeringLabel = (id) => {
    const p = peeringPartners.value?.find(p => p.id === id);
    if (!p) return 'CHECKING...';
    if (!p.isEligible) return 'NOT ELIGIBLE';
    return 'REQUEST PEERING';
};

const openPeeringNegotiation = (comp) => {
    const p = peeringPartners.value?.find(p => p.id === comp.id);
    if (p) {
        selectedPartner.value = { ...p, color: comp.color_primary };
    }
};

const handlePeeringSuccess = () => {
    fetchPeeringPartners();
};

const economyStateClass = computed(() => {
    const s = marketStore.economy.state;
    if (['growth', 'expansion'].includes(s)) return 'state-good';
    if (['recession', 'crisis'].includes(s)) return 'state-bad';
    return 'state-neutral';
});

const marketShareData = computed(() => {
    const data = marketStore.competitors.map(c => ({
        label: c.name,
        value: Number(c.marketShare),
        color: c.color_primary || '#666'
    }));

    if (marketStore.player.globalShare > 0) {
        data.push({
            label: 'You',
            value: Number(marketStore.player.globalShare),
            color: 'var(--ds-primary)'
        });
    }

    return data.sort((a, b) => b.value - a.value);
});

const totalMarketCapLabel = computed(() => {
    return 'TOTAL';
});

const shareTrend = computed(() => {
    // Determine trend based on recent history (mocked for now or use store history)
    return 'stable';
});

const formatPercent = (val) => {
    return (val * 100).toFixed(1) + '%';
};

function formatArchetype(arch) {
    if (!arch) return 'Balanced';
    return arch.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}
</script>

<style scoped>
.market-dashboard {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--ds-bg-base);
    color: var(--ds-text-primary);
}

.market-header {
    padding: var(--ds-space-6);
    background: var(--ds-bg-elevated);
    border-bottom: 1px solid var(--ds-border-subtle);
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-4);
}

.header-left {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.market-title {
    font-size: var(--ds-text-2xl);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.market-status {
    display: flex;
    align-items: center;
    gap: var(--ds-space-2);
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-sm);
    text-transform: uppercase;
    background: var(--ds-bg-subtle);
    padding: 4px 8px;
    border-radius: 4px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ds-text-muted);
}

.state-good {
    background: var(--ds-nominal);
    box-shadow: 0 0 8px var(--ds-nominal-glow);
}

.state-bad {
    background: var(--ds-critical);
    box-shadow: 0 0 8px var(--ds-critical-glow);
}

.state-neutral {
    background: var(--ds-warning);
}

.market-ticker-container {
    background: #000;
    border: 1px solid var(--ds-border-subtle);
    border-radius: var(--ds-radius-sm);
    padding: var(--ds-space-2);
    overflow: hidden;
    white-space: nowrap;
}

.ticker-scroll {
    display: inline-block;
    animation: ticker 30s linear infinite;
    padding-left: 100%;
}

.ticker-item {
    display: inline-block;
    margin-right: var(--ds-space-8);
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-xs);
    color: var(--ds-accent);
}

@keyframes ticker {
    0% {
        transform: translateX(0);
    }

    100% {
        transform: translateX(-100%);
    }
}

.market-tabs {
    display: flex;
    padding: 0 var(--ds-space-6);
    border-bottom: 1px solid var(--ds-border-subtle);
    background: var(--ds-bg-base);
}

.tab-btn {
    padding: var(--ds-space-4) var(--ds-space-6);
    background: transparent;
    border: none;
    color: var(--ds-text-muted);
    font-size: var(--ds-text-sm);
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: var(--ds-text-primary);
}

.tab-btn.active {
    color: var(--ds-primary);
    border-bottom-color: var(--ds-primary);
}

.market-content {
    flex: 1;
    overflow-y: auto;
    padding: var(--ds-space-6);
    position: relative;
}

/* OVERVIEW GRID */
.overview-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: var(--ds-space-6);
    max-width: 1200px;
    margin: 0 auto;
}

.dashboard-card {
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-subtle);
    border-radius: var(--ds-radius-lg);
    padding: var(--ds-space-5);
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-4);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: var(--ds-text-sm);
    font-weight: 700;
    text-transform: uppercase;
    color: var(--ds-text-muted);
}

.card-badge {
    background: var(--ds-critical);
    color: #fff;
    font-size: 9px;
    padding: 2px 4px;
    border-radius: 2px;
    font-weight: 800;
}

.chart-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--ds-space-6);
}

.legend {
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-2);
    width: 100%;
}

.legend-item {
    padding: var(--ds-space-2);
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--ds-radius-sm);
    border-left: 2px solid var(--comp-color);
    transition: all 0.2s;
}

.legend-item:hover {
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(4px);
}

.legend-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.legend-label {
    font-size: var(--ds-text-xs);
    font-weight: 600;
}

.legend-value {
    font-size: var(--ds-text-xs);
    font-family: var(--ds-font-mono);
    font-weight: 700;
}

.legend-bar-track {
    height: 3px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 1px;
    overflow: hidden;
}

.legend-bar-fill {
    height: 100%;
    border-radius: 1px;
    box-shadow: 0 0 4px var(--comp-color);
}

.dashboard-right-col {
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-6);
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--ds-space-4);
}

/* SECTORS */
.sector-list {
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-3);
}

.sector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--ds-space-3);
    background: var(--ds-bg-subtle);
    border-radius: var(--ds-radius-md);
}

.sector-info {
    display: flex;
    flex-direction: column;
}

.sector-name {
    font-weight: 600;
    font-size: var(--ds-text-sm);
}

.sector-meta {
    font-size: var(--ds-text-xs);
    color: var(--ds-text-muted);
}

.sector-bars {
    width: 120px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bar-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.bar-group label {
    font-size: 9px;
    width: 50px;
    text-align: right;
    color: var(--ds-text-ghost);
}

.mini-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.mini-bar-fill {
    height: 100%;
    background: var(--ds-primary);
    border-radius: 2px;
}

.mini-bar-fill.innovation {
    background: var(--ds-accent);
}

/* COMPETITORS */
.competitor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--ds-space-4);
}

.competitor-card {
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-subtle);
    border-radius: var(--ds-radius-md);
    padding: var(--ds-space-4);
}

.comp-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--ds-space-4);
}

.comp-ident {
    display: flex;
    gap: var(--ds-space-3);
}

.comp-logo {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #fff;
    font-size: 14px;
}

.comp-name {
    font-weight: 700;
    font-size: var(--ds-text-sm);
}

.comp-tagline {
    font-size: var(--ds-text-xs);
    color: var(--ds-text-muted);
}

.comp-share {
    font-family: var(--ds-font-mono);
    font-weight: 700;
    font-size: var(--ds-text-lg);
}

.comp-stats {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid var(--ds-border-subtle);
    padding-top: var(--ds-space-3);
}

.stat {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat label {
    font-size: 9px;
    color: var(--ds-text-muted);
    text-transform: uppercase;
}

.stat span {
    font-size: var(--ds-text-xs);
    font-weight: 600;
}

.behavior-val {
    color: var(--ds-primary);
    font-style: italic;
}

.comp-tech-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    padding: 8px;
    margin-top: 10px;
    gap: 8px;
}

.mini-metric {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.m-label {
    font-size: 8px;
    color: var(--ds-text-ghost);
    font-family: var(--ds-font-mono);
}

.m-val {
    font-size: 10px;
    font-weight: 700;
}

.comp-actions {
    margin-top: 15px;
    border-top: 1px solid var(--ds-border-subtle);
    padding-top: 15px;
}

.peering-btn {
    width: 100%;
    padding: 8px;
    background: var(--ds-primary);
    color: #000;
    border: none;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.peering-btn:hover:not(:disabled) {
    background: var(--ds-primary-glow);
    transform: translateY(-1px);
}

.peering-btn:disabled {
    background: var(--ds-bg-subtle);
    color: var(--ds-text-ghost);
    cursor: not-allowed;
    border: 1px dashed var(--ds-border-subtle);
}

.close-btn {
    background: none;
    border: none;
    color: var(--ds-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    padding: 8px;
    transition: all 0.2s;
    margin-left: var(--ds-space-4);
}

.close-btn:hover {
    color: #fff;
    transform: rotate(90deg);
}
</style>
