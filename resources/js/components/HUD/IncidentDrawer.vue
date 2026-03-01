<template>
    <div class="incident-system" :class="{ 'is-collapsed': collapsed }">
        <!-- Trigger Button -->
        <button class="incident-trigger" @click="isOpen = !isOpen"
            :class="{ 'has-alerts': activeIncidents.length > 0 }">
            <span class="trigger-icon">⚠</span>
            <span v-if="!collapsed" class="trigger-label">SYSTEM_ALERTS</span>
            <span v-if="activeIncidents.length > 0" class="incident-count">{{ activeIncidents.length }}</span>
        </button>

        <!-- Right-sliding Drawer -->
        <transition name="slide-right">
            <div v-if="isOpen" class="incident-drawer shadow-2xl">
                <header class="drawer-header">
                    <div class="header-left">
                        <span class="status-indicator"></span>
                        <h3>INCIDENT_LOG</h3>
                    </div>
                    <button class="clear-all" @click="isOpen = false">CLOSE</button>
                </header>

                <div class="drawer-content">
                    <div v-if="activeIncidents.length === 0" class="empty-incidents">
                        <span class="icon">✓</span>
                        <p>ALL SYSTEMS NOMINAL</p>
                        <span class="subtext">No active service disruptions detected.</span>
                    </div>
                    <div v-else class="incident-list">
                        <div v-for="incident in activeIncidents" :key="incident.id" class="incident-item"
                            :class="incident.severity">
                            <div class="incident-marker"></div>
                            <div class="incident-body">
                                <div class="incident-meta">
                                    <span class="severity-badge">{{ incident.severity?.toUpperCase() }}</span>
                                    <span class="incident-time">{{ formatTime(incident.started_at) }}</span>
                                </div>
                                <div class="incident-title">{{ incident.title?.toUpperCase() }}</div>
                                <div class="incident-desc">{{ incident.description }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="drawer-footer">
                    <button class="history-btn" @click="isOpen = false">ARCHIVE_ACCESS</button>
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
    return isNaN(d.getTime()) ? '----' : d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
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
    top: 48px;
    /* TopBar height */
    left: var(--v3-sidebar-width);
    bottom: 0;
    width: 320px;
    background: var(--v3-bg-overlay);
    border-right: var(--v3-border-heavy);
    box-shadow: 20px 0 50px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    z-index: 2500;
    display: flex;
    flex-direction: column;
    transition: left var(--v3-transition-base);
}

.is-collapsed .incident-drawer {
    left: var(--v3-sidebar-collapsed);
}

.drawer-header {
    padding: 16px 20px;
    border-bottom: var(--v3-border-soft);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(0, 0, 0, 0.2);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-indicator {
    width: 4px;
    height: 12px;
    background: var(--v3-danger);
    border-radius: 1px;
}

.drawer-header h3 {
    font-size: 0.6rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: var(--v3-text-secondary);
}

.clear-all {
    font-size: 0.55rem;
    font-weight: 800;
    color: var(--v3-text-ghost);
    text-transform: uppercase;
    cursor: pointer;
}

.clear-all:hover {
    color: #fff;
}

.drawer-content {
    flex: 1;
    overflow-y: auto;
}

.empty-incidents {
    padding: 60px 40px;
    text-align: center;
    color: var(--v3-text-ghost);
}

.empty-incidents .icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 16px;
    color: var(--v3-success);
    opacity: 0.4;
}

.empty-incidents .subtext {
    font-size: 0.55rem;
    opacity: 0.5;
    margin-top: 8px;
    display: block;
}

.incident-list {
    display: flex;
    flex-direction: column;
}

.incident-item {
    display: flex;
    padding: 20px;
    border-bottom: var(--v3-border-soft);
    position: relative;
    transition: background var(--v3-transition-fast);
}

.incident-item:hover {
    background: rgba(255, 255, 255, 0.02);
}

.incident-marker {
    width: 3px;
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
}

.incident-item.critical .incident-marker {
    background: var(--v3-danger);
}

.incident-item.warning .incident-marker {
    background: var(--v3-warning);
}

.incident-item.info .incident-marker {
    background: var(--v3-accent);
}

.incident-body {
    flex: 1;
}

.incident-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.severity-badge {
    font-size: 0.5rem;
    font-weight: 900;
    padding: 1px 4px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--v3-text-secondary);
}

.critical .severity-badge {
    background: rgba(255, 77, 79, 0.1);
    color: var(--v3-danger);
}

.incident-title {
    font-weight: 800;
    font-size: 0.7rem;
    color: #fff;
    margin-bottom: 4px;
    letter-spacing: 0.05em;
}

.incident-desc {
    font-size: 0.65rem;
    color: var(--v3-text-secondary);
    margin-bottom: 10px;
    line-height: 1.5;
}

.incident-time {
    font-size: 0.55rem;
    font-family: var(--font-family-mono);
    color: var(--v3-text-ghost);
}

.drawer-footer {
    padding: 16px 20px;
    background: var(--v3-bg-base);
    border-top: var(--v3-border-soft);
}

.history-btn {
    width: 100%;
    font-size: 0.6rem;
    font-weight: 800;
    color: #fff;
    text-align: center;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.03);
    border: var(--v3-border-soft);
    padding: 10px;
    cursor: pointer;
    letter-spacing: 0.1em;
}

.history-btn:hover {
    background: rgba(255, 255, 255, 0.08);
}

/* Animations */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform var(--v3-transition-base);
}

.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(-100%);
}

@keyframes v3-pulse-state {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.7;
    }
}
</style>
