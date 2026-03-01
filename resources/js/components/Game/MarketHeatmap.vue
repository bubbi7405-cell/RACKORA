<template>
    <div class="market-heatmap">
        <div class="heatmap-header">
            <span class="label">REGIONAL_SATURATION_MAP</span>
            <div class="legend">
                <span class="dot low"></span> Low
                <span class="dot mid"></span> Mid
                <span class="dot high"></span> High
            </div>
        </div>
        <div class="heatmap-grid">
            <div v-for="(data, region) in regions" :key="region" class="region-node" :class="'intensity-' + getIntensity(data.saturation)">
                <div class="region-flag">{{ data.flag }}</div>
                <div class="region-info">
                    <div class="region-name">{{ data.name }}</div>
                    <div class="region-stats">
                        <span>SAT: {{ Math.round(data.saturation) }}%</span>
                        <span>LAT: {{ data.latency }}ms</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    marketData: Object
});

const regions = computed(() => {
    // Mocking saturation for now, in reality this would come from marketShare.regionalDistribution
    return {
        'us_east': { name: 'US_EAST', flag: '🇺🇸', saturation: 45, latency: 12 },
        'eu_central': { name: 'EU_CENTRAL', flag: '🇩🇪', saturation: 78, latency: 8 },
        'asia_east': { name: 'ASIA_EAST', flag: '🇯🇵', saturation: 22, latency: 135 },
    };
});

function getIntensity(val) {
    if (val > 70) return 'high';
    if (val > 30) return 'mid';
    return 'low';
}
</script>

<style scoped>
.market-heatmap {
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 16px;
}
.heatmap-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 0.7rem;
    font-weight: 900;
    color: var(--v2-text-muted);
}
.legend { display: flex; gap: 12px; }
.dot { width: 8px; height: 8px; border-radius: 50%; opacity: 0.8; }
.dot.low { background: #3fb950; }
.dot.mid { background: #d29922; }
.dot.high { background: #f85149; }

.heatmap-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.region-node {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 12px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s;
}
.intensity-low { border-left: 4px solid #3fb950; }
.intensity-mid { border-left: 4px solid #d29922; }
.intensity-high { border-left: 4px solid #f85149; }

.region-flag { font-size: 1.5rem; }
.region-name { font-weight: 800; font-size: 0.8rem; color: #fff; }
.region-stats { display: flex; gap: 10px; font-size: 0.65rem; color: var(--v2-text-muted); font-family: monospace; }
</style>
