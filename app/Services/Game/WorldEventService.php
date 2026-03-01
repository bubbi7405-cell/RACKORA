<?php

namespace App\Services\Game;

use App\Models\WorldEvent;
use Illuminate\Support\Facades\Log;

class WorldEventService
{
    /**
     * Get templates from database.
     */
    private function getTemplates(): array
    {
        return \App\Models\GameConfig::get('world_event_templates', []);
    }

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

        // 2. Randomly trigger new events
        $activeCount = WorldEvent::where('is_active', true)->count();
        
        // Max 3 active events at once
        if ($activeCount >= 3) return;

        // Global events: 5% chance per tick (if no active crisis)
        $activeCrisis = WorldEvent::where('is_active', true)->where('type', 'crisis')->exists();
        
        if (!$activeCrisis && rand(1, 100) <= 5) {
            $this->triggerRandomEvent();
        }

        // Regional events: 3% chance per tick (independent from crisis check)
        if (rand(1, 100) <= 3) {
            $this->triggerRegionalEvent();
        }
    }

    /**
     * Trigger a random global event from templates.
     */
    public function triggerRandomEvent(): WorldEvent
    {
        $templates = $this->getTemplates();
        if (empty($templates)) {
            throw new \Exception("No world event templates configured.");
        }
        
        // Filter to global-only templates (affected_regions is null or empty)
        $globalTemplates = array_filter($templates, fn($t) => empty($t['affected_regions']));
        if (empty($globalTemplates)) {
            $globalTemplates = $templates; // fallback
        }
        
        $template = $globalTemplates[array_rand($globalTemplates)];
        
        $event = WorldEvent::create([
            'title' => $template['title'],
            'description' => $template['description'],
            'type' => $template['type'],
            'modifier_type' => $template['modifier_type'],
            'modifier_value' => $template['modifier_value'],
            'affected_regions' => $template['affected_regions'] ?? null,
            'starts_at' => now(),
            'ends_at' => now()->addMinutes($template['duration_minutes']),
            'is_active' => true,
        ]);

        event(new \App\Events\WorldEventTriggered($event));
        
        // Broadcast via News Service
        $newsService = app(NewsService::class);
        $newsService->broadcastGlobal(
            "🌍 WELT-ALARM: {$event->title}. {$event->description}", 
            'breaking', 
            'WORLD'
        );

        Log::info("World Event Triggered: {$event->title}");
        
        return $event;
    }

    /**
     * Trigger a regional event that only affects specific regions.
     */
    public function triggerRegionalEvent(): WorldEvent
    {
        $regionalTemplates = $this->getRegionalTemplates();
        
        if (empty($regionalTemplates)) {
            // Fallback: pick a random region and create a basic event
            $regions = array_keys(\App\Models\GameConfig::get('regions', []));
            $region = $regions[array_rand($regions)];
            $regionalTemplates = [[
                'title' => 'Regionale Marktschwankung',
                'description' => 'Lokale Nachfrageänderungen in der Region.',
                'type' => 'info',
                'modifier_type' => 'order_value',
                'modifier_value' => 1.1,
                'affected_regions' => [$region],
                'duration_minutes' => 30,
            ]];
        }
        
        $template = $regionalTemplates[array_rand($regionalTemplates)];

        $event = WorldEvent::create([
            'title' => $template['title'],
            'description' => $template['description'],
            'type' => $template['type'],
            'modifier_type' => $template['modifier_type'],
            'modifier_value' => $template['modifier_value'],
            'affected_regions' => $template['affected_regions'],
            'starts_at' => now(),
            'ends_at' => now()->addMinutes($template['duration_minutes']),
            'is_active' => true,
        ]);

        event(new \App\Events\WorldEventTriggered($event));
        
        $regionNames = implode(', ', array_map('strtoupper', $template['affected_regions']));
        $newsService = app(NewsService::class);
        $newsService->broadcastGlobal(
            "📍 REGIONAL: {$event->title} (betrifft: {$regionNames}). {$event->description}", 
            'alert', 
            'REGION'
        );

        Log::info("Regional Event: {$event->title} → " . implode(', ', $template['affected_regions']));
        
        return $event;
    }

    /**
     * Get regional event templates from GameConfig (those with affected_regions set).
     */
    private function getRegionalTemplates(): array
    {
        $allTemplates = $this->getTemplates();
        return array_values(array_filter($allTemplates, fn($t) => !empty($t['affected_regions'])));
    }

    /**
     * Get active modifiers for easy access.
     */
    public function getModifiers(): array
    {
        return WorldEvent::getActiveModifiers();
    }

    /**
     * Get active modifiers for a specific region.
     */
    public function getModifiersForRegion(string $region): array
    {
        return WorldEvent::getActiveModifiersForRegion($region);
    }
}
