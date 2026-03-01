<template>
    <div class="auctions-container">
        <header class="auctions-header">
            <div class="header-info">
                <h3>LIVE_LIQUIDATION_AUCTIONS</h3>
                <p>High-risk, high-reward hardware from bankrupt entities.</p>
            </div>
            <div class="header-actions" v-if="gameStore.player?.is_admin">
                <button class="admin-trigger-btn" @click="adminTrigger" :disabled="triggering">
                    {{ triggering ? 'LAUNCHING...' : 'FORCE_NEW_AUCTION' }}
                </button>
            </div>
            <div class="auction-count" v-if="auctions.length > 0">
                {{ auctions.length }} ACTIVE_LOTS
            </div>
        </header>

        <div v-if="loading" class="loading-state">
            <div class="scanline"></div>
            <p>Scanning encrypted frequency bands...</p>
        </div>

        <div v-else-if="auctions.length === 0" class="empty-state">
            <div class="empty-icon">🔨</div>
            <h4>NO_ACTIVE_AUCTIONS</h4>
            <p>Check back later. Auctions are triggered by market volatility.</p>
        </div>

        <div v-else class="auctions-grid">
            <div v-for="auction in auctions" :key="auction.id" class="auction-card" :class="{ 'is-winning': isWinning(auction) }">
                <div class="card-header">
                    <div class="item-type-badge">{{ auction.item_type.toUpperCase() }}</div>
                    <div class="timer" :class="{ 'urgent': auction.time_remaining_seconds < 60 }">
                        {{ formatTime(auction.time_remaining_seconds) }}
                    </div>
                </div>

                <div class="item-visuals">
                    <HardwareIcon :type="auction.item_type" size="lg" />
                    <div class="item-meta">
                        <div class="item-name">{{ formatName(auction.item_key) }}</div>
                        <div class="seller">SELLER: {{ auction.seller_name }}</div>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat">
                        <label>Condition</label>
                        <span :class="getConditionClass(auction.condition)">{{ auction.condition }}%</span>
                    </div>
                    <div class="stat">
                        <label>Defect Risk</label>
                        <span :class="{ 'text-danger': auction.defect_chance > 10 }">{{ auction.defect_chance }}%</span>
                    </div>
                </div>

                <div class="bidding-section">
                    <div class="price-display">
                        <label>{{ auction.current_bid ? 'CURRENT_BID' : 'STARTING_PRICE' }}</label>
                        <div class="price-value" :class="{ 'winning': isWinning(auction) }">
                             ${{ formatNumber(auction.current_bid || auction.starting_price) }}
                        </div>
                        <div class="bidder-tag" v-if="auction.highest_bidder_id">
                            {{ isWinning(auction) ? 'YOU ARE HIGHEST BIDDER' : 'ANOTHER BIDDER LEADING' }}
                        </div>
                    </div>

                    <div class="bid-actions">
                        <div class="bid-input-group">
                            <span class="currency">$</span>
                            <input 
                                type="number" 
                                v-model="bidAmounts[auction.id]" 
                                :min="auction.min_next_bid" 
                                step="10"
                            />
                        </div>
                        <button 
                            class="bid-btn" 
                            @click="placeBid(auction)" 
                            :disabled="bidding === auction.id || !canAfford(bidAmounts[auction.id]) || isWinning(auction)"
                        >
                            {{ bidding === auction.id ? 'BUYING...' : (isWinning(auction) ? 'WINNING' : 'PLACE BID') }}
                        </button>
                    </div>
                    <div class="min-bid-hint">Min next bid: ${{ formatNumber(auction.min_next_bid) }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from '../../utils/api';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import SoundManager from '../../services/SoundManager';
import HardwareIcon from '../UI/HardwareIcon.vue';

const gameStore = useGameStore();
const toast = useToastStore();

const loading = ref(true);
const auctions = ref([]);
const bidding = ref(null);
const triggering = ref(false);
const bidAmounts = ref({});
let refreshInterval = null;

const fetchAuctions = async () => {
    try {
        const res = await api.get('/auctions');
        if (res) {
            auctions.value = res;
            // Initialize bid amounts with min required if not set
            res.forEach(a => {
                if (!bidAmounts.value[a.id]) {
                    bidAmounts.value[a.id] = a.min_next_bid;
                } else if (bidAmounts.value[a.id] < a.min_next_bid) {
                     bidAmounts.value[a.id] = a.min_next_bid;
                }
            });
        }
    } catch (e) {
        console.error("Auction Error", e);
    } finally {
        loading.value = false;
    }
};

const placeBid = async (auction) => {
    const amount = bidAmounts.value[auction.id];
    if (amount < auction.min_next_bid) {
        toast.error(`Minimum bid is $${auction.min_next_bid}`);
        return;
    }

    if (!confirm(`Confirm bid of $${formatNumber(amount)} on ${formatName(auction.item_key)}? Funds will be deducted immediately.`)) return;

    bidding.value = auction.id;
    try {
        const res = await api.post(`/auctions/${auction.id}/bid`, { amount });
        if (res.success) {
            toast.success("Bid placed successfully!");
            SoundManager.playSuccess();
            await gameStore.loadGameState();
            await fetchAuctions();
        }
    } catch (e) {
        toast.error(e.response?.data?.error || "Failed to place bid");
        SoundManager.playError();
    } finally {
        bidding.value = null;
    }
};

const adminTrigger = async () => {
    if (!confirm("Admin: Force trigger a new liquidation auction now?")) return;
    triggering.value = true;
    try {
        await api.post('/admin/auctions/trigger');
        toast.success("Auction triggered manually!");
        await fetchAuctions();
    } catch (e) {
        toast.error("Failed to trigger auction");
    } finally {
        triggering.value = false;
    }
};

const isWinning = (auction) => {
    return auction.highest_bidder_id === gameStore.player.id;
};

const canAfford = (amount) => {
    return gameStore.player.economy.balance >= amount;
};

const formatTime = (seconds) => {
    if (seconds <= 0) return "ENDING...";
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const formatName = (key) => key.replace(/_/g, ' ').toUpperCase();
const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num);

const getConditionClass = (cond) => {
    if (cond > 70) return 'text-success';
    if (cond > 40) return 'text-warning';
    return 'text-danger';
};

onMounted(() => {
    fetchAuctions();
    refreshInterval = setInterval(() => {
        // Decrease local timers
        auctions.value.forEach(a => {
            if (a.time_remaining_seconds > 0) a.time_remaining_seconds--;
        });
        // Every 5 seconds, sync with server
        if (Date.now() % 5000 < 1000) {
            fetchAuctions();
        }
    }, 1000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<style scoped>
.auctions-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 24px;
}

.auctions-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    padding-bottom: 16px;
}

.header-info h3 {
    margin: 0;
    font-family: var(--ds-font-mono);
    color: var(--ds-primary);
    letter-spacing: 2px;
}

.header-info p {
    margin: 4px 0 0 0;
    font-size: 0.85rem;
    color: var(--ds-text-muted);
}

.auction-count {
    background: var(--ds-accent);
    color: #fff;
    font-size: 0.65rem;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 800;
}

.auctions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.auction-card {
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-subtle);
    border-radius: var(--ds-radius-lg);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s;
}

.auction-card:hover {
    border-color: var(--ds-primary);
    box-shadow: 0 0 20px rgba(0, 255, 157, 0.1);
}

.auction-card.is-winning {
    border-color: var(--ds-nominal);
    background: rgba(46, 204, 113, 0.05);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-type-badge {
    font-size: 0.6rem;
    font-weight: 900;
    background: rgba(255,255,255,0.05);
    padding: 2px 8px;
    border-radius: 4px;
    color: var(--ds-text-ghost);
}

.timer {
    font-family: var(--ds-font-mono);
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--ds-accent);
}

.timer.urgent {
    color: var(--ds-critical);
    animation: pulse 1s infinite alternate;
}

@keyframes pulse {
    from { opacity: 1; }
    to { opacity: 0.5; }
}

.item-visuals {
    display: flex;
    gap: 16px;
    align-items: center;
}

.item-meta {
    flex: 1;
}

.item-name {
    font-weight: 800;
    font-size: 1rem;
    color: #fff;
    line-height: 1.2;
}

.seller {
    font-size: 0.65rem;
    color: var(--ds-text-muted);
    text-transform: uppercase;
    margin-top: 4px;
}

.stats-row {
    display: flex;
    gap: 24px;
    background: rgba(0,0,0,0.2);
    padding: 10px;
    border-radius: 6px;
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat label {
    font-size: 0.6rem;
    color: var(--ds-text-ghost);
    text-transform: uppercase;
}

.stat span {
    font-weight: 800;
    font-size: 0.85rem;
}

.bidding-section {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.price-display {
    text-align: center;
}

.price-display label {
    font-size: 0.6rem;
    color: var(--ds-text-muted);
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
}

.price-value {
    font-size: 1.8rem;
    font-weight: 900;
    font-family: var(--ds-font-mono);
    color: #fff;
}

.price-value.winning {
    color: var(--ds-nominal);
}

.bidder-tag {
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-top: 4px;
}

.bid-actions {
    display: flex;
    gap: 8px;
}

.bid-input-group {
    flex: 1;
    display: flex;
    align-items: center;
    background: #000;
    border: 1px solid var(--ds-border-subtle);
    border-radius: 4px;
    padding: 0 10px;
}

.currency {
    color: var(--ds-text-ghost);
    font-size: 0.8rem;
    margin-right: 4px;
}

.bid-input-group input {
    background: transparent;
    border: none;
    color: #fff;
    width: 100%;
    padding: 8px 0;
    font-family: var(--ds-font-mono);
    font-weight: 800;
    outline: none;
}

.bid-btn {
    background: var(--ds-primary);
    color: #000;
    border: none;
    padding: 0 20px;
    border-radius: 4px;
    font-weight: 900;
    font-size: 0.75rem;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.bid-btn:hover:not(:disabled) {
    background: var(--ds-primary-glow);
    transform: translateY(-2px);
}

.bid-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--ds-bg-subtle);
    color: var(--ds-text-ghost);
}

.min-bid-hint {
    font-size: 0.6rem;
    color: var(--ds-text-ghost);
    text-align: center;
}

.loading-state {
    padding: 100px;
    text-align: center;
    color: var(--ds-primary);
    position: relative;
}

.scanline {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 2px;
    background: var(--ds-primary);
    box-shadow: 0 0 10px var(--ds-primary);
    opacity: 0.3;
    animation: scan 2s linear infinite;
}

@keyframes scan {
    from { top: 0; }
    to { top: 100%; }
}

.text-success { color: var(--ds-nominal); }
.text-warning { color: var(--ds-warning); }
.text-danger { color: var(--ds-critical); }

.admin-trigger-btn {
    background: #7f1d1d;
    color: #fca5a5;
    border: 1px solid #991b1b;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 800;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.2s;
}
.admin-trigger-btn:hover:not(:disabled) {
    background: #991b1b;
    color: #fff;
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
}
.admin-trigger-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
