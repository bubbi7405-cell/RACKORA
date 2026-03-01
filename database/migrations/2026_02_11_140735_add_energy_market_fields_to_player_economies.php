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
            $table->string('energy_contract_type')->default('variable')->after('power_price_per_kwh');
            $table->decimal('energy_contract_price', 10, 4)->nullable()->after('energy_contract_type');
            $table->timestamp('energy_contract_expires_at')->nullable()->after('energy_contract_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_economy', function (Blueprint $table) {
            $table->dropColumn(['energy_contract_type', 'energy_contract_price', 'energy_contract_expires_at']);
        });
    }
};
