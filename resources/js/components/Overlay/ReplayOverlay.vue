<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="replay-overlay glass-panel animation-fade-in">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">⏮️</span>
                    <h2>Timeline Replay</h2>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>

            <div v-if="loading" class="loading-state">
                <div class="spinner"></div>
                <p>Loading historical data...</p>
            </div>

            <div v-else-if="!stats.length" class="empty-state">
                <div class="empty-icon">📂</div>
                <h3>No History Available</h3>
                <p>Play longer to generate a timeline.</p>
            </div>

            <div v-else class="overlay-body">
                <!-- Timeline Visualization -->
                <div class="timeline-visual">
                    <svg viewBox="0 0 800 200" preserveAspectRatio="none" class="timeline-chart">
                        <!-- Grid -->
                        <line v-for="i in 4" :key="i" x1="0" :y1="i * 40" x2="800" :y2="i * 40" class="chart-grid" />
                        
                        <!-- Balance Line -->
                        <path :d="getPath('balance', 800, 200)" fill="none" stroke="#2ea043" stroke-width="2" />
                        <!-- Reputation Line -->
                        <path :d="getPath('reputation', 800, 200)" fill="none" stroke="#58a6ff" stroke-width="2" stroke-dasharray="4" />

                        <!-- Current Time Marker -->
                        <line :x1="markerX" y1="0" :x2="markerX" y2="200" stroke="#fff" stroke-width="2" />
                        <circle :cx="markerX" :cy="getPointY('balance', currentIndex, 200)" r="4" fill="#2ea043" stroke="#fff" />
                    </svg>
                </div>

                <!-- Controls -->
                <div class="timeline-controls">
                    <button class="control-btn" @click="togglePlay">
                        {{ isPlaying ? '⏸️ Pause' : '▶️ Play' }}
                    </button>
                    <input 
                        type="range" 
                        class="timeline-slider" 
                        min="0" 
                        :max="stats.length - 1" 
                        v-model.number="currentIndex"
                        @input="pause"
                    />
                    <div class="time-display">
                        {{ formatTime(currentStat.created_at) }}
                        <span class="tick-badge">Tick #{{ currentStat.tick }}</span>
                    </div>
                    <div class="speed-control">
                        <button @click="speed = 1" :class="{ active: speed === 1 }">1x</button>
                        <button @click="speed = 2" :class="{ active: speed === 2 }">2x</button>
                        <button @click="speed = 5" :class="{ active: speed === 5 }">5x</button>
                    </div>
                </div>

                <!-- State Snapshot -->
                <div class="snapshot-grid">
                    <div class="stat-box">
                        <label>Balance</label>
                        <div class="val text-success">${{ formatMoney(currentStat.balance) }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Reputation</label>
                        <div class="val text-primary">{{ Math.round(currentStat.reputation) }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Online Servers</label>
                        <div class="val">{{ currentStat.active_servers }}</div>
                    </div>
                    <div class="stat-box">
                        <label>Cust. Satisfaction</label>
                        <div class="val" :class="getSatColor(currentStat.avg_satisfaction)">
                            {{ Math.round(currentStat.avg_satisfaction) }}%
                        </div>
                    </div>
                </div>

                <!-- Log Feed (Contextual) -->
                <div class="log-feed">
                    <h3>Events around {{ formatTime(currentStat.created_at) }}</h3>
                    <div class="log-list" ref="logContainer">
                        <div v-for="log in visibleLogs" :key="log.id" class="log-item" :class="log.type">
                            <span class="log-time">{{ formatTimeOnly(log.created_at) }}</span>
                            <span class="log-msg">{{ log.message }}</span>
                            <span class="log-meta" v-if="log.category">[{{ log.category }}]</span>
                        </div>
                        <div v-if="visibleLogs.length === 0" class="no-logs">
                            No major events recorded at this moment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import api from '../../utils/api';

const emit = defineEmits(['close']);

const loading = ref(true);
const stats = ref([]);
const logs = ref([]);
const currentIndex = ref(0);
const isPlaying = ref(false);
const speed = ref(1);

const currentStat = computed(() => stats.value[currentIndex.value] || {});
const markerX = computed(() => {
    if (!stats.value.length) return 0;
    return (currentIndex.value / (stats.value.length - 1)) * 800;
});

// Logs visible within +/- 2 ticks of current index
const visibleLogs = computed(() => {
    if (!stats.value.length || !logs.value.length) return [];
    
    const currentPriceTime = new Date(currentStat.value.created_at).getTime();
    
    // Find logs within 5 minutes of current stat frame
    // 5 minutes = 300000 ms
    const window = 300000; 
    
    return logs.value.filter(log => {
        const logTime = new Date(log.created_at).getTime();
        return Math.abs(logTime - currentPriceTime) < window;
    });
});

onMounted(async () => {
    await fetchData();
});

async function fetchData() {
    try {
        const res = await api.get('/game/replay?limit=100');
        if (res.success) {
            // Check if stats array exists
            const s = res.data.stats || [];
            if (s.length > 0) {
                // Ensure chronological order (oldest first)
                stats.value = s.sort((a,b) => new Date(a.created_at) - new Date(b.created_at));
                logs.value = res.data.logs || [];
                currentIndex.value = stats.value.length - 1; // Start at end
            }
        }
    } catch (e) {
        console.error('Replay load failed', e);
    } finally {
        loading.value = false;
    }
}

let playInterval;
function togglePlay() {
    isPlaying.value = !isPlaying.value;
    if (isPlaying.value) {
        if (currentIndex.value >= stats.value.length - 1) currentIndex.value = 0;
        playInterval = setInterval(() => {
            if (currentIndex.value < stats.value.length - 1) {
                currentIndex.value++;
            } else {
                pause();
            }
        }, 1000 / speed.value); // 1 tick per sec at 1x
    } else {
        clearInterval(playInterval);
    }
}

function pause() {
    isPlaying.value = false;
    clearInterval(playInterval);
}

watch(speed, () => {
    if (isPlaying.value) {
        pause();
        togglePlay();
    }
});

function getPath(key, width, height) {
    if (!stats.value.length) return '';
    const values = stats.value.map(s => parseFloat(s[key] || 0));
    const min = Math.min(...values);
    const max = Math.max(...values, 1);
    const range = max - min || 1;

    return values.map((val, i) => {
        const x = (i / (values.length - 1)) * width;
        const normalized = (val - min) / range;
        const y = height - (normalized * (height * 0.8)) - (height * 0.1); // Add padding
        return `${i===0?'M':'L'} ${x} ${y}`;
    }).join(' ');
}

function getPointY(key, index, height) {
    if (!stats.value.length) return 0;
    const values = stats.value.map(s => parseFloat(s[key] || 0));
    const min = Math.min(...values);
    const max = Math.max(...values, 1);
    const range = max - min || 1;
    const val = values[index];
    const normalized = (val - min) / range;
    return height - (normalized * (height * 0.8)) - (height * 0.1);
}

function formatTime(ts) {
    if (!ts) return '';
    return new Date(ts).toLocaleString();
}

function formatTimeOnly(ts) {
    return new Date(ts).toLocaleTimeString();
}

function formatMoney(val) {
    return Number(val || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
}

function getSatColor(val) {
    if (val >= 80) return 'text-success';
    if (val >= 50) return 'text-warning';
    return 'text-danger';
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 5000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.replay-overlay {
    width: 900px;
    max-width: 95vw;
    height: 80vh;
    background: #0d1117;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    color: #fff;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.overlay-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title .icon { font-size: 1.5rem; }
.header-title h2 { margin: 0; font-size: 1.25rem; font-weight: 700; }

.overlay-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 20px;
    gap: 20px;
    overflow-y: auto;
}

.timeline-visual {
    height: 200px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.timeline-chart {
    width: 100%;
    height: 100%;
}

.chart-grid {
    stroke: rgba(255, 255, 255, 0.05);
    stroke-width: 1;
}

/* Controls */
.timeline-controls {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.03);
    padding: 15px;
    border-radius: 8px;
}

.control-btn {
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 700;
    cursor: pointer;
    min-width: 80px;
}

.timeline-slider {
    flex: 1;
    height: 6px;
    -webkit-appearance: none;
    appearance: none;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    outline: none;
}

.timeline-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    background: var(--color-primary);
    border-radius: 50%;
    cursor: pointer;
}

.time-display {
    font-family: monospace;
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.tick-badge {
    font-size: 0.7rem;
    color: #888;
}

.speed-control {
    display: flex;
    gap: 5px;
}

.speed-control button {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
}

.speed-control button.active {
    background: #fff;
    color: #000;
}

/* Snapshot Grid */
.snapshot-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.stat-box {
    background: rgba(255, 255, 255, 0.03);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

.stat-box label {
    display: block;
    font-size: 0.75rem;
    color: #888;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.stat-box .val {
    font-size: 1.2rem;
    font-weight: 700;
    font-family: monospace;
}

.text-success { color: #2ea043; }
.text-primary { color: #58a6ff; }
.text-warning { color: #d29922; }
.text-danger { color: #f85149; }

/* Log Feed */
.log-feed {
    flex: 1;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.log-feed h3 {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    color: #aaa;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 5px;
}

.log-list {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.log-item {
    font-family: monospace;
    font-size: 0.85rem;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 10px;
    align-items: flex-start;
}

.log-time { color: #666; }
.log-msg { color: #ccc; }
.log-meta { color: #58a6ff; font-size: 0.75rem; }

.log-item.critical .log-msg { color: #f85149; font-weight: bold; }
.log-item.success .log-msg { color: #2ea043; }
.log-item.warning .log-msg { color: #d29922; }

.no-logs {
    text-align: center;
    padding: 20px;
    color: #444;
    font-style: italic;
}

.loading-state, .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: #888;
}

.spinner {
    width: 30px; height: 30px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
