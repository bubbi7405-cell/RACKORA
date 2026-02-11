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
            $table->json('automation_settings')->nullable()->after('last_income_tick');
        });
    }

    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn('automation_settings');
        });
    }
};
