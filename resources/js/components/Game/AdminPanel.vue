<template>
    <div class="v2-main-viewport admin-panel">
        <header class="v2-content-header">
            <div class="v2-breadcrumb l3-priority">
                <span class="v2-path">ROOT_PRIVILEGE</span>
                <span class="v2-sep">≫</span>
                <span class="v2-asset-site">SYSTEM_DEBUG_REPL</span>
            </div>
            <div class="header-stats">
                <div class="h-stat danger">
                    <span class="hs-label l3-priority">ADMIN_PRIVILEGE</span>
                    <span class="hs-val l1-priority">ESCALATED</span>
                </div>
            </div>
        </header>

        <div class="v2-content-scroll">
            <div class="v2-title l2-priority">SYSTEM_AUTHORITY_OVERRIDE // [DEBUG_MODE]</div>
            
            <div class="admin-grid">
                <!-- Economy Hacks -->
                <div class="admin-card">
                    <div class="v2-title small">ECONOMY_OVERRIDE</div>
                    <div class="admin-actions">
                        <button class="v2-cmd-btn" @click="modifyEconomy('add', 100000)">ADD_$100K</button>
                        <button class="v2-cmd-btn" @click="modifyEconomy('add', 1000000)">ADD_$1M</button>
                        <button class="v2-cmd-btn secondary" @click="modifyEconomy('reset', 0)">RESET_BALANCE</button>
                    </div>
                </div>

                <!-- XP Hacks -->
                <div class="admin-card">
                    <div class="v2-title small">XP_OVERRIDE</div>
                    <div class="admin-actions">
                        <button class="v2-cmd-btn" @click="addXP(500)">+500_XP</button>
                        <button class="v2-cmd-btn" @click="addXP(5000)">+5k_XP</button>
                        <button class="v2-cmd-btn l1-priority" @click="levelUp">INSTANT_LEVEL_UP</button>
                    </div>
                </div>

                <!-- Asset Spawning -->
                <div class="admin-card">
                    <div class="v2-title small">ASSET_PROVISIONING</div>
                    <div class="admin-actions">
                        <button class="v2-cmd-btn" @click="spawnServer('vserver_node')">SPAWN_CORE_SERVER</button>
                        <button class="v2-cmd-btn" @click="spawnServer('gpu_cluster')">SPAWN_GPU_CLUSTER</button>
                        <button class="v2-cmd-btn l1-priority" @click="unlockAllRegions">OPEN_REGIONAL_LOCKS</button>
                    </div>
                </div>

                <!-- Simulation Control -->
                <div class="admin-card">
                    <div class="v2-title small">SIMULATION_TIMEBASE</div>
                    <div class="admin-actions">
                        <button class="v2-cmd-btn" @click="setSpeed(1)">SPEED_1X</button>
                        <button class="v2-cmd-btn" @click="setSpeed(5)">SPEED_5X</button>
                        <button class="v2-cmd-btn danger" @click="resetProgress">FULL_INFRA_RESET</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useEconomyStore } from '../../stores/economy';
import { useGameStore } from '../../stores/game';
import api from '../../utils/api';
import { useToastStore } from '../../stores/toast';

const economyStore = useEconomyStore();
const gameStore = useGameStore();
const toast = useToastStore();

const modifyEconomy = async (action, amount) => {
    try {
        const response = await api.post('/admin/economy', { action, amount });
        if (response.success) {
            toast.success(`Economy modified: ${action} ${amount}`);
            await gameStore.loadGameState(true);
        }
    } catch (e) { toast.error('Admin override failed'); }
};

const addXP = async (amount) => {
    await api.post('/admin/xp', { amount });
    await gameStore.loadGameState(true);
};

const levelUp = async () => {
    await api.post('/admin/level-up');
    await gameStore.loadGameState(true);
};

const spawnServer = async (type) => {
    await api.post('/admin/spawn-server', { type });
    await gameStore.loadGameState(true);
};

const unlockAllRegions = async () => {
    await api.post('/admin/unlock-regions');
    await gameStore.loadGameState(true);
};

const setSpeed = async (speed) => {
    economyStore.gameSpeed = speed;
};

const resetProgress = async () => {
    if (confirm('AUTHORIZE COMPLETE WIPEOUT? This cannot be undone.')) {
        await api.post('/admin/reset');
        window.location.reload();
    }
};
</script>

<style scoped>
.admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.admin-card {
    background: rgba(200,30,30,0.05);
    border: 1px solid rgba(255,50,50,0.1);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.admin-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.danger .hs-val { color: #f85149; text-shadow: 0 0 10px rgba(248, 81, 73, 0.4); }
</style>
