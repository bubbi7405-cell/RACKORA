<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergyFuture extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'region',
        'total_kwh',
        'remaining_kwh',
        'buy_price',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'total_kwh' => 'decimal:4',
        'remaining_kwh' => 'decimal:4',
        'buy_price' => 'decimal:4',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
