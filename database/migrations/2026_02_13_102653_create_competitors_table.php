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
        Schema::create('competitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('tagline')->nullable();
            
            // Stats
            $table->decimal('reputation', 5, 2)->default(50.00);
            $table->decimal('market_share', 5, 2)->default(0.00);
            
            // Strategy
            $table->string('pricing_strategy')->default('balanced'); // budget, balanced, premium
            $table->string('focus_sector')->default('web'); // gaming, enterprise, storage, web, ai
            
            // Aggressiveness (1-100)
            $table->integer('aggression')->default(50);
            
            // Visuals
            $table->string('color_primary')->default('#58a6ff');
            $table->string('logo_slug')->nullable();

            $table->string('status')->default('active'); // active, stagnant, bankrupt
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};
