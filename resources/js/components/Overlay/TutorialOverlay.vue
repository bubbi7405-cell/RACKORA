<template>
    <div class="tutorial-overlay" v-if="isActive">
        <!-- Spotlight Mask -->
        <svg class="spotlight-mask" v-if="spotlightRect" width="100%" height="100%">
            <defs>
                <mask id="spotlight-mask">
                    <rect x="0" y="0" width="100%" height="100%" fill="white" />
                    <!-- The Hole -->
                    <rect 
                        :x="spotlightRect.x - 10" 
                        :y="spotlightRect.y - 10" 
                        :width="spotlightRect.width + 20" 
                        :height="spotlightRect.height + 20" 
                        rx="8" 
                        fill="black" 
                    />
                </mask>
            </defs>
            <rect x="0" y="0" width="100%" height="100%" fill="rgba(0,0,0,0.7)" mask="url(#spotlight-mask)" />
            
            <!-- Glow Border around hole -->
            <rect 
                :x="spotlightRect.x - 10" 
                :y="spotlightRect.y - 10" 
                :width="spotlightRect.width + 20" 
                :height="spotlightRect.height + 20" 
                rx="8" 
                fill="none" 
                stroke="#00f0ff" 
                stroke-width="2" 
                class="glow-border"
            />
        </svg>

        <!-- Floating Guide Box -->
        <div 
            class="guide-box glass-panel animation-bounce-in" 
            :class="{ 'minimized': isMinimized }"
            :style="boxStyle"
        >
             <div class="guide-header" @click="isMinimized = !isMinimized">
                 <div class="mascot">🐴</div>
                 <div class="title">ONBOARDING: {{ currentStep.title }}</div>
                 <button class="btn-minimize">{{ isMinimized ? '▢' : '–' }}</button>
             </div>
             <div class="guide-body" v-if="!isMinimized">
                 <p v-html="currentStep.text"></p>
             </div>
             <div class="guide-footer" v-if="!isMinimized">
                 <div class="progress-dots">
                     <span 
                        v-for="(s, i) in steps" 
                        :key="i"
                        class="dot"
                        :class="{ active: i === currentStepIndex, completed: i < currentStepIndex }"
                     ></span>
                 </div>
                 <div class="actions">
                     <button class="skip-btn" @click.stop="skipTutorial" v-if="!isLastStep">Skip</button>
                     <button 
                        class="next-btn" 
                        :disabled="!canAdvance" 
                        @click.stop="nextStep"
                        :class="{ 'pulse': canAdvance }"
                    >
                         {{ currentStep.nextLabel || (canAdvance ? 'Next' : 'Wait...') }}
                     </button>
                 </div>
             </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useGameStore } from '../../stores/game';
import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();

// Replace storeToRefs
const player = computed(() => gameStore.player);
const rooms = computed(() => gameStore.rooms);
const orders = computed(() => gameStore.orders);
const customers = computed(() => gameStore.customers);

const isMinimized = ref(false);
const spotlightRect = ref(null);
const updateInterval = ref(null);

const steps = [
    {
        id: 'welcome',
        title: 'EXECUTIVE_ONBOARDING',
        text: 'Welcome to the network, Director. I am your <b>EXECUTIVE_ADVISOR</b>. Let\'s bring this facility online and secure your first service contract.',
        highlightSelector: null,
        manualNext: true,
        check: () => true
    },
    {
        id: 'buy_rack',
        title: '1. DEPLOY_INFRASTRUCTURE',
        text: 'Infrastructure is capital. Select an <b>available site segment</b> to deploy your first hardware rack.',
        getHighlight: () => document.getElementById('rack-purchase-modal') || document.querySelector('#tutorial-first-slot'),
        check: () => {
             const room = Object.values(rooms.value || {})[0];
             return room && room.racks && room.racks.length > 0;
        }
    },
    {
        id: 'select_rack',
        title: '2. INSPECT_SITE',
        text: 'Site segment secured. Now **Select the Rack** to manage individual asset slots.',
        highlightSelector: '[id^="rack-unit-"]', 
        check: () => gameStore.selectedRackId
    },
    {
        id: 'buy_server',
        title: '3. PROVISION_HARDWARE',
        text: 'Access the <b>Hardware Catalog</b> (click the icon) and provision a <b>Standard Server</b> into an empty slot in your rack.',
        highlightSelector: '#nav-market',
        check: () => {
            const room = Object.values(rooms.value || {})[0];
            return room?.racks?.some(r => r.servers && r.servers.length > 0);
        }
    },
    {
        id: 'power_on',
        title: '4. AUTHORIZE_OPERATIONS',
        text: 'Passive assets generate no revenue. Select your unit and click <b>AUTHORIZE_OPERATIONS</b> in the control panel to bring it online.',
        highlightSelector: '[id^="server-"]',
        check: () => {
             const room = Object.values(rooms.value || {})[0];
             if (!room?.racks) return false;
             return room.racks.some(r => r.servers && r.servers.some(s => ['online', 'provisioning'].includes(s.status)));
        }
    },
    {
        id: 'monitor',
        title: '5. MONITOR_THERMAL_LOAD',
        text: 'Operations generate heat. Monitor the <b>Thermal Load</b> gauge in the top bar. If it exceeds 55°C, hardware damage is imminent.',
        highlightSelector: '#hud-stats-thermal',
        manualNext: true,
        check: () => true
    },
    {
        id: 'accept_order',
        title: '6. SECURE_CONTRACT',
        text: 'Contracts drive your cash flow. Open the <b>CAPITAL</b> panel and <b>AUTHORIZE</b> an incoming service request.',
        highlightSelector: '#nav-management',
        check: () => {
            const hasAccepted = customers.value?.list?.some(c => c.activeOrdersCount > 0);
            return hasAccepted || orders.value?.provisioning?.length > 0;
        }
    },
    {
        id: 'complete',
        title: 'ONBOARDING_COMPLETE!',
        text: 'You now have full executive authority. Monitor your margins, expand the network, and secure market dominance. Lead on.',
        manualNext: true,
        nextLabel: 'COMMENCE_OPERATIONS',
        check: () => true
    }
];

const currentStepIndex = computed(() => player.value?.tutorial_step || 0);

const currentStep = computed(() => {
    if (currentStepIndex.value >= steps.length) return null;
    return steps[currentStepIndex.value];
});

const isActive = computed(() => {
    return player.value && !player.value.tutorial_completed && currentStep.value;
});

const canAdvance = computed(() => {
    if (!currentStep.value) return false;
    if (currentStep.value.manualNext) return true;
    return currentStep.value.check();
});

const boxStyle = computed(() => {
    // Default center-ish
    let style = { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' };
    
    if (spotlightRect.value) {
        // Position relative to spotlight
        // Try bottom
        const bottomY = spotlightRect.value.y + spotlightRect.value.height + 20;
        if (bottomY + 200 < window.innerHeight) {
            style = { top: `${bottomY}px`, left: `${Math.max(20, spotlightRect.value.x)}px`, transform: 'none' };
        } else {
            // Try top
            style = { bottom: `${window.innerHeight - spotlightRect.value.y + 20}px`, left: `${Math.max(20, spotlightRect.value.x)}px`, transform: 'none' };
        }
    }
    return style;
});

const isLastStep = computed(() => {
    return currentStepIndex.value === steps.length - 1;
});

function updateHighlight() {
    if (!isActive.value || !currentStep.value) {
        spotlightRect.value = null;
        return;
    }
    
    const selector = currentStep.value.highlightSelector;
    let el = null;
    
    if (currentStep.value.getHighlight) {
        el = currentStep.value.getHighlight();
    }
    
    if (!el && selector) {
        el = document.querySelector(selector);
        // Fallback for ID starting with
        if (!el && selector === '[id^="rack-unit-"]') el = document.querySelector('[id^="rack-unit-"]'); 
        if (!el && selector === '[id^="server-"]') el = document.querySelector('[id^="server-"]'); 
    }
    
    if (!el && currentStep.value.alternateSelector) {
        el = document.querySelector(currentStep.value.alternateSelector);
    }
    
    if (el) {
        spotlightRect.value = el.getBoundingClientRect();
    } else {
        spotlightRect.value = null;
    }
}

function nextStep() {
    if (!canAdvance.value) return;
    
    const nextIdx = currentStepIndex.value + 1;
    const isFinished = nextIdx >= steps.length;
    
    gameStore.updateTutorialProgress(
        isFinished ? steps.length : nextIdx, 
        isFinished
    );
    
    if (isFinished) SoundManager.playSuccess();
    else SoundManager.playClick();
    
    // Force re-check highlight
    spotlightRect.value = null;
    setTimeout(updateHighlight, 500);
}

function skipTutorial() {
    SoundManager.playClick();
    gameStore.updateTutorialProgress(steps.length, true);
}

// Watchers
watch(() => currentStepIndex.value, () => {
    nextTick(updateHighlight);
});

// Auto-advance check loop
// Event handlers
function handleInteraction() {
    setTimeout(updateHighlight, 100);
}

onMounted(() => {
    updateHighlightedLoop();
    window.addEventListener('resize', updateHighlight);
    window.addEventListener('scroll', updateHighlight, true);
    document.addEventListener('click', handleInteraction);
    // Also listen for keydown (ESC etc)
    document.addEventListener('keydown', handleInteraction);
});

onUnmounted(() => {
    if (updateInterval.value) cancelAnimationFrame(updateInterval.value);
    window.removeEventListener('resize', updateHighlight);
    window.removeEventListener('scroll', updateHighlight, true);
    document.removeEventListener('click', handleInteraction);
    document.removeEventListener('keydown', handleInteraction);
});

function updateHighlightedLoop() {
    updateHighlight();
    // Auto advance if condition met (optional, mostly manual click 'next' is better UX for reading)
    // But if step check passes, we enable button.
    updateInterval.value = requestAnimationFrame(updateHighlightedLoop);
}

</script>

<style scoped>
.tutorial-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    z-index: var(--zi-tutorial-mask); pointer-events: none;
}

.spotlight-mask {
    position: absolute; top: 0; left: 0; pointer-events: none;
    z-index: calc(var(--zi-tutorial-mask) + 1);
}

.glow-border {
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0% { stroke-opacity: 0.5; stroke-width: 2; }
    50% { stroke-opacity: 1; stroke-width: 4; }
    100% { stroke-opacity: 0.5; stroke-width: 2; }
}

.guide-box {
    position: absolute;
    width: 360px;
    background: rgba(10, 15, 25, 0.95);
    border: 1px solid var(--color-primary);
    box-shadow: 0 0 30px rgba(0, 240, 255, 0.2);
    border-radius: 8px;
    padding: 20px;
    pointer-events: auto; /* Enable interaction */
    color: #fff;
    transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
    z-index: calc(var(--zi-tutorial-mask) + 2);
    backdrop-filter: blur(10px);
}

.guide-box.minimized { width: 280px; padding: 12px; }

.guide-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; cursor: pointer; }
.guide-box.minimized .guide-header { margin-bottom: 0; }

.mascot { font-size: 1.5rem; background: #fff; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #000; }
.title { font-weight: 800; font-size: 0.85rem; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.1em; flex: 1; }

.btn-minimize { background: none; border: none; color: #666; cursor: pointer; font-size: 1.2rem; }
.btn-minimize:hover { color: #fff; }

.guide-body p { line-height: 1.5; color: #ccc; font-size: 0.9rem; margin: 0; }
.guide-body b { color: #fff; font-weight: 700; }

.guide-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; }

.progress-dots { display: flex; gap: 6px; }
.dot { width: 6px; height: 6px; background: #333; border-radius: 50%; transition: all 0.3s; }
.dot.active { background: var(--color-primary); transform: scale(1.2); }
.dot.completed { background: var(--color-success); }

.actions { display: flex; gap: 10px; }

.skip-btn {
    background: transparent; border: 1px solid #333; color: #666;
    padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: 700;
}
.skip-btn:hover { border-color: #666; color: #aaa; }

.next-btn {
    background: var(--color-primary); color: #000; border: none;
    padding: 6px 20px; border-radius: 4px; font-size: 0.8rem; font-weight: 800;
    cursor: pointer; transition: all 0.2s;
}
.next-btn:disabled { opacity: 0.3; cursor: not-allowed; background: #333; color: #666; }
.next-btn.pulse { animation: btn-pulse 1.5s infinite; }

@keyframes btn-pulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 240, 255, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(0, 240, 255, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0, 240, 255, 0); }
}

@keyframes bounce-in {
    0% { opacity: 0; transform: translateY(10px) scale(0.95); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
.animation-bounce-in { animation: bounce-in 0.4s cubic-bezier(0.19, 1, 0.22, 1) forwards; }
</style>
