<template>
    <div 
        v-if="store.visible" 
        class="v3-intel-intercept" 
        :style="style"
        ref="tooltip"
    >
        <div class="intercept-header l1-priority">
            <span class="header-label">AUTH_SIGNAL // [SIG_SYNC]</span>
            <span class="header-id">RX-{{ (store.title?.length || 0) * 12 }}-X</span>
        </div>
        <div class="intercept-body">
            <div class="intercept-scanline"></div>
            <div v-if="store.title" class="intercept-title l1-priority">{{ store.title.toUpperCase() }}</div>
            <div class="intercept-content l3-priority">{{ store.content }}</div>
            <div v-if="store.hint" class="intercept-hint l2-priority">
                <span class="hint-marker">»</span> {{ store.hint.toUpperCase() }}
            </div>
            
            <!-- Industrial Corner Brackets -->
            <div class="bracket-tl"></div>
            <div class="bracket-tr"></div>
            <div class="bracket-bl"></div>
            <div class="bracket-br"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useTooltipStore } from '../../stores/tooltip';

const store = useTooltipStore();

const style = computed(() => ({
    left: `${store.x}px`,
    top: `${store.y - 12}px`,
    opacity: store.visible ? 1 : 0,
    transform: store.visible ? 'translate(-50%, -100%) scale(1)' : 'translate(-50%, -95%) scale(0.95)'
}));
</script>

<style scoped>
.v3-intel-intercept {
    position: fixed;
    z-index: var(--zi-interaction);
    pointer-events: none;
    transform: translate(-50%, -100%);
    width: 280px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.intercept-header {
    background: var(--ds-bg-void);
    border: 1px solid var(--ds-accent-soft);
    border-bottom: none;
    padding: 4px 10px;
    display: flex;
    justify-content: space-between;
    font-size: 0.45rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    color: var(--ds-accent);
}

.intercept-body {
    background: rgba(5, 7, 10, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid var(--ds-accent-soft);
    padding: 16px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6), 0 0 20px rgba(88, 166, 255, 0.1);
}

.intercept-scanline {
    position: absolute;
    inset: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(88, 166, 255, 0.03) 50%);
    background-size: 100% 2px;
    pointer-events: none;
    z-index: 10;
}

.intercept-title {
    font-size: 0.65rem;
    font-weight: 950;
    letter-spacing: 0.1em;
    margin-bottom: 8px;
    color: #fff;
}

.intercept-content {
    font-size: 0.7rem;
    line-height: 1.6;
    color: var(--ds-text-ghost);
    font-weight: 600;
}

.intercept-hint {
    margin-top: 12px;
    padding-top: 8px;
    font-size: 0.55rem;
    font-weight: 950;
    color: var(--ds-warning);
    letter-spacing: 0.05em;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.hint-marker {
    margin-right: 4px;
}

/* ── CORNER BRACKETS ────────────────────────── */
.bracket-tl, .bracket-tr, .bracket-bl, .bracket-br {
    position: absolute;
    width: 6px;
    height: 6px;
    border-color: var(--ds-accent);
    border-style: solid;
    opacity: 0.6;
}

.bracket-tl { top: 0; left: 0; border-width: 2px 0 0 2px; }
.bracket-tr { top: 0; right: 0; border-width: 2px 2px 0 0; }
.bracket-bl { bottom: 0; left: 0; border-width: 0 0 2px 2px; }
.bracket-br { bottom: 0; right: 0; border-width: 0 2px 2px 0; }
</style>
