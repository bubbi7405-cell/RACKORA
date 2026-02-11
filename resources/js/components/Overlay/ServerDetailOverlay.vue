<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="server-detail-overlay glass-panel animation-slide-up" v-if="server">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">💻</span>
                    <h2>{{ server.modelName }} <small>#{{ server.id.substring(0, 8) }}</small></h2>
                </div>
                <div class="header-actions">
                    <div class="status-indicator" :class="server.status">
                        {{ server.status }}
                    </div>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </div>
            </div>

            <div class="overlay-tabs">
                <button 
                    v-for="tab in ['Summary', 'Performance', 'Logs']" 
                    :key="tab"
                    @click="activeTab = tab"
                    :class="{ active: activeTab === tab }"
                >{{ tab }}</button>
            </div>

            <div class="overlay-body">
                <!-- Summary Tab -->
                <div v-if="activeTab === 'Summary'" class="tab-content summary-tab">
                    <div class="info-grid">
                        <div class="info-group">
                            <label>Hardware Specs</label>
                            <div class="spec-list">
                                <div class="spec-item"><span>CPU</span> <strong>{{ server.specs.cpuCores }} Cores</strong></div>
                                <div class="spec-item"><span>RAM</span> <strong>{{ server.specs.ramGb }} GB</strong></div>
                                <div class="spec-item"><span>Disk</span> <strong>{{ server.specs.storageTb }} TB</strong></div>
                                <div class="spec-item"><span>Net</span> <strong>{{ server.specs.bandwidthMbps }} Mbps</strong></div>
                            </div>
                        </div>
                        <div class="info-group">
                            <label>Maintenance</label>
                            <div class="health-display">
                                <div class="health-meta">
                                    <span>System Health</span>
                                    <strong>{{ Math.round(server.health) }}%</strong>
                                </div>
                                <div class="health-bar-container">
                                    <div class="health-bar" :style="{ width: server.health + '%' }" :class="healthClass"></div>
                                </div>
                                <div v-if="server.currentFault" class="fault-badge">
                                    DETECTED FAULT: {{ server.currentFault }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="orders-section" v-if="server.activeOrdersCount > 0">
                        <label>Active Workloads</label>
                        <div class="workload-list">
                            <!-- In a real app we'd fetch the actual order details, but we can simulate a list -->
                            <div class="workload-item" v-for="i in server.activeOrdersCount" :key="i">
                                <span class="dot"></span> Instance #{{ 100 + i }} - Active
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Tab -->
                <div v-if="activeTab === 'Performance'" class="tab-content performance-tab">
                    <div class="chart-container">
                        <label>CPU Utilization (last 20m)</label>
                        <div class="sparkline-wrapper">
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                                <path :d="generateSparkline(metrics, 'cpu')" fill="rgba(0, 242, 255, 0.1)" stroke="var(--color-primary)" stroke-width="0.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="chart-container">
                        <label>RAM Usage (last 20m)</label>
                        <div class="sparkline-wrapper">
                            <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                                <path :d="generateSparkline(metrics, 'ram')" fill="rgba(187, 134, 252, 0.1)" stroke="#bb86fc" stroke-width="0.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div v-if="activeTab === 'Logs'" class="tab-content logs-tab">
                    <div class="terminal">
                        <div v-for="(log, i) in logs" :key="i" class="log-line" :class="log.level">
                            <span class="ts">[{{ log.timestamp.substring(11, 19) }}]</span>
                            <span class="msg">{{ log.message }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overlay-actions footer">
                <div class="left-actions">
                    <button class="btn-diagnose" :disabled="server.isDiagnosed || processing" @click="runDiagnostics">
                        🔍 {{ server.isDiagnosed ? 'Diagnosed' : 'Run Diagnostics' }}
                    </button>
                </div>
                <div class="right-actions">
                    <template v-if="server.status === 'online' || server.status === 'degraded'">
                        <button 
                            class="btn-maintenance" 
                            :disabled="processing" 
                            @click="startMaintenance"
                        >
                             📅 Maintenance (${{ maintenanceCost }})
                        </button>
                    </template>
                    <template v-if="server.status === 'damaged' || server.status === 'degraded'">
                        <button class="btn-repair" :disabled="processing" @click="repairServer">
                             🔧 Repair (${{ repairCost }})
                        </button>
                    </template>
                    
                    <button v-if="server.status === 'online'" class="btn-off" @click="powerToggle">Power Off</button>
                    <button v-else-if="server.status !== 'maintenance'" class="btn-on" @click="powerToggle">Power On</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const props = defineProps({
    serverId: { type: String, required: true }
});

const gameStore = useGameStore();
const activeTab = ref('Summary');
const server = ref(null);
const metrics = ref([]);
const logs = ref([]);
const processing = ref(false);

const loadDetails = async () => {
    try {
        const response = await api.get(`/server/${props.serverId}/details`);
        if (response.success) {
            server.value = response.data.server;
            metrics.value = response.data.metrics;
            logs.value = response.data.logs;
        }
    } catch (e) {
        console.error('Failed to load server details', e);
    }
};

onMounted(loadDetails);

// Sparkline generation logic
const generateSparkline = (data, key) => {
    if (data.length === 0) return '';
    const maxVal = 100;
    const points = data.map((d, i) => {
        const x = (i / (data.length - 1)) * 100;
        const y = 30 - (d[key] / maxVal) * 30;
        return `${x},${y}`;
    });
    
    // Close the path for fill
    return `M 0,30 L ${points.join(' L ')} L 100,30 Z`;
};

const healthClass = computed(() => {
    if (!server.value) return '';
    if (server.value.health > 70) return 'good';
    if (server.value.health > 30) return 'warn';
    return 'danger';
});

const repairCost = computed(() => {
    if (!server.value) return 0;
    let cost = server.value.purchaseCost * 0.2;
    if (server.value.isDiagnosed) cost *= 0.5;
    return Math.round(cost);
});

const maintenanceCost = computed(() => {
    if (!server.value) return 0;
    return Math.round(server.value.purchaseCost * 0.05);
});

const runDiagnostics = async () => {
    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/diagnose`);
        if (response.success) {
            server.value = response.server;
            loadDetails(); // reload to get diagnostic logs
        }
    } finally {
        processing.value = false;
    }
};

const repairServer = async () => {
    processing.value = true;
    try {
        const response = await api.post('/server/repair', { server_id: props.serverId });
        if (response.success) {
            server.value = response.data;
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const startMaintenance = async () => {
    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/maintenance`);
        if (response.success) {
            server.value = response.data;
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const powerToggle = async () => {
    if (server.value.status === 'online') {
        await gameStore.powerOffServer(server.value.id);
    } else {
        await gameStore.powerOnServer(server.value.id);
    }
    loadDetails();
};

</script>

<style scoped>
.server-detail-overlay {
    width: 750px;
    max-width: 95vw;
    background: var(--color-bg-light);
    border-radius: 12px;
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border);
}

.header-title h2 { margin: 0; font-size: 1.2rem; }
.header-title small { font-size: 0.8rem; opacity: 0.5; font-family: monospace; }

.status-indicator {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    background: #333;
}
.status-indicator.online { color: var(--color-success); border: 1px solid var(--color-success); }
.status-indicator.offline { color: var(--color-text-muted); border: 1px solid var(--color-border); }
.status-indicator.damaged, .status-indicator.degraded { color: var(--color-danger); border: 1px solid var(--color-danger); }

.overlay-tabs {
    display: flex;
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid var(--color-border);
}

.overlay-tabs button {
    flex: 1;
    padding: 12px;
    background: transparent;
    border: none;
    color: var(--color-text-muted);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}

.overlay-tabs button.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: rgba(var(--color-primary-rgb), 0.05);
}

.overlay-body {
    padding: 25px;
    height: 350px;
    overflow-y: auto;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: 15px;
    letter-spacing: 1px;
}

.spec-list {
    display: flex;
    flex-direction: column;
    gap : 8px;
}

.spec-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.health-display {
    background: rgba(0,0,0,0.2);
    padding: 15px;
    border-radius: 8px;
}

.health-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.health-bar-container {
    height: 8px;
    background: #111;
    border-radius: 4px;
    overflow: hidden;
}

.health-bar {
    height: 100%;
    transition: width 0.5s ease;
}
.health-bar.good { background: var(--color-success); }
.health-bar.warn { background: var(--color-warning); }
.health-bar.danger { background: var(--color-danger); }

.fault-badge {
    margin-top: 15px;
    font-size: 0.8rem;
    color: var(--color-danger);
    font-weight: 700;
    padding: 8px;
    background: rgba(var(--color-danger-rgb), 0.1);
    border: 1px solid var(--color-danger);
    border-radius: 4px;
}

.workload-list {
    background: rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px;
}

.workload-item {
    font-size: 0.85rem;
    padding: 5px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-container {
    margin-bottom: 30px;
}

.sparkline-wrapper {
    height: 80px;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 5px;
}

.sparkline-wrapper svg {
    width: 100%;
    height: 100%;
}

.terminal {
    background: #050505;
    padding: 15px;
    border-radius: 8px;
    height: 100%;
    overflow-y: auto;
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 0.8rem;
    border: 1px solid #222;
}

.log-line {
    margin-bottom: 4px;
}
.log-line.error { color: #f44336; }
.log-line.warn { color: #ff9800; }
.log-line.info { color: #4caf50; }

.ts { opacity: 0.4; margin-right: 10px; }

.footer {
    padding: 20px;
    background: rgba(0,0,0,0.1);
    border-top: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
}

.right-actions {
    display: flex;
    gap: 10px;
}

.btn-diagnose {
    background: rgba(var(--color-primary-rgb), 0.1);
    color: var(--color-primary);
    border: 1px solid var(--color-primary);
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 700;
}

.btn-repair {
    background: var(--color-success);
    color: #000;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 700;
}

.btn-maintenance {
    background: rgba(var(--color-primary-rgb), 0.2);
    color: var(--color-primary);
    border: 1px solid var(--color-primary);
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 700;
}

.btn-maintenance:hover { background: var(--color-primary); color: #000; }

.btn-off { background: #555; border: none; padding: 8px 15px; border-radius: 6px; color: #fff; }
.btn-on { background: var(--color-primary); border: none; padding: 8px 15px; border-radius: 6px; color: #000; }

.animation-slide-up {
    animation: slide-up 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slide-up {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
