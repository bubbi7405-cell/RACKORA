<template>
    <div class="strategic-directive" v-if="directive">
        <div class="directive-box l1-priority" :class="[`type--${directive.type}`]" @click="executeAction">
            <div class="directive-meta l3-priority">
                <span class="meta-icon">◈</span>
                <span class="meta-label">STRATEGIC_ADVISORY</span>
            </div>
            <div class="directive-main">
                <span class="directive-text l1-priority">{{ directive.label.toUpperCase() }}</span>
                <div class="directive-status-led"></div>
            </div>
            <div class="directive-tags l2-priority" v-if="directive.type === 'danger'">
                CRITICAL_RISK // HIGH_PRIORITY
            </div>
            <div class="directive-glow"></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();
const emit = defineEmits(['navigate']);

const props = defineProps({
    activeView: { type: String, default: 'overview' }
});

const directive = computed(() => {
    const stats = gameStore.stats || {};
    const player = gameStore.player || { economy: {} };
    const playerLevel = player.economy?.level || 1;
    const balance = player.economy?.balance || 0;
    const activeEvents = gameStore.events?.active || [];
    const pendingOrders = gameStore.orders?.pending || [];
    const totalU = (stats.totalRacks || 0) * 42;
    const usedU = (stats.totalServers || 0) * 1.5;
    const utilization = totalU > 0 ? (usedU / totalU) : 0;

    // Navigation Helper: Only return directive if not already in that view
    const isNotIn = (view) => props.activeView !== view;

    // --- PHASE 1: OPERATIONS (LEVEL 1-3) ---
    if (playerLevel <= 3) {
        if (isNotIn('incidents') && (gameStore.hasCriticalEvent || activeEvents.some(e => e.severity === 'critical'))) {
            return { label: 'RESOLVE_OPERATIONAL_RISKS', type: 'danger', action: 'incidents' };
        }
        if (utilization > 0.85 && isNotIn('infrastructure')) {
            return { label: 'EXPAND_FACILITY_CAPACITY', type: 'warning', action: 'infrastructure' };
        }
        if (pendingOrders.length >= 3 && isNotIn('orders')) {
            return { label: 'SECURE_REVENUE_FLOW', type: 'success', action: 'orders' };
        }
    }

    // --- PHASE 2: GROWTH (LEVEL 4-8) ---
    if (playerLevel <= 8) {
        if (balance > 50000 && Object.keys(gameStore.rooms || {}).length < 2 && isNotIn('locations')) {
            return { label: 'ACQUIRE_NEW_SITE', type: 'primary', action: 'locations' };
        }
        if (balance > 10000 && isNotIn('research')) {
            return { label: 'INITIATE_ASSET_R&D', type: 'primary', action: 'research' };
        }
        if (pendingOrders.some(o => o.sla?.tier === 'enterprise') && isNotIn('orders')) {
            return { label: 'SECURE_ENTERPRISE_CONTRACTS', type: 'success', action: 'orders' };
        }
    }

    // --- PHASE 3: AUTHORITY & DOMINANCE (LEVEL 9+) ---
    if (playerLevel >= 9) {
        const topThreat = gameStore.marketShare?.participants?.find(p => p.playerEnmity > 60);
        if (topThreat && isNotIn('strategy')) {
            return { label: `MITIGATE_${topThreat.name.toUpperCase()}_ASSETS`, type: 'danger', action: 'strategy' };
        }
        if (balance > 250000 && isNotIn('market')) {
            return { label: 'SECURE_MARKET_DOMINANCE', type: 'primary', action: 'market' };
        }
        if (isNotIn('energy')) {
            return { label: 'STABILIZE_ENERGY_COSTS', type: 'warning', action: 'energy' };
        }
    }

    // Default: Status Review
    return { label: 'REVIEW_NETWORK_STATUS', type: 'secondary', action: 'overview' };
});

const executeAction = () => {
    if (directive.value?.action) {
        emit('navigate', directive.value.action);
    }
};
</script>

<style scoped>
.strategic-directive {
    display: flex;
    justify-content: center;
    align-items: center;
    min-width: 320px;
}

.directive-box {
    background: rgba(5, 10, 20, 0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 12px 48px;
    border-radius: 4px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.4s var(--ds-ease-out);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.8);
}

.directive-box::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
}

.directive-box:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
}

.directive-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.meta-icon {
    font-size: 0.5rem;
    color: var(--v3-text-ghost);
}

.meta-label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.25em;
}

.directive-main {
    display: flex;
    align-items: center;
    gap: 12px;
}

.directive-text {
    font-size: 0.7rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.directive-status-led {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--v3-text-ghost);
    box-shadow: 0 0 5px rgba(255,255,255,0.2);
}

.directive-tags {
    font-size: 0.45rem;
    font-weight: 800;
    color: var(--v3-danger);
    letter-spacing: 0.1em;
    margin-top: 2px;
}

/* Types */
.type--danger { border-color: rgba(255, 77, 79, 0.3); }
.type--danger .directive-text { color: var(--v3-danger); }
.type--danger .directive-status-led { background: var(--v3-danger); box-shadow: 0 0 8px var(--v3-danger); animation: v3-pulse-state 1s infinite; }

.type--warning { border-color: rgba(255, 179, 0, 0.3); }
.type--warning .directive-text { color: var(--v3-warning); }
.type--warning .directive-status-led { background: var(--v3-warning); box-shadow: 0 0 8px var(--v3-warning); }

.type--success { border-color: rgba(0, 230, 118, 0.3); }
.type--success .directive-text { color: var(--v3-success); }
.type--success .directive-status-led { background: var(--v3-success); box-shadow: 0 0 8px var(--v3-success); }

.type--primary { border-color: rgba(88, 166, 255, 0.3); }
.type--primary .directive-text { color: var(--v3-accent); }
.type--primary .directive-status-led { background: var(--v3-accent); }

.directive-glow {
    position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: scan-line 3s infinite linear;
}

@keyframes scan-line {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes v3-pulse-state {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}
</style>
