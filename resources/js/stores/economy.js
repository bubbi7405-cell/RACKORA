import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';
import SoundManager from '../services/SoundManager';

/**
 * Economy Store
 * Owns: Player economy (balance, income, expenses, reputation, level, XP),
 *       transactions, energy market, game speed/pause
 */
export const useEconomyStore = defineStore('economy', () => {
    // ─── State ──────────────────────────────────────────
    const isLoading = ref(false);

    const player = ref({
        id: null,
        name: '',
        economy: {
            balance: 0,
            hourlyIncome: 0,
            hourlyExpenses: 0,
            netIncomePerHour: 0,
            reputation: 50,
            level: 1,
            experience: { current: 0, forNextLevel: 100, progress: 0 },
        },
        tutorial_step: 0,
        tutorial_completed: false,
    });

    const gameSpeed = ref(1);
    const isPaused = ref(false);

    const customers = ref({
        total: 0,
        active: 0,
        unhappy: 0,
        churning: 0,
        list: [],
    });

    const orders = ref({
        pending: [],
        provisioning: [],
        urgentCount: 0,
    });

    const energyMarket = ref({
        spotPrice: 0.12,
        history: [],
        offers: [],
        currentContract: null,
        storage: {
            total_capacity: 0,
            current_level: 0,
            battery_count: 0
        },
        greenScore: 0,
        policies: [],
        activePolicies: [],
        isVolatile: false, // F121
    });

    const marketShare = ref({
        participants: [],
        player: null,
    });

    const stockMarket = ref({
        stockPrice: 10.0,
        shortPositions: [],
        isFrozen: false,
        freezeEndsAt: null,
    });

    // Feature: Global Market Simulation
    const globalMarket = reactive({
        demand: {
            web: { value: 65, trend: 'stable', label: 'WEB_HOSTING' },
            ai: { value: 88, trend: 'up', label: 'AI_COMPUTE' },
            storage: { value: 45, trend: 'down', label: 'DATA_STORAGE' },
            streaming: { value: 72, trend: 'up', label: 'MEDIA_STREAMING' }
        },
        regionalDemand: {}, // Populated with { us_east: { web: 70, ai: 40 }, ... }
        marketEvents: [],
        competitionIdx: 1.0
    });

    // ─── Getters ────────────────────────────────────────

    const balance = computed(() => player.value.economy.balance);
    const reputation = computed(() => player.value.economy.reputation);
    const level = computed(() => player.value.economy.level);

    const netIncome = computed(() =>
        player.value.economy.hourlyIncome - player.value.economy.hourlyExpenses
    );

    const isBalanceLow = computed(() => player.value.economy.balance < 500);
    const isReputationCritical = computed(() => player.value.economy.reputation < 30);

    const pendingOrderCount = computed(() => orders.value.pending?.length || 0);

    // ─── State Application ──────────────────────────────

    /**
     * Apply economy-related data from the game state payload.
     * Called by the orchestrator (game.js) during applyGameState.
     */
    function applyState(data) {
        if (!data) return;

        if (data.player) {
            player.value.id = data.player.id;
            player.value.name = data.player.name;
            if (data.player.economy) {
                Object.assign(player.value.economy, data.player.economy);
                if (data.player.economy.gameSpeed !== undefined) gameSpeed.value = data.player.economy.gameSpeed;
                if (data.player.economy.isPaused !== undefined) isPaused.value = data.player.economy.isPaused;
            }
            player.value.tutorial_step = data.player.tutorial_step || 0;
            player.value.tutorial_completed = data.player.tutorial_completed || false;
        }

        if (data.customers) {
            Object.assign(customers.value, data.customers);
        }

        if (data.orders) {
            Object.assign(orders.value, data.orders);
            if (!Array.isArray(orders.value.pending)) orders.value.pending = [];
        }

        if (data.marketShare) {
            Object.assign(marketShare.value, data.marketShare);
        }

        // F121: Energy volatility flag from backend
        if (data.isEnergyVolatile !== undefined) {
            energyMarket.value.isVolatile = data.isEnergyVolatile;
        }
        if (data.energy) {
            energyMarket.value.spotPrice = data.energy.spotPrice ?? energyMarket.value.spotPrice;
        }

        if (data.globalMarket) {
            Object.assign(globalMarket, data.globalMarket);
        }
    }

    // ─── Economy WS Handler ─────────────────────────────

    function handleEconomyTick(data) {
        if (data.economy) {
            Object.assign(player.value.economy, data.economy);
            if (data.economy.gameSpeed !== undefined) gameSpeed.value = data.economy.gameSpeed;
            if (data.economy.isPaused !== undefined) isPaused.value = data.economy.isPaused;
        }
        if (data.pendingOrders !== undefined) {
            orders.value.urgentCount = data.pendingOrders;
        }
    }

    // ─── Actions ────────────────────────────────────────

    async function initializePlayer() {
        const toast = useToastStore();
        try {
            const response = await api.post('/game/initialize');
            if (response.success) {
                toast.success('Welcome to Rackora! Your empire begins now.');
                return response.data;
            }
        } catch (error) {
            console.error('Failed to initialize player:', error);
            toast.error('Failed to start new game');
        }
        return null;
    }

    async function setGameSpeed(speed) {
        try {
            // Optimistic update
            if (speed === 0) { isPaused.value = true; }
            else { isPaused.value = false; gameSpeed.value = speed; }

            const response = await api.post('/game/speed', { speed });
            if (response.success) {
                gameSpeed.value = response.speed;
                isPaused.value = response.paused;
                return true;
            }
        } catch (error) {
            console.error('Failed to set game speed', error);
            useToastStore().error('Failed to set game speed');
            return false;
        }
    }

    async function loadTransactions(page = 1, filters = {}) {
        try {
            const params = new URLSearchParams({ page });
            if (filters.type) params.append('type', filters.type);
            if (filters.category) params.append('category', filters.category);
            if (filters.hours) params.append('hours', filters.hours);

            const response = await api.get(`/economy/transactions?${params}`);
            if (response.success) {
                return response.data;
            }
        } catch (error) {
            console.error('Failed to load transactions', error);
        }
        return null;
    }

    async function cancelOrder(orderId) {
        const toast = useToastStore();
        isLoading.value = true;
        try {
            const response = await api.post(`/orders/${orderId}/cancel`);
            if (response.success) {
                toast.warning(response.message || 'Order cancelled. Reputation -5.');
                SoundManager.playError();
                return true;
            }
        } catch (error) {
            toast.error(error.response?.data?.error || 'Failed to cancel order');
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function submitBid(orderId, bid) {
        isLoading.value = true;
        try {
            const response = await api.post(`/negotiation/${orderId}/bid`, bid);
            if (response.success) {
                if (response.data.success) {
                    useToastStore().success(response.data.message);
                } else {
                    useToastStore().warning(response.data.message);
                }
                return response.data;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Negotiation failed');
        } finally {
            isLoading.value = false;
        }
        return null;
    }

    async function getBidPreview(orderId, bid) {
        try {
            const response = await api.post(`/negotiation/${orderId}/preview`, bid);
            if (response.success) {
                return response.probability;
            }
        } catch (error) {
            console.error('Failed to get bid preview', error);
        }
        return 0;
    }

    async function updateTutorialProgress(step, completed = false) {
        player.value.tutorial_step = step;
        if (completed) player.value.tutorial_completed = true;
        try {
            await api.post('/game/tutorial', { step, completed });
        } catch (e) {
            console.error('Failed to sync tutorial progress', e);
        }
    }

    // ─── Energy Actions ─────────────────────────────────

    async function loadEnergyData() {
        try {
            const response = await api.get('/energy');
            if (response.success) {
                energyMarket.value.spotPrice = response.spot_price;
                energyMarket.value.history = response.price_history;
                energyMarket.value.offers = response.offers;
                energyMarket.value.policies = response.policies;
                energyMarket.value.activePolicies = response.active_policies;
                energyMarket.value.currentContract = response.current_contract;
                energyMarket.value.storage = response.storage;
                energyMarket.value.greenScore = response.green_score;
            }
        } catch (error) {
            console.error('Failed to load energy data', error);
        }
    }

    async function signEnergyContract(type) {
        isLoading.value = true;
        try {
            const response = await api.post('/energy/sign', { type });
            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                await loadEnergyData();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Failed to sign contract');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function toggleEnergyPolicy(policyKey) {
        isLoading.value = true;
        try {
            const response = await api.post('/energy/policy', { policy: policyKey });
            if (response.success) {
                useToastStore().success(response.message);
                SoundManager.playSuccess();
                await loadEnergyData();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Failed to update policy');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Stock Market Actions ───────────────────────────

    async function loadStockMarketData() {
        try {
            const response = await api.get('/stock-market');
            if (response.success) {
                Object.assign(stockMarket.value, response.data);
            }
        } catch (error) {
            console.error('Failed to load stock market data', error);
        }
    }

    async function shortOwnStock(shares) {
        isLoading.value = true;
        try {
            const response = await api.post('/stock-market/short', { shares });
            if (response.success) {
                useToastStore().warning(`Position opened: Shorted ${shares} shares.`);
                SoundManager.playSuccess();
                await loadStockMarketData();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Short-selling failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function closeShortPosition(positionId) {
        isLoading.value = true;
        try {
            const response = await api.post('/stock-market/close', { position_id: positionId });
            if (response.success) {
                const p = response.profit;
                const msg = p >= 0 ? `Profit: $${p.toLocaleString()}` : `Loss: $${Math.abs(p).toLocaleString()}`;
                useToastStore().info(`Position closed. ${msg}`);
                SoundManager.playSuccess();
                await loadStockMarketData();
                return true;
            }
        } catch (error) {
            useToastStore().error(error.response?.data?.error || 'Closing position failed');
            SoundManager.playError();
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    // ─── Return ─────────────────────────────────────────
    return {
        // State
        isLoading,
        player,
        gameSpeed,
        isPaused,
        customers,
        orders,
        energyMarket,
        marketShare,
        globalMarket,
        // Getters
        balance,
        reputation,
        level,
        netIncome,
        isBalanceLow,
        isReputationCritical,
        pendingOrderCount,
        // State application
        applyState,
        // WS handler
        handleEconomyTick,
        // Actions
        initializePlayer,
        setGameSpeed,
        loadTransactions,
        cancelOrder,
        submitBid,
        getBidPreview,
        updateTutorialProgress,
        loadEnergyData,
        signEnergyContract,
        toggleEnergyPolicy,
        stockMarket,
        loadStockMarketData,
        shortOwnStock,
        closeShortPosition,
    };
});
