<template>
    <Transition name="overlay">
        <div 
            v-if="currentEvent" 
            class="event-overlay"
            :class="overlayClass"
        >
            <div class="event-card" :class="cardClass">
                <!-- Event Icon -->
                <div class="event-card__icon">
                    <svg v-if="currentEvent.type === 'power_outage'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    <svg v-else-if="currentEvent.type === 'overheating'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>
                    </svg>
                    <svg v-else-if="currentEvent.type === 'ddos_attack'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9"/>
                    </svg>
                    <svg v-else-if="currentEvent.type === 'hardware_failure'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="4" width="16" height="16" rx="2" ry="2"/>
                        <rect x="9" y="9" width="6" height="6"/>
                        <line x1="9" y1="1" x2="9" y2="4"/>
                        <line x1="15" y1="1" x2="15" y2="4"/>
                        <line x1="9" y1="20" x2="9" y2="23"/>
                        <line x1="15" y1="20" x2="15" y2="23"/>
                        <line x1="20" y1="9" x2="23" y2="9"/>
                        <line x1="20" y1="14" x2="23" y2="14"/>
                        <line x1="1" y1="9" x2="4" y2="9"/>
                        <line x1="1" y1="14" x2="4" y2="14"/>
                    </svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>

                <!-- Event Info -->
                <h2 class="event-card__title">{{ eventTitle }}</h2>
                <p class="event-card__description">{{ eventDescription }}</p>

                <!-- Timer -->
                <div class="event-card__timer-label">Time Remaining</div>
                <div class="event-card__timer" :class="{ 'event-card__timer--critical': remainingSeconds < 30 }">
                    {{ formattedTime }}
                </div>

                <!-- Affected entities -->
                <div v-if="currentEvent.affectedServers?.length" class="affected-entities">
                    <span class="affected-entities__label">Affected:</span>
                    <span class="affected-entities__count">{{ currentEvent.affectedServers.length }} server(s)</span>
                </div>

                <!-- Action Buttons -->
                <div class="event-actions">
                    <button 
                        v-for="action in currentEvent.actions" 
                        :key="action.id"
                        class="event-action-btn"
                        :class="{ 'event-action-btn--primary': action.isPrimary }"
                        @click="resolveEvent(action.id)"
                        :disabled="isResolving || !canAffordAction(action)"
                    >
                        <div class="event-action-btn__info">
                            <div class="event-action-btn__name">{{ action.name }}</div>
                            <div class="event-action-btn__desc">{{ action.description }}</div>
                        </div>
                        <div class="event-action-btn__meta">
                            <div v-if="action.cost > 0" class="event-action-btn__cost">${{ action.cost.toLocaleString() }}</div>
                            <div class="event-action-btn__success">{{ Math.round(action.successChance * 100) }}% success</div>
                        </div>
                    </button>
                </div>

                <!-- Skip/Ignore -->
                <button class="event-skip-btn" @click="dismissEvent">
                    Let it fail (consequences will apply)
                </button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';

const gameStore = useGameStore();
const { events, player } = storeToRefs(gameStore);

const isResolving = ref(false);
const remainingSeconds = ref(0);
let timerInterval = null;

// Get the most urgent event
const currentEvent = computed(() => {
    if (!events.value.active || events.value.active.length === 0) return null;
    
    // Sort by deadline, return most urgent
    const sorted = [...events.value.active].sort((a, b) => {
        return new Date(a.deadline) - new Date(b.deadline);
    });
    
    return sorted[0];
});

const overlayClass = computed(() => {
    if (!currentEvent.value) return '';
    if (currentEvent.value.status === 'escalated' || remainingSeconds.value < 30) {
        return 'event-overlay--critical';
    }
    return 'event-overlay--warning';
});

const cardClass = computed(() => {
    if (!currentEvent.value) return '';
    if (currentEvent.value.status === 'escalated') return 'event-card--escalated';
    return '';
});

const eventTitle = computed(() => {
    if (!currentEvent.value) return '';
    const titles = {
        power_outage: 'POWER OUTAGE',
        overheating: 'CRITICAL OVERHEATING',
        ddos_attack: 'DDoS ATTACK DETECTED',
        hardware_failure: 'HARDWARE FAILURE',
        network_outage: 'NETWORK OUTAGE',
        security_breach: 'SECURITY BREACH',
        cooling_failure: 'COOLING SYSTEM FAILURE',
        bandwidth_spike: 'BANDWIDTH OVERLOAD',
    };
    return titles[currentEvent.value.type] || 'CRISIS EVENT';
});

const eventDescription = computed(() => {
    if (!currentEvent.value) return '';
    const descriptions = {
        power_outage: 'Immediate power loss detected. Servers are running on UPS backup. Take action before batteries deplete!',
        overheating: 'Temperature exceeds safe operating limits. Servers at risk of thermal shutdown and damage!',
        ddos_attack: 'Massive traffic flood detected targeting your infrastructure. Service degradation imminent!',
        hardware_failure: 'Critical hardware component has failed. Affected servers are at risk of data loss!',
        network_outage: 'Network connectivity issues detected. External services may be unreachable!',
        security_breach: 'Unauthorized access attempt detected. Customer data may be at risk!',
        cooling_failure: 'Primary cooling system offline. Temperatures rising rapidly!',
        bandwidth_spike: 'Bandwidth utilization exceeding capacity. Service quality degrading!',
    };
    return descriptions[currentEvent.value.type] || 'A critical event requires your immediate attention.';
});

const formattedTime = computed(() => {
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

function canAffordAction(action) {
    if (!action.cost) return true;
    return player.value.economy.balance >= action.cost;
}

async function resolveEvent(actionId) {
    if (!currentEvent.value || isResolving.value) return;
    
    isResolving.value = true;
    await gameStore.resolveEvent(currentEvent.value.id, actionId);
    isResolving.value = false;
}

function dismissEvent() {
    // User chooses to let the event fail
    // In a real implementation, this would trigger auto-fail
}

function updateTimer() {
    if (!currentEvent.value?.deadline) return;
    
    const deadline = new Date(currentEvent.value.deadline);
    const now = new Date();
    const diff = Math.max(0, Math.floor((deadline - now) / 1000));
    remainingSeconds.value = diff;
}

onMounted(() => {
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});
</script>

<style scoped>
.event-overlay {
    position: fixed;
    inset: 0;
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10, 13, 20, 0.9);
    backdrop-filter: blur(8px);
    pointer-events: none;
}

.event-overlay--warning {
    animation: overlay-pulse-warning 3s ease-in-out infinite;
}

.event-overlay--critical {
    animation: overlay-pulse-critical 1s ease-in-out infinite;
}

@keyframes overlay-pulse-warning {
    0%, 100% { background: rgba(10, 13, 20, 0.9); }
    50% { background: rgba(245, 158, 11, 0.15); }
}

@keyframes overlay-pulse-critical {
    0%, 100% { 
        background: rgba(10, 13, 20, 0.9);
        box-shadow: inset 0 0 100px rgba(239, 68, 68, 0.1);
    }
    50% { 
        background: rgba(239, 68, 68, 0.2);
        box-shadow: inset 0 0 200px rgba(239, 68, 68, 0.2);
    }
}

.event-card {
    pointer-events: auto;
    background: var(--color-bg-dark);
    border: 2px solid var(--color-warning);
    border-radius: var(--radius-xl);
    padding: var(--space-2xl);
    max-width: 550px;
    width: 95%;
    text-align: center;
    animation: card-appear 0.4s ease;
}

.event-card--escalated {
    border-color: var(--color-danger);
    animation: card-shake 0.5s ease;
}

@keyframes card-appear {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes card-shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.event-card__icon {
    width: 80px;
    height: 80px;
    margin: 0 auto var(--space-lg);
    padding: var(--space-md);
    border-radius: 50%;
    background: var(--color-danger-dim);
}

.event-card__icon svg {
    width: 100%;
    height: 100%;
    color: var(--color-danger);
}

.event-card__title {
    font-size: var(--font-size-2xl);
    font-weight: 700;
    color: var(--color-danger);
    margin-bottom: var(--space-sm);
    text-transform: uppercase;
    letter-spacing: 0.15em;
}

.event-card__description {
    color: var(--color-text-secondary);
    margin-bottom: var(--space-lg);
    line-height: 1.6;
}

.event-card__timer-label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: var(--space-xs);
}

.event-card__timer {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-4xl);
    font-weight: 700;
    color: var(--color-warning);
    margin-bottom: var(--space-lg);
}

.event-card__timer--critical {
    color: var(--color-danger);
    animation: timer-flash 0.5s steps(1) infinite;
}

@keyframes timer-flash {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.5; }
}

.affected-entities {
    display: flex;
    justify-content: center;
    gap: var(--space-sm);
    margin-bottom: var(--space-lg);
    font-size: var(--font-size-sm);
}

.affected-entities__label {
    color: var(--color-text-muted);
}

.affected-entities__count {
    color: var(--color-danger);
    font-weight: 600;
}

.event-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    margin-bottom: var(--space-lg);
}

.event-action-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-md) var(--space-lg);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
    text-align: left;
}

.event-action-btn:hover:not(:disabled) {
    background: var(--color-primary-dim);
    border-color: var(--color-primary);
    transform: translateX(4px);
}

.event-action-btn:disabled {
    opacity: 0.4;
}

.event-action-btn--primary {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.event-action-btn__name {
    font-weight: 600;
    margin-bottom: 2px;
}

.event-action-btn__desc {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
}

.event-action-btn__meta {
    text-align: right;
    min-width: 80px;
}

.event-action-btn__cost {
    font-family: var(--font-family-mono);
    font-weight: 600;
    color: var(--color-warning);
}

.event-action-btn__success {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
}

.event-skip-btn {
    color: var(--color-text-muted);
    font-size: var(--font-size-sm);
    text-decoration: underline;
    opacity: 0.7;
    transition: opacity var(--transition-fast);
}

.event-skip-btn:hover {
    opacity: 1;
}

/* Transition */
.overlay-enter-active {
    animation: overlay-fade-in 0.3s ease;
}

.overlay-leave-active {
    animation: overlay-fade-out 0.3s ease;
}

@keyframes overlay-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes overlay-fade-out {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>
