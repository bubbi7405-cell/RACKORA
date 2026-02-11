<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRoom extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'level',
        'max_racks',
        'max_power_kw',
        'max_cooling_kw',
        'bandwidth_gbps',
        'rent_per_hour',
        'is_unlocked',
        'position',
        'upgrades',
        'unlocked_at',
    ];

    protected $casts = [
        'type' => RoomType::class,
        'is_unlocked' => 'boolean',
        'position' => 'array',
        'upgrades' => 'array',
        'unlocked_at' => 'datetime',
        'rent_per_hour' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function racks(): HasMany
    {
        return $this->hasMany(ServerRack::class, 'room_id');
    }

    public function getCurrentPowerUsage(): float
    {
        return $this->racks->sum('current_power_kw');
    }

    public function getCurrentHeatOutput(): float
    {
        return $this->racks->sum('current_heat_kw');
    }

    public function getAvailableRackSlots(): int
    {
        return $this->max_racks - $this->racks->count();
    }

    public function canAddRack(): bool
    {
        return $this->getAvailableRackSlots() > 0;
    }

    public function getPowerCapacityPercent(): float
    {
        if ($this->max_power_kw === 0) return 0;
        return ($this->getCurrentPowerUsage() / $this->max_power_kw) * 100;
    }

    public function getCoolingCapacityPercent(): float
    {
        if ($this->max_cooling_kw === 0) return 0;
        return ($this->getCurrentHeatOutput() / $this->max_cooling_kw) * 100;
    }

    public function isOverheating(): bool
    {
        return $this->getCoolingCapacityPercent() > 95;
    }

    public function isPowerOverloaded(): bool
    {
        return $this->getPowerCapacityPercent() > 100;
    }

    public function getCurrentBandwidthUsage(): float
    {
        // Sum bandwidth requirements of all active orders on servers in this room
        // Convert Mbps to Gbps (1000 Mbps = 1 Gbps)
        $totalMbps = 0;
        
        foreach ($this->racks as $rack) {
            foreach ($rack->servers as $server) {
                $totalMbps += $server->activeOrders->sum(function($order) {
                    return $order->requirements['bandwidth'] ?? 0;
                });
            }
        }

        return $totalMbps / 1000;
    }

    public function getBandwidthCapacityPercent(): float
    {
        if ($this->bandwidth_gbps <= 0) return 0;
        return ($this->getCurrentBandwidthUsage() / $this->bandwidth_gbps) * 100;
    }

    public function isBandwidthSaturated(): bool
    {
        return $this->getBandwidthCapacityPercent() > 95;
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'level' => $this->level,
            'maxRacks' => $this->max_racks,
            'usedRacks' => $this->racks->count(),
            'power' => [
                'max' => (float) $this->max_power_kw,
                'current' => $this->getCurrentPowerUsage(),
                'percent' => $this->getPowerCapacityPercent(),
            ],
            'cooling' => [
                'max' => (float) $this->max_cooling_kw,
                'current' => $this->getCurrentHeatOutput(),
                'percent' => $this->getCoolingCapacityPercent(),
            ],
            'bandwidth' => [
                'max' => (float) $this->bandwidth_gbps,
                'current' => (float) $this->getCurrentBandwidthUsage(),
                'percent' => $this->getBandwidthCapacityPercent(),
            ],
            'rentPerHour' => (float) $this->rent_per_hour,
            'isUnlocked' => $this->is_unlocked,
            'position' => $this->position ?? ['x' => 0, 'y' => 0],
            'warnings' => [
                'overheating' => $this->isOverheating(),
                'powerOverload' => $this->isPowerOverloaded(),
                'bandwidthSaturated' => $this->isBandwidthSaturated(),
            ],
            'racks' => $this->racks->map(fn($rack) => $rack->toGameState())->toArray(),
        ];
    }
}
