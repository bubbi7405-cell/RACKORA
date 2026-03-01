<template>
    <div :class="{ 'overlay-backdrop': !inline }" @click.self="$emit('close')">
        <div class="customers-overlay" :class="{ 'glass-panel animation-slide-up': !inline, 'inline-panel': inline }">
            <div class="overlay-header" v-if="!inline">
                <div class="header-title">
                    <span class="icon">👥</span>
                    <h2>Kundenbeziehungen</h2>
                    <span class="customer-count" v-if="customers.total">{{ customers.total }} Kunden</span>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <!-- Top Stats -->
                <div class="customer-stats">
                    <div class="stat-box">
                        <label>Gesamt verwaltet</label>
                        <div class="value">{{ customers.total || 0 }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Zufriedenheit</label>
                        <div class="value" :class="avgSatisfactionClass">{{ avgSatisfaction }}%</div>
                    </div>
                    <div class="stat-box">
                        <label>Monatl. Umsatz</label>
                        <div class="value text-success">${{ monthlyRevenue.toLocaleString() }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Ø Loyalität</label>
                        <div class="value" :class="avgLoyaltyClass">{{ avgLoyalty }}%</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>REGION</label>
                        <select v-model="filterRegion" class="filter-select">
                            <option value="">Alle Regionen</option>
                            <option v-for="(data, key) in gameStore.regions" :key="key" :value="key">
                                {{ data.flag }} {{ data.name }}
                            </option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>TIER</label>
                        <select v-model="filterTier" class="filter-select">
                            <option value="">Alle Tiers</option>
                            <option value="diamond">💎 Diamond</option>
                            <option value="enterprise">🏢 Enterprise</option>
                            <option value="silver">🥈 Silver</option>
                            <option value="bronze">🥉 Bronze</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>STATUS</label>
                        <select v-model="filterStatus" class="filter-select">
                            <option value="">Alle</option>
                            <option value="active">Aktiv</option>
                            <option value="unhappy">Unzufrieden</option>
                            <option value="churning">Abwandernd</option>
                        </select>
                    </div>
                    <div class="filter-count">{{ filteredCustomers.length }} Ergebnisse</div>
                </div>

                <!-- Customer List -->
                <div class="customer-list-container">
                    <div class="list-header">
                        <span>Unternehmen</span>
                        <span>Region</span>
                        <span>Tier</span>
                        <span>Status</span>
                        <span>Zufriedenheit</span>
                        <span>Loyalität</span>
                        <span></span>
                    </div>

                    <div v-if="filteredCustomers.length > 0" class="customer-list">
                        <template v-for="customer in filteredCustomers" :key="customer?.id || Math.random()">
                            <div v-if="customer" class="customer-row" @click="inspectCustomer(customer)">
                                <!-- Brand + Name -->
                                <div class="cust-brand-wrapper">
                                    <div class="cust-brand" :style="{
                                        backgroundColor: customer.brand?.color || '#1e293b',
                                        borderRadius: customer.brand?.shape === 'circle' ? '50%' : (customer.brand?.shape === 'rounded' ? '8px' : '2px')
                                    }">
                                        {{ getCompanyInitial(customer.companyName) }}
                                    </div>
                                    <div class="cust-info">
                                        <div class="cust-name" :title="'Kontakt: ' + customer.name">{{ customer.companyName }}</div>
                                        <div class="cust-sub">{{ customer.activeOrdersCount }} aktive Instanzen</div>
                                    </div>
                                </div>

                                <!-- Region -->
                                <div class="cust-region">
                                    <span class="region-flag-mini">{{ customer.region?.flag || '🌍' }}</span>
                                    <span class="region-key">{{ (customer.region?.key || '—').toUpperCase() }}</span>
                                </div>

                                <!-- Tier -->
                                <div class="cust-tier">
                                    <span class="tier-badge" :class="customer.tier">
                                        {{ getTierIcon(customer.tier) }} {{ customer.tier }}
                                    </span>
                                </div>

                                <!-- Status -->
                                <div class="cust-status">
                                    <span class="status-badge" :class="customer.status">
                                        {{ getStatusLabel(customer.status) }}
                                    </span>
                                </div>

                                <!-- Satisfaction -->
                                <div class="cust-sat">
                                    <div class="sat-bar-container">
                                        <div class="sat-bar" :style="{ width: customer.satisfaction + '%' }"
                                            :class="getSatClass(customer.satisfaction)"></div>
                                    </div>
                                    <span>{{ Math.round(customer.satisfaction) }}%</span>
                                </div>

                                <!-- Loyalty -->
                                <div class="cust-loyalty">
                                    <div class="loyalty-bar-container">
                                        <div class="loyalty-bar" :style="{ width: ((customer.loyaltyScore || 0) * 100) + '%' }"
                                            :class="getLoyaltyClass(customer.loyaltyScore)"></div>
                                    </div>
                                    <span>{{ Math.round((customer.loyaltyScore || 0) * 100) }}%</span>
                                </div>

                                <!-- Expand Arrow -->
                                <div class="cust-expand">
                                    <span :class="{ 'rotated': selectedCustomer?.id === customer.id }">▸</span>
                                </div>
                            </div>

                            <!-- Expanded customer details -->
                            <div v-if="selectedCustomer?.id === customer.id" class="customer-details-expanded glass-panel">
                                <div class="details-grid">
                                    <!-- Sidebar -->
                                    <div class="details-sidebar">
                                        <!-- Region Card -->
                                        <div class="detail-section">
                                            <h4>HERKUNFTS_REGION</h4>
                                            <div class="region-detail-card">
                                                <span class="region-flag-large">{{ customer.region?.flag || '🌍' }}</span>
                                                <div>
                                                    <div class="region-detail-name">{{ customer.region?.name || 'Unbekannt' }}</div>
                                                    <div class="region-detail-key">{{ (customer.region?.key || '').toUpperCase() }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Customer Flags -->
                                        <div class="detail-section">
                                            <h4>KUNDEN_PROFIL</h4>
                                            <div class="flag-tags">
                                                <span v-if="customer.flags?.isComplianceHeavy" class="flag-tag compliance">🛡️ DSGVO-Fokus</span>
                                                <span v-if="customer.flags?.isPerformanceFocused" class="flag-tag performance">⚡ Performance</span>
                                                <span v-if="customer.flags?.isEcoFocused" class="flag-tag eco">🌿 Öko-bewusst</span>
                                                <span v-if="!customer.flags?.isComplianceHeavy && !customer.flags?.isPerformanceFocused && !customer.flags?.isEcoFocused" class="flag-tag neutral">📦 Standard</span>
                                            </div>
                                        </div>

                                        <!-- Contracts -->
                                        <div class="detail-section">
                                            <h4>VERTRÄGE</h4>
                                            <div v-for="order in customerOrders" :key="order.id" class="order-mini-row">
                                                <div class="o-type">{{ order.productType }}</div>
                                                <div class="o-price">${{ (order.pricePerMonth || 0).toLocaleString() }}</div>
                                                <button class="o-cancel" @click.stop="handleCancelOrder(order.id)" :disabled="cancellingId === order.id">✕</button>
                                            </div>
                                            <div v-if="customerOrders.length === 0" class="empty-hint">Keine aktiven Verträge.</div>
                                        </div>

                                        <!-- PR Action -->
                                        <div class="detail-section">
                                            <h4>REPUTATIONS_MGMT</h4>
                                            <button class="btn-pr-stunt" @click.stop="handlePrOutreach(customer)" :disabled="customer.satisfaction >= 95 || isProcessingPr === customer.id">
                                                {{ isProcessingPr === customer.id ? 'WIRD AUSGEFÜHRT...' : 'PR_MASSNAHME (-$2.500)' }}
                                            </button>
                                            <p class="hint">Verbessert Zufriedenheit sofort um +15%.</p>
                                        </div>
                                    </div>

                                    <!-- Main: Reviews -->
                                    <div class="details-main">
                                        <div class="detail-section">
                                            <h4>KUNDENFEEDBACK</h4>
                                            <div class="reviews-list">
                                                <div v-for="review in customer.recentReviews || []" :key="review.id" class="review-item" :class="review.sentiment">
                                                    <div class="review-header">
                                                        <span class="stars">
                                                            <span v-for="i in 5" :key="i" class="star" :class="{ 'filled': i <= review.rating }">★</span>
                                                        </span>
                                                        <span class="date">{{ formatDate(review.createdAt) }}</span>
                                                    </div>
                                                    <div class="review-content">"{{ review.content }}"</div>
                                                </div>
                                                <div v-if="!customer.recentReviews?.length" class="empty-hint">Noch kein Feedback eingegangen.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div v-else class="empty-state">
                        <p>Noch keine aktiven Kunden. Die Server warten auf Aufträge.</p>
                        <button class="btn-primary" @click="$emit('close')">Zurück zum Betrieb</button>
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

const props = defineProps({
    inline: { type: Boolean, default: false }
});

const gameStore = useGameStore();
const customers = computed(() => gameStore.customers || { total: 0, list: [] });

const selectedCustomer = ref(null);
const customerOrders = ref([]);
const cancellingId = ref(null);
const isProcessingPr = ref(null);

// Filters
const filterRegion = ref('');
const filterTier = ref('');
const filterStatus = ref('');

const filteredCustomers = computed(() => {
    let list = customers.value.list || [];
    if (filterRegion.value) {
        list = list.filter(c => c.region?.key === filterRegion.value);
    }
    if (filterTier.value) {
        list = list.filter(c => c.tier === filterTier.value);
    }
    if (filterStatus.value) {
        list = list.filter(c => c.status === filterStatus.value);
    }
    return list;
});

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

const avgLoyalty = computed(() => {
    if (!customers.value.list || customers.value.list.length === 0) return 0;
    const sum = customers.value.list.reduce((acc, c) => acc + (c.loyaltyScore || 0), 0);
    return Math.round((sum / customers.value.list.length) * 100);
});

const avgLoyaltyClass = computed(() => {
    if (avgLoyalty.value > 70) return 'text-success';
    if (avgLoyalty.value > 40) return 'text-warning';
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

const getLoyaltyClass = (score) => {
    if (score > 0.7) return 'high';
    if (score > 0.4) return 'medium';
    return 'low';
};

const getTierIcon = (tier) => {
    return { diamond: '💎', enterprise: '🏢', silver: '🥈', bronze: '🥉' }[tier] || '📦';
};

const getStatusLabel = (status) => {
    return { active: 'AKTIV', unhappy: 'UNZUFRIEDEN', churning: 'ABWANDERND', churned: 'VERLOREN' }[status] || status;
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

const getCompanyInitial = (name) => {
    if (!name) return '?';
    const cleanName = name.replace(/^The\s+/i, '');
    return cleanName.charAt(0).toUpperCase();
};

const handleCancelOrder = async (orderId) => {
    cancellingId.value = orderId;
    const result = await gameStore.cancelOrder(orderId);
    cancellingId.value = null;
    if (result) {
        customerOrders.value = customerOrders.value.filter(o => o.id !== orderId);
        gameStore.loadGameState();
    }
};

const handlePrOutreach = async (customer) => {
    isProcessingPr.value = customer.id;
    try {
        const response = await api.post(`/customers/${customer.id}/pr-outreach`);
        if (response.success) {
            gameStore.loadGameState();
        }
    } catch (e) {
        console.error(e);
    } finally {
        isProcessingPr.value = null;
    }
};

const formatDate = (isoStr) => {
    return new Date(isoStr).toLocaleDateString('de-DE');
};
</script>

<style scoped>
.customers-overlay {
    width: 1100px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: 12px;
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: 85vh;
}

.customers-overlay.inline-panel {
    width: 100%;
    max-width: none;
    background: transparent;
    border: none;
}

.customers-overlay.inline-panel .overlay-body {
    padding: 0;
    height: auto;
}

.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 5000;
    display: flex; justify-content: center; align-items: center;
}

.overlay-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, transparent 100%);
}

.header-title { display: flex; align-items: center; gap: 15px; }
.header-title h2 { margin: 0; font-size: 1.4rem; color: #fff; }
.icon { font-size: 1.8rem; }
.customer-count {
    font-size: 0.7rem; background: rgba(88,166,255,0.15); color: #58a6ff; 
    padding: 3px 8px; border-radius: 10px; font-weight: 600; 
}
.close-btn { background: none; border: none; color: #8b949e; font-size: 2rem; cursor: pointer; }
.close-btn:hover { color: #fff; }

.overlay-body {
    padding: 25px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    overflow-y: auto;
}

/* ── Stats Row ────────────────── */
.customer-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.stat-box {
    background: rgba(0, 0, 0, 0.25);
    padding: 16px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.stat-box label {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: 6px;
    letter-spacing: 0.8px;
    font-weight: 700;
}

.stat-box .value {
    font-size: 1.6rem;
    font-weight: 800;
}

/* ── Filter Bar ────────────────── */
.filter-bar {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    background: rgba(0, 0, 0, 0.2);
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 0.6rem;
    color: #8b949e;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
}

.filter-select {
    background: #010409;
    border: 1px solid #30363d;
    color: #e6edf3;
    padding: 6px 10px;
    font-family: monospace;
    border-radius: 4px;
    font-size: 0.8rem;
    min-width: 140px;
}

.filter-count {
    margin-left: auto;
    font-size: 0.75rem;
    color: #58a6ff;
    font-weight: 600;
    align-self: center;
}

/* ── Customer Table ────────────────── */
.customer-list-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.list-header {
    display: grid;
    grid-template-columns: 2fr 1fr 0.8fr 0.8fr 1.2fr 1fr 40px;
    padding: 10px 16px;
    background: rgba(0, 0, 0, 0.3);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-muted);
    letter-spacing: 0.5px;
}

.customer-list {
    overflow-y: auto;
    max-height: 50vh;
}

.customer-row {
    display: grid;
    grid-template-columns: 2fr 1fr 0.8fr 0.8fr 1.2fr 1fr 40px;
    padding: 12px 16px;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    transition: background 0.2s;
    cursor: pointer;
}

.customer-row:hover {
    background: rgba(88, 166, 255, 0.04);
}

/* ── Brand / Name Column ────────── */
.cust-brand-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cust-brand {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.cust-name {
    font-weight: 700;
    color: #e6edf3;
    font-size: 0.85rem;
}

.cust-sub {
    font-size: 0.7rem;
    color: #6e7681;
}

/* ── Region Column ────────── */
.cust-region {
    display: flex;
    align-items: center;
    gap: 6px;
}

.region-flag-mini { font-size: 1.1rem; }

.region-key {
    font-size: 0.6rem;
    font-weight: 700;
    color: #8b949e;
    font-family: monospace;
}

/* ── Tier Column ────────── */
.tier-badge {
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 800;
    text-transform: uppercase;
    border: 1px solid transparent;
}

.tier-badge.diamond { background: rgba(168, 85, 247, 0.15); color: #c084fc; border-color: rgba(168, 85, 247, 0.25); }
.tier-badge.enterprise { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.25); }
.tier-badge.silver { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border-color: rgba(148, 163, 184, 0.25); }
.tier-badge.bronze { background: rgba(180, 120, 60, 0.15); color: #d4a574; border-color: rgba(180, 120, 60, 0.25); }

/* ── Status ────────── */
.status-badge {
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 800;
    text-transform: uppercase;
}

.status-badge.active { background: rgba(63, 185, 80, 0.1); color: #3fb950; border: 1px solid rgba(63, 185, 80, 0.2); }
.status-badge.unhappy { background: rgba(210, 153, 34, 0.1); color: #d29922; border: 1px solid rgba(210, 153, 34, 0.2); }
.status-badge.churning { background: rgba(248, 81, 73, 0.1); color: #f85149; border: 1px solid rgba(248, 81, 73, 0.2); }

/* ── Satisfaction & Loyalty Bars ────────── */
.cust-sat, .cust-loyalty {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    font-family: monospace;
    color: #c9d1d9;
}

.sat-bar-container, .loyalty-bar-container {
    flex: 1;
    height: 4px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
    overflow: hidden;
}

.sat-bar, .loyalty-bar { height: 100%; border-radius: 2px; transition: width 0.3s; }

.sat-bar.good { background: #3fb950; }
.sat-bar.ok { background: #d29922; }
.sat-bar.poor { background: #f85149; }

.loyalty-bar.high { background: #58a6ff; }
.loyalty-bar.medium { background: #d2a8ff; }
.loyalty-bar.low { background: #6e7681; }

/* ── Expand Arrow ────────── */
.cust-expand {
    text-align: center;
    color: #484f58;
    transition: transform 0.2s;
}
.cust-expand .rotated { display: inline-block; transform: rotate(90deg); }

/* ── Expanded Details ────────── */
.customer-details-expanded {
    grid-column: 1 / -1;
    margin: 0 16px 8px;
    background: rgba(13, 17, 23, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    overflow: hidden;
}

.details-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    min-height: 250px;
}

.details-sidebar {
    padding: 20px;
    background: rgba(0, 0, 0, 0.25);
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.details-main { padding: 20px; }

.detail-section h4 {
    font-size: 0.6rem;
    color: #6e7681;
    letter-spacing: 0.15em;
    margin-bottom: 10px;
    font-weight: 800;
}

/* Region Detail Card */
.region-detail-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.region-flag-large { font-size: 2rem; }
.region-detail-name { font-weight: 700; color: #e6edf3; font-size: 0.9rem; }
.region-detail-key { font-size: 0.6rem; color: #58a6ff; font-family: monospace; letter-spacing: 0.05em; }

/* Flag Tags */
.flag-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.flag-tag {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}

.flag-tag.compliance { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
.flag-tag.performance { background: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.2); }
.flag-tag.eco { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
.flag-tag.neutral { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.15); }

/* ── Orders ────────── */
.order-mini-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 4px;
    margin-bottom: 4px;
    font-size: 0.8rem;
}

.o-type { font-weight: 700; flex: 1; color: #c9d1d9; }
.o-price { font-family: monospace; color: #3fb950; opacity: 0.8; }
.o-cancel {
    background: transparent; border: none; color: #f85149; 
    opacity: 0.3; cursor: pointer; padding: 0 4px;
}
.o-cancel:hover { opacity: 1; }

.btn-pr-stunt {
    width: 100%;
    padding: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    border-radius: 4px;
    margin-bottom: 6px;
    transition: all 0.2s;
}

.btn-pr-stunt:hover:not(:disabled) { background: rgba(88, 166, 255, 0.15); border-color: #58a6ff; color: #58a6ff; }
.btn-pr-stunt:disabled { opacity: 0.4; cursor: not-allowed; }

.hint { font-size: 0.6rem; color: #6e7681; }
.empty-hint { font-size: 0.8rem; color: #6e7681; font-style: italic; }

/* ── Reviews ────────── */
.reviews-list { display: flex; flex-direction: column; gap: 10px; }

.review-item {
    padding: 14px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border-left: 3px solid #484f58;
}

.review-item.positive { border-left-color: #3fb950; }
.review-item.negative { border-left-color: #f85149; }
.review-item.neutral { border-left-color: #d29922; }

.review-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
.star { color: rgba(255,255,255,0.1); font-size: 0.85rem; }
.star.filled { color: #facc15; }
.date { font-size: 0.65rem; color: #6e7681; }
.review-content { font-size: 0.85rem; line-height: 1.4; font-style: italic; color: #e6edf3; }

/* ── General ────────── */
.empty-state { padding: 40px; text-align: center; color: #6e7681; }
.btn-primary {
    margin-top: 12px; background: #58a6ff; color: #000; border: none;
    padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer;
}
.btn-primary:hover { background: #79b8ff; }

.text-success { color: #3fb950; }
.text-warning { color: #d29922; }
.text-danger { color: #f85149; }

/* ── Responsive ────────── */
@media (max-width: 768px) {
    .customers-overlay { width: 100%; max-height: 90vh; }
    .customer-stats { grid-template-columns: repeat(2, 1fr); }
    .list-header, .customer-row { grid-template-columns: 2fr 1fr 1fr 40px; }
    .cust-region, .cust-tier, .cust-loyalty { display: none; }
    .filter-bar { flex-wrap: wrap; }
    .details-grid { grid-template-columns: 1fr; }
}
</style>
