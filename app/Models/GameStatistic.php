<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_statistics';
    public $timestamps = false; // We only use created_at, managed by DB default

    protected $fillable = [
        'user_id',
        'tick',
        'revenue',
        'expenses',
        'balance',
        'reputation',
        'active_customers',
        'active_servers',
        'avg_satisfaction'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'revenue' => 'decimal:2',
        'expenses' => 'decimal:2',
        'balance' => 'decimal:2',
        'reputation' => 'decimal:2',
        'avg_satisfaction' => 'decimal:2',
    ];
}
