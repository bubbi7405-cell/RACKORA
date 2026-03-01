<template>
    <div class="overlay-backdrop">
        <div class="overlay-modal">
            <div class="modal-header">
                <h2>Activity & Incident Log</h2>
                <button class="close-btn" @click="$emit('close')">×</button>
            </div>

            <div class="modal-content">
                <div class="log-controls">
                    <div class="filter-group">
                        <button 
                            v-for="cat in categories" 
                            :key="cat"
                            class="filter-btn"
                            :class="{ 'active': activeCategory === cat }"
                            @click="activeCategory = cat"
                        >
                            {{ cat || 'All' }}
                        </button>
                    </div>
                    <button class="refresh-btn" @click="fetchLogs" :disabled="loading">
                        {{ loading ? '...' : 'Refresh' }}
                    </button>
                </div>

                <div class="log-list" v-if="!loading">
                    <div 
                        v-for="log in filteredLogs" 
                        :key="log.id" 
                        class="log-item"
                        :class="`log-item--${log.type}`"
                    >
                        <div class="log-item__time">{{ formatTime(log.created_at) }}</div>
                        <div class="log-item__content">
                            <div class="log-item__category">{{ log.category }}</div>
                            <div class="log-item__message">{{ log.message }}</div>
                        </div>
                        <div class="log-item__type-icon">{{ getTypeIcon(log.type) }}</div>
                    </div>

                    <div v-if="filteredLogs.length === 0" class="no-logs">
                        No activity recorded for this category.
                    </div>
                </div>

                <div v-else class="loading-state">
                    Loading logs...
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../utils/api';

const emit = defineEmits(['close']);

const logs = ref([]);
const loading = ref(true);
const activeCategory = ref(null);

const categories = [null, 'economy', 'infrastructure', 'research', 'maintenance', 'world'];

const fetchLogs = async () => {
    loading.value = true;
    try {
        const res = await api.get('/game/logs');
        if (res.success) {
            logs.value = res.data;
        }
    } catch (e) {
        console.error("Failed to fetch logs", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchLogs();
});

const filteredLogs = computed(() => {
    if (!activeCategory.value) return logs.value;
    return logs.value.filter(l => l.category === activeCategory.value);
});

const formatTime = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const getTypeIcon = (type) => {
    switch (type) {
        case 'success': return '✓';
        case 'warning': return '⚠';
        case 'danger': return '✖';
        default: return 'ℹ';
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: flex; justify-content: center; align-items: center;
    z-index: 2100;
}

.overlay-modal {
    background: #1a1f2e;
    border: 1px solid #333;
    border-radius: 12px;
    width: 600px;
    max-width: 90vw;
    height: 70vh;
    display: flex; flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #333;
    display: flex; align-items: center; justify-content: space-between;
}

.modal-header h2 { margin: 0; font-size: 1.25rem; }

.close-btn {
    background: none; border: none; font-size: 2rem; color: #666; cursor: pointer;
}

.modal-content {
    flex: 1; padding: 0; overflow-y: hidden;
    display: flex; flex-direction: column;
}

.log-controls {
    padding: 15px 20px;
    border-bottom: 1px solid #2a2f3e;
    display: flex; justify-content: space-between; align-items: center;
    background: #222736;
}

.filter-group { display: flex; gap: 8px; }

.filter-btn {
    background: #2d3345; border: 1px solid #444; color: #888;
    padding: 4px 10px; border-radius: 4px; font-size: 0.75rem;
    cursor: pointer; text-transform: capitalize;
}

.filter-btn.active {
    background: #00bcd4; color: #000; border-color: #00bcd4; font-weight: bold;
}

.refresh-btn {
    background: transparent; border: 1px solid #444; color: #aaa;
    padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;
}

.log-list {
    flex: 1; overflow-y: auto; padding: 10px 0;
}

.log-item {
    display: flex; align-items: center; gap: 15px;
    padding: 12px 20px;
    border-bottom: 1px solid #222736;
    transition: background 0.2s;
}

.log-item:hover { background: #222736; }

.log-item__time {
    font-family: monospace; font-size: 0.8rem; color: #555;
    white-space: nowrap;
}

.log-item__content { flex: 1; }

.log-item__category {
    font-size: 0.65rem; text-transform: uppercase; color: #00bcd4;
    letter-spacing: 1px; margin-bottom: 2px;
}

.log-item__message { font-size: 0.9rem; color: #ccc; }

.log-item__type-icon {
    font-size: 1.1rem; font-weight: bold; opacity: 0.7;
}

.log-item--success .log-item__type-icon { color: #4caf50; }
.log-item--warning .log-item__type-icon { color: #ff9800; }
.log-item--danger .log-item__type-icon { color: #f44336; }
.log-item--info .log-item__type-icon { color: #2196f3; }

.no-logs { padding: 40px; text-align: center; color: #555; }
.loading-state { padding: 40px; text-align: center; color: #888; }

</style>
