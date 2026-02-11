<?php

namespace App\Events;

use App\Models\Server;
use App\Models\ServerRack;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public ServerRack $rack,
        public Server $server
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'server' => $this->server->toGameState(),
            'rack' => $this->rack->toGameState(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
