<template>
    <div :class="{ 'overlay-backdrop': !inline }" @click.self="$emit('close')">
        <div class="ledger-modal glass-panel animation-slide-up" :class="{ 'inline-panel': inline }">
            <div class="modal-header">
                <div class="header-title">
                    <span class="icon">📊</span>
                    <h2 class="l1-priority">
                        CAPITAL_DOMINANCE_CONTROLLER // [FIN_SEC]
                        <span class="v3-info-trigger"
                            @mouseenter="tooltipStore.show($event, { title: 'FINANCIAL_LEDGER', content: 'Detailed record of all transactions, hardware costs, and service revenue.', hint: 'Use filters to analyze specific cost centers.' })"
                            @mouseleave="tooltipStore.hide()">ⓘ</span>
                        <small class="l3-priority">CAPITAL_ASSET_ALLOCATION // [PROFIT_MATRIX]</small>
                    </h2>
                </div>
                <div class="header-tabs">
                    <button class="tab-btn l2-priority" :class="{ active: activeTab === 'ledger' }"
                        @click="activeTab = 'ledger'">TRANSACTION_LOG</button>
                    <button class="tab-btn l2-priority" :class="{ active: activeTab === 'stocks' }"
                        @click="activeTab = 'stocks'"
                        @mouseenter="tooltipStore.show($event, { title: 'CAPITAL_SPECULATION', content: 'Speculate on your own stock price. High risk of SEC audit.', hint: 'Profitable during outages or major events.' })"
                        @mouseleave="tooltipStore.hide()">MARKET_DEVIATION_SPECULATION</button>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div v-if="activeTab === 'stocks'" class="stocks-container">
                <div class="market-overview">
                    <div class="stock-ticker shadow-glow">
                        <div class="ticker-info"
                            @mouseenter="tooltipStore.show($event, { title: 'PONY_INDEX', content: 'Your company\'s market valuation based on reputation, revenue, and stability.', hint: 'Fluctuates every game tick.' })"
                            @mouseleave="tooltipStore.hide()">
                            <span class="symbol l1-priority">$PONY</span>
                            <span class="name l3-priority">CODEPONY_CORP // NASDAQ_EXCH</span>
                        </div>
                        <div class="ticker-price">
                            <span class="price l1-priority">${{ stockMarket.stockPrice.toFixed(2) }}</span>
                            <span class="trend l2-priority" :class="stockMarket.stockPrice >= 10 ? 'up' : 'down'">
                                <span class="arrow">{{ stockMarket.stockPrice >= 10 ? '▲' : '▼' }}</span>
                                {{ Math.abs(((stockMarket.stockPrice - 10) / 10) * 100).toFixed(1) }}%
                            </span>
                        </div>
                        <div v-if="stockMarket.isFrozen" class="freeze-alert"
                            @mouseenter="tooltipStore.show($event, { title: 'ASSETS_FROZEN', content: 'The SEC has halted all trading due to suspicious activity (Short-Selling).', hint: 'You cannot open or close positions until this expires.' })"
                            @mouseleave="tooltipStore.hide()">
                            <span class="alert-icon">⚖️</span>
                            <span class="alert-text">ASSETS FROZEN BY SEC (until {{ formatTime(stockMarket.freezeEndsAt)
                                }})</span>
                        </div>
                    </div>

                    <div class="trading-box glass-panel--accent">
                        <h3 class="l1-priority">
                            SHORT_SELL_INITIATIVE // [LIQUIDITY_CHECK]
                            <span class="v3-info-trigger"
                                @mouseenter="tooltipStore.show($event, { title: 'SHORT_SELLING', content: 'Borrow shares now to sell at current price. Return them later at (hopefully) lower price to profit.', hint: 'High risk of audit (30%) if you short during an incident.' })"
                                @mouseleave="tooltipStore.hide()">ⓘ</span>
                        </h3>
                        <p class="hint l3-priority">Borrow shares and sell them immediately. Profit if the price drops.
                            30% Audit Risk.</p>

                        <div class="input-row">
                            <div class="input-group">
                                <label>Shares to Short</label>
                                <input type="number" v-model="orderShares" min="10" step="10">
                            </div>
                            <div class="order-preview">
                                <div class="preview-item">
                                    <span class="label">Est. Collateral (40%)</span>
                                    <span class="value">${{ formatMoney(orderShares * stockMarket.stockPrice * 0.4)
                                        }}</span>
                                </div>
                            </div>
                            <button class="btn-order shadow-danger l2-priority"
                                :disabled="stockMarket.isFrozen || orderShares < 10 || loading" @click="handleShort">
                                EXECUTE_SHORT_POSITION
                            </button>
                        </div>
                    </div>
                </div>

                <div class="positions-list">
                    <div class="section-title l2-priority">
                        ACTIVE_LIABILITY_POSITIONS
                        <span class="v3-info-trigger"
                            @mouseenter="tooltipStore.show($event, { title: 'ACTIVE_POSITIONS', content: 'Track your current liabilities. You must eventually close these by buying back shares.', hint: 'If price rises significantly, you will lose money on repayment.' })"
                            @mouseleave="tooltipStore.hide()">ⓘ</span>
                    </div>
                    <div class="positions-grid">
                        <div v-for="pos in stockMarket.shortPositions" :key="pos.id"
                            class="position-card shadow-glow--dim">
                            <div class="pos-main">
                                <div class="pos-info">
                                    <span class="shares">{{ pos.shares }} Shares</span>
                                    <span class="entry">Entry: ${{ pos.entry_price.toFixed(2) }}</span>
                                </div>
                                <div class="pos-profit" :class="calculateProfit(pos) >= 0 ? 'up' : 'down'">
                                    <span class="val">{{ calculateProfit(pos) >= 0 ? '+' : '' }}${{
                                        formatMoney(calculateProfit(pos)) }}</span>
                                    <span class="label">P/L REAL-TIME</span>
                                </div>
                            </div>
                            <button class="btn-close-pos" :disabled="stockMarket.isFrozen || loading"
                                @click="handleClose(pos.id)">
                                CLOSE & REPAY
                            </button>
                        </div>
                        <div v-if="stockMarket.shortPositions.length === 0" class="no-positions">
                            No active short positions.
                        </div>
                    </div>
                </div>
            </div>

            <template v-else>
                <div class="modal-summary">
                    <div class="summary-card income"
                        @mouseenter="tooltipStore.show($event, { title: 'TOTAL_INCOME', content: 'Sum of all revenue from customer orders and node operations.', hint: 'Measured over the last period.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="label l3-priority">TOTAL_INCOME // [LAST_PERIOD]</span>
                        <span class="value l1-priority">+${{ formatMoney(summary.totalIncome) }}</span>
                    </div>
                    <div class="summary-card expenses"
                        @mouseenter="tooltipStore.show($event, { title: 'TOTAL_EXPENSES', content: 'Sum of all hardware maintenance, electricity, payroll, and SLA penalties.', hint: 'High hardware density increases maintenance costs.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="label l3-priority">TOTAL_EXPENSES // [LAST_PERIOD]</span>
                        <span class="value l1-priority">-${{ formatMoney(summary.totalExpenses) }}</span>
                    </div>
                    <div class="summary-card net"
                        :class="{ positive: summary.netProfit >= 0, negative: summary.netProfit < 0 }"
                        @mouseenter="tooltipStore.show($event, { title: 'NET_PERFORMANCE', content: 'Your total profitability. If negative, you are burning capital.', hint: 'Green means growth.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="label l3-priority">AGGREGATE_CAPITAL_FLOW // [ACTIVE]</span>
                        <span class="value l1-priority">{{ summary.netProfit >= 0 ? '+' : '' }}${{
                            formatMoney(summary.netProfit) }}</span>
                    </div>
                </div>

                <div class="modal-controls">
                    <div class="filter-group">
                        <div class="filter-select">
                            <label>Category</label>
                            <select v-model="filters.category" @change="fetchTransactions(1)">
                                <option value="">All Categories</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ formatLabel(cat) }}
                                </option>
                            </select>
                        </div>
                        <div class="filter-select">
                            <label>Type</label>
                            <select v-model="filters.type" @change="fetchTransactions(1)">
                                <option value="">All Types</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="filter-select">
                            <label>Time Range</label>
                            <select v-model="filters.hours" @change="fetchTransactions(1)">
                                <option :value="1">Last 1 Hour</option>
                                <option :value="6">Last 6 Hours</option>
                                <option :value="24">Last 24 Hours</option>
                                <option :value="168">Last 7 Days</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-refresh" @click="fetchTransactions(1)" :disabled="loading"
                        @mouseenter="tooltipStore.show($event, { title: 'SYNC_LEDGER', content: 'Synchronizes your local records with the central banking authority.', hint: 'Use if data feels stale.' })"
                        @mouseleave="tooltipStore.hide()">
                        {{ loading ? 'Updating...' : 'Refresh Ledger' }}
                    </button>
                </div>

                <div class="modal-body">
                    <div class="ledger-table-header">
                        <span class="col-time">Timestamp</span>
                        <span class="col-cat">Category</span>
                        <span class="col-desc">Description</span>
                        <span class="col-amount">Amount</span>
                    </div>

                    <div class="ledger-list" v-if="!loading">
                        <div v-for="tx in transactions" :key="tx.id" class="ledger-row" :class="tx.type">
                            <span class="col-time">{{ formatTime(tx.created_at) }}</span>
                            <span class="col-cat">
                                <span class="cat-badge" :style="{ '--cat-color': getCategoryColor(tx.category) }"
                                    @mouseenter="tooltipStore.show($event, { title: formatLabel(tx.category), content: 'Financial type: ' + tx.type, hint: 'Category ID: ' + tx.category })"
                                    @mouseleave="tooltipStore.hide()">
                                    {{ tx.category }}
                                </span>
                            </span>
                            <span class="col-desc">{{ tx.description }}</span>
                            <span class="col-amount" :class="tx.type">
                                {{ tx.type === 'income' ? '+' : '-' }}${{ Math.abs(tx.amount).toLocaleString() }}
                            </span>
                        </div>

                        <div v-if="transactions.length === 0" class="no-data">
                            No transactions found for the selected filters.
                        </div>
                    </div>

                    <div v-else class="loading-state">
                        <div class="loader"></div>
                        <span>Decrypting financial data...</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="pagination">
                        <button :disabled="page <= 1" @click="fetchTransactions(page - 1)">PREV</button>
                        <span>Page {{ page }} of {{ lastPage }}</span>
                        <button :disabled="page >= lastPage" @click="fetchTransactions(page + 1)">NEXT</button>
                    </div>
                    <div class="total-count">
                        {{ totalItems }} TRANSACTIONS LOGGED
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useEconomyStore } from '../../stores/economy';
import { useTooltipStore } from '../../stores/tooltip';

const props = defineProps({
    inline: { type: Boolean, default: false }
});

const emit = defineEmits(['close']);
const economyStore = useEconomyStore();
const tooltipStore = useTooltipStore();

const activeTab = ref('ledger');
const orderShares = ref(100);
const stockMarket = computed(() => economyStore.stockMarket);

const loading = ref(true);
const transactions = ref([]);
const page = ref(1);
const lastPage = ref(1);
const totalItems = ref(0);

const summary = reactive({
    totalIncome: 0,
    totalExpenses: 0,
    netProfit: 0
});

const filters = reactive({
    category: '',
    type: '',
    hours: 24
});

const categories = ['income', 'hardware', 'infrastructure', 'maintenance', 'real_estate', 'sla_penalty', 'marketing', 'research', 'employees', 'utility', 'energy'];

const fetchTransactions = async (newPage = 1) => {
    loading.value = true;
    try {
        const res = await economyStore.loadTransactions(newPage, filters);
        if (res) {
            transactions.value = res.transactions.data;
            page.value = res.transactions.current_page;
            lastPage.value = res.transactions.last_page;
            totalItems.value = res.transactions.total;

            summary.totalIncome = res.summary.totalIncome;
            summary.totalExpenses = res.summary.totalExpenses;
            summary.netProfit = res.summary.netProfit;
        }
    } catch (e) {
        console.error("Failed to fetch ledger", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchTransactions();
    economyStore.loadStockMarketData();
});

const handleShort = async () => {
    if (loading.value) return;
    loading.value = true;
    try {
        await economyStore.shortOwnStock(orderShares.value);
    } finally {
        loading.value = false;
    }
};

const handleClose = async (posId) => {
    if (loading.value) return;
    loading.value = true;
    try {
        await economyStore.closeShortPosition(posId);
    } finally {
        loading.value = false;
    }
};

const calculateProfit = (pos) => {
    return (pos.entry_price - stockMarket.value.stockPrice) * pos.shares;
};

const formatTime = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' ' + d.toLocaleDateString([], { day: '2-digit', month: 'short' });
};

const formatMoney = (val) => {
    return Math.abs(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatLabel = (str) => {
    return str.replace(/_/g, ' ').toUpperCase();
};

const getCategoryColor = (cat) => {
    const map = {
        income: '#4ade80',
        hardware: '#60a5fa',
        infrastructure: '#f472b6',
        maintenance: '#fbbf24',
        real_estate: '#a78bfa',
        sla_penalty: '#f87171',
        marketing: '#2dd4bf',
        research: '#818cf8',
        employees: '#fb923c',
        utility: '#94a3b8',
        energy: '#f97316'
    };
    return map[cat] || '#ffffff';
};
</script>

<style scoped>
.ledger-modal {
    width: 900px;
    max-width: 95vw;
    height: 80vh;
    background: var(--v3-bg-base);
    border: var(--v3-border-heavy);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--v3-radius);
    box-shadow: 0 50px 100px rgba(0, 0, 0, 0.6);
}

.ledger-modal.inline-panel {
    width: 100%;
    max-width: none;
    height: 100%;
    background: transparent;
    border: none;
    box-shadow: none;
}

.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-btn {
    background: none;
    border: none;
    color: var(--v3-text-ghost);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    padding: 8px;
    transition: all 0.2s;
}

.close-btn:hover {
    color: #fff;
    transform: rotate(90deg);
}

.modal-header {
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: var(--v3-border-soft);
}

.header-title h2 {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #fff;
    text-transform: uppercase;
}

.header-title small {
    display: block;
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
    margin-top: 4px;
    letter-spacing: 0.05em;
}

.modal-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: var(--v3-border-soft);
    border-bottom: var(--v3-border-soft);
}

.summary-card {
    background: var(--v3-bg-base);
    padding: 20px 32px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.summary-card .label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.summary-card .value {
    font-size: 1.25rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
}

.summary-card.income .value {
    color: var(--v3-success);
}

.summary-card.expenses .value {
    color: var(--v3-danger);
}

.summary-card.positive .value {
    color: var(--v3-success);
}

.summary-card.negative .value {
    color: var(--v3-danger);
}

.modal-controls {
    padding: 16px 32px;
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: var(--v3-border-soft);
}

.filter-group {
    display: flex;
    gap: 24px;
}

.filter-select {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-select label {
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
}

.filter-select select {
    background: #000;
    color: #fff;
    border: 1px solid #333;
    padding: 6px 12px;
    font-size: 0.65rem;
    font-weight: 800;
    border-radius: 2px;
}

.btn-refresh {
    background: transparent;
    color: var(--v3-text-secondary);
    border: 1px solid #333;
    padding: 8px 20px;
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-refresh:hover {
    border-color: var(--v3-accent);
    color: #fff;
}

.modal-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ledger-table-header {
    display: grid;
    grid-template-columns: 140px 140px 1fr 140px;
    padding: 12px 32px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: var(--v3-border-soft);
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.ledger-list {
    flex: 1;
    overflow-y: auto;
}

.ledger-row {
    display: grid;
    grid-template-columns: 140px 140px 1fr 140px;
    padding: 12px 32px;
    border-bottom: var(--v3-border-soft);
    align-items: center;
    transition: background 0.1s;
}

.ledger-row:hover {
    background: rgba(255, 255, 255, 0.015);
}

.col-time {
    font-family: var(--font-family-mono);
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
}

.col-cat .cat-badge {
    font-size: 0.5rem;
    font-weight: 900;
    text-transform: uppercase;
    padding: 2px 6px;
    border: 1px solid var(--cat-color);
    color: var(--cat-color);
    border-radius: 2px;
}

.col-desc {
    font-size: 0.72rem;
    color: var(--v3-text-secondary);
}

.col-amount {
    font-size: 0.75rem;
    font-weight: 900;
    text-align: right;
    font-family: var(--font-family-mono);
}

.col-amount.income {
    color: var(--v3-success);
}

.col-amount.expense {
    color: var(--v3-danger);
}

.modal-footer {
    padding: 16px 32px;
    border-top: var(--v3-border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0, 0, 0, 0.1);
}

.pagination {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
}

.pagination button {
    background: #000;
    border: 1px solid #333;
    color: #fff;
    padding: 4px 12px;
    font-size: 0.55rem;
    font-weight: 900;
    cursor: pointer;
}

.pagination button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.total-count {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.loading-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    color: var(--v3-text-ghost);
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
}

.loader {
    width: 24px;
    height: 24px;
    border: 2px solid #333;
    border-top-color: var(--v3-accent);
    border-radius: 50%;
    animation: v3-spin 1s linear infinite;
}

@keyframes v3-spin {
    to {
        transform: rotate(360deg);
    }
}

.no-data {
    padding: 60px;
    text-align: center;
    color: var(--v3-text-ghost);
    font-size: 0.7rem;
    letter-spacing: 0.1em;
}

/* Stock Market Styles */
.header-tabs {
    display: flex;
    gap: 8px;
    margin-left: 40px;
}

.tab-btn {
    background: transparent;
    border: 1px solid #333;
    color: var(--v3-text-ghost);
    padding: 6px 16px;
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.tab-btn.active {
    background: var(--v3-accent);
    color: #000;
    border-color: var(--v3-accent);
}

.stocks-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding: 32px;
    gap: 32px;
}

.market-overview {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.stock-ticker {
    background: #000;
    border: var(--v3-border-heavy);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
}

.ticker-info {
    display: flex;
    flex-direction: column;
}

.symbol {
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--v3-accent);
}

.name {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
}

.ticker-price {
    display: flex;
    align-items: baseline;
    gap: 16px;
}

.price {
    font-size: 2rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
}

.trend {
    font-size: 0.8rem;
    font-weight: 900;
}

.trend.up {
    color: var(--v3-success);
}

.trend.down {
    color: var(--v3-danger);
}

.freeze-alert {
    margin-top: 12px;
    padding: 8px;
    background: rgba(248, 113, 113, 0.1);
    border: 1px solid var(--v3-danger);
    color: var(--v3-danger);
    font-size: 0.6rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 8px;
}

.trading-box {
    padding: 24px;
}

.trading-box h3 {
    margin: 0 0 8px 0;
    font-size: 0.9rem;
    font-weight: 900;
    text-transform: uppercase;
    color: var(--v3-accent);
}

.hint {
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    margin-bottom: 24px;
    line-height: 1.4;
}

.input-row {
    display: flex;
    align-items: flex-end;
    gap: 24px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.input-group label {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
}

.input-group input {
    background: #000;
    border: 1px solid #333;
    color: #fff;
    padding: 10px;
    font-family: var(--font-family-mono);
    font-weight: 900;
    width: 120px;
}

.order-preview {
    flex: 1;
}

.preview-item {
    display: flex;
    flex-direction: column;
}

.preview-item .label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
}

.preview-item .value {
    font-size: 0.9rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
    color: #fff;
}

.btn-order {
    background: var(--v3-danger);
    color: #fff;
    border: none;
    padding: 12px 24px;
    font-weight: 900;
    text-transform: uppercase;
    cursor: pointer;
    border-radius: 2px;
}

.btn-order:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.positions-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.section-title {
    font-size: 0.7rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.positions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.position-card {
    background: rgba(255, 255, 255, 0.03);
    border: var(--v3-border-soft);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pos-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pos-info {
    display: flex;
    flex-direction: column;
}

.shares {
    font-size: 0.9rem;
    font-weight: 900;
    color: #fff;
}

.entry {
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
    font-family: var(--font-family-mono);
}

.pos-profit {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pos-profit .val {
    font-size: 1.1rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
}

.pos-profit.up .val {
    color: var(--v3-success);
}

.pos-profit.down .val {
    color: var(--v3-danger);
}

.pos-profit .label {
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
}

.btn-close-pos {
    background: transparent;
    border: 1px solid #333;
    color: #fff;
    padding: 8px;
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    cursor: pointer;
}

.btn-close-pos:hover:not(:disabled) {
    border-color: var(--v3-accent);
    color: var(--v3-accent);
}

.no-positions {
    padding: 20px;
    color: var(--v3-text-ghost);
    font-size: 0.7rem;
    text-align: center;
    border: 1px dashed #333;
    grid-column: span 2;
}

.v3-info-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(88, 166, 255, 0.15);
    color: #58a6ff;
    font-size: 10px;
    font-weight: 800;
    cursor: help;
    margin-left: 6px;
    vertical-align: middle;
    border: 1px solid rgba(88, 166, 255, 0.3);
    transition: all 0.2s;
}

.v3-info-trigger:hover {
    background: #58a6ff;
    color: #05070a;
    box-shadow: 0 0 10px rgba(88, 166, 255, 0.4);
}
</style>
