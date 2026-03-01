<template>
    <div class="v2-market-listings">
        <header class="v2-content-header">
            <div class="v2-breadcrumb">
                <span class="v2-path">GLOBAL_NETWORK</span>
                <span class="v2-sep">//</span>
                <span class="v2-node">REFURBISHED_EXCHANGE</span>
            </div>
            <div class="v2-timer">REFRESH_IN: {{ refreshTimer }}s</div>
        </header>

        <div v-if="loading" class="v2-loading">Scanning market frequencies...</div>
        
        <div v-else-if="!listings || listings.length === 0" class="v2-empty-state">
            <div class="v2-icon-large">📦</div>
            <h3>NO_INVENTORY</h3>
            <p>The black market is currently dry. Check back later for new liquidations.</p>
        </div>

        <div v-else class="v2-grid-scroller">
            <div class="v2-market-grid">
                <div 
                    v-for="item in listings" 
                    :key="item.id" 
                    class="v2-market-card"
                    :class="{ 
                        'is-risky': item.defect_chance > 15, 
                        'is-deal': item.discount_percent > 65 
                    }"
                >
                    <div class="v2-card-badge" v-if="item.discount_percent > 50">
                        -{{ item.discount_percent }}%
                    </div>

                    <div class="v2-card-main">
                        <div class="v2-item-icon">
                            <HardwareIcon :type="item.item_type" size="md" />
                        </div>
                        
                        <div class="v2-item-details">
                            <div class="v2-item-name">{{ formatName(item.item_key) }}</div>
                            <div class="v2-item-seller">SOLD_BY: {{ item.seller_name }}</div>
                            
                            <div class="v2-stat-row">
                                <div class="v2-stat-pill" :class="getConditionClass(item.condition)">
                                    COND: {{ item.condition }}%
                                </div>
                                <div class="v2-stat-pill is-danger" v-if="item.defect_chance > 5">
                                    RISK: {{ item.defect_chance }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="v2-card-footer">
                        <div class="v2-price-block">
                            <span class="v2-price-old">\${{ formatNumber(item.original_price) }}</span>
                            <span class="v2-price-new">\${{ formatNumber(item.price) }}</span>
                        </div>
                        
                        <button 
                            @click="buyItem(item)" 
                            :disabled="buying === item.id || !canAfford(item.price)"
                            class="v2-btn is-primary is-small"
                        >
                            {{ buying === item.id ? 'PROCESSING...' : 'PURCHASE' }}
                        </button>
                    </div>
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

const listings = ref([]);
const loading = ref(true);
const buying = ref(null);
const refreshTimer = ref(60);
let timerInterval = null;

const gameStore = useGameStore();
const toast = useToastStore();

const loadListings = async () => {
    loading.value = true;
    try {
        const response = await api.get('/market/used');
        if (response.success) {
            listings.value = response.data;
        }
    } catch (e) {
        console.error("Failed to load used market", e);
    } finally {
        loading.value = false;
    }
};

const buyItem = async (item) => {
    if (item.defect_chance > 20) {
        if (!confirm(`WARNING: This item has a ${item.defect_chance}% chance of critical failure. Proceed?`)) return;
    }

    buying.value = item.id;
    try {
        const response = await api.post('/market/buy', { listing_id: item.id });
        if (response.success) {
            toast.success(`Acquired ${formatName(item.item_key)}`);
            SoundManager.playSuccess();
            await gameStore.loadGameState();
            // Remove locally
            listings.value = listings.value.filter(l => l.id !== item.id);
        }
    } catch (e) {
        toast.error(e.response?.data?.error || 'Purchase failed');
        SoundManager.playError();
    } finally {
        buying.value = null;
    }
};

const canAfford = (price) => {
    return gameStore.player.economy.balance >= price;
};

const formatName = (key) => {
    return key.replace(/_/g, ' ').toUpperCase();
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num);
};

const getConditionClass = (cond) => {
    if (cond > 80) return 'is-success';
    if (cond > 50) return 'is-warning';
    return 'is-danger';
};

onMounted(() => {
    loadListings();
    timerInterval = setInterval(() => {
        refreshTimer.value--;
        if (refreshTimer.value <= 0) {
            refreshTimer.value = 60;
            loadListings(); // Silent refresh
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});
</script>

<style scoped>
.v2-market-listings {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--v3-bg-base);
}

.v2-loading {
    padding: 60px;
    text-align: center;
    color: var(--v3-text-ghost);
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}

.v2-grid-scroller {
    flex: 1;
    overflow-y: auto;
    padding: 32px;
}

.v2-market-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.v2-market-card {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    transition: all var(--v3-transition-fast);
    border-radius: var(--v3-radius);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.v2-market-card:hover {
    border-color: var(--v3-text-ghost);
    background: var(--v3-bg-overlay);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
}

.v2-market-card.is-risky {
    border-left: 2px solid var(--v3-danger);
}

.v2-market-card.is-deal {
    border-left: 2px solid var(--v3-success);
}

.v2-card-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--v3-accent);
    color: #fff;
    font-weight: 900;
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: var(--v3-radius);
    letter-spacing: 0.05em;
    z-index: 2;
}

.v2-card-main {
    display: flex;
    gap: 16px;
}

.v2-item-icon {
    font-size: 1.5rem;
    background: rgba(0,0,0,0.2);
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--v3-radius);
    border: var(--v3-border-soft);
}

.v2-item-details {
    flex: 1;
}

.v2-item-name {
    font-family: var(--font-family-mono);
    font-weight: 800;
    color: #fff;
    font-size: 0.85rem;
    margin-bottom: 4px;
}

.v2-item-seller {
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700;
    margin-bottom: 12px;
}

.v2-stat-row {
    display: flex;
    gap: 10px;
}

.v2-stat-pill {
    font-size: 0.55rem;
    padding: 3px 8px;
    border-radius: 2px;
    background: rgba(255,255,255,0.03);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid transparent;
}

.v2-stat-pill.is-success { color: var(--v3-success); border-color: rgba(46, 204, 113, 0.2); }
.v2-stat-pill.is-warning { color: var(--v3-warning); border-color: rgba(244, 180, 0, 0.2); }
.v2-stat-pill.is-danger { color: var(--v3-danger); border-color: rgba(255, 77, 79, 0.2); }

.v2-card-footer {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-top: var(--v3-border-soft);
    padding-top: 16px;
}

.v2-price-block {
    display: flex;
    flex-direction: column;
}

.v2-price-old {
    font-size: 0.7rem;
    text-decoration: line-through;
    color: var(--v3-text-ghost);
    font-family: var(--font-family-mono);
}

.v2-price-new {
    font-size: 1.1rem;
    font-weight: 900;
    color: var(--v3-accent);
    font-family: var(--font-family-mono);
}

.v2-timer {
    font-family: var(--font-family-mono);
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.v2-btn.is-primary.is-small {
    padding: 8px 16px;
    background: var(--v3-accent);
    color: #fff;
    border: none;
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    border-radius: var(--v3-radius);
    transition: all var(--v3-transition-fast);
}

.v2-btn:hover:not(:disabled) {
    background: #477fff;
    box-shadow: 0 4px 15px var(--v3-accent-glow);
    transform: translateY(-1px);
}

.v2-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    grayscale: 1;
}
</style>
