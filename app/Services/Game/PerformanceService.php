<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\GameRoom;
use App\Models\CustomerOrder;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\Log;

class PerformanceService
{
    public function __construct(
        protected ResearchService $researchService,
        protected PlayerSkillService $skillService,
        protected EnergyService $energyService,
        protected EmployeeService $employeeService
    ) {}

    /**
     * FEATURE 110: Modularized Simulation Engine
     * Handle physical performance (Thermal, Power, Bandwidth)
     */
    public function processEnvironment(User $user): void
    {
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers'])
            ->get();

        foreach ($rooms as $room) {
            $this->processRoomThermal($user, $room);
        }
    }

    private function processRoomThermal(User $user, GameRoom $room): void
    {
        // Degrade Cooling System
        if ($room->cooling_health > 0) {
            $room->cooling_health = max(0, $room->cooling_health - 0.02);
            $room->save();
        }

        $racks = $room->racks;
        if ($racks->isEmpty()) return;

        // Power Check
        $hasPowerOutage = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('affected_room_id', $room->id)
            ->where('type', \App\Enums\EventType::POWER_OUTAGE)
            ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
            ->exists();

        $effectiveCoolingTotal = $hasPowerOutage ? 0 : $room->getEffectiveCooling();
        $coolingPerRack = $effectiveCoolingTotal / $racks->count();

        $coolingBonus = $this->researchService->getBonus($user, 'power_efficiency');
        $skillCoolingBonus = $this->skillService->getBonus($user, 'cooling_efficiency');
        $effectiveCoolingPerRack = $coolingPerRack * (1 + $coolingBonus + $skillCoolingBonus);

        $tempAdjustments = [];

        foreach ($racks as $rack) {
            $this->processRackTherals($user, $rack, $effectiveCoolingPerRack);
            $tempAdjustments[$rack->id] = 0;
        }

        // Intra-room heat bleed (F47)
        $this->processHeatBleed($room, $racks, $tempAdjustments);

        foreach ($racks as $rack) {
            $rack->temperature = round($rack->temperature + ($tempAdjustments[$rack->id] ?? 0), 1);
            $rack->save();

            if ($rack->temperature >= 60.0) {
                $this->emergencyShutdownRack($rack, $user);
            } elseif ($rack->temperature >= 45.0) {
                $this->applyHeatStress($user, $rack);
            }
        }
    }

    private function processRackTherals(User $user, ServerRack $rack, float $effectiveCoolingPerRack): void
    {
        // Fan Wear (F64)
        $pdu = $rack->pdu_status ?? [];
        $fanHealth = $pdu['fan_health'] ?? 100.0;
        if ($rack->temperature > 40 && $fanHealth > 0) {
            $wearRate = 0.01 * (($rack->temperature - 40) / 10);
            $fanHealth = max(0, $fanHealth - $wearRate);
            $pdu['fan_health'] = round($fanHealth, 2);
            $rack->pdu_status = $pdu;
        }

        // Dust (F36)
        $dustRate = $rack->room->type->dustRate();
        $rack->dust_level = min(100, $rack->dust_level + $dustRate);

        $fanMod = max(0.5, $fanHealth / 100.0);
        $rackCooling = $effectiveCoolingPerRack * $fanMod;

        // Calculate Heat
        $heatOutput = 0;
        foreach ($rack->servers as $server) {
            if ($server->status->isOperational()) {
                $serverHeat = $server->heat_output_kw;
                
                // Rep milestones
                if ($user->economy->getSpecializedReputation('hpc') >= 85) {
                    $serverHeat *= 0.85;
                }
                $heatOutput += $serverHeat;
            }
        }

        $heatPenalty = $this->skillService->getBonus($user, 'heat_penalty');
        $effectiveHeat = ($heatOutput * (1.0 + $heatPenalty)) * (1 + ($rack->dust_level / 1000));

        $heatDelta = $effectiveHeat - $rackCooling;
        $tempChange = ($heatDelta * 2.0);

        // Cryo Rack (F33)
        if ($rack->type === \App\Enums\RackType::CRYO_RACK) {
            $tempChange -= 4.0;
            if (rand(1, 400) === 1) $this->triggerCondensation($user, $rack);
        }

        $ambient = 22.0;
        $ambientPull = ($ambient - $rack->temperature) * 0.1;
        
        $newTemp = $rack->temperature + $tempChange + $ambientPull;
        $rack->temperature = max($ambient, min(80.0, round($newTemp, 1)));

        // Intra-Rack Bleeding (Thermal Map)
        $this->updateThermalMap($rack, $ambient);
    }

    private function processHeatBleed(GameRoom $room, $racks, &$tempAdjustments): void
    {
        $maxRacks = $room->max_racks;
        $rowLength = (int) ceil($maxRacks / 2);
        $bleedRate = 0.05;

        $bleedReduction = match($room->airflow_type) {
            'hot_aisle' => 0.40,
            'cold_aisle_containment' => 0.85,
            default => 0.0
        };
        $effectiveBleedRate = $bleedRate * (1.0 - $bleedReduction);

        foreach ($racks as $rack) {
            if (!isset($rack->position['slot'])) continue;
            $slot = $rack->position['slot'];
            $row = (int) floor($slot / $rowLength);
            $col = $slot % $rowLength;

            $neighbors = $racks->filter(function($r) use ($row, $col, $rowLength) {
                if (!isset($r->position['slot'])) return false;
                $s = $r->position['slot'];
                $rRow = (int) floor($s / $rowLength);
                $rCol = $s % $rowLength;
                return (abs($rRow - $row) === 1 && $rCol === $col) || (rRow === $row \u0026\u0026 abs($rCol - $col) === 1);
            });

            foreach ($neighbors as $neighbor) {
                $diff = $neighbor->temperature - $rack->temperature;
                $tempAdjustments[$rack->id] += $diff * $effectiveBleedRate;
            }
        }
    }

    private function updateThermalMap(ServerRack $rack, float $ambient): void
    {
        if (!$rack->thermal_map) {
            $rack->thermal_map = array_fill(1, $rack->total_units, (float) $rack->temperature);
        }

        $thermalMap = $rack->thermal_map;
        $nextMap = $thermalMap;
        $convectionRate = 0.35;
        $conductionRate = 0.15;
        $sinkRate = 0.08;

        foreach ($rack->servers as $server) {
            if ($server->status->isOperational()) {
                $boost = ($server->heat_output_kw / max(1, $server->size_u)) * 3.0;
                for ($u = 0; $u < $server->size_u; $u++) {
                    $s = $server->start_slot + $u;
                    if (isset($thermalMap[$s])) $thermalMap[$s] += $boost;
                }
            }
        }

        for ($i = 1; $i <= $rack->total_units; $i++) {
            $curr = (float)($thermalMap[$i] ?? $ambient);
            $adj = 0;
            if ($i > 1) $adj += ((float)($thermalMap[$i-1] ?? $ambient) - $curr) * $convectionRate;
            if ($i < $rack->total_units) $adj += ((float)($thermalMap[$i+1] ?? $ambient) - $curr) * $conductionRate;
            $adj -= ($curr - $ambient) * $sinkRate;
            $nextMap[$i] = round($curr + $adj, 2);
        }
        
        $rack->temperature = round(array_sum($nextMap) / count($nextMap), 1);
        $rack->thermal_map = $nextMap;
    }

    private function applyHeatStress(User $user, ServerRack $rack): void
    {
        foreach ($rack->servers as $server) {
            if ($server->status === ServerStatus::ONLINE) {
                $damage = ($rack->temperature >= 55.0) ? 6 : 3;
                $server->health = max(0, $server->health - $damage);
                if ($server->health < 70) {
                    $server->status = ServerStatus::DEGRADED;
                    $server->current_fault = 'Thermal Throttling';
                    \App\Events\ServerStatusChanged::dispatch($user, $server);
                }
                $server->save();
            }
        }
    }

    private function triggerCondensation(User $user, ServerRack $rack): void
    {
        $online = $rack->servers->where('status', ServerStatus::ONLINE);
        if ($online->isEmpty()) return;

        $target = $online->random();
        $target->health -= rand(15, 35);
        $target->status = ServerStatus::DEGRADED;
        $target->current_fault = 'Condensation Short';
        $target->save();
        
        Log::warning(\"Cryo Rack {$rack->id} suffered condensation!\");
        \App\Models\GameLog::log($user, \"Cryo-Vault: Condensation in rack {$rack->name}!\", 'warning', 'hardware');
    }

    private function emergencyShutdownRack(ServerRack $rack, User $user): void
    {
        foreach ($rack->servers as $server) {
            if ($server->status->isOperational()) {
                $server->status = ServerStatus::OFFLINE;
                $server->save();
                broadcast(new \App\Events\ServerStatusChanged($user, $server))->toOthers();
            }
        }
        Log::emergency(\"Rack {$rack->id} EXceeded 60C! Emergency shutdown initiated.\");
        \App\Models\GameLog::log($user, \"EMERGENCY: Rack {$rack->name} shutdown due to critical heat (>60°C)!\", 'danger', 'hardware');
    }

    public function calculatePowerMetrics(User $user): array
    {
        $rooms = GameRoom::where('user_id', $user->id)->with('racks.servers')->get();
        $totalItPower = 0;
        $totalBandwidth = 0;
        $results = [];

        foreach ($rooms as $room) {
            $roomItPower = 0;
            foreach ($room->racks as $rack) {
                if ($rack->is_colocation_mode) {
                    $roomItPower += ($rack->colocation_units * 0.15);
                    $totalBandwidth += ($rack->colocation_units * 50);
                }
                foreach ($rack->servers as $server) {
                    if ($server->status->isOperational()) {
                         $roomItPower += $server->getEffectivePowerDraw();
                         $totalBandwidth += $server->bandwidth_mbps;
                    }
                }
            }
            $pue = $room->calculatePue();
            $roomTotalPower = $roomItPower * $pue;
            $solar = $this->energyService->getSolarProduction($user, $room->region);
            
            $results[$room->id] = [
                'it_kw' => $roomItPower,
                'total_kw' => $roomTotalPower,
                'solar_kw' => $solar,
                'net_kw' => max(0, $roomTotalPower - $solar)
            ];
            $totalItPower += $roomItPower;
        }

        return [
            'rooms' => $results,
            'total_it_kw' => $totalItPower,
            'total_bandwidth_mbps' => $totalBandwidth
        ];
    }
}
