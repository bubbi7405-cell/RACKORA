<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketRegion extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'label',
        'gdp_growth',
        'political_stability',
        'infra_saturation',
        'energy_cost_multiplier',
        'regulation_level',
        'demand_base',
        'demand_growth_factor',
        'ip_pool_capacity',
        'ip_pool_used',
        'ix_available',
        'ix_latency_bonus',
        'incident_rate_modifier',
        'cyber_threat_level',
    ];

    protected $casts = [
        'gdp_growth' => 'decimal:4',
        'political_stability' => 'decimal:2',
        'infra_saturation' => 'decimal:2',
        'energy_cost_multiplier' => 'decimal:3',
        'regulation_level' => 'decimal:2',
        'demand_growth_factor' => 'decimal:4',
        'ip_pool_capacity' => 'integer',
        'ip_pool_used' => 'integer',
        'ix_available' => 'boolean',
        'ix_latency_bonus' => 'decimal:2',
        'incident_rate_modifier' => 'decimal:3',
        'cyber_threat_level' => 'decimal:2',
    ];

    /**
     * Get the current IP availability percentage.
     */
    public function getIpAvailabilityPercent(): float
    {
        if ($this->ip_pool_capacity <= 0) return 0;
        return round((1 - ($this->ip_pool_used / $this->ip_pool_capacity)) * 100, 2);
    }

    /**
     * Get the economic attractiveness score (0-100).
     * High GDP growth + low energy costs + low regulation = attractive.
     */
    public function getAttractivenessScore(): float
    {
        $gdpScore = min(100, max(0, ($this->gdp_growth + 0.05) / 0.10 * 100)); // -5% to +5% mapped to 0-100
        $energyScore = max(0, 100 - (($this->energy_cost_multiplier - 0.5) / 2.0 * 100));
        $regScore = max(0, 100 - $this->regulation_level);
        $stabilityScore = $this->political_stability;
        $saturationPenalty = $this->infra_saturation * 0.5;

        return round(
            ($gdpScore * 0.25 + $energyScore * 0.20 + $regScore * 0.15 + $stabilityScore * 0.25) - $saturationPenalty * 0.15,
            2
        );
    }

    /**
     * Get the effective demand multiplier for this region.
     * Combines GDP growth, political stability, and saturation effects.
     */
    public function getEffectiveDemandMultiplier(): float
    {
        // GDP impact: use a softer multiplicative scale instead of additive.
        // GDP -10% → 0.60x, GDP 0% → 1.0x, GDP +10% → 1.40x
        $gdpFactor = max(0.4, 1.0 + (float) $this->gdp_growth * 4);

        // Political stability: linear scale (100% = 1.0, 50% = 0.5)
        $stabilityFactor = max(0.3, (float) $this->political_stability / 100);

        // Infrastructure saturation: high saturation reduces demand slightly
        $saturationFactor = 1 - ((float) $this->infra_saturation / 250);

        // Energy cost: expensive regions get less demand
        $energyFactor = 1 / max(0.5, (float) $this->energy_cost_multiplier);

        $result = $gdpFactor * $stabilityFactor * $saturationFactor * $energyFactor;

        return max(0.25, round($result, 4));
    }

    /**
     * Export for frontend game state.
     */
    public function toGameState(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'gdpGrowth' => (float) $this->gdp_growth,
            'politicalStability' => (float) $this->political_stability,
            'infraSaturation' => (float) $this->infra_saturation,
            'energyCostMultiplier' => (float) $this->energy_cost_multiplier,
            'regulationLevel' => (float) $this->regulation_level,
            'demandBase' => $this->demand_base,
            'demandGrowthFactor' => (float) $this->demand_growth_factor,
            'ipAvailability' => $this->getIpAvailabilityPercent(),
            'ixAvailable' => $this->ix_available,
            'attractiveness' => $this->getAttractivenessScore(),
            'effectiveDemand' => $this->getEffectiveDemandMultiplier(),
            'incidentRisk' => (float) $this->incident_rate_modifier,
            'cyberThreat' => (float) $this->cyber_threat_level,
        ];
    }
}
