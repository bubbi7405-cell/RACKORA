<template>
    <div class="panel-achievements">
        <div class="achievements-header">
            <h3>Milestones & Statistics</h3>
            <p>Historical record of your achievements and system performance.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-meta">
                    <span class="label">UPTIME PERFORMANCE</span>
                    <span class="val success">99.98%</span>
                </div>
                <div class="sparkline-container">
                    <Sparkline :data="[99.9, 99.8, 99.9, 99.95, 99.99, 99.98, 99.98]" color="#22c55e" :height="40" />
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-meta">
                    <span class="label">REVENUE TREND</span>
                    <span class="val">$1.2M</span>
                </div>
                <div class="sparkline-container">
                    <Sparkline :data="[100, 200, 450, 600, 800, 1100, 1200]" color="#3b82f6" :height="40" />
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-meta">
                    <span class="label">SECURITY RECORD</span>
                    <span class="val info">12 CRITICALS</span>
                </div>
                <div class="sparkline-container">
                    <Sparkline :data="[1, 0, 2, 5, 3, 1, 0]" color="#60a5fa" :height="40" />
                </div>
            </div>
        </div>

        <div class="achievements-section">
            <div class="section-title">
                 <span>HALL OF RECORDS</span>
                 <span>{{ unlockedCount }} / {{ achievements.length }} UNLOCKED</span>
            </div>

            <div class="badges-grid v-scrollbar">
                <div 
                    v-for="ach in achievements" 
                    :key="ach.id" 
                    class="badge-card"
                    :class="{ locked: !ach.unlocked }"
                >
                    <div class="badge-visual">
                         <div class="badge-bg" :style="{ background: ach.unlocked ? 'var(--color-primary)' : '#18181b' }"></div>
                         <div class="badge-icon">{{ ach.unlocked ? ach.icon : '🔒' }}</div>
                    </div>
                    <div class="badge-text" v-tooltip="ach.description">
                        <h4>{{ ach.name?.toUpperCase() }}</h4>
                        <p>{{ ach.description }}</p>
                        <div v-if="ach.unlocked" class="unlock-tag">UNLOCKED {{ formatDate(ach.unlocked_at) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../../utils/api';
import Sparkline from '../../UI/Sparkline.vue';

const props = defineProps(['profileData']);
const achievements = ref([]);

const unlockedCount = computed(() => achievements.value.filter(a => a.unlocked).length);

async function loadAchievements() {
    try {
        const res = await api.get('/profile'); // We get them from profile data
        if (res.success && res.data.achievements) {
            // Transform if needed, but assuming structure is okay
            achievements.value = res.data.achievements.map(a => ({
                id: a.id,
                name: a.name,
                description: a.description,
                icon: a.icon || '🏆',
                unlocked: a.isUnlocked, // Mismatch in property names sometimes
                unlocked_at: a.unlocked_at
            }));
        }
    } catch (e) {
        console.error(e);
    }
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
}

onMounted(loadAchievements);
</script>

<style scoped>
.panel-achievements {
    animation: fadeIn 0.4s ease-out;
}

.achievements-header { margin-bottom: 30px; }
.achievements-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 5px; }
.achievements-header p { color: #71717a; font-size: 0.9rem; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: #09090b;
    border: 1px solid #18181b;
    padding: 20px;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.stat-meta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.label { font-size: 0.65rem; font-weight: 800; color: #71717a; letter-spacing: 0.05em; }
.val { font-size: 1.2rem; font-weight: 800; }
.val.success { color: #22c55e; }
.val.info { color: var(--color-primary); }

.sparkline-container { height: 40px; }

.section-title {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 800;
    color: #52525b;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #18181b;
}

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.badge-card {
    display: flex;
    gap: 15px;
    background: #09090b;
    border: 1px solid #18181b;
    padding: 15px;
    border-radius: 12px;
    transition: all 0.2s;
}

.badge-card:not(.locked):hover {
    border-color: var(--color-primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.badge-card.locked { opacity: 0.4; filter: grayscale(1); border-style: dashed; }

.badge-visual {
    position: relative;
    width: 50px;
    height: 50px;
    flex-shrink: 0;
}

.badge-bg {
    position: absolute;
    inset: 0;
    border-radius: 12px;
    opacity: 0.1;
}

.badge-icon {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.badge-text { flex: 1; }
.badge-text h4 { font-size: 0.85rem; font-weight: 800; color: #fff; margin-bottom: 4px; }
.badge-text p { font-size: 0.75rem; color: #71717a; line-height: 1.4; }

.unlock-tag {
    font-size: 0.6rem;
    color: #22c55e;
    font-weight: 800;
    margin-top: 8px;
}

@media (max-width: 800px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
