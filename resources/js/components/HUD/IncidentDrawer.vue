<template>
    <div class="incident-system" :class="{ 'is-collapsed': collapsed }">
        <!-- Trigger Button -->
        <button class="incident-trigger" @click="isOpen = !isOpen"
            :class="{ 'has-alerts': activeIncidents.length > 0 }">
            <span class="trigger-icon">⚠</span>
            <span v-if="!collapsed" class="trigger-label">SYSTEM_ALERTS</span>
            <span v-if="activeIncidents.length > 0" class="incident-count">{{ activeIncidents.length }}</span>
        </button>

        <!-- Tactical Operational Risk Console -->
        <transition name="slide-right">
            <div v-if="isOpen" class="incident-drawer shadow-2xl">
                <div class="drawer-scanline"></div>
                
                <header class="drawer-header">
                    <div class="header-left">
                        <span class="status-indicator" :class="{ 'is-compromised': activeIncidents.length > 0 }"></span>
                        <h3 class="l2-priority">OPERATIONAL_RISK_LOG</h3>
                    </div>
                    <button class="clear-all l3-priority" @click="isOpen = false">[DISMISS]</button>
                </header>

                <div class="drawer-content">
                    <div v-if="activeIncidents.length === 0" class="empty-incidents">
                        <span class="icon l3-priority">◈</span>
                        <p class="l2-priority">SCANNING_STABLE</p>
                        <span class="subtext l3-priority">No operational compromises detected in this sector.</span>
                    </div>
                    <div v-else class="incident-list">
                        <div v-for="incident in activeIncidents" :key="incident.id" class="incident-item"
                            :class="[incident.severity, { 'l1-priority': incident.severity === 'critical' }]">
                            <div class="incident-marker"></div>
                            <div class="incident-body">
                                <div class="incident-meta">
                                    <span class="severity-badge">{{ getAggressiveSeverity(incident.severity) }}</span>
                                    <span class="incident-time">{{ formatTime(incident.started_at) }}</span>
                                </div>
                                <div class="incident-title">{{ incident.title?.toUpperCase() }}</div>
                                <div class="incident-desc l3-priority">{{ incident.description }}</div>
                                <div class="incident-action l1-priority" v-if="incident.severity === 'critical'">// MANUAL_INTERVENTION_REQUIRED</div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="drawer-footer">
                    <button class="history-btn l2-priority" @click="isOpen = false">VIEW_THREAT_HISTORY</button>
                </footer>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';

const props = defineProps({
    collapsed: { type: Boolean, default: false }
});

const gameStore = useGameStore();
const isOpen = ref(false);

const activeIncidents = computed(() => {
    return gameStore.events.active.map(e => ({
        id: e.id,
        title: e.title,
        description: e.description,
        severity: e.severity || 'info',
        started_at: e.starts_at
    }));
});

const formatTime = (time) => {
    if (!time) return '----';
    const d = new Date(time);
    return isNaN(d.getTime()) ? '----' : d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const getAggressiveSeverity = (sev) => {
    if (sev === 'critical') return 'COMPROMISED';
    if (sev === 'warning') return 'RISK_DETECTED';
    return 'NOMINAL_LOG';
};
</script>

<style scoped>
.incident-system {
    position: relative;
    padding: 8px 12px;
}

.incident-trigger {
    width: 100%;
    height: 40px;
    padding: 0 16px;
    background: rgba(255, 77, 79, 0.05);
    border: 1px solid rgba(255, 77, 79, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all var(--v3-transition-base);
    border-radius: 2px;
    position: relative;
}

.is-collapsed .incident-trigger {
    padding: 0;
    justify-content: center;
}

.incident-trigger:hover {
    background: rgba(255, 77, 79, 0.1);
    border-color: rgba(255, 77, 79, 0.3);
}

.incident-trigger.has-alerts {
    border-color: var(--v3-danger);
    background: rgba(255, 77, 79, 0.15);
    animation: v3-pulse-state 1.5s infinite var(--v3-easing);
}

.trigger-icon {
    font-size: 0.9rem;
    color: var(--v3-danger);
}

.trigger-label {
    font-size: 0.55rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    color: #fff;
    white-space: nowrap;
}

.incident-count {
    background: var(--v3-danger);
    color: #fff;
    font-size: 0.5rem;
    font-weight: 900;
    padding: 1px 5px;
    border-radius: 2px;
    margin-left: auto;
    box-shadow: 0 0 10px var(--v3-danger);
}

.is-collapsed .incident-count {
    position: absolute;
    top: -2px;
    right: -2px;
    margin: 0;
    font-size: 0.45rem;
}

.incident-drawer {
    position: fixed;
    top: 80px; /* TopBar height */
    left: var(--v3-sidebar-width);
    bottom: 80px; /* BottomHud height */
    width: 360px;
    background: linear-gradient(90deg, var(--ds-bg-void) 0%, rgba(10, 15, 25, 0.98) 100%);
    border-right: 2px solid rgba(255, 255, 255, 0.1);
    box-shadow: 20px 0 60px rgba(0, 0, 0, 0.9);
    overflow: hidden;
    z-index: var(--zi-hud-base);
    display: flex;
    flex-direction: column;
    transition: left var(--v3-transition-base);
}

.drawer-scanline {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.15) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.02), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.02));
    background-size: 100% 2px, 3px 100%;
    pointer-events: none;
    z-index: 10;
}

.is-collapsed .incident-drawer {
    left: var(--v3-sidebar-collapsed);
}

.drawer-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.02);
}

.status-indicator.is-compromised {
    background: var(--ds-critical);
    box-shadow: 0 0 10px var(--ds-critical);
    animation: ds-blink 0.5s infinite;
}

.incident-item {
    display: flex;
    padding: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    transition: all 0.2s;
}

.incident-item.critical {
    background: rgba(239, 68, 68, 0.03);
}

.incident-action {
    font-size: 0.55rem;
    font-weight: 950;
    margin-top: 12px;
    letter-spacing: 0.1em;
}

.severity-badge {
    font-size: 0.5rem;
    font-weight: 950;
    letter-spacing: 0.1em;
    padding: 2px 6px;
    border-radius: 1px;
    background: rgba(255, 255, 255, 0.05);
}

@keyframes ds-blink {
    50% { opacity: 0.3; }
}
</style>
