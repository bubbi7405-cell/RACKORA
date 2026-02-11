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
            $table->decimal('dust_level', 5, 2)->default(0.00)->after('temperature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_racks', function (Blueprint $table) {
            $table->dropColumn('dust_level');
        });
    }
};
