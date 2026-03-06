<template>
    <div class="v2-main-viewport hosting-view">
        <header class="v2-content-header">
            <div class="v2-breadcrumb l3-priority">
                <span class="v2-path">BUSINESS_LOGIC_LAYER</span>
                <span class="v2-sep">≫</span>
                <span class="v2-asset-site">GAME_SERVER_PROVISIONING</span>
            </div>
            <div class="header-stats">
                <div class="h-stat">
                    <span class="hs-label l3-priority">ACTIVE_INSTANCES</span>
                    <span class="hs-val l1-priority">{{ totalInstances }}</span>
                </div>
            </div>
        </header>

        <div class="v2-content-scroll">
            <div class="v2-title l2-priority">FLAVOR_PROFILES // [SERVICE_TYPES]</div>
            
            <div class="hosting-grid">
                <div 
                    v-for="service in serviceTypes" 
                    :key="service.id" 
                    class="service-card"
                    :class="{ 'is-unlocked': playerLevel >= service.unlockLevel }"
                >
                    <div class="service-icon">{{ service.icon }}</div>
                    <div class="service-info">
                        <h3 class="service-label l1-priority">{{ service.name }}</h3>
                        <div class="service-meta l3-priority">
                            <span>REVENUE: ${{ service.revenue }}/hr</span>
                            <span>CPU: {{ service.requirements.cpu }}C / RAM: {{ service.requirements.ram }}G</span>
                        </div>
                    </div>
                    <div v-if="playerLevel < service.unlockLevel" class="lock-overlay">
                        <span>LOCKED // REQ: LVL_{{ service.unlockLevel }}</span>
                    </div>
                </div>
            </div>

            <div class="v2-section active-fleet">
                <div class="v2-section-header l2-priority">ACTIVE_DEPLOYMENTS // [LIVE_FLEET]</div>
                <div class="fleet-list">
                    <div v-if="activeDeployments.length === 0" class="v2-empty-state">
                        <p>AWAITING_INITIAL_DEPLOYMENT_COMMANDS</p>
                    </div>
                    <div 
                        v-for="deployment in activeDeployments" 
                        :key="deployment.id" 
                        class="deployment-row l1-priority"
                    >
                        <span class="d-icon">{{ deployment.icon }}</span>
                        <span class="d-name">{{ deployment.name }} #{{ deployment.id.slice(-4) }}</span>
                        <span class="d-status" :class="deployment.status">{{ deployment.status.toUpperCase() }}</span>
                        <span class="d-infra l3-priority">{{ deployment.serverName }} @ {{ deployment.region }}</span>
                        <span class="d-rev text-success">+${{ deployment.hourlyRevenue }}/hr</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useEconomyStore } from '../../stores/economy';
import { useInfrastructureStore } from '../../stores/infrastructure';

const economyStore = useEconomyStore();
const infraStore = useInfrastructureStore();

const playerLevel = computed(() => economyStore.player?.economy?.level || 1);

const serviceTypes = [
    { id: 'minecraft', name: 'MINECRAFT_CORE', icon: '🧊', revenue: 15, unlockLevel: 1, requirements: { cpu: 2, ram: 4 } },
    { id: 'valorant', name: 'FPS_TACTICAL', icon: '🔫', revenue: 35, unlockLevel: 5, requirements: { cpu: 4, ram: 8 } },
    { id: 'wow_clone', name: 'MMO_PERSISTENT', icon: '⚔️', revenue: 120, unlockLevel: 12, requirements: { cpu: 8, ram: 32 } },
    { id: 'ai_instance', name: 'NEURAL_NETWORK_NODE', icon: '🧠', revenue: 450, unlockLevel: 25, requirements: { cpu: 32, ram: 128 } },
];

const totalInstances = computed(() => economyStore.customers?.active || 0);

// Mock data as actual deployments would come from the backend state (contracts)
const activeDeployments = computed(() => {
    return economyStore.customers?.list || [
        { id: 'srv_a1b2', name: 'CRAFT_VANILLA', icon: '🧊', status: 'online', serverName: 'SRV-X1', region: 'us_east', hourlyRevenue: 15 },
        { id: 'srv_c3d4', name: 'FPS_RANKED', icon: '🔫', status: 'degraded', serverName: 'SRV-X2', region: 'eu_west', hourlyRevenue: 35 },
    ];
});
</script>

<style scoped>
.hosting-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.service-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.service-icon { font-size: 2rem; }
.service-label { margin: 0; font-size: 0.9rem; font-weight: 800; }
.service-meta { font-size: 0.65rem; display: flex; flex-direction: column; gap: 4px; }

.lock-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.lock-overlay span { font-size: 0.7rem; font-weight: 900; background: #333; padding: 4px 12px; border-radius: 4px; border: 1px solid #444; }

.fleet-list {
    margin-top: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.deployment-row {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.03);
    padding: 12px 20px;
    border-radius: 4px;
    display: grid;
    grid-template-columns: 32px 1fr 100px 1fr 100px;
    align-items: center;
    font-size: 0.75rem;
}

.d-status.online { color: var(--color-success); font-weight: 800; }
.d-status.degraded { color: var(--color-warning); font-weight: 800; }
.d-rev { font-weight: 800; text-align: right; }
</style>
