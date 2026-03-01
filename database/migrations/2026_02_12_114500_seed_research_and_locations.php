<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\GameConfig;
use App\Enums\RoomType;
use App\Services\Game\ResearchService;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed Research Tree
        // We reflectively access the constant since we haven't modified the class yet
        $techTree = ResearchService::TECH_TREE;
        GameConfig::set('research_tree', $techTree, 'gameplay', 'Tech tree definitions');

        // 2. Seed Location Definitions
        $locations = [];
        foreach (RoomType::cases() as $room) {
            $locations[$room->value] = [
                'name' => $room->label(),
                'max_racks' => $room->maxRacks(),
                'max_power_kw' => $room->maxPowerKw(),
                'max_cooling_kw' => $room->maxCoolingKw(),
                'bandwidth_gbps' => $room->bandwidthGbps(),
                'unlock_cost' => $room->unlockCost(),
                'rent_per_hour' => $room->rentPerHour(),
                'required_level' => $room->requiredLevel(),
                'dust_rate' => $room->dustRate(),
            ];
        }
        GameConfig::set('location_definitions', $locations, 'gameplay', 'Room/Location definitions');
    }

    public function down(): void
    {
        GameConfig::where('key', 'research_tree')->delete();
        GameConfig::where('key', 'location_definitions')->delete();
    }
};
