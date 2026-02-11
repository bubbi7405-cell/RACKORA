<template>
    <footer class="bottom-hud">
        <div class="action-buttons">
            <button class="action-button" @click="refreshState" :disabled="isRefreshing">
                <span class="action-button__icon">
                    <svg :class="{ 'spin': isRefreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <polyline points="1 20 1 14 7 14"/>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                </span>
                <span class="action-button__label">Refresh</span>
            </button>

            <button class="action-button" @click="emit('openMarket')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </span>
                <span class="action-button__label">Market</span>
            </button>

            <button class="action-button" @click="emit('openCustomers')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <span class="action-button__label">Customers</span>
            </button>

            <button class="action-button" @click="emit('openUpgrades')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"/>
                        <line x1="18" y1="20" x2="18" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="16"/>
                    </svg>
                </span>
                <span class="action-button__label">Upgrades</span>
            </button>

            <button class="action-button" @click="emit('openStats')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                    </svg>
                </span>
                <span class="action-button__label">Statistics</span>
            </button>

            <button class="action-button" @click="emit('openEmployees')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                </span>
                <span class="action-button__label">Employees</span>
            </button>

            <button class="action-button" @click="emit('openAutomation')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="4 17 10 11 4 5"></polyline>
                        <line x1="12" y1="19" x2="20" y2="19"></line>
                    </svg>
                </span>
                <span class="action-button__label">Scripts</span>
            </button>
        </div>

        <!-- Time indicator -->
        <div class="time-indicator">
            <span class="time-indicator__icon">{{ isDay ? '☀️' : '🌙' }}</span>
            <span class="time-indicator__label">Game Time</span>
            <span class="time-indicator__value">{{ gameTime.formatted }}</span>
        </div>
    </footer>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const emit = defineEmits(['openMarket', 'openCustomers', 'openUpgrades', 'openStats', 'openEmployees', 'openAutomation']);

const gameStore = useGameStore();

const gameTime = computed(() => gameStore.player?.economy?.gameTime || { formatted: '00:00', hour: 0 });
const isDay = computed(() => gameTime.value.hour >= 6 && gameTime.value.hour < 20);

const isRefreshing = ref(false);

const refresh = async () => {
    isRefreshing.value = true;
    try {
        await gameStore.loadGameState();
    } finally {
        isRefreshing.value = false;
    }
};
</script>

<style scoped>
.bottom-hud {
    grid-area: bottom-hud;
    height: 70px;
    background: linear-gradient(0deg, rgba(15, 20, 25, 0.98) 0%, rgba(22, 27, 34, 0.95) 100%);
    border-top: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-xl);
    backdrop-filter: blur(8px);
}

.action-buttons {
    display: flex;
    gap: var(--space-md);
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: var(--space-sm) var(--space-lg);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
    min-width: 70px;
}

.action-button:hover:not(:disabled) {
    background: var(--color-primary-dim);
    border-color: var(--color-primary);
    transform: translateY(-2px);
}

.action-button:disabled {
    opacity: 0.5;
}

.action-button__icon {
    width: 20px;
    height: 20px;
    color: var(--color-primary);
}

.action-button__icon svg {
    width: 100%;
    height: 100%;
}

.action-button__icon svg.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.action-button__label {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.time-indicator {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.time-indicator__label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    text-transform: uppercase;
}

.time-indicator__value {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-lg);
    color: var(--color-primary);
}
</style>
