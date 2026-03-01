<template>
    <transition name="slide-down">
        <div v-if="crisis" class="global-crisis-bar" :class="crisis.phase">
            <div class="bar-content">
                <div class="crisis-icon">
                    <span v-if="crisis.type === 'solar_flare'">☀️</span>
                    <span v-else-if="crisis.type === 'fiber_cut'">🌐</span>
                    <span v-else-if="crisis.type === 'market_crash'">📉</span>
                    <span v-else-if="crisis.type === 'energy_crisis'">⚡</span>
                    <span v-else-if="crisis.type === 'hardware_shortage'">🦾</span>
                    <span v-else-if="crisis.type === 'crypto_ransom'">💀</span>
                    <span v-else-if="crisis.type === 'power_rationing'">🔌</span>
                    <span v-else-if="crisis.type === 'employee_strike'">🪧</span>
                    <span v-else>⚠️</span>
                </div>
                
                <div class="crisis-info">
                    <div class="title-row">
                        <span class="crisis-title">{{ getTitle }}</span>
                        <span class="phase-badge" :class="crisis.phase">{{ crisis.phase.toUpperCase() }}</span>
                    </div>
                    <div class="crisis-desc">{{ getDescription }}</div>
                </div>

                <div class="crisis-timer" v-if="timeLeft">
                    <div class="timer-label">{{ crisis.phase === 'warning' ? 'IMPACT IN' : 'REMAINING' }}</div>
                    <div class="timer-value">{{ timeLeft }}</div>
                </div>
            </div>
            
            <div class="progress-bar-container" v-if="progress > 0">
                <div class="progress-bar" :style="{ width: progress + '%' }"></div>
            </div>

            <div class="crisis-actions" v-if="getAvailableActions.length > 0">
                <button 
                    v-for="action in getAvailableActions" 
                    :key="action.id"
                    class="action-btn"
                    :class="{ 'taken': hasTakenAction(action.id) }"
                    :disabled="hasTakenAction(action.id)"
                    @click="takeAction(action.id)"
                >
                    <span class="btn-label">{{ action.label }}</span>
                    <span class="btn-cost" v-if="action.cost">{{ formatMoney(action.cost) }}</span>
                    <span class="btn-status" v-if="hasTakenAction(action.id)">ACTIVE</span>
                </button>
            </div>
        </div>
    </transition>

    <FiberCutMinigame 
        v-if="showFiberMinigame" 
        @close="showFiberMinigame = false" 
        @complete="handleMinigameComplete"
    />

    <StrikeNegotiationMgame 
        v-if="showStrikeMinigame" 
        @complete="handleStrikeComplete"
    />
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import FiberCutMinigame from '../Minigame/FiberCutMgame.vue';
import StrikeNegotiationMgame from '../Minigame/StrikeNegotiationMgame.vue';

const gameStore = useGameStore();

const crisis = computed(() => gameStore.activeCrisis);

const now = ref(Date.now());
const timerInterval = ref(null);
const showFiberMinigame = ref(false);
const showStrikeMinigame = ref(false);

const getTitle = computed(() => {
    if (!crisis.value) return '';
    if (crisis.value.data && crisis.value.data.name) return crisis.value.data.name;
    return crisis.value.type.replace('_', ' ').toUpperCase();
});

const getDescription = computed(() => {
    if (!crisis.value) return '';
    const map = {
        'solar_flare': 'Geomagnetic storm detected. High energy particles incoming.',
        'fiber_cut': 'Major undersea cable severed. Global network routing is failing.',
        'market_crash': 'Tech sector volatility high. Customer budgets slashed.',
        'energy_crisis': 'Energy prices are skyrocketing. Infrastructure cost is critical.',
        'hardware_shortage': 'Global semiconductor production halted. Server costs doubled.',
        'crypto_ransom': 'Ransomware group detected. Business operations compromised.',
        'power_rationing': 'Regional grid operator has mandated a 50% power quota.',
        'employee_strike': 'Your employees have organized a general strike!'
    };
    return map[crisis.value.type] || 'Global infrastructure alert.';
});

const timeLeft = computed(() => {
    if (!crisis.value) return null;
    
    let target = null;
    if (crisis.value.phase === 'warning') {
        target = new Date(crisis.value.impact_starts_at).getTime();
    } else if (crisis.value.phase === 'impact') {
        return null; 
    }
    
    if (!target) return null;
    
    const diff = target - now.value;
    if (diff <= 0) return '00:00';
    
    const minutes = Math.floor(diff / 60000);
    const seconds = Math.floor((diff % 60000) / 1000);
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

const progress = computed(() => {
    if (!crisis.value) return 0;
    
    const start = new Date(crisis.value.started_at).getTime();
    let target = null;
    
    if (crisis.value.phase === 'warning') {
        target = new Date(crisis.value.impact_starts_at).getTime();
    } else {
        return 0;
    }
    
    const total = target - start;
    const elapsed = now.value - start;
    return Math.min(100, Math.max(0, (elapsed / total) * 100));
});

const getAvailableActions = computed(() => {
    if (!crisis.value) return [];
    
    const actions = [];
    if (crisis.value.type === 'solar_flare') {
        actions.push({ id: 'activate_shield', label: 'Shielding', cost: 5000 });
        actions.push({ id: 'emergency_shutdown', label: 'Power Off', cost: 0 });
    } else if (crisis.value.type === 'fiber_cut') {
        actions.push({ id: 'reroute_traffic', label: 'Reroute', cost: 2000 });
        actions.push({ id: 'manual_reroute', label: 'Manual Redir', cost: 0 });
    } else if (crisis.value.type === 'crypto_ransom') {
        const level = gameStore.player?.economy?.level || 1;
        actions.push({ id: 'pay_ransom', label: 'Pay Ransom', cost: 1000 * level });
        actions.push({ id: 'restore_backups', label: 'Restore Backups', cost: 5000 });
        actions.push({ id: 'hacker_counterstrike', label: 'Counter-Strike', cost: 10000 });
    } else if (crisis.value.type === 'energy_crisis') {
        actions.push({ id: 'lobby_subsidies', label: 'Lobby for Subsidies', cost: 5000 });
    } else if (crisis.value.type === 'market_crash') {
        actions.push({ id: 'lobby_subsidies', label: 'Request Relief Package', cost: 5000 });
    } else if (crisis.value.type === 'hardware_shortage') {
        actions.push({ id: 'bulk_hardware_contract', label: 'Bulk supply contract', cost: 8000 });
    } else if (crisis.value.type === 'power_rationing') {
        actions.push({ id: 'emergency_shedding', label: 'Load Shedding', cost: 5000 });
    } else if (crisis.value.type === 'employee_strike') {
        actions.push({ id: 'negotiate_strike', label: 'Negotiate', cost: 0 });
    }
    
    // Global actions available during any crisis
    actions.push({ id: 'cooling_overdrive', label: 'Cooling Overdrive', cost: 1000 });
    
    return actions;
});

const hasTakenAction = (actionId) => {
    return crisis.value?.data?.actions_taken?.includes(actionId) || false;
};

const takeAction = async (actionId) => {
    if (actionId === 'manual_reroute') {
        showFiberMinigame.value = true;
        return;
    }
    if (actionId === 'negotiate_strike') {
        showStrikeMinigame.value = true;
        return;
    }
    await gameStore.takeCrisisAction(actionId);
};

const handleMinigameComplete = async (result) => {
    showFiberMinigame.value = false;
    await gameStore.submitFiberMinigame(result.success);
};

const handleStrikeComplete = async (result) => {
    showStrikeMinigame.value = false;
    await gameStore.submitStrikeNegotiation(result.outcome, result.success);
};

const formatMoney = (val) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val);
};

onMounted(() => {
    timerInterval.value = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval.value) clearInterval(timerInterval.value);
});
</script>

<style scoped>
.global-crisis-bar {
    position: absolute;
    top: 70px; /* Below TopBar (60px) + gap */
    left: 50%;
    transform: translateX(-50%);
    width: 600px;
    background: rgba(20, 20, 25, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    border: 1px solid var(--color-border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: hidden;
    pointer-events: auto;
}

.global-crisis-bar.warning {
    border-color: #ffcc00;
    box-shadow: 0 0 15px rgba(255, 204, 0, 0.3);
}

.global-crisis-bar.impact {
    border-color: #ff3333;
    background: rgba(40, 10, 10, 0.95);
    box-shadow: 0 0 20px rgba(255, 51, 51, 0.4); 
    animation: pulse-border 2s infinite;
}

@keyframes pulse-border {
    0% { border-color: #ff3333; box-shadow: 0 0 10px rgba(255, 51, 51, 0.4); }
    50% { border-color: #ff6666; box-shadow: 0 0 25px rgba(255, 51, 51, 0.7); }
    100% { border-color: #ff3333; box-shadow: 0 0 10px rgba(255, 51, 51, 0.4); }
}

.bar-content {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    gap: 15px;
}

.crisis-icon {
    font-size: 2rem;
}

.crisis-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.crisis-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: #fff;
    letter-spacing: 0.5px;
}

.phase-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
}

.phase-badge.warning {
    background: #ffcc00;
    color: #000;
}

.phase-badge.impact {
    background: #ff3333;
    color: #fff;
    animation: flash-text 1s infinite alternate;
}

.crisis-desc {
    font-size: 0.85rem;
    color: #aaa;
    margin-top: 2px;
}

.crisis-timer {
    text-align: right;
    min-width: 70px;
}

.timer-label {
    font-size: 0.65rem;
    color: #888;
    text-transform: uppercase;
}

.timer-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.warning .timer-value { color: #ffcc00; }
.impact .timer-value { color: #ff3333; }

.crisis-actions {
    display: flex;
    padding: 10px 20px;
    gap: 10px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.action-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px;
    background: var(--color-surface-soft);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover:not(:disabled) {
    background: var(--color-primary);
    border-color: var(--color-primary);
    transform: translateY(-2px);
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-btn.taken {
    background: #22c55e33;
    border-color: #22c55e;
    color: #22c55e;
}

.btn-label {
    font-size: 0.8rem;
    font-weight: 700;
}

.btn-cost {
    font-size: 0.7rem;
    color: var(--color-text-dim);
}

.btn-status {
    font-size: 0.6rem;
    font-weight: 800;
    margin-top: 2px;
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translate(-50%, -20px);
}

/* Mobile & Tablet Responsiveness */
@media (max-width: 768px) {
    .global-crisis-bar {
        width: 90%;
        top: 60px;
    }
    
    .crisis-icon {
        font-size: 1.5rem;
    }
    
    .crisis-title {
        font-size: 1rem;
    }
    
    .crisis-desc {
        display: none; /* Hide description on small screens */
    }
    
    .timer-value {
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .bar-content {
        padding: 8px 12px;
        gap: 10px;
    }
    
    .crisis-actions {
        padding: 8px 12px;
    }
    
    .btn-label {
        font-size: 0.7rem;
    }
}
</style>
