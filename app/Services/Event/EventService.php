<?php

namespace App\Services\Event;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\ServerStatus;
use App\Events\GameEventEscalated;
use App\Events\GameEventFailed;
use App\Events\GameEventResolved;
use App\Events\GameEventStarted;
use App\Models\Customer;
use App\Models\GameEvent;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventService
{
    /**
     * Trigger a new game event
     */
    public function triggerEvent(
        User $user,
        EventType $type,
        ?GameRoom $room = null,
        ?ServerRack $rack = null,
        ?Server $server = null
    ): GameEvent {
        $now = Carbon::now();

        $event = GameEvent::create([
            'user_id' => $user->id,
            'type' => $type,
            'severity' => 'warning',
            'status' => EventStatus::WARNING,
            'title' => $type->label(),
            'description' => $type->description(),
            'affected_room_id' => $room?->id,
            'affected_rack_id' => $rack?->id,
            'affected_server_id' => $server?->id,
            'available_actions' => $this->getActionsForEvent($type),
            'warning_at' => $now,
            'escalates_at' => $now->copy()->addSeconds($type->warningSeconds()),
            'deadline_at' => $now->copy()->addSeconds($type->deadlineSeconds()),
            'affected_customers_count' => $this->countAffectedCustomers($user, $room, $rack, $server),
        ]);

        // Broadcast to player
        broadcast(new GameEventStarted($user, $event))->toOthers();

        return $event;
    }

    /**
     * Process an event escalation (called by scheduler or when time passes)
     */
    public function processEscalation(GameEvent $event): void
    {
        if (!$event->shouldEscalate()) {
            return;
        }

        $event->escalate();

        // Apply intermediate damage
        $this->applyEscalationEffects($event);

        broadcast(new GameEventEscalated($event->user, $event))->toOthers();
    }

    /**
     * Process event auto-fail (deadline passed)
     */
    public function processAutoFail(GameEvent $event): void
    {
        if (!$event->shouldAutoFail()) {
            return;
        }

        $consequences = $this->calculateFailureConsequences($event);
        $event->fail($consequences);

        // Apply full damage
        $this->applyFailureEffects($event, $consequences);

        broadcast(new GameEventFailed($event->user, $event, $consequences))->toOthers();
    }

    /**
     * Player resolves an event with chosen action
     */
    public function resolveEvent(User $user, GameEvent $event, string $actionId): array
    {
        if ($event->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if (!$event->status->isActive()) {
            return ['success' => false, 'error' => 'Event is not active'];
        }

        $action = collect($event->available_actions)->firstWhere('id', $actionId);
        if (!$action) {
            return ['success' => false, 'error' => 'Invalid action'];
        }

        // Check if player can afford
        $economy = $user->economy;
        if (!$economy->canAfford($action['cost'])) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }

        // Roll for success
        $roll = rand(1, 100);
        $success = $roll <= $action['successRate'];

        return DB::transaction(function () use ($user, $event, $action, $success, $economy) {
            // Deduct cost
            $economy->debit($action['cost'], "Event response: {$action['name']}");

            if ($success) {
                $consequences = [
                    'action' => $action['name'],
                    'result' => 'success',
                    'damagePrevented' => true,
                ];
                $event->resolve($action['id'], $consequences);

                // Award XP for successful resolution
                $xpReward = match($event->severity) {
                    'warning' => 10,
                    'critical' => 25,
                    'catastrophic' => 50,
                    default => 10,
                };
                $economy->addExperience($xpReward);
                $economy->adjustReputation(2);

                broadcast(new GameEventResolved($user, $event, $consequences))->toOthers();

                return [
                    'success' => true,
                    'resolution' => 'success',
                    'event' => $event->fresh(),
                    'xpEarned' => $xpReward,
                ];
            } else {
                // Action failed - escalate immediately
                if ($event->status !== EventStatus::ESCALATED) {
                    $event->escalate();
                    $this->applyEscalationEffects($event);
                }

                return [
                    'success' => true, // The action was attempted
                    'resolution' => 'failed',
                    'event' => $event->fresh(),
                    'message' => 'The action failed! The situation has escalated.',
                ];
            }
        });
    }

    /**
     * Get available actions for an event type
     */
    private function getActionsForEvent(EventType $type): array
    {
        return match($type) {
            EventType::POWER_OUTAGE => [
                [
                    'id' => 'emergency_generator',
                    'name' => 'Activate Emergency Generator',
                    'cost' => 500,
                    'successRate' => 95,
                    'description' => 'Switch to backup power immediately',
                ],
                [
                    'id' => 'call_utility',
                    'name' => 'Call Utility Company',
                    'cost' => 100,
                    'successRate' => 60,
                    'description' => 'Request priority power restoration',
                ],
                [
                    'id' => 'wait_it_out',
                    'name' => 'Wait It Out',
                    'cost' => 0,
                    'successRate' => 30,
                    'description' => 'Hope power comes back soon',
                ],
            ],
            EventType::OVERHEATING => [
                [
                    'id' => 'emergency_cooling',
                    'name' => 'Emergency Cooling Boost',
                    'cost' => 300,
                    'successRate' => 90,
                    'description' => 'Increase cooling power dramatically',
                ],
                [
                    'id' => 'shutdown_servers',
                    'name' => 'Shut Down Non-Critical Servers',
                    'cost' => 0,
                    'successRate' => 85,
                    'description' => 'Reduce heat by shutting down servers',
                ],
                [
                    'id' => 'open_doors',
                    'name' => 'Open All Doors',
                    'cost' => 0,
                    'successRate' => 40,
                    'description' => 'Risky but free cooling option',
                ],
            ],
            EventType::DDOS_ATTACK => [
                [
                    'id' => 'mitigation_service',
                    'name' => 'Activate DDoS Mitigation',
                    'cost' => 800,
                    'successRate' => 92,
                    'description' => 'Route traffic through protection service',
                ],
                [
                    'id' => 'null_route',
                    'name' => 'Null Route Attack Traffic',
                    'cost' => 50,
                    'successRate' => 70,
                    'description' => 'Block attack but may affect legitimate traffic',
                ],
                [
                    'id' => 'absorb',
                    'name' => 'Absorb The Attack',
                    'cost' => 0,
                    'successRate' => 25,
                    'description' => 'Try to handle it with existing capacity',
                ],
            ],
            EventType::NETWORK_FAILURE => [
                [
                    'id' => 'failover_isp',
                    'name' => 'Switch to Backup ISP',
                    'cost' => 200,
                    'successRate' => 95,
                    'description' => 'Activate redundant internet connection',
                ],
                [
                    'id' => 'contact_provider',
                    'name' => 'Contact Network Provider',
                    'cost' => 0,
                    'successRate' => 50,
                    'description' => 'Report issue and wait for fix',
                ],
            ],
            EventType::HARDWARE_FAILURE => [
                [
                    'id' => 'hot_swap',
                    'name' => 'Hot Swap Component',
                    'cost' => 400,
                    'successRate' => 85,
                    'description' => 'Replace failed component without downtime',
                ],
                [
                    'id' => 'emergency_repair',
                    'name' => 'Emergency Repair',
                    'cost' => 150,
                    'successRate' => 65,
                    'description' => 'Attempt to repair the failed component',
                ],
                [
                    'id' => 'migrate_services',
                    'name' => 'Migrate Services',
                    'cost' => 100,
                    'successRate' => 75,
                    'description' => 'Move workloads to other servers',
                ],
            ],
            EventType::SECURITY_BREACH => [
                [
                    'id' => 'isolate_network',
                    'name' => 'Isolate Affected Systems',
                    'cost' => 0,
                    'successRate' => 88,
                    'description' => 'Quarantine compromised systems',
                ],
                [
                    'id' => 'security_team',
                    'name' => 'Engage Security Team',
                    'cost' => 1000,
                    'successRate' => 95,
                    'description' => 'Professional incident response',
                ],
                [
                    'id' => 'full_shutdown',
                    'name' => 'Full System Shutdown',
                    'cost' => 0,
                    'successRate' => 99,
                    'description' => 'Shut everything down - stops attack but maximum downtime',
                ],
            ],
        };
    }

    /**
     * Apply effects when event escalates
     */
    private function applyEscalationEffects(GameEvent $event): void
    {
        // Damage affected servers
        if ($event->affected_server_id) {
            $server = $event->affectedServer;
            if ($server) {
                $server->health = max(0, $server->health - 15);
                if ($server->health < 50) {
                    $server->status = ServerStatus::DEGRADED;
                }
                $server->save();
            }
        } elseif ($event->affected_rack_id) {
            // Damage all servers in rack
            $rack = $event->affectedRack;
            if ($rack) {
                foreach ($rack->servers as $server) {
                    $server->health = max(0, $server->health - 10);
                    $server->save();
                }
            }
        }

        // Notify affected customers
        $this->notifyAffectedCustomers($event, 'escalated');
    }

    /**
     * Apply effects when event fails
     */
    private function applyFailureEffects(GameEvent $event, array $consequences): void
    {
        $economy = $event->user->economy;
        $damageCost = $consequences['damageCost'] ?? 0;

        // Debit damage cost if player has funds
        if ($damageCost > 0 && $economy->canAfford($damageCost)) {
            $economy->debit($damageCost, "Event damage: {$event->type->label()}");
        }

        // Reputation hit
        $economy->adjustReputation(-10);

        // Damage/offline affected equipment
        if ($event->affected_server_id) {
            $server = $event->affectedServer;
            if ($server) {
                $server->health = max(0, $server->health - 40);
                $server->status = ServerStatus::DAMAGED;
                $server->save();
            }
        } elseif ($event->affected_rack_id) {
            $rack = $event->affectedRack;
            if ($rack) {
                $rack->status = 'damaged';
                $rack->save();
                foreach ($rack->servers as $server) {
                    $server->health = max(0, $server->health - 25);
                    $server->status = ServerStatus::OFFLINE;
                    $server->save();
                }
            }
        }

        // Register incidents with affected customers
        $this->notifyAffectedCustomers($event, 'failed');
    }

    /**
     * Calculate consequences for failure
     */
    private function calculateFailureConsequences(GameEvent $event): array
    {
        $baseDamage = match($event->type) {
            EventType::POWER_OUTAGE => 2000,
            EventType::OVERHEATING => 3000,
            EventType::DDOS_ATTACK => 1500,
            EventType::NETWORK_FAILURE => 1000,
            EventType::HARDWARE_FAILURE => 4000,
            EventType::SECURITY_BREACH => 5000,
        };

        // Multiply by severity
        $multiplier = match($event->severity) {
            'warning' => 1.0,
            'critical' => 1.5,
            'catastrophic' => 2.5,
            default => 1.0,
        };

        $damageCost = $baseDamage * $multiplier;

        return [
            'damageCost' => $damageCost,
            'affectedCustomers' => $event->affected_customers_count,
            'serversAffected' => $this->countAffectedServers($event),
            'downtimeMinutes' => rand(15, 120),
        ];
    }

    /**
     * Count affected customers
     */
    private function countAffectedCustomers(User $user, ?GameRoom $room, ?ServerRack $rack, ?Server $server): int
    {
        // Count active orders on affected equipment
        if ($server) {
            return $server->activeOrders->count();
        }

        if ($rack) {
            return $rack->servers->sum(fn($s) => $s->activeOrders->count());
        }

        if ($room) {
            $count = 0;
            foreach ($room->racks as $r) {
                $count += $r->servers->sum(fn($s) => $s->activeOrders->count());
            }
            return $count;
        }

        return 0;
    }

    /**
     * Count affected servers
     */
    private function countAffectedServers(GameEvent $event): int
    {
        if ($event->affected_server_id) {
            return 1;
        }
        if ($event->affected_rack_id) {
            return $event->affectedRack?->servers->count() ?? 0;
        }
        if ($event->affected_room_id) {
            $room = $event->affectedRoom;
            if ($room) {
                return $room->racks->sum(fn($r) => $r->servers->count());
            }
        }
        return 0;
    }

    /**
     * Notify customers of event
     */
    private function notifyAffectedCustomers(GameEvent $event, string $eventType): void
    {
        // Find affected customers through their active orders
        $affectedOrders = collect();

        if ($event->affected_server_id) {
            $affectedOrders = $event->affectedServer?->activeOrders ?? collect();
        } elseif ($event->affected_rack_id) {
            $rack = $event->affectedRack;
            if ($rack) {
                foreach ($rack->servers as $server) {
                    $affectedOrders = $affectedOrders->merge($server->activeOrders);
                }
            }
        }

        // Register incidents
        $customerIds = $affectedOrders->pluck('customer_id')->unique();
        Customer::whereIn('id', $customerIds)->each(function ($customer) {
            $customer->registerIncident();
        });
    }
}
