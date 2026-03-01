<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Competitor;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;

class MarketService
{
    public function __construct(
        protected RackManagementService $rackService
    ) {}

    /**
     * Get the player's estimated market share for a specific region or global.
     */
    public function getPlayerMarketShare(User $user, ?string $region = null): float
    {
        $query = Customer::where('user_id', $user->id)
            ->where('status', 'active');

        if ($region) {
            $query->whereHas('activeOrders.server.rack.room', function ($q) use ($region) {
                $q->where('region', $region);
            });
        }

        $playerCustomers = $query->count();
            
        // Scale market size per region or global
        $regions = \App\Models\GameConfig::get('regions', []);
        $regionCount = count($regions) ?: 1;

        $baseMarketSize = $region ? 100 : 250;
        $marketGrowth = floor($user->economy->level * ($region ? 20 : 50));
        $totalMarketSize = $baseMarketSize + $marketGrowth;

        if ($totalMarketSize <= 0) return 0;

        return round(($playerCustomers / $totalMarketSize) * 100, 2);
    }

    /**
     * Get all market participants including player for a specific region.
     */
    public function getMarketOverview(User $user, ?string $region = null): array
    {
        $competitors = Competitor::where('status', 'active')->get();
        $playerShare = $this->getPlayerMarketShare($user, $region);
        
        $participants = $competitors->map(function ($c) use ($region) {
            $data = $c->toGameState();
            if ($region && isset($c->regional_shares[$region])) {
                $data['marketShare'] = (float) $c->regional_shares[$region];
            }
            return $data;
        });

        $totalCompetitorShare = $participants->sum('marketShare');
        
        return [
            'participants' => $participants->toArray(),
            'player' => [
                'name' => $user->name . " (You)",
                'marketShare' => $playerShare,
                'reputation' => (float) $user->economy->reputation,
                'color' => '#3fb950',
            ],
            'positioning' => $this->getMarketPositioning($user),
            'unclaimed' => max(0, 100 - $totalCompetitorShare - $playerShare),
            'region' => $region ?: 'global',
            'availableRegions' => \App\Models\GameConfig::get('regions', [])
        ];
    }

    /**
     * Calculate radar coordinates for all market participants.
     */
    public function getMarketPositioning(User $user): array
    {
        $competitors = Competitor::where('status', 'active')->get();
        $data = [];
        
        foreach ($competitors as $c) {
            // X-Axis: Niche (0) to Mass (100) -> Based on Market Share (0-40% range)
            $x = min(95, max(5, ($c->market_share / 35.0) * 100));
            
            // Y-Axis: Premium (0) to Budget (100) -> Based on Price Modifier (2.5 to 0.4 range)
            $y = 100 - (($c->price_modifier - 0.4) / (2.5 - 0.4) * 100);
            
            $data[] = [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'x' => round($x, 2),
                'y' => round(max(5, min(95, $y)), 2),
                'isPlayer' => false
            ];
        }
        
        // Player Position
        $playerShare = $this->getPlayerMarketShare($user);
        $economy = $user->economy;
        
        // Calculate player Y based on pricing policies and reputation
        $priceBase = 1.0;
        $marketFocus = $economy->getPolicy('market_focus', 'balanced');
        if ($marketFocus === 'budget') $priceBase -= 0.4;
        if ($marketFocus === 'premium') $priceBase += 0.6;
        
        // Reputation factor (higher reputation = more "premium" feel)
        $qualityMod = ($economy->reputation - 50) / 100; 
        
        $playerY = 100 - (($priceBase + $qualityMod - 0.4) / (2.5 - 0.4) * 100);
        
        $data[] = [
            'id' => 'player',
            'name' => 'YOU',
            'color' => '#3fb950',
            'x' => round(min(95, max(5, ($playerShare / 35.0) * 100)), 2),
            'y' => round(max(5, min(95, $playerY)), 2),
            'isPlayer' => true
        ];
        
        return $data;
    }

    /**
     * Get available used market listings
     */
    public function getUsedMarketListings(User $user): array
    {
        return \App\Models\MarketListing::active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(function ($item) {
                // Ensure discount percent is available
                $item->discount_percent = $item->getDiscountPercent();
            })
            ->toArray();
    }

    /**
     * Tick for Used Hardware Market
     */
    public function tickUsedMarket(): void
    {
        // 1. Cleanup old listings
        \App\Models\MarketListing::where('expires_at', '<', now())
            ->orWhere('is_sold', true)
            ->delete();

        // 2. Chance to generate new listings (e.g. 30% chance per tick)
        if (rand(1, 100) > 30) return;

        // 3. Generate Listing
        $this->generateRandomListing();
    }

    private function generateRandomListing(): void
    {
        // Types: server (70%), rack (30%)
        // Component type disabled until catalog exists
        $rand = rand(1, 100);
        $type = $rand <= 70 ? 'server' : 'rack';

        $seller = $this->generateSellerName();
        $condition = rand(20, 95); // 20% to 95% condition
        
        $itemData = null;
        $key = null;
        $originalPrice = 0;

        if ($type === 'server') {
            $catalog = $this->rackService->getServerCatalog();
            // Flatten catalog
            $flatCatalog = [];
            foreach ($catalog as $category => $models) {
                foreach ($models as $k => $v) {
                    $flatCatalog[$k] = $v;
                }
            }
            if (empty($flatCatalog)) return;
            
            $key = array_rand($flatCatalog);
            $itemData = $flatCatalog[$key];
            $originalPrice = $itemData['purchaseCost'] ?? 1000;
        } elseif ($type === 'rack') {
            $cases = \App\Enums\RackType::cases();
            $randomCase = $cases[array_rand($cases)];
            $key = $randomCase->value;
            $originalPrice = $randomCase->purchaseCost();
            $itemData = [
                'modelName' => $randomCase->label(),
                'units' => $randomCase->totalUnits(),
                'powerKw' => $randomCase->maxPowerKw()
            ];
        }

        if (!$itemData) return;
        
        // Price Curve: value drops faster than condition
        // 90% condition -> 85% price
        // 50% condition -> 40% price
        // 20% condition -> 10% price
        $priceMultiplier = pow($condition / 100, 1.5);
        $price = $originalPrice * $priceMultiplier;

        // Defect chance (risk)
        // 100% condition -> 0% defect
        // 50% condition -> 10% defect
        // 20% condition -> 25% defect
        $defectChance = (100 - $condition) * 0.5; // Max ~40%

        \App\Models\MarketListing::create([
            'seller_name' => $seller,
            'item_type' => $type,
            'item_key' => $key,
            'condition' => $condition,
            'price' => round($price, 2),
            'original_price' => $originalPrice,
            'expires_at' => now()->addMinutes(rand(15, 120)), // 15m to 2h expiry
            'is_sold' => false,
            'specs' => $itemData, // Snapshot
            'defect_chance' => $defectChance
        ]);
    }

    private function generateSellerName(): string
    {
        $prefixes = ['Failed', 'Liquidated', 'Bankrupt', 'Old', 'Refurbished', 'Vintage'];
        $nouns = ['Startup', 'Crypto Farm', 'ISP', 'Lab', 'Enterprise', 'Cloud Provider'];
        
        // Or NPC names
        $competitors = Competitor::inRandomOrder()->first();
        if ($competitors && rand(0,1)) {
            return $competitors->name . " (Liquidation)";
        }

        return $prefixes[array_rand($prefixes)] . ' ' . $nouns[array_rand($nouns)];
    }

    /**
     * Get active competitive penalties for player order generation
     */
    public function getCompetitiveModifier(User $user): float
    {
        $penalties = 0.0;
        
        $competitors = Competitor::where('status', 'active')->get();
        foreach ($competitors as $npc) {
            if (Cache::has("npc_event_{$npc->id}_price_war")) {
                $penalties += 0.25; // 25% harder per active price war
            }
        }

        return max(0.1, 1.0 - $penalties);
    }
}
