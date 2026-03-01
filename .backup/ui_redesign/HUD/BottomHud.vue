<template>
    <footer class="bottom-hud">
        <div class="action-buttons">
            <button class="action-button" @click="refresh" :disabled="isRefreshing">
                <span class="action-button__icon">
                    <svg :class="{ 'spin': isRefreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <polyline points="1 20 1 14 7 14"/>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                </span>
                <span class="action-button__label">Refresh</span>
            </button>

            <button class="action-button" @click="handleAction('openMarket')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </span>
                <span class="action-button__label">Market</span>
            </button>

            <button class="action-button" @click="handleAction('openCustomers')">
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

            <button class="action-button" @click="handleAction('openUpgrades')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"/>
                        <line x1="18" y1="20" x2="18" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="16"/>
                    </svg>
                </span>
                <span class="action-button__label">Upgrades</span>
            </button>

            <button class="action-button" @click="handleAction('openStats')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                    </svg>
                </span>
                <span class="action-button__label">Statistics</span>
            </button>

            <button class="action-button" @click="handleAction('openEmployees')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                </span>
                <span class="action-button__label">Employees</span>
            </button>

            <button class="action-button" @click="handleAction('openAutomation')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="4 17 10 11 4 5"></polyline>
                        <line x1="12" y1="19" x2="20" y2="19"></line>
                    </svg>
                </span>
                <span class="action-button__label">Scripts</span>
            </button>

            <button class="action-button" @click="handleAction('openCompliance')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 7V3M21 7V3M3 7V3M21 17v4M3 17v4M12 17v4M13.5 10H15M9 10H10.5M12 14v-4"/>
                        <circle cx="12" cy="7" r="1"/>
                        <circle cx="21" cy="7" r="1"/>
                        <circle cx="3" cy="7" r="1"/>
                    </svg>
                </span>
                <span class="action-button__label">Compliance</span>
            </button>

            <button class="action-button" @click="handleAction('openEnergy')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                    </svg>
                </span>
                <span class="action-button__label">Energy</span>
            </button>

            <button class="action-button" @click="handleAction('openLogs')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </span>
                <span class="action-button__label">Logs</span>
            </button>

            <button class="action-button action-button--urgent" @click="handleAction('openIncidents')">
                <span class="action-button__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </span>
                <span class="action-button__label">Incidents</span>
            </button>
        </div>

        <div class="right-section">
            <div 
                v-if="pendingOrdersCount > 0" 
                class="status-group" 
                :class="{ 'status-group--urgent': urgentOrdersCount > 0 }"
                @click="emit('openCustomers')"
                title="View Pending Orders"
            >
                <div class="status-icon">📦</div>
                <div class="status-info">
                    <span class="status-label">Orders</span>
                    <span class="status-value" :class="{ 'urgent-text': urgentOrdersCount > 0 }">
                        {{ pendingOrdersCount }}
                    </span>
                </div>
            </div>

            <!-- Corp Logo -->
            <div class="hud-logo">
                <img src="/images/logo.png" alt="Corp" />
            </div>

            <!-- Time indicator -->
            <div class="time-indicator">
                <span class="time-indicator__icon">{{ isDay ? '☀️' : '🌙' }}</span>
                <span class="time-indicator__label">Game Time</span>
                <span class="time-indicator__value">{{ gameTime.formatted }}</span>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const emit = defineEmits(['openMarket', 'openCustomers', 'openUpgrades', 'openStats', 'openEmployees', 'openAutomation', 'openEnergy', 'openLogs', 'openIncidents', 'openCompliance']);

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

const handleAction = (action) => {
    emit(action);
};

const pendingOrdersCount = computed(() => gameStore.orders?.pending?.length || 0);
const urgentOrdersCount = computed(() => gameStore.orders?.urgentCount || 0);
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
    z-index: 1500;
}

.action-buttons {
    display: flex;
    gap: var(--space-md);
}

.right-section {
    display: flex;
    align-items: center;
    gap: var(--space-xl);
}

.hud-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    padding-right: var(--space-md);
    border-right: 1px solid rgba(255,255,255,0.1);
}

.hud-logo img {
    height: 100%;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 0 2px rgba(255,255,255,0.3));
    transition: transform 0.2s;
}

.hud-logo img:hover {
    transform: scale(1.1);
    filter: drop-shadow(0 0 5px rgba(255,255,255,0.6));
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

.action-button--urgent {
    border-color: rgba(244, 67, 54, 0.3);
}

.action-button--urgent:hover:not(:disabled) {
    background: rgba(244, 67, 54, 0.15);
    border-color: #f44336;
}

.action-button--urgent .action-button__icon {
    color: #f44336;
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

.status-group {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
}

.status-group:hover {
    background: var(--color-bg-light);
    transform: translateY(-1px);
}

.status-group--urgent {
    border-color: var(--color-danger);
    background: rgba(244, 67, 54, 0.1);
    animation: pulse-border 2s infinite;
}

.status-icon { font-size: 1.2rem; }

.status-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.status-label {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.status-value {
    font-family: var(--font-family-mono);
    font-weight: bold;
    color: var(--color-text-primary);
    font-size: 1.1rem;
    line-height: 1;
}

.urgent-text { color: var(--color-danger); }

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

@keyframes pulse-border {
    0% { border-color: var(--color-danger); box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.4); }
    70% { border-color: var(--color-danger); box-shadow: 0 0 0 6px rgba(244, 67, 54, 0); }
    100% { border-color: var(--color-danger); box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
}
</style>
