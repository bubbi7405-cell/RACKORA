<?php

namespace App\Events;

use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameEventEscalated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public GameEvent $event
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'event.escalated';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event->toGameState(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
