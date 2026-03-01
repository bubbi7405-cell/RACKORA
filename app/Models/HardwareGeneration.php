<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareGeneration extends Model
{
    protected $fillable = [
        'generation',
        'name',
        'era',
        'efficiency_multiplier',
        'power_multiplier',
        'price_multiplier',
        'depreciation_rate',
        'bonuses',
        'is_available',
        'released_at',
        'discontinued_at',
    ];

    protected $casts = [
        'efficiency_multiplier' => 'decimal:2',
        'power_multiplier' => 'decimal:2',
        'price_multiplier' => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'bonuses' => 'array',
        'is_available' => 'boolean',
        'released_at' => 'datetime',
        'discontinued_at' => 'datetime',
    ];

    /**
     * Get the current generation that players should buy
     */
    public static function current(): ?self
    {
        return static::where('era', 'current')->first();
    }

    /**
     * Get all purchasable generations
     */
    public static function available()
    {
        return static::where('is_available', true)->orderBy('generation')->get();
    }

    /**
     * Check if this generation is outdated
     */
    public function isLegacy(): bool
    {
        return $this->era === 'legacy';
    }

    /**
     * Get effective specs for a catalog item
     */
    public function applyToSpecs(array $baseSpecs): array
    {
        return [
            'cpuCores' => (int) ceil(($baseSpecs['cpuCores'] ?? 0) * $this->efficiency_multiplier),
            'ramGb' => (int) ceil(($baseSpecs['ramGb'] ?? 0) * $this->efficiency_multiplier),
            'storageTb' => $baseSpecs['storageTb'] ?? 0,
            'bandwidthMbps' => $baseSpecs['bandwidthMbps'] ?? 0,
        ];
    }

    /**
     * Get adjusted price for a catalog item
     */
    public function adjustPrice(float $basePrice): float
    {
        return round($basePrice * $this->price_multiplier, 2);
    }

    /**
     * Get adjusted power draw for generation
     */
    public function adjustPower(float $basePower): float
    {
        return round($basePower * $this->power_multiplier, 2);
    }
}
