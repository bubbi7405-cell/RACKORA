<template>
    <div class="tab-content tuning-tab provision-lab">


        <div class="tuning-lab" v-if="server.tuning">
            <!-- Stability Header -->
            <div class="v3-stability-header" :class="{ 'warning': server.tuning.stability < 80, 'critical': server.tuning.stability < 50 }">
                <div class="v3-stab-meta">
                    <label>SYSTEM_STABILITÄTS_INDEX</label>
                    <strong class="v3-stab-val">{{ server.tuning.stability.toFixed(1) }}%</strong>
                </div>
                <div class="v3-stab-bar">
                    <div class="fill" :style="{ width: server.tuning.stability + '%' }"></div>
                </div>
                <div class="stability-alert" v-if="server.tuning.stability < 70">
                    ⚠️ RISIKO: Systeminstabilität erkannt. Erhöhen Sie die Spannung oder senken Sie den Takt.
                </div>
            </div>

            <div class="tuning-scroll-area">
                <div class="tuning-grid-main">
                    <!-- CPU Section -->
                    <div class="tuning-panel-v3">
                        <div class="p-head">
                            <label>CPU_HETERODYNE_CONTROL</label>
                            <i class="hw-icon">⚡</i>
                        </div>
                        
                        <div class="v3-control">
                            <div class="c-info">
                                <span>TAKTFREQUENZ</span>
                                <strong>{{ clockTuning }} MHz</strong>
                            </div>
                            <input type="range" v-model.number="clockTuning" :min="server.tuning.baseClock * 0.5" :max="server.tuning.baseClock * 2.0" step="50" class="v3-range primary">
                            <div class="c-footer">
                                <span>Base: {{ server.tuning.baseClock }} MHz</span>
                                <span :class="clockTuning > server.tuning.baseClock ? 'text-success' : 'text-ghost'">
                                    {{ (clockTuning / server.tuning.baseClock * 100).toFixed(0) }}% Leistung
                                </span>
                            </div>
                        </div>

                        <div class="v3-control">
                            <div class="c-info">
                                <span>KERNSPANNUNG</span>
                                <strong>{{ voltageTuning.toFixed(3) }} V</strong>
                            </div>
                            <input type="range" v-model.number="voltageTuning" :min="0.7" :max="1.6" step="0.005" class="v3-range danger">
                            <div class="c-footer">
                                <span>Base: {{ server.tuning.baseVoltage.toFixed(3) }} V</span>
                                <span :class="voltageTuning > server.tuning.baseVoltage ? 'text-danger' : 'text-ghost'">
                                    {{ (voltageTuning / server.tuning.baseVoltage * 100).toFixed(0) }}% Spannung
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- RAM / Other Section -->
                    <div class="tuning-panel-v3">
                        <div class="p-head">
                            <label>MEMORY_LATENCY_SUBTIMINGS</label>
                            <i class="hw-icon">💾</i>
                        </div>

                        <div class="v3-control">
                            <div class="c-info">
                                <span>CAS_LATENZ_FAKTOR</span>
                                <strong>{{ ramLatencyTuning.toFixed(2) }}x</strong>
                            </div>
                            <input type="range" v-model.number="ramLatencyTuning" min="0.5" max="1.5" step="0.05" class="v3-range warning">
                            <div class="c-footer">
                                <span>Standard: 1.00x</span>
                                <span :class="ramLatencyTuning < 1.0 ? 'text-success' : 'text-ghost'">
                                    {{ (1.0 / ramLatencyTuning * 100).toFixed(0) }}% Zugriffstempo
                                </span>
                            </div>
                        </div>
                        
                        <div v-if="server.type === 'gpu_server'" class="tuning-control-group">
                            <div class="control-label">
                                <span>GPU_TAKT (BOOST)</span>
                                <strong class="val">{{ gpuClockTuning }} MHz</strong>
                            </div>
                            <input type="range" v-model.number="gpuClockTuning" min="500" max="3000" step="25" class="v3-range accent">
                        </div>
                        <div class="stress-test-box-v3">
                            <div class="v3-terminal tuning-terminal" v-if="localIsStressTesting || localStressLogs.length > 0">
                                <div v-for="(log, i) in localStressLogs" :key="i" class="log-line">
                                    <span class="msg">{{ log }}</span>
                                </div>
                            </div>
                            <button @click="runStressTest" :disabled="processing || localIsStressTesting || server.status !== 'online'" class="btn-stress-v3">
                                {{ localIsStressTesting ? 'STRESS_TEST_LÄUFT...' : 'STABILITÄTS_TEST_STARTEN' }}
                            </button>
                            <div v-if="localStressTestResult" class="test-result-v3" :class="localStressTestResult.passed ? 'success' : 'failure'">
                                <span v-if="localStressTestResult.passed">PASSED: System stabil unter Last.</span>
                                <span v-else>FAILED: Thermal Throttling oder System-Crash!</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Impact Analysis -->
                <div class="impact-dashboard">
                    <div class="impact-item">
                        <label>THERMISCHE LAST</label>
                        <div class="impact-val" :class="{ 'warning': server.tuning.degradationMult > 2, 'danger': server.tuning.degradationMult > 5 }">
                            {{ (server.tuning.degradationMult * 100).toFixed(0) }}%
                        </div>
                        <p class="impact-hint">Verschleiß-Multiplikator für Hardware-Zyklen.</p>
                    </div>
                    <div class="impact-item">
                        <label>ENERGIEBEDARF</label>
                        <div class="impact-val">
                            +{{ ((Math.pow(voltageTuning/server.tuning.baseVoltage, 2) * (clockTuning/server.tuning.baseClock) - 1) * 100).toFixed(0) }}%
                        </div>
                        <p class="impact-hint">Zusätzliche Stromkosten pro Tick.</p>
                    </div>
                </div>
            </div>

            <!-- Actions Area -->
            <div class="tuning-footer">
                <div class="main-tuning-btns">
                    <button class="btn-primary-v3" 
                            :disabled="processing || server.status !== 'offline'"
                            @click="applyTuning">
                        PROFIL_AKTIVIEREN
                    </button>
                    <button class="btn-v3-ghost" 
                            :disabled="processing || server.status !== 'offline'"
                            @click="resetTuning">
                        WERKSEINSTELLUNGEN
                    </button>
                </div>
            </div>

            <div class="tuning-not-possible" v-if="server.status !== 'offline'">
                <span>HW_LOCK: Server muss OFFLINE sein, um BIOS-Parameter zu ändern.</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import api from '@/utils/api';
import { useGameStore } from '@/stores/game';

const gameStore = useGameStore();

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

// Tuning state
const clockTuning = ref(0);
const voltageTuning = ref(0);
const ramLatencyTuning = ref(1);
const gpuClockTuning = ref(0);

// Stress test state (moved from parent)
const localStressLogs = ref([]);
const localIsStressTesting = ref(false);
const localStressTestResult = ref(null);

const initializeTuning = () => {
    if (props.server?.tuning) {
        clockTuning.value = props.server.tuning.cpuClock || props.server.tuning.baseClock || 1000;
        voltageTuning.value = props.server.tuning.cpuVoltage || props.server.tuning.baseVoltage || 1.1;
        ramLatencyTuning.value = props.server.tuning.ramLatency || 1.0;
        gpuClockTuning.value = props.server.tuning.gpuClock || 0;
    }
};

watch(() => props.server, () => {
    initializeTuning();
}, { deep: true });

onMounted(() => {
    initializeTuning();
});

const resetTuning = () => {
    if (!props.server?.tuning) return;
    clockTuning.value = props.server.tuning.baseClock;
    voltageTuning.value = props.server.tuning.baseVoltage;
    ramLatencyTuning.value = 1.0;
    if (props.server.type === 'gpu_server') {
        gpuClockTuning.value = 800;
    }
    applyTuning();
};

const applyTuning = async () => {
    if (props.processing) return;

    const payload = {
        cpu_clock_mhz: clockTuning.value,
        cpu_voltage_v: Number(voltageTuning.value.toFixed(3)),
        ram_latency: Number(ramLatencyTuning.value.toFixed(2)),
        gpu_clock_mhz: gpuClockTuning.value > 0 ? gpuClockTuning.value : null
    };

    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/tune`, payload);
        if (response.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const runStressTest = async () => {
    localIsStressTesting.value = true;
    localStressTestResult.value = null;
    localStressLogs.value = ["[INFO] Initiating stress test sequence...", "[INFO] Warming up cores..."];
    
    const lines = [
        "[OK] Cooling system set to 100% duty cycle.",
        "[RUN] Increasing CPU load to 100%...",
        "[DATA] Voltage fluctuation detected: " + (Math.random() * 0.05).toFixed(3) + "V",
        "[RUN] Benchmarking FP32 operations...",
        "[BUSY] I/O stress on primary storage...",
        "[DATA] Measured thermals: " + (50 + Math.random() * 20).toFixed(1) + "°C",
        "[RUN] RAM integrity check (ECC)...",
        "[OK] Integrity validated.",
        "[RUN] Final stability verification..."
    ];

    let i = 0;
    const interval = setInterval(() => {
        if (i < lines.length) {
            localStressLogs.value.push(lines[i]);
            i++;
        } else {
            clearInterval(interval);
            finishTest();
        }
    }, 400);

    const finishTest = async () => {
        try {
            const response = await api.post(`/server/${props.server.id}/stress-test`);
            if (response.success) {
                localStressTestResult.value = response.data;
                localStressLogs.value.push(response.data.passed ? "[SUCCESS] System stable under load." : "[CRITICAL] Stability fail! Automatic throttle engaged.");
                gameStore.loadGameState();
            }
        } finally {
            localIsStressTesting.value = false;
        }
    };
};
</script>
