<?php

namespace App\Models;

use App\Enums\RackType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerRack extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'type',
        'name',
        'total_units',
        'used_units',
        'max_power_kw',
        'current_power_kw',
        'current_heat_kw',
        'position',
        'status',
        'temperature',
        'dust_level',
        'purchase_cost',
    ];

    protected $casts = [
        'type' => RackType::class,
        'position' => 'array',
        'current_power_kw' => 'decimal:2',
        'current_heat_kw' => 'decimal:2',
        'temperature' => 'decimal:2',
        'dust_level' => 'decimal:2',
        'purchase_cost' => 'decimal:2',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'room_id');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'rack_id')->orderBy('start_slot');
    }

    /**
     * Get available U-slots (not occupied by any server)
     */
    public function getOccupiedSlots(): array
    {
        $occupied = [];
        foreach ($this->servers as $server) {
            for ($i = 0; $i < $server->size_u; $i++) {
                $occupied[] = $server->start_slot + $i;
            }
        }
        return $occupied;
    }

    public function getFreeSlots(): array
    {
        $occupied = $this->getOccupiedSlots();
        $free = [];
        for ($i = 1; $i <= $this->total_units; $i++) {
            if (!in_array($i, $occupied)) {
                $free[] = $i;
            }
        }
        return $free;
    }

    /**
     * Check if a server of given size can fit starting at slot
     */
    public function canFitServerAt(int $startSlot, int $sizeU): bool
    {
        // Check bounds
        if ($startSlot < 1 || $startSlot + $sizeU - 1 > $this->total_units) {
            return false;
        }

        $occupied = $this->getOccupiedSlots();
        for ($i = $startSlot; $i < $startSlot + $sizeU; $i++) {
            if (in_array($i, $occupied)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Find first available slot that can fit a server of given size
     */
    public function findSlotForServer(int $sizeU): ?int
    {
        for ($i = 1; $i <= $this->total_units - $sizeU + 1; $i++) {
            if ($this->canFitServerAt($i, $sizeU)) {
                return $i;
            }
        }
        return null;
    }

    public function recalculatePowerAndHeat(): void
    {
        $this->current_power_kw = $this->servers->sum('power_draw_kw');
        $this->current_heat_kw = $this->servers->sum('heat_output_kw');
        $this->used_units = $this->servers->sum('size_u');
        $this->save();
    }

    public function getAvailableUnits(): int
    {
        return $this->total_units - $this->used_units;
    }

    public function getAvailablePowerKw(): float
    {
        return $this->max_power_kw - $this->current_power_kw;
    }

    public function isOverheating(): bool
    {
        return $this->temperature > 35;
    }

    public function isCriticalTemperature(): bool
    {
        return $this->temperature > 45;
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'roomId' => $this->room_id,
            'type' => $this->type->value,
            'name' => $this->name,
            'units' => [
                'total' => $this->total_units,
                'used' => $this->used_units,
                'available' => $this->getAvailableUnits(),
            ],
            'power' => [
                'max' => (float) $this->max_power_kw,
                'current' => (float) $this->current_power_kw,
                'available' => $this->getAvailablePowerKw(),
            ],
            'heat' => (float) $this->current_heat_kw,
            'temperature' => (float) $this->temperature,
            'dustLevel' => (float) $this->dust_level,
            'status' => $this->status,
            'position' => $this->position ?? ['slot' => 0],
            'warnings' => [
                'overheating' => $this->isOverheating(),
                'critical' => $this->isCriticalTemperature(),
            ],
            'slots' => $this->buildSlotMap(),
            'servers' => $this->servers->map(fn($server) => $server->toGameState())->toArray(),
        ];
    }

    /**
     * Build a visual representation of the rack slots
     */
    private function buildSlotMap(): array
    {
        $slots = [];
        $occupied = [];

        // Map each server to its slots
        foreach ($this->servers as $server) {
            for ($i = 0; $i < $server->size_u; $i++) {
                $slotNum = $server->start_slot + $i;
                $occupied[$slotNum] = [
                    'serverId' => $server->id,
                    'isStart' => $i === 0,
                    'isEnd' => $i === $server->size_u - 1,
                    'serverStatus' => $server->status,
                ];
            }
        }

        // Build complete slot map (1-indexed from bottom)
        for ($i = 1; $i <= $this->total_units; $i++) {
            $slots[$i] = $occupied[$i] ?? ['empty' => true];
        }

        return $slots;
    }
}
