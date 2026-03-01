<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\GameConfig;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = [
            'vserver_node' => [
                'vs_starter' => [
                    'modelName' => 'VNode Starter',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.3,
                    'heatOutputKw' => 0.25,
                    'cpuCores' => 8,
                    'ramGb' => 32,
                    'storageTb' => 1,
                    'bandwidthMbps' => 1000,
                    'vserverCapacity' => 4,
                    'purchaseCost' => 800,
                ],
                'vs_pro' => [
                    'modelName' => 'VNode Pro',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.6,
                    'heatOutputKw' => 0.5,
                    'cpuCores' => 32,
                    'ramGb' => 128,
                    'storageTb' => 4,
                    'bandwidthMbps' => 10000,
                    'vserverCapacity' => 16,
                    'purchaseCost' => 3500,
                ],
                'vs_enterprise' => [
                    'modelName' => 'VNode Enterprise',
                    'sizeU' => 4,
                    'powerDrawKw' => 1.2,
                    'heatOutputKw' => 1.0,
                    'cpuCores' => 128,
                    'ramGb' => 512,
                    'storageTb' => 8,
                    'bandwidthMbps' => 25000,
                    'vserverCapacity' => 48,
                    'purchaseCost' => 12000,
                ],
            ],
            'dedicated' => [
                'ded_basic' => [
                    'modelName' => 'Dedicated Basic',
                    'sizeU' => 1,
                    'powerDrawKw' => 0.35,
                    'heatOutputKw' => 0.3,
                    'cpuCores' => 4,
                    'ramGb' => 16,
                    'storageTb' => 2,
                    'bandwidthMbps' => 1000,
                    'purchaseCost' => 600,
                ],
                'ded_performance' => [
                    'modelName' => 'Dedicated Performance',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.8,
                    'heatOutputKw' => 0.7,
                    'cpuCores' => 16,
                    'ramGb' => 64,
                    'storageTb' => 4,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 2500,
                ],
                'ded_monster' => [
                    'modelName' => 'Dedicated Monster',
                    'sizeU' => 4,
                    'powerDrawKw' => 1.5,
                    'heatOutputKw' => 1.3,
                    'cpuCores' => 64,
                    'ramGb' => 256,
                    'storageTb' => 16,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 8000,
                ],
            ],
            'gpu_server' => [
                'gpu_compute' => [
                    'modelName' => 'GPU Compute',
                    'sizeU' => 4,
                    'powerDrawKw' => 2.5,
                    'heatOutputKw' => 2.2,
                    'cpuCores' => 32,
                    'ramGb' => 128,
                    'storageTb' => 4,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 25000,
                ],
                'gpu_ai' => [
                    'modelName' => 'GPU AI Cluster Node',
                    'sizeU' => 4,
                    'powerDrawKw' => 4.0,
                    'heatOutputKw' => 3.5,
                    'cpuCores' => 64,
                    'ramGb' => 512,
                    'storageTb' => 8,
                    'bandwidthMbps' => 100000,
                    'purchaseCost' => 80000,
                ],
            ],
            'storage_server' => [
                'storage_basic' => [
                    'modelName' => 'Storage Array Basic',
                    'sizeU' => 2,
                    'powerDrawKw' => 0.4,
                    'heatOutputKw' => 0.35,
                    'cpuCores' => 4,
                    'ramGb' => 16,
                    'storageTb' => 48,
                    'bandwidthMbps' => 10000,
                    'purchaseCost' => 3000,
                ],
                'storage_nas' => [
                    'modelName' => 'Enterprise NAS',
                    'sizeU' => 4,
                    'powerDrawKw' => 0.8,
                    'heatOutputKw' => 0.7,
                    'cpuCores' => 8,
                    'ramGb' => 64,
                    'storageTb' => 192,
                    'bandwidthMbps' => 25000,
                    'purchaseCost' => 15000,
                ],
            ],
        ];

        GameConfig::set('server_catalog', $catalog, 'hardware', 'Catalog of available servers in the shop');
    }

    public function down(): void
    {
        GameConfig::where('key', 'server_catalog')->delete();
    }
};
