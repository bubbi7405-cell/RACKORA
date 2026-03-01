<template>
    <div class="black-market-container">
        <div class="bm-header">
            <div class="bm-title">
                <span class="glitch-text" data-text="THE NIGHT SHOP">THE NIGHT SHOP</span>
            </div>
            <div class="bm-timer" :class="{ 'timer-critical': closingSoon }">
                <span class="label">CLOSING IN:</span>
                <span class="value">{{ formattedTime }}</span>
            </div>
        </div>

        <div v-if="!data.available" class="shop-closed">
            <div class="closed-icon">🌑</div>
            <h3>SHOP CLOSED</h3>
            <p>The underground connections are only active between 00:00 and 04:00.</p>
            <div class="reopen-timer">
                Next window opens in approximately <strong>{{ data.nextOpening }} minutes</strong>.
            </div>
        </div>

        <div v-else class="shop-grid">
            <div v-for="deal in data.deals" :key="deal.id" class="deal-card">
                <div class="card-glow"></div>
                <div class="deal-header">
                    <div class="deal-type">{{ deal.type.toUpperCase() }}</div>
                    <div class="deal-condition" :class="getConditionClass(deal.condition)">
                        {{ deal.condition }}% HEALTH
                    </div>
                </div>
                
                <div class="deal-name">{{ deal.name }}</div>
                <div class="deal-desc">{{ deal.description }}</div>
                
                <div class="deal-footer">
                    <div class="price-section">
                        <div class="original-price">${{ formatNumber(deal.originalPrice) }}</div>
                        <div class="current-price">${{ formatNumber(deal.price) }}</div>
                    </div>
                    <button 
                        class="buy-btn" 
                        @click="buyItem(deal)"
                        :disabled="loading || economy.balance < deal.price"
                    >
                        <span v-if="loading && selectedDealId === deal.id">...</span>
                        <span v-else>ACQUIRE</span>
                    </button>
                </div>

                <div class="risk-indicator">
                    <div class="risk-bar" :style="{ width: '15%' }"></div>
                    <span class="risk-label">SECURITY RISK: HIGH</span>
                </div>
            </div>
        </div>

        <div class="bm-footer">
            <p class="warning">⚠️ NO WARRANTIES. ALL SALES FINAL. HARDWARE MAY CONTAIN UNKNOWN TRACES.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useEconomyStore } from '@/stores/economy';
import api from '@/api';

const economy = useEconomyStore();
const data = ref({
    available: false,
    closingIn: 0,
    nextOpening: 0,
    deals: []
});

const loading = ref(false);
const selectedDealId = ref(null);

const fetchDeals = async () => {
    try {
        const response = await api.get('/black-market');
        if (response.data.success) {
            data.value = response.data.data;
        }
    } catch (error) {
        console.error('Failed to fetch night deals:', error);
    }
};

const buyItem = async (deal) => {
    if (loading.value) return;
    
    loading.value = true;
    selectedDealId.value = deal.id;

    try {
        const response = await api.post('/black-market/purchase', {
            deal_id: deal.id
        });

        if (response.data.success) {
            // Re-fetch to update deals (and potential duplicates if user tries to spam)
            await fetchDeals();
            // Trigger refresh for economy if needed, though debit handles it via broadcast usually
        } else {
            alert(response.data.error || 'Purchase failed.');
        }
    } catch (error) {
        alert(error.response?.data?.error || 'Network error.');
    } finally {
        loading.value = false;
        selectedDealId.value = null;
    }
};

const formattedTime = computed(() => {
    const mins = data.value.closingIn || 0;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
});

const closingSoon = computed(() => data.value.closingIn < 60);

const getConditionClass = (c) => {
    if (c >= 80) return 'text-success';
    if (c >= 50) return 'text-warning';
    return 'text-danger';
};

const formatNumber = (n) => new Intl.NumberFormat().format(n);

onMounted(() => {
    fetchDeals();
});
</script>

<style scoped>
.black-market-container {
    padding: 24px;
    background: #0a0a0c;
    color: #e0e0e0;
    min-height: 400px;
    font-family: 'Outfit', sans-serif;
}

.bm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid rgba(138, 43, 226, 0.3);
    padding-bottom: 16px;
    margin-bottom: 24px;
}

.bm-title {
    font-size: 1.5rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    color: #8a2be2;
}

.glitch-text {
    position: relative;
    display: inline-block;
}

.bm-timer {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.bm-timer .label {
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.5);
    letter-spacing: 0.1em;
}

.bm-timer .value {
    font-size: 1.4rem;
    font-family: 'JetBrains Mono', monospace;
    color: #00ffcc;
    text-shadow: 0 0 10px rgba(0, 255, 204, 0.5);
}

.timer-critical .value {
    color: #ff3366;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.shop-closed {
    text-align: center;
    padding: 60px 20px;
}

.closed-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.deal-card {
    background: rgba(20, 20, 25, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 16px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.deal-card:hover {
    border-color: rgba(138, 43, 226, 0.5);
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.card-glow {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(138, 43, 226, 0.05) 0%, transparent 70%);
    pointer-events: none;
}

.deal-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    font-weight: 800;
    margin-bottom: 12px;
}

.deal-type {
    background: rgba(138, 43, 226, 0.2);
    padding: 2px 8px;
    border-radius: 4px;
    color: #bb86fc;
}

.deal-name {
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: #fff;
}

.deal-desc {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
    margin-bottom: 20px;
    height: 40px;
    overflow: hidden;
}

.deal-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.price-section .original-price {
    font-size: 0.75rem;
    text-decoration: line-through;
    color: rgba(255, 255, 255, 0.3);
}

.price-section .current-price {
    font-size: 1.3rem;
    font-weight: 900;
    color: #00ffcc;
}

.buy-btn {
    background: #8a2be2;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 900;
    cursor: pointer;
    transition: all 0.2s;
}

.buy-btn:hover:not(:disabled) {
    background: #9932cc;
    box-shadow: 0 0 15px rgba(138, 43, 226, 0.6);
}

.buy-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.risk-indicator {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.05);
    height: 12px;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
}

.risk-bar {
    height: 100%;
    background: linear-gradient(90deg, #ff9900, #ff3300);
}

.risk-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.5rem;
    font-weight: 900;
    color: #fff;
    white-space: nowrap;
}

.bm-footer {
    margin-top: 32px;
    text-align: center;
    padding-top: 16px;
    border-top: 1px dashed rgba(255, 255, 255, 0.1);
}

.warning {
    font-size: 0.65rem;
    color: #ff3366;
    letter-spacing: 0.05em;
    font-weight: 700;
}

.text-success { color: #00ffcc; }
.text-warning { color: #ffcc00; }
.text-danger { color: #ff3366; }
</style>
