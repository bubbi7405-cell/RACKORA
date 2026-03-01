<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();              // eu, na, apac, emerging
            $table->string('label');                       // "Europe", "North America" ...

            // Regional Economic Indicators
            $table->decimal('gdp_growth', 6, 4)->default(0.0250);         // 2.5% annual
            $table->decimal('political_stability', 5, 2)->default(80.00); // 0-100
            $table->decimal('infra_saturation', 5, 2)->default(45.00);    // 0-100 (how full the region is)
            $table->decimal('energy_cost_multiplier', 5, 3)->default(1.000);
            $table->decimal('regulation_level', 5, 2)->default(50.00);    // 0-100

            // Demand Parameters
            $table->integer('demand_base')->default(100);                 // Base demand units per tick
            $table->decimal('demand_growth_factor', 6, 4)->default(1.0200); // Compound growth

            // IP & Network Infrastructure
            $table->integer('ip_pool_capacity')->default(100000);
            $table->integer('ip_pool_used')->default(35000);
            $table->boolean('ix_available')->default(true);               // Internet exchange present
            $table->decimal('ix_latency_bonus', 5, 2)->default(5.00);     // ms reduction

            // Risk Factors
            $table->decimal('incident_rate_modifier', 5, 3)->default(1.000);
            $table->decimal('cyber_threat_level', 5, 2)->default(30.00);   // 0-100

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_regions');
    }
};
