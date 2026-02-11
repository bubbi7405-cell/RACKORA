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
            'server_type' => 'required|string|in:vserver_node,dedicated,gpu_server,storage_server',
            'model_key' => 'required|string',
            'target_slot' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $rack = ServerRack::with(['room', 'servers'])->findOrFail($request->rack_id);

        $result = $this->rackService->placeServer(
            $user,
            $rack,
            $request->server_type,
            $request->model_key,
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
}
