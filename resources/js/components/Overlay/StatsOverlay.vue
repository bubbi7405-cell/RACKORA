<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="stats-overlay">
            <div class="overlay-header">
                <h2>Performance Analytics</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div> Loading analytics data...
                </div>

                <div v-else-if="history.length === 0" class="empty-state">
                    <p>No historical data available yet.</p>
                    <small>Statistics are recorded every game hour.</small>
                </div>

                <div v-else class="stats-content">
                    <!-- Revenue Chart -->
                    <div class="chart-section">
                        <div class="chart-header">
                            <h3>Revenue Growth</h3>
                            <span class="trend-indicator text-success">
                                <span v-if="revenueTrend > 0">▲ +{{ revenueTrend }}%</span>
                                <span v-else-if="revenueTrend < 0" class="text-danger">▼ {{ revenueTrend }}%</span>
                            </span>
                        </div>
                        <div class="chart-wrapper">
                            <svg viewBox="0 0 500 150" class="line-chart" preserveAspectRatio="none">
                                <!-- Grid -->
                                <line x1="0" y1="37.5" x2="500" y2="37.5" class="grid-line" />
                                <line x1="0" y1="75" x2="500" y2="75" class="grid-line" />
                                <line x1="0" y1="112.5" x2="500" y2="112.5" class="grid-line" />
                                
                                <!-- Line -->
                                <polyline 
                                    :points="getPoints('revenue')" 
                                    fill="none" 
                                    stroke="var(--color-success)" 
                                    stroke-width="2" 
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Customers & Satisfaction -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-title">Active Customers</div>
                            <div class="chart-wrapper small">
                                <svg viewBox="0 0 200 60" class="line-chart" preserveAspectRatio="none">
                                    <polyline 
                                        :points="getPoints('active_customers', 200, 60)" 
                                        fill="none" 
                                        stroke="var(--color-primary)" 
                                        stroke-width="2" 
                                    />
                                </svg>
                            </div>
                            <div class="metric-val">{{ current.active_customers }}</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-title">Avg Satisfaction</div>
                             <div class="chart-wrapper small">
                                <svg viewBox="0 0 200 60" class="line-chart" preserveAspectRatio="none">
                                    <polyline 
                                        :points="getPoints('avg_satisfaction', 200, 60)" 
                                        fill="none" 
                                        stroke="var(--color-warning)" 
                                        stroke-width="2" 
                                    />
                                </svg>
                            </div>
                            <div class="metric-val">{{ parseFloat(current.avg_satisfaction).toFixed(1) }}%</div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-title">Reputation</div>
                            <div class="chart-wrapper small">
                                <svg viewBox="0 0 200 60" class="line-chart" preserveAspectRatio="none">
                                    <polyline 
                                        :points="getPoints('reputation', 200, 60)" 
                                        fill="none" 
                                        stroke="var(--color-secondary)" 
                                        stroke-width="2" 
                                    />
                                </svg>
                            </div>
                            <div class="metric-val">{{ parseFloat(current.reputation).toFixed(1) }}</div>
                        </div>
                    </div>
                    
                    <div class="data-table-section">
                        <h3>Recent History</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tick</th>
                                    <th>Revenue</th>
                                    <th>Expenses</th>
                                    <th>Customers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="point in history.slice(0, 5)" :key="point.id">
                                    <td>{{ point.tick }}</td>
                                    <td class="text-success">${{ parseFloat(point.revenue).toFixed(2) }}</td>
                                    <td class="text-danger">-${{ parseFloat(point.expenses).toFixed(2) }}</td>
                                    <td>{{ point.active_customers }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../utils/api';

const emit = defineEmits(['close']);
const loading = ref(true);
const history = ref([]);

const current = computed(() => history.value[history.value.length - 1] || {});

const revenueTrend = computed(() => {
    if (history.value.length < 2) return 0;
    const curr = parseFloat(current.value.revenue);
    const prev = parseFloat(history.value[history.value.length - 2].revenue);
    if (prev === 0) return 0;
    return Math.round(((curr - prev) / prev) * 100);
});

onMounted(async () => {
    try {
        const response = await api.get('/stats/history?limit=50');
        if (response.success) {
            history.value = response.data;
        }
    } catch (e) {
        console.error('Stats load failed', e);
    } finally {
        loading.value = false;
    }
});

function getPoints(key, width = 500, height = 150) {
    if (!history.value.length) return '';
    
    const values = history.value.map(h => parseFloat(h[key] || 0));
    const max = Math.max(...values);
    const min = Math.min(...values);
    const range = max - min || 1;
    
    // Normalize to height
    // Invert Y because SVG 0 is top
    return values.map((val, i) => {
        const x = (i / (values.length - 1)) * width;
        const normalizedVal = (val - min) / range;
        const y = height - (normalizedVal * (height * 0.8)) - (height * 0.1); // 10% padding
        
        // Handle NaN/Infinity
        const safeX = isFinite(x) ? x : 0;
        const safeY = isFinite(y) ? y : height;
        
        return `${safeX},${safeY}`;
    }).join(' ');
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stats-overlay {
    width: 800px;
    max-width: 95vw;
    height: 80vh;
    background: var(--color-bg-medium, #0d1117);
    border: 1px solid var(--color-border, #30363d);
    box-shadow: 0 0 50px rgba(0,0,0,0.6);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
}

.overlay-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--color-border, #30363d);
    background: var(--color-bg-elevated, #161b22);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.overlay-header h2 { margin: 0; color: var(--color-text-primary, #e6edf3); font-size: 1.5rem; }

.close-btn {
    background: none;
    border: none;
    color: var(--color-text-muted, #8b949e);
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #fff; }

.overlay-body {
    flex: 1;
    overflow-y: auto;
    padding: 25px;
}

.loading-state, .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--color-text-muted, #8b949e);
}

.chart-section {
    background: var(--color-bg-elevated, #161b22);
    border: 1px solid var(--color-border, #30363d);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.chart-wrapper {
    width: 100%;
    overflow: hidden;
}

.line-chart {
    width: 100%;
    height: 100%;
    min-height: 150px;
}

.grid-line {
    stroke: var(--color-border, #30363d);
    stroke-dasharray: 4 4;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.metric-card {
    background: var(--color-bg-elevated, #161b22);
    border: 1px solid var(--color-border, #30363d);
    padding: 15px;
    border-radius: 8px;
}

.metric-title {
    font-size: 0.8rem;
    color: var(--color-text-muted, #8b949e);
    margin-bottom: 10px;
}

.metric-val {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text-primary, #fff);
    font-family: monospace;
}

.chart-wrapper.small {
    height: 60px;
    margin-bottom: 10px;
    opacity: 0.7;
}

.data-table-section h3 {
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table th {
    text-align: left;
    padding: 10px;
    border-bottom: 2px solid var(--color-border, #30363d);
    color: var(--color-text-muted, #8b949e);
}

.data-table td {
    padding: 10px;
    border-bottom: 1px solid var(--color-border, #30363d);
    font-family: monospace;
}

.text-success { color: var(--color-success, #22c55e); }
.text-danger { color: var(--color-danger, #ef4444); }
</style>
