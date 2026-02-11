<?php

namespace App\Services\Game;

use App\Enums\ServerStatus;
use App\Models\User;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\GameRoom;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\PlayerEconomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Game\StatsService;
use App\Services\Game\WorldEventService;

class GameLoopService
{
    public function __construct(
        protected CustomerOrderService $orderService,
        protected RackManagementService $rackService,
        protected GameEventService $eventService,
        protected ResearchService $researchService,
        protected EmployeeService $employeeService,
        protected StatsService $statsService,
        protected WorldEventService $worldEventService,
        protected ManagementService $managementService
    ) {}

    public function processTick(): void
    {
        // Global World Events processing (once per minute)
        $this->worldEventService->tick();

        $users = User::all();

        foreach ($users as $user) {
            try {
                $this->processUserTick($user);
            } catch (\Exception $e) {
                Log::error("Game loop error for user {$user->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    private function processUserTick(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Reload economy
            $economy = $user->economy;
            if (!$economy) return;

            // Load world modifiers
            $modifiers = $this->worldEventService->getModifiers();

            // 0. Update Game Time
            $economy->increment('current_tick');
            $economy->refresh();

            // 1. Order Generation / Expiration
            $this->orderService->tick($user);
            $this->processOrderProvisioning($user);

            // 2. Game Events (Incidents)
            $this->eventService->tick($user);

            // 3. Research Progress
            $this->researchService->tick($user);

            // 4. Server Processing (Provisioning, Health Decay)
            $this->processServers($user);
            
            // 4b. Maintenance Mode Recovery
            $this->processMaintenance($user);

            // 5. Temperature Simulation (CRITICAL: makes cooling meaningful)
            $this->processTemperature($user);

            // 6. Customer Satisfaction & Churn
            $this->processCustomerSatisfaction($user, $modifiers);

            // 7. Economy (Real costs)
            $this->processEconomy($user, $modifiers);

            // 8. XP Passive Awards
            $this->processPassiveXp($user);

            // 9. Auto-Trigger Events from Simulation (overheat, power overload)
            $this->autoTriggerEvents($user);

            // 10. Automation (Employees & Scripts)
            $this->employeeService->processAutomation($user);
            $this->processAutoReboot($user);
            $this->processAutoProvisioning($user);
            
            // 11. Statistics Snapshot
            $this->statsService->recordSnapshot($user);
            
            // 12. SLA & Uptime Tracking
            $this->processUptimeTracking($user);

            // 13. Strategic Milestones
            $this->managementService->checkMilestones($economy);

            // 14. Policy Tick Effects (e.g., Green Energy Reputation)
            $energyFocus = $economy->getPolicy('energy_strategy', 'standard');
            if ($energyFocus === 'green') {
                $economy->adjustReputation(0.1); // Small boost per minute
            }
        });
    }

    private function processAutoReboot(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('auto_reboot')) return;

        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', \App\Enums\ServerStatus::OFFLINE)
            ->whereHas('activeOrders')
            ->get();

        foreach ($servers as $server) {
            $server->status = \App\Enums\ServerStatus::ONLINE;
            $server->save();
            $server->rack->recalculatePowerAndHeat();
            Log::info("Automation: Auto-rebooted server {$server->id}");
        }
    }

    private function processOrderProvisioning(User $user): void
    {
        $provisioningOrders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'provisioning')
            ->where('provisioning_completes_at', '<=', now())
            ->get();

        foreach ($provisioningOrders as $order) {
            $order->activate();
            Log::info("Order {$order->id} provisioning complete. Activated.");
        }
    }

    private function processAutoProvisioning(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('auto_provisioning')) return;

        $pendingOrders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending')
            ->get();

        foreach ($pendingOrders as $order) {
            // Find first online server with sufficient capacity
            // Note: This is an expensive query if many servers exist, but for current scale it is fine.
            $requirements = $order->requirements;
            
            // Filter servers that are ONLINE and have enough resources
            $suitableServer = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                ->where('status', \App\Enums\ServerStatus::ONLINE)
                ->where('cpu_cores', '>=', $requirements['cpu'] ?? 0)
                ->where('ram_gb', '>=', $requirements['ram'] ?? 0)
                ->get()
                ->first(function ($server) use ($order) {
                    // Further checks for vserver node vs dedicated
                    if ($server->type === \App\Enums\ServerType::VSERVER_NODE) {
                        return $server->getAvailableVServerSlots() > 0;
                    }
                    return $server->orders()->whereIn('status', ['active', 'provisioning'])->count() === 0;
                });

            if ($suitableServer) {
                try {
                    $this->orderService->assignOrder($user, $order, $suitableServer);
                    Log::info("Automation: Auto-provisioned order {$order->id} to server {$suitableServer->id}");
                } catch (\Exception $e) {
                    // Log but continue
                }
            }
        }
    }

    private function processUptimeTracking(User $user): void
    {
        $activeOrders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->where('status', 'active')
            ->with(['assignedServer', 'customer'])
            ->get();

        foreach ($activeOrders as $order) {
            $order->increment('total_ticks');
            
            $server = $order->assignedServer;
            $isDown = !$server || !in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED]);
            
            if ($isDown) {
                $order->increment('downtime_ticks');
            }
            
            // Reload after increment
            $order->refresh();
            
            // Calculate uptime percentage
            if ($order->total_ticks > 0) {
                $order->uptime_percent = (($order->total_ticks - $order->downtime_ticks) / $order->total_ticks) * 100;
            }
            
            // Check for SLA penalty
            if ($order->uptime_percent < $order->getSlaThreshold()) {
                // Penalty: 10% of hourly value per down tick
                $hourlyValue = $order->getHourlyValue();
                $penalty = ($hourlyValue / 60) * 10;
                
                $user->economy->debit($penalty, "SLA Penalty: {$order->customer->company_name}", 'sla_penalty');
            }
            
            $order->save();
        }
    }

    // ─────────────────────────────────────────────────────────
    // 4. SERVER PROCESSING
    // ─────────────────────────────────────────────────────────

    private function processMaintenance(User $user): void
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
    
    private function processServers(User $user): void
    {
        $servers = Server::whereHas('rack.room', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->get();

        foreach ($servers as $server) {
            // Auto-complete provisioning
            if ($server->status === ServerStatus::PROVISIONING) {
                if ($server->isProvisioningComplete()) {
                    $server->completeProvisioning();
                    $server->rack->recalculatePowerAndHeat();
                    Log::info("Server {$server->id} provisioning complete.");
                }
            }

            // Natural health decay (aging) — 0.05% per tick for online servers
            // Exclude maintenance mode
            if ($server->status === ServerStatus::ONLINE) {
                $decayRate = 0.05; // 0.05% per minute → ~3% per hour
                $server->health = max(0, $server->health - $decayRate);

                // Auto-degrade when health is low
                if ($server->health < 30 && $server->status === ServerStatus::ONLINE) {
                    $server->status = ServerStatus::DEGRADED;
                    
                    // Assign a hidden fault
                    $faults = ['Bad Sector', 'Fan Clogged', 'Loose Cable', 'OS Kernel Panic', 'Power Surge', 'Cache Inconsistency'];
                    $server->current_fault = $faults[array_rand($faults)];
                    $server->is_diagnosed = false;
                    
                    Log::info("Server {$server->id} degraded due to low health ({$server->health}%). Fault: {$server->current_fault}");
                }

                $server->save();
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // 5. TEMPERATURE SIMULATION
    //    Makes cooling a REAL mechanic instead of decoration.
    //    Heat rises when servers produce more heat than cooling can handle.
    // ─────────────────────────────────────────────────────────

    private function processTemperature(User $user): void
    {
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers'])
            ->get();

        foreach ($rooms as $room) {
            // Room-level cooling capacity split evenly across racks
            $rackCount = $room->racks->count();
            if ($rackCount === 0) continue;

            $coolingPerRack = $room->max_cooling_kw / $rackCount;

            // Apply research bonus to cooling efficiency
            $coolingBonus = $this->researchService->getBonus($user, 'power_efficiency');
            $effectiveCoolingPerRack = $coolingPerRack * (1 + $coolingBonus);

            foreach ($room->racks as $rack) {
                // Update dust accumulation
                $dustRate = $room->type->dustRate();
                $rack->dust_level = min(100, $rack->dust_level + $dustRate);

                // Calculate heat from online servers
                $heatOutput = 0;
                foreach ($rack->servers as $server) {
                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                        $heatOutput += $server->heat_output_kw;
                    }
                }

                // Dust impact: More dust = less cooling efficiency / higher effective heat
                // 100% dust = 10% more effective heat
                $dustMultiplier = 1 + ($rack->dust_level / 1000);
                $effectiveHeat = $heatOutput * $dustMultiplier;

                // Temperature delta per tick (simplified thermodynamics)
                // Positive = heating up, Negative = cooling down
                $heatDelta = $effectiveHeat - $effectiveCoolingPerRack;

                // Temperature change rate: ~2°C per kW imbalance per tick
                $tempChange = $heatDelta * 2.0;

                // Ambient temperature pull (gravitates toward 22°C)
                $ambient = 22.0;
                $ambientPull = ($ambient - $rack->temperature) * 0.1;

                $newTemp = $rack->temperature + $tempChange + $ambientPull;

                // Clamp between ambient and 80°C
                $rack->temperature = max($ambient, min(80.0, round($newTemp, 1)));
                $rack->save();

                // Auto-trigger overheating consequences
                if ($rack->temperature >= 55.0) {
                    // CRITICAL: Force shutdown servers to prevent damage
                    $this->emergencyShutdownRack($rack, $user);
                } elseif ($rack->temperature >= 45.0) {
                    // Damage online servers (heat damage)
                    foreach ($rack->servers as $server) {
                        if ($server->status === ServerStatus::ONLINE) {
                            $server->health = max(0, $server->health - 2);
                            $server->save();
                        }
                    }
                }
            }
        }
    }

    private function emergencyShutdownRack(ServerRack $rack, User $user): void
    {
        foreach ($rack->servers as $server) {
            if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                $server->status = ServerStatus::DAMAGED;
                $server->health = max(0, $server->health - 10);
                $server->save();
            }
        }

        Log::warning("Emergency shutdown on rack {$rack->id} due to critical temperature ({$rack->temperature}°C).");

        // Recalculate power/heat
        $rack->recalculatePowerAndHeat();
    }

    // ─────────────────────────────────────────────────────────
    // 6. CUSTOMER SATISFACTION & CHURN
    //    If their server is down/degraded, satisfaction drops.
    //    If satisfaction hits 0, customer churns (loses revenue!).
    // ─────────────────────────────────────────────────────────

    private function processCustomerSatisfaction(User $user, array $modifiers = []): void
    {
        $decayMod = $modifiers['satisfaction_decay'] ?? 1.0;
        
        $supportAgentCount = \App\Models\Employee::where('user_id', $user->id)
            ->where('type', 'support_agent')
            ->sum('efficiency');

        $customers = Customer::where('user_id', $user->id)
            ->whereIn('status', ['active', 'unhappy'])
            ->with(['activeOrders.assignedServer'])
            ->get();

        foreach ($customers as $customer) {
            $satisfactionChange = 0;

            foreach ($customer->activeOrders as $order) {
                if (!$order->assigned_server_id) continue;

                $server = $order->assignedServer;
                if (!$server) continue;

                // Server is healthy → satisfaction recovery
                if ($server->status === ServerStatus::ONLINE && $server->health > 50) {
                    $satisfactionChange += 0.5; // +0.5 per tick per healthy order
                }

                // Server is degraded → mild drop
                if ($server->status === ServerStatus::DEGRADED) {
                    $satisfactionChange -= 2;
                }

                // Server is damaged/offline → heavy drop
                if (in_array($server->status, [ServerStatus::DAMAGED, ServerStatus::OFFLINE])) {
                    $satisfactionChange -= 5;
                }

                // Server is in maintenance → light drop (handled downtime)
                if ($server->status === ServerStatus::MAINTENANCE) {
                    $satisfactionChange -= 1.0;
                }
            }

            // If no active orders (somehow), slow natural decay
            if ($customer->activeOrders->isEmpty()) {
                $satisfactionChange -= 0.1;
            }

            // Apply change
            $change = $satisfactionChange;
            if ($change < 0) $change *= $decayMod; // Only modify decay, not recovery
            
            $newSatisfaction = max(0, min(100, $customer->satisfaction + $change));
            $customer->satisfaction = $newSatisfaction;

            // Status transitions
            if ($newSatisfaction >= 50) {
                $customer->status = 'active';
            } elseif ($newSatisfaction >= 20) {
                $customer->status = 'unhappy';
            } else {
                // CHURN CHECK
                // Apply Support Agent bonus: 10% chance to save per agent (efficiency sum)
                // Cap at 50%
                $saveChance = min(0.5, $supportAgentCount * 0.1);

                if ($saveChance > 0 && (rand(0, 100) / 100) < $saveChance) {
                    // Saved! Bump satisfaction to survive
                    $customer->satisfaction = 20;
                    $customer->status = 'unhappy';
                    Log::info("Support agent saved customer {$customer->company_name} from churn.");
                    $customer->save();
                    continue;
                }

                // CHURN! Customer leaves.
                $this->churnCustomer($customer, $user);
                continue;
            }

            $customer->save();
        }
    }

    private function churnCustomer(Customer $customer, User $user): void
    {
        Log::info("Customer {$customer->company_name} churning (satisfaction: {$customer->satisfaction}).");

        // Cancel all active orders
        foreach ($customer->activeOrders as $order) {
            $order->status = 'cancelled';
            $order->save();

            // Release server capacity
            if ($order->assigned_server_id) {
                $server = Server::find($order->assigned_server_id);
                if ($server && $server->vservers_used > 0) {
                    $server->vservers_used = max(0, $server->vservers_used - 1);
                    $server->save();
                }
            }
        }

        $customer->status = 'churned';
        $customer->churn_at = now();
        $customer->save();

        // Reputation penalty
        $user->economy->adjustReputation(-3);

        Log::info("Customer {$customer->company_name} has churned. Reputation -3.");
    }

    // ─────────────────────────────────────────────────────────
    // 7. ECONOMY — REAL COSTS (not hardcoded!)
    //    Power cost = Σ(server.power_draw_kw) × price_per_kwh
    //    Rent = room.rent_per_hour
    //    Research bonus reduces power costs
    // ─────────────────────────────────────────────────────────

    private function processEconomy(User $user, array $modifiers = []): void
    {
        $powerMod = $modifiers['power_cost'] ?? 1.0;
        
        $economy = $user->economy;
        if (!$economy) return;

        // ─── INCOME ───
        $activeOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'active')->get();

        // Convert monthly income to hourly (720h per month)
        $hourlyIncome = $activeOrders->sum('price_per_month') / 720;

        // ─── EXPENSES ───
        $rooms = GameRoom::where('user_id', $user->id)->with('racks.servers')->get();

        $hourlyExpenses = 0;
        $totalBandwidthUsed = 0;

        foreach ($rooms as $room) {
            // Room rent
            $hourlyExpenses += $room->rent_per_hour;

            // Power costs: Sum actual power draw × price per kWh
            foreach ($room->racks as $rack) {
                foreach ($rack->servers as $server) {
                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED, ServerStatus::PROVISIONING])) {
                        // Base power price
                        $actualPowerPrice = $economy->power_price_per_kwh;
                        
                        // Apply World Modifier
                        $actualPowerPrice *= $powerMod;

                        // Apply Strategic Policy Modifier
                        $energyFocus = $economy->getPolicy('energy_strategy', 'standard');
                        $policyModifiers = \App\Services\Game\ManagementService::DECISIONS['energy_strategy']['options'][$energyFocus]['modifiers'];
                        $actualPowerPrice *= ($policyModifiers['power_cost_modifier'] ?? 1.0);

                        $hourlyExpenses += $server->power_draw_kw * $actualPowerPrice;
                        $totalBandwidthUsed += $server->bandwidth_mbps;
                    }
                }
            }
        }

        // Bandwidth cost (convert Mbps to Gbps, multiply by price)
        $bandwidthGbps = $totalBandwidthUsed / 1000;
        $bandwidthCostPerHour = $bandwidthGbps * ($economy->bandwidth_cost_per_gbps ?? 0);
        $hourlyExpenses += $bandwidthCostPerHour;

        // Employee Salaries
        $hourlyExpenses += $this->employeeService->getTotalHourlySalary($user);

        // Apply Research Bonus: Cooling efficiency reduces power costs
        $coolingBonus = $this->researchService->getBonus($user, 'power_efficiency');
        if ($coolingBonus > 0) {
            // Only reduce the power portion, not rent or bandwidth
            $totalRent = $rooms->sum('rent_per_hour');
            $powerCosts = $hourlyExpenses - $totalRent - $bandwidthCostPerHour;
            $hourlyExpenses = $totalRent + $bandwidthCostPerHour + ($powerCosts * (1 - $coolingBonus));
        }

        // ─── NET INCOME ───
        $netHourly = $hourlyIncome - $hourlyExpenses;
        $incomePerMinute = $netHourly / 60; // Per tick (1 min)

        // Update economy record
        $economy->hourly_income = round($hourlyIncome, 2);
        $economy->hourly_expenses = round($hourlyExpenses, 2);

        // Update balance (allow negative! Player can go bankrupt)
        $economy->balance += $incomePerMinute;

        $economy->save();
    }

    // ─────────────────────────────────────────────────────────
    // 8. PASSIVE XP AWARDS
    //    Player earns XP just for running servers.
    //    This makes the level bar actually move!
    // ─────────────────────────────────────────────────────────

    private function processPassiveXp(User $user): void
    {
        $economy = $user->economy;
        if (!$economy) return;

        // Count online servers → 1 XP per online server per tick
        $onlineCount = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->count();

        if ($onlineCount > 0) {
            $economy->addExperience($onlineCount);
        }
    }

    // ─────────────────────────────────────────────────────────
    // 9. AUTO-TRIGGER EVENTS FROM SIMULATION
    //    Overheat → Overheat Event
    //    Power Overload → Power Outage Event
    //    Links the simulation to the event system.
    // ─────────────────────────────────────────────────────────

    private function autoTriggerEvents(User $user): void
    {
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers'])
            ->get();

        foreach ($rooms as $room) {
            // Check power overload
            $totalPowerUsed = 0;
            foreach ($room->racks as $rack) {
                foreach ($rack->servers as $server) {
                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                        $totalPowerUsed += $server->power_draw_kw;
                    }
                }

                // Check rack temperature → trigger overheat event
                if ($rack->temperature >= 50.0) {
                    // Only trigger if there isn't already an active overheat event for this rack
                    $existingEvent = \App\Models\GameEvent::where('user_id', $user->id)
                        ->where('type', \App\Enums\EventType::OVERHEATING)
                        ->whereIn('status', ['warning', 'active', 'escalated'])
                        ->where('affected_rack_id', $rack->id)
                        ->exists();

                    if (!$existingEvent) {
                        Log::info("Auto-triggering overheat event for rack {$rack->id} at {$rack->temperature}°C.");
                        $this->eventService->createOverheatEvent($user, $rack);
                    }
                }
            }

            // Power overload → trigger power outage event
            if ($totalPowerUsed > $room->max_power_kw) {
                $existingEvent = \App\Models\GameEvent::where('user_id', $user->id)
                    ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                    ->whereIn('status', ['warning', 'active', 'escalated'])
                    ->exists();

                if (!$existingEvent) {
                    Log::info("Auto-triggering power outage event for room {$room->id} (using {$totalPowerUsed}kW / max {$room->max_power_kw}kW).");
                    $this->eventService->createPowerOutage($user, $room);
                }
            }
        }
    }
}
