<template>
    <div class="log-ticker-container" v-show="uiStore.showLogTicker">
        <div class="ticker-header">
            <span class="header-label">SYSTEM_PIPELINE.LOG</span>
            <div class="header-led"></div>
        </div>
        <div class="ticker-content" ref="scrollContainer">
            <div 
                v-for="log in logs" 
                :key="log.id" 
                class="log-entryLine"
                :class="[`type--${log.type}`]"
            >
                <span class="entry-time">[{{ log.timestamp }}]</span>
                <span class="entry-msg">{{ log.message }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useLogStore } from '../../stores/logs';
import { useUiStore } from '../../stores/ui';

const logStore = useLogStore();
const uiStore = useUiStore();
const logs = computed(() => logStore.logs);
const scrollContainer = ref(null);

onMounted(() => {
    // Initial scroll or settings
});
</script>

<style scoped>
.log-ticker-container {
    position: fixed;
    right: 320px; /* Adjust based on RightPanel width */
    bottom: 24px;
    width: 280px;
    height: 180px;
    background: rgba(0, 5, 0, 0.85);
    border: 1px solid rgba(0, 255, 65, 0.2);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5), inset 0 0 15px rgba(0, 255, 65, 0.05);
    display: flex;
    flex-direction: column;
    z-index: 100;
    pointer-events: none;
    border-radius: 4px;
    overflow: hidden;
    font-family: var(--font-family-mono, 'Courier New', Courier, monospace);
    transition: all 0.3s ease;
}

.ticker-header {
    height: 24px;
    background: rgba(0, 255, 65, 0.1);
    border-bottom: 1px solid rgba(0, 255, 65, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
}

.header-label {
    font-size: 0.6rem;
    font-weight: 900;
    color: rgba(0, 255, 65, 0.8);
    letter-spacing: 0.1em;
}

.header-led {
    width: 6px;
    height: 6px;
    background: #00ff41;
    border-radius: 50%;
    box-shadow: 0 0 8px #00ff41;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.ticker-content {
    flex: 1;
    padding: 8px 12px;
    overflow-y: hidden;
    display: flex;
    flex-direction: column-reverse; /* Newest logs at bottom if we want, but store is unshifted */
    mask-image: linear-gradient(to top, black 80%, transparent 100%);
}

.log-entryLine {
    font-size: 0.65rem;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: rgba(0, 255, 65, 0.7);
    margin-bottom: 2px;
    animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(10px); }
    to { opacity: 1; transform: translateX(0); }
}

.entry-time {
    color: rgba(0, 255, 65, 0.4);
    margin-right: 8px;
}

.type--warning { color: #f4b400; }
.type--danger { color: #ff4d4f; }
.type--success { color: #00e676; }
.type--info { color: rgba(0, 255, 65, 0.7); }

/* Matrix scanline effect */
.log-ticker-container::before {
    content: " ";
    display: block;
    position: absolute;
    top: 0; left: 0; bottom: 0; right: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
    z-index: 2;
    background-size: 100% 2px, 3px 100%;
    pointer-events: none;
}
</style>
