<?php

namespace App\Services\Game;

use App\Models\WorldEvent;
use Illuminate\Support\Facades\Log;

class WorldEventService
{
    private const EVENT_TEMPLATES = [
        [
            'title' => 'Energy Crisis',
            'description' => 'Global energy prices are surging due to supply chain disruptions. Power costs increased by 50%.',
            'type' => 'crisis',
            'modifier_type' => 'power_cost',
            'modifier_value' => 1.5,
            'duration_minutes' => 60,
        ],
        [
            'title' => 'Tech Boom',
            'description' => 'A new AI startup craze is driving massive demand for servers. Order frequency increased by 30%.',
            'type' => 'boom',
            'modifier_type' => 'order_frequency',
            'modifier_value' => 1.3,
            'duration_minutes' => 45,
        ],
        [
            'title' => 'Hardware Shortage',
            'description' => 'A fire in a major semiconductor factory has caused a worldwide chipset shortage. Repair costs doubled.',
            'type' => 'crisis',
            'modifier_type' => 'repair_cost',
            'modifier_value' => 2.0,
            'duration_minutes' => 90,
        ],
        [
            'title' => 'Internet Holiday',
            'description' => 'Everyone is offline for a global digital detox day. Satisfaction decays slower.',
            'type' => 'news',
            'modifier_type' => 'satisfaction_decay',
            'modifier_value' => 0.5,
            'duration_minutes' => 30,
        ],
        [
            'title' => 'Cyber Monday',
            'description' => 'E-commerce demand is peaking! New order values are 20% higher.',
            'type' => 'boom',
            'modifier_type' => 'order_value',
            'modifier_value' => 1.2,
            'duration_minutes' => 120,
        ]
    ];

    /**
     * Update active world events and potentially trigger new ones.
     */
    public function tick(): void
    {
        // 1. Deactivate expired events
        WorldEvent::where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['is_active' => false]);

        // 2. Randomly trigger new events (e.g., 2% chance per tick if no active crisis)
        $activeCrisis = WorldEvent::where('is_active', true)->where('type', 'crisis')->exists();
        
        if (!$activeCrisis && rand(1, 100) <= 2) {
            $this->triggerRandomEvent();
        }
    }

    /**
     * Trigger a random event from templates.
     */
    public function triggerRandomEvent(): WorldEvent
    {
        $template = self::EVENT_TEMPLATES[array_rand(self::EVENT_TEMPLATES)];
        
        $event = WorldEvent::create([
            'title' => $template['title'],
            'description' => $template['description'],
            'type' => $template['type'],
            'modifier_type' => $template['modifier_type'],
            'modifier_value' => $template['modifier_value'],
            'starts_at' => now(),
            'ends_at' => now()->addMinutes($template['duration_minutes']),
            'is_active' => true,
        ]);

        Log::info("World Event Triggered: {$event->title}");
        
        return $event;
    }

    /**
     * Get active modifiers for easy access.
     */
    public function getModifiers(): array
    {
        return WorldEvent::getActiveModifiers();
    }
}
