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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('specialization')->nullable()->after('type');
        });

        Schema::table('player_economy', function (Blueprint $table) {
            $table->integer('spare_parts_count')->default(0)->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });

        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn('spare_parts_count');
        });
    }
};
