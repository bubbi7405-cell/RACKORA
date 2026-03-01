<template>
    <div class="telemetry-line" :style="containerStyle">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            :width="width"
            :height="height"
            preserveAspectRatio="none"
            class="tl-svg"
        >
            <!-- Gradient fill beneath line -->
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="resolvedColor" stop-opacity="0.15" />
                    <stop offset="100%" :stop-color="resolvedColor" stop-opacity="0" />
                </linearGradient>
            </defs>

            <!-- Area fill -->
            <path
                v-if="areaPath"
                :d="areaPath"
                :fill="`url(#${gradientId})`"
                class="tl-area"
            />

            <!-- Main line -->
            <polyline
                v-if="linePath"
                :points="linePath"
                :stroke="resolvedColor"
                stroke-width="1.5"
                fill="none"
                stroke-linejoin="round"
                stroke-linecap="round"
                class="tl-line"
            />

            <!-- Current value dot -->
            <circle
                v-if="normalizedData.length > 0"
                :cx="dotX"
                :cy="dotY"
                r="2.5"
                :fill="resolvedColor"
                class="tl-dot"
            />
        </svg>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /** Normalized 0-1 data array from useTelemetry().sparkData */
    data: {
        type: Array,
        default: () => [],
    },
    /** CSS color or CSS variable */
    color: {
        type: String,
        default: 'var(--ds-accent)',
    },
    /** SVG width */
    width: {
        type: Number,
        default: 200,
    },
    /** SVG height */
    height: {
        type: Number,
        default: 32,
    },
    /** Show the area fill gradient beneath the line */
    showArea: {
        type: Boolean,
        default: true,
    },
});

// Unique gradient ID per instance
const instanceId = ref(Math.random().toString(36).slice(2, 8));
const gradientId = computed(() => `tl-grad-${instanceId.value}`);

const resolvedColor = computed(() => props.color);

const normalizedData = computed(() => {
    if (!props.data || props.data.length === 0) return [];
    return props.data;
});

// Padding inside SVG
const padding = { top: 4, bottom: 4, left: 0, right: 4 };
const chartWidth = computed(() => props.width - padding.left - padding.right);
const chartHeight = computed(() => props.height - padding.top - padding.bottom);

// Generate SVG polyline points string
const linePath = computed(() => {
    const d = normalizedData.value;
    if (d.length < 2) return '';

    const stepX = chartWidth.value / (d.length - 1);

    return d.map((val, i) => {
        const x = padding.left + i * stepX;
        const y = padding.top + (1 - val) * chartHeight.value;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
});

// Generate SVG area path
const areaPath = computed(() => {
    if (!props.showArea) return '';
    const d = normalizedData.value;
    if (d.length < 2) return '';

    const stepX = chartWidth.value / (d.length - 1);
    const bottom = props.height;

    let path = `M ${padding.left},${bottom}`;

    // Line to first point
    d.forEach((val, i) => {
        const x = padding.left + i * stepX;
        const y = padding.top + (1 - val) * chartHeight.value;
        path += ` L ${x.toFixed(1)},${y.toFixed(1)}`;
    });

    // Close path
    const lastX = padding.left + (d.length - 1) * stepX;
    path += ` L ${lastX.toFixed(1)},${bottom} Z`;

    return path;
});

// Current value dot position
const dotX = computed(() => {
    const d = normalizedData.value;
    if (d.length < 2) return padding.left;
    const stepX = chartWidth.value / (d.length - 1);
    return padding.left + (d.length - 1) * stepX;
});

const dotY = computed(() => {
    const d = normalizedData.value;
    if (d.length === 0) return props.height / 2;
    const last = d[d.length - 1];
    return padding.top + (1 - last) * chartHeight.value;
});

const containerStyle = computed(() => ({
    width: `${props.width}px`,
    height: `${props.height}px`,
}));
</script>

<style scoped>
.telemetry-line {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
}

.tl-svg {
    display: block;
}

.tl-line {
    transition: all 300ms ease-out;
}

.tl-area {
    transition: all 300ms ease-out;
}

.tl-dot {
    filter: drop-shadow(0 0 3px currentColor);
    animation: dotPulse 2s ease-in-out infinite;
}

@keyframes dotPulse {
    0%, 100% { opacity: 1; r: 2.5; }
    50% { opacity: 0.7; r: 3.5; }
}
</style>
