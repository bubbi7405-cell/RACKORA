<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CorporateHq extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'level',
        'prestige_score',
        'visual_style',
        'amenities',
        'metadata'
    ];

    protected $casts = [
        'amenities' => 'array',
        'metadata' => 'array',
        'prestige_score' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPrestigeBonus(): float
    {
        return $this->prestige_score / 1000; // e.g. 500 prestige = +0.5 multiplier for whale attraction
    }
}
