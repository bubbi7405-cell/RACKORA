<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_economy', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(5000.00); // Starting cash
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('hourly_income', 12, 2)->default(0);
            $table->decimal('hourly_expenses', 12, 2)->default(0);
            $table->decimal('reputation', 5, 2)->default(50.0); // 0-100
            $table->integer('experience_points')->default(0);
            $table->integer('level')->default(1);
            $table->decimal('power_price_per_kwh', 8, 4)->default(0.12);
            $table->decimal('bandwidth_cost_per_gbps', 10, 2)->default(100.0);
            $table->timestamp('last_income_tick')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_economy');
    }
};
