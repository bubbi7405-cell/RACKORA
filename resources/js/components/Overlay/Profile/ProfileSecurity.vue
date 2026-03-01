<template>
    <div class="panel-security">
        <div class="security-header">
            <h3>System Security</h3>
            <p>Maintain the integrity of your executive access node.</p>
        </div>

        <div class="security-grid">
            <!-- 2FA -->
            <div class="security-card">
                <div class="card-title">
                     <span class="icon">🛂</span>
                     <h4>Multi-Factor Authentication</h4>
                </div>
                <p class="desc">Encrypted second-step verification for administrative operations.</p>
                <div class="status-box" :class="{ enabled: user?.two_factor_enabled }">
                    <span class="status-dot"></span>
                    <span class="status-label">{{ user?.two_factor_enabled ? 'SECURE' : 'UNPROTECTED' }}</span>
                </div>
                <button class="action-btn" :class="{ danger: user?.two_factor_enabled }" @click="toggle2FA">
                    {{ user?.two_factor_enabled ? 'TERMINATE 2FA' : 'INITIALIZE 2FA' }}
                </button>
            </div>

            <!-- Active Sessions -->
            <div class="security-card sessions-card">
                <div class="card-title">
                     <span class="icon">🖥️</span>
                     <h4>Administrative Sessions</h4>
                </div>
                <div class="sessions-v-list v-scrollbar">
                    <div v-for="sess in sessions" :key="sess.id" class="session-node">
                        <div class="node-info">
                            <div class="node-name">
                                {{ sess.name || 'Terminal Session' }}
                                <span v-if="sess.is_current" class="current-tag">CURRENT NODE</span>
                            </div>
                            <div class="node-meta">
                                <span>IP: {{ sess.ip || '255.255.255.0' }}</span>
                                <span>ACTIVE: {{ formatDate(sess.last_used_at) }}</span>
                            </div>
                        </div>
                        <button v-if="!sess.is_current" class="revoke-btn" @click="revoke(sess.id)">&times;</button>
                    </div>
                </div>
            </div>

            <!-- Login History / Audit -->
            <div class="security-card audit-card">
                <div class="card-title">
                     <span class="icon">📜</span>
                     <h4>Security Audit Log</h4>
                </div>
                <div class="audit-v-list v-scrollbar">
                    <div v-for="log in logs" :key="log.id" class="audit-entry">
                        <span class="entry-time">{{ formatTime(log.created_at) }}</span>
                        <span class="entry-msg">{{ log.message }}</span>
                        <span class="entry-status" :class="log.type">{{ log.type?.toUpperCase() }}</span>
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

const props = defineProps(['user']);
const toast = useToastStore();
const sessions = ref([]);
const logs = ref([]);

async function loadData() {
    try {
        const sRes = await api.get('/profile/sessions');
        if (sRes.success) sessions.value = sRes.sessions;

        const pRes = await api.get('/profile');
        if (pRes.success) logs.value = pRes.data.security_logs;
    } catch (e) {
        console.error(e);
    }
}

async function revoke(id) {
    if (!confirm('Terminate this session node?')) return;
    try {
        await api.delete(`/profile/sessions/${id}`);
        loadData();
        toast.info('Session node terminated.');
    } catch (e) {
        toast.error('Revocation failed: ' + e.message);
    }
}

function toggle2FA() {
    toast.warning('2FA setup requires external authenticator app. This feature is currently in sandboxed test mode.');
}

function formatDate(date) {
    if (!date) return 'Recently';
    return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatTime(date) {
    if (!date) return '--:--';
    return new Date(date).toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
}

onMounted(loadData);
</script>

<style scoped>
.panel-security {
    animation: fadeIn 0.4s ease-out;
}

.security-header { margin-bottom: 30px; }
.security-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 5px; }
.security-header p { color: #71717a; font-size: 0.9rem; }

.security-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.security-card {
    background: #09090b;
    border: 1px solid #18181b;
    padding: 24px;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-title h4 {
    margin: 0;
    font-weight: 800;
    font-size: 0.9rem;
    color: #fff;
}

.desc { font-size: 0.8rem; color: #71717a; line-height: 1.5; margin-bottom: 20px; }

.status-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    width: fit-content;
}

.status-box.enabled {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.status-label { font-size: 0.75rem; font-weight: 900; letter-spacing: 0.05em; }

.action-btn {
    background: #fff;
    color: #000;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
}

.action-btn.danger { background: #ef4444; color: #fff; }

.sessions-card { grid-row: span 2; }

.sessions-v-list {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 400px;
}

.session-node {
    background: #111;
    border: 1px solid #18181b;
    padding: 12px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.node-name { font-size: 0.85rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
.current-tag { font-size: 0.6rem; background: #3b82f6; color: #fff; padding: 2px 6px; border-radius: 4px; }
.node-meta { display: flex; gap: 10px; margin-top: 5px; font-size: 0.7rem; color: #52525b; }

.revoke-btn {
    background: none;
    border: none;
    color: #71717a;
    font-size: 1.2rem;
    cursor: pointer;
}

.revoke-btn:hover { color: #ef4444; }

.audit-card { grid-column: span 1; }

.audit-v-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 200px;
}

.audit-entry {
    display: flex;
    gap: 10px;
    font-size: 0.75rem;
    padding: 4px 0;
    border-bottom: 1px dashed #18181b;
}

.entry-time { color: #52525b; font-family: monospace; }
.entry-msg { flex: 1; color: #a1a1aa; }
.entry-status { font-weight: 800; font-size: 0.6rem; }
.entry-status.info { color: #3b82f6; }
.entry-status.warn { color: #fbbf24; }

@media (max-width: 800px) {
    .security-grid { grid-template-columns: 1fr; }
}
</style>
