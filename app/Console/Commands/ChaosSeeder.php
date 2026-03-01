<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\GameEvent;
use App\Enums\EventType;
use App\Enums\EventStatus;
use App\Services\Game\CustomerOrderService;
use App\Services\Game\EventTriggerService;

class ChaosSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:chaos {email} {--customers=50} {--events=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'FEATURE 80: Generates high-stress scenarios (many customers, multiple concurrent events) to test dashboard performance.';

    /**
     * Execute the console command.
     */
    public function handle(CustomerOrderService $orderService, EventTriggerService $eventService)
    {
        $email = $this->argument('email');
        $customerCount = (int) $this->option('customers');
        $eventCount = (int) $this->option('events');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        $this->info("Initiating CHAOS SEEDER for user {$email}...");

        // 1. Generate Customers & Orders
        $this->line("Seeding {$customerCount} customers...");
        
        $user->economy->level = max(20, $user->economy->level); 
        $user->economy->save();

        for ($i = 0; $i < $customerCount; $i++) {
            $order = $orderService->generateNewOrder($user);
            
            // Auto-accept the order to create active contracts (skip provisioning for stress test)
            $order->status = 'active';
            $order->save();
            
            $order->customer->satisfaction = rand(20, 80);
            $order->customer->save();
        }

        // 2. Generate Events
        $this->line("Seeding {$eventCount} active crises...");
        
        $eventTypes = [
            EventType::DDOS_ATTACK,
            EventType::RANSOMWARE,
            EventType::HARDWARE_FAILURE,
            EventType::FIBER_CUT,
            EventType::DATA_BREACH
        ];

        // Ensure we have some servers to target
        $servers = \App\Models\Server::whereHas('rack.room', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->get();

        for ($i = 0; $i < $eventCount; $i++) {
            $type = $eventTypes[array_rand($eventTypes)];
            
            $title = match($type) {
                EventType::DDOS_ATTACK => "Massive Layer 7 HTTP Flood",
                EventType::RANSOMWARE => "CryptoLocker Variant Detected",
                EventType::HARDWARE_FAILURE => "Cascading Drive Failure",
                EventType::FIBER_CUT => "Main Uplink Severed",
                EventType::DATA_BREACH => "Unauthorized Admin Access",
                default => "Critical System Failure"
            };

            $event = GameEvent::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'description' => "CHAOS SEEDER INJECTED EVENT: System under heavy load!",
                'severity' => rand(1, 100) > 50 ? 'high' : 'critical',
                'status' => EventStatus::ACTIVE,
                'started_at' => now(),
                'expires_at' => now()->addMinutes(120),
                'affected_server_id' => $servers->isNotEmpty() ? $servers->random()->id : null,
                'progress' => rand(0, 30),
                'resolution_time_minutes' => rand(30, 180),
                'metadata' => [
                    'chaos_seeder' => true
                ]
            ]);
        }

        $this->info("Chaos seeded successfully. Good luck.");
        return 0;
    }
}
