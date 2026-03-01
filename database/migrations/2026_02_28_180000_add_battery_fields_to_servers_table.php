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
        Schema::table('servers', function (Blueprint $table) {
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable()->after('power_draw_kw');
            $table->decimal('battery_level_kwh', 8, 2)->nullable()->after('battery_capacity_kwh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['battery_capacity_kwh', 'battery_level_kwh']);
        });
    }
};
