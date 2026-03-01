<template>
    <div class="market-alert-ticker" v-if="alerts.length > 0">
        <div class="alert-track">
            <div 
                v-for="(alert, index) in alerts" 
                :key="alert.id"
                class="market-alert-item"
                :class="alert.severity"
            >
                <div class="alert-icon">{{ alert.icon }}</div>
                <div class="alert-content">
                    <span class="alert-title">{{ alert.title }}</span>
                    <span class="alert-value">{{ alert.value }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useGameStore } from '../../stores/game';
// import SoundManager from '../../services/SoundManager';

const gameStore = useGameStore();

const alerts = computed(() => {
    const list = [];
    const energy = gameStore.energy || { spot_price: 0 };
    
    // High Energy Price Alerts
    if (energy.spot_price >= 0.30) {
        list.push({
            id: 'high_power',
            severity: 'critical',
            icon: '⚡',
            title: 'CRITICAL ENERGY SPIKE',
            value: `$${energy.spot_price.toFixed(3)}/kWh`
        });
    } else if (energy.spot_price >= 0.20) {
         list.push({
            id: 'warn_power',
            severity: 'warning',
            icon: '⚡',
            title: 'RISING ENERGY COSTS',
            value: `$${energy.spot_price.toFixed(3)}/kWh`
        });
    }

    // Critical World Events
    const activeEvents = gameStore.worldEvents?.active || [];
    activeEvents.forEach(e => {
        if (e.modifier_type === 'hardware_cost' && e.modifier_value < 1.0) {
            const discount = Math.round((1 - e.modifier_value) * 100);
            list.push({
                id: 'hw_sale_' + e.id,
                severity: 'sale',
                icon: '🏷️',
                title: 'HARDWARE LIQUIDATION',
                value: `${discount}% OFF ALL COMPONENTS`
            });
        } else if (e.modifier_type === 'hardware_cost' && e.modifier_value > 1.0) {
            const increase = Math.round((e.modifier_value - 1) * 100);
            list.push({
                id: 'hw_crisis_' + e.id,
                severity: 'warning',
                icon: '📦',
                title: 'SUPPLY CHAIN CRISIS',
                value: `+${increase}% HARDWARE COSTS`
            });
        } else if (e.severity === 'critical') {
            list.push({
                id: 'event_' + e.id,
                severity: 'critical',
                icon: '🚨',
                title: e.title.toUpperCase(),
                value: 'IMMEDIATE ACTION REQUIRED'
            });
        }
    });

    return list;
});

</script>

<style scoped>
.market-alert-ticker {
    display: flex;
    align-items: center;
    padding: 0 12px;
    height: 100%;
}

.alert-track {
    display: flex;
    gap: 12px;
}

.market-alert-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    animation: flash-alert 2s infinite;
}

.market-alert-item.critical {
    background: rgba(220, 38, 38, 0.2);
    border-color: rgba(220, 38, 38, 0.8);
    color: #fff;
    box-shadow: 0 0 15px rgba(220, 38, 38, 0.4), inset 0 0 10px rgba(220, 38, 38, 0.2);
    animation: flash-alert-critical 0.8s infinite alternate ease-in-out;
}

.market-alert-item.warning {
    background: rgba(217, 119, 6, 0.15);
    border-color: rgba(217, 119, 6, 0.5);
    color: #fcd34d;
}

.market-alert-item.sale {
    background: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.8);
    color: #34d399;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.3), inset 0 0 10px rgba(16, 185, 129, 0.1);
    animation: flash-sale 1.5s infinite alternate ease-in-out;
}

.alert-icon { font-size: 1.2rem; filter: drop-shadow(0 0 5px currentColor); }

.alert-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.1;
}

.alert-title {
    font-size: 0.55rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    opacity: 0.9;
}

.alert-value {
    font-size: 0.75rem;
    font-weight: 800;
    font-family: var(--font-mono);
}

@keyframes flash-alert {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@keyframes flash-alert-critical {
    from { opacity: 1; transform: scale(1); border-color: rgba(220, 38, 38, 0.8); }
    to { opacity: 0.8; transform: scale(1.02); border-color: rgba(220, 38, 38, 1); }
}

@keyframes flash-sale {
    from { opacity: 1; transform: scale(1); border-color: rgba(16, 185, 129, 0.8); }
    to { opacity: 0.85; transform: scale(1.02); border-color: rgba(16, 185, 129, 1); box-shadow: 0 0 20px rgba(16, 185, 129, 0.5); }
}
</style>
