<template>
    <div class="tab-content performance-tab monitoring-v3">
        <div class="proc-header">
            <div class="proc-title">
                <h3>REALTIME_MONITORING_SUITE</h3>
                <p>Echtzeit-Analyse der Hardware-Auslastung auf Node: {{ server.id.substring(0,8) }}</p>
            </div>
        </div>

        <div class="live-metrics-v3">
            <div class="v3-metric cpu" :class="{ 'high-load': latestCpu > 80 }">
                <div class="m-card-bg"></div>
                <label>PROZESSOR_LAST</label>
                <div class="m-val">{{ latestCpu }}<small>%</small></div>
                <div class="m-graph-mini">
                    <div class="m-fill" :style="{ width: latestCpu + '%' }"></div>
                </div>
            </div>
            <div class="v3-metric ram">
                <div class="m-card-bg"></div>
                <label>SPEICHER_BELEGUNG</label>
                <div class="m-val">{{ latestRam }}<small>%</small></div>
                <div class="m-graph-mini">
                    <div class="m-fill" :style="{ width: latestRam + '%' }"></div>
                </div>
            </div>
            <div class="v3-metric net">
                <div class="m-card-bg"></div>
                <label>DURCHSATZ_UPSTREAM</label>
                <div class="m-val">{{ latestBw.toFixed(1) }}<small>Mbps</small></div>
                <div class="m-graph-mini">
                    <div class="m-fill" :style="{ width: Math.min(100, latestBw / 10) + '%' }"></div>
                </div>
            </div>
        </div>

        <div class="telemetry-grid">
            <div class="telemetry-chart">
                <div class="chart-info">
                    <label>CPU_UTILIZATION_HISTORY</label>
                    <span>Letzte 20 Zyklen (Aggregiert)</span>
                </div>
                <div class="sparkline-v3 cpu">
                    <Sparkline :data="metrics.map(d => d.cpu)" color="#00f0ff" :height="30" />
                </div>
            </div>
            <div class="telemetry-chart">
                <div class="chart-info">
                    <label>RAM_CONSUMPTION_LOG</label>
                    <span>System & Applikations-Buffer</span>
                </div>
                <div class="sparkline-v3 ram">
                    <Sparkline :data="metrics.map(d => d.ram)" color="#a855f7" :height="30" />
                </div>
            </div>
            <div class="telemetry-chart full-width">
                <div class="chart-info">
                    <label>NETWORK_IO_ANALYTICS</label>
                    <span>Aggregierter Durchsatz (Node Local)</span>
                </div>
                <div class="sparkline-v3 net">
                    <Sparkline :data="metrics.map(d => d.bandwidth)" color="#2ecc71" :height="30" :max="1000" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Sparkline from '../../../UI/Sparkline.vue';

const props = defineProps({
    server: { type: Object, required: true },
    metrics: { type: Array, default: () => [] }
});

const latestCpu = computed(() => props.metrics.length > 0 ? props.metrics[props.metrics.length - 1].cpu : 0);
const latestRam = computed(() => props.metrics.length > 0 ? props.metrics[props.metrics.length - 1].ram : 0);
const latestBw = computed(() => props.metrics.length > 0 ? props.metrics[props.metrics.length - 1].bandwidth : 0);
</script>
