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
        protected ResearchService $researchService
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
        int $targetSlot
    ): array {
        // Get server specs from market/catalog
        $specs = $this->getServerSpecs($serverType, $modelKey);
        if (!$specs) {
            return ['success' => false, 'error' => 'Invalid server model'];
        }

        // Validate ownership
        $room = $rack->room;
        if ($room->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Check level requirement
        $economy = $user->economy;
        $serverTypeEnum = ServerType::from($serverType);
        if ($economy->level < $serverTypeEnum->requiredLevel()) {
            return ['success' => false, 'error' => 'Level too low for this server type'];
        }

        // Check if player can afford
        if (!$economy->canAfford($specs['purchaseCost'])) {
            return ['success' => false, 'error' => 'Insufficient funds'];
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
        return DB::transaction(function () use ($user, $rack, $specs, $targetSlot, $serverType, $modelKey, $economy) {
            // Create server
            $server = Server::create([
                'rack_id' => $rack->id,
                'type' => $serverType,
                'model_name' => $specs['modelName'],
                'size_u' => $specs['sizeU'],
                'start_slot' => $targetSlot,
                'power_draw_kw' => $specs['powerDrawKw'],
                'heat_output_kw' => $specs['heatOutputKw'],
                'cpu_cores' => $specs['cpuCores'],
                'ram_gb' => $specs['ramGb'],
                'storage_tb' => $specs['storageTb'],
                'bandwidth_mbps' => $specs['bandwidthMbps'],
                'vserver_capacity' => $specs['vserverCapacity'] ?? 0,
                'status' => ServerStatus::OFFLINE,
                'health' => 100.0,
                'purchase_cost' => $specs['purchaseCost'],
                'monthly_depreciation' => $specs['purchaseCost'] * 0.02, // 2% monthly
            ]);

            // Deduct cost
            if (!$economy->debit($specs['purchaseCost'], "Purchased {$specs['modelName']}", 'hardware', $server)) {
                throw new \Exception("Insufficient funds");
            }

            // Update rack
            $rack->recalculatePowerAndHeat();

            // Dispatch event for WebSocket
            broadcast(new ServerPlaced($user, $rack, $server))->toOthers();

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
        $researchService = app(ResearchService::class);
        $speedBonus = $researchService->getBonus($user, 'provisioning_speed');

        $server->startProvisioning($provisioningTime, $speedBonus);

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

            broadcast(new RackUpdated($user, $rack->fresh(['servers'])))->toOthers();

            return [
                'success' => true,
                'rack' => $rack->fresh(['servers']),
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

        // Gate 42U behind research
        if ($rackTypeEnum === RackType::RACK_42U) {
            if ($this->researchService->getBonus($user, 'unlock_rack_42u') < 1) {
                return ['success' => false, 'error' => 'Complete "High-Density Architecture" research first!'];
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
        return [
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
                ],
                'vs_pro' => [
                    'modelName' => 'VNode Pro',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.6,
                    'heatOutputKw' => 0.5,
                    'cpuCores' => 32,
                    'ramGb' => 128,
                    'storageTb' => 4,
                    'bandwidthMbps' => 10000,
                    'vserverCapacity' => 16,
                    'purchaseCost' => 3500,
                ],
                'vs_enterprise' => [
                    'modelName' => 'VNode Enterprise',
                    'sizeU' => 4,
                    'powerDrawKw' => 1.2,
                    'heatOutputKw' => 1.0,
                    'cpuCores' => 128,
                    'ramGb' => 512,
                    'storageTb' => 8,
                    'bandwidthMbps' => 25000,
                    'vserverCapacity' => 48,
                    'purchaseCost' => 12000,
                ],
            ],
            'dedicated' => [
                'ded_basic' => [
                    'modelName' => 'Dedicated Basic',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.35,
                    'heatOutputKw' => 0.3,
                    'cpuCores' => 4,
                    'ramGb' => 16,
                    'storageTb' => 2,
                    'bandwidthMbps' => 1000,
                    'purchaseCost' => 600,
                ],
                'ded_performance' => [
                    'modelName' => 'Dedicated Performance',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.8,
                    'heatOutputKw' => 0.7,
                    'cpuCores' => 16,
                    'ramGb' => 64,
                    'storageTb' => 4,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 2500,
                ],
                'ded_monster' => [
                    'modelName' => 'Dedicated Monster',
                    'sizeU' => 4,
                    'powerDrawKw' => 1.5,
                    'heatOutputKw' => 1.3,
                    'cpuCores' => 64,
                    'ramGb' => 256,
                    'storageTb' => 16,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 8000,
                ],
            ],
            'gpu_server' => [
                'gpu_compute' => [
                    'modelName' => 'GPU Compute',
                    'sizeU' => 4,
                    'powerDrawKw' => 2.5,
                    'heatOutputKw' => 2.2,
                    'cpuCores' => 32,
                    'ramGb' => 128,
                    'storageTb' => 4,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 25000,
                ],
                'gpu_ai' => [
                    'modelName' => 'GPU AI Cluster Node',
                    'sizeU' => 4,
                    'powerDrawKw' => 4.0,
                    'heatOutputKw' => 3.5,
                    'cpuCores' => 64,
                    'ramGb' => 512,
                    'storageTb' => 8,
                    'bandwidthMbps' => 100000,
                    'purchaseCost' => 80000,
                ],
            ],
            'storage_server' => [
                'storage_basic' => [
                    'modelName' => 'Storage Array Basic',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.4,
                    'heatOutputKw' => 0.35,
                    'cpuCores' => 4,
                    'ramGb' => 16,
                    'storageTb' => 48,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 3000,
                ],
                'storage_nas' => [
                    'modelName' => 'Enterprise NAS',
                    'sizeU' => 4,
                    'powerDrawKw' => 0.8,
                    'heatOutputKw' => 0.7,
                    'cpuCores' => 8,
                    'ramGb' => 64,
                    'storageTb' => 192,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 15000,
                ],
            ],
        ];
    }
}
