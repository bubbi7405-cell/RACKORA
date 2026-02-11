<template>
    <aside class="left-panel">
        <div class="panel-header">
            <h3 class="panel-header__title">Operations</h3>
        </div>

        <div class="panel-content">
            <!-- Room Selection -->
            <div class="panel-section">
                <h4 class="section-title">Locations</h4>
                
                <div class="room-list">
                    <button 
                        v-for="room in roomList" 
                        :key="room.id"
                        class="room-item"
                        :class="{ 
                            'room-item--selected': selectedRoomId === room.id,
                            'room-item--warning': room.warnings.overheating
                        }"
                        @click="gameStore.selectRoom(room.id)"
                    >
                        <div class="room-item__icon">
                            {{ getRoomIcon(room.type) }}
                        </div>
                        <div class="room-item__info">
                            <div class="room-item__name">{{ room.name }}</div>
                            <div class="room-item__stats">
                                {{ room.usedRacks }}/{{ room.maxRacks }} racks
                            </div>
                        </div>
                        <div class="room-item__indicators">
                            <div v-if="room.warnings.overheating" class="indicator indicator--danger" title="Overheating">
                                🌡
                            </div>
                            <div v-if="room.warnings.powerOverload" class="indicator indicator--danger" title="Power Overload">
                                ⚡
                            </div>
                            <div v-if="room.warnings.bandwidthSaturated" class="indicator indicator--warning" title="Bandwidth Saturated">
                                🌐
                            </div>
                        </div>
                        <button class="room-item__action" title="Room Infrastructure" @click.stop="$emit('openUpgrades')">
                            🏗️
                        </button>
                    </button>

                    <!-- Locked Rooms (Purchasable) -->
                    <div 
                        v-for="(roomType, key) in lockedRooms" 
                        :key="key"
                        class="room-item room-item--locked"
                        :class="{ 
                            'room-item--available': canPurchaseRoom(key),
                            'room-item--purchasing': purchasingRoom === key
                        }"
                    >
                        <div class="room-item__icon">{{ canPurchaseRoom(key) ? '🔓' : '🔒' }}</div>
                        <div class="room-item__info">
                            <div class="room-item__name">{{ roomType.name }}</div>
                            <div class="room-item__stats">
                                <span :class="{ 'text-danger': player.economy.level < roomType.level }">
                                    Lv.{{ roomType.level }}
                                </span>
                                •
                                <span :class="{ 'text-danger': player.economy.balance < roomType.cost }">
                                    ${{ formatMoney(roomType.cost) }}
                                </span>
                            </div>
                        </div>
                        <button 
                            v-if="canPurchaseRoom(key)"
                            class="room-buy-btn"
                            :disabled="purchasingRoom === key"
                            @click.stop="purchaseRoom(key)"
                        >
                            {{ purchasingRoom === key ? '...' : 'BUY' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="panel-section">
                <h4 class="section-title">Infrastructure</h4>
                
                <div class="quick-stats">
                    <div class="quick-stat">
                        <span class="quick-stat__label">Total Racks</span>
                        <span class="quick-stat__value">{{ stats.totalRacks }}</span>
                    </div>
                    <div class="quick-stat">
                        <span class="quick-stat__label">Total Servers</span>
                        <span class="quick-stat__value">{{ stats.totalServers }}</span>
                    </div>
                    <div class="quick-stat">
                        <span class="quick-stat__label">Uptime</span>
                        <span class="quick-stat__value" :class="{ 'text-warning': stats.uptime < 99, 'text-danger': stats.uptime < 95 }">
                            {{ stats.uptime.toFixed(1) }}%
                        </span>
                    </div>
                    <div class="quick-stat">
                        <span class="quick-stat__label">MRR</span>
                        <span class="quick-stat__value text-success">${{ formatMoney(stats.monthlyRecurringRevenue) }}</span>
                    </div>
                </div>
            </div>

            <!-- Management Actions -->
            <div class="panel-section">
                <h4 class="section-title">Management</h4>
                <div class="action-grid">
                     <button class="action-btn" @click="$emit('openResearch')">
                        <span class="action-icon">🔬</span>
                        <span>Research & Dev</span>
                        <div v-if="gameStore.research.active" class="action-badge">Active</div>
                     </button>
                </div>
            </div>

            <!-- Recent Activity / Orders -->
            <div class="panel-section">
                <h4 class="section-title">
                    Pending Orders
                    <span v-if="orders.urgentCount > 0" class="badge badge--danger">{{ orders.urgentCount }}</span>
                </h4>
                
                <div class="order-list">
                    <div 
                        v-for="order in orders.pending.slice(0, 5)" 
                        :key="order.id"
                        @click="gameStore.selectOrder(order)"
                        class="order-item clickable"
                        :class="{ 'order-item--urgent': order.patience.progress > 70 }"
                    >
                        <div class="order-item__info">
                            <div class="order-item__customer">{{ order.customerName }}</div>
                            <div class="order-item__product">{{ order.productType }}</div>
                        </div>
                        <div class="order-item__timer">
                            <div class="progress-bar" :class="{ 'progress-bar--danger': order.patience.progress > 70 }">
                                <div class="progress-bar__fill" :style="{ width: order.patience.progress + '%' }"></div>
                            </div>
                            <span class="order-item__time">{{ formatTime(order.patience.remainingSeconds) }}</span>
                        </div>
                    </div>

                    <div v-if="orders.pending.length === 0" class="empty-state">
                        <span class="empty-state__icon">📭</span>
                        <span class="empty-state__text">No pending orders</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useGameStore } from '../../stores/game';
import { storeToRefs } from 'pinia';

const gameStore = useGameStore();
const { rooms, selectedRoomId, stats, orders, player } = storeToRefs(gameStore);

const purchasingRoom = ref(null);

const roomList = computed(() => Object.values(rooms.value));

const lockedRooms = computed(() => {
    const unlocked = new Set(roomList.value.map(r => r.type));
    const locked = {};
    
    const roomTypes = {
        garage: { name: 'Garage', level: 5, cost: 25000 },
        small_hall: { name: 'Small Hall', level: 15, cost: 150000 },
        data_center: { name: 'Data Center', level: 30, cost: 1000000 },
    };

    for (const [key, value] of Object.entries(roomTypes)) {
        if (!unlocked.has(key)) {
            locked[key] = value;
        }
    }
    
    return locked;
});

function canPurchaseRoom(roomType) {
    const room = lockedRooms.value[roomType];
    if (!room || !player.value?.economy) return false;
    return player.value.economy.level >= room.level && player.value.economy.balance >= room.cost;
}

async function purchaseRoom(roomType) {
    if (purchasingRoom.value) return;
    purchasingRoom.value = roomType;
    
    try {
        await gameStore.purchaseRoom(roomType);
    } finally {
        purchasingRoom.value = null;
    }
}

function getRoomIcon(type) {
    const icons = {
        basement: '🏠',
        garage: '🏭',
        small_hall: '🏢',
        data_center: '🏗️',
    };
    return icons[type] || '📦';
}

function formatMoney(value) {
    if (value >= 1000000) {
        return (value / 1000000).toFixed(1) + 'M';
    }
    if (value >= 1000) {
        return (value / 1000).toFixed(0) + 'K';
    }
    return value.toFixed(0);
}

function formatTime(seconds) {
    if (seconds <= 0) return 'Expired!';
    
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
</script>

<style scoped>
.left-panel {
    grid-area: left-panel;
    background: linear-gradient(90deg, rgba(15, 20, 25, 0.98) 0%, rgba(22, 27, 34, 0.95) 100%);
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.panel-content {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-md);
}

.panel-section {
    margin-bottom: var(--space-xl);
}

.section-title {
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-text-secondary);
    margin-bottom: var(--space-sm);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.badge {
    font-size: var(--font-size-xs);
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius-sm);
}

.badge--danger {
    background: var(--color-danger);
    color: white;
}

.room-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.room-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    background: var(--color-bg-elevated);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
    text-align: left;
}

.room-item:hover {
    background: var(--color-bg-light);
}

.room-item--selected {
    border-color: var(--color-primary);
    background: var(--color-primary-dim);
}

.room-item--warning {
    border-color: var(--color-warning);
}

.room-item--locked {
    opacity: 0.5;
    cursor: not-allowed;
}

.room-item__icon {
    font-size: 1.25rem;
}

.room-item__info {
    flex: 1;
}

.room-item__name {
    font-weight: 500;
    font-size: var(--font-size-sm);
}

.room-item__stats {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
}

.room-item__indicators {
    display: flex;
    gap: var(--space-xs);
}

.indicator {
    font-size: var(--font-size-sm);
}

.indicator--danger {
    animation: pulse 1s ease-in-out infinite;
}

.room-item__action {
    background: transparent;
    border: none;
    font-size: 1.1rem;
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    opacity: 0.4;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.room-item__action:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
    transform: scale(1.1);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-sm);
}

.quick-stat {
    background: var(--color-bg-elevated);
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
}

.quick-stat__label {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
}

.quick-stat__value {
    font-family: var(--font-family-mono);
    font-weight: 600;
}

.order-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.order-item {
    background: var(--color-bg-elevated);
    padding: var(--space-sm);
    border-radius: var(--radius-md);
    border: 1px solid transparent;
}

.order-item--urgent {
    border-color: var(--color-danger);
    background: var(--color-danger-dim);
}

.order-item__customer {
    font-size: var(--font-size-sm);
    font-weight: 500;
}

.order-item__product {
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    text-transform: capitalize;
}

.order-item__timer {
    margin-top: var(--space-xs);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.order-item__time {
    font-family: var(--font-family-mono);
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
}

.order-item--urgent .order-item__time {
    color: var(--color-danger);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: var(--space-lg);
    color: var(--color-text-muted);
}

.empty-state__icon {
    font-size: 1.5rem;
    margin-bottom: var(--space-sm);
}

.empty-state__text {
    font-size: var(--font-size-sm);
}

.clickable {
    cursor: pointer;
    transition: transform 0.1s;
}
.clickable:hover {
    transform: scale(1.02);
}

/* Management Actions */
.action-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-sm);
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--space-md);
    background: var(--color-bg-elevated);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
    color: var(--color-text-primary);
    position: relative;
    font-size: var(--font-size-sm);
    font-weight: 500;
}

.action-btn:hover {
    background: var(--color-bg-light);
    border-color: var(--color-primary);
    transform: translateY(-2px);
}

.action-icon {
    font-size: 1.5rem;
    margin-bottom: var(--space-xs);
}

.action-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 0.6rem;
    background: var(--color-warning);
    color: #000;
    padding: 1px 4px;
    border-radius: 4px;
    font-weight: 600;
    animation: pulse 2s infinite;
}

/* Room Purchase */
.room-item--available {
    border-color: var(--color-success) !important;
    background: rgba(46, 160, 67, 0.08) !important;
}

.room-item--purchasing {
    opacity: 0.6;
    pointer-events: none;
}

.room-buy-btn {
    padding: 3px 10px;
    background: var(--color-success);
    color: #000;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 700;
    cursor: pointer;
    transition: all var(--transition-fast);
    letter-spacing: 0.05em;
    flex-shrink: 0;
}

.room-buy-btn:hover {
    background: var(--color-success-hover, #3fb950);
    transform: scale(1.05);
}

.room-buy-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.text-danger {
    color: var(--color-danger) !important;
}
</style>
