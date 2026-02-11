<template>
    <div class="research-overlay">
        <div class="overlay-header">
            <h2>Research & Development</h2>
            <button class="close-btn" @click="$emit('close')">&times;</button>
        </div>

        <div class="tech-grid" v-if="localProjects.length">
            <div 
                v-for="proj in localProjects" 
                :key="proj.key"
                class="tech-card"
                :class="{
                    'tech-card--active': proj.status === 'active',
                    'tech-card--maxed': proj.status === 'maxed',
                    'tech-card--locked': proj.status === 'locked'
                }"
            >
                <div class="tech-card__header">
                    <div class="tech-icon">
                        <svg v-if="proj.key === 'cooling_efficiency'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/></svg>
                        <svg v-else-if="proj.key === 'provisioning_speed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <svg v-else-if="proj.key === 'marketing_campaign'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        <svg v-else-if="proj.key === 'high_density_racks'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="12" y1="2" x2="12" y2="22"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                    </div>
                    <div class="tech-info">
                        <h3>{{ proj.name }}</h3>
                        <div class="tech-level">Level {{ proj.currentLevel }} / {{ proj.maxLevel }}</div>
                    </div>
                </div>

                <div class="tech-card__body">
                    <p>{{ proj.description }}</p>

                    <!-- Active Progress -->
                    <div v-if="proj.status === 'active'" class="tech-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" :style="{ width: proj.progress + '%' }"></div>
                        </div>
                        <div class="progress-text">{{ proj.progress }}% Researched</div>
                    </div>

                    <!-- Upgrade Info -->
                    <div v-else-if="proj.status !== 'maxed'" class="upgrade-info">
                        <div class="cost-item">
                            <span class="label">Cost:</span>
                            <span class="value">${{ proj.cost.toLocaleString() }}</span>
                        </div>
                        <div class="cost-item">
                            <span class="label">Time:</span>
                            <span class="value">{{ formatDuration(proj.duration) }}</span>
                        </div>
                    </div>
                </div>

                <div class="tech-card__actions">
                    <button 
                        v-if="proj.status === 'available'"
                        class="btn-research"
                        @click="startResearch(proj)"
                        :disabled="!!gameStore.research.active"
                    >
                        Start Research
                    </button>
                    <button v-else-if="proj.status === 'active'" class="btn-research btn-research--active" disabled>
                        In Progress
                    </button>
                    <button v-else-if="proj.status === 'maxed'" class="btn-research btn-research--maxed" disabled>
                        Max Level
                    </button>
                    <button v-else class="btn-research btn-research--locked" disabled>
                        Locked
                    </button>
                </div>
            </div>
        </div>
        <div v-else class="loading-state">
            Loading Research Data...
        </div>
    </div>
</template>

<script setup>
import { onMounted, computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();

const localProjects = computed(() => gameStore.research.projects || []);

onMounted(() => {
    gameStore.loadResearch();
});

function startResearch(proj) {
    gameStore.startResearch(proj.key);
}

function formatDuration(seconds) {
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    return `${mins}m`;
}
</script>

<style scoped>
.research-overlay {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 900px;
    max-width: 95vw;
    height: 80vh;
    background: #0d1117;
    border: 1px solid #30363d;
    box-shadow: 0 20px 50px rgba(0,0,0,0.8);
    border-radius: 8px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    padding: 20px;
    border-bottom: 1px solid #30363d;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #161b22;
}

.overlay-header h2 {
    margin: 0;
    color: #e6edf3;
    font-size: 1.5rem;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
}
.close-btn:hover { color: #fff; }

.tech-grid {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.tech-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 6px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    transition: all 0.2s;
}

.tech-card:hover {
    border-color: #58a6ff;
    transform: translateY(-2px);
}

.tech-card--active {
    border-color: #eab308;
    box-shadow: 0 0 10px rgba(234, 179, 8, 0.1);
}

.tech-card--maxed {
    border-color: #2ea043;
    opacity: 0.8;
}

.tech-card--locked {
    opacity: 0.5;
    pointer-events: none;
}

.tech-card__header {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.tech-icon {
    width: 48px;
    height: 48px;
    background: #21262d;
    border-radius: 8px;
    padding: 10px;
    color: #58a6ff;
}

.tech-info h3 {
    margin: 0 0 5px 0;
    font-size: 1rem;
    color: #e6edf3;
}

.tech-level {
    font-size: 0.8rem;
    color: #8b949e;
    font-family: monospace;
}

.tech-card__body {
    flex: 1;
    margin-bottom: 15px;
    color: #8b949e;
    font-size: 0.9rem;
    line-height: 1.4;
}

.upgrade-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #30363d;
    font-size: 0.85rem;
}

.cost-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}
.cost-item .value { color: #e6edf3; font-weight: 600; font-family: monospace; }

.tech-progress {
    margin-top: 15px;
}

.progress-bar {
    height: 6px;
    background: #30363d;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 5px;
}
.progress-fill {
    height: 100%;
    background: #eab308;
    transition: width 0.5s ease;
}
.progress-text {
    font-size: 0.8rem;
    color: #eab308;
    text-align: right;
}

.btn-research {
    width: 100%;
    padding: 8px;
    background: #238636;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}
.btn-research:hover:not(:disabled) { background: #2ea043; }
.btn-research:disabled { opacity: 0.6; cursor: not-allowed; background: #30363d; }

.btn-research--active { background: #eab308; color: #000; }
.btn-research--maxed { background: #30363d; color: #2ea043; border: 1px solid #2ea043; }
</style>
