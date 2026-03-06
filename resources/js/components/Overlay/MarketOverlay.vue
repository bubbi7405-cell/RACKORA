<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="market-overlay glass-panel animate-fade-in">
            <header class="overlay-header">
                <div class="header-content">
                    <h2>Market Intelligence</h2>
                    <p>Global hosting market share and competitive analysis.</p>
                </div>
                <button class="close-button" @click="$emit('close')">&times;</button>
            </header>

            <div class="overlay-nav">
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'intelligence' }" 
                    @click="activeTab = 'intelligence'"
                >
                    INTEL
                </button>
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'sabotage' }" 
                    @click="activeTab = 'sabotage'"
                >
                    OPERATIONS
                </button>
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'auctions' }" 
                    @click="activeTab = 'auctions'"
                >
                    AUCTIONS
                </button>
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'hardware' }" 
                    @click="activeTab = 'hardware'"
                >
                    USED_HARDWARE
                </button>
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'liquidate' }" 
                    @click="activeTab = 'liquidate'"
                >
                    LIQUIDATE
                </button>
                <button 
                    class="nav-tab" 
                    :class="{ active: activeTab === 'exclusivity' }" 
                    @click="activeTab = 'exclusivity'"
                >
                    EXCLUSIVITY
                </button>
                <button 
                    class="nav-tab night-shop-tab" 
                    :class="{ active: activeTab === 'blackmarket', 'is-night': isNight }" 
                    @click="activeTab = 'blackmarket'"
                >
                    <span class="icon">🌙</span> NIGHT_SHOP
                </button>

                <div class="region-tabs" v-if="activeTab === 'intelligence'">
                    <button 
                        class="nav-tab sub-tab" 
                        :class="{ active: activeRegion === null }" 
                        @click="setRegion(null)"
                    >
                        Global
                    </button>
                    <button 
                        v-for="(r, key) in marketData.availableRegions" 
                        :key="key"
                        class="nav-tab sub-tab" 
                        :class="{ active: activeRegion === key }"
                        @click="setRegion(key)"
                    >
                        {{ r.name }}
                    </button>
                </div>
            </div>

            <div class="overlay-body">
                <SabotagePanel 
                    v-if="activeTab === 'sabotage'" 
                    :competitors="sortedParticipants.filter(p => !p.isPlayer)" 
                />

                <div v-else-if="loading && activeTab === 'intelligence'" class="loading-state">
                    <div class="spinner"></div>
                    <p>Scanning global networks...</p>
                </div>

                <div v-else-if="activeTab === 'intelligence'" class="market-content">
                    <!-- Market Share Chart Section -->
                    <section class="market-share-section">
                        <h3>Domain Dominance</h3>
                        <div class="chart-container">
                            <MarketShareChart 
                                :segments="chartSegmentsGrouped" 
                                :size="240" 
                                :thickness="40"
                                show-total
                                total-label="TOTAL"
                            />

                            <div class="market-legend">
                                <div v-for="item in chartSegmentsGrouped" :key="item.label" class="legend-item" :style="{ '--comp-color': item.color }">
                                    <div class="legend-header">
                                        <span class="legend-label">{{ item.label }}</span>
                                        <span class="legend-value">{{ item.value.toFixed(1) }}%</span>
                                    </div>
                                    <div class="legend-bar-track">
                                        <div class="legend-bar-fill" :style="{ width: item.value + '%', background: item.color }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Competitor List Section -->
                    <section class="competitors-list-section">
                        <h3>Primary Competitors</h3>
                        <div class="competitors-grid">
                            <div v-for="(c, index) in sortedParticipants" :key="c.id" class="competitor-card" :class="{ player: c.isPlayer }">
                                <div class="card-header" :style="{ borderLeftColor: c.color }">
                                    <div class="name-box">
                                        <h4>{{ c.name }}</h4>
                                        <span class="hq-region" v-if="c.hqRegion">
                                            HQ: {{ marketData.availableRegions[c.hqRegion]?.name }}
                                        </span>
                                    </div>
                                    <div class="rank-badge">#{{ index + 1 }}</div>
                                </div>
                                <p class="tagline">{{ c.tagline }}</p>
                                <div class="stats">
                                    <div class="stat">
                                        <label>Pricing</label>
                                        <span>{{ c.pricing }}</span>
                                    </div>
                                    <div class="stat">
                                        <label>Behavior</label>
                                        <span class="archetype-label">{{ formatArchetype(c.archetype) }}</span>
                                    </div>
                                    <div class="stat">
                                        <label>Market Share</label>
                                        <span class="share-value" :style="{ color: c.color }">{{ c.marketShare }}%</span>
                                    </div>
                                </div>
                                <div class="tech-metrics" v-if="!c.isPlayer">
                                    <div class="metric">
                                        <span class="m-label">UPTIME</span>
                                        <span class="m-value">{{ (c.uptimeScore || 0).toFixed(2) }}%</span>
                                    </div>
                                    <div class="metric">
                                        <span class="m-label">LATENCY</span>
                                        <span class="m-value">{{ (c.latencyScore || 0).toFixed(0) }}ms</span>
                                    </div>
                                    <div class="metric">
                                        <span class="m-label">INNOVATION</span>
                                        <span class="m-value">{{ (c.innovationIndex || 0).toFixed(0) }}%</span>
                                    </div>
                                </div>
                                <div class="reputation-container">
                                    <label>Brand Reputation</label>
                                    <div class="reputation-bar">
                                        <div class="fill" :style="{ width: c.reputation + '%', background: c.color }"></div>
                                    </div>
                                </div>
                                <div class="card-actions" v-if="!c.isPlayer">
                                    <button 
                                        class="peering-btn" 
                                        :disabled="!isEligible(c.id)"
                                        @click="openPeeringNegotiation(c)"
                                    >
                                        {{ getPeeringLabel(c.id) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else-if="['auctions', 'hardware', 'liquidate', 'exclusivity'].includes(activeTab)" class="secondary-market-view animate-fade-in">
                    <!-- Auctions Section -->
                    <div v-if="activeTab === 'auctions'" class="auctions-panel">
                        <div v-if="marketStore.auctions.length === 0" class="empty-state">
                            <div class="empty-icon">⚖️</div>
                            <p>No active liquidations found. Government agencies and bankrupt firms hold these sporadically.</p>
                        </div>
                        <div v-else class="auction-grid">
                            <div v-for="auction in marketStore.auctions" :key="auction.id" class="auction-card glass-panel" :class="{ 'high-bid': auction.highest_bidder_id === user?.id }">
                                <div class="auction-badge">{{ auction.item_type.toUpperCase() }}</div>
                                <div class="auction-timer" :class="{ critical: auction.time_remaining_seconds < 60 }">
                                    <span class="timer-label">CLOSING_IN:</span>
                                    <span class="timer-value">{{ formatTimeRemaining(auction.time_remaining_seconds) }}</span>
                                </div>
                                <h3 class="item-name">{{ auction.item_specs?.name || auction.item_key }}</h3>
                                <p class="seller">Origin: {{ auction.seller_name }}</p>
                                
                                <div class="specs-mini">
                                    <div class="spec">
                                        <label>INTEGRITY</label>
                                        <span :class="getConditionClass(auction.condition)">{{ auction.condition }}%</span>
                                    </div>
                                    <div class="spec" v-if="auction.defect_chance > 5">
                                        <label>FAULT_RISK</label>
                                        <span class="text-danger">{{ auction.defect_chance }}%</span>
                                    </div>
                                </div>

                                <div class="price-box">
                                    <div class="current">
                                        <label>CURRENT_BID</label>
                                        <span class="value">${{ (auction.current_bid || 0).toLocaleString() }}</span>
                                    </div>
                                    <div class="next-inc">
                                        <label>MIN_INCREMENT</label>
                                        <span class="value">+$50</span>
                                    </div>
                                </div>

                                <div class="bid-actions" v-if="auction.highest_bidder_id !== user?.id">
                                    <button class="bid-btn" @click="handleBid(auction)">ENTER_BID</button>
                                </div>
                                <div class="bid-status winner" v-else>
                                    ✓ YOU ARE CURRENTLY HIGHEST BIDDER
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Used Hardware Section -->
                    <div v-if="activeTab === 'hardware'" class="listings-panel">
                        <div v-if="marketStore.usedListings.length === 0" class="empty-state">
                            <div class="empty-icon">📦</div>
                            <p>Secondary market inventory depleted. Hardware supply chains are currently stable.</p>
                        </div>
                        <div v-else class="listings-grid">
                             <div v-for="listing in marketStore.usedListings" :key="listing.id" class="listing-card glass-panel">
                                <div class="listing-header">
                                    <span class="type-tag">{{ listing.item_type.toUpperCase() }}</span>
                                    <div class="condition-group">
                                        <label>HEALTH</label>
                                        <div class="condition-meter">
                                            <div class="fill" :style="{ width: listing.condition + '%', background: getConditionColor(listing.condition) }"></div>
                                        </div>
                                    </div>
                                </div>
                                <h3>{{ listing.item_key }}</h3>
                                <p class="seller-info">Refurbished by {{ listing.seller_name }}</p>
                                
                                <div class="price-tag">${{ (listing.price || 0).toLocaleString() }}</div>
                                
                                <button class="buy-btn" :disabled="processing" @click="handleBuyUsed(listing)">
                                    INSTANT_PURCHASE
                                </button>
                             </div>
                        </div>
                    </div>

                    <!-- Liquidation / Selling Section -->
                    <div v-if="activeTab === 'liquidate'" class="liquidation-panel animate-slide-up">
                        <div class="market-ticker">
                            <div class="ticker-item" v-for="(val, type) in trendsExclTime" :key="type">
                                <span class="type">{{ type.toUpperCase() }}</span>
                                <span class="trend" :class="getTrendClass(val)">
                                    {{ val > 1.0 ? '▲' : '▼' }} {{ Math.abs((val - 1) * 100).toFixed(0) }}%
                                </span>
                            </div>
                        </div>

                        <div class="inventory-to-sell">
                            <div v-if="inventoryToSell.length === 0" class="empty-state">
                                <p>Inventory clear. No unassigned components to liquidate.</p>
                            </div>
                            <div v-else class="sell-grid">
                                <div v-for="item in inventoryToSell" :key="item.id" class="sell-card">
                                    <div class="item-icon">📦</div>
                                    <div class="item-info">
                                        <h4>{{ item.name }} <span v-if="item.isLeased" class="lease-tag">GELEAST</span></h4>
                                        <p>Condition: {{ Math.round(item.health) }}% | Base: ${{ item.config.price.toLocaleString() }}</p>
                                    </div>
                                    <div class="item-price">
                                        <label>MARKET_VALUE</label>
                                        <span class="value">${{ calculateResale(item).toLocaleString() }}</span>
                                    </div>
                                    <div class="sell-actions">
                                        <template v-if="item.isLeased">
                                            <button class="buyout-btn" @click="handleBuyout(item)">BUYOUT (${{ Math.round(item.config.price * 0.75).toLocaleString() }})</button>
                                            <button class="return-btn" @click="handleReturn(item)">RETURN</button>
                                        </template>
                                        <template v-else>
                                            <button v-if="item.needsShredding" class="shred-btn" @click="handleShred(item)">SECURE_SHRED ($50)</button>
                                            <button v-else class="sell-action-btn" @click="handleSell(item)">LIQUIDATE</button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exclusivity Deals Section -->
                    <div v-if="activeTab === 'exclusivity'" class="deals-panel animate-slide-up">
                        <div class="deals-hero">
                            <div class="hero-icon">🤝</div>
                            <div class="hero-text">
                                <h3>Brand Exclusivity Deals</h3>
                                <p>Partner with a hardware vendor for extreme discounts. But be warned: you cannot source parts from any other brand while the contract is active.</p>
                            </div>
                        </div>

                        <div v-if="infraStore.brandDeals.current" class="active-deal-card glass-panel">
                            <div class="active-badge">ACTIVE CONTRACT</div>
                            <div class="deal-info">
                                <h4>{{ infraStore.brandDeals.current.brand_name }}</h4>
                                <p>Discount: {{ infraStore.brandDeals.current.discount_percent }}% on all gear</p>
                                <p class="expiry">Expires in: {{ formatDealExpiry(infraStore.brandDeals.current.expires_at) }}</p>
                            </div>
                            <button class="terminate-btn" @click="handleTerminateDeal">TERMINATE_CONTRACT ($2,500 Penalty)</button>
                        </div>

                        <div class="deals-grid">
                            <div v-for="opt in infraStore.brandDeals.options" :key="opt.name" class="deal-card glass-panel" :class="{ disabled: infraStore.brandDeals.current && !opt.is_active }">
                                <div class="deal-brand">{{ opt.name }}</div>
                                <div class="deal-meta">
                                    <span class="focus">{{ opt.focus }}</span>
                                    <span class="reputation">{{ opt.reputation }}</span>
                                </div>
                                <div class="deal-benefit">
                                    <div class="benefit-val">{{ opt.discount }}%</div>
                                    <div class="benefit-label">Hardware Discount</div>
                                </div>
                                <button 
                                    class="sign-btn" 
                                    :disabled="infraStore.brandDeals.current"
                                    @click="handleSignDeal(opt.name)"
                                >
                                    {{ opt.is_active ? 'CONTRACT_ACTIVE' : 'SIGN_30_DAY_DEAL' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Black Market / Night Shop (F157) -->
                    <BlackMarketOverlay v-if="activeTab === 'blackmarket'" />
                </div>
            </div>
        </div>
        <PeeringNegotiationOverlay 
            v-if="selectedPartner" 
            :partner="selectedPartner" 
            @close="selectedPartner = null"
            @success="handlePeeringSuccess"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '../../utils/api';
import { useMarketStore } from '../../stores/useMarketStore';
import { useGameStore } from '../../stores/game';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { useInfrastructureStore } from '../../stores/infrastructure';
import SabotagePanel from './SabotagePanel.vue';
import PeeringNegotiationOverlay from './PeeringNegotiationOverlay.vue';
import MarketShareChart from '../Market/MarketShareChart.vue';
import BlackMarketOverlay from './BlackMarketOverlay.vue';

const marketStore = useMarketStore();
const gameStore = useGameStore();
const authStore = useAuthStore();
const toastStore = useToastStore();
const infraStore = useInfrastructureStore();

const user = computed(() => authStore.user);
const processing = ref(false);

const emit = defineEmits(['close']);

const loading = ref(true);
const activeTab = ref('intelligence');
const activeRegion = ref(null);
const marketData = ref({
    participants: [],
    player: { id: 'player-market-info', name: 'You', marketShare: 0, color: '#3fb950', reputation: 50 },
    unclaimed: 0,
    availableRegions: {}
});
const peeringPartners = ref([]);
const selectedPartner = ref(null);

async function fetchMarketData() {
    loading.value = true;
    try {
        const url = activeRegion.value ? `/market?region=${activeRegion.value}` : '/market';
        const res = await api.get(url);
        if (res.success) {
            marketData.value = res.data;
        }

        // Fetch peering eligibility
        const pRes = await api.get('/network/peering/partners');
        if (pRes.success) {
            peeringPartners.value = pRes.data;
        }
    } catch (e) {
        console.error('Failed to load market data', e);
    } finally {
        loading.value = false;
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

const openPeeringNegotiation = (competitor) => {
    const p = peeringPartners.value?.find(p => p.id === competitor.id);
    if (p) {
        selectedPartner.value = { ...p, color: competitor.color };
    }
};

const handlePeeringSuccess = (agreement) => {
    fetchMarketData();
};

function setRegion(region) {
    activeRegion.value = region;
    fetchMarketData();
}

const sortedParticipants = computed(() => {
    const all = [
        ...(marketData.value.participants || []),
        marketData.value.player ? { ...marketData.value.player, isPlayer: true } : null
    ].filter(p => p);
    return all.sort((a, b) => (b.marketShare || 0) - (a.marketShare || 0));
});

function formatArchetype(arch) {
    if (!arch) return 'Unknown';
    return arch.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

const chartSegmentsGrouped = computed(() => {
    const segments = [];

    // Participants
    if (marketData.value && marketData.value.participants) {
        marketData.value.participants.forEach(p => {
            segments.push({
                label: p.name,
                value: p.marketShare || 0,
                color: p.color
            });
        });
    }

    // Player
    if (marketData.value && marketData.value.player) {
        segments.push({
            label: 'You',
            value: marketData.value.player.marketShare || 0,
            color: marketData.value.player.color || '#3fb950'
        });
    }

    // Unclaimed
    if (marketData.value && (marketData.value.unclaimed || 0) > 0.1) {
        segments.push({
            label: 'Unclaimed',
            value: marketData.value.unclaimed || 0,
            color: 'rgba(255,255,255,0.05)'
        });
    }

    return segments.sort((a, b) => b.value - a.value);
});

const formatTimeRemaining = (seconds) => {
    if (seconds <= 0) return 'ENDING...';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const getConditionClass = (c) => {
    if (c > 80) return 'text-success';
    if (c > 50) return 'text-warning';
    return 'text-danger';
};

const getConditionColor = (c) => {
    if (c > 80) return '#3fb950';
    if (c > 50) return '#d2994e';
    return '#f85149';
};

const handleBid = async (auction) => {
    const minBid = (auction.current_bid || 0) + 50;
    const amountStr = prompt(`Enter bid amount for ${auction.item_key} (Min: $${minBid.toLocaleString()}):`, minBid);
    if (!amountStr) return;
    
    const amount = parseFloat(amountStr);
    if (isNaN(amount) || amount < minBid) {
        toastStore.show('Invalid bid amount', 'error');
        return;
    }

    const res = await marketStore.placeBid(auction.id, amount);
    if (res.success) {
        toastStore.show('Bid placed successfully!', 'success');
    } else {
        toastStore.show(res.error, 'error');
    }
};

const handleBuyUsed = async (listing) => {
    if (processing.value) return;
    processing.value = true;
    try {
        const res = await marketStore.buyUsedItem(listing.id);
        if (res.success) {
            toastStore.show('Asset purchased and added to inventory!', 'success');
        } else {
            toastStore.show(res.error, 'error');
        }
    } finally {
        processing.value = false;
    }
};

const trendsExclTime = computed(() => {
    const { last_update, ...rest } = marketStore.resaleTrends;
    return rest;
});

const getTrendClass = (val) => {
    if (val > 1.05) return 'text-success';
    if (val < 0.95) return 'text-danger';
    return 'text-warning';
};

const inventoryToSell = computed(() => {
    return (gameStore.hardware?.inventory || []).filter(i => i.status === 'inventory');
});

const calculateResale = (item) => {
    const base = item.config.price || 0;
    const trend = marketStore.resaleTrends[item.type] || 1.0;
    const cond = Math.max(0.1, item.health / 100);
    return Math.round(base * 0.4 * trend * cond);
};

const handleSell = async (item) => {
    if (item.needsShredding) {
        toastStore.error('Cannot liquidate components containing sensitive data. Shred them first.');
        return;
    }
    if (!confirm(`Sell ${item.name} for $${calculateResale(item).toLocaleString()}?`)) return;
    const res = await infraStore.sellComponent(item.id);
    if (res.success) {
        await gameStore.loadGameState();
    }
};

const handleShred = async (item) => {
    if (!confirm(`Are you sure you want to securely shred ${item.name}? This costs $50 but clears it for resale and grants compliance progress.`)) return;
    const res = await infraStore.shredComponent(item.id);
    if (res.success) {
        await gameStore.loadGameState();
    }
};

const handleBuyout = async (item) => {
    const price = Math.round(item.config.price * 0.75);
    if (!confirm(`Buyout ${item.name} for $${price.toLocaleString()}?`)) return;
    processing.value = true;
    try {
        const res = await api.post(`/hardware/components/${item.id}/buyout`);
        if (res.success) {
            toastStore.show('Component buyout successful!', 'success');
            await gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const handleReturn = async (item) => {
    if (!confirm(`Return ${item.name} to vendor?`)) return;
    processing.value = true;
    try {
        const res = await api.post(`/hardware/components/${item.id}/return`);
        if (res.success) {
            toastStore.show('Component returned to vendor.', 'success');
            await gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const handleSignDeal = async (brandName) => {
    const res = await infraStore.signBrandDeal(brandName);
    if (res.success) {
        await gameStore.loadGameState();
    }
};

const handleTerminateDeal = async () => {
    if (!confirm('Termination costs $2,500 in legal penalties. Proceed?')) return;
    const res = await infraStore.terminateBrandDeal();
    if (res.success) {
        await gameStore.loadGameState();
    }
};

const formatDealExpiry = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    const diff = date.getTime() - new Date().getTime();
    const days = Math.ceil(diff / (1000 * 3600 * 24));
    return `${days} Days`;
};

watch(activeTab, (newTab) => {
    if (newTab === 'auctions') marketStore.fetchAuctions();
    if (newTab === 'hardware') marketStore.fetchUsedListings();
    if (newTab === 'liquidate') marketStore.fetchResaleTrends();
    if (newTab === 'exclusivity') infraStore.loadBrandDeals();
});

const isNight = computed(() => {
    const tick = gameStore.state?.economy?.current_tick || 0;
    const minuteOfDay = tick % 1440;
    return minuteOfDay >= 0 && minuteOfDay <= 240;
});

onMounted(fetchMarketData);
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3000;
    backdrop-filter: blur(10px);
}

.market-overlay {
    width: 900px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 24px 32px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.overlay-nav {
    display: flex;
    padding: 0 32px;
    gap: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(0, 0, 0, 0.2);
}

.nav-tab {
    background: none;
    border: none;
    padding: 16px 0;
    color: #8b949e;
    font-size: 0.9rem;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
}

.nav-tab:hover {
    color: #fff;
}

.nav-tab.active {
    color: var(--color-primary);
    font-weight: bold;
}

.nav-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: var(--color-primary);
    box-shadow: 0 0 10px var(--color-primary);
}

.night-shop-tab.is-night {
    color: #bb86fc;
    text-shadow: 0 0 8px rgba(187, 134, 252, 0.5);
}

.night-shop-tab.active.is-night::after {
    background: #bb86fc;
    box-shadow: 0 0 10px #bb86fc;
}

.header-content h2 {
    margin: 0;
    font-size: 1.8rem;
    color: var(--color-primary);
    letter-spacing: 2px;
}

.header-content p {
    margin: 4px 0 0 0;
    color: #8b949e;
    font-size: 0.9rem;
}

.overlay-body {
    padding: 32px;
    overflow-y: auto;
}

.market-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.market-share-section h3, .competitors-list-section h3 {
    margin-top: 0;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 1px;
    color: #8b949e;
    margin-bottom: 24px;
}

.chart-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 32px;
}

.market-pie {
    width: 240px;
    height: 240px;
    transform: rotate(-90deg);
}

.chart-label {
    font-size: 6px;
    fill: #8b949e;
    font-weight: bold;
}

.chart-value {
    font-size: 10px;
    fill: #fff;
    font-weight: bold;
}

.market-legend {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.legend-item {
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 6px;
    border-left: 2px solid var(--comp-color);
    transition: all 0.2s;
}

.legend-item:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(4px);
}

.legend-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.legend-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
}

.legend-value {
    font-size: 0.75rem;
    font-family: var(--font-family-mono);
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
    box-shadow: 0 0 6px var(--comp-color);
}

.competitors-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.competitor-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 16px;
    transition: transform 0.2s, border-color 0.2s;
}

.competitor-card:hover {
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateX(4px);
}

.competitor-card.player {
    background: rgba(63, 185, 80, 0.05);
    border: 1px solid rgba(63, 185, 80, 0.2);
}

.competitor-card.player .rank-badge {
    background: var(--color-primary);
    color: #fff;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 3px solid;
    padding-left: 12px;
    margin-bottom: 10px;
}

.name-box {
    display: flex;
    flex-direction: column;
}

.name-box h4 {
    margin: 0;
    font-size: 1rem;
}

.hq-region {
    font-size: 0.7rem;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rank-badge {
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: var(--font-family-mono);
    font-size: 0.75rem;
    font-weight: bold;
}

.badge {
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}

.badge.active { background: rgba(35, 134, 54, 0.2); color: #3fb950; }

.tagline {
    font-size: 0.8rem;
    color: #8b949e;
    font-style: italic;
    margin: 0 0 16px 0;
}

.stats {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 12px;
}

.stat label {
    display: block;
    font-size: 0.65rem;
    color: #8b949e;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.stat span {
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: capitalize;
}

.reputation-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
    margin-top: 6px;
}

.reputation-bar .fill {
    height: 100%;
    border-radius: 3px;
}

.close-button {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
}

.close-button:hover { color: #fff; }

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.region-tabs {
    display: flex;
    gap: 15px;
    align-items: center;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    padding-left: 15px;
    margin-left: 15px;
}

.archetype-label {
    color: var(--color-primary);
    font-style: italic;
}

.tech-metrics {
    display: flex;
    justify-content: space-between;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 15px;
}

.metric {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.m-label {
    font-size: 0.6rem;
    color: #8b949e;
    font-family: var(--font-family-mono);
}

.m-value {
    font-size: 0.85rem;
    font-weight: bold;
    color: #fff;
}

.card-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.peering-btn {
    flex: 1;
    background: rgba(0, 255, 157, 0.1);
    border: 1px solid rgba(0, 255, 157, 0.3);
    color: #00ff9d;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    text-transform: uppercase;
    transition: all 0.2s;
}

.peering-btn:hover:not(:disabled) {
    background: rgba(0, 255, 157, 0.2);
    border-color: #00ff9d;
}

.peering-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.1);
    color: #8b949e;
}

/* Secondary Market Styles */
.secondary-market-view {
    width: 100%;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    opacity: 0.5;
    text-align: center;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 20px;
}

.auction-grid, .listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}

.auction-card, .listing-card {
    padding: 20px;
    display: flex;
    flex-direction: column;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.auction-card:hover, .listing-card:hover {
    border-color: var(--color-primary);
    transform: translateY(-4px);
    background: rgba(var(--color-primary-rgb), 0.05);
}

.auction-card.high-bid {
    border-color: #3fb950;
    box-shadow: inset 0 0 20px rgba(63, 185, 80, 0.1);
}

.auction-badge, .type-tag {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 0.6rem;
    font-weight: 800;
    padding: 2px 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    color: #fff;
}

.auction-timer {
    font-family: var(--font-family-mono);
    font-size: 0.75rem;
    margin-bottom: 12px;
    color: #8b949e;
}

.auction-timer.critical {
    color: #ff5500;
    animation: pulse-red 1s infinite alternate;
}

.timer-value {
    color: #fff;
    margin-left: 8px;
    font-weight: bold;
}

.item-name {
    margin: 10px 0 4px 0;
    font-size: 1.1rem;
}

.seller, .seller-info {
    font-size: 0.75rem;
    color: #8b949e;
    margin-bottom: 20px;
}

.specs-mini {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.spec label {
    display: block;
    font-size: 0.6rem;
    color: #8b949e;
}

.spec span {
    font-size: 0.85rem;
    font-weight: 800;
}

.price-box {
    background: rgba(0, 0, 0, 0.3);
    padding: 12px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.price-box label {
    display: block;
    font-size: 0.6rem;
    color: #8b949e;
}

.price-box .value {
    font-family: var(--font-family-mono);
    font-weight: bold;
    color: var(--color-primary);
}

.bid-btn, .buy-btn {
    width: 100%;
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 10px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 0.8rem;
    cursor: pointer;
    text-transform: uppercase;
}

.bid-status.winner {
    background: rgba(63, 185, 80, 0.1);
    color: #3fb950;
    padding: 10px;
    text-align: center;
    font-size: 0.7rem;
    font-weight: 800;
    border-radius: 6px;
}

.condition-group {
    margin-top: 15px;
}

.condition-meter {
    height: 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 4px;
}

.condition-meter .fill {
    height: 100%;
}

.price-tag {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 20px;
    color: var(--color-primary);
}

@keyframes pulse-red {
    from { opacity: 0.5; }
    to { opacity: 1; text-shadow: 0 0 10px #ff5500; }
}

.text-success { color: #3fb950; }
.text-warning { color: #d2994e; }
.text-danger { color: #f85149; }

.market-ticker {
    display: flex; gap: 20px; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 30px;
    border: 1px dashed rgba(255,255,255,0.1); justify-content: space-around;
}
.ticker-item { display: flex; flex-direction: column; gap: 2px; align-items: center; }
.ticker-item .type { font-size: 0.6rem; color: #8b949e; letter-spacing: 1px; }
.ticker-item .trend { font-weight: 800; font-family: var(--font-family-mono); font-size: 0.9rem; }

.sell-grid { display: flex; flex-direction: column; gap: 10px; }
.sell-card {
    display: flex; align-items: center; gap: 20px; background: rgba(255,255,255,0.03);
    padding: 15px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);
}
.item-icon { font-size: 1.5rem; }
.item-info { flex: 1; }
.item-info h4 { margin: 0; font-size: 0.9rem; }
.item-info p { margin: 4px 0 0; font-size: 0.75rem; color: #8b949e; }
.item-price { text-align: right; margin-right: 20px; }
.item-price label { display: block; font-size: 0.6rem; color: #8b949e; }
.item-price .value { font-weight: 800; font-family: var(--font-family-mono); color: var(--color-primary); }

.sell-action-btn {
    background: #f8514933; border: 1px solid #f8514966; color: #ff7b72;
    padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
}
.sell-action-btn:hover { background: #f85149; color: #fff; }

.shred-btn {
    background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.3); color: #fff;
    padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
}
.shred-btn:hover { background: #fff; color: #000; }

.deals-hero {
    display: flex; align-items: center; gap: 30px; margin-bottom: 30px; background: rgba(var(--color-primary-rgb), 0.1);
    padding: 30px; border-radius: 12px; border: 1px solid rgba(var(--color-primary-rgb), 0.2);
}
.hero-icon { font-size: 3rem; }
.hero-text h3 { margin: 0; color: var(--color-primary); font-size: 1.4rem; }
.hero-text p { margin: 8px 0 0; color: #8b949e; line-height: 1.5; }

.active-deal-card {
    background: rgba(var(--color-primary-rgb), 0.15); border: 1px solid var(--color-primary);
    padding: 24px; border-radius: 12px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;
}
.active-badge { position: absolute; top: -10px; left: 20px; background: var(--color-primary); color: #000; font-weight: 900; font-size: 0.6rem; padding: 2px 10px; border-radius: 4px; }
.deal-info h4 { margin: 0; font-size: 1.2rem; }
.deal-info p { margin: 4px 0 0; color: #fff; font-weight: bold; }
.deal-info .expiry { font-size: 0.8rem; color: #8b949e; font-weight: normal; }

.terminate-btn {
    background: rgba(248, 81, 73, 0.1); border: 1px solid rgba(248, 81, 73, 0.4); color: #f85149;
    padding: 10px 20px; border-radius: 6px; font-weight: 800; cursor: pointer; transition: 0.2s; font-size: 0.75rem;
}
.terminate-btn:hover { background: #f85149; color: #fff; }

.deals-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
.deal-card { padding: 20px; text-align: center; display: flex; flex-direction: column; gap: 15px; border: 1px solid rgba(255,255,255,0.05); }
.deal-card.disabled { opacity: 0.4; filter: grayscale(0.8); pointer-events: none; }
.deal-brand { font-size: 1.1rem; font-weight: 800; }
.deal-meta { display: flex; justify-content: center; gap: 10px; }
.deal-meta span { font-size: 0.6rem; text-transform: uppercase; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; color: #8b949e; }
.deal-benefit .benefit-val { font-size: 2rem; font-weight: 900; color: var(--color-primary); }
.deal-benefit .benefit-label { font-size: 0.65rem; color: #8b949e; text-transform: uppercase; }
.sign-btn {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff;
    padding: 10px; border-radius: 6px; font-weight: 800; cursor: pointer; transition: 0.2s; font-size: 0.7rem;
}
.sign-btn:hover:not(:disabled) { background: var(--color-primary); color: #000; border-color: var(--color-primary); }

.buyout-btn {
    background: #10b98133; border: 1px solid #10b98166; color: #3fb950;
    padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
}
.buyout-btn:hover { background: #10b981; color: #fff; }

.return-btn {
    background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); color: #8b949e;
    padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
}
.return-btn:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }

.lease-tag {
    background: #ffa500;
    color: #000;
    font-size: 0.6rem;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 900;
    margin-left: 6px;
    vertical-align: middle;
}
</style>
