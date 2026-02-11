<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="finance-overlay">
            <div class="overlay-header">
                <h2>Financial History</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="history-content">
                <div v-if="loading" class="loading-state">
                    Loading transactions...
                </div>
                
                <div v-else-if="transactions.length === 0" class="empty-state">
                    No transactions found.
                </div>

                <div v-else class="table-container">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tx in transactions" :key="tx.id" class="tx-row">
                                <td class="col-time">{{ formatDate(tx.created_at) }}</td>
                                <td class="col-cat">
                                    <span class="badge" :class="getCategoryClass(tx.category)">
                                        {{ formatCategory(tx.category) }}
                                    </span>
                                </td>
                                <td class="col-desc">{{ tx.description }}</td>
                                <td class="col-amount text-right" :class="tx.amount >= 0 ? 'text-success' : 'text-danger'">
                                    {{ tx.amount >= 0 ? '+' : '-' }} ${{ formatMoney(Math.abs(tx.amount)) }}
                                </td>
                                <td class="col-balance text-right font-mono text-muted">
                                    ${{ formatMoney(tx.balance_after) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overlay-footer">
                <button class="btn-nav" :disabled="page <= 1 || loading" @click="changePage(page - 1)">
                    &larr; Newer
                </button>
                <span class="page-info">Page {{ page }}</span>
                <button class="btn-nav" :disabled="!hasMore || loading" @click="changePage(page + 1)">
                    Older &rarr;
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';

const emit = defineEmits(['close']);
const gameStore = useGameStore();

const transactions = ref([]);
const page = ref(1);
const hasMore = ref(false);
const loading = ref(true);

onMounted(() => {
    loadPage(1);
});

async function loadPage(newPage) {
    loading.value = true;
    const data = await gameStore.loadTransactions(newPage);
    if (data) {
        transactions.value = data.data;
        page.value = data.current_page;
        hasMore.value = data.next_page_url !== null;
    }
    loading.value = false;
}

function changePage(newPage) {
    loadPage(newPage);
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString(undefined, { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function formatMoney(value) {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCategory(cat) {
    return cat.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function getCategoryClass(cat) {
    const map = {
        'income': 'badge-success',
        'expense': 'badge-danger',
        'hardware': 'badge-warning',
        'infrastructure': 'badge-info',
        'maintenance': 'badge-secondary',
        'event_mitigation': 'badge-danger',
        'research': 'badge-primary',
        'real_estate': 'badge-info'
    };
    return map[cat] || 'badge-secondary';
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.finance-overlay {
    width: 800px;
    max-width: 95vw;
    height: 80vh;
    background: #0d1117;
    border: 1px solid #30363d;
    box-shadow: 0 0 50px rgba(0,0,0,0.5);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 15px 20px;
    border-bottom: 1px solid #30363d;
    background: #161b22;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.overlay-header h2 {
    margin: 0;
    color: #e6edf3;
    font-size: 1.2rem;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #fff; }

.history-content {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.table-container {
    flex: 1;
    overflow-y: auto;
}

.tx-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.tx-table th {
    text-align: left;
    padding: 12px 15px;
    background: #161b22;
    color: #8b949e;
    font-weight: 600;
    position: sticky;
    top: 0;
    border-bottom: 1px solid #30363d;
}

.tx-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #21262d;
    color: #c9d1d9;
}

.tx-row:hover {
    background: rgba(56, 139, 253, 0.05);
}

.text-right { text-align: right; }
.text-success { color: #2ea043; }
.text-danger { color: #f85149; }
.text-muted { color: #8b949e; }
.font-mono { font-family: monospace; }

.badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #21262d;
    color: #c9d1d9;
    border: 1px solid #30363d;
}

.badge-success { background: rgba(46, 160, 67, 0.15); color: #3fb950; border-color: rgba(46, 160, 67, 0.4); }
.badge-danger { background: rgba(248, 81, 73, 0.15); color: #f85149; border-color: rgba(248, 81, 73, 0.4); }
.badge-warning { background: rgba(210, 153, 34, 0.15); color: #e3b341; border-color: rgba(210, 153, 34, 0.4); }
.badge-info { background: rgba(56, 139, 253, 0.15); color: #58a6ff; border-color: rgba(56, 139, 253, 0.4); }
.badge-primary { background: rgba(163, 113, 247, 0.15); color: #d2a8ff; border-color: rgba(163, 113, 247, 0.4); }

.overlay-footer {
    padding: 15px;
    border-top: 1px solid #30363d;
    background: #161b22;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    border-radius: 0 0 8px 8px;
}

.btn-nav {
    padding: 5px 15px;
    background: #21262d;
    border: 1px solid #30363d;
    color: #c9d1d9;
    border-radius: 4px;
    cursor: pointer;
}
.btn-nav:hover:not(:disabled) {
    background: #30363d;
    color: #fff;
}
.btn-nav:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-info {
    color: #8b949e;
    font-size: 0.9rem;
}

.loading-state, .empty-state {
    padding: 40px;
    text-align: center;
    color: #8b949e;
}
</style>
