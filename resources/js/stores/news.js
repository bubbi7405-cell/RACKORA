import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useNewsStore = defineStore('news', () => {
    const historicalNews = ref([]);
    const maxItems = 10;

    function addNews(newsItem) {
        historicalNews.value.unshift({
            id: newsItem.id || Date.now(),
            headline: newsItem.title || newsItem.headline,
            category: newsItem.category || 'BROADCAST',
            type: newsItem.type || 'info',
            timestamp: newsItem.timestamp || new Date().toISOString(),
        });

        if (historicalNews.value.length > maxItems) {
            historicalNews.value.pop();
        }
    }

    function setInitialNews(items) {
        historicalNews.value = items.map(item => ({
            id: Math.random(),
            headline: item.headline,
            category: item.category,
            type: item.type,
            timestamp: new Date().toISOString()
        }));
    }

    return {
        historicalNews,
        addNews,
        setInitialNews
    };
});
