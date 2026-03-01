<template>
    <div class="tab-content summary-tab provision-lab">
        <div class="proc-header">
            <div class="proc-title">
                <h3>SYSTEM_ÜBERSICHT_NODE</h3>
                <p>Struktur-Analyse und Integritäts-Status für {{ server.nickname || server.modelName }}</p>
            </div>
        </div>

        <div class="summary-grid-v3">
            <div class="summary-group-v3">
                <label>{{ server.type === 'battery' ? 'BATTERIE_SPEZIFIKATIONEN' : 'HARDWARE_SPEZIFIKATIONEN' }}</label>
                <div class="spec-grid-v3" v-if="server.type === 'battery'">
                    <div class="v3-spec"><span class="l">KAPAZITÄT</span><strong>{{ server.battery?.capacity }}
                            kWh</strong></div>
                    <div class="v3-spec"><span class="l">LADESTAND</span><strong>{{ Math.round(server.battery?.percent
                        || 0) }}%</strong></div>
                    <div class="v3-spec"><span class="l">ZUSTAND (SoH)</span><strong>{{ Math.round(server.health)
                    }}%</strong></div>
                    <div class="v3-spec"><span class="l">ENTLADUNG (MAX)</span><strong>{{ ((server.battery?.capacity || 0) *
                        2).toFixed(1) }} kW</strong></div>
                    <div class="v3-spec"><span class="l">LEBENSDAUER</span><strong>{{ server.aging?.lifespan ||
                        'UNENDLICH' }}h</strong></div>
                    <div class="v3-spec"><span class="l">LEISTUNGSAUFNAHME</span><strong>{{ (server.effectivePower ||
                        0).toFixed(2) }} kW</strong></div>
                </div>
                <div class="spec-grid-v3" v-else>
                    <div class="v3-spec"><span class="l">GENERATION</span><strong class="gen-badge"
                            :class="`gen-${server.hardwareGeneration}`">GEN {{ server.hardwareGeneration }}</strong>
                    </div>
                    <div class="v3-spec"><span class="l">KERNE</span><strong>{{ server.specs?.cpuCores || 0 }}</strong>
                    </div>
                    <div class="v3-spec"><span class="l">SPEICHER</span><strong>{{ server.specs?.ramGb || 0 }}
                            GB</strong></div>
                    <div class="v3-spec"><span class="l">KAPAZITÄT</span><strong>{{ server.specs?.storageTb || 0 }}
                            TB</strong></div>
                    <div class="v3-spec"><span class="l">DURCHSATZ</span><strong>{{ server.specs?.bandwidthMbps || 0 }}
                            Mbps</strong></div>
                    <div class="v3-spec"><span class="l">LEISTUNGSAUFNAHME</span><strong>{{ (server.effectivePower ||
                        0).toFixed(2) }} kW</strong></div>
                </div>
            </div>

            <div class="summary-group-v3">
                <label>WARTUNGS_STATUS</label>
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
                                {{ server.health > 90 ? 'OPTIMAL' : (server.health > 50 ? 'DEGRADIERT' : 'KRITISCH') }}
                            </div>
                            <div v-if="server.currentFault" class="v3-fault-alert">
                                ⚠️ {{ server.currentFault }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="summary-row-v3" v-if="server.type === 'vserver_node' || server.type === 'shared_node'">
            <div class="v3-info-box">
                <label>{{ server.type === 'shared_node' ? 'SHARED_HOSTING_AUSLASTUNG' : 'VSERVER_INSTANZEN' }}</label>
                <div class="v3-capacity-inner">
                    <div class="v3-cap-text">
                        <span>{{ server.vserver?.used || 0 }} / {{ server.vserver?.capacity || 0 }} Slots belegt</span>
                        <strong>{{ (server.vserver?.capacity || 0) > 0 ? Math.round(((server.vserver?.used || 0) /
                            server.vserver.capacity) * 100) : 0 }}%</strong>
                    </div>
                    <div class="v3-progress-flat">
                        <div class="fill"
                            :style="{ width: (server.vserver?.capacity > 0 ? (server.vserver.used / server.vserver.capacity * 100) : 0) + '%' }">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="summary-grid-v3 secondary">
            <div class="summary-group-v3" v-if="server.aging">
                <label>HARDWARE_LEBENSZYKLUS</label>
                <div class="v3-lifecycle">
                    <div class="l-row"><span>Betriebszeit:</span> <strong>{{ formatRuntime(server.aging?.totalRuntime || 0)
                    }}</strong></div>
                    <div class="l-row"><span>Verschleiß-Level:</span> <strong
                            :class="getWearClass(server.aging?.wearPercentage || 0)">{{ (server.aging?.wearPercentage ||
                                0).toFixed(1) }}%</strong></div>
                    <div class="v3-progress-flat wear">
                        <div class="fill" :class="getWearClass(server.aging?.wearPercentage || 0)"
                            :style="{ width: (server.aging?.wearPercentage || 0) + '%' }"></div>
                    </div>
                </div>
            </div>

            <div class="summary-group-v3">
                <label>SICHERHEIT_&_COMPLIANCE</label>
                <div class="v3-security-list">
                    <div class="v3-sec-item" :class="{ 'warn': (server.os?.patch_level || 0) < 90 }">
                        <div class="i">🔒</div>
                        <div class="t">
                            <span>Patch-Level</span>
                            <strong>{{ server.os?.patch_level || 0 }}% geschützt</strong>
                        </div>
                    </div>
                    <div class="v3-sec-item"
                        :class="{ 'v-safe': server.activeOrdersCount > 0, 'idle': server.activeOrdersCount === 0 }">
                        <div class="i">🛡️</div>
                        <div class="t">
                            <span>Compliance-Tier</span>
                            <strong v-if="hasSensitiveData">💎 DIAMOND_ELITE</strong>
                            <strong v-else-if="server.activeOrdersCount > 0">✅ BUSINESS_STANDARD</strong>
                            <strong v-else>⚪ KEIN_AKTIVER_AUFTRAG</strong>
                        </div>
                    </div>
                    <div class="sec-tags-v3">
                        <span class="v3-tag" v-if="(server.os?.patch_level || 0) >= 95">✅ SOC2/ISO</span>
                        <span class="v3-tag warn" v-else>⚠️ UNZERTIFIZIERT</span>
                        <span class="v3-tag danger" v-if="hasSensitiveData"
                            title="Dieses System unterliegt strengen Datenvernichtungsrichtlinien.">☣️
                            SENSITIVE_DATA</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
defineProps({
    server: { type: Object, required: true },
    healthClass: { type: String, required: true },
    formatRuntime: { type: Function, required: true },
    getWearClass: { type: Function, required: true }
});

const hasSensitiveData = computed(() => {
    // Check if any active orders are Diamond or Enterprise
    // For now, simpler: if it has high compliance requirements
    return props.server.activeOrders?.some(o => o.sla_tier === 'whale' || o.sla_tier === 'diamond' || o.sla_tier === 'enterprise') || false;
});
</script>
