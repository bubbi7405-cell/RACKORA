<template>
    <aside class="v2-sidebar" :class="{ 'is-collapsed': isCollapsed }">
        <div class="v2-sidebar-header">
            <div class="v2-logo" v-if="!isCollapsed">
                <span class="v2-logo-text">RACKORA</span>
                <span class="v2-badge">v2.5_EXT</span>
            </div>
            <div class="v2-logo-collapsed" v-else>R</div>
        </div>

        <nav class="v2-nav-list">
            <div class="v2-nav-section" v-if="!isCollapsed">COMMAND_CENTER</div>
            
            <div
                v-for="item in navItemsWithTooltips"
                :key="item.id"
                class="v2-nav-item"
                :id="'nav-' + item.id"
                :class="{ 'is-active': activeView === item.id }"
                @click="selectView(item.id)"
                v-tooltip="{ title: item.label, content: item.tooltipContent, hint: item.tooltipHint }"
            >
                <div class="v2-nav-icon" v-html="item.icon"></div>
                <div class="v2-nav-label">{{ item.label }}</div>
                
                <div class="item-badge" v-if="item.id === 'management' && pendingOrdersCount > 0">
                    {{ pendingOrdersCount }}
                </div>
            </div>

            <div class="v2-nav-spacer"></div>

            <IncidentDrawer :collapsed="isCollapsed" />

            <div class="v2-nav-section" v-if="!isCollapsed">SYSTEM_STABILITY</div>
            <div class="v2-system-status" v-if="!isCollapsed">
                <div class="v2-status-row">
                    <span class="v2-status-dot" :class="{ 'is-online': wsConnected && !isPolling, 'is-warning': isPolling && !wsConnected }"></span>
                    <span class="v2-status-label">UPLINK:</span>
                    <span class="v2-status-value" :class="{ 'is-online': wsConnected && !isPolling, 'is-warning': isPolling && !wsConnected }">
                        {{ wsConnected ? (isPolling ? 'LATENCY_MODE' : 'ESTABLISHED') : (isPolling ? 'LATENCY_MODE' : 'LINK_LOSS') }}
                    </span>
                </div>
            </div>
        </nav>

        <div class="v2-sidebar-footer">
            <button class="v2-collapse-btn" @click="$emit('update:isCollapsed', !isCollapsed)" title="Toggle Sidebar">
                <!-- Icon for EXPAND (when collapsed) -->
                <svg v-if="isCollapsed" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="v2-nav-icon force-visible">
                    <polyline points="13 17 18 12 13 7"></polyline>
                    <polyline points="6 17 11 12 6 7"></polyline>
                </svg>
                
                <!-- Icon for COLLAPSE (when expanded) -->
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="v2-nav-icon force-visible">
                    <polyline points="11 17 6 12 11 7"></polyline>
                    <polyline points="18 17 13 12 18 7"></polyline>
                </svg>

                <span v-if="!isCollapsed" style="white-space: nowrap;">COLLAPSE_HUD</span>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import IncidentDrawer from './IncidentDrawer.vue';

const props = defineProps({
    activeView: { type: String, default: 'overview' },
    isCollapsed: { type: Boolean, default: false }
});

const emit = defineEmits(['update:activeView', 'update:isCollapsed']);

const gameStore = useGameStore();

const wsConnected = computed(() => gameStore.wsConnected);
const isPolling = computed(() => gameStore.isPolling);
const pendingOrdersCount = computed(() => gameStore.orders?.pending?.length || 0);

const toggleCollapse = () => {
    emit('update:isCollapsed', !props.isCollapsed);
};

const selectView = (id) => {
    emit('update:activeView', id);
};

const navItemsWithTooltips = [
    { id: 'overview', label: 'UNIT_OPERATIONS', icon: '◈', tooltipContent: 'Die direkte Rack-Analyse. Verwalte Hardware und beobachte die Hitze-Entwicklung.', tooltipHint: 'Nutze das Scrollrad zum Zoomen!' },
    { id: 'locations', label: 'NODE_ASSETS', icon: '◰', tooltipContent: 'Deine Immobilien-Übersicht. Kaufe neue Rechenzentren weltweit.', tooltipHint: 'Regionale Strompreise variieren stark!' },
    { id: 'infrastructure', label: 'SYSTEMS_TELEMETRY', icon: '▣', tooltipContent: 'Hardware-Verwaltung und Shop. Kaufe neue Server und Komponenten.', tooltipHint: 'Achte auf die Energieeffizienz (PUE).' },
    { id: 'management', label: 'ENTITY_CONTROL', icon: '◧', tooltipContent: 'Kunden- und Personalmanagement. Bearbeite Verträge und Mitarbeiter-Zuweisungen.', tooltipHint: 'Zufriedene Kunden zahlen mehr!' },
    { id: 'network', label: 'NETWORK_OPERATIONS', icon: '📡', tooltipContent: 'BGP-Routing und IP-Verwaltung. Optimiere deine Anbindungen.', tooltipHint: 'Anycast-Routing reduziert die Latenz.' },
    { id: 'research', label: 'RD_PROTOCOLS', icon: '⌬', tooltipContent: 'Forschungslabore. Schalte neue Technologien und Buffs frei.', tooltipHint: 'Priorisiere die Energie-Forschung!' },
    { id: 'market', label: 'MARKET_EXCHANGE', icon: '📈', tooltipContent: 'Finanzmärkte und Börse. Verwalte Kredite und Handelskriege.', tooltipHint: 'Leihe Geld nur wenn du expandierst.' },
    { id: 'world', label: 'GLOBAL_INTEL', icon: '🌐', tooltipContent: 'Die Weltkarte und Ranglisten. Sieh wie du im Vergleich stehst.', tooltipHint: 'Beachte globale Events im News-Ticker!' },
    { id: 'settings', label: 'SYSTEM_CONFIG', icon: '⚙', tooltipContent: 'Kern-Konfiguration des Operator-Systems.', tooltipHint: 'Hier kannst du deinen Firmennamen ändern.' },
];
</script>

<style scoped>
.v2-sidebar {
    height: 100vh;
    background: var(--v3-bg-surface);
    border-right: var(--v3-border-heavy);
    display: flex;
    flex-direction: column;
    transition: width var(--v3-transition-base);
    z-index: 1000;
}

.v2-sidebar-header {
    height: var(--v3-topbar-height);
    display: flex;
    align-items: center;
    padding: 0 20px;
    border-bottom: var(--v3-border-soft);
}

.v2-logo-text {
    font-size: 0.85rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.2em;
}

.v2-badge {
    font-size: 0.5rem;
    font-weight: 800;
    color: var(--v3-accent);
    opacity: 0.8;
    margin-left: 8px;
}

.v2-nav-list {
    flex: 1;
    padding: 12px 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
}

.v2-nav-section {
    padding: 24px 20px 8px;
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.25em;
}

.v2-nav-spacer {
    flex: 1;
}

.v2-system-status {
    padding: 12px 20px;
    background: rgba(0,0,0,0.1);
    border-top: var(--v3-border-soft);
}

.v2-status-row {
    font-size: 0.6rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
}

.v2-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--v3-danger);
    margin-right: 8px;
    display: inline-block;
}

.v2-status-dot.is-online {
    background: var(--v3-success);
    box-shadow: 0 0 5px var(--v3-success);
}
.v2-status-dot.is-warning {
    background: var(--v3-warning);
    box-shadow: 0 0 5px var(--v3-warning);
    animation: v3-pulse-state 2s infinite ease-in-out;
}
.v2-status-value.is-warning {
    color: var(--v3-warning);
}

.v2-sidebar-footer {
    display: flex;
    justify-content: stretch;
    padding: 0;
    border-top: var(--v3-border-soft);
    flex-shrink: 0;
    min-height: 48px;
    background: var(--v3-bg-surface);
}

.v2-collapse-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: flex-start; /* Default left */
    gap: 12px;
    padding: 12px 20px;
    color: var(--v3-text-ghost);
    background: transparent;
    border: none;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: all var(--v3-transition-fast);
}

.is-collapsed .v2-collapse-btn {
    padding: 12px 0;
    justify-content: center; /* Centered when collapsed */
}

.v2-collapse-btn:hover {
    color: #fff;
    background: var(--v3-bg-accent);
}

.item-badge {
    position: absolute;
    right: 12px;
    background: var(--v3-danger);
    color: #fff;
    font-size: 0.55rem;
    font-weight: 900;
    padding: 1px 5px;
    border-radius: 2px;
}

 

.v2-nav-icon {
    font-size: 1.25rem;
    margin-right: 16px;
    width: 24px;
    text-align: center;
    transition: all 0.2s;
}

.is-collapsed .v2-nav-icon {
    margin-right: 0;
}

.force-visible {
    opacity: 1 !important;
    display: inline-block !important;
    width: 24px;
    height: 24px;
    min-width: 24px;
    stroke: var(--v3-text-ghost);
    transition: stroke 0.2s;
}

.v2-collapse-btn:hover .force-visible {
    stroke: var(--v3-accent);
}
</style>
