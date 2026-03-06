<template>
    <div class="tab-content software-tab provision-lab">
        <!-- Installing Software View -->
        <div v-if="server.software?.status === 'installing'" class="provisioning-active">

            <div class="proc-main">
                <div class="proc-identity">
                    <div class="os-icon-anim">📦</div>
                    <h4>PROZESS_LÄUFT</h4>
                    <span>Installation von Systemdiensten...</span>
                    <div class="v3-provisioning-eta"
                        style="margin-top: 15px; font-family: var(--ds-font-mono); color: var(--ds-accent);">
                        <template v-if="remainingSeconds > 0">
                            {{ remainingSeconds }}s VERBLEIBEND
                        </template>
                        <template v-else>
                            <div class="sync-status">
                                <span>SYSTEM_SYNC...</span>
                                <button class="btn-on btn-xs"
                                    style="margin-left: 10px; font-size: 0.6rem; padding: 2px 8px;"
                                    @click="$emit('reload')" :disabled="processing">SYNC_NOW</button>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="proc-visuals">
                    <div class="progress-ring-container">
                        <svg viewBox="0 0 36 36" class="circular-progress">
                            <path class="circle-bg"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path class="circle" :stroke-dasharray="softwareProgress + ', 100'"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <text x="18" y="20.35" class="percentage">{{ Math.round(softwareProgress) }}%</text>
                        </svg>
                    </div>
                    <div class="kernel-console">
                        <div class="console-header">PACKAGE_MANAGER_LOG</div>
                        <div class="console-body">
                            <div v-for="(log, idx) in pkgLogs" :key="idx" class="console-line">{{ log }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="software-dashboard">
            <!-- Active Services -->
            <div class="active-services-panel">
                <div class="panel-header">
                    <h5>AKTIVE_SYSTEM_DIENSTE</h5>
                    <span class="service-count">{{ server.software?.installed?.length || 0 }} AKTIV</span>
                </div>

                <div class="service-grid-v3">
                    <div v-for="app in server.software?.installed || []" :key="app.id" class="service-node"
                        :class="{ 'has-update': needsUpdate(app.id) }">
                        <div class="node-status"><span class="pulse-dot"></span></div>
                        <div class="node-info">
                            <span class="node-name">{{ app.name }}</span>
                            <span class="node-version">v{{ app.version }}</span>
                        </div>
                        <div class="node-actions">
                            <button v-if="needsUpdate(app.id)" @click="updateSoftware(app.id)" class="btn-node-update"
                                title="Paket aktualisieren">⚡</button>
                            <button @click="uninstallSoftware(app.id)" class="btn-node-remove">×</button>
                        </div>
                    </div>
                    <div v-if="!(server.software?.installed?.length)" class="empty-services">
                        Keine aktiven Dienste auf diesem System gefunden.
                    </div>
                </div>
            </div>

            <!-- Catalog Grid -->
            <div class="software-catalog-v3">
                <div class="section-header">
                    <h5>SOFTWARE_ZENTRALE</h5>
                </div>

                <div class="os-grid-v3">
                    <div v-for="(def, key) in softwareCatalog" :key="key" class="os-release-card software"
                        :class="{ active: isSoftwareInstalled(key) }">
                        <div class="release-header">
                            <strong>{{ def.name }}</strong>
                            <span class="cost" :class="{ 'text-danger': def.cost > 0 }" v-if="def.cost > 0">${{ def.cost
                                }}</span>
                            <span class="cost free" v-else>FREEWARE</span>
                        </div>
                        <p class="release-desc">{{ def.description }}</p>
                        <div class="release-specs">
                            <div class="spec-tag">v{{ def.version }}</div>
                            <div class="spec-tag" v-if="def.performance_bonus > 1">⚡ +{{
                                Math.round((def.performance_bonus - 1) * 100) }}% Perf.</div>
                        </div>
                        <button @click="installSoftware(key)" class="btn-deployment"
                            :disabled="isSoftwareInstalled(key) || processing">
                            {{ isSoftwareInstalled(key) ? 'INSTALLIERT' : 'INSTALLIEREN' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="pendingAction !== null"
            :title="pendingAction === 'uninstall' ? 'DIENST_DEINSTALLATION' : 'SOFTWARE_DEKPLOYMENT'"
            :message="pendingAction === 'uninstall' ? 'Diesen Systemdienst wirklich vom Server entfernen?' : `${softwareCatalog[pendingKey]?.name} auf dem Zielsystem installieren?`"
            warning="Dieser Vorgang kann aktive Kundenaufträge auf diesem Knoten unterbrechen!"
            :confirm-label="pendingAction === 'uninstall' ? 'LÖSCHEN_BESTÄTIGEN' : 'INSTALLATION_STARTEN'"
            :type="pendingAction === 'uninstall' ? 'danger' : 'info'" @confirm="executeAction"
            @cancel="pendingAction = null" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    server: { type: Object, required: true },
    softwareCatalog: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

import api from '../../../../utils/api';
import ConfirmationModal from '../../../UI/ConfirmationModal.vue';

const pendingAction = ref(null);
const pendingKey = ref(null);
const pendingId = ref(null);

const installSoftware = (type) => {
    if (props.processing) return;
    pendingAction.value = 'install';
    pendingKey.value = type;
};

const uninstallSoftware = (id) => {
    if (props.processing) return;
    pendingAction.value = 'uninstall';
    pendingId.value = id;
};

const executeAction = async () => {
    const action = pendingAction.value;
    const key = pendingKey.value;
    const id = pendingId.value;
    pendingAction.value = null;

    emit('processing-start');
    try {
        let response;
        if (action === 'install') {
            response = await api.post(`/server/${props.server.id}/install-software`, { software_type: key });
        } else {
            response = await api.post(`/server/${props.server.id}/uninstall-software`, { software_id: id });
        }
        if (response.success) {
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const updateSoftware = async (id) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/update-software`, { software_id: id });
        if (response.success) {
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const isSoftwareInstalled = (modKey) => {
    return props.server.software?.installed?.some(s => s.id === modKey);
};

const needsUpdate = (modKey) => {
    const installed = props.server.software?.installed?.find(s => s.id === modKey);
    const catalogItem = props.softwareCatalog[modKey];
    if (!installed || !catalogItem) return false;
    // Assuming simple version comparison for now
    return installed.version !== catalogItem.version;
};

// -- Local Installation State Simulation --
const currentTime = ref(Date.now());
let timerInterval = null;

const pkgLines = [
    "[INFO] Checking dependencies...",
    "[INFO] Downloading package manifests...",
    "[GET]  Resolving remote repository keys...",
    "[OK]   Signatures validated.",
    "[RUN]  Executing pre-install scripts...",
    "[OK]   Pre-flight checks passed.",
    "[BUSY] Unpacking binary payloads...",
    "[BUSY] Compiling native extensions (if applicable)...",
    "[OK]   Linking runtime libraries...",
    "[INFO] Generating default configuration...",
    "[RUN]  Registering system service daemon...",
    "[OK]   Service registration complete.",
    "[OK]   Deployment finalized successfully."
];

const pkgLogs = ref([]);
const pkgLogIndex = ref(0);

const softwareProgress = computed(() => {
    if (!props.server?.software?.installCompletesAt || !props.server?.software?.installStartedAt) return 0;
    const start = new Date(props.server.software.installStartedAt).getTime();
    const end = new Date(props.server.software.installCompletesAt).getTime();
    return Math.min(100, Math.max(0, ((currentTime.value - start) / (end - start)) * 100));
});

const remainingSeconds = computed(() => {
    if (!props.server?.software?.installCompletesAt) return 0;
    const end = new Date(props.server.software.installCompletesAt).getTime();
    return Math.max(0, Math.floor((end - currentTime.value) / 1000));
});

onMounted(() => {
    timerInterval = setInterval(() => {
        currentTime.value = Date.now();

        if (props.server?.software?.status === 'installing') {
            const totalSteps = pkgLines.length;
            const stepThreshold = 100 / totalSteps;
            const currentStep = Math.floor(softwareProgress.value / stepThreshold);

            if (currentStep > pkgLogIndex.value && pkgLogIndex.value < totalSteps) {
                const line = pkgLines[pkgLogIndex.value];
                pkgLogs.value.unshift(line);
                pkgLogIndex.value++;
            }
        } else {
            pkgLogs.value = [];
            pkgLogIndex.value = 0;
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});
</script>
