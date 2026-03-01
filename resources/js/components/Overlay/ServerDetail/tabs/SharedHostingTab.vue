<template>
    <div class="tab-content shared-hosting-tab provision-lab">
        <div class="proc-header">
            <div class="proc-title">
                <h3>SHARED_HOSTING_METRIKEN</h3>
                <p>Analyse der Instanz-Dichte und Resource-Oversubscription.</p>
            </div>
        </div>

        <div class="density-overview-v3">
            <div class="density-gauge-v3">
                <div class="gauge-ring">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="6" />
                        <circle cx="50" cy="50" r="45" fill="none" stroke="var(--v3-primary)" stroke-width="6" 
                            :stroke-dasharray="282 * ((server.vserver?.used || 0) / (server.vserver?.capacity || 1))"
                            stroke-dashoffset="0"
                            transform="rotate(-90 50 50)"
                            stroke-linecap="round" />
                    </svg>
                    <div class="gauge-data">
                        <strong>{{ Math.round(((server.vserver?.used || 0) / (server.vserver?.capacity || 1)) * 100) }}%</strong>
                        <span>AUSLASTUNG</span>
                    </div>
                </div>
            </div>
            <div class="density-stats-v3">
                <div class="dns-card">
                    <label>AKTIVE_INSTANZEN</label>
                    <strong>{{ server.vserver?.used || 0 }}</strong>
                </div>
                <div class="dns-card">
                    <label>GESAMT_KAPAZITÄT</label>
                    <strong>{{ server.vserver?.capacity || 0 }}</strong>
                </div>
                <div class="dns-card highlight">
                    <label>MANAGEMENT_LIZENZ</label>
                    <strong class="text-success">ULTRA_PRO_V3</strong>
                </div>
            </div>
        </div>

        <div class="v3-info-box warning" style="margin-top: 20px;">
            <label>HYPER-DENSE_OVERPROVISIONING</label>
            <p>Dieser Node ist für maximale Dichte konfiguriert. Kerne werden über hunderte Mikro-Container geteilt, um den Umsatz pro Höheneinheit (U) zu maximieren.</p>
            <div class="v3-feature-tags">
                <span class="v3-tag">⚡ 20:1 CPU Oversubscription</span>
                <span class="v3-tag">📦 Dynamic RAM Consolidation</span>
                <span class="v3-tag">📉 Low-Margin / High-Volume</span>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    server: { type: Object, required: true }
});
</script>
