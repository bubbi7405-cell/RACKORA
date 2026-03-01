<?php

namespace App\Services\Game;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\ServerStatus;
use App\Models\GameEvent;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\User;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameEventService
{
    public function __construct(
        protected PlayerSkillService $skillService,
        protected AchievementService $achievementService,
        protected MarketService $marketService,
        protected EmployeeService $employeeService,
        protected ResearchService $researchService
    ) {}

    /**
     * Process game events for a user (called every tick)
     */
    public function tick(User $user): void
    {
        // 0. Tick Used Market
        $this->marketService->tickUsedMarket();

        // 1. Process active events (check timestamps, auto-fail, escalate)
        $this->processActiveEvents($user);

        // 2. Chance to generate new event
        $serverCount = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->count();
        $diffModifiers = $user->economy->getDifficultyModifiers();
        
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $eventFreqMult = $engine['event_frequency_modifier'] ?? 1.0;
        
        $baseChance = (2 + ($serverCount * 0.1)) * ($diffModifiers['event_freq_mod'] ?? 1.0) * $eventFreqMult; 
        $maxChance = 30; 
        
        $chance = min($baseChance, $maxChance);

        // Check active events count cap (Difficulty dependent)
        $maxActive = match($user->economy->difficulty) {
            'easy' => 2,
            'normal' => 3,
            'hard' => 5,
            'ironman' => 8,
            default => 3
        };

        $activeCount = GameEvent::where('user_id', $user->id)
            ->whereIn('status', [EventStatus::WARNING, EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->count();

        if ($activeCount < $maxActive && rand(0, 100) < $chance) {
            $this->generateEvent($user);
        }
    }

    private function processActiveEvents(User $user): void
    {
        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', [EventStatus::WARNING, EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->get();

        $activeCount = $events->count();

        foreach ($events as $event) {
            $now = Carbon::now();

            // CASCADING TIME PRESSURE
            if ($activeCount > 1 && $event->status !== EventStatus::WARNING) {
                $secondsToReduce = ($activeCount - 1) * 30; 
                $event->deadline_at = $event->deadline_at->subSeconds($secondsToReduce);
                $event->escalates_at = $event->escalates_at->subSeconds($secondsToReduce);
                $event->save();
            }

            // Transition Warning -> Active
            if ($event->status === EventStatus::WARNING && $event->warning_at && $now->gte($event->warning_at)) {
                $event->status = EventStatus::ACTIVE;
                $event->save();
                broadcast(new \App\Events\GameEventStarted($user, $event));
                
                \App\Models\GameLog::log($user, "Critical Alert: {$event->title}", 'danger', 'infrastructure', [
                    'event_id' => $event->id,
                    'severity' => $event->severity
                ]);
            }

            // Transition Active -> Escalated
            if ($event->status === EventStatus::ACTIVE && $event->shouldEscalate()) {
                $event->escalate();
                $this->applyEscalationConsequences($event);
            }

            // Deadline check -> Fail
            if ($event->shouldAutoFail()) {
                $this->failEvent($event);
                continue;
            }

            // Record incident state
            $this->recordIncidentState($event);
        }
    }

    private function recordIncidentState(GameEvent $event): void
    {
        $replay = $event->replay_data ?? [];
        
        $snapshot = [
            'timestamp' => now()->toIso8601String(),
            'status' => $event->status->value,
            'severity' => $event->severity,
            'telemetry' => []
        ];

        if ($event->affected_room_id) {
            $room = $event->affectedRoom;
            if ($room) {
                $snapshot['telemetry']['room'] = [
                    'temp' => (float) $room->temperature,
                    'power' => (float) $room->current_power_kw,
                ];
            }
        }

        if ($event->affected_rack_id) {
            $rack = $event->affectedRack;
            if ($rack) {
                $snapshot['telemetry']['rack'] = [
                    'temp' => (float) $rack->temperature,
                    'thermalMap' => $rack->thermal_map,
                ];
            }
        }

        if ($event->affected_server_id) {
            $server = $event->affectedServer;
            if ($server) {
                $snapshot['telemetry']['server'] = [
                    'health' => (int) $server->health,
                    'status' => $server->status->value,
                ];
            }
        }

        $replay[] = $snapshot;
        if (count($replay) > 60) array_shift($replay);

        $event->replay_data = $replay;
        $event->save();
    }

    public function resolveEvent(User $user, string $eventId, string $actionId): array
    {
        $event = GameEvent::where('user_id', $user->id)
            ->where('id', $eventId)
            ->whereIn('status', [EventStatus::WARNING, EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->firstOrFail();

        $action = collect($event->getActions())->firstWhere('id', $actionId);
        if (!$action) {
            throw new \Exception("Invalid action.");
        }

        if ($user->economy->balance < $action['cost']) {
            throw new \Exception("Insufficient funds.");
        }

        return DB::transaction(function () use ($user, $event, $action) {
            if ($action['cost'] > 0) {
                if (!$user->economy->debit($action['cost'], "Event Action: {$action['label']}", 'event_mitigation', $event)) {
                     throw new \Exception("Insufficient funds.");
                }
            }

            $success = rand(0, 100) < $action['success_chance'];

            if ($success) {
                $this->applyActionEffects($user, $event, $action);

                $event->status = EventStatus::RESOLVED;
                $event->resolved_at = now();
                $event->action_cost = $action['cost'] ?? 0;
                
                $scoring = $this->calculateCrisisScore($event);
                $event->management_score = $scoring['score'];
                $event->management_grade = $scoring['grade'];
                
                $event->save();

                $msg = "Incident Resolved: {$event->title} (Grade: {$event->management_grade})";
                \App\Models\GameLog::log($user, $msg, 'success', 'infrastructure');
                
                $xpReward = $event->xp_reward ?? 50;
                if ($event->management_grade === 'S') {
                    $xpReward *= 2.0;
                    $user->economy->adjustReputation(3.0);
                } elseif ($event->management_grade === 'A') {
                    $xpReward *= 1.5;
                    $user->economy->adjustReputation(1.0);
                }

                $user->economy->addExperience((int) $xpReward);
                $this->awardSpecializedReputation($user, $event, 'resolution');

                if (in_array($action['id'], [
                    'replace_component', 'replace_cable', 'reroute_traffic', 'reboot_system', 'update_firmware',
                    'reboot', 'replace_drive', 'mitigate_premium', 'mitigate_basic'
                ])) {
                    if ($event->affected_server_id) {
                        $server = $event->affectedServer;
                        if ($server) {
                             $server->health = min(100, $server->health + 30);
                             $server->status = ServerStatus::ONLINE;
                             $server->save();
                        }
                    }
                }

                broadcast(new \App\Events\GameEventResolved($user, $event));

                if ($event->management_grade === 'S') {
                    $this->achievementService->unlock($user, 'perfect_grade');
                }

                $resolvedCount = \App\Models\GameEvent::where('user_id', $user->id)
                    ->where('status', \App\Enums\EventStatus::RESOLVED)
                    ->count();
                if ($resolvedCount >= 10) {
                    $this->achievementService->unlock($user, 'crisis_survivor');
                }

                return ['resolution' => 'success', 'xpEarned' => $event->xp_reward ?? 50];
            } else {
                $event->severity = 'critical'; 
                $event->description = "The action '{$action['label']}' failed! " . $event->description;
                $event->save();

                \App\Models\GameLog::log($user, "Action Failed: Response to {$event->title} was unsuccessful.", 'warning', 'infrastructure');

                $this->applyEscalationConsequences($event);
                broadcast(new \App\Events\GameEventEscalated($user, $event));

                return ['resolution' => 'failure'];
            }
        });
    }

    private function applyActionEffects(User $user, GameEvent $event, array $action): void
    {
        // --- PRICE WAR SPECIAL EFFECTS ---
        if ($event->type === EventType::PRICE_WAR) {
            $economy = $user->economy;
            switch ($action['id']) {
                case 'match_pricing':
                    // Temporary price reduction (simulated by setting a policy or modifier)
                    $economy->setPolicy('price_match_active', true, 15); // Active for 15 ticks
                    \App\Models\GameLog::log($user, "MARKET: You have matched the competitor's prices. Market share should stabilize.", 'info', 'economy');
                    break;
                case 'marketing_counter':
                    $economy->adjustReputation(5.0);
                    \App\Models\GameLog::log($user, "MARKET: PR campaign launched. Customers value your reliability over cheap prices.", 'success', 'economy');
                    break;
                case 'ignore_pricing':
                    // No immediate effect, but the 'ignore' path has low success chance which triggers failure consequences
                    break;
            }
            return;
        }

        // --- HIRING RAID SPECIAL EFFECTS ---
        if ($event->type === EventType::HIRING_RAID) {
            $economy = $user->economy;
            switch ($action['id']) {
                case 'counter_offer':
                    // Permanent salary hike for all employees to keep them
                    \App\Models\Employee::where('user_id', $user->id)
                        ->increment('salary', DB::raw('salary * 0.2'));
                    $economy->adjustReputation(2.0);
                    \App\Models\GameLog::log($user, "STRATEGY: Salary hike implemented. Staff loyalty solidified.", 'success', 'hr');
                    break;
                case 'loyalty_bonus':
                    // One-time payment (cost in event) + stress reduction
                    \App\Models\Employee::where('user_id', $user->id)
                        ->decrement('stress', 30);
                    \App\Models\GameLog::log($user, "STRATEGY: Loyalty bonuses distributed. Morale improved.", 'success', 'hr');
                    break;
                case 'ignore_raid':
                    // If success (15% chance), nobody leaves.
                    $economy->adjustReputation(-2.0);
                    break;
            }
            return;
        }

        // --- PATENT LAWSUIT SPECIAL EFFECTS ---
        if ($event->type === EventType::PATENT_LAWSUIT) {
            $economy = $user->economy;
            switch ($action['id']) {
                case 'settle':
                    $economy->adjustReputation(1.0); // Being fair
                    break;
                case 'fight':
                    // If success (handled in resolveEvent), we just saved money.
                    // If failed (resolveEvent logic), the penalty should be applied here 
                    // Wait, resolveEvent only calls applyActionEffects on SUCCESS.
                    // I need to handle failure in a separate place or check status.
                    $economy->adjustReputation(3.0); // Bold move
                    break;
                case 'countersue':
                    $payout = $action['cost'] * 3;
                    $economy->credit($payout, "Won Intellectual Property Counter-Suit", 'legal');
                    $economy->adjustReputation(8.0);
                    break;
            }
            return;
        }

        switch ($action['id']) {
            case 'controlled_shutdown':
                if ($event->affected_room_id) {
                    $servers = Server::whereHas('rack', fn($q) => $q->where('room_id', $event->affected_room_id))
                        ->where('status', ServerStatus::ONLINE)
                        ->get();
                    foreach ($servers as $server) {
                        $server->status = ServerStatus::OFFLINE;
                        $server->save();
                    }
                }
                break;
            case 'restore_backup':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server && $server->last_backup_data) {
                        $backup = $server->last_backup_data;
                        $server->installed_os_type = $backup['os_type'] ?? null;
                        $server->installed_os_version = $backup['os_version'] ?? null;
                        $server->os_config = $backup['os_config'] ?? [];
                        $server->installed_applications = $backup['installed_applications'] ?? [];
                        $server->security_patch_level = $backup['security_patch_level'] ?? 0;
                        $server->os_install_status = 'installed';
                        $server->current_fault = null;
                        $server->status = ServerStatus::ONLINE;
                        $server->health = min(100, $server->health + 10);
                        $server->save();
                    }
                }
                break;
            case 'reinstall_clean':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server) {
                        $server->installed_os_type = null;
                        $server->installed_os_version = null;
                        $server->os_config = [];
                        $server->installed_applications = [];
                        $server->security_patch_level = 0;
                        $server->os_install_status = 'none';
                        $server->current_fault = null;
                        $server->status = ServerStatus::OFFLINE;
                        $server->health = min(100, $server->health + 10);
                        $server->save();
                    }
                }
                break;
            case 'isolate_server':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server) {
                        $server->status = ServerStatus::OFFLINE;
                        $server->save();
                    }
                }
                break;
            case 'active_defense':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server && $server->status === ServerStatus::ONLINE) {
                        $server->health = max(10, $server->health - 5); 
                        $server->save();
                    }
                }
                break;
            case 'restart_interface':
                 if ($event->affected_server_id) {
                     $server = $event->affectedServer;
                     $server->health = min(100, $server->health + 10);
                     $server->save();
                 }
                 break;
            case 'blackhole_traffic':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server) {
                        $orders = $server->activeOrders;
                        foreach ($orders as $order) {
                            $order->status = 'cancelled';
                            $order->save();
                        }
                        $user->economy->adjustReputation(-10.0);
                    }
                }
                break;
            case 'boost_cooling':
            case 'engage_secondary_cooling':
                if ($event->affected_room_id) {
                    $room = GameRoom::find($event->affected_room_id);
                    if ($room) {
                        foreach ($room->racks as $rack) {
                            $rack->temperature = max(18, $rack->temperature - 15);
                            $rack->save();
                        }
                    }
                }
                break;
            case 'repair_ac':
                if ($event->affected_room_id) {
                    $room = GameRoom::find($event->affected_room_id);
                    if ($room) {
                        foreach ($room->racks as $rack) {
                            $rack->temperature = 20; 
                            $rack->save();
                        }
                    }
                }
                break;
            case 'quick_patch':
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server) {
                        $server->health = max(5, $server->health - 25);
                        $server->save();
                    }
                }
                break;
            case 'emergency_patch':
                if (isset($action['target_os'])) {
                    \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                        ->where('installed_os_type', $action['target_os'])
                         ->update(['security_patch_level' => DB::raw('LEAST(100, security_patch_level + 15)')]);
                }
                break;
            case 'vendor_wait':
                if (isset($action['target_os'])) {
                    \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                        ->where('installed_os_type', $action['target_os'])
                         ->update(['security_patch_level' => DB::raw('LEAST(100, security_patch_level + 5)')]);
                }
                break;
            
            // --- FEATURE 213: ISP BANNING ACTIONS ---
            case 'legal_appeal':
                $user->economy->adjustReputation(5.0);
                \App\Models\GameLog::log($user, "LEGAL: Erfolg! Ihr IP-Adressraum wurde nach einer manuellen Überprüfung wieder freigegeben.", 'success', 'network');
                break;
            case 'bribe_admin':
                 $user->economy->adjustReputation(-5.0);
                 \App\Models\GameLog::log($user, "NETZWERK: Der Blacklist-Admin hat Ihr 'Geschenk' angenommen. Die IPs sind wieder sauber.", 'warning', 'network');
                 break;
            case 'rotate_ips':
                 $network = $user->network;
                 $meta = $network->metadata ?? [];
                 $meta['black_market_ip_count'] = max(0, ($meta['black_market_ip_count'] ?? 0) - 32);
                 $network->metadata = $meta;
                 $network->save();
                 \App\Models\GameLog::log($user, "NETZWERK: Prefixe rotiert. Die aktuelle Blacklist betrifft Ihre aktiven Dienste nicht mehr.", 'info', 'network');
                 break;
            case 'failover_satellite':
                $user->economy->adjustReputation(2.0);
                \App\Models\GameLog::log($user, "NETWORK: Satellite link active. Latency increased but connectivity restored.", 'info', 'network');
                break;
            case 'legal_demand':
                $user->economy->adjustReputation(1.0);
                \App\Models\GameLog::log($user, "LEGAL: Demand sent. Repair crew has been prioritized for your circuit.", 'info', 'legal');
                break;
        }
    }

    private function generateEvent(User $user): void
    {
        $types = [
            EventType::HARDWARE_FAILURE->value => 40,
            EventType::DDOS_ATTACK->value => 30,
            EventType::OVERHEATING->value => 15,
            EventType::SECURITY_BREACH->value => 10,
            EventType::POWER_OUTAGE->value => 5,
            EventType::DATA_LEAK->value => 2,
            EventType::ZERO_DAY_EXPLOIT->value => 5,
            EventType::STORAGE_FAILURE->value => 8,
            EventType::FIBER_CUT->value => 5,
        ];

        // 1. CASCADE LOGIC
        $activeEvents = GameEvent::where('user_id', $user->id)
            ->whereIn('status', [EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->get();

        foreach ($activeEvents as $event) {
            if ($event->type === EventType::OVERHEATING) {
                 $types[EventType::HARDWARE_FAILURE->value] += 40; 
            }
            if ($event->type === EventType::DDOS_ATTACK) {
                 $types[EventType::SECURITY_BREACH->value] += 25;
            }
            if ($event->type === EventType::POWER_OUTAGE) {
                 $types[EventType::HARDWARE_FAILURE->value] += 20;
            }
        }
        
        // 2. HEALTH LOGIC
        $degradedCount = Server::whereHas('rack.room', fn($q)=>$q->where('user_id', $user->id))
             ->where('status', ServerStatus::DEGRADED)
             ->count();
        
        if ($degradedCount > 0) {
             $types[EventType::HARDWARE_FAILURE->value] += min(100, $degradedCount * 5);
             $types[EventType::DDOS_ATTACK->value] += min(50, $degradedCount * 3);
        }

        // 2b. SECURITY LOGIC
        $failedBreaches = GameEvent::where('user_id', $user->id)
            ->where('type', EventType::SECURITY_BREACH)
            ->where('status', EventStatus::FAILED)
            ->where('created_at', '>', now()->subHours(1))
            ->count();
            
        if ($failedBreaches > 0) {
            $types[EventType::SECURITY_BREACH->value] += 50;
            $types[EventType::HARDWARE_FAILURE->value] += 30; 
        }

        // FEATURE 157: Backdoor Risk
        $backdoorCount = \App\Models\UserComponent::where('user_id', $user->id)
            ->where('meta->has_backdoor', true)
            ->count();
        if ($backdoorCount > 0) {
            $types[EventType::SECURITY_BREACH->value] += ($backdoorCount * 25);
            $types[EventType::DATA_LEAK->value] += ($backdoorCount * 10);
            $types[EventType::ZERO_DAY_EXPLOIT->value] += ($backdoorCount * 5);
        }

        // 3. RESEARCH LOGIC
        $researchService = app(ResearchService::class);
        $securityDefense = $researchService->getBonus($user, 'security_defense');
        
        // --- FEATURE 55: SECURITY PERKS ---
        $securityEngineers = \App\Models\Employee::where('user_id', $user->id)
            ->where('type', 'security_engineer')
            ->get();
        
        $firewallBonus = 0;
        foreach ($securityEngineers as $eng) {
            if ($this->employeeService->hasPerk($eng, 'firewall_master')) {
                 $firewallBonus += 0.20;
            }
        }
        
        $totalDefense = min(0.95, $securityDefense + $firewallBonus);

        if ($totalDefense > 0) {
            $originalBreachProb = $types[EventType::SECURITY_BREACH->value] ?? 0;
            $types[EventType::SECURITY_BREACH->value] = (int) max(1, $originalBreachProb * (1.0 - $totalDefense));
            
            $originalDdosProb = $types[EventType::DDOS_ATTACK->value] ?? 0;
             $types[EventType::DDOS_ATTACK->value] = (int) max(1, $originalDdosProb * (1.0 - ($totalDefense * 0.5)));
        }

        // 3b. PLAYER SKILL & SPECIALIZED TECH: DDoS Resilience
        $ddosReduction = $this->skillService->getBonus($user, 'ddos_risk_reduction');
        $quantumResilience = $this->researchService->getBonus($user, 'ddos_resilience'); // 0.80 for Premium
        
        $totalDdosReduction = min(0.98, $ddosReduction + $quantumResilience);
        if ($totalDdosReduction > 0) {
            $originalDdosProb = $types[EventType::DDOS_ATTACK->value] ?? 0;
            $types[EventType::DDOS_ATTACK->value] = (int) max(1, $originalDdosProb * (1.0 - $totalDdosReduction));
        }

        // 3c. SPECIALIZED TECH: BGP Immunity
        $bgpImmunity = $this->researchService->getBonus($user, 'bgp_immunity'); // 1.0 for Crypto
        if ($bgpImmunity >= 1.0) {
            unset($types[EventType::BGP_HIJACKING->value]);
        }

        // 4. INFRASTRUCTURE REDUNDANCY
        $rooms = GameRoom::where('user_id', $user->id)->get();
        $avgRedundancyBonus = 0;
        if ($rooms->count() > 0) {
            $totalReduction = 0;
            foreach ($rooms as $room) {
                $prot = $room->getRedundancyProtection();
                $totalReduction += $prot['prob_reduction'];
            }
            $avgRedundancyBonus = $totalReduction / $rooms->count();
        }

        if ($avgRedundancyBonus > 0) {
            $types[EventType::POWER_OUTAGE->value] = (int) max(1, ($types[EventType::POWER_OUTAGE->value] ?? 5) * (1.0 - $avgRedundancyBonus));
            $types[EventType::OVERHEATING->value] = (int) max(1, ($types[EventType::OVERHEATING->value] ?? 15) * (1.0 - $avgRedundancyBonus));
        }

        // 5. Weighted Random Selection
        $totalWeight = array_sum($types);
        $rand = rand(1, $totalWeight);
        
        $cumul = 0;
        $selectedType = EventType::HARDWARE_FAILURE;

        foreach ($types as $typeValue => $weight) {
            $cumul += $weight;
            if ($rand <= $cumul) {
                $selectedType = EventType::from($typeValue);
                break;
            }
        }

        switch ($selectedType) {
            case EventType::HARDWARE_FAILURE:
                $this->createHardwareFailure($user);
                break;
            case EventType::DDOS_ATTACK:
                $this->createDdosAttack($user);
                break;
            case EventType::OVERHEATING:
                $this->createOverheatEvent($user);
                break;
            case EventType::POWER_OUTAGE:
                $this->createPowerOutage($user);
                break;
            case EventType::NETWORK_FAILURE:
                $this->createNetworkFailure($user);
                break;
            case EventType::SECURITY_BREACH:
                $this->createSecurityBreach($user);
                break;
            case EventType::BGP_HIJACKING:
                $this->createBgpEvent($user);
                break;
            case EventType::DATA_LEAK:
                $this->createDataLeak($user);
                break;
            case EventType::ZERO_DAY_EXPLOIT:
                $this->createZeroDayExploit($user);
                break;
            case EventType::STORAGE_FAILURE:
                $this->createStorageFailure($user);
                break;
            case EventType::FIBER_CUT:
                $this->createFiberCutEvent($user);
                break;
            default:
                $this->createHardwareFailure($user);
        }
    }

    private function createHardwareFailure(User $user): void
    {
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->inRandomOrder()
            ->first();

        if (!$server) return;

        $deadlineBonus = $this->skillService->getBonus($user, 'deadline_bonus_s');
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $repairMult = $engine['base_repair_cost_multiplier'] ?? 1.0;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::HARDWARE_FAILURE,
            'severity' => 'medium',
            'status' => EventStatus::WARNING,
            'title' => 'Hardware Malfunction Detected',
            'description' => "Server {$server->model_name} in Rack {$server->rack->name} is reporting drive errors.",
            'affected_room_id' => $server->rack->room_id,
            'affected_rack_id' => $server->rack_id,
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->warningSeconds() + $deadlineBonus),
            'escalates_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->escalationSeconds() + $deadlineBonus),
            'deadline_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->deadlineSeconds() + $deadlineBonus),
            'damage_cost' => $server->purchase_cost * 0.1 * $repairMult,
            'available_actions' => [
                [
                    'id' => 'quick_patch', 
                    'label' => 'Hot-Swap Patch ($' . (int)(50 * $repairMult) . ')', 
                    'cost' => 50 * $repairMult, 
                    'duration' => 10,
                    'description' => 'Fast temporary fix. High success chance but permanently damages server health.',
                    'success_chance' => 90
                ],
                [
                    'id' => 'reboot', 
                    'label' => 'Reboot & Check (Low Chance)', 
                    'cost' => 0, 
                    'duration' => 60,
                    'description' => 'Try to reboot the server. Might fail if hardware is broken.',
                    'success_chance' => 30
                ],
                [
                    'id' => 'replace_drive', 
                    'label' => 'Total Part Replacement ($' . (int)(500 * $repairMult) . ')', 
                    'cost' => 500 * $repairMult, 
                    'duration' => 300,
                    'description' => 'Professional repair. Expensive and slow, but 100% reliable.',
                    'success_chance' => 100
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
            'xp_reward' => 75,
        ]);

        $server->status = ServerStatus::DEGRADED;
        $server->health -= 20;
        $server->save();
    }

    private function createDdosAttack(User $user): void
    {
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('activeOrders')
            ->inRandomOrder()
            ->first();

        if (!$server) return;
        
        // --- FEATURE 55: SECURITY PERKS (Trace Route) ---
        $securityEngineers = \App\Models\Employee::where('user_id', $user->id)
            ->where('type', 'security_engineer')
            ->get();
            
        $traceRouteBonus = 0;
        foreach ($securityEngineers as $eng) {
            if ($this->employeeService->hasPerk($eng, 'trace_route')) {
                 $traceRouteBonus += 0.30; 
            }
        }
        
        // Specialization V2: Pen-Tester (Detection Speed)
        $penTesterBonus = $this->employeeService->getAggregatedBonus($user, 'detection_speed');
        
        $timeMultiplier = 1.0 + min(1.0, $traceRouteBonus) + ($penTesterBonus > 1.0 ? ($penTesterBonus - 1.0) : 0);

        $baseEscalate = EventType::DDOS_ATTACK->escalationSeconds();
        $baseDeadline = EventType::DDOS_ATTACK->deadlineSeconds();
        $skillBonus = $this->skillService->getBonus($user, 'deadline_bonus_s');

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::DDOS_ATTACK,
            'severity' => 'high',
            'status' => EventStatus::ACTIVE,
            'title' => 'DDoS Attack Inbound',
            'description' => "Massive traffic spike targeting Server {$server->model_name}. Network saturation imminent.",
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(($baseEscalate * $timeMultiplier) + $skillBonus),
            'deadline_at' => Carbon::now()->addSeconds(($baseDeadline * $timeMultiplier) + $skillBonus),
            'damage_cost' => 0,
            'available_actions' => [
                [
                    'id' => 'blackhole_traffic', 
                    'label' => 'Blackhole Traffic (Instant)', 
                    'cost' => 0, 
                    'duration' => 5,
                    'description' => 'Drop all traffic to target. Stops DDoS immediately, but kills all active services on the server.',
                    'success_chance' => 100
                ],
                [
                    'id' => 'mitigate_basic', 
                    'label' => 'Basic Filtering (Free)', 
                    'cost' => 0, 
                    'duration' => 300,
                    'description' => 'Apply firewall rules. Reduces impact but may block legitimate traffic.',
                    'success_chance' => 50
                ],
                [
                    'id' => 'mitigate_premium', 
                    'label' => 'Premium Protection ($500)', 
                    'cost' => 500, 
                    'duration' => 60,
                    'description' => 'Route through scrubbing center. Clean traffic, high success.',
                    'success_chance' => 95
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
            'xp_reward' => 150,
        ]);
    }
    
    private function createSecurityBreach(User $user): void
    {
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->inRandomOrder()
            ->first();

        if (!$server) return;
        
        // FEATURE 157: Backdoor Detection
        $backdoorComp = \App\Models\UserComponent::where('assigned_server_id', $server->id)
            ->where('meta->has_backdoor', true)
            ->first();
        
        $title = $backdoorComp ? 'Unidentified Transmission' : 'Unauthorized Access Attempt';
        $desc = $backdoorComp 
            ? "Server {$server->model_name} is sending encrypted heartbeats to an unknown command & control server. Tainted hardware detected."
            : "Intrusion detection alert on Server {$server->model_name}. Someone is trying to brute-force the root password.";

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::SECURITY_BREACH,
            'severity' => $backdoorComp ? 'critical' : 'high',
            'status' => EventStatus::ACTIVE,
            'title' => $title,
            'description' => $desc,
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(EventType::SECURITY_BREACH->escalationSeconds()),
            'deadline_at' => Carbon::now()->addSeconds(EventType::SECURITY_BREACH->deadlineSeconds()),
            'damage_cost' => 0,
            'available_actions' => [
                [
                    'id' => 'isolate_server', 
                    'label' => 'Physically Disconnect (Offload)', 
                    'cost' => 0, 
                    'duration' => 10,
                    'description' => 'Shutdown immediately. Safe but causes downtime.',
                    'success_chance' => 100
                ],
                [
                    'id' => 'active_defense', 
                    'label' => 'Cyber Countermeasures ($250)', 
                    'cost' => 250, 
                    'duration' => 120,
                    'description' => 'Deploy anti-intruder bots. Keeps server online, 75% success.',
                    'success_chance' => 75
                ],
                [
                    'id' => 'reinstall_clean', 
                    'label' => 'Full OS Wipe & Reinstall', 
                    'cost' => 100, 
                    'duration' => 600,
                    'description' => 'Nuclear option. Resolves issue but wipes all data/config.',
                    'success_chance' => 100
                ],
            ],
            'xp_reward' => 200,
        ]);
    }
    
    public function createOverheatEvent(User $user, ?ServerRack $triggerRack = null): void
    {
        $room = $triggerRack ? $triggerRack->room : GameRoom::where('user_id', $user->id)->first();
        if (!$room) return;

        $title = $triggerRack 
            ? "Overheating: {$triggerRack->name} at {$triggerRack->temperature}°C"
            : 'Cooling System Warning';
            
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $repairMult = $engine['base_repair_cost_multiplier'] ?? 1.0;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::OVERHEATING,
            'severity' => 'critical',
            'status' => EventStatus::WARNING,
            'title' => $title,
            'description' => "Temperature rising in {$room->name}. Cooling unit malfunction.",
            'affected_room_id' => $room->id,
            'affected_rack_id' => $triggerRack?->id,
            'warning_at' => Carbon::now()->addSeconds(30 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'escalates_at' => Carbon::now()->addSeconds(180 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'deadline_at' => Carbon::now()->addSeconds(600 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'available_actions' => array_merge([
                [
                    'id' => 'boost_cooling', 
                    'label' => 'Emergency Cooling Boost ($' . (int)(100 * $repairMult) . ')', 
                    'cost' => 100 * $repairMult, 
                    'duration' => 30,
                    'description' => 'Overdrive remaining units. Higher energy cost.',
                    'success_chance' => 80
                ],
                [
                    'id' => 'repair_ac', 
                    'label' => 'Repair AC Unit ($' . (int)(1000 * $repairMult) . ')', 
                    'cost' => 1000 * $repairMult, 
                    'duration' => 120,
                    'description' => 'Full repair of the failed unit.',
                    'success_chance' => 100
                ],
            ], $room->redundancy_level >= 2 ? [
                [
                    'id' => 'engage_secondary_cooling', 
                    'label' => 'Secondary Cooling Plant ($' . (int)(200 * $repairMult) . ')', 
                    'cost' => 200 * $repairMult, 
                    'duration' => 10,
                    'description' => 'Utilize redundant N+1 cooling capacity.',
                    'success_chance' => 98
                ]
            ] : []),
            'affected_customers_count' => 0,
            'xp_reward' => 100,
        ]);
    }

    public function createZeroDayExploit(User $user): void
    {
        // Select random OS from User's Fleet
        $osTypes = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', \App\Enums\ServerStatus::ONLINE)
            ->whereNotNull('installed_os_type')
            ->select('installed_os_type')
            ->distinct()
            ->pluck('installed_os_type');

        if ($osTypes->isEmpty()) return;

        $targetOs = $osTypes->random();
        
        // Decrease patch level immediately upon discovery
        \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('installed_os_type', $targetOs)
            ->update(['security_patch_level' => DB::raw('GREATEST(0, security_patch_level - 20)')]);

        $affectedCount = \App\Models\Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('installed_os_type', $targetOs)
            ->count();

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::ZERO_DAY_EXPLOIT,
            'severity' => 'high',
            'status' => EventStatus::ACTIVE,
            'title' => "Zero-Day Vulnerability: {$targetOs}",
            'description' => "A critical zero-day exploit has been publicized for {$targetOs}. Patch levels dropped by 20%. {$affectedCount} servers vulnerable.",
            'warning_at' => Carbon::now(), // Immediate active
            'escalates_at' => Carbon::now()->addSeconds(300),
            'deadline_at' => Carbon::now()->addSeconds(600),
            'damage_cost' => 0,
            'available_actions' => [
                [
                    'id' => 'emergency_patch',
                    'label' => 'Emergency Patch ($500)',
                    'cost' => 500,
                    'duration' => 60,
                    'description' => 'Deploy hotfix immediately. Restores 15% patch level. High success.',
                    'success_chance' => 95,
                    'target_os' => $targetOs // Metadata for action effect
                ],
                [
                    'id' => 'vendor_wait',
                    'label' => 'Wait for Vendor (Free)',
                    'cost' => 0,
                    'duration' => 300,
                    'description' => 'Wait for official patch. Restores 5% patch level. Slow.',
                    'success_chance' => 100,
                     'target_os' => $targetOs
                ]
            ],
            'affected_customers_count' => $affectedCount, // Approximate impact
            'xp_reward' => 200,
        ]);
    }

    public function createStorageFailure(User $user): void
    {
        // Target random online server with data
        $targetServer = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->whereNotNull('installed_os_type')
            ->whereNotNull('installed_applications')
            ->inRandomOrder()
            ->first();

        if (!$targetServer) return;

        // Execute Fault
        $targetServer->current_fault = 'storage_corruption';
        $targetServer->status = ServerStatus::DEGRADED; 
        
        // Simulating data loss by clearing active state, but keeping backup
        $targetServer->installed_applications = []; 
        $targetServer->os_install_status = 'corrupted'; 
        
        $targetServer->save();
        
        Log::warning("Storage Failure on Server {$targetServer->id} (User: {$user->id}). Data wiped.");

        $recoverChance = 0;
        if ($targetServer->backup_plan !== \App\Enums\BackupPlan::NONE) {
            $recoverChance = $targetServer->backup_health ?? 50;
        }

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::STORAGE_FAILURE,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => "Storage Failure: {$targetServer->model_name}",
            'description' => "Critical drive failure logic board error on {$targetServer->model_name}. Application data index corrupted. Restore required.",
            'affected_server_id' => $targetServer->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(1800),
            'deadline_at' => Carbon::now()->addSeconds(3600),
            'available_actions' => [
                 [
                     'id' => 'restore_backup',
                     'label' => 'Restore from Backup',
                     'cost' => 0,
                     'description' => 'Attempt data recovery from last snapshot.',
                     'duration' => 120,
                     'success_chance' => $recoverChance,
                 ],
                 [
                     'id' => 'reinstall_clean',
                     'label' => 'Clean Reinstall ($50)',
                     'cost' => 50,
                     'description' => 'Wipe and install fresh OS. Data lost.',
                     'duration' => 300,
                     'success_chance' => 100,
                 ]
            ],
            'affected_customers_count' => $targetServer->activeOrders()->count(),
            'xp_reward' => 150,
        ]);
    }

    public function createPowerOutage(User $user, ?GameRoom $triggerRoom = null): void
    {
        $room = $triggerRoom ?? GameRoom::where('user_id', $user->id)
            ->whereHas('racks.servers', fn($q) => $q->where('status', ServerStatus::ONLINE))
            ->first();
        
        if (!$room) return;

        $affectedServers = Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))
            ->where('status', ServerStatus::ONLINE)
            ->count();
            
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $repairMult = $engine['base_repair_cost_multiplier'] ?? 1.0;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::POWER_OUTAGE,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'Power Grid Instability',
            'description' => "Power fluctuations detected in {$room->name}. {$affectedServers} servers at risk of going offline.",
            'affected_room_id' => $room->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(180 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'deadline_at' => Carbon::now()->addSeconds(480 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'available_actions' => array_merge([
                [
                    'id' => 'switch_backup',
                    'label' => 'Switch to Backup Power ($' . (int)(300 * $repairMult) . ')',
                    'cost' => 300 * $repairMult,
                    'duration' => 30,
                    'description' => 'Activate emergency generators. Prevents shutdown.',
                    'success_chance' => 90,
                ],
                [
                    'id' => 'controlled_shutdown',
                    'label' => 'Controlled Shutdown (Free)',
                    'cost' => 0,
                    'duration' => 120,
                    'description' => 'Safely power down non-essential servers. Prevents hardware damage but causes downtime.',
                    'success_chance' => 100,
                ],
            ], $room->redundancy_level >= 3 ? [
                [
                    'id' => 'seamless_redundant_path',
                    'label' => 'Redundant Power Path ($' . (int)(50 * $repairMult) . ')',
                    'cost' => 50 * $repairMult,
                    'duration' => 5,
                    'description' => 'Automatically reroute power through Tier 3 secondary circuits.',
                    'success_chance' => 99,
                ]
            ] : ($room->redundancy_level >= 2 ? [
                [
                    'id' => 'engage_ha_failover',
                    'label' => 'Engage N+1 Failover ($' . (int)(150 * $repairMult) . ')',
                    'cost' => 150 * $repairMult,
                    'duration' => 15,
                    'description' => 'Utilize N+1 backup capacity to stabilize the grid.',
                    'success_chance' => 95,
                ]
            ] : [])),
            'affected_customers_count' => $affectedServers,
            'xp_reward' => 200,
        ]);
    }

    private function createNetworkFailure(User $user): void
    {
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->whereHas('activeOrders')
            ->inRandomOrder()
            ->first();

        if (!$server) return;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::NETWORK_FAILURE,
            'severity' => 'medium',
            'status' => EventStatus::WARNING,
            'title' => 'Network Connectivity Issue',
            'description' => "Packet loss detected on Server {$server->model_name}. Customers reporting slow connections.",
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now()->addSeconds(60 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'escalates_at' => Carbon::now()->addSeconds(300 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'deadline_at' => Carbon::now()->addSeconds(600 + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'available_actions' => [
                [
                    'id' => 'restart_interface',
                    'label' => 'Restart Network Interface (Free)',
                    'cost' => 0,
                    'duration' => 30,
                    'description' => 'Quick fix. Brief downtime while interface resets.',
                    'success_chance' => 60,
                ],
                [
                    'id' => 'replace_cable',
                    'label' => 'Replace Network Cable ($50)',
                    'cost' => 50,
                    'duration' => 60,
                    'description' => 'Swap the suspected faulty cable.',
                    'success_chance' => 85,
                ],
                [
                    'id' => 'reroute_traffic',
                    'label' => 'Reroute via Backup Path ($200)',
                    'cost' => 200,
                    'duration' => 15,
                    'description' => 'Use alternative network route. Instant but expensive.',
                    'success_chance' => 100,
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
            'xp_reward' => 100,
        ]);
    }

    private function failEvent(GameEvent $event): void
    {
        $user = $event->user;
        $event->status = EventStatus::FAILED;
        $event->resolved_at = now();
        $event->management_score = 0;
        $event->management_grade = 'F';
        $event->save();

        \App\Models\GameLog::log($user, "CRITICAL FAILURE: Incident '{$event->title}' was not resolved in time!", 'danger', 'infrastructure');

        $penaltyBase = -15.0;
        if ($event->type === EventType::DATA_LEAK) {
            $penaltyBase = -($user->economy->reputation * 0.50);
            \App\Models\GameLog::log($user, "SCANDAL: Massive data leak confirmed! Public trust destroyed (-50% Rep).", 'danger', 'security');
        } elseif ($event->type === EventType::DDOS_ATTACK) {
            $ddosResilience = app(ResearchService::class)->getBonus($user, 'ddos_resilience');
            $penaltyBase *= (1.0 - $ddosResilience);
        }
        $user->economy->adjustReputation($penaltyBase);

        if ($event->affected_customers_count > 0) {
            $penalty = $event->affected_customers_count * 150;
            $repPremium = $user->economy->getSpecializedReputation('premium');
            if ($repPremium >= 80) {
                $penalty *= 0.80; 
            }
            $user->economy->debit($penalty, "SLA Penalty: Unresolved {$event->type->value}", 'maintenance', $event);
        }

        if (in_array($event->type, [EventType::SECURITY_BREACH, EventType::DATA_LEAK, EventType::DDOS_ATTACK])) {
            if (rand(1, 100) < 50) { 
                app(ManagementService::class)->triggerDecision($user->economy, 'data_leak');
                \App\Models\GameLog::log($user, "SCANDAL: The failed mitigation has led to a major data leak! PR crisis in progress.", 'critical', 'security');
            }
        }

        // --- HIRING RAID FAILURE ---
        if ($event->type === EventType::HIRING_RAID) {
            $empService = app(\App\Services\Game\EmployeeService::class);
            $attritionReduction = $empService->getAggregatedBonus($user, 'attrition_reduction');
            
            // Random chance to block defection based on reduction
            if (rand(0, 100) > ($attritionReduction * 100)) {
                $victimCount = rand(1, 2); // Typically 1-2 engineers leave
                $victims = \App\Models\Employee::where('user_id', $user->id)
                    ->inRandomOrder()
                    ->limit($victimCount)
                    ->get();
                    
                foreach ($victims as $v) {
                    \App\Models\GameLog::log($user, "DEFECTION: {$v->name} ({$v->type}) has accepted an offer from a competitor and resigned!", 'danger', 'hr');
                    $v->delete();
                }
                $user->economy->adjustReputation(-8.0);
            } else {
                \App\Models\GameLog::log($user, "HR VICTORY: Your managers counter-offered effectively. Nobody left!", 'success', 'hr');
            }
        }

        broadcast(new \App\Events\GameEventFailed($user, $event, []));
    }

    private function createBgpEvent(User $user): void
    {
        $deadlineBonus = $this->skillService->getBonus($user, 'deadline_bonus_s');

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::BGP_HIJACKING,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'BGP Hijacking Detected',
            'description' => "Your network prefixes are being announced by an unauthorized ASN (AS1337). Traffic is being rerouted. Reputation loss imminent.",
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(EventType::BGP_HIJACKING->escalationSeconds() + $deadlineBonus),
            'deadline_at' => Carbon::now()->addSeconds(EventType::BGP_HIJACKING->deadlineSeconds() + $deadlineBonus),
            'available_actions' => [
                [
                    'id' => 'alert_upstream',
                    'label' => 'Alert Upstream Provider',
                    'cost' => 5000,
                    'duration' => 60,
                    'description' => 'Contact Tier-1 peers to filter the malicious announcements.',
                    'success_chance' => 75
                ],
                [
                    'id' => 'aggressive_announcement',
                    'label' => 'Aggressive Re-announcement ($1.5k)',
                    'cost' => 1500,
                    'duration' => 30,
                    'description' => 'Attempt to reclaim prefixes by shortening AS-path.',
                    'success_chance' => 45
                ],
            ],
            'affected_customers_count' => $user->rooms()->sum('bandwidth_gbps') * 10, 
            'xp_reward' => 200,
        ]);
    }

    private function applyEscalationConsequences(GameEvent $event): void
    {
        switch ($event->type) {
            case EventType::OVERHEATING:
                if ($event->affected_room_id) {
                    $room = GameRoom::find($event->affected_room_id);
                    $prot = $room ? $room->getRedundancyProtection() : ['prob_reduction' => 0, 'capacity_recovery' => 0];
                    if ($event->affected_rack_id) {
                        $rack = ServerRack::find($event->affected_rack_id);
                        if ($rack) {
                            $damageBase = 15;
                            $actualDamage = $damageBase * (1.0 - $prot['capacity_recovery']);
                            foreach ($rack->servers as $server) {
                                if ($server->status === ServerStatus::ONLINE) {
                                    $server->health = max(0, $server->health - $actualDamage);
                                    if ($server->health < 70) $server->status = ServerStatus::DEGRADED;
                                    $server->save();
                                }
                            }
                            if (rand(0, 100) > ($prot['prob_reduction'] * 100)) {
                                $slot = $rack->position['slot'] ?? 0;
                                $adjacentRacks = ServerRack::where('room_id', $rack->room_id)
                                    ->where('id', '!=', $rack->id)
                                    ->get()
                                    ->filter(function($r) use ($slot) {
                                        $rSlot = $r->position['slot'] ?? -99;
                                        return abs($rSlot - $slot) === 1;
                                    });
                                foreach ($adjacentRacks as $adjRack) {
                                    $adjRack->temperature = min(80, $adjRack->temperature + 10);
                                    $adjRack->save();
                                }
                            }
                        }
                    }
                }
                break;
            case EventType::DDOS_ATTACK:
                if ($event->affected_server_id) {
                    $server = Server::find($event->affected_server_id);
                    if ($server) {
                        $server->health = max(0, $server->health - 15);
                        $server->status = ServerStatus::DEGRADED;
                        $server->save();
                        $user = $server->rack->room->user;
                        \App\Models\GameLog::log($user, "CASCADE: DDOS traffic is masking a possible Security Intrusion!", 'warning', 'security');
                    }
                }
                break;
            case EventType::POWER_OUTAGE:
                if ($event->affected_room_id) {
                    $room = GameRoom::find($event->affected_room_id);
                    if ($room) {
                        $prot = $room->getRedundancyProtection();
                        
                        // --- FEATURE 186: DIESEL BACKUP PROTECTION ---
                        $hasBackup = $room->has_diesel_backup && $room->diesel_fuel_liters > 0;
                        
                        $shutdownChance = 75 * (1.0 - $prot['capacity_recovery']);
                        if ($hasBackup) {
                            $shutdownChance = 5; // 5% chance of transfer failure if diesel is available
                            \App\Models\GameLog::log($room->user, "BACKUP ACTIVE: Diesel generator in {$room->name} kicked in. Servers remain online.", 'success', 'energy');
                        }
                        
                        $damageChance = 10 * (1.0 - $prot['prob_reduction']);
                        $onlineServers = Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))
                            ->where('status', ServerStatus::ONLINE)
                            ->get();
                        foreach ($onlineServers as $server) {
                            if (rand(0, 100) < $shutdownChance) {
                                $server->status = ServerStatus::OFFLINE;
                                if (rand(0, 100) < $damageChance) { 
                                    $server->health = max(0, $server->health - 20);
                                    $server->status = ServerStatus::DAMAGED;
                                }
                                $server->save();
                                $server->rack->recalculatePowerAndHeat();
                            }
                        }
                    }
                }
                break;
            case EventType::SECURITY_BREACH:
                if ($event->affected_server_id) {
                    $server = Server::find($event->affected_server_id);
                    if ($server) {
                        $server->health = max(0, $server->health - 25);
                        $server->save();
                        if (rand(0,100) < 40) {
                             $server->status = ServerStatus::DAMAGED;
                             $server->save();
                             \App\Models\GameLog::log($server->rack->room->user, "SABOTAGE: Intruders have physically damaged Server {$server->model_name} firmware!", 'danger', 'security');
                        }
                        if (rand(0, 100) < 30) {
                            $other = Server::where('rack_id', $server->rack_id)
                                ->where('id', '!=', $server->id)
                                ->where('status', ServerStatus::ONLINE)
                                ->inRandomOrder()
                                ->first();
                            if ($other) {
                                $other->health = max(0, $other->health - 10);
                                $other->status = ServerStatus::DEGRADED;
                                $other->save();
                            }
                        }
                    }
                }
                break;
        }
    }

    private function calculateCrisisScore(GameEvent $event): array
    {
        if ($event->status === EventStatus::FAILED) {
            return ['score' => 0, 'grade' => 'F'];
        }

        $base = 100;
        $totalTime = $event->warning_at->diffInSeconds($event->deadline_at);
        $timeSpent = $event->warning_at->diffInSeconds($event->resolved_at);
        $timeRatio = $totalTime > 0 ? ($timeSpent / $totalTime) : 0;
        $timePenalty = min(40, $timeRatio * 40);

        $costPenalty = min(20, ($event->action_cost ?? 0) / 500);

        $customerPenalty = min(20, ($event->affected_customers_count ?? 0) * 1.5);
        $damagePenalty = min(20, ($event->damage_cost ?? 0) / 100);
        $impactPenalty = $customerPenalty + $damagePenalty;

        $finalScore = (int) max(0, $base - $timePenalty - $costPenalty - $impactPenalty);

        $grade = 'F';
        if ($finalScore >= 95) $grade = 'S';
        elseif ($finalScore >= 85) $grade = 'A';
        elseif ($finalScore >= 70) $grade = 'B';
        elseif ($finalScore >= 50) $grade = 'C';
        elseif ($finalScore >= 30) $grade = 'D';

        return ['score' => $finalScore, 'grade' => $grade];
    }

    private function awardSpecializedReputation(User $user, GameEvent $event, string $outcome): void
    {
        $economy = $user->economy;
        $amount = ($outcome === 'resolution') ? 3.0 : -8.0;

        switch ($event->type) {
            case EventType::SECURITY_BREACH:
            case EventType::DDOS_ATTACK:
            case EventType::NETWORK_FAILURE:
                if ($outcome === 'failure') {
                    $ddosResilience = app(ResearchService::class)->getBonus($user, 'ddos_resilience');
                    $amount *= (1.0 - $ddosResilience);
                }
                $economy->adjustSpecializedReputation('premium', $amount);
                break;
            case EventType::HARDWARE_FAILURE:
            case EventType::OVERHEATING:
            case EventType::POWER_OUTAGE:
                $economy->adjustSpecializedReputation('budget', $amount);
                break;
        }

        $economy->save();
    }

    private function createDataLeak(User $user): void
    {
        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::DATA_LEAK,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'URGENT: Database Dump Found Online',
            'description' => "A large archive containing customer credentials has appeared on a dark web forum. Public relations are in freefall.",
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(EventType::DATA_LEAK->escalationSeconds() + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'deadline_at' => Carbon::now()->addSeconds(EventType::DATA_LEAK->deadlineSeconds() + $this->skillService->getBonus($user, 'deadline_bonus_s')),
            'available_actions' => [
                [
                    'id' => 'full_reset', 
                    'label' => 'Force Global Password Reset ($5k)', 
                    'cost' => 5000, 
                    'duration' => 120,
                    'description' => 'Contain the damage. High success, moderate reputation loss.',
                    'success_chance' => 90
                ],
                [
                    'id' => 'damage_control_pr', 
                    'label' => 'Hire PR Crisis Firm ($15k)', 
                    'cost' => 15000, 
                    'duration' => 30,
                    'description' => 'Spin the story. Potential to turn the negative into a neutral.',
                    'success_chance' => 70
                ]
            ],
            'affected_customers_count' => Customer::where('user_id', $user->id)->count(),
            'xp_reward' => 250,
        ]);
        
        $user->economy->adjustReputation(-5.0);
    }

    public function createPatentLawsuit(User $user, \App\Models\Competitor $c): void
    {
        $fine = min($user->economy->balance * 0.1, 75000); 
        
        $actions = [
            [
                'id' => 'settle',
                'label' => 'Pay Settlement ($' . number_format($fine) . ')',
                'description' => "Accept responsibility and pay the fine. Ends the dispute immediately.",
                'cost' => $fine,
                'success_chance' => 100,
            ],
            [
                'id' => 'fight',
                'label' => 'Defend in Court',
                'description' => "Hire legal team ($" . number_format($fine / 2) . "). 50% win chance. IF YOU LOSE: You must pay $ " . number_format($fine * 3) . " in damages!",
                'cost' => $fine / 2,
                'success_chance' => 50,
            ],
            [
                'id' => 'countersue',
                'label' => 'Aggressive Counter-Suit',
                'description' => "Try to win $" . number_format($fine * 2) . ". High risk, High reward. Requires intense legal power.",
                'cost' => $fine,
                'success_chance' => 30,
            ]
        ];

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::PATENT_LAWSUIT,
            'severity' => 'warning',
            'status' => EventStatus::ACTIVE,
            'title' => 'PATENT_DISPUTE: ' . $c->name,
            'description' => "Your rival '{$c->name}' claims you've stolen their server-rack automation patents. Settle now or see them in court.",
            'available_actions' => $actions,
            'warning_at' => now(),
            'escalates_at' => now()->addMinutes(10),
            'deadline_at' => now()->addMinutes(25),
            'xp_reward' => 200,
        ]);
    }

    public function createHiringRaid(User $user, \App\Models\Competitor $c): void
    {
        $employeeCount = \App\Models\Employee::where('user_id', $user->id)->count();
        if ($employeeCount === 0) return;

        $fine = min($user->economy->balance * 0.1, 75000); 
        
        $actions = [
            [
                'id' => 'counter_offer',
                'label' => 'Strategic Salary Hike (+20% Perm)',
                'description' => "Lock in employees with better terms. Prevents defection but permanently increases overhead.",
                'cost' => 0, 
                'success_chance' => 100,
            ],
            [
                'id' => 'loyalty_bonus',
                'label' => 'One-Time Loyalty Bonus',
                'description' => "Pay a significant lump sum ($1,500/employee) to keep them for now.",
                'cost' => $employeeCount * 1500,
                'success_chance' => 85,
            ],
            [
                'id' => 'ignore_raid',
                'label' => 'Do Nothing',
                'description' => "Hope their loyalty holds. HIGH probability of losing 1-2 random employees.",
                'cost' => 0,
                'success_chance' => 15,
            ]
        ];

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::HIRING_RAID,
            'severity' => 'medium',
            'status' => EventStatus::ACTIVE,
            'title' => 'HR EMERGENCY: ' . $c->name . ' Poaching Staff!',
            'description' => "Headhunters from '{$c->name}' are contacting your staff. Your best engineers are considering their offers.",
            'available_actions' => $actions,
            'warning_at' => now(),
            'escalates_at' => now()->addMinutes(5),
            'deadline_at' => now()->addMinutes(15),
            'xp_reward' => 100,
        ]);
    }
    public function createPriceWar(User $user, \App\Models\Competitor $c): void
    {
        $actions = [
            [
                'id' => 'match_pricing',
                'label' => 'Aggressive Price Match (-30%)',
                'description' => "Temporarily slash your prices by 30% to match {$c->name}. Stops market share loss but tanks short-term revenue.",
                'cost' => 0, 
                'success_chance' => 95,
            ],
            [
                'id' => 'marketing_counter',
                'label' => 'Quality PR Campaign ($8,000)',
                'description' => "Run ads focusing on your superior reliability and support. Reduces share loss without cutting prices.",
                'cost' => 8000,
                'success_chance' => 75,
            ],
            [
                'id' => 'ignore_pricing',
                'label' => 'Ignore & Maintain Margins',
                'description' => "Keep your prices steady. Risk significant market share loss to {$c->name}.",
                'cost' => 0,
                'success_chance' => 20,
            ]
        ];

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::PRICE_WAR,
            'severity' => 'medium',
            'status' => EventStatus::ACTIVE,
            'title' => 'MARKET THREAT: Price War vs ' . $c->name,
            'description' => "{$c->name} has started an aggressive campaign to undercut your services. They are luring your customers away with unsustainable bottom-tier pricing.",
            'available_actions' => $actions,
            'warning_at' => now(),
            'escalates_at' => now()->addMinutes(8),
            'deadline_at' => now()->addMinutes(20),
            'xp_reward' => 100,
        ]);
    }

    private function createFiberCutEvent(User $user): void
    {
        $room = GameRoom::where('user_id', $user->id)
            ->whereHas('racks.servers')
            ->inRandomOrder()
            ->first();

        if (!$room) return;

        $deadlineBonus = $this->skillService->getBonus($user, 'deadline_bonus_s');

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::FIBER_CUT,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'Backhaul Fiber Cut!',
            'description' => "A major fiber optic line servicing Room '{$room->name}' has been severed by a construction crew. Connectivity is severely limited.",
            'affected_room_id' => $room->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(EventType::FIBER_CUT->escalationSeconds() + $deadlineBonus),
            'deadline_at' => Carbon::now()->addSeconds(EventType::FIBER_CUT->deadlineSeconds() + $deadlineBonus),
            'damage_cost' => 0,
            'available_actions' => [
                [
                    'id' => 'reroute_traffic',
                    'label' => 'Reroute via MPLS ($2,500)',
                    'cost' => 2500,
                    'duration' => 120,
                    'description' => 'Manually adjust BGP weights and reroute traffic through auxiliary backhaul.',
                    'success_chance' => 95
                ],
                [
                    'id' => 'failover_satellite',
                    'label' => 'Activate Satellite Failover ($5,000)',
                    'cost' => 5000,
                    'duration' => 30,
                    'description' => 'Switch to high-latency emergency satellite link. 100% success but high recurring cost.',
                    'success_chance' => 100
                ],
                [
                    'id' => 'legal_demand',
                    'label' => 'Legal Demand to Provider ($500)',
                    'cost' => 500,
                    'duration' => 600,
                    'description' => 'Demand immediate priority repair. Slow and low success chance.',
                    'success_chance' => 30
                ],
            ],
            'affected_customers_count' => Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))->withCount('activeOrders')->get()->sum('active_orders_count'),
            'xp_reward' => 200,
        ]);
    }
}
