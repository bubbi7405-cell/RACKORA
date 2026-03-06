<?php

namespace App\Services\Game;

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\GameLog;
use Illuminate\Support\Facades\Log;

class TrafficOrchestratorService
{
    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * Main tick for the Smart Traffic Orchestrator
     */
    public function tick(User $user): void
    {
        // 1. Requirement Check: Anycast Research + Level 30
        if (!$this->isUnlocked($user)) {
            return;
        }

        // 2. Setting Check: Is Automation Enabled?
        if (!$user->economy->isAutomationEnabled('smart_traffic_orchestrator')) {
            return;
        }

        // 3. Logic: Find customers who would benefit from migration
        $this->processMigrations($user);
    }

    public function isUnlocked(User $user): bool
    {
        $level = $user->economy->level ?? 1;
        $hasAnycast = $this->researchService->hasEffect($user, 'unlock_network_tier', 3); // Anycast
        
        return $level >= 30 && $hasAnycast;
    }

    /**
     * Process potential migrations for all active customers
     */
    private function processMigrations(User $user): void
    {
        $customers = Customer::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['activeOrders.assignedServer.rack.room'])
            ->get();

        foreach ($customers as $customer) {
            foreach ($customer->activeOrders as $order) {
                $this->evaluateMigration($user, $order);
            }
        }
    }

    /**
     * Evaluate if an order should be migrated to another server/region
     */
    private function evaluateMigration(User $user, CustomerOrder $order): void
    {
        $currentServer = $order->assignedServer;
        if (!$currentServer || !$currentServer->rack || !$currentServer->rack->room) {
            return;
        }

        $currentRoom = $currentServer->rack->room;
        $customer = $order->customer;
        $targetRegion = $customer->preferences['target_region'] ?? $currentRoom->region;

        // 1. Scoring current placement
        $currentScore = $this->calculateScore($order, $currentRoom);

        // 2. Look for better options
        $bestOption = [
            'score' => $currentScore,
            'server' => $currentServer,
            'room' => $currentRoom
        ];

        // We only scan a subset of rooms/servers to avoid O(N^2) in the loop if the player has hundreds
        $candidateRooms = GameRoom::where('user_id', $user->id)
            ->where('is_unlocked', true)
            ->get();

        foreach ($candidateRooms as $room) {
            // Region/Latency Check: Only consider rooms in target region or with better latency
            $roomLatency = $room->latency_ms ?? 100;
            $maxAllowedLatency = $order->requirements['max_latency_ms'] ?? 150;
            
            // Skip rooms that would definitely violate SLA
            if ($roomLatency > $maxAllowedLatency) {
                continue;
            }

            $roomScore = $this->calculateScore($order, $room);

            if ($roomScore > $bestOption['score'] + 10) { // Require a 10% "improvement buffer" to avoid flapping
                // Find an available server in this room
                $newServer = $this->findAvailableServerInRoom($room, $order);
                if ($newServer) {
                    $bestOption = [
                        'score' => $roomScore,
                        'server' => $newServer,
                        'room' => $room
                    ];
                }
            }
        }

        // 3. Execution
        if ($bestOption['server']->id !== $currentServer->id) {
            $this->migrateOrder($user, $order, $bestOption['server']);
        }
    }

    /**
     * Score a room for a specific order (Balance Latency vs Cost)
     * Higher is better
     */
    private function calculateScore(CustomerOrder $order, GameRoom $room): float
    {
        $score = 100.0;

        // Penalty for wrong region
        $customerRegion = $order->customer->preferences['target_region'] ?? null;
        if ($customerRegion && $room->region !== $customerRegion) {
            $score -= 30;
        }

        // Latency Impact (SLA satisfaction)
        $maxLatency = $order->requirements['max_latency_ms'] ?? 150;
        $currentLatency = $room->latency_ms ?? 100;
        
        $latencyRatio = $currentLatency / $maxLatency;
        if ($latencyRatio < 0.5) $score += 20; // Perfect latency
        elseif ($latencyRatio > 0.9) $score -= 40; // Danger zone

        // Cost Impact (Profitability)
        $powerCost = (float) $room->power_cost_kwh;
        // Average power cost is around 0.15. 
        // We normalize: lower is better.
        $costFactor = ($powerCost - 0.05) / 0.20; // 0.05 = cheap, 0.25 = expensive
        $score -= ($costFactor * 30); // Up to 30 points penalty for high power costs

        // Temperature/Stability
        if ($room->isOverheating()) {
            $score -= 50;
        }

        return $score;
    }

    private function findAvailableServerInRoom(GameRoom $room, CustomerOrder $order): ?Server
    {
        foreach ($room->racks as $rack) {
            foreach ($rack->servers as $server) {
                if ($server->canHostVserver() && $server->getAvailableVserverSlots() > 0) {
                    // Check if server is online
                    if ($server->status === \App\Enums\ServerStatus::ONLINE) {
                        return $server;
                    }
                }
            }
        }
        return null;
    }

    private function migrateOrder(User $user, CustomerOrder $order, Server $newServer): void
    {
        $oldRoom = $order->assignedServer->rack->room;
        $newRoom = $newServer->rack->room;
        
        Log::info("Smart Orchestrator: Migrating order {$order->id} from {$oldRoom->name} to {$newRoom->name}");
        
        $order->assigned_server_id = $newServer->id;
        $order->save();

        // Increment counter in user metadata for stats/achievements
        $metadata = $user->economy->metadata ?? [];
        $metadata['auto_migrations_count'] = ($metadata['auto_migrations_count'] ?? 0) + 1;
        $user->economy->metadata = $metadata;
        $user->economy->save();

        if (rand(1, 20) === 1) {
            GameLog::log($user, "Smart Orchestrator: Optimized {$order->customer->company_name} to {$newRoom->region} for better latency.", 'info', 'automation');
        }
    }
}
