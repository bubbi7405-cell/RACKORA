<template>
    <div class="negotiation-overlay" v-if="order">
        <div class="overlay-backdrop" @click="$emit('close')"></div>
        <div class="overlay-content">
            <header class="overlay-header">
                <div class="header-main">
                    <h2>Contract Negotiation</h2>
                    <span class="whale-badge" v-if="order.sla.tier === 'whale'">WHALE CLIENT</span>
                    <span class="enterprise-badge" v-else-if="order.sla.tier === 'enterprise'">ENTERPRISE</span>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="negotiation-layout">
                <!-- Left: Client Profile & Requirements -->
                <div class="sidebar">
                    <div class="client-card">
                        <div class="avatar">🏢</div>
                        <h3>{{ order.customerName }}</h3>
                        <div class="tier">{{ order.productType }} / {{ order.sla.tier }}</div>
                        <div v-if="order.greenPreference" class="eco-tag">
                            <span class="eco-icon">🌱</span>
                            <span class="eco-text">Sustainability Focus</span>
                        </div>
                    </div>

                    <div class="requirements-box">
                        <label>Service Requirements</label>
                        <ul>
                            <li :class="{ 'warning': bid.sla !== order.sla.tier }">
                                <span>CPU:</span>
                                <strong>{{ projectedRequirements.cpu }} Cores</strong>
                                <small v-if="bid.sla !== order.sla.tier">({{ projectedRequirements.cpu >
                                    order.requirements.cpu ? '+' : '' }}{{ projectedRequirements.cpu -
                                    order.requirements.cpu }})</small>
                            </li>
                            <li :class="{ 'warning': bid.sla !== order.sla.tier }">
                                <span>RAM:</span>
                                <strong>{{ projectedRequirements.ram }} GB</strong>
                                <small v-if="bid.sla !== order.sla.tier">({{ projectedRequirements.ram >
                                    order.requirements.ram ? '+' : '' }}{{ projectedRequirements.ram -
                                    order.requirements.ram }})</small>
                            </li>
                            <li :class="{ 'warning': bid.sla !== order.sla.tier }">
                                <span>Disk:</span>
                                <strong>{{ projectedRequirements.storage }} GB</strong>
                                <small v-if="bid.sla !== order.sla.tier">({{ projectedRequirements.storage >
                                    order.requirements.storage ? '+' : '' }}{{ projectedRequirements.storage -
                                    order.requirements.storage }})</small>
                            </li>
                            <li :class="{ 'warning': bid.sla !== order.sla.tier }">
                                <span>Net:</span>
                                <strong>{{ projectedRequirements.bandwidth }} Mbps</strong>
                                <small v-if="bid.sla !== order.sla.tier">({{ (projectedRequirements.bandwidth >
                                    order.requirements.bandwidth ? '+' : '') }}{{ projectedRequirements.bandwidth -
                                    order.requirements.bandwidth }})</small>
                            </li>
                        </ul>
                        <div class="scaling-note" v-if="bid.sla !== order.sla.tier">
                            ⚠️ SLA changes impact resource requirements.
                        </div>
                    </div>

                    <div class="negotiation-history">
                        <label>Conversation Status</label>
                        <div class="patience-meter">
                            <div class="patience-label">Customer Patience</div>
                            <div class="patience-bar">
                                <div class="fill" :style="{ width: order.patience.progress + '%' }"></div>
                            </div>
                        </div>
                        <div class="attempts">
                            Negotiation Rounds: {{ order.negotiation.attempts }} / 3
                        </div>
                    </div>
                </div>

                <!-- Right: The Bidding Interface -->
                <div class="bidding-panel">
                    <div class="panel-section">
                        <div class="section-label">Proposed Monthly Revenue</div>
                        <div class="price-input-row">
                            <span class="currency">$</span>
                            <input type="number" v-model="bid.price" @input="updatePreview" step="10">
                            <span class="per-mo">/mo</span>
                        </div>
                        <div class="price-slider">
                            <input type="range" v-model="bid.price" :min="order.negotiation.basePriceRequested * 0.5"
                                :max="order.negotiation.basePriceRequested * 3.0" step="1" @input="updatePreview">
                        </div>
                        <div class="price-hint">
                            Client Requested: ${{ order.negotiation.basePriceRequested.toFixed(2) }}
                        </div>
                    </div>

                    <div class="panel-section">
                        <div class="section-label">Service Level Agreement (SLA)</div>
                        <div class="sla-selector">
                            <button v-for="tier in ['standard', 'premium', 'enterprise', 'whale']" :key="tier"
                                :class="{ active: bid.sla === tier }" @click="bid.sla = tier; updatePreview()">
                                {{ tier.toUpperCase() }}
                            </button>
                        </div>
                        <div class="sla-info">
                            Guarantee: {{ getSlaValue(bid.sla) }}% up-time
                        </div>
                    </div>

                    <div class="panel-section">
                        <div class="section-label">Contract Commitment</div>
                        <div class="length-selector">
                            <input type="range" v-model="bid.months" min="1" max="60" step="1" @input="updatePreview">
                            <div class="length-value">{{ bid.months }} Months</div>
                        </div>
                    </div>

                    <div class="probability-gauge" :class="{ calculating: isCalculating }">
                        <div class="gauge-label">Likelihood of Acceptance</div>
                        <div class="gauge-container">
                            <div class="gauge-bar"
                                :style="{ width: (probability * 100) + '%', backgroundColor: getProbColor }"></div>
                        </div>
                        <div class="gauge-percentage" :style="{ color: getProbColor }">
                            <span v-if="!isCalculating">{{ (probability * 100).toFixed(0) }}%</span>
                            <span v-else class="calc-dots">...</span>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn-cancel" @click="$emit('close')">Cancel</button>
                        <button class="btn-submit" :disabled="processing" @click="submitBid">
                            <span v-if="!processing">Submit Proposal</span>
                            <span v-else class="spinner"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import debounce from 'lodash/debounce';

const props = defineProps({
    order: { type: Object, required: true }
});

const emit = defineEmits(['close', 'negotiated']);
const gameStore = useGameStore();

const bid = ref({
    price: props.order.negotiation.basePriceRequested,
    sla: props.order.sla.tier,
    months: props.order.contractMonths
});

const probability = ref(0.8);
const processing = ref(false);
const isCalculating = ref(false);

const debouncedFetch = debounce(async () => {
    try {
        const prob = await gameStore.getBidPreview(props.order.id, bid.value);
        if (prob !== undefined) probability.value = prob;
    } finally {
        isCalculating.value = false;
    }
}, 500);

const updatePreview = () => {
    isCalculating.value = true;
    debouncedFetch();
};

onMounted(() => {
    updatePreview();
});

const getSlaValue = (tier) => {
    const map = { standard: 99.0, premium: 99.9, enterprise: 99.99, whale: 99.999 };
    return map[tier];
};

const getProbColor = computed(() => {
    const p = probability.value;
    if (p > 0.7) return '#2ea043';
    if (p > 0.4) return '#e3b341';
    return '#f85149';
});

const projectedRequirements = computed(() => {
    const base = props.order.requirements._base;
    if (!base) return props.order.requirements;

    const reqMod = props.order.requirements._reqMod || 1.0;
    const slaTier = bid.value.sla;

    const slaReqMod = {
        standard: 1.0,
        premium: 1.5,
        enterprise: 5.0,
        whale: 15.0
    }[slaTier] || 1.0;

    return {
        cpu: Math.ceil(base.cpu * reqMod * slaReqMod),
        ram: Math.ceil(base.ram * reqMod * slaReqMod),
        storage: Math.ceil(base.storage * reqMod * slaReqMod),
        bandwidth: Math.ceil(base.bandwidth * reqMod * slaReqMod)
    };
});

const submitBid = async () => {
    processing.value = true;
    try {
        const result = await gameStore.submitBid(props.order.id, bid.value);
        if (result && result.success) {
            emit('negotiated', result.order);
            emit('close');
        } else if (result && result.walked_away) {
            emit('close');
        }
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.negotiation-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(8px);
}

.overlay-content {
    position: relative;
    width: 900px;
    max-width: 95%;
    max-height: 90vh;
    background: var(--color-bg-dark);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.overlay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: var(--color-bg-medium);
    border-bottom: 1px solid var(--color-border);
}

.header-main {
    display: flex;
    align-items: center;
    gap: 15px;
}

.whale-badge {
    background: linear-gradient(45deg, var(--color-warning), #fbbf24);
    color: black;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: 1px;
}

.enterprise-badge {
    background: linear-gradient(45deg, #3b82f6, #60a5fa);
    color: white;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: 1px;
}

.negotiation-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    flex: 1;
    overflow: hidden;
}

.sidebar {
    background: var(--color-bg-dark);
    padding: 30px;
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.client-card {
    text-align: center;
}

.avatar {
    font-size: 3rem;
    margin-bottom: 15px;
}

.client-card h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--color-text-primary);
}

.tier {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    margin-top: 5px;
}

.eco-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 20px;
    color: #10b981;
}

.eco-icon {
    font-size: 0.8rem;
}

.eco-text {
    font-size: 0.75rem;
    font-weight: 700;
}

.requirements-box label,
.negotiation-history label {
    display: block;
    font-size: 0.75rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    margin-bottom: 15px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 5px;
}

.requirements-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.requirements-box li {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.requirements-box li span {
    color: var(--color-text-muted);
}

.requirements-box li strong {
    color: var(--color-text-primary);
    font-family: var(--font-family-mono);
}

.requirements-box li small {
    font-size: 0.7rem;
    color: var(--color-warning);
    margin-left: 5px;
}

.requirements-box li.warning strong {
    color: var(--color-warning);
}

.scaling-note {
    font-size: 0.7rem;
    color: var(--color-warning);
    margin-top: 15px;
    padding: 8px;
    background: rgba(227, 179, 65, 0.1);
    border: 1px solid rgba(227, 179, 65, 0.2);
    border-radius: 4px;
}

.patience-bar {
    height: 8px;
    background: var(--color-bg-deep);
    border-radius: 4px;
    margin-top: 8px;
    overflow: hidden;
}

.fill {
    height: 100%;
    background: var(--color-primary);
    transition: width 0.5s;
}

.attempts {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-top: 15px;
}

.bidding-panel {
    padding: 40px;
    background: var(--color-bg-dark);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.section-label {
    font-size: 1rem;
    color: var(--color-text-primary);
    margin-bottom: 20px;
    font-weight: 600;
}

.price-input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.currency {
    font-size: 2rem;
    color: var(--color-success);
}

.per-mo {
    color: var(--color-text-muted);
}

.price-input-row input {
    background: var(--color-bg-medium);
    border: 1px solid var(--color-border);
    color: var(--color-text-primary);
    font-size: 2rem;
    width: 200px;
    padding: 5px 15px;
    border-radius: 8px;
    font-weight: 700;
    font-family: var(--font-family-mono);
}

.price-slider input,
.length-selector input {
    width: 100%;
    accent-color: var(--color-success);
}

.price-hint {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-top: 10px;
}

.sla-selector {
    display: flex;
    gap: 10px;
}

.sla-selector button {
    flex: 1;
    padding: 12px;
    background: var(--color-bg-medium);
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.sla-selector button.active {
    background: var(--color-success);
    color: var(--color-text-inverse);
    border-color: var(--color-success);
    box-shadow: 0 0 15px var(--color-success-dim);
}

.sla-info {
    font-size: 0.85rem;
    color: var(--color-text-muted);
    margin-top: 10px;
}

.length-value {
    text-align: right;
    font-size: 1.2rem;
    color: var(--color-primary);
    font-weight: 700;
    margin-top: 10px;
}

.probability-gauge {
    background: var(--color-bg-medium);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--color-border);
    text-align: center;
}

.gauge-label {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    margin-bottom: 15px;
}

.gauge-container {
    height: 12px;
    background: var(--color-bg-dark);
    border-radius: 6px;
    margin: 10px 0;
    overflow: hidden;
}

.gauge-bar {
    height: 100%;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.gauge-percentage {
    font-size: 2.5rem;
    font-weight: 800;
    font-family: var(--font-family-mono);
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 20px;
    margin-top: 20px;
}

.btn-cancel {
    padding: 12px 24px;
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-submit {
    padding: 12px 40px;
    background: var(--color-success);
    color: var(--color-text-inverse);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.2s;
}

.btn-submit:hover:not(:disabled) {
    background: var(--color-success);
    filter: brightness(1.2);
    transform: scale(1.05);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.spinner {
    display: block;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s infinite linear;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.probability-gauge.calculating {
    opacity: 0.7;
}

.calc-dots {
    animation: pulse 1s infinite;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 0.3;
    }

    50% {
        opacity: 1;
    }
}
</style>
