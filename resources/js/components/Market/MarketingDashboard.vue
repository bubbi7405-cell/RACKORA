<template>
    <div class="marketing-dashboard">
        <!-- Header -->
        <header class="v3-header-glass">
            <div class="header-content">
                <div class="title-group">
                    <span class="v3-sys-label-glow">GROWTH_LEVERS</span>
                    <h1>Public Relations & Marketing</h1>
                </div>
                <div class="header-stats">
                    <div class="stat-box">
                        <span class="stat-label">Reputation</span>
                        <span class="stat-value" :class="reputationClass">
                            {{ reputation.toFixed(1) }}
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <div v-if="loading" class="v3-loading-state">
            <div class="scan-line"></div>
            Analyzing demographic metrics...
        </div>

        <div v-else class="dashboard-content scroller glass-scroller">
            
            <!-- Active Campaigns Section -->
            <section v-if="activeCampaigns.length > 0" class="section-active">
                <h2 class="section-title">LIVE_CAMPAIGNS //</h2>
                <div class="active-grid">
                    <div v-for="camp in activeCampaigns" :key="camp.id" class="campaign-card active-pulse">
                        <div class="card-header">
                            <span class="camp-icon">📣</span>
                            <div class="camp-meta">
                                <h3>{{ camp.name }}</h3>
                                <span class="camp-time">Expires in: {{ formatTimeLeft(camp.ends_at) }}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="result-row">
                                <span>Cust. Acquired:</span>
                                <strong>{{ camp.results?.customers_gained || 0 }}</strong>
                            </div>
                            <div class="result-row">
                                <span>Reputation Gain:</span>
                                <strong class="text-success">+{{ (camp.results?.reputation_gained || 0).toFixed(2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Available Campaigns Section -->
            <section class="section-available">
                <h2 class="section-title">CAMPAIGN_PORTFOLIO //</h2>
                
                <div class="campaign-list">
                    <div 
                        v-for="(config, key) in campaignTypes" 
                        :key="key" 
                        class="campaign-action-card glass-panel"
                        :class="{'locked': reputation < config.min_reputation}"
                    >
                        <div class="camp-info">
                            <h3>{{ config.name }}</h3>
                            <div class="camp-desc">
                                Duration: {{ formatDuration(config.duration) }} <br/>
                                Base Effectiveness: {{ config.effectiveness }}x <br/>
                                Target Rep Gain: +{{ config.reputation_gain }}
                            </div>
                            <div v-if="reputation < config.min_reputation" class="lock-req">
                                REQUIRES REPUTATION {{ config.min_reputation }}
                            </div>
                        </div>
                        
                        <div class="camp-execution">
                            <div class="camp-cost text-danger">-${{ formatMoney(config.cost) }}</div>
                            <button 
                                @click="startCampaign(key, config)" 
                                :disabled="reputation < config.min_reputation || !canAfford(config.cost) || isStarting === key"
                                class="btn-execute"
                            >
                                {{ isStarting === key ? 'INITIALIZING...' : 'LAUNCH CAMPAIGN' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AI Intelligence Section (New FEATURE 205) -->
            <section class="section-ai">
                <h2 class="section-title">MARKET_INTELLIGENCE_AI //</h2>
                <div v-if="reputation < 30 && !predictions.length" class="ai-locked-card glass-panel">
                    <div class="ai-icon">🤖</div>
                    <div class="ai-content">
                        <h3>NEURAL_MARKET_ANALYZER v2.4</h3>
                        <p>Our deep-learning models require at least **Level 15** and higher company reputation to crunch regional volatility data.</p>
                        <div class="ai-progress">
                            <div class="ai-progress-fill" :style="{ width: Math.min(100, (reputation / 30) * 100) + '%' }"></div>
                        </div>
                    </div>
                </div>
                <div v-else-if="predictions.length > 0" class="ai-prediction-grid">
                    <div v-for="pred in predictions" :key="pred.region" class="prediction-card" :class="pred.impact_type">
                        <div class="pred-header">
                            <span class="region-tag">{{ pred.region.toUpperCase() }}</span>
                            <span class="confidence">{{ pred.confidence }}% CONFIDENCE</span>
                        </div>
                        <h3>{{ pred.label }}</h3>
                        <p>{{ pred.description }}</p>
                        <div class="impact-indicator">
                            IMPACT: {{ pred.impact_type.toUpperCase() }}
                        </div>
                    </div>
                </div>
                <div v-else-if="!loading && reputation >= 15" class="ai-loading-mini">
                    <div class="pulse-ring"></div>
                    <span>Crunshing regional datasets...</span>
                </div>
            </section>

            <!-- History Section -->
            <section v-if="history.length > 0" class="section-history">
                <h2 class="section-title">PREVIOUS_OPs //</h2>
                <div class="history-table-container glass-panel">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>CAMPAIGN</th>
                                <th>COMPLETED</th>
                                <th>ACQUISITIONS</th>
                                <th>REP INFLUENCE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in history" :key="item.id">
                                <td>{{ item.name }}</td>
                                <td>{{ new Date(item.ends_at).toLocaleDateString() }}</td>
                                <td>{{ item.results?.customers_gained || 0 }}</td>
                                <td class="text-success">+{{ (item.results?.reputation_gained || 0).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();
const toast = useToastStore();

const loading = ref(true);
const isStarting = ref(null);
const activeCampaigns = ref([]);
const campaignTypes = ref({});
const history = ref([]);
const predictions = ref([]);
const now = ref(new Date());

let ticker = null;

const reputation = computed(() => gameStore.player?.economy?.reputation || 0);

const reputationClass = computed(() => {
    if (reputation.value > 80) return 'text-success drop-shadow-success';
    if (reputation.value < 30) return 'text-danger drop-shadow-danger';
    return 'text-primary drop-shadow-primary';
});

const canAfford = (cost) => {
    return (gameStore.player?.economy?.balance || 0) >= cost;
};

const formatMoney = (val) => {
    return new Intl.NumberFormat('en-US').format(val);
};

const formatDuration = (mins) => {
    if (mins >= 1440) return `${mins / 1440} Days`;
    if (mins >= 60) return `${mins / 60} Hours`;
    return `${mins} Mins`;
};

const formatTimeLeft = (endTimeStr) => {
    const end = new Date(endTimeStr);
    const diff = Math.floor((end - now.value) / 1000); 
    if (diff <= 0) return 'Ending soon...';
    
    if (diff > 86400) return `${Math.floor(diff / 86400)}d left`;
    if (diff > 3600) return `${Math.floor(diff / 3600)}h left`;
    const m = Math.floor(diff / 60);
    const s = diff % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
};

async function loadData() {
    try {
        const response = await api.get('/marketing');
        if (response.success) {
            activeCampaigns.value = response.active;
            campaignTypes.value = response.types;
            history.value = response.history;
        }

        // Fetch AI Predictions if reputation is sufficient
        if (reputation.value >= 30) {
            const predRes = await api.get('/marketing/predictions');
            if (predRes.success) {
                predictions.value = predRes.predictions;
            }
        }
    } catch (e) {
        // toast.error('Failed to load marketing intel'); // Silent fail for predictions
    } finally {
        loading.value = false;
    }
}

async function startCampaign(key, config) {
    if (!canAfford(config.cost)) return;
    
    isStarting.value = key;
    try {
        const response = await api.post('/marketing/start', { type: key });
        if (response.success) {
            toast.success(response.message);
            SoundManager.playSuccess();
            await loadData();
            await gameStore.loadGameState(); // Refresh economy
        }
    } catch (e) {
        toast.error(e.response?.data?.error || 'Failed to start campaign');
        SoundManager.playError();
    } finally {
        isStarting.value = null;
    }
}

onMounted(() => {
    loadData();
    ticker = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (ticker) clearInterval(ticker);
});
</script>

<style scoped>
.marketing-dashboard {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--v3-bg-base);
    color: #fff;
}

.title-group h1 {
    font-size: var(--v3-text-2xl);
    font-weight: 800;
    letter-spacing: -0.02em;
    margin-top: 5px;
}

.stat-box {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.stat-label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.stat-value {
    font-size: var(--v3-text-xl);
    font-weight: 900;
    font-family: var(--font-family-mono);
}

.text-success { color: var(--v3-success); }
.text-danger { color: var(--v3-danger); }
.text-primary { color: var(--v3-accent); }
.drop-shadow-success { filter: drop-shadow(0 0 8px rgba(46, 204, 113, 0.4)); }
.drop-shadow-danger { filter: drop-shadow(0 0 8px rgba(255, 77, 79, 0.4)); }
.drop-shadow-primary { filter: drop-shadow(0 0 8px rgba(88, 166, 255, 0.4)); }

.dashboard-content {
    flex: 1;
    padding: var(--space-xl) var(--space-2xl);
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.section-title {
    font-size: 0.65rem;
    font-weight: 900;
    color: var(--v3-text-secondary);
    letter-spacing: 0.2em;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title::before {
    content: '';
    width: 3px;
    height: 12px;
    background: var(--v3-accent);
}

/* Active Campaigns */
.active-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.campaign-card {
    background: var(--v3-bg-surface);
    border: 1px solid var(--v3-accent-soft);
    border-radius: var(--v3-radius);
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.campaign-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--v3-accent), transparent);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
    border-bottom: var(--v3-border-soft);
    padding-bottom: 12px;
}

.camp-icon {
    font-size: 1.5rem;
    background: rgba(88, 166, 255, 0.1);
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px;
}

.camp-meta h3 {
    font-size: 0.85rem;
    font-weight: 800;
}
.camp-time {
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
}

.result-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-bottom: 8px;
    border-bottom: 1px dashed rgba(255,255,255,0.05);
    padding-bottom: 4px;
}
.result-row span { color: var(--v3-text-secondary); }

/* Available Campaigns */
.campaign-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.campaign-action-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    transition: all var(--v3-transition-fast);
}

.campaign-action-card:hover:not(.locked) {
    border-color: var(--v3-text-ghost);
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
}

.campaign-action-card.locked {
    opacity: 0.5;
    filter: grayscale(1);
}

.camp-info h3 {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.camp-desc {
    font-size: 0.75rem;
    color: var(--v3-text-ghost);
    line-height: 1.5;
}

.lock-req {
    margin-top: 10px;
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-danger);
    letter-spacing: 0.1em;
}

.camp-execution {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

.camp-cost {
    font-size: 1.2rem;
    font-weight: 900;
    font-family: var(--font-family-mono);
}

.btn-execute {
    padding: 12px 24px;
    background: var(--v3-bg-accent);
    color: var(--v3-text-primary);
    border: var(--v3-border-soft);
    border-radius: var(--v3-radius);
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    cursor: pointer;
    transition: all var(--v3-transition-fast);
}

.btn-execute:hover:not(:disabled) {
    background: var(--v3-accent);
    color: #fff;
    border-color: var(--v3-accent);
    box-shadow: 0 10px 20px var(--v3-accent-glow);
    transform: translateY(-2px);
}

.btn-execute:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* History Grid */
.history-table-container {
    padding: 0;
    overflow: hidden;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th {
    padding: 16px;
    text-align: left;
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    border-bottom: var(--v3-border-soft);
    background: rgba(0,0,0,0.2);
}

.history-table td {
    padding: 16px;
    font-size: 0.75rem;
    border-bottom: var(--v3-border-soft);
    background: var(--v3-bg-surface);
}

.history-table tr:hover td {
    background: var(--v3-bg-overlay);
}

.history-table tr:last-child td {
    border-bottom: none;
}

/* AI Intelligence Styles */
.section-ai {
    margin-top: 20px;
}

.ai-locked-card {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 30px;
    background: linear-gradient(135deg, rgba(88, 166, 255, 0.05), rgba(0,0,0,0.4));
    border: 1px solid var(--v3-accent-soft);
}

.ai-icon {
    font-size: 3rem;
    filter: drop-shadow(0 0 15px var(--v3-accent-glow));
}

.ai-content h3 {
    font-size: 0.9rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--v3-accent);
}

.ai-content p {
    font-size: 0.75rem;
    color: var(--v3-text-ghost);
    margin-bottom: 16px;
}

.ai-progress {
    height: 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    overflow: hidden;
}

.ai-progress-fill {
    height: 100%;
    background: var(--v3-accent);
    box-shadow: 0 0 10px var(--v3-accent-glow);
    transition: width 1s ease-out;
}

.ai-prediction-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.prediction-card {
    background: var(--v3-bg-surface);
    border-left: 4px solid var(--v3-accent);
    padding: 20px;
    border-radius: 4px;
    transition: transform 0.3s ease;
}

.prediction-card:hover {
    transform: translateY(-5px);
}

.prediction-card.negative { border-left-color: var(--v3-danger); }
.prediction-card.positive { border-left-color: var(--v3-success); }
.prediction-card.premium { border-left-color: #f1c40f; }

.pred-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.region-tag {
    font-size: 0.6rem;
    font-weight: 900;
    background: rgba(255,255,255,0.05);
    padding: 2px 6px;
    border-radius: 3px;
}

.confidence {
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
}

.prediction-card h3 {
    font-size: 0.9rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.prediction-card p {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    line-height: 1.4;
    margin-bottom: 15px;
}

.impact-indicator {
    font-size: 0.55rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    opacity: 0.6;
}
</style>
