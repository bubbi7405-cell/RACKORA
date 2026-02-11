<template>
    <div class="world-news-ticker">
        <div class="ticker-label">WORLD NEWS</div>
        <div class="ticker-content" v-if="events.length > 0">
            <div class="ticker-track" :style="{ animationDuration: scrollDuration + 's' }">
                <div v-for="(event, index) in displayEvents" :key="index" class="news-item" :class="event.type">
                    <span class="event-title">{{ event.title }}:</span>
                    <span class="event-description">{{ event.description }}</span>
                </div>
            </div>
        </div>
        <div class="ticker-content ticker-content--empty" v-else>
            <div class="news-item">
                <span class="event-title">SYSTEM:</span>
                <span class="event-description">Global market conditions stable. Watch this space for economy shifts.</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import api from '../../utils/api';

const events = ref([]);
const scrollDuration = computed(() => {
    // Roughly 15 seconds per event for a good reading speed
    const duration = events.value.length * 20;
    return Math.max(duration, 30);
});

const displayEvents = computed(() => {
    if (events.value.length === 0) return [];
    // Repeat to ensure seamless loop
    return [...events.value, ...events.value, ...events.value];
});

const loadEvents = async () => {
    try {
        const response = await api.get('/world-events/active');
        if (response.success) {
            events.value = response.events;
        }
    } catch (e) {
        console.error('Failed to load world events', e);
    }
};

let interval = null;
onMounted(() => {
    loadEvents();
    // Poll for new world events every minute
    interval = setInterval(loadEvents, 60000);
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<style scoped>
.world-news-ticker {
    grid-area: news-ticker;
    height: 30px;
    background: rgba(0, 0, 0, 0.5);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    overflow: hidden;
    z-index: 10;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    position: relative;
}

.ticker-label {
    background: var(--color-primary);
    color: #fff;
    padding: 0 15px;
    height: 100%;
    display: flex;
    align-items: center;
    font-weight: 800;
    font-size: 0.7rem;
    letter-spacing: 1px;
    white-space: nowrap;
    z-index: 5;
    box-shadow: 10px 0 20px rgba(0,0,0,0.5);
}

.ticker-content {
    flex: 1;
    overflow: hidden;
    display: flex;
    align-items: center;
}

.ticker-track {
    display: flex;
    white-space: nowrap;
    animation: scroll linear infinite;
    padding-left: 50px;
}

.news-item {
    display: flex;
    align-items: center;
    margin-right: 150px;
    color: var(--color-text-muted);
}

.event-title {
    font-weight: 700;
    color: var(--color-text-primary);
    margin-right: 8px;
    text-transform: uppercase;
}

.news-item.crisis .event-title { color: var(--color-danger); }
.news-item.boom .event-title { color: var(--color-success); }
.news-item.news .event-title { color: var(--color-primary); }

@keyframes scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-33.33%); } /* Since we repeated 3 times */
}

/* Pause on hover would be nice but might flicker with loop. Let's keep it simple. */
</style>
