<template>
    <div class="diagnostic-logs">
        <div class="puzzle-header">
            <div class="header-main">
                <span class="version">V_KERN_LOG_SCNR</span>
                <h3>KERNEL_LOG_ANALYZER</h3>
            </div>
            <div class="timer" :class="{ critical: timeLeft < 10 }">
                T-MINUS: {{ timeLeft }}s
            </div>
        </div>

        <div class="puzzle-instructions">
            LOCATE THE <span class="highlight">CRITICAL_EXCEPTION_CODE</span> IN THE SYSTEM STREAM TO ISOLATE THE KERNEL FAULT.
        </div>

        <div class="log-stream" ref="streamRef">
            <div v-for="(line, i) in logLines" :key="i" 
                 class="log-line" 
                 :class="{ error: line.isError, found: line.found }"
                 @click="handleClick(i)"
            >
                <span class="ts">[{{ line.ts }}]</span>
                <span class="msg">{{ line.msg }}</span>
            </div>
        </div>

        <div class="puzzle-footer">
            <div class="target-info">
                TARGET_CODE: <span class="highlight">{{ targetCode }}</span>
            </div>
            <div class="status">
                STATUS: {{ gameActive ? 'SCANNING...' : 'COMPLETED' }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

const emit = defineEmits(['complete', 'fail']);

const logLines = ref([]);
const timeLeft = ref(20);
const targetCode = ref('0xEBAD');
const gameActive = ref(true);
const streamRef = ref(null);

const errorCodes = ['0xEBAD', '0xDEAD', '0xDEAF', '0xCAFE', '0xBABE', '0xFEED'];
const genericMsg = [
    'INFO: CPU Thread scheduler sync...',
    'DEBUG: Memory allocation page 0x',
    'INFO: Network interface eth0: heartbeat',
    'DEBUG: Buffer flush successful',
    'INFO: VFS Mount check: /dev/sda1',
    'DEBUG: Thermal sensor polling...'
];

const generateLine = (forceError = false) => {
    const isError = forceError || (Math.random() < 0.05);
    const code = isError ? errorCodes[Math.floor(Math.random() * errorCodes.length)] : '';
    const ts = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }) + '.' + Math.floor(Math.random() * 999);
    
    return {
        ts,
        msg: isError ? `CRITICAL_EXCEPTION: ${code} - Kernel stack smash detected` : genericMsg[Math.floor(Math.random() * genericMsg.length)] + (Math.random().toString(16).substring(2, 6)),
        isError,
        code,
        found: false
    };
};

const tick = () => {
    if (!gameActive.value) return;
    
    logLines.value.push(generateLine());
    if (logLines.value.length > 30) logLines.value.shift();
    
    nextTick(() => {
        if (streamRef.value) {
            streamRef.value.scrollTop = streamRef.value.scrollHeight;
        }
    });

    setTimeout(tick, 200 + Math.random() * 300);
};

const handleClick = (index) => {
    if (!gameActive.value) return;
    const line = logLines.value[index];
    
    if (line.isError && line.code === targetCode.value) {
        line.found = true;
        win();
    } else if (line.isError) {
        // Wrong error code
        fail('MISIDENTIFIED_ERROR: Wrong Exception Isolated');
    }
    // Normal lines do nothing or minor penalty
};

let timer;
const startTimer = () => {
    timer = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            fail('LOG_TIMEOUT');
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
    targetCode.value = errorCodes[Math.floor(Math.random() * errorCodes.length)];
    // Pre-fill some lines
    for(let i=0; i<15; i++) logLines.value.push(generateLine());
    startTimer();
    tick();
    
    // Occasionally force the target code after a few seconds
    setTimeout(() => {
        if (gameActive.value) logLines.value.push(generateLine(true));
    }, 5000 + Math.random() * 5000);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>

<style scoped>
.diagnostic-logs {
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

.log-stream {
    background: #000;
    height: 250px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
}

.log-line {
    font-size: 0.65rem;
    white-space: nowrap;
    padding: 4px 0;
    cursor: pointer;
    transition: background 0.2s;
}

.log-line:hover { background: rgba(255, 255, 255, 0.05); }

.log-line.error {
    color: var(--v3-warning);
    animation: flash 1s infinite;
}

.log-line.found {
    background: var(--v3-success);
    color: #000;
    animation: none;
}

.ts { opacity: 0.4; margin-right: 10px; }

.puzzle-footer {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
}

@keyframes flash {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
</style>
