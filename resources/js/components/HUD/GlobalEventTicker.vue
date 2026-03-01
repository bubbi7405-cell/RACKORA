<template>
    <div class="global-event-ticker" v-if="activeEvents.length > 0 && !isDismissed">
        <div class="ticker-header" v-if="!isMinimized">
            <span class="header-label">SYSTEM_ALERT</span>
            <div class="header-actions">
                <button class="action-btn" @click="isMinimized = true" title="Minimize">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>
                </button>
                <button class="action-btn" @click="dismissCurrentEvents" title="Dismiss">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div v-if="!isMinimized" class="ticker-content animate-slide-in">
            <div class="ticker-item" v-for="event in activeEvents" :key="event.id">
                <div class="item-visual">
                    <span class="ticker-type" :class="event.type">{{ event.type.toUpperCase() }}</span>
                    <span class="ticker-title">{{ event.title }}</span>
                </div>
                <span class="ticker-desc">{{ event.description }}</span>
                <div class="item-footer">
                    <span class="ticker-timer">ENDS_IN: {{ formatTime(event.ends_at) }}</span>
                </div>
            </div>
        </div>

        <div v-else class="ticker-minimized" @click="isMinimized = false" title="Show Event Details">
            <div class="pulse-icon" :class="activeEvents[0]?.type"></div>
            <span class="min-label">{{ activeEvents.length }} ACTIVE EVENT</span>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();
const activeEvents = computed(() => gameStore.worldEvents.active);

const now = ref(Date.now());
const isMinimized = ref(false);
const isDismissed = ref(false);
const dismissedEventIds = ref(new Set());

let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(timer);
});

// Watch for new events to reset dismissal
watch(activeEvents, (newEvents) => {
    const newIds = new Set(newEvents.map(e => e.id));
    // If there's an ID we haven't seen/dismissed yet, show the ticker again
    const hasNewEvent = [...newIds].some(id => !dismissedEventIds.value.has(id));
    if (hasNewEvent) {
        isDismissed.value = false;
        isMinimized.value = false;
    }
}, { deep: true });

const dismissCurrentEvents = () => {
    activeEvents.value.forEach(e => dismissedEventIds.value.add(e.id));
    isDismissed.value = true;
};

const formatTime = (date) => {
    if (!date) return 'Unknown';
    const expires = new Date(date).getTime();
    const diff = Math.floor((expires - now.value) / 1000);
    
    if (diff <= 0) return 'EXPIRED';
    
    const mins = Math.floor(diff / 60);
    const secs = diff % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};
</script>

<style scoped>
.global-event-ticker {
    background: rgba(10, 13, 20, 0.85);
    backdrop-filter: blur(12px);
    border-radius: 4px;
    padding: 12px 16px;
    border-left: 3px solid var(--color-primary);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    width: 320px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.ticker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.header-label {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--color-primary);
    letter-spacing: 0.1em;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.3);
    cursor: pointer;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.ticker-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ticker-minimized {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 4px 0;
}

.pulse-icon {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #58a6ff;
    box-shadow: 0 0 10px #58a6ff;
    animation: pulse-dot 2s infinite;
}

.pulse-icon.boom { background: #00ff9d; box-shadow: 0 0 10px #00ff9d; }
.pulse-icon.crisis { background: #ff4e4e; box-shadow: 0 0 10px #ff4e4e; }

.min-label {
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
}

@keyframes pulse-dot {
    0% { transform: scale(0.9); opacity: 0.6; }
    50% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.6; }
}

.ticker-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.item-visual {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ticker-type {
    font-weight: 800;
    font-size: 0.65rem;
    padding: 1px 4px;
    border-radius: 3px;
}

.ticker-type.boom {
    background: rgba(0, 255, 157, 0.2);
    color: #00ff9d;
}

.ticker-type.crisis {
    background: rgba(255, 78, 78, 0.2);
    color: #ff4e4e;
}

.ticker-title {
    font-weight: 600;
    color: #fff;
}

.ticker-desc {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
    line-height: 1.4;
}

.item-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 4px;
}

.ticker-timer {
    font-family: var(--font-family-mono);
    color: var(--color-primary);
    font-size: 0.75rem;
}

.animate-slide-in {
    animation: slideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
    from { 
        opacity: 0; 
        transform: translateX(40px) scale(0.95); 
    }
    to { 
        opacity: 1; 
        transform: translateX(0) scale(1); 
    }
}
</style>
