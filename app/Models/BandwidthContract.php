<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BandwidthContract extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'isp_name',
        'isp_tier',
        'capacity_mbps',
        'monthly_cost',
        'commitment',
        'burst_ratio',
        'regions',
        'latency_bonus_ms',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'monthly_cost' => 'decimal:2',
        'burst_ratio' => 'decimal:2',
        'latency_bonus_ms' => 'decimal:2',
        'regions' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the effective bandwidth including burst.
     */
    public function getEffectiveCapacityMbps(): int
    {
        return (int) ($this->capacity_mbps * $this->burst_ratio);
    }

    /**
     * Get the tier label.
     */
    public function getTierLabel(): string
    {
        return match ($this->isp_tier) {
            'tier1' => 'Tier-1 Global Transit',
            'tier2' => 'Tier-2 Regional Provider',
            'tier3' => 'Tier-3 Local ISP',
            default => 'Unknown Tier',
        };
    }

    /**
     * Check if contract covers a specific region.
     */
    public function coversRegion(string $region): bool
    {
        if (!$this->regions) return true; // No region filter = global
        return in_array($region, $this->regions);
    }

    /**
     * Scope to active contracts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
