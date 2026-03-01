<template>
    <Transition name="overlay">
        <div 
            v-if="events.resolvedSummary" 
            class="event-overlay event-overlay--resolved"
        >
            <div class="event-card event-card--resolved">
                <div class="grade-medal" :class="'grade--' + events.resolvedSummary.managementGrade">
                    <span class="grade-medal__label">GRADE</span>
                    <span class="grade-medal__char">{{ events.resolvedSummary.managementGrade }}</span>
                </div>

                <h2 class="event-card__title text-success">Incident Resolved</h2>
                <div class="score-display">
                    <div class="score-display__value">{{ events.resolvedSummary.managementScore }}</div>
                    <div class="score-display__label">Performance Score</div>
                </div>

                <div class="resolution-stats">
                    <div class="res-stat">
                        <span class="res-stat__label">Response Time:</span>
                        <span class="res-stat__value">{{ formatDuration(events.resolvedSummary.timing.warningAt, events.resolvedSummary.timing.resolvedAt) }}</span>
                    </div>
                    <div class="res-stat">
                        <span class="res-stat__label">Mitigation Cost:</span>
                        <span class="res-stat__value">${{ events.resolvedSummary.actionCost.toLocaleString() }}</span>
                    </div>
                    <div class="res-stat">
                        <span class="res-stat__label">Collateral Impact:</span>
                        <span class="res-stat__value text-danger">${{ events.resolvedSummary.damageCost.toLocaleString() }}</span>
                    </div>
                </div>

                <div v-if="events.resolvedSummary.postMortem?.lessons?.length > 0" class="post-mortem">
                    <div class="post-mortem__title">POST-MORTEM ANALYSIS</div>
                    <ul class="post-mortem__list">
                        <li v-for="(lesson, index) in events.resolvedSummary.postMortem.lessons" :key="index">
                            {{ lesson }}
                        </li>
                    </ul>
                </div>

                <div class="event-resolution-actions">
                    <button class="event-action-btn event-action-btn--primary" @click="gameStore.closeResolvedSummary">
                        Mission Accomplished
                    </button>
                    
                    <button 
                        v-if="events.resolvedSummary.replay_data?.length > 0"
                        class="event-action-btn event-action-btn--secondary" 
                        @click="showReplay = true"
                    >
                        Review Blackbox Recording
                    </button>
                </div>

                <IncidentReplay 
                    v-if="showReplay" 
                    :event="events.resolvedSummary" 
                    @close="showReplay = false" 
                />
            </div>
        </div>

        <div 
            v-else-if="currentEvent" 
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

                <!-- Stress Meter (Multi-event pressure) -->
                <div v-if="activeEventsCount > 1" class="stress-meter">
                    <div class="stress-meter__label">
                        <span class="blink">⚠️</span> MULTI-INCIDENT STRESS <span class="blink">⚠️</span>
                    </div>
                    <div class="stress-meter__description">
                        Critical load! Your team is overwhelmed. All deadlines are accelerating by <b>{{ (activeEventsCount - 1) * 30 }}s</b> per tick.
                    </div>
                    <div class="stress-meter__bar">
                        <div class="stress-meter__fill" :style="{ width: Math.min(100, (activeEventsCount - 1) * 25) + '%' }"></div>
                    </div>
                </div>

                <!-- Timer -->
                <div class="event-card__timer-label">Time Remaining</div>
                <div class="event-card__timer" :class="{ 'event-card__timer--critical': remainingSeconds < 30 }">
                    {{ formattedTime }}
                </div>

                <!-- Affected entities -->
                <div v-if="currentEvent.affected_customers_count > 0" class="affected-entities">
                    <span class="affected-entities__label">Affected Customers:</span>
                    <span class="affected-entities__count">{{ currentEvent.affected_customers_count }}</span>
                </div>

                <!-- Action Buttons -->
                <div class="event-actions">
                    <button 
                        v-for="action in currentEvent.available_actions" 
                        :key="action.id"
                        class="event-action-btn"
                        :class="getActionClass(action)"
                        @click="resolveEvent(action.id)"
                        :disabled="isResolving || !canAffordAction(action)"
                    >
                        <div class="event-action-btn__info">
                            <div class="event-action-btn__name-row">
                                <span class="event-action-btn__name">{{ action.label }}</span>
                                <span v-if="getActionBadge(action)" class="action-badge" :class="'action-badge--' + getActionBadgeType(action)">
                                    {{ getActionBadge(action) }}
                                </span>
                            </div>
                            <div class="event-action-btn__desc">{{ action.description }}</div>
                        </div>
                        <div class="event-action-btn__meta">
                            <div v-if="action.cost > 0" class="event-action-btn__cost">${{ action.cost.toLocaleString() }}</div>
                            <div v-else class="event-action-btn__cost text-success">FREE</div>
                            <div class="event-action-btn__success">{{ action.success_chance }}% success</div>
                        </div>
                    </button>
                </div>

                <!-- Skip/Ignore -->
                <button class="event-skip-btn" @click="dismissEvent">
                    Let it fail (reputation & SLA penalties will apply)
                </button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useGameStore } from '../../stores/game';
import IncidentReplay from '../HUD/IncidentReplay.vue';

const gameStore = useGameStore();

// Replace storeToRefs with direct computed properties
const events = computed(() => gameStore.events);
const player = computed(() => gameStore.player);

const isResolving = ref(false);
const showReplay = ref(false);
const remainingSeconds = ref(0);
let timerInterval = null;

// Get the most urgent event
const currentEvent = computed(() => {
    if (!events.active || events.active.length === 0) return null;
    
    // Sort by deadline, return most urgent
    const sorted = [...events.active].sort((a, b) => {
        return new Date(a.deadline_at) - new Date(b.deadline_at);
    });
    
    return sorted[0];
});

const activeEventsCount = computed(() => events.active?.length || 0);

const overlayClass = computed(() => {
    if (!currentEvent.value) return '';
    if (currentEvent.value.severity === 'critical' || remainingSeconds.value < 60) {
        return 'event-overlay--critical';
    }
    return 'event-overlay--warning';
});

const cardClass = computed(() => {
    if (!currentEvent.value) return '';
    if (currentEvent.value.status === 'escalated' || currentEvent.value.severity === 'critical') return 'event-card--escalated';
    return '';
});

const eventTitle = computed(() => {
    if (!currentEvent.value) return '';
    return currentEvent.value.title;
});

const eventDescription = computed(() => {
    if (!currentEvent.value) return '';
    return currentEvent.value.description;
});

function formatDuration(start, end) {
    const s = new Date(start);
    const e = new Date(end);
    const diff = Math.floor((e - s) / 1000);
    const mins = Math.floor(diff / 60);
    const secs = diff % 60;
    return `${mins}m ${secs}s`;
}

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
    // Just close for now, let it auto-fail in background
    gameStore.closeEventOverlay();
}

function updateTimer() {
    if (!currentEvent.value?.deadline_at) return;
    
    const deadline = new Date(currentEvent.value.deadline_at);
    const now = new Date();
    const diff = Math.max(0, Math.floor((deadline - now) / 1000));
    remainingSeconds.value = diff;
}

// Dilemma helpers
function getActionClass(action) {
    if (action.id.includes('quick') || action.id.includes('blackhole')) return 'event-action-btn--warning';
    if (action.success_chance >= 90 && action.cost > 0) return 'event-action-btn--primary';
    return '';
}

function getActionBadge(action) {
    if (action.id.includes('quick')) return 'RISKY';
    if (action.id.includes('blackhole')) return 'SAFE (DOWNTIME)';
    if (action.id.includes('premium') || action.id.includes('replace_drive')) return 'RECOMMENDED';
    if (action.success_chance < 50) return 'UNRELIABLE';
    return null;
}

function getActionBadgeType(action) {
    if (action.id.includes('quick')) return 'danger';
    if (action.id.includes('blackhole')) return 'warning';
    if (action.id.includes('premium')) return 'success';
    return 'info';
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

.event-overlay--resolved {
    background: rgba(10, 13, 20, 0.95);
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

/* Resolution Grade Styles */
.grade-medal {
    width: 120px;
    height: 120px;
    margin: 0 auto var(--space-lg);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 4px solid var(--color-text-muted);
    background: rgba(255, 255, 255, 0.05);
    position: relative;
    box-shadow: 0 0 30px rgba(0,0,0,0.5);
}

.grade-medal__label {
    font-size: 10px;
    font-weight: 800;
    opacity: 0.6;
    letter-spacing: 0.2em;
}

.grade-medal__char {
    font-size: 60px;
    font-weight: 900;
    line-height: 1;
}

.grade--S { border-color: #ffd700; color: #ffd700; box-shadow: 0 0 40px rgba(255, 215, 0, 0.3); }
.grade--A { border-color: #c0c0c0; color: #c0c0c0; }
.grade--B { border-color: #cd7f32; color: #cd7f32; }
.grade--C { border-color: #4a9eff; color: #4a9eff; }
.grade--D { border-color: #ff8c00; color: #ff8c00; }
.grade--F { border-color: var(--color-danger); color: var(--color-danger); }

.score-display {
    margin-bottom: var(--space-xl);
}

.score-display__value {
    font-size: 48px;
    font-weight: 800;
    font-family: var(--font-family-mono);
}

.score-display__label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    text-transform: uppercase;
}

.resolution-stats {
    background: rgba(255,255,255,0.03);
    border-radius: var(--radius-md);
    padding: var(--space-lg);
    margin-bottom: var(--space-xl);
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    text-align: left;
}

.res-stat {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-sm);
}

.res-stat__label { color: var(--color-text-muted); }
.res-stat__value { font-weight: 600; font-family: var(--font-family-mono); }

.post-mortem {
    margin-bottom: var(--space-xl);
    text-align: left;
    background: rgba(0, 212, 255, 0.05);
    border: 1px solid var(--color-primary-dim);
    border-radius: var(--radius-md);
    padding: var(--space-md);
}

.post-mortem__title {
    font-size: var(--font-size-xs);
    font-weight: 800;
    color: var(--color-primary);
    letter-spacing: 0.1em;
    margin-bottom: var(--space-sm);
}

.post-mortem__list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.post-mortem__list li {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    padding-left: 1.25rem;
    position: relative;
    line-height: 1.4;
    margin-bottom: var(--space-xs);
}

.post-mortem__list li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: var(--color-primary);
}

.text-success { color: var(--color-success); }

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

/* Stress Meter */
.stress-meter {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    margin-bottom: var(--space-lg);
    text-align: left;
}

.stress-meter__label {
    font-size: var(--font-size-sm);
    font-weight: 800;
    color: var(--color-danger);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stress-meter__description {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
    margin-bottom: var(--space-sm);
}

.stress-meter__bar {
    height: 6px;
    background: var(--color-bg-dark);
    border-radius: 3px;
    overflow: hidden;
}

.stress-meter__fill {
    height: 100%;
    background: linear-gradient(90deg, var(--color-danger), #ff4d4d);
    transition: width 0.5s ease;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.blink {
    animation: blink 1s infinite;
}

/* Action Grid Enhancements */
.event-action-btn__name-row {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin-bottom: 2px;
}

.action-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.action-badge--danger {
    background: var(--color-danger-dim);
    color: var(--color-danger);
    border: 1px solid var(--color-danger);
}

.action-badge--warning {
    background: var(--color-warning-dim);
    color: var(--color-warning);
    border: 1px solid var(--color-warning);
}

.action-badge--success {
    background: var(--color-success-dim);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.action-badge--info {
    background: var(--color-primary-dim);
    color: var(--color-primary);
    border: 1px solid var(--color-primary);
}

.event-action-btn--warning {
    border-color: var(--color-warning);
}

.event-action-btn--warning:hover:not(:disabled) {
    background: var(--color-warning-dim);
    border-color: var(--color-warning);
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
