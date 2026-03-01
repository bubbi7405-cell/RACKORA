<?php

namespace App\Services\Market;

use App\Models\Competitor;
use App\Models\Customer;
use App\Models\MarketRegion;
use App\Models\User;
use App\Models\GameConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DemandEngine — Generates and distributes market demand per region × sector.
 *
 * Demand(region, sector) = BaseDemand × GrowthFactor × EconomicMultiplier × RegionMultiplier
 *
 * Demand is distributed proportionally based on each participant's competitive score.
 * Unmet demand accumulates and can be captured by expanding capacity.
 */
class DemandEngine
{
    public function __construct(
        protected EconomicCycleEngine $cycleEngine,
    ) {}

    // ─── DEMAND GENERATION ─────────────────────────────

    /**
     * Generate demand for all regions and sectors.
     * Returns array of demand pools keyed by region.sector.
     */
    public function generateDemandPools(int $currentTick): array
    {
        $economicModifiers = $this->cycleEngine->getModifiers();
        $globalDemandIndex = $economicModifiers['global_demand_index'];
        $regions = MarketRegion::all();
        $sectors = EconomicCycleEngine::SECTORS;

        $pools = [];

        foreach ($regions as $region) {
            foreach ($sectors as $sectorKey => $sectorConfig) {
                $demand = $this->calculateDemand($region, $sectorKey, $sectorConfig, $globalDemandIndex, $economicModifiers);

                $pools["{$region->key}.{$sectorKey}"] = [
                    'region' => $region->key,
                    'sector' => $sectorKey,
                    'demand' => $demand,
                    'region_model' => $region,
                    'sector_config' => $sectorConfig,
                ];
            }
        }

        return $pools;
    }

    /**
     * Calculate demand for a specific region × sector combination.
     */
    public function calculateDemand(
        MarketRegion $region,
        string $sectorKey,
        array $sectorConfig,
        float $globalDemandIndex,
        array $economicModifiers,
    ): int {
        $baseDemand = $sectorConfig['base_demand'];
        $sectorGrowth = $sectorConfig['growth_rate'];
        $regionDemand = $region->demand_base;
        $regionGrowth = (float) $region->demand_growth_factor;
        $regionMultiplier = $region->getEffectiveDemandMultiplier();

        // Base formula
        $demand = ($baseDemand + $regionDemand * 0.3)
            * $sectorGrowth
            * $regionGrowth
            * $regionMultiplier
            * ($globalDemandIndex / 100)
            * $economicModifiers['demand'];

        // Apply sector-specific innovation drift
        $innovationAccumulated = (float) GameConfig::get("market.innovation.{$sectorKey}", 0);
        $demand *= (1 + $innovationAccumulated * 0.1);

        // Small random variance (±10%)
        $demand *= (1 + (mt_rand(-100, 100) / 1000));

        return max(1, (int) round($demand));
    }

    // ─── DEMAND DISTRIBUTION ───────────────────────────

    /**
     * Distribute demand among competitors and players based on competitive scores.
     *
     * @param array $demandPools  Output of generateDemandPools()
     * @param array $playerScores Player scores keyed by region.sector = { score, capacity }
     * @return array Distribution results
     */
    public function distributeDemand(array $demandPools, array $playerScores): array
    {
        $competitors = Competitor::where('status', 'active')->get();
        $results = [];

        foreach ($demandPools as $poolKey => $pool) {
            $region = $pool['region'];
            $sector = $pool['sector'];
            $totalDemand = $pool['demand'];
            $sectorConfig = $pool['sector_config'];

            // Calculate each participant's score
            $participants = [];

            // 1. Add competitors
            foreach ($competitors as $competitor) {
                $score = $this->calculateCompetitorScore($competitor, $region, $sector, $sectorConfig);
                // Use sqrt-scaled capacity to prevent large NPCs from monopolizing demand
                $rawCapacity = $competitor->capacity_score * (($competitor->regional_shares[$region] ?? 10) / 100);
                $capacity = max(10, (int) (sqrt($rawCapacity) * 15));

                $participants["npc_{$competitor->id}"] = [
                    'type' => 'competitor',
                    'id' => $competitor->id,
                    'name' => $competitor->name,
                    'score' => $score,
                    'capacity' => $capacity,
                ];
            }

            // 2. Add player (if they have a score for this region.sector)
            $playerKey = "{$region}.{$sector}";
            if (isset($playerScores[$playerKey])) {
                $participants['player'] = [
                    'type' => 'player',
                    'score' => $playerScores[$playerKey]['score'],
                    'capacity' => $playerScores[$playerKey]['capacity'],
                ];
            }

            // 3. Distribute demand proportionally to scores, capped by capacity
            $distribution = $this->proportionalDistribute($participants, $totalDemand);

            $results[$poolKey] = [
                'region' => $region,
                'sector' => $sector,
                'total_demand' => $totalDemand,
                'distribution' => $distribution['allocated'],
                'unmet_demand' => $distribution['unmet'],
                'player_served' => $distribution['allocated']['player'] ?? 0,
            ];
        }

        return $results;
    }

    /**
     * Calculate competitive score for an NPC competitor.
     *
     * Score = (Uptime × 0.25) + (Latency × 0.20) + (Price × 0.20) + (Reputation × 0.15) + (Capacity × 0.20)
     */
    public function calculateCompetitorScore(
        Competitor $competitor,
        string $region,
        string $sector,
        array $sectorConfig,
    ): float {
        // --- FEATURE: PRICE WAR SENSITIVITY ---
        // Check if any active price war is happening in the market
        $isGlobalPriceWar = \Illuminate\Support\Facades\Cache::has("npc_event_{$competitor->id}_price_war");
        
        $priceWeight = 0.20;
        $repWeight = 0.15;
        $capacityWeight = 0.20;

        if ($isGlobalPriceWar) {
            $priceWeight = 0.45; // Price becomes dominant
            $repWeight = 0.05;   // Brand loyalty drops
            $capacityWeight = 0.10;
        }

        // Normalize uptime (99.9% = 100 score, 95% = 50 score)
        $uptimeScore = min(100, max(0, ($competitor->uptime_score - 95) / 5 * 100));

        // Normalize latency (lower is better: 10ms = 100, 100ms = 0)
        $latencyScore = max(0, min(100, (100 - $competitor->latency_score) * 1.1));

        // Price competitiveness (lower price_modifier = more competitive)
        $priceScore = max(0, min(100, (2.0 - $competitor->price_modifier) * 50));

        // Reputation (0-100 already)
        $reputationScore = min(100, (float) $competitor->reputation);

        // Capacity availability (log scale to prevent size-dominance)
        $capacityScore = min(100, log10(max(1, $competitor->capacity_score)) * 30);

        // Regional advantage — bonus if HQ is in this region
        $regionalBonus = ($competitor->headquarters_region === $region) ? 15 : 0;

        // Sector specialization — bonus if focus matches
        $sectorBonus = ($competitor->focus_sector === $sector) ? 20 : 0;

        // Apply sector-specific weights
        $latencyWeight = $sectorConfig['latency_weight'] ?? 0.20;
        $uptimeWeight = $sectorConfig['uptime_weight'] ?? 0.25;

        $score = ($uptimeScore * $uptimeWeight)
            + ($latencyScore * $latencyWeight)
            + ($priceScore * $priceWeight)
            + ($reputationScore * $repWeight)
            + ($capacityScore * $capacityWeight)
            + $regionalBonus
            + $sectorBonus;

        // Apply marketing budget effect
        $marketingBoost = min(20, $competitor->marketing_budget / 10000);
        $score += $marketingBoost;

        return max(1, round($score, 2));
    }

    // ─── PLAYER SCORE CALCULATION ──────────────────────

    /**
     * Calculate the player's competitive score for a specific region+sector.
     * Uses real infrastructure data from the player's game state.
     */
    public function calculatePlayerScore(User $user, string $region, string $sector, array $sectorConfig): array
    {
        $economy = $user->economy;
        $network = $user->network;
        if (!$economy || !$network) {
            return ['score' => 0, 'capacity' => 0];
        }

        // Uptime from player's actual servers in this region
        $rooms = $user->rooms()->where('region', $region)->get();
        $totalServers = 0;
        $onlineServers = 0;

        foreach ($rooms as $room) {
            $racks = $room->racks;
            foreach ($racks as $rack) {
                $servers = $rack->servers;
                $totalServers += $servers->count();
                $onlineServers += $servers->where('status', 'online')->count();
            }
        }

        $uptimeScore = $totalServers > 0
            ? min(100, ($onlineServers / $totalServers) * 100)
            : 0;

        // Latency from network state
        $latencyMs = $network->regional_latency[$region] ?? 100;
        $latencyScore = max(0, min(100, (100 - $latencyMs) * 1.1));

        // Price competitiveness — based on player's pricing strategy
        $priceStrategy = $economy->getPolicy('pricing_strategy', 'balanced');
        $priceScore = match ($priceStrategy) {
            'budget' => 90,
            'balanced' => 60,
            'premium' => 30,
            default => 60,
        };

        // Reputation
        $reputationScore = min(100, (float) $economy->reputation);

        // Capacity — available server slots that can serve this sector
        $capacity = max(0, $totalServers * 5); // Rough: each server can serve ~5 demand units

        // --- GLOBAL PRICE WAR CHECK ---
        $priceWeight = 0.20;
        $repWeight = 0.15;
        $capWeight = 0.20; // Default weight for capacity

        $anyPriceWar = DB::table('competitors')->where('status', 'active')->get()->contains(function($c) {
            return \Illuminate\Support\Facades\Cache::has("npc_event_{$c->id}_price_war");
        });

        if ($anyPriceWar) {
            $priceWeight = 0.45;
            $repWeight = 0.05;
            $capWeight = 0.10;
        }

        // Marketing effectiveness
        $marketingBoost = min(20, (float) $economy->marketing_budget / 10000 * $economy->marketing_effectiveness);

        // Innovation index
        $innovationBoost = min(15, (float) $economy->innovation_index / 10);

        // Apply sector weights
        $latencyWeight = $sectorConfig['latency_weight'] ?? 0.20;
        $uptimeWeight = $sectorConfig['uptime_weight'] ?? 0.25;

        $score = ($uptimeScore * $uptimeWeight)
            + ($latencyScore * $latencyWeight)
            + ($priceScore * $priceWeight)
            + ($reputationScore * $repWeight)
            + (min(100, $capacity / 5) * $capWeight)
            + $marketingBoost
            + $innovationBoost;

        // Regional presence bonus — large bonus if player has rooms in this region
        if ($rooms->count() > 0) {
            $score += 15;
        }

        return [
            'score' => max(1, round($score, 2)),
            'capacity' => $capacity,
        ];
    }

    // ─── DISTRIBUTION ALGORITHM ────────────────────────

    /**
     * Distribute demand proportionally to scores, capped by capacity.
     * Multi-pass: if a participant is capacity-capped, redistribute excess.
     */
    private function proportionalDistribute(array $participants, int $totalDemand): array
    {
        $allocated = [];
        $remaining = $totalDemand;
        $uncapped = $participants;
        $maxPasses = 5;

        for ($pass = 0; $pass < $maxPasses && $remaining > 0 && !empty($uncapped); $pass++) {
            $totalScore = array_sum(array_column($uncapped, 'score'));
            if ($totalScore <= 0) break;

            $newUncapped = [];

            foreach ($uncapped as $key => $p) {
                $share = ($p['score'] / $totalScore) * $remaining;
                $capped = min($share, $p['capacity'] - ($allocated[$key] ?? 0));
                $capped = max(0, (int) round($capped));

                $allocated[$key] = ($allocated[$key] ?? 0) + $capped;
                $remaining -= $capped;

                // If not fully capacity-capped, keep in pool for next pass
                if ($allocated[$key] < $p['capacity']) {
                    $newUncapped[$key] = $p;
                }
            }

            $uncapped = $newUncapped;
        }

        return [
            'allocated' => $allocated,
            'unmet' => max(0, $remaining),
        ];
    }

    // ─── MARKET DRIFT ──────────────────────────────────

    /**
     * Apply natural market drift (called less frequently than main tick).
     * • AI demand slowly increases with innovation
     * • Storage prices slowly decrease
     * • Regional attractiveness shifts based on crises
     */
    public function applyMarketDrift(int $currentTick): void
    {
        $lastDrift = (int) GameConfig::get('market.last_drift_tick', 0);
        if ($currentTick - $lastDrift < 20) return; // Only drift every 20 ticks

        GameConfig::set('market.last_drift_tick', $currentTick, 'market');

        foreach (EconomicCycleEngine::SECTORS as $sectorKey => $config) {
            $drift = $config['innovation_drift'];
            $current = (float) GameConfig::get("market.innovation.{$sectorKey}", 0);
            $new = max(-1, min(5, $current + $drift + (mt_rand(-10, 10) / 1000)));
            GameConfig::set("market.innovation.{$sectorKey}", round($new, 4), 'market');
        }

        Log::debug("[MarketDrift] Applied innovation drift at tick {$currentTick}");
    }
}
