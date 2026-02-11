<?php

namespace App\Events;

use App\Models\ServerRack;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RackUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public ServerRack $rack
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rack.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'rack' => $this->rack->toGameState(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
