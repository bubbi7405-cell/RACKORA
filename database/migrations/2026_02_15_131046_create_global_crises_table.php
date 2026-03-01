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
        Schema::create('global_crises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // solar_flare, fiber_cut, crypto_ransom, market_crash
            $table->string('phase')->default('warning'); // warning, impact, aftermath, resolved
            $table->timestamp('started_at');
            $table->timestamp('impact_starts_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('severity')->default(1);
            $table->json('data')->nullable(); // For storing progress, affected counts, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_crises');
    }
};
