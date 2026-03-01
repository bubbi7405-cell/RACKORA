/**
 * useKpiColor — Severity-based color system for KPI metrics.
 *
 * Maps a numeric value to a severity level (nominal → caution → warning → critical)
 * and returns reactive CSS custom properties for color, glow, and background.
 *
 * Usage:
 *   const { severity, color, glow, softBg } = useKpiColor(
 *       () => temperatureValue.value,
 *       { nominal: 30, caution: 38, warning: 45 }
 *   );
 *
 * Inverted mode (lower = worse, e.g. SLA compliance, health):
 *   const { severity } = useKpiColor(
 *       () => slaCompliance.value,
 *       { nominal: 99, caution: 97, warning: 95 },
 *       { inverted: true }
 *   );
 */
import { computed } from 'vue';

export function useKpiColor(valueGetter, thresholds = {}, options = {}) {
    const {
        nominal = 70,
        caution = 85,
        warning = 95,
    } = thresholds;

    const { inverted = false } = options;

    const severity = computed(() => {
        const v = typeof valueGetter === 'function' ? valueGetter() : valueGetter.value;

        if (inverted) {
            // Lower = worse (e.g., SLA: 100% = good, 90% = bad)
            if (v <= warning) return 'critical';
            if (v <= caution) return 'warning';
            if (v <= nominal) return 'caution';
            return 'nominal';
        }

        // Higher = worse (e.g., temperature, saturation)
        if (v >= warning) return 'critical';
        if (v >= caution) return 'warning';
        if (v >= nominal) return 'caution';
        return 'nominal';
    });

    const color = computed(() => `var(--ds-${severity.value})`);
    const glow = computed(() => `var(--ds-${severity.value}-glow)`);
    const softBg = computed(() => `var(--ds-${severity.value}-soft)`);
    const mediumBg = computed(() => `var(--ds-${severity.value}-medium)`);

    const pulseClass = computed(() => {
        if (severity.value === 'warning' || severity.value === 'critical') {
            return `pulse-${severity.value}`;
        }
        return '';
    });

    const isElevated = computed(() =>
        severity.value === 'warning' || severity.value === 'critical'
    );

    return {
        severity,
        color,
        glow,
        softBg,
        mediumBg,
        pulseClass,
        isElevated,
    };
}

/**
 * Predefined KPI threshold presets for common metrics.
 */
export const KPI_PRESETS = {
    temperature: { nominal: 30, caution: 38, warning: 45 },
    power: { nominal: 70, caution: 85, warning: 95 },
    bandwidth: { nominal: 70, caution: 85, warning: 95 },
    packetLoss: { nominal: 0.01, caution: 0.1, warning: 1.0 },
    latency: { nominal: 30, caution: 60, warning: 100 },
    slaCompliance: { nominal: 99, caution: 97, warning: 95, inverted: true },
    serverHealth: { nominal: 80, caution: 60, warning: 30, inverted: true },
    reputation: { nominal: 90, caution: 70, warning: 50, inverted: true },
    ipUtilization: { nominal: 70, caution: 85, warning: 95 },
    rackCapacity: { nominal: 70, caution: 85, warning: 95 },
    dustLevel: { nominal: 20, caution: 50, warning: 80 },
};

/**
 * Shorthand: create a KPI color binding from a preset name.
 *
 * Usage:
 *   const tempKpi = usePresetKpi('temperature', () => rack.temperature);
 */
export function usePresetKpi(presetName, valueGetter) {
    const preset = KPI_PRESETS[presetName];
    if (!preset) {
        console.warn(`[usePresetKpi] Unknown preset: "${presetName}"`);
        return useKpiColor(valueGetter);
    }
    const { inverted, ...thresholds } = preset;
    return useKpiColor(valueGetter, thresholds, { inverted: !!inverted });
}
