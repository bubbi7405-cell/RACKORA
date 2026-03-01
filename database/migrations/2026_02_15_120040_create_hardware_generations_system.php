<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add generation tracking to servers
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('hardware_generation')->default(1)->after('model_name');
            $table->decimal('resale_value', 15, 2)->nullable()->after('purchase_cost');
        });

        // Hardware generations config table
        Schema::create('hardware_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('generation');
            $table->string('name');          // "Gen 1 (DDR4)", "Gen 2 (DDR5)", etc.
            $table->string('era');           // "legacy", "current", "nextgen"
            $table->decimal('efficiency_multiplier', 4, 2)->default(1.0);  // 1.0 = base, 1.3 = 30% better
            $table->decimal('power_multiplier', 4, 2)->default(1.0);       // Lower = more efficient
            $table->decimal('price_multiplier', 4, 2)->default(1.0);       // Higher = costs more
            $table->decimal('depreciation_rate', 4, 2)->default(0.05);     // Monthly depreciation %
            $table->json('bonuses')->nullable();                            // Special generation bonuses
            $table->boolean('is_available')->default(true);                // Can still be purchased
            $table->timestamp('released_at')->nullable();
            $table->timestamp('discontinued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['hardware_generation', 'resale_value']);
        });

        Schema::dropIfExists('hardware_generations');
    }
};
