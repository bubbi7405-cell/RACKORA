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
            $table->decimal('cooling_health', 5, 2)->default(100.00)->after('max_cooling_kw');
            $table->string('airflow_type')->default('standard')->after('cooling_health'); // standard, hot_aisle, cold_aisle
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn(['cooling_health', 'airflow_type']);
        });
    }
};
