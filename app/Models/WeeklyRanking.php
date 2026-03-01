<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyRanking extends Model
{
    protected $fillable = [
        'year',
        'week',
        'user_id',
        'rank',
        'balance',
        'reputation',
        'level',
        'reward_granted'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
