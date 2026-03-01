<template>
    <div class="global-event-ticker" v-if="activeEvents.length > 0">
        <div class="ticker-content animate-slide-up">
            <div class="ticker-item" v-for="event in activeEvents" :key="event.id">
                <span class="ticker-type" :class="event.type">{{ event.type.toUpperCase() }}</span>
                <span class="ticker-title">{{ event.title }}</span>
                <span class="ticker-desc">{{ event.description }}</span>
                <span class="ticker-timer">Ends in: {{ formatTime(event.ends_at) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const gameStore = useGameStore();
const activeEvents = computed(() => gameStore.worldEvents.active);

const now = ref(dayjs());
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        now.value = dayjs();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(timer);
});

const formatTime = (date) => {
    if (!date) return 'Unknown';
    const expires = dayjs(date);
    const diff = expires.diff(now.value, 'second');
    
    if (diff <= 0) return 'Expired';
    
    const mins = Math.floor(diff / 60);
    const secs = diff % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};
</script>

<style scoped>
.global-event-ticker {
    background: rgba(0, 0, 0, 0.4);
    border-radius: 8px;
    padding: 4px 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    overflow: hidden;
    height: 32px;
    display: flex;
    align-items: center;
    max-width: 400px;
}

.ticker-content {
    display: flex;
    flex-direction: column;
}

.ticker-item {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    font-size: 0.8rem;
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
    color: rgba(255, 255, 255, 0.5);
    text-overflow: ellipsis;
    overflow: hidden;
    max-width: 200px;
}

.ticker-timer {
    font-family: var(--font-family-mono);
    color: var(--color-primary);
    font-size: 0.75rem;
}

.animate-slide-up {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
