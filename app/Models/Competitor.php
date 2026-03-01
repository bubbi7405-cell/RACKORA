<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competitor extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'personality',
        'archetype',
        'tagline',
        'reputation',
        'assets_value',
        'market_share',
        'regional_shares',
        'sector_shares',
        'pricing_strategy',
        'focus_sector',
        'headquarters_region',
        'aggression',
        'intelligence',
        'capacity_score',
        'uptime_score',
        'latency_score',
        'price_modifier',
        'marketing_budget',
        'innovation_index',
        'last_decision_tick',
        'decision_cooldown',
        'profit_margin',
        'expansion_streak',
        'contraction_streak',
        'color_primary',
        'logo_slug',
        'player_enmity',
        'has_mole',
        'last_attack_at',
        'status',
    ];

    protected $casts = [
        'reputation' => 'decimal:2',
        'assets_value' => 'decimal:2',
        'market_share' => 'decimal:2',
        'player_enmity' => 'integer',
        'has_mole' => 'boolean',
        'last_attack_at' => 'datetime',
        'regional_shares' => 'array',
        'sector_shares' => 'array',
        'aggression' => 'integer',
        'intelligence' => 'integer',
        'capacity_score' => 'decimal:2',
        'uptime_score' => 'decimal:3',
        'latency_score' => 'decimal:2',
        'price_modifier' => 'decimal:3',
        'marketing_budget' => 'decimal:2',
        'innovation_index' => 'decimal:2',
        'last_decision_tick' => 'integer',
        'decision_cooldown' => 'integer',
        'profit_margin' => 'decimal:3',
        'expansion_streak' => 'integer',
        'contraction_streak' => 'integer',
    ];

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'personality' => $this->personality,
            'archetype' => $this->archetype,
            'tagline' => $this->tagline,
            'reputation' => (float) $this->reputation,
            'assetsValue' => (float) $this->assets_value,
            'marketShare' => (float) $this->market_share,
            'playerEnmity' => (int) $this->player_enmity,
            'hasMole' => (bool) $this->has_mole,
            'lastAttackAt' => $this->last_attack_at?->toIso8601String(),
            'regionalShares' => $this->regional_shares ?? [],
            'sectorShares' => $this->sector_shares ?? [],
            'hqRegion' => $this->headquarters_region,
            'pricing' => $this->pricing_strategy,
            'focus' => $this->focus_sector,
            'aggression' => $this->aggression,
            'intelligence' => $this->intelligence,
            'capacityScore' => (float) $this->capacity_score,
            'uptimeScore' => (float) $this->uptime_score,
            'latencyScore' => (float) $this->latency_score,
            'priceModifier' => (float) $this->price_modifier,
            'innovationIndex' => (float) $this->innovation_index,
            'profitMargin' => (float) $this->profit_margin,
            'color' => $this->color_primary,
            'status' => $this->status,
        ];
    }
}
