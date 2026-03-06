<template>
    <div class="global-event-ticker" 
        v-if="activeEvents.length > 0 && !isDismissed"
        :class="{ 'is-dimmed': isWorkspaceFocused }"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <div class="ticker-header" v-if="!isMinimized">
            <span class="header-label">MARKET_EVENTS</span>
            <div class="header-actions">
                <button class="action-btn" @click="isMinimized = true" title="Minimize">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg>
                </button>
                <button class="action-btn" @click="dismissCurrentEvents" title="Dismiss">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div v-if="!isMinimized" class="ticker-content" :class="{ 'is-collapsed': isWorkspaceFocused && !isHovered }">
            <div class="ticker-item" v-for="event in activeEvents" :key="event.id">
                <div class="item-visual">
                    <span class="ticker-type" :class="event.type">{{ event.type.toUpperCase() }}</span>
                    <span class="ticker-title">{{ event.title }}</span>
                </div>
                <div class="item-footer">
                    <span class="ticker-timer">{{ formatTime(event.ends_at) }}</span>
                </div>
            </div>
        </div>

        <div v-else class="ticker-minimized" @click="isMinimized = false" title="Show Event Details">
            <div class="event-indicator" :class="activeEvents[0]?.type"></div>
            <span class="min-label">{{ activeEvents.length }} ACTIVE</span>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();

const props = defineProps({
    activeView: { type: String, default: 'overview' }
});

const activeEvents = computed(() => gameStore.worldEvents.active);

const now = ref(Date.now());
const isMinimized = ref(false);
const isDismissed = ref(false);
const isHovered = ref(false);
const dismissedEventIds = ref(new Set());

const isWorkspaceFocused = computed(() => {
    return ['research', 'finance', 'analytics', 'compliance'].includes(props.activeView);
});

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
    background: rgba(8, 10, 15, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-left: 2px solid var(--v3-accent);
    padding: 12px;
    width: 100%;
    margin-bottom: 8px;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}

.global-event-ticker.is-dimmed {
    opacity: 0.25;
}

.global-event-ticker.is-dimmed:hover {
    opacity: 1;
    background: rgba(8, 10, 15, 0.95);
}

.ticker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    padding-bottom: 4px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.header-label {
    font-size: 0.45rem;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.15em;
}

.ticker-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow: hidden;
    transition: max-height 0.3s;
}

.ticker-content.is-collapsed {
    max-height: 24px;
}

.ticker-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.item-visual {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    overflow: hidden;
}

.ticker-title {
    font-size: 0.65rem;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ticker-timer {
    font-family: var(--font-family-mono);
    color: var(--v3-accent);
    font-size: 0.55rem;
    font-weight: 700;
    flex-shrink: 0;
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
