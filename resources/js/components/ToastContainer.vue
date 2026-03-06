<template>
    <div class="v2-intercept-hub">
        <TransitionGroup name="intercept">
            <div 
                v-for="toast in toasts" 
                :key="toast.id"
                :class="['v2-intercept-item', `is-${toast.type}`, { 'l1-priority': toast.type === 'error' }]"
            >
                <div class="intercept-scanline"></div>
                <div class="intercept-header">
                    <span class="header-tag l3-priority">SOURCE_ID // {{ getTelemetryId(toast.id) }}</span>
                    <span class="header-type l2-priority">{{ getBureauLabel(toast.type) }}</span>
                </div>
                
                <div class="intercept-body">
                    <div class="intercept-icon l1-priority">
                        <!-- Redacted standard SVG icons for a unified industrial look -->
                        <span v-if="toast.type === 'success'">▣</span>
                        <span v-else-if="toast.type === 'error'">⚠</span>
                        <span v-else-if="toast.type === 'warning'">◈</span>
                        <span v-else-if="toast.type === 'achievement'">✦</span>
                        <span v-else>≡</span>
                    </div>
                    <div class="intercept-content">
                        <div class="intercept-message l1-priority">{{ toast.message?.toUpperCase() }}</div>
                        <div class="intercept-status l3-priority">// DATA_STREAM_STABLE</div>
                    </div>
                </div>

                <button class="intercept-close l3-priority" @click="toastStore.removeToast(toast.id)">
                    [ACKNOWLEDGE]
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useToastStore } from '../stores/toast';
import { storeToRefs } from 'pinia';

const toastStore = useToastStore();
const { toasts } = storeToRefs(toastStore);

const getTelemetryId = (id) => {
    return `RX-${1000 + id}-${Math.floor(Math.random() * 900) + 100}`;
};

const getBureauLabel = (type) => {
    const labels = {
        success: 'INTEL_RECOVERY',
        error: 'CRITICAL_BREACH',
        warning: 'OPERATIONAL_RISK',
        achievement: 'MILESTONE_SYNTH',
        info: 'SYSTEM_TELEMETRY'
    };
    return labels[type] || 'SIGNAL_INTERCEPT';
};
</script>

<style scoped>
.v2-intercept-hub {
    position: fixed;
    top: 100px;
    right: 32px;
    z-index: var(--zi-hud-overlay);
    display: flex;
    flex-direction: column;
    gap: 16px;
    pointer-events: none;
}

.v2-intercept-item {
    pointer-events: auto;
    width: 380px;
    background: linear-gradient(135deg, rgba(8, 12, 18, 0.95) 0%, rgba(5, 7, 12, 0.98) 100%);
    border-left: 4px solid var(--ds-accent);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    border-bottom: 2px solid rgba(0, 0, 0, 0.5);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.9);
    position: relative;
    overflow: hidden;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.intercept-scanline {
    position: absolute;
    inset: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%);
    background-size: 100% 2px;
    pointer-events: none;
    opacity: 0.3;
}

.intercept-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 8px;
}

.header-tag {
    font-size: 0.45rem;
    font-weight: 950;
    letter-spacing: 0.2em;
    font-family: var(--ds-font-mono);
}

.header-type {
    font-size: 0.55rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    color: var(--ds-accent);
}

.intercept-body {
    display: flex;
    gap: 16px;
    align-items: center;
}

.intercept-icon {
    font-size: 1.4rem;
    color: var(--ds-accent);
    opacity: 0.8;
}

.intercept-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.intercept-message {
    font-size: 0.75rem;
    font-weight: 950;
    color: #fff;
    line-height: 1.3;
    letter-spacing: 0.02em;
}

.intercept-status {
    font-size: 0.45rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}

.intercept-close {
    align-self: flex-end;
    background: transparent;
    border: none;
    color: var(--ds-text-ghost);
    font-size: 0.5rem;
    font-weight: 950;
    letter-spacing: 0.15em;
    cursor: pointer;
    transition: color 0.2s;
}

.intercept-close:hover {
    color: #fff;
}

/* Severity Modifiers */
.v2-intercept-item.is-error { border-left-color: var(--ds-critical); }
.v2-intercept-item.is-error .header-type { color: var(--ds-critical); }
.v2-intercept-item.is-error .intercept-icon { 
    color: var(--ds-critical); 
    text-shadow: 0 0 10px var(--ds-critical);
}

.v2-intercept-item.is-warning { border-left-color: #fbbf24; }
.v2-intercept-item.is-warning .header-type { color: #fbbf24; }

.v2-intercept-item.is-achievement { border-left-color: #388bfd; }
.v2-intercept-item.is-achievement .header-type { color: #388bfd; }

/* Transitions */
.intercept-enter-active {
    animation: intercept-pop 0.4s var(--ds-ease-spring);
}

.intercept-leave-active {
    animation: intercept-fade-out 0.3s var(--ds-ease-in) forwards;
}

@keyframes intercept-pop {
    0% { opacity: 0; transform: translateX(50px) scale(0.95); filter: blur(4px); }
    100% { opacity: 1; transform: translateX(0) scale(1); filter: blur(0); }
}

@keyframes intercept-fade-out {
    to { opacity: 0; transform: translateX(100px); filter: blur(8px); }
}
</style>
