<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorldEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'type',
        'modifier_type',
        'modifier_value',
        'starts_at',
        'ends_at',
        'is_active'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'modifier_value' => 'decimal:2',
    ];

    /**
     * Get active modifiers for game logic.
     * Returns an associative array [modifier_type => modifier_value]
     */
    public static function getActiveModifiers(): array
    {
        return self::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->whereNotNull('modifier_type')
            ->get()
            ->groupBy('modifier_type')
            ->map(fn($events) => $events->avg('modifier_value'))
            ->toArray();
    }
}
