<template>
    <aside class="v2-sidebar" :class="{ 'is-collapsed': isCollapsed }">
        <div class="v2-sidebar-header">
            <div class="v2-logo" v-if="!isCollapsed">
                <div class="logo-main l1-priority">RACKORA</div>
                <div class="logo-sub l3-priority">STRATEGIC_ASSET_PORTFOLIO</div>
            </div>
            <div class="v2-logo-collapsed l1-priority" v-else>R</div>
        </div>

        <nav class="v2-nav-list">
            <!-- Domain Groups -->
            <template v-for="domain in domains" :key="domain.id">
                <!-- Domain Header -->
                <div
                    class="domain-header"
                    :class="{
                        'is-locked': !isDomainUnlocked(domain),
                        'is-expanded': expandedDomains[domain.id],
                        'has-active': domainContainsActive(domain),
                        'l2-priority': isDomainUnlocked(domain),
                        'l4-priority': !isDomainUnlocked(domain)
                    }"
                    @click="toggleDomain(domain)"
                    v-if="!isCollapsed"
                >
                    <div class="domain-marker"></div>
                    <span class="domain-label">{{ domain.label.toUpperCase() }}</span>
                    
                    <div v-if="!isDomainUnlocked(domain)" class="milestone-lock l4-priority">
                        <span class="lock-req">[CLASSIFIED_REQ: L_{{ domain.unlockLevel }}]</span>
                    </div>
                    <span v-else-if="getDomainBadgeCount(domain) > 0" class="domain-badge l1-priority">{{ getDomainBadgeCount(domain) }}</span>
                    <span v-else class="domain-chevron l3-priority" :class="{ 'is-open': expandedDomains[domain.id] }">◈</span>
                </div>

                <!-- Domain Items -->
                <div class="domain-items-container" v-show="isDomainUnlocked(domain) && (expandedDomains[domain.id] || isCollapsed)">
                    <div
                        v-for="item in getVisibleItems(domain)"
                        :key="item.id"
                        class="v2-nav-item"
                        :class="{
                            'is-active': activeView === item.id,
                            'l1-priority': activeView === item.id,
                            'l3-priority': activeView !== item.id
                        }"
                        @click="selectView(item.id)"
                    >
                        <div class="v2-nav-icon">{{ item.icon }}</div>
                        <div class="v2-nav-label">{{ item.label.toUpperCase() }}</div>
                        <div class="item-badge l1-priority" v-if="getItemBadge(item) > 0">
                            {{ getItemBadge(item) }}
                        </div>
                    </div>
                </div>

                <!-- Collapsed Locked State -->
                <div
                    v-if="isCollapsed && !isDomainUnlocked(domain)"
                    class="v2-nav-item is-locked-item l4-priority"
                >
                    <div class="v2-nav-icon locked-icon">🔒</div>
                </div>
            </template>

            <div class="v2-nav-spacer"></div>

            <!-- Global Network Dominance -->
            <div class="dominance-section" v-if="!isCollapsed">
                <div class="dominance-header">
                    <span class="dominance-label l2-priority">MARKET_PORTFOLIO</span>
                    <span class="dominance-val l1-priority">{{ dominancePercent }}%</span>
                </div>
                <div class="dominance-bar-container">
                    <div class="dominance-bar-bg">
                        <div class="dominance-bar-fill" :style="{ width: dominancePercent + '%' }"></div>
                    </div>
                    <div class="dominance-status l3-priority">// PORTFOLIO_INDEX: [OPTIMIZED]</div>
                </div>
            </div>

            <IncidentDrawer :collapsed="isCollapsed" />

            <div class="v2-system-status" v-if="!isCollapsed">
                <div class="v2-status-row l3-priority">
                    <span class="v2-status-dot" :class="{ 'is-online': wsConnected && !isPolling }"></span>
                    <span class="v2-status-label">NETWORK_LINK::</span>
                    <span class="v2-status-value">
                        {{ wsConnected ? 'AUTHORITY_STABLE' : 'LINK_INTERRUPTED' }}
                    </span>
                </div>
            </div>
        </nav>

        <div class="v2-sidebar-footer">
            <button class="v2-collapse-btn l2-priority" @click="$emit('update:isCollapsed', !isCollapsed)">
                <span class="v2-nav-icon">{{ isCollapsed ? '≫' : '≪' }}</span>
                <span v-if="!isCollapsed">SECURE_HUD</span>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue';
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
const playerLevel = computed(() => gameStore.player?.economy?.level || 1);
const pendingOrdersCount = computed(() => gameStore.orders?.pending?.length || 0);
const activeEventsCount = computed(() => gameStore.events?.active?.length || 0);

const dominancePercent = computed(() => {
    const totalRooms = 15; // Max rooms possible
    const currentRooms = Object.keys(gameStore.rooms || {}).length;
    const totalU = (gameStore.stats?.totalRacks || 0) * 42;
    const usedU = (gameStore.stats?.totalServers || 0) * 1.5;
    const utilization = totalU > 0 ? (usedU / totalU) : 0;
    
    // Scale: Rooms count + Utilization weight
    const score = (currentRooms / totalRooms) * 80 + (utilization * 20);
    return Math.min(100, Math.round(score * 10) / 10 || 0.1);
});

// Track which items have been "seen" after unlock (for new-indicator)
const seenItems = reactive(JSON.parse(localStorage.getItem('rackora_seen_items') || '{}'));

// Domain expansion state — Operations starts expanded
const expandedDomains = reactive({
    operations: true,
    finance: false,
    strategy: false,
    knowledge: true,
    maintenance: false,
});

// ─── DOMAIN DEFINITIONS ───────────────────────────────────────
const domains = [
    {
        id: 'operations',
        label: 'OPERATIONS',
        icon: '◈',
        unlockLevel: 1,
        items: [
            { id: 'overview', label: 'OVERVIEW', icon: '⧇', unlockLevel: 1 },
            { id: 'locations', label: 'NETWORK', icon: '◰', unlockLevel: 1 },
            { id: 'infrastructure', label: 'ASSETS', icon: '▣', unlockLevel: 1 },
            { id: 'hosting', label: 'GAME_HOSTS', icon: '🧊', unlockLevel: 1 },
            { id: 'noc', label: 'FACILITY_OPS', icon: '📡', unlockLevel: 2 },
            { id: 'incidents', label: 'INCIDENT_LOGS', icon: '📟', unlockLevel: 8 },
            { id: 'replay', label: 'POST_AUDIT', icon: '⏮️', unlockLevel: 12 },
        ]
    },
    {
        id: 'finance',
        label: 'FINANCE',
        icon: '💼',
        unlockLevel: 1,
        items: [
            { id: 'orders', label: 'CONTRACTS', icon: '📦', unlockLevel: 1, badge: 'pendingOrders' },
            { id: 'customers', label: 'CLIENT_BASE', icon: '👥', unlockLevel: 3 },
            { id: 'personnel', label: 'HUMAN_ASSETS', icon: '👔', unlockLevel: 3 },
            { id: 'finance', label: 'LEDGER', icon: '💰', unlockLevel: 1 },
            { id: 'executive', label: 'EXECUTIVE_HUB', icon: '📊', unlockLevel: 5 },
            { id: 'analytics', label: 'BI_ANALYTICS', icon: '📺', unlockLevel: 8 },
        ]
    },
    {
        id: 'strategy',
        label: 'STRATEGY',
        icon: '⌬',
        unlockLevel: 5,
        items: [
            { id: 'research', label: 'ASSET_R&D', icon: '⌬', unlockLevel: 5 },
            { id: 'strategy', label: 'BOARD_STRATEGY', icon: '🎯', unlockLevel: 5 },
            { id: 'automation', label: 'AUTO_PROTOCOLS', icon: '⚙️', unlockLevel: 10 },
            { id: 'market', label: 'MARKET_INDEX', icon: '📈', unlockLevel: 7 },
            { id: 'compliance', label: 'COMPLIANCE', icon: '🛡️', unlockLevel: 10 },
            { id: 'energy', label: 'ENERGY_GRID', icon: '⚡', unlockLevel: 7 },
            { id: 'world', label: 'MARKET_PROJECTION', icon: '🌐', unlockLevel: 12 },
        ]
    },
    {
        id: 'knowledge',
        label: 'KNOWLEDGE',
        icon: '📚',
        unlockLevel: 1,
        items: [
            { id: 'wiki_beginner', label: 'Beginner Guide', icon: '👶', unlockLevel: 1 },
            { id: 'wiki_infrastructure', label: 'Infrastructure', icon: '🏗️', unlockLevel: 1 },
            { id: 'wiki_servers', label: 'Servers', icon: '🌐', unlockLevel: 1 },
            { id: 'wiki_network', label: 'Network', icon: '📡', unlockLevel: 1 },
            { id: 'wiki_economy', label: 'Economy', icon: '💰', unlockLevel: 1 },
            { id: 'wiki_strategy', label: 'Strategy', icon: '⌬', unlockLevel: 1 },
            { id: 'wiki_events', label: 'Events', icon: '🌪️', unlockLevel: 1 },
        ]
    },
    {
        id: 'maintenance',
        label: 'MAINTENANCE',
        icon: '🔧',
        unlockLevel: 1,
        items: [
            { id: 'admin', label: 'ROOT_TERM', icon: '⌨️', unlockLevel: 1 },
        ]
    },
];

// ─── COMPUTED ──────────────────────────────────────────────────

const visibleDomains = computed(() => {
    // All domains are always visible (locked ones show as locked)
    return domains;
});

// ─── METHODS ──────────────────────────────────────────────────

function isDomainUnlocked(domain) {
    return playerLevel.value >= domain.unlockLevel;
}

function getVisibleItems(domain) {
    return domain.items.filter(item => playerLevel.value >= item.unlockLevel);
}

function toggleDomain(domain) {
    if (!isDomainUnlocked(domain)) return;
    expandedDomains[domain.id] = !expandedDomains[domain.id];
}

function selectView(id) {
    // Mark as seen
    if (!seenItems[id]) {
        seenItems[id] = true;
        localStorage.setItem('rackora_seen_items', JSON.stringify(seenItems));
    }
    emit('update:activeView', id);
}

function domainContainsActive(domain) {
    return domain.items.some(item => item.id === props.activeView);
}

function getDomainBadgeCount(domain) {
    let count = 0;
    for (const item of domain.items) {
        count += getItemBadge(item);
    }
    return count;
}

function getItemBadge(item) {
    if (item.badge === 'pendingOrders') return pendingOrdersCount.value;
    if (item.badge === 'activeEvents') return activeEventsCount.value;
    return 0;
}

function isNewlyUnlocked(item) {
    if (playerLevel.value < item.unlockLevel) return false;
    return !seenItems[item.id] && item.unlockLevel > 1;
}

// ─── WATCHERS ─────────────────────────────────────────────────

// Auto-expand domain when navigating to one of its children
watch(() => props.activeView, (newView) => {
    for (const domain of domains) {
        if (domain.items.some(item => item.id === newView)) {
            expandedDomains[domain.id] = true;
        }
    }
});

// Auto-expand newly unlocked domains with a slight celebration
watch(playerLevel, (newLevel, oldLevel) => {
    if (!oldLevel || newLevel <= oldLevel) return;
    for (const domain of domains) {
        if (domain.unlockLevel === newLevel) {
            // Auto-expand newly unlocked domain
            setTimeout(() => {
                expandedDomains[domain.id] = true;
            }, 300);
        }
    }
});
</script>

<style scoped>
.v2-sidebar {
    height: 100vh;
    background: var(--ds-sidebar-bg);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    flex-direction: column;
    width: var(--v3-sidebar-width);
    transition: width var(--v3-transition-base);
    z-index: var(--zi-hud-base);
    overflow: hidden;
}

.is-collapsed .v2-sidebar {
    width: var(--v3-sidebar-collapsed);
}

.v2-sidebar-header {
    height: 64px;
    display: flex;
    align-items: center;
    padding: 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.logo-main {
    font-weight: 800;
    letter-spacing: 0.06em;
    font-size: 1rem;
    color: #fff;
    font-family: var(--ds-font-sans);
}

.logo-sub {
    font-size: 0.625rem;
    color: var(--ds-sidebar-text);
    letter-spacing: 0.02em;
    margin-top: 2px;
    font-weight: 500;
}

.v2-logo-collapsed {
    font-size: 1.125rem;
    font-weight: 800;
    color: #fff;
    text-align: center;
    width: 100%;
}

.v2-nav-list {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    padding-bottom: 20px;
}

/* ── DOMAIN HEADERS ─────────────────────────── */
.domain-header {
    display: flex;
    align-items: center;
    padding: 20px 20px 8px;
    cursor: pointer;
    position: relative;
    transition: background 0.15s;
}

.domain-header:hover:not(.is-locked) {
    background: rgba(255, 255, 255, 0.03);
}

.domain-marker {
    width: 3px;
    height: 12px;
    background: var(--ds-accent);
    margin-right: 10px;
    border-radius: 2px;
    opacity: 0.6;
}

.domain-header.is-locked .domain-marker {
    background: var(--ds-sidebar-text);
    opacity: 0.3;
}

.domain-label {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--ds-sidebar-text);
    letter-spacing: 0.06em;
    flex: 1;
    text-transform: uppercase;
}

.domain-header.has-active .domain-label {
    color: #fff;
}

.milestone-lock {
    font-size: 0.625rem;
    font-weight: 600;
    color: var(--ds-sidebar-text);
    opacity: 0.5;
}

/* ── NAV ITEMS ─────────────────────────────── */
.v2-nav-item {
    display: flex;
    align-items: center;
    padding: 9px 20px 9px 36px;
    cursor: pointer;
    position: relative;
    transition: all 0.15s;
    border-left: 3px solid transparent;
    color: var(--ds-sidebar-text);
}

.v2-nav-item:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #fff;
}

.v2-nav-item.is-active {
    background: var(--ds-sidebar-active);
    border-left: 3px solid var(--ds-accent);
    color: #fff;
}

.v2-nav-icon {
    width: 20px;
    font-size: 0.9rem;
    margin-right: 12px;
    display: flex;
    justify-content: center;
    opacity: 0.7;
}

.v2-nav-item.is-active .v2-nav-icon,
.v2-nav-item:hover .v2-nav-icon {
    opacity: 1;
}

.v2-nav-label {
    font-size: 0.8125rem;
    font-weight: 500;
    letter-spacing: 0;
    white-space: nowrap;
}

/* ── DOMINANCE METER ─────────────────────────── */
.dominance-section {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.dominance-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.dominance-label {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--ds-sidebar-text);
    text-transform: uppercase;
}

.dominance-val {
    font-size: 0.75rem;
    font-weight: 700;
    font-family: var(--ds-font-mono);
    color: #fff;
}

.dominance-bar-bg {
    height: 4px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: var(--ds-radius-full);
    overflow: hidden;
}

.dominance-bar-fill {
    height: 100%;
    background: var(--ds-accent);
    border-radius: var(--ds-radius-full);
    transition: width 1.5s ease;
    position: relative;
}

.v2-system-status {
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.02);
}

.v2-status-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--ds-sidebar-text);
}

.v2-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-critical);
}

.v2-status-dot.is-online {
    background: var(--ds-nominal);
}

.v2-sidebar-footer {
    padding: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.v2-collapse-btn {
    width: 100%;
    padding: 14px 20px;
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--ds-sidebar-text);
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 500;
}

.v2-collapse-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.04);
}
</style>


