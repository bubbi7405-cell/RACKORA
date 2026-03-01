<template>
    <div class="tab-content networking-tab provision-lab">
        <div class="proc-header">
            <div class="proc-title">
                <h3>NETZWERK_TOPOLOGIE</h3>
                <p>Konfiguration der VPC-Paritionierung und Interface-Telemetrie.</p>
            </div>
        </div>

        <div class="v3-info-box">
            <label>VLAN_PEERING_STATUS</label>
            <div class="vpc-visualizer-card-v3">
                 <div v-if="server.networking?.privateNetworkId" class="vlan-active-state">
                     <div class="v3-node-map">
                         <div class="node server">
                             <div class="icon">🖥️</div>
                             <div class="meta">
                                 <strong>{{ server.nickname || 'LOCAL_NODE' }}</strong>
                                 <span>{{ server.networking.privateIp }}</span>
                             </div>
                         </div>
                         <div class="peering-line active">
                             <div class="pulse"></div>
                         </div>
                         <div class="node vpc">
                             <div class="icon">☁️</div>
                             <div class="meta">
                                 <strong>{{ getNetworkName(server.networking.privateNetworkId) }}</strong>
                                 <span>VPC_GATEWAY</span>
                             </div>
                         </div>
                     </div>
                     <div class="v3-actions-row" style="margin-top: 20px;">
                         <button class="btn-danger-v3-sm" @click="detachFromNetwork" :disabled="processing">PEERING_TERMINIEREN</button>
                     </div>
                 </div>
                 <div v-else class="vlan-empty-state">
                     <div class="v3-node-map inactive">
                         <div class="node server">🖥️</div>
                         <div class="peering-line"></div>
                         <div class="node vpc">☁️</div>
                     </div>
                     <div class="vpc-selection-v3">
                         <select v-model="selectedNetworkId" class="v3-select-sm">
                             <option value="" disabled>VLAN_WÄHLEN...</option>
                             <option v-for="net in netStore.privateNetworks" :key="net.id" :value="net.id">
                                 {{ net.name }}
                             </option>
                         </select>
                         <button class="btn-primary-v3-sm" @click="attachToNetwork" :disabled="!selectedNetworkId || processing">
                             PEERING_INITIALISIEREN
                         </button>
                     </div>
                 </div>
            </div>
        </div>

        <div class="v3-info-box" style="margin-top: 25px;">
            <label>INTERFACE_TELEMETRIE_L3</label>
            <div class="v3-hw-grid">
                <div class="v3-hw-slot active">
                    <div class="v3-icon">🌐</div>
                    <div class="slot-info">
                        <div class="n">WAN_ETH0 (PUBLIC)</div>
                        <div class="v">{{ server.ip_address ?? '185.22.41.9' }}</div>
                    </div>
                    <div class="port-telemetry">
                        <div class="t-val">{{ server.currentBandwidth || 0 }} Mbps</div>
                        <div class="v3-progress-flat small primary">
                            <div class="fill" :style="{ width: Math.min(100, ((server.currentBandwidth || 0) / (server.specs?.bandwidthMbps || 1)) * 100) + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div class="v3-hw-slot" :class="{ 'active': server.networking?.privateNetworkId }">
                    <div class="v3-icon">🔗</div>
                    <div class="slot-info">
                        <div class="n">LAN_ETH1 (VPC)</div>
                        <div class="v">{{ server.networking?.privateIp || 'DISCONNECTED' }}</div>
                    </div>
                    <div class="port-telemetry" v-if="server.networking?.privateNetworkId">
                        <div class="t-val">VPC_ACTIVE</div>
                        <div class="v3-progress-flat small accent">
                            <div class="fill" style="width: 25%"></div>
                        </div>
                    </div>
                </div>

                <div class="v3-hw-slot active">
                    <div class="v3-icon">🛡️</div>
                    <div class="slot-info">
                        <div class="n">LOM_MGMT (OOB)</div>
                        <div class="v">SECURE_TUNNEL_UP</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useNetworkStore } from '../../../../stores/network';

const props = defineProps({
    server: { type: Object, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

const netStore = useNetworkStore();
const selectedNetworkId = ref('');

const getNetworkName = (id) => {
    const net = netStore.privateNetworks.find(n => n.id === id);
    return net ? `${net.name} (VLAN ${net.vlanTag})` : 'Unknown Network';
};

const attachToNetwork = async () => {
    if (!selectedNetworkId.value || props.processing) return;
    emit('processing-start');
    try {
        const success = await netStore.attachServerToNetwork(selectedNetworkId.value, props.server.id);
        if (success) {
            emit('reload');
        }
        selectedNetworkId.value = '';
    } finally {
        emit('processing-end');
    }
};

const detachFromNetwork = async () => {
    if (props.processing) return;
    if (!confirm('Server vom privaten Netzwerk trennen? Die Verbindung zu anderen Knoten geht verloren.')) return;
    emit('processing-start');
    try {
        const success = await netStore.detachServerFromNetwork(props.server.id);
        if (success) {
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

onMounted(() => {
    if (!netStore.networksLoaded) {
        netStore.loadPrivateNetworks();
    }
});
</script>
