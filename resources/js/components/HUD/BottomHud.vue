<template>
    <footer class="v2-command-deck">
        <!-- BUREAU: FINANCIAL_OPERATIONS -->
        <div class="v2-bureau-cluster">
            <div class="bureau-label l3-priority">FIN_OPS // [LIQUIDITY]</div>
            <div class="bureau-actions">
                <button class="v2-deck-btn l2-priority" @click="handleAction('openFinance')" v-tooltip="'FIN_OPS: Capital Flow & Treasury'">
                    <span class="btn-icon">◈</span>
                    <span class="btn-label">TREASURY</span>
                </button>
                <button class="v2-deck-btn l2-priority" :class="{ 'energy-volatile': isEnergyVolatile }" @click="handleAction('openEnergy')" v-tooltip="'ENERGY_MARKET: Grid Acquisition'">
                    <span class="btn-icon">⚡</span>
                    <span class="btn-label">ENERGY</span>
                </button>
            </div>
        </div>

        <div class="v2-deck-divider"></div>

        <!-- BUREAU: ASSET_MANAGEMENT -->
        <div class="v2-bureau-cluster">
            <div class="bureau-label l3-priority">ASSET_MGMT // [DEPLOYMENT]</div>
            <div class="bureau-actions">
                <button class="v2-deck-btn l2-priority" @click="handleAction('openCustomers')" v-tooltip="'MARKET_OPS: Customer Signal Acquisition'">
                    <span class="btn-icon">▣</span>
                    <span class="btn-label">ACQUISITION</span>
                </button>
                <button class="v2-deck-btn l2-priority" @click="handleAction('openEmployees')" v-tooltip="'HUMAN_ASSETS: Specialist Personnel'">
                    <span class="btn-icon">◈</span>
                    <span class="btn-label">PERSONNEL</span>
                </button>
            </div>
        </div>

        <!-- INTEL STRIP (CENTER DOMINANCE) -->
        <div class="v2-intel-strip">
            <div class="intel-header l3-priority">GLOBAL_INTEL_STREAM // [DECRYPTED]</div>
            <div class="intel-content">
                <WorldNewsTicker compact />
                <MarketAlertTicker compact />
            </div>
        </div>

        <!-- BUREAU: SYSTEM_RD_LOGS -->
        <div class="v2-bureau-cluster">
            <div class="bureau-label l3-priority">SYSTEM_RD // [SYNTHESIS]</div>
            <div class="bureau-actions">
                <button class="v2-deck-btn l2-priority" @click="handleAction('openUpgrades')" v-tooltip="'SYSTEM_RD: Infrastructure Evolution'">
                    <span class="btn-icon">▲</span>
                    <span class="btn-label">UPGRADES</span>
                </button>
                <button class="v2-deck-btn l2-priority" @click="handleAction('openLogs')" v-tooltip="'CMD_LOGS: System Event History'">
                    <span class="btn-icon">≡</span>
                    <span class="btn-label">LOGS</span>
                </button>
            </div>
        </div>

        <div class="v2-deck-divider"></div>

        <!-- BUREAU: OPERATIONAL_RISK -->
        <div class="v2-bureau-cluster is-critical">
            <div class="bureau-label l3-priority">OP_RISK // [THREAT_LOG]</div>
            <div class="bureau-actions">
                <button class="v2-deck-btn l1-priority btn-danger" @click="handleAction('openIncidents')" v-tooltip="'THREAT_CONSOLE: Active Compromises'">
                    <span class="btn-icon">⚠</span>
                    <span class="btn-label">INCIDENTS</span>
                    <span v-if="activeEventsCount > 0" class="mini-count pulse-urgent">{{ activeEventsCount }}</span>
                </button>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useGameStore } from '../../stores/game';
import WorldNewsTicker from './WorldNewsTicker.vue';
import MarketAlertTicker from './MarketAlertTicker.vue';

const emit = defineEmits(['openMarket', 'openCustomers', 'openUpgrades', 'openStats', 'openEmployees', 'openAutomation', 'openEnergy', 'openLogs', 'openIncidents', 'openCompliance', 'openFinance']);

const gameStore = useGameStore();

const isEnergyVolatile = computed(() => gameStore.energyMarket?.isVolatile || false);
const activeEventsCount = computed(() => gameStore.events?.active?.length || 0);

const handleAction = (action) => {
    emit(action);
};
</script>

<style scoped>
.v2-command-deck {
    height: 80px;
    background: linear-gradient(180deg, rgba(5, 10, 20, 0.95) 0%, var(--ds-bg-void) 100%);
    border-top: 2px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    z-index: var(--ds-z-sticky);
    box-shadow: 0 -8px 30px rgba(0,0,0,0.8);
}

.v2-bureau-cluster {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 140px;
}

.bureau-label {
    font-size: 0.5rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    color: var(--ds-text-ghost);
}

.bureau-actions {
    display: flex;
    gap: 12px;
}

.v2-deck-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--ds-text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 2px;
    position: relative;
}

.v2-deck-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.3);
}

.btn-icon {
    font-size: 0.8rem;
    color: var(--ds-accent);
}

.btn-label {
    font-size: 0.65rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.v2-intel-strip {
    flex: 1;
    max-width: 500px;
    margin: 0 40px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: rgba(0,0,0,0.3);
    padding: 8px 16px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.intel-header {
    font-size: 0.45rem;
    font-weight: 950;
    letter-spacing: 0.2em;
}

.intel-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    height: 32px;
}

.v2-deck-divider {
    width: 1px;
    height: 40px;
    background: linear-gradient(transparent, rgba(255,255,255,0.1), transparent);
    margin: 0 10px;
}

/* Operational Risk Styling */
.is-critical .v2-deck-btn.btn-danger {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.2);
    color: var(--ds-critical);
}

.is-critical .v2-deck-btn.btn-danger:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: var(--ds-critical);
}

.mini-count {
    background: var(--ds-critical);
    color: #fff;
    font-size: 0.5rem;
    font-weight: 950;
    padding: 1px 4px;
    border-radius: 1px;
    box-shadow: 0 0 10px var(--ds-critical);
}

.pulse-urgent {
    animation: ds-pulse-urgent 1s infinite alternate;
}

@keyframes ds-pulse-urgent {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0.8; transform: scale(1.1); }
}

/* F121 Energy volatility */
.energy-volatile {
    border-color: rgba(255, 187, 51, 0.3) !important;
    animation: ds-energy-blink 2s infinite;
}

@keyframes ds-energy-blink {
    50% { border-color: rgba(255, 187, 51, 0.6); }
}
</style>
