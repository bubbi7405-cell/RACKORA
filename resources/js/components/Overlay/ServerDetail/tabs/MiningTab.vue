<template>
    <div class="proc-v3 scroll-y">


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
                    <button class="btn-toggle" :class="{ 'on': server.mining?.isMining }" @click="toggleMining"
                        :disabled="processing || (!server.mining?.isMining && server.status !== 'online')"
                        :title="!server.mining?.isMining && server.status !== 'online' ? 'Server muss online sein.' : ''">
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
