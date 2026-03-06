<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Server;
use App\Enums\ServerStatus;
use App\Models\GameLog;
use Illuminate\Support\Facades\Log;

class HardwareMaintenanceService
{
    public function __construct(
        protected EmployeeService $employeeService,
        protected ResearchService $researchService
    ) {}

    /**
     * Process health decay and wear for all servers of a user.
     */
    public function tick(User $user): void
    {
        $servers = $user->servers()->whereNotIn('status', [
            ServerStatus::EOL,
            ServerStatus::PROVISIONING
        ])->get();

        foreach ($servers as $server) {
            $this->processServerDecay($user, $server);
        }

        // FEATURE 98: Component-based failure logic (MTBF)
        $this->processComponentDecay($user);
    }

    /**
     * Calculate and apply health decay for a single server.
     */
    protected function processServerDecay(User $user, Server $server): void
    {
        // 0. Increment Runtime if operational
        if ($server->status->isOperational()) {
            $server->increment('total_runtime_seconds', 60); // Assuming 60s per tick or scaling appropriately
        }

        // 1. Base Decay based on load
        // usage 0% -> 0.02, usage 100% -> 0.1
        $loadFactor = $server->vserver_capacity > 0 ? ($server->vservers_used / $server->vserver_capacity) : 0.5;
        $decayRate = 0.02 * (1.0 + ($loadFactor * 4)); 

        // FEATURE 255: Tuning-based Degradation
        if ($server->base_voltage_v > 0 && $server->base_clock_mhz > 0) {
            $vRatio = $server->cpu_voltage_v / $server->base_voltage_v;
            $fRatio = $server->cpu_clock_mhz / $server->base_clock_mhz;
            
            // Electromigration wear: V^2 * f * T (temp handled later)
            // Excessive voltage is extremely damaging (cube law for wear simulation)
            $tuningWear = ($vRatio * $vRatio * $vRatio) * $fRatio;
            $decayRate *= $tuningWear;

            // Warn player if voltage is dangerously high
            if ($vRatio > 1.2 && rand(1, 100) === 1) {
                GameLog::log($user, "⚠️ CRITICAL WEAR: Server '{$server->model_name}' is operating at dangerously high voltage!", 'warning', 'hardware');
            }
        }
        // 2. Research Bonus: Lifespan
        $lifespanBonus = $this->researchService->getBonus($user, 'lifespan_bonus');
        if ($lifespanBonus > 0) {
            $decayRate *= (1.0 - min(0.9, $lifespanBonus));
        }

        // 3. Employee Bonuses (Specializations, Perks, Synergies)
        $allBonuses = $this->employeeService->getAllActiveBonuses($user);
        $wearReduction = $allBonuses['wear_reduction'] ?? 0;
        if ($wearReduction > 0) {
            $decayRate *= (1.0 - min(0.9, $wearReduction));
        }

        // 4. Usage Multiplier (Older servers degrade faster)
        $usagePercent = $server->aging->wearPercentage ?? 0;
        $ageMultiplier = 1.0;
        if ($usagePercent > 100) {
            $ageMultiplier = 5.0 + (($usagePercent - 100) / 10);
        } elseif ($usagePercent > 80) {
            $ageMultiplier = 2.0 + (($usagePercent - 80) / 10); 
        } elseif ($usagePercent > 50) {
            $ageMultiplier = 1.0 + (($usagePercent - 50) / 100);
        }

        $decayRate *= $ageMultiplier;

        // 5. Heat Multiplier (if rack is hot)
        if ($server->rack && $server->rack->temperature > 35) {
            $decayRate *= 1.5;
        }

        // 6. Apply Decay
        $server->health = max(0, $server->health - $decayRate);

        // 7. Auto-degrade check (Random faults when health < 30)
        $this->checkDegradation($user, $server);

        // 8. Critical Failure (EOL)
        if ($server->health <= 0 || $usagePercent > 120) {
            $this->handleEndOfLife($user, $server);
        }

        // 9. Experimental Hardware Risks
        $this->processExperimentalRisks($user, $server);
        
        // 10. FEATURE 254: Tuning Fire Risk (F31)
        $this->checkTuningFireRisk($user, $server);

        $server->save();
    }

    /**
     * Process servers currently in maintenance mode.
     */
    public function processMaintenance(User $user): void
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::MAINTENANCE)
            ->get();

        foreach ($servers as $server) {
            // Gradually restore health — 2.0% per tick (full restore in ~50 ticks)
            $server->health = min(100, $server->health + 2.0);

            // Once fully healthy, move to OFFLINE status (ready to re-boot)
            if ($server->health >= 100) {
                $server->status = ServerStatus::OFFLINE;
                Log::info("Server {$server->id} maintenance complete. Now offline.");
            }

            $server->save();
        }
    }

    /**
     * Start maintenance windows that were scheduled.
     */
    public function processScheduledMaintenance(User $user): void
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('maintenance_scheduled_at')
            ->where('maintenance_scheduled_at', '<=', now())
            ->get();

        foreach ($servers as $server) {
            $server->status = ServerStatus::MAINTENANCE;
            $server->maintenance_scheduled_at = null;
            $server->last_maintenance_at = now();
            $server->save();

            GameLog::log($user, "Maintenance window started for server {$server->model_name}.", 'info', 'hardware');
        }
    }

    /**
     * Handle transition to EOL state.
     */
    public function handleEndOfLife(User $user, Server $server): void
    {
        // Don't trigger twice
        if ($server->status === ServerStatus::EOL) return;

        $server->status = ServerStatus::EOL;
        $server->health = 0;
        $server->current_fault = 'Irreparable Hardware Failure (End-of-Life)';
        
        // Terminate all orders attached to this server
        foreach ($server->activeOrders as $order) {
            $order->status = 'cancelled';
            $order->save();
        }
        
        // Reputation Penalty
        $user->economy->adjustReputation(-15); 
        
        GameLog::log($user, "CATASTROPHIC FAILURE: Server {$server->model_name} in Rack {$server->rack->name} has reached critical failure point and is permanently broken (EOL). All hosted services lost!", 'danger', 'hardware');
    }

    /**
     * Check if server should enter DEGRADED state due to low health.
     */
    protected function checkDegradation(User $user, Server $server): void
    {
        if ($server->health < 30 && $server->status === ServerStatus::ONLINE) {
            $server->status = ServerStatus::DEGRADED;
            
            $faults = ['Bad Sector', 'Fan Clogged', 'Loose Cable', 'OS Kernel Panic', 'Power Surge', 'Cache Inconsistency', 'Aging Capacitor'];
            $server->current_fault = $faults[array_rand($faults)];
            $server->is_diagnosed = false;
            
            Log::info("Server {$server->id} degraded (Health: {$server->health}%). Fault: {$server->current_fault}");
            GameLog::log($user, "Hardware Alert: Server in rack {$server->rack->name} has entered Degraded mode. Fault: {$server->current_fault}", 'warning', 'hardware');
        }

        // Auto-Recovery Research
        if ($server->status === ServerStatus::DEGRADED) {
            $autoRecoveryChance = $this->researchService->getBonus($user, 'auto_recovery_chance');
            if ($autoRecoveryChance > 0 && (rand(1, 1000) / 1000) <= $autoRecoveryChance) {
                $server->status = ServerStatus::ONLINE;
                $server->health = max($server->health, 50); 
                $server->current_fault = null;
                $server->is_diagnosed = false;
                GameLog::log($user, "Auto-Recovery: Systems on server in rack {$server->rack->name} have self-healed.", 'success', 'hardware');
            }
        }
    }

    /**
     * Process risks for experimental hardware tiers.
     */
    protected function processExperimentalRisks(User $user, Server $server): void
    {
        $serverSpecs = $server->specs ?? [];
        if (empty($serverSpecs['isExperimental'])) {
            return;
        }

        $riskType = $serverSpecs['riskType'] ?? 'unstable';
        
        // Instability (Quantum Drift)
        if ($riskType === 'instability' && rand(1, 100) <= 2) { 
            $drop = rand(5, 12);
            $server->health = max(0, $server->health - $drop);
            GameLog::log($user, "QM-1 Cluster: Quantum Drift detected on server in rack {$server->rack->name}.", 'warning', 'hardware');
        }

        // Meltdown
        if ($riskType === 'meltdown' && rand(1, 150) === 1) { 
            $server->health = max(0, $server->health - rand(20, 40));
            $server->status = ServerStatus::DEGRADED;
            $server->current_fault = 'Thermal Melt';
            GameLog::log($user, "X-TRME Overclock: Critical thermal containment failure on rack {$server->rack->name}!", 'critical', 'hardware');
        }
    }

    /**
     * FEATURE 254: Overvolting Fire Risk
     */
    protected function checkTuningFireRisk(User $user, Server $server): void
    {
        if ($server->status !== ServerStatus::ONLINE && $server->status !== ServerStatus::DEGRADED) {
            return;
        }

        if ($server->base_voltage_v > 0) {
            $vRatio = $server->cpu_voltage_v / $server->base_voltage_v;
            
            // Fire risk applies if overvolted beyond 1.15x
            if ($vRatio > 1.15) {
                // Risk scales exponentially: 1.2x = 0.5% chance, 1.4x = ~5% chance per tick
                $fireChance = pow(($vRatio - 1.15) * 10, 2) * 5; 

                // Heat adds to the risk
                if ($server->rack && $server->rack->temperature > 40) {
                    $fireChance *= 2;
                }

                if (rand(1, 1000) <= $fireChance) {
                    $server->status = ServerStatus::OFFLINE;
                    $server->health = 0;
                    $server->current_fault = 'CATASTROPHIC_FIRE';
                    
                    // Kill all active orders instantly
                    foreach ($server->activeOrders as $order) {
                        $order->status = 'cancelled';
                        $order->save();
                    }

                    GameLog::log($user, "🔥 FIRE ALERT (F31): Server '{$server->model_name}' (Rack {$server->rack->name}) caught fire due to excessive overvolting! Hardware destroyed.", 'danger', 'security');
                    
                    // Emit game event for global UI
                    $eventService = app(\App\Services\Game\GameEventService::class);
                    $eventService->triggerRoomEvent($server->rack->room, \App\Enums\EventType::NETWORK_FAILURE, "Fire isolated in {$server->model_name}");
                }
            }
        }
    }

    /**
     * FEATURE 98: Component-based failure logic (MTBF)
     * Decreases component health and triggers failures based on MTBF.
     */
    protected function processComponentDecay(User $user): void
    {
        $components = \App\Models\UserComponent::where('user_id', $user->id)
            ->whereIn('status', ['installed', 'active'])
            ->whereNotNull('assigned_server_id')
            ->get();

        foreach ($components as $component) {
            $config = $component->getConfig();
            if (!$config) continue;

            $mtbfHours = $config['mtbf_hours'] ?? 50000;
            
            // Overclocking malus (Factor of 3x faster decay if overclocked)
            $server = $component->server;
            $overclockMod = 1.0;
            if ($server && $server->cpu_clock_mhz > $server->base_clock_mhz) {
                $overclockMod = 3.0;
            }

            // Temperature Malus
            $room = $server?->rack?->room;
            $tempMod = 1.0;
            if ($room && $room->temperature > 35) {
                // Decay doubles for every 10 degrees above 35
                $tempMod = pow(2, ($room->temperature - 35) / 10);
            }

            // Decay calculation: (ticks per hour / mtbf) * 100
            // Assuming 1 tick ≈ 1 minute.
            $baseDecay = (1 / $mtbfHours) * (60 / 60) * 100; // Simplified for 1-minute ticks
            $actualDecay = $baseDecay * $overclockMod * $tempMod;
            
            // Random jitter/variance (±10%)
            $actualDecay *= (1.0 + (rand(-10, 10) / 100));

            $component->health = max(0, $component->health - $actualDecay);
            
            // Failure Trigger: Chance increases as health drops below 50%
            $failureChance = 0;
            if ($component->health < 50) {
                 // Exponential increase in failure chance as health approaches 0
                 $failureChance = pow((50 - $component->health) / 50, 2) * 2; // Up to 2% chance per tick at 0 health
            }

            if (rand(1, 10000) <= ($failureChance * 100)) {
                $this->triggerComponentFailure($user, $component);
            }

            if ($component->isDirty()) {
                $component->save();
            }
        }
    }

    /**
     * Trigger a hardware failure based on component.
     */
    protected function triggerComponentFailure(User $user, \App\Models\UserComponent $component): void
    {
        $server = $component->server;
        if (!$server) return;

        // If it's the motherboard, server is dead instantly
        if ($component->component_type === 'motherboard') {
            $server->status = ServerStatus::OFFLINE;
            $server->current_fault = 'MOTHERBOARD_FAILURE';
            $server->health = 20; // Massive drop
        } else {
            // Other parts degrade health and potentially degrade status
            $server->health = max(5, $server->health - 15);
            $server->status = ServerStatus::DEGRADED;
            $server->current_fault = strtoupper($component->component_type) . "_FAILURE_" . strtoupper($component->component_key);
        }

        $server->is_diagnosed = false;
        $server->save();

        GameLog::log($user, "HARDWARE FAILURE: A '{$component->getConfig()['name']}' failed in server '{$server->model_name}'!", 'critical', 'hardware');
        
        // Chance to mark component as broken
        if (rand(1, 10) <= 3 || $component->health <= 0) {
            $component->status = 'broken';
            GameLog::log($user, "The component '{$component->getConfig()['name']}' is permanently broken and must be replaced.", 'danger', 'hardware');
        }
    }
}
