<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="server-detail-overlay glass-panel animation-slide-up" v-if="server">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon">💻</span>
                    <h2 class="server-title-main">
                        <span class="name">{{ server.nickname || server.modelName }}</span>
                        <small v-if="server.nickname" class="model-sub">{{ server.modelName }}</small>
                        <small class="id-sub">#{{ server.id.substring(0, 8) }}</small>
                    </h2>
                </div>
                <div class="header-actions">
                    <div class="status-indicator" :class="server.status">
                        {{ server.status }}
                    </div>
                    <button class="close-btn" @click="$emit('close')">&times;</button>
                </div>
            </div>

            <div class="overlay-tabs">
                <button v-for="tab in availableTabs" :key="tab" @click="activeTab = tab"
                    :class="{ active: activeTab === tab }">{{ tabLabels[tab] || tab }}</button>
            </div>

            <div class="overlay-body">
                <!-- Summary Tab -->
                <SummaryTab v-if="activeTab === 'Summary'" :server="server" :healthClass="healthClass"
                    :formatRuntime="formatRuntime" :getWearClass="getWearClass" />
                <!-- Networking Tab -->
                <NetworkingTab v-if="activeTab === 'Networking'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <!-- Shared Hosting Tab -->
                <SharedHostingTab v-if="activeTab === 'Shared Hosting'" :server="server" />

                <!-- Hardware Tab -->
                <HardwareTab v-if="activeTab === 'Hardware'" :server="server" :components="components"
                    :processing="processing" @power-toggle="powerToggle" @processing-start="processing = true"
                    @processing-end="processing = false" @reload="loadDetails" @close="$emit('close')" />

                <OsTab v-if="activeTab === 'OS'" :server="server" :osCatalog="osCatalog" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <!-- Rollout / Templates Tab -->
                <RolloutTab v-if="activeTab === 'Rollout'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails"
                    @switch-tab="activeTab = $event" />

                <!-- Software Tab -->
                <SoftwareTab v-if="activeTab === 'Software'" :server="server" :softwareCatalog="softwareCatalog"
                    :processing="processing" @processing-start="processing = true" @processing-end="processing = false"
                    @reload="loadDetails" />

                <!-- Performance Tab -->
                <PerformanceTab v-if="activeTab === 'Performance'" :server="server" :metrics="metrics" />

                <MaintenanceTab v-if="activeTab === 'Maintenance'" :server="server" :player="gameStore.player"
                    :healthClass="healthClass" :getWearClass="getWearClass" :formatTimeDetailed="formatTimeDetailed"
                    :processing="processing" @processing-start="processing = true" @processing-end="processing = false"
                    @reload="loadDetails" @close="$emit('close')" />

                <!-- Backups Tab -->
                <BackupsTab v-if="activeTab === 'Backups'" :server="server" :formatRuntime="formatRuntime"
                    :processing="processing" @processing-start="processing = true" @processing-end="processing = false"
                    @reload="loadDetails" />

                <!-- Mining Tab -->
                <MiningTab v-if="activeTab === 'Mining'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <!-- Contracts & Legal Tab -->
                <ContractsTab v-if="activeTab === 'Contracts'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" @close="$emit('close')" />

                <AppearanceTab v-if="activeTab === 'Appearance'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <!-- Tuning Tab (FEATURE 254/290) -->
                <TuningTab v-if="activeTab === 'Tuning'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <LogsTab v-if="activeTab === 'Logs'" :logs="logs" />

                <BatteryTab v-if="activeTab === 'Battery'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />

                <RentalTab v-if="activeTab === 'Rental'" :server="server" :processing="processing"
                    @processing-start="processing = true" @processing-end="processing = false" @reload="loadDetails" />
            </div>

            <div class="overlay-actions footer">
                <div class="left-actions">
                    <div v-if="diagnosticStatus" class="diag-status-text">{{ diagnosticStatus }}</div>
                    <button class="btn-diagnose" :disabled="server.isDiagnosed || processing" @click="runDiagnostics">
                        🔍 {{ server.isDiagnosed ? 'DIAGNOSE_ABGESCHLOSSEN' : 'DIAGNOSE_STARTEN' }}
                    </button>
                    <div v-if="server.isDiagnosed && server.currentFault" class="fault-display-footer">
                        SIGNAL: {{ server.currentFault }}
                    </div>
                </div>
                <div class="right-actions">
                    <template v-if="server.status === 'online' || server.status === 'degraded'">
                        <div class="maintenance-action-group">
                            <select v-model="maintenanceDelay" class="v3-select-sm">
                                <option :value="0">JETZT</option>
                                <option :value="10">10m</option>
                                <option :value="30">30m</option>
                            </select>
                            <button class="btn-maintenance" :disabled="processing" @click="startMaintenance">
                                📅 WARTUNG (${{ maintenanceCost }})
                            </button>
                        </div>
                    </template>
                    <template v-if="server.status === 'damaged' || server.status === 'degraded'">
                        <button class="btn-repair" :disabled="processing" @click="repairServer">
                            🔧 REPARATUR (${{ repairCost }})
                        </button>
                    </template>

                    <button v-if="server.status === 'online'" class="btn-off" :disabled="processing"
                        @click="powerToggle">AUSSCHALTEN</button>
                    <button v-else-if="server.status !== 'maintenance'" class="btn-on" :disabled="processing"
                        @click="powerToggle">EINSCHALTEN</button>

                    <button class="btn-sell-sm" v-if="server.status === 'offline' && !server.isLeased"
                        :disabled="processing || server.activeOrdersCount > 0" @click="sellToSecondaryMarket"
                        title="Auf dem Gebrauchtmarkt verkaufen">
                        💰 VERKAUFEN
                    </button>

                    <button class="btn-shred" v-if="server.status === 'offline'"
                        :disabled="processing || server.activeOrdersCount > 0" @click="shredServer"
                        title="Festplatten sicher löschen (Compliance)">
                        🪓 SHREDDER ($50)
                    </button>
                </div>
            </div>
        </div>

        <!-- Diagnostic Puzzle Overlay -->
        <div v-if="showDiagnosticTask" class="diagnostic-container-overlay">
            <DiagnosticTask :type="interactionType" :hint="faultHint" @complete="handleDiagnosticSuccess"
                @fail="handleDiagnosticFail" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useMultiplayerStore } from '../../stores/multiplayer';
import { useAuthStore } from '../../stores/auth';
const emit = defineEmits(['close']);
import api from '../../utils/api';
import { useInfrastructureStore } from '../../stores/infrastructure';
import DiagnosticTask from '../UI/DiagnosticTask.vue';
import { useServerDetail } from '../../composables/useServerDetail';

// Extracted sub-tab components
import HardwareTab from './ServerDetail/tabs/HardwareTab.vue';
import OsTab from './ServerDetail/tabs/OsTab.vue';
import SoftwareTab from './ServerDetail/tabs/SoftwareTab.vue';
import MaintenanceTab from './ServerDetail/tabs/MaintenanceTab.vue';
import TuningTab from './ServerDetail/tabs/TuningTab.vue';
import SummaryTab from './ServerDetail/tabs/SummaryTab.vue';
import NetworkingTab from './ServerDetail/tabs/NetworkingTab.vue';
import SharedHostingTab from './ServerDetail/tabs/SharedHostingTab.vue';
import LogsTab from './ServerDetail/tabs/LogsTab.vue';
import BackupsTab from './ServerDetail/tabs/BackupsTab.vue';
import AppearanceTab from './ServerDetail/tabs/AppearanceTab.vue';
import RolloutTab from './ServerDetail/tabs/RolloutTab.vue';
import ContractsTab from './ServerDetail/tabs/ContractsTab.vue';
import PerformanceTab from './ServerDetail/tabs/PerformanceTab.vue';
import RentalTab from './ServerDetail/tabs/RentalTab.vue';
import MiningTab from './ServerDetail/tabs/MiningTab.vue';
import BatteryTab from './ServerDetail/tabs/BatteryTab.vue';

const props = defineProps({
    serverId: { type: String, required: true }
});

const gameStore = useGameStore();
const multiplayerStore = useMultiplayerStore();
const authStore = useAuthStore();
const infraStore = useInfrastructureStore();

const activeTab = ref('Summary');

// --- Use Extracted Composable ---
const { 
    server, metrics, logs, components, processing, 
    loadDetails: fetchServerDetails, formatRuntime, formatTimeDetailed, 
    getWearClass, healthClass, repairCost, maintenanceCost 
} = useServerDetail(props.serverId);

const maintenanceDelay = ref(0);
const showDiagnosticTask = ref(false);
const interactionType = ref('signal');
const faultHint = ref('');
const diagnosticStatus = ref('');
const rentalPrice = ref(0);
const osCatalog = ref({});
const serverNickname = ref('');
const currentTime = ref(Date.now());
let timerInterval = null;

const economy = computed(() => gameStore.player?.economy || {});



const loadDetails = async () => {
    try {
        const success = await fetchServerDetails();
        if (success) {
            // Sync nickname for editing
            if (server.value.nickname && !serverNickname.value) {
                serverNickname.value = server.value.nickname;
            } else if (!server.value.nickname) {
                serverNickname.value = '';
            }

            // If we have catalogs, we can assume we also want insurance/darknet
            if (activeTab.value === 'OS') {
                loadOsCatalog();
            }
            if (activeTab.value === 'Software') {
                loadSoftwareCatalog();
            }
        }
    } catch (e) {
        console.error('Failed to load server details', e);
    }
};



onMounted(() => {
    loadDetails();
    timerInterval = setInterval(() => {
        currentTime.value = Date.now();

        // If installation just finished, reload details
        if (server.value?.os?.status === 'installing' && remainingSeconds.value === 0) {
            loadDetails();
            gameStore.loadGameState();
        }

        // Auto-refresh metrics every 5s if on Performance tab
        if (activeTab.value === 'Performance' && (Math.floor(Date.now() / 1000) % 5 === 0)) {
            loadDetails();
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});



const runDiagnostics = async () => {
    if (processing.value) return;
    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/diagnose`);
        if (response.success) {
            interactionType.value = response.interaction_type || 'signal';
            faultHint.value = response.fault_hint || '';
            showDiagnosticTask.value = true;
        }
    } finally {
        processing.value = false;
    }
};

const handleDiagnosticSuccess = async () => {
    showDiagnosticTask.value = false;
    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/diagnose/complete`);
        if (response.success) {
            server.value = response.server;
            gameStore.loadGameState();
            diagnosticStatus.value = 'DIAGNOSIS_COMPLETE: System logic restored.';
            loadDetails(); // reload to get diagnostic logs
        }
    } finally {
        processing.value = false;
    }
};

const handleDiagnosticFail = (reason) => {
    showDiagnosticTask.value = false;
    diagnosticStatus.value = `DIAG_FAIL: ${reason}`;
};

const repairServer = async () => {
    processing.value = true;
    try {
        const response = await api.post('/server/repair', { server_id: props.serverId });
        if (response.success) {
            server.value = response.data;
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};





const startMaintenance = async () => {
    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/maintenance`, {
            delay_minutes: maintenanceDelay.value
        });
        if (response.success) {
            server.value = response.data;
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const powerToggle = async () => {
    if (processing.value) return;
    processing.value = true;
    try {
        if (server.value.status === 'online') {
            await gameStore.powerOffServer(server.value.id);
        } else {
            await gameStore.powerOnServer(server.value.id);
        }
        await loadDetails();
    } finally {
        processing.value = false;
    }
};

const sellToSecondaryMarket = async () => {
    const value = Math.round(server.value.resaleValue);
    if (!confirm(`Diesen Server für $${value.toLocaleString()} auf dem Gebrauchtmarkt verkaufen? Diese Aktion ist unwiderruflich.`)) return;

    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/sell`);
        if (response.success) {
            emit('close');
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};

const shredServer = async () => {
    if (!confirm("SICHERE VERNICHTUNG: Möchten Sie Speicher und CPU dieser Einheit wirklich physisch schreddern? Kosten: $50, kein Wiederverkaufswert, aber Ihr 'Sichere Entsorgung'-Zähler für Behördenzertifizierungen wird erhöht.")) return;

    processing.value = true;
    try {
        const response = await api.post(`/server/${props.serverId}/shred`);
        if (response.success) {
            emit('close');
            gameStore.loadGameState();
        }
    } finally {
        processing.value = false;
    }
};
const softwareCatalog = ref({});

const loadSoftwareCatalog = async () => {
    try {
        const res = await api.get('/catalog/software');
        if (res.success) softwareCatalog.value = res.catalog || res.data;
    } catch (e) {
        console.error("Failed to load software catalog", e);
    }
};



const tabLabels = {
    'Summary': 'ÜBERSICHT',
    'Networking': 'NETZWERK',
    'OS': 'BETRIEBSSYSTEM',
    'Rollout': 'ROLLOUT',
    'Software': 'SOFTWARE',
    'Shared Hosting': 'SHARED_HOSTING',
    'Hardware': 'HARDWARE',
    'Performance': 'LEISTUNG',
    'Maintenance': 'WARTUNG',
    'Backups': 'BACKUPS',
    'Appearance': 'OPTIK',
    'Contracts': 'VERTRÄGE',
    'Logs': 'PROTOKOLLE',
    'Rental': 'VERMIETUNG',
    'Tuning': 'TUNING',
    'Mining': 'MINING',
    'Battery': 'BATTERIE'
};

watch(activeTab, (val) => {
    if (val === 'OS' && Object.keys(osCatalog.value).length === 0) {
        loadOsCatalog();
    }
    if (val === 'Software' && Object.keys(softwareCatalog.value).length === 0) {
        loadSoftwareCatalog();
    }
});

const availableTabs = computed(() => {
    if (server.value?.type === 'battery') {
        return ['Summary', 'Battery', 'Hardware', 'Performance', 'Maintenance', 'Appearance', 'Logs'];
    }
    const tabs = ['Summary', 'Networking', 'OS', 'Rollout', 'Software', 'Tuning', 'Mining'];
    if (server.value?.type === 'shared_node') tabs.push('Shared Hosting');
    tabs.push('Hardware');
    tabs.push('Performance', 'Maintenance', 'Backups', 'Appearance', 'Contracts', 'Logs', 'Rental');
    return tabs;
});


</script>

<style scoped src="./ServerDetailOverlay.css"></style>
