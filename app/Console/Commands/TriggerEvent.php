<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Enums\EventType;
use App\Services\Game\GameEventService;
use Illuminate\Console\Command;

class TriggerEvent extends Command
{
    protected $signature = 'game:trigger-event {user_id} {type?}';
    protected $description = 'Trigger a specific game event for a user';

    public function handle(GameEventService $eventService)
    {
        $userId = $this->argument('user_id');
        $type = $this->argument('type');

        $user = User::findOrFail($userId);

        if ($type) {
            // Force specific event logic
            // This requires making generate methods public or using reflection,
            // or adding a specific method in service.
            // For now, let's just use the random generation logic but force chance to 100% locally if possible,
            // or just add a direct create method.
            
            // Actually, let's just make a public helper in Service or use reflection.
            // I'll use reflection for quick hack access to private methods
            
            $methodName = match($type) {
                'hardware' => 'createHardwareFailure',
                'ddos' => 'createDdosAttack',
                'overheat' => 'createOverheating',
                default => null
            };

            if (!$methodName) {
                $this->error("Unknown event type: $type. Usage: hardware, ddos, overheat");
                return;
            }

            $reflection = new \ReflectionClass($eventService);
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);
            $method->invoke($eventService, $user);
            
            $this->info("Triggered $type event for user {$user->name}");
        } else {
            // Just tick logic but force it? No, tick logic has probability.
            $this->info("Please specify type: hardware, ddos, overheat");
        }
    }
}
