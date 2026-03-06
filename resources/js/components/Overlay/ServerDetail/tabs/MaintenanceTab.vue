<template>
    <div class="tab-content maintenance-tab provision-lab">
        <div class="maintenance-dashboard">
            <!-- Upper Section: Vital Signs -->
            <div class="v3-vital-grid">
                <div class="v3-info-box health">
                    <label>SYSTEM_INTEGRITÄT</label>
                    <div class="v3-health-visual">
                        <div class="h-main">
                            <div class="h-ring">
                                <svg viewBox="0 0 36 36">
                                    <path class="circle-bg"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" :class="healthClass"
                                        :stroke-dasharray="(server.health || 0) + ', 100'"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                </svg>
                                <div class="h-text">
                                    <strong>{{ Math.round(server.health || 0) }}%</strong>
                                    <span>INTEGRITÄT</span>
                                </div>
                            </div>
                            <div class="h-info">
                                <div class="h-status" :class="healthClass">
                                    {{ (server.health || 0) > 90 ? 'OPTIMAL' : ((server.health || 0) > 50 ? 'DEGRADIERT'
                                        : 'KRITISCH') }}
                                </div>
                                <div v-if="server.currentFault" class="v3-fault-alert">
                                    ⚠️ {{ server.currentFault }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="v3-info-box warning aging">
                    <label>HARDWARE_LEBENSDAUER</label>
                    <div class="v3-lifecycle">
                        <div class="l-row">
                            <span>Akkumulierter Verschleiß:</span>
                            <strong :class="getWearClass(server.aging?.wearPercentage || 0)">{{
                                (server.aging?.wearPercentage || 0).toFixed(2) }}%</strong>
                        </div>
                        <div class="l-row">
                            <span>Geschätzte Restlaufzeit:</span>
                            <strong>{{ Math.max(0, 100 - (server.aging?.wearPercentage || 0)).toFixed(0) }}
                                Zyklen</strong>
                        </div>
                        <div class="v3-progress-flat wear">
                            <div class="fill" :class="getWearClass(server.aging?.wearPercentage || 0)"
                                :style="{ width: (server.aging?.wearPercentage || 0) + '%' }"></div>
                        </div>
                    </div>
                    <div class="v3-pfa-zone" v-if="player?.research?.monitoring_v1" style="margin-top: 20px;">
                        <label>PREDICTIVE_FAILURE_ANALYSIS</label>
                        <div class="v3-progress-flat small danger">
                            <div class="fill"
                                :style="{ width: Math.min(100, (server.aging?.wearPercentage || 0) * 1.5) + '%' }">
                            </div>
                        </div>
                        <small class="hint">Wahrscheinlichkeit eines Totalausfalls: {{ ((server.aging?.wearPercentage ||
                            0) * 0.2).toFixed(1) }}%</small>
                    </div>
                </div>
            </div>

            <!-- Supply Management Section -->
            <div class="v3-info-box" style="margin-top: 30px;">
                <div class="v3-supply-header">
                    <div class="h-title">
                        <label>WARTUNGS_LOGISTIK</label>
                        <p>Verwalten Sie Ersatzteil-Kits für sofortige Hardware-Wiederherstellung.</p>
                    </div>
                    <div class="h-inventory">
                        <span class="label">VORRAT:</span>
                        <strong class="val">{{ player?.economy?.spare_parts_count || 0 }} KITS</strong>
                    </div>
                </div>

                <div class="v3-hw-grid" style="margin-top: 20px;">
                    <div class="v3-hw-slot"
                        :class="{ disabled: (player?.economy?.spare_parts_count || 0) < 1 || server.health >= 100 }">
                        <div class="v3-icon">🔧</div>
                        <div class="slot-info">
                            <div class="n">KIT_ANWENDEN</div>
                            <div class="v">+25% Integrität (SOFORT)</div>
                        </div>
                        <button class="btn-primary-v3-sm" @click="useSparePart"
                            :disabled="processing || (player?.economy?.spare_parts_count || 0) < 1 || (server.health || 0) >= 100">
                            AKTIVIEREN
                        </button>
                    </div>
                    <div class="v3-hw-slot">
                        <div class="v3-icon">🛒</div>
                        <div class="slot-info">
                            <div class="n">LOGISTIK_ORDER</div>
                            <div class="v">$500 pro Revision-Kit</div>
                        </div>
                        <button class="btn-v3-ghost-sm" @click="buySpareParts"
                            :disabled="processing || (player?.economy?.balance || 0) < 500">
                            BESTELLEN
                        </button>
                    </div>
                </div>
            </div>

            <!-- Service History -->
            <div class="v3-info-box" style="margin-top: 30px;">
                <label>WARTUNGSHISTORIE</label>
                <div class="v3-table-wrapper">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>ZEITPUNKT</th>
                                <th>TYP</th>
                                <th>KOSTEN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in (server.maintenanceLog || []).slice(0, 5)" :key="entry.id">
                                <td class="time mono">{{ formatTimeDetailed(entry.timestamp) }}</td>
                                <td><span class="v3-tag" :class="entry.type">{{ entry.type.toUpperCase() }}</span></td>
                                <td class="cost mono">${{ (entry.cost || 0).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="!server.maintenanceLog || server.maintenanceLog.length === 0">
                                <td colspan="3" class="empty-msg">Keine Service-Einträge vorhanden.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- EOL Emergency -->
            <div class="eol-catastrophe" v-if="server.status === 'eol'">
                <div class="cat-icon">💀</div>
                <div class="cat-content">
                    <h3>TOTALAUSFALL DER HARDWARE</h3>
                    <p>End-of-Life erreicht. Dieses System ist irreparabel beschädigt.</p>
                    <button class="btn-scrap-emergency" @click="sellServer">ALS RECYCLING-SCHROTT VERKAUFEN (${{
                        Math.round(server.resaleValue || 0).toLocaleString() }})</button>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="showScrapConfirm" title="HARDWARE_RECYCLING_BESTÄTIGUNG"
            message="Sind Sie sicher, dass Sie diesen Server VERSCHROTTEN wollen?"
            warning="Alle Daten und aktiven Kundenaufträge werden unwiderruflich zerstört!"
            confirm-label="VERSCHROTTUNG_DURCHFÜHREN" type="danger" @confirm="executeScrap"
            @cancel="showScrapConfirm = false" />
    </div>
</template>

<script setup>
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';
import ConfirmationModal from '../../../UI/ConfirmationModal.vue';
import { ref } from 'vue';

const showScrapConfirm = ref(false);

const props = defineProps({
    server: { type: Object, required: true },
    player: { type: Object, required: true },
    healthClass: { type: String, required: true },
    getWearClass: { type: Function, required: true },
    formatTimeDetailed: { type: Function, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload', 'close']);

const gameStore = useGameStore();

const useSparePart = async () => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post(`/hardware/servers/${props.server.id}/maintain`);
        if (response.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const buySpareParts = async () => {
    if (props.processing) return;
    if (props.player?.economy?.balance < 500) return alert("Nicht genügend Guthaben!");

    emit('processing-start');
    try {
        const response = await api.post('/hardware/spare-parts/purchase', { amount: 1 });
        if (response.success) {
            gameStore.loadGameState();
        }
    } finally {
        emit('processing-end');
    }
};

const sellServer = () => {
    showScrapConfirm.value = true;
};

const executeScrap = async () => {
    showScrapConfirm.value = false;
    emit('processing-start');
    try {
        const response = await api.post('/hardware/sell', { server_id: props.server.id });
        if (response.success) {
            emit('close');
            gameStore.loadGameState();
        }
    } finally {
        emit('processing-end');
    }
};
</script>
