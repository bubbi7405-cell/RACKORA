<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="customers-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">👥</span>
                    <h2>Customer Relations</h2>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <div class="customer-stats">
                    <div class="stat-box">
                        <label>Total Managed</label>
                        <div class="value">{{ customers.total || 0 }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Satisfaction</label>
                        <div class="value" :class="avgSatisfactionClass">{{ avgSatisfaction }}%</div>
                    </div>
                    <div class="stat-box">
                        <label>Monthly Revenue</label>
                        <div class="value text-success">${{ monthlyRevenue.toLocaleString() }}</div>
                    </div>
                </div>

                <div class="customer-list-container">
                    <div class="list-header">
                        <span>Customer Name</span>
                        <span>Status</span>
                        <span>Satisfaction</span>
                        <span>Actions</span>
                    </div>

                    <div v-if="customers.list && customers.list.length > 0" class="customer-list">
                        <div v-for="customer in customers.list" :key="customer.id" class="customer-row">
                            <div class="cust-info">
                                <div class="cust-name">{{ customer.name }}</div>
                                <div class="cust-sub">{{ customer.activeOrdersCount }} active instances</div>
                            </div>
                            <div class="cust-status">
                                <span class="status-badge" :class="customer.status">
                                    {{ customer.status }}
                                </span>
                            </div>
                            <div class="cust-sat">
                                <div class="sat-bar-container">
                                    <div class="sat-bar" :style="{ width: customer.satisfaction + '%' }" :class="getSatClass(customer.satisfaction)"></div>
                                </div>
                                <span>{{ Math.round(customer.satisfaction) }}%</span>
                            </div>
                            <div class="cust-actions">
                                <button class="btn-inspect" @click="inspectCustomer(customer)">Details</button>
                            </div>
                        </div>

                        <!-- Expanded order details -->
                        <div v-if="selectedCustomer?.id === customer.id && customerOrders.length > 0" class="customer-orders-expanded">
                            <div v-for="order in customerOrders" :key="order.id" class="order-row">
                                <div class="order-info">
                                    <span class="order-type">{{ order.productType }}</span>
                                    <span class="order-revenue">${{ order.pricePerMonth.toFixed(2) }}/mo</span>
                                    <span class="order-sla">SLA: {{ order.sla?.tier || 'standard' }}</span>
                                </div>
                                <button
                                    class="btn-cancel-order"
                                    @click="handleCancelOrder(order.id)"
                                    :disabled="cancellingId === order.id"
                                >
                                    {{ cancellingId === order.id ? 'Cancelling...' : '✕ Cancel Contract' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-state">
                        <p>No active customers yet. Servers are idling.</p>
                        <button class="btn-primary" @click="$emit('close')">Back to Ops</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const gameStore = useGameStore();
const customers = computed(() => gameStore.customers || { total: 0, list: [] });

const selectedCustomer = ref(null);
const customerOrders = ref([]);
const cancellingId = ref(null);

const avgSatisfaction = computed(() => {
    if (!customers.value.list || customers.value.list.length === 0) return 0;
    const sum = customers.value.list.reduce((acc, c) => acc + c.satisfaction, 0);
    return Math.round(sum / customers.value.list.length);
});

const avgSatisfactionClass = computed(() => {
    if (avgSatisfaction.value > 80) return 'text-success';
    if (avgSatisfaction.value > 40) return 'text-warning';
    return 'text-danger';
});

const monthlyRevenue = computed(() => {
    if (!customers.value.list) return 0;
    return customers.value.list.reduce((acc, c) => acc + (c.revenuePerMonth || 0), 0);
});

const getSatClass = (sat) => {
    if (sat > 80) return 'good';
    if (sat > 40) return 'ok';
    return 'poor';
};

const inspectCustomer = async (customer) => {
    if (selectedCustomer.value?.id === customer.id) {
        selectedCustomer.value = null;
        customerOrders.value = [];
        return;
    }
    selectedCustomer.value = customer;
    try {
        const response = await api.get('/orders');
        if (response.success) {
            customerOrders.value = response.data.active.filter(
                o => o.customerId === customer.id
            );
        }
    } catch (e) {
        customerOrders.value = [];
    }
};

const handleCancelOrder = async (orderId) => {
    cancellingId.value = orderId;
    const result = await gameStore.cancelOrder(orderId);
    cancellingId.value = null;
    if (result) {
        customerOrders.value = customerOrders.value.filter(o => o.id !== orderId);
    }
};
</script>

<style scoped>
.customers-overlay {
    width: 900px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: 12px;
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border);
    background: rgba(var(--color-primary-rgb), 0.05);
}

.header-title { display: flex; align-items: center; gap: 15px; }
.header-title h2 { margin: 0; font-size: 1.4rem; }
.icon { font-size: 1.8rem; }

.overlay-body {
    padding: 25px;
    height: 60vh;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.customer-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-box {
    background: rgba(0,0,0,0.2);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.05);
}

.stat-box label {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: 8px;
    letter-spacing: 1px;
}

.stat-box .value {
    font-size: 1.8rem;
    font-weight: 800;
}

.customer-list-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.list-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr 1fr;
    padding: 12px 20px;
    background: rgba(0,0,0,0.3);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.customer-list {
    overflow-y: auto;
}

.customer-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr 1fr;
    padding: 15px 20px;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.2s;
}

.customer-row:hover {
    background: rgba(var(--color-primary-rgb), 0.03);
}

.cust-name { font-weight: 700; color: var(--color-text-primary); }
.cust-sub { font-size: 0.75rem; color: var(--color-text-muted); }

.status-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 800;
    text-transform: uppercase;
}
.status-badge.active { background: rgba(var(--color-success-rgb), 0.1); color: var(--color-success); border: 1px solid var(--color-success); }
.status-badge.unhappy { background: rgba(var(--color-warning-rgb), 0.1); color: var(--color-warning); border: 1px solid var(--color-warning); }
.status-badge.churning { background: rgba(var(--color-danger-rgb), 0.1); color: var(--color-danger); border: 1px solid var(--color-danger); }

.cust-sat {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sat-bar-container {
    flex: 1;
    height: 6px;
    background: #111;
    border-radius: 3px;
    overflow: hidden;
}

.sat-bar { height: 100%; border-radius: 3px; }
.sat-bar.good { background: var(--color-success); }
.sat-bar.ok { background: var(--color-warning); }
.sat-bar.poor { background: var(--color-danger); }

.btn-inspect {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
}
.btn-inspect:hover { border-color: var(--color-primary); color: var(--color-primary); }

.empty-state {
    padding: 50px;
    text-align: center;
    color: var(--color-text-muted);
}

.btn-primary {
    margin-top: 15px;
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
}

.animation-slide-up {
    animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slide-up {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.text-success { color: var(--color-success); }
.text-warning { color: var(--color-warning); }
.text-danger { color: var(--color-danger); }

.customer-orders-expanded {
    grid-column: 1 / -1;
    padding: 12px 20px 12px 40px;
    background: rgba(0, 0, 0, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.order-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 6px;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.order-info {
    display: flex;
    gap: 16px;
    align-items: center;
    font-size: 0.85rem;
}

.order-type {
    font-weight: 700;
    text-transform: capitalize;
    color: var(--color-text-primary);
}

.order-revenue {
    color: var(--color-success);
    font-family: monospace;
}

.order-sla {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
}

.btn-cancel-order {
    background: transparent;
    border: 1px solid rgba(248, 81, 73, 0.4);
    color: #f85149;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel-order:hover:not(:disabled) {
    background: rgba(248, 81, 73, 0.15);
    border-color: #f85149;
}

.btn-cancel-order:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
