<template>
    <div class="sparkline-wrapper" ref="container">
        <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none">
            <path :d="linePath" :stroke="color" fill="none" :stroke-width="strokeWidth" vector-effect="non-scaling-stroke" />
            <path :d="areaPath" :fill="color" fill-opacity="0.1" stroke="none" />
        </svg>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },
    color: { type: String, default: '#00f0ff' },
    strokeWidth: { type: Number, default: 2 },
    height: { type: Number, default: 50 },
    width: { type: Number, default: 200 },
    max: { type: Number, default: null }
});

const maxVal = computed(() => {
    if (props.max !== null) return props.max;
    if (!props.data.length) return 1;
    return Math.max(...props.data, 1);
});

const minVal = computed(() => {
    if (!props.data.length) return 0;
    return Math.min(...props.data, 0);
});

const points = computed(() => {
    if (!props.data.length) return [];
    
    // Safety check for flat line
    const range = maxVal.value - minVal.value;
    const safeRange = range === 0 ? 1 : range;

    return props.data.map((val, i) => {
        const x = (i / (props.data.length - 1)) * props.width;
        // Invert Y axis because SVG 0 is top
        const normalized = (val - minVal.value) / safeRange;
        const y = props.height - (normalized * props.height);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
});

const linePath = computed(() => {
    if (props.data.length < 2) return '';
    return `M ${points.value.join(' L ')}`;
});

const areaPath = computed(() => {
    if (props.data.length < 2) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    // Close the path at the bottom
    return `M ${first} L ${points.value.join(' L ')} L ${last.split(',')[0]},${props.height} L 0,${props.height} Z`;
});
</script>

<style scoped>
.sparkline-wrapper { width: 100%; height: 100%; display: block; }
svg { width: 100%; height: 100%; overflow: visible; display: block; }
</style>
