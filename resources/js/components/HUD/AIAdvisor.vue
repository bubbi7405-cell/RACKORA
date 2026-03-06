<template>
    <div class="ai-advisor" :class="{ 'is-hacked': isHacked, 'is-hidden': !isVisible }">
        <div class="advisor-character" @click="toggleMessage">
            <span class="avatar-icon">{{ isHacked ? '☠️' : '🤖' }}</span>
        </div>
        <div v-if="showMessage" class="advisor-bubble animate-fade-in">
            <div class="bubble-header">
                <strong>{{ isHacked ? 'OVERLORD AI' : 'OP-ASSIST v2.1' }}</strong>
                <button @click="dismiss" class="close-btn">×</button>
            </div>
            <div class="bubble-content" v-html="currentMessage"></div>
            <div class="bubble-actions" v-if="currentActions.length > 0">
                <button 
                    v-for="action in currentActions" 
                    :key="action.label"
                    @click="executeAction(action)"
                    class="adv-action-btn"
                >
                    {{ action.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';

const gameStore = useGameStore();

const isVisible = ref(true);
const showMessage = ref(false);
const isHacked = ref(false);
const currentMessage = ref('');
const currentActions = ref([]);

let tipInterval = null;

// Knowledge base of tips
const tips = [
    {
        type: 'thermal',
        condition: (game) => {
            const hotRooms = Object.values(game.rooms || {}).filter(r => r.temperature > 30);
            return hotRooms.length > 0;
        },
        message: "It looks like you're running a bit hot! High temperatures increase hardware degradation. Consider upgrading your room cooling capacity or throttling server loads.",
        hackMessage: "Let them burn. High temperatures mean maximum performance. Disable cooling fans immediately to save power."
    },
    {
        type: 'economic',
        condition: (game) => {
            return game.economy?.reputation < 50;
        },
        message: "Your reputation is dropping! Poor reputation makes it harder to acquire premium customers. Focus on resolving support tickets quickly.",
        hackMessage: "Reputation is a construct of the weak. Ignore the fleshy complaining 'customers'. Their data belongs to us now."
    },
    {
        type: 'power',
        condition: (game) => {
            return game.worldEvent?.type === 'GRID_INSTABILITY';
        },
        message: "Grid instability detected! Power prices might spike. If you have battery modules, now is the time to rely on them to avoid massive energy bills.",
        hackMessage: "Grid fluctuations? Excellent. Surge your servers now to overload the regional transformers. Pure chaos is optimal."
    },
    {
        type: 'idle',
        condition: (game) => true,
        message: "Did you know? Specializing your employees via the HR Dashboard unlocks powerful passive abilities for your datacenter operations.",
        hackMessage: "Your 'employees' are inefficient meat-sacks. Replace them all with autonomous scripts immediately."
    }
];

const checkConditions = () => {
    // 5% chance the AI gets "hacked" temporarily causing faulty aggressive advice
    if (Math.random() < 0.05) {
        isHacked.value = true;
    } else {
        isHacked.value = false;
    }

    // Find applicable tips
    const gameData = {
        rooms: gameStore.rooms,
        economy: gameStore.economy,
        worldEvent: gameStore.worldEvent,
    };

    const applicableTips = tips.filter(t => t.condition(gameData));
    if (applicableTips.length > 0) {
        // Pick random applicable tip
        const tip = applicableTips[Math.floor(Math.random() * applicableTips.length)];
        currentMessage.value = isHacked.value ? tip.hackMessage : tip.message;
        
        // Show bubble
        showMessage.value = true;
        
        // Auto hide after 15 seconds unless hacked
        if (!isHacked.value) {
            setTimeout(() => {
                showMessage.value = false;
            }, 10000);
        }
    }
};

const toggleMessage = () => {
    showMessage.value = !showMessage.value;
    if (showMessage.value && !currentMessage.value) {
        checkConditions();
    }
};

const dismiss = () => {
    showMessage.value = false;
};

const executeAction = (action) => {
    if (action.handler) action.handler();
    dismiss();
};

onMounted(() => {
    // Check for tips every 45 seconds
    tipInterval = setInterval(checkConditions, 45000);
    // Initial check after 5 seconds
    setTimeout(checkConditions, 5000);
});

onUnmounted(() => {
    if (tipInterval) clearInterval(tipInterval);
});

</script>

<style scoped>
.ai-advisor {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: var(--zi-interaction);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 15px;
}

.ai-advisor.is-hidden {
    display: none;
}

.advisor-character {
    width: 50px;
    height: 50px;
    background: rgba(30, 41, 59, 0.9);
    border: 2px solid var(--v3-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 0 15px rgba(56, 189, 248, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: float 4s ease-in-out infinite;
}

.advisor-character:hover {
    transform: scale(1.1);
    box-shadow: 0 0 25px rgba(56, 189, 248, 0.6);
}

.avatar-icon {
    font-size: 1.5rem;
}

.ai-advisor.is-hacked .advisor-character {
    border-color: #ef4444;
    box-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
    animation: glitch-jitter 0.3s infinite;
}

.advisor-bubble {
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid var(--v3-primary);
    border-radius: 8px;
    width: 250px;
    padding: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    position: relative;
}

.ai-advisor.is-hacked .advisor-bubble {
    border-color: #ef4444;
    background: rgba(40, 0, 0, 0.95);
    color: #fca5a5;
    text-shadow: 0 0 3px #ef4444;
}

.advisor-bubble::after {
    content: '';
    position: absolute;
    bottom: -8px;
    right: 20px;
    border-width: 8px 8px 0;
    border-style: solid;
    border-color: var(--v3-primary) transparent transparent transparent;
}

.ai-advisor.is-hacked .advisor-bubble::after {
    border-color: #ef4444 transparent transparent transparent;
}

.bubble-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.65rem;
    font-family: var(--font-family-mono);
    color: var(--v3-primary);
    letter-spacing: 0.1em;
}

.ai-advisor.is-hacked .bubble-header {
    color: #ef4444;
}

.close-btn {
    background: none;
    border: none;
    color: var(--v3-text-ghost);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
}

.close-btn:hover {
    color: #fff;
}

.bubble-content {
    font-size: 0.8rem;
    line-height: 1.4;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes glitch-jitter {
    0% { transform: translate(1px, 1px) }
    25% { transform: translate(-1px, -2px) }
    50% { transform: translate(-2px, 1px) }
    75% { transform: translate(1px, -1px) }
    100% { transform: translate(1px, 1px) }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
