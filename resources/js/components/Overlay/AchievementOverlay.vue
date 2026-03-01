<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="achievement-modal animation-scale-up">
            <div class="modal-header">
                <div class="header-content">
                    <div class="pre-title">CAREER MILESTONES</div>
                    <h1>Hall of Records</h1>
                    <p>Documenting your rise from a basement server room to a global infrastructure titan.</p>
                </div>
                <div class="stats-badges">
                    <div class="stat-badge">
                        <span class="label">COMLETED</span>
                        <span class="value">{{ unlockedCount }}/{{ achievements.length }}</span>
                    </div>
                    <div class="stat-badge">
                        <span class="label">TOTAL POINTS</span>
                        <span class="value">{{ totalPoints }}</span>
                    </div>
                </div>
                <button class="close-btn" @click="$emit('close')">×</button>
            </div>

            <div class="modal-tabs">
                <button 
                    v-for="cat in categories" 
                    :key="cat.id" 
                    class="tab-btn"
                    :class="{ active: activeCategory === cat.id }"
                    @click="activeCategory = cat.id"
                >
                    {{ cat.label }}
                </button>
            </div>

            <div class="modal-content">
                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div>
                    <p>Fetching records...</p>
                </div>
                
                <div v-else class="achievement-grid">
                    <div 
                        v-for="ach in filteredAchievements" 
                        :key="ach.id" 
                        class="achievement-card"
                        :class="{ 'is-locked': !ach.isUnlocked }"
                    >
                        <div class="card-inner">
                            <div class="ach-icon">{{ ach.isUnlocked ? ach.icon : '🔒' }}</div>
                            <div class="ach-main">
                                <div class="ach-title">
                                    {{ ach.name }}
                                    <span v-if="ach.isUnlocked" class="unlocked-tag">UNLOCKED</span>
                                </div>
                                <div class="ach-desc">{{ ach.description }}</div>
                                <div v-if="ach.isUnlocked" class="ach-date">
                                    Resolved on {{ formatDate(ach.unlockedAt) }}
                                </div>
                            </div>
                            <div class="ach-points">
                                <div class="points-val">{{ ach.points }}</div>
                                <div class="points-lab">PTS</div>
                            </div>
                        </div>
                        <div class="card-bg-glow"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

const emit = defineEmits(['close']);

const achievements = ref([]);
const loading = ref(true);
const activeCategory = ref('all');

const categories = [
    { id: 'all', label: 'All Records' },
    { id: 'infrastructure', label: 'Build' },
    { id: 'economy', label: 'Profit' },
    { id: 'events', label: 'Crises' },
    { id: 'specialization', label: 'Strategy' },
];

const fetchAchievements = async () => {
    loading.value = true;
    try {
        const res = await api.get('/achievements');
        if (res.success) {
            achievements.value = res.data;
        }
    } catch (e) {
        console.error("Failed to fetch achievements", e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchAchievements);

const filteredAchievements = computed(() => {
    if (activeCategory.value === 'all') return achievements.value;
    return achievements.value.filter(a => a.category === activeCategory.value);
});

const unlockedCount = computed(() => achievements.value.filter(a => a.isUnlocked).length);
const totalPoints = computed(() => achievements.value.filter(a => a.isUnlocked).reduce((sum, a) => sum + a.points, 0));

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    z-index: 4000;
}

.achievement-modal {
    width: 900px; max-width: 95vw; height: 80vh;
    background: #0d1117; border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px; box-shadow: 0 0 50px rgba(0,0,0,0.8);
    display: flex; flex-direction: column; overflow: hidden;
    position: relative;
}

.modal-header {
    padding: 40px; background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, transparent 100%);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    display: flex; justify-content: space-between; align-items: center;
    position: relative;
}

.pre-title { font-size: 0.75rem; font-weight: 800; color: #388bfd; letter-spacing: 3px; margin-bottom: 8px; }
h1 { font-size: 2.25rem; margin: 0; color: #fff; letter-spacing: -1px; }
.header-content p { color: #8b949e; margin: 5px 0 0; font-size: 1rem; }

.stats-badges { display: flex; gap: 20px; }
.stat-badge { text-align: right; }
.stat-badge .label { display: block; font-size: 0.65rem; color: #484f58; font-weight: 800; letter-spacing: 1px; }
.stat-badge .value { font-size: 1.5rem; font-weight: 300; color: #fff; font-family: var(--font-family-mono); }

.close-btn { font-size: 3rem; background: none; border: none; color: #484f58; cursor: pointer; transition: 0.2s; padding: 0; line-height: 1; }
.close-btn:hover { color: #fff; transform: rotate(90deg); }

.modal-tabs { padding: 0 40px; display: flex; gap: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2); }
.tab-btn {
    background: none; border: none; padding: 15px 0; color: #8b949e; cursor: pointer;
    font-size: 0.9rem; font-weight: 600; transition: 0.2s; position: relative;
}
.tab-btn.active { color: #fff; }
.tab-btn.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #388bfd; }
.tab-btn:hover { color: #fff; }

.modal-content { flex: 1; overflow-y: auto; padding: 30px 40px; }

.achievement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

.achievement-card {
    background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px; padding: 20px; position: relative; overflow: hidden;
    transition: 0.3s;
}

.achievement-card.is-locked { opacity: 0.4; filter: grayscale(1); }
.achievement-card.is-locked:hover { opacity: 0.6; }

.card-inner { display: flex; align-items: center; gap: 20px; position: relative; z-index: 2; }

.ach-icon {
    font-size: 2.5rem; width: 64px; height: 64px; background: rgba(0,0,0,0.3);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(255,255,255,0.05);
}

.ach-main { flex: 1; }
.ach-title { font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.unlocked-tag { font-size: 0.6rem; background: #388bfd; color: #fff; padding: 2px 6px; border-radius: 3px; font-weight: 800; }

.ach-desc { font-size: 0.85rem; color: #8b949e; line-height: 1.4; margin-bottom: 8px; }
.ach-date { font-size: 0.7rem; color: #484f58; font-family: var(--font-family-mono); }

.ach-points { text-align: center; border-left: 1px solid rgba(255,255,255,0.05); padding-left: 20px; }
.points-val { font-size: 1.25rem; font-weight: 800; color: #388bfd; line-height: 1; }
.points-lab { font-size: 0.6rem; color: #484f58; font-weight: 800; }

.card-bg-glow {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: radial-gradient(circle at top right, rgba(56, 139, 253, 0.05), transparent);
    pointer-events: none;
}

.loading-state { text-align: center; padding: 100px 0; color: #484f58; }
.spinner {
    width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.1); border-top-color: #388bfd;
    border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px;
}
@keyframes spin { to { transform: rotate(360deg); } }

@keyframes scaleUp {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.animation-scale-up { animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
</style>
