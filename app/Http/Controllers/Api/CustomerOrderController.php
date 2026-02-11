<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Models\Server;
use App\Services\Game\CustomerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    protected $orderService;

    public function __construct(CustomerOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $pending = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($order) => $order->toGameState());

        $active = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereIn('status', ['active', 'provisioning'])
            ->with(['customer', 'server'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($order) => $order->toGameState());

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pending,
                'active' => $active,
            ],
        ]);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);

        $user = $request->user();
        $order = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('id', $id)
            ->firstOrFail();

        $server = Server::where('id', $request->server_id)->firstOrFail();

        try {
            $this->orderService->assignOrder($user, $order, $server);
            
            // Award XP for accepting an order
            $user->economy->addExperience(10);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'order' => $order->fresh()->toGameState(),
                    'server' => $server->fresh()->toGameState(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('id', $id)
            ->firstOrFail();

        if (!$order->isPending()) {
            return response()->json([
                'success' => false,
                'error' => 'Order is not pending.',
            ], 400);
        }

        $order->cancel(); // Or reject logic? Cancel is fine.

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Cancel an active order (release server, reputation penalty)
     */
    public function cancelActive(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('id', $id)
            ->whereIn('status', ['active', 'provisioning'])
            ->firstOrFail();

        // Release server assignment
        $server = $order->server;
        if ($server) {
            // Free VServer slot if applicable
            if ($server->type === \App\Enums\ServerType::VSERVER_NODE && $server->vservers_used > 0) {
                $server->vservers_used--;
                $server->save();
            }
        }

        // Cancel the order
        $order->status = 'cancelled';
        $order->assigned_server_id = null;
        $order->save();

        // Reputation penalty for breaking a contract
        $user->economy->adjustReputation(-5);

        // Register incident on customer
        $order->customer->registerIncident();

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled. Reputation -5.',
        ]);
    }
}
