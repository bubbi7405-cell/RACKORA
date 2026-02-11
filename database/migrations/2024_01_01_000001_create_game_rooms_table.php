<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // basement, garage, small_hall, data_center
            $table->string('name');
            $table->integer('level')->default(1);
            $table->integer('max_racks')->default(2);
            $table->integer('max_power_kw')->default(10);
            $table->integer('max_cooling_kw')->default(8);
            $table->integer('bandwidth_gbps')->default(1);
            $table->decimal('rent_per_hour', 10, 2)->default(0);
            $table->boolean('is_unlocked')->default(false);
            $table->json('position')->nullable(); // {x, y} for isometric grid
            $table->json('upgrades')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
