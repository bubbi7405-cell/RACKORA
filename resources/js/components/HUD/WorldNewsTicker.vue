<template>
    <div class="world-news-ticker" :class="{ 'ticker--compact': compact }">
        <div class="ticker-label" v-if="!compact">CRYPTO_NEWS :: LIVE</div>
        <div class="ticker-content" v-if="newsItems.length > 0">
            <div class="ticker-track" :style="{ animationDuration: scrollDuration + 's' }">
                <div v-for="(item, index) in displayItems" :key="index" class="news-item" :class="item.type">
                    <span class="item-cat">[{{ item.category }}]</span>
                    <span class="item-headline">{{ item.headline }}</span>
                    <span v-if="item.content" class="item-content">{{ item.content }}</span>
                </div>
            </div>
        </div>
        <div class="ticker-content ticker-content--empty" v-else>
            <div class="news-item atmosphere">
                <span class="item-cat">[CONNECTING]</span>
                <span class="item-headline">Establishing secure uplink to Global News Network...</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import api from '../../utils/api';
import { useNewsStore } from '../../stores/news';

const props = defineProps({
    compact: { type: Boolean, default: false }
});

const newsStore = useNewsStore();
const newsItems = computed(() => newsStore.historicalNews);

const scrollDuration = computed(() => {
    if (!newsItems.value || newsItems.value.length === 0) return 30;
    // Roughly adjusted speed based on content length
    const totalChars = newsItems.value.reduce((sum, item) => {
        if (!item) return sum;
        return sum + (item.headline?.length || 0) + (item.category?.length || 0);
    }, 0);
    return Math.max(totalChars / 12, 15); 
});

const displayItems = computed(() => {
    if (!newsItems.value || newsItems.value.length === 0) return [];
    const validItems = newsItems.value.filter(item => item && item.headline);
    if (validItems.length === 0) return [];
    // Triple up for seamless loop
    return [...validItems, ...validItems, ...validItems];
});

const loadNews = async () => {
    try {
        const response = await api.get('/game/news');
        if (response.success && response.data) {
            newsStore.setInitialNews(response.data);
        }
    } catch (e) {
        console.error('Failed to load news feed', e);
    }
};

onMounted(() => {
    if (newsItems.value.length === 0) {
        loadNews();
    }
});
</script>

<style scoped>
.world-news-ticker {
    height: 100%;
    display: flex;
    align-items: center;
    overflow: hidden;
    font-size: 0.65rem;
    font-family: var(--font-mono);
    color: var(--v3-text-ghost);
    background: rgba(0,0,0,0.4);
    border-radius: 4px;
    padding: 0 10px;
    border: 1px solid rgba(255,255,255,0.05);
}

.ticker-label {
    padding-right: 15px;
    font-weight: 900;
    color: var(--v3-text-ghost);
    letter-spacing: 0.15em;
    white-space: nowrap;
    border-right: 1px solid rgba(255,255,255,0.1);
    margin-right: 20px;
    font-size: 0.55rem;
}

.ticker-content {
    flex: 1;
    overflow: hidden;
}

.ticker-track {
    display: flex;
    white-space: nowrap;
    animation: ticker-scroll linear infinite;
}

.news-item {
    display: flex;
    align-items: center;
    margin-right: 80px;
    gap: 10px;
}

.item-cat {
    font-weight: 900;
    color: var(--v3-accent);
    font-size: 0.5rem;
    opacity: 0.7;
}

.item-headline {
    font-weight: 800;
    color: #fff;
    letter-spacing: 0.05em;
}

.item-content {
    opacity: 0.5;
    font-style: italic;
    font-size: 0.6rem;
}

/* Specific Types */
.news-item.breaking { 
    color: var(--v3-warning); 
    position: relative;
    padding: 0 15px;
}

.news-item.breaking::before {
    content: 'BREAKING';
    position: absolute;
    top: -10px;
    left: 15px;
    font-size: 0.45rem;
    font-weight: 900;
    color: #fff;
    background: var(--v3-danger);
    padding: 1px 4px;
    border-radius: 2px;
    letter-spacing: 0.1em;
    animation: v3-pulse-slow 1.5s infinite;
}

.news-item.breaking .item-cat { color: var(--v3-danger); font-weight: 900; text-shadow: 0 0 5px rgba(239, 68, 68, 0.4); }
.news-item.breaking .item-headline { 
    color: #fff; 
    text-shadow: 0 0 10px rgba(255, 77, 79, 0.5);
    font-size: 0.75rem;
    font-weight: 900;
}

@keyframes v3-pulse-slow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(0.95); }
}

.news-item.milestone .item-cat { color: var(--v3-success); }
.news-item.ticker { opacity: 0.6; font-size: 0.6rem; }
.news-item.ticker .item-cat { display: none; }

@keyframes ticker-scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-33.333%); }
}

.ticker--compact {
    background: transparent;
    border: none;
}
</style>

