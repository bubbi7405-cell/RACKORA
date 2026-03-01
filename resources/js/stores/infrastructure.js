import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useLogStore } from './logs';
import { useToastStore } from './toast';
import SoundManager from '../services/SoundManager';

/**
 * Infrastructure Store
 * Owns: Rooms, Racks, Servers, Hardware inventory/catalog
 * Actions: purchase/upgrade rooms, purchase racks, place/move/power servers,
 *          repair, clean, modernize, assemble/disassemble, maintenance
 */
export const useInfrastructureStore = defineStore('infrastructure', () => {
    // ─── State ──────────────────────────────────────────
    const isLoading = ref(false);
    const logStore = useLogStore();

    const rooms = ref({});
    const regions = ref({});
    const locationDefinitions = ref({});
    const weather = ref({});

    const hardware = ref({
        inventory: [],
        catalog: {},
    });

    const brandDeals = ref({
        options: [],
        current: null
    });

    const stats = ref({
        totalRooms: 0,
        totalRacks: 0,
        totalServers: 0,
        onlineServers: 0,
        uptime: 100,
        totalCustomers: 0,
        monthlyRecurringRevenue: 0,
    });

    // ─── Getters ────────────────────────────────────────

    const roomList = computed(() => Object.values(rooms.value));
    const roomCount = computed(() => Object.keys(rooms.value).length);

    const totalRackCapacity = computed(() => stats.value.totalRacks * 42);

    const serverUtilization = computed(() => {
        if (stats.value.totalServers === 0) return 0;
        return Math.round((stats.value.onlineServers / stats.value.totalServers) * 100);
    });

    // ─── State Application ──────────────────────────────

    /**
     * Apply infrastructure-related data from the game state payload.
     * Called by the orchestrator (game.js) during applyGameState.
     */
    function applyState(data) {
        if (!data) return;

        if (data.rooms) {
            rooms.value = data.rooms;
        }

        if (data.location_definitions) {
            locationDefinitions.value = data.location_definitions;
        }

        if (data.regions) {
            regions.value = data.regions;
        }

        if (data.hardware) {
            Object.assign(hardware.value, data.hardware);
        }

        if (data.stats) {
            Object.assign(stats.value, data.stats);
        }

        if (data.weather) {
            weather.value = data.weather;
        }
    }

    // ─── Room Actions ───────────────────────────────────

    async function purchaseRoom(roomType, options = {}) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/purchase', {
                room_type: roomType,
                ...options
            });
            if (response.success) {
                useToastStore().success(response.message || `${roomType} purchased!`);
                SoundManager.playSuccess();
                logStore.addLog('PROVISION_ROOM: ' + roomType.toUpperCase(), 'success');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Purchase failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function upgradeRoom(roomId, upgradeType, options = {}) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/upgrade', {
                room_id: roomId,
                upgrade_type: upgradeType,
                ...options
            });
            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                logStore.addLog('UPGRADE_INFRA: ' + roomId + ' -> ' + upgradeType.toUpperCase(), 'success');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Upgrade failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function customizeRoom(roomId, wallpaperId, theme = 'classic') {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/customize', {
                room_id: roomId,
                wallpaper_id: wallpaperId,
                theme: theme
            });
            if (response.success) {
                if (response.data?.room) {
                    rooms.value[roomId] = response.data.room;
                }
                useToastStore().success(response.message || 'Room style updated!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Customization failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }



    async function hostPrTour(roomId) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/pr-tour', { room_id: roomId });
            if (response.success) {
                if (response.data?.room) {
                    rooms.value[roomId] = response.data.room;
                }
                useToastStore().success(response.data.message || 'PR Tour hosted successfully!');
                SoundManager.playSuccess();
                return response.data;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'PR Tour failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function resetCircuitBreaker(roomId) {
        isLoading.value = true;
        try {
            const response = await api.post('/rooms/reset-breaker', { room_id: roomId });
            if (response.success) {
                if (response.data) {
                    rooms.value[roomId] = response.data;
                }
                const toast = useToastStore();
                toast.success(response.message || 'Circuit breaker reset! Power restored.');
                SoundManager.playSuccess();
                logStore.addLog('POWER_RESTORED: ' + roomId, 'success');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Reset failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Hardware Brand Deals ───────────────────────────

    async function loadBrandDeals() {
        try {
            const response = await api.get('/hardware/brand-deals/options');
            if (response.success) {
                brandDeals.value.options = response.data.options;
                brandDeals.value.current = response.data.current_deal;
            }
        } catch (error) {
            console.error('Failed to load brand deals', error);
        }
    }

    async function signBrandDeal(brandName) {
        isLoading.value = true;
        try {
            const response = await api.post('/hardware/brand-deals/sign', { brand_name: brandName });
            if (response.success) {
                useToastStore().success(response.message);
                await loadBrandDeals();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Signing deal failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function terminateBrandDeal() {
        isLoading.value = true;
        try {
            const response = await api.post('/hardware/brand-deals/terminate');
            if (response.success) {
                useToastStore().success(response.message);
                await loadBrandDeals();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Termination failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Rack Actions ───────────────────────────────────

    async function purchaseRack(roomId, rackType) {
        const toast = useToastStore();
        try {
            const response = await api.post('/rack/purchase', {
                room_id: roomId,
                rack_type: rackType,
            });
            if (response.success) {
                if (response.data.room) {
                    rooms.value[roomId] = response.data.room;
                }
                toast.success(`${rackType.replace('_', ' ').toUpperCase()} purchased!`);
                logStore.addLog('PROVISION_RACK: ' + roomId + ' [+1 UNIT]', 'success');
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to purchase rack');
            SoundManager.playError();
            return { success: false, error: error.message };
        }
    }

    async function cleanRack(rackId) {
        isLoading.value = true;
        try {
            const response = await api.post('/rack/clean', { rack_id: rackId });
            if (response.success) {
                if (response.data?.rack) updateRackInState(response.data.rack);
                useToastStore().success('Rack cleaned! Cooling efficiency restored.');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Cleaning failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleColocation(rackId) {
        isLoading.value = true;
        try {
            const response = await api.post('/rack/colocation/toggle', { rack_id: rackId });
            if (response.success) {
                updateRackInState(response.data.rack);
                logStore.addLog('COLO_MODE_TOGGLE: ' + rackId, 'info');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Toggle failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Server Actions ─────────────────────────────────

    async function placeServer(rackId, serverType, modelKey, targetSlot, hardwareGeneration = 2, isLeased = false) {
        const toast = useToastStore();
        try {
            const response = await api.post('/server/place', {
                rack_id: rackId,
                server_type: serverType,
                model_key: modelKey,
                target_slot: targetSlot,
                hardware_generation: hardwareGeneration,
                is_leased: isLeased,
            });
            if (response.success) {
                updateRackInState(response.data.rack);
                toast.success(`${response.data.server.modelName} installed!`);
                SoundManager.playSuccess();
                logStore.addLog('SRV_MOUNT: ' + response.data.server.modelName + ' @ SLOT-' + targetSlot, 'success');
                return { success: true, server: response.data.server };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to place server');
            SoundManager.playError();
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
                SoundManager.playError();
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to move server');
            SoundManager.playError();
            return { success: false, error: error.message };
        }
    }

    async function powerOnServer(serverId) {
        const toast = useToastStore();
        try {
            const response = await api.post('/server/power-on', { server_id: serverId });
            if (response.success) {
                updateServerInState(response.data.server);
                toast.success('Server powering on...');
                SoundManager.playPowerOn();
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to power on server');
            SoundManager.playError();
            return { success: false, error: error.message };
        }
    }

    async function powerOffServer(serverId) {
        const toast = useToastStore();
        try {
            const response = await api.post('/server/power-off', { server_id: serverId });
            if (response.success) {
                updateServerInState(response.data.server);
                toast.success('Server powered off');
                SoundManager.playPowerOff();
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error('Failed to power off server');
            SoundManager.playError();
            return { success: false, error: error.message };
        }
    }

    async function modernizeServer(serverId) {
        const toast = useToastStore();
        try {
            const response = await api.post('/server/modernize', { server_id: serverId });
            if (response.success) {
                updateServerInState(response.data.server);
                toast.success(response.message || 'Server successfully modernized!');
                return { success: true };
            } else {
                toast.error(response.error);
                return { success: false, error: response.error };
            }
        } catch (error) {
            toast.error(error.response?.data?.error || 'Failed to modernize server');
            return { success: false, error: error.message };
        }
    }

    async function repairServer(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post('/server/repair', { server_id: serverId });
            if (response.success) {
                useToastStore().success(response.message || 'Server repaired!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Repair failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function scheduleMaintenance(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/server/${serverId}/maintenance`);
            if (response.success) {
                useToastStore().success('Maintenance window started! Health is restoring.');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Maintenance failed');
            SoundManager.playError();
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * FEATURE 208: Hardware Insurance
     */
    async function insureServer(serverId, plan) {
        isLoading.value = true;
        try {
            const response = await api.post(`/hardware/servers/${serverId}/insure`, { plan });
            if (response.success) {
                useToastStore().success('Insurance policy active!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Insurance failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function cancelInsurance(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/hardware/servers/${serverId}/insure/cancel`);
            if (response.success) {
                useToastStore().success('Insurance policy cancelled.');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Cancellation failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * FEATURE 202: Darknet Operations
     */
    async function enableDarknet(serverId, type) {
        isLoading.value = true;
        try {
            const response = await api.post(`/server/${serverId}/darknet/enable`, { type });
            if (response.success) {
                useToastStore().warning('DARKNET_LINK_ESTABLISHED: Monitoring for federal activity.');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Darknet activation failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function disableDarknet(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/server/${serverId}/darknet/disable`);
            if (response.success) {
                useToastStore().success('Darknet operations ceased.');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Darknet deactivation failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Hardware / Assembly Actions ────────────────────

    async function purchaseComponent(type, key, deliveryType = 'standard', isLeased = false) {
        isLoading.value = true;
        try {
            const response = await api.post('/hardware/purchase', { type, key, delivery_type: deliveryType, is_leased: isLeased });
            if (response.success) {
                useToastStore().success(`${isLeased ? 'Lease' : 'Order'} confirmed (${deliveryType})!`);
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Purchase failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function sellComponent(id) {
        isLoading.value = true;
        try {
            const response = await api.post(`/hardware/${id}/sell`);
            if (response.success) {
                useToastStore().success(response.message);
                // The main state is usually updated via a global tick or push, 
                // but we can remove it locally for instant feedback if needed.
                hardware.value.inventory = hardware.value.inventory.filter(c => c.id !== id);
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Sale failed');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function shredComponent(id) {
        isLoading.value = true;
        try {
            const response = await api.post(`/hardware/${id}/shred`);
            if (response.success) {
                useToastStore().success(response.message);
                // Update local state
                const comp = hardware.value.inventory.find(c => c.id === id);
                if (comp) comp.needsShredding = false;
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Shredding failed');
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function assembleServer(payload) {
        isLoading.value = true;
        try {
            const response = await api.post('/hardware/assemble', payload);
            if (response.success) {
                useToastStore().success('Server assembled and installed!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Assembly failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function disassembleServer(serverId) {
        isLoading.value = true;
        try {
            const response = await api.post(`/hardware/disassemble/${serverId}`);
            if (response.success) {
                useToastStore().success('Server disassembled. Parts returned to inventory.');
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Disassembly failed');
            return { success: false, error: error.message };
        } finally {
            isLoading.value = false;
        }
    }

    async function simulateBuild(payload) {
        try {
            const response = await api.post('/hardware/simulate', payload);
            if (response.success) {
                return response.data;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Simulation failed');
            return null;
        }
    }

    async function swapComponent(serverId, componentId, slotIndex = 0) {
        isLoading.value = true;
        try {
            const response = await api.post(`/server/${serverId}/swap-component`, {
                component_id: componentId,
                slot_index: slotIndex
            });
            if (response.success) {
                if (response.data?.server) updateServerInState(response.data.server);
                useToastStore().success(response.message || 'Hardware upgraded!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Upgrade failed');
            SoundManager.playError();
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function updateRackLighting(rackId, ledColor, ledMode) {
        isLoading.value = true;
        try {
            const response = await api.post(`/rack/${rackId}/lighting`, {
                led_color: ledColor,
                led_mode: ledMode
            });
            if (response.success) {
                if (response.data) updateRackInState(response.data);
                useToastStore().success('Rack-Beleuchtung aktualisiert!');
                SoundManager.playSuccess();
                return { success: true };
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || error.message || 'Update fehlgeschlagen');
            SoundManager.playError();
            return { success: false };
        } finally {
            isLoading.value = false;
        }
    }

    async function updateRoomRackLeds(roomId, ledColor, ledMode) {
        const room = rooms.value[roomId];
        if (!room?.racks) return;
        for (const rack of room.racks) {
            await updateRackLighting(rack.id, ledColor, ledMode);
        }
    }

    // ─── Helpers ────────────────────────────────────────

    function updateRackInState(rackData) {
        if (!rackData || !rackData.id) return;
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
        if (!serverData || !serverData.id) return;
        for (const roomId in rooms.value) {
            const room = rooms.value[roomId];
            for (const rack of room.racks || []) {
                const serverIndex = rack.servers?.findIndex(s => s && s.id === serverData.id);
                if (serverIndex !== undefined && serverIndex !== -1) {
                    rack.servers[serverIndex] = serverData;
                    return;
                }
            }
        }
    }

    // ─── WebSocket Handlers ─────────────────────────────

    function handleServerStatusChanged(data) {
        if (data.server) {
            for (const roomId in rooms.value) {
                const room = rooms.value[roomId];
                if (room.racks) {
                    for (const rack of room.racks) {
                        if (rack.servers) {
                            const idx = rack.servers.findIndex(s => s.id === data.server.id);
                            if (idx !== -1) {
                                rack.servers[idx] = { ...rack.servers[idx], ...data.server };
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    function handleRackUpdated(data) {
        if (data.rack) {
            for (const roomId in rooms.value) {
                const room = rooms.value[roomId];
                if (room.racks) {
                    const idx = room.racks.findIndex(r => r.id === data.rack.id);
                    if (idx !== -1) {
                        room.racks[idx] = data.rack;
                        return;
                    }
                }
            }
        }
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        isLoading,
        rooms,
        regions,
        locationDefinitions,
        weather,
        hardware,
        stats,
        // Getters
        roomList,
        roomCount,
        totalRackCapacity,
        serverUtilization,
        // State application
        applyState,
        // Room actions
        purchaseRoom,
        upgradeRoom,
        customizeRoom,
        hostPrTour,
        resetCircuitBreaker,
        // Rack actions
        purchaseRack,
        cleanRack,
        toggleColocation,
        // Server actions
        placeServer,
        moveServer,
        powerOnServer,
        powerOffServer,
        modernizeServer,
        repairServer,
        scheduleMaintenance,
        // Hardware actions
        purchaseComponent,
        assembleServer,
        disassembleServer,
        simulateBuild,
        swapComponent,
        updateRackLighting,
        updateRoomRackLeds,
        // Helpers
        updateRackInState,
        updateServerInState,
        // WS handlers
        handleServerStatusChanged,
        handleRackUpdated,
        insureServer,
        cancelInsurance,
        enableDarknet,
        disableDarknet,

        // Brand Deals
        brandDeals,
        loadBrandDeals,
        signBrandDeal,
        terminateBrandDeal,

        // Inventory Management
        sellComponent,
        shredComponent
    };
});
