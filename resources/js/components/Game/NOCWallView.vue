<template>
    <div class="noc-wall" :class="{ 'is-fullscreen': isFullscreen }">
        <header class="wall-header">
            <div class="header-left">
                <div class="brand">
                    <span class="brand-line"></span>
                    <span class="brand-text">NOC_WALL_MODE // PHASE_04</span>
                </div>
                <div class="global-status">
                    <div class="status-item">
                        <span class="label">THREAT_LEVEL</span>
                        <span class="value" :class="'threat-' + globalThreatLevel.id">{{ globalThreatLevel.label
                            }}</span>
                    </div>
                    <div class="status-item">
                        <span class="label">GLOBAL_UPTIME</span>
                        <span class="value success">{{ networkStore.metrics.slaCompliance.toFixed(3) }}%</span>
                    </div>
                    <div class="status-item">
                        <span class="label">NET_FLOW</span>
                        <span class="value" :class="netIncome >= 0 ? 'success' : 'danger'">
                            {{ netIncome >= 0 ? '+' : '' }}${{ formatMoney(netIncome) }}/hr
                        </span>
                    </div>
                </div>
            </div>

            <div class="header-center">
                <div class="system-clock">{{ currentTime }}</div>
            </div>

            <div class="header-right">
                <button class="wall-btn" @click="toggleFullscreen">
                    {{ isFullscreen ? 'EXIT_FULLSCREEN' : 'ENTER_FULLSCREEN' }}
                </button>
                <button class="wall-btn close-btn" @click="handleClose">CLOSE_WALL</button>
            </div>
        </header>

        <main class="wall-grid">
            <!-- PANEL 1: REGIONAL TRAFFIC HEATMAP -->
            <section class="wall-panel traffic-map">
                <div class="panel-header">
                    <span class="panel-icon">🌐</span>
                    <span class="panel-title">GLOBAL_TRAFFIC_HEATMAP</span>
                </div>
                <div class="panel-content map-wrapper">
                    <div class="svg-map-container">
                        <svg viewBox="0 0 1000 500" class="world-svg">
                            <!-- Simplified World Paths -->
                            <path d="M150,150 Q250,100 350,150 T550,150 T850,200" class="map-path" />
                            <circle v-for="reg in regions" :key="'node-' + reg.id" :cx="getRegionCoords(reg.id).x"
                                :cy="getRegionCoords(reg.id).y" r="8"
                                :class="['map-node', { 'active': highlightedRegion === reg.id }]"
                                @click="handleRegionClick(reg.id)">
                                <animate attributeName="r" values="8;12;8" dur="2s" repeatCount="indefinite" />
                            </circle>
                            <!-- Flow Lines -->
                            <g v-if="highlightedRegion">
                                <line v-for="other in regions.filter(r => r.id !== highlightedRegion)"
                                    :key="'flow-' + other.id" :x1="getRegionCoords(highlightedRegion).x"
                                    :y1="getRegionCoords(highlightedRegion).y" :x2="getRegionCoords(other.id).x"
                                    :y2="getRegionCoords(other.id).y" class="flow-line" />
                            </g>
                        </svg>
                    </div>
                    <div class="heatmap-container overlay-list">
                        <div v-for="reg in regions" :key="reg.id" class="region-node"
                            :class="{ 'is-active': highlightedRegion === reg.id }" @click="handleRegionClick(reg.id)">
                            <div class="node-meta">
                                <span class="flag">{{ reg.flag }}</span>
                                <span class="name">{{ reg.id.toUpperCase() }}</span>
                            </div>
                            <div class="node-bars">
                                <div class="bar-group">
                                    <label>LOAD</label>
                                    <div class="bar-bg">
                                        <div class="bar-fill"
                                            :style="{ width: reg.load + '%', background: getLoadColor(reg.load) }">
                                        </div>
                                    </div>
                                </div>
                                <div class="bar-group">
                                    <label>LATENCY</label>
                                    <div class="bar-bg">
                                        <div class="bar-fill latency"
                                            :style="{ width: Math.min(100, (reg.latency / 2)) + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="node-stats">
                                <span class="stat">{{ reg.latency }}ms</span>
                                <span class="stat">{{ reg.load }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PANEL 2: ACTIVE INCIDENTS -->
            <section class="wall-panel incidents">
                <div class="panel-header">
                    <span class="panel-icon">🚨</span>
                    <span class="panel-title">ACTIVE_INCIDENTS</span>
                    <span class="badge" v-if="activeEvents.length > 0">{{ activeEvents.length }}</span>
                </div>
                <div class="panel-content scrollable">
                    <div v-if="selectedEvent" class="event-detail-view">
                        <header class="detail-header">
                            <button class="back-btn" @click="selectedEvent = null">← RETURN_TO_LIST</button>
                            <div class="timing">{{ formatRemaining(selectedEvent) }} REMAINING</div>
                        </header>
                        <div class="detail-body">
                            <div class="type-tag" :class="'severity-' + selectedEvent.severity">{{
                                selectedEvent.typeLabel }} // {{ selectedEvent.severity.toUpperCase() }}</div>
                            <h2 class="title">{{ selectedEvent.title }}</h2>
                            <p class="desc">{{ selectedEvent.description }}</p>

                            <div class="actions-grid">
                                <button v-for="action in selectedEvent.available_actions" :key="action.id"
                                    class="wall-action-btn" :class="getActionClass(action)"
                                    @click="resolveEvent(action.id)" :disabled="isResolving">
                                    <div class="a-info">
                                        <span class="a-label">{{ action.label }}</span>
                                        <span class="a-desc">{{ action.description }}</span>
                                    </div>
                                    <div class="a-meta">
                                        <span class="a-cost">${{ action.cost.toLocaleString() }}</span>
                                        <span class="a-chance">{{ action.success_chance }}% CHANCE</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    <template v-else>
                        <div v-for="event in activeEvents" :key="event.id" class="event-row"
                            :class="['severity-' + event.severity, { 'is-selected': selectedEvent?.id === event.id }]"
                            @click="handleIncidentClick(event)">
                            <div class="event-type">{{ event.typeLabel }}</div>
                            <div class="event-info">
                                <div class="event-title">{{ event.title }}</div>
                                <div class="event-meta">ID: {{ event.id.toString().slice(-8) }} // {{
                                    formatRemaining(event) }}</div>
                            </div>
                            <div class="event-progress">
                                <div class="p-bg">
                                    <div class="p-fill" :style="{ width: getProgressWidth(event) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                        <div v-if="activeEvents.length === 0" class="empty-state">
                            SYSTEMS_NOMINAL // NO ACTIVE THREATS
                        </div>
                    </template>
                </div>
            </section>

            <!-- PANEL 3: NETWORK TELEMETRY -->
            <section class="wall-panel telemetry">
                <div class="panel-header">
                    <span class="panel-icon">📊</span>
                    <span class="panel-title">SYSTEM_TELEMETRY</span>
                </div>
                <div class="panel-content telemetry-grid">
                    <div class="tel-item">
                        <label>AVG_LATENCY</label>
                        <div class="tel-main">
                            <span class="tel-val">{{ networkStore.metrics.latencyMs.toFixed(1) }}</span>
                            <span class="tel-unit">ms</span>
                        </div>
                        <div class="tel-spark">
                            <div v-for="(v, i) in latencyHistory" :key="i" class="spark-line"
                                :style="{ height: (v / 1.5) + '%' }">
                            </div>
                        </div>
                    </div>
                    <div class="tel-item">
                        <label>PACKET_LOSS</label>
                        <div class="tel-main">
                            <span class="tel-val text-danger">{{ (networkStore.metrics.packetLoss * 100).toFixed(3)
                                }}</span>
                            <span class="tel-unit">%</span>
                        </div>
                        <div class="tel-spark">
                            <div v-for="(v, i) in packetLossHistory" :key="i" class="spark-line danger"
                                :style="{ height: (v * 200) + '%' }"></div>
                        </div>
                    </div>
                    <div class="tel-item">
                        <label>THROUGHPUT</label>
                        <div class="tel-main">
                            <span class="tel-val text-accent">{{ networkStore.bandwidth.totalUsedGbps.toFixed(2)
                                }}</span>
                            <span class="tel-unit">Gbps</span>
                        </div>
                        <div class="tel-spark">
                            <div v-for="(v, i) in throughputHistory" :key="i" class="spark-line accent"
                                :style="{ height: (v / 2) + '%' }"></div>
                        </div>
                    </div>
                    <div class="tel-item">
                        <label>SLA_COMPLIANCE</label>
                        <div class="tel-main">
                            <span class="tel-val text-success">{{ networkStore.metrics.slaCompliance.toFixed(2)
                                }}</span>
                            <span class="tel-unit">%</span>
                        </div>
                        <div class="tel-spark">
                            <div v-for="(v, i) in slaHistory" :key="i" class="spark-line success"
                                :style="{ height: (v - 90) * 10 + '%' }"></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PANEL 4: INFRASTRUCTURE & LOGS -->
            <section class="wall-panel infrastructure">
                <div class="panel-header tab-header">
                    <div class="header-tabs">
                        <button class="tab-btn" :class="{ active: activeTab === 'nodes' }"
                            @click="activeTab = 'nodes'">INFRASTRUCTURE_NODES</button>
                        <button class="tab-btn" :class="{ active: activeTab === 'logs' }"
                            @click="activeTab = 'logs'">SYSTEM_LOGS</button>
                    </div>
                </div>
                <div class="panel-content scrollable">
                    <div v-if="activeTab === 'nodes'" class="infra-grid">
                        <div v-for="room in filteredRooms" :key="room.id" class="room-stat-card"
                            :class="{ 'is-highlighted': highlightedRegion === room.region }">
                            <div class="r-head">
                                <span class="r-name">{{ room.name }}</span>
                                <span class="r-reg">{{ room.region.toUpperCase() }}</span>
                            </div>
                            <div class="r-metrics">
                                <div class="r-met">
                                    <span class="l">LOAD</span>
                                    <span class="v">{{ Math.round(room.currentPower / room.maxPower * 100) }}%</span>
                                </div>
                                <div class="r-met">
                                    <span class="l">TEMP</span>
                                    <span class="v" :class="getTempClass(room.temperature)">{{ room.temperature
                                        }}°C</span>
                                </div>
                                <div class="r-met">
                                    <span class="l">RACKS</span>
                                    <span class="v">{{ room.usedRacks }}/{{ room.maxRacks }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="log-console">
                        <div v-for="log in logStore.logs" :key="log.id" class="log-entry" :class="log.type">
                            <span class="l-time">[{{ log.timestamp }}]</span>
                            <span class="l-type">{{ log.type.toUpperCase() }}:</span>
                            <span class="l-msg">{{ log.message }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="wall-footer">
            <div class="footer-left">
                <span class="label">UPLINK_STATUS:</span>
                <span class="value" :class="{ 'online': gameStore.wsConnected }">{{ gameStore.wsConnected ?
                    'SECURE_LIVE' :
                    'LINK_FAILURE' }}</span>
            </div>
            <div class="footer-center">
                <div class="ticker-wrap">
                    <div class="ticker">
                        <span>{{ currentLog }}</span>
                    </div>
                </div>
            </div>
            <div class="footer-right">
                <span class="label">SESSION_UID:</span>
                <span class="value">{{ player?.id?.toString().slice(-12) || '0000-0000-0000' }}</span>
            </div>
        </footer>

        <!-- DIGITAL RAIN EFFECT -->
        <div class="digital-rain-overlay">
            <div v-for="n in 30" :key="n" class="rain-column" :style="getRainStyle(n)">
                {{ rainChars }}
            </div>
        </div>

        <!-- CRT OVERLAY -->
        <div class="crt-overlay"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useNetworkStore } from '../../stores/network';
import { useEventsStore } from '../../stores/events';
import { useLogStore } from '../../stores/logs';
import { useNetworkMetrics } from '../../composables/useNetworkMetrics';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();
const networkStore = useNetworkStore();
const eventsStore = useEventsStore();
const logStore = useLogStore();

const emit = defineEmits(['close']);

const handleClose = () => {
    SoundManager.playClick();
    emit('close');
};

const {
    latencyHistory,
    packetLossHistory,
    throughputHistory,
    slaHistory,
    startTracking,
    stopTracking
} = useNetworkMetrics(40, 3000);

const isFullscreen = ref(false);
const currentTime = ref('');
const currentLog = ref('SYSTEMS READY. MONITORING GLOBAL TRAFFIC...');
const selectedEvent = ref(null);
const highlightedRegion = ref(null);
const isResolving = ref(false);
const activeTab = ref('nodes');
const rainChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789$#@%&*';

const getRainStyle = (n) => {
    const left = Math.random() * 100;
    const dur = 10 + Math.random() * 20;
    const delay = Math.random() * -20;
    const opacity = 0.05 + Math.random() * 0.1;
    return {
        left: `${left}%`,
        animationDuration: `${dur}s`,
        animationDelay: `${delay}s`,
        opacity
    };
};

const player = computed(() => gameStore.player);
const economy = computed(() => player.value?.economy || {});
const activeEvents = computed(() => eventsStore.activeEvents);
const playerRooms = computed(() => Object.values(gameStore.rooms || {}));

const globalThreatLevel = computed(() => {
    const critical = activeEvents.value.filter(e => e.severity === 'critical').length;
    const high = activeEvents.value.filter(e => e.severity === 'high').length;

    if (critical > 0) return { id: 'critical', label: 'DEFCON_1 // CRITICAL' };
    if (high > 1) return { id: 'high', label: 'DEFCON_2 // ELEVATED' };
    if (activeEvents.value.length > 0) return { id: 'guarded', label: 'DEFCON_3 // GUARDED' };
    return { id: 'nominal', label: 'DEFCON_5 // NOMINAL' };
});

const getRegionCoords = (id) => {
    const coords = {
        'us-east': { x: 250, y: 180 },
        'us-west': { x: 150, y: 190 },
        'eu-central': { x: 500, y: 150 },
        'asia-east': { x: 800, y: 200 },
        'sa-east': { x: 300, y: 350 },
        'af-south': { x: 520, y: 380 }
    };
    return coords[id] || { x: 500, y: 250 };
};

const netIncome = computed(() => {
    return (economy.value.hourlyIncome || 0) - (economy.value.hourlyExpenses || 0);
});

const regions = computed(() => {
    const res = [];
    const activeRegions = new Set();
    playerRooms.value.forEach(room => {
        if (room.region) activeRegions.add(room.region);
    });

    for (const rKey of activeRegions) {
        const meta = gameStore.regions?.[rKey] || { name: rKey.toUpperCase(), flag: '🌐' };
        const roomsInRegion = playerRooms.value.filter(room => room.region === rKey);
        const avgLoad = roomsInRegion.reduce((acc, r) => acc + (r.currentPower / r.maxPower * 100), 0) / roomsInRegion.length;

        res.push({
            id: rKey,
            name: meta.name,
            flag: meta.flag,
            load: Math.round(avgLoad),
            latency: networkStore.metrics.latencyMs + (Math.random() * 5) // Mock regional variance
        });
    }
    return res;
});

const filteredRooms = computed(() => {
    if (!highlightedRegion.value) return playerRooms.value;
    return playerRooms.value.filter(r => r.region === highlightedRegion.value);
});

const formatMoney = (v) => {
    if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M';
    if (v >= 1000) return (v / 1000).toFixed(1) + 'K';
    return Math.floor(v).toLocaleString();
};

const formatRemaining = (event) => {
    if (!event.deadlineAt) return '--:--';
    const total = new Date(event.deadlineAt) - new Date();
    if (total <= 0) return 'EXPIRED';
    const s = Math.floor((total / 1000) % 60);
    const m = Math.floor((total / 1000 / 60) % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const getProgressWidth = (event) => {
    if (!event.warningAt || !event.deadlineAt) return 0;
    const start = new Date(event.warningAt).getTime();
    const end = new Date(event.deadlineAt).getTime();
    const now = Date.now();
    return Math.max(0, Math.min(100, ((now - start) / (end - start)) * 100));
};

const getLoadColor = (l) => {
    if (l > 85) return '#f85149';
    if (l > 60) return '#d29922';
    return '#3fb950';
};

const handleIncidentClick = (event) => {
    selectedEvent.value = event;
};

const handleRegionClick = (regId) => {
    highlightedRegion.value = highlightedRegion.value === regId ? null : regId;
};

const resolveEvent = async (actionId) => {
    if (!selectedEvent.value || isResolving.value) return;
    isResolving.value = true;
    const res = await gameStore.resolveEvent(selectedEvent.value.id, actionId);
    isResolving.value = false;
    if (res.success) {
        selectedEvent.value = null;
    }
};

const getActionClass = (action) => {
    if (action.success_chance < 50) return 'danger';
    if (action.cost > 5000) return 'warning';
    return 'success';
};

const getTempClass = (t) => {
    if (t > 50) return 'text-danger';
    if (t > 35) return 'text-warning';
    return 'text-success';
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
            isFullscreen.value = false;
        }
    }
};

let timer;
watch(activeEvents, (newEvents) => {
    if (selectedEvent.value && !newEvents.find(e => e.id === selectedEvent.value.id)) {
        selectedEvent.value = null;
    }
}, { deep: true });

onMounted(() => {
    startTracking();
    timer = setInterval(() => {
        currentTime.value = new Date().toLocaleTimeString('en-US', { hour12: false });
    }, 1000);

    // Mock ticker
    setInterval(() => {
        if (logStore.logs.length > 0 && Math.random() > 0.5) {
            currentLog.value = logStore.logs[0].message.toUpperCase();
            return;
        }
        const logs = [
            'OPTIMIZING BGP ROUTES VIA AS' + (networkStore.infrastructure.asn || '65001'),
            'CLEANING PACKETS REJECTED BY VPC_' + (networkStore.privateNetworks?.[0]?.name || 'MAIN'),
            'GEOLOCATING TRAFFIC SPIKES IN ' + (regions.value[0]?.name || 'REGION_ALPHA'),
            'SLA COMPLIANCE VERIFIED AGAINST LOGS',
            'REDUNDANCY CHECKS PASSED FOR GLOBAL NODES'
        ];
        currentLog.value = logs[Math.floor(Math.random() * logs.length)];
    }, 5000);
});

onUnmounted(() => {
    stopTracking();
    clearInterval(timer);
});


</script>

<style scoped>
.noc-wall {
    position: fixed;
    inset: 0;
    background: #0d1117;
    color: #e6edf3;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    overflow: hidden;
}

.wall-header {
    height: 80px;
    background: rgba(0, 0, 0, 0.4);
    border-bottom: 2px solid #30363d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 15px;
}

.brand-line {
    width: 4px;
    height: 30px;
    background: #58a6ff;
}

.brand-text {
    font-size: 1.2rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #58a6ff;
}

.global-status {
    display: flex;
    gap: 40px;
    margin-left: 40px;
}

.status-item {
    display: flex;
    flex-direction: column;
}

.status-item .label {
    font-size: 0.6rem;
    color: #8b949e;
    font-weight: 800;
}

.status-item .value {
    font-size: 1.1rem;
    font-weight: 900;
}

.system-clock {
    font-size: 2.5rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    opacity: 0.8;
}

.wall-btn {
    background: transparent;
    border: 1px solid #30363d;
    color: #8b949e;
    padding: 8px 16px;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    margin-left: 10px;
}

.wall-btn:hover {
    border-color: #58a6ff;
    color: #fff;
    background: rgba(88, 166, 255, 0.05);
}

.close-btn:hover {
    border-color: #f85149;
    background: rgba(248, 81, 73, 0.05);
    color: #f85149;
}

.wall-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
    gap: 20px;
    padding: 20px;
}

.wall-panel {
    background: rgba(22, 27, 34, 0.6);
    border: 1px solid #30363d;
    border-top: 3px solid #30363d;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.wall-panel::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 20px 20px;
    pointer-events: none;
}

.panel-header {
    background: rgba(0, 0, 0, 0.3);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid #30363d;
}

.panel-icon {
    font-size: 1.2rem;
}

.panel-title {
    font-size: 0.85rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: #8b949e;
}

.badge {
    background: #f85149;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
}

.panel-content {
    flex: 1;
    padding: 20px;
}

.scrollable {
    overflow-y: auto;
}

/* PANEL: TRAFFIC MAP */
.heatmap-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 15px;
}

.region-node {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px;
    border-radius: 4px;
}

.node-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.node-meta .flag {
    font-size: 1.5rem;
}

.node-meta .name {
    font-weight: 900;
    font-size: 1rem;
}

.node-bars {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bar-group label {
    font-size: 0.5rem;
    opacity: 0.5;
    display: block;
    margin-bottom: 2px;
}

.bar-bg {
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
}

.bar-fill {
    height: 100%;
    transition: width 0.5s;
}

.bar-fill.latency {
    background: #58a6ff;
}

.node-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 0.7rem;
    font-weight: 800;
    opacity: 0.7;
}

/* PANEL: INCIDENTS */
.event-row {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    margin-bottom: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.event-row.severity-critical {
    border-left: 4px solid #f85149;
}

.event-row.severity-high {
    border-left: 4px solid #d29922;
}

.event-row.severity-normal {
    border-left: 4px solid #3fb950;
}

.event-type {
    font-size: 0.7rem;
    font-weight: 900;
    width: 100px;
}

.event-info {
    flex: 1;
}

.event-title {
    font-weight: 800;
    font-size: 1rem;
    margin-bottom: 4px;
}

.event-meta {
    font-size: 0.6rem;
    opacity: 0.5;
}

.event-progress {
    width: 150px;
}

.p-bg {
    height: 6px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 3px;
}

.p-fill {
    height: 100%;
    background: #58a6ff;
}

/* PANEL: TELEMETRY */
.telemetry-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.tel-item {
    background: rgba(0, 0, 0, 0.2);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.tel-item label {
    font-size: 0.65rem;
    color: #8b949e;
    font-weight: 800;
    margin-bottom: 10px;
}

.tel-main {
    display: flex;
    align-items: baseline;
    gap: 5px;
    margin-bottom: 20px;
}

.tel-val {
    font-size: 2rem;
    font-weight: 900;
}

.tel-unit {
    font-size: 0.9rem;
    opacity: 0.5;
}

.tel-spark {
    height: 60px;
    display: flex;
    align-items: flex-end;
    gap: 2px;
}

.spark-line {
    flex: 1;
    background: #58a6ff;
    opacity: 0.6;
}

.spark-line.danger {
    background: #f85149;
}

.spark-line.accent {
    background: #a371f7;
}

.spark-line.success {
    background: #3fb950;
}

/* PANEL: INFRASTRUCTURE */
.infra-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.room-stat-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 15px;
}

.r-head {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 8px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
}

.r-name {
    font-weight: 800;
    font-size: 0.8rem;
}

.r-reg {
    font-size: 0.6rem;
    opacity: 0.5;
}

.r-metrics {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.r-met {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
}

.r-met .l {
    opacity: 0.5;
}

.r-met .v {
    font-weight: 800;
}

/* FOOTER */
.wall-footer {
    height: 50px;
    background: rgba(0, 0, 0, 0.6);
    border-top: 2px solid #30363d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
}

.footer-left,
.footer-right {
    display: flex;
    gap: 10px;
    font-size: 0.7rem;
    font-weight: 800;
}

.footer-left .online {
    color: #3fb950;
}

.label {
    color: #8b949e;
}

.footer-center {
    flex: 1;
    margin: 0 40px;
    overflow: hidden;
}

.ticker-wrap {
    background: rgba(0, 0, 0, 0.3);
    padding: 5px 20px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.ticker {
    font-size: 0.75rem;
    color: #58a6ff;
    font-weight: 900;
    white-space: nowrap;
}

/* Utilities */
.success {
    color: #3fb950;
}

.danger {
    color: #f85149;
}

.text-danger {
    color: #f85149;
}

.text-warning {
    color: #d29922;
}

.text-success {
    color: #3fb950;
}

.text-accent {
    color: #58a6ff;
}

/* Interactive States */
.region-node {
    cursor: pointer;
    transition: transform 0.2s, border-color 0.2s;
}

.region-node:hover {
    border-color: #58a6ff;
    transform: translateY(-2px);
}

.region-node.is-active {
    border-color: #58a6ff;
    background: rgba(88, 166, 255, 0.1);
}

.event-row {
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}

.event-row:hover {
    background: rgba(255, 255, 255, 0.05);
}

.event-row.is-selected {
    border-color: #58a6ff;
    background: rgba(88, 166, 255, 0.05);
}

.room-stat-card.is-highlighted {
    border-color: #58a6ff;
    box-shadow: 0 0 10px rgba(88, 166, 255, 0.2);
}

/* THREAT LEVELS */
.threat-critical {
    color: #f85149;
    text-shadow: 0 0 10px rgba(248, 81, 73, 0.5);
    font-weight: 950 !important;
}

.threat-high {
    color: #d29922;
}

.threat-guarded {
    color: #58a6ff;
}

.threat-nominal {
    color: #3fb950;
}

/* SVG MAP */
.map-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
}

.svg-map-container {
    position: absolute;
    inset: 0;
    opacity: 0.15;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.world-svg {
    width: 100%;
    height: 100%;
}

.map-path {
    fill: none;
    stroke: #30363d;
    stroke-width: 1;
    stroke-dasharray: 4;
}

.map-node {
    fill: #58a6ff;
    stroke: #fff;
    stroke-width: 2;
    pointer-events: auto;
    cursor: pointer;
}

.map-node.active {
    fill: #f85149;
    filter: drop-shadow(0 0 5px #f85149);
}

.flow-line {
    stroke: #58a6ff;
    stroke-width: 1;
    stroke-dasharray: 5;
    animation: flow 2s infinite linear;
    opacity: 0.5;
}

@keyframes flow {
    from {
        stroke-dashoffset: 20;
    }

    to {
        stroke-dashoffset: 0;
    }
}

.overlay-list {
    position: relative;
    z-index: 2;
    pointer-events: none;
}

.overlay-list>* {
    pointer-events: auto;
    background: rgba(13, 17, 23, 0.85) !important;
    backdrop-filter: blur(2px);
}

/* Incident Detail View */
.event-detail-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    gap: 20px;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #30363d;
    padding-bottom: 10px;
}

.back-btn {
    background: transparent;
    border: none;
    color: #58a6ff;
    font-weight: 900;
    font-size: 0.7rem;
    cursor: pointer;
}

.timing {
    font-weight: 900;
    font-size: 0.7rem;
    color: #f85149;
}

.detail-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.type-tag {
    font-size: 0.6rem;
    font-weight: 900;
    background: #30363d;
    padding: 4px 8px;
    border-radius: 4px;
    width: fit-content;
}

.type-tag.severity-critical {
    color: #f85149;
    border: 1px solid #f85149;
}

.type-tag.severity-high {
    color: #d29922;
    border: 1px solid #d29922;
}

.detail-body .title {
    font-size: 1.4rem;
    font-weight: 900;
    margin: 0;
}

.detail-body .desc {
    font-size: 0.85rem;
    color: #8b949e;
    line-height: 1.5;
    margin: 0;
}

.actions-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: auto;
}

.wall-action-btn {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid #30363d;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.wall-action-btn:hover:not(:disabled) {
    border-color: #58a6ff;
    background: rgba(88, 166, 255, 0.05);
}

.wall-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.wall-action-btn .a-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.wall-action-btn .a-label {
    font-weight: 900;
    font-size: 0.9rem;
    color: #fff;
}

.wall-action-btn .a-desc {
    font-size: 0.7rem;
    color: #8b949e;
}

.wall-action-btn .a-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.wall-action-btn .a-cost {
    font-weight: 900;
    color: #d29922;
    font-size: 0.9rem;
}

.wall-action-btn .a-chance {
    font-size: 0.6rem;
    font-weight: 800;
    color: #8b949e;
}

.wall-action-btn.success:hover {
    border-color: #3fb950;
    background: rgba(63, 185, 80, 0.05);
}

.wall-action-btn.warning:hover {
    border-color: #d29922;
    background: rgba(210, 153, 34, 0.05);
}

.wall-action-btn.danger:hover {
    border-color: #f85149;
    background: rgba(248, 81, 73, 0.05);
}

/* TAB SYSTEM */
.tab-header {
    justify-content: flex-start;
    gap: 0;
    padding: 0;
    background: rgba(0, 0, 0, 0.4);
    border-bottom: 2px solid #30363d;
}

.header-tabs {
    display: flex;
    height: 100%;
}

.tab-btn {
    background: transparent;
    border: none;
    border-right: 1px solid #30363d;
    color: #8b949e;
    padding: 12px 24px;
    font-size: 0.75rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: rgba(255, 255, 255, 0.05);
}

.tab-btn.active {
    background: rgba(88, 166, 255, 0.1);
    color: #58a6ff;
    border-bottom: 2px solid #58a6ff;
}

/* LOG CONSOLE */
.log-console {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 0.75rem;
}

.log-entry {
    display: flex;
    gap: 10px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.02);
}

.log-entry.info {
    color: #8b949e;
}

.log-entry.warning {
    color: #d29922;
    background: rgba(210, 153, 34, 0.05);
}

.log-entry.danger {
    color: #f85149;
    background: rgba(248, 81, 73, 0.05);
    font-weight: 900;
}

.log-entry.success {
    color: #3fb950;
}

.l-time {
    color: #58a6ff;
    opacity: 0.6;
    min-width: 80px;
}

.l-type {
    font-weight: 900;
    min-width: 60px;
}

.l-msg {
    flex: 1;
}

/* DIGITAL RAIN */
.digital-rain-overlay {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 1;
    overflow: hidden;
    display: flex;
}

.rain-column {
    position: absolute;
    top: -100%;
    color: #58a6ff;
    font-size: 0.8rem;
    writing-mode: vertical-rl;
    text-orientation: upright;
    animation: rain linear infinite;
    white-space: nowrap;
}

@keyframes rain {
    0% {
        transform: translateY(0);
    }

    100% {
        transform: translateY(200%);
    }
}

/* CRT OVERLAY EFFECTS */
.crt-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%),
        linear-gradient(90deg, rgba(255, 0, 0, 0.03), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.03));
    background-size: 100% 3px, 3px 100%;
    pointer-events: none;
    z-index: 10000;
}

.crt-overlay::after {
    content: " ";
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: rgba(18, 16, 16, 0.03);
    opacity: 0;
    z-index: 10000;
    pointer-events: none;
    animation: flicker 0.15s infinite;
}

.crt-overlay::before {
    content: " ";
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 0%, rgba(32, 32, 32, 0.1) 50%, rgba(18, 16, 16, 0) 100%);
    opacity: 0.1;
    z-index: 10000;
    pointer-events: none;
    animation: scanline 10s linear infinite;
}

@keyframes flicker {
    0% {
        opacity: 0.27861;
    }

    5% {
        opacity: 0.34769;
    }

    10% {
        opacity: 0.23604;
    }

    15% {
        opacity: 0.90626;
    }

    20% {
        opacity: 0.18128;
    }

    /* ... shortened for brevity but still effective ... */
    100% {
        opacity: 0.27861;
    }
}

@keyframes scanline {
    0% {
        transform: translateY(-100%);
    }

    100% {
        transform: translateY(100%);
    }
}

@keyframes v3-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

/* Fullscreen adjustments */
.is-fullscreen {
    padding: 0;
}
</style>
