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
        Schema::table('server_racks', function (Blueprint $table) {
            $table->boolean('is_colocation_mode')->default(false);
            $table->integer('colocation_units')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_racks', function (Blueprint $table) {
            $table->dropColumn(['is_colocation_mode', 'colocation_units']);
        });
    }
};
