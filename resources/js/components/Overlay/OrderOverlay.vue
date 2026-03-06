<template>
    <div v-if="order" class="overlay-backdrop" @click.self="$emit('close')">
        <div class="overlay-content">
            <header class="overlay-header">
                <h2>New Order: {{ order.productType }}</h2>
                <button class="close-btn" @click="$emit('close')">&times;</button>
            </header>

            <div class="order-details">
                <div class="customer-info">
                    <span class="customer-avatar">🏢</span>
                    <div class="customer-text">
                        <div class="customer-title-row">
                            <h3>{{ order.customerName }}</h3>
                            <span v-if="order.sla && order.sla.tier === 'enterprise'"
                                class="sla-badge enterprise">ENTERPRISE</span>
                            <span v-if="order.sla && order.sla.tier === 'diamond' || order.sla.tier === 'whale'"
                                class="sla-badge whale">DIAMOND_GRADE</span>
                            <span v-if="order.targetRegion" class="region-badge"
                                :title="'Prefers ' + order.targetRegion">
                                {{ getRegionFlag(order.targetRegion) }} {{ order.targetRegion }}
                            </span>
                        </div>
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
                            <div class="req-item" v-if="order.requirements.max_latency_ms">
                                <span class="req-label">Max Latency</span>
                                <span class="req-value" style="color: #ff9d00">{{ order.requirements.max_latency_ms }}
                                    ms</span>
                            </div>
                            <div class="req-item" v-if="order.requirements.ipv4">
                                <span class="req-label">IPv4_ADDR</span>
                                <span class="req-value">{{ order.requirements.ipv4 }} Fixed</span>
                            </div>
                            <div class="req-item" v-if="order.requirements.os">
                                <span class="req-label">Required OS</span>
                                <span class="req-value" style="color: #58a6ff">{{ order.requirements.os }}</span>
                            </div>
                            <div class="req-item" v-if="order.requirements.ports && order.requirements.ports.length">
                                <span class="req-label">Comm Ports</span>
                                <span class="req-value" style="color: #ff9d00">
                                    TCP: {{ order.requirements.ports.join(', ') }}
                                </span>
                            </div>
                            <div class="req-item">
                                <span class="req-label">SECURITY_PATCH_LVL</span>
                                <span class="req-value"
                                    :style="{ color: getRequiredSecurity(order.sla?.tier) > 0 ? '#ff5f5f' : '#8b949e' }">
                                    {{ getRequiredSecurity(order.sla?.tier) }}%
                                </span>
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
                    <button v-for="server in availableServers" :key="server.id" class="server-option"
                        :class="{ 'server-option--suitable': isSuitable(server), 'server-option--unsuitable': !isSuitable(server) }"
                        :disabled="!isSuitable(server)" @click="acceptOrder(server.id)">
                        <div class="server-option__icon">🖥️</div>
                        <div class="server-option__info">
                            <div class="server-option__name">{{ server.modelName }}</div>
                            <div class="server-option__specs">
                                {{ server.specs.cpuCores }}C • {{ server.specs.ramGb }}GB • {{ server.specs.storageTb
                                }}TB
                            </div>
                            <div v-if="!isSuitable(server)" class="server-option__reasons">
                                <span v-for="(reason, i) in getRejectionReasons(server)" :key="i"
                                    class="rejection-tag">⚠ {{ reason }}</span>
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
                <button v-if="order.isNegotiable || (order.negotiation && order.negotiation.isNegotiable)"
                    class="btn btn--primary" @click="$emit('negotiate', order)">
                    Negotiate Terms
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useGameStore } from '../../stores/game';
import { useToastStore } from '../../stores/toast';
import api from '../../utils/api';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    }
});

const emit = defineEmits(['close', 'negotiate']);
const gameStore = useGameStore();
const toastStore = useToastStore();
const processing = ref(false);

watch(() => props.order?.status, (status) => {
    if (status && status !== 'pending') {
        emit('close');
    }
}, { immediate: true });

const availableServers = computed(() => {
    const servers = [];
    console.log('SCANNING ROOMS:', Object.keys(gameStore.rooms || {}).length);

    // Own rooms
    for (const roomId in gameStore.rooms) {
        const room = gameStore.rooms[roomId];
        console.log('Room:', room.name, 'Racks:', room.racks?.length);
        if (room.racks) {
            for (const rack of room.racks) {
                console.log('Rack:', rack.name, 'Servers:', rack.servers?.length);
                if (rack.servers) {
                    for (const server of rack.servers) {
                        if (server.status !== 'offline' && server.status !== 'damaged') {
                            servers.push(server);
                        }
                    }
                }
            }
        }
    }

    // Rented servers
    if (gameStore.rentedServers) {
        console.log('SCANNING RENTED:', gameStore.rentedServers.length);
        for (const server of gameStore.rentedServers) {
            if (server.status !== 'offline' && server.status !== 'damaged') {
                servers.push(server);
            }
        }
    }

    console.log('FOUND TOTAL SERVERS:', servers.length);
    return servers;
});

function isSuitable(server) {
    return getRejectionReasons(server).length === 0;
}

function getRejectionReasons(server) {
    const reasons = [];
    if (server.status === 'offline') {
        reasons.push('Server ist OFFLINE');
        return reasons;
    }

    const req = props.order.requirements;

    if (server.specs.cpuCores < req.cpu) {
        reasons.push(`CPU: ${server.specs.cpuCores}/${req.cpu} Cores`);
    }
    if (server.specs.ramGb < req.ram) {
        reasons.push(`RAM: ${server.specs.ramGb}/${req.ram} GB`);
    }
    if (server.specs.storageTb * 1024 < req.storage) {
        reasons.push(`Storage: ${Math.round(server.specs.storageTb * 1024)}/${req.storage} GB`);
    }

    // Check OS
    if (req.os) {
        if (!server.os.type || server.os.type === 'none') {
            reasons.push(`Kein OS installiert (${req.os} benötigt)`);
        } else if (server.os.type !== req.os) {
            reasons.push(`Falsches OS: ${server.os.type} (${req.os} benötigt)`);
        } else if (server.os.status !== 'installed') {
            reasons.push(`OS wird noch installiert`);
        }
    }

    // Check availability (if vserver node)
    if (server.type === 'shared_node') {
        if (props.order.productType !== 'web_hosting' && props.order.productType !== 'database_hosting') {
            reasons.push('Shared Node: falscher Produkttyp');
        } else if (server.vserver.available <= 0) {
            reasons.push('Keine freien vServer-Slots');
        }
    } else if (server.type === 'vserver_node') {
        if (server.vserver.available <= 0) {
            reasons.push('Keine freien vServer-Slots');
        }
    } else {
        if (server.activeOrdersCount > 0) {
            reasons.push('Server bereits belegt');
        }
    }

    // Check Latency Requirement
    if (req.max_latency_ms) {
        let roomLatency = null;
        for (const roomId in gameStore.rooms) {
            const room = gameStore.rooms[roomId];
            if (room.racks) {
                for (const rack of room.racks) {
                    if (rack.servers && rack.servers.some(s => s.id === server.id)) {
                        roomLatency = room.latency || 100;
                        break;
                    }
                }
            }
            if (roomLatency !== null) break;
        }

        if (roomLatency !== null && roomLatency > req.max_latency_ms) {
            reasons.push(`Latenz: ${roomLatency}ms > ${req.max_latency_ms}ms`);
        }
    }

    // Check Region Requirement
    if (req.required_region) {
        let serverRegion = null;
        for (const roomId in gameStore.rooms) {
            const room = gameStore.rooms[roomId];
            if (room.racks && room.racks.some(r => r.servers && r.servers.some(s => s.id === server.id))) {
                serverRegion = room.region;
                break;
            }
        }

        if (serverRegion && serverRegion !== req.required_region) {
            const reqName = gameStore.regions[req.required_region]?.name || req.required_region;
            const srvName = gameStore.regions[serverRegion]?.name || serverRegion;
            reasons.push(`Region: ${srvName} (benötigt ${reqName})`);
        }
    }

    // Check Security Patch Level
    const requiredSecurity = getRequiredSecurity(props.order.sla?.tier);
    if (requiredSecurity > 0 && (server.os?.security || 0) < requiredSecurity) {
        reasons.push(`Security: ${server.os?.security || 0}%/${requiredSecurity}%`);
    }

    return reasons;
}

function getRequiredSecurity(tier) {
    if (tier === 'diamond' || tier === 'whale') return 95;
    if (tier === 'enterprise') return 90;
    if (tier === 'premium') return 80;
    return 0;
}

async function acceptOrder(serverId) {
    if (processing.value) return;
    processing.value = true;
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
        const msg = error.message || '';
        if (msg.includes('pending') || msg.includes('not found')) {
            // It's already gone or accepted, just close silently or with a small info
            console.log('Order already processed:', msg);
            await gameStore.loadGameState();
            emit('close');
        } else {
            toastStore.error(msg || 'Failed to accept order');
        }
    } finally {
        processing.value = false;
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

function getRegionFlag(regionKey) {
    if (!regionKey) return '';
    return gameStore.regions[regionKey]?.flag || '❓';
}
</script>

<style scoped>
.overlay-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(10, 15, 20, 0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: var(--zi-overlays);
    pointer-events: auto;
}

@keyframes v3-pop-in {
    from {
        transform: scale(0.98) translateY(10px);
        opacity: 0;
    }

    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

.overlay-content {
    position: relative;
    width: 600px;
    max-width: 90%;
    max-height: 90vh;
    background: var(--v3-bg-overlay);
    border: var(--v3-border-heavy);
    border-radius: var(--v3-radius);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: v3-pop-in 0.15s var(--v3-easing) forwards;
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

.close-btn:hover {
    color: #e6edf3;
}

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
    margin: 0;
    font-size: 1.1rem;
    color: #e6edf3;
}

.customer-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
}

.region-badge {
    font-size: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    padding: 2px 8px;
    border-radius: 4px;
    color: #8b949e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sla-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 3px;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sla-badge.enterprise {
    background: linear-gradient(135deg, #1f6feb, #388bfd);
    box-shadow: 0 0 10px rgba(31, 111, 235, 0.3);
}

.sla-badge.whale {
    background: linear-gradient(135deg, #d29922, #e3b341);
    color: #0d1117;
    box-shadow: 0 0 10px rgba(210, 153, 34, 0.4);
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

.server-option__reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
}

.rejection-tag {
    font-size: 0.85rem;
    color: #f85149;
    background: rgba(248, 81, 73, 0.15);
    border: 1px solid rgba(248, 81, 73, 0.3);
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
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

.btn--primary {
    background: #2ea043;
    color: white;
    margin-left: 12px;
}

.btn--primary:hover {
    background: #3fb950;
    box-shadow: 0 0 10px rgba(46, 160, 67, 0.4);
}
</style>
