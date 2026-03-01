<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerRental;
use App\Services\Game\ServerRentalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\PlayerEconomy;

class MultiplayerController extends Controller
{
    public function __construct(
        protected ServerRentalService $rentalService
    ) {}

    /**
     * Get available rentals in the market.
     */
    public function getAvailableRentals()
    {
        $rentals = ServerRental::with(['provider.economy', 'server'])
            ->where('status', 'available')
            ->where('provider_id', '!=', Auth::id())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rentals->map(function ($r) {
                return [
                    'id' => $r->id,
                    'provider' => [
                        'name' => $r->provider->company_name ?? $r->provider->name,
                        'reputation' => $r->provider->economy ? $r->provider->economy->getSpecializedReputation('rental_reliability') : 0,
                    ],
                    'pricePerHour' => (float) $r->price_per_hour,
                    'server' => [
                        'id' => $r->server->id,
                        'model' => $r->server->model_name,
                        'cores' => $r->server->cpu_cores,
                        'ram' => $r->server->ram_gb,
                        'storage' => $r->server->storage_tb,
                        'bandwidth' => $r->server->bandwidth_mbps,
                        'generation' => $r->server->hardware_generation,
                    ]
                ];
            })
        ]);
    }

    /**
     * List a server for rent.
     */
    public function listServer(Request $request)
    {
        $request->validate([
            'serverId' => 'required|uuid|exists:servers,id',
            'pricePerHour' => 'required|numeric|min:0.01',
        ]);

        $server = Server::where('id', $request->serverId)->firstOrFail();

        // Verify rack/room exists and belongs to user
        if (!$server->rack || !$server->rack->room || $server->rack->room->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $rental = $this->rentalService->listForRent($server, $request->pricePerHour);
            return response()->json(['success' => true, 'data' => $rental]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Rent a server.
     */
    public function rentServer(Request $request)
    {
        $request->validate([
            'rentalId' => 'required|uuid|exists:server_rentals,id',
        ]);

        try {
            $rental = $this->rentalService->rentServer(Auth::user(), $request->rentalId);
            return response()->json(['success' => true, 'data' => $rental]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get my active rentals (as provider or tenant).
     */
    public function getMyRentals()
    {
        $asProvider = ServerRental::with(['tenant', 'server'])
            ->where('provider_id', Auth::id())
            ->where('status', 'rented')
            ->get();

        $asTenant = ServerRental::with(['provider', 'server'])
            ->where('tenant_id', Auth::id())
            ->where('status', 'rented')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'asProvider' => $asProvider,
                'asTenant' => $asTenant,
            ]
        ]);
    }

    /**
     * Terminate rental.
     */
    public function terminateRental($id)
    {
        try {
            $this->rentalService->terminateRental($id, Auth::user());
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get global leaderboard rankings.
     */
    public function getLeaderboard()
    {
        // Get top 20 players by balance + assets
        $topPlayers = PlayerEconomy::with('user')
            ->orderBy('balance', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topPlayers->map(function ($e, $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $e->user->company_name ?? $e->user->name,
                    'balance' => (float)$e->balance,
                    'reputation' => (float)$e->reputation,
                    'level' => (int)$e->level,
                    'marketShare' => (float)($e->global_market_share ?? 0),
                    'isMe' => $e->user_id === Auth::id()
                ];
            })
        ]);
    }
}
