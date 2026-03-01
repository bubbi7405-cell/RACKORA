<template>
    <div 
        v-if="store.visible" 
        class="tooltip-container" 
        :style="style"
        ref="tooltip"
    >
        <div class="tooltip-arrow" :class="{ bottom: position === 'top', top: position === 'bottom' }"></div>
        <div class="tooltip-content shadow-premium">
            <div v-if="store.title" class="tooltip-title">{{ store.title }}</div>
            <div class="tooltip-text">{{ store.content }}</div>
            <div v-if="store.hint" class="tooltip-hint">
                <i class="fas fa-lightbulb"></i> {{ store.hint }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useTooltipStore } from '../../stores/tooltip';

const store = useTooltipStore();
const position = ref('top');

const style = computed(() => ({
    left: `${store.x}px`,
    top: `${store.y - 12}px`,
    opacity: store.visible ? 1 : 0,
    transform: store.visible ? 'translate(-50%, -100%) scale(1)' : 'translate(-50%, -90%) scale(0.95)'
}));
</script>

<style scoped>
.tooltip-container {
    position: fixed;
    z-index: 10000;
    pointer-events: none;
    background: rgba(13, 17, 23, 0.95);
    border: 1px solid rgba(56, 139, 253, 0.4);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 0 10px rgba(56, 139, 253, 0.1);
    border-radius: 8px;
    padding: 10px 14px;
    max-width: 260px;
    transition: all 0.2s cubic-bezier(0.23, 1, 0.32, 1);
    backdrop-filter: blur(12px);
}

.tooltip-arrow {
    position: absolute;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
}

.tooltip-arrow.bottom {
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    border-top: 6px solid rgba(56, 139, 253, 0.4);
}

.tooltip-title {
    color: #58a6ff;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08rem;
    margin-bottom: 6px;
    border-bottom: 1px solid rgba(56, 139, 253, 0.2);
    padding-bottom: 4px;
}

.tooltip-text {
    color: #e6edf3;
    font-size: 0.82rem;
    line-height: 1.5;
    font-weight: 400;
}

.tooltip-hint {
    margin-top: 8px;
    font-size: 0.7rem;
    color: #8b949e;
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 4px;
}

.tooltip-hint i {
    color: #f1e05a;
}
</style>

<style scoped>
.tooltip-container {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    transform: translate(-50%, -100%);
    background: rgba(13, 17, 23, 0.95);
    border: 1px solid rgba(56, 139, 253, 0.4);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 0 10px rgba(56, 139, 253, 0.2);
    border-radius: 8px;
    padding: 10px 14px;
    max-width: 280px;
    backdrop-filter: blur(8px);
    animation: fadeIn 0.15s ease-out;
}

.tooltip-arrow {
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 6px solid rgba(56, 139, 253, 0.4);
}

.tooltip-title {
    color: #58a6ff;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05rem;
    margin-bottom: 4px;
    border-bottom: 1px solid rgba(56, 139, 253, 0.2);
    padding-bottom: 4px;
}

.tooltip-text {
    color: #c9d1d9;
    font-size: 0.85rem;
    line-height: 1.4;
    font-family: 'Inter', sans-serif;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translate(-50%, -90%); }
    to { opacity: 1; transform: translate(-50%, -100%); }
}
</style>
