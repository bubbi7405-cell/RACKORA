<template>
    <div class="hardware-probe">
        <div class="puzzle-header">
            <div class="header-main">
                <span class="version">V_HW_PHYS_PRB</span>
                <h3>HARDWARE_PROBE_SCANNER</h3>
            </div>
            <div class="timer" :class="{ critical: timeLeft < 10 }">
                T-MINUS: {{ timeLeft }}s
            </div>
        </div>

        <div class="puzzle-instructions">
            PROBE THE <span class="highlight">VOLTAGE_NODES</span> WHEN THEY FLUCTUATE TO RESET THE POWER RAIL.
        </div>

        <div class="board-canvas">
            <svg viewBox="0 0 400 300" class="motherboard-svg">
                <!-- Board Base -->
                <rect x="20" y="20" width="360" height="260" rx="10" fill="#1a2b1a" stroke="#2e4d2e" stroke-width="2" />
                
                <!-- CPU Sockets -->
                <rect x="60" y="60" width="80" height="80" rx="4" fill="#222" stroke="#444" />
                <rect x="260" y="60" width="80" height="80" rx="4" fill="#222" stroke="#444" />
                
                <!-- RAM Slots -->
                <rect x="150" y="50" width="10" height="100" rx="1" fill="#111" />
                <rect x="170" y="50" width="10" height="100" rx="1" fill="#111" />
                <rect x="220" y="50" width="10" height="100" rx="1" fill="#111" />
                <rect x="240" y="50" width="10" height="100" rx="1" fill="#111" />

                <!-- Trace Lines (Decorative) -->
                <path d="M 140,100 L 150,100 M 250,100 L 260,100 M 140,140 L 400,140" stroke="#2e4d2e" stroke-width="1" fill="none" opacity="0.5" />

                <!-- Interactive Probe Points -->
                <g v-for="(point, i) in probePoints" :key="i" 
                   class="probe-point" 
                   :class="{ active: point.active, success: point.success, fail: point.fail }"
                   @click="handleProbe(i)"
                >
                    <circle :cx="point.x" :cy="point.y" r="12" class="outer-ring" />
                    <circle :cx="point.x" :cy="point.y" r="6" class="inner-core" />
                    <text :x="point.x" :y="point.y - 15" class="point-label">{{ point.label }}</text>
                </g>
            </svg>
        </div>

        <div class="puzzle-footer">
            <div class="progress">
                NODES_STABILIZED: {{ stabilizedCount }} / {{ totalNodes }}
            </div>
            <div class="status">
                VOLTAGE: {{ voltageLevel }}V
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['complete', 'fail']);

const timeLeft = ref(25);
const stabilizedCount = ref(0);
const totalNodes = 4;
const voltageLevel = ref(12.0);
const gameActive = ref(true);

const probePoints = ref([
    { x: 100, y: 100, label: 'PWR_CPU1', active: false, success: false, fail: false },
    { x: 300, y: 100, label: 'PWR_CPU2', active: false, success: false, fail: false },
    { x: 200, y: 200, label: 'PWR_MEM_BUS', active: false, success: false, fail: false },
    { x: 100, y: 220, label: 'PSU_IN', active: false, success: false, fail: false },
    { x: 300, y: 220, label: 'IO_CONTROLLER', active: false, success: false, fail: false }
]);

const triggerRandomPoint = () => {
    if (!gameActive.value) return;
    
    // Find an inactive point
    const inactiveOnes = probePoints.value.filter(p => !p.active && !p.success);
    if (inactiveOnes.length > 0) {
        const p = inactiveOnes[Math.floor(Math.random() * inactiveOnes.length)];
        p.active = true;
        
        // Point stays active for a short time
        setTimeout(() => {
            if (p.active && !p.success) {
                p.active = false;
                voltageLevel.value = (parseFloat(voltageLevel.value) - 0.5).toFixed(1);
                if (voltageLevel.value < 9.0) fail('VOLTAGE_CRASH: Fatal Power Drop');
            }
        }, 1500);
    }

    setTimeout(triggerRandomPoint, 1000 + Math.random() * 1500);
};

const handleProbe = (index) => {
    if (!gameActive.value) return;
    const p = probePoints.value[index];
    
    if (p.active) {
        p.active = false;
        p.success = true;
        stabilizedCount.value++;
        voltageLevel.value = (parseFloat(voltageLevel.value) + 0.2).toFixed(1);
        
        if (stabilizedCount.value >= totalNodes) {
            win();
        }
    } else if (!p.success) {
        // Missed click
        p.fail = true;
        voltageLevel.value = (parseFloat(voltageLevel.value) - 1.0).toFixed(1);
        setTimeout(() => p.fail = false, 500);
        
        if (voltageLevel.value < 9.0) fail('SHORT_CIRCUIT: Manual Override Failed');
    }
};

let timer;
const startTimer = () => {
    timer = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            fail('HARDWARE_TIMEOUT');
        }
    }, 1000);
};

const win = () => {
    gameActive.value = false;
    clearInterval(timer);
    setTimeout(() => emit('complete'), 500);
};

const fail = (reason) => {
    gameActive.value = false;
    clearInterval(timer);
    emit('fail', reason);
};

onMounted(() => {
    startTimer();
    triggerRandomPoint();
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>

<style scoped>
.hardware-probe {
    background: rgba(10, 15, 25, 0.95);
    border: 1px solid var(--v3-accent);
    border-radius: 8px;
    padding: 24px;
    width: 100%;
    max-width: 500px;
    margin: 20px auto;
    font-family: 'JetBrains Mono', monospace;
    color: #fff;
    box-shadow: 0 0 40px rgba(0, 242, 255, 0.1);
}

.puzzle-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.version { font-size: 0.6rem; color: var(--v3-accent); opacity: 0.8; }
h3 { margin: 0; font-size: 1rem; letter-spacing: 2px; }

.timer { font-size: 0.9rem; color: var(--v3-accent); }
.timer.critical { color: var(--v3-danger); }

.puzzle-instructions {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    margin-bottom: 20px;
    line-height: 1.4;
}

.highlight { color: var(--v3-accent); font-weight: bold; }

.board-canvas {
    background: #000;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
    cursor: crosshair;
}

.motherboard-svg {
    width: 100%;
    height: auto;
}

.probe-point {
    cursor: pointer;
}

.outer-ring {
    fill: transparent;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 1;
    transition: all 0.3s;
}

.inner-core {
    fill: rgba(255, 255, 255, 0.2);
    transition: all 0.3s;
}

.point-label {
    font-size: 10px;
    fill: var(--v3-text-ghost);
    text-anchor: middle;
}

.probe-point.active .outer-ring {
    stroke: var(--v3-warning);
    stroke-width: 2;
    r: 15;
    animation: pulse-ring 1s infinite;
}

.probe-point.active .inner-core {
    fill: var(--v3-warning);
    r: 8;
}

.probe-point.success .inner-core {
    fill: var(--v3-success);
}

.probe-point.success .outer-ring {
    stroke: var(--v3-success);
    opacity: 0.5;
}

.probe-point.fail .inner-core {
    fill: var(--v3-danger);
    animation: shake 0.3s;
}

@keyframes pulse-ring {
    0% { r: 12; opacity: 1; }
    100% { r: 20; opacity: 0; }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-2px); }
    75% { transform: translateX(2px); }
}

.puzzle-footer {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
}
</style>
