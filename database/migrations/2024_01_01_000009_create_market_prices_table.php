<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global market - shared between all players
        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->string('item_type'); // server, rack, upgrade, component
            $table->string('item_key'); // Unique identifier for the item
            $table->decimal('base_price', 12, 2);
            $table->decimal('current_price', 12, 2);
            $table->decimal('price_modifier', 6, 4)->default(1.0); // Market fluctuation
            $table->integer('stock_available')->default(-1); // -1 = unlimited
            $table->json('specs'); // Full item specifications
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['item_type', 'item_key']);
        });

        // Leaderboard / Rankings
        Schema::create('player_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('season'); // e.g., "2024-Q1"
            $table->integer('rank')->default(0);
            $table->decimal('score', 15, 2)->default(0);
            $table->integer('total_customers')->default(0);
            $table->integer('total_servers')->default(0);
            $table->decimal('uptime_percentage', 5, 2)->default(100.0);
            $table->integer('events_handled')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'season']);
            $table->index(['season', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_rankings');
        Schema::dropIfExists('market_prices');
    }
};
