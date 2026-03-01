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
        Schema::table('game_rooms', function (Blueprint $blueprint) {
            $blueprint->integer('redundancy_level')->default(1)->after('airflow_type');
            // Tier 1: None
            // Tier 2: N+1 (Active backup - small redundancy)
            // Tier 3: 2N (Full duplicated path)
            // Tier 4: 2(N+1) (High-Availability / Fault Tolerant)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $blueprint) {
            $blueprint->dropColumn('redundancy_level');
        });
    }
};
