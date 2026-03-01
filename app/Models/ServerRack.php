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
        'is_colocation_mode',
        'colocation_units',
        'led_color',
        'led_mode',
    ];

    protected $casts = [
        'type' => RackType::class,
        'position' => 'array',
        'current_power_kw' => 'decimal:2',
        'current_heat_kw' => 'decimal:2',
        'temperature' => 'decimal:2',
        'dust_level' => 'decimal:2',
        'thermal_map' => 'array',
        'power_load_map' => 'array',
        'pdu_status' => 'array',
        'purchase_cost' => 'decimal:2',
        'is_colocation_mode' => 'boolean',
        'colocation_units' => 'integer',
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
        $this->current_power_kw = 0;
        $this->current_heat_kw = 0;
        
        // Colocation overhead (Small tenants)
        if ($this->is_colocation_mode) {
            $this->current_power_kw += ($this->colocation_units * 0.15); // 150W per tenant
            $this->current_heat_kw += ($this->colocation_units * 0.10);  // 100W heat per tenant
        }

        $this->used_units = $this->servers->sum('size_u') + ($this->is_colocation_mode ? $this->colocation_units : 0);
        
        // --- V2: Granular Simulation Maps ---
        $thermalMap = array_fill(1, $this->total_units, (float) $this->temperature);
        $powerMap = array_fill(1, $this->total_units, 0.0);
        
        foreach ($this->servers as $server) {
            if (in_array($server->status, [\App\Enums\ServerStatus::ONLINE, \App\Enums\ServerStatus::DEGRADED])) {
                $effPower = (float) $server->getEffectivePowerDraw();
                $effHeat = (float) $server->getEffectiveHeatOutput();
                
                // FEATURE 59: Thermal Runaway (The Death Spiral)
                // If ambient rack temperature is critical (>45C), server fans max out and components leak power,
                // increasing power consumption by 15-25%.
                if ($this->temperature > 45) {
                    $effPower *= (1.0 + (rand(15, 25) / 100.0));
                }
                
                $this->current_power_kw += $effPower;
                $this->current_heat_kw += $effHeat;
                
                $heatPerUnit = $effHeat / max(1, $server->size_u);
                $powerPerUnit = $effPower / max(1, $server->size_u);
                
                for ($i = 0; $i < $server->size_u; $i++) {
                    $slot = $server->start_slot + $i;
                    if ($slot <= $this->total_units) {
                        $thermalMap[$slot] += ($heatPerUnit * 5.0); // Local hot spot effect
                        $powerMap[$slot] = (float) $powerPerUnit;
                    }
                }
            }
        }
        
        $this->thermal_map = $thermalMap;
        $this->power_load_map = $powerMap;
        
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
            'thermalMap' => $this->thermal_map ?? [],
            'powerMap' => $this->power_load_map ?? [],
            'dustLevel' => (float) $this->dust_level,
            'fanHealth' => (float) (($this->pdu_status ?? [])['fan_health'] ?? 100.0), // F64
            'status' => $this->status,
            'position' => $this->position ?? ['slot' => 0],
            'warnings' => [
                'overheating' => $this->isOverheating(),
                'critical' => $this->isCriticalTemperature(),
            ],
            'isColocationMode' => (bool) $this->is_colocation_mode,
            'colocationUnits' => (int) $this->colocation_units,
            'ledColor' => $this->led_color,
            'ledMode' => $this->led_mode,
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
        $coloRemaining = $this->is_colocation_mode ? $this->colocation_units : 0;
        
        for ($i = 1; $i <= $this->total_units; $i++) {
            if (isset($occupied[$i])) {
                $slots[$i] = $occupied[$i];
            } elseif ($coloRemaining > 0) {
                $slots[$i] = [
                    'empty' => false,
                    'isColo' => true,
                    'serverId' => 'colo-' . $i,
                    'serverStatus' => 'online',
                    'modelName' => 'Tenant Area (1U)',
                ];
                $coloRemaining--;
            } else {
                $slots[$i] = ['empty' => true];
            }
        }

        return $slots;
    }
}
