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
            $table->json('strategic_policies')->nullable()->after('automation_settings');
            $table->json('pending_decisions')->nullable()->after('strategic_policies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['strategic_policies', 'pending_decisions']);
        });
    }
};
