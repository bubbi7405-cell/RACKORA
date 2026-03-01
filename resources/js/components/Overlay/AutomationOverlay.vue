<template>
    <div class="overlay-backdrop" @click.self="$emit('close')">
        <div class="automation-overlay glass-panel animation-slide-up">
            <div class="overlay-header">
                <div class="header-title">
                    <span class="icon-pulsing">🤖</span>
                    <h2>Automation & Services</h2>
                </div>
                <div class="header-tabs">
                    <button class="header-tab" :class="{ active: activeTab === 'system' }" @click="activeTab = 'system'">System</button>
                    <button class="header-tab" :class="{ active: activeTab === 'apis' }" @click="activeTab = 'apis'">Virtual APIs</button>
                </div>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </div>
            
            <div class="overlay-body">
                <!-- SYSTEM AUTOMATION TAB -->
                <div v-if="activeTab === 'system'" class="tab-content">
                    <div class="system-status">
                        <div class="status-indicator">
                            <span class="dot" :class="{ 'dot--active': activeModulesCount > 0 }"></span>
                            {{ activeModulesCount }} / 4 Modules Operational
                        </div>
                    </div>

                    <div class="automation-grid">
                        <!-- Auto Reboot -->
                        <div class="module-card" :class="{ 'module-card--active': settings.auto_reboot }">
                            <div class="module-header">
                                <div class="module-icon">⚡</div>
                                <div class="module-info">
                                    <h3>Neural Rebooter</h3>
                                    <p>Monitors kernel status and forces hardware reboots on critical failure detection.</p>
                                </div>
                            </div>
                            <div class="module-controls">
                                <div class="status-label">{{ settings.auto_reboot ? 'SYSTEM ONLINE' : 'STANDBY' }}</div>
                                <label class="premium-switch">
                                    <input type="checkbox" :checked="settings.auto_reboot" @change="toggle('auto_reboot', $event.target.checked)" :disabled="processing">
                                    <span class="premium-slider"></span>
                                </label>
                            </div>
                            <div v-if="settings.auto_reboot" class="module-scanline"></div>
                        </div>

                        <!-- Auto Provisioning -->
                        <div class="module-card" :class="{ 'module-card--active': settings.auto_provisioning }">
                            <div class="module-header">
                                <div class="module-icon">📦</div>
                                <div class="module-info">
                                    <h3>Smart Provisioner</h3>
                                    <p>Automated workload distribution. Assigns incoming contracts to optimal infrastructure.</p>
                                </div>
                            </div>
                            <div class="module-controls">
                                <div class="status-label">{{ settings.auto_provisioning ? 'SYSTEM ONLINE' : 'STANDBY' }}</div>
                                <label class="premium-switch">
                                    <input type="checkbox" :checked="settings.auto_provisioning" @change="toggle('auto_provisioning', $event.target.checked)" :disabled="processing">
                                    <span class="premium-slider"></span>
                                </label>
                            </div>
                            <div v-if="settings.auto_provisioning" class="module-scanline"></div>
                        </div>

                        <!-- Auto-Cleanup -->
                        <div class="module-card" :class="{ 
                            'module-card--active': settings.auto_cleanup, 
                            'module-card--locked': !gameStore.isResearched('auto_cleanup') 
                        }">
                            <div class="module-header">
                                <div class="module-icon">🧹</div>
                                <div class="module-info">
                                    <h3>Garbage Collector</h3>
                                    <p>Automated termination of expired or non-compliant service contracts. (Requires Research)</p>
                                </div>
                            </div>
                            <div class="module-controls">
                                <template v-if="gameStore.isResearched('auto_cleanup')">
                                    <div class="status-label">{{ settings.auto_cleanup ? 'SYSTEM ONLINE' : 'STANDBY' }}</div>
                                    <label class="premium-switch">
                                        <input type="checkbox" :checked="settings.auto_cleanup" @change="toggle('auto_cleanup', $event.target.checked)" :disabled="processing">
                                        <span class="premium-slider"></span>
                                    </label>
                                </template>
                                <template v-else>
                                    <div class="status-label">LOCKED</div>
                                    <div class="lock-icon">🔒</div>
                                </template>
                            </div>
                            <div v-if="settings.auto_cleanup" class="module-scanline"></div>
                        </div>

                        <!-- Cooling Automation -->
                        <div class="module-card" :class="{ 
                            'module-card--active': settings.cooling_automation, 
                            'module-card--locked': !gameStore.isResearched('cooling_automation') 
                        }">
                            <div class="module-header">
                                <div class="module-icon">❄️</div>
                                <div class="module-info">
                                    <h3>Adaptive Thermal Governor</h3>
                                    <p>Dynamically adjusts cooling intensity to maintain optimal temperatures while minimizing power costs.</p>
                                </div>
                            </div>
                            <div class="module-controls">
                                <template v-if="gameStore.isResearched('cooling_automation')">
                                    <div class="status-label">{{ settings.cooling_automation ? 'SYSTEM ONLINE' : 'STANDBY' }}</div>
                                    <label class="premium-switch">
                                        <input type="checkbox" :checked="settings.cooling_automation" @change="toggle('cooling_automation', $event.target.checked)" :disabled="processing">
                                        <span class="premium-slider"></span>
                                    </label>
                                </template>
                                <template v-else>
                                    <div class="status-label">LOCKED</div>
                                    <div class="lock-icon">🔒</div>
                                </template>
                            </div>
                            <div v-if="settings.cooling_automation" class="module-scanline"></div>
                        </div>
                    </div>
                </div>

                <!-- API SIMULATION TAB -->
                <div v-if="activeTab === 'apis'" class="tab-content api-simulation">
                    <div class="api-header">
                        <div class="api-stats">
                            <div class="stat">
                                <span class="label">ACTIVE ENDPOINTS</span>
                                <span class="value">{{ endpoints.length }}</span>
                            </div>
                            <div class="stat">
                                <span class="label">TOTAL TRAFFIC</span>
                                <span class="value">{{ totalRpm.toLocaleString() }} RPM</span>
                            </div>
                        </div>
                        <button class="premium-btn" @click="showCreateApi = true">+ Deploy Endpoint</button>
                    </div>

                    <div class="endpoint-list">
                        <div v-for="ep in endpoints" :key="ep.id" class="endpoint-card" :class="`ep--${ep.status}`">
                            <div class="ep-method" :class="ep.method">{{ ep.method }}</div>
                            <div class="ep-info">
                                <div class="ep-path">{{ ep.path }}</div>
                                <div class="ep-server">Host: {{ ep.serverId ? 'Server Instance' : 'Unassigned' }}</div>
                            </div>
                            <div class="ep-metrics">
                                <div class="metric">
                                    <span class="m-val">{{ ep.rpm }}</span>
                                    <span class="m-lab">RPM</span>
                                </div>
                                <div class="metric">
                                    <span class="m-val">{{ ep.latency }}ms</span>
                                    <span class="m-lab">Latency</span>
                                </div>
                                <div class="metric">
                                    <span class="m-val">{{ ep.uptime }}%</span>
                                    <span class="m-lab">Uptime</span>
                                </div>
                            </div>
                            <div class="ep-actions">
                                <button class="delete-btn" @click="deleteEndpoint(ep.id)">×</button>
                            </div>
                        </div>

                        <div v-if="endpoints.length === 0" class="empty-state">
                            <div class="empty-icon">🌐</div>
                            <h3>No Active Endpoints</h3>
                            <p>Deploy virtual API endpoints to generate automated traffic and revenue.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Endpoint Modal -->
            <div v-if="showCreateApi" class="sub-modal-backdrop" @click.self="showCreateApi = false">
                <div class="sub-modal glass-panel">
                    <h3>Deploy New Service</h3>
                    <div class="form-group">
                        <label>Target Server</label>
                        <select v-model="newApi.server_id">
                            <option v-for="server in onlineServers" :key="server.id" :value="server.id">
                                {{ server.model_name || 'Server' }} ({{ server.cpu_usage }}% CPU)
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API Path</label>
                        <input type="text" v-model="newApi.path" placeholder="/v1/auth">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Method</label>
                            <select v-model="newApi.method">
                                <option>GET</option>
                                <option>POST</option>
                                <option>PUT</option>
                                <option>DELETE</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Complexity</label>
                            <select v-model="newApi.complexity">
                                <option value="low">Low (1x Rev)</option>
                                <option value="medium">Medium (2x Rev)</option>
                                <option value="high">High (5x Rev)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="secondary-btn" @click="showCreateApi = false">Cancel</button>
                        <button class="premium-btn" @click="createEndpoint" :disabled="!isFormValid">Initialize Host</button>
                    </div>
                </div>
            </div>

            <div class="overlay-footer">
                <div class="hint">
                    <span class="hint-icon">ℹ️</span>
                    {{ activeTab === 'system' ? 'Scripts execute at 60s intervals. No overhead costs required for current tier.' : 'API simulation revenue is automatically credited to your balance every minute.' }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';

const gameStore = useGameStore();
const toastStore = useToastStore();
const processing = ref(false);
const activeTab = ref('system');

// API Simulation State
const endpoints = ref([]);
const showCreateApi = ref(false);
const newApi = ref({
    server_id: '',
    path: '',
    method: 'GET',
    complexity: 'low'
});

const onlineServers = computed(() => {
    // Collect all servers that are online across all rooms
    const all = [];
    Object.values(gameStore.rooms).forEach(room => {
        room.racks?.forEach(rack => {
            rack.servers?.forEach(server => {
                if (server.status === 'online') all.push(server);
            });
        });
    });
    return all;
});

const totalRpm = computed(() => endpoints.value.reduce((sum, ep) => sum + ep.rpm, 0));
const isFormValid = computed(() => newApi.value.server_id && newApi.value.path);

const fetchEndpoints = async () => {
    try {
        const res = await api.get('/api-simulation');
        if (res.success) {
            endpoints.value = res.data;
        }
    } catch (e) {
        console.error("Failed to fetch endpoints", e);
    }
};

const createEndpoint = async () => {
    processing.value = true;
    try {
        const res = await api.post('/api-simulation', newApi.value);
        if (res.success) {
            toastStore.success(`Endpoint ${newApi.value.path} deployed successfully.`);
            showCreateApi.value = false;
            fetchEndpoints();
        }
    } catch (e) {
        toastStore.error("Hardware configuration rejected. Check server state.");
    } finally {
        processing.value = false;
    }
};

const deleteEndpoint = async (id) => {
    try {
        const res = await api.delete(`/api-simulation/${id}`);
        if (res.success) {
            fetchEndpoints();
            toastStore.info("Service decommissioned.");
        }
    } catch (e) {
        toastStore.error("Failed to decommissioning service.");
    }
};

onMounted(() => {
    fetchEndpoints();
});

const settings = computed(() => gameStore.player?.economy?.automation || {});
const activeModulesCount = computed(() => {
    let count = 0;
    if (settings.value.auto_reboot) count++;
    if (settings.value.auto_provisioning) count++;
    if (settings.value.auto_cleanup) count++;
    if (settings.value.cooling_automation) count++;
    return count;
});

const toggle = async (key, value) => {
    if (processing.value) return;
    processing.value = true;
    
    try {
        const response = await api.post('/automation/toggle', { key, value });
        if (response.success) {
            gameStore.player.economy.automation = response.settings;
            toastStore.info(`Module ${key.replace('_', ' ').toUpperCase()} ${value ? 'Engaged' : 'Disengaged'}`);
        }
    } catch (e) {
        console.error('Failed to toggle automation', e);
        toastStore.error('System synchronization failed');
    } finally {
        processing.value = false;
    }
};
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.automation-overlay {
    width: 800px;
    max-width: 95vw;
    border-radius: 24px;
    display: flex;
    flex-direction: column;
    color: #fff;
    overflow: hidden;
    border: 1px solid rgba(0, 242, 255, 0.1);
    box-shadow: 0 0 80px rgba(0, 242, 255, 0.05);
}

.overlay-header {
    padding: 20px 40px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-tabs {
    display: flex;
    gap: 10px;
    background: rgba(0,0,0,0.3);
    padding: 4px;
    border-radius: 12px;
}

.header-tab {
    background: none; border: none; padding: 8px 20px;
    color: #8b949e; cursor: pointer; border-radius: 8px;
    font-size: 0.85rem; font-weight: 700; transition: 0.2s;
}

.header-tab.active {
    background: rgba(0, 242, 255, 0.1);
    color: #00f2ff;
}

/* API SIMULATION STYLES */
.api-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 30px;
}

.api-stats { display: flex; gap: 40px; }
.api-stats .stat { display: flex; flex-direction: column; }
.api-stats .label { font-size: 0.65rem; color: #484f58; font-weight: 800; }
.api-stats .value { font-size: 1.4rem; font-weight: 300; font-family: var(--font-family-mono); }

.endpoint-list { display: flex; flex-direction: column; gap: 12px; }

.endpoint-card {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; gap: 20px;
    transition: 0.2s;
}

.endpoint-card:hover { background: rgba(255,255,255,0.04); border-color: rgba(0, 242, 255, 0.2); }

.ep-method {
    font-size: 0.7rem; font-weight: 900; padding: 4px 8px; border-radius: 4px;
    min-width: 60px; text-align: center;
}
.ep-method.GET { background: rgba(76, 175, 80, 0.1); color: #4caf50; }
.ep-method.POST { background: rgba(33, 150, 243, 0.1); color: #2196f3; }
.ep-method.PUT { background: rgba(255, 152, 0, 0.1); color: #ff9800; }
.ep-method.DELETE { background: rgba(244, 67, 54, 0.1); color: #f44336; }

.ep-info { flex: 1; }
.ep-path { font-size: 1rem; font-weight: 700; font-family: var(--font-family-mono); }
.ep-server { font-size: 0.75rem; color: #484f58; }

.ep-metrics { display: flex; gap: 25px; }
.metric { display: flex; flex-direction: column; align-items: center; min-width: 60px; }
.m-val { font-size: 0.9rem; font-weight: 700; color: #fff; }
.m-lab { font-size: 0.6rem; color: #484f58; text-transform: uppercase; }

.ep-actions .delete-btn {
    background: none; border: none; color: #484f58; font-size: 1.4rem;
    cursor: pointer; transition: 0.2s;
}
.ep-actions .delete-btn:hover { color: #f44336; }

/* Sub Modal */
.sub-modal-backdrop {
    position: absolute; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
    display: flex; align-items: center; justify-content: center; z-index: 100;
}
.sub-modal { width: 400px; padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
.sub-modal h3 { margin-top: 0; margin-bottom: 25px; letter-spacing: 1px; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 0.75rem; color: #8b949e; margin-bottom: 8px; text-transform: uppercase; }
.form-group input, .form-group select {
    width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
    color: #fff; padding: 12px; border-radius: 8px; outline: none;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

.modal-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; }
.secondary-btn { background: none; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
.premium-btn { background: #00f2ff; border: none; color: #000; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; }
.premium-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.empty-state { text-align: center; padding: 60px 0; color: #484f58; }
.empty-icon { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }
.empty-state h3 { color: #8b949e; margin-bottom: 5px; }

.header-title h2 {
    margin: 0;
    font-size: 1.5rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 800;
    background: linear-gradient(to right, #00f2ff, #006aff);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.icon-pulsing {
    font-size: 2.2rem;
    animation: icon-pulse 2s infinite ease-in-out;
}

@keyframes icon-pulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.1); opacity: 1; filter: drop-shadow(0 0 10px #00f2ff); }
}

.overlay-body {
    flex: 1;
    padding: 40px;
}

.system-status {
    margin-bottom: 30px;
    background: rgba(255, 255, 255, 0.03);
    padding: 12px 20px;
    border-radius: 40px;
    display: inline-flex;
    align-items: center;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.status-indicator {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: 10px;
}

.dot {
    width: 10px; height: 10px;
    background: #333;
    border-radius: 50%;
    position: relative;
}

.dot--active {
    background: #00ff88;
    box-shadow: 0 0 10px #00ff88;
}

.dot--active::after {
    content: '';
    position: absolute;
    top: -5px; left: -5px; right: -5px; bottom: -5px;
    border-radius: 50%;
    border: 2px solid #00ff88;
    animation: ping 1.5s infinite ease-out;
}

@keyframes ping {
    from { transform: scale(0.5); opacity: 1; }
    to { transform: scale(2); opacity: 0; }
}

.automation-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.module-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.module-card--active {
    background: rgba(0, 242, 255, 0.03);
    border-color: rgba(0, 242, 255, 0.3);
    box-shadow: inset 0 0 20px rgba(0, 242, 255, 0.05);
}

.module-card--locked {
    opacity: 0.6;
    filter: grayscale(0.5);
    background: rgba(0, 0, 0, 0.2);
}

.module-header {
    display: flex;
    gap: 25px;
    align-items: center;
}

.module-icon {
    width: 65px; height: 65px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.module-info h3 {
    margin: 0 0 6px 0;
    font-size: 1.2rem;
    letter-spacing: 1px;
}

.module-info p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--color-text-muted);
    max-width: 400px;
}

.module-controls {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}

.status-label {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 2px;
}

.module-card--active .status-label { color: #00ff88; }

/* Premium Switch */
.premium-switch {
    width: 60px; height: 30px;
    position: relative;
    cursor: pointer;
}

.premium-switch input { opacity: 0; width: 0; height: 0; }

.premium-slider {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 30px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: 0.3s;
}

.premium-slider::before {
    content: "";
    position: absolute;
    left: 4px; bottom: 4px;
    width: 20px; height: 20px;
    background: #fff;
    border-radius: 50%;
    transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
}

input:checked + .premium-slider {
    background: rgba(0, 242, 255, 0.2);
    border-color: rgba(0, 242, 255, 0.5);
}

input:checked + .premium-slider::before {
    transform: translateX(30px);
    background: #00f2ff;
    box-shadow: 0 0 15px #00f2ff;
}

.module-scanline {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 2px;
    background: rgba(0, 242, 255, 0.2);
    box-shadow: 0 0 10px #00f2ff;
    animation: scan 3s infinite linear;
    pointer-events: none;
}

@keyframes scan {
    from { transform: translateY(-10px); }
    to { transform: translateY(160px); }
}

.overlay-footer {
    padding: 20px 40px;
    background: rgba(0, 0, 0, 0.3);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.hint {
    font-size: 0.85rem;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: 10px;
}

.hint-icon { font-size: 1.1rem; }

.animation-slide-up {
    animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slide-up {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.locked {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--color-text-muted);
}
</style>
