<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeeringAgreement extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'competitor_id',
        'provider_name',
        'tier',
        'monthly_cost',
        'capacity_gbps',
        'latency_bonus',
        'hops_reduction',
        'status',
        'signed_at',
        'expires_at',
    ];

    protected $casts = [
        'monthly_cost' => 'decimal:2',
        'latency_bonus' => 'decimal:2',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
