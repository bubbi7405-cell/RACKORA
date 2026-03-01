<?php

use App\Models\GameConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $components = [
            'cpu' => [
                'intel_xeon_e2224' => [
                    'name' => 'Intel Xeon E-2224',
                    'manufacturer' => 'Intel',
                    'cores' => 4,
                    'threads' => 4,
                    'clock_ghz' => 3.4,
                    'power_draw_w' => 71,
                    'heat_output_w' => 71,
                    'price' => 250,
                    'level_required' => 1,
                ],
                'amd_epyc_7302p' => [
                    'name' => 'AMD EPYC 7302P',
                    'manufacturer' => 'AMD',
                    'cores' => 16,
                    'threads' => 32,
                    'clock_ghz' => 3.0,
                    'power_draw_w' => 155,
                    'heat_output_w' => 155,
                    'price' => 950,
                    'level_required' => 5,
                ],
                'intel_xeon_gold_6248r' => [
                    'name' => 'Intel Xeon Gold 6248R',
                    'manufacturer' => 'Intel',
                    'cores' => 24,
                    'threads' => 48,
                    'clock_ghz' => 3.0,
                    'power_draw_w' => 205,
                    'heat_output_w' => 205,
                    'price' => 2450,
                    'level_required' => 10,
                ],
            ],
            'ram' => [
                'ddr4_16gb_ecc' => [
                    'name' => '16GB ECC DDR4',
                    'size_gb' => 16,
                    'power_draw_w' => 2,
                    'price' => 85,
                    'level_required' => 1,
                ],
                'ddr4_32gb_ecc' => [
                    'name' => '32GB ECC DDR4',
                    'size_gb' => 32,
                    'power_draw_w' => 4,
                    'price' => 160,
                    'level_required' => 3,
                ],
                'ddr4_64gb_ecc' => [
                    'name' => '64GB ECC DDR4',
                    'size_gb' => 64,
                    'power_draw_w' => 8,
                    'price' => 310,
                    'level_required' => 7,
                ],
            ],
            'storage' => [
                'hdd_1tb_ent' => [
                    'name' => '1TB Enterprise HDD',
                    'size_tb' => 1,
                    'type' => 'HDD',
                    'power_draw_w' => 7,
                    'price' => 50,
                    'level_required' => 1,
                ],
                'ssd_1tb_ent' => [
                    'name' => '1TB Enterprise SSD',
                    'size_tb' => 1,
                    'type' => 'SSD',
                    'power_draw_w' => 3,
                    'price' => 140,
                    'level_required' => 3,
                ],
                'nvme_2tb_ent' => [
                    'name' => '2TB NVMe SSD',
                    'size_tb' => 2,
                    'type' => 'NVMe',
                    'power_draw_w' => 5,
                    'price' => 450,
                    'level_required' => 8,
                ],
            ],
            'motherboard' => [
                'workstation_c242' => [
                    'name' => 'Workstation C242 (1U)',
                    'size_u' => 1,
                    'cpu_slots' => 1,
                    'ram_slots' => 4,
                    'storage_slots' => 2,
                    'price' => 180,
                    'level_required' => 1,
                ],
                'server_epyc_tier1' => [
                    'name' => 'Server EPYC T1 (2U)',
                    'size_u' => 2,
                    'cpu_slots' => 1,
                    'ram_slots' => 8,
                    'storage_slots' => 4,
                    'price' => 450,
                    'level_required' => 5,
                ],
                'enterprise_dual_xeon' => [
                    'name' => 'Enterprise Dual Xeon (2U)',
                    'size_u' => 2,
                    'cpu_slots' => 2,
                    'ram_slots' => 16,
                    'storage_slots' => 8,
                    'price' => 850,
                    'level_required' => 10,
                ],
            ]
        ];

        GameConfig::set('server_components', $components, 'hardware', 'Available components for modular server building');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        GameConfig::where('key', 'server_components')->delete();
    }
};
