<template>
    <div class="analytics-dashboard animate-fade-in glass-panel">
        <header class="dashboard-header">
            <div class="header-title">
                <span class="header-icon">📊</span>
                <h2>ADVANCED ANALYTICS & INTEL</h2>
            </div>
            <p class="header-subtitle">Real-time market share, competitor AI telemetry, and finalcial trajectory.</p>
        </header>

        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <span>Establishing secure connection to telemetry nodes...</span>
        </div>

        <div v-else class="dashboard-grid">
            <!-- Left Column: Global Market Share -->
            <div class="market-share-col">
                <div class="panel-section">
                    <h3>🌍 GLOBAL MARKET SHARE</h3>
                    <div class="market-pie-container">
                        <div class="share-list">
                            <div class="share-item player">
                                <span class="cmp-icon">👑</span>
                                <div class="cmp-info">
                                    <span class="cmp-name">{{ marketData?.player?.name }}</span>
                                    <div class="mini-bar-bg"><div class="mini-bar data-fill" :style="'width: ' + marketData?.player?.marketShare + '%'"></div></div>
                                </div>
                                <span class="cmp-value">{{ marketData?.player?.marketShare }}%</span>
                            </div>
                            
                            <div v-for="comp in marketData?.competitors" :key="comp.id" class="share-item competitor" :class="{'is-hostile': comp.isHostile}">
                                <span class="cmp-icon">{{ comp.isHostile ? '⚔️' : '🏢' }}</span>
                                <div class="cmp-info">
                                    <span class="cmp-name">{{ comp.name }}</span>
                                    <div class="mini-bar-bg"><div class="mini-bar comp-fill" :style="'width: ' + comp.marketShare + '%'"></div></div>
                                </div>
                                <span class="cmp-value">{{ comp.marketShare }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-section mt-4">
                    <h3>⚡ YOUR METRICS</h3>
                    <div class="metrics-grid">
                         <div class="metric-card">
                            <span class="label">CLIENTS</span>
                            <span class="value">{{ playerStats?.customers || 0 }}</span>
                         </div>
                         <div class="metric-card">
                            <span class="label">ACTIVE SERVERS</span>
                            <span class="value">{{ playerStats?.activeServers || 0 }}</span>
                         </div>
                         <div class="metric-card">
                            <span class="label">REPUTATION</span>
                            <span class="value">{{ marketData?.player?.reputation || 50 }}</span>
                         </div>
                         <div class="metric-card highlight">
                            <span class="label">PROFIT TICK</span>
                            <span class="value" :class="financials?.summary?.profit >= 0 ? 'text-success' : 'text-danger'">
                                ${{ formatMoney(financials?.summary?.profit || 0) }}
                            </span>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Competitor Intel -->
            <div class="intel-col">
                <div class="panel-section">
                    <h3>🕵️ COMPETITOR INTELLIGENCE</h3>
                    <div class="intel-list">
                        <div v-for="comp in marketData?.competitors" :key="'intel-'+comp.id" class="intel-card" :class="{'hostile-glow': comp.isHostile}">
                            <div class="intel-header">
                                <div class="comp-title">
                                    <h4>{{ comp.name }}</h4>
                                    <span class="archetype-badge">{{ formatArchetype(comp.archetype) }}</span>
                                </div>
                                <div class="threat-level" v-if="comp.isHostile" title="High Hostility Detected">⚠️ Threat</div>
                            </div>
                            <div class="intel-stats">
                                <div class="stat">
                                    <span class="label">Uptime</span>
                                    <span class="value" :class="comp.uptime > 99 ? 'text-success' : 'text-warning'">{{ comp.uptime }}%</span>
                                </div>
                                <div class="stat">
                                    <span class="label">Reputation</span>
                                    <span class="value">{{ comp.reputation }}</span>
                                </div>
                                <div class="stat">
                                    <span class="label">Capacity</span>
                                    <span class="value">{{ formatMoney(comp.capacity) }}</span>
                                </div>
                            </div>
                            <p class="intel-desc">{{ getArchetypeDesc(comp.archetype) }}</p>
                            <div class="enmity-bar-group">
                                <div class="enmity-labels">
                                    <span>Hostility to You</span>
                                    <span>{{ comp.enmity }}/100</span>
                                </div>
                                <div class="mini-bar-bg enmity-bg">
                                    <div class="mini-bar enmity-fill" :style="'width: ' + comp.enmity + '%'"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const gameStore = useGameStore();

const loading = ref(true);
const marketData = ref(null);
const financials = ref(null);
const playerStats = ref({});

let pollInterval = null;

onMounted(async () => {
    await fetchAnalytics();
    pollInterval = setInterval(fetchAnalytics, 15000); // 15 sec refresh
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

async function fetchAnalytics() {
    try {
        const response = await api.get('/analytics');
        if (response.success) {
            marketData.value = response.market;
            financials.value = response.financials;
            playerStats.value = response.playerStats;
        }
    } catch (e) {
        console.error('Failed to load analytics', e);
    } finally {
        loading.value = false;
    }
}

function formatMoney(value) {
    return Number(value).toLocaleString();
}

function formatArchetype(string) {
    return string.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function getArchetypeDesc(type) {
    const descs = {
        'aggressive_expander': 'Rapidly expands capacity and triggers price wars. Highly aggressive if threatened.',
        'premium_stability': 'Focuses on 99.99% uptime and high prices. Avoids price wars but has high reputation.',
        'budget_volume': 'The cheapest in the market. Thin margins but massive user volume and capacity.',
        'stealth_innovator': 'Secretive. Invests heavily in late-game tech to lower latency.',
        'regional_specialist': 'Defends their home region brutally. Hard to displace locally.'
    };
    return descs[type] || 'Unknown strategic behavior.';
}
</script>

<style scoped>
.analytics-dashboard {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: var(--space-xl);
}

.dashboard-header {
    margin-bottom: var(--space-xl);
}
.header-title { display: flex; align-items: center; gap: 12px; margin-bottom: 8px;}
.header-title h2 { font-size: 1.25rem; font-weight: 900; color: #fff; letter-spacing: 0.15em; }
.header-subtitle { color: var(--v3-text-secondary); font-size: 0.85rem; }

.dashboard-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: var(--space-xl);
    flex: 1;
    overflow: hidden;
}

.panel-section {
    background: rgba(0, 0, 0, 0.2);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
    padding: var(--space-lg);
    display: flex;
    flex-direction: column;
}

.panel-section h3 {
    font-size: 0.65rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.2em;
    margin-bottom: var(--space-lg);
    text-transform: uppercase;
}

.market-share-col {
    display: flex;
    flex-direction: column;
    gap: var(--space-lg);
}

.intel-col {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.intel-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-md);
    overflow-y: auto;
    padding-right: 8px;
}

.intel-card {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
    padding: var(--space-md);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.intel-card.hostile-glow {
    border-color: rgba(255, 50, 50, 0.4);
    box-shadow: inset 0 0 20px rgba(255, 50, 50, 0.05);
}

.intel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.comp-title h4 { font-weight: 800; font-size: 0.9rem; color: #fff; margin-bottom: 4px; }
.archetype-badge { font-size: 0.55rem; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-family: var(--font-family-mono); }
.threat-level { color: #ff4e4e; font-size: 0.65rem; font-weight: bold; background: rgba(255, 78, 78, 0.1); padding: 4px 8px; border-radius: 4px; animation: pulseRed 2s infinite; }

@keyframes pulseRed {
    0% { background: rgba(255, 78, 78, 0.1); }
    50% { background: rgba(255, 78, 78, 0.3); }
    100% { background: rgba(255, 78, 78, 0.1); }
}

.intel-stats {
    display: flex;
    justify-content: space-between;
    background: rgba(0,0,0,0.3);
    padding: 8px;
    border-radius: var(--v3-radius);
    font-size: 0.75rem;
}
.intel-stats .stat { display: flex; flex-direction: column; gap: 2px;}
.intel-stats .label { font-size: 0.55rem; color: var(--v3-text-ghost); text-transform: uppercase; }
.intel-stats .value { font-weight: 800; font-family: var(--font-family-mono); }

.intel-desc { font-size: 0.75rem; color: var(--v3-text-secondary); line-height: 1.4; }

.enmity-bar-group { display: flex; flex-direction: column; gap: 4px; margin-top: auto;}
.enmity-labels { display: flex; justify-content: space-between; font-size: 0.6rem; color: var(--v3-text-ghost); font-weight: bold; text-transform: uppercase;}
.enmity-bg { height: 6px; background: rgba(0,0,0,0.5); border-radius: 3px; overflow: hidden; }
.enmity-fill { height: 100%; background: linear-gradient(90deg, #ffb347, #ff4e4e); transition: width 0.5s;}

/* Market Share List */
.share-list { display: flex; flex-direction: column; gap: 12px; }
.share-item { display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: var(--v3-radius);}
.share-item.player { border: 1px solid var(--v3-accent-glow); background: rgba(88, 166, 255, 0.05); }
.share-item.is-hostile { border: 1px solid rgba(255, 78, 78, 0.2); }
.cmp-icon { font-size: 1.2rem;}
.cmp-info { flex: 1; display: flex; flex-direction: column; gap: 6px;}
.cmp-name { font-weight: 700; font-size: 0.75rem; color: #fff;}
.cmp-value { font-family: var(--font-family-mono); font-weight: 800; font-size: 0.85rem;}

.mini-bar-bg { width: 100%; height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; }
.data-fill { height: 100%; background: var(--v3-accent); }
.comp-fill { height: 100%; background: var(--v3-text-ghost); }

/* Metrics grid */
.metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.metric-card { background: rgba(0,0,0,0.3); padding: 12px; border-radius: var(--v3-radius); display: flex; flex-direction: column; gap: 4px; }
.metric-card.highlight { background: rgba(88, 166, 255, 0.1); border: 1px solid rgba(88, 166, 255, 0.2);}
.metric-card .label { font-size: 0.55rem; color: var(--v3-text-ghost); font-weight: 900; letter-spacing: 0.1em; }
.metric-card .value { font-size: 1.1rem; font-weight: 800; font-family: var(--font-family-mono); color: #fff; }

.text-success { color: #00ff9d !important; }
.text-danger { color: #ff4e4e !important; }
.text-warning { color: #ffb347 !important; }
</style>
