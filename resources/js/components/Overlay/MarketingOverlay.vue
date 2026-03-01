<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="marketing-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <h2>📢 Marketing Campaigns</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <div v-if="loading" class="loading">Loading...</div>
                
                <div v-else class="campaign-grid">
                    <!-- Active Campaigns -->
                    <div class="section active-campaigns" v-if="activeCampaigns.length > 0">
                        <h3>Active Campaigns</h3>
                        <div v-for="campaign in activeCampaigns" :key="campaign.id" class="campaign-card active">
                            <div class="card-header">
                                <span class="type-badge">{{ campaign.name }}</span>
                                <span class="timer">Ends in {{ getTimeRemaining(campaign.ends_at) }}</span>
                            </div>
                            <div class="card-stats">
                                <div class="stat">
                                    <small>Leads</small>
                                    <strong>+{{ campaign.results?.customers_gained || 0 }}</strong>
                                </div>
                                <div class="stat">
                                    <small>Reputation</small>
                                    <strong>+{{ (campaign.results?.reputation_gained || 0).toFixed(1) }}</strong>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="fill" :style="{ width: getProgress(campaign) + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Campaigns -->
                    <div class="section available-campaigns">
                        <h3>Launch New Campaign</h3>
                        <div class="campaign-list">
                            <div v-for="(type, key) in campaignTypes" :key="key" class="campaign-option" :class="{ disabled: !canAfford(type.cost) }">
                                <div class="option-header">
                                    <h4>{{ type.name }}</h4>
                                    <span class="cost">${{ type.cost.toLocaleString() }}</span>
                                </div>
                                <div class="option-details">
                                    <p>Duration: {{ formatDuration(type.duration) }}</p>
                                    <p>Effectiveness: <span class="text-success">{{ type.effectiveness }}x Leads</span></p>
                                    <p>Reputation: +{{ type.reputation_gain }}</p>
                                </div>
                                <button 
                                    class="launch-btn" 
                                    :disabled="processing || !canAfford(type.cost) || type.min_reputation > currentReputation"
                                    @click="startCampaign(key)"
                                >
                                    {{ processing ? 'Launching...' : 'Kworb Launch' }}
                                </button>
                                <div v-if="type.min_reputation > currentReputation" class="requirement-warning">
                                    Min Rep: {{ type.min_reputation }}
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
import { ref, onMounted, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const emit = defineEmits(['close']);
const gameStore = useGameStore();

const loading = ref(true);
const processing = ref(false);
const activeCampaigns = ref([]);
const campaignTypes = ref({});
const history = ref([]);

const currentReputation = computed(() => gameStore.player?.economy?.reputation || 0);

const loadData = async () => {
    try {
        const response = await api.get('/marketing');
        if (response.success) {
            activeCampaigns.value = response.active;
            campaignTypes.value = response.types;
            history.value = response.history;
        }
    } catch (e) {
        console.error("Failed to load marketing data", e);
    } finally {
        loading.value = false;
    }
};

onMounted(loadData);

const canAfford = (cost) => {
    return (gameStore.player?.economy?.balance || 0) >= cost;
};

const formatDuration = (minutes) => {
    if (minutes >= 1440) return (minutes / 1440).toFixed(1) + ' Days';
    if (minutes >= 60) return (minutes / 60).toFixed(1) + ' Hours';
    return minutes + ' Mins';
};

const getTimeRemaining = (endsAt) => {
    const end = new Date(endsAt).getTime();
    const now = Date.now();
    const diff = Math.max(0, Math.floor((end - now) / 60000));
    if (diff < 60) return diff + 'm';
    return Math.floor(diff / 60) + 'h ' + (diff % 60) + 'm';
};

const getProgress = (campaign) => {
    const start = new Date(campaign.started_at).getTime();
    const end = new Date(campaign.ends_at).getTime();
    const now = Date.now();
    const total = end - start;
    const elapsed = now - start;
    if (total <= 0) return 100;
    return Math.min(100, Math.max(0, (elapsed / total) * 100));
};

const startCampaign = async (type) => {
    if (processing.value) return;
    processing.value = true;
    
    try {
        const response = await api.post('/marketing/start', { type });
        if (response.success) {
            await loadData();
            gameStore.loadGameState(); // Refresh money
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to start campaign');
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.marketing-overlay {
    width: 900px;
    max-width: 95vw;
    height: 80vh;
    background: var(--color-bg-light);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    padding: 20px;
    background: rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border);
}

.overlay-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.section {
    margin-bottom: 30px;
}

.section h3 {
    margin-bottom: 15px;
    color: var(--color-text-muted);
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 1px;
}

/* Active Campaigns */
.active-campaigns {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.campaign-card {
    background: rgba(var(--color-primary-rgb), 0.1);
    border: 1px solid var(--color-primary);
    border-radius: 8px;
    padding: 15px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
}

.card-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.stat small { display: block; font-size: 0.7rem; opacity: 0.7; }

.progress-bar {
    height: 4px;
    background: rgba(0,0,0,0.3);
    border-radius: 2px;
    overflow: hidden;
}

.progress-bar .fill {
    height: 100%;
    background: var(--color-primary);
    transition: width 1s linear;
}

/* Available Campaigns */
.campaign-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.campaign-option {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 15px;
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
}

.campaign-option:hover {
    transform: translateY(-2px);
    border-color: var(--color-text-muted);
}

.campaign-option.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.option-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
}

.cost { color: var(--color-warning); }

.option-details {
    font-size: 0.85rem;
    margin-bottom: 15px;
    flex: 1;
}

.option-details p { margin: 4px 0; }

.launch-btn {
    width: 100%;
    padding: 10px;
    background: var(--color-primary);
    color: #000;
    border: none;
    border-radius: 4px;
    font-weight: 700;
    cursor: pointer;
}

.launch-btn:disabled {
    background: #444;
    color: #888;
    cursor: not-allowed;
}

.requirement-warning {
    margin-top: 5px;
    font-size: 0.75rem;
    color: var(--color-danger);
    text-align: center;
}

.text-success { color: var(--color-success); }
</style>
