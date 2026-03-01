<template>
    <header class="v2-topbar">
        <div class="v2-status-group">
            <div class="v2-stat-item is-supporting"
                v-tooltip="{ title: 'Uplink-Status', content: 'Zeigt die Verbindung zum Spielserver an.', hint: 'Grün bedeutet Echtzeit-Synchronisation.' }">
                <span class="v2-stat-label">UPLINK</span>
                <span class="v2-stat-value" :class="{ 'is-success': wsConnected, 'is-danger': !wsConnected }">
                    {{ wsConnected ? 'LIVE' : 'OFFLINE' }}
                </span>
            </div>
            <div class="v2-stat-item is-supporting"
                v-tooltip="{ title: 'Operator ID', content: 'Dein registrierter Unternehmensname im Netzwerk.' }">
                <span class="v2-stat-label">OPERATOR</span>
                <span class="v2-stat-value">{{ player?.companyName || 'R_ALPHA' }}</span>
            </div>
            <div class="v2-stat-item is-secondary"
                v-tooltip="{ title: 'Systemzeit', content: 'Aktuelle Zeit in der Simulationsinstanz.', hint: 'Wichtig für Energie-Preisphasen.' }">
                <span class="v2-stat-label">SYSTEM_TIME</span>
                <span class="v2-stat-value">{{ formattedTime }}</span>
            </div>
            <WeatherWidget />
        </div>

        <div class="v2-center-monitoring">
            <MarketAlertTicker />
            <WorldNewsTicker compact />
        </div>

        <div class="v2-status-group">
            <div class="v2-stat-item is-primary"
                v-tooltip="{ title: 'Kapital', content: 'Deine liquiden Mittel für Hardware und Expansion.', hint: 'Kredite erhöhen deine monatlichen Fixkosten!' }">
                <span class="v2-stat-label">CAPITAL</span>
                <span class="v2-stat-value is-success">${{ formatMoney(economy.balance) }}</span>
            </div>
            <div class="v2-stat-item is-secondary"
                v-tooltip="{ title: 'Netto-Cashflow', content: 'Stündlicher Gewinn oder Verlust nach Abzug aller Betriebskosten.', hint: 'Achte auf deine Energiepreise!' }">
                <span class="v2-stat-label">NET_FLOW</span>
                <span class="v2-stat-value" :class="{ 'is-success': netIncome > 0, 'is-danger': netIncome < 0 }">
                    {{ netIncome >= 0 ? '+' : '' }}{{ formatMoney(netIncome) }}
                </span>
            </div>

            <div class="v2-divider"></div>

            <ControlCenter @openMarketing="$emit('openMarketing')" @openLeaderboard="$emit('openLeaderboard')"
                @openRoadmap="$emit('openRoadmap')" @openAnalytics="$emit('openAnalytics')"
                @openAchievements="$emit('openAchievements')" @openFinance="$emit('openFinance')"
                @openReplay="$emit('openReplay')" @openSettings="$emit('openProfile')"
                @openCustomers="$emit('openCustomers')" @openEmployees="$emit('openEmployees')" />

            <a v-if="player?.is_admin" href="/admin" target="_blank" class="v2-stat-item admin-button"
                v-tooltip="'Access Obsidian Live-Ops'">
                <span class="v2-stat-label" style="color:#ef4444">LIVE_OPS</span>
                <span class="v2-stat-value" style="color:#fca5a5">OBSIDIAN</span>
            </a>

            <button class="v2-profile-area" @click="$emit('openProfile')">
                <div class="v2-stat-item is-supporting">
                    <span class="v2-stat-label">RANK</span>
                    <span class="v2-stat-value">LVL_{{ player?.economy?.level || 1 }}</span>
                </div>
                <div class="v2-avatar-glow"></div>
            </button>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';
import WorldNewsTicker from './WorldNewsTicker.vue';
import MarketAlertTicker from './MarketAlertTicker.vue';
import WeatherWidget from './WeatherWidget.vue';
import ControlCenter from './ControlCenter.vue';

const gameStore = useGameStore();

const player = computed(() => gameStore.player || {});
const economy = computed(() => gameStore.player?.economy || {});
const lastUpdate = computed(() => gameStore.lastUpdate);
const wsConnected = computed(() => gameStore.wsConnected);

defineEmits(['openProfile', 'openMarketing', 'openLeaderboard', 'openRoadmap', 'openAnalytics', 'openAchievements', 'openFinance', 'openReplay', 'openCustomers', 'openEmployees']);

const formattedTime = computed(() => {
    if (!lastUpdate.value) return '--:--:--';
    try {
        const d = new Date(lastUpdate.value);
        if (isNaN(d.getTime())) return '--:--:--';
        return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) {
        return '--:--:--';
    }
});

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
</script>

<style scoped>
.v2-topbar {
    height: var(--topbar-height);
    background: var(--color-base);
    border-bottom: var(--border-ui);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-xl);
    z-index: 1000;
}

.bar-section {
    display: flex;
    align-items: center;
    gap: 32px;
    height: 100%;
}

.bar-section.center {
    flex: 1;
    justify-content: center;
    gap: 40px;
}

/* System Uplink */
.system-uplink {
    display: flex;
    align-items: center;
    gap: 8px;
}

.uplink-pip {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--color-danger);
    box-shadow: 0 0 8px var(--color-danger);
}

.uplink-pip.online {
    background: var(--color-success);
    box-shadow: 0 0 10px var(--color-success);
    animation: pulse-status 2s infinite;
}

.uplink-label {
    font-size: 0.55rem;
    font-weight: 900;
    color: var(--color-muted);
    letter-spacing: 0.15em;
}

@keyframes pulse-status {

    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: 0.6;
        transform: scale(0.9);
    }
}

/* Company Badge */
.company-badge {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.corp-name {
    font-size: 0.75rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 0.05em;
}

.corp-id {
    font-size: 0.5rem;
    font-family: var(--font-mono);
    color: var(--color-muted);
    opacity: 0.5;
}

/* Runtime Monitor */
.monitor-seq {
    display: flex;
    align-items: center;
    gap: 16px;
    height: 24px;
    position: relative;
}

.seq-line {
    width: 40px;
    height: 1px;
    background: var(--color-accent);
    position: relative;
    overflow: hidden;
}

.seq-line::after {
    content: '';
    position: absolute;
    left: -100%;
    top: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, #fff, transparent);
    animation: scanline 3s infinite linear;
}

.seq-label {
    font-size: 0.55rem;
    font-weight: 800;
    color: var(--color-muted);
    letter-spacing: 0.2em;
}

.seq-value {
    font-size: 0.75rem;
    font-family: var(--font-mono);
    color: var(--color-accent);
    font-weight: 800;
}

@keyframes scanline {
    0% {
        left: -100%;
    }

    100% {
        left: 100%;
    }
}

/* Finance Stat Group */
.stat-group {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 6px 16px;
    background: rgba(255, 255, 255, 0.02);
    border: var(--border-dim);
    border-radius: 2px;
}

.stat-mini {
    display: flex;
    flex-direction: column;
}

.m-label {
    font-size: 0.5rem;
    font-weight: 900;
    color: var(--color-muted);
    letter-spacing: 0.1em;
    margin-bottom: 2px;
}

.m-val {
    font-size: 0.8rem;
    font-family: var(--font-mono);
    font-weight: 800;
    color: #fff;
}

.m-val.pos {
    color: var(--color-success);
}

.m-val.neg {
    color: var(--color-danger);
}

.sep {
    width: 1px;
    height: 20px;
    background: var(--border-dim);
}

/* Profile Trigger */
.profile-trigger {
    display: flex;
    align-items: center;
    gap: 12px;
}

.level-badge {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--color-muted);
    border: var(--border-dim);
    padding: 2px 6px;
    border-radius: 2px;
}

.avatar-box {
    width: 32px;
    height: 32px;
    background: var(--color-muted);
    opacity: 0.1;
    border: var(--border-ui);
    transition: var(--transition-subtle);
}

.profile-trigger:hover .avatar-box {
    opacity: 0.3;
    border-color: #fff;
    transform: scale(1.05);
}

.divider {
    display: none;
}
</style>
