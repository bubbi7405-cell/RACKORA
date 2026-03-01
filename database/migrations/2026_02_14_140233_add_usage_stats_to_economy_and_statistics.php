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
        // Add usage columns to PlayerEconomy for efficient fetching
        Schema::table('player_economy', function (Blueprint $table) {
            if (!Schema::hasColumn('player_economy', 'total_power_kw')) {
                $table->decimal('total_power_kw', 10, 4)->default(0)->after('hourly_expenses');
            }
            if (!Schema::hasColumn('player_economy', 'total_bandwidth_gbps')) {
                $table->decimal('total_bandwidth_gbps', 10, 4)->default(0)->after('total_power_kw');
            }
        });

        // Add history columns to GameStatistics for graphing
        Schema::table('game_statistics', function (Blueprint $table) {
            if (!Schema::hasColumn('game_statistics', 'power_usage')) {
                $table->decimal('power_usage', 10, 4)->default(0)->after('expenses');
            }
            if (!Schema::hasColumn('game_statistics', 'bandwidth_usage')) {
                $table->decimal('bandwidth_usage', 10, 4)->default(0)->after('power_usage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['total_power_kw', 'total_bandwidth_gbps']);
        });

        Schema::table('game_statistics', function (Blueprint $table) {
             $table->dropColumn(['power_usage', 'bandwidth_usage']);
        });
    }
};
