<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'seller_name',
        'item_type',
        'item_key',
        'condition',
        'price',
        'original_price',
        'expires_at',
        'is_sold',
        'specs',
        'defect_chance',
    ];

    protected $casts = [
        'condition' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'defect_chance' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_sold' => 'boolean',
        'specs' => 'array',
    ];

    public function scopeActive(Builder $query): void
    {
        $query->where('is_sold', false)
              ->where('expires_at', '>', now());
    }

    public function getDiscountPercent(): int
    {
        if ($this->original_price <= 0) return 0;
        return (int) round((1 - ($this->price / $this->original_price)) * 100);
    }
}
