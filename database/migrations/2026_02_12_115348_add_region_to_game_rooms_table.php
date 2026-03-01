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
            $table->string('region')->default('unknown'); // e.g. 'us_east', 'eu_central'
            $table->decimal('power_cost_kwh', 8, 4)->nullable(); // Overrides current global cost if set
            $table->decimal('latency_ms', 5, 2)->default(50.00); // Base latency
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn(['region', 'power_cost_kwh', 'latency_ms']);
        });
    }
};
