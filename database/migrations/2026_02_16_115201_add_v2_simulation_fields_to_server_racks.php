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
            $table->json('thermal_map')->nullable()->after('temperature');
            $table->json('power_load_map')->nullable()->after('current_power_kw');
            $table->json('pdu_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('server_racks', function (Blueprint $table) {
            $table->dropColumn(['thermal_map', 'power_load_map', 'pdu_status']);
        });
    }
};
