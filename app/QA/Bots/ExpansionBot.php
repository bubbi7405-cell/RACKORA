<?php

namespace App\QA\Bots;

use App\QA\BotPlayer;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\Rack;
use App\Models\Server;

class ExpansionBot extends BotPlayer
{
    public function getStrategyName(): string
    {
        return 'EXPANSION_BOT (Rapid Scale)';
    }

    public function tick(int $currentTick): void
    {
        $user = $this->getUser();
        $balance = $user->economy->balance;
        $rooms = $user->rooms;

        // 1. Check for orders (Prioritize)
        $pendingOrders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->orderBy('price_per_month', 'desc')
            ->get();

        foreach ($pendingOrders as $order) {
            $compServer = $user->servers()
                ->where('status', 'online')
                ->where('type', $order->product_type)
                ->whereDoesntHave('orders')
                ->first();

            if ($compServer) {
                $this->performAction("orders/{$order->id}/accept", ['server_id' => $compServer->id]);
            }
        }

        // 2. Buy Rooms whenever possible (Aggressive expansion)
        if ($balance > 15000 && $rooms->isEmpty()) {
            $regions = ['us_east', 'us_west', 'eu_central', 'asia_east'];
            $loc = $regions[array_rand($regions)];
            $this->performAction('rooms/purchase', ['room_type' => 'garage', 'region' => $loc]);
        }

        // 3. Fill existing rooms with racks
        foreach ($rooms as $room) {
            if ($room->racks->count() < 10 && $balance > 3000) {
                $this->performAction('rack/purchase', ['room_id' => $room->id, 'rack_type' => 'rack_42u']);
            }

            foreach ($room->racks as $rack) {
                $servers = $rack->servers()->count();
                if ($servers < 42 && $balance > 1500) {
                    $this->performAction('server/place', [
                        'rack_id' => $rack->id,
                        'server_type' => 'dedicated',
                        'model_key' => 'ded_entry',
                        'target_slot' => $rack->units_total - $servers,
                        'hardware_generation' => 2
                    ]);
                }
            }
        }
    }
}
