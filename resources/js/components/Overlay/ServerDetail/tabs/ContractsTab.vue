<template>
    <div class="tab-content contracts-tab provision-lab">
        <!-- Insurance Section -->
        <div class="v3-info-box">
            <label>HARDWARE_VERSICHERUNG</label>
            <p class="setting-desc">Schützen Sie Ihre Assets vor Hardware-Defekten, Bränden oder Beschlagnahmung.</p>

            <div v-if="server.specs?.insurance_plan" class="active-contract-card insurance">
                <div class="v3-spec-card">
                    <HardwareIcon type="motherboard" size="sm" />
                    <div class="v3-details">
                        <strong>{{ insurancePlans[server.specs.insurance_plan]?.name || server.specs.insurance_plan
                            }}</strong>
                        <span>Prämie: ${{ (server.purchaseCost *
                            (insurancePlans[server.specs.insurance_plan]?.premium_rate || 0)).toFixed(2) }}/h</span>
                    </div>
                </div>
                <div class="active-contract-actions" style="display: flex; gap: 10px; margin-top: 15px;">
                    <button class="btn-cancel-v3" @click="cancelInsurance"
                        :disabled="processing">POLICE_STORNIEREN</button>
                    <button class="btn-cancel-v3" style="background:#800000; color:#fff;" @click="commitFraud"
                        :disabled="processing">🔥 BRANDSTIFTUNG (EVIL PATH)</button>
                </div>
            </div>

            <div v-else class="v3-plan-grid">
                <div v-for="(plan, key) in insurancePlans" :key="key" class="v3-plan-card"
                    :class="{ locked: playerLevel < plan.min_level }" @click="insureServer(key)">
                    <div class="p-head">
                        <span class="p-name">{{ plan.name }}</span>
                        <span class="p-rate">{{ (plan.premium_rate * 100).toFixed(1) }}%/h</span>
                    </div>
                    <p class="p-desc">{{ plan.description }}</p>
                    <div class="p-footer">
                        <span>Deckung: {{ (plan.coverage_pct * 100) }}%</span>
                        <span v-if="playerLevel < plan.min_level" class="lock-tag">🔒 LVL {{ plan.min_level }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Darknet Section -->
        <div class="v3-info-box warning" style="margin-top: 30px;">
            <label>DARKNET_OPERATIONEN</label>
            <p class="setting-desc">Hosten von Hochrisiko-Workloads für maximale Marge. Erhöht den Umsatz, generiert
                lokal **FEDERAL_HEAT**.</p>

            <div class="v3-heat-telemetry" v-if="darknetState">
                <div class="h-header">
                    <label>FÖDERALE_BEWACHUNGS_INTENSITÄT</label>
                    <span class="h-val" :class="{ critical: darknetState.current_heat > 50 }">
                        {{ (darknetState.current_heat || 0).toFixed(1) }} / {{ darknetState.max_heat }}
                    </span>
                </div>
                <div class="v3-progress-flat danger">
                    <div class="fill"
                        :style="{ width: (darknetState.current_heat / darknetState.max_heat * 100) + '%' }"></div>
                </div>
            </div>

            <div v-if="server.specs?.darknet_active" class="active-contract-card dark">
                <div class="v3-spec-card">
                    <div class="v3-icon">🌑</div>
                    <div class="v3-details">
                        <strong>{{ darknetTypes[server.specs.darknet_type]?.name || server.specs.darknet_type
                            }}</strong>
                        <span>Profit-Multiplikator: x{{ darknetTypes[server.specs.darknet_type]?.profit_mult }}</span>
                    </div>
                </div>
                <button class="btn-cancel-v3" @click="disableDarknet" :disabled="processing">OFFLINE_GEHEN</button>
            </div>

            <div v-else class="v3-plan-grid">
                <div v-for="(type, key) in darknetTypes" :key="key" class="v3-plan-card dark"
                    :class="{ locked: playerLevel < type.min_level }" @click="enableDarknet(key)">
                    <div class="p-head">
                        <span class="p-name">{{ type.name }}</span>
                        <span class="p-rate">PROFIT x{{ type.profit_mult }}</span>
                    </div>
                    <p class="p-desc">{{ type.description }}</p>
                    <div class="p-footer">
                        <span>Heat: +{{ type.heat_gain }}</span>
                        <span v-if="playerLevel < type.min_level" class="lock-tag">🔒 LVL {{ type.min_level }}</span>
                    </div>
                </div>
            </div>

            <div v-if="server.specs?.seized_by_fbi" class="v3-seizure-notice">
                🚨 ALERT: SYSTEM_BESCHLAGNAHMT. TERMINAL_ACCESS_DENIED.
            </div>
        </div>

        <!-- Compliance Section -->
        <div class="v3-info-box" style="margin-top: 30px;">
            <label>COMPLIANCE_&_DATA_INTEGRITY</label>
            <p class="setting-desc">Überwachung der regulatorischen Anforderungen für diesen Node.</p>

            <div class="v3-compliance-dashboard">
                <div class="c-stat">
                    <span class="l">Security Patch-Level</span>
                    <strong
                        :class="{ 'text-success': (server.os?.patch_level || 0) >= 90, 'text-danger': (server.os?.patch_level || 0) < 90 }">
                        {{ server.os?.patch_level || 0 }}%
                    </strong>
                </div>
                <div class="c-stat">
                    <span class="l">Nachhaltigkeit</span>
                    <strong
                        :class="{ 'text-success': greenScore >= 80, 'text-warning': greenScore >= 50, 'text-danger': greenScore < 50 }">
                        {{ greenScore.toFixed(1) }}%
                    </strong>
                </div>
                <div class="c-stat">
                    <span class="l">Sicherheits-Freigabe</span>
                    <strong v-if="hasSensitiveData" class="text-warning">💎 DIAMOND_LEVEL</strong>
                    <strong v-else class="text-ghost">STANDARD</strong>
                </div>

                <div class="compliance-alert" v-if="hasSensitiveData">
                    <div class="a-icon">⚠️</div>
                    <div class="a-text">
                        <strong>DATA_RETENTION_WARNING</strong>
                        <span>Dieses System hostet sensible Daten. Vor einer Hardware-Veräußerung ist eine zertifizierte
                            Schredder-Prozedur ($50) zwingend erforderlich.</span>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="pendingAction !== null" :title="modalTitle" :message="modalMessage"
            :warning="modalWarning" :confirm-label="modalConfirmLabel" :type="modalType" @confirm="executeAction"
            @cancel="pendingAction = null" />
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import HardwareIcon from '../../../UI/HardwareIcon.vue';
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';
import { useInfrastructureStore } from '../../../../stores/infrastructure';
import ConfirmationModal from '../../../UI/ConfirmationModal.vue';

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload', 'close']);

const gameStore = useGameStore();
const infraStore = useInfrastructureStore();

const playerLevel = computed(() => gameStore.player?.economy?.level || 1);
const greenScore = computed(() => gameStore.player?.economy?.specializedReputation?.green || 0);

const insurancePlans = ref({});
const darknetTypes = ref({});
const darknetState = ref({});
const pendingAction = ref(null);
const pendingKey = ref(null);

const modalTitle = computed(() => {
    if (pendingAction.value === 'insure') return 'VERSICHERUNGS_ABSCHLUSS';
    if (pendingAction.value === 'cancel_insure') return 'POLICE_STORNIERUNG';
    if (pendingAction.value === 'fraud') return ' !!! EXTREME_GEFAHR !!!';
    if (pendingAction.value === 'darknet') return 'FEDERAL_NOTICE';
    return '';
});

const modalMessage = computed(() => {
    if (pendingAction.value === 'insure') return `Möchten Sie diesen Server wirklich mit '${insurancePlans.value[pendingKey.value]?.name}' versichern?`;
    if (pendingAction.value === 'cancel_insure') return 'Versicherungspolice kündigen? Der Schutz entfällt sofort.';
    if (pendingAction.value === 'fraud') return 'Wollen Sie wirklich vorsätzlichen Versicherungsbetrug (Brandstiftung) begehen?';
    if (pendingAction.value === 'darknet') return 'Die Aktivierung von Darknet-Operationen erhöht die behördliche Überwachung maßgeblich.';
    return '';
});

const modalWarning = computed(() => {
    if (pendingAction.value === 'fraud') return 'ERFOLG: 150% Hardware-Wert. FEHLSCHLAG: Massive Strafe, Reputationsverlust & Beschlagnahmung!';
    return '';
});

const modalConfirmLabel = computed(() => {
    if (pendingAction.value === 'insure') return 'ABSCHLIESSEN';
    if (pendingAction.value === 'cancel_insure') return 'STORNORIEREN';
    if (pendingAction.value === 'fraud') return 'BRANDSTIFTUNG_DURCHFÜHREN';
    if (pendingAction.value === 'darknet') return 'OPERATION_STARTEN';
    return 'BESTÄTIGEN';
});

const modalType = computed(() => {
    if (pendingAction.value === 'fraud') return 'danger';
    if (pendingAction.value === 'darknet') return 'warning';
    return 'info';
});

const loadContractData = async () => {
    try {
        const insRes = await api.get('/hardware/insurance/plans');
        if (insRes.success) insurancePlans.value = insRes.plans;

        const darkRes = await api.get('/management/darknet');
        if (darkRes.success) {
            darknetState.value = darkRes.data;
            darknetTypes.value = darkRes.data.traffic_types;
        }
    } catch (e) {
        console.error('Failed to load contract data', e);
    }
};

onMounted(() => {
    loadContractData();
});

const hasSensitiveData = computed(() => {
    return props.server.activeOrdersCount > 0 && props.server.activeOrders?.some(o => o.sla_tier === 'whale' || o.sla_tier === 'diamond' || o.sla_tier === 'enterprise');
});

const insureServer = (planKey) => {
    if (playerLevel.value < insurancePlans.value[planKey].min_level || props.processing) return;
    pendingAction.value = 'insure';
    pendingKey.value = planKey;
};

const cancelInsurance = () => {
    if (props.processing) return;
    pendingAction.value = 'cancel_insure';
};

const commitFraud = () => {
    if (props.processing) return;
    pendingAction.value = 'fraud';
};

const enableDarknet = (type) => {
    if (playerLevel.value < darknetTypes.value[type].min_level || props.processing) return;
    pendingAction.value = 'darknet';
    pendingKey.value = type;
};

const executeAction = async () => {
    const action = pendingAction.value;
    const key = pendingKey.value;
    pendingAction.value = null;

    emit('processing-start');
    try {
        if (action === 'insure') {
            await infraStore.insureServer(props.server.id, key);
            emit('reload');
        } else if (action === 'cancel_insure') {
            await infraStore.cancelInsurance(props.server.id);
            emit('reload');
        } else if (action === 'fraud') {
            const response = await api.post(`/server/${props.server.id}/insurance-fraud`);
            alert(response.message);
            emit('close');
            gameStore.loadGameState();
        } else if (action === 'darknet') {
            const success = await infraStore.enableDarknet(props.server.id, key);
            if (success) {
                emit('reload');
                loadContractData();
            }
        }
    } finally {
        emit('processing-end');
    }
};

const disableDarknet = async () => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const success = await infraStore.disableDarknet(props.server.id);
        if (success) {
            emit('reload');
            loadContractData();
        }
    } finally {
        emit('processing-end');
    }
};
</script>
