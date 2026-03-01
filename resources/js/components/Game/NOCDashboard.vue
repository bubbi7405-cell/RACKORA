<template>
    <div class="noc-dashboard">
        <!-- Sidebar: Active Incidents -->
        <div class="noc-sidebar">
            <div class="noc-panel-header">
                <span class="header-icon">🚨</span>
                <span class="header-title">ACTIVE_INCIDENTS</span>
                <span class="active-count">{{ activeEvents.length }}</span>
            </div>

            <div class="incident-list">
                <div 
                    v-for="event in activeEvents" 
                    :key="event.id" 
                    class="incident-item"
                    :class="['severity-' + event.severity, { 'selected': selectedEvent?.id === event.id }]"
                    @click="selectedEvent = event"
                >
                    <div class="incident-item__header">
                        <span class="incident-type">{{ event.typeLabel }}</span>
                        <span class="incident-timer">{{ formatRemaining(event) }}</span>
                    </div>
                    <div class="incident-title">{{ event.title }}</div>
                    <div class="incident-progress">
                        <div class="progress-bar" :style="{ width: getProgressWidth(event) + '%' }"></div>
                    </div>
                </div>

                <div v-if="activeEvents.length === 0" class="noc-empty">
                    <div class="empty-icon">🛡️</div>
                    <div class="empty-text">SYSTEMS_NOMINAL</div>
                    <div class="empty-sub">Monitor telemetry for anomalies.</div>
                </div>
            </div>
        </div>

        <!-- Main Content: Telemetry & Command -->
        <div class="noc-main">
            <!-- Header Telemetry Row -->
            <div class="noc-telemetry-row">
                <div class="telemetry-card">
                    <div class="tel-label">AVG_LATENCY</div>
                    <div class="tel-value">{{ latency?.toFixed(1) || '0.0' }}ms</div>
                    <div class="tel-chart">
                        <!-- Simplified Sparkline -->
                        <div v-for="(v, i) in 20" :key="i" class="spark-bar" :style="{ height: (Math.random() * 40 + 10) + '%' }"></div>
                    </div>
                </div>
                <div class="telemetry-card">
                    <div class="tel-label">PACKET_LOSS</div>
                    <div class="tel-value text-danger">{{ packetLossPercent?.toFixed(2) || '0.00' }}%</div>
                    <div class="tel-chart">
                         <div v-for="(v, i) in 20" :key="i" class="spark-bar" :style="{ height: (Math.random() * 10 + (packetLossPercent > 0 ? 30 : 5)) + '%' }"></div>
                    </div>
                </div>
                <div class="telemetry-card">
                    <div class="tel-label">THROUGHPUT</div>
                    <div class="tel-value text-accent">{{ bandwidthGbps?.toFixed(1) || '0.0' }} Gbps</div>
                    <div class="tel-chart">
                         <div v-for="(v, i) in 20" :key="i" class="spark-bar" :style="{ height: (Math.random() * 60 + 20) + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Focus Area -->
            <div class="noc-focus">
                <div v-if="selectedEvent" class="event-details">
                    <div class="details-header">
                        <div class="details-type" :class="'severity-' + selectedEvent.severity">{{ selectedEvent.typeLabel }}</div>
                        <h2 class="details-title">{{ selectedEvent.title }}</h2>
                        <p class="details-desc">{{ selectedEvent.description }}</p>
                    </div>

                    <div class="impact-stats">
                        <div class="stat">
                            <span class="label">AFFECTED_CUSTOMERS</span>
                            <span class="value">{{ selectedEvent.affected_customers_count }}</span>
                        </div>
                        <div class="stat" v-if="selectedEvent.affected_server_id">
                            <span class="label">TARGET_UID</span>
                            <span class="value">{{ selectedEvent.affected_server_id.toString().slice(-8) }}</span>
                        </div>
                    </div>

                    <div class="action-grid">
                        <div 
                            v-for="action in selectedEvent.available_actions" 
                            :key="action.id"
                            class="action-card"
                            :class="{ 'disabled': isResolving || !canAfford(action) }"
                            @click="handleAction(action)"
                        >
                            <div class="action-top">
                                <span class="action-label">{{ action.label }}</span>
                                <span class="action-cost">${{ action.cost.toLocaleString() }}</span>
                            </div>
                            <p class="action-desc">{{ action.description }}</p>
                            <div class="action-meta">
                                <span class="chance">{{ action.success_chance }}% Success Chance</span>
                                <span class="duration">{{ action.duration }}s Execution</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="noc-landing">
                    <div class="grid-background"></div>
                    <div class="landing-content">
                        <div class="pulse-ring"></div>
                        <h3>NETWORK_OPERATIONS_CENTER</h3>
                        <p>Awaiting incident trigger. All regional nodes verifying integrity.</p>
                        
                        <div class="regional-status">
                            <div v-for="reg in regions" :key="reg.id" class="region-pip">
                                <span class="pip-dot" :class="reg.status"></span>
                                <span class="pip-name">{{ reg.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useNetworkStore } from '../../stores/network';
import { useInfrastructureStore } from '../../stores/infrastructure';
import { useEventsStore } from '../../stores/events';
import { storeToRefs } from 'pinia';
import { useNetworkMetrics } from '../../composables/useNetworkMetrics';
import api from '../../utils/api';
import { useToastStore } from '../../stores/toast';

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();
const eventsStore = useEventsStore();
const networkStore = useNetworkStore();
const toastStore = useToastStore();

// Replace storeToRefs
const activeEvents = computed(() => eventsStore.activeEvents);
const player = computed(() => gameStore.player);

const { latencyHistory, packetLossHistory, throughputHistory, latency, packetLossPercent, bandwidthGbps } = useNetworkMetrics();

console.log('[NOCDashboard] Hooking into metrics...', { latency: latency.value });

const selectedEvent = ref(null);
const isResolving = ref(false);

const regions = computed(() => {
    const activeRegions = new Set();
    Object.values(gameStore.rooms || {}).forEach(room => {
        if (room.region) activeRegions.add(room.region);
    });

    return Array.from(activeRegions).map(regionKey => {
        const meta = gameStore.regions?.[regionKey] || { name: regionKey.toUpperCase() };
        return {
            id: regionKey,
            name: meta.name || regionKey,
            status: 'online' // Default to online if we have rooms there
        };
    });
});

const canAfford = (action) => {
    return (player.value?.economy?.balance || 0) >= action.cost;
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
    const pct = ((now - start) / (end - start)) * 100;
    return Math.max(0, Math.min(100, pct));
};

const handleAction = async (action) => {
    if (isResolving.value || !canAfford(action)) return;
    
    isResolving.value = true;
    try {
        const response = await api.post(`/events/${selectedEvent.value.id}/resolve`, {
            action_id: action.id
        });
        
        if (response.success) {
            toastStore.add(`Action Executed: ${action.label}`, 'success');
            // Store will be updated via partial state or next polling
            if (response.resolution === 'success') {
                selectedEvent.value = null;
            }
        } else {
            toastStore.add(response.error || 'Action Failed', 'error');
        }
    } catch (e) {
        toastStore.add('Link failure to NOC command server', 'error');
    } finally {
        isResolving.value = false;
    }
};

// Auto-select first event if none selected
onMounted(() => {
    if (activeEvents.value.length > 0 && !selectedEvent.value) {
        selectedEvent.value = activeEvents.value[0];
    }
});

</script>

<style scoped>
.noc-dashboard {
    display: flex;
    height: 100%;
    background: var(--v3-bg-dark);
    gap: 1px;
}

.noc-sidebar {
    width: 320px;
    background: var(--v3-bg-surface);
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--v3-border-soft);
}

.noc-panel-header {
    padding: 16px;
    background: rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--v3-border-soft);
}

.header-title {
    font-weight: 800;
    letter-spacing: 0.1em;
    font-size: 0.85rem;
}

.active-count {
    margin-left: auto;
    background: var(--v3-accent);
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 900;
}

.incident-list {
    flex: 1;
    overflow-y: auto;
}

.incident-item {
    padding: 16px;
    border-bottom: 1px solid var(--v3-border-soft);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.incident-item:hover {
    background: rgba(255,255,255,0.03);
}

.incident-item.selected {
    background: rgba(var(--v3-accent-rgb), 0.1);
    border-left: 3px solid var(--v3-accent);
}

.incident-item__header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.incident-type {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    opacity: 0.7;
}

.incident-timer {
    font-family: var(--v3-font-mono);
    font-size: 0.75rem;
}

.incident-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.incident-progress {
    height: 4px;
    background: rgba(0,0,0,0.3);
    border-radius: 2px;
    margin-top: 8px;
}

.progress-bar {
    height: 100%;
    background: var(--v3-accent);
    transition: width 1s linear;
}

.severity-critical .progress-bar { background: var(--v3-danger); }
.severity-high .progress-bar { background: var(--v3-warning); }

.noc-empty {
    padding: 60px 20px;
    text-align: center;
    opacity: 0.3;
}

.empty-icon { font-size: 3rem; margin-bottom: 12px; }
.empty-text { font-weight: 800; letter-spacing: 0.2em; margin-bottom: 4px; }
.empty-sub { font-size: 0.7rem; }

.noc-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 24px;
    gap: 24px;
    overflow-y: auto;
}

.noc-telemetry-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.telemetry-card {
    background: var(--v3-bg-surface);
    border: 1px solid var(--v3-border-soft);
    padding: 16px;
    border-radius: 4px;
}

.tel-label {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--v3-text-secondary);
    margin-bottom: 4px;
}

.tel-value {
    font-size: 1.5rem;
    font-weight: 900;
    font-family: var(--v3-font-mono);
    margin-bottom: 12px;
}

.tel-chart {
    height: 40px;
    display: flex;
    align-items: flex-end;
    gap: 2px;
}

.spark-bar {
    flex: 1;
    background: var(--v3-accent);
    opacity: 0.5;
}

.noc-focus {
    flex: 1;
    min-height: 400px;
}

.event-details {
    background: var(--v3-bg-surface);
    border: 1px solid var(--v3-border-soft);
    border-radius: 8px;
    padding: 32px;
    height: 100%;
}

.details-type {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 16px;
    background: rgba(255,255,255,0.1);
}

.severity-critical { color: var(--v3-danger); border: 1px solid var(--v3-danger); }

.details-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 12px;
}

.details-desc {
    font-size: 1.1rem;
    color: var(--v3-text-secondary);
    max-width: 800px;
    margin-bottom: 32px;
    line-height: 1.6;
}

.impact-stats {
    display: flex;
    gap: 40px;
    margin-bottom: 40px;
    padding: 20px;
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
}

.stat .label { display: block; font-size: 0.65rem; color: var(--v3-text-secondary); margin-bottom: 4px; }
.stat .value { font-size: 1.2rem; font-weight: 800; font-family: var(--v3-font-mono); }

.action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.action-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--v3-border-soft);
    padding: 20px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-card:hover:not(.disabled) {
    background: rgba(var(--v3-accent-rgb), 0.1);
    border-color: var(--v3-accent);
    transform: translateY(-2px);
}

.action-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.action-label { font-weight: 800; font-size: 1rem; }
.action-cost { color: var(--v3-accent); font-weight: 900; }

.action-desc {
    font-size: 0.85rem;
    color: var(--v3-text-secondary);
    margin-bottom: 16px;
    min-height: 40px;
}

.action-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    font-weight: 700;
    opacity: 0.6;
}

.disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

.noc-landing {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.grid-background {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), 
                      linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(circle, black, transparent 80%);
}

.landing-content {
    text-align: center;
    z-index: 1;
}

.pulse-ring {
    width: 100px;
    height: 100px;
    border: 2px solid var(--v3-accent);
    border-radius: 50%;
    margin: 0 auto 32px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(1.5); opacity: 0; }
}

.landing-content h3 { letter-spacing: 0.4em; font-weight: 900; margin-bottom: 12px; }
.landing-content p { color: var(--v3-text-secondary); font-size: 0.8rem; margin-bottom: 40px; }

.regional-status {
    display: flex;
    justify-content: center;
    gap: 24px;
}

.region-pip { display: flex; align-items: center; gap: 8px; }
.pip-dot { width: 8px; height: 8px; border-radius: 50%; }
.pip-dot.online { background: var(--v3-success); box-shadow: 0 0 5px var(--v3-success); }
.pip-name { font-size: 0.6rem; font-weight: 800; opacity: 0.5; }

</style>
