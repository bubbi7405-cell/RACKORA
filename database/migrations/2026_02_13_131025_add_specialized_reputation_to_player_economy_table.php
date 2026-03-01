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
            $table->json('specialized_reputation')->nullable()->after('reputation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn('specialized_reputation');
        });
    }
};
