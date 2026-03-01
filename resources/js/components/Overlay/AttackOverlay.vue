<template>
    <transition name="attack-flash">
        <div v-if="attack" class="attack-overlay">
            <div class="scanlines"></div>
            <div class="glitch-red"></div>
            
            <div class="alert-content">
                <div class="warning-header">
                    <span class="warning-icon">⚠️</span>
                    <span class="warning-title">SABOTAGE_DETECTED</span>
                    <span class="warning-icon">⚠️</span>
                </div>
                
                <div class="divider"></div>
                
                <div class="attacker-info">
                    <div class="label">SOURCE_ID</div>
                    <div class="value">{{ attack.competitor_name }}</div>
                    <div class="sub-label">Archetype: {{ attack.competitor_archetype }}</div>
                </div>
                
                <div class="attack-type">
                    <div class="label">VECTOR_TYPE</div>
                    <div class="value pulse">{{ attack.action_type?.toUpperCase() }}</div>
                </div>
                
                <div class="threat-msg">
                    INTEL_LOG: Defensive protocols engaged. Monitor system integrity immediately.
                </div>
                
                <div class="countdown-bar">
                    <div class="bar-fill"></div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { computed } from 'vue';
import { useUiStore } from '../../stores/ui';

const uiStore = useUiStore();
const attack = computed(() => uiStore.activeAttack);
</script>

<style scoped>
.attack-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(40, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    border: 4px solid var(--v3-danger);
    animation: border-flash 1s infinite;
}

.scanlines {
    position: absolute;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        to bottom,
        rgba(255, 0, 0, 0) 50%,
        rgba(255, 0, 0, 0.05) 50%
    );
    background-size: 100% 4px;
    z-index: 1;
}

.alert-content {
    background: rgba(0, 0, 0, 0.9);
    border: 1px solid var(--v3-danger);
    padding: 40px;
    max-width: 500px;
    width: 90%;
    position: relative;
    z-index: 10;
    box-shadow: 0 0 50px rgba(255, 77, 79, 0.3);
    text-align: center;
}

.warning-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 20px;
}

.warning-title {
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--v3-danger);
    letter-spacing: 0.2em;
    text-shadow: 0 0 15px rgba(255, 77, 79, 0.5);
}

.warning-icon {
    font-size: 2rem;
    animation: blink 0.5s infinite;
}

.divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--v3-danger), transparent);
    margin: 20px 0;
}

.attacker-info, .attack-type {
    margin-bottom: 25px;
}

.label {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    letter-spacing: 0.1em;
    margin-bottom: 5px;
}

.value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
    text-transform: uppercase;
}

.pulse {
    animation: pulse-red 1s infinite;
}

.sub-label {
    font-size: 0.6rem;
    color: var(--v3-danger);
    opacity: 0.7;
    margin-top: 5px;
}

.threat-msg {
    font-size: 0.8rem;
    color: var(--v3-text-dim);
    line-height: 1.5;
    margin-bottom: 30px;
}

.countdown-bar {
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: var(--v3-danger);
    width: 100%;
    animation: shrink-bar 10s linear forwards;
}

@keyframes shrink-bar {
    from { width: 100%; }
    to { width: 0%; }
}

@keyframes border-flash {
    0%, 100% { border-color: rgba(255, 77, 79, 0.3); }
    50% { border-color: rgba(255, 77, 79, 1); }
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

@keyframes pulse-red {
    0%, 100% { text-shadow: 0 0 5px rgba(255, 77, 79, 1); }
    50% { text-shadow: 0 0 20px rgba(255, 77, 79, 1); }
}

.attack-flash-enter-active {
    animation: flash-in 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.attack-flash-leave-active {
    animation: flash-in 0.3s reverse;
}

@keyframes flash-in {
    0% { opacity: 0; background: rgba(255, 0, 0, 0.8); }
    100% { opacity: 1; background: rgba(40, 0, 0, 0.4); }
}
</style>
