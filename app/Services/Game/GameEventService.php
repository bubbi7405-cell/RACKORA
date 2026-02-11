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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GameEventService
{
    /**
     * Process game events for a user (called every tick)
     */
    public function tick(User $user): void
    {
        // 1. Process active events (check timestamps, auto-fail, escalate)
        $this->processActiveEvents($user);

        // 2. Chance to generate new event
        // Base chance: 1% per minute (assuming tick is 1 min)
        // With many servers, chance increases slightly
        $serverCount = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))->count();
        $baseChance = 2 + ($serverCount * 0.1); 
        $maxChance = 20; // Cap at 20%
        
        $chance = min($baseChance, $maxChance);

        // Check active events count cap (max 3 at a time)
        $activeCount = GameEvent::where('user_id', $user->id)
            ->whereIn('status', [EventStatus::WARNING, EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->count();

        if ($activeCount < 3 && rand(0, 100) < $chance) {
            $this->generateEvent($user);
        }
    }

    private function processActiveEvents(User $user): void
    {
        $events = GameEvent::where('user_id', $user->id)
            ->whereIn('status', [EventStatus::WARNING, EventStatus::ACTIVE, EventStatus::ESCALATED])
            ->get();

        foreach ($events as $event) {
            $now = Carbon::now();

            // Transition Warning -> Active
            if ($event->status === EventStatus::WARNING && $event->warning_at && $now->gte($event->warning_at)) {
                $event->status = EventStatus::ACTIVE;
                $event->save();
                // Notify user (WebSocket)
            }

            // Transition Active -> Escalated
            if ($event->status === EventStatus::ACTIVE && $event->shouldEscalate()) {
                $event->escalate();
                // Apply escalation consequences (e.g. damage spread)
            }

            // Deadline check -> Fail
            if ($event->shouldAutoFail()) {
                $this->failEvent($event);
            }
        }
    }

    private function generateEvent(User $user): void
    {
        // Select event type based on probability
        $types = [
            EventType::HARDWARE_FAILURE->value => 40,
            EventType::DDOS_ATTACK->value => 30,
            EventType::OVERHEATING->value => 15,
            EventType::SECURITY_BREACH->value => 10,
            EventType::POWER_OUTAGE->value => 5,
        ];

        $rand = rand(1, 100);
        $cumul = 0;
        $selectedType = EventType::HARDWARE_FAILURE;

        foreach ($types as $typeValue => $prob) {
            $cumul += $prob;
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
            default:
                $this->createHardwareFailure($user);
        }
    }

    private function createHardwareFailure(User $user): void
    {
        // Find a random online server
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->inRandomOrder()
            ->first();

        if (!$server) return;

        $event = GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::HARDWARE_FAILURE,
            'severity' => 'medium',
            'status' => EventStatus::WARNING,
            'title' => 'Hardware Malfunction Detected',
            'description' => "Server {$server->model_name} in Rack {$server->rack->name} is reporting drive errors.",
            'affected_room_id' => $server->rack->room_id,
            'affected_rack_id' => $server->rack_id,
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->warningSeconds()),
            'escalates_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->escalationSeconds()),
            'deadline_at' => Carbon::now()->addSeconds(EventType::HARDWARE_FAILURE->deadlineSeconds()),
            'damage_cost' => $server->purchase_cost * 0.1, // Repair cost estimate
            'available_actions' => [
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
                    'label' => 'Replace Drive ($200)', 
                    'cost' => 200, 
                    'duration' => 120,
                    'description' => 'Send a technician to replace the failing drive.',
                    'success_chance' => 100
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
        ]);

        // Immediate effect
        $server->status = ServerStatus::DEGRADED;
        $server->health -= 20;
        $server->save();
    }

    private function createDdosAttack(User $user): void
    {
        // Find a server with active orders
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('activeOrders')
            ->inRandomOrder()
            ->first();

        if (!$server) return;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::DDOS_ATTACK,
            'severity' => 'high',
            'status' => EventStatus::ACTIVE, // Starts immediately
            'title' => 'DDoS Attack Inbound',
            'description' => "Massive traffic spike targeting Server {$server->model_name}. Network saturation imminent.",
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now(), // Now
            'escalates_at' => Carbon::now()->addSeconds(EventType::DDOS_ATTACK->escalationSeconds()),
            'deadline_at' => Carbon::now()->addSeconds(EventType::DDOS_ATTACK->deadlineSeconds()),
            'damage_cost' => 0,
            'available_actions' => [
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
                    'label' => 'Premium DDoS Protection ($500)', 
                    'cost' => 500, 
                    'duration' => 60,
                    'description' => 'Route traffic through scrubbing center.',
                    'success_chance' => 100
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
        ]);
    }
    
    public function createOverheatEvent(User $user, ?ServerRack $triggerRack = null): void
    {
        $room = null;
        if ($triggerRack) {
            $room = $triggerRack->room;
        } else {
            $room = GameRoom::where('user_id', $user->id)->first();
        }
        if (!$room) return;

        $title = $triggerRack 
            ? "Overheating: {$triggerRack->name} at {$triggerRack->temperature}°C"
            : 'Cooling System Warning';

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::OVERHEATING,
            'severity' => 'critical',
            'status' => EventStatus::WARNING,
            'title' => $title,
            'description' => "Temperature rising in {$room->name}. Cooling unit malfunction.",
            'affected_room_id' => $room->id,
            'affected_rack_id' => $triggerRack?->id,
            'warning_at' => Carbon::now()->addSeconds(30),
            'escalates_at' => Carbon::now()->addSeconds(180),
            'deadline_at' => Carbon::now()->addSeconds(600),
            'available_actions' => [
                [
                    'id' => 'boost_cooling', 
                    'label' => 'Emergency Cooling Boost ($100)', 
                    'cost' => 100, 
                    'duration' => 30,
                    'description' => 'Overdrive remaining units. Higher energy cost.',
                    'success_chance' => 80
                ],
                [
                    'id' => 'repair_ac', 
                    'label' => 'Repair AC Unit ($1000)', 
                    'cost' => 1000, 
                    'duration' => 120,
                    'description' => 'Full repair of the failed unit.',
                    'success_chance' => 100
                ],
            ],
            'affected_customers_count' => 0,
        ]);
    }

    public function createPowerOutage(User $user, ?GameRoom $triggerRoom = null): void
    {
        $room = $triggerRoom ?? GameRoom::where('user_id', $user->id)
            ->whereHas('racks.servers', fn($q) => $q->where('status', ServerStatus::ONLINE))
            ->first();
        
        if (!$room) return;

        // Count affected servers
        $affectedServers = Server::whereHas('rack', fn($q) => $q->where('room_id', $room->id))
            ->where('status', ServerStatus::ONLINE)
            ->count();

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::POWER_OUTAGE,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'Power Grid Instability',
            'description' => "Power fluctuations detected in {$room->name}. {$affectedServers} servers at risk of going offline.",
            'affected_room_id' => $room->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(180),
            'deadline_at' => Carbon::now()->addSeconds(480),
            'available_actions' => [
                [
                    'id' => 'switch_backup',
                    'label' => 'Switch to Backup Power ($300)',
                    'cost' => 300,
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
            ],
            'affected_customers_count' => $affectedServers,
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
            'warning_at' => Carbon::now()->addSeconds(60),
            'escalates_at' => Carbon::now()->addSeconds(300),
            'deadline_at' => Carbon::now()->addSeconds(600),
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
        ]);
    }

    private function createSecurityBreach(User $user): void
    {
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->where('status', ServerStatus::ONLINE)
            ->inRandomOrder()
            ->first();

        if (!$server) return;

        GameEvent::create([
            'user_id' => $user->id,
            'type' => EventType::SECURITY_BREACH,
            'severity' => 'critical',
            'status' => EventStatus::ACTIVE,
            'title' => 'Security Alert: Unauthorized Access',
            'description' => "Suspicious activity detected on Server {$server->model_name}. Possible intrusion attempt.",
            'affected_server_id' => $server->id,
            'warning_at' => Carbon::now(),
            'escalates_at' => Carbon::now()->addSeconds(120),
            'deadline_at' => Carbon::now()->addSeconds(360),
            'available_actions' => [
                [
                    'id' => 'isolate_server',
                    'label' => 'Isolate Server (Free)',
                    'cost' => 0,
                    'duration' => 15,
                    'description' => 'Take server offline and isolate. Stops the breach but kills all services.',
                    'success_chance' => 100,
                ],
                [
                    'id' => 'active_defense',
                    'label' => 'Active Defense ($800)',
                    'cost' => 800,
                    'duration' => 180,
                    'description' => 'Deploy countermeasures while keeping services running. Risky.',
                    'success_chance' => 60,
                ],
            ],
            'affected_customers_count' => $server->activeOrders()->count(),
        ]);
    }

    public function resolveEvent(User $user, GameEvent $event, string $actionId): array
    {
        if ($event->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if ($event->status->isResolved()) {
            return ['success' => false, 'error' => 'Event already resolved'];
        }

        // Find action config
        $actions = $event->available_actions;
        $action = null;
        foreach ($actions as $a) {
            if ($a['id'] === $actionId) {
                $action = $a;
                break;
            }
        }

        if (!$action) {
            return ['success' => false, 'error' => 'Invalid action'];
        }

        // Check costs
        if ($user->economy->balance < $action['cost']) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }

        return DB::transaction(function () use ($user, $event, $action) {
            // Debit cost
            if ($action['cost'] > 0) {
                if (!$user->economy->debit($action['cost'], "Event Action: {$action['label']}", 'event_mitigation', $event)) {
                     throw new \Exception("Insufficient funds.");
                }
            }

            // Determine outcome
            $success = rand(0, 100) < $action['success_chance'];

            if ($success) {
                $event->resolve($action['id'], ['message' => 'Action successful. Incident resolved.']);
                
                // Restore server state if needed
                if ($event->affected_server_id) {
                    $server = $event->affectedServer;
                    if ($server) {
                        if (in_array($event->type, [EventType::HARDWARE_FAILURE, EventType::NETWORK_FAILURE])) {
                            $server->health = min(100, $server->health + 30);
                            $server->status = ServerStatus::ONLINE;
                            $server->save();
                        }
                    }
                }
                
                return ['success' => true, 'message' => 'Event resolved successfully.', 'outcome' => 'success'];
            } else {
                return ['success' => true, 'message' => 'Action failed! The problem persists.', 'outcome' => 'failed'];
            }
        });
    }

    private function failEvent(GameEvent $event): void
    {
        $event->fail(['message' => 'Deadline exceeded. Major impact suffered.']);
        
        // Load user if not loaded
        $user = User::find($event->user_id);
        if (!$user || !$user->economy) return;
        
        // Reputation hit
        $user->economy->adjustReputation(-5);
        
        // If server affected, damage it
        if ($event->affected_server_id) {
            $server = Server::find($event->affected_server_id);
            if ($server) {
                $server->status = ServerStatus::DAMAGED;
                $server->health = max(0, $server->health - 30);
                $server->save();
            }
        }
    }
}
