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
        'affected_regions',
        'starts_at',
        'ends_at',
        'is_active'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'modifier_value' => 'decimal:2',
        'affected_regions' => 'array',
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

    /**
     * Get active modifiers that affect a specific region.
     * Returns modifiers that have NO region restriction (global) OR include the given region.
     */
    public static function getActiveModifiersForRegion(string $region): array
    {
        $events = self::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->whereNotNull('modifier_type')
            ->get();

        // Filter: global events (no region) OR events that include this region
        $filtered = $events->filter(function ($event) use ($region) {
            $regions = $event->affected_regions;
            // Null or empty = affects ALL regions (global)
            if (empty($regions)) return true;
            return in_array($region, $regions);
        });

        return $filtered->groupBy('modifier_type')
            ->map(fn($events) => $events->avg('modifier_value'))
            ->toArray();
    }

    /**
     * Check if this event affects a specific region.
     */
    public function affectsRegion(string $region): bool
    {
        $regions = $this->affected_regions;
        if (empty($regions)) return true; // Global event
        return in_array($region, $regions);
    }

    /**
     * Check if this event is global (affects all regions).
     */
    public function isGlobal(): bool
    {
        return empty($this->affected_regions);
    }

    /**
     * Get all currently active events as formatted game data.
     */
    public static function getActiveEventsForDisplay(): array
    {
        return self::where('is_active', true)
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'type' => $e->type,
                'modifier_type' => $e->modifier_type,
                'modifier_value' => (float) $e->modifier_value,
                'affected_regions' => $e->affected_regions ?? [],
                'is_global' => $e->isGlobal(),
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'remaining_minutes' => $e->ends_at ? max(0, now()->diffInMinutes($e->ends_at, false)) : null,
            ])
            ->toArray();
    }
}
