<?php

namespace App\Services\Game;

use App\Enums\RoomType;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\GameEvent;
use App\Models\GameRoom;
use App\Models\PlayerEconomy;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\User;
use Illuminate\Support\Collection;

class GameStateService
{
    /**
     * Get the complete authoritative game state for a player
     */
    public function getFullState(User $user): array
    {
        \Log::info('getFullState started for user: ' . $user->id);
        $economy = $user->economy ?? $this->initializePlayer($user);
        
        // Recalculate time-based values before returning state
        $this->recalculateTimeBased($user);

        \Log::info('Fetching rooms...');
        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['racks.servers.activeOrders'])
            ->get();
        \Log::info('Rooms fetched: ' . $rooms->count());

        \Log::info('Fetching customers...');
        $customers = Customer::where('user_id', $user->id)
            ->whereIn('status', ['active', 'unhappy', 'churning'])
            ->with(['orders', 'activeOrders', 'pendingOrders'])
            ->get();
        \Log::info('Customers fetched: ' . $customers->count());

        \Log::info('Fetching pending orders...');
        $pendingOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'pending')
            ->with('customer')
            ->orderBy('patience_expires_at')
            ->get();
        \Log::info('Pending orders fetched: ' . $pendingOrders->count());

        $activeEvents = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['warning', 'active', 'escalated'])
            ->orderBy('deadline_at')
            ->get();

        return [
            'timestamp' => now()->toIso8601String(),
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'economy' => $economy->toGameState(),
            ],
            'rooms' => $rooms->map(fn($room) => $room->toGameState())->keyBy('id')->toArray(),
            'customers' => [
                'total' => $customers->count(),
                'active' => $customers->where('status', 'active')->count(),
                'unhappy' => $customers->where('status', 'unhappy')->count(),
                'churning' => $customers->where('status', 'churning')->count(),
                'list' => $customers->map(fn($c) => $c->toGameState())->toArray(),
            ],
            'orders' => [
                'pending' => $pendingOrders->map(fn($o) => $o->toGameState())->toArray(),
                'provisioning' => CustomerOrder::whereHas('customer', fn($q) => $q->where('user_id', $user->id))
                    ->where('status', 'provisioning')
                    ->with('customer')
                    ->get()
                    ->map(fn($o) => $o->toGameState())
                    ->toArray(),
                'urgentCount' => $pendingOrders->filter(fn($o) => $o->getPatienceProgress() > 70)->count(),
            ],
            'events' => [
                'active' => $activeEvents->map(fn($e) => $e->toGameState())->toArray(),
                'hasWarnings' => $activeEvents->where('status', 'warning')->isNotEmpty(),
                'hasCritical' => $activeEvents->whereIn('severity', ['critical', 'catastrophic'])->isNotEmpty(),
            ],
            'stats' => $this->calculateStats($user, $rooms, $customers),
        ];
    }

    /**
     * Initialize a new player with starting resources
     */
    public function initializePlayer(User $user): PlayerEconomy
    {
        // Create economy
        $economy = PlayerEconomy::create([
            'user_id' => $user->id,
            'balance' => 5000.00,
            'reputation' => 50.0,
            'level' => 1,
            'last_income_tick' => now(),
        ]);

        // Create starting basement room
        $basement = GameRoom::create([
            'user_id' => $user->id,
            'type' => RoomType::BASEMENT,
            'name' => 'Home Basement',
            'level' => 1,
            'max_racks' => RoomType::BASEMENT->maxRacks(),
            'max_power_kw' => RoomType::BASEMENT->maxPowerKw(),
            'max_cooling_kw' => RoomType::BASEMENT->maxCoolingKw(),
            'bandwidth_gbps' => RoomType::BASEMENT->bandwidthGbps(),
            'rent_per_hour' => RoomType::BASEMENT->rentPerHour(),
            'is_unlocked' => true,
            'unlocked_at' => now(),
            'position' => ['x' => 0, 'y' => 0],
        ]);

        return $economy;
    }

    /**
     * Recalculate time-based values (income, provisioning, etc.)
     */
    public function recalculateTimeBased(User $user): void
    {
        // Check for completed provisioning
        $provisioningServers = Server::whereHas('rack.room', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'provisioning')->get();

        foreach ($provisioningServers as $server) {
            if ($server->isProvisioningComplete()) {
                $server->completeProvisioning();
                
                // Recalculate rack power/heat
                $server->rack->recalculatePowerAndHeat();
            }
        }

        // Calculate hourly income/expenses
        $this->recalculateEconomy($user);
    }

    /**
     * Recalculate hourly income and expenses
     */
    private function recalculateEconomy(User $user): void
    {
        $economy = $user->economy;
        if (!$economy) return;

        // Calculate hourly income from active orders
        $hourlyIncome = CustomerOrder::whereHas('customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'active')->get()->sum(function ($order) {
            return $order->getHourlyValue();
        });

        // Calculate hourly expenses
        $rooms = GameRoom::where('user_id', $user->id)->with('racks.servers.activeOrders')->get();
        
        $hourlyExpenses = 0;

        foreach ($rooms as $room) {
            // Room rent
            $hourlyExpenses += $room->rent_per_hour;

            // Power costs (per kWh * usage * hours)
            $powerKw = $room->getCurrentPowerUsage();
            
            // Research Bonus: Power Efficiency
            $researchService = app(\App\Services\Game\ResearchService::class);
            $efficiency = $researchService->getBonus($user, 'power_efficiency');
            $powerCostMultiplier = max(0.1, 1.0 - $efficiency);

            $hourlyExpenses += ($powerKw * $economy->power_price_per_kwh * $powerCostMultiplier);

            // Bandwidth costs (per Gbps used)
            $bandwidthGbps = $room->getCurrentBandwidthUsage();
            $hourlyExpenses += $bandwidthGbps * $economy->bandwidth_cost_per_gbps;
        }

        $economy->hourly_income = $hourlyIncome;
        $economy->hourly_expenses = $hourlyExpenses;
        $economy->save();
    }

    /**
     * Calculate aggregate stats
     */
    private function calculateStats(User $user, Collection $rooms, Collection $customers): array
    {
        $totalServers = 0;
        $onlineServers = 0;
        $totalRacks = 0;

        foreach ($rooms as $room) {
            $totalRacks += $room->racks->count();
            foreach ($room->racks as $rack) {
                $totalServers += $rack->servers->count();
                $onlineServers += $rack->servers->where('status', 'online')->count();
            }
        }

        return [
            'totalRooms' => $rooms->where('is_unlocked', true)->count(),
            'totalRacks' => $totalRacks,
            'totalServers' => $totalServers,
            'onlineServers' => $onlineServers,
            'uptime' => $totalServers > 0 ? ($onlineServers / $totalServers) * 100 : 100,
            'totalCustomers' => $customers->count(),
            'monthlyRecurringRevenue' => $customers->sum('revenue_per_month'),
        ];
    }

    /**
     * Get a delta update (only changed state since timestamp)
     * This is for WebSocket updates - we send events, not full state
     */
    public function getDelta(User $user, string $sinceTimestamp): array
    {
        // For now, return minimal delta
        // Real implementation would track changes
        return [
            'timestamp' => now()->toIso8601String(),
            'changes' => [],
        ];
    }
}
