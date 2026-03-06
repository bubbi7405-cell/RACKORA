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
use App\Models\UserComponent;
use Illuminate\Support\Collection;

class GameStateService
{
    public function __construct(
        protected ResearchService $researchService,
        protected MarketService $marketService,
        protected \App\Services\Game\NetworkService $networkService,
        protected \App\Services\Game\EnergyService $energyService
    ) {}

    /**
     * Get the complete authoritative game state for a player
     */
    public function getFullState(User $user, bool $fresh = false): array
    {
        $cacheKey = "game_state_{$user->id}";

        if (!$fresh) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached) return $cached;
        }

        // JIT Sync: Process any completed tasks (provisioning, installs) before returning state
        $this->recalculateTimeBased($user);

        $economy = $user->economy ?? $this->initializePlayer($user);

        $rooms = GameRoom::where('user_id', $user->id)
            ->with(['user', 'racks.servers.activeOrders'])
            ->get();

        $customers = Customer::where('user_id', $user->id)
            ->whereIn('status', ['active', 'unhappy', 'churning'])
            ->with(['orders', 'activeOrders', 'pendingOrders'])
            ->get();

        $pendingOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'pending')
            ->with('customer')
            ->orderBy('patience_expires_at')
            ->get();

        $activeEvents = GameEvent::where('user_id', $user->id)
            ->whereIn('status', ['warning', 'active', 'escalated'])
            ->orderBy('deadline_at')
            ->get();

        $activeCrisis = \App\Models\GlobalCrisis::where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->first();

        $researchState = $this->researchService->getResearchState($user);
        $marketShare = $this->marketService->getMarketOverview($user);
        
        $network = $user->network ?? $this->networkService->initializeNetwork($user);
        $networkState = $this->networkService->getNetworkGameState($network);

        // --- FEATURE 118: Vulnerability HUD Alerts ---
        $vulnerableServers = Server::whereHas('rack.room', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('security_patch_level', '<', 50)
          ->where('status', 'online')
          ->with(['rack.room'])
          ->get();

        // --- FEATURE 121: Energy Price Volatility Detection ---
        $energyHistory = \Illuminate\Support\Facades\Cache::get('energy_price_history', []);
        $isVolatile = false;
        if (count($energyHistory) >= 10) {
            $currentPrice = $this->energyService->getSpotPrice();
            $avgPrice = collect($energyHistory)->avg('price');
            if ($avgPrice > 0 && ($currentPrice / $avgPrice) > 1.20) {
                $isVolatile = true;
            }
        }

        $state = [
            'timestamp' => now()->toIso8601String(),
            'activeCrisis' => $activeCrisis,
            'isEnergyVolatile' => $isVolatile, // F121
            'vulnerabilities' => $vulnerableServers->map(fn($s) => [ // F118
                'id' => $s->id,
                'name' => $s->name,
                'rack_name' => $s->rack->name,
                'room_name' => $s->rack->room->name,
                'patch_level' => $s->security_patch_level,
                'os' => $s->installed_os_type
            ])->toArray(),
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'companyName' => $user->company_name,
                'companyLogo' => $user->company_logo,
                'economy' => $economy->toGameState(),
                'tutorial_step' => (int) $user->tutorial_step,
                'tutorial_completed' => (bool) $user->tutorial_completed,
                'specialization' => $user->specialization ?? 'balanced',
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
            'research' => $researchState,
            'regions' => \App\Models\GameConfig::get('regions', []),
            'location_definitions' => \App\Models\GameConfig::get('location_definitions', []),
            'hardware' => [
                'inventory' => UserComponent::where('user_id', $user->id)
                    ->where('status', 'inventory')
                    ->get()
                    ->map(fn($c) => $c->toGameState())
                    ->toArray(),
                'catalog' => \App\Models\GameConfig::get('server_components', []),
                'servers' => \App\Models\GameConfig::get('server_catalog', []),
            ],
            'world_events' => [
                'active' => \App\Models\WorldEvent::where('is_active', true)->get()->map(fn($e) => $e->toArray())->toArray(),
                'history' => \App\Models\WorldEvent::where('is_active', false)->orderBy('ends_at', 'desc')->limit(10)->get()->toArray(),
            ],
            'marketShare' => $marketShare,
            'network' => $networkState,
            'rentedServers' => Server::where('tenant_id', $user->id)->with('rack.room')->get()->map(fn($s) => $s->toGameState())->toArray(),
            'stats' => $this->calculateStats($user, $rooms, $customers),
            'weather' => \Illuminate\Support\Facades\Cache::get('regional_weather', []),
            'energy' => [
                'spotPrice' => app(\App\Services\Game\EnergyService::class)->getSpotPrice(),
                'regional_prices' => \Illuminate\Support\Facades\Cache::get('energy_regional_prices', []),
                'regional_solar' => \Illuminate\Support\Facades\Cache::get('energy_regional_solar_factors', []),
                'global_factor' => \Illuminate\Support\Facades\Cache::get('energy_global_factor', 1.0),
                'price_history' => \Illuminate\Support\Facades\Cache::get('energy_price_history', []),
            ],
        ];

        // Cache for 2 seconds (short enough for interactivity, long enough for poll reduction)
        \Illuminate\Support\Facades\Cache::put($cacheKey, $state, 2);

        return $state;
    }

    /**
     * Initialize a new player with starting resources
     */
    public function initializePlayer(User $user): PlayerEconomy
    {
        $economy = PlayerEconomy::create([
            'user_id' => $user->id,
            'balance' => 5000.00,
            'reputation' => 50.0,
            'level' => 1,
            'last_income_tick' => now(),
        ]);

        // Set default company name if not set
        if (!$user->company_name) {
            $user->company_name = $user->name . ' Systems';
            $user->save();
        }

        $basementType = \App\Enums\RoomType::tryFrom(\App\Enums\RoomType::BASEMENT);

        // Create starting basement room
        $basement = GameRoom::create([
            'user_id' => $user->id,
            'type' => \App\Enums\RoomType::BASEMENT,
            'name' => 'Home Basement',
            'level' => 1,
            'max_racks' => $basementType->maxRacks(),
            'max_power_kw' => $basementType->maxPowerKw(),
            'max_cooling_kw' => $basementType->maxCoolingKw(),
            'bandwidth_gbps' => $basementType->bandwidthGbps(),
            'rent_per_hour' => $basementType->rentPerHour(),
            'is_unlocked' => true,
            'unlocked_at' => now(),
            'position' => ['x' => 0, 'y' => 0],
        ]);

        $this->networkService->initializeNetwork($user);

        return $economy;
    }

    /**
     * Recalculate time-based values (income, provisioning, etc.)
     */
    public function recalculateTimeBased(User $user): void
    {
        // JIT Sync ONLY servers that have actually finished their time
        // This makes it extremely lightweight for normal polling
        $servers = Server::where(function($q) use ($user) {
            $q->whereHas('rack.room', fn($inner) => $inner->where('user_id', $user->id))
              ->orWhere('tenant_id', $user->id);
        })->where(function($q) {
            $q->where(function($sq) {
                $sq->where('status', 'provisioning')
                  ->where('provisioning_completes_at', '<=', now());
            })->orWhere(function($sq) {
                $sq->where('os_install_status', 'installing')
                  ->where('os_install_completes_at', '<=', now());
            })->orWhere(function($sq) {
                $sq->where('app_install_status', 'installing')
                  ->where('app_install_completes_at', '<=', now());
            });
        })->get();

        if ($servers->isNotEmpty()) {
            foreach ($servers as $server) {
                $server->syncTaskStates();
            }
            // Only recalculate economy if something actually changed status
            $this->recalculateEconomy($user);
        }
    }

    /**
     * Recalculate hourly income and expenses
     */
    private function recalculateEconomy(User $user): void
    {
        // Ensure network exists before recalculating
        if (!$user->relationLoaded('network') || !$user->network) {
            $user->load('network');
            if (!$user->network) {
                $this->networkService->initializeNetwork($user);
                $user->load('network');
            }
        }

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
        $totalPowerKw = 0;
        $totalBandwidthGbps = 0;

        // Global Engine Constants
        $engine = \App\Models\GameConfig::get('engine_constants', []);
        $revMult = $engine['revenue_multiplier'] ?? 1.0;
        $expMult = $engine['expense_multiplier'] ?? 1.0;

        // Check for active fixed contract
        $hasFixedContract = $economy->energy_contract_type === 'fixed' 
            && $economy->energy_contract_expires_at 
            && $economy->energy_contract_expires_at->isFuture();

        foreach ($rooms as $room) {
            // Room rent
            $hourlyExpenses += ($room->rent_per_hour * $expMult);

            // Power costs (per kWh * usage * hours)
            $powerKw = $room->getCurrentPowerUsage();
            $totalPowerKw += $powerKw;
            
            // Research Bonus: Power Efficiency
            $efficiency = $this->researchService->getBonus($user, 'power_efficiency');
            $powerCostMultiplier = max(0.1, 1.0 - $efficiency);

            // Determine Power Price
             if ($room->power_cost_kwh) {
                // Manual override or legacy fixed
                $powerPrice = $room->power_cost_kwh;
            } elseif ($hasFixedContract) {
                // Use contracted rate
                $powerPrice = $economy->energy_contract_price;
            } else {
                // Use dynamic regional spot price
                $powerPrice = $this->energyService->getSpotPrice($room->region);
            }

            $hourlyExpenses += ($powerKw * $powerPrice * $powerCostMultiplier * $expMult);

            // Bandwidth costs (per Gbps used)
            $bandwidthGbps = $room->getCurrentBandwidthUsage();
            $totalBandwidthGbps += $bandwidthGbps;
            $hourlyExpenses += ($bandwidthGbps * $economy->bandwidth_cost_per_gbps * $expMult);
        }

        // --- TRANSIT BURST COSTS (Blueprint 1.3) ---
        $netState = $this->networkService->getNetworkGameState($user->network);
        $capacityGbps = $netState['bandwidth']['totalCapacityGbps'];
        if ($totalBandwidthGbps > $capacityGbps && $capacityGbps > 0) {
            $burstGbps = $totalBandwidthGbps - $capacityGbps;
            $burstFee = $burstGbps * 15.00 * $expMult; // $15 per Gbps burst
            $hourlyExpenses += $burstFee;
        }

        // IP Maintenance Costs
        $network = $user->network;
        if ($network) {
            $ipv4HourlyCost = config('game.network.ipv4_cost_per_hour', 0.05);
            
            // Research Bonus: IPv6 Transition reduces v4 costs
            $v4CostReduction = $this->researchService->getBonus($user, 'ipv4_cost_reduction');
            $ipv4HourlyCost *= (1.0 - $v4CostReduction);

            $hourlyExpenses += ($network->ipv4_total * $ipv4HourlyCost * $expMult);
        }

        $economy->hourly_income = $hourlyIncome * $revMult;
        $economy->hourly_expenses = $hourlyExpenses;
        $economy->total_power_kw = $totalPowerKw;
        $economy->total_bandwidth_gbps = $totalBandwidthGbps;
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
