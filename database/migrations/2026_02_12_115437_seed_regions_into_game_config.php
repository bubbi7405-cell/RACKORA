<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\GameConfig;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        GameConfig::updateOrCreate(
            ['key' => 'regions'],
            ['value' => [
                'us_east' => [
                    'name' => 'US East (N. Virginia)',
                    'flag' => '🇺🇸',
                    'base_power_cost' => 0.12,
                    'latency_modifier' => 1.0, 
                    'description' => 'Standard latency, average power costs. The default choice.',
                    'level_required' => 1
                ],
                'eu_central' => [
                    'name' => 'EU Central (Frankfurt)',
                    'flag' => '🇪🇺',
                    'base_power_cost' => 0.28, // High power cost!
                    'latency_modifier' => 0.8, // Better for EU customers
                    'description' => 'Expensive power, but great stability and data privacy.',
                    'level_required' => 5
                ],
                'asia_pacific' => [
                    'name' => 'Asia Pacific (Tokyo)',
                    'flag' => '🇯🇵',
                    'base_power_cost' => 0.18, 
                    'latency_modifier' => 1.2,
                    'description' => 'High tech infrastructure, moderate costs.',
                    'level_required' => 10
                ],
                'iceland' => [
                    'name' => 'Nordic (Reykjavik)',
                    'flag' => '🇮🇸',
                    'base_power_cost' => 0.05, // Extremely cheap power (Geothermal)
                    'latency_modifier' => 1.5, // High latency
                    'description' => 'Cheap green energy, but isolated network with higher latency.',
                    'level_required' => 15
                ]
            ]]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove config on rollback to preserve user edits
    }
};
