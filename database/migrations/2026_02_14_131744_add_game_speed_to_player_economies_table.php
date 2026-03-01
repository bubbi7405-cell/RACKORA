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
        Schema::table('player_economy', function (Blueprint $table) {
            if (!Schema::hasColumn('player_economy', 'game_speed')) {
                $table->integer('game_speed')->default(1);
            }
            if (!Schema::hasColumn('player_economy', 'is_paused')) {
                $table->boolean('is_paused')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['game_speed', 'is_paused']);
        });
    }
};
