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
        Schema::create('market_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('seller_name');
            $table->string('item_type'); // server, rack, component
            $table->string('item_key');
            $table->integer('condition')->default(100);
            $table->decimal('price', 15, 2);
            $table->decimal('original_price', 15, 2);
            $table->timestamp('expires_at');
            $table->boolean('is_sold')->default(false);
            $table->json('specs')->nullable();
            $table->decimal('defect_chance', 5, 2)->default(0.0); // Hidden risk
            $table->timestamps();

            $table->index(['expires_at', 'is_sold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
    }
};
