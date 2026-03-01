<?php

namespace App\Events;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Achievement $achievement
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'achievement.unlocked';
    }

    public function broadcastWith(): array
    {
        return [
            'achievement' => [
                'name' => $this->achievement->name,
                'icon' => $this->achievement->icon,
                'description' => $this->achievement->description,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
