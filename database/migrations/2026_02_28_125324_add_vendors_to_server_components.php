<?php

use App\Models\GameConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $components = GameConfig::get('server_components', []);
        
        $vendors = [
            'Rack-Mate' => ['focus' => 'general', 'reputation' => 'reliable'],
            'PonyForce' => ['focus' => 'performance', 'reputation' => 'premium'],
            'TitanStorage' => ['focus' => 'storage', 'reputation' => 'durable'],
            'ServerGeneral' => ['focus' => 'budget', 'reputation' => 'cheap'],
        ];

        // Assign vendors to components
        foreach ($components as $type => &$items) {
            foreach ($items as $key => &$item) {
                if ($type === 'cpu') {
                    $item['vendor'] = ($item['manufacturer'] === 'Intel') ? 'Rack-Mate' : 'PonyForce';
                } elseif ($type === 'storage') {
                    $item['vendor'] = 'TitanStorage';
                } elseif ($type === 'ram') {
                    $item['vendor'] = 'ServerGeneral';
                } else {
                    $item['vendor'] = 'Rack-Mate';
                }
            }
        }

        GameConfig::set('server_components', $components);
        GameConfig::set('hardware_vendors', $vendors, 'hardware', 'List of hardware vendors for exclusivity deals');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_components', function (Blueprint $table) {
            //
        });
    }
};
