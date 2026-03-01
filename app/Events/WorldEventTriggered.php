<?php

namespace App\Events;

use App\Models\WorldEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorldEventTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WorldEvent $event)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('world-events'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorldEventTriggered';
    }
}
