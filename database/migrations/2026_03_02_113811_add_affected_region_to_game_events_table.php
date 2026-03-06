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
            $table->string('affected_region')->nullable()->after('affected_server_id')->index();
            $table->timestamp('expires_at')->nullable()->after('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropColumn(['affected_region', 'expires_at']);
        });
    }
};
