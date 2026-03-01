<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Rackora — Broadcast full game state after each tick.
 * Uses ShouldBroadcastNow to bypass queue for real-time delivery.
 */
class GameTickProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $gameState;

    public function __construct(
        public User $user,
        array $gameState
    ) {
        $this->gameState = $gameState;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tick.processed';
    }

    public function broadcastWith(): array
    {
        return $this->gameState;
    }
}
