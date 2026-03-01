<?php

namespace App\Services\Market;

use App\Models\GameConfig;
use App\Models\MarketRegion;
use Illuminate\Support\Facades\Log;

/**
 * EconomicCycleEngine — Manages global economic state machine.
 *
 * Economic States: Growth → Expansion → Peak → Recession → Crisis → Recovery → Growth
 *
 * Each state affects:
 *   - Global demand index
 *   - Energy costs
 *   - Hardware prices
 *   - Credit costs
 *   - Customer behavior
 *   - Competitor aggression
 */
class EconomicCycleEngine
{
    /**
     * Economic state definitions.
     * duration_ticks: min and max ticks before state can transition
     * transition_weights: probability weights for next states
     */
    public const STATES = [
        'growth' => [
            'label' => 'Economic Growth',
            'duration_ticks' => [40, 120],
            'demand_modifier' => 1.15,
            'energy_cost_modifier' => 1.00,
            'hardware_price_modifier' => 1.00,
            'credit_cost_modifier' => 0.90,
            'customer_spend_modifier' => 1.10,
            'competitor_aggression_mod' => 1.0,
            'gdp_drift' => 0.001,
            'inflation_drift' => 0.0005,
            'transitions' => ['expansion' => 60, 'growth' => 25, 'recession' => 15],
        ],
        'expansion' => [
            'label' => 'Rapid Expansion',
            'duration_ticks' => [30, 80],
            'demand_modifier' => 1.30,
            'energy_cost_modifier' => 1.10,
            'hardware_price_modifier' => 1.05,
            'credit_cost_modifier' => 0.85,
            'customer_spend_modifier' => 1.20,
            'competitor_aggression_mod' => 1.3,
            'gdp_drift' => 0.002,
            'inflation_drift' => 0.001,
            'transitions' => ['peak' => 55, 'expansion' => 30, 'growth' => 15],
        ],
        'peak' => [
            'label' => 'Market Peak',
            'duration_ticks' => [20, 60],
            'demand_modifier' => 1.40,
            'energy_cost_modifier' => 1.25,
            'hardware_price_modifier' => 1.15,
            'credit_cost_modifier' => 1.10,
            'customer_spend_modifier' => 1.25,
            'competitor_aggression_mod' => 1.5,
            'gdp_drift' => 0.0005,
            'inflation_drift' => 0.002,
            'transitions' => ['recession' => 55, 'peak' => 20, 'crisis' => 25],
        ],
        'recession' => [
            'label' => 'Market Recession',
            'duration_ticks' => [30, 100],
            'demand_modifier' => 0.80,
            'energy_cost_modifier' => 0.95,
            'hardware_price_modifier' => 0.90,
            'credit_cost_modifier' => 1.20,
            'customer_spend_modifier' => 0.75,
            'competitor_aggression_mod' => 0.8,
            'gdp_drift' => -0.001,
            'inflation_drift' => -0.0005,
            'transitions' => ['crisis' => 30, 'recovery' => 50, 'recession' => 20],
        ],
        'crisis' => [
            'label' => 'Economic Crisis',
            'duration_ticks' => [20, 50],
            'demand_modifier' => 0.55,
            'energy_cost_modifier' => 1.40,
            'hardware_price_modifier' => 1.30,
            'credit_cost_modifier' => 1.50,
            'customer_spend_modifier' => 0.55,
            'competitor_aggression_mod' => 0.5,
            'gdp_drift' => -0.003,
            'inflation_drift' => 0.003,
            'transitions' => ['recovery' => 70, 'crisis' => 20, 'recession' => 10],
        ],
        'recovery' => [
            'label' => 'Economic Recovery',
            'duration_ticks' => [30, 80],
            'demand_modifier' => 1.05,
            'energy_cost_modifier' => 0.90,
            'hardware_price_modifier' => 0.95,
            'credit_cost_modifier' => 1.00,
            'customer_spend_modifier' => 1.00,
            'competitor_aggression_mod' => 1.1,
            'gdp_drift' => 0.0015,
            'inflation_drift' => -0.001,
            'transitions' => ['growth' => 65, 'recovery' => 25, 'expansion' => 10],
        ],
    ];

    /**
     * Market sectors with baseline characteristics.
     */
    public const SECTORS = [
        'gaming' => [
            'label' => 'Gaming & Streaming',
            'base_demand' => 800,
            'growth_rate' => 1.04,       // 4% growth per cycle
            'price_sensitivity' => 0.7,  // Less price sensitive
            'latency_weight' => 0.35,    // Very latency-sensitive
            'uptime_weight' => 0.25,
            'innovation_drift' => 0.02,
        ],
        'enterprise' => [
            'label' => 'Enterprise IT',
            'base_demand' => 1200,
            'growth_rate' => 1.02,
            'price_sensitivity' => 0.5,
            'latency_weight' => 0.15,
            'uptime_weight' => 0.40,      // Very uptime-sensitive
            'innovation_drift' => 0.01,
        ],
        'storage' => [
            'label' => 'Cloud Storage',
            'base_demand' => 1000,
            'growth_rate' => 1.03,
            'price_sensitivity' => 0.85,  // Very price-sensitive
            'latency_weight' => 0.05,
            'uptime_weight' => 0.30,
            'innovation_drift' => -0.005, // Storage gets cheaper over time
        ],
        'ai_compute' => [
            'label' => 'AI & ML Compute',
            'base_demand' => 400,
            'growth_rate' => 1.12,        // Explosive growth
            'price_sensitivity' => 0.3,   // Least price-sensitive
            'latency_weight' => 0.10,
            'uptime_weight' => 0.20,
            'innovation_drift' => 0.05,   // Innovation drives demand massively
        ],
        'web_hosting' => [
            'label' => 'Web Hosting',
            'base_demand' => 1500,
            'growth_rate' => 1.01,        // Mature, slow growth
            'price_sensitivity' => 0.90,  // Extremely price-sensitive
            'latency_weight' => 0.20,
            'uptime_weight' => 0.25,
            'innovation_drift' => -0.01,  // Commoditized
        ],
    ];

    // ─── TICK ──────────────────────────────────────────

    /**
     * Process one economic tick.
     * Called from MarketSimulationService at configured intervals.
     */
    public function tick(int $currentTick): void
    {
        $state = $this->getCurrentState();
        $ticksInState = $this->getTicksInState();
        $config = self::STATES[$state] ?? self::STATES['growth'];

        // 1. Apply economic drift (GDP, inflation, energy)
        $this->applyEconomicDrift($config);

        // 2. Apply regional effects
        $this->applyRegionalEffects($config);

        // 3. Check for state transition
        $this->checkStateTransition($state, $ticksInState, $config, $currentTick);

        // 4. Update global demand index
        $this->updateGlobalDemandIndex($config);

        // 5. Increment tick counter
        GameConfig::set('market.economic_cycle_tick', $ticksInState + 1, 'market');
    }

    // ─── STATE MANAGEMENT ──────────────────────────────

    public function getCurrentState(): string
    {
        return GameConfig::get('market.economic_state', 'growth');
    }

    public function getTicksInState(): int
    {
        return (int) GameConfig::get('market.economic_cycle_tick', 0);
    }

    public function getStateConfig(?string $state = null): array
    {
        $state ??= $this->getCurrentState();
        return self::STATES[$state] ?? self::STATES['growth'];
    }

    /**
     * Get all current modifiers for other services to consume.
     */
    public function getModifiers(): array
    {
        $config = $this->getStateConfig();
        return [
            'state' => $this->getCurrentState(),
            'label' => $config['label'],
            'demand' => $config['demand_modifier'],
            'energy_cost' => $config['energy_cost_modifier'],
            'hardware_price' => $config['hardware_price_modifier'],
            'credit_cost' => $config['credit_cost_modifier'],
            'customer_spend' => $config['customer_spend_modifier'],
            'competitor_aggression' => $config['competitor_aggression_mod'],
            'gdp_growth' => (float) GameConfig::get('market.gdp_growth_rate', 0.025),
            'inflation' => (float) GameConfig::get('market.inflation_rate', 0.02),
            'energy_index' => (float) GameConfig::get('market.energy_cost_index', 1.0),
            'global_demand_index' => (float) GameConfig::get('market.global_demand_index', 100),
        ];
    }

    // ─── PRIVATE METHODS ───────────────────────────────

    private function applyEconomicDrift(array $config): void
    {
        // GDP drift with small random variance
        $gdpGrowth = (float) GameConfig::get('market.gdp_growth_rate', 0.025);
        $gdpGrowth += $config['gdp_drift'] + (mt_rand(-50, 50) / 100000);
        $gdpGrowth = max(-0.05, min(0.15, $gdpGrowth));
        GameConfig::set('market.gdp_growth_rate', round($gdpGrowth, 5), 'market');

        // Inflation drift
        $inflation = (float) GameConfig::get('market.inflation_rate', 0.02);
        $inflation += $config['inflation_drift'] + (mt_rand(-30, 30) / 100000);
        $inflation = max(-0.05, min(0.20, $inflation));
        GameConfig::set('market.inflation_rate', round($inflation, 5), 'market');

        // Energy cost index (tied to economic state + inflation)
        $energyIndex = (float) GameConfig::get('market.energy_cost_index', 1.0);
        $energyDrift = ($config['energy_cost_modifier'] - $energyIndex) * 0.05; // Smooth convergence
        $energyIndex += $energyDrift + (mt_rand(-20, 20) / 10000);
        $energyIndex = max(0.5, min(3.0, $energyIndex));
        GameConfig::set('market.energy_cost_index', round($energyIndex, 4), 'market');
    }

    private function applyRegionalEffects(array $config): void
    {
        $regions = MarketRegion::all();

        foreach ($regions as $region) {
            // GDP propagation — regional GDP drifts toward global GDP
            $globalGdp = (float) GameConfig::get('market.gdp_growth_rate', 0.025);
            $regionGdpDelta = ($globalGdp - (float) $region->gdp_growth) * 0.1;
            $region->gdp_growth = round(
                max(-0.10, min(0.15, (float) $region->gdp_growth + $regionGdpDelta + (mt_rand(-20, 20) / 100000))),
                4
            );

            // Infrastructure saturation grows slowly during expansion/peak
            if (in_array($this->getCurrentState(), ['expansion', 'peak'])) {
                $region->infra_saturation = min(95, (float) $region->infra_saturation + mt_rand(0, 10) / 100);
            } elseif (in_array($this->getCurrentState(), ['recession', 'crisis'])) {
                $region->infra_saturation = max(10, (float) $region->infra_saturation - mt_rand(0, 5) / 100);
            }

            // Energy cost follows global + regional variance
            $globalEnergy = (float) GameConfig::get('market.energy_cost_index', 1.0);
            $region->energy_cost_multiplier = round(
                max(0.5, min(3.0, $globalEnergy + (mt_rand(-100, 100) / 1000))),
                3
            );

            // Political stability can shift during crises
            if ($this->getCurrentState() === 'crisis') {
                $region->political_stability = max(20, (float) $region->political_stability - mt_rand(0, 50) / 100);
            } elseif (in_array($this->getCurrentState(), ['growth', 'recovery'])) {
                $region->political_stability = min(98, (float) $region->political_stability + mt_rand(0, 20) / 100);
            }

            // Cyber threat level rises during crises and peaks
            if (in_array($this->getCurrentState(), ['crisis', 'peak'])) {
                $region->cyber_threat_level = min(95, (float) $region->cyber_threat_level + mt_rand(0, 30) / 100);
            } else {
                $region->cyber_threat_level = max(5, (float) $region->cyber_threat_level - mt_rand(0, 15) / 100);
            }

            $region->save();
        }
    }

    private function checkStateTransition(string $currentState, int $ticksInState, array $config, int $currentTick): void
    {
        [$minDuration, $maxDuration] = $config['duration_ticks'];

        // Not enough time in current state
        if ($ticksInState < $minDuration) return;

        // Increasing probability of transition as we approach max duration
        $progress = ($ticksInState - $minDuration) / max(1, $maxDuration - $minDuration);
        $transitionChance = min(0.95, $progress * 0.8 + 0.05);

        if (mt_rand(1, 1000) / 1000 > $transitionChance) return;

        // Weighted random selection of next state
        $transitions = $config['transitions'];
        $nextState = $this->weightedRandom($transitions);

        if ($nextState !== $currentState) {
            $oldState = $currentState;
            GameConfig::set('market.economic_state', $nextState, 'market');
            GameConfig::set('market.economic_cycle_tick', 0, 'market');
            GameConfig::set('market.last_transition_tick', $currentTick, 'market');

            Log::info("[MarketCycle] Transition: {$oldState} → {$nextState} at tick {$currentTick}");
        }
    }

    private function updateGlobalDemandIndex(array $config): void
    {
        $currentDemand = (float) GameConfig::get('market.global_demand_index', 100);
        $target = 100 * $config['demand_modifier'];

        // Smooth convergence toward target demand
        $delta = ($target - $currentDemand) * 0.08;
        $delta += mt_rand(-50, 50) / 1000; // Small noise
        $newDemand = max(20, min(300, $currentDemand + $delta));

        GameConfig::set('market.global_demand_index', round($newDemand, 2), 'market');
    }

    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) return $key;
        }

        return array_key_first($weights);
    }
}
