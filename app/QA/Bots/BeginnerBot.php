<?php

namespace App\QA\Bots;

use App\QA\BotPlayer;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\Rack;

class BeginnerBot extends BotPlayer
{
    public function getStrategyName(): string
    {
        return 'BEGINNER_BOT (Conservative Growth)';
    }

    public function tick(int $currentTick): void
    {
        $user = $this->getUser();
        $balance = $user->economy->balance;
        $rooms = $user->rooms;

        // 1. Check for Pending Orders (Accept if possible)
        $pendingOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->get();

        foreach ($pendingOrders as $order) {
            // Find a server that can handle this order
            $compatibleServer = $user->servers()
                ->where('status', 'online')
                ->where('type', $order->product_type)
                ->whereDoesntHave('orders') // Basic bot doesn't know about VServers yet
                ->first();

            if ($compatibleServer) {
                $this->performAction("orders/{$order->id}/accept", ['server_id' => $compatibleServer->id]);
            }
        }

        // 2. Slow Infrastructure Growth (Room -> Rack -> Server)
        if ($balance > 5000 && $rooms->isEmpty()) {
            // Buy first room
            $this->performAction('rooms/purchase', ['room_type' => 'basement', 'region' => 'us_east']);
        }

        foreach ($rooms as $room) {
            $racks = $room->racks;
            if ($racks->isEmpty() && $balance > 2500) {
                // Buy first rack
                $this->performAction('rack/purchase', ['room_id' => $room->id, 'rack_type' => 'rack_12u']);
                continue;
            }

            foreach ($racks as $rack) {
                $servers = $rack->servers;
                if ($servers->count() < 2 && $balance > 1500) {
                    // Buy a basic web server
                    $this->performAction('server/place', [
                        'rack_id' => $rack->id,
                        'server_type' => 'vserver_node',
                        'model_key' => 'vs_starter',
                        'target_slot' => $rack->units_total - $servers->count(),
                        'hardware_generation' => 1
                    ]);
                }
            }
        }

        // 3. Maintenance (Check health)
        $damagedServers = $user->servers()->whereIn('status', ['damaged', 'degraded'])->get();
        foreach ($damagedServers as $server) {
            if ($balance > 200) {
                $this->performAction('server/repair', ['server_id' => $server->id]);
            }
        }
    }
}
