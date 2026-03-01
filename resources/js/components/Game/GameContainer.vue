<template>
    <div class="v2-layout" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
        <Sidebar 
            class="v2-sidebar"
            :class="{ 'is-collapsed': isSidebarCollapsed }"
            v-model:isCollapsed="isSidebarCollapsed" 
            :activeView="activeView"
            @update:activeView="handleViewChange"
        />
        
        <div class="v2-content-wrapper">
            <TopBar 
                class="v2-topbar"
                @openProfile="showProfileOverlay = true"
                @openMarketing="activeView = 'marketing'"
                @openAnalytics="activeView = 'analytics'"
                @openLeaderboard="activeView = 'world'"
                @openRoadmap="showRoadmapOverlay = true"
                @openAchievements="activeView = 'world'"
                @openFinance="showFinanceOverlay = true"
                @openReplay="showReplayOverlay = true"
                @openCustomers="activeView = 'management'; managementTab = 'customers'"
                @openEmployees="activeView = 'management'; managementTab = 'personnel'"
            />
            
            <GlobalEventTicker class="global-event-floating" />
            <main class="v2-main-viewport">
                <!-- Contextual Views -->
                <div v-if="activeView === 'overview'" class="overview-layout">
                    <LeftPanel 
                        @openResearch="activeView = 'research'"
                        @openSpecialization="activeView = 'management'; managementTab = 'strategy'"
                        @openSandbox="showAssemblyOverlay = true"
                        @openMarket="activeView = 'market'"
                        @openMarketing="activeView = 'marketing'"
                        @openAnalytics="activeView = 'analytics'"
                        @openCustomers="activeView = 'management'; managementTab = 'customers'"
                    />
                    <GameWorld @open-server-details="openServerDetails" />
                </div>

                <div v-else-if="activeView === 'locations'" class="context-page">
                    <LocationsView 
                        @room-switched="activeView = 'overview'" 
                        @open-region-select="(type) => { pendingRoomPurchase = type; showRegionSelect = true; }"
                    />
                </div>
                
                <div v-else-if="activeView === 'infrastructure'" class="context-page">
                    <InfrastructureView 
                        @openShop="showShop = true" 
                        @openMarket="showEnergyOverlay = true"
                    />
                </div>
                
                <div v-else-if="activeView === 'management'" class="context-page">
                    <ManagementView :initial-tab="managementTab" />
                </div>

                <div v-else-if="activeView === 'network'" class="context-page">
                    <NetworkView />
                </div>

                <div v-else-if="activeView === 'market'" class="context-page">
                    <MarketDashboard />
                </div>

                <div v-else-if="activeView === 'marketing'" class="context-page">
                    <MarketingDashboard />
                </div>

                <div v-else-if="activeView === 'analytics'" class="context-page">
                    <AnalyticsDashboard />
                </div>

                <div v-else-if="activeView === 'research'" class="context-page">
                    <ResearchView />
                </div>

                <div v-else-if="activeView === 'world'" class="context-page">
                    <WorldView />
                </div>

                    <!-- Settings removed, using ProfileOverlay -->
            </main>
        </div>

        <!-- Infrastructure Shop (Slide Panel) -->
        <transition name="slide-right">
            <div v-if="showShop" class="shop-panel-wrapper">
            <div 
                class="panel-backdrop" 
                :class="{ 'dragging-active': gameStore.isDragging }" 
                @click="showShop = false"
            ></div>
                <RightPanel 
                    slide-out 
                    @close="showShop = false"
                    @openDetails="openServerDetails"
                    @openAssembly="showAssemblyOverlay = true"
                />
            </div>
        </transition>

        <!-- Incidents Popup (Clean Notification) - MOVED TO SIDEBAR -->

        <!-- Overlays (Legacy/Modal) -->
        <FinanceOverlay v-if="showFinanceOverlay" @close="showFinanceOverlay = false" />
        <ServerDetailOverlay 
            v-if="showServerDetailOverlay" 
            :serverId="detailServerId" 
            @close="showServerDetailOverlay = false" 
        />
        <RegionSelectOverlay 
            v-if="showRegionSelect" 
            :roomType="pendingRoomPurchase" 
            :roomCost="getRoomCost(pendingRoomPurchase)"
            @close="showRegionSelect = false" 
        />
        <AssemblyOverlay v-if="showAssemblyOverlay" @close="showAssemblyOverlay = false" />
        <TutorialOverlay v-if="player && !player.tutorial_completed" />
        
        <LoginSummaryOverlay 
            v-if="showLoginSummary && loginSummary" 
            :summary="loginSummary" 
            @close="showLoginSummary = false" 
        />

        <RoadmapOverlay v-if="showRoadmapOverlay" @close="showRoadmapOverlay = false" />
        <ProfileOverlay v-if="showProfileOverlay" @close="showProfileOverlay = false" />
        <ReplayOverlay v-if="showReplayOverlay" @close="showReplayOverlay = false" />
        <EnergyMarketOverlay v-if="showEnergyOverlay" @close="showEnergyOverlay = false" />

        <Tooltip 
            :visible="tooltipStore.visible" 
            :title="tooltipStore.title" 
            :content="tooltipStore.content" 
            :hint="tooltipStore.hint" 
            :x="tooltipStore.x" 
            :y="tooltipStore.y" 
        />

        <OrderOverlay 
            v-if="uiStore.selectedOrder" 
            :order="uiStore.selectedOrder" 
            @close="uiStore.selectOrder(null)" 
            @negotiate="openNegotiation"
        />
        <ContractNegotiationOverlay 
            v-if="negotiatingOrder" 
            :order="negotiatingOrder" 
            @close="closeNegotiation" 
            @negotiated="handleNegotiationComplete" 
        />
        <AttackOverlay />
        <GlobalCrisisOverlay />
        <VulnerabilityHUD class="vulnerability-hud-floating" />
        <AIAdvisor />
        <LogTicker />
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useUiStore } from '../../stores/ui';
import { useTooltipStore } from '../../stores/tooltip';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

// Components
import Sidebar from '../HUD/Sidebar.vue';
import TopBar from '../HUD/TopBar.vue';
import GlobalEventTicker from '../HUD/GlobalEventTicker.vue';
import LogTicker from '../HUD/LogTicker.vue';
import GameWorld from './GameWorld.vue';
import RightPanel from '../HUD/RightPanel.vue';
import LeftPanel from '../HUD/LeftPanel.vue';

// Views (Sub-layouts)
// Note: These might be modified Overlays or new components
import InfrastructureView from './InfrastructureView.vue';
import ManagementView from './ManagementView.vue';
import WorldView from './WorldView.vue';
import NetworkView from './NetworkView.vue';
import LocationsView from './LocationsView.vue';
import MarketDashboard from '../Market/MarketDashboard.vue';
import MarketingDashboard from '../Market/MarketingDashboard.vue';
import AnalyticsDashboard from '../Market/AnalyticsDashboard.vue';
import ResearchView from './ResearchView.vue';

// Overlays
import FinanceOverlay from '../Overlay/FinanceOverlay.vue';
import ServerDetailOverlay from '../Overlay/ServerDetailOverlay.vue';
import RegionSelectOverlay from '../Overlay/RegionSelectOverlay.vue';
import OrderOverlay from '../Overlay/OrderOverlay.vue';
import AssemblyOverlay from '../Overlay/AssemblyOverlay.vue';
import ContractNegotiationOverlay from '../Overlay/ContractNegotiationOverlay.vue';
import TutorialOverlay from '../Overlay/TutorialOverlay.vue';
import LoginSummaryOverlay from '../Overlay/LoginSummaryOverlay.vue';
import AttackOverlay from '../Overlay/AttackOverlay.vue';
import GlobalCrisisOverlay from '../Overlay/GlobalCrisisOverlay.vue';
import Tooltip from '../UI/Tooltip.vue';
import AIAdvisor from '../HUD/AIAdvisor.vue';
import VulnerabilityHUD from '../HUD/VulnerabilityHUD.vue'; // F118

// Legacy logic imports that stay but are used differently
// immport ResearchOverlay from '../Overlay/ResearchOverlay.vue'; // Legacy removed
import ProfileOverlay from '../Overlay/ProfileOverlay.vue';
import RoadmapOverlay from '../Overlay/RoadmapOverlay.vue';
import ReplayOverlay from '../Overlay/ReplayOverlay.vue';
import EnergyMarketOverlay from '../Overlay/EnergyMarketOverlay.vue';

const gameStore = useGameStore();
const uiStore = useUiStore();
const tooltipStore = useTooltipStore();

const activeView = ref('overview');
const isSidebarCollapsed = ref(false);
const showShop = ref(false);

const showFinanceOverlay = ref(false);
const showServerDetailOverlay = ref(false);
const showRegionSelect = ref(false);
const showAssemblyOverlay = ref(false);
const showLoginSummary = ref(false);
const showRoadmapOverlay = ref(false);
const showProfileOverlay = ref(false);
const showReplayOverlay = ref(false);
const showEnergyOverlay = ref(false);

const detailServerId = ref(null);
const loginSummary = ref(null);
const pendingRoomPurchase = ref('garage');
const managementTab = ref('dashboard');
const negotiatingOrder = ref(null);

const player = computed(() => gameStore.player);

const openServerDetails = (id) => {
    detailServerId.value = id;
    showServerDetailOverlay.value = true;
};

const getRoomCost = (type) => {
    return gameStore.locationDefinitions?.[type]?.unlock_cost || 0;
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
        if (showShop.value) { showShop.value = false; return; }
        showFinanceOverlay.value = false;
        showServerDetailOverlay.value = false;
        showRegionSelect.value = false;
        showAssemblyOverlay.value = false;
        return;
    }
};

const handleViewChange = (view) => {
    if (view === 'settings') {
        showProfileOverlay.value = true;
    } else {
        activeView.value = view;
    }
};

const openNegotiation = (order) => {
    uiStore.selectOrder(null); // Close order overlay
    negotiatingOrder.value = order;
};

const closeNegotiation = () => {
    negotiatingOrder.value = null;
};

const handleNegotiationComplete = (newOrderData) => {
    // Optionally alert player or auto-accept/re-open order overlay.
    // For now we just refresh game state when negotiation modifies the order.
    gameStore.loadGameState();
    closeNegotiation();
    // Maybe re-open?
    gameStore.selectOrder(newOrderData);
};

onMounted(async () => {
    gameStore.connectWebSocket();
    // SoundManager.startAmbience(); // Removed auto-start to comply with browser policy
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
    background: #05070a;
    color: #e6edf3;
    overflow: hidden;
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
}

.overview-layout {
    display: flex;
    height: 100%;
    width: 100%;
}

.context-page {
    width: 100%;
    height: 100%;
    overflow-y: auto;
    background: #0a0d14;
}

.shop-panel-wrapper {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 2000;
    display: flex;
}

.panel-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    transition: all 0.3s ease;
}

.panel-backdrop.dragging-active {
    pointer-events: none;
    opacity: 0.1;
    backdrop-filter: blur(1px);
}

/* Animations */
.slide-right-enter-active, .slide-right-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-right-enter-from, .slide-right-leave-to {
    transform: translateX(100%);
}

.global-event-floating {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 900;
}

.vulnerability-hud-floating {
    position: fixed;
    top: 100px;
    right: 24px; /* Floating on HUD overlay */
    z-index: 1000;
}
</style>
