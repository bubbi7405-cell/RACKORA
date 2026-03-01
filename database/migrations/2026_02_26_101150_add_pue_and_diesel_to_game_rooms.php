<?php

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
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->decimal('pue_score', 4, 2)->default(2.50)->after('cooling_intensity');
            $table->boolean('has_diesel_backup')->default(false)->after('pue_score');
            $table->integer('diesel_fuel_liters')->default(0)->after('has_diesel_backup');
            $table->integer('diesel_fuel_capacity')->default(1000)->after('diesel_fuel_liters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn(['pue_score', 'has_diesel_backup', 'diesel_fuel_liters', 'diesel_fuel_capacity']);
        });
    }
};
