<template>
    <div class="tab-content os-tab provisioning-lab">
        <!-- Installation View -->
        <div v-if="server.os?.status === 'installing'" class="provisioning-active">
            <div class="proc-header">
                <div class="proc-title">
                    <h3>SYSTEM_IMAGE_DEPLOYMENT</h3>
                    <p>Initialisierung der Betriebssystem-Umgebung auf Node: {{ server.id.substring(0,8) }}</p>
                </div>
                <div class="proc-eta">
                    <label>RESTZEIT_VERBLEIBEND</label>
                    <strong>{{ remainingSeconds }}s</strong>
                </div>
            </div>

            <div class="proc-main">
                <div class="proc-identity">
                    <div class="os-icon-anim">💿</div>
                    <h4>{{ getOsName(server.os.type) }}</h4>
                    <span>Version {{ server.os.version || 'v1.0' }}</span>
                </div>

                <div class="proc-visuals">
                    <div class="progress-ring-container">
                        <svg viewBox="0 0 36 36" class="circular-progress">
                            <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path class="circle" :stroke-dasharray="installProgress + ', 100'" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <text x="18" y="20.35" class="percentage">{{ Math.round(installProgress) }}%</text>
                        </svg>
                    </div>
                    
                    <div class="kernel-console">
                        <div class="console-header">KERNEL_BOOT_LOG</div>
                        <div class="console-body">
                            <div v-for="(log, idx) in kernelLogs" :key="idx" class="console-line">{{ log }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard View -->
        <div v-else class="os-lab-dashboard">
             <!-- Current OS Identity Card -->
            <div class="os-id-card" v-if="server.os?.type">
                <div class="os-card-main">
                    <div class="os-branding">
                        <div class="os-logo">🐧</div>
                        <div class="os-details">
                            <h3>{{ getOsName(server.os.type) }}</h3>
                            <div class="os-badges">
                                <span class="badge version">{{ server.os.version }}</span>
                                <span class="badge status" :class="server.os.license">{{ (server.os.license || 'active').toUpperCase() }}</span>
                                <span class="badge arch" v-if="server.os.isProprietary">PONY_CORE</span>
                            </div>
                        </div>
                    </div>
                    <div class="os-health-grid">
                        <div class="h-stat">
                            <label>SICHERHEIT</label>
                            <span :class="(server.os.security || 0) > 80 ? 'text-success' : 'text-danger'">{{ server.os.security || 0 }}%</span>
                        </div>
                        <div class="h-stat">
                            <label>INTEGRITÄT</label>
                            <span>{{ server.os.health || 0 }}%</span>
                        </div>
                        <div class="h-stat">
                            <label>KOMPATIBILITÄT</label>
                            <span>{{ server.os.compatibility || 0 }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- OS Marketplace / Deployment Grid -->
            <div class="os-catalog-v3">
                <div class="section-header">
                    <h5>VERFÜGBARE_KERNELS</h5>
                    <div class="hw-warning-inline" v-if="server.status === 'online'">
                        <span>⚠️ SERVER_REBOOT_ERFORDERLICH</span>
                    </div>
                </div>

                <div class="os-grid-v3">
                    <div 
                        v-for="(def, key) in osCatalog" 
                        :key="key" 
                        class="os-release-card"
                        :class="{ active: server.os?.type === key }"
                    >
                        <div class="release-header">
                            <strong>{{ def.name }}</strong>
                            <span class="cost" v-if="def.license_cost > 0">${{ def.license_cost }} / m</span>
                            <span class="cost free" v-else>OPEN_SOURCE</span>
                        </div>
                        
                        <p class="release-desc">{{ def.description }}</p>
                        
                        <div class="release-specs">
                            <div class="spec-tag">🛡️ Security Base: {{ def.security_base }}</div>
                            <div class="spec-tag">⚡ Perf Multi: x{{ def.performance_mod }}</div>
                        </div>

                        <button 
                            @click="installOs(key)" 
                            class="btn-deployment" 
                            :disabled="server.os?.type === key || processing"
                        >
                            {{ server.os?.type === key ? 'AKTUELL' : 'DEPLOY_IMAGE' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    server: { type: Object, required: true },
    osCatalog: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

import api from '../../../../utils/api';

const getOsName = (type) => {
    return props.osCatalog[type]?.name || type;
};

const installOs = async (type) => {
    if (props.processing) return;
    if (!confirm(`${props.osCatalog[type]?.name || type} installieren? Bestehende Daten werden gelöscht.`)) return;
    
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/os/install`, { os_type: type });
        if (response.success) {
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

// -- Local Installation State Simulation --
const currentTime = ref(Date.now());
let timerInterval = null;

const kernelLines = [
    "[    0.000000] Linux version 6.5.0-rackora-v3 (root@build-server) (gcc (Debian 12.2.0-14) 12.2.0, GNU ld (GNU Binutils for Debian) 2.40)",
    "[    0.000000] Command line: BOOT_IMAGE=/boot/vmlinuz-6.5.0 root=/dev/sda1 ro quiet console=ttyS0",
    "[    0.021045] SMBIOS 3.3.0 present.",
    "[    0.021045] DMI: CodePony VirtualRack M4 Instance",
    "[    0.852941] TCP: Hash tables configured (established 8388608 bind 65536)",
    "[    1.492040] Freeing SMP alternatives memory: 44K",
    "[    2.102948] ACPI: Core revision 20230628",
    "[    2.839210] pci 0000:00:00.0: [8086:1237] type 00 class 0x060000",
    "[    3.482019] Initializing systemd-journald...",
    "[    4.192048] Starting provisioning agent...",
    "[    5.029193] Downloading system image from repository...",
    "[    8.192048] Extracting filesystem layers...",
    "[   12.492019] Applying kernel optimizations...",
    "[   15.102948] Configuring network stack...",
    "[   18.492019] Setting up driver modules...",
    "[   22.102948] Initializing bootstrap process...",
    "[   25.492019] Synchronizing entropy pool..."
];

const kernelLogs = ref([]);
const kernelLogIndex = ref(0);

const installProgress = computed(() => {
    if (!props.server?.os?.installCompletesAt || !props.server?.os?.installStartedAt) return 0;
    const start = new Date(props.server.os.installStartedAt).getTime();
    const end = new Date(props.server.os.installCompletesAt).getTime();
    return Math.min(100, Math.max(0, ((currentTime.value - start) / (end - start)) * 100));
});

const remainingSeconds = computed(() => {
    if (props.server?.os?.status !== 'installing' || !props.server?.os?.installCompletesAt) return 0;
    const end = new Date(props.server.os.installCompletesAt).getTime();
    return Math.max(0, Math.floor((end - currentTime.value) / 1000));
});

onMounted(() => {
    timerInterval = setInterval(() => {
        currentTime.value = Date.now();
        
        if (props.server?.os?.status === 'installing') {
            const totalSteps = kernelLines.length;
            const stepThreshold = 100 / totalSteps;
            const currentStep = Math.floor(installProgress.value / stepThreshold);
            
            if (currentStep > kernelLogIndex.value && kernelLogIndex.value < totalSteps) {
                const line = kernelLines[kernelLogIndex.value];
                kernelLogs.value.unshift(line);
                kernelLogIndex.value++;
            }
        } else {
            kernelLogs.value = [];
            kernelLogIndex.value = 0;
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});
</script>
