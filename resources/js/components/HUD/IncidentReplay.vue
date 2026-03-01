<template>
    <div class="v2-replay-overlay" v-if="event">
        <div class="v2-replay-window">
            <header class="v2-replay-header">
                <div class="v2-replay-title">
                    <span class="v2-label">BLACKBOX_PLAYBACK</span>
                    <span class="v2-sep">//</span>
                    <span class="v2-node">{{ event.title }}</span>
                </div>
                <button @click="$emit('close')" class="v2-close-btn">×</button>
            </header>

            <div class="v2-replay-main">
                <div class="v2-telemetry-view">
                    <!-- Thermal Visualization -->
                    <div class="v2-viz-card">
                        <div class="v2-title">THERMAL_CONTEXT</div>
                        <div class="v2-rack-mini">
                            <div v-for="(temp, slot) in currentSnapshot.telemetry.rack?.thermalMap || {}" 
                                 :key="slot"
                                 class="v2-slot-pip"
                                 :style="getThermalStyle(temp)">
                            </div>
                        </div>
                        <div class="v2-viz-stat">
                            <span>CURRENT_TEMP</span>
                            <span class="v2-val">{{ currentSnapshot.telemetry.rack?.temp || 'N/A' }}°C</span>
                        </div>
                    </div>

                    <!-- Server Health Visualization -->
                    <div class="v2-viz-card">
                        <div class="v2-title">ASSET_INTEGRITY</div>
                        <div class="v2-health-gauge">
                            <div class="v2-gauge-bg">
                                <div class="v2-gauge-fill" :style="{ width: currentSnapshot.telemetry.server?.health + '%', background: getHealthColor(currentSnapshot.telemetry.server?.health) }"></div>
                            </div>
                        </div>
                        <div class="v2-viz-stat">
                            <span>INTEGRITY_INDEX</span>
                            <span class="v2-val">{{ currentSnapshot.telemetry.server?.health || 0 }}%</span>
                        </div>
                    </div>
                </div>

                <div class="v2-timeline-controls">
                    <div class="v2-scrub-container">
                        <input type="range" 
                               min="0" 
                               :max="event.replay_data?.length - 1" 
                               v-model="scrubIndex" 
                               class="v2-scrubber">
                        <div class="v2-time-display">
                            T+{{ scrubIndex }} ticks // {{ formatTimestamp(currentSnapshot.timestamp) }}
                        </div>
                    </div>
                    
                    <div class="v2-playback-actions">
                        <button @click="togglePlayback" class="v2-play-btn">
                            {{ isPlaying ? '⏸ PAUSE' : '▶ PLAY' }}
                        </button>
                    </div>
                </div>
            </div>

            <footer class="v2-replay-footer">
                <div class="v2-post-mortem">
                    <div class="v2-grade-badge" :class="'grade-' + event.managementGrade">
                        {{ event.managementGrade }}
                    </div>
                    <div class="v2-summary">
                        <h3>POST_MORTEM_SUMMARY</h3>
                        <p>{{ event.postMortem?.summary }}</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps(['event']);
const emit = defineEmits(['close']);

const scrubIndex = ref(0);
const isPlaying = ref(false);
let playbackInterval = null;

const currentSnapshot = computed(() => {
    return props.event.replay_data?.[scrubIndex.value] || { telemetry: {} };
});

const togglePlayback = () => {
    if (isPlaying.value) {
        clearInterval(playbackInterval);
        isPlaying.value = false;
    } else {
        isPlaying.value = true;
        playbackInterval = setInterval(() => {
            if (scrubIndex.value < props.event.replay_data.length - 1) {
                scrubIndex.value++;
            } else {
                clearInterval(playbackInterval);
                isPlaying.value = false;
            }
        }, 500);
    }
};

const formatTimestamp = (ts) => {
    if (!ts) return '--:--:--';
    const d = new Date(ts);
    return isNaN(d.getTime()) ? '--:--:--' : d.toLocaleTimeString('de-DE');
};

const getThermalStyle = (temp) => {
    const hue = Math.max(0, 200 - (temp * 2));
    const opacity = Math.min(1, (temp - 20) / 40);
    return {
        background: `hsla(${hue}, 80%, 50%, ${opacity})`,
        boxShadow: temp > 45 ? `0 0 10px hsla(${hue}, 80%, 50%, 0.5)` : 'none'
    };
};

const getHealthColor = (health) => {
    if (health > 80) return 'var(--v2-success)';
    if (health > 40) return 'var(--v2-warning)';
    return 'var(--v2-danger)';
};

onUnmounted(() => {
    if (playbackInterval) clearInterval(playbackInterval);
});
</script>

<style scoped>
.v2-replay-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(10px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.v2-replay-window {
    width: 100%;
    max-width: 900px;
    background: var(--v2-bg-surface);
    border: var(--v2-border);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 0 50px rgba(0,0,0,0.5);
}

.v2-replay-header {
    padding: 16px 24px;
    border-bottom: var(--v2-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.v2-replay-main {
    padding: 32px;
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.v2-telemetry-view {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}

.v2-viz-card {
    background: var(--v2-bg-overlay);
    padding: 20px;
    border-radius: 8px;
    border: var(--v2-border);
}

.v2-rack-mini {
    display: grid;
    grid-template-rows: repeat(21, 1fr);
    gap: 2px;
    height: 180px;
    width: 60px;
    background: #000;
    padding: 4px;
    border: 1px solid #333;
    margin: 0 auto 16px;
}

.v2-slot-pip {
    width: 100%;
    height: 100%;
    border-radius: 1px;
}

.v2-viz-stat {
    display: flex;
    justify-content: space-between;
    font-family: var(--font-family-mono);
    font-size: 0.7rem;
    color: var(--v2-text-ghost);
}

.v2-val {
    color: var(--v2-text-main);
    font-weight: 800;
}

.v2-timeline-controls {
    background: var(--v2-bg-overlay);
    padding: 24px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.v2-scrub-container {
    flex: 1;
}

.v2-scrubber {
    width: 100%;
    cursor: pointer;
}

.v2-time-display {
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v2-accent);
    margin-top: 8px;
}

.v2-playback-actions button {
    background: var(--v2-accent);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
}

.v2-replay-footer {
    padding: 24px;
    background: var(--v2-bg-accent);
    border-top: var(--v2-border);
}

.v2-post-mortem {
    display: flex;
    gap: 24px;
    align-items: center;
}

.v2-grade-badge {
    width: 64px;
    height: 64px;
    background: #222;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 900;
    border: 4px solid var(--v2-accent);
}

.grade-S { color: gold; border-color: gold; box-shadow: 0 0 20px rgba(255,215,0,0.3); }

.v2-summary h3 {
    font-size: 0.7rem;
    color: var(--v2-text-ghost);
    margin-bottom: 4px;
}

.v2-summary p {
    font-size: 0.85rem;
    color: var(--v2-text-main);
}
</style>
