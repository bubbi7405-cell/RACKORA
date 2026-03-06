<template>
    <div class="strategy-dashboard">
        <div class="dashboard-section header-section">
            <div class="section-title">
                <h2 class="l1-priority">BOARD_STRATEGY</h2>
                <div class="subtitle l3-priority">Define corporate-wide directives and strategic specialization protocols.</div>
            </div>
            <div class="header-stats">
                <div class="stat-pill l2-priority" 
                    @mouseenter="tooltipStore.show($event, { title: 'ACTIVE_STRATEGIES', content: 'Current number of active management directives affecting operational costs.', hint: 'Monitor resource draw metrics.' })"
                    @mouseleave="tooltipStore.hide()"
                >
                    <span class="label l3-priority">ACTIVE_STRATEGIES</span>
                    <span class="value l1-priority">{{ activePolicies.length }}</span>
                </div>
                <div class="stat-pill l2-priority" 
                    @mouseenter="tooltipStore.show($event, { title: 'MARKET_PENETRATION', content: 'Aggregated market share across all regional networks.', hint: 'Increase footprint to dominate the sector.' })"
                    @mouseleave="tooltipStore.hide()"
                >
                    <span class="label l3-priority">MARKET_PENETRATION</span>
                    <span class="value l1-priority">{{ Math.round(gameStore.marketShare?.playerShare || 0) }}%</span>
                </div>
            </div>
        </div>

        <div class="strategy-grid">
            <!-- Left: Strategic Policies -->
            <div class="strategy-column policies">
                <div class="panel-card glass">
                    <div class="card-header">
                        <span class="header-icon">🔌</span>
                        <h3 class="l2-priority">
                            BOARD_DIRECTIVES
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'BOARD_DIRECTIVES', content: 'Policies that modify power consumption and resource efficiency.', hint: 'Changes apply immediately.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                        <div class="header-line"></div>
                    </div>
                    <div class="card-body scrollable">
                        <div class="policy-stack">
                            <div 
                                v-for="(policy, key) in availablePolicies" 
                                :key="key"
                                class="policy-node"
                                :class="{ 'node-active': isPolicyActive(key) }"
                                @click="togglePolicy(key)"
                                @mouseenter="tooltipStore.show($event, { title: policy.name, content: policy.description, hint: 'Toggling costs 0 XP but may affect reputation.' })"
                                @mouseleave="tooltipStore.hide()"
                            >
                                <div class="node-icon">{{ policy.icon }}</div>
                                <div class="node-main">
                                    <div class="node-title">
                                        <h4 class="l2-priority">{{ policy.name }}</h4>
                                        <div v-if="isPolicyActive(key)" class="active-indicator l1-priority">
                                            <span class="pulse-dot"></span>
                                            LIVE_THREAD
                                        </div>
                                    </div>
                                    <p class="node-desc">{{ policy.description }}</p>
                                    <div class="node-metrics">
                                        <div v-if="policy.power_cost_mod" class="metric" :class="getModClass(policy.power_cost_mod, 'cost')">
                                            COST {{ formatMod(policy.power_cost_mod) }}
                                        </div>
                                        <div v-if="policy.reputation_mod" class="metric" :class="getModClass(policy.reputation_mod, 'rep')">
                                            REP {{ formatModRep(policy.reputation_mod) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle: Internal Doctrine -->
            <div class="strategy-column doctrine">
                <div class="panel-card glass" :class="{ 'is-locked': playerLevel < 10 }">
                    <div class="card-header">
                        <span class="header-icon">{{ playerLevel < 10 ? '🔒' : '🏢' }}</span>
                        <h3 class="l2-priority">
                            STRATEGIC_SPECIALIZATION
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'CORPORATE_SPECIALIZATION', content: 'Long-term corporate focus providing significant passive benefits.', hint: 'Once selected, changes require a strategic reset.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div v-if="hasSpecialization" class="specialization-display"
                            @mouseenter="tooltipStore.show($event, { title: getSpecName(activespec), content: getSpecDesc(activespec), hint: 'Your active specialization is providing global bonuses.' })"
                            @mouseleave="tooltipStore.hide()"
                        >
                            <div class="spec-banner" :class="activespec">
                                <div class="spec-hero">
                                    <span class="hero-icon">{{ getSpecIcon(activespec) }}</span>
                                    <div class="hero-text">
                                        <h4 class="l1-priority">{{ getSpecName(activespec) }}</h4>
                                        <span class="hero-tag l3-priority">STRATEGY_CONFIRMED // [ACTIVE]</span>
                                    </div>
                                </div>
                                <p class="hero-desc">{{ getSpecDesc(activespec) }}</p>
                            </div>
                            <div class="buff-ledger">
                                <div class="ledger-label">STRATEGY_EFFECTS</div>
                                <div class="ledger-items">
                                    <div v-for="buff in getSpecBuffs(activespec)" :key="buff" class="ledger-item">
                                        <span class="check">✓</span> {{ buff }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="specialization-selection">
                            <!-- Locked State -->
                            <template v-if="playerLevel < 10">
                                <div class="selection-alert locked"
                                    @mouseenter="tooltipStore.show($event, { title: 'LOCKED_FEATURE', content: 'Advanced Corporate Specialization requires Executive Tier 10.', hint: 'Maintain operational growth to unlock.' })"
                                    @mouseleave="tooltipStore.hide()"
                                >
                                    <span class="lock-icon">🔒</span>
                                    SPECIALIZATION_RESTRICTED (REQ: TIER_10)
                                </div>
                                <div class="selection-grid locked">
                                    <div v-for="spec in specs" :key="spec.id" class="selection-btn locked"
                                        @mouseenter="tooltipStore.show($event, { title: spec.name, content: spec.shortDesc, hint: 'Locked.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <span class="btn-icon">🔒</span>
                                        <span class="btn-label">{{ spec.name }}</span>
                                    </div>
                                </div>
                                <div class="lock-progress-mini">
                                    <div class="progress-info">
                                        <span>TIER_PROGRESSION</span>
                                        <span>{{ playerLevel }}/10</span>
                                    </div>
                                    <div class="bar-bg">
                                        <div class="bar-fill" :style="{ width: Math.min(100, (playerLevel / 10) * 100) + '%' }"></div>
                                    </div>
                                </div>
                            </template>

                            <!-- Ready to Select State -->
                            <template v-else>
                                <div class="selection-alert ready">AWAITING_STRATEGY_SELECTION</div>
                                <div class="selection-grid">
                                    <button 
                                        v-for="spec in specs" 
                                        :key="spec.id" 
                                        class="selection-btn"
                                        :class="[spec.id, { 'is-processing': isProcessing }]"
                                        @click="confirmSpecialization(spec.id)"
                                        @mouseenter="tooltipStore.show($event, { title: spec.name, content: spec.shortDesc, hint: 'Select this specialization to commit the organization to this strategy.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <span class="btn-icon">{{ spec.icon }}</span>
                                        <span class="btn-label">{{ spec.name }}</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="panel-card glass positioning">
                    <div class="card-header">
                        <span class="header-icon">🎯</span>
                        <h3 class="l2-priority">
                            MARKET_POSITIONING
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'MARKET_POSITIONING', content: 'Relative position of the company compared to key competitors.', hint: 'Premium = Margin focus, Mass = Volume focus.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                        <div class="header-line"></div>
                    </div>
                    <div class="card-body">
                        <div class="position-radar">
                             <div class="radar-axes">
                                 <div class="axis v"><span>PREMIUM</span><span>BUDGET</span></div>
                                 <div class="axis h"><span>NICHE</span><span>MASS</span></div>
                             </div>
                             <div class="radar-content">
                                 <div 
                                     v-for="dot in radarPositioning" 
                                     :key="dot.id" 
                                     class="radar-dot" 
                                     :class="{ 'player-dot': dot.isPlayer, 'npc-dot': !dot.isPlayer }"
                                     :style="{ top: dot.y + '%', left: dot.x + '%', '--dot-color': dot.color }"
                                 >
                                    <div v-if="dot.isPlayer" class="ring"></div>
                                    <span class="label">{{ dot.name }}</span>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Competitor Intelligence -->
            <div class="strategy-column intelligence">
                <div class="panel-card glass alert-border">
                    <div class="card-header">
                        <span class="header-icon">📡</span>
                        <h3 class="l2-priority">
                            COMPETITIVE_INTELLIGENCE
                            <span class="v3-info-trigger" 
                                @mouseenter="tooltipStore.show($event, { title: 'COMPETITIVE_INTEL', content: 'Monitoring competitor market share and risk levels.', hint: 'Active competitors may attempt to undermine your operations.' })"
                                @mouseleave="tooltipStore.hide()"
                            >ⓘ</span>
                        </h3>
                        <div class="header-line"></div>
                    </div>
                    <div class="card-body scrollable">
                        <div class="intel-briefing">
                            <div v-for="npc in competitors" :key="npc.id" class="intel-card" :class="{ 'high-threat': npc.playerEnmity > 70 }">
                                <div class="intel-header">
                                    <div class="npc-marker" :style="{ backgroundColor: npc.color }"></div>
                                    <div class="npc-info">
                                        <span class="npc-name l1-priority">{{ npc.name }}</span>
                                        <span class="npc-archetype l3-priority">{{ npc.archetype.toUpperCase() }} // [INTEL_ACTIVE]</span>
                                    </div>
                                    <div class="npc-share l2-priority">
                                        DOMINANCE: {{ npc.marketShare.toFixed(1) }}%
                                        <span class="v3-info-trigger" 
                                            @mouseenter="tooltipStore.show($event, { title: 'MARKET_SHARE', content: 'The percentage of active customers currently served by this entity.', hint: 'Lower their share by expanding your regional footprint.' })"
                                            @mouseleave="tooltipStore.hide()"
                                        >ⓘ</span>
                                    </div>
                                </div>
                                <div class="intel-body">
                                    <div class="enmity-meter"
                                        @mouseenter="tooltipStore.show($event, { title: 'FEINDSELIGKEIT (ENMITY)', content: 'How likely this competitor is to attack you. Increases if you sabotage them.', hint: 'High enmity leads to DDOS and poaching attempts.' })"
                                        @mouseleave="tooltipStore.hide()"
                                    >
                                        <div class="meter-label l3-priority">THREAT_LEVEL</div>
                                        <div class="meter-track">
                                            <div class="meter-fill l1-priority" :style="{ width: npc.playerEnmity + '%', backgroundColor: getEnmityColor(npc.playerEnmity) }"></div>
                                        </div>
                                    </div>
                                    <div class="intel-footer" v-if="npc.lastAttackAt">
                                        <span class="last-atk">Zuletzt aktiv_ {{ formatDate(npc.lastAttackAt) }}</span>
                                    </div>
                                    
                                    <!-- RETALIATION MENU -->
                                    <div class="retaliation-zone">
                                        <div class="zone-label l2-priority">
                                            COMPETITIVE_MEASURES
                                            <span class="v3-info-trigger" 
                                                @mouseenter="tooltipStore.show($event, { title: 'COUNTER_STRATEGIES', content: 'Active measures to protect market share or disrupt competitors.', hint: 'Costs capital and carries detection risk.' })"
                                                @mouseleave="tooltipStore.hide()"
                                            >ⓘ</span>
                                        </div>
                                        <div class="ops-grid">
                                            <button 
                                                v-for="(type, key) in sabotageTypes" 
                                                :key="key" 
                                                class="ops-btn"
                                                :class="[type.category, { 'is-disabled': isAttacking || (key === 'patent_countersuit' && npc.playerEnmity < 50) }]"
                                                @click="executeRetaliation(npc.id, key)"
                                                @mouseenter="tooltipStore.show($event, { title: type.name, content: type.description, hint: 'Detection Risk: ' + type.detection_chance + '%' })"
                                                @mouseleave="tooltipStore.hide()"
                                            >
                                                <span class="btn-name">{{ type.name.split(' ')[0] }}</span>
                                                <span class="btn-cost">${{ Math.round(type.cost / 1000) }}k</span>
                                                <div v-if="key === 'patent_countersuit' && npc.playerEnmity < 50" class="lock-hint">ENMITY &lt; 50</div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-scanline"></div>
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
import api from '../../utils/api';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import { useTooltipStore } from '../../stores/tooltip';

const gameStore = useGameStore();
const toast = useToastStore();
const tooltipStore = useTooltipStore();

const availablePolicies = ref({});
const activePolicies = ref([]);
const sabotageTypes = ref({});
const activeAttackDetails = ref(null);
const isAttacking = ref(false);

// Specialization Logic
const playerEconomy = computed(() => gameStore.player?.economy || {});
const playerLevel = computed(() => playerEconomy.value.level || 1);
const activespec = computed(() => playerEconomy.value.specialization || null);
const hasSpecialization = computed(() => !!activespec.value && activespec.value !== 'balanced');
const canUnlockSpecialization = computed(() => !hasSpecialization.value && playerLevel.value >= 10);

// Competitor Intel
const competitors = computed(() => gameStore.marketShare?.participants || []);
const radarPositioning = computed(() => gameStore.marketShare?.positioning || []);

const getEnmityColor = (enmity) => {
    if (enmity > 75) return '#ff4d4f'; // Aggressive Red
    if (enmity > 40) return '#faad14'; // Warning Orange
    return '#52c41a'; // Neutral Green
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'NEVER';
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const specs = [
    { id: 'budget_mass', name: 'MASS_MARKET', icon: '📉', shortDesc: 'Volume over margin. Fast growth, Low Quality.' },
    { id: 'high_performance', name: 'PERFORMANCE_ELITE', icon: '⚡', shortDesc: 'Maximum Compute. High Income, High Cost.' },
    { id: 'eco_certified', name: 'ECO_CERTIFIED', icon: '🌿', shortDesc: 'Renewables focus. High Rep, Low Power Cost.' },
    { id: 'hpc_specialist', name: 'HPC_SPECIALIST', icon: '🧠', shortDesc: 'AI & Neural compute. Extreme margin, Extreme cooling.' },
    { id: 'crypto_vault', name: 'CRYPTO_VAULT', icon: '🔐', shortDesc: 'Private ledger hosting. High volatility, High security.' }
];

const getSpecIcon = (id) => specs.find(s => s.id === id)?.icon || '🏢';
const getSpecName = (id) => specs.find(s => s.id === id)?.name || id;
const getSpecDesc = (id) => specs.find(s => s.id === id)?.shortDesc || '';
const getSpecBuffs = (id) => {
    if (id === 'budget_mass') return ['-25% Hardware Setup Cost', '+40% Customer Churn Penalty', '+15% Experience Gain'];
    if (id === 'high_performance') return ['+30% Net Income Boost', '+10% Cooling Load penalty', 'Unlock "Extreme Computing" Contracts'];
    if (id === 'eco_certified') return ['-20% Power Cost', '+10% Reputation Gain', '-15% Customer Churn'];
    if (id === 'hpc_specialist') return ['+50% Net Income Boost', '+30% Cooling Load penalty', '8x ML Order Weight'];
    if (id === 'crypto_vault') return ['+120% Price Premium', '-50% Patience penalty', '+30% Bandwidth Drain'];
    return [];
};

const isProcessing = ref(false);
const confirmSpecialization = async (id) => {
    if (isProcessing.value) return;
    if (!confirm("Confirm Doctrine Selection? You will be committed to this strategy for 24 hours.")) return;
    
    isProcessing.value = true;
    try {
        const response = await api.post('/management/specialization', { specialization: id });
        if (response.success) {
            toast.success("CORPORATE STRATEGY ESTABLISHED");
            await gameStore.fetchPlayer(); // Refresh state
        } else {
            toast.error(response.error || "Failed to set specialization");
        }
    } catch (e) {
        const msg = e.response?.data?.error || e.message || "Failed to set specialization";
        toast.error(msg);
    } finally {
        isProcessing.value = false;
    }
};

const loadData = async () => {
    try {
        const [energyRes, sabotageRes] = await Promise.all([
            api.get('/energy'),
            api.get('/sabotage')
        ]);

        if (energyRes.success) {
            availablePolicies.value = energyRes.policies || {};
            activePolicies.value = energyRes.active_policies || [];
        }
        if (sabotageRes.success) {
            sabotageTypes.value = sabotageRes.types || {};
        }
    } catch (e) {
        console.error("Failed to load strategy data", e);
    }
};

const executeRetaliation = async (npcId, sabotageType) => {
    if (isAttacking.value) return;
    
    const type = sabotageTypes.value[sabotageType];
    if (!type) return;

    if (!confirm(`CONFIRM_OPERATION: ${type.name.toUpperCase()}?\nCOST: $${type.cost.toLocaleString()}\nRISK: ${type.detection_chance}% DETECTION`)) {
        return;
    }

    isAttacking.value = true;
    try {
        const response = await api.post('/sabotage/attempt', {
            target_id: npcId,
            target_type: 'competitor',
            sabotage_type: sabotageType
        });

        if (response.success) {
            const data = response.data;
            if (data.success) {
                toast.success(`OPERATION_SUCCESS: ${data.result.message || data.result.damage}`);
            } else {
                toast.error(`OPERATION_FAILED: ${data.result.message}`);
            }
            
            if (data.detected) {
                toast.error(`DETECTION_ALERT: YOU HAVE BEEN TRACED!`);
            }
            
            await gameStore.fetchPlayer();
        }
    } catch (e) {
        toast.error(e.response?.data?.error || "Connection failure during operation.");
    } finally {
        isAttacking.value = false;
    }
};

onMounted(() => {
    loadData();
});

const isPolicyActive = (key) => {
    return activePolicies.value.includes(key);
};

const togglePolicy = async (key) => {
    try {
        const response = await api.post('/energy/policy', { policy: key });
        if (response.success) {
            // Optimistic update or reload
            if (activePolicies.value.includes(key)) {
                activePolicies.value = activePolicies.value.filter(k => k !== key);
                toast.info(`Policy deactivated: ${availablePolicies.value[key].name}`);
            } else {
                activePolicies.value.push(key);
                toast.success(`Policy activated: ${availablePolicies.value[key].name}`);
            }
        }
    } catch (e) {
        toast.error('Failed to update policy');
        console.error(e);
    }
};

const formatMod = (val) => {
    const pct = Math.round((val - 1) * 100);
    return (pct > 0 ? '+' : '') + pct + '%';
};

const formatModRep = (val) => {
    // Rep mod is usually additive (e.g. 0.05)
    // Or multiplier? EnergyService says "reputation_mod" => 0.05
    const pct = Math.round(val * 100);
    return (pct > 0 ? '+' : '') + pct + '%';
};

const getModClass = (val, type) => {
    if (type === 'cost' || type === 'draw') {
        // Lower is better
        return val < 1 ? 'positive' : 'negative';
    }
    // Higher is better (Reputation)
    if (type === 'rep') {
        return val > 0 ? 'positive' : 'negative'; // val is additive
    }
    return '';
};
</script>

<style scoped>
/* --- NEW ORDERLY DESIGN STYLES --- */
.strategy-dashboard {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 24px;
    border-bottom: 1px dashed var(--v3-border-soft);
}

.section-title h2 {
    font-size: 1.4rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.2em;
    margin: 0;
    text-shadow: 0 0 10px rgba(255,255,255,0.2);
}

.subtitle {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    opacity: 0.8;
}

/* Header Stats Section */
.header-stats {
    display: flex;
    gap: 16px;
}

.stat-pill {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 10px 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    border-radius: 4px;
    position: relative;
    min-width: 140px;
}

.stat-pill::after {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0; width: 2px;
    background: var(--v3-accent);
    opacity: 0.5;
}

.stat-pill .label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.15em;
    margin-bottom: 4px;
}

.stat-pill .value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    font-family: var(--font-family-mono);
    line-height: 1;
    text-shadow: 0 0 10px rgba(47, 107, 255, 0.3);
}

/* Strategy Grid & Specialization Selection */
.strategy-grid {
    display: grid;
    grid-template-columns: 1fr 1.2fr 1fr;
    gap: 20px;
    height: calc(100vh - 250px);
    overflow: hidden;
}

.strategy-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
    min-width: 0; /* Fix flex overflow */
}

/* Card Improvements */
.panel-card.glass {
    background: rgba(10, 15, 25, 0.7);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.panel-card.glass::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
}

.card-header {
    padding: 14px 18px;
    background: rgba(255,255,255,0.03);
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-icon { font-size: 1rem; opacity: 0.8; }
.card-header h3 {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    color: var(--v3-text-secondary);
    margin: 0;
}

.header-line { flex: 1; height: 1px; background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent); }

.card-body { padding: 18px; flex: 1; display: flex; flex-direction: column; min-height: 0; }
.card-body.scrollable { overflow-y: auto; padding-right: 8px; flex: 1; }

/* Scrollbar styling */
.card-body.scrollable::-webkit-scrollbar { width: 4px; }
.card-body.scrollable::-webkit-scrollbar-track { background: transparent; }
.card-body.scrollable::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.strategy-column.intelligence .panel-card {
    flex: 1;
    min-height: 0;
}

/* Right Column: Policies (Protocols) */
.policy-stack { display: flex; flex-direction: column; gap: 12px; }
.policy-node {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 14px;
    border-radius: 4px;
    display: flex;
    gap: 14px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
}

.policy-node:hover {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.2);
    transform: translateX(4px);
}

.node-active {
    background: rgba(47, 107, 255, 0.08);
    border-color: var(--v3-accent);
}

.node-icon { font-size: 1.5rem; filter: grayscale(1); transition: 0.3s; }
.node-active .node-icon { filter: grayscale(0); transform: scale(1.1); }

.node-main { flex: 1; }
.node-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.node-title h4 { font-size: 0.85rem; font-weight: 800; color: #fff; margin: 0; }

.active-indicator {
    display: flex; align-items: center; gap: 4px;
    font-size: 0.55rem; font-weight: 900; color: var(--v3-accent);
    background: rgba(47, 107, 255, 0.2);
    padding: 2px 6px; border-radius: 2px;
}

.pulse-dot {
    width: 6px; height: 6px; background: var(--v3-accent); border-radius: 50%;
    animation: indicator-pulse 1.5s infinite;
}

.node-desc { font-size: 0.65rem; color: var(--v3-text-ghost); line-height: 1.4; margin-bottom: 8px; }
.node-metrics { display: flex; gap: 8px; }
.metric {
    font-size: 0.55rem; font-family: var(--font-family-mono); font-weight: 700;
    padding: 2px 6px; background: rgba(0,0,0,0.3); border-radius: 2px;
}
.metric.positive { color: var(--v3-success); }
.metric.negative { color: var(--v3-danger); }

/* Center: Doctrine & Positioning */
.specialization-display { display: flex; flex-direction: column; gap: 20px; }
.spec-banner {
    padding: 20px; border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.1);
    background: linear-gradient(135deg, rgba(255,255,255,0.05), transparent);
    position: relative;
}

.spec-banner.eco_certified { border-color: #00ff9d; box-shadow: 0 0 30px rgba(0,255,157,0.05); }
.spec-banner.high_performance { border-color: #2f6bff; box-shadow: 0 0 30px rgba(47,107,255,0.05); }
.spec-banner.budget_mass { border-color: #ffd700; box-shadow: 0 0 30px rgba(255,215,0,0.05); }

.spec-hero { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; }
.hero-icon { font-size: 2.2rem; }
.hero-text h4 { margin: 0; font-size: 1.1rem; color: #fff; letter-spacing: 0.1em; }
.hero-tag { font-size: 0.55rem; color: var(--v3-text-ghost); font-weight: 800; opacity: 0.7; }
.hero-desc { font-size: 0.75rem; color: var(--v3-text-secondary); line-height: 1.5; margin: 0; }

.buff-ledger { background: rgba(0,0,0,0.2); padding: 16px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); }
.ledger-label { font-size: 0.6rem; font-weight: 900; color: var(--v3-text-ghost); margin-bottom: 12px; letter-spacing: 0.1em; }
.ledger-items { display: flex; flex-direction: column; gap: 8px; }
.ledger-item {
    font-size: 0.7rem; color: #fff; font-family: var(--font-family-mono);
    display: flex; gap: 10px; align-items: center;
}
.ledger-item .check { color: var(--v3-accent); font-weight: 900; }

/* Specialization Selection UI */
.specialization-selection {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 10px 0;
}

.selection-alert {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--v3-accent);
    letter-spacing: 0.2em;
    text-align: center;
    background: rgba(47, 107, 255, 0.1);
    padding: 6px;
    border-radius: 2px;
    border: 1px dashed var(--v3-accent);
}

.selection-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.selection-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
}

.selection-btn:hover {
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.2);
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
}

.selection-btn .btn-icon {
    font-size: 2rem;
    transition: transform 0.3s;
}

.selection-btn:hover .btn-icon {
    transform: scale(1.2);
}

.selection-btn .btn-label {
    font-size: 0.65rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.selection-btn.is-processing {
    opacity: 0.6;
    cursor: wait;
    pointer-events: none;
}

.selection-btn.eco_certified:hover { border-color: #00ff9d; color: #00ff9d; }
.selection-btn.high_performance:hover { border-color: #2f6bff; color: #2f6bff; }
.selection-btn.budget_mass:hover { border-color: #ffd700; color: #ffd700; }
.selection-btn.hpc_specialist:hover { border-color: #ff00ff; color: #ff00ff; }
.selection-btn.crypto_vault:hover { border-color: #00ffff; color: #00ffff; }

.selection-btn.locked {
    cursor: default;
    opacity: 0.5;
    background: rgba(0,0,0,0.2);
    border-style: dashed;
}

.selection-btn.locked:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.05);
}

.selection-btn.locked .btn-icon {
    opacity: 0.3;
}

.lock-progress-mini {
    margin-top: 24px;
    padding: 16px;
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.03);
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    margin-bottom: 8px;
    letter-spacing: 0.1em;
}

.progress-info span:last-child {
    color: var(--v3-danger);
}

.lock-progress-mini .bar-bg {
    height: 3px;
    background: rgba(255,255,255,0.05);
    border-radius: 2px;
    overflow: hidden;
}

.lock-progress-mini .bar-fill {
    height: 100%;
    background: var(--v3-danger);
    box-shadow: 0 0 10px rgba(255, 77, 79, 0.4);
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.selection-alert.locked {
    background: rgba(255, 77, 79, 0.1);
    border: 1px dashed var(--v3-danger);
    color: var(--v3-danger);
}

.stat-pill.is-locked .value {
    color: var(--v3-danger);
    text-shadow: 0 0 10px rgba(255, 77, 79, 0.3);
}

.lock-ring {
    position: absolute;
    inset: 0;
    border: 2px solid rgba(255, 255, 255, 0.05);
    border-top-color: var(--v3-danger);
    border-radius: 50%;
    animation: spin 3s linear infinite;
}

.lock-msg h3 {
    font-size: 0.9rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
    margin: 0 0 8px 0;
}

.lock-msg p {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    line-height: 1.6;
    margin: 0;
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

.lock-msg span {
    color: var(--v3-danger);
    font-weight: 800;
}

.lock-status {
    width: 100%;
    max-width: 200px;
}

.status-label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    margin-bottom: 8px;
    letter-spacing: 0.05em;
}

.lock-progress .bar-bg {
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2px;
    overflow: hidden;
}

.lock-progress .bar-fill {
    height: 100%;
    background: var(--v3-danger);
    border-radius: 2px;
    box-shadow: 0 0 10px rgba(255, 77, 79, 0.3);
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-pill.is-locked .value {
    color: var(--v3-danger);
    text-shadow: 0 0 10px rgba(255, 77, 79, 0.3);
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Positioning Radar */
.panel-card.positioning { height: 260px; }
.position-radar { flex: 1; position: relative; margin-top: 10px; }
.radar-axes { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: 0.3; }
.axis { display: flex; justify-content: space-between; font-size: 0.5rem; font-weight: 900; color: var(--v3-text-ghost); }
.axis.v { flex-direction: column; height: 100%; align-items: center; }
.axis.h { width: 100%; padding: 0 10px; border-top: 1px solid rgba(255,255,255,0.15); }
.axis.v::before { content: ''; position: absolute; left: 50%; height: 100%; border-left: 1px solid rgba(255,255,255,0.15); }

.radar-content { position: absolute; inset: 0; }
.radar-dot {
    position: absolute; transform: translate(-50%, -50%);
    width: 6px; height: 6px; background: var(--dot-color, #fff); border-radius: 50%;
    box-shadow: 0 0 10px var(--dot-color, #fff);
    z-index: 2;
    transition: all 1s cubic-bezier(0.4, 0, 0.2, 1);
}
.player-dot {
    width: 8px; height: 8px; z-index: 5;
    background: #fff;
    box-shadow: 0 0 15px #fff;
}
.player-dot .ring {
    position: absolute; inset: -4px; border: 1px solid #fff; border-radius: 50%;
    animation: indicator-pulse 2s infinite;
}
.radar-dot .label {
    position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
    font-size: 0.5rem; font-weight: 900; white-space: nowrap; text-shadow: 0 0 5px #000;
    color: #fff;
    opacity: 0.7;
    pointer-events: none;
}
.player-dot .label { opacity: 1; font-weight: 950; text-shadow: 0 0 8px rgba(255,255,255,0.5); }
.npc-dot { opacity: 0.8; }

/* Right: Intelligence Briefing */
.intel-briefing { display: flex; flex-direction: column; gap: 14px; }
.intel-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 4px; padding: 14px;
    position: relative; overflow: hidden;
}

.intel-card.high-threat {
    background: linear-gradient(90deg, rgba(255, 77, 79, 0.05), transparent);
    border-left: 3px solid var(--v3-danger);
}

.intel-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.npc-marker { width: 4px; height: 24px; border-radius: 2px; }
.npc-info { flex: 1; display: flex; flex-direction: column; }
.npc-name { font-size: 0.8rem; font-weight: 800; color: #fff; }
.npc-archetype { font-size: 0.5rem; color: var(--v3-text-ghost); text-transform: uppercase; }
.npc-share { font-size: 0.9rem; font-family: var(--font-family-mono); font-weight: 700; color: var(--v3-accent); }

.meter-label { font-size: 0.5rem; color: var(--v3-text-ghost); font-weight: 900; margin-bottom: 4px; text-transform: uppercase; }
.meter-track { height: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden; }
.meter-fill { height: 100%; transition: width 1s ease-in-out; }

.intel-footer { margin-top: 10px; padding-top: 8px; border-top: 1px dashed rgba(255,255,255,0.05); }
.last-atk { font-size: 0.5rem; font-family: var(--font-family-mono); color: var(--v3-text-ghost); }

.card-scanline {
    position: absolute; inset: 0;
    background: linear-gradient(transparent 50%, rgba(255,255,255,0.01) 50%);
    background-size: 100% 4px;
    pointer-events: none;
}

/* Base Utility Classes */
.panel-card.alert-border { border-top: 3px solid var(--v3-accent); }

@keyframes indicator-pulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(3); opacity: 0; }
}

@keyframes scan-line {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}



/* Retaliation Counter-Ops */
.retaliation-zone {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.zone-label {
    font-size: 0.6rem;
    color: var(--v3-accent);
    letter-spacing: 2px;
    margin-bottom: 8px;
    font-weight: 800;
}

.ops-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

.ops-btn {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 6px 8px;
    text-align: left;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    border-radius: 2px;
}

.ops-btn:hover:not(.is-disabled) {
    background: rgba(255, 255, 255, 0.05);
    border-color: var(--v3-accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
}

.ops-btn.is-disabled {
    opacity: 0.3;
    cursor: not-allowed;
    filter: grayscale(1);
}

.ops-btn .btn-name {
    font-size: 0.65rem;
    font-weight: 700;
    color: #fff;
    text-transform: uppercase;
}

.ops-btn .btn-cost {
    font-size: 0.55rem;
    color: #ffd700;
    font-family: var(--font-family-mono);
}

.ops-btn .lock-hint {
    font-size: 0.5rem;
    color: var(--v3-danger);
    margin-top: 2px;
    font-weight: 800;
}

/* Category Specifics */
.ops-btn.network:hover { border-color: #c41eff; }
.ops-btn.intelligence:hover { border-color: #00f2ff; }
.ops-btn.social:hover { border-color: #ffd700; }
.ops-btn.legal:hover { border-color: #00ff9d; }

</style>
