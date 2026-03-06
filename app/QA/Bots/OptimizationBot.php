<?php

namespace App\QA\Bots;

use App\QA\BotPlayer;
use App\Models\CustomerOrder;
use App\Models\GameRoom;
use App\Models\Server;
use App\Models\Rack;

class OptimizationBot extends BotPlayer
{
    public function getStrategyName(): string
    {
        return 'OPTIMIZATION_BOT (Profit Efficiency Focus)';
    }

    public function tick(int $currentTick): void
    {
        $user = $this->getUser();
        $balance = $user->economy->balance;

        // 1. Research energy efficiency first
        if ($balance > 10000 && !$user->isResearched('advanced_cooling')) {
            $this->performAction('research', ['tech_id' => 'advanced_cooling']);
        }

        // 2. Only accept high ROI contracts (Price / Energy consumption)
        $orders = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->get()
            ->sortByDesc(fn ($order) => $order->price_per_month);

        foreach ($orders as $order) {
            $server = $user->servers()
                ->where('status', 'online')
                ->where('type', $order->product_type)
                ->whereDoesntHave('orders')
                ->first();

            if ($server) {
                // Check if ROI is worth it (Mock check for bot)
                if ($order->price_per_month > 1000) {
                     $this->performAction("orders/{$order->id}/accept", ['server_id' => $server->id]);
                }
            }
        }

        // 3. Selective hardware (12U for low fixed costs)
        if ($balance > 5000 && $user->rooms->isEmpty()) {
             $this->performAction('rooms/purchase', ['room_type' => 'basement', 'region' => 'eu_central']);
        }
    }
}
