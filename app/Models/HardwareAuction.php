<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareAuction extends Model
{
    use HasUuids;

    protected $fillable = [
        'item_type',
        'item_key',
        'item_specs',
        'seller_name',
        'starting_price',
        'current_bid',
        'highest_bidder_id',
        'condition',
        'defect_chance',
        'starts_at',
        'ends_at',
        'is_processed',
    ];

    protected $casts = [
        'item_specs' => 'array',
        'starting_price' => 'decimal:2',
        'current_bid' => 'decimal:2',
        'condition' => 'integer',
        'defect_chance' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_processed' => 'boolean',
    ];

    public function highestBidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'highest_bidder_id');
    }

    public function getMinNextBid(): float
    {
        $current = $this->current_bid ?: $this->starting_price;
        // 5% minimum raise
        return round($current * 1.05, 2);
    }

    public function isActive(): bool
    {
        return !$this->is_processed && $this->starts_at <= now() && $this->ends_at > now();
    }
}
