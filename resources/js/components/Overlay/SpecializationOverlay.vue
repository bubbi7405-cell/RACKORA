<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="spec-overlay glass-panel animation-fade-in">
            <div class="header-section">
                <div class="milestone-badge">COMPANY STRATEGY</div>
                <h1>Specialization</h1>
                <p class="description">Define your company's identity. Switching specializes your infrastructure but costs reputation and money.</p>
                <div class="close-btn" @click="$emit('close')">×</div>
            </div>

            <div class="tabs-control">
                <div class="tab-item" :class="{ 'active': activeTab === 'strategy' }" @click="activeTab = 'strategy'">
                    <span class="tab-icon">🎯</span> BUSINESS STRATEGY
                </div>
                <div class="tab-item" :class="{ 'active': activeTab === 'skills' }" @click="activeTab = 'skills'">
                    <span class="tab-icon">🧬</span> SKILL SPECIALIZATIONS
                </div>
                <div class="tab-item" :class="{ 'active': activeTab === 'reputation' }" @click="activeTab = 'reputation'">
                    <span class="tab-icon">🏆</span> BRAND & REPUTATION
                </div>
            </div>

            <div v-if="loading" class="loader-container">
                <div class="loader"></div>
            </div>

            <div v-else class="content-body">
                <!-- STRATEGY TAB -->
                <div v-if="activeTab === 'strategy'" class="animation-slide-up">
                    <!-- Level 10 Gate -->
                    <div v-if="playerLevel < 10" class="level-gate">
                        <div class="gate-icon">🔒</div>
                        <h3>CORPORATE DOCTRINE LOCKED</h3>
                        <p>Specialization Protocols require <strong>Level 10</strong> to establish your company's strategic identity.</p>
                        <div class="gate-progress">
                            <div class="gate-bar">
                                <div class="gate-fill" :style="{ width: (playerLevel / 10) * 100 + '%' }"></div>
                            </div>
                            <span class="gate-label">Level {{ playerLevel }} / 10</span>
                        </div>
                    </div>

                    <template v-else>
                    <div class="current-status" v-if="currentSpec">
                        <span class="label">Current Strategy:</span>
                        <span class="value">{{ definitions[currentSpec]?.name || currentSpec }}</span>
                    </div>

                    <div class="specs-grid">
                        <div 
                            v-for="(def, key) in definitions" 
                            :key="key" 
                            class="spec-card"
                            :class="{ 
                                'active': currentSpec === key, 
                                'selected': selectedKey === key,
                                'can-afford': (gameStore.player?.economy?.balance || 0) >= def.unlock_cost
                            }"
                            @click="selectSpec(key)"
                        >
                            <div class="card-glow" :style="{ background: getGlowColor(key) }"></div>
                            
                            <div class="card-header">
                                <div class="icon-box">{{ getIcon(key) }}</div>
                                <div class="title-box">
                                    <h3>{{ def.name }}</h3>
                                    <div v-if="currentSpec === key" class="active-badge">CURRENT IDENTITY</div>
                                </div>
                            </div>

                            <p class="desc">{{ def.description }}</p>
                            
                            <div class="metrics">
                                <div class="metric-row">
                                    <span class="m-label">Pricing</span>
                                    <div class="m-bar"><div class="m-fill" :style="{ width: (def.price_modifier * 50) + '%', background: getGlowColor(key) }"></div></div>
                                    <span class="m-val" :class="def.price_modifier >= 1 ? 'pos' : 'neg'">x{{ def.price_modifier.toFixed(1) }}</span>
                                </div>
                                <div class="metric-row">
                                    <span class="m-label">Patience</span>
                                    <div class="m-bar"><div class="m-fill" :style="{ width: (def.patience_modifier * 50) + '%', background: getGlowColor(key) }"></div></div>
                                    <span class="m-val" :class="def.patience_modifier >= 1 ? 'pos' : 'neg'">x{{ def.patience_modifier.toFixed(1) }}</span>
                                </div>
                            </div>

                            <div class="passives" v-if="def.passives && Object.keys(def.passives).length">
                                <div v-for="(val, p) in def.passives" :key="p" class="passive-pill">
                                    ➕ {{ formatPassive(p, val) }}
                                </div>
                            </div>

                            <div class="footer-info">
                                <div class="rep-impact" v-if="def.reputation_impact !== 0">
                                    Reputation: <span :class="def.reputation_impact > 0 ? 'pos' : 'neg'">{{ def.reputation_impact > 0 ? '+' : '' }}{{ def.reputation_impact }}</span>
                                </div>
                                <div class="cost" v-if="currentSpec !== key">
                                    Cost: <span :class="(gameStore.player?.economy?.balance || 0) < def.unlock_cost ? 'text-danger' : 'text-success'">${{ def.unlock_cost.toLocaleString() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="selection-detail" v-if="selectedKey && currentSpec !== selectedKey">
                        <div class="detail-info">
                            <strong>Strategic Shift:</strong> Are you sure you want to rebrand to <span>{{ definitions[selectedKey].name }}</span>? 
                            This will cost <span>${{ definitions[selectedKey].unlock_cost.toLocaleString() }}</span> and shift your market perception.
                        </div>
                        <button class="rebrand-btn" @click="confirmSwitch" :disabled="processing || !canSwitch">
                            {{ processing ? 'EXECUTING REBRAND...' : 'CONFIRM REBRANDING' }}
                        </button>
                    </div>
                    </template>
                </div>

                <!-- SKILLS TAB -->
                <div v-if="activeTab === 'skills'" class="animation-slide-up">
                    <div class="skills-header">
                        <div class="points-badge">
                            <span class="pts-val">{{ skillPoints }}</span>
                            <span class="pts-label">SKILL POINTS AVAILABLE</span>
                        </div>
                        <p class="skill-intro">Spend points earned from leveling up to gain permanent tactical bonuses across your operations.</p>
                    </div>

                    <div class="skill-tree-layout">
                        <div v-for="(category, catId) in skillTree" :key="catId" class="skill-category">
                            <div class="cat-label">{{ category.label }}</div>
                            <div class="skills-list">
                                <div 
                                    v-for="(skill, skillId) in category.skills" 
                                    :key="skillId"
                                    class="skill-node"
                                    :class="{
                                        'unlocked': isSkillUnlocked(skillId),
                                        'locked': !isSkillUnlocked(skillId) && skillPoints < skill.cost,
                                        'affordable': !isSkillUnlocked(skillId) && skillPoints >= skill.cost
                                    }"
                                    @click="unlockSkill(skillId, skill)"
                                >
                                    <div class="node-status">
                                        {{ isSkillUnlocked(skillId) ? '✓ UNLOCKED' : `${skill.cost} PTS` }}
                                    </div>
                                    <h4>{{ skill.name }}</h4>
                                    <p>{{ skill.description }}</p>
                                    <div class="node-glow"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- REPUTATION TAB -->
                <div v-if="activeTab === 'reputation'" class="animation-slide-up">
                    <div class="rep-header">
                        <div class="global-rep">
                            <span class="rep-val">{{ Math.round(gameStore.player?.economy?.reputation || 0) }}</span>
                            <span class="rep-label">GLOBAL REPUTATION</span>
                        </div>
                        <p class="rep-intro">Specialized reputation is earned through your actions, research, and the customers you serve. Higher reputation in a niche unlocks powerful institutional bonuses.</p>
                    </div>

                    <div class="rep-grid">
                        <div v-for="(rep, cat) in specializedReputation" :key="cat" class="rep-segment">
                            <div class="rep-segment__header">
                                <span class="rep-icon">{{ getIcon(cat) }}</span>
                                <div>
                                    <h4 class="rep-segment__name">{{ cat.charAt(0).toUpperCase() + cat.slice(1) }} Specialist</h4>
                                    <div class="rep-segment__desc">{{ getRepDesc(cat) }}</div>
                                </div>
                                <div class="rep-segment__value">{{ Math.round(rep) }}/100</div>
                            </div>
                            
                            <div class="rep-progress-container">
                                <div class="rep-progress-bar">
                                    <div class="rep-fill" :style="{ width: rep + '%', background: getGlowColor(cat) }"></div>
                                    <!-- Milestone Mark -->
                                    <div class="milestone-marker" :class="{ 'achieved': rep >= getMilestone(cat) }" :style="{ left: getMilestone(cat) + '%' }">
                                        <div class="milestone-label">MILESTONE ({{ getMilestone(cat) }}+)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rep-bonuses">
                                <div class="bonus-item" :class="{ 'locked': rep < getMilestone(cat) }">
                                    <span class="bonus-status">{{ rep >= getMilestone(cat) ? '✓ ACTIVE' : '🔒 LOCKED' }}</span>
                                    <div class="bonus-text">
                                        <strong>Bonus:</strong> {{ getBonusDesc(cat) }}
                                    </div>
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
import { ref, computed, onMounted } from 'vue';
import api from '../../utils/api';
import { useGameStore } from '../../stores/game';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();
const definitions = ref({});
const currentSpec = ref('balanced');
const selectedKey = ref(null);
const loading = ref(true);
const processing = ref(false);
const error = ref(null);
const activeTab = ref('strategy');

// Skill state
const skillTree = ref({});
const unlockedSkills = ref([]);
const skillPoints = ref(0);
const playerLevel = computed(() => gameStore.player?.economy?.level || 1);

const specializedReputation = computed(() => {
    return gameStore.player?.economy?.specializedReputation || {
        budget: 0,
        premium: 0,
        hpc: 0,
        green: 0
    };
});

const emit = defineEmits(['close']);

onMounted(async () => {
    try {
        const [specRes, skillRes] = await Promise.all([
            api.get('/management/specializations'),
            api.get('/management/skills')
        ]);

        if (specRes.success) {
            definitions.value = specRes.definitions;
            currentSpec.value = specRes.current;
            selectedKey.value = specRes.current;
        }

        if (skillRes.success) {
            skillTree.value = skillRes.tree;
            unlockedSkills.value = skillRes.unlocked;
            skillPoints.value = skillRes.points;
        }
    } catch (e) {
        error.value = "Failed to load management data.";
    } finally {
        loading.value = false;
    }
});

const getIcon = (key) => {
    const map = {
        'balanced': '🏢',
        'budget_mass': '📦',
        'budget': '📦',
        'high_performance': '⚡',
        'premium': '💎',
        'hpc': '⚡',
        'eco_certified': '🌱',
        'green': '🌱',
        'crypto_vault': '🔐'
    };
    return map[key] || '🏢';
};

const getGlowColor = (key) => {
    const map = {
        'balanced': '#8b949e',
        'budget_mass': '#f85149',
        'budget': '#f85149',
        'high_performance': '#a371f7',
        'premium': '#d29922',
        'hpc': '#a371f7',
        'eco_certified': '#3fb950',
        'green': '#3fb950',
    };
    return map[key] || '#8b949e';
};

const getRepDesc = (cat) => {
    const map = {
        'budget': 'Focused on volume and razor-thin margins. Scalability is your middle name.',
        'budget_mass': 'Focused on volume and razor-thin margins. Scalability is your middle name.',
        'premium': 'Elite service for elite customers. Reliability and trust are your core assets.',
        'high_performance': 'The heavy lifters. Parallel computing and raw horsepower attract specialized researchers.',
        'hpc': 'The heavy lifters. Parallel computing and raw horsepower attract specialized researchers.',
        'eco_certified': 'Eco-conscious infrastructure. Offsetting your carbon footprint to attract futuristic brands.',
        'green': 'Eco-conscious infrastructure. Offsetting your carbon footprint to attract futuristic brands.'
    };
    return map[cat] || '';
};

const getMilestone = (cat) => {
    const map = { 'budget': 75, 'budget_mass': 75, 'premium': 80, 'high_performance': 85, 'hpc': 85, 'eco_certified': 75, 'green': 75 };
    return map[cat] || 75;
};

const getBonusDesc = (cat) => {
    const map = {
        'budget': 'Bulk Purchase Discount (-10% reduction in all hardware purchase costs).',
        'budget_mass': 'Bulk Purchase Discount (-10% reduction in all hardware purchase costs).',
        'premium': 'Elite Trust (20% reduction in SLA penalties for unresolved incidents).',
        'high_performance': 'Compute Authority (15% reduction in total server heat output).',
        'hpc': 'Compute Authority (15% reduction in total server heat output).',
        'eco_certified': 'Eco Subsidy (15% reduction in hourly regional taxes).',
        'green': 'Eco Subsidy (15% reduction in hourly regional taxes).'
    };
    return map[cat] || '';
};

const formatPassive = (key, val) => {
    const map = {
        'power_cost_reduction': 'Power efficiency',
        'reputation_gain': 'Passive reputation boost',
        'churn_reduction': 'Lower customer churn',
        'cooling_penalty': 'Higher cooling demand',
        'bandwidth_drain': 'Higher bandwidth draw'
    };
    return map[key] || key;
};

const selectSpec = (key) => {
    selectedKey.value = key;
    SoundManager.playClick();
};

const canSwitch = computed(() => {
    const def = definitions.value[selectedKey.value];
    if (!def) return false;
    return (gameStore.player?.economy?.balance || 0) >= def.unlock_cost;
});

const confirmSwitch = async () => {
    processing.value = true;
    try {
        const res = await api.post('/management/specialization', {
            specialization: selectedKey.value
        });
        
        if (res.success) {
            SoundManager.playSuccess();
            await gameStore.loadGameState();
            emit('close');
        }
    } catch (e) {
        error.value = e.response?.data?.error || "Rebranding failed.";
        SoundManager.playError();
    } finally {
        processing.value = false;
    }
};

const isSkillUnlocked = (id) => unlockedSkills.value.includes(id);

const unlockSkill = async (id, skill) => {
    if (isSkillUnlocked(id) || skillPoints.value < skill.cost || processing.value) return;
    
    processing.value = true;
    try {
        const res = await api.post('/management/skills/unlock', { skill_id: id });
        if (res.success) {
            SoundManager.playSuccess();
            unlockedSkills.value.push(id);
            skillPoints.value -= skill.cost;
            await gameStore.loadGameState();
        }
    } catch (e) {
        error.value = e.response?.data?.error || "Skill unlock failed.";
        SoundManager.playError();
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.9); backdrop-filter: blur(12px);
    z-index: 3000;
    display: flex; align-items: center; justify-content: center;
}

.spec-overlay {
    width: 1200px; max-width: 95vw; max-height: 90vh;
    background: #0d1117; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    display: flex; flex-direction: column;
    padding: 0;
    position: relative;
    overflow: hidden;
    box-shadow: 0 0 50px rgba(0,0,0,0.5);
}

.header-section { 
    padding: 40px 40px 20px;
    background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, transparent 100%);
}

.milestone-badge {
    display: inline-block;
    background: var(--color-primary);
    color: #000;
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 12px;
}

h1 { font-size: 2.5rem; margin: 0; color: #fff; letter-spacing: -1px; }

.description { color: #8b949e; font-size: 1.1rem; margin-top: 8px; max-width: 600px; }

.close-btn { position: absolute; top: 30px; right: 40px; font-size: 2.5rem; cursor: pointer; color: #484f58; transition: 0.2s; z-index: 10; }
.close-btn:hover { color: #fff; transform: scale(1.1); }

.tabs-control {
    display: flex;
    padding: 0 40px;
    gap: 32px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.tab-item {
    padding: 16px 0;
    color: #8b949e;
    font-weight: 700;
    font-size: 0.85rem;
    letter-spacing: 1px;
    cursor: pointer;
    position: relative;
    transition: 0.2s;
}

.tab-item:hover { color: #fff; }
.tab-item.active { color: var(--color-primary); }
.tab-item.active::after {
    content: '';
    position: absolute; bottom: 0; left: 0; width: 100%; height: 2px;
    background: var(--color-primary);
    box-shadow: 0 0 10px var(--color-primary);
}

.tab-icon { margin-right: 8px; font-size: 1.1rem; vertical-align: middle; }

.content-body { flex: 1; padding: 40px; overflow-y: auto; }

.specs-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 24px; margin-top: 20px;
}

.spec-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px; padding: 24px;
    cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex; flex-direction: column;
    overflow: hidden;
}

.card-glow {
    position: absolute; top: 0; left: 0; width: 100%; height: 4px; opacity: 0.3;
}

.spec-card:hover { 
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.15);
    transform: translateY(-5px);
}

.spec-card.selected { 
    border-color: rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.07);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.spec-card.active { 
    border-color: var(--color-primary);
    background: rgba(56, 139, 253, 0.05);
}

.card-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; }

.icon-box {
    width: 48px; height: 48px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
}

.title-box h3 { margin: 0; font-size: 1.25rem; color: #fff; }

.active-badge { 
    color: var(--color-primary); font-size: 0.65rem; font-weight: 800; 
    letter-spacing: 1px; margin-top: 4px;
}

.desc { color: #8b949e; font-size: 0.9rem; line-height: 1.5; margin-bottom: 24px; min-height: 3rem; }

.metrics { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }

.metric-row { display: flex; align-items: center; gap: 10px; font-size: 0.8rem; }
.m-label { color: #484f58; width: 60px; text-transform: uppercase; font-weight: 700; }
.m-bar { flex: 1; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; }
.m-fill { height: 100%; border-radius: 2px; transition: width 1s; }
.m-val { font-weight: 800; min-width: 35px; text-align: right; }

.passives { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.passive-pill {
    background: rgba(63, 185, 80, 0.1);
    color: #3fb950;
    padding: 4px 10px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 600;
}

.footer-info {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.05);
    display: flex; justify-content: space-between;
    font-size: 0.85rem;
}

.rep-impact { color: #8b949e; }
.cost { font-weight: bold; }

.selection-detail {
    margin-top: 32px;
    background: rgba(56, 139, 253, 0.1);
    border: 1px solid rgba(56, 139, 253, 0.2);
    border-radius: 12px;
    padding: 24px;
    display: flex; align-items: center; justify-content: space-between;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(3); opacity: 0; }
}

/* Level Gate Styles */
.level-gate {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px 40px; text-align: center;
    background: rgba(0,0,0,0.2); border: 1px dashed rgba(255,255,255,0.1);
    border-radius: 12px; margin-top: 20px;
}
.gate-icon { font-size: 3rem; margin-bottom: 20px; opacity: 0.5; }
.level-gate h3 { color: #fff; margin: 0 0 10px; font-size: 1.5rem; letter-spacing: 2px; }
.level-gate p { color: #8b949e; max-width: 500px; margin: 0 auto 30px; font-size: 1rem; line-height: 1.5; }

.gate-progress { width: 100%; max-width: 400px; }
.gate-bar { height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
.gate-fill { height: 100%; background: var(--color-primary); transition: width 0.5s ease; }
.gate-label { color: #8b949e; font-size: 0.8rem; font-weight: 700; letter-spacing: 1px; }

.detail-info { color: #8b949e; font-size: 1rem; max-width: 70%; }
.detail-info span { color: #fff; font-weight: bold; }

.rebrand-btn {
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 14px 28px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s;
}

.rebrand-btn:hover:not(:disabled) { transform: translateY(-2px); filter: brightness(1.2); }
.rebrand-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.pos { color: #3fb950; }
.neg { color: #f85149; }
.text-danger { color: #f85149; }
.text-success { color: #3fb950; }

.loader-container { padding: 100px; display: flex; flex-direction: column; align-items: center; gap: 20px; }
.loader { border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid var(--color-primary); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Skill Tree Styles */
.skills-header { margin-bottom: 40px; display: flex; align-items: center; gap: 32px; }
.points-badge {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    padding: 15px 25px; border-radius: 12px;
    text-align: center; display: flex; flex-direction: column;
}
.pts-val { font-size: 2rem; font-weight: 900; color: var(--color-primary); line-height: 1; }
.pts-label { font-size: 0.6rem; font-weight: 800; color: #8b949e; margin-top: 5px; }
.skill-intro { color: #8b949e; font-size: 1.1rem; max-width: 600px; line-height: 1.4; }

.skill-tree-layout { display: flex; flex-direction: column; gap: 40px; }
.skill-category { }
.cat-label { 
    font-size: 0.75rem; font-weight: 800; color: #484f58; letter-spacing: 2px; 
    text-transform: uppercase; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px;
}
.cat-label::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.05); }

.skills-list { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.skill-node {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px; padding: 24px; position: relative;
    cursor: pointer; transition: all 0.3s; overflow: hidden;
}

.node-status { 
    font-size: 0.65rem; font-weight: 800; margin-bottom: 12px;
    background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 4px;
    display: inline-block;
}

.skill-node h4 { margin: 0 0 8px; color: #fff; font-size: 1.1rem; }
.skill-node p { margin: 0; color: #8b949e; font-size: 0.85rem; line-height: 1.5; }

.skill-node.unlocked { 
    border-color: rgba(63, 185, 80, 0.3);
    background: rgba(63, 185, 80, 0.05);
}
.skill-node.unlocked .node-status { color: #3fb950; background: rgba(63, 185, 80, 0.1); }
.skill-node.unlocked h4 { color: #3fb950; }

.skill-node.affordable:hover {
    background: rgba(56, 139, 253, 0.05);
    border-color: var(--color-primary);
    transform: translateY(-4px);
}
.skill-node.affordable .node-status { color: var(--color-primary); }

.skill-node.locked { opacity: 0.5; cursor: not-allowed; }

.node-glow {
    position: absolute; bottom: 0; right: 0; width: 60px; height: 60px;
    background: radial-gradient(circle at bottom right, var(--color-primary), transparent 70%);
    opacity: 0; transition: 0.3s;
}
.skill-node.affordable:hover .node-glow { opacity: 0.2; }
.skill-node.unlocked .node-glow { opacity: 0.1; background: radial-gradient(circle at bottom right, #3fb950, transparent 70%); }
.skill-node.unlocked .node-glow { opacity: 0.1; background: radial-gradient(circle at bottom right, #3fb950, transparent 70%); }

/* Reputation Tab Styles */
.rep-header { margin-bottom: 40px; display: flex; align-items: center; gap: 32px; }
.global-rep {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    padding: 15px 25px; border-radius: 12px;
    text-align: center; display: flex; flex-direction: column;
}
.rep-val { font-size: 2rem; font-weight: 900; color: #fff; line-height: 1; }
.rep-label { font-size: 0.6rem; font-weight: 800; color: #8b949e; margin-top: 5px; }
.rep-intro { color: #8b949e; font-size: 1.1rem; max-width: 600px; line-height: 1.4; }

.rep-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
.rep-segment {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px; padding: 24px;
}
.rep-segment__header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
.rep-icon { font-size: 2rem; }
.rep-segment__name { margin: 0; font-size: 1.1rem; color: #fff; }
.rep-segment__desc { font-size: 0.8rem; color: #8b949e; margin-top: 4px; line-height: 1.4; }
.rep-segment__value { margin-left: auto; font-family: var(--font-family-mono); font-weight: 700; color: #fff; font-size: 0.9rem; }

.rep-progress-container { margin-bottom: 24px; }
.rep-progress-bar { height: 8px; background: rgba(0,0,0,0.3); border-radius: 4px; position: relative; overflow: visible; }
.rep-fill { height: 100%; border-radius: 4px; transition: width 1s ease-out; }

.milestone-marker {
    position: absolute; top: -10px; bottom: -10px; width: 2px; background: rgba(255,255,255,0.3);
    pointer-events: none;
}
.milestone-marker.achieved { background: #fff; box-shadow: 0 0 10px #fff; }
.milestone-label {
    position: absolute; top: -18px; left: 50%; transform: translateX(-50%);
    font-size: 8px; font-weight: 800; white-space: nowrap; color: #484f58;
}
.milestone-marker.achieved .milestone-label { color: #fff; }

.rep-bonuses { }
.bonus-item {
    background: rgba(0,0,0,0.2); border-radius: 8px; padding: 12px;
    display: flex; gap: 12px; align-items: flex-start;
}
.bonus-status { font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,0.05); color: #484f58; }
.bonus-item.locked { opacity: 0.4; }
.bonus-item:not(.locked) .bonus-status { background: rgba(56, 139, 253, 0.2); color: var(--color-primary); }
.bonus-text { font-size: 0.85rem; color: #8b949e; line-height: 1.4; }
.bonus-text strong { color: #fff; }
</style>
