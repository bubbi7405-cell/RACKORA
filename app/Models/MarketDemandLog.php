<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketDemandLog extends Model
{
    protected $fillable = [
        'tick',
        'region',
        'sector',
        'demand_generated',
        'demand_served',
        'player_served',
        'competitor_served',
        'unmet_demand',
        'avg_price',
    ];

    protected $casts = [
        'competitor_served' => 'array',
        'demand_generated' => 'integer',
        'demand_served' => 'integer',
        'player_served' => 'integer',
        'unmet_demand' => 'integer',
        'avg_price' => 'decimal:2',
    ];
}
