<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="research-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <h2>🔬 Research Lab</h2>
                <div class="header-resources">
                    <span class="resource-pill">💡 {{ activeResearchCount }}/1 Active</span>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div class="overlay-body">
                <div v-if="loading" class="loading">Loading Research Data...</div>
                
                <div v-else class="research-tree">
                    <!-- Categories -->
                    <div v-for="(techs, category) in groupedResearch" :key="category" class="research-category">
                        <h3 class="category-title">{{ formatCategory(category) }}</h3>
                        
                        <div class="tech-grid">
                            <div 
                                v-for="tech in techs" 
                                :key="tech?.id || Math.random()"
                                class="tech-card"
                                :class="{
                                    'status-locked': tech?.status === 'locked',
                                    'status-available': tech?.status === 'available',
                                    'status-researching': tech?.status === 'researching',
                                    'status-completed': tech?.status === 'completed'
                                }"
                            >
                                <template v-if="tech">
                                    <!-- Dynamic Animation Overlays -->
                                    <div v-if="tech.status === 'researching'" class="researching-visuals">
                                        <div class="v3-grid-pattern"></div>
                                        <div class="v3-scan-line"></div>
                                        <div class="v3-pulse-glow"></div>
                                    </div>
                                    <div class="tech-header">
                                        <div class="tech-icon">{{ getIcon(tech.category) }}</div>
                                        <div class="tech-title">
                                            <h4>{{ tech.name }}</h4>
                                            <span class="tech-duration">⏱ {{ formatDuration(tech.duration) }}</span>
                                        </div>
                                    </div>
                                    
                                    <p class="tech-desc">{{ tech.description }}</p>
                                    
                                    <div class="tech-footer">
                                        <div v-if="tech.status === 'completed'" class="completed-badge">
                                            ✅ Researched
                                        </div>
                                        
                                        <div v-else-if="tech.status === 'researching'" class="progress-container">
                                            <div class="progress-info">
                                                <span class="est-label">EST_TIME:</span>
                                                <span class="est-value">{{ formatRemainingTime(tech.remaining_seconds) }}</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="fill" :style="{ width: (tech.progress || 0) + '%' }"></div>
                                            </div>
                                            <span class="progress-text">{{ Math.round(tech.progress || 0) }}%</span>
                                        </div>
                                        
                                        <div v-else class="action-area">
                                            <div class="cost" :class="{ 'text-danger': !canAfford(tech.cost || 0) }">
                                                ${{ (tech.cost || 0).toLocaleString() }}
                                            </div>
                                            <button 
                                                class="btn-research"
                                                :disabled="tech.status === 'locked' || tech.is_busy || !canAfford(tech.cost || 0)"
                                                @click="startResearch(tech)"
                                            >
                                                <template v-if="tech.status === 'locked'">Locked</template>
                                                <template v-else-if="tech.is_busy">Busy</template>
                                                <template v-else-if="!canAfford(tech.cost || 0)">Low Balance</template>
                                                <template v-else>Start Research</template>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Prerequisites Hint -->
                                    <div v-if="tech.status === 'locked'" class="prereq-hint">
                                        Requires: {{ formatPrereqs(tech.prerequisites) }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const emit = defineEmits(['close']);
const gameStore = useGameStore();

const loading = ref(true);
const researchData = ref([]);

const loadResearch = async () => {
    loading.value = true;
    try {
        const response = await api.get('/research');
        if (response.success) {
            researchData.value = response.data;
        }
    } catch (e) {
        console.error("Failed to load research", e);
    } finally {
        loading.value = false;
    }
};

let timer = null;

onMounted(() => {
    loadResearch();
    timer = setInterval(() => {
        if (Array.isArray(researchData.value)) {
            researchData.value.forEach(tech => {
                if (tech.status === 'researching' && tech.remaining_seconds > 0) {
                    tech.remaining_seconds--;
                }
            });
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const groupedResearch = computed(() => {
    const groups = {};
    const data = Array.isArray(researchData.value) ? researchData.value : [];
    
    data.forEach(tech => {
        if (!tech || !tech.category) return;
        if (!groups[tech.category]) groups[tech.category] = [];
        groups[tech.category].push(tech);
    });
    return groups;
});

const activeResearchCount = computed(() => {
    const data = Array.isArray(researchData.value) ? researchData.value : [];
    return data.filter(t => t && t.status === 'researching').length;
});

const formatCategory = (cat) => {
    if (!cat) return 'Unknown';
    return cat.charAt(0).toUpperCase() + cat.slice(1);
};

const formatDuration = (mins) => {
    if (!mins) return '0m';
    if (mins >= 60) return (mins / 60).toFixed(1) + 'h';
    return mins + 'm';
};

const formatPrereqs = (prereqs) => {
    if (!Array.isArray(prereqs) || prereqs.length === 0) return 'None';
    return prereqs.join(', ');
};

const formatRemainingTime = (seconds) => {
    if (seconds === undefined || seconds === null) return '--:--';
    if (seconds <= 0) return 'READY';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const canAfford = (cost) => {
    return (gameStore.player?.economy?.balance || 0) >= cost;
};

const getIcon = (cat) => {
    switch(cat) {
        case 'infrastructure': return '🏗️';
        case 'software': return '💾';
        case 'marketing': return '📢';
        default: return '🔬';
    }
};

const startResearch = async (tech) => {
    try {
        const response = await api.post('/research/start', { tech_id: tech.id });
        if (response.success) {
            await loadResearch();
            gameStore.loadGameState(); // Refresh money
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to start research');
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.research-overlay {
    width: 1000px;
    max-width: 90vw;
    max-height: 80vh;
    background: var(--v3-bg-base);
    display: flex;
    flex-direction: column;
    border: var(--v3-border-heavy);
    box-shadow: 0 40px 100px rgba(0,0,0,0.6);
    border-radius: var(--v3-radius);
    overflow: hidden;
}

.overlay-header {
    background: rgba(0,0,0,0.2);
    padding: 24px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: var(--v3-border-soft);
}

.overlay-header h2 {
    font-size: 0.85rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 12px;
}

.overlay-header h2::before {
    content: '';
    width: 3px;
    height: 12px;
    background: var(--v3-accent);
}

.resource-pill {
    font-size: 0.55rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    padding: 6px 12px;
    background: rgba(255,255,255,0.03);
    border: var(--v3-border-soft);
    border-radius: 2px;
    letter-spacing: 0.1em;
}

.close-btn {
    font-size: 1.2rem;
    color: var(--v3-text-ghost);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color var(--v3-transition-fast);
}
.close-btn:hover { color: #fff; }

.overlay-body {
    flex: 1;
    overflow-y: auto;
    padding: 32px;
}

.research-category {
    margin-bottom: 48px;
}

.category-title {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-text-secondary);
    margin-bottom: 20px;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 12px;
}

.category-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--v3-border-soft);
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.tech-card {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    padding: 24px;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: all var(--v3-transition-fast);
    border-radius: var(--v3-radius);
}

.tech-card:hover:not(.status-locked) {
    border-color: var(--v3-text-ghost);
    background: var(--v3-bg-overlay);
    transform: translateY(-2px);
}

.tech-header {
    display: flex;
    gap: 0;
    margin-bottom: 12px;
}

.tech-icon { font-size: 1rem; margin-right: 12px; opacity: 0.7; }

.tech-title h4 { 
    margin: 0 0 4px 0; 
    font-size: 0.75rem; 
    font-weight: 800; 
    color: #fff; 
    letter-spacing: 0.05em;
}
.tech-duration { 
    font-size: 0.55rem; 
    font-family: var(--font-family-mono); 
    color: var(--v3-text-ghost); 
    font-weight: 700;
}

.tech-desc {
    font-size: 0.65rem;
    color: var(--v3-text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
    flex: 1;
}

.tech-footer {
    border-top: var(--v3-border-soft);
    padding-top: 16px;
}

.action-area {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cost {
    font-size: 0.7rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-primary);
    font-weight: 700;
}

.btn-research {
    background: var(--v3-bg-accent);
    color: var(--v3-text-primary);
    border: var(--v3-border-soft);
    padding: 6px 12px;
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: all var(--v3-transition-fast);
}

.btn-research:hover:not(:disabled) {
    background: var(--v3-accent);
    color: #fff;
    border-color: var(--v3-accent);
}

.btn-research:disabled {
    opacity: 0.2;
    filter: grayscale(1);
    cursor: not-allowed;
}

/* Status V3 */
.status-completed {
    border-color: var(--v3-success);
    background: rgba(46, 204, 113, 0.02);
}

.status-researching {
    border-color: var(--v3-accent);
    background: rgba(47, 107, 255, 0.05);
    overflow: hidden;
}

.researching-visuals {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.v3-grid-pattern {
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(47, 107, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(47, 107, 255, 0.05) 1px, transparent 1px);
    background-size: 20px 20px;
    opacity: 0.5;
}

.v3-scan-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100px;
    background: linear-gradient(180deg, 
        transparent 0%, 
        rgba(47, 107, 255, 0.1) 50%, 
        transparent 100%);
    animation: v3-scan-move 4s linear infinite;
    opacity: 0.8;
}

.v3-pulse-glow {
    position: absolute;
    inset: 0;
    box-shadow: inset 0 0 30px rgba(47, 107, 255, 0.1);
    animation: v3-glow-pulse 2s ease-in-out infinite;
}

@keyframes v3-scan-move {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(300%); }
}

@keyframes v3-glow-pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}

.tech-header, .tech-desc, .tech-footer {
    position: relative;
    z-index: 1;
}

.status-locked {
    opacity: 0.4;
    filter: grayscale(1);
    background: rgba(0,0,0,0.1);
}

.completed-badge {
    color: var(--v3-success);
    font-size: 0.55rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    text-align: center;
}

.progress-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.est-label { font-size: 0.5rem; font-weight: 800; color: var(--v3-text-ghost); letter-spacing: 0.1em; }
.est-value { font-size: 0.6rem; font-family: var(--font-family-mono); color: var(--v3-accent); font-weight: 900; }

.progress-bar {
    flex: 1;
    height: 4px;
    background: rgba(47, 107, 255, 0.05);
    border: 1px solid rgba(47, 107, 255, 0.1);
    position: relative;
    overflow: visible;
}

.progress-bar .fill {
    height: 100%;
    background: var(--v3-accent);
    box-shadow: 0 0 15px var(--v3-accent-glow);
    transition: width 1s linear;
    position: relative;
}

.progress-bar .fill::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    bottom: -2px;
    width: 4px;
    background: #fff;
    box-shadow: 0 0 10px #fff, 0 0 20px var(--v3-accent);
    border-radius: 2px;
}

.progress-text {
    font-size: 0.55rem;
    font-family: var(--font-family-mono);
    font-weight: 800;
    color: var(--v3-accent);
}

.prereq-hint {
    font-size: 0.5rem;
    color: var(--v3-danger);
    margin-top: 12px;
    font-weight: 700;
    text-transform: uppercase;
}
</style>
