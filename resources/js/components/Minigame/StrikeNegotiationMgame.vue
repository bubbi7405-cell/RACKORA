<template>
    <div class="minigame-overlay">
        <div class="dialogue-card glass-panel shadow-danger">
            <div class="dialogue-character">
                <div class="avatar-placeholder">🪧</div>
                <div class="char-info">
                    <span class="char-name">Viktor, Union Rep</span>
                    <span class="char-org">United Infrastructure Workers</span>
                </div>
            </div>

            <div class="dialogue-content">
                <div class="typewriter-text">{{ currentText }}</div>
            </div>

            <div class="dialogue-options" v-if="!isProcessing">
                <button 
                    v-for="(option, idx) in currentOptions" 
                    :key="idx"
                    class="option-btn"
                    @click="handleOption(option)"
                >
                    <span class="option-label">{{ option.label }}</span>
                    <span class="option-meta" v-if="option.meta">{{ option.meta }}</span>
                </button>
            </div>
            
            <div v-else class="processing-bar">
                <div class="bar-fill"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const emit = defineEmits(['complete']);

const currentText = ref("We've had enough. Your record profits are built on our burnout. We want fair pay and better benefits, or the servers stay dark.");
const isProcessing = ref(false);

const options = [
    { 
        label: "Agree to 15% Wage Increase", 
        meta: "Permanent Salary ++",
        outcome: 'raise',
        nextText: "Finally, some respect. The guys will be back on the floor within the hour."
    },
    { 
        label: "Offer Comprehensive Benefit Package", 
        meta: "$15,000 upfront + 5% Wage",
        outcome: 'benefits',
        nextText: "It's a start. We'll accept this for now. Don't let the maintenance backlog grow again."
    },
    { 
        label: "Threaten Legal Action & Wage Freezes", 
        meta: "Risk: Escalation or Break",
        outcome: 'intimidate',
        nextText: "You think you can bully us? We'll see how your clients like a total blackout!"
    },
    { 
        label: "Hire Replacement Scab Labor", 
        meta: "$30,000 + Massive Rep Loss",
        outcome: 'scabs',
        nextText: "You're selling us out? This company will have no soul left. Fine, bring in your scabs."
    }
];

const currentOptions = ref(options);

const handleOption = async (option) => {
    isProcessing.value = true;
    currentText.value = option.nextText;
    currentOptions.value = [];
    
    // Determine success if random
    let success = true;
    if (option.outcome === 'intimidate') {
        success = Math.random() < 0.35; // Low success chance for intimidation
        if (success) {
            currentText.value = "...Fine. We'll go back. But we're watching you.";
        }
    }

    setTimeout(() => {
        emit('complete', { outcome: option.outcome, success });
    }, 2500);
};

</script>

<style scoped>
.minigame-overlay {
    position: fixed; inset: 0; z-index: 2100;
    background: rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(8px);
}

.dialogue-card {
    width: 600px; background: #0c0c12; border: 1px solid #333;
    padding: 40px; display: flex; flex-direction: column; gap: 30px;
    border-radius: 12px;
}

.dialogue-character {
    display: flex; align-items: center; gap: 20px;
}

.avatar-placeholder {
    width: 64px; height: 64px; background: #1a1a25; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 2rem;
    border: 2px solid #ef4444;
}

.char-info { display: flex; flex-direction: column; }
.char-name { font-weight: 800; font-size: 1.1rem; color: #fff; }
.char-info .char-org { font-size: 0.75rem; color: #f87171; text-transform: uppercase; font-weight: 700; }

.dialogue-content {
    background: rgba(255,255,255,0.03); border-left: 4px solid #ef4444;
    padding: 20px; min-height: 100px; font-style: italic; color: #ddd;
    line-height: 1.6;
}

.typewriter-text { font-size: 1.05rem; }

.dialogue-options {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;
}

.option-btn {
    background: #151520; border: 1px solid #333; padding: 15px; text-align: left;
    display: flex; flex-direction: column; gap: 4px; cursor: pointer; transition: all 0.2s;
    border-radius: 8px;
}

.option-btn:hover { background: #1e1e30; border-color: #ef4444; }

.option-label { color: #fff; font-weight: 700; font-size: 0.9rem; }
.option-meta { font-size: 0.7rem; color: #888; font-weight: 600; }

.processing-bar {
    height: 4px; background: #111; border-radius: 2px; overflow: hidden;
}

.bar-fill {
    height: 100%; background: #ef4444; width: 0;
    animation: bar-progress 2.5s linear forwards;
}

@keyframes bar-progress {
    from { width: 0; }
    to { width: 100%; }
}
</style>
