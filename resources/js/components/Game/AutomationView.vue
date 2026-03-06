<template>
    <div class="v2-main-viewport automation-view">
        <header class="v2-content-header">
            <div class="v2-breadcrumb l3-priority">
                <span class="v2-path">STRATEGIC_DIRECTIVES</span>
                <span class="v2-sep">≫</span>
                <span class="v2-asset-site">LOGIC_AUTOMATION</span>
            </div>
            <div class="header-stats">
                <div class="h-stat">
                    <span class="hs-label l3-priority">AI_CORES_ACTIVE</span>
                    <span class="hs-val l1-priority">{{ activeCount }}</span>
                </div>
            </div>
        </header>

        <div class="v2-content-scroll">
            <div class="v2-title l2-priority">DIRECTIVE_MANAGEMENT // [AUTOMATION_PROTOCOLS]</div>
            
            <div class="automation-grid">
                <div 
                    v-for="(feature, key) in automationStore.config" 
                    :key="key" 
                    class="auto-card"
                    :class="{ 
                        'is-active': feature.enabled, 
                        'is-locked': playerLevel < feature.unlockLevel 
                    }"
                >
                    <div class="auto-header">
                        <div class="auto-icon">⚙️</div>
                        <div class="auto-meta">
                            <h3 class="auto-label l1-priority">{{ feature.label }}</h3>
                            <span class="auto-status">{{ feature.enabled ? 'ACTIVE' : 'READY' }}</span>
                        </div>
                        <div v-if="playerLevel < feature.unlockLevel" class="lock-overlay">
                            <span>LOCKED // REQ: LVL_{{ feature.unlockLevel }}</span>
                        </div>
                    </div>
                    
                    <p class="auto-desc l3-priority">{{ feature.desc }}</p>

                    <div class="auto-actions">
                        <div v-if="feature.enabled" class="status-indicator">
                            <span class="pip online"></span>
                            <span class="text">EXECUTING_LOGIC</span>
                        </div>
                        <button 
                            v-else 
                            class="v2-cmd-btn l1-priority" 
                            :disabled="playerLevel < feature.unlockLevel"
                            @click="automationStore.toggleAutomation(key)"
                        >
                            AUTHORIZE_PROTOCOL
                        </button>
                        <button 
                            v-if="feature.enabled" 
                            class="v2-cmd-btn secondary" 
                            @click="automationStore.toggleAutomation(key)"
                        >
                            TERMINATE
                        </button>
                    </div>
                </div>
            </div>

            <div class="v2-section info-section">
                <div class="v2-card help-card">
                    <div class="v2-title small l2-priority">AUTOMATION_PROTOCOL_OPERATIONS</div>
                    <p class="l3-priority">
                        Logic automation reduces the need for manual intervention by delegating complex infrastructure tasks to regional AI cores. 
                        Active protocols increase operational efficiency but may consume additional CPU cycles globally.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAutomationStore } from '../../stores/automation';
import { useEconomyStore } from '../../stores/economy';

const automationStore = useAutomationStore();
const economyStore = useEconomyStore();

const playerLevel = computed(() => economyStore.player?.economy?.level || 1);
const activeCount = computed(() => Object.values(automationStore.config).filter(f => f.enabled).length);
</script>

<style scoped>
.automation-view {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.automation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.auto-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 24px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.auto-card.is-active {
    background: rgba(58, 134, 255, 0.05);
    border-color: var(--color-accent);
}

.auto-card.is-locked {
    opacity: 0.5;
}

.auto-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.auto-icon { font-size: 1.5rem; background: rgba(0,0,0,0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.auto-label { font-weight: 800; font-size: 0.9rem; margin: 0; }
.auto-status { font-size: 0.6rem; color: var(--color-muted); letter-spacing: 0.1em; font-weight: 800; }

.auto-desc { font-size: 0.75rem; margin: 0; line-height: 1.4; }

.auto-actions {
    margin-top: auto;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
}

.status-indicator { display: flex; align-items: center; gap: 8px; font-size: 0.6rem; font-weight: 900; color: var(--color-success); }
.pip { width: 6px; height: 6px; border-radius: 50%; }
.pip.online { background: var(--color-success); box-shadow: 0 0 8px var(--color-success); }

.lock-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.lock-overlay span { font-size: 0.7rem; font-weight: 900; color: #fff; background: #c52f24; padding: 4px 12px; border-radius: 4px; }

.header-stats { display: flex; align-items: flex-end; }
.h-stat { display: flex; flex-direction: column; align-items: flex-end; }
.hs-label { font-size: 0.55rem; color: var(--v2-text-muted); font-weight: 800; }
.hs-val { font-size: 1.2rem; font-weight: 900; color: var(--color-accent); font-family: monospace; }
</style>
