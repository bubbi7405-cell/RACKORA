<?php

namespace App\QA\Bots;

use App\QA\BotPlayer;
use App\Models\CustomerOrder;
use App\Models\Server;
use App\Models\Rack;

class ChaosBot extends BotPlayer
{
    public function getStrategyName(): string
    {
        return 'CHAOS_BOT (Non-Linear Testing)';
    }

    public function tick(int $currentTick): void
    {
        $user = $this->getUser();
        $balance = $user->economy->balance;
        $rooms = $user->rooms;

        $actions = ['buy_rack', 'toggle_power', 'repair_server', 'reject_order', 'accept_order'];
        $rndAction = $actions[array_rand($actions)];

        switch ($rndAction) {
            case 'buy_rack':
                $room = $rooms->random();
                if ($room) {
                    $this->performAction('rack/purchase', ['room_id' => $room->id, 'rack_type' => 'rack_42u']);
                }
                break;
            case 'toggle_power':
                $server = $user->servers()->get()->random();
                if ($server) {
                    $this->performAction("server/{$server->id}/power/toggle");
                }
                break;
            case 'repair_server':
                $server = $user->servers()->get()->random();
                if ($server) {
                    $this->performAction('server/repair', ['server_id' => $server->id]);
                }
                break;
            case 'reject_order':
                $order = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('status', 'pending')
                    ->get()
                    ->random();

                if ($order) {
                    $this->performAction("orders/{$order->id}/reject");
                }
                break;
            case 'accept_order':
                $order = CustomerOrder::whereHas('customer', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('status', 'pending')
                    ->get()
                    ->random();

                if ($order) {
                    $server = $user->servers()->get()->random();
                    if ($server) {
                        $this->performAction("orders/{$order->id}/accept", ['server_id' => $server->id]);
                    }
                }
                break;
        }
    }
}
