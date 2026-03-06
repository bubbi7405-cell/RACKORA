<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_networks', function (Blueprint $table) {
            $table->boolean('has_orbital_redundancy')->default(false)->after('sla_compliance_rate');
        });
    }

    public function down(): void
    {
        Schema::table('player_networks', function (Blueprint $table) {
            $table->dropColumn('has_orbital_redundancy');
        });
    }
};
