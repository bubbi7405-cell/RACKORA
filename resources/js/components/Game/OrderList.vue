<template>
    <div class="order-list-context">
        <div class="operational-header">
            <div class="h-main">
                <span class="h-label">INBOUND_REQUEST_POOL</span>
                <span class="h-count">[{{ pendingOrders.length }}]</span>
            </div>
            <div class="btn-refresh" @click="refreshState">FORCE_SYNC</div>
        </div>

        <div v-if="pendingOrders.length > 0" class="order-table">
            <div class="table-head">
                <span class="col-type">PROTOCOL</span>
                <span class="col-client">ENTITY</span>
                <span class="col-specs">REQS</span>
                <span class="col-price">REVENUE</span>
                <span class="col-action">ACTION</span>
            </div>
            
            <div 
                v-for="order in pendingOrders" 
                :key="order.id" 
                class="order-row"
                @click="selectOrder(order)"
                :style="{
                    borderLeft: getBorderColor(order),
                    background: getBgColor(order),
                    cursor: 'pointer',
                    position: 'relative',
                    zIndex: 20
                }"
            >
                <span class="col-type">{{ order.productType?.toUpperCase() || 'UNKNOWN' }}</span>
                <span class="col-client">
                    {{ order.customerName || 'Private Entity' }}
                    <span v-if="order.sla?.tier === 'enterprise'" class="badge-mini ent">ENT</span>
                    <span v-if="order.sla?.tier === 'whale'" class="badge-mini whale">WHALE</span>
                </span>
                <span class="col-specs">
                    {{ order.requirements?.cpu || 0 }}C / {{ order.requirements?.ram || 0 }}G
                </span>
                <span class="col-price">${{ order.pricePerMonth?.toFixed(0) || 0 }}</span>
                <span class="col-action">
                    <button class="btn-select" @click.stop="selectOrder(order)">OPEN</button>
                </span>
            </div>
        </div>

        <div v-else class="empty-operations">
            <span class="empty-signal">READY_FOR_TRAFFIC</span>
            <p>Awaiting new infrastructure provisioning requests.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useGameStore } from '../../stores/game';
import { useUiStore } from '../../stores/ui';

const gameStore = useGameStore();
const uiStore = useUiStore();

const pendingOrders = computed(() => {
    return gameStore.orders?.pending || [];
});

function selectOrder(order) {
    console.log('CLICKED ORDER:', order.id);
    uiStore.selectOrder(order);
}

function refreshState() {
    gameStore.loadGameState();
}

function getBorderColor(order) {
    if (order.sla?.tier === 'whale') return '4px solid #fbbf24';
    if (order.sla?.tier === 'enterprise') return '4px solid #3b82f6';
    return '2px solid transparent';
}

function getBgColor(order) {
    if (order.sla?.tier === 'whale') return 'rgba(251, 191, 36, 0.15)';
    if (order.sla?.tier === 'enterprise') return 'rgba(59, 130, 246, 0.1)';
    return 'rgba(255,255,255,0.02)';
}
</script>

<style scoped>
.order-list-context { display: flex; flex-direction: column; gap: 16px; width: 100%; }
.operational-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px; }
.h-label { font-size: 0.6rem; font-weight: 900; color: #888; letter-spacing: 0.2em; }
.h-count { color: var(--v3-accent); font-weight: 800; }
.btn-refresh { font-size: 0.5rem; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 2px; cursor: pointer; }
.btn-refresh:hover { background: rgba(255,255,255,0.1); }

.order-table { display: flex; flex-direction: column; background: #0c0e12; border-radius: 4px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
.table-head { display: grid; grid-template-columns: 100px 1fr 150px 100px 80px; padding: 10px 16px; background: rgba(0,0,0,0.3); font-size: 0.5rem; color: #555; font-weight: 900; }

.order-row { 
    display: grid; 
    grid-template-columns: 100px 1fr 150px 100px 80px; 
    padding: 12px 16px; 
    border-bottom: 1px solid rgba(255,255,255,0.05); 
    align-items: center;
    transition: all 0.2s;
}
.order-row:hover { background: rgba(255,255,255,0.05) !important; }

.col-type { font-family: monospace; font-size: 0.6rem; color: var(--v3-accent); }
.col-client { font-weight: 700; color: #fff; font-size: 0.8rem; display: flex; align-items: center; gap: 8px; }
.col-specs { font-size: 0.65rem; color: #888; font-family: monospace; }
.col-price { color: #52c41a; font-weight: 800; font-family: monospace; }

.btn-select {
    background: var(--v3-accent);
    color: #000;
    border: none;
    padding: 4px 8px;
    font-size: 0.55rem;
    font-weight: 900;
    border-radius: 2px;
    cursor: pointer;
}

.badge-mini { font-size: 0.5rem; padding: 1px 4px; border-radius: 2px; }
.badge-mini.ent { background: #3b82f6; color: #fff; }
.badge-mini.whale { background: #fbbf24; color: #000; }

.empty-operations { text-align: center; padding: 60px; color: #555; }
</style>
