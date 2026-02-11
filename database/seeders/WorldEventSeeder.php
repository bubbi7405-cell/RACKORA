<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorldEvent;

class WorldEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WorldEvent::create([
            'title' => 'Global Expansion',
            'description' => 'Internet penetration is reaching record highs. Demand for new services is up 25%!',
            'type' => 'boom',
            'modifier_type' => 'order_frequency',
            'modifier_value' => 1.25,
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        WorldEvent::create([
            'title' => 'Sustainability Initiative',
            'description' => 'A new green energy subsidy has slightly lowered power costs for clean datacenters.',
            'type' => 'news',
            'modifier_type' => 'power_cost',
            'modifier_value' => 0.9,
            'starts_at' => now(),
            'ends_at' => now()->addHours(12),
            'is_active' => true,
        ]);
        
        WorldEvent::create([
            'title' => 'Tech Market Update',
            'description' => 'Investors are bullish on cloud infrastructure. Company reputation gains are slightly boosted.',
            'type' => 'news',
            'starts_at' => now(),
            'ends_at' => now()->addHours(6),
            'is_active' => true,
        ]);
    }
}
