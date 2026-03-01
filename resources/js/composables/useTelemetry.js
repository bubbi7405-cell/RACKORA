/**
 * useTelemetry — Live value tracking with sparkline data generation.
 *
 * Watches a reactive value over time, stores history, and computes
 * sparkline-ready data, trend direction, min/max, and delta.
 *
 * Usage:
 *   const bandwidth = useTelemetry(
 *       () => network.bandwidth.saturation,
 *       { historyLength: 60, sampleInterval: 1000 }
 *   );
 *
 *   // In template:
 *   <TelemetryLine :data="bandwidth.sparkData" :color="bandwidth.trendColor" />
 *   <span>{{ bandwidth.current }}</span>
 *   <span :class="bandwidth.trendClass">{{ bandwidth.trendLabel }}</span>
 */
import { ref, computed, watch, onUnmounted } from 'vue';

export function useTelemetry(valueGetter, options = {}) {
    const {
        historyLength = 60,
        sampleInterval = 1000,
        decimals = 2,
    } = options;

    const history = ref([]);
    const current = computed(() => {
        const val = typeof valueGetter === 'function' ? valueGetter() : valueGetter?.value;
        return typeof val === 'number' ? val : 0;
    });

    // Sample on interval (not every reactive change)
    let intervalId = null;
    const startSampling = () => {
        intervalId = setInterval(() => {
            const point = {
                value: current.value,
                time: Date.now(),
            };
            history.value = [...history.value.slice(-(historyLength - 1)), point];
        }, sampleInterval);
    };

    startSampling();
    onUnmounted(() => {
        if (intervalId) clearInterval(intervalId);
    });

    // Sparkline data: normalized 0–1 values for SVG rendering
    const sparkData = computed(() => {
        const values = history.value.map(p => p.value);
        if (values.length < 2) return values.map(() => 0.5);

        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min || 1;

        return values.map(v => (v - min) / range);
    });

    // Raw values for more complex charting
    const rawValues = computed(() => history.value.map(p => p.value));

    // Trend: comparing last 5 values vs previous 5
    const trend = computed(() => {
        const vals = history.value.map(p => p.value);
        if (vals.length < 6) return 'stable';

        const recent = vals.slice(-5);
        const prior = vals.slice(-10, -5);

        const recentAvg = recent.reduce((a, b) => a + b, 0) / recent.length;
        const priorAvg = prior.reduce((a, b) => a + b, 0) / Math.max(prior.length, 1);

        const delta = recentAvg - priorAvg;
        const threshold = priorAvg * 0.02; // 2% change threshold

        if (delta > threshold) return 'rising';
        if (delta < -threshold) return 'falling';
        return 'stable';
    });

    const trendLabel = computed(() => {
        switch (trend.value) {
            case 'rising': return '↑';
            case 'falling': return '↓';
            default: return '→';
        }
    });

    const trendClass = computed(() => `trend-${trend.value}`);

    const trendColor = computed(() => {
        switch (trend.value) {
            case 'rising': return 'var(--ds-caution)';
            case 'falling': return 'var(--ds-nominal)';
            default: return 'var(--ds-text-muted)';
        }
    });

    // Min/Max over history window
    const min = computed(() => {
        if (history.value.length === 0) return 0;
        return Math.min(...history.value.map(p => p.value));
    });

    const max = computed(() => {
        if (history.value.length === 0) return 0;
        return Math.max(...history.value.map(p => p.value));
    });

    // Delta: difference between first and last value
    const delta = computed(() => {
        if (history.value.length < 2) return 0;
        const first = history.value[0].value;
        const last = history.value[history.value.length - 1].value;
        return +(last - first).toFixed(decimals);
    });

    const deltaPercent = computed(() => {
        if (history.value.length < 2) return 0;
        const first = history.value[0].value;
        if (first === 0) return 0;
        return +(((history.value[history.value.length - 1].value - first) / first) * 100).toFixed(1);
    });

    return {
        current,
        history,
        sparkData,
        rawValues,
        trend,
        trendLabel,
        trendClass,
        trendColor,
        min,
        max,
        delta,
        deltaPercent,
    };
}
