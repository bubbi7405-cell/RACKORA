import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';
import SoundManager from '../services/SoundManager';

export const useGameStore = defineStore('game', () => {
    // State
    const isLoading = ref(false);
    const lastUpdate = ref(null);

    // Player state
    const player = reactive({
        id: null,
        name: '',
        economy: {
            balance: 0,
            hourlyIncome: 0,
            hourlyExpenses: 0,
            netIncomePerHour: 0,
            reputation: 50,
            level: 1,
            experience: { current: 0, forNextLevel: 100, progress: 0 },
        },
    });

    // Game world state
    const rooms = ref({});
    const customers = reactive({
        total: 0,
        active: 0,
        unhappy: 0,
        churning: 0,
        list: [],
    });
    const orders = reactive({
        pending: [],
        urgentCount: 0,
    });
    const events = reactive({
        active: [],
        hasWarnings: false,
        hasCritical: false,
    });

    const worldEvents = reactive({
        active: [],
        history: [],
    });

    const research = reactive({
        projects: [],
        active: null,
    });

    const stats = reactive({
        totalRooms: 0,
        totalRacks: 0,
        totalServers: 0,
        onlineServers: 0,
        uptime: 100,
        totalCustomers: 0,
        monthlyRecurringRevenue: 0,
    });

    // UI State
    const selectedRoomId = ref(null);
    const selectedRackId = ref(null);
    const selectedServerId = ref(null);
    const selectedOrder = ref(null);
    const isDragging = ref(false);
    const draggedServer = ref(null);

    // Employee State
    const employees = ref([]);
    const availableEmployeeTypes = ref({});


    // Getters
    const selectedRoom = computed(() => {
        return selectedRoomId.value ? rooms.value[selectedRoomId.value] : null;
    });

    const selectedRack = computed(() => {
        if (!selectedRoom.value || !selectedRackId.value) return null;
        return selectedRoom.value.racks?.find(r => r.id === selectedRackId.value);
    });

    const activeEventCount = computed(() => events.active.length);
    const hasCriticalEvent = computed(() => events.hasCritical);

    // Actions
    async function loadGameState(background = false) {
        if (!background) isLoading.value = true;
        const toast = useToastStore();

        try {
            const response = await api.get('/game/state');

            if (response.success) {
                applyGameState(response.data);
                lastUpdate.value = response.data.timestamp;
            } else {
                // Player might not be initialized
                if (response.error?.includes('not initialized')) {
                    await initializePlayer();
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

    async function upgradeRoom(roomId, upgradeType) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/upgrade', {
                room_id: roomId,
                upgrade_type: upgradeType
            });
            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Upgrade failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function initializePlayer(name) {
        const toast = useToastStore();

        try {
            const response = await api.post('/game/initialize');

            if (response.success) {
                applyGameState(response.data);
                toast.success('Welcome to Server Tycoon! Your empire begins now.');
            }
        } catch (error) {
            console.error('Failed to initialize player:', error);
            toast.error('Failed to start new game');
        }
    }

    function applyGameState(data) {
        // Player
        if (data.player) {
            player.id = data.player.id;
            player.name = data.player.name;
            Object.assign(player.economy, data.player.economy);
        }

        // Rooms
        if (data.rooms) {
            rooms.value = data.rooms;

            // Auto-select first room if none selected
            if (!selectedRoomId.value) {
                const roomIds = Object.keys(data.rooms);
                if (roomIds.length > 0) {
                    selectedRoomId.value = roomIds[0];
                }
            }
        }

        // Customers
        if (data.customers) {
            Object.assign(customers, data.customers);
        }

        // Orders
        if (data.orders) {
            Object.assign(orders, data.orders);
        }

        // Events
        if (data.events) {
            Object.assign(events, data.events);
        }

        // World Events
        if (data.world_events) {
            Object.assign(worldEvents, data.world_events);
        }

        // Stats
        if (data.stats) {
            Object.assign(stats, data.stats);
        }

        lastUpdate.value = data.timestamp;

        // Update selectedOrder reference
        if (selectedOrder.value && data.orders?.pending) {
            const updatedOrder = data.orders.pending.find(o => o.id === selectedOrder.value.id);
            if (updatedOrder) {
                selectedOrder.value = updatedOrder;
            } else {
                // Order is no longer pending
                selectedOrder.value = null; // Auto-close
                useToastStore().info('Order processed or expired.');
            }
        }
    }

    // Rack Management
    async function purchaseRack(roomId, rackType) {
        const toast = useToastStore();

        try {
            const response = await api.post('/rack/purchase', {
                room_id: roomId,
                rack_type: rackType,
            });

            if (response.success) {
                // Update room in state
                if (response.data.room) {
                    rooms.value[roomId] = response.data.room;
                }
                toast.success(`${rackType.replace('_', ' ').toUpperCase()} purchased!`);
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to purchase rack');
            return { success: false, error: error.message };
        }
    }

    async function placeServer(rackId, serverType, modelKey, targetSlot) {
        const toast = useToastStore();

        try {
            const response = await api.post('/server/place', {
                rack_id: rackId,
                server_type: serverType,
                model_key: modelKey,
                target_slot: targetSlot,
            });

            if (response.success) {
                // Update rack in state
                updateRackInState(response.data.rack);

                // Update player economy
                await loadGameState(); // Full refresh to get updated balance

                toast.success(`${response.data.server.modelName} installed!`);
                return { success: true, server: response.data.server };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to place server');
            return { success: false, error: error.message };
        }
    }

    async function moveServer(serverId, targetRackId, targetSlot) {
        const toast = useToastStore();

        try {
            const response = await api.post('/server/move', {
                server_id: serverId,
                target_rack_id: targetRackId,
                target_slot: targetSlot,
            });

            if (response.success) {
                updateRackInState(response.data.sourceRack);
                updateRackInState(response.data.targetRack);
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to move server');
            return { success: false, error: error.message };
        }
    }

    async function powerOnServer(serverId) {
        const toast = useToastStore();

        try {
            const response = await api.post('/server/power-on', {
                server_id: serverId,
            });

            if (response.success) {
                updateServerInState(response.data.server);
                toast.success('Server powering on...');
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to power on server');
            return { success: false, error: error.message };
        }
    }

    async function powerOffServer(serverId) {
        const toast = useToastStore();

        try {
            const response = await api.post('/server/power-off', {
                server_id: serverId,
            });

            if (response.success) {
                updateServerInState(response.data.server);
                toast.success('Server powered off');
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to power off server');
            return { success: false, error: error.message };
        }
    }

    // Event Management
    async function resolveEvent(eventId, actionId) {
        const toast = useToastStore();

        try {
            const response = await api.post('/events/resolve', {
                event_id: eventId,
                action_id: actionId,
            });

            if (response.success) {
                // Remove event from active list
                const index = events.active.findIndex(e => e.id === eventId);
                if (index !== -1) {
                    events.active.splice(index, 1);
                }

                if (response.data.resolution === 'success') {
                    toast.success('Crisis resolved! XP earned: ' + response.data.xpEarned);
                } else {
                    toast.warning('Action failed! The situation has escalated.');
                }

                // Refresh game state for updated economy
                await loadGameState();

                return { success: true, resolution: response.data.resolution };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to resolve event');
            return { success: false, error: error.message };
        }
    }

    // Helper functions
    function updateRackInState(rackData) {
        // Find the room containing this rack
        for (const roomId in rooms.value) {
            const room = rooms.value[roomId];
            const rackIndex = room.racks?.findIndex(r => r.id === rackData.id);

            if (rackIndex !== undefined && rackIndex !== -1) {
                room.racks[rackIndex] = rackData;
                break;
            }
        }
    }

    function updateServerInState(serverData) {
        // Find the rack containing this server
        for (const roomId in rooms.value) {
            const room = rooms.value[roomId];
            for (const rack of room.racks || []) {
                const serverIndex = rack.servers?.findIndex(s => s.id === serverData.id);

                if (serverIndex !== undefined && serverIndex !== -1) {
                    rack.servers[serverIndex] = serverData;
                    return;
                }
            }
        }
    }





    // Research Actions
    async function loadResearch() {
        try {
            const response = await api.get('/research/projects'); // Need route
            if (response.success) {
                // Determine active research from response
                // Response is list of projects with status/progress
                research.projects = response.data;
                const activeWrapper = response.data.find(p => p.status === 'active');
                if (activeWrapper) {
                    research.active = activeWrapper;
                } else {
                    research.active = null;
                }
            }
        } catch (error) {
            console.error('Failed to load research', error);
        }
    }

    async function startResearch(key) {
        isLoading.value = true;
        try {
            const response = await api.post('/research/start', { research_key: key });
            if (response.success) {
                useToastStore().success('Research started');
                await loadResearch();
                await loadGameState(); // Update balance
            }
        } catch (error) {
            useToastStore().error(error.message || 'Failed to start research');
        } finally {
            isLoading.value = false;
        }
    }





    async function purchaseRoom(roomType) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/purchase', { room_type: roomType });
            if (response.success) {
                useToastStore().success(response.message || `${roomType} purchased!`);
                SoundManager.playSuccess();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Purchase failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function loadTransactions(page = 1, filters = {}) {
        try {
            const params = new URLSearchParams({ page });
            if (filters.type) params.append('type', filters.type);
            if (filters.category) params.append('category', filters.category);
            if (filters.hours) params.append('hours', filters.hours);

            const response = await api.get(`/economy/transactions?${params}`);
            if (response.success) {
                return response.data;
            }
        } catch (error) {
            console.error('Failed to load transactions', error);
        }
        return null;
    }

    async function cancelOrder(orderId) {
        const toast = useToastStore();
        isLoading.value = true;
        try {
            const response = await api.post(`/orders/${orderId}/cancel`);
            if (response.success) {
                toast.warning(response.message || 'Order cancelled. Reputation -5.');
                SoundManager.playError();
                await loadGameState();
                return true;
            }
        } catch (error) {
            toast.error(error.response?.data?.error || 'Failed to cancel order');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function loadEmployees() {
        try {
            const response = await api.get('/employees');
            if (response.success) {
                employees.value = response.employees;
                availableEmployeeTypes.value = response.available_types;
            }
        } catch (error) {
            console.error('Failed to load employees', error);
        }
    }

    async function hireEmployee(type) {
        isLoading.value = true;
        try {
            const response = await api.post('/employees/hire', { type });
            if (response.success) {
                useToastStore().success(response.message || 'Employee hired!');
                SoundManager.playSuccess();
                await loadEmployees();
                await loadGameState(); // Update balance
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Hire failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function fireEmployee(id) {
        if (!confirm('Are you sure you want to fire this employee?')) return;
        isLoading.value = true;
        try {
            const response = await api.post(`/employees/${id}/fire`);
            if (response.success) {
                useToastStore().success(response.message || 'Employee fired.');
                await loadEmployees();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Fire failed');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function repairServer(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post('/server/repair', { server_id: serverId });
            if (response.success) {
                useToastStore().success(response.message || 'Server repaired!');
                SoundManager.playSuccess();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Repair failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function cleanRack(rackId) {
        isLoading.value = true;
        try {
            const response = await api.post('/rack/clean', { rack_id: rackId });
            if (response.success) {
                useToastStore().success('Rack cleaned! Cooling efficiency restored.');
                SoundManager.playSuccess();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Cleaning failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    // Selection helpers
    function selectRoom(roomId) {
        selectedRoomId.value = roomId;
        selectedRackId.value = null;
        selectedServerId.value = null;
    }

    function selectRack(rackId) {
        selectedRackId.value = rackId;
        selectedServerId.value = null;
    }

    async function scheduleMaintenance(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/server/${serverId}/maintenance`);
            if (response.success) {
                useToastStore().success('Maintenance window started! Health is restoring.');
                SoundManager.playSuccess();
                await loadGameState();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Maintenance failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    function selectServer(serverId) {
        selectedServerId.value = serverId;
    }

    function selectOrder(order) {
        selectedOrder.value = order;
    }

    // Drag and drop
    function startDrag(server) {
        isDragging.value = true;
        draggedServer.value = server;
    }

    function endDrag() {
        isDragging.value = false;
        draggedServer.value = null;
    }

    // Polling logic
    let pollingInterval = null;

    function startPolling(interval = 30000) {
        if (pollingInterval) return;
        pollingInterval = setInterval(() => {
            loadGameState(true);
        }, interval);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    return {
        // State
        isLoading,
        lastUpdate,
        player,
        rooms,
        customers,
        orders,
        events,
        worldEvents,
        research,
        stats,
        selectedRoomId,
        selectedRackId,
        selectedServerId,
        selectedOrder,
        isDragging,
        draggedServer,
        // Getters
        selectedRoom,
        selectedRack,
        activeEventCount,
        hasCriticalEvent,
        // Actions
        loadGameState,
        startPolling,
        stopPolling,
        initializePlayer,
        upgradeRoom,
        purchaseRack,
        placeServer,
        moveServer,
        powerOnServer,
        powerOffServer,
        scheduleMaintenance,
        resolveEvent,
        loadResearch,
        startResearch,
        purchaseRoom,
        repairServer,
        cleanRack,
        selectRoom,
        selectRack,
        selectServer,
        selectOrder,
        startDrag,
        endDrag,
        loadTransactions,
        cancelOrder,
        powerOnServer,
        powerOffServer,
        employees,
        availableEmployeeTypes,
        loadEmployees,
        hireEmployee,
        fireEmployee,
    };
});
