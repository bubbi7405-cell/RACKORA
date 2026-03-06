<template>
    <div class="lab-overlay-backdrop" @click.self="$emit('close')">
        <div class="lab-card">
            <header class="lab-header">
                <div class="header-main">
                    <div class="lab-icon">🔬</div>
                    <div class="lab-titles">
                        <h1>Hardware Benchmarking Lab</h1>
                        <p>Optimize voltage and clock curves for permanent model efficiency.</p>
                    </div>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="lab-content">
                <!-- Sidebar: Model Selection -->
                <aside class="model-sidebar">
                    <h3>Select Model</h3>
                    <div class="model-list">
                        <div 
                            v-for="(models, category) in serverCatalog" 
                            :key="category"
                            class="model-group"
                        >
                            <div class="group-title">{{ formatCategory(category) }}</div>
                            <button 
                                v-for="(model, key) in models" 
                                :key="key"
                                class="model-item"
                                :class="{ active: selectedModelKey === key, optimized: isOptimized(key) }"
                                @click="selectModel(key, model)"
                            >
                                <span class="model-name">{{ model.modelName }}</span>
                                <span v-if="isOptimized(key)" class="optimized-badge">★ +10%</span>
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- Main Area: Tuning -->
                <main class="tuning-area" v-if="selectedModel">
                    <div class="bench-visual">
                        <div class="server-tray" :class="{ 'testing': isRunning, 'failed': testResult && !testResult.test_passed }">
                            <div class="tray-inner">
                                <div class="cpu-socket">
                                    <div class="cpu-die" :style="thermalColor"></div>
                                    <div class="voltage-arcs" v-if="isRunning"></div>
                                </div>
                                <div class="status-display">
                                    <div class="stat">
                                        <label>CLOCK</label>
                                        <div class="value">{{ (selectedModel.cpuClockMhz || 2400) * clockMod }} MHz</div>
                                    </div>
                                    <div class="stat">
                                        <label>VOLTAGE</label>
                                        <div class="value">{{ (selectedModel.cpuVoltageV || 1.1).toFixed(3) * voltageMod }} V</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stability-meter">
                            <label>Predicted Stability</label>
                            <div class="meter-bar">
                                <div class="fill" :style="{ width: stabilityPrediction + '%', background: getStabilityColor(stabilityPrediction) }"></div>
                            </div>
                            <span class="meter-value">{{ stabilityPrediction.toFixed(1) }}%</span>
                        </div>
                    </div>

                    <div class="controls-grid">
                        <div class="control-group">
                            <div class="control-header">
                                <label>Clock Multiplier</label>
                                <span class="value">x{{ clockMod.toFixed(2) }}</span>
                            </div>
                            <input type="range" v-model.number="clockMod" min="1.0" max="1.5" step="0.01" :disabled="isRunning">
                            <p class="hint">Higher clock increases throughput but reduces stability.</p>
                        </div>

                        <div class="control-group">
                            <div class="control-header">
                                <label>Voltage Multiplier</label>
                                <span class="value">x{{ voltageMod.toFixed(2) }}</span>
                            </div>
                            <input type="range" v-model.number="voltageMod" min="0.9" max="1.3" step="0.01" :disabled="isRunning">
                            <p class="hint">Higher voltage increases stability but raises temperature.</p>
                        </div>
                    </div>

                    <div class="action-footer">
                        <div class="cost-info">
                            <span class="cost">$500</span>
                            <span class="label">per test run</span>
                        </div>
                        <button 
                            class="run-btn" 
                            :disabled="isRunning || !canAfford"
                            @click="runTest"
                        >
                            <span v-if="!isRunning">RUN BENCHMARK</span>
                            <span v-else class="loader"></span>
                        </button>
                    </div>

                    <!-- Result Overlay -->
                    <transition name="fade">
                        <div v-if="testResult" class="test-result-pop" :class="{ success: testResult.test_passed, failure: !testResult.test_passed, secret: testResult.is_secret_found }">
                            <div class="result-icon">{{ testResult.test_passed ? '✅' : '❌' }}</div>
                            <div class="result-text">
                                <h2>{{ testResult.test_passed ? 'Test Passed!' : 'System Crash!' }}</h2>
                                <p v-if="testResult.test_passed">Stability: {{ testResult.stability }}% | Performance: +{{ testResult.performance_gain }}%</p>
                                <p v-else>The configuration was too unstable for the workload.</p>
                                
                                <div v-if="testResult.is_secret_found" class="secret-found">
                                    <h3>★ SECRET OPTIMIZATION FOUND ★</h3>
                                    <p>Optimal efficiency found! Permanent +10% power reduction unlocked for all {{ selectedModel.modelName }} units.</p>
                                </div>
                            </div>
                            <button @click="testResult = null">DISMISS</button>
                        </div>
                    </transition>
                </main>

                <div v-else class="empty-state">
                    <div class="pulse-icon">⚡</div>
                    <h2>Select a server model to begin benchmarking</h2>
                    <p>Unlock permanent performance and efficiency bonuses by finding the secret optimal settings for each hardware model.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';
import SoundManager from '../../services/SoundManager';

const emit = defineEmits(['close']);

const gameStore = useGameStore();
const serverCatalog = ref({});
const benchmarks = ref({});
const selectedModelKey = ref(null);
const selectedModel = ref(null);

const clockMod = ref(1.0);
const voltageMod = ref(1.0);
const isRunning = ref(false);
const testResult = ref(null);

const playerBalance = computed(() => gameStore.player?.economy?.balance || 0);
const canAfford = computed(() => playerBalance.value >= 500);

const stabilityPrediction = computed(() => {
    // Prediction formula matching backend roughly
    const cp = Math.max(0, (clockMod.value - 1.0) * 150);
    const vs = Math.max(0, (voltageMod.value - 1.0) * 120);
    return Math.max(0, Math.min(100, 100.0 - cp + vs));
});

const thermalColor = computed(() => {
    const temp = (voltageMod.value - 1.0) * 200; // 0 to 60ish
    const hue = 200 - (temp * 3); // Blue to Red
    return {
        background: `hsl(${hue}, 80%, 50%)`,
        boxShadow: `0 0 20px hsla(${hue}, 80%, 50%, 0.5)`
    };
});

const isOptimized = (key) => {
    return benchmarks.value[key]?.optimized || false;
};

const formatCategory = (cat) => {
    return cat.replace('_', ' ').toUpperCase();
};

const selectModel = (key, model) => {
    selectedModelKey.value = key;
    selectedModel.value = model;
    testResult.value = null;
    // Reset to found settings if optimized
    if (isOptimized(key)) {
        clockMod.value = benchmarks.value[key].clock || 1.0;
        voltageMod.value = benchmarks.value[key].voltage || 1.0;
    } else {
        clockMod.value = 1.0;
        voltageMod.value = 1.0;
    }
};

const getStabilityColor = (val) => {
    if (val > 80) return '#4ade80';
    if (val > 50) return '#fbbf24';
    return '#ef4444';
};

const runTest = async () => {
    if (isRunning.value || !canAfford.value) return;

    isRunning.value = true;
    testResult.value = null;
    SoundManager.playClick();

    try {
        const response = await api.post('/hardware/benchmarks/run', {
            model_key: selectedModelKey.value,
            clock_mod: clockMod.value,
            voltage_mod: voltageMod.value
        });

        // Simulate computation delay
        setTimeout(() => {
            isRunning.value = false;
            testResult.value = response;
            
            if (response.test_passed) {
                SoundManager.playSuccess();
                if (response.is_secret_found) {
                    SoundManager.playNotification();
                    loadBenchmarks(); // Refresh
                }
            } else {
                SoundManager.playError();
            }
            
            gameStore.loadGameState(); // Refresh balance
        }, 2000);

    } catch (e) {
        isRunning.value = false;
        console.error(e);
    }
};

const loadBenchmarks = async () => {
    try {
        const response = await api.get('/hardware/benchmarks');
        if (response.success) {
            benchmarks.value = response.benchmarks;
        }
    } catch (e) { console.error(e); }
};

onMounted(() => {
    loadBenchmarks();
});

watch(() => gameStore.hardware?.servers, (newVal) => {
    if (newVal) {
        serverCatalog.value = newVal;
    }
}, { immediate: true });
</script>

<style scoped>
.lab-overlay-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5000;
}

.lab-card {
    width: 1000px;
    height: 700px;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.lab-header {
    padding: 24px 32px;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-main {
    display: flex;
    align-items: center;
    gap: 16px;
}

.lab-icon {
    font-size: 32px;
}

.lab-titles h1 {
    font-size: 20px;
    font-weight: 700;
    background: linear-gradient(135deg, #38bdf8, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.lab-titles p {
    font-size: 13px;
    color: #94a3b8;
    margin: 4px 0 0 0;
}

.close-btn {
    background: none;
    border: none;
    color: #475569;
    font-size: 28px;
    cursor: pointer;
    transition: color 0.2s;
}

.close-btn:hover {
    color: white;
}

.lab-content {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.model-sidebar {
    width: 280px;
    background: rgba(0, 0, 0, 0.2);
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    padding: 20px;
    overflow-y: auto;
}

.model-sidebar h3 {
    font-size: 11px;
    letter-spacing: 0.1em;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 16px;
}

.model-group {
    margin-bottom: 24px;
}

.group-title {
    font-size: 10px;
    color: #475569;
    margin-bottom: 8px;
    font-weight: 600;
}

.model-item {
    width: 100%;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 10px 12px;
    text-align: left;
    color: #94a3b8;
    cursor: pointer;
    margin-bottom: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.model-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
}

.model-item.active {
    background: rgba(56, 189, 248, 0.1);
    border-color: rgba(56, 189, 248, 0.3);
    color: #38bdf8;
}

.model-item.optimized {
    border-left: 3px solid #10b981;
}

.optimized-badge {
    font-size: 9px;
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 700;
}

.tuning-area {
    flex: 1;
    padding: 40px;
    display: flex;
    flex-direction: column;
    gap: 32px;
    position: relative;
}

.bench-visual {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 16px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}

.server-tray {
    width: 400px;
    height: 120px;
    background: #1e293b;
    border: 2px solid #334155;
    border-radius: 4px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    padding: 10px;
}

.server-tray.testing {
    animation: shake 0.1s infinite;
    border-color: #38bdf8;
    box-shadow: 0 0 20px rgba(56, 189, 248, 0.2);
}

.server-tray.failed {
    border-color: #ef4444;
}

.tray-inner {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-around;
}

.cpu-socket {
    width: 60px;
    height: 60px;
    background: #0f172a;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.cpu-die {
    width: 30px;
    height: 30px;
    border-radius: 2px;
    transition: background 0.3s, box-shadow 0.3s;
}

.status-display {
    display: flex;
    gap: 24px;
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat label {
    font-size: 10px;
    color: #64748b;
    font-weight: 700;
}

.stat .value {
    font-size: 20px;
    font-family: 'JetBrains Mono', monospace;
    color: #e2e8f0;
}

.stability-meter {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.stability-meter label {
    font-size: 12px;
    color: #94a3b8;
}

.meter-bar {
    width: 80%;
    height: 8px;
    background: #0f172a;
    border-radius: 4px;
    overflow: hidden;
}

.meter-bar .fill {
    height: 100%;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.meter-value {
    font-weight: 700;
    color: #e2e8f0;
}

.controls-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.control-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.control-header label {
    font-size: 14px;
    font-weight: 600;
    color: #cbd5e1;
}

.control-header .value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 16px;
    color: #38bdf8;
}

.hint {
    font-size: 11px;
    color: #64748b;
    margin: 0;
}

input[type="range"] {
    appearance: none;
    background: #1e293b;
    height: 6px;
    border-radius: 3px;
    outline: none;
}

input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 18px;
    height: 18px;
    background: #38bdf8;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.5);
}

.action-footer {
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 24px;
}

.cost-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.cost-info .cost {
    font-size: 24px;
    font-weight: 800;
    color: #fbbf24;
}

.cost-info .label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
}

.run-btn {
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    color: white;
    border: none;
    padding: 16px 48px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    transition: all 0.2s;
}

.run-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.run-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #475569;
}

.test-result-pop {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    background: #1e293b;
    border-radius: 20px;
    padding: 32px;
    text-align: center;
    box-shadow: 0 0 100px rgba(0, 0, 0, 0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    z-index: 10;
}

.test-result-pop.success { border-top: 8px solid #10b981; }
.test-result-pop.failure { border-top: 8px solid #ef4444; }
.test-result-pop.secret { 
    border-top: 8px solid #f59e0b;
    background: radial-gradient(circle at top, #2d1b0d, #1e293b);
}

.result-icon { font-size: 48px; }
.result-text h2 { margin: 0; font-size: 24px; }
.result-text p { color: #94a3b8; font-size: 14px; }

.secret-found {
    margin-top: 16px;
    padding: 16px;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    animation: pulse-glow 2s infinite;
}

.secret-found h3 { color: #f59e0b; font-size: 18px; margin: 0; }

.test-result-pop button {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    padding: 8px 24px;
    border-radius: 8px;
    cursor: pointer;
}

.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px;
    color: #475569;
}

.pulse-icon {
    font-size: 80px;
    margin-bottom: 24px;
    opacity: 0.2;
    animation: float 3s ease-in-out infinite;
}

.empty-state h2 { color: #94a3b8; font-size: 24px; margin-bottom: 12px; }
.empty-state p { max-width: 400px; line-height: 1.6; }

/* Animations */
@keyframes shake {
    0% { transform: translate(1px, 1px) rotate(0deg); }
    10% { transform: translate(-1px, -2px) rotate(-1deg); }
    20% { transform: translate(-3px, 0px) rotate(1deg); }
    30% { transform: translate(3px, 2px) rotate(0deg); }
    40% { transform: translate(1px, -1px) rotate(1deg); }
    50% { transform: translate(-1px, 2px) rotate(-1deg); }
    60% { transform: translate(-3px, 1px) rotate(0deg); }
    70% { transform: translate(3px, 1px) rotate(-1deg); }
    80% { transform: translate(-1px, -1px) rotate(1deg); }
    90% { transform: translate(1px, 2px) rotate(0deg); }
    100% { transform: translate(1px, -2px) rotate(-1deg); }
}

@keyframes float {
    0% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
    100% { transform: translateY(0); }
}

@keyframes pulse-glow {
    0% { box-shadow: 0 0 5px rgba(245, 158, 11, 0.2); }
    50% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.4); }
    100% { box-shadow: 0 0 5px rgba(245, 158, 11, 0.2); }
}

.loader {
    width: 24px;
    height: 24px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
