<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\ServerRack;
use App\Services\Game\RackManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RackController extends Controller
{
    public function __construct(
        private RackManagementService $rackService
    ) {}

    /**
     * Get server catalog
     */
    public function getCatalog(): JsonResponse
    {
        $catalog = $this->rackService->getServerCatalog();

        return response()->json([
            'success' => true,
            'data' => $catalog,
            'generations' => app(\App\Services\Game\HardwareDepreciationService::class)->getGenerationComparison(),
        ]);
    }

    /**
     * Purchase a new rack
     */
    public function purchaseRack(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|uuid|exists:game_rooms,id',
            'rack_type' => 'required|string|in:rack_12u,rack_24u,rack_42u',
        ]);

        $user = $request->user();
        $room = GameRoom::findOrFail($request->room_id);

        $result = $this->rackService->purchaseRack($user, $room, $request->rack_type);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rack' => $result['rack']->toGameState(),
                'room' => $result['room']->toGameState(),
            ],
        ]);
    }

    /**
     * Place a server in a rack
     */
    public function placeServer(Request $request): JsonResponse
    {
        $request->validate([
            'rack_id' => 'required|uuid|exists:server_racks,id',
            'server_type' => 'required|string|in:vserver_node,dedicated,gpu_server,storage_server,experimental,battery',
            'model_key' => 'required|string',
            'target_slot' => 'required|integer|min:1',
            'hardware_generation' => 'sometimes|integer|min:1|max:10',
            'is_leased' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $rack = ServerRack::with(['room', 'servers'])->findOrFail($request->rack_id);

        $result = $this->rackService->placeServer(
            $user,
            $rack,
            $request->server_type,
            $request->model_key,
            $request->target_slot,
            $request->input('hardware_generation', 2),
            $request->boolean('is_leased', false)
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $result['server']->toGameState(),
                'rack' => $result['rack']->toGameState(),
            ],
        ]);
    }

    /**
     * Move a server to a new position
     */
    public function moveServer(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
            'target_rack_id' => 'required|uuid|exists:server_racks,id',
            'target_slot' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $server = Server::with(['rack.room'])->findOrFail($request->server_id);
        $targetRack = ServerRack::with(['room', 'servers'])->findOrFail($request->target_rack_id);

        $result = $this->rackService->moveServer(
            $user,
            $server,
            $targetRack,
            $request->target_slot
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $result['server']->toGameState(),
                'sourceRack' => $result['sourceRack']->toGameState(),
                'targetRack' => $result['targetRack']->toGameState(),
            ],
        ]);
    }

    /**
     * Power on a server
     */
    public function powerOn(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
        ]);

        $user = $request->user();
        $server = Server::with(['rack.room'])->findOrFail($request->server_id);

        $result = $this->rackService->powerOnServer($user, $server);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $result['server']->toGameState(),
                'provisioningTime' => $result['provisioningTime'],
            ],
        ]);
    }

    /**
     * Power off a server
     */
    public function powerOff(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
        ]);

        $user = $request->user();
        $server = Server::with(['rack.room', 'activeOrders'])->findOrFail($request->server_id);

        $result = $this->rackService->powerOffServer($user, $server);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $result['server']->toGameState(),
            ],
        ]);
    }

    /**
     * Clean dust from a rack
     */
    public function clean(Request $request): JsonResponse
    {
        $request->validate([
            'rack_id' => 'required|uuid|exists:server_racks,id',
        ]);

        $user = $request->user();
        $rack = ServerRack::with(['room', 'servers'])->findOrFail($request->rack_id);

        $result = $this->rackService->cleanRack($user, $rack);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rack' => $result['rack']->toGameState(),
            ],
        ]);
    }
    /**
     * Modernize an old server
     */
    public function modernize(Request $request): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|uuid|exists:servers,id',
        ]);

        $user = $request->user();
        $server = Server::with(['rack.room'])->findOrFail($request->server_id);

        $result = $this->rackService->modernizeServer($user, $server);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'server' => $result['server']->toGameState(),
            ],
            'message' => 'Server successfully modernized to peak efficiency.'
        ]);
    }

    /**
     * Toggle Colocation mode on a rack
     */
    public function toggleColocation(Request $request): JsonResponse
    {
        $request->validate([
            'rack_id' => 'required|uuid|exists:server_racks,id',
        ]);

        $user = $request->user();
        $rack = ServerRack::with(['room', 'servers'])->findOrFail($request->rack_id);

        $rack->is_colocation_mode = !$rack->is_colocation_mode;
        
        // If turning off, remove all colo units (they leave)
        if (!$rack->is_colocation_mode) {
            $rack->colocation_units = 0;
        }
        
        $rack->recalculatePowerAndHeat();
        $rack->save();

        return response()->json([
            'success' => true,
            'data' => [
                'rack' => $rack->toGameState(),
            ],
            'message' => $rack->is_colocation_mode ? 'Colocation mode activated' : 'Colocation mode deactivated'
        ]);
    }
    /**
     * FEATURE 263: Update Rack lighting (RGB)
     */
    public function updateLighting(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'led_color' => 'required|string|max:20',
            'led_mode' => 'required|string|in:static,pulse,rainbow',
        ]);

        $user = $request->user();
        $rack = ServerRack::whereHas('room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        $rack->led_color = $request->led_color;
        $rack->led_mode = $request->led_mode;
        $rack->save();

        broadcast(new \App\Events\RackUpdated($user, $rack->fresh(['servers'])))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Rack lighting updated.',
            'data' => $rack->toGameState(),
        ]);
    }

    /**
     * FEATURE 64: Replace worn-out fans in a rack
     * Cost: $200 per rack. Restores fan_health to 100%.
     */
    public function replaceFans(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $rack = ServerRack::whereHas('room', fn($q) => $q->where('user_id', $user->id))->findOrFail($id);

        $pdu = $rack->pdu_status ?? [];
        $fanHealth = $pdu['fan_health'] ?? 100.0;

        if ($fanHealth >= 95) {
            return response()->json([
                'success' => false,
                'error' => 'Fans are still in good condition. Replacement not needed.',
            ], 400);
        }

        $cost = 200.00;
        $economy = $user->economy;

        if (!$economy->canAfford($cost)) {
            return response()->json([
                'success' => false,
                'error' => "Insufficient funds. Fan replacement costs \${$cost}.",
            ], 400);
        }

        $economy->debit($cost, "Fan Replacement: Rack {$rack->name}", 'maintenance');

        $pdu['fan_health'] = 100.0;
        $pdu['last_fan_replacement'] = now()->toIso8601String();
        $rack->pdu_status = $pdu;
        $rack->save();

        \App\Models\GameLog::log($user, "🔧 FAN_REPLACEMENT: Rack {$rack->name} fans replaced. Cooling restored to 100%.", 'success', 'infrastructure');

        return response()->json([
            'success' => true,
            'message' => "Fans replaced successfully. Cost: \${$cost}.",
            'data' => $rack->toGameState(),
        ]);
    }
}
