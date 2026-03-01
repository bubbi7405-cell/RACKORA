<template>
    <div class="v2-main-viewport world-v2">
        <header class="v2-content-header">
            <div class="v2-breadcrumb">
                <span class="v2-path">GLOBAL_OPERATIONS</span>
                <span class="v2-sep">//</span>
                <span class="v2-node">{{ activeTab.toUpperCase() }}</span>
            </div>
            
            <div class="v2-room-tabs">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="v2-room-tab"
                    :class="{ 'is-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>
        </header>

        <div class="v2-content-scroll">
            <transition name="v2-fade" mode="out-in">
                <div :key="activeTab">
                    <!-- Global Leaderboard -->
                    <div v-if="activeTab === 'leaderboard'" class="v2-tab-pane">
                        <div class="v2-card">
                            <div class="v2-title">RANKING_AUTHORITY</div>
                            <LeaderboardOverlay inline />
                        </div>
                    </div>

                    <!-- Market Intel -->
                    <div v-else-if="activeTab === 'market'" class="v2-tab-pane">
                        <div class="v2-stats-row">
                            <MarketHeatmap :market-data="marketSharedData" style="flex: 1" />
                            <div class="v2-card market-stats" style="flex: 1">
                                <div class="v2-title">MARKET_DISTRIBUTION</div>
                                <div class="v2-market-chart">
                                    <div 
                                        v-for="p in (marketSharedData?.participants || [])" 
                                        :key="p.name"
                                        class="v2-market-bar"
                                        :style="{ height: (p.marketShare * 2) + 'px', background: p.color }"
                                    >
                                        <div class="v2-bar-label">{{ Math.round(p.marketShare) }}%</div>
                                        <div class="v2-bar-name">{{ p.name.split(' ')[0] }}</div>
                                    </div>
                                    <div v-if="marketSharedData?.player" class="v2-market-bar player" :style="{ height: (marketSharedData.player.marketShare * 2) + 'px' }">
                                        <div class="v2-bar-label">{{ Math.round(marketSharedData.player.marketShare) }}%</div>
                                        <div class="v2-bar-name">YOU</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="v2-section">
                            <div class="v2-title">ACTIVE_COMPETITOR_INTEL</div>
                            <div class="v2-intel-grid">
                                <div v-for="npc in (marketSharedData?.participants || [])" :key="npc.id" class="v2-card npc-card">
                                    <div class="npc-header">
                                        <div class="v2-status-dot" :class="{ 'is-online': npc.status === 'active' }"></div>
                                        <span class="npc-name">{{ npc.name }}</span>
                                        <span class="npc-personality" :class="npc.personality">{{ npc.personality }}</span>
                                    </div>
                                    <div class="npc-tagline">{{ npc.tagline }}</div>
                                    <div class="npc-stats-grid">
                                        <div class="npc-stat">
                                            <span class="v2-label">SHARE</span>
                                            <span class="npc-val">{{ npc.marketShare }}%</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">SECTOR</span>
                                            <span class="npc-val">{{ npc.focus.toUpperCase() }}</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">INTEL</span>
                                            <span class="npc-val">{{ npc.intelligence }}</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">RISK</span>
                                            <div class="risk-gauge">
                                                <div class="risk-fill" :style="{ width: npc.aggression + '%', background: getRiskColor(npc.aggression) }"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="v2-card-footer">
                                        HQ: {{ npc.hqRegion }} // STRAT: {{ npc.pricing }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- World Events -->
                    <div v-else-if="activeTab === 'events'" class="v2-tab-pane">
                        <div class="v2-title">AKTIVE_EREIGNISSE</div>
                        <div class="v2-intel-grid" v-if="activeWorldEvents.length > 0">
                            <div v-for="event in activeWorldEvents" :key="event.id" class="v2-card event-live-card" :class="event.type">
                                <div class="event-live-header">
                                    <span class="event-type-badge" :class="event.type">
                                        {{ event.type === 'crisis' ? '⚠️' : event.type === 'boom' ? '🚀' : 'ℹ️' }}
                                        {{ event.type.toUpperCase() }}
                                    </span>
                                    <span class="event-scope" :class="{ global: event.is_global }">
                                        <template v-if="event.is_global">🌍 GLOBAL</template>
                                        <template v-else>
                                            📍 <span v-for="r in event.affected_regions" :key="r" class="region-tag">{{ r.toUpperCase() }}</span>
                                        </template>
                                    </span>
                                </div>
                                <div class="event-live-title">{{ event.title }}</div>
                                <div class="event-live-desc">{{ event.description }}</div>
                                <div class="event-live-footer">
                                    <span class="modifier-info">
                                        {{ event.modifier_type?.toUpperCase() }}: 
                                        <span :class="event.modifier_value > 0 ? 'mod-positive' : 'mod-negative'">
                                            {{ event.modifier_value > 0 ? '+' : '' }}{{ Math.round(event.modifier_value * 100) }}%
                                        </span>
                                    </span>
                                    <span class="time-remaining" v-if="event.remaining_minutes != null">
                                        ⏱ {{ event.remaining_minutes }} Min.
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="v2-empty-state" style="padding: 40px;">KEINE_AKTIVEN_EREIGNISSE</div>

                        <div class="v2-title" style="margin-top: 30px;">ARCHIV</div>
                        <div class="v2-table">
                            <div class="v2-table-header">
                                <span class="v2-th">ZEITSTEMPEL</span>
                                <span class="v2-th">EREIGNIS</span>
                                <span class="v2-th">AUSWIRKUNG</span>
                            </div>
                            <div v-for="event in eventHistory" :key="event.id" class="v2-table-row">
                                <span class="v2-td text-mono">{{ formatDate(event.ends_at) }}</span>
                                <span class="v2-td font-bold">{{ event.title }}</span>
                                <span class="v2-td text-dim">{{ event.description }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Regional Analytics -->
                    <div v-else-if="activeTab === 'regions'" class="v2-tab-pane">
                        <div class="v2-intel-grid">
                            <div v-for="(data, key) in gameStore.regions" :key="key" class="v2-card region-card" :class="{ 'is-locked': !isRegionUnlocked(data) }">
                                <div class="region-header">
                                    <span class="region-flag">{{ data.flag }}</span>
                                    <div class="region-meta">
                                        <div class="region-name">{{ data.name }}</div>
                                        <div class="region-slug text-mono">{{ key.toUpperCase() }}</div>
                                    </div>
                                    <span v-if="!isRegionUnlocked(data)" class="lock-badge">🔒 LVL {{ data.level_required }}</span>
                                </div>
                                <div class="region-desc">{{ data.description }}</div>
                                
                                <!-- LIVE TELEMETRIE -->
                                <div class="region-live-strip">
                                    <!-- Energie-Spot -->
                                    <div class="live-metric">
                                        <span class="live-label">⚡ SPOT_PREIS</span>
                                        <span class="live-value" :class="getEnergyPriceClass(key)">
                                            ${{ getRegionalPrice(key) }}/kWh
                                        </span>
                                    </div>
                                    <!-- Wetter -->
                                    <div class="live-metric">
                                        <span class="live-label">🌤️ WETTER</span>
                                        <span class="live-value weather-tag" :class="'weather-' + getWeatherType(key)">
                                            {{ getWeatherIcon(key) }} {{ getWeatherLabel(key) }}
                                        </span>
                                    </div>
                                    <!-- Solar -->
                                    <div class="live-metric">
                                        <span class="live-label">☀️ SOLAR</span>
                                        <span class="live-value">{{ getSolarPercent(key) }}%</span>
                                        <div class="solar-bar">
                                            <div class="solar-fill" :style="{ width: getSolarPercent(key) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Wetter-Auswirkung -->
                                <div class="weather-effect" v-if="getWeatherEffect(key)">
                                    <span class="effect-icon">{{ getWeatherEffectIcon(key) }}</span>
                                    <span class="effect-text">{{ getWeatherEffect(key) }}</span>
                                </div>

                                <div class="region-stats">
                                    <div class="stat-item">
                                        <label>ENERGIE_BASIS</label>
                                        <span class="stat-value">${{ data.base_power_cost }}/kWh</span>
                                        <div class="stat-bar"><div class="stat-fill" :style="{ width: (data.base_power_cost * 400) + '%', background: '#3b82f6' }"></div></div>
                                    </div>
                                    <div class="stat-item">
                                        <label>STEUERSATZ</label>
                                        <span class="stat-value">{{ Math.round(data.tax_rate * 100) }}%</span>
                                        <div class="stat-bar"><div class="stat-fill" :style="{ width: (data.tax_rate * 400) + '%', background: '#ef4444' }"></div></div>
                                    </div>
                                    <div class="stat-item">
                                        <label>CO2_ABGABE</label>
                                        <span class="stat-value">${{ data.carbon_tax_per_kw || 0 }}/kW</span>
                                    </div>
                                    <div class="stat-item">
                                        <label>ENTERPRISE_QUOTE</label>
                                        <span class="stat-value">{{ Math.round((data.preferences?.enterprise_ratio || 0) * 100) }}%</span>
                                    </div>
                                </div>

                                <!-- Aktive Events für Region -->
                                <div class="region-active-events" v-if="getRegionEvents(key).length">
                                    <div class="v2-title small">AKTIVE_EVENTS</div>
                                    <div v-for="ev in getRegionEvents(key)" :key="ev.id" class="region-event-chip" :class="ev.type">
                                        {{ ev.type === 'crisis' ? '⚠️' : '🚀' }} {{ ev.title }}
                                    </div>
                                </div>

                                <div class="region-preferences">
                                    <div class="v2-title small">MARKT_PROFIL</div>
                                    <div class="bias-tags">
                                        <span v-if="data.preferences?.is_privacy_focused" class="v2-badge bg-blue-900">🛡️ DSGVO</span>
                                        <span v-if="data.preferences?.is_performance_focused" class="v2-badge bg-orange-900">⚡ PERFORMANCE</span>
                                        <span v-if="data.preferences?.is_eco_focused" class="v2-badge bg-green-900">🌿 ÖKO</span>
                                        <span v-if="data.preferences?.cpu_focus && data.preferences.cpu_focus !== false" class="v2-badge bg-slate-800">CPU×{{ data.preferences.cpu_focus }}</span>
                                        <span v-if="data.preferences?.ram_focus && data.preferences.ram_focus !== false" class="v2-badge bg-slate-800">RAM×{{ data.preferences.ram_focus }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Capacity Exchange (Multiplayer Rentals) -->
                    <div v-else-if="activeTab === 'exchange'" class="v2-tab-pane">
                        <div class="v2-stats-row">
                            <div class="v2-card">
                                <div class="v2-title">ACTIVE_RENTAL_CONTRACTS</div>
                                <div class="v2-table" style="min-height: 100px;">
                                    <div class="v2-table-header">
                                        <span class="v2-th">ROLE</span>
                                        <span class="v2-th">PARTNER</span>
                                        <span class="v2-th">UNIT</span>
                                        <span class="v2-th">RATE</span>
                                        <span class="v2-th">ACTION</span>
                                    </div>
                                    <div v-for="rent in multiplayerStore.myRentalsAsTenant" :key="rent.id" class="v2-table-row">
                                        <span class="v2-td"><span class="v2-badge bg-blue-900">TENANT</span></span>
                                        <span class="v2-td">{{ rent.provider?.company_name || 'System' }}</span>
                                        <span class="v2-td text-mono">{{ rent.server?.model_name }}</span>
                                        <span class="v2-td">${{ rent.price_per_hour }}/h</span>
                                        <span class="v2-td">
                                            <button @click="multiplayerStore.terminateRental(rent.id)" class="v2-action-btn danger small">TERMINATE</button>
                                        </span>
                                    </div>
                                    <div v-for="rent in multiplayerStore.myRentalsAsProvider" :key="rent.id" class="v2-table-row">
                                        <span class="v2-td"><span class="v2-badge bg-green-900">PROVIDER</span></span>
                                        <span class="v2-td">{{ rent.tenant?.company_name || 'Individual' }}</span>
                                        <span class="v2-td text-mono">{{ rent.server?.model_name }}</span>
                                        <span class="v2-td">${{ rent.price_per_hour }}/h</span>
                                        <span class="v2-td">
                                            <button @click="multiplayerStore.terminateRental(rent.id)" class="v2-action-btn danger small">RECALL</button>
                                        </span>
                                    </div>
                                    <div v-if="multiplayerStore.myRentalsAsTenant.length === 0 && multiplayerStore.myRentalsAsProvider.length === 0" class="v2-empty-state">
                                        NO_ACTIVE_CONTRACTS
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="v2-section">
                            <div class="v2-title">CAPACITY_MARKETPLACE</div>
                            
                            <!-- Filters -->
                            <div class="v2-filters-row">
                                <div class="filter-group">
                                    <label>MIN_CORES</label>
                                    <input type="number" v-model="filters.minCores" class="v2-input-small" placeholder="0">
                                </div>
                                <div class="filter-group">
                                    <label>MIN_RAM (GB)</label>
                                    <input type="number" v-model="filters.minRam" class="v2-input-small" placeholder="0">
                                </div>
                                <div class="filter-group">
                                    <label>MAX_RATE ($/h)</label>
                                    <input type="number" v-model="filters.maxPrice" class="v2-input-small" placeholder="Any">
                                </div>
                                <div class="filter-group">
                                    <label>SORT_BY</label>
                                    <select v-model="filters.sortBy" class="v2-select-small">
                                        <option value="price_asc">PRICE_LOW</option>
                                        <option value="price_desc">PRICE_HIGH</option>
                                        <option value="perf_desc">PERFORMANCE</option>
                                        <option value="trust_desc">TRUST_SCORE</option>
                                    </select>
                                </div>
                            </div>

                            <div class="v2-intel-grid">
                                <div v-for="offer in filteredRentals" :key="offer.id" class="v2-card npc-card">
                                    <div class="npc-header">
                                        <div class="v2-status-dot is-online"></div>
                                        <span class="npc-name">{{ offer.provider.name }}</span>
                                        <div class="trust-score" :title="'Reliability Score: ' + Math.round(offer.provider.reputation) + '%'">
                                            <div class="trust-fill" :style="{ width: offer.provider.reputation + '%', background: getTrustColor(offer.provider.reputation) }"></div>
                                        </div>
                                    </div>
                                    <div class="npc-tagline">Offering dedicated {{ offer.server.model }} capacity.</div>
                                    <div class="npc-stats-grid">
                                        <div class="npc-stat">
                                            <span class="v2-label">CORES</span>
                                            <span class="npc-val">{{ offer.server.cores }}</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">RAM</span>
                                            <span class="npc-val">{{ offer.server.ram }}GB</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">BW</span>
                                            <span class="npc-val">{{ offer.server.bandwidth }}M</span>
                                        </div>
                                        <div class="npc-stat">
                                            <span class="v2-label">GEN</span>
                                            <span class="npc-val">v{{ offer.server.generation }}</span>
                                        </div>
                                    </div>
                                    <div class="v2-card-footer" style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>RATE: <span class="text-white">${{ offer.pricePerHour }}/h</span></span>
                                        <button @click="multiplayerStore.rentServer(offer.id)" class="v2-action-btn small">SIGN_CONTRACT</button>
                                    </div>
                                </div>
                                <div v-if="filteredRentals.length === 0" class="v2-empty-state" style="grid-column: span 3;">
                                    MARKET_LIQUIDITY_LOW_NO_OFFERS
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useMultiplayerStore } from '../../stores/multiplayer';
import { storeToRefs } from 'pinia';
import LeaderboardOverlay from '../Overlay/LeaderboardOverlay.vue';
import MarketHeatmap from './MarketHeatmap.vue';

const gameStore = useGameStore();
const multiplayerStore = useMultiplayerStore();

const filters = ref({
    minCores: 0,
    minRam: 0,
    maxPrice: null,
    sortBy: 'price_asc'
});

const filteredRentals = computed(() => {
    let list = [...multiplayerStore.availableRentals];

    if (filters.value.minCores > 0) {
        list = list.filter(r => r.server.cores >= filters.value.minCores);
    }
    if (filters.value.minRam > 0) {
        list = list.filter(r => r.server.ram >= filters.value.minRam);
    }
    if (filters.value.maxPrice > 0) {
        list = list.filter(r => r.pricePerHour <= filters.value.maxPrice);
    }

    list.sort((a, b) => {
        if (filters.value.sortBy === 'price_asc') return a.pricePerHour - b.pricePerHour;
        if (filters.value.sortBy === 'price_desc') return b.pricePerHour - a.pricePerHour;
        if (filters.value.sortBy === 'perf_desc') return b.server.cores - a.server.cores;
        if (filters.value.sortBy === 'trust_desc') return b.provider.reputation - a.provider.reputation;
        return 0;
    });

    return list;
});

// Replace storeToRefs with direct computed properties
const marketShare = computed(() => gameStore.marketShare);
const worldEvents = computed(() => gameStore.worldEvents);

const activeTab = ref('market');

const tabs = [
    { id: 'market', label: 'MARKET_INTEL' },
    { id: 'regions', label: 'REGIONAL_ANALYTICS' },
    { id: 'exchange', label: 'CAPACITY_EXCHANGE' },
    { id: 'leaderboard', label: 'LEADERBOARD' },
    { id: 'events', label: 'WORLD_EVENTS' },
];

const eventHistory = computed(() => {
    if (!worldEvents.value || !worldEvents.value.history) return [];
    return worldEvents.value.history;
});

const activeWorldEvents = computed(() => {
    if (!worldEvents.value || !worldEvents.value.active) return [];
    return worldEvents.value.active;
});

const isRegionUnlocked = (regionData) => {
    const playerLevel = gameStore.economy?.level || 1;
    return playerLevel >= (regionData.level_required || 1);
};

// Provide fallback for missing marketShare in store
const marketSharedData = computed(() => gameStore.marketShare || { participants: [], player: null });
const formatDate = (date) => {
    if (!date) return '---';
    const d = new Date(date);
    return isNaN(d.getTime()) ? '---' : d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const getTrustColor = (reputation) => {
    if (reputation > 90) return 'var(--v2-success)';
    if (reputation > 70) return 'var(--v2-warning)';
    return 'var(--v2-danger)';
};

const getRiskColor = (aggression) => {
    if (aggression > 80) return 'var(--v2-danger)';
    if (aggression > 50) return 'var(--v2-warning)';
    return 'var(--v2-success)';
};

// ─── REGIONAL LIVE DATA ──────────────────────────────

const getRegionalPrice = (regionKey) => {
    const prices = gameStore.energy?.regional_prices || {};
    return (prices[regionKey] || gameStore.energy?.spotPrice || 0.12).toFixed(4);
};

const getEnergyPriceClass = (regionKey) => {
    const price = parseFloat(getRegionalPrice(regionKey));
    if (price > 0.30) return 'price-critical';
    if (price > 0.18) return 'price-high';
    if (price < 0.08) return 'price-low';
    return 'price-normal';
};

const weatherMap = {
    clear: { icon: '☀️', label: 'Klar', effect: null, effectIcon: null },
    cloudy: { icon: '☁️', label: 'Bewölkt', effect: 'Solar -60%, Netzstabilität OK', effectIcon: '🌥️' },
    heatwave: { icon: '🔥', label: 'Hitzewelle', effect: 'Solar +20%, Kühlung +35% teurer, Netz -10%', effectIcon: '⚠️' },
    storm: { icon: '⛈️', label: 'Sturm', effect: 'Solar -90%, Netz -30% Stabilität!', effectIcon: '🚨' },
    blizzard: { icon: '❄️', label: 'Blizzard', effect: 'Solar -95%, Kühlung -20% günstiger, Netz -40%!', effectIcon: '🚨' },
};

const getWeatherType = (regionKey) => {
    return gameStore.weather?.[regionKey]?.type || 'clear';
};

const getWeatherIcon = (regionKey) => {
    return weatherMap[getWeatherType(regionKey)]?.icon || '☀️';
};

const getWeatherLabel = (regionKey) => {
    return weatherMap[getWeatherType(regionKey)]?.label || 'Unbekannt';
};

const getWeatherEffect = (regionKey) => {
    return weatherMap[getWeatherType(regionKey)]?.effect || null;
};

const getWeatherEffectIcon = (regionKey) => {
    return weatherMap[getWeatherType(regionKey)]?.effectIcon || 'ℹ️';
};

const getSolarPercent = (regionKey) => {
    const solar = gameStore.energy?.regional_solar || {};
    return Math.round((solar[regionKey] || 0) * 100);
};

const getRegionEvents = (regionKey) => {
    if (!activeWorldEvents.value) return [];
    return activeWorldEvents.value.filter(ev => {
        if (ev.is_global) return true;
        const regions = ev.affected_regions || [];
        return regions.includes(regionKey);
    });
};

onMounted(() => {
    multiplayerStore.loadAvailableRentals();
    multiplayerStore.loadMyRentals();
});
</script>

<style scoped>
.world-view { display: flex; flex-direction: column; height: 100%; }

.view-header {
    padding: var(--space-xl) var(--space-2xl) 0;
    background: #0d1117;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.view-title { font-size: 1.5rem; font-weight: 700; margin-bottom: var(--space-lg); }

.view-tabs { display: flex; gap: var(--space-xl); }

.tab-btn {
    padding: var(--space-md) 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--color-text-muted);
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-btn:hover { color: #fff; }
.tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

.view-content { padding: var(--space-2xl); flex: 1; overflow-y: auto; }

.history-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: var(--space-2xl); }
.column-title { font-size: 1rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: var(--space-lg); }

.event-list { display: flex; flex-direction: column; gap: var(--space-md); }

.event-card {
    background: #161b22;
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.event-card.active { border-color: var(--color-primary-dim); box-shadow: 0 0 15px rgba(0, 212, 255, 0.05); }

.event-type { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; padding: 2px 6px; border-radius: 4px; width: fit-content; }
.event-type.boom { background: var(--color-success-dim); color: var(--color-success); }
.event-type.crisis { background: var(--color-danger-dim); color: var(--color-danger); }

.event-name { font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
.event-desc { font-size: 0.85rem; color: var(--color-text-muted); }
.event-meta { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 4px; }

.mock-chart { display: flex; align-items: flex-end; gap: 20px; height: 200px; margin-top: 40px; justify-content: center; }
.bar { width: 60px; border-radius: 4px 4px 0 0; position: relative; }
.bar span { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; white-space: nowrap; }

.empty-state { padding: var(--space-xl); text-align: center; color: var(--color-text-muted); opacity: 0.5; }

.fade-fast-enter-active, .fade-fast-leave-active {
    transition: opacity 0.15s ease;
}
.fade-fast-enter-from, .fade-fast-leave-to {
    opacity: 0;
}

.v2-filters-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    background: #0d1117;
    padding: 15px;
    border: 1px solid #30363d;
    border-radius: 6px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 0.7rem;
    color: #8b949e;
    text-transform: uppercase;
    font-weight: 600;
}

.v2-input-small, .v2-select-small {
    background: #010409;
    border: 1px solid #30363d;
    color: #e6edf3;
    padding: 5px 10px;
    font-family: monospace;
    border-radius: 4px;
    width: 100px;
}

.v2-select-small {
    width: 140px;
}

.trust-score {
    width: 60px;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
    margin-left: auto;
}

.trust-fill {
    height: 100%;
    transition: width 0.3s ease;
}

/* Regional Analytics Styles */
.region-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: transform 0.2s;
}

.region-card:hover {
    transform: translateY(-4px);
}

.region-header {
    display: flex;
    gap: 12px;
    align-items: center;
}

.region-flag {
    font-size: 2rem;
}

.region-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: #fff;
}

.region-slug {
    font-size: 0.65rem;
    color: var(--color-primary);
    letter-spacing: 0.05em;
}

.region-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.4;
}

.region-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 4px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-item label {
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    font-weight: 800;
}

.stat-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
    font-family: var(--font-family-mono);
}

.stat-bar {
    height: 3px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.stat-fill {
    height: 100%;
    border-radius: 2px;
}

.bias-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.bg-orange-900 { background: rgba(249, 115, 22, 0.2); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.2); }
.bg-slate-800 { background: rgba(30, 41, 59, 0.5); color: #94a3b8; border: 1px solid rgba(30, 41, 59, 0.2); }
.bg-green-900 { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }

/* Event Live Cards */
.event-live-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-left: 3px solid rgba(255,255,255,0.1);
    transition: transform 0.2s;
}

.event-live-card.crisis { border-left-color: #f85149; }
.event-live-card.boom { border-left-color: #3fb950; }
.event-live-card.info { border-left-color: #58a6ff; }

.event-live-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.event-type-badge {
    font-size: 0.6rem;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.05em;
}

.event-type-badge.crisis { background: rgba(248, 81, 73, 0.15); color: #f85149; }
.event-type-badge.boom { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
.event-type-badge.info { background: rgba(88, 166, 255, 0.15); color: #58a6ff; }

.event-scope {
    font-size: 0.65rem;
    font-weight: 700;
    color: #8b949e;
}

.event-scope.global { color: #d2a8ff; }

.region-tag {
    background: rgba(255,255,255,0.08);
    padding: 1px 5px;
    border-radius: 3px;
    margin-left: 3px;
    font-size: 0.6rem;
}

.event-live-title {
    font-weight: 700;
    font-size: 1.05rem;
    color: #fff;
}

.event-live-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.55);
    line-height: 1.4;
}

.event-live-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    font-family: var(--font-family-mono);
    color: #8b949e;
    padding-top: 8px;
    border-top: 1px solid rgba(255,255,255,0.05);
}

.modifier-info { font-weight: 600; }
.mod-positive { color: #3fb950; }
.mod-negative { color: #f85149; }

.time-remaining {
    color: #d29922;
    font-weight: 600;
}

/* Region Locked */
.region-card.is-locked {
    opacity: 0.45;
    pointer-events: none;
    filter: grayscale(0.5);
}

.lock-badge {
    margin-left: auto;
    font-size: 0.65rem;
    font-weight: 800;
    background: rgba(255,255,255,0.08);
    color: #d29922;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid rgba(210, 169, 34, 0.2);
}

/* ── Regional Live Telemetry Strip ─────────────────── */
.region-live-strip {
    display: flex;
    gap: 16px;
    padding: 10px 12px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 6px;
}

.live-metric {
    display: flex;
    flex-direction: column;
    gap: 3px;
    flex: 1;
}

.live-label {
    font-size: 0.55rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.35);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.live-value {
    font-size: 0.85rem;
    font-weight: 700;
    font-family: var(--font-family-mono);
    color: #e6edf3;
}

.price-normal { color: #8b949e; }
.price-low { color: #3fb950; }
.price-high { color: #d29922; }
.price-critical { color: #f85149; text-shadow: 0 0 6px rgba(248, 81, 73, 0.4); }

.weather-tag { font-size: 0.8rem; }
.weather-clear .live-value { color: #fbbf24; }
.weather-cloudy { color: #9ca3af; }
.weather-heatwave { color: #f97316; }
.weather-storm { color: #60a5fa; }
.weather-blizzard { color: #93c5fd; }

.solar-bar {
    height: 3px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 3px;
}

.solar-fill {
    height: 100%;
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
    border-radius: 2px;
    transition: width 0.5s ease;
}

/* Weather Effect Banner */
.weather-effect {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: rgba(210, 169, 34, 0.08);
    border: 1px solid rgba(210, 169, 34, 0.15);
    border-radius: 4px;
    font-size: 0.7rem;
    color: #d29922;
}

.effect-icon { font-size: 0.85rem; }
.effect-text { font-family: var(--font-family-mono); letter-spacing: 0.02em; }

/* Regional Active Events */
.region-active-events {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.region-event-chip {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 4px;
    border-left: 2px solid;
    background: rgba(255, 255, 255, 0.03);
}

.region-event-chip.crisis {
    border-left-color: #f85149;
    color: #f85149;
}

.region-event-chip.boom {
    border-left-color: #3fb950;
    color: #3fb950;
}

.region-event-chip.info {
    border-left-color: #58a6ff;
    color: #58a6ff;
}
</style>
