import { ref, computed } from 'vue';
import api from '../utils/api';
import { useGameStore } from '../stores/game';

export function useServerDetail(serverId) {
    const server = ref(null);
    const metrics = ref([]);
    const logs = ref([]);
    const components = ref([]);
    const processing = ref(false);
    const gameStore = useGameStore();

    const loadDetails = async () => {
        try {
            const response = await api.get(`/server/${serverId}/details`);
            if (response.success) {
                server.value = response.data.server;
                metrics.value = response.data.metrics || [];
                logs.value = response.data.logs || [];
                components.value = response.data.components || [];
                return true;
            }
            return false;
        } catch (e) {
            console.error('Failed to load server details', e);
            return false;
        }
    };

    const formatRuntime = (seconds) => {
        if (!seconds) return '0h';
        const hours = Math.floor(seconds / 3600);
        const days = Math.floor(hours / 24);
        if (days > 0) return `${days}d ${hours % 24}h`;
        return `${hours}h`;
    };

    const formatTimeDetailed = (dateStr) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' ' + d.toLocaleDateString([], { day: '2-digit', month: 'short' });
    };

    const getWearClass = (percentage) => {
        if (percentage > 90) return 'text-danger';
        if (percentage > 70) return 'text-warning';
        return 'text-success';
    };

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

    return {
        server,
        metrics,
        logs,
        components,
        processing,
        loadDetails,
        formatRuntime,
        formatTimeDetailed,
        getWearClass,
        healthClass,
        repairCost,
        maintenanceCost
    };
}
