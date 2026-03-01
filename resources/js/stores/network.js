import { defineStore } from 'pinia';
import { ref, computed, reactive } from 'vue';
import api from '../utils/api';
import { useToastStore } from './toast';

export const useNetworkStore = defineStore('network', () => {
    // ─── State ──────────────────────────────────────────
    const isLoading = ref(false);
    const networksLoaded = ref(false);
    const ispsLoaded = ref(false);

    // Core Network Metrics (from PlayerNetwork)
    const ips = reactive({
        ipv4: { total: 0, used: 0, percent: 0, subnets: [] },
        ipv6: { total: 0, used: 0, percent: 0, subnets: [] }
    });

    const bandwidth = reactive({
        totalCapacityGbps: 0,
        totalUsedGbps: 0,
        saturation: 0,
        contractMbps: 0,
        tier: 'standard',
        tierLabel: 'Standard'
    });

    const metrics = reactive({
        latencyMs: 0,
        packetLoss: 0,
        jitterMs: 0,
        slaCompliance: 100,
        reputation: 100.0,
        healthScore: 100,
        severity: 'nominal'
    });

    const traffic = reactive({
        inGbps: 0,
        outGbps: 0,
        totalGbps: 0,
        ratio: 0
    });

    const infrastructure = reactive({
        asn: null,
        peeringLevel: 0,
        peeringLabel: 'Transit Only',
        peeringScore: 0,
        ddosProtection: 0,
        ddosProtectionLabel: 'None',
        ddosMitigationCapacity: 0,
        ddosEventsTotal: 0,
        lastDdosAt: null,
        bgpRoutes: 0
    });

    const isp = reactive({
        provider: 'generic_transit',
        label: 'Generic Transit',
        tier: 'standard',
        monthlyCost: 0,
        contracts: []
    });

    const regional = reactive({
        latency: {},
        presence: ['eu']
    });

    // Market State
    const market = reactive({
        loaded: false,
        blocks: [],
        currentPool: { total: 0, used: 0, available: 0 },
        marketTrend: { scarcityIndex: 0, priceDirection: 'stable', label: 'BALANCED' }
    });

    const availableIsps = ref([]);
    const ddosTiers = ref([]);

    // Private Networks
    const privateNetworks = ref([]);

    // Dark Fiber
    const darkFiberOptions = ref([]);
    const darkFiberLoaded = ref(false);

    // ─── Getters ────────────────────────────────────────

    const saturationSeverity = computed(() => {
        if (bandwidth.saturation > 90) return 'critical';
        if (bandwidth.saturation > 75) return 'warning';
        if (bandwidth.saturation > 50) return 'caution';
        return 'nominal';
    });

    const saturationLabel = computed(() => {
        if (bandwidth.saturation > 90) return 'CRITICAL_OVERLOAD';
        if (bandwidth.saturation > 75) return 'HIGH_CONGESTION';
        if (bandwidth.saturation > 50) return 'MODERATE_LOAD';
        return 'NOMINAL_LOAD';
    });

    const ipv4Severity = computed(() => {
        if (ips.ipv4.percent > 95) return 'critical';
        if (ips.ipv4.percent > 85) return 'warning';
        if (ips.ipv4.percent > 70) return 'caution';
        return 'nominal';
    });

    const slaSeverity = computed(() => {
        if (metrics.slaCompliance < 95) return 'critical';
        if (metrics.slaCompliance < 98) return 'warning';
        return 'nominal';
    });

    const latencySeverity = computed(() => {
        if (metrics.latencyMs > 100) return 'critical';
        if (metrics.latencyMs > 60) return 'warning';
        if (metrics.latencyMs > 30) return 'caution';
        return 'nominal';
    });

    const currentDdosTier = computed(() => infrastructure.ddosProtection);
    const nextDdosTier = computed(() => ddosTiers.value.find(t => t.level === infrastructure.ddosProtection + 1));
    const hasAsn = computed(() => !!infrastructure.asn);
    const ipv4Available = computed(() => ips.ipv4.total - ips.ipv4.used);

    // ─── Actions ────────────────────────────────────────

    function applyNetworkState(data) {
        if (!data) return;

        // IPs
        if (data.ips) {
            Object.assign(ips.ipv4, data.ips.ipv4);
            Object.assign(ips.ipv6, data.ips.ipv6);
        }

        // Bandwidth
        if (data.bandwidth) Object.assign(bandwidth, data.bandwidth);

        // Metrics
        if (data.metrics) Object.assign(metrics, data.metrics);

        // Traffic
        if (data.traffic) Object.assign(traffic, data.traffic);

        // Infrastructure
        if (data.infrastructure) Object.assign(infrastructure, data.infrastructure);

        // ISP
        if (data.isp) Object.assign(isp, data.isp);

        // Regional
        if (data.regional) Object.assign(regional, data.regional);
    }

    async function loadMarketData() {
        market.loaded = false;
        try {
            const res = await api.get('/network/ipv4-market');
            if (res.success) {
                Object.assign(market, res.data);
                market.loaded = true;
            }
        } catch (e) {
            console.error('Failed to load IPv4 market data', e);
        }
    }

    async function buyIpv4Block(size) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/buy-ipv4', { block_size: size });
            if (res.success) {
                applyNetworkState(res.data);
                await loadMarketData();
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function sellIpv4Block(size) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/sell-ipv4', { block_size: size });
            if (res.success) {
                applyNetworkState(res.data);
                await loadMarketData();
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function establishASN() {
        isLoading.value = true;
        try {
            const res = await api.post('/network/asn');
            if (res.success) {
                applyNetworkState(res.data);
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function upgradePeering() {
        isLoading.value = true;
        try {
            const res = await api.post('/network/peering/upgrade');
            if (res.success) {
                applyNetworkState(res.data);
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function upgradeDdos() {
        isLoading.value = true;
        try {
            const res = await api.post('/network/upgrade-ddos');
            if (res.success) {
                applyNetworkState(res.data);
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function switchIsp(ispId, bandwidthOption) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/switch-isp', {
                isp_id: ispId,
                bandwidth_option: bandwidthOption
            });
            if (res.success) {
                applyNetworkState(res.data.network);
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function loadAvailableIsps() {
        try {
            const res = await api.get('/network/isps');
            if (res.success) {
                availableIsps.value = res.data;
                ispsLoaded.value = true;
            }
        } catch (e) {
            console.error('Failed to load ISPs', e);
        }
    }

    async function allocateSubnet(size) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/allocate-subnet', { size });
            if (res.success) {
                applyNetworkState(res.data);
                useToastStore().success(res.message);
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    // ─── Private Networks ───────────────────────────────

    async function loadPrivateNetworks() {
        try {
            const res = await api.get('/network/private');
            if (res.success) {
                privateNetworks.value = res.data;
                networksLoaded.value = true;
            }
        } catch (e) {
            console.error('Failed to load private networks', e);
        }
    }

    async function createPrivateNetwork(name, cidr) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/private', { name, cidr });
            if (res.success) {
                // The backend returns the new network or we can just reload
                await loadPrivateNetworks();
                useToastStore().success('Private network created');
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function deletePrivateNetwork(id) {
        isLoading.value = true;
        try {
            const res = await api.delete(`/network/private/${id}`);
            if (res.success) {
                privateNetworks.value = privateNetworks.value.filter(n => n.id !== id);
                useToastStore().success('Private network deleted');
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function attachServerToNetwork(networkId, serverId) {
        isLoading.value = true;
        try {
            const res = await api.post(`/network/private/${networkId}/attach`, { server_id: serverId });
            if (res.success) {
                await loadPrivateNetworks();
                useToastStore().success('Server attached to network');
                return true;
            } else {
                useToastStore().error(res.error);
            }
        } catch (e) {
            useToastStore().error(e.message || 'Failed to attach server');
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function detachServerFromNetwork(serverId) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/private/detach', { server_id: serverId });
            if (res.success) {
                await loadPrivateNetworks();
                useToastStore().success('Server detached from network');
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function addFirewallRule(networkId, ruleData) {
        isLoading.value = true;
        try {
            const res = await api.post(`/network/private/${networkId}/firewall`, ruleData);
            if (res.success) {
                const net = privateNetworks.value.find(n => n.id === networkId);
                if (net) {
                    if (!net.firewallRules) net.firewallRules = [];
                    net.firewallRules.push(res.data);
                    net.firewallRules.sort((a, b) => a.priority - b.priority);
                }
                useToastStore().success('Firewall rule added');
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function deleteFirewallRule(networkId, ruleId) {
        isLoading.value = true;
        try {
            const res = await api.delete(`/network/private/firewall/${ruleId}`);
            if (res.success) {
                const net = privateNetworks.value.find(n => n.id === networkId);
                if (net && net.firewallRules) {
                    net.firewallRules = net.firewallRules.filter(r => r.id !== ruleId);
                }
                useToastStore().success('Firewall rule deleted');
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    async function testFirewallRule(networkId, testData) {
        try {
            const res = await api.post(`/network/private/${networkId}/test`, testData);
            return res;
        } catch (e) {
            return { success: false, error: e.message || 'Test failed' };
        }
    }

    async function loadDarkFiberOptions() {
        try {
            const res = await api.get('/network/dark-fiber/options');
            if (res.success) {
                darkFiberOptions.value = res.data;
                darkFiberLoaded.value = true;
            }
        } catch (e) {
            console.error('Failed to load Dark Fiber options', e);
        }
    }

    async function leaseDarkFiber(region_a, region_b) {
        isLoading.value = true;
        try {
            const res = await api.post('/network/dark-fiber/lease', { region_a, region_b });
            if (res.success) {
                useToastStore().success(res.message);
                await loadDarkFiberOptions();
                return true;
            }
        } finally {
            isLoading.value = false;
        }
        return false;
    }

    return {
        // State
        isLoading,
        ips,
        bandwidth,
        metrics,
        traffic,
        infrastructure,
        isp,
        regional,
        market,
        availableIsps,
        ispsLoaded,
        ddosTiers,
        // Getters
        saturationSeverity,
        saturationLabel,
        ipv4Severity,
        slaSeverity,
        latencySeverity,
        currentDdosTier,
        nextDdosTier,
        hasAsn,
        ipv4Available,
        // Actions
        applyNetworkState,
        loadMarketData,
        buyIpv4Block,
        sellIpv4Block,
        establishASN,
        upgradePeering,
        upgradeDdos,
        switchIsp,
        loadAvailableIsps,
        allocateSubnet,

        // Private Networks
        privateNetworks,
        networksLoaded,
        loadPrivateNetworks,
        createPrivateNetwork,
        deletePrivateNetwork,
        attachServerToNetwork,
        detachServerFromNetwork,
        addFirewallRule,
        deleteFirewallRule,
        testFirewallRule,

        // Dark Fiber
        darkFiberOptions,
        darkFiberLoaded,
        loadDarkFiberOptions,
        leaseDarkFiber
    };
});
