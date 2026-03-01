<template>
    <div class="v2-main-viewport management-v2">
        <header class="v2-content-header">
            <div class="v2-breadcrumb">
                <span class="v2-path">MISSION_CONTROL</span>
                <span class="v2-sep">//</span>
                <span class="v2-node">{{ activeTab.toUpperCase() }}</span>
            </div>
            
            <div class="v2-room-tabs">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="v2-room-tab"
                    :class="{ 'is-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>
        </header>

        <div class="v2-content-scroll">
            <transition name="v3-fade" mode="out-in">
                <div :key="activeTab">
                    <!-- Active Tab Content -->
                    <div v-if="activeTab === 'dashboard'" class="v2-tab-pane">
                        <ExecutiveDashboard />
                    </div>

                    <div v-else-if="activeTab === 'strategy'" class="v2-tab-pane">
                        <StrategyDashboard />
                    </div>


                    <div v-else-if="activeTab === 'orders'" class="v2-tab-pane">
                        <OrderList />
                    </div>

                    <div v-else-if="activeTab === 'customers'" class="v2-tab-pane">
                        <CustomersOverlay inline />
                    </div>

                    <div v-else-if="activeTab === 'employees'" class="v2-tab-pane">
                        <HRDashboard />
                    </div>

                    <div v-else-if="activeTab === 'support'" class="v2-tab-pane">
                        <SupportDashboard />
                    </div>

                    <div v-else-if="activeTab === 'finance'" class="v2-tab-pane">
                        <FinanceOverlay inline />
                    </div>

                    <div v-else-if="activeTab === 'noc'" class="v2-tab-pane">
                        <NOCDashboard />
                    </div>

                    <div v-else-if="activeTab === 'incidents'" class="v2-tab-pane">
                        <IncidentLog />
                    </div>

                    <div v-else-if="activeTab === 'compliance'" class="v2-tab-pane">
                        <ComplianceOverlay inline />
                    </div>
                    
                    <div v-else-if="activeTab === 'leaderboard'" class="v2-tab-pane">
                        <LeaderboardOverlay inline />
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import OrderList from './OrderList.vue';
import CustomersOverlay from '../Overlay/CustomersOverlay.vue';
import HRDashboard from './HRDashboard.vue';
import FinanceOverlay from '../Overlay/FinanceOverlay.vue';
import LeaderboardOverlay from '../Overlay/LeaderboardOverlay.vue';
import NOCDashboard from './NOCDashboard.vue';
import IncidentLog from './IncidentLog.vue';
import ComplianceOverlay from '../Overlay/ComplianceOverlay.vue';
import ExecutiveDashboard from './ExecutiveDashboard.vue';
import StrategyDashboard from './StrategyDashboard.vue';
import SupportDashboard from './SupportDashboard.vue';

const tabs = [
    { id: 'dashboard', label: 'EXECUTIVE_SUMMARY', icon: '📊' },
    { id: 'strategy', label: 'STRATEGY', icon: '◧' },
    { id: 'orders', label: 'INBOUND_ORDERS', icon: '📥' },
    { id: 'support', label: 'SUPPORT_CENTER', icon: '🎧' },
    { id: 'customers', label: 'ACTIVE_ENTITIES', icon: '👥' },
    { id: 'employees', label: 'HUMAN_RESOURCES', icon: '👔' },
    { id: 'finance', label: 'FISCAL_LEDGER', icon: '💰' },
    { id: 'noc', label: 'COMMAND_CENTER', icon: '🛰️' },
    { id: 'incidents', label: 'INCIDENT_LOGS', icon: '📟' },
    { id: 'compliance', label: 'COMPLIANCE_CENTER', icon: '🛡️' },
    { id: 'leaderboard', label: 'MARKET_RANK', icon: '🏆' },
];

const props = defineProps(['initialTab']);
const activeTab = ref(props.initialTab || 'dashboard');

// Watch for prop changes if the parent dictates navigation
import { watch } from 'vue';
watch(() => props.initialTab, (newVal) => {
    if (newVal) activeTab.value = newVal;
});

</script>

<style scoped>
.management-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--color-surface);
}

.view-header {
    padding: var(--space-xl) var(--space-2xl);
    border-bottom: var(--border-ui);
}

.breadcrumb {
    display: flex;
    gap: 12px;
    font-size: 0.65rem;
    font-weight: 800;
    font-family: var(--font-mono);
    margin-bottom: 12px;
}

.breadcrumb .root { color: var(--color-muted); }
.breadcrumb .sep { color: var(--color-muted); opacity: 0.3; }
.breadcrumb .active { color: var(--color-accent); }

.management-tabs {
    display: flex;
    gap: 24px;
}

.mgmt-tab {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--color-muted);
    padding: var(--space-xs) 0;
    position: relative;
    transition: color 0.2s;
}

.mgmt-tab:hover { color: #fff; }
.mgmt-tab.active { color: #fff; }
.mgmt-tab.active::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--color-accent);
}

.view-content {
    padding: var(--space-2xl);
    flex: 1;
    overflow-y: auto;
}

.empty-state {
    text-align: center;
}

.empty-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: var(--space-md);
    opacity: 0.5;
}

.fade-fast-enter-active, .fade-fast-leave-active {
    transition: opacity 0.15s ease;
}
.fade-fast-enter-from, .fade-fast-leave-to {
    opacity: 0;
}
</style>
