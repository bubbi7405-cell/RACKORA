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
use App\Models\UserComponent;
use App\Models\GameEvent;
use App\Models\GlobalCrisis;
use App\Models\WorldEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Game\StatsService;
use App\Services\Game\MarketingService;
use App\Services\Game\PlayerSkillService;
use App\Services\Game\GlobalCrisisService;
use App\Services\Game\NewsService;
use App\Services\Market\MarketSimulationService;

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
        protected ManagementService $managementService,
        protected EnergyService $energyService,
        protected MarketingService $marketingService,

        protected PlayerSkillService $skillService,
        protected AchievementService $achievementService,
        protected ComplianceService $complianceService,
        protected HardwareDepreciationService $depreciationService,
        protected GlobalCrisisService $globalCrisisService,
        protected ApiService $apiService,
        protected NetworkService $networkService,
        protected MarketSimulationService $marketSimulationService,
        protected FormulaService $formulaService,
        protected ServerRentalService $rentalService,
        protected AuctionService $auctionService,
        protected RankingService $rankingService,
        protected TrafficOrchestratorService $trafficOrchestratorService,
        protected ModernizationService $modernizationService,
        protected HardwareMaintenanceService $maintenanceService,
        protected NewsService $newsService
    ) {}

    public function processTick(): void
    {
        // Global World Events processing (once per minute)
        $this->worldEventService->tick();
        $this->energyService->tickMarket();
        $this->auctionService->tick();

        $this->rentalService->processRentalPayments();
        $this->rentalService->npcRentAvailableServers();

        // NPC Competition Tick
        $globalTick = (int) \App\Models\GameConfig::get('global_tick_count', 0) + 1;
        \App\Models\GameConfig::set('global_tick_count', $globalTick);

        // Weekly Rankings processing (every 60 ticks / 1 hr)
        if ($globalTick % 60 === 0) {
            $this->rankingService->generateRankings();
        }
        $this->marketSimulationService->globalTick($globalTick);
        // Optimized: Dispatch jobs for users into the queue
        // In V2, we only tick users who have been active recently or are "active simulation" users
        $users = User::all(); // TODO: Filter by active session in future V2 step

        foreach ($users as $user) {
            \App\Jobs\ProcessPlayerTick::dispatch($user);
        }
    }

    public function processUserTick(User $user): void
    {
        // Feature 65: Global Crisis Tick
        $this->globalCrisisService->tick($user);

        $economy = $user->economy;
        if (!$economy) return;

        // Check Pause State
        if ($economy->is_paused) {
            return;
        }

        // Get Speed Multiplier (Clamp 1-5 just for safety)
        $speed = max(1, min(5, (int) ($economy->game_speed ?? 1)));

        // Execute Logic Loop
        for ($i = 0; $i < $speed; $i++) {
            try {
                $this->processUserLogic($user);
            } catch (\Exception $e) {
                Log::error("Game Logic Error (User {$user->id}, Speed $i/$speed): " . $e->getMessage());
            }
        }

        // Record Stats Snapshot (Once per batch)
        try {
            $this->statsService->recordSnapshot($user);
        } catch (\Exception $e) {
            Log::error("Stats Error User {$user->id}: " . $e->getMessage());
        }

        // Broadcast State (Once per batch)
        $this->broadcastUserUpdate($user);
    }

    private function processUserLogic(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Reload economy
            $economy = $user->economy;
            if (!$economy) return;

            // Load world modifiers — use regional if player has rooms
            $playerRoom = GameRoom::where('user_id', $user->id)->first();
            $playerRegion = $playerRoom?->region;
            $modifiers = $playerRegion
                ? \App\Models\WorldEvent::getActiveModifiersForRegion($playerRegion)
                : $this->worldEventService->getModifiers();
            
            // Feature 65: Merge with Global Crisis modifiers
            $crisisModifiers = $this->globalCrisisService->getActiveModifiers($user);
            $modifiers = array_merge($modifiers, $crisisModifiers);

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
            $this->modernizationService->tick($user);

            // 4. Server Processing (Provisioning, Health Decay, Maintenance)
            $this->maintenanceService->tick($user);
            $this->maintenanceService->processMaintenance($user);
            $this->maintenanceService->processScheduledMaintenance($user);
            
            // 5. Temperature Simulation (CRITICAL: makes cooling meaningful)
            $this->processTemperature($user);
            $this->processHumidity($user);

            // 6. Customer Satisfaction & Churn
            $this->processCustomerSatisfaction($user, $modifiers);

            // 7. Economy (Real costs)
            $this->processEconomy($user, $modifiers);

            // 8. XP Passive Awards
            $this->processPassiveXp($user);
            $this->processColocation($user);

            // 9. Auto-Trigger Events from Simulation (overheat, power overload)
            $this->autoTriggerEvents($user);

            // 10. Automation (Employees & Scripts)
            $this->employeeService->processAutomation($user);
            $this->processAutoReboot($user);
            $this->processAutoProvisioning($user);
            $this->processCoolingAutomation($user);
            $this->processAutoCleanup($user);
            $this->processOsInstalls($user);
            $this->processSoftwareInstalls($user);
            
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

            // 15. Specialization Passive Tick Effects
            $specService = app(\App\Services\Game\SpecializationService::class);
            $specMods = $specService->getActiveModifiers($user);
            $passives = $specMods['passives'] ?? [];
            
            if (isset($passives['reputation_gain'])) {
                $economy->adjustReputation((float) $passives['reputation_gain']);
            }

            // 16. Component Deliveries
            $this->processDeliveries($user);

            // 17. Marketing Campaigns
            $this->marketingService->tick($user);

            // 18. Experimental Tech Risks (Phase 3)
            $this->processExperimentalRisks($user);

            // 19. Backup Processes
            $this->processBackups($user);

            // 20. Compliance Tick (Scores, Fines, Audits)
            $this->complianceService->tick($user);

            // 21. OS Licensing Fees (Hourly)
            $this->processOsLicenseCosts($user);

            // 20. Achievement Monitoring
            $this->achievementService->checkAchievements($user);

            // 21. Compliance & Audits
            $this->complianceService->tick($user);

            // 22. Virtual API Simulation
            $this->apiService->tick($user);

            // 23. Hardware Depreciation
            $this->depreciationService->tick($user);

            // 24. Network & NOC Tick
            $this->networkService->tick($user);

            // 25. Market Simulation (Dynamic Economy)
            $this->marketSimulationService->tick($user);

            // 26. Live News Engine (Blueprint 4.1)
            $this->newsService->generateNews($user);

            // 27. Board of Directors KPI Monitoring (F195)
            app(BoardOfDirectorsService::class)->tick($user);

            // 28. FEATURE 197: Silent Outage (Invisible Regression)
            $this->processSilentOutages($user);

            // 29. FEATURE 198: Geopolitical Border Shifting
            $this->processGeopoliticalRisks($user);

            // 30. FEATURE 206: Bribery & Moral Decisions
            app(\App\Services\Game\BriberyService::class)->generateBribeOffer($user);

            // FEATURE 53: Smart Traffic Orchestrator
            $this->trafficOrchestratorService->tick($user);

            // 31. FEATURE 202: Darknet Operations Marketplace
            app(\App\Services\Game\DarknetService::class)->tick($user);

            // 32. Dubious Policies (F207)
            $this->processDubiousPolicies($user);

            // 33. FEATURE 65: Crypto Mining Idle Logic
            $this->processCryptoMining($user);

            // 34. FEATURE 117: Security Patch Decay (Oscillating Patch Cycles)
            $this->processSecurityPatchDecay($user);

            // 35. FEATURE 125: Automated Maintenance Routines
            $this->processAutomatedMaintenance($user);

            // 36. FEATURE 69: Corporate Headhunting (Poaching)
            $this->processHeadhunting($user);

            // 37. FEATURE 83: Circuit Breaker Logic
            $this->processCircuitBreakers($user);

            // 38. FEATURE 293: Hardware Aging Stability Curves
            $this->processHardwareAging($user);
        });
    }

    private function broadcastUserUpdate(User $user): void
    {
        // --- BROADCAST REAL-TIME UPDATE VIA WEBSOCKET ---
        try {
            $economy = $user->economy?->fresh();
            if ($economy) {
                $activeEvents = \App\Models\GameEvent::where('user_id', $user->id)
                    ->whereIn('status', ['warning', 'active', 'escalated'])
                    ->count();

                $pendingOrders = \App\Models\CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
                    ->where('status', 'pending')
                    ->count();

                $activeCrisis = \App\Models\GlobalCrisis::where('user_id', $user->id)
                    ->whereNull('resolved_at')
                    ->first();

                // Get Network State
                $networkState = null;
                $privateNetworks = [];
                if ($user->network) {
                    $networkState = app(NetworkService::class)->getNetworkGameState($user->network);
                }
                
                $privateNetworks = app(PrivateNetworkService::class)->getUserNetworks($user);

                broadcast(new \App\Events\EconomyUpdated($user, [
                    'economy' => $economy->toGameState(),
                    'network' => $networkState,
                    'privateNetworks' => $privateNetworks,
                    'activeEvents' => $activeEvents,
                    'activeCrisis' => $activeCrisis,
                    'pendingOrders' => $pendingOrders,
                    'timestamp' => now()->toIso8601String(),
                ]));
            }
        } catch (\Exception $e) {
            // Don't let broadcast failures break the game loop
            Log::debug("Broadcast failed for user {$user->id}: " . $e->getMessage());
        }
    }

    private function processExperimentalRisks(User $user): void
    {
        // A. Fusion PDU Surge Risk
        // 40% reduction is from Helium-3 Fusion PDU
        if ($this->researchService->hasEffect($user, 'power_cost_reduction', 0.40)) {
                if (rand(1, 600) === 1) { // ~Once per 10 hours of gameplay on average
                Log::warning("User {$user->id} Helium-3 Fusion PDU suffered a containment surge!");
                
                // Trigger localized power outages
                $rooms = GameRoom::where('user_id', $user->id)->get();
                foreach ($rooms as $room) {
                    if (rand(1, 2) === 1) {
                        $this->eventService->triggerRoomEvent($room, \App\Enums\EventType::POWER_OUTAGE, 'Magnetic Containment Surge');
                    }
                }
                }
        }
    }

    protected function processDeliveries(User $user)
    {
        \App\Models\UserComponent::where('user_id', $user->id)
            ->where('status', 'delivering')
            ->where('arrival_at', '<=', now())
            ->update([
                'status' => 'inventory',
                'delivery_status' => 'inventory'
            ]);
    }

    private function processAutoReboot(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('auto_reboot') || !$this->researchService->isUnlocked($user, 'auto_reboot')) return;

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

            // Research Bonus: Reputation Gain
            $repMultiplier = $this->researchService->getBonus($user, 'rep_gain_multiplier');
            $baseRepGain = 0.5; // Small boost for new activation
            $user->economy->adjustReputation($baseRepGain * (1 + $repMultiplier));
        }
    }

    private function processAutoProvisioning(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('auto_provisioning') || !$this->researchService->isUnlocked($user, 'auto_provisioning')) return;

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

    private function processCoolingAutomation(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('cooling_automation') || !$this->researchService->isUnlocked($user, 'cooling_automation')) return;

        $rooms = GameRoom::where('user_id', $user->id)->with('racks.servers')->get();
        foreach ($rooms as $room) {
            if (!$room->is_unlocked) continue;
            
            $avgTemp = $room->racks->avg('temperature') ?? 22;
            $currentIntensity = (float) $room->cooling_intensity;

            $newIntensity = $currentIntensity;
            if ($avgTemp > 35) {
                $newIntensity = min(100, $currentIntensity + 10);
            } elseif ($avgTemp < 28) {
                // Aim for optimal range 28-35 to save power
                $newIntensity = max(10, $currentIntensity - 5);
            }

            if (abs($newIntensity - $currentIntensity) > 0.01) {
                $room->cooling_intensity = $newIntensity;
                $room->save();
                // Log::info("Cooling Automation: Adjusted Room {$room->id} intensity to {$newIntensity}%");
            }
        }
    }

    private function processAutoCleanup(User $user): void
    {
        if (!$user->economy->isAutomationEnabled('auto_cleanup') || !$this->researchService->isUnlocked($user, 'auto_cleanup')) return;

        // Cleanup completed or cancelled orders from servers
        $staleOrders = CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['completed', 'cancelled', 'churned'])
            ->whereNotNull('assigned_server_id')
            ->get();

        foreach ($staleOrders as $order) {
            $server = $order->assignedServer;
            if ($server) {
                if ($server->type === \App\Enums\ServerType::VSERVER_NODE) {
                    $server->vservers_used = max(0, $server->vservers_used - 1);
                }
                $server->save();
            }
            
            // Release IPs
            $this->networkService->releaseIPs($order);
            
            $order->assigned_server_id = null;
            $order->save();
            
            // Log::info("Garbage Collector: Decommissioned stale order {$order->id}");
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
                // APPLY FORMULA-BASED SLA PENALTY (Obsidian Architecture)
                $penalty = $this->formulaService->evaluate('formula_sla_penalty', [
                    'hourly_value' => $order->getHourlyValue(),
                    'downtime_ticks' => $order->downtime_ticks
                ], ($order->getHourlyValue() / 60) * 10);
                
                // Scale penalty drastically for higher SLA tiers
                $slaMultipliers = ['standard' => 1.0, 'premium' => 1.5, 'enterprise' => 5.0, 'whale' => 25.0];
                $penalty *= ($slaMultipliers[$order->sla_tier] ?? 1.0);
                
                $user->economy->debit($penalty, "SLA Penalty ({$order->sla_tier}): {$order->customer->company_name}", 'sla_penalty');
            }
            
            $order->save();
        }
    }

    // ─────────────────────────────────────────────────────────

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
            // Degrade Cooling System (mechanical wear)
            // 0.02% per tick -> ~1.2% per hour
            if ($room->cooling_health > 0) {
                $room->cooling_health = max(0, $room->cooling_health - 0.02);
                $room->save();
            }

            // FEATURE 64: Fan Wear & Mechanical Fatigue
            // Rack fans degrade when running at high temperatures (>40°C)
            foreach ($room->racks as $rack) {
                $pdu = $rack->pdu_status ?? [];
                $fanHealth = $pdu['fan_health'] ?? 100.0;

                if ($rack->temperature > 40 && $fanHealth > 0) {
                    // Faster wear at higher temps
                    $wearRate = 0.01 * (($rack->temperature - 40) / 10); // 0.01% per 10°C above 40
                    $fanHealth = max(0, $fanHealth - $wearRate);
                    $pdu['fan_health'] = round($fanHealth, 2);
                    $rack->pdu_status = $pdu;
                    // Don't save yet — will be saved after temperature processing
                }
            }

            // Room-level cooling capacity split evenly across racks
            $rackCount = $room->racks->count();
            if ($rackCount === 0) continue;

            $effectiveCoolingTotal = $room->getEffectiveCooling();

            // POWER DEPENDENCY CHECK
            // If power is out, cooling is dead.
            $hasPowerOutage = \App\Models\GameEvent::where('user_id', $user->id)
                ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                ->where('affected_room_id', $room->id)
                ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
                ->exists();

            if ($hasPowerOutage) {
                $effectiveCoolingTotal = 0;
            }

            $coolingPerRack = $rackCount > 0 ? $effectiveCoolingTotal / $rackCount : 0;

            // Apply research bonus to cooling efficiency
            $coolingBonus = $this->researchService->getBonus($user, 'power_efficiency');
            
            // PLAYER SKILL: Cooling Mastery
            $skillCoolingBonus = $this->skillService->getBonus($user, 'cooling_efficiency');
            
            $effectiveCoolingPerRack = $coolingPerRack * (1 + $coolingBonus + $skillCoolingBonus);

            foreach ($room->racks as $rack) {
                // Update dust accumulation
                $dustRate = $room->type->dustRate();
                $rack->dust_level = min(100, $rack->dust_level + $dustRate);

                // FEATURE 64: Fan health reduces rack-level cooling effectiveness
                $pdu = $rack->pdu_status ?? [];
                $fanHealth = $pdu['fan_health'] ?? 100.0;
                $fanMod = max(0.5, $fanHealth / 100.0); // Min 50% cooling even with dead fans (passive airflow)

                // FEATURE 47: Cooling Zone Management (Positional puzzle)
                // Center of the room gets -5% cooling, edges get +5%
                $maxRacks = max(1, $room->max_racks);
                $slotCount = $maxRacks - 1; // 0-indexed slots
                $centerPoint = $slotCount / 2;
                $slotPos = $rack->position['slot'] ?? 0;
                
                // Distance from center (0 = at exact center, max = at edges)
                $distanceFromCenter = abs($slotPos - $centerPoint);
                $maxDistance = max(1, $centerPoint);
                
                // Scale modifier: -0.05 at center, +0.05 at edges
                $positionModifier = 1.0 - 0.05 + ($distanceFromCenter / $maxDistance) * 0.10;
                
                $rackCooling = $effectiveCoolingPerRack * $fanMod * $positionModifier;

                // Calculate heat from online servers
                $heatOutput = 0;
                foreach ($rack->servers as $server) {
                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                        $serverHeat = $server->heat_output_kw;

                        // REPUTATION MILESTONE: HPC Specialist
                        $repHpc = $user->economy->getSpecializedReputation('hpc');
                        if ($repHpc >= 85) {
                            $serverHeat *= 0.85; // 15% heat reduction (elite optimization)
                        }

                        $heatOutput += $serverHeat;
                    }
                }

                // PLAYER SKILL: Heat Penalty (Overclocking)
                $heatPenalty = $this->skillService->getBonus($user, 'heat_penalty');
                if ($heatPenalty > 0) {
                    $heatOutput *= (1.0 + $heatPenalty);
                }

                // Dust impact: More dust = less cooling efficiency / higher effective heat
                // 100% dust = 10% more effective heat
                $dustMultiplier = 1 + ($rack->dust_level / 1000);
                $effectiveHeat = $heatOutput * $dustMultiplier;

                // Temperature delta per tick (simplified thermodynamics)
            // Positive = heating up, Negative = cooling down
            $heatDelta = $effectiveHeat - $rackCooling; // F64: uses fan-health-adjusted cooling

            // Temperature change rate: ~2°C per kW imbalance per tick
            $tempChange = $heatDelta * 2.0;

            // Feature 33: Cryo Rack Logic
            if ($rack->type === \App\Enums\RackType::CRYO_RACK) {
                $tempChange -= 4.0; // Moderate inherent cooling for Cryo
                
                // Risk: Condensation leads to hardware damage
                if (rand(1, 400) === 1) { // ~Once per 6-7 hours per rack
                    $onlineServers = $rack->servers->where('status', \App\Enums\ServerStatus::ONLINE);
                    if ($onlineServers->count() > 0) {
                        $serverToBreak = $onlineServers->random();
                        $serverToBreak->health -= rand(15, 35);
                        $serverToBreak->status = \App\Enums\ServerStatus::DEGRADED;
                        $serverToBreak->current_fault = 'Condensation Short';
                        $serverToBreak->save();
                        
                        Log::warning("Cryo Rack {$rack->id} suffered condensation! Server {$serverToBreak->id} damaged.");
                        \App\Models\GameLog::log($user, "Cryo-Vault: Condensation detected in rack {$rack->name}! Hardware short-circuit.", 'warning', 'hardware');
                    }
                }
            }

                // Ambient temperature pull (gravitates toward 22°C)
                $ambient = 22.0;
                $ambientPull = ($ambient - $rack->temperature) * 0.1;

                // SPECIALIZATION: Cooling Penalty
                $specService = app(\App\Services\Game\SpecializationService::class);
                $specMods = $specService->getActiveModifiers($user);
                $passives = $specMods['passives'] ?? [];
                
                if (isset($passives['cooling_penalty'])) {
                    $tempChange *= (1.0 + (float) $passives['cooling_penalty']);
                }

                $newTemp = $rack->temperature + $tempChange + $ambientPull;

                // Clamp between ambient and 80°C
                $rack->temperature = max($ambient, min(80.0, round($newTemp, 1)));

                // --- V2 PHYSICS: INTRA-RACK THERMAL BLEEDING ---
                // Initialize map if missing
                if (!$rack->thermal_map) {
                    $rack->thermal_map = array_fill(1, $rack->total_units, (float) $rack->temperature);
                }

                $thermalMap = $rack->thermal_map;
                $nextMap = $thermalMap;
                $totalUnits = $rack->total_units;
                
                // Constants for heat transfer
                $conductionRate = 0.15; // Side-to-side/Material spread
                $convectionRate = 0.35; // Heat rising effect (Stronger)
                $sinkRate = 0.08;       // Internal dissipation to ambient
                
                // Add heat source from servers directly
                foreach ($rack->servers as $server) {
                    if ($server->status->isOperational()) {
                        // Spread server heat across its slots
                        $boost = ($server->heat_output_kw / max(1, $server->size_u)) * 3.0;
                        for ($u = 0; $u < $server->size_u; $u++) {
                             $s = $server->start_slot + $u;
                             if(isset($thermalMap[$s])) {
                                $thermalMap[$s] += $boost;
                             }
                        }
                    }
                }

                for ($i = 1; $i <= $totalUnits; $i++) {
                    $currentSlotTemp = (float) ($thermalMap[$i] ?? $ambient);
                    $adjustment = 0;
                    
                    // A. Convection: Heat from slot below rises to this one
                    if ($i > 1) {
                        $belowTemp = (float) ($thermalMap[$i-1] ?? $ambient);
                        if ($belowTemp > $currentSlotTemp) {
                            $risingHeat = ($belowTemp - $currentSlotTemp) * $convectionRate;
                            $adjustment += $risingHeat;
                            // Cool the bottom slot slightly as heat leaves
                            // (Handled in next iteration effectively or simplified here)
                        }
                    }
                    
                    // B. Conduction: Equalizing with slot above
                    if ($i < $totalUnits) {
                        $aboveTemp = (float) ($thermalMap[$i+1] ?? $ambient);
                        $adjustment += ($aboveTemp - $currentSlotTemp) * $conductionRate;
                    }
                    
                    // C. Ambient Sink: Each slot sheds heat to the rack's cooling
                    $adjustment -= ($currentSlotTemp - $ambient) * $sinkRate;
                    
                    $nextMap[$i] = round($currentSlotTemp + $adjustment, 2);
                }
                
                // Sync base temperature to average of map
                $avgTemp = array_sum($nextMap) / count($nextMap);
                $rack->temperature = round($avgTemp, 1);
                $rack->thermal_map = $nextMap;
            }

            // --- FEATURE 47: COOLING ZONE MANAGEMENT & HEAT TRANSFER ---
            // Racks now exchange heat with neighbors based on a 2-row grid.
            $racks = $room->racks;
            $maxRacks = $room->max_racks;
            $rowLength = (int) ceil($maxRacks / 2);
            $bleedRate = 0.05; // 5% of temperature difference bleeds per tick

            // Airflow Optimization reduces heat bleed between racks
            $bleedReduction = match($room->airflow_type) {
                'hot_aisle' => 0.40, // 40% less bleed
                'cold_aisle_containment' => 0.85, // 85% less bleed
                default => 0.0
            };
            $effectiveBleedRate = $bleedRate * (1.0 - $bleedReduction);

            $tempAdjustments = [];
            foreach ($racks as $rack) {
                $tempAdjustments[$rack->id] = 0;
            }

            foreach ($racks as $rack) {
                if (!isset($rack->position['slot'])) continue;
                $slot = $rack->position['slot'];
                $row = (int) floor($slot / $rowLength);
                $col = $slot % $rowLength;

                // Identify neighbors
                $neighbors = $racks->filter(function($r) use ($row, $col, $rowLength) {
                    if (!isset($r->position['slot'])) return false;
                    $s = $r->position['slot'];
                    $rRow = (int) floor($s / $rowLength);
                    $rCol = $s % $rowLength;

                    // Direct neighbors: Left, Right, or Across Aisle
                    return (abs($rRow - $row) === 1 && $rCol === $col) || // Across
                           ($rRow === $row && abs($rCol - $col) === 1);   // L/R
                });

                foreach ($neighbors as $neighbor) {
                    $diff = $neighbor->temperature - $rack->temperature;
                    // Move heat from hotter to colder
                    $adjustment = $diff * $effectiveBleedRate;
                    $tempAdjustments[$rack->id] += $adjustment;
                }
            }

            // Apply adjustments and final checks
            foreach ($racks as $rack) {
                $rack->temperature = round($rack->temperature + ($tempAdjustments[$rack->id] ?? 0), 1);
                $rack->save();

                // Auto-trigger overheating consequences
                if ($rack->temperature >= 60.0) {
                    $this->emergencyShutdownRack($rack, $user);
                } elseif ($rack->temperature >= 45.0) {
                    foreach ($rack->servers as $server) {
                        if ($server->status === \App\Enums\ServerStatus::ONLINE) {
                            $damage = 3; 
                            if ($rack->temperature >= 55.0) $damage = 6;
                            $server->health = max(0, $server->health - $damage);
                            if ($server->health < 70) {
                                $server->status = \App\Enums\ServerStatus::DEGRADED;
                                $server->current_fault = 'Thermal Throttling';
                            }
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
                // Trip logic: forceful shutdown prevents total loss but causes stress
                $server->status = ServerStatus::OFFLINE;
                $server->health = max(0, $server->health - 15); // Significant stress damage
                $server->current_fault = 'Thermal Trip';
                $server->save();
                
                \Illuminate\Support\Facades\Log::warning("Server {$server->id} thermal tripped (shutdown) in Rack {$rack->id}.");
            }
        }

        Log::warning("Emergency shutdown on rack {$rack->id} due to critical temperature ({$rack->temperature}°C).");

        // Recalculate power/heat
        $rack->recalculatePowerAndHeat();
    }

    // ─────────────────────────────────────────────────────────
    // 5.5 HUMIDITY SIMULATION
    //     Rooms need to be between 40-60%.
    //     <40% = Static Discharge risk
    //     >60% = Corrosion risk
    // ─────────────────────────────────────────────────────────

    private function processHumidity(User $user): void
    {
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers'])
            ->get();

        $eventService = app(\App\Services\Game\GameEventService::class);

        foreach ($rooms as $room) {
            // Drift towards natural ambient humidity based on region
            $weather = \Illuminate\Support\Facades\Cache::get('regional_weather', []);
            $ambientHumidity = (float) ($weather[$room->region]['humidity'] ?? 50.0);
            
            // Basic stabilization: HVAC systems naturally try to pull towards 50%
            // But if ambient is extreme, it tugs it away
            $targetHumidity = ($ambientHumidity + 50.0) / 2;
            
            // Random fluctuations
            $drift = (rand(-15, 15) / 100); // +/- 0.15% per tick
            
            // Move humidity towards target slowly
            if ($room->humidity < $targetHumidity) {
                $room->humidity += 0.05 + $drift;
            } else {
                $room->humidity -= 0.05 + $drift;
            }
            
            $room->humidity = max(20, min(90, $room->humidity));
            $room->save();

            // Check for extreme conditions
            if ($room->humidity < 35.0) {
                // Static risk
                if (rand(1, 1000) === 1) {
                    $eventService->triggerRoomEvent($room, \App\Enums\EventType::STATIC_DISCHARGE, "Extreme dry air caused a massive static discharge in {$room->name}");
                }
            } elseif ($room->humidity > 65.0) {
                // Corrosion risk
                if (rand(1, 1000) === 1) {
                    $eventService->triggerRoomEvent($room, \App\Enums\EventType::CORROSION, "Moisture condensation caused a short-circuit in {$room->name}");
                }
            }
            
            // Continuous minor damage on extremes
            if ($room->humidity < 30.0 || $room->humidity > 70.0) {
                foreach ($room->racks as $rack) {
                    foreach ($rack->servers as $server) {
                        if ($server->status === \App\Enums\ServerStatus::ONLINE) {
                            if (rand(1, 100) === 1) { // 1% chance per server per tick to take 1 damage
                                $server->health = max(0, $server->health - 1);
                                $server->save();
                            }
                        }
                    }
                }
            }
        }
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

        // Cache room latencies to avoid recalculating per customer
        $roomLatencies = []; 
        $userRooms = GameRoom::where('user_id', $user->id)->get();
        foreach ($userRooms as $room) {
            $roomLatencies[$room->id] = $this->calculateDynamicLatency($room, $user);
        }

        foreach ($customers as $customer) {
            $satisfactionChange = 0;

            foreach ($customer->activeOrders as $order) {
                if (!$order->assigned_server_id) continue;

                $server = $order->assignedServer;
                if (!$server) continue;

                if ($server->status === ServerStatus::ONLINE && $server->health > 50) {
                    $satisfactionChange += 0.5; // +0.5 per tick per healthy order
                    
                    // --- FEATURE 205: PROPRIETARY OS BONUS ---
                    if ($server->installed_os_type === 'pony_os_v1') {
                        $retentionBonus = app(ResearchService::class)->getBonus($user, 'customer_retention');
                        $satisfactionChange += (0.1 * (1 + $retentionBonus)); // Extra boost
                    }
                }

                $room = $server->rack->room;

                // REGIONAL MATCHING
                $targetRegion = $customer->preferences['target_region'] ?? null;
                if ($targetRegion) {
                    if ($room->region !== $targetRegion) {
                        $satisfactionChange -= 1.5; // Wrong region penalty
                        if (rand(1, 100) < 5) {
                            Log::info("Customer {$customer->company_name} unhappy: mismatched region (Pref: $targetRegion, Current: {$room->region})");
                        }
                    } else {
                        $satisfactionChange += 0.2; // Optimized region bonus
                    }
                }

                // LATENCY CHECK
                $latency = $roomLatencies[$room->id] ?? 50;
                
                $sensitivity = [
                    'game_server' => 45, // Threashold in ms
                    'vserver' => 70,
                    'dedicated' => 70,
                    'database_hosting' => 60,
                    'web_hosting' => 100,
                    // ML and Storage have no entry -> implicitly 999
                ];

                $threshold = $sensitivity[$order->product_type] ?? 999;
                if ($latency > $threshold) {
                    // Penalty: -1 for every 20ms over threshold
                    $penalty = ceil(($latency - $threshold) / 20);
                    $satisfactionChange -= $penalty;
                    
                    if (rand(1, 100) < 5) { // 5% chance to log tip
                         Log::info("Customer {$customer->company_name} is unhappy with high latency in {$room->region} ($latency ms) for product {$order->product_type}.");
                    }
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

                // Energy Policy: Grid Saver penalty (Latency spikes)
                if (in_array('grid_saver', $user->economy->strategic_policies ?? [])) {
                    $satisfactionChange -= 0.5;
                }
            }

            // If no active orders, apply situational decay
            if ($customer->activeOrders->isEmpty()) {
                // Only decay if customer has had previous orders (they lost service)
                // New customers waiting for their first order should not be penalized
                $hasHistoricalOrders = $customer->orders()
                    ->whereIn('status', ['completed', 'cancelled'])
                    ->exists();
                
                if ($hasHistoricalOrders) {
                    $satisfactionChange -= 0.01; // Very slow decay (was -0.1)
                }
                // else: No decay — customer is simply waiting for first order
            }

            // Apply change
            $diffModifiers = $user->economy->getDifficultyModifiers();
            $change = $satisfactionChange;
            if ($change < 0) {
                // Apply difficulty and world modifier
                $change *= $decayMod;
                $change *= ($diffModifiers['satisfaction_decay_mod'] ?? 1.0);
            }
            
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
                $specService = app(\App\Services\Game\SpecializationService::class);
                $specMods = $specService->getActiveModifiers($user);
                $passives = $specMods['passives'] ?? [];
                
                $specChurnBonus = isset($passives['churn_reduction']) ? abs((float) $passives['churn_reduction']) : 0;
                $specChurnPenalty = isset($passives['churn_penalty']) ? abs((float) $passives['churn_penalty']) : 0;
                
                // --- FEATURE 55: SKILL TREE PERKS ---
                $supportAgents = \App\Models\Employee::where('user_id', $user->id)
                    ->where('type', 'support_agent')
                    ->get();
                
                $supportBonus = 0;
                $hasCrisisManager = false;

                foreach ($supportAgents as $agent) {
                    $perkBonus = 0.10; // Base 10% per agent efficiency unit
                    if ($this->employeeService->hasPerk($agent, 'empathy_chip')) {
                        $perkBonus = 0.12; // 12% per unit (20% boost to base)
                    }
                    $supportBonus += ($agent->efficiency * $perkBonus);

                    if ($this->employeeService->hasPerk($agent, 'crisis_manager')) {
                        $hasCrisisManager = true;
                    }
                }

                // Perk: Crisis Manager (Prevent churn during outages)
                // If the satisfaction drop was mainly due to outage (how to know? active events?)
                if ($hasCrisisManager) {
                    $activeOutage = \App\Models\GameEvent::where('user_id', $user->id)
                        ->whereIn('type', [\App\Enums\EventType::POWER_OUTAGE, \App\Enums\EventType::NETWORK_FAILURE])
                        ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
                        ->exists();

                    if ($activeOutage) {
                        Log::info("Crisis Manager prevented churn during outage for customer {$customer->company_name}.");
                         // Reset to just above churn threshold to buy time
                        $customer->satisfaction = 20;
                        $customer->status = 'unhappy';
                        $customer->save();
                        continue;
                    }
                }
                
                $saveChance = min(0.6, $supportBonus + $specChurnBonus - $specChurnPenalty);

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

            // FEATURE 119: Record uptime tick for sparkline history
            $customer->recordUptimeTick();

            $customer->save();
        }

        // --- FEATURE 120: Organic Growth (Client Referrals) ---
        // Calculate averge satisfaction for active customers
        $activeCustomers = Customer::where('user_id', $user->id)->where('status', 'active');
        if ($activeCustomers->count() > 0) {
            $averageSatisfaction = $activeCustomers->avg('satisfaction') ?? 0;
            if ($averageSatisfaction >= 95) {
                // High satisfaction (>95%) triggers organic new customer leads
                if (rand(1, 100) <= 2) { // 2% chance per tick to get a free organic order
                    try {
                        app(CustomerOrderService::class)->generateNewOrder($user);
                        \App\Models\GameLog::log($user, "🌟 Phenomenal Service! Word-of-mouth generated a free organic customer referral.", 'success', 'market');
                    } catch (\Exception $e) {
                         // Ignore if generated fails for some reason (e.g. max pending orders)
                    }
                }
            }
        }
    }

    private function calculateDynamicLatency(GameRoom $room, User $user): float
    {
        // 1. Base Latency (Region + Tier dependent)
        $base = $room->calculateRegionalLatencyBase();
        $tier = $room->getNetworkTier();
        
        // Tier-based resistance to congestion and DDoS
        // Tier 0 (Standard): 1.0x penalty
        // Tier 1 (Fiber): 0.7x penalty
        // Tier 2 (Backbone): 0.4x penalty
        // Tier 3 (Anycast): 0.1x penalty
        $penaltyResistance = match($tier) {
            0 => 1.0,
            1 => 0.7,
            2 => 0.4,
            3 => 0.1,
            default => 1.0
        };

        $penalty = 0;

        // 2. Congestion Penalty
        $usagePercent = $room->getBandwidthCapacityPercent();
        if ($usagePercent > 100) {
            $penalty += (200 + (($usagePercent - 100) * 10)) * $penaltyResistance;
        } elseif ($usagePercent > 80) {
            $penalty += ($usagePercent - 80) * $penaltyResistance;
        }

        // 3. DDoS Penalty (Network dependency cascade)
        $activeDdosEvents = \App\Models\GameEvent::where('user_id', $user->id)
            ->where('type', \App\Enums\EventType::DDOS_ATTACK)
            ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
            ->with('affectedServer.rack')
            ->get();
            
        foreach ($activeDdosEvents as $event) {
            if ($event->affectedServer && $event->affectedServer->rack && $event->affectedServer->rack->room_id === $room->id) {
                $penalty += 500 * $penaltyResistance; 
                break; 
            }
        }

        // Apply Specializations
        $empService = app(\App\Services\Game\EmployeeService::class);
        $latencyFlat = $empService->getAggregatedBonus($user, 'latency_reduction_flat');
        if ($latencyFlat > 0) {
            $base = max(1, $base - $latencyFlat);
        }

        $totalLatency = round($base + $penalty, 2);

        $jitterReduction = $empService->getAggregatedBonus($user, 'jitter_reduction');
        if ($jitterReduction > 0) {
            $totalLatency *= (1.0 - min(0.9, $jitterReduction));
        }
        
        // Update room model so frontend sees it
        if (abs($room->latency_ms - $totalLatency) > 0.01) {
            $room->latency_ms = $totalLatency;
            $room->save();
        }

        return max(1.0, $totalLatency);
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

        // Specialized Reputation Penalty
        $customerTier = $customer->tier ?? 'standard';
        if ($customerTier === 'bronze' || $customerTier === 'standard') {
            $user->economy->adjustSpecializedReputation('budget', -5.0);
        } else {
            $user->economy->adjustSpecializedReputation('premium', -8.0);
        }
        $user->economy->save();
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
        $incomeMod = $modifiers['contract_payout'] ?? 1.0;
        $coolingEffMod = $modifiers['cooling_efficiency'] ?? 1.0;
        $bandwidthMod = $modifiers['bandwidth_cost'] ?? 1.0;
        
        // Research Bonus: Power Cost Reduction (Global)
        $powerReduction = $this->researchService->getBonus($user, 'power_cost_reduction');
        // Cap reduction at 80% to avoid free power
        $powerMod *= (1.0 - min(0.80, $powerReduction));

        $economy = $user->economy;
        if (!$economy) return;

        // ─── INCOME ───
        $activeOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'active')->get();

        // Convert monthly income to hourly (720h per month)
        $hourlyIncome = ($activeOrders->sum('price_per_month') / 720) * $incomeMod;

        // --- FEATURE 61: HYBRID CLOUD BURSTING ---
        $cloudHourlyExpense = 0;
        foreach ($activeOrders as $order) {
            $isCloud = ($order->metadata ?? [])['is_cloud'] ?? false;
            if ($isCloud) {
                // Cloud operational cost 5x the normal value
                $cloudHourlyExpense += ($order->getHourlyValue() * 5.0);
            }
        }

        // Apply Difficulty Modifier
        $diffModifiers = $economy->getDifficultyModifiers();
        $hourlyIncome *= ($diffModifiers['income_mod'] ?? 1.0);

        // SPECIALIZATION: Income Boost
        $specService = app(\App\Services\Game\SpecializationService::class);
        $specMods = $specService->getActiveModifiers($user);
        if (isset($specMods['passives']['income_boost'])) {
            $hourlyIncome *= (1.0 + (float) $specMods['passives']['income_boost']);
        }

        // PLAYER SKILL: Revenue Bonus
        $revBonus = $this->skillService->getBonus($user, 'revenue_bonus');
        if ($revBonus > 0) {
            $hourlyIncome *= (1.0 + $revBonus);
        }

        // Global Crisis: Reputation Penalty (Specialized V2)
        $repPenalty = $modifiers['reputation_penalty'] ?? 0.0;
        if ($repPenalty > 0) {
            // Specialization V2: Crisis Negotiator (Rep Penalty Reduction)
            $crisisNegotiatorBonus = $this->employeeService->getAggregatedBonus($user, 'crisis_rep_loss_reduction');
            $repPenalty *= (1.0 - min(0.9, $crisisNegotiatorBonus));
            
            $economy->adjustReputation(-$repPenalty);
        }

        // ─── EXPENSES ───
        // Regional Tax Calculation
        $totalTax = 0;
        $regions = \App\Models\GameConfig::get('regions', []);
        foreach ($activeOrders as $order) {
            $regionKey = $order->server?->rack?->room?->region ?? 'us_east';
            $taxRate = $regions[$regionKey]['tax_rate'] ?? 0.05;
            $totalTax += ($order->getHourlyValue() * $taxRate);
        }

        // PLAYER SKILL: Tax Reduction
        $taxReduction = $this->skillService->getBonus($user, 'tax_reduction');
        
        // RESEARCH: Tax Evasion AI (Specialized tech)
        $taxEvasionBonus = $this->researchService->getBonus($user, 'tax_reduction');
        
        $totalTaxDiscount = min(0.9, $taxReduction + $taxEvasionBonus);
        if ($totalTaxDiscount > 0) {
            $totalTax *= (1.0 - $totalTaxDiscount);
        }
        $rooms = GameRoom::where('user_id', $user->id)->with('racks.servers')->get();

        $hourlyExpenses = 0;
        $totalBandwidthUsed = 0;
        $totalPowerCost = 0;
        $totalPowerKw = 0;
        $hardwareLeaseTotal = 0;

        $regionRentCache = [];

        foreach ($rooms as $room) {
            // FEATURE 66: Dynamic Real Estate (Variable Rent)
            // WorldEvents in a region increase or decrease the rent multiplier.
            $regionKey = $room->region ?? 'us_east';
            if (!isset($regionRentCache[$regionKey])) {
                $regionMods = \App\Models\WorldEvent::getActiveModifiersForRegion($regionKey);
                $regionRentCache[$regionKey] = $regionMods['rent_multiplier'] ?? 1.0;
            }
            
            $rentMultiplier = max(0.1, $regionRentCache[$regionKey]); // Minimum 10% rent
            $hourlyExpenses += ($room->rent_per_hour * $rentMultiplier);

            // --- FEATURE 186: DIESEL GENERATOR LOGIC ---
            $activeOutage = \App\Models\GameEvent::where('affected_room_id', $room->id)
                ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
                ->first();

            $isGridDown = (bool)$activeOutage;
            $usingDiesel = false;
            $usingBattery = false;

            if ($isGridDown) {
                // Determine if we have power for this room
                if ($room->has_diesel_backup && $room->diesel_fuel_liters > 0) {
                    $usingDiesel = true;
                    // Fuel consumption: approx 1 liter per 50kW per hour
                    $consumptionRate = max(0.1, ($room->getCurrentPowerUsage() / 50) / 60);
                    $room->diesel_fuel_liters = max(0, $room->diesel_fuel_liters - $consumptionRate);
                    $room->save();

                    // Reputation penalty for pollution during diesel usage
                    $economy->adjustReputation(-0.02); 

                    if ($room->diesel_fuel_liters <= 0) {
                        \App\Models\GameLog::log($user, "BACKUP FAILURE: Diesel generator in {$room->name} ran out of fuel!", 'danger', 'energy');
                    }
                } else {
                    // Check if room has at least one charged battery unit
                    $hasChargedBattery = $room->racks->flatMap(fn($r) => $r->servers)
                        ->where('type', 'battery')
                        ->where('battery_level_kwh', '>', 0)
                        ->isNotEmpty();
                        
                    if ($hasChargedBattery) {
                        $usingBattery = true;
                    }
                }
            }

            $roomItPowerKw = 0;
            
            foreach ($room->racks as $rack) {
                // Colocation income & load
                if ($rack->is_colocation_mode && $rack->colocation_units > 0) {
                    $hourlyIncome += ($rack->colocation_units * 2.50); // $2.50 per unit per hour
                    $roomItPowerKw += ($rack->colocation_units * 0.15); // 0.15 kW per unit overhead
                    $totalBandwidthUsed += ($rack->colocation_units * 50); // 50 Mbps per unit
                }

                foreach ($rack->servers as $server) {
                    // LEASING COST (SERVER LEVEL)
                    if ($server->is_leased) {
                        $hardwareLeaseTotal += $server->lease_cost_per_hour;
                    }

                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED, ServerStatus::PROVISIONING])) {
                        // In case of grid outage without diesel or battery backup, servers are effectively offline for power draw
                        if ($isGridDown && !$usingDiesel && !$usingBattery) continue;

                        $activePolicies = $economy->strategic_policies ?? [];
                        $policyPowerDrawMod = 1.0;
                        if (in_array('performance_mode', $activePolicies)) {
                            $policyPowerDrawMod = 1.25;
                        }

                        $effectivePowerDraw = $server->getEffectivePowerDraw() * $policyPowerDrawMod; 
                        $roomItPowerKw += $effectivePowerDraw;
                        $totalBandwidthUsed += $server->bandwidth_mbps;
                    }
                }
            }

            // --- FEATURE 185: PUE-SCORE OPTIMIZATION ---
            $pue = $room->calculatePue();
            $roomTotalPowerKw = $roomItPowerKw * $pue;

            // --- FEATURE 193: PLANETARY ROTATION (SOLAR SYNC) ---
            $roomSolarProd = $this->energyService->getSolarProduction($user, $room->region);
            $roomNetPowerKw = max(0, $roomTotalPowerKw - $roomSolarProd);

            if (!$usingDiesel) {
                if ($isGridDown) {
                    $roomNetPowerKw = 0;
                }
                $totalPowerKw += $roomNetPowerKw;
            }
        }

        // LEASING COST (INVENTORY LEVEL - components not yet in servers)
        $unlinkedLeasedComponents = UserComponent::where('user_id', $user->id)
            ->where('is_leased', true)
            ->where(function ($q) {
                $q->whereNull('assigned_server_id')
                  ->orWhere('status', 'delivering');
            })
            ->sum('lease_cost_per_hour');
        
        $hardwareLeaseTotal += $unlinkedLeasedComponents;
        $hourlyExpenses += $hardwareLeaseTotal;

        // Unified Grid Usage
        $netPowerKw = $totalPowerKw; 
        
        // --- FEATURE 56: ENERGY FUTURES ---
        $this->energyService->processFutures($user, $netPowerKw);

        // Process Battery Storage (Grid Stabilization)
        $this->energyService->processStorage($user, $netPowerKw, $rooms);

        // Final Power Cost Calculation — per-room regional pricing
        $totalPowerCost = 0;
        foreach ($rooms as $room) {
            $roomRegion = $room->region ?? null;
            
            // Use fixed contract price if available, otherwise regional spot
            if ($economy->energy_contract_type === 'fixed' && $economy->energy_contract_expires_at > now()) {
                $roomSpotPrice = (float) $economy->energy_contract_price;
            } else {
                $roomSpotPrice = $this->energyService->getSpotPrice($roomRegion);
            }
            
            // Calculate this room's share of total power proportionally
            $roomPowerKw = $room->getCurrentPowerUsage();
            if ($totalPowerKw > 0 && $roomPowerKw > 0) {
                $roomShare = $roomPowerKw / max(1, $totalPowerKw);
            } else {
                $roomShare = $rooms->count() > 0 ? 1 / $rooms->count() : 1;
            }
            
            $totalPowerCost += ($netPowerKw * $roomShare) * $roomSpotPrice * $powerMod;
        }
        
        // Fallback: if no rooms, use global
        if ($rooms->count() === 0) {
            $spotPrice = $this->energyService->getSpotPrice();
            $totalPowerCost = $netPowerKw * $spotPrice * $powerMod;
        }

        // --- FEATURE 188: DUBIOUS DATA-MINING ---
        $activePolicies = $economy->strategic_policies ?? [];
        if (in_array('data_mining', $activePolicies)) {
            $customerCount = \App\Models\Customer::where('user_id', $user->id)->count();
            $miningIncome = $customerCount * 0.50; // $0.50 per customer per hour
            $hourlyIncome += $miningIncome;
            $economy->adjustReputation(-0.02); // Continuous minor penalty (per minute-ish tick)
        }

        // --- FEATURE 202: DARKNET OPERATIONS MARKETPLACE ---
        $darknetService = app(\App\Services\Game\DarknetService::class);
        $hourlyIncome += $darknetService->getHourlyProfit($user);

        // ─── IP COSTS ───
        $ipv4Count = $user->network->ipv4_used ?? 0;
        $ipv4CostBase = $ipv4Count * 0.50; // $0.50 per IP per hour
        
        $ipv4Reduction = $this->researchService->getBonus($user, 'ipv4_cost_reduction');
        $ipv4Cost = $ipv4CostBase * (1.0 - $ipv4Reduction);
        $hourlyExpenses += $ipv4Cost;

        // --- FEATURE 54: Heat Recovery System (District Heating) ---
        if ($this->researchService->isUnlocked($user, 'heat_recovery')) {
            $totalWasteHeatOutput = $rooms->sum(fn($room) => max(0, $room->getCurrentPowerUsage() * max(0, $room->calculatePue() - 1.0)));
            if ($totalWasteHeatOutput > 0) {
                // Sell at 10c per kW of waste heat
                $districtHeatingIncome = $totalWasteHeatOutput * 0.10; 
                $hourlyIncome += $districtHeatingIncome;
                
                // Steady green reputation boost
                $economy->adjustSpecializedReputation('green', 0.02 * min(5, $rooms->count()));
            }
        }

        // Apply Research Bonus: Cooling efficiency reduces power costs
        $coolingBonus = $this->researchService->getBonus($user, 'power_efficiency');
        if ($coolingBonus > 0) {
            $totalPowerCost *= (1.0 - $coolingBonus);
        }

        // Apply Research Bonus: Energy Optimizer AI reduces power costs further
        $energyOptimizerBonus = $this->researchService->getBonus($user, 'power_cost_reduction');
        if ($energyOptimizerBonus > 0) {
            $totalPowerCost *= (1.0 - $energyOptimizerBonus);
        }

        // SPECIALIZATION: Power Cost Reduction (DevOps Guru & Others)
        $specService = app(\App\Services\Game\SpecializationService::class);
        $specMods = $specService->getActiveModifiers($user);
        $passives = $specMods['passives'] ?? [];
        
        if (isset($passives['power_cost_reduction'])) {
            $totalPowerCost *= (1.0 - (float) $passives['power_cost_reduction']);
        }

        // Specialization V2: DevOps Guru (Power Draw Reduction)
        $devOpsBonus = $this->employeeService->getAggregatedBonus($user, 'power_draw_reduction');
        $totalPowerCost *= (1.0 - min(0.8, $devOpsBonus));

        // Specialization V2: Tax Whisperer (Tax Reduction)
        $taxWhispererBonus = $this->employeeService->getAggregatedBonus($user, 'tax_reduction_flat');
        $totalTax *= (1.0 - min(0.9, $taxWhispererBonus));

        // REPUTATION MILESTONE: Green Synergy
        $repGreen = $economy->getSpecializedReputation('green');
        if ($repGreen >= 75) {
            $totalTax *= 0.85; // 15% tax break for green companies
        }

        // Specialization V2: PR Liaison (Passive Reputation)
        $prBonus = $this->employeeService->getAggregatedBonus($user, 'passive_reputation_gain');
        if ($prBonus > 0) {
            // FEATURE 90: PR Agency campaigns multiply the base PR bonus
            $marketingService = app(\App\Services\Game\MarketingService::class);
            $prCampaignSpeed = $marketingService->getReputationRecoverySpeed($user);
            $prBonus *= $prCampaignSpeed;

            $economy->adjustReputation($prBonus / 60);
        }

        // FEATURE 90: PR Agency passive reputation recovery (even without PR Liaison employee)
        $marketingService = $marketingService ?? app(\App\Services\Game\MarketingService::class);
        $prRecoverySpeed = $marketingService->getReputationRecoverySpeed($user);
        if ($prRecoverySpeed > 1.0 && $economy->reputation < 100) {
            // Base recovery: 0.01 per tick, multiplied by recovery speed
            $baseRecovery = 0.01 * $prRecoverySpeed;
            $economy->adjustReputation($baseRecovery);
        }

        // PLAYER SKILL: Power Draw efficiency
        $powerSkillBonus = $this->skillService->getBonus($user, 'power_draw');
        if ($powerSkillBonus != 0) {
            $totalPowerCost *= (1.0 + $powerSkillBonus);
        }

        $hourlyExpenses += $totalPowerCost;

    // ─── BACKUP COSTS ───
    $totalBackupCost = 0;
    foreach ($rooms as $room) {
        foreach ($room->racks as $rack) {
            foreach ($rack->servers as $server) {
                if ($server->backup_plan !== \App\Enums\BackupPlan::NONE) {
                    $totalBackupCost += $server->backup_plan->hourlyCost();
                }
            }
        }
    }
    $hourlyExpenses += $totalBackupCost;

    // --- FEATURE 208: INSURANCE PREMIUMS ---
    $insuranceService = app(\App\Services\Game\InsuranceService::class);
    $hourlyExpenses += $insuranceService->processHourlyPremiums($user);

    // --- FEATURE 91: CYBER-INSURANCE PREMIUMS ---
    $cyberInsurance = app(\App\Services\Game\CyberInsuranceService::class);
    $hourlyExpenses += $cyberInsurance->processHourlyPremium($user);

    // --- FEATURE 268: THERMAL POLLUTION CARBON TAXES ---
    $totalCarbonTax = 0;
    $regions = \App\Models\GameConfig::get('regions', []);
    foreach ($rooms as $room) {
        $regionKey = $room->region ?? 'us_east';
        $carbonRate = (float) ($regions[$regionKey]['carbon_tax_per_kw'] ?? 0.0);
        if ($carbonRate <= 0) continue;

        // Tax is based on waste heat: IT Power * (PUE - 1) = overhead power (cooling, lighting, etc.)
        $roomPue = $room->calculatePue();
        $roomItPower = 0;
        foreach ($room->racks as $rack) {
            foreach ($rack->servers as $server) {
                if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                    $roomItPower += $server->getEffectivePowerDraw();
                }
            }
        }
        $wasteHeatKw = $roomItPower * max(0, $roomPue - 1.0);

        // Heat recovery upgrade reduces waste heat by 40%
        $upgrades = $room->upgrades ?? [];
        if (in_array('heat_recovery', $upgrades)) {
            $wasteHeatKw *= 0.6;
        }

        $roomCarbonTax = $wasteHeatKw * $carbonRate;

        // Green Reputation discount: up to 50% at 100 green rep
        $greenRep = $economy->getSpecializedReputation('green');
        $greenDiscount = min(0.50, $greenRep / 200); // 100 rep = 50% discount
        $roomCarbonTax *= (1.0 - $greenDiscount);

        // eco_mode policy gives additional 15% discount
        $activePolicies = $economy->strategic_policies ?? [];
        if (in_array('eco_mode', $activePolicies)) {
            $roomCarbonTax *= 0.85;
        }

        $totalCarbonTax += $roomCarbonTax;
    }
    $hourlyExpenses += $totalCarbonTax;

        // Bandwidth cost (convert Mbps to Gbps, multiply by price)
        $bandwidthGbps = $totalBandwidthUsed / 1000;
        $bandwidthCostPerHour = $bandwidthGbps * ($economy->bandwidth_cost_per_gbps ?? 0) * $bandwidthMod;
        $hourlyExpenses += $bandwidthCostPerHour;

        // Employee Salaries
        $hourlyExpenses += $this->employeeService->getTotalHourlySalary($user);

        // Regional Taxes
        $hourlyExpenses += $totalTax;

        // Cloud Bursting (Emergency Capacity)
        if (isset($cloudHourlyExpense)) {
            $hourlyExpenses += $cloudHourlyExpense;
        }

        // Apply Difficulty Modifier (Expense)
        $hourlyExpenses *= ($diffModifiers['expense_mod'] ?? 1.0);

        // ─── NET INCOME ───
        $netHourly = $hourlyIncome - $hourlyExpenses;
        $incomePerMinute = $netHourly / 60; // Per tick (1 min)

        // Update economy record
        $economy->hourly_income = round($hourlyIncome, 2);
        $economy->hourly_expenses = round($hourlyExpenses, 2);
        $economy->total_power_kw = round($totalPowerKw, 4);
        $economy->total_bandwidth_gbps = round($totalBandwidthUsed / 1000, 4); // Convert Mbps to Gbps

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

        // 1. XP for online servers → 1 XP per online server per tick
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->with(['activeOrders'])
            ->get();

        $onlineCount = $servers->count();

        if ($onlineCount > 0) {
            $diffModifiers = $economy->getDifficultyModifiers();
            $xpToGive = (int) ($onlineCount * ($diffModifiers['xp_mod'] ?? 1.0));
            $economy->addExperience($xpToGive);
        }

        // 2. Specialized Reputation Growth & Tech Debt Calculation
        $totalEfficiencyPenalty = 0;
        $activeServerCount = 0;

        foreach ($servers as $server) {
            // Tech Debt Aggregate
            $totalEfficiencyPenalty += $server->getEfficiencyPenalty();
            $activeServerCount++;

            foreach ($server->activeOrders as $order) {
                // Research Bonus: Reputation Gain
                $repMultiplier = 1 + $this->researchService->getBonus($user, 'rep_gain_multiplier');
                $gain = 0.05 * $repMultiplier;

                switch ($order->product_type) {
                    case 'web_hosting':
                        $economy->adjustSpecializedReputation('budget', $gain);
                        break;
                    case 'dedicated':
                    case 'database_hosting':
                        $economy->adjustSpecializedReputation('premium', $gain);
                        break;
                    case 'gpu_server':
                        $economy->adjustSpecializedReputation('hpc', $gain * 2);
                        break;
                    case 'game_server':
                        $economy->adjustSpecializedReputation('premium', $gain * 1.5);
                        break;
                    case 'ml_training':
                        $economy->adjustSpecializedReputation('hpc', $gain * 3); // Harder to get, more reward
                        break;
                }
            }
        }

        // GREEN Reputation Growth
        $energyPolicy = $economy->getPolicy('energy_strategy', 'standard');
        if ($energyPolicy === 'green') {
            // Passive green rep growth
            $economy->adjustSpecializedReputation('green', 0.10);
        }

        // Feature 32: Tech Debt Score (0-100)
        // 100% means total obsolescence.
        if ($activeServerCount > 0) {
            $avgPenalty = $totalEfficiencyPenalty / $activeServerCount; 
            // Penalty varies 0.0 to 0.5. 
            // Score = avgPenalty * 200 (so 0.50 -> 100 score)
            $techDebtScore = min(100, $avgPenalty * 200);
            
            // Store directly in specialized_reputation JSON for now
            $rep = $economy->specialized_reputation ?? [];
            $rep['tech_debt'] = round($techDebtScore, 1);
            $economy->specialized_reputation = $rep;
            $economy->save();
        }

        // Green Reputation for policies
        $policies = $economy->strategic_policies ?? [];
        if (in_array('eco_mode', $policies)) {
            $economy->adjustSpecializedReputation('green', 0.1);
        }
        if ($economy->energy_contract_type === 'green') {
            $economy->adjustSpecializedReputation('green', 0.2);
        }

        $economy->save();
    }

    /**
     * Process Colocation tenants and tickets
     */
    private function processColocation(User $user): void
    {
        $racks = ServerRack::whereHas('room', fn($q) => $q->where('user_id', $user->id))
            ->where('is_colocation_mode', true)
            ->get();

        foreach ($racks as $rack) {
            $available = $rack->getAvailableUnits();
            $reputation = $user->economy->reputation; // 0-100

            // 1. Tenant Acquisition
            if ($available > 0) {
                // Chance: Rep * 0.1% per tick. At 100 Rep, 10% chance per tick to get a tenant.
                if (rand(1, 1000) <= ($reputation * 1)) {
                    $rack->colocation_units++;
                    \App\Models\GameLog::log($user, "New Colocation tenant arrived in Rack: " . $rack->name, 'info', 'infrastructure');
                }
            }

            // 2. Tenant Churn
            if ($rack->colocation_units > 0) {
                // Base 0.5% churn per tick
                if (rand(1, 1000) <= 5) {
                    $rack->colocation_units--;
                    \App\Models\GameLog::log($user, "Colocation tenant departed from Rack: " . $rack->name, 'info', 'infrastructure');
                }
            }

            // 3. Service Tickets
            if ($rack->colocation_units > 0) {
                // 1% chance per tenant per tick to generate a ticket
                $ticketChance = $rack->colocation_units * 1;
                if (rand(1, 1000) <= $ticketChance) {
                    $this->generateSupportTicket($user, 'colocation', $rack);
                }
            }

            $rack->recalculatePowerAndHeat();
            $rack->save();
        }
    }

    private function generateSupportTicket(User $user, string $type, $target): void
    {
        // Simple ticket generation helper
        $customer = \App\Models\Customer::where('user_id', $user->id)->inRandomOrder()->first();
        
        \App\Models\SupportTicket::create([
            'user_id' => $user->id,
            'customer_id' => $customer?->id,
            'subject' => 'Colo Issue: Network latency reported',
            'priority' => 'low',
            'status' => 'open',
            'description' => "Tenant in rack {$target->name} is reporting intermittent connectivity issues.",
            'complexity' => rand(5, 15),
        ]);
        
        \App\Models\GameLog::log($user, "New support ticket generated (Colocation)", 'warning', 'support');
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
            $policies = $user->economy->strategic_policies ?? [];
            $drawMod = in_array('performance_mode', $policies) ? 1.25 : 1.0;

            foreach ($room->racks as $rack) {
                foreach ($rack->servers as $server) {
                    if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                        $totalPowerUsed += ($server->power_draw_kw * $drawMod);
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
            $effectiveMaxPower = $room->getEffectiveMaxPowerKw();
            if ($totalPowerUsed > $effectiveMaxPower) {
                // Check for Regional Blackout (Rationing)
                $isRationing = \App\Models\GameEvent::where('affected_region', $room->region)
                    ->where('type', \App\Enums\EventType::REGIONAL_BLACKOUT)
                    ->whereIn('status', [\App\Enums\EventStatus::ACTIVE, \App\Enums\EventStatus::ESCALATED])
                    ->exists();

                if ($isRationing) {
                     // Automated Rationing Enforcement: Shut down servers with lowest power_priority
                     // until we are below the limit.
                     $serversToKill = Server::whereHas('rack.room', fn($q) => $q->where('id', $room->id))
                         ->whereIn('status', [ServerStatus::ONLINE, ServerStatus::DEGRADED])
                         ->orderBy('power_priority', 'asc') // Lowest priority first
                         ->get();

                     foreach ($serversToKill as $server) {
                         if ($totalPowerUsed <= $effectiveMaxPower) break;

                         $draw = (float) $server->power_draw_kw * $drawMod;
                         $server->status = ServerStatus::OFFLINE;
                         $server->current_fault = 'Power Rationing Shutdown';
                         $server->save();

                         $totalPowerUsed -= $draw;
                         \App\Models\GameLog::log($user, "RATIONING: Server {$server->nickname} ({$server->model_name}) shutdown due to regional blackout priority.", 'warning', 'infrastructure');
                     }
                }

                // FEATURE 83: Circuit Breaker Logic
                if (!$room->has_circuit_breaker_tripped && rand(1, 100) <= 20) { // 20% chance per minute approx to pop
                    $room->has_circuit_breaker_tripped = true;
                    $room->save();

                    foreach ($room->racks as $rack) {
                        foreach ($rack->servers as $server) {
                            if (in_array($server->status, [ServerStatus::ONLINE, ServerStatus::DEGRADED])) {
                                $server->status = ServerStatus::OFFLINE;
                                $server->current_fault = 'Circuit Breaker Tripped';
                                $server->save();
                            }
                        }
                        $rack->recalculatePowerAndHeat();
                    }

                    \App\Models\GameLog::log($user, "CRITICAL: The circuit breaker in {$room->name} has TRIPPED due to massive power overload! Servers offline.", 'danger', 'infrastructure');
                }

                $existingEvent = \App\Models\GameEvent::where('user_id', $user->id)
                    ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                    ->whereIn('status', ['warning', 'active', 'escalated'])
                    ->exists();

                if (!$existingEvent) {
                    Log::info("Auto-triggering power outage event for room {$room->id} (using {$totalPowerUsed}kW / max {$room->max_power_kw}kW).");
                    $this->eventService->createPowerOutage($user, $room);
                }
            }

            // FEATURE 51: Grid Instability (Weather-based)
            $weatherCache = \Illuminate\Support\Facades\Cache::get('regional_weather', []);
            $stability = (float) ($weatherCache[$room->region]['modifiers']['grid_stability'] ?? 1.0);
            
            if ($stability < 1.0) {
                // 1.5% chance per tick during storms (0.7 stability), 2% during blizzards (0.6 stability)
                if (rand(1, 1000) <= (1.0 - $stability) * 50) {
                    $existingEvent = \App\Models\GameEvent::where('user_id', $user->id)
                        ->where('type', \App\Enums\EventType::POWER_OUTAGE)
                        ->whereIn('status', ['warning', 'active', 'escalated'])
                        ->exists();

                    if (!$existingEvent) {
                        Log::info("Weather-induced grid instability triggering power outage for room {$room->id} (Stability: {$stability}).");
                        $this->eventService->createPowerOutage($user, $room);
                        \App\Models\GameLog::log($user, "WARNING: Severe weather in {$room->region} caused grid instability!", 'warning', 'infrastructure');
                    }
                }
            }
        }
    }
    private function processBackups(User $user): void
    {
        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('backup_plan', '!=', \App\Enums\BackupPlan::NONE)
            ->where('status', \App\Enums\ServerStatus::ONLINE)
            ->get();

        foreach ($servers as $server) {
            $intervalMinutes = match($server->backup_plan) {
                \App\Enums\BackupPlan::HOURLY => 60,
                \App\Enums\BackupPlan::OFFSITE => 120, // slightly slower than hourly, but more robust
                default => 1440,
            };
            
            // If never backed up, or time for next backup
            if (!$server->last_backup_at || $server->last_backup_at->addMinutes($intervalMinutes)->isPast()) {
                $server->last_backup_at = now();
                
                // PLAYER SKILL: Backup Architect
                $reliabilityBonus = $this->skillService->getBonus($user, 'backup_reliability');
                
                // Randomize health slightly (90-100%) to simulate occasional data corruption
                $baseHealth = rand(90, 100);
                $server->backup_health = min(100, $baseHealth + ($reliabilityBonus * 50)); 
                
                // Create Snapshot
                $server->last_backup_data = [
                    'os_type' => $server->installed_os_type,
                    'os_version' => $server->installed_os_version,
                    'os_config' => $server->os_config,
                    'installed_applications' => $server->installed_applications,
                    'security_patch_level' => $server->security_patch_level,
                    'timestamp' => now()->toIso8601String(),
                ];
                
                $server->save();
                
                Log::info("Backup completed for Server {$server->model_name} (User: {$user->id})");
            }
        }
    }
    private function processOsInstalls(User $user): void
    {
        $osService = app(\App\Services\Game\OsService::class);
        $servers = \App\Models\Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
        ->where('os_install_status', 'installing')
        ->get();
            
        foreach ($servers as $server) {
            $osService->processInstallTick($server);
        }
    }

    private function processSoftwareInstalls(User $user): void
    {
        $softwareService = app(\App\Services\Game\SoftwareService::class);
        $servers = \App\Models\Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
        ->where('app_install_status', 'installing')
        ->get();
            
        foreach ($servers as $server) {
            $softwareService->processInstallTick($server);
        }
    }

    private function processOsLicenseCosts(User $user): void
    {
        // Only run on the hour
        if (now()->minute !== 0) return;

        // Prevent double billing for this hour
        $cacheKey = "os_billing_{$user->id}_" . now()->format('YmdH');
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) return;
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);

        $osService = app(\App\Services\Game\OsService::class);
        $defs = $osService->getDefinitions();
        
        $totalCost = 0;
        $details = [];

        // Sum up costs for all ONLINE servers with paid OS owned or rented by user
        // Note: Rented servers usually include OS in rental price, but maybe we charge for license separately?
        // Assuming standard model: Owner pays license. Or user pays if they installed it.
        // Query servers where user is owner OR tenant
        
        $servers = \App\Models\Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })
        ->where('status', \App\Enums\ServerStatus::ONLINE)
        ->whereNotNull('installed_os_type')
        ->get();

        foreach ($servers as $server) {
            $osType = $server->installed_os_type;
            if (!isset($defs[$osType])) continue;
            
            $cost = $defs[$osType]['license_cost'] ?? 0;
            if ($cost > 0) {
                $totalCost += $cost;
                if (!isset($details[$osType])) {
                    $details[$osType] = ['count' => 0, 'cost' => 0];
                }
                $details[$osType]['count']++;
                $details[$osType]['cost'] += $cost;
            }
        }

        if ($totalCost > 0) {
             $user->economy->debit($totalCost, "OS Licensing Fees (Hourly)", 'opex', null);
             
             // Log breakdown if significant
             if ($totalCost > 50) {
                 $msg = "Paid \${$totalCost} for OS licenses.";
                 foreach ($details as $os => $data) {
                     $msg .= " {$os}: {$data['count']} servers (\${$data['cost']}).";
                 }
                 \App\Models\GameLog::log($user, $msg, 'info', 'financial');
             }
        }
    }

    private function processCryptoMining(User $user): void
    {
        // 1. Fetch servers that are ONLINE and have mining toggled ON
        $miningServers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('is_mining', true)
            ->where('status', \App\Enums\ServerStatus::ONLINE)
            ->get();

        if ($miningServers->isEmpty()) return;

        $totalIncome = 0;

        foreach ($miningServers as $server) {
            // Calculates based on effective CPU cores (more cores = more crypto)
            // Base income: $0.20 per effective core per tick (~$12/hour per core)
            $effectiveCores = $server->getEffectiveCpuCores();
            $income = $effectiveCores * 0.20;

            // Log total mined for the server stats
            $server->increment('total_mined_crypto', $income);
            $totalIncome += $income;
        }

        if ($totalIncome > 0) {
            $user->economy->credit($totalIncome, 'Crypto Mining Yield', 'crypto_mining');
        }
    }
    /**
     * FEATURE 197: "The Silent Outage" (Invisible Regression)
     * 
     * A rare, invisible performance bug that doesn't trigger monitoring.
     * Causes slow revenue leakage until a manual deep-scan is performed.
     * Stored in server specs as 'silent_outage' => true.
     */
    private function processSilentOutages(User $user): void
    {
        $economy = $user->economy;
        if ($economy->level < 8) return; // Only affects experienced players

        $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->get();

        foreach ($servers as $server) {
            $specs = $server->specs ?? [];
            
            // Already has a silent outage? Apply the leak.
            if ($specs['silent_outage'] ?? false) {
                // 15% revenue leak on this server's orders
                $leakPerOrder = 0.15;
                $activeOrders = $server->activeOrders()->count();
                $leakedAmount = $activeOrders * $leakPerOrder * 2.0; // ~$2 per order per tick leaked
                
                if ($leakedAmount > 0) {
                    $economy->balance -= $leakedAmount;
                    $economy->save();
                }
                continue;
            }
            
            // 0.1% chance per tick per server to develop a silent outage
            if (rand(1, 1000) === 1) {
                $specs['silent_outage'] = true;
                $specs['silent_outage_started_at'] = now()->toIso8601String();
                $server->specs = $specs;
                $server->save();
                
                // NO log, NO notification – that's the whole point!
                Log::info("Silent outage injected on server {$server->id} for user {$user->id}");
            }
        }
    }

    /**
     * FEATURE 198: Geopolitical Border Shifting
     * 
     * Rare event where a region becomes unstable.
     * Player has limited time to evacuate hardware.
     */
    private function processGeopoliticalRisks(User $user): void
    {
        $economy = $user->economy;
        if ($economy->level < 12) return; // High-level players only
        
        $meta = $economy->metadata ?? [];
        $geoEvent = $meta['geopolitical_event'] ?? null;

        if ($geoEvent) {
            $deadline = \Carbon\Carbon::parse($geoEvent['deadline_at']);
            
            if (now()->gt($deadline)) {
                $affectedRooms = $user->rooms()->where('region', $geoEvent['region'])->get();
                $totalLost = 0;
                
                foreach ($affectedRooms as $room) {
                    $serverCount = Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))
                        ->where('status', '!=', 'offline')
                        ->count();
                    $totalLost += $serverCount;
                    
                    Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))
                        ->update(['status' => ServerStatus::OFFLINE]);
                }
                
                $repPenalty = min(50, $totalLost * 5);
                $economy->adjustReputation(-$repPenalty);
                $economy->save();
                
                \App\Models\GameLog::log($user, "🏳️ GEOPOLITICAL SEIZURE: {$totalLost} servers in {$geoEvent['region']} have been shut down. Evacuate now!", 'danger', 'world');
                
                $meta['geopolitical_event'] = null;
                $meta['geopolitical_event_history'][] = [
                    'region' => $geoEvent['region'],
                    'resolved_at' => now()->toIso8601String(),
                    'servers_lost' => $totalLost,
                ];
                $economy->metadata = $meta;
                $economy->save();
            }
            return;
        }

        if (rand(1, 2000) !== 1) return;

        $regions = ['asia_east', 'sa_east', 'eu_central'];
        $region = $regions[array_rand($regions)];
        
        $hasRooms = $user->rooms()->where('region', $region)->exists();
        if (!$hasRooms) return;

        $regionNames = [
            'asia_east' => 'APAC Corridor',
            'sa_east' => 'South American Zone',
            'eu_central' => 'Central European Hub',
        ];

        $meta['geopolitical_event'] = [
            'region' => $region,
            'region_name' => $regionNames[$region] ?? $region,
            'started_at' => now()->toIso8601String(),
            'deadline_at' => now()->addMinutes(30)->toIso8601String(),
        ];
        $economy->metadata = $meta;
        $economy->save();

        \App\Models\GameEvent::create([
            'user_id' => $user->id,
            'title' => '🏳️ GEOPOLITICAL_INSTABILITY',
            'description' => "Severe instability in " . ($regionNames[$region] ?? $region) . ". Government may seize hardware in 30 minutes.",
            'type' => \App\Enums\EventType::NETWORK_FAILURE,
            'status' => 'active',
            'severity' => 'warning',
            'deadline_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * FEATURE 207: Duplicate / Dubious Policies Risks
     */
    private function processDubiousPolicies(User $user): void
    {
        $economy = $user->fresh()->economy;
        if (!$economy) return;

        $activePolicies = $economy->strategic_policies ?? [];

        if (in_array('data_mining', $activePolicies)) {
            if (rand(1, 500) === 1) {
                $customerCount = \App\Models\Customer::where('user_id', $user->id)->count();
                $fine = max(1000, $customerCount * 500); 
                $repLoss = 15;

                \App\Models\GameEvent::create([
                    'user_id' => $user->id,
                    'title' => '📢 PRIVACY_SCANDAL_EXPOSED',
                    'description' => "Investigative Journalisten haben Ihre betrügerischen Data-Mining-Praktiken aufgedeckt. Bußgeld: \$" . number_format($fine, 2) . ".",
                    'type' => \App\Enums\EventType::DATA_LEAK,
                    'status' => 'active',
                    'severity' => 'critical',
                    'warning_at' => now(),
                    'escalates_at' => now()->addSeconds(300),
                    'deadline_at' => now()->addSeconds(1200),
                ]);

                $economy->debit($fine, "GDPR Verstoß / Data Mining Skandal", 'compliance');
                $economy->adjustReputation(-$repLoss);
                
                \App\Models\GameLog::log($user, "DATENSCHUTZ-SKANDAL: Dubioses Data-Mining aufgeflogen. -\${$fine} und -{$repLoss} Rep!", 'danger', 'compliance');
            }
        }
    }

    /**
     * FEATURE 117: Oscillating Security Patch Cycles
     * Security patch level slowly decays on online servers.
     * Players must periodically re-patch (via OS reinstall or security events).
     * Low patch level increases vulnerability to DDoS, ransomware, and data breaches.
     */
    private function processSecurityPatchDecay(User $user): void
    {
        $tick = $user->economy->current_tick ?? 0;

        // Only process every 10 ticks (~10 seconds) for performance
        if ($tick % 10 !== 0) return;

        $servers = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [ServerStatus::ONLINE, ServerStatus::DEGRADED])
            ->where('security_patch_level', '>', 0)
            ->get();

        foreach ($servers as $server) {
            // Base decay: -0.1 per 10 ticks (slow erosion)
            $decay = 0.1;

            // Servers with darknet ops decay faster
            if (!empty($server->specs['darknet_type'])) {
                $decay *= 2.0;
            }

            // Research bonus: "Hardened OS" reduces decay
            $researchService = app(ResearchService::class);
            if ($researchService->hasResearch($user, 'hardened_os')) {
                $decay *= 0.5;
            }

            $server->security_patch_level = max(0, $server->security_patch_level - $decay);

            // When patch level drops below 30, chance of triggering a security event
            if ($server->security_patch_level < 30 && rand(1, 200) === 1) {
                \App\Models\GameEvent::create([
                    'user_id' => $user->id,
                    'title' => "🔓 VULNERABILITY_DETECTED: {$server->model_name}",
                    'description' => "Unpatched system {$server->model_name} has been targeted by automated exploit scanners. Patch immediately!",
                    'type' => \App\Enums\EventType::DATA_LEAK,
                    'status' => 'active',
                    'severity' => $server->security_patch_level < 15 ? 'critical' : 'high',
                    'server_id' => $server->id,
                    'warning_at' => now(),
                    'escalates_at' => now()->addSeconds(300),
                    'deadline_at' => now()->addSeconds(900),
                ]);

                \App\Models\GameLog::log($user, "🔓 SECURITY_ALERT: Server {$server->model_name} is vulnerable (Patch Level: " . round($server->security_patch_level) . "%). Exploit scanners detected!", 'danger', 'security');
            }

            $server->save();
        }
    }

    /**
     * FEATURE 125: Automated Maintenance Routines
     * Auto-schedules maintenance for servers that drop below a health threshold.
     * Requires: automation research or "auto_maintenance" toggle enabled.
     * Stored in player_economy.metadata['auto_maintenance']
     */
    private function processAutomatedMaintenance(User $user): void
    {
        $tick = $user->economy->current_tick ?? 0;

        // Only check every 30 ticks (~30 seconds)
        if ($tick % 30 !== 0) return;

        $meta = $user->economy->metadata ?? [];
        $autoMaint = $meta['auto_maintenance'] ?? null;

        if (!$autoMaint || !($autoMaint['enabled'] ?? false)) return;

        $healthThreshold = $autoMaint['health_threshold'] ?? 50;
        $maxCostPerTick = $autoMaint['max_cost_per_tick'] ?? 500;

        $servers = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->where('health', '<', $healthThreshold)
            ->whereNull('maintenance_scheduled_at')
            ->orderBy('health', 'asc')
            ->limit(3) // Max 3 servers per cycle
            ->get();

        $totalCost = 0;

        foreach ($servers as $server) {
            $maintenanceCost = $server->purchase_cost * 0.05;

            if ($totalCost + $maintenanceCost > $maxCostPerTick) continue;
            if (!$user->economy->canAfford($maintenanceCost)) continue;

            $user->economy->debit($maintenanceCost, "Auto-Maintenance: {$server->model_name}", 'maintenance', $server);
            
            $server->status = ServerStatus::MAINTENANCE;
            $server->last_maintenance_at = now();
            $server->addMaintenanceLogEntry('maintenance', "Automated maintenance triggered (Health: {$server->health}%)", $maintenanceCost);
            $server->save();

            $totalCost += $maintenanceCost;

            \App\Models\GameLog::log($user, "🔧 AUTO-MAINTENANCE: Server {$server->model_name} entered automated maintenance (Health was {$server->health}%).", 'info', 'infrastructure');
        }
    }

    /**
     * FEATURE 69: Corporate Headhunting (Staff Poaching)
     * Competitors try to poach high-level employees with low loyalty.
     * Player can counter with Golden Handcuffs (F128) or raises.
     */
    private function processHeadhunting(User $user): void
    {
        $tick = $user->economy->current_tick ?? 0;

        // Only check every 60 ticks (~1 minute)
        if ($tick % 60 !== 0) return;

        $employees = \App\Models\Employee::where('user_id', $user->id)
            ->where('level', '>=', 5)
            ->where('loyalty', '<', 40)
            ->get();

        foreach ($employees as $employee) {
            // Skip employees with active retention bonus (F128)
            $retentionUntil = $employee->metadata['retention_until'] ?? null;
            if ($retentionUntil && now()->lt(\Carbon\Carbon::parse($retentionUntil))) {
                continue;
            }

            // Skip employees on sabbatical
            if ($employee->isOnSabbatical()) continue;

            // Poaching probability: higher level + lower loyalty = higher chance
            $poachChance = ($employee->level * 0.5) + ((100 - $employee->loyalty) * 0.1);
            if (rand(1, 1000) > $poachChance) continue;

            // Headhunter offer!
            $offerMultiplier = 1.3 + (rand(0, 50) / 100); // 1.3x - 1.8x current salary
            $offerSalary = round($employee->salary * $offerMultiplier, 2);

            $meta = $employee->metadata ?? [];
            $meta['headhunter_offer'] = [
                'salary' => $offerSalary,
                'deadline' => now()->addMinutes(10)->toIso8601String(),
                'competitor' => $this->generateCompetitorName(),
            ];
            $employee->metadata = $meta;
            $employee->save();

            $competitor = $meta['headhunter_offer']['competitor'];
            \App\Models\GameLog::log($user, "🎯 HEADHUNTER: {$competitor} is trying to poach {$employee->name} (Lvl {$employee->level}) with a \${$offerSalary}/h offer! Counter with a raise or retention bonus.", 'warning', 'hr');
        }

        // Process expired headhunter offers — employee leaves if not countered
        $allEmployees = \App\Models\Employee::where('user_id', $user->id)->get();
        foreach ($allEmployees as $emp) {
            $offer = $emp->metadata['headhunter_offer'] ?? null;
            if (!$offer) continue;

            $deadline = \Carbon\Carbon::parse($offer['deadline']);
            if (now()->lt($deadline)) continue; // Not expired yet

            // Offer expired — employee leaves if loyalty is still low
            if ($emp->loyalty < 35) {
                \App\Models\GameLog::log($user, "💼 POACHED: {$emp->name} has left for {$offer['competitor']}! (Salary offered: \${$offer['salary']}/h)", 'danger', 'hr');
                $emp->delete();
            } else {
                // Employee decided to stay
                $meta = $emp->metadata ?? [];
                unset($meta['headhunter_offer']);
                $emp->metadata = $meta;
                $emp->save();
                \App\Models\GameLog::log($user, "✅ {$emp->name} rejected the headhunter offer and stays loyal.", 'success', 'hr');
            }
        }
    }

    /**
     * Helper: Generate a random competitor company name for poaching events
     */
    private function generateCompetitorName(): string
    {
        $prefixes = ['Nebula', 'Quantum', 'Vertex', 'Cipher', 'Obsidian', 'Zenith', 'Apex', 'Titan', 'Helix', 'Paragon'];
        $suffixes = ['Solutions', 'Cloud', 'Digital', 'Systems', 'Infrastructure', 'Hosting', 'Networks', 'Data'];
        return $prefixes[array_rand($prefixes)] . ' ' . $suffixes[array_rand($suffixes)];
    }

    /**
     * FEATURE 83: Circuit Breaker Logic
     * When total power draw in a room exceeds capacity, circuit breaker trips.
     * Randomly shuts down servers until power is within limits.
     * Player must manually reset via Room Dashboard or wait for auto-recovery.
     */
    private function processCircuitBreakers(User $user): void
    {
        $rooms = \App\Models\GameRoom::where('user_id', $user->id)
            ->with(['racks.servers'])
            ->get();

        foreach ($rooms as $room) {
            $totalPowerDraw = $room->racks->sum('current_power_kw');
            $powerCapacity = $room->power_capacity_kw ?? 100;

            // Trip if power exceeds 110% of capacity
            if ($totalPowerDraw <= $powerCapacity * 1.1) continue;

            // 5% chance per tick to trip when overloaded
            if (rand(1, 20) !== 1) continue;

            $overloadPercent = round(($totalPowerDraw / $powerCapacity - 1) * 100, 1);

            // Shut down random servers until under capacity
            $serversToTrip = $room->racks->flatMap(fn($rack) => $rack->servers)
                ->where('status', ServerStatus::ONLINE)
                ->shuffle()
                ->take(rand(1, 3));

            foreach ($serversToTrip as $server) {
                $server->status = ServerStatus::OFFLINE;
                $server->current_fault = 'Circuit Breaker Trip';
                $server->save();
            }

            // Recalculate rack power
            foreach ($room->racks as $rack) {
                $rack->recalculatePowerAndHeat();
            }

            $count = $serversToTrip->count();
            GameLog::log($user, "⚡ CIRCUIT_BREAKER: Room {$room->name} tripped! Power overload by {$overloadPercent}%. {$count} server(s) emergency shutdown.", 'danger', 'infrastructure');
        }
    }

    /**
     * FEATURE 293: Hardware Aging Stability Curves
     * Older servers (high lifespan usage) have increasing failure rates.
     * Past 80% lifespan: chance of random health drops.
     * Past 100% lifespan: chance of spontaneous hardware faults.
     * Overclocked servers age 2x faster (MTBF reduction).
     */
    private function processHardwareAging(User $user): void
    {
        $tick = $user->economy->current_tick ?? 0;

        // Only check every 20 ticks for performance
        if ($tick % 20 !== 0) return;

        $servers = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [ServerStatus::ONLINE, ServerStatus::DEGRADED])
            ->where('lifespan_seconds', '>', 0)
            ->get();

        foreach ($servers as $server) {
            $lifespanUsage = $server->getLifespanUsage(); // 0-100+

            // No aging effects before 80% lifespan
            if ($lifespanUsage < 80) continue;

            // Overclocked servers: treat lifespan as 2x consumed
            $specs = $server->specs ?? [];
            $isOverclocked = isset($specs['overclocked_until']) && now()->lt(\Carbon\Carbon::parse($specs['overclocked_until']));
            if ($isOverclocked) {
                $lifespanUsage *= 1.5; // Effective aging boost
            }

            // FEATURE 67: Physical Rack Integrity (Weight Load)
            // Overloaded racks increase hardware failure rate (frame warping)
            if ($server->rack && $server->rack->isOverloadedByWeight()) {
                $lifespanUsage *= 1.25; // 25% faster degradation due to structural stress
            }

            // Random health drop (aging corrosion)
            // 80-100%: 0.2% chance per check to lose 1-3 health
            // >100%: 1% chance per check to lose 2-5 health
            if ($lifespanUsage >= 100) {
                if (rand(1, 100) <= 1) {
                    $damage = rand(2, 5);
                    $server->health = max(0, $server->health - $damage);

                    // 10% chance of actual fault when past EOL
                    if (rand(1, 10) === 1) {
                        $faults = ['Capacitor Leak', 'Solder Joint Fatigue', 'Memory Bit Rot', 'PSU Degradation', 'Thermal Paste Dry-Out'];
                        $server->status = ServerStatus::DEGRADED;
                        $server->current_fault = $faults[array_rand($faults)];
                        $server->addMaintenanceLogEntry('error', "AGING FAILURE: {$server->current_fault} (Lifespan: " . round($lifespanUsage) . "%)", 0);

                        \App\Models\GameLog::log($user, "⚠️ HARDWARE_AGING: {$server->model_name} suffering from {$server->current_fault}. Hardware is past EOL (" . round($lifespanUsage) . "% lifespan).", 'warning', 'hardware');
                    }

                    $server->save();
                }
            } elseif ($lifespanUsage >= 80) {
                if (rand(1, 500) <= 1) {
                    $damage = rand(1, 3);
                    $server->health = max(0, $server->health - $damage);
                    $server->save();
                }
            }
        }

        // FEATURE 93: Regional Power Rationing Trigger
        // If regional price is extreme (> $0.40/kWh), there's a 2% chance per tick to trigger rationing
        $price = app(\App\Services\Game\EnergyService::class)->getSpotPrice($room->region);
        if ($price > 0.40) {
            $existingBlackout = \App\Models\GameEvent::where('user_id', $user->id)
                ->where('type', \App\Enums\EventType::REGIONAL_BLACKOUT)
                ->where('affected_region', $room->region)
                ->whereIn('status', ['active', 'escalated'])
                ->exists();

            if (!$existingBlackout && rand(1, 100) <= 2) {
                $this->eventService->createRegionalBlackout($user, $room->region);
            }
        }
    }
}
