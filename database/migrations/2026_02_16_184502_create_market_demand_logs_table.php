<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_demand_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('tick');
            $table->string('region');                // eu, na, apac, emerging
            $table->string('sector');                // gaming, enterprise, storage, ai_compute, web_hosting
            $table->integer('demand_generated')->default(0);
            $table->integer('demand_served')->default(0);
            $table->integer('player_served')->default(0);
            $table->json('competitor_served')->nullable();  // { competitor_id: amount, ... }
            $table->integer('unmet_demand')->default(0);
            $table->decimal('avg_price', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tick', 'region', 'sector']);
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_demand_logs');
    }
};
