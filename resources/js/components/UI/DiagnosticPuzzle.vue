<template>
    <div class="diagnostic-puzzle">
        <div class="puzzle-header">
            <div class="header-main">
                <span class="version">V_DIAG_4.2</span>
                <h3>KERNEL_SIGNAL_ANALYZER</h3>
            </div>
            <div class="timer" :class="{ critical: timeLeft < 10 }">
                T-MINUS: {{ timeLeft }}s
            </div>
        </div>

        <div class="puzzle-instructions">
            IDENTIFY AND ISOLATE THE <span class="highlight">CORRUPTED_HEX_STRINGS</span> TO RESTORE SYSTEM INTEGRITY.
        </div>

        <div class="hex-grid">
            <button 
                v-for="(cell, index) in grid" 
                :key="index"
                class="hex-cell"
                :class="{ 
                    faulty: cell.isFaulty && cell.revealed, 
                    cleared: cell.cleared,
                    wrong: cell.wrong 
                }"
                @click="handleClick(index)"
                :disabled="cell.cleared"
            >
                {{ cell.value }}
            </button>
        </div>

        <div class="puzzle-footer">
            <div class="status-monitor">
                <span class="label">SIG_NOISE:</span>
                <div class="progress-bar">
                    <div class="fill" :style="{ width: noiseLevel + '%' }"></div>
                </div>
            </div>
            <div class="faults-found">
                CORRUPTION_ISOLATED: {{ clearedFaults }} / {{ totalFaults }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['complete', 'fail']);

const grid = ref([]);
const timeLeft = ref(30);
const totalFaults = 3;
const clearedFaults = ref(0);
const noiseLevel = ref(0);
const gameActive = ref(true);

const hexChars = '0123456789ABCDEF';
const generateHex = () => {
    return '0x' + hexChars[Math.floor(Math.random() * 16)] + hexChars[Math.floor(Math.random() * 16)];
};

const initPuzzle = () => {
    const newGrid = [];
    for (let i = 0; i < 25; i++) {
        newGrid.push({
            value: generateHex(),
            isFaulty: false,
            revealed: false,
            cleared: false,
            wrong: false
        });
    }

    // Place faults
    let placed = 0;
    while (placed < totalFaults) {
        let idx = Math.floor(Math.random() * 25);
        if (!newGrid[idx].isFaulty) {
            newGrid[idx].isFaulty = true;
            // Make faulty values distinct (e.g. they contain 'F' or '0')
            newGrid[idx].value = '0x' + 'FF'; 
            placed++;
        }
    }

    // Randomize non-faulty values to NOT be '0xFF'
    newGrid.forEach(cell => {
        if (!cell.isFaulty && cell.value === '0xFF') {
            cell.value = '0xA1';
        }
    });

    grid.value = newGrid;
};

const handleClick = (index) => {
    if (!gameActive.value) return;

    const cell = grid.value[index];
    if (cell.isFaulty) {
        cell.cleared = true;
        cell.revealed = true;
        clearedFaults.value++;
        noiseLevel.value = Math.max(0, noiseLevel.value - 20);
        
        if (clearedFaults.value === totalFaults) {
            win();
        }
    } else {
        cell.wrong = true;
        noiseLevel.value = Math.min(100, noiseLevel.value + 25);
        setTimeout(() => cell.wrong = false, 500);
        
        if (noiseLevel.value >= 100) {
            fail('SYSTEM_OVERLOAD: Excessive Noise');
        }
    }
};

let timer;
const startTimer = () => {
    timer = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            fail('CONNECTION_TIMEOUT');
        }
    }, 1000);
};

const win = () => {
    gameActive.value = false;
    clearInterval(timer);
    emit('complete');
};

const fail = (reason) => {
    gameActive.value = false;
    clearInterval(timer);
    emit('fail', reason);
};

onMounted(() => {
    initPuzzle();
    startTimer();
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>

<style scoped>
.diagnostic-puzzle {
    background: rgba(10, 15, 25, 0.95);
    border: 1px solid var(--v3-accent);
    border-radius: 8px;
    padding: 24px;
    width: 100%;
    max-width: 450px;
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
    border-bottom: 1px solid rgba(0, 242, 255, 0.2);
    padding-bottom: 12px;
}

.version { font-size: 0.6rem; color: var(--v3-accent); opacity: 0.8; }
h3 { margin: 0; font-size: 1rem; letter-spacing: 2px; }

.timer {
    font-size: 0.9rem;
    color: var(--v3-accent);
}
.timer.critical { color: var(--v3-danger); animation: v3-pulse 1s infinite; }

.puzzle-instructions {
    font-size: 0.7rem;
    color: var(--v3-text-ghost);
    margin-bottom: 20px;
    line-height: 1.4;
}

.highlight { color: var(--v3-accent); font-weight: bold; }

.hex-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-bottom: 24px;
}

.hex-cell {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--v3-text-ghost);
    padding: 12px 0;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 2px;
}

.hex-cell:hover:not(:disabled) {
    background: rgba(0, 242, 255, 0.1);
    border-color: var(--v3-accent);
    color: #fff;
}

.hex-cell.faulty {
    background: rgba(46, 204, 113, 0.2);
    border-color: var(--v3-success);
    color: var(--v3-success);
}

.hex-cell.cleared {
    background: var(--v3-success);
    color: #000;
    border-color: var(--v3-success);
    opacity: 0.8;
}

.hex-cell.wrong {
    background: rgba(231, 76, 60, 0.3);
    border-color: var(--v3-danger);
    animation: shake 0.3s;
}

.puzzle-footer {
    display: flex;
    flex-direction: column;
    gap: 12px;
    font-size: 0.7rem;
}

.status-monitor {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar {
    flex: 1;
    height: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 2px;
    overflow: hidden;
}

.fill {
    height: 100%;
    background: linear-gradient(90deg, var(--v3-accent), var(--v3-danger));
    transition: width 0.3s ease;
}

.faults-found {
    text-align: right;
    color: var(--v3-accent);
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}

@keyframes v3-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>
