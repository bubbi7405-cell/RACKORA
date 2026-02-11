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
        Schema::create('game_statistics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('tick'); // Game tick count
            
            // Financials
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('expenses', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            
            // Status
            $table->decimal('reputation', 5, 2)->default(0);
            $table->integer('active_customers')->default(0);
            $table->integer('active_servers')->default(0);
            $table->decimal('avg_satisfaction', 5, 2)->default(0);
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_statistics');
    }
};
