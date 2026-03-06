<?php

use App\Models\GameConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $components = GameConfig::get('server_components', []);

        // CPU
        $components['cpu']['intel_xeon_e2224']['mtbf_hours'] = 35000;
        $components['cpu']['amd_epyc_7302p']['mtbf_hours'] = 43800;
        $components['cpu']['intel_xeon_gold_6248r']['mtbf_hours'] = 52560;

        // RAM
        $components['ram']['ddr4_16gb_ecc']['mtbf_hours'] = 87600;
        $components['ram']['ddr4_32gb_ecc']['mtbf_hours'] = 105120;
        $components['ram']['ddr4_64gb_ecc']['mtbf_hours'] = 122640;

        // Storage
        $components['storage']['hdd_1tb_ent']['mtbf_hours'] = 26280;
        $components['storage']['ssd_1tb_ent']['mtbf_hours'] = 43800;
        $components['storage']['nvme_2tb_ent']['mtbf_hours'] = 52560;

        // Motherboard
        $components['motherboard']['workstation_c242']['mtbf_hours'] = 61320;
        $components['motherboard']['server_epyc_tier1']['mtbf_hours'] = 70080;
        $components['motherboard']['enterprise_dual_xeon']['mtbf_hours'] = 87600;

        GameConfig::set('server_components', $components);
    }

    public function down(): void
    {
        // No easy way to remove just the mtbf_hours without reloading everything, 
        // but for a game config it's fine.
    }
};
