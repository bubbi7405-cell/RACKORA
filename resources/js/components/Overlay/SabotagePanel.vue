<template>
    <div class="sabotage-panel">
        <div class="panel-layout">
            <!-- TARGET SELECTION -->
            <div class="target-list">
                <h3>Select Target</h3>
                <div 
                    v-for="comp in competitors" 
                    :key="comp.id" 
                    class="target-card"
                    :class="{ active: selectedTarget?.id === comp.id }"
                    @click="selectTarget(comp)"
                >
                    <div class="target-icon" :style="{ background: comp.color }"></div>
                    <div class="target-info">
                        <div class="target-name">{{ comp.name }}</div>
                        <div class="target-share">{{ comp.marketShare }}% Share</div>
                    </div>
                </div>
            </div>

            <!-- OPERATION CENTER -->
            <div class="operation-center">
                <div v-if="!selectedTarget" class="placeholder">
                    Select a competitor to target.
                </div>
                <div v-else>
                    <div class="target-header">
                        <h2 :style="{ color: selectedTarget.color }">
                            TARGET: {{ selectedTarget.name.toUpperCase() }}
                        </h2>
                        <div class="intel-summary">
                            <span>Aggr: {{ selectedTarget.aggression }}</span>
                            <span>Rep: {{ selectedTarget.reputation }}</span>
                        </div>
                    </div>

                    <!-- OPS GRID -->
                    <div class="ops-grid">
                        <div 
                            v-for="(op, key) in sabotageTypes" 
                            :key="key"
                            class="op-card"
                            :class="{ selected: selectedOp === key }"
                            @click="selectOp(key)"
                        >
                            <div class="op-header">
                                <span class="op-name">{{ op.name }}</span>
                                <span class="op-cost text-red">${{ op.cost.toLocaleString() }}</span>
                            </div>
                            <div class="op-desc">{{ op.description }}</div>
                            <div class="op-stats">
                                <div class="stat-row">
                                    <span>Success:</span>
                                    <span class="val">{{ op.base_chance }}%</span>
                                </div>
                                <div class="stat-row">
                                    <span>Risk:</span>
                                    <span class="val text-orange">{{ op.detection_chance }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EXECUTE -->
                    <div class="action-area" v-if="selectedOp">
                        <button 
                            class="execute-btn" 
                            :disabled="isExecuting || !canAfford"
                            @click="executeSabotage"
                        >
                            <span v-if="isExecuting">INITIATING...</span>
                            <span v-else>EXECUTE OPERATION (${{ selectedOpCost.toLocaleString() }})</span>
                        </button>
                        <div class="funds-warning" v-if="!canAfford">Insufficient Crypto Funds</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HISTORY LOG -->
        <div class="history-log">
            <h3>Recent Operations</h3>
            <div class="log-list">
                <div v-for="log in history" :key="log.id" class="log-item" :class="log.status">
                    <span class="time">{{ formatDate(log.created_at) }}</span>
                    <span class="type">{{ formatType(log.type) }}</span>
                    <span class="status-badge" :class="log.status">{{ log.status }}</span>
                    <span class="result-text">{{ getResultText(log) }}</span>
                </div>
                <div v-if="history.length === 0" class="empty-log">No recent operations.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';

const props = defineProps({
    competitors: { type: Array, required: true }
});

const gameStore = useGameStore();
const toast = useToastStore();

const sabotageTypes = ref({});
const history = ref([]);
const selectedTarget = ref(null);
const selectedOp = ref(null);
const isExecuting = ref(false);

const selectedOpConfig = computed(() => {
    if (!selectedOp.value) return null;
    return sabotageTypes.value[selectedOp.value];
});

const selectedOpCost = computed(() => selectedOpConfig.value?.cost || 0);

const canAfford = computed(() => {
    return gameStore.player.economy.balance >= selectedOpCost.value;
});

async function loadData() {
    try {
        const res = await api.get('/sabotage');
        if (res.success) {
            sabotageTypes.value = res.types;
            history.value = res.history;
        }
    } catch (e) {
        console.error('Failed to load sabotage data', e);
    }
}

function selectTarget(comp) {
    selectedTarget.value = comp;
    selectedOp.value = null;
}

function selectOp(key) {
    selectedOp.value = key;
}

async function executeSabotage() {
    if (!selectedTarget.value || !selectedOp.value) return;
    
    isExecuting.value = true;
    try {
        const res = await api.post('/sabotage/attempt', {
            target_id: selectedTarget.value.id,
            target_type: 'competitor',
            sabotage_type: selectedOp.value
        });

        if (res.success) {
            if (res.data.success) {
                toast.success('Operation Successful!');
            } else {
                toast.warning('Operation Failed.');
            }
            if (res.data.detected) {
                toast.error('WARNING: You were detected!');
            }
            
            // Refresh data
            loadData();
            gameStore.loadGameState(); // Update balance
        }
    } catch (e) {
        toast.error(e.response?.data?.error || 'Operation Error');
    } finally {
        isExecuting.value = false;
    }
}

function formatDate(date) {
    if (!date) return '---';
    const d = new Date(date);
    return isNaN(d.getTime()) ? '---' : d.toLocaleTimeString('de-DE');
}

function formatType(type) {
    return (sabotageTypes.value[type]?.name || type).toUpperCase();
}

function getResultText(log) {
    if (log.result && log.result.message) return log.result.message;
    if (log.result && log.result.damage) return log.result.damage;
    return '';
}

onMounted(loadData);
</script>

<style scoped>
.sabotage-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    color: #fff;
    gap: 20px;
}

.panel-layout {
    display: flex;
    gap: 20px;
    flex: 1;
    min-height: 0; /* scroll fix */
}

/* TARGET LIST */
.target-list {
    width: 250px;
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

.target-list h3 {
    font-size: 0.8rem;
    color: #888;
    margin-bottom: 5px;
}

.target-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.target-card:hover { background: rgba(255,255,255,0.1); }
.target-card.active { border-color: var(--color-primary); background: rgba(63, 185, 80, 0.1); }

.target-icon {
    width: 10px; height: 10px; border-radius: 50%;
}

.target-name { font-weight: bold; font-size: 0.9rem; }
.target-share { font-size: 0.75rem; color: #aaa; }

/* OPERATION CENTER */
.operation-center {
    flex: 1;
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
}

.placeholder {
    color: #555; font-style: italic; text-align: center; margin-top: 50px;
}

.target-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 10px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.target-header h2 { margin: 0; font-size: 1.2rem; }
.intel-summary { font-family: monospace; color: #aaa; font-size: 0.8rem; }
.intel-summary span { margin-left: 10px; }

.ops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.op-card {
    background: rgba(30,30,35,0.8);
    border: 1px solid #444;
    padding: 15px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.op-card:hover { border-color: #777; }
.op-card.selected { border-color: #ff3333; box-shadow: 0 0 10px rgba(255,51,51,0.2); }

.op-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
.op-name { font-weight: bold; font-size: 0.9rem; color: #eee; }
.op-cost { font-family: monospace; font-size: 0.9rem; }
.text-red { color: #ff6666; }

.op-desc { font-size: 0.75rem; color: #aaa; margin-bottom: 10px; line-height: 1.3; }

.op-stats { font-size: 0.75rem; color: #888; }
.stat-row { display: flex; justify-content: space-between; }
.text-orange { color: #ffaa00; }

.execute-btn {
    width: 100%;
    padding: 15px;
    background: #ff3333;
    color: white;
    font-weight: bold;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    letter-spacing: 1px;
}
.execute-btn:disabled { background: #552222; cursor: not-allowed; color: #888; }
.funds-warning { color: #ff6666; text-align: center; margin-top: 5px; font-size: 0.8rem; }

/* HISTORY LOG */
.history-log {
    height: 150px;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 10px;
    overflow-y: auto;
}
.history-log h3 { font-size: 0.8rem; color: #888; margin-top: 0; margin-bottom: 10px; }

.log-list { display: flex; flex-direction: column; gap: 5px; }

.log-item {
    display: flex;
    gap: 15px;
    font-family: monospace;
    font-size: 0.8rem;
    padding: 5px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.log-item.success { color: #88ff88; }
.log-item.failed { color: #aaaaaa; }
.log-item.detected { color: #ffaa00; } /* Usually combined with success/fail */

.status-badge { padding: 2px 5px; border-radius: 3px; font-size: 0.7rem; text-transform: uppercase; }
.status-badge.success { background: rgba(0,255,0,0.2); }
.status-badge.failed { background: rgba(255,255,255,0.1); }
</style>
