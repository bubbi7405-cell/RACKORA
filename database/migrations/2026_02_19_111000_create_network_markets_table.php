<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_markets', function (Blueprint $table) {
            $table->id();
            $table->integer('ipv4_scarcity_index')->default(45);
            $table->decimal('ipv4_base_price', 15, 2)->default(2500);
            $table->decimal('global_demand_factor', 5, 2)->default(1.0);
            $table->timestamp('last_crash_at')->nullable();
            $table->timestamps();
        });

        // Initialize
        \App\Models\NetworkMarket::create([]);
    }

    public function down(): void
    {
        Schema::dropIfExists('network_markets');
    }
};
