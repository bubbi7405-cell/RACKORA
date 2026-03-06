<?php

namespace App\QA\Bots;

use App\QA\BotPlayer;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\Rack;
use App\Models\Server;

class AggressiveInvestorBot extends BotPlayer
{
    public function getStrategyName(): string
    {
        return 'AGGRESSIVE_INVESTOR (High leverage)';
    }

    public function tick(int $currentTick): void
    {
        $user = $this->getUser();
        $balance = $user->economy->balance;

        // 1. Spend everything on capacity
        if ($balance > 5000) {
             $rooms = $user->rooms;
             if ($rooms->isEmpty()) {
                 $this->performAction('rooms/purchase', ['location_id' => 'new_york_01']);
             }
             
             foreach ($rooms as $room) {
                 if ($room->racks->count() < 10) {
                      $this->performAction('rack/purchase', ['room_id' => $room->id, 'rack_type' => 'rack_42u']);
                 }
             }
        }

        // 2. Accept large orders only
        $orders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->where('price_per_month', '>', 5000)
            ->get();

        foreach ($orders as $order) {
            $server = $user->servers()
                ->where('status', 'online')
                ->where('type', $order->product_type)
                ->whereDoesntHave('orders')
                ->first();

            if ($server) {
                $this->performAction("orders/{$order->id}/accept", ['server_id' => $server->id]);
            }
        }
    }
}
