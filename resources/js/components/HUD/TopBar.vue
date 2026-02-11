<template>
    <header class="top-bar">
        <!-- Left section - Logo & Money -->
        <div class="hud-section">
            <div class="game-logo">
                <span class="game-logo__text">Server</span>
                <span class="game-logo__accent">Tycoon</span>
            </div>

            <div class="hud-stat hud-stat--money" @click="openFinance" role="button" tabindex="0">
                <div class="hud-stat__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                <div>
                    <div class="hud-stat__value">${{ formatMoney(economy.balance) }}</div>
                    <div class="hud-stat__label" :class="{ 'text-success': netIncome > 0, 'text-danger': netIncome < 0 }">
                        {{ netIncome >= 0 ? '+' : '' }}${{ formatMoney(netIncome) }}/hr
                    </div>
                </div>
            </div>
        </div>

        <!-- Center section - Stats -->
        <div class="hud-section">
            <div class="hud-stat" :class="{ 'hud-stat--warning': stats.uptime < 99 }">
                <div class="hud-stat__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                </div>
                <div>
                    <div class="hud-stat__value">{{ stats.onlineServers }}/{{ stats.totalServers }}</div>
                    <div class="hud-stat__label">Servers Online</div>
                </div>
            </div>

            <div class="hud-stat">
                <div class="hud-stat__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div>
                    <div class="hud-stat__value">{{ customers.active }}</div>
                    <div class="hud-stat__label">Customers</div>
                </div>
            </div>

            <div class="hud-stat" :class="{ 'hud-stat--danger': orders.urgentCount > 0 }">
                <div class="hud-stat__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="hud-stat__value">{{ orders.pending.length }}</div>
                    <div class="hud-stat__label">Pending Orders</div>
                </div>
            </div>

            <div v-if="activeEventCount > 0" class="hud-stat hud-stat--danger event-indicator">
                <div class="hud-stat__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <div class="hud-stat__value">{{ activeEventCount }}</div>
                    <div class="hud-stat__label">Active Crisis!</div>
                </div>
            </div>
        </div>

        <!-- Right section - Player & Reputation -->
        <div class="hud-section">
            <div class="hud-stat">
                <div class="hud-stat__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <div>
                    <div class="hud-stat__value">{{ Math.round(economy.reputation) }}</div>
                    <div class="hud-stat__label">Reputation</div>
                </div>
            </div>

            <div class="player-info">
                <div class="player-level">
                    <span class="player-level__badge">Level {{ economy.level }}</span>
                    <div class="player-level__xp-bar">
                        <div 
                            class="player-level__xp-fill" 
                            :style="{ width: economy.experience.progress + '%' }"
                        ></div>
                    </div>
                </div>
                <button class="player-avatar" @click="openSettings">
                    <span>{{ playerInitial }}</span>
                </button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import { useAuthStore } from '../../stores/auth';
import { storeToRefs } from 'pinia';

const gameStore = useGameStore();
const authStore = useAuthStore();
const emit = defineEmits(['openFinance', 'openSettings']);

import SoundManager from '../../services/SoundManager';

const { player, customers, orders, stats, activeEventCount } = storeToRefs(gameStore);



const economy = computed(() => player.value.economy);
const netIncome = computed(() => economy.value.netIncomePerHour);

const playerInitial = computed(() => {
    return authStore.user?.name?.charAt(0).toUpperCase() || 'P';
});

function openFinance() {
    SoundManager.playClick();
    emit('openFinance');
}

function openSettings() {
    SoundManager.playClick();
    emit('openSettings');
}

function formatMoney(value) {
    if (value >= 1000000) {
        return (value / 1000000).toFixed(2) + 'M';
    }
    if (value >= 1000) {
        return (value / 1000).toFixed(1) + 'K';
    }
    return value.toFixed(0);
}
</script>

<style scoped>
.top-bar {
    grid-area: top-bar;
    height: 60px;
    background: linear-gradient(180deg, rgba(22, 27, 34, 0.98) 0%, rgba(15, 20, 25, 0.95) 100%);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-lg);
    backdrop-filter: blur(8px);
    z-index: 100;
}

.game-logo {
    font-size: 1.25rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-right: var(--space-lg);
}

.game-logo__text {
    color: var(--color-text-primary);
}

.game-logo__accent {
    color: var(--color-primary);
}

.hud-section {
    display: flex;
    align-items: center;
    gap: var(--space-md);
}

.hud-stat {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-xs) var(--space-md);
    background: rgba(33, 38, 45, 0.6);
    border-radius: var(--radius-md);
    border: 1px solid transparent;
}

.hud-stat__icon {
    width: 20px;
    height: 20px;
    color: var(--color-primary);
}

.hud-stat__icon svg {
    width: 100%;
    height: 100%;
}

.hud-stat__value {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-lg);
    font-weight: 600;
    line-height: 1.2;
}

.hud-stat__label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.hud-stat--money .hud-stat__value {
    color: var(--color-success);
}

.hud-stat--money {
    cursor: pointer;
    transition: background-color 0.2s;
}

.hud-stat--money:hover {
    background: rgba(33, 38, 45, 0.9);
    border-color: #30363d;
}

.hud-stat--warning {
    border-color: var(--color-warning);
}

.hud-stat--warning .hud-stat__icon {
    color: var(--color-warning);
}

.hud-stat--danger {
    border-color: var(--color-danger);
    animation: pulse-border 1s ease-in-out infinite;
}

.hud-stat--danger .hud-stat__icon {
    color: var(--color-danger);
}

@keyframes pulse-border {
    0%, 100% { border-color: var(--color-danger); }
    50% { border-color: transparent; }
}

.event-indicator {
    background: var(--color-danger-dim);
}

.player-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--color-bg-deep);
    transition: transform var(--transition-fast);
}

.player-avatar:hover {
    transform: scale(1.05);
}
</style>
