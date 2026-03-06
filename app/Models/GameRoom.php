<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Services\Game\ResearchService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRoom extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'level',
        'max_racks',
        'max_power_kw',
        'has_circuit_breaker_tripped',
        'max_cooling_kw',
        'cooling_health',
        'airflow_type',
        'redundancy_level',
        'bandwidth_gbps',
        'rent_per_hour',
        'is_unlocked',
        'position',
        'upgrades',
        'unlocked_at',
        'region',
        'power_cost_kwh',
        'latency_ms',
        'last_pr_tour_at',
        'wallpaper_id',
        'theme',
        'cooling_intensity',
        'pue_score',
        'has_diesel_backup',
        'diesel_fuel_liters',
        'diesel_fuel_capacity',
        'solar_capacity_kw',
        'metadata',
        'humidity',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'position' => 'array',
        'upgrades' => 'array',
        'unlocked_at' => 'datetime',
        'rent_per_hour' => 'decimal:2',
        'cooling_health' => 'decimal:2',
        'power_cost_kwh' => 'decimal:4',
        'latency_ms' => 'decimal:2',
        'last_pr_tour_at' => 'datetime',
        'cooling_intensity' => 'decimal:2',
        'pue_score' => 'decimal:2',
        'has_diesel_backup' => 'boolean',
        'diesel_fuel_liters' => 'integer',
        'diesel_fuel_capacity' => 'integer',
        'solar_capacity_kw' => 'float',
        'has_circuit_breaker_tripped' => 'boolean',
        'metadata' => 'json',
        'humidity' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => RoomType::tryFrom($value) ?? new RoomType(RoomType::BASEMENT),
            set: fn ($value) => $value instanceof RoomType ? $value->value : $value,
        );
    }

    public function getEffectiveCooling(): float
    {
        $healthFactor = $this->cooling_health / 100;
        $intensityFactor = $this->cooling_intensity / 100;
        $base = $this->max_cooling_kw * $healthFactor * $intensityFactor;
        
        $multiplier = match($this->airflow_type) {
            'hot_aisle' => 1.25,
            'cold_aisle_containment' => 1.50,
            default => 1.0
        };

        return $base * $multiplier;
    }

    public function getRedundancyLabel(): string
    {
        return match($this->redundancy_level) {
            1 => 'Standard (Tier 1)',
            2 => 'N+1 Redundancy (Tier 2)',
            3 => '2N Redundancy (Tier 3)',
            4 => '2(N+1) Fault Tolerant (Tier 4)',
            default => 'Standard'
        };
    }

    /**
     * Get protection multipliers
     * Returns reduction in failure probability
     */
    public function getRedundancyProtection(): array
    {
        return match($this->redundancy_level) {
            1 => ['prob_reduction' => 0.0, 'capacity_recovery' => 0.0],
            2 => ['prob_reduction' => 0.35, 'capacity_recovery' => 0.20], // 35% less chance, 20% backup
            3 => ['prob_reduction' => 0.70, 'capacity_recovery' => 0.50], // 70% less chance, 50% backup
            4 => ['prob_reduction' => 0.95, 'capacity_recovery' => 0.85], // 95% less chance, 85% backup
            default => ['prob_reduction' => 0.0, 'capacity_recovery' => 0.0]
        };
    }


    public function racks(): HasMany
    {
        return $this->hasMany(ServerRack::class, 'room_id');
    }

    public function getCurrentPowerUsage(): float
    {
        return (float) $this->racks->sum('current_power_kw');
    }

    public function getPowerCapacityPercent(): float
    {
        $capacity = $this->getEffectiveMaxPowerKw();
        if ($capacity <= 0) return 0;
        return ($this->getCurrentPowerUsage() / $capacity) * 100;
    }

    public function getCurrentHeatOutput(): float
    {
        return (float) $this->racks->sum('current_heat_kw');
    }

    public function getCoolingCapacityPercent(): float
    {
        $cooling = $this->getEffectiveCooling();
        if ($cooling <= 0) return 0;
        return ($this->getCurrentHeatOutput() / $cooling) * 100;
    }

    public function getCurrentBandwidthUsage(): float
    {
        $totalMbps = 0;
        foreach ($this->racks as $rack) {
            foreach ($rack->servers as $server) {
                // Sum requirements from active customer orders on this server
                $totalMbps += $server->activeOrders->sum(function($order) {
                    return $order->requirements['bandwidth'] ?? 0;
                });
            }
        }
        return $totalMbps / 1000; // Mbps to Gbps
    }

    public function getBandwidthCapacityPercent(): float
    {
        $researchService = app(ResearchService::class);
        $bandwidthBonus = $researchService->getBonus($this->user, 'bandwidth_capacity_bonus');
        $effectiveBandwidth = $this->bandwidth_gbps * (1 + $bandwidthBonus);

        if ($effectiveBandwidth <= 0) return 0;
        return ($this->getCurrentBandwidthUsage() / $effectiveBandwidth) * 100;
    }

    public function isOverheating(): bool
    {
        return $this->racks->contains(fn($rack) => $rack->temperature > 35) || $this->getCoolingCapacityPercent() > 100;
    }

    public function getEffectiveMaxPowerKw(): float
    {
        $maxPower = (float) $this->max_power_kw;
        
        // FEATURE 93: Regional Power Rationing
        $activeBlackout = \App\Models\GameEvent::where('affected_region', $this->region)
            ->where('type', \App\Enums\EventType::REGIONAL_BLACKOUT)
            ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
            ->first();
            
        if ($activeBlackout) {
            $multiplier = (float) ($activeBlackout->metadata['capacity_multiplier'] ?? 0.4);
            $maxPower *= $multiplier;
        }
        
        return $maxPower;
    }

    public function isPowerOverloaded(): bool
    {
        return $this->getCurrentPowerUsage() > $this->getEffectiveMaxPowerKw();
    }

    public function isBandwidthSaturated(): bool
    {
        $researchService = app(ResearchService::class);
        $bandwidthBonus = $researchService->getBonus($this->user, 'bandwidth_capacity_bonus');
        $effectiveBandwidth = $this->bandwidth_gbps * (1 + $bandwidthBonus);

        return $this->getCurrentBandwidthUsage() > $effectiveBandwidth;
    }
    public function canAddRack(): bool
    {
        return $this->racks()->count() < $this->max_racks;
    }

    /**
     * Get current network tier (from upgrades)
     */
    public function getNetworkTier(): int
    {
        return $this->upgrades['network_tier'] ?? 0;
    }

    public function getNetworkTierLabel(): string
    {
        return match($this->getNetworkTier()) {
            0 => 'Standard (Tier 3 ISP)',
            1 => 'Premium Fiber (Tier 2)',
            2 => 'Enterprise Backbone (Tier 1)',
            3 => 'Global Anycast (Direct Uplink)',
            default => 'Standard'
        };
    }

    /**
     * Calculate base regional latency
     */
    public function calculateRegionalLatencyBase(): float
    {
        $regionBases = [
            'us_east' => 25.0,
            'eu_central' => 35.0,
            'asia_east' => 110.0,
            'sa_east' => 90.0,
            'us_west' => 45.0
        ];
        
        $base = $regionBases[$this->region] ?? 50.0;
        
        // Tier impact on base latency
        $tier = $this->getNetworkTier();
        $tierModifiers = [1.0, 0.85, 0.7, 0.5]; // 15% reduction per tier approx
        
        $latency = $base * ($tierModifiers[$tier] ?? 1.0);

        // Research Bonus: Latency Reduction
        $researchService = app(ResearchService::class);
        $latencyBonus = $researchService->getBonus($this->user, 'latency_reduction');
        if ($latencyBonus > 0) {
            $latency *= (1.0 - $latencyBonus);
        }

        return $latency;
    }

    public function calculatePue(): float
    {
        $basePue = match($this->type->value) {
            RoomType::BASEMENT => 2.5,
            RoomType::GARAGE => 2.0,
            RoomType::SMALL_HALL => 1.8,
            RoomType::DATA_CENTER => 1.5,
            default => 2.5
        };

        // Redundancy increases PUE (more overhead for keeping standby systems alive)
        $redundancyPenalty = ($this->redundancy_level - 1) * 0.1;
        $basePue += $redundancyPenalty;

        // Upgrades reduce PUE
        $researchService = app(ResearchService::class);
        $efficiencyBonus = $researchService->getBonus($this->user, 'power_efficiency');
        
        $multiplier = 1.0;
        if ($this->airflow_type === 'hot_aisle') $multiplier -= 0.15;
        if ($this->airflow_type === 'cold_aisle_containment') $multiplier -= 0.30;
        
        // FEATURE 203: Weather affects PUE
        $weather = \Illuminate\Support\Facades\Cache::get('regional_weather', []);
        $weatherPueMod = (float) ($weather[$this->region]['modifiers']['pue_mod'] ?? 1.0);
        
        // Final PUE
        $pue = $basePue * $multiplier * (1.0 - min(0.5, $efficiencyBonus)) * $weatherPueMod;
        
        return max(1.05, $pue); // Absolute physical minimum is near 1.0
    }

    public function toGameState(): array
    {
        // Check for active power outage
        $hasPowerOutage = \App\Models\GameEvent::where('affected_room_id', $this->id)
             ->where('type', \App\Enums\EventType::POWER_OUTAGE)
             ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
             ->exists();

        $protection = $this->getRedundancyProtection();
        
        // If we have an outage, but also redundancy, we don't drop to 0.
        $effectiveCooling = $this->getEffectiveCooling();
        if ($hasPowerOutage) {
            $effectiveCooling *= $protection['capacity_recovery'];
        }

        $heatOutput = $this->getCurrentHeatOutput();
        $coolingPercent = $effectiveCooling > 0 ? ($heatOutput / $effectiveCooling) * 100 : ($hasPowerOutage ? 999 : 0);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'theme' => $this->theme ?? 'classic',
            'wallpaper' => $this->wallpaper_id ?? 'default',
            'region' => $this->region, 
            'powerCostKwh' => (float) $this->power_cost_kwh, 
            'latency' => (float) $this->latency_ms,
            'level' => $this->level,
            'maxRacks' => $this->max_racks,
            'usedRacks' => $this->racks->count(),
            'power' => [
                'max' => (float) $this->max_power_kw,
                'effectiveMax' => $this->getEffectiveMaxPowerKw(),
                'current' => $this->getCurrentPowerUsage(),
                'percent' => $this->getPowerCapacityPercent(),
                'pue' => (float) $this->calculatePue(),
                'wasteHeatKw' => round($this->getCurrentPowerUsage() * max(0, $this->calculatePue() - 1.0), 2),
                'breakerTripped' => $this->has_circuit_breaker_tripped,
            ],
            'carbonTax' => $this->calculateCarbonTaxInfo(),
            'diesel' => [
                'hasBackup' => $this->has_diesel_backup,
                'fuel' => $this->diesel_fuel_liters,
                'capacity' => $this->diesel_fuel_capacity,
            ],
            'cooling' => [
                'max' => (float) $this->max_cooling_kw,
                'effective' => (float) $effectiveCooling,
                'current' => $this->getCurrentHeatOutput(),
                'percent' => $coolingPercent,
                'health' => (float) $this->cooling_health,
                'airflow' => $this->airflow_type,
                'redundancy' => $this->redundancy_level,
                'redundancyLabel' => $this->getRedundancyLabel(),
            ],
            'environment' => [
                'humidity' => (float) $this->humidity,
            ],
            'bandwidth' => [
                'max' => (float) $this->bandwidth_gbps,
                'current' => (float) $this->getCurrentBandwidthUsage(),
                'percent' => $this->getBandwidthCapacityPercent(),
                'networkTier' => $this->getNetworkTier(),
                'networkTierLabel' => $this->getNetworkTierLabel(),
            ],
            'rentPerHour' => (float) $this->rent_per_hour,
            'isUnlocked' => $this->is_unlocked,
            'hasAcademy' => in_array('academy', $this->upgrades ?? []),
            'lastPrTourAt' => $this->last_pr_tour_at?->toIso8601String(),
            'position' => $this->position ?? ['x' => 0, 'y' => 0],
            'warnings' => [
                'overheating' => $this->isOverheating() || $hasPowerOutage, // Power outage implies overheating risk
                'powerOverload' => $this->isPowerOverloaded(),
                'bandwidthSaturated' => $this->isBandwidthSaturated(),
                'powerOutage' => $hasPowerOutage,
            ],
            'racks' => $this->racks->map(fn($rack) => $rack->toGameState())->toArray(),
        ];
    }

    /**
     * FEATURE 268: Calculate carbon tax info for this room
     */
    public function calculateCarbonTaxInfo(): array
    {
        $regions = \App\Models\GameConfig::get('regions', []);
        $regionKey = $this->region ?? 'us_east';
        $carbonRate = (float) ($regions[$regionKey]['carbon_tax_per_kw'] ?? 0.0);

        $pue = $this->calculatePue();
        $itPower = $this->getCurrentPowerUsage();
        $wasteHeatKw = $itPower * max(0, $pue - 1.0);

        // Heat recovery upgrade
        $upgrades = $this->upgrades ?? [];
        $hasHeatRecovery = in_array('heat_recovery', $upgrades);
        if ($hasHeatRecovery) {
            $wasteHeatKw *= 0.6;
        }

        $hourlyTax = $wasteHeatKw * $carbonRate;

        return [
            'ratePerKw' => $carbonRate,
            'wasteHeatKw' => round($wasteHeatKw, 2),
            'hourlyTax' => round($hourlyTax, 2),
            'hasHeatRecovery' => $hasHeatRecovery,
        ];
    }
}
