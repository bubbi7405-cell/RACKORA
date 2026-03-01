<?php

namespace Database\Seeders;

use App\Models\HardwareGeneration;
use Illuminate\Database\Seeder;

class HardwareGenerationSeeder extends Seeder
{
    public function run(): void
    {
        $generations = [
            [
                'generation' => 1,
                'name' => 'Gen 1 — Legacy (DDR4 / Xeon v3)',
                'era' => 'legacy',
                'efficiency_multiplier' => 1.0,
                'power_multiplier' => 1.2,       // 20% MORE power
                'price_multiplier' => 0.5,        // 50% cheaper (discontinued)
                'depreciation_rate' => 0.10,      // Depreciates fast
                'bonuses' => ['cheap_entry' => true],
                'is_available' => true,            // Still sold as budget option
                'released_at' => now()->subMonths(24),
                'discontinued_at' => now()->subMonths(6),
            ],
            [
                'generation' => 2,
                'name' => 'Gen 2 — Standard (DDR5 / Xeon v5)',
                'era' => 'current',
                'efficiency_multiplier' => 1.3,   // 30% better performance
                'power_multiplier' => 1.0,        // Baseline
                'price_multiplier' => 1.0,        // Baseline
                'depreciation_rate' => 0.05,      // Normal depreciation
                'bonuses' => ['balanced' => true],
                'is_available' => true,
                'released_at' => now()->subMonths(6),
            ],
            [
                'generation' => 3,
                'name' => 'Gen 3 — Next-Gen (DDR5+ / EPYC Turin)',
                'era' => 'nextgen',
                'efficiency_multiplier' => 1.7,   // 70% better performance
                'power_multiplier' => 0.8,        // 20% LESS power
                'price_multiplier' => 2.0,        // 100% more expensive
                'depreciation_rate' => 0.03,      // Holds value well
                'bonuses' => ['ai_optimized' => true, 'efficiency_bonus' => 0.15],
                'is_available' => true,
                'released_at' => now(),
            ],
        ];

        foreach ($generations as $gen) {
            HardwareGeneration::updateOrCreate(
                ['generation' => $gen['generation']],
                $gen
            );
        }
    }
}
