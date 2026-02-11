<?php

namespace App\Events;

use App\Models\Server;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
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
        return 'server.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'server' => $this->server->toGameState(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
