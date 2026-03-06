<template>
    <aside class="left-panel v2-left-panel">
        <div class="panel-header l1-priority">
            <div class="sys-label l3-priority">ASSET_IDENTIFICATION // [VERIFIED]</div>
            <h3 class="panel-header__title">SITE_PORTFOLIO</h3>
        </div>

        <div class="panel-content scroll-v2">
            <!-- Current Room Status -->
            <div class="panel-section current-node">
                <div class="section-label-industrial l2-priority">ACTIVE_SITE</div>
                <div class="node-summary glass-v2" v-if="selectedRoom"
                    @mouseenter="tooltipStore.show($event, { title: 'SITE_DESIGNATION', content: 'The currently active infrastructure site. All executive actions apply to this location.', hint: 'Switch focus in GLOBAL_NETWORK.' })"
                    @mouseleave="tooltipStore.hide()">
                    <div class="node-meta-top">
                        <span class="node-type-label l2-priority">UNIT_TYPE: {{ selectedRoom.type?.toUpperCase() || 'NODE' }}</span>
                        <div class="node-indicators">
                            <span class="status-badge l2-priority" :class="{ 'is-active': selectedRoom.status === 'online' }">
                                {{ selectedRoom.status === 'online' ? '[ACTIVE]' : '[OFFLINE]' }}
                            </span>
                        </div>
                    </div>
                    <div class="node-name-main l1-priority">{{ selectedRoom.name.toUpperCase() }}</div>
                    <div class="node-sub-meta l3-priority">SITE_CONNECTED // [LINK_STABLE]</div>
                </div>
            </div>

            <!-- Global Facility Overview -->
            <div class="panel-section">
                <div class="section-label-industrial l2-priority">NETWORK_TOPOLOGY</div>
                <DatacenterMinimap class="minimap-v2" />
            </div>

            <!-- Quick Stats -->
            <div class="panel-section">
                <div class="section-label-industrial l3-priority">SITE_METRICS</div>
                <div class="quick-stats">
                    <div class="quick-stat glass-v2"
                        @mouseenter="tooltipStore.show($event, 'rack')"
                        @mouseleave="tooltipStore.hide()">
                        <span class="quick-stat__label l3-priority">CAPACITY</span>
                        <span class="quick-stat__value l3-priority" :class="{ 'l1-priority text-critical': stats.utilization > 0.9 }">
                            {{ (stats.totalRacks || 0) * 42 }}U
                        </span>
                    </div>
                    <div class="quick-stat glass-v2"
                        @mouseenter="tooltipStore.show($event, 'server')"
                        @mouseleave="tooltipStore.hide()">
                        <span class="quick-stat__label l3-priority">ASSETS</span>
                        <span class="quick-stat__value l3-priority">{{ stats.totalServers || 0 }}</span>
                    </div>
                    <div class="quick-stat glass-v2 wide"
                        @mouseenter="tooltipStore.show($event, { title: 'STABILITY', content: 'Percentage of continuous operational uptime.', hint: 'Maintain power yields to preserve stability.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="quick-stat__label l3-priority">STABILITY</span>
                        <div class="mini-bar-bg-v2">
                            <div class="mini-bar data-fill-v2" :style="{ width: (stats.uptime || 0) + '%' }"
                                :class="{ 'warning': stats.uptime < 99 }"></div>
                        </div>
                        <span class="quick-stat__value l3-priority" :class="{ 'l1-priority text-critical': stats.uptime < 99 }">
                            {{ (stats.uptime || 0) >= 99.99 ? 'NOMINAL_100%' : (stats.uptime || 0).toFixed(2) + '%' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Room Actions (contextual) -->
            <div class="panel-section" v-if="selectedRoom">
                <div class="section-label-industrial l2-priority">SITE_ACTIONS</div>
                <div class="action-grid-v2">
                    <button class="action-btn-v2" @click="$emit('openSandbox')"
                        @mouseenter="tooltipStore.show($event, { title: 'DEPLOYMENT', content: 'Configure and provision new hardware assets.', hint: 'Requires available rack space.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="action-icon">⧊</span>
                        <span>DEPLOYMENT</span>
                    </button>
                    <button class="action-btn-v2" @click="$emit('openLab')"
                        @mouseenter="tooltipStore.show($event, { title: 'STRESS_TEST', content: 'Execute performance diagnostics to verify asset stability.', hint: 'High power draw during testing.' })"
                        @mouseleave="tooltipStore.hide()">
                        <span class="action-icon">⧓</span>
                        <span>STRESS_TEST</span>
                    </button>
                </div>
            </div>

            <!-- Pending Orders -->
            <div class="panel-section">
                <div class="section-label-industrial l2-priority">
                    PENDING_CONTRACTS // [INBOUND]
                    <span v-if="orders?.urgentCount > 0" class="urgent-id">[{{ orders.urgentCount }}] // URGENT_SIG</span>
                </div>

                <div class="order-list-v2">
                    <div v-for="order in orders?.pending?.slice(0, 5) || []" :key="order?.id || Math.random()"
                        v-if="order" @click="gameStore.selectOrder(order)" class="order-item-v2" :class="{
                            'is-urgent': (order.patience?.progress || 0) > 70,
                            'is-enterprise': order.sla && order.sla.tier === 'enterprise',
                            'is-whale': order.sla && order.sla.tier === 'whale'
                        }">
                        <div class="order-id-bracket"></div>
                        <div class="order-body">
                            <div class="order-customer l1-priority">{{ order.customerName?.toUpperCase() || 'UNKNOWN' }}</div>
                            <div class="order-type l3-priority">{{ order.productType }}</div>
                        </div>
                        <div class="order-meta">
                            <span class="time l2-priority">{{ formatTime(order.patience?.remainingSeconds || 0) }}</span>
                            <div class="patience-track-v2">
                                <div class="p-fill-v2" :style="{ width: (order.patience?.progress || 0) + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!orders?.pending?.length" class="empty-orders-v2 glass-v2" @click="$emit('openMarketing')">
                        <div class="empty-icon-static animate-flicker">⧓</div>
                        <span class="text l2-priority">NETWORK_SCAN_ACTIVE...</span>
                        <span class="sub-text l3-priority">NO_PENDING_CONTRACTS_FOUND</span>
                        <button class="cta-link-v2 l1-priority">EXPAND_MARKET_REACH</button>
                    </div>
                </div>
            </div>

            <!-- Energy & Battery Status -->
            <div class="panel-section" v-if="energyMarket.storage?.battery_count > 0">
                <div class="section-label-industrial l3-priority">
                    ENERGY_RESERVES
                    <span class="vpp-indicator" v-if="energyMarket.storage?.is_vpp_active">VPP_ACTIVE</span>
                </div>
                <div class="energy-mini-card-v2 glass-v2" @click="$emit('openMarket')">
                    <div class="energy-header l2-priority">
                        <span class="capacity-text">{{ (energyMarket.storage?.current_level || 0).toFixed(1) }} / {{
                            (energyMarket.storage?.total_capacity || 0).toFixed(0) }} kWh</span>
                        <span class="health-text" :class="healthClass">{{
                            (energyMarket.storage?.average_health || 100).toFixed(0) }}% SOH</span>
                    </div>
                    <div class="energy-progress-track-v2">
                        <div class="energy-progress-fill-v2" :style="{ width: storagePercent + '%' }"
                            :class="storageClass"></div>
                    </div>
                    <div class="energy-footer l3-priority">
                        <span class="grid-status-v2">
                            <span class="pulse-dot-v2" v-if="isDischarging || energyMarket.storage?.is_vpp_active"></span>
                            {{ gridStatusText }}
                        </span>
                        <span class="unit-count">{{ energyMarket.storage?.battery_count || 0 }} UNITS</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useTooltipStore } from '../../stores/tooltip';
import DatacenterMinimap from './DatacenterMinimap.vue';

const emit = defineEmits(['openSandbox', 'openLab', 'openMarketing', 'openMarket']);

const gameStore = useGameStore();
const tooltipStore = useTooltipStore();

const selectedRoom = computed(() => gameStore.selectedRoom);
const stats = computed(() => gameStore.stats || {});
const orders = computed(() => gameStore.orders || { pending: [] });
const energyMarket = computed(() => gameStore.energyMarket || {});

const storagePercent = computed(() => {
    if (!energyMarket.value.storage?.total_capacity) return 0;
    return (energyMarket.value.storage.current_level / energyMarket.value.storage.total_capacity) * 100;
});

const healthClass = computed(() => {
    const h = energyMarket.value.storage?.average_health || 100;
    if (h > 80) return 'text-nominal';
    if (h > 50) return 'text-warning';
    return 'text-critical';
});

const storageClass = computed(() => {
    if (storagePercent.value > 80) return 'is-high';
    if (storagePercent.value < 20) return 'is-low';
    return 'is-nominal';
});

const isDischarging = computed(() => {
    return energyMarket.value.spotPrice > 0.18;
});

const gridStatusText = computed(() => {
    if (energyMarket.value.storage?.is_vpp_active) return 'VPP_PAYOUT';
    if (isDischarging.value) return 'DISCHARGING';
    if (energyMarket.value.spotPrice < 0.12) return 'CHARGING';
    return 'STABILIZED';
});

function formatTime(seconds) {
    if (seconds <= 0) return 'EXPIRED';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<style scoped>
.v2-left-panel {
    width: 320px;
    background: var(--ds-bg-elevated);
    border-right: 1px solid var(--ds-border-color);
    display: flex;
    flex-direction: column;
    height: 100%;
    z-index: 100;
    position: relative;
}

.panel-header {
    padding: 20px;
    border-bottom: 1px solid var(--ds-border-color);
}

.sys-label {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--ds-text-ghost);
    letter-spacing: 0.04em;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.panel-header__title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ds-text-primary);
}

.panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.section-label-industrial {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--ds-text-muted);
    letter-spacing: 0.04em;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
}

.section-label-industrial::before {
    content: '';
    width: 3px;
    height: 12px;
    background: var(--ds-accent);
    border-radius: 2px;
}

/* ── CARDS ────────────────────────────────── */
.glass-v2 {
    background: var(--ds-bg-elevated);
    border: 1px solid var(--ds-border-color);
    border-radius: var(--ds-radius-lg);
    transition: all 0.2s ease;
    box-shadow: var(--ds-shadow-card);
}

.node-summary {
    padding: 16px;
    border-radius: var(--ds-radius-lg);
    border: 1px solid var(--ds-border-color);
    background: var(--ds-bg-elevated);
    box-shadow: var(--ds-shadow-card);
}

.node-summary:hover {
    border-color: var(--ds-accent);
    transform: translateY(-1px);
    box-shadow: var(--ds-shadow-md);
}

.node-meta-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.node-type-label { font-size: 0.6875rem; font-weight: 600; color: var(--ds-accent); }
.status-badge { font-size: 0.6875rem; font-family: var(--ds-font-mono); color: var(--ds-text-ghost); }
.status-badge.is-active { color: var(--ds-nominal); }

.node-name-main { font-size: 1.125rem; font-weight: 700; color: var(--ds-text-primary); line-height: 1.2; }
.node-sub-meta { font-size: 0.6875rem; font-weight: 500; color: var(--ds-text-ghost); margin-top: 6px; }

/* ── QUICK STATS ────────────────────────────── */
.quick-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.quick-stat {
    display: flex; flex-direction: column; gap: 4px; padding: 14px;
    background: var(--ds-bg-subtle); border-radius: var(--ds-radius-md);
    border: 1px solid var(--ds-border-color);
}
.quick-stat.wide { grid-column: span 2; }
.quick-stat__label { font-size: 0.6875rem; font-weight: 600; color: var(--ds-text-ghost); text-transform: uppercase; }
.quick-stat__value { font-size: 1.125rem; font-weight: 700; font-family: var(--ds-font-mono); color: var(--ds-text-primary); }

.mini-bar-bg-v2 { height: 4px; background: var(--ds-bg-hover); margin: 6px 0; overflow: hidden; border-radius: var(--ds-radius-full); }
.mini-bar { height: 100%; background: var(--ds-nominal); transition: width 1s ease-out; border-radius: var(--ds-radius-full); }
.mini-bar.warning { background: var(--ds-warning); }

.text-nominal { color: var(--ds-nominal); }
.text-warning { color: var(--ds-warning); }
.text-critical { color: var(--ds-critical); }

/* ── ACTIONS ───────────────────────────────── */
.action-grid-v2 { display: grid; gap: 8px; }
.action-btn-v2 {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    background: var(--ds-bg-elevated); border: 1px solid var(--ds-border-color);
    color: var(--ds-text-secondary); font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    border-radius: var(--ds-radius-md); transition: all 0.15s;
}
.action-btn-v2:hover { background: var(--ds-accent); color: #fff; border-color: var(--ds-accent); }
.action-icon { font-size: 1rem; }

/* ── ORDERS ────────────────────────────────── */
.order-list-v2 { display: flex; flex-direction: column; gap: 8px; }
.order-item-v2 {
    padding: 14px; background: var(--ds-bg-elevated); border: 1px solid var(--ds-border-color);
    cursor: pointer; position: relative; transition: all 0.15s;
    border-radius: var(--ds-radius-md); box-shadow: var(--ds-shadow-sm);
}
.order-item-v2:hover { border-color: #CBD5E1; box-shadow: var(--ds-shadow-md); }
.order-id-bracket { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--ds-border-color); border-radius: 3px 0 0 3px; }

.is-urgent .order-id-bracket { background: var(--ds-critical); }
.is-enterprise .order-id-bracket { background: var(--ds-accent); }
.is-whale .order-id-bracket { background: #F59E0B; }

.order-customer { font-size: 0.8125rem; font-weight: 600; color: var(--ds-text-primary); margin-bottom: 2px; }
.order-type { font-size: 0.75rem; font-weight: 500; color: var(--ds-text-ghost); }
.order-meta { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.time { font-size: 0.75rem; font-family: var(--ds-font-mono); color: var(--ds-text-muted); width: 40px; }
.patience-track-v2 { flex: 1; height: 3px; background: var(--ds-bg-hover); border-radius: var(--ds-radius-full); }
.p-fill-v2 { height: 100%; background: var(--ds-nominal); transition: width 1s linear; border-radius: var(--ds-radius-full); }
.is-urgent .p-fill-v2 { background: var(--ds-critical); }

/* ── ENERGY ─────────────────────────────────── */
.energy-mini-card-v2 { padding: 14px; cursor: pointer; border: 1px solid var(--ds-border-color); border-radius: var(--ds-radius-md); background: var(--ds-bg-elevated); }
.energy-mini-card-v2:hover { border-color: var(--ds-accent); }
.energy-header { display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 600; color: var(--ds-text-primary); margin-bottom: 10px; }
.energy-progress-track-v2 { height: 4px; background: var(--ds-bg-hover); border-radius: var(--ds-radius-full); overflow: hidden; margin-bottom: 10px; }
.energy-progress-fill-v2 { height: 100%; background: var(--ds-accent); border-radius: var(--ds-radius-full); }
.energy-progress-fill-v2.is-high { background: var(--ds-nominal); }
.energy-progress-fill-v2.is-low { background: var(--ds-critical); }

.grid-status-v2 { font-size: 0.75rem; font-weight: 500; color: var(--ds-text-ghost); display: flex; align-items: center; gap: 6px; }
.pulse-dot-v2 { width: 6px; height: 6px; background: var(--ds-nominal); border-radius: 50%; }

.empty-orders-v2 {
    display: flex; flex-direction: column; align-items: center; padding: 32px 16px; text-align: center; gap: 8px;
    border: 2px dashed var(--ds-border-color); cursor: pointer; border-radius: var(--ds-radius-lg); background: var(--ds-bg-subtle);
}
.empty-icon-static { font-size: 1.5rem; color: var(--ds-accent); margin-bottom: 8px; }
.empty-orders-v2 .text { font-size: 0.875rem; font-weight: 600; color: var(--ds-text-primary); }
.empty-orders-v2 .sub-text { font-size: 0.75rem; color: var(--ds-text-ghost); }
.cta-link-v2 {
    background: var(--ds-accent); color: #fff; border: none; font-size: 0.8125rem; font-weight: 600;
    padding: 8px 16px; margin-top: 12px; cursor: pointer; border-radius: var(--ds-radius-md);
}

@keyframes ds-pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
</style>
