<template>
    <div class="peering-overlay" v-if="partner">
        <div class="overlay-backdrop" @click="$emit('close')"></div>
        <div class="overlay-content glass-panel animate-slide-up">
            <header class="overlay-header">
                <div class="header-main">
                    <div class="npc-logo" :style="{ background: partner.color }">{{ partner.name[0] }}</div>
                    <div class="npc-info">
                        <h2>{{ partner.name }}</h2>
                        <p>Backbone Peering Negotiation</p>
                    </div>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="negotiation-layout">
                <div class="partner-meta">
                    <div class="meta-card">
                        <label>NPC ARQUETYPE</label>
                        <div class="val">{{ partner.archetype?.replace('_', ' ').toUpperCase() }}</div>
                    </div>
                    <div class="meta-card">
                        <label>NETWORK STABILITY</label>
                        <div class="val">{{ partner.baseLatency }}ms Avg</div>
                    </div>
                    <div class="meta-card">
                        <label>TRUST THRESHOLD</label>
                        <div class="val">{{ partner.minPeeringScore }} PTS</div>
                    </div>
                </div>

                <div class="simulation-vis">
                    <div class="vis-header">
                        <label>BGP PATH DISCOVERY</label>
                        <span class="status-blink" v-if="processing">CONVERGING...</span>
                    </div>
                    
                    <div class="path-diagram">
                        <div class="node self">ASN {{ gameStore.network?.infrastructure?.asn || '65001' }}</div>
                        <div class="path-line">
                            <div class="hop-dots">
                                <span v-for="n in 3" :key="n" class="dot"></span>
                            </div>
                            <div class="optimization-text" v-if="probability > 0.5">-{{ Math.round(3 * (probability)) }} Hops Optimization</div>
                        </div>
                        <div class="node partner" :style="{ borderColor: partner.color }">{{ partner.name }}</div>
                    </div>
                    
                    <div class="impact-summary">
                        <div class="impact-item">
                            <label>LATENCY IMPACT</label>
                            <span class="val text-success">-{{ Math.round((1 - (1.0 - (100 - partner.baseLatency) / 200)) * 100) }}%</span>
                        </div>
                        <div class="impact-item">
                            <label>ROUTE DIVERSITY</label>
                            <span class="val">+{{ (bid.capacity / 100).toFixed(1) }}X</span>
                        </div>
                    </div>
                </div>

                <div class="proposal-panel">
                    <div class="input-group">
                        <div class="label-row">
                            <label>CAPACITY COMMITMENT</label>
                            <span class="value">{{ bid.capacity }} Gbps</span>
                        </div>
                        <input type="range" v-model="bid.capacity" min="10" max="1000" step="10">
                    </div>

                    <div class="input-group">
                        <div class="label-row">
                            <label>MONTHLY REVENUE SHARE</label>
                            <span class="value text-success">${{ Number(bid.cost).toLocaleString() }}</span>
                        </div>
                        <input type="number" v-model="bid.cost" class="cost-input">
                        <div class="slider-hint">NPC Expected: approx. ${{ expectedCost.toLocaleString() }}</div>
                    </div>

                    <div class="acceptance-meter">
                        <div class="meter-label">Likelihood of Acceptance</div>
                        <div class="meter-track">
                            <div class="meter-fill" :style="{ width: (probability * 100) + '%', background: getProbColor }"></div>
                        </div>
                        <div class="probability-val" :style="{ color: getProbColor }">{{ (probability * 100).toFixed(0) }}%</div>
                    </div>

                    <div class="actions">
                        <button class="btn-cancel" @click="$emit('close')">ABORT</button>
                        <button 
                            class="btn-submit" 
                            :disabled="processing || partner.playerScore < partner.minPeeringScore" 
                            @click="submitProposal"
                        >
                            <span v-if="!processing">TRANSMIT PROPOSAL</span>
                            <span v-else class="spinner"></span>
                        </button>
                    </div>
                    
                    <div class="error-msg" v-if="partner.playerScore < partner.minPeeringScore">
                        Error: Peering Score too low. You need at least {{ partner.minPeeringScore }} points.
                    </div>
                </div>
            </div>

            <div v-if="response" class="npc-response animate-fade-in" :class="{ 'rejected': !response.success }">
                <div class="msg">{{ response.message }}</div>
                <button v-if="!response.success" @click="response = null" class="btn-retry">ADJUST OFFER</button>
                <button v-else @click="$emit('close')" class="btn-done">CLOSE</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';

const gameStore = useGameStore();

const props = defineProps({
    partner: { type: Object, required: true }
});

const emit = defineEmits(['close', 'success']);

const bid = ref({
    capacity: props.partner.baseCapacity || 100,
    cost: (props.partner.baseCapacity || 100) * 10
});

const processing = ref(false);
const response = ref(null);

const expectedCost = computed(() => {
    // Basic frontend-side estimation to help player
    return (bid.value.capacity / 10) * 100; 
});

const probability = computed(() => {
    if (!props.partner) return 0;
    const pScore = props.partner.playerScore || 0;
    const ratio = bid.value.cost / Math.max(1, expectedCost.value);
    let p = (ratio) * (pScore / 100);
    return Math.min(1.0, Math.max(0.01, p));
});

const getProbColor = computed(() => {
    const p = probability.value;
    if (p > 0.7) return '#00ff9d';
    if (p > 0.4) return '#ffcc00';
    return '#ff4d4d';
});

const submitProposal = async () => {
    processing.value = true;
    try {
        const res = await api.post('/network/peering/propose', {
            competitor_id: props.partner.id,
            capacity_gbps: bid.value.capacity,
            monthly_cost: bid.value.cost
        });

        if (res.success) {
            response.value = { success: true, message: res.message };
            emit('success', res.data);
        } else {
            response.value = { success: false, message: res.message || 'Proposal Rejected.' };
        }
    } catch (e) {
        console.error(e);
        response.value = { success: false, message: e.response?.data?.message || 'Network Error during transmission.' };
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.peering-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 2500;
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-backdrop {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
}

.overlay-content {
    position: relative;
    width: 650px;
    background: #0d1117;
    border: 1px solid rgba(0, 255, 157, 0.2);
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 0 40px rgba(0, 255, 157, 0.1);
}

.overlay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 20px;
}

.header-main {
    display: flex;
    gap: 20px;
    align-items: center;
}

.npc-logo {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
    color: #fff;
}

.npc-info h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #fff;
}

.npc-info p {
    margin: 0;
    font-size: 0.8rem;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.close-btn {
    background: none;
    border: none;
    color: #8b949e;
    font-size: 2rem;
    cursor: pointer;
}

.negotiation-layout {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.partner-meta {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.meta-card {
    background: rgba(255,255,255,0.05);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.05);
}

.meta-card label {
    display: block;
    font-size: 0.6rem;
    color: #8b949e;
    margin-bottom: 5px;
}

.meta-card .val {
    font-weight: 700;
    font-family: var(--font-family-mono);
    color: #f0f6fc;
}

.input-group {
    margin-bottom: 25px;
}

.label-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.label-row label {
    font-size: 0.8rem;
    color: #8b949e;
    font-weight: 600;
}

.label-row .value {
    font-family: var(--font-family-mono);
    font-weight: 700;
    font-size: 1.1rem;
}

input[type="range"] {
    width: 100%;
    accent-color: #00ff9d;
}

.cost-input {
    width: 100%;
    background: #161b22;
    border: 1px solid #30363d;
    color: #fff;
    padding: 12px;
    font-size: 1.5rem;
    font-weight: 700;
    font-family: var(--font-family-mono);
    border-radius: 6px;
}

.slider-hint {
    font-size: 0.7rem;
    color: #8b949e;
    margin-top: 5px;
}

.acceptance-meter {
    background: rgba(0,0,0,0.3);
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.meter-track {
    height: 8px;
    background: #161b22;
    border-radius: 4px;
    margin: 10px 0;
    overflow: hidden;
}

.meter-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.probability-val {
    font-size: 2rem;
    font-weight: 800;
    font-family: var(--font-family-mono);
}

.actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.btn-cancel {
    flex: 1;
    padding: 15px;
    background: transparent;
    border: 1px solid #30363d;
    color: #8b949e;
    font-weight: 700;
    border-radius: 6px;
    cursor: pointer;
}

.btn-submit {
    flex: 2;
    padding: 15px;
    background: #00ff9d;
    color: #0d1117;
    border: none;
    font-weight: 800;
    font-size: 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-submit:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.npc-response {
    position: absolute;
    inset: 0;
    background: rgba(13, 17, 23, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    text-align: center;
    border-radius: 12px;
    z-index: 10;
}

.npc-response .msg {
    font-size: 1.2rem;
    margin-bottom: 30px;
    line-height: 1.5;
}

.rejected .msg { color: #ff4d4d; }

.btn-retry, .btn-done {
    padding: 12px 30px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
}

.btn-retry { background: #30363d; color: #fff; border: 1px solid #444; }
.btn-done { background: #00ff9d; color: #0d1117; border: none; }

.error-msg {
    color: #ff4d4d;
    font-size: 0.8rem;
    text-align: center;
    margin-top: 10px;
    font-weight: 600;
}

.spinner {
    display: block;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(0,0,0,0.1);
    border-top-color: #0d1117;
    border-radius: 50%;
    animation: spin 1s infinite linear;
    margin: 0 auto;
}

@keyframes spin { to { transform: rotate(360deg); } }

.animate-slide-up {
    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.simulation-vis {
    background: rgba(0,0,0,0.3); padding: 20px; border-radius: 8px; border: 1px dashed rgba(0, 255, 157, 0.2);
}
.vis-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.vis-header label { font-size: 0.6rem; color: #8b949e; letter-spacing: 1px; }

.path-diagram {
    display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 20px;
}
.node {
    background: #161b22; border: 1px solid #30363d; padding: 6px 12px; border-radius: 4px;
    font-size: 0.75rem; font-family: var(--font-family-mono); font-weight: 700;
}
.node.self { border-color: #00ff9d; color: #00ff9d; box-shadow: 0 0 10px rgba(0, 255, 157, 0.1); }
.node.partner { border-width: 2px; }

.path-line { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; position: relative; }
.path-line::before { content: ''; position: absolute; top: 18px; left: 0; right: 0; height: 1px; background: rgba(255,255,255,0.1); }

.hop-dots { display: flex; gap: 15px; z-index: 1; }
.dot { width: 6px; height: 6px; background: #30363d; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1); }

.optimization-text { font-size: 0.6rem; color: #00ff9d; font-weight: 800; text-transform: uppercase; }

.impact-summary { display: flex; justify-content: space-around; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; }
.impact-item { text-align: center; }
.impact-item label { display: block; font-size: 0.55rem; color: #8b949e; margin-bottom: 2px; }
.impact-item .val { font-weight: 800; font-family: var(--font-family-mono); font-size: 0.9rem; }

.status-blink { font-size: 0.6rem; color: #ffcc00; font-weight: 800; animation: blink 1s infinite; }
@keyframes blink { 0% { opacity: 0.2; } 50% { opacity: 1; } 100% { opacity: 0.2; } }
</style>
