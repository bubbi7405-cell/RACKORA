<template>
    <div class="v2-evolution-forge">
        <div class="view-header">
            <div class="header-left">
                <h2 class="l1-priority">
                    R&D_PIPELINE // [PROJECT_LIST]
                </h2>
                <div class="subtitle l3-priority">STRATEGIC_ASSET_DEVELOPMENT // [INTEL_SYNC]</div>
            </div>
            
            <div class="v2-dominance-stats">
                <div class="dominance-label l3-priority">R&D_PROGRESSION</div>
                <div class="dominance-progress-row">
                    <div class="dominance-track">
                        <div class="dominance-fill" :style="{ width: (completedCount / Math.max(1, researchData.length) * 100) + '%' }"></div>
                    </div>
                    <span class="dominance-val l1-priority">{{ Math.round((completedCount / Math.max(1, researchData.length) * 100)) }}%</span>
                </div>
            </div>

            <div class="header-stats">
                <div class="stat-pill l2-priority">
                    <span class="label l3-priority">ACTIVE_PROJECTS</span>
                    <span class="value l1-priority">{{ activeResearchCount }}</span>
                </div>
                <div class="stat-pill l2-priority">
                    <span class="label l3-priority">DEVELOPED_ASSETS</span>
                    <span class="value l1-priority">{{ completedCount }}</span>
                </div>
            </div>
        </div>

        <div class="view-content">
            <div v-if="loading" class="loading-state">
                <div class="scan-line"></div>
                <span>ACCESSING_R&D_DATABASE...</span>
            </div>
            
            <div v-else class="research-grid-container">
                <div v-for="(techs, category) in groupedResearch" :key="category" class="research-category-section">
                    <div class="category-header">
                        <h3 class="l2-priority">
                            {{ formatCategory(category) }}
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'DEVELOPMENT_BRANCH: ' + formatCategory(category), content: 'Specific R&D branch focused on ' + category + ' improvements.', hint: 'Unlock tiers to reach advanced capabilities.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                        <div class="category-line"></div>
                    </div>
                    
                    <div class="tech-cards-grid">
                        <div 
                            v-for="tech in techs" 
                            :key="tech?.id || Math.random()"
                            class="v2-spec-card"
                            :class="{
                                'status-locked': tech?.status === 'locked',
                                'status-available': tech?.status === 'available',
                                'status-researching': tech?.status === 'researching',
                                'status-completed': tech?.status === 'completed'
                            }"
                        >
                            <template v-if="tech">
                                <!-- Active Research Visuals -->
                                <div v-if="tech.status === 'researching'" class="researching-bg-effect">
                                    <div class="grid-pattern"></div>
                                    <div class="pulse-glow"></div>
                                </div>

                                <div class="tech-card-header">
                                    <div class="tech-id-tag l3-priority">ASSET_ID // {{ tech.id }}</div>
                                    <h4 class="l1-priority">{{ tech.name }}</h4>
                                    
                                    <div v-if="tech.status === 'completed'" class="status-badge success l2-priority">
                                        [DEPLOYED]
                                    </div>
                                    <div v-else-if="tech.status === 'researching'" class="status-badge active l1-priority">
                                        DEVELOPING...
                                    </div>
                                </div>
                                
                                <div class="tech-card-meta l3-priority">
                                    <span>BRANCH: {{ tech.category.toUpperCase() }}</span>
                                    <span>DURATION: {{ formatDuration(tech.duration) }}</span>
                                </div>
                                
                                <div class="tech-card-body">
                                    <p class="l3-priority">{{ tech.description }}</p>

                                    <!-- Progress Bar if researching -->
                                    <div v-if="tech.status === 'researching'" class="progress-section"
                                        @mouseenter="tooltipStore.show($event, { title: 'DEVELOPMENT_PROGRESS', content: 'Percentage completion of the current project.', hint: 'Time remaining is based on resource allocation.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <div class="progress-meta">
                                            <span class="l1-priority">ETA: {{ formatRemainingTime(tech.remaining_seconds) }}</span>
                                            <span class="l1-priority">{{ Math.round(tech.progress || 0) }}%</span>
                                        </div>
                                        <div class="progress-bar-track">
                                            <div class="progress-bar-fill" :style="{ width: (tech.progress || 0) + '%' }"></div>
                                        </div>
                                    </div>

                                    <!-- Prerequisites -->
                                    <div v-if="tech.status === 'locked'" class="prereq-warning l3-priority"
                                        @mouseenter="tooltipStore.show($event, { title: 'PROJECT_LOCKED', content: 'Development is restricted until prerequisites are met.', hint: 'Unlock the required technology first.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <div class="lock-header">
                                            <span class="icon">🔒</span>
                                            <span v-if="tech.specialization && tech.specialization !== economyStore.player?.economy?.corporate_specialization" class="doctrine-label">
                                                SPECIALIZATION_RESTRICTED
                                            </span>
                                            <span v-else>ACCESS_RESTRICTED</span>
                                        </div>
                                        
                                        <div v-if="tech.specialization && tech.specialization !== economyStore.player?.economy?.corporate_specialization" class="doctrine-info">
                                            This specialized technology is exclusive to the <strong>{{ tech.specialization.toUpperCase() }}</strong> specialization. 
                                            You must pivot your corporate strategy to unlock this R&D branch.
                                        </div>
                                        <div v-else class="req-list">
                                            REQ: {{ formatPrereqs(tech.prerequisites) }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tech-card-footer">
                                    <div class="cost-display l2-priority" :class="{ 'insufficient text-danger': !canAfford(tech.cost || 0) && tech.status !== 'completed' }"
                                        @mouseenter="tooltipStore.show($event, { title: 'BUDGET_ALLOCATION', content: 'Upfront credit requirement for R&D infrastructure and resources.', hint: 'Non-refundable.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        BUDGET: ${{ (tech.cost || 0).toLocaleString() }}
                                    </div>

                                    <button 
                                        class="action-btn l2-priority"
                                        :disabled="tech.status !== 'available' || tech.is_busy || !canAfford(tech.cost || 0)"
                                        @click="startResearch(tech)"
                                        @mouseenter="tooltipStore.show($event, { title: 'INITIATE_PROJECT', content: 'Start development of this asset.', hint: 'Verify budget availability.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <span v-if="tech.status === 'completed'">ACTIVE</span>
                                        <span v-else-if="tech.status === 'researching'">DEVELOPING...</span>
                                        <span v-else-if="tech.status === 'locked'">ACCESS_RESTRICTED</span>
                                        <span v-else-if="!canAfford(tech.cost || 0)">INSUFFICIENT_FUNDS</span>
                                        <span v-else>INITIATE_PROJECT</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, computed, ref } from 'vue';
import { useResearchStore } from '../../stores/research';
import { useEconomyStore } from '../../stores/economy';
import { useTooltipStore } from '../../stores/tooltip';
import api from '../../utils/api';

const researchStore = useResearchStore();
const economyStore = useEconomyStore();
const tooltipStore = useTooltipStore();

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
    // Local timer for smooth countdown UI, server is authoritative source
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

const completedCount = computed(() => {
    const data = Array.isArray(researchData.value) ? researchData.value : [];
    return data.filter(t => t && t.status === 'completed').length;
});

const formatCategory = (cat) => {
    if (!cat) return 'UNKNOWN_PROTOCOL';
    return cat.replace('_', ' ').toUpperCase();
};

const formatDuration = (mins) => {
    if (!mins) return '0m';
    if (mins >= 60) return (mins / 60).toFixed(1) + 'h';
    return mins + 'm';
};

const formatPrereqs = (prereqs) => {
    if (!Array.isArray(prereqs) || prereqs.length === 0) return 'NONE';
    return prereqs.map(p => p.toUpperCase()).join(', ');
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
    return (economyStore.player?.economy?.balance || 0) >= cost;
};

const getIcon = (cat) => {
    switch(cat) {
        case 'infrastructure': return '🏗️';
        case 'software': return '💾';
        case 'marketing': return '📢';
        case 'efficiency': return '⚡';
        case 'security': return '🛡️';
        default: return '🔬';
    }
};

const startResearch = async (tech) => {
    const success = await researchStore.startResearch(tech.id);
    if (success) {
        await loadResearch();
        economyStore.initializePlayer(); // Refresh money - wait, initializePlayer is heavy. loadGameState is better.
        // Actually researchStore action calls loadGameState inside itself? No it calls loadResearch.
        // We should trigger economy update.
        // Ideally use gameStore.loadGameState() but we decomposed.
        // economyStore has no loadGameState but applyState.
        // We'll rely on WS update or manual refresh if needed.
        // Re-reading game.js: startResearch calls loadGameState().
        // Wait, I am using local researchData here, but researchStore also has state.
        // I should probably switch to using researchStore state fully eventually.
        // For now, let's keep local loadResearch for self-contained view, but verify money update.
    }
};
</script>

<style scoped>
.v2-evolution-forge {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--ds-bg-void);
    color: #fff;
}

.view-header {
    padding: 32px 40px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 2px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.v2-dominance-stats {
    flex: 1;
    max-width: 400px;
    margin: 0 60px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.dominance-label {
    font-size: 0.5rem;
    font-weight: 950;
    letter-spacing: 0.2em;
}

.dominance-progress-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.dominance-track {
    flex: 1;
    height: 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
    overflow: hidden;
}

.dominance-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ds-accent), #fff);
    box-shadow: 0 0 15px var(--ds-accent-glow);
    transition: width 1.5s var(--ds-ease-spring);
}

.header-left h2 {
    font-size: 1.4rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    color: #fff;
    margin: 0;
}

.subtitle {
    font-size: 0.65rem;
    color: var(--v3-text-ghost);
    letter-spacing: 0.2em;
    margin-top: 4px;
}

.header-stats {
    display: flex;
    gap: 16px;
}

.stat-pill {
    background: var(--v3-bg-surface);
    border: var(--v3-border-soft);
    padding: 8px 16px;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.stat-pill .label {
    font-size: 0.5rem;
    color: var(--v3-text-ghost);
    font-weight: 800;
    letter-spacing: 0.1em;
}

.stat-pill .value {
    font-size: 1.1rem;
    font-family: var(--font-family-mono);
    color: var(--v3-accent);
    line-height: 1;
}

.view-content {
    flex: 1;
    overflow-y: auto;
    padding: 32px;
    background: var(--v3-bg-base); 
    /* Add subtle grid background */
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 40px 40px;
}

.loading-state {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--v3-text-ghost);
    gap: 16px;
    font-family: var(--font-family-mono);
}

.research-category-section {
    margin-bottom: 48px;
}

.category-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 16px;
}

.category-header h3 {
    font-size: 0.8rem;
    font-weight: 900;
    color: var(--v3-text-secondary);
    letter-spacing: 0.15em;
    min-width: fit-content;
}

.category-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, var(--v3-border-soft), transparent);
}

.tech-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.v2-spec-card {
    background: rgba(255, 255, 255, 0.02);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    border-bottom: 2px solid rgba(0, 0, 0, 0.4);
    border-left: 1px solid rgba(255, 255, 255, 0.05);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s var(--ds-ease-out);
}

.v2-spec-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.15);
}

.tech-id-tag {
    font-size: 0.45rem;
    font-weight: 950;
    letter-spacing: 0.2em;
    font-family: var(--ds-font-mono);
    margin-bottom: 8px;
}

.v2-spec-card.status-available {
    border-top-color: var(--ds-accent);
}

.v2-spec-card.status-researching {
    background: linear-gradient(135deg, rgba(88, 166, 255, 0.05) 0%, transparent 100%);
    border-color: var(--ds-accent);
}

.v2-spec-card.status-completed {
    opacity: 0.7;
    filter: grayscale(0.5);
    border-left: 2px solid var(--ds-nominal);
}

.v2-spec-card.status-locked {
    opacity: 0.4;
    filter: blur(1px) grayscale(1);
    pointer-events: none;
}

.tech-card-header {
    display: flex;
    flex-direction: column;
    margin-bottom: 12px;
}

.tech-card-meta {
    display: flex;
    gap: 16px;
    font-size: 0.55rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.tech-icon-box {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border-radius: 4px;
}

.tech-info h4 {
    margin: 0 0 4px 0;
    font-size: 0.9rem;
    color: #fff;
    font-weight: 800;
}

.tech-meta {
    font-size: 0.6rem;
    color: var(--v3-text-ghost);
    font-family: var(--font-family-mono);
}

.status-badge {
    margin-left: auto;
    font-size: 0.5rem;
    font-weight: 900;
    padding: 4px 8px;
    border-radius: 2px;
    text-transform: uppercase;
}
.status-badge.active { background: var(--v3-accent); color: #fff; }
.status-badge.success { background: var(--v3-success); color: #000; }

.tech-card-body {
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.tech-card-body p {
    font-size: 0.75rem;
    color: var(--v3-text-secondary);
    line-height: 1.5;
    margin-bottom: 16px;
}

.progress-section {
    background: rgba(0,0,0,0.3);
    padding: 8px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
}

.progress-meta {
    display: flex;
    justify-content: space-between;
    font-family: var(--font-family-mono);
    font-size: 0.6rem;
    margin-bottom: 6px;
    color: var(--v3-accent);
}

.progress-bar-track {
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: var(--v3-accent);
    /* box-shadow: 0 0 10px var(--v3-accent); */
    transition: width 1s linear;
}

.prereq-warning {
    font-size: 0.65rem;
    color: var(--v3-danger);
    font-weight: 700;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: rgba(0, 0, 0, 0.2);
    padding: 10px;
    border: 1px solid rgba(255, 77, 79, 0.2);
    border-radius: 4px;
}

.lock-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.doctrine-label {
    color: #ffc107;
    letter-spacing: 1px;
}

.doctrine-info {
    font-size: 0.6rem;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.4;
    font-weight: 400;
}

.doctrine-info strong {
    color: #ffc107;
}

.req-list {
    color: var(--v3-text-ghost);
    font-family: var(--font-family-mono);
}

.tech-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.05);
    position: relative;
    z-index: 2;
}

.cost-display {
    font-family: var(--font-family-mono);
    font-size: 0.75rem;
    color: var(--v3-text-primary);
    font-weight: 700;
}
.cost-display.insufficient { color: var(--v3-danger); }

.action-btn {
    background: transparent;
    border: 1px solid var(--v3-border-soft);
    color: var(--v3-text-primary);
    padding: 8px 16px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn:hover:not(:disabled) {
    background: var(--v3-accent);
    color: #fff;
    border-color: var(--v3-accent);
}

.action-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    border-color: transparent;
}

/* Background Effects for Active Research */
.researching-bg-effect {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}
.grid-pattern {
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(47, 107, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(47, 107, 255, 0.05) 1px, transparent 1px);
    background-size: 10px 10px;
}
.pulse-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(47, 107, 255, 0.1) 0%, transparent 70%);
    animation: pulse 3s infinite ease-in-out;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 0.4; }
}

.v3-info-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(88, 166, 255, 0.15);
    color: #58a6ff;
    font-size: 10px;
    font-weight: 800;
    cursor: help;
    margin-left: 6px;
    vertical-align: middle;
    border: 1px solid rgba(88, 166, 255, 0.3);
    transition: all 0.2s;
}

.v3-info-trigger:hover {
    background: #58a6ff;
    color: #05070a;
    box-shadow: 0 0 10px rgba(88, 166, 255, 0.4);
}
</style>
