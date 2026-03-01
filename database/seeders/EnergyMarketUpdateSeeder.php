<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameConfig;

class EnergyMarketUpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch existing templates
        $templates = GameConfig::get('world_event_templates', []);
        
        $newTemplates = [
            [
                'title' => 'Heatwave: Asia Pacific',
                'description' => 'Extreme temperatures in Tokyo are straining the power grid. Cooling costs are skyrocketing.',
                'type' => 'crisis',
                'modifier_type' => 'energy_price:asia-east-tokyo',
                'modifier_value' => 1.8,
                'duration_minutes' => 60,
            ],
            [
                'title' => 'EU Green Deal Grants',
                'description' => 'New renewable energy subsidies have lowered power costs in Frankfurt.',
                'type' => 'boom',
                'modifier_type' => 'energy_price:eu-central-frankfurt',
                'modifier_value' => 0.7,
                'duration_minutes' => 90,
            ],
            [
                'title' => 'Grid Instability: US East',
                'description' => 'Rolling blackouts in Virginia due to storm damage. Spot prices are surging.',
                'type' => 'crisis',
                'modifier_type' => 'energy_price:us-east-virginia',
                'modifier_value' => 2.5,
                'duration_minutes' => 45,
            ]
        ];

        // Merge new templates, avoiding duplicates by title
        foreach ($newTemplates as $new) {
            $exists = false;
            foreach ($templates as $existing) {
                if ($existing['title'] === $new['title']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $templates[] = $new;
            }
        }

        // Save back to config
        GameConfig::set('world_event_templates', $templates, 'simulation', 'Templates for world-wide dynamic events.');
        
        $this->command->info('Energy Market Event Templates seeded successfully.');
    }
}
