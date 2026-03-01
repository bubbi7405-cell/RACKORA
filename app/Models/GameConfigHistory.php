<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameConfigHistory extends Model
{
    protected $table = 'game_config_history';

    protected $fillable = [
        'config_key',
        'old_value',
        'new_value',
        'user_id',
        'comment',
        'version'
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
