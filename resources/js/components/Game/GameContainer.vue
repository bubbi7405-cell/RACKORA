<template>
    <div class="app-layout">
        <main class="game-container">
            <!-- Top HUD Bar -->
            <TopBar @openFinance="showFinanceOverlay = true" @openSettings="showSettingsOverlay = true" />

            <!-- World News Ticker -->
            <WorldNewsTicker />

            <!-- Left Panel - Room Selection & Info -->
            <LeftPanel 
                @openResearch="showResearchOverlay = true" 
                @openUpgrades="showRoomUpgradesOverlay = true"
            />

            <!-- Main Game World (Canvas) -->
            <GameWorld />

            <!-- Right Panel - Server Shop & Details -->
            <RightPanel @openDetails="(id) => { detailServerId = id; showServerDetailOverlay = true; }" />

            <!-- Bottom HUD - Quick Actions -->
            <BottomHud 
                @openEmployees="showEmployeesOverlay = true" 
                @openStats="showStatsOverlay = true" 
                @openAutomation="showAutomationOverlay = true" 
                @openMarket="showFinanceOverlay = true"
                @openCustomers="showCustomersOverlay = true"
                @openUpgrades="showRoomUpgradesOverlay = true"
            />
        </main>

        <!-- Overlays (Outside Grid) -->
        <EventOverlay v-if="gameStore.hasCriticalEvent" />
        <ResearchOverlay v-if="showResearchOverlay" @close="showResearchOverlay = false" />
        <FinanceOverlay v-if="showFinanceOverlay" @close="showFinanceOverlay = false" />
        <SettingsOverlay v-if="showSettingsOverlay" @close="showSettingsOverlay = false" />
        <EmployeesOverlay v-if="showEmployeesOverlay" @close="showEmployeesOverlay = false" />
        <StatsOverlay v-if="showStatsOverlay" @close="showStatsOverlay = false" />
        <AutomationOverlay v-if="showAutomationOverlay" @close="showAutomationOverlay = false" />
        <DecisionOverlay v-if="hasPendingDecisions" />
        <CustomersOverlay v-if="showCustomersOverlay" @close="showCustomersOverlay = false" />
        <RoomUpgradesOverlay v-if="showRoomUpgradesOverlay" @close="showRoomUpgradesOverlay = false" />
        <ServerDetailOverlay 
            v-if="showServerDetailOverlay" 
            :serverId="detailServerId" 
            @close="showServerDetailOverlay = false" 
        />
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import TopBar from '../HUD/TopBar.vue';
import WorldNewsTicker from '../HUD/WorldNewsTicker.vue';
import LeftPanel from '../HUD/LeftPanel.vue';
import RightPanel from '../HUD/RightPanel.vue';
import BottomHud from '../HUD/BottomHud.vue';
import GameWorld from './GameWorld.vue';
import EventOverlay from '../Overlay/EventOverlay.vue';
import ResearchOverlay from '../Overlay/ResearchOverlay.vue';
import FinanceOverlay from '../Overlay/FinanceOverlay.vue';
import SettingsOverlay from '../Overlay/SettingsOverlay.vue';
import EmployeesOverlay from '../Overlay/EmployeesOverlay.vue';
import StatsOverlay from '../Overlay/StatsOverlay.vue';
import AutomationOverlay from '../Overlay/AutomationOverlay.vue';
import DecisionOverlay from '../Overlay/DecisionOverlay.vue';
import CustomersOverlay from '../Overlay/CustomersOverlay.vue';
import RoomUpgradesOverlay from '../Overlay/RoomUpgradesOverlay.vue';
import ServerDetailOverlay from '../Overlay/ServerDetailOverlay.vue';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();

const hasActiveEvent = computed(() => gameStore.activeEventCount > 0);
const hasPendingDecisions = computed(() => false); // Placeholder
const showResearchOverlay = ref(false);
const showFinanceOverlay = ref(false);
const showSettingsOverlay = ref(false);
const showEmployeesOverlay = ref(false);
const showStatsOverlay = ref(false);
const showAutomationOverlay = ref(false);
const showCustomersOverlay = ref(false);
const showRoomUpgradesOverlay = ref(false);
const showServerDetailOverlay = ref(false);
const detailServerId = ref(null);

onMounted(() => {
    gameStore.startPolling();
});

onUnmounted(() => {
    gameStore.stopPolling();
});
</script>

<style scoped>
.app-layout {
    width: 100vw;
    height: 100vh;
    position: relative;
    overflow: hidden;
    background: var(--color-bg-deep);
    pointer-events: none; /* Let clicks pass through backdrop layer */
}

.game-container {
    pointer-events: auto; /* Re-enable clicks for the actual game */
    width: 100%;
    height: 100%;
    display: grid;
    grid-template-rows: 60px 30px 1fr 70px;
    grid-template-columns: 280px 1fr 320px;
    grid-template-areas:
        "top-bar top-bar top-bar"
        "news-ticker news-ticker news-ticker"
        "left-panel game-world right-panel"
        "bottom-hud bottom-hud bottom-hud";
}
</style>
