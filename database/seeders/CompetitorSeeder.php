<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompetitorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $competitors = [
            [
                'name' => 'Macrosoft AzureGate',
                'personality' => 'industrial',
                'archetype' => 'premium_stability',
                'tagline' => 'Global Enterprise Dominance.',
                'reputation' => 85.00,
                'assets_value' => 50000000.00,
                'market_share' => 18.00,
                'regional_shares' => ['na' => 35.0, 'eu' => 30.0, 'apac' => 20.0, 'emerging' => 15.0],
                'pricing_strategy' => 'premium',
                'focus_sector' => 'enterprise',
                'headquarters_region' => 'na',
                'aggression' => 30,
                'intelligence' => 95,
                'color_primary' => '#0078d4',
                'player_enmity' => 0,
            ],
            [
                'name' => 'NeoNet Hosting',
                'personality' => 'aggressive',
                'archetype' => 'budget_volume',
                'tagline' => 'The fastest path to the web.',
                'reputation' => 55.00,
                'assets_value' => 12000000.00,
                'market_share' => 22.00,
                'regional_shares' => ['eu' => 40.0, 'na' => 25.0, 'emerging' => 25.0, 'apac' => 10.0],
                'pricing_strategy' => 'budget',
                'focus_sector' => 'web_hosting',
                'headquarters_region' => 'eu',
                'aggression' => 70,
                'intelligence' => 60,
                'color_primary' => '#58a6ff',
                'player_enmity' => 0,
            ],
            [
                'name' => 'GameSpike.io',
                'personality' => 'aggressive',
                'archetype' => 'aggressive_expander',
                'tagline' => 'Ultra-low latency for elites.',
                'reputation' => 65.00,
                'assets_value' => 8000000.00,
                'market_share' => 15.00,
                'regional_shares' => ['apac' => 40.0, 'na' => 30.0, 'eu' => 20.0, 'emerging' => 10.0],
                'pricing_strategy' => 'balanced',
                'focus_sector' => 'gaming',
                'headquarters_region' => 'apac',
                'aggression' => 90,
                'intelligence' => 40,
                'color_primary' => '#ff4d4f',
                'player_enmity' => 0,
            ],
            [
                'name' => 'VaultCloud Secure',
                'personality' => 'stealth',
                'archetype' => 'stealth_innovator',
                'tagline' => 'Secure. Immutable. Permanent.',
                'reputation' => 75.00,
                'assets_value' => 25000000.00,
                'market_share' => 14.00,
                'regional_shares' => ['na' => 30.0, 'eu' => 30.0, 'apac' => 25.0, 'emerging' => 15.0],
                'pricing_strategy' => 'premium',
                'focus_sector' => 'storage',
                'headquarters_region' => 'na',
                'aggression' => 20,
                'intelligence' => 85,
                'color_primary' => '#c41eff',
                'player_enmity' => 0,
            ],
            [
                'name' => 'Aurelius Finance',
                'personality' => 'balanced',
                'archetype' => 'premium_stability',
                'tagline' => 'The backbone of global banking.',
                'reputation' => 92.00,
                'assets_value' => 150000000.00,
                'market_share' => 12.00,
                'regional_shares' => ['eu' => 45.0, 'na' => 30.0, 'apac' => 15.0, 'emerging' => 10.0],
                'pricing_strategy' => 'premium',
                'focus_sector' => 'enterprise',
                'headquarters_region' => 'eu',
                'aggression' => 10,
                'intelligence' => 98,
                'color_primary' => '#ffd700',
                'player_enmity' => 0,
            ],
            [
                'name' => 'PonyExpress Cloud',
                'personality' => 'balanced',
                'archetype' => 'regional_specialist',
                'tagline' => 'Delivering data like lightning.',
                'reputation' => 40.00,
                'assets_value' => 5000000.00,
                'market_share' => 19.00,
                'regional_shares' => ['emerging' => 45.0, 'apac' => 30.0, 'na' => 15.0, 'eu' => 10.0],
                'pricing_strategy' => 'budget',
                'focus_sector' => 'web_hosting',
                'headquarters_region' => 'emerging',
                'aggression' => 50,
                'intelligence' => 50,
                'color_primary' => '#00ff9d',
                'player_enmity' => 0,
            ],
        ];

        foreach ($competitors as $c) {
            \App\Models\Competitor::updateOrCreate(['name' => $c['name']], $c);
        }
    }
}
