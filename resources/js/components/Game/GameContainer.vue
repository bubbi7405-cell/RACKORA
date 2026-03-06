<template>
    <div class="v2-layout" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <Sidebar class="v2-sidebar" :class="{
            'is-collapsed': isSidebarCollapsed,
            'v3-dimmer': isModalActive
        }" v-model:isCollapsed="isSidebarCollapsed" :activeView="activeView" @update:activeView="handleViewChange" />

        <div class="v2-content-wrapper" :class="{ 'v3-dimmer': isModalActive }">
            <TopBar class="v2-topbar" :active-view="activeView" @openNocWall="showNocWall = true"
                @openProfile="showProfileOverlay = true" @openFinance="activeView = 'finance'"
                @openReplay="activeView = 'replay'" @openCustomers="activeView = 'customers'"
                @openEmployees="activeView = 'personnel'" @openView="handleViewChange" />

            <main class="v2-main-viewport">
                <!-- ═══ DOMAIN: OPERATIONS ═══ -->
                <div v-if="activeView === 'overview'" class="overview-layout">
                    <LeftPanel @openResearch="activeView = 'research'" @openSpecialization="activeView = 'strategy'"
                        @openSandbox="showAssemblyOverlay = true" @openMarket="activeView = 'market'"
                        @openMarketing="activeView = 'market'" @openAnalytics="activeView = 'analytics'"
                        @openCustomers="activeView = 'customers'" @openLab="showLabOverlay = true" />
                    <GameWorld @open-server-details="openServerDetails" @openRackPurchase="showRackPurchase = true" @openSandbox="showShop = true" />
                    <aside class="v2-right-panel">
                        <GlobalEventTicker :active-view="activeView" />
                        <LogTicker :active-view="activeView" />
                        <VulnerabilityHUD :active-view="activeView" />
                    </aside>
                </div>

                <div v-else-if="activeView === 'locations'" class="context-page">
                    <LocationsView @room-switched="activeView = 'overview'"
                        @open-region-select="(type) => { pendingRoomPurchase = type; showRegionSelect = true; }" />
                </div>

                <div v-else-if="activeView === 'infrastructure'" class="context-page">
                    <InfrastructureView @openShop="() => { shopCategory = 'vserver_node'; showShop = true; }"
                        @openRackPurchase="showRackPurchase = true"
                        @openInventory="(rackId) => { shopCategory = 'inventory'; gameStore.selectRack(rackId); showShop = true; }"
                        @openMarket="showEnergyOverlay = true" @openLab="showLabOverlay = true" />
                </div>

                <div v-else-if="activeView === 'noc'" class="context-page">
                    <NOCDashboard />
                </div>

                <div v-else-if="activeView === 'hosting'" class="context-page">
                    <HostingDashboard />
                </div>

                <!-- ═══ DOMAIN: BUSINESS ═══ -->
                <div v-else-if="activeView === 'orders'" class="context-page">
                    <OrderList />
                </div>

                <div v-else-if="activeView === 'customers'" class="context-page">
                    <CustomersOverlay inline @close="activeView = 'overview'" />
                </div>

                <div v-else-if="activeView === 'personnel'" class="context-page">
                    <HRDashboard />
                </div>

                <div v-else-if="activeView === 'finance'" class="context-page">
                    <FinanceOverlay inline @close="activeView = 'overview'" />
                </div>

                <div v-else-if="activeView === 'executive'" class="context-page">
                    <ExecutiveDashboard />
                </div>

                <div v-else-if="activeView === 'support'" class="context-page">
                    <SupportDashboard />
                </div>

                <!-- ═══ DOMAIN: STRATEGY ═══ -->
                <div v-else-if="activeView === 'research'" class="context-page">
                    <ResearchView />
                </div>

                <div v-else-if="activeView === 'strategy'" class="context-page">
                    <StrategyDashboard />
                </div>

                <div v-else-if="activeView === 'market'" class="context-page">
                    <MarketDashboard @close="activeView = 'overview'" />
                </div>

                <div v-else-if="activeView === 'compliance'" class="context-page">
                    <ComplianceOverlay inline @close="activeView = 'overview'" />
                </div>

                <div v-else-if="activeView === 'energy'" class="context-page">
                    <MarketDashboard initial-tab="energy" @close="activeView = 'overview'" />
                </div>

                <!-- ═══ DOMAIN: INTELLIGENCE ═══ -->
                <div v-else-if="activeView === 'network'" class="context-page">
                    <NetworkView />
                </div>

                <div v-else-if="activeView === 'incidents'" class="context-page">
                    <IncidentLog />
                </div>

                <div v-else-if="activeView === 'analytics'" class="context-page">
                    <AnalyticsDashboard />
                </div>

                <div v-else-if="activeView === 'world'" class="context-page">
                    <WorldView />
                </div>

                <div v-else-if="activeView === 'replay'" class="context-page">
                    <ReplayView />
                </div>

                <div v-else-if="activeView === 'automation'" class="context-page">
                    <AutomationView />
                </div>

                <div v-else-if="activeView === 'admin'" class="context-page">
                    <AdminPanel />
                </div>

                <!-- ═══ DOMAIN: KNOWLEDGE ═══ -->
                <div v-else-if="activeView.startsWith('wiki_')" class="context-page">
                    <WikiSystem :initial-tab="activeView.replace('wiki_', '')" />
                </div>

                <!-- ═══ LEGACY: ManagementView fallback ═══ -->
                <div v-else-if="activeView === 'management'" class="context-page">
                    <ManagementView :initial-tab="managementTab" />
                </div>
            </main>

            <!-- PERSPECTIVE SHIFT OVERLAY -->
            <transition name="perspective">
                <div v-if="isReorienting" class="v2-perspective-overlay">
                    <div class="overlay-scanline"></div>
                    <div class="overlay-telemetry">
                        <div class="telemetry-box l1-priority">
                            <span class="l3-priority">// DOMAIN_ACQUISITION</span>
                            <div class="telemetry-label">RE_ORIENTING::{{ activeView.toUpperCase() }}</div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Infrastructure Shop (Slide Panel) -->
        <transition name="slide-right">
            <div v-if="showShop" class="shop-panel-wrapper">
                <div class="panel-backdrop" :class="{ 'dragging-active': gameStore.isDragging }"
                    @click="showShop = false"></div>
                <RightPanel slide-out :initial-category="shopCategory" @close="showShop = false"
                    @openDetails="openServerDetails" @openAssembly="showAssemblyOverlay = true" />
            </div>
        </transition>

        <!-- Overlays (Modal Layer) -->
        <ServerDetailOverlay v-if="showServerDetailOverlay" :serverId="detailServerId"
            @close="showServerDetailOverlay = false" />
        <RegionSelectOverlay v-if="showRegionSelect" :roomType="pendingRoomPurchase"
            :roomCost="getRoomCost(pendingRoomPurchase)" @close="showRegionSelect = false" />
        <AssemblyOverlay v-if="showAssemblyOverlay" @close="showAssemblyOverlay = false" />
        <RackPurchaseOverlay v-if="showRackPurchase" @close="showRackPurchase = false" />
        <TutorialOverlay v-if="player && !player.tutorial_completed" />

        <LoginSummaryOverlay v-if="showLoginSummary && loginSummary" :summary="loginSummary"
            @close="showLoginSummary = false" />

        <RoadmapOverlay v-if="showRoadmapOverlay" @close="showRoadmapOverlay = false" />
        <ProfileOverlay v-if="showProfileOverlay" @close="showProfileOverlay = false" />
        <EnergyMarketOverlay v-if="showEnergyOverlay" @close="showEnergyOverlay = false" />

        <Tooltip :visible="tooltipStore.visible" :title="tooltipStore.title" :content="tooltipStore.content"
            :hint="tooltipStore.hint" :x="tooltipStore.x" :y="tooltipStore.y" />

        <OrderOverlay v-if="uiStore.selectedOrder" :order="uiStore.selectedOrder" @close="uiStore.selectOrder(null)"
            @negotiate="openNegotiation" />
        <ContractNegotiationOverlay v-if="negotiatingOrder" :order="negotiatingOrder" @close="closeNegotiation"
            @negotiated="handleNegotiationComplete" />
        <AttackOverlay />
        <GlobalCrisisOverlay />
        <AIAdvisor />
        <NOCWallView v-if="showNocWall" @close="showNocWall = false" />
        <BenchmarkingLabOverlay v-if="showLabOverlay" @close="showLabOverlay = false" />
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useUiStore } from '../../stores/ui';
import { useTooltipStore } from '../../stores/tooltip';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

// Core Layout Components
import Sidebar from '../HUD/Sidebar.vue';
import TopBar from '../HUD/TopBar.vue';
import GlobalEventTicker from '../HUD/GlobalEventTicker.vue';
import LogTicker from '../HUD/LogTicker.vue';
import GameWorld from './GameWorld.vue';
import RightPanel from '../HUD/RightPanel.vue';
import LeftPanel from '../HUD/LeftPanel.vue';

// ─── DOMAIN: OPERATIONS ───
import InfrastructureView from './InfrastructureView.vue';
import LocationsView from './LocationsView.vue';
import HostingDashboard from './HostingDashboard.vue';
import NOCDashboard from './NOCDashboard.vue';

// ─── DOMAIN: BUSINESS ───
import OrderList from './OrderList.vue';
import CustomersOverlay from '../Overlay/CustomersOverlay.vue';
import HRDashboard from './HRDashboard.vue';
import FinanceOverlay from '../Overlay/FinanceOverlay.vue';
import ExecutiveDashboard from './ExecutiveDashboard.vue';
import SupportDashboard from './SupportDashboard.vue';

// ─── DOMAIN: STRATEGY ───
import ResearchView from './ResearchView.vue';
import StrategyDashboard from './StrategyDashboard.vue';
import MarketDashboard from '../Market/MarketDashboard.vue';
import ComplianceOverlay from '../Overlay/ComplianceOverlay.vue';

// ─── DOMAIN: INTELLIGENCE ───
import NetworkView from './NetworkView.vue';
import IncidentLog from './IncidentLog.vue';
import AnalyticsDashboard from '../Market/AnalyticsDashboard.vue';
import WorldView from './WorldView.vue';
import ReplayView from './ReplayView.vue';
import AutomationView from './AutomationView.vue';
import AdminPanel from './AdminPanel.vue';

// ─── DOMAIN: KNOWLEDGE ───
import WikiSystem from '../Wiki/WikiSystem.vue';

// ─── LEGACY (kept for backward compatibility) ───
import ManagementView from './ManagementView.vue';
import NOCWallView from './NOCWallView.vue';

// ─── OVERLAYS (Modal Layer) ───
import ServerDetailOverlay from '../Overlay/ServerDetailOverlay.vue';
import RegionSelectOverlay from '../Overlay/RegionSelectOverlay.vue';
import OrderOverlay from '../Overlay/OrderOverlay.vue';
import AssemblyOverlay from '../Overlay/AssemblyOverlay.vue';
import ContractNegotiationOverlay from '../Overlay/ContractNegotiationOverlay.vue';
import TutorialOverlay from '../Overlay/TutorialOverlay.vue';
import LoginSummaryOverlay from '../Overlay/LoginSummaryOverlay.vue';
import AttackOverlay from '../Overlay/AttackOverlay.vue';
import GlobalCrisisOverlay from '../Overlay/GlobalCrisisOverlay.vue';
import BenchmarkingLabOverlay from '../Overlay/BenchmarkingLabOverlay.vue';
import Tooltip from '../UI/Tooltip.vue';
import AIAdvisor from '../HUD/AIAdvisor.vue';
import VulnerabilityHUD from '../HUD/VulnerabilityHUD.vue';
import ProfileOverlay from '../Overlay/ProfileOverlay.vue';
import RoadmapOverlay from '../Overlay/RoadmapOverlay.vue';
import EnergyMarketOverlay from '../Overlay/EnergyMarketOverlay.vue';
import RackPurchaseOverlay from '../Overlay/RackPurchaseOverlay.vue';

const gameStore = useGameStore();
const uiStore = useUiStore();
const tooltipStore = useTooltipStore();

const activeView = ref('overview');
const isReorienting = ref(false);
const isSidebarCollapsed = ref(false);
const showShop = ref(false);
const showRackPurchase = ref(false);
const shopCategory = ref('vserver_node');

const showServerDetailOverlay = ref(false);
const showRegionSelect = ref(false);
const showAssemblyOverlay = ref(false);
const showLoginSummary = ref(false);
const showRoadmapOverlay = ref(false);
const showProfileOverlay = ref(false);
const showEnergyOverlay = ref(false);
const showNocWall = ref(false);
const showLabOverlay = ref(false);

const detailServerId = ref(null);
const loginSummary = ref(null);
const pendingRoomPurchase = ref('garage');
const managementTab = ref('dashboard');
const negotiatingOrder = ref(null);

const player = computed(() => gameStore.player);

const isModalActive = computed(() => {
    return showServerDetailOverlay.value ||
        showRegionSelect.value ||
        showAssemblyOverlay.value ||
        showLoginSummary.value ||
        showRoadmapOverlay.value ||
        showProfileOverlay.value ||
        showEnergyOverlay.value ||
        showNocWall.value ||
        showLabOverlay.value ||
        !!uiStore.selectedOrder ||
        !!negotiatingOrder.value;
});

const openServerDetails = (id) => {
    showShop.value = false;
    detailServerId.value = id;
    showServerDetailOverlay.value = true;
};

const getRoomCost = (type) => {
    return gameStore.locationDefinitions?.[type]?.unlock_cost || 0;
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
        if (showShop.value) { showShop.value = false; return; }
        showServerDetailOverlay.value = false;
        showRegionSelect.value = false;
        showAssemblyOverlay.value = false;
        showNocWall.value = false;
        showLabOverlay.value = false;
        showProfileOverlay.value = false;
        return;
    }
};

const handleViewChange = (view) => {
    if (view === 'settings') {
        showProfileOverlay.value = true;
    } else if (view !== activeView.value) {
        // Trigger Digital Perspective Shift
        isReorienting.value = true;
        setTimeout(() => {
            activeView.value = view;
            setTimeout(() => {
                isReorienting.value = false;
            }, 300);
        }, 150);
    }
};

const openNegotiation = (order) => {
    uiStore.selectOrder(null);
    negotiatingOrder.value = order;
};

const closeNegotiation = () => {
    negotiatingOrder.value = null;
};

const handleNegotiationComplete = (newOrderData) => {
    gameStore.loadGameState();
    closeNegotiation();
    gameStore.selectOrder(newOrderData);
};

onMounted(async () => {
    gameStore.connectWebSocket();
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('click', () => SoundManager.startAmbience(), { once: true });

    try {
        const response = await api.get('/game/summary');
        if (response.success && response.data) {
            loginSummary.value = response.data;
            showLoginSummary.value = true;
        }
    } catch (e) { console.error(e); }
});

onUnmounted(() => {
    gameStore.stopPolling();
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
.v2-layout {
    width: 100vw;
    height: 100vh;
    display: flex;
    background: var(--ds-bg-void);
    color: var(--ds-text-primary);
    overflow: hidden;
    font-family: var(--ds-font-sans);
}

.v2-content-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.v2-main-viewport {
    flex: 1;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: ds-fade-in 0.4s var(--ds-ease-out);
}

.overview-layout {
    display: flex;
    flex: 1;
    width: 100%;
    height: 100%;
}

.v2-right-panel {
    width: 260px;
    background: var(--ds-bg-elevated);
    border-left: 1px solid var(--ds-border-color);
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px;
    overflow-y: auto;
    z-index: 20;
}

/* Perspective Shift Overlay */
.v2-perspective-overlay {
    position: absolute;
    inset: 0;
    background: var(--ds-bg-void);
    z-index: 5000;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.overlay-scanline {
    display: none;
}

.overlay-telemetry {
    position: relative;
    z-index: 10;
}

.telemetry-box {
    border-left: 2px solid var(--ds-accent);
    padding-left: 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.telemetry-label {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--ds-text-primary);
}

@keyframes ds-fade-in {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.context-page {
    width: 100%;
    height: 100%;
    overflow-y: auto;
    background: var(--ds-bg-void);
    padding: 32px;
}

/* Transitions */
.perspective-enter-active,
.perspective-leave-active {
    transition: opacity 0.2s ease;
}

.perspective-enter-from,
.perspective-leave-to {
    opacity: 0;
}

/* Shop Panel Wrapper & Backdrop */
.shop-panel-wrapper {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 380px;
    z-index: 2000;
    display: flex;
}

.panel-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    z-index: -1;
    transition: all 0.3s;
}

.panel-backdrop.dragging-active {
    pointer-events: none;
    opacity: 0.1;
}

.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.3s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
}
</style>
