<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\PlayerNetwork;
use App\Models\PeeringAgreement;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\BandwidthContract;
use App\Models\IpAllocation;
use App\Models\GameEvent;
use App\Enums\EventType;
use Illuminate\Support\Facades\DB;

class NetworkService
{
    /**
     * Available ISP providers with pricing and characteristics.
     */
    public const ISP_CATALOG = [
        'generic_transit' => [
            'name' => 'Generic Transit',
            'tier' => 'tier3',
            'base_latency_ms' => 40,
            'reliability' => 0.96, // 4% chance of micro-outage per tick
            'bandwidth_options' => [
                ['mbps' => 1000,  'monthly' => 50,   'commit' => 'monthly'],
                ['mbps' => 2000,  'monthly' => 90,   'commit' => 'monthly'],
                ['mbps' => 5000,  'monthly' => 200,  'commit' => 'annual'],
            ],
            'regions' => ['eu'],
            'unlock_level' => 1,
        ],
        'tier2_regional' => [
            'name' => 'RegionalConnect',
            'tier' => 'tier2',
            'base_latency_ms' => 25,
            'reliability' => 0.985, // 1.5% chance
            'bandwidth_options' => [
                ['mbps' => 5000,  'monthly' => 180,  'commit' => 'monthly'],
                ['mbps' => 10000, 'monthly' => 320,  'commit' => 'annual'],
                ['mbps' => 20000, 'monthly' => 580,  'commit' => 'annual'],
            ],
            'regions' => ['eu', 'us-east'],
            'unlock_level' => 5,
        ],
        'tier1_global' => [
            'name' => 'GlobalTier Networks',
            'tier' => 'tier1',
            'base_latency_ms' => 12,
            'reliability' => 0.999,
            'bandwidth_options' => [
                ['mbps' => 10000, 'monthly' => 400,   'commit' => 'annual'],
                ['mbps' => 50000, 'monthly' => 1500,  'commit' => 'annual'],
                ['mbps' => 100000,'monthly' => 2800,  'commit' => 'multi_year'],
            ],
            'regions' => ['eu', 'us-east', 'us-west', 'asia'],
            'unlock_level' => 12,
        ],
        'premium_enterprise' => [
            'name' => 'Enterprise Direct',
            'tier' => 'tier1',
            'base_latency_ms' => 5,
            'reliability' => 0.9999,
            'bandwidth_options' => [
                ['mbps' => 100000, 'monthly' => 5000,  'commit' => 'multi_year'],
                ['mbps' => 500000, 'monthly' => 18000, 'commit' => 'multi_year'],
            ],
            'regions' => ['eu', 'us-east', 'us-west', 'asia', 'oceania'],
            'unlock_level' => 20,
        ],
    ];

    /**
     * Regional base latencies (ms) from datacenter origin (EU default).
     */
    public const REGIONAL_BASE_LATENCY = [
        'eu'       => 5,
        'us-east'  => 80,
        'us-west'  => 120,
        'asia'     => 180,
        'oceania'  => 250,
        'sa'       => 200,
        'africa'   => 220,
    ];

    public function __construct(
        protected ResearchService $researchService
    ) {}

    // ─── INITIALIZATION ─────────────────────────────────

    /**
     * Initialize network for a new user.
     */
    public function initializeNetwork(User $user): PlayerNetwork
    {
        $network = PlayerNetwork::firstOrCreate(
            ['user_id' => $user->id],
            [
                'ipv4_total' => 16,
                'ipv4_used' => 0,
                'ipv6_total' => 65536,
                'ipv6_used' => 0,
                'ipv4_subnets' => [
                    ['cidr' => '/28', 'size' => 16, 'allocated' => 0, 'label' => 'Initial Block'],
                ],
                'isp_provider' => 'generic_transit',
                'bandwidth_contract_mbps' => 1000,
                'bandwidth_contract_cost' => 50.00,
                'bandwidth_tier' => 'standard',
                'regional_latency' => self::REGIONAL_BASE_LATENCY,
                'regional_presence' => ['eu'],
                'network_reputation' => 100.00,
                'sla_compliance_rate' => 100.00,
            ]
        );

        // Create initial bandwidth contract
        BandwidthContract::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            [
                'isp_name' => 'Generic Transit',
                'isp_tier' => 'tier3',
                'capacity_mbps' => 1000,
                'monthly_cost' => 50.00,
                'commitment' => 'monthly',
                'burst_ratio' => 1.0,
                'regions' => ['eu'],
            ]
        );

        return $network;
    }

    // ─── GAME STATE ─────────────────────────────────────

    /**
     * Get the comprehensive game state representation of the network.
     */
    public function getNetworkGameState(PlayerNetwork $network): array
    {
        $user = $network->user;
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers.activeOrders'])
            ->get();

        // Sum bandwidth usage
        $totalBandwidthUsedGbps = 0;
        $totalBandwidthCapacityGbps = 0;

        foreach ($rooms as $room) {
            $totalBandwidthUsedGbps += $room->getCurrentBandwidthUsage();
            $totalBandwidthCapacityGbps += $room->bandwidth_gbps;
        }

        // Apply Research Bonus: Tier-1 Peering
        $capacityBonus = $this->researchService->getBonus($user, 'bandwidth_capacity_bonus');
        $empService = app(\App\Services\Game\EmployeeService::class);
        
        $capacityMultiplier = $empService->getAggregatedBonus($user, 'capacity_bonus_multiplier');
        if ($capacityMultiplier > 0) {
            $capacityBonus *= $capacityMultiplier;
        }

        $totalBandwidthCapacityGbps *= (1.0 + $capacityBonus);

        // Apply Staff Bonus: Network Engineer (Optimization)
        $engineerBonus = $user->employees()->where('type', 'network_engineer')->sum('efficiency') * 0.05;
        $totalBandwidthCapacityGbps *= (1.0 + $engineerBonus);
        
        // Apply SysAdmin Bonus: Network Guru (throughput)
        $throughputBonus = $empService->getAggregatedBonus($user, 'throughput_bonus');
        if ($throughputBonus > 1.0) {
            $totalBandwidthCapacityGbps *= $throughputBonus;
        }

        // Calculate saturation
        $saturation = $totalBandwidthCapacityGbps > 0
            ? ($totalBandwidthUsedGbps / $totalBandwidthCapacityGbps)
            : 0;

        // Dynamic Latency
        $latency = $this->calculateDynamicLatency($user, $network, $saturation, $peeringLatencyBonus ?? 1.0);

        // Packet Loss
        $packetLoss = $this->calculatePacketLoss($user, $saturation);

        // SLA Compliance
        $sla = 100.0 - ($packetLoss * 500) - (max(0, $latency - 50) / 10);
        $sla = max(0, min(100, $sla));

        // Traffic data
        $trafficIn = (float) $network->traffic_in_gbps;
        $trafficOut = (float) $network->traffic_out_gbps;

        // Active contracts
        $contracts = BandwidthContract::where('user_id', $user->id)
            ->active()
            ->get();

        $peeringAgreements = PeeringAgreement::where('user_id', $user->id)
            ->active()
            ->get();
        
        // Cumulative Latency Bonus from peering (capped at 50% reduction)
        $peeringLatencyBonus = 1.0;
        foreach ($peeringAgreements as $pa) {
            $peeringLatencyBonus *= (float)$pa->latency_bonus;
            $totalBandwidthCapacityGbps += $pa->capacity_gbps;
        }
        $peeringLatencyBonus = max(0.5, $peeringLatencyBonus);

        return [
            'ips' => [
                'ipv4' => [
                    'total' => $network->ipv4_total,
                    'used' => $network->ipv4_used,
                    'percent' => $network->getIpv4UsagePercent(),
                    'subnets' => $network->ipv4_subnets ?? [],
                ],
                'ipv6' => [
                    'total' => $network->ipv6_total,
                    'used' => $network->ipv6_used,
                    'percent' => $network->getIpv6UsagePercent(),
                    'subnets' => $network->ipv6_subnets ?? [],
                ],
            ],
            'bandwidth' => [
                'totalCapacityGbps' => (float) $totalBandwidthCapacityGbps,
                'totalUsedGbps' => (float) $totalBandwidthUsedGbps,
                'saturation' => (float) ($saturation * 100),
                'contractMbps' => $network->bandwidth_contract_mbps,
                'tier' => $network->bandwidth_tier,
                'tierLabel' => $network->getBandwidthTierLabel(),
            ],
            'metrics' => [
                'latencyMs' => (float) round($latency, 2),
                'packetLoss' => (float) round($packetLoss, 4),
                'jitterMs' => (float) round($network->jitter_ms, 2),
                'slaCompliance' => (float) round($sla, 2),
                'reputation' => (float) $network->network_reputation,
                'healthScore' => $network->getNetworkHealthScore(),
                'severity' => $network->getNetworkSeverity(),
            ],
            'traffic' => [
                'inGbps' => $trafficIn,
                'outGbps' => $trafficOut,
                'totalGbps' => $trafficIn + $trafficOut,
                'ratio' => $trafficOut > 0 ? round($trafficIn / max(0.001, $trafficOut), 2) : 0,
            ],
            'infrastructure' => [
                'asn' => $network->asn,
                'peeringLevel' => $network->peering_level,
                'peeringLabel' => $network->getPeeringLabel(),
                'peeringScore' => (float) $network->peering_score,
                'ddosProtection' => $network->ddos_protection_level,
                'ddosProtectionLabel' => $network->getDdosProtectionLabel(),
                'ddosMitigationCapacity' => $network->getDdosMitigationCapacity(),
                'firewallBonus' => app(PrivateNetworkService::class)->getFirewallMitigationBonus($user),
                'ddosEventsTotal' => $network->ddos_events_total,
                'lastDdosAt' => $network->last_ddos_at?->toIso8601String(),
                'bgpRoutes' => $network->bgp_routes_announced,
            ],
            'isp' => [
                'provider' => $network->isp_provider,
                'label' => $network->getIspLabel(),
                'tier' => $network->bandwidth_tier,
                'monthlyCost' => (float) $network->bandwidth_contract_cost,
                'contracts' => $contracts->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->isp_name,
                    'tier' => $c->isp_tier,
                    'capacityMbps' => $c->capacity_mbps,
                    'effectiveMbps' => $c->getEffectiveCapacityMbps(),
                    'monthlyCost' => (float) $c->monthly_cost,
                    'commitment' => $c->commitment,
                    'regions' => $c->regions,
                    'status' => $c->status,
                ])->toArray(),
            ],
            'regional' => [
                'latency' => $network->regional_latency ?? self::REGIONAL_BASE_LATENCY,
                'presence' => $network->regional_presence ?? ['eu'],
            ],
            'peering' => $peeringAgreements->map(fn($pa) => [
                'id' => $pa->id,
                'provider' => $pa->provider_name,
                'tier' => $pa->tier,
                'capacity' => $pa->capacity_gbps,
                'bonus' => (float)$pa->latency_bonus,
                'monthlyCost' => (float)$pa->monthly_cost
            ])->toArray()
        ];
    }

    // ─── NETWORK CALCULATIONS ───────────────────────────

    /**
     * Calculate dynamic latency based on ISP, saturation, and research.
     */
    private function calculateDynamicLatency(User $user, PlayerNetwork $network, float $saturation, float $peeringBonus = 1.0): float
    {
        // Base latency from ISP provider
        $ispConfig = self::ISP_CATALOG[$network->isp_provider] ?? self::ISP_CATALOG['generic_transit'];
        $baseLatency = $ispConfig['base_latency_ms'];

        // Saturation penalty
        $latency = $baseLatency + ($saturation * 60.0);

        // Overload spike
        if ($saturation > 1.2) {
            $latency += ($saturation - 1.2) * 500;
        }

        // Research bonus: Anycast / routing optimization
        $latencyReduction = $this->researchService->getBonus($user, 'latency_reduction');
        $latency *= (1.0 - $latencyReduction);

        // Staff bonus: Network Engineer
        $netStaffBonus = $user->employees()->where('type', 'network_engineer')->sum('efficiency') * 0.10; // 10% reduction per engineer
        $latency *= (1.0 - $netStaffBonus);

        // Peering bonus (Contract-based)
        $latency *= $peeringBonus;

        // Legacy Peering Level Bonus (backward compatibility)
        if ($network->peering_level >= 2) {
            $latency *= 0.85; // 15% reduction for premium peering
        } elseif ($network->peering_level >= 1) {
            $latency *= 0.92; // 8% reduction for community peering
        }

        // Apply Regional Modifiers (World Events targeting the player's region)
        $playerRoom = \App\Models\GameRoom::where('user_id', $user->id)->first();
        $playerRegion = $playerRoom?->region;
        $worldMods = $playerRegion
            ? \App\Models\WorldEvent::getActiveModifiersForRegion($playerRegion)
            : \App\Models\WorldEvent::getActiveModifiers();
        
        $crisisService = app(\App\Services\Game\GlobalCrisisService::class);
        $crisisMods = $crisisService->getActiveModifiers($user);

        $latency += ($worldMods['latency'] ?? 0);
        $latency += ($crisisMods['latency'] ?? 0);

        // Apply SysAdmin & Network Engineer specializations
        $empService = app(\App\Services\Game\EmployeeService::class);
        $latencyFlat = $empService->getAggregatedBonus($user, 'latency_reduction_flat');
        if ($latencyFlat > 0) {
            $latency -= $latencyFlat;
        }

        $jitterBonus = $empService->getAggregatedBonus($user, 'jitter_reduction');
        if ($jitterBonus > 0) {
            $latency *= (1.0 - min(0.9, $jitterBonus));
        }

        // --- FEATURE 58: ORBITAL REDUNDANCY (Satellite Failover) ---
        $isOrbitalActive = $this->isOrbitalFailoverActive($user);
        if ($isOrbitalActive) {
            // Satellite is high latency, but better than being completely down (e.g. 500ms ms vs infinite)
            $latency = max(400, $latency + 350); 
        }

        // ISP Micro-Outage Impact
        $hasMicroOutage = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('title', 'LIKE', '%ISP_MICRO_OUTAGE%')
            ->where('status', 'active')
            ->exists();
        if ($hasMicroOutage) {
            $latency += 15.0; // Flat +15ms jitter/latency
        }

        return max(1.0, $latency);
    }

    /**
     * Calculate packet loss based on saturation and active events.
     */
    private function calculatePacketLoss(User $user, float $saturation): float
    {
        $loss = 0.0;
        
        // ISP Micro-Outage Impact
        $hasMicroOutage = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('title', 'LIKE', '%ISP_MICRO_OUTAGE%')
            ->where('status', 'active')
            ->exists();
        if ($hasMicroOutage) {
            $loss += 0.015; // 1.5% base loss
        }

        if ($saturation <= 0.9) return $loss;

        // Linear increase: 1% per 10% saturation above 90%
        $loss += ($saturation - 0.9) * 0.1;

        // Exponential increase above 120% saturation
        if ($saturation > 1.2) {
            $loss += pow(($saturation - 1.2), 2) * 0.5;
        }

        return min(0.15, $loss); // Cap at 15%
    }

    // ─── ISP & BANDWIDTH MANAGEMENT ────────────────────

    /**
     * Get available ISPs for the user's level.
     */
    public function getAvailableIsps(User $user): array
    {
        $level = $user->economy->level ?? 1;

        return collect(self::ISP_CATALOG)
            ->filter(fn($isp) => $isp['unlock_level'] <= $level)
            ->map(fn($isp, $key) => array_merge($isp, ['id' => $key]))
            ->values()
            ->toArray();
    }

    /**
     * Switch ISP provider.
     */
    public function switchIsp(User $user, string $ispId, int $bandwidthOptionIndex): array
    {
        $network = $user->network;
        if (!$network) {
            return ['success' => false, 'message' => 'Network not initialized'];
        }

        $isp = self::ISP_CATALOG[$ispId] ?? null;
        if (!$isp) {
            return ['success' => false, 'message' => 'Unknown ISP provider'];
        }

        // Level check
        $level = $user->economy->level ?? 1;
        if ($isp['unlock_level'] > $level) {
            return ['success' => false, 'message' => "Requires level {$isp['unlock_level']}"];
        }

        // Get bandwidth option
        $option = $isp['bandwidth_options'][$bandwidthOptionIndex] ?? null;
        if (!$option) {
            return ['success' => false, 'message' => 'Invalid bandwidth option'];
        }

        // Deactivate old contracts
        BandwidthContract::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'terminated']);

        // Create new contract
        $contract = BandwidthContract::create([
            'user_id' => $user->id,
            'isp_name' => $isp['name'],
            'isp_tier' => $isp['tier'],
            'capacity_mbps' => $option['mbps'],
            'monthly_cost' => $option['monthly'],
            'commitment' => $option['commit'],
            'burst_ratio' => ($isp['tier'] === 'tier1') ? 1.5 : 1.2,
            'regions' => $isp['regions'],
        ]);

        // Update network
        $network->isp_provider = $ispId;
        $network->bandwidth_contract_mbps = $option['mbps'];
        $network->bandwidth_contract_cost = $option['monthly'];
        $network->bandwidth_tier = match($isp['tier']) {
            'tier1' => 'enterprise',
            'tier2' => 'premium',
            default => 'standard',
        };
        $network->save();

        return [
            'success' => true,
            'message' => "Switched to {$isp['name']} — {$option['mbps']} Mbps",
            'contract' => $contract,
        ];
    }

    // ─── SUBNET MANAGEMENT ──────────────────────────────

    /**
     * Allocate a new IPv4 subnet.
     */
    public function allocateSubnet(User $user, int $size): array
    {
        $network = $user->network;
        if (!$network) {
            return ['success' => false, 'message' => 'Network not initialized'];
        }

        // Available sizes and their costs
        $subnetPricing = [
            16 => ['cidr' => '/28', 'cost' => 200],
            32 => ['cidr' => '/27', 'cost' => 500],
            64 => ['cidr' => '/26', 'cost' => 1200],
            128 => ['cidr' => '/25', 'cost' => 3000],
            256 => ['cidr' => '/24', 'cost' => 7500],
        ];

        $pricing = $subnetPricing[$size] ?? null;
        if (!$pricing) {
            return ['success' => false, 'message' => 'Invalid subnet size'];
        }

        // Check balance
        if (($user->economy->balance ?? 0) < $pricing['cost']) {
            return ['success' => false, 'message' => 'Insufficient funds'];
        }

        // Purchase
        $user->economy->balance -= $pricing['cost'];
        $user->economy->save();

        // Add subnet
        $subnets = $network->ipv4_subnets ?? [];
        $subnets[] = [
            'cidr' => $pricing['cidr'],
            'size' => $size,
            'allocated' => 0,
            'label' => "Block " . (count($subnets) + 1),
        ];
        $network->ipv4_subnets = $subnets;
        $network->ipv4_total += $size;
        $network->save();

        return [
            'success' => true,
            'message' => "Allocated {$pricing['cidr']} subnet ({$size} IPs)",
            'cost' => $pricing['cost'],
        ];
    }

    /**
     * Allocate a cheap but "dirty" IPv4 subnet from the black market.
     */
    public function allocateBlackMarketSubnet(User $user, int $size): array
    {
        $network = $user->network;
        if (!$network) return ['success' => false, 'message' => 'Network not initialized'];

        $pricing = [
            16 => ['cidr' => '/28', 'cost' => 50],
            64 => ['cidr' => '/26', 'cost' => 300],
            256 => ['cidr' => '/24', 'cost' => 1800],
        ];

        $p = $pricing[$size] ?? null;
        if (!$p) return ['success' => false, 'message' => 'Invalid size'];

        if ($user->economy->balance < $p['cost']) return ['success' => false, 'message' => 'Insufficient funds'];

        if (!$user->economy->debit($p['cost'], "Black Market IPv4: {$p['cidr']}", 'black_market')) {
            return ['success' => false, 'message' => 'Transaction failed'];
        }

        $subnets = $network->ipv4_subnets ?? [];
        $subnets[] = [
            'cidr' => $p['cidr'],
            'size' => $size,
            'allocated' => 0,
            'label' => "Black Market Block " . (count($subnets) + 1),
            'is_black_market' => true,
        ];
        $network->ipv4_subnets = $subnets;
        $network->ipv4_total += $size;
        
        // Tracking risk
        $metadata = $network->metadata ?? [];
        $metadata['black_market_ip_count'] = ($metadata['black_market_ip_count'] ?? 0) + $size;
        $network->metadata = $metadata;
        
        $network->save();

        \App\Models\GameLog::log($user, "Acquired {$size} IPs via the Dark Web. High risk of blacklisting!", 'warning', 'network');

        return [
            'success' => true,
            'message' => "Black market subnet acquired!",
            'cost' => $p['cost']
        ];
    }

    // ─── TRAFFIC SIMULATION ─────────────────────────────

    /**
     * Calculate aggregate traffic from all active servers.
     */
    public function getAggregateTraffic(User $user): array
    {
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers' => function ($q) {
                $q->where('status', 'online');
            }])
            ->get();

        $totalIn = 0;
        $totalOut = 0;

        foreach ($rooms as $room) {
            foreach ($room->racks as $rack) {
                foreach ($rack->servers as $server) {
                    $traffic = $this->calculateServerTraffic($server);
                    $totalIn += $traffic['in'];
                    $totalOut += $traffic['out'];
                }
            }
        }

        return [
            'in' => round($totalIn, 3),
            'out' => round($totalOut, 3),
            'total' => round($totalIn + $totalOut, 3),
        ];
    }

    /**
     * Calculate traffic for a single server.
     */
    private function calculateServerTraffic($server): array
    {
        // Base traffic per active order
        $orderCount = $server->activeOrders ? $server->activeOrders->count() : 0;
        $bandwidthMbps = $server->bandwidth_mbps ?? 1000;

        // Utilization factor (0-1)
        $utilization = min(1.0, $orderCount / max(1, $server->vserver_capacity ?? 1));

        // Traffic in Gbps
        $traffic = ($bandwidthMbps / 1000) * $utilization;

        // Asymmetric traffic (more outbound for hosting)
        return [
            'in' => $traffic * 0.3,  // 30% inbound
            'out' => $traffic * 0.7,  // 70% outbound
        ];
    }

    // ─── IP PROVISIONING ────────────────────────────────

    /**
     * Assign IPs when an order is activated.
     */
    public function provisionIPs(CustomerOrder $order): void
    {
        $network = $order->customer->user->network;
        if (!$network) return;

        $needsDedip = $order->requirements['dedicated_ip'] ?? false;
        $ipCount = $needsDedip ? 2 : 1;

        $network->ipv4_used += $ipCount;
        $network->ipv6_used += rand(1, 10);
        $network->save();

        // Create IP allocation records
        for ($i = 0; $i < $ipCount; $i++) {
            IpAllocation::create([
                'user_id' => $order->customer->user_id,
                'server_id' => $order->server_id,
                'order_id' => $order->id,
                'type' => 'ipv4',
                'address' => $this->generateFakeIp('v4'),
                'purpose' => $i === 0 ? 'server' : 'customer',
                'status' => 'allocated',
            ]);
        }

        // Store in order for display
        $reqs = $order->requirements;
        $reqs['assigned_ips'] = [
            'ipv4' => $this->generateFakeIp('v4'),
            'ipv6' => $this->generateFakeIp('v6'),
        ];
        $order->requirements = $reqs;
        $order->save();
    }

    /**
     * Release IPs when an order is cancelled or expired.
     */
    public function releaseIPs(CustomerOrder $order): void
    {
        $network = $order->customer->user->network;
        if (!$network) return;

        $needsDedip = $order->requirements['dedicated_ip'] ?? false;
        $ipCount = $needsDedip ? 2 : 1;

        $network->ipv4_used = max(0, $network->ipv4_used - $ipCount);
        $network->ipv6_used = max(0, $network->ipv6_used - rand(1, 10));
        $network->save();

        // Release IP allocation records
        IpAllocation::where('order_id', $order->id)
            ->update(['status' => 'available', 'order_id' => null]);
    }

    private function generateFakeIp(string $type): string
    {
        if ($type === 'v4') {
            return rand(10, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254);
        }
        return sprintf(
            '%04x:%04x:%04x:%04x:%04x:%04x:%04x:%04x',
            rand(0, 0xffff), rand(0, 0xffff), rand(0, 0xffff), rand(0, 0xffff),
            rand(0, 0xffff), rand(0, 0xffff), rand(0, 0xffff), rand(0, 0xffff)
        );
    }

    // ─── TICK (GAME LOOP) ───────────────────────────────

    /**
     * Main network tick — called every game loop cycle.
     */
    public function tick(User $user): void
    {
        $network = $user->network ?? $this->initializeNetwork($user);
        $state = $this->getNetworkGameState($network);

        // Tick Private Networks simulation
        app(PrivateNetworkService::class)->tick($user);

        // 1. Update traffic metrics
        $traffic = $this->getAggregateTraffic($user);
        $network->traffic_in_gbps = $traffic['in'];
        $network->traffic_out_gbps = $traffic['out'];

        // 2. Bandwidth overload effects
        $saturation = $state['bandwidth']['saturation'];

        if ($saturation > 100) {
            $overloadRatio = $saturation / 100;
            $this->applyCongestionEffects($user, $network, $overloadRatio);
        }

        // 3. IP exhaustion warnings
        $ipUsage = $network->getIpv4UsagePercent();
        if ($ipUsage > 90) {
            $this->handleIpExhaustion($user, $network, $ipUsage);
        }
        
        // 3b. Compliance Risk (Black Market IPs)
        $this->evaluateComplianceRisk($user, $network);

        // 4. DDoS risk evaluation
        $this->evaluateDdosRisk($user, $network, $saturation);

        // 4b. ISP Reliability / Micro-Outages
        $this->evaluateIspReliability($user, $network);

        // 5. Peering score progression
        if ($state['metrics']['packetLoss'] == 0 && $network->peering_score < 100) {
            $network->peering_score = min(100, $network->peering_score + 0.05);
        }

        // 5b. BGP Convergence Logic
        PeeringAgreement::where('user_id', $user->id)
            ->where('status', 'converging')
            ->where('signed_at', '<', now()->addSeconds(-10)) // Use 10s for snappier feel
            ->update(['status' => 'active']);

        // 6. Jitter calculation (based on saturation + noise)
        $jitter = max(0, ($saturation / 100 * 5) + (rand(-100, 100) / 100));
        
        // Specialization V2: Network Guru (Jitter Reduction)
        $empService = app(EmployeeService::class);
        $jitterBonus = $empService->getAggregatedBonus($user, 'jitter_reduction');
        $jitter *= (1.0 - min(0.8, $jitterBonus));
        
        $network->jitter_ms = $jitter;

        // 7. Update regional latency
        $this->updateRegionalLatency($network, $saturation);
        
        // 7b. Flat Latency Reduction (Specialization V2: BGP Optimizer)
        $flatRed = $empService->getAggregatedBonus($user, 'latency_reduction_flat');
        if ($flatRed > 0) {
            $regLat = $network->regional_latency ?? [];
            foreach ($regLat as $region => $lat) {
                $regLat[$region] = max(1.0, $lat - $flatRed);
            }
            $network->regional_latency = $regLat;
        }

        // 8. Reputation drift
        $this->processReputationDrift($user, $network, $state);

        // --- FEATURE 58: Orbital Redundancy Billing ---
        if ($this->isOrbitalFailoverActive($user)) {
             $totalGb = ($traffic['total'] * 60) / 8; // Assuming 60s per tick
             $costPerGb = 5.00; // $5 per GB (Orbit is expensive!)
             $totalCost = $totalGb * $costPerGb;
             
             if ($totalCost > 1) {
                  $user->economy->debit($totalCost, "Satellite Uplink usage: " . round($totalGb, 2) . " GB", 'network');
                  GameLog::log($user, "ORBITAL: Failover active. Bypassing network outages via satellite (" . round($totalGb, 1) . " GB consumed).", 'warning', 'network');
             }
        }

        $network->save();
    }

    // ─── TICK SUBSYSTEMS ────────────────────────────────

    /**
     * Apply congestion effects when bandwidth is overloaded.
     */
    private function applyCongestionEffects(User $user, PlayerNetwork $network, float $overloadRatio): void
    {
        $severity = match (true) {
            $overloadRatio > 2.0 => 'critical',
            $overloadRatio > 1.5 => 'warning',
            default => 'info',
        };

        // Reputation penalty proportional to overload
        $repPenalty = ($overloadRatio - 1.0) * 3.0;
        $user->economy->adjustReputation(-$repPenalty);

        // Create incident if severe enough
        if ($severity !== 'info') {
            $existing = GameEvent::where('user_id', $user->id)
                ->where('title', 'LIKE', '%BANDWIDTH_CONGESTION%')
                ->where('status', 'active')
                ->exists();

            if (!$existing) {
                GameEvent::create([
                    'user_id' => $user->id,
                    'title' => '⚠️ BANDWIDTH_CONGESTION',
                    'description' => "Network overloaded at " . round($overloadRatio * 100) . "% capacity. Packet loss increasing, customer SLAs at risk.",
                    'type' => EventType::NETWORK_FAILURE,
                    'status' => 'active',
                    'severity' => $severity,
                ]);
            }
        }

        // Increase churn risk on active orders
        if ($overloadRatio > 1.3) {
            CustomerOrder::where('user_id', $user->id)
                ->where('status', 'active')
                ->increment('churn_risk', min(5, ($overloadRatio - 1.0) * 2));
        }
    }
    
    /**
     * Evaluate risk of being blacklisted due to black market IP usage.
     */
    private function evaluateComplianceRisk(User $user, PlayerNetwork $network): void
    {
        $bmCount = $network->metadata['black_market_ip_count'] ?? 0;
        if ($bmCount <= 0) return;
        
        // Chance of ISP Banning or Blacklisting
        // 0.5% base chance per 10 Black Market IPs
        $risk = ($bmCount / 20); 
        
        // Don't trigger if already active
        $active = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('type', EventType::ISP_BANNING)
            ->where('status', \App\Enums\EventStatus::ACTIVE)
            ->exists();
            
        if ($active) return;
        
        if (rand(1, 1000) < ($risk * 10)) {
             $this->triggerBlacklistEvent($user, $network);
        }
    }

    /**
     * Handle IP address pool exhaustion.
     */
    private function handleIpExhaustion(User $user, PlayerNetwork $network, float $ipUsage): void
    {
        if ($ipUsage >= 100) {
            $existing = GameEvent::where('user_id', $user->id)
                ->where('title', 'LIKE', '%IP_POOL_EXHAUSTED%')
                ->where('status', 'active')
                ->exists();

            if (!$existing) {
                GameEvent::create([
                    'user_id' => $user->id,
                    'title' => '🔴 IP_POOL_EXHAUSTED',
                    'description' => "All IPv4 addresses allocated. New orders cannot be provisioned until additional IP blocks are purchased.",
                    'type' => EventType::NETWORK_FAILURE,
                    'status' => 'active',
                    'severity' => 'critical',
                ]);
            }
        }
    }

    /**
     * Evaluate and potentially trigger DDoS events.
     */
    private function evaluateDdosRisk(User $user, PlayerNetwork $network, float $saturation): void
    {
        // Don't trigger if already active
        $active = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::DDOS_ATTACK)
            ->where('status', 'active')
            ->where('title', 'LIKE', '%DDoS%')
            ->exists();

        if ($active) return;

        // DDoS chance scales with visibility (reputation + traffic)
        $reputation = $user->economy->reputation ?? 0;
        $ddosChance = ($reputation / 1000) + ($saturation / 500);

        // Higher traffic = bigger target
        $trafficFactor = min(1, ($network->traffic_in_gbps + $network->traffic_out_gbps) / 10);
        $ddosChance += $trafficFactor * 2;

        // Cooldown check
        if ($network->last_ddos_at && $network->last_ddos_at->diffInMinutes(now()) < 10) {
            return; // 10-minute cooldown
        }

        if (rand(1, 1000) < ($ddosChance * 10)) {
            $this->triggerDdosEvent($user, $network);
        }
    }

    /**
     * Trigger a DDoS attack event.
     */
    private function triggerDdosEvent(User $user, PlayerNetwork $network): void
    {
        $mitigation = $network->getDdosMitigationCapacity();
        $surge = rand(500, 2000) / 100; // 5x to 20x traffic

        // Research Bonus: Flow Scrubbing
        $resilience = $this->researchService->getBonus($user, 'ddos_resilience');
        $surge *= (1.0 - $resilience);

        // Mitigation reduces impact
        $firewallBonus = app(PrivateNetworkService::class)->getFirewallMitigationBonus($user);
        
        $playerRoom = \App\Models\GameRoom::where('user_id', $user->id)->first();
        $playerRegion = $playerRoom?->region;
        $worldMods = $playerRegion
            ? \App\Models\WorldEvent::getActiveModifiersForRegion($playerRegion)
            : \App\Models\WorldEvent::getActiveModifiers();
        $securityMod = $worldMods['security_defense'] ?? 1.0;

        $totalMitigation = min(0.99, ($mitigation + $firewallBonus) * $securityMod);
        
        $reducedSurge = $surge * (1.0 - $totalMitigation);

        $severity = $reducedSurge > 5 ? 'critical' : ($reducedSurge > 2 ? 'warning' : 'info');

        \App\Models\GameEvent::create([
            'user_id' => $user->id,
            'title' => '🚨 DDOS_ATTACK_DETECTED',
            'description' => "DDoS attack detected! Traffic spike: " . round($surge * 100) . "%. Mitigated to " . round($reducedSurge * 100) . "%. " . ($severity === 'critical' ? 'Service degradation likely.' : 'Defenses holding.'),
            'type' => EventType::DDOS_ATTACK,
            'status' => 'active',
            'severity' => $severity,
        ]);

        $network->last_ddos_at = now();
        $network->ddos_events_total++;

        $user->economy->adjustReputation(-($reducedSurge * 1.5));
    }

    private function triggerBlacklistEvent(User $user, PlayerNetwork $network): void
    {
        \App\Models\GameEvent::create([
            'user_id' => $user->id,
            'title' => '🚫 IP_BLACKLIST_NOTIFICATION',
            'description' => "Your network prefixes have been flagged for 'Suspicious Behavior' by Spamhaus. Traffic to major clouds is being dropped.",
            'type' => EventType::ISP_BANNING,
            'status' => \App\Enums\EventStatus::ACTIVE,
            'severity' => 'critical',
            'warning_at' => now(),
            'escalates_at' => now()->addSeconds(300),
            'deadline_at' => now()->addSeconds(600),
            'available_actions' => [
                [
                    'id' => 'legal_appeal',
                    'label' => 'Submit Legal Appeal ($2,000)',
                    'cost' => 2000,
                    'duration' => 120,
                    'description' => 'Verify your ASN and dispute the listing. High success but slow.',
                    'success_chance' => 85,
                ],
                [
                    'id' => 'bribe_admin',
                    'label' => 'Bribe Blacklist Admin ($5,000)',
                    'cost' => 5000,
                    'duration' => 30,
                    'description' => 'Fastest way to get delisted. Risk of severe fallout.',
                    'success_chance' => 60,
                ],
                [
                    'id' => 'rotate_ips',
                    'label' => 'Rotate Affected Prefixes ($500)',
                    'cost' => 500,
                    'duration' => 180,
                    'description' => 'Move services to clean blocks. Mitigates impact but doesn\'t solve the listing.',
                    'success_chance' => 100,
                ]
            ]
        ]);
        
        \App\Models\GameLog::log($user, "BLACKLISTED: Upstream providers are blocking your black-market IPs!", 'danger', 'network');
        $user->economy->adjustReputation(-10.0);
    }

    /**
     * Update regional latency based on current conditions.
     */
    private function updateRegionalLatency(PlayerNetwork $network, float $saturation): void
    {
        $latencies = [];
        $presence = $network->regional_presence ?? ['eu'];
        $ispConfig = self::ISP_CATALOG[$network->isp_provider] ?? self::ISP_CATALOG['generic_transit'];
        $ispLatency = $ispConfig['base_latency_ms'];

        foreach (self::REGIONAL_BASE_LATENCY as $region => $baseMs) {
            $regionLatency = $baseMs + $ispLatency;

            // Presence bonus: having a PoP significantly reduces latency
            if (in_array($region, $presence)) {
                $regionLatency *= 0.4; // 60% reduction with local PoP
            }

            // Saturation penalty
            $regionLatency += ($saturation / 100 * 20);

            // DARK FIBER BONUS
            $dfLease = \App\Models\DarkFiberLease::where('user_id', $network->user_id)
                ->where('status', 'active')
                ->where(function($q) use ($region) {
                    $q->where('region_a', $region)->orWhere('region_b', $region);
                })->first();
            
            if ($dfLease) {
                $regionLatency *= (1.0 - (float)$dfLease->latency_reduction);
            }

            // Peering bonus
            if ($network->peering_level >= 2) {
                $regionLatency *= 0.85;
            }

            // Add some natural jitter
            $regionLatency += rand(-3, 3);

            $latencies[$region] = max(1, round($regionLatency, 1));
        }

        $network->regional_latency = $latencies;

        // Update average latency (weighted by presence)
        $avgLatency = 0;
        $weight = 0;
        foreach ($latencies as $region => $ms) {
            $w = in_array($region, $presence) ? 3 : 1;
            $avgLatency += $ms * $w;
            $weight += $w;
        }
        $network->avg_latency_ms = $weight > 0 ? round($avgLatency / $weight, 2) : 50;
    }

    /**
     * Process reputation drift — it slowly recovers if health is good.
     */
    private function processReputationDrift(User $user, PlayerNetwork $network, array $state): void
    {
        $health = $network->getNetworkHealthScore();

        if ($health >= 90 && $network->network_reputation < 100) {
            // Slow recovery
            $network->network_reputation = min(100, $network->network_reputation + 0.1);
        } elseif ($health < 50) {
            // Fast decay
            $network->network_reputation = max(0, $network->network_reputation - 0.5);
        }
    }

    /**
     * Evaluate ISP reliability and trigger micro-outages if roll fails.
     */
    private function evaluateIspReliability(User $user, PlayerNetwork $network): void
    {
        $ispConfig = self::ISP_CATALOG[$network->isp_provider] ?? self::ISP_CATALOG['generic_transit'];
        $reliability = $ispConfig['reliability'] ?? 0.95;

        // Roll for micro-outage (flapping/packet loss spike)
        // 1.0 - reliability = failure chance
        if (rand(1, 1000) > ($reliability * 1000)) {
            $durationMinutes = rand(1, 3);
            
            // Don't duplicate if already active
            $existing = \App\Models\GameEvent::where('user_id', $user->id)
                ->where('title', 'LIKE', '%ISP_MICRO_OUTAGE%')
                ->where('status', 'active')
                ->exists();

            if (!$existing) {
                \App\Models\GameEvent::create([
                    'user_id' => $user->id,
                    'title' => '📡 ISP_MICRO_OUTAGE',
                    'description' => "Minor connectivity flapping detected with {$ispConfig['name']}. Packet loss is spiking.",
                    'type' => EventType::NETWORK_FAILURE,
                    'status' => 'active',
                    'severity' => 'info',
                    'expires_at' => now()->addMinutes($durationMinutes),
                ]);

                \App\Models\GameLog::log($user, "ISP flapping: Small packet loss spike on {$ispConfig['name']}.", 'warning', 'network');
                
                // Immediate impact
                $network->avg_packet_loss += 0.02; // 2% spike
                $user->economy->adjustReputation(-0.5);
            }
        }
    }

    /**
     * Check if orbital failover is currently rescuing the network.
     */
    public function isOrbitalFailoverActive(User $user): bool
    {
        // 1. Requirement: Unlocked via Research
        if (!$this->researchService->isUnlocked($user, 'orbital_redundancy')) {
            return false;
        }

        // 2. Condition: Active Critical Network Failure
        $hasCriticalEvent = GameEvent::where('user_id', $user->id)
            ->whereIn('type', [EventType::FIBER_CUT, EventType::BGP_HIJACKING, EventType::ISP_BANNING])
            ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
            ->exists();

        return $hasCriticalEvent;
    }
}
