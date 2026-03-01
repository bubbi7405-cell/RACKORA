<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="stats-overlay glass-panel animation-fade-in">
            <div class="overlay-header">
                <div class="header-tabs">
                    <button 
                        class="tab-btn" 
                        :class="{ 'active': activeTab === 'analytics' }"
                        @click="activeTab = 'analytics'"
                    >
                        📈 Analytics
                    </button>
                    <button 
                        class="tab-btn" 
                        :class="{ 'active': activeTab === 'log' }"
                        @click="activeTab = 'log'"
                    >
                        📜 Security & Ops Log
                    </button>
                    <button 
                        class="tab-btn" 
                        :class="{ 'active': activeTab === 'customers' }"
                        @click="activeTab = 'customers'"
                    >
                        👥 Clients
                    </button>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div v-if="loading" class="loading-state">
                <div class="spinner"></div>
                <p>Synchronizing data nodes...</p>
            </div>

            <div v-else class="overlay-body">
                <!-- ANALYTICS TAB -->
                <div v-if="activeTab === 'analytics'">
                    <div v-if="history.length === 0" class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>Diagnostic Data Unavailable</h3>
                        <p>System snapshots are recorded every game hour. Please allow time for the first data cycle to complete.</p>
                        <button class="btn-refresh" @click="fetchStats">Re-Check Link</button>
                    </div>

                    <div v-else>
                        <!-- Top Summary Cards -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <label>Current Balance</label>
                                <div class="value">${{ formatMoney(gameStore.player?.economy?.balance) }}</div>
                                <div class="sub" :class="gameStore.player?.economy?.netIncomePerHour >= 0 ? 'text-success' : 'text-danger'">
                                    {{ gameStore.player?.economy?.netIncomePerHour >= 0 ? '+' : '' }}${{ formatMoney(gameStore.player?.economy?.netIncomePerHour) }}/hr
                                </div>
                            </div>
                            <div class="summary-card">
                                <label>Business Reputation</label>
                                <div class="value">{{ Math.round(gameStore.player?.economy?.reputation) }}/100</div>
                                <div class="progress-mini">
                                    <div class="fill" :style="{ width: gameStore.player?.economy?.reputation + '%' }"></div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <label>SLA Uptime</label>
                                <div class="value">{{ (gameStore.stats?.uptime || 100).toFixed(2) }}%</div>
                                <div class="sub text-muted">{{ gameStore.stats?.onlineServers || 0 }} / {{ gameStore.stats?.totalServers || 0 }} Servers Online</div>
                            </div>
                        </div>

                        <!-- Reputation Profile -->
                        <div class="rep-profile-grid">
                            <div class="rep-card" v-for="(val, key) in specializedRep" :key="key">
                                <div class="rep-header">
                                    <div class="rep-icon" :style="{ color: getRepColor(key) }">{{ getRepIcon(key) }}</div>
                                    <div class="rep-label">{{ formatRepLabel(key) }}</div>
                                    <div class="rep-val">{{ Math.round(val) }}</div>
                                </div>
                                <div class="rep-bar">
                                    <div class="rep-fill" :style="{ width: val + '%', background: getRepColor(key) }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Revenue Chart -->
                        <div class="chart-main">
                            <div class="chart-header">
                                <h3>Net Revenue History (Last 50 Ticks)</h3>
                                <div class="trend" :class="revenueTrend >= 0 ? 'text-success' : 'text-danger'">
                                    {{ revenueTrend >= 0 ? '▲' : '▼' }} {{ Math.abs(revenueTrend) }}%
                                </div>
                            </div>
                            <div class="chart-container">
                                <svg viewBox="0 0 800 200" preserveAspectRatio="none" class="svg-chart">
                                    <defs>
                                        <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="var(--color-success)" stop-opacity="0.3" />
                                            <stop offset="100%" stop-color="var(--color-success)" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>
                                    <line v-for="i in 4" :key="i" x1="0" :y1="i * 40" x2="800" :y2="i * 40" class="chart-grid" />
                                    <path :d="getAreaPath('revenue', 800, 200)" fill="url(#revenueGradient)" />
                                    <path :d="getLinePath('revenue', 800, 200)" fill="none" stroke="var(--color-success)" stroke-width="3" stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>

                        <!-- Secondary Metrics -->
                        <div class="metrics-row">
                            <div class="metric-block">
                                <div class="block-header">
                                    <span class="label">Customer Satisfaction</span>
                                    <span class="value">{{ Math.round(current.avg_satisfaction) }}%</span>
                                </div>
                                <div class="sparkline-container">
                                    <svg viewBox="0 0 200 50" preserveAspectRatio="none">
                                        <path :d="getLinePath('avg_satisfaction', 200, 50)" fill="none" stroke="var(--color-warning)" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                            <div class="metric-block">
                                <div class="block-header">
                                    <span class="label">Operating Expenses</span>
                                    <span class="value text-danger">-${{ formatMoney(current.expenses) }}</span>
                                </div>
                                <div class="sparkline-container">
                                    <svg viewBox="0 0 200 50" preserveAspectRatio="none">
                                        <path :d="getLinePath('expenses', 200, 50)" fill="none" stroke="var(--color-danger)" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LOG TAB -->
                <div v-else-if="activeTab === 'log'" class="log-section">
                    <div v-if="eventHistory.length === 0" class="empty-state">
                        <div class="empty-icon">📅</div>
                        <h3>No Historical Logs</h3>
                        <p>Past incidents and system events will be archived here once resolved.</p>
                    </div>

                    <div v-else class="event-log">
                        <div v-for="event in eventHistory" :key="event.id" class="log-entry" :class="event.status">
                            <div class="entry-time">{{ formatTime(event.resolved_at) }}</div>
                            <div class="entry-header">
                                <span class="entry-severity" :class="event.severity">{{ event.severity }}</span>
                                <span class="entry-title">{{ event.title }}</span>
                            </div>
                            <p class="entry-msg">{{ event.description }}</p>
                            <div class="entry-outcome" v-if="event.status === 'resolved'">
                                <span class="badge success">RESOLVED</span>
                                <span class="outcome-msg">{{ event.resolution_message }}</span>
                            </div>
                            <div class="entry-outcome" v-else-if="event.status === 'failed'">
                                <span class="badge danger">FAILED</span>
                                <span class="outcome-msg">System recovery failed. Consequential damage recorded.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CUSTOMERS TAB -->
                <div v-else-if="activeTab === 'customers'" class="customers-section">
                    <div v-if="!gameStore.customers || gameStore.customers.length === 0" class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3>No Active Clients</h3>
                        <p>Market to build your reputation and attract your first corporate contracts.</p>
                    </div>

                    <div v-else class="customer-grid">
                        <div v-for="customer in gameStore.customers" :key="customer.id" class="customer-card">
                            <div class="customer-card__header">
                                <div class="customer-card__title">
                                    <h4>{{ customer.companyName }}</h4>
                                    <span class="customer-card__tier">{{ customer.tier }} client</span>
                                </div>
                                <div class="customer-card__revenue">${{ formatMoney(customer.actualRevenue) }}/mo</div>
                            </div>
                            
                            <div class="customer-card__body">
                                <div class="stat-row">
                                    <label>Satisfaction</label>
                                    <span :class="getSatisfactionColorClass(customer.satisfaction)">{{ Math.round(customer.satisfaction) }}%</span>
                                </div>
                                <div class="progress-mini">
                                    <div class="fill" :class="getSatisfactionColorClass(customer.satisfaction)" :style="{ width: customer.satisfaction + '%' }"></div>
                                </div>
                                
                                <div class="stat-row mt-sm">
                                    <label>Preferred Region</label>
                                    <span class="region-info">
                                        {{ getRegionFlag(customer.preferences?.target_region) }}
                                        {{ customer.preferences?.target_region || 'Any' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Table (Always shown at bottom of analytics) -->
                <div v-if="activeTab === 'analytics' && history.length > 0" class="history-table-container">
                    <div class="table-header">
                        <h3>Tick-by-Tick History</h3>
                    </div>
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Cycle (Tick)</th>
                                <th>Hourly Revenue</th>
                                <th>Operation Costs</th>
                                <th>Net Profit</th>
                                <th>Cust. Base</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="entry in history.slice(0, 15)" :key="entry?.id || Math.random()">
                                <tr v-if="entry">
                                    <td class="tick">#{{ entry.tick }}</td>
                                    <td class="text-success">+ ${{ formatMoney(entry.revenue) }}</td>
                                    <td class="text-danger">- ${{ formatMoney(entry.expenses) }}</td>
                                    <td class="font-bold" :class="(entry.revenue - entry.expenses) >= 0 ? 'text-success' : 'text-danger'">
                                        ${{ formatMoney(entry.revenue - entry.expenses) }}
                                    </td>
                                    <td>{{ entry.active_customers }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const emit = defineEmits(['close']);
const gameStore = useGameStore();

const loading = ref(true);
const activeTab = ref('analytics');
const history = ref([]);
const eventHistory = ref([]);

const current = computed(() => history.value[0] || {}); 

onMounted(() => {
    fetchStats();
});

async function fetchStats() {
    loading.value = true;
    try {
        const [statsRes, eventsRes] = await Promise.all([
            api.get('/stats/history?limit=50'),
            api.get('/events/history?limit=20')
        ]);

        if (statsRes.success) history.value = statsRes.data;
        if (eventsRes.success) eventHistory.value = eventsRes.data;
    } catch (e) {
        console.error('Stats/Events load failed', e);
    } finally {
        loading.value = false;
    }
}

function formatTime(timestamp) {
    if (!timestamp) return '---';
    const d = new Date(timestamp);
    return isNaN(d.getTime()) ? '---' : d.toLocaleTimeString('de-DE');
}

const revenueTrend = computed(() => {
    if (history.value.length < 2) return 0;
    const curr = parseFloat(history.value[0].revenue);
    const prev = parseFloat(history.value[1].revenue);
    if (prev === 0) return 0;
    return Math.round(((curr - prev) / prev) * 100);
});

const specializedRep = computed(() => gameStore.player?.economy?.specializedReputation || {});

function getRepIcon(key) {
    const map = {
        'budget': '📦',
        'premium': '💎',
        'hpc': '⚡',
        'green': '🌱',
        'tech_debt': '🏚️'
    };
    return map[key] || '📊';
}

function getRepColor(key) {
    const map = {
        'budget': '#f85149',
        'premium': '#d29922',
        'hpc': '#a371f7',
        'green': '#3fb950',
        'tech_debt': '#ff0000' // Pure red for bad
    };
    return map[key] || '#8b949e';
}

function formatRepLabel(key) {
    const map = {
        'budget': 'Budget / Value',
        'premium': 'Premium Enterprise',
        'hpc': 'HPC & AI Research',
        'green': 'Eco / Sustainable',
        'tech_debt': 'Technical Debt (Wear)'
    };
    return map[key] || key;
}

function formatMoney(value) {
    if (value === undefined || value === null) return '0.00';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* ... existing functions ... */
function getLinePath(key, width, height) {
    if (!history.value.length) return '';
    
    // We want chronolocial for chart: oldest to newest
    const points = [...history.value].reverse();
    const values = points.map(h => parseFloat(h[key] || 0));
    
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;

    return values.map((val, i) => {
        const x = (i / (values.length - 1)) * width;
        const normalizedVal = (val - min) / range;
        const y = height - (normalizedVal * (height * 0.8)) - (height * 0.1);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');
}

function getAreaPath(key, width, height) {
    const linePath = getLinePath(key, width, height);
    if (!linePath) return '';
    
    return `${linePath} L ${width} ${height} L 0 ${height} Z`;
}

function getRegionFlag(regionKey) {
    if (!regionKey) return '';
    return gameStore.regions[regionKey]?.flag || '❓';
}

function getSatisfactionColorClass(val) {
    if (val >= 80) return 'text-success';
    if (val >= 50) return 'text-warning';
    return 'text-danger';
}
</script>

<style scoped>
/* ... existing styles ... */
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(12px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-overlay {
    width: 900px;
    max-width: 95vw;
    height: 85vh;
    border-radius: 20px;
    display: flex;
    flex-direction: column;
    color: #fff;
    overflow: hidden;
    position: relative;
    /* ensure consistent bg */
    background: #0d1117; 
}

/* Reputation Profile Grid */
.rep-profile-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.rep-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 12px;
}

.rep-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.rep-icon { font-size: 1.2rem; display: flex; align-items: center; justify-content: center; width: 24px; }
.rep-label { font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; font-weight: 700; }
.rep-val { font-family: var(--font-family-mono); font-weight: 700; font-size: 0.9rem; }

.rep-bar {
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
    overflow: hidden;
}

.rep-fill {
    height: 100%;
    transition: width 0.5s ease-out;
}

/* ... existing styles continuation ... */
.overlay-header {
    padding: 25px 35px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-title h2 {
    margin: 0;
    font-size: 1.6rem;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.icon {
    font-size: 1.8rem;
}

.overlay-body {
    flex: 1;
    overflow-y: auto;
    padding: 35px;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    position: relative;
}

.summary-card label {
    display: block;
    font-size: 0.75rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    margin-bottom: 5px;
}

.summary-card .value {
    font-size: 1.6rem;
    font-weight: 800;
    font-family: var(--font-family-mono);
}

.summary-card .sub {
    font-size: 0.85rem;
    margin-top: 5px;
}

.progress-mini {
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}

.progress-mini .fill {
    height: 100%;
    background: var(--color-primary);
    box-shadow: 0 0 10px var(--color-primary);
}

/* Main Chart */
.chart-main {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-header h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--color-text-secondary);
}

.chart-container {
    height: 200px;
    width: 100%;
}

.svg-chart {
    width: 100%;
    height: 100%;
}

.chart-grid {
    stroke: rgba(255, 255, 255, 0.05);
    stroke-width: 1;
}

/* Metrics Row */
.metrics-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.metric-block {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 20px;
}

.block-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.block-header .label {
    font-size: 0.85rem;
    color: var(--color-text-muted);
}

.block-header .value {
    font-weight: 700;
    font-family: var(--font-family-mono);
}

.sparkline-container {
    height: 50px;
    opacity: 0.6;
}

/* Table */
.analytics-table {
    width: 100%;
    border-collapse: collapse;
}

.analytics-table th {
    text-align: left;
    padding: 12px;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--color-text-muted);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.analytics-table td {
    padding: 12px;
    font-size: 0.9rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.analytics-table tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

.analytics-table td.tick {
    color: var(--color-primary);
    font-weight: 700;
}

.loading-state, .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.spinner {
    width: 40px; height: 40px;
    border: 3px solid rgba(255,255,255,0.1);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

.empty-icon { font-size: 4rem; opacity: 0.2; margin-bottom: 20px; }

.btn-refresh {
    margin-top: 20px;
    padding: 10px 25px;
    background: var(--color-primary);
    color: #000;
    border-radius: 6px;
    font-weight: 800;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Tabs */
.header-tabs {
    display: flex;
    gap: 10px;
}

.tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: var(--color-text-muted);
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.tab-btn:hover {
    background: rgba(255, 255, 255, 0.05);
}

.tab-btn.active {
    background: var(--color-primary);
    color: #000;
    border-color: var(--color-primary);
    box-shadow: 0 0 15px rgba(var(--color-primary-rgb), 0.3);
}

/* Event Log */
.event-log {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.log-entry {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-left: 3px solid #666;
    border-radius: 8px;
    padding: 15px;
    position: relative;
}

.log-entry.resolved { border-left-color: var(--color-success); }
.log-entry.failed { border-left-color: var(--color-danger); }

.entry-time {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 0.75rem;
    font-family: var(--font-family-mono);
    color: var(--color-text-muted);
}

.entry-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.entry-severity {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.1);
}

.entry-severity.critical, .entry-severity.catastrophic { background: var(--color-danger); color: #fff; }
.entry-severity.warning { background: var(--color-warning); color: #000; }
.entry-severity.info { background: var(--color-primary); color: #000; }

.entry-title {
    font-weight: 700;
    font-size: 1rem;
}

.entry-msg {
    margin: 0 0 12px 0;
    font-size: 0.9rem;
    color: var(--color-text-secondary);
    line-height: 1.4;
}

.entry-outcome {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.badge {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
}

.badge.success { background: rgba(var(--color-success-rgb), 0.1); color: var(--color-success); border: 1px solid var(--color-success); }
.badge.danger { background: rgba(var(--color-danger-rgb), 0.1); color: var(--color-danger); border: 1px solid var(--color-danger); }

.outcome-msg {
    font-size: 0.85rem;
    font-style: italic;
    color: var(--color-text-muted);
}

/* Customers Section */
.customer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.customer-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.customer-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: var(--color-primary);
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.customer-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.customer-card__title h4 {
    margin: 0;
    font-size: 1.1rem;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.customer-card__tier {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.customer-card__revenue {
    font-family: var(--font-family-mono);
    font-weight: 700;
    color: var(--color-success);
    font-size: 0.9rem;
}

.customer-card__body .stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    margin-bottom: 6px;
}

.customer-card__body .stat-row label {
    color: var(--color-text-muted);
}

.customer-card__body .progress-mini {
    height: 6px;
    background: rgba(255, 255, 255, 0.05);
    margin-bottom: 15px;
}

.fill.text-success { background: var(--color-success); box-shadow: 0 0 10px rgba(var(--color-success-rgb), 0.5); }
.fill.text-warning { background: var(--color-warning); box-shadow: 0 0 10px rgba(var(--color-warning-rgb), 0.5); }
.fill.text-danger { background: var(--color-danger); box-shadow: 0 0 10px rgba(var(--color-danger-rgb), 0.5); }

.mt-sm { margin-top: 15px; }

.region-info {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-secondary);
}

.text-success { color: var(--color-success) !important; }
.text-danger { color: var(--color-danger) !important; }
.text-warning { color: var(--color-warning) !important; }
.text-muted { color: var(--color-text-muted) !important; }
.font-bold { font-weight: 700; }

.animation-fade-in { animation: fade-in 0.5s ease-out; }

@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
