<template>
    <div v-if="order" class="order-overlay">
        <div class="overlay-backdrop" @click="$emit('close')"></div>
        <div class="overlay-content">
            <header class="overlay-header">
                <h2>New Order: {{ order.productType }}</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="order-details">
                <div class="customer-info">
                    <span class="customer-avatar">🏢</span>
                    <div class="customer-text">
                        <h3>{{ order.customerName }}</h3>
                        <span class="customer-tier">{{ order.contractMonths }} month contract</span>
                    </div>
                </div>

                <div class="order-stats">
                    <div class="stat-group">
                        <label>Requirements</label>
                        <div class="requirements-grid">
                            <div class="req-item">
                                <span class="req-label">CPU</span>
                                <span class="req-value">{{ order.requirements.cpu }} Cores</span>
                            </div>
                            <div class="req-item">
                                <span class="req-label">RAM</span>
                                <span class="req-value">{{ order.requirements.ram }} GB</span>
                            </div>
                            <div class="req-item">
                                <span class="req-label">Storage</span>
                                <span class="req-value">{{ order.requirements.storage }} GB</span>
                            </div>
                            <div class="req-item" v-if="order.requirements.bandwidth">
                                <span class="req-label">Bandwidth</span>
                                <span class="req-value">{{ order.requirements.bandwidth }} Mbps</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-group revenue">
                        <label>Revenue</label>
                        <div class="revenue-value">${{ order.pricePerMonth.toFixed(2) }} <small>/mo</small></div>
                        <div class="revenue-sub">≈${{ (order.hourlyValue).toFixed(2) }} /hr</div>
                    </div>
                </div>

                <div class="timer-section">
                    <div class="timer-label">Offer expires in:</div>
                    <div class="timer-bar">
                        <div class="timer-fill" :style="{ width: order.patience.progress + '%' }"></div>
                    </div>
                    <div class="timer-text">{{ formatTime(order.patience.remainingSeconds) }}</div>
                </div>
            </div>

            <div class="server-selection">
                <h3>Select Server</h3>
                <div v-if="availableServers.length === 0" class="no-servers">
                    No suitable online servers available.
                </div>
                <div class="server-list" v-else>
                    <button 
                        v-for="server in availableServers" 
                        :key="server.id"
                        class="server-option"
                        :class="{ 'server-option--suitable': isSuitable(server), 'server-option--unsuitable': !isSuitable(server) }"
                        :disabled="!isSuitable(server)"
                        @click="acceptOrder(server.id)"
                    >
                        <div class="server-option__icon">🖥️</div>
                        <div class="server-option__info">
                            <div class="server-option__name">{{ server.modelName }}</div>
                            <div class="server-option__specs">
                                {{ server.specs.cpuCores }}C • {{ server.specs.ramGb }}GB • {{ server.specs.storageTb }}TB
                            </div>
                        </div>
                        <div class="server-option__action">
                            <span v-if="isSuitable(server)">Select</span>
                            <span v-else>Incapable</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="overlay-actions">
                <button class="btn btn--danger" @click="rejectOrder">Reject Order</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    }
});

const emit = defineEmits(['close']);
const gameStore = useGameStore();
const toastStore = useToastStore();

const availableServers = computed(() => {
    const servers = [];
    for (const roomId in gameStore.rooms) {
        const room = gameStore.rooms[roomId];
        if (room.racks) {
            for (const rack of room.racks) {
                if (rack.servers) {
                    for (const server of rack.servers) {
                        // Filters: Must be online, must be proper type (e.g. vserver node if VPS)
                        // For now, simplify: status must be online or 'provisioning'
                        if (server.status !== 'offline' && server.status !== 'damaged') {
                            servers.push(server);
                        }
                    }
                }
            }
        }
    }
    return servers;
});

function isSuitable(server) {
    if (server.status === 'offline') return false;
    
    // Check requirements
    const req = props.order.requirements;
    if (server.specs.cpuCores < req.cpu) return false;
    if (server.specs.ramGb < req.ram) return false;
    if (server.specs.storageTb * 1024 < req.storage) return false;

    // Check availability (if vserver node)
    if (server.type === 'vserver_node') {
        if (server.vserver.available <= 0) return false;
    } else {
        // Dedicated: must be empty/unused?
        // Current logic doesn't track "used" status for dedicated well yet, 
        // assuming dedicated = 1 order capacity for now (or locked)
        if (server.activeOrdersCount > 0) return false;
    }

    return true;
}

async function acceptOrder(serverId) {
    try {
        const response = await api.post(`/orders/${props.order.id}/accept`, {
            server_id: serverId
        });

        if (response.success) {
            toastStore.success('Order accepted!');
            await gameStore.loadGameState(); // Refresh
            emit('close');
        }
    } catch (error) {
        toastStore.error(error.message || 'Failed to accept order');
        if (error.message && (error.message.includes('pending') || error.message.includes('not found'))) {
            await gameStore.loadGameState();
            emit('close');
        }
    }
}

async function rejectOrder() {
    try {
        const response = await api.post(`/orders/${props.order.id}/reject`);
        
        if (response.success) {
            toastStore.info('Order rejected');
            await gameStore.loadGameState();
            emit('close');
        }
    } catch (error) {
        toastStore.error(error.message || 'Failed to reject order');
        if (error.message && (error.message.includes('pending') || error.message.includes('not found'))) {
            await gameStore.loadGameState();
            emit('close');
        }
    }
}

function formatTime(seconds) {
    if (seconds <= 0) return 'Expired';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<style scoped>
.order-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-backdrop {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.overlay-content {
    position: relative;
    width: 600px;
    max-width: 90%;
    max-height: 90vh;
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.overlay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    background: #0d1117;
    border-bottom: 1px solid #30363d;
}

.overlay-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #e6edf3;
    text-transform: capitalize;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #8b949e;
    cursor: pointer;
    padding: 4px;
}
.close-btn:hover { color: #e6edf3; }

.order-details {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
    background: #0d1117;
}

.customer-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.customer-avatar {
    font-size: 2.5rem;
    background: #21262d;
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.customer-text h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    color: #e6edf3;
}

.customer-tier {
    font-size: 0.9rem;
    color: #8b949e;
}

.order-stats {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    background: #161b22;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #30363d;
}

.stat-group label {
    display: block;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #8b949e;
    margin-bottom: 8px;
}

.requirements-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.req-item {
    display: flex;
    flex-direction: column;
}

.req-label {
    font-size: 0.75rem;
    color: #8b949e;
}

.req-value {
    font-family: monospace;
    font-size: 0.9rem;
    color: #e6edf3;
}

.revenue {
    text-align: right;
    border-left: 1px solid #30363d;
    padding-left: 24px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.revenue-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2ea043;
}

.revenue-value small {
    font-size: 0.9rem;
    font-weight: 400;
    color: #8b949e;
}

.revenue-sub {
    font-size: 0.85rem;
    color: #8b949e;
}

.timer-section {
    display: flex;
    align-items: center;
    gap: 12px;
}

.timer-label {
    font-size: 0.85rem;
    color: #8b949e;
}

.timer-bar {
    flex: 1;
    height: 6px;
    background: #21262d;
    border-radius: 3px;
    overflow: hidden;
}

.timer-fill {
    height: 100%;
    background: #e6edf3;
    transition: width 1s linear;
}

.timer-text {
    font-family: monospace;
    font-size: 0.9rem;
    color: #e6edf3;
    min-width: 50px;
    text-align: right;
}

.server-selection {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    border-top: 1px solid #30363d;
}

.server-selection h3 {
    margin: 0 0 16px 0;
    font-size: 1rem;
    color: #e6edf3;
}

.server-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.server-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    text-align: left;
    transition: all 0.2s;
    cursor: pointer;
}

.server-option:hover:not(:disabled) {
    border-color: #58a6ff;
    background: #161b22;
}

.server-option--unsuitable {
    opacity: 0.5;
    cursor: not-allowed;
    border-style: dashed;
}

.server-option__icon {
    font-size: 1.5rem;
}

.server-option__info {
    flex: 1;
}

.server-option__name {
    font-weight: 600;
    color: #e6edf3;
}

.server-option__specs {
    font-size: 0.85rem;
    color: #8b949e;
    font-family: monospace;
}

.server-option__action {
    font-size: 0.85rem;
    color: #2ea043;
    font-weight: 600;
}

.no-servers {
    text-align: center;
    padding: 32px;
    color: #8b949e;
    font-style: italic;
    background: #0d1117;
    border-radius: 6px;
}

.overlay-actions {
    padding: 16px 24px;
    border-top: 1px solid #30363d;
    display: flex;
    justify-content: flex-end;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.btn--danger {
    background: transparent;
    border-color: #f85149;
    color: #f85149;
}

.btn--danger:hover {
    background: rgba(248, 81, 73, 0.1);
}
</style>
