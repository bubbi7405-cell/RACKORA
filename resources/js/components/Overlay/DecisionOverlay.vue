<template>
    <div class="overlay-backdrop">
        <div class="decision-overlay glass-panel animation-fade-in" v-if="currentDecision">
            <div class="decision-content">
                <div class="header-section">
                    <div class="milestone-badge">STRATEGIC MILESTONE: LEVEL {{ currentDecision.triggered_at_level }}</div>
                    <h1>{{ currentDecision.title }}</h1>
                    <p class="description">{{ currentDecision.description }}</p>
                </div>

                <div class="options-grid">
                    <div 
                        v-for="(option, key) in currentDecision.options" 
                        :key="key" 
                        class="option-card"
                        :class="{ selected: selectedOption === key }"
                        @click="selectedOption = key"
                    >
                        <div class="option-header">
                            <div class="option-icon">{{ getIcon(key) }}</div>
                            <h3>{{ option.title }}</h3>
                        </div>
                        <div class="option-body">
                            <p class="option-desc">{{ option.description }}</p>
                            <div class="effects-list">
                                <span class="effect-tag">{{ option.effects }}</span>
                            </div>
                        </div>
                        <div class="selection-indicator">
                            <div class="check" v-if="selectedOption === key">✓</div>
                        </div>
                    </div>
                </div>

                <div class="action-footer">
                    <p class="warning">⚠️ This decision is permanent and will define your company's path.</p>
                    <button 
                        class="confirm-btn" 
                        :disabled="!selectedOption || processing"
                        @click="confirmDecision"
                    >
                        <span v-if="!processing">COMMIT TO STRATEGY</span>
                        <span v-else class="loader"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const gameStore = useGameStore();
const processing = ref(false);
const selectedOption = ref(null);

const currentDecision = computed(() => {
    const pending = gameStore.player?.economy?.pendingDecisions || [];
    return pending.length > 0 ? pending[0] : null;
});

const getIcon = (key) => {
    const icons = {
        'budget': '📦',
        'premium': '💎',
        'balanced': '⚖️',
        'green': '☘️',
        'standard': '🔌',
        'managed': '🛠️',
        'infrastructure': '🏗️'
    };
    return icons[key] || '📊';
};

const confirmDecision = async () => {
    if (!selectedOption.value || !currentDecision.value) return;

    processing.value = true;
    try {
        const response = await api.post('/management/decision', {
            decision_type: currentDecision.value.type,
            option_key: selectedOption.value
        });

        if (response.success) {
            // Success animation or sound could go here
            if (response.gameState) {
                gameStore.applyGameState(response.gameState);
            }
            selectedOption.value = null;
        }
    } catch (e) {
        console.error('Failed to commit decision', e);
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto; /* Important: ensures overlay intercepts clicks */
}

.glass-panel {
    background: rgba(20, 25, 35, 0.7);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.decision-overlay {
    width: 900px;
    max-width: 95vw;
    border: 1px solid rgba(0, 242, 255, 0.2);
    box-shadow: 0 0 80px rgba(0, 212, 255, 0.05);
    border-radius: 20px;
    padding: 50px;
    color: #fff;
    position: relative;
    overflow: hidden;
}

.decision-overlay::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, var(--color-primary), transparent);
}

.header-section {
    text-align: center;
    margin-bottom: 40px;
}

.milestone-badge {
    display: inline-block;
    background: rgba(var(--color-primary-rgb), 0.2);
    color: var(--color-primary);
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 15px;
    border: 1px solid rgba(var(--color-primary-rgb), 0.3);
}

.header-section h1 {
    font-size: 2.5rem;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 2px;
    background: linear-gradient(to bottom, #fff, #aaa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.description {
    color: var(--color-text-muted);
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.option-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 25px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    position: relative;
}

.option-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-5px);
}

.option-card.selected {
    background: rgba(var(--color-primary-rgb), 0.1);
    border-color: var(--color-primary);
    box-shadow: 0 0 20px rgba(var(--color-primary-rgb), 0.2);
}

.option-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.option-icon {
    font-size: 2rem;
}

.option-header h3 {
    margin: 0;
    font-size: 1.2rem;
}

.option-desc {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    line-height: 1.5;
    margin-bottom: 20px;
    min-height: 60px;
}

.effect-tag {
    display: block;
    font-size: 0.8rem;
    color: var(--color-primary);
    background: rgba(var(--color-primary-rgb), 0.1);
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
}

.selection-indicator {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 24px;
    height: 24px;
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.option-card.selected .selection-indicator {
    background: var(--color-primary);
    border-color: var(--color-primary);
}

.check {
    color: #000;
    font-weight: bold;
    font-size: 14px;
}

.action-footer {
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 30px;
}

.warning {
    color: #ffaa00;
    font-size: 0.85rem;
    margin-bottom: 20px;
}

.confirm-btn {
    background: var(--color-primary);
    color: #000;
    border: none;
    padding: 15px 40px;
    font-size: 1rem;
    font-weight: 800;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    letter-spacing: 1px;
    min-width: 250px;
}

.confirm-btn:hover:not(:disabled) {
    background: #00e5f0;
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(var(--color-primary-rgb), 0.5);
}

.confirm-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #555;
    color: #888;
}

.loader {
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0,0,0,0.3);
    border-radius: 50%;
    border-top-color: #000;
    animation: spin 1s linear infinite;
    display: inline-block;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes fade-in {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animation-fade-in {
    animation: fade-in 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
