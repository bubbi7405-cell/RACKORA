<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="dashboard-overlay glass-panel animation-fade-in">
            <div class="header-section">
                <div class="header-badge">EXECUTIVE BRIEFING</div>
                <h1>Company Performance</h1>
                <p class="description">Comprehensive operational intelligence and corporate health assessment.</p>
                <div class="close-btn" @click="$emit('close')">×</div>
            </div>

            <div v-if="loading" class="loading-state">
                <div class="spinner"></div>
                <p>Compiling executive report...</p>
            </div>

            <div v-else-if="report" class="dashboard-body">
                <!-- COMPANY RATING HERO -->
                <div class="rating-hero">
                    <div class="rating-card">
                        <div class="rating-letter" :class="'rating--' + ratingTier">
                            {{ report.companyRating.letter }}
                        </div>
                        <div class="rating-meta">
                            <div class="rating-label">COMPANY RATING</div>
                            <div class="rating-score">{{ report.companyRating.score }}%</div>
                            <div class="rating-outlook" :class="'outlook--' + report.companyRating.outlook">
                                Outlook: {{ report.companyRating.outlook }}
                            </div>
                        </div>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-val">${{ formatNum(report.financial.balance) }}</div>
                            <div class="hero-label">Net Worth</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-val">{{ report.reputation }}%</div>
                            <div class="hero-label">Reputation</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-val">Lvl {{ report.level }}</div>
                            <div class="hero-label">CEO Level</div>
                        </div>
                    </div>
                </div>

                <!-- METRICS GRID -->
                <div class="metrics-grid">
                    <!-- Financial Health -->
                    <div class="metric-panel">
                        <div class="panel-header">
                            <span class="panel-icon">💰</span>
                            <h3>Financial Health</h3>
                            <span class="panel-badge" :class="report.financial.profitMargin > 0 ? 'badge-good' : 'badge-bad'">
                                {{ report.financial.profitMargin > 0 ? 'PROFITABLE' : 'BURNING' }}
                            </span>
                        </div>
                        <div class="finance-grid">
                            <div class="finance-item">
                                <div class="fi-label">Hourly Income</div>
                                <div class="fi-value text-success">${{ formatNum(report.financial.hourlyIncome) }}</div>
                            </div>
                            <div class="finance-item">
                                <div class="fi-label">Hourly Expenses</div>
                                <div class="fi-value text-danger">${{ formatNum(report.financial.hourlyExpenses) }}</div>
                            </div>
                            <div class="finance-item">
                                <div class="fi-label">Profit Margin</div>
                                <div class="fi-value" :class="report.financial.profitMargin >= 0 ? 'text-success' : 'text-danger'">
                                    {{ report.financial.profitMargin }}%
                                </div>
                            </div>
                            <div class="finance-item">
                                <div class="fi-label">Revenue Trend</div>
                                <div class="fi-value" :class="report.financial.revenueTrend >= 0 ? 'text-success' : 'text-danger'">
                                    {{ report.financial.revenueTrend >= 0 ? '▲' : '▼' }} {{ Math.abs(report.financial.revenueTrend) }}%
                                </div>
                            </div>
                        </div>
                        <div v-if="report.financial.cashRunway !== null" class="runway-warning">
                            ⚠ Cash Runway: {{ report.financial.cashRunway }} hours until bankrupt
                        </div>

                        <!-- Mini Revenue Chart -->
                        <div class="mini-chart" v-if="report.financial.balanceHistory.length > 2">
                            <svg viewBox="0 0 300 60" preserveAspectRatio="none" class="sparkline-svg">
                                <defs>
                                    <linearGradient id="balGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="var(--color-primary)" stop-opacity="0.3"/>
                                        <stop offset="100%" stop-color="var(--color-primary)" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path :d="getAreaPath(report.financial.balanceHistory, 300, 60)" fill="url(#balGrad)" />
                                <path :d="getLinePath(report.financial.balanceHistory, 300, 60)" fill="none" stroke="var(--color-primary)" stroke-width="2" />
                            </svg>
                            <div class="chart-label">Balance History</div>
                        </div>
                    </div>

                    <!-- Resource Operations (NEW) -->
                    <div class="metric-panel full-width">
                        <div class="panel-header">
                            <span class="panel-icon">⚡</span>
                            <h3>Grid & Network Operations</h3>
                            <span class="panel-badge badge-neutral">LIVE MONITORING</span>
                        </div>
                        <div class="resource-grid">
                            <!-- Power -->
                            <div class="res-card">
                                <div class="res-meta">
                                    <span class="res-label">Power Load</span>
                                    <span class="res-val text-warning">{{ formatNum(report.resources.currentPower) }} kW</span>
                                </div>
                                <div class="mini-chart">
                                    <svg viewBox="0 0 300 60" preserveAspectRatio="none" class="sparkline-svg">
                                        <defs>
                                            <linearGradient id="pwrGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#d29922" stop-opacity="0.3"/>
                                                <stop offset="100%" stop-color="#d29922" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path :d="getAreaPath(report.resources.powerHistory, 300, 60)" fill="url(#pwrGrad)" />
                                        <path :d="getLinePath(report.resources.powerHistory, 300, 60)" fill="none" stroke="#d29922" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                            <!-- Bandwidth -->
                            <div class="res-card">
                                <div class="res-meta">
                                    <span class="res-label">Network Traffic</span>
                                    <span class="res-val text-primary">{{ formatNum(report.resources.currentBandwidth) }} Gbps</span>
                                </div>
                                <div class="mini-chart">
                                    <svg viewBox="0 0 300 60" preserveAspectRatio="none" class="sparkline-svg">
                                        <defs>
                                            <linearGradient id="bwGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#58a6ff" stop-opacity="0.3"/>
                                                <stop offset="100%" stop-color="#58a6ff" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path :d="getAreaPath(report.resources.bandwidthHistory, 300, 60)" fill="url(#bwGrad)" />
                                        <path :d="getLinePath(report.resources.bandwidthHistory, 300, 60)" fill="none" stroke="#58a6ff" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compliance & Safety (NEW) -->
                    <div class="metric-panel">
                        <div class="panel-header">
                            <span class="panel-icon">⚖️</span>
                            <h3>Compliance & Safety</h3>
                            <button class="btn-panel-action" @click="$emit('openCompliance')">MANAGE AUDITS</button>
                        </div>
                        <div class="compliance-scores">
                            <div class="c-score-item">
                                <div class="c-score-val" :class="getScoreClass(report.compliance.securityScore)">
                                    {{ report.compliance.securityScore }}%
                                </div>
                                <div class="c-score-label">Security Posture</div>
                            </div>
                            <div class="c-score-item">
                                <div class="c-score-val" :class="getScoreClass(report.compliance.privacyScore)">
                                    {{ report.compliance.privacyScore }}%
                                </div>
                                <div class="c-score-label">Privacy (GDPR)</div>
                            </div>
                        </div>
                        <div class="compliance-hint">
                            Certifications unlock high-value Enterprise contracts.
                        </div>
                    </div>

                    <!-- Crisis Management -->
                    <div class="metric-panel">
                        <div class="panel-header">
                            <span class="panel-icon">🛡️</span>
                            <h3>Crisis Management</h3>
                            <span class="panel-badge" :class="crisisRatingClass">
                                {{ crisisRatingLabel }}
                            </span>
                        </div>
                        <div v-if="report.crisisManagement.totalEvents > 0">
                            <div class="crisis-score-ring">
                                <svg viewBox="0 0 120 120" class="ring-svg">
                                    <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="8"/>
                                    <circle cx="60" cy="60" r="50" fill="none" :stroke="crisisScoreColor"
                                        stroke-width="8" stroke-linecap="round"
                                        :stroke-dasharray="crisisScoreDash" stroke-dashoffset="0"
                                        transform="rotate(-90 60 60)" class="score-ring-fill"/>
                                </svg>
                                <div class="ring-center">
                                    <div class="ring-value">{{ report.crisisManagement.avgScore }}</div>
                                    <div class="ring-label">AVG</div>
                                </div>
                            </div>
                            <div class="grade-bar">
                                <div v-for="(count, grade) in report.crisisManagement.gradeDistribution" :key="grade"
                                    class="grade-segment" :class="'grade--' + grade"
                                    v-show="count > 0"
                                    :title="grade + ': ' + count">
                                    <span class="grade-letter">{{ grade }}</span>
                                    <span class="grade-count">{{ count }}</span>
                                </div>
                            </div>
                            <div class="crisis-stats">
                                <span>{{ report.crisisManagement.resolved }} resolved</span>
                                <span class="text-danger" v-if="report.crisisManagement.failed > 0">{{ report.crisisManagement.failed }} failed</span>
                            </div>
                        </div>
                        <div v-else class="no-data-state">
                            <div class="no-data-icon">🌤️</div>
                            <p>No incidents recorded yet. Calm before the storm.</p>
                        </div>
                    </div>

                    <!-- Customer Health -->
                    <div class="metric-panel">
                        <div class="panel-header">
                            <span class="panel-icon">👥</span>
                            <h3>Customer Health</h3>
                            <span class="panel-badge" :class="report.customerHealth.satisfactionRate >= 80 ? 'badge-good' : 'badge-warning'">
                                {{ report.customerHealth.satisfactionRate }}% HAPPY
                            </span>
                        </div>
                        <div class="customer-metrics">
                            <div class="cust-big-stat">
                                <div class="cust-big-val">{{ report.customerHealth.total }}</div>
                                <div class="cust-big-label">Active Clients</div>
                            </div>
                            <div class="cust-details">
                                <div class="cust-row">
                                    <span class="cust-label">Avg. Satisfaction</span>
                                    <span class="cust-value">{{ report.customerHealth.avgSatisfaction }}%</span>
                                </div>
                                <div class="cust-row">
                                    <span class="cust-label">Unhappy Clients</span>
                                    <span class="cust-value text-warning">{{ report.customerHealth.unhappy }}</span>
                                </div>
                                <div class="cust-row">
                                    <span class="cust-label">Recent Churn</span>
                                    <span class="cust-value text-danger">{{ report.customerHealth.recentChurn }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Satisfaction Bar -->
                        <div class="sat-bar">
                            <div class="sat-fill" :style="{ width: report.customerHealth.satisfactionRate + '%' }"
                                :class="satBarClass"></div>
                        </div>
                    </div>

                    <!-- Infrastructure -->
                    <div class="metric-panel">
                        <div class="panel-header">
                            <span class="panel-icon">🖥️</span>
                            <h3>Infrastructure</h3>
                            <span class="panel-badge" :class="report.infrastructure.uptime >= 95 ? 'badge-good' : 'badge-warning'">
                                {{ report.infrastructure.uptime }}% UP
                            </span>
                        </div>
                        <div class="infra-grid">
                            <div class="infra-item">
                                <div class="infra-icon online">●</div>
                                <div class="infra-info">
                                    <div class="infra-val">{{ report.infrastructure.online }}</div>
                                    <div class="infra-label">Online</div>
                                </div>
                            </div>
                            <div class="infra-item">
                                <div class="infra-icon degraded">●</div>
                                <div class="infra-info">
                                    <div class="infra-val">{{ report.infrastructure.degraded }}</div>
                                    <div class="infra-label">Degraded</div>
                                </div>
                            </div>
                            <div class="infra-item">
                                <div class="infra-icon total">●</div>
                                <div class="infra-info">
                                    <div class="infra-val">{{ report.infrastructure.totalServers }}</div>
                                    <div class="infra-label">Total Fleet</div>
                                </div>
                            </div>
                            <div class="infra-item">
                                <div class="infra-icon health">♥</div>
                                <div class="infra-info">
                                    <div class="infra-val">{{ report.infrastructure.avgHealth }}%</div>
                                    <div class="infra-label">Avg Health</div>
                                </div>
                            </div>
                        </div>

                        <!-- SLA Compliance -->
                        <div class="sla-section">
                            <div class="sla-header">
                                <span>SLA Compliance</span>
                                <span class="sla-rate" :class="report.sla.complianceRate >= 99 ? 'text-success' : 'text-warning'">
                                    {{ report.sla.complianceRate }}%
                                </span>
                            </div>
                            <div class="sla-bar">
                                <div class="sla-fill" :style="{ width: report.sla.complianceRate + '%' }"></div>
                            </div>
                            <div class="sla-meta">
                                {{ report.sla.activeContracts }} contracts
                                <span class="text-danger" v-if="report.sla.breaches > 0"> · {{ report.sla.breaches }} breaches</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="error-state">
                <p>Failed to load performance data. Please try again.</p>
                <button class="btn-retry" @click="fetchReport">Retry</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../../utils/api';

const emit = defineEmits(['close', 'openCompliance']);

const loading = ref(true);
const report = ref(null);
let pollTimer = null;

const getScoreClass = (score) => {
    if (score >= 80) return 'text-success';
    if (score >= 50) return 'text-warning';
    return 'text-danger';
};

const fetchReport = async () => {
    // Only show loading on initial fetch
    if (!report.value) loading.value = true;
    
    try {
        const res = await api.get('/performance/report');
        if (res.success) {
            report.value = res.data;
        }
    } catch (e) {
        console.error('Failed to load performance report', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchReport();
    pollTimer = setInterval(fetchReport, 2000); // Poll every 2s for live feel
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// --- Company Rating Tier (for CSS class) ---
const ratingTier = computed(() => {
    if (!report.value) return 'low';
    const letter = report.value.companyRating.letter;
    if (['AAA', 'AA'].includes(letter)) return 'elite';
    if (['A', 'BBB'].includes(letter)) return 'good';
    if (['BB', 'B'].includes(letter)) return 'mid';
    return 'low';
});

// --- Crisis Rating ---
const crisisRatingClass = computed(() => {
    if (!report.value || report.value.crisisManagement.totalEvents === 0) return 'badge-neutral';
    const avg = report.value.crisisManagement.avgScore;
    if (avg >= 80) return 'badge-good';
    if (avg >= 50) return 'badge-warning';
    return 'badge-bad';
});

const crisisRatingLabel = computed(() => {
    if (!report.value || report.value.crisisManagement.totalEvents === 0) return 'NO DATA';
    const avg = report.value.crisisManagement.avgScore;
    if (avg >= 80) return 'EXCELLENT';
    if (avg >= 60) return 'ADEQUATE';
    if (avg >= 40) return 'POOR';
    return 'FAILING';
});

const crisisScoreColor = computed(() => {
    if (!report.value) return '#555';
    const avg = report.value.crisisManagement.avgScore;
    if (avg >= 80) return '#3fb950';
    if (avg >= 60) return '#58a6ff';
    if (avg >= 40) return '#d29922';
    return '#f85149';
});

const crisisScoreDash = computed(() => {
    if (!report.value) return '0 314';
    const pct = (report.value.crisisManagement.avgScore || 0) / 100;
    const circumference = 2 * Math.PI * 50; // ~314
    return `${pct * circumference} ${circumference}`;
});

// --- Satisfaction Bar ---
const satBarClass = computed(() => {
    if (!report.value) return '';
    const rate = report.value.customerHealth.satisfactionRate;
    if (rate >= 80) return 'sat-good';
    if (rate >= 50) return 'sat-warning';
    return 'sat-danger';
});

// --- Formatting ---
const formatNum = (val) => {
    if (val === undefined || val === null) return '0';
    if (Math.abs(val) >= 1000000) return (val / 1000000).toFixed(1) + 'M';
    if (Math.abs(val) >= 1000) return (val / 1000).toFixed(1) + 'K';
    return val.toFixed(0);
};

// --- Chart Helpers ---
const getLinePath = (data, w, h) => {
    if (!data || data.length < 2) return '';
    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;
    const step = w / (data.length - 1);

    return data.map((val, i) => {
        const x = i * step;
        const y = h - ((val - min) / range) * (h - 4) - 2;
        return `${i === 0 ? 'M' : 'L'}${x},${y}`;
    }).join(' ');
};

const getAreaPath = (data, w, h) => {
    if (!data || data.length < 2) return '';
    const linePath = getLinePath(data, w, h);
    const step = w / (data.length - 1);
    const lastX = (data.length - 1) * step;
    return `${linePath} L${lastX},${h} L0,${h} Z`;
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.88); backdrop-filter: blur(10px);
    z-index: 4000;
    display: flex; align-items: center; justify-content: center;
}

.dashboard-overlay {
    width: 1200px; max-width: 96vw; height: 90vh;
    background: #080b12; border: 1px solid rgba(255,255,255,0.06);
    border-radius: 20px; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 0 80px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.04);
}

/* HEADER */
.header-section {
    padding: 28px 40px 20px;
    background: linear-gradient(180deg, rgba(88, 166, 255, 0.04) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    position: relative;
}

.header-badge {
    display: inline-block; background: rgba(88, 166, 255, 0.12); color: #58a6ff;
    padding: 3px 12px; border-radius: 4px; font-size: 0.65rem; font-weight: 800;
    letter-spacing: 2.5px; margin-bottom: 10px; border: 1px solid rgba(88, 166, 255, 0.2);
}

h1 { font-size: 1.8rem; margin: 0; color: #fff; letter-spacing: -0.5px; }
.description { color: #484f58; font-size: 0.9rem; margin-top: 4px; }

.close-btn {
    position: absolute; top: 28px; right: 40px; font-size: 2.5rem;
    cursor: pointer; color: #30363d; transition: 0.2s; line-height: 1;
}
.close-btn:hover { color: #fff; transform: scale(1.1); }

/* LOADING */
.loading-state {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 20px; color: #484f58;
}
.spinner {
    width: 36px; height: 36px; border: 3px solid rgba(255,255,255,0.06);
    border-top-color: #58a6ff; border-radius: 50%; animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* DASHBOARD BODY */
.dashboard-body { flex: 1; overflow-y: auto; padding: 30px 40px; }

/* HERO RATING */
.rating-hero {
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(135deg, rgba(88, 166, 255, 0.06) 0%, rgba(56, 139, 253, 0.02) 100%);
    border: 1px solid rgba(88, 166, 255, 0.12);
    border-radius: 16px; padding: 30px 40px; margin-bottom: 30px;
}

.rating-card { display: flex; align-items: center; gap: 24px; }

.rating-letter {
    font-size: 3.5rem; font-weight: 900; line-height: 1;
    padding: 16px 24px; border-radius: 12px;
    background: rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.1);
    min-width: 100px; text-align: center;
}

.rating--elite .rating-letter,
.rating-letter.rating--elite { color: #ffd700; border-color: rgba(255, 215, 0, 0.3); text-shadow: 0 0 30px rgba(255, 215, 0, 0.3); }
.rating--good .rating-letter,
.rating-letter.rating--good { color: #3fb950; border-color: rgba(63, 185, 80, 0.3); }
.rating--mid .rating-letter,
.rating-letter.rating--mid { color: #d29922; border-color: rgba(210, 153, 34, 0.3); }
.rating--low .rating-letter,
.rating-letter.rating--low { color: #f85149; border-color: rgba(248, 81, 73, 0.3); }

.rating-meta { display: flex; flex-direction: column; gap: 4px; }
.rating-label { font-size: 0.6rem; font-weight: 800; letter-spacing: 2px; color: #484f58; }
.rating-score { font-size: 2rem; font-weight: 800; color: #fff; }
.rating-outlook { font-size: 0.75rem; font-weight: 700; text-transform: capitalize; }
.outlook--stable { color: #3fb950; }
.outlook--positive { color: #58a6ff; }
.outlook--negative { color: #f85149; }

.hero-stats { display: flex; gap: 40px; }
.hero-stat { text-align: center; }
.hero-val { font-size: 1.4rem; font-weight: 800; color: #e6edf3; font-family: var(--font-family-mono); }
.hero-label { font-size: 0.65rem; color: #484f58; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

/* METRICS GRID */
.metrics-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.metric-panel {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px; padding: 24px;
    transition: border-color 0.3s;
}
.metric-panel:hover { border-color: rgba(88, 166, 255, 0.15); }

.panel-header {
    display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
}
.panel-icon { font-size: 1.3rem; }
.panel-header h3 { margin: 0; font-size: 1rem; color: #e6edf3; flex: 1; }

.panel-badge {
    font-size: 0.6rem; font-weight: 800; padding: 3px 8px;
    border-radius: 4px; letter-spacing: 1px;
}
.badge-good { background: rgba(63, 185, 80, 0.15); color: #3fb950; border: 1px solid rgba(63, 185, 80, 0.2); }
.badge-warning { background: rgba(210, 153, 34, 0.15); color: #d29922; border: 1px solid rgba(210, 153, 34, 0.2); }
.badge-bad { background: rgba(248, 81, 73, 0.15); color: #f85149; border: 1px solid rgba(248, 81, 73, 0.2); }
.badge-neutral { background: rgba(255,255,255,0.05); color: #8b949e; border: 1px solid rgba(255,255,255,0.08); }

/* FINANCE */
.finance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.finance-item { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; }
.fi-label { font-size: 0.65rem; color: #484f58; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.fi-value { font-size: 1.1rem; font-weight: 800; font-family: var(--font-family-mono); }

.runway-warning {
    margin-top: 12px; padding: 10px; background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.2); border-radius: 6px;
    font-size: 0.8rem; color: #f85149; text-align: center; font-weight: 600;
}

.mini-chart { margin-top: 16px; }
.sparkline-svg { width: 100%; height: 60px; display: block; }
.chart-label { font-size: 0.6rem; color: #30363d; text-align: center; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }

/* CRISIS */
.crisis-score-ring { display: flex; justify-content: center; margin-bottom: 16px; position: relative; }
.ring-svg { width: 120px; height: 120px; }
.score-ring-fill { transition: stroke-dasharray 1s ease-out; }
.ring-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    text-align: center;
}
.ring-value { font-size: 1.8rem; font-weight: 900; color: #fff; }
.ring-label { font-size: 0.55rem; color: #484f58; font-weight: 800; letter-spacing: 2px; }

.grade-bar {
    display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin-bottom: 12px;
}
.grade-segment {
    display: flex; align-items: center; gap: 4px; padding: 4px 10px;
    border-radius: 6px; font-size: 0.7rem; font-weight: 800;
}
.grade-letter { font-size: 0.8rem; }
.grade-count { opacity: 0.7; }

.grade--S { background: rgba(210, 153, 34, 0.2); color: #ffd700; }
.grade--A { background: rgba(63, 185, 80, 0.2); color: #3fb950; }
.grade--B { background: rgba(88, 166, 255, 0.2); color: #58a6ff; }
.grade--C { background: rgba(139, 148, 158, 0.2); color: #8b949e; }
.grade--D { background: rgba(248, 81, 73, 0.2); color: #f85149; }
.grade--F { background: rgba(80, 80, 80, 0.2); color: #666; }

.crisis-stats {
    display: flex; justify-content: center; gap: 20px;
    font-size: 0.75rem; color: #8b949e; font-weight: 600;
}

.no-data-state { text-align: center; padding: 30px 0; }
.no-data-icon { font-size: 2.5rem; margin-bottom: 10px; }
.no-data-state p { color: #484f58; font-size: 0.85rem; }

/* CUSTOMERS */
.customer-metrics { display: flex; align-items: center; gap: 30px; margin-bottom: 16px; }
.cust-big-stat { text-align: center; padding: 16px 24px; background: rgba(0,0,0,0.2); border-radius: 12px; }
.cust-big-val { font-size: 2.5rem; font-weight: 900; color: #fff; line-height: 1; }
.cust-big-label { font-size: 0.6rem; color: #484f58; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }

.cust-details { flex: 1; }
.cust-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
.cust-label { font-size: 0.8rem; color: #8b949e; }
.cust-value { font-size: 0.8rem; font-weight: 700; color: #e6edf3; font-family: var(--font-family-mono); }

.sat-bar { height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden; }
.sat-fill { height: 100%; border-radius: 3px; transition: width 1s ease-out; }
.sat-good { background: linear-gradient(90deg, #3fb950, #56d364); }
.sat-warning { background: linear-gradient(90deg, #d29922, #e3b341); }
.sat-danger { background: linear-gradient(90deg, #f85149, #da3633); }

/* INFRASTRUCTURE */
.infra-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.infra-item { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(0,0,0,0.15); border-radius: 8px; }
.infra-icon { font-size: 1.2rem; width: 20px; text-align: center; }
.infra-icon.online { color: #3fb950; }
.infra-icon.degraded { color: #d29922; }
.infra-icon.total { color: #58a6ff; }
.infra-icon.health { color: #f85149; font-size: 1rem; }
.infra-val { font-size: 1.1rem; font-weight: 800; color: #e6edf3; }
.infra-label { font-size: 0.6rem; color: #484f58; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

/* SLA */
.sla-section { background: rgba(0,0,0,0.15); padding: 14px; border-radius: 10px; }
.sla-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.75rem; color: #8b949e; font-weight: 700; }
.sla-rate { font-family: var(--font-family-mono); }
.sla-bar { height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden; }
.sla-fill { height: 100%; background: linear-gradient(90deg, #58a6ff, #3fb950); border-radius: 2px; transition: width 1s; }
.sla-meta { font-size: 0.65rem; color: #30363d; margin-top: 6px; }

/* COLOR UTILITIES */
.text-success { color: #3fb950 !important; }
.text-danger { color: #f85149 !important; }
.text-warning { color: #d29922 !important; }

/* ERROR */
.error-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px; color: #8b949e; }
.btn-retry {
    background: rgba(88, 166, 255, 0.1); border: 1px solid rgba(88, 166, 255, 0.2);
    color: #58a6ff; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;
}
.btn-retry:hover { background: rgba(88, 166, 255, 0.2); }

/* RESPONSIVE */
@media (max-width: 900px) {
    .rating-hero { flex-direction: column; gap: 20px; text-align: center; }
    .hero-stats { justify-content: center; }
    .metrics-grid { grid-template-columns: 1fr; }
}

/* RESOURCE PANEL */
.full-width { grid-column: 1 / -1; }
.resource-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.res-card { background: rgba(0,0,0,0.25); padding: 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03); }
.res-meta { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px; }
.res-label { font-size: 0.7rem; color: #8b949e; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.res-val { font-size: 1.2rem; font-weight: 800; font-family: var(--font-family-mono); color: #e6edf3; }
.text-primary { color: #58a6ff !important; }

/* COMPLIANCE */
.compliance-scores { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.c-score-item { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; text-align: center; }
.c-score-val { font-size: 1.4rem; font-weight: 900; }
.c-score-label { font-size: 0.6rem; color: #484f58; text-transform: uppercase; font-weight: 700; margin-top: 4px; }
.compliance-hint { font-size: 0.7rem; color: #58a6ff; text-align: center; font-style: italic; opacity: 0.8; }

.btn-panel-action {
    background: rgba(88, 166, 255, 0.1); border: 1px solid rgba(88, 166, 255, 0.2);
    color: #58a6ff; padding: 4px 10px; border-radius: 4px; font-size: 0.6rem;
    font-weight: 800; cursor: pointer; transition: 0.2s;
}
.btn-panel-action:hover { background: #58a6ff; color: #fff; }
</style>
