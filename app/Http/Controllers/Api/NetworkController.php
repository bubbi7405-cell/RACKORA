<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Game\NetworkService;
use App\Models\DarkFiberLease;
use Illuminate\Support\Facades\DB;

class NetworkController extends Controller
{
    public function __construct(
        protected NetworkService $networkService
    ) {}

    public function purchaseASN(Request $request): JsonResponse
    {
        $user = $request->user();
        $network = $user->network;
        $economy = $user->economy;

        if ($network->asn) {
            return response()->json(['success' => false, 'error' => 'You already have an ASN.'], 400);
        }

        $cost = 50000; // Cost for ASN establish
        if (!$economy->debit($cost, "Registered Autonomous System (ASN)", "infrastructure")) {
            return response()->json(['success' => false, 'error' => 'Insufficient funds ($50,000 required).'], 400);
        }

        $network->asn = rand(10000, 99999);
        $network->peering_level = 1; // Unlock Community Peering
        $network->save();

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($network),
            'message' => "ASN Established: AS{$network->asn}"
        ]);
    }

    public function upgradePeering(Request $request): JsonResponse
    {
        $user = $request->user();
        $network = $user->network;
        $economy = $user->economy;

        if (!$network->asn) {
            return response()->json(['success' => false, 'error' => 'ASN required for peering upgrades.'], 400);
        }

        if ($network->peering_level >= 2) {
            return response()->json(['success' => false, 'error' => 'Max peering level reached.'], 400);
        }

        $cost = $network->peering_level == 1 ? 250000 : 500000;
        if (!$economy->debit($cost, "Upgraded Peering Contracts", "infrastructure")) {
            return response()->json(['success' => false, 'error' => "Insufficient funds ($" . number_format($cost) . " required)."], 400);
        }

        $network->peering_level++;
        $network->peering_score += 10.0;
        $network->save();

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($network),
            'message' => "Peering upgrade complete: {$network->getPeeringLabel()}"
        ]);
    }

    /**
     * IPv4 Market — Get available blocks and pricing
     */
    public function getIpv4Market(Request $request): JsonResponse
    {
        $user = $request->user();
        $network = $user->network;

        $blocks = $this->getAvailableBlocks($network);

        return response()->json([
            'success' => true,
            'data' => [
                'blocks' => $blocks,
                'currentPool' => [
                    'total' => $network->ipv4_total,
                    'used' => $network->ipv4_used,
                    'available' => $network->ipv4_total - $network->ipv4_used,
                ],
                'marketTrend' => $this->getMarketTrend($network),
            ],
        ]);
    }

    /**
     * Purchase an IPv4 block from the market
     */
    public function purchaseIpv4Block(Request $request): JsonResponse
    {
        $request->validate([
            'block_size' => 'required|integer|in:8,16,32,64,128,256',
        ]);

        $user = $request->user();
        $network = $user->network;
        $economy = $user->economy;

        $blockSize = $request->block_size;
        $blocks = $this->getAvailableBlocks($network);
        $block = collect($blocks)->firstWhere('size', $blockSize);

        if (!$block) {
            return response()->json(['success' => false, 'error' => 'Invalid block size.'], 400);
        }

        if (!$block['available']) {
            return response()->json(['success' => false, 'error' => 'This block size requires a higher reputation level.'], 400);
        }

        $cost = $block['price'];
        if (!$economy->debit($cost, "IPv4 Block Purchase: /{$this->getCidr($blockSize)} ({$blockSize} IPs)", "infrastructure")) {
            return response()->json(['success' => false, 'error' => "Insufficient funds (\${$this->formatPrice($cost)} required)."], 400);
        }

        $network->ipv4_total += $blockSize;
        $network->save();

        // Log event
        \App\Models\GameLog::log($user, "Acquired IPv4 Block: +{$blockSize} addresses (/{$this->getCidr($blockSize)})", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($network),
            'message' => "Acquired {$blockSize} IPv4 addresses (/{$this->getCidr($blockSize)})",
        ]);
    }

    /**
     * Sell IPv4 addresses back to the market
     */
    public function sellIpv4Block(Request $request): JsonResponse
    {
        $request->validate([
            'block_size' => 'required|integer|in:8,16,32,64',
        ]);

        $user = $request->user();
        $network = $user->network;
        $economy = $user->economy;

        $blockSize = $request->block_size;
        $available = $network->ipv4_total - $network->ipv4_used;

        if ($available < $blockSize) {
            return response()->json([
                'success' => false, 
                'error' => "Cannot sell: Only {$available} IPs are unused. Release customer assignments first.",
            ], 400);
        }

        // Prevent selling below minimum pool
        if (($network->ipv4_total - $blockSize) < 8) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot reduce pool below minimum of 8 addresses.',
            ], 400);
        }

        // Sell price is 40% of market value
        $market = \App\Models\NetworkMarket::getMarket();
        $sellPrice = round($market->getPriceForSize($blockSize) * 0.4);

        $economy->credit($sellPrice, "IPv4 Block Sale: /{$this->getCidr($blockSize)} ({$blockSize} IPs)", "infrastructure");

        $network->ipv4_total -= $blockSize;
        $network->save();

        \App\Models\GameLog::log($user, "Sold IPv4 Block: -{$blockSize} addresses for \${$this->formatPrice($sellPrice)}", 'info', 'network');

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($network),
            'message' => "Sold {$blockSize} IPv4 addresses for \${$this->formatPrice($sellPrice)}",
        ]);
    }

    /**
     * Upgrade DDoS protection tier
     */
    public function upgradeDdos(Request $request): JsonResponse
    {
        $user = $request->user();
        $network = $user->network;
        $economy = $user->economy;

        $currentLevel = $network->ddos_protection_level;

        $tiers = [
            0 => ['next' => 1, 'label' => 'Cloud Proxy (Basic)',    'cost' => 15000,  'description' => 'Basic cloud-based traffic filtering'],
            1 => ['next' => 2, 'label' => 'Hardware Mitigation',    'cost' => 75000,  'description' => 'On-premise hardware scrubbing appliance'],
            2 => ['next' => 3, 'label' => 'AI-Driven Shield',       'cost' => 250000, 'description' => 'ML-powered real-time threat detection'],
        ];

        if (!isset($tiers[$currentLevel])) {
            return response()->json(['success' => false, 'error' => 'Maximum DDoS protection level reached.'], 400);
        }

        $tier = $tiers[$currentLevel];
        $cost = $tier['cost'];

        if (!$economy->debit($cost, "DDoS Protection: {$tier['label']}", "infrastructure")) {
            return response()->json(['success' => false, 'error' => "Insufficient funds (\${$this->formatPrice($cost)} required)."], 400);
        }

        $network->ddos_protection_level = $tier['next'];
        $network->save();

        \App\Models\GameLog::log($user, "DDoS Protection Upgraded: {$tier['label']}", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($network),
            'message' => "DDoS Protection upgraded to: {$tier['label']}",
            'tier' => $tier,
        ]);
    }

    /**
     * Get DDoS protection tiers info
     */
    public function getDdosTiers(Request $request): JsonResponse
    {
        $user = $request->user();
        $network = $user->network;

        $tiers = [
            ['level' => 0, 'label' => 'No Protection',           'cost' => 0,      'mitigation' => '0%',   'active' => $network->ddos_protection_level == 0, 'current' => $network->ddos_protection_level >= 0],
            ['level' => 1, 'label' => 'Cloud Proxy (Basic)',      'cost' => 15000,  'mitigation' => '50%',  'active' => $network->ddos_protection_level == 1, 'current' => $network->ddos_protection_level >= 1],
            ['level' => 2, 'label' => 'Hardware Mitigation',      'cost' => 75000,  'mitigation' => '75%',  'active' => $network->ddos_protection_level == 2, 'current' => $network->ddos_protection_level >= 2],
            ['level' => 3, 'label' => 'AI-Driven Shield',         'cost' => 250000, 'mitigation' => '95%',  'active' => $network->ddos_protection_level == 3, 'current' => $network->ddos_protection_level >= 3],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'currentLevel' => $network->ddos_protection_level,
                'tiers' => $tiers,
            ],
        ]);
    }

    // ─── ISP Management ────────────────────────────────

    /**
     * Get available ISP providers for the user's level.
     */
    public function getAvailableIsps(Request $request): JsonResponse
    {
        $user = $request->user();
        $isps = $this->networkService->getAvailableIsps($user);

        return response()->json([
            'success' => true,
            'data' => $isps,
        ]);
    }

    /**
     * Switch ISP provider and bandwidth plan.
     */
    public function switchIsp(Request $request): JsonResponse
    {
        $request->validate([
            'isp_id' => 'required|string',
            'bandwidth_option' => 'required|integer|min:0',
        ]);

        $user = $request->user();
        $result = $this->networkService->switchIsp($user, $request->isp_id, $request->bandwidth_option);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['message']], 400);
        }

        $network = $user->network->fresh();

        \App\Models\GameLog::log($user, "ISP Switch: {$result['message']}", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => ['network' => $this->networkService->getNetworkGameState($network)],
            'message' => $result['message'],
        ]);
    }

    /**
     * Allocate a new IPv4 subnet.
     */
    public function allocateSubnet(Request $request): JsonResponse
    {
        $request->validate([
            'size' => 'required|integer|in:16,32,64,128,256',
        ]);

        $user = $request->user();
        $result = $this->networkService->allocateSubnet($user, $request->size);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['message']], 400);
        }

        \App\Models\GameLog::log($user, "Subnet Allocated: {$result['message']}", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($user->network->fresh()),
            'message' => $result['message'],
        ]);
    }
    /**
     * Purchase a "dirty" IPv4 block from the black market
     */
    public function purchaseBlackMarketBlock(Request $request): JsonResponse
    {
        $request->validate([
            'size' => 'required|integer|in:16,64,256',
        ]);

        $user = $request->user();
        $result = $this->networkService->allocateBlackMarketSubnet($user, $request->size);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['message']], 400);
        }

        \App\Models\GameLog::log($user, "Black Market Acquisition: {$result['message']}", 'warning', 'network');

        return response()->json([
            'success' => true,
            'data' => $this->networkService->getNetworkGameState($user->network->fresh()),
            'message' => $result['message'],
        ]);
    }

    /**
     * Get available Dark Fiber lease options
     */
    public function getDarkFiberOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $regions = array_keys(NetworkService::REGIONAL_BASE_LATENCY);
        $options = [];

        // Define region connections (simplified)
        $connections = [
            ['eu', 'us-east', 250000, 5000, 'Trans-Atlantic Express'],
            ['us-east', 'us-west', 150000, 3000, 'Coast-to-Coast Backbone'],
            ['us-west', 'asia', 450000, 8000, 'Trans-Pacific Direct'],
            ['eu', 'asia', 400000, 7500, 'Eurasia Backbone'],
            ['asia', 'oceania', 200000, 4000, 'South-East Link'],
            ['us-east', 'sa', 200000, 4000, 'Pan-American Fiber'],
            ['eu', 'africa', 150000, 3000, 'Euro-African Backbone'],
        ];

        $currentLeases = DarkFiberLease::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($connections as $conn) {
            $isLeased = $currentLeases->contains(function($l) use ($conn) {
                return ($l->region_a === $conn[0] && $l->region_b === $conn[1]) ||
                       ($l->region_a === $conn[1] && $l->region_b === $conn[0]);
            });

            $options[] = [
                'region_a' => $conn[0],
                'region_b' => $conn[1],
                'setup_fee' => $conn[2],
                'monthly_cost' => $conn[3],
                'provider' => $conn[4],
                'isLeased' => $isLeased,
                'latencyReduction' => 0.40,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Lease a Dark Fiber connection
     */
    public function leaseDarkFiber(Request $request): JsonResponse
    {
        $request->validate([
            'region_a' => 'required|string',
            'region_b' => 'required|string',
        ]);

        $user = $request->user();
        $economy = $user->economy;

        // Verify option and cost
        $connections = [
            ['eu', 'us-east', 250000, 5000, 'Trans-Atlantic Express'],
            ['us-east', 'us-west', 150000, 3000, 'Coast-to-Coast Backbone'],
            ['us-west', 'asia', 450000, 8000, 'Trans-Pacific Direct'],
            ['eu', 'asia', 400000, 7500, 'Eurasia Backbone'],
            ['asia', 'oceania', 200000, 4000, 'South-East Link'],
            ['us-east', 'sa', 200000, 4000, 'Pan-American Fiber'],
            ['eu', 'africa', 150000, 3000, 'Euro-African Backbone'],
        ];

        $conn = collect($connections)->first(function($c) use ($request) {
            return ($c[0] === $request->region_a && $c[1] === $request->region_b) ||
                   ($c[1] === $request->region_a && $c[0] === $request->region_b);
        });

        if (!$conn) {
            return response()->json(['success' => false, 'error' => 'Invalid region connection.'], 400);
        }

        $existing = DarkFiberLease::where('user_id', $user->id)
            ->where('region_a', $conn[0])
            ->where('region_b', $conn[1])
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'error' => 'Connection already leased.'], 400);
        }

        if (!$economy->debit($conn[2], "Dark Fiber Lease: {$conn[4]} ({$conn[0]} <-> {$conn[1]})", "infrastructure")) {
            return response()->json(['success' => false, 'error' => "Insufficient funds (\${$this->formatPrice($conn[2])} required)."], 400);
        }

        $lease = DarkFiberLease::create([
            'user_id' => $user->id,
            'region_a' => $conn[0],
            'region_b' => $conn[1],
            'provider_name' => $conn[4],
            'monthly_cost' => $conn[3],
            'setup_fee' => $conn[2],
            'latency_reduction' => 0.40,
            'signed_at' => now(),
            'status' => 'active',
        ]);

        \App\Models\GameLog::log($user, "Invested in Dark Fiber: {$conn[4]}", 'success', 'network');

        return response()->json([
            'success' => true,
            'data' => $lease,
            'message' => "Successfully leased {$conn[4]}. Regional latency reduced."
        ]);
    }

    // ─── Helpers ───────────────────────────────────────

    private function getAvailableBlocks($network): array
    {
        $rep = $network->network_reputation;
        $market = \App\Models\NetworkMarket::getMarket();

        $sizes = [8, 16, 32, 64, 128, 256];
        $blocks = [];

        foreach ($sizes as $size) {
            $blocks[] = [
                'size' => $size,
                'cidr' => '/' . $this->getCidr($size),
                'price' => round($market->getPriceForSize($size)),
                'available' => $this->isSizeAvailable($size, $rep),
                'minRep' => $this->getMinRepForSize($size)
            ];
        }

        return $blocks;
    }

    private function isSizeAvailable(int $size, float $rep): bool
    {
        return $rep >= $this->getMinRepForSize($size);
    }

    private function getMinRepForSize(int $size): int
    {
        return match($size) {
            32 => 30,
            64 => 50,
            128 => 70,
            256 => 90,
            default => 0
        };
    }

    private function getMarketTrend($network): array
    {
        $market = \App\Models\NetworkMarket::getMarket();
        $scarcity = $market->ipv4_scarcity_index;

        return [
            'scarcityIndex' => round($scarcity, 1),
            'priceDirection' => $scarcity > 50 ? 'rising' : ($scarcity < 30 ? 'falling' : 'stable'),
            'label' => $scarcity > 75 ? 'SELLER_MARKET' : ($scarcity > 40 ? 'BALANCED' : 'BUYER_MARKET'),
            'basePrice' => (float)$market->ipv4_base_price,
            'demandFactor' => (float)$market->global_demand_factor
        ];
    }

    private function getCidr(int $size): string
    {
        return match($size) {
            8 => '29', 16 => '28', 32 => '27', 64 => '26', 128 => '25', 256 => '24',
            default => '32',
        };
    }

    private function formatPrice($price): string
    {
        return number_format($price, 0, '.', ',');
    }
}
