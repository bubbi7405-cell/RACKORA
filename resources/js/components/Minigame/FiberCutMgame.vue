<template>
    <div class="minigame-overlay" @click.self="$emit('close')">
        <div class="mgame-card glass-panel shadow-danger">
            <div class="mgame-header">
                <h3>🚨 MANUAL REDIRECTION PROTOCOL</h3>
                <div class="timer" :class="{ 'critical': timeLeft < 5 }">
                    {{ timeLeft.toFixed(1) }}s
                </div>
            </div>

            <div class="mgame-body">
                <p class="hint">Connect the <b>SOURCE</b> coord to the <b>GATEWAY</b> by authorizing adjacent active coordinates. Avoid the dead zones!</p>
                
                <div class="grid-container">
                    <div v-for="(node, idx) in nodes" 
                         :key="idx" 
                         class="node"
                         :class="{ 
                             'dead': node.type === 'dead',
                             'source': node.type === 'source',
                             'goal': node.type === 'goal',
                             'active': node.selected,
                             'reachable': isReachable(idx)
                         }"
                         @click="selectNode(idx)"
                    >
                        <span v-if="node.type === 'source'">IN</span>
                        <span v-else-if="node.type === 'goal'">OUT</span>
                        <span v-else-if="node.type === 'dead'">ERR</span>
                    </div>
                </div>
            </div>

            <div class="mgame-footer">
                <button class="btn-cancel" @click="$emit('close')">ABORT</button>
                <div class="status-msg" :class="status">{{ message }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['close', 'complete']);

const timeLeft = ref(15);
const message = ref('Ready...');
const status = ref('idle');
const nodes = ref([]);
const gridWidth = 5;
const gridHeight = 5;

// Generate Grid
const initGrid = () => {
    const total = gridWidth * gridHeight;
    const items = [];
    for (let i = 0; i < total; i++) {
        items.push({ type: 'normal', selected: false });
    }

    // Fixed Source & Goal
    items[0].type = 'source';
    items[0].selected = true;
    items[total - 1].type = 'goal';

    // Random Dead Nodes (roughly 20% chance)
    for (let i = 1; i < total - 1; i++) {
        if (Math.random() < 0.25) {
            items[i].type = 'dead';
        }
    }

    nodes.value = items;
};

const isReachable = (idx) => {
    if (nodes.value[idx].type === 'dead') return false;
    if (nodes.value[idx].selected) return false;

    // Check neighbors
    const neighbors = getNeighbors(idx);
    return neighbors.some(nIdx => nodes.value[nIdx].selected);
};

const getNeighbors = (idx) => {
    const x = idx % gridWidth;
    const y = Math.floor(idx / gridWidth);
    const n = [];

    if (x > 0) n.push(idx - 1);
    if (x < gridWidth - 1) n.push(idx + 1);
    if (y > 0) n.push(idx - gridWidth);
    if (y < gridHeight - 1) n.push(idx + gridWidth);

    return n;
};

const selectNode = (idx) => {
    if (status.value !== 'idle') return;
    if (!isReachable(idx)) return;

    nodes.value[idx].selected = true;

    if (nodes.value[idx].type === 'goal') {
        win();
    }
};

const win = () => {
    status.value = 'success';
    message.value = 'REDIRECTION COMPLETE!';
    stopTimer();
    setTimeout(() => {
        emit('complete', { success: true });
    }, 1000);
};

const lose = () => {
    status.value = 'fail';
    message.value = 'LINK LOST - TIMEOUT';
    stopTimer();
    setTimeout(() => {
        emit('complete', { success: false });
    }, 1500);
};

let timer = null;
const startTimer = () => {
    timer = setInterval(() => {
        timeLeft.value -= 0.1;
        if (timeLeft.value <= 0) {
            timeLeft.value = 0;
            lose();
        }
    }, 100);
};

const stopTimer = () => {
    if (timer) clearInterval(timer);
};

onMounted(() => {
    initGrid();
    startTimer();
});

onUnmounted(() => {
    stopTimer();
});
</script>

<style scoped>
.minigame-overlay {
    position: fixed; inset: 0; z-index: 2000;
    background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(5px);
}

.mgame-card {
    width: 450px; background: #0a0a0f; border: 2px solid #333;
    padding: 30px; display: flex; flex-direction: column; gap: 20px;
}

.mgame-header {
    display: flex; justify-content: space-between; align-items: center;
}

.mgame-header h3 { margin: 0; font-size: 1rem; color: #f87171; letter-spacing: 2px; }

.timer { 
    font-family: var(--font-family-mono); font-size: 1.5rem; font-weight: 800; 
    color: var(--v3-accent); width: 80px; text-align: right;
}
.timer.critical { color: var(--v3-danger); animation: v3-pulse 0.5s infinite; }

.hint { font-size: 0.75rem; color: #888; line-height: 1.4; margin-bottom: 20px; }

.grid-container {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;
    background: #000; padding: 15px; border-radius: 4px; border: 1px solid #222;
}

.node {
    aspect-ratio: 1; border: 1px solid #222; display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem; font-weight: 900; color: #444; cursor: not-allowed; transition: all 0.2s;
}

.node.source { background: #3b82f6; color: #fff; cursor: default; border-color: #60a5fa; box-shadow: 0 0 10px rgba(59, 130, 246, 0.4); }
.node.goal { background: #10b981; color: #fff; border-color: #34d399; }
.node.dead { background: #1a1a1a; color: #ef4444; border-color: #450a0a; }

.node.active { background: #3b82f6 !important; color: #fff; border-color: #60a5fa; box-shadow: 0 0 15px rgba(59, 130, 246, 0.6); animation: node-pop 0.3s; }
.node.reachable { cursor: pointer; border-color: #555; background: #0f0f1a; }
.node.reachable:hover { background: #1e1e30; border-color: #3b82f6; }

@keyframes node-pop {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.mgame-footer {
    display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #222; padding-top: 20px;
}

.btn-cancel { 
    background: transparent; border: 1px solid #555; color: #888; 
    padding: 8px 16px; font-weight: 800; font-size: 0.7rem; cursor: pointer;
}
.btn-cancel:hover { border-color: #fff; color: #fff; }

.status-msg { font-size: 0.8rem; font-weight: 900; letter-spacing: 1px; }
.status-msg.success { color: #10b981; }
.status-msg.fail { color: #ef4444; }

@keyframes v3-pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>
