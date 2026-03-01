<template>
    <div
        class="kpi-gauge"
        :class="[`severity-${severity}`, pulseClass, { 'is-elevated': isElevated }]"
    >
        <div class="kpi-header">
            <span class="kpi-label">{{ label }}</span>
            <span class="kpi-badge" v-if="badge" :style="{ color: color }">{{ badge }}</span>
        </div>

        <div class="kpi-body">
            <div class="kpi-value-row">
                <span class="kpi-value" :class="flashClass" :style="{ color: color }">
                    {{ display }}
                </span>
                <span class="kpi-unit" v-if="unit">{{ unit }}</span>
            </div>

            <TelemetryLine
                v-if="showSparkline && sparkData.length > 2"
                :data="sparkData"
                :color="color"
                :width="sparkWidth"
                :height="20"
            />
        </div>

        <!-- Optional progress bar -->
        <div class="kpi-bar" v-if="showBar">
            <div class="kpi-bar-track">
                <div
                    class="kpi-bar-fill"
                    :style="{
                        width: barPercent + '%',
                        background: color,
                        boxShadow: isElevated ? `0 0 8px ${glow}` : 'none',
                    }"
                ></div>
            </div>
            <div class="kpi-bar-labels" v-if="barLabel">
                <span class="kpi-bar-current">{{ barLabel }}</span>
                <span class="kpi-bar-max" v-if="barMaxLabel">{{ barMaxLabel }}</span>
            </div>
        </div>

        <div class="kpi-footer" v-if="$slots.footer || trend">
            <span class="kpi-trend" v-if="trend" :class="`trend-${trend}`">
                {{ trendIcon }} {{ trendText }}
            </span>
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import TelemetryLine from './TelemetryLine.vue';
import { useKpiColor } from '../../composables/useKpiColor.js';
import { useAnimatedValue } from '../../composables/useAnimatedValue.js';

const props = defineProps({
    /** Display label (e.g., "BANDWIDTH_SATURATION") */
    label: { type: String, required: true },

    /** Current numeric value */
    value: { type: Number, default: 0 },

    /** Unit suffix (e.g., "%", "ms", "°C") */
    unit: { type: String, default: '' },

    /** Number of decimal places */
    decimals: { type: Number, default: 1 },

    /** Badge text (e.g., "STABLE", "WARNING") */
    badge: { type: String, default: '' },

    /** Sparkline data from useTelemetry().sparkData */
    sparkData: { type: Array, default: () => [] },

    /** Show sparkline */
    showSparkline: { type: Boolean, default: true },

    /** Sparkline width */
    sparkWidth: { type: Number, default: 80 },

    /** Show progress bar */
    showBar: { type: Boolean, default: false },

    /** Bar percentage (0-100) */
    barPercent: { type: Number, default: 0 },

    /** Bar current label */
    barLabel: { type: String, default: '' },

    /** Bar max label */
    barMaxLabel: { type: String, default: '' },

    /** Trend direction: 'rising', 'falling', 'stable' */
    trend: { type: String, default: '' },

    /** Trend text label */
    trendText: { type: String, default: '' },

    /** KPI severity thresholds */
    thresholds: {
        type: Object,
        default: () => ({ nominal: 70, caution: 85, warning: 95 }),
    },

    /** Whether lower values are worse (e.g., SLA compliance) */
    inverted: { type: Boolean, default: false },
});

// KPI severity colors
const { severity, color, glow, pulseClass, isElevated } = useKpiColor(
    () => props.value,
    props.thresholds,
    { inverted: props.inverted }
);

// Animated number display
const { display, flashClass } = useAnimatedValue(
    () => props.value,
    { decimals: props.decimals, duration: 400 }
);

const trendIcon = computed(() => {
    switch (props.trend) {
        case 'rising': return '↑';
        case 'falling': return '↓';
        default: return '→';
    }
});
</script>

<style scoped>
.kpi-gauge {
    background: var(--ds-bg-elevated);
    border: var(--ds-border-subtle);
    border-radius: var(--ds-radius-md);
    padding: var(--ds-space-6) var(--ds-space-8);
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-3);
    transition: border-color var(--ds-duration-normal) var(--ds-ease-out),
                box-shadow var(--ds-duration-normal) var(--ds-ease-out);
    position: relative;
    overflow: hidden;
}

.kpi-gauge::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: transparent;
    transition: background var(--ds-duration-normal) var(--ds-ease-out);
}

.kpi-gauge.severity-caution::after  { background: var(--ds-caution); opacity: 0.3; }
.kpi-gauge.severity-warning::after  { background: var(--ds-warning); opacity: 0.5; }
.kpi-gauge.severity-critical::after { background: var(--ds-critical); opacity: 0.7; }

.kpi-gauge.is-elevated {
    border-color: var(--ds-border-color);
}

/* Header */
.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kpi-label {
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-xs);
    font-weight: 700;
    color: var(--ds-text-muted);
    letter-spacing: var(--ds-tracking-widest);
    text-transform: uppercase;
}

.kpi-badge {
    font-family: var(--ds-font-mono);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: var(--ds-tracking-wide);
    padding: 1px 5px;
    border: 1px solid currentColor;
    border-radius: var(--ds-radius-sm);
    opacity: 0.8;
}

/* Body */
.kpi-body {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--ds-space-4);
}

.kpi-value-row {
    display: flex;
    align-items: baseline;
    gap: var(--ds-space-2);
}

.kpi-value {
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-xl);
    font-weight: 800;
    letter-spacing: var(--ds-tracking-tight);
    line-height: 1;
    transition: color var(--ds-duration-normal) var(--ds-ease-out);
}

.kpi-unit {
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-xs);
    font-weight: 600;
    color: var(--ds-text-muted);
    letter-spacing: var(--ds-tracking-wide);
}

/* Flash animation classes */
.ds-flash-up {
    animation: flashUp 800ms ease-out;
}

.ds-flash-down {
    animation: flashDown 800ms ease-out;
}

@keyframes flashUp {
    0% { text-shadow: 0 0 8px var(--ds-nominal-glow); transform: scale(1.04); }
    100% { text-shadow: none; transform: scale(1); }
}

@keyframes flashDown {
    0% { text-shadow: 0 0 8px var(--ds-critical-glow); transform: scale(1.04); }
    100% { text-shadow: none; transform: scale(1); }
}

/* Progress Bar */
.kpi-bar {
    display: flex;
    flex-direction: column;
    gap: var(--ds-space-1);
}

.kpi-bar-track {
    width: 100%;
    height: 3px;
    background: var(--ds-bg-subtle);
    border-radius: var(--ds-radius-full);
    overflow: hidden;
}

.kpi-bar-fill {
    height: 100%;
    border-radius: var(--ds-radius-full);
    transition: width var(--ds-duration-slow) var(--ds-ease-out),
                background var(--ds-duration-normal) var(--ds-ease-out);
}

.kpi-bar-labels {
    display: flex;
    justify-content: space-between;
    font-family: var(--ds-font-mono);
    font-size: 9px;
    color: var(--ds-text-ghost);
    letter-spacing: var(--ds-tracking-wide);
}

/* Footer / Trend */
.kpi-footer {
    display: flex;
    align-items: center;
    gap: var(--ds-space-4);
}

.kpi-trend {
    font-family: var(--ds-font-mono);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: var(--ds-tracking-wide);
}

.trend-rising  { color: var(--ds-caution); }
.trend-falling { color: var(--ds-nominal); }
.trend-stable  { color: var(--ds-text-ghost); }
</style>
