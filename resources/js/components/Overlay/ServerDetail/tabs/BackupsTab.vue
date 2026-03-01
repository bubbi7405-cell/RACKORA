<template>
    <div class="tab-content backups-tab provision-lab">
        <div class="proc-header">
            <div class="proc-title">
                <h3>DATENSICHERUNG_&_REDUNDANZ</h3>
                <p>Konfiguration der automatisierten Snapshot-Zyklen und Recovery-SLAs.</p>
            </div>
        </div>

        <div class="v3-backup-dashboard">
            <div class="v3-status-card" :class="server.backup.plan">
                <div class="status-icon">🛡️</div>
                <div class="status-info">
                    <label>SICHERUNGS_STATUS</label>
                    <strong>{{ server.backup.plan === 'none' ? 'UNPROTECTED' : server.backup.plan.toUpperCase() + '_REDUNDANCY' }}</strong>
                </div>
            </div>
            
            <div class="v3-backup-metrics">
                <div class="v3-metric">
                    <span>Letzter Sync</span>
                    <strong>{{ server.backup.lastBackupAt ? formatRuntime( (Date.now() - new Date(server.backup.lastBackupAt).getTime()) / 1000 ) + ' her' : 'Nie' }}</strong>
                </div>
                <div class="v3-metric">
                    <span>Archiv-Integrität</span>
                    <strong :class="server.backup.health < 95 ? 'text-warning' : 'text-success'">{{ Math.round(server.backup.health) }}%</strong>
                </div>
            </div>
        </div>

        <div class="v3-info-box" style="margin-top: 30px; border-color: #ffd700;" v-if="server.status === 'damaged' || server.status === 'offline'">
            <label style="color: #ffd700;">TEMPORAL_OPS (FEATURE 275)</label>
            <p class="setting-desc">Notfall-Rollback durchführen. Setzt das System auf den letzten intakten Zustand zurück.<br>Hardware-Ersatz wird umgangen. Kostet -50 Reputation.</p>
            <button class="btn-cancel-v3" style="background: #b8860b; color: #fff; border-color: #ffd700; border-width: 1px;" 
                    @click="rollbackBackup" 
                    :disabled="server.backup.health <= 0 || processing">
                SYSTEM_ZURÜCKSETZEN_(-50 REP)
            </button>
        </div>

        <div class="v3-plan-selector" style="margin-top: 30px;">
            <label>SLA_LEVEL_DEFINITION</label>
            <div class="v3-plan-grid">
                <div 
                    v-for="plan in backupPlans" 
                    :key="plan.id"
                    class="v3-plan-card"
                    :class="{ active: server.backup.plan === plan.id, 'none': plan.id === 'none', 'disabled': processing }"
                    @click="updateBackupPlan(plan.id)"
                >
                    <div class="p-head">
                        <span class="p-name">{{ plan.name }}</span>
                        <span class="p-cost">{{ plan.cost }}</span>
                    </div>
                    <p class="p-desc">{{ plan.description }}</p>
                    <div class="p-footer">
                        <span>Recovery: {{ plan.chance }}%</span>
                        <div class="p-indicator" :style="{ width: plan.chance + '%' }"></div>
                    </div>
                </div>
            </div>
            <p class="v3-disclaimer">
                * Die laufenden Kosten werden sofort angepasst. Sicherungen erfolgen im laufenden Betrieb.
            </p>
        </div>
    </div>
</template>

<script setup>
import api from '../../../../utils/api';
import { useGameStore } from '../../../../stores/game';

const props = defineProps({
    server: { type: Object, required: true },
    formatRuntime: { type: Function, required: true },
    processing: { type: Boolean, default: false }
});

const emit = defineEmits(['processing-start', 'processing-end', 'reload']);

const gameStore = useGameStore();

const updateBackupPlan = async (planId) => {
    if (props.processing) return;
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/backup-plan`, { plan: planId });
        if (response.success) {
            gameStore.loadGameState();
            emit('reload');
        }
    } finally {
        emit('processing-end');
    }
};

const rollbackBackup = async () => {
    if (props.processing) return;
    if (!confirm('!!! TEMPORAL OPS !!!\nMöchten Sie wirklich ein Disaster Snapshot Rollback durchführen?\nDies löscht den aktuellen fehlerhaften Zustand und lädt das letzte Snapshot, was -50 Reputation kostet.')) return;
    emit('processing-start');
    try {
        const response = await api.post(`/server/${props.server.id}/rollback`);
        if (response.success) {
            alert(response.message);
            gameStore.loadGameState();
            emit('reload');
        }
    } catch (e) {
        if (e.response && e.response.data) alert(e.response.data.message);
    } finally {
        emit('processing-end');
    }
};

const backupPlans = [
    { id: 'none', name: 'None', cost: 'Free', chance: 0, description: 'No protection. High risk of data loss on failure.' },
    { id: 'daily', name: 'Standard (Daily)', cost: '$0.05/hr', chance: 60, description: 'Daily snapshots. Good for non-critical workloads.' },
    { id: 'hourly', name: 'Premium (Hourly)', cost: '$0.25/hr', chance: 98, description: 'Frequent snapshots. Essential for high-uptime services.' },
    { id: 'tape', name: 'Tape Archive (F88)', cost: '$0.50/hr', chance: 100, description: 'Physical magnetic tape backup. Immune to digital corruption.' }
];
</script>
