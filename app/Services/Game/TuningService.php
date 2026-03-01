<?php

namespace App\Services\Game;

use App\Models\Server;
use App\Models\GameLog;
use App\Enums\ServerStatus;
use Illuminate\Support\Facades\DB;

class TuningService
{
    /**
     * Calculate stability percentage (0-100)
     * 100% = perfectly stable
     * < 80% = risk of crashes
     * < 50% = system won't boot
     */
    public function calculateStability(Server $server): float
    {
        if (!$server->base_clock_mhz || !$server->base_voltage_v) return 100.0;

        $clockRatio = $server->cpu_clock_mhz / $server->base_clock_mhz;
        $voltageRatio = $server->cpu_voltage_v / $server->base_voltage_v;

        // Stability formula:
        // Overclocking decreases stability.
        // Overvolting increases stability up to a point, but has diminishing returns and high thermal cost.
        // Base stability starts at 100 for stock settings.
        
        // Penalize clock increase
        $clockPenalty = max(0, ($clockRatio - 1.0) * 150); // 10% OC = 15% penalty
        
        // Reward voltage increase (to counteract clock penalty)
        // 1.0 ratio = 0 support, 1.2 ratio = 15 support?
        $voltageSupport = max(0, ($voltageRatio - 1.0) * 120); 

        $stability = 100.0 - $clockPenalty + $voltageSupport;
        
        // RAM Latency impact (if exists in specs)
        $specs = $server->specs ?? [];
        if (isset($specs['tuning_ram_latency'])) {
            // Lower latency (e.g. 0.8 factor) decreases stability
            $ramPenalty = max(0, (1.0 - $specs['tuning_ram_latency']) * 50);
            $stability -= $ramPenalty;
        }

        return max(0, min(100, $stability));
    }

    /**
     * Calculate health degradation rate multiplier
     */
    public function getDegradationMultiplier(Server $server): float
    {
        if (!$server->base_voltage_v) return 1.0;

        $voltageRatio = $server->cpu_voltage_v / $server->base_voltage_v;
        
        // Degradation is highly sensitive to voltage
        // Map 1.0 -> 1.0x, 1.2 -> 5.0x, 1.4 -> 20.0x?
        if ($voltageRatio <= 1.0) return 1.0;
        
        return 1.0 + pow(($voltageRatio - 1.0) * 10, 2); 
    }

    /**
     * Run a stress test on the server
     * Returns results and potential damage
     */
    public function runStressTest(Server $server): array
    {
        $stability = $this->calculateStability($server);
        
        // Random roll for failure
        $roll = rand(0, 100);
        $success = $roll < $stability;
        
        $damageTaken = 0;
        $fault = null;

        if (!$success) {
            // Stability below 80% can cause damage on failure
            if ($stability < 80) {
                $damageTaken = rand(5, 15);
                if ($stability < 50) $damageTaken += rand(10, 30);
                
                $server->health = max(0, $server->health - $damageTaken);
                
                if ($server->health <= 0) {
                    $server->status = ServerStatus::DAMAGED;
                    $fault = 'CORE_MELTDOWN';
                } else if (rand(1, 10) > 7) {
                    $server->status = ServerStatus::DEGRADED;
                    $fault = 'VRM_OVERHEAT_FAULT';
                }
            }
        }

        return [
            'success' => $success,
            'stability' => $stability,
            'damage' => $damageTaken,
            'fault' => $fault,
            'roll' => $roll
        ];
    }
}
