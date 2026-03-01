<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DarkFiberLease extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = [
        'user_id', 'region_a', 'region_b', 'provider_name',
        'monthly_cost', 'setup_fee', 'latency_reduction',
        'signed_at', 'expires_at', 'status'
    ];

    protected $casts = [
        'monthly_cost' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'latency_reduction' => 'decimal:2',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
