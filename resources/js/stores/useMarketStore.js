import { defineStore } from 'pinia';
import api from '../utils/api';

export const useMarketStore = defineStore('market', {
    state: () => ({
        isLoading: false,
        error: null,

        // Core Market State
        economy: {
            state: 'growth',
            label: 'Economic Growth',
            demand: 1.0,
            energy_cost: 1.0,
            hardware_price: 1.0,
            credit_cost: 1.0,
            gdp_growth: 0.025,
            inflation: 0.02,
            global_demand_index: 100,
        },

        regions: [],
        competitors: [],
        sectors: [],

        kpi: {
            demandServedRatio: 0,
            totalDemandGenerated: 0,
            marketShare: 0, // Player global share
        },

        player: {
            globalShare: 0,
            regionalShares: {},
            sectorShares: {},
            arpu: 0,
            innovationIndex: 0,
            riskExposure: 0,
            marketingBudget: 0,
            customerAcquisitionCost: 0,
        },

        // Charts
        demandHistory: [],

        // Used Market
        usedListings: [],
        auctions: [],
        resaleTrends: {
            cpu: 1.0,
            ram: 1.0,
            storage: 1.0,
            motherboard: 1.0,
            last_update: null
        },
    }),

    actions: {
        async fetchMarketState() {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await api.get('/market');

                if (response.success) {
                    this.updateState(response.data);
                }
            } catch (err) {
                console.error('Failed to fetch market state:', err);
                this.error = 'Failed to load market data';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchDemandHistory() {
            try {
                const response = await api.get('/market/history');
                if (response.success) {
                    this.demandHistory = response.data;
                }
            } catch (err) {
                console.error('Failed to fetch demand history:', err);
            }
        },

        async fetchUsedListings() {
            try {
                const response = await api.get('/market/used');
                if (response.success) {
                    this.usedListings = response.data;
                }
            } catch (err) {
                console.error('Failed to fetch used listings:', err);
            }
        },

        async buyUsedItem(listingId) {
            try {
                const response = await api.post('/market/buy', { listing_id: listingId });
                if (response.success) {
                    // Remove from local list immediately
                    this.usedListings = this.usedListings.filter(l => l.id !== listingId);
                    return { success: true, message: response.message };
                }
            } catch (err) {
                return {
                    success: false,
                    error: err.message || 'Purchase failed'
                };
            }
        },

        async fetchAuctions() {
            try {
                const response = await api.get('/auctions');
                if (response.success) {
                    this.auctions = response.data;
                }
            } catch (err) {
                console.error('Failed to fetch auctions:', err);
            }
        },

        async placeBid(auctionId, amount) {
            try {
                const response = await api.post(`/auctions/${auctionId}/bid`, { amount });
                if (response.success) {
                    // Update local auction state
                    const idx = this.auctions.findIndex(a => a.id === auctionId);
                    if (idx !== -1) {
                        this.auctions[idx] = response.auction;
                    }
                    return { success: true };
                }
            } catch (err) {
                return {
                    success: false,
                    error: err.response?.data?.error || err.message || 'Bid failed'
                };
            }
        },

        async fetchResaleTrends() {
            try {
                const response = await api.get('/hardware/resale-trends');
                if (response.success) {
                    this.resaleTrends = response.data;
                }
            } catch (err) {
                console.error('Failed to fetch resale trends:', err);
            }
        },

        async sellComponent(componentId) {
            try {
                const response = await api.post(`/hardware/components/${componentId}/sell`);
                return response;
            } catch (err) {
                return { success: false, error: err.response?.data?.error || 'Sale failed' };
            }
        },

        updateState(data) {
            if (!data) return;

            if (data.economy) this.economy = data.economy;
            if (data.regions) this.regions = data.regions;
            if (data.competitors) this.competitors = data.competitors;
            if (data.sectors) this.sectors = data.sectors;

            if (data.kpi) {
                this.kpi = {
                    ...this.kpi,
                    ...data.kpi
                };
            }

            if (data.player) {
                this.player = data.player;
                this.kpi.marketShare = data.player.globalShare || 0;
            }
        },

        /**
         * Handle WebSocket updates for market data
         */
        handleSocketUpdate(payload) {
            // If the payload contains efficient diffs updates, apply them
            // For now, we assume full state refresh or specific keys
            if (payload.market) {
                this.updateState(payload.market);
            }
        }
    },

    getters: {
        getRegion: (state) => (key) => state.regions.find(r => r.key === key),

        getCompetitor: (state) => (id) => state.competitors.find(c => c.id === id),

        sortedCompetitors: (state) => {
            return [...state.competitors].sort((a, b) => b.marketShare - a.marketShare);
        },

        topCompetitor: (state) => {
            return state.competitors.reduce((prev, current) =>
                (prev.marketShare > current.marketShare) ? prev : current,
                { marketShare: 0 }
            );
        },

        playerRank: (state) => {
            const allShares = [
                ...state.competitors.map(c => c.marketShare),
                state.player.globalShare
            ].sort((a, b) => b - a);

            return allShares.indexOf(state.player.globalShare) + 1;
        }
    }
});
