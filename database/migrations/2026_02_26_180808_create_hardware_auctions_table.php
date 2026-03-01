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
        Schema::create('hardware_auctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('item_type'); // 'server', 'rack', 'component', 'bulk_lot'
            $table->string('item_key');
            $table->json('item_specs')->nullable();
            $table->string('seller_name');
            
            $table->decimal('starting_price', 15, 2);
            $table->decimal('current_bid', 15, 2)->nullable();
            $table->foreignId('highest_bidder_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->integer('condition')->default(100);
            $table->decimal('defect_chance', 5, 2)->default(0.00);
            
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamps();

            $table->index(['is_processed', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_auctions');
    }
};
