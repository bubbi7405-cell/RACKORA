<template>
    <header class="v2-topbar">
        <!-- High-level Tier 1: Player Power -->
        
        <div class="v2-identity-block l1-priority" @click="$emit('openProfile')">
            <div class="v2-rank-module">
                <span class="rank-tag">TIER</span>
                <span class="rank-id">{{ economy.level || 1 }}</span>
            </div>
                <div class="v2-title-node l1-priority">
                    <span class="prefix">RANK //</span>
                    <span class="designation">{{ getExecutiveTitle(economy.level || 1) }}</span>
                </div>
                <div class="v2-progression-track">
                    <div class="v2-xp-bar-bg">
                        <div class="v2-xp-bar-fill" :style="{ width: (economy.experience?.progress || 0) + '%' }"></div>
                    </div>
                    <span class="v2-xp-val l2-priority">GROWTH_INDEX: {{ Math.round(economy.experience?.progress || 0) }}%</span>
                </div>
            </div>

        <div class="v2-strategic-context">
            <StrategicDirective :active-view="activeView" @navigate="$emit('openView', $event)" />
        </div>

        <div class="v2-capital-block">
            <div class="v2-capital-stat l1-priority" :class="balancePulse">
                <div class="v2-cap-label l3-priority">OPERATING_CAPITAL</div>
                <div class="v2-cap-main">
                    <span class="curr">$</span>
                    <span class="val">{{ formatMoneyFull(economy.balance) }}</span>
                </div>
            </div>
            
            <div class="v2-momentum-stat" :class="{ 'is-gain': netIncome > 0, 'is-loss': netIncome < 0 }">
                <div class="v2-mom-label l3-priority">NET_YIELD</div>
                <div class="v2-mom-main l2-priority">
                    <span class="v-dir">{{ netIncome >= 0 ? '▲' : '▼' }}</span>
                    <span class="v-val">{{ formatMoney(Math.abs(netIncome)) }}/HR</span>
                </div>
            </div>

            <div class="v2-cmd-divider"></div>

            <div class="v2-sys-actions">
                <button class="v2-sys-btn l3-priority" @click="$emit('openNocWall')">OPERATIONS</button>
                <button class="v2-sys-btn l3-priority" @click="$emit('openFinance')">FINANCE</button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import StrategicDirective from './StrategicDirective.vue';

const gameStore = useGameStore();
const props = defineProps({
    activeView: { type: String, default: 'overview' }
});

const economy = computed(() => gameStore.player?.economy || {});
const lastUpdate = computed(() => gameStore.lastUpdate);

const balancePulse = ref(null);
watch(() => economy.value.balance, (newVal, oldVal) => {
    if (newVal > oldVal) balancePulse.value = 'growth-pulse';
    else if (newVal < oldVal) balancePulse.value = 'decay-pulse';
    setTimeout(() => { balancePulse.value = null; }, 800);
});

defineEmits(['openNocWall', 'openProfile', 'openFinance', 'openView']);

const netIncome = computed(() => {
    const inc = economy.value?.hourlyIncome || 0;
    const exp = economy.value?.hourlyExpenses || 0;
    return inc - exp;
});

function formatMoney(value) {
    if (value === undefined || value === null) return '0';
    if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
    if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
    return Math.floor(value).toLocaleString();
}

function formatMoneyFull(value) {
     if (value === undefined || value === null) return '0';
     return Math.floor(value).toLocaleString('de-DE');
}

const getExecutiveTitle = (level) => {
    const titles = [
        'JUNIOR_ASSOCIATE', 'SENIOR_ASSOCIATE', 'OPERATIONS_MANAGER',
        'SITE_SUPERVISOR', 'FACILITY_DIRECTOR', 'DIVISION_DIRECTOR',
        'REGIONAL_MANAGER', 'SENIOR_DIRECTOR', 'MANAGING_DIRECTOR',
        'REGION_VP', 'EXECUTIVE_VP', 'MANAGING_PARTNER',
        'GENERAL_PARTNER', 'SENIOR_PARTNER', 'CHIEF_OPERATING_OFFICER',
        'PRESIDENT', 'CHIEF_EXECUTIVE', 'BOARD_MEMBER',
        'BOARD_DIRECTOR', 'MANAGING_CHAIRMAN', 'BOARD_CHAIRMAN',
        'PRINCIPAL_CONTROLLER', 'GLOBAL_DIRECTOR', 'MARKET_CONTROLLER',
        'MAJORITY_OWNER', 'CHIEF_CONTROLLER', 'LEAD_STRATEGIST',
        'SENIOR_EXECUTIVE', 'CHIEF_ARBITER', 'GLOBAL_CHAIRMAN'
    ];
    return titles[Math.min(level - 1, titles.length - 1)] || 'SENIOR_EXECUTIVE';
};
</script>

<style scoped>
.v2-topbar {
    height: 64px;
    background: var(--ds-topbar-bg);
    border-bottom: 1px solid var(--ds-topbar-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: relative;
    z-index: 1000;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.v2-scanlines { display: none; }

.v2-identity-block { display: flex; align-items: center; gap: 16px; cursor: pointer; transition: opacity 0.2s; }
.v2-identity-block:hover { opacity: 0.8; }

.v2-rank-module {
    width: 40px; height: 40px; background: var(--ds-accent);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    border-radius: var(--ds-radius-md); clip-path: none;
}

.rank-tag { font-size: 0.5rem; font-weight: 700; color: rgba(255,255,255,0.7); letter-spacing: 0.04em; }
.rank-id { font-size: 1.25rem; font-weight: 800; line-height: 1; color: #fff; }

.v2-id-meta { display: flex; flex-direction: column; gap: 2px; }
.v2-title-node { display: flex; gap: 6px; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0; }
.v2-title-node .prefix { color: var(--ds-text-ghost); }
.v2-title-node .designation { color: var(--ds-text-primary); }

.v2-progression-track { display: flex; align-items: center; gap: 8px; }
.v2-xp-bar-bg { width: 120px; height: 4px; background: var(--ds-bg-hover); border-radius: var(--ds-radius-full); overflow: hidden; }
.v2-xp-bar-fill { height: 100%; background: var(--ds-accent); border-radius: var(--ds-radius-full); transition: width 1s ease; }
.v2-xp-val { font-size: 0.6875rem; font-weight: 600; color: var(--ds-text-muted); }

.v2-strategic-context { flex: 1; display: flex; justify-content: center; }

.v2-capital-block { display: flex; align-items: center; gap: 32px; }
.v2-capital-stat { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
.v2-cap-label { font-size: 0.6875rem; font-weight: 600; color: var(--ds-text-ghost); letter-spacing: 0.02em; }

.v2-cap-main { display: flex; align-items: baseline; gap: 4px; }
.v2-cap-main .curr { font-size: 1rem; color: var(--ds-nominal); font-weight: 700; }
.v2-cap-main .val { font-size: 1.75rem; font-weight: 800; line-height: 1; color: var(--ds-text-primary); letter-spacing: -0.02em; font-family: var(--ds-font-mono); }

.v2-momentum-stat { display: flex; flex-direction: column; align-items: flex-end; }
.v2-mom-label { font-size: 0.6875rem; font-weight: 600; color: var(--ds-text-ghost); letter-spacing: 0.02em; }
.v2-mom-main { display: flex; align-items: center; gap: 4px; font-size: 0.875rem; font-weight: 600; font-family: var(--ds-font-mono); }

.is-gain { color: var(--ds-nominal); }
.is-loss { color: var(--ds-critical); }

.v2-cmd-divider { width: 1px; height: 32px; background: var(--ds-border-color); margin: 0 8px; }
.v2-sys-actions { display: flex; gap: 8px; }

.v2-sys-btn {
    font-family: var(--ds-font-sans); font-size: 0.8125rem; font-weight: 600; padding: 8px 14px;
    background: var(--ds-bg-subtle); border: 1px solid var(--ds-border-color); color: var(--ds-text-secondary);
    cursor: pointer; transition: all 0.15s; border-radius: var(--ds-radius-md);
}

.v2-sys-btn:hover { background: var(--ds-bg-hover); color: var(--ds-text-primary); border-color: #CBD5E1; }

.growth-pulse .val { animation: ds-growth 0.6s ease; }
.decay-pulse .val { animation: ds-decay 0.6s ease; }

@keyframes ds-growth { 0% { color: var(--ds-nominal); transform: scale(1.03); } 100% { color: var(--ds-text-primary); transform: scale(1); } }
@keyframes ds-decay { 0% { color: var(--ds-critical); transform: scale(0.97); } 100% { color: var(--ds-text-primary); transform: scale(1); } }
</style>
