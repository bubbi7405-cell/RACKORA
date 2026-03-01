<template>
    <div class="market-share-chart" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg
            :viewBox="`0 0 ${size} ${size}`"
            :width="size"
            :height="size"
            class="chart-svg"
        >
            <defs>
                <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                    <feGaussianBlur stdDeviation="3" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
                <filter id="shadow">
                    <feDropShadow dx="0" dy="0" stdDeviation="2" flood-opacity="0.5"/>
                </filter>
                <radialGradient id="centerGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="var(--ds-primary-glow)" stop-opacity="0.1" />
                    <stop offset="100%" stop-color="var(--ds-primary-glow)" stop-opacity="0" />
                </radialGradient>
            </defs>

            <g :transform="`translate(${center}, ${center})`">
                <!-- Inner Glow -->
                <circle
                    :r="radius - (thickness / 2)"
                    fill="url(#centerGlow)"
                />

                <!-- Background circle (empty state) -->
                <circle
                    v-if="segments.length === 0"
                    r="40"
                    fill="none"
                    stroke="var(--ds-bg-subtle)"
                    stroke-width="10"
                />

                <!-- Slices -->
                <path
                    v-for="(slice, index) in processedSlices"
                    :key="index"
                    :d="slice.path"
                    :fill="slice.color"
                    :stroke="strokeColor"
                    stroke-width="1.5"
                    class="chart-slice"
                    :class="{ 'is-hovered': hoveredIndex === index }"
                    filter="url(#shadow)"
                    @mouseenter="hoveredIndex = index"
                    @mouseleave="hoveredIndex = null"
                />

                <!-- Inner Text (Donut) -->
                <g v-if="showTotal">
                    <text
                        dy="-0.2em"
                        text-anchor="middle"
                        class="chart-total"
                    >
                        {{ totalLabel }}
                    </text>
                    <text
                        dy="1.2em"
                        text-anchor="middle"
                        class="chart-sub-label"
                    >
                        CAPACITY
                    </text>
                </g>
            </g>
        </svg>

        <!-- Tooltip -->
        <div
            v-if="hoveredIndex !== null"
            class="chart-tooltip"
            :style="tooltipStyle"
        >
            <div class="tooltip-label">{{ processedSlices[hoveredIndex].label }}</div>
            <div class="tooltip-value">{{ processedSlices[hoveredIndex].formattedValue }}%</div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /** Data: [{ label, value, color }] */
    segments: {
        type: Array,
        required: true,
    },
    size: {
        type: Number,
        default: 200,
    },
    strokeColor: {
        type: String,
        default: 'var(--ds-bg-elevated)',
    },
    donut: {
        type: Boolean,
        default: true,
    },
    thickness: {
        type: Number,
        default: 20, // Percentage of radius (0-100) or pixels? Let's treat as pixels for simplicity
    },
    showTotal: {
        type: Boolean,
        default: false,
    },
    totalLabel: {
        type: String,
        default: '100%',
    },
});

const hoveredIndex = ref(null);
const center = computed(() => props.size / 2);
const radius = computed(() => (props.size / 2) - 2); // padding for stroke

const totalValue = computed(() => {
    return props.segments.reduce((acc, curr) => acc + curr.value, 0);
});

const processedSlices = computed(() => {
    let startAngle = 0;
    const innerRadius = props.donut ? radius.value - props.thickness : 0;

    return props.segments.map((segment) => {
        // Calculate percentage of circle
        const sliceAngle = (segment.value / totalValue.value) * 360;
        const endAngle = startAngle + sliceAngle;

        // Create path
        const path = describeArc(0, 0, radius.value, innerRadius, startAngle, endAngle);

        // Advance start angle for next slice
        const currentStart = startAngle;
        startAngle = endAngle;

        return {
            ...segment,
            path,
            startAngle: currentStart,
            endAngle,
            formattedValue: segment.value.toFixed(1),
        };
    });
});

const tooltipStyle = computed(() => {
    // Basic positioning, could be improved with mouse tracking
    return {
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
    };
});

// SVG Helper Functions
function polarToCartesian(centerX, centerY, radius, angleInDegrees) {
    const angleInRadians = (angleInDegrees - 90) * Math.PI / 180.0;
    return {
        x: centerX + (radius * Math.cos(angleInRadians)),
        y: centerY + (radius * Math.sin(angleInRadians))
    };
}

function describeArc(x, y, outerRadius, innerRadius, startAngle, endAngle) {
    // If full circle
    if (endAngle - startAngle >= 360) {
        endAngle = startAngle + 359.99;
    }

    const start = polarToCartesian(x, y, outerRadius, endAngle);
    const end = polarToCartesian(x, y, outerRadius, startAngle);
    
    const startInner = polarToCartesian(x, y, innerRadius, endAngle);
    const endInner = polarToCartesian(x, y, innerRadius, startAngle);

    const largeArcFlag = endAngle - startAngle <= 180 ? "0" : "1";

    // Outer arc
    const d = [
        "M", start.x, start.y,
        "A", outerRadius, outerRadius, 0, largeArcFlag, 0, end.x, end.y,
        // Line to inner start
        "L", endInner.x, endInner.y,
        // Inner arc (reverse direction)
        "A", innerRadius, innerRadius, 0, largeArcFlag, 1, startInner.x, startInner.y,
        "Z"
    ].join(" ");

    return d;
}
</script>

<style scoped>
.market-share-chart {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
}

.chart-svg {
    transform: rotate(0deg); /* Optional initial rotation */
}

.chart-slice {
    transition: transform 0.2s ease-out, opacity 0.2s;
    cursor: pointer;
}

.chart-slice:hover {
    opacity: 0.9;
    transform: scale(1.02); /* This transform won't work well on path inside group without origin logic */
    filter: brightness(1.1);
}

.is-hovered {
    /* Alternative highlight */
    stroke-width: 4;
}

.chart-total {
    fill: var(--ds-text-primary);
    font-family: var(--ds-font-mono);
    font-size: var(--ds-text-2xl);
    font-weight: 800;
    letter-spacing: -0.05em;
}

.chart-sub-label {
    fill: var(--ds-text-muted);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.chart-tooltip {
    position: absolute;
    pointer-events: none;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
    padding: var(--ds-space-2) var(--ds-space-4);
    border-radius: var(--ds-radius-md);
    border: 1px solid var(--ds-border-subtle);
    text-align: center;
    z-index: 10;
}

.tooltip-label {
    font-size: var(--ds-text-xs);
    color: var(--ds-text-muted);
    font-weight: 600;
}

.tooltip-value {
    font-size: var(--ds-text-sm);
    color: var(--ds-text-primary);
    font-weight: 700;
    font-family: var(--ds-font-mono);
}
</style>
