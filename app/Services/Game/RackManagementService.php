<?php

namespace App\Services\Game;

use App\Enums\RackType;
use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Events\RackUpdated;
use App\Events\ServerPlaced;
use App\Events\ServerStatusChanged;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\ServerRack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RackManagementService
{
    public function __construct(
        protected ResearchService $researchService,
        protected AchievementService $achievementService
    ) {}

    /**
     * Attempt to place a server in a rack at specified slot
     * 
     * @return array{success: bool, error?: string, server?: Server}
     */
    public function placeServer(
        User $user,
        ServerRack $rack,
        string $serverType,
        string $modelKey,
        int $targetSlot,
        int $hardwareGeneration = 2,
        bool $isLeased = false
    ): array {
        // Get server specs from market/catalog
        $specs = $this->getServerSpecs($serverType, $modelKey);
        if (!$specs) {
            return ['success' => false, 'error' => 'Invalid server model'];
        }

        // Resolve hardware generation and apply multipliers
        $gen = \App\Models\HardwareGeneration::where('generation', $hardwareGeneration)->first();
        if (!$gen || !$gen->is_available) {
            return ['success' => false, 'error' => 'Hardware generation not available'];
        }
        $specs['cpuCores'] = (int) ceil($specs['cpuCores'] * $gen->efficiency_multiplier);
        $specs['ramGb'] = (int) ceil($specs['ramGb'] * $gen->efficiency_multiplier);
        $specs['powerDrawKw'] = round($specs['powerDrawKw'] * $gen->power_multiplier, 2);
        $specs['heatOutputKw'] = round($specs['heatOutputKw'] * $gen->power_multiplier, 2);
        $specs['purchaseCost'] = round($specs['purchaseCost'] * $gen->price_multiplier, 2);

        // Validate ownership
        $room = $rack->room;
        if ($room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Check level requirement
        $economy = $user->economy;
        $serverTypeEnum = \App\Enums\ServerType::from($serverType);
        if ($economy->level < $serverTypeEnum->requiredLevel()) {
            return ['success' => false, 'error' => 'Level too low for this server type'];
        }

        // LEASING CALCULATION
        $upfrontCost = 0;
        $leaseCostPerHour = 0;
        
        if ($isLeased) {
            // Lease costs ~12% of value per month (720 hours) - slightly higher for pre-builts than parts
            // Plus a 5% setup fee
            $leaseCostPerHour = ($specs['purchaseCost'] * 0.12) / 720;
            $upfrontCost = ($specs['purchaseCost'] * 0.05); 
        } else {
            $upfrontCost = $specs['purchaseCost'];
            
            // REPUTATION MILESTONE: Budget Specialist
            if ($economy->getSpecializedReputation('budget') >= 75) {
                $upfrontCost *= 0.90; // 10% discount
            }

            // SPECIALIZATION: Hardware Cost Reduction
            $specService = app(\App\Services\Game\SpecializationService::class);
            $specMods = $specService->getActiveModifiers($user);
            if (isset($specMods['passives']['hardware_cost_reduction'])) {
                $upfrontCost *= (1.0 - (float) $specMods['passives']['hardware_cost_reduction']);
            }
        }

        if (!$economy->canAfford($upfrontCost)) {
            return ['success' => false, 'error' => 'Insufficient funds for ' . ($isLeased ? 'setup fee' : 'purchase')];
        }

        // Validate slot availability
        $sizeU = $specs['sizeU'];
        if (!$rack->canFitServerAt($targetSlot, $sizeU)) {
            return ['success' => false, 'error' => 'Cannot place server at this position'];
        }

        // Check power capacity (rack level)
        $powerDraw = $specs['powerDrawKw'];
        if ($rack->getAvailablePowerKw() < $powerDraw) {
            return ['success' => false, 'error' => 'Insufficient power capacity in rack'];
        }

        // Check room-level power capacity
        $currentRoomPower = $room->getCurrentPowerUsage();
        if ($currentRoomPower + $powerDraw > $room->max_power_kw) {
            return ['success' => false, 'error' => 'Room power grid capacity exceeded (' . round($currentRoomPower + $powerDraw, 1) . '/' . $room->max_power_kw . ' kW)'];
        }

        // Check room cooling capacity
        $heatOutput = $specs['heatOutputKw'];
        $currentHeat = $room->getCurrentHeatOutput();
        if ($currentHeat + $heatOutput > $room->max_cooling_kw) {
            return ['success' => false, 'error' => 'Insufficient cooling capacity in room'];
        }

        // All validations passed - create server
        return DB::transaction(function () use ($user, $rack, $specs, $targetSlot, $serverType, $modelKey, $economy, $gen, $upfrontCost, $isLeased, $leaseCostPerHour) {
            // Create server
            $server = Server::create([
                'rack_id' => $rack->id,
                'type' => $serverType,
                'model_name' => $specs['modelName'] . ' (Gen ' . $gen->generation . ')',
                'hardware_generation' => $gen->generation,
                'size_u' => $specs['sizeU'],
                'start_slot' => $targetSlot,
                'power_draw_kw' => $specs['powerDrawKw'],
                'heat_output_kw' => $specs['heatOutputKw'],
                'cpu_cores' => $specs['cpuCores'],
                'ram_gb' => $specs['ramGb'],
                'storage_tb' => $specs['storageTb'],
                'bandwidth_mbps' => $specs['bandwidthMbps'],
                'vserver_capacity' => $specs['vserverCapacity'] ?? 0,
                'battery_capacity_kwh' => $specs['batteryCapacityKwh'] ?? 0,
                'battery_level_kwh' => $specs['batteryCapacityKwh'] ?? 0,
                'status' => ServerStatus::OFFLINE,
                'health' => 100.0,
                'purchase_cost' => $isLeased ? 0 : $specs['purchaseCost'],
                'resale_value' => $isLeased ? 0 : round($specs['purchaseCost'] * 0.80, 2),
                'monthly_depreciation' => round($specs['purchaseCost'] * $gen->depreciation_rate, 2),
                'purchase_date' => now(),
                'specs' => $specs,
                'is_leased' => $isLeased,
                'lease_cost_per_hour' => $leaseCostPerHour,
                'cpu_clock_mhz' => $specs['baseClockMhz'] ?? 3200,
                'cpu_voltage_v' => $specs['baseVoltageV'] ?? 1.200,
                'base_clock_mhz' => $specs['baseClockMhz'] ?? 3200,
                'base_voltage_v' => $specs['baseVoltageV'] ?? 1.200,
            ]);

            // Deduct cost
            if (!$economy->debit($upfrontCost, ($isLeased ? 'Leased ' : 'Purchased ') . "{$specs['modelName']}", 'hardware', $server)) {
                throw new \Exception("Insufficient funds");
            }

            // Update rack
            $rack->recalculatePowerAndHeat();

            // Dispatch event for WebSocket
            broadcast(new \App\Events\ServerPlaced($user, $rack, $server))->toOthers();

            // Achievement: Full Rack
            $rack->refresh();
            $slotsUsed = $rack->servers->sum('size_u');
            if ($slotsUsed >= $rack->total_slots) {
                $this->achievementService->unlock($user, 'full_rack');
            }

            // Award XP for placing server
            $economy->addExperience(50);

            return [
                'success' => true,
                'server' => $server,
                'rack' => $rack->fresh(['servers']),
            ];
        });
    }

    /**
     * Move a server to a new slot (drag & drop)
     */
    public function moveServer(
        User $user,
        Server $server,
        ServerRack $targetRack,
        int $targetSlot
    ): array {
        $sourceRack = $server->rack;

        // Validate ownership
        if ($sourceRack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if ($targetRack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // If moving to same position, no-op
        if ($sourceRack->id === $targetRack->id && $server->start_slot === $targetSlot) {
            return [
                'success' => true, 
                'server' => $server, 
                'noChange' => true,
                'sourceRack' => $sourceRack->fresh(['servers']),
                'targetRack' => $targetRack->fresh(['servers']),
            ];
        }

        // Temporarily remove server from current position for validation
        $originalSlot = $server->start_slot;
        $originalRackId = $server->rack_id;

        // Check if target slot is available
        $isValid = $this->validateMoveTarget($server, $targetRack, $targetSlot);
        if (!$isValid['valid']) {
            return ['success' => false, 'error' => $isValid['error']];
        }

        // Check power capacity if moving to different rack
        if ($sourceRack->id !== $targetRack->id) {
            if ($targetRack->getAvailablePowerKw() < $server->power_draw_kw) {
                return ['success' => false, 'error' => 'Insufficient power in target rack'];
            }

            // Check room cooling if moving to different room
            if ($sourceRack->room_id !== $targetRack->room_id) {
                $targetRoom = $targetRack->room;
                if ($targetRoom->getCurrentHeatOutput() + $server->heat_output_kw > $targetRoom->max_cooling_kw) {
                    return ['success' => false, 'error' => 'Insufficient cooling in target room'];
                }
            }
        }

        return DB::transaction(function () use ($user, $server, $sourceRack, $targetRack, $targetSlot) {
            // Update server position
            $server->rack_id = $targetRack->id;
            $server->start_slot = $targetSlot;
            $server->save();

            // Recalculate racks
            $sourceRack->recalculatePowerAndHeat();
            if ($sourceRack->id !== $targetRack->id) {
                $targetRack->recalculatePowerAndHeat();
            }

            // Broadcast update
            broadcast(new RackUpdated($user, $targetRack->fresh(['servers'])))->toOthers();
            if ($sourceRack->id !== $targetRack->id) {
                broadcast(new RackUpdated($user, $sourceRack->fresh(['servers'])))->toOthers();
            }

            return [
                'success' => true,
                'server' => $server->fresh(),
                'sourceRack' => $sourceRack->fresh(['servers']),
                'targetRack' => $targetRack->fresh(['servers']),
            ];
        });
    }

    /**
     * Power on a server (starts provisioning)
     */
    public function powerOnServer(User $user, Server $server): array
    {
        if ($server->rack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if ($server->status !== ServerStatus::OFFLINE) {
            return ['success' => false, 'error' => 'Server is not offline'];
        }

        // Calculate provisioning time based on server specs
        $baseTime = 60; // 1 minute base
        $sizeMultiplier = $server->size_u * 30; // +30s per U
        $provisioningTime = $baseTime + $sizeMultiplier;

        // Apply research bonus
        $speedBonus = $this->researchService->getBonus($user, 'provisioning_speed');

        $server->startProvisioning($provisioningTime, $speedBonus);

        // Award XP for starting provisioning
        $user->economy->addExperience(5);

        broadcast(new ServerStatusChanged($user, $server))->toOthers();

        return [
            'success' => true,
            'server' => $server->fresh(),
            'provisioningTime' => $provisioningTime,
        ];
    }

    /**
     * Power off a server
     */
    public function powerOffServer(User $user, Server $server): array
    {
        if ($server->rack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if (!$server->status->isOperational()) {
            return ['success' => false, 'error' => 'Server is not operational'];
        }

        // Check if server has active orders
        if ($server->activeOrders->count() > 0) {
            return ['success' => false, 'error' => 'Server has active customer orders'];
        }

        $server->status = ServerStatus::OFFLINE;
        $server->save();

        broadcast(new ServerStatusChanged($user, $server))->toOthers();

        return [
            'success' => true,
            'server' => $server->fresh(),
        ];
    }

    /**
     * Clean dust from a rack to improve cooling
     */
    public function cleanRack(User $user, ServerRack $rack): array
    {
        if ($rack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        if ($rack->dust_level < 1.0) {
            return ['success' => false, 'error' => 'Rack is already clean!'];
        }

        $cost = 50.0;
        $economy = $user->economy;

        if (!$economy->canAfford($cost)) {
            return ['success' => false, 'error' => 'Insufficient funds to clean rack ($50.00)'];
        }

        return DB::transaction(function () use ($user, $rack, $economy, $cost) {
            $rack->dust_level = 0.00;
            $rack->save();

            $economy->debit($cost, "Cleaning service for {$rack->name}", 'maintenance', $rack);

            // Award XP for cleaning
            $economy->addExperience(10);

            broadcast(new RackUpdated($user, $rack->fresh(['servers'])))->toOthers();

            return [
                'success' => true,
                'rack' => $rack->fresh(['servers']),
            ];
        });
    }

    /**
     * Modernize an old server to reset its efficiency penalty and health
     */
    public function modernizeServer(User $user, Server $server): array
    {
        if ($server->rack->room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Only allow modernization if it's actually inefficient (penalty > 0) or health is lower
        $penalty = $server->getEfficiencyPenalty();
        if ($penalty <= 0.02 && $server->health >= 98) {
            return ['success' => false, 'error' => 'Server is already at peak operational capacity!'];
        }

        // Must be offline to modernize
        if ($server->status !== ServerStatus::OFFLINE) {
            return ['success' => false, 'error' => 'Server must be powered off for modernization.'];
        }

        // Cost is 40% of purchase cost + $200 handling fee
        $modernizationCost = ($server->purchase_cost * 0.4) + 200;
        $economy = $user->economy;

        if (!$economy->canAfford($modernizationCost)) {
            return [
                'success' => false, 
                'error' => 'Insufficient funds for modernization ($' . number_format($modernizationCost, 2) . ')'
            ];
        }

        return DB::transaction(function () use ($user, $server, $economy, $modernizationCost) {
            // Reset runtime (efficiency) and health
            $server->total_runtime_seconds = 0;
            $server->health = 100.0;
            $server->save();

            $economy->debit($modernizationCost, "Hardware Modernization: {$server->model_name}", 'maintenance', $server);

            // Award XP for modernization
            $economy->addExperience(150);

            broadcast(new ServerStatusChanged($user, $server->fresh()))->toOthers();

            return [
                'success' => true,
                'server' => $server->fresh(),
            ];
        });
    }

    /**
     * Purchase and add a new rack to a room
     */
    public function purchaseRack(User $user, GameRoom $room, string $rackType): array
    {
        if ($room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        $rackTypeEnum = RackType::from($rackType);

        // Check level
        $economy = $user->economy;
        if ($economy->level < $rackTypeEnum->requiredLevel()) {
            return ['success' => false, 'error' => 'Level too low for this rack'];
        }

        // Gate 42U behind research (High-Voltage Racks)
        if ($rackTypeEnum === RackType::RACK_42U) {
            if (!$this->researchService->hasEffect($user, 'unlock', 'rack_hv')) {
                return ['success' => false, 'error' => 'Complete "High-Voltage Racks" research first!'];
            }
        }

        // Check room capacity
        if (!$room->canAddRack()) {
            return ['success' => false, 'error' => 'No space for more racks in this room'];
        }

        // Check funds
        $cost = $rackTypeEnum->purchaseCost();
        if (!$economy->canAfford($cost)) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }

        return DB::transaction(function () use ($user, $room, $rackTypeEnum, $economy, $cost) {
            // Find next available slot
            $usedSlots = $room->racks->pluck('position.slot')->toArray();
            $nextSlot = 0;
            for ($i = 0; $i < $room->max_racks; $i++) {
                if (!in_array($i, $usedSlots)) {
                    $nextSlot = $i;
                    break;
                }
            }

            $rack = ServerRack::create([
                'room_id' => $room->id,
                'type' => $rackTypeEnum,
                'name' => "Rack " . ($room->racks->count() + 1),
                'total_units' => $rackTypeEnum->totalUnits(),
                'max_power_kw' => $rackTypeEnum->maxPowerKw(),
                'position' => ['slot' => $nextSlot],
                'status' => 'operational',
                'temperature' => 22.0,
                'purchase_cost' => $cost,
            ]);

            if (!$economy->debit($cost, "Purchased {$rackTypeEnum->label()}", 'infrastructure', $rack)) {
                throw new \Exception("Insufficient funds");
            }

            // Award XP for rack purchase
            $economy->addExperience(25);

            return [
                'success' => true,
                'rack' => $rack,
                'room' => $room->fresh(['racks.servers']),
            ];
        });
    }

    /**
     * Validate if server can be moved to target
     */
    private function validateMoveTarget(Server $server, ServerRack $targetRack, int $targetSlot): array
    {
        // Get occupied slots excluding this server
        $occupied = [];
        foreach ($targetRack->servers as $existingServer) {
            if ($existingServer->id === $server->id) continue;
            
            for ($i = 0; $i < $existingServer->size_u; $i++) {
                $occupied[] = $existingServer->start_slot + $i;
            }
        }

        // Check if target slots are free
        for ($i = $targetSlot; $i < $targetSlot + $server->size_u; $i++) {
            if ($i < 1 || $i > $targetRack->total_units) {
                return ['valid' => false, 'error' => 'Position out of bounds'];
            }
            if (in_array($i, $occupied)) {
                return ['valid' => false, 'error' => 'Position already occupied'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get server specifications from catalog
     */
    private function getServerSpecs(string $serverType, string $modelKey): ?array
    {
        $catalog = $this->getServerCatalog();
        
        return $catalog[$serverType][$modelKey] ?? null;
    }

    /**
     * Get the server catalog (in production, this would be from database/config)
     */
    public function getServerCatalog(): array
    {
        $defaultCatalog = [
            'vserver_node' => [
                'vs_starter' => [
                    'modelName' => 'VNode Starter',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.3,
                    'heatOutputKw' => 0.25,
                    'cpuCores' => 8,
                    'ramGb' => 32,
                    'storageTb' => 1,
                    'bandwidthMbps' => 1000,
                    'vserverCapacity' => 4,
                    'purchaseCost' => 800,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=vs_starter&backgroundColor=1a1a1b',
                ],
                'vs_pro' => [
                    'modelName' => 'VNode Pro',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.6,
                    'heatOutputKw' => 0.5,
                    'cpuCores' => 16,
                    'ramGb' => 64,
                    'storageTb' => 2,
                    'bandwidthMbps' => 2500,
                    'vserverCapacity' => 8,
                    'purchaseCost' => 1800,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=vs_pro&backgroundColor=1a1a1b',
                ],
            ],
            'dedicated' => [
                'ded_entry' => [
                    'modelName' => 'Zenith Tower',
                    'sizeU' => 4,
                    'powerDrawKw' => 0.5,
                    'heatOutputKw' => 0.4,
                    'cpuCores' => 12,
                    'ramGb' => 48,
                    'storageTb' => 4,
                    'bandwidthMbps' => 1000,
                    'purchaseCost' => 1200,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=ded_entry&backgroundColor=1a1a1b',
                ],
            ],
            'storage_server' => [
                'stor_vault' => [
                    'modelName' => 'DataVault 100',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.4,
                    'heatOutputKw' => 0.3,
                    'cpuCores' => 4,
                    'ramGb' => 16,
                    'storageTb' => 100,
                    'bandwidthMbps' => 5000,
                    'purchaseCost' => 4500,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=stor_vault&backgroundColor=1a1a1b',
                ],
            ],
            'gpu_server' => [
                'gpu_tensor' => [
                    'modelName' => 'TensorCore G1',
                    'sizeU' => 2,
                    'powerDrawKw' => 1.8,
                    'heatOutputKw' => 1.6,
                    'cpuCores' => 16,
                    'ramGb' => 128,
                    'storageTb' => 2,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 12000,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=gpu_tensor&backgroundColor=1a1a1b',
                ],
            ],
            'battery' => [
                'bat_standard' => [
                    'modelName' => 'PowerGate 1U Li-Ion',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.01,
                    'heatOutputKw' => 0.05,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 1200,
                    'batteryCapacityKwh' => 2.5,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=bat_standard&backgroundColor=1a1a1b',
                ],
                'bat_pro' => [
                    'modelName' => 'VoltVault 2U Ultra',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.02,
                    'heatOutputKw' => 0.1,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 2800,
                    'batteryCapacityKwh' => 6.0,
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=bat_pro&backgroundColor=1a1a1b',
                ],
            ],
            'experimental' => [
                'quantum_one' => [
                    'modelName' => 'QM-1 Quantum Node',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.05,
                    'heatOutputKw' => 0.01,
                    'cpuCores' => 1024,
                    'ramGb' => 4096,
                    'storageTb' => 100,
                    'bandwidthMbps' => 10000,
                    'vserverCapacity' => 100,
                    'purchaseCost' => 75000,
                    'isExperimental' => true,
                    'riskType' => 'instability',
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=quantum_one&backgroundColor=1a1a1b',
                ],
                'overclock_monster' => [
                    'modelName' => 'X-TRME Overclock',
                    'sizeU' => 2,
                    'powerDrawKw' => 8.5,
                    'heatOutputKw' => 11.0,
                    'cpuCores' => 256,
                    'ramGb' => 512,
                    'storageTb' => 10,
                    'bandwidthMbps' => 5000,
                    'vserverCapacity' => 32,
                    'purchaseCost' => 35000,
                    'isExperimental' => true,
                    'riskType' => 'meltdown',
                    'imageUrl' => 'https://api.dicebear.com/7.x/identicon/svg?seed=overclock_monster&backgroundColor=1a1a1b',
                ]
            ],
            'hardware_parts' => [
                'mb_standard' => [
                    'modelName' => 'CoreBoard M1',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.1,
                    'heatOutputKw' => 0.08,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 1000,
                    'purchaseCost' => 150,
                    'partType' => 'motherboard',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=mb_standard&backgroundColor=1a1a1b',
                ],
                'mb_enterprise' => [
                    'modelName' => 'CoreBoard X9',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.15,
                    'heatOutputKw' => 0.12,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 2500,
                    'purchaseCost' => 450,
                    'partType' => 'motherboard',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=mb_enterprise&backgroundColor=1a1a1b',
                ],
                'cpu_basic' => [
                    'modelName' => 'Nexon CPU 8C',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.15,
                    'heatOutputKw' => 0.12,
                    'cpuCores' => 8,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 220,
                    'partType' => 'cpu',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=cpu_basic&backgroundColor=1a1a1b',
                ],
                'cpu_performance' => [
                    'modelName' => 'Nexon CPU 32C',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.35,
                    'heatOutputKw' => 0.30,
                    'cpuCores' => 32,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 800,
                    'partType' => 'cpu',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=cpu_perfo&backgroundColor=1a1a1b',
                ],
                'ram_16g' => [
                    'modelName' => 'QuickRAM DDR5 16G',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.02,
                    'heatOutputKw' => 0.01,
                    'cpuCores' => 0,
                    'ramGb' => 16,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 80,
                    'partType' => 'ram',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=ram16&backgroundColor=1a1a1b',
                ],
                'ram_64g' => [
                    'modelName' => 'QuickRAM DDR5 64G',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.05,
                    'heatOutputKw' => 0.03,
                    'cpuCores' => 0,
                    'ramGb' => 64,
                    'storageTb' => 0,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 280,
                    'partType' => 'ram',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=ram64&backgroundColor=1a1a1b',
                ],
                'ssd_1t' => [
                    'modelName' => 'NVMe SSD 1TB',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.01,
                    'heatOutputKw' => 0.005,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 1,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 120,
                    'partType' => 'storage',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=ssd1&backgroundColor=1a1a1b',
                ],
                'ssd_4t' => [
                    'modelName' => 'NVMe SSD 4TB',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.02,
                    'heatOutputKw' => 0.01,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 4,
                    'bandwidthMbps' => 0,
                    'purchaseCost' => 400,
                    'partType' => 'storage',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=ssd4&backgroundColor=1a1a1b',
                ],
                'nic_10g' => [
                    'modelName' => 'NetLink 10G NIC',
                    'sizeU' => 0,
                    'powerDrawKw' => 0.03,
                    'heatOutputKw' => 0.02,
                    'cpuCores' => 0,
                    'ramGb' => 0,
                    'storageTb' => 0,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 350,
                    'partType' => 'network',
                    'imageUrl' => 'https://api.dicebear.com/7.x/bottts/svg?seed=nic10&backgroundColor=1a1a1b',
                ],
            ],
        ];

        return \App\Models\GameConfig::get('server_catalog', $defaultCatalog);
    }
}
