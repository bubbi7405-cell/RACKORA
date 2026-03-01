import { ref, computed, watch, onMounted, onUnmounted, reactive } from 'vue';
import { useNetworkStore } from '../stores/network';

/**
 * Network Metrics Composable
 * Provides historical data buffers and derived metrics for network visualization.
 * 
 * @param {number} bufferSize - Number of data points to keep in history (default: 30)
 * @param {number} intervalMs - Polling interval in ms (default: 5000)
 */
export function useNetworkMetrics(bufferSize = 30, intervalMs = 5000) {
    const netStore = useNetworkStore();

    // Histories
    const latencyHistory = ref([]);
    const packetLossHistory = ref([]);
    const throughputHistory = ref([]);
    const slaHistory = ref([]);

    // Timer reference
    let metricsInterval = null;

    /**
     * Push current store values into history buffers
     */
    const snapshot = () => {
        const push = (arr, val) => {
            arr.push(val);
            if (arr.length > bufferSize) arr.shift();
        };

        if (netStore.metrics && netStore.bandwidth) {
            push(latencyHistory.value, netStore.metrics.latencyMs || 0);
            push(packetLossHistory.value, (netStore.metrics.packetLoss || 0) * 100);
            push(throughputHistory.value, netStore.bandwidth.totalUsedGbps || 0);
            push(slaHistory.value, netStore.metrics.slaCompliance || 100);
        }
    };

    /**
     * Start tracking metrics
     */
    const startTracking = () => {
        // Initial snapshot
        snapshot();
        // Clear existing if any
        if (metricsInterval) clearInterval(metricsInterval);
        // Start interval
        metricsInterval = setInterval(snapshot, intervalMs);
    };

    /**
     * Stop tracking metrics
     */
    const stopTracking = () => {
        if (metricsInterval) {
            clearInterval(metricsInterval);
            metricsInterval = null;
        }
    };

    // Auto-start/stop on component lifecycle
    onMounted(() => {
        console.log('[useNetworkMetrics] Mounted');
        startTracking();
    });

    onUnmounted(() => {
        console.log('[useNetworkMetrics] Unmounted');
        stopTracking();
    });

    // Derived states
    const isCongested = computed(() => (netStore.bandwidth?.saturation || 0) > 80);
    const isCritical = computed(() => (netStore.bandwidth?.saturation || 0) > 95);

    return {
        // Raw Data (Refs)
        latencyHistory,
        packetLossHistory,
        throughputHistory,
        slaHistory,

        // Controls
        startTracking,
        stopTracking,
        snapshot,

        // Derived Status
        isCongested,
        isCritical,

        // Metrics (Auto-unwrapped in templates)
        latency: computed(() => netStore.metrics?.latencyMs || 0),
        packetLossPercent: computed(() => (netStore.metrics?.packetLoss || 0) * 100),
        bandwidthGbps: computed(() => netStore.bandwidth?.totalUsedGbps || 0),
        capacityGbps: computed(() => netStore.bandwidth?.totalCapacityGbps || 0),
        saturation: computed(() => netStore.bandwidth?.saturation || 0),
        slaCompliance: computed(() => netStore.metrics?.slaCompliance || 100),
    };
}
