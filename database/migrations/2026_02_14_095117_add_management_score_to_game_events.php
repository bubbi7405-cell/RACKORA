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
        Schema::table('game_events', function (Blueprint $table) {
            $table->integer('management_score')->nullable()->after('damage_cost');
            $table->string('management_grade', 2)->nullable()->after('management_score');
            $table->decimal('action_cost', 12, 2)->default(0)->after('management_grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropColumn(['management_score', 'management_grade', 'action_cost']);
        });
    }
};
