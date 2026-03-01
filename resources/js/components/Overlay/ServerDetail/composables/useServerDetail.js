/**
 * Shared composable for ServerDetailOverlay sub-components.
 * Provides reactive server state, loading utilities and common formatting functions.
 */
import { ref, computed } from 'vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';
import { useInfrastructureStore } from '../../../../stores/infrastructure';
import { useMultiplayerStore } from '../../../../stores/multiplayer';
import { useAuthStore } from '../../../../stores/auth';
import { useNetworkStore } from '../../../../stores/network';

export function useServerDetail(serverId) {
    const gameStore = useGameStore();
    const infraStore = useInfrastructureStore();
    const multiplayerStore = useMultiplayerStore();
    const authStore = useAuthStore();
    const netStore = useNetworkStore();

    const server = ref(null);
    const metrics = ref([]);
    const logs = ref([]);
    const processing = ref(false);
    const components = ref([]);

    const economy = computed(() => gameStore.player?.economy || {});
    const inventory = computed(() => gameStore.hardware?.inventory || []);

    const loadDetails = async () => {
        try {
            const response = await api.get(`/server/${serverId}/details`);
            if (response.success) {
                server.value = response.data.server;
                metrics.value = response.data.metrics;
                logs.value = response.data.logs;
                components.value = response.data.components || [];
            }
        } catch (e) {
            console.error('Failed to load server details', e);
        }
    };

    // ─── Formatting Helpers ─────────────────────────────

    const formatRuntime = (seconds) => {
        if (!seconds) return '0h';
        const hours = Math.floor(seconds / 3600);
        const days = Math.floor(hours / 24);
        if (days > 0) return `${days}d ${hours % 24}h`;
        return `${hours}h`;
    };

    const formatTimeDetailed = (dateStr) => {
        const d = new Date(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' ' + d.toLocaleDateString([], { day: '2-digit', month: 'short' });
    };

    const getWearClass = (percentage) => {
        if (percentage > 90) return 'text-danger';
        if (percentage > 70) return 'text-warning';
        return 'text-success';
    };

    // ─── Health Derived ─────────────────────────────────

    const healthClass = computed(() => {
        if (!server.value) return '';
        if (server.value.health > 70) return 'good';
        if (server.value.health > 30) return 'warn';
        return 'danger';
    });

    const repairCost = computed(() => {
        if (!server.value) return 0;
        let cost = server.value.purchaseCost * 0.2;
        if (server.value.isDiagnosed) cost *= 0.5;
        return Math.round(cost);
    });

    const maintenanceCost = computed(() => {
        if (!server.value) return 0;
        return Math.round(server.value.purchaseCost * 0.05);
    });

    // ─── Power Toggle ───────────────────────────────────

    const powerToggle = async () => {
        if (processing.value) return;
        processing.value = true;
        try {
            if (server.value.status === 'online') {
                await gameStore.powerOffServer(server.value.id);
            } else {
                await gameStore.powerOnServer(server.value.id);
            }
            await loadDetails();
        } finally {
            processing.value = false;
        }
    };

    return {
        // Stores
        gameStore,
        infraStore,
        multiplayerStore,
        authStore,
        netStore,
        // Reactive state
        server,
        metrics,
        logs,
        processing,
        components,
        economy,
        inventory,
        // Computeds
        healthClass,
        repairCost,
        maintenanceCost,
        // Actions
        loadDetails,
        powerToggle,
        // Formatters
        formatRuntime,
        formatTimeDetailed,
        getWearClass,
    };
}
