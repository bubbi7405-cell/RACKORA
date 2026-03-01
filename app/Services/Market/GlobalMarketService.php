<?php

namespace App\Services\Market;

use App\Models\Competitor;
use App\Models\GameConfig;
use App\Models\MarketRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GlobalMarketService — Calculates and updates market shares for all participants.
 *
 * Market Share Formula:
 *   MarketShareChange = (PerformanceScore × Reputation × MarketingPower)
 *                     − (Incidents × Risk × CapacityShortage)
 *
 * This service connects demand distribution results to actual market share updates.
 */
class GlobalMarketService
{
    public function __construct(
        protected DemandEngine $demandEngine,
        protected EconomicCycleEngine $cycleEngine,
    ) {}

    // ─── MARKET SHARE CALCULATION ──────────────────────

    /**
     * Recalculate and update market shares for all participants.
     * Called after demand distribution.
     *
     * @param array $demandResults  Output of DemandEngine::distributeDemand()
     */
    public function updateMarketShares(array $demandResults): void
    {
        $totalDemandServed = 0;
        $playerDemandServed = 0;
        $competitorDemandServed = []; // competitor_id => total_served
        $totalDemandGenerated = 0;

        // Aggregate results across all regions × sectors
        foreach ($demandResults as $poolKey => $result) {
            $totalDemandGenerated += $result['total_demand'];

            foreach ($result['distribution'] as $participantKey => $served) {
                $totalDemandServed += $served;

                if ($participantKey === 'player') {
                    $playerDemandServed += $served;
                } else {
                    // npc_{id}
                    $competitorId = str_replace('npc_', '', $participantKey);
                    $competitorDemandServed[$competitorId] = ($competitorDemandServed[$competitorId] ?? 0) + $served;
                }
            }
        }

        if ($totalDemandGenerated <= 0) return;

        // Calculate global market shares
        $playerGlobalShare = ($playerDemandServed / $totalDemandGenerated) * 100;

        // Update competitors
        $competitors = Competitor::where('status', 'active')->get();
        foreach ($competitors as $competitor) {
            $served = $competitorDemandServed[$competitor->id] ?? 0;
            $newShare = ($served / $totalDemandGenerated) * 100;

            // Smooth transition (30% new, 70% old — prevent volatile swings)
            $competitor->market_share = round(
                ($newShare * 0.3) + ((float) $competitor->market_share * 0.7),
                3
            );

            // Update regional shares
            $regionalShares = $this->calculateRegionalShares($demandResults, "npc_{$competitor->id}");
            $competitor->regional_shares = $regionalShares;

            // Update sector shares
            $sectorShares = $this->calculateSectorShares($demandResults, "npc_{$competitor->id}");
            $competitor->sector_shares = $sectorShares;

            $competitor->save();
        }

        // Update player market shares
        $users = User::with('economy')->get();
        foreach ($users as $user) {
            if (!$user->economy) continue;

            $economy = $user->economy;

            // Smooth transition (30% new, 70% old)
            $economy->global_market_share = round(
                ($playerGlobalShare * 0.3) + ((float) $economy->global_market_share * 0.7),
                3
            );

            $economy->regional_shares = $this->calculateRegionalShares($demandResults, 'player');
            $economy->sector_shares = $this->calculateSectorShares($demandResults, 'player');

            // Calculate ARPU
            $activeCustomers = $user->customers()->where('status', 'active')->count();
            if ($activeCustomers > 0) {
                $monthlyRevenue = $user->orders()
                    ->where('customer_orders.status', 'active')
                    ->sum('customer_orders.price_per_month');
                $economy->arpu = round($monthlyRevenue / $activeCustomers, 2);
            }

            $economy->save();
        }

        // Store demand served ratio
        $ratio = round($totalDemandServed / $totalDemandGenerated, 4);
        GameConfig::set('market.demand_served_ratio', $ratio, 'market');
        GameConfig::set('market.total_demand_generated', $totalDemandGenerated, 'market');
    }

    // ─── INCIDENT IMPACT ───────────────────────────────

    /**
     * Process incident impacts on market shares.
     * When infrastructure problems occur, market share is lost.
     */
    public function processIncidentImpact(User $user, string $incidentType, float $severity): void
    {
        if (!$user->economy) return;

        // Calculate share loss based on incident type and severity
        $shareLoss = match ($incidentType) {
            'thermal_critical' => $severity * 0.5,           // 0.5% per severity point
            'power_overload' => $severity * 0.3,
            'network_congestion' => $severity * 0.7,         // Network issues hurt most
            'ddos_attack' => $severity * 0.4,
            'hardware_failure' => $severity * 0.2,
            'sla_violation' => $severity * 0.8,              // SLA violations are devastating
            default => $severity * 0.1,
        };

        // Apply share loss
        $user->economy->global_market_share = max(0,
            (float) $user->economy->global_market_share - $shareLoss
        );

        // Risk exposure increases
        $user->economy->risk_exposure = min(100,
            (float) $user->economy->risk_exposure + $severity * 2
        );

        $user->economy->save();

        // Competitors benefit from player incidents
        $this->redistributeLostShare($shareLoss);

        Log::info("[Market] Player incident '{$incidentType}' caused {$shareLoss}% share loss");
    }

    /**
     * Check infrastructure thresholds and generate incident impacts.
     * Called during market tick.
     */
    public function checkIncidentThresholds(User $user): void
    {
        if (!$user->network) return;
        $network = $user->network;

        // Thermal check
        $avgTemp = DB::table('server_racks')
            ->join('game_rooms', 'server_racks.room_id', '=', 'game_rooms.id')
            ->where('game_rooms.user_id', $user->id)
            ->where('server_racks.status', 'online')
            ->avg('server_racks.temperature') ?? 25;

        if ($avgTemp > 80) {
            $this->processIncidentImpact($user, 'thermal_critical', ($avgTemp - 80) / 10);
        }

        // Power check
        $rooms = $user->rooms;
        foreach ($rooms as $room) {
            $powerUsage = $room->current_power_kw ?? 0;
            $powerCapacity = $room->power_capacity_kw ?? 1;
            $powerPercent = ($powerUsage / max(0.1, $powerCapacity)) * 100;

            if ($powerPercent > 90) {
                $this->processIncidentImpact($user, 'power_overload', ($powerPercent - 90) / 5);
            }
        }

        // Network check
        $bandwidth = $network->bandwidth_saturation ?? 0;
        if ($bandwidth > 95) {
            $this->processIncidentImpact($user, 'network_congestion', ($bandwidth - 95) / 2);
        }

        // Natural risk exposure decay
        $user->economy->risk_exposure = max(0,
            (float) ($user->economy->risk_exposure ?? 0) - 0.1
        );
        $user->economy->save();
    }

    // ─── HELPER METHODS ────────────────────────────────

    private function calculateRegionalShares(array $demandResults, string $participantKey): array
    {
        $regionalServed = [];
        $regionalTotal = [];

        foreach ($demandResults as $poolKey => $result) {
            $region = $result['region'];
            $served = $result['distribution'][$participantKey] ?? 0;

            $regionalServed[$region] = ($regionalServed[$region] ?? 0) + $served;
            $regionalTotal[$region] = ($regionalTotal[$region] ?? 0) + $result['total_demand'];
        }

        $shares = [];
        foreach ($regionalTotal as $region => $total) {
            if ($total > 0) {
                $shares[$region] = round(($regionalServed[$region] / $total) * 100, 2);
            } else {
                $shares[$region] = 0;
            }
        }

        return $shares;
    }

    private function calculateSectorShares(array $demandResults, string $participantKey): array
    {
        $sectorServed = [];
        $sectorTotal = [];

        foreach ($demandResults as $poolKey => $result) {
            $sector = $result['sector'];
            $served = $result['distribution'][$participantKey] ?? 0;

            $sectorServed[$sector] = ($sectorServed[$sector] ?? 0) + $served;
            $sectorTotal[$sector] = ($sectorTotal[$sector] ?? 0) + $result['total_demand'];
        }

        $shares = [];
        foreach ($sectorTotal as $sector => $total) {
            if ($total > 0) {
                $shares[$sector] = round(($sectorServed[$sector] / $total) * 100, 2);
            } else {
                $shares[$sector] = 0;
            }
        }

        return $shares;
    }

    private function redistributeLostShare(float $lostShare): void
    {
        $competitors = Competitor::where('status', 'active')->get();
        if ($competitors->isEmpty()) return;

        // Distribute lost share proportionally to existing market share
        $totalCompetitorShare = $competitors->sum('market_share');
        if ($totalCompetitorShare <= 0) {
            $totalCompetitorShare = $competitors->count();
        }

        foreach ($competitors as $competitor) {
            $proportion = (float) $competitor->market_share / max(1, $totalCompetitorShare);
            $gain = $lostShare * $proportion * 0.6; // Only 60% is redistributed (rest goes to "unclaimed")

            $competitor->market_share = min(75, (float) $competitor->market_share + $gain);
            $competitor->save();
        }
    }

    // ─── MARKET STATE EXPORT ───────────────────────────

    /**
     * Get the full market state for frontend consumption.
     */
    public function getMarketState(?User $user = null): array
    {
        $economicMods = $this->cycleEngine->getModifiers();
        $regions = MarketRegion::all()->map(fn($r) => $r->toGameState());
        $competitors = Competitor::where('status', 'active')
            ->get()
            ->map(fn($c) => $c->toGameState());

        $state = [
            'economy' => $economicMods,
            'regions' => $regions->toArray(),
            'competitors' => $competitors->toArray(),
            'sectors' => collect(EconomicCycleEngine::SECTORS)->map(function ($config, $key) {
                $innovation = (float) GameConfig::get("market.innovation.{$key}", 0);
                return [
                    'key' => $key,
                    'label' => $config['label'],
                    'baseDemand' => $config['base_demand'],
                    'growthRate' => $config['growth_rate'],
                    'priceSensitivity' => $config['price_sensitivity'],
                    'innovation' => round($innovation, 3),
                ];
            })->values()->toArray(),
            'kpi' => [
                'demandServedRatio' => (float) GameConfig::get('market.demand_served_ratio', 0),
                'totalDemandGenerated' => (int) GameConfig::get('market.total_demand_generated', 0),
                'globalDemandIndex' => $economicMods['global_demand_index'],
            ],
        ];

        if ($user && $user->economy) {
            $state['player'] = [
                'globalShare' => (float) $user->economy->global_market_share,
                'regionalShares' => $user->economy->regional_shares ?? [],
                'sectorShares' => $user->economy->sector_shares ?? [],
                'arpu' => (float) $user->economy->arpu,
                'innovationIndex' => (float) $user->economy->innovation_index,
                'riskExposure' => (float) $user->economy->risk_exposure,
                'marketingBudget' => (float) $user->economy->marketing_budget,
                'customerAcquisitionCost' => (float) $user->economy->customer_acquisition_cost,
            ];
        }

        return $state;
    }
}
