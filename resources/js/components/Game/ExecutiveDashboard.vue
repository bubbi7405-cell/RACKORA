<template>
    <div class="executive-dashboard">
        <!-- TOP KPI GRID -->
        <div class="kpi-grid">
            <div class="kpi-card" v-for="kpi in kpis" :key="kpi.label">
                <div class="kpi-icon">{{ kpi.icon }}</div>
                <div class="kpi-data">
                    <span class="label">{{ kpi.label }}</span>
                    <div class="value-row">
                        <span class="value" :class="kpi.trend">{{ kpi.value }}</span>
                        <span class="trend-indicator" v-if="kpi.trend === 'up'">▲</span>
                        <span class="trend-indicator" v-if="kpi.trend === 'down'">▼</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-body">
            <!-- LEFT COLUMN: SYSTEM STATUS -->
            <div class="status-column">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h4>INFRASTRUCTURE_OVERVIEW</h4>
                    </div>
                    <div class="room-status-list">
                        <div v-for="room in playerRooms" :key="room.id" class="mini-room-card">
                            <div class="room-info">
                                <span class="room-name">{{ room.name }}</span>
                                <span class="room-region">{{ room.region.toUpperCase() }}</span>
                            </div>
                            <div class="room-metrics">
                                <div class="metric">
                                    <label>LOAD</label>
                                    <div class="mini-bar">
                                        <div class="fill" :style="{ width: (room.currentPower / room.maxPower * 100) + '%' }"></div>
                                    </div>
                                </div>
                                <div class="metric">
                                    <label>TEMP</label>
                                    <span :class="getTempClass(room.temperature)">{{ room.temperature }}°C</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h4>ACTIVE_STRATEGIC_POLICIES</h4>
                    </div>
                    <div class="policy-list">
                        <div v-for="(val, key) in economy.strategic_policies" :key="key" class="policy-item">
                            <span class="p-key">{{ key.replace('_', ' ').toUpperCase() }}</span>
                            <span class="p-val">{{ val.toUpperCase() }}</span>
                        </div>
                        <div v-if="Object.keys(economy.strategic_policies || {}).length === 0" class="no-policies">
                            NO ACTIVE POLICIES DEFINED
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: RECENT INCIDENTS & NEWS -->
            <div class="intel-column">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h4>RECENT_INCIDENTS</h4>
                        <span class="sub">LAST_24_TICK</span>
                    </div>
                    <div class="incident-summary">
                        <div v-for="log in recentLogs" :key="log.id" class="mini-log-item" :class="log.type">
                            <span class="t-stamp">[{{ formatTime(log.created_at) }}]</span>
                            <span class="msg">{{ log.message }}</span>
                        </div>
                        <div v-if="recentLogs.length === 0" class="no-incidents">
                            SYSTEMS_STABLE: NO RECENT INCIDENTS
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h4>SLA_PERFORMANCE</h4>
                    </div>
                    <div class="sla-container">
                        <div class="sla-circle">
                            <svg viewBox="0 0 36 36" class="circular-chart">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="circle" :stroke-dasharray="uptimePercent + ', 100'" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                            <div class="percentage">{{ uptimePercent }}%</div>
                        </div>
                        <div class="sla-meta">
                            <div class="sla-label">GLOBAL_UPTIME</div>
                            <div class="sla-desc">Target: 99.9%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useNetworkStore } from '../../stores/network';
import api from '../../utils/api';

const gameStore = useGameStore();
const networkStore = useNetworkStore();
const logs = ref([]);

const economy = computed(() => gameStore.player?.economy || {});
const playerRooms = computed(() => gameStore.rooms || []);

const kpis = computed(() => [
    { label: 'NET_INCOM_EST', value: '$' + formatValue(economy.value.hourlyIncome - economy.value.hourlyExpenses), icon: '💰', trend: economy.value.hourlyIncome > economy.value.hourlyExpenses ? 'up' : 'down' },
    { label: 'REPUTATION', value: Math.round(economy.value.reputation || 0) + '%', icon: '📈', trend: 'neutral' },
    { label: 'ENTITY_LEVEL', value: 'RANK_' + (economy.value.level || 1), icon: '🏆', trend: 'neutral' },
    { label: 'CASH_RESERVE', value: '$' + formatValue(economy.value.balance), icon: '🏛️', trend: 'neutral' }
]);

const uptimePercent = computed(() => networkStore.metrics.slaCompliance.toFixed(2));

const recentLogs = computed(() => {
    return logs.value.slice(0, 5);
});

const formatValue = (v) => {
    if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M';
    if (v >= 1000) return (v / 1000).toFixed(1) + 'K';
    return Math.floor(v).toLocaleString();
};

const formatTime = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const getTempClass = (t) => {
    if (t > 50) return 'text-danger';
    if (t > 35) return 'text-warning';
    return 'text-success';
};

onMounted(async () => {
    try {
        const response = await api.get('/game/logs?limit=10');
        if (response.success) {
            logs.value = response.data;
        }
    } catch (e) {
        console.error(e);
    }
});
</script>

<style scoped>
.executive-dashboard {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 0 5px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.kpi-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    border-radius: 4px;
}

.kpi-icon {
    font-size: 1.5rem;
    opacity: 0.8;
}

.kpi-data {
    display: flex;
    flex-direction: column;
}

.kpi-data .label {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    letter-spacing: 1px;
}

.value-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.value {
    font-size: 1.2rem;
    font-weight: 900;
    font-family: var(--font-mono);
}

.trend-indicator {
    font-size: 0.8rem;
}

.value.up { color: var(--v3-success); }
.value.down { color: var(--v3-danger); }

.dashboard-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.status-column, .intel-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.dashboard-section {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 4px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 10px;
}

.section-header h4 {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 2px;
}

.section-header .sub {
    font-size: 0.55rem;
    color: var(--v3-text-ghost);
}

.room-status-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mini-room-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 2px;
}

.room-info {
    display: flex;
    flex-direction: column;
}

.room-name { font-size: 0.75rem; font-weight: bold; }
.room-region { font-size: 0.55rem; color: var(--v3-text-ghost); }

.room-metrics {
    display: flex;
    gap: 20px;
    align-items: center;
}

.metric {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.metric label {
    font-size: 0.5rem;
    color: var(--v3-text-ghost);
    margin-bottom: 2px;
}

.mini-bar {
    width: 60px;
    height: 3px;
    background: rgba(255, 255, 255, 0.05);
}

.fill { height: 100%; background: var(--v3-accent); }

.policy-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.policy-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    padding: 4px 8px;
    background: rgba(0, 242, 255, 0.05);
    border-left: 2px solid var(--v3-accent);
}

.p-key { font-weight: bold; color: var(--v3-text-ghost); }
.p-val { color: var(--v3-accent); font-weight: 800; }

.incident-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mini-log-item {
    font-size: 0.65rem;
    padding: 6px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.01);
    display: flex;
    gap: 10px;
}

.mini-log-item.danger { color: #f87171; border-left: 2px solid #ef4444; }
.mini-log-item.success { color: #4ade80; border-left: 2px solid #22c55e; }
.mini-log-item.warning { color: #fbbf24; border-left: 2px solid #f59e0b; }

.t-stamp { font-family: var(--font-mono); opacity: 0.5; }

.sla-container {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 10px 0;
}

.sla-circle {
    width: 80px;
    height: 80px;
    position: relative;
}

.circular-chart {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    max-height: 100%;
}

.circle-bg { fill: none; stroke: rgba(255, 255, 255, 0.05); stroke-width: 2.8; }
.circle { fill: none; stroke: var(--v3-success); stroke-width: 2.8; stroke-linecap: round; }

.percentage {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1rem;
    font-weight: 900;
    font-family: var(--font-mono);
}

.sla-meta {
    display: flex;
    flex-direction: column;
}

.sla-label { font-size: 0.8rem; font-weight: 800; letter-spacing: 1px; }
.sla-desc { font-size: 0.6rem; color: var(--v3-text-ghost); }

.no-incidents, .no-policies {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    opacity: 0.5;
    text-align: center;
    padding: 20px;
}
</style>
