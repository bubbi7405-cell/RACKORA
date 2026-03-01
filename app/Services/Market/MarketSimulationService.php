<?php

namespace App\Services\Market;

use App\Models\GameConfig;
use App\Models\MarketRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketSimulationService — Top-level orchestrator for the dynamic market simulation.
 *
 * Called from GameLoopService every tick.
 * Coordinates all market sub-systems in the correct order:
 *
 *   Step 1: Economic Update (GDP, inflation, energy, cycle transitions)
 *   Step 2: Demand Generation (per region × sector)
 *   Step 3: Competitor AI Decisions
 *   Step 4: Score Evaluation (player + competitors)
 *   Step 5: Demand Distribution (proportional to scores)
 *   Step 6: Market Share Update
 *   Step 7: Incident Impact Check
 *   Step 8: Market Drift (slow structural changes)
 *   Step 9: Demand Logging
 */
class MarketSimulationService
{
    /**
     * How often the FULL market simulation runs (in ticks).
     * Light updates (economic drift) run every tick.
     * Full demand+distribution runs every N ticks.
     */
    private const FULL_SIM_INTERVAL = 5;

    /**
     * How often competitor AI decisions are processed.
     */
    private const AI_DECISION_INTERVAL = 10;

    public function __construct(
        protected EconomicCycleEngine $cycleEngine,
        protected DemandEngine $demandEngine,
        protected CompetitorAIService $competitorAI,
        protected GlobalMarketService $globalMarketService,
    ) {}

    // ─── MAIN TICK ─────────────────────────────────────

    /**
     * Global Tick — Process NPC AI and Economic Cycles.
     * Called once per global tick from GameLoopService.
     */
    public function globalTick(int $currentTick): void
    {
        try {
            // Step 1: ALWAYS — Global Economic parameter update
            $this->cycleEngine->tick($currentTick);

            // Step 3: PERIODIC — Competitor AI Decisions
            if ($currentTick % self::AI_DECISION_INTERVAL === 0) {
                $this->competitorAI->tick($currentTick);
            }
        } catch (\Throwable $e) {
            Log::error("[MarketSimulation] Global Tick error: {$e->getMessage()}");
        }
    }

    /**
     * Process one market simulation tick.
     * Called from GameLoopService::processUserLogic().
     */
    public function tick(User $user): void
    {
        $currentTick = (int) ($user->economy->current_tick ?? 0);

        try {
            // Step 2-6: PERIODIC — Full demand simulation
            if ($currentTick % self::FULL_SIM_INTERVAL === 0) {
                Log::info("[MarketSimulation] Running full simulation for User {$user->id} at tick {$currentTick}");
                $this->runFullSimulation($user, $currentTick);
            }

            // Step 7: ALWAYS — Incident threshold checks (User specific impacts)
            $this->globalMarketService->checkIncidentThresholds($user);

            // Step 8: PERIODIC — Market drift (User demand drift)
            $this->demandEngine->applyMarketDrift($currentTick);

        } catch (\Throwable $e) {
            Log::error("[MarketSimulation] Tick error: {$e->getMessage()}", [
                'user_id' => $user->id,
                'tick' => $currentTick,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // ─── FULL SIMULATION ───────────────────────────────

    private function runFullSimulation(User $user, int $currentTick): void
    {
        // Step 2: Generate demand pools
        $demandPools = $this->demandEngine->generateDemandPools($currentTick);

        // Step 4: Calculate player scores (per region × sector)
        $playerScores = $this->calculateAllPlayerScores($user);

        // Step 5: Distribute demand
        $demandResults = $this->demandEngine->distributeDemand($demandPools, $playerScores);

        // Step 6: Update market shares
        $this->globalMarketService->updateMarketShares($demandResults);

        // Step 9: Log demand data
        $this->logDemandResults($demandResults, $currentTick);

        // Update player KPIs
        $this->updatePlayerKpis($user, $demandResults);
    }

    // ─── PLAYER SCORE AGGREGATION ──────────────────────

    private function calculateAllPlayerScores(User $user): array
    {
        $regions = MarketRegion::all();
        $sectors = EconomicCycleEngine::SECTORS;
        $scores = [];

        foreach ($regions as $region) {
            foreach ($sectors as $sectorKey => $sectorConfig) {
                $key = "{$region->key}.{$sectorKey}";
                $scores[$key] = $this->demandEngine->calculatePlayerScore(
                    $user,
                    $region->key,
                    $sectorKey,
                    $sectorConfig,
                );
            }
        }

        return $scores;
    }

    // ─── DEMAND LOGGING ────────────────────────────────

    private function logDemandResults(array $demandResults, int $currentTick): void
    {
        $logs = [];
        $now = now();

        foreach ($demandResults as $poolKey => $result) {
            // Build competitor served map
            $competitorServed = [];
            foreach ($result['distribution'] as $key => $served) {
                if ($key !== 'player' && str_starts_with($key, 'npc_')) {
                    $competitorServed[str_replace('npc_', '', $key)] = $served;
                }
            }

            $logs[] = [
                'tick' => $currentTick,
                'region' => $result['region'],
                'sector' => $result['sector'],
                'demand_generated' => $result['total_demand'],
                'demand_served' => $result['total_demand'] - $result['unmet_demand'],
                'player_served' => $result['player_served'],
                'competitor_served' => json_encode($competitorServed),
                'unmet_demand' => $result['unmet_demand'],
                'avg_price' => 0, // Will be calculated when pricing system is more mature
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($logs)) {
            DB::table('market_demand_logs')->insert($logs);
        }

        // Keep only last 1000 log entries to prevent unbounded growth
        $threshold = DB::table('market_demand_logs')
            ->orderBy('id', 'desc')
            ->skip(1000)
            ->value('id');

        if ($threshold) {
            DB::table('market_demand_logs')->where('id', '<', $threshold)->delete();
        }
    }

    // ─── PLAYER KPI UPDATE ─────────────────────────────

    private function updatePlayerKpis(User $user, array $demandResults): void
    {
        if (!$user->economy) return;

        $economy = $user->economy;

        // Calculate customer growth rate from demand served
        $totalPlayerServed = 0;
        foreach ($demandResults as $result) {
            $totalPlayerServed += $result['player_served'];
        }

        // Innovation index — boosted by research progress
        $researchProgress = 0;
        try {
            $researchProgress = DB::table('user_researches')
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
        } catch (\Throwable $e) {
            // user_researches table may not exist yet
        }

        $economy->innovation_index = min(100, max(0,
            10 + ($researchProgress * 2) + (mt_rand(-5, 5) / 10)
        ));

        // Customer acquisition cost
        $marketingSpend = (float) $economy->marketing_budget;
        $newCustomers = max(1, $totalPlayerServed);
        $economy->customer_acquisition_cost = round($marketingSpend / $newCustomers, 2);

        $economy->save();
    }

    // ─── INITIALIZATION ────────────────────────────────

    /**
     * Initialize market simulation state for a fresh game.
     * Sets up initial GameConfig values.
     */
    public static function initializeMarketState(): void
    {
        $defaults = [
            'market.economic_state' => 'growth',
            'market.economic_cycle_tick' => 0,
            'market.gdp_growth_rate' => 0.025,
            'market.inflation_rate' => 0.02,
            'market.energy_cost_index' => 1.0,
            'market.global_demand_index' => 100,
            'market.demand_served_ratio' => 0,
            'market.total_demand_generated' => 0,
            'market.last_drift_tick' => 0,
            'market.last_transition_tick' => 0,
        ];

        foreach ($defaults as $key => $value) {
            GameConfig::set($key, $value, 'market');
        }

        // Initialize innovation indices
        foreach (EconomicCycleEngine::SECTORS as $key => $config) {
            GameConfig::set("market.innovation.{$key}", 0, 'market');
        }

        Log::info("[MarketSimulation] Market state initialized");
    }

    // ─── API METHODS ───────────────────────────────────

    /**
     * Get market overview for API consumption.
     */
    public function getMarketOverview(User $user): array
    {
        return $this->globalMarketService->getMarketState($user);
    }

    /**
     * Get demand history for charts.
     */
    public function getDemandHistory(int $limit = 50): array
    {
        return DB::table('market_demand_logs')
            ->select('tick', 'region', 'sector', 'demand_generated', 'demand_served', 'player_served', 'unmet_demand')
            ->orderBy('tick', 'desc')
            ->limit($limit)
            ->get()
            ->groupBy('tick')
            ->map(function ($tickData) {
                return [
                    'tick' => $tickData->first()->tick,
                    'regions' => $tickData->groupBy('region')->map(function ($regionData) {
                        return [
                            'totalDemand' => $regionData->sum('demand_generated'),
                            'totalServed' => $regionData->sum('demand_served'),
                            'playerServed' => $regionData->sum('player_served'),
                            'unmet' => $regionData->sum('unmet_demand'),
                            'sectors' => $regionData->pluck(null, 'sector')->toArray(),
                        ];
                    })->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }
}
