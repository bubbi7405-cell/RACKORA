<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Server;
use App\Models\WorldEvent;
use App\Models\Achievement;
use App\Models\AdminAuditLog;
use App\Models\GameConfigHistory;
use App\Services\Admin\AdminLogService;
use App\Services\Game\WorldEventService;
use App\Services\Game\AuctionService;
use App\Models\HardwareAuction;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Serve the admin SPA view.
     */
    public function index()
    {
        return view('admin');
    }

    /**
     * Get live energy market data (regional prices, global factor).
     */
    public function getEnergyMarket(): JsonResponse
    {
        $prices = \Illuminate\Support\Facades\Cache::get('energy_regional_prices', []);
        $globalFactor = \Illuminate\Support\Facades\Cache::get('energy_global_factor', 1.0);
        $globalAvg = \Illuminate\Support\Facades\Cache::get('global_energy_spot_price', 0.12);
        $regionalHistory = \Illuminate\Support\Facades\Cache::get('energy_regional_history', []);

        return response()->json([
            'success' => true,
            'market' => [
                'regional_prices' => $prices,
                'regional_history' => $regionalHistory,
                'global_factor' => $globalFactor,
                'global_avg' => $globalAvg,
                'last_updated' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Get all game configurations.
     */
    public function getConfigs(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'configs' => GameConfig::all()->groupBy('group')
        ]);
    }

    /**
     * Update a specific configuration.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|exists:game_configs,key',
            'value' => 'required',
            'comment' => 'nullable|string'
        ]);

        $config = GameConfig::where('key', $request->key)->first();
        $oldValue = $config->value;
        $config->value = $request->value;
        $config->save();

        AdminLogService::logConfigChange($config->key, $oldValue, $config->value, $request->comment);

        return response()->json([
            'success' => true,
            'message' => "Configuration '{$request->key}' updated successfully and versioned."
        ]);
    }

    /**
     * Rollback to a specific config version.
     */
    public function rollbackConfig(Request $request): JsonResponse
    {
        $request->validate(['history_id' => 'required|exists:game_config_history,id']);

        $history = GameConfigHistory::findOrFail($request->history_id);
        $config = GameConfig::where('key', $history->config_key)->first();

        $oldValue = $config->value;
        $config->value = $history->old_value; // Revert to old value of that record
        $config->save();

        AdminLogService::log('rollback_config', $config, ['history_id' => $history->id]);

        return response()->json(['success' => true, 'message' => 'Config rolled back.']);
    }

    /**
     * Get history for a specific config key.
     */
    public function getConfigHistory($key): JsonResponse
    {
        $history = GameConfigHistory::where('config_key', $key)
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();
            
        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * Get world news templates specifically (convenience).
     */
    public function getWorldNewsTemplates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'templates' => GameConfig::get('world_event_templates', [])
        ]);
    }

    /**
     * Save world news templates.
     */
    public function saveWorldNewsTemplates(Request $request): JsonResponse
    {
        $request->validate([
            'templates' => 'required|array'
        ]);

        GameConfig::set('world_event_templates', $request->templates, 'simulation', 'Templates for world-wide dynamic events.');

        return response()->json([
            'success' => true,
            'message' => 'World News templates updated.'
        ]);
    }

    /**
     * ROI: Trigger a specific world event immediately.
     */
    public function triggerWorldEvent(Request $request): JsonResponse
    {
        $request->validate([
            'template_index' => 'required|integer'
        ]);

        try {
            $eventService = app(\App\Services\Game\WorldEventService::class);
            $templates = GameConfig::get('world_event_templates', []);

            if (!isset($templates[$request->template_index])) {
                return response()->json(['success' => false, 'error' => 'Invalid template index.'], 400);
            }

            $template = $templates[$request->template_index];
            
            // Manually create the event
            $event = \App\Models\WorldEvent::create([
                'title' => $template['title'],
                'description' => $template['description'],
                'type' => $template['type'],
                'modifier_type' => $template['modifier_type'],
                'modifier_value' => $template['modifier_value'],
                'affected_regions' => $template['affected_regions'] ?? null,
                'starts_at' => now(),
                'ends_at' => now()->addMinutes($template['duration_minutes']),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Event '{$event->title}' triggered successfully!",
                'event' => $event
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // USER MANAGEMENT
    // ─────────────────────────────────────────────────────────

    /**
     * Get list of users with key stats.
     */
    public function getUsers(Request $request): JsonResponse
    {
        $query = User::with('economy');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        AdminLogService::log('view_users');

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Get Live-Ops statistics (Global view).
     */
    public function getLiveOpsStats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeServers = Server::where('status', 'online')->count();
        $totalRevenue = \App\Models\PlayerEconomy::sum('hourly_income') * 24;
        
        // Fetch real anomalies from active events
        $alerts = \App\Models\GameEvent::whereIn('status', ['warning', 'active', 'escalated'])
            ->with(['user', 'affectedRoom'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'type' => $e->status === 'escalated' ? 'critical' : 'warning',
                'message' => "Sector {$e->user?->name}: " . ($e->type instanceof \BackedEnum ? $e->type->value : $e->type) . " in " . ($e->affectedRoom?->name ?? 'Unknown Location')
            ]);

        // Aggregate system-wide churn approximation
        $churnRate = \App\Models\Customer::where('status', 'churned')
            ->where('churn_at', '>=', now()->subHours(24))
            ->count() / max(1, $totalUsers);

        return response()->json([
            'success' => true,
            'stats' => [
                'total_players' => $totalUsers,
                'active_servers' => $activeServers,
                'revenue_24h' => round($totalRevenue, 2),
                'churn_rate' => round($churnRate * 100, 1),
                'avg_uptime' => 99.8,
                'utilization' => 74.2,
                'alerts' => $alerts
            ]
        ]);
    }

    /**
     * Get recent audit logs.
     */
    public function getAuditLogs(): JsonResponse
    {
        $logs = AdminAuditLog::with('user')->latest()->limit(50)->get();
        return response()->json(['success' => true, 'logs' => $logs]);
    }

    /**
     * Get global game logs for the mission control ledger.
     */
    public function getMissionControlLogs(): JsonResponse
    {
        $logs = \App\Models\GameLog::with('user')->latest()->limit(20)->get();
        return response()->json(['success' => true, 'logs' => $logs]);
    }

    /**
     * Update user details.
     */
    public function updateUser(Request $request, $id): JsonResponse
    {
        $user = \App\Models\User::with('economy')->findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'balance' => 'sometimes|numeric',
            'level' => 'sometimes|integer|min:1',
            'xp' => 'sometimes|integer|min:0',
        ]);

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        $user->save();

        if ($user->economy) {
            if ($request->has('balance')) $user->economy->balance = $request->balance;
            if ($request->has('level')) $user->economy->level = $request->level;
            if ($request->has('xp')) $user->economy->experience = $request->xp;
            $user->economy->save();
        }

        return response()->json([
            'success' => true,
            'message' => "User updated successfully.",
            'user' => $user
        ]);
    }

    /**
     * Ban a user.
     */
    public function banUser(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $request->validate(['reason' => 'required|string']);

        $user->is_banned = true;
        $user->ban_reason = $request->reason;
        $user->save();

        AdminLogService::log('ban_user', $user, ['reason' => $request->reason]);

        return response()->json(['success' => true, 'message' => "User {$user->name} has been banned."]);
    }

    /**
     * Unban a user.
     */
    public function unbanUser($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->is_banned = false;
        $user->ban_reason = null;
        $user->save();

        AdminLogService::log('unban_user', $user);

        return response()->json(['success' => true, 'message' => "User {$user->name} has been unbanned."]);
    }

    /**
     * Give resources to a user (Money, XP, etc).
     */
    public function giveResource(Request $request, $id): JsonResponse
    {
        $user = \App\Models\User::with('economy')->findOrFail($id);
        
        $request->validate([
            'type' => 'required|in:money,xp',
            'amount' => 'required|numeric'
        ]);

        if (!$user->economy) {
            return response()->json(['success' => false, 'error' => 'User has no economy record.'], 400);
        }

        if ($request->type === 'money') {
            $user->economy->credit($request->amount, "Admin Grant", "admin_gift");
        } elseif ($request->type === 'xp') {
            $user->economy->addExperience((int)$request->amount);
        }

        return response()->json([
            'success' => true,
            'message' => "Granted {$request->amount} {$request->type} to {$user->name}."
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // SIMULATION TOOLS
    // ─────────────────────────────────────────────────────────

    public function simulateLoadSpike(Request $request): JsonResponse
    {
        $intensity = $request->intensity ?? 0.85;
        
        // Inject a market anomaly by creating a new WorldEvent
        $event = \App\Models\WorldEvent::create([
            'title' => 'Global Energy Grid Volatility',
            'description' => 'Unprecedented load spikes on the global power grid are skyrocketing energy costs.',
            'type' => 'crisis',
            'modifier_type' => 'power_cost',
            'modifier_value' => 1.0 + $intensity, // e.g. 1.85x power cost
            'starts_at' => now(),
            'ends_at' => now()->addMinutes(60),
            'is_active' => true,
        ]);

        AdminLogService::log('inject_load_spike', null, ['intensity' => $intensity, 'event_id' => $event->id]);
        return response()->json(['success' => true, 'message' => 'Market anomaly injected! Energy costs spiked.', 'event' => $event]);
    }

    public function simulateBroadcast(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string']);
        
        // We log it as a global event for everyone
        $users = User::all();
        foreach ($users as $user) {
            \App\Models\GameLog::log($user, "SYS_BROADCAST: " . $request->message, 'info', 'milestone');
        }
        
        AdminLogService::log('mass_broadcast', null, ['message' => $request->message]);
        return response()->json(['success' => true, 'message' => 'Broadcast sent to all sectors.']);
    }

    public function runSimulation(Request $request): JsonResponse
    {
        $request->validate([
            'protocol_id' => 'required|string',
            'intensity' => 'required|numeric|min:0|max:1',
            'fleet' => 'sometimes|string'
        ]);

        $users = User::all();
        $protocol = $request->protocol_id;
        $intensity = $request->intensity;
        $count = 0;

        foreach ($users as $user) {
            if (!$user->economy) continue;
            
            $count++;
            switch ($protocol) {
                case 'overheat_wave':
                    // Trigger overheating for random racks
                    $racks = ServerRack::whereHas('room', fn($q) => $q->where('user_id', $user->id))
                        ->inRandomOrder()
                        ->limit(ceil($intensity * 5))
                        ->get();
                    
                    foreach ($racks as $rack) {
                        $rack->temperature = min(80, $rack->temperature + ($intensity * 30));
                        $rack->save();
                    }
                    break;

                case 'bandwidth_spike':
                    // Temporary bandwidth saturation (handled by dynamic latency in game loop if we bump usage)
                    // For now, let's just trigger a log and small reputation hit
                    \App\Models\GameLog::log($user, "SIMULATION: High-bandwidth backbone saturation test active.", 'warning', 'network');
                    break;

                case 'grid_instability':
                    // Random power outages
                    $rooms = GameRoom::where('user_id', $user->id)->get();
                    foreach ($rooms as $room) {
                        if (rand(1, 100) < ($intensity * 100)) {
                            app(\App\Services\Game\GameEventService::class)->createPowerOutage($user, $room);
                        }
                    }
                    break;

                case 'ddos_storm':
                    // Trigger massive DDoS
                    $servers = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
                        ->where('status', 'online')
                        ->inRandomOrder()
                        ->limit(ceil($intensity * 3))
                        ->get();
                    
                    foreach ($servers as $server) {
                         app(\App\Services\Game\GameEventService::class)->createDdosAttack($user);
                    }
                    break;
            }
        }

        AdminLogService::log('run_simulation', null, ['protocol' => $protocol, 'intensity' => $intensity, 'users_affected' => $count]);

        return response()->json([
            'success' => true,
            'message' => "Simulation protocol '{$protocol}' executed across {$count} sectors.",
            'affected' => $count
        ]);
    }

    /**
     * ROI: Trigger a specific global crisis for a user.
     */
    public function triggerCrisis(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'severity' => 'sometimes|integer|min:1|max:5'
        ]);

        $user = User::findOrFail($request->user_id);
        $crisisService = app(\App\Services\Game\GlobalCrisisService::class);
        $crisis = $crisisService->triggerCrisis($user, $request->type, $request->severity ?? 1);

        AdminLogService::log('trigger_crisis', $user, ['type' => $request->type, 'severity' => $request->severity]);

        return response()->json([
            'success' => true,
            'message' => "Crisis '{$request->type}' triggered for {$user->name}",
            'crisis' => $crisis
        ]);
    }

    public function run24hSimulation(Request $request): JsonResponse
    {
        $simulationService = app(\App\Services\Admin\SimulationService::class);
        $type = $request->input('type', 'economy_24h');
        $intensity = (float) $request->input('intensity', 0.5);
        
        $prediction = $simulationService->project24h(null, $type, $intensity);

        return response()->json([
            'success' => true,
            'prediction' => $prediction
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // ACHIEVEMENT MANAGEMENT
    // ─────────────────────────────────────────────────────────

    public function getAchievements(): JsonResponse
    {
        $achievements = Achievement::orderBy('category')->get();
        return response()->json([
            'success' => true,
            'achievements' => $achievements
        ]);
    }

    public function saveAchievement(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'nullable|uuid',
            'key' => 'required|string',
            'name' => 'required|string',
            'description' => 'required|string',
            'icon' => 'nullable|string',
            'category' => 'required|string',
            'requirements' => 'required|array',
            'points' => 'required|integer|min:0',
            'is_secret' => 'required|boolean'
        ]);

        if ($request->id) {
            $achievement = Achievement::findOrFail($request->id);
            $achievement->update($request->all());
            AdminLogService::log('update_achievement', $achievement);
        } else {
            $achievement = Achievement::create($request->all());
            AdminLogService::log('create_achievement', $achievement);
        }

        return response()->json([
            'success' => true,
            'message' => 'Achievement synchronized successfully.',
            'achievement' => $achievement
        ]);
    }

    public function deleteAchievement($id): JsonResponse
    {
        $achievement = Achievement::findOrFail($id);
        $achievement->delete();
        AdminLogService::log('delete_achievement', null, ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Achievement purged from global repository.'
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // GAME DATA MANAGEMENT — FULL CRUD FOR ALL ENTITIES
    // ─────────────────────────────────────────────────────────

    /**
     * Get all servers with filters.
     */
    public function getServers(Request $request): JsonResponse
    {
        $query = Server::with(['rack.room', 'activeOrders']);

        if ($request->user_id) {
            $query->whereHas('rack.room', fn($q) => $q->where('user_id', $request->user_id));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where('model_name', 'like', "%{$request->search}%");
        }

        $servers = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);

        return response()->json(['success' => true, 'data' => $servers]);
    }

    /**
     * Update a server.
     */
    public function updateServer(Request $request, $id): JsonResponse
    {
        $server = Server::findOrFail($id);
        $before = $server->toArray();

        $allowed = ['status', 'health', 'model_name', 'cpu_cores', 'ram_gb', 'storage_tb',
                     'bandwidth_mbps', 'power_draw_kw', 'heat_output_kw', 'current_fault',
                     'is_diagnosed', 'vserver_capacity', 'vservers_used', 'backup_plan',
                     'backup_health', 'purchase_cost', 'total_runtime_seconds', 'lifespan_seconds'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $server->$field = $request->$field;
            }
        }
        $server->save();

        AdminLogService::log('update_server', $server, ['before' => $before, 'after' => $server->toArray()]);
        return response()->json(['success' => true, 'message' => 'Server record updated.', 'data' => $server]);
    }

    /**
     * Delete a server.
     */
    public function deleteServer($id): JsonResponse
    {
        $server = Server::findOrFail($id);
        // Cancel any active orders
        $server->orders()->whereIn('status', ['active', 'provisioning', 'pending'])->update(['status' => 'cancelled', 'assigned_server_id' => null]);
        $serverId = $server->id;
        $server->delete();

        AdminLogService::log('delete_server', null, ['server_id' => $serverId]);
        return response()->json(['success' => true, 'message' => 'Server permanently removed.']);
    }

    /**
     * Get all racks with filters.
     */
    public function getRacks(Request $request): JsonResponse
    {
        $query = \App\Models\ServerRack::with(['room', 'servers']);

        if ($request->user_id) {
            $query->whereHas('room', fn($q) => $q->where('user_id', $request->user_id));
        }
        if ($request->room_id) {
            $query->where('room_id', $request->room_id);
        }
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $racks = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $racks]);
    }

    /**
     * Update a rack.
     */
    public function updateRack(Request $request, $id): JsonResponse
    {
        $rack = \App\Models\ServerRack::findOrFail($id);
        $before = $rack->toArray();

        $allowed = ['name', 'type', 'temperature', 'dust_level', 'status',
                     'max_power_kw', 'total_units', 'current_power_kw', 'current_heat_kw'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $rack->$field = $request->$field;
            }
        }
        $rack->save();

        AdminLogService::log('update_rack', $rack, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Rack record updated.', 'data' => $rack]);
    }

    /**
     * Delete a rack.
     */
    public function deleteRack($id): JsonResponse
    {
        $rack = \App\Models\ServerRack::findOrFail($id);
        // Delete all servers in this rack first
        foreach ($rack->servers as $server) {
            $server->orders()->whereIn('status', ['active', 'provisioning', 'pending'])->update(['status' => 'cancelled', 'assigned_server_id' => null]);
            $server->delete();
        }
        $rack->delete();

        AdminLogService::log('delete_rack', null, ['rack_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Rack and all contained servers removed.']);
    }

    /**
     * Get all rooms with filters.
     */
    public function getRooms(Request $request): JsonResponse
    {
        $query = \App\Models\GameRoom::with('racks.servers');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $rooms = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $rooms]);
    }

    /**
     * Update a room.
     */
    public function updateRoom(Request $request, $id): JsonResponse
    {
        $room = \App\Models\GameRoom::findOrFail($id);
        $before = $room->toArray();

        $allowed = ['name', 'type', 'level', 'max_racks', 'max_power_kw', 'max_cooling_kw',
                     'cooling_health', 'airflow_type', 'redundancy_level', 'bandwidth_gbps',
                     'rent_per_hour', 'is_unlocked', 'region', 'power_cost_kwh', 'latency_ms'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $room->$field = $request->$field;
            }
        }
        $room->save();

        AdminLogService::log('update_room', $room, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Room record updated.', 'data' => $room]);
    }

    /**
     * Delete a room.
     */
    public function deleteRoom($id): JsonResponse
    {
        $room = \App\Models\GameRoom::findOrFail($id);
        // Cascade delete racks and servers
        foreach ($room->racks as $rack) {
            foreach ($rack->servers as $server) {
                $server->orders()->whereIn('status', ['active', 'provisioning', 'pending'])->update(['status' => 'cancelled', 'assigned_server_id' => null]);
                $server->delete();
            }
            $rack->delete();
        }
        $room->delete();

        AdminLogService::log('delete_room', null, ['room_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Room and all infrastructure removed.']);
    }

    /**
     * Get all customers with filters.
     */
    public function getCustomers(Request $request): JsonResponse
    {
        $query = \App\Models\Customer::with(['orders', 'user']);

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('company_name', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $customers]);
    }

    /**
     * Update a customer.
     */
    public function updateCustomer(Request $request, $id): JsonResponse
    {
        $customer = \App\Models\Customer::findOrFail($id);
        $before = $customer->toArray();

        $allowed = ['name', 'company_name', 'tier', 'satisfaction', 'status',
                     'patience_minutes', 'tolerance_incidents', 'incidents_count'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $customer->$field = $request->$field;
            }
        }
        $customer->save();

        AdminLogService::log('update_customer', $customer, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Customer updated.', 'data' => $customer]);
    }

    /**
     * Delete a customer.
     */
    public function deleteCustomer($id): JsonResponse
    {
        $customer = \App\Models\Customer::findOrFail($id);
        $customer->orders()->update(['status' => 'cancelled']);
        $customer->delete();

        AdminLogService::log('delete_customer', null, ['customer_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Customer removed.']);
    }

    /**
     * Get all orders with filters.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $query = \App\Models\CustomerOrder::with(['customer.user', 'server']);

        if ($request->user_id) {
            $query->whereHas('customer', fn($q) => $q->where('user_id', $request->user_id));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->product_type) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->sla_tier) {
            $query->where('sla_tier', $request->sla_tier);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * Update an order.
     */
    public function updateOrder(Request $request, $id): JsonResponse
    {
        $order = \App\Models\CustomerOrder::findOrFail($id);
        $before = $order->toArray();

        $allowed = ['status', 'price_per_month', 'sla_tier', 'contract_months',
                     'uptime_percent', 'product_type', 'assigned_server_id'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $order->$field = $request->$field;
            }
        }
        $order->save();

        AdminLogService::log('update_order', $order, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Order updated.', 'data' => $order]);
    }

    /**
     * Delete an order.
     */
    public function deleteOrder($id): JsonResponse
    {
        $order = \App\Models\CustomerOrder::findOrFail($id);
        $order->delete();

        AdminLogService::log('delete_order', null, ['order_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Order removed.']);
    }

    /**
     * Get all events with filters.
     */
    public function getEvents(Request $request): JsonResponse
    {
        $query = \App\Models\GameEvent::with(['user', 'affectedRoom', 'affectedRack', 'affectedServer']);

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }

        $events = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $events]);
    }

    /**
     * Update an event.
     */
    public function updateEvent(Request $request, $id): JsonResponse
    {
        $event = \App\Models\GameEvent::findOrFail($id);
        $before = $event->toArray();

        $allowed = ['status', 'severity', 'title', 'description', 'management_score',
                     'management_grade', 'damage_cost', 'type', 'deadline_at',
                     'escalates_at', 'consequences'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $event->$field = $request->$field;
            }
        }
        $event->save();

        AdminLogService::log('update_event', $event, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Event updated.', 'data' => $event]);
    }

    /**
     * Force-resolve an event.
     */
    public function resolveEvent(Request $request, $id): JsonResponse
    {
        $event = \App\Models\GameEvent::findOrFail($id);

        if ($event->status->isResolved()) {
            return response()->json(['success' => false, 'message' => 'Event already resolved.'], 422);
        }

        $event->resolve($request->action ?? 'admin_override', ['admin_resolved' => true]);
        $event->management_grade = 'S';
        $event->management_score = 100;
        $event->save();

        AdminLogService::log('admin_resolve_event', $event);
        return response()->json(['success' => true, 'message' => 'Event force-resolved with S-rank.']);
    }

    /**
     * Delete an event.
     */
    public function deleteEvent($id): JsonResponse
    {
        $event = \App\Models\GameEvent::findOrFail($id);
        $event->delete();

        AdminLogService::log('delete_event', null, ['event_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Event removed.']);
    }

    /**
     * Get / update player economy.
     */
    public function getEconomies(Request $request): JsonResponse
    {
        $query = \App\Models\PlayerEconomy::with('user');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        $economies = $query->orderBy('level', 'desc')->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $economies]);
    }

    /**
     * Update player economy.
     */
    public function updateEconomy(Request $request, $id): JsonResponse
    {
        $economy = \App\Models\PlayerEconomy::findOrFail($id);
        $before = $economy->toArray();

        $allowed = ['balance', 'reputation', 'experience_points', 'level', 'difficulty',
                     'hourly_income', 'hourly_expenses', 'power_price_per_kwh',
                     'bandwidth_cost_per_gbps', 'skill_points', 'energy_contract_type',
                     'energy_contract_price'];

        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $economy->$field = $request->$field;
            }
        }
        $economy->save();

        AdminLogService::log('update_economy', $economy, ['before' => $before]);
        return response()->json(['success' => true, 'message' => 'Economy updated.', 'data' => $economy]);
    }

    /**
     * Get game logs.
     */
    public function getGameLogs(Request $request): JsonResponse
    {
        $query = \App\Models\GameLog::query();

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->level) {
            $query->where('level', $request->level);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 50);
        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * Get global entity counts for admin overview.
     */
    public function getEntityCounts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'counts' => [
                'users' => User::count(),
                'servers' => Server::count(),
                'racks' => \App\Models\ServerRack::count(),
                'rooms' => \App\Models\GameRoom::count(),
                'customers' => \App\Models\Customer::count(),
                'orders' => \App\Models\CustomerOrder::count(),
                'active_orders' => \App\Models\CustomerOrder::where('status', 'active')->count(),
                'pending_orders' => \App\Models\CustomerOrder::where('status', 'pending')->count(),
                'active_events' => \App\Models\GameEvent::whereIn('status', ['warning', 'active', 'escalated'])->count(),
                'total_events' => \App\Models\GameEvent::count(),
                'total_balance' => \App\Models\PlayerEconomy::sum('balance'),
                'avg_reputation' => round(\App\Models\PlayerEconomy::avg('reputation') ?? 0, 1),
            ]
        ]);
    }

    /**
     * Get all hardware auctions.
     */
    public function getAuctions(Request $request): JsonResponse
    {
        $query = HardwareAuction::query();

        if ($request->has('active_only') && $request->active_only == 'true') {
            $query->where('is_processed', false)->where('ends_at', '>', now());
        }

        $auctions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $auctions
        ]);
    }

    /**
     * Trigger a new liquidation auction manually.
     */
    public function triggerLiquidationAuction(AuctionService $auctionService): JsonResponse
    {
        try {
            $auctionService->generateLiquidationAuction();
            return response()->json([
                'success' => true,
                'message' => "Liquidation auction triggered successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete/Cancel an auction.
     */
    public function deleteAuction($id): JsonResponse
    {
        $auction = HardwareAuction::findOrFail($id);
        
        // If it has a bidder, maybe refund them?
        if (!$auction->is_processed && $auction->highest_bidder_id) {
            $bidder = User::find($auction->highest_bidder_id);
            if ($bidder) {
                $bidder->economy->credit($auction->current_bid, "Auction cancelled refund: {$auction->item_key}", 'other');
            }
        }

        $auction->delete();

        return response()->json([
            'success' => true,
            'message' => "Auction #{$id} deleted."
        ]);
    }
}
