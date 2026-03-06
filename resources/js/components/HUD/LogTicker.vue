<template>
    <div 
        class="log-ticker-container" 
        v-show="uiStore.showLogTicker"
        :class="{ 
            'v3-stealth': isStealth,
            'is-dimmed': isWorkspaceFocused && !isHovered
        }"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <div class="ticker-header">
            <span class="header-label">FINANCIAL_LEDGER</span>
            <div class="header-led"></div>
        </div>
        <div class="ticker-content" ref="scrollContainer">
            <div 
                v-for="log in logs" 
                :key="log.id" 
                class="log-entryLine"
                :class="[`type--${log.type}`]"
            >
                <span class="entry-time l3-priority">[{{ log.timestamp }}]</span>
                <span class="entry-msg l2-priority">{{ log.message }}</span>
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

const props = defineProps({
    activeView: { type: String, default: 'overview' }
});

const logs = computed(() => logStore.logs);
const scrollContainer = ref(null);

const isStealth = ref(false);
const isHovered = ref(false);

const isWorkspaceFocused = computed(() => {
    return ['research', 'finance', 'analytics', 'compliance'].includes(props.activeView);
});
let stealthTimeout = null;

const startStealthTimer = () => {
    if (stealthTimeout) clearTimeout(stealthTimeout);
    isStealth.value = false;
    stealthTimeout = setTimeout(() => {
        isStealth.value = true;
    }, 10000); // 10 seconds of inactivity
};

const handleInteraction = () => {
    isHovered.value = true;
    startStealthTimer();
};

watch(logs, () => {
    startStealthTimer();
}, { deep: true });

onMounted(() => {
    startStealthTimer();
});
</script>

<style scoped>
/* Position is now handled by GameContainer to ensure layout orchestration */
.log-ticker-container {
    width: 100%;
    height: 140px;
    background: rgba(0, 5, 0, 0.4);
    display: flex;
    flex-direction: column;
    pointer-events: auto;
    border-radius: 2px;
    overflow: hidden;
    font-family: var(--font-family-mono, 'Courier New', Courier, monospace);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.log-ticker-container.is-dimmed {
    opacity: 0.2;
}

.log-ticker-container.is-dimmed:hover {
    opacity: 1;
    background: rgba(0, 5, 0, 0.9);
}

.log-ticker-container.v3-stealth:not(:hover) {
    opacity: 0.1;
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


</style>
