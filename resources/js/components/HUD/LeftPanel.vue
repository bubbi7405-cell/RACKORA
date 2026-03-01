<template>
    <aside class="left-panel">
        <div class="panel-header">
            <div class="sys-label">METRIC_EXTRACTION_SYSTEM</div>
            <h3 class="panel-header__title">NODE_TELEMETRY</h3>
        </div>

        <div class="panel-content">
            <!-- Current Room Status -->
            <div class="panel-section current-node">
                <div class="section-label-industrial">ACTIVE_UNIT</div>
                <div class="node-summary glass-panel animate-glitch" v-if="selectedRoom"
                    v-tooltip="{ title: 'Aktiver Knotenpunkt', content: 'Dies ist dein aktuell ausgewähltes Rechenzentrum.', hint: 'Klicke in der Locations-Ansicht auf ein anderes DC zum Wechseln.' }">
                    <div class="scan-line"></div>
                    <div class="node-meta-top">
                        <span class="node-type-label">{{ selectedRoom.type?.toUpperCase() || 'NODE' }}</span>
                        <div class="node-indicators">
                            <div v-if="selectedRoom.warnings?.overheating" class="ind danger"
                                v-tooltip="'KRITISCHE_HITZE: Sofortige Reparatur oder Kühlung erforderlich!'">!</div>
                            <div v-if="selectedRoom.warnings?.powerOverload" class="ind danger"
                                v-tooltip="'STROM_LIMIT: Das DC verbraucht mehr Energie als verfügbar!'">P</div>
                        </div>
                    </div>
                    <div class="node-name-main">{{ selectedRoom.name }}</div>
                </div>
            </div>

            <!-- Global Facility Overview -->
            <div class="panel-section">
                <DatacenterMinimap />
            </div>

            <!-- Quick Stats -->
            <div class="panel-section">
                <div class="section-label-industrial">REGION_METRICS</div>
                <div class="quick-stats">
                    <div class="quick-stat"
                        v-tooltip="{ title: 'HE-Kapazität', content: 'Die Summe aller verfügbaren Höheneinheiten (U) in diesem Gebäude.', hint: 'Jeder Server belegt 1-4U.' }">
                        <span class="quick-stat__label">U_CAPACITY</span>
                        <span class="quick-stat__value">{{ stats.totalRacks * 42 }}U</span>
                    </div>
                    <div class="quick-stat"
                        v-tooltip="{ title: 'Aktive Nodes', content: 'Anzahl der physisch installierten Server-Blades in diesem DC.' }">
                        <span class="quick-stat__label">NODES</span>
                        <span class="quick-stat__value">{{ stats.totalServers }}</span>
                    </div>
                    <div class="quick-stat wide"
                        v-tooltip="{ title: 'Verfügbarkeits-SLO', content: 'Die durchschnittliche Betriebszeit deiner Hardware in diesem Monat.', hint: 'Fällt der Wert unter 99.9%, drohen Vertragsstrafen!' }">
                        <span class="quick-stat__label">UPTIME_SLO</span>
                        <div class="mini-bar-bg">
                            <div class="mini-bar data-fill" :style="{ width: stats.uptime + '%' }"
                                :class="{ 'warning': stats.uptime < 99 }"></div>
                        </div>
                        <span class="quick-stat__value" :class="{ 'warning': stats.uptime < 99 }">{{
                            stats.uptime.toFixed(2) }}%</span>
                    </div>
                </div>
            </div>

            <!-- Management Actions -->
            <div class="panel-section">
                <div class="section-label-industrial">OVERSIGHT_PROTOCOLS</div>
                <div class="action-grid">
                    <button class="action-btn glass-panel" @click="$emit('openResearch')"
                        v-tooltip="'Öffnet das Forschungszentrum für neue Technologien.'">
                        <span class="action-icon">⌬</span>
                        <span>RD_LABS</span>
                        <div v-if="gameStore.research.active" class="active-pulse"></div>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openSpecialization')"
                        v-tooltip="'Wähle dein Unternehmens-Spezialisierung (z.B. Security oder Hosting).'">
                        <span class="action-icon">◧</span>
                        <span>STRATEGY</span>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openAnalytics')"
                        v-tooltip="'Detaillierte Graphen zu Umsatz, Traffic und Hardware-Zustand.'">
                        <span class="action-icon">📊</span>
                        <span>INTEL</span>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openMarket')"
                        v-tooltip="'Der Broker-Desk für Energie-Arbitrage und Hardware-Börse.'">
                        <span class="action-icon">📈</span>
                        <span>EXCHANGE</span>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openMarketing')"
                        v-tooltip="'Starte Kampagnen um neue Kunden anzulocken.'">
                        <span class="action-icon">📣</span>
                        <span>MARKETING</span>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openCustomers')"
                        v-tooltip="'Verwalte deine Kundenbeziehungen und prüfe die Zufriedenheit.'">
                        <span class="action-icon">CRM</span>
                        <span>CUSTOMERS</span>
                    </button>
                    <button class="action-btn glass-panel" @click="$emit('openSandbox')"
                        v-tooltip="'Experimentier-Zentrum für Server-Konfigurationen.'">
                        <span class="action-icon">🧪</span>
                        <span>HW_LAB</span>
                    </button>
                </div>
            </div>

            <!-- Pending Orders -->
            <div class="panel-section">
                <div class="section-label-industrial">
                    QUEUE_INGRESS
                    <span v-if="orders?.urgentCount > 0" class="urgent-id">[{{ orders.urgentCount }}]</span>
                </div>

                <div class="order-list">
                    <div v-for="order in orders?.pending?.slice(0, 5) || []" :key="order?.id || Math.random()"
                        v-if="order" @click="gameStore.selectOrder(order)" class="order-item" :class="{
                            'urgent': (order.patience?.progress || 0) > 70,
                            'enterprise': order.sla && order.sla.tier === 'enterprise',
                            'whale': order.sla && order.sla.tier === 'whale'
                        }">
                        <div class="order-id-mark"></div>
                        <div class="order-body">
                            <div class="order-customer">{{ order.customerName?.toUpperCase() || 'UNKNOWN' }}</div>
                            <div class="order-type">{{ order.productType }}</div>
                        </div>
                        <div class="order-meta">
                            <span class="time">{{ formatTime(order.patience?.remainingSeconds || 0) }}</span>
                            <div class="patience-track">
                                <div class="p-fill data-fill" :style="{ width: (order.patience?.progress || 0) + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!orders?.pending?.length" class="empty-orders">
                        <div class="empty-icon-static">∅</div>
                        <span class="text">LIST_EMPTY</span>
                    </div>
                </div>
            </div>

            <!-- NEW: Energy & Battery Status -->
            <div class="panel-section" v-if="energyMarket.storage?.battery_count > 0">
                <div class="section-label-industrial">
                    ENERGY_RESERVES
                    <span class="vpp-indicator" v-if="energyMarket.storage?.is_vpp_active">VPP_ACTIVE</span>
                </div>
                <div class="energy-mini-card glass-panel" @click="$emit('openMarket')">
                    <div class="energy-header">
                        <span class="capacity-text">{{ (energyMarket.storage?.current_level || 0).toFixed(1) }} / {{
                            (energyMarket.storage?.total_capacity || 0).toFixed(0) }} kWh</span>
                        <span class="health-text" :class="healthClass">{{
                            (energyMarket.storage?.average_health || 100).toFixed(0) }}% SOH</span>
                    </div>
                    <div class="energy-progress-track">
                        <div class="energy-progress-fill" :style="{ width: storagePercent + '%' }"
                            :class="storageClass"></div>
                    </div>
                    <div class="energy-footer">
                        <span class="grid-status">
                            <span class="pulse-dot" v-if="isDischarging || energyMarket.storage?.is_vpp_active"></span>
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
import DatacenterMinimap from './DatacenterMinimap.vue';
import { storeToRefs } from 'pinia';

const emit = defineEmits(['openUpgrades', 'openResearch', 'openSpecialization', 'openSandbox', 'openMarket', 'openMarketing', 'openAnalytics', 'openCustomers']);

const gameStore = useGameStore();

const selectedRoom = computed(() => gameStore.selectedRoom);
const stats = computed(() => gameStore.stats || {});
const orders = computed(() => gameStore.orders || { pending: [] });
const player = computed(() => gameStore.player || {});

const energyMarket = computed(() => gameStore.energyMarket || {});
const storagePercent = computed(() => {
    if (!energyMarket.value.storage?.total_capacity) return 0;
    return (energyMarket.value.storage.current_level / energyMarket.value.storage.total_capacity) * 100;
});

const healthClass = computed(() => {
    const h = energyMarket.value.storage?.average_health || 100;
    if (h > 80) return 'text-success';
    if (h > 50) return 'text-warning';
    return 'text-danger';
});

const storageClass = computed(() => {
    if (storagePercent.value > 80) return 'is-high';
    if (storagePercent.value < 20) return 'is-low';
    return 'is-nominal';
});

const isDischarging = computed(() => {
    return energyMarket.value.spotPrice > 0.18; // Logic from EnergyService
});

const gridStatusText = computed(() => {
    if (energyMarket.value.storage?.is_vpp_active) return 'VPP_PAYOUT';
    if (isDischarging.value) return 'DISCHARGING';
    if (energyMarket.value.spotPrice < 0.12) return 'CHARGING';
    return 'STABILIZED';
});

function formatMoney(value) {
    if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
    if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
    return value.toFixed(0);
}

function formatTime(seconds) {
    if (seconds <= 0) return 'EXPIRED';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<style scoped>
.left-panel {
    width: var(--sidebar-width);
    background: var(--v3-bg-base);
    border-right: var(--v3-border-heavy);
    display: flex;
    flex-direction: column;
    height: 100%;
    z-index: 10;
}

.panel-header {
    padding: 16px 20px;
    border-bottom: var(--v3-border-soft);
}

.sys-label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.25em;
    margin-bottom: 2px;
}

.panel-header__title {
    font-size: 0.75rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.1em;
}

.panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.section-label-industrial {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-secondary);
    letter-spacing: 0.2em;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-label-industrial::before {
    content: '';
    width: 2px;
    height: 8px;
    background: var(--v3-accent);
}

/* Node Telemetry Card */
.node-summary {
    padding: 16px;
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    position: relative;
    border-radius: var(--v3-radius);
    transition: all var(--v3-transition-base);
}

.node-meta-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.node-type-label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-accent);
    letter-spacing: 0.15em;
}

.node-indicators {
    display: flex;
    gap: 6px;
}

.ind {
    width: 14px;
    height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-family-mono);
    font-size: 0.6rem;
    font-weight: 900;
    border-radius: 2px;
    opacity: 0.8;
}

.ind.danger {
    background: var(--v3-danger);
    color: #fff;
    animation: v3-pulse-state 1.2s infinite ease-in-out;
}

.node-name-main {
    font-size: 0.9rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 0.05em;
}

/* Quick Stats Grid */
.quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.quick-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px;
    background: rgba(0, 0, 0, 0.2);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
}

.quick-stat.wide {
    grid-column: span 2;
}

.quick-stat__label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
}

.quick-stat__value {
    font-size: 0.8rem;
    font-family: var(--font-family-mono);
    font-weight: 700;
    color: var(--v3-text-primary);
}

.mini-bar-bg {
    height: 2px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 1px;
    margin: 4px 0;
    overflow: hidden;
}

.mini-bar {
    height: 100%;
    background: var(--v3-success);
    transition: width var(--v3-transition-slow);
}

.mini-bar.warning {
    background: var(--v3-warning);
}

/* Action Grid */
.action-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 4px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 16px;
    background: transparent;
    border: none;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--v3-text-secondary);
    transition: all var(--v3-transition-fast);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    border-radius: var(--v3-radius);
}

.action-btn:hover {
    color: #fff;
    background: var(--v3-bg-accent);
}

.action-icon {
    font-size: 1rem;
    color: var(--v3-accent);
    opacity: 0.8;
}

/* Ingress Queue */
.order-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.order-item {
    padding: 12px;
    background: rgba(0, 0, 0, 0.2);
    border: var(--v3-border-soft);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
    border-left: 2px solid transparent;
    transition: all var(--v3-transition-fast);
    border-radius: var(--v3-radius);
}

.order-item:hover {
    background: var(--v3-bg-surface);
    border-left-color: var(--v3-accent);
}

.order-item.urgent {
    border-left-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.03);
}

.order-item.enterprise {
    border-left-color: #3b82f6;
    background: rgba(59, 130, 246, 0.05);
}

.order-item.whale {
    border-left-color: #fbbf24;
    background: rgba(251, 191, 36, 0.1);
}

.order-customer {
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 0.05em;
}

.order-type {
    font-size: 0.55rem;
    color: var(--v3-text-ghost);
}

.order-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.time {
    font-size: 0.6rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-secondary);
    font-weight: 700;
    width: 42px;
}

.patience-track {
    flex: 1;
    height: 2px;
    background: rgba(255, 255, 255, 0.03);
    position: relative;
    overflow: hidden;
}

.p-fill {
    height: 100%;
    background: var(--v3-accent);
    transition: width 1s linear;
}

.urgent .p-fill {
    background: var(--v3-danger);
}

.urgent-id {
    color: var(--v3-danger);
    font-family: var(--font-family-mono);
    font-weight: 900;
}

/* Energy Mini Card */
.vpp-indicator {
    margin-left: auto;
    background: rgba(88, 166, 255, 0.15);
    color: #58a6ff;
    padding: 1px 6px;
    border-radius: 2px;
    font-size: 0.45rem;
    animation: anchor-pulse 1.5s infinite;
}

.energy-mini-card {
    padding: 12px;
    background: rgba(0, 0, 0, 0.2);
    border: var(--v3-border-soft);
    cursor: pointer;
    transition: all 0.2s;
}

.energy-mini-card:hover {
    background: var(--v3-bg-surface);
    border-color: var(--v3-accent);
}

.energy-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
}

.health-text {
    font-size: 0.55rem;
}

.energy-progress-track {
    height: 4px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 8px;
}

.energy-progress-fill {
    height: 100%;
    background: #58a6ff;
    transition: width 0.5s ease-out;
}

.energy-progress-fill.is-high {
    background: #3fb950;
}

.energy-progress-fill.is-low {
    background: #f85149;
}

.energy-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.grid-status {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
    display: flex;
    align-items: center;
    gap: 6px;
}

.unit-count {
    font-size: 0.5rem;
    color: var(--v3-text-ghost);
}

.pulse-dot {
    width: 4px;
    height: 4px;
    background: #58a6ff;
    border-radius: 50%;
    animation: anchor-pulse 1s infinite;
}

@keyframes anchor-pulse {
    0% {
        opacity: 0.3;
        transform: scale(0.8);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }

    100% {
        opacity: 0.3;
        transform: scale(0.8);
    }
}

.text-success {
    color: #3fb950;
}

.text-warning {
    color: #e3b341;
}

.text-danger {
    color: #f85149;
}
</style>
