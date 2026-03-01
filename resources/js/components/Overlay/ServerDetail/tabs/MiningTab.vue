<template>
    <div class="proc-v3 scroll-y">
        <div class="proc-header">
            <div class="proc-title">
                <h3>CRYPTO_MINING_PROTOCOL</h3>
                <p>Nutze ungenutzte Serverkapazitäten zum Schürfen von Kryptowährungen.</p>
            </div>
        </div>

        <div class="summary-group-v3 mb-4">
            <label class="section-label-industrial">MINING_STATUS</label>
            <div class="mining-status-card" :class="{ 'active': server.mining?.isMining }">
                <div class="status-icon">{{ server.mining?.isMining ? '⛏️' : '⏸️' }}</div>
                <div class="status-details">
                    <div class="status-title">{{ server.mining?.isMining ? 'MINING AKTIV' : 'MINING INAKTIV' }}</div>
                    <div class="status-desc" v-if="server.mining?.isMining">
                        Der Server läuft unter Volllast. Der Stromverbrauch ist maxmiert (+50%) und die Hitzeentwicklung
                        ist extrem gesteigert (+80%).
                    </div>
                </div>
                <div class="status-toggle">
                    <button class="btn-toggle" :class="{ 'on': server.mining?.isMining }"
                        @click="toggleMining" :disabled="processing">
                        {{ server.mining?.isMining ? 'STOPPEN' : 'STARTEN' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="summary-grid-v3">
            <div class="summary-group-v3">
                <label class="section-label-industrial">MINING_STATS</label>
                <div class="spec-grid-v3">
                    <div class="v3-spec">
                        <span class="l">TOTAL MINED</span>
                        <strong>${{ (server.mining?.totalMined || 0).toFixed(2) }}</strong>
                    </div>
                    <div class="v3-spec">
                        <span class="l">EST. HASHRATE</span>
                        <strong>{{ (server.specs?.effectiveCpuCores || 0) * 1.5 }} MH/s</strong>
                    </div>
                    <div class="v3-spec">
                        <span class="l">EST. INCOME</span>
                        <strong class="text-success">+${{ ((server.specs?.effectiveCpuCores || 0) * 0.20 *
                            60).toFixed(2) }} / hr</strong>
                    </div>
                </div>
            </div>

            <div class="summary-group-v3 warning-group">
                <label class="section-label-industrial">RISIKEN & NEBENWIRKUNGEN</label>
                <ul class="risk-list">
                    <li>⚠️ Erhöht den <strong>STROMVERBRAUCH</strong> um 50%.</li>
                    <li>🔥 Erhöht die <strong>HITZEENTWICKLUNG</strong> um 80%.</li>
                    <li>🚫 Kann zu thermischer Notabschaltung führen, wenn die Kühlung unzureichend ist.</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';

const props = defineProps({
    server: Object,
    processing: Boolean
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

const gameStore = useGameStore();

const toggleMining = async () => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/mining/toggle`);
        if (response.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};
</script>

<style scoped>
.mining-status-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--v3-radius);
    transition: all var(--v3-transition-fast);
}

.mining-status-card.active {
    border-color: var(--v3-warning);
    background: rgba(241, 196, 15, 0.05);
}

.status-icon {
    font-size: 2rem;
}

.status-details {
    flex: 1;
}

.status-title {
    font-size: 0.9rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 5px;
}

.mining-status-card.active .status-title {
    color: var(--v3-warning);
    animation: v3-pulse-state 2s infinite;
}

.status-desc {
    font-size: 0.65rem;
    color: var(--v3-text-secondary);
}

.btn-toggle {
    background: transparent;
    border: 1px solid var(--v3-border-heavy);
    color: var(--v3-text-ghost);
    padding: 10px 20px;
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    border-radius: 2px;
}

.btn-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.btn-toggle.on {
    border-color: var(--v3-danger);
    color: var(--v3-danger);
}

.btn-toggle.on:hover {
    background: var(--v3-danger);
    color: #000;
}

.warning-group {
    border-color: rgba(255, 77, 79, 0.3);
}

.risk-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.65rem;
    color: var(--v3-text-secondary);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.risk-list li strong {
    color: var(--v3-danger);
}
</style>
