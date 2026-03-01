<template>
    <span
        class="status-indicator"
        :class="[`si-${severity}`, { 'si-pulse': pulse }]"
        :title="title"
    >
        <span class="si-dot"></span>
        <span class="si-label" v-if="label">{{ label }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /**
     * Severity level: 'nominal', 'caution', 'warning', 'critical', 'offline', 'unknown'
     */
    severity: {
        type: String,
        default: 'nominal',
        validator: (v) => ['nominal', 'caution', 'warning', 'critical', 'offline', 'unknown'].includes(v),
    },

    /** Optional text label next to the dot */
    label: { type: String, default: '' },

    /** Tooltip text */
    title: { type: String, default: '' },

    /** Whether to animate the pulse */
    pulse: { type: Boolean, default: true },
});
</script>

<style scoped>
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: var(--ds-space-3, 6px);
}

.si-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    transition: background var(--ds-duration-normal, 250ms) ease-out,
                box-shadow var(--ds-duration-normal, 250ms) ease-out;
}

.si-label {
    font-family: var(--ds-font-mono, monospace);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

/* Nominal (green) */
.si-nominal .si-dot {
    background: var(--ds-nominal, hsl(152, 55%, 48%));
    box-shadow: 0 0 5px var(--ds-nominal-glow, hsla(152, 55%, 48%, 0.3));
}
.si-nominal .si-label { color: var(--ds-nominal, hsl(152, 55%, 48%)); }
.si-nominal.si-pulse .si-dot {
    animation: siPulseNominal 3s ease-in-out infinite;
}

/* Caution (amber) */
.si-caution .si-dot {
    background: var(--ds-caution, hsl(42, 80%, 52%));
    box-shadow: 0 0 5px var(--ds-caution-glow, hsla(42, 80%, 52%, 0.3));
}
.si-caution .si-label { color: var(--ds-caution); }
.si-caution.si-pulse .si-dot {
    animation: siPulseCaution 2.5s ease-in-out infinite;
}

/* Warning (orange) */
.si-warning .si-dot {
    background: var(--ds-warning, hsl(28, 85%, 52%));
    box-shadow: 0 0 6px var(--ds-warning-glow, hsla(28, 85%, 52%, 0.3));
}
.si-warning .si-label { color: var(--ds-warning); }
.si-warning.si-pulse .si-dot {
    animation: siPulseWarning 2s ease-in-out infinite;
}

/* Critical (red) */
.si-critical .si-dot {
    background: var(--ds-critical, hsl(0, 70%, 52%));
    box-shadow: 0 0 8px var(--ds-critical-glow, hsla(0, 70%, 52%, 0.4));
}
.si-critical .si-label { color: var(--ds-critical); }
.si-critical.si-pulse .si-dot {
    animation: siPulseCritical 1.2s ease-in-out infinite;
}

/* Offline (gray) */
.si-offline .si-dot {
    background: var(--ds-text-ghost, hsl(220, 10%, 24%));
    box-shadow: none;
}
.si-offline .si-label { color: var(--ds-text-ghost); }

/* Unknown (dim) */
.si-unknown .si-dot {
    background: var(--ds-text-ghost, hsl(220, 10%, 24%));
    box-shadow: none;
    opacity: 0.5;
}
.si-unknown .si-label { color: var(--ds-text-ghost); opacity: 0.5; }

/* Pulse animations — each severity has its own rhythm */
@keyframes siPulseNominal {
    0%, 100% { box-shadow: 0 0 4px var(--ds-nominal-glow, hsla(152, 55%, 48%, 0.2)); }
    50%      { box-shadow: 0 0 8px 2px var(--ds-nominal-glow, hsla(152, 55%, 48%, 0.35)); }
}

@keyframes siPulseCaution {
    0%, 100% { box-shadow: 0 0 5px var(--ds-caution-glow, hsla(42, 80%, 52%, 0.2)); }
    50%      { box-shadow: 0 0 10px 2px var(--ds-caution-glow, hsla(42, 80%, 52%, 0.4)); }
}

@keyframes siPulseWarning {
    0%, 100% { box-shadow: 0 0 5px var(--ds-warning-glow, hsla(28, 85%, 52%, 0.2)); }
    50%      { box-shadow: 0 0 12px 3px var(--ds-warning-glow, hsla(28, 85%, 52%, 0.45)); }
}

@keyframes siPulseCritical {
    0%, 100% { box-shadow: 0 0 6px var(--ds-critical-glow, hsla(0, 70%, 52%, 0.25)); }
    50%      { box-shadow: 0 0 14px 4px var(--ds-critical-glow, hsla(0, 70%, 52%, 0.5)); }
}
</style>
