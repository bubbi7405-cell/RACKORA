import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';
import { useNetworkStore } from './network';
import { useInfrastructureStore } from './infrastructure';
import { useEconomyStore } from './economy';
import { useEventsStore } from './events';
import { useEmployeesStore } from './employees';
import { useResearchStore } from './research';
import { useUiStore } from './ui';
import { useNewsStore } from './news';
import { useLogStore } from './logs';
import { useAutomationStore } from './automation';
import { initEcho, subscribeToGameChannel, subscribeToWorldEvents, disconnectEcho } from '../services/echo';
import SoundManager from '../services/SoundManager';

/**
 * Game Orchestrator Store
 * 
 * Responsibilities:
 * 1. Initialize and coordinate sub-stores
 * 2. Fetch global game state and distribute to sub-stores
 * 3. Manage WebSocket connection and dispatch events to sub-stores
 * 4. Maintain backward compatibility for components using useGameStore()
 */
export const useGameStore = defineStore('game', () => {
    // ─── Sub-Stores ─────────────────────────────────────
    const networkStore = useNetworkStore();
    const infraStore = useInfrastructureStore();
    const economyStore = useEconomyStore();
    const eventStore = useEventsStore();
    const employeeStore = useEmployeesStore();
    const researchStore = useResearchStore();
    const uiStore = useUiStore();
    const newsStore = useNewsStore();
    const logStore = useLogStore();
    const automationStore = useAutomationStore();
    const toast = useToastStore();

    // ─── Global State ───────────────────────────────────
    const isLoading = ref(false);
    const lastUpdate = ref(null);

    // WebSocket State
    let pollingInterval = null;
    let wsChannel = null;
    const wsConnected = ref(false);
    const isPolling = ref(false);

    // ─── Unified State Loading ──────────────────────────

    async function loadGameState(background = false) {
        if (!background) isLoading.value = true;

        try {
            const response = await api.get('/game/state');

            if (response.success) {
                console.log('[game.js] State loaded successfully', {
                    roomCount: Object.keys(response.data.rooms || {}).length,
                    hasPlayer: !!response.data.player
                });
                applyGameState(response.data);
            } else {
                if (response.error?.includes('not initialized')) {
                    await economyStore.initializePlayer();
                } else {
                    toast.error('Failed to load game state');
                }
            }
        } catch (error) {
            console.error('Failed to load game state:', error);
            if (!background) toast.error('Connection error. Please try again.');
        } finally {
            if (!background) isLoading.value = false;
        }
    }

    function applyGameState(data) {
        if (!data) return;

        lastUpdate.value = data.timestamp;

        // Dispatch data to domain stores
        economyStore.applyState(data);
        infraStore.applyState(data);
        eventStore.applyState(data);
        researchStore.applyState(data);
        automationStore.applyState(data);

        // Network has its own dedicated handler in its store
        if (data.network) {
            networkStore.applyNetworkState(data.network);
        }

        // UI Updates based on new state
        if (data.rooms) {
            uiStore.autoSelectFirstRoom(Object.keys(data.rooms));
        }
        if (data.orders?.pending) {
            uiStore.syncSelectedOrder(data.orders.pending);
        }
    }

    // ─── WebSocket / Real-Time ──────────────────────────

    // ─── WebSocket / Real-Time ──────────────────────────

    // Auto-connect when player ID becomes available
    watch(() => economyStore.player.id, (newId) => {
        if (newId && !wsConnected.value && !wsChannel) {
            connectWebSocket();
        }
    });

    function connectWebSocket() {
        if (!economyStore.player.id) {
            // Wait for watcher to trigger
            return;
        }

        if (wsChannel) return;

        try {
            const echo = initEcho();
            wsChannel = subscribeToGameChannel(economyStore.player.id);
            const connector = echo.connector.pusher.connection;

            // Connection Lifecycle
            connector.bind('state_change', (states) => {
                console.log(`🔌 [Rackora WS] State: ${states.current}`);
                wsConnected.value = (states.current === 'connected');
                if (states.current === 'disconnected' || states.current === 'failed') {
                    startPolling(15000);
                }
            });

            connector.bind('connected', () => {
                wsConnected.value = true;
                console.log('🔌 [Rackora WS] Uplink Established.');
            });

            connector.bind('unavailable', () => {
                wsConnected.value = false;
                startPolling(10000);
            });

            // Event Dispatching
            wsChannel.listen('.economy.updated', (data) => {
                lastUpdate.value = data.timestamp;
                wsConnected.value = true;
                logStore.addLog('ACK: DATA_STREAM_TICK [' + data.timestamp + ']', 'info');

                // Update Economy
                economyStore.handleEconomyTick(data);

                // Update Network
                if (data.network) {
                    networkStore.applyNetworkState(data.network);
                }

                // Update Private Networks (VPCs)
                if (data.privateNetworks) {
                    networkStore.privateNetworks = data.privateNetworks;
                }

                eventStore.handleEconomyTickEvents(data);
            });

            wsChannel.listen('.event.started', (event) => {
                logStore.addLog('CRITICAL_ALERT: ' + event.type + ' DETECTED', 'danger');
                eventStore.handleEventStarted(event);
            });
            wsChannel.listen('.event.escalated', (event) => {
                logStore.addLog('ESCALATION: ' + event.type + ' SPREADING', 'warning');
                eventStore.handleEventEscalated(event);
            });
            wsChannel.listen('.event.resolved', (event) => {
                logStore.addLog('RESOLVED: ' + event.type + ' SECURED', 'success');
                eventStore.handleEventResolved(event);
            });
            wsChannel.listen('.event.failed', (event) => {
                logStore.addLog('FAILURE: ' + event.type + ' BREACHED', 'danger');
                eventStore.handleEventFailed(event);
            });

            wsChannel.listen('.server.status_changed', (data) => {
                logStore.addLog('STATUS_CHANGE: SRV-' + data.serverId + ' -> ' + data.status, 'info');
                infraStore.handleServerStatusChanged(data);
            });
            wsChannel.listen('.rack.updated', (data) => {
                logStore.addLog('RACK_SYNC: UNIT-' + data.rackId, 'info');
                infraStore.handleRackUpdated(data);
            });

            wsChannel.listen('.competitor.attack_started', (data) => {
                SoundManager.playAlert();
                uiStore.triggerAttackOverlay(data);
            });

            wsChannel.listen('.achievement.unlocked', (data) => {
                if (data.achievement) {
                    SoundManager.playSuccess();
                    toast.success(`🏆 Achievement: ${data.achievement.name}`);
                }
            });

            wsChannel.listen('.rental.event', (data) => {
                if (data.type === 'success') toast.success(data.message, data.title);
                else if (data.type === 'warning') toast.warning(data.message, data.title);
                else toast.info(data.message, data.title);
            });

            wsChannel.listen('.news.broadcast', (data) => {
                const item = data.newsItem;
                newsStore.addNews(item);

                if (item?.type === 'breaking') {
                    SoundManager.playBreakingNews();
                } else {
                    SoundManager.playNotification();
                }
            });

            const worldChannel = subscribeToWorldEvents();
            worldChannel.listen('.WorldEventTriggered', (data) => {
                eventStore.handleWorldEvent(data);
            });

            wsConnected.value = true;

        } catch (err) {
            console.warn('🔌 [Rackora] WS failed, polling fallback:', err);
            startPolling(30000);
        }
    }

    function disconnectWebSocket() {
        if (wsChannel) wsChannel = null;
        wsConnected.value = false;
        disconnectEcho();
    }

    function startPolling(interval = 45000, force = false) {
        if (pollingInterval && !force) return;
        if (force && pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        isPolling.value = true;
        pollingInterval = setInterval(() => loadGameState(true), interval);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        disconnectWebSocket();
    }

    // ─── Backward Compatibility Getters ─────────────────

    // We compute these derived values on demand to support legacy access patterns
    const selectedRoom = computed(() => {
        const id = uiStore.selectedRoomId;
        if (!id) return null;
        return infraStore.rooms[id] || null;
    });

    const selectedRack = computed(() => {
        const roomId = uiStore.selectedRoomId;
        const rackId = uiStore.selectedRackId;
        if (!roomId || !rackId || !infraStore.rooms[roomId]) return null;
        return infraStore.rooms[roomId].racks?.find(r => r.id === rackId);
    });

    return {
        // Core State
        isLoading,
        lastUpdate,
        wsConnected,
        isPolling,
        loadGameState,
        applyGameState,
        connectWebSocket,
        disconnectWebSocket,
        startPolling,
        stopPolling,

        // Economy Store Re-exports
        player: computed(() => economyStore.player),
        gameSpeed: computed(() => economyStore.gameSpeed),
        isPaused: computed(() => economyStore.isPaused),
        customers: computed(() => economyStore.customers),
        orders: computed(() => economyStore.orders),
        marketShare: computed(() => economyStore.marketShare),
        energyMarket: computed(() => economyStore.energyMarket),
        initializePlayer: economyStore.initializePlayer,
        setGameSpeed: economyStore.setGameSpeed,
        loadTransactions: economyStore.loadTransactions,
        cancelOrder: economyStore.cancelOrder,
        submitBid: economyStore.submitBid,
        getBidPreview: economyStore.getBidPreview,
        updateTutorialProgress: economyStore.updateTutorialProgress,
        loadEnergyData: economyStore.loadEnergyData,
        signEnergyContract: economyStore.signEnergyContract,
        toggleEnergyPolicy: economyStore.toggleEnergyPolicy,

        // Infrastructure Store Re-exports
        rooms: computed(() => infraStore.rooms),
        regions: computed(() => infraStore.regions),
        locationDefinitions: computed(() => infraStore.locationDefinitions),
        weather: computed(() => infraStore.weather),
        hardware: computed(() => infraStore.hardware),
        stats: computed(() => infraStore.stats),
        upgradeRoom: infraStore.upgradeRoom,
        customizeRoom: infraStore.customizeRoom,
        hostPrTour: infraStore.hostPrTour,
        resetCircuitBreaker: infraStore.resetCircuitBreaker,
        purchaseRack: infraStore.purchaseRack,
        cleanRack: infraStore.cleanRack,
        placeServer: infraStore.placeServer,
        moveServer: infraStore.moveServer,
        powerOnServer: infraStore.powerOnServer,
        powerOffServer: infraStore.powerOffServer,
        modernizeServer: infraStore.modernizeServer,
        repairServer: infraStore.repairServer,
        scheduleMaintenance: infraStore.scheduleMaintenance,
        purchaseComponent: infraStore.purchaseComponent,
        assembleServer: infraStore.assembleServer,
        disassembleServer: infraStore.disassembleServer,
        simulateBuild: infraStore.simulateBuild,
        toggleColocation: infraStore.toggleColocation,
        updateRoomRackLeds: infraStore.updateRoomRackLeds,

        // Events Store Re-exports
        events: computed(() => eventStore.events),
        worldEvents: computed(() => eventStore.worldEvents),
        activeCrisis: computed(() => eventStore.activeCrisis),
        activeEventCount: computed(() => eventStore.activeEventCount),
        hasCriticalEvent: computed(() => eventStore.hasCriticalEvent),
        resolveEvent: eventStore.resolveEvent,
        takeCrisisAction: eventStore.takeCrisisAction,
        submitFiberMinigame: eventStore.submitFiberMinigame,
        submitStrikeNegotiation: eventStore.submitStrikeNegotiation,
        closeResolvedSummary: eventStore.closeResolvedSummary,

        // Employees Store Re-exports
        employees: computed(() => employeeStore.employees),
        loadEmployees: employeeStore.loadEmployees,
        hireEmployee: employeeStore.hireEmployee,
        fireEmployee: employeeStore.fireEmployee,

        // Research Store Re-exports
        research: computed(() => researchStore.research),
        loadResearch: researchStore.loadResearch,
        startResearch: researchStore.startResearch,
        isResearched: (techId) => researchStore.isResearched(techId),

        // UI Store Re-exports
        selectedRoomId: computed(() => uiStore.selectedRoomId),
        selectedRackId: computed(() => uiStore.selectedRackId),
        selectedServerId: computed(() => uiStore.selectedServerId),
        selectedOrder: computed(() => uiStore.selectedOrder),
        isDragging: computed(() => uiStore.isDragging),
        draggedServer: computed(() => uiStore.draggedServer),
        uiStore, // Expose store for newer components

        // Computed Re-exports
        selectedRoom,
        selectedRack,

        // UI Actions
        selectRoom: uiStore.selectRoom,
        selectRack: uiStore.selectRack,
        selectServer: uiStore.selectServer,
        selectOrder: uiStore.selectOrder,
        startDrag: uiStore.startDrag,
        endDrag: uiStore.endDrag,

        // Network Store Re-exports
        // We re-export the entire reactive objects to match original game.js structure
        network: computed(() => ({
            ips: networkStore.ips,
            bandwidth: networkStore.bandwidth,
            metrics: networkStore.metrics,
            traffic: networkStore.traffic,
            infrastructure: networkStore.infrastructure,
            isp: networkStore.isp,
            regional: networkStore.regional
        })),
    };
});
