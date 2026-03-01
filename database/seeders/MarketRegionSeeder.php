<?php

namespace Database\Seeders;

use App\Models\MarketRegion;
use Illuminate\Database\Seeder;

class MarketRegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [
                'key' => 'na',
                'label' => 'North America',
                'gdp_growth' => 0.0280,
                'political_stability' => 82.00,
                'infra_saturation' => 65.00,
                'energy_cost_multiplier' => 1.100,
                'regulation_level' => 55.00,
                'demand_base' => 150,
                'demand_growth_factor' => 1.0180,
                'ip_pool_capacity' => 150000,
                'ip_pool_used' => 95000,
                'ix_available' => true,
                'ix_latency_bonus' => 3.00,
                'incident_rate_modifier' => 0.900,
                'cyber_threat_level' => 45.00,
            ],
            [
                'key' => 'eu',
                'label' => 'Europe',
                'gdp_growth' => 0.0200,
                'political_stability' => 85.00,
                'infra_saturation' => 55.00,
                'energy_cost_multiplier' => 1.350,
                'regulation_level' => 75.00,
                'demand_base' => 130,
                'demand_growth_factor' => 1.0150,
                'ip_pool_capacity' => 120000,
                'ip_pool_used' => 70000,
                'ix_available' => true,
                'ix_latency_bonus' => 4.00,
                'incident_rate_modifier' => 0.850,
                'cyber_threat_level' => 35.00,
            ],
            [
                'key' => 'apac',
                'label' => 'Asia-Pacific',
                'gdp_growth' => 0.0450,
                'political_stability' => 70.00,
                'infra_saturation' => 40.00,
                'energy_cost_multiplier' => 0.850,
                'regulation_level' => 45.00,
                'demand_base' => 180,
                'demand_growth_factor' => 1.0350,
                'ip_pool_capacity' => 200000,
                'ip_pool_used' => 80000,
                'ix_available' => true,
                'ix_latency_bonus' => 6.00,
                'incident_rate_modifier' => 1.100,
                'cyber_threat_level' => 55.00,
            ],
            [
                'key' => 'emerging',
                'label' => 'Emerging Markets',
                'gdp_growth' => 0.0600,
                'political_stability' => 55.00,
                'infra_saturation' => 20.00,
                'energy_cost_multiplier' => 0.700,
                'regulation_level' => 30.00,
                'demand_base' => 80,
                'demand_growth_factor' => 1.0500,
                'ip_pool_capacity' => 80000,
                'ip_pool_used' => 15000,
                'ix_available' => false,
                'ix_latency_bonus' => 0.00,
                'incident_rate_modifier' => 1.400,
                'cyber_threat_level' => 60.00,
            ],
        ];

        foreach ($regions as $region) {
            MarketRegion::updateOrCreate(
                ['key' => $region['key']],
                $region,
            );
        }
    }
}
