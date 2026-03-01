<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMarket extends Model
{
    protected $fillable = [
        'ipv4_scarcity_index', // 1-100
        'ipv4_base_price',
        'global_demand_factor',
        'last_crash_at',
    ];

    public static function getMarket(): self
    {
        return self::firstOrCreate([], [
            'ipv4_scarcity_index' => 45,
            'ipv4_base_price' => 2500,
            'global_demand_factor' => 1.0,
        ]);
    }

    public function getPriceForSize(int $size): float
    {
        // Price formula: base * size * dependency(scarcity)
        $scarcityFactor = 1.0 + (pow($this->ipv4_scarcity_index / 50, 2));
        return $this->ipv4_base_price * ($size / 16) * $scarcityFactor * $this->global_demand_factor;
    }
}
