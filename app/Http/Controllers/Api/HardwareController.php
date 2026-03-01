<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameConfig;
use App\Models\GameRoom;
use App\Models\PlayerEconomy;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\UserComponent;
use App\Models\HardwareBrandDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HardwareController extends Controller
{
    /**
     * Get the catalog of all available components.
     */
    public function getCatalog(): JsonResponse
    {
        $components = GameConfig::get('server_components', []);
        
        return response()->json([
            'success' => true,
            'data' => $components
        ]);
    }

    /**
     * Get the available generations of hardware.
     */
    public function getGenerations(Request $request): JsonResponse
    {
        $generations = GameConfig::get('hardware_generations', []);
        
        return response()->json([
            'success' => true,
            'generations' => $generations
        ]);
    }

    /**
     * Get the user's component inventory.
     */
    public function getInventory(Request $request): JsonResponse
    {
        $user = $request->user();
        $inventory = UserComponent::where('user_id', $user->id)
            ->whereIn('status', ['inventory', 'delivering'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $inventory->map(fn($c) => $c->toGameState())
        ]);
    }

    /**
     * Purchase a single component.
     */
    public function purchaseComponent(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'key' => 'required|string',
            'delivery_type' => 'nullable|string|in:standard,express',
            'is_leased' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $economy = $user->economy;
        $allComponents = GameConfig::get('server_components', []);
        
        $type = $request->input('type');
        $key = $request->input('key');
        $deliveryType = $request->input('delivery_type', 'standard');
        $isLeased = $request->boolean('is_leased', false);
        
        $config = $allComponents[$type][$key] ?? null;

        if (!$config) {
            return response()->json(['success' => false, 'error' => 'Component not found'], 404);
        }

        // Logistic Costs
        $shippingCost = 0;
        $deliveryMinutes = 2; // Default standard

        if ($deliveryType === 'express') {
            $shippingCost = 50 + ($config['price'] * 0.05); // Base fee + 5%
            $deliveryMinutes = 0.5; // Express (30s)
        } else {
            $deliveryMinutes = rand(2, 4); // Standard (2-4m)
        }

        // Apply World Event & Crisis Modifiers
        $worldModifiers = \App\Models\WorldEvent::getActiveModifiers();
        $crisisService = app(\App\Services\Game\GlobalCrisisService::class);
        $crisisModifiers = $crisisService->getActiveModifiers($user);
        
        $modifiers = array_merge($worldModifiers, $crisisModifiers);
        $hardwareCostMod = $modifiers['hardware_cost'] ?? 1.0;

        // --- HARDWARE BRAND DEAL ---
        $activeDeal = HardwareBrandDeal::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        $compVendor = $config['vendor'] ?? 'Generic';

        if ($activeDeal) {
            if ($activeDeal->brand_name === $compVendor) {
                // Apply discount (e.g. 0.90 for 10% off)
                $hardwareCostMod *= (1.0 - ($activeDeal->discount_percent / 100));
            } else {
                // F267: Cannot buy parts from other vendors
                return response()->json([
                    'success' => false, 
                    'error' => "Your exclusivity deal with {$activeDeal->brand_name} prevents buying from {$compVendor}."
                ], 403);
            }
        }

        // LEASING CALCULATION
        $upfrontCost = $shippingCost;
        $leaseCostPerHour = 0;
        
        if ($isLeased) {
            // Lease costs ~10% of value per month (720 hours)
            // Plus a small 5% setup fee of the hardware price (CAPEX reduction)
            $leaseCostPerHour = ($config['price'] * 0.10) / 720;
            $upfrontCost += ($config['price'] * 0.05); 
        } else {
            $upfrontCost += ($config['price'] * $hardwareCostMod);
        }

        if ($economy->balance < $upfrontCost) {
            return response()->json(['success' => false, 'error' => 'Insufficient balance for upfront costs'], 400);
        }

        if ($economy->level < ($config['level_required'] ?? 1)) {
            return response()->json(['success' => false, 'error' => 'Level too low'], 400);
        }

        return DB::transaction(function () use ($user, $economy, $type, $key, $config, $upfrontCost, $deliveryType, $deliveryMinutes, $isLeased, $leaseCostPerHour) {
            // Debit balance
            $economy->balance -= $upfrontCost;
            $economy->save();

            // Create log
            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => -$upfrontCost,
                'type' => 'expense',
                'category' => 'hardware',
                'description' => ($isLeased ? 'Leased ' : 'Purchased ') . $config['name'] . ' (' . ucfirst($deliveryType) . ' Shipping)',
                'balance_after' => $economy->balance
            ]);

            // Calculate arrival
            $arrivalAt = now()->addSeconds($deliveryMinutes * 60);

            // Add to delivery queue
            $component = UserComponent::create([
                'user_id' => $user->id,
                'component_type' => $type,
                'component_key' => $key,
                'status' => 'delivering',
                'delivery_status' => 'delivering',
                'delivery_type' => $deliveryType,
                'arrival_at' => $arrivalAt,
                'health' => 100,
                'purchased_at' => now(),
                'is_leased' => $isLeased,
                'lease_cost_per_hour' => $leaseCostPerHour,
            ]);

            return response()->json([
                'success' => true,
                'message' => $isLeased ? 'Component leased and ordered' : 'Component ordered',
                'data' => $component->toGameState()
            ]);
        });
    }

    /**
     * Simulate server stats based on chosen components.
     * Does not use real inventory; uses keys directly from catalog.
     */
    public function simulateBuild(Request $request): JsonResponse
    {
        $request->validate([
            'motherboard_key' => 'required|string',
            'cpu_key' => 'required|string',
            'cpu_count' => 'required|integer|min:1',
            'ram_key' => 'required|string',
            'ram_count' => 'required|integer|min:1',
            'storage_key' => 'required|string',
            'storage_count' => 'required|integer|min:1',
        ]);

        $catalog = \App\Models\GameConfig::get('server_components', []);

        $mb = clone (object) ($catalog['motherboard'][$request->motherboard_key] ?? null);
        $cpu = clone (object) ($catalog['cpu'][$request->cpu_key] ?? null);
        $ram = clone (object) ($catalog['ram'][$request->ram_key] ?? null);
        $storage = clone (object) ($catalog['storage'][$request->storage_key] ?? null);

        if (!$mb || !$cpu || !$ram || !$storage) {
            return response()->json(['success' => false, 'error' => 'Invalid component key provided.'], 400);
        }

        // Apply clamping based on motherboard slots
        $cpuCount = min($request->cpu_count, $mb->cpu_slots);
        $ramCount = min($request->ram_count, $mb->ram_slots);
        $storageCount = min($request->storage_count, $mb->storage_slots);

        // Calculate aggregated metrics
        $totalCores = ($cpu->cores ?? 0) * $cpuCount;
        $totalRamGb = ($ram->size_gb ?? 0) * $ramCount;
        $totalStorageTb = ($storage->size_tb ?? 0) * $storageCount;

        // Note: Prices and Power specs can vary, here we assume all components have them
        $totalPowerDraw = ($mb->base_power_draw ?? 0) 
            + (($cpu->power_draw_w ?? 0) * $cpuCount) 
            + (($ram->power_draw_w ?? 0) * $ramCount) 
            + (($storage->power_draw_w ?? 0) * $storageCount);

        // Heat output in kW (approx 90% of power draw is converted to heat for simple models)
        $totalHeatOutputKw = ($totalPowerDraw / 1000) * 0.9;
        
        $totalPrice = ($mb->price ?? 0)
            + (($cpu->price ?? 0) * $cpuCount)
            + (($ram->price ?? 0) * $ramCount)
            + (($storage->price ?? 0) * $storageCount);

        // Simple vServer capacity logic based on cores, ram, and storage
        // A standard VM needs ~2 cores, ~4GB RAM, and ~0.1TB storage
        $vserverCapCores = floor($totalCores / 2);
        $vserverCapRam = floor($totalRamGb / 4);
        $vserverCapStorage = floor($totalStorageTb / 0.1);
        $vserverCapacity = min($vserverCapCores, $vserverCapRam, $vserverCapStorage);

        return response()->json([
            'success' => true,
            'data' => [
                'cpu_cores' => $totalCores,
                'ram_gb' => $totalRamGb,
                'storage_tb' => $totalStorageTb,
                'size_u' => $mb->size_u,
                'power_draw_kw' => $totalPowerDraw / 1000,
                'heat_output_kw' => $totalHeatOutputKw,
                'vserver_capacity' => max(0, $vserverCapacity),
                'total_price' => $totalPrice,
            ]
        ]);
    }

    /**
     * Assemble a server from components.
     */
    public function assembleServer(Request $request): JsonResponse
    {
        $request->validate([
            'rack_id' => 'required|uuid',
            'slot' => 'required|integer',
            'motherboard_id' => 'required|uuid',
            'cpu_ids' => 'required|array',
            'ram_ids' => 'required|array',
            'storage_ids' => 'required|array',
            'name' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $rack = ServerRack::whereHas('room', fn($q) => $q->where('user_id', $user->id))
            ->find($request->rack_id);

        if (!$rack) {
            return response()->json(['success' => false, 'error' => 'Rack not found'], 404);
        }

        return DB::transaction(function () use ($user, $rack, $request) {
            // Find motherboard
            $motherboard = UserComponent::where('user_id', $user->id)
                ->where('id', $request->motherboard_id)
                ->where('status', 'inventory')
                ->where('component_type', 'motherboard')
                ->first();

            if (!$motherboard) {
                throw new \Exception("Motherboard not found in inventory");
            }

            $mbConfig = $motherboard->getConfig();

            // Validate slot
            if (!$rack->canFitServerAt($request->slot, $mbConfig['size_u'])) {
                throw new \Exception("Server does not fit in selected slot");
            }

            // Find other components
            $cpus = UserComponent::whereIn('id', $request->cpu_ids)->where('user_id', $user->id)->get();
            $rams = UserComponent::whereIn('id', $request->ram_ids)->where('user_id', $user->id)->get();
            $storages = UserComponent::whereIn('id', $request->storage_ids)->where('user_id', $user->id)->get();

            // Validate counts against motherboard slots
            if ($cpus->count() > $mbConfig['cpu_slots'] || $cpus->count() === 0) {
                throw new \Exception("Invalid CPU count for this motherboard");
            }
            if ($rams->count() > $mbConfig['ram_slots'] || $rams->count() === 0) {
                throw new \Exception("Invalid RAM count for this motherboard");
            }
            if ($storages->count() > $mbConfig['storage_slots'] || $storages->count() === 0) {
                throw new \Exception("Invalid Storage count for this motherboard");
            }

            // Calculate aggregations
            $totalCores = $cpus->sum(fn($c) => $c->getConfig()['cores'] ?? 0);
            $totalRam = $rams->sum(fn($c) => $c->getConfig()['size_gb'] ?? 0);
            $totalStorage = $storages->sum(fn($c) => $c->getConfig()['size_tb'] ?? 0);
            $totalPower = $mbConfig['base_power_draw_w'] ?? 20; // Base motherboard power
            $totalPower += $cpus->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);
            $totalPower += $rams->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);
            $totalPower += $storages->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);

            // AGGREGATE LEASING
            $totalLeaseCost = $motherboard->lease_cost_per_hour;
            $totalLeaseCost += $cpus->sum('lease_cost_per_hour');
            $totalLeaseCost += $rams->sum('lease_cost_per_hour');
            $totalLeaseCost += $storages->sum('lease_cost_per_hour');
            
            $isLeasedServer = $motherboard->is_leased || 
                              $cpus->contains('is_leased', true) || 
                              $rams->contains('is_leased', true) || 
                              $storages->contains('is_leased', true);

            // Create the server
            $server = Server::create([
                'rack_id' => $rack->id,
                'type' => 'custom', // Special type for modular servers
                'model_name' => $request->name ?: 'Custom ' . ($mbConfig['size_u'] . 'U'),
                'size_u' => $mbConfig['size_u'],
                'start_slot' => $request->slot,
                'power_draw_kw' => $totalPower / 1000,
                'heat_output_kw' => $totalPower / 1000,
                'cpu_cores' => $totalCores,
                'ram_gb' => $totalRam,
                'storage_tb' => $totalStorage,
                'vserver_capacity' => $totalCores * 4, // Assume 4 VServers per core for modular builds
                'vservers_used' => 0,
                'bandwidth_mbps' => 1000, // Default 1Gbps for custom builds
                'status' => 'offline',
                'health' => 100,
                'purchase_cost' => 0, // Components already paid for
                'is_leased' => $isLeasedServer,
                'lease_cost_per_hour' => $totalLeaseCost,
                'specs' => [
                    'is_custom' => true,
                    'motherboard_id' => $motherboard->id,
                    'cpu_ids' => $cpus->pluck('id')->toArray(),
                    'ram_ids' => $rams->pluck('id')->toArray(),
                    'storage_ids' => $storages->pluck('id')->toArray(),
                ]
            ]);

            // Update component statuses
            $motherboard->update(['status' => 'installed', 'assigned_server_id' => $server->id]);
            foreach ($cpus as $c) $c->update(['status' => 'installed', 'assigned_server_id' => $server->id]);
            foreach ($rams as $c) $c->update(['status' => 'installed', 'assigned_server_id' => $server->id]);
            foreach ($storages as $c) $c->update(['status' => 'installed', 'assigned_server_id' => $server->id]);

            $rack->recalculatePowerAndHeat();

            return response()->json([
                'success' => true,
                'message' => 'Server assembled successfully',
                'data' => $server->toGameState()
            ]);
        });
    }

    /**
     * Disassemble a modular server.
     */
    public function disassembleServer(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->find($id);

        if (!$server) {
            return response()->json(['success' => false, 'error' => 'Server not found'], 404);
        }

        if ($server->status !== 'offline') {
            return response()->json(['success' => false, 'error' => 'Power off server before disassembly'], 400);
        }

        if (!$server->specs || !($server->specs['is_custom'] ?? false)) {
            return response()->json(['success' => false, 'error' => 'Not a modular server'], 400);
        }

        return DB::transaction(function () use ($server) {
            $rack = $server->rack;

            // --- FEATURE 265: COMPLIANCE DATA DESTRUCTION ---
            // Detect if this server held sensitive data
            $hasSensitiveData = \App\Models\CustomerOrder::where('assigned_server_id', $server->id)
                ->whereIn('sla_tier', ['enterprise', 'whale'])
                ->exists();

            // Return all assigned components to inventory
            foreach (UserComponent::where('assigned_server_id', $server->id)->get() as $comp) {
                $updateData = ['status' => 'inventory', 'assigned_server_id' => null];
                
                // If sensitive, mark storage components for shredding
                if ($hasSensitiveData && $comp->component_type === 'storage') {
                    $updateData['needs_shredding'] = true;
                    \App\Models\GameLog::log($user, "Storage component {$comp->id} marked for SECURE SHREDDING due to sensitive customer data.", 'warning', 'compliance');
                }
                
                $comp->update($updateData);
            }

            $server->delete();
            $rack->recalculatePowerAndHeat();

            return response()->json([
                'success' => true,
                'message' => 'Server disassembled and components returned to inventory.'
            ]);
        });
    }

    /**
     * Install a component into a modular server.
     * 
     * @param Request $request
     * @param string $serverId
     * @return JsonResponse
     */
    public function installComponent(Request $request, string $serverId): JsonResponse
    {
        $request->validate([
            'component_id' => 'required|uuid',
            'slot_type' => 'required|string|in:cpu,ram,storage',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->find($serverId);

        if (!$server) {
            return response()->json(['success' => false, 'error' => 'Server not found'], 404);
        }

        if ($server->status !== \App\Enums\ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server must be powered off to modify hardware'], 400);
        }

        if (!($server->specs['is_custom'] ?? false)) {
            return response()->json(['success' => false, 'error' => 'This server is not modular'], 400);
        }

        return DB::transaction(function () use ($user, $server, $request) {
            $component = UserComponent::where('user_id', $user->id)
                ->where('id', $request->component_id)
                ->where('status', 'inventory')
                ->first();

            if (!$component) {
                return response()->json(['success' => false, 'error' => 'Component not found in inventory'], 404);
            }

            if ($component->component_type !== $request->slot_type) {
                return response()->json(['success' => false, 'error' => 'Component type mismatch'], 400);
            }

            // Verify slot capacity
            $specs = $server->specs;
            $mb = UserComponent::find($specs['motherboard_id']);
            if (!$mb) throw new \Exception("Motherboard missing");
            
            $mbConfig = $mb->getConfig();
            $maxSlots = match ($request->slot_type) {
                'cpu' => $mbConfig['cpu_slots'],
                'ram' => $mbConfig['ram_slots'],
                'storage' => $mbConfig['storage_slots'],
                default => 0
            };

            $currentIds = $specs[$request->slot_type . '_ids'] ?? [];
            if (count($currentIds) >= $maxSlots) {
                return response()->json(['success' => false, 'error' => 'All ' . strtoupper($request->slot_type) . ' slots are full'], 400);
            }

            // Install
            $component->update([
                'status' => 'installed',
                'assigned_server_id' => $server->id
            ]);

            $currentIds[] = $component->id;
            $specs[$request->slot_type . '_ids'] = $currentIds;
            $server->specs = $specs;
            $server->save();

            $this->recalculateServerStats($server);

            return response()->json([
                'success' => true,
                'message' => 'Component installed',
                'data' => $server->toGameState()
            ]);
        });
    }

    /**
     * Remove a component from a modular server.
     * 
     * @param Request $request
     * @param string $serverId
     * @return JsonResponse
     */
    public function removeComponent(Request $request, string $serverId): JsonResponse
    {
        $request->validate([
            'component_id' => 'required',
            'slot_type' => 'required|string|in:cpu,ram,storage',
        ]);

        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->find($serverId);

        if (!$server) {
            return response()->json(['success' => false, 'error' => 'Server not found'], 404);
        }

        if ($server->status !== \App\Enums\ServerStatus::OFFLINE) {
            return response()->json(['success' => false, 'error' => 'Server must be powered off to modify hardware'], 400);
        }

        if (!($server->specs['is_custom'] ?? false)) {
            return response()->json(['success' => false, 'error' => 'This server is not modular'], 400);
        }

        return DB::transaction(function () use ($user, $server, $request) {
            $component = UserComponent::where('user_id', $user->id)
                ->where('id', $request->component_id)
                ->where('assigned_server_id', $server->id)
                ->first();

            if (!$component) {
                return response()->json(['success' => false, 'error' => 'Component not installed in this server'], 404);
            }

            // Remove
            $component->update([
                'status' => 'inventory',
                'assigned_server_id' => null
            ]);

            $specs = $server->specs;
            $key = $request->slot_type . '_ids';
            $ids = $specs[$key] ?? [];
            
            // Remove ID from array
            $index = array_search($component->id, $ids);
            if ($index !== false) {
                unset($ids[$index]);
            }
            
            $specs[$key] = array_values($ids);
            $server->specs = $specs;
            $server->save();

            $this->recalculateServerStats($server);

            return response()->json([
                'success' => true,
                'message' => 'Component removed',
                'data' => $server->toGameState()
            ]);
        });
    }

    /**
     * Recalculate server stats based on installed components.
     */
    private function recalculateServerStats(Server $server): void
    {
        $specs = $server->specs;
        
        $mb = UserComponent::find($specs['motherboard_id']);
        if (!$mb) return;
        $mbConfig = $mb->getConfig();

        $cpus = UserComponent::whereIn('id', $specs['cpu_ids'] ?? [])->get();
        $rams = UserComponent::whereIn('id', $specs['ram_ids'] ?? [])->get();
        $storages = UserComponent::whereIn('id', $specs['storage_ids'] ?? [])->get();

        $totalCores = $cpus->sum(fn($c) => $c->getConfig()['cores'] ?? 0);
        $totalRam = $rams->sum(fn($c) => $c->getConfig()['size_gb'] ?? 0);
        $totalStorage = $storages->sum(fn($c) => $c->getConfig()['size_tb'] ?? 0);
        
        $totalPower = $mbConfig['base_power_draw_w'] ?? 20;
        $totalPower += $cpus->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);
        $totalPower += $rams->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);
        $totalPower += $storages->sum(fn($c) => $c->getConfig()['power_draw_w'] ?? 0);

        $server->update([
            'cpu_cores' => $totalCores,
            'ram_gb' => $totalRam,
            'storage_tb' => $totalStorage,
            'power_draw_kw' => $totalPower / 1000,
            'heat_output_kw' => $totalPower / 1000,
            'vserver_capacity' => $totalCores * 4,
        ]);
        
        if ($server->rack) {
            $server->rack->recalculatePowerAndHeat();
        }
    }

    /**
     * Purchase spare parts for maintenance.
     */
    public function purchaseSpareParts(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $economy = $user->economy;
        $costPerPart = 150; // Standard price for a maintenance kit
        $totalCost = $request->amount * $costPerPart;

        if ($economy->balance < $totalCost) {
            return response()->json(['success' => false, 'error' => 'Insufficient funds'], 400);
        }

        return DB::transaction(function () use ($user, $economy, $totalCost, $request) {
            $economy->balance -= $totalCost;
            $economy->spare_parts_count += $request->amount;
            $economy->save();

            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => -$totalCost,
                'type' => 'expense',
                'category' => 'maintenance',
                'description' => 'Purchased ' . $request->amount . 'x Server Spare Parts',
                'balance_after' => $economy->balance
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchased ' . $request->amount . ' spare parts',
                'spare_parts_count' => $economy->spare_parts_count
            ]);
        });
    }

    /**
     * Manually maintain a server using spare parts.
     */
    public function maintainServer(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $server = Server::whereHas('rack.room', fn($q) => $q->where('user_id', $user->id))
            ->find($id);

        if (!$server) {
            return response()->json(['success' => false, 'error' => 'Server not found'], 404);
        }

        if (!$server->status->isRepairable()) {
            return response()->json(['success' => false, 'error' => 'Server health has reached 0%. It is beyond repair. Scrap it for parts.'], 400);
        }

        $economy = $user->economy;
        if ($economy->spare_parts_count < 1) {
            return response()->json(['success' => false, 'error' => 'No spare parts available. Purchase kits first.'], 400);
        }

        $server->health = min(100, $server->health + 25); // Restores 25% health
        $server->last_maintenance_at = now();
        $server->addMaintenanceLogEntry('maintenance', 'Manual hardware maintenance using spare parts.', 0);
        $server->save();

        $economy->spare_parts_count--;
        $economy->save();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance performed. Health restored to ' . round($server->health) . '%',
            'data' => $server->toGameState()
        ]);
    }

    /**
     * Get current resale market trends/multipliers.
     */
    public function getResaleTrends(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Use cache to keep trends stable for 30 minutes
        $trends = cache()->remember('hardware_resale_trends', 1800, function() {
            return [
                'cpu' => rand(85, 125) / 100,
                'ram' => rand(90, 140) / 100, // RAM more volatile
                'storage' => rand(80, 110) / 100,
                'motherboard' => rand(95, 105) / 100,
                'last_update' => now()->toIso8601String()
            ];
        });

        // Crisis Modifier: Hardware Shortage spikes resale value
        $crisisService = app(\App\Services\Game\GlobalCrisisService::class);
        $mods = $crisisService->getActiveModifiers($user);
        
        $multiplier = $mods['hardware_cost'] ?? 1.0;
        if ($multiplier > 1.0) {
            // If new hardware is 2x expensive, secondary market value also spikes (but less aggressively)
            foreach (['cpu', 'ram', 'storage', 'motherboard'] as $type) {
                $trends[$type] *= (1.0 + (($multiplier - 1.0) * 0.7));
            }
        }

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * Sell a single component from the inventory back to the market.
     */
    public function sellComponent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;
        
        $component = UserComponent::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$component) {
            return response()->json(['success' => false, 'error' => 'Component not found'], 404);
        }

        if ($component->status !== 'inventory') {
            return response()->json(['success' => false, 'error' => 'Component must be unassigned/in inventory to be sold'], 400);
        }

        if ($component->needs_shredding) {
            return response()->json([
                'success' => false, 
                'error' => 'COMPLIANCE VIOLATION: This component contains sensitive data and MUST be shredded before resale.'
            ], 403);
        }

        return DB::transaction(function () use ($user, $economy, $component) {
            $config = $component->getConfig();
            $basePrice = $config['price'] ?? 0;
            
            // --- DYNAMIC RESALE VALUE ---
            $trendsRes = $this->getResaleTrends(request());
            $trends = $trendsRes->getData()->data;
            $type = $component->component_type;
            $marketMult = $trends->$type ?? 1.0;

            $conditionMult = max(0.1, $component->health / 100);
            
            // Base resale is 40%, then multiplied by market trend and health
            $resaleValue = round($basePrice * 0.4 * $marketMult * $conditionMult);

            // --- FEATURE 192: HAZARDOUS WASTE DISPOSAL FEES ---
            $disposalFee = 0;
            $isBroken = ($component->health < 30);
            
            if ($isBroken) {
                $baseFee = 75.0; // Standard disposal fee for scrap
                $reduction = app(\App\Services\Game\ResearchService::class)->getBonus($user, 'waste_cost_reduction');
                $disposalFee = $baseFee * (1.0 - $reduction);
                
                if ($disposalFee > 0) {
                     \App\Models\GameLog::log($user, "Charged \${$disposalFee} Hazardous Waste Disposal Fee for broken component.", 'warning', 'economy');
                }
            }

            $netAmount = $resaleValue - $disposalFee;

            $economy->balance += $netAmount;
            $economy->save();

            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => $netAmount,
                'type' => $netAmount >= 0 ? 'income' : 'expense',
                'category' => 'hardware',
                'description' => ($isBroken ? 'Scrapped ' : 'Liquidated ') . 'component ' . ($config['name'] ?? 'Unknown') . ($disposalFee > 0 ? " (incl. disposal fee)" : ""),
                'balance_after' => $economy->balance
            ]);

            $component->delete();

            return response()->json([
                'success' => true,
                'message' => $isBroken 
                    ? 'Component scrapped. Net: $' . number_format($netAmount)
                    : 'Component liquidated for $' . number_format($netAmount),
                'resale_value' => $resaleValue,
                'disposal_fee' => $disposalFee,
                'net_amount' => $netAmount
            ]);
        });
    }
    /**
     * Shred a component that needs data destruction.
     */
    public function shredComponent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;
        
        $component = UserComponent::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$component) {
            return response()->json(['success' => false, 'error' => 'Component not found'], 404);
        }

        if (!$component->needs_shredding) {
            return response()->json(['success' => false, 'error' => 'This component does not require shredding.'], 400);
        }

        return DB::transaction(function () use ($user, $economy, $component) {
            // Shredding has a small cost
            $cost = 50;
            if ($economy->balance < $cost) {
                return response()->json(['success' => false, 'error' => "Insufficient balance (\${$cost})"], 400);
            }

            $economy->balance -= $cost;
            $economy->shred_count++;
            $economy->save();

            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => -$cost,
                'type' => 'expense',
                'category' => 'compliance',
                'description' => "Secured shredding of storage component",
                'balance_after' => $economy->balance
            ]);

            // Clear the flag
            $component->update(['needs_shredding' => false]);

            \App\Models\GameLog::log($user, "Securely shredded storage component. Compliance certification progress increased.", 'success', 'compliance');

            return response()->json([
                'success' => true,
                'message' => 'Component shredded and cleared for resale.',
                'shred_count' => $economy->shred_count
            ]);
        });
    }

    /**
     * Buyout a leased component.
     */
    public function buyoutComponent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $economy = $user->economy;
        
        $component = UserComponent::where('user_id', $user->id)
            ->where('id', $id)
            ->where('is_leased', true)
            ->first();

        if (!$component) {
            return response()->json(['success' => false, 'error' => 'Leased component not found'], 404);
        }

        $config = $component->getConfig();
        $basePrice = $config['price'] ?? 0;
        
        // Buyout calculation: 75% of original price
        $buyoutPrice = round($basePrice * 0.75);

        if ($economy->balance < $buyoutPrice) {
            return response()->json(['success' => false, 'error' => "Insufficient balance (\${$buyoutPrice}) for buyout"], 400);
        }

        return DB::transaction(function () use ($user, $economy, $component, $buyoutPrice, $config) {
            $economy->balance -= $buyoutPrice;
            $economy->save();

            \App\Models\PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => -$buyoutPrice,
                'type' => 'expense',
                'category' => 'hardware',
                'description' => "Lease buyout for " . ($config['name'] ?? 'Component'),
                'balance_after' => $economy->balance
            ]);

            $component->update([
                'is_leased' => false,
                'lease_cost_per_hour' => 0
            ]);

            // If the component is in a server, we need to update the server's lease cost
            if ($component->assigned_server_id) {
                $server = $component->server;
                if ($server) {
                    $this->recalculateServerLeaseCost($server);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Component bought out successfully. It is now your property.',
                'data' => $component->toGameState()
            ]);
        });
    }

    /**
     * Return a leased component.
     */
    public function returnLeasedComponent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        $component = UserComponent::where('user_id', $user->id)
            ->where('id', $id)
            ->where('is_leased', true)
            ->first();

        if (!$component) {
            return response()->json(['success' => false, 'error' => 'Leased component not found'], 404);
        }

        return DB::transaction(function () use ($user, $component) {
            $serverId = $component->assigned_server_id;
            $server = $serverId ? $component->server : null;

            // If in a server, it must be offline to "unplug" it for return
            if ($server && $server->status !== \App\Enums\ServerStatus::OFFLINE) {
                return response()->json(['success' => false, 'error' => 'Server must be offline to return installed components'], 400);
            }

            $componentName = $component->getConfig()['name'] ?? 'Component';

            if ($server) {
                // Update server specs meta (remove ID from list)
                $specs = $server->specs;
                $typeKey = $component->component_type . '_ids';
                
                if ($component->component_type === 'motherboard') {
                    // If motherboard is returned, the whole server is gone!
                    $server->delete();
                    $component->delete();
                    return response()->json(['success' => true, 'message' => "Motherboard returned. Server '{$server->model_name}' has been decommissioned."]);
                }

                $ids = $specs[$typeKey] ?? [];
                $index = array_search($component->id, $ids);
                if ($index !== false) {
                    unset($ids[$index]);
                    $specs[$typeKey] = array_values($ids);
                    $server->specs = $specs;
                    $server->save();
                    
                    $this->recalculateServerStats($server);
                    $this->recalculateServerLeaseCost($server);
                }
            }

            $component->delete();
            \App\Models\GameLog::log($user, "Leased component '{$componentName}' returned to vendor.", 'info', 'hardware');

            return response()->json([
                'success' => true,
                'message' => 'Leased component returned successfully.'
            ]);
        });
    }

    private function recalculateServerLeaseCost(Server $server): void
    {
        $components = UserComponent::where('assigned_server_id', $server->id)->get();
        $totalLease = $components->sum('lease_cost_per_hour');
        $hasLease = $components->contains('is_leased', true);
        
        $server->update([
            'is_leased' => $hasLease,
            'lease_cost_per_hour' => $totalLease
        ]);
    }
}
