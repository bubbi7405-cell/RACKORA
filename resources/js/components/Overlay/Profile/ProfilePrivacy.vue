<template>
    <div class="panel-privacy">
        <div class="privacy-header">
            <h3>Data Custody & Privacy</h3>
            <p>Full control over your digital footprint and node telemetry.</p>
        </div>

        <div class="privacy-grid">
            <!-- Data Export -->
            <div class="privacy-card">
                <div class="card-title">
                    <span class="icon">📦</span>
                    <h4>Personal Data Vault</h4>
                </div>
                <p class="desc">Download a complete snapshot of your node's state, history, and executive configuration in JSON format.</p>
                <button class="export-btn" @click="exportData" :disabled="exporting">
                    {{ exporting ? 'COMPILING VAULT...' : 'REQUEST DATA EXPORT' }}
                </button>
                <div class="hint">Adheres to Global Data Sovereignty Standards.</div>
            </div>

            <!-- Transparency -->
            <div class="privacy-card">
                <div class="card-title">
                    <span class="icon">👁️</span>
                    <h4>System Transparency</h4>
                </div>
                <div class="control-row">
                    <div class="c-info">
                        <label>Public Profile</label>
                        <p>Allow other nodes to view your rank and stats.</p>
                    </div>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="profilePublic" @change="save('public_profile')">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="control-row">
                    <div class="c-info">
                        <label>Anonymous Telemetry</label>
                        <p>Share system metrics for global balance analysis.</p>
                    </div>
                    <label class="premium-switch">
                        <input type="checkbox" v-model="telemetryEnabled" @change="save('telemetry')">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="privacy-card danger-card">
                <div class="card-title">
                    <span class="icon">☣️</span>
                    <h4>Decommission Node</h4>
                </div>
                <p class="desc">Permanently erase all system data, active contracts, and credentials. This operation is irreversible and will result in total data loss.</p>
                
                <div v-if="!confirmingDelete" class="initial-delete">
                    <button class="delete-btn" @click="confirmingDelete = true">DECOMMISSION ACCOUNT</button>
                </div>
                
                <div v-else class="delete-form animation-shake">
                    <div class="form-group">
                        <label>CONFIRM ACCESS KEY</label>
                        <input type="password" v-model="deleteKey" placeholder="Current Password">
                    </div>
                    <div class="form-group checkbox">
                        <label class="check-container">
                            <input type="checkbox" v-model="deleteAgreed">
                            <span class="check-label">I acknowledge that all progress, purchases, and assets will be purged.</span>
                        </label>
                    </div>
                    <div class="delete-actions">
                        <button class="cancel-btn" @click="confirmingDelete = false">ABORT</button>
                        <button class="final-delete-btn" :disabled="!deleteKey || !deleteAgreed" @click="decommission">PURGE DATA</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../utils/api';
import { useToastStore } from '../../../stores/toast';

const toast = useToastStore();
const exporting = ref(false);
const profilePublic = ref(true);
const telemetryEnabled = ref(true);

const confirmingDelete = ref(false);
const deleteKey = ref('');
const deleteAgreed = ref(false);

async function load() {
    try {
        const res = await api.get('/profile');
        if (res.success) {
            profilePublic.value = res.data.preferences.public_profile !== false;
            telemetryEnabled.value = res.data.preferences.telemetry !== false;
        }
    } catch (e) {}
}

async function exportData() {
    exporting.value = true;
    try {
        const res = await api.post('/profile/export');
        if (res.success) {
             const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(res.data, null, 2));
             const downloadAnchorNode = document.createElement('a');
             downloadAnchorNode.setAttribute("href", dataStr);
             downloadAnchorNode.setAttribute("download", "system_node_vault_export.json");
             document.body.appendChild(downloadAnchorNode);
             downloadAnchorNode.click();
             downloadAnchorNode.remove();
             toast.success('Data vault compiled and download initiated.');
        }
    } catch (e) {
        toast.error('Export failed: ' + e.message);
    } finally {
        exporting.value = false;
    }
}

async function save(key) {
    try {
        const val = key === 'public_profile' ? profilePublic.value : telemetryEnabled.value;
        await api.post('/profile/preferences', { [key]: val });
    } catch (e) {}
}

async function decommission() {
    try {
        const res = await api.post('/profile/delete', { 
            password: deleteKey.value,
            confirm: deleteAgreed.value 
        });
        if (res.success) {
            window.location.reload();
        }
    } catch (e) {
        toast.error('Purge rejected: Invalid authorization key.');
    }
}

onMounted(load);
</script>

<style scoped>
.panel-privacy {
    animation: fadeIn 0.4s ease-out;
}

.privacy-header { margin-bottom: 30px; }
.privacy-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 5px; }
.privacy-header p { color: #71717a; font-size: 0.9rem; }

.privacy-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.privacy-card {
    background: #09090b;
    border: 1px solid #18181b;
    padding: 24px;
    border-radius: 16px;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-title h4 {
    margin: 0;
    font-weight: 800; font-size: 0.9rem; color: #fff;
}

.desc { font-size: 0.8rem; color: #71717a; line-height: 1.5; margin-bottom: 24px; }

.export-btn {
    width: 100%;
    background: #18181b;
    border: 1px solid #27272a;
    color: #fff;
    padding: 12px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
    margin-bottom: 12px;
}

.export-btn:hover { background: #27272a; }

.hint { font-size: 0.65rem; color: #52525b; text-align: center; }

.control-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.c-info label { display: block; font-size: 0.85rem; font-weight: 800; color: #fff; margin-bottom: 4px; }
.c-info p { font-size: 0.75rem; color: #71717a; margin: 0; }

.danger-card {
    grid-column: span 2;
    background: linear-gradient(to top right, rgba(239, 68, 68, 0.05), transparent);
    border-color: rgba(239, 68, 68, 0.2);
}

.delete-btn {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #ef4444;
    color: #ef4444;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}

.delete-form {
    background: rgba(239, 68, 68, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(239, 68, 68, 0.1);
}

.form-group label { display: block; font-size: 0.7rem; font-weight: 900; color: #ef4444; margin-bottom: 8px; }
.form-group input { width: 100%; background: #000; border: 1px solid #ef4444; color: #fff; padding: 10px; border-radius: 6px; }

.checkbox { margin: 15px 0; }
.check-container { display: flex; gap: 10px; cursor: pointer; }
.check-label { font-size: 0.75rem; color: #71717a; line-height: 1.4; }

.delete-actions { display: flex; gap: 10px; margin-top: 20px; }
.cancel-btn { flex: 1; background: #27272a; border: none; color: #fff; padding: 10px; border-radius: 6px; font-weight: 800; cursor: pointer; }
.final-delete-btn { flex: 2; background: #ef4444; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 800; cursor: pointer; }
.final-delete-btn:disabled { opacity: 0.3; cursor: not-allowed; }

@media (max-width: 800px) {
    .privacy-grid { grid-template-columns: 1fr; }
    .danger-card { grid-column: span 1; }
}
</style>
